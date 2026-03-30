<?php
// landing_api.php
header('Content-Type: application/json');
require_once 'config/database.php';
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if ($data['action'] === 'register_demo') {
    $hospitalName = trim($data['hospitalName'] ?? '');
    $email = trim($data['email'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    
    if (empty($hospitalName) || empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Name and Email are required.']);
        exit;
    }
    
    // Create a smooth username
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $hospitalName)) . rand(100,999);
    $hashed_password = password_hash('demo123', PASSWORD_DEFAULT); // Default password for demo
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (Username, Email, Password, FullName, Phone, Role) VALUES (?, ?, ?, ?, ?, 'ADMIN')");
        $stmt->execute([$username, $email, $hashed_password, $hospitalName, $mobile]);
        $userId = $pdo->lastInsertId();
        
        // Log the user in
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['full_name'] = $hospitalName;
        $_SESSION['role'] = 'ADMIN';
        $_SESSION['email'] = $email;
        
        // Start 30 min strict trial
        $_SESSION['trial_start'] = time();
        
        echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['status' => 'error', 'message' => 'An account with this email/name already exists. Try logging in.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
