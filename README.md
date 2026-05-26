![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=user%40PM-CyberSec%3A~%24+whoami+--focus+%22Network+Engineering%22+--project+%22IoT_Network_v1.0%22)

# # SYSTEM_OVERRIDE: [IOT_SENSOR_NETWORK]
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Encrypted_Mail-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-AdvancedNetworks-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/AdvancedNetworks)

![Status](https://img.shields.io/badge/STATUS-OPERATIONAL-00FFCC?style=for-the-badge&logo=network&logoColor=0052CC)
![Cisco](https://img.shields.io/badge/Cisco_Packet_Tracer-0052CC?style=for-the-badge&logo=cisco&logoColor=white)
![VLAN](https://img.shields.io/badge/VLAN_Segmentation-001F4D?style=for-the-badge&logoColor=00FFCC)

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
This project was built using enterprise networking protocols:

{<br>
  "💻 Network Devices": [
    ![Cisco](https://img.shields.io/badge/Cisco-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC),
    ![Switch](https://img.shields.io/badge/Multilayer_Switch-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC),
    ![Router](https://img.shields.io/badge/Cisco_Router-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC)
  ],
<br>
  "🛡️ Security Modules": [
    ![VLAN](https://img.shields.io/badge/VLAN_Segmentation-001F4D?style=flat-square&logoColor=00FFCC),
    ![ACL](https://img.shields.io/badge/Traffic_Filtering-001F4D?style=flat-square&logoColor=00FFCC),
    ![InterVLAN](https://img.shields.io/badge/Inter_VLAN_Routing-001F4D?style=flat-square&logoColor=00FFCC)
  ],
<br>
  "⬢ Wireless & IoT": [
    ![WiFi](https://img.shields.io/badge/IoT_WiFi-001F4D?style=flat-square&logo=wifi&logoColor=00FFCC),
    ![Sensors](https://img.shields.io/badge/IoT_Sensors-001F4D?style=flat-square&logoColor=00FFCC),
    ![DHCP](https://img.shields.io/badge/DHCP-001F4D?style=flat-square&logoColor=00FFCC)
  ]<br>
}

---

## [ 🛡️ ] LOG_02: DEFENSIVE_ARCHITECTURE
The network is hardened against common misconfigurations using modern segmentation:

* **{**
* **"Isolation":** [`VLAN 10 (IoT Sensors)`, `VLAN 20 (Users)`, `VLAN 30 (Servers)`],
* **"Management":** [`VLAN 50 (Management Network)`],
* **"Routing":** [`Inter-VLAN via MLS`, `Gateway Redundancy`],
* **"Access Control":** [`ACL-based filtering`, `Restricted Management Access`]
* **}**

---

## [ 💾 ] LOG_03: SYSTEM_MODULES

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

## [ 📊 ] LOG_04: NETWORK_TOPOLOGY

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

## [ ⚙ ] LOG_05: CONFIGURATION_COMMANDS

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

## [ 🔍 ] LOG_06: VERIFICATION_COMMANDS

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

* **Step 01 ->** `git clone -b AdvancedNetworks https://github.com/PM-CyberSec/SUTech-courses-labs.git`
* **Step 02 ->** Open `iot network project final.pkt` in Cisco Packet Tracer
* **Step 03 ->** Verify VLAN configuration with `show vlan brief`
* **Step 04 ->** Test inter-VLAN connectivity between segments
* **Step 05 ->** Verify DHCP pool allocation

---

## [ ✉ ] TRANSMIT_DATA
> **The network speaks louder than words. Review the topology.**

* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Encrypted Mail:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Org:** IEEE Student Branch (Tech & R&D)

---

### > Goodbye, friend.
### > [EOF]
