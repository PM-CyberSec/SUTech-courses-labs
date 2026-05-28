#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

echo "Stopping project server if it is running..."
bash "$SERVER_DIR/server.sh" stop >/dev/null 2>&1 || true

echo "Removing demo users alice and bob from $LOCAL_STORE..."
rm -rf "$(user_dir alice)" "$(user_dir bob)"

echo "Removing demo public keys from $USERS_DB..."
rm -f "$(server_public_key_path alice)" "$(server_public_key_path bob)"

echo "Removing demo downloads and logs..."
rm -rf "$DOWNLOADS_DIR/alice" "$DOWNLOADS_DIR/bob"
rm -f "$ROOT_DIR/alice_client.log" "$ROOT_DIR/bob_client.log" "$ROOT_DIR/demo_secret.txt"
rm -f "$SERVER_PID_FILE"
: >"$SERVER_LOG"

echo "Demo data reset complete."
