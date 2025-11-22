<?php 
use BitBlog\Utils;
use BitBlog\Language; 
?>
<!doctype html>
<html lang="<?= Language::getCurrentLanguage() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Utils::e(($title ?? $siteTitle) . ' - ' . $siteTitle) ?></title>
<link rel="stylesheet" href="<?= Utils::e($baseUrl) ?>/templates/style.css">
<link rel="alternate" type="application/rss+xml" title="RSS" href="<?= Utils::e($baseUrl) ?>/rss.php">
</head>
<body>
<header class="site-header">
  <div class="container">
    <h1 class="site-title"><a href="<?= Utils::e($baseUrl) ?>"><?= Utils::e($siteTitle) ?></a></h1>
    <div class="search-container">
      <div id="search-toggle" class="search-toggle">
        <span>🔍</span>
        <span><?= Language::getText('search_placeholder') ?></span>
      </div>
      <div id="search-input-wrapper" class="search-input-wrapper">
        <input type="text" 
               id="search-input" 
               class="search-input" 
               placeholder="<?= Language::getText('search_placeholder') ?>" 
               autocomplete="off"
               data-base-url="<?= Utils::e($baseUrl) ?>"
               data-cache-version="<?= @filemtime(__DIR__ . '/../cache/search-index.json') ?: 0 ?>"
               data-no-results-text="<?= Language::getText('no_search_results') ?>"
               data-copy-link-text="<?= Language::getText('copy_link') ?>">
        <span class="search-icon">🔍</span>
        <div id="search-results" class="search-results"></div>
      </div>
    </div>
  </div>
</header>
<main class="container">
  <section class="content">
    <?= $content ?>
  </section>
</main>
<footer class="site-footer">
  <div class="container">
    <?= $signatureHtml ?>
  </div>
</footer>
<script src="<?= Utils::e($baseUrl) ?>/templates/script.js"></script>
</body>
</html>
