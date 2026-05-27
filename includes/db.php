<?php
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: '');
$db_name = getenv('DB_NAME') ?: 'billing_system';
$port = getenv('DB_PORT') ?: 3306;

$conn = mysqli_connect($host, $user, $pass, $db_name, $port);

if(!$conn){
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>
