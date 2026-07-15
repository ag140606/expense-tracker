-- Database schema for expense tracker
CREATE DATABASE IF NOT EXISTS expense_tracker
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
  -- For comparing, sorting, and matching text characters

USE expense_tracker;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- A couple of indexes that make the aggregate queries fast
CREATE INDEX idx_expenses_date ON expenses (date);
-- This index accelerates queries that group, filter, or sort by category
CREATE INDEX idx_expenses_category ON expenses (category);

-- Sample data so the app isn't empty on the first run
INSERT INTO expenses (title, amount, category, date) VALUES
('Grocery shopping', 1450.00, 'Food', '2026-07-02'),
('Uber to college', 180.50, 'Transport', '2026-07-03'),
('Netflix subscription', 199.00, 'Entertainment', '2026-07-05'),
('Textbooks', 890.00, 'Education', '2026-07-06'),
('Coffee with friends', 240.00, 'Food', '2026-07-09'),
('Mobile recharge', 299.00, 'Utilities', '2026-07-10'),
('Movie tickets', 600.00, 'Entertainment', '2026-07-11'),
('Bus pass', 500.00, 'Transport', '2026-06-28');