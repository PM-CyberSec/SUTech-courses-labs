"""
Correlation engine: process file access + outbound bytes per PID, Suricata enrichment via ss mapping.
Emits unified JSON records (stdout, optional HTTP, optional SQLite).
"""

from __future__ import annotations

import json
import os
import sqlite3
import threading
import time
from collections import defaultdict, deque
from datetime import datetime, timezone
from typing import Any, Deque, Dict, List, Optional, Set, Tuple

from net_mapper import NetMapper, read_proc_comm
from rules import (
    exfil_byte_threshold,
    is_outbound_exfil_candidate,
    is_private_ip,
    is_sensitive,
    suricata_severity_label,
)


def _iso_from_zeek_ts(ts_raw: str) -> str:
    try:
        t = float(ts_raw)
        return datetime.fromtimestamp(t, tz=timezone.utc).isoformat()
    except (ValueError, TypeError):
        return datetime.now(timezone.utc).isoformat()


def _empty_record() -> Dict[str, Any]:
    return {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "type": "alert",
        "pid": 0,
        "process_name": "",
        "file": "",
        "src_ip": "",
        "src_port": 0,
        "dst_ip": "",
        "dst_port": 0,
        "bytes_sent": 0,
        "alert_type": "",
        "severity": "LOW",
        "description": "",
    }


