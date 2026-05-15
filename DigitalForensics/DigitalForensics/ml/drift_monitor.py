#!/usr/bin/env python3
"""
Feature drift monitor for DLDS telemetry.

Compares baseline training data against recent production-like telemetry
using PSI for numeric features and total variation distance for protocol mix.
"""

from __future__ import annotations

import argparse
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, Iterable, Optional

import numpy as np
import pandas as pd


def _utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def _first_present(df: pd.DataFrame, candidates: Iterable[str], default=0):
    for col in candidates:
        if col in df.columns:
            return df[col]
    return pd.Series([default] * len(df), index=df.index)


def _build_features(df: pd.DataFrame) -> pd.DataFrame:
    out = pd.DataFrame(index=df.index)
    out["src_port"] = pd.to_numeric(
        _first_present(df, ["src_port", "Source Port", "sport", "spt", "id.orig_p"]),
        errors="coerce",
    ).fillna(0)
    out["dst_port"] = pd.to_numeric(
        _first_present(df, ["dst_port", "Destination Port", "dport", "dpt", "id.resp_p"]),
        errors="coerce",
    ).fillna(0)
    out["bytes_sent"] = pd.to_numeric(
        _first_present(df, ["bytes_sent", "orig_bytes", "bytes_toserver", "sbytes"]),
        errors="coerce",
    ).fillna(0)
    out["duration"] = pd.to_numeric(
        _first_present(df, ["duration", "dur", "Flow Duration", "flow_duration"]),
        errors="coerce",
    ).fillna(0.0)
    out["severity"] = pd.to_numeric(
        _first_present(df, ["severity", "alert_severity", "Severity"], 0),
        errors="coerce",
    ).fillna(0).clip(0, 3)
    out["frame_length"] = pd.to_numeric(
        _first_present(df, ["frame_length", "frame.len", "Pkt Len Min", "pkt_len_min"]),
        errors="coerce",
    ).fillna(out["bytes_sent"])
    out["protocol"] = (
        _first_present(df, ["protocol", "proto", "Protocol", "proto_name"], "unknown")
        .astype(str)
        .str.lower()
    )
    return out


def _read_current(path: Path) -> pd.DataFrame:
    if path.suffix.lower() == ".csv":
        return pd.read_csv(path, low_memory=False)
    # default JSONL (one event per line)
    rows = []
    with path.open("r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            try:
                rows.append(json.loads(line))
            except json.JSONDecodeError:
                continue
    return pd.DataFrame(rows)


def _psi(expected: pd.Series, actual: pd.Series, bins: int = 10) -> float:
    expected = expected.astype(float)
    actual = actual.astype(float)
    quantiles = np.linspace(0, 1, bins + 1)
    breakpoints = np.unique(np.quantile(expected, quantiles))
    if len(breakpoints) < 3:
        return 0.0
    expected_hist, _ = np.histogram(expected, bins=breakpoints)
    actual_hist, _ = np.histogram(actual, bins=breakpoints)
    expected_pct = np.where(expected_hist == 0, 1e-6, expected_hist / max(expected_hist.sum(), 1))
    actual_pct = np.where(actual_hist == 0, 1e-6, actual_hist / max(actual_hist.sum(), 1))
    return float(np.sum((expected_pct - actual_pct) * np.log(expected_pct / actual_pct)))


def _tv_distance(expected: pd.Series, actual: pd.Series) -> float:
    exp_dist = expected.value_counts(normalize=True)
    act_dist = actual.value_counts(normalize=True)
    keys = exp_dist.index.union(act_dist.index)
    exp = exp_dist.reindex(keys, fill_value=0.0).values
    act = act_dist.reindex(keys, fill_value=0.0).values
    return float(0.5 * np.abs(exp - act).sum())


def _drift_level(value: float) -> str:
    if value >= 0.30:
        return "high"
    if value >= 0.15:
        return "moderate"
    return "low"


def run(baseline_path: Path, current_path: Path, report_path: Path, min_samples: int) -> int:
    baseline_df = pd.read_csv(baseline_path, low_memory=False)
    current_df = _read_current(current_path)

    if len(current_df) < min_samples:
        report = {
            "run_at": _utc_now(),
            "status": "insufficient_data",
            "current_samples": int(len(current_df)),
            "min_samples": int(min_samples),
        }
        report_path.parent.mkdir(parents=True, exist_ok=True)
        report_path.write_text(json.dumps(report, indent=2), encoding="utf-8")
        return 3

    b = _build_features(baseline_df)
    c = _build_features(current_df)

    numeric_cols = ["src_port", "dst_port", "bytes_sent", "duration", "severity", "frame_length"]
    psi_map: Dict[str, float] = {}
    for col in numeric_cols:
        psi_map[col] = round(_psi(b[col], c[col]), 4)

    proto_drift = round(_tv_distance(b["protocol"], c["protocol"]), 4)
    overall = round(float(np.mean(list(psi_map.values()) + [proto_drift])), 4)

    drift_features = [
        {"feature": col, "psi": val, "level": _drift_level(val)}
        for col, val in psi_map.items()
    ]
    proto_level = _drift_level(proto_drift)

    has_high = any(item["level"] == "high" for item in drift_features) or proto_level == "high"
    status = "alert" if has_high else "ok"

    report = {
        "run_at": _utc_now(),
        "status": status,
        "baseline_path": str(baseline_path),
        "current_path": str(current_path),
        "baseline_samples": int(len(b)),
        "current_samples": int(len(c)),
        "overall_drift_score": overall,
        "numeric_drift": drift_features,
        "protocol_drift": {
            "total_variation_distance": proto_drift,
            "level": proto_level,
        },
    }
    report_path.parent.mkdir(parents=True, exist_ok=True)
    report_path.write_text(json.dumps(report, indent=2), encoding="utf-8")
    return 2 if has_high else 0


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="DLDS feature drift monitor")
    parser.add_argument("--baseline", required=True, help="Baseline CSV path used for training.")
    parser.add_argument("--current", required=True, help="Current data path (CSV or JSONL).")
    parser.add_argument(
        "--report",
        default="data/output/drift_report.json",
        help="Output report path.",
    )
    parser.add_argument(
        "--min-samples",
        type=int,
        default=500,
        help="Minimum current samples required before evaluating drift.",
    )
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    return run(
        baseline_path=Path(args.baseline),
        current_path=Path(args.current),
        report_path=Path(args.report),
        min_samples=args.min_samples,
    )


if __name__ == "__main__":
    raise SystemExit(main())
