<?php
// add_electrical_products.php
// This script adds a predefined list of electrical products for a house to the products table.
// Each product will have a stock quantity of 150 and a sample price.

require_once '../includes/auth.php'; // ensure admin logged in
require_once '../includes/db.php';
require_once '../includes/products_seed.php';

$inserted = seed_electrical_products($conn);

echo "<script>alert('Added/updated $inserted electrical products with stock 150 each.');window.location='products.php';</script>";
?>
