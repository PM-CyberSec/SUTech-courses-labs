# AI Agent Skills — DLDS Extension

## Skill: Feature Extraction
Extract features from DLDS events.

Inputs:
- Zeek logs
- Suricata alerts

Outputs:
- Feature vector

Steps:
1. Parse logs
2. Normalize values
3. Encode protocol and ports

---

## Skill: ML Classification

Inputs:
- Feature vector
- Model

Outputs:
- label
- confidence

Steps:
1. Load model
2. Predict
3. Return result

---

## Skill: Anomaly Detection

Inputs:
- Traffic features

Outputs:
- anomaly_score

Steps:
1. Apply Isolation Forest
2. Normalize score

---

## Skill: Correlation

Inputs:
- ML result
- Suricata alert
- process log

Outputs:
- final alert

Steps:
1. Merge evidence
2. Assign severity

---

## Skill: Laravel Integration

Inputs:
- AI alert

Outputs:
- Stored DB event

Steps:
1. POST to API
2. Validate response
