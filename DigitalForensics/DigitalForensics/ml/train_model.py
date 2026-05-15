import argparse
import glob
import json
import os
from datetime import datetime, timezone
from typing import Iterable, List, Optional

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    f1_score,
    precision_score,
    recall_score,
)
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder

# Paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MODEL_DIR = os.path.join(BASE_DIR, "ml", "models")
MODEL_PATH = os.path.join(MODEL_DIR, "rf_model.pkl")
META_PATH = os.path.join(MODEL_DIR, "model_metadata.json")

os.makedirs(MODEL_DIR, exist_ok=True)


def _first_present(df: pd.DataFrame, candidates: Iterable[str], default=0):
    for col in candidates:
        if col in df.columns:
            return df[col]
    return pd.Series([default] * len(df))


def _infer_label_column(df: pd.DataFrame) -> Optional[str]:
    for col in ("label", "Label", "class", "Class", "attack_cat", "Attack"):
        if col in df.columns:
            return col
    return None


def _normalize_label(v) -> str:
    text = str(v).strip().lower()
    if text in {"0", "benign", "normal"}:
        return "benign"
    if text in {"1", "suspicious"}:
        return "suspicious"
    if text in {"2", "malicious", "attack"}:
        return "malicious"

    # Dataset-specific tags
    suspicious_keywords = ["recon", "analysis", "fuzzer", "scan"]
    malicious_keywords = [
        "dos",
        "ddos",
        "exploit",
        "backdoor",
        "worm",
        "shellcode",
        "bot",
        "infiltration",
        "sql",
        "xss",
        "heartbleed",
        "bruteforce",
        "brute force",
    ]
    if any(k in text for k in malicious_keywords):
        return "malicious"
    if any(k in text for k in suspicious_keywords):
        return "suspicious"

    # Conservative default
    return "suspicious"


def _build_features(df: pd.DataFrame) -> pd.DataFrame:
    out = pd.DataFrame(index=df.index)
    out["src_port"] = pd.to_numeric(
        _first_present(df, ["src_port", "Source Port", "sport", "spt", "id.orig_p"]),
        errors="coerce",
    ).fillna(0).astype(int)
    out["dst_port"] = pd.to_numeric(
        _first_present(df, ["dst_port", "Destination Port", "dport", "dpt", "id.resp_p"]),
        errors="coerce",
    ).fillna(0).astype(int)

    out["protocol"] = (
        _first_present(df, ["protocol", "proto", "Protocol", "proto_name"], "unknown")
        .astype(str)
        .str.lower()
    )

    out["bytes_sent"] = pd.to_numeric(
        _first_present(
            df,
            [
                "bytes_sent",
                "orig_bytes",
                "bytes_toserver",
                "sbytes",
                "TotLen Fwd Pkts",
                "tot_len_fwd_pkts",
            ],
        ),
        errors="coerce",
    ).fillna(0)

    out["duration"] = pd.to_numeric(
        _first_present(df, ["duration", "dur", "Flow Duration", "flow_duration"]),
        errors="coerce",
    ).fillna(0.0)

    # Severity scale normalized to [0..3] if source field exists.
    out["severity"] = pd.to_numeric(
        _first_present(df, ["severity", "alert_severity", "Severity"], 0),
        errors="coerce",
    ).fillna(0).clip(0, 3).astype(int)

    out["frame_length"] = pd.to_numeric(
        _first_present(df, ["frame_length", "frame.len", "Pkt Len Min", "pkt_len_min"]),
        errors="coerce",
    ).fillna(out["bytes_sent"]).astype(int)

    out["tcp_flags"] = _first_present(
        df, ["tcp_flags", "Flags", "flag", "history"], ""
    ).astype(str)

    dns_col = _first_present(df, ["dns_query", "dns.qry.name"], "")
    http_col = _first_present(df, ["http_host", "http.host"], "")
    tls_col = _first_present(df, ["tls_sni", "tls.sni"], "")
    out["dns_query_presence"] = dns_col.astype(str).str.strip().ne("").astype(int)
    out["http_host_presence"] = http_col.astype(str).str.strip().ne("").astype(int)
    out["tls_sni_presence"] = tls_col.astype(str).str.strip().ne("").astype(int)
    return out


