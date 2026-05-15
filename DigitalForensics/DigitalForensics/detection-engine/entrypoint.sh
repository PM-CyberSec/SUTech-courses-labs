#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

log() { printf '[dlds-entrypoint] %s\n' "$*"; }
warn() { printf '[dlds-entrypoint][warn] %s\n' "$*" >&2; }
die() { printf '[dlds-entrypoint][error] %s\n' "$*" >&2; exit 1; }

load_env_file() {
  local env_file="$1"
  if [[ -f "$env_file" ]]; then
    # shellcheck disable=SC1090
    set -a; source "$env_file"; set +a
    log "Loaded environment file: $env_file"
  fi
}

check_python() {
  local py_bin="${PYTHON_BIN:-python3}"

  if ! command -v "$py_bin" >/dev/null 2>&1; then
    die "Python interpreter not found: $py_bin"
  fi

  if [[ ! -f "$SCRIPT_DIR/main.py" ]]; then
    die "main.py not found in $SCRIPT_DIR"
  fi

  PYTHON_BIN="$py_bin"
}

validate_runtime_inputs() {
  local api_url="${DLDS_API_URL:-${LARAVEL_API_URL:-}}"
  if [[ -z "$api_url" ]]; then
    warn "Neither DLDS_API_URL nor LARAVEL_API_URL is set. Engine will run without Laravel forwarding."
  elif [[ ! "$api_url" =~ ^https?:// ]]; then
    die "Invalid API URL '$api_url' (must start with http:// or https://)"
  else
    log "Laravel API target: $api_url"
  fi

  local suricata_path="${SURICATA_EVE_PATH:-${SURICATA_EVE:-/var/log/suricata/eve.json}}"
  if [[ -f "$suricata_path" ]]; then
    log "Suricata eve.json path: $suricata_path"
  else
    warn "Suricata eve.json not found at: $suricata_path"
  fi

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
      if [[ -f "$candidate" ]]; then
        zeek_path="$candidate"
        break
      fi
    done
  fi

  if [[ -n "$zeek_path" && -f "$zeek_path" ]]; then
    log "Zeek conn.log path: $zeek_path"
  else
    warn "Zeek conn.log path is not available. Set ZEEK_CONN_LOG or ZEEK_LOG_DIR if Zeek is enabled."
  fi
}

maybe_install_deps() {
  if [[ "${DLDS_AUTO_PIP_INSTALL:-0}" != "1" ]]; then
    return
  fi

  if [[ ! -f "$SCRIPT_DIR/requirements.txt" ]]; then
    die "DLDS_AUTO_PIP_INSTALL=1 but requirements.txt is missing"
  fi

  "$PYTHON_BIN" -m pip --version >/dev/null 2>&1 || die "pip is not available for $PYTHON_BIN"

  log "Installing Python dependencies from requirements.txt"
  "$PYTHON_BIN" -m pip install --disable-pip-version-check -r "$SCRIPT_DIR/requirements.txt"
}

activate_venv_if_present() {
  if [[ "${DLDS_USE_VENV:-1}" != "1" ]]; then
    return
  fi

  if [[ -n "${VIRTUAL_ENV:-}" ]]; then
    return
  fi

  if [[ -f "$SCRIPT_DIR/.venv/bin/activate" ]]; then
    # shellcheck disable=SC1091
    source "$SCRIPT_DIR/.venv/bin/activate"
    log "Activated virtualenv: $SCRIPT_DIR/.venv"
  fi
}

run_main() {
  cd "$SCRIPT_DIR"
  log "Starting detection engine"
  exec "$PYTHON_BIN" "$SCRIPT_DIR/main.py"
}

main() {
  load_env_file "$PROJECT_DIR/.env"
  load_env_file "$SCRIPT_DIR/.env"

  check_python
  activate_venv_if_present
  maybe_install_deps
  validate_runtime_inputs
  run_main
}

main "$@"
