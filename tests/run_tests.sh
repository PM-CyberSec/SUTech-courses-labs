#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
export CIPHERSHELL_HOME="$ROOT_DIR/.test_home/.ciphershell"
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

TEST_PORT=500
TEST_HOST=127.0.0.1
BOB_PID=""
USERS_DB_BACKUP=$(mktemp -d)
mkdir -p "$USERS_DB_BACKUP/users.db"
if [[ -d "$USERS_DB" ]]; then
    cp -a "$USERS_DB/." "$USERS_DB_BACKUP/users.db/" 2>/dev/null || true
fi

pass() {
    echo "[PASS] $*"
}

fail() {
    echo "[FAIL] $*" >&2
    exit 1
}

cleanup() {
    if [[ -n "$BOB_PID" ]]; then
        kill "$BOB_PID" 2>/dev/null || true
    fi
    bash "$SERVER_DIR/server.sh" stop >/dev/null 2>&1 || true
    rm -rf "$USERS_DB"
    mkdir -p "$USERS_DB"
    cp -a "$USERS_DB_BACKUP/users.db/." "$USERS_DB/" 2>/dev/null || true
    rm -rf "$USERS_DB_BACKUP"
}
trap cleanup EXIT

wait_for_log() {
    local file="$1"
    local pattern="$2"
    local limit="${3:-15}"
    for _ in $(seq 1 "$limit"); do
        if grep -q "$pattern" "$file" 2>/dev/null; then
            return 0
        fi
        sleep 1
    done
    return 1
}

cd "$ROOT_DIR"
rm -rf "$ROOT_DIR/.test_home" "$ROOT_DIR/downloads/alice" "$ROOT_DIR/downloads/bob"
bash "$ROOT_DIR/scripts/reset_demo_data.sh" >/dev/null 2>&1 || true
save_config "$TEST_HOST" "$TEST_PORT" "$DEFAULT_SERVER_BIND_HOST"

if bash "$ROOT_DIR/client/register.sh" "Bad User" >/tmp/ciphershell_invalid_user.out 2>&1; then
    fail "invalid username should fail"
fi
pass "Invalid username rejected"

if bash "$SERVER_DIR/server.sh" start notaport "$TEST_HOST" >/tmp/ciphershell_invalid_port.out 2>&1; then
    fail "invalid port should fail"
fi
pass "Invalid port rejected"

if bash "$ROOT_DIR/client/send.sh" alice bob "server down" >/tmp/ciphershell_server_down.out 2>&1; then
    fail "server-not-running send should fail"
fi
pass "Server-not-running case is clear"

bash "$ROOT_DIR/client/register.sh" alice >/tmp/ciphershell_register_alice.out
bash "$ROOT_DIR/client/register.sh" bob >/tmp/ciphershell_register_bob.out
test -f "$CIPHERSHELL_HOME/alice/private.pem" || fail "alice private key missing"
test -f "$USERS_DB/bob.pub" || fail "bob public key missing"
pass "Register alice"
pass "Register bob"

bash "$SERVER_DIR/server.sh" start "$TEST_PORT" "$TEST_HOST" >/tmp/ciphershell_server_start.out
wait_for_log "$SERVER_LOG" "server started" || fail "server did not log startup"
pass "Start server on port $TEST_PORT"

bash "$ROOT_DIR/client/client.sh" login bob "$TEST_HOST" "$TEST_PORT" >"$ROOT_DIR/bob_client.log" 2>&1 &
BOB_PID=$!
wait_for_log "$ROOT_DIR/bob_client.log" "Waiting for encrypted messages" || fail "bob listener did not start"
pass "Login bob listener"

bash "$ROOT_DIR/client/send.sh" alice bob "hello from tests" >/tmp/ciphershell_send_msg.out
grep -q "Message sent successfully" /tmp/ciphershell_send_msg.out || fail "message send success not shown"
wait_for_log "$ROOT_DIR/bob_client.log" "hello from tests" || fail "bob did not receive message"
pass "Send message from alice to bob"

printf 'secret file from tests\n' >"$ROOT_DIR/demo_secret.txt"
bash "$ROOT_DIR/client/send_file.sh" alice bob "$ROOT_DIR/demo_secret.txt" >/tmp/ciphershell_send_file.out
grep -q "File sent successfully" /tmp/ciphershell_send_file.out || fail "file send success not shown"
wait_for_log "$ROOT_DIR/bob_client.log" "New File" || fail "bob did not receive file"
cmp "$ROOT_DIR/demo_secret.txt" "$ROOT_DIR/downloads/bob/demo_secret.txt" || fail "received file differs"
pass "Send file from alice to bob"

if bash "$ROOT_DIR/client/send.sh" bob alice "offline check" >/tmp/ciphershell_offline.out 2>&1; then
    fail "recipient offline should return non-zero"
fi
grep -q "recipient is offline" /tmp/ciphershell_offline.out || fail "offline error not clear"
pass "Recipient offline message is clear"

grep -q "message forwarded sender=alice recipient=bob" "$SERVER_LOG" || fail "forward log missing"
grep -q "recipient offline sender=bob recipient=alice" "$SERVER_LOG" || fail "offline log missing"
pass "Server logs useful delivery events"

echo
echo "All CipherShell tests passed."
