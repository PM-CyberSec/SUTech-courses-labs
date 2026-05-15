# 🚀 DLDS SOC Dashboard & AI-Driven Detection Engine

> AI-Powered Security Monitoring Platform | Built for Real-Time Detection & Forensics

---

## 📌 Overview

**DLDS (Digital Leak Detection System)** is an advanced SOC platform designed to transform raw system logs into actionable security intelligence.

It combines:
- 🔍 Network Monitoring (Zeek + Suricata)
- 🧠 AI-based Threat Detection (Random Forest)
- 🧩 LLM adapter foundation (local stub + OpenAI-compatible)
- 📚 Minimal local RAG with provenance-aware evidence
- 🧪 Deterministic AI evaluation and prompt version metadata
- 🔐 RBAC permission checks for protected actions
- 🧾 Request IDs, structured logs, and audit logging
- ⚡ Real-time Streaming (Filebeat + Kafka + ELK)
- 📊 Interactive SOC Dashboard (Laravel)

The system bridges low-level telemetry with high-level threat analysis.

---

## 🧠 Architecture & Data Flow

### 1. Detection Layer (Sensors)

| Tool | Role |
|-----|------|
| Zeek | Network metadata extraction |
| Suricata | IDS alerts & signatures |
| Auditd | Process & file monitoring |
| tshark | Packet analysis (offline PCAP) |

---

### 2. Ingestion & Streaming Layer

- Local normalized JSON append (durable on disk)
- Filebeat disk queue buffering
- Kafka replicated topics for decoupled fan-out
- Logstash persisted queue + DLQ

---

### 3. Processing Layer (Python Engine)

- Log ingestion (tailing)
- Correlation (network + process + file)
- Feature extraction
- AI classification
- Event normalization

---

### 4. AI Layer

- Model: Random Forest
- Features:
  - src_port / dst_port
  - bytes_sent
  - duration
  - severity
  - flags / protocol
- Output:
  - ai_label → benign / suspicious / malicious
  - confidence
  - reasoning (Explainable AI)

---

### 5. Backend (Laravel API)

- Receives JSON events
- Deduplicates via deterministic `event_id`
- Stores in DB
- Broadcasts via WebSockets
- Provides an LLM adapter interface for future AI workflows
- Injects retrieved local evidence into LLM prompts with provenance
- Evaluates RAG/LLM outputs for answer match, confidence, and source coverage
- Enforces role and permission gates for protected UI/API actions
- Emits request IDs, structured request logs, and audit records

---

### 6. Production Foundation

| Area | Implementation |
|------|----------------|
| LLM adapter | `App\Services\LLM\LLMAdapter` |
| Local test adapter | `LocalStubLLMAdapter` |
| OpenAI-compatible adapter | `OpenAICompatibleLLMAdapter` |
| Document ingestion | `DocumentIngestionService` |
| Local retriever | `LocalKeywordRetriever` |
| Provenance schema | `ProvenanceResponseSchema` |
| Evaluation | `LLMOutputEvaluator` |
| Prompt metadata | `PromptVersionMetadata` |
| RBAC roles | `admin`, `analyst`, `viewer` |
| Permission registry | `App\Security\PermissionRegistry` |
| Permission middleware | `permission:<permission-name>` |
| Request observability | `LogRequestMetrics` middleware |
| Audit log | `storage/logs/audit.log` |

---

### 7. Visualization Layer

- Laravel Dashboard
- Real-time updates (Reverb)
- ELK Stack (Kibana dashboards)

---

## 🛠️ Tech Stack

### 🐍 Backend & Detection
- Python 3
- Bash
- Zeek
- Suricata
- Auditd

### 🤖 Machine Learning
- scikit-learn
- pandas
- numpy
- joblib

### 🐘 Backend API
- Laravel 13
- PHP 8.3
- Eloquent ORM

### 🌐 Frontend
- Blade
- TailwindCSS v4
- Vanilla JavaScript
- Vite

### ⚡ Real-time
- Laravel Reverb
- Pusher.js
- Laravel Echo
- Filebeat
- Kafka (KRaft cluster)

### 📊 ELK Stack
- Elasticsearch
- Logstash
- Kibana

### 🐳 DevOps
- Docker
- Docker Compose

---

## 📦 Python Dependencies

```bash
scikit-learn
pandas
numpy
joblib
requests
python-dotenv
confluent-kafka
```

---

## 📦 PHP Dependencies (Composer)

```bash
laravel/framework
laravel/reverb
guzzlehttp/guzzle
```

