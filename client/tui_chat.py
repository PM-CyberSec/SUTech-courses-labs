#!/usr/bin/env python3
import sys
import os
import socket
import json
import re
import subprocess
import tempfile
import base64
import time
import threading
import queue
from pathlib import Path

SCRIPT_DIR = Path(__file__).parent
ROOT_DIR = SCRIPT_DIR.parent

CIPHERSHELL_HOME = os.environ.get("CIPHERSHELL_HOME", str(Path.home() / ".ciphershell"))

_key_cache: dict[str, tuple[str, float]] = {}
KEY_CACHE_TTL = 60
CALLSIGN_RE = re.compile(r"^[a-z0-9_-]+$")

# ── Shared helpers ──────────────────────────────────────────────────

def load_config():
    config_file = Path(os.environ.get("CIPHERSHELL_ROOT", str(ROOT_DIR))) / "config" / "ciphershell.conf"
    if config_file.exists():
        with open(config_file) as f:
            for line in f:
                line = line.strip()
                if "=" in line:
                    key, val = line.split("=", 1)
                    os.environ[key] = val

def validate_callsign(callsign):
    return bool(callsign) and CALLSIGN_RE.match(callsign)

def private_key_path(username):
    return str(Path(CIPHERSHELL_HOME) / username / "private.pem")

def public_key_path(username):
    return str(Path(CIPHERSHELL_HOME) / username / "public.pem")

def server_public_key_path(username):
    return str(ROOT_DIR / "server" / "users.db" / f"{username}.pub")

def ident_is_enlisted(callsign):
    missing = []
    if not os.path.exists(private_key_path(callsign)):
        missing.append(f"private_key={private_key_path(callsign)}")
    if not os.path.exists(public_key_path(callsign)):
        missing.append(f"public_key={public_key_path(callsign)}")
    if missing:
        return False
    return True

downloads_dir = str(ROOT_DIR / "downloads")

def send_command(sock, cmd):
    sock.sendall((cmd + "\n").encode())

def read_line(sock, timeout=10):
    sock.settimeout(timeout)
    data = b""
    while True:
        c = sock.recv(1)
        if not c:
            return None
        if c == b"\n":
            break
        data += c
    return data.decode().strip()

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
            return result.stdout.strip()
        else:
            err = result.stderr.strip()
            if err:
                return f"<render error: {err}>"
            return "<could not display message>"
    except subprocess.TimeoutExpired:
        return "<render timed out>"
    finally:
        if os.path.exists(temp):
            os.unlink(temp)

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
    return bool(reply and reply.startswith("OK"))

def fetch_pubkey(callsign, host, port):
    now = time.time()
    cached = _key_cache.get(callsign)
    if cached:
        cached_path, cached_time = cached
        if now - cached_time < KEY_CACHE_TTL and os.path.exists(cached_path):
            return cached_path
    reply = short_connect(host, port, f"GETKEY {callsign}")
    if reply and reply.startswith("KEY "):
        key_b64 = reply[4:]
        dest = server_public_key_path(callsign)
        os.makedirs(os.path.dirname(dest), exist_ok=True)
        with open(dest, "wb") as f:
            f.write(base64.b64decode(key_b64))
        os.chmod(dest, 0o644)
        _key_cache[callsign] = (dest, now)
        return dest
    return None

# ── TUI Chat ────────────────────────────────────────────────────────

import curses

def _parse_signal(signal):
    lines = signal.split("\n")
    kind = ""
    body = signal
    for line in lines:
        stripped = line.strip()
        if stripped == "New Message":
            kind = "message"
        elif stripped == "New File":
            kind = "file"
        elif stripped.startswith("Message: "):
            body = stripped[len("Message: "):]
            break
        elif stripped.startswith("Saved: "):
            body = stripped
            break
        elif stripped.startswith("Size: "):
            body = lines[-2].strip() if len(lines) > 2 else body
    return body

