<?php
include 'includes/db.php';

echo "<h2>Duplicate Check Analysis</h2>";

$res = db_query($conn, "SELECT product_id, product_name, category FROM products");
$products = [];
while ($row = db_fetch_assoc($res)) {
    $products[] = $row;
}

echo "Total products in DB: " . count($products) . "<br><br>";

foreach ($products as $p1) {
    $p_name = $p1['product_name'];
    $escaped = db_escape($conn, $p_name);
    
    // Simulate the check query
    $check_res = db_query($conn, "SELECT product_id FROM products WHERE product_name = '$escaped'");
    $count = db_num_rows($check_res);
    
    echo "Product ID {$p1['product_id']}: '{$p_name}' (Escaped: '{$escaped}') -> Matched Rows: {$count}<br>";
}
?>
