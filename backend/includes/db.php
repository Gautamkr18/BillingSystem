<?php
/**
 * Unified Database Abstraction Layer
 *
 * Automatically detects environment:
 *   - DATABASE_URL env var set  → PostgreSQL via PDO (Render production)
 *   - DATABASE_URL not set      → SQLite3 (local XAMPP development)
 *
 * Exposes a consistent API so all PHP files work without modification:
 * db_connect(), db_query(), db_fetch_assoc(), db_fetch_array(),
 * db_num_rows(), db_escape(), db_insert_id(), db_error(), db_close()
 */

$env_db_url = getenv('DATABASE_URL');
if (empty($env_db_url) && isset($_ENV['DATABASE_URL'])) $env_db_url = $_ENV['DATABASE_URL'];
if (empty($env_db_url) && isset($_SERVER['DATABASE_URL'])) $env_db_url = $_SERVER['DATABASE_URL'];

define('DB_MODE', !empty($env_db_url) ? 'pgsql' : 'sqlite');

// ============================================================
// UNIFIED RESULT WRAPPER (shared by both SQLite and PostgreSQL)
// ============================================================
class DbResultWrapper {
    private $rows = [];
    private $index = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
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

// ============================================================
// CONNECTION CLASS
// ============================================================
class DbConnection {
    public $mode;           // 'pgsql' or 'sqlite'
    public $pdo  = null;   // PDO instance (PostgreSQL)
    public $db   = null;   // SQLite3 instance (SQLite)
    public $error = '';
    public $last_insert_id = 0;

    public function __construct(string $mode) {
        $this->mode = $mode;
    }
}

// ============================================================
// db_connect()
// ============================================================
function db_connect($host = null, $user = null, $pass = null, $db_name = null, $port = null) {
    $conn = new DbConnection(DB_MODE);

    if (DB_MODE === 'pgsql') {
        // ── PostgreSQL (Render production) ──────────────────
        global $env_db_url;
        $db_url = $env_db_url;
        if (empty($db_url)) {
             $db_url = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? ''));
        }
        try {
            $url = parse_url($db_url);
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
                $url['host'],
                $url['port'] ?? 5432,
                ltrim($url['path'], '/')
            );
            $conn->pdo = new PDO($dsn, $url['user'] ?? '', $url['pass'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    } else {
        // ── SQLite (local XAMPP development) ────────────────
        $db_dir = dirname(dirname(__DIR__)) . '/database';
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0777, true);
        }
        $db_path = $db_dir . '/billing_system.sqlite';
        try {
            $conn->db = new SQLite3($db_path);
            if (!$conn->db) {
                $conn->error = 'Could not open SQLite database';
                return false;
            }
            $conn->db->busyTimeout(5000);
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    }

    return $conn;
}

function db_connect_error() {
    return 'Database Connection Error';
}

// ============================================================
// SQL TRANSLATION: MySQL-style → PostgreSQL
// ============================================================
function _pgsql_translate(string $sql): string {
    // Skip SQLite-only / no-op statements
    if (preg_match('/^\s*DELETE\s+FROM\s+sqlite_sequence/i', $sql)) return '--noop';
    if (preg_match('/^\s*PRAGMA\s+/i', $sql))                       return '--noop';

    // AUTO_INCREMENT → SERIAL
    $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i',     'SERIAL PRIMARY KEY', $sql);
    $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i',     'SERIAL PRIMARY KEY', $sql);
    $sql = preg_replace('/\bINTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'SERIAL PRIMARY KEY', $sql);
    $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'SERIAL PRIMARY KEY', $sql);

    // Date functions
    $sql = str_ireplace('CURRENT_DATE()', 'CURRENT_DATE', $sql);
    $sql = preg_replace(
        '/DATE_SUB\s*\(\s*(?:CURRENT_DATE|date\s*\(\s*\'now\'\s*\))\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i',
        "CURRENT_DATE - INTERVAL '\\1 days'",
        $sql
    );

    // Aggregate / scalar functions
    $sql = preg_replace('/\bMONTH\s*\(([^)]+)\)/i', 'EXTRACT(MONTH FROM \\1)::INT', $sql);
    $sql = preg_replace('/\bYEAR\s*\(([^)]+)\)/i',  'EXTRACT(YEAR FROM \\1)::INT',  $sql);

    // Backtick identifiers → double-quoted (PostgreSQL standard)
    $sql = str_replace('`', '"', $sql);

    return $sql;
}

