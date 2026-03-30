<?php
//sales_history.php with Delete Functionality
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

// Display session messages if any
$session_message = '';
$session_type = '';
if (isset($_SESSION['message'])) {
    $session_message = $_SESSION['message'];
    $session_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$customer = $_GET['customer'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($date_from) {
    $where_conditions[] = "DATE(s.SaleDate) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = "DATE(s.SaleDate) <= ?";
    $params[] = $date_to;
}
if ($customer) {
    $where_conditions[] = "(s.CustomerName LIKE ? OR s.CustomerPhone LIKE ?)";
    $params[] = "%$customer%";
    $params[] = "%$customer%";
}
if ($payment_method) {
    $where_conditions[] = "s.PaymentMethod = ?";
    $params[] = $payment_method;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM sales s $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get sales data
$sales_query = "
    SELECT 
        s.*,
        COUNT(si.SaleItemID) as TotalItems,
        SUM(si.Quantity) as TotalQuantity
    FROM sales s
    LEFT JOIN sales_items si ON s.SaleID = si.SaleID
    $where_clause
    GROUP BY s.SaleID
    ORDER BY s.SaleDate DESC
    LIMIT $limit OFFSET $offset
";
$sales_stmt = $pdo->prepare($sales_query);
$sales_stmt->execute($params);
$sales = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_sales,
        COALESCE(SUM(FinalAmount), 0) as total_revenue,
        COALESCE(AVG(FinalAmount), 0) as avg_sale
    FROM sales s $where_clause
";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php
$base_url = '../';
$extra_css = '
    <style>
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .filter-card { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; }
        .table-hover tbody tr:hover { background-color: rgba(102, 126, 234, 0.1); }
    </style>
';
include '../includes/header.php';
?>
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h1 class="h3 text-primary"><i class="fas fa-history"></i> Sales History</h1>
                <div>
                    <a href="new_sale.php" class="btn btn-success me-2">
                        <i class="fas fa-plus"></i> New Sale
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Session Messages -->
        <?php if ($session_message): ?>
        <div class="alert alert-<?php echo $session_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($session_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['total_sales']); ?></h4>
                        <p class="mb-0">Total Sales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                        <h4>₹<?php echo number_format($stats['total_revenue'], 2); ?></h4>
                        <p class="mb-0">Total Revenue</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <h4>₹<?php echo number_format($stats['avg_sale'], 2); ?></h4>
                        <p class="mb-0">Average Sale</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card filter-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Customer</label>
                                <input type="text" name="customer" class="form-control" placeholder="Name or Phone" value="<?php echo htmlspecialchars($customer); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="">All Methods</option>
                                    <option value="CASH" <?php echo $payment_method === 'CASH' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="CARD" <?php echo $payment_method === 'CARD' ? 'selected' : ''; ?>>Card</option>
                                    <option value="UPI" <?php echo $payment_method === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-light">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Sales Records</h5>
                        <span class="badge bg-light text-dark">
                            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> records
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sales)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No sales found</h5>
                            <p class="text-muted">Try adjusting your filters or add a new sale.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Date & Time</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary">#<?php echo str_pad($sale['SaleID'], 6, '0', STR_PAD_LEFT); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($sale['SaleDate'])); ?>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($sale['SaleDate'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($sale['CustomerName'] ?: 'Walk-in Customer'); ?>
                                            <?php if ($sale['CustomerPhone']): ?>
                                                <br><small class="text-muted"><?php echo $sale['CustomerPhone']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $sale['TotalItems']; ?> items</span>
                                            <br><small class="text-muted">Qty: <?php echo $sale['TotalQuantity']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $sale['PaymentMethod'] == 'CASH' ? 'success' : ($sale['PaymentMethod'] == 'CARD' ? 'primary' : 'info'); ?>">
                                                <?php echo $sale['PaymentMethod']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong>₹<?php echo number_format($sale['FinalAmount'], 2); ?></strong>
                                            <?php if ($sale['Discount'] > 0): ?>
                                                <br><small class="text-success">-₹<?php echo number_format($sale['Discount'], 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $sale['Status'] == 'COMPLETED' ? 'success' : 'warning'; ?>">
                                                <?php echo $sale['Status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="invoice.php?id=<?php echo $sale['SaleID']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="View Invoice">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                                <button type="button" 
                                                   class="btn btn-sm btn-outline-danger delete-sale-btn" 
                                                   data-sale-id="<?php echo $sale['SaleID']; ?>"
                                                   data-sale-number="#<?php echo str_pad($sale['SaleID'], 6, '0', STR_PAD_LEFT); ?>"
                                                   title="Delete Sale">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav aria-label="Sales pagination">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">Previous</a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">Next</a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete sale <strong id="saleNumberDisplay"></strong>?</p>
                    <p class="text-danger"><i class="fas fa-warning"></i> This action cannot be undone. All related sale items will also be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="delete_sale.php" id="deleteForm">
                        <input type="hidden" name="sale_id" id="saleIdInput">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Sale
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Delete sale confirmation
        document.querySelectorAll('.delete-sale-btn').forEach(button => {
            button.addEventListener('click', function() {
                const saleId = this.getAttribute('data-sale-id');
                const saleNumber = this.getAttribute('data-sale-number');
                
                document.getElementById('saleIdInput').value = saleId;
                document.getElementById('saleNumberDisplay').textContent = saleNumber;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                modal.show();
            });
        });
    </script>
<?php include '../includes/footer.php'; ?>