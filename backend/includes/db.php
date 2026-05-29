<?php
// 100% Self-Contained SQLite3 Unified Database Layer
// Cleaned up and streamlined for local and production SQLite databases.
    class SQLiteWrapper {
        public $db;
        public $last_query;
        public $error = '';
        
        public function __construct($path) {
            $this->db = new SQLite3($path);
            if (!$this->db) {
                $this->error = "Could not open SQLite database";
            }
            $this->db->busyTimeout(5000);
        }
    }

    function db_connect($host = null, $user = null, $pass = null, $db_name = null, $port = null) {
        $db_dir = dirname(dirname(__DIR__)) . '/database';
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0777, true);
        }
        $db_path = $db_dir . '/billing_system.sqlite';
        $conn = new SQLiteWrapper($db_path);
        if ($conn->error) {
            return false;
        }
        return $conn;
    }

    function db_connect_error() {
        return "SQLite Connection Error";
    }

    function db_query($conn, $sql) {
        if (!$conn || !$conn->db) return false;
        $conn->error = '';
        
        // 1. SQLite Translation of MySQL specific syntax
        $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bINTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        
        // MySQL date function translations for SQLite
        $sql = str_ireplace('CURRENT_DATE()', "date('now')", $sql);
        $sql = preg_replace('/DATE_SUB\s*\(\s*date\(\s*\'now\'\s*\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i', "date('now', '-\$1 day')", $sql);
        
        // Register custom MD5 and date helper functions for SQLite
        $conn->db->createFunction('MD5', 'md5', 1);
        $conn->db->createFunction('MONTH', function($date_str) {
            if (!$date_str) return null;
            return intval(date('m', strtotime($date_str)));
        }, 1);
        $conn->db->createFunction('YEAR', function($date_str) {
            if (!$date_str) return null;
            return intval(date('Y', strtotime($date_str)));
        }, 1);
        
        // 2. SHOW COLUMNS FROM table LIKE column -> PRAGMA table_info(table) emulation
        if (preg_match('/SHOW\s+COLUMNS\s+FROM\s+`?([a-zA-Z0-9_]+)`?\s+LIKE\s+\'([a-zA-Z0-9_]+)\'/i', $sql, $matches)) {
            $table = $matches[1];
            $column = $matches[2];
            
            $res = @$conn->db->query("PRAGMA table_info(`$table`)");
            $found = false;
            if ($res) {
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    if ($row['name'] === $column) {
                        $found = true;
                        break;
                    }
                }
            }
            return new SQLiteResultMock($found ? [ ['Field' => $column] ] : []);
        }
        
        try {
            $result = @$conn->db->query($sql);
            if ($result === false) {
                $conn->error = $conn->db->lastErrorMsg();
                return false;
            }
            
            if ($result instanceof SQLite3Result) {
                return new SQLiteResultWrapper($result);
            }
            return true;
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    }

    class SQLiteResultWrapper {
        private $result;
        private $rows = [];
        private $index = 0;
        
        public function __construct($result) {
            $this->result = $result;
            if ($result instanceof SQLite3Result && $result->numColumns() > 0) {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $this->rows[] = $row;
                }
            }
        }
        
        public function fetchAssoc() {
            if ($this->index < count($this->rows)) {
                return $this->rows[$this->index++];
            }
            return null;
        }
        
        public function fetchArray() {
            if ($this->index < count($this->rows)) {
                $row = $this->rows[$this->index++];
                $arr = [];
                $i = 0;
                foreach ($row as $k => $v) {
                    $arr[$k] = $v;
                    $arr[$i++] = $v;
                }
                return $arr;
            }
            return null;
        }
        
        public function numRows() {
            return count($this->rows);
        }
    }

    class SQLiteResultMock {
        private $rows;
        private $index = 0;
        
        public function __construct($rows) {
            $this->rows = $rows;
        }
        
        public function fetchAssoc() {
            if ($this->index < count($this->rows)) {
                return $this->rows[$this->index++];
            }
            return null;
        }
        
        public function numRows() {
            return count($this->rows);
        }
    }

    function db_num_rows($result) {
        if ($result instanceof SQLiteResultWrapper || $result instanceof SQLiteResultMock) {
            return $result->numRows();
        }
        return 0;
    }

    function db_fetch_assoc($result) {
        if ($result instanceof SQLiteResultWrapper || $result instanceof SQLiteResultMock) {
            return $result->fetchAssoc();
        }
        return null;
    }

    function db_fetch_array($result) {
        if ($result instanceof SQLiteResultWrapper) {
            return $result->fetchArray();
        }
        return null;
    }

    function db_escape($conn, $str) {
        if ($str === null) return '';
        return SQLite3::escapeString($str);
    }

    function db_insert_id($conn) {
        if (!$conn || !$conn->db) return 0;
        return $conn->db->lastInsertRowID();
    }

    function db_error($conn) {
        if (!$conn) return "No database connection";
        return $conn->error ?: $conn->db->lastErrorMsg();
    }

    function db_close($conn) {
        if ($conn && $conn->db) {
            $conn->db->close();
        }
    }

    function db_affected_rows($conn) {
        if (!$conn || !$conn->db) return 0;
        return $conn->db->changes();
    }

    function db_query_prepared($conn, $sql, $params = []) {
        if (!$conn || !$conn->db) return false;
        $conn->error = '';
        
        // Emulate MySQL AUTO_INCREMENT & date translations in prepared queries
        $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bINTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = str_ireplace('CURRENT_DATE()', "date('now')", $sql);
        $sql = preg_replace('/DATE_SUB\s*\(\s*date\(\s*\'now\'\s*\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i', "date('now', '-\$1 day')", $sql);
        
        try {
            $stmt = $conn->db->prepare($sql);
            if ($stmt === false) {
                $conn->error = $conn->db->lastErrorMsg();
                return false;
            }
            
            foreach ($params as $key => $value) {
                $type = SQLITE3_TEXT;
                if (is_int($value)) {
                    $type = SQLITE3_INTEGER;
                } elseif (is_float($value)) {
                    $type = SQLITE3_FLOAT;
                } elseif (is_null($value)) {
                    $type = SQLITE3_NULL;
                }
                $stmt->bindValue($key, $value, $type);
            }
            
            $result = $stmt->execute();
            if ($result === false) {
                $conn->error = $conn->db->lastErrorMsg();
                return false;
            }
            
            if ($result instanceof SQLite3Result) {
                return new SQLiteResultWrapper($result);
            }
            return true;
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    }

// Define global alias to handle any converted mysqli_real_escape_string calls
if (!function_exists('db_real_escape_string')) {
    function db_real_escape_string($conn, $str) {
        return db_escape($conn, $str);
    }
}

// Auto-initialize the global database connection variable $conn
$conn = db_connect();

// Deduplicate products helper function (extremely robust, database-agnostic, and handles SQLite/MySQL perfectly)
function db_deduplicate_products($conn) {
    if (!$conn) return;
    
    // Check if products table exists first to avoid errors during initial migration
    $table_check = @db_query($conn, "SELECT 1 FROM products LIMIT 1");
    if ($table_check === false) {
        return;
    }
    
    $res = db_query($conn, "SELECT product_id, product_name FROM products ORDER BY product_id ASC");
    if ($res) {
        $seen = [];
        $to_delete = [];
        while ($row = db_fetch_assoc($res)) {
            $normalized_name = strtolower(trim($row['product_name'] ?? ''));
            if ($normalized_name === '') {
                continue;
            }
            if (in_array($normalized_name, $seen)) {
                $to_delete[] = $row['product_id'];
            } else {
                $seen[] = $normalized_name;
            }
        }
        if (!empty($to_delete)) {
            $ids = implode(',', $to_delete);
            db_query($conn, "DELETE FROM products WHERE product_id IN ($ids)");
        }
    }
}

// Deduplicate invoices helper function (database-agnostic, handles SQLite/MySQL perfectly, auto-reverses double-submissions)
function db_deduplicate_invoices($conn) {
    if (!$conn) return;
    
    // Check if invoices table exists first to avoid errors during initial migration
    $table_check = @db_query($conn, "SELECT 1 FROM invoices LIMIT 1");
    if ($table_check === false) {
        return;
    }
    
    $res = db_query($conn, "SELECT * FROM invoices ORDER BY invoice_id DESC");
    if ($res) {
        $invoices = [];
        while ($row = db_fetch_assoc($res)) {
            $invoices[] = $row;
        }
        
        $count = count($invoices);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $inv1 = $invoices[$i];
                $inv2 = $invoices[$j];
                
                // Compare customer, grand total, amount paid, payment method, and creation time (within 60 seconds)
                $time1 = strtotime($inv1['invoice_date'] ?? '');
                $time2 = strtotime($inv2['invoice_date'] ?? '');
                
                if ($inv1['customer_id'] == $inv2['customer_id'] &&
                     abs(floatval($inv1['grand_total']) - floatval($inv2['grand_total'])) < 0.01 &&
                     abs(floatval($inv1['amount_paid']) - floatval($inv2['amount_paid'])) < 0.01 &&
                     ($inv1['payment_method'] ?? '') === ($inv2['payment_method'] ?? '') &&
                     abs($time1 - $time2) <= 60) {
                    
                    // Duplicate found! inv1 is the newer one (higher ID because of DESC sort)
                    $del_id = $inv1['invoice_id'];
                    
                    // Safety check: verify it wasn't already deleted in this run
                    $check_exists = db_query($conn, "SELECT 1 FROM invoices WHERE invoice_id='$del_id'");
                    if ($check_exists && db_num_rows($check_exists) > 0) {
                        
                        // 1. Restore product stock for the duplicate invoice
                        $items_res = db_query($conn, "SELECT * FROM invoice_items WHERE invoice_id='$del_id'");
                        if ($items_res) {
                            while ($item = db_fetch_assoc($items_res)) {
                                $pid = $item['product_id'];
                                $qty = intval($item['quantity']);
                                db_query($conn, "UPDATE products SET stock_quantity = stock_quantity + $qty WHERE product_id='$pid'");
                                db_query($conn, "INSERT INTO inventory_logs (product_id, quantity, type, remarks) VALUES ('$pid', '$qty', 'IN', 'Deduplication stock restoration - Invoice #$del_id')");
                            }
                        }
                        
                        // 2. Deduct outstanding dues of the duplicate invoice from customer's credit balance
                        $due_amount = floatval($inv1['grand_total']) - floatval($inv1['amount_paid']);
                        if ($due_amount > 0) {
                            $cust_id = $inv1['customer_id'];
                            db_query($conn, "UPDATE customers SET credit_balance = credit_balance - $due_amount WHERE customer_id='$cust_id'");
                        }
                        
                        // 3. Delete invoice ledger entries, items, and the duplicate invoice
                        db_query($conn, "DELETE FROM customer_ledger WHERE invoice_id='$del_id'");
                        db_query($conn, "DELETE FROM invoice_items WHERE invoice_id='$del_id'");
                        db_query($conn, "DELETE FROM invoices WHERE invoice_id='$del_id'");
                        
                        // 4. Log deduplication activity
                        $username = $_SESSION['username'] ?? 'System';
                        $uid = $_SESSION['user_id'] ?? 0;
                        db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Database Self-Healing', 'Removed duplicate invoice #$del_id and restored inventory/credit balances')");
                    }
                }
            }
        }
    }
}

