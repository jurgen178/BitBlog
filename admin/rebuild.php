<?php
require_login();

use BitBlog\Content;
use BitBlog\Config;

$content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());

// Rebuild index and generate all pages
$content->rebuildAll();

// Get count of posts after rebuild
$posts = $content->getIndex();
$postCount = count($posts);

// Redirect back to admin dashboard with success message and post count
header('Location: admin.php?rebuilt=1&post_count=' . $postCount);
exit;
