<?php
include dirname(__DIR__) . '/includes/db.php';

echo "<h2>Starting Database Migration...</h2>";

// Helper function to check if a column exists
function columnExists($conn, $table, $column) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return mysqli_num_rows($result) > 0;
}

// 1. Upgrade `users` table
if (!columnExists($conn, 'users', 'role')) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'admin'");
    echo "Added 'role' to 'users' table.<br>";
}

// Ensure role is admin for existing users
mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE role IS NULL OR role = ''");

// Insert a default cashier user for testing if not exists
$cashier_check = mysqli_query($conn, "SELECT * FROM users WHERE username = 'cashier'");
if (mysqli_num_rows($cashier_check) == 0) {
    $pwd = MD5('cashier123');
    mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES ('cashier', '$pwd', 'cashier')");
    echo "Inserted default cashier user ('cashier' / 'cashier123').<br>";
}

// 2. Upgrade `customers` table
if (!columnExists($conn, 'customers', 'gstin')) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN gstin VARCHAR(15) DEFAULT NULL");
    echo "Added 'gstin' to 'customers' table.<br>";
}
if (!columnExists($conn, 'customers', 'credit_balance')) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN credit_balance DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'credit_balance' to 'customers' table.<br>";
}

// 3. Upgrade `products` table
if (!columnExists($conn, 'products', 'cost_price')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'cost_price' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'category')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN category VARCHAR(50) DEFAULT 'General'");
    echo "Added 'category' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'barcode')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN barcode VARCHAR(50) DEFAULT NULL");
    echo "Added 'barcode' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'hsn_code')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN hsn_code VARCHAR(20) DEFAULT '8473'");
    echo "Added 'hsn_code' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'image_path')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
    echo "Added 'image_path' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'expiry_date')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN expiry_date DATE DEFAULT NULL");
    echo "Added 'expiry_date' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'unit')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN unit VARCHAR(20) DEFAULT 'pcs'");
    echo "Added 'unit' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'low_stock_threshold')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN low_stock_threshold INT DEFAULT 5");
    echo "Added 'low_stock_threshold' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'brand')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN brand VARCHAR(100) DEFAULT NULL");
    echo "Added 'brand' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'supplier')) {
    mysqli_query($conn, "ALTER TABLE products ADD COLUMN supplier VARCHAR(100) DEFAULT NULL");
    echo "Added 'supplier' to 'products' table.<br>";
}

// 4. Upgrade `invoices` table
if (!columnExists($conn, 'invoices', 'discount')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'discount' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'cgst')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN cgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'cgst' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'sgst')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN sgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'sgst' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'igst')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN igst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'igst' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'payment_method')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash'");
    echo "Added 'payment_method' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'amount_paid')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'amount_paid' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'payment_status')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN payment_status VARCHAR(20) DEFAULT 'Paid'");
    echo "Added 'payment_status' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'round_off')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN round_off DECIMAL(5,2) DEFAULT 0.00");
    echo "Added 'round_off' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'refunded_amount')) {
    mysqli_query($conn, "ALTER TABLE invoices ADD COLUMN refunded_amount DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'refunded_amount' to 'invoices' table.<br>";
}

// Update amount_paid for pre-existing invoices to equal grand_total
mysqli_query($conn, "UPDATE invoices SET amount_paid = grand_total WHERE amount_paid = 0.00 OR amount_paid IS NULL");

// 5. Upgrade `invoice_items` table
if (!columnExists($conn, 'invoice_items', 'cgst')) {
    mysqli_query($conn, "ALTER TABLE invoice_items ADD COLUMN cgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'cgst' to 'invoice_items' table.<br>";
}
if (!columnExists($conn, 'invoice_items', 'sgst')) {
    mysqli_query($conn, "ALTER TABLE invoice_items ADD COLUMN sgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'sgst' to 'invoice_items' table.<br>";
}
if (!columnExists($conn, 'invoice_items', 'igst')) {
    mysqli_query($conn, "ALTER TABLE invoice_items ADD COLUMN igst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'igst' to 'invoice_items' table.<br>";
}
if (!columnExists($conn, 'invoice_items', 'discount')) {
    mysqli_query($conn, "ALTER TABLE invoice_items ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'discount' to 'invoice_items' table.<br>";
}

// 6. Create `activity_logs` table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'activity_logs' table exists.<br>";

// 7. Create `inventory_logs` table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'IN', 'OUT', 'DAMAGE', 'TRANSFER'
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'inventory_logs' table exists.<br>";

// 8. Create `expenses` table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'expenses' table exists.<br>";

// 9. Create `customer_ledger` table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS customer_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT DEFAULT NULL,
    type VARCHAR(20) NOT NULL, -- 'DEBIT' (purchase), 'CREDIT' (payment)
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'customer_ledger' table exists.<br>";

echo "<h3>Database Migration Completed Successfully!</h3>";
echo "<a href='../admin/dashboard.php'>Go to Dashboard</a>";
?>
