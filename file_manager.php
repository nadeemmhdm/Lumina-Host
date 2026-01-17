<?php
require 'config.php';
require 'includes/auth_check.php';

$username = $_SESSION['username'];
$site_name = $_GET['site'] ?? '';

// Check Site Ownership
$stmt = $pdo->prepare("SELECT * FROM user_sites WHERE user_id = ? AND subdomain = ?");
$stmt->execute([$_SESSION['user_id'], $site_name]);
$site = $stmt->fetch();

if (!$site) {
    header("Location: dashboard.php");
    exit;
}

$user_root = __DIR__ . "/users/" . $site_name;
if (!file_exists($user_root))
    mkdir($user_root, 0777, true);

$current_rel_path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$show_trash = isset($_GET['view']) && $_GET['view'] === 'trash';

// Path Security
if (strpos($current_rel_path, '..') !== false)
    $current_rel_path = '';
$current_abs_path = $user_root . ($current_rel_path ? '/' . $current_rel_path : '');

if ($show_trash) {
    $current_abs_path = $user_root . '/.trash';
    if (!file_exists($current_abs_path))
        mkdir($current_abs_path, 0777, true);
    $current_rel_path = '.trash';
} else {
    if ($current_rel_path === '.trash' || strpos($current_rel_path, '.trash/') === 0) {
        $current_rel_path = '';
        $current_abs_path = $user_root;
    }
}

if (!is_dir($current_abs_path)) {
    $current_rel_path = '';
    $current_abs_path = $user_root;
}

$items = scandir($current_abs_path);
$folders = [];
$files = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..')
        continue;
    if (!$show_trash && $item === '.trash')
        continue;

    $path = $current_abs_path . '/' . $item;
    if (is_dir($path))
        $folders[] = $item;
    else
        $files[] = $item;
}

$stmt = $pdo->prepare("SELECT theme_preference FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_theme = $stmt->fetchColumn() ?: 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($user_theme); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .modal {
            display: none !important;
        }

        .modal.active {
            display: flex !important;
        }

        .toolbar-container {
            display: flex;
            align-items: center;
            background: var(--bg-card);
            padding: 5px;
            border-radius: 12px;
            border: 1px solid var(--border);
            gap: 5px;
            flex-wrap: wrap;
        }

        .tool-btn {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: var(--text-muted);
            transition: all 0.2s;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .tool-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
        }

        .tool-btn.active {
            color: var(--primary);
            background: rgba(81, 207, 102, 0.1);
        }

        .tool-btn-primary {
            background: var(--primary);
            color: #fff !important;
            padding: 0 15px;
            width: auto;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .tool-btn-primary:hover {
            background: var(--primary-hover);
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0 10px;
            height: 36px;
            width: 200px;
        }

        .search-bar input {
            background: none;
            border: none;
            color: var(--text-main);
            outline: none;
            width: 100%;
            margin-left: 5px;
        }

        /* Toast Notification */
        #toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            color: var(--text-main);
        }

        .toast.success {
            border-left: 4px solid var(--primary);
        }

        .toast.error {
            border-left: 4px solid var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .upload-options {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .upload-opt-card {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }

        .upload-opt-card:hover {
            border-color: var(--primary);
            background: rgba(81, 207, 102, 0.05);
        }

        .filter-chip {
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 12px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            cursor: pointer;
            color: var(--text-muted);
        }

        .filter-chip.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .bxs-star {
            color: #fdda0d;
        }
    </style>
</head>

<body>

    <div class="dashboard-layout">
        <aside class="sidebar">
            <a href="index.php" class="logo"
                style="display:block; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-main); font-weight: bold; font-size: 1.2rem;">
                <i class='bx bxs-cube-alt' style="font-size: 1.5rem; color: var(--primary);"></i> Lumina Host
            </a>

            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class='bx bx-arrow-back'></i> Back to Dashboard</a></li>
                <li><a href="?site=<?php echo $site_name; ?>" class="<?php echo !$show_trash ? 'active' : ''; ?>"><i
                            class='bx bx-folder'></i> Files</a></li>
                <li><a href="?site=<?php echo $site_name; ?>&view=trash"
                        class="<?php echo $show_trash ? 'active' : ''; ?>" style="color: var(--danger);"><i
                            class='bx bx-trash'></i> Recycle Bin</a></li>
                <li><a href="preview.php?site=<?php echo $site_name; ?>" target="_blank"><i class='bx bx-show'></i> Live
                        Preview</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px;">
                <div style="display:flex; align-items:center; gap: 15px;">
                    <h2 style="margin:0;"><?php echo $show_trash ? 'Recycle Bin' : 'Files'; ?></h2>
                    <button onclick="toggleTheme()" class="tool-btn" id="theme-btn"
                        title="Toggle Theme (System/Light/Dark)">
                        <i class='bx bx-desktop'></i> <!-- Default icon, updated by JS -->
                    </button>

                    <!-- Search -->
                    <div class="search-bar">
                        <i class='bx bx-search' style="color: var(--text-muted);"></i>
                        <input type="text" id="file-search" placeholder="Search files..." onkeyup="filterFiles()">
                    </div>
                </div>

                <div class="toolbar-container">
                    <!-- Bulk Actions -->
                    <div id="bulk-actions" style="display:none; display: flex; gap: 5px;">
                        <button onclick="bulkDownload()" class="tool-btn" title="Download ZIP"><i
                                class='bx bx-download'></i></button>
                        <button onclick="bulkDelete()" class="tool-btn" style="color: var(--danger);"
                            title="Delete Selected"><i class='bx bx-trash'></i></button>
                        <div style="width: 1px; height: 20px; background: var(--border); margin: 0 5px;"></div>
                    </div>

                    <button onclick="location.reload()" class="tool-btn" title="Refresh"><i
                            class='bx bx-refresh'></i></button>

                    <?php if (!$show_trash): ?>
                        <div style="width: 1px; height: 20px; background: var(--border); margin: 0 5px;"></div>

                        <button onclick="openModal('create-folder')" class="tool-btn" title="New Folder"><i
                                class='bx bx-folder-plus'></i></button>
                        <button onclick="openModal('create-file')" class="tool-btn" title="New File"><i
                                class='bx bx-file-blank'></i></button>

                        <div style="width: 1px; height: 20px; background: var(--border); margin: 0 5px;"></div>

                        <button onclick="openModal('upload-modal')" class="tool-btn tool-btn-primary">
                            <i class='bx bx-cloud-upload' style="margin-right: 5px;"></i> Upload
                        </button>
                    <?php endif; ?>
                </div>
            </header>

            <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                <span class="filter-chip active" onclick="setFilter('all', this)">All</span>
                <span class="filter-chip" onclick="setFilter('image', this)">Images</span>
                <span class="filter-chip" onclick="setFilter('video', this)">Video</span>
                <span class="filter-chip" onclick="setFilter('code', this)">Code</span>
            </div>

            <?php if (!$show_trash): ?>
                <div class="breadcrumb" style="margin-bottom: 20px;">
                    <a href="?site=<?php echo $site_name; ?>"><i class='bx bx-home'></i> Root</a>
                    <?php
                    if ($current_rel_path) {
                        $parts = explode('/', $current_rel_path);
                        $build = '';
                        foreach ($parts as $part) {
                            $build .= '/' . $part;
                            echo ' / <a href="?site=' . $site_name . '&path=' . trim($build, '/') . '">' . $part . '</a>';
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (!$show_trash && (count($files) > 0 || count($folders) > 0)): ?>
                <div style="padding: 10px; border-bottom: 1px solid var(--border); display:flex; align-items:center;">
                    <div class="custom-checkbox" onclick="toggleSelectAll()" id="select-all-cb"><i class='bx bx-check'></i>
                    </div>
                    <span style="font-size: 0.9rem; color: var(--text-muted); margin-left: 10px; user-select: none;">Select
                        All</span>
                </div>
            <?php endif; ?>

            <div class="file-list" id="file-container">
                <?php if ($current_rel_path && !$show_trash): ?>
                    <div class="file-item">
                        <div style="width: 30px;"
                            onclick="window.location.href='?site=<?php echo $site_name; ?>&path=<?php echo dirname($current_rel_path) == '.' ? '' : dirname($current_rel_path); ?>'">
                        </div>
                        <span class="file-icon"><i class='bx bx-subdirectory-left'></i></span>
                        <span
                            onclick="window.location.href='?site=<?php echo $site_name; ?>&path=<?php echo dirname($current_rel_path) == '.' ? '' : dirname($current_rel_path); ?>'">..</span>
                    </div>
                <?php endif; ?>

                <?php foreach ($folders as $folder): ?>
                    <div class="file-item item-row" data-name="<?php echo strtolower($folder); ?>" data-type="folder"
                        data-path="<?php echo ($current_rel_path ? $current_rel_path . '/' : '') . $folder; ?>">
                        <?php if (!$show_trash): ?>
                            <div class="custom-checkbox" onclick="toggleSelect(this, event)"><i class='bx bx-check'></i></div>
                        <?php endif; ?>

                        <span class="file-icon"><i class='bx bxs-folder'></i></span>
                        <a href="?site=<?php echo $site_name; ?>&path=<?php echo $current_rel_path ? $current_rel_path . '/' . $folder : $folder; ?>"
                            style="color: inherit; text-decoration: none; flex-grow: 1;">
                            <?php echo $folder; ?>
                        </a>

                        <?php if (!$show_trash): ?>
                            <!-- Actions Menu -->
                            <div style="display:flex; gap: 10px; align-items: center;">
                                <i class='bx bx-trash' style="cursor: pointer; color: var(--danger); opacity: 0.7;"
                                    onclick="deleteItem('<?php echo ($current_rel_path ? $current_rel_path . '/' : '') . $folder; ?>')"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($files as $file): ?>
                    <?php
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $icon = 'bx-file';
                    $cat = 'other';
                    if (in_array($ext, ['php', 'html', 'js', 'css', 'sql'])) {
                        $icon = 'bxs-file-code';
                        $cat = 'code';
                    }
                    if (in_array($ext, ['jpg', 'png', 'jpeg', 'gif', 'svg'])) {
                        $icon = 'bxs-image';
                        $cat = 'image';
                    }
                    if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                        $icon = 'bxs-video';
                        $cat = 'video';
                    }
                    if ($ext == 'zip')
                        $icon = 'bxs-file-archive';
                    if ($ext == 'pdf') {
                        $icon = 'bxs-file-pdf';
                        $cat = 'doc';
                    }

                    $full_path = ($current_rel_path ? $current_rel_path . '/' : '') . $file;
                    $edit_link = "editor.php?site=$site_name&file=" . urlencode($full_path);
                    $file_url = "users/" . $site_name . "/" . $full_path;

                    $preview_attr = "";
                    if (in_array($ext, ['jpg', 'png', 'jpeg', 'gif', 'svg', 'mp4', 'webm', 'pdf'])) {
                        $preview_attr = "onclick=\"previewMedia('$file_url', '$ext'); event.preventDefault();\"";
                    }
                    ?>
                    <div class="file-item item-row" data-name="<?php echo strtolower($file); ?>"
                        data-type="<?php echo $cat; ?>" data-path="<?php echo $full_path; ?>">
                        <?php if (!$show_trash): ?>
                            <div class="custom-checkbox" onclick="toggleSelect(this, event)"><i class='bx bx-check'></i></div>
                        <?php endif; ?>

                        <span class="file-icon"><i class='bx <?php echo $icon; ?>'></i></span>

                        <a href="<?php echo $edit_link; ?>" <?php echo $preview_attr; ?>
                            style="color: inherit; text-decoration: none; flex-grow: 1;">
                            <?php echo $file; ?>
                        </a>

                        <?php if (!$show_trash): ?>
                            <div style="display:flex; gap: 10px; align-items:center;">
                                <i class='bx bx-link' onclick="copyUrl('<?php echo $file_url; ?>')" title="Copy Link"
                                    style="cursor: pointer; opacity: 0.5;"></i>
                                <i class='bx bx-star' onclick="toggleStar(this)" title="Star"
                                    style="cursor: pointer; opacity: 0.5;"></i>
                                <i class='bx bx-trash' style="cursor: pointer; color: var(--danger); opacity: 0.7;"
                                    onclick="deleteItem('<?php echo $full_path; ?>')"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($folders) && empty($files)): ?>
                    <div class="empty-state" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        <i class='bx bx-folder-open' style="font-size: 3rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <br>Empty Directory
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <div id="upload-modal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <h3>Upload Manager</h3>
            <div class="upload-options">
                <div class="upload-opt-card" onclick="document.getElementById('file-input').click()">
                    <i class='bx bx-file'></i><span>Files</span>
                </div>
                <div class="upload-opt-card" onclick="document.getElementById('folder-input').click()">
                    <i class='bx bx-folder'></i><span>Folder</span>
                </div>
                <div class="upload-opt-card" onclick="document.getElementById('zip-input').click()">
                    <i class='bx bxs-file-archive'></i><span>ZIP Extract</span>
                </div>
            </div>
            <input type="file" id="file-input" multiple style="display: none;"
                onchange="handleFiles(this.files, 'file')">
            <input type="file" id="folder-input" webkitdirectory directory multiple style="display: none;"
                onchange="handleFiles(this.files, 'folder')">
            <input type="file" id="zip-input" accept=".zip" style="display: none;"
                onchange="handleFiles(this.files, 'zip')">

            <div id="upload-progress" style="margin-top: 15px; max-height: 150px; overflow-y: auto;"></div>
            <button onclick="closeModal('upload-modal')" class="btn btn-outline"
                style="width: 100%; margin-top: 10px;">Close</button>
        </div>
    </div>

    <!-- Other Modals (Create, Media) -->
    <div id="create-file" class="modal">
        <div class="modal-content">
            <h3>New File</h3>
            <input type="text" id="new-filename" class="form-control" placeholder="index.php">
            <button onclick="createFile()" class="btn btn-primary">Create</button>
            <button onclick="closeModal('create-file')" class="btn btn-outline">Close</button>
        </div>
    </div>

    <div id="create-folder" class="modal">
        <div class="modal-content">
            <h3>New Folder</h3>
            <input type="text" id="new-foldername" class="form-control" placeholder="images">
            <button onclick="createFolder()" class="btn btn-primary">Create</button>
            <button onclick="closeModal('create-folder')" class="btn btn-outline">Close</button>
        </div>
    </div>

    <div id="media-modal" class="modal media-preview-modal" onclick="closeModal('media-modal')">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div id="media-container"></div>
        </div>
        <button class="btn"
            style="position: absolute; top: 20px; right: 20px; color: white; background: rgba(0,0,0,0.5);"
            onclick="closeModal('media-modal')">&times;</button>
    </div>

    <div id="toast-container"></div>

    <script>
        // --- CONSTANTS ---
        const currentSite = '<?php echo $site_name; ?>';
        const currentPath = '<?php echo $current_rel_path; ?>';
        const csrfToken = "<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>";
        let selectedItems = [];
        let themeState = ['system', 'light', 'dark'];
        let currentThemeIndex = 0; // default system, logic below

        // --- INIT ---
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            // Long Press Logic
            let pressTimer;
            document.querySelectorAll('.file-item').forEach(item => {
                item.addEventListener('touchstart', (e) => {
                    pressTimer = setTimeout(() => {
                        const cb = item.querySelector('.custom-checkbox');
                        if (cb) toggleSelect(cb, e);
                    }, 800);
                });
                item.addEventListener('touchend', () => clearTimeout(pressTimer));
            });
        });

        // --- THEME LOGIC ---
        function initTheme() {
            const stored = localStorage.getItem('theme_pref') || 'system';
            const root = document.documentElement;
            // Map index
            if (stored === 'light') currentThemeIndex = 1;
            else if (stored === 'dark') currentThemeIndex = 2;
            else currentThemeIndex = 0;

            applyTheme(stored);
        }

        function toggleTheme() {
            currentThemeIndex = (currentThemeIndex + 1) % 3;
            const modes = ['system', 'light', 'dark'];
            const mode = modes[currentThemeIndex];
            localStorage.setItem('theme_pref', mode);
            applyTheme(mode);
        }

        function applyTheme(mode) {
            const btn = document.getElementById('theme-btn');
            const root = document.documentElement;
            let actual = mode;
            let icon = 'bx-desktop';

            if (mode === 'system') {
                actual = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                icon = 'bx-desktop';
            } else if (mode === 'light') {
                icon = 'bx-sun';
            } else {
                icon = 'bx-moon';
            }

            root.setAttribute('data-theme', actual);
            btn.innerHTML = `<i class='bx ${icon}'></i>`;
        }


        // --- TOASTS ---
        function showToast(msg, type = 'success') {
            const con = document.getElementById('toast-container');
            const el = document.createElement('div');
            el.className = `toast ${type}`;
            el.innerHTML = `<i class='bx ${type == 'success' ? 'bx-check-circle' : 'bx-error'}'></i> ${msg}`;
            con.appendChild(el);
            setTimeout(() => el.remove(), 3000);
        }

        // --- FILTER & SEARCH ---
        function filterFiles() {
            const query = document.getElementById('file-search').value.toLowerCase();
            document.querySelectorAll('.file-item.item-row').forEach(row => {
                const name = row.dataset.name;
                if (name.includes(query)) row.style.display = 'flex';
                else row.style.display = 'none';
            });
        }

        function setFilter(type, btn) {
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('.file-item.item-row').forEach(row => {
                if (type === 'all' || row.dataset.type === type) row.style.display = 'flex';
                else row.style.display = 'none';
            });
        }

        // --- ACTIONS ---
        function toggleSelect(el, e) {
            e.stopPropagation();
            el.classList.toggle('checked');
            el.parentElement.classList.toggle('selected');

            const sel = document.querySelectorAll('.item-row.selected');
            selectedItems = Array.from(sel).map(r => r.dataset.path);

            const bar = document.getElementById('bulk-actions');
            bar.style.display = selectedItems.length > 0 ? 'flex' : 'none';
        }

        function toggleSelectAll() {
            const master = document.getElementById('select-all-cb');
            master.classList.toggle('checked');
            const checked = master.classList.contains('checked');
            document.querySelectorAll('.item-row .custom-checkbox').forEach(cb => {
                if (checked != cb.classList.contains('checked')) {
                    cb.click(); // Trigger individual toggle to sync state
                }
            });
        }

        async function bulkDelete() {
            if (!confirm(`Delete ${selectedItems.length} items?`)) return;
            for (let path of selectedItems) await postAction({ action: 'delete', path: path });
            showToast('Deleted selected items');
            setTimeout(() => location.reload(), 1000);
        }

        async function bulkDownload() {
            // Need a backend logic to ZIP selected
            showToast('Preparing ZIP...', 'success');
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'file_actions.php';

            const act = document.createElement('input'); act.type = 'hidden'; act.name = 'action'; act.value = 'bulk_download';
            form.appendChild(act);

            const st = document.createElement('input'); st.type = 'hidden'; st.name = 'site'; st.value = currentSite;
            form.appendChild(st);

            // Need to pass array
            selectedItems.forEach(p => {
                const i = document.createElement('input'); i.type = 'hidden'; i.name = 'paths[]'; i.value = p;
                form.appendChild(i);
            });

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // --- COPY URL ---
        function copyUrl(path) {
            const url = window.location.origin + window.location.pathname.replace('file_manager.php', '') + path;
            navigator.clipboard.writeText(url).then(() => showToast('URL Copied!'));
        }

        function toggleStar(icon) {
            icon.classList.toggle('bxs-star');
            // Save to local storage for persistence on this browser (or DB if endpoint exists)
        }

        // --- STANDARD OPERATIONS (Create, Delete, Upload) ---
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }

        async function createFolder() {
            const name = document.getElementById('new-foldername').value;
            if (name) postAction({ action: 'create_folder', name: name, path: currentPath }).then(res => {
                if (res.status === 'success') location.reload(); else showToast(res.message, 'error');
            });
        }
        async function createFile() {
            const name = document.getElementById('new-filename').value;
            if (name) postAction({ action: 'create_file', name: name, path: currentPath }).then(res => {
                if (res.status === 'success') location.reload(); else showToast(res.message, 'error');
            });
        }
        async function deleteItem(path) {
            if (confirm('Recycle Bin?')) postAction({ action: 'delete', path: path }).then(res => {
                if (res.status === 'success') location.reload(); else showToast(res.message, 'error');
            });
        }

        // UPLOAD logic same as before but using toast notifications
        async function handleFiles(files, type) {
            if (!files.length) return;
            const p = document.getElementById('upload-progress');
            p.innerHTML = `<div style="color:var(--primary);"><i class='bx bx-loader bx-spin'></i> Uploading...</div>`;

            const fd = new FormData();
            fd.append('action', 'upload');
            fd.append('site', currentSite);
            fd.append('path', currentPath);
            fd.append('csrf_token', csrfToken);

            for (let i = 0; i < files.length; i++) {
                fd.append('files[]', files[i]);
                if (type === 'folder' && files[i].webkitRelativePath) fd.append('paths[]', files[i].webkitRelativePath);
            }
            if (type === 'folder') fd.append('is_folder_upload', 'true');

            try {
                const res = await fetch('file_actions.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.status === 'success') {
                    showToast('Upload Complete!');
                    setTimeout(() => location.reload(), 1000);
                } else showToast(json.message, 'error');
            } catch (e) { showToast('Connection failed', 'error'); }
        }

        async function postAction(data) {
            const fd = new FormData();
            for (let k in data) fd.append(k, data[k]);
            fd.append('site', currentSite);
            fd.append('csrf_token', csrfToken);
            return fetch('file_actions.php', { method: 'POST', body: fd }).then(r => r.json());
        }

        function previewMedia(url, ext) {
            const c = document.getElementById('media-container'); c.innerHTML = '';
            if (['jpg', 'png', 'gif', 'svg'].includes(ext)) c.innerHTML = `<img src="${url}">`;
            else if (['mp4', 'webm'].includes(ext)) c.innerHTML = `<video src="${url}" controls autoplay></video>`;
            else if (ext === 'pdf') c.innerHTML = `<iframe src="${url}"></iframe>`;
            openModal('media-modal');
        }
    </script>
</body>

</html>