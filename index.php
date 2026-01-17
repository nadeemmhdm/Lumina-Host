<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lumina Host - Premium Web Hosting</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>

    <header>
        <div class="container">
            <nav>
                <a href="index.php" class="logo">
                    <i class="fas fa-cube"></i> Lumina Host
                </a>
                <div class="nav-links">
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How it Works</a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                    <?php else: ?>
                        <a href="login.php">Login</a>
                        <a href="signup.php" class="btn btn-primary">Get Started</a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container hero-content">
            <h1>Create & Host Websites Instantly</h1>
            <p>The most advanced browser-based web hosting platform. Code, deploy, and manage your projects with our
                powerful cloud tools. 1GB Storage included.</p>
            <div class="hero-buttons">
                <a href="signup.php" class="btn btn-primary">Start Building Free</a>
                <a href="#features" class="btn btn-outline">Explore Features</a>
            </div>
        </div>
    </section>

    <section id="features" class="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Lumina Host?</h2>
                <p>Everything you need to build amazing websites right in your browser.</p>
            </div>
            <div class="grid">
                <div class="card">
                    <div class="card-icon"><i class="fas fa-code"></i></div>
                    <h3>Live Code Editor</h3>
                    <p>Write HTML, CSS, JS, and PHP with syntax highlighting and instant preview. No setup required.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-hdd"></i></div>
                    <h3>1GB Cloud Storage</h3>
                    <p>Plenty of space for your projects, assets, and databases. Manage files easily with our file
                        manager.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-globe"></i></div>
                    <h3>Custom Subdomains</h3>
                    <p>Get your own username.yourdomain.com instantly or connect your own custom domain.</p>
                </div>
                <div class="card">
                    <div class="card-icon"><i class="fas fa-database"></i></div>
                    <h3>MySQL Database</h3>
                    <p>Full database support for dynamic PHP applications. Secure and scalable.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy;
                <?php echo date('Y'); ?> Lumina Host. All rights reserved.
            </p>
        </div>
    </footer>

</body>

</html>