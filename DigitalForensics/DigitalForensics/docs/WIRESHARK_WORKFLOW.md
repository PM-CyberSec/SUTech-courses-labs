# Wireshark PCAP Workflow Guide

This document outlines how Wireshark is integrated into the **AI-Assisted Network Traffic Forensics** project as a primary packet capture and validation tool.

## 1. Capturing Traffic with Wireshark
Wireshark provides packet-level visibility into network events. It is used to generate the ground-truth PCAP files that the forensic pipeline analyzes.

### Using the Wireshark GUI:
1. Open Wireshark and select your primary network interface (e.g., `eth0` or `wlan0`).
2. Click **Start Capturing Packets**.
3. Perform the desired activities (e.g., normal web browsing or attack simulations).
4. Click **Stop Capturing Packets**.
5. Save the file: `File -> Save As`. 
   - **Format:** Choose `.pcapng` or `.pcap`.
   - **Location:** Save the file to `data/pcaps/` in the project directory.
   - **Naming Convention:** Use descriptive names like `data/pcaps/sample_normal.pcapng` or `data/pcaps/beaconing_attack.pcap`.

### Using `tshark` CLI:
If you prefer the command line, you can capture traffic using `tshark`:
```bash
sudo tshark -i eth0 -w data/pcaps/cli_capture.pcapng -a duration:60
```
*(This captures traffic on `eth0` for 60 seconds and saves it).*

## 2. Processing the PCAP in the Forensic Pipeline
Once a PCAP is captured, it is processed via the `ingest_pcap.sh` script. This script acts as an orchestrator, distributing the PCAP to three distinct forensic sensors:

1. **Zeek:** Extracts high-level protocol metadata and writes JSON logs (`conn.log`, `dns.log`).
2. **Suricata:** Replays the traffic against IDS signatures and outputs alerts to `eve.json`.
3. **tshark:** Extracts granular packet-level metadata (TLS SNI, DNS queries, TCP flags) directly into the `data/pcaps/reports/` folder.

**Command to process:**
```bash
./simulation/ingest_pcap.sh data/pcaps/sample_normal.pcapng
```

## 3. How the Data Integrates
After `ingest_pcap.sh` completes, the raw data is ready. The Python pipeline uses the `ingest_wireshark.py` module to parse the `tshark` metadata directly, mapping fields like `frame.len` and `ip.src` into the project's unified JSON schema. Similar scripts (`ingest_zeek.py` and `ingest_suricata.py`) exist to process the other generated logs.

This unified, correlated output is what ultimately feeds the Scikit-learn Machine Learning engine and Elasticsearch dashboards.
