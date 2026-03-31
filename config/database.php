<?php
// Database configuration
// Database configuration
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false) {
    // Local XAMPP Settings
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pharmaceutical_inventory');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // InfinityFree / Production Settings
    // Replace these with the values from your InfinityFree Control Panel (MySQL Databases)
    define('DB_HOST', getenv('DB_HOST') ?: 'sql212.infinityfree.com');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_41443010_pharmacy');
    define('DB_USER', getenv('DB_USER') ?: 'if0_41443010');
    define('DB_PASS', getenv('DB_PASS') ?: 'YOUR_VPANEL_PASSWORD'); // ← Put your InfinityFree Password here!
}

try {
    // Port handling for Aiven/Docker
    $host_parts = explode(':', DB_HOST);
    $host = $host_parts[0];
    $port = isset($host_parts[1]) ? $host_parts[1] : '3306';

    // Create PDO connection
    $dsn = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5 // 5 seconds timeout
        ]
    );
    
    // Set timezone
    $pdo->exec("SET time_zone = '+05:30'");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Utility function for safe query execution
function executeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        throw new Exception("Database operation failed");
    }
}

// Function to get medicine item by ID
function getMedicineItem($pdo, $itemId) {
    $stmt = executeQuery($pdo, "SELECT * FROM medicines WHERE ItemID = ?", [$itemId]);
    return $stmt->fetch();
}

// Function to update medicine quantity
function updateMedicineQuantity($pdo, $itemId, $newQuantity) {
    return executeQuery($pdo, "UPDATE medicines SET Quantity = ? WHERE ItemID = ?", [$newQuantity, $itemId]);
}

// Function to get available stock for medicine
function getAvailableStock($pdo, $itemId) {
    $stmt = executeQuery($pdo, "SELECT Quantity FROM medicines WHERE ItemID = ? AND (ExpiryDate IS NULL OR ExpiryDate > CURDATE() OR ExpiryDate = '0000-00-00')", [$itemId]);
    $result = $stmt->fetch();
    return $result ? (int)$result['Quantity'] : 0;
}
?>