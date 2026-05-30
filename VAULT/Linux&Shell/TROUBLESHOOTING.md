![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=user%40PM-CyberSec%3A~%24+cat+TROUBLESHOOTING.md+--section%3DDIAGNOSTICS)

# # SYSTEM_OVERRIDE: [NEONNET_TROUBLESHOOTING]
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Encrypted_Mail-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-Linux%26Shell-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/Linux%26Shell)

![Status](https://img.shields.io/badge/STATUS-DIAGNOSTIC-00FFCC?style=for-the-badge&logo=lock&logoColor=0052CC)
![Stack](https://img.shields.io/badge/STACK-Bash_Python-0052CC?style=for-the-badge&logo=gnu-bash&logoColor=white)
![Mode](https://img.shields.io/badge/MODE-DEBUG_MODE-001F4D?style=for-the-badge&logoColor=00FFCC)

### > Hello, friend.
### > You are jacked into the NeonNet diagnostic terminal.

---

## [ ⟁ ] LOG_01: COMMON FAULTS
When the system misbehaves, run these diagnostics before diving deeper:

### Permission Denied
```bash
chmod +x ciphershell.sh
./ciphershell.sh
# or
bash ciphershell.sh
```

### Server Is Not Running
```bash
bash server/server.sh start 500 127.0.0.1
bash server/server.sh status
tail -n 100 server.log
```

---

## [ 🛡️ ] LOG_02: NETWORK & PORT FAULTS

### Port Already In Use
```bash
# Identify the offender
ss -tulnp | grep 500

# Safe shutdown
bash server/server.sh stop

# Or switch ports
bash server/server.sh start 7000 127.0.0.1
```
> The selected port persists in `config/ciphershell.conf` so clients use it automatically.

### Recipient Offline
```
Message could not be delivered because recipient is offline.
```
The target must open a listener first:
```bash
bash client/client.sh login bob
bash client/send.sh alice bob "hello"
```

---

## [ 💾 ] LOG_03: IDENTITY & KEY FAULTS

### User Is Not Registered
```bash
bash client/register.sh alice
```
**Valid callsigns:** lowercase letters, numbers, underscore, dash
```
alice       ✅
bob_1       ✅
student-01  ✅
Alice       ❌
bad user    ❌
admin!      ❌
```

### Keys Already Exist
```
User already exists. Existing keys will be reused.
```
Private keys are never overwritten during normal registration. To reset demo data:
```bash
bash scripts/reset_demo_data.sh
```

---

## [ ⚙ ] LOG_04: LOG & FILE FAULTS

### Logs Are Empty
Start the server through the server script or launcher:
```bash
bash server/server.sh start 500 127.0.0.1
tail -n 100 server.log
```
**Useful log events:**
- `server started`
- `user connected`
- `message received`
- `message forwarded`
- `recipient offline`
- `error occurred`

> Plaintext messages are never logged.

### File Was Not Received
```bash
ls -la downloads/bob
tail -n 100 bob_client.log
tail -n 100 server.log
```
Received files are saved under `downloads/<username>/`. Existing files are not overwritten.

---

## [ ⌬ ] SYSTEM_ACCESS (EMERGENCY)

```bash
ps aux | grep ciphershell
pkill -f ciphershell
```
> Prefer the safe shutdown first:
```bash
bash server/server.sh stop
```

### Quick Diagnostics
| Symptom | Command |
|---------|---------|
| Core unresponsive | `bash server/server.sh status` |
| Port conflict | `ss -tulnp \| grep <port>` |
| Connection refused | `nc -zv <host> <port>` |
| Key corruption | `ls -la ~/.ciphershell/<callsign>/` |
| Process leak | `ps aux \| grep python` |
| Network debug | `./ciphershell.sh → option 14` |

---

## [ ✉ ] TRANSMIT_DATA
> **When in doubt, check the logs first. When in crisis, reboot the core.**

* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Encrypted Mail:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Org:** IEEE Student Branch (Tech & R&D)

---

### > Goodbye, friend.
### > [EOF]
