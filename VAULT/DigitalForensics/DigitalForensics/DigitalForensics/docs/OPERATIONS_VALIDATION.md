# DLDS Operational Validation Runbook

This runbook covers the final operational 0.2 required for full production certification.

## 1) Full Live tcpreplay Testing

### Command
```bash
python3 scripts/live_tcpreplay_validation.py \
  --iface eth0 \
  --scenario-file simulation/tcpreplay_scenarios.json \
  --report-file data/output/live_tcpreplay_report.json
# Add --no-sudo if tcpreplay already has required capabilities on host.
```

`simulation/tcpreplay_scenarios.json` ships with smoke-test scenarios over `sample_normal.pcapng`.
Replace entries with real attack PCAPs (recon, exploit, C2, DDoS) before formal SOC certification.

### Pass Criteria
- All scenarios return `status: passed`.
- `es_ingested_delta > 0`.
- No pipeline crash during replay.

### Output
- `data/output/live_tcpreplay_report.json`

## 2) CI/CD Retraining Pipeline

### Trigger
- GitHub Actions: `.github/workflows/model-retrain.yml`
- Manual or weekly schedule.

### Required Environment
- `DLDS_TRAIN_DATASET_DIR` or `DLDS_TRAIN_DATASET_FILE`
- Optional: `DATASET_ARCHIVE_URL` secret for secure dataset pull.

### Quality Gates
- F1 >= 0.90
- Recall >= 0.88
- Precision >= 0.88

If any gate fails, model promotion is blocked.

## 3) Drift Monitoring

### Local Command
```bash
python3 ml/drift_monitor.py \
  --baseline data/datasets/baseline.csv \
  --current data/logs/audit_events.json \
  --report data/output/drift_report.json \
  --min-samples 500
```

### CI Schedule
- `.github/workflows/drift-monitor.yml` runs hourly.

### Alert Rule
- Any feature drift with `level=high` triggers failure.

## 4) Kubernetes Deployment

### Deploy
```bash
kubectl apply -k k8s/base
kubectl apply -k k8s/overlays/production
```

### Validate
```bash
kubectl -n dlds-soc get deploy,pods,hpa,cronjobs
kubectl -n dlds-soc get pvc
```

## 5) Scheduled Chaos Testing

### Local Drill
```bash
./scripts/chaos_k8s_drill.sh
```

### Scheduled Drill
- GitHub Actions: `.github/workflows/chaos-drill.yml` (weekly)
- Kubernetes CronJob: `dlds-weekly-chaos-drill` (weekly)

### Pass Criteria
- `rollout status` succeeds within timeout.
- Pods recover to ready state.
- Ingestion resumes with no net event loss after recovery window.

## Final Certification Checklist

- [ ] Live replay report available and green
- [ ] Retrain pipeline passed latest quality gates
- [ ] Drift monitor clean (or investigated and accepted)
- [ ] K8s deployment healthy with HPA active
- [ ] Latest chaos drill passed and report archived
