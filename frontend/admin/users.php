<?php
include '../../backend/includes/auth.php';
restrictToAdmin();
include '../../backend/includes/db.php';

// Handle Add User
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? 'cashier');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "Username and password are required.";
        header("Location: users.php");
        exit();
    }

    $hashed_password = md5($password); // Keeping MD5 for consistency with original login logic

    // Check if username exists using prepared query
    $check = db_query_prepared($conn, "SELECT id FROM users WHERE username = :username", [':username' => $username]);
    
    // Check if phone number exists using prepared query (if phone is provided)
    $phone_exists = false;
    if (!empty($phone)) {
        $check_phone = db_query_prepared($conn, "SELECT id FROM users WHERE phone = :phone", [':phone' => $phone]);
        if (db_num_rows($check_phone) > 0) {
            $phone_exists = true;
        }
    }
    
    if (db_num_rows($check) > 0) {
        $_SESSION['error_message'] = "Error: Username already exists.";
    } elseif ($phone_exists) {
        $_SESSION['error_message'] = "Error: This phone number is already registered.";
    } else {
        $inserted = db_query_prepared($conn, 
            "INSERT INTO users (username, password, role, phone) VALUES (:username, :password, :role, :phone)",
            [
                ':username' => $username,
                ':password' => $hashed_password,
                ':role' => $role,
                ':phone' => $phone
            ]
        );
        
        if ($inserted) {
            // Log activity
            $admin_name = $_SESSION['username'];
            $admin_id = $_SESSION['user_id'];
            db_query_prepared($conn, 
                "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:admin_id, :admin_name, 'Add Staff', :details)",
                [
                    ':admin_id' => $admin_id,
                    ':admin_name' => $admin_name,
                    ':details' => "Added new user $username with role $role"
                ]
            );
            $_SESSION['success_message'] = "Staff account successfully created.";
        } else {
            $_SESSION['error_message'] = "Failed to create staff account. Please try again.";
        }
    }
    header("Location: users.php");
    exit();
}

// Handle Reset Password
if (isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($user_id) || empty($new_password)) {
        $_SESSION['error_message'] = "Invalid password reset request.";
        header("Location: users.php");
        exit();
    }
    
    $hashed_pwd = md5($new_password);
    
    $u_res = db_query_prepared($conn, "SELECT username FROM users WHERE id = :id", [':id' => $user_id]);
    if (db_num_rows($u_res) > 0) {
        $u_data = db_fetch_assoc($u_res);
        $username = $u_data['username'];
        
        db_query_prepared($conn, "UPDATE users SET password = :password WHERE id = :id", [
            ':password' => $hashed_pwd,
            ':id' => $user_id
        ]);
        
        // Log activity
        $admin_name = $_SESSION['username'];
        $admin_id = $_SESSION['user_id'];
        db_query_prepared($conn, 
            "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:admin_id, :admin_name, 'Reset Password', :details)",
            [
                ':admin_id' => $admin_id,
                ':admin_name' => $admin_name,
                ':details' => "Changed password for user $username"
            ]
        );
        $_SESSION['success_message'] = "Password reset successfully for $username.";
    } else {
        $_SESSION['error_message'] = "Staff account not found.";
    }
    header("Location: users.php");
    exit();
}

// Handle Delete User
if (isset($_POST['delete_user'])) {
    $del_id = $_POST['delete_id'] ?? '';
    
    if (empty($del_id)) {
        $_SESSION['error_message'] = "Invalid delete request.";
        header("Location: users.php");
        exit();
    }
    
    $u_res = db_query_prepared($conn, "SELECT username FROM users WHERE id = :id", [':id' => $del_id]);
    if (db_num_rows($u_res) > 0) {
        $u_data = db_fetch_assoc($u_res);
        $username = $u_data['username'];

        if ($username === $_SESSION['username']) {
            $_SESSION['error_message'] = "Error: You cannot delete your own account!";
        } else {
            db_query_prepared($conn, "DELETE FROM users WHERE id = :id", [':id' => $del_id]);
            
            // Log activity
            $admin_name = $_SESSION['username'];
            $admin_id = $_SESSION['user_id'];
            db_query_prepared($conn, 
                "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:admin_id, :admin_name, 'Delete Staff', :details)",
                [
                    ':admin_id' => $admin_id,
                    ':admin_name' => $admin_name,
                    ':details' => "Deleted user account $username"
                ]
            );
            $_SESSION['success_message'] = "Staff account deleted successfully.";
        }
    } else {
        $_SESSION['error_message'] = "Staff account not found.";
    }
    header("Location: users.php");
    exit();
}

