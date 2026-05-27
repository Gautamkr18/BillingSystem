<?php
include '../includes/auth.php';
include '../includes/db.php';
include '../includes/header.php';

// Handle Delete (Admin Only)
if(isset($_POST['delete_invoice'])){
    restrictToAdmin();
    $del_id = $_POST['delete_id'];
    
    // Fetch customer_id, grand_total, payment_status for restoring stock and credit
    $inv_res = mysqli_query($conn, "SELECT * FROM invoices WHERE invoice_id='$del_id'");
    $invoice = mysqli_fetch_assoc($inv_res);
    
    if ($invoice) {
        $customer_id = $invoice['customer_id'];
        $grand_total = $invoice['grand_total'];
        $status = $invoice['payment_status'];
        
        // Restore product stock
        $items_res = mysqli_query($conn, "SELECT * FROM invoice_items WHERE invoice_id='$del_id'");
        while ($item = mysqli_fetch_assoc($items_res)) {
            $pid = $item['product_id'];
            $qty = $item['quantity'];
            mysqli_query($conn, "UPDATE products SET stock_quantity = stock_quantity + $qty WHERE product_id='$pid'");
            // Log inventory movement
            mysqli_query($conn, "INSERT INTO inventory_logs (product_id, quantity, type, remarks) VALUES ('$pid', '$qty', 'IN', 'Restored from deleted Invoice #$del_id')");
        }
        
        // Deduct outstanding dues from customer's credit balance
        $due_amount = $invoice['grand_total'] - $invoice['amount_paid'];
        if ($due_amount > 0) {
            mysqli_query($conn, "UPDATE customers SET credit_balance = credit_balance - $due_amount WHERE customer_id='$customer_id'");
        }
        
        // Delete invoice logs and ledger records
        mysqli_query($conn, "DELETE FROM customer_ledger WHERE invoice_id='$del_id'");
        mysqli_query($conn, "DELETE FROM invoice_items WHERE invoice_id='$del_id'");
        mysqli_query($conn, "DELETE FROM invoices WHERE invoice_id='$del_id'");
        
        // Log Activity
        $username = $_SESSION['username'];
        $uid = $_SESSION['user_id'];
        mysqli_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Delete Invoice', 'Deleted invoice #$del_id and restored inventory')");
        
        echo "<script>alert('Invoice Deleted and Stock Restored successfully.'); window.location='invoices.php';</script>";
    }
}

