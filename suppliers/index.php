<?php
// suppliers/index.php
require_once __DIR__ . '/../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN');
$base_url = '../';
$extra_css = '
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .action-section {
            background: white; border-radius: 20px; padding: 25px;
            margin-bottom: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        .table-container {
            background: white; border-radius: 20px; padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        .table-modern thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none; font-weight: 600; color: #495057; padding: 15px;
        }
        .table-modern tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .table-modern tbody tr:hover { background: rgba(102, 126, 234, 0.05); }
        .btn-modern { border-radius: 12px; padding: 10px 20px; font-weight: 600; transition: all 0.3s ease; }
        .btn-modern:hover { transform: translateY(-2px); }
    </style>
';
include __DIR__ . '/../includes/header.php';
?>

<div class="main-container">
    <div class="dashboard-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 fw-bold text-white"><i class="fas fa-truck-field"></i> Supplier Management</h1>
            <p class="mb-0 opacity-90 text-white">Manage your pharmacy suppliers and distributors</p>
        </div>
    </div>

    <div id="statusMessage" class="alert d-none fade show" role="alert">
        <span id="statusText"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Actions -->
    <div class="action-section d-flex justify-content-between align-items-center">
        <div class="d-flex gap-2">
            <input type="text" id="searchInput" class="form-control text-dark" placeholder="Search suppliers..." style="width: 300px;">
        </div>
        <?php if ($isAdmin): ?>
            <button class="btn btn-primary btn-modern" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Supplier
            </button>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-modern" id="suppliersTable">
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="supplierList">
                    <tr><td colspan="<?php echo $isAdmin ? '5' : '4'; ?>" class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i> Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header text-white" style="background: var(--primary-gradient); border: none;">
                <h5 class="modal-title" id="modalTitle">Add Supplier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="supplierForm">
                <input type="hidden" name="action" id="formAction" value="add_supplier">
                <input type="hidden" name="supplier_id" id="supplier_id">
                <div class="modal-body p-4 text-dark">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Supplier Name *</label>
                        <input type="text" name="name" id="sup_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Phone</label>
                            <input type="text" name="phone" id="sup_phone" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" id="sup_email" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <textarea name="address" id="sup_address" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light" style="border: none;">
                    <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-modern"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    const supplierModalElement = document.getElementById('supplierModal');
    const supplierModal = typeof bootstrap !== 'undefined' && supplierModalElement ? new bootstrap.Modal(supplierModalElement) : null;

    function showMessage(type, text) {
        const d = document.getElementById('statusMessage');
        d.className = `alert alert-${type} alert-dismissible fade show`;
        document.getElementById('statusText').textContent = text;
        setTimeout(() => d.classList.add('d-none'), 5000);
    }

    async function fetchSuppliers() {
        try {
            const res = await fetch('api.php?action=get_suppliers');
            const result = await res.json();
            if (result.status === 'success') {
                renderTable(result.data);
            } else {
                showMessage('danger', result.message);
            }
        } catch (e) {
            console.error(e);
            showMessage('danger', 'Error fetching suppliers');
        }
    }

    function renderTable(data) {
        let html = '';
        if (data.length === 0) {
            const colspan = isAdmin ? 5 : 4;
            html = `<tr><td colspan="${colspan}" class="text-center py-5 text-muted">No suppliers found.</td></tr>`;
        } else {
            data.forEach(s => {
                html += `
                    <tr class="supplier-row">
                        <td class="fw-bold text-dark supplier-name">${s.Name}</td>
                        <td class="text-dark">${s.Phone || '-'}</td>
                        <td class="text-dark">${s.Email || '-'}</td>
                        <td class="text-dark">${s.Address || '-'}</td>
                `;
                if (isAdmin) {
                    html += `
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick='openEditModal(${JSON.stringify(s)})'><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick='deleteSupplier(${s.SupplierID})'><i class="fas fa-trash"></i></button>
                        </td>
                    `;
                }
                html += `</tr>`;
            });
        }
        document.getElementById('supplierList').innerHTML = html;
    }

    // Search
    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase();
        document.querySelectorAll('.supplier-row').forEach(row => {
            const name = row.querySelector('.supplier-name').textContent.toLowerCase();
            row.style.display = name.includes(val) ? '' : 'none';
        });
    });

    if (isAdmin) {
        window.openAddModal = () => {
            document.getElementById('supplierForm').reset();
            document.getElementById('formAction').value = 'add_supplier';
            document.getElementById('modalTitle').textContent = 'Add Supplier';
        };

        window.openEditModal = (s) => {
            document.getElementById('formAction').value = 'update_supplier';
            document.getElementById('supplier_id').value = s.SupplierID;
            document.getElementById('sup_name').value = s.Name;
            document.getElementById('sup_phone').value = s.Phone || '';
            document.getElementById('sup_email').value = s.Email || '';
            document.getElementById('sup_address').value = s.Address || '';
            document.getElementById('modalTitle').textContent = 'Edit Supplier';
            supplierModal.show();
        };

        window.deleteSupplier = async (id) => {
            if(!confirm('Delete this supplier?')) return;
            try {
                const res = await fetch(`api.php?action=delete_supplier&id=${id}`);
                const result = await res.json();
                if(result.status === 'success') {
                    showMessage('success', result.message);
                    fetchSuppliers();
                } else {
                    showMessage('danger', result.message);
                }
            } catch(e) {
                showMessage('danger', 'Error deleting supplier');
            }
        };

        document.getElementById('supplierForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            try {
                const res = await fetch('api.php', { method: 'POST', body: formData });
                const result = await res.json();
                if(result.status === 'success') {
                    showMessage('success', result.message);
                    supplierModal.hide();
                    fetchSuppliers();
                } else {
                    showMessage('danger', result.message);
                }
            } catch(e) {
                showMessage('danger', 'Error saving supplier');
            }
        });
    }

    fetchSuppliers();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
