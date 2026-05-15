"""
Tail Suricata eve.json; reopen on log rotation (inode / path change).
"""

from __future__ import annotations

import json
import logging
import os
import time
from typing import Any, Dict, Generator, TextIO

from config import getenv_bool, getenv_float, parse_timestamp_to_utc_iso, suricata_eve_path
from rules import is_private_ip

LOGGER = logging.getLogger(__name__)

_NETWORK_EVENT_TYPES = {"flow", "dns", "tls", "http", "quic"}


def _eve_path() -> str:
    return suricata_eve_path()


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


def _as_int(value: Any, default: int = 0) -> int:
    try:
        if value is None:
            return default
        return int(value)
    except (TypeError, ValueError):
        return default


def _as_dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _derive_local_remote(
    src_ip: str,
    src_port: int,
    dst_ip: str,
    dst_port: int,
) -> tuple[str, int, str, int]:
    src_private = is_private_ip(src_ip)
    dst_private = is_private_ip(dst_ip)

    if src_private and not dst_private:
        return src_ip, src_port, dst_ip, dst_port

    if dst_private and not src_private:
        return dst_ip, dst_port, src_ip, src_port

    # Ambiguous flows (both private/public): keep initiator as local by default.
    return src_ip, src_port, dst_ip, dst_port


def _event_time(ts: Any) -> str:
    return parse_timestamp_to_utc_iso(ts)


def stream_suricata() -> Generator[Dict[str, Any], None, None]:
    path = _eve_path()
    poll = max(0.1, getenv_float("DLDS_SURICATA_POLL", 0.2))
    start_from_end = getenv_bool("DLDS_SURICATA_FROM_END", default=True)

    LOGGER.info("Suricata stream initialized path=%s poll=%.2fs", path, poll)
    if not os.path.exists(path):
        LOGGER.warning(
            "Suricata eve.json does not exist at %s. Set SURICATA_EVE_PATH to the active path.",
            path,
        )

    lines_read = 0

    while True:
        try:
            f, open_real, open_ino = _open_follow(path)
            LOGGER.info(
                "Suricata log opened path=%s real_path=%s inode=%s",
                path,
                open_real,
                open_ino,
            )
        except OSError:
            LOGGER.debug("Suricata log unavailable at %s; retrying", path)
            time.sleep(1.0)
            continue

        if start_from_end:
            LOGGER.info("Suricata stream mode=tail-from-end")
            f.seek(0, os.SEEK_END)
        else:
            LOGGER.info("Suricata stream mode=read-from-start")
            f.seek(0, os.SEEK_SET)

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
                raw = line.strip()
                if not raw:
                    continue

                try:
                    data = json.loads(raw)
                except json.JSONDecodeError:
                    LOGGER.debug("Skipped Suricata JSON decode error line_no=%s", lines_read)
                    continue

                event_type = str(data.get("event_type") or "").lower()
                if not event_type:
                    continue

                src = _as_dict(data.get("src"))
                dst = _as_dict(data.get("dest"))
                src_ip = str(data.get("src_ip") or src.get("ip") or "")
                dst_ip = str(data.get("dest_ip") or data.get("dst_ip") or dst.get("ip") or "")
                src_port = _as_int(data.get("src_port"), 0)
                dst_port = _as_int(data.get("dest_port") or data.get("dst_port"), 0)

                if event_type == "alert":
                    alert = _as_dict(data.get("alert"))
                    ev = {
                        "type": "alert",
                        "source": "suricata.eve.alert",
                        "timestamp_raw": data.get("timestamp"),
                        "timestamp": _event_time(data.get("timestamp")),
                        "src_ip": src_ip,
                        "dst_ip": dst_ip,
                        "src_port": src_port,
                        "dst_port": dst_port,
                        "alert_signature": str(alert.get("signature") or ""),
                        "severity_num": _as_int(alert.get("severity"), 0),
                        "category": str(alert.get("category") or ""),
                        "gid": alert.get("gid"),
                        "sid": alert.get("signature_id"),
                        "flow_id": data.get("flow_id"),
                        "proto": str(data.get("proto") or ""),
                        "payload": data.get("payload_printable"),
                    }
                    yield ev
                    continue

                if event_type not in _NETWORK_EVENT_TYPES:
                    continue

                if not src_ip or not dst_ip:
                    continue

                flow = _as_dict(data.get("flow"))
                bytes_to_server = max(0, _as_int(flow.get("bytes_toserver") or data.get("bytes_toserver"), 0))
                bytes_to_client = max(0, _as_int(flow.get("bytes_toclient") or data.get("bytes_toclient"), 0))

                local_ip, local_port, remote_ip, remote_port = _derive_local_remote(
                    src_ip,
                    src_port,
                    dst_ip,
                    dst_port,
                )

                if local_ip == src_ip and local_port == src_port:
                    bytes_sent = bytes_to_server
                elif local_ip == dst_ip and local_port == dst_port:
                    bytes_sent = bytes_to_client
                else:
                    bytes_sent = max(bytes_to_server, bytes_to_client)

                ev = {
                    "type": "network",
                    "source": f"suricata.eve.{event_type}",
                    "timestamp_raw": data.get("timestamp"),
                    "timestamp": _event_time(data.get("timestamp")),
                    "src_ip": src_ip,
                    "dst_ip": dst_ip,
                    "src_port": src_port,
                    "dst_port": dst_port,
                    "local_ip": local_ip,
                    "local_port": local_port,
                    "remote_ip": remote_ip,
                    "remote_port": remote_port,
                    "bytes_sent": bytes_sent,
                    "description": f"Suricata {event_type} event",
                    "severity": "LOW",
                    "event_type_raw": event_type,
                }

                LOGGER.debug(
                    "Classified Suricata event type=%s src=%s dst=%s",
                    ev.get("type"),
                    ev.get("src_ip"),
                    ev.get("dst_ip"),
                )
                yield ev
        except OSError:
            time.sleep(0.5)
        finally:
            try:
                f.close()
            except OSError:
                pass
