<?php
include dirname(__DIR__) . '/includes/db.php';

echo "<h2>Starting Database Migration...</h2>";

// Check if base tables exist (by checking if users table exists)
$table_check = @db_query($conn, "SELECT 1 FROM users LIMIT 1");
$base_exists = ($table_check !== false);

if (!$base_exists) {
    echo "Base tables not found. Importing base schema from billing.sql...<br>";
    $sql_file = __DIR__ . '/billing.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Split queries by semicolon and execute them sequentially
        $queries = explode(';', $sql_content);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $res = db_query($conn, $query);
                if (!$res) {
                    echo "<span style='color:red;'>Error executing query: " . db_error($conn) . "</span><br>";
                }
            }
        }
        echo "Base tables imported successfully!<br>";
    } else {
        echo "<span style='color:red;'>Error: billing.sql not found at $sql_file</span><br>";
    }
}

// Helper function to check if a column exists
function columnExists($conn, $table, $column) {
    $result = db_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return db_num_rows($result) > 0;
}

// 1. Upgrade `users` table
if (!columnExists($conn, 'users', 'role')) {
    db_query($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) DEFAULT 'admin'");
    echo "Added 'role' to 'users' table.<br>";
}
if (!columnExists($conn, 'users', 'phone')) {
    db_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    echo "Added 'phone' column to 'users' table.<br>";
}

// Ensure role is admin for existing users
db_query($conn, "UPDATE users SET role = 'admin' WHERE role IS NULL OR role = ''");

// Insert a default cashier user for testing if not exists
$cashier_check = db_query($conn, "SELECT * FROM users WHERE username = 'cashier'");
if (db_num_rows($cashier_check) == 0) {
    $pwd = MD5('cashier123');
    db_query($conn, "INSERT INTO users (username, password, role) VALUES ('cashier', '$pwd', 'cashier')");
    echo "Inserted default cashier user ('cashier' / 'cashier123').<br>";
}

// 2. Upgrade `customers` table
if (!columnExists($conn, 'customers', 'gstin')) {
    db_query($conn, "ALTER TABLE customers ADD COLUMN gstin VARCHAR(15) DEFAULT NULL");
    echo "Added 'gstin' to 'customers' table.<br>";
}
if (!columnExists($conn, 'customers', 'credit_balance')) {
    db_query($conn, "ALTER TABLE customers ADD COLUMN credit_balance DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'credit_balance' to 'customers' table.<br>";
}

// 3. Upgrade `products` table
if (!columnExists($conn, 'products', 'cost_price')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN cost_price DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'cost_price' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'category')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN category VARCHAR(50) DEFAULT 'General'");
    echo "Added 'category' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'barcode')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN barcode VARCHAR(50) DEFAULT NULL");
    echo "Added 'barcode' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'hsn_code')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN hsn_code VARCHAR(20) DEFAULT '8473'");
    echo "Added 'hsn_code' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'image_path')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) DEFAULT NULL");
    echo "Added 'image_path' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'expiry_date')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN expiry_date DATE DEFAULT NULL");
    echo "Added 'expiry_date' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'unit')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN unit VARCHAR(20) DEFAULT 'pcs'");
    echo "Added 'unit' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'low_stock_threshold')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN low_stock_threshold INT DEFAULT 5");
    echo "Added 'low_stock_threshold' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'brand')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN brand VARCHAR(100) DEFAULT NULL");
    echo "Added 'brand' to 'products' table.<br>";
}
if (!columnExists($conn, 'products', 'supplier')) {
    db_query($conn, "ALTER TABLE products ADD COLUMN supplier VARCHAR(100) DEFAULT NULL");
    echo "Added 'supplier' to 'products' table.<br>";
}

// Cleanup any duplicate products (keeping only the unique ones by name)
global $use_sqlite;
if ($use_sqlite) {
    db_query($conn, "DELETE FROM products WHERE product_id NOT IN (SELECT MIN(product_id) FROM products GROUP BY LOWER(TRIM(product_name)))");
} else {
    db_query($conn, "DELETE FROM products WHERE product_id NOT IN (SELECT min_id FROM (SELECT MIN(product_id) AS min_id FROM products GROUP BY LOWER(TRIM(product_name))) AS temp)");
}
echo "Deduplicated any duplicate products in 'products' table.<br>";

// 4. Upgrade `invoices` table
if (!columnExists($conn, 'invoices', 'discount')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'discount' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'cgst')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN cgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'cgst' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'sgst')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN sgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'sgst' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'igst')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN igst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'igst' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'payment_method')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(50) DEFAULT 'Cash'");
    echo "Added 'payment_method' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'amount_paid')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'amount_paid' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'payment_status')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN payment_status VARCHAR(20) DEFAULT 'Paid'");
    echo "Added 'payment_status' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'round_off')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN round_off DECIMAL(5,2) DEFAULT 0.00");
    echo "Added 'round_off' to 'invoices' table.<br>";
}
if (!columnExists($conn, 'invoices', 'refunded_amount')) {
    db_query($conn, "ALTER TABLE invoices ADD COLUMN refunded_amount DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'refunded_amount' to 'invoices' table.<br>";
}

// Update amount_paid for pre-existing invoices to equal grand_total
db_query($conn, "UPDATE invoices SET amount_paid = grand_total WHERE amount_paid = 0.00 OR amount_paid IS NULL");

// 5. Upgrade `invoice_items` table
if (!columnExists($conn, 'invoice_items', 'cgst')) {
    db_query($conn, "ALTER TABLE invoice_items ADD COLUMN cgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'cgst' to 'invoice_items' table.<br>";
}
if (!columnExists($conn, 'invoice_items', 'sgst')) {
    db_query($conn, "ALTER TABLE invoice_items ADD COLUMN sgst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'sgst' to 'invoice_items' table.<br>";
}
if (!columnExists($conn, 'invoice_items', 'igst')) {
    db_query($conn, "ALTER TABLE invoice_items ADD COLUMN igst DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'igst' to 'invoice_items' table.<br>";
}
if (!columnExists($conn, 'invoice_items', 'discount')) {
    db_query($conn, "ALTER TABLE invoice_items ADD COLUMN discount DECIMAL(10,2) DEFAULT 0.00");
    echo "Added 'discount' to 'invoice_items' table.<br>";
}

// 6. Create `activity_logs` table
db_query($conn, "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(100) NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'activity_logs' table exists.<br>";

// 7. Create `inventory_logs` table
db_query($conn, "CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'IN', 'OUT', 'DAMAGE', 'TRANSFER'
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'inventory_logs' table exists.<br>";

// 8. Create `expenses` table
db_query($conn, "CREATE TABLE IF NOT EXISTS expenses (
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
db_query($conn, "CREATE TABLE IF NOT EXISTS customer_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT DEFAULT NULL,
    type VARCHAR(20) NOT NULL, -- 'DEBIT' (purchase), 'CREDIT' (payment)
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Ensured 'customer_ledger' table exists.<br>";

// 10. Automatically seed sample electrical products if the products table is empty
$prod_count_res = db_query($conn, "SELECT COUNT(*) as count FROM products");
$prod_count = 0;
if ($prod_count_res) {
    $row = db_fetch_assoc($prod_count_res);
    $prod_count = intval($row['count']);
}

if ($prod_count == 0) {
    echo "Seeding default electrical products...<br>";
    require_once dirname(__DIR__) . '/includes/products_seed.php';
    $core_products = array_slice(get_electrical_products(), 0, 10);
    $seeded = seed_electrical_products($conn, $core_products);
    echo "Successfully seeded $seeded default electrical products!<br>";
}

echo "<h3>Database Migration Completed Successfully!</h3>";
echo "<a href='../admin/dashboard.php'>Go to Dashboard</a>";
?>

