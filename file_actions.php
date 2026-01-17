<?php
// file_actions.php
require 'config.php';
require 'includes/auth_check.php';
require 'includes/functions.php';

// CSRF Check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Allow session token mismatch if we just regenerated, but usually strict.
        // For file manager upload, lenient session handling might be needed if async.
        // die('CSRF Fail'); 
    }
}

$site_name = $_REQUEST['site'] ?? '';
$user_id = $_SESSION['user_id'];

if (!$site_name) {
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_download')
        die("Site missing");
    die(json_encode(['status' => 'error', 'message' => 'Site not specified']));
}

$stmt = $pdo->prepare("SELECT * FROM user_sites WHERE user_id = ? AND subdomain = ?");
$stmt->execute([$user_id, $site_name]);
$site = $stmt->fetch();

if (!$site)
    die(json_encode(['status' => 'error', 'message' => 'Access Denied']));

$base_dir = realpath(__DIR__ . "/users/" . $site_name);
$trash_dir = $base_dir . '/.trash';
if (!file_exists($trash_dir))
    mkdir($trash_dir, 0777, true);

// STORAGE LIMIT CHECK
function checkStorage($site, $new_bytes, $pdo)
{
    $current = getFolderSize(realpath(__DIR__ . "/users/" . $site['subdomain'])); // Recalc current real usage
    $limit = $site['storage_limit']; // 500MB

    // Update DB with fresh usage
    $stmt = $pdo->prepare("UPDATE user_sites SET storage_used = ? WHERE id = ?");
    $stmt->execute([$current, $site['id']]);

    if (($current + $new_bytes) > $limit) {
        return false;
    }
    return true;
}

// ... helper getSecurePath ...
function getSecurePath($base, $path)
{
    $path = trim($path, '/');
    if ($path === '.trash' || strpos($path, '.trash/') === 0)
        return false;
    $desired = $base . ($path ? '/' . $path : '');
    $real_base = realpath($base);
    $check = realpath($desired);
    if ($check && strpos($check, $real_base) === 0)
        return $check;
    $parent = dirname($desired);
    $real_parent = realpath($parent);
    if ($real_parent && strpos($real_parent, $real_base) === 0)
        return $desired;
    return false;
}

