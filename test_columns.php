<?php
require 'config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM sales");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Columns in sales: " . implode(", ", $columns) . "\n";
