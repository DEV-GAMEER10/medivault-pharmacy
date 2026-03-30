<?php
// search.php
require_once 'config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: users/login.php');
    exit;
}

$query = $_GET['q'] ?? '';
$medicines = [];
$sales = [];

if (trim($query) !== '') {
    $search_term = "%{$query}%";
    
    // Search Medicines
    $stmt_meds = $pdo->prepare("SELECT * FROM medicines WHERE ItemName LIKE ? OR Category LIKE ? OR BatchNumber LIKE ?");
    $stmt_meds->execute([$search_term, $search_term, $search_term]);
    $medicines = $stmt_meds->fetchAll(PDO::FETCH_ASSOC);
    
    // Search Sales
    if (is_numeric($query)) {
        $stmt_sales = $pdo->prepare("SELECT * FROM sales WHERE SaleID = ? OR CustomerPhone LIKE ?");
        $stmt_sales->execute([$query, $search_term]);
    } else {
        $stmt_sales = $pdo->prepare("SELECT * FROM sales WHERE CustomerName LIKE ? OR CustomerPhone LIKE ?");
        $stmt_sales->execute([$search_term, $search_term]);
    }
    $sales = $stmt_sales->fetchAll(PDO::FETCH_ASSOC);
}

$base_url = '';
$extra_css = '
<style>
    .result-card { transition: all 0.3s ease; border: 1px solid #e5e7eb; border-radius: 12px; }
    .result-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-color: #3b82f6; }
    .nav-tabs .nav-link { font-weight: 600; color: #64748b; padding: 12px 24px; }
    .nav-tabs .nav-link.active { color: #2563eb; border-bottom: 3px solid #2563eb; background: transparent; }
</style>
';
include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Global Search Results</h2>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 15px;">
        <form action="search.php" method="GET" class="d-flex">
            <input type="text" name="q" class="form-control form-control-lg me-3" placeholder="Search medicines, sales, customers..." value="<?php echo htmlspecialchars($query); ?>" required>
            <button type="submit" class="btn btn-primary btn-lg px-4"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>

    <?php if (trim($query) !== ''): ?>
        <p class="text-muted mb-4">Found <strong><?php echo count($medicines); ?></strong> medicines and <strong><?php echo count($sales); ?></strong> sales for "<?php echo htmlspecialchars($query); ?>"</p>

        <ul class="nav nav-tabs mb-4" id="searchTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="medicines-tab" data-bs-toggle="tab" data-bs-target="#medicines" type="button" role="tab" aria-controls="medicines" aria-selected="true">
                    <i class="fas fa-pills me-2"></i> Medicines (<?php echo count($medicines); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab" aria-controls="sales" aria-selected="false">
                    <i class="fas fa-receipt me-2"></i> Sales (<?php echo count($sales); ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="searchTabsContent">
            <!-- Medicines Tab -->
            <div class="tab-pane fade show active" id="medicines" role="tabpanel" aria-labelledby="medicines-tab">
                <?php if (empty($medicines)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
                        <h4 class="text-muted">No medicines found</h4>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($medicines as $med): ?>
                            <div class="col-md-4">
                                <div class="card result-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title text-primary mb-0"><?php echo htmlspecialchars($med['ItemName']); ?></h5>
                                            <span class="badge bg-<?php echo $med['Quantity'] <= 20 ? 'danger' : 'success'; ?> rounded-pill">
                                                Qty: <?php echo $med['Quantity']; ?>
                                            </span>
                                        </div>
                                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($med['Category']); ?> &bull; <?php echo htmlspecialchars($med['TypeForm']); ?></p>
                                        
                                        <div class="mb-3">
                                            <p class="mb-1"><small class="text-muted">Batch:</small> <strong><?php echo htmlspecialchars($med['BatchNumber']); ?></strong></p>
                                            <p class="mb-1"><small class="text-muted">Price:</small> <strong>₹<?php echo number_format($med['SellingPrice'], 2); ?></strong></p>
                                            <p class="mb-0"><small class="text-muted">Expiry:</small> <strong><?php echo htmlspecialchars($med['ExpiryDate'] ?? 'N/A'); ?></strong></p>
                                        </div>
                                        
                                        <a href="inventory/index.php?filter_name=<?php echo urlencode($med['ItemName']); ?>" class="btn btn-outline-primary btn-sm w-100">
                                            View in Inventory
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sales Tab -->
            <div class="tab-pane fade" id="sales" role="tabpanel" aria-labelledby="sales-tab">
                <?php if (empty($sales)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3 opacity-50"></i>
                        <h4 class="text-muted">No sales found</h4>
                    </div>
                <?php else: ?>
                    <div class="table-responsive bg-white rounded-3 shadow-sm border p-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><strong>#<?php echo str_pad($sale['SaleID'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo date('d M Y, h:i A', strtotime($sale['SaleDate'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($sale['CustomerName'] ?: 'Walk-in Customer'); ?>
                                            <?php if ($sale['CustomerPhone']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($sale['CustomerPhone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>₹<?php echo number_format($sale['FinalAmount'], 2); ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($sale['PaymentMethod']); ?></span></td>
                                        <td>
                                            <a href="sales/invoice.php?id=<?php echo $sale['SaleID']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-receipt"></i> View Invoice
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-4 opacity-25" style="margin-top: 50px;"></i>
            <h3 class="text-muted">Enter a search term</h3>
            <p class="text-muted">Search for medicines by name, category, or batch.<br>Search sales by customer name, phone, or Sale ID.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