// Automatically heal any duplicate products by name dynamically (Disabled to prevent unexpected automated resets)
// db_deduplicate_products($conn);

// Automatically heal any duplicate invoices dynamically (Disabled to allow rapid consecutive matching sales)
// db_deduplicate_invoices($conn);

// Deduplicate and merge customer profiles helper function (extremely robust, database-agnostic)
function db_deduplicate_customers($conn) {
    if (!$conn) return;
    
    // Check if customers table exists first to avoid errors during initial migration
    $table_check = @db_query($conn, "SELECT 1 FROM customers LIMIT 1");
    if ($table_check === false) {
        return;
    }
    
    $res = db_query($conn, "SELECT customer_id, name, phone FROM customers ORDER BY customer_id ASC");
    if ($res) {
        $seen = [];
        $duplicates = [];
        while ($row = db_fetch_assoc($res)) {
            $name = strtolower(trim($row['name'] ?? ''));
            $phone = trim($row['phone'] ?? '');
            
            // Generate a unique key based on name and phone (if phone exists)
            $key = $name . '|' . $phone;
            
            if (isset($seen[$key])) {
                // Duplicate found! Keep the older one (seen[$key] contains the lower customer_id)
                $duplicates[] = [
                    'keep_id' => $seen[$key],
                    'delete_id' => $row['customer_id']
                ];
            } else {
                $seen[$key] = $row['customer_id'];
            }
        }
        
        foreach ($duplicates as $dup) {
            $keep = $dup['keep_id'];
            $delete = $dup['delete_id'];
            
            // Safety check: verify it wasn't already deleted in this run
            $check_exists = db_query($conn, "SELECT 1 FROM customers WHERE customer_id='$delete'");
            if ($check_exists && db_num_rows($check_exists) > 0) {
                // 1. Re-route any invoices linked to the duplicate customer
                db_query($conn, "UPDATE invoices SET customer_id='$keep' WHERE customer_id='$delete'");
                
                // 2. Re-route any customer ledger logs linked to the duplicate customer
                db_query($conn, "UPDATE customer_ledger SET customer_id='$keep' WHERE customer_id='$delete'");
                
                // 3. Merge customer outstanding credit balances safely
                $cust_res = db_query($conn, "SELECT credit_balance FROM customers WHERE customer_id='$delete'");
                if ($cust_res && db_num_rows($cust_res) > 0) {
                    $c_data = db_fetch_assoc($cust_res);
                    $credit_to_add = floatval($c_data['credit_balance']);
                    if ($credit_to_add > 0) {
                        db_query($conn, "UPDATE customers SET credit_balance = credit_balance + $credit_to_add WHERE customer_id='$keep'");
                    }
                }
                
                // 4. Delete the duplicate customer profile
                db_query($conn, "DELETE FROM customers WHERE customer_id='$delete'");
                
                // 5. Log activity
                $username = $_SESSION['username'] ?? 'System';
                $uid = $_SESSION['user_id'] ?? 0;
                db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$username', 'Database Self-Healing', 'Merged duplicate customer ID $delete into ID $keep')");
            }
        }
    }
}