---

## 📦 Node Dependencies

```bash
vite
tailwindcss
axios
laravel-echo
pusher-js
```

---

## 📂 Project Structure

```
DigitalForensics/
│
├── app/
│   ├── Http/Middleware/
│   │   ├── EnsureUserCanPerform.php
│   │   └── LogRequestMetrics.php
│   ├── Security/
│   │   ├── Permission.php
│   │   ├── PermissionRegistry.php
│   │   └── UserRole.php
│   └── Services/
│       ├── AuditLogger.php
│       ├── Evaluation/
│       │   ├── EvaluationCase.php
│       │   ├── EvaluationResult.php
│       │   ├── LLMOutputEvaluator.php
│       │   ├── PromptVersionMetadata.php
│       │   └── SampleEvaluations.php
│       ├── LLM/
│       │   ├── LLMAdapter.php
│       │   ├── LocalStubLLMAdapter.php
│       │   └── OpenAICompatibleLLMAdapter.php
│       └── RAG/
│           ├── DocumentIngestionService.php
│           ├── LocalKeywordRetriever.php
│           ├── ProvenanceEvidence.php
│           ├── ProvenanceResponseSchema.php
│           └── RetrieverInterface.php
│
├── config/
│   ├── llm.php
│   ├── rag.php
│   └── logging.php
│
├── detection-engine/
│   ├── main.py
│   ├── correlator.py
│   ├── ai_engine.py
│   ├── process_monitor.py
│   ├── zeek_stream.py
│   ├── suricata_stream.py
│   └── run_services.sh
│
├── pipeline/
│   ├── main.py
│   ├── inference.py
│
├── ml/
│   ├── train_model.py
│   └── models/
│
├── data/
│   ├── logs/
│   └── pcaps/
│
├── docker-compose.elk.yml
├── docker-compose.kafka.yml
├── ran.sh
```

---

## ⚙️ Installation

### 1. System Setup

```bash
sudo apt update
sudo apt install -y \
php php-cli php-xml php-mbstring php-curl php-zip php-sqlite3 \
composer nodejs npm \
python3 python3-pip python3-venv \
suricata zeek auditd audispd-plugins
```

---

### 2. Laravel Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate

npm run build
```

Key environment values:

```bash
APP_ENV=local
APP_DEBUG=true

DB_USERNAME=dlds_app
DB_PASSWORD=change-me

DLDS_API_KEY=change-me
DLDS_HMAC_SECRET=change-me-too
DLDS_INGEST_MAX_SKEW_SECONDS=300

LLM_DRIVER=local_stub
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-4.1-mini
OPENAI_TIMEOUT_SECONDS=30
OPENAI_RETRY_ATTEMPTS=2
OPENAI_RETRY_DELAY_MS=250

RAG_CHUNK_SIZE_WORDS=180
RAG_CHUNK_OVERLAP_WORDS=30

AUDIT_LOG_LEVEL=info
PUBLIC_REGISTRATION=false
```

Use `LLM_DRIVER=local_stub` for local development and tests. Use `LLM_DRIVER=openai` with `OPENAI_API_KEY` and optional `OPENAI_BASE_URL` for OpenAI-compatible providers.

For a safer local database, avoid passwordless MySQL root access:

```sql
CREATE DATABASE IF NOT EXISTS DigitalForensics;
CREATE USER IF NOT EXISTS 'dlds_app'@'127.0.0.1' IDENTIFIED BY 'change-me';
GRANT SELECT, INSERT, UPDATE, DELETE ON DigitalForensics.* TO 'dlds_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Full setup, rollback, and temporary migration grant notes are in [docs/MYSQL_APP_USER.md](docs/MYSQL_APP_USER.md).

Development may use localhost-only services. Production must place Laravel, MySQL, Elasticsearch, Kibana, Kafka UI, Kafka, Logstash, and Reverb behind private networking, firewall rules, TLS, and authentication. Do not expose the development compose ports directly to the internet.

---

### 3. Python Setup

```bash
cd detection-engine
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

---

### 4. Train AI Model (Real Data Required)

```bash
cd ml
python3 train_model.py --dataset-file /path/to/dataset.csv
# or:
python3 train_model.py --dataset-dir /path/to/csv_directory
```

---

### 5. Start Services

```bash
sudo DLDS_RUN_SERVICES=1 ./detection-engine/run_services.sh
```

---

## ▶️ Run System

### 🔥 One Command (Recommended)

```bash
./ran.sh
```

---

### 🧪 Manual

```bash
# Laravel
php artisan serve

