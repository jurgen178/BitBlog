<?php
// This file is included from admin.php, so require_login() is already called

use BitBlog\Content;
use BitBlog\Config;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?action=archive&error=method_not_allowed');
    exit;
}

check_csrf();

// Get backup file from POST
$backupFileName = $_POST['backup_file'] ?? '';
if (empty($backupFileName)) {
    header('Location: admin.php?action=archive&error=no_file_uploaded');
    exit;
}

// Security: Prevent path traversal
if (strpos($backupFileName, '..') !== false || strpos($backupFileName, '/') !== false || strpos($backupFileName, '\\') !== false) {
    header('Location: admin.php?action=archive&error=invalid_zip_structure');
    exit;
}

// Check if backup file exists
$backupFile = __DIR__ . '/../archive/' . $backupFileName;
if (!file_exists($backupFile) || !is_file($backupFile)) {
    header('Location: admin.php?action=archive&error=backup_file_not_found');
    exit;
}

// Validate it's a ZIP file
$zip = new ZipArchive();
if ($zip->open($backupFile) !== true) {
    header('Location: admin.php?action=archive&error=invalid_zip');
    exit;
}

// Create backup of current content before restoring
$timestamp = date('Y-m-d_His');
$contentDirPath = Config::CONTENT_DIR;
$contentDir = realpath($contentDirPath);

if ($contentDir && is_dir($contentDir)) {
    $archiveDir = __DIR__ . '/../archive';
    if (!file_exists($archiveDir)) {
        mkdir($archiveDir, 0755, true);
    }
    
    $beforeRestoreBackup = $archiveDir . '/archive-' . $timestamp . '.zip';
    
    BitBlog\Utils::createZipArchive($contentDir, $beforeRestoreBackup);
}

// Delete current content directory and extract backup
// NOTE: Copy+delete is used instead of rename() because on Windows, rename() fails
// if any process has a handle to the directory (PHP itself, File Explorer, antivirus, etc.).
// While unlink() can delete individual files even when the directory is opened,
// rename() requires exclusive access to the entire directory structure.
// Copy+delete is more robust and works reliably across platforms.
try {
    BitBlog\Utils::extractZipReplace($backupFile, $contentDirPath);
} catch (Exception $e) {
    header('Location: admin.php?action=archive&error=extract_failed');
    exit;
}

// Rebuild index after restore
try {
    $content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
    $content->rebuildIndex();
    $posts = $content->getIndex();
    $content->generateOverviewPage($posts);
    $content->generateOverviewPage($posts, 'edit');
} catch (Exception $e) {
    header('Location: admin.php?action=archive&restored=1&warning=rebuild_failed');
    exit;
}

// Success
header('Location: admin.php?action=archive&restored=1');
exit;
