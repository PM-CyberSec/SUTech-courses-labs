#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
export CIPHERSHELL_HOME="$SCRIPT_DIR/.demo_home/.ciphershell"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"

cd "$SCRIPT_DIR"

ALICE_PID=""
BOB_PID=""
USERS_DB_BACKUP=$(mktemp -d)
mkdir -p "$USERS_DB_BACKUP/users.db"
if [[ -d "$USERS_DB" ]]; then
    cp -a "$USERS_DB/." "$USERS_DB_BACKUP/users.db/" 2>/dev/null || true
fi

restore_users_db() {
    rm -rf "$USERS_DB"
    mkdir -p "$USERS_DB"
    cp -a "$USERS_DB_BACKUP/users.db/." "$USERS_DB/" 2>/dev/null || true
    rm -rf "$USERS_DB_BACKUP"
}

cleanup() {
    [[ -n "$ALICE_PID" ]] && kill "$ALICE_PID" 2>/dev/null || true
    [[ -n "$BOB_PID" ]] && kill "$BOB_PID" 2>/dev/null || true
    bash "$SERVER_DIR/server.sh" stop >/dev/null 2>&1 || true
    restore_users_db
}
trap cleanup EXIT

wait_for_log() {
    local file="$1"
    local pattern="$2"
    for _ in $(seq 1 15); do
        grep -q "$pattern" "$file" 2>/dev/null && return 0
        sleep 1
    done
    return 1
}

echo "======================================"
echo "    CipherShell Automated Demo"
echo "======================================"

echo "[1/7] Resetting isolated demo data..."
bash "$SCRIPT_DIR/scripts/reset_demo_data.sh" >/dev/null
save_config "127.0.0.1" "500" "$DEFAULT_SERVER_BIND_HOST"

echo "[2/7] Starting server on 127.0.0.1:500..."
bash "$SERVER_DIR/server.sh" start 500 127.0.0.1

echo "[3/7] Registering alice and bob..."
bash "$SCRIPT_DIR/client/register.sh" alice
bash "$SCRIPT_DIR/client/register.sh" bob

echo "[4/7] Starting alice and bob listeners in the background..."
bash "$SCRIPT_DIR/client/client.sh" login alice 127.0.0.1 500 >"$SCRIPT_DIR/alice_client.log" 2>&1 &
ALICE_PID=$!
bash "$SCRIPT_DIR/client/client.sh" login bob 127.0.0.1 500 >"$SCRIPT_DIR/bob_client.log" 2>&1 &
BOB_PID=$!
wait_for_log "$SCRIPT_DIR/alice_client.log" "Waiting for encrypted messages"
wait_for_log "$SCRIPT_DIR/bob_client.log" "Waiting for encrypted messages"

echo "[5/7] Alice sends Bob a message..."
bash "$SCRIPT_DIR/client/send.sh" alice bob "Hello Bob, this is a secret message!"
wait_for_log "$SCRIPT_DIR/bob_client.log" "Hello Bob"

echo "[6/7] Bob replies and Alice sends a file..."
bash "$SCRIPT_DIR/client/send.sh" bob alice "Hi Alice, message received securely."
printf 'Confidential demo file\n' >"$SCRIPT_DIR/demo_secret.txt"
bash "$SCRIPT_DIR/client/send_file.sh" alice bob "$SCRIPT_DIR/demo_secret.txt"
wait_for_log "$SCRIPT_DIR/alice_client.log" "Hi Alice"
wait_for_log "$SCRIPT_DIR/bob_client.log" "New File"

echo "[7/7] Demo results"
echo "--------------------------------------"
echo "Bob listener log:"
cat "$SCRIPT_DIR/bob_client.log"
echo
echo "Alice listener log:"
cat "$SCRIPT_DIR/alice_client.log"
echo
echo "Server log:"
tail -n 40 "$SERVER_LOG"
echo
echo "Demo complete. Received files are under downloads/<username>/."
