#!/usr/bin/env bash

if [[ -z "${BASH_VERSION:-}" ]]; then
    echo "CipherShell requires Bash." >&2
    exit 1
fi

CIPHERSHELL_ROOT="${CIPHERSHELL_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." >/dev/null 2>&1 && pwd)}"
CONFIG_DIR="$CIPHERSHELL_ROOT/config"
CONFIG_FILE="$CONFIG_DIR/ciphershell.conf"
SERVER_DIR="$CIPHERSHELL_ROOT/server"
USERS_DB="$SERVER_DIR/users.db"
SERVER_LOG="$CIPHERSHELL_ROOT/server.log"
SERVER_PID_FILE="$SERVER_DIR/server.pid"
LOCAL_STORE="${CIPHERSHELL_HOME:-$HOME/.ciphershell}"
DOWNLOADS_DIR="$CIPHERSHELL_ROOT/downloads"
DEFAULT_SERVER_HOST="127.0.0.1"
DEFAULT_SERVER_BIND_HOST="0.0.0.0"
DEFAULT_SERVER_PORT="500"
USERNAME_RE='^[a-z0-9_-]+$'

die() {
    echo "Error: $*" >&2
    exit 1
}

info() {
    echo "$*"
}

ensure_project_dirs() {
    mkdir -p "$CONFIG_DIR" "$USERS_DB" "$LOCAL_STORE" "$DOWNLOADS_DIR"
    chmod 700 "$LOCAL_STORE" 2>/dev/null || true
}

ensure_config() {
    ensure_project_dirs
    if [[ ! -f "$CONFIG_FILE" ]]; then
        cat >"$CONFIG_FILE" <<EOF
SERVER_HOST=$DEFAULT_SERVER_HOST
SERVER_BIND_HOST=$DEFAULT_SERVER_BIND_HOST
SERVER_PORT=$DEFAULT_SERVER_PORT
EOF
    fi
}

load_config() {
    ensure_config
    # shellcheck disable=SC1090
    source "$CONFIG_FILE"
    SERVER_HOST="${SERVER_HOST:-$DEFAULT_SERVER_HOST}"
    SERVER_BIND_HOST="${SERVER_BIND_HOST:-$DEFAULT_SERVER_BIND_HOST}"
    SERVER_PORT="${SERVER_PORT:-$DEFAULT_SERVER_PORT}"
}

save_config() {
    local host="$1"
    local port="$2"
    local bind_host="${3:-$DEFAULT_SERVER_BIND_HOST}"
    validate_host "$host" || return 1
    validate_host "$bind_host" || return 1
    validate_port "$port" || return 1
    ensure_project_dirs
    cat >"$CONFIG_FILE" <<EOF
SERVER_HOST=$host
SERVER_BIND_HOST=$bind_host
SERVER_PORT=$port
EOF
}

validate_username() {
    local username="${1:-}"
    [[ -n "$username" ]] || { echo "Username must not be empty." >&2; return 1; }
    [[ "$username" != *[[:space:]]* ]] || { echo "Username must not contain spaces." >&2; return 1; }
    [[ "$username" =~ $USERNAME_RE ]] || {
        echo "Username must use only lowercase letters, numbers, underscore, or dash." >&2
        return 1
    }
}

validate_port() {
    local port="${1:-}"
    [[ "$port" =~ ^[0-9]+$ ]] || { echo "Port must be a number between 1 and 65535." >&2; return 1; }
    (( port >= 1 && port <= 65535 )) || { echo "Port must be between 1 and 65535." >&2; return 1; }
}

validate_host() {
    local host="${1:-}"
    [[ -n "$host" ]] || { echo "Host must not be empty." >&2; return 1; }
    [[ "$host" != *[[:space:]]* ]] || { echo "Host must not contain spaces." >&2; return 1; }
}

user_dir() {
    printf '%s/%s\n' "$LOCAL_STORE" "$1"
}

private_key_path() {
    printf '%s/private.pem\n' "$(user_dir "$1")"
}

public_key_path() {
    printf '%s/public.pem\n' "$(user_dir "$1")"
}

server_public_key_path() {
    printf '%s/%s.pub\n' "$USERS_DB" "$1"
}

