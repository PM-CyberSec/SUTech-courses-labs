#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() { printf '[dlds-services] %s\n' "$*"; }
warn() { printf '[dlds-services][warn] %s\n' "$*" >&2; }

SUDO=()
if [[ "$(id -u)" -ne 0 ]]; then
  if command -v sudo >/dev/null 2>&1 && sudo -n true >/dev/null 2>&1; then
    SUDO=(sudo -n)
  fi
fi

run_root() {
  if [[ "$(id -u)" -eq 0 ]]; then
    "$@"
    return
  fi

  if [[ "${#SUDO[@]}" -gt 0 ]]; then
    "${SUDO[@]}" "$@"
    return
  fi

  return 1
}

can_manage_services() {
  if ! command -v systemctl >/dev/null 2>&1; then
    warn "systemctl is not available on this host"
    return 1
  fi

  if [[ "$(id -u)" -eq 0 || "${#SUDO[@]}" -gt 0 ]]; then
    return 0
  fi

  warn "No root privileges or non-interactive sudo available"
  return 1
}

start_service() {
  local service_name="$1"

  if ! can_manage_services; then
    warn "Skipping service '$service_name'"
    return 0
  fi

  run_root systemctl enable "$service_name" >/dev/null 2>&1 || true
  run_root systemctl restart "$service_name" >/dev/null 2>&1 || true

  if run_root systemctl is-active --quiet "$service_name"; then
    log "Service '$service_name' is active"
  else
    warn "Service '$service_name' is not active"
  fi
}

find_zeekctl() {
  if [[ -n "${ZEEKCTL_BIN:-}" ]]; then
    printf '%s\n' "$ZEEKCTL_BIN"
    return
  fi

  if command -v zeekctl >/dev/null 2>&1; then
    command -v zeekctl
    return
  fi

  for candidate in /opt/zeek/bin/zeekctl /usr/local/zeek/bin/zeekctl; do
    if [[ -x "$candidate" ]]; then
      printf '%s\n' "$candidate"
      return
    fi
  done

  printf '\n'
}

deploy_zeek() {
  local zeekctl_bin
  zeekctl_bin="$(find_zeekctl)"

  if [[ -z "$zeekctl_bin" ]]; then
    warn "zeekctl was not found. Set ZEEKCTL_BIN if installed in a custom location."
    return 0
  fi

  if [[ "$(id -u)" -eq 0 ]]; then
    "$zeekctl_bin" deploy >/dev/null 2>&1 || warn "zeekctl deploy returned non-zero"
    return 0
  fi

  if [[ "${#SUDO[@]}" -gt 0 ]]; then
    "${SUDO[@]}" "$zeekctl_bin" deploy >/dev/null 2>&1 || warn "zeekctl deploy returned non-zero"
    return 0
  fi

  warn "Skipping zeekctl deploy (requires root or non-interactive sudo)"
}

report_log_paths() {
  local suricata_path="${SURICATA_EVE_PATH:-${SURICATA_EVE:-/var/log/suricata/eve.json}}"
  local zeek_path="${ZEEK_CONN_LOG:-}"

  if [[ -z "$zeek_path" && -n "${ZEEK_LOG_DIR:-}" ]]; then
    zeek_path="${ZEEK_LOG_DIR%/}/conn.log"
  fi

  if [[ -z "$zeek_path" ]]; then
    for candidate in \
      /opt/zeek/logs/current/conn.log \
      /usr/local/zeek/logs/current/conn.log \
      /var/log/zeek/current/conn.log \
      /nsm/zeek/logs/current/conn.log; do
      local zeek_dir
      zeek_dir="$(dirname "$candidate")"
      if [[ -d "$zeek_dir" ]]; then
        zeek_path="$candidate"
        break
      fi
    done
  fi

  log "Suricata eve.json: $suricata_path"
  if [[ -n "$zeek_path" ]]; then
    log "Zeek conn.log: $zeek_path"
  else
    warn "Zeek conn.log path unresolved"
  fi
}

main() {
  if [[ "${DLDS_RUN_SERVICES:-0}" != "1" ]]; then
    log "Skipping host service bootstrap (set DLDS_RUN_SERVICES=1 to enable)"
    report_log_paths
    exit 0
  fi

  cd "$SCRIPT_DIR"
  log "Starting optional host service bootstrap"

  start_service suricata
  start_service auditd
  deploy_zeek

  report_log_paths
  log "Service bootstrap complete"
}

main "$@"