// ============================================================
// SQL TRANSLATION: MySQL-style → SQLite
// ============================================================
function _sqlite_translate(string $sql): string {
    $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i',     'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i',     'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = str_ireplace('CURRENT_DATE()', "date('now')", $sql);
    $sql = preg_replace(
        '/DATE_SUB\s*\(\s*date\s*\(\s*\'now\'\s*\)\s*,\s*INTERVAL\s+(\d+)\s+DAY\s*\)/i',
        "date('now', '-\\1 day')",
        $sql
    );
    return $sql;
}

// ============================================================
// SHOW COLUMNS emulation helper for PostgreSQL
// ============================================================
function _pgsql_show_columns(DbConnection $conn, string $table, string $column): DbResultWrapper {
    try {
        $stmt = $conn->pdo->prepare(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = :t AND column_name = :c"
        );
        $stmt->execute([':t' => $table, ':c' => $column]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return new DbResultWrapper(array_map(fn($r) => ['Field' => $r['column_name']], $rows));
    } catch (Exception $e) {
        return new DbResultWrapper([]);
    }
}

// ============================================================
// SHOW COLUMNS emulation helper for SQLite
// ============================================================
function _sqlite_show_columns(DbConnection $conn, string $table, string $column): DbResultWrapper {
    $res = @$conn->db->query("PRAGMA table_info(`$table`)");
    $found = false;
    if ($res) {
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            if ($row['name'] === $column) { $found = true; break; }
        }
    }
    return new DbResultWrapper($found ? [['Field' => $column]] : []);
}

