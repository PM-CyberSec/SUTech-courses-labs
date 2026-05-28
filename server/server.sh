#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

cd "$SCRIPT_DIR"

show_usage() {
    cat <<EOF
Usage:
  $0 start [port] [client_host] [bind_host]
  $0 stop
  $0 status
  $0 foreground [port] [client_host] [bind_host]

Compatibility:
  $0 <port>    # starts in foreground on that port
EOF
}

warn_low_port() {
    local port="$1"
    if (( port < 1024 )); then
        echo "Note: Port $port is a privileged port on many Linux systems."
        echo "If binding fails, choose a port >= 1024 or grant Python CAP_NET_BIND_SERVICE."
    fi
}

assert_port_free() {
    local port="$1"
    mapfile -t listeners < <(port_listener_pids "$port")
    if (( ${#listeners[@]} > 0 )); then
        echo "Port $port is already in use by PID(s): ${listeners[*]}" >&2
        echo "Run: ss -tulnp | grep :$port" >&2
        return 1
    fi
}

status_server() {
    load_config
    local pid
    if pid=$(read_server_pid 2>/dev/null) && is_project_server_pid "$pid"; then
        echo "Server status: running"
        echo "PID: $pid"
        echo "Client address: $SERVER_HOST:$SERVER_PORT"
        echo "Log: $SERVER_LOG"
        return 0
    fi

    mapfile -t pids < <(project_server_pids)
    if (( ${#pids[@]} > 0 )); then
        echo "Server status: running (no current PID file)"
        echo "PID(s): ${pids[*]}"
        echo "Client address from config: $SERVER_HOST:$SERVER_PORT"
        echo "Log: $SERVER_LOG"
        return 0
    fi

    echo "Server status: stopped"
    echo "Configured client address: $SERVER_HOST:$SERVER_PORT"
    echo "Log: $SERVER_LOG"
}

start_server() {
    load_config
    local port="${1:-$SERVER_PORT}"
    local client_host="${2:-$SERVER_HOST}"
    local bind_host="${3:-$SERVER_BIND_HOST}"

    validate_port "$port" || exit 1
    validate_host "$client_host" || exit 1
    validate_host "$bind_host" || exit 1
    ensure_project_dirs

    mapfile -t existing < <(project_server_pids)
    if (( ${#existing[@]} > 0 )); then
        echo "CipherShell server is already running with PID(s): ${existing[*]}"
        echo "Use '$0 stop' before starting another server."
        status_server
        return 0
    fi

    assert_port_free "$port"
    warn_low_port "$port"
    save_config "$client_host" "$port" "$bind_host"

    : >"$SERVER_LOG"
    echo "Starting CipherShell server on $client_host:$port..."
    nohup python3 "$SCRIPT_DIR/socket_mux.py" \
        --host "$bind_host" \
        --port "$port" \
        --users-db "$USERS_DB" \
        >>"$SERVER_LOG" 2>&1 &
    local pid=$!
    echo "$pid" >"$SERVER_PID_FILE"

    sleep 1
    if ! kill -0 "$pid" 2>/dev/null; then
        rm -f "$SERVER_PID_FILE"
        echo "Server failed to start. Last log lines:" >&2
        tail -n 40 "$SERVER_LOG" >&2 || true
        return 1
    fi

    echo "Server started successfully."
    echo "PID: $pid"
    echo "Active address: $client_host:$port"
    echo "Log: $SERVER_LOG"
}

stop_server() {
    mapfile -t pids < <(project_server_pids)
    if (( ${#pids[@]} == 0 )); then
        rm -f "$SERVER_PID_FILE"
        echo "Server is already stopped."
        return 0
    fi

    echo "Stopping CipherShell server PID(s): ${pids[*]}"
    kill "${pids[@]}" 2>/dev/null || true
    sleep 1
    mapfile -t still_running < <(project_server_pids)
    if (( ${#still_running[@]} > 0 )); then
        echo "Server did not stop cleanly; forcing PID(s): ${still_running[*]}"
        kill -9 "${still_running[@]}" 2>/dev/null || true
    fi
    rm -f "$SERVER_PID_FILE"
    echo "Server stopped."
}

foreground_server() {
    load_config
    local port="${1:-$SERVER_PORT}"
    local client_host="${2:-$SERVER_HOST}"
    local bind_host="${3:-$SERVER_BIND_HOST}"

    validate_port "$port" || exit 1
    validate_host "$client_host" || exit 1
    validate_host "$bind_host" || exit 1
    ensure_project_dirs
    assert_port_free "$port"
    warn_low_port "$port"
    save_config "$client_host" "$port" "$bind_host"

    echo "Starting CipherShell server in foreground on $client_host:$port..."
    exec python3 "$SCRIPT_DIR/socket_mux.py" \
        --host "$bind_host" \
        --port "$port" \
        --users-db "$USERS_DB"
}

COMMAND="${1:-status}"
case "$COMMAND" in
    start)
        shift
        start_server "$@"
        ;;
    stop)
        stop_server
        ;;
    status)
        status_server
        ;;
    foreground)
        shift
        foreground_server "$@"
        ;;
    -h|--help|help)
        show_usage
        ;;
    '' )
        status_server
        ;;
    * )
        if [[ "$COMMAND" =~ ^[0-9]+$ ]]; then
            shift || true
            foreground_server "$COMMAND" "$@"
        else
            show_usage >&2
            exit 1
        fi
        ;;
esac
