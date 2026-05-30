"""Small utility script to send periodic test alerts to the Laravel API."""

from __future__ import annotations

import logging
import time
from datetime import datetime, timezone

from config import build_http_session, detection_api_timeout, detection_api_url, setup_logging

LOGGER = logging.getLogger(__name__)


def send_event(description: str) -> bool:
    api_url = detection_api_url()
    if not api_url:
        LOGGER.error("DLDS_API_URL/LARAVEL_API_URL is not set")
        return False

    now_iso = datetime.now(timezone.utc).isoformat()
    payload = {
        "event_time": now_iso,
        "timestamp": now_iso,
        "type": "alert",
        "pid": 0,
        "process_name": "python-test",
        "file_path": None,
        "file": None,
        "src_ip": None,
        "src_port": 0,
        "dst_ip": None,
        "dst_port": 0,
        "bytes_sent": 0,
        "alert_type": "PYTHON_TEST",
        "severity": "LOW",
        "description": description,
    }

    session = build_http_session()
    try:
        response = session.post(api_url, json=payload, timeout=detection_api_timeout())
        LOGGER.info("POST %s status=%s", api_url, response.status_code)
        if response.status_code not in (200, 201):
            LOGGER.warning("RESPONSE: %s", response.text[:300])
            return False
        return True
    except Exception:
        LOGGER.exception("Failed to send test event")
        return False


if __name__ == "__main__":
    setup_logging()
    LOGGER.info("DLDS test sender started")
    try:
        while True:
            send_event("test alert from python engine")
            time.sleep(5)
    except KeyboardInterrupt:
        LOGGER.info("DLDS test sender stopped")