user_is_registered() {
    local username="$1"
    if [[ ! -f "$(private_key_path "$username")" ]]; then
        echo "User '$username' is missing private key:" >&2
        echo "  private_key=$(private_key_path "$username")" >&2
        return 1
    fi
    if [[ ! -f "$(public_key_path "$username")" ]]; then
        echo "User '$username' is missing public key:" >&2
        echo "  public_key=$(public_key_path "$username")" >&2
        return 1
    fi
    return 0
}

upload_pubkey() {
    local username="$1" host="$2" port="$3"
    local pubkey dest
    dest="$(server_public_key_path "$username")"
    if [[ ! -f "$(public_key_path "$username")" ]]; then
        echo "Cannot upload: local public key not found for '$username'." >&2
        return 1
    fi
    pubkey=$(base64 < "$(public_key_path "$username")" | tr -d '\n')
    exec 3<>"/dev/tcp/$host/$port" 2>/dev/null || {
        echo "Could not upload public key (server unreachable at $host:$port)." >&2
        return 1
    }
    printf 'PUTKEY %s %s\n' "$username" "$pubkey" >&3
    IFS= read -r -t 5 resp <&3 || resp=""
    exec 3<&- 3>&- 2>/dev/null || true
    if [[ "$resp" == "OK"* ]]; then
        echo "Public key uploaded to server for '$username'." >&2
        return 0
    fi
    echo "Could not upload public key: ${resp:-server unreachable}" >&2
    return 1
}

fetch_pubkey() {
    local username="$1" host="$2" port="$3"
    local dest
    dest="$(server_public_key_path "$username")"
    exec 3<>"/dev/tcp/$host/$port" 2>/dev/null || {
        echo "Could not fetch public key for '$username' (server unreachable at $host:$port)." >&2
        return 1
    }
    printf 'GETKEY %s\n' "$username" >&3
    IFS= read -r -t 5 resp <&3 || resp=""
    exec 3<&- 3>&- 2>/dev/null || true
    if [[ "$resp" == "KEY "* ]]; then
        local keydata="${resp#KEY }"
        mkdir -p "$(dirname "$dest")"
        echo "$keydata" | base64 -d > "$dest" 2>/dev/null
        chmod 644 "$dest" 2>/dev/null || true
        echo "Fetched public key for '$username' from server." >&2
        return 0
    fi
    echo "Could not fetch public key for '$username': ${resp:-server unreachable}" >&2
    return 1
}

can_connect() {
    local host="$1"
    local port="$2"
    timeout 3 bash -c "exec 9<>/dev/tcp/$host/$port" >/dev/null 2>&1
}

port_listener_pids() {
    local port="$1"
    if command -v lsof >/dev/null 2>&1; then
        lsof -tiTCP:"$port" -sTCP:LISTEN 2>/dev/null || true
    else
        ss -ltnp 2>/dev/null | awk -v port=":$port" '$4 ~ port {print $0}' | sed -n 's/.*pid=\([0-9]\+\).*/\1/p'
    fi
}

project_server_pids() {
    local pid cwd
    while read -r pid _; do
        [[ -n "$pid" ]] || continue
        cwd=$(pwdx "$pid" 2>/dev/null | sed 's/^[0-9]*: //') || true
        if [[ "$cwd" == "$SERVER_DIR" ]]; then
            printf '%s\n' "$pid"
        fi
    done < <(pgrep -af 'socket_mux.py' 2>/dev/null || true)
}

is_project_server_pid() {
    local pid="${1:-}"
    [[ -n "$pid" && -d "/proc/$pid" ]] || return 1
    local cwd
    cwd=$(pwdx "$pid" 2>/dev/null | sed 's/^[0-9]*: //') || return 1
    [[ "$cwd" == "$SERVER_DIR" ]]
}

read_server_pid() {
    [[ -f "$SERVER_PID_FILE" ]] || return 1
    local pid
    pid=$(<"$SERVER_PID_FILE")
    [[ "$pid" =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$pid"
}

pause_for_user() {
    echo
    read -r -n 1 -s -p "Press any key to continue..."
    echo
}
