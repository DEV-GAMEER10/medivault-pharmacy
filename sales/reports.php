<?php
//reports.php
require_once __DIR__ . '/../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

// Get current period (default to daily)
$period = $_GET['period'] ?? 'daily';
$custom_date = $_GET['date'] ?? date('Y-m-d');

// Helper function to get date range based on period
function getDateRange($period, $custom_date = null) {
    $date = $custom_date ?: date('Y-m-d');
    
    switch($period) {
        case 'daily':
            return [
                'start' => $date,
                'end' => $date,
                'group_by' => 'HOUR(SaleDate)',
                'label' => 'Hour'
            ];
        case 'weekly':
            $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
            $end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
            return [
                'start' => $start,
                'end' => $end,
                'group_by' => 'DAYNAME(SaleDate)',
                'label' => 'Day'
            ];
        case 'monthly':
            return [
                'start' => date('Y-m-01', strtotime($date)),
                'end' => date('Y-m-t', strtotime($date)),
                'group_by' => 'DAY(SaleDate)',
                'label' => 'Day'
            ];
        case 'yearly':
            return [
                'start' => date('Y-01-01', strtotime($date)),
                'end' => date('Y-12-31', strtotime($date)),
                'group_by' => 'MONTH(SaleDate)',
                'label' => 'Month'
            ];
    }
}

$dateRange = getDateRange($period, $custom_date);

// Get summary statistics
$summary_query = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(FinalAmount), 0) as total_revenue,
        COALESCE(SUM(Discount), 0) as total_discount,
        COALESCE(SUM(Tax), 0) as total_tax,
        COALESCE(AVG(FinalAmount), 0) as avg_sale_amount
    FROM sales 
    WHERE DATE(SaleDate) BETWEEN ? AND ?
";
$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$dateRange['start'], $dateRange['end']]);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// ---------------------------------------------------------
// FINANCIAL LEDGER LOGIC
// ---------------------------------------------------------
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
} catch(PDOException $e) {}

$ledger_query = "
    SELECT 'EARNING' as Type, 'Sale' as Category, CONCAT('Sale #', SaleID, ' (', COALESCE(CustomerName, 'Walk-in'), ')') as Description, FinalAmount as Amount, SaleDate as TransactionDate, 'SALE' as Source, SaleID as SourceID 
    FROM sales 
    WHERE DATE(SaleDate) BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 'EXPENSE' as Type, 'Inventory Purchase' as Category, CONCAT('Restock from Supplier #', SupplierID) as Description, TotalAmount as Amount, PurchaseDate as TransactionDate, 'PURCHASE' as Source, PurchaseID as SourceID 
    FROM purchases 
    WHERE DATE(PurchaseDate) BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT Type, Category, Description, Amount, TransactionDate, 'MANUAL' as Source, TransactionID as SourceID 
    FROM transactions 
    WHERE DATE(TransactionDate) BETWEEN ? AND ?
    
    ORDER BY TransactionDate DESC
";
$ledger_stmt = $pdo->prepare($ledger_query);
$ledger_stmt->execute([
    $dateRange['start'], $dateRange['end'],
    $dateRange['start'], $dateRange['end'],
    $dateRange['start'], $dateRange['end']
]);
$ledger_transactions = $ledger_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_earnings = 0;
$total_expenses = 0;
foreach($ledger_transactions as $tx) {
    if ($tx['Type'] === 'EARNING') $total_earnings += floatval($tx['Amount']);
    if ($tx['Type'] === 'EXPENSE') $total_expenses += floatval($tx['Amount']);
}
$net_profit = $total_earnings - $total_expenses;
// ---------------------------------------------------------

// Get sales data for charts
$chart_query = "
    SELECT 
        {$dateRange['group_by']} as period_unit,
        COUNT(*) as sales_count,
        COALESCE(SUM(FinalAmount), 0) as revenue
    FROM sales 
    WHERE DATE(SaleDate) BETWEEN ? AND ?
    GROUP BY {$dateRange['group_by']}
    ORDER BY period_unit
