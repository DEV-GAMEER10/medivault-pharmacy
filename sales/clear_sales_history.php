<?php
// clear_sales_history.php
require_once __DIR__ . '/../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || $input['action'] !== 'clear_all_sales') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // First, get count of records to be deleted for logging
    $count_stmt = $pdo->query("SELECT COUNT(*) FROM sales");
    $total_sales = $count_stmt->fetchColumn();
    
    $count_items_stmt = $pdo->query("SELECT COUNT(*) FROM sales_items");
    $total_items = $count_items_stmt->fetchColumn();
    
    // Delete all sales items first (due to foreign key constraints)
    $delete_items = $pdo->prepare("DELETE FROM sales_items");
    $delete_items->execute();
    
    // Delete all sales records
    $delete_sales = $pdo->prepare("DELETE FROM sales");
    $delete_sales->execute();
    
    // Reset auto increment (optional - removes gaps in IDs)
    $pdo->exec("ALTER TABLE sales AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE sales_items AUTO_INCREMENT = 1");
    
    $pdo->commit();
    
    // Log this action
    $log_message = "Sales history cleared: {$total_sales} sales records and {$total_items} items deleted on " . date('Y-m-d H:i:s');
    
    // Optional: Write to a log file (create logs directory if needed)
    if (!is_dir('../logs')) {
        mkdir('../logs', 0755, true);
    }
    error_log($log_message, 3, '../logs/sales_clear.log');
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully cleared {$total_sales} sales records and {$total_items} items",
        'deleted_sales' => $total_sales,
        'deleted_items' => $total_items
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    
    // Log the error
    if (!is_dir('../logs')) {
        mkdir('../logs', 0755, true);
    }
    error_log("Error clearing sales history: " . $e->getMessage(), 3, '../logs/error.log');
    
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
