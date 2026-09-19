![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=Network+Operations+%7C+Automation+Platform;Laravel+13+%2B+Ansible+%E2%80%A2+Cisco+IOS)

# Network Automation Platform — Intelligent Configuration Management
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-NetworkOperations-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/NetworkOperations)

![Status](https://img.shields.io/badge/STATUS-COMPLETE-00FFCC?style=for-the-badge&logo=network&logoColor=0052CC)
![Laravel](https://img.shields.io/badge/Laravel_13-0052CC?style=for-the-badge&logo=laravel&logoColor=white)
![Ansible](https://img.shields.io/badge/Ansible-001F4D?style=for-the-badge&logo=ansible&logoColor=00FFCC)

---

## Overview
AutoConfigLab replaces traditional CRUD workflows with an intelligent guided wizard for network configuration management. Built with Laravel orchestration and Ansible execution for AI-assisted Cisco configuration generation and deployment.

* **Project:** AutoConfigLab v1.0
* **Framework:** Laravel 13 + Ansible
* **Core Engine:** Wizard-driven deployment with AI assistant
* **Design Pattern:** Intent-to-Configuration Automation

---

## Technical Stack

{
  "💻 Backend": [
    ![Laravel](https://img.shields.io/badge/Laravel_13-001F4D?style=flat-square&logo=laravel&logoColor=00FFCC),
    ![PHP](https://img.shields.io/badge/PHP_8.4-001F4D?style=flat-square&logo=php&logoColor=00FFCC),
    ![Ansible](https://img.shields.io/badge/Ansible-001F4D?style=flat-square&logo=ansible&logoColor=00FFCC)
  ],
  "🛡️ Automation": [
    ![Playbooks](https://img.shields.io/badge/Ansible_Playbooks-001F4D?style=flat-square&logoColor=00FFCC),
    ![AI](https://img.shields.io/badge/AI_Assistant-001F4D?style=flat-square&logoColor=00FFCC),
    ![Validation](https://img.shields.io/badge/Topology_Validation-001F4D?style=flat-square&logoColor=00FFCC)
  ],
  "🌐 Network": [
    ![Cisco](https://img.shields.io/badge/Cisco_IOS-001F4D?style=flat-square&logo=cisco&logoColor=00FFCC),
    ![SNMP](https://img.shields.io/badge/SNMP-001F4D?style=flat-square&logoColor=00FFCC),
    ![Grafana](https://img.shields.io/badge/Grafana-001F4D?style=flat-square&logo=grafana&logoColor=00FFCC)
  ]
}

---

## Architecture

* **Validation:** IP conflict detection, VLAN duplication check, configuration syntax validation
* **Rollback:** Snapshot before deploy, diff preview, one-click revert
* **Audit:** Deployment history, action logging, RBAC enforcement
* **Execution:** Async queue execution, job status tracking, failure recovery

---

## System Modules

#### 🌐 Core API — Laravel Controllers
RESTful API endpoints and web controllers handling authentication, authorization, and request validation.

#### 📡 Orchestration — Service Layer
`ConfigGenerationService`, `DeploymentService`, `ValidationService` — manages intent parsing, config generation, and execution orchestration.

#### 👥 AI Engine — `AIAssistantService`
Converts natural language to structured automation plans and provides recommendations from historical patterns.

#### 🖥️ Execution — Ansible Engine
Playbook rendering, inventory generation, and job execution with rollback and full audit trail.

---

## Feature Matrix

| Module | Description | Status |
|--------|-------------|--------|
| Dashboard | KPIs, charts, logs, recent deployments | ✅ Active |
| Wizard | Device → Goal → Inputs → Preview → Deploy | ✅ Active |
| Devices | Inventory profiles, credentials, history | ✅ Active |
| Inventories | Dynamic Ansible host/group data | ✅ Active |
| Templates | Reusable configs, presets, versioning | ✅ Active |
| Deployments | Async execution, replay, rollback | ✅ Active |
| Topology | Visual maps, simulation overlay | ✅ Active |
| AI Builder | Prompt-driven lab generation | ✅ Active |

---

## Installation and Execution

### Prerequisites
* PHP 8.4+, Composer, Ansible, MySQL

### Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed

# Ansible dependencies
ansible-galaxy collection install cisco.ios ansible.netcommon
```

### Execution
```bash
php artisan serve --host=127.0.0.1 --port=9001
```

### Demo Scenarios
1. Create VLAN with DHCP and ACL via wizard
2. Ask AI assistant for OSPF routing configuration
3. Trigger duplicate VLAN conflict and observe validation
4. Deploy in simulation mode, then rollback

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