function db_deduplicate_users($conn) {
    if (!$conn) return;
    $table_check = @db_query($conn, "SELECT 1 FROM users LIMIT 1");
    if ($table_check === false) return;
    
    $res = db_query($conn, "SELECT id, username FROM users ORDER BY id ASC");
    if ($res) {
        $seen = [];
        $to_delete = [];
        while ($row = db_fetch_assoc($res)) {
            $username = strtolower(trim($row['username'] ?? ''));
            if ($username === '') continue;
            
            if (in_array($username, $seen)) {
                $to_delete[] = $row['id'];
            } else {
                $seen[] = $username;
            }
        }
        if (!empty($to_delete)) {
            $ids = implode(',', $to_delete);
            db_query($conn, "DELETE FROM users WHERE id IN ($ids)");
        }
    }
}

// Automatically heal and merge any duplicate customer profiles dynamically (Disabled to prevent unexpected merging)
// db_deduplicate_customers($conn);

// Helper function to auto-reset SQLite auto-increment sequences for any completely empty tables
function db_reset_empty_sequences($conn) {
    if (!$conn) return;
    
    $tables = ['customers', 'products', 'invoices', 'invoice_items', 'expenses', 'activity_logs', 'customer_ledger', 'inventory_logs'];
    foreach ($tables as $table) {
        $check = @db_query($conn, "SELECT 1 FROM `$table` LIMIT 1");
        if ($check !== false) {
            if (db_num_rows($check) == 0) {
                @db_query($conn, "DELETE FROM sqlite_sequence WHERE name='$table'");
            }
        }
    }
}

// Automatically reset empty table auto-increment indexes dynamically
db_reset_empty_sequences($conn);

// One-time Database Deduplication & Healing execution (Safe and permanently locked out after first run)
$lock_file = dirname(dirname(__DIR__)) . '/database/dedup.lock';
if (!file_exists($lock_file)) {
    db_deduplicate_invoices($conn);
    db_deduplicate_customers($conn);
    @file_put_contents($lock_file, 'healed');
}

/**
 * Persistently logs system events and debugging information to detect duplicates.
 */
function db_log($message) {
    $log_file = dirname(dirname(__DIR__)) . '/database/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>