";
$chart_stmt = $pdo->prepare($chart_query);
$chart_stmt->execute([$dateRange['start'], $dateRange['end']]);
$chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment method breakdown
$payment_query = "
    SELECT 
        PaymentMethod,
        COUNT(*) as transaction_count,
        COALESCE(SUM(FinalAmount), 0) as total_amount
    FROM sales 
    WHERE DATE(SaleDate) BETWEEN ? AND ?
    GROUP BY PaymentMethod
";
$payment_stmt = $pdo->prepare($payment_query);
$payment_stmt->execute([$dateRange['start'], $dateRange['end']]);
$payment_data = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$products_query = "
    SELECT 
        si.ItemName,
        SUM(si.Quantity) as total_quantity,
        COUNT(DISTINCT si.SaleID) as times_sold,
        COALESCE(SUM(si.TotalPrice), 0) as total_revenue
    FROM sales_items si
    JOIN sales s ON si.SaleID = s.SaleID
    WHERE DATE(s.SaleDate) BETWEEN ? AND ?
    GROUP BY si.ItemName
    ORDER BY total_quantity DESC
    LIMIT 10
";
$products_stmt = $pdo->prepare($products_query);
$products_stmt->execute([$dateRange['start'], $dateRange['end']]);
$top_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent high-value sales
$high_value_query = "
    SELECT 
        SaleID,
        SaleDate,
        CustomerName,
        FinalAmount,
        PaymentMethod
    FROM sales 
    WHERE DATE(SaleDate) BETWEEN ? AND ?
    ORDER BY FinalAmount DESC
    LIMIT 5