# Reverb
php artisan reverb:start --host=127.0.0.1 --port=8080

# Python Engine
cd detection-engine
python3 main.py

# Kafka Streaming Layer
docker compose --project-name dlds_kafka -f docker-compose.kafka.yml up -d
./scripts/create_kafka_topics.sh
./scripts/create_kafka_topics.sh list

# ELK
docker compose --project-name dlds_elk -f docker-compose.elk.yml up -d

# Forensic Pipeline
export PYTHONPATH=$(pwd)
export PIPELINE_KAFKA_ENABLED=true
export PIPELINE_KAFKA_BOOTSTRAP_SERVERS=127.0.0.1:19092
python3 pipeline/main.py
```

Kafka bootstrap values:

| Caller | Bootstrap server |
|--------|------------------|
| Host shell / Python pipeline | `127.0.0.1:19092` |
| Containers on `dlds_streaming` | `kafka-1:29092` |
| Kafka CLI inside `dlds_kafka_1` | `kafka-1:29092` |

---

## 🤖 LLM Adapter Usage

Resolve the adapter from the Laravel container:

```php
use App\Services\LLM\LLMAdapter;
use App\Services\LLM\LLMRequest;

$response = app(LLMAdapter::class)->complete(
    LLMRequest::user(
        prompt: 'Summarize this suspicious event as JSON.',
        expectsJson: true,
    ),
);

$data = $response->requireData();
```

The local stub is deterministic and safe for tests. The OpenAI-compatible adapter uses chat completions, request timeout, retry attempts, and structured JSON response parsing.

---

## 📚 Local RAG Usage

Chunk documents and retrieve evidence in memory:

```php
use App\Services\LLM\LLMAdapter;
use App\Services\LLM\LLMRequest;
use App\Services\RAG\DocumentIngestionService;
use App\Services\RAG\LocalKeywordRetriever;
use App\Services\RAG\ProvenanceResponseSchema;

$chunks = app(DocumentIngestionService::class)->ingest(
    title: 'Suricata Runbook',
    path: 'docs/suricata.md',
    content: $markdown,
    metadata: ['owner' => 'soc'],
);

$evidence = (new LocalKeywordRetriever($chunks))->retrieve('tls exfiltration');

$response = app(LLMAdapter::class)->complete(
    LLMRequest::user(
        prompt: 'Explain the alert using retrieved evidence.',
        responseSchema: ProvenanceResponseSchema::schema(),
        evidence: $evidence,
    ),
);
```

Each evidence item includes `source_id`, `source_title`, `excerpt`, `confidence`, and `retrieved_at`. If retrieval returns no evidence, pass `evidence: []`; the request receives a low-confidence `missing-evidence` source so responses do not silently imply unsupported certainty.

---

## 🧪 AI Evaluation

Evaluate deterministic RAG/LLM outputs locally:

```php
use App\Services\Evaluation\EvaluationCase;
use App\Services\Evaluation\LLMOutputEvaluator;
use App\Services\Evaluation\PromptVersionMetadata;

$prompt = new PromptVersionMetadata(
    promptName: 'incident_summary',
    version: '1.0.0',
    description: 'Summarize incident evidence with provenance.',
    schemaVersion: '1.0',
    updatedAt: '2026-05-04T00:00:00Z',
);

$result = app(LLMOutputEvaluator::class)->evaluate(new EvaluationCase(
    expectedAnswer: 'TLS exfiltration',
    actualAnswer: 'The alert indicates TLS exfiltration.',
    confidence: 0.91,
    sources: [['source_id' => 'src_suricata']],
    expectedSourceIds: ['src_suricata'],
));
```

Run sample evaluations:

```bash
php artisan ai:evaluate
```

The evaluator checks expected answer coverage, confidence threshold, source coverage, and returns deterministic pass/fail reasons.

---

## 🔐 RBAC & Audit

Roles:

| Role | Intended permissions |
|------|----------------------|
| `admin` | All permissions |
| `analyst` | Dashboard/read access + LLM invocation |
| `viewer` | Dashboard/read access only |

Protected tool/action routes should use:

```php
Route::middleware(['auth', 'approved', 'permission:tool.execute'])
    ->post('/tools/run', ToolController::class);
