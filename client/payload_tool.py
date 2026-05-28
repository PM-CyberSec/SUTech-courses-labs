#!/usr/bin/env python3
"""Build and render decrypted CipherShell payloads."""

from __future__ import annotations

import argparse
import base64
import json
import re
import sys
from pathlib import Path


SAFE_NAME_RE = re.compile(r"[^A-Za-z0-9._-]+")


def safe_filename(name: str) -> str:
    cleaned = SAFE_NAME_RE.sub("_", Path(name).name).strip("._")
    return cleaned or "received_file"


def unique_path(directory: Path, filename: str) -> tuple[Path, bool]:
    directory.mkdir(parents=True, exist_ok=True)
    candidate = directory / filename
    if not candidate.exists():
        return candidate, False

    stem = candidate.stem or "received_file"
    suffix = candidate.suffix
    for index in range(1, 10000):
        next_candidate = directory / f"{stem}_{index}{suffix}"
        if not next_candidate.exists():
            return next_candidate, True
    raise RuntimeError("Could not find a safe non-overwriting filename.")


def build_message(args: argparse.Namespace) -> int:
    message = args.message
    if not message.strip():
        print("Message must not be empty.", file=sys.stderr)
        return 1
    payload = {"kind": "message", "message": message}
    Path(args.output).write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    return 0


def build_file(args: argparse.Namespace) -> int:
    path = Path(args.file)
    if not path.is_file():
        print(f"File does not exist: {path}", file=sys.stderr)
        return 1
    data = path.read_bytes()
    payload = {
        "kind": "file",
        "filename": safe_filename(path.name),
        "size": len(data),
        "content_b64": base64.b64encode(data).decode("ascii"),
    }
    Path(args.output).write_text(json.dumps(payload, ensure_ascii=False), encoding="utf-8")
    return 0


def render(args: argparse.Namespace) -> int:
    payload = json.loads(Path(args.input).read_text(encoding="utf-8"))
    kind = payload.get("kind")
    if kind == "message":
        print()
        print("==============================")
        print("New Message")
        print(f"From: {args.sender}")
        print(f"Message: {payload.get('message', '')}")
        print("==============================")
        return 0

    if kind == "file":
        filename = safe_filename(str(payload.get("filename", "received_file")))
        content = base64.b64decode(str(payload.get("content_b64", "")), validate=True)
        target_dir = Path(args.downloads_root) / args.username
        target, renamed = unique_path(target_dir, filename)
        target.write_bytes(content)
        print()
        print("==============================")
        print("New File")
        print(f"From: {args.sender}")
        print(f"Saved: {target}")
        print(f"Size: {len(content)} bytes")
        if renamed:
            print("Note: Existing file was kept; saved with a new name.")
        print("==============================")
        return 0

    print("Received an unknown payload type.", file=sys.stderr)
    return 1


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="CipherShell payload helper")
    subparsers = parser.add_subparsers(dest="command", required=True)

    msg = subparsers.add_parser("build-message")
    msg.add_argument("--message", required=True)
    msg.add_argument("--output", required=True)
    msg.set_defaults(func=build_message)

    file_parser = subparsers.add_parser("build-file")
    file_parser.add_argument("--file", required=True)
    file_parser.add_argument("--output", required=True)
    file_parser.set_defaults(func=build_file)

    render_parser = subparsers.add_parser("render")
    render_parser.add_argument("--username", required=True)
    render_parser.add_argument("--sender", required=True)
    render_parser.add_argument("--input", required=True)
    render_parser.add_argument("--downloads-root", required=True)
    render_parser.set_defaults(func=render)
    return parser


def main() -> int:
    args = build_parser().parse_args()
    try:
        return args.func(args)
    except Exception as exc:
        print(f"Payload error: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
