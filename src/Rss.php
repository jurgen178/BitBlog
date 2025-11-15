<?php
declare(strict_types=1);

namespace BitBlog;

final class Rss
{
    public static function build(array $posts, string $title, string $baseUrl): string
    {
        $updated = Utils::iso($posts[0]['timestamp'] ?? time());
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<rss version="2.0">';
        $xml[] = '  <channel>';
        $xml[] = '    <title>' . Utils::e($title) . '</title>';
        $xml[] = '    <link>' . Utils::e($baseUrl) . '</link>';
        $xml[] = '    <description>' . Utils::e($title) . '</description>';
        $xml[] = '    <lastBuildDate>' . date(DATE_RSS, strtotime($updated)) . '</lastBuildDate>';
        foreach ($posts as $p) {
            $xml[] = '    <item>';
            $xml[] = '      <title>' . Utils::e($p['title']) . '</title>';
            $xml[] = '      <link>' . Utils::e($p['url']) . '</link>';
            $xml[] = '      <guid>' . Utils::e($p['url']) . '</guid>';
            $xml[] = '      <pubDate>' . date(DATE_RSS, $p['timestamp']) . '</pubDate>';
            $xml[] = '      <description>' . Utils::e($p['title']) . '</description>';
            $xml[] = '    </item>';
        }
        $xml[] = '  </channel>';
        $xml[] = '</rss>';
        return implode("\n", $xml);
    }
}
