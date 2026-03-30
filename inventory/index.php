<?php
// inventory/index.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}
require_once '../config/database.php';

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Fetch categories & suppliers for filter dropdowns using PDO
$categories = $pdo->query("SELECT DISTINCT Category FROM medicines ORDER BY Category ASC")->fetchAll(PDO::FETCH_ASSOC);
$suppliers  = $pdo->query("SELECT DISTINCT SupplierName FROM medicines ORDER BY SupplierName ASC")->fetchAll(PDO::FETCH_ASSOC);

$base_url = '../';
$extra_css = '
<style>
    .card {
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    }
    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #343a40;
    }
    .card-text strong { color: #555; }
    .expired {
        border: 2px solid #dc3545;
        background-color: #f8d7da;
        color: #721c24;
    }
    .expiring-soon {
        border: 2px solid #ffc107;
        background-color: #fff3cd;
        color: #664d03;
    }
    .low-stock {
        border: 2px solid #17a2b8;
        background-color: #d1ecf1;
        color: #0c5460;
    .main-container {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05);
        padding: 2rem;
        margin-top: 2rem;
    }
    .status-tab {
        border: none;
        background: none;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        color: #64748b;
        border-bottom: 2px solid transparent;
        transition: all 0.3s;
    }
    .status-tab:hover { color: #6366f1; }
    .status-tab.active {
        color: #6366f1;
        border-bottom-color: #6366f1;
    }
    .inventory-table thead {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
    }
    .inventory-table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #64748b;
        padding: 1rem;
    }
    .inventory-table td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .batch-row {
        background-color: #f8fafc;
        display: none;
    }
    .batch-row td {
        padding: 0.75rem 2rem !important;
        font-size: 0.875rem;
    }
    .expand-btn {
        cursor: pointer;
        transition: transform 0.2s;
    }
    .expand-btn.rotated { transform: rotate(180deg); }
    .offcanvas-header { background: #6366f1; color: white; }
    .badge-subtle {
        padding: 0.35em 0.65em;
        border-radius: 0.375rem;
        font-weight: 600;
    }
    .bg-low { background: #fef3c7; color: #92400e; }
    .bg-expired { background: #fee2e2; color: #991b1b; }
    .bg-expiring { background: #ffedd5; color: #9a3412; }
    .bg-seasonal { background: #e0e7ff; color: #3730a3; }
</style>
';
include '../includes/header.php';
?>

<div class="container-fluid pb-5">
    <div class="main-container">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-boxes text-primary me-2"></i> Inventory Manager</h2>
                <p class="text-muted mb-0">Manage your pharmaceutical stock with precision and seasonal insights.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#offcanvasAdd">
                    <i class="fas fa-plus me-1"></i> Add Medicine
                </button>
                <?php if (isset($_SESSION['plan_name'])): ?>
                    <input type="file" id="bulkImportFile" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" class="d-none">
                    <button class="btn btn-outline-primary" onclick="document.getElementById('bulkImportFile').click()">
                        <i class="fas fa-file-excel"></i> Import
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="p-3 border rounded-3 bg-light">
                    <small class="text-muted d-block mb-1">Total Stock Value</small>
                    <h4 class="fw-bold mb-0 text-primary" id="totalStockValueDisplay">₹0.00</h4>
                </div>
            </div>
            <div id="statusAlertContainer" class="col-md-9 d-none">
                <div id="statusMessage" class="alert mb-0 fade show" role="alert">
                    <span id="statusText"></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <!-- Filter & Tab Section -->
        <div class="card mb-4 border-0 shadow-sm overflow-hidden">
            <div class="bg-white border-bottom p-2 d-flex gap-2 overflow-auto">
                <button class="status-tab active" data-filter="all">All Items</button>
                <button class="status-tab" data-filter="low_stock"><i class="fas fa-arrow-down me-1"></i> Low Stock</button>
                <button class="status-tab" data-filter="expiring"><i class="fas fa-hourglass-half me-1"></i> Expiring Soon</button>
                <button class="status-tab" data-filter="seasonal"><i class="fas fa-cloud-sun me-1"></i> Seasonal Focus</button>
            </div>
            <div class="p-3 bg-light">
                <form id="filterForm" class="row g-2 align-items-center">
                    <div class="col-md-4"><input type="text" name="filter_name" class="form-control form-control-sm" placeholder="Search item name or SKU..."></div>
                    <div class="col-md-3">
                        <select name="filter_category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php if($categories): foreach($categories as $c): ?>
                                <option value="<?=h($c['Category'])?>" <?=(($_GET['filter_category'] ?? '') == $c['Category']?'selected':'')?>><?=h($c['Category'])?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="sort_expiry" class="form-select form-select-sm">
                            <option value="">Sort by Expiry</option>
                            <option value="1">Expiring Soonest</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-dark w-100"><i class="fas fa-sync me-1"></i> Refresh</button></div>
                </form>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table inventory-table align-middle">
                <thead>
                    <tr>
                        <th width="30"></th>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th class="text-center">Batches</th>
                        <th class="text-center">Total Stock</th>
                        <th>Avg Cost</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryList">
                    <tr><td colspan="8" class="text-center p-5 active"><i class="fas fa-spinner fa-spin fa-2x"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Medicine Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAdd" style="width: 500px;">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold"><i class="fas fa-plus-circle me-1"></i> Add New Stock</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <form id="addForm" class="row g-3">
            <input type="hidden" name="action" value="add_medicine">
            <div class="col-12"><label class="form-label fw-bold">Item Name</label><input type="text" name="name" class="form-control" placeholder="e.g. AZEE 500" required></div>
            <div class="col-md-6"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Antibiotics" required></div>
            <div class="col-md-6"><label class="form-label">Type/Form</label><input type="text" name="form" class="form-control" placeholder="e.g. Tablet" required></div>
            <div class="col-md-6"><label class="form-label">Batch Number</label><input type="text" name="batch" class="form-control" placeholder="B-123" required></div>
            <div class="col-md-6"><label class="form-label">Supplier</label><input type="text" name="supplier" class="form-control" placeholder="ABC Pharma" required></div>
            <div class="col-md-4"><label class="form-label">Cost Price</label><input type="number" step="0.01" name="cost_price" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Selling Price</label><input type="number" step="0.01" name="selling_price" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Expiry Date</label><input type="date" name="expiry" class="form-control"></div>
            <div class="col-md-6">
                <label class="form-label">Seasonality</label>
                <select name="seasonality" class="form-select">
                    <option value="NONE">None</option>
                    <option value="WINTER">Winter (Flu)</option>
                    <option value="MONSOON">Monsoon (Fever)</option>
                    <option value="SUMMER">Summer</option>
                </select>
            </div>
            <div class="col-12 mt-4"><button class="btn btn-primary w-100 py-2 fw-bold" type="submit">Add to Inventory</button></div>
        </form>
    </div>
</div>

<!-- Update Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="updateForm" class="modal-content">
            <input type="hidden" name="action" value="update_medicine">
            <input type="hidden" name="update_id" id="update_id">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Update Stock Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3"><label class="form-label">Item Name</label><input type="text" name="name" id="update_name" class="form-control" required></div>
                <div class="row g-2">
                    <div class="col-6 mb-3"><label class="form-label">Category</label><input type="text" name="category" id="update_category" class="form-control" required></div>
                    <div class="col-6 mb-3"><label class="form-label">Batch</label><input type="text" name="batch" id="update_batch" class="form-control" required></div>
                </div>
                <div class="row g-2">
                    <div class="col-6 mb-3"><label class="form-label">Cost Price</label><input type="number" step="0.01" name="cost_price" id="update_cost_price" class="form-control" required></div>
                    <div class="col-6 mb-3"><label class="form-label">Selling Price</label><input type="number" step="0.01" name="selling_price" id="update_selling_price" class="form-control" required></div>
                </div>
                <div class="row g-2">
                    <div class="col-6 mb-3"><label class="form-label">Quantity</label><input type="number" name="quantity" id="update_quantity" class="form-control" required></div>
                    <div class="col-6 mb-3"><label class="form-label">Expiry</label><input type="date" name="expiry" id="update_expiry" class="form-control"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Seasonal Trend</label>
                    <select name="seasonality" id="update_seasonality" class="form-select">
                        <option value="NONE">None</option>
                        <option value="WINTER">Winter</option>
                        <option value="MONSOON">Monsoon</option>
                        <option value="SUMMER">Summer</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="submit" class="btn btn-primary fw-bold px-4">Save Updates</button>
            </div>
        </form>
    </div>
</div>

<?php 
$extra_js = "
<script src=\"https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js\"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('inventoryList');
    const addForm = document.getElementById('addForm');
    const updateForm = document.getElementById('updateForm');
    const filterForm = document.getElementById('filterForm');
    const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
    const offcanvasAdd = new bootstrap.Offcanvas(document.getElementById('offcanvasAdd'));
    const statusAlertContainer = document.getElementById('statusAlertContainer');
    const statusTextSpan = document.getElementById('statusText');
    const totalStockValueDisplay = document.getElementById('totalStockValueDisplay');

    let currentFilterTab = 'all';

    // Show status message
    function showStatusMessage(type, message) {
        statusAlertContainer.classList.remove('d-none');
        const alert = document.getElementById('statusMessage');
        alert.classList.remove('alert-success', 'alert-danger', 'alert-info');
        alert.classList.add(`alert-\${type}`);
        statusTextSpan.textContent = message;
        setTimeout(() => { statusAlertContainer.classList.add('d-none'); }, 5000);
    }

    // Fetch & render with grouping
    async function fetchAndRenderInventory() {
        tableBody.innerHTML = '<tr><td colspan=\"8\" class=\"text-center p-5\"><i class=\"fas fa-spinner fa-spin fa-2x text-primary\"></i></td></tr>';
        
        const formData = new FormData(filterForm);
        const queryString = new URLSearchParams(formData).toString();

        try {
            const response = await fetch(`api.php?action=get_inventory&\${queryString}`);
            const result = await response.json();

            if (result.status === 'success') {
                const medicines = result.data;
                totalStockValueDisplay.textContent = '₹' + medicines.reduce((acc, current) => acc + (current.CostPrice * current.Quantity), 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });

                // Group by Medicine Name
                const grouped = {};
                medicines.forEach(med => {
                    const name = med.ItemName || 'Uncategorized';
                    if (!grouped[name]) grouped[name] = { items: [], totalQty: 0, categories: new Set(), types: new Set(), avgCost: 0 };
                    grouped[name].items.push(med);
                    grouped[name].totalQty += parseInt(med.Quantity);
                    grouped[name].categories.add(med.Category);
                    grouped[name].types.add(med.TypeForm);
                    grouped[name].avgCost += parseFloat(med.CostPrice) * parseInt(med.Quantity);
                });

                let html = '';
                const LOW_STOCK_THRESHOLD = 20;

                Object.keys(grouped).forEach((name, index) => {
                    const group = grouped[name];
                    const avgCost = group.totalQty > 0 ? group.avgCost / group.totalQty : 0;
                    
                    // Filter logic per tab
                    let showInTab = true;
                    if (currentFilterTab === 'low_stock') showInTab = group.totalQty <= LOW_STOCK_THRESHOLD;
                    if (currentFilterTab === 'expiring') showInTab = group.items.some(item => {
                        if (!item.ExpiryDate) return false;
                        const diff = (new Date(item.ExpiryDate).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24);
                        return diff <= 30;
                    });
                    if (currentFilterTab === 'seasonal') showInTab = group.items.some(item => item.SeasonalityTag && item.SeasonalityTag !== 'NONE');

                    if (!showInTab) return;

                    const hasExpiring = group.items.some(item => {
                         if (!item.ExpiryDate) return false;
                         return (new Date(item.ExpiryDate).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24) <= 30;
                    });

                    html += `
                        <tr class=\"fw-bold text-dark\">
                            <td><i class=\"fas fa-chevron-right expand-btn text-muted\" onclick=\"toggleBatches('\${index}', this)\"></i></td>
                            <td>\${name}</td>
                            <td><span class=\"badge bg-light text-dark\">\${Array.from(group.categories).join(', ')}</span></td>
                            <td class=\"text-muted fw-normal\">\${Array.from(group.types).join(', ')}</td>
                            <td class=\"text-center\"><span class=\"badge rounded-pill bg-secondary\">\${group.items.length} Batches</span></td>
                            <td class=\"text-center\">
                                <span class=\"badge-subtle \${group.totalQty <= LOW_STOCK_THRESHOLD ? 'bg-low' : 'bg-success text-white'}\">
                                    \${group.totalQty} Units
                                </span>
                            </td>
                            <td>₹\${avgCost.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                            <td class=\"text-end\">
                                \${hasExpiring ? '<span class=\"badge-subtle bg-expiring me-2\"><i class=\"fas fa-exclamation-triangle\"></i> RISK</span>' : ''}
                                \${group.items.some(it => it.SeasonalityTag !== 'NONE') ? '<span class=\"badge-subtle bg-seasonal\"><i class=\"fas fa-cloud-moon\"></i> SEASONAL</span>' : ''}
                            </td>
                        </tr>
                    `;

                    // Individual Batch Rows
                    group.items.forEach(item => {
                        const diffDays = item.ExpiryDate ? (new Date(item.ExpiryDate).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24) : 999;
                        const isExpired = diffDays <= 0;
                        const isExpiring = diffDays <= 30;

                        html += `
                            <tr class=\"batch-row batch-\${index} ms-3\">
                                <td></td>
                                <td colspan=\"2\"><small class=\"text-muted\">Batch: \${item.BatchNumber}</small></td>
                                <td><small class=\"text-muted\">\${item.SupplierName || 'Direct'}</small></td>
                                <td class=\"text-center\">\${item.Quantity}</td>
                                <td class=\"text-center\">\${item.CostPrice}</td>
                                <td>
                                    <small class=\"\${isExpired ? 'text-danger fw-bold' : (isExpiring ? 'text-warning fw-bold' : 'text-muted')}\">
                                        Exp: \${item.ExpiryDate || 'N/A'}
                                    </small>
                                </td>
                                <td class=\"text-end\">
                                    <button class=\"btn btn-sm btn-link py-0\" onclick='openUpdateModal(\${JSON.stringify(item)})'><i class=\"fas fa-edit\"></i></button>
                                    <button class=\"btn btn-sm btn-link py-0 text-danger\" onclick=\"deleteMedicine(\${item.ItemID})\"><i class=\"fas fa-trash\"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                });

                tableBody.innerHTML = html || '<tr><td colspan=\"8\" class=\"text-center p-5 text-muted\">No stock found matching current criteria.</td></tr>';
            }
        } catch (error) { showStatusMessage('danger', 'System Error: Failed to sync inventory data.'); }
    }

    window.toggleBatches = (index, btn) => {
        const rows = document.querySelectorAll('.batch-' + index);
        const isHidden = rows[0].style.display === 'none' || rows[0].style.display === '';
        rows.forEach(r => r.style.display = isHidden ? 'table-row' : 'none');
        btn.classList.toggle('rotated', isHidden);
        btn.classList.toggle('fa-chevron-right', !isHidden);
        btn.classList.toggle('fa-chevron-down', isHidden);
    };

    // Tab Logic
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelector('.status-tab.active').classList.remove('active');
            tab.classList.add('active');
            currentFilterTab = tab.dataset.filter;
            fetchAndRenderInventory();
        });
    });

    window.openUpdateModal = (item) => {
        document.getElementById('update_id').value = item.ItemID;
        document.getElementById('update_name').value = item.ItemName || '';
        document.getElementById('update_category').value = item.Category || '';
        document.getElementById('update_batch').value = item.BatchNumber || '';
        document.getElementById('update_cost_price').value = item.CostPrice || '';
        document.getElementById('update_selling_price').value = item.SellingPrice || '';
        document.getElementById('update_quantity').value = item.Quantity || '';
        document.getElementById('update_expiry').value = item.ExpiryDate || '';
        document.getElementById('update_seasonality').value = item.SeasonalityTag || 'NONE';
        updateModal.show();
    };

    // Form handlers
    addForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const response = await fetch('api.php', { method: 'POST', body: new FormData(addForm) });
        const result = await response.json();
        if (result.status === 'success') {
            showStatusMessage('success', 'New stock item has been added to the vault.');
            addForm.reset();
            offcanvasAdd.hide();
            fetchAndRenderInventory();
        } else showStatusMessage('danger', result.message);
    });

    updateForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const response = await fetch('api.php', { method: 'POST', body: new FormData(updateForm) });
        const result = await response.json();
        if (result.status === 'success') {
            showStatusMessage('success', 'Stock record updated successfully.');
            updateModal.hide();
            fetchAndRenderInventory();
        } else showStatusMessage('danger', result.message);
    });

    window.deleteMedicine = async (id) => {
        if (!confirm('Are you sure you want to permanently delete this batch record?')) return;
        const response = await fetch(`api.php?action=delete_medicine&id=\${id}`);
        const result = await response.json();
        if (result.status === 'success') {
            showStatusMessage('success', 'Batch record deleted.');
            fetchAndRenderInventory();
        }
    };

    filterForm.addEventListener('submit', (e) => { e.preventDefault(); fetchAndRenderInventory(); });
    fetchAndRenderInventory();

    // Excel Logic (same as before)
    document.getElementById('bulkImportFile').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const jsonData = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            fetch('api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'import_inventory', items: jsonData })
            })
            .then(res => res.json())
            .then(res => { if(res.status === 'success') fetchAndRenderInventory(); showStatusMessage(res.status === 'success' ? 'success' : 'danger', res.message); });
        };
        reader.readAsArrayBuffer(file);
    });
});
</script>
";
include '../includes/footer.php';
?>
