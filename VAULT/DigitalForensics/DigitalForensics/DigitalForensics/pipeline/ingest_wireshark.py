import os
import subprocess
import logging

# Ensure output_manager and normalizer are importable
from pipeline.normalizer import normalize_event

def extract_tshark_metadata(pcap_path: str):
    """
    Uses tshark to extract packet-level metadata from a PCAP/PCAPNG file.
    Returns a list of raw event dictionaries.
    """
    if not os.path.exists(pcap_path):
        raise FileNotFoundError(f"PCAP file not found: {pcap_path}")

    # Build tshark command to output fields efficiently
    command = [
        "tshark",
        "-r", pcap_path,
        "-T", "fields",
        "-e", "frame.time_epoch",
        "-e", "ip.src",
        "-e", "ip.dst",
        "-e", "tcp.srcport",
        "-e", "tcp.dstport",
        "-e", "udp.srcport",
        "-e", "udp.dstport",
        "-e", "frame.protocols",
        "-e", "frame.len",
        "-e", "tcp.flags.str",
        "-e", "dns.qry.name",
        "-e", "http.host",
        "-e", "tls.handshake.extensions_server_name",
        "-E", "separator=|",
        "-E", "header=y",
        "-E", "quote=n"
    ]

    events = []
    try:
        result = subprocess.run(command, capture_output=True, text=True, check=True)
        lines = result.stdout.strip().split('\n')
        if len(lines) <= 1:
            return events

        headers = lines[0].split('|')
        for line in lines[1:]:
            values = line.split('|')
            if len(values) == len(headers):
                event = dict(zip(headers, values))
                events.append(event)
    except subprocess.CalledProcessError as e:
        logging.error(f"tshark failed: {e.stderr}")
        raise
    except FileNotFoundError:
        logging.error("tshark is not installed or not in PATH.")
        raise

    return events

def ingest_wireshark(pcap_path: str, output_manager):
    """
    Ingests a Wireshark PCAP file, parses it with tshark, normalizes the events,
    and writes them out using the provided output manager.
    """
    raw_events = extract_tshark_metadata(pcap_path)
    count = 0
    for raw in raw_events:
        # Pre-process raw tshark dictionary for the normalizer
        src_port = raw.get("tcp.srcport") or raw.get("udp.srcport") or "0"
        dst_port = raw.get("tcp.dstport") or raw.get("udp.dstport") or "0"
        
        raw["tshark_src_port"] = src_port.split(',')[0] if src_port else "0"
        raw["tshark_dst_port"] = dst_port.split(',')[0] if dst_port else "0"
        
        normalized = normalize_event("wireshark", raw)
        
        # Add tshark-specific enrichments
        normalized["tcp_flags"] = raw.get("tcp.flags.str", "")
        normalized["dns_query"] = raw.get("dns.qry.name", "")
        normalized["http_host"] = raw.get("http.host", "")
        normalized["tls_sni"] = raw.get("tls.handshake.extensions_server_name", "")
        
        output_manager.write_event(normalized)
        count += 1

    return count

if __name__ == "__main__":
    import sys
    import argparse
    from pipeline.output_manager import OutputManager

    parser = argparse.ArgumentParser(description="Ingest Wireshark PCAP using tshark")
    parser.add_argument("pcap_path", help="Path to the PCAP file")
    parser.add_argument("--outdir", default="data/logs", help="Output directory for JSON/CSV logs")
    args = parser.parse_args()

    out_manager = OutputManager(args.outdir)
    processed = ingest_wireshark(args.pcap_path, out_manager)
    print(f"Successfully processed {processed} packets from {args.pcap_path}")
