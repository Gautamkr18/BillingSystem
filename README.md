# 📊 Billing & Inventory Management System (BillingSystem)

A modern, robust, and feature-rich **Billing and Inventory Management System** built with PHP and MySQL. Designed for retail, electrical, and small-to-medium businesses to seamlessly manage sales, track inventory, compute GST taxes, log business expenses, and monitor customer ledgers.

---

## ✨ Key Features

### 👤 Role-Based Authentication
* **Admin Dashboard:** Access to comprehensive business insights, financial reports, user management, and configuration settings.
* **Cashier Access:** Focused, fast Point of Sale (POS) interface optimized for high-speed checkout and billing.

### 🛒 Point of Sale & Invoicing
* **GST Calculation Engine:** Automatic computation of **CGST, SGST, and IGST** tax brackets based on HSN codes.
* **Flexible Billing:** Supports discounts, round-offs, payment status tracking (Paid, Partial, Unpaid), and custom payment methods.
* **Thermal & Standard Print Formats:** Ready-to-print invoice formatting matching standard billing prints.

### 📦 Product & Inventory Control
* **Smart Stock Management:** Fields for stock quantity, low-stock thresholds, category, brand, supplier, and expiry date.
* **Barcode & HSN Ready:** Ready to integrate with hardware barcode scanners and HSN coding for Indian tax compliance.
* **Stock Auditing:** Automatically logs inventory movements (`IN`, `OUT`, `DAMAGE`, `TRANSFER`) for audit trails.

### 💳 Customer Ledger & Credit Account
* **Store Credit System:** Manage customer profiles, track credit balances, and record partial/later payments.
* **Detailed Ledger:** Complete financial statement for each customer showing debit purchases and credit payments.

### 📉 Financial Reporting & Expenses
* **Expense Tracker:** Log everyday business expenses by category (utilities, salaries, rent, etc.) to keep net margins accurate.
* **GST & Sales Reports:** Generate accounting-friendly summaries for tax filing and audits.

---

## 🛠️ Tech Stack
* **Backend:** PHP 7.4+ / 8.0+
* **Database:** MySQL / MariaDB
* **Frontend:** Vanilla HTML5, Responsive CSS3, JavaScript (AJAX for POS checkouts)
* **Server Environment:** XAMPP / WAMP / LEMP

---

## 🚀 Installation & Setup

Follow these simple steps to run the Billing System locally on your machine using **XAMPP**:

### 1. Clone the Repository
Clone this repository directly into your web root directory (e.g., `htdocs` for XAMPP):
```bash
git clone https://github.com/Gautamkr18/BillingSystem.git
```

### 2. Configure Database Connection
* Open `includes/db.php` in a text editor.
* Configure your local MySQL database connection details:
  ```php
  $conn = mysqli_connect("localhost", "root", "", "billing_system");
  ```

### 3. Initialize & Migrate the Database
We have included a convenient automatic migration system that creates all tables and upgrades schema dynamically.
1. Start your **Apache** and **MySQL** services in XAMPP.
2. Open your web browser and navigate to:
   [http://localhost/billing-system/database/migrate.php](http://localhost/billing-system/database/migrate.php)
3. You will see a progress report as the database and all its tables are automatically generated and configured.

---

## 🔑 Default Credentials

Use the following default accounts to log in after installation:

| Username | Password | Role |
| :--- | :--- | :--- |
| **`admin`** | `admin123` | **Administrator** (Full access) |
| **`cashier`** | `cashier123` | **Cashier** (Billing & POS only) |

> [!WARNING]
> Please change these default passwords immediately after logging in for the first time via the user profile/admin settings.

---

## 📂 Project Structure

```
billing-system/
├── admin/                 # Main management interfaces
│   ├── pos.php            # POS billing system terminal
│   ├── ledger.php         # Customer statement & ledger tracking
│   ├── dashboard.php      # Analytics, sales summary, & metrics
│   ├── expenses.php       # Company expense management
│   ├── inventory.php      # Stock levels and product directories
│   └── ...                # User management, tax reports, invoices
├── css/                   # Style layouts and premium CSS variables
├── database/              # SQL schemas and migration runner
│   ├── billing.sql        # Core base schema
│   └── migrate.php        # Auto-migration utility script
├── includes/              # Shared template components (header, footer, database, auth)
├── index.php              # Public entry / redirect
├── login.php              # User authentication system
└── register.php           # User signup gateway
```

---

## 🤝 Contributing
Contributions make the open-source community an amazing place to learn, inspire, and create. Any contributions you make are **greatly appreciated**.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📝 License
Distributed under the MIT License. See `LICENSE` for more information.
