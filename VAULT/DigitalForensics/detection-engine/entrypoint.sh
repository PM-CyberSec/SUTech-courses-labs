#!/bin/bash
set -e

echo "[+] Starting Zeek via ZeekControl (correct mode)"

if [ -z "$ZEEK_INTERFACE" ]; then
    ZEEK_INTERFACE=$(ip route | awk '/default/ {print $5}' | head -n1)
fi

echo "[+] Interface: $ZEEK_INTERFACE"

# IMPORTANT: use zeekctl-managed node
cd /opt/zeek/bin
exec ./zeekctl start