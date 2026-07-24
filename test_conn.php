<?php
require_once "backend/includes/db.php";

var_dump($conn);

echo "<br>Error: ";

if ($conn === false) {
    echo "Connection failed";
} else {
    echo db_error($conn);
}
