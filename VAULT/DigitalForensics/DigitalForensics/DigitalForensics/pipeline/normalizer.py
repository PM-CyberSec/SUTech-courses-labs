def normalize_event(sensor: str, raw_event: dict) -> dict:
    """
    Transforms raw Zeek, Suricata, or Auditd events into a unified JSON schema.
    """
    normalized = {
        "timestamp": "",
        "sensor": sensor,
        "flow_id": "",
        "src_ip": "",
        "dst_ip": "",
        "src_port": 0,
        "dst_port": 0,
        "protocol": "",
        "bytes_sent": 0,
        "bytes_received": 0,
        "duration": 0.0,
        "connection_state": "",
        "alert_signature": "",
        "alert_category": "",
        "severity": 0,
        "ml_label": "unscored",
        "ml_confidence": 0.0,
        "evidence_summary": "",
        "process_name": "",
        "pid": 0,
        "uid": 0,
        "gid": 0,
        "file_path": "",
    }

    if sensor == "zeek":
        normalized["timestamp"] = raw_event.get("ts", "")
        normalized["flow_id"] = raw_event.get("uid", "")
        normalized["src_ip"] = raw_event.get("id.orig_h", "")
        normalized["dst_ip"] = raw_event.get("id.resp_h", "")
        normalized["src_port"] = raw_event.get("id.orig_p", 0)
        normalized["dst_port"] = raw_event.get("id.resp_p", 0)
        normalized["protocol"] = raw_event.get("proto", "unknown")
        normalized["bytes_sent"] = raw_event.get("orig_bytes", 0)
        normalized["bytes_received"] = raw_event.get("resp_bytes", 0)
        normalized["duration"] = raw_event.get("duration", 0.0)
        normalized["connection_state"] = raw_event.get("conn_state", "")

    elif sensor == "suricata":
        normalized["timestamp"] = raw_event.get("timestamp", "")
        normalized["flow_id"] = raw_event.get("flow_id", "")
        normalized["src_ip"] = raw_event.get("src_ip", "")
        normalized["dst_ip"] = raw_event.get("dest_ip", "")
        normalized["src_port"] = raw_event.get("src_port", 0)
        normalized["dst_port"] = raw_event.get("dest_port", 0)
        normalized["protocol"] = raw_event.get("proto", "unknown")
        
        # Suricata specific alert fields
        alert = raw_event.get("alert", {})
        normalized["alert_signature"] = alert.get("signature", "")
        normalized["alert_category"] = alert.get("category", "")
        normalized["severity"] = alert.get("severity", 0)
        flow = raw_event.get("flow", {})
        if flow:
            normalized["bytes_sent"] = int(flow.get("bytes_toserver", 0) or 0)
            normalized["bytes_received"] = int(flow.get("bytes_toclient", 0) or 0)

    elif sensor == "auditd":
        # Auditd process monitoring events
        normalized["timestamp"] = raw_event.get("timestamp", "")
        normalized["process_name"] = raw_event.get("comm", raw_event.get("name", ""))
        normalized["pid"] = int(raw_event.get("pid", 0))
        normalized["uid"] = int(raw_event.get("uid", 0))
        normalized["gid"] = int(raw_event.get("gid", 0))
        normalized["file_path"] = raw_event.get("name", "")
        normalized["alert_category"] = raw_event.get("type", "process")
        # Determine severity based on event type
        if raw_event.get("type") in ["unlink", "execve", "open"]:
            normalized["severity"] = 2  # MEDIUM

    elif sensor == "wireshark":
        normalized["timestamp"] = raw_event.get("frame.time_epoch", "")
        normalized["src_ip"] = raw_event.get("ip.src", "")
        normalized["dst_ip"] = raw_event.get("ip.dst", "")
        normalized["src_port"] = int(raw_event.get("tshark_src_port", 0))
        normalized["dst_port"] = int(raw_event.get("tshark_dst_port", 0))
        normalized["protocol"] = raw_event.get("frame.protocols", "unknown")
        normalized["bytes_sent"] = int(raw_event.get("frame.len", 0))

    return normalized
