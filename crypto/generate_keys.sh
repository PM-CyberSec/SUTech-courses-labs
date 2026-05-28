#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

if [[ "$#" -ne 1 ]]; then
    echo "Usage: $0 <username>" >&2
    exit 1
fi

USERNAME="$1"
validate_username "$USERNAME" || exit 1
ensure_project_dirs

USER_DIR=$(user_dir "$USERNAME")
PRIVATE_KEY="$USER_DIR/private.pem"
PUBLIC_KEY="$USER_DIR/public.pem"

if [[ -f "$PRIVATE_KEY" && -f "$PUBLIC_KEY" ]]; then
    echo "User already exists. Existing keys will be reused."
    chmod 700 "$USER_DIR"
    chmod 600 "$PRIVATE_KEY"
    exit 0
fi

if [[ -e "$PRIVATE_KEY" || -e "$PUBLIC_KEY" ]]; then
    die "Partial key files exist for '$USERNAME'. Use the reset option before regenerating keys."
fi

mkdir -p "$USER_DIR"
chmod 700 "$USER_DIR"

if ! command -v openssl >/dev/null 2>&1; then
    die "OpenSSL is required but not found. Install it with: pkg install openssl"
fi

echo "Generating RSA-4096 key pair for $USERNAME..."
(
    umask 077
    openssl genpkey -algorithm RSA -out "$PRIVATE_KEY" -pkeyopt rsa_keygen_bits:4096
)
chmod 600 "$PRIVATE_KEY"
openssl rsa -pubout -in "$PRIVATE_KEY" -out "$PUBLIC_KEY"
chmod 644 "$PUBLIC_KEY"
echo "Key pair ready in $USER_DIR"
