#!/usr/bin/env bash
set -e

echo "==============================================="
echo "💀 DLDS Realistic Attack Simulator (tcpreplay)"
echo "==============================================="

if ! command -v tcpreplay >/dev/null 2>&1; then
    echo "[!] tcpreplay is not installed."
    echo "[*] Installing tcpreplay..."
    sudo apt update && sudo apt install -y tcpreplay
fi

if [ -z "$1" ]; then
    echo "Usage: $0 <path_to_malware.pcap>"
    echo "Example: $0 data/pcaps/mirai_botnet.pcap"
    exit 1
fi

PCAP_FILE="$1"
INTERFACE="lo" # Default to loopback so we don't spam the actual network

if [ ! -f "$PCAP_FILE" ]; then
    echo "[!] Error: PCAP file '$PCAP_FILE' not found."
    exit 1
fi

echo "[*] Replaying $PCAP_FILE on interface $INTERFACE at 10x speed..."
echo "[*] Zeek and Suricata will now capture these packets as live traffic."
sudo tcpreplay -i "$INTERFACE" -x 10 "$PCAP_FILE"

echo "[✔] Attack simulation complete. Check your DLDS Dashboard / Kibana!"
