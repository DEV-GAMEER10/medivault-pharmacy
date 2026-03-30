<?php
// includes/sidebar.php
$current_page = basename($_SERVER['SCRIPT_NAME']);
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
if ($current_dir === 'htdocs' || $current_dir === 'my_store') {
    $current_dir = ''; // Root directory of the app
}
?>
<div class="sidebar">
  <a href="<?php echo $base_url; ?>index.php" style="text-decoration: none; color: inherit; display: block;">
    <h4>
      <img src="<?php echo $base_url; ?>med.jpg" alt="Medi-Vault Logo" style="width:150px; height:120px; vertical-align:middle; border-radius:10px; margin-bottom:10px;">
      <div id="medivault-title">MEDIVAULT</div>
    </h4>
  </a>
  <a href="<?php echo $base_url; ?>dashboard.php" class="<?php echo ($current_page == 'dashboard.php' || $current_page == 'welcome.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-house"></i> Dashboard
  </a>
  <a href="<?php echo $base_url; ?>inventory/index.php" class="<?php echo ($current_dir == 'inventory') ? 'active' : ''; ?>">
    <i class="fa-solid fa-capsules"></i> Inventory
  </a>
  <a href="<?php echo $base_url; ?>suppliers/index.php" class="<?php echo ($current_dir == 'suppliers') ? 'active' : ''; ?>">
    <i class="fa-solid fa-truck-field"></i> Suppliers
  </a>
  <a href="<?php echo $base_url; ?>purchases/new_purchase.php" class="<?php echo ($current_page == 'new_purchase.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-cart-arrow-down"></i> Purchase Entry
  </a>
  <a href="<?php echo $base_url; ?>purchases/index.php" class="<?php echo ($current_dir == 'purchases' && $current_page == 'index.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-clipboard-list"></i> Purchase History
  </a>
  <a href="<?php echo $base_url; ?>sales/index.php" class="<?php echo ($current_dir == 'sales' && $current_page == 'index.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-sack-dollar"></i> Sales Module
  </a>
  <a href="<?php echo $base_url; ?>sales/reports.php" class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-chart-line"></i> Reports
  </a>
  <a href="<?php echo $base_url; ?>backup.php" class="<?php echo ($current_page == 'backup.php') ? 'active' : ''; ?>" title="Download a secure copy of your entire cloud database">
    <i class="fa-solid fa-cloud-arrow-down"></i> Cloud Backup
  </a>
  <a href="<?php echo $base_url; ?>users/index.php" class="<?php echo ($current_dir == 'users' && $current_page != 'login.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-user-gear"></i> Users
  </a>
</div>
