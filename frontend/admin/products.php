<?php
include '../../backend/includes/auth.php';
include '../../backend/includes/db.php';

// Handle Action Restrictions
if (isset($_POST['delete_product']) || isset($_POST['update_product'])) {
    restrictToAdmin();
}

// Ensure Upload Directory Exists at the Root of the application
$upload_dir = '../../uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Delete
if(isset($_POST['delete_product'])){
    $del_id = $_POST['delete_id'] ?? '';
    
    if (!empty($del_id)) {
        // Fetch product name for logs using prepared statement
        $p_res = db_query_prepared($conn, "SELECT product_name FROM products WHERE product_id = :id", [':id' => $del_id]);
        if (db_num_rows($p_res) > 0) {
            $p_data = db_fetch_assoc($p_res);
            $product_name = $p_data['product_name'];
            
            db_query_prepared($conn, "DELETE FROM products WHERE product_id = :id", [':id' => $del_id]);
            
            // Log Activity
            $username = $_SESSION['username'];
            $uid = $_SESSION['user_id'];
            db_query_prepared($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:uid, :username, 'Delete Product', :details)", [
                ':uid' => $uid,
                ':username' => $username,
                ':details' => "Deleted product $product_name"
            ]);
            
            $_SESSION['success_message'] = "Product Deleted Successfully";
        } else {
            $_SESSION['error_message'] = "Product not found.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid product ID.";
    }
    
    header("Location: products.php");
    exit();
}

