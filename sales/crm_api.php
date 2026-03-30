<?php
// sales/crm_api.php
header('Content-Type: application/json');
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Ensure the tables are upgraded for the new CRM features
try {
    // Upgrade customers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        CustomerID INT AUTO_INCREMENT PRIMARY KEY,
        CreatedBy INT,
        Name VARCHAR(100) NOT NULL,
        Phone VARCHAR(20),
        Address TEXT,
        DoctorName VARCHAR(100),
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (CreatedBy) REFERENCES users(UserID)
    )");
    
    // Add columns if they don't exist (Address, DoctorName) in existing customers table
    $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'Address'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN Address TEXT AFTER Phone");
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'DoctorName'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN DoctorName VARCHAR(100) AFTER Address");
    }

    // Upgrade sales table for CRM integration
    $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'CustomerID'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN CustomerID INT NULL");
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'DoctorName'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE sales ADD COLUMN DoctorName VARCHAR(100) NULL");
    }
} catch(PDOException $e) {
    // Ignore if exists
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data && isset($_GET['action'])) {
    $data = $_GET;
}

if (!$data || !isset($data['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request data.']);
    exit;
}

// 1. Search Customers (Dropdown Autocomplete)
if ($data['action'] === 'search_customers') {
    $query = $data['query'] ?? '';
    
    // Search by Name or Phone
    $stmt = $pdo->prepare("SELECT CustomerID, Name, Phone, Address, DoctorName FROM customers WHERE (Name LIKE ? OR Phone LIKE ?) AND CreatedBy = ? ORDER BY Name LIMIT 10");
    $stmt->execute(["%$query%", "%$query%", $_SESSION['user_id']]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $customers]);
    exit;
}

// 2. Quick Add Customer (from modal)
if ($data['action'] === 'quick_add_customer') {
    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $doctor = trim($data['doctor'] ?? '');

    if (empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'Patient Name is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO customers (CreatedBy, Name, Phone, Address, DoctorName) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $name, $phone, $address, $doctor]);
        
        $new_id = $pdo->lastInsertId();
        
        echo json_encode([
            'status' => 'success', 
            'customer' => [
                'CustomerID' => $new_id,
                'Name' => $name,
                'Phone' => $phone,
                'Address' => $address,
                'DoctorName' => $doctor
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// 3. Fetch Patient Prescription History
if ($data['action'] === 'get_patient_history') {
    $customer_id = intval($data['customer_id'] ?? 0);
    $limit = intval($data['limit'] ?? 5);
    
    if ($customer_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Customer ID.']);
        exit;
    }

    try {
        // Fetch last N sales for this customer
        $stmt = $pdo->prepare("
            SELECT SaleID, SaleDate, FinalAmount, DoctorName
            FROM sales 
            WHERE CustomerID = ? AND CreatedBy = ?
            ORDER BY SaleDate DESC LIMIT ?
        ");
        $stmt->execute([$customer_id, $_SESSION['user_id'], $limit]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch the items for each sale
        foreach($history as &$sale) {
            $item_stmt = $pdo->prepare("SELECT ItemID, ItemName, BatchNumber, Quantity, UnitPrice FROM sales_items WHERE SaleID = ?");
            $item_stmt->execute([$sale['SaleID']]);
            $sale['items'] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['status' => 'success', 'data' => $history]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
