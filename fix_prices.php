<?php
require 'config/database.php';
try {
    // Show columns
    $stmt = $pdo->query("SHOW COLUMNS FROM medicines");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n\n";

    // Show sample of items with price 0
    $stmt = $pdo->query("SELECT * FROM medicines WHERE UnitPrice = 0 OR UnitPrice IS NULL LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample items with 0 price:\n";
    print_r($items);

} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
