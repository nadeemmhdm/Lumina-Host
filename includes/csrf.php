<?php
// includes/csrf.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf()
{
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Allow optional skip for upload if strictly necessary, but better to enforce
        // For this system, we enforce it.
        die(json_encode(['status' => 'error', 'message' => 'CSRF validation failed. Refresh the page.']));
    }
}
?>