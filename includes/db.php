<?php
// SQLite & MySQLi Hybrid Unified Database Layer
// Automatically switches to SQLite if available (e.g. Render), 
// otherwise falls back to MySQLi dynamically (e.g. default XAMPP).

$use_sqlite = class_exists('SQLite3');

if ($use_sqlite) {
    // =============================================================
    // SQLite3 Implementation
    // =============================================================
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
        $db_dir = dirname(__DIR__) . '/db-data';
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
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $this->rows[] = $row;
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

    function db_free_result($result) {
        // Noop since SQLite releases resource automatically
    }
} else {
    // =============================================================
    // MySQLi Fallback Implementation (XAMPP default)
    // =============================================================
    function db_connect($host = null, $user = null, $pass = null, $db_name = null, $port = null) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: '');
        $db_name = getenv('DB_NAME') ?: 'billing_system';
        $port = getenv('DB_PORT') ?: 3306;
        
        // Prevent warning logs if extensions are missing
        $conn = @mysqli_connect($host, $user, $pass, $db_name, $port);
        return $conn;
    }

    function db_connect_error() {
        return mysqli_connect_error();
    }

    function db_query($conn, $sql) {
        return mysqli_query($conn, $sql);
    }

    function db_num_rows($result) {
        if (!$result) return 0;
        return mysqli_num_rows($result);
    }

    function db_fetch_assoc($result) {
        if (!$result) return null;
        return mysqli_fetch_assoc($result);
    }

    function db_fetch_array($result) {
        if (!$result) return null;
        return mysqli_fetch_array($result);
    }

    function db_escape($conn, $str) {
        if ($str === null) return '';
        return mysqli_real_escape_string($conn, $str);
    }

    function db_insert_id($conn) {
        return mysqli_insert_id($conn);
    }

    function db_error($conn) {
        if (!$conn) return "Database Connection Failed";
        return mysqli_error($conn);
    }

    function db_close($conn) {
        if ($conn) {
            return mysqli_close($conn);
        }
    }

    function db_affected_rows($conn) {
        return mysqli_affected_rows($conn);
    }

    function db_free_result($result) {
        if ($result) {
            return mysqli_free_result($result);
        }
    }
}

// Auto-initialize the global database connection variable $conn
$conn = db_connect();
?>
