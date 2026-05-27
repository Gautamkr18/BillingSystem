<?php
// SQLite Unified Database Layer
// Translates procedural database calls to SQLite3 natively

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

// Emulate db_connect
function db_connect($host = null, $user = null, $pass = null, $db_name = null, $port = null) {
    $db_dir = dirname(__DIR__) . '/database';
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

// Emulate db_query with SQLite translations
function db_query($conn, $sql) {
    if (!$conn || !$conn->db) return false;
    $conn->error = '';
    
    // 1. SQLite Translation of MySQL specific syntax
    $sql = preg_replace('/\bINT\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINT\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINTEGER\s+AUTO_INCREMENT\s+PRIMARY\s+KEY\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTO_INCREMENT\b/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    
    // Register custom MD5 function so user creation MD5() calls work in SQLite
    $conn->db->createFunction('MD5', 'md5', 1);
    
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

// Wrapper for SQLite results
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

// Mock wrapper for empty/single results (like show columns check)
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

// Auto-initialize the global database connection variable $conn
$conn = db_connect();
?>