";
$high_value_stmt = $pdo->prepare($high_value_query);
$high_value_stmt->execute([$dateRange['start'], $dateRange['end']]);
$high_value_sales = $high_value_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$base_url = '../';
$extra_css = '
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --info-gradient: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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
        }

        .period-selector {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .period-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .period-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            background: white;
            color: #495057;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .period-btn:hover, .period-btn.active {
            background: var(--primary-gradient);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .revenue-card { background: var(--primary-gradient); }
        .sales-card { background: var(--primary-gradient); }
        .discount-card { background: var(--primary-gradient); }
        .avg-card { background: var(--primary-gradient); }
        .tax-card { background: var(--primary-gradient); }

        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .table-modern {
            margin: 0;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            font-weight: 600;
            color: #495057;
            padding: 15px;
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
            padding: 15px;
            vertical-align: middle;
        }
    </style>
';
include __DIR__ . '/../includes/header.php';
?>
    <div class="main-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="dashboard-title">
                        <i class="fas fa-chart-bar"></i> Sales Reports
                    </h1>
                    <p class="mb-0 opacity-90">Comprehensive sales analytics and insights</p>
                </div>
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-calendar-alt text-primary"></i> Report Period
                </h5>
                <input type="date" class="form-control" style="max-width: 200px;" value="<?php echo $custom_date; ?>" onchange="changeDate(this.value)">
            </div>
            <div class="period-buttons">
                <a href="?period=daily&date=<?php echo $custom_date; ?>" class="period-btn <?php echo $period === 'daily' ? 'active' : ''; ?>">
                    <i class="fas fa-sun"></i> Daily
                </a>
                <a href="?period=weekly&date=<?php echo $custom_date; ?>" class="period-btn <?php echo $period === 'weekly' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-week"></i> Weekly
                </a>
                <a href="?period=monthly&date=<?php echo $custom_date; ?>" class="period-btn <?php echo $period === 'monthly' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar"></i> Monthly
                </a>
                <a href="?period=yearly&date=<?php echo $custom_date; ?>" class="period-btn <?php echo $period === 'yearly' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Yearly
                </a>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card revenue-card" style="background: <?php echo $net_profit >= 0 ? 'var(--success-gradient)' : 'var(--warning-gradient)'; ?>;">
                <i class="fas fa-wallet stat-icon"></i>
                <div class="stat-value">₹<?php echo number_format($net_profit, 2); ?></div>
                <div class="stat-label">Net Profit (P&L)</div>
            </div>
            <div class="stat-card sales-card">
                <i class="fas fa-arrow-down stat-icon" style="color: #6ed3a6;"></i>
                <div class="stat-value">₹<?php echo number_format($total_earnings, 2); ?></div>
                <div class="stat-label">Total Earnings (Sales + Additions)</div>
            </div>
            <div class="stat-card discount-card">
                <i class="fas fa-arrow-up stat-icon text-warning"></i>
                <div class="stat-value">₹<?php echo number_format($total_expenses, 2); ?></div>
                <div class="stat-label">Total Expenses (Purchases + Operation)</div>
            </div>
            <div class="stat-card avg-card">
                <i class="fas fa-shopping-cart stat-icon"></i>
                <div class="stat-value"><?php echo number_format($summary['total_sales']); ?></div>
                <div class="stat-label">Sales Volume</div>
            </div>
            <div class="stat-card tax-card">
                <i class="fas fa-percent stat-icon"></i>
                <div class="stat-value">₹<?php echo number_format($summary['total_discount'] ?? 0, 2); ?></div>
                <div class="stat-label">Discounts Given</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <div class="chart-header">
                        <i class="fas fa-chart-area text-primary"></i>
                        Sales Trend - <?php echo ucfirst($period); ?>
                    </div>
                    <canvas id="salesChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <div class="chart-header">
                        <i class="fas fa-chart-pie text-success"></i>
                        Payment Methods
                    </div>
                    <canvas id="paymentChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Financial Ledger Row -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="table-container">
                    <div class="chart-header d-flex justify-content-between align-items-center mb-0 pb-3 border-bottom">
                        <div>
                            <i class="fas fa-book text-primary me-2"></i>
                            Financial Ledger (Income vs Expense)
                        </div>
                        <button class="btn btn-primary btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#transactionModal" onclick="openAddModal()">
                            <i class="fas fa-plus me-1"></i> Add Transaction
                        </button>
                    </div>
                    <div class="table-responsive mt-3" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-modern table-hover">
                            <thead class="sticky-top bg-white" style="z-index: 1;">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ledger_transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No financial records found for this period.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($ledger_transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d M Y, h:i A', strtotime($tx['TransactionDate'])); ?></td>
                                        <td>
                                            <?php if ($tx['Type'] === 'EARNING'): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="fas fa-arrow-down me-1"></i> EARNING</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="fas fa-arrow-up me-1"></i> EXPENSE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="fw-semibold text-secondary"><?php echo htmlspecialchars($tx['Category']); ?></span></td>
                                        <td class="text-muted"><?php echo htmlspecialchars($tx['Description']); ?></td>
                                        <td class="text-end fw-bold <?php echo $tx['Type'] === 'EARNING' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $tx['Type'] === 'EARNING' ? '+' : '-'; ?>₹<?php echo number_format($tx['Amount'], 2); ?>
                                        </td>
                                        <td class="text-end px-3">
                                            <?php if ($tx['Source'] === 'MANUAL'): ?>
                                                <button class="btn btn-sm btn-outline-primary rounded-circle" onclick="editTransaction(<?php echo $tx['SourceID']; ?>, '<?php echo htmlspecialchars(addslashes($tx['Type'])); ?>', '<?php echo htmlspecialchars(addslashes($tx['Category'])); ?>', '<?php echo htmlspecialchars(addslashes($tx['Description'])); ?>', <?php echo $tx['Amount']; ?>, '<?php echo date('Y-m-d', strtotime($tx['TransactionDate'])); ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                                                <button class="btn btn-sm btn-outline-danger rounded-circle ms-1" onclick="deleteTransaction(<?php echo $tx['SourceID']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Tables Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="table-container">
                    <div class="chart-header">
                        <i class="fas fa-pills text-warning"></i>
                        Top Selling Products
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Qty Sold</th>
                                    <th>Times Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No product sales found for this period</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['ItemName']); ?></strong></td>
                                    <td><span class="badge bg-primary"><?php echo $product['total_quantity']; ?></span></td>
                                    <td><?php echo $product['times_sold']; ?></td>
                                    <td class="text-success"><strong>₹<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="table-container">
                    <div class="chart-header">
                        <i class="fas fa-star text-info"></i>
                        High-Value Sales
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($high_value_sales)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No sales found</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($high_value_sales as $sale): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($sale['SaleID'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($sale['CustomerName'] ?: 'Walk-in'); ?></div>
                                        <small class="text-muted"><?php echo date('d M', strtotime($sale['SaleDate'])); ?></small>
                                    </td>
                                    <td class="text-success"><strong>₹<?php echo number_format($sale['FinalAmount'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
                <div class="modal-header bg-light border-0 p-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i> Log Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="txForm">
                        <input type="hidden" id="txId" value="">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Transaction Type</label>
                            <select class="form-select form-select-lg" id="txType" required>
                                <option value="EXPENSE">Expense (Money Out)</option>
                                <option value="EARNING">Earning (Money In)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" class="form-control" id="txCategory" placeholder="e.g. Rent, Salary, Bill, Refund" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control form-control-lg" id="txAmount" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Optional Description</label>
                            <textarea class="form-control" id="txDesc" rows="2" placeholder="Notes..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="date" class="form-control" id="txDate" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveTransaction()">Save Log</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('txForm').reset();
            document.getElementById('txId').value = '';
            document.querySelector('#transactionModal .modal-title').innerHTML = '<i class="fas fa-plus-circle text-primary me-2"></i> Log Transaction';
        }

        function editTransaction(id, type, category, desc, amount, date) {
            document.getElementById('txId').value = id;
            document.getElementById('txType').value = type;
            document.getElementById('txCategory').value = category;
            document.getElementById('txDesc').value = desc;
            document.getElementById('txAmount').value = amount;
            document.getElementById('txDate').value = date;
            
            document.querySelector('#transactionModal .modal-title').innerHTML = '<i class="fas fa-pen text-primary me-2"></i> Edit Transaction';
            new bootstrap.Modal(document.getElementById('transactionModal')).show();
        }

        async function saveTransaction() {
            const data = {
                action: document.getElementById('txId').value ? 'edit_transaction' : 'add_transaction',
                id: document.getElementById('txId').value,
                type: document.getElementById('txType').value,
                category: document.getElementById('txCategory').value.trim(),
                amount: parseFloat(document.getElementById('txAmount').value),
                description: document.getElementById('txDesc').value.trim(),
                date: document.getElementById('txDate').value
            };
            
            if (!data.category || isNaN(data.amount) || data.amount <= 0) {
                alert("Please enter a valid Category and Amount.");
                return;
            }

            try {
                const res = await fetch('finance_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                const response = await res.json();
                if (response.status === 'success') {
                    window.location.reload();
                } else {
                    alert(response.message || 'Failed to save transaction.');
                }
            } catch (e) {
                alert('Connection error.');
            }
        }

        async function deleteTransaction(id) {
            if(!confirm("Are you sure you want to permanently delete this transaction? This will affect your Net Profit.")) return;
            try {
                const res = await fetch('finance_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'delete_transaction', id: id})
                });
                const response = await res.json();
                if(response.status === 'success') window.location.reload();
                else alert(response.message || 'Failed to delete transaction.');
            } catch(e) {
                alert('Connection error.');
            }
        }

        // Sales Trend Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.period_unit),
                datasets: [{
                    label: 'Revenue (₹)',
                    data: chartData.map(item => item.revenue),
                    borderColor: 'rgba(102, 126, 234, 1)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Sales Count',
                    data: chartData.map(item => item.sales_count),
                    borderColor: 'rgba(17, 153, 142, 1)',
                    backgroundColor: 'rgba(17, 153, 142, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₹)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Sales Count'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });

        // Payment Methods Pie Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentData = <?php echo json_encode($payment_data); ?>;
        
        new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: paymentData.map(item => item.PaymentMethod),
                datasets: [{
                    data: paymentData.map(item => item.total_amount),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(17, 153, 142, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(255, 206, 84, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        function changeDate(date) {
            const currentPeriod = '<?php echo $period; ?>';
            window.location.href = `?period=${currentPeriod}&date=${date}`;
        }
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
