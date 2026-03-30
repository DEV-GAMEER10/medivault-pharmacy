<?php
// subscription/api.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';

// Get JSON POST payload
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if(!$input && !empty($_POST)) $input = $_POST;
$action = $input['action'] ?? '';

if ($action === 'verify_payment') {
    $payment_id = $input['razorpay_payment_id'] ?? '';
    $planName = $input['plan'] ?? '';
    $amount = $input['amount'] ?? 0;
    $duration = $input['duration'] ?? 'Monthly';
    
    // In a real production scenario, you would use Razorpay's API with secret key to cryptographically verify the signature here.
    // For this module, we simulate the success callback recording the subscription.

    try {
        // Retrieve plan explicit limits
        $medicine_limit = 500;
        $staff_limit = 2;
        $daily_sales_limit = 50;

        if ($planName === 'Professional') {
            $medicine_limit = 999999;
            $staff_limit = 10;
            $daily_sales_limit = 500;
        } elseif ($planName === 'Enterprise') {
            $medicine_limit = 999999;
            $staff_limit = 999999;
            $daily_sales_limit = 999999;
        }

        $user_id = $_SESSION['user_id'];
        
        // Calculate EndDate
        if ($duration === 'Annual') {
            $endDate = date('Y-m-d H:i:s', strtotime('+1 year'));
        } else {
            $endDate = date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_name, expiry_time, medicine_limit, staff_limit, daily_sales_limit) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $planName, $endDate, $medicine_limit, $staff_limit, $daily_sales_limit]);

        echo json_encode(['status' => 'success', 'message' => 'Subscription activated. Valid until ' . date('d M Y', strtotime($endDate))]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
