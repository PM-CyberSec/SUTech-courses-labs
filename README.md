### **user@PM-CyberSec:~$ whoami --focus "Database Management" --project "Online_Purchase_System_v1.0"**

# # SYSTEM_OVERRIDE: [ONLINE_PURCHASE_SYSTEM]

![Status](https://img.shields.io/badge/STATUS-OPERATIONAL-39FF14?style=for-the-badge&logo=database)
![Stack](https://img.shields.io/badge/STACK-MySQL_Java-005571?style=for-the-badge&logo=mysql)
![Environment](https://img.shields.io/badge/ENV-Development-orange?style=for-the-badge&logo=java)

### > Welcome, friend.
### > You are accessing the Online Purchase System Database Project.

---

## [ ⟁ ] LOG_01: THE OBJECTIVE
This project implements a complete database management system for an online purchase platform. It includes database schema design, sample data, SQL queries, and a Java-based GUI for executing queries.

* $ **CODE_NAME=** Online_Purchase_System
* $ **DATABASE_ENGINE=** MySQL
* $ **CORE_TECH=** SQL (DDL, DML, DCL)
* $ **APPLICATION_LANG=** Java (JavaFX/Swing GUI)
* $ **DESIGN_PATTERN=** Relational Database Model

---

## [ ⚙ ] THE TOOLKIT (DECRYPTED)
This project was built using modern database technologies and Java:

## 💻 Database
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![SQL](https://img.shields.io/badge/SQL-006D77?style=for-the-badge&logo=postgresql&logoColor=white)
![Relational DB](https://img.shields.io/badge/Relational_DB-1289A3?style=for-the-badge&logo=databricks&logoColor=white)

## 🛠️ Development Tools
![Java](https://img.shields.io/badge/Java-ED8B00?style=for-the-badge&logo=openjdk&logoColor=white)
![JDBC](https://img.shields.io/badge/JDBC-005571?style=for-the-badge&logo=java&logoColor=white)
![Eclipse IDE](https://img.shields.io/badge/Eclipse_IDE-2C2255?style=for-the-badge&logo=eclipseide&logoColor=white)

## 📊 Documentation
![ERD](https://img.shields.io/badge/ERD-Enabled-green?style=for-the-badge)
![Relational Schema](https://img.shields.io/badge/Relational_Schema-Active-blue?style=for-the-badge)
![PDF Diagrams](https://img.shields.io/badge/Diagrams-PDF-purple?style=for-the-badge&logo=adobeacrobatreader&logoColor=white)

---

## [ 🛡️ ] LOG_02: DEFENSIVE ARCHITECTURE
The database is designed with proper relationships and constraints:

* **{**
* **"Data Integrity":** [`Primary Keys`, `Foreign Keys`, `Check Constraints`],
* **"Relationships":** [`One-to-One (users-address)`, `One-to-Many (users-orders)`, `Many-to-Many (users-categories)`],
* **"Normalization":** [`3NF Compliance`, `Proper Indexing`],
* **"Security":** [`UNIQUE constraints`, `NOT NULL fields`, `Timestamp defaults`]
* **}**

---

## [ 💾 ] LOG_03: SYSTEM MODULES (DATABASE SCHEMA)

#### 👤 [USERS] > `users`
> Stores user account information.
> Columns: user_id (PK), user_name, email (UNIQUE), created_at

#### 📍 [ADDRESS] > `address`
> Stores user address information (1-to-1 with users).
> Columns: user_id (PK, FK), street, city

#### 📂 [CATEGORIES] > `categories`
> Product categories for classification.
> Columns: category_id (PK), name

#### ❤️ [USER_LIKES] > `user_likes`
> Many-to-many relationship between users and categories.
> Columns: user_like_id (PK), user_id (FK), category_id (FK)

#### 📦 [PRODUCTS] > `products`
> Product inventory information.
> Columns: product_id (PK), product_name, description, stock_quantity, image_url, category_id (FK)

#### 🛒 [ORDERS] > `orders`
> Customer order information.
> Columns: order_id (PK), user_id (FK), order_date, total_amount, order_status

#### 📋 [ORDER_ITEMS] > `order_items`
> Individual items in an order.
> Columns: order_item_id (PK), order_id (FK), product_id (FK), order_item_quantity, price

#### 💳 [PAYMENTS] > `payments`
> Payment transaction records.
> Columns: payment_id (PK), order_id (FK), payment_date, amount, method, status

#### ⭐ [REVIEWS] > `reviews`
> Product reviews and ratings.
> Columns: review_id (PK), user_id (FK), product_id (FK), rating (1-5), comment, created_at

---

## [ 📊 ] LOG_04: DATABASE DIAGRAMS

### Entity-Relationship Diagram
![ERD](ERD.pdf)
> Complete ERD showing all entities, attributes, and relationships.

### Relational Schema
![Schema](relationalschema.pdf)
> Detailed relational schema with table structures and keys.

---

## [ ⚙ ] LOG_05: PROJECT FILES

### SQL Files
| File | Description |
|------|-------------|
| `ddl.sql` | Data Definition Language - Table creation |
| `dml.sql` | Data Manipulation Language - SELECT queries |
| `sampledata.sql` | Sample data for testing |

### Java Application
| File | Description |
|------|-------------|
| `SQLQueryGUI.java` | Java-based SQL query interface |

### Documentation
| File | Description |
|------|-------------|
| `ERD.pdf` | Entity-Relationship Diagram |
| `relationalschema.pdf` | Database Schema |
| `presentation.pdf` | Project Presentation |

---

## [ 🔍 ] LOG_06: SQL QUERIES INCLUDED

```sql
-- 1. Select all users
SELECT user_id, user_name, email, created_at FROM users;

-- 2. Find products in 'Electronics' category
SELECT ... FROM products JOIN categories ...

-- 3. Get orders and items for specific user
SELECT ... FROM orders JOIN order_items ...

-- 4. Calculate total spent by each user
SELECT u.user_name, SUM(o.total_amount) ...

-- 5. Products with rating >= 4
SELECT DISTINCT p.product_name, AVG(r.rating) ...

-- 6. Users who liked 'Books' category
SELECT u.user_name, u.email FROM users JOIN user_likes ...

-- 7. Payment details for specific order
SELECT ... FROM orders JOIN payments ...

-- 8. Address details for specific user
SELECT ... FROM users JOIN address ...

-- 9. Products count per category
SELECT c.name, COUNT(p.product_id) ...

-- 10. Out of stock products
SELECT product_name, stock_quantity WHERE stock_quantity = 0;
```

---

## [ ⌬ ] SYSTEM_ACCESS

* **Step 01 ->** `git clone <repo-url>`
* **Step 02 ->** Import `ddl.sql` to create database schema
* **Step 03 ->** Import `sampledata.sql` to populate test data
* **Step 04 ->** Run queries from `dml.sql` to verify data
* **Step 05 ->** Compile and run `SQLQueryGUI.java` for GUI interface

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

---

## [ ✉ ] TRANSMIT_DATA
> **The database speaks louder than words. Review the schema.**

* **LinkedIn:** [LinkedIn](https://www.linkedin.com/in/paula-maged-04a721249/)
* **Terminal:** [GitHub Portfolio](https://github.com/PM-CyberSec)
* **Encrypted Mail:** paulamagedcyber@gmail.com

---

### > [EOF]
