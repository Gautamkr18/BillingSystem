<?php
// Test SQLite3 queries and escaping behavior
$db = new SQLite3(':memory:');
$db->exec("CREATE TABLE products (product_id INTEGER PRIMARY KEY AUTOINCREMENT, product_name TEXT, category TEXT, price REAL, stock_quantity INTEGER, gst_percentage INTEGER)");

$p_name = SQLite3::escapeString("Ceiling Fan");
$db->exec("INSERT INTO products (product_name, category, price, stock_quantity) VALUES ('$p_name', 'Electrical', 1200.00, 150)");

// Check if it exists
$check_res = $db->query("SELECT product_id FROM products WHERE product_name = '$p_name'");
$row = $check_res->fetchArray(SQLITE3_ASSOC);
echo "Exists? " . ($row ? "YES, ID = " . $row['product_id'] : "NO") . "<br>";

// Let's test if we do db_escape on top of it or if there is any other issue
?>
