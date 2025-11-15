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
require __DIR__ . '/src/Renderer.php';
require __DIR__ . '/src/Router.php';
require __DIR__ . '/src/Rss.php';
require __DIR__ . '/src/Sitemap.php';
require __DIR__ . '/src/Language.php';

use BitBlog\Config;
use BitBlog\Constants;
use BitBlog\Router;
use BitBlog\Content;
use BitBlog\Renderer;
use BitBlog\Language;
use BitBlog\Rss;
use BitBlog\Sitemap;

// Load configuration once at startup
$siteTitle = Config::get('SITE_TITLE');
$postsPerPage = Config::get('POSTS_PER_PAGE');
$rssLimit = Config::get('RSS_POSTS_LIMIT');
$timezone = Config::get('TIMEZONE');

date_default_timezone_set($timezone);

$content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
$renderer = new Renderer(__DIR__ . '/templates', $siteTitle, Config::BASE_URL());

$router = new Router();
$route = $router->match();

// Set caching headers for better performance
header('Cache-Control: public, max-age=300'); // 5 minutes cache
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');

switch ($route['name']) {
    case Constants::ROUTE_HOME:
        $page = (int)($route['params']['page'] ?? 1);
        [$posts, $totalPages] = $content->getPostsPage($page, $postsPerPage);
        
        // Generate tooltips for pagination
        $newerTooltip = '';
        $olderTooltip = '';
        if ($page > 1) {
            $prevPosts = $content->getPostTitlesPage($page - 1, $postsPerPage);
            if (!empty($prevPosts)) {
                $titles = array_map(fn($p) => $p['title'], $prevPosts);
                $newerTooltip = implode("\n", $titles);
            }
        }
        if ($page < $totalPages) {
            $nextPosts = $content->getPostTitlesPage($page + 1, $postsPerPage);
            if (!empty($nextPosts)) {
                $titles = array_map(fn($p) => $p['title'], $nextPosts);
                $olderTooltip = implode("\n", $titles);
            }
        }
        
        echo $renderer->render('list', [
            'title' => Language::getText('home'),
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'tags' => $content->getTagCloud(),
            'newerTooltip' => $newerTooltip,
            'olderTooltip' => $olderTooltip,
        ]);
        break;

    case Constants::ROUTE_POST:
        $id = $route['params']['id'];
        $post = $content->getPostById($id);
        
        // Check if post exists and handle status-based access control
        if (!$post) {
            http_response_code(404);
            echo $renderer->render('page', ['title' => Language::getText('not_found'), 'html' => '<p>' . Language::getText('post_not_found') . '</p>']);
            break;
        }
        
        // Published and Draft posts - always accessible
        if ($post['status'] === Constants::POST_STATUS_PUBLISHED || $post['status'] === Constants::POST_STATUS_DRAFT) {
            echo $renderer->render('post', ['post' => $post, 'tags' => $content->getTagCloud()]);
            break;
        }
        
        // Private posts - require valid token
        if ($post['status'] === Constants::POST_STATUS_PRIVATE) {
            $requiredToken = $post['token'] ?? null;
            $providedToken = $_GET['token'] ?? null;
            
            if ($requiredToken && $requiredToken === $providedToken) {
                echo $renderer->render('post', ['post' => $post, 'tags' => $content->getTagCloud()]);
                break;
            }
        }
        
        // Invalid token for private post - return 404 (don't reveal existence)
        http_response_code(404);
        echo $renderer->render('page', ['title' => Language::getText('not_found'), 'html' => '<p>' . Language::getText('post_not_found') . '</p>']);
        break;

    case Constants::ROUTE_TAG:
        $tag = $route['params']['tag'];
        $page = (int)($route['params']['page'] ?? 1);
        [$posts, $totalPages] = $content->getPostsByTag($tag, $page, $postsPerPage);
        
        // Generate tooltips for pagination
        $newerTooltip = '';
        $olderTooltip = '';
        if ($page > 1) {
            $prevPosts = $content->getPostTitlesByTag($tag, $page - 1, $postsPerPage);
            if (!empty($prevPosts)) {
                $titles = array_map(fn($p) => $p['title'], $prevPosts);
                $newerTooltip = implode("\n", $titles);
            }
        }
        if ($page < $totalPages) {
            $nextPosts = $content->getPostTitlesByTag($tag, $page + 1, $postsPerPage);
            if (!empty($nextPosts)) {
                $titles = array_map(fn($p) => $p['title'], $nextPosts);
                $olderTooltip = implode("\n", $titles);
            }
        }
        
        // Translate tag name and set title
        $displayTag = Language::translateTagName($tag);
        $title = Language::getText('tags') . ': ' . $displayTag;
        
        echo $renderer->render('list', [
            'title' => $title,
            'posts' => $posts,
            'page' => $page,
            'totalPages' => $totalPages,
            'tags' => $content->getTagCloud(),
            'newerTooltip' => $newerTooltip,
            'olderTooltip' => $olderTooltip,
        ]);
        break;

    case Constants::ROUTE_PAGE:
        $pageName = $route['params']['name'];
        $pageData = $content->getPage($pageName);
        if (!$pageData) {
            http_response_code(404);
            echo $renderer->render('page', ['title' => Language::getText('not_found'), 'html' => '<p>' . Language::getText('page_not_found') . '</p>']);
            break;
        }
        echo $renderer->render('page', ['title' => $pageData['meta']['title'] ?? ucfirst($pageName), 'html' => $pageData['html'], 'tags' => $content->getTagCloud()]);
        break;

    case Constants::ROUTE_FEED:
        header('Content-Type: application/rss+xml; charset=UTF-8');
        $finalRssLimit = $rssLimit > 0 ? $rssLimit : PHP_INT_MAX;
        echo Rss::build($content->getRecentPosts($finalRssLimit), $siteTitle, Config::BASE_URL());
        break;

    case Constants::ROUTE_SITEMAP:
        header('Content-Type: application/xml; charset=UTF-8');
        
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
        break;

    default:
        http_response_code(404);
        echo $renderer->render('page', ['title' => Language::getText('not_found'), 'html' => '<p>' . Language::getText('not_found') . '</p>']);
}
?>
