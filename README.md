![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=user%40PM-CyberSec%3A~%24+ls+--archive+%22NeonNet%22+--status%3DOPERATIONAL)

# # SYSTEM_OVERRIDE: [NEONNET_SECURE_MESSENGER]
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Encrypted_Mail-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-Linux%26Shell-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/Linux%26Shell)

![Status](https://img.shields.io/badge/STATUS-OPERATIONAL-00FFCC?style=for-the-badge&logo=lock&logoColor=0052CC)
![Stack](https://img.shields.io/badge/STACK-Bash_Python-0052CC?style=for-the-badge&logo=gnu-bash&logoColor=white)
![Encryption](https://img.shields.io/badge/ENCRYPTION-RSA_4096_AES_256_GCM-001F4D?style=for-the-badge&logoColor=00FFCC)

### > Hello, friend.
### > You are accessing the NeonNet CyberDeck — end-to-end encrypted messenger.

---

## [ ⟁ ] LOG_01: THE OBJECTIVE
End-to-end encrypted messaging and file transfer over a TCP relay. The server sees only ciphertext — zero knowledge, absolute privacy.

* $ **CODE_NAME=** NeonNet
* $ **STACK=** Bash + Python
* $ **ENCRYPTION=** RSA-4096 + AES-256-GCM hybrid
* $ **PROTOCOL=** TCP socket relay via Python AsyncIO

---

## [ ⚙ ] LOG_02: THE TOOLKIT (DECRYPTED)
This system is forged from battle-tested cryptographic primitives:

💻 **Core Stack**
![Bash](https://img.shields.io/badge/Bash-90%25-0052CC?style=for-the-badge&logo=gnu-bash&logoColor=white) ![Python](https://img.shields.io/badge/Python-10%25-0052CC?style=for-the-badge&logo=python&logoColor=white) ![OpenSSL](https://img.shields.io/badge/OpenSSL-3.x-001F4D?style=for-the-badge&logo=openssl&logoColor=00FFCC)

🛡️ **Cryptographic Modules**
![RSA](https://img.shields.io/badge/RSA_4096-ENABLED-00FFCC?style=for-the-badge&logoColor=0052CC) ![AES](https://img.shields.io/badge/AES_256_GCM-ACTIVE-00FFCC?style=for-the-badge&logoColor=0052CC) ![PSS](https://img.shields.io/badge/RSA_PSS_SHA256-SIGNING-00FFCC?style=for-the-badge&logoColor=0052CC)

🌐 **Network Protocol**
![TCP](https://img.shields.io/badge/TCP_Relay-ENABLED-00FFCC?style=for-the-badge&logoColor=0052CC) ![AsyncIO](https://img.shields.io/badge/Python_AsyncIO-VIRTUAL-00FFCC?style=for-the-badge&logoColor=0052CC) ![Curses](https://img.shields.io/badge/TUI_Curses-AUTOMATED-00FFCC?style=for-the-badge&logoColor=0052CC)

---

## [ 🛡️ ] LOG_03: DEFENSIVE ARCHITECTURE
The system is hardened against eavesdropping and traffic analysis:

* **{**
* **"Key Exchange":** [`RSA-4096 OAEP`],
* **"Message Encryption":** [`AES-256-GCM`],
* **"Digital Signatures":** [`RSA-PSS-SHA256`],
* **"Private Key Protection":** [`chmod 600`, `Local storage`],
* **"Server Knowledge":** [`Ciphertext only`, `Zero plaintext access`],
* **"Max File Size":** [`25 MB limit`]
* **}**

---

## [ 💾 ] LOG_04: SYSTEM MODULES (ARCHITECTURE)

```
                        ┌─────────────┐
                        │  CyberDeck   │
                        │  (curses UI) │
                        └──────┬──────┘
                               │ TCP (ciphertext only)
                        ┌──────┴──────┐
                        │     Core    │
                        │  Relay ASync│
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

#### 🌐 [CYBERDECK] > [curses_TUI]
> Split-terminal interface with 3 panes: Transcript, Composer, Syslog.
> Commands: `/connect`, `/disconnect`, `/to`, `/scan`, `/whois`, `/status`, `/clear`, `/help`, `/quit`

#### 📡 [CORE_RELAY] > [socket_mux.py]
> AsyncIO TCP server that relays encrypted payloads.
> Maintains connection state, user directory, and message queue.

#### 👥 [CLI_NODES] > [client_scripts]
> `register.sh`, `send.sh`, `send_file.sh`, `client.sh`, `interactive_chat.sh`

#### 🛡️ [CRYPTO_ENGINE] > [cipher.py]
> Client-side encryption: RSA-4096 keygen, AES-256-GCM en/decrypt, RSA-PSS signing.

---

## [ ⚙ ] LOG_05: LAUNCHER MENU & COMMANDS

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

  -- Ident  --
 5) Enlist identity        Register a new callsign (RSA keypair)
 6) Scan identity          Check if a callsign exists on relay

  -- Link   --
 7) Deploy CyberDeck       TUI split-terminal chat (curses)
 8) CLI channel            Interactive chat in terminal
 9) Eavesdrop channel      Listen for messages in background
10) Transmit pulse         Send a one-shot message
11) Upload file            Send an encrypted file
12) Jack into relay        Connect to a specific remote relay

  -- Tools  --
13) Quick deploy           Boot core + register demo users
14) Diagnostics            Network/process debugging
15) Wipe testing data      Remove demo data
 0) Exit
```

### CLI Commands
| Command | Description |
|---------|-------------|
| `bash server/server.sh start <port> <ip>` | Boot core relay |
| `bash client/register.sh <callsign>` | Enlist identity |
| `bash client/client.sh login <callsign> <ip> <port>` | Eavesdrop channel |
| `bash client/send.sh <from> <to> "<message>"` | Transmit encrypted pulse |
| `bash client/send_file.sh <from> <to> <file>` | Upload encrypted file |
| `bash client/interactive_chat.sh <callsign> <ip> <port>` | CLI channel |

---

## [ 🔍 ] LOG_06: PROJECT STRUCTURE

```
NeonNet/
├── ciphershell.sh              # Main launcher menu
├── client/
│   ├── tui_chat.py             # CyberDeck TUI (curses)
│   ├── interactive_chat.py     # CLI channel
│   ├── payload_tool.py         # Message/file builder
│   ├── client.sh               # Login/background listener
│   ├── register.sh             # Identity enlistment
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
│   └── generate_keys.sh        # Keypair forger
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

## [ ⌬ ] SYSTEM_ACCESS

* **Step 01 ->** `sudo apt install -y bash python3 python3-cryptography openssl procps iproute2 lsof`
* **Step 02 ->** `chmod +x ciphershell.sh client/*.sh crypto/*.sh server/*.sh scripts/*.sh tests/*.sh`
* **Step 03 ->** `./ciphershell.sh` — select option `13) Quick deploy`
* **Step 04 ->** Select `7) Deploy CyberDeck` to jack into the TUI
* **Step 05 ->** Select `14) Diagnostics` for network debugging

### Demo Scenario
1. Boot core: `bash server/server.sh start 500 0.0.0.0`
2. Enlist alice: `bash client/register.sh alice`
3. Enlist bob: `bash client/register.sh bob`
4. Eavesdrop (bob): `bash client/client.sh login bob 192.168.1.5 500`
5. Transmit pulse: `bash client/send.sh alice bob "Hello Bob, this is secure!"`
6. Upload file: `bash client/send_file.sh alice bob secret.txt`
7. Interactive: `bash client/interactive_chat.sh alice 192.168.1.5 500`
8. TUI: `python3 client/tui_chat.py alice 192.168.1.5 500`

---

## [ 🔧 ] LOG_07: TROUBLESHOOTING

```bash
# Core status
bash server/server.sh status

# Core log
tail -n 100 server.log

# Launcher diagnostics
./ciphershell.sh  → option 14 (Diagnostics)
```

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for detailed fault resolution.

---

## [ ✉ ] TRANSMIT_DATA
> **The truth is in the ciphertext. Decrypt your own destiny.**

* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Encrypted Mail:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Org:** IEEE Student Branch (Tech & R&D)

---

### > Goodbye, friend.
### > [EOF]
