#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
source "$ROOT_DIR/lib/common.sh"

if [[ "$#" -ne 2 ]]; then
    echo "Usage: $0 <username> <pubkey_file>" >&2
    echo "Imports another device's public key into server/users.db/." >&2
    exit 1
fi

USERNAME="$1"
PUBKEY_FILE="$2"

if [[ ! -f "$PUBKEY_FILE" ]]; then
    die "File not found: $PUBKEY_FILE"
fi

DEST=$(server_public_key_path "$USERNAME")
mkdir -p "$(dirname "$DEST")"
cp "$PUBKEY_FILE" "$DEST"
chmod 644 "$DEST"

echo "Imported public key for '$USERNAME' from $PUBKEY_FILE"
echo "Destination: $DEST"
echo "Both devices can now verify each other's signatures."
