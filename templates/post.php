<?php
use BitBlog\Utils;
use BitBlog\Language;
$title = $post['title'];

// Share URL should preserve how the post is accessed (e.g. ?name=...),
// and also preserve an optional ?token= for private posts.
$shareParams = [];
if (!empty($_GET['file'])) {
  $shareParams['file'] = (string)$_GET['file'];
} elseif (!empty($_GET['name'])) {
  $shareParams['name'] = (string)$_GET['name'];
} else {
  $shareParams['id'] = (string)$post['id'];
}
if (!empty($_GET['token'])) {
  $shareParams['token'] = (string)$_GET['token'];
}
$shareUrl = $baseUrl . '/index.php?' . http_build_query($shareParams);
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
      <button class="share-button" onclick="sharePost(this)" data-url="<?= Utils::e($shareUrl) ?>" data-title="<?= Utils::e($post['title']) ?>" data-copy-link-text="<?= Language::getText('copy_link') ?>" title="<?= Language::getText('share_post') ?>" aria-label="<?= Language::getText('share_post') ?>">🔗</button>
    </div>
  </header>
  <br />
  <section class="content">
    <?= $post['html'] ?>
    <br />
  </section>
</article>
