<?php
include '../../backend/includes/auth.php';
include '../../backend/includes/db.php';

// Check if database tables are migrated, if not, redirect to migrate.php automatically
$table_check = @db_query($conn, "SELECT 1 FROM users LIMIT 1");
if ($table_check === false) {
    header("Location: ../../migrate.php");
    exit();
}

include '../includes/header.php';

// Calculate double-entry statistics
// 1. Overall Gross Sales
$gross_res = db_fetch_assoc(db_query($conn, "SELECT SUM(grand_total) as val FROM invoices"));
$overall_sales = (is_array($gross_res) && isset($gross_res['val'])) ? floatval($gross_res['val']) : 0.00;

// 2. Today's Gross Sales
$today_res = db_fetch_assoc(db_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE DATE(invoice_date) = CURRENT_DATE()"));
$today_sales = (is_array($today_res) && isset($today_res['val'])) ? floatval($today_res['val']) : 0.00;

// 3. This Month's Revenue
$month_res = db_fetch_assoc(db_query($conn, "SELECT SUM(grand_total) as val FROM invoices WHERE MONTH(invoice_date) = MONTH(CURRENT_DATE()) AND YEAR(invoice_date) = YEAR(CURRENT_DATE())"));
$month_revenue = (is_array($month_res) && isset($month_res['val'])) ? floatval($month_res['val']) : 0.00;

// 4. Counts
$total_products = db_num_rows(db_query($conn,"SELECT * FROM products"));
$total_customers = db_num_rows(db_query($conn,"SELECT * FROM customers"));
$total_invoices = db_num_rows(db_query($conn,"SELECT * FROM invoices"));

// 5. Low Stock Alert Count
$low_stock_res = db_fetch_assoc(db_query($conn, "SELECT COUNT(*) as count FROM products WHERE stock_quantity <= low_stock_threshold"));
$low_stock_count = (is_array($low_stock_res) && isset($low_stock_res['count'])) ? intval($low_stock_res['count']) : 0;

// 6. Calculate Total Outstanding Dues across all customers
$dues_res = db_fetch_assoc(db_query($conn, "SELECT SUM(credit_balance) as val FROM customers"));
$overall_dues = (is_array($dues_res) && isset($dues_res['val'])) ? floatval($dues_res['val']) : 0.00;

// 7. Fetch Daily sales of the last 7 days for the dashboard chart
$weekly_sales = db_query($conn, "SELECT DATE(invoice_date) as sdate, SUM(grand_total) as amt 
                                     FROM invoices 
                                     WHERE invoice_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)
                                     GROUP BY DATE(invoice_date) 
                                     ORDER BY DATE(invoice_date) ASC");
$weekly_labels = [];
$weekly_values = [];
while($row = db_fetch_assoc($weekly_sales)) {
    $weekly_labels[] = date('D d M', strtotime($row['sdate']));
    $weekly_values[] = floatval($row['amt']);
}

// In case the weekly sales are empty, fill in with dummy data points to keep the chart beautiful
if (count($weekly_labels) == 0) {
    for ($i = 6; $i >= 0; $i--) {
        $weekly_labels[] = date('D d M', strtotime("-$i days"));
        $weekly_values[] = 0;
    }
}
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <h1>Dashboard Overview</h1>
    <div style="font-size:0.9rem; color:var(--text-muted);"><i class="fa-solid fa-clock"></i> Local Time: <strong><?php echo date('d M Y, h:i A'); ?></strong></div>
</div>

<?php if ($total_products == 0 && isAdmin()): ?>
<div class="alert-info" style="background: rgba(79, 70, 229, 0.08); color: var(--primary-color); border: 1px solid rgba(79, 70, 229, 0.25); padding: 18px 24px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.05); animation: fadeIn 0.5s ease;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <div style="background: rgba(79, 70, 229, 0.15); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-color); font-size: 1.3rem;">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </div>
        <div>
            <h4 style="margin: 0 0 4px 0; font-size: 1.05rem; font-weight: 700; color: #1F2937;">Welcome to BillingPro!</h4>
            <p style="margin: 0; font-size: 0.9rem; color: #4B5563;">Your inventory is currently empty. Please navigate to the Products section to add your items and get started!</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Primary Stats Grid (Gradient Styling) -->
