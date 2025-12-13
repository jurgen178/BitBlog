<?php
declare(strict_types=1);

namespace BitBlog;

final class PageGenerator
{
    private string $cacheDir;
    private string $baseUrl;
    private MarkdownProcessor $markdownProcessor;
    private string $pagesDir;

    public function __construct(string $contentDir, string $cacheDir, string $baseUrl, MarkdownProcessor $markdownProcessor)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->markdownProcessor = $markdownProcessor;
        $this->pagesDir = rtrim($contentDir, '/') . '/pages';
    }

    public function generateOverviewPage(array $posts, string $urlType = 'index'): void
    {
        // Use default language for static files
        $originalLanguage = Language::getCurrentLanguage();
        Language::setLanguage(Config::DEFAULT_LANGUAGE);
        
        try {
            // Group posts by tags (categories) - only published posts
            $categories = [];
            $publishedPostsCount = 0;
            foreach ($posts as $post) {
                if ($post['status'] !== Constants::POST_STATUS_PUBLISHED) continue;
                
                $publishedPostsCount++;
                
                if (empty($post['tags'])) {
                    $categories[Language::getText('uncategorized')][] = $post;
                } else {
                    foreach ($post['tags'] as $tag) {
                        $categories[$tag][] = $post;
                    }
                }
            }
            
            // Sort categories alphabetically, but put uncategorized at the end
            $uncategorizedLabel = Language::getText('uncategorized');
            $uncategorizedCategory = null;
            if (isset($categories[$uncategorizedLabel])) {
                $uncategorizedCategory = $categories[$uncategorizedLabel];
                unset($categories[$uncategorizedLabel]);
            }
            
            ksort($categories, SORT_NATURAL | SORT_FLAG_CASE);
            
            // Add uncategorized back at the end if it exists
            if ($uncategorizedCategory !== null) {
                $categories[$uncategorizedLabel] = $uncategorizedCategory;
            }
            
            // Generate HTML for category-based overview
            $html = $this->renderCategoriesPageHtml($categories, $urlType, $publishedPostsCount);
            
            // Determine output file based on URL type
            $fileName = ($urlType === 'edit') ? 'index2a.html' : 'index2.html';
            $outputPath = dirname($this->cacheDir) . '/' . $fileName;
            Utils::writeFile($outputPath, $html);
            
            // Generate chronological overview (index3.html for public, index3a.html for edit)
            $this->generateChronologicalOverview($posts, $urlType);
            
        } finally {
            // Restore original language
            Language::setLanguage($originalLanguage);
        }
    }

    public function generateChronologicalOverview(array $posts, string $urlType = 'index'): void
    {
        // Filter only published posts
        $publishedPosts = array_filter($posts, function($post) {
            return $post['status'] === Constants::POST_STATUS_PUBLISHED;
        });
        
        // Sort posts chronologically (newest first)
        usort($publishedPosts, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
        // Generate HTML for both public and edit versions
        $htmlPublic = $this->renderChronologicalPageHtml($publishedPosts, 'index');
        $htmlEdit = $this->renderChronologicalPageHtml($publishedPosts, 'edit');
        
        // Output both versions
        $outputPathPublic = dirname($this->cacheDir) . '/index3.html';  // Public version
        $outputPathEdit = dirname($this->cacheDir) . '/index3a.html';   // Edit version
        
        Utils::writeFile($outputPathPublic, $htmlPublic);
        Utils::writeFile($outputPathEdit, $htmlEdit);
    }

    private function renderCategoriesPageHtml(array $categories, string $urlType = 'index', int $totalPosts = 0): string
    {
        $totalCategories = count($categories);
        
        $isEdit = ($urlType === 'edit');
        
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="<?= Language::getCurrentLanguage() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Language::getText($isEdit ? 'admin_categories_overview' : 'categories_overview') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { 
            margin: 0; 
            font-family: Arial, sans-serif;
            line-height: 1.1;
            color: #000;
            background: #fff;
            padding: 0.8rem;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
        }
        .header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.75rem;
        }
        .header h1 {
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
            color: #000;
        }
        .header .stats {
            font-size: 0.85rem;
            color: #666;
        }
        .categories-container {
            column-count: 3;
            column-gap: 2rem;
            column-fill: balance;
        }
        @media (max-width: 900px) {
            .categories-container {
                column-count: 2;
            }
        }
        @media (max-width: 600px) {
            .categories-container {
                column-count: 1;
            }
        }
        .category-section {
            break-inside: avoid;
            margin-bottom: 1.5rem;
            background: #f0f0f0;
            padding: 1rem;
            border-radius: 4px;
        }
        .category-title {
            font-size: 1rem;
            font-weight: bold;
            margin: 0 0 0.5rem 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 0.25rem;
        }
        .category-title a {
            color: #000;
            text-decoration: none;
        }
        .category-title a:hover {
            color: #0b79d0;
            text-decoration: underline;
        }
        .posts-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .post-item {
            margin-bottom: 0.4rem;
        }
        .post-link {
            text-decoration: none;
            color: #0b79d0;
            font-size: 0.8rem;
            line-height: 1.1;
            display: block;
        }
        .post-link:hover {
            text-decoration: underline;
        }
        .post-link-name {
            color: #25b4c7ff;
        }
        .back-link {
            display: inline-block;
            color: #000;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="<?= htmlspecialchars($this->baseUrl) ?>/" class="back-link">← <?= Language::getText('back_to_blog') ?></a>
            <h1><?= Language::getText($isEdit ? 'admin_categories_overview' : 'categories_overview') ?></h1>
            <div class="stats"><?= Language::getTextf('posts_in_categories', $totalPosts, $totalCategories) ?></div>
        </div>
        
        <div class="categories-container">
<?php foreach ($categories as $categoryName => $categoryPosts): ?>
            <div class="category-section">
                <h2 class="category-title">
                    <a href="<?= htmlspecialchars($this->baseUrl) ?>/index.php?tag=<?= urlencode((string)$categoryName) ?>"><?= htmlspecialchars((string)$categoryName) ?> (<?= count($categoryPosts) ?>)</a>
                </h2>
                <ul class="posts-list">
<?php foreach ($categoryPosts as $post): ?>
                    <li class="post-item">
                        <a href="<?php if ($isEdit) {
                            echo htmlspecialchars($this->baseUrl) . '/admin.php?action=editor&id=' . $post['id'];
                        } else {
                            echo htmlspecialchars($this->baseUrl) . '/index.php?id=' . $post['id'];
                        }
