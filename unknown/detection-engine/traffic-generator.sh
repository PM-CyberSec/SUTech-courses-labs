#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

log() { printf '[traffic-generator] %s\n' "$*"; }
warn() { printf '[traffic-generator][warn] %s\n' "$*" >&2; }

load_env_file() {
  local env_file="$1"
  if [[ -f "$env_file" ]]; then
    # shellcheck disable=SC1090
    set -a; source "$env_file"; set +a
    log "Loaded environment file: $env_file"
  fi
}

command_exists() {
  command -v "$1" >/dev/null 2>&1
}

report_log_sources() {
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
      if [[ -f "$candidate" ]]; then
        zeek_path="$candidate"
        break
      fi
    done
  fi

  if [[ -f "$suricata_path" ]]; then
    log "Suricata eve.json path: $suricata_path"
  else
    warn "Suricata eve.json not found at: $suricata_path"
  fi

  if [[ -n "$zeek_path" && -f "$zeek_path" ]]; then
    log "Zeek conn.log path: $zeek_path"
  else
    warn "Zeek conn.log path not found (set ZEEK_CONN_LOG or ZEEK_LOG_DIR if needed)"
  fi
}

resolve_local_login_url() {
  local explicit_url="${DLDS_TRAFFIC_TARGET_URL:-}"
  if [[ -n "$explicit_url" ]]; then
    if [[ "$explicit_url" =~ ^https?:// ]]; then
      printf '%s\n' "$explicit_url"
      return
    fi
    warn "Invalid DLDS_TRAFFIC_TARGET_URL '$explicit_url'; expected http(s) URL"
  fi

  local app_url="${APP_URL:-${LARAVEL_APP_URL:-}}"
  local api_url="${DLDS_API_URL:-${LARAVEL_API_URL:-}}"
  local base_url="$app_url"

  # If APP_URL is localhost without port but API URL has one, prefer API origin.
  if [[ -n "$api_url" && "$api_url" =~ ^https?://[^/]+ ]]; then
    local api_origin="${BASH_REMATCH[0]}"
    if [[ -z "$base_url" ]] || [[ "$base_url" =~ ^https?://(localhost|127\.0\.0\.1)/?$ ]]; then
      base_url="$api_origin"
    fi
  fi

  if [[ -z "$base_url" ]]; then
    base_url="http://127.0.0.1:8000"
  fi

  local login_url="${base_url%/}/login"
  if [[ ! "$login_url" =~ ^https?:// ]]; then
    warn "Computed invalid login URL '$login_url'; falling back to http://127.0.0.1:8000/login"
    login_url="http://127.0.0.1:8000/login"
  fi

  printf '%s\n' "$login_url"
}

hit_url_with_curl() {
  local target_url="$1"
  local timeout="${TRAFFIC_CURL_TIMEOUT:-5}"

  if ! command_exists curl; then
    warn "curl is not installed; skipping request: $target_url"
    return 0
  fi

  if ! curl -fsS --max-time "$timeout" "$target_url" >/dev/null; then
    warn "Request failed: $target_url"
  fi
}

generate_http_traffic() {
  log "Generating HTTP/HTTPS traffic"
  hit_url_with_curl "http://example.com"
  hit_url_with_curl "https://example.com"
}

generate_dns_traffic() {
  log "Generating DNS traffic"
  if command_exists nslookup; then
    nslookup google.com >/dev/null 2>&1 || warn "nslookup google.com failed"
    nslookup yahoo.com >/dev/null 2>&1 || warn "nslookup yahoo.com failed"
    return
  fi

  if command_exists dig; then
    dig +short google.com >/dev/null 2>&1 || warn "dig google.com failed"
    dig +short yahoo.com >/dev/null 2>&1 || warn "dig yahoo.com failed"
    return
  fi

  warn "Skipping DNS generation (no nslookup/dig found)"
}

generate_file_download_traffic() {
  local output_path="${TMPDIR:-/tmp}/dlds_testfile.txt"
  local sample_url="https://www.w3.org/TR/PNG/iso_8859-1.txt"
  log "Generating file-download traffic"

  if command_exists wget; then
    wget -q --timeout=10 --tries=1 "$sample_url" -O "$output_path" || warn "wget download failed"
    return
  fi

  if command_exists curl; then
    curl -fsS --max-time 10 "$sample_url" -o "$output_path" || warn "curl download failed"
    return
  fi

  warn "Skipping file-download traffic (no wget/curl found)"
}

generate_local_laravel_traffic() {
  local login_url="$1"
  local request_count="${TRAFFIC_LOCAL_REQUEST_COUNT:-5}"

  if [[ ! "$request_count" =~ ^[0-9]+$ ]] || [[ "$request_count" -lt 1 ]]; then
    warn "Invalid TRAFFIC_LOCAL_REQUEST_COUNT='$request_count'; using 5"
    request_count=5
  fi

  log "Generating repeated local Laravel requests to: $login_url (count=$request_count)"
  local i
  for ((i = 1; i <= request_count; i++)); do
    hit_url_with_curl "$login_url"
  done
}

main() {
  load_env_file "$PROJECT_DIR/.env"
  load_env_file "$SCRIPT_DIR/.env"
  cd "$SCRIPT_DIR"

  log "Starting synthetic traffic generation for Suricata/Zeek pipelines"
  report_log_sources

  local login_url
  login_url="$(resolve_local_login_url)"

  generate_http_traffic
  generate_dns_traffic
  generate_file_download_traffic
  generate_local_laravel_traffic "$login_url"

  log "Traffic generation complete"
}

main "$@"