```

Unauthorized permission checks return `403 Forbidden` for authenticated users and write audit records to `storage/logs/audit.log`. Every HTTP response includes `X-Request-ID`; an inbound `X-Request-ID` is preserved.

---

## 🌐 Access

* Dashboard → [http://127.0.0.1:8000](http://127.0.0.1:8000)
* Kibana → [http://127.0.0.1:5601](http://127.0.0.1:5601)
* Elasticsearch → [http://127.0.0.1:9200](http://127.0.0.1:9200)
* Kafka UI → [http://127.0.0.1:8085](http://127.0.0.1:8085)

The root compose files bind Elasticsearch, Kibana, Kafka UI, Kafka, and Logstash to `127.0.0.1` for development safety. If remote team access is needed, use a VPN, SSH tunnel, or reverse proxy with authentication rather than changing these bindings to `0.0.0.0`.

The root Compose files use explicit project names to avoid orphan warnings:

| Stack | Compose project |
|-------|-----------------|
| ELK | `dlds_elk` |
| Kafka | `dlds_kafka` |

---

## 📡 Reverb Troubleshooting

Expected local values:

```bash
BROADCAST_CONNECTION=reverb
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Verify Reverb and the dashboard:

```bash
npm run build
php artisan reverb:restart
php artisan reverb:start --host=127.0.0.1 --port=8080
```

Open the dashboard browser console and confirm either `Reverb connected to 127.0.0.1:8080` or the polling fallback warning. If the browser still disconnects, confirm `REVERB_ALLOWED_ORIGINS` contains the dashboard URL and that `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, and `VITE_REVERB_SCHEME` match the `.env` values consumed by Laravel.

A `404` from `curl http://127.0.0.1:8080` only proves the HTTP root is not a health endpoint; it does not validate the WebSocket handshake.

---

## Elasticsearch Dev Health

A single-node Elasticsearch development cluster is yellow when indices request replicas, because replicas cannot be assigned to the same node as their primary shard. For local DLDS development, set DLDS event indices to zero replicas:

```bash
curl -X PUT 'http://127.0.0.1:9200/dlds-events-*/_settings' \
  -H 'Content-Type: application/json' \
  --data '{"index":{"number_of_replicas":0}}'

curl -X PUT 'http://127.0.0.1:9200/_index_template/dlds-dev-single-node' \
  -H 'Content-Type: application/json' \
  --data '{"index_patterns":["dlds-events-*"],"priority":500,"template":{"settings":{"number_of_replicas":0}}}'
```

Verify:

```bash
curl http://127.0.0.1:9200/_cluster/health
curl 'http://127.0.0.1:9200/_cat/indices?h=index,health,pri,rep,docs.count'
```

Production clusters should use replicas across multiple data nodes instead of this dev-only setting.

---

## Final Health Check

Run the local hardening health check:

```bash
./scripts/dlds_health_check.sh
```

It checks Laravel routing, database migrations, public stats API, Reverb WebSocket origin handling, Kafka topics, Elasticsearch, Kibana, and deterministic AI evaluation.

---

## 📊 Database Schema

Table: `dlds_events`

* src_ip, dst_ip
* src_port, dst_port
* bytes_sent
* process_name
* file_path
* severity

The ingestion API maps canonical fields plus common Zeek/Suricata aliases such as `event_type`, `dest_ip`, `dest_port`, nested `source.ip`, `source.port`, `destination.address`, and `destination.p`. Events with missing normalized critical fields are accepted but logged with `event_type=dlds.ingest.data_quality` so bad sensor payloads can be corrected without dropping telemetry.

### AI Fields:

* ai_label
* confidence
* anomaly_score
* ai_reason
* model_version

---

## 🧪 Testing

Application tests:

```bash
composer test
```

Traffic simulation:

```bash
./detection-engine/traffic-generator.sh
# Optional realistic replay:
tcpreplay -i eth0 data/pcaps/attack_sample.pcap
```

---

## 🎯 Features

* Real-time detection ⚡
* AI classification 🤖
* Explainable decisions 🧠
* SOC dashboard 📊
* ELK visualization 📈
* Durable streaming with Kafka/Filebeat 🧵
* Drift-aware ML operations 📉
* Kubernetes-ready deployment ☸️
* Scheduled chaos resilience drills 🔁

---

## 🧭 Production Ops

- Architecture hardening plan: [docs/PRODUCTION_GRADE_UPGRADE.md](docs/PRODUCTION_GRADE_UPGRADE.md)
- Operational certification runbook: [docs/OPERATIONS_VALIDATION.md](docs/OPERATIONS_VALIDATION.md)
- Kubernetes deployment: [k8s/README.md](k8s/README.md)

---

## 📜 License

MIT License © 2026
