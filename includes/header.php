<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL helps resolve paths correctly depending on directory depth
if (!isset($base_url)) {
    $base_url = ''; 
}

// Note: User-agent check removed — the session check below enforces authentication instead.

require_once __DIR__ . '/../includes/subscription_check.php';

// Ensure PDO is available
require_once __DIR__ . '/../config/database.php';

$expiringCount = 0;
$lowStockCount = 0;
$notifications = [];

if (isset($pdo)) {
    // Expiring soon (within 30 days) or already expired
    $stmtExp = $pdo->query("SELECT ItemName, ExpiryDate, DATEDIFF(ExpiryDate, CURDATE()) as days_left FROM medicines WHERE ExpiryDate IS NOT NULL AND DATEDIFF(ExpiryDate, CURDATE()) <= 30 ORDER BY days_left ASC LIMIT 5");
    $exp_items = $stmtExp->fetchAll(PDO::FETCH_ASSOC);
    
    // Low stock
    $stmtLow = $pdo->query("SELECT ItemName, Quantity FROM medicines WHERE Quantity <= 20 ORDER BY Quantity ASC LIMIT 5");
    $low_items = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtCounts = $pdo->query("SELECT 
        SUM(CASE WHEN ExpiryDate IS NOT NULL AND DATEDIFF(ExpiryDate, CURDATE()) <= 30 THEN 1 ELSE 0 END) as exp_count,
        SUM(CASE WHEN Quantity <= 20 THEN 1 ELSE 0 END) as low_count
        FROM medicines");
    $counts = $stmtCounts->fetch(PDO::FETCH_ASSOC);
    $expiringCount = $counts['exp_count'] ?? 0;
    $lowStockCount = $counts['low_count'] ?? 0;

    foreach ($exp_items as $item) {
        $days = $item['days_left'];
        $text = $days < 0 ? "Expired " . abs($days) . " days ago" : ($days == 0 ? "Expires today" : "Expires in $days days");
        $typeClass = $days < 0 ? 'text-danger' : 'text-warning';
        $notifications[] = [
            'icon' => 'fa-exclamation-triangle',
            'icon_class' => $typeClass,
            'title' => htmlspecialchars($item['ItemName']),
            'text' => $text,
            'link' => $base_url . 'inventory/index.php?filter_name=' . urlencode($item['ItemName'])
        ];
    }
    
    foreach ($low_items as $item) {
        $notifications[] = [
            'icon' => 'fa-box-open',
            'icon_class' => 'text-info',
            'title' => htmlspecialchars($item['ItemName']),
            'text' => "Low stock: " . $item['Quantity'] . " remaining",
            'link' => $base_url . 'inventory/index.php?filter_name=' . urlencode($item['ItemName'])
        ];
    }
}
$notifCount = $expiringCount + $lowStockCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pharmacy Management System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background-color: #f5f6f8;
      margin: 0;
      color: #333;
    }
    
    /* Layout */
    .app-wrapper {
      display: flex;
      min-height: 100vh;
    }
    
    /* Sidebar */
    .sidebar {
      width: 240px;
      min-width: 240px;
      background: #1f2937;
      color: #e5e7eb;
      display: flex;
      flex-direction: column;
      padding: 20px 15px;
      box-shadow: 2px 0 6px rgba(0,0,0,0.1);
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }
    .sidebar h4 { text-align: center; margin-bottom: 30px; font-weight: 600; color: #60a5fa; }
    .sidebar img { display: block; margin: 0 auto; }
    #medivault-title { margin-top: 10px; font-size: 20px; font-weight: 700; letter-spacing: 2px; color: #60a5fa; }
    .sidebar a {
      display: flex; align-items: center; padding: 12px 15px; margin: 6px 0;
      text-decoration: none; color: #e5e7eb; border-radius: 8px; transition: all 0.3s ease; font-size: 15px;
    }
    .sidebar a i { margin-right: 12px; font-size: 18px; color: #9ca3af; }
    .sidebar a:hover { background-color: #374151; transform: translateX(5px); color: #ffffff; text-decoration: none; }
    .sidebar a.active { background-color: #2563eb; color: white; font-weight: 600; }
    .sidebar a.active i { color: white; }
    
    /* Content Area */
    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #f9fafb;
      min-width: 0;
    }
    
    /* Topbar */
    .topbar {
      height: 70px; background: white; display: flex; align-items: center;
      justify-content: space-between; padding: 0 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 1000;
    }
    .topbar-left { display: flex; align-items: center; gap: 20px; flex: 1; }
    .topbar-right { display: flex; align-items: center; gap: 20px; }
    
    .topbar h5 { margin: 0; font-weight: 600; color: #1f2937; display: none; }
    @media (min-width: 992px) { .topbar h5 { display: block; } }

    /* Search Bar */
    .global-search { max-width: 400px; width: 100%; position: relative; }
    .global-search input {
        border-radius: 20px; padding-left: 40px; border: 1px solid #e5e7eb;
        background-color: #f9fafb; transition: all 0.3s;
    }
    .global-search input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); background-color: white; outline: none; }
    .global-search i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; }

    /* Notifications */
    .notif-bell { position: relative; cursor: pointer; color: #4b5563; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; transition: background 0.2s; text-decoration: none;}
    .notif-bell:hover { background-color: #f3f4f6; color: #1f2937; }
    .notif-badge { 
        position: absolute; top: 2px; right: 2px; background: #ef4444; color: white; 
        font-size: 0.65rem; font-weight: bold; width: 18px; height: 18px; 
        display: flex; align-items: center; justify-content: center; border-radius: 50%; 
        border: 2px solid white;
    }
    .dropdown-menu-notif { width: 320px; padding: 0; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 12px; overflow: hidden; margin-top: 10px !important; }
    .notif-header { background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #1e293b; display: flex; justify-content: space-between; align-items: center; }
    .notif-body { max-height: 350px; overflow-y: auto; }
    .notif-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #f1f5f9; text-decoration: none; transition: background 0.2s; }
    .notif-item:hover { background: #f8fafc; text-decoration: none; }
    .notif-icon { flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
    .notif-content { flex: 1; min-width: 0; }
    .notif-title { color: #1e293b; font-size: 0.9rem; font-weight: 600; margin: 0 0 2px 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .notif-desc { color: #64748b; font-size: 0.8rem; margin: 0; }
    .notif-footer { padding: 12px; text-align: center; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .notif-footer a { color: #3b82f6; font-size: 0.85rem; font-weight: 500; text-decoration: none; }
    .notif-footer a:hover { text-decoration: underline; }

    /* Profile Info */
    .profile-info { font-size: 14px; color: #4b5563; display: flex; align-items: center; gap: 15px; border-left: 1px solid #e5e7eb; padding-left: 20px; }
    
    .content-body { padding: 30px; flex: 1; overflow-y: auto; }

    @media print {
        .sidebar, .topbar, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
    }
  </style>
  
  <?php if(isset($extra_css)) echo $extra_css; ?>
</head>
<body>
<div class="app-wrapper">
<?php include __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="topbar-left">
          <h5>MediVault</h5>
          
          <!-- Global Search -->
          <div class="global-search">
              <form action="<?php echo $base_url; ?>search.php" method="GET">
                  <i class="fas fa-search"></i>
                  <input type="text" name="q" class="form-control" placeholder="Search medicines, sales, customers..." required>
              </form>
          </div>
      </div>
      
      <div class="topbar-right">
          <!-- Live Trial Timer -->
          <?php if (isset($_SESSION['plan_name']) && $_SESSION['plan_name'] === 'Free Trial' && isset($_SESSION['trial_start'])): ?>
              <?php 
                 $seconds_remaining = 1800 - (time() - $_SESSION['trial_start']);
                 if ($seconds_remaining < 0) $seconds_remaining = 0;
              ?>
              <div class="d-flex align-items-center gap-2 me-3">
                  <div class="trial-timer d-flex align-items-center bg-warning text-dark px-3 py-1 rounded-pill fw-bold" style="font-size:0.9rem; border: 1px solid #d97706;">
                      <i class="fas fa-stopwatch me-2" style="color: #d97706;"></i>
                      <span>Trial: <span id="trialCountdown"><?= gmdate("i:s", $seconds_remaining) ?></span></span>
                  </div>
                  <a href="<?php echo $base_url; ?>index.php#pricing" class="btn btn-sm btn-dark rounded-pill fw-bold px-3 border-0" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6);">
                      <i class="fas fa-gem me-1 text-info"></i> Buy Now
                  </a>
              </div>
              <script>
                  let trialTimeLeft = <?= $seconds_remaining ?>;
                  const trialTimerEl = document.getElementById('trialCountdown');
                  if(trialTimerEl && trialTimeLeft > 0) {
                      const trialInterval = setInterval(() => {
                          trialTimeLeft--;
                          if(trialTimeLeft <= 0) {
                              clearInterval(trialInterval);
                              window.location.reload(); // Triggers the PHP middleware redirect
                          } else {
                              let m = Math.floor(trialTimeLeft / 60);
                              let s = trialTimeLeft % 60;
                              trialTimerEl.innerText = (m < 10 ? '0'+m : m) + ':' + (s < 10 ? '0'+s : s);
                          }
                      }, 1000);
                  }
              </script>
          <?php endif; ?>

          <!-- Notifications Dropdown -->
          <div class="dropdown">
              <a href="#" class="notif-bell" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fas fa-bell"></i>
                  <?php if ($notifCount > 0): ?>
                      <span class="notif-badge"><?php echo $notifCount > 99 ? '99+' : $notifCount; ?></span>
                  <?php endif; ?>
              </a>
              <div class="dropdown-menu dropdown-menu-end dropdown-menu-notif" aria-labelledby="notifDropdown">
                  <div class="notif-header">
                      <span>Notifications</span>
                      <?php if ($notifCount > 0): ?>
                          <span class="badge bg-primary rounded-pill"><?php echo $notifCount; ?> New</span>
                      <?php endif; ?>
                  </div>
                  <div class="notif-body">
                      <?php if (empty($notifications)): ?>
                          <div class="p-4 text-center text-muted">
                              <i class="fas fa-check-circle fa-2x mb-2 text-success" style="opacity: 0.5;"></i>
                              <p class="mb-0 small">You\'re all caught up!</p>
                          </div>
                      <?php else: ?>
                          <?php foreach ($notifications as $notif): ?>
                              <a href="<?php echo $notif['link']; ?>" class="notif-item">
                                  <div class="notif-icon">
                                      <i class="fas <?php echo $notif['icon']; ?> <?php echo $notif['icon_class']; ?>"></i>
                                  </div>
                                  <div class="notif-content">
                                      <p class="notif-title"><?php echo $notif['title']; ?></p>
                                      <p class="notif-desc"><?php echo $notif['text']; ?></p>
                                  </div>
                              </a>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </div>
                  <div class="notif-footer">
                      <a href="<?php echo $base_url; ?>inventory/index.php">View All Inventory Alerts</a>
                  </div>
              </div>
          </div>
          
          <div class="profile-info">
              <span><i class="fas fa-user-circle me-1"></i> <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></span>
              <a href="<?php echo $base_url; ?>users/login.php" class="text-danger text-decoration-none" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
          </div>
      </div>
    </div>
    <div class="content-body">
