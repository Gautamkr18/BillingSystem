<?php
session_start();
include 'includes/db.php';

if(isset($_POST['login'])){

    $username = $_POST['username'];
    $password = MD5($_POST['password']);

    $query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = db_query($conn, $query);

    if(db_num_rows($result) > 0){
        $user = db_fetch_assoc($result);
        $_SESSION['admin'] = $username;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ? $user['role'] : 'admin';
        
        $user_id = $user['id'];
        $role = $_SESSION['role'];
        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$user_id', '$username', 'Login', 'User logged in as $role')");
        
        header("Location: admin/dashboard.php");
    } else {
        $error = "Invalid Username or Password";
        
        // Debugging logs for browser console (F12)
        $user_check = db_query($conn, "SELECT * FROM users WHERE username='$username'");
        if (db_num_rows($user_check) > 0) {
            $user_data = db_fetch_assoc($user_check);
            $db_pass = $user_data['password'];
            $debug_msg = "User exists. Password mismatch! Submitted hash: $password | Database hash: $db_pass";
        } else {
            $debug_msg = "Username '$username' does not exist in the database.";
        }
        $_SESSION['login_debug'] = $debug_msg;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing System - Login</title>
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
                <h2>Welcome</h2>
                <p>Please enter your credentials to access your account.</p>
            </div>

            <?php 
            if(isset($_SESSION['register_success'])) { 
                echo '<div class="alert-success" style="background:#10B981; color:#fff; padding:12px; border-radius:6px; margin-bottom:20px; font-size:0.9rem;">'.$_SESSION['register_success'].'</div>'; 
                unset($_SESSION['register_success']);
            } 
            ?>
            <?php if(isset($error)) { echo '<div class="alert-error">'.$error.'</div>'; } ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" name="login" class="btn-primary">Sign In</button>
            </form>
        </div>
    </div>

    <?php
    if (isset($_SESSION['login_debug'])) {
        $msg = addslashes($_SESSION['login_debug']);
        echo "<script>console.warn('Login Debug: ' + '$msg');</script>";
        unset($_SESSION['login_debug']);
    }
    ?>
</body>
</html>

