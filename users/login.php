<?php
// users/login.php - Full login and registration system
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if config file exists
if (!file_exists('../config/database.php')) {
    die('Config file not found. Please create config/database.php');
}

// Note: Browser check removed — login is now accessible from both the desktop app and direct browser access.

require_once '../config/database.php';

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

$error = '';
$success = '';
$mode = $_GET['mode'] ?? 'login'; // login or register

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE Username = ? AND Status = 'ACTIVE'");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['Password'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['full_name'] = $user['FullName'];
                    $_SESSION['role'] = $user['Role'];
                    $_SESSION['email'] = $user['Email'];
                    
                    // Update last login
                    $update_stmt = $pdo->prepare("UPDATE users SET LastLogin = NOW() WHERE UserID = ?");
                    $update_stmt->execute([$user['UserID']]);
                    
                    header('Location: ../dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                }
            } catch (Exception $e) {
                $error = 'Login failed. Please try again.';
            }
        } else {
            $error = 'Please enter both username and password';
        }
    } 
    elseif ($_POST['action'] === 'register') {
        $username = $_POST['reg_username'] ?? '';
        $email = $_POST['reg_email'] ?? '';
        $password = $_POST['reg_password'] ?? '';
        $confirm_password = $_POST['reg_confirm_password'] ?? '';
        $full_name = $_POST['reg_full_name'] ?? '';
        $phone = $_POST['reg_phone'] ?? '';
        $role = $_POST['reg_role'] ?? 'CASHIER';
        
        if ($username && $email && $password && $full_name) {
            if ($password === $confirm_password) {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (Username, Email, Password, FullName, Phone, Role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Automatically log the user in for the 30-minute trial
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role'] = $role;
                    $_SESSION['email'] = $email;
                    
                    // Start 30 min strict trial
                    $_SESSION['trial_start'] = time();
                    
                    // Update last login
                    $update_stmt = $pdo->prepare("UPDATE users SET LastLogin = NOW() WHERE UserID = ?");
                    $update_stmt->execute([$userId]);
                    
                    header('Location: ../dashboard.php');
                    exit;
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                        $error = 'Username or email already exists';
                    } else {
                        $error = 'Registration failed: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Passwords do not match';
            }
        } else {
            $error = 'Please fill all required fields';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediVault Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --secondary: #64748b;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 20px;
            display: flex;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .login-sidebar {
            flex: 1;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            padding: 60px;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        @media (max-width: 768px) {
            .login-sidebar { display: none; }
            .login-wrapper { max-width: 450px; }
        }

        .login-sidebar h1 {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .login-sidebar p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-content {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
        }

        .back-home {
            position: absolute;
            top: 30px;
            left: 30px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: opacity 0.3s;
        }
        
        .back-home:hover {
            opacity: 0.8;
            color: white;
        }

        .login-logo {
            width: 48px;
            height: 48px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 32px;
        }

        h2 {
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .subtitle {
            color: var(--secondary);
            margin-bottom: 32px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #475569;
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            margin-top: 16px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
        }

        .mode-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 32px;
        }

        .mode-toggle a {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--secondary);
            transition: all 0.2s;
        }

        .mode-toggle a.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .alert {
            border-radius: 12px;
            font-size: 0.875rem;
            padding: 12px 16px;
            margin-bottom: 24px;
            border: none;
        }

        .alert-danger { background: #fef2f2; color: #991b1b; }
        .alert-success { background: #f0fdf4; color: #166534; }

        .footer-text {
            margin-top: auto;
            text-align: center;
            font-size: 0.875rem;
            color: var(--secondary);
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-sidebar">
            <a href="../index.php" class="back-home">
                <i class="fas fa-arrow-left"></i>
                <i class="fas fa-home"></i> Back to Website
            </a>
            <h1>MediVault</h1>
            <p>Empowering pharmacies with smart inventory management, real-time analytics, and seamless sales experiences.</p>
            <div class="mt-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3 fs-3"><i class="fas fa-check-circle"></i></div>
                    <div>Cloud-based Accessibility</div>
                </div>
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3 fs-3"><i class="fas fa-check-circle"></i></div>
                    <div>AI-Driven Forecasting</div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-3 fs-3"><i class="fas fa-check-circle"></i></div>
                    <div>Secure Data Backups</div>
                </div>
            </div>
        </div>

        <div class="login-content">
            <div class="login-logo d-md-none mb-4">
                <i class="fas fa-pills"></i>
            </div>
            
            <h2><?php echo $mode === 'register' ? 'Create Account' : 'Welcome Back'; ?></h2>
            <p class="subtitle"><?php echo $mode === 'register' ? 'Join our pharmacy network today' : 'Please enter your login details'; ?></p>

            <div class="mode-toggle">
                <a href="?mode=login" class="<?php echo $mode === 'login' ? 'active' : ''; ?>">Login</a>
                <a href="?mode=register" class="<?php echo $mode === 'register' ? 'active' : ''; ?>">Register</a>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if ($mode === 'login'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="register">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="reg_full_name" class="form-control" placeholder="John Doe" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="reg_username" class="form-control" placeholder="johndoe123" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="reg_email" class="form-control" placeholder="name@company.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="reg_role" class="form-select">
                        <option value="STAFF">Pharmacist / Staff</option>
                        <option value="ADMIN">Administrator</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="reg_password" class="form-control" placeholder="••••••••" required minlength="6">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Confirm</label>
                        <input type="password" name="reg_confirm_password" class="form-control" placeholder="••••••••" required minlength="6">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Account</button>
            </form>
            <?php endif; ?>

            <div class="footer-text mt-4">
                <?php if ($mode === 'login'): ?>
                    Don't have an account? <a href="?mode=register">Register</a>
                <?php else: ?>
                    Already have an account? <a href="?mode=login">Login</a>
                <?php endif; ?>
                <div class="mt-2" style="opacity: 0.6; font-size: 0.75rem;">
                    &copy; 2024 MediVault. Secure Pharmacy Solutions.
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>