<?php
// purchases/index.php (Purchase History)
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN');
$base_url = '../';
$extra_css = '
    <style>
        .main-container { padding: 25px; }
        .dashboard-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-radius: 20px; padding: 25px; margin-bottom: 30px; color: white;
            box-shadow: 0 10px 30px rgba(17, 153, 142, 0.3);
        }
        .table-container {
            background: white; border-radius: 20px; padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #f1f5f9;
        }
        .table-modern thead th { background: #f8fafc; border: none; font-weight: 600; color: #475569; padding: 15px; }
        .table-modern tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table-modern tbody tr:hover { background: #f8fafc; }
    </style>
';
include '../includes/header.php';
?>

<div class="main-container">
    <div class="dashboard-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 fw-bold text-white"><i class="fas fa-clipboard-list"></i> Purchase History</h1>
            <p class="mb-0 opacity-90 text-white">View past supplier orders and restock history</p>
        </div>
        <?php if ($isAdmin): ?>
            <a href="new_purchase.php" class="btn btn-light" style="border-radius:12px; font-weight:600;"><i class="fas fa-plus text-success"></i> New Purchase</a>
        <?php endif; ?>
    </div>

    <div class="table-container">
        <div class="d-flex justify-content-between mb-4">
            <h5 class="fw-bold m-0"><i class="fas fa-history text-success"></i> Recent Purchases</h5>
            <input type="text" id="searchInput" class="form-control w-25" placeholder="Filter by Supplier...">
        </div>
        
        <div class="table-responsive">
            <table class="table table-modern" id="purchasesTable">
                <thead>
                    <tr>
                        <th>Purchase ID</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Total Items</th>
                        <th>Total Amount</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="purchaseList">
                    <tr><td colspan="6" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i> Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Purchase <span id="modalPurchaseId" class="text-primary"></span> Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Exp Date</th><th>Cost</th></tr></thead>
                    <tbody id="itemsList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const detailsModalElement = document.getElementById('detailsModal');
    const detailsModal = typeof bootstrap !== 'undefined' && detailsModalElement ? new bootstrap.Modal(detailsModalElement) : null;

    async function loadPurchases() {
        try {
            const res = await fetch('api.php?action=get_purchases');
            const data = await res.json();
            let html = '';
            if (data.data.length === 0) {
                html = '<tr><td colspan="6" class="text-center py-5 text-muted">No purchases recorded yet.</td></tr>';
            } else {
                data.data.forEach(p => {
                    html += `
                        <tr class="purchase-row">
                            <td class="fw-bold text-dark">#${String(p.PurchaseID).padStart(5, '0')}</td>
                            <td class="text-dark">${p.PurchaseDate}</td>
                            <td class="supplier-name fw-bold text-dark">${p.SupplierName}</td>
                            <td><span class="badge bg-info rounded-pill">${p.TotalItems} items</span></td>
                            <td class="fw-bold text-success">₹${parseFloat(p.TotalAmount).toFixed(2)}</td>
                            <td><button class="btn btn-sm btn-outline-primary" onclick="viewDetails(${p.PurchaseID})">View Items</button></td>
                        </tr>
                    `;
                });
            }
            document.getElementById('purchaseList').innerHTML = html;
        } catch (e) {
            document.getElementById('purchaseList').innerHTML = '<tr><td colspan="6" class="text-danger text-center">Error loading history</td></tr>';
        }
    }

    async function viewDetails(id) {
        document.getElementById('modalPurchaseId').innerText = '#' + String(id).padStart(5, '0');
        document.getElementById('itemsList').innerHTML = '<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin text-primary"></i></td></tr>';
        if (detailsModal) detailsModal.show();
        
        try {
            const res = await fetch(`api.php?action=get_purchase_items&id=${id}`);
            const data = await res.json();
            let html = '';
            data.data.forEach(i => {
                html += `
                    <tr>
                        <td class="fw-bold text-dark">${i.MedicineName}</td>
                        <td class="text-dark">${i.BatchNumber}</td>
                        <td class="text-dark">${i.Quantity}</td>
                        <td class="text-dark">${i.ExpiryDate || 'N/A'}</td>
                        <td class="text-success fw-bold">₹${parseFloat(i.PurchasePrice).toFixed(2)}</td>
                    </tr>
                `;
            });
            document.getElementById('itemsList').innerHTML = html;
        } catch (e) {
            document.getElementById('itemsList').innerHTML = '<tr><td colspan="5" class="text-danger">Failed to load items.</td></tr>';
        }
    }

    document.getElementById('searchInput')?.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('.purchase-row').forEach(r => {
            const sup = r.querySelector('.supplier-name').innerText.toLowerCase();
            r.style.display = sup.includes(query) ? '' : 'none';
        });
    });

    loadPurchases();
</script>

<?php include '../includes/footer.php'; ?>