// Handle Create Invoice
if(isset($_POST['create_invoice'])){
    $customer_id = $_POST['customer_id'];
    $tax_treatment = $_POST['tax_treatment']; // 'LOCAL' or 'OUT_OF_STATE'
    $payment_method = $_POST['payment_method']; // 'Cash', 'Card', 'UPI', 'Credit'
    $product_ids = $_POST['product_id']; // Array of product IDs
    $quantities = $_POST['quantity'];   // Array of quantities
    $item_discounts = isset($_POST['item_discount']) ? $_POST['item_discount'] : []; // Array of unit discounts
    $bill_discount = isset($_POST['bill_discount']) && !empty($_POST['bill_discount']) ? floatval($_POST['bill_discount']) : 0;
    
    // Arrays to store details for insertion after validation
    $invoice_items = [];
    $total_subtotal = 0;
    $total_cgst = 0;
    $total_sgst = 0;
    $total_igst = 0;
    
    $has_error = false;
    $error_msg = "";
    
    // 1. Validate Stock and Calculate Taxes
    for ($i = 0; $i < count($product_ids); $i++) {
        $pid = $product_ids[$i];
        $qty = intval($quantities[$i]);
        $item_disc = isset($item_discounts[$i]) ? floatval($item_discounts[$i]) : 0;
        
        if(empty($pid) || empty($qty) || $qty <= 0) continue;
        
        $product = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM products WHERE product_id='$pid'"));
        
        if ($product['stock_quantity'] < $qty) {
            $has_error = true;
            $error_msg .= "Not enough stock for " . $product['product_name'] . ". Available: " . $product['stock_quantity'] . "\\n";
        } else {
            $price = $product['price'];
            $gst_percentage = $product['gst_percentage'];
            
            $total_item_disc = $item_disc * $qty;
            $subtotal = ($price * $qty) - $total_item_disc;
            if($subtotal < 0) $subtotal = 0;
            
            $item_cgst = 0;
            $item_sgst = 0;
            $item_igst = 0;
            
            if ($tax_treatment == 'LOCAL') {
                $item_cgst = ($subtotal * ($gst_percentage / 2)) / 100;
                $item_sgst = ($subtotal * ($gst_percentage / 2)) / 100;
            } else {
                $item_igst = ($subtotal * $gst_percentage) / 100;
            }
            
            $item_total = $subtotal + $item_cgst + $item_sgst + $item_igst;
            
            $total_subtotal += $subtotal;
            $total_cgst += $item_cgst;
            $total_sgst += $item_sgst;
            $total_igst += $item_igst;
            
            $invoice_items[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'price' => $price,
                'discount' => $total_item_disc,
                'cgst' => $item_cgst,
                'sgst' => $item_sgst,
                'igst' => $item_igst,
                'total' => $item_total
            ];
        }
    }

    if ($has_error) {
        echo "<script>alert('Error: " . $error_msg . "');</script>";
    } else if (count($invoice_items) > 0) {
        // Calculate grand totals and round-off
        $exact_gst_total = $total_cgst + $total_sgst + $total_igst;
        $exact_grand_total = $total_subtotal + $exact_gst_total - $bill_discount;
        if($exact_grand_total < 0) $exact_grand_total = 0;
        
        $rounded_grand_total = round($exact_grand_total);
        $round_off = $rounded_grand_total - $exact_grand_total;
        
        $amount_paid_input = isset($_POST['amount_paid']) && $_POST['amount_paid'] !== '' ? floatval($_POST['amount_paid']) : -1;
        
        $payment_status = 'Paid';
        if ($payment_method == 'Credit') {
            $amount_paid = 0.00;
            $payment_status = 'Pending';
        } else {
            if ($amount_paid_input >= 0) {
                $amount_paid = min($amount_paid_input, $rounded_grand_total);
                if ($amount_paid <= 0) {
                    $payment_status = 'Pending';
                } else if ($amount_paid < $rounded_grand_total) {
                    $payment_status = 'Partial';
                } else {
                    $payment_status = 'Paid';
                }
            } else {
                $amount_paid = $rounded_grand_total;
                $payment_status = 'Paid';
            }
        }

        // 2. Create Invoice record
        $query = "INSERT INTO invoices (customer_id, subtotal, gst_total, cgst, sgst, igst, discount, round_off, grand_total, payment_method, amount_paid, payment_status)
                  VALUES ('$customer_id', '$total_subtotal', '$exact_gst_total', '$total_cgst', '$total_sgst', '$total_igst', '$bill_discount', '$round_off', '$rounded_grand_total', '$payment_method', '$amount_paid', '$payment_status')";
        
        if (mysqli_query($conn, $query)) {
            $invoice_id = mysqli_insert_id($conn);
            
            // 3. Create Invoice Items, Deduct Stock & Log inventory
            foreach ($invoice_items as $item) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];
                $price = $item['price'];
                $disc = $item['discount'];
                $cgst = $item['cgst'];
                $sgst = $item['sgst'];
                $igst = $item['igst'];
                $total = $item['total'];
                
                mysqli_query($conn,"INSERT INTO invoice_items(invoice_id, product_id, quantity, price, discount, cgst, sgst, igst, total)
                                    VALUES('$invoice_id', '$pid', '$qty', '$price', '$disc', '$cgst', '$sgst', '$igst', '$total')");
                
                // Deduct stock and log stock out
                mysqli_query($conn,"UPDATE products SET stock_quantity = stock_quantity - $qty WHERE product_id='$pid'");
                mysqli_query($conn,"INSERT INTO inventory_logs(product_id, quantity, type, remarks) VALUES ('$pid', '-$qty', 'OUT', 'Invoice #$invoice_id Sale')");
            }
            
            // 4. Record to Customer Ledger & manage outstanding credit
            $cust_name_query = mysqli_query($conn, "SELECT name FROM customers WHERE customer_id='$customer_id'");
            $cust_data = mysqli_fetch_assoc($cust_name_query);
            $customer_name = $cust_data['name'];
            
            // DEBIT entry for the invoice purchase
            mysqli_query($conn, "INSERT INTO customer_ledger(customer_id, invoice_id, type, amount, description) 
                                 VALUES ('$customer_id', '$invoice_id', 'DEBIT', '$rounded_grand_total', 'Purchase - Invoice #$invoice_id')");
            
            if ($amount_paid > 0) {
                mysqli_query($conn, "INSERT INTO customer_ledger(customer_id, invoice_id, type, amount, description) 
                                     VALUES ('$customer_id', '$invoice_id', 'CREDIT', '$amount_paid', 'Paid at checkout via $payment_method')");
            }
            
            $due_amount = $rounded_grand_total - $amount_paid;
            if ($due_amount > 0) {
                mysqli_query($conn, "UPDATE customers SET credit_balance = credit_balance + $due_amount WHERE customer_id='$customer_id'");
            }
            
            // 5. Activity Log
            $username = $_SESSION['username'];
            $uid = $_SESSION['user_id'];
            mysqli_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Create Invoice', 'Generated invoice #$invoice_id for customer $customer_name, Total: ₹$rounded_grand_total')");
            
            echo "<script>
                alert('Invoice Created Successfully! Grand Total: ₹" . number_format($rounded_grand_total, 2) . "');
                window.open('print_invoice.php?id=$invoice_id', '_blank');
                window.location='invoices.php';
            </script>";
        } else {
            echo "<script>alert('Error generating invoice: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        echo "<script>alert('Please add at least one valid product.');</script>";
    }
}

// Handle Record Specific Invoice Payment
if (isset($_POST['record_invoice_payment'])) {
    $invoice_id = mysqli_real_escape_string($conn, $_POST['pay_invoice_id']);
    $pay_amount = floatval($_POST['pay_amount']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['pay_method']);
    
    // Fetch current invoice
    $inv_res = mysqli_query($conn, "SELECT * FROM invoices WHERE invoice_id='$invoice_id'");
    $invoice = mysqli_fetch_assoc($inv_res);
    
    if ($invoice && $pay_amount > 0) {
        $customer_id = $invoice['customer_id'];
        $grand_total = $invoice['grand_total'];
        $current_paid = $invoice['amount_paid'];
        $remaining_due = $grand_total - $current_paid;
        
        // Clamp payment to remaining due
        $actual_payment = min($pay_amount, $remaining_due);
        
        if ($actual_payment > 0) {
            $new_amount_paid = $current_paid + $actual_payment;
            $new_status = ($new_amount_paid >= $grand_total) ? 'Paid' : 'Partial';
            
            // 1. Update invoice paid amount and status
            mysqli_query($conn, "UPDATE invoices SET amount_paid='$new_amount_paid', payment_status='$new_status' WHERE invoice_id='$invoice_id'");
            
            // 2. Reduce customer's outstanding credit balance
            mysqli_query($conn, "UPDATE customers SET credit_balance = credit_balance - $actual_payment WHERE customer_id='$customer_id'");
            
            // 3. Record CREDIT entry in customer ledger
            $desc = "Dues Payment Received via $payment_method for Invoice #" . str_pad($invoice_id, 6, "0", STR_PAD_LEFT);
            mysqli_query($conn, "INSERT INTO customer_ledger (customer_id, invoice_id, type, amount, description) 
                                 VALUES ('$customer_id', '$invoice_id', 'CREDIT', '$actual_payment', '$desc')");
                                 
            // 4. Log Activity
            $username = $_SESSION['username'];
            $uid = $_SESSION['user_id'];
            mysqli_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) 
                                 VALUES ('$uid', '$username', 'Invoice Dues Payment', 'Received ₹" . number_format($actual_payment, 2) . " for Invoice #$invoice_id')");
                                 
            echo "<script>alert('Invoice Payment recorded successfully!'); window.location='invoices.php';</script>";
        }
    }
}
?>

<div class="page-header">
    <h2>Billing / Invoice Center</h2>
    <a href="pos.php" class="btn-primary" style="background:#10B981; text-decoration:none;"><i class="fa-solid fa-cash-register"></i> Switch to POS Terminal</a>
</div>

<!-- Invoice Generation Card -->
<div class="card-form">
    <h3><i class="fa-solid fa-file-invoice"></i> Generate Custom GST Invoice</h3>
    <br>
    <form method="POST">
        <!-- Client Selection & Settings Grid -->
        <div class="form-grid" style="grid-template-columns: 1.5fr 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Select Customer</label>
                <select name="customer_id" required style="width: 100%;">
                    <option value="">-- Choose Customer --</option>
                    <?php
                    $customers = mysqli_query($conn,"SELECT * FROM customers");
                    while($c = mysqli_fetch_assoc($customers)){
                        $selected = (isset($_GET['customer_id']) && $_GET['customer_id'] == $c['customer_id']) ? 'selected' : '';
                    ?>
                    <option value="<?php echo $c['customer_id']; ?>" <?php echo $selected; ?>>
                        <?php echo htmlspecialchars($c['name']); ?> (GSTIN: <?php echo !empty($c['gstin']) ? $c['gstin'] : 'N/A'; ?>)
                    </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 0;">
                <label>Tax Treatment</label>
                <select name="tax_treatment" required>
                    <option value="LOCAL">CGST + SGST (Local State)</option>
                    <option value="OUT_OF_STATE">IGST (Inter-State)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label>Payment Mode</label>
                <select name="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI / QR Payment</option>
                    <option value="Card">Bank Card</option>
                    <option value="Credit">Credit Account (Pending)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label>Amount Paid (₹)</label>
                <input type="number" name="amount_paid" placeholder="Full Payment" step="0.01" min="0">
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label>Overall Discount (₹)</label>
                <input type="number" name="bill_discount" placeholder="0.00" step="0.01" min="0">
            </div>
        </div>
        
        <hr style="border: 0; border-top: 1px solid #E5E7EB; margin: 25px 0;">
        <h4 style="margin: 0 0 15px 0; color: var(--text-main); font-size: 1rem;"><i class="fa-solid fa-cart-shopping"></i> Product Basket</h4>
        
        <!-- Cart Table List -->
        <div id="product-rows">
            <div class="form-grid product-row" style="align-items:end; margin-bottom: 15px; grid-template-columns: 2fr 1fr 1fr 1fr 50px;">
                <div class="form-group" style="margin-bottom:0;">
                    <label>Select Product</label>
                    <select name="product_id[]" required style="width: 100%;">
                        <option value="">-- Choose Product --</option>
                        <?php
                        $products = mysqli_query($conn,"SELECT * FROM products WHERE stock_quantity > 0");
                        $product_options = "";
                        while($p = mysqli_fetch_assoc($products)){
                            $opt = "<option value='" . $p['product_id'] . "' data-price='" . $p['price'] . "' data-gst='" . $p['gst_percentage'] . "'>" . htmlspecialchars($p['product_name'], ENT_QUOTES) . " (₹" . $p['price'] . ") - Stock: " . $p['stock_quantity'] . "</option>";
                            echo $opt;
                            $product_options .= $opt;
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom:0;">
                    <label>Quantity</label>
                    <input type="number" name="quantity[]" placeholder="Qty" required min="1">
                </div>
                
                <div class="form-group" style="margin-bottom:0;">
                    <label>Discount per unit (₹)</label>
                    <input type="number" name="item_discount[]" placeholder="0.00" step="0.01" min="0">
                </div>
                
                <div class="form-group" style="margin-bottom:0;">
                    <!-- Live Item Subtotal Preview -->
                    <label>Total Price</label>
                    <input type="text" class="row-subtotal" readonly placeholder="₹0.00" style="background:#F9FAFB; font-weight:bold; color:var(--text-muted);">
                </div>

                <div class="form-group" style="margin-bottom:0; display:flex; justify-content:center;">
                    <button type="button" class="btn-primary remove-row" style="background:#EF4444; padding:12px; border-radius:8px;"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        </div>
        
        <button type="button" id="add-product-btn" class="btn-primary" style="background:#10B981; margin: 20px 0;"><i class="fa-solid fa-plus"></i> Add Another Product</button>
        
        <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">
        
        <button type="submit" name="create_invoice" class="btn-primary"><i class="fa-solid fa-receipt"></i> Generate GST Invoice</button>
    </form>
</div>

<script>
// Append row
document.getElementById('add-product-btn').addEventListener('click', function() {
    const container = document.getElementById('product-rows');
    const newRow = document.createElement('div');
    newRow.className = 'form-grid product-row';
    newRow.style.alignItems = 'end';
    newRow.style.marginBottom = '15px';
    newRow.style.gridTemplateColumns = '2fr 1fr 1fr 1fr 50px';
    
    newRow.innerHTML = `
        <div class="form-group" style="margin-bottom:0;">
            <label>Select Product</label>
            <select name="product_id[]" required style="width: 100%;">
                <option value="">-- Choose Product --</option>
                <?php echo $product_options; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Quantity</label>
            <input type="number" name="quantity[]" placeholder="Qty" required min="1">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label>Discount per unit (₹)</label>
            <input type="number" name="item_discount[]" placeholder="0.00" step="0.01" min="0">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <input type="text" class="row-subtotal" readonly placeholder="₹0.00" style="background:#F9FAFB; font-weight:bold; color:var(--text-muted);">
        </div>
        <div class="form-group" style="margin-bottom:0; display:flex; justify-content:center;">
            <button type="button" class="btn-primary remove-row" style="background:#EF4444; padding:12px; border-radius:8px;"><i class="fa-solid fa-trash"></i></button>
        </div>
    `;
    container.appendChild(newRow);
});

// Remove row and recalculate
document.getElementById('product-rows').addEventListener('click', function(e) {
    if (e.target.closest('.remove-row')) {
        const rows = document.querySelectorAll('.product-row');
        if(rows.length > 1) {
            e.target.closest('.product-row').remove();
        } else {
            alert('At least one product row is required.');
        }
    }
});

// Real-time calculation previews
document.getElementById('product-rows').addEventListener('input', function(e) {
    calculateRow(e.target.closest('.product-row'));
});
document.getElementById('product-rows').addEventListener('change', function(e) {
    calculateRow(e.target.closest('.product-row'));
});

function calculateRow(row) {
    const productSelect = row.querySelector('select[name="product_id[]"]');
    const qtyInput = row.querySelector('input[name="quantity[]"]');
    const discInput = row.querySelector('input[name="item_discount[]"]');
    const subtotalPreview = row.querySelector('.row-subtotal');
    
    if(!productSelect || !qtyInput) return;
    
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    if(!selectedOption || selectedOption.value === "") {
        subtotalPreview.value = "₹0.00";
        return;
    }
    
    const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
    const gstPct = parseFloat(selectedOption.getAttribute('data-gst')) || 0;
    const qty = parseInt(qtyInput.value) || 0;
    const itemDisc = parseFloat(discInput.value) || 0;
    
    // Subtotal = (Price - Discount) * Qty
    const itemSubtotal = (price * qty) - (itemDisc * qty);
    const positiveSubtotal = itemSubtotal < 0 ? 0 : itemSubtotal;
    
    // Tax Calculation
    const tax = (positiveSubtotal * gstPct) / 100;
    const total = positiveSubtotal + tax;
    
    subtotalPreview.value = "₹" + total.toFixed(2);
}
</script>

<!-- Recent Invoices List -->
<div class="page-header" style="margin-top: 40px;">
    <h2>Recent Generated Invoices</h2>
</div>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Invoice</th>
                <th>Client</th>
                <th>Subtotal</th>
                <th>Taxes (GST)</th>
                <th>Discount</th>
                <th>Grand Total</th>
                <th>Payment Mode</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "SELECT i.*, c.name FROM invoices i JOIN customers c ON i.customer_id = c.customer_id ORDER BY i.invoice_id DESC";
            $result = mysqli_query($conn, $query);
            if(mysqli_num_rows($result) == 0) {
                echo "<tr><td colspan='9' style='text-align:center; color:var(--text-muted); padding:20px;'>No invoices created yet.</td></tr>";
            }
            while($row = mysqli_fetch_assoc($result)){
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
                <td><div style="font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></div><span style="font-size:0.75rem; color:var(--text-muted);"><?php echo date('d M Y h:i A', strtotime($row['invoice_date'])); ?></span></td>
                <td>₹<?php echo number_format($row['subtotal'], 2); ?></td>
                <td>
                    <div style="font-size:0.85rem;">₹<?php echo number_format($row['gst_total'], 2); ?></div>
                    <span style="font-size:0.7rem; color:var(--text-muted);">
                        <?php 
                        if ($row['igst'] > 0) {
                            echo "IGST: ₹" . number_format($row['igst'], 2);
                        } else {
                            echo "CGST: ₹" . number_format($row['cgst'], 2) . " | SGST: ₹" . number_format($row['sgst'], 2);
                        }
                        ?>
                    </span>
                </td>
                <td style="color:#EF4444;">-₹<?php echo number_format($row['discount'], 2); ?></td>
                <td style="font-weight:bold; color:var(--primary-color);">
                    ₹<?php echo number_format($row['grand_total'], 2); ?>
                    <?php if($row['round_off'] != 0): ?>
                        <span style="font-size: 0.7rem; color: var(--text-muted); display: block; font-weight: normal;">(Rnd: <?php echo $row['round_off'] > 0 ? '+' : ''; ?><?php echo number_format($row['round_off'], 2); ?>)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: #E5E7EB; color: #374151;">
                        <?php echo $row['payment_method']; ?>
                    </span>
                </td>
                <td>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>;">
                        <?php echo $row['payment_status']; ?>
                    </span>
                    <div style="font-size:0.75rem; color:#10B981; margin-top:4px;">Paid: ₹<?php echo number_format($row['amount_paid'], 2); ?></div>
                    <div style="font-size:0.75rem; color:<?php echo ($row['grand_total'] - $row['amount_paid'] > 0) ? '#EF4444' : '#10B981'; ?>; font-weight:600;">
                        Due: ₹<?php echo number_format(max(0, $row['grand_total'] - $row['amount_paid']), 2); ?>
                    </div>
                </td>
                <td style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <a href="print_invoice.php?id=<?php echo $row['invoice_id']; ?>" target="_blank" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem;"><i class="fa-solid fa-print"></i> A4</a>
                    <a href="print_invoice.php?id=<?php echo $row['invoice_id']; ?>&format=thermal" target="_blank" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background:#4F46E5;"><i class="fa-solid fa-receipt"></i> Recpt</a>
                    
                    <?php 
                    $due = $row['grand_total'] - $row['amount_paid'];
                    if ($due > 0): 
                    ?>
                        <button type="button" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background-color: #10B981;" onclick="openPaymentModal(<?php echo $row['invoice_id']; ?>, <?php echo $due; ?>)"><i class="fa-solid fa-indian-rupee-sign"></i> Pay Dues</button>
                    <?php endif; ?>

                    <?php if(isAdmin()): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this invoice? Stock will be restored.');">
                        <input type="hidden" name="delete_id" value="<?php echo $row['invoice_id']; ?>">
                        <button type="submit" name="delete_invoice" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background-color: #EF4444;"><i class="fa-solid fa-trash"></i> Delete</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Dues Payment Modal overlay -->
<div id="payment-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div class="card-form" style="background:#fff; border-radius:12px; max-width:400px; width:100%; margin:auto; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.2); box-sizing:border-box;">
        <h3 style="margin-top:0; color:var(--primary-color);"><i class="fa-solid fa-indian-rupee-sign"></i> Record Dues Payment</h3>
        <br>
        <form method="POST">
            <input type="hidden" name="pay_invoice_id" id="modal_invoice_id">
            <div class="form-group" style="margin-bottom:15px;">
                <label>Remaining Dues: <strong id="modal_due_text" style="color:var(--error); font-size:1.1rem;">₹0.00</strong></label>
            </div>
            <div class="form-group" style="margin-bottom:15px;">
                <label>Amount Received (₹)</label>
                <input type="number" name="pay_amount" id="modal_pay_amount" step="0.01" min="0.01" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px; box-sizing:border-box;">
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <label>Payment Method</label>
                <select name="pay_method" required style="width:100%; padding:10px; border:1px solid var(--border-color); border-radius:6px;">
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI / QR Scan</option>
                    <option value="Card">Bank Card</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn-primary" style="background:#6B7280; padding: 8px 16px; font-size: 0.9rem;" onclick="closePaymentModal()">Cancel</button>
                <button type="submit" name="record_invoice_payment" class="btn-primary" style="background:#10B981; padding: 8px 16px; font-size: 0.9rem;">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(invoiceId, remainingDue) {
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_due_text').innerText = '₹' + parseFloat(remainingDue).toFixed(2);
    document.getElementById('modal_pay_amount').max = remainingDue;
    document.getElementById('modal_pay_amount').value = parseFloat(remainingDue).toFixed(2);
    document.getElementById('payment-modal').style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('payment-modal').style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
