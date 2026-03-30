<?php
require 'config/database.php';
try {
    // 1. If CostPrice > 0 but SellingPrice is 0, set Selling to Cost + 30% margin
    $stmt1 = $pdo->exec("UPDATE medicines SET SellingPrice = CostPrice * 1.30 WHERE SellingPrice <= 0 AND CostPrice > 0");
    
    // 2. If both are 0, set some default realistic values so they are sellable
    $stmt2 = $pdo->exec("UPDATE medicines SET SellingPrice = 85.00, CostPrice = 60.00 WHERE SellingPrice <= 0 AND CostPrice <= 0");
    
    // 3. Make sure no item is left as 0 selling price
    $stmt3 = $pdo->exec("UPDATE medicines SET SellingPrice = 50.00 WHERE SellingPrice <= 0");

    echo "Successfully updated prices!\n";
    echo "Items fixed with margin: " . $stmt1 . "\n";
    echo "Items fixed with default values: " . $stmt2 . "\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
