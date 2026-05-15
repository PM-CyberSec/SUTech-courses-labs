"""
Stream auditd events from audit.log (tail -F). Parses SYSCALL + PATH groups via audit message id.
Requires readable /var/log/audit/audit.log and appropriate audit rules for file access.
"""

from __future__ import annotations

import os
import re
import subprocess
import time
from datetime import datetime, timezone
from typing import Any, Dict, Generator, List

AUDIT_MSG = re.compile(r"msg=audit\(([^)]+)\)")
TYPE_RE = re.compile(r"^type=(\S+)")
NAME_RE = re.compile(r'\bname="([^"]*)"')
EXE_RE = re.compile(r'exe="([^"]*)"')
PID_RE = re.compile(r"\bpid=(\d+)\b")
NAMETYPE_RE = re.compile(r"nametype=(\S+)")


def _audit_path() -> str:
    return os.environ.get("AUDIT_LOG", "/var/log/audit/audit.log")


def _iso_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _flush_record(
    key: str, rec: Dict[str, Any], paths: List[str]
) -> Generator[Dict[str, Any], None, None]:
    pid = rec.get("pid")
    exe = rec.get("exe", "")
    if pid is None or not paths:
        return
    for path in paths:
        yield {
            "type": "process",
            "source": "auditd",
            "audit_id": key,
            "timestamp": _iso_now(),
            "pid": int(pid),
            "executable": exe,
            "file": path,
            "action": "access",
        }


def _iter_tail_lines(path: str) -> Generator[str, None, None]:
    if not os.path.exists(path):
        return
    try:
        proc = subprocess.Popen(
            ["tail", "-n", "0", "-F", path],
            stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL,
            text=True,
            bufsize=1,
        )
    except FileNotFoundError:
        return

    assert proc.stdout is not None
    try:
        for line in iter(proc.stdout.readline, ""):
            if not line:
                break
            yield line
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=2)
        except subprocess.TimeoutExpired:
            proc.kill()


def process_event_stream() -> Generator[Dict[str, Any], None, None]:
    path = _audit_path()

    while True:
        while not os.path.exists(path):
            time.sleep(2.0)

        pending: Dict[str, Dict[str, Any]] = {}
        path_acc: Dict[str, List[str]] = {}
        order: List[str] = []

        def flush_key(k: str) -> Generator[Dict[str, Any], None, None]:
            if k not in pending:
                return
            rec = pending.pop(k, {})
            paths = path_acc.pop(k, [])
            yield from _flush_record(k, rec, paths)
            if k in order:
                order.remove(k)

        for line in _iter_tail_lines(path):
            m = AUDIT_MSG.search(line)
            if not m:
                continue
            key = m.group(1)
            tm = TYPE_RE.match(line.strip())
            typ = tm.group(1) if tm else ""

            if typ == "SYSCALL":
                pm = PID_RE.search(line)
                em = EXE_RE.search(line)
                pending.setdefault(key, {})
                if pm:
                    pending[key]["pid"] = int(pm.group(1))
                if em:
                    pending[key]["exe"] = em.group(1)
                path_acc.setdefault(key, [])
                if key not in order:
                    order.append(key)

            elif typ == "PATH":
                nm = NAME_RE.search(line)
                nt = NAMETYPE_RE.search(line)
                nametype = nt.group(1) if nt else ""
                if not nm:
                    continue
                if nametype and nametype not in ("NORMAL", "UNKNOWN", "CREATE", "PARENT"):
                    continue
                path_acc.setdefault(key, [])
                name = nm.group(1)
                if name and name not in path_acc[key]:
                    path_acc[key].append(name)
                pending.setdefault(key, {})

            elif typ == "EOE":
                yield from flush_key(key)

            if len(order) > 500:
                old = order.pop(0)
                yield from flush_key(old)

        time.sleep(0.5)
