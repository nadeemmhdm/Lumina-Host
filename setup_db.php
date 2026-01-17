<?php
// setup_db.php
require 'config.php';

try {
    echo "Updating Database Schema...<br>";

    // Updated user_sites with status column
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        site_name VARCHAR(100) NOT NULL,
        subdomain VARCHAR(100) NOT NULL UNIQUE,
        storage_used BIGINT DEFAULT 0,
        storage_limit BIGINT DEFAULT 524288000, -- 500 MB
        status VARCHAR(20) DEFAULT 'published', -- New status field
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Modify existing columns if needed (safe alter)
    $colCheck = $pdo->query("SHOW COLUMNS FROM user_sites LIKE 'status'");
    if ($colCheck->rowCount() == 0) {
        $pdo->exec("ALTER TABLE user_sites ADD COLUMN status VARCHAR(20) DEFAULT 'published'");
    }

    // Ensure storage limit is 500MB
    $pdo->exec("ALTER TABLE user_sites MODIFY COLUMN storage_limit BIGINT DEFAULT 524288000");

    echo "✔ DB updated: Status column added & Limit set to 500MB.<br>";

} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>