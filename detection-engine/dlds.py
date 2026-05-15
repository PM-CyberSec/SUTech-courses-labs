import requests
from datetime import datetime, timezone
import time

API_URL = "http://127.0.0.1:8000/api/dlds/alerts"

def send_event(description):
    payload = {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "type": "alert",
        "pid": 0,
        "process_name": "python-test",
        "file": "",
        "src_ip": "",
        "src_port": 0,
        "dst_ip": "",
        "dst_port": 0,
        "bytes_sent": 0,
        "alert_type": "PYTHON_TEST",
        "severity": "LOW",
        "description": description
    }

    try:
        r = requests.post(API_URL, json=payload, timeout=5)
        print("STATUS:", r.status_code)
        print("RESPONSE:", r.text)
    except Exception as e:
        print("ERROR:", e)


if __name__ == "__main__":
    while True:
        send_event("test alert from python engine")
        time.sleep(5)