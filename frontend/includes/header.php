<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BillingPro - Admin Dashboard</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">

<div class="admin-wrapper">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fa-solid fa-file-invoice-dollar"></i> BillingPro</h2>
            <div style="font-size: 0.8rem; color: #9CA3AF; margin-top: 5px; display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-circle" style="color: <?php echo isAdmin() ? '#10B981' : '#F59E0B'; ?>; font-size: 0.6rem;"></i>
                <span><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo strtoupper($_SESSION['role']); ?>)</span>
            </div>
        </div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="pos.php" style="background: rgba(79, 70, 229, 0.2); font-weight: bold; border-left: 4px solid #10B981;"><i class="fa-solid fa-cash-register"></i> POS Terminal</a></li>
            <li><a href="customers.php"><i class="fa-solid fa-users"></i> Customers</a></li>
            <li><a href="products.php" <?php if (isAdmin()) echo 'style="background: rgba(79, 70, 229, 0.2); font-weight: bold; border-left: 4px solid #10B981;"'; ?>>
                    <i class="fa-solid fa-box-open"></i> Manage Products
                </a></li>
            <li><a href="inventory.php"><i class="fa-solid fa-warehouse"></i> Inventory Stock</a></li>
            <li><a href="invoices.php"><i class="fa-solid fa-file-invoice"></i> Invoices</a></li>
            
            <?php if (isAdmin()): ?>
                <li><a href="expenses.php"><i class="fa-solid fa-receipt"></i> Expenses</a></li>
                <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> Financial Reports</a></li>
                <li><a href="gst_reports.php"><i class="fa-solid fa-file-shield"></i> GST Reports</a></li>
                <li><a href="users.php"><i class="fa-solid fa-user-shield"></i> Staff Accounts</a></li>
            <?php endif; ?>
        </ul>
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="content-container">
