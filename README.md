# 🚀 DLDS SOC Dashboard

> ✨ Powered by Antigravity | Built with Precision & Intelligence

---

## 📌 Overview

The **Digital Leak Detection System (DLDS) SOC Dashboard** is a state-of-the-art, high-performance security monitoring platform. It provides a unified view of network telemetry, process monitoring, and intrusion alerts.

The platform consists of a **Python-based Detection Engine** that tails raw system logs, and a **Laravel 13 Web Dashboard** that provides real-time visualization, advanced filtering, and instant threat triage capabilities.

---

## 🧠 System Architecture & Data Flow

The project is divided into two main architectural components that work seamlessly together:

### 1. 🐍 Python Detection Engine (`/detection-engine`)
This engine acts as the log forwarder and correlator. It runs as a daemon on the host machine and continuously monitors three primary security sensors:
- **Suricata (`/var/log/suricata/eve.json`):** Extracts IDS/IPS alerts and signature matches.
- **Zeek (`/opt/zeek/logs/current/conn.log`):** Extracts raw network connection metadata.
- **Auditd (`/var/log/audit/audit.log`):** Tracks process executions and sensitive file access.

**Correlation:** The engine cross-references network connections with active PIDs and file interactions to detect complex threats like **Data Exfiltration**. 
**Ingestion:** Events are normalized into structured JSON payloads and pushed via `POST` requests to the Laravel API, bypassing duplication using a unique `event_hash`.

### 2. 🐘 Laravel REST API & SOC UI (`/app`, `/resources`)
- **Backend API:** Receives events, validates them, and stores them in the `dlds_events` relational database table. Complex querying is handled by `DldsEventQueryService.php`.
- **Real-Time Sync:** Utilizes **Laravel Reverb**, **Pusher.js**, and **Laravel Echo** to broadcast events instantly. As a fallback, a smart **Auto-Refresh Polling System** runs every 4 seconds.
- **Dynamic Frontend:** Built with **Blade**, **Tailwind CSS v4**, and **Vanilla JS**. Features include a debounced live search, sortable tables, responsive grid layouts, and interactive UI states.

---

## 🛠️ Tech Stack

- **Detection Engine:** Python 3, Bash, Zeek, Suricata, Auditd
- **Backend Framework:** Laravel 13, PHP 8.3, Eloquent ORM
- **Database:** MySQL / SQLite
- **Real-time WebSockets:** Laravel Reverb
- **Frontend:** Blade Templating, TailwindCSS, Particles.js, Vite

---

## 📂 Project Structure & Deep Dive

The project is split into distinct layers. Here is an exact breakdown of every critical file and its role in the system:

### 🐍 1. Python Detection Engine (`/detection-engine/`)
The background daemon responsible for parsing raw logs and identifying threats.

- **`main.py`**: The entry point. Initializes the streams, correlator, and manages the lifecycle of the detection threads.
- **`correlator.py`**: The core "brain". It aggregates parsed events, checks for data exfiltration (network + file access), normalizes the payload, and sends it to the Laravel API.
- **`suricata_stream.py`**: Continuously tails `/var/log/suricata/eve.json` to extract IDS alerts, DNS lookups, and flow data.
- **`zeek_stream.py`**: Tails `/opt/zeek/logs/current/conn.log` to provide deep connection state visibility.
- **`process_monitor.py`**: Monitors `/var/log/audit/audit.log` to track new process executions and sensitive file reads.
- **`net_mapper.py`**: Maps active network sockets directly to Linux PIDs to attribute traffic to specific processes.
- **`config.py`**: Manages environment variables (e.g., `LARAVEL_API_URL`, tolerances) and API session persistence.
- **`run_services.sh`**: The bootstrap script. Auto-detects log paths, configures interfaces, and starts Zeek/Suricata/Auditd.
- **`traffic-generator.sh`**: A simulation tool to generate fake malicious traffic for testing the SOC dashboard.

### 🐘 2. Laravel Backend API (`/app/`)
The PHP application that stores, filters, and broadcasts the telemetry.

- **`app/Http/Controllers/Api/`**:
  - **`EventController.php`**: Handles `POST` ingestion from Python and `GET` requests for the main event stream.
  - **`AlertController.php`, `NetworkController.php`, `ProcessController.php`**: Specialized controllers for specific dashboard views.
- **`app/Services/`**:
  - **`EventIngestionService.php`**: Validates JSON payloads, calculates an `event_hash` for exact deduplication, and writes to MySQL.
  - **`DldsEventQueryService.php`**: The querying powerhouse. Handles dynamic sorting, severity filtering, and optimized database queries.
- **`app/Models/DldsEvent.php`**: The primary Eloquent ORM model for events. It includes complex accessors to resolve related lookup data.
- **`app/Events/AlertCreated.php`**: Implements `ShouldBroadcast`. Triggered upon ingestion to push real-time data to Reverb WebSockets.

### 🎨 3. Frontend Dashboard (`/resources/`)
The modern SOC analyst interface built with Blade, JS, and CSS.

- **`resources/views/pages/`**:
  - **`dashboard.blade.php`**: The primary overview page featuring severity statistics and a live real-time event feed.
  - **`events.blade.php`, `alerts.blade.php`**: Dedicated paginated tables with dynamic sorting and debounced search fields.
- **`resources/views/layouts/`**:
  - **`master.blade.php`**: The global HTML layout. Includes the sidebar, navbar, and the `particles.js` animated background.
- **`resources/js/app.js`**: 
  - The core client-side intelligence. It manages the **Live Sync Polling Loop** (`dlds:auto-refresh`), maintains state in `localStorage`, initializes Laravel Echo, and formats UI timestamps.
- **`resources/css/app.css`**: 
  - Contains all the UI styling. Implements a responsive CSS Grid (`.layout`), the collapsible sidebar logic (`:has(.sidebar.collapsed)`), and the visual threat-severity badges (Low, Medium, High, Critical).

---

## ⚙️ Detailed Installation Guide

### Step 0: Install System Prerequisites (Kali/Debian)
Before running the application, ensure all core monitoring tools and languages are installed.

```bash
# Update package repositories
sudo apt update

# 1. Install Laravel & UI dependencies (PHP, Composer, Node.js, NPM)
sudo apt install -y php php-cli php-xml php-mbstring php-curl php-zip php-sqlite3 composer nodejs npm

# 2. Install Python Environment
sudo apt install -y python3 python3-pip python3-venv

# 3. Install Security Sensors (Zeek, Suricata, Auditd)
sudo apt install -y suricata zeek auditd audispd-plugins

# 4. Enable Auditd Service
sudo systemctl enable --now auditd
```

### Step 1: Backend Setup (Laravel)
```bash
# Clone the repository
git clone https://github.com/your-repo/DigitalForensics.git
cd DigitalForensics

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Set up the SQLite/MySQL database (Update .env if needed)
php artisan migrate
```

### Step 2: Frontend Setup (Vite & Tailwind)
```bash
# Install Node modules
npm install

# Compile assets (Crucial for JavaScript Auto-Refresh and CSS grids)
npm run build
```

### Step 3: Detection Engine Setup
The detection engine requires root privileges to configure network interfaces and read system logs.

```bash
cd detection-engine

# 1. Create a Python Virtual Environment & Install requirements
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

# 2. Update Zeek Configuration (If using WiFi instead of eth0)
sudo sed -i 's/interface=eth0/interface=wlan0/g' /opt/zeek/etc/node.cfg

# 3. Bootstrap Services (Deploys Zeek and Suricata configs)
sudo DLDS_RUN_SERVICES=1 ./run_services.sh
```

---

## ▶️ Usage & Execution

### 🚀 Automated Execution (Recommended)
You can launch the entire project automatically using the provided magic script. This script will bootstrap Zeek/Suricata, compile the UI assets, and launch the Laravel Backend and Python Engine in separate terminal windows for easy monitoring:
```bash
cd DigitalForensics
./ran.sh
```

### 🛠️ Manual Execution

**1. Compile the UI Styles (If not using `ran.sh`)**
```bash
npm run build
```

**2. Start the Laravel Server**
In the root directory of the project, start the PHP server:
```bash
php artisan serve
```

**3. Start the Live Feed Ingestion**
In a new terminal window, run the Python engine to start capturing network and process traffic, forwarding it to the API:
```bash
cd detection-engine
source .venv/bin/activate
export LARAVEL_API_URL="http://127.0.0.1:8000/api/dlds/events"
python3 main.py
```

### 🖥️ Access the Dashboard
Open your browser and navigate to `http://127.0.0.1:8000`.
- Click the **Live Sync: OFF** button in the top navigation bar to turn it **ON**.
- The status pill will change to **Connecting live feed** (Green dot).
- Watch as network traffic, process logs, and alerts stream directly into the dashboard in real-time.

---

## 🗃️ Database Schema (`dlds_events`)

The core repository for all telemetry is the `dlds_events` table, which includes:
- `id` (Primary Key)
- `event_time` (UTC Timestamp)
- `type` / `event_type_id` (Alert, Network, Process)
- `severity` (Low, Medium, High, Critical)
- `src_ip`, `dst_ip`, `src_port`, `dst_port`, `bytes_sent`
- `pid`, `process_name`, `file_path`
- `description`
- `event_hash` (Unique hash to prevent duplicate ingestion)

---

## 📡 API Endpoints

All endpoints are prefixed with `/api/dlds/`.

- `POST /events` - Ingest a new normalized event.
- `GET /events` - Retrieve paginated events. Accepts query params: `search`, `type`, `severity`, `sort_by`, `sort_dir`, `page`.
- `GET /alerts` - Retrieve events strictly categorized as security alerts.
- `GET /network` - Retrieve Zeek connection telemetry.
- `GET /processes` - Retrieve Auditd process activity.

---

## 🤝 Contribution

Contributions are welcome! 🎉

1. Fork the repo
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📜 License

MIT License © 2026

---

## 💡 Credits

Built with ❤️ using Antigravity & Precision Engineering principles.
