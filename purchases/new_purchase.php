<?php
// purchases/new_purchase.php
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN');
if (!$isAdmin) {
    die('<div style="padding: 20px; font-family: sans-serif;"><h2>Access Denied</h2><p>Only administrators can create purchase entries.</p><a href="../dashboard.php">Go back</a></div>');
}

// Fetch suppliers
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY Name ASC")->fetchAll(PDO::FETCH_ASSOC);

$base_url = '../';
$extra_css = '
    <style>
        .container-fluid { padding: 20px; }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .card { border-radius: 15px; border: none; box-shadow: 0 8px 30px rgba(0,0,0,0.05); }
        .card-header { background-color: transparent; border-bottom: 2px solid #f1f5f9; padding: 20px; font-weight: bold; }
        .table th { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
        .btn-modern { border-radius: 10px; padding: 10px 20px; font-weight: 600; transition: all 0.3s ease; }
        .btn-modern:hover { transform: translateY(-2px); }
        .cart-total-box { background: #f8fafc; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; }
        .total-amount { font-size: 2rem; font-weight: bold; color: #2563eb; }
    </style>
';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="dashboard-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 fw-bold text-white"><i class="fas fa-cart-arrow-down"></i> New Purchase Entry</h1>
            <p class="mb-0 opacity-90 text-white">Record new stock arrivals and update inventory</p>
        </div>
        <a href="index.php" class="btn btn-light btn-modern"><i class="fas fa-list"></i> Purchase History</a>
    </div>

    <div id="statusMessage" class="alert d-none fade show" role="alert">
        <span id="statusText"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <div class="row g-4">
        <!-- Left Panel: Add Items -->
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header text-primary"><i class="fas fa-truck"></i> Purchase Details</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Supplier *</label>
                        <select id="supplier_id" class="form-select form-select-lg" required>
                            <option value="">-- Choose Supplier --</option>
                            <?php foreach($suppliers as $s): ?>
                                <option value="<?=$s['SupplierID']?>"><?=htmlspecialchars($s['Name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="my-4 text-muted">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-pills text-info"></i> Add Item to Purchase</h5>
                        <button type="button" id="btnAiSuggest" class="btn btn-sm btn-dark text-white fw-bold shadow-sm" style="background: linear-gradient(135deg, #a855f7, #6b21a8); border:none; transition: transform 0.2s;">
                            🤖 Auto-Fill AI Suggestions
                        </button>
                    </div>
                    
                    <form id="addItemForm">
                        <div class="mb-3">
                            <label class="form-label">Medicine Name *</label>
                            <input type="text" id="med_name" class="form-control" required placeholder="e.g. Paracetamol 500mg">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Batch Number *</label>
                                <input type="text" id="med_batch" class="form-control" required placeholder="e.g. B2024">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Quantity *</label>
                                <input type="number" id="med_qty" class="form-control" required min="1" placeholder="e.g. 100">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Purchase Price (Total) *</label>
                                <input type="number" id="med_price" class="form-control" required step="0.01" min="0" placeholder="₹ Total Cost">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" id="med_expiry" class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-modern w-100">
                            <i class="fas fa-plus"></i> Add to Purchase Cart
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Panel: Cart -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header text-success"><i class="fas fa-shopping-cart"></i> Purchase Cart</div>
                <div class="card-body d-flex flex-column">
                    <div class="table-responsive flex-grow-1" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle" id="cartTable">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th>Item</th>
                                    <th>Batch</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="cartList">
                                <tr><td colspan="5" class="text-center text-muted py-4">Cart is empty. Add items from the left.</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cart-total-box mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="fs-5 text-muted fw-bold">Total Amount:</span>
                            <span class="total-amount" id="cartTotalDisplay">₹0.00</span>
                        </div>
                        <button id="btnSubmitPurchase" class="btn btn-success btn-modern btn-lg w-100" disabled>
                            <i class="fas fa-check-circle"></i> Save & Update Inventory
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let cart = [];

    function showMessage(type, text) {
        const d = document.getElementById('statusMessage');
        d.className = `alert alert-${type} alert-dismissible fade show`;
        document.getElementById('statusText').textContent = text;
        setTimeout(() => d.classList.add('d-none'), 5000);
        window.scrollTo(0, 0);
    }

    function renderCart() {
        const list = document.getElementById('cartList');
        const btnSubmit = document.getElementById('btnSubmitPurchase');
        let total = 0;
        
        if (cart.length === 0) {
            list.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Cart is empty. Add items from the left.</td></tr>';
            btnSubmit.disabled = true;
        } else {
            let html = '';
            cart.forEach((item, index) => {
                total += parseFloat(item.price);
                html += `
                    <tr>
                        <td><div class="fw-bold">${item.name}</div><small class="text-muted text-nowrap">Exp: ${item.expiry || 'N/A'}</small></td>
                        <td>${item.batch}</td>
                        <td><span class="badge bg-secondary rounded-pill px-3 py-2">${item.qty}</span></td>
                        <td class="fw-bold text-success">₹${parseFloat(item.price).toFixed(2)}</td>
                        <td><button class="btn btn-sm btn-outline-danger" onclick="removeItem(${index})"><i class="fas fa-times"></i></button></td>
                    </tr>
                `;
            });
            list.innerHTML = html;
            btnSubmit.disabled = false;
        }
        document.getElementById('cartTotalDisplay').textContent = `₹${total.toFixed(2)}`;
    }

    document.getElementById('addItemForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const item = {
            name: document.getElementById('med_name').value.trim(),
            batch: document.getElementById('med_batch').value.trim(),
            qty: parseInt(document.getElementById('med_qty').value),
            price: parseFloat(document.getElementById('med_price').value),
            expiry: document.getElementById('med_expiry').value
        };
        
        // Prevent exact duplicates in cart
        const duplicateIndex = cart.findIndex(c => c.name.toLowerCase() === item.name.toLowerCase() && c.batch.toLowerCase() === item.batch.toLowerCase());
        if (duplicateIndex > -1) {
            alert('This medicine and batch is already in the cart. Please remove it first to update the quantity/price.');
            return;
        }

        cart.push(item);
        e.target.reset(); // clear form
        renderCart();
    });

    // AI Predictive Fetch Logic
    document.getElementById('btnAiSuggest').addEventListener('click', async () => {
        const btn = document.getElementById('btnAiSuggest');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing Velocity...';
        
        try {
            const res = await fetch('api.php?action=get_ai_predictions');
            const result = await res.json();
            
            if (result.status === 'success' && result.data.length > 0) {
                let addedCount = 0;
                result.data.forEach(aiItem => {
                    // Only add if not already in cart
                    const duplicateIndex = cart.findIndex(c => c.name.toLowerCase() === aiItem.name.toLowerCase());
                    if (duplicateIndex === -1) {
                        cart.push(aiItem);
                        addedCount++;
                    }
                });
                renderCart();
                
                if (addedCount > 0) {
                    showMessage('success', `🤖 AI Engine: Added ${addedCount} suggested items based on 30-day velocity.`);
                } else {
                    showMessage('info', 'AI suggestions are already in your cart.');
                }
            } else if (result.status === 'success') {
                showMessage('info', '✅ Inventory is healthy! No items need restocking based on 30-day velocity vectors.');
            } else {
                showMessage('danger', result.message);
            }
        } catch(e) {
            showMessage('danger', 'Error connecting to AI prediction engine.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    window.removeItem = (index) => {
        cart.splice(index, 1);
        renderCart();
    };

    document.getElementById('btnSubmitPurchase').addEventListener('click', async () => {
        const supplier_id = document.getElementById('supplier_id').value;
        if (!supplier_id) {
            alert('Please select a supplier.');
            return;
        }
        if (cart.length === 0) return;

        if (!confirm('Are you sure you want to save this purchase? The inventory will be permanently updated.')) return;

        const payload = {
            action: 'save_purchase',
            supplier_id: supplier_id,
            items: cart
        };

        const btn = document.getElementById('btnSubmitPurchase');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await res.json();
            
            if (result.status === 'success') {
                showMessage('success', result.message);
                cart = [];
                renderCart();
                document.getElementById('supplier_id').value = '';
            } else {
                showMessage('danger', result.message);
            }
        } catch (e) {
            showMessage('danger', 'A network error occurred while saving the purchase.');
        } finally {
            if (cart.length > 0) btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Save & Update Inventory';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
