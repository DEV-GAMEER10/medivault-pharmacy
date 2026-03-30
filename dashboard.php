<?php
// dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: users/login.php');
    exit;
}

$base_url = '';
$extra_css = '
<style>
    .hero-section {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
        color: white;
        padding: 50px 0;
        border-radius: 15px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    .hero-section::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1000 100\' fill=\'white\' opacity=\'0.1\'><polygon points=\'0,0 1000,0 1000,60 0,100\'/></svg>") no-repeat bottom;
        background-size: cover;
    }
    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
    }
    .hero-title {
        font-size: 3rem;
        font-weight: 800;
        margin-bottom: 1rem;
        text-shadow: 2px 4px 8px rgba(0,0,0,0.2);
    }
    .hero-subtitle {
        font-size: 1.2rem;
        font-weight: 400;
        opacity: 0.95;
    }
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .dash-card {
        background: white;
        padding: 30px;
        border-radius: 16px;
        border: 1px solid rgba(59, 130, 246, 0.1);
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        transition: transform 0.3s;
        display: flex;
        align-items: center;
        text-decoration: none;
        color: inherit;
    }
    .dash-card:hover {
        transform: translateY(-5px);
        color: inherit;
    }
    .dash-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(96, 165, 250, 0.1));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20px;
    }
    .dash-icon i {
        font-size: 1.8rem;
        color: #3b82f6;
    }
    .dash-info h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e40af;
        margin: 0 0 5px 0;
    }
    .dash-info p {
        margin: 0;
        color: #64748b;
        font-size: 0.9rem;
    }
</style>
';
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title"><i class="fas fa-pills me-3"></i>MediVault</h1>
            <p class="hero-subtitle">Smart Pharmacy Management System Dashboard</p>
        </div>
    </div>
    
    <div class="dashboard-cards">
        <a href="inventory/index.php" class="dash-card">
            <div class="dash-icon"><i class="fas fa-capsules"></i></div>
            <div class="dash-info">
                <h3>Inventory Management</h3>
                <p>Track medicines, manage stock, and monitor expiry dates.</p>
            </div>
        </a>
        <a href="sales/index.php" class="dash-card">
            <div class="dash-icon"><i class="fas fa-sack-dollar"></i></div>
            <div class="dash-info">
                <h3>Sales POS</h3>
                <p>Process new sales, generate invoices, and manage carts.</p>
            </div>
        </a>
        <a href="sales/reports.php" class="dash-card">
            <div class="dash-icon"><i class="fas fa-chart-line"></i></div>
            <div class="dash-info">
                <h3>Financial Reports</h3>
                <p>View daily, weekly, and monthly revenue analytics.</p>
            </div>
        </a>
        <a href="users/index.php" class="dash-card">
            <div class="dash-icon"><i class="fas fa-user-gear"></i></div>
            <div class="dash-info">
                <h3>User Management</h3>
                <p>Manage staff accounts, roles, and platform access.</p>
            </div>
        </a>
        <a href="welcome.php" class="dash-card">
            <div class="dash-icon"><i class="fas fa-info-circle"></i></div>
            <div class="dash-info">
                <h3>About MediVault</h3>
                <p>Learn more about our mission, vision, and team.</p>
            </div>
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
