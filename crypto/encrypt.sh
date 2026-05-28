#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)

if [[ "$#" -ne 6 ]]; then
    echo "Usage: $0 <recipient_public_key> <sender_private_key> <sender> <recipient> <plaintext_file> <output_file>" >&2
    exit 1
fi

python3 "$SCRIPT_DIR/cipher.py" encrypt \
    --recipient-public-key "$1" \
    --sender-private-key "$2" \
    --sender "$3" \
    --recipient "$4" \
    --input "$5" \
    --output "$6"
