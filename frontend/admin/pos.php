<?php
include '../../backend/includes/auth.php';
include '../../backend/includes/db.php';
include '../includes/header.php';

// Handle Checkout Submission (Copied from invoices.php but highly optimized for POS checkout)
if (isset($_POST['checkout_pos'])) {
    $customer_id = $_POST['customer_id'];
    $tax_treatment = $_POST['tax_treatment']; // 'LOCAL' or 'OUT_OF_STATE'
    $payment_method = $_POST['payment_method']; // 'Cash', 'Card', 'UPI', 'Credit'
    
    $product_ids = $_POST['prod_ids']; // Array of product IDs
    $quantities = $_POST['prod_qtys'];   // Array of quantities
    $item_discounts = isset($_POST['prod_discounts']) ? $_POST['prod_discounts'] : []; // Array of unit discounts
    $bill_discount = isset($_POST['bill_discount']) ? floatval($_POST['bill_discount']) : 0;
    
    // Arrays for validation
    $invoice_items = [];
    $total_subtotal = 0;
    $total_cgst = 0;
    $total_sgst = 0;
    $total_igst = 0;
    
    $has_error = false;
    $error_msg = "";
    
    // Validate
    if (empty($product_ids) || count($product_ids) == 0) {
        $has_error = true;
        $error_msg = "Your cart is empty. Click on products to add them.";
    } else {
        for ($i = 0; $i < count($product_ids); $i++) {
            $pid = $product_ids[$i];
            $qty = intval($quantities[$i]);
            $item_disc = isset($item_discounts[$i]) ? floatval($item_discounts[$i]) : 0;
            
            if (empty($pid) || $qty <= 0) continue;
            
            $product = db_fetch_assoc(db_query($conn,"SELECT * FROM products WHERE product_id='$pid'"));
            
            if ($product['stock_quantity'] < $qty) {
                $has_error = true;
                $error_msg .= "Not enough stock for " . $product['product_name'] . ". Available: " . $product['stock_quantity'] . "\\n";
            } else {
                $price = $product['price'];
                $gst_percentage = $product['gst_percentage'];
                
                $total_item_disc = $item_disc * $qty;
                $subtotal = ($price * $qty) - $total_item_disc;
                if ($subtotal < 0) $subtotal = 0;
                
                $item_cgst = 0; $item_sgst = 0; $item_igst = 0;
                
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
    }

    if ($has_error) {
        echo "<script>alert('Error: " . $error_msg . "');</script>";
    } else if (count($invoice_items) > 0) {
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

        // Create Invoice
        $query = "INSERT INTO invoices (customer_id, subtotal, gst_total, cgst, sgst, igst, discount, round_off, grand_total, payment_method, amount_paid, payment_status)
                  VALUES ('$customer_id', '$total_subtotal', '$exact_gst_total', '$total_cgst', '$total_sgst', '$total_igst', '$bill_discount', '$round_off', '$rounded_grand_total', '$payment_method', '$amount_paid', '$payment_status')";
        
        if (db_query($conn, $query)) {
            $invoice_id = db_insert_id($conn);
            
            // Insert items, deduct stock
            foreach ($invoice_items as $item) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];
                $price = $item['price'];
                $disc = $item['discount'];
                $cgst = $item['cgst'];
                $sgst = $item['sgst'];
                $igst = $item['igst'];
                $total = $item['total'];
                
                db_query($conn,"INSERT INTO invoice_items (invoice_id, product_id, quantity, price, discount, cgst, sgst, igst, total)
                                    VALUES ('$invoice_id', '$pid', '$qty', '$price', '$disc', '$cgst', '$sgst', '$igst', '$total')");
                
                db_query($conn,"UPDATE products SET stock_quantity = stock_quantity - $qty WHERE product_id='$pid'");
                db_query($conn,"INSERT INTO inventory_logs(product_id, quantity, type, remarks) VALUES ('$pid', '-$qty', 'OUT', 'POS Sale Inv #$invoice_id')");
            }
            
            // Ledger Entries
            $cust_name_query = db_query($conn, "SELECT name FROM customers WHERE customer_id='$customer_id'");
            $cust_data = db_fetch_assoc($cust_name_query);
            $customer_name = $cust_data['name'];
            
            db_query($conn, "INSERT INTO customer_ledger(customer_id, invoice_id, type, amount, description) 
                                 VALUES ('$customer_id', '$invoice_id', 'DEBIT', '$rounded_grand_total', 'POS Purchase - Invoice #$invoice_id')");
            
            if ($amount_paid > 0) {
                db_query($conn, "INSERT INTO customer_ledger(customer_id, invoice_id, type, amount, description) 
                                     VALUES ('$customer_id', '$invoice_id', 'CREDIT', '$amount_paid', 'Paid at counter via $payment_method')");
            }
            
            $due_amount = $rounded_grand_total - $amount_paid;
            if ($due_amount > 0) {
                db_query($conn, "UPDATE customers SET credit_balance = credit_balance + $due_amount WHERE customer_id='$customer_id'");
            }
            
            // Activity log
            $username = $_SESSION['username'];
            $uid = $_SESSION['user_id'];
            db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'POS Checkout', 'Checkout Invoice #$invoice_id at counter, Total: ₹$rounded_grand_total')");
            
            echo "<script>
                alert('POS Invoice generated successfully!');
                window.open('print_invoice.php?id=$invoice_id&format=thermal', '_blank');
                window.location='pos.php';
            </script>";
        } else {
            echo "<script>alert('Error: " . db_error($conn) . "');</script>";
        }
    }
}

// Fetch Customers and Products for dropdowns/renders
$customers = db_query($conn, "SELECT customer_id, name, phone, gstin FROM customers ORDER BY name ASC");
$all_customers = [];
while($c = db_fetch_assoc($customers)) {
    $all_customers[] = $c;
}

$categories_res = db_query($conn, "SELECT DISTINCT category FROM products ORDER BY category ASC");
$categories = [];
while($cat = db_fetch_assoc($categories_res)) {
    $categories[] = $cat['category'];
}

$products_res = db_query($conn, "SELECT * FROM products WHERE stock_quantity > 0 ORDER BY product_name ASC");
$all_products = [];
while($p = db_fetch_assoc($products_res)) {
    $all_products[] = $p;
}
?>

<style>
/* Modern POS UI Layout Styling */
.pos-wrapper {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 20px;
    height: calc(100vh - 150px);
    overflow: hidden;
}

.pos-panel {
    background: var(--white);
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.pos-search-bar {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    gap: 10px;
    align-items: center;
}

.pos-search-bar input {
    flex: 1;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
}

.category-tabs {
    display: flex;
    gap: 8px;
    padding: 10px 15px;
    overflow-x: auto;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}

.category-tab {
    padding: 6px 12px;
    border-radius: 20px;
    background: #F3F4F6;
    color: #4B5563;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}

.category-tab.active, .category-tab:hover {
    background: var(--primary-color);
    color: var(--white);
}

.product-grid {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 15px;
    align-content: start;
}

.pos-prod-card {
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 140px;
}

.pos-prod-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.1);
}

.pos-prod-card .prod-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-main);
    margin-bottom: 5px;
}

.pos-prod-card .prod-price {
    color: var(--primary-color);
    font-weight: bold;
    font-size: 0.95rem;
}

.pos-prod-card .prod-stock {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 5px;
}

/* Cart Panel Styling */
.cart-header {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    background: #F9FAFB;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-items-container {
    flex: 1;
    overflow-y: auto;
    padding: 15px 0;
}

.cart-item-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 40px;
    align-items: center;
    padding: 10px 15px;
    border-bottom: 1px solid #F3F4F6;
    gap: 5px;
}

.cart-item-row .item-title {
    font-weight: 500;
    font-size: 0.85rem;
}

.cart-item-row input {
    width: 100%;
    padding: 6px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    text-align: center;
    font-size: 0.85rem;
}

.pos-summary-sheet {
    border-top: 1px solid var(--border-color);
    background: #F9FAFB;
    padding: 15px;
}

.pos-summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.pos-summary-row.total {
    border-top: 1px solid #E5E7EB;
    padding-top: 10px;
    font-weight: 700;
    font-size: 1.25rem;
    color: var(--primary-color);
}

.pos-shortcuts {
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 8px;
    padding: 8px 12px;
    margin-top: 10px;
    display: flex;
    justify-content: space-around;
    font-size: 0.8rem;
    color: #92400E;
}
</style>

<div class="page-header" style="margin-bottom:15px;">
    <h2>Counter Point-of-Sale Terminal</h2>
    <div style="font-size:0.85rem; color:var(--text-muted);"><i class="fa-solid fa-keyboard"></i> Quick Shortcuts: <strong style="color:var(--primary-color);">[F2]</strong> Focus Search | <strong style="color:var(--primary-color);">[F8]</strong> Pay & Print</div>
</div>

<form method="POST" id="pos-checkout-form" onsubmit="event.preventDefault(); submitCheckoutForm();">
    <input type="hidden" name="checkout_pos" value="1">
    <div class="pos-wrapper">
        
        <!-- Left Panel: Products List -->
        <div class="pos-panel">
            <div class="pos-search-bar">
                <i class="fa-solid fa-magnifying-glass" style="color:var(--text-muted);"></i>
                <input type="text" id="pos-search" placeholder="Type product name, category, or scan Barcode (F2 to focus)..." autofocus>
            </div>
            
            <div class="category-tabs">
                <button type="button" class="category-tab active" onclick="filterCategory('ALL')">All Categories</button>
                <?php foreach($categories as $cat): ?>
                    <button type="button" class="category-tab" onclick="filterCategory('<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>')"><?php echo htmlspecialchars($cat); ?></button>
                <?php endforeach; ?>
            </div>
            
            <!-- Product grid container to support scrolling -->
            <div class="product-grid">
                <!-- Products dynamically rendered here -->
                <?php foreach($all_products as $p): ?>
                    <div class="pos-prod-card" 
                         data-id="<?php echo $p['product_id']; ?>" 
                         data-name="<?php echo htmlspecialchars($p['product_name'], ENT_QUOTES); ?>"
                         data-category="<?php echo htmlspecialchars($p['category'], ENT_QUOTES); ?>"
                         data-barcode="<?php echo htmlspecialchars($p['barcode'], ENT_QUOTES); ?>"
                         data-price="<?php echo $p['price']; ?>"
                         data-gst="<?php echo $p['gst_percentage']; ?>"
                         data-stock="<?php echo $p['stock_quantity']; ?>"
                         data-unit="<?php echo htmlspecialchars($p['unit'], ENT_QUOTES); ?>"
                         onclick="addToCart(this)">
                        <div class="prod-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                        <div>
                            <div class="prod-price">₹<?php echo number_format($p['price'], 2); ?></div>
                            <div class="prod-stock"><?php echo $p['stock_quantity']; ?> <?php echo htmlspecialchars($p['unit']); ?> left</div>
                            <span class="badge" style="font-size:0.65rem; background:#F3F4F6; color:#4B5563; padding:2px 6px; border-radius:4px; margin-top:4px; display:inline-block;"><?php echo htmlspecialchars($p['category']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Right Panel: POS Cart & Summary Sheet -->
        <div class="pos-panel">
            <div class="cart-header">
                <h3 style="margin:0; font-size:1.1rem;"><i class="fa-solid fa-cart-shopping"></i> Active Invoice</h3>
                <span class="badge" id="cart-counter" style="background:var(--primary-color); color:white; border-radius:20px; padding:4px 10px;">0 Items</span>
            </div>
            
            <!-- Customer Selector in Checkout Form -->
            <div style="padding: 12px 15px; border-bottom: 1px solid var(--border-color); background: #FAF5FF; display: grid; grid-template-columns: 1.5fr 1fr; gap:10px;">
                <div>
                    <label style="font-size: 0.75rem; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 4px;">Select Customer</label>
                    <select name="customer_id" required style="padding: 8px; font-size: 0.85rem; border-radius: 6px; width: 100%; border: 1px solid var(--border-color);">
                        <?php foreach($all_customers as $cust): ?>
                            <option value="<?php echo $cust['customer_id']; ?>">
                                <?php echo htmlspecialchars($cust['name']); ?> <?php echo !empty($cust['phone']) ? '(' . $cust['phone'] . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.75rem; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 4px;">Tax Treatment</label>
                    <select name="tax_treatment" id="tax_treatment" style="padding: 8px; font-size: 0.85rem; border-radius: 6px; width: 100%; border: 1px solid var(--border-color);" onchange="recalculateCart()">
                        <option value="LOCAL">CGST + SGST (Local)</option>
                        <option value="OUT_OF_STATE">IGST (Interstate)</option>
                    </select>
                </div>
            </div>
            
            <!-- Cart Items Grid -->
            <div class="cart-items-container" id="cart-items-wrapper">
                <div style="text-align: center; color: var(--text-muted); padding: 40px;" id="empty-cart-msg">
                    <i class="fa-solid fa-basket-shopping" style="font-size: 3rem; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p style="margin:0;">POS Cart is empty.<br>Click products on the left or scan barcodes.</p>
                </div>
                <!-- Dynamic cart rows will appear here -->
            </div>
            
            <!-- Checkout Summary & Actions -->
            <div class="pos-summary-sheet">
                <div class="pos-summary-row">
                    <span>Subtotal:</span>
                    <span id="summary-subtotal" style="font-weight: 500;">₹0.00</span>
                </div>
                <div class="pos-summary-row">
                    <span id="summary-tax-label">CGST & SGST:</span>
                    <span id="summary-tax-total" style="font-weight: 500;">₹0.00</span>
                </div>
                <div class="pos-summary-row" style="align-items: center;">
                    <span>Discount (₹):</span>
                    <input type="number" name="bill_discount" id="summary-bill-discount" step="0.01" min="0" placeholder="0.00" style="width: 80px; padding: 4px; font-size:0.85rem; border: 1px solid var(--border-color); border-radius:4px; text-align:right;" oninput="recalculateCart()">
                </div>
                <div class="pos-summary-row">
                    <span>Round Off:</span>
                    <span id="summary-round-off" style="font-weight: 500; color:var(--text-muted);">₹0.00</span>
                </div>
                <div class="pos-summary-row total">
                    <span>Grand Total:</span>
                    <span id="summary-grand-total">₹0.00</span>
                </div>
                
                <!-- Payment mode -->
                <div style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 4px;">Payment Method</label>
                        <select name="payment_method" id="payment_method" style="padding: 10px; font-size: 0.9rem; font-weight: bold; border-radius: 6px; width: 100%; border: 1px solid var(--border-color); background: #FAF5FF; border-color:#D6BCFA;">
                            <option value="Cash">Cash Payment</option>
                            <option value="UPI">UPI / QR Scan</option>
                            <option value="Card">Bank Card</option>
                            <option value="Credit">Credit Ledger Account</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; font-weight: bold; color: var(--text-muted); display: block; margin-bottom: 4px;">Amount Paid (₹)</label>
                        <input type="number" name="amount_paid" id="amount_paid_pos" step="0.01" min="0" placeholder="Full Amount" style="padding: 10px; font-size: 0.9rem; border-radius: 6px; width: 100%; border: 1px solid var(--border-color); box-sizing: border-box; background: #FFF; border-color:var(--border-color);">
                    </div>
                    
                    <div style="display:flex; align-items:end;">
                        <button type="submit" id="pos-submit-btn" class="btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:1.05rem; background:var(--primary-color); border-radius:6px; box-shadow: 0 4px 10px rgba(79,70,229,0.2);">
                            <i class="fa-solid fa-print"></i> Pay & Print [F8]
                        </button>
                    </div>
                </div>
                
                <div class="pos-shortcuts">
                    <span><strong>F2:</strong> Search</span>
                    <span><strong>F4:</strong> Payment Type</span>
                    <span><strong>F8:</strong> Checkout</span>
                </div>
            </div>
        </div>
        
    </div>
</form>

<script>
let cart = {};

// Handle filter category tabs
function filterCategory(cat) {
    // Set active tab styling
    const tabs = document.querySelectorAll('.category-tab');
    tabs.forEach(t => t.classList.remove('active'));
    
    // Find active tab
    const event = window.event;
    if(event) {
        event.target.classList.add('active');
    }
    
    // Filter product grid
    const cards = document.querySelectorAll('.pos-prod-card');
    cards.forEach(c => {
        if(cat === 'ALL' || c.getAttribute('data-category') === cat) {
            c.style.display = 'flex';
        } else {
            c.style.display = 'none';
        }
    });
}

// POS Live Search (Filters name, category, or exact barcode scan)
document.getElementById('pos-search').addEventListener('input', function(e) {
    const val = e.target.value.toLowerCase().trim();
    const cards = document.querySelectorAll('.pos-prod-card');
    
    let matchedCard = null;
    let matchCount = 0;
    
    cards.forEach(c => {
        const name = c.getAttribute('data-name').toLowerCase();
        const cat = c.getAttribute('data-category').toLowerCase();
        const barcode = c.getAttribute('data-barcode').toLowerCase();
        
        if (name.includes(val) || cat.includes(val) || (barcode && barcode === val)) {
            c.style.display = 'flex';
            matchedCard = c;
            matchCount++;
        } else {
            c.style.display = 'none';
        }
    });
    
    // Barcode scanner automatic adding trigger:
    // If a scanned value perfectly matches a single product barcode and search was quick, auto-add it!
    if (val !== "" && matchCount === 1 && matchedCard && matchedCard.getAttribute('data-barcode').toLowerCase() === val) {
        addToCart(matchedCard);
        document.getElementById('pos-search').value = ""; // Clear input
        filterCategory('ALL'); // Reset tabs
    }
});

// Add Product Card Click to Cart
function addToCart(card) {
    const id = card.getAttribute('data-id');
    const name = card.getAttribute('data-name');
    const price = parseFloat(card.getAttribute('data-price'));
    const gst = parseFloat(card.getAttribute('data-gst'));
    const stock = parseInt(card.getAttribute('data-stock'));
    const unit = card.getAttribute('data-unit');
    
    if (cart[id]) {
        if (cart[id].qty + 1 > stock) {
            alert('Cannot add item. Maximum available stock limit is ' + stock + ' ' + unit + '.');
            return;
        }
        cart[id].qty++;
    } else {
        cart[id] = {
            id: id,
            name: name,
            price: price,
            gst: gst,
            stock: stock,
            unit: unit,
            qty: 1,
            discount: 0
        };
    }
    
    renderCart();
}

// Remove from Cart
function removeFromCart(id) {
    delete cart[id];
    renderCart();
}

// Update Qty directly
function updateQty(id, newQty) {
    newQty = parseInt(newQty) || 0;
    if (newQty <= 0) {
        removeFromCart(id);
        return;
    }
    if (newQty > cart[id].stock) {
        alert('Stock limit reached. Max: ' + cart[id].stock);
        newQty = cart[id].stock;
    }
    cart[id].qty = newQty;
    recalculateCart();
}

// Update item discount
function updateItemDiscount(id, disc) {
    disc = parseFloat(disc) || 0;
    cart[id].discount = disc;
    recalculateCart();
}

// Render visual Cart Items
function renderCart() {
    const wrapper = document.getElementById('cart-items-wrapper');
    const emptyMsg = document.getElementById('empty-cart-msg');
    
    // Clear dynamic rows
    const rows = wrapper.querySelectorAll('.cart-item-row');
    rows.forEach(r => r.remove());
    
    const cartKeys = Object.keys(cart);
    if(cartKeys.length === 0) {
        emptyMsg.style.display = 'block';
        document.getElementById('cart-counter').textContent = "0 Items";
        recalculateCart();
        return;
    }
    
    emptyMsg.style.display = 'none';
    document.getElementById('cart-counter').textContent = cartKeys.length + " Items";
    
    cartKeys.forEach(key => {
        const item = cart[key];
        const itemRow = document.createElement('div');
        itemRow.className = 'cart-item-row';
        itemRow.innerHTML = `
            <div>
                <div class="item-title" style="font-weight:600;">${item.name}</div>
                <div style="font-size:0.75rem; color:var(--text-muted);">₹${item.price.toFixed(2)} / ${item.unit} | Stock: ${item.stock}</div>
                <!-- Hidden inputs for form submit -->
                <input type="hidden" name="prod_ids[]" value="${item.id}">
            </div>
            <div>
                <input type="number" name="prod_qtys[]" value="${item.qty}" min="1" max="${item.stock}" oninput="updateQty('${item.id}', this.value)" style="padding:6px; font-weight:bold;">
            </div>
            <div>
                <input type="number" name="prod_discounts[]" value="${item.discount || ''}" placeholder="0.00" step="0.01" oninput="updateItemDiscount('${item.id}', this.value)" title="Unit discount in ₹">
            </div>
            <div style="text-align:right; font-weight:bold; font-size:0.9rem; color:var(--text-main);" id="item-sub-${item.id}">
                ₹0.00
            </div>
            <div style="display:flex; justify-content:center;">
                <button type="button" class="btn-primary" onclick="removeFromCart('${item.id}')" style="background:#EF4444; padding:6px 10px; border-radius:4px; font-size:0.8rem;"><i class="fa-solid fa-trash-can"></i></button>
            </div>
        `;
        wrapper.appendChild(itemRow);
    });
    
    recalculateCart();
}

// Calculate taxes, rounded totals, roundoff
function recalculateCart() {
    let subtotal = 0;
    let cgst = 0;
    let sgst = 0;
    let igst = 0;
    
    const taxTreatment = document.getElementById('tax_treatment').value;
    
    Object.keys(cart).forEach(key => {
        const item = cart[key];
        const itemDiscountTotal = (item.discount || 0) * item.qty;
        const itemSubtotal = (item.price * item.qty) - itemDiscountTotal;
        const cleanSubtotal = itemSubtotal < 0 ? 0 : itemSubtotal;
        
        subtotal += cleanSubtotal;
        
        let rowCgst = 0;
        let rowSgst = 0;
        let rowIgst = 0;
        
        if (taxTreatment === 'LOCAL') {
            rowCgst = (cleanSubtotal * (item.gst / 2)) / 100;
            rowSgst = (cleanSubtotal * (item.gst / 2)) / 100;
            cgst += rowCgst;
            sgst += rowSgst;
        } else {
            rowIgst = (cleanSubtotal * item.gst) / 100;
            igst += rowIgst;
        }
        
        const rowTotal = cleanSubtotal + rowCgst + rowSgst + rowIgst;
        
        // Update row preview in HTML
        const rowSubtotalPreview = document.getElementById(`item-sub-${item.id}`);
        if(rowSubtotalPreview) {
            rowSubtotalPreview.textContent = "₹" + rowTotal.toFixed(2);
        }
    });
    
    const billDiscount = parseFloat(document.getElementById('summary-bill-discount').value) || 0;
    const taxesSum = cgst + sgst + igst;
    const exactGrandTotal = subtotal + taxesSum - billDiscount;
    const positiveExactTotal = exactGrandTotal < 0 ? 0 : exactGrandTotal;
    
    const roundedGrandTotal = Math.round(positiveExactTotal);
    const roundOff = roundedGrandTotal - positiveExactTotal;
    
    // Render summary UI
    document.getElementById('summary-subtotal').textContent = "₹" + subtotal.toFixed(2);
    
    if (taxTreatment === 'LOCAL') {
        document.getElementById('summary-tax-label').textContent = "CGST & SGST:";
        document.getElementById('summary-tax-total').textContent = "₹" + (cgst + sgst).toFixed(2);
    } else {
        document.getElementById('summary-tax-label').textContent = "IGST Total:";
        document.getElementById('summary-tax-total').textContent = "₹" + igst.toFixed(2);
    }
    
    document.getElementById('summary-round-off').textContent = (roundOff >= 0 ? '+' : '') + "₹" + roundOff.toFixed(2);
    document.getElementById('summary-grand-total').textContent = "₹" + roundedGrandTotal.toFixed(2);
}

// Unified submit lock and checkout validation
let isSubmitted = false;

function submitCheckoutForm() {
    if (isSubmitted) return;
    
    if (Object.keys(cart).length === 0) {
        alert('POS Error: Cannot checkout with an empty cart.');
        return;
    }
    
    isSubmitted = true;
    
    const btn = document.getElementById('pos-submit-btn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing [F8]...';
    }
    
    document.getElementById('pos-checkout-form').submit();
}

// Keyboard shortcuts handlers
window.addEventListener('keydown', function(e) {
    // F2 to focus Search Input
    if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('pos-search').focus();
        document.getElementById('pos-search').select();
    }
    
    // F4 to toggle payment method dropdown
    if (e.key === 'F4') {
        e.preventDefault();
        const payMethod = document.getElementById('payment_method');
        payMethod.focus();
    }
    
    // F8 to trigger submit/checkout
    if (e.key === 'F8') {
        e.preventDefault();
        submitCheckoutForm();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
