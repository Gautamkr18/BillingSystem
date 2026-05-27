<?php
include '../includes/auth.php';
restrictToAdmin();
include '../includes/db.php';
include '../includes/header.php';

// Handle Add Expense
if (isset($_POST['add_expense'])) {
    $expense_name = db_real_escape_string($conn, $_POST['expense_name']);
    $category = db_real_escape_string($conn, $_POST['category']);
    $amount = floatval($_POST['amount']);
    $expense_date = db_real_escape_string($conn, $_POST['expense_date']);
    $remarks = db_real_escape_string($conn, $_POST['remarks']);
    
    if ($amount <= 0) {
        echo "<script>alert('Error: Expense amount must be greater than zero.');</script>";
    } else {
        db_query($conn, "INSERT INTO expenses (expense_name, category, amount, expense_date, remarks) 
                             VALUES ('$expense_name', '$category', '$amount', '$expense_date', '$remarks')");
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Add Expense', 'Logged expense $expense_name of ₹$amount')");
        
        echo "<script>alert('Expense Logged Successfully!'); window.location='expenses.php';</script>";
    }
}

// Handle Delete Expense
if (isset($_POST['delete_expense'])) {
    $del_id = $_POST['delete_id'];
    db_query($conn, "DELETE FROM expenses WHERE id='$del_id'");
    
    // Log Activity
    $username = $_SESSION['username'];
    $uid = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Delete Expense', 'Deleted expense record ID $del_id')");
    
    echo "<script>alert('Expense Deleted Successfully'); window.location='expenses.php';</script>";
}
?>

<div class="page-header">
    <h2>Overhead Expense Tracker</h2>
</div>

<div class="form-grid" style="grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
    
    <!-- Left Column: Add Expense Form -->
    <div class="card-form" style="margin-bottom: 0;">
        <h3><i class="fa-solid fa-receipt"></i> Log Business Expense</h3>
        <br>
        <form method="POST">
            <div class="form-group">
                <label>Expense Label / Name</label>
                <input type="text" name="expense_name" placeholder="e.g. Monthly Shop Rent" required>
            </div>
            
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="Rent">Shop Rent</option>
                    <option value="Electricity">Electricity / Utilities</option>
                    <option value="Salary">Staff Salaries</option>
                    <option value="Inventory">Inventory Purchase</option>
                    <option value="Travel">Transport / Travel</option>
                    <option value="Marketing">Marketing / Advertisement</option>
                    <option value="Other">Other Miscellaneous</option>
                </select>
            </div>

            <div class="form-group">
                <label>Amount Paid (₹)</label>
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>

            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label>Remarks / Notes</label>
                <input type="text" name="remarks" placeholder="Txn Ref No or details">
            </div>

            <button type="submit" name="add_expense" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fa-solid fa-save"></i> Save Expense</button>
        </form>
    </div>

    <!-- Right Column: Recent Expenses Table -->
    <div class="table-container">
        <h3 style="padding: 20px 20px 0 20px; margin: 0;"><i class="fa-solid fa-list-check"></i> Expense History</h3>
        <br>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Expense Name</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $expenses = db_query($conn, "SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");
                if (db_num_rows($expenses) == 0) {
                    echo "<tr><td colspan='5' style='text-align:center; color:var(--text-muted); padding:20px;'>No expenses logged yet.</td></tr>";
                }
                while ($row = db_fetch_assoc($expenses)) {
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['expense_date'])); ?></td>
                    <td>
                        <div style="font-weight: 600; color:var(--text-main);"><?php echo htmlspecialchars($row['expense_name']); ?></div>
                        <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($row['remarks']); ?></span>
                    </td>
                    <td>
                        <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #F3F4F6; color: #4B5563;">
                            <?php echo $row['category']; ?>
                        </span>
                    </td>
                    <td style="font-weight: bold; color: var(--error);">
                        ₹<?php echo number_format($row['amount'], 2); ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this expense record?');">
                            <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" name="delete_expense" class="btn-primary" style="padding: 6px 12px; font-size: 0.85rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

