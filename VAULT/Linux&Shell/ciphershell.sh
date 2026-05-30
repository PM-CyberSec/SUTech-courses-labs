#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"

if [[ ! -x "${BASH_SOURCE[0]}" ]]; then
    echo "Launcher is not executable yet."
    echo "Run: chmod +x ciphershell.sh"
    echo
fi

ensure_project_dirs
load_config

clear_screen() {
    if [[ -t 1 ]]; then
        clear
    fi
}

header() {
    load_config
    clear_screen
    echo "=========================================="
    echo "           NeonNet CyberDeck"
    echo "=========================================="
    bash "$SERVER_DIR/server.sh" status | sed 's/^/  /'
    echo "=========================================="
}

prompt_default() {
    local prompt="$1"
    local default="$2"
    local value
    read -r -p "$prompt [$default]: " value
    printf '%s\n' "${value:-$default}"
}

boot_core() {
    load_config
    bash "$SERVER_DIR/server.sh" status
    echo
    if bash "$SERVER_DIR/server.sh" status 2>&1 | grep -q "running"; then
        echo "Server is already running."
        pause_for_user
        return
    fi
    local port host
    port=$(prompt_default "Server port" "$SERVER_PORT")
    validate_port "$port" || { pause_for_user; return; }
    host=$(prompt_default "Client host" "$SERVER_HOST")
    validate_host "$host" || { pause_for_user; return; }
    bash "$SERVER_DIR/server.sh" start "$port" "$host" "$DEFAULT_SERVER_BIND_HOST" || true
    pause_for_user
}

enlist_ident() {
    local callsign
    read -r -p "Callsign: " callsign
    if validate_username "$callsign"; then
        bash "$SCRIPT_DIR/client/register.sh" "$callsign" || true
    fi
    pause_for_user
}

boot_core_advanced() {
    load_config
    bash "$SERVER_DIR/server.sh" status
    echo
    if bash "$SERVER_DIR/server.sh" status 2>&1 | grep -q "running"; then
        echo "Server is already running."
        pause_for_user
        return
    fi
    local port host bind_host
    port=$(prompt_default "Server port" "$SERVER_PORT")
    validate_port "$port" || { pause_for_user; return; }
    host=$(prompt_default "Client host" "$SERVER_HOST")
    validate_host "$host" || { pause_for_user; return; }
    bind_host=$(prompt_default "Bind host" "$DEFAULT_SERVER_BIND_HOST")
    validate_host "$bind_host" || { pause_for_user; return; }
    bash "$SERVER_DIR/server.sh" start "$port" "$host" "$bind_host" || true
    pause_for_user
}

eavesdrop_channel() {
    load_config
    local callsign
    read -r -p "Callsign to monitor: " callsign
    validate_username "$callsign" || { pause_for_user; return; }
    echo
    echo "Starting watcher. Keep this terminal open."
    echo
    bash "$SCRIPT_DIR/client/client.sh" login "$callsign" "$SERVER_HOST" "$SERVER_PORT" || true
    pause_for_user
}

transmit_pulse() {
    local source target msg
    read -r -p "Source callsign: " source
    read -r -p "Target callsign: " target
    read -r -p "Message: " msg
    bash "$SCRIPT_DIR/client/send.sh" "$source" "$target" "$msg" || true
    pause_for_user
}

cli_channel() {
    load_config
    local callsign
    read -r -p "Callsign: " callsign
    if ! validate_username "$callsign" 2>/dev/null; then
        echo "[!] Invalid callsign."
        pause_for_user
        return
    fi
    bash "$SCRIPT_DIR/client/interactive_chat.sh" "$callsign" "$SERVER_HOST" "$SERVER_PORT" || true
    pause_for_user
}

upload_file() {
    local source target file_path
    read -r -p "Source callsign: " source
    read -r -p "Target callsign: " target
    read -r -e -p "File path: " file_path
    bash "$SCRIPT_DIR/client/send_file.sh" "$source" "$target" "$file_path" || true
    pause_for_user
}

deploy_deck() {
    local callsign
    read -r -p "Callsign: " callsign
    validate_username "$callsign" || { pause_for_user; return; }
    if command -v python3 &>/dev/null; then
        python3 "$SCRIPT_DIR/client/tui_chat.py" "$callsign" "$SERVER_HOST" "$SERVER_PORT" || true
    else
        echo "Python 3 is required for CyberDeck."
    fi
    pause_for_user
}

scan_ident() {
    local callsign
    read -r -p "Callsign to scan: " callsign
    validate_username "$callsign" || { pause_for_user; return; }
    if [ -f "$USERS_DB/${callsign}.pub" ]; then
        echo "Key on relay: $USERS_DB/${callsign}.pub ($(wc -c < "$USERS_DB/${callsign}.pub") bytes)"
    else
        echo "No key on relay for '$callsign'"
    fi
    if [ -f "$(eval echo ~/.ciphershell/$callsign/private.pem)" ]; then
        echo "Local keys exist for '$callsign'"
    else
        echo "No local keys for '$callsign'"
    fi
    bash "$SERVER_DIR/server.sh" status | grep -qi "listening" 2>/dev/null && \
        echo "Core is running - connectivity check possible" || \
        echo "Core is offline locally. Use 12) Jack into relay to query a remote relay."
    pause_for_user
}

