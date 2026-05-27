<?php
include '../includes/auth.php';
restrictToAdmin();
include '../includes/db.php';
include '../includes/header.php';
date_default_timezone_set('Asia/Kolkata'); // Set to server's local timezone

// Reset financial stats (sales only)
if (isset($_POST['reset_stats'])) {
    // Truncate sales tables
    db_query($conn, "TRUNCATE TABLE invoice_items");
    db_query($conn, "TRUNCATE TABLE invoices");
    // Optionally reset activity logs if desired (commented out)
    // db_query($conn, "TRUNCATE TABLE activity_logs");
    echo "<script>alert('Sales data has been reset.'); window.location='reports.php';</script>";
    exit;
}

// 1. Double-Entry Profit & Loss Math Calculations
// Total Sales Revenue
$sales_res = db_fetch_assoc(db_query($conn, "SELECT SUM(grand_total) as gross_sales FROM invoices"));
$gross_sales = $sales_res['gross_sales'] ? floatval($sales_res['gross_sales']) : 0.00;

// Cost of Goods Sold (COGS) = SUM (sold items * product.cost_price)
$cogs_res = db_fetch_assoc(db_query($conn, "SELECT SUM(ii.quantity * p.cost_price) as cogs 
                                                    FROM invoice_items ii 
                                                    JOIN products p ON ii.product_id = p.product_id"));
$cogs = $cogs_res['cogs'] ? floatval($cogs_res['cogs']) : 0.00;

// Overhead Expenses (Utilities, Rent, Salaries, travel etc.)
$exp_res = db_fetch_assoc(db_query($conn, "SELECT SUM(amount) as overhead FROM expenses"));
$overhead = $exp_res['overhead'] ? floatval($exp_res['overhead']) : 0.00;

// Gross Profit = Sales - COGS
$gross_profit = $gross_sales - $cogs;

// Net Profit = Gross Profit - Overhead Expenses
$net_profit = $gross_profit - $overhead;


// 2. Fetch daily sales for Chart.js Revenue Trend (Last 30 Days)
$daily_sales = db_query($conn, "SELECT DATE(invoice_date) as sdate, SUM(grand_total) as amt 
                                    FROM invoices 
                                    GROUP BY DATE(invoice_date) 
                                    ORDER BY DATE(invoice_date) ASC 
                                    LIMIT 30");
$chart_labels = [];
$chart_values = [];
while($row = db_fetch_assoc($daily_sales)) {
    $chart_labels[] = date('d M', strtotime($row['sdate']));
    $chart_values[] = floatval($row['amt']);
}

// 3. Fetch Category Sales Distribution for Pie Chart
$cat_sales = db_query($conn, "SELECT p.category, SUM(ii.total) as total_cat 
                                  FROM invoice_items ii 
                                  JOIN products p ON ii.product_id = p.product_id 
                                  GROUP BY p.category");
$cat_labels = [];
$cat_values = [];
while($row = db_fetch_assoc($cat_sales)) {
    $cat_labels[] = $row['category'];
    $cat_values[] = floatval($row['total_cat']);
}
?>

<!-- Include Chart.js dynamically from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <h2>Financial Performance & Analytics</h2>
</div>

<div style="margin:20px 0;">
    <form method="POST">
        <button type="submit" name="reset_stats" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset all financial data?');">Reset All Financial Data</button>
    </form>
</div>

<!-- Financial Summary Statement (P&L Panel) -->
<div class="dashboard-cards" style="grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
    <!-- Gross Sales -->
    <div class="stat-card" style="border-left-color: var(--primary-color); padding: 20px; box-sizing: border-box;">
        <div class="stat-icon" style="background: rgba(79, 70, 229, 0.08); color: var(--primary-color); width:50px; height:50px; font-size:1.4rem;">
            <i class="fa-solid fa-cart-shopping"></i>
        </div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem;">Gross Sales</h3>
            <p class="stat-value" style="font-size:1.5rem;">₹<?php echo number_format($gross_sales, 2); ?></p>
        </div>
    </div>
    
    <!-- Cost of Goods Sold (COGS) -->
    <div class="stat-card" style="border-left-color: #6B7280; padding: 20px; box-sizing: border-box;">
        <div class="stat-icon" style="background: rgba(107, 114, 128, 0.08); color: #6B7280; width:50px; height:50px; font-size:1.4rem;">
            <i class="fa-solid fa-truck-ramp-box"></i>
        </div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem;">Cost of Goods (COGS)</h3>
            <p class="stat-value" style="font-size:1.5rem; color:#4B5563;">₹<?php echo number_format($cogs, 2); ?></p>
        </div>
    </div>

    <!-- Overhead Expenses -->
    <div class="stat-card" style="border-left-color: var(--error); padding: 20px; box-sizing: border-box;">
        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.08); color: var(--error); width:50px; height:50px; font-size:1.4rem;">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem;">Overhead Costs</h3>
            <p class="stat-value" style="font-size:1.5rem; color:var(--error);">₹<?php echo number_format($overhead, 2); ?></p>
        </div>
    </div>

    <!-- Net profit -->
    <div class="stat-card" style="border-left-color: <?php echo $net_profit >= 0 ? 'var(--secondary-color)' : 'var(--error)'; ?>; padding: 20px; box-sizing: border-box;">
        <div class="stat-icon" style="background: <?php echo $net_profit >= 0 ? 'rgba(16, 185, 129, 0.08)' : 'rgba(239, 68, 68, 0.08)'; ?>; color: <?php echo $net_profit >= 0 ? 'var(--secondary-color)' : 'var(--error)'; ?>; width:50px; height:50px; font-size:1.4rem;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="stat-info" style="margin-left: 10px;">
            <h3 style="font-size:0.75rem;">Net profit / Loss</h3>
            <p class="stat-value" style="font-size:1.5rem; color: <?php echo $net_profit >= 0 ? 'var(--secondary-color)' : 'var(--error)'; ?>;">
                ₹<?php echo number_format($net_profit, 2); ?>
            </p>
        </div>
    </div>
</div>

<!-- Chart Graphics Panel Grid -->
<div class="form-grid" style="grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 40px; align-items: stretch;">
    <!-- Left Chart: Daily sales trend -->
    <div class="card-form" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between; height: 100%; box-sizing: border-box;">
        <h3 style="margin-top:0;"><i class="fa-solid fa-chart-line"></i> Revenue Analytics Chart</h3>
        <span style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:15px;">Daily gross billings recorded at POS and counter</span>
        <div style="flex:1; width:100%; min-height: 250px; position:relative;">
            <canvas id="revenueTrendChart" style="width:100%; height:100%;"></canvas>
        </div>
    </div>
    
    <!-- Right Chart: Category Distribution -->
    <div class="card-form" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between; height: 100%; box-sizing: border-box;">
        <h3 style="margin-top:0;"><i class="fa-solid fa-chart-pie"></i> Sales by Category</h3>
        <span style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:15px;">Revenue distribution by product categories</span>
        <div style="flex:1; width:100%; min-height: 250px; position:relative; display:flex; justify-content:center; align-items:center;">
            <canvas id="categoryPieChart" style="max-height: 240px; max-width: 240px;"></canvas>
        </div>
    </div>
</div>

<script>
// 1. Line Chart rendering
const trendCtx = document.getElementById('revenueTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Sales Revenue (₹)',
            data: <?php echo json_encode($chart_values); ?>,
            borderColor: '#4F46E5',
            backgroundColor: 'rgba(79, 70, 229, 0.1)',
            fill: true,
            tension: 0.3,
            borderWidth: 3,
            pointRadius: 4,
            pointBackgroundColor: '#4F46E5'
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
                grid: { color: '#E5E7EB' },
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

// 2. Pie Chart rendering
const pieCtx = document.getElementById('categoryPieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($cat_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($cat_values); ?>,
            backgroundColor: [
                '#4F46E5', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#EC4899'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 12, font: { size: 10 } }
            }
        }
    }
});
</script>

<!-- Bottom Table: Top Selling Products list -->
<div class="page-header">
    <h2>Best-Selling Products & Items</h2>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Product Description</th>
                <th>Category</th>
                <th>Quantity Sold</th>
                <th>Total Sales Generated</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $top_products = db_query($conn, "SELECT p.product_name, p.category, p.unit, SUM(ii.quantity) as qty_sold, SUM(ii.total) as total_generated 
                                                 FROM invoice_items ii 
                                                 JOIN products p ON ii.product_id = p.product_id 
                                                 GROUP BY p.product_id 
                                                 ORDER BY qty_sold DESC 
                                                 LIMIT 15");
            if (db_num_rows($top_products) == 0) {
                echo "<tr><td colspan='4' style='text-align:center; color:var(--text-muted); padding:20px;'>No billing data available to generate performance records.</td></tr>";
            }
            while ($row = db_fetch_assoc($top_products)) {
            ?>
            <tr>
                <td style="font-weight: bold; color: var(--text-main);"><?php echo htmlspecialchars($row['product_name']); ?></td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #F3F4F6; color: #4B5563;">
                        <?php echo htmlspecialchars($row['category']); ?>
                    </span>
                </td>
                <td style="font-weight: 600;"><?php echo $row['qty_sold']; ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                <td style="font-weight: bold; color: var(--primary-color);">₹<?php echo number_format($row['total_generated'], 2); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>

