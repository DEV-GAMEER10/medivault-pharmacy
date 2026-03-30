<?php
//search_products.php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_POST['query']) {
    $query = '%' . $_POST['query'] . '%';
    
    $stmt = $pdo->prepare("
        SELECT 
            ItemID, 
            SKU,
            ItemName, 
            Category, 
            TypeForm,
            BatchNumber, 
            Quantity, 
            CostPrice,
            SellingPrice,
            ExpiryDate,
            SupplierName
        FROM medicines 
        WHERE (ItemName LIKE ? OR SKU LIKE ? OR Category LIKE ? OR BatchNumber LIKE ? OR SupplierName LIKE ?) 
        AND Quantity > 0 
        AND (ExpiryDate IS NULL OR ExpiryDate > CURDATE() OR ExpiryDate = '0000-00-00')
        ORDER BY ItemName ASC 
        LIMIT 10
    ");
    
    $stmt->execute([$query, $query, $query, $query, $query]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} else {
    echo json_encode([]);
}
?>