#!/usr/bin/env python3
import sys
import os
import select
import socket
import json
import re
import subprocess
import tempfile
import base64
import time
from pathlib import Path

sys.stdout.reconfigure(line_buffering=True)

SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent

CIPHERSHELL_HOME = os.environ.get("CIPHERSHELL_HOME", str(Path.home() / ".ciphershell"))

# cache: username -> (filepath, fetch_time)
_pubkey_cache: dict[str, tuple[str, float]] = {}
PUBKEY_CACHE_TTL = 60

def load_config():
    config_file = Path(os.environ.get("CIPHERSHELL_ROOT", str(ROOT_DIR))) / "config" / "ciphershell.conf"
    if config_file.exists():
        with open(config_file) as f:
            for line in f:
                line = line.strip()
                if "=" in line:
                    key, val = line.split("=", 1)
                    os.environ[key] = val

USERNAME_RE = re.compile(r"^[a-z0-9_-]+$")

def validate_username(username):
    return bool(username) and USERNAME_RE.match(username)

def local_store():
    return CIPHERSHELL_HOME

def user_dir(username):
    return str(Path(local_store()) / username)

def private_key_path(username):
    return str(Path(local_store()) / username / "private.pem")

def public_key_path(username):
    return str(Path(local_store()) / username / "public.pem")

def server_public_key_path(username):
    return str(ROOT_DIR / "server" / "users.db" / f"{username}.pub")

def user_is_registered(username):
    missing = []
    if not os.path.exists(private_key_path(username)):
        missing.append(f"private_key={private_key_path(username)}")
    if not os.path.exists(public_key_path(username)):
        missing.append(f"public_key={public_key_path(username)}")
    if missing:
        print(f"[!] User '{username}' is missing keys:", file=sys.stderr)
        for m in missing:
            print(f"    {m}", file=sys.stderr)
        return False
    return True

def short_connect(host, port, command, timeout=5):
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(timeout)
    try:
        sock.connect((host, port))
        send_command(sock, command)
        reply = read_line(sock, timeout)
        return reply
    except Exception:
        return None
    finally:
        sock.close()

def upload_own_pubkey(username, host, port):
    pub_path = public_key_path(username)
    if not os.path.exists(pub_path):
        return False
    with open(pub_path, "rb") as f:
        pub_b64 = base64.b64encode(f.read()).decode("ascii")
    reply = short_connect(host, port, f"PUTKEY {username} {pub_b64}")
    if reply and reply.startswith("OK"):
        return True
    return False

def fetch_pubkey(username, host, port):
    now = time.time()
    cached = _pubkey_cache.get(username)
    if cached:
        cached_path, cached_time = cached
        if now - cached_time < PUBKEY_CACHE_TTL and os.path.exists(cached_path):
            return cached_path

    reply = short_connect(host, port, f"GETKEY {username}")
    if reply and reply.startswith("KEY "):
        key_b64 = reply[4:]
        dest = server_public_key_path(username)
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        with open(dest, "wb") as f:
            f.write(base64.b64decode(key_b64))
        os.chmod(dest, 0o644)
        _pubkey_cache[username] = (dest, now)
        return dest
    return None

downloads_dir = str(ROOT_DIR / "downloads")

def send_command(sock, cmd):
    sock.sendall((cmd + "\n").encode())

def read_line(sock, timeout=10):
    sock.settimeout(timeout)
    try:
        data = b""
        while True:
            c = sock.recv(1)
            if not c:
                return None
            if c == b"\n":
                break
            data += c
        return data.decode().strip()
    except socket.timeout:
        return None

def make_temp(suffix=""):
    fd, path = tempfile.mkstemp(suffix=suffix)
    os.close(fd)
    return path

def run_crypto(args, stdin=None, timeout=30):
    try:
        result = subprocess.run(args, capture_output=True, input=stdin, timeout=timeout)
    except subprocess.TimeoutExpired:
        cmd = " ".join(str(a) for a in args)
        raise RuntimeError(f"{cmd} timed out after {timeout}s")
    if result.returncode != 0:
        err = result.stderr.decode().strip() if result.stderr else ""
        cmd = " ".join(str(a) for a in args)
        if err:
            raise RuntimeError(f"{cmd} failed: {err}")
        raise RuntimeError(f"{cmd} exited with code {result.returncode}")
    return result

def encrypt_message(recipient_pub, sender_priv, sender, recipient, message):
    temp_payload = make_temp(".json")
    temp_b64 = make_temp()

    subprocess.run(["python3", str(SCRIPT_DIR / "payload_tool.py"), "build-message",
                   "--message", message, "--output", temp_payload], check=True)

    run_crypto(["bash", str(ROOT_DIR / "crypto/encrypt.sh"),
               recipient_pub, sender_priv, sender, recipient,
               temp_payload, temp_b64])

    with open(temp_b64) as f:
        b64 = f.read().strip()

    os.unlink(temp_payload)
    os.unlink(temp_b64)
    return b64

def decrypt_message(payload_b64, my_priv, sender_pub, sender, my_username):
    temp_b64 = make_temp()
    temp_plain = make_temp()

    with open(temp_b64, "w") as f:
        f.write(payload_b64)

    try:
        result = subprocess.run(
            ["bash", str(ROOT_DIR / "crypto/decrypt.sh"),
             my_priv, sender_pub, sender, my_username,
             temp_b64, temp_plain],
            capture_output=True, timeout=15,
        )
    except subprocess.TimeoutExpired:
        print("[!] decrypt_message timed out", file=sys.stderr)
        os.unlink(temp_b64)
        if os.path.exists(temp_plain):
            os.unlink(temp_plain)
        return None

    if result.returncode == 0 and os.path.exists(temp_plain):
        with open(temp_plain) as f:
            plain = f.read()
        os.unlink(temp_b64)
        os.unlink(temp_plain)
        return plain

    err = result.stderr.decode().strip() if result.stderr else ""
    if err:
        print(f"[!] Decryption error: {err}")
    elif result.returncode != 0:
        print(f"[!] Decrypt process exited with code {result.returncode}")
    os.unlink(temp_b64)
    if os.path.exists(temp_plain):
        os.unlink(temp_plain)
    return None

