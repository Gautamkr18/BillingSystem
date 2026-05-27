<?php
session_start();
date_default_timezone_set('Asia/Kolkata'); // Set correct system-wide timezone (Indian Standard Time)

if(!isset($_SESSION['admin'])){
    header("Location: ../login.php");
    exit();
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCashier() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'cashier';
}

function restrictToAdmin() {
    if (!isAdmin()) {
        echo "<div style='padding:20px; font-family:sans-serif; text-align:center; margin-top:50px;'>
                <h2 style='color:#EF4444;'>Access Denied</h2>
                <p>You do not have permission to access this page.</p>
                <a href='dashboard.php' style='display:inline-block; padding:10px 20px; background:#4F46E5; color:#fff; text-decoration:none; border-radius:5px;'>Return to Dashboard</a>
              </div>";
        exit();
    }
}
?>
