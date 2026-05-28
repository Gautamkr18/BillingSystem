<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/header.php';

// Handle Action Restrictions
if (isset($_POST['add_product']) || isset($_POST['delete_product']) || isset($_POST['update_product'])) {
    restrictToAdmin();
}

// Ensure Upload Directory Exists
$upload_dir = '../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Delete
if(isset($_POST['delete_product'])){
    $del_id = $_POST['delete_id'];
    
    // Fetch product name for logs
    $p_res = db_query($conn, "SELECT product_name FROM products WHERE product_id='$del_id'");
    $p_data = db_fetch_assoc($p_res);
    $product_name = $p_data['product_name'];
    
    db_query($conn,"DELETE FROM products WHERE product_id='$del_id'");
    
    // Log Activity
    $username = $_SESSION['username'];
    $uid = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Delete Product', 'Deleted product $product_name')");
    
    echo "<script>alert('Product Deleted Successfully'); window.location='products.php';</script>";
}

// Handle Add
if(isset($_POST['add_product'])){
    $product_name = db_real_escape_string($conn, $_POST['product_name']);
    $price = floatval($_POST['price']);
    $cost_price = floatval($_POST['cost_price']);
    $gst = floatval($_POST['gst']);
    $stock = intval($_POST['stock']);
    $category = db_real_escape_string($conn, $_POST['category']);
    $barcode = db_real_escape_string($conn, $_POST['barcode']);
    $hsn_code = db_real_escape_string($conn, $_POST['hsn_code']);
    $brand = db_real_escape_string($conn, $_POST['brand']);
    $supplier = db_real_escape_string($conn, $_POST['supplier']);
    $unit = db_real_escape_string($conn, $_POST['unit']);
    $expiry_date = !empty($_POST['expiry_date']) ? "'".$_POST['expiry_date']."'" : "NULL";
    $low_stock = intval($_POST['low_stock']);
    
    // Prevent duplicate product names
    $check_exists = db_query($conn, "SELECT product_id FROM products WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$product_name'))");
    if ($check_exists && db_num_rows($check_exists) > 0) {
        echo "<script>alert('Error: A product with the name \"$product_name\" already exists!'); window.location='products.php';</script>";
        exit();
    }

    // Handle Image Upload
    $image_path = "";
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_name = time() . '_' . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            $image_path = 'uploads/' . $file_name;
        }
    }

    $query = "INSERT INTO products (product_name, price, cost_price, gst_percentage, stock_quantity, category, barcode, hsn_code, brand, supplier, image_path, expiry_date, unit, low_stock_threshold)
              VALUES ('$product_name', '$price', '$cost_price', '$gst', '$stock', '$category', '$barcode', '$hsn_code', '$brand', '$supplier', '$image_path', $expiry_date, '$unit', '$low_stock')";
              
    if(db_query($conn, $query)) {
        $new_id = db_insert_id($conn);
        // Log to inventory logs
        db_query($conn, "INSERT INTO inventory_logs(product_id, quantity, type, remarks) VALUES ('$new_id', '$stock', 'IN', 'Opening Stock')");
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Add Product', 'Added product $product_name')");
        
        echo "<script>alert('Product Added Successfully'); window.location='products.php';</script>";
    } else {
        echo "<script>alert('Error adding product: " . db_error($conn) . "');</script>";
    }
}

// Handle Update
if(isset($_POST['update_product'])){
    $update_id = $_POST['product_id'];
    $product_name = db_real_escape_string($conn, $_POST['product_name']);
    $price = floatval($_POST['price']);
    $cost_price = floatval($_POST['cost_price']);
    $gst = floatval($_POST['gst']);
    $stock = intval($_POST['stock']);
    $category = db_real_escape_string($conn, $_POST['category']);
    $barcode = db_real_escape_string($conn, $_POST['barcode']);
    $hsn_code = db_real_escape_string($conn, $_POST['hsn_code']);
    $brand = db_real_escape_string($conn, $_POST['brand']);
    $supplier = db_real_escape_string($conn, $_POST['supplier']);
    $unit = db_real_escape_string($conn, $_POST['unit']);
    $expiry_date = !empty($_POST['expiry_date']) ? "'".$_POST['expiry_date']."'" : "NULL";
    $low_stock = intval($_POST['low_stock']);
    
    // Prevent duplicate product names on update (excluding itself)
    $check_exists = db_query($conn, "SELECT product_id FROM products WHERE LOWER(TRIM(product_name)) = LOWER(TRIM('$product_name')) AND product_id != '$update_id'");
    if ($check_exists && db_num_rows($check_exists) > 0) {
        echo "<script>alert('Error: A product with the name \"$product_name\" already exists!'); window.location='products.php';</script>";
        exit();
    }

    // Handle Image Upload
    $image_update_sql = "";
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_name = time() . '_' . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            $image_path = 'uploads/' . $file_name;
            $image_update_sql = ", image_path='$image_path'";
        }
    }

    $query = "UPDATE products SET 
              product_name='$product_name', 
              price='$price', 
              cost_price='$cost_price', 
              gst_percentage='$gst', 
              stock_quantity='$stock', 
              category='$category', 
              barcode='$barcode', 
              hsn_code='$hsn_code', 
              brand='$brand', 
              supplier='$supplier', 
              expiry_date=$expiry_date, 
              unit='$unit', 
              low_stock_threshold='$low_stock' 
              $image_update_sql 
              WHERE product_id='$update_id'";
              
    if(db_query($conn, $query)) {
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Update Product', 'Updated product $product_name')");
        
        echo "<script>alert('Product Updated Successfully'); window.location='products.php';</script>";
    } else {
        echo "<script>alert('Error updating product: " . db_error($conn) . "');</script>";
    }
}