?>" class="post-link" <?= $isEdit ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($post['title']) ?> (<?php
                            $date = new \DateTime('@' . $post['timestamp']);
                            echo $date->format('d.m.Y');
?>)</a>
<?php if (isset($post['name']) && $post['name'] !== ''): ?>
                        <a href="<?= htmlspecialchars($this->baseUrl) ?>/index.php?name=<?= urlencode($post['name']) ?>" class="post-link post-link-name"><?= htmlspecialchars($post['title']) ?> (name=<?= htmlspecialchars($post['name']) ?>)</a>
<?php endif; ?>
                    </li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }

    private function renderChronologicalPageHtml(array $posts, string $urlType = 'index'): string
    {
        $isEdit = ($urlType === 'edit');
        
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="<?= Language::getCurrentLanguage() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Language::getText($isEdit ? 'admin_chronological_overview' : 'chronological_overview') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { 
            margin: 0; 
            font-family: Arial, sans-serif;
            line-height: 1.1;
            color: #000;
            background: #fff;
            padding: 0.8rem;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
        }
        .header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.75rem;
        }
        .header h1 {
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
            color: #000;
        }
        .header .stats {
            font-size: 0.85rem;
            color: #666;
        }
        .posts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .posts-table th {
            background: #f0f0f0;
            padding: 0.4rem;
            text-align: left;
            border-bottom: 2px solid #ddd;
            font-weight: bold;
            font-size: 0.75rem;
        }
        .posts-table td {
            padding: 0.3rem 0.4rem;
            border-bottom: 1px solid #eee;
            vertical-align: top;
            line-height: 1.0;
        }
        .posts-table td.post-date {
            vertical-align: middle;
        }
        .posts-table tbody tr:hover {
            background: #f9f9f9;
        }
        .post-title {
            color: #0b79d0;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
        }
        .post-title:hover {
            text-decoration: underline;
        }
        .post-title-name {
            color: #25b4c7ff;
        }
        .post-date {
            white-space: nowrap;
            color: #666;
            font-size: 0.7rem;
        }
        .post-categories {
            font-size: 0.65rem;
        }
        .category-tag {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 1px 4px;
            margin: 1px 1px 1px 0;
            border-radius: 2px;
            text-decoration: none;
            border: 1px solid #dee2e6;
        }
        .category-tag:hover {
            background: #d1ecf1;
            color: #0c5460;
        }
        .back-link {
            display: inline-block;
            color: #000;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: bold;
            font-size: 1rem;
        }
        @media (max-width: 768px) {
            .posts-table {
                font-size: 0.75rem;
            }
            .posts-table th,
            .posts-table td {
                padding: 0.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="<?= htmlspecialchars($this->baseUrl) ?>/" class="back-link">← <?= Language::getText('back_to_blog') ?></a>
            <h1><?= Language::getText($isEdit ? 'admin_chronological_overview' : 'chronological_overview') ?></h1>
            <div class="stats"><?= Language::getTextf('total_posts_chronological', count($posts)) ?></div>
        </div>
        
        <table class="posts-table">
            <thead>
                <tr>
                    <th><?= Language::getText('date') ?></th>
                    <th><?= Language::getText('title') ?></th>
                    <th><?= Language::getText('tags') ?></th>
                    <th style="text-align: right;"><?= Language::getText('reading_time_label') ?></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($posts as $post): ?>
                <tr>
                    <td class="post-date"><?php
                        $date = new \DateTime('@' . $post['timestamp']);
                        echo htmlspecialchars($date->format('d.m.Y'));
?></td>
                    <td><a href="<?php if ($isEdit) {
                        echo htmlspecialchars($this->baseUrl) . '/admin.php?action=editor&id=' . $post['id'];
                    } else {
                        echo htmlspecialchars($this->baseUrl) . '/index.php?id=' . $post['id'];
                    }
?>" class="post-title" <?= $isEdit ? 'target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($post['title']) ?></a>
<?php if (isset($post['name']) && $post['name'] !== ''): ?>
<br><a href="<?= htmlspecialchars($this->baseUrl) ?>/index.php?name=<?= urlencode($post['name']) ?>" class="post-title post-title-name"><?= htmlspecialchars($post['title']) ?> (name=<?= htmlspecialchars($post['name']) ?>)</a>
<?php endif; ?>
</td>
                    <td class="post-categories">
<?php if (empty($post['tags'])): ?>
<span class="category-tag"><a href="<?= htmlspecialchars($this->baseUrl) ?>/index.php?tag=<?= urlencode(Language::getText('uncategorized')) ?>" class="category-tag"><?= htmlspecialchars(Language::getText('uncategorized')) ?></a></span>
<?php else: ?>
<?php foreach ($post['tags'] as $tag): ?>
<a href="<?= htmlspecialchars($this->baseUrl) ?>/index.php?tag=<?= urlencode((string)$tag) ?>" class="category-tag"><?= htmlspecialchars((string)$tag) ?></a>
<?php endforeach; ?>
<?php endif; ?>
</td>
                    <td class="post-reading-time" style="text-align: right; white-space: nowrap; color: #666; font-size: 0.7rem;">
                        <?php if (isset($post['reading_time'])): ?>
                            <?= Language::getTextf('reading_time', $post['reading_time']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
        return ob_get_clean();
    }

    /**
     * Generate signature HTML files for all languages
     */
    public function generateSignature(array $posts): void
    {
        $supportedLanguages = ['de', 'en'];
        
        foreach ($supportedLanguages as $lang) {
            $this->generateSignatureForLanguage($posts, $lang);
        }
    }

    /**
     * Generate signature HTML file for a specific language
     */
    private function generateSignatureForLanguage(array $posts, string $lang): void
    {
        // Try language-specific signature first, then fallback
        $signaturePage = $this->getPage('signature-' . $lang);
        if (!$signaturePage) {
            $signaturePage = $this->getPage('signature'); // fallback to generic signature
        }
        
        if (!$signaturePage) {
            // Create fallback signature if no template exists
            Language::setLanguage($lang);
            $signatureHtml = '<p>' . Language::getText('built_with') . '</p>';
        } else {
            // Get the correct signature template path
            $signaturePath = $this->pagesDir . '/signature-' . $lang . '.md';
            if (!file_exists($signaturePath)) {
                $signaturePath = $this->pagesDir . '/signature.md'; // fallback
            }
            
            $parsed = $this->markdownProcessor->readMarkdownWithMeta($signaturePath);
            
            // Replace BASE_URL placeholder in the raw markdown
            $markdownBody = str_replace('<!-- BASE_URL_PLACEHOLDER -->', $this->baseUrl, $parsed['body']);
            
            // Convert to HTML
            $signatureHtml = RenderMarkdown::toHtml($markdownBody);
        }

        // Collect categories from published posts
        $categories = [];
        foreach ($posts as $post) {
            if ($post['status'] !== Constants::POST_STATUS_PUBLISHED) continue;
            
            if (empty($post['tags'])) {
                // Use language-specific "Uncategorized" label for display
                Language::setLanguage($lang);
                $uncategorizedLabel = Language::getText('uncategorized');
                $categories[$uncategorizedLabel] = ($categories[$uncategorizedLabel] ?? 0) + 1;
            } else {
                foreach ($post['tags'] as $tag) {
                    $categories[$tag] = ($categories[$tag] ?? 0) + 1;
                }
            }
        }
        
        // Sort categories alphabetically, but put uncategorized at the end
        Language::setLanguage($lang);
        $uncategorizedLabel = Language::getText('uncategorized');
        $uncategorizedCategory = null;
        if (isset($categories[$uncategorizedLabel])) {
            $uncategorizedCategory = $categories[$uncategorizedLabel];
            unset($categories[$uncategorizedLabel]);
        }
        
        ksort($categories, SORT_NATURAL | SORT_FLAG_CASE);
        
        // Add uncategorized back at the end if it exists
        if ($uncategorizedCategory !== null) {
            $categories[$uncategorizedLabel] = $uncategorizedCategory;
        }

        // Generate categories HTML - horizontal layout with separators
        $categoryLinks = [];
        foreach ($categories as $categoryName => $count) {
            // For uncategorized, use "Querbeet" in URL but display in current language
            $urlTag = ($categoryName === $uncategorizedLabel) ? 'Querbeet' : $categoryName;
            
            $categoryLinks[] = sprintf(
                '<a href="%s/index.php?tag=%s">%s</a>',
                htmlspecialchars($this->baseUrl),
                urlencode((string)$urlTag),
                htmlspecialchars((string)$categoryName)
            );
        }
        $categoriesHtml = implode(' • ', $categoryLinks);

        // Replace categories placeholder in the HTML
        $signatureHtml = str_replace('<!-- CATEGORIES_PLACEHOLDER -->', $categoriesHtml, $signatureHtml);

        // Save the language-specific signature
        $signatureFile = $this->cacheDir . '/signature-' . $lang . '.html';
        Utils::writeFile($signatureFile, $signatureHtml);
    }

    /**
     * Get signature HTML for current language
     */
    public function getSignatureHtml(): string
    {
        $currentLang = Language::getCurrentLanguage();
        $signatureFile = $this->cacheDir . '/signature-' . $currentLang . '.html';
        
        if (file_exists($signatureFile)) {
            return Utils::readFile($signatureFile);
        }
        
        // Fallback if language-specific signature doesn't exist
        $fallbackSignatureFile = $this->cacheDir . '/signature-de.html';
        if (file_exists($fallbackSignatureFile)) {
            return Utils::readFile($fallbackSignatureFile);
        }
        
        // Last resort fallback
        return '<p>' . Language::getText('built_with') . '</p>';
    }

    private function getPage(string $name): ?array
    {
        // Use page name directly
        $path = $this->pagesDir . '/' . $name . '.md';
        if (!is_file($path)) return null;
        $parsed = $this->markdownProcessor->readMarkdownWithMeta($path);
        $html = RenderMarkdown::toHtml($parsed['body']);
        return ['meta' => $parsed['meta'], 'html' => $html];
    }
}
