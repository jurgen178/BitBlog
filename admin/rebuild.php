<?php
require_login();

use BitBlog\Content;
use BitBlog\Config;
use BitBlog\Utils;

$content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());

// Rebuild index and generate all pages
$content->rebuildAll();

// Get count of posts after rebuild
$posts = $content->getIndex();
$postCount = count($posts);

// Create ZIP archive of content folder
$archiveDir = __DIR__ . '/../archive';
if (!file_exists($archiveDir)) {
    mkdir($archiveDir, 0755, true);
}

$timestamp = date('Y-m-d_His'); // Format: Year-Month-Day_HourMinuteSecond
$zipFile = $archiveDir . '/blog-content-' . $timestamp . '.zip';

Utils::createZipArchive(Config::CONTENT_DIR, $zipFile);

// Redirect back to admin dashboard with success message and post count
header('Location: admin.php?rebuilt=1&post_count=' . $postCount);
exit;