class Correlator:
    def __init__(self) -> None:
        self._lock = threading.Lock()
        self.mapper = NetMapper()
        self.process_files: Dict[int, Deque[Dict[str, Any]]] = defaultdict(
            lambda: deque(maxlen=512)
        )
        self.network_bytes: Dict[int, int] = defaultdict(int)
        self._last_remote: Dict[int, str] = {}
        self._exfil_announced: Set[Tuple[int, str]] = set()
        self._emit_mode = os.environ.get("DLDS_EMIT_MODE", "alerts")
        self._api_url = os.environ.get("DLDS_API_URL", "").strip()
        self._sqlite_path = os.environ.get("DLDS_SQLITE_PATH", "").strip()
        self._db_initialized = False

    # --- ingestion ---

    def handle_event(self, event: Dict[str, Any]) -> None:
        et = event.get("type")
        if et == "process":
            self._handle_process(event)
        elif et == "network":
            self._handle_network(event)
        elif et == "alert":
            self._handle_suricata(event)

        if self._emit_mode == "all":
            rec = self._normalize_observation(event)
            if rec:
                self._emit(rec)

    def _handle_process(self, event: Dict[str, Any]) -> None:
        pid = int(event.get("pid") or 0)
        if pid <= 0:
            return
        path = event.get("file") or ""
        with self._lock:
            self.process_files[pid].append(
                {
                    "file": path,
                    "executable": event.get("executable") or "",
                    "timestamp": event.get("timestamp"),
                }
            )

    def _handle_network(self, event: Dict[str, Any]) -> None:
        li = event.get("local_ip") or ""
        lp = int(event.get("local_port") or 0)
        ri = event.get("remote_ip") or ""
        rp = int(event.get("remote_port") or 0)
        sent = int(event.get("bytes_sent") or event.get("bytes") or 0)

        info = self.mapper.lookup(li, lp, ri, rp, force_refresh=False)
        if info is None:
            info = self.mapper.lookup(li, lp, ri, rp, force_refresh=True)
        if info is None:
            return

        pid, pname = info
        if pid <= 0:
            return

        with self._lock:
            self.network_bytes[pid] += max(0, sent)
            self._last_remote[pid] = ri

    def _handle_suricata(self, event: Dict[str, Any]) -> None:
        src_ip = event.get("src_ip") or ""
        dst_ip = event.get("dst_ip") or ""
        sp = int(event.get("src_port") or 0)
        dp = int(event.get("dst_port") or 0)

        if is_private_ip(src_ip):
            li, lp, ri, rp = src_ip, sp, dst_ip, dp
        elif is_private_ip(dst_ip):
            li, lp, ri, rp = dst_ip, dp, src_ip, sp
        else:
            li, lp, ri, rp = src_ip, sp, dst_ip, dp

        info = self.mapper.lookup(li, lp, ri, rp, force_refresh=True)
        pid: Optional[int] = None
        pname = ""
        if info:
            pid, pname = info

        if pid and pid > 0:
            pname = pname or read_proc_comm(pid)

        sev = suricata_severity_label(event.get("severity_num"))
        sig = event.get("alert_signature") or "Suricata alert"
        desc = f"IDS: {sig}"
        if pid:
            desc += f"; pid={pid} ({pname or 'unknown'})"

        rec = _empty_record()
        rec["timestamp"] = event.get("timestamp") or datetime.now(timezone.utc).isoformat()
        rec["type"] = "alert"
        rec["pid"] = int(pid or 0)
        rec["process_name"] = pname or ""
        rec["src_ip"] = src_ip
        rec["src_port"] = sp
        rec["dst_ip"] = dst_ip
        rec["dst_port"] = dp
        rec["bytes_sent"] = 0
        rec["alert_type"] = "Suricata Enrichment"
        rec["severity"] = sev
        rec["description"] = desc
        self._emit(rec)

    def _normalize_observation(self, raw: Dict[str, Any]) -> Optional[Dict[str, Any]]:
        r = _empty_record()
        t = raw.get("type")
        if t == "network":
            r["timestamp"] = _iso_from_zeek_ts(str(raw.get("ts_raw", "")))
            r["type"] = "network"
            r["src_ip"] = raw.get("src_ip") or ""
            r["src_port"] = int(raw.get("src_port") or 0)
            r["dst_ip"] = raw.get("dst_ip") or ""
            r["dst_port"] = int(raw.get("dst_port") or 0)
            r["bytes_sent"] = int(raw.get("bytes_sent") or raw.get("bytes") or 0)
            r["description"] = "Zeek conn log"
            return r
        if t == "process":
            r["timestamp"] = raw.get("timestamp") or datetime.now(timezone.utc).isoformat()
            r["type"] = "process"
            r["pid"] = int(raw.get("pid") or 0)
            r["process_name"] = os.path.basename(raw.get("executable") or "")
            r["file"] = raw.get("file") or ""
            r["description"] = "auditd file access"
            return r
        return None

    # --- detection ---

    def detect(self) -> None:
        thr = exfil_byte_threshold()
        pending: List[Dict[str, Any]] = []
        with self._lock:
            candidates = set(self.process_files.keys()) | set(self.network_bytes.keys())
            for pid in candidates:
                files = list(self.process_files.get(pid, ()))
                if not any(is_sensitive(f.get("file", "")) for f in files):
                    continue
                total = int(self.network_bytes.get(pid, 0))
                if total < thr:
                    continue
                remote = self._last_remote.get(pid, "")
                if not is_outbound_exfil_candidate(remote):
                    continue

                sens_path = next(
                    (f.get("file") for f in files if is_sensitive(f.get("file", ""))),
                    "",
                )
                exe = next(
                    (f.get("executable") for f in reversed(files) if f.get("executable")),
                    "",
                )
                key = (pid, sens_path)
                if key in self._exfil_announced:
                    continue
                self._exfil_announced.add(key)

                rec = _empty_record()
                rec["timestamp"] = datetime.now(timezone.utc).isoformat()
                rec["type"] = "alert"
                rec["pid"] = pid
                rec["process_name"] = os.path.basename(exe) if exe else read_proc_comm(pid)
                rec["file"] = sens_path
                rec["src_ip"] = ""
                rec["src_port"] = 0
                rec["dst_ip"] = remote
                rec["dst_port"] = 0
                rec["bytes_sent"] = total
                rec["alert_type"] = "Data Exfiltration"
                rec["severity"] = "HIGH"
                rec["description"] = (
                    f"Sensitive file access correlated with >= {thr} bytes outbound "
                    f"(Zeek-observed) for pid {pid}"
                )
                pending.append(rec)
        for rec in pending:
            self._emit(rec)

    # --- output ---

    def _emit(self, record: Dict[str, Any]) -> None:
        line = json.dumps(record, ensure_ascii=False)
        print(line, flush=True)
        if self._api_url:
            self._post_http(record)
        if self._sqlite_path:
            self._persist_sqlite(record)

    def _post_http(self, record: Dict[str, Any]) -> None:
        try:
            import requests

            requests.post(self._api_url, json=record, timeout=5)
        except Exception:
            pass

    def _persist_sqlite(self, record: Dict[str, Any]) -> None:
        try:
            conn = sqlite3.connect(self._sqlite_path)
            cur = conn.cursor()
            if not self._db_initialized:
                cur.execute(
                    """
                    CREATE TABLE IF NOT EXISTS dlds_events (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        timestamp TEXT,
                        type TEXT,
                        pid INTEGER,
                        process_name TEXT,
                        file TEXT,
                        src_ip TEXT,
                        src_port INTEGER,
                        dst_ip TEXT,
                        dst_port INTEGER,
                        bytes_sent INTEGER,
                        alert_type TEXT,
                        severity TEXT,
                        description TEXT
                    )
                    """
                )
                conn.commit()
                self._db_initialized = True
            cur.execute(
                """
                INSERT INTO dlds_events (
                    timestamp, type, pid, process_name, file,
                    src_ip, src_port, dst_ip, dst_port, bytes_sent,
                    alert_type, severity, description
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                """,
                (
                    record.get("timestamp"),
                    record.get("type"),
                    record.get("pid"),
                    record.get("process_name"),
                    record.get("file"),
                    record.get("src_ip"),
                    record.get("src_port"),
                    record.get("dst_ip"),
                    record.get("dst_port"),
                    record.get("bytes_sent"),
                    record.get("alert_type"),
                    record.get("severity"),
                    record.get("description"),
                ),
            )
            conn.commit()
            conn.close()
        except Exception:
            pass
