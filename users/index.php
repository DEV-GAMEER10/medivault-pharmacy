<?php
//users/index.php - User Management Dashboard
require_once __DIR__ . '/../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Create users table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        UserID INT AUTO_INCREMENT PRIMARY KEY,
        Username VARCHAR(50) UNIQUE NOT NULL,
        Email VARCHAR(100) UNIQUE NOT NULL,
        Password VARCHAR(255) NOT NULL,
        FullName VARCHAR(100) NOT NULL,
        Role ENUM('ADMIN', 'MANAGER', 'CASHIER', 'PHARMACIST') DEFAULT 'CASHIER',
        Phone VARCHAR(20),
        Address TEXT,
        Status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED') DEFAULT 'ACTIVE',
        LastLogin DATETIME,
        CreatedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CreatedBy VARCHAR(50) DEFAULT 'SYSTEM'
    )");
} catch(Exception $e) {
    // Table might already exist
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'add_user':
                try {
                    // Subscription Limit Check
                    require_once __DIR__ . '/../includes/subscription_check.php';
                    $limit = $_SESSION['limits']['staff'] ?? 1;
                    $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE Role != 'ADMIN'");
                    $currentStaff = $countStmt->fetchColumn();
                    if ($currentStaff >= $limit) {
                        throw new Exception("Staff limit reached for your " . ($_SESSION['plan_name'] ?? 'Free Trial') . " plan ($limit).");
                    }

                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $full_name = $_POST['full_name'];
                    $role = $_POST['role'];
                    $phone = $_POST['phone'] ?? null;
                    $address = $_POST['address'] ?? null;
                    
                    $stmt = $pdo->prepare("INSERT INTO users (Username, Email, Password, FullName, Role, Phone, Address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $password, $full_name, $role, $phone, $address]);
                    
                    $success = "User added successfully!";
                } catch(Exception $e) {
                    $error = "Error adding user: " . $e->getMessage();
                }
                break;
                
            case 'update_status':
                try {
                    $user_id = $_POST['user_id'];
                    $status = $_POST['status'];
                    
                    $stmt = $pdo->prepare("UPDATE users SET Status = ? WHERE UserID = ?");
                    $stmt->execute([$status, $user_id]);
                    
                    $success = "User status updated successfully!";
                } catch(Exception $e) {
                    $error = "Error updating status: " . $e->getMessage();
                }
                break;

            case 'delete_user':
                try {
                    $user_id = $_POST['user_id'];
                    if ($user_id == $_SESSION['user_id']) throw new Exception("You cannot delete your currently active account.");
                    $stmt = $pdo->prepare("DELETE FROM users WHERE UserID = ?");
                    $stmt->execute([$user_id]);
                    $success = "User permanently deleted.";
                } catch(Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
                
            case 'factory_reset':
                try {
                    $pdo->exec("DELETE FROM sales_items");
                    $pdo->exec("DELETE FROM sales");
                    $pdo->exec("DELETE FROM medicines");
                    $pdo->exec("DELETE FROM users WHERE UserID != " . intval($_SESSION['user_id']));
                    $success = "Database successfully wiped clean! Only your Admin account securely remains.";
                } catch(Exception $e) {
                    $error = "Factory Reset failed: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all users
$users_query = "SELECT * FROM users ORDER BY CreatedDate DESC";
$users = $pdo->query($users_query)->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN Status = 'ACTIVE' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN Role = 'ADMIN' THEN 1 ELSE 0 END) as admin_users,
        SUM(CASE WHEN LastLogin >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_logins
    FROM users
";
$stats = $pdo->query($stats_query)->fetch(PDO::FETCH_ASSOC);
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

        .total-card { background: var(--primary-gradient); }
        .active-card { background: var(--success-gradient); }
        .admin-card { background: var(--warning-gradient); }
        .recent-card { background: var(--info-gradient); }

        .action-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .btn-modern {
            border-radius: 12px;
            padding: 12px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
        }

        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
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

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            margin-right: 10px;
        }

        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .modal-modern .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .modal-modern .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
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
                        <i class="fas fa-users"></i> User Management
                    </h1>
                    <p class="mb-0 opacity-90">Manage users, roles, and permissions</p>
                </div>
                <a href="../sales/index.php" class="btn btn-light btn-modern">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card total-card">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card active-card">
                <i class="fas fa-user-check stat-icon"></i>
                <div class="stat-value"><?php echo number_format($stats['active_users'] ?? 0); ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card admin-card">
                <i class="fas fa-user-shield stat-icon"></i>
                <div class="stat-value"><?php echo number_format($stats['admin_users'] ?? 0); ?></div>
                <div class="stat-label">Admin Users</div>
            </div>
            <div class="stat-card recent-card">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?php echo number_format($stats['recent_logins'] ?? 0); ?></div>
                <div class="stat-label">Recent Logins</div>
            </div>
        </div>

        <!-- Actions -->
        <div class="action-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-cogs text-primary"></i> Quick Actions
                </h5>
            </div>
            <button type="button" class="btn btn-primary btn-modern me-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add New User
            </button>
            <button type="button" class="btn btn-info btn-modern me-2" onclick="exportUsers()">
                <i class="fas fa-download"></i> Export Users
            </button>
            <button type="button" class="btn btn-danger btn-modern float-end shadow-sm" style="border: 2px solid #ef4444;" onclick="factoryReset()">
                <i class="fas fa-biohazard text-white me-1"></i> Factory Reset Demo Data
            </button>
        </div>

        <!-- Users Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-list text-primary"></i> User List
                </h5>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" placeholder="Search users..." id="searchUsers" style="width: 250px;">
                    <select class="form-select" id="filterRole" style="width: 150px;">
                        <option value="">All Roles</option>
                        <option value="ADMIN">Admin</option>
                        <option value="MANAGER">Manager</option>
                        <option value="PHARMACIST">Pharmacist</option>
                        <option value="CASHIER">Cashier</option>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-modern" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-user-plus fa-3x mb-3"></i>
                                <h5>No users found</h5>
                                <p>Add your first user to get started.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar" style="background: <?php echo '#' . substr(md5($user['Username']), 0, 6); ?>">
                                        <?php echo strtoupper(substr($user['FullName'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['FullName']); ?></div>
                                        <small class="text-muted">@<?php echo htmlspecialchars($user['Username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($user['Email']); ?></div>
                                <?php if ($user['Phone']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($user['Phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="role-badge bg-<?php 
                                    echo $user['Role'] === 'ADMIN' ? 'danger' : 
                                        ($user['Role'] === 'MANAGER' ? 'warning' : 
                                        ($user['Role'] === 'PHARMACIST' ? 'info' : 'secondary')); 
                                ?>">
                                    <?php echo $user['Role']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge bg-<?php echo $user['Status'] === 'ACTIVE' ? 'success' : ($user['Status'] === 'INACTIVE' ? 'secondary' : 'danger'); ?>">
                                    <?php echo $user['Status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['LastLogin']): ?>
                                    <div><?php echo date('d M Y', strtotime($user['LastLogin'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($user['LastLogin'])); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo date('d M Y', strtotime($user['CreatedDate'])); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($user['CreatedBy']); ?></small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo $user['UserID']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="viewUser(<?php echo $user['UserID']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-warning dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $user['UserID']; ?>, 'ACTIVE')">Activate</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $user['UserID']; ?>, 'INACTIVE')">Deactivate</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $user['UserID']; ?>, 'SUSPENDED')">Suspend</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteUser(<?php echo $user['UserID']; ?>)">Delete</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade modal-modern" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="phone" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role *</label>
                                    <select name="role" class="form-select" required>
                                        <option value="CASHIER">Cashier</option>
                                        <option value="PHARMACIST">Pharmacist</option>
                                        <option value="MANAGER">Manager</option>
                                        <option value="ADMIN">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-modern">
                            <i class="fas fa-save"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <!-- Removed duplicate bootstrap JS script import; handled by footer.php -->
    <script>
        // Search functionality
        document.getElementById('searchUsers').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Filter by role
        document.getElementById('filterRole').addEventListener('change', function() {
            const selectedRole = this.value;
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                if (!selectedRole) {
                    row.style.display = '';
                } else {
                    const roleCell = row.cells[2]?.textContent.trim();
                    row.style.display = roleCell === selectedRole ? '' : 'none';
                }
            });
        });

        // Change user status
        function changeStatus(userId, status) {
            if (confirm(`Are you sure you want to change this user's status to ${status}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="user_id" value="${userId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Placeholder functions for additional features
        function editUser(userId) {
            alert('Edit user functionality - User ID: ' + userId);
        }

        function viewUser(userId) {
            alert('View user details - User ID: ' + userId);
        }

        function deleteUser(userId) {
            if (confirm('Are you absolutely sure you want to permanently delete this user? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function factoryReset() {
            if (confirm('WARNING: Are you absolutely sure? This will wipe ALL Sales, Inventory, and Users (except yours). This factory reset is permanent!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="factory_reset">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportUsers() {
            alert('Export users functionality');
        }

        function bulkActions() {
            alert('Bulk actions functionality');
        }
    </script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