<div class="dashboard-cards" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
    
    <!-- Overall Gross Sales -->
    <div class="stat-card" style="border-left-color: var(--primary-color);">
        <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary-color);"><i class="fa-solid fa-sack-dollar"></i></div>
        <div class="stat-info">
            <h3>Total Gross Sales</h3>
            <p class="stat-value" style="font-size:1.6rem;">₹<?php echo number_format($overall_sales, 2); ?></p>
        </div>
    </div>
    
    <!-- Today's Gross Sales -->
    <div class="stat-card" style="border-left-color: var(--secondary-color);">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--secondary-color);"><i class="fa-solid fa-calendar-day"></i></div>
        <div class="stat-info">
            <h3>Today's Sales</h3>
            <p class="stat-value" style="font-size:1.6rem; color:var(--secondary-color);">₹<?php echo number_format($today_sales, 2); ?></p>
        </div>
    </div>

    <!-- Monthly Revenue -->
    <div class="stat-card" style="border-left-color: #3B82F6;">
        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;"><i class="fa-solid fa-chart-line"></i></div>
        <div class="stat-info">
            <h3>Monthly Revenue</h3>
            <p class="stat-value" style="font-size:1.6rem; color:#2563EB;">₹<?php echo number_format($month_revenue, 2); ?></p>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <a href="inventory.php" style="text-decoration:none; color:inherit; display:block;">
        <div class="stat-card" style="border-left-color: <?php echo $low_stock_count > 0 ? 'var(--error)' : 'var(--secondary-color)'; ?>; height:100%; box-sizing:border-box;">
            <div class="stat-icon" style="background: <?php echo $low_stock_count > 0 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'; ?>; color: <?php echo $low_stock_count > 0 ? 'var(--error)' : 'var(--secondary-color)'; ?>;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="stat-info">
                <h3>Low Stock Alerts</h3>
                <p class="stat-value" style="font-size:1.6rem; color: <?php echo $low_stock_count > 0 ? 'var(--error)' : 'var(--secondary-color)'; ?>;"><?php echo $low_stock_count; ?></p>
            </div>
        </div>
    </a>
    
    <!-- Total Customer Dues -->
    <a href="customers.php" style="text-decoration:none; color:inherit; display:block;">
        <div class="stat-card" style="border-left-color: #EF4444; height:100%; box-sizing:border-box;">
            <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <div class="stat-info">
                <h3>Total Dues Owed</h3>
                <p class="stat-value" style="font-size:1.6rem; color:#EF4444;">₹<?php echo number_format($overall_dues, 2); ?></p>
            </div>
        </div>
    </a>
</div>

<!-- Secondary Stats Grid (Quick Counts) -->
<div class="dashboard-cards" style="grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom: 30px;">
    <!-- Active Products -->
    <div class="stat-card" style="border-left-color: #6B7280; padding:15px; box-sizing:border-box;">
        <div class="stat-icon" style="background: rgba(107, 114, 128, 0.1); color: #6B7280; width:45px; height:45px; font-size:1.2rem;"><i class="fa-solid fa-box-open"></i></div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem; margin-bottom:2px;">Tracked Products</h3>
            <p class="stat-value" style="font-size:1.35rem;"><?php echo $total_products; ?></p>
        </div>
    </div>
    
    <!-- Active Customers -->
    <div class="stat-card" style="border-left-color: #6B7280; padding:15px; box-sizing:border-box;">
        <div class="stat-icon" style="background: rgba(107, 114, 128, 0.1); color: #6B7280; width:45px; height:45px; font-size:1.2rem;"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem; margin-bottom:2px;">Total Customers</h3>
            <p class="stat-value" style="font-size:1.35rem;"><?php echo $total_customers; ?></p>
        </div>
    </div>

    <!-- Active Invoices -->
    <div class="stat-card" style="border-left-color: #6B7280; padding:15px; box-sizing:border-box;">
        <div class="stat-icon" style="background: rgba(107, 114, 128, 0.1); color: #6B7280; width:45px; height:45px; font-size:1.2rem;"><i class="fa-solid fa-file-invoice"></i></div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem; margin-bottom:2px;">Invoices Issued</h3>
            <p class="stat-value" style="font-size:1.35rem;"><?php echo $total_invoices; ?></p>
        </div>
    </div>
</div>

