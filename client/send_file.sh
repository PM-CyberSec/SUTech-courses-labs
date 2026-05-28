#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

MAX_FILE_BYTES=$((25 * 1024 * 1024))

if [[ "$#" -ne 3 ]]; then
    echo "Usage: $0 <sender_username> <recipient> <file_path>" >&2
    exit 1
fi

SENDER="$1"
RECIPIENT="$2"
FILE="$3"

load_config
validate_username "$SENDER" || exit 1
validate_username "$RECIPIENT" || exit 1

if [[ ! -f "$FILE" ]]; then
    echo "File does not exist: $FILE" >&2
    exit 1
fi

FILE_SIZE=$(stat -c '%s' "$FILE")
if (( FILE_SIZE > MAX_FILE_BYTES )); then
    echo "File is too large for this beginner demo transfer path." >&2
    echo "Maximum: $MAX_FILE_BYTES bytes; selected file: $FILE_SIZE bytes." >&2
    exit 1
fi

if ! user_is_registered "$SENDER"; then
    echo "Sender '$SENDER' is not registered."
    echo "Run: bash client/register.sh $SENDER"
    exit 1
fi

RECIPIENT_PUB=$(server_public_key_path "$RECIPIENT")
if [[ ! -f "$RECIPIENT_PUB" ]]; then
    echo "Recipient public key not found: $RECIPIENT_PUB"
    echo "Ask the recipient to register first."
    exit 1
fi

if ! exec 3<>"/dev/tcp/$SERVER_HOST/$SERVER_PORT" 2>/dev/null; then
    echo "Server is not running or not reachable at $SERVER_HOST:$SERVER_PORT."
    echo "Fix: bash server/server.sh start $SERVER_PORT $SERVER_HOST"
    exit 1
fi

TEMP_PAYLOAD=$(mktemp)
TEMP_B64=$(mktemp)
cleanup() {
    rm -f "$TEMP_PAYLOAD" "$TEMP_B64"
    exec 3<&- 3>&- 2>/dev/null || true
}
trap cleanup EXIT

echo "File: $FILE"
echo "Size: $FILE_SIZE bytes"
python3 "$SCRIPT_DIR/payload_tool.py" build-file --file "$FILE" --output "$TEMP_PAYLOAD"

echo "Encrypting file with recipient public key..."
bash "$ROOT_DIR/crypto/encrypt.sh" \
    "$RECIPIENT_PUB" \
    "$(private_key_path "$SENDER")" \
    "$SENDER" \
    "$RECIPIENT" \
    "$TEMP_PAYLOAD" \
    "$TEMP_B64"

echo "Sending encrypted file..."
printf 'SEND %s %s %s\n' "$SENDER" "$RECIPIENT" "$(cat "$TEMP_B64")" >&3

if ! IFS= read -r -t 20 response <&3; then
    echo "No delivery response from server."
    exit 1
fi

case "$response" in
    OK*)
        echo "File sent successfully."
        ;;
    OFFLINE*)
        echo "File could not be delivered because recipient is offline."
        exit 2
        ;;
    ERROR*)
        echo "File could not be delivered: ${response#ERROR }"
        exit 1
        ;;
    *)
        echo "Unexpected server response: $response"
        exit 1
        ;;
esac
