"""
Map (local_ip, local_port, remote_ip, remote_port) -> (pid, process name) using ss -tnp.
Refreshes on a short TTL to stay current without blocking ingestion on every event.
"""

from __future__ import annotations

import os
import re
import subprocess
import threading
import time
from typing import Dict, Iterator, Optional, Tuple

Quad = Tuple[str, int, str, int]
PidInfo = Tuple[int, str]

_SS_LINE = re.compile(
    r"^\s*\S+\s+\d+\s+\d+\s+"
    r"(?P<loc>\S+):(?P<lp>\d+)\s+"
    r"(?P<rem>\S+):(?P<rp>\d+)\s*"
    r"(?P<rest>.*)$"
)
_USERS = re.compile(r'users:\(\("([^"]+)",pid=(\d+)', re.DOTALL)


def _strip_brackets(addr: str) -> str:
    if addr.startswith("[") and addr.endswith("]"):
        return addr[1:-1]
    return addr


class NetMapper:
    def __init__(self, refresh_sec: float | None = None) -> None:
        self._lock = threading.Lock()
        self._by_quad: Dict[Quad, PidInfo] = {}
        self._last_mono = 0.0
        self._refresh_sec = float(
            refresh_sec if refresh_sec is not None else os.environ.get("DLDS_SS_REFRESH_SEC", "1.0")
        )

    def _should_refresh(self) -> bool:
        return (time.monotonic() - self._last_mono) >= self._refresh_sec

    def refresh(self, force: bool = False) -> None:
        if not force and not self._should_refresh() and self._by_quad:
            return
        new_map: Dict[Quad, PidInfo] = {}
        try:
            out = subprocess.check_output(
                ["ss", "-tn", "state", "established"],
                stderr=subprocess.DEVNULL,
                text=True,
                timeout=5,
            )
        except (FileNotFoundError, subprocess.CalledProcessError, subprocess.TimeoutExpired):
            with self._lock:
                self._last_mono = time.monotonic()
            return

        for line in out.splitlines():
            line = line.strip()
            if not line or line.startswith("State"):
                continue
            m = _SS_LINE.match(line)
            if not m:
                continue
            loc = _strip_brackets(m.group("loc"))
            rem = _strip_brackets(m.group("rem"))
            try:
                lp = int(m.group("lp"))
                rp = int(m.group("rp"))
            except ValueError:
                continue
            um = _USERS.search(m.group("rest") or "")
            if not um:
                continue
            name, pid_s = um.group(1), um.group(2)
            try:
                pid = int(pid_s)
            except ValueError:
                continue
            quad: Quad = (loc, lp, rem, rp)
            new_map[quad] = (pid, name)

        with self._lock:
            self._by_quad = new_map
            self._last_mono = time.monotonic()

    def lookup(
        self,
        local_ip: str,
        local_port: int,
        remote_ip: str,
        remote_port: int,
        *,
        force_refresh: bool = False,
    ) -> Optional[PidInfo]:
        if force_refresh or self._should_refresh():
            self.refresh(force=True)
        else:
            self.refresh(force=False)
        key: Quad = (local_ip, local_port, remote_ip, remote_port)
        with self._lock:
            return self._by_quad.get(key)

    def iter_mappings(self) -> Iterator[Tuple[Quad, PidInfo]]:
        self.refresh(force=False)
        with self._lock:
            for k, v in self._by_quad.items():
                yield k, v


def read_proc_comm(pid: int) -> str:
    try:
        with open(f"/proc/{pid}/comm", "r", encoding="utf-8", errors="replace") as f:
            return f.read().strip()
    except OSError:
        return ""
