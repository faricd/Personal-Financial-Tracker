# FinTrack — Personal Financial Tracker

A personal finance web app for **students** (allowance, tuition, part-time jobs) and **professionals** (salary, bills, savings).
It's built with PHP and MySQL, and includes **FinBot**, a rule-based AI assistant that analyses your own data and gives advice.

**Technology:** HTML5 · CSS3 · Bootstrap 5.3 · JavaScript (Fetch/AJAX) · Chart.js · PHP 8 · MySQL/MariaDB · XAMPP / phpMyAdmin

---

## How to run (XAMPP)

1. Copy the folder **`Personal-Financial-Tracker`** into `C:\xampp\htdocs\`
2. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
3. Open **http://localhost/phpmyadmin**, then **Import**, choose `database/financial_tracker.sql`, and press **Import**.
   (This creates the `financial_tracker` database, its 6 tables and a demo account.)
4. Open **http://localhost/Personal-Financial-Tracker/**
5. Log in with the demo account: **demo@fintrack.com / demo123**, or register a new account.

> If you set a password for MySQL in XAMPP, change `DB_PASS` in `config/database.php`.
> An internet connection is needed for Bootstrap, the icons and Chart.js (loaded from a CDN).

---

## Features

| Area | What it does |
|---|---|
| Accounts | Register (as Student or Professional), login, logout, hashed passwords, profile page, change password, choose a currency (৳ $ € £ ₹) |
| Dashboard | Balance, monthly income/expenses, savings rate, 6-month bar chart, category doughnut chart, budgets, goals, recent transactions, FinBot health score |
| Transactions (CRUD) | Add, view, edit and delete, with type, category, amount, date, payment method (Cash / Card / bKash-Nagad / Bank) and description |
| Search & filter | **Live AJAX search** while typing, plus filters for type, category, payment method and date range, with totals of the results |
| Categories (CRUD) | Add, edit and delete categories with icons. Starter categories are created at sign-up. Categories that are in use can't be deleted |
| Budgets (CRUD) | Monthly limit per category, coloured progress bars, warnings, month navigation and "copy last month" |
| Savings goals (CRUD) | Target, deadline, progress bar and "Add money" |
| Reports | Date range with quick buttons, totals, line chart, category breakdown, top 5 expenses, payment methods, **Print/PDF** and **CSV export** |
| FinBot AI | Financial health score (0–100), personal insights, month-end forecast, and a **chat box** on every page that answers questions about your data |
| Validation | JavaScript (Bootstrap validation, password strength, passwords match, category filtered by type, no future dates, date range check) **plus** PHP server-side checks |
| Security | Prepared statements (no SQL injection), `htmlspecialchars` (no XSS), CSRF tokens on every form, and users can only see or change their own data |

### FinBot — how the "AI" works
FinBot doesn't use any paid API. It's a **rule-based expert system** written in PHP (`includes/ai_helper.php`):
1. It reads the user's data from MySQL (transactions, budgets, goals).
2. It calculates statistics such as totals, savings rate, month-over-month change and a month-end forecast (daily average × days in the month).
3. It applies if/else rules to turn those numbers into advice and a health score.
4. In the chat, it uses **keyword-based intent detection** to work out the question ("spent", "budget", "afford", "last month", a category name, …) and answers it with a SQL query.

Things you can ask: *"How much did I spend on food this month?"*, *"Spent on transport last month"*, *"Can I afford 3000?"*, *"How are my budgets?"*, *"Forecast my month"*, *"Give me saving tips"*, *"What is my biggest expense?"*, *"health score"*, *"help"*.

---

## Folder structure

```
Personal-Financial-Tracker/
├── index.php                  → redirects to public/
├── config/database.php        → MySQL connection (XAMPP settings)
├── database/financial_tracker.sql → tables + demo data (import in phpMyAdmin)
├── includes/
│   ├── auth.php               → sessions, login check, CSRF protection
│   ├── functions.php          → helpers: e(), money(), fetchAll(), validation, default categories
│   ├── ai_helper.php          → FinBot: insights, health score, chat answers
│   ├── transaction_functions.php → search/filter query, table rows, transaction validation
│   ├── header.php / footer.php → navbar, chat box, delete popup, scripts
│   └── transaction_form.php, category_form.php, goal_form.php → shared forms
├── public/                    → the pages users open
│   ├── login.php, register.php, logout.php, profile.php
│   ├── dashboard.php, reports.php, assistant.php (FinBot)
│   ├── transactions.php, add_transaction.php, edit_transaction.php, delete_transaction.php
│   ├── categories.php, edit_category.php, delete_category.php
│   ├── budgets.php, goals.php, edit_goal.php, export_csv.php
│   ├── ajax/search_transactions.php, ajax/ai_chat.php
│   └── assets/css/style.css, assets/js/script.js
└── documentation/             → project document, ER diagram, screenshots
```