// Handle Role Change
if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'] ?? '';
    $role = trim($_POST['role'] ?? 'cashier');
    
    if (empty($user_id)) {
        $_SESSION['error_message'] = "Invalid update request.";
        header("Location: users.php");
        exit();
    }
    
    $u_res = db_query_prepared($conn, "SELECT username FROM users WHERE id = :id", [':id' => $user_id]);
    if (db_num_rows($u_res) > 0) {
        $u_data = db_fetch_assoc($u_res);
        $username = $u_data['username'];

        db_query_prepared($conn, "UPDATE users SET role = :role WHERE id = :id", [
            ':role' => $role,
            ':id' => $user_id
        ]);
        
        // Log activity
        $admin_name = $_SESSION['username'];
        $admin_id = $_SESSION['user_id'];
        db_query_prepared($conn, 
            "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:admin_id, :admin_name, 'Update Staff Role', :details)",
            [
                ':admin_id' => $admin_id,
                ':admin_name' => $admin_name,
                ':details' => "Changed role of $username to $role"
            ]
        );
        $_SESSION['success_message'] = "Role updated successfully for $username.";
    } else {
        $_SESSION['error_message'] = "Staff account not found.";
    }
    header("Location: users.php");
    exit();
}

// Handle Clear Logs
if (isset($_POST['clear_logs'])) {
    db_query_prepared($conn, "DELETE FROM activity_logs");
    db_query_prepared($conn, "DELETE FROM sqlite_sequence WHERE name='activity_logs'");
    
    // Log the clear action itself
    $admin_name = $_SESSION['username'];
    $admin_id = $_SESSION['user_id'];
    db_query_prepared($conn, 
        "INSERT INTO activity_logs (user_id, username, action, details) VALUES (:admin_id, :admin_name, 'Clear Logs', 'Cleared all system activity logs')",
        [
            ':admin_id' => $admin_id,
            ':admin_name' => $admin_name
        ]
    );
    $_SESSION['success_message'] = "Activity logs cleared successfully.";
    header("Location: users.php");
    exit();
}

// Include header after redirection check blocks
include '../includes/header.php';
?>

<div class="page-header">
    <h2>Staff & Security Management</h2>
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

<div class="form-grid" style="grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
    <!-- Left Column: Add User Form -->
    <div class="card-form" style="margin-bottom: 0;">
        <h3><i class="fa-solid fa-user-shield"></i> Add Staff Account</h3>
        <br>
        <form method="POST" id="staff-form">
            <input type="hidden" name="add_user" value="1">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="Enter 10-digit mobile number" required>
            </div>
            <div class="form-group">
                <label>System Role</label>
                <select name="role" required>
                    <option value="cashier">Cashier (Invoicing Only)</option>
                    <option value="admin">Admin (Full Access)</option>
                </select>
            </div>
            <button type="submit" id="staff-submit-btn" class="btn-primary" style="width: 100%; justify-content: center;"><i class="fa-solid fa-user-plus"></i> Save Staff Member</button>
        </form>
    </div>

    <!-- Right Column: User Management Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = db_query_prepared($conn, "SELECT * FROM users ORDER BY id DESC");
                if ($result) {
                    while ($row = db_fetch_assoc($result)) {
                ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($row['username']); ?></td>
                    <td style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500;"><?php echo htmlspecialchars($row['phone'] ?: 'N/A'); ?></td>
                    <td>
                        <span class="badge" style="padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; 
                            background-color: <?php echo $row['role'] === 'admin' ? 'rgba(79, 70, 229, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; 
                            color: <?php echo $row['role'] === 'admin' ? 'var(--primary-color)' : 'var(--secondary-color)'; ?>;">
                            <?php echo strtoupper($row['role']); ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <!-- Edit Role Form -->
                            <form method="POST" style="display: inline-flex; align-items: center; gap: 5px;">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <select name="role" onchange="this.form.submit()" style="padding: 6px; font-size: 0.85rem; border-radius: 5px; width: auto; margin:0;">
                                    <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="cashier" <?php echo $row['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                </select>
                                <input type="hidden" name="update_role" value="1">
                            </form>
                            
                            <!-- Reset Password Form -->
                            <form method="POST" style="display: inline-flex; align-items: center; gap: 4px;" onsubmit="return confirm('Are you sure you want to reset the password for this staff member?');">
                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                <input type="password" name="new_password" placeholder="New Password" required style="padding: 6px; font-size: 0.85rem; border-radius: 5px; width: 100px; margin:0; border: 1px solid #D1D5DB;">
                                <button type="submit" name="reset_password" class="btn-primary" style="padding: 6px 10px; font-size: 0.85rem; background-color: #F59E0B; border: none; border-radius: 5px; color:#fff; cursor:pointer;" title="Change Password"><i class="fa-solid fa-key"></i> Reset</button>
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
                <?php 
                    } 
                }
                ?>
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
            $logs = db_query_prepared($conn, "SELECT * FROM activity_logs ORDER BY id DESC LIMIT 100");
            if ($logs) {
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
            <?php 
                } 
            }
            ?>
        </tbody>
    </table>
</div>

<script>
let isStaffSubmitted = false;
document.getElementById('staff-form').addEventListener('submit', function(e) {
    if (isStaffSubmitted) {
        e.preventDefault();
        return false;
    }
    isStaffSubmitted = true;
    const btn = document.getElementById('staff-submit-btn');
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
