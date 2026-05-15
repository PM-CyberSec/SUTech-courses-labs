"""
Detection rules: sensitive paths, thresholds, IP classification, severity mapping.
"""

from __future__ import annotations

import ipaddress
from typing import Optional

from config import getenv_bool, getenv_int

SENSITIVE_FILES = [
    "secret.txt",
    "passwords",
    "id_rsa",
    "database",
    ".pem",
    "credentials",
    "shadow",
    "The Cybersecurity Trinity.pdf",
]


def exfil_byte_threshold() -> int:
    return max(1, getenv_int("DLDS_EXFIL_BYTES", 1024 * 1024))


def is_sensitive(file_path: str) -> bool:
    if not file_path:
        return False
    norm = file_path.replace("\\", "/")
    return any(token in norm for token in SENSITIVE_FILES)


def is_private_ip(addr: str) -> bool:
    if not addr or addr in ("-", "0.0.0.0"):
        return False
    try:
        ip = ipaddress.ip_address(addr.split("%")[0])
        return ip.is_private or ip.is_loopback or ip.is_link_local
    except ValueError:
        return False


def is_outbound_exfil_candidate(dst_ip: str) -> bool:
    """Treat RFC1918/loopback destinations as lower priority unless disabled."""
    if not dst_ip or dst_ip == "-":
        return False
    if not getenv_bool("DLDS_EXFIL_REQUIRE_PUBLIC_DST", True):
        return True
    return not is_private_ip(dst_ip)


def suricata_severity_label(severity: Optional[int]) -> str:
    """
    Suricata severity: lower is more severe (1 highest).
    Map to engine labels that Laravel accepts.
    """
    if severity is None:
        return "MEDIUM"
    if severity <= 1:
        return "CRITICAL"
    if severity == 2:
        return "HIGH"
    if severity == 3:
        return "MEDIUM"
    return "LOW"


def normalize_severity(label: str) -> str:
    u = (label or "LOW").upper()
    if u in ("LOW", "MEDIUM", "HIGH", "CRITICAL"):
        return u
    return "LOW"


def should_enrich_suricata_signature(signature: Optional[str]) -> bool:
    return bool(signature)
