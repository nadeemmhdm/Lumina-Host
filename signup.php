<?php
require 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    // We treat site_name as the subdomain/folder name
    $site_name = trim($_POST['site_name']);
    // Basic sanitization for directory safety
    $site_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $site_name);

    if (empty($site_name)) {
        $error = "Site name contains invalid characters. Use letters, numbers, hyphens only.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $error = "Username or Email already exists.";
        } else {
            // Check site name availability (subdomain/folder)
            $stmt = $pdo->prepare("SELECT id FROM user_sites WHERE subdomain = ?");
            $stmt->execute([$site_name]);

            if ($stmt->rowCount() > 0) {
                $error = "Site name/domain is already taken.";
            } else {
                // Register User
                $hash = password_hash($password, PASSWORD_BCRYPT);

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $email, $hash]);
                    $user_id = $pdo->lastInsertId();

                    // Create User Site
                    $stmt = $pdo->prepare("INSERT INTO user_sites (user_id, site_name, subdomain) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $site_name, $site_name]);

                    // Create User Directory using SITE NAME (Subdomain)
                    $user_dir = __DIR__ . "/users/" . $site_name;
                    if (!file_exists($user_dir)) {
                        mkdir($user_dir, 0777, true);
                        // Create a default index.html
                        $default_index = "<h1>Welcome to " . htmlspecialchars($site_name) . "</h1><p>This is your new website!</p>";
                        file_put_contents($user_dir . "/index.html", $default_index);
                    }

                    $pdo->commit();
                    $success = "Account created successfully! <a href='login.php'>Login here</a>";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error creating account: " . $e->getMessage();
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
    <title>Sign Up - Lumina Host</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2 style="text-align:center; margin-bottom:2rem;">Create Account</h2>

            <?php if ($error): ?>
                <div
                    style="background: rgba(255, 0, 0, 0.2); color: #ff6b6b; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div
                    style="background: rgba(0, 255, 0, 0.2); color: #51cf66; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="johndoe">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="john@example.com">
                </div>
                <div class="form-group">
                    <label>Site Name (Domain)</label>
                    <input type="text" name="site_name" class="form-control" required placeholder="mysite">
                    <small style="color: var(--text-muted); display:block; margin-top:5px;">
                        Your site will be at: .../users/<strong>mysite</strong>
                    </small>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; justify-content: center;">Sign
                    Up</button>
            </form>
            <p style="text-align: center; margin-top: 1rem; color: var(--text-muted);">
                Already have an account? <a href="login.php" style="color: var(--primary);">Login</a>
            </p>
        </div>
    </div>
</body>

</html>