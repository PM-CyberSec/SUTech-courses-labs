import os
import re
import time
from typing import Generator, Dict, Any


def tail_auditd_events(log_path: str) -> Generator[Dict[str, Any], None, None]:
    """
    Tails Linux audit.log and yields process/file events.
    Handles file recreation / rotation.
    """
    poll = float(os.getenv("PIPELINE_AUDITD_POLL_SEC", "0.5"))
    default_start = "false" if "/data/logs/" in log_path or log_path.startswith("data/logs/") else "true"
    start_from_end = os.getenv("PIPELINE_AUDITD_FROM_END", default_start).lower() == "true"

    # If file doesn't exist, wait for it or return empty
    while not os.path.exists(log_path):
        print(f"[Auditd] Log file not found: {log_path}. Waiting...")
        time.sleep(2.0)

    msg_re = re.compile(r"msg=audit\(([^)]+)\)")
    type_re = re.compile(r"^type=(\S+)")
    comm_re = re.compile(r'\bcomm="([^"]*)"')
    exe_re = re.compile(r'\bexe="([^"]*)"')
    name_re = re.compile(r'\bname="([^"]*)"')
    pid_re = re.compile(r"\bpid=(\d+)\b")
    uid_re = re.compile(r"\buid=(\d+)\b")
    gid_re = re.compile(r"\bgid=(\d+)\b")
    syscall_re = re.compile(r"\bsyscall=(\d+)\b")

    def parse_line(line: str) -> Dict[str, Any] | None:
        raw = line.strip()
        if raw == "":
            return None

        match_type = type_re.search(raw)
        match_msg = msg_re.search(raw)
        if match_type is None or match_msg is None:
            return None

        line_type = match_type.group(1).upper()
        ts = match_msg.group(1).split(":", 1)[0]

        pid_match = pid_re.search(raw)
        uid_match = uid_re.search(raw)
        gid_match = gid_re.search(raw)
        comm_match = comm_re.search(raw)
        exe_match = exe_re.search(raw)
        name_match = name_re.search(raw)

        event: Dict[str, Any] = {
            "timestamp": ts,
            "pid": int(pid_match.group(1)) if pid_match else 0,
            "uid": int(uid_match.group(1)) if uid_match else 0,
            "gid": int(gid_match.group(1)) if gid_match else 0,
            "comm": comm_match.group(1) if comm_match else "",
            "exe": exe_match.group(1) if exe_match else "",
            "name": name_match.group(1) if name_match else "",
            "type": "",
        }

        # Map Linux audit line types to pipeline event categories.
        if line_type in {"SYSCALL", "EXECVE"}:
            syscall_match = syscall_re.search(raw)
            syscall = int(syscall_match.group(1)) if syscall_match else -1
            event["type"] = "execve" if syscall == 59 or line_type == "EXECVE" else "syscall"
            return event

        if line_type == "PATH" and event["name"]:
            event["type"] = "open"
            return event

        return None

    f = None
    current_inode = None

    try:
        while True:
            if f is None:
                f = open(log_path, "r", encoding="utf-8", errors="ignore")
                stat = os.fstat(f.fileno())
                current_inode = stat.st_ino
                if start_from_end:
                    f.seek(0, os.SEEK_END)

            line = f.readline()
            if line:
                event = parse_line(line)
                if event is not None:
                    yield event
                continue

            # Detect rotation/recreate via inode change
            try:
                latest_inode = os.stat(log_path).st_ino
            except FileNotFoundError:
                latest_inode = None

            if latest_inode is None or latest_inode != current_inode:
                f.close()
                f = None
                current_inode = None
                time.sleep(poll)
                continue

            time.sleep(poll)
    finally:
        if f is not None:
            f.close()
