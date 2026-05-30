"""
Tail Zeek conn.log in real time; reopen when ZeekControl rotates (current/ symlink target / inode changes).
"""

from __future__ import annotations

import logging
import os
import time
from typing import Any, Dict, Generator, List, Optional, TextIO

from config import getenv_bool, getenv_float, zeek_conn_log_path
from parser_zeek import parse_conn_data_line, parse_fields_header

LOGGER = logging.getLogger(__name__)

def _conn_path() -> str:
    return zeek_conn_log_path()


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


def _read_fields(f: TextIO, *, start_from_end: bool) -> Optional[List[str]]:
    """
    Read #fields header then position stream either at EOF (tail mode) or BOF (replay mode).
    """
    f.seek(0, os.SEEK_SET)
    field_names: Optional[List[str]] = None
    for line in f:
        if line.startswith("#fields"):
            hdr = parse_fields_header(line)
            if hdr:
                field_names = hdr
        elif not line.startswith("#") and line.strip():
            break

    f.seek(0, os.SEEK_END if start_from_end else os.SEEK_SET)
    return field_names


def stream_zeek() -> Generator[Dict[str, Any], None, None]:
    path = _conn_path()
    poll = max(0.1, getenv_float("DLDS_ZEEK_POLL", 0.2))
    start_from_end = getenv_bool("DLDS_ZEEK_FROM_END", default=True)
    LOGGER.info("Zeek stream initialized path=%s poll=%.2fs", path, poll)
    LOGGER.info("Zeek stream mode=%s", "tail-from-end" if start_from_end else "read-from-start")
    if not os.path.exists(path):
        LOGGER.info(
            "Waiting for Zeek to capture traffic to create %s...",
            path,
        )
    lines_read = 0

    while True:
        try:
            f, open_real, open_ino = _open_follow(path)
            LOGGER.info(
                "Zeek conn.log opened path=%s real_path=%s inode=%s",
                path,
                open_real,
                open_ino,
            )
        except OSError:
            LOGGER.debug("Zeek conn log unavailable at %s; retrying", path)
            time.sleep(1.0)
            continue

        field_names = _read_fields(f, start_from_end=start_from_end)
        if field_names is None:
            LOGGER.debug("Zeek conn.log missing #fields header; waiting for next rotation")
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
                lines_read += 1
                LOGGER.debug("Read Zeek line successfully line_no=%s", lines_read)

                if line.startswith("#"):
                    hdr = parse_fields_header(line)
                    if hdr:
                        field_names = hdr
                    continue

                ev = parse_conn_data_line(field_names, line)
                if ev:
                    ev["type"] = "network"
                    ev["source"] = "zeek.conn"
                    LOGGER.debug(
                        "Parsed Zeek event src=%s:%s dst=%s:%s bytes_sent=%s",
                        ev.get("src_ip"),
                        ev.get("src_port"),
                        ev.get("dst_ip"),
                        ev.get("dst_port"),
                        ev.get("bytes_sent"),
                    )
                    yield ev
        except OSError:
            time.sleep(0.5)
        finally:
            try:
                f.close()
            except OSError:
                pass
