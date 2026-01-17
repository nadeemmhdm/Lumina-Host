<?php
require 'config.php';
require 'includes/auth_check.php';

$username = $_SESSION['username'];
$site_name = $_GET['site'] ?? '';
$file_path_rel = $_GET['file'] ?? '';

if (!$site_name || !$file_path_rel) {
    die("Missing parameters.");
}

// Verify Site
$stmt = $pdo->prepare("SELECT id FROM user_sites WHERE user_id = ? AND subdomain = ?");
$stmt->execute([$_SESSION['user_id'], $site_name]);
if ($stmt->rowCount() == 0) {
    die("Access Denied.");
}

$base_dir = realpath(__DIR__ . "/users/" . $site_name);
$real_path = realpath($base_dir . '/' . $file_path_rel);

if (!$real_path || strpos($real_path, $base_dir) !== 0 || !file_exists($real_path)) {
    die("Invalid file permission.");
}

$content = file_get_contents($real_path);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Visual Editor - <?php echo htmlspecialchars(basename($file_path_rel)); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .editor-container {
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <a href="index.php" class="logo" style="display:block; margin-bottom: 2rem;">
                <i class="fas fa-cube"></i> WebHost
            </a>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-arrow-left"></i> Back</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Visual: <?php echo htmlspecialchars(basename($file_path_rel)); ?></h2>
                <div>
                    <span id="save-msg"
                        style="color: #51cf66; margin-right: 15px; opacity: 0; transition: opacity 0.3s;">Saved!</span>
                    <button onclick="saveContent()" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <a href="editor.php?site=<?php echo $site_name; ?>&file=<?php echo urlencode($file_path_rel); ?>"
                        class="btn btn-outline">Code View</a>
                </div>
            </div>

            <textarea id="rich-editor" style="height: 500px;"><?php echo htmlspecialchars($content); ?></textarea>
        </main>
    </div>

    <script>
        tinymce.init({
            selector: '#rich-editor',
            height: 600,
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
        });

        async function saveContent() {
            const content = tinymce.get('rich-editor').getContent();
            const formData = new FormData();
            formData.append('action', 'save_content');
            formData.append('site', '<?php echo $site_name; ?>');
            formData.append('path', "<?php echo $file_path_rel; ?>");
            formData.append('content', content);

            try {
                const res = await fetch('file_actions.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    const msg = document.getElementById('save-msg');
                    msg.style.opacity = 1;
                    setTimeout(() => msg.style.opacity = 0, 2000);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (e) {
                alert('Connection error');
            }
        }
    </script>
</body>

</html>