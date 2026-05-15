#!/bin/bash
# Script to orchestrate offline PCAP processing for forensics validation
# Integrates Zeek, Suricata, and Wireshark/tshark workflows.

PCAP_FILE=$1

if [ -z "$PCAP_FILE" ]; then
    echo "Usage: $0 <path_to_pcap_file>"
    echo "Example: $0 data/pcaps/sample_normal.pcapng"
    exit 1
fi

if [ ! -f "$PCAP_FILE" ]; then
    echo "[!] Error: PCAP file not found: $PCAP_FILE"
    exit 1
fi

PCAP_ABS="$(realpath "$PCAP_FILE")"

echo "============================================="
echo "[*] Initializing Offline PCAP Ingestion Pipeline"
echo "[*] Target File: $PCAP_ABS"
echo "============================================="

# 1. Create PCAP Workflow folders
mkdir -p data/pcaps/processed
mkdir -p data/pcaps/reports
mkdir -p data/logs/zeek_offline
mkdir -p data/logs/suricata_offline

# 2. Tshark Metadata Extraction
echo "[+] Starting Wireshark/tshark metadata extraction..."
if command -v tshark &> /dev/null; then
    export PYTHONPATH="$(pwd):$PYTHONPATH"
    python3 -m pipeline.ingest_wireshark "$PCAP_ABS" --outdir data/pcaps/reports/
else
    echo "    [!] Warning: tshark is not installed. Skipping direct packet metadata extraction."
fi

# 3. Zeek Processing
echo "[+] Starting Zeek PCAP processing..."
cd data/logs/zeek_offline || exit
zeek -r "$PCAP_ABS" LogAscii::use_json=T 2>/dev/null
if [ $? -eq 0 ]; then
    echo "    -> Zeek logs successfully generated."
else
    echo "    [!] Error: Zeek processing failed."
fi
cd ../../../

# 4. Suricata Processing
echo "[+] Starting Suricata PCAP processing..."
suricata -r "$PCAP_ABS" -l data/logs/suricata_offline/ -k none >/dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "    -> Suricata alerts successfully generated at eve.json."
else
    echo "    [!] Error: Suricata processing failed."
fi

echo "============================================="
echo "[*] PCAP Processing Complete!"
echo "    - Zeek Logs: data/logs/zeek_offline/"
echo "    - Suricata Logs: data/logs/suricata_offline/eve.json"
echo "    - tshark Reports: data/pcaps/reports/"
echo "[*] You can now point the Python pipeline to ingest these logs."
echo "============================================="
