# DLDS AI Extension - Network Traffic Forensics

This extension enhances the DLDS SOC Dashboard by integrating a Machine Learning (ML) pipeline for intelligent Intrusion Detection and Anomaly analysis.

## AI Architecture

The AI module acts as an intelligent middleware between the Rule-based Correlation Engine and the Laravel Backend.

1. **Sensors**: Zeek, Suricata, Auditd generate logs.
2. **Shipper**: Filebeat tails normalized JSON logs with disk queue buffering.
3. **Streaming**: Kafka (replicated topics) decouples ingest from downstream consumers.
4. **Correlator + AI Engine (`ai_engine.py`)**: Classifies events and emits explainable evidence.
5. **Storage/Visualization**: Logstash -> Elasticsearch -> Kibana + Laravel dashboard.

## ML Workflow

- **Feature Extraction**: Extracts network + context features (ports, bytes, duration, severity, protocol flags, DNS/HTTP/TLS presence).
- **Algorithm**: Random Forest Classifier (Multi-class: Benign, Suspicious, Malicious), saved as model bundle.
- **Explainability**: Associates triggered features to plain-text evidence for SOC analysts.
- **Policy**: No automatic synthetic-data fallback in training.

## How to Train the Model

To train and generate `rf_model.pkl` from **real datasets**:

```bash
cd ml
source .venv/bin/activate
pip install pandas scikit-learn joblib
python train_model.py --dataset-file /path/to/dataset.csv
# or:
python train_model.py --dataset-dir /path/to/csv_directory
```
The model artifact is also mirrored to `detection-engine/models/rf_model.pkl` via:
```bash
cd detection-engine
python train_model.py --dataset-file /path/to/dataset.csv
```

## How to Run AI Detection

Start the main detection pipeline. By default, if no model is found, events are marked `unscored`.
For lab-only deterministic fallback, set:
```bash
export DLDS_ALLOW_MOCK_MODEL=true
```

```bash
cd detection-engine
python main.py
```

## Example Input / Output

**Input from Correlator:**
```json
{
  "type": "network",
  "src_ip": "10.0.0.5",
  "dst_port": 4444,
  "bytes_sent": 8500,
  "severity": "CRITICAL",
  "file_path": "/etc/shadow"
}
```

**Output after `AIEngine.predict()`:**
```json
{
  "ai_label": "malicious",
  "confidence": 0.95,
  "anomaly_score": 0.94,
  "ai_reason": "Model predicted Malicious due to high risk feature correlation.",
  "ai_evidence": [
    "High data transfer detected (8500 bytes)",
    "Base severity is critically high",
    "Sensitive file access involved"
  ],
  "model_version": "rf-v1.0"
}
```

## Attack Validation Scenarios

See [docs/AI_VALIDATION.md](docs/AI_VALIDATION.md) for detailed step-by-step attack simulation guides and expected ML dashboard outcomes.
For complete SOC-scale hardening, see [docs/PRODUCTION_GRADE_UPGRADE.md](docs/PRODUCTION_GRADE_UPGRADE.md).

## Streaming Setup (Kafka + ELK)

1. Start Kafka cluster:
```bash
docker compose --project-name dlds_kafka -f docker-compose.kafka.yml up -d
```
2. Create topics:
```bash
./scripts/create_kafka_topics.sh
```
3. Start ELK stack:
```bash
docker compose --project-name dlds_elk -f docker-compose.elk.yml up -d
```

`elk/filebeat/filebeat.yml` now ships events to Kafka (`dlds.raw.telemetry`) with disk queue buffering, and Logstash consumes from Kafka with persisted queue + deterministic `event_id` dedup.

## Operational Validation (Final 0.2)

Use [docs/OPERATIONS_VALIDATION.md](docs/OPERATIONS_VALIDATION.md) to execute:
- full live `tcpreplay` testing with JSON report output,
- CI/CD model retraining + promotion gates,
- hourly feature drift monitoring,
- Kubernetes deployment runbook,
- scheduled chaos drills.
