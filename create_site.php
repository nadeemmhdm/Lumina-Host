<?php
require 'config.php';
require 'includes/auth_check.php';

$error = '';
$success = '';

// Check current site count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_sites WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$site_count = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($site_count >= 3) {
        $error = "You have reached the limit of 3 websites.";
    } else {
        $site_name = trim($_POST['site_name']);
        // Sanitize for folder name (only alphanumeric and hyphens)
        $subdomain = preg_replace('/[^a-zA-Z0-9-]/', '', $site_name); // simpler subdomain logic

        if (strlen($subdomain) < 3) {
            $error = "Site name must be at least 3 characters.";
        } else {
            // Check availability
            $stmt = $pdo->prepare("SELECT id FROM user_sites WHERE subdomain = ?");
            $stmt->execute([$subdomain]);

            if ($stmt->rowCount() > 0) {
                $error = "This site name is already taken.";
            } else {
                try {
                    // Create in DB
                    $stmt = $pdo->prepare("INSERT INTO user_sites (user_id, site_name, subdomain) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $site_name, $subdomain]);

                    // Create Folder
                    $site_dir = __DIR__ . "/users/" . $subdomain;
                    if (!file_exists($site_dir)) {
                        mkdir($site_dir, 0777, true);
                        file_put_contents($site_dir . "/index.html", "<h1>Welcome to " . htmlspecialchars($site_name) . "</h1>");
                    }

                    header("Location: dashboard.php");
                    exit;
                } catch (Exception $e) {
                    $error = "Error creating website: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Website - WebHost Studio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2>Create New Website</h2>
            <p style="color: var(--text-muted); margin-bottom: 20px;">
                You have used
                <?php echo $site_count; ?>/3 slots.
            </p>

            <?php if ($error): ?>
                <div
                    style="background: rgba(255, 0, 0, 0.2); color: #ff6b6b; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($site_count >= 3): ?>
                <div class="alert">
                    You have reached the maximum number of websites (3). Please delete one to create a new one.
                </div>
                <a href="dashboard.php" class="btn btn-outline"
                    style="width: 100%; justify-content: center; margin-top: 10px;">Back to Dashboard</a>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Website Name</label>
                        <input type="text" name="site_name" class="form-control" placeholder="My Awesome Site" required>
                        <small style="display:block; margin-top:5px; color:var(--text-muted);">
                            This will also be your subdomain (e.g., users/<b>myawesomesite</b>)
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Create
                        Website</button>
                    <a href="dashboard.php" class="btn btn-outline"
                        style="width: 100%; justify-content: center; margin-top: 10px; text-align: center;">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>