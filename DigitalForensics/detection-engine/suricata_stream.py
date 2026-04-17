"""
Tail Suricata eve.json; reopen on log rotation (inode / path change).
"""

from __future__ import annotations

import json
import os
import time
from datetime import datetime, timezone
from typing import Any, Dict, Generator

DEFAULT_EVE = "/var/log/suricata/eve.json"


def _eve_path() -> str:
    return os.environ.get("SURICATA_EVE", DEFAULT_EVE)


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


def _iso_from_eve(ts: Any) -> str:
    if isinstance(ts, str):
        try:
            if ts.endswith("Z"):
                return datetime.fromisoformat(ts.replace("Z", "+00:00")).astimezone(timezone.utc).isoformat()
            return datetime.fromisoformat(ts).astimezone(timezone.utc).isoformat()
        except ValueError:
            pass
    return datetime.now(timezone.utc).isoformat()


def stream_suricata() -> Generator[Dict[str, Any], None, None]:
    path = _eve_path()
    poll = float(os.environ.get("DLDS_SURICATA_POLL", "0.2"))

    while True:
        try:
            f, open_real, open_ino = _open_follow(path)
        except OSError:
            time.sleep(1.0)
            continue

        f.seek(0, os.SEEK_END)

        try:
            while True:
                if _rotation_detected(path, open_real, open_ino):
                    f.close()
                    break

                line = f.readline()
                if not line:
                    time.sleep(poll)
                    continue

                line = line.strip()
                if not line:
                    continue

                try:
                    data = json.loads(line)
                except json.JSONDecodeError:
                    continue

                if data.get("event_type") != "alert":
                    continue

                alert = data.get("alert") or {}
                src_ip = data.get("src_ip") or data.get("src", {}).get("ip")
                dst_ip = data.get("dest_ip") or data.get("dst_ip") or data.get("dest", {}).get("ip")
                src_port = data.get("src_port") or 0
                dst_port = data.get("dest_port") or data.get("dst_port") or 0

                ev: Dict[str, Any] = {
                    "type": "alert",
                    "source": "suricata.eve",
                    "timestamp_raw": data.get("timestamp"),
                    "timestamp": _iso_from_eve(data.get("timestamp")),
                    "src_ip": src_ip or "",
                    "dst_ip": dst_ip or "",
                    "src_port": int(src_port) if src_port else 0,
                    "dst_port": int(dst_port) if dst_port else 0,
                    "alert_signature": alert.get("signature") or "",
                    "severity_num": alert.get("severity"),
                    "category": alert.get("category") or "",
                    "gid": alert.get("gid"),
                    "sid": alert.get("signature_id"),
                    "flow_id": data.get("flow_id"),
                    "proto": data.get("proto") or "",
                    "payload": data.get("payload_printable"),
                }
                yield ev
        except OSError:
            time.sleep(0.5)
        finally:
            try:
                f.close()
            except OSError:
                pass
