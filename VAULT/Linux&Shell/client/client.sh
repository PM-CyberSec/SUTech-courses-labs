#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

if [[ "${1:-}" != "login" || -z "${2:-}" ]]; then
    echo "Usage: $0 login <username> [host] [port]" >&2
    exit 1
fi

USERNAME="$2"
load_config
HOST="${3:-$SERVER_HOST}"
PORT="${4:-$SERVER_PORT}"

validate_username "$USERNAME" || exit 1
validate_host "$HOST" || exit 1
validate_port "$PORT" || exit 1

if ! user_is_registered "$USERNAME"; then
    echo "User '$USERNAME' is not registered yet."
    echo "Run: bash client/register.sh $USERNAME"
    exit 1
fi

# Upload our public key to the server so other devices can fetch it
upload_pubkey "$USERNAME" "$HOST" "$PORT" || true

if ! exec 3<>"/dev/tcp/$HOST/$PORT" 2>/dev/null; then
    echo "Could not connect to the CipherShell server at $HOST:$PORT."
    echo "Start it with: bash server/server.sh start $PORT $HOST"
    exit 1
fi

CLEANED="no"
cleanup() {
    [[ "$CLEANED" == "yes" ]] && return
    CLEANED="yes"
    exec 3<&- 3>&- 2>/dev/null || true
}

trap 'echo; cleanup; echo "Listener stopped."; exit 0' INT TERM
trap cleanup EXIT

printf 'LOGIN %s\n' "$USERNAME" >&3
if ! IFS= read -r reply <&3; then
    echo "Server closed the connection before login completed."
    exit 1
fi

case "$reply" in
    OK*)
        ;;
    ERROR*)
        echo "${reply#ERROR }"
        exit 1
        ;;
    *)
        echo "Unexpected server response: $reply"
        exit 1
        ;;
esac

echo "Logged in as $USERNAME"
echo "Connected to $HOST:$PORT"
echo "Waiting for encrypted messages..."
echo "Press Ctrl+C to stop listening."

bash "$SCRIPT_DIR/listener.sh" - "$USERNAME" "$HOST" "$PORT" <&3
echo "Connection closed."
