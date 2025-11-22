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
      <?php if (isset($post['reading_time'])): ?>
        <span>·</span>
        <span>⏱️ <?= Language::getTextf('reading_time', $post['reading_time']) ?></span>
      <?php endif; ?>
      <button class="share-button" onclick="sharePost(this)" data-url="<?= Utils::e($baseUrl . '/index.php?id=' . $post['id']) ?>" data-title="<?= Utils::e($post['title']) ?>" title="<?= Language::getText('share_post') ?>" aria-label="<?= Language::getText('share_post') ?>">🔗</button>
    </div>
  </header>
  <br />
  <section class="content">
    <?= $post['html'] ?>
    <br />
  </section>
</article>
