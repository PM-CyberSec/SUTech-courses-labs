![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=Linux+%26+Shell+%7C+NeonNet+Secure+Messenger;Bash+%2B+Python+%E2%80%A2+RSA-4096+%E2%80%A2+AES-256-GCM)

# NeonNet — End-to-End Encrypted Messenger over TCP Relay
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-Linux%26Shell-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/Linux%26Shell)

![Status](https://img.shields.io/badge/STATUS-COMPLETE-00FFCC?style=for-the-badge&logo=lock&logoColor=0052CC)
![Stack](https://img.shields.io/badge/STACK-Bash_Python-0052CC?style=for-the-badge&logo=gnu-bash&logoColor=white)
![Encryption](https://img.shields.io/badge/ENCRYPTION-RSA_4096_AES_256_GCM-001F4D?style=for-the-badge&logoColor=00FFCC)

---

## Overview
End-to-end encrypted messaging and file transfer system over a TCP relay. The server handles only ciphertext to ensure zero-knowledge privacy between clients.

* **Project:** NeonNet — Secure Messenger
* **Stack:** Bash + Python
* **Encryption:** RSA-4096 + AES-256-GCM hybrid
* **Protocol:** TCP socket relay via Python AsyncIO
* **File Limit:** 25 MB per transfer

---

## Technical Stack

**Core Stack**
![Bash](https://img.shields.io/badge/Bash-90%25-0052CC?style=for-the-badge&logo=gnu-bash&logoColor=white) ![Python](https://img.shields.io/badge/Python-10%25-0052CC?style=for-the-badge&logo=python&logoColor=white) ![OpenSSL](https://img.shields.io/badge/OpenSSL-3.x-001F4D?style=for-the-badge&logo=openssl&logoColor=00FFCC)

**Cryptographic Modules**
![RSA](https://img.shields.io/badge/RSA_4096-ENABLED-00FFCC?style=for-the-badge&logoColor=0052CC) ![AES](https://img.shields.io/badge/AES_256_GCM-ACTIVE-00FFCC?style=for-the-badge&logoColor=0052CC) ![PSS](https://img.shields.io/badge/RSA_PSS_SHA256-SIGNING-00FFCC?style=for-the-badge&logoColor=0052CC)

**Network Protocol**
![TCP](https://img.shields.io/badge/TCP_Relay-ENABLED-00FFCC?style=for-the-badge&logoColor=0052CC) ![AsyncIO](https://img.shields.io/badge/Python_AsyncIO-ENABLED-00FFCC?style=for-the-badge&logoColor=0052CC) ![Curses](https://img.shields.io/badge/TUI_Curses-ENABLED-00FFCC?style=for-the-badge&logoColor=0052CC)

---

## Architecture

* **Key Exchange:** RSA-4096 OAEP
* **Message Encryption:** AES-256-GCM
* **Digital Signatures:** RSA-PSS-SHA256
* **Private Key Protection:** `chmod 600`, local storage only
* **Server Knowledge:** Ciphertext only — no plaintext access

```
                        ┌─────────────┐
                        │  CyberDeck   │
                        │  (curses UI) │
                        └──────┬──────┘
                               │ TCP (ciphertext only)
                        ┌──────┴──────┐
                        │     Core    │
                        │  Relay Async│
                        │  Server     │
                        └──────┬──────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
   ┌──────┴──────┐     ┌──────┴──────┐     ┌──────┴──────┐
   │  CLI Node   │     │  CLI Node   │     │  CLI Node   │
   │  (alice)    │     │  (bob)      │     │  (eve)      │
   └─────────────┘     └─────────────┘     └─────────────┘
         │                    │                    │
   ┌─────┴──────┐      ┌─────┴──────┐      ┌─────┴──────┐
   │ private.pem│      │ private.pem│      │ private.pem│
   │ public.pem │      │ public.pem │      │ public.pem │
   └────────────┘      └────────────┘      └────────────┘
```

#### 🌐 CyberDeck — Curses TUI
Split-terminal interface with 3 panes: Transcript, Composer, Syslog. Commands: `/connect`, `/disconnect`, `/to`, `/scan`, `/whois`, `/status`, `/clear`, `/help`, `/quit`.

#### 📡 Core Relay — `socket_mux.py`
AsyncIO TCP server that relays encrypted payloads and maintains connection state, user directory, and message queue.

#### 👥 CLI Nodes — Client Scripts
`register.sh`, `send.sh`, `send_file.sh`, `client.sh`, `interactive_chat.sh`.

#### 🛡️ Crypto Engine — `cipher.py`
Client-side encryption: RSA-4096 key generation, AES-256-GCM encryption/decryption, RSA-PSS signing.

---

## Launcher Menu

```
$ ./ciphershell.sh

============================================
            NeonNet CyberDeck
============================================

  -- Core   --
 1) Boot core              Start the relay server
 2) Core status            Check if server is running
 3) Read core log          View server log
 4) Shutdown core          Stop the relay server

  -- Identity  --
 5) Enlist identity        Register a new callsign (RSA keypair)
 6) Scan identity          Check if a callsign exists on relay

  -- Communication  --
 7) Deploy CyberDeck       TUI split-terminal chat (curses)
 8) CLI channel            Interactive chat in terminal
 9) Eavesdrop channel      Listen for messages in background
10) Transmit pulse         Send a one-shot message
11) Upload file            Send an encrypted file
12) Connect to relay       Connect to a specific remote relay

  -- Tools  --
13) Quick deploy           Boot core + register demo users
14) Diagnostics            Network/process debugging
15) Wipe testing data      Remove demo data
 0) Exit
```

### CLI Commands
| Command | Description |
|---------|-------------|
| `bash server/server.sh start <port> <ip>` | Start core relay |
| `bash client/register.sh <callsign>` | Register identity |
| `bash client/client.sh login <callsign> <ip> <port>` | Background listener |
| `bash client/send.sh <from> <to> "<message>"` | Send encrypted message |
| `bash client/send_file.sh <from> <to> <file>` | Send encrypted file |
| `bash client/interactive_chat.sh <callsign> <ip> <port>` | Interactive CLI session |

---

## Project Structure

```
NeonNet/
├── ciphershell.sh              # Main launcher menu
├── client/
│   ├── tui_chat.py             # CyberDeck TUI (curses)
│   ├── interactive_chat.py     # CLI channel
│   ├── payload_tool.py         # Message/file builder
│   ├── client.sh               # Login/background listener
│   ├── register.sh             # Identity registration
│   ├── send.sh                 # Transmit message
│   ├── send_file.sh            # Upload file
│   ├── listener.sh             # Background watcher
│   └── interactive_chat.sh     # Interactive CLI launcher
├── server/
│   ├── server.sh               # Core control script
│   ├── socket_mux.py           # AsyncIO relay server
│   └── users.db/               # Public key storage
├── crypto/
│   ├── cipher.py               # Python crypto engine
│   ├── encrypt.sh              # Encryption wrapper
│   ├── decrypt.sh              # Decryption wrapper
│   └── generate_keys.sh        # Keypair generation
├── lib/
│   └── common.sh               # Shared functions
├── config/
│   └── ciphershell.conf        # Configuration
├── scripts/
│   └── reset_demo_data.sh      # Demo cleanup
├── tests/
│   └── run_tests.sh            # Test suite
└── downloads/                  # Received files
```

---

## Getting Started

### Prerequisites
```bash
sudo apt install -y bash python3 python3-cryptography openssl procps iproute2 lsof
chmod +x ciphershell.sh client/*.sh crypto/*.sh server/*.sh scripts/*.sh tests/*.sh
```

### Quick Start
```bash
./ciphershell.sh  # Select option 13) Quick deploy
# Then select 7) Deploy CyberDeck for the TUI
```

### Demo Scenario
```bash
bash server/server.sh start 500 0.0.0.0
bash client/register.sh alice
bash client/register.sh bob
bash client/client.sh login bob 192.168.1.5 500
bash client/send.sh alice bob "Hello Bob, this is secure!"
bash client/send_file.sh alice bob secret.txt
bash client/interactive_chat.sh alice 192.168.1.5 500
python3 client/tui_chat.py alice 192.168.1.5 500
```

---

## Troubleshooting

```bash
bash server/server.sh status
tail -n 100 server.log
./ciphershell.sh  # → option 14 (Diagnostics)
```

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for detailed fault resolution.

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