def _load_dataset(csv_file: Optional[str], csv_dir: Optional[str]) -> pd.DataFrame:
    sources: List[str] = []
    if csv_file:
        sources.append(csv_file)
    if csv_dir:
        sources.extend(sorted(glob.glob(os.path.join(csv_dir, "*.csv"))))

    if not sources:
        raise FileNotFoundError(
            "No dataset provided. Pass --dataset-file or --dataset-dir with real data."
        )

    frames = []
    for path in sources:
        df = pd.read_csv(path, low_memory=False)
        df["__dataset_source"] = os.path.basename(path)
        frames.append(df)
    return pd.concat(frames, ignore_index=True)


def train(dataset_file: Optional[str], dataset_dir: Optional[str]):
    df_raw = _load_dataset(dataset_file, dataset_dir)
    label_col = _infer_label_column(df_raw)
    if not label_col:
        raise ValueError(
            "No label column found. Expected one of: label, Label, class, attack_cat."
        )

    labels = df_raw[label_col].map(_normalize_label)
    features_raw = _build_features(df_raw)

    proto_encoder = LabelEncoder()
    flag_encoder = LabelEncoder()
    features_raw["protocol_enc"] = proto_encoder.fit_transform(features_raw["protocol"])
    features_raw["flags_enc"] = flag_encoder.fit_transform(features_raw["tcp_flags"])

    feature_cols = [
        "src_port",
        "dst_port",
        "protocol_enc",
        "bytes_sent",
        "duration",
        "severity",
        "frame_length",
        "flags_enc",
        "dns_query_presence",
        "http_host_presence",
        "tls_sni_presence",
    ]
    X = features_raw[feature_cols].fillna(0)
    y = labels

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    clf = RandomForestClassifier(
        n_estimators=300,
        random_state=42,
        n_jobs=-1,
        class_weight="balanced_subsample",
    )
    clf.fit(X_train, y_train)

    preds = clf.predict(X_test)
    acc = accuracy_score(y_test, preds)
    prec = precision_score(y_test, preds, average="weighted", zero_division=0)
    rec = recall_score(y_test, preds, average="weighted", zero_division=0)
    f1 = f1_score(y_test, preds, average="weighted", zero_division=0)

    print("\n--- Model Evaluation ---")
    print(f"Accuracy:  {acc:.4f}")
    print(f"Precision: {prec:.4f}")
    print(f"Recall:    {rec:.4f}")
    print(f"F1-Score:  {f1:.4f}")
    print("\nDetailed Report:\n", classification_report(y_test, preds, zero_division=0))

    bundle = {
        "model": clf,
        "features": feature_cols,
        "proto_encoder": proto_encoder,
        "flag_encoder": flag_encoder,
        "model_version": "rf-prod-v2.0",
    }
    joblib.dump(bundle, MODEL_PATH)

    metadata = {
        "version": "2.0",
        "model_type": "RandomForestClassifier",
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "dataset_file": dataset_file,
        "dataset_dir": dataset_dir,
        "rows": int(len(df_raw)),
        "features": feature_cols,
        "metrics": {
            "accuracy": acc,
            "precision": prec,
            "recall": rec,
            "f1_score": f1,
        },
        "label_distribution": y.value_counts().to_dict(),
    }
    with open(META_PATH, "w", encoding="utf-8") as f:
        json.dump(metadata, f, indent=2)

    print(f"\n[✔] Model bundle saved to {MODEL_PATH}")
    print(f"[✔] Metadata saved to {META_PATH}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Train DLDS model from real datasets (no synthetic fallback)."
    )
    parser.add_argument(
        "--dataset-file",
        default=os.getenv("DLDS_TRAIN_DATASET_FILE", ""),
        help="Path to a single CSV dataset file.",
    )
    parser.add_argument(
        "--dataset-dir",
        default=os.getenv("DLDS_TRAIN_DATASET_DIR", ""),
        help="Path to a directory containing CSV files (CIC-IDS2017/UNSW-NB15 splits).",
    )
    args = parser.parse_args()

    dataset_file = args.dataset_file or None
    dataset_dir = args.dataset_dir or None
    train(dataset_file, dataset_dir)

