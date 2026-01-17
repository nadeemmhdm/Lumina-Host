# Lumina Host (WebHost) 🚀

**Lumina Host** is a powerful, lightweight PHP-based web hosting management platform. It allows users to create, manage, and edit up to 3 distinct websites with a full-featured file manager, live code editor, and robust security limits.

![Lumina Host Dashboard](https://via.placeholder.com/800x400?text=Lumina+Host+Dashboard+Preview)

---

## 🌟 Key Features

### 🖥️ Multi-Site Management
*   **3 Websites per Account**: Users can create up to 3 unique subdomains (e.g., `mysite.lumina.host`).
*   **Storage Quotas**: Strict **500MB** storage limit per website with visual usage bars on the dashboard.
*   **Publish Control**: Toggle websites between **Published** (Live) and **Unpublished** (Maintenance Mode).

### 📁 Advanced File Manager
*   **Smart Uploads**:
    *   **Drag & Drop** support.
    *   **Folder Upload**: Upload entire directory structures.
    *   **ZIP Auto-Extract**: Upload a ZIP file, and it automatically unpacks on the server.
*   **Bulk Actions**: Multi-select files to Delete or Download as ZIP.
*   **Recycle Bin**: Deleted files move to a trash folder for recovery (7-day retention logic).
*   **Search & Filter**: Instant client-side filtering by name or file type (Image, Video, Code).
*   **Media Preview**: Built-in lightbox for Images, Videos, and PDFs.

### 📝 Integrated Editors
*   **Code Editor**: Powered by **CodeMirror** (Syntax highlighting for HTML, CSS, JS, PHP).
    *   **Auto-Save**: Changes saved every 15s.
    *   **Version History**: Rollback to previous file versions.
*   **Rich Text Editor**: Powered by **TinyMCE** for visual HTML editing.

### 🎨 Modern UI/UX
*   **Theme Engine**: Switch between **Light**, **Dark**, and **System** themes.
*   **Responsive**: Fully mobile-optimized dashboard and file manager.
*   **Toast Notifications**: Sleek, non-intrusive alerts for actions.

---

## 🛠️ Installation & Setup

### Requirements
*   PHP 7.4 or higher
*   MySQL / MariaDB
*   Apache/Nginx Web Server

### 1. Configuration
Open `config.php` and update your database credentials and base URL:

```php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_pass');

define('BASE_URL', 'http://localhost/lumina-host/');
```

### 2. Database Initialization
Visit the setup script in your browser once to create all necessary tables:
`http://localhost/lumina-host/setup_db.php`

> **Note:** This script creates tables for `users`, `user_sites`, `file_versions`, `activity_logs`, and adds necessary columns like `status` and `theme_preference`.

### 3. Permissions
Ensure the `users/` directory exists and is writable by the web server:
```bash
mkdir users
chmod 777 users
```

---

## 📂 Directory Structure

```
/
├── assets/             # CSS, JS, and Images
├── includes/           # Core PHP functions & auth checks
├── users/              # User website data (Isolated per subfolder)
├── config.php          # Database config
├── setup_db.php        # Database installer
├── index.php           # Landing Page
├── dashboard.php       # Main User Dashboard
├── file_manager.php    # Frontend File Manager
├── file_actions.php    # Backend File Operations (AJAX)
├── editor.php          # Code Editor
└── settings.php        # Account & Site Settings
```

---

## 🔐 Security Features
*   **CSRF Protection**: All form submissions and AJAX requests are protected by unique tokens.
*   **Path Traversal Prevention**: Custom `getSecurePath()` function ensures users cannot access files outside their directory.
*   **Storage Enforcement**: Server-side checks prevent bypassing the 500MB limit.
*   **File Type Whitelisting**: Executables (`.exe`, `.sh`) and dangerous PHP extensions are blocked from upload.
*   **Directory Isolation**: Each website operates in a strictly sandboxed directory.

---

## 📜 License
This project is open-source and available for educational and personal use.
