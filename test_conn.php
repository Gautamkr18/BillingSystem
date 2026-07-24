<?php
$db_url = getenv('DATABASE_URL');
echo "DATABASE_URL is: " . ($db_url ? "Set" : "Empty") . "<br>";

if ($db_url) {
    try {
        $url = parse_url($db_url);
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
            $url['host'],
            $url['port'] ?? 5432,
            ltrim($url['path'], '/')
        );
        echo "DSN: $dsn <br>";
        
        $pdo = new PDO($dsn, $url['user'] ?? '', $url['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "<strong style='color:green;'>Connection Successful!</strong>";
    } catch (Exception $e) {
        echo "<strong style='color:red;'>Connection Failed: " . $e->getMessage() . "</strong>";
    }
} else {
    echo "No DATABASE_URL found in environment variables.";
}
?>
