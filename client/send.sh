#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

if (( $# < 3 )); then
    echo "Usage: $0 <sender_username> <recipient> <message...>" >&2
    exit 1
fi

SENDER="$1"
RECIPIENT="$2"
shift 2
MESSAGE="$*"

load_config
validate_username "$SENDER" || exit 1
validate_username "$RECIPIENT" || exit 1

if [[ -z "${MESSAGE//[[:space:]]/}" ]]; then
    echo "Message must not be empty." >&2
    exit 1
fi

if ! user_is_registered "$SENDER"; then
    echo "Sender '$SENDER' is not registered."
    echo "Run: bash client/register.sh $SENDER"
    exit 1
fi

RECIPIENT_PUB=$(server_public_key_path "$RECIPIENT")
if [[ ! -f "$RECIPIENT_PUB" ]]; then
    # Try to fetch from server (other device may have registered them)
    echo "Recipient key not found locally. Fetching from server..."
    fetch_pubkey "$RECIPIENT" "$SERVER_HOST" "$SERVER_PORT" || {
        echo "Recipient public key not found locally or on server."
        echo "Ask the recipient to register first."
        exit 1
    }
fi

if ! exec 3<>"/dev/tcp/$SERVER_HOST/$SERVER_PORT" 2>/dev/null; then
    echo "Server is not running or not reachable at $SERVER_HOST:$SERVER_PORT."
    echo "Fix: bash server/server.sh start $SERVER_PORT $SERVER_HOST"
    exit 1
fi
trap 'exec 3<&- 3>&- 2>/dev/null || true' EXIT

TEMP_PAYLOAD=$(mktemp)
TEMP_B64=$(mktemp)
cleanup_temp() {
    rm -f "$TEMP_PAYLOAD" "$TEMP_B64"
}
trap 'cleanup_temp; exec 3<&- 3>&- 2>/dev/null || true' EXIT

python3 "$SCRIPT_DIR/payload_tool.py" build-message --message "$MESSAGE" --output "$TEMP_PAYLOAD"
echo "Encrypting message with recipient public key..."
bash "$ROOT_DIR/crypto/encrypt.sh" \
    "$RECIPIENT_PUB" \
    "$(private_key_path "$SENDER")" \
    "$SENDER" \
    "$RECIPIENT" \
    "$TEMP_PAYLOAD" \
    "$TEMP_B64"

echo "Sending message..."
printf 'SEND %s %s %s\n' "$SENDER" "$RECIPIENT" "$(cat "$TEMP_B64")" >&3

if ! IFS= read -r -t 10 response <&3; then
    echo "No delivery response from server."
    exit 1
fi

case "$response" in
    OK*)
        echo "Message sent successfully."
        ;;
    OFFLINE*)
        echo "Message could not be delivered because recipient is offline."
        exit 2
        ;;
    ERROR*)
        echo "Message could not be delivered: ${response#ERROR }"
        exit 1
        ;;
    *)
        echo "Unexpected server response: $response"
        exit 1
        ;;
esac
