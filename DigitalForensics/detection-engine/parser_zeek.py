"""
Zeek TSV log parsing with dynamic #fields header (handles ZeekControl rotation and schema changes).
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional

_UNSET = "-"


def parse_fields_header(line: str) -> Optional[List[str]]:
    if not line.startswith("#fields"):
        return None
    parts = line.rstrip("\n").split("\t")
    if len(parts) < 2:
        return None
    return parts[1:]


def _get(row: Dict[str, str], *keys: str, default: str = _UNSET) -> str:
    for k in keys:
        if k in row and row[k] != _UNSET and row[k] != "":
            return row[k]
    return default


def _int_field(val: str) -> int:
    if val in (_UNSET, "", "(empty)"):
        return 0
    try:
        return int(float(val))
    except ValueError:
        return 0


def _bool_field(val: str) -> bool:
    return val.upper() in ("T", "TRUE", "1", "YES")


def row_to_dict(field_names: List[str], parts: List[str]) -> Dict[str, str]:
    row: Dict[str, str] = {}
    for i, name in enumerate(field_names):
        row[name] = parts[i] if i < len(parts) else _UNSET
    return row


def parse_conn_data_line(field_names: List[str], line: str) -> Optional[Dict[str, Any]]:
    if line.startswith("#") or not line.strip():
        return None
    parts = line.rstrip("\n").split("\t")
    if len(parts) < len(field_names):
        parts.extend([_UNSET] * (len(field_names) - len(parts)))
    row = row_to_dict(field_names, parts)

    ts_s = _get(row, "ts")
    orig_h = _get(row, "id.orig_h")
    orig_p = _get(row, "id.orig_p")
    resp_h = _get(row, "id.resp_h")
    resp_p = _get(row, "id.resp_p")
    orig_bytes = _int_field(_get(row, "orig_bytes", default="0"))
    resp_bytes = _int_field(_get(row, "resp_bytes", default="0"))
    local_orig = _bool_field(_get(row, "local_orig", default="F"))

    try:
        orig_port = int(float(orig_p)) if orig_p not in (_UNSET, "") else 0
    except ValueError:
        orig_port = 0
    try:
        resp_port = int(float(resp_p)) if resp_p not in (_UNSET, "") else 0
    except ValueError:
        resp_port = 0

    # Zeek: id.orig_* is the flow initiator; local_orig means the monitored host initiated.
    if local_orig:
        local_ip, local_port = orig_h, orig_port
        remote_ip, remote_port = resp_h, resp_port
        bytes_sent = orig_bytes
        bytes_rcvd = resp_bytes
    else:
        local_ip, local_port = resp_h, resp_port
        remote_ip, remote_port = orig_h, orig_port
        bytes_sent = resp_bytes
        bytes_rcvd = orig_bytes

    return {
        "ts_raw": ts_s,
        "src_ip": orig_h,
        "src_port": orig_port,
        "dst_ip": resp_h,
        "dst_port": resp_port,
        "local_ip": local_ip,
        "local_port": local_port,
        "remote_ip": remote_ip,
        "remote_port": remote_port,
        "orig_bytes": orig_bytes,
        "resp_bytes": resp_bytes,
        "bytes": bytes_sent,
        "bytes_sent": bytes_sent,
        "bytes_rcvd": bytes_rcvd,
        "local_orig": local_orig,
        "proto": _get(row, "proto"),
        "uid": _get(row, "uid"),
    }


def parse_conn_line(line: str, field_names: Optional[List[str]]) -> Optional[Dict[str, Any]]:
    if field_names is None:
        return None
    return parse_conn_data_line(field_names, line)
