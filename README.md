![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=Database+Management+%7C+Online+Purchase+System;MySQL+%E2%80%A2+3NF+Normalization+%E2%80%A2+Java+GUI)

# Online Purchase System — Relational Database Management
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Email-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-DBMS-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/DBMS)

![Status](https://img.shields.io/badge/STATUS-COMPLETE-00FFCC?style=for-the-badge&logo=database&logoColor=0052CC)
![MySQL](https://img.shields.io/badge/MySQL-0052CC?style=for-the-badge&logo=mysql&logoColor=white)
![Java](https://img.shields.io/badge/Java_GUI-001F4D?style=for-the-badge&logo=openjdk&logoColor=00FFCC)

---

## Overview
Complete relational database management system for an online purchase platform. Covers schema design, sample data, SQL query development, and a Java-based GUI for query execution.

* **Project:** Online Purchase System v1.0
* **Database Engine:** MySQL
* **Core Technology:** SQL (DDL, DML, DCL)
* **Application Layer:** Java GUI with JDBC
* **Design Model:** Relational Database — Third Normal Form (3NF)

---

## Technical Stack

{
  "💻 Database": [
    ![MySQL](https://img.shields.io/badge/MySQL-001F4D?style=flat-square&logo=mysql&logoColor=00FFCC),
    ![SQL](https://img.shields.io/badge/SQL-001F4D?style=flat-square&logo=postgresql&logoColor=00FFCC),
    ![Relational](https://img.shields.io/badge/Relational_DB-001F4D?style=flat-square&logo=databricks&logoColor=00FFCC)
  ],
  "🛠️ Development": [
    ![Java](https://img.shields.io/badge/Java-001F4D?style=flat-square&logo=openjdk&logoColor=00FFCC),
    ![JDBC](https://img.shields.io/badge/JDBC-001F4D?style=flat-square&logoColor=00FFCC),
    ![Eclipse](https://img.shields.io/badge/Eclipse_IDE-001F4D?style=flat-square&logo=eclipseide&logoColor=00FFCC)
  ],
  "📊 Documentation": [
    ![ERD](https://img.shields.io/badge/ER_Diagram-001F4D?style=flat-square&logoColor=00FFCC),
    ![Schema](https://img.shields.io/badge/Relational_Schema-001F4D?style=flat-square&logoColor=00FFCC)
  ]
}

---

## Database Architecture

* **Data Integrity:** Primary keys, foreign keys, check constraints
* **Relationships:** 1-to-1 (users–address), 1-to-Many (users–orders), Many-to-Many (users–categories via junction table)
* **Normalization:** 3NF compliance with proper indexing
* **Constraints:** UNIQUE, NOT NULL, timestamp defaults, cascade deletes

---

## Database Schema

#### 👤 `users`
User accounts — `user_id` (PK), `user_name`, `email` (UNIQUE), `created_at`

#### 📍 `address`
User addresses (1-to-1 with users) — `user_id` (PK, FK), `street`, `city`

#### 📂 `categories`
Product categories — `category_id` (PK), `name`

#### ❤️ `user_likes`
Junction table: users ↔ categories — `user_like_id` (PK), `user_id` (FK), `category_id` (FK)

#### 📦 `products`
Inventory — `product_id` (PK), `name`, `description`, `stock_quantity`, `category_id` (FK)

#### 🛒 `orders`
Customer orders — `order_id` (PK), `user_id` (FK), `order_date`, `total_amount`, `order_status`

#### 📋 `order_items`
Order line items — `order_item_id` (PK), `order_id` (FK), `product_id` (FK), `quantity`, `price`

#### 💳 `payments`
Payment transactions — `payment_id` (PK), `order_id` (FK), `payment_date`, `amount`, `method`, `status`

#### ⭐ `reviews`
Product ratings — `review_id` (PK), `user_id` (FK), `product_id` (FK), `rating` (1–5), `comment`

---

## Repository Structure

```
DBMS/
├── ddl.sql              # Table creation (DDL)
├── dml.sql              # SELECT queries (DML)
├── sampledata.sql       # Test data population
├── SQLQueryGUI.java     # Java GUI application
├── ERD.pdf              # Entity-Relationship Diagram
└── relationalschema.pdf # Relational Schema
```

---

## Installation and Execution

### Database Setup
```bash
mysql -u root -p < ddl.sql
mysql -u root -p < sampledata.sql
```

### Java GUI
```bash
javac SQLQueryGUI.java
java SQLQueryGUI
```

### Included Queries
```sql
-- All users
SELECT user_id, user_name, email, created_at FROM users;

-- Products with average rating >= 4
SELECT DISTINCT p.product_name, AVG(r.rating) FROM products p JOIN reviews r ...

-- Total spent per user
SELECT u.user_name, SUM(o.total_amount) FROM users u JOIN orders o ...
```

---

## Technical Highlights

* **3NF Normalization** — Eliminated redundancy through proper functional dependencies
* **Java SQL GUI** — Interactive JDBC-based query interface
* **10 Analytical Queries** — JOINs, aggregations, subqueries, and grouping
* **Referential Integrity** — Cascade deletes, composite keys, and constraint enforcement

---

## Challenges and Solutions

| Challenge | Solution |
|-----------|----------|
| Complex many-to-many relationships | Junction table `user_likes` with composite foreign keys |
| GUI-to-DB connection management | JDBC connection handling with prepared statements |

---

## Contact
* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Email:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Organization:** IEEE Student Branch — Technical & R&D
