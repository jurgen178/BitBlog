<?php
declare(strict_types=1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/Constants.php';
require __DIR__ . '/src/Utils.php';
require __DIR__ . '/src/RenderMarkdown.php';
require __DIR__ . '/src/MarkdownProcessor.php';
require __DIR__ . '/src/IndexManager.php';
require __DIR__ . '/src/PageGenerator.php';
require __DIR__ . '/src/Content.php';
require __DIR__ . '/src/Language.php';

use BitBlog\Config;
use BitBlog\Constants;
use BitBlog\Utils;
use BitBlog\Language;
use BitBlog\Content;

// Set timezone
$timezone = Config::get('TIMEZONE');
date_default_timezone_set($timezone);

// Translation function for status display
function translateStatus(string $status): string {
    return Language::getText("status_$status");
}

// Authentication functions - define directly here
function require_login() {
    if (empty($_SESSION[Constants::SESSION_ADMIN])) {
        header('Location: admin.php?action=login');
        exit;
    }
}

function csrf_token(): string {
    if (empty($_SESSION[Constants::SESSION_CSRF])) {
        $_SESSION[Constants::SESSION_CSRF] = bin2hex(random_bytes(16));
    }
    return $_SESSION[Constants::SESSION_CSRF];
}

function check_csrf() {
    if (empty($_POST[Constants::SESSION_CSRF]) || $_SESSION[Constants::SESSION_CSRF] !== $_POST[Constants::SESSION_CSRF]) {
        http_response_code(400);
        echo 'Invalid CSRF token';
        exit;
    }
}

// Simple admin router based on action parameter
$action = $_GET['action'] ?? 'dashboard';

switch ($action) {
    case 'login':
        include __DIR__ . '/admin/login.php';
        break;
        
    case 'logout':
        include __DIR__ . '/admin/logout.php';
        break;
        
    case 'editor':
        require_login();
        include __DIR__ . '/admin/editor.php';
        break;
        
    case 'rebuild':
        require_login();
        include __DIR__ . '/admin/rebuild.php';
        break;
        
    case 'delete':
        require_login();
        include __DIR__ . '/admin/delete.php';
        break;
        
    case 'settings':
        require_login();
        include __DIR__ . '/admin/settings.php';
        break;
        
    case 'signature':
        require_login();
        include __DIR__ . '/admin/signature.php';
        break;
        
    case 'dashboard':
    default:
        // DASHBOARD - Only this is integrated into admin.php
        require_login();
        $content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
        $posts = $content->getIndex();
        
        // Count posts by status for filter display
        $allPosts = $posts;
        $statusCounts = [
            'all' => count($allPosts),
            Constants::POST_STATUS_PUBLISHED => count(array_filter($allPosts, fn($p) => $p['status'] === Constants::POST_STATUS_PUBLISHED)),
            Constants::POST_STATUS_DRAFT => count(array_filter($allPosts, fn($p) => $p['status'] === Constants::POST_STATUS_DRAFT)),
            Constants::POST_STATUS_PRIVATE => count(array_filter($allPosts, fn($p) => $p['status'] === Constants::POST_STATUS_PRIVATE)),
        ];
        
        // Apply status filter if provided
        $statusFilter = $_GET['status'] ?? 'all';
        if ($statusFilter !== 'all') {
            $posts = array_filter($posts, fn($p) => $p['status'] === $statusFilter);
        }
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin</title>
<link rel="stylesheet" href="<?= Config::BASE_URL() ?>/admin/admin.css">
</head><body>
<header>
  <strong>📊 <?= Language::getText('admin_panel') ?></strong>
  <nav>
    <a href="admin.php?action=editor">📝 <?= Language::getText('new_post') ?></a>
    <a href="admin.php?action=settings">⚙️ <?= Language::getText('settings') ?></a>
    <a href="admin.php?action=rebuild">🔄 <?= Language::getText('rebuild_index') ?></a>
    <a href="admin.php?action=logout">🚪 <?= Language::getText('logout') ?></a>
  </nav>
</header>
<main>
<?php if (!empty($_GET['rebuilt'])): ?>
  <p class="notice success">
    <?= Language::getText('rebuild_success') ?><br>
    <?= Language::getText('overview_created') ?> <a href="index2.html" target="_blank">→ <?= Language::getText('view') ?></a><br>
    <?= Language::getText('admin_overview_created') ?> <a href="index2a.html" target="_blank">→ <?= Language::getText('view') ?></a><br>
    <?= Language::getText('chronological_created') ?> <a href="index3.html" target="_blank">→ <?= Language::getText('view') ?></a><br>
    <?= Language::getText('chronological_admin_created') ?> <a href="index3a.html" target="_blank">→ <?= Language::getText('view') ?></a><?php
      $postCount = (int)($_GET['post_count'] ?? 0);
      if ($postCount > 0) {
        echo "<br>📄 " . Language::getTextf('posts_found', $postCount);
      }
      if (file_exists(__DIR__ . '/blog-content.zip')) {
        echo "<br>📦 <a href='blog-content.zip' download>" . Language::getText('download_blog_archive') . "</a>";
      }
?>
  </p>
<?php endif; ?>

<?php if (!empty($_GET['deleted'])): ?>
  <p class="notice success">
    ✅ <?= Language::getText('post_deleted') ?>
  </p>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
  <p class="notice error">
    ❌ <?= Language::getText('error') ?>: <?php
      switch($_GET['error']) {
        case 'invalid_id': echo Language::getText('invalid_id'); break;
        case 'post_not_found': echo Language::getText('post_not_found'); break;
        case 'delete_failed': echo Language::getText('delete_failed'); break;
        case 'file_not_found': echo Language::getText('file_not_found'); break;
        case 'invalid_parameters': echo Language::getText('invalid_parameters'); break;
        default: echo Language::getText('unknown_error');
      }
?>
  </p>
<?php endif; ?>
  <h2><?= Language::getText('posts') ?></h2>
  <div class="settings-filter">
    <label><?= Language::getText('status') ?>: </label>
    <select onchange="window.location.href='admin.php?status='+this.value" class="filter-select">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>><?= Language::getText('status_all') ?> (<?= $statusCounts['all'] ?>)</option>
      <option value="<?= Constants::POST_STATUS_PUBLISHED ?>" <?= $statusFilter === Constants::POST_STATUS_PUBLISHED ? 'selected' : '' ?>><?= Language::getText('status_published') ?> (<?= $statusCounts[Constants::POST_STATUS_PUBLISHED] ?>)</option>
      <option value="<?= Constants::POST_STATUS_DRAFT ?>" <?= $statusFilter === Constants::POST_STATUS_DRAFT ? 'selected' : '' ?>><?= Language::getText('status_draft') ?> (<?= $statusCounts[Constants::POST_STATUS_DRAFT] ?>)</option>
      <option value="<?= Constants::POST_STATUS_PRIVATE ?>" <?= $statusFilter === Constants::POST_STATUS_PRIVATE ? 'selected' : '' ?>><?= Language::getText('status_private') ?> (<?= $statusCounts[Constants::POST_STATUS_PRIVATE] ?>)</option>
    </select>
  </div>
  <table class="table">
    <thead><tr><th>ID</th><th><?= Language::getText('title') ?></th><th>📅 <?= Language::getText('date') ?></th><th><?= Language::getText('status') ?></th><th><?= Language::getText('actions') ?></th></tr></thead>
    <tbody>
<?php foreach ($posts as $p): ?>
      <tr>
        <td><a href="index.php?id=<?= $p['id'] ?><?= ($p['status'] === Constants::POST_STATUS_PRIVATE && isset($p['token'])) ? '&token=' . urlencode($p['token']) : '' ?>" target="_blank" class="post-id-link"><strong>#<?= $p['id'] ?></strong></a></td>
        <td>
          <a href="admin.php?action=editor&id=<?= $p['id'] ?>" class="post-title-link"><?= htmlspecialchars($p['title']) ?></a>
        </td>
        <td><a href="admin.php?action=editor&id=<?= $p['id'] ?>" class="post-date-link">📅 <?php
          $dateFormatter = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
          $timeFormatter = new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::NONE, IntlDateFormatter::SHORT);
          echo $dateFormatter->format($p['timestamp']) . ' · ' . $timeFormatter->format($p['timestamp']);
?></a></td>
        <td><a href="admin.php?action=editor&id=<?= $p['id'] ?>" class="post-status-link"><?php if ($p['status'] === Constants::POST_STATUS_PRIVATE): ?>🔒 <?php endif; ?><?= htmlspecialchars(translateStatus($p['status'])) ?></a></td>
        <td>
          <a href="admin.php?action=editor&id=<?= $p['id'] ?>" title="<?= Language::getText('edit') ?>">✏️</a>
<?php if ($p['status'] === Constants::POST_STATUS_PRIVATE && isset($p['token'])): ?>
          <a href="index.php?id=<?= $p['id'] ?>&token=<?= urlencode($p['token']) ?>" target="_blank" title="<?= Language::getText('view') ?>">📄</a>
<?php else: ?>
          <a href="index.php?id=<?= $p['id'] ?>" target="_blank" title="<?= Language::getText('view') ?>">📄</a>
<?php endif; ?>
          <a href="admin.php?action=delete&id=<?= $p['id'] ?>" class="post-delete-link" title="<?= Language::getText('delete') ?>">🗑️</a>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table>
</main>
</body></html>
<?php
        break;
}
?>
