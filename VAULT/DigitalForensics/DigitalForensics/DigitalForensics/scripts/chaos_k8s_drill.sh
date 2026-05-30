#!/usr/bin/env bash
set -euo pipefail

NAMESPACE="${DLDS_NAMESPACE:-dlds-soc}"
TARGET_DEPLOYMENT="${DLDS_CHAOS_TARGET_DEPLOYMENT:-logstash}"
WAIT_TIMEOUT="${DLDS_CHAOS_WAIT_TIMEOUT:-240s}"
REPORT_FILE="${DLDS_CHAOS_REPORT_FILE:-data/output/chaos_report.json}"

if ! command -v kubectl >/dev/null 2>&1; then
  echo "[!] kubectl is required."
  exit 2
fi

mkdir -p "$(dirname "${REPORT_FILE}")"
STARTED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

echo "[*] Chaos drill: restarting deployment/${TARGET_DEPLOYMENT} in ns/${NAMESPACE}"
if ! kubectl -n "${NAMESPACE}" get deployment "${TARGET_DEPLOYMENT}" >/dev/null 2>&1; then
  echo "[!] Target deployment not found: ${TARGET_DEPLOYMENT}"
  exit 3
fi

kubectl -n "${NAMESPACE}" rollout restart "deployment/${TARGET_DEPLOYMENT}"
if kubectl -n "${NAMESPACE}" rollout status "deployment/${TARGET_DEPLOYMENT}" --timeout="${WAIT_TIMEOUT}"; then
  STATUS="passed"
  EXIT_CODE=0
else
  STATUS="failed"
  EXIT_CODE=1
fi

PODS_JSON="$(kubectl -n "${NAMESPACE}" get pods -o json)"
READY_COUNT="$(PODS_JSON="${PODS_JSON}" python3 -c 'import json, os; data = json.loads(os.environ.get("PODS_JSON", "{}")); items = data.get("items", []); ready = 0
for pod in items:
    statuses = pod.get("status", {}).get("containerStatuses", [])
    if statuses and all(s.get("ready", False) for s in statuses):
        ready += 1
print(ready)')"

cat > "${REPORT_FILE}" <<EOF
{
  "started_at": "${STARTED_AT}",
  "finished_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "namespace": "${NAMESPACE}",
  "target_deployment": "${TARGET_DEPLOYMENT}",
  "status": "${STATUS}",
  "ready_pods_after_drill": ${READY_COUNT}
}
EOF

echo "[*] Chaos report written to ${REPORT_FILE}"
exit "${EXIT_CODE}"
