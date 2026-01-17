<?php
// config.php

$db_host = ''; 
$db_user = '';
$db_pass = '';
$db_name = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Global Constants
define('MAX_STORAGE', 1024 * 1024 * 1024); // 1GB
define('APP_NAME', 'Lumina Host');

// The public URL where the app is hosted
define('BASE_URL', 'https://1bc123.gamer.gd/Web/Lumina%20Host');

// Start Session
session_start();
?>
