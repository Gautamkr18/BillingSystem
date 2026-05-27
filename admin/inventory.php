<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/header.php';

// Handle Stock Adjustments (IN or DAMAGE)
if (isset($_POST['adjust_stock'])) {
    $product_id = $_POST['product_id'];
    $qty = intval($_POST['quantity']);
    $type = mysqli_real_escape_string($conn, $_POST['adj_type']); // 'IN' or 'DAMAGE'
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    
    if ($qty <= 0) {
        echo "<script>alert('Error: Quantity must be greater than zero.');</script>";
    } else {
        // Fetch current product
        $prod_res = mysqli_query($conn, "SELECT product_name, stock_quantity FROM products WHERE product_id='$product_id'");
        $product = mysqli_fetch_assoc($prod_res);
        
        if (!$product) {
            echo "<script>alert('Error: Product not found.');</script>";
        } else {
            $product_name = $product['product_name'];
            $current_stock = $product['stock_quantity'];
            
            if ($type == 'DAMAGE' && $qty > $current_stock) {
                echo "<script>alert('Error: Damaged quantity exceeds available stock.');</script>";
            } else {
                // Apply calculations
                if ($type == 'IN') {
                    $new_stock = $current_stock + $qty;
                    $log_qty = $qty;
                } else { // DAMAGE
                    $new_stock = $current_stock - $qty;
                    $log_qty = -$qty; // negative for stock outs
                }
                
                // 1. Update stock_quantity in database
                mysqli_query($conn, "UPDATE products SET stock_quantity = '$new_stock' WHERE product_id='$product_id'");
                
                // 2. Insert into inventory_logs
                mysqli_query($conn, "INSERT INTO inventory_logs (product_id, quantity, type, remarks) VALUES ('$product_id', '$log_qty', '$type', '$remarks')");
                
                // 3. Insert into activity_logs
                $username = $_SESSION['username'];
                $uid = $_SESSION['user_id'];
                $action_desc = ($type == 'IN') ? "Restocked $qty $product_name" : "Reported $qty damaged $product_name";
                mysqli_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Stock Adjustment', '$action_desc')");
                
                echo "<script>alert('Stock Adjusted Successfully!'); window.location='inventory.php';</script>";
            }
        }
    }
}
// Handle product deletion

if (isset($_POST['delete_product'])) {
    $del_id = $_POST['delete_product'];
    // Delete product from products table
    $del_res = mysqli_query($conn, "DELETE FROM products WHERE product_id='$del_id'");
    // Optionally delete related logs
    mysqli_query($conn, "DELETE FROM inventory_logs WHERE product_id='$del_id'");
    // Remove activity logs referencing this product (simple pattern match)
    mysqli_query($conn, "DELETE FROM activity_logs WHERE details LIKE CONCAT('%', '$del_id', '%')");
    if ($del_res) {
        echo "<script>alert('Product deleted successfully.'); window.location='inventory.php';</script>"; exit;
    } else {
        echo "<script>alert('Error deleting product.');</script>"; exit;
    }
}

// Delete all products
if (isset($_POST['delete_all_products'])) {
    // Delete all rows from products
    mysqli_query($conn, "DELETE FROM products");
    // Clean related logs
    mysqli_query($conn, "DELETE FROM inventory_logs");
    mysqli_query($conn, "DELETE FROM activity_logs");
    echo "<script>alert('All products deleted successfully.'); window.location='inventory.php';</script>"; exit;
}

?>

<div class="page-header">
    <h2>Inventory Stock Management</h2>
</div>

<!-- Stock Grid Summary -->
<div class="dashboard-cards" style="grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- Card 1: Low Stock Warning Count -->
    <?php
    $low_stock_query = "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= low_stock_threshold";
    $low_stock_res = mysqli_query($conn, $low_stock_query);
    $low_stock_data = mysqli_fetch_assoc($low_stock_res);
    $low_stock_count = $low_stock_data['count'];
    ?>
    <div class="stat-card" style="border-left-color: var(--error); display: flex; align-items: center; gap: 20px; box-sizing: border-box;">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--error); width: 60px; height: 60px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-info">
            <h3>Low Stock Products</h3>
            <p class="stat-value" style="color: var(--error);"><?php echo $low_stock_count; ?></p>
        </div>
    </div>

    <!-- Card 2: Total Items in Inventory -->
    <?php
    $total_stock_query = "SELECT SUM(stock_quantity) as total FROM products";
    $total_stock_res = mysqli_query($conn, $total_stock_query);
    $total_stock_data = mysqli_fetch_assoc($total_stock_res);
    $total_stock = $total_stock_data['total'] ? $total_stock_data['total'] : 0;
    ?>
    <div class="stat-card" style="border-left-color: var(--secondary-color); display: flex; align-items: center; gap: 20px; box-sizing: border-box;">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); width: 60px; height: 60px;">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="stat-info">
            <h3>Total Inventory Stock</h3>
            <p class="stat-value" style="color: var(--secondary-color);"><?php echo $total_stock; ?></p>
        </div>
    </div>
</div>

