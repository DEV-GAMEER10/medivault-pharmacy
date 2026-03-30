<?php
// includes/subscription_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    return;
}

require_once __DIR__ . '/../config/database.php';

// Initialize session start time for physical 30-min trial restriction
if (!isset($_SESSION['trial_start'])) {
    $_SESSION['trial_start'] = time();
}

$current_time = time();

try {
    // Determine if user has a paid active subscription
    // We wrap this in try-catch because on some hosting environments, the table might not exist yet
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND expiry_time > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        // Using Free Trial bounds
        if ($current_time > ($_SESSION['trial_start'] + 1800)) {
            // Force redirect to public pricing (do NOT destroy session, we need user_id for payment)
            // Use relative path or base_url if available
            $redirect_host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            header('Location: ' . $redirect_host . '/index.php?msg=trial_expired#pricing');
            exit;
        }
        
        $_SESSION['plan_name'] = 'Free Trial';
        $_SESSION['expiry_time'] = date('Y-m-d H:i:s', $_SESSION['trial_start'] + 1800);
        // Generic loose trial constraints as fallback
        $_SESSION['limits'] = [
            'medicine' => 50,
            'staff' => 1,
            'sales' => 50
        ];
    } else {
        // Paid Subscription Found
        $_SESSION['plan_name'] = $sub['plan_name'];
        $_SESSION['expiry_time'] = $sub['expiry_time'];
        $_SESSION['limits'] = [
            'medicine' => $sub['medicine_limit'],
            'staff' => $sub['staff_limit'],
            'sales' => $sub['daily_sales_limit']
        ];
    }
} catch (Exception $e) {
    // Fallback to Free Trial if table is missing or query fails
    $_SESSION['plan_name'] = 'Free Trial (Basic)';
    $_SESSION['expiry_time'] = date('Y-m-d H:i:s', $current_time + 1800);
    $_SESSION['limits'] = [
        'medicine' => 50,
        'staff' => 1,
        'sales' => 50
    ];
}
?>