def render_message(plain, sender, username):
    temp = make_temp()
    with open(temp, "w") as f:
        f.write(plain)
    try:
        result = subprocess.run(
            ["python3", str(SCRIPT_DIR / "payload_tool.py"), "render",
             "--username", username, "--sender", sender,
             "--input", temp, "--downloads-root", downloads_dir],
            capture_output=True, text=True, timeout=10,
        )
        if result.returncode == 0:
            sys.stdout.write(result.stdout)
            sys.stdout.flush()
        else:
            err = result.stderr.strip()
            if err:
                print(f"[!] render error: {err}", file=sys.stderr)
            else:
                print(f"[!] Could not display message from {sender}.", file=sys.stderr)
    except subprocess.TimeoutExpired:
        print(f"[!] render_message timed out for message from {sender}.", file=sys.stderr)
    os.unlink(temp)

def main():
    if len(sys.argv) < 2:
        print("Usage: interactive_chat.py <username> [host] [port]", file=sys.stderr)
        sys.exit(1)

    username = sys.argv[1]
    host = sys.argv[2] if len(sys.argv) > 2 else None
    port = int(sys.argv[3]) if len(sys.argv) > 3 else None

    load_config()
    host = host or os.environ.get("SERVER_HOST", "127.0.0.1")
    port = port or int(os.environ.get("SERVER_PORT", "500"))

    if not validate_username(username):
        sys.exit(1)
    if not user_is_registered(username):
        print(f"User '{username}' is not registered. Run: bash client/register.sh {username}")
        sys.exit(1)

    # Upload our public key to the server (separate connection)
    upload_own_pubkey(username, host, port)

    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    try:
        sock.connect((host, port))
    except Exception as e:
        print(f"Could not connect to {host}:{port}. Start server first.")
        sys.exit(1)

    sock.settimeout(None)
    send_command(sock, f"LOGIN {username}")
    reply = read_line(sock, 10)
    if not reply or not reply.startswith("OK"):
        print(f"Login failed: {reply}")
        sys.exit(1)

    os.system("clear")
    print("==========================================")
    print("  CipherShell Interactive Chat")
    print(f"  Logged in as: {username}")
    print(f"  Server: {host}:{port}")
    print("==========================================")
    print("Type messages and press Enter to send.")
    print("Commands: /to <user>  /quit")
    print("Press Ctrl+C to exit.")
    print("==========================================")
    print()

    recipient = None

    def select_recipient():
        nonlocal recipient
        print()
        while True:
            try:
                r = input("recipient> ").strip()
            except EOFError:
                print("Goodbye.")
                sys.exit(0)
            if r == "/quit":
                print("Goodbye.")
                sys.exit(0)
            if validate_username(r):
                recipient = r
                break
            print("[!] Invalid username.")

    select_recipient()

    print(f"[>] Chatting with: {recipient}")

    try:
        while True:
            readsock = [sys.stdin, sock]
            r, _, _ = select.select(readsock, [], [], 0.1)

            if sock in r:
                line = read_line(sock, 1)
                if not line:
                    print("\n[!] Connection closed.")
                    break

                if line.startswith("FROM "):
                    parts = line.split(" ", 2)
                    if len(parts) >= 3:
                        sender = parts[1]
                        payload = parts[2]

                        sender_pub = server_public_key_path(sender)
                        my_priv = private_key_path(username)

                        def try_decrypt(key_path):
                            try:
                                plain = decrypt_message(payload, my_priv, key_path, sender, username)
                                if plain is not None:
                                    print()
                                    print(f"[+] Message from {sender}:")
                                    render_message(plain, sender, username)
                                else:
                                    print(f"[!] Could not decrypt message from {sender}")
                                return True
                            except Exception:
                                return False

                        decrypted = False
                        if os.path.exists(sender_pub):
                            decrypted = try_decrypt(sender_pub)

                        if not decrypted:
                            print(f"[!] Decryption with local key failed. Fetching fresh key from server...")
                            fresh = fetch_pubkey(sender, host, port)
                            if fresh:
                                decrypted = try_decrypt(fresh)

                        if not decrypted:
                            print(f"\n[+] Could not decrypt message from {sender}")
                else:
                    print(f"\n[*] {line}")

            if sys.stdin in r:
                try:
                    message = input("> ")
                except EOFError:
                    print("Goodbye.")
                    break

                if not message.strip():
                    continue

                if message.strip() == "/quit":
                    print("Goodbye.")
                    break
                elif message.strip() == "/to":
                    select_recipient()
                    print(f"[>] Chatting with: {recipient}")
                else:
                    try:
                        recipient_pub = server_public_key_path(recipient)
                        # Fetch latest key (uses 60s cache to avoid network on every send)
                        if not fetch_pubkey(recipient, host, port) and not os.path.exists(recipient_pub):
                            print(f"[!] Public key not found for: {recipient}")
                            continue

                        b64 = encrypt_message(recipient_pub, private_key_path(username),
                                             username, recipient, message)
                        send_command(sock, f"SEND {recipient} {b64}")
                    except Exception as e:
                        print(f"[!] Error: {e}")

    except KeyboardInterrupt:
        print("\nGoodbye.")
    except (select.error, OSError, BrokenPipeError) as e:
        print(f"\n[!] Connection error: {e}")
    finally:
        sock.close()

if __name__ == "__main__":
    main()