![Typing Effect](https://readme-typing-svg.demolab.com?font=JetBrains+Mono&weight=500&size=18&pause=2000&color=00FFCC&background=1E1E1E00&center=false&vCenter=true&width=1000&lines=user%40PM-CyberSec%3A~%24+whoami+--focus+%22Database+Management%22+--project+%22Online_Purchase_System_v1.0%22)

# # SYSTEM_OVERRIDE: [ONLINE_PURCHASE_SYSTEM]
[![LinkedIn](https://img.shields.io/badge/LinkedIn-%230052CC.svg?style=for-the-badge&logo=linkedin&logoColor=00FFCC)](https://www.linkedin.com/in/paula-maged-04a721249)
[![Gmail](https://img.shields.io/badge/Encrypted_Mail-0052CC?style=for-the-badge&logo=gmail&logoColor=00FFCC)](mailto:paulamagedcyber@gmail.com)
[![Portfolio](https://img.shields.io/badge/Portfolio-%230052CC.svg?style=for-the-badge&logo=github&logoColor=00FFCC)](https://pm-cybersec.github.io/portfolio-site)
[![Branch](https://img.shields.io/badge/BRANCH-DBMS-00FFCC?style=for-the-badge&logo=git&logoColor=0052CC)](https://github.com/PM-CyberSec/SUTech-courses-labs/tree/DBMS)

![Status](https://img.shields.io/badge/STATUS-OPERATIONAL-00FFCC?style=for-the-badge&logo=database&logoColor=0052CC)
![MySQL](https://img.shields.io/badge/MySQL-0052CC?style=for-the-badge&logo=mysql&logoColor=white)
![Java](https://img.shields.io/badge/Java_GUI-001F4D?style=for-the-badge&logo=openjdk&logoColor=00FFCC)

### > Welcome, friend.
### > You are accessing the Online Purchase System Database Project.

---

## [ ⟁ ] LOG_01: THE OBJECTIVE
This project implements a complete database management system for an online purchase platform. It includes schema design, sample data, SQL queries, and a Java-based GUI for executing queries.

* $ **CODE_NAME=** Online_Purchase_System
* $ **DATABASE_ENGINE=** MySQL
* $ **CORE_TECH=** SQL (DDL, DML, DCL)
* $ **APPLICATION_LANG=** Java (JavaFX/Swing GUI)
* $ **DESIGN_PATTERN=** Relational Database Model (3NF)

---

## [ ⚙ ] THE TOOLKIT (DECRYPTED)
This project was built using modern database technologies:

{<br>
  "💻 Database": [
    ![MySQL](https://img.shields.io/badge/MySQL-001F4D?style=flat-square&logo=mysql&logoColor=00FFCC),
    ![SQL](https://img.shields.io/badge/SQL-001F4D?style=flat-square&logo=postgresql&logoColor=00FFCC),
    ![Relational](https://img.shields.io/badge/Relational_DB-001F4D?style=flat-square&logo=databricks&logoColor=00FFCC)
  ],
<br>
  "🛠️ Development": [
    ![Java](https://img.shields.io/badge/Java-001F4D?style=flat-square&logo=openjdk&logoColor=00FFCC),
    ![JDBC](https://img.shields.io/badge/JDBC-001F4D?style=flat-square&logoColor=00FFCC),
    ![Eclipse](https://img.shields.io/badge/Eclipse_IDE-001F4D?style=flat-square&logo=eclipseide&logoColor=00FFCC)
  ],
<br>
  "📊 Documentation": [
    ![ERD](https://img.shields.io/badge/ER_Diagram-001F4D?style=flat-square&logoColor=00FFCC),
    ![Schema](https://img.shields.io/badge/Relational_Schema-001F4D?style=flat-square&logoColor=00FFCC)
  ]<br>
}

---

## [ 🛡️ ] LOG_02: DATABASE_ARCHITECTURE
The database is designed with proper relationships and constraints:

* **{**
* **"Data Integrity":** [`Primary Keys`, `Foreign Keys`, `Check Constraints`],
* **"Relationships":** [`1-to-1 (users-address)`, `1-to-Many (users-orders)`, `Many-to-Many (users-categories)`],
* **"Normalization":** [`3NF Compliance`, `Proper Indexing`],
* **"Security":** [`UNIQUE constraints`, `NOT NULL fields`, `Timestamp defaults`]
* **}**

---

## [ 💾 ] LOG_03: DATABASE_SCHEMA

#### 👤 [USERS] > `users`
> User account information. Columns: user_id (PK), user_name, email (UNIQUE), created_at

#### 📍 [ADDRESS] > `address`
> User address information (1-to-1 with users). Columns: user_id (PK, FK), street, city

#### 📂 [CATEGORIES] > `categories`
> Product categories. Columns: category_id (PK), name

#### ❤️ [USER_LIKES] > `user_likes`
> Many-to-many: users ↔ categories. Columns: user_like_id (PK), user_id (FK), category_id (FK)

#### 📦 [PRODUCTS] > `products`
> Product inventory. Columns: product_id (PK), name, description, stock_quantity, category_id (FK)

#### 🛒 [ORDERS] > `orders`
> Customer orders. Columns: order_id (PK), user_id (FK), order_date, total_amount, order_status

#### 📋 [ORDER_ITEMS] > `order_items`
> Line items in orders. Columns: order_item_id (PK), order_id (FK), product_id (FK), quantity, price

#### 💳 [PAYMENTS] > `payments`
> Payment transactions. Columns: payment_id (PK), order_id (FK), payment_date, amount, method, status

#### ⭐ [REVIEWS] > `reviews`
> Product ratings. Columns: review_id (PK), user_id (FK), product_id (FK), rating (1-5), comment

---

## [ 📊 ] LOG_04: REPOSITORY_STRUCTURE

```
DBMS/
│
├── ddl.sql              # Table creation (DDL)
├── dml.sql              # SELECT queries (DML)
├── sampledata.sql       # Test data population
├── SQLQueryGUI.java     # Java GUI application
├── ERD.pdf              # Entity-Relationship Diagram
└── relationalschema.pdf # Relational Schema
```

---

## [ ⚙ ] LOG_05: INSTALLATION_AND_EXECUTION

### Database Setup
```bash
mysql -u root -p < ddl.sql
mysql -u root -p < sampledata.sql
```

### Java GUI Compilation
```bash
javac SQLQueryGUI.java
java SQLQueryGUI
```

### Included SQL Queries
```sql
-- All users
SELECT user_id, user_name, email, created_at FROM users;
-- Products with rating >= 4
SELECT DISTINCT p.product_name, AVG(r.rating) ...
-- Total spent by user
SELECT u.user_name, SUM(o.total_amount) ...
```

---

## [ 🔍 ] LOG_06: TECHNICAL_HIGHLIGHTS

* **3NF Normalization** — Eliminated data redundancy with proper functional dependencies
* **Java SQL GUI** — Interactive query interface with JDBC connectivity
* **10 Analytical Queries** — JOINs, aggregations, subqueries, and grouping
* **Entity Integrity** — Cascade deletes, composite keys, referential constraints

---

## [ ⚠ ] LOG_07: CHALLENGES_AND_SOLUTIONS

| Challenge | Solution |
|-----------|----------|
| Complex many-to-many relationships | Junction table `user_likes` with composite foreign keys |
| GUI-to-DB connection management | JDBC connection pooling with prepared statements |

---

## [ ✉ ] TRANSMIT_DATA
> **The database speaks louder than words. Review the schema.**

* **LinkedIn:** [paula-maged](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Encrypted Mail:** [paulamagedcyber@gmail.com](mailto:paulamagedcyber@gmail.com)
* **Portfolio:** [pm-cybersec.github.io](https://pm-cybersec.github.io/portfolio-site)
* **Org:** IEEE Student Branch (Tech & R&D)

---

### > Goodbye, friend.
### > [EOF]
