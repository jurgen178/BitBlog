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
require __DIR__ . '/src/Sitemap.php';
require __DIR__ . '/src/Language.php';

use BitBlog\Config;
use BitBlog\Constants;
use BitBlog\Content;
use BitBlog\Sitemap;

date_default_timezone_set(Config::TIMEZONE);

// Set XML headers
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400'); // 24 hour cache for sitemap

$content = new Content(
    Config::CONTENT_DIR,
    Config::CACHE_DIR,
    Config::BASE_URL()
);

// Gather comprehensive sitemap data
$posts = array_filter($content->getIndex(), fn($p) => $p['status'] === Constants::POST_STATUS_PUBLISHED);
$categories = $content->getTagCloud();
$staticPages = ['about']; // Add your static pages here

$additionalData = [
    'posts' => $posts,
    'categories' => $categories,
    'pages' => $staticPages
];

echo Sitemap::build($content->getAllPostUrls(), Config::BASE_URL(), $additionalData);
?>
