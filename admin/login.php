<?php
// Dependencies are already loaded by admin.php
use BitBlog\Config;
use BitBlog\Constants;
use BitBlog\Language;
use BitBlog\Content;

// Check if setup mode is needed
$setupMode = false;
$setupHash = '';
if (Config::ADMIN_USER === '' && Config::ADMIN_PASSWORD_HASH === '') {
    // Safety check: only allow setup mode for new blogs (less than 3 published posts)
    // This prevents accidental exposure of setup mode on existing blogs
    $content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
    $posts = $content->getIndex();
    $publishedPosts = array_filter($posts, fn($p) => $p['status'] === Constants::POST_STATUS_PUBLISHED);
    
    if (count($publishedPosts) < 3) {
        $setupMode = true;
    }
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF check
    if (empty($_POST[Constants::SESSION_CSRF]) || empty($_SESSION[Constants::SESSION_CSRF]) || $_SESSION[Constants::SESSION_CSRF] !== $_POST[Constants::SESSION_CSRF]) {
        $err = Language::getText('invalid_security_token');
    } else {
        // Setup mode: generate password hash
        if ($setupMode && isset($_POST['setup'])) {
            $u = trim($_POST['user'] ?? '');
            $p = $_POST['pass'] ?? '';
            
            if ($u === '' || $p === '') {
                $err = Language::getText('setup_please_enter_credentials');
            } else {
                // Generate bcrypt hash
                $setupHash = password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
                // Don't clear form, show result instead
            }
        } 
        // Normal login mode
        else {
            $u = $_POST['user'] ?? '';
            $p = $_POST['pass'] ?? '';
            if ($u === Config::ADMIN_USER && password_verify($p, Config::ADMIN_PASSWORD_HASH)) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION[Constants::SESSION_ADMIN] = true;
                header('Location: admin.php');
                exit;
            } else {
                $err = Language::getText('invalid_credentials');
            }
        }
    }
}

// Generate new CSRF token for each request
$_SESSION[Constants::SESSION_CSRF] = bin2hex(random_bytes(16));
?>
<!doctype html>
<html><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $setupMode ? '🚀 ' . Language::getText('setup_mode') : Language::getText('login') ?></title>
<link rel="stylesheet" href="admin/admin.css">
<style>
  .setup-result {
    background: #f0f9ff;
    border: 2px solid #0ea5e9;
    padding: 1.5rem;
    margin: 1rem 0;
    border-radius: 8px;
  }
  .setup-result h3 {
    margin-top: 0;
    color: #0369a1;
  }
  .setup-result pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 1rem;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 0.9rem;
    line-height: 1.5;
  }
  .setup-result .instruction {
    font-weight: bold;
    margin: 1rem 0 0.5rem 0;
  }
  .setup-info {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 1rem;
    margin-bottom: 1rem;
  }
</style>
</head><body>
<header><strong><?= $setupMode ? '🚀 ' . Language::getText('blog_setup') : '🔐 ' . Language::getText('admin_panel') . ' ' . Language::getText('login') ?></strong></header>
<main>
  <?php if ($setupMode): ?>
    <?php if ($setupHash): ?>
      <!-- Setup successful: show instructions -->
      <div class="setup-result">
        <h3>✅ <?= Language::getText('setup_success_title') ?></h3>
        <div class="instruction">📝 <?= Language::getText('setup_copy_instructions') ?></div>
        <pre><?= "public const ADMIN_USER = '" . htmlspecialchars($_POST['user'], ENT_QUOTES) . "';\npublic const ADMIN_PASSWORD_HASH = '" . htmlspecialchars($setupHash, ENT_QUOTES) . "';" ?></pre>
        
        <div class="instruction">📍 <?= Language::getText('setup_replace_instructions') ?></div>
        <pre><?= "public const ADMIN_USER = '';\npublic const ADMIN_PASSWORD_HASH = '';" ?></pre>
        
        <p class="login-setup-hint"><?= Language::getText('setup_after_save') ?></p>
      </div>
    <?php else: ?>
      <!-- Setup form -->
      <div class="setup-info">
        <p><strong><?= Language::getText('setup_welcome') ?></strong></p>
        <p><?= Language::getText('setup_create_admin') ?></p>
      </div>
      
      <form method="post" class="login-form">
        <?php if ($err): ?><p class="notice"><?= htmlspecialchars($err, ENT_QUOTES) ?></p><?php endif; ?>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION[Constants::SESSION_CSRF], ENT_QUOTES) ?>">
        <input type="hidden" name="setup" value="1">
        
        <label><?= Language::getText('setup_username') ?></label>
        <input type="text" name="user" value="<?= htmlspecialchars($_POST['user'] ?? '', ENT_QUOTES) ?>" autofocus required>
        
        <label><?= Language::getText('setup_password') ?></label>
        <input type="password" name="pass" required>
        
        <div class="actions">
          <button type="submit">🚀 <?= Language::getText('setup_create_account') ?></button>
        </div>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <!-- Normal login form -->
    <form method="post" class="login-form">
      <?php if ($err): ?><p class="notice"><?= htmlspecialchars($err, ENT_QUOTES) ?></p><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION[Constants::SESSION_CSRF], ENT_QUOTES) ?>">
      <label><?= Language::getText('username') ?></label>
      <input type="text" name="user" autofocus>
      <label><?= Language::getText('password') ?></label>
      <input type="password" name="pass">
      <div class="actions">
        <button type="submit"><?= Language::getText('login') ?></button>
      </div>
    </form>
  <?php endif; ?>
</main>
</body></html>
