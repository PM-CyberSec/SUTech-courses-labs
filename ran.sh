#!/usr/bin/env bash

# Exit immediately if a command exits with a non-zero status
set -e

echo "==============================================="
echo "🚀 Starting DLDS Project (Full Stack) in Separate Windows"
echo "==============================================="

echo "[1/5] 🛠️ Starting XAMPP Server..."
sudo /opt/lampp/lampp start || echo "⚠️  Failed to start XAMPP. Make sure it is installed!"

echo "[2/5] 🛡️ Bootstrapping Detection Engine Services (Zeek, Suricata, Auditd)..."
cd ~/Downloads/DigitalForensics/detection-engine
sudo DLDS_RUN_SERVICES=1 ./run_services.sh

echo "[3/5] 🧹 Clearing Laravel Caches & Optimizing..."
cd ~/Downloads/DigitalForensics
php artisan optimize:clear
php artisan config:clear

echo "[4/4] 📦 Building Frontend Assets (Fixing Styles)..."
npm install
npm run build

echo "[5/5] 🖥️ Launching Services in Separate Terminals..."

# 1. Python Detection Engine
echo " -> Launching Python Engine..."
x-terminal-emulator -T "DLDS Python Engine" -e bash -c 'cd ~/Downloads/DigitalForensics/detection-engine && export LARAVEL_API_URL="http://127.0.0.1:8000/api/dlds/events" && [ -f ".venv/bin/activate" ] && source .venv/bin/activate; echo -e "\e[1;34mStarting Python Event Ingestor...\e[0m" && python3 main.py; exec bash' &

# 2. Laravel Backend Server
echo " -> Launching Laravel API Server..."
x-terminal-emulator -T "DLDS Laravel Server" -e bash -c 'cd ~/Downloads/DigitalForensics && echo -e "\e[1;31mStarting Laravel API Server...\e[0m" && php artisan serve; exec bash' &

echo "==============================================="
echo "✅ All services successfully launched!"
echo "==============================================="
