<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/header.php';

// Handle Delete (Admin Only)
if(isset($_POST['delete_customer'])){
    restrictToAdmin();
    $del_id = $_POST['delete_id'];
    db_query($conn,"DELETE FROM customers WHERE customer_id='$del_id'");
    db_query($conn,"DELETE FROM customer_ledger WHERE customer_id='$del_id'");
    
    // Log Activity
    $username = $_SESSION['username'];
    $uid = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Delete Customer', 'Deleted customer ID $del_id')");
    
    echo "<script>alert('Customer Deleted Successfully'); window.location='customers.php';</script>";
}

// Handle Add
if(isset($_POST['add_customer'])){
    $name = db_real_escape_string($conn, $_POST['name']);
    $phone = db_real_escape_string($conn, $_POST['phone']);
    $email = db_real_escape_string($conn, $_POST['email']);
    $address = db_real_escape_string($conn, $_POST['address']);
    $gstin = db_real_escape_string($conn, $_POST['gstin']);
    $credit = !empty($_POST['credit_balance']) ? floatval($_POST['credit_balance']) : 0.00;

    db_query($conn,"INSERT INTO customers(name,phone,email,address,gstin,credit_balance)
    VALUES('$name','$phone','$email','$address','$gstin','$credit')");
    $new_id = db_insert_id($conn);
    
    // Log Activity
    $username = $_SESSION['username'];
    $uid = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Add Customer', 'Added customer $name')");
    
    if($credit > 0) {
        // Log to customer ledger as opening credit balance
        db_query($conn, "INSERT INTO customer_ledger(customer_id, type, amount, description) VALUES ('$new_id', 'CREDIT', '$credit', 'Opening Balance')");
    }
    
    echo "<script>alert('Customer Added Successfully'); window.location='customers.php';</script>";
}

// Handle Update
if(isset($_POST['update_customer'])){
    $update_id = $_POST['customer_id'];
    $name = db_real_escape_string($conn, $_POST['name']);
    $phone = db_real_escape_string($conn, $_POST['phone']);
    $email = db_real_escape_string($conn, $_POST['email']);
    $address = db_real_escape_string($conn, $_POST['address']);
    $gstin = db_real_escape_string($conn, $_POST['gstin']);
    $credit = !empty($_POST['credit_balance']) ? floatval($_POST['credit_balance']) : 0.00;

    db_query($conn,"UPDATE customers SET name='$name', phone='$phone', email='$email', address='$address', gstin='$gstin', credit_balance='$credit' WHERE customer_id='$update_id'");
    
    // Log Activity
    $username = $_SESSION['username'];
    $uid = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Update Customer', 'Updated customer $name')");

    echo "<script>alert('Customer Updated Successfully'); window.location='customers.php';</script>";
}

// Fetch edit mode data
$edit_mode = false;
$edit_data = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'gstin' => '',
    'credit_balance' => ''
];
if(isset($_GET['edit'])){
    $edit_id = $_GET['edit'];
    $edit_result = db_query($conn, "SELECT * FROM customers WHERE customer_id='$edit_id'");
    if(db_num_rows($edit_result) > 0){
        $edit_mode = true;
        $edit_data = db_fetch_assoc($edit_result);
    }
}
?>

<div class="page-header">
    <h2>Manage Customers</h2>
</div>

<div class="card-form">
    <h3><i class="fa-solid fa-<?php echo $edit_mode ? 'edit' : 'user-plus'; ?>"></i> <?php echo $edit_mode ? 'Edit Customer Details' : 'Add New Customer'; ?></h3>
    <br>
    <form method="POST" action="customers.php">
        <?php if($edit_mode): ?>
        <input type="hidden" name="customer_id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Customer Name</label>
                <input type="text" name="name" placeholder="Enter full name" value="<?php echo htmlspecialchars($edit_data['name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="Enter phone number" value="<?php echo htmlspecialchars($edit_data['phone']); ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($edit_data['email']); ?>">
            </div>
            <div class="form-group">
                <label>GSTIN / Tax ID</label>
                <input type="text" name="gstin" placeholder="15-digit GSTIN" maxlength="15" value="<?php echo htmlspecialchars($edit_data['gstin']); ?>">
            </div>
            <div class="form-group">
                <label>Credit Balance / Limit (₹)</label>
                <input type="number" name="credit_balance" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($edit_data['credit_balance']); ?>">
            </div>
            <div class="form-group">
                <label>Customer Address</label>
                <textarea name="address" placeholder="Enter complete address" rows="1"><?php echo htmlspecialchars($edit_data['address']); ?></textarea>
            </div>
        </div>
        <?php if($edit_mode): ?>
            <button type="submit" name="update_customer" class="btn-primary" style="background:#10B981;"><i class="fa-solid fa-save"></i> Update Customer</button>
            <a href="customers.php" class="btn-primary" style="background:#6B7280; margin-left: 10px; text-decoration:none;">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_customer" class="btn-primary"><i class="fa-solid fa-save"></i> Save Customer</button>
        <?php endif; ?>
    </form>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Contact Info</th>
                <th>GSTIN</th>
                <th>Credit Balance</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = db_query($conn,"SELECT * FROM customers ORDER BY customer_id DESC");
            while($row = db_fetch_assoc($result)){
            ?>
            <tr>
                <td>#<?php echo $row['customer_id']; ?></td>
                <td>
                    <div style="font-weight:bold; color: var(--text-main);"><?php echo htmlspecialchars($row['name']); ?></div>
                    <span style="font-size:0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['address']); ?></span>
                </td>
                <td>
                    <div><i class="fa-solid fa-phone" style="font-size:0.8rem; color:var(--text-muted);"></i> <?php echo htmlspecialchars($row['phone']); ?></div>
                    <div style="font-size:0.85rem; color: var(--text-muted);"><i class="fa-solid fa-envelope" style="font-size:0.8rem;"></i> <?php echo htmlspecialchars($row['email']); ?></div>
                </td>
                <td style="font-family: monospace; font-weight: bold;"><?php echo !empty($row['gstin']) ? htmlspecialchars($row['gstin']) : '<span style="color:#9CA3AF; font-weight:normal;">N/A</span>'; ?></td>
                <td style="font-weight:bold; color: <?php echo $row['credit_balance'] > 0 ? 'var(--primary-color)' : '#9CA3AF'; ?>;">
                    ₹<?php echo number_format($row['credit_balance'], 2); ?>
                </td>
                <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="pos.php?customer_id=<?php echo $row['customer_id']; ?>" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background-color: #10B981;" title="POS Checkout"><i class="fa-solid fa-cash-register"></i> Sell</a>
                    <a href="ledger.php?id=<?php echo $row['customer_id']; ?>" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background-color: #F59E0B;" title="Customer Ledger"><i class="fa-solid fa-book"></i> Ledger</a>
                    <a href="customers.php?edit=<?php echo $row['customer_id']; ?>" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-edit"></i> Edit</a>
                    
                    <?php if (isAdmin()): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer?');">
                        <input type="hidden" name="delete_id" value="<?php echo $row['customer_id']; ?>">
                        <button type="submit" name="delete_customer" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

