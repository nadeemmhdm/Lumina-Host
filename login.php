<?php
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Login by Username or Email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Init Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];

        // Fetch User's Site Name (Folder Name)
        $stmtSite = $pdo->prepare("SELECT subdomain FROM user_sites WHERE user_id = ?");
        $stmtSite->execute([$user['id']]);
        $site = $stmtSite->fetch();

        if ($site) {
            $_SESSION['site_name'] = $site['subdomain'];
        } else {
            // Fallback if something is wrong (shouldn't happen)
            $_SESSION['site_name'] = $user['username'];
        }

        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Lumina Host</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="auth-container">
        <div class="auth-card">
            <h2 style="text-align:center; margin-bottom:2rem;">Welcome Back</h2>

            <?php if ($error): ?>
                <div
                    style="background: rgba(255, 0, 0, 0.2); color: #ff6b6b; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary"
                    style="width:100%; justify-content: center;">Login</button>
            </form>
            <p style="text-align: center; margin-top: 1rem; color: var(--text-muted);">
                Don't have an account? <a href="signup.php" style="color: var(--primary);">Sign Up</a>
            </p>
        </div>
    </div>
</body>

</html>