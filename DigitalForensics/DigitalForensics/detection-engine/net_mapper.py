"""
Map (local_ip, local_port, remote_ip, remote_port) -> (pid, process name) using ss.
Refreshes on a short TTL to stay current without blocking ingestion on every event.
"""

from __future__ import annotations

import logging
import os
import re
import shlex
import subprocess
import threading
import time
from typing import Dict, Iterator, Optional, Tuple

from config import getenv_float, getenv_str

LOGGER = logging.getLogger(__name__)

Quad = Tuple[str, int, str, int]
PidInfo = Tuple[int, str]

_USERS = re.compile(r'users:\(\("([^"]+)",pid=(\d+)', re.DOTALL)


def _strip_brackets(addr: str) -> str:
    if addr.startswith("[") and addr.endswith("]"):
        return addr[1:-1]
    return addr


def _ss_command() -> list[str]:
    raw = getenv_str("DLDS_SS_CMD", "ss -tnp state established")
    parts = shlex.split(raw)
    if not parts:
        return ["ss", "-tnp", "state", "established"]
    return parts


def _split_endpoint(endpoint: str) -> Optional[Tuple[str, int]]:
    endpoint = endpoint.strip()
    if not endpoint:
        return None

    host, sep, port_s = endpoint.rpartition(":")
    if not sep:
        return None

    host = _strip_brackets(host)
    try:
        port = int(port_s)
    except ValueError:
        return None

    return host, port


class NetMapper:
    def __init__(self, refresh_sec: float | None = None) -> None:
        self._lock = threading.Lock()
        self._by_quad: Dict[Quad, PidInfo] = {}
        self._last_mono = 0.0
        self._refresh_sec = float(
            refresh_sec if refresh_sec is not None else getenv_float("DLDS_SS_REFRESH_SEC", 1.0)
        )
        self._warned_missing_pid = False

    def _should_refresh(self) -> bool:
        return (time.monotonic() - self._last_mono) >= self._refresh_sec

    def refresh(self, force: bool = False) -> None:
        if not force and not self._should_refresh() and self._by_quad:
            return

        command = _ss_command()
        new_map: Dict[Quad, PidInfo] = {}
        try:
            out = subprocess.check_output(
                command,
                stderr=subprocess.DEVNULL,
                text=True,
                timeout=5,
            )
        except (FileNotFoundError, subprocess.CalledProcessError, subprocess.TimeoutExpired) as exc:
            LOGGER.warning("Failed to read socket mapping via %s: %s", command, exc)
            with self._lock:
                self._last_mono = time.monotonic()
            return

        parsed_lines = 0
        for line in out.splitlines():
            line = line.strip()
            if not line or line.startswith("State"):
                continue

            parts = line.split()
            if len(parts) < 5:
                continue
            parsed_lines += 1

            local_raw = parts[3]
            remote_raw = parts[4]
            rest = " ".join(parts[5:]) if len(parts) > 5 else ""

            local = _split_endpoint(local_raw)
            remote = _split_endpoint(remote_raw)
            if local is None or remote is None:
                continue
            loc, lp = local
            rem, rp = remote

            um = _USERS.search(rest)
            if not um:
                continue

            name, pid_s = um.group(1), um.group(2)
            try:
                pid = int(pid_s)
            except ValueError:
                continue

            quad: Quad = (loc, lp, rem, rp)
            new_map[quad] = (pid, name)

        if parsed_lines > 0 and not new_map and not self._warned_missing_pid:
            LOGGER.info(
                "No PID mappings found via ss. If you want process mapping, run with sudo."
            )
            self._warned_missing_pid = True

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