<div class="form-grid" style="grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
    
    <!-- Left Column: Low Stock Alerts Table & General List -->
    <div class="table-container">
        <h3 style="padding: 20px 20px 0 20px; margin: 0; color: var(--error);"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</h3>
        <span style="font-size:0.85rem; color:var(--text-muted); padding: 0 20px; display:block; margin-top:5px;">Products below minimum stock safety thresholds</span>
        <br>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Threshold</th>
                    <th>Current Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $low_stock_items = mysqli_query($conn, "SELECT * FROM products WHERE stock_quantity <= low_stock_threshold ORDER BY stock_quantity ASC");
                if (mysqli_num_rows($low_stock_items) == 0) {
                    echo "<tr><td colspan='5' style='text-align:center; color:#10B981; font-weight:bold; padding:25px;'>All products are safely above stock thresholds!</td></tr>";
                }
                while ($item = mysqli_fetch_assoc($low_stock_items)) {
                ?>
                <tr>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td><?php echo $item['low_stock_threshold']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                    <td style="font-weight: bold; color: <?php echo $item['stock_quantity'] == 0 ? 'var(--error)' : 'var(--warning)'; ?>;">
                        <?php echo $item['stock_quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?>
                    </td>
                    <td>
                        <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; 
                            background-color: <?php echo $item['stock_quantity'] == 0 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(245, 158, 11, 0.1)'; ?>;
                            color: <?php echo $item['stock_quantity'] == 0 ? 'var(--error)' : '#D97706'; ?>;">
                            <?php echo $item['stock_quantity'] == 0 ? 'OUT' : 'LOW'; ?>
                        </span>
                    </td><td><form method="POST" style="display:inline;"><input type="hidden" name="delete_product" value="<?php echo $item['product_id']; ?>"><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button></form></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Right Column: Stock Adjustments Quick Action Form -->
    <div class="card-form" style="margin-bottom: 0;">
        <h3><i class="fa-solid fa-sliders"></i> Stock Adjustment</h3>
        <br>
        <form method="POST">
            <div class="form-group">
                <label>Select Product</label>
                <select name="product_id" required style="width: 100%;">
                    <option value="">-- Choose Product --</option>
                    <?php
                    $prods = mysqli_query($conn, "SELECT product_id, product_name, stock_quantity, unit FROM products ORDER BY product_name ASC");
                    while($p = mysqli_fetch_assoc($prods)){
                    ?>
                    <option value="<?php echo $p['product_id']; ?>">
                        <?php echo htmlspecialchars($p['product_name']); ?> (Stock: <?php echo $p['stock_quantity'] . ' ' . $p['unit']; ?>)
                    </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Adjustment Type</label>
                <select name="adj_type" required>
                    <option value="IN">Stock In (Purchase/Restock)</option>
                    <option value="DAMAGE">Stock Out (Damaged/Write-off)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" placeholder="Quantity to adjust" min="1" required>
            </div>

            <div class="form-group">
                <label>Remarks / Notes</label>
                <input type="text" name="remarks" placeholder="e.g. Purchase Inv #104 or Vendor return" required>
            </div>

            <button type="submit" name="adjust_stock" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fa-solid fa-save"></i> Save Adjustment</button>
        </form>
    </div>
</div>

<!-- All Products Section (Full Width Below for optimal space and layout aesthetics) -->
<div class="table-container" style="margin-top: 30px;">
    <h3 style="padding:20px 20px 0 20px; margin:0; color: var(--primary);"><i class="fa-solid fa-box"></i> All Products</h3>
    <a href="products.php" class="btn btn-success" style="margin:10px;">Add New Product</a>
    <table class="data-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Unit</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $all_products = mysqli_query($conn, "SELECT * FROM products ORDER BY product_name ASC");
            while($p = mysqli_fetch_assoc($all_products)) {
            ?>
            <tr>
                <td style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($p['product_name']); ?></td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #F3F4F6; color: #4B5563;">
                        <?php echo htmlspecialchars($p['category']); ?>
                    </span>
                </td>
                <td style="font-weight: bold; color: var(--primary-color);"><?php echo $p['stock_quantity']; ?></td>
                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete_product" value="<?php echo $p['product_id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</button>
                    </form>
                </td>
            </tr>
            <?php }
            ?>
        </tbody>
    </table>
</div>

<!-- Bottom Section: Detailed Audit Stock Logs -->
<div class="page-header" style="margin-top: 40px;">
    <h2>Stock Movement Ledger Logs</h2>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Type</th>
                <th>New Stock</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT l.*, p.product_name, p.unit, p.stock_quantity FROM inventory_logs l JOIN products p ON l.product_id = p.product_id ORDER BY l.id DESC LIMIT 50";
            $log_res = mysqli_query($conn, $query);
            if(mysqli_num_rows($log_res) == 0) {
                echo "<tr><td colspan='5' style='text-align:center; color:var(--text-muted); padding:20px;'>No stock logs available yet.</td></tr>";
            }
            while($row = mysqli_fetch_assoc($log_res)) {
                $type_color = "var(--secondary-color)";
                $badge_bg = "rgba(16, 185, 129, 0.1)";
                if($row['type'] == 'DAMAGE') {
                    $type_color = "var(--error)";
                    $badge_bg = "rgba(239, 68, 68, 0.1)";
                } else if($row['type'] == 'OUT') {
                    $type_color = "#3B82F6";
                    $badge_bg = "rgba(59, 130, 246, 0.1)";
                }
            ?>
            <tr>
                <td style="color:var(--text-muted); font-size:0.9rem;"><?php echo date('d M Y h:i A', strtotime($row['created_at'])); ?></td>
                <td style="font-weight:bold; color:var(--text-main);"><?php echo htmlspecialchars($row['product_name']); ?></td>
                <td style="font-weight:bold; color: <?php echo $row['quantity'] > 0 ? 'var(--secondary-color)' : 'var(--error)'; ?>;">
                    <?php echo $row['quantity'] > 0 ? '+' : ''; ?><?php echo $row['quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?>
                </td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $type_color; ?>;">
                        <?php echo $row['type']; ?>
                    </span>
                </td>
                <td style="font-size:0.9rem; color:#4B5563;"><?php echo $row['stock_quantity']; ?> <?php echo htmlspecialchars($row['unit']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
