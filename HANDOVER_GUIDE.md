# 🏪 Billing & Inventory Management System: Handover Guide

This document provides a complete guide for installing, configuring, deploying, and maintaining the **Billing & Inventory Management System**. It is optimized to run locally on SQLite (zero-config, serverless) or in production on PostgreSQL (fully persistent, robust cloud database).

---

## 🏗️ Architecture & Features Overview
This project is built using a highly portable and efficient hybrid database architecture:
* **Backend:** PHP 7.4+ (Pure Vanilla PHP, no heavy framework dependencies).
* **Database Modes (Automatic):**
  * **Production (PostgreSQL):** Triggered when the `DATABASE_URL` environment variable is set. Uses PDO to connect to PostgreSQL with on-the-fly SQL query translation.
  * **Local / Dev (SQLite 3):** Default fallback. Requires **zero database configuration or server installation**. All data is saved in a single file in the `database/` folder.
* **Key Modules:**
  * **Interactive POS Terminal:** Cashier interface for high-speed checkout and billing.
  * **Role-Based Accounts:** Separates Administrator (financials, staff accounts, reports) and Cashier (billing & POS).
  * **GST Engine:** Automatically calculates CGST, SGST, and IGST rates.
  * **Store Credit System:** Customer profiles, ledgers, and credit limits.
  * **Dual Invoice Printing:** Toggle dynamically between standard **A4 Sheet invoices** and **80mm Thermal Receipt formats**.
  * **UPI QR Payment:** Generates secure, dynamic UPI payment QR codes on invoices based on outstanding balances.
  * **WhatsApp & Sharing Integration:** Instantly generate and share ready-formatted bills on WhatsApp.

---

## ⚙️ Shop Customization Settings
To adapt this billing system for any specific shop, edit the central configuration file:

📁 **File Location:** `backend/includes/config.php`

Open this file in a text editor and update the store information:
```php
define('STORE_NAME', 'Krishna Hardware');
define('STORE_ADDRESS', 'ICHAK BAZAR HAZARIBAGH, JHARKHAND');
define('STORE_PHONE', '7549117172');
define('STORE_EMAIL', 'support@krishnahardware.com');
define('STORE_GSTIN', '20ABCDE1234F1Z5');

// UPI Payment Configuration (For dynamic payment QR codes on invoices)
define('STORE_UPI_ID', 'gautamgupta35@ybl'); // UPI ID to receive payments (e.g., GPay/PhonePe ID)
define('STORE_MERCHANT_NAME', 'Krishna Hardware'); // Merchant Name registered with the UPI ID
```
*Updating these values will immediately refresh all invoices (A4/Thermal), WhatsApp greetings, and UPI payment links with the new store's details.*

---

## 🚀 Deployment Guide: Production (PostgreSQL)

This project is pre-configured for a containerized cloud deployment on services like **Render** using the provided `render.yaml` and `Dockerfile`.

### Step 1: Deploy Service & DB
1. Push your codebase to a repository on **GitHub**, **GitLab**, or **Bitbucket**.
2. Log in to your [Render Dashboard](https://dashboard.render.com).
3. Click on the **Blueprints** tab and select **New Blueprint Instance**.
4. Connect the repository containing this project.
5. Render will automatically parse the `render.yaml` configuration:
   * It spins up a Dockerized Web Service.
   * It provisions a **PostgreSQL Database** instance.
   * It injects the `DATABASE_URL` environment variable directly into the Web Service container.
6. Click **Apply**.

### Step 2: Initialize Database Schemas
Once the build is complete and live, navigate to the migration script in your browser to generate the tables in PostgreSQL:
`https://<your-deployed-domain>.onrender.com/migrate.php`

*(This script automatically adapts to PostgreSQL mode and runs the full schema setup, default user seeding, and deduplication logic).*

---

## 🚀 Local Installation Guide (SQLite)

To run the application locally on a shop's computer:

### Step 1: Install XAMPP
1. Download and install **XAMPP for Windows** (with PHP 7.4 or newer).
2. Install it in the default directory (usually `C:\xampp`).

### Step 2: Copy Project Files
1. Copy the entire `billing-system` project folder into XAMPP's web root:
   `C:\xampp\htdocs\billing-system\`

### Step 3: Run Apache Web Server
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to the **Apache** service.
3. *Note: You do NOT need to start the MySQL service, as this application defaults to SQLite locally!*

### Step 4: Run Database Migrations
1. Open any web browser and navigate to:
   [http://localhost/billing-system/migrate.php](http://localhost/billing-system/migrate.php)
2. This will automatically build the SQLite database file (`database/billing_system.sqlite`) and structure all the tables.

---

## 🔑 Default Accounts & Access

On first login, use these credentials. You should change the default passwords immediately from the settings:

| Username | Password | Role / Permission Level |
| :--- | :--- | :--- |
| **`admin`** | `admin123` | **Administrator** (Full access to settings, reports, expenses, inventory, staff management) |
| **`cashier`** | `cashier123` | **Cashier** (Limited access, boots directly into the POS checkout terminal) |

---

## 💾 Backups & Database Maintenance

Depending on how you run the application, choose the appropriate backup strategy:

### For Production (PostgreSQL):
* **Automated Backups:** Cloud providers like Render perform daily automated backups of your database.
* **Manual Backups:** Use standard PostgreSQL tools. You can generate a full database dump (`.sql` file) using `pg_dump`:
  ```bash
  pg_dump postgres://user:password@host:port/dbname > billing_backup.sql
  ```

### For Local Dev / Offline Fallback (SQLite):
* **Database File Location:** `database/billing_system.sqlite`
* **How to Backup:** Simply copy the `billing_system.sqlite` file and save it to a secure location (e.g., a USB drive, cloud storage, Google Drive, etc.).
