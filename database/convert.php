<?php
echo "<h2>Starting Code Conversion from MySQLi to Unified DB Layer (SQLite)...</h2>";

$dir = dirname(__DIR__);
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$converted = 0;
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        
        // Skip db.php and convert.php itself
        if (basename($path) === 'db.php' || basename($path) === 'convert.php') {
            continue;
        }
        
        $content = file_get_contents($path);
        if (strpos($content, 'mysqli_') !== false) {
            $content = str_replace('mysqli_', 'db_', $content);
            file_put_contents($path, $content);
            echo "Converted: " . basename($path) . "<br>";
            $converted++;
        }
    }
}

echo "<h3>Conversion complete! Converted $converted files.</h3>";
echo "<a href='../database/migrate.php'>Proceed to Database Migration</a>";
?>
