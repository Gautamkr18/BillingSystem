<?php
include '../includes/auth.php';
restrictToAdmin();
include '../includes/db.php';
include '../includes/header.php';

// Handle Add User
if (isset($_POST['add_user'])) {
    $username = db_real_escape_string($conn, $_POST['username']);
    $password = MD5($_POST['password']);
    $role = db_real_escape_string($conn, $_POST['role']);

    // Check if user exists
    $check = db_query($conn, "SELECT * FROM users WHERE username='$username'");
    if (db_num_rows($check) > 0) {
        echo "<script>alert('Error: Username already exists.');</script>";
    } else {
        db_query($conn, "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')");
        // Log activity
        $admin_name = $_SESSION['username'];
        $admin_id = $_SESSION['user_id'];
        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$admin_id', '$admin_name', 'Add Staff', 'Added new user $username with role $role')");
        echo "<script>alert('User Added Successfully'); window.location='users.php';</script>";
    }
}

// Handle Delete User
if (isset($_POST['delete_user'])) {
    $del_id = $_POST['delete_id'];
    $u_res = db_query($conn, "SELECT username FROM users WHERE id='$del_id'");
    $u_data = db_fetch_assoc($u_res);
    $username = $u_data['username'];

    if ($username == $_SESSION['username']) {
        echo "<script>alert('Error: You cannot delete your own account!');</script>";
    } else {
        db_query($conn, "DELETE FROM users WHERE id='$del_id'");
        // Log activity
        $admin_name = $_SESSION['username'];
        $admin_id = $_SESSION['user_id'];
        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$admin_id', '$admin_name', 'Delete Staff', 'Deleted user account $username')");
        echo "<script>alert('User Deleted Successfully'); window.location='users.php';</script>";
    }
}

// Handle Role Change
if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $role = db_real_escape_string($conn, $_POST['role']);
    
    $u_res = db_query($conn, "SELECT username FROM users WHERE id='$user_id'");
    $u_data = db_fetch_assoc($u_res);
    $username = $u_data['username'];

    db_query($conn, "UPDATE users SET role='$role' WHERE id='$user_id'");
    // Log activity
    $admin_name = $_SESSION['username'];
    $admin_id = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$admin_id', '$admin_name', 'Update Staff Role', 'Changed role of $username to $role')");
    echo "<script>alert('Role Updated Successfully'); window.location='users.php';</script>";
}

// Handle Clear Logs
if (isset($_POST['clear_logs'])) {
    db_query($conn, "TRUNCATE TABLE activity_logs");
    // Log the clear action itself
    $admin_name = $_SESSION['username'];
    $admin_id = $_SESSION['user_id'];
    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$admin_id', '$admin_name', 'Clear Logs', 'Cleared all system activity logs')");
    echo "<script>alert('Activity logs cleared successfully'); window.location='users.php';</script>";
    exit;
}
?>

<div class="page-header">
    <h2>Staff & Security Management</h2>
</div>

<div class="form-grid" style="grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
    <!-- Left Column: Add User Form -->
    <div class="card-form" style="margin-bottom: 0;">
        <h3><i class="fa-solid fa-user-shield"></i> Add Staff Account</h3>
        <br>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>
            <div class="form-group">
                <label>System Role</label>
                <select name="role" required>
                    <option value="cashier">Cashier (Invoicing Only)</option>
                    <option value="admin">Admin (Full Access)</option>
                </select>
            </div>
            <button type="submit" name="add_user" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fa-solid fa-user-plus"></i> Save Staff Member</button>
        </form>
    </div>

    <!-- Right Column: User Management Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = db_query($conn, "SELECT * FROM users ORDER BY id DESC");
                while ($row = db_fetch_assoc($result)) {
                ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td>
                        <span class="badge" style="padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; 
                            background-color: <?php echo $row['role'] === 'admin' ? 'rgba(79, 70, 229, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; 
                            color: <?php echo $row['role'] === 'admin' ? 'var(--primary-color)' : 'var(--secondary-color)'; ?>;">
                            <?php echo strtoupper($row['role']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px;">
                            <!-- Edit Role Form -->
                            <form method="POST" style="display: inline-flex; align-items: center; gap: 5px;">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <select name="role" onchange="this.form.submit()" style="padding: 6px; font-size: 0.85rem; border-radius: 5px; width: auto; margin:0;">
                                    <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="cashier" <?php echo $row['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                </select>
                                <input type="hidden" name="update_role" value="1">
                            </form>
                            
                            <!-- Delete Button -->
                            <?php if ($row['username'] !== $_SESSION['username']): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="delete_user" class="btn-primary" style="padding: 6px 12px; font-size: 0.85rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="page-header" style="margin-top: 40px; display: flex; justify-content: space-between; align-items: center;">
    <h2>System Activity Logs</h2>
    <form method="POST" style="margin: 0;">
        <button type="submit" name="clear_logs" onclick="return confirm('Are you sure you want to clear all system activity logs? This action cannot be undone.');" style="background-color: #EF4444; color: #fff; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; border: none; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2); transition: background-color 0.2s;">
            <i class="fa-solid fa-trash-can"></i> Clear Activity Logs
        </button>
    </form>
</div>

<div class="table-container" style="max-height: 400px; overflow-y: auto;">
    <table class="data-table">
        <thead style="position: sticky; top: 0; z-index: 1;">
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $logs = db_query($conn, "SELECT * FROM activity_logs ORDER BY id DESC LIMIT 100");
            if (db_num_rows($logs) == 0) {
                echo "<tr><td colspan='4' style='text-align:center; color:var(--text-muted);'>No activity logs available yet.</td></tr>";
            }
            while ($log = db_fetch_assoc($logs)) {
            ?>
            <tr>
                <td style="color: var(--text-muted); font-size: 0.9rem;"><?php echo date('d M Y h:i A', strtotime($log['timestamp'])); ?></td>
                <td style="font-weight: 600;"><?php echo htmlspecialchars($log['username']); ?></td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #E5E7EB; color: #374151;">
                        <?php echo htmlspecialchars($log['action']); ?>
                    </span>
                </td>
                <td style="font-size: 0.9rem; color: #4B5563;"><?php echo htmlspecialchars($log['details']); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

