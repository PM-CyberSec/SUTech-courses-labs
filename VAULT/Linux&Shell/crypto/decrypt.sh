#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)

if [[ "$#" -ne 6 ]]; then
    echo "Usage: $0 <recipient_private_key> <sender_public_key> <expected_sender> <expected_recipient> <input_b64_file> <output_plaintext_file>" >&2
    exit 1
fi

python3 "$SCRIPT_DIR/cipher.py" decrypt \
    --recipient-private-key "$1" \
    --sender-public-key "$2" \
    --expected-sender "$3" \
    --expected-recipient "$4" \
    --input "$5" \
    --output "$6"
