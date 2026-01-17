<?php
require 'config.php';

$site_name = $_GET['site'] ?? '';

// Basic sanity check on formatting
if (!preg_match('/^[a-zA-Z0-9-]+$/', $site_name)) {
    die("Invalid Site URL");
}

$file_path = "users/" . $site_name . "/";
// Ensure directory exists (or webserver will 404 naturally, but good to check)
if (!is_dir(__DIR__ . "/users/" . $site_name)) {
    die("Site not found.");
}

$url = BASE_URL . '/users/' . $site_name . '/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Preview - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        .preview-bar {
            height: 40px;
            background: #222;
            color: #fff;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            font-family: sans-serif;
            border-bottom: 1px solid #444;
        }

        .url-box {
            background: #000;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            color: #aaa;
            margin: 0 auto;
            width: 50%;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        iframe {
            width: 100%;
            height: calc(100% - 40px);
            border: none;
            background: #fff;
        }

        .back-btn {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
        }

        .action-btn {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            margin-left: 15px;
        }
    </style>
</head>

<body>
    <div class="preview-bar">
        <!-- Close the preview tab or go back? Usually close if opened in new tab -->
        <a href="javascript:window.close();" class="back-btn">&times; Close</a>
        <div class="url-box">
            <?php echo htmlspecialchars($url); ?>
        </div>
        <div>
            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" class="action-btn"><i
                    class="fas fa-external-link-alt"></i> Open New Tab</a>
        </div>
    </div>
    <iframe src="<?php echo $url; ?>"></iframe>
</body>

</html>