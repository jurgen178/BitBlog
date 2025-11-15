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
<link rel="stylesheet" href="<?= Utils::e($baseUrl) ?>/assets/style.css">
<link rel="alternate" type="application/rss+xml" title="RSS" href="<?= Utils::e($baseUrl) ?>/rss.php">
</head>
<body>
<header class="site-header">
  <div class="container">
    <h1 class="site-title"><a href="<?= Utils::e($baseUrl) ?>"><?= Utils::e($siteTitle) ?></a></h1>
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
</body>
</html>
