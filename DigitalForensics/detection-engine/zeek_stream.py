"""
Tail Zeek conn.log in real time; reopen when ZeekControl rotates (current/ symlink target / inode changes).
"""

from __future__ import annotations

import os
import time
from typing import Any, Dict, Generator, List, Optional, TextIO

from parser_zeek import parse_conn_data_line, parse_fields_header

DEFAULT_CONN = "/opt/zeek/logs/current/conn.log"


def _conn_path() -> str:
    return os.environ.get("ZEEK_CONN_LOG", DEFAULT_CONN)


def _open_follow(path: str) -> tuple[TextIO, str, int]:
    real = os.path.realpath(path)
    f = open(real, "r", encoding="utf-8", errors="replace")
    st = os.fstat(f.fileno())
    return f, real, st.st_ino


def _rotation_detected(path: str, open_real: str, open_ino: int) -> bool:
    try:
        real_now = os.path.realpath(path)
        st = os.stat(real_now)
    except OSError:
        return False
    return real_now != open_real or st.st_ino != open_ino


def _read_fields_from_start(f: TextIO) -> Optional[List[str]]:
    """Load #fields from beginning of log; leave file position at EOF for tailing."""
    f.seek(0)
    field_names: Optional[List[str]] = None
    for line in f:
        if line.startswith("#fields"):
            hdr = parse_fields_header(line)
            if hdr:
                field_names = hdr
        elif not line.startswith("#") and line.strip():
            break
    f.seek(0, os.SEEK_END)
    return field_names


def stream_zeek() -> Generator[Dict[str, Any], None, None]:
    path = _conn_path()
    poll = float(os.environ.get("DLDS_ZEEK_POLL", "0.2"))

    while True:
        try:
            f, open_real, open_ino = _open_follow(path)
        except OSError:
            time.sleep(1.0)
            continue

        field_names = _read_fields_from_start(f)
        if field_names is None:
            f.close()
            time.sleep(1.0)
            continue

        try:
            while True:
                if _rotation_detected(path, open_real, open_ino):
                    f.close()
                    break

                line = f.readline()
                if not line:
                    time.sleep(poll)
                    continue

                if line.startswith("#"):
                    hdr = parse_fields_header(line)
                    if hdr:
                        field_names = hdr
                    continue

                ev = parse_conn_data_line(field_names, line)
                if ev:
                    ev["type"] = "network"
                    ev["source"] = "zeek.conn"
                    yield ev
        except OSError:
            time.sleep(0.5)
        finally:
            try:
                f.close()
            except OSError:
                pass
