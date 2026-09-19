![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=Advanced+Networks+%7C+IoT+Enterprise+Topology;VLAN+Segmentation+%E2%80%A2+Inter-VLAN+Routing+%E2%80%A2+Cisco+Packet+Tracer)

# IoT Sensor Network — Enterprise Topology with VLAN Segmentation
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-AdvancedNetworks-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/AdvancedNetworks)

![Status](https://img.shields.io/badge/STATUS-COMPLETE-00FFCC?style=for-the-badge&logo=network&logoColor=0052CC)
![Cisco](https://img.shields.io/badge/Cisco_Packet_Tracer-0052CC?style=for-the-badge&logo=cisco&logoColor=white)
![VLAN](https://img.shields.io/badge/VLAN_Segmentation-001F4D?style=for-the-badge&logoColor=00FFCC)

---

## Overview
Enterprise-style IoT and sensor network implemented in Cisco Packet Tracer using VLAN segmentation, inter-VLAN routing, and centralized monitoring. Designed with hierarchical topology for isolation, scalability, and manageability.

* **Project:** IoT Network Project — Enterprise Topology
* **Platform:** Cisco Packet Tracer
* **Core Device:** Multilayer Switch 3560 with VLAN segmentation
* **Architecture:** Hierarchical enterprise network with segmented broadcast domains

---

## Technical Stack

{
  "💻 Network Devices": [
    ![Cisco](https://img.shields.io/badge/Cisco-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC),
    ![Switch](https://img.shields.io/badge/Multilayer_Switch-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC),
    ![Router](https://img.shields.io/badge/Cisco_Router-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC)
  ],
  "🛡️ Security Modules": [
    ![VLAN](https://img.shields.io/badge/VLAN_Segmentation-001F4D?style=flat-square&logoColor=00FFCC),
    ![ACL](https://img.shields.io/badge/Traffic_Filtering-001F4D?style=flat-square&logoColor=00FFCC),
    ![InterVLAN](https://img.shields.io/badge/Inter_VLAN_Routing-001F4D?style=flat-square&logoColor=00FFCC)
  ],
  "⬢ Wireless & IoT": [
    ![WiFi](https://img.shields.io/badge/IoT_WiFi-001F4D?style=flat-square&logo=wifi&logoColor=00FFCC),
    ![Sensors](https://img.shields.io/badge/IoT_Sensors-001F4D?style=flat-square&logoColor=00FFCC),
    ![DHCP](https://img.shields.io/badge/DHCP-001F4D?style=flat-square&logoColor=00FFCC)
  ]
}

---

## Network Architecture

| Segment | VLAN | Purpose | Subnet | Gateway |
|---------|------|---------|--------|---------|
| IoT Sensors | 10 | Wireless IoT sensor devices | 192.168.10.0/24 | 192.168.10.1 |
| Users | 20 | End-user devices and printer | 192.168.20.0/24 | 192.168.20.1 |
| Servers | 30 | IoT Server and Network Controller | 192.168.30.0/24 | 192.168.30.1 |
| Management | 50 | Administrative access | 192.168.50.0/24 | 192.168.50.2 |

* **Isolation:** VLAN-based segmentation per functional domain
* **Routing:** Inter-VLAN routing via multilayer switch with gateway redundancy
* **Access Control:** ACL-based traffic filtering and restricted management access

---

## System Modules

#### 🌐 Core Switch — MLS 3560
Central routing and VLAN gateway handling inter-VLAN routing, DHCP services, and Layer 3 switching.

#### 📡 IoT Segment — VLAN 10
Wireless IoT sensor devices connected via access point. SSID: `IoT-WiFi`.

#### 👥 User Segment — VLAN 20
User workstations (PC0: 192.168.20.10, PC1: 192.168.20.11) and shared printer (192.168.20.12).

#### 🖥️ Server Segment — VLAN 30
IoT Server (192.168.30.10) and Network Controller (192.168.30.20).

#### ⚙️ Management — VLAN 50
Dedicated administrative network (Router Management: 192.168.50.5).

---

## Network Topology

### IP Addressing Plan
| Device | IP Address |
|--------|------------|
| MLS VLAN10 | 192.168.10.1 |
| MLS VLAN20 | 192.168.20.1 |
| MLS VLAN30 | 192.168.30.1 |
| MLS VLAN50 | 192.168.50.2 |
| IoT Server | 192.168.30.10 |
| Network Controller | 192.168.30.20 |
| PC0 | 192.168.20.10 |
| PC1 | 192.168.20.11 |
| Printer | 192.168.20.12 |
| Router Management | 192.168.50.5 |

### WAN Router Links
| Link | Network |
|------|---------|
| R0 ↔ R1 | 192.168.1.0/30 |
| R1 ↔ R2 | 192.168.1.4/30 |
| R0 ↔ R2 | 192.168.1.8/30 |

---

## Configuration

### Multilayer Switch (Core)
```bash
enable
conf t
vlan 10
name IoT-Sensors
vlan 20
name Users
vlan 30
name Servers
vlan 50
name Management
interface vlan 10
ip address 192.168.10.1 255.255.255.0
no shutdown
ip routing
ip dhcp pool IOT
network 192.168.10.0 255.255.255.0
default-router 192.168.10.1
dns-server 192.168.30.10
```

### Access Point
| Setting | Value |
|---------|-------|
| SSID | IoT-WiFi |
| VLAN | 10 |

---

## Verification

```bash
show vlan brief           # VLAN verification
show interfaces trunk     # Trunk verification
show ip route             # Routing verification
show ip dhcp binding      # DHCP verification
show ip interface brief   # Interface status
show mac address-table    # MAC table
```

---

## Getting Started

1. Clone the branch: `git clone -b AdvancedNetworks https://github.com/PM-CyberSec/SUTech-courses-labs.git`
2. Open `iot network project final.pkt` in Cisco Packet Tracer
3. Verify VLAN configuration with `show vlan brief`
4. Test inter-VLAN connectivity between segments
5. Verify DHCP pool allocation

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
