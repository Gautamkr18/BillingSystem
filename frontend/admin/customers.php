<?php
include '../../backend/includes/auth.php';
include '../../backend/includes/db.php';

// Handle Delete (Admin Only)
if(isset($_POST['delete_customer'])){
    restrictToAdmin();
    $del_id = $_POST['delete_id'] ?? '';
    
    if(!empty($del_id)) {
        db_query_prepared($conn, "DELETE FROM customers WHERE customer_id = :id", [':id' => $del_id]);
        db_query_prepared($conn, "DELETE FROM customer_ledger WHERE customer_id = :id", [':id' => $del_id]);
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query_prepared($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:uid, :username, 'Delete Customer', :details)", [
            ':uid' => $uid,
            ':username' => $username,
            ':details' => "Deleted customer ID $del_id"
        ]);
        
        $_SESSION['success_message'] = "Customer Deleted Successfully";
    } else {
        $_SESSION['error_message'] = "Invalid customer ID for deletion";
    }
    
    header("Location: customers.php");
    exit();
}

// Handle Add
if(isset($_POST['add_customer'])){
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gstin = trim($_POST['gstin'] ?? '');
    $credit = !empty($_POST['credit_balance']) ? floatval($_POST['credit_balance']) : 0.00;

    // Log request receipt to detect duplicate submissions
    db_log("Add Customer request received. POST: name='$name', phone='$phone', email='$email', ip='" . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN') . "', ua='" . ($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN') . "'");

    // Backend validation: Name is mandatory
    if(empty($name)) {
        db_log("Add Customer failed: Customer Name is empty.");
        $_SESSION['error_message'] = "Customer Name is required";
        header("Location: customers.php");
        exit();
    }

    // Backend validation: Duplicate checks
    if(!empty($phone)) {
        $check_phone = db_query_prepared($conn, "SELECT customer_id FROM customers WHERE phone = :phone", [':phone' => $phone]);
        if(db_num_rows($check_phone) > 0) {
            db_log("Duplicate customer insert blocked: Phone number '$phone' already exists in SQLite.");
            $_SESSION['error_message'] = "A customer with this phone number already exists.";
            header("Location: customers.php");
            exit();
        }
    }
    
    if(!empty($email)) {
        $check_email = db_query_prepared($conn, "SELECT customer_id FROM customers WHERE email = :email", [':email' => $email]);
        if(db_num_rows($check_email) > 0) {
            db_log("Duplicate customer insert blocked: Email address '$email' already exists in SQLite.");
            $_SESSION['error_message'] = "A customer with this email address already exists.";
            header("Location: customers.php");
            exit();
        }
    }

    db_log("Attempting SQLite INSERT statement for customer '$name'...");

    // Secure Prepared Statement insertion
    $inserted = db_query_prepared($conn, 
        "INSERT INTO customers(name, phone, email, address, gstin, credit_balance) VALUES(:name, :phone, :email, :address, :gstin, :credit)",
        [
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address,
            ':gstin' => $gstin,
            ':credit' => $credit
        ]
    );

    if($inserted) {
        $new_id = db_insert_id($conn);
        db_log("SQLite INSERT statement completed. New customer created successfully with Row ID #$new_id.");
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query_prepared($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:uid, :username, 'Add Customer', :details)", [
            ':uid' => $uid,
            ':username' => $username,
            ':details' => "Added customer $name"
        ]);
        
        if($credit > 0) {
            // Log to customer ledger as opening credit balance
            db_query_prepared($conn, "INSERT INTO customer_ledger(customer_id, type, amount, description) VALUES (:customer_id, 'CREDIT', :amount, 'Opening Balance')", [
                ':customer_id' => $new_id,
                ':amount' => $credit
            ]);
        }
        
        $_SESSION['success_message'] = "Customer Added Successfully";
    } else {
        db_log("SQLite INSERT statement failed. Database error: " . db_error($conn));
        $_SESSION['error_message'] = "Failed to add customer. Please try again.";
    }

    header("Location: customers.php");
    exit();
}

