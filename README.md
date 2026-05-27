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

## ☁️ Deploying to Render

This project is fully ready to be deployed on **Render** using standard Docker containers. You have two options for deployment:

### Option A: Standard Blueprint Deployment (Recommended for Production)
This uses Render's **Blueprints** (`render.yaml`) to automatically spin up both the PHP container and a private MySQL database container with a **1GB persistent disk** (so your database records are safely saved when the server restarts).
*Note: Render's persistent disks require a paid tier (starter instance).*

1. Push your codebase to a GitHub, GitLab, or Bitbucket repository.
2. Log in to your [Render Dashboard](https://dashboard.render.com).
3. Click on the **Blueprints** tab on the sidebar.
4. Click **New Blueprint Instance**.
5. Connect your repository containing this code.
6. Render will automatically parse the `render.yaml` file. Click **Apply**.
7. Once successfully deployed, navigate to the Web Service URL:
   `https://<your-app-name>.onrender.com/database/migrate.php`
   This will automatically initialize and build all your MySQL tables in the private container!

---

### Option B: 100% Free Tier Deployment (No Credit Card Required)
If you want to host this completely free, you can run the PHP app as a free Web Service on Render and connect it to a free external MySQL provider (e.g. [Aiven](https://aiven.io), [Clever Cloud](https://www.clever-cloud.com), or [Railway](https://railway.app)).

1. **Set Up a Free Database:**
   * Create a free MySQL database instance on Aiven, Clever Cloud, or Railway.
   * Save the host, port, database name, username, and password.
2. **Deploy the Web App on Render:**
   * In the Render Dashboard, click **New +** and select **Web Service**.
   * Connect your GitHub repository.
   * Set **Language / Runtime** to `Docker` (Render will automatically detect the `Dockerfile` at the root).
   * Choose the **Free** instance type.
3. **Configure Environment Variables:**
   * Go to the **Environment** tab of your new Web Service on Render.
   * Add the following keys with your free database credentials:
     * `DB_HOST` (e.g., `mysql-instance.aivencloud.com`)
     * `DB_PORT` (e.g., `12345`)
     * `DB_NAME` (e.g., `billing_system`)
     * `DB_USER` (e.g., `avnadmin`)
     * `DB_PASSWORD` (your database password)
   * Click **Save Changes**.
4. **Initialize Database:**
   * Once your deployment finishes building and is live, navigate to:
     `https://<your-render-subdomain>.onrender.com/database/migrate.php`
   * This runs all migration scripts and automatically populates the remote database schema.

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
