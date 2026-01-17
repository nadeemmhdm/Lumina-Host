<?php
// settings.php
require 'config.php';
require 'includes/auth_check.php';
require 'includes/csrf.php';

$user_id = $_SESSION['user_id'];
$site_id = isset($_GET['site_id']) ? (int) $_GET['site_id'] : 0;
$site = null;

if ($site_id) {
    $stmt = $pdo->prepare("SELECT * FROM user_sites WHERE id = ? AND user_id = ?");
    $stmt->execute([$site_id, $user_id]);
    $site = $stmt->fetch();
    if (!$site)
        header("Location: settings.php");
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Publish / Unpublish Logic
    if (isset($_POST['toggle_status']) && $site) {
        verify_csrf();
        $new_status = $_POST['new_status']; // 'published' or 'unpublished'
        if (in_array($new_status, ['published', 'unpublished'])) {
            $stmt = $pdo->prepare("UPDATE user_sites SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $site_id]);
            $message = "Website status updated to " . ucfirst($new_status);
            // Refresh
            header("Location: settings.php?site_id=$site_id&msg=" . urlencode($message));
            exit;
        }
    }

    // 2. Delete Website
    if (isset($_POST['delete_website']) && $site) {
        verify_csrf();
        $confirm = $_POST['confirm_site_delete'];
        if ($confirm === $site['subdomain']) {
            // Recursive delete
            function deleteDirectory($dir)
            {
                if (!file_exists($dir))
                    return true;
                if (!is_dir($dir))
                    return unlink($dir);
                foreach (scandir($dir) as $item) {
                    if ($item == '.' || $item == '..')
                        continue;
                    if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item))
                        return false;
                }
                return rmdir($dir);
            }

            $base_dir = realpath(__DIR__ . "/users/" . $site['subdomain']);
            if ($base_dir && is_dir($base_dir))
                deleteDirectory($base_dir);

            $stmt = $pdo->prepare("DELETE FROM user_sites WHERE id = ?");
            $stmt->execute([$site_id]);

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Confirmation failed. Type subdomain exactly.";
        }
    }
}

$status = $site['status'] ?? 'published';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Settings - Lumina Host</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <a href="index.php" class="logo" style="margin-bottom: 2rem; display: block;">
                <i class="fas fa-cube"></i> Lumina Host
            </a>
            <!-- Simple Sidebar -->
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <?php if ($site): ?>
                <h2>Settings for: <span
                        style="color: var(--primary);"><?php echo htmlspecialchars($site['site_name']); ?></span></h2>

                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert"
                        style="background: rgba(81, 207, 102, 0.2); color: #51cf66; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                        <?php echo htmlspecialchars($_GET['msg']); ?>
                    </div>
                <?php endif; ?>

                <!-- Status Card -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3>Website Status</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                        Control the public visibility of your website. Unpublished websites are improved but not accessible
                        to the public.
                    </p>

                    <div
                        style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-body); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                        <div>
                            <strong>Current Status: </strong>
                            <span class="<?php echo $status == 'published' ? 'status-published' : 'status-unpublished'; ?>"
                                style="padding: 2px 8px; border-radius: 4px; font-weight: bold;">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="toggle_status" value="1">
                            <?php if ($status === 'published'): ?>
                                <input type="hidden" name="new_status" value="unpublished">
                                <button type="submit" class="btn btn-outline"
                                    style="border-color: var(--danger); color: var(--danger);">
                                    <i class="fas fa-eye-slash"></i> Unpublish Website
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="new_status" value="published">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-globe"></i> Publish Website
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Delete Card -->
                <div class="card" style="border-color: var(--danger);">
                    <h3 style="color: var(--danger);">Danger Zone</h3>
                    <p>Permanently delete this website and all its files
                        (<?php echo htmlspecialchars($site['subdomain']); ?>).</p>

                    <form method="POST" onsubmit="return confirm('Are you sure? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="delete_website" value="1">
                        <input type="text" name="confirm_site_delete" class="form-control"
                            placeholder="Type subdomain to confirm" required style="border-color: var(--danger);">
                        <button type="submit" class="btn btn-primary" style="background: var(--danger);">Delete
                            Website</button>
                    </form>
                    <?php if ($error): ?>
                        <p style="color: var(--danger); margin-top: 10px;"><?php echo $error; ?></p><?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Global Account Settings (Password, etc - if no site selected) -->
                <h2>Account Settings</h2>
                <p>Select a website from the dashboard to manage its specific settings.</p>
            <?php endif; ?>
        </main>
    </div>
</body>

</html>