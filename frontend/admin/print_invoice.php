<?php
include '../../backend/includes/auth.php';
include '../../backend/includes/db.php';

if(!isset($_GET['id'])) {
    die("Invoice ID is required.");
}

$invoice_id = $_GET['id'];
$format = isset($_GET['format']) ? $_GET['format'] : 'a4'; // 'a4' or 'thermal'

// Get Invoice
$query = "SELECT i.*, c.name, c.phone, c.email, c.address, c.gstin 
          FROM invoices i 
          JOIN customers c ON i.customer_id = c.customer_id 
          WHERE i.invoice_id = '$invoice_id'";
$result = db_query($conn, $query);
$invoice = db_fetch_assoc($result);

if(!$invoice) {
    die("Invoice not found.");
}

// Get Items
$items_query = "SELECT ii.*, p.product_name, p.hsn_code, p.unit 
                FROM invoice_items ii 
                JOIN products p ON ii.product_id = p.product_id 
                WHERE ii.invoice_id = '$invoice_id'";
$items_result = db_query($conn, $items_query);
$items = [];
while ($it = db_fetch_assoc($items_result)) {
    $items[] = $it;
}



// UPI Payment configuration constants
define('STORE_UPI_ID', 'gautamgupta35@ybl'); // Merchant UPI ID
define('STORE_MERCHANT_NAME', 'Krishna Hardware'); // Merchant Name

// Build standard UPI deep link payload using remaining due amount
$remaining_due = max(0, $invoice['grand_total'] - $invoice['amount_paid']);
$upi_payload = "upi://pay?pa=" . urlencode(STORE_UPI_ID) . 
               "&pn=" . urlencode(STORE_MERCHANT_NAME) . 
               "&am=" . urlencode(number_format($remaining_due, 2, '.', '')) . 
               "&cu=INR" . 
               "&tn=" . urlencode("Inv-" . str_pad($invoice_id, 6, "0", STR_PAD_LEFT));

// QR Code URL using api.qrserver.com
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($upi_payload);

// Construct a detailed WhatsApp Sharing proper greeting message listing items
$customer_phone = preg_replace('/[^0-9]/', '', $invoice['phone']);
if (strlen($customer_phone) == 10) {
    $customer_phone = "91" . $customer_phone;
}

$items_list_text = "";
foreach ($items as $idx => $item) {
    $items_list_text .= "📦 " . ($idx + 1) . ". " . $item['product_name'] . " (" . $item['quantity'] . " " . $item['unit'] . ")\n";
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

$whatsapp_text = "🌟 *INVOICE GENERATED - KRISHNA HARDWARE* 🌟\n\n"
               . "Dear *" . $invoice['name'] . "*,\n\n"
               . "Thank you for shopping with us! Your tax invoice is ready. Please find the details below:\n\n"
               . "📄 *Invoice Details:*\n"
               . "🔹 *Invoice No:* #" . str_pad($invoice_id, 6, "0", STR_PAD_LEFT) . "\n"
               . "📅 *Date:* " . date('d M Y h:i A', strtotime($invoice['invoice_date'])) . "\n"
               . "💰 *Total Value:* ₹" . number_format($invoice['grand_total'], 2) . " (" . $invoice['payment_status'] . " via " . $invoice['payment_method'] . ")\n\n"
               . "🛒 *Items Purchased:*\n"
               . $items_list_text . "\n"
               . "🔗 *View / Print Digital Invoice:*\n"
               . $base_url . "/print_invoice.php?id=" . $invoice_id . "\n\n";

if ($invoice['payment_status'] == 'Pending' || $invoice['payment_status'] == 'Partial') {
    $upi_deep_link = "upi://pay?pa=" . STORE_UPI_ID . "&pn=" . rawurlencode(STORE_MERCHANT_NAME) . "&am=" . number_format($remaining_due, 2, '.', '') . "&cu=INR&tn=Inv-" . str_pad($invoice_id, 6, "0", STR_PAD_LEFT);
    
    $whatsapp_text .= "💳 *Outstanding Due Balance:* ₹" . number_format($remaining_due, 2) . "\n"
                    . "⚡ *Instant UPI Payment Link (Click to Pay):*\n" . $upi_deep_link . "\n\n";
}

$whatsapp_text .= "We appreciate your business! If you have any queries, contact support@krishnahardware.com.\n\n"
               . "✨ *Krishna Hardware*";

$whatsapp_url = "https://api.whatsapp.com/send?phone=" . $customer_phone . "&text=" . rawurlencode($whatsapp_text);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo str_pad($invoice_id, 6, "0", STR_PAD_LEFT); ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Courier+Prime&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if ($format == 'thermal'): ?>
    <!-- Thermal Receipt CSS -->
    <style>
        body {
            font-family: 'Courier Prime', monospace;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 10px;
            font-size: 11px;
            line-height: 1.4;
        }
        .receipt-box {
            max-width: 280px;
            margin: auto;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .header h2 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 10px;
        }
        .bill-details {
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .bill-details p {
            margin: 2px 0;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .receipt-table th, .receipt-table td {
            padding: 4px 0;
            text-align: left;
        }
        .receipt-table th {
            border-bottom: 1px dashed #000;
            text-transform: uppercase;
        }
        .totals-section {
            border-top: 1px dashed #000;
            padding-top: 6px;
            margin-bottom: 8px;
            font-size: 10px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }
        .grand-total {
            font-size: 12px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            margin-top: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 10px;
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
            font-family: 'Inter', sans-serif;
        }
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #4F46E5;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            font-size: 12px;
            margin: 5px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
    <?php else: ?>
    <!-- Standard A4 Invoice CSS -->
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 40px;
            background: #f9f9f9;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 40px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            background: #fff;
            border-radius: 8px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #4F46E5;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 0.95em;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .totals-container {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 40px;
            margin-top: 20px;
        }
        .qr-section {
            border: 1px dashed #4F46E5;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            background: linear-gradient(135deg, #F5F3FF 0%, #FAF5FF 100%);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
            box-sizing: border-box;
        }
        .qr-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .qr-code-img {
            width: 110px;
            height: 110px;
            border: 4px solid #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .qr-info {
            flex: 1;
            text-align: left;
        }
        .qr-info h4 {
            margin: 0 0 5px 0;
            color: #1E1B4B;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .qr-info p {
            margin: 2px 0;
            font-size: 0.75rem;
            color: #4B5563;
            line-height: 1.3;
        }
        .qr-info .amount-to-pay {
            font-size: 1.05rem;
            font-weight: 700;
            color: #4F46E5;
            margin-top: 6px;
        }
        .paid-stamp-box {
            border: 3px double #10B981;
            border-radius: 12px;
            padding: 12px 20px;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(16, 185, 129, 0.05);
            color: #10B981;
            transform: rotate(-3deg);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.05);
            font-family: 'Inter', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: auto;
        }
        .paid-stamp-title {
            font-size: 1.6rem;
            font-weight: 900;
            line-height: 1;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .paid-stamp-details {
            font-size: 0.7rem;
            font-weight: 600;
            margin-top: 4px;
            color: #047857;
            text-transform: none;
            letter-spacing: 0;
            text-align: center;
            line-height: 1.3;
        }
        .totals {
            width: 100%;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table th, .totals-table td {
            padding: 8px 10px;
        }
        .totals-table th {
            text-align: right;
            color: #6b7280;
            font-weight: 500;
        }
        .totals-table td {
            text-align: right;
            font-weight: 500;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .invoice-box {
                box-shadow: none;
                border: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4F46E5;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            cursor: pointer;
            border: none;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #4338CA;
        }
    </style>
    <?php endif; ?>
</head>
<body>

    <!-- Toolbar buttons for sharing, format toggle, printing -->
    <div class="no-print" style="text-align: center; margin-bottom: 25px; background:rgba(255,255,255,0.9); padding:15px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); max-width:800px; margin-left:auto; margin-right:auto;">
        <button class="btn" onclick="window.print()" style="background:#10B981;"><i class="fa-solid fa-print"></i> Print Invoice</button>
        
        <?php if($format == 'thermal'): ?>
            <a href="print_invoice.php?id=<?php echo $invoice_id; ?>&format=a4" class="btn" style="background:#4F46E5;"><i class="fa-solid fa-file-invoice"></i> Switch to Standard A4</a>
        <?php else: ?>
            <a href="print_invoice.php?id=<?php echo $invoice_id; ?>&format=thermal" class="btn" style="background:#F59E0B;"><i class="fa-solid fa-receipt"></i> Switch to 80mm Thermal</a>
        <?php endif; ?>
        
        <!-- Social sharing -->
        <?php if(!empty($invoice['phone'])): ?>
            <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn" style="background:#25D366;"><i class="fa-brands fa-whatsapp"></i> Share on WhatsApp</a>
        <?php endif; ?>
        
        <a href="invoices.php" class="btn" style="background: #6B7280;">Back to Invoice Center</a>
    </div>
    
    <!-- Render thermal roll receipt -->
    <?php if ($format == 'thermal'): ?>
    <div class="receipt-box">
        <div class="header">
            <h2>KRISHNA HARDWARE</h2>
            <p>ICHAK BAZAR HAZARIBAGH, JHARKHAND</p>
            <p>Phone: 7549117172 | GSTIN: 20ABCDE1234F1Z5</p>
        </div>
        
        <div class="bill-details">
            <p><strong>Invoice No:</strong> <?php echo str_pad($invoice_id, 6, "0", STR_PAD_LEFT); ?></p>
            <p><strong>Date:</strong> <?php echo date('d-m-Y h:i A', strtotime($invoice['invoice_date'])); ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($invoice['name']); ?></p>
            <?php if(!empty($invoice['phone'])): ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['phone']); ?></p>
            <?php endif; ?>
            <?php if(!empty($invoice['gstin'])): ?>
                <p><strong>GSTIN:</strong> <?php echo htmlspecialchars($invoice['gstin']); ?></p>
            <?php endif; ?>
        </div>
        
        <table class="receipt-table">
            <thead>
                <tr>
                    <th style="width:60%;">Item</th>
                    <th style="text-align:center; width:15%;">Qty</th>
                    <th style="text-align:right; width:25%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($item['product_name']); ?>
                        <div style="font-size:8px; color:#555;">Rate: ₹<?php echo number_format($item['price'], 2); ?></div>
                    </td>
                    <td style="text-align:center;"><?php echo $item['quantity']; ?></td>
                    <td style="text-align:right;">₹<?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>₹<?php echo number_format($invoice['subtotal'], 2); ?></span>
            </div>
            
            <?php if ($invoice['igst'] > 0): ?>
                <div class="total-row">
                    <span>IGST Total:</span>
                    <span>₹<?php echo number_format($invoice['igst'], 2); ?></span>
                </div>
            <?php else: ?>
                <div class="total-row">
                    <span>CGST Total:</span>
                    <span>₹<?php echo number_format($invoice['cgst'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span>SGST Total:</span>
                    <span>₹<?php echo number_format($invoice['sgst'], 2); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($invoice['discount'] > 0): ?>
                <div class="total-row" style="color:red;">
                    <span>Discount:</span>
                    <span>-₹<?php echo number_format($invoice['discount'], 2); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($invoice['round_off'] != 0): ?>
                <div class="total-row">
                    <span>Round Off:</span>
                    <span><?php echo $invoice['round_off'] > 0 ? '+' : ''; ?>₹<?php echo number_format($invoice['round_off'], 2); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="total-row grand-total">
                <span>GRAND TOTAL:</span>
                <span>₹<?php echo number_format($invoice['grand_total'], 2); ?></span>
            </div>
            <div class="total-row" style="font-weight: bold; padding-top: 4px;">
                <span>AMOUNT PAID:</span>
                <span>₹<?php echo number_format($invoice['amount_paid'], 2); ?></span>
            </div>
            <div class="total-row" style="font-weight: bold; padding-top: 4px; border-bottom: 1px dashed #000; padding-bottom: 4px;">
                <span>DUE AMOUNT:</span>
                <span>₹<?php echo number_format($remaining_due, 2); ?></span>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Payment Mode:</strong> <?php echo strtoupper($invoice['payment_method']); ?></p>
            <p><strong>Payment Status:</strong> <?php echo strtoupper($invoice['payment_status']); ?></p>
            
            <?php if ($invoice['payment_status'] == 'Pending' || $invoice['payment_status'] == 'Partial'): ?>
                <div style="text-align: center; margin: 15px auto; padding: 10px; border: 1px dashed #000; max-width: 180px;">
                    <p style="margin: 0 0 5px 0; font-weight: bold; font-size: 10px; letter-spacing: 0.5px;">SCAN TO PAY VIA UPI</p>
                    <img src="<?php echo $qr_code_url; ?>" alt="UPI QR" style="width: 120px; height: 120px; display: block; margin: 5px auto;">
                    <p style="margin: 5px 0 0 0; font-size: 9px; font-weight: bold;">Amt: ₹<?php echo number_format($remaining_due, 2); ?></p>
                </div>
            <?php else: ?>
                <div style="text-align: center; margin: 12px auto; font-weight: bold; font-size: 12px; border: 2px double #000; padding: 6px 12px; max-width: 120px; display: inline-block;">
                    *** PAID ***
                </div>
            <?php endif; ?>
            
            <br>
            <p>*** THANK YOU FOR SHOPPING! ***</p>
            <p>Krishna Hardware, Hazaribagh</p>
        </div>
    </div>
    
    <!-- Render standard A4 sheet invoice -->
    <?php else: ?>
    <div class="invoice-box">
        <div class="header">
            <div>
                <h1>KRISHNA HARDWARE</h1>
                <p style="margin: 5px 0 0 0; line-height: 1.4; color: #4B5563;">
                    ICHAK BAZAR HAZARIBAGH, JHARKHAND<br>
                    <strong>Phone:</strong> 7549117172 | <strong>Email:</strong> support@krishnahardware.com<br>
                    <strong>GSTIN:</strong> 20ABCDE1234F1Z5
                </p>
            </div>
            <div style="text-align: right;">
                <h2 style="margin-top:0; color: #111827; margin-bottom: 5px; font-weight:700;">TAX INVOICE</h2>
                <span class="badge" style="background:rgba(79, 70, 229, 0.1); color:var(--primary-color); font-weight:bold; padding: 4px 10px; border-radius:4px; font-size:0.8rem; text-transform:uppercase;">
                    GST COMPLIANT
                </span>
                <p style="margin: 10px 0 0 0; font-size: 0.9rem; color:#4B5563;">
                    <strong>Invoice No:</strong> <?php echo str_pad($invoice_id, 6, "0", STR_PAD_LEFT); ?><br>
                    <strong>Date:</strong> <?php echo date('d M Y h:i A', strtotime($invoice['invoice_date'])); ?>
                </p>
            </div>
        </div>
        
        <div class="details">
            <div style="background:#F9FAFB; padding: 15px; border-radius:8px;">
                <strong style="color:var(--primary-color); text-transform:uppercase; font-size:0.85em; letter-spacing:0.5px;">Billed To:</strong>
                <p style="margin: 8px 0 0 0; font-size:0.95rem; line-height:1.6;">
                    <strong><?php echo htmlspecialchars($invoice['name']); ?></strong><br>
                    <?php echo htmlspecialchars($invoice['address']); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($invoice['phone']); ?><br>
                    <?php if(!empty($invoice['email'])): ?>
                        <strong>Email:</strong> <?php echo htmlspecialchars($invoice['email']); ?><br>
                    <?php endif; ?>
                    <?php if(!empty($invoice['gstin'])): ?>
                        <strong>Customer GSTIN:</strong> <span style="font-family:monospace; font-weight:bold;"><?php echo htmlspecialchars($invoice['gstin']); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div style="background:#F9FAFB; padding: 15px; border-radius:8px; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <strong style="color:var(--primary-color); text-transform:uppercase; font-size:0.85em; letter-spacing:0.5px;">Payment Details:</strong>
                    <p style="margin: 8px 0 0 0; font-size:0.95rem;">
                        <strong>Method:</strong> <?php echo $invoice['payment_method']; ?><br>
                        <strong>Status:</strong> 
                        <span style="font-weight:bold; color:<?php echo $invoice['payment_status'] == 'Paid' ? '#10B981' : '#F59E0B'; ?>;">
                            <?php echo $invoice['payment_status']; ?>
                        </span>
                    </p>
                </div>
                <div style="font-size:0.8rem; color:var(--text-muted);">
                    All prices are inclusive of GST. Damaged goods are subject to standard return policy.
                </div>
            </div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Item Description</th>
                    <th>Rate</th>
                    <th>Qty</th>
                    <th>Discount</th>
                    <th>GST %</th>
                    <th style="text-align:right;">Total (Inc. GST)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sno = 1;
                foreach ($items as $item) { 
                ?>
                <tr>
                    <td><?php echo $sno++; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td>₹<?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                    <td style="color:#EF4444;">
                        <?php echo $item['discount'] > 0 ? "-₹" . number_format($item['discount'], 2) : "-"; ?>
                    </td>
                    <td>
                        <?php 
                        // GST rate calculation
                        $row_sub = ($item['price'] * $item['quantity']) - $item['discount'];
                        $row_tax = $item['cgst'] + $item['sgst'] + $item['igst'];
                        $row_gst_pct = $row_sub > 0 ? round(($row_tax / $row_sub) * 100) : 0;
                        echo $row_gst_pct . "%";
                        ?>
                    </td>
                    <td style="text-align:right; font-weight:600;">₹<?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        
        <div class="totals-container">
            <!-- Left Side: QR Code or Paid Badge -->
            <div style="display: flex; align-items: center; justify-content: center; width: 100%;">
                <?php if ($invoice['payment_status'] == 'Paid'): ?>
                    <div class="paid-stamp-box">
                        <div class="paid-stamp-title">
                            <i class="fa-solid fa-circle-check"></i> PAID
                        </div>
                        <div class="paid-stamp-details">
                            via <?php echo htmlspecialchars($invoice['payment_method']); ?><br>
                            on <?php echo date('d-M-Y', strtotime($invoice['invoice_date'])); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="qr-section">
                        <img src="<?php echo $qr_code_url; ?>" alt="Payment QR Code" class="qr-code-img">
                        <div class="qr-info">
                            <h4><i class="fa-solid fa-qrcode"></i> Scan to Pay</h4>
                            <p>Use any UPI app (GPay, PhonePe, Paytm, BHIM) to pay instantly.</p>
                            <p><strong>Merchant:</strong> <?php echo htmlspecialchars(STORE_MERCHANT_NAME); ?></p>
                            <p style="font-size:0.7rem; color:#6B7280; font-style: italic;">Dynamic secure UPI payment</p>
                            <div class="amount-to-pay">
                                Remaining Due: ₹<?php echo number_format($remaining_due, 2); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Side: Totals Breakout -->
            <div class="totals clearfix">
                <table class="totals-table">
                    <tr>
                        <th>Taxable Value:</th>
                        <td>₹<?php echo number_format($invoice['subtotal'], 2); ?></td>
                    </tr>
                    
                    <?php if ($invoice['igst'] > 0): ?>
                        <tr>
                            <th>IGST Total:</th>
                            <td>₹<?php echo number_format($invoice['igst'], 2); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>CGST Total:</th>
                            <td>₹<?php echo number_format($invoice['cgst'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>SGST Total:</th>
                            <td>₹<?php echo number_format($invoice['sgst'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if($invoice['discount'] > 0): ?>
                        <tr>
                            <th style="color: #EF4444;">Discount:</th>
                            <td style="color: #EF4444;">-₹<?php echo number_format($invoice['discount'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php if($invoice['round_off'] != 0): ?>
                        <tr>
                            <th>Round Off:</th>
                            <td><?php echo $invoice['round_off'] > 0 ? '+' : ''; ?>₹<?php echo number_format($invoice['round_off'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th style="font-size:1.25em; border-top: 2px solid #ddd; padding-top: 15px; color:#111827;">Grand Total:</th>
                        <td style="font-size:1.25em; font-weight:700; border-top: 2px solid #ddd; padding-top: 15px; color: #4F46E5;">
                            ₹<?php echo number_format($invoice['grand_total'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="font-size:1.1em; color:#10B981; padding-top: 10px;">Amount Paid:</th>
                        <td style="font-size:1.1em; font-weight:700; color:#10B981; padding-top: 10px;">
                            ₹<?php echo number_format($invoice['amount_paid'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="font-size:1.1em; color:#EF4444; padding-top: 10px;">Due Amount:</th>
                        <td style="font-size:1.1em; font-weight:700; color:#EF4444; padding-top: 10px;">
                            ₹<?php echo number_format($remaining_due, 2); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div style="text-align: center; border-top: 1px solid #E5E7EB; margin-top: 40px; padding-top: 20px; color:#6B7280; font-size:0.85rem;">
            Thank you for shopping with Krishna Hardware!<br>
            For any queries, contact support@krishnahardware.com
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Automatically open print dialog on page load
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
