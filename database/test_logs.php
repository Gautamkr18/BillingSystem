<?php
include __DIR__ . '/../backend/includes/db.php';
$res = db_query($conn, "SELECT * FROM activity_logs ORDER BY id DESC LIMIT 10");
if ($res) {
    while ($row = db_fetch_assoc($res)) {
        echo "ID: " . $row['id'] . " | Timestamp: " . $row['timestamp'] . " | Action: " . $row['action'] . " | Details: " . $row['details'] . "\n";
    }
} else {
    echo "Query failed: " . db_error($conn) . "\n";
}
?>
