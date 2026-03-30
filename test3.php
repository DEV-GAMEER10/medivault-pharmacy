<?php
require 'config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM sales");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('test_sales_db.json', json_encode($cols));
