<?php
require_login();

use BitBlog\Content;
use BitBlog\Config;
use BitBlog\Utils;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php?error=method_not_allowed');
    exit;
}

check_csrf();

// Check if file was uploaded
if (!isset($_FILES['archive']) || $_FILES['archive']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'upload_failed';
    if (isset($_FILES['archive']['error'])) {
        switch ($_FILES['archive']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg = 'upload_too_large';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg = 'no_file_uploaded';
                break;
        }
    }
    header('Location: admin.php?error=' . $errorMsg);
    exit;
}

$uploadedFile = $_FILES['archive']['tmp_name'];
$originalName = $_FILES['archive']['name'];

// Security: Check file extension
$fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($fileExt !== 'zip') {
    header('Location: admin.php?error=invalid_file_type');
    exit;
}

// Security: Check file size (max 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($_FILES['archive']['size'] > $maxSize) {
    header('Location: admin.php?error=upload_too_large');
    exit;
}

// Move uploaded file to archive directory
$archiveDir = __DIR__ . '/../archive';
if (!file_exists($archiveDir)) {
    mkdir($archiveDir, 0755, true);
}

$timestamp = date('Y-m-d_His'); // Format: Year-Month-Day_HourMinuteSecond
$uploadedArchive = $archiveDir . '/uploaded-' . $timestamp . '.zip';
if (!move_uploaded_file($uploadedFile, $uploadedArchive)) {
    header('Location: admin.php?error=upload_failed');
    exit;
}

// Now work with the moved file
$uploadedFile = $uploadedArchive;

// Try to open ZIP
$zip = new ZipArchive();
$zipOpenResult = $zip->open($uploadedFile);

if ($zipOpenResult !== true) {
    header('Location: admin.php?error=invalid_zip');
    exit;
}

// Security: Validate ZIP structure
$hasPostsDir = false;
$hasPagesDir = false;
$hasInvalidFiles = false;
$allowedExtensions = ['md', 'markdown'];
$allowedDirs = ['posts', 'pages'];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $filename = $zip->getNameIndex($i);
    
    // Security: Check for directory traversal
    if (strpos($filename, '..') !== false) {
        $zip->close();
        header('Location: admin.php?error=invalid_zip_structure');
        exit;
    }
    
    // Normalize path separators to forward slashes
    $filename = str_replace('\\', '/', $filename);
    
    // Check directory structure
    if (str_starts_with($filename, 'posts/')) {
        $hasPostsDir = true;
    } elseif (str_starts_with($filename, 'pages/')) {
        $hasPagesDir = true;
    }
    
    // Skip directories
    if (substr($filename, -1) === '/') {
        continue;
    }
    
    // Validate file extensions
    $parts = explode('/', $filename);
    if (count($parts) >= 2) {
        $dir = $parts[0];
        $file = end($parts);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if (!in_array($dir, $allowedDirs)) {
            $hasInvalidFiles = true;
            break;
        }
        
        if (!in_array($ext, $allowedExtensions)) {
            $hasInvalidFiles = true;
            break;
        }
    }
}

if (!$hasPostsDir || !$hasPagesDir) {
    $zip->close();
    header('Location: admin.php?error=missing_required_dirs');
    exit;
}

if ($hasInvalidFiles) {
    $zip->close();
    header('Location: admin.php?error=invalid_files_in_zip');
    exit;
}

// Create backup of current content directory as ZIP
$timestamp = date('Y-m-d_His'); // Format: Year-Month-Day_HourMinuteSecond
$contentDirPath = Config::CONTENT_DIR;
$contentDir = realpath($contentDirPath);

if (!$contentDir || !is_dir($contentDir)) {
    $zip->close();
    header('Location: admin.php?error=content_dir_not_found');
    exit;
}

$archiveDir = __DIR__ . '/../archive';
if (!file_exists($archiveDir)) {
    mkdir($archiveDir, 0755, true);
}

$backupZipFile = $archiveDir . '/archive-' . $timestamp . '.zip';

// Create backup ZIP
if (!Utils::createZipArchive($contentDir, $backupZipFile)) {
    $zip->close();
    header('Location: admin.php?error=backup_failed');
    exit;
}

// Delete old content and extract uploaded archive
try {
    BitBlog\Utils::extractZipReplace($uploadedFile, $contentDirPath);
} catch (Exception $e) {
    header('Location: admin.php?error=extract_failed');
    exit;
}

// Rebuild index after successful upload
try {
    $content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
    $content->rebuildIndex();
    $posts = $content->getIndex();
    $content->generateOverviewPage($posts);
    $content->generateOverviewPage($posts, 'edit');
} catch (Exception $e) {
    // Delete uploaded archive file (we only need the backup)
    @unlink($uploadedFile);
    
    // Don't rollback here - content was uploaded successfully
    // Just redirect with a warning
    header('Location: admin.php?uploaded=1&warning=rebuild_failed');
    exit;
}

// Delete uploaded archive file (we only need the backup)
@unlink($uploadedFile);

// Success - redirect to dashboard
header('Location: admin.php?uploaded=1&backup=' . urlencode(basename($backupZipFile)));
exit;
