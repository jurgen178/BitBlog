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

// Redirect back to admin dashboard with success message and post count
header('Location: admin.php?rebuilt=1&post_count=' . $postCount);
exit;
