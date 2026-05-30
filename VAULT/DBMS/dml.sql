-- SQL Queries for the online_purchase_system database (nicely formatted, one query per line)

USE online_purchase_system;

-- 1. Select all users from the 'users' table
SELECT user_id, user_name, email, created_at FROM users;

-- 2. Find all products in the 'Electronics' category
SELECT p.product_id, p.product_name, p.description, p.stock_quantity, p.image_url, c.name AS category_name FROM products AS p JOIN categories AS c ON p.category_id = c.category_id WHERE c.name = 'Electronics';

-- 3. Get all orders and their items for a specific user (e.g., Alice Smith - user_id = 1)
SELECT o.order_id, o.order_date, o.total_amount, o.order_status, p.product_name, oi.order_item_quantity, oi.price AS item_price FROM orders AS o JOIN order_items AS oi ON o.order_id = oi.order_id JOIN products AS p ON oi.product_id = p.product_id WHERE o.user_id = 1 ORDER BY o.order_date DESC;

-- 4. Calculate the total amount spent by each user
SELECT u.user_id, u.user_name, SUM(o.total_amount) AS total_spent FROM users AS u JOIN orders AS o ON u.user_id = o.user_id GROUP BY u.user_id, u.user_name ORDER BY total_spent DESC;

-- 5. List all products that have reviews with a rating of 4 or higher
SELECT DISTINCT p.product_name, p.description, p.image_url, AVG(r.rating) AS average_rating FROM products AS p JOIN reviews AS r ON p.product_id = r.product_id WHERE r.rating >= 4 GROUP BY p.product_id, p.product_name, p.description, p.image_url ORDER BY average_rating DESC;

-- 6. Find users who have liked the 'Books' category
SELECT u.user_name, u.email FROM users AS u JOIN user_likes AS ul ON u.user_id = ul.user_id JOIN categories AS c ON ul.category_id = c.category_id WHERE c.name = 'Books';

-- 7. Get the payment details for a specific order (e.g., order_id = 1)
SELECT o.order_id, o.total_amount AS order_total, p.payment_id, p.payment_date, p.amount AS payment_amount, p.method, p.status AS payment_status FROM orders AS o JOIN payments AS p ON o.order_id = p.order_id WHERE o.order_id = 1;

-- 8. Get the address details for a specific user (e.g., Bob Johnson - user_id = 2)
SELECT u.user_name, a.street, a.city FROM users AS u JOIN address AS a ON u.user_id = a.user_id WHERE u.user_id = 2;

-- 9. Count the number of products in each category
SELECT c.name AS category_name, COUNT(p.product_id) AS number_of_products FROM categories AS c LEFT JOIN products AS p ON c.category_id = p.category_id GROUP BY c.name ORDER BY number_of_products DESC;

-- 10. Find products that are currently out of stock
SELECT product_id, product_name, stock_quantity FROM products WHERE stock_quantity = 0;
