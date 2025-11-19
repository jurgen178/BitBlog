<?php
require_login();

use BitBlog\Content;
use BitBlog\Config;

$content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());

// Rebuild index
$content->rebuildIndex();

// Get count of posts after rebuild
$posts = $content->getIndex();
$postCount = count($posts);

// Generate overview pages
$content->generateOverviewPage($posts);
$content->generateOverviewPage($posts, 'edit');

// Create ZIP archive of content folder
$zipFile = __DIR__ . '/../blog-content.zip';
if (file_exists($zipFile)) {
    unlink($zipFile);
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $contentPath = realpath(Config::CONTENT_DIR);
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($contentPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($contentPath) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    $zip->close();
}

// Redirect back to admin dashboard with success message and post count
header('Location: admin.php?rebuilt=1&post_count=' . $postCount);
exit;
