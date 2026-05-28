#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." >/dev/null 2>&1 && pwd)
# shellcheck source=../lib/common.sh
source "$ROOT_DIR/lib/common.sh"

INPUT="${1:-}"
USERNAME="${2:-}"
CIPHERSHELL_SERVER_HOST="${3:-}"
CIPHERSHELL_SERVER_PORT="${4:-}"
if [[ -z "$INPUT" || -z "$USERNAME" ]]; then
    echo "Usage: $0 <input_file_or_-> <username> [server_host [server_port]]" >&2
    exit 1
fi

validate_username "$USERNAME" || exit 1
ensure_project_dirs

if [[ ! -f "$(private_key_path "$USERNAME")" ]]; then
    echo "User '$USERNAME' is not registered. Register first."
    exit 1
fi

process_line() {
    local line="$1"
    local tag sender payload temp_b64 temp_plain sender_pub

    if [[ "$line" == ERROR* ]]; then
        echo
        echo "Server error: ${line#ERROR }"
        return
    fi

    if [[ "$line" == OK* || "$line" == OFFLINE* ]]; then
        echo
        echo "Server: $line"
        return
    fi

    tag="${line%% *}"
    [[ "$tag" == "FROM" ]] || {
        echo
        echo "Unknown server message: $line"
        return
    }

    line="${line#FROM }"
    sender="${line%% *}"
    payload="${line#"$sender"}"
    payload="${payload# }"

    if ! validate_username "$sender" >/dev/null 2>&1; then
        echo
        echo "Ignored message with invalid sender name."
        return
    fi

    sender_pub=$(server_public_key_path "$sender")
    if [[ ! -f "$sender_pub" ]]; then
        if [[ -n "$CIPHERSHELL_SERVER_HOST" && -n "$CIPHERSHELL_SERVER_PORT" ]]; then
            fetch_pubkey "$sender" "$CIPHERSHELL_SERVER_HOST" "$CIPHERSHELL_SERVER_PORT" 2>/dev/null || true
        fi
        if [[ ! -f "$sender_pub" ]]; then
            echo
            echo "Could not verify message from '$sender' because their public key is missing."
            return
        fi
    fi

    temp_b64=$(mktemp)
    temp_plain=$(mktemp)
    printf '%s' "$payload" >"$temp_b64"

    local decrypt_err
    decrypt_err=$(mktemp)
    if bash "$ROOT_DIR/crypto/decrypt.sh" \
        "$(private_key_path "$USERNAME")" \
        "$sender_pub" \
        "$sender" \
        "$USERNAME" \
        "$temp_b64" \
        "$temp_plain" 2>"$decrypt_err"; then
        rm -f "$decrypt_err"
        if ! python3 "$SCRIPT_DIR/payload_tool.py" render \
            --username "$USERNAME" \
            --sender "$sender" \
            --input "$temp_plain" \
            --downloads-root "$DOWNLOADS_DIR"; then
            echo
            echo "Could not display the decrypted payload from '$sender'."
        fi
    else
        local err_text
        err_text=$(<"$decrypt_err")
        rm -f "$decrypt_err"
        echo
        if [[ -n "$err_text" ]]; then
            echo "Decryption error: ${err_text%%$'\n'*}"
        else
            echo "Could not decrypt or verify message from '$sender'."
        fi
    fi

    rm -f "$temp_b64" "$temp_plain" "$decrypt_err"
}

if [[ "$INPUT" == "-" ]]; then
    while IFS= read -r line; do
        [[ -n "$line" ]] && process_line "$line"
    done
else
    while IFS= read -r line; do
        [[ -n "$line" ]] && process_line "$line"
    done <"$INPUT"
fi
