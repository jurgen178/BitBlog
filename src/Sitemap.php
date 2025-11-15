<?php
declare(strict_types=1);

namespace BitBlog;

final class Sitemap
{
    public static function build(array $postUrls, string $baseUrl, array $additionalData = []): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        // Homepage with highest priority
        $xml[] = '<url>';
        $xml[] = '  <loc>' . Utils::e($baseUrl) . '</loc>';
        $xml[] = '  <lastmod>' . date('c') . '</lastmod>';
        $xml[] = '  <priority>1.0</priority>';
        $xml[] = '</url>';
        
        // Add category pages if provided
        if (isset($additionalData['categories'])) {
            foreach ($additionalData['categories'] as $category => $count) {
                $xml[] = '<url>';
                $xml[] = '  <loc>' . Utils::e($baseUrl) . '/index.php?tag=' . urlencode($category) . '</loc>';
                $xml[] = '  <priority>0.8</priority>';
                $xml[] = '</url>';
            }
        }
        
        // Add static pages if provided  
        if (isset($additionalData['pages'])) {
            foreach ($additionalData['pages'] as $pageName) {
                $xml[] = '<url>';
                $xml[] = '  <loc>' . Utils::e($baseUrl) . '/index.php?page=' . urlencode($pageName) . '</loc>';
                $xml[] = '  <priority>0.7</priority>';
                $xml[] = '</url>';
            }
        }
        
        // Add posts with timestamps if available
        if (isset($additionalData['posts'])) {
            foreach ($additionalData['posts'] as $post) {
                $xml[] = '<url>';
                $xml[] = '  <loc>' . Utils::e($post['url']) . '</loc>';
                if (isset($post['timestamp'])) {
                    $xml[] = '  <lastmod>' . date('c', $post['timestamp']) . '</lastmod>';
                }
                $xml[] = '  <priority>0.6</priority>';
                $xml[] = '</url>';
            }
        } else {
            // Fallback: simple URL list (backward compatibility)
            foreach ($postUrls as $u) {
                $xml[] = '<url>';
                $xml[] = '  <loc>' . Utils::e($u) . '</loc>';
                $xml[] = '  <priority>0.6</priority>';
                $xml[] = '</url>';
            }
        }
        
        $xml[] = '</urlset>';
        return implode("\n", $xml);
    }
}
