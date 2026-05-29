<?php
include '../../backend/includes/auth.php';
include '../../backend/includes/db.php';

// Check if database tables are migrated, if not, redirect to migrate.php automatically
$table_check = @db_query($conn, "SELECT 1 FROM users LIMIT 1");
if ($table_check === false) {
    header("Location: ../../migrate.php");
    exit();
}

include '../includes/header.php';

// Handle Stock Adjustments (IN or DAMAGE)
if (isset($_POST['adjust_stock'])) {
    $product_id = $_POST['product_id'];
    $qty = intval($_POST['quantity']);
    $type = db_real_escape_string($conn, $_POST['adj_type']); // 'IN' or 'DAMAGE'
    $remarks = db_real_escape_string($conn, $_POST['remarks']);
    
    if ($qty <= 0) {
        echo "<script>alert('Error: Quantity must be greater than zero.');</script>";
    } else {
        // Fetch current product
        $prod_res = db_query($conn, "SELECT product_name, stock_quantity FROM products WHERE product_id='$product_id'");
        $product = db_fetch_assoc($prod_res);
        
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
                db_query($conn, "UPDATE products SET stock_quantity = '$new_stock' WHERE product_id='$product_id'");
                
                // 2. Insert into inventory_logs
                db_query($conn, "INSERT INTO inventory_logs (product_id, quantity, type, remarks) VALUES ('$product_id', '$log_qty', '$type', '$remarks')");
                
                // 3. Insert into activity_logs
                $username = $_SESSION['username'];
                $uid = $_SESSION['user_id'];
                $action_desc = ($type == 'IN') ? "Restocked $qty $product_name" : "Reported $qty damaged $product_name";
                db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Stock Adjustment', '$action_desc')");
                
                echo "<script>alert('Stock Adjusted Successfully!'); window.location='inventory.php';</script>";
            }
        }
    }
}

// Handle product deletion
if (isset($_POST['delete_product'])) {
    restrictToAdmin();
    $del_id = $_POST['delete_product'];
    // Delete product from products table
    $del_res = db_query($conn, "DELETE FROM products WHERE product_id='$del_id'");
    // Optionally delete related logs
    db_query($conn, "DELETE FROM inventory_logs WHERE product_id='$del_id'");
    // Remove activity logs referencing this product (simple pattern match)
    db_query($conn, "DELETE FROM activity_logs WHERE details LIKE CONCAT('%', '$del_id', '%')");
    if ($del_res) {
        echo "<script>alert('Product deleted successfully.'); window.location='inventory.php';</script>"; exit;
    } else {
        echo "<script>alert('Error deleting product.');</script>"; exit;
    }
}

