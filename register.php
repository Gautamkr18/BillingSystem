<?php
session_start();
include 'includes/db.php';

if(isset($_POST['register'])){

    $username = $_POST['username'];
    $password = MD5($_POST['password']);
    $role = isset($_POST['role']) ? $_POST['role'] : 'admin';
    
    // Check if username already exists
    $check_query = "SELECT * FROM users WHERE username='$username'";
    $check_result = mysqli_query($conn, $check_query);
    
    if(mysqli_num_rows($check_result) > 0) {
        $error = "Username already exists. Please choose another.";
    } else {
        $query = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', '$role')";
        if(mysqli_query($conn, $query)) {
            $_SESSION['register_success'] = "Registration successful! You can now log in.";
            header("Location: login.php");
            exit;
        } else {
            $error = "Failed to register. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing System - Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-image">
            <div class="login-branding">
                <h1>BillingPro</h1>
                <p>Streamline your invoicing and payments seamlessly.</p>
            </div>
        </div>
        <div class="login-form-container">
            <div class="login-header">
                <h2>Create Account</h2>
                <p>Register a new admin account to access the system.</p>
            </div>

            <?php if(isset($error)) { echo '<div class="alert-error">'.$error.'</div>'; } ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                </div>
                <div class="form-group">
                    <label for="role">Select Role</label>
                    <select id="role" name="role" required style="width: 100%; padding: 12px 15px; border: 1px solid #D1D5DB; border-radius: 8px; font-size: 1rem; box-sizing: border-box; background: white;">
                        <option value="admin">Admin (Full Access)</option>
                        <option value="cashier">Cashier (Billing Only)</option>
                    </select>
                </div>
                <button type="submit" name="register" class="btn-primary">Register Account</button>
            </form>
            
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #6B7280; font-size: 0.9rem;">Already have an account? <a href="login.php" style="color: #4F46E5; font-weight: 600; text-decoration: none;">Sign In</a></p>
            </div>
        </div>
    </div>

</body>
</html>
