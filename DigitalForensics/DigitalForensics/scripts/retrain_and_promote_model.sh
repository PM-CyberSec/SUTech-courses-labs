#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ML_DIR="${ROOT_DIR}/ml"
MODEL_DIR="${ML_DIR}/models"
DETECTION_MODEL_DIR="${ROOT_DIR}/detection-engine/models"
META_FILE="${MODEL_DIR}/model_metadata.json"
MODEL_FILE="${MODEL_DIR}/rf_model.pkl"

MIN_F1="${DLDS_MIN_F1:-0.90}"
MIN_RECALL="${DLDS_MIN_RECALL:-0.88}"
MIN_PRECISION="${DLDS_MIN_PRECISION:-0.88}"

DATASET_FILE="${DLDS_TRAIN_DATASET_FILE:-}"
DATASET_DIR="${DLDS_TRAIN_DATASET_DIR:-}"

if [[ -z "${DATASET_FILE}" && -z "${DATASET_DIR}" ]]; then
  echo "[!] Set DLDS_TRAIN_DATASET_FILE or DLDS_TRAIN_DATASET_DIR before retraining."
  exit 2
fi

mkdir -p "${MODEL_DIR}" "${DETECTION_MODEL_DIR}"

echo "[*] Training model from real datasets..."
if [[ -n "${DATASET_FILE}" ]]; then
  if [[ -n "${DATASET_DIR}" ]]; then
    python3 "${ML_DIR}/train_model.py" --dataset-file "${DATASET_FILE}" --dataset-dir "${DATASET_DIR}"
  else
    python3 "${ML_DIR}/train_model.py" --dataset-file "${DATASET_FILE}"
  fi
else
  python3 "${ML_DIR}/train_model.py" --dataset-dir "${DATASET_DIR}"
fi

if [[ ! -f "${MODEL_FILE}" || ! -f "${META_FILE}" ]]; then
  echo "[!] Training output missing model or metadata."
  exit 3
fi

echo "[*] Validating quality gates..."
python3 - "${META_FILE}" "${MIN_F1}" "${MIN_RECALL}" "${MIN_PRECISION}" <<'PY'
import json
import sys
from pathlib import Path

meta_path = Path(sys.argv[1])
min_f1 = float(sys.argv[2])
min_recall = float(sys.argv[3])
min_precision = float(sys.argv[4])

data = json.loads(meta_path.read_text(encoding="utf-8"))
metrics = data.get("metrics", {})
f1 = float(metrics.get("f1_score", 0.0))
recall = float(metrics.get("recall", 0.0))
precision = float(metrics.get("precision", 0.0))

errors = []
if f1 < min_f1:
    errors.append(f"f1_score={f1:.4f} < min_f1={min_f1:.4f}")
if recall < min_recall:
    errors.append(f"recall={recall:.4f} < min_recall={min_recall:.4f}")
if precision < min_precision:
    errors.append(f"precision={precision:.4f} < min_precision={min_precision:.4f}")

if errors:
    print("[!] Model promotion blocked by quality gates:")
    for item in errors:
        print("   -", item)
    sys.exit(5)

print("[+] Quality gates passed.")
PY

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RELEASE_DIR="${MODEL_DIR}/releases/${STAMP}"
mkdir -p "${RELEASE_DIR}"

cp -f "${MODEL_FILE}" "${RELEASE_DIR}/rf_model.pkl"
cp -f "${META_FILE}" "${RELEASE_DIR}/model_metadata.json"
cp -f "${MODEL_FILE}" "${DETECTION_MODEL_DIR}/rf_model.pkl"

ln -sfn "${RELEASE_DIR}" "${MODEL_DIR}/current"

echo "[+] Model promoted."
echo "    release_dir=${RELEASE_DIR}"
echo "    current_link=${MODEL_DIR}/current"
echo "    detection_engine_model=${DETECTION_MODEL_DIR}/rf_model.pkl"
