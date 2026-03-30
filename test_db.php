<?php
require 'config/database.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
print_r($tables);

if (in_array('customers', $tables)) {
    echo "\nColumns in customers:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM customers");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
}
