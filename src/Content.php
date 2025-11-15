<?php
declare(strict_types=1);

namespace BitBlog;

final class Content
{
    private string $contentDir;
    private string $cacheDir;
    private string $baseUrl;
    private string $pagesDir;
    private ?array $tagCloudCache = null;
    
    private IndexManager $indexManager;
    private PageGenerator $pageGenerator;
    private MarkdownProcessor $markdownProcessor;

    public function __construct(string $contentDir, string $cacheDir, string $baseUrl)
    {
        $this->contentDir = rtrim($contentDir, '/');
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->pagesDir = $this->contentDir . '/pages';
    }

    private function getMarkdownProcessor(): MarkdownProcessor
    {
        return $this->markdownProcessor ??= new MarkdownProcessor();
    }

    private function getIndexManager(): IndexManager  
    {
        return $this->indexManager ??= new IndexManager($this->contentDir, $this->cacheDir, $this->baseUrl, $this->getMarkdownProcessor());
    }

    private function getPageGenerator(): PageGenerator
    {
        return $this->pageGenerator ??= new PageGenerator($this->contentDir, $this->cacheDir, $this->baseUrl, $this->getMarkdownProcessor());
    }

    public function rebuildIndex(): void
    {
        $this->getIndexManager()->rebuildIndex();
        
        // Generate overview pages and signature after index rebuild
        $posts = $this->getIndexManager()->getIndex();
        $this->getPageGenerator()->generateOverviewPage($posts);
        $this->getPageGenerator()->generateOverviewPage($posts, 'edit');
        $this->getPageGenerator()->generateSignature($posts);
        
        // Invalidate tag cloud cache
        $this->tagCloudCache = null;
    }

    public function getIndex(): array
    {
        return $this->getIndexManager()->getIndex();
    }

    public function getPostsPage(int $page = 1, int $postsPerPage = 10): array
    {
        $index = $this->getIndex();
        $published = array_filter($index, fn($p) => $p['status'] === Constants::POST_STATUS_PUBLISHED);
        
        $totalPosts = count($published);
        $totalPages = (int) ceil($totalPosts / $postsPerPage);
        $offset = ($page - 1) * $postsPerPage;
        $slice = array_slice($published, $offset, $postsPerPage);
        
        $slice = $this->loadHtmlForPosts($slice);
        
        return [$slice, $totalPages];
    }

    public function getRecentPosts(int $limit): array
    {
        $idx = array_values(array_filter($this->getIndex(), fn($p) => $p['status'] === Constants::POST_STATUS_PUBLISHED));
        return array_slice($idx, 0, $limit);
    }

    public function getPostById(int $id): ?array
    {
        return $this->getIndexManager()->getPostById($id);
    }
    
    private function loadHtmlForPosts(array $posts): array
    {
        // SIMPLE: Direct file loading without complex caching
        return array_map(function($post) {
            if (!isset($post['html'])) {
                $path = $post['path'];
                if (is_file($path)) {
                    $raw = Utils::readFile($path);
                    $parsed = $this->readMarkdownWithMeta($path, $raw);
                    $post['html'] = RenderMarkdown::toHtml($parsed['body']);
                } else {
                    $post['html'] = '';
                }
            }
            return $post;
        }, $posts);
    }

    public function getAllPostUrls(): array
    {
        return $this->getIndexManager()->getAllPostUrls();
    }

    public function getPostsByTag(string $tag, int $page, int $perPage): array
    {
        // Use optimized IndexManager method
        $idx = $this->getIndexManager()->getPostsByTag($tag);
        
        $total = count($idx);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        
        // PERFORMANCE: Only slice the posts we need FIRST, then load HTML
        $slice = array_slice($idx, ($page - 1) * $perPage, $perPage);
        
        // Add HTML content ONLY for posts on the current page - OPTIMIZED
        $slice = $this->loadHtmlForPosts($slice);
        
        return [$slice, $totalPages];
    }

    public function getTagCloud(): array
    {
        if ($this->tagCloudCache !== null) {
            return $this->tagCloudCache;
        }

        $this->tagCloudCache = $this->getIndexManager()->getTagCloud();
        return $this->tagCloudCache;
    }

    public function getPage(string $name): ?array
    {
        // Use page name directly
        $path = $this->pagesDir . '/' . $name . '.md';
        if (!is_file($path)) return null;
        $parsed = $this->getMarkdownProcessor()->readMarkdownWithMeta($path);
        $html = RenderMarkdown::toHtml($parsed['body']);
        return ['meta' => $parsed['meta'], 'html' => $html];
    }

    public function readMarkdownWithMeta(string $path, ?string $raw = null): array
    {
        return $this->getMarkdownProcessor()->readMarkdownWithMeta($path, $raw);
    }

    public function generateOverviewPage(array $posts, string $urlType = 'index'): void
    {
        $this->getPageGenerator()->generateOverviewPage($posts, $urlType);
    }

    /**
     * Get signature HTML for current language
     */
    public function getSignatureHtml(): string
    {
        return $this->getPageGenerator()->getSignatureHtml();
    }

    /**
     * Get post titles only (for tooltips) - performance optimized
     */
    public function getPostTitlesPage(int $page = 1, int $postsPerPage = 10): array
    {
        $index = $this->getIndex();
        $published = array_filter($index, fn($p) => $p['status'] === Constants::POST_STATUS_PUBLISHED);
        
        $offset = ($page - 1) * $postsPerPage;
        $slice = array_slice($published, $offset, $postsPerPage);
        
        // Return only titles - no HTML loading
        return array_map(fn($p) => ['title' => $p['title']], $slice);
    }

    /**
     * Get post titles by tag (for tooltips) - performance optimized  
     */
    public function getPostTitlesByTag(string $tag, int $page, int $perPage): array
    {
        // Use optimized IndexManager method  
        $idx = $this->getIndexManager()->getPostsByTag($tag);
        
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($idx, $offset, $perPage);
        
        // Return only titles - no HTML loading
        return array_map(fn($p) => ['title' => $p['title']], $slice);
    }
    
    /**
     * Parse markdown content and extract metadata for comparison
     */
    public function parseMarkdown(string $content): array
    {
        // Extract YAML front matter manually since we have raw content
        $meta = [];
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        if (strncmp($content, "---\n", 4) === 0) {
            $end = strpos($content, "\n---", 4);
            if ($end !== false) {
                $yaml = substr($content, 4, $end - 4);
                $meta = $this->parseYamlSimple($yaml);
            }
        }
        
        return $meta;
    }
    
    /**
     * Simple YAML parser for front matter
     */
    private function parseYamlSimple(string $yaml): array
    {
        $lines = explode("\n", $yaml);
        $data = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Handle arrays like tags: [tag1, tag2]
                if (preg_match('/^\[(.*)\]$/', $value, $matches)) {
                    $items = array_map('trim', explode(',', $matches[1]));
                    $data[$key] = array_filter($items);
                } else {
                    $data[$key] = $value;
                }
            }
        }
        
        return $data;
    }
}
