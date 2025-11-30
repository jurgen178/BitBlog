<?php
declare(strict_types=1);

namespace BitBlog;

final class Router
{
    public function __construct()
    {
        // Router now uses GET parameters instead of URL paths
    }

    public function match(): array
    {
        // Check for name parameter first
        if (isset($_GET['name'])) {
            $name = (string)$_GET['name'];
            $token = isset($_GET['token']) ? (string)$_GET['token'] : null;
            return ['name' => Constants::ROUTE_POST_BY_NAME, 'params' => ['name' => $name, 'token' => $token]];
        }
        
        // Check for specific GET parameters
        if (isset($_GET['id']) || isset($_GET['id_post'])) {
            $id = (int)($_GET['id'] ?? $_GET['id_post']);
            $token = isset($_GET['token']) ? (string)$_GET['token'] : null;
            return ['name' => Constants::ROUTE_POST, 'params' => ['id' => $id, 'token' => $token]];
        }
        
        if (isset($_GET['tag']) || isset($_GET['category'])) {
            $tag = (string)($_GET['tag'] ?? $_GET['category']);
            $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
            return ['name' => Constants::ROUTE_TAG, 'params' => ['tag' => $tag, 'page' => $page]];
        }
        
        if (isset($_GET['page'])) {
            $pageName = (string)$_GET['page'];
            return ['name' => Constants::ROUTE_PAGE, 'params' => ['name' => $pageName]];
        }
        
        if (isset($_GET['feed'])) {
            return ['name' => Constants::ROUTE_FEED, 'params' => []];
        }
        
        if (isset($_GET['sitemap'])) {
            return ['name' => Constants::ROUTE_SITEMAP, 'params' => []];
        }
        
        // Home page with optional pagination (use 'p' parameter to avoid conflict with 'page' route)
        $page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
        return ['name' => Constants::ROUTE_HOME, 'params' => ['page' => $page]];
    }
}
