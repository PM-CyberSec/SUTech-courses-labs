#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
source "$ROOT_DIR/lib/common.sh"

if [[ "$#" -lt 1 ]]; then
    echo "Usage: $0 <username> [output_dir]" >&2
    echo "Exports a user's server public key for use on another device." >&2
    exit 1
fi

USERNAME="$1"
OUTPUT_DIR="${2:-.}"

SRC=$(server_public_key_path "$USERNAME")
if [[ ! -f "$SRC" ]]; then
    die "Public key not found for '$USERNAME' at: $SRC"
fi

DEST="$OUTPUT_DIR/${USERNAME}.pub"
cp "$SRC" "$DEST"
echo "Exported public key for '$USERNAME': $DEST"
echo "Transfer this file to the other device and run:"
echo "  bash scripts/import_keys.sh $USERNAME $DEST"
