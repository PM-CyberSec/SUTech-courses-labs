"""
DLDS entrypoint: optionally boot host services, then run Zeek, Suricata,
auditd ingestion and correlation/detection loops in resilient worker threads.
"""

from __future__ import annotations

import logging
import subprocess
import threading
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Callable

from requests import Session

from config import (
    build_http_session,
    detection_api_timeout,
    detection_api_url,
    getenv_bool,
    getenv_float,
    setup_logging,
)
from correlator import Correlator
from process_monitor import process_event_stream
from suricata_stream import stream_suricata
from zeek_stream import stream_zeek

LOGGER = logging.getLogger(__name__)

_ALLOWED_TYPES = {"alert", "network", "process", "test"}
_ALLOWED_SEVERITIES = {"LOW", "MEDIUM", "HIGH", "CRITICAL"}


def _run_services_script() -> None:
    # Safer default for production workers: do not auto-manage host services unless requested.
    if getenv_bool("DLDS_SKIP_RUN_SERVICES", default=True):
        LOGGER.info("Skipping run_services.sh (DLDS_SKIP_RUN_SERVICES=true)")
        return

    script = Path(__file__).resolve().parent / "run_services.sh"
    if not script.exists():
        LOGGER.warning("run_services.sh not found at %s; continuing without service bootstrap", script)
        return

    timeout = max(5.0, getenv_float("DLDS_SERVICES_TIMEOUT", 45.0))
    LOGGER.info("Running service bootstrap script: %s", script)

    try:
        completed = subprocess.run(
            ["bash", str(script)],
            check=False,
            stdin=subprocess.DEVNULL,
            stdout=None if getenv_bool("DLDS_SERVICES_VERBOSE") else subprocess.DEVNULL,
            stderr=None if getenv_bool("DLDS_SERVICES_VERBOSE") else subprocess.DEVNULL,
            timeout=timeout,
            cwd=str(script.parent),
        )
    except subprocess.TimeoutExpired:
        LOGGER.warning("run_services.sh timed out after %.1fs; continuing", timeout)
        return
    except OSError as exc:
        LOGGER.warning("Could not execute run_services.sh: %s", exc)
        return

    if completed.returncode != 0:
        LOGGER.warning("run_services.sh exited with status=%s; continuing", completed.returncode)


def _as_int(value: Any, fallback: int = 0) -> int:
    try:
        return int(value) if value is not None else fallback
    except (TypeError, ValueError):
        return fallback


def _normalize_event(event: dict[str, Any]) -> dict[str, Any]:
    event_type = str(event.get("type", "network")).lower()
    if event_type not in _ALLOWED_TYPES:
        event_type = "test"

    severity = str(event.get("severity", "LOW")).upper()
    if severity not in _ALLOWED_SEVERITIES:
        severity = "LOW"

    description_raw = event.get("description")
    description = "" if description_raw is None else str(description_raw)

    process_name = event.get("process_name") or event.get("executable") or None
    file_path = event.get("file_path") or event.get("file") or None

    alert_type = event.get("alert_type") or event.get("category") or None

    # Always use the current time so that static logs get treated as live events, bypassing duplicate hashes
    event_time = datetime.now(timezone.utc).isoformat()

    # Keep both canonical and backward-compatible fields so Laravel can map consistently.
    return {
        "event_time": event_time,
        "timestamp": event_time,
        "type": event_type,
        "severity": severity,
        "description": description[:16384],
        "pid": _as_int(event.get("pid"), 0),
        "process_name": process_name,
        "file_path": file_path,
        "file": file_path,
        "src_ip": event.get("src_ip") or None,
        "dst_ip": event.get("dst_ip") or None,
        "src_port": _as_int(event.get("src_port"), 0),
        "dst_port": _as_int(event.get("dst_port"), 0),
        "bytes_sent": max(0, _as_int(event.get("bytes_sent"), 0)),
        "alert_type": alert_type,
    }


