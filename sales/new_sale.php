<?php
//new_sale.php
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../users/login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'process_sale') {
    try {
        // Subscription Limit Check: Daily Sales
        require_once '../includes/subscription_check.php';
        $limit = $_SESSION['limits']['sales'] ?? 50;
        $countStmt = $pdo->query("SELECT COUNT(*) FROM sales WHERE DATE(SaleDate) = CURDATE()");
        $currentSales = $countStmt->fetchColumn();
        if ($currentSales >= $limit) {
            throw new Exception("Daily sales limit reached for your " . ($_SESSION['plan_name'] ?? 'Free Trial') . " plan ($limit). Please upgrade to continue billing.");
        }

        $pdo->beginTransaction();
        
        $customer_name = $_POST['customer_name'] ?? null;
        $customer_phone = $_POST['customer_phone'] ?? null;
        $payment_method = $_POST['payment_method'] ?? 'CASH';
        $discount = (float)($_POST['discount'] ?? 0);
        $tax = (float)($_POST['tax'] ?? 0);
        
        $items = json_decode($_POST['items'], true);
        $total_amount = 0;
        $total_cost = 0;
        
        // Calculate total
        foreach ($items as $item) {
            $qty = (float)$item['quantity'];
            $price = (float)$item['price'];
            $cost = (float)($item['cost'] ?? 0);
            $total_amount += $qty * $price;
            $total_cost += $qty * $cost;
        }
        
        $final_amount = $total_amount - $discount + $tax;
        $total_profit = $final_amount - $total_cost;
        
        // Insert sale record
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $doctor_name = !empty($_POST['doctor_name']) ? trim($_POST['doctor_name']) : null;

        $sale_stmt = $pdo->prepare("INSERT INTO sales (CreatedBy, CustomerID, CustomerName, CustomerPhone, DoctorName, TotalAmount, Discount, Tax, FinalAmount, PaymentMethod, TotalCost, TotalProfit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $sale_stmt->execute([$_SESSION['user_id'], $customer_id, $customer_name, $customer_phone, $doctor_name, $total_amount, $discount, $tax, $final_amount, $payment_method, $total_cost, $total_profit]);
        
        $sale_id = $pdo->lastInsertId();
        
        // Insert sale items
        $item_stmt = $pdo->prepare("INSERT INTO sales_items (SaleID, ItemID, ItemName, BatchNumber, Category, TypeForm, Quantity, UnitPrice, TotalPrice, CostPrice) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $qty = (float)$item['quantity'];
            $price = (float)$item['price'];
            $total_price = $qty * $price;
            $cost = (float)($item['cost'] ?? 0);
            
            $item_stmt->execute([
                $sale_id,
                $item['item_id'],
                $item['name'],
                $item['batch'],
                $item['category'] ?? '',
                $item['type_form'] ?? '',
                $qty,
                $price,
                $total_price,
                $cost
            ]);
        }
        
        $pdo->commit();
        
        // Redirect to invoice
        header("Location: invoice.php?id=" . $sale_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing sale: " . $e->getMessage();
    }
}
?>

<?php
$base_url = '../';
$extra_css = '
    <style>
        .card { border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .item-search { position: relative; }
        .search-results { 
            position: absolute; 
            top: 100%; 
            left: 0; 
            right: 0; 
            background: white; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            max-height: 200px; 
            overflow-y: auto; 
            z-index: 1000;
            display: none;
        }
        .search-item { 
            padding: 10px; 
            cursor: pointer; 
            border-bottom: 1px solid #eee;
        }
        .search-item:hover { background-color: #f8f9fa; }
        .cart-total { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            font-size: 1.2em; 
        }
    </style>
';
include '../includes/header.php';
?>
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h1 class="h3 text-primary"><i class="fas fa-shopping-cart"></i> New Sale</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Side - Product Selection -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-search"></i> Search & Add Products</h5>
                    </div>
                    <div class="card-body">
                        <!-- Search Box -->
                        <div class="item-search mb-4">
                            <input type="text" id="itemSearch" class="form-control form-control-lg" 
                                   placeholder="Search by product name, batch number, or category...">
                            <div class="search-results" id="searchResults"></div>
                        </div>

                        <!-- Cart Items Table -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="cartTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Product</th>
                                        <th>Batch</th>
                                        <th>Available</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="cartItems">
                                    <tr id="emptyCart">
                                        <td colspan="7" class="text-center text-muted">
                                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                            <br>Cart is empty. Search and add products to start sale.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Sale Summary -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator"></i> Sale Summary</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="saleForm">
                            <input type="hidden" name="action" value="process_sale">
                            <input type="hidden" name="items" id="cartData">

                            <!-- Advanced CRM Customer Details -->
                            <div class="mb-3 position-relative" id="crmSearchContainer">
                                <label class="form-label d-flex justify-content-between align-items-center">
                                    <span>Patient / Customer A/C</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#quickAddCustomerModal" style="font-size: 0.8rem; line-height: 1.5;">
                                        <i class="fas fa-plus"></i> New
                                    </button>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                    <input type="text" id="crmPatientSearch" class="form-control fw-bold" placeholder="Search name or mobile..." autocomplete="off">
                                </div>
                                <div class="shadow-lg w-100 bg-white" id="crmPatientResults" style="display:none; position:absolute; z-index:1050; border-radius:8px; border:1px solid #ddd; max-height:250px; overflow-y:auto; top:70px;"></div>
                                
                                <!-- Hidden inputs to bind to form -->
                                <input type="hidden" name="customer_id" id="crmCustomerId">
                                <input type="hidden" name="customer_name" id="crmCustomerName">
                                <input type="hidden" name="customer_phone" id="crmCustomerPhone">
                            </div>

                            <!-- Selected Patient Card (Shows when customer is selected) -->
                            <div id="crmSelectedPatientCard" class="card mb-3 border-primary bg-primary bg-opacity-10" style="display:none;">
                                <div class="card-body p-3 position-relative">
                                    <button type="button" class="btn-close position-absolute top-0 end-0 m-2" onclick="clearCRMSelection()" style="font-size:10px;"></button>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user-check text-primary fa-2x me-3"></i>
                                        <div>
                                            <h6 class="mb-0 fw-bold text-primary" id="crmCardName"></h6>
                                            <small class="text-muted" id="crmCardPhone"></small>
                                        </div>
                                    </div>
                                    <hr class="my-2 opacity-25 border-primary">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div style="font-size:0.85rem;" class="text-dark">
                                            <i class="fas fa-stethoscope text-secondary"></i> <span id="crmCardDoctor"></span><br>
                                            <i class="fas fa-map-marker-alt text-secondary mt-1"></i> <span id="crmCardAddress"></span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" onclick="fetchPatientHistory()">
                                            <i class="fas fa-history"></i> History
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Referring Doctor</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-md"></i></span>
                                    <input type="text" name="doctor_name" id="crmDoctorName" class="form-control" placeholder="Dr. Name (Optional)">
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="CASH">Cash</option>
                                    <option value="CARD">Card</option>
                                    <option value="UPI">UPI</option>
                                </select>
                            </div>

                            <!-- Amounts -->
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Discount (₹)</label>
                                    <input type="number" name="discount" id="discount" class="form-control" 
                                           value="0" step="0.01" min="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Tax (₹)</label>
                                    <input type="number" name="tax" id="tax" class="form-control" 
                                           value="0" step="0.01" min="0">
                                </div>
                            </div>

                            <!-- Total Summary -->
                            <div class="card cart-total">
                                <div class="card-body text-center">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Items:</strong>
                                            <div id="totalItems">0</div>
                                        </div>
                                        <div class="col-6">
                                            <strong>Quantity:</strong>
                                            <div id="totalQty">0</div>
                                        </div>
                                    </div>
                                    <hr class="text-white">
                                    <div class="row">
                                        <div class="col-12">
                                            <strong>Subtotal:</strong>
                                            <div class="h4" id="subtotal">₹0.00</div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <small>Discount:</small>
                                            <div id="discountAmount">₹0.00</div>
                                        </div>
                                        <div class="col-6">
                                            <small>Tax:</small>
                                            <div id="taxAmount">₹0.00</div>
                                        </div>
                                    </div>
                                    <hr class="text-white">
                                    <div class="h3" id="finalTotal">₹0.00</div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-success btn-lg" id="processSale" disabled>
                                    <i class="fas fa-check"></i> Process Sale
                                </button>
                                <button type="button" class="btn btn-warning" onclick="clearCart()">
                                    <i class="fas fa-trash"></i> Clear Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let cart = [];

        $(document).ready(function() {
            // Search functionality
            $('#itemSearch').on('input', function() {
                const query = $(this).val();
                if (query.length >= 2) {
                    searchProducts(query);
                } else {
                    $('#searchResults').hide();
                }
            });

            // Update totals when discount or tax changes
            $('#discount, #tax').on('input', updateTotals);
        });

        function searchProducts(query) {
            $.ajax({
                url: 'search_products.php',
                method: 'POST',
                data: { query: query },
                dataType: 'json',
                success: function(products) {
                    let html = '';
                    products.forEach(function(product) {
                        html += `
                            <div class="search-item" onclick="addToCart(${product.ItemID}, '${product.ItemName}', '${product.BatchNumber}', ${product.SellingPrice || product.CostPrice}, ${product.Quantity}, '${product.Category}', '${product.TypeForm}', ${product.CostPrice})">
                                <strong>${product.ItemName}</strong> - ${product.Category}
                                <br><small class="text-muted">Batch: ${product.BatchNumber} | Available: ${product.Quantity} | Price: ₹${product.SellingPrice || product.CostPrice}</small>
                            </div>
                        `;
                    });
                    $('#searchResults').html(html).show();
                }
            });
        }

        function addToCart(itemId, name, batch, price, available, category, typeForm, costPrice) {
            // Check if item already exists in cart
            const existingItem = cart.find(item => item.item_id === itemId && item.batch === batch);
            
            if (existingItem) {
                if (existingItem.quantity < available) {
                    existingItem.quantity++;
                } else {
                    alert('Cannot add more items. Stock limit reached.');
                    return;
                }
            } else {
                cart.push({
                    item_id: itemId,
                    name: name,
                    batch: batch,
                    category: category,
                    type_form: typeForm,
                    price: price,
                    cost: costPrice, // Track cost specifically for profit calculcation
                    quantity: 1,
                    available: available
                });
            }

            updateCartDisplay();
            $('#itemSearch').val('');
            $('#searchResults').hide();
        }

        function updateCartDisplay() {
            const tbody = $('#cartItems');
            tbody.empty();

            if (cart.length === 0) {
                tbody.html(`
                    <tr id="emptyCart">
                        <td colspan="7" class="text-center text-muted">
                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                            <br>Cart is empty. Search and add products to start sale.
                        </td>
                    </tr>
                `);
                $('#processSale').prop('disabled', true);
            } else {
                cart.forEach(function(item, index) {
                    const total = item.quantity * item.price;
                    tbody.append(`
                        <tr>
                            <td><strong>${item.name}</strong></td>
                            <td>${item.batch}</td>
                            <td><span class="badge bg-info">${item.available}</span></td>
                            <td>
                                <div class="input-group" style="width: 100px;">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="updateQuantity(${index}, -1)">-</button>
                                    <input type="number" class="form-control form-control-sm text-center" value="${item.quantity}" min="1" max="${item.available}" onchange="setQuantity(${index}, this.value)">
                                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="updateQuantity(${index}, 1)">+</button>
                                </div>
                            </td>
                            <td>₹${item.price}</td>
                            <td><strong>₹${total.toFixed(2)}</strong></td>
                            <td>
                                <button class="btn btn-danger btn-sm" onclick="removeFromCart(${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                });
                $('#processSale').prop('disabled', false);
            }

            updateTotals();
        }

        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantity + change;
            
            if (newQuantity >= 1 && newQuantity <= item.available) {
                item.quantity = newQuantity;
                updateCartDisplay();
            }
        }

        function setQuantity(index, quantity) {
            const item = cart[index];
            const newQuantity = parseInt(quantity);
            
            if (newQuantity >= 1 && newQuantity <= item.available) {
                item.quantity = newQuantity;
                updateCartDisplay();
            }
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function clearCart() {
            cart = [];
            updateCartDisplay();
        }

        function updateTotals() {
            let subtotal = 0;
            let totalItems = cart.length;
            let totalQty = 0;

            cart.forEach(function(item) {
                subtotal += item.quantity * item.price;
                totalQty += item.quantity;
            });

            const discount = parseFloat($('#discount').val()) || 0;
            const tax = parseFloat($('#tax').val()) || 0;
            const finalTotal = subtotal - discount + tax;

            $('#totalItems').text(totalItems);
            $('#totalQty').text(totalQty);
            $('#subtotal').text('₹' + subtotal.toFixed(2));
            $('#discountAmount').text('₹' + discount.toFixed(2));
            $('#taxAmount').text('₹' + tax.toFixed(2));
            $('#finalTotal').text('₹' + finalTotal.toFixed(2));

            // Update hidden form data
            $('#cartData').val(JSON.stringify(cart));
        }
    </script>
    
    <!-- Quick Add Customer Modal -->
    <div class="modal fade" id="quickAddCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius: 20px;">
                <div class="modal-header bg-light border-0 p-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-plus text-primary me-2"></i> Register New Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="quickAddForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Patient Name *</label>
                            <input type="text" class="form-control" id="qaName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mobile Number</label>
                            <input type="tel" class="form-control" id="qaPhone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Address / Area</label>
                            <input type="text" class="form-control" id="qaAddress">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Referring Doctor</label>
                            <input type="text" class="form-control" id="qaDoctor">
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveQuickCustomer()">Save & Select</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient History Modal / Offcanvas Side Panel -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="patientHistoryPanel" style="width: 450px;">
        <div class="offcanvas-header bg-primary bg-opacity-10 border-bottom border-primary border-opacity-25 pb-3">
            <div>
                <h5 class="offcanvas-title fw-bold text-primary"><i class="fas fa-history me-2"></i> Patient History</h5>
                <small class="text-muted" id="historyPanelName"></small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body bg-light" id="historyPanelBody">
            <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Loading history...</div>
        </div>
    </div>

    <script>
        // CRM Integrations
        let searchTimeout;
        $('#crmPatientSearch').on('input', function() {
            clearTimeout(searchTimeout);
            let query = $(this).val().trim();
            if(query.length < 2) {
                $('#crmPatientResults').hide();
                return;
            }
            searchTimeout = setTimeout(() => {
                $.ajax({
                    url: 'crm_api.php',
                    type: 'POST',
                    data: JSON.stringify({ action: 'search_customers', query: query }),
                    contentType: 'application/json',
                    success: function(res) {
                        if(res.status === 'success' && res.data.length > 0) {
                            let html = '';
                            res.data.forEach(c => {
                                html += `<div class="p-3 border-bottom search-item crm-result-item" onclick="selectCRMCustomer(${c.CustomerID}, '${c.Name}', '${c.Phone}', '${c.DoctorName}', '${c.Address}')" style="cursor:pointer;">
                                            <div class="fw-bold text-primary">${c.Name}</div>
                                            <div class="small text-muted"><i class="fas fa-phone"></i> ${c.Phone || 'N/A'} | <i class="fas fa-stethoscope"></i> ${c.DoctorName || 'N/A'}</div>
                                         </div>`;
                            });
                            $('#crmPatientResults').html(html).show();
                        } else {
                            $('#crmPatientResults').html('<div class="p-3 text-muted text-center">No patients found.</div>').show();
                        }
                    }
                });
            }, 300);
        });

        $(document).click(function(e) {
            if(!$(e.target).closest('#crmSearchContainer').length) {
                $('#crmPatientResults').hide();
            }
        });

        function selectCRMCustomer(id, name, phone, doctor, address) {
            $('#crmPatientResults').hide();
            $('#crmPatientSearch').val('');
            $('#crmSearchContainer').hide();
            
            $('#crmCustomerId').val(id);
            $('#crmCustomerName').val(name);
            $('#crmCustomerPhone').val(phone);
            $('#crmDoctorName').val((doctor && doctor !== 'null') ? doctor : '');
            
            $('#crmCardName').text(name);
            $('#crmCardPhone').text((phone && phone !== 'null') ? phone : 'No Phone Provided');
            $('#crmCardDoctor').text((doctor && doctor !== 'null') ? doctor : 'No Referrer');
            $('#crmCardAddress').text((address && address !== 'null') ? address : 'No Address Provided');
            
            $('#crmSelectedPatientCard').fadeIn();
        }

        function clearCRMSelection() {
            $('#crmCustomerId').val('');
            $('#crmCustomerName').val('');
            $('#crmCustomerPhone').val('');
            $('#crmSelectedPatientCard').hide();
            $('#crmSearchContainer').fadeIn();
        }

        function saveQuickCustomer() {
            let data = {
                action: 'quick_add_customer',
                name: $('#qaName').val(),
                phone: $('#qaPhone').val(),
                address: $('#qaAddress').val(),
                doctor: $('#qaDoctor').val()
            };
            
            if(!data.name) { alert("Patient Name is required!"); return; }
            
            $.ajax({
                url: 'crm_api.php',
                type: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                success: function(res) {
                    if(res.status === 'success') {
                        let c = res.customer;
                        selectCRMCustomer(c.CustomerID, c.Name, c.Phone, c.DoctorName, c.Address);
                        
                        // Fail-safe modal closing logic
                        let modalEl = document.getElementById('quickAddCustomerModal');
                        let modalInst = bootstrap.Modal.getInstance(modalEl);
                        if (modalInst) {
                            modalInst.hide();
                        } else {
                            // Fallback if getInstance fails
                            $('#quickAddCustomerModal').modal('hide');
                        }
                        
                        // Forceably kill the bootstrap backdrop to prevent screen freeze
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css({"padding-right": "", "overflow": ""});
                        
                        document.getElementById('quickAddForm').reset();
                    } else {
                        alert(res.message);
                    }
                },
                error: function() {
                    alert("Network error. Please try again.");
                }
            });
        }

        function fetchPatientHistory() {
            let customerId = $('#crmCustomerId').val();
            let name = $('#crmCustomerName').val();
            if(!customerId) return;

            $('#historyPanelName').text(name);
            
            // Prevent singleton duplication freeze
            let offcanvasEl = document.getElementById('patientHistoryPanel');
            let offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl) || new bootstrap.Offcanvas(offcanvasEl);
            offcanvas.show();

            $.ajax({
                url: 'crm_api.php',
                type: 'POST',
                data: JSON.stringify({ action: 'get_patient_history', customer_id: customerId }),
                contentType: 'application/json',
                success: function(res) {
                    if(res.status === 'success') {
                        let html = '';
                        if(res.data.length === 0) {
                            html = '<div class="text-center py-5 text-muted"><i class="fas fa-box-open fa-3x mb-3 opacity-50"></i><br>No previous purchases found.</div>';
                        } else {
                            res.data.forEach(sale => {
                                let dateStr = new Date(sale.SaleDate).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year:'numeric' });
                                html += `<div class="card mb-3 border-0 shadow-sm">
                                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
                                                <span class="fw-bold text-dark"><i class="fas fa-calendar-alt text-primary me-1"></i> ${dateStr}</span>
                                                <span class="text-success fw-bold">₹${parseFloat(sale.FinalAmount).toFixed(2)}</span>
                                            </div>
                                            <div class="card-body p-0">
                                                <ul class="list-group list-group-flush">`;
                                
                                sale.items.forEach(item => {
                                    let safeItem = encodeURIComponent(JSON.stringify({
                                        id: item.ItemID,
                                        name: item.ItemName.replace(/'/g, "\\'"),
                                        batch: item.BatchNumber,
                                        price: item.UnitPrice,
                                        cost: item.UnitPrice // Approximated for reorder ease
                                    }));
                                    html += `<li class="list-group-item d-flex justify-content-between align-items-center py-2 bg-light">
                                                <div>
                                                    <div class="fw-bold" style="font-size:0.9rem;">${item.ItemName}</div>
                                                    <small class="text-muted px-1 border border-secondary rounded" style="font-size:0.75rem;">Qty: ${item.Quantity}</small>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary rounded-circle" onclick="reorderItem('${safeItem}', ${item.Quantity})" title="Reorder this item">
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                             </li>`;
                                });
                                
                                html += `</ul></div></div>`;
                            });
                        }
                        $('#historyPanelBody').html(html);
                    }
                }
            });
        }

        function reorderItem(encodedStr, qty) {
            try {
                let p = JSON.parse(decodeURIComponent(encodedStr));
                addToCart(p.id, p.name, p.batch, '', '', 9999, p.price, p.cost);
                let idx = cart.findIndex(i => i.item_id == p.id && i.batch == p.batch);
                if(idx !== -1) {
                    setQuantity(idx, qty);
                }
            } catch(e) { console.error(e); }
        }
    </script>
<?php include '../includes/footer.php'; ?>