<?php
// Dependencies are already loaded by admin.php
use BitBlog\Config;
use BitBlog\Constants;
use BitBlog\Language;

$message = '';
$messageType = 'success';

// Check if we were redirected after successful save
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = '✅ ' . Language::getText('settings_saved');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (empty($_POST[Constants::SESSION_CSRF]) || $_SESSION[Constants::SESSION_CSRF] !== $_POST[Constants::SESSION_CSRF]) {
        $message = Language::getText('invalid_security_token');
        $messageType = 'error';
    } else {
        // Save settings
        $settings = [];
        foreach (Config::CONFIGURABLE_SETTINGS as $key => $config) {
            if (isset($_POST[$key])) {
                $settings[$key] = $_POST[$key];
            }
        }
        
        if (Config::saveSettings($settings)) {
            // Clear any potential config caches
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Redirect to reload page with updated settings
            header('Location: admin.php?action=settings&saved=1');
            exit;
        } else {
            $message = '❌ ' . Language::getText('settings_save_failed');
            $messageType = 'error';
        }
    }
}

// Get current settings
$currentSettings = Config::getAllSettings();

// Generate CSRF token
if (empty($_SESSION[Constants::SESSION_CSRF])) {
    $_SESSION[Constants::SESSION_CSRF] = bin2hex(random_bytes(16));
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Language::getText('settings') ?> - Admin</title>
<link rel="stylesheet" href="<?= Config::BASE_URL() ?>/admin/admin.css">
</head><body>
<header>
  <strong>⚙️ <?= Language::getText('settings') ?></strong>
  <nav>
    <button type="submit" form="settings-form">💾 <?= Language::getText('save_settings') ?></button>
    <a href="admin.php?action=signature">📄 <?= Language::getText('signature_editor') ?></a>
    <a href="admin.php">📊 <?= Language::getText('dashboard') ?></a>
  </nav>
</header>
<main>
  <?php if ($message): ?>
    <p class="notice <?= $messageType === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($message, ENT_QUOTES) ?>
    </p>
<?php endif; ?>

  <form method="post" id="settings-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION[Constants::SESSION_CSRF], ENT_QUOTES) ?>">
    
    <?php 
    $groups = [];
    foreach (Config::CONFIGURABLE_SETTINGS as $key => $config) {
        $groups[$config['group']][] = [$key, $config];
    }
    ?>
    
    <?php foreach ($groups as $groupName => $groupSettings): ?>
      <fieldset class="settings-fieldset">
        <legend><?= Language::getText($groupName . '_settings') ?></legend>
        
        <?php foreach ($groupSettings as [$key, $config]): ?>
          <?php $fieldId = 'setting_' . strtolower($key); ?>
          <div class="settings-field">
            <label for="<?= $fieldId ?>"><?= Language::getText($config['label']) ?>:</label>
            
            <?php if ($config['type'] === 'text'): ?>
              <input type="text" id="<?= $fieldId ?>" name="<?= $key ?>" 
                     value="<?= htmlspecialchars($currentSettings[$key], ENT_QUOTES) ?>">
                     
            <?php elseif ($config['type'] === 'number'): ?>
              <input type="number" id="<?= $fieldId ?>" name="<?= $key ?>" 
                     value="<?= $currentSettings[$key] ?>"
                     min="<?= $config['min'] ?? '' ?>" 
                     max="<?= $config['max'] ?? '' ?>">
                     
            <?php elseif ($config['type'] === 'select'): ?>
              <select id="<?= $fieldId ?>" name="<?= $key ?>">
                <?php foreach ($config['options'] as $optionValue => $optionLabel): ?>
                  <?php 
                    // Support both array formats: ['value'] and ['key' => 'label']
                    if (is_numeric($optionValue)) {
                      $optionValue = $optionLabel;
                    }
                  ?>
                  <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES) ?>" <?= $currentSettings[$key] === $optionValue ? 'selected' : '' ?>>
                    <?= htmlspecialchars($optionLabel, ENT_QUOTES) ?>
                  </option>
<?php endforeach; ?>
              </select>
<?php endif; ?>
          </div>
<?php endforeach; ?>
      </fieldset>
<?php endforeach; ?>
  </form>
</main>
</body></html>