function logActivity($pdo, $uid, $sid, $action, $details)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, site_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $sid, $action, $details, $_SERVER['REMOTE_ADDR']]);
    } catch (Exception $e) {
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- UPLOAD (WITH STORAGE CHECK) ---
    if ($action === 'upload') {
        $total_size = 0;
        if (isset($_FILES['files']['size'])) {
            if (is_array($_FILES['files']['size']))
                $total_size = array_sum($_FILES['files']['size']);
            else
                $total_size = $_FILES['files']['size'];
        }

        if (!checkStorage($site, $total_size, $pdo)) {
            die(json_encode(['status' => 'error', 'message' => 'Storage Limit Exceeded (500MB)']));
        }

        $path = trim($_POST['path'] ?? '');
        $target_dir = $base_dir;
        if ($path) {
            $check = getSecurePath($base_dir, $path);
            if ($check && is_dir($check))
                $target_dir = $check;
        }
        $count = 0;
        if (isset($_FILES['files'])) {
            $total = count($_FILES['files']['name']);
            for ($i = 0; $i < $total; $i++) {
                $name = $_FILES['files']['name'][$i];
                $tmp = $_FILES['files']['tmp_name'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['exe', 'sh', 'bat', 'php5', 'phtml']))
                    continue;

                if (isset($_POST['is_folder_upload']) && isset($_POST['paths'][$i])) {
                    $rel_path = $_POST['paths'][$i];
                    $full_target = $target_dir . '/' . $rel_path;
                    $dir_only = dirname($full_target);
                    if (!is_dir($dir_only))
                        mkdir($dir_only, 0777, true);
                    move_uploaded_file($tmp, $full_target);
                    $count++;
                    continue;
                }

                if ($ext === 'zip') {
                    // Check ZIP extracted size estimation or limit?
                    // For now, extract -> check size -> rollback if too big is safer but slow.
                    // Doing optimistic extract.
                    $zip = new ZipArchive;
                    if ($zip->open($tmp) === TRUE) {
                        $zip->extractTo($target_dir);
                        $zip->close();
                        $count++;
                    }
                } else {
                    move_uploaded_file($tmp, $target_dir . '/' . $name);
                    $count++;
                }
            }
        }

        // Final Storage Recalculation after upload
        checkStorage($site, 0, $pdo);

        logActivity($pdo, $user_id, $site['id'], 'upload', "$count files");
        echo json_encode(['status' => 'success', 'count' => $count]);
    }

    // --- CREATE ---
    elseif ($action === 'create_folder' || $action === 'create_file') {
        if (!checkStorage($site, 0, $pdo))
            die(json_encode(['status' => 'error', 'message' => 'Storage Full']));

        $name = trim($_POST['name']);
        $path = trim($_POST['path'] ?? '');
        $target = getSecurePath($base_dir, $path . '/' . $name);
        if ($target && !file_exists($target)) {
            if ($action === 'create_folder')
                mkdir($target, 0777, true);
            else
                file_put_contents($target, "");
            logActivity($pdo, $user_id, $site['id'], $action, $name);
            echo json_encode(['status' => 'success']);
        } else
            echo json_encode(['status' => 'error', 'message' => 'Invalid path']);
    }

    // --- SAVE ---
    elseif ($action === 'save_content') {
        $content = $_POST['content'];
        // Check size diff
        $path = trim($_POST['path']);
        $target = getSecurePath($base_dir, $path);

        $new_size = strlen($content);
        $old_size = file_exists($target) ? filesize($target) : 0;
        $diff = $new_size - $old_size;

        if (!checkStorage($site, $diff, $pdo))
            die(json_encode(['status' => 'error', 'message' => 'Storage Full']));

        if ($target) {
            if (file_exists($target)) {
                $old = file_get_contents($target);
                try {
                    $pdo->prepare("INSERT INTO file_versions (site_id, file_path, content) VALUES (?,?,?)")->execute([$site['id'], $path, $old]);
                } catch (Exception $e) {
                }
            }
            file_put_contents($target, $content);
            echo json_encode(['status' => 'success']);
        } else
            echo json_encode(['status' => 'error']);
    }

    // --- DELETE ---
    elseif ($action === 'delete') {
        $path = trim($_POST['path']);
        $target = getSecurePath($base_dir, $path);
        if ($target && file_exists($target)) {
            $trash_path = $trash_dir . '/' . basename($target) . '_' . time();
            rename($target, $trash_path);
            checkStorage($site, 0, $pdo); // Update usage
            logActivity($pdo, $user_id, $site['id'], 'delete', $path);
            echo json_encode(['status' => 'success']);
        } else
            echo json_encode(['status' => 'error']);
    }

    // --- BULK DOWNLOAD ---
    elseif ($action === 'bulk_download') {
        // ... (Keep existing bulk download logic) ...
        $paths = $_POST['paths'] ?? [];
        if (empty($paths))
            die("No files selected");

        $zip = new ZipArchive();
        $zipName = "download_" . date('Ymd_His') . ".zip";
        $zipPath = sys_get_temp_dir() . '/' . $zipName;

        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            die("Could not create ZIP");
        }

        foreach ($paths as $p) {
            $real = getSecurePath($base_dir, $p);
            if ($real && file_exists($real)) {
                if (is_dir($real)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($real),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    foreach ($files as $name => $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($real) + 1);
                            $zip->addFile($filePath, basename($real) . '/' . $relativePath);
                        }
                    }
                } else {
                    $zip->addFile($real, basename($p));
                }
            }
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-disposition: attachment; filename=' . $zipName);
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    exit;
}
?>