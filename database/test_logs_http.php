<?php
header('Content-Type: text/plain');
include __DIR__ . '/../backend/includes/db.php';

echo "==================================================\n";
echo "       SQLite Database Health & Integrity Check   \n";
echo "==================================================\n\n";

// 1. Connection check
if (!$conn || !$conn->db) {
    echo "❌ CONNECTION ERROR: Could not establish a database connection.\n";
    exit;
}
echo "✅ CONNECTION: Successful connection to SQLite database file.\n";

// 2. Database File details
$db_path = dirname(__DIR__) . '/database/billing_system.sqlite';
echo "📁 DATABASE PATH: " . realpath($db_path) . "\n";
if (file_exists($db_path)) {
    echo "📁 DATABASE SIZE: " . number_format(filesize($db_path) / 1024, 2) . " KB\n";
    echo "📁 WRITE PERMISSION: " . (is_writable($db_path) ? "✅ Yes (Writable)" : "❌ No (Read-Only)") . "\n\n";
} else {
    echo "❌ ERROR: Database file does not exist at expected path!\n\n";
}

// 3. SQLite Integrity Check
echo "🔍 INTEGRITY STATUS:\n";
$integrity = db_query($conn, "PRAGMA integrity_check");
if ($integrity) {
    $row = db_fetch_assoc($integrity);
    $status = $row['integrity_check'] ?? 'unknown';
    if (strtolower($status) === 'ok') {
        echo "   ✅ PRAGMA integrity_check: OK (No corruption detected)\n\n";
    } else {
        echo "   ⚠️ WARNING: PRAGMA integrity_check returned: $status\n\n";
    }
} else {
    echo "   ❌ ERROR: Could not run integrity check. " . db_error($conn) . "\n\n";
}

// 4. Schema & Table Record Counts
echo "📊 SYSTEM TABLES & RECORD COUNTS:\n";
$tables = ['users', 'customers', 'products', 'invoices', 'invoice_items', 'expenses', 'activity_logs', 'customer_ledger', 'inventory_logs'];

foreach ($tables as $table) {
    $res = @db_query($conn, "SELECT COUNT(*) as cnt FROM `$table`");
    if ($res) {
        $row = db_fetch_assoc($res);
        $count = $row['cnt'] ?? 0;
        echo "   🔹 Table '" . str_pad($table, 17) . "': " . str_pad($count, 5, ' ', STR_PAD_LEFT) . " records\n";
    } else {
        echo "   ❌ Table '" . str_pad($table, 17) . "': Table does not exist or has errors! (" . db_error($conn) . ")\n";
    }
}

echo "\n";
echo "==================================================\n";
echo "                  Check Completed                 \n";
echo "==================================================\n";
?>