def send_event(event: dict[str, Any], session: Session) -> bool:
    """Send a DLDS event to Laravel API."""
    api_url = detection_api_url()
    if not api_url:
        LOGGER.error("DLDS_API_URL/LARAVEL_API_URL is not set; cannot send event")
        return False

    payload = _normalize_event(event)
    timeout = detection_api_timeout()
    LOGGER.debug("Built Laravel payload=%s", payload)

    try:
        from config import signed_headers
        LOGGER.debug("Posting event to %s", api_url)
        raw_body, headers = signed_headers(payload)
        response = session.post(api_url, data=raw_body, headers=headers, timeout=timeout)
        LOGGER.info("POST %s status=%s", api_url, response.status_code)
        if response.status_code in (200, 201):
            return True

        body_preview = response.text[:300].replace("\n", " ")
        LOGGER.warning(
            "Event API returned non-success status=%s body=%s",
            response.status_code,
            body_preview,
        )
        return False
    except Exception as exc:  # requests exceptions + network stack errors
        LOGGER.exception("Request to event API failed: %s", exc)
        return False


def _safe_worker(name: str, stop: threading.Event, callback: Callable[[], None]) -> None:
    while not stop.is_set():
        try:
            callback()
            return
        except Exception:
            LOGGER.exception("Worker %s crashed; restarting in 1s", name)
            time.sleep(1.0)


def main() -> None:
    setup_logging()
    _run_services_script()

    api_url = detection_api_url()
    if not api_url:
        LOGGER.warning("No DLDS_API_URL/LARAVEL_API_URL configured. HTTP forwarding disabled.")
    else:
        LOGGER.info("Event forwarding enabled: %s", api_url)

    session = build_http_session()

    if getenv_bool("DLDS_TEST_EVENT", default=True):
        send_event(
            {
                "type": "test",
                "severity": "LOW",
                "description": "DLDS Python engine startup test",
            },
            session,
        )

    correlator = Correlator(http_session=session)
    stop = threading.Event()

    def zeek_worker() -> None:
        for event in stream_zeek():
            if stop.is_set():
                break
            LOGGER.debug("Zeek event received by pipeline type=%s source=%s", event.get("type"), event.get("source"))
            correlator.handle_event(event)

    def suricata_worker() -> None:
        for event in stream_suricata():
            if stop.is_set():
                break
            LOGGER.debug(
                "Suricata event received by pipeline type=%s source=%s",
                event.get("type"),
                event.get("source"),
            )
            correlator.handle_event(event)

    def process_worker() -> None:
        for event in process_event_stream():
            if stop.is_set():
                break
            LOGGER.debug("Process event received by pipeline type=%s source=%s", event.get("type"), event.get("source"))
            correlator.handle_event(event)

    def detection_worker() -> None:
        interval = max(0.1, getenv_float("DLDS_DETECT_INTERVAL", 0.5))
        while not stop.is_set():
            correlator.detect()
            time.sleep(interval)

    threads = [
        threading.Thread(target=lambda: _safe_worker("zeek", stop, zeek_worker), name="zeek", daemon=True),
        threading.Thread(target=lambda: _safe_worker("suricata", stop, suricata_worker), name="suricata", daemon=True),
        threading.Thread(target=lambda: _safe_worker("auditd", stop, process_worker), name="auditd", daemon=True),
        threading.Thread(target=lambda: _safe_worker("detect", stop, detection_worker), name="detect", daemon=True),
    ]

    for thread in threads:
        thread.start()

    try:
        while True:
            time.sleep(5)
            for thread in threads:
                if not thread.is_alive():
                    LOGGER.warning("Worker thread %s is not alive", thread.name)
    except KeyboardInterrupt:
        LOGGER.info("Stopping DLDS engine (Ctrl+C)")
        stop.set()
        try:
            for thread in threads:
                thread.join(timeout=1)
        except KeyboardInterrupt:
            pass


if __name__ == "__main__":
    main()
