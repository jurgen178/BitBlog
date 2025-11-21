<?php
require_login();

use BitBlog\Config;
use BitBlog\Language;
use BitBlog\Constants;

?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Language::getText('archive') ?> - Admin</title>
<link rel="stylesheet" href="<?= Config::BASE_URL() ?>/admin/admin.css">
</head><body>
<header>
  <strong>📦 <?= Language::getText('archive') ?></strong>
  <nav>
    <a href="admin.php"><?= Language::getText('back_to_dashboard') ?></a>
  </nav>
</header>
<main>
  <h2><?= Language::getText('backup_restore') ?></h2>
  
  <?php if (isset($_GET['restored'])): ?>
    <p class="notice success">✅ <?= Language::getText('restore_success') ?></p>
  <?php endif; ?>
  
  <?php if (isset($_GET['deleted'])): ?>
    <p class="notice success">✅ <?= Language::getText('backup_deleted') ?></p>
  <?php endif; ?>
  
  <?php if (isset($_GET['created'])): ?>
    <p class="notice success">✅ <?= Language::getText('archive_created') ?></p>
  <?php endif; ?>
  
  <!-- Download Section -->
  <section class="archive-section">
    <h3>📥 <?= Language::getText('download_backup') ?></h3>
    <p><?= Language::getText('download_backup_description') ?></p>
    
    <p>
      <a href="admin.php?action=create_archive" class="button">🔄 <?= Language::getText('create_archive_now') ?></a>
    </p>
    
    <p class="info-text">
      ℹ️ <?= Language::getText('archive_download_info') ?>
    </p>
  </section>
  
  <hr>
  
  <!-- Upload Section -->
  <section class="archive-section">
    <h3>📤 <?= Language::getText('upload_archive') ?></h3>
    <p><?= Language::getText('upload_archive_description') ?></p>
    
    <form action="admin.php?action=upload" method="post" enctype="multipart/form-data" 
          onsubmit="return confirm('<?= Language::getText('confirm_upload') ?>')">
      <input type="hidden" name="<?= Constants::SESSION_CSRF ?>" value="<?= csrf_token() ?>">
      
      <p class="warning-text">⚠️ <?= Language::getText('upload_warning') ?></p>
      
      <div class="form-group">
        <label for="archive"><?= Language::getText('select_archive_file') ?>:</label>
        <input type="file" name="archive" id="archive" accept=".zip" required>
      </div>
      
      <p class="info-text">
        📋 <?= Language::getText('upload_requirements') ?>
      </p>
      
      <button type="submit" class="button">📤 <?= Language::getText('upload') ?></button>
    </form>
  </section>
  
  <!-- Backup History -->
  <hr>
  
  <section class="archive-section">
    <h3>📂 <?= Language::getText('backup_history') ?></h3>
    <p><?= Language::getText('backup_history_description') ?></p>
    
    <?php
    // Get all backup ZIP files
    $allBackups = array_merge(
      glob(__DIR__ . '/../archive/archive-*.zip') ?: [],         // Auto-archives before upload/restore
      glob(__DIR__ . '/../archive/blog-content-*.zip') ?: [],    // Manual archives from rebuild
      glob(__DIR__ . '/../archive/uploaded-*.zip') ?: []         // Uploaded archives (if deletion failed)
    );
    
    if (!empty($allBackups)):
      // Sort by modification time, newest first
      usort($allBackups, function($a, $b) {
        return filemtime($b) - filemtime($a);
      });
    ?>
      <table class="backup-table">
        <thead>
          <tr>
            <th><?= Language::getText('date') ?></th>
            <th><?= Language::getText('type') ?></th>
            <th><?= Language::getText('size') ?></th>
            <th><?= Language::getText('actions') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($allBackups as $backupFile): 
          $fileName = basename($backupFile);
          $fileSize = filesize($backupFile);
          $fileSizeMB = round($fileSize / 1024 / 1024, 2);
          $fileDate = filemtime($backupFile);
          
          // Determine type
          if (str_starts_with($fileName, 'archive-')) {
            $type = '🔄 ' . Language::getText('auto_backup');
          } elseif (str_starts_with($fileName, 'uploaded-')) {
            $type = '📤 ' . Language::getText('uploaded_backup');
          } else {
            $type = '📦 ' . Language::getText('manual_backup');
          }
        ?>
          <tr>
            <td><?= date('Y-m-d H:i:s', $fileDate) ?></td>
            <td><?= $type ?></td>
            <td><?= $fileSizeMB ?> MB</td>
            <td>
              <a href="archive/<?= htmlspecialchars($fileName) ?>" download class="button small">⬇️ <?= Language::getText('download') ?></a>
              <form method="post" action="admin.php?action=restore" style="display:inline;" onsubmit="return confirm('<?= Language::getText('confirm_restore') ?>');">
                <input type="hidden" name="<?= Constants::SESSION_CSRF ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($fileName) ?>">
                <button type="submit" class="button small">↩️ <?= Language::getText('restore') ?></button>
              </form>
              <form method="post" action="admin.php?action=delete_backup" style="display:inline;" onsubmit="return confirm('<?= Language::getText('confirm_delete_backup') ?>');">
                <input type="hidden" name="<?= Constants::SESSION_CSRF ?>" value="<?= csrf_token() ?>">
                <input type="hidden" name="backup_file" value="<?= htmlspecialchars($fileName) ?>">
                <button type="submit" class="button small" style="background:#dc3545;">🗑️ <?= Language::getText('delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="notice info">ℹ️ <?= Language::getText('no_backups_found') ?></p>
    <?php endif; ?>
  </section>
  
</main>
</body></html>