HUB = 1
CHANNEL = 2

class Packet:
    def __init__(self, direction, source, text, timestamp="", status=""):
        self.direction = direction
        self.source = source
        self.text = text
        self.timestamp = timestamp or time.strftime("%H:%M:%S")
        self.status = status

    def encode(self, mw):
        prefix = f"[{self.timestamp}]"
        if self.direction == "in":
            line = f" {prefix} [{self.source}] {self.text}"
        else:
            line = f" {prefix} [{self.source}->] {self.text}"
        if self.direction == "out":
            if self.status == "sending":
                line += " \u25B3"
            elif self.status == "sent":
                line += " \u25B3"
            elif self.status == "delivered":
                line += " \u2713"
            elif self.status == "failed":
                line += " \u2717"
        max_w = mw - 3
        if len(line) > max_w:
            line = line[:max_w-3] + "..."
        return line

class CyberDeck:
    def __init__(self, stdscr, callsign=None):
        self.stdscr = stdscr
        self.callsign = callsign or ""
        self.relay = ""
        self.chan = ""
        self.target = ""
        self.screen = HUB
        self.running = True
        self.linked = False
        self.online_users: set[str] = set()

        self.transcript: list[Packet] = []
        self.compose = ""
        self.syslog = ["Welcome to NeonNet"]

        self.wire = None
        self.link_thread = None
        self.event_q: queue.Queue = queue.Queue()
        self.net_lock = threading.Lock()
        self._kill_link = False

        self.form_callsign = ""
        self.form_relay = ""
        self.form_chan = ""
        self.focus = 0

        self.msg_win = None
        self.input_win = None
        self.status_win = None

    def log(self, text):
        self.syslog.append(text)
        if len(self.syslog) > 100:
            self.syslog = self.syslog[-50:]

    def init_console(self):
        curses.use_default_colors()
        self.stdscr.keypad(True)
        curses.curs_set(0)
        self._recalc()

    def _recalc(self):
        h, w = self.stdscr.getmaxyx()
        if h < 10 or w < 20:
            return
        msg_h = max(h * 3 // 5, 2)
        inp_h = 3
        sts_h = h - msg_h - inp_h
        self.msg_win = curses.newwin(msg_h, w, 0, 0)
        self.input_win = curses.newwin(inp_h, w, msg_h, 0)
        self.status_win = curses.newwin(sts_h, w, msg_h + inp_h, 0)
        self.msg_win.scrollok(True)
        self.status_win.scrollok(True)

    def render(self):
        if self.screen == HUB:
            self._render_hub()
        else:
            self._render_transcript()
            self._render_composer()
            self._render_syslog()
        curses.doupdate()

    def _render_hub(self):
        self.stdscr.erase()
        h, w = self.stdscr.getmaxyx()
        title = "NeonNet CyberDeck"
        self.stdscr.addstr(h // 2 - 6, (w - len(title)) // 2, title, curses.A_BOLD)

        fields = [
            ("Callsign:", self.form_callsign),
            ("Relay:",     self.form_relay),
            ("Channel:",   self.form_chan),
        ]
        labels = ["Callsign:", "Relay:", "Channel:"]
        start_y = h // 2 - 3
        for i, (label, val) in enumerate(fields):
            y = start_y + i * 2
            attr = curses.A_REVERSE if i == self.focus else 0
            self.stdscr.addstr(y, w // 2 - 20, f"  {label}  ")
            display = val if val else "(type here)"
            self.stdscr.addstr(y, w // 2 - 5, f"[ {display:<20} ]", attr)

        btn_y = start_y + 7
        btn_attr = curses.A_REVERSE if self.focus == 3 else 0
        conn_label = "JACK IN" if not self.linked else "JACK OUT"
        self.stdscr.addstr(btn_y, w // 2 - 5, f"  {conn_label}  ", btn_attr | curses.A_BOLD)
        self.stdscr.addstr(btn_y + 2, w // 2 - 5, "  QUIT  ",
                          curses.A_REVERSE if self.focus == 4 else 0)

        status = "Link: "
        if self.linked:
            status += f"Online @ {self.relay}:{self.chan} as {self.callsign}"
        else:
            status += "Offline"
        self.stdscr.addstr(btn_y + 5, w // 2 - 20, status)

        log_y = btn_y + 7
        for line in self.syslog[-4:]:
            if log_y >= h - 3:
                break
            self.stdscr.addstr(log_y, w // 2 - 20, line[:40])
            log_y += 1

        cmd_hint = "Tab/Arrows: navigate   Enter: select   Ctrl+C: quit"
        self.stdscr.addstr(h - 2, (w - len(cmd_hint)) // 2, cmd_hint, curses.A_DIM)

    def _render_transcript(self):
        if not self.msg_win:
            return
        self.msg_win.erase()
        mh, mw = self.msg_win.getmaxyx()
        self.msg_win.box()

        status_indicator = " \u25CF " if self.linked else " \u25CB "
        header = f" Transcript {status_indicator}{self.relay}:{self.chan} "
        if self.linked and self.online_users:
            online_count = len(self.online_users) - (1 if self.callsign in self.online_users else 0)
            header += f"[{online_count} online] "
        if self.target:
            header += f"-> {self.target} "
        self.msg_win.addstr(0, 2, header[:mw-4])

        y = mh - 2
        for pkt in reversed(self.transcript):
            if y < 1:
                break
            try:
                self.msg_win.addstr(y, 1, pkt.encode(mw))
            except curses.error:
                pass
            y -= 1
        self.msg_win.noutrefresh()

    def _render_composer(self):
        if not self.input_win:
            return
        self.input_win.erase()
        ih, iw = self.input_win.getmaxyx()
        self.input_win.box()
        prompt = f" {self.callsign}> "
        max_text = iw - len(prompt) - 2
        visible = self.compose
        if len(visible) > max_text:
            visible = visible[-(max_text):]
        try:
            self.input_win.addstr(1, 1, prompt + visible)
        except curses.error:
            pass
        self.input_win.noutrefresh()

    def _render_syslog(self):
        if not self.status_win:
            return
        self.status_win.erase()
        sh, sw = self.status_win.getmaxyx()
        self.status_win.box()
        self.status_win.addstr(0, 2, " Syslog ")
        y = sh - 2
        for line in reversed(self.syslog):
            if y < 1:
                break
            text = line[:sw-2]
            try:
                self.status_win.addstr(y, 1, text)
            except curses.error:
                pass
            y -= 1
        self.status_win.noutrefresh()

    # ── Network ─────────────────────────────────────────────────

    def _jack_in(self, relay, channel):
        if self.linked:
            self._jack_out()

        self.relay = relay
        self.chan = channel
        self._kill_link = False
        self._link_ready = threading.Event()

        self.link_thread = threading.Thread(target=self._netloop, daemon=True)
        self.link_thread.start()
        ok = self._link_ready.wait(timeout=10)
        if not ok:
            self.log("Link timed out")
        return self.linked

    def _jack_out(self):
        self._kill_link = True
        with self.net_lock:
            if self.wire:
                try:
                    self.wire.close()
                except OSError:
                    pass
                self.wire = None
        if self.link_thread:
            self.link_thread.join(timeout=2)
        self.linked = False
        self.log("Jacked out")

    def _netloop(self):
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        try:
            sock.connect((self.relay, int(self.chan)))
        except Exception as e:
            self.event_q.put(f"ERROR: Connection failed: {e}")
            self._link_ready.set()
            return
        sock.settimeout(None)

        upload_own_pubkey(self.callsign, self.relay, int(self.chan))
        send_command(sock, f"LOGIN {self.callsign}")
        reply = read_line(sock, 10)
        if not reply or not reply.startswith("OK"):
            self.event_q.put(f"ERROR: Login failed: {reply}")
            sock.close()
            self._link_ready.set()
            return

        with self.net_lock:
            self.wire = sock
        self.linked = True
        self.event_q.put("CONNECTED")
        self._link_ready.set()

        while not self._kill_link:
            try:
                line = read_line(sock, 1)
            except socket.timeout:
                continue
            except OSError:
                break
            if line is None:
                continue
            if line.startswith("FROM "):
                parts = line.split(" ", 2)
                if len(parts) >= 3:
                    self.event_q.put(f"MSG_IN:{parts[1]}:{parts[2]}")
            elif line.startswith("OK "):
                self.event_q.put(f"DATA:{line}")

        with self.net_lock:
            if self.wire is sock:
                self.wire = None
        self.linked = False
        self._link_ready.set()
        self.event_q.put("DISCONNECTED")
        sock.close()

    def _drain_events(self):
        while not self.event_q.empty():
            item = self.event_q.get_nowait()
            if item == "CONNECTED":
                self.log(f"Linked to {self.relay}:{self.chan}")
                # auto-scan on connect
                with self.net_lock:
                    if self.wire:
                        send_command(self.wire, "WHO")
            elif item.startswith("ERROR:"):
                self.log(item[6:])
            elif item == "DISCONNECTED":
                self.log("Link lost")
            elif item.startswith("MSG_IN:"):
                _, sender, payload = item.split(":", 2)
                self._intercept(sender, payload)
            elif item.startswith("DATA:"):
                data = item[5:]
                if data.startswith("OK ONLINE "):
                    users = data[10:].strip()
                    if users:
                        self.online_users = set(users.split())
                    else:
                        self.online_users = set()
                    if self.online_users:
                        self.log(f"Online: {', '.join(sorted(self.online_users))}")
                    else:
                        self.log("No other users online")
                elif data.startswith("OK "):
                    self.log(f"Server: {data[3:]}")

    def _intercept(self, source, payload):
        source_pub = server_public_key_path(source)
        my_priv = private_key_path(self.callsign)

        def try_decrypt(key_path):
            try:
                plain = decrypt_message(payload, my_priv, key_path, source, self.callsign)
                if plain is not None:
                    rendered = render_message(plain, source, self.callsign) or plain
                    display = _parse_signal(rendered)
                    self.transcript.append(Packet("in", source, display))
                    self.log(f"Packet from {source}")
                    return True
            except Exception:
                pass
            return False

        decrypted = False
        if os.path.exists(source_pub):
            decrypted = try_decrypt(source_pub)
        if not decrypted:
            fresh = fetch_pubkey(source, self.relay, int(self.chan))
            if fresh:
                decrypted = try_decrypt(fresh)
        if not decrypted:
            self.transcript.append(Packet("in", source, "<could not decrypt>", status="failed"))
            self.log(f"Could not decrypt packet from {source}")

    def _transmit(self, text):
        if not self.linked:
            self.log("Not linked. Use /connect <relay> <channel> first")
            return
        if not self.target:
            self.log("No target set. Use /to <callsign>")
            return
        target_pub = server_public_key_path(self.target)
        fetch_pubkey(self.target, self.relay, int(self.chan))
        if not os.path.exists(target_pub):
            self.log(f"No key for {self.target}")
            self.transcript.append(Packet("out", self.callsign, text, status="failed"))
            return
        try:
            b64 = encrypt_message(target_pub, private_key_path(self.callsign),
                                 self.callsign, self.target, text)
            pkt = Packet("out", self.callsign, text, status="sending")
            self.transcript.append(pkt)
            with self.net_lock:
                if self.wire:
                    send_command(self.wire, f"SEND {self.target} {b64}")
                    pkt.status = "sent"
                    self.log(f"Sent to {self.target}")
        except Exception as e:
            self.log(f"Send error: {e}")
            self.transcript.append(Packet("out", self.callsign, text, status="failed"))

    # ── Connect Screen ──────────────────────────────────────────

    def _hub_loop(self):
        if self.callsign:
            self.form_callsign = self.callsign
        if self.relay:
            self.form_relay = self.relay
        if self.chan:
            self.form_chan = str(self.chan)

        self.focus = 0
        self.stdscr.timeout(300)
        while self.screen == HUB and self.running:
            self.render()
            key = self.stdscr.getch()
            if key == -1:
                continue
            if key == curses.KEY_RESIZE:
                self._recalc()
                continue
            if key == 3:
                self.running = False
                break
            if key == 9 or key == curses.KEY_DOWN:
                self.focus = (self.focus + 1) % 5
                continue
            if key == curses.KEY_UP:
                self.focus = (self.focus - 1) % 5
                continue
            if key in (curses.KEY_ENTER, 10, 13):
                if self.focus == 3:
                    self._jack_action()
                elif self.focus == 4:
                    self.running = False
                    break
                elif self.focus < 3:
                    self.focus = (self.focus + 1) % 5
                continue
            if self.focus < 3:
                self._field_key(key)

    def _field_key(self, key):
        fields = [self.form_callsign, self.form_relay, self.form_chan]
        if key == 127 or key == curses.KEY_BACKSPACE or key == 8:
            fields[self.focus] = fields[self.focus][:-1]
        elif key == 21:
            fields[self.focus] = ""
        elif key == curses.KEY_DC:
            fields[self.focus] = ""
        elif 32 <= key <= 126:
            ch = chr(key)
            if self.focus == 2:
                if ch.isdigit():
                    fields[self.focus] += ch
            else:
                fields[self.focus] += ch
        self.form_callsign, self.form_relay, self.form_chan = fields[:3]

    def _jack_action(self):
        if self.linked:
            self._jack_out()
            return
        c = self.form_callsign.strip()
        r = self.form_relay.strip()
        ch = self.form_chan.strip()
        if not c or not r or not ch:
            self.log("Fill all fields")
            return
        if not validate_callsign(c):
            self.log(f"Invalid callsign: {c}")
            return
        if not ident_is_enlisted(c):
            self.log(f"Ident '{c}' not enlisted. Enlist first")
            return
        self.callsign = c
        self.log(f"Jacking into {r}:{ch} as {c}...")
        if self._jack_in(r, ch):
            self.screen = CHANNEL

    # ── Chat Screen ─────────────────────────────────────────────

    def _channel_loop(self):
        self.init_console()
        self.stdscr.timeout(200)
        self.log("Type /help for commands")
        while self.screen == CHANNEL and self.running:
            self._drain_events()
            if not self.linked:
                self.log("Link lost — returning to hub")
                self.screen = HUB
                break
            self.render()
            try:
                key = self.stdscr.getch()
            except KeyboardInterrupt:
                break
            if key == -1:
                continue
            if key == curses.KEY_RESIZE:
                self._recalc()
                continue
            if key in (curses.KEY_ENTER, 10, 13):
                self._execute()
            elif key == 127 or key == curses.KEY_BACKSPACE or key == 8:
                self.compose = self.compose[:-1]
            elif key == curses.KEY_DC:
                self.compose = ""
            elif key == 21:
                self.compose = ""
            elif key == 3:
                break
            elif 32 <= key <= 126:
                self.compose += chr(key)

        if self.linked:
            self._jack_out()

    def _execute(self):
        text = self.compose.strip()
        self.compose = ""
        if not text:
            return
        if text == "/quit":
            self.running = False
            return
        if text == "/disconnect":
            self._jack_out()
            self.screen = HUB
            return
        if text == "/connect":
            self.screen = HUB
            return
        if text.startswith("/connect "):
            parts = text.split()
            if len(parts) >= 3:
                r, ch = parts[1], parts[2]
                self.relay = r
                self.chan = ch
                self.form_relay = r
                self.form_chan = ch
                self.log(f"Jacking into {r}:{ch}...")
                self._jack_in(r, ch)
            else:
                self.log("Usage: /connect <host> <port>")
            return
        if text.startswith("/to "):
            parts = text.split(" ", 1)
            if len(parts) > 1 and validate_callsign(parts[1]):
                self.target = parts[1]
                status = "ONLINE" if parts[1] in self.online_users else "?"
                self.log(f"Target: {self.target} [{status}]")
            else:
                self.log("Usage: /to <callsign>")
            return
        if text == "/status":
            self.log(f"Status: {'Linked' if self.linked else 'Offline'} @ {self.relay}:{self.chan}")
            self.log(f"Ident: {self.callsign}  Target: {self.target or '(none)'}")
            if self.online_users:
                self.log(f"Online: {', '.join(sorted(self.online_users))}")
            return
        if text == "/scan":
            if not self.linked:
                self.log("Not linked")
                return
            with self.net_lock:
                if self.wire:
                    send_command(self.wire, "WHO")
                    self.log("Scanning network...")
                else:
                    self.log("No link")
            return
        if text.startswith("/whois "):
            parts = text.split(" ", 1)
            if len(parts) > 1:
                target = parts[1].strip()
                if target in self.online_users:
                    self.log(f"{target} is ONLINE")
                else:
                    self.log(f"{target} is OFFLINE (or not in scan cache)")
            else:
                self.log("Usage: /whois <callsign>")
            return
        if text == "/help":
            self.log("Commands:")
            self.log("  /connect <relay> <channel>  - jack into relay")
            self.log("  /disconnect                 - jack out")
            self.log("  /to <callsign>              - set target")
            self.log("  /scan                       - scan for online users")
            self.log("  /whois <callsign>           - check if user is online")
            self.log("  /status                     - show link status")
            self.log("  /clear                      - clear transcript")
            self.log("  /quit                       - exit")
            return
        if text == "/clear":
            self.transcript.clear()
            return
        self._transmit(text)

    # ── Main ─────────────────────────────────────────────────────

    def run(self):
        self.init_console()
        self._hub_loop()
        if self.running and self.linked:
            self._channel_loop()
        self._jack_out()


def main():
    callsign = sys.argv[1] if len(sys.argv) > 1 else None
    relay = sys.argv[2] if len(sys.argv) > 2 else None
    channel = sys.argv[3] if len(sys.argv) > 3 else None

    load_config()
    if not relay:
        relay = os.environ.get("SERVER_HOST", "")
    if not channel:
        channel = os.environ.get("SERVER_PORT", "500")

    d = CyberDeck.__new__(CyberDeck)
    d.callsign = callsign or ""
    d.relay = relay or ""
    d.chan = str(channel) if channel else ""
    d.target = ""
    d.screen = HUB
    d.running = True
    d.linked = False
    d.online_users = set()
    d.transcript = []
    d.compose = ""
    d.syslog = ["Welcome to NeonNet"]
    d.wire = None
    d.link_thread = None
    d.event_q = queue.Queue()
    d.net_lock = threading.Lock()
    d._kill_link = False
    d.form_callsign = d.callsign
    d.form_relay = d.relay
    d.form_chan = d.chan
    d.focus = 3 if (d.callsign and d.relay and d.chan) else 0
    d._link_ready = threading.Event()
    d.msg_win = None
    d.input_win = None
    d.status_win = None

    curses.wrapper(lambda stdscr: CyberDeck._boot(stdscr, d))


def _boot(stdscr, d):
    stdscr.keypad(True)
    d.stdscr = stdscr
    d.init_console()
    d._hub_loop()
    if d.running and d.linked:
        d._channel_loop()
    d._jack_out()

CyberDeck._boot = staticmethod(_boot)

if __name__ == "__main__":
    main()
