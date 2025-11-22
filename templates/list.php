<?php
use BitBlog\Utils;
use BitBlog\Language;
$title = $title ?? Language::getText('home');
?>
<?php if (empty($posts)): ?>
  <p><?= Language::getText('no_posts') ?></p>
<?php else: ?>
  <ul class="post-list">
    <?php foreach ($posts as $p): ?>
      <li class="post-item">
        <h3><a href="<?= Utils::e($p['url']) ?>"><?= Utils::e($p['title']) ?></a></h3>
        <div class="meta">
          <time datetime="<?= Utils::e(Utils::iso($p['timestamp'])) ?>">📅 <?php 
            $formatter = new IntlDateFormatter(
              Language::getLocale(), 
              IntlDateFormatter::LONG, 
              IntlDateFormatter::NONE
            );
            echo $formatter->format($p['timestamp']);
          ?></time>
          <?php if (!empty($p['tags'])): ?>
            <span>·</span>
            <?php foreach ($p['tags'] as $i => $t): ?>
              <?php $displayTag = Language::translateTagName($t); ?>
              <a href="<?= Utils::e($baseUrl) ?>/index.php?tag=<?= urlencode((string)$t) ?>"><?= Utils::e($displayTag) ?></a><?= $i < count($p['tags']) - 1 ? ', ' : '' ?>
<?php endforeach; ?>
<?php endif; ?>
          <?php if (isset($p['reading_time'])): ?>
            <span>·</span>
            <span>⏱️ <?= Language::getTextf('reading_time', $p['reading_time']) ?></span>
          <?php endif; ?>
          <button class="share-button" onclick="sharePost(this)" data-url="<?= Utils::e($p['url']) ?>" data-title="<?= Utils::e($p['title']) ?>" title="<?= Language::getText('share_post') ?>" aria-label="<?= Language::getText('share_post') ?>">🔗</button>
        </div>
        <br />
        <div class="content">
          <?= $p['html'] ?? '' ?>
        </div>
      </li>
<?php endforeach; ?>
  </ul>

  <?php if (($totalPages ?? 1) > 1): ?>
    <div class="pagination">
      <?php 
        $currentPage = $page ?? 1;
        $tagParam = isset($tag) ? '&tag=' . urlencode((string)$tag) : '';
        
        // Tooltips are pre-calculated and passed from index.php
        $newerTooltip = $newerTooltip ?? '';
        $olderTooltip = $olderTooltip ?? '';
      ?>
      
      <?php if ($currentPage > 1): ?>
        <a href="<?= Utils::e($baseUrl) ?>/index.php?p=<?= $currentPage - 1 ?><?= $tagParam ?>" 
           class="pagination-link"
           <?= $newerTooltip ? 'title="' . Utils::e($newerTooltip) . '"' : '' ?>>
          ← <?= Language::getText('newer_posts') ?>
        </a>
<?php endif; ?>
      
      <span class="pagination-info">
        <?= Language::getTextf('page_of', $currentPage, $totalPages) ?>
      </span>
      
      <?php if ($currentPage < $totalPages): ?>
        <a href="<?= Utils::e($baseUrl) ?>/index.php?p=<?= $currentPage + 1 ?><?= $tagParam ?>" 
           class="pagination-link"
           <?= $olderTooltip ? 'title="' . Utils::e($olderTooltip) . '"' : '' ?>>
          <?= Language::getText('older_posts') ?> →
        </a>
<?php endif; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