// Fetch details if Edit is requested
$edit_mode = false;
$edit_data = [
    'product_name' => '',
    'brand' => '',
    'supplier' => '',
    'price' => '0.00',
    'cost_price' => '0.00',
    'gst_percentage' => '18',
    'stock_quantity' => '0',
    'category' => 'General',
    'barcode' => '',
    'hsn_code' => '8473',
    'expiry_date' => '',
    'unit' => 'pcs',
    'low_stock_threshold' => '5',
    'image_path' => ''
];
if(isset($_GET['edit']) && isAdmin()){
    $edit_id = $_GET['edit'];
    $edit_result = db_query($conn, "SELECT * FROM products WHERE product_id='$edit_id'");
    if(db_num_rows($edit_result) > 0){
        $edit_mode = true;
        $edit_data = db_fetch_assoc($edit_result);
    }
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0;">Manage Products & Inventory</h2>
    <?php if (isAdmin()): ?>
        <a href="add_electrical_products.php" class="btn-primary" style="background: #4F46E5; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 10px 18px; border-radius: 8px; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.15);">
            <i class="fa-solid fa-bolt"></i> Load Electrical Products
        </a>
    <?php endif; ?>
</div>

<!-- Left Form only shown to Admins -->
<?php if (isAdmin()): ?>
<div class="card-form">
    <h3><i class="fa-solid fa-<?php echo $edit_mode ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $edit_mode ? 'Edit Product Details' : 'Add New Product'; ?></h3>
    <br>
    <form method="POST" action="products.php" enctype="multipart/form-data">
        <?php if($edit_mode): ?>
        <input type="hidden" name="product_id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="product_name" placeholder="Enter product name" value="<?php echo htmlspecialchars($edit_data['product_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" placeholder="e.g., Hardware, Tools, Pipes" value="<?php echo htmlspecialchars($edit_data['category']); ?>" required>
            </div>
            <div class="form-group">
                <label>Price (₹ Selling Price)</label>
                <input type="number" name="price" placeholder="0.00" step="0.01" value="<?php echo $edit_data['price']; ?>" required>
            </div>
            <div class="form-group">
                <label>Cost Price (₹ Buy Price)</label>
                <input type="number" name="cost_price" placeholder="0.00" step="0.01" value="<?php echo $edit_data['cost_price']; ?>" required>
            </div>
            <div class="form-group">
                <label>GST Percentage (%)</label>
                <input type="number" name="gst" placeholder="e.g., 18" step="0.01" value="<?php echo $edit_data['gst_percentage']; ?>" required>
            </div>
            <div class="form-group">
                <label>Initial Stock Quantity</label>
                <input type="number" name="stock" placeholder="Enter quantity" value="<?php echo $edit_data['stock_quantity']; ?>" required>
            </div>
            <div class="form-group">
                <label>Barcode / GTIN</label>
                <input type="text" name="barcode" placeholder="Scan or enter barcode" value="<?php echo htmlspecialchars($edit_data['barcode']); ?>">
            </div>
            <div class="form-group">
    <label>HSN / SAC Code</label>
    <input type="text" name="hsn_code" placeholder="GST HSN Code" value="<?php echo htmlspecialchars($edit_data['hsn_code']); ?>" required>
</div>
<div class="form-group">
    <label>Brand</label>
    <input type="text" name="brand" placeholder="Brand name" value="<?php echo htmlspecialchars($edit_data['brand'] ?? ''); ?>">
</div>
<div class="form-group">
    <label>Supplier</label>
    <input type="text" name="supplier" placeholder="Supplier name" value="<?php echo htmlspecialchars($edit_data['supplier'] ?? ''); ?>">
</div>
<div class="form-group">
    <label>Measurement Unit</label>
    <select name="unit" required>
        <option value="pcs" <?php echo $edit_data['unit'] == 'pcs' ? 'selected' : ''; ?>>Pieces (pcs)</option>
        <option value="kg" <?php echo $edit_data['unit'] == 'kg' ? 'selected' : ''; ?>>Kilograms (kg)</option>
        <option value="mtr" <?php echo $edit_data['unit'] == 'mtr' ? 'selected' : ''; ?>>Meters (mtr)</option>
        <option value="box" <?php echo $edit_data['unit'] == 'box' ? 'selected' : ''; ?>>Boxes (box)</option>
    </select>
</div>
            <div class="form-group">
                <label>Low Stock Warning Limit</label>
                <input type="number" name="low_stock" placeholder="Alert threshold" value="<?php echo $edit_data['low_stock_threshold']; ?>" required>
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value="<?php echo $edit_data['expiry_date']; ?>">
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="product_image" accept="image/*">
                <?php if ($edit_mode && !empty($edit_data['image_path'])): ?>
                    <span style="font-size:0.8rem; margin-top:5px; display:block;">Current Image: <a href="../<?php echo $edit_data['image_path']; ?>" target="_blank">View Image</a></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if($edit_mode): ?>
            <button type="submit" name="update_product" class="btn-primary" style="background:#10B981;"><i class="fa-solid fa-save"></i> Update Product</button>
            <a href="products.php" class="btn-primary" style="background:#6B7280; margin-left: 10px; text-decoration:none;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_product" class="btn-primary"><i class="fa-solid fa-save"></i> Save Product</button>
        <?php endif; ?>
    </form>
</div>
<?php else: ?>
<div class="alert-error" style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color); border-color: rgba(79, 70, 229, 0.3); margin-bottom: 25px;">
    <strong>Notice:</strong> Cashier accounts are in read-only mode for product definitions. Please contact an administrator to manage products.
</div>
<?php endif; ?>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Image</th>
                <th>Product Details</th>
                <th>HSN/Barcode</th>
                <th>Price (Buy/Sell)</th>
                <th>GST</th>
                <th>Stock Level</th>
                <?php if (isAdmin()): ?>
                <th>Action</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = db_query($conn,"SELECT * FROM products ORDER BY product_id DESC");
            while($row = db_fetch_assoc($result)){
                $stock_class = "color: #10B981; font-weight: bold;";
                $stock_status = "In Stock";
                if ($row['stock_quantity'] <= 0) {
                    $stock_class = "color: #EF4444; font-weight: bold;";
                    $stock_status = "Out of Stock";
                } else if ($row['stock_quantity'] <= $row['low_stock_threshold']) {
                    $stock_class = "color: #F59E0B; font-weight: bold;";
                    $stock_status = "Low Stock";
                }
            ?>
            <tr>
                <td>
                    <?php if (!empty($row['image_path'])): ?>
                        <img src="../<?php echo $row['image_path']; ?>" alt="Product" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid #E5E7EB;">
                    <?php else: ?>
                        <div style="width: 50px; height: 50px; border-radius: 8px; background: #F3F4F6; display: flex; align-items: center; justify-content: center; color: #9CA3AF; border: 1px solid #E5E7EB;">
                            <i class="fa-solid fa-box-open" style="font-size: 1.2rem;"></i>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:bold; color: var(--text-main);"><?php echo htmlspecialchars($row['product_name']); ?></div>
                    <span class="badge" style="padding: 2px 6px; font-size: 0.75rem; background: #F3F4F6; color: #4B5563; border-radius: 4px;"><?php echo htmlspecialchars($row['category']); ?></span>
                    <?php if (!empty($row['expiry_date'])): ?>
                        <span style="font-size:0.75rem; color:var(--error); display:block; margin-top:2px;"><i class="fa-solid fa-hourglass-half"></i> Exp: <?php echo date('d M Y', strtotime($row['expiry_date'])); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size: 0.85rem;"><strong>HSN:</strong> <?php echo htmlspecialchars($row['hsn_code']); ?></div>
                    <?php if(!empty($row['barcode'])): ?>
                        <div style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;"><i class="fa-solid fa-barcode"></i> <?php echo htmlspecialchars($row['barcode']); ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div><strong>Sell:</strong> ₹<?php echo number_format($row['price'], 2); ?></div>
                    <?php if (isAdmin()): ?>
                        <div style="font-size: 0.85rem; color: var(--text-muted);"><strong>Cost:</strong> ₹<?php echo number_format($row['cost_price'], 2); ?></div>
                    <?php endif; ?>
                </td>
                <td style="font-weight: 500;"><?php echo $row['gst_percentage']; ?>%</td>
                <td>
                    <div style="<?php echo $stock_class; ?>"><?php echo $row['stock_quantity']; ?> <?php echo $row['unit']; ?></div>
                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $stock_status; ?></span>
                </td>
                <?php if (isAdmin()): ?>
                <td style="display: flex; gap: 8px;">
                    <a href="products.php?edit=<?php echo $row['product_id']; ?>" class="btn-primary" style="padding: 6px 12px; font-size: 0.85rem;"><i class="fa-solid fa-edit"></i> Edit</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                        <input type="hidden" name="delete_id" value="<?php echo $row['product_id']; ?>">
                        <button type="submit" name="delete_product" class="btn-primary" style="padding: 6px 12px; font-size: 0.85rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

