import json
import os
import time
from typing import Generator, Dict, Any


def tail_zeek_json(log_path: str) -> Generator[Dict[str, Any], None, None]:
    """
    Tails Zeek JSON logs and survives common log rotation patterns.
    """
    poll = float(os.getenv("PIPELINE_ZEEK_POLL_SEC", "0.2"))
    default_start = "false" if "_offline" in log_path else "true"
    start_from_end = os.getenv("PIPELINE_ZEEK_FROM_END", default_start).lower() == "true"

    while not os.path.exists(log_path):
        print(f"[Zeek] Log file not found: {log_path}. Waiting...")
        time.sleep(1.0)

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
                try:
                    yield json.loads(line)
                except json.JSONDecodeError:
                    continue
                continue

            # Detect rotation/recreate via inode change.
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
