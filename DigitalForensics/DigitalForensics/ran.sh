#!/usr/bin/env bash
set -e

PROJECT_DIR="$HOME/Downloads/DigitalForensics"
DETECTION_DIR="$PROJECT_DIR/detection-engine"
ML_DIR="$PROJECT_DIR/ml"

echo "==============================================="
echo "🚀 Starting DLDS AI System (Full Stack)"
echo "==============================================="

# -------------------------------
# 1) Start XAMPP
# -------------------------------
echo "[1/10] 🛠️ Starting XAMPP..."
sudo /opt/lampp/lampp start || echo "⚠️ XAMPP failed. Continuing..."

# -------------------------------
# 2) Setup Python Environment
# -------------------------------
echo "[2/10] 🐍 Preparing Python Environment..."
cd "$PROJECT_DIR"

if [ ! -d "$ML_DIR/.venv" ]; then
    echo "Creating ML virtual environment..."
    python3 -m venv "$ML_DIR/.venv"
fi

source "$ML_DIR/.venv/bin/activate"

echo "Installing Python dependencies..."
pip install --upgrade pip

if [ -f "$DETECTION_DIR/requirements.txt" ]; then
    pip install -r "$DETECTION_DIR/requirements.txt" || true
fi

pip install requests python-dotenv scikit-learn pandas numpy joblib

# -------------------------------
# 3) Train Model
# -------------------------------
echo "[3/10] 🤖 Checking AI Model..."
mkdir -p "$ML_DIR/models"

if [ ! -f "$ML_DIR/models/rf_model.pkl" ]; then
    if [ -n "${DLDS_TRAIN_DATASET_FILE:-}" ] || [ -n "${DLDS_TRAIN_DATASET_DIR:-}" ]; then
        echo "Training AI model from real dataset..."
        cd "$ML_DIR"
        python3 train_model.py \
          ${DLDS_TRAIN_DATASET_FILE:+--dataset-file "$DLDS_TRAIN_DATASET_FILE"} \
          ${DLDS_TRAIN_DATASET_DIR:+--dataset-dir "$DLDS_TRAIN_DATASET_DIR"}
        cp -f "$ML_DIR/models/rf_model.pkl" "$DETECTION_DIR/models/rf_model.pkl" || true
    else
        echo "⚠️ Model missing and no real dataset configured."
        echo "   Set DLDS_TRAIN_DATASET_FILE or DLDS_TRAIN_DATASET_DIR to enable training."
        echo "   The engine will run in unscored mode unless DLDS_ALLOW_MOCK_MODEL=true."
    fi
else
    echo "Model already exists ✔"
fi

# -------------------------------
# 4) Bootstrap Detection Services
# -------------------------------
echo "[4/10] 🛡️ Starting Zeek / Suricata / Auditd..."
cd "$DETECTION_DIR"

if [ -f "./run_services.sh" ]; then
    sudo DLDS_RUN_SERVICES=1 ./run_services.sh || echo "⚠️ Detection service bootstrap failed. Continuing..."
else
    echo "⚠️ run_services.sh not found. Skipping..."
fi

# -------------------------------
# 5) Setup Forensic Directories
# -------------------------------
echo "[5/10] 📁 Preparing Wireshark & Forensic Directories..."
mkdir -p "$PROJECT_DIR/data/pcaps"
mkdir -p "$PROJECT_DIR/data/pcaps/processed"
mkdir -p "$PROJECT_DIR/data/pcaps/reports"
mkdir -p "$PROJECT_DIR/data/logs"
mkdir -p "$PROJECT_DIR/data/logs/zeek_offline"
mkdir -p "$PROJECT_DIR/data/logs/suricata_offline"
mkdir -p "$PROJECT_DIR/data/output"

# -------------------------------
# 6) Start Kafka Streaming Layer
# -------------------------------
echo "[6/10] 🧵 Starting Kafka Streaming Layer..."
cd "$PROJECT_DIR"

if [ -f "$PROJECT_DIR/docker-compose.kafka.yml" ]; then
    if ! docker network inspect dlds_streaming >/dev/null 2>&1; then
        docker network create dlds_streaming >/dev/null 2>&1 || sudo docker network create dlds_streaming >/dev/null
    fi
    if ! docker volume inspect digitalforensics_kafka1_data >/dev/null 2>&1; then
        docker volume create digitalforensics_kafka1_data >/dev/null 2>&1 || sudo docker volume create digitalforensics_kafka1_data >/dev/null
    fi

    if docker info >/dev/null 2>&1; then
        docker compose --project-name dlds_kafka -f docker-compose.kafka.yml up -d || echo "⚠️ Kafka cluster failed. Continuing..."
    else
        sudo docker compose --project-name dlds_kafka -f docker-compose.kafka.yml up -d || echo "⚠️ Kafka cluster failed. Continuing..."
    fi

    if [ -f "$PROJECT_DIR/scripts/create_kafka_topics.sh" ]; then
        chmod +x "$PROJECT_DIR/scripts/create_kafka_topics.sh"
        "$PROJECT_DIR/scripts/create_kafka_topics.sh" || echo "⚠️ Topic provisioning failed. Continuing..."
    fi
else
    echo "⚠️ docker-compose.kafka.yml not found. Streaming durability will be limited."
fi

# -------------------------------
# 7) Start ELK Stack
# -------------------------------
echo "[7/10] 🐳 Starting ELK Stack (Logstash, Elasticsearch, Kibana)..."
cd "$PROJECT_DIR"