// ============================================================
// db_query()
// ============================================================
function db_query($conn, $sql) {
    if (!$conn) return false;
    $conn->error = '';

    // ── SHOW COLUMNS emulation ───────────────────────────────
    if (preg_match('/SHOW\s+COLUMNS\s+FROM\s+`?(\w+)`?\s+LIKE\s+\'(\w+)\'/i', $sql, $m)) {
        return $conn->mode === 'pgsql'
            ? _pgsql_show_columns($conn, $m[1], $m[2])
            : _sqlite_show_columns($conn, $m[1], $m[2]);
    }

    if ($conn->mode === 'pgsql') {
        // ── PostgreSQL ───────────────────────────────────────
        $sql = _pgsql_translate($sql);
        if (trim($sql) === '--noop') return true;

        try {
            $stmt = $conn->pdo->query($sql);

            // Track last inserted ID for SERIAL columns
            if (preg_match('/^\s*INSERT\s+/i', $sql)) {
                try {
                    $idRes = $conn->pdo->query('SELECT lastval()');
                    $idRow = $idRes ? $idRes->fetch(PDO::FETCH_NUM) : null;
                    $conn->last_insert_id = $idRow ? (int)$idRow[0] : 0;
                } catch (Exception $e) {
                    $conn->last_insert_id = 0;
                }
            }

            if ($stmt === false) return false;

            if ($stmt->columnCount() > 0) {
                return new DbResultWrapper($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            return true;
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    } else {
        // ── SQLite ───────────────────────────────────────────
        $sql = _sqlite_translate($sql);

        $conn->db->createFunction('MD5',   'md5', 1);
        $conn->db->createFunction('MONTH', fn($d) => $d ? (int)date('m', strtotime($d)) : null, 1);
        $conn->db->createFunction('YEAR',  fn($d) => $d ? (int)date('Y', strtotime($d)) : null, 1);

        try {
            $result = @$conn->db->query($sql);
            if ($result === false) {
                $conn->error = $conn->db->lastErrorMsg();
                return false;
            }
            if ($result instanceof SQLite3Result) {
                $rows = [];
                if ($result->numColumns() > 0) {
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $rows[] = $row;
                    }
                }
                return new DbResultWrapper($rows);
            }
            return true;
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    }
}

// ============================================================
// db_query_prepared()
// ============================================================
function db_query_prepared($conn, $sql, $params = []) {
    if (!$conn) return false;
    $conn->error = '';

    if ($conn->mode === 'pgsql') {
        $sql = _pgsql_translate($sql);
        if (trim($sql) === '--noop') return true;

        try {
            $stmt = $conn->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $type = PDO::PARAM_STR;
                if (is_int($value))  $type = PDO::PARAM_INT;
                if (is_null($value)) $type = PDO::PARAM_NULL;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();

            if (preg_match('/^\s*INSERT\s+/i', $sql)) {
                try {
                    $idRes = $conn->pdo->query('SELECT lastval()');
                    $idRow = $idRes ? $idRes->fetch(PDO::FETCH_NUM) : null;
                    $conn->last_insert_id = $idRow ? (int)$idRow[0] : 0;
                } catch (Exception $e) {
                    $conn->last_insert_id = 0;
                }
            }

            if ($stmt->columnCount() > 0) {
                return new DbResultWrapper($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            return true;
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    } else {
        $sql = _sqlite_translate($sql);

        try {
            $stmt = $conn->db->prepare($sql);
            if ($stmt === false) {
                $conn->error = $conn->db->lastErrorMsg();
                return false;
            }
            foreach ($params as $key => $value) {
                $type = SQLITE3_TEXT;
                if (is_int($value))   $type = SQLITE3_INTEGER;
                if (is_float($value)) $type = SQLITE3_FLOAT;
                if (is_null($value))  $type = SQLITE3_NULL;
                $stmt->bindValue($key, $value, $type);
            }
            $result = $stmt->execute();
            if ($result === false) {
                $conn->error = $conn->db->lastErrorMsg();
                return false;
            }
            if ($result instanceof SQLite3Result) {
                $rows = [];
                if ($result->numColumns() > 0) {
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $rows[] = $row;
                    }
                }
                return new DbResultWrapper($rows);
            }
            return true;
        } catch (Exception $e) {
            $conn->error = $e->getMessage();
            return false;
        }
    }
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function db_num_rows($result) {
    if ($result instanceof DbResultWrapper) return $result->numRows();
    return 0;
}

function db_fetch_assoc($result) {
    if ($result instanceof DbResultWrapper) return $result->fetchAssoc();
    return null;
}

function db_fetch_array($result) {
    if ($result instanceof DbResultWrapper) return $result->fetchArray();
    return null;
}

function db_escape($conn, $str) {
    if ($str === null) return '';
    if ($conn && $conn->mode === 'pgsql' && $conn->pdo) {
        $quoted = $conn->pdo->quote($str);
        return substr($quoted, 1, -1); // Strip surrounding quotes
    }
    return SQLite3::escapeString($str);
}

function db_insert_id($conn) {
    if (!$conn) return 0;
    if ($conn->mode === 'pgsql') return (int)($conn->last_insert_id ?? 0);
    return $conn->db->lastInsertRowID();
}

function db_error($conn) {
    if (!$conn) return 'No database connection';
    return $conn->error ?: ($conn->mode === 'sqlite' ? $conn->db->lastErrorMsg() : '');
}

function db_close($conn) {
    if ($conn && $conn->mode === 'sqlite' && $conn->db) {
        $conn->db->close();
    }
    // PDO connections close automatically on unset/end-of-scope
}

function db_affected_rows($conn) {
    return 0; // Safe default — use db_num_rows for SELECT counts
}

if (!function_exists('db_real_escape_string')) {
    function db_real_escape_string($conn, $str) {
        return db_escape($conn, $str);
    }
}

// ============================================================
// AUTO-INITIALIZE GLOBAL CONNECTION
// ============================================================
$conn = db_connect();

// ============================================================
// DEDUPLICATION HELPERS (preserved from original)
// ============================================================

function db_deduplicate_products($conn) {
    if (!$conn) return;
    $table_check = @db_query($conn, "SELECT 1 FROM products LIMIT 1");
    if ($table_check === false) return;

    $res = db_query($conn, "SELECT product_id, product_name FROM products ORDER BY product_id ASC");
    if ($res) {
        $seen = []; $to_delete = [];
        while ($row = db_fetch_assoc($res)) {
            $name = strtolower(trim($row['product_name'] ?? ''));
            if ($name === '') continue;
            if (in_array($name, $seen)) {
                $to_delete[] = $row['product_id'];
            } else {
                $seen[] = $name;
            }
        }
        if (!empty($to_delete)) {
            $ids = implode(',', $to_delete);
            db_query($conn, "DELETE FROM products WHERE product_id IN ($ids)");
        }
    }
}

function db_deduplicate_invoices($conn) {
    if (!$conn) return;
    $table_check = @db_query($conn, "SELECT 1 FROM invoices LIMIT 1");
    if ($table_check === false) return;

    $res = db_query($conn, "SELECT * FROM invoices ORDER BY invoice_id DESC");
    if (!$res) return;

    $invoices = [];
    while ($row = db_fetch_assoc($res)) { $invoices[] = $row; }

    $count = count($invoices);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $inv1 = $invoices[$i]; $inv2 = $invoices[$j];
            $t1 = strtotime($inv1['invoice_date'] ?? '');
            $t2 = strtotime($inv2['invoice_date'] ?? '');

            if ($inv1['customer_id'] == $inv2['customer_id'] &&
                abs((float)$inv1['grand_total']  - (float)$inv2['grand_total'])  < 0.01 &&
                abs((float)$inv1['amount_paid']  - (float)$inv2['amount_paid'])  < 0.01 &&
                ($inv1['payment_method'] ?? '') === ($inv2['payment_method'] ?? '') &&
                abs($t1 - $t2) <= 60) {

                $del_id = $inv1['invoice_id'];
                $chk = db_query($conn, "SELECT 1 FROM invoices WHERE invoice_id='$del_id'");
                if ($chk && db_num_rows($chk) > 0) {
                    $items = db_query($conn, "SELECT * FROM invoice_items WHERE invoice_id='$del_id'");
                    if ($items) {
                        while ($item = db_fetch_assoc($items)) {
                            $pid = $item['product_id']; $qty = (int)$item['quantity'];
                            db_query($conn, "UPDATE products SET stock_quantity = stock_quantity + $qty WHERE product_id='$pid'");
                            db_query($conn, "INSERT INTO inventory_logs (product_id, quantity, type, remarks) VALUES ('$pid', '$qty', 'IN', 'Deduplication stock restoration - Invoice #$del_id')");
                        }
                    }
                    $due = (float)$inv1['grand_total'] - (float)$inv1['amount_paid'];
                    if ($due > 0) {
                        $cid = $inv1['customer_id'];
                        db_query($conn, "UPDATE customers SET credit_balance = credit_balance - $due WHERE customer_id='$cid'");
                    }
                    db_query($conn, "DELETE FROM customer_ledger WHERE invoice_id='$del_id'");
                    db_query($conn, "DELETE FROM invoice_items WHERE invoice_id='$del_id'");
                    db_query($conn, "DELETE FROM invoices WHERE invoice_id='$del_id'");
                    $uname = $_SESSION['username'] ?? 'System';
                    $uid   = $_SESSION['user_id']  ?? 0;
                    db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$uname', 'Database Self-Healing', 'Removed duplicate invoice #$del_id and restored inventory/credit balances')");
                }
            }
        }
    }
}

function db_deduplicate_customers($conn) {
    if (!$conn) return;
    $table_check = @db_query($conn, "SELECT 1 FROM customers LIMIT 1");
    if ($table_check === false) return;

    $res = db_query($conn, "SELECT customer_id, name, phone FROM customers ORDER BY customer_id ASC");
    if (!$res) return;

    $seen = []; $duplicates = [];
    while ($row = db_fetch_assoc($res)) {
        $key = strtolower(trim($row['name'] ?? '')) . '|' . trim($row['phone'] ?? '');
        if (isset($seen[$key])) {
            $duplicates[] = ['keep_id' => $seen[$key], 'delete_id' => $row['customer_id']];
        } else {
            $seen[$key] = $row['customer_id'];
        }
    }

    foreach ($duplicates as $dup) {
        $keep = $dup['keep_id']; $del = $dup['delete_id'];
        $chk = db_query($conn, "SELECT 1 FROM customers WHERE customer_id='$del'");
        if ($chk && db_num_rows($chk) > 0) {
            db_query($conn, "UPDATE invoices SET customer_id='$keep' WHERE customer_id='$del'");
            db_query($conn, "UPDATE customer_ledger SET customer_id='$keep' WHERE customer_id='$del'");
            $cr = db_query($conn, "SELECT credit_balance FROM customers WHERE customer_id='$del'");
            if ($cr && db_num_rows($cr) > 0) {
                $cd = db_fetch_assoc($cr);
                $credit = (float)$cd['credit_balance'];
                if ($credit > 0) db_query($conn, "UPDATE customers SET credit_balance = credit_balance + $credit WHERE customer_id='$keep'");
            }
            db_query($conn, "DELETE FROM customers WHERE customer_id='$del'");
            $uname = $_SESSION['username'] ?? 'System';
            $uid   = $_SESSION['user_id']  ?? 0;
            db_query($conn, "INSERT INTO activity_logs (user_id, username, action, details) VALUES ('$uid', '$uname', 'Database Self-Healing', 'Merged duplicate customer ID $del into ID $keep')");
        }
    }
}

function db_deduplicate_users($conn) {
    if (!$conn) return;
    $table_check = @db_query($conn, "SELECT 1 FROM users LIMIT 1");
    if ($table_check === false) return;

    $res = db_query($conn, "SELECT id, username FROM users ORDER BY id ASC");
    if (!$res) return;

    $seen = []; $to_delete = [];
    while ($row = db_fetch_assoc($res)) {
        $uname = strtolower(trim($row['username'] ?? ''));
        if ($uname === '') continue;
        if (in_array($uname, $seen)) {
            $to_delete[] = $row['id'];
        } else {
            $seen[] = $uname;
        }
    }
    if (!empty($to_delete)) {
        $ids = implode(',', $to_delete);
        db_query($conn, "DELETE FROM users WHERE id IN ($ids)");
    }
}

function db_reset_empty_sequences($conn) {
    // Only applicable to SQLite (PostgreSQL sequences are managed automatically)
    if (!$conn || $conn->mode === 'pgsql') return;

    $tables = ['customers', 'products', 'invoices', 'invoice_items',
               'expenses', 'activity_logs', 'customer_ledger', 'inventory_logs'];
    foreach ($tables as $table) {
        $check = @db_query($conn, "SELECT 1 FROM `$table` LIMIT 1");
        if ($check !== false && db_num_rows($check) == 0) {
            @db_query($conn, "DELETE FROM sqlite_sequence WHERE name='$table'");
        }
    }
}

// Automatically reset empty table auto-increment sequences (SQLite only)
db_reset_empty_sequences($conn);

// One-time deduplication & healing on first startup
$lock_file = dirname(dirname(__DIR__)) . '/database/dedup.lock';
if (!file_exists($lock_file)) {
    db_deduplicate_invoices($conn);
    db_deduplicate_customers($conn);
    @file_put_contents($lock_file, 'healed');
}

// ============================================================
// LOGGING HELPER
// ============================================================
function db_log($message) {
    $log_file = dirname(dirname(__DIR__)) . '/database/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}
?>
