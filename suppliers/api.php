<?php
// suppliers/api.php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN');
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

header('Content-Type: application/json');

if ($action === 'get_suppliers') {
    try {
        $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY Name ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (!$isAdmin) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied. Admins only.']);
    exit;
}

if ($action === 'add_supplier') {
    try {
        $name = $_POST['name'];
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO suppliers (Name, Phone, Email, Address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $address]);
        
        echo json_encode(['status' => 'success', 'message' => 'Supplier added successfully']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'update_supplier') {
    try {
        $id = $_POST['supplier_id'];
        $name = $_POST['name'];
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $address = $_POST['address'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE suppliers SET Name=?, Phone=?, Email=?, Address=? WHERE SupplierID=?");
        $stmt->execute([$name, $phone, $email, $address, $id]);
        
        echo json_encode(['status' => 'success', 'message' => 'Supplier updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_supplier') {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE SupplierID=?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Supplier deleted successfully']);
    } catch (Exception $e) {
        // Handle constraint failure
        if ($e->getCode() == 23000) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete supplier because they have existing purchase records.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
