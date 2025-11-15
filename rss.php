<?php
declare(strict_types=1);

require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/Constants.php';
require __DIR__ . '/src/Utils.php';
require __DIR__ . '/src/RenderMarkdown.php';
require __DIR__ . '/src/MarkdownProcessor.php';
require __DIR__ . '/src/IndexManager.php';
require __DIR__ . '/src/PageGenerator.php';
require __DIR__ . '/src/Content.php';
require __DIR__ . '/src/Rss.php';
require __DIR__ . '/src/Language.php';

use BitBlog\Config;
use BitBlog\Content;
use BitBlog\Rss;

date_default_timezone_set(Config::TIMEZONE);

// Set RSS headers FIRST
header('Content-Type: application/rss+xml; charset=UTF-8');

$content = new Content(
    Config::CONTENT_DIR,
    Config::CACHE_DIR,
    Config::BASE_URL()
);

$rssLimit = Config::RSS_POSTS_LIMIT > 0 ? Config::RSS_POSTS_LIMIT : PHP_INT_MAX;
echo Rss::build($content->getRecentPosts($rssLimit), Config::SITE_TITLE, Config::BASE_URL());
?>
