-- DML to insert sample data into the online_purchase_system database

USE online_purchase_system;

-- 1. Insert data into the 'users' table
INSERT INTO users (user_name, email, created_at) VALUES
('Alice Smith', 'alice.smith@example.com', '2023-01-15 10:00:00'),
('Bob Johnson', 'bob.j@example.com', '2023-02-20 11:30:00'),
('Charlie Brown', 'charlie.b@example.com', '2023-03-01 14:45:00'),
('Diana Prince', 'diana.p@example.com', '2023-04-10 09:15:00');

-- 2. Insert data into the 'address' table
-- Ensure user_id matches existing users
INSERT INTO address (user_id, street, city) VALUES
(1, '123 Main St', 'Anytown'),
(2, '45 Oak Ave', 'Springfield'),
(3, '789 Pine Ln', 'Metropolis'),
(4, '101 Elm Rd', 'Gotham');

-- 3. Insert data into the 'categories' table
INSERT INTO categories (name) VALUES
('Electronics'),
('Books'),
('Clothing'),
('Home & Kitchen'),
('Sports');

-- 4. Insert data into the 'products' table
-- Ensure category_id matches existing categories
INSERT INTO products (product_name, description, stock_quantity, image_url, category_id) VALUES
('Laptop Pro X', 'High-performance laptop with 16GB RAM and 1TB SSD.', 50, 'http://example.com/laptop.jpg', 1),
('The Great Novel', 'A captivating story of adventure and discovery.', 150, 'http://example.com/novel.jpg', 2),
('Wireless Headphones', 'Noise-cancelling headphones with 30-hour battery life.', 100, 'http://example.com/headphones.jpg', 1),
('Summer T-Shirt', '100% cotton, breathable fabric, various sizes.', 200, 'http://example.com/tshirt.jpg', 3),
('Coffee Maker Deluxe', 'Programmable coffee maker with built-in grinder.', 30, 'http://example.com/coffee_maker.jpg', 4),
('Yoga Mat Eco', 'Eco-friendly yoga mat, non-slip surface.', 80, 'http://example.com/yoga_mat.jpg', 5);

-- 5. Insert data into the 'user_likes' table
-- Ensure user_id and category_id match existing records
INSERT INTO user_likes (user_id, category_id) VALUES
(1, 1), -- Alice likes Electronics
(1, 2), -- Alice likes Books
(2, 3), -- Bob likes Clothing
(3, 1), -- Charlie likes Electronics
(3, 4), -- Charlie likes Home & Kitchen
(4, 5); -- Diana likes Sports

-- 6. Insert data into the 'orders' table
-- Ensure user_id matches existing users
INSERT INTO orders (user_id, order_date, total_amount, order_status) VALUES
(1, '2023-05-01 12:00:00', 1200.00, 'Completed'),
(2, '2023-05-03 15:30:00', 50.00, 'Pending'),
(1, '2023-05-05 09:00:00', 150.00, 'Shipped'),
(3, '2023-05-07 10:10:00', 300.00, 'Processing');

-- 7. Insert data into the 'order_items' table
-- Ensure order_id and product_id match existing records
INSERT INTO order_items (order_id, product_id, order_item_quantity, price) VALUES
(1, 1, 1, 1200.00), -- Order 1: 1 Laptop Pro X
(2, 4, 1, 50.00),   -- Order 2: 1 Summer T-Shirt
(3, 3, 1, 150.00),  -- Order 3: 1 Wireless Headphones
(4, 5, 1, 300.00),  -- Order 4: 1 Coffee Maker Deluxe
(1, 3, 1, 150.00);  -- Order 1: 1 Wireless Headphones (added to existing order)

-- 8. Insert data into the 'payments' table
-- Ensure order_id matches existing orders
INSERT INTO payments (order_id, payment_date, amount, method, status) VALUES
(1, '2023-05-01 12:05:00', 1200.00, 'Credit Card', 'Completed'),
(2, '2023-05-03 15:35:00', 50.00, 'PayPal', 'Pending'),
(3, '2023-05-05 09:05:00', 150.00, 'Debit Card', 'Completed'),
(4, '2023-05-07 10:15:00', 300.00, 'Credit Card', 'Processing');

-- 9. Insert data into the 'reviews' table
-- Ensure user_id and product_id match existing records
INSERT INTO reviews (user_id, product_id, rating, comment, created_at) VALUES
(1, 1, 5, 'Absolutely love this laptop! Super fast and reliable.', '2023-05-02 10:00:00'),
(2, 4, 4, 'Comfortable t-shirt, good for summer. A bit pricey.', '2023-05-04 11:00:00'),
(1, 3, 5, 'Great headphones, excellent noise cancellation.', '2023-05-06 14:00:00'),
(3, 5, 3, 'Coffee maker works well, but the grinder is a bit noisy.', '2023-05-08 09:30:00');
