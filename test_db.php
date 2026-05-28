<?php
include 'includes/db.php';

echo "<h2>Database Type: " . ($use_sqlite ? "SQLite" : "MySQL") . "</h2>";

$res = db_query($conn, "SELECT product_id, product_name, category, price, stock_quantity, gst_percentage FROM products");
echo "<table border='1'>
<tr>
<th>ID</th>
<th>Name</th>
<th>Category</th>
<th>Price</th>
<th>Stock</th>
<th>GST</th>
<th>Name Hex</th>
</tr>";

while ($row = db_fetch_assoc($res)) {
    echo "<tr>
    <td>{$row['product_id']}</td>
    <td>" . htmlspecialchars($row['product_name']) . "</td>
    <td>{$row['category']}</td>
    <td>{$row['price']}</td>
    <td>{$row['stock_quantity']}</td>
    <td>{$row['gst_percentage']}</td>
    <td>" . bin2hex($row['product_name']) . "</td>
    </tr>";
}
echo "</table>";
?>