// Handle Add
if(isset($_POST['add_product'])){
    $product_name = trim($_POST['product_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $gst = floatval($_POST['gst'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $hsn_code = trim($_POST['hsn_code'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $low_stock = intval($_POST['low_stock'] ?? 5);
    
    // Log request receipt
    db_log("Add Product request received. name='$product_name', barcode='$barcode'");

    if (empty($product_name)) {
        $_SESSION['error_message'] = "Product Name is required.";
        header("Location: products.php");
        exit();
    }

    // Prevent duplicate product names using prepared query
    $check_exists = db_query_prepared($conn, "SELECT product_id FROM products WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(:name))", [':name' => $product_name]);
    if (db_num_rows($check_exists) > 0) {
        db_log("Duplicate product insert blocked: '$product_name' already exists.");
        $_SESSION['error_message'] = "Error: A product with the name \"$product_name\" already exists!";
        header("Location: products.php");
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

    $inserted = db_query_prepared($conn, 
        "INSERT INTO products (product_name, price, cost_price, gst_percentage, stock_quantity, category, barcode, hsn_code, brand, supplier, image_path, expiry_date, unit, low_stock_threshold)
         VALUES (:name, :price, :cost_price, :gst, :stock, :category, :barcode, :hsn, :brand, :supplier, :image, :expiry, :unit, :low_stock)",
        [
            ':name' => $product_name,
            ':price' => $price,
            ':cost_price' => $cost_price,
            ':gst' => $gst,
            ':stock' => $stock,
            ':category' => $category,
            ':barcode' => $barcode,
            ':hsn' => $hsn_code,
            ':brand' => $brand,
            ':supplier' => $supplier,
            ':image' => $image_path,
            ':expiry' => $expiry_date,
            ':unit' => $unit,
            ':low_stock' => $low_stock
        ]
    );
              
    if($inserted) {
        $new_id = db_insert_id($conn);
        
        // Log to inventory logs using prepared query
        db_query_prepared($conn, "INSERT INTO inventory_logs(product_id, quantity, type, remarks) VALUES (:pid, :qty, 'IN', 'Opening Stock')", [
            ':pid' => $new_id,
            ':qty' => $stock
        ]);
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query_prepared($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:uid, :username, 'Add Product', :details)", [
            ':uid' => $uid,
            ':username' => $username,
            ':details' => "Added product $product_name"
        ]);
        
        db_log("Product '$product_name' added successfully. Row ID #$new_id.");
        $_SESSION['success_message'] = "Product Added Successfully";
    } else {
        db_log("Failed to add product. DB error: " . db_error($conn));
        $_SESSION['error_message'] = "Failed to add product. Please try again.";
    }
    
    header("Location: products.php");
    exit();
}

// Handle Update
if(isset($_POST['update_product'])){
    $update_id = $_POST['product_id'] ?? '';
    $product_name = trim($_POST['product_name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $gst = floatval($_POST['gst'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $hsn_code = trim($_POST['hsn_code'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $supplier = trim($_POST['supplier'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $low_stock = intval($_POST['low_stock'] ?? 5);
    
    if (empty($update_id)) {
        $_SESSION['error_message'] = "Invalid product ID.";
        header("Location: products.php");
        exit();
    }

    if (empty($product_name)) {
        $_SESSION['error_message'] = "Product Name is required.";
        header("Location: products.php?edit=" . urlencode($update_id));
        exit();
    }

    // Prevent duplicate product names on update (excluding itself) using prepared query
    $check_exists = db_query_prepared($conn, "SELECT product_id FROM products WHERE LOWER(TRIM(product_name)) = LOWER(TRIM(:name)) AND product_id != :id", [
        ':name' => $product_name,
        ':id' => $update_id
    ]);
    
    if (db_num_rows($check_exists) > 0) {
        $_SESSION['error_message'] = "Error: A product with the name \"$product_name\" already exists!";
        header("Location: products.php?edit=" . urlencode($update_id));
        exit();
    }

    // Fetch existing image path
    $img_res = db_query_prepared($conn, "SELECT image_path FROM products WHERE product_id = :id", [':id' => $update_id]);
    $existing_image = "";
    if (db_num_rows($img_res) > 0) {
        $img_data = db_fetch_assoc($img_res);
        $existing_image = $img_data['image_path'];
    }

    // Handle Image Upload
    $image_path = $existing_image;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $file_name = time() . '_' . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            $image_path = 'uploads/' . $file_name;
        }
    }

    $updated = db_query_prepared($conn, 
        "UPDATE products SET 
         product_name = :name, 
         price = :price, 
         cost_price = :cost_price, 
         gst_percentage = :gst, 
         stock_quantity = :stock, 
         category = :category, 
         barcode = :barcode, 
         hsn_code = :hsn, 
         brand = :brand, 
         supplier = :supplier, 
         expiry_date = :expiry, 
         unit = :unit, 
         low_stock_threshold = :low_stock, 
         image_path = :image
         WHERE product_id = :id",
        [
            ':name' => $product_name,
            ':price' => $price,
            ':cost_price' => $cost_price,
            ':gst' => $gst,
            ':stock' => $stock,
            ':category' => $category,
            ':barcode' => $barcode,
            ':hsn' => $hsn_code,
            ':brand' => $brand,
            ':supplier' => $supplier,
            ':expiry' => $expiry_date,
            ':unit' => $unit,
            ':low_stock' => $low_stock,
            ':image' => $image_path,
            ':id' => $update_id
        ]
    );
              
    if($updated) {
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query_prepared($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:uid, :username, 'Update Product', :details)", [
            ':uid' => $uid,
            ':username' => $username,
            ':details' => "Updated product $product_name"
        ]);
        
        $_SESSION['success_message'] = "Product Updated Successfully";
    } else {
        $_SESSION['error_message'] = "Failed to update product. Please try again.";
    }
    
    header("Location: products.php");
    exit();
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
    $edit_result = db_query_prepared($conn, "SELECT * FROM products WHERE product_id = :id", [':id' => $edit_id]);
    if(db_num_rows($edit_result) > 0){
        $edit_mode = true;
        $edit_data = db_fetch_assoc($edit_result);
    }
}

// Include header after redirection check blocks
include '../includes/header.php';
?>

<div class="page-header">
    <h2>Manage Products & Inventory</h2>
</div>

<!-- Session Banners for feedback -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert-success">
        <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert-error">
        <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    </div>
<?php endif; ?>

