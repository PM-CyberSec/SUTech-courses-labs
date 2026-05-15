"""
DLDS entrypoint: starts run_services.sh, then runs Zeek, Suricata, auditd ingestion
and the correlation / detection loop in background threads.
"""

from __future__ import annotations

import os
import subprocess
import threading
import time
import requests
from datetime import datetime, timezone
from dotenv import load_dotenv

load_dotenv()

from correlator import Correlator
from process_monitor import process_event_stream
from suricata_stream import stream_suricata
from zeek_stream import stream_zeek


def _run_services_script() -> None:
    if os.environ.get("DLDS_SKIP_RUN_SERVICES"):
        return
    base = os.path.dirname(os.path.abspath(__file__))
    script = os.path.join(base, "run_services.sh")
    if not os.path.isfile(script):
        return
    try:
        subprocess.run(
            ["bash", script],
            cwd=base,
            check=False,
            stdout=None if os.environ.get("DLDS_SERVICES_VERBOSE") else subprocess.DEVNULL,
            stderr=None if os.environ.get("DLDS_SERVICES_VERBOSE") else subprocess.DEVNULL,
        )
    except OSError:
        pass


def send_event(event: dict) -> bool:
    """
    Send DLDS event to Laravel API.
    Returns True if successful, False otherwise.
    """

    api_url = os.getenv("LARAVEL_API_URL")

    if not api_url:
        print("❌ LARAVEL_API_URL is not set")
        return False

    payload = {
        "timestamp": datetime.now(timezone.utc).isoformat(),  # FIXED (no deprecated utcnow)
        "type": event.get("type", "network"),
        "severity": event.get("severity", "LOW"),
        "description": event.get("description", ""),

        # optional DLDS fields (safe defaults)
        "pid": event.get("pid"),
        "process_name": event.get("process_name"),
        "file": event.get("file"),
        "src_ip": event.get("src_ip"),
        "dst_ip": event.get("dst_ip"),
        "src_port": event.get("src_port"),
        "dst_port": event.get("dst_port"),
        "bytes_sent": event.get("bytes_sent"),
        "alert_type": event.get("alert_type"),
    }

    try:
        response = requests.post(
            api_url,
            json=payload,
            timeout=5,
            headers={"Content-Type": "application/json"},
        )

        print("➡ Status:", response.status_code)

        # IMPORTANT: avoid JSON crash on HTML errors (like 404)
        try:
            print("➡ Response JSON:", response.json())
        except Exception:
            print("➡ Response Text:", response.text[:300])

        return response.status_code in (200, 201)

    except requests.exceptions.RequestException as e:
        print("❌ Request failed:", str(e))
        return False



def main() -> None:
    _run_services_script()

    if os.environ.get("DLDS_TEST_EVENT", "1") == "1":
        send_event({
            "type": "network",
            "severity": "LOW",
            "description": "DLDS Python engine startup test"
        })

    correlator = Correlator()
    stop = threading.Event()

    def zeek_worker() -> None:
        for event in stream_zeek():
            if stop.is_set():
                break
            correlator.handle_event(event)

    def suricata_worker() -> None:
        for event in stream_suricata():
            if stop.is_set():
                break
            correlator.handle_event(event)

    def process_worker() -> None:
        for event in process_event_stream():
            if stop.is_set():
                break
            correlator.handle_event(event)

    def detection_worker() -> None:
        interval = float(os.environ.get("DLDS_DETECT_INTERVAL", "0.5"))
        while not stop.is_set():
            correlator.detect()
            time.sleep(interval)

    threads = [
        threading.Thread(target=zeek_worker, name="zeek", daemon=True),
        threading.Thread(target=suricata_worker, name="suricata", daemon=True),
        threading.Thread(target=process_worker, name="auditd", daemon=True),
        threading.Thread(target=detection_worker, name="detect", daemon=True),
    ]
    for t in threads:
        t.start()
    try:
        while True:
            time.sleep(3600)
    except KeyboardInterrupt:
        stop.set()


if __name__ == "__main__":
    main()
