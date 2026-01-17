<?php
require 'config.php';
require 'includes/auth_check.php';
require 'includes/csrf.php'; // Include CSRF

$username = $_SESSION['username'];
$site_name = $_GET['site'] ?? '';
$file_path_rel = $_GET['file'] ?? '';

// ... (Existing validations) ...
if (!$site_name || !$file_path_rel)
    die("Missing parameters.");
$stmt = $pdo->prepare("SELECT id FROM user_sites WHERE user_id = ? AND subdomain = ?");
$stmt->execute([$_SESSION['user_id'], $site_name]);
if ($stmt->rowCount() == 0)
    die("Access Denied.");

$base_dir = realpath(__DIR__ . "/users/" . $site_name);
$real_path = realpath($base_dir . '/' . $file_path_rel);
if (!$real_path || strpos($real_path, $base_dir) !== 0 || !file_exists($real_path))
    die("Invalid file.");

$content = file_get_contents($real_path);
$ext = pathinfo($real_path, PATHINFO_EXTENSION);
$mode = 'htmlmixed';
if ($ext == 'js')
    $mode = 'javascript';
if ($ext == 'css')
    $mode = 'css';
if ($ext == 'php')
    $mode = 'application/x-httpd-php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Editor - <?php echo htmlspecialchars(basename($file_path_rel)); ?></title>
    <!-- CodeMirror Assets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/dracula.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            overflow: hidden;
            background: #282a36;
        }

        .editor-layout {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .editor-toolbar {
            background: #1e1f29;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #44475a;
        }

        .file-info {
            color: #f8f8f2;
            font-family: monospace;
        }

        .CodeMirror {
            flex-grow: 1;
            height: auto;
            font-size: 16px;
            line-height: 1.6;
        }

        /* Auto-save indicators */
        .status-pill {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-right: 10px;
        }

        .status-saved {
            background: rgba(81, 207, 102, 0.2);
            color: #51cf66;
        }

        .status-saving {
            background: rgba(255, 212, 59, 0.2);
            color: #ffd43b;
        }

        .status-error {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }

        .versions-dropdown {
            position: relative;
            display: inline-block;
        }

        .versions-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1;
            border-radius: 5px;
            overflow: hidden;
        }

        .versions-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 0.9rem;
        }

        .versions-content a:hover {
            background-color: #f1f1f1;
        }

        .versions-dropdown:hover .versions-content {
            display: block;
        }
    </style>
</head>

<body>
    <div class="editor-layout">
        <div class="editor-toolbar">
            <div class="file-info">
                <a href="file_manager.php?site=<?php echo $site_name; ?>&path=<?php echo dirname($file_path_rel) == '.' ? '' : dirname($file_path_rel); ?>"
                    style="color: #6272a4; text-decoration: none; margin-right: 15px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <i class="fas fa-file-code"></i> <?php echo htmlspecialchars($file_path_rel); ?>
            </div>

            <div style="display: flex; align-items: center;">
                <span id="auto-save-status" class="status-pill status-saved"><i class="fas fa-check"></i> Saved</span>

                <div class="versions-dropdown">
                    <button class="btn btn-outline" style="font-size: 0.8rem; margin-right: 10px;">History <i
                            class="fas fa-caret-down"></i></button>
                    <div class="versions-content" id="version-list">
                        <a href="#">Loading...</a>
                    </div>
                </div>

                <button onclick="saveFile(true)" class="btn btn-primary" style="font-size: 0.9rem;">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
        <textarea id="code-editor"><?php echo htmlspecialchars($content); ?></textarea>
    </div>

    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("code-editor"), {
            mode: "<?php echo $mode; ?>",
            theme: "dracula",
            lineNumbers: true,
            autoCloseTags: true,
            matchBrackets: true
        });

        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
        let lastParams = editor.getValue();
        let autoSaveTimer = null;

        // Auto-Save Logic (Interval)
        setInterval(() => {
            if (editor.getValue() !== lastParams) {
                saveFile(false); // Silent save
            }
        }, 15000); // Check every 15s

        // Save Function
        async function saveFile(manual) {
            const content = editor.getValue();
            const statusEl = document.getElementById('auto-save-status');

            // UI Update
            statusEl.className = 'status-pill status-saving';
            statusEl.innerHTML = '<i class="fas fa-sync fa-spin"></i> Saving...';

            const formData = new FormData();
            formData.append('action', 'save_content');
            formData.append('site', '<?php echo $site_name; ?>');
            formData.append('path', "<?php echo $file_path_rel; ?>");
            formData.append('content', content);
            formData.append('csrf_token', csrfToken); // CSRF

            try {
                const res = await fetch('file_actions.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.status === 'success') {
                    lastParams = content;
                    statusEl.className = 'status-pill status-saved';
                    statusEl.innerHTML = '<i class="fas fa-check"></i> Saved';
                    if (manual) {
                        // Visual feedback for manual save
                        statusEl.style.fontWeight = 'bold';
                        setTimeout(() => statusEl.style.fontWeight = 'normal', 1000);
                        loadVersions(); // Refresh history
                    }
                } else {
                    statusEl.className = 'status-pill status-error';
                    statusEl.innerHTML = '<i class="fas fa-times"></i> Error';
                    alert(data.message);
                }
            } catch (e) {
                statusEl.className = 'status-pill status-error';
                statusEl.innerHTML = '<i class="fas fa-wifi"></i> Offline';
            }
        }

        // Load Versions
        async function loadVersions() {
            const res = await fetch(`file_actions.php?site=<?php echo $site_name; ?>&action=get_versions&path=<?php echo urlencode($file_path_rel); ?>`);
            const data = await res.json();
            const list = document.getElementById('version-list');
            list.innerHTML = '';

            if (data.length === 0) {
                list.innerHTML = '<a href="#">No history</a>';
                return;
            }

            data.forEach(v => {
                const date = new Date(v.created_at).toLocaleString();
                const link = document.createElement('a');
                link.href = '#';
                link.innerText = date + ' (' + v.size + 'b)';
                link.onclick = () => restoreVersion(v.id);
                list.appendChild(link);
            });
        }

        async function restoreVersion(id) {
            if (!confirm('Restore this version? Current unsaved changes will be lost.')) return;

            const formData = new FormData();
            formData.append('action', 'restore_version');
            formData.append('version_id', id);
            formData.append('site', '<?php echo $site_name; ?>');
            formData.append('csrf_token', csrfToken);

            const res = await fetch('file_actions.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success') location.reload();
        }

        // Lock Heartbeat
        setInterval(() => {
            const formData = new FormData();
            formData.append('action', 'lock_file');
            formData.append('site', '<?php echo $site_name; ?>');
            formData.append('path', "<?php echo $file_path_rel; ?>");
            formData.append('csrf_token', csrfToken);
            navigator.sendBeacon('file_actions.php', formData);
        }, 30000);

        // Shortcuts
        document.addEventListener('keydown', function (e) {
            if ((window.navigator.platform.match("Mac") ? e.metaKey : e.ctrlKey) && e.keyCode == 83) {
                e.preventDefault();
                saveFile(true);
            }
        });

        // Init
        loadVersions();
    </script>
</body>

</html>