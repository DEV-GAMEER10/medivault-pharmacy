<?php
require 'config/database.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'CustomerID'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN CustomerID INT NULL");
        echo "CustomerID added\n";
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'DoctorName'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN DoctorName VARCHAR(100) NULL");
        echo "DoctorName added\n";
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
