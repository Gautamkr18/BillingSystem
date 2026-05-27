<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/header.php';

if(!isset($_GET['id'])) {
    echo "<script>alert('Customer ID is required'); window.location='customers.php';</script>";
    exit();
}

$customer_id = $_GET['id'];
$cust_res = mysqli_query($conn, "SELECT * FROM customers WHERE customer_id='$customer_id'");
$customer = mysqli_fetch_assoc($cust_res);

if(!$customer) {
    echo "<script>alert('Customer not found'); window.location='customers.php';</script>";
    exit();
}

// Handle Record Payment
if(isset($_POST['record_payment'])){
    $amount = floatval($_POST['amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    
    if($amount <= 0) {
        echo "<script>alert('Please enter a valid amount.');</script>";
    } else {
        // 1. Insert into customer_ledger
        $desc = "Payment Received via $payment_method - " . $remarks;
        mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, type, amount, description) 
                             VALUES ('$customer_id', 'CREDIT', '$amount', '$desc')");
                             
        // 2. Update customer credit balance (decrease the outstanding balance)
        mysqli_query($conn, "UPDATE customers SET credit_balance = credit_balance - $amount WHERE customer_id='$customer_id'");
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        mysqli_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) 
                             VALUES ('$uid', '$username', 'Customer Payment', 'Received ₹" . number_format($amount, 2) . " from customer " . $customer['name'] . "')");
                             
        echo "<script>alert('Payment Recorded Successfully!'); window.location='ledger.php?id=$customer_id';</script>";
    }
}
?>

<div class="page-header">
    <h2>Customer Ledger Statement</h2>
    <a href="customers.php" class="btn-primary" style="background:#6B7280; text-decoration:none;"><i class="fa-solid fa-arrow-left"></i> Back to Customers</a>
</div>

<!-- Customer Profile Summary Card -->
<div class="dashboard-cards" style="grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <div class="stat-card" style="border-left-color: var(--primary-color); display: block; height: 100%; box-sizing: border-box;">
        <h3 style="margin-top:0; color:var(--primary-color); font-size:1.2rem; border-bottom:1px solid #eee; padding-bottom:10px;">
            <i class="fa-solid fa-user"></i> Customer Information
        </h3>
        <table style="width:100%; border-collapse:collapse; margin-top:15px; font-size:0.95rem; line-height:1.8;">
            <tr>
                <td style="font-weight:bold; width:120px; color:var(--text-muted);">Name:</td>
                <td style="font-weight:bold;"><?php echo htmlspecialchars($customer['name']); ?></td>
            </tr>
            <tr>
                <td style="font-weight:bold; color:var(--text-muted);">Phone:</td>
                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
            </tr>
            <tr>
                <td style="font-weight:bold; color:var(--text-muted);">Email:</td>
                <td><?php echo htmlspecialchars($customer['email']); ?></td>
            </tr>
            <tr>
                <td style="font-weight:bold; color:var(--text-muted);">GSTIN:</td>
                <td style="font-family:monospace; font-weight:bold;"><?php echo !empty($customer['gstin']) ? htmlspecialchars($customer['gstin']) : 'N/A'; ?></td>
            </tr>
            <tr>
                <td style="font-weight:bold; color:var(--text-muted);">Address:</td>
                <td><?php echo htmlspecialchars($customer['address']); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="stat-card" style="border-left-color: var(--secondary-color); display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; box-sizing: border-box; text-align:center;">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); width: 70px; height: 70px; border-radius: 50%; font-size: 2.2rem; margin-bottom:10px;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <h3 style="margin:0; font-size: 0.9rem; color:var(--text-muted); text-transform:uppercase;">Outstanding Balance</h3>
        <p style="margin:5px 0 0 0; font-size: 2.2rem; font-weight: bold; color: <?php echo $customer['credit_balance'] > 0 ? 'var(--primary-color)' : '#9CA3AF'; ?>;">
            ₹<?php echo number_format($customer['credit_balance'], 2); ?>
        </p>
    </div>
</div>

<div class="form-grid" style="grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
    <!-- Left Column: Transaction Ledger Table -->
    <div class="table-container">
        <h3 style="padding: 20px 20px 0 20px; margin: 0;"><i class="fa-solid fa-receipt"></i> Transaction History</h3>
        <br>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Debit (Purchases)</th>
                    <th>Credit (Payments)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch dynamic ledger log
                $query = "SELECT * FROM customer_ledger WHERE customer_id='$customer_id' ORDER BY id DESC";
                $res = mysqli_query($conn, $query);
                
                if(mysqli_num_rows($res) == 0) {
                    echo "<tr><td colspan='5' style='text-align:center; color:var(--text-muted); padding:30px;'>No transactions recorded for this customer.</td></tr>";
                }
                
                while($row = mysqli_fetch_assoc($res)) {
                ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.9rem;"><?php echo date('d M Y h:i A', strtotime($row['created_at'])); ?></td>
                    <td>
                        <?php if(!empty($row['invoice_id'])): ?>
                            <a href="print_invoice.php?id=<?php echo $row['invoice_id']; ?>" target="_blank" style="color:var(--primary-color); font-weight:bold; text-decoration:none;">
                                <i class="fa-solid fa-file-pdf"></i> Invoice #<?php echo str_pad($row['invoice_id'], 6, "0", STR_PAD_LEFT); ?>
                            </a>
                        <?php else: ?>
                            <span style="font-weight:600;"><?php echo htmlspecialchars($row['description']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:bold; font-size:0.8rem; padding: 4px 8px; border-radius: 4px; 
                            background-color: <?php echo $row['type'] === 'DEBIT' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>;
                            color: <?php echo $row['type'] === 'DEBIT' ? 'var(--error)' : 'var(--secondary-color)'; ?>;">
                            <?php echo $row['type']; ?>
                        </span>
                    </td>
                    <td style="font-weight:bold; color: var(--error);">
                        <?php echo $row['type'] === 'DEBIT' ? '₹' . number_format($row['amount'], 2) : '-'; ?>
                    </td>
                    <td style="font-weight:bold; color: var(--secondary-color);">
                        <?php echo $row['type'] === 'CREDIT' ? '₹' . number_format($row['amount'], 2) : '-'; ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <!-- Right Column: Record Payment Form -->
    <div class="card-form" style="margin-bottom: 0;">
        <h3><i class="fa-solid fa-wallet"></i> Record Payment</h3>
        <br>
        <form method="POST">
            <div class="form-group">
                <label>Amount Received (₹)</label>
                <input type="number" name="amount" step="0.01" min="0.01" max="<?php echo max(0, $customer['credit_balance']); ?>" placeholder="0.00" required>
                <span style="font-size:0.8rem; color:var(--text-muted); margin-top:4px; display:block;">Max applicable payment: ₹<?php echo number_format($customer['credit_balance'], 2); ?></span>
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI / QR Code</option>
                    <option value="Card">Bank Card</option>
                    <option value="Cheque">Cheque</option>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks / Notes</label>
                <input type="text" name="remarks" placeholder="Txn Ref or details">
            </div>
            <button type="submit" name="record_payment" class="btn-primary" style="width:100%; justify-content:center; background:#10B981;" <?php echo $customer['credit_balance'] <= 0 ? 'disabled style="background:#CBCBCB; cursor:not-allowed;"' : ''; ?>>
                <i class="fa-solid fa-save"></i> Save Payment
            </button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
