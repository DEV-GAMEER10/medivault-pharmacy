<?php
require 'c:/xampp/htdocs/my_store/config/database.php';
try {
    $pdo->exec('DROP TABLE IF EXISTS subscriptions');
    $pdo->exec('CREATE TABLE subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        user_id INT NOT NULL, 
        plan_name VARCHAR(50) NOT NULL, 
        start_time DATETIME DEFAULT CURRENT_TIMESTAMP, 
        expiry_time DATETIME NOT NULL, 
        medicine_limit INT NOT NULL, 
        staff_limit INT NOT NULL, 
        daily_sales_limit INT NOT NULL
    )');
    echo "DB Subscriptions Schema Created\n";
} catch(Exception $e){ echo $e->getMessage(); }
