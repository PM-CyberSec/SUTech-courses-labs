import time

class Correlator:
    def __init__(self, time_window_seconds=10):
        self.time_window = time_window_seconds
        # Stores recent zeek events: {(src_ip, dst_ip, src_port, dst_port): event}
        self.zeek_cache = {}

    def clean_cache(self):
        """Removes entries older than the time window to prevent memory leaks."""
        current_time = time.time()
        keys_to_delete = []
        for key, event in self.zeek_cache.items():
            # In a real implementation, parse event['timestamp'] to float
            # Here we use a simplified assumption of ingestion time for safety
            if current_time - event.get("_ingest_time", current_time) > self.time_window:
                keys_to_delete.append(key)
        for key in keys_to_delete:
            del self.zeek_cache[key]

    def add_zeek_event(self, normalized_event: dict):
        self.clean_cache()
        normalized_event["_ingest_time"] = time.time()
        key = (
            normalized_event["src_ip"],
            normalized_event["dst_ip"],
            normalized_event["src_port"],
            normalized_event["dst_port"]
        )
        self.zeek_cache[key] = normalized_event

    def correlate_suricata_alert(self, normalized_alert: dict) -> dict:
        self.clean_cache()
        key = (
            normalized_alert["src_ip"],
            normalized_alert["dst_ip"],
            normalized_alert["src_port"],
            normalized_alert["dst_port"]
        )
        
        if key in self.zeek_cache:
            zeek_event = self.zeek_cache[key]
            # Enrich Suricata alert with Zeek's exact byte counts and connection state
            normalized_alert["bytes_sent"] = zeek_event.get("bytes_sent", 0)
            normalized_alert["bytes_received"] = zeek_event.get("bytes_received", 0)
            normalized_alert["duration"] = zeek_event.get("duration", 0.0)
            normalized_alert["connection_state"] = zeek_event.get("connection_state", "")
            
        return normalized_alert
