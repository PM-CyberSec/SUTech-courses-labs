![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=Web+Security+%7C+Secure+Product+Management;Laravel+11+%E2%80%A2+PHP+8.2+%E2%80%A2+OWASP)

# Inventory Vault — Secure Product Management System
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-WebSecurity-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/WebSecurity)

![Status](https://img.shields.io/badge/STATUS-COMPLETE-00FFCC?style=for-the-badge&logo=target&logoColor=0052CC)
![Laravel](https://img.shields.io/badge/Laravel_11-0052CC?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP_8.2-001F4D?style=for-the-badge&logo=php&logoColor=00FFCC)

---

## Overview
Secure product and asset management application built with Laravel 11 and PHP 8.2. Implements OWASP-aligned controls, robust input validation, and relational data integrity with a modern responsive interface.

* **Project:** Inventory Vault v1.0
* **Framework:** Laravel 11 / PHP 8.2
* **Database:** MySQL with migration-based schema
* **Security Focus:** CSRF, SQL injection prevention, XSS mitigation, access control

---

## Technical Stack

{
  "💻 Backend": [
    ![Laravel](https://img.shields.io/badge/Laravel-001F4D?style=flat-square&logo=laravel&logoColor=00FFCC),
    ![PHP](https://img.shields.io/badge/PHP-001F4D?style=flat-square&logo=php&logoColor=00FFCC),
    ![MySQL](https://img.shields.io/badge/MySQL-001F4D?style=flat-square&logo=mysql&logoColor=00FFCC)
  ],
  "🛡️ Security": [
    ![CSRF](https://img.shields.io/badge/CSRF_Protection-001F4D?style=flat-square&logoColor=00FFCC),
    ![Eloquent](https://img.shields.io/badge/Eloquent_ORM-001F4D?style=flat-square&logo=laravel&logoColor=00FFCC),
    ![Bcrypt](https://img.shields.io/badge/Bcrypt_Hashing-001F4D?style=flat-square&logoColor=00FFCC)
  ],
  "⬢ Frontend": [
    ![Bootstrap](https://img.shields.io/badge/Bootstrap-001F4D?style=flat-square&logo=bootstrap&logoColor=00FFCC),
    ![JavaScript](https://img.shields.io/badge/JavaScript-001F4D?style=flat-square&logo=javascript&logoColor=00FFCC),
    ![CSS3](https://img.shields.io/badge/CSS3-001F4D?style=flat-square&logo=css3&logoColor=00FFCC)
  ]
}

---

## Security Architecture

* **Protection:** CSRF tokens, SQL injection prevention via Eloquent ORM, XSS output escaping
* **Authentication:** Secure session management, Bcrypt password hashing
* **Data Integrity:** Foreign key constraints, migration-based schema versioning
* **UI Stability:** Managed z-index stacking, portal-based dropdown rendering

---

## System Modules

#### 📦 Asset Management — CRUD
Complete lifecycle management for enterprise assets — create, read, update, and delete with validation and real-time feedback.

#### 🏷️ Tag Orchestration — Tom Select Integration
Advanced multi-tagging system with searchable inputs and on-the-fly tag creation for rapid categorization.

#### 🔍 Dynamic Filtering — Query Builder
High-speed filtering by price, category, and keywords with optimized database queries.

#### 🧪 Developer Sandbox — Sidebar Navigation
Dedicated environment for testing algorithmic logic including even/prime calculations and utility tools.

---

## Repository Structure

```
WebSecurity/
├── app/
│   ├── Http/Controllers/
│   ├── Models/
│   └── Providers/
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
│   ├── css/
│   └── images/
├── resources/
│   ├── css/
│   ├── js/
│   └── views/
├── routes/
├── storage/
├── tests/
└── vendor/
```

---

## Installation and Execution

### Prerequisites
* PHP 8.2+, Composer, Node.js, MySQL

### Setup
```bash
git clone -b WebSecurity https://github.com/PM-CyberSec/SUTech-courses-labs.git
cd SUTech-courses-labs
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

### Execution
```bash
php artisan serve
```

---

## Technical Highlights

* **Secure ORM Usage** — SQL injection prevention through Laravel Eloquent query builder
* **Multi-Tagging System** — Advanced categorization with searchable select inputs
* **Responsive UI** — Modern interface with stable layout and stacking context management
* **Algorithmic Sandbox** — Built-in development tools for mathematical logic testing

---

## Challenges and Solutions

| Challenge | Solution |
|-----------|----------|
| Z-index stacking with layered components | Portal-based dropdowns with managed stacking contexts |
| Tag search performance | Debounced AJAX search with indexed queries |

---

## Learning Outcomes

* Laravel security patterns — CSRF, SQL injection prevention, XSS escaping
* UI architecture with stable layout management
* Database integrity through migration-based schema design

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