// Delete all products
if (isset($_POST['delete_all_products'])) {
    restrictToAdmin();
    // Delete all rows from products
    db_query($conn, "DELETE FROM products");
    // Clean related logs
    db_query($conn, "DELETE FROM inventory_logs");
    db_query($conn, "DELETE FROM activity_logs");
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
    $low_stock_res = db_query($conn, $low_stock_query);
    $low_stock_data = db_fetch_assoc($low_stock_res);
    $low_stock_count = (is_array($low_stock_data) && isset($low_stock_data['count'])) ? intval($low_stock_data['count']) : 0;
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
    $total_stock_res = db_query($conn, $total_stock_query);
    $total_stock_data = db_fetch_assoc($total_stock_res);
    $total_stock = (is_array($total_stock_data) && isset($total_stock_data['total'])) ? intval($total_stock_data['total']) : 0;
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
    
    <!-- Left Column: Low Stock Alerts Table -->
    <div class="table-container">
        <h3 style="padding: 20px 20px 0 20px; margin: 0; color: var(--error);"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Threshold</th>
                    <th>Status</th>
                    <?php if (isAdmin()): ?>
                    <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $alerts = db_query($conn, "SELECT product_id, product_name, category, stock_quantity, low_stock_threshold, unit FROM products WHERE stock_quantity <= low_stock_threshold ORDER BY stock_quantity ASC");
                $colspan = isAdmin() ? 6 : 5;
                if(db_num_rows($alerts) == 0) {
                    echo "<tr><td colspan='$colspan' style='text-align:center;'>No alerts at the moment.</td></tr>";
                }
                while($a = db_fetch_assoc($alerts)) {
                    $stock_class = "color: #EF4444; font-weight: bold;";
                    $stock_status = "Out of Stock";
                    if ($a['stock_quantity'] > 0) {
                        $stock_class = "color: #F59E0B; font-weight: bold;";
                        $stock_status = "Low Stock";
                    }
                ?>
                <tr>
                    <td style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($a['product_name']); ?></td>
                    <td>
                        <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #F3F4F6; color: #4B5563;">
                            <?php echo htmlspecialchars($a['category']); ?>
                        </span>
                    </td>
                    <td style="<?php echo $stock_class; ?>"><?php echo $a['stock_quantity'] . ' ' . $a['unit']; ?></td>
                    <td><?php echo $a['low_stock_threshold']; ?></td>
                    <td><span style="<?php echo $stock_class; ?>"><?php echo $stock_status; ?></span></td>
                    <?php if (isAdmin()): ?>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                            <input type="hidden" name="delete_product" value="<?php echo $a['product_id']; ?>">
                            <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.85rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Right Column: Adjustment Form -->
    <div class="table-container">
        <h3 style="padding: 20px 20px 0 20px; margin: 0; color: var(--primary);"><i class="fa-solid fa-pen-to-square"></i> Adjust Stock</h3>
        <form method="POST" style="padding: 20px;">
            <div class="form-group">
                <label>Select Product</label>
                <select name="product_id" required style="width: 100%;">
                    <option value="">-- Choose Product --</option>
                    <?php
                    $prods = db_query($conn, "SELECT product_id, product_name, stock_quantity, unit FROM products ORDER BY product_name ASC");
                    while($p = db_fetch_assoc($prods)){
                    ?>
                    <option value="<?php echo $p['product_id']; ?>">
                        <?php echo htmlspecialchars($p['product_name']); ?> (Stock: <?php echo $p['stock_quantity'] . ' ' . $p['unit']; ?>)
                    </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" required min="1" style="width: 100%;">
            </div>

            <div class="form-group">
                <label>Adjustment Type</label>
                <select name="adj_type" required style="width: 100%;">
                    <option value="IN">Stock IN (Restock)</option>
                    <option value="DAMAGE">Stock OUT (Damage/Waste)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <input type="text" name="remarks" placeholder="Optional notes..." style="width: 100%;">
            </div>

            <button type="submit" name="adjust_stock" class="btn" style="width: 100%; background: var(--primary); color: white; border: none; padding: 10px; cursor: pointer; border-radius: 6px; font-weight: 600;">
                Save Adjustment
            </button>
        </form>
    </div>
</div>

<!-- All Products Section (Full Width Below) -->
<div class="table-container" style="margin-top: 30px;">
    <h3 style="padding: 20px; margin: 0; color: var(--primary); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <span><i class="fa-solid fa-box"></i> All Products</span>
        <span style="display: flex; gap: 10px; align-items: center;">
            <a href="products.php" class="btn-primary" style="padding: 8px 16px; font-size: 0.9rem; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;"><i class="fa-solid fa-plus-circle"></i> Add New Product</a>
            <?php if (isAdmin()): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('WARNING: This will permanently delete ALL products and associated logs! Are you sure?');">
                <button type="submit" name="delete_all_products" class="btn-primary" style="padding: 8px 16px; font-size: 0.9rem; background-color: #EF4444; border-radius: 6px; border: none; cursor: pointer;"><i class="fa-solid fa-trash-can"></i> Delete All Products</button>
            </form>
            <?php endif; ?>
        </span>
    </h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Stock Status</th>
                <th>Current Stock</th>
                <th>Unit</th>
                <?php if (isAdmin()): ?>
                <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $all_products = db_query($conn, "SELECT * FROM products ORDER BY product_name ASC");
            if (db_num_rows($all_products) == 0) {
                $colspan = isAdmin() ? 6 : 5;
                echo "<tr><td colspan='$colspan' style='text-align:center; color:var(--text-muted); padding:20px;'>No products in inventory.</td></tr>";
            }
            while($p = db_fetch_assoc($all_products)) {
                $stock_class = "color: #10B981; font-weight: bold;";
                $stock_status = "In Stock";
                if ($p['stock_quantity'] <= 0) {
                    $stock_class = "color: #EF4444; font-weight: bold;";
                    $stock_status = "Out of Stock";
                } else if ($p['stock_quantity'] <= $p['low_stock_threshold']) {
                    $stock_class = "color: #F59E0B; font-weight: bold;";
                    $stock_status = "Low Stock";
                }
            ?>
            <tr>
                <td style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($p['product_name']); ?></td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #F3F4F6; color: #4B5563;">
                        <?php echo htmlspecialchars($p['category']); ?>
                    </span>
                </td>
                <td>
                    <span style="<?php echo $stock_class; ?>"><?php echo $stock_status; ?></span>
                </td>
                <td style="font-weight: bold; color: var(--primary-color);"><?php echo $p['stock_quantity']; ?></td>
                <td><?php echo htmlspecialchars($p['unit']); ?></td>
                <?php if (isAdmin()): ?>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                        <input type="hidden" name="delete_product" value="<?php echo $p['product_id']; ?>">
                        <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.85rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Stock Activity Log Section (Full Width Below) -->
<div class="table-container" style="margin-top: 30px;">
    <h3 style="padding: 20px; margin: 0; color: var(--primary);"><i class="fa-solid fa-history"></i> Recent Stock Activity Log</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Product Name</th>
                <th>Adjustment Qty</th>
                <th>Activity Type</th>
                <th>Remaining Stock</th>
                <th>Remarks/Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT l.*, p.product_name, p.unit, p.stock_quantity FROM inventory_logs l JOIN products p ON l.product_id = p.product_id ORDER BY l.id DESC LIMIT 50";
            $log_res = db_query($conn, $query);
            if(db_num_rows($log_res) == 0) {
                echo "<tr><td colspan='6' style='text-align:center; color:var(--text-muted); padding:20px;'>No stock logs available yet.</td></tr>";
            }
            while($row = db_fetch_assoc($log_res)) {
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
                <td style="color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars($row['remarks'] ?? ''); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

