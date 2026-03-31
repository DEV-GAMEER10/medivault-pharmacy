<?php
//invoice.php
require_once __DIR__ . '/../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$sale_id = $_GET['id'];

// Get sale details
$sale_stmt = $pdo->prepare("SELECT * FROM sales WHERE SaleID = ?");
$sale_stmt->execute([$sale_id]);
$sale = $sale_stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: index.php');
    exit;
}

// Get sale items
$items_stmt = $pdo->prepare("SELECT * FROM sales_items WHERE SaleID = ?");
$items_stmt->execute([$sale_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$base_url = '../';
$extra_css = '
    <style>
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .top-header { display: none !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
            body { font-size: 12px; background-color: white !important; }
            .invoice-box { border: none !important; }
        }
        .invoice-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .invoice-box { border: 2px solid #667eea; border-radius: 10px; background: white; margin-bottom: 20px; }
        .invoice-footer { background-color: #f8f9fa; }
    </style>
';
include __DIR__ . '/../includes/header.php';
?>
    <div class="container mt-4">
        <!-- Print Buttons -->
        <div class="row mb-3 no-print">
            <div class="col-12 text-end">
                <a href="index.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
            </div>
        </div>

        <!-- Invoice -->
        <div class="invoice-box">
            <!-- Header -->
            <div class="invoice-header p-4 text-center">
                <h2><i class="fas fa-pills"></i> PHARMACY MANAGEMENT SYSTEM</h2>
                <p class="mb-0">Complete Healthcare Solutions</p>
            </div>

            <!-- Invoice Details -->
            <div class="p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="text-primary">Invoice Details</h5>
                        <p><strong>Invoice #:</strong> <?php echo str_pad($sale['SaleID'], 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($sale['SaleDate'])); ?></p>
                        <p><strong>Payment Method:</strong> <span class="badge bg-success"><?php echo $sale['PaymentMethod']; ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <h5 class="text-primary">Customer Details</h5>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($sale['CustomerName'] ?: 'Walk-in Customer'); ?></p>
                        <?php if ($sale['CustomerPhone']): ?>
                        <p><strong>Phone:</strong> <?php echo $sale['CustomerPhone']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-primary">
                            <tr>
                                <th width="5%">S.No</th>
                                <th width="35%">Product Name</th>
                                <th width="15%">Batch No.</th>
                                <th width="10%">Qty</th>
                                <th width="15%">Unit Price</th>
                                <th width="20%">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $serial = 1;
                            $subtotal = 0;
                            foreach ($items as $item): 
                                $subtotal += $item['TotalPrice'];
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $serial++; ?></td>
                                <td><?php echo htmlspecialchars($item['ItemName']); ?></td>
                                <td><?php echo htmlspecialchars($item['BatchNumber']); ?></td>
                                <td class="text-center"><?php echo $item['Quantity']; ?></td>
                                <td class="text-end">₹<?php echo number_format($item['UnitPrice'], 2); ?></td>
                                <td class="text-end"><strong>₹<?php echo number_format($item['TotalPrice'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded">
                            <h6>Payment Information</h6>
                            <p class="mb-1"><strong>Method:</strong> <?php echo $sale['PaymentMethod']; ?></p>
                            <p class="mb-1"><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $sale['Status'] == 'COMPLETED' ? 'success' : 'warning'; ?>">
                                    <?php echo $sale['Status']; ?>
                                </span>
                            </p>
                            <p class="mb-0"><strong>Total Items:</strong> <?php echo count($items); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end">₹<?php echo number_format($sale['TotalAmount'], 2); ?></td>
                            </tr>
                            <?php if ($sale['Discount'] > 0): ?>
                            <tr class="text-success">
                                <td class="text-end"><strong>Discount:</strong></td>
                                <td class="text-end">- ₹<?php echo number_format($sale['Discount'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($sale['Tax'] > 0): ?>
                            <tr>
                                <td class="text-end"><strong>Tax:</strong></td>
                                <td class="text-end">₹<?php echo number_format($sale['Tax'], 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="table-primary">
                                <td class="text-end"><strong>TOTAL AMOUNT:</strong></td>
                                <td class="text-end"><strong>₹<?php echo number_format($sale['FinalAmount'], 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="invoice-footer p-3 text-center">
                <p class="mb-1"><strong>Thank you for choosing our pharmacy!</strong></p>
                <p class="mb-1 text-muted">For any queries, please contact us.</p>
                <p class="mb-0 small">This is a computer generated invoice.</p>
            </div>
        </div>

        <!-- Additional Actions -->
        <div class="row mt-3 no-print">
            <div class="col-12 text-center">
                <a href="new_sale.php" class="btn btn-success me-2">
                    <i class="fas fa-plus"></i> New Sale
                </a>
                <a href="sales_history.php" class="btn btn-info">
                    <i class="fas fa-history"></i> Sales History
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
