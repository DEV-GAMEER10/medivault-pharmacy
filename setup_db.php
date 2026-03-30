<?php
// setup_db.php
require_once __DIR__ . '/config/database.php';

try {
    $pdo->beginTransaction();

    // 1. Roles Update
    $pdo->exec("UPDATE users SET Role = 'STAFF' WHERE Role NOT IN ('ADMIN')");
    $pdo->exec("ALTER TABLE users MODIFY Role ENUM('ADMIN', 'STAFF') DEFAULT 'STAFF'");
    
    // 2. Suppliers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        SupplierID INT AUTO_INCREMENT PRIMARY KEY,
        Name VARCHAR(255) NOT NULL,
        Phone VARCHAR(50),
        Email VARCHAR(100),
        Address TEXT,
        CreatedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 3. Purchases Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        PurchaseID INT AUTO_INCREMENT PRIMARY KEY,
        SupplierID INT NOT NULL,
        PurchaseDate DATETIME DEFAULT CURRENT_TIMESTAMP,
        TotalAmount DECIMAL(10,2) NOT NULL,
        CreatedBy INT,
        FOREIGN KEY (SupplierID) REFERENCES suppliers(SupplierID)
    )");
    
    // 4. Purchase Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_items (
        ItemID INT AUTO_INCREMENT PRIMARY KEY,
        PurchaseID INT NOT NULL,
        MedicineName VARCHAR(255) NOT NULL,
        BatchNumber VARCHAR(100) NOT NULL,
        Quantity INT NOT NULL,
        PurchasePrice DECIMAL(10,2) NOT NULL,
        ExpiryDate DATE,
        FOREIGN KEY (PurchaseID) REFERENCES purchases(PurchaseID)
    )");
    
    // 5. Medicines Structural Link
    try {
        $pdo->exec("ALTER TABLE medicines ADD SupplierID INT NULL");
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE medicines ADD CONSTRAINT fk_medicine_supplier FOREIGN KEY (SupplierID) REFERENCES suppliers(SupplierID) ON DELETE SET NULL");
    } catch (Exception $e) {}

    // 6. Profit Tracking (Sales)
    try {
        $pdo->exec("ALTER TABLE sale_items ADD CostPrice DECIMAL(10,2) DEFAULT 0");
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE sales ADD TotalCost DECIMAL(10,2) DEFAULT 0");
    } catch (Exception $e) {}
    
    try {
        $pdo->exec("ALTER TABLE sales ADD TotalProfit DECIMAL(10,2) DEFAULT 0");
    } catch (Exception $e) {}

    $pdo->commit();
    echo "Successfully updated database schema.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error updating database schema: " . $e->getMessage() . "\n";
}
