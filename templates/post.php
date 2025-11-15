<?php
use BitBlog\Utils;
use BitBlog\Language;
$title = $post['title'];
?>
<article class="post">
  <header>
    <br />
    <h3><?= Utils::e($post['title']) ?></h3>
    <div class="meta">
      <time datetime="<?= Utils::e(Utils::iso($post['timestamp'])) ?>">📅 <?php 
        $formatter = new IntlDateFormatter(
          Language::getLocale(), 
          IntlDateFormatter::LONG, 
          IntlDateFormatter::NONE
        );
        echo $formatter->format($post['timestamp']);
      ?></time>
      <?php if (!empty($post['tags'])): ?>
        <span>·</span>
        <?php foreach ($post['tags'] as $i => $t): ?>
          <?php $displayTag = Language::translateTagName($t); ?>
          <a href="<?= Utils::e($baseUrl) ?>/index.php?tag=<?= urlencode((string)$t) ?>"><?= Utils::e($displayTag) ?></a><?= $i < count($post['tags']) - 1 ? ', ' : '' ?>
<?php endforeach; ?>
<?php endif; ?>
    </div>
  </header>
  <br />
  <section class="content">
    <?= $post['html'] ?>
    <br />
  </section>
</article>
