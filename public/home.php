<?php
require_once __DIR__ . '/../src/auth/auth_check.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/templates/header.php';
?>

<div class="video-header">
    <div class="overlay"></div>
    <video autoplay muted loop playsinline>
    <source src="videos/4770380-hd_1920_1080_30fps.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>
    <div class="content fade-in">
        <h1 class="display-4 fw-bold" data-translate-key="home_welcome">Welcome to Your Real Estate Hub</h1>
        <p class="lead" data-translate-key="home_welcome_subtitle">Manage everything from one place, seamlessly.</p>
    </div>
</div>

<div class="container features-section">
    <div class="text-center mb-5">
        <h2 class="display-5 fw-bold" data-translate-key="home_why_crm">Why Our CRM?</h2>
        <p class="text-muted fs-5" data-translate-key="home_why_crm_subtitle">A toolkit designed for peak performance and efficiency.</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-4 col-md-6">
            <div class="card p-4 text-center feature-card h-100">
                <div class="icon mb-3"><i class="fa-solid fa-users-gear"></i></div>
                <h4 class="card-title fw-bold" data-translate-key="home_feat_client_mgmt">Client Management</h4>
                <p class="card-text text-secondary" data-translate-key="home_feat_client_mgmt_desc">Keep track of all your clients, interactions, and follow-ups in one organized database.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card p-4 text-center feature-card h-100">
                <div class="icon mb-3"><i class="fa-solid fa-house-signal"></i></div>
                <h4 class="card-title fw-bold" data-translate-key="home_feat_prop_track">Property Tracking</h4>
                <p class="card-text text-secondary" data-translate-key="home_feat_prop_track_desc">Manage your property listings, viewings, and status updates with our intuitive tools.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card p-4 text-center feature-card h-100">
                <div class="icon mb-3"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                <h4 class="card-title fw-bold" data-translate-key="home_feat_analytics">Insightful Analytics</h4>
                <p class="card-text text-secondary" data-translate-key="home_feat_analytics_desc">Get detailed reports and charts to understand your performance and drive growth.</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../src/templates/footer.php';
?>