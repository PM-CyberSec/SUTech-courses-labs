![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=SUTech+Lab+Vault+%7C+Academic+Portfolio+Archive;Branch-Indexed+%7C+10+Projects+%7C+6+Technical+Domains;El+Sewedy+University+of+Technology)

# SUTech Labs Vault — Academic Portfolio Archive
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Repo](https://img.shields.io/badge/REPO-SUTech_Labs-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs)

![Status](https://img.shields.io/badge/STATUS-ACTIVE-00FFCC?style=for-the-badge&logo=target&logoColor=0052CC)
![Branches](https://img.shields.io/badge/BRANCHES-11-0052CC?style=for-the-badge&logo=git&logoColor=white)
![Domains](https://img.shields.io/badge/DOMAINS-6-001F4D?style=for-the-badge&logoColor=00FFCC)

---

## Overview
This repository is a **branch-indexed portfolio vault** where each branch is an independent academic or technical project. The `main` branch serves as the landing page and navigation hub; source code for each project lives exclusively on its own branch.

* **Architecture:** One branch per project — zero cross-contamination
* **Main Branch Role:** Index and navigation only
* **Project Isolation:** Each branch is self-contained with its own codebase and documentation
* **Design Pattern:** Git branches as portfolio projects for modular versioning

---

## Technical Domains
The vault spans six core domains of applied computer science and cybersecurity:

{
  "💻 Networking": [
    ![Cisco](https://img.shields.io/badge/Cisco-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC),
    ![VLAN](https://img.shields.io/badge/VLAN_Segmentation-001F4D?style=flat-square&logoColor=00FFCC),
    ![WAN](https://img.shields.io/badge/WAN_Routing-001F4D?style=flat-square&logoColor=00FFCC)
  ],
  "🛡️ Security": [
    ![Wazuh](https://img.shields.io/badge/Wazuh-001F4D?style=flat-square&logo=wazuh&logoColor=00FFCC),
    ![Suricata](https://img.shields.io/badge/Suricata-001F4D?style=flat-square&logo=suricata&logoColor=00FFCC),
    ![Forensics](https://img.shields.io/badge/Digital_Forensics-001F4D?style=flat-square&logoColor=00FFCC),
    ![WebSec](https://img.shields.io/badge/Web_Security-001F4D?style=flat-square&logoColor=00FFCC)
  ],
  "⬢ Programming": [
    ![Python](https://img.shields.io/badge/Python-001F4D?style=flat-square&logo=python&logoColor=00FFCC),
    ![Java](https://img.shields.io/badge/Java-001F4D?style=flat-square&logo=openjdk&logoColor=00FFCC),
    ![PHP](https://img.shields.io/badge/PHP-001F4D?style=flat-square&logo=php&logoColor=00FFCC),
    ![JavaScript](https://img.shields.io/badge/JavaScript-001F4D?style=flat-square&logo=javascript&logoColor=00FFCC)
  ],
  "▤ Databases": [
    ![SQL](https://img.shields.io/badge/SQL-001F4D?style=flat-square&logo=postgresql&logoColor=00FFCC),
    ![DBMS](https://img.shields.io/badge/DBMS-001F4D?style=flat-square&logoColor=00FFCC)
  ],
  "▦ Linux & Shell": [
    ![Linux](https://img.shields.io/badge/Linux-001F4D?style=flat-square&logo=linux&logoColor=00FFCC),
    ![Bash](https://img.shields.io/badge/Bash-001F4D?style=flat-square&logo=gnu-bash&logoColor=00FFCC)
  ],
  "⊞ Web Development": [
    ![Laravel](https://img.shields.io/badge/Laravel-001F4D?style=flat-square&logo=laravel&logoColor=00FFCC),
    ![HTML](https://img.shields.io/badge/HTML_CSS-001F4D?style=flat-square&logo=html5&logoColor=00FFCC)
  ]
}

---

## Branch Index

| Branch | Domain | Description |
|--------|--------|-------------|
| `AdvancedNetworks` | Networking | Enterprise IoT network topology — VLAN segmentation, inter-VLAN routing, multilayer switching |
| `DBMS` | Databases | Database management system — SQL, schema design, query optimization |
| `DigitalForensics` | Security | AI-assisted network forensics — ML-based anomaly detection on PCAP data |
| `Linux&Shell` | Linux / Shell | Linux administration and shell scripting — NeonNet secure messenger |
| `NetworkOperations` | Networking | Network automation platform — Laravel + Ansible orchestration |
| `NetworkingBasics` | Networking | Foundational networking concepts — subnetting, routing, switching |
| `OOP-JAVA` | Programming | Object-oriented programming in Java — design patterns, data structures |
| `Python` | Programming | Python scripting, automation, and tool development |
| `WebProgramming` | Web Dev | Web applications — HTML, CSS, PHP, JavaScript |
| `WebSecurity` | Security | Web application security — OWASP, secure coding, Laravel hardening |

---

## VAULT Directory

The [`VAULT/`](./VAULT/) directory is a **flat mirror** of all branches, consolidated for tooling compatibility. It allows language servers, dependency scanners, and CI systems to index every project in a single checkout without branch switching.

* **Mirror Source:** Each subfolder corresponds to a branch of the same name
* **Authoritative Source:** Branches remain the single source of truth
* **Purpose:** Enables cross-project analysis, dependency graphs, and IDE indexing
* **Sync Policy:** `VAULT/` is updated when branches are added or updated

---

## Getting Started

```bash
# Clone the vault
git clone https://github.com/PM-CyberSec/SUTech-courses-labs.git

# List all projects
git branch -a

# Checkout a specific project
git checkout <branch-name>
```

For branches with special characters:

```bash
git checkout "Linux&Shell"
```

To fetch a specific branch without switching:

```bash
git fetch origin <branch-name>
git checkout <branch-name>
```

---

## Featured Projects

#### 🛡️ Digital Forensics — [Branch: DigitalForensics](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/DigitalForensics)
![Python](https://img.shields.io/badge/Python-001F4D?style=flat-square&logo=python&logoColor=white) ![Scikit-Learn](https://img.shields.io/badge/Scikit--Learn-0052CC?style=flat-square&logo=scikit-learn) ![Suricata](https://img.shields.io/badge/Suricata-00FFCC?style=flat-square&logoColor=001F4D)
ML-based network forensics pipeline for C2 and data exfiltration detection from PCAP data.

#### 🌐 Advanced Networks — [Branch: AdvancedNetworks](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/AdvancedNetworks)
![Cisco](https://img.shields.io/badge/Cisco-001F4D?style=flat-square&logo=cisco&logoColor=white) ![Packet Tracer](https://img.shields.io/badge/Packet_Tracer-0052CC?style=flat-square) ![VLAN](https://img.shields.io/badge/VLAN-00FFCC?style=flat-square&logoColor=001F4D)
Enterprise IoT sensor network with multilayer switching, VLAN segmentation, and WAN routing.

#### 🖥️ Web Security — [Branch: WebSecurity](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/WebSecurity)
![Laravel](https://img.shields.io/badge/Laravel-001F4D?style=flat-square&logo=laravel&logoColor=white) ![PHP](https://img.shields.io/badge/PHP-0052CC?style=flat-square&logo=php) ![OWASP](https://img.shields.io/badge/OWASP-00FFCC?style=flat-square&logoColor=001F4D)
Secure product management system with Laravel hardening, OWASP-aligned controls, and input validation.

#### 📦 Database Systems — [Branch: DBMS](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/DBMS)
![SQL](https://img.shields.io/badge/SQL-001F4D?style=flat-square&logo=postgresql&logoColor=white) ![Database](https://img.shields.io/badge/Database-0052CC?style=flat-square) ![Schema](https://img.shields.io/badge/Schema_Design-00FFCC?style=flat-square&logoColor=001F4D)
Relational database design for an online purchase platform — 3NF normalization and query optimization.

---

## Notes

* Each branch is independently versioned and not synced with `main` beyond this index
* No cross-branch dependencies — each project is standalone
* All work conducted in controlled, authorized academic environments
* Repository reflects completed coursework and independent effort

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