<!-- Layout split charts and shortcuts -->
<div class="form-grid" style="grid-template-columns: 2.2fr 1fr; gap: 30px; margin-bottom: 40px; align-items: stretch;">
    <!-- Left Column: Chart trend -->
    <div class="card-form" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between; height: 100%; box-sizing: border-box;">
        <h3 style="margin-top:0;"><i class="fa-solid fa-chart-line"></i> Weekly Sales Activity Chart</h3>
        <div style="flex:1; width:100%; min-height: 250px; position:relative;">
            <canvas id="weeklySalesChart" style="width:100%; height:100%;"></canvas>
        </div>
    </div>

    <!-- Right Column: Quick Billing Shortcuts -->
    <div class="card-form" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:start; height:100%; box-sizing: border-box; background:#FAF5FF; border: 1px solid #E9D8FD;">
        <h3 style="margin-top:0; color:var(--primary-color);"><i class="fa-solid fa-circle-play"></i> POS Quick Actions</h3>
        <br>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <a href="pos.php" class="btn-primary" style="justify-content:center; padding:12px; font-weight:bold;"><i class="fa-solid fa-cash-register"></i> Open POS Terminal</a>
            <a href="invoices.php" class="btn-primary" style="justify-content:center; padding:12px; background:#10B981;"><i class="fa-solid fa-file-invoice"></i> Create Standard Bill</a>
            <a href="customers.php" class="btn-primary" style="justify-content:center; padding:12px; background:#F59E0B;"><i class="fa-solid fa-user-plus"></i> Add Customer Profile</a>
            <?php if (isAdmin()): ?>
                <a href="gst_reports.php" class="btn-primary" style="justify-content:center; padding:12px; background:#3B82F6;"><i class="fa-solid fa-file-shield"></i> Run GSTR-1 Audits</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const weeklyCtx = document.getElementById('weeklySalesChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($weekly_labels); ?>,
        datasets: [{
            label: 'Sales Revenue (₹)',
            data: <?php echo json_encode($weekly_values); ?>,
            backgroundColor: '#4F46E5',
            borderRadius: 6,
            hoverBackgroundColor: '#4338CA'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#F3F4F6' },
                ticks: {
                    callback: function(value) { return '₹' + value.toLocaleString(); }
                }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>

<!-- Bottom Section: Latest 5 Invoices -->
<div class="page-header">
    <h2>Recent Transactions Log</h2>
    <a href="invoices.php" style="font-size:0.9rem; color:var(--primary-color); font-weight:bold; text-decoration:none;">View All Invoices <i class="fa-solid fa-arrow-right"></i></a>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th>Issue Date</th>
                <th>Payment Mode</th>
                <th>Total Value</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT i.*, c.name FROM invoices i JOIN customers c ON i.customer_id = c.customer_id ORDER BY i.invoice_id DESC LIMIT 5";
            $result = db_query($conn, $query);
            if(db_num_rows($result) == 0) {
                echo "<tr><td colspan='7' style='text-align:center; color:var(--text-muted); padding:20px;'>No billing transactions logged yet.</td></tr>";
            }
            while($row = db_fetch_assoc($result)){
                $status_color = '#10B981';
                $status_bg = 'rgba(16, 185, 129, 0.1)';
                if ($row['payment_status'] == 'Pending') {
                    $status_color = '#EF4444';
                    $status_bg = 'rgba(239, 68, 68, 0.1)';
                } else if ($row['payment_status'] == 'Partial') {
                    $status_color = '#F59E0B';
                    $status_bg = 'rgba(245, 158, 11, 0.1)';
                }
            ?>
            <tr>
                <td style="font-weight:bold; font-family:monospace;"><?php echo str_pad($row['invoice_id'], 6, "0", STR_PAD_LEFT); ?></td>
                <td style="font-weight:600; color:var(--text-main);"><?php echo htmlspecialchars($row['name']); ?></td>
                <td style="color:var(--text-muted); font-size:0.9rem;"><?php echo date('d M Y, h:i A', strtotime($row['invoice_date'])); ?></td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #E5E7EB; color: #374151;">
                        <?php echo $row['payment_method']; ?>
                    </span>
                </td>
                <td style="font-weight:bold; color:var(--primary-color);">₹<?php echo number_format($row['grand_total'], 2); ?></td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>;">
                        <?php echo $row['payment_status']; ?>
                    </span>
                    <div style="font-size:0.75rem; color:#10B981; margin-top:4px;">Paid: ₹<?php echo number_format($row['amount_paid'], 2); ?></div>
                    <div style="font-size:0.75rem; color:<?php echo ($row['grand_total'] - $row['amount_paid'] > 0) ? '#EF4444' : '#10B981'; ?>; font-weight:600;">
                        Due: ₹<?php echo number_format(max(0, $row['grand_total'] - $row['amount_paid']), 2); ?>
                    </div>
                </td>
                <td>
                    <a href="print_invoice.php?id=<?php echo $row['invoice_id']; ?>&format=thermal" target="_blank" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-receipt"></i> Print Receipt</a>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
