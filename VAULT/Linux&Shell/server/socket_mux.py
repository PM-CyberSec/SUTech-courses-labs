#!/usr/bin/env python3
"""Async relay server for CipherShell Chat."""

from __future__ import annotations

import argparse
import asyncio
import logging
import re
import signal
from pathlib import Path


USERNAME_RE = re.compile(r"^[a-z0-9_-]+$")
MAX_LINE_BYTES = 60 * 1024 * 1024


clients: dict[str, asyncio.StreamWriter] = {}
users_db = Path("users.db")


def peer(writer: asyncio.StreamWriter) -> str:
    return str(writer.get_extra_info("peername") or "unknown")


def valid_username(username: str) -> bool:
    return bool(USERNAME_RE.fullmatch(username))


async def write_line(writer: asyncio.StreamWriter, line: str) -> None:
    writer.write((line + "\n").encode("utf-8"))
    await writer.drain()


async def close_writer(writer: asyncio.StreamWriter) -> None:
    writer.close()
    try:
        await writer.wait_closed()
    except Exception:
        pass


async def route_message(sender: str, recipient: str, payload: str, reply: asyncio.StreamWriter) -> None:
    logging.info("message received sender=%s recipient=%s bytes=%s", sender, recipient, len(payload))

    if not valid_username(sender) or not valid_username(recipient):
        logging.warning("invalid send envelope sender=%s recipient=%s", sender, recipient)
        await write_line(reply, "ERROR invalid sender or recipient username")
        return

    if not (users_db / f"{recipient}.pub").is_file():
        logging.warning("recipient not registered recipient=%s sender=%s", recipient, sender)
        await write_line(reply, f"ERROR recipient '{recipient}' is not registered")
        return

    recipient_writer = clients.get(recipient)
    if recipient_writer is None:
        logging.info("recipient offline sender=%s recipient=%s", sender, recipient)
        await write_line(reply, f"OFFLINE {recipient}")
        return

    try:
        await write_line(recipient_writer, f"FROM {sender} {payload}")
        logging.info("message forwarded sender=%s recipient=%s", sender, recipient)
        await write_line(reply, f"OK delivered to {recipient}")
    except Exception as exc:
        logging.exception("error occurred while forwarding sender=%s recipient=%s: %s", sender, recipient, exc)
        if clients.get(recipient) is recipient_writer:
            clients.pop(recipient, None)
        await close_writer(recipient_writer)
        await write_line(reply, f"OFFLINE {recipient}")


async def handle_login(username: str, reader: asyncio.StreamReader, writer: asyncio.StreamWriter) -> None:
    if not valid_username(username):
        await write_line(writer, "ERROR invalid username")
        return

    if not (users_db / f"{username}.pub").is_file():
        logging.warning("login rejected unregistered user=%s peer=%s", username, peer(writer))
        await write_line(writer, f"ERROR user '{username}' is not registered. Register first.")
        return

    old_writer = clients.get(username)
    if old_writer is not None and old_writer is not writer:
        logging.info("duplicate login user=%s; closing previous listener", username)
        try:
            await write_line(old_writer, "ERROR another listener logged in with this username")
        except Exception:
            pass
        await close_writer(old_writer)

    clients[username] = writer
    logging.info("user connected user=%s peer=%s", username, peer(writer))
    await write_line(writer, f"OK logged in as {username}")

    try:
        while True:
            line = await reader.readline()
            if not line:
                break
            msg = line.decode("utf-8", errors="replace").strip()
            if not msg:
                continue
            parts = msg.split(" ", 2)
            if len(parts) == 3 and parts[0] == "SEND":
                await route_message(username, parts[1], parts[2], writer)
            elif len(parts) == 2 and parts[0] == "GETKEY":
                key_path = users_db / f"{parts[1]}.pub"
                if key_path.is_file():
                    import base64
                    key_data = base64.b64encode(key_path.read_bytes()).decode("ascii")
                    await write_line(writer, f"KEY {key_data}")
                else:
                    await write_line(writer, f"ERROR public key for '{parts[1]}' not found")
            elif parts[0] == "WHO":
                online = " ".join(sorted(clients.keys()))
                await write_line(writer, f"OK ONLINE {online}")
            else:
                logging.warning("unknown command from logged-in user=%s command=%s", username, msg[:80])
                await write_line(writer, "ERROR unknown command")
    finally:
        if clients.get(username) is writer:
            clients.pop(username, None)
            logging.info("user disconnected user=%s", username)


async def handle_client(reader: asyncio.StreamReader, writer: asyncio.StreamWriter) -> None:
    try:
        first = await reader.readline()
        if not first:
            return
        line = first.decode("utf-8", errors="replace").strip()
        parts = line.split(" ", 3)

        if len(parts) == 2 and parts[0] == "LOGIN":
            await handle_login(parts[1], reader, writer)
            return

        if len(parts) == 4 and parts[0] == "SEND":
            await route_message(parts[1], parts[2], parts[3], writer)
            return

        if len(parts) == 2 and parts[0] == "GETKEY":
            key_path = users_db / f"{parts[1]}.pub"
            if key_path.is_file():
                import base64
                key_data = base64.b64encode(key_path.read_bytes()).decode("ascii")
                await write_line(writer, f"KEY {key_data}")
            else:
                await write_line(writer, f"ERROR public key for '{parts[1]}' not found")
            return

        if len(parts) == 3 and parts[0] == "PUTKEY":
            import base64
            key_path = users_db / f"{parts[1]}.pub"
            key_path.parent.mkdir(parents=True, exist_ok=True)
            try:
                key_bytes = base64.b64decode(parts[2])
                key_path.write_bytes(key_bytes)
                logging.info("public key stored user=%s peer=%s", parts[1], peer(writer))
                await write_line(writer, "OK key stored")
            except Exception as exc:
                logging.warning("invalid pubkey from user=%s: %s", parts[1], exc)
                await write_line(writer, "ERROR invalid public key data")
            return

        if parts[0] == "WHO":
            online = " ".join(sorted(clients.keys()))
            await write_line(writer, f"OK ONLINE {online}")
            return

        logging.warning("unknown initial command peer=%s command=%s", peer(writer), line[:80])
        await write_line(writer, "ERROR expected LOGIN <username> or SEND <sender> <recipient> <payload>, or GETKEY <user>, or PUTKEY <user> <base64>")
    except asyncio.LimitOverrunError:
        logging.error("error occurred: client payload exceeded %s bytes peer=%s", MAX_LINE_BYTES, peer(writer))
        try:
            await write_line(writer, "ERROR payload too large")
        except Exception:
            pass
    except Exception as exc:
        logging.exception("error occurred while handling peer=%s: %s", peer(writer), exc)
        try:
            await write_line(writer, "ERROR internal server error")
        except Exception:
            pass
    finally:
        await close_writer(writer)


async def main(args: argparse.Namespace) -> None:
    global users_db
    users_db = Path(args.users_db)
    users_db.mkdir(parents=True, exist_ok=True)
    stop_event = asyncio.Event()
    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        try:
            loop.add_signal_handler(sig, stop_event.set)
        except NotImplementedError:
            pass

    server = await asyncio.start_server(
        handle_client,
        args.host,
        args.port,
        limit=MAX_LINE_BYTES,
    )
    sockets = ", ".join(str(sock.getsockname()) for sock in server.sockets or [])
    logging.info("server started host=%s port=%s sockets=%s", args.host, args.port, sockets)
    async with server:
        await stop_event.wait()
    logging.info("server shutting down")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="CipherShell relay server")
    parser.add_argument("legacy_port", nargs="?", type=int, help="compatibility positional port")
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--port", type=int, default=None)
    parser.add_argument("--users-db", default="users.db")
    return parser


def run() -> int:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )
    args = build_parser().parse_args()
    args.port = args.port or args.legacy_port or 500

    try:
        asyncio.run(main(args))
    except OSError as exc:
        logging.error("error occurred: could not bind %s:%s (%s)", args.host, args.port, exc)
        return 98
    except KeyboardInterrupt:
        logging.info("server shutting down")
        return 0
    finally:
        for writer in list(clients.values()):
            writer.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(run())
