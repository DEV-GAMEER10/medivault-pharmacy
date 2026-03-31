<?php
// sales/delete_sale.php
require_once __DIR__ . '/../config/database.php';
session_start();

// Check if user has permission (optional - add your auth check)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sale_id'])) {
    $sale_id = (int)$_POST['sale_id'];
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First, delete all sales_items for this sale
        $delete_items_stmt = $pdo->prepare("DELETE FROM sales_items WHERE SaleID = ?");
        $delete_items_stmt->execute([$sale_id]);
        
        // Then, delete the sale record
        $delete_sale_stmt = $pdo->prepare("DELETE FROM sales WHERE SaleID = ?");
        $delete_sale_stmt->execute([$sale_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = 'Sale deleted successfully!';
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        $response['success'] = false;
        $response['message'] = 'Error deleting sale: ' . $e->getMessage();
    }
    
    // Return JSON response for AJAX calls
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Redirect back with message for regular POST
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = $response['success'] ? 'success' : 'danger';
    header('Location: sales_history.php');
    exit;
}

// If accessed directly without POST, redirect
header('Location: sales_history.php');
exit;
?>
