<?php
//index.php
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

// Get today's sales summary
$today_sales_query = "SELECT COUNT(*) as total_sales, COALESCE(SUM(FinalAmount), 0) as total_revenue FROM sales WHERE DATE(SaleDate) = CURDATE()";
$today_stats = $pdo->query($today_sales_query)->fetch(PDO::FETCH_ASSOC);

// Get recent sales (Refactored to avoid using VIEWs for InfinityFree)
$recent_sales_query = "
    SELECT 
        s.*,
        COUNT(si.ItemID) as TotalItems,
        COALESCE(SUM(si.Quantity), 0) as TotalQuantity
    FROM sales s
    LEFT JOIN sales_items si ON s.SaleID = si.SaleID
    GROUP BY s.SaleID
    ORDER BY s.SaleDate DESC
    LIMIT 10
";
$recent_sales = $pdo->query($recent_sales_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$base_url = '../';
$extra_css = '
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --info-gradient: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #4a6741 100%);
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 0px;
            padding: 30px;
        }

        .dashboard-header {
            background: var(--primary-gradient);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .dashboard-title {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            border-radius: 20px;
            padding: 25px;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.95rem;
            opacity: 0.9;
            font-weight: 500;
        }

        .sales-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .revenue-card {
            background: var(--success-gradient);
        }

        .actions-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .action-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 15px 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-right: 15px;
            margin-bottom: 10px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .action-btn.info {
            background: var(--info-gradient);
        }

        .action-btn.info:hover {
            box-shadow: 0 10px 25px rgba(33, 147, 176, 0.4);
        }

        .recent-sales-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            overflow: hidden;
        }

        .card-header-modern {
            background: var(--primary-gradient);
            color: white;
            padding: 20px 25px;
            border: none;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .table-modern {
            margin: 0;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            font-weight: 600;
            color: #495057;
            padding: 20px 15px;
        }

        .table-modern tbody tr {
            border: none;
            transition: all 0.2s ease;
        }

        .table-modern tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .table-modern tbody td {
            border: none;
            padding: 20px 15px;
            vertical-align: middle;
        }

        .sale-id {
            font-weight: 700;
            color: var(--bs-primary);
            font-size: 1.1rem;
        }

        .badge-modern {
            padding: 8px 15px;
            border-radius: 10px;
            font-weight: 500;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
        }
    </style>
';
include '../includes/header.php';
?>
    <div class="main-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                Sales Dashboard
            </h1>
            <p class="mb-0 opacity-90">Monitor and manage your pharmacy sales performance</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card sales-card">
                <i class="fas fa-shopping-cart stat-icon"></i>
                <div class="stat-value"><?php echo number_format($today_stats['total_sales']); ?></div>
                <div class="stat-label">Today's Sales</div>
            </div>
            <div class="stat-card revenue-card">
                <i class="fas fa-rupee-sign stat-icon"></i>
                <div class="stat-value">₹<?php echo number_format($today_stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-section">
            <h5 class="mb-3 fw-bold text-dark">
                <i class="fas fa-bolt text-warning"></i> Quick Actions
            </h5>
            <a href="new_sale.php" class="action-btn">
                <i class="fas fa-plus"></i> Create Sale
            </a>
            <a href="sales_history.php" class="action-btn info">
                <i class="fas fa-history"></i> View Sales History
            </a>
        </div>

        <!-- Recent Sales -->
        <div class="recent-sales-card">
            <div class="card-header-modern">
                <i class="fas fa-clock"></i>
                <h5 class="mb-0">Recent Sales</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_sales)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox empty-state-icon"></i>
                                    <h5>No recent sales found</h5>
                                    <p class="text-muted">Sales will appear here once transactions are made.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td>
                                <span class="sale-id">#<?php echo str_pad($sale['SaleID'], 6, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo date('d M Y', strtotime($sale['SaleDate'])); ?></div>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($sale['SaleDate'])); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($sale['CustomerName'] ?: 'Walk-in Customer'); ?></div>
                                <?php if ($sale['CustomerPhone']): ?>
                                    <small class="text-muted"><?php echo $sale['CustomerPhone']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-modern bg-secondary"><?php echo $sale['TotalItems']; ?> items</span>
                                <br><small class="text-muted">Qty: <?php echo $sale['TotalQuantity']; ?></small>
                            </td>
                            <td>
                                <div class="fw-bold text-success">₹<?php echo number_format($sale['FinalAmount'], 2); ?></div>
                                <?php if ($sale['Discount'] > 0): ?>
                                    <small class="text-success">Discount: ₹<?php echo number_format($sale['Discount'], 2); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-modern bg-<?php echo $sale['PaymentMethod'] == 'CASH' ? 'success' : 'primary'; ?>">
                                    <?php echo $sale['PaymentMethod']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="invoice.php?id=<?php echo $sale['SaleID']; ?>" class="btn btn-outline-primary btn-modern btn-sm">
                                    <i class="fas fa-receipt"></i> Invoice
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
<?php include '../includes/footer.php'; ?>