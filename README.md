![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=Digital+Forensics+%7C+Threat+Detection+System;Laravel+%2B+Python+%E2%80%A2+Zeek+%E2%80%A2+Suricata)

# Digital Forensics — Threat Detection and Event Correlation System
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-DigitalForensics-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/DigitalForensics)

![Status](https://img.shields.io/badge/STATUS-COMPLETE-00FFCC?style=for-the-badge&logo=shield&logoColor=0052CC)
![Laravel](https://img.shields.io/badge/Laravel-0052CC?style=for-the-badge&logo=laravel&logoColor=white)
![Python](https://img.shields.io/badge/Python-001F4D?style=for-the-badge&logo=python&logoColor=00FFCC)

---

## Overview
Comprehensive digital forensics and threat detection platform featuring real-time event monitoring, network analysis, process tracking, and cross-source alert correlation.

* **Project:** Digital Lab Detection System (DLDS) v1.0
* **Framework:** Laravel + React
* **Detection Engine:** Python (Zeek, Suricata parsers)
* **Database:** MySQL
* **Architecture:** Agent-based monitoring with centralized correlation

---

## Technical Stack

{
  "💻 Backend": [
    ![PHP](https://img.shields.io/badge/PHP-001F4D?style=flat-square&logo=php&logoColor=00FFCC),
    ![Laravel](https://img.shields.io/badge/Laravel-001F4D?style=flat-square&logo=laravel&logoColor=00FFCC),
    ![Python](https://img.shields.io/badge/Python-001F4D?style=flat-square&logo=python&logoColor=00FFCC)
  ],
  "🛡️ Detection": [
    ![Zeek](https://img.shields.io/badge/Zeek-001F4D?style=flat-square&logoColor=00FFCC),
    ![Suricata](https://img.shields.io/badge/Suricata-001F4D?style=flat-square&logo=suricata&logoColor=00FFCC),
    ![Wireshark](https://img.shields.io/badge/Wireshark-001F4D?style=flat-square&logo=wireshark&logoColor=00FFCC)
  ],
  "⬢ Frontend": [
    ![JavaScript](https://img.shields.io/badge/JavaScript-001F4D?style=flat-square&logo=javascript&logoColor=00FFCC),
    ![React](https://img.shields.io/badge/React-001F4D?style=flat-square&logo=react&logoColor=00FFCC),
    ![Vite](https://img.shields.io/badge/Vite-001F4D?style=flat-square&logo=vite&logoColor=00FFCC)
  ]
}

---

## Architecture

* **Network Monitoring:** Zeek logs, Suricata alerts, packet capture analysis
* **Process Tracking:** Real-time process lifecycle monitoring, system calls, behavioral heuristics
* **Event Correlation:** Multi-source data correlation, threat scoring, alert prioritization
* **Alert Management:** Real-time alerts with severity levels and historical search

---

## System Modules

#### 🌐 Network Monitor
Parses Zeek and Suricata `eve.json` logs, detects anomalies, and maps network topology.

#### ⚙️ Process Monitor
Tracks process creation, termination, and suspicious behavior patterns in real time.

#### 🔔 Alert System
Manages security alerts with severity classification and multi-source correlation.

#### 📊 Event Ingestion
Ingests, stores, and indexes security events with query and analysis capabilities.

---

## Repository Structure

```
DigitalForensics/
├── app/                    # Laravel application
│   ├── Http/Controllers/  # API Controllers
│   ├── Models/            # Database models
│   └── Services/          # Business logic
├── detection-engine/      # Python detection system
│   ├── main.py            # Entry point
│   ├── correlator.py      # Event correlation
│   ├── parser_zeek.py     # Zeek log parser
│   ├── suricata_stream.py # Suricata parser
│   ├── process_monitor.py # Process tracking
│   └── rules.py           # Detection rules
├── database/              # Migrations & seeders
├── routes/                # API routes
└── resources/             # Frontend assets
```

---

## Installation and Execution

### Web Application
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Detection Engine
```bash
cd detection-engine
pip install -r requirements.txt
python main.py
```

### Start Services
```bash
bash detection-engine/run_services.sh
```

---

## Detection Rules

```python
# Network Anomalies
- Abnormal port usage
- Suspicious protocols
- Unusual traffic patterns

# Process Behavior
- Suspicious process spawning
- Privilege escalation attempts
- Process injection detection

# Event Correlation
- Multi-source threat detection
- Severity scoring
- Alert prioritization
```

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
