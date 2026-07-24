<?php
echo "<h2>Database Test</h2>";

echo "DATABASE_URL exists: ";
echo getenv('DATABASE_URL') ? "YES<br>" : "NO<br>";

echo "PDO available: ";
echo class_exists('PDO') ? "YES<br>" : "NO<br>";

echo "PostgreSQL driver: ";
echo in_array("pgsql", PDO::getAvailableDrivers()) ? "YES<br>" : "NO<br>";

print_r(PDO::getAvailableDrivers());
?>
