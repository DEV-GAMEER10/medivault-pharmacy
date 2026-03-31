<?php
// sales/finance_api.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Ensure the transactions table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        TransactionID INT AUTO_INCREMENT PRIMARY KEY,
        TransactionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
        Type ENUM('EARNING', 'EXPENSE') NOT NULL,
        Category VARCHAR(50) NOT NULL,
        Description TEXT,
        Amount DECIMAL(10,2) NOT NULL,
        CreatedBy INT,
        CreatedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (CreatedBy) REFERENCES users(UserID)
    )");
} catch(PDOException $e) {
    // Ignore if exists
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data.']);
    exit;
}

if ($data['action'] === 'add_transaction') {
    $type = strtoupper($data['type'] ?? '');
    $category = $data['category'] ?? '';
    $description = $data['description'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $date = $data['date'] ?? date('Y-m-d');
    
    if (!in_array($type, ['EARNING', 'EXPENSE']) || empty($category) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide valid valid Type, Category, and Amount.']);
        exit;
    }

    try {
        // Append current time to the user's selected date
        $txDate = $date . ' ' . date('H:i:s');
        
        $stmt = $pdo->prepare("INSERT INTO transactions (Type, Category, Description, Amount, TransactionDate, CreatedBy) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $category, $description, $amount, $txDate, $_SESSION['user_id']]);
        
        echo json_encode(['status' => 'success', 'message' => ucfirst(strtolower($type)) . ' logged successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($data['action'] === 'edit_transaction') {
    $id = intval($data['id'] ?? 0);
    $type = strtoupper($data['type'] ?? '');
    $category = $data['category'] ?? '';
    $description = $data['description'] ?? '';
    $amount = floatval($data['amount'] ?? 0);
    $date = $data['date'] ?? date('Y-m-d');
    
    if ($id <= 0 || !in_array($type, ['EARNING', 'EXPENSE']) || empty($category) || $amount <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid update payload.']);
        exit;
    }

    try {
        // Only update time if date hasn't fundamentally changed, else keep time simple.
        $txDate = $date . ' ' . date('H:i:s');
        $stmt = $pdo->prepare("UPDATE transactions SET Type=?, Category=?, Description=?, Amount=?, TransactionDate=? WHERE TransactionID=? AND CreatedBy=?");
        $stmt->execute([$type, $category, $description, $amount, $txDate, $id, $_SESSION['user_id']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Transaction updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($data['action'] === 'delete_transaction') {
    $id = intval($data['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE TransactionID=? AND CreatedBy=?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Transaction deleted completely.']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
