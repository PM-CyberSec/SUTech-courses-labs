### **user@DigitalForensics:~$ whoami --focus "Digital Forensics" --project "DLDS_v1.0"**

# # SYSTEM_OVERRIDE: [DIGITAL_FORENSICS_LAB]

![Status](https://img.shields.io/badge/STATUS-OPERATIONAL-39FF14?style=for-the-badge&logo=shield)
![Stack](https://img.shields.io/badge/STACK-PHP_JS_Python-005571?style=for-the-badge&logo=php)
![Environment](https://img.shields.io/badge/ENV-Development-orange?style=for-the-badge&logo=linux&logoColor=white)

### > Welcome, friend.
### > You are accessing the Digital Forensics & Detection System.

---

## [ ⟁ ] LOG_01: THE OBJECTIVE
This project implements a comprehensive digital forensics and threat detection system. It includes real-time event monitoring, network analysis, process tracking, and alert correlation.

* $ **CODE_NAME=** DLDS (Digital Lab Detection System)
* $ **FRAMEWORK=** Laravel + React
* $ **DETECTION_ENGINE=** Python (Zeek, Suricata)
* $ **DATABASE=** MySQL
* $ **ARCHITECTURE=** Agent-Based Monitoring

---

## [ ⚙ ] THE TOOLKIT (DECRYPTED)
This project was built using modern cybersecurity technologies:

## 💻 Backend
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)

## 🛡️ Detection Tools
![Zeek](https://img.shields.io/badge/Zeek-0088CC?style=for-the-badge)
![Suricata](https://img.shields.io/badge/Suricata-E94F28?style=for-the-badge)![Wireshark](https://img.shields.io/badge/Wireshark-1679A7?style=for-the-badge&logo=wireshark&logoColor=white)
![Python Scripts](https://img.shields.io/badge/Python_Scripts-3776AB?style=for-the-badge&logo=python&logoColor=white)

## 📊 Frontend
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![React](https://img.shields.io/badge/React-61DAFB?style=for-the-badge&logo=react&logoColor=black)
![Vite](https://img.shields.io/badge/Vite-646CFF?style=for-the-badge&logo=vite&logoColor=white)

---

## [ 🛡️ ] LOG_02: DEFENSIVE ARCHITECTURE
The system is designed with multiple layers of security monitoring:

* **{**
* **"Network Monitoring":** [`Zeek Logs`, `Suricata Alerts`, `Packet Capture`],
* **"Process Tracking":** [`Real-time Process Monitor`, `System Calls`, `Process Heuristics`],
* **"Event Correlation":** [`Multi-source Data Correlation`, `Alert Prioritization`, `Threat Scoring`],
* **"Alert Management":** [`Real-time Alerts`, `Severity Levels`, `Event History`]
* **}**

---

## [ 💾 ] LOG_03: SYSTEM MODULES

#### 🌐 [NETWORK] > Network Monitor
> Monitors network traffic using Zeek and Suricata.
> Parses eve.json logs, detects anomalies, maps network topology.

#### ⚙️ [PROCESS] > Process Monitor
> Tracks system processes in real-time.
> Monitors process creation, termination, and suspicious behavior.

#### 🔔 [ALERTS] > Alert System
> Manages security alerts with severity levels.
> Correlates events from multiple sources.

#### 📊 [EVENTS] > Event Ingestion
> Ingests and stores security events.
> Provides query and analysis capabilities.

---

## [ 📊 ] LOG_04: PROJECT STRUCTURE

### Main Application (Laravel)
```
├── app/                    # Laravel application
│   ├── Http/Controllers/  # API Controllers
│   ├── Models/             # Database models
│   ├── Services/           # Business logic
│   └── Events/             # Event classes
├── routes/                 # API routes
├── resources/              # Frontend assets
├── database/               # Migrations
└── storage/                # Logs & cache
```

### Detection Engine (Python)
```
├── detection-engine/
│   ├── main.py             # Main entry point
│   ├── correlator.py       # Event correlation
│   ├── parser_zeek.py      # Zeek log parser
│   ├── suricata_stream.py  # Suricata parser
│   ├── process_monitor.py # Process tracking
│   ├── net_mapper.py       # Network mapping
│   └── rules.py            # Detection rules
```

---

## [ ⚙ ] LOG_05: PROJECT FILES

### Core Files
| File | Description |
|------|-------------|
| `artisan` | Laravel CLI |
| `composer.json` | PHP dependencies |
| `package.json` | JS dependencies |
| `.env.example` | Environment template |

### Detection Engine
| File | Description |
|------|-------------|
| `main.py` | Main detection script |
| `correlator.py` | Event correlation engine |
| `parser_zeek.py` | Zeek log parser |
| `rules.py` | Detection rules |

### Configuration
| File | Description |
|------|-------------|
| `composer.lock` | PHP lock file |
| `package-lock.json` | JS lock file |
| `phpunit.xml` | Test configuration |

---

## [ 🔍 ] LOG_06: DETECTION RULES

The system includes detection rules for:

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

## [ ⌬ ] SYSTEM_ACCESS

* **Step 01 ->** `git clone <repo-url>`
* **Step 02 ->** `composer install` (PHP dependencies)
* **Step 03 ->** `npm install` (JS dependencies)
* **Step 04 ->** Copy `.env.example` to `.env`
* **Step 05 ->** `php artisan migrate` (Database setup)
* **Step 06 ->** `php artisan serve` (Start web server)

### Detection Engine Setup
```bash
cd detection-engine
pip install -r requirements.txt
python main.py
```

### Start Services
```bash
# Start Zeek
bash run_services.sh

# Start Detection Engine
python main.py
```

---

## [ ✉ ] TRANSMIT_DATA
> **The truth is in the logs. Analyze the evidence.**

* **LinkedIn:** [LinkedIn](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Terminal:** [GitHub Portfolio](https://github.com/PM-CyberSec)
* **Encrypted Mail:** paulamagedcyber@gmail.com

---

### > [EOF]