<!-- Left Form shown to Admins (Add/Edit) and Cashiers (Add only) -->
<?php if (isAdmin() || !$edit_mode): ?>
<div class="card-form">
    <h3><i class="fa-solid fa-<?php echo $edit_mode ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $edit_mode ? 'Edit Product Details' : 'Add New Product'; ?></h3>
    <br>
    <form method="POST" action="products.php" enctype="multipart/form-data" id="product-form">
        <?php if($edit_mode): ?>
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($edit_id); ?>">
            <input type="hidden" name="update_product" value="1">
        <?php else: ?>
            <input type="hidden" name="add_product" value="1">
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
                <input type="number" name="price" placeholder="0.00" step="0.01" value="<?php echo htmlspecialchars($edit_data['price']); ?>" required>
            </div>
            <div class="form-group">
                <label>Cost Price (₹ Buy Price)</label>
                <input type="number" name="cost_price" placeholder="0.00" step="0.01" value="<?php echo htmlspecialchars($edit_data['cost_price']); ?>" required>
            </div>
            <div class="form-group">
                <label>GST Percentage (%)</label>
                <input type="number" name="gst" placeholder="e.g., 18" step="0.01" value="<?php echo htmlspecialchars($edit_data['gst_percentage']); ?>" required>
            </div>
            <div class="form-group">
                <label>Initial Stock Quantity</label>
                <input type="number" name="stock" placeholder="Enter quantity" value="<?php echo htmlspecialchars($edit_data['stock_quantity']); ?>" required>
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
                <input type="number" name="low_stock" placeholder="Alert threshold" value="<?php echo htmlspecialchars($edit_data['low_stock_threshold']); ?>" required>
            </div>
            <div class="form-group">
                <label>Expiry Date</label>
                <input type="date" name="expiry_date" value="<?php echo htmlspecialchars($edit_data['expiry_date']); ?>">
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="product_image" accept="image/*">
                <?php if ($edit_mode && !empty($edit_data['image_path'])): ?>
                    <span style="font-size:0.8rem; margin-top:5px; display:block;">Current Image: <a href="../../<?php echo $edit_data['image_path']; ?>" target="_blank">View Image</a></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if($edit_mode): ?>
            <button type="submit" id="product-submit-btn" class="btn-primary" style="background:#10B981;"><i class="fa-solid fa-save"></i> Update Product</button>
            <a href="products.php" class="btn-primary" style="background:#6B7280; margin-left: 10px; text-decoration:none;">Cancel</a>
        <?php else: ?>
            <button type="submit" id="product-submit-btn" class="btn-primary"><i class="fa-solid fa-save"></i> Save Product</button>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>

<?php if (!isAdmin()): ?>
<div class="alert-info" style="background: rgba(16, 185, 129, 0.08); color: var(--secondary-color); border: 1px solid rgba(16, 185, 129, 0.2); padding: 12px; border-radius: 8px; margin-bottom: 25px; font-size: 0.9rem;">
    <i class="fa-solid fa-circle-info"></i> <strong>Cashier Permission:</strong> You have permission to add new products to the inventory. To modify or delete existing products, please contact an administrator.
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
            $result = db_query_prepared($conn, "SELECT * FROM products ORDER BY product_id DESC");
            if ($result) {
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
                        <img src="../../<?php echo $row['image_path']; ?>" alt="Product" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid #E5E7EB;">
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
                        <div style="font-size: 0.8 / 1rem; color: var(--text-muted); font-family: monospace;"><i class="fa-solid fa-barcode"></i> <?php echo htmlspecialchars($row['barcode']); ?></div>
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
            <?php 
                } 
            }
            ?>
        </tbody>
    </table>
</div>

<script>
let isProductSubmitted = false;
document.getElementById('product-form').addEventListener('submit', function(e) {
    if (isProductSubmitted) {
        e.preventDefault();
        return false;
    }
    isProductSubmitted = true;
    const btn = document.getElementById('product-submit-btn');
    if (btn) {
        // Disable button asynchronously to prevent standard submit event cancellation in some browsers
        setTimeout(() => {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        }, 10);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
