<?php
require_login();

use BitBlog\Content;
use BitBlog\Config;
use BitBlog\Language;

// Check for invalid parameters first
$invalidParams = array_diff_key($_GET, array_flip(['action', 'id', 'id_post', 'confirm']));
if (!empty($invalidParams)) {
    header('Location: admin.php?error=invalid_parameters');
    exit;
}

$postId = (int)($_GET['id'] ?? $_GET['id_post'] ?? 0);
if ($postId === 0) {
    header('Location: admin.php?error=invalid_id');
    exit;
}

// Load content to find the post
$content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
$post = $content->getPostById($postId);

if (!$post) {
    header('Location: admin.php?error=post_not_found');
    exit;
}

// Check if confirmed
if (!isset($_GET['confirm']) || $_GET['confirm'] !== '1') {
    // Show confirmation page
    ?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Language::getText('confirm_delete_title') ?> - Admin</title>
<link rel="stylesheet" href="<?= Config::BASE_URL() ?>/admin/admin.css">
</head><body>
<header>
  <strong>🗑️ <?= Language::getText('confirm_delete_title') ?></strong>
  <nav>
    <a href="admin.php">📊 <?= Language::getText('dashboard') ?></a>
    <a href="admin.php?action=logout">🚪 <?= Language::getText('logout') ?></a>
  </nav>
</header>
<main>
  <div class="delete-confirmation">
    <h2>⚠️ <?= Language::getText('confirm_delete_title') ?></h2>
    <p class="delete-confirmation-text">
      <?= Language::getText('confirm_delete_message') ?>
    </p>
    <div class="delete-post-info">
      <strong><?= Language::getText('post_id') ?>:</strong> #<?= $postId ?><br>
      <strong><?= Language::getText('title') ?>:</strong> <?= htmlspecialchars($post['title']) ?><br>
      <strong><?= Language::getText('date') ?>:</strong> <?php
        $date = new DateTime('@' . $post['timestamp']);
        echo $date->format('d.m.Y H:i');
      ?>
    </div>
    <p class="delete-warning">
      <?= Language::getText('action_cannot_be_undone') ?>
    </p>
    <div class="delete-actions">
      <a href="admin.php?action=delete&id=<?= $postId ?>&confirm=1" class="delete-btn">
        🗑️ <?= Language::getText('yes_delete') ?>
      </a>
      <a href="admin.php" class="cancel-btn">
        ❌ <?= Language::getText('cancel') ?>
      </a>
    </div>
  </div>
</main>
</body></html>
    <?php
    exit;
}

// Delete the post file
if (file_exists($post['path'])) {
    if (unlink($post['path'])) {

        // Rebuild the index to remove the post from cache
        $content->rebuildIndex();
        
        // Redirect back with success message
        header('Location: admin.php?deleted=1');
        exit;
    } else {
        header('Location: admin.php?error=delete_failed');
        exit;
    }
} else {
    header('Location: admin.php?error=file_not_found');
    exit;
}
