import os
import time
import threading
import json
import socket
import logging

from pipeline.ingest_zeek import tail_zeek_json
from pipeline.ingest_suricata import tail_suricata_eve
from pipeline.ingest_auditd import tail_auditd_events
from pipeline.normalizer import normalize_event
from pipeline.correlator import Correlator
from pipeline.inference import MLEngine
from pipeline.output_manager import OutputManager
from pipeline.http_ingester import HTTPIngester

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")

# Configuration via environment variables
ZEEK_LOG = os.getenv("ZEEK_LOG_PATH", "data/logs/zeek_offline/conn.log")
SURICATA_LOG = os.getenv("SURICATA_LOG_PATH", "data/logs/suricata_offline/eve.json")
AUDITD_LOG = os.getenv("AUDITD_LOG_PATH", "/var/log/audit/audit.log")
LOCAL_LOG_DIR = os.getenv("LOCAL_LOG_DIR", "data/logs")

class RealTimePipeline:
    def __init__(self):
        self.correlator = Correlator(time_window_seconds=30)
        self.ml_engine = MLEngine()
        self.output_manager = OutputManager(LOCAL_LOG_DIR)
        self.http_ingester = HTTPIngester()
        
        # Filebeat handles ELK streaming automatically via data/logs/*.json
        self.logstash_available = False
        if self.output_manager.kafka_sink.enabled:
            logging.info("Kafka fan-out enabled for enriched events.")
        else:
            logging.info("Kafka fan-out disabled (local logs only).")
        
        if self.http_ingester.enabled:
            logging.info(f"HTTP API ingestion enabled: {self.http_ingester.endpoint}")
        else:
            logging.info("HTTP API ingestion disabled.")

    def process_event(self, normalized_event: dict):
        """Passes the event through ML, local storage, HTTP API, and ELK."""
        enriched = self.ml_engine.classify(normalized_event)
        
        # Write to local JSON/CSV (Filebeat tails this for zero data loss)
        self.output_manager.write_event(enriched)
        
        # POST to Laravel DLDS API for immediate ingestion
        self.http_ingester.ingest(enriched)
        
        if enriched.get("ml_label") == "malicious":
            logging.warning(f"🚨 ALARM: Malicious traffic detected -> {enriched.get('evidence_summary')}")
        elif enriched.get("ml_label") == "suspicious":
            logging.info(f"⚠️ Warning: Suspicious traffic -> {enriched.get('src_ip')} to {enriched.get('dst_port')}")

    def consume_zeek(self):
        logging.info(f"Starting Zeek log tailer on {ZEEK_LOG}")
        for raw_zeek in tail_zeek_json(ZEEK_LOG):
            normalized = normalize_event("zeek", raw_zeek)
            self.correlator.add_zeek_event(normalized)
            # We process raw zeek events as background context telemetry
            self.process_event(normalized)

    def consume_suricata(self):
        logging.info(f"Starting Suricata log tailer on {SURICATA_LOG}")
        for raw_suri in tail_suricata_eve(SURICATA_LOG):
            normalized = normalize_event("suricata", raw_suri)
            # Correlate Suricata alert with Zeek context
            correlated = self.correlator.correlate_suricata_alert(normalized)
            self.process_event(correlated)

    def consume_auditd(self):
        logging.info(f"Starting Auditd log tailer on {AUDITD_LOG}")
        for raw_audit in tail_auditd_events(AUDITD_LOG):
            normalized = normalize_event("auditd", raw_audit)
            self.process_event(normalized)

    def start(self):
        logging.info("Starting Real-Time Forensics Pipeline...")
        t1 = threading.Thread(target=self.consume_zeek, daemon=True)
        t2 = threading.Thread(target=self.consume_suricata, daemon=True)
        t3 = threading.Thread(target=self.consume_auditd, daemon=True)
        
        t1.start()
        t2.start()
        t3.start()
        
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            self.output_manager.kafka_sink.flush()
            logging.info("Shutting down pipeline.")

if __name__ == "__main__":
    # To run: export PYTHONPATH=$(pwd) && python3 pipeline/main.py
    pipeline = RealTimePipeline()
    pipeline.start()
