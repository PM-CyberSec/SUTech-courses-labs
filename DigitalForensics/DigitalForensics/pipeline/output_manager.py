import json
import csv
import os
import hashlib
from datetime import datetime, timezone

from pipeline.kafka_sink import KafkaSink

class OutputManager:
    def __init__(self, log_dir: str):
        self.log_dir = log_dir
        os.makedirs(self.log_dir, exist_ok=True)
        self.json_file = os.path.join(self.log_dir, "audit_events.json")
        self.csv_file = os.path.join(self.log_dir, "audit_events.csv")
        self.csv_initialized = os.path.exists(self.csv_file)
        self.kafka_sink = KafkaSink()

    @staticmethod
    def _event_id(event: dict) -> str:
        """
        Build a deterministic identifier to deduplicate retries across the pipeline.
        """
        key_parts = [
            str(event.get("timestamp", "")),
            str(event.get("sensor", "")),
            str(event.get("src_ip", "")),
            str(event.get("src_port", "")),
            str(event.get("dst_ip", "")),
            str(event.get("dst_port", "")),
            str(event.get("protocol", "")),
            str(event.get("alert_signature", "")),
            str(event.get("bytes_sent", "")),
        ]
        raw = "|".join(key_parts).encode("utf-8", errors="ignore")
        return hashlib.sha256(raw).hexdigest()

    def write_event(self, event: dict):
        """Writes the unified event to local JSON and CSV audit logs safely."""
        # Clean up internal metadata before outputting
        event_to_write = dict(event)
        event_to_write.pop("_ingest_time", None)
        event_to_write.setdefault(
            "pipeline_ingest_time", datetime.now(timezone.utc).isoformat()
        )
        event_to_write.setdefault("event_id", self._event_id(event_to_write))

        try:
            # 1. Append to JSON log
            with open(self.json_file, "a") as f_json:
                f_json.write(json.dumps(event_to_write) + "\n")

            # 2. Append to CSV log
            with open(self.csv_file, "a", newline='') as f_csv:
                writer = csv.DictWriter(f_csv, fieldnames=event_to_write.keys())
                if not self.csv_initialized:
                    writer.writeheader()
                    self.csv_initialized = True
                writer.writerow(event_to_write)

            # 3. Optionally publish to Kafka for durable fan-out
            self.kafka_sink.publish(event_to_write)
                
        except Exception as e:
            print(f"[Error] Failed to write event to local audit logs: {e}")
