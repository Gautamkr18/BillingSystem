<?php
// add_electrical_products.php
// This script adds a predefined list of electrical products for a house to the products table.
// Each product will have a stock quantity of 150 and a sample price.

require_once '../includes/auth.php'; // ensure admin logged in
require_once '../includes/db.php';

// List of electrical products (name, category, unit, price)
$products = [
    ['Ceiling Fan', 'Electrical', 'pcs', 1200.00],
    ['Table Lamp', 'Electrical', 'pcs', 250.00],
    ['LED Bulb (5W)', 'Electrical', 'pcs', 50.00],
    ['Light Switch', 'Electrical', 'pcs', 30.00],
    ['Wall Socket', 'Electrical', 'pcs', 20.00],
    ['Extension Cord', 'Electrical', 'pcs', 150.00],
    ['Copper Cable 2mm', 'Electrical', 'meter', 200.00],
    ['Electric Heater', 'Electrical', 'pcs', 3000.00],
    ['Air Conditioner 1.5 Ton', 'Electrical', 'pcs', 28000.00],
    ['Power Strip', 'Electrical', 'pcs', 180.00],
    // Additional 50 placeholder electrical items
    ['Electrical Product 1', 'Electrical', 'pcs', 100.00],
    ['Electrical Product 2', 'Electrical', 'pcs', 101.00],
    ['Electrical Product 3', 'Electrical', 'pcs', 102.00],
    ['Electrical Product 4', 'Electrical', 'pcs', 103.00],
    ['Electrical Product 5', 'Electrical', 'pcs', 104.00],
    ['Electrical Product 6', 'Electrical', 'pcs', 105.00],
    ['Electrical Product 7', 'Electrical', 'pcs', 106.00],
    ['Electrical Product 8', 'Electrical', 'pcs', 107.00],
    ['Electrical Product 9', 'Electrical', 'pcs', 108.00],
    ['Electrical Product 10', 'Electrical', 'pcs', 109.00],
    ['Electrical Product 11', 'Electrical', 'pcs', 110.00],
    ['Electrical Product 12', 'Electrical', 'pcs', 111.00],
    ['Electrical Product 13', 'Electrical', 'pcs', 112.00],
    ['Electrical Product 14', 'Electrical', 'pcs', 113.00],
    ['Electrical Product 15', 'Electrical', 'pcs', 114.00],
    ['Electrical Product 16', 'Electrical', 'pcs', 115.00],
    ['Electrical Product 17', 'Electrical', 'pcs', 116.00],
    ['Electrical Product 18', 'Electrical', 'pcs', 117.00],
    ['Electrical Product 19', 'Electrical', 'pcs', 118.00],
    ['Electrical Product 20', 'Electrical', 'pcs', 119.00],
    ['Electrical Product 21', 'Electrical', 'pcs', 120.00],
    ['Electrical Product 22', 'Electrical', 'pcs', 121.00],
    ['Electrical Product 23', 'Electrical', 'pcs', 122.00],
    ['Electrical Product 24', 'Electrical', 'pcs', 123.00],
    ['Electrical Product 25', 'Electrical', 'pcs', 124.00],
    ['Electrical Product 26', 'Electrical', 'pcs', 125.00],
    ['Electrical Product 27', 'Electrical', 'pcs', 126.00],
    ['Electrical Product 28', 'Electrical', 'pcs', 127.00],
    ['Electrical Product 29', 'Electrical', 'pcs', 128.00],
    ['Electrical Product 30', 'Electrical', 'pcs', 129.00],
    ['Electrical Product 31', 'Electrical', 'pcs', 130.00],
    ['Electrical Product 32', 'Electrical', 'pcs', 131.00],
    ['Electrical Product 33', 'Electrical', 'pcs', 132.00],
    ['Electrical Product 34', 'Electrical', 'pcs', 133.00],
    ['Electrical Product 35', 'Electrical', 'pcs', 134.00],
    ['Electrical Product 36', 'Electrical', 'pcs', 135.00],
    ['Electrical Product 37', 'Electrical', 'pcs', 136.00],
    ['Electrical Product 38', 'Electrical', 'pcs', 137.00],
    ['Electrical Product 39', 'Electrical', 'pcs', 138.00],
    ['Electrical Product 40', 'Electrical', 'pcs', 139.00],
    ['Electrical Product 41', 'Electrical', 'pcs', 140.00],
    ['Electrical Product 42', 'Electrical', 'pcs', 141.00],
    ['Electrical Product 43', 'Electrical', 'pcs', 142.00],
    ['Electrical Product 44', 'Electrical', 'pcs', 143.00],
    ['Electrical Product 45', 'Electrical', 'pcs', 144.00],
    ['Electrical Product 46', 'Electrical', 'pcs', 145.00],
    ['Electrical Product 47', 'Electrical', 'pcs', 146.00],
    ['Electrical Product 48', 'Electrical', 'pcs', 147.00],
    ['Electrical Product 49', 'Electrical', 'pcs', 148.00],
    ['Electrical Product 50', 'Electrical', 'pcs', 149.00]
];

$inserted = 0;
foreach ($products as $p) {
    $p_name = db_escape($conn, $p[0]);
    $category = db_escape($conn, $p[1]);
    $unit = db_escape($conn, $p[2]);
    $price = floatval($p[3]);
    $stock = 150;
    
    // Check if the product already exists to emulate ON DUPLICATE KEY UPDATE cross-platform
    $check_res = db_query($conn, "SELECT product_id FROM products WHERE product_name = '$p_name'");
    if ($check_res && db_num_rows($check_res) > 0) {
        $row = db_fetch_assoc($check_res);
        $pid = $row['product_id'];
        $query = "UPDATE products SET price = '$price', stock_quantity = '$stock', category = '$category', unit = '$unit', gst_percentage = 18, hsn_code = '8504' WHERE product_id = '$pid'";
    } else {
        $query = "INSERT INTO products (product_name, category, unit, price, stock_quantity, gst_percentage, hsn_code) VALUES ('$p_name', '$category', '$unit', '$price', '$stock', 18, '8504')";
    }
    
    if (db_query($conn, $query)) {
        $inserted++;
    }
}

echo "<script>alert('Added/updated $inserted electrical products with stock 150 each.');window.location='products.php';</script>";
?>