// Handle Update
if(isset($_POST['update_customer'])){
    $update_id = $_POST['customer_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gstin = trim($_POST['gstin'] ?? '');
    $credit = !empty($_POST['credit_balance']) ? floatval($_POST['credit_balance']) : 0.00;

    if(empty($update_id)) {
        $_SESSION['error_message'] = "Invalid customer ID";
        header("Location: customers.php");
        exit();
    }

    if(empty($name)) {
        $_SESSION['error_message'] = "Customer Name is required";
        header("Location: customers.php?edit=" . urlencode($update_id));
        exit();
    }

    // Backend validation: Duplicate checks excluding self
    if(!empty($phone)) {
        $check_phone = db_query_prepared($conn, "SELECT customer_id FROM customers WHERE phone = :phone AND customer_id != :id", [
            ':phone' => $phone,
            ':id' => $update_id
        ]);
        if(db_num_rows($check_phone) > 0) {
            $_SESSION['error_message'] = "A customer with this phone number already exists.";
            header("Location: customers.php?edit=" . urlencode($update_id));
            exit();
        }
    }
    
    if(!empty($email)) {
        $check_email = db_query_prepared($conn, "SELECT customer_id FROM customers WHERE email = :email AND customer_id != :id", [
            ':email' => $email,
            ':id' => $update_id
        ]);
        if(db_num_rows($check_email) > 0) {
            $_SESSION['error_message'] = "A customer with this email address already exists.";
            header("Location: customers.php?edit=" . urlencode($update_id));
            exit();
        }
    }

    // Secure Prepared Statement update
    $updated = db_query_prepared($conn, 
        "UPDATE customers SET name=:name, phone=:phone, email=:email, address=:address, gstin=:gstin, credit_balance=:credit WHERE customer_id=:id",
        [
            ':name' => $name,
            ':phone' => $phone,
            ':email' => $email,
            ':address' => $address,
            ':gstin' => $gstin,
            ':credit' => $credit,
            ':id' => $update_id
        ]
    );
    
    if($updated) {
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query_prepared($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:uid, :username, 'Update Customer', :details)", [
            ':uid' => $uid,
            ':username' => $username,
            ':details' => "Updated customer $name"
        ]);

        $_SESSION['success_message'] = "Customer Updated Successfully";
    } else {
        $_SESSION['error_message'] = "Failed to update customer. Please try again.";
    }

    header("Location: customers.php");
    exit();
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
    $edit_result = db_query_prepared($conn, "SELECT * FROM customers WHERE customer_id = :id", [':id' => $edit_id]);
    if(db_num_rows($edit_result) > 0){
        $edit_mode = true;
        $edit_data = db_fetch_assoc($edit_result);
    }
}

// Include header after redirection logic to avoid headers already sent issues
include '../includes/header.php';
?>

<div class="page-header">
    <h2>Manage Customers</h2>
</div>

<!-- Session-based styled Alerts -->
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

<div class="card-form">
    <h3><i class="fa-solid fa-<?php echo $edit_mode ? 'edit' : 'user-plus'; ?>"></i> <?php echo $edit_mode ? 'Edit Customer Details' : 'Add New Customer'; ?></h3>
    <br>
    <form method="POST" action="customers.php" id="customer-form">
        <?php if($edit_mode): ?>
            <input type="hidden" name="update_customer" value="1">
            <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($edit_id); ?>">
        <?php else: ?>
            <input type="hidden" name="add_customer" value="1">
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
            <button type="submit" id="customer-submit-btn" class="btn-primary" style="background:#10B981;"><i class="fa-solid fa-save"></i> Update Customer</button>
            <a href="customers.php" class="btn-primary" style="background:#6B7280; margin-left: 10px; text-decoration:none;">Cancel</a>
        <?php else: ?>
            <button type="submit" id="customer-submit-btn" class="btn-primary"><i class="fa-solid fa-save"></i> Save Customer</button>
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
            $result = db_query_prepared($conn, "SELECT * FROM customers ORDER BY customer_id DESC");
            if($result) {
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
            <?php 
                } 
            }
            ?>
        </tbody>
    </table>
</div>

<script>
let isCustomerSubmitted = false;
document.getElementById('customer-form').addEventListener('submit', function(e) {
    if (isCustomerSubmitted) {
        e.preventDefault();
        return false;
    }
    isCustomerSubmitted = true;
    const btn = document.getElementById('customer-submit-btn');
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
