<?php
// index.php - Public Landing Page & SaaS Entry Point
session_start();

// Determine if user is already logged in (they might have an expired trial, or be active)
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? ($_SESSION['full_name'] ?? 'User') : '';
$user_email = $is_logged_in ? ($_SESSION['email'] ?? 'user@example.com') : '';

// If accessed natively via the Desktop App, skip the landing/marketing page completely
if (strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'MediVaultDesktopApp') !== false) {
    if ($is_logged_in) {
        header('Location: dashboard.php');
    } else {
        header('Location: users/login.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediVault - AI-Driven Cloud Based Pharmacy Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            overflow-x: hidden;
        }

        /* Navbar */
        .navbar {
            background-color: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 800;
            color: #1e40af;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }

        .navbar-brand i {
            color: #3b82f6;
            font-size: 1.8rem;
        }

        .nav-link {
            font-weight: 500;
            color: #475569;
            margin: 0 10px;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: #1e40af;
        }

        .btn-demo {
            background: #2563eb;
            color: white;
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-demo:hover {
            background: #1d4ed8;
            color: white;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero-section {
            padding: 80px 0;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23eff6ff' fill-opacity='1' d='M0,256L80,240C160,224,320,192,480,186.7C640,181,800,203,960,208C1120,213,1280,203,1360,197.3L1440,192L1440,0L1360,0C1280,0,1120,0,960,0C800,0,640,0,480,0C320,0,160,0,80,0L0,0Z'%3E%3C/path%3E%3C/svg%3E") no-repeat top;
            background-size: cover;
            min-height: 85vh;
            display: flex;
            align-items: center;
        }

        .hero-title {
            color: #1e3a8a;
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 20px;
        }

        .hero-title span {
            color: #2563eb;
        }

        .hero-subtitle {
            color: #64748b;
            font-size: 1.15rem;
            line-height: 1.7;
            margin-bottom: 40px;
            max-width: 90%;
        }

        /* Form Card */
        .registration-card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.08);
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 18px;
            border: 2px solid #e2e8f0;
            margin-bottom: 20px;
            background-color: #f8fafc;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            background-color: white;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            width: 100%;
            padding: 15px;
            color: white;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.3);
            transform: translateY(-2px);
        }

        /* Feature Grid (Right Side) */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            position: relative;
        }

        .feature-pill {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .feature-pill:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: #bfdbfe;
        }

        .feature-icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .feature-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 1.1rem;
        }

        /* Specific Icon Colors */
        .icon-mobility { background: #fee2e2; color: #ef4444; }
        .icon-cloud { background: #dcfce7; color: #22c55e; }
        .icon-sku { background: #e0e7ff; color: #4f46e5; }
        .icon-profit { background: #fef3c7; color: #d97706; }

        /* The arrow SVG decoration */
        .arrow-decoration {
            position: absolute;
            left: -80px;
            top: 40%;
            width: 150px;
            height: auto;
            z-index: 10;
            pointer-events: none;
            opacity: 0.7;
        }

        /* Pricing Section */
        .pricing-section {
            padding: 100px 0;
            background: white;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-header h2 {
            font-weight: 800;
            color: #1e293b;
            font-size: 2.5rem;
        }

        /* Comparison Table Styling */
        .pricing-table-wrapper {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        .pricing-table {
            width: 100%;
            min-width: 800px;
            border-collapse: collapse;
            background-color: white;
        }
        .pricing-table th, .pricing-table td {
            padding: 24px 25px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .pricing-table th.feature-col, .pricing-table td.feature-col {
            text-align: left;
            font-weight: 600;
            color: #334155;
            width: 31%;
            border-right: 1px solid #f1f5f9;
            font-size: 1.05rem;
        }
        .pricing-table th {
            padding-top: 45px;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
            vertical-align: bottom;
            width: 23%;
            border-right: 1px solid transparent;
        }
        .pricing-table th.bg-white { background: white; border-top: none; }
        .pricing-table th.popular-col {
            border-top: 5px solid #06b6d4;
            background: #f0fdfa; /* faint cyan tint */
            border-left: 2px solid #06b6d4;
            border-right: 2px solid #06b6d4;
        }
        .pricing-table td.popular-col {
            background: #f8fafc;
        }
        .pricing-table td.popular-col-body { border-left: 2px solid #06b6d4; border-right: 2px solid #06b6d4; }
        .pricing-table td.popular-col-foot { border-left: 2px solid #06b6d4; border-right: 2px solid #06b6d4; border-bottom: 2px solid #06b6d4; border-radius: 0 0 20px 20px;}
        
        .popular-badge-table {
            position: absolute;
            top: -16px;
            left: 50%;
            transform: translateX(-50%);
            background: #06b6d4;
            color: white;
            padding: 6px 20px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 4px 10px rgba(6, 182, 212, 0.3);
        }
        .plan-name-table { font-size: 1.35rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
        .price-amount-table { font-size: 2.5rem; font-weight: 800; color: #1e3a8a; line-height: 1; }
        .price-amount-table sup { font-size: 1.25rem; top: -0.8em; color: #94a3b8; margin-right: 4px; }
        .feature-icon-check { color: #06b6d4; font-size: 1.4rem; }
        .feature-icon-cross { color: #ef4444; font-size: 1.2rem; background: #fee2e2; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; }
        
        .btn-plan-table { display: inline-block; border-radius: 8px; padding: 10px 24px; font-weight: 600; font-size: 0.95rem; margin-top: 15px; transition: all 0.3s; width: 80%; text-decoration: none;}
        .tbl-btn-outline { background: transparent; color: #1e3a8a; }
        .tbl-btn-outline:hover { color: #06b6d4; }
        .tbl-btn-solid { background: transparent; color: #1e3a8a; }
        .tbl-btn-solid:hover { color: #06b6d4; }

    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-pills me-2"></i> MediVault</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="welcome.php">Our Team</a></li>
                    
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item ms-3"><a class="btn btn-outline-primary rounded-pill px-4" href="users/login.php"><i class="fas fa-user me-1"></i> My Account</a></li>
                    <?php else: ?>
                        <li class="nav-item ms-2"><a class="btn btn-outline-primary rounded-pill px-4" href="users/login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a></li>
                        <li class="nav-item ms-2"><a class="btn btn-demo" href="#register"><i class="fas fa-rocket me-1"></i> Start Free Trial</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Trial Expired Message -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'trial_expired'): ?>
    <div class="bg-danger text-white text-center py-3 fw-bold shadow-sm">
        <i class="fas fa-clock fa-spin me-2"></i> Your 30-Minute Free Trial has expired! Please subscribe below to regain full access to your pharmacy dashboard.
    </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero-section" id="register">
        <div class="container">
            <div class="row align-items-center">
                
                <!-- Left Content -->
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h1 class="hero-title">AI-Driven <span>Cloud Based</span><br>Pharmacy Management System</h1>
                    <p class="hero-subtitle">
                        Our SaaS integrated with precision SKU automation and advanced real-time profit tracking enhances your pharmacy workflow. Stop relying on outdated spreadsheets. Improve your healthcare future with our intelligent architecture.
                    </p>

                    <div class="registration-card text-center p-5 shadow-sm border-0" style="border-radius: 20px;">
                        <div class="feature-icon-wrapper icon-cloud mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2.5rem; background: #e0e7ff; color: #4f46e5;">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h3 class="fw-bold mb-3">Start Your Free Trial</h3>
                        <p class="text-muted mb-4">Get 30 minutes of full access to MediVault — no credit card required. Experience the future of pharmacy management.</p>
                        <a href="users/login.php?mode=register" class="btn btn-demo btn-lg w-100 py-3 mb-3">
                            <i class="fas fa-user-plus me-2"></i> Register & Start Trial
                        </a>
                        <a href="users/login.php" class="btn btn-outline-primary w-100 py-2" style="border-radius:8px;">
                            <i class="fas fa-sign-in-alt me-2"></i> Already have an account? Login
                        </a>
                        <p class="text-center text-muted small mt-4 mb-0"><i class="fas fa-gift me-1"></i> Includes a fully-featured 30-minute free trial.</p>
                    </div>

                </div>

                <!-- Right Content Features Graphic -->
                <div class="col-lg-5 offset-lg-1 position-relative">
                    
                    <svg class="arrow-decoration" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 90 Q 50 10 90 90" stroke="#3b82f6" stroke-width="3" fill="none" stroke-linecap="round"/>
                        <path d="M80 85 L 90 90 L 85 80" stroke="#3b82f6" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                    <div class="feature-grid" id="features">
                        <div class="feature-pill">
                            <div class="feature-icon-wrapper icon-mobility"><i class="fas fa-mobile-alt"></i></div>
                            <span class="feature-title">Mobility POS</span>
                        </div>
                        <div class="feature-pill" style="transform: translateY(30px);">
                            <div class="feature-icon-wrapper icon-cloud"><i class="fas fa-cloud"></i></div>
                            <span class="feature-title">Cloud Based</span>
                        </div>
                        <div class="feature-pill">
                            <div class="feature-icon-wrapper icon-sku"><i class="fas fa-barcode"></i></div>
                            <span class="feature-title">Batch SKUs</span>
                        </div>
                        <div class="feature-pill" style="transform: translateY(30px);">
                            <div class="feature-icon-wrapper icon-profit"><i class="fas fa-chart-line"></i></div>
                            <span class="feature-title">Live Profit Data</span>
                        </div>
                        <div class="feature-pill w-100" style="grid-column: span 2; margin-top: 15px;">
                            <div class="d-flex align-items-center justify-content-center gap-3 w-100">
                                <div class="feature-icon-wrapper mx-0" style="background:#f3e8ff; color:#a855f7; width: 60px; height: 60px;"><i class="fas fa-robot"></i></div>
                                <span class="feature-title mb-0 fs-5">AI-Enhanced Health Tech</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing-section bg-light" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2 style="color:#1e3a8a;">Simple, Transparent<br>Pricing for Your Pharmacy</h2>
                <p class="text-muted mt-2">One-time payment. No hidden fees. Full access forever.</p>
            </div>

            <div class="row justify-content-center g-4 mt-2">

                <!-- One-Time Plan -->
                <div class="col-md-5">
                    <div class="card border-0 shadow-lg h-100" style="border-radius:20px; overflow:hidden;">
                        <div class="card-header text-center py-4" style="background: linear-gradient(135deg,#1e3a8a,#3b82f6); color:white; border:none;">
                            <i class="fas fa-crown mb-2" style="font-size:2.5rem;"></i>
                            <h3 class="fw-800 mb-1">Lifetime License</h3>
                            <p class="mb-0 opacity-75 small">One-time payment — use forever</p>
                        </div>
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <span style="font-size:3.2rem; font-weight:800; color:#1e3a8a;">₹15,000</span>
                                <span class="text-muted d-block small mt-1">One-time payment</span>
                            </div>
                            <ul class="list-unstyled text-start mb-4">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Full Inventory Management</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Unlimited Sales & Billing</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Supplier & Purchase Tracking</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Advanced Financial Reports</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Bulk Excel Import/Export</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> AI-Enhanced Analytics</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Multi-User Access</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Cloud Data Backup</li>
                            </ul>
                            <button class="btn btn-demo w-100 py-3 pay-btn" data-plan="Lifetime" data-price="15000">
                                <i class="fas fa-lock-open me-2"></i> Buy Now — ₹15,000
                            </button>
                        </div>
                    </div>
                </div>

                <!-- AMC Plan -->
                <div class="col-md-5">
                    <div class="card border-0 shadow-lg h-100" style="border-radius:20px; overflow:hidden; border: 2px solid #06b6d4 !important;">
                        <div class="card-header text-center py-4" style="background: linear-gradient(135deg,#0e7490,#06b6d4); color:white; border:none;">
                            <span class="popular-badge-table mb-2" style="display:inline-block;">Recommended</span>
                            <i class="fas fa-shield-alt mb-2" style="font-size:2.5rem; display:block;"></i>
                            <h3 class="fw-800 mb-1">AMC — Annual Support</h3>
                            <p class="mb-0 opacity-75 small">Annual Maintenance Contract</p>
                        </div>
                        <div class="card-body text-center p-4">
                            <div class="mb-3">
                                <span style="font-size:3.2rem; font-weight:800; color:#0e7490;">₹2,500</span>
                                <span class="text-muted d-block small mt-1">per year</span>
                            </div>
                            <ul class="list-unstyled text-start mb-4">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Everything in Lifetime License</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Priority Technical Support</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Free Software Updates</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Remote Assistance & Training</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Bug Fixes & Security Patches</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> WhatsApp Support Channel</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Annual Data Health Check</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Feature Request Priority</li>
                            </ul>
                            <button class="btn w-100 py-3 pay-btn fw-bold" style="background:linear-gradient(135deg,#0e7490,#06b6d4); color:white; border-radius:8px;" data-plan="AMC" data-price="2500">
                                <i class="fas fa-handshake me-2"></i> Subscribe AMC — ₹2,500/yr
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <p class="text-center text-muted mt-4 small"><i class="fas fa-info-circle me-1"></i> To purchase, first <a href="users/login.php?mode=register">register your free trial account</a>, then return here to complete payment.</p>
        </div>
    </section>ction>

    <!-- Footer -->
    <footer class="bg-white py-4 border-top">
        <div class="container text-center text-muted">
            <p class="mb-0">&copy; <?= date('Y') ?> MediVault Pharmacy SaaS. All rights reserved.</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        // Registration AJAX Logic Removed - Users now download the Desktop App
        // Razorpay Subscription Logic
        const isLoggedIn = <?= json_encode($is_logged_in) ?>;
        
        document.querySelectorAll('.pay-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (!isLoggedIn) {
                    alert("Please register a free demo account on the left before subscribing so we can provision your database.");
                    window.scrollTo({top: 0, behavior: 'smooth'});
                    document.getElementById('hospitalName').focus();
                    return;
                }

                const planName = this.getAttribute('data-plan');
                const amount = this.getAttribute('data-price');

                const options = {
                    "key": "rzp_test_YourTestKey", // Using a dummy test key placeholder
                    "amount": amount * 100, // Amount is in currency subunits (paise)
                    "currency": "INR",
                    "name": "MediVault SaaS",
                    "description": planName + " Subscription",
                    "image": "med.jpg",
                    "handler": function (response){
                        // Payment Successful Callback
                        fetch('subscription/api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'verify_payment',
                                razorpay_payment_id: response.razorpay_payment_id,
                                plan: planName,
                                amount: amount,
                                duration: 'Monthly'
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if(data.status === 'success') {
                                alert('Payment Successful! Your software limit restrictions are lifted.');
                                window.location.href = 'dashboard.php';
                            } else {
                                alert('Payment verified, but error updating account: ' + data.message);
                            }
                        })
                        .catch(err => {
                            alert('Payment succeeded, but we failed to reach our server. Please contact support.');
                        });
                    },
                    "prefill": {
                        "name": "<?= addslashes($user_name) ?>",
                        "email": "<?= addslashes($user_email) ?>",
                        "contact": "9999999999" 
                    },
                    "theme": {
                        "color": "#3b82f6"
                    }
                };
                
                // For demonstration/testing simulating successful payment
                if (confirm("Test Mode Notice:\n\nYou haven't configured a live Razorpay Key yet. Would you like to simulate a successful UPI/Card payment to activate the '" + planName + "' subscription?")) {
                    options.handler({ razorpay_payment_id: "pay_mock_" + Math.random().toString(36).substr(2, 9) });
                }
            });
        });
    </script>
</body>
</html>
