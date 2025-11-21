<?php
/**
 * Blog Post Index Manager
 * 
 * High-performance post management with caching:
 * - Scans markdown files and builds searchable index
 * - Caches metadata for fast retrieval
 * - Tag cloud generation and filtering
 * - Memory-optimized operations for large blogs
*/

declare(strict_types=1);

namespace BitBlog;

final class IndexManager
{
    private string $contentDir;
    private string $cacheDir;
    private string $baseUrl;
    private string $postsDir;
    private string $indexFile;
    private ?array $indexCache = null;
    private ?array $tagCloudCache = null;
    private MarkdownProcessor $markdownProcessor;

    /**
     * Initialize the index manager with directory paths and dependencies
     * 
     * @param string $contentDir Directory containing blog posts
     * @param string $cacheDir Directory for cache files
     * @param string $baseUrl Base URL for generating post links
     * @param MarkdownProcessor $markdownProcessor For processing markdown files
     */
    public function __construct(string $contentDir, string $cacheDir, string $baseUrl, MarkdownProcessor $markdownProcessor)
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->postsDir = $this->contentDir . '/posts';
        $this->indexFile = $this->cacheDir . '/' . Constants::CACHE_INDEX_FILE;
        $this->markdownProcessor = $markdownProcessor;
        
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0775, true) && !is_dir($this->cacheDir)) {
            throw new \RuntimeException("Cannot create cache directory: " . $this->cacheDir);
        }
        if (!file_exists($this->indexFile)) {
            $this->rebuildPostIndex();
        }
    }

    /**
     * Rebuild the post index cache from markdown files
     * Scans all posts, extracts metadata, and creates searchable JSON index
     * @return void
     */
    public function rebuildPostIndex(): void
    {
        $posts = [];
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->postsDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if (!$file->isFile()) continue;
            if (strtolower($file->getExtension()) !== Constants::MARKDOWN_EXTENSION) continue;
            $path = $file->getPathname();
            $post = $this->markdownProcessor->readMarkdownWithMeta($path);
            $meta = $post['meta'];
            if (!isset($meta['title'])) continue;
            
            // Always read date from filename, not from YAML meta
            $date = $this->markdownProcessor->inferDate($path);
            $status = strtolower((string)($meta['status'] ?? Constants::POST_STATUS_PUBLISHED));
            $tags = $this->markdownProcessor->toStringArray($meta['tags'] ?? []);
            $token = isset($meta['token']) ? (string)$meta['token'] : null;
            
            // Extract ID from filename format: YYYY-MM-DD.ID.md
            $filename = basename($path, '.md');
            $id = $this->markdownProcessor->extractIdFromFilename($filename);
            if ($id === null) continue; // Skip files that don't match the expected format
            
            $url = $this->baseUrl . '/index.php?id=' . $id;

            $postData = [
                'id' => $id,
                'title' => (string)$meta['title'],
                'timestamp' => strtotime($date) ?: filemtime($path),
                'status' => $status,
                'tags' => $tags,
                'path' => $path,
                'url' => $url,
            ];
            
            // Only include token if it exists (for private posts)
            if ($token !== null) {
                $postData['token'] = $token;
            }
            
            $posts[] = $postData;
        }
        usort($posts, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        Utils::writeFile($this->indexFile, json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Build search index
        $this->buildSearchIndex($posts);
        
        // PERFORMANCE: Invalidate caches after rebuild
        $this->indexCache = null;
        $this->tagCloudCache = null;
    }

    /**
     * Build search index for client-side instant search
     * Creates a lightweight JSON file with title, content, and tags (all lowercase)
     * 
     * @param array $posts Array of post metadata
     * @return void
     */
    private function buildSearchIndex(array $posts): void
    {
        $searchIndex = [];
        
        foreach ($posts as $post) {
            // Skip non-published posts in search index
            if ($post['status'] !== Constants::POST_STATUS_PUBLISHED) {
                continue;
            }
            
            // Read and strip HTML from content
            $raw = Utils::readFile($post['path']);
            $parsed = $this->markdownProcessor->readMarkdownWithMeta($post['path'], $raw);
            $html = RenderMarkdown::toHtml($parsed['body']);
            $plainText = strip_tags($html);
            
            // Normalize whitespace: replace multiple spaces, newlines, tabs with single space
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $plainText = trim($plainText);
            
            // Build search entry with lowercase values for case-insensitive search
            // Also store original text for proper display in results
            $searchIndex[$post['id']] = [
                'title' => mb_strtolower($post['title'], 'UTF-8'),
                'content' => mb_strtolower($plainText, 'UTF-8'),
                'original_title' => $post['title'],
                'original_content' => $plainText,
                'url' => $post['url'],
                'date' => $post['timestamp']
            ];
        }
        
        $searchIndexFile = $this->cacheDir . '/search-index.json';
        Utils::writeFile($searchIndexFile, json_encode($searchIndex, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get all posts from index
     * @return array<int, array<string, mixed>>
     */
    public function getIndex(): array
    {
        // PERFORMANCE: Cache index in memory to avoid repeated file reads
        if ($this->indexCache !== null) {
            return $this->indexCache;
        }
        
        $json = Utils::readFile($this->indexFile);
        $arr = json_decode($json, true);
        
        $this->indexCache = is_array($arr) ? $arr : [];
        return $this->indexCache;
    }

    public function getPostById(int $id): ?array
    {
        // SIMPLE: Direct index search - fast enough for small datasets
        $index = $this->getIndex();
        
        $postMeta = null;
        foreach ($index as $post) {
            if ($post['id'] === $id) {
                $postMeta = $post;
                break;
            }
        }
        
        if (!$postMeta) return null;
        
        // Load only the specific file
        $path = $postMeta['path'];
        if (!is_file($path)) return null;
        
        $raw = Utils::readFile($path);
        $parsed = $this->markdownProcessor->readMarkdownWithMeta($path, $raw);
        $html = RenderMarkdown::toHtml($parsed['body']);
        
        $result = [
            'id' => $id,
            'title' => $postMeta['title'],
            'timestamp' => $postMeta['timestamp'],
            'status' => $postMeta['status'],
            'tags' => $postMeta['tags'],
            'path' => $path,
            'url' => $postMeta['url'],
            'html' => $html,
            'meta' => $parsed['meta'],
        ];
        
        // Include token if it exists (for private posts)
        if (isset($postMeta['token'])) {
            $result['token'] = $postMeta['token'];
        }
        
        return $result;
    }

    public function getAllPostUrls(): array
    {
        return array_map(fn($p) => $p['url'], $this->getIndex());
    }

    public function getTagCloud(): array
    {
        if ($this->tagCloudCache !== null) {
            return $this->tagCloudCache;
        }

        $counts = [];
        $untaggedCount = 0;
        
        foreach ($this->getIndex() as $p) {
            if ($p['status'] !== Constants::POST_STATUS_PUBLISHED) continue;
            
            if (empty($p['tags'])) {
                $untaggedCount++;
            } else {
                foreach ($p['tags'] as $t) {
                    $tStr = (string)$t;
                    $counts[$tStr] = ($counts[$tStr] ?? 0) + 1;
                }
            }
        }
        
        // Sort all tags alphabetically first
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        
        // Add special key for uncategorized posts at the end (will be translated in templates)
        if ($untaggedCount > 0) {
            $counts['uncategorized'] = $untaggedCount;
        }
        
        $this->tagCloudCache = $counts;
        return $counts;
    }

    /**
     * Get posts by tag with better performance using pre-filtered arrays
     */
    /**
     * Get posts by tag with better performance using pre-filtered arrays
     * @param string $tag
     * @return array<int, array<string, mixed>>
     */
    public function getPostsByTag(string $tag): array
    {
        $tag = (string)$tag;
        $tagLower = \mb_strtolower($tag, 'UTF-8');
        $result = [];
        
        // Check if tag exists in real tag cloud - if not, treat as uncategorized
        if (!empty($tag) && $tagLower !== 'uncategorized') {
            $tagCloud = $this->getTagCloud();
            $tagExists = false;
            foreach ($tagCloud as $realTag => $count) {
                if (\mb_strtolower((string)$realTag, 'UTF-8') === $tagLower) {
                    $tagExists = true;
                    break;
                }
            }
            // If tag doesn't exist in real tags, treat as uncategorized
            if (!$tagExists) {
                $tag = '';
                $tagLower = 'uncategorized';
            }
        }
        
        foreach ($this->getIndex() as $post) {
            if ($post['status'] !== Constants::POST_STATUS_PUBLISHED) continue;
            
            // Special case: uncategorized posts (empty tag or explicit "uncategorized")
            if (empty($tag) || $tagLower === 'uncategorized') {
                if (empty($post['tags'])) {
                    $result[] = $post;
                }
                continue;
            }
            
            // Regular tag matching - optimized with early break
            foreach ($post['tags'] as $t) {
                if (\mb_strtolower((string)$t, 'UTF-8') === $tagLower) {
                    $result[] = $post;
                    break; // Found match, no need to check other tags
                }
            }
        }
        
        return $result;
    }

    /**
     * Invalidate the index cache (useful for external modifications)
     */
    public function invalidateCache(): void
    {
        $this->indexCache = null;
        $this->tagCloudCache = null;
    }
}
