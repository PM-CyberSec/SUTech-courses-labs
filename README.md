### **user@PM-CyberSec:~$ whoami --focus "Network Engineering" --project "IoT_Network_v1.0"**

# # SYSTEM_OVERRIDE: [IOT_SENSOR_NETWORK]

![Status](https://img.shields.io/badge/STATUS-OPERATIONAL-39FF14?style=for-the-badge&logo=network)
![Stack](https://img.shields.io/badge/STACK-CISCO_PACKET_TRACER-005571?style=for-the-badge&logo=cisco)
![Environment](https://img.shields.io/badge/ENV-SIMULATION-orange?style=for-the-badge&logo=linux)

### > Welcome, friend.
### > You are accessing the IoT Network Secure Topology.

---

## [ ⟁ ] LOG_01: THE OBJECTIVE
In a world of connected devices, isolation is the only defense. This project implements a secure enterprise-style IoT and sensor network using VLAN segmentation, inter-VLAN routing, and centralized monitoring.

* $ **CODE_NAME=** IoT_Network_Project
* $ **SIMULATION_PLATFORM=** Cisco Packet Tracer
* $ **CORE_ENGINE=** Multilayer Switch (3560) + VLAN Segmentation
* $ **DESIGN_PATTERN=** Hierarchical Enterprise Topology

---

## [ ⚙ ] THE TOOLKIT (DECRYPTED)
This project was built using enterprise networking protocols to ensure scalability and security:

💻 **Network Devices**
![Cisco](https://img.shields.io/badge/Cisco-005BA9?style=for-the-badge&logo=cisco) ![Switch](https://img.shields.io/badge/Multilayer_Switch-005BA9?style=for-the-badge) ![Router](https://img.shields.io/badge/Cisco_Router-005BA9?style=for-the-badge)

🛡️ **Security Modules**
![VLAN](https://img.shields.io/badge/VLAN_SEGMENTATION-ENABLED-green?style=for-the-badge) ![ACL](https://img.shields.io/badge/TRAFFIC_FILTERING-ACTIVE-blue?style=for-the-badge) ![INTER_VLAN](https://img.shields.io/badge/INTER_VLAN_ROUTING-CONFIGURED-purple?style=for-the-badge)

⬢ **Wireless & IoT**
![WiFi](https://img.shields.io/badge/IoT_WiFi-ENABLED-39FF14?style=for-the-badge) ![Sensors](https://img.shields.io/badge/IoT_Sensors-VIRTUAL-green?style=for-the-badge) ![DHCP](https://img.shields.io/badge/DHCP-AUTOMATED-orange?style=for-the-badge)

---

## [ 🛡️ ] LOG_02: DEFENSIVE ARCHITECTURE
The network is hardened against common misconfigurations using modern segmentation:

* **{**
* **"Isolation":** [`VLAN 10 (IoT Sensors)`, `VLAN 20 (Users)`, `VLAN 30 (Servers)`],
* **"Management":** [`VLAN 50 (Management Network)`],
* **"Routing":** [`Inter-VLAN via MLS`, `Gateway Redundancy`],
* **"Access Control":** [`ACL-based filtering`, `Restricted Management Access`]
* **}**

---

## [ 💾 ] LOG_03: SYSTEM MODULES (TOPOLOGY)

#### 🌐 [CORE_SWITCH] > [MLS_3560]
> Central routing and VLAN gateway.
> Handles inter-VLAN routing, DHCP services, and layer 3 switching.

#### 📡 [IoT_SEGMENT] > [VLAN_10]
> IoT sensor devices communicate via wireless access point.
> Subnet: 192.168.10.0/24 | Gateway: 192.168.10.1

#### 👥 [USER_SEGMENT] > [VLAN_20]
> User devices, PCs, and shared printer.
> Subnet: 192.168.20.0/24 | Gateway: 192.168.20.1

#### 🖥️ [SERVER_SEGMENT] > [VLAN_30]
> IoT Server and Network Controller.
> Subnet: 192.168.30.0/24 | Gateway: 192.168.30.1

#### ⚙️ [MANAGEMENT] > [VLAN_50]
> Dedicated admin network for network management.
> Subnet: 192.168.50.0/24 | Gateway: 192.168.50.2

---

## [ 📊 ] LOG_04: NETWORK TOPOLOGY

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

## [ ⚙ ] LOG_05: CONFIGURATION COMMANDS

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

### IoT Server
| Setting | Value |
|---------|-------|
| IP Address | 192.168.30.10 |
| Gateway | 192.168.30.1 |

---

## [ 🔍 ] LOG_06: VERIFICATION COMMANDS

```bash
show vlan brief           # VLAN verification
show interfaces trunk     # Trunk verification
show ip route             # Routing verification
show ip dhcp binding      # DHCP verification
show ip interface brief   # Interface status
show mac address-table    # MAC table
```

---

## [ ⌬ ] SYSTEM_ACCESS
* **Step 01 ->** `git clone <repo-url>`
* **Step 02 ->** Open `iot network project final.pkt` in Cisco Packet Tracer
* **Step 03 ->** Verify VLAN configuration with `show vlan brief`
* **Step 04 ->** Test inter-VLAN connectivity between segments
* **Step 05 ->** Verify DHCP pool allocation

---

## [ ✉ ] TRANSMIT_DATA
> **The network speaks louder than words. Review the topology.**

* **GitHub:** [Your GitHub Profile]
* **Project Files:** Packet Tracer (.pkt), Documentation (.md)

---

### > [EOF]
