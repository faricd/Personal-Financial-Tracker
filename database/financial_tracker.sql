-- =====================================================================
--  FinTrack - Personal Financial Tracker
--  Database: financial_tracker  (MySQL / MariaDB - XAMPP)
--
--  How to import:
--    1. Start Apache and MySQL in the XAMPP Control Panel.
--    2. Open http://localhost/phpmyadmin
--    3. Click "Import", choose this file and press "Import" (Go).
--
--  Demo login:  demo@fintrack.com  /  demo123
-- =====================================================================

-- Read this file as UTF-8 so symbols like ৳ are stored correctly
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS financial_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE financial_tracker;

-- Drop old tables first so the file can be imported again safely.
-- (Child tables are dropped before parent tables because of foreign keys.)
DROP TABLE IF EXISTS ai_chat_history;
DROP TABLE IF EXISTS savings_goals;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
-- 1. USERS : people who use the website
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,                       -- hashed with password_hash()
    user_type   ENUM('student','professional') NOT NULL DEFAULT 'student',
    currency    VARCHAR(5)   NOT NULL DEFAULT '৳',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. CATEGORIES : each user has their own income / expense categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    type        ENUM('income','expense') NOT NULL,
    icon        VARCHAR(40)  NOT NULL DEFAULT 'bi-tag',      -- Bootstrap Icons class name
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_category_per_user UNIQUE (user_id, name, type)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. TRANSACTIONS : every income or expense record
-- ---------------------------------------------------------------------
CREATE TABLE transactions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    category_id       INT UNSIGNED NOT NULL,
    amount            DECIMAL(12,2) NOT NULL,
    type              ENUM('income','expense') NOT NULL,
    payment_method    ENUM('cash','card','mobile_banking','bank') NOT NULL DEFAULT 'cash',
    description       VARCHAR(255) NULL,
    transaction_date  DATE NOT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_transactions_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,          -- a used category cannot be deleted
    CONSTRAINT chk_transaction_amount CHECK (amount > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX idx_transactions_type      ON transactions(type);

-- ---------------------------------------------------------------------
-- 4. BUDGETS : monthly spending limit for an expense category
-- ---------------------------------------------------------------------
CREATE TABLE budgets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    category_id   INT UNSIGNED NOT NULL,
    budget_month  CHAR(7) NOT NULL,                    -- format YYYY-MM, e.g. 2026-10
    amount        DECIMAL(12,2) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_budgets_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_budgets_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT uq_budget UNIQUE (user_id, category_id, budget_month),
    CONSTRAINT chk_budget_amount CHECK (amount > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. SAVINGS_GOALS : things the user is saving money for
-- ---------------------------------------------------------------------
CREATE TABLE savings_goals (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NOT NULL,
    title          VARCHAR(100) NOT NULL,
    target_amount  DECIMAL(12,2) NOT NULL,
    saved_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
    deadline       DATE NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_goals_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_goal_target CHECK (target_amount > 0),
    CONSTRAINT chk_goal_saved  CHECK (saved_amount >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. AI_CHAT_HISTORY : questions asked to FinBot and its answers
-- ---------------------------------------------------------------------
CREATE TABLE ai_chat_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    question    VARCHAR(500) NOT NULL,
    answer      TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chat_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
--  SAMPLE DATA (demo account so the website is not empty)
--  Dates are calculated from today's date, so the demo always looks fresh.
-- =====================================================================

SET @m0 = CAST(DATE_FORMAT(CURDATE(), '%Y-%m-01') AS DATE);   -- first day of this month
SET @m1 = @m0 - INTERVAL 1 MONTH;                               -- last month
SET @m2 = @m0 - INTERVAL 2 MONTH;
SET @m3 = @m0 - INTERVAL 3 MONTH;

-- Password is: demo123
INSERT INTO users (id, name, email, password, user_type, currency) VALUES
(1, 'Demo Student', 'demo@fintrack.com',
 '$2y$10$yNzTcUUUSmU0y.0QdLKei.AxnO9LzFvtnxHoxPpnyNlFr5YiI3kwe', 'student', '৳');

INSERT INTO categories (id, user_id, name, type, icon) VALUES
(1,  1, 'Allowance',        'income',  'bi-cash-coin'),
(2,  1, 'Part-time Job',    'income',  'bi-briefcase'),
(3,  1, 'Scholarship',      'income',  'bi-award'),
(4,  1, 'Freelance',        'income',  'bi-laptop'),
(5,  1, 'Food & Dining',    'expense', 'bi-cup-hot'),
(6,  1, 'Transport',        'expense', 'bi-bus-front'),
(7,  1, 'Books & Supplies', 'expense', 'bi-book'),
(8,  1, 'Tuition Fees',     'expense', 'bi-mortarboard'),
(9,  1, 'Mobile & Internet','expense', 'bi-phone'),
(10, 1, 'Entertainment',    'expense', 'bi-controller'),
(11, 1, 'Shopping',         'expense', 'bi-bag'),
(12, 1, 'Rent & Hostel',    'expense', 'bi-house'),
(13, 1, 'Health',           'expense', 'bi-heart-pulse');

INSERT INTO transactions (user_id, category_id, amount, type, payment_method, description, transaction_date) VALUES
-- three months ago
(1, 1, 12000, 'income',  'mobile_banking', 'Monthly allowance from family', @m3 + INTERVAL 1 DAY),
(1, 2,  6000, 'income',  'cash',           'Private tuition (2 students)',  @m3 + INTERVAL 4 DAY),
(1, 12, 5500, 'expense', 'mobile_banking', 'Hostel seat rent',              @m3 + INTERVAL 2 DAY),
(1, 5,  4200, 'expense', 'cash',           'Canteen and meals',             @m3 + INTERVAL 10 DAY),
(1, 6,  1300, 'expense', 'cash',           'Bus and rickshaw',              @m3 + INTERVAL 12 DAY),
(1, 9,   500, 'expense', 'mobile_banking', 'Internet package',              @m3 + INTERVAL 5 DAY),
(1, 7,  1800, 'expense', 'card',           'Semester books',                @m3 + INTERVAL 8 DAY),
(1, 10,  700, 'expense', 'card',           'Movie with friends',            @m3 + INTERVAL 20 DAY),
-- two months ago
(1, 1, 12000, 'income',  'mobile_banking', 'Monthly allowance from family', @m2 + INTERVAL 1 DAY),
(1, 2,  6000, 'income',  'cash',           'Private tuition (2 students)',  @m2 + INTERVAL 4 DAY),
(1, 4,  3500, 'income',  'bank',           'Logo design project',           @m2 + INTERVAL 15 DAY),
(1, 12, 5500, 'expense', 'mobile_banking', 'Hostel seat rent',              @m2 + INTERVAL 2 DAY),
(1, 5,  4600, 'expense', 'cash',           'Canteen and meals',             @m2 + INTERVAL 11 DAY),
(1, 6,  1400, 'expense', 'cash',           'Bus and rickshaw',              @m2 + INTERVAL 13 DAY),
(1, 9,   500, 'expense', 'mobile_banking', 'Internet package',              @m2 + INTERVAL 5 DAY),
(1, 8,  9000, 'expense', 'bank',           'Semester tuition installment',  @m2 + INTERVAL 9 DAY),
(1, 11, 2200, 'expense', 'card',           'New shoes',                     @m2 + INTERVAL 18 DAY),
(1, 13,  600, 'expense', 'cash',           'Pharmacy',                      @m2 + INTERVAL 21 DAY),
-- last month
(1, 1, 12000, 'income',  'mobile_banking', 'Monthly allowance from family', @m1 + INTERVAL 1 DAY),
(1, 2,  6000, 'income',  'cash',           'Private tuition (2 students)',  @m1 + INTERVAL 4 DAY),
(1, 3,  5000, 'income',  'bank',           'Merit scholarship',             @m1 + INTERVAL 12 DAY),
(1, 12, 5500, 'expense', 'mobile_banking', 'Hostel seat rent',              @m1 + INTERVAL 2 DAY),
(1, 5,  4800, 'expense', 'cash',           'Canteen and meals',             @m1 + INTERVAL 10 DAY),
(1, 6,  1500, 'expense', 'cash',           'Bus and rickshaw',              @m1 + INTERVAL 14 DAY),
(1, 9,   500, 'expense', 'mobile_banking', 'Internet package',              @m1 + INTERVAL 5 DAY),
(1, 10, 1200, 'expense', 'card',           'Concert ticket',                @m1 + INTERVAL 19 DAY),
(1, 11, 1500, 'expense', 'card',           'Clothes',                       @m1 + INTERVAL 22 DAY),
(1, 7,   650, 'expense', 'cash',           'Printing and stationery',       @m1 + INTERVAL 7 DAY),
-- this month (relative to today)
(1, 1, 12000, 'income',  'mobile_banking', 'Monthly allowance from family', CURDATE() - INTERVAL 12 DAY),
(1, 2,  6000, 'income',  'cash',           'Private tuition (2 students)',  CURDATE() - INTERVAL 9 DAY),
(1, 12, 5500, 'expense', 'mobile_banking', 'Hostel seat rent',              CURDATE() - INTERVAL 11 DAY),
(1, 5,  1850, 'expense', 'cash',           'Canteen and meals',             CURDATE() - INTERVAL 8 DAY),
(1, 5,  1450, 'expense', 'mobile_banking', 'Food delivery',                 CURDATE() - INTERVAL 3 DAY),
(1, 6,   650, 'expense', 'cash',           'Bus and rickshaw',              CURDATE() - INTERVAL 6 DAY),
(1, 9,   500, 'expense', 'mobile_banking', 'Internet package',              CURDATE() - INTERVAL 10 DAY),
(1, 10, 1250, 'expense', 'card',           'Gaming subscription and cafe',  CURDATE() - INTERVAL 4 DAY),
(1, 11, 3200, 'expense', 'card',           'Headphones',                    CURDATE() - INTERVAL 2 DAY),
(1, 7,   450, 'expense', 'cash',           'Lab notebook',                  CURDATE() - INTERVAL 1 DAY);

INSERT INTO budgets (user_id, category_id, budget_month, amount) VALUES
(1, 5,  DATE_FORMAT(CURDATE(), '%Y-%m'), 5000),
(1, 6,  DATE_FORMAT(CURDATE(), '%Y-%m'), 1500),
(1, 9,  DATE_FORMAT(CURDATE(), '%Y-%m'),  600),
(1, 10, DATE_FORMAT(CURDATE(), '%Y-%m'), 1000),
(1, 11, DATE_FORMAT(CURDATE(), '%Y-%m'), 2500);

INSERT INTO savings_goals (user_id, title, target_amount, saved_amount, deadline) VALUES
(1, 'New Laptop',          60000, 22000, CURDATE() + INTERVAL 6 MONTH),
(1, 'Emergency Fund',      20000,  9000, NULL),
(1, 'Trip to Cox''s Bazar', 12000, 10500, CURDATE() + INTERVAL 2 MONTH);
