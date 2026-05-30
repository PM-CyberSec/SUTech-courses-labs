# AI Extension for DLDS SOC

## Overview
This module extends DLDS by adding AI-based intrusion detection.

## What it Adds
- Machine Learning classification
- Anomaly detection
- AI confidence scoring
- Advanced correlation

## Architecture

DLDS Engine
    ↓
AI Module
    ↓
Laravel API
    ↓
Dashboard

## How it Works
1. DLDS generates events
2. AI module processes them
3. Adds:
   - label
   - confidence
   - anomaly score
4. Sends enriched event

## Example Output
```json
{
  "ai_label": "malicious",
  "confidence": 0.92,
  "anomaly_score": 0.87
}
Run
python ai_engine.py
Integration

Modify:

correlator.py

Add:

ai_result = ai_model.predict(features)
event["ai"] = ai_result

---

# 🏗️ 3) التعديل المطلوب على مشروعك الحالي

## في `correlator.py` (مهم جدًا)

```python
features = extract_features(event)

ai_result = model.predict(features)

event["ai_label"] = ai_result["label"]
event["confidence"] = ai_result["confidence"]
📊 4) التعديل على Laravel

في Model:

protected $fillable = [
    ...
    'ai_label',
    'confidence',
    'anomaly_score'
];

في Dashboard:

<td>{{ $event->ai_label }}</td>
<td>{{ $event->confidence }}</td>
🚀 Execution Command
cd detection-engine
source .venv/bin/activate

export LARAVEL_API_URL="http://127.0.0.1:8000/api/dlds/events"

python main.py