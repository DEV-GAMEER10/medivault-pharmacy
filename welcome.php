<?php
// welcome.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: users/login.php');
    exit;
}

$base_url = '';
$extra_css = '
<style>
    /* Hero Section */
    .hero-section {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
        color: white;
        padding: 50px 0;
        position: relative;
        overflow: hidden;
        border-radius: 15px;
        margin-bottom: 30px;
    }

    .hero-section::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1000 100\' fill=\'white\' opacity=\'0.1\'><polygon points=\'0,0 1000,0 1000,60 0,100\'/></svg>") no-repeat bottom;
        background-size: cover;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        text-align: center;
    }

    .hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
        text-shadow: 2px 4px 8px rgba(0,0,0,0.2);
    }

    .hero-subtitle {
        font-size: 1.3rem;
        font-weight: 400;
        opacity: 0.95;
        margin-bottom: 2rem;
    }

    .hero-description {
        font-size: 1.1rem;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.9;
        font-weight: 300;
    }

    /* Mission Section */
    .mission-section {
        padding: 60px 0;
        background: white;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .section-title {
        font-size: 2.2rem;
        font-weight: 700;
        color: #1e40af;
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .section-title::after {
        content: "";
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
        border-radius: 2px;
    }

    .mission-content {
        max-width: 900px;
        margin: 0 auto;
        text-align: center;
    }

    .mission-text {
        font-size: 1.1rem;
        color: #475569;
        font-weight: 400;
        line-height: 1.8;
        margin-bottom: 2rem;
    }

    /* Features Section */
    .features-section {
        padding: 60px 0;
        background: transparent;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }

    .feature-card {
        background: white;
        padding: 40px 30px;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        border: 1px solid rgba(59, 130, 246, 0.1);
        position: relative;
        overflow: hidden;
    }

    .feature-card::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #60a5fa);
    }

    .feature-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(59, 130, 246, 0.15);
    }

    .feature-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(96, 165, 250, 0.1));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 25px;
    }

    .feature-icon i {
        font-size: 2rem;
        color: #3b82f6;
    }

    .feature-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 15px;
    }

    .feature-description {
        color: #64748b;
        font-size: 1rem;
        line-height: 1.6;
    }

    /* Team Section */
    .team-section {
        padding: 60px 0;
        background: white;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }

    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
        margin-top: 50px;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
    }

    .team-member {
        text-align: center;
        padding: 30px;
        background: #f8fafc;
        border-radius: 20px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .team-member:hover {
        transform: translateY(-5px);
        border-color: rgba(59, 130, 246, 0.2);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .member-photo {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 4px solid white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .member-photo i {
        font-size: 3rem;
        color: #64748b;
    }
    
    .member-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .member-name {
        font-size: 1.3rem;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 8px;
    }

    .member-role {
        font-size: 1rem;
        color: #64748b;
        font-weight: 500;
        margin-bottom: 15px;
    }

    .member-description {
        font-size: 0.9rem;
        color: #475569;
        line-height: 1.5;
    }

    /* Vision Section */
    .vision-section {
        padding: 60px 0;
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        color: white;
        text-align: center;
        border-radius: 15px;
    }

    .vision-quote {
        font-size: 1.8rem;
        font-weight: 300;
        font-style: italic;
        max-width: 800px;
        margin: 0 auto;
        line-height: 1.6;
        position: relative;
    }

    .vision-quote::before,
    .vision-quote::after {
        content: \'"\';
        font-size: 4rem;
        position: absolute;
        top: -20px;
        opacity: 0.3;
        font-family: serif;
    }

    .vision-quote::before { left: -30px; }
    .vision-quote::after { right: -30px; }
</style>
';
include 'includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title"><i class="fas fa-pills me-3"></i>MediVault</h1>
                <p class="hero-subtitle">Smart Pharmacy Management System</p>
                <p class="hero-description">
                    Revolutionizing pharmacy operations with intelligent technology, streamlined workflows, and enhanced patient care through our comprehensive digital solution.
                </p>
            </div>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="mission-section">
        <div class="container">
            <h2 class="section-title">The MediVault Vision</h2>
            <div class="mission-content">
                <p class="mission-text">
                    Founded with a passion for healthcare excellence, <strong>MediVault AI</strong> is more than just a management script. It is an intelligent infrastructure designed to help local pharmacies compete with global giants through automated pricing, real-time inventory sync, and deep profit analytics.
                </p>
                <p class="mission-text">
                    We believe that every pharmacist should focus on patients, not paperwork. Our mission is to digitize the backbone of healthcare logistics, one pharmacy at a time.
                </p>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 class="section-title">Engineering Excellence</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-microchip"></i></div>
                    <h3 class="feature-title">Batch-Level SKUs</h3>
                    <p class="feature-description">Every single bottle and strip is tracked with high-granularity SKUs for 100% accurate expiry and stock audits.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h3 class="feature-title">Profit Matrices</h3>
                    <p class="feature-description">Real-time margin calculation that tracks your cost prices vs. selling prices for absolute financial clarity.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
                    <h3 class="feature-title">Multi-Role RBAC</h3>
                    <p class="feature-description">Secure access control ensuring that staff can perform billing while admins manage the deep internals of the store.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section mt-5">
        <div class="container">
            <h2 class="section-title">Our Founders & Developers</h2>
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-photo"><i class="fas fa-code"></i></div>
                    <h3 class="member-name">Swaroop Lenka</h3>
                    <p class="member-role">Lead Database Architect</p>
                    <p class="member-description">Expert in MySQL optimization and relational data integrity for high-volume inventory systems.</p>
                    <p class="mt-2 text-primary small"><i class="fas fa-phone-alt me-1"></i> +91 9136577472</p>
                </div>
                <div class="team-member">
                    <div class="member-photo"><i class="fas fa-server"></i></div>
                    <h3 class="member-name">Mihir Kulkarni</h3>
                    <p class="member-role">Full-Stack PHP Developer</p>
                    <p class="member-description">The mastermind behind core inventory matrix logic and seamless backend-frontend integration.</p>
                    <p class="mt-2 text-primary small"><i class="fas fa-phone-alt me-1"></i> +91 8208784552</p>
                </div>
                <div class="team-member">
                    <div class="member-photo"><i class="fas fa-chart-pie"></i></div>
                    <h3 class="member-name">Aryan Kulkarni</h3>
                    <p class="member-role">Analytics & UI Specialist</p>
                    <p class="member-description">Focuses on building high-fidelity visual reports and ensuring a premium, user-friendly SaaS dashboard experience.</p>
                </div>
                <div class="team-member">
                    <div class="member-photo"><i class="fas fa-shield-halved"></i></div>
                    <h3 class="member-name">Harsh Makde</h3>
                    <p class="member-role">Security & Auth Engineer</p>
                    <p class="member-description">Bridges the gap between data and security, overseeing the RBAC and staff management logic.</p>
                </div>
                <div class="team-member">
                    <div class="member-photo"><i class="fas fa-network-wired"></i></div>
                    <h3 class="member-name">Madhur Nichal</h3>
                    <p class="member-role">Core Systems Orchestrator</p>
                    <p class="member-description">Specializes in secure routing, API architecture, and overall system scalability.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Vision Section -->
    <section class="vision-section mt-5">
        <div class="container">
            <p class="vision-quote">
                We envision a future where every pharmacy operates with precision, efficiency, and excellence, powered by intelligent technology that puts patient care first.
            </p>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
