<?php
require 'config/database.php';
$stmt = $pdo->query("SHOW COLUMNS FROM medicines");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
file_put_contents('medicines_cols.json', json_encode($cols));
