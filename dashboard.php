<?php
// dashboard.php
require 'config.php';
require 'includes/auth_check.php';
require 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch User Sites
$stmt = $pdo->prepare("SELECT * FROM user_sites WHERE user_id = ?");
$stmt->execute([$user_id]);
$sites = $stmt->fetchAll();

// Handle New Site Creation
$error = '';
if (isset($_GET['create_error'])) {
    $error = htmlspecialchars($_GET['create_error']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lumina Host</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .site-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
        }

        .site-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .site-info h3 {
            margin: 0 0 5px 0;
            font-size: 1.2rem;
        }

        .site-info a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .site-stats {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        .progress-fill.warning {
            background: var(--secondary);
        }

        .progress-fill.danger {
            background: var(--danger);
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .status-published {
            background: rgba(81, 207, 102, 0.2);
            color: #51cf66;
        }

        .status-unpublished {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <a href="index.php" class="logo" style="margin-bottom: 2rem; display: block;">
                <i class="fas fa-cube"></i> Lumina Host
            </a>
            <ul class="sidebar-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Account Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h2>Welcome, <?php echo htmlspecialchars($username); ?></h2>
                    <p style="color: var(--text-muted);">Manage your websites and files.</p>
                </div>
                <?php if (count($sites) < 3): ?>
                    <a href="create_site.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create New Site</a>
                <?php else: ?>
                    <button class="btn btn-outline" disabled style="opacity:0.6;"><i class="fas fa-ban"></i> Max Sites
                        Reached (3/3)</button>
                <?php endif; ?>
            </header>

            <?php if ($error): ?>
                <div class="alert"
                    style="background: rgba(255,0,0,0.1); color: #ff6b6b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <h3 style="margin-bottom: 1rem;">Your Websites</h3>

            <?php if (count($sites) === 0): ?>
                <div
                    style="text-align: center; padding: 3rem; background: var(--bg-card); border-radius: 12px; border: 1px dashed var(--border);">
                    <i class="fas fa-globe" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p>No websites yet. Create your first one!</p>
                    <a href="create_site.php" class="btn btn-primary" style="margin-top: 1rem;">Get Started</a>
                </div>
            <?php else: ?>
                <?php foreach ($sites as $site): ?>
                    <?php
                    $used = $site['storage_used'];
                    $limit = $site['storage_limit'];
                    $percent = min(100, ($used / $limit) * 100);
                    $colorClass = '';
                    if ($percent > 80)
                        $colorClass = 'warning';
                    if ($percent > 95)
                        $colorClass = 'danger';

                    $status = $site['status'] ?? 'published';
                    ?>
                    <div class="site-card">
                        <div class="site-info" style="flex: 1; margin-right: 20px;">
                            <div style="display: flex; align-items: center;">
                                <h3><?php echo htmlspecialchars($site['site_name']); ?></h3>
                                <span
                                    class="status-badge <?php echo $status == 'published' ? 'status-published' : 'status-unpublished'; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                            <a href="http://<?php echo $site['subdomain']; ?>.repl.co" target="_blank">
                                <i class="fas fa-external-link-alt"></i>
                                <?php echo htmlspecialchars($site['subdomain']); ?>.lumina.host
                            </a>

                            <div class="site-stats">
                                Storage: <?php echo formatSize($used); ?> / <?php echo formatSize($limit); ?>
                                <div class="progress-bar">
                                    <div class="progress-fill <?php echo $colorClass; ?>"
                                        style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="site-actions" style="display: flex; gap: 10px;">
                            <a href="file_manager.php?site=<?php echo $site['subdomain']; ?>" class="btn btn-primary">
                                <i class="fas fa-folder-open"></i> Manage
                            </a>
                            <a href="settings.php?site_id=<?php echo $site['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-sliders-h"></i> Settings
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
</body>

</html>