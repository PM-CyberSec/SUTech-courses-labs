#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

RESET="no"
if [[ "${1:-}" == "--reset" ]]; then
    RESET="yes"
    shift
fi

if [[ "$#" -lt 1 ]]; then
    echo "Usage: $0 [--reset] <username> [server_host [server_port]]" >&2
    exit 1
fi

USERNAME="$1"
SERVER_HOST_ARG="${2:-}"
SERVER_PORT_ARG="${3:-}"
validate_username "$USERNAME" || exit 1
ensure_project_dirs

USER_DIR=$(user_dir "$USERNAME")
if [[ "$RESET" == "yes" ]]; then
    rm -rf "$USER_DIR" "$(server_public_key_path "$USERNAME")"
fi

if [[ -f "$(private_key_path "$USERNAME")" && -f "$(public_key_path "$USERNAME")" ]]; then
    echo "User already exists. Existing keys will be reused."
else
    bash "$ROOT_DIR/crypto/generate_keys.sh" "$USERNAME"
fi

chmod 700 "$USER_DIR"
chmod 600 "$(private_key_path "$USERNAME")"
cp "$(public_key_path "$USERNAME")" "$(server_public_key_path "$USERNAME")" || {
    die "Failed to copy public key to server. Check permissions for: $(server_public_key_path "$USERNAME")"
}
chmod 644 "$(server_public_key_path "$USERNAME")"

PUB_DEST=$(server_public_key_path "$USERNAME")
if [[ ! -f "$PUB_DEST" ]]; then
    die "Public key was not published. Expected at: $PUB_DEST"
fi

echo "User '$USERNAME' is ready."
echo "Local keys: $USER_DIR"
echo "Public key published: $PUB_DEST"

# Upload to remote server if address was provided
if [[ -n "$SERVER_HOST_ARG" ]]; then
    load_config
    local_host="${SERVER_HOST_ARG:-$SERVER_HOST}"
    local_port="${SERVER_PORT_ARG:-$SERVER_PORT}"
    echo "Uploading public key to server at $local_host:$local_port..."
    upload_pubkey "$USERNAME" "$local_host" "$local_port" || true
fi