if [ -f "$PROJECT_DIR/docker-compose.elk.yml" ]; then
    if ! docker network inspect dlds_streaming >/dev/null 2>&1; then
        docker network create dlds_streaming >/dev/null 2>&1 || sudo docker network create dlds_streaming >/dev/null
    fi
    if ! docker volume inspect digitalforensics_esdata >/dev/null 2>&1; then
        docker volume create digitalforensics_esdata >/dev/null 2>&1 || sudo docker volume create digitalforensics_esdata >/dev/null
    fi

    if docker info >/dev/null 2>&1; then
        if docker compose version >/dev/null 2>&1; then
            docker compose --project-name dlds_elk -f docker-compose.elk.yml up -d || echo "⚠️ ELK failed. Continuing..."
        elif command -v docker-compose >/dev/null 2>&1; then
            docker-compose --project-name dlds_elk -f docker-compose.elk.yml up -d || echo "⚠️ ELK failed. Continuing..."
        else
            echo "⚠️ Docker Compose not installed. Skipping ELK..."
        fi
    else
        if sudo docker compose version >/dev/null 2>&1; then
            sudo docker compose --project-name dlds_elk -f docker-compose.elk.yml up -d || echo "⚠️ ELK failed. Continuing..."
        elif command -v docker-compose >/dev/null 2>&1; then
            sudo docker-compose --project-name dlds_elk -f docker-compose.elk.yml up -d || echo "⚠️ ELK failed. Continuing..."
        else
            echo "⚠️ Docker Compose not installed. Skipping ELK..."
        fi
    fi
else
    echo "⚠️ docker-compose.elk.yml not found. Skipping ELK..."
fi

# -------------------------------
# 8) Laravel Setup
# -------------------------------
echo "[8/10] 🧹 Preparing Laravel..."
cd "$PROJECT_DIR"

php artisan optimize:clear || true
php artisan config:clear || true

echo "Running migrations..."
php artisan migrate --force || echo "⚠️ Migration failed. Continuing..."

# -------------------------------
# 9) Frontend Build
# -------------------------------
echo "[9/10] 📦 Building Frontend..."
npm install
npm run build

# -------------------------------
# 10) Launch Services
# -------------------------------
echo "[10/10] 🖥️ Launching Services..."

# Python Engine Laravel Link
x-terminal-emulator -T "DLDS Python Engine" -e bash -c "
cd '$DETECTION_DIR'
source '$ML_DIR/.venv/bin/activate'
export LARAVEL_API_URL='http://127.0.0.1:8000/api/dlds/events'
echo -e '\e[1;34mStarting Python Event Ingestor...\e[0m'
python3 main.py
exec bash
" &

# Forensic Pipeline ELK Link
x-terminal-emulator -T "DLDS Forensic Pipeline" -e bash -c "
cd '$PROJECT_DIR'
source '$ML_DIR/.venv/bin/activate'
if [ -f '$PROJECT_DIR/.env' ]; then
    set -a
    source '$PROJECT_DIR/.env'
    set +a
fi
export PYTHONPATH='$PROJECT_DIR'
export PIPELINE_KAFKA_ENABLED='true'
export PIPELINE_KAFKA_BOOTSTRAP_SERVERS='127.0.0.1:19092'
export PIPELINE_KAFKA_TOPIC='dlds.enriched'
echo -e '\e[1;36mStarting Real-Time Forensic Pipeline (ELK & Local JSON)...\e[0m'
python3 pipeline/main.py
exec bash
" &

# Laravel Server
x-terminal-emulator -T "DLDS Laravel Server" -e bash -c "
cd '$PROJECT_DIR'
if curl -fsS --max-time 2 'http://127.0.0.1:8000/up' >/dev/null 2>&1; then
    echo -e '\e[1;33mLaravel API Server already running on 127.0.0.1:8000. Skipping start.\e[0m'
elif ss -H -ltn '( sport = :8000 )' 2>/dev/null | grep -q LISTEN; then
    echo -e '\e[1;31mPort 8000 is busy, but Laravel health check is not responding.\e[0m'
    echo -e '\e[1;31mStop the process using port 8000, then run ./ran.sh again.\e[0m'
else
    echo -e '\e[1;31mStarting Laravel API Server...\e[0m'
    php artisan serve --host=127.0.0.1 --port=8000
fi
exec bash
" &

# Reverb Server
x-terminal-emulator -T "DLDS Reverb Server" -e bash -c "
cd '$PROJECT_DIR'
if ss -H -ltn '( sport = :8080 )' 2>/dev/null | grep -q LISTEN; then
    echo -e '\e[1;33mReverb Server already running on port 8080. Skipping start.\e[0m'
else
    php artisan reverb:restart || true
    echo -e '\e[1;32mStarting Laravel Reverb...\e[0m'
    php artisan reverb:start --host=127.0.0.1 --port=8080
fi
exec bash
" &

echo "==============================================="
if [ -f "$PROJECT_DIR/scripts/dlds_health_check.sh" ]; then
    echo "🧪 Running DLDS health check..."
    bash "$PROJECT_DIR/scripts/dlds_health_check.sh" || echo "⚠️ Health check reported issues."
fi
echo "✅ DLDS AI System is READY!"
echo "==============================================="
echo "Laravel:       http://127.0.0.1:8000"
echo "Kibana:        http://127.0.0.1:5601"
echo "Elasticsearch: http://127.0.0.1:9200"
echo "Kafka UI:      http://127.0.0.1:8085"
echo "==============================================="