jack_into() {
    local input relay chan callsign
    read -r -p "Relay address (host:port): " input
    relay="${input%%:*}"
    chan="${input##*:}"
    [[ "$relay" == "$chan" ]] && chan="$SERVER_PORT"
    validate_host "$relay" || { pause_for_user; return; }
    validate_port "$chan" || { pause_for_user; return; }
    read -r -p "Callsign: " callsign
    validate_username "$callsign" || { pause_for_user; return; }
    bash "$SCRIPT_DIR/client/interactive_chat.sh" "$callsign" "$relay" "$chan" || true
    pause_for_user
}

quick_deploy() {
    load_config
    echo "Quick Deploy"
    echo "1. Booting core on $SERVER_HOST:$SERVER_PORT"
    bash "$SERVER_DIR/server.sh" start "$SERVER_PORT" "$SERVER_HOST" "$DEFAULT_SERVER_BIND_HOST" || true
    echo
    echo "2. Enlisting demo idents"
    bash "$SCRIPT_DIR/client/register.sh" alice
    bash "$SCRIPT_DIR/client/register.sh" bob
    echo
    echo "3. Open a second terminal and run:"
    echo "   cd \"$SCRIPT_DIR\""
    echo "   bash client/client.sh login bob"
    echo
    echo "4. Then transmit a test pulse from this terminal:"
    echo "   bash client/send.sh alice bob \"hello bob\""
    echo
    echo "Tip: choose menu option 8 (CLI channel) to send a message."
    pause_for_user
}

read_core_log() {
    echo "Last 100 lines of $SERVER_LOG"
    echo "------------------------------------------"
    tail -n 100 "$SERVER_LOG" 2>/dev/null || echo "No core log yet."
    pause_for_user
}

wipe_data() {
    echo "This removes only testing data for alice and bob, downloads, and logs."
    read -r -p "Wipe testing data? [y/N]: " answer
    [[ "$answer" =~ ^[Yy]$ ]] || return
    bash "$SCRIPT_DIR/scripts/reset_demo_data.sh" || true
    pause_for_user
}

diagnostics() {
    while true; do
        load_config
        clear_screen
        echo "Diagnostics"
        echo "Active channel: $SERVER_PORT"
        echo "1) ss -tulnp | grep <port>"
        echo "2) tail -n 100 core.log"
        echo "3) ps aux | grep ciphershell"
        echo "4) pkill -f ciphershell"
        echo "5) ls -la ~/.ciphershell"
        echo "6) ls -la server/users.db"
        echo "7) Back"
        echo
        read -r -p "Select [1-7]: " choice
        case "$choice" in
            1)
                ss -tulnp | grep "$SERVER_PORT" || echo "No listener found on port $SERVER_PORT."
                pause_for_user
                ;;
            2)
                tail -n 100 "$SERVER_LOG" 2>/dev/null || echo "No server log yet."
                pause_for_user
                ;;
            3)
                ps aux | grep -i '[c]iphershell\|[s]ocket_mux\|client/client.sh' || true
                pause_for_user
                ;;
            4)
                echo "This can stop CipherShell launchers/listeners in this user session."
                read -r -p "Type YES to run 'pkill -f ciphershell': " confirm
                if [[ "$confirm" == "YES" ]]; then
                    pkill -f ciphershell || true
                fi
                pause_for_user
                ;;
            5)
                ls -la "$LOCAL_STORE" 2>/dev/null || echo "$LOCAL_STORE does not exist yet."
                pause_for_user
                ;;
            6)
                ls -la "$USERS_DB" 2>/dev/null || echo "$USERS_DB does not exist yet."
                pause_for_user
                ;;
            7)
                return
                ;;
            *)
                echo "Choose a number from 1 to 7."
                sleep 1
                ;;
        esac
    done
}

main_menu() {
    while true; do
        header
        echo "  -- Core   --"
        echo " 1) Boot core"
        echo " 2) Core status"
        echo " 3) Read core log"
        echo " 4) Shutdown core"
        echo
        echo "  -- Ident  --"
        echo " 5) Enlist identity"
        echo " 6) Scan identity"
        echo
        echo "  -- Link   --"
        echo " 7) Deploy CyberDeck"
        echo " 8) CLI channel"
        echo " 9) Eavesdrop channel"
        echo "10) Transmit pulse"
        echo "11) Upload file"
        echo "12) Jack into relay"
        echo
        echo "  -- Tools  --"
        echo "13) Quick deploy"
        echo "14) Diagnostics"
        echo "15) Wipe testing data"
        echo " 0) Exit"
        echo
        read -r -p "Select an option: " choice
        case "$choice" in
            1) boot_core ;;
            2) bash "$SERVER_DIR/server.sh" status; pause_for_user ;;
            3) read_core_log ;;
            4) bash "$SERVER_DIR/server.sh" stop || true; pause_for_user ;;
            5) enlist_ident ;;
            6) scan_ident ;;
            7) deploy_deck ;;
            8) cli_channel ;;
            9) eavesdrop_channel ;;
            10) transmit_pulse ;;
            11) upload_file ;;
            12) jack_into ;;
            13) quick_deploy ;;
            14) diagnostics ;;
            15) wipe_data ;;
            0) echo "Goodbye."; exit 0 ;;
            *) echo "Choose a menu option from the list."; sleep 1 ;;
        esac
    done
}

main_menu
