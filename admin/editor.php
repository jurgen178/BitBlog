<?php
/**
 * Blog Post Editor - Advanced markdown editor with Monaco Editor integration
 * 
 * Features:
 * - Visual Studio Code Editor with markdown support and syntax highlighting
 * - Fullscreen mode with draggable toolbar
 * - Live preview toggle with markdown rendering
*/

// Authentication check
require_login();

// Import required classes for content management
use BitBlog\Constants;
use BitBlog\Content;
use BitBlog\Config;
use BitBlog\Utils;
use BitBlog\Language;

// Initialize content manager with configuration
$content = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());

// Editor mode: 'new' for creating posts, 'edit' for modifying existing posts
$mode = 'new';

// Default metadata structure for new posts
$meta = [
  'title' => '', 
  'date' => gmdate('Y-m-d\TH:i:00\Z'), // ISO 8601 format in UTC
  'status' => Constants::DEFAULT_POST_STATUS,
  'tags' => []
];

// Post content and file tracking
$body = "\n";
$original_path = '';

// Handle edit mode - load existing post for modification (support both 'id' and 'id_post')
$editPostId = null;
$editError = null;

// Check for invalid URL parameters first
$invalidParams = array_diff_key($_GET, array_flip(['action', 'id', 'id_post']));
if (!empty($invalidParams)) {
    $editError = 'invalid_parameters';
}

if (!empty($_GET['id']) || !empty($_GET['id_post'])) {
    $mode = 'edit';
    $editPostId = (int)($_GET['id'] ?? $_GET['id_post']);
    
    if ($editPostId <= 0) {
        $editError = 'invalid_post_id';
    } else {
        $post = $content->getPostById($editPostId);
        if ($post) {
            // Merge post data (from index, includes 'timestamp' from filename) with meta (from YAML)
            $meta = array_merge($post, $post['meta']);
            // Load original markdown content (not HTML)
            $body = $post['html'] ? substr($post['html'], 0, -strlen($post['html'])) : '';
            // We need to read the raw file for editing
            $raw = Utils::readFile($post['path']);
            $parsed = $content->readMarkdownWithMeta($post['path'], $raw);
            $body = $parsed['body'];
            $original_path = $post['path'];
        } else {
            $editError = 'post_not_found';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    // Handle token regeneration request
    if (!empty($_POST['regenerate_token'])) {
        $post_id = (int)($_POST['post_id'] ?? 0);
        if ($post_id > 0) {
            $post = $content->getPostById($post_id);
            if ($post && $post['status'] === Constants::POST_STATUS_PRIVATE) {
                // Read existing file
                $path = $post['path'];
                $fileContent = file_get_contents($path);
                
                // Generate new token
                $newToken = Utils::generateSecureToken();
                
                // Replace token in YAML front matter
                $fileContent = preg_replace('/^token:\s*.+$/m', 'token: ' . $newToken, $fileContent);
                
                // If no token field exists, add it after status
                if (strpos($fileContent, 'token:') === false) {
                    $fileContent = preg_replace('/(status:\s*private\s*\n)/', '$1token: ' . $newToken . "\n", $fileContent);
                }
                
                // Write back
                file_put_contents($path, $fileContent);
                
                // Rebuild index to update token
                $content->rebuildAll();
                
                // Reload post data
                $editPostId = $post_id;
                $mode = 'edit';
                $post = $content->getPostById($post_id);
                $meta = $post;
                $original_path = $path;
                $parsed = $content->readMarkdownWithMeta($path);
                $body = $parsed['body'];
            }
        }
    }
    // Regular save operation
    elseif (!empty($_POST['title']) || !empty($_POST['body'])) {
      $title = trim($_POST['title'] ?? '');
      $date = trim($_POST['date'] ?? ''); // value from visible datetime-local input
      $original_date_input = trim($_POST['original_date'] ?? ''); // hidden original date value for change detection
        $status = $_POST['status'] ?? Constants::DEFAULT_POST_STATUS;
        $tags = array_values(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))));
        $body = (string)($_POST['body'] ?? '');
        $original_path = (string)($_POST['original_path'] ?? '');
        $post_id = (int)($_POST['post_id'] ?? 0);
        
        // Validate date input
        if (!empty($date)) {
            $dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $date);
            if (!$dateObj || $dateObj->format('Y-m-d\TH:i') !== $date) {
                // Invalid date format - redirect with error
                header('Location: admin.php?error=invalid_date');
                exit;
            }
        }
        
        // Load existing token if editing
        $existingToken = null;
        if ($post_id > 0 && $original_path && file_exists($original_path)) {
            $parsed = $content->readMarkdownWithMeta($original_path);
            $existingToken = $parsed['meta']['token'] ?? null;
        }
        
        // Generate token for private posts if not exists
        $token = $existingToken;
        if ($status === Constants::POST_STATUS_PRIVATE && !$token) {
            $token = Utils::generateSecureToken();
        }

        if ($title === '') { $title = Language::getText('default_post_title'); }
        
        // Create YAML - always quote title to handle special characters (escape order: \ first, then ")
        $yaml = "---\n".
                'title: "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $title) . "\"\n".
                'status: ' . $status . "\n";
        
        // Add token only for private posts
        if ($status === Constants::POST_STATUS_PRIVATE && $token) {
            $yaml .= 'token: ' . $token . "\n";
        }
        
        $yaml .= 'tags: [' . implode(', ', array_map(fn($t) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $t) . '"', $tags)) . "]\n".
                "---\n\n";

        $md = $yaml . $body;

    $destDir = Config::CONTENT_DIR . '/posts';
    
    // Determine destination and ID
    if ($post_id > 0 && $original_path) {
      // Editing existing post - determine if user REALLY changed the date field
      $id = $post_id;

      $oldFilename = basename($original_path, '.md');
      $oldDateInput = null; // format Y-m-dTH:i (local time)
      if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2})(\d{2})\./', $oldFilename, $m)) {
        // Convert UTC from filename to local time for comparison
        $utcTimestamp = strtotime($m[1] . 'T' . $m[2] . ':' . $m[3] . ':00Z');
        $oldDateInput = date('Y-m-d\TH:i', $utcTimestamp);
      }

      // Normalise current input to same format (local time, ignore seconds)
      $currentDateInput = '';
      if ($date !== '') {
        // Input is now local time, convert to local format for comparison
        $tsTmp = strtotime($date);
        if ($tsTmp !== false) {
          $currentDateInput = date('Y-m-d\TH:i', $tsTmp);
        }
      }

      $effectiveOriginalInput = $original_date_input !== '' ? $original_date_input : ($oldDateInput ?? '');
      $dateChanged = ($effectiveOriginalInput !== '' && $currentDateInput !== '' && $currentDateInput !== $effectiveOriginalInput);

      if (!$dateChanged) {
        // Date not changed by user -> keep original filename
        $dest = $original_path;
      } else {
        // User changed date -> convert local time to UTC for filename
        $ts = strtotime($date) ?: time();
        $datePrefix = gmdate('Y-m-d', $ts);
        $timeStr = gmdate('Hi', $ts);
        $dest = $destDir . '/' . $datePrefix . 'T' . $timeStr . '.' . $id . '.md';
      }
    } else {
        // New post - generate new ID and filename
        $maxId = 0;
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($destDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === Constants::MARKDOWN_EXTENSION) {
                $filename = basename($file->getPathname(), '.md');
                // Format: YYYY-MM-DDTHHMM.ID.md
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{4}\.(\d+)$/', $filename, $matches)) {
                    $maxId = max($maxId, (int)$matches[1]);
                }
            }
        }
        $id = $maxId + 1;
        
        // Create new filename from date field: YYYY-MM-DDTHHMM.ID.md (convert local to UTC)
        $ts = strtotime($date) ?: time();
        $datePrefix = gmdate('Y-m-d', $ts); // Convert to UTC for consistent storage
        $timeStr = gmdate('Hi', $ts);
        $dest = $destDir . '/' . $datePrefix . 'T' . $timeStr . '.' . $id . '.md';
    }

    // Check if index rebuild is needed BEFORE making changes
    $needsIndexRebuild = false;
    
    if ($original_path && file_exists($original_path)) {
        // Compare old and new metadata
        $oldContent = file_get_contents($original_path);
        $oldMeta = $content->parseMarkdown($oldContent);
        $newMeta = $content->parseMarkdown($md);
        
        // Check if important metadata changed
        $needsIndexRebuild = (
            ($oldMeta['title'] ?? '') !== ($newMeta['title'] ?? '') ||
            ($oldMeta['status'] ?? '') !== ($newMeta['status'] ?? '') ||
            ($oldMeta['category'] ?? '') !== ($newMeta['category'] ?? '') ||
            ($oldMeta['tags'] ?? '') !== ($newMeta['tags'] ?? '') ||
            realpath($original_path) !== realpath($dest) // filename changed (date changed)
        );
    } else {
        // New post always needs index rebuild
        $needsIndexRebuild = true;
    }

    // Remove old file if filename changed (date was changed)
    if ($original_path && realpath($original_path) !== realpath($dest) && file_exists($original_path)) {
        unlink($original_path);
    }
    
    // Save content to new/updated filename
    file_put_contents($dest, $md);
    
    if ($needsIndexRebuild) {
        $content->rebuildAll();
    }

    header('Location: admin.php');
    exit;
    }
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $mode === 'new' ? Language::getText('new_post') : Language::getText('edit_post') ?></title>
<link rel="stylesheet" href="<?= Config::BASE_URL() ?>/admin/admin.css">
<script src="https://unpkg.com/monaco-editor@0.54.0/min/vs/loader.js"></script>
<style>
/* ==========================================================================
   BASE STYLES - Match blog typography
   ========================================================================== */

body {
  font-family: monospace;
  font-size: 0.75rem;
}

/* ==========================================================================
   EDITOR LAYOUT - Split-screen editor with preview
   ========================================================================== */

#editor-container {
  display: flex;
  gap: 10px;
  border: 1px solid #ddd;
  height: 90vh;
}

/* Split-screen layout: Editor and Preview panes
 * Uses modern flexbox approach instead of fixed width: 50%
 * 
 * PREVIOUS ISSUE: width: 50% caused overflow problems on small screens
 * - Content could exceed container width
 * - Scrollbars would disappear
 * - Layout was not responsive
 * 
 * SOLUTION: flex: 1 + min-width: 0
 * - flex: 1 = take equal share of available space (flexible sizing)
 * - min-width: 0 = allow container to shrink below content size
 * - Combined with img { max-width: 100% } for proper image scaling
 */
#editor-section {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

#editor-container:has(#preview-pane.hidden-simple) #editor-section {
  width: 100%;
}

#monaco-editor {
  flex: 1;
}

.section-header {
  background: #f5f5f5;
  padding: 8px;
  border-bottom: 1px solid #ddd;
  font-weight: bold;
}

#preview-pane {
  flex: 1;
  min-width: 0;
  border-left: 1px solid #ddd;
  background: #f9f9f9;
  display: flex;
  flex-direction: column;
}

#preview-pane .section-header {
  background: #e9ecef;
}

#preview-content {
  padding: 10px;
  flex: 1;
  overflow-y: auto;
}

#editor-container #preview-pane.hidden-simple {
  display: none;
}

/* Hide scroll-sync button when preview is hidden */
#editor-container:has(#preview-pane.hidden-simple) #scroll-sync-toggle {
  display: none;
}

/* Tag Styles */
#selected-tags {
  margin-bottom: 10px;
  min-height: 30px;
  border: 1px solid #ddd;
  padding: 5px;
  border-radius: 4px;
  background: #f9f9f9;
}

.tag-item {
  display: inline-block;
  background: #007bff;
  color: white;
  padding: 3px 8px;
  margin: 2px;
  border-radius: 12px;
  font-size: 12px;
}

.tag-remove {
  background: none;
  border: none;
  color: white;
  cursor: pointer;
  margin-left: 5px;
}

/* Token Section Styles */
#token-section {
  background: #fff3cd;
  padding: 10px;
  border: 1px solid #ffc107;
  border-radius: 4px;
  margin-top: 16px;
  grid-template-columns: 2fr 1fr;
}

#token-section.hidden {
  display: none;
}

.token-url-container {
  flex: 1;
}

.token-url-input-wrapper {
  display: flex;
  gap: 10px;
  align-items: center;
}

#private-url {
  flex: 1;
  background: #f8f9fa;
  font-size: 0.75rem;
}

.token-copy-btn {
  white-space: nowrap;
}

.token-regenerate-btn {
  background: #dc3545 !important;
  color: white !important;
}

.token-warning {
  color: #856404;
  display: block;
  margin-top: 5px;
}

.token-info {
  flex: 1;
}

.token-info p {
  margin: 0;
  color: #856404;
}

/* Error Notice */
.notice.error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
  padding: 10px;
  margin-bottom: 20px;
  border-radius: 4px;
}

/* UTC Date Display */
.utc-date-hint {
  font-size: 0.65rem;
  color: #555;
  margin-top: 2px;
}

/* Hidden Editor Textarea */
#editor {
  display: none;
}

/* Button Styles - only for button elements */
button.btn-primary {
  padding: 6px 12px;
  font-size: 12px;
  background: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button.btn-secondary {
  padding: 4px 8px;
  font-size: 12px;
  background: #6c757d;
  color: white;
  border: none;
  border-radius: 3px;
  cursor: pointer;
}

button.btn-success {
  padding: 4px 8px;
  font-size: 12px;
  background: #28a745;
  color: white;
  border: none;
  border-radius: 3px;
  cursor: pointer;
}

/* Labels and Header - larger and normal font */
label, header {
  font-size: 14px;
  font-family: sans-serif;
  font-weight: normal;
}

#content-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 5px;
}

#editor-buttons {
  display: flex;
  gap: 5px;
}

.toolbar-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Markdown Toolbar Styles */
#markdown-toolbar {
  background: #f8f9fa;
  padding: 5px;
  border-bottom: 1px solid #ddd;
  display: flex;
  gap: 3px;
}

.toolbar-btn {
  padding: 5px 8px;
  border: 1px solid #ddd;
  background: white;
  cursor: pointer;
  border-radius: 3px;
  color: black;
}

.toolbar-btn-bold {
  font-weight: bold;
}

.toolbar-btn-italic {
  font-style: italic;
}

/* Tag Input Row Styles */
.tag-input-row {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.tag-select, .tag-input {
  flex: 1;
}

.tag-add-btn {
  padding: 5px 10px;
}

/* ==========================================================================
   NORMAL MODE - Standard editor layout
   ========================================================================== */

body:not(.fullscreen-mode) #editor-container {
  height: 90vh;
  max-height: 90vh;
  overflow: hidden;
}

/* ==========================================================================
   FULLSCREEN MODE - Distraction-free editing
   ========================================================================== */

body.fullscreen-mode {
  padding: 0;
  margin: 0;
  overflow: hidden;
}

body.fullscreen-mode main {
  padding: 0;
  margin: 0;
}

/* Hide all UI elements in fullscreen mode */
body.fullscreen-mode header,
body.fullscreen-mode .row,
body.fullscreen-mode label,
body.fullscreen-mode #tags-container,
body.fullscreen-mode #preview-pane,
body.fullscreen-mode .section-header,
body.fullscreen-mode #markdown-toolbar,
body.fullscreen-mode #preview-toggle,
body.fullscreen-mode #scroll-sync-toggle,
body.fullscreen-mode #content-toolbar {
  display: none !important;
}

body.fullscreen-mode #editor-container {
  height: 100vh;
  border: none;
  gap: 0;
}

body.fullscreen-mode #editor-section {
  width: 100%;
}

/* ==========================================================================
   DRAGGABLE TOOLBAR - Floating toolbar for fullscreen mode
   ========================================================================== */
body.fullscreen-mode div[data-draggable-toolbar="true"] {
  display: flex;
  position: fixed;
  top: 10px;
  right: 10px;
  z-index: 1000;
  background: rgba(0, 0, 0, 0.8);
  padding: 8px;
  border-radius: 8px;
  cursor: move;
  border: 2px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  user-select: none;
}

body.fullscreen-mode div[data-draggable-toolbar="true"]:hover {
  background: rgba(0, 0, 0, 0.9);
  border-color: rgba(255, 255, 255, 0.4);
}

/* Code Block Styling for Preview (matches public style.css) */
pre {
  background: #f8f9fa;
  color: #000;
  padding: 12px;
  border: 1px solid #d0d0d0;
  border-radius: 4px;
  overflow: auto;
}
code {
  background: #f5f5f5;
  color: #E21144;
  padding: 2px 4px;
  border: 1px solid #e1e1e8;
  border-radius: 3px;
}
pre code {
  background: transparent;
  color: #000;
  padding: 0;
  border: none;
}
/* Heading Sizes */
h1 { font-size: 1.8rem; }
h2 { font-size: 1.5rem; }
h3 { font-size: 1.3rem; }
h4 { font-size: 1.1rem; }
h5 { font-size: 1rem; }
h6 { font-size: 0.9rem; }

/* All headings styling */
h1, h2, h3, h4, h5, h6 {
  margin: 1.5rem 0 0.5rem 0;
  line-height: 1.3;
  font-weight: 600;
}

/* Images - match blog styling */
img {
  max-width: 100%;
  height: auto;
}
</style>

</head><body>
<header>
  <strong><?= $mode === 'new' ? '📝 ' . Language::getText('new_post') : '✏️ ' . Language::getText('edit_post') ?></strong>
  <nav>
    <button type="submit" form="editor-form">💾 <?= Language::getText('save') ?></button>
    <a href="admin.php">📊 <?= Language::getText('dashboard') ?></a>
    <a href="admin.php?action=logout">🚪 <?= Language::getText('logout') ?></a>
  </nav>
</header>
<main>
  <?php if ($editError): ?>
    <div class="notice error">
      <strong>⚠️ <?= Language::getText('error') ?>:</strong>
      <?php 
        switch($editError) {
          case 'invalid_parameters': 
            echo Language::getText('invalid_parameters') . ' (' . htmlspecialchars(implode(', ', array_keys($invalidParams)), ENT_QUOTES) . ')';
            break;
          case 'invalid_post_id': 
            echo Language::getText('invalid_post_id') . ': ' . htmlspecialchars($editPostId, ENT_QUOTES);
            break;
          case 'post_not_found': 
            echo Language::getText('post_not_found') . ' (ID: ' . htmlspecialchars($editPostId, ENT_QUOTES) . ')';
            break;
          default: 
            echo Language::getText('unknown_error');
        }
      ?>
      <br><small>💡 Gültige Parameter: <code>?action=editor&id=123</code> oder <code>?action=editor&id_post=123</code></small>
    </div>
<?php endif; ?>
  <form method="post" id="editor-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
    <input type="hidden" name="original_path" value="<?= htmlspecialchars($original_path, ENT_QUOTES) ?>">
    <input type="hidden" name="post_id" value="<?= htmlspecialchars($editPostId ?? 0, ENT_QUOTES) ?>">
    <div class="row">
      <div>
        <label><?= Language::getText('title') ?></label>
        <input type="text" name="title" value="<?= htmlspecialchars($meta['title'] ?? '', ENT_QUOTES) ?>">
      </div>
      <?php if ($mode === 'edit' && $editPostId): ?>
      <div>
        <label><?= Language::getText('post_id') ?></label>
        <input type="text" value="<?= htmlspecialchars($editPostId, ENT_QUOTES) ?>" disabled>
      </div>
<?php endif; ?>
    </div>
    <div class="row">
      <div>
        <label><?= Language::getText('date') ?> <span title="<?= Language::getText('local_hint') ?>">– <?php 
          $tz = Config::get('TIMEZONE');
          $tzOptions = Config::CONFIGURABLE_SETTINGS['TIMEZONE']['options'];
          echo htmlspecialchars($tzOptions[$tz] ?? $tz, ENT_QUOTES);
        ?></span></label>
        <?php
        // Derive date value from filename (UTC) and convert to local time for editing
        $shownDateValue = '';
        if (!empty($original_path)) {
          $base = basename($original_path, '.md');
          if (preg_match('/^(\\d{4}-\\d{2}-\\d{2})T(\\d{2})(\\d{2})\./', $base, $m)) {
            // Parse UTC time from filename and convert to local
            $utcTimestamp = strtotime($m[1] . 'T' . $m[2] . ':' . $m[3] . ':00Z');
            $shownDateValue = date('Y-m-d\\TH:i', $utcTimestamp);
          }
        }
        if ($shownDateValue === '') {
          // Fallback: Meta-Date (UTC) or now, converted to local time
          $metaDate = $meta['timestamp'] ?? time();
          $shownDateValue = date('Y-m-d\\TH:i', $metaDate);
        }
        ?>
        <input type="datetime-local" name="date" value="<?= htmlspecialchars($shownDateValue, ENT_QUOTES) ?>" oninput="updateUtcDateDisplay()" onchange="updateUtcDateDisplay()">
        <input type="hidden" name="original_date" value="<?= htmlspecialchars($shownDateValue, ENT_QUOTES) ?>">
        <div class="utc-date-hint">
          <span id="utc-date-display"></span>
        </div>
      </div>
      <div>
        <label><?= Language::getText('status') ?></label>
        <select name="status" id="status-select" onchange="toggleTokenSection()">
          <option value="<?= Constants::POST_STATUS_DRAFT ?>" <?= (($meta['status'] ?? Constants::DEFAULT_POST_STATUS)===Constants::POST_STATUS_DRAFT)?'selected':'' ?>><?= Language::getText('status_draft') ?></option>
          <option value="<?= Constants::POST_STATUS_PUBLISHED ?>" <?= (($meta['status'] ?? Constants::DEFAULT_POST_STATUS)===Constants::POST_STATUS_PUBLISHED)?'selected':'' ?>><?= Language::getText('status_published') ?></option>
          <option value="<?= Constants::POST_STATUS_PRIVATE ?>" <?= (($meta['status'] ?? Constants::DEFAULT_POST_STATUS)===Constants::POST_STATUS_PRIVATE)?'selected':'' ?>><?= Language::getText('status_private') ?></option>
        </select>
      </div>
    </div>
    <div id="token-section" class="row <?= (isset($meta['status']) && $meta['status'] === Constants::POST_STATUS_PRIVATE) ? '' : 'hidden' ?>">
      <?php if ($mode === 'edit' && $editPostId && isset($meta['token'])): ?>
      <div class="token-url-container">
        <label><?= Language::getText('share_url') ?></label>
        <div class="token-url-input-wrapper">
          <input type="text" id="private-url" value="<?= htmlspecialchars(Config::BASE_URL() . '/index.php?id=' . $editPostId . '&token=' . ($meta['token'] ?? ''), ENT_QUOTES) ?>" readonly>
          <button type="button" onclick="copyUrl()" class="btn-secondary token-copy-btn">📋 <?= Language::getText('copy') ?></button>
        </div>
      </div>
      <div>
        <label><?= Language::getText('regenerate_token') ?></label>
        <button type="submit" name="regenerate_token" value="1" class="btn-secondary token-regenerate-btn" 
                onclick="return confirmRegenerate()">
          🔄 <?= Language::getText('regenerate') ?>
        </button>
        <small class="token-warning">
          ⚠️ <?= Language::getText('regenerate_token_warning') ?>
        </small>
      </div>
      <?php else: ?>
      <div class="token-info">
        <p>
          ℹ️ <?= Language::getText('private_token_info') ?>
        </p>
      </div>
      <?php endif; ?>
    </div>
    <label><?= Language::getText('tags') ?></label>
    <div id="tags-container">
      <div id="selected-tags">
        <?php 
        $currentTags = is_array($meta['tags'] ?? []) ? $meta['tags'] : [];
        foreach ($currentTags as $tag): 
        ?>
          <span class="tag-item" data-tag="<?= htmlspecialchars($tag, ENT_QUOTES) ?>">
            <?= htmlspecialchars($tag, ENT_QUOTES) ?> 
            <button type="button" class="tag-remove" onclick="removeTag(this)">×</button>
          </span>
<?php endforeach; ?>
      </div>
      
      <div class="tag-input-row">
        <select id="existing-tags" class="tag-select" onchange="addExistingTag(this)">
          <option value=""><?= Language::getText('select_existing_category') ?></option>
          <?php
          // Sammle alle Kategorien aus allen Posts
          $allTags = [];
          $posts = $content->getIndex();
          foreach ($posts as $post) {
            if (isset($post['tags']) && is_array($post['tags'])) {
              foreach ($post['tags'] as $tag) {
                if (!in_array($tag, $allTags)) {
                  $allTags[] = $tag;
                }
              }
            }
          }
          sort($allTags);
          foreach ($allTags as $tag): 
          ?>
            <option value="<?= htmlspecialchars($tag, ENT_QUOTES) ?>"><?= htmlspecialchars($tag, ENT_QUOTES) ?></option>
<?php endforeach; ?>
        </select>
        
        <input type="text" id="new-tag" placeholder="<?= Language::getText('enter_new_category') ?>" class="tag-input">
        <button type="button" onclick="addNewTag()" class="btn-secondary tag-add-btn"><?= Language::getText('add') ?></button>
      </div>
      
      <input type="hidden" name="tags" id="tags-hidden" value="<?= htmlspecialchars(implode(',', $currentTags), ENT_QUOTES) ?>">
    </div>
    <div id="content-toolbar">
      <div class="toolbar-left">
        <label><?= Language::getText('content') ?></label>
      </div>
      <div id="editor-buttons">
        <button type="button" onclick="changeTheme()" class="btn-secondary">
          <?= Language::getText('theme_dark') ?>
        </button>
        <button type="button" onclick="changeFontSize(-1)" class="btn-secondary">
          A-
        </button>
        <button type="button" onclick="changeFontSize(1)" class="btn-secondary">
          A+
        </button>
        <button type="button" id="preview-toggle" onclick="togglePreview()" class="btn-primary">
          <?= Language::getText('hide_preview') ?>
        </button>
        <button type="button" id="scroll-sync-toggle" onclick="toggleScrollSync()" class="btn-secondary">
          <?= Language::getText('scroll_sync_on') ?>
        </button>
        <button type="button" id="fullscreen-toggle" onclick="toggleFullscreenMode()" class="btn-success">
          <?= Language::getText('fullscreen') ?>
        </button>
      </div>
    </div>
            <div id="editor-container">
              <div id="editor-section">
                <div class="section-header">
                  <?= Language::getText('editor') ?>
                </div>
                <div id="markdown-toolbar">
                  <button type="button" onclick="insertMarkdown('**', '**')" title="<?= Language::getText('bold') ?>" class="toolbar-btn toolbar-btn-bold">
                    <?= Language::getText('bold') ?>
                  </button>
                  <button type="button" onclick="insertMarkdown('*', '*')" title="<?= Language::getText('italic') ?>" class="toolbar-btn toolbar-btn-italic">
                    <?= Language::getText('italic') ?>
                  </button>
                  <button type="button" onclick="insertCodeBlock()" title="<?= Language::getText('code') ?>" class="toolbar-btn">
                    <?= Language::getText('code') ?>
                  </button>
                  <button type="button" onclick="insertMarkdown('[', '](url)')" title="<?= Language::getText('link') ?>" class="toolbar-btn">
                    <?= Language::getText('link') ?>
                  </button>
                  <button type="button" onclick="insertTable()" title="<?= Language::getText('table') ?>" class="toolbar-btn">
                    <?= Language::getText('table') ?>
                  </button>
                </div>
                <div id="monaco-editor"></div>
                <textarea id="editor" name="body"><?= htmlspecialchars($body, ENT_QUOTES) ?></textarea>
              </div>
              <div id="preview-pane">
                <div class="section-header">
                  <?= Language::getText('preview') ?>
                </div>
                <div id="preview-content"><?= Language::getText('preview_appears_here') ?></div>
              </div>
            </div>
  </form>
</main>

<script>
/* ==========================================================================
   BLOG POST EDITOR - Advanced Markdown editor with fullscreen mode
   Features: Monaco Editor, Live preview, Draggable toolbar, Fullscreen editing
   ========================================================================== */

// Translations for JavaScript
const TRANSLATIONS = {
  themeLight: '<?= Language::getText("theme_light") ?>',
  themeDark: '<?= Language::getText("theme_dark") ?>',
  showPreview: '<?= Language::getText("show_preview") ?>',
  hidePreview: '<?= Language::getText("hide_preview") ?>',
  fullscreen: '<?= Language::getText("fullscreen") ?>',
  normalMode: '<?= Language::getText("normal_mode") ?>',
  enterCodeHere: '<?= Language::getText("enter_code_here") ?>',
  error: '<?= Language::getText("error") ?>',
  scrollSyncOn: '<?= Language::getText("scroll_sync_on") ?>',
  scrollSyncOff: '<?= Language::getText("scroll_sync_off") ?>',
  regenerateTokenConfirm: '<?= Language::getText("regenerate_token_confirm") ?>',
  localTime: '<?= Language::getText("local_time") ?>',
  utcOffsetFormat: '<?= Language::getText("utc_offset_format") ?>',
  previewAppearsHere: '<?= Language::getText("preview_appears_here") ?>'
};

function confirmRegenerate() {
  return confirm(TRANSLATIONS.regenerateTokenConfirm);
}

let monacoEditor = null;
let scrollSyncEnabled = true;

/* ==========================================================================
   Visual Studio Code EDITOR INITIALIZATION
   ========================================================================== */

require.config({ 
  paths: { 'vs': 'https://unpkg.com/monaco-editor@0.54.0/min/vs' }
});

require(['vs/editor/editor.main'], function() {
  // Load saved settings before initializing editor
  const settings = loadEditorSettings();
  isDarkTheme = settings.isDarkTheme;
  currentFontSize = settings.fontSize;
  toolbarPosition = settings.toolbarPosition;
  
  // Load initial content from hidden textarea
  const initialContent = document.getElementById('editor').value;
  
  /**
   * Initialize Monaco Editor with markdown support
   * Configuration optimized for blog post editing:
   * - Markdown language with syntax highlighting
   * - Word wrap enabled for better readability
   * - No line numbers for cleaner interface
   * - Automatic layout adjustment on container resize
   * - Apply saved theme and font size
   */
  monacoEditor = monaco.editor.create(document.getElementById('monaco-editor'), {
    value: initialContent,
    language: 'markdown',
    theme: isDarkTheme ? 'vs-dark' : 'vs',
    fontSize: currentFontSize,
    automaticLayout: true,
    wordWrap: 'on',
    lineNumbers: 'off',
    scrollBeyondLastLine: false
  });

  // Apply saved settings to UI
  scrollSyncEnabled = settings.scrollSyncEnabled;
  applyLoadedSettings(settings);
  
  // Sync editor content with form textarea
  monacoEditor.onDidChangeModelContent(() => {
    document.getElementById('editor').value = monacoEditor.getValue();
    updatePreview();
  });
  
  // Add synchronized scrolling
  monacoEditor.onDidScrollChange(() => {
    syncScrollToPreview();
  });
  
  // Register keyboard shortcuts
  monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyB, () => insertMarkdown('**', '**'));
  monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyI, () => insertMarkdown('*', '*'));
  monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyK, () => insertMarkdown('[', '](url)'));
  monacoEditor.addCommand(monaco.KeyCode.F11, () => toggleFullscreenMode());
  
  // Save with Ctrl+S
  monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
    // Make sure textarea is updated before submit
    document.getElementById('editor').value = monacoEditor.getValue();
    document.querySelector('form').submit();
  });
  
  updatePreview();
});

/* ==========================================================================
   LIVE PREVIEW SYSTEM
   ========================================================================== */

function updatePreview() {
  if (!monacoEditor) return;
  const markdown = monacoEditor.getValue();
  const previewContent = document.getElementById('preview-content');
  
  // If editor is empty, show cue text
  if (!markdown.trim()) {
    previewContent.innerHTML = TRANSLATIONS.previewAppearsHere || '<?= Language::getText('preview_appears_here') ?>';
    return;
  }
  
  fetch('<?= Config::BASE_URL() ?>/admin/preview.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `markdown=${encodeURIComponent(markdown)}`
  })
  .then(r => r.json())
  .then(data => {
    previewContent.innerHTML = data.success ? data.html : '<em>' + TRANSLATIONS.error + '</em>';
    // Setup preview scroll sync after content update
    setupPreviewScrollSync();
  })
  .catch(err => {
    console.error('Preview fetch error:', err);
    previewContent.innerHTML = '<em>' + TRANSLATIONS.error + ': ' + err.message + '</em>';
  });
}

/* ==========================================================================
   SYNCHRONIZED SCROLLING SYSTEM
   ========================================================================== */

let isScrollSyncing = false;

/**
 * Sync editor scroll position to preview
 */
function syncScrollToPreview() {
  if (!monacoEditor || isScrollSyncing || !scrollSyncEnabled) return;
  
  const previewContent = document.getElementById('preview-content');
  if (!previewContent) return;
  
  isScrollSyncing = true;
  
  // Get editor scroll info
  const scrollTop = monacoEditor.getScrollTop();
  const scrollHeight = monacoEditor.getScrollHeight();
  const visibleHeight = monacoEditor.getLayoutInfo().height;
  
  // Calculate scroll percentage
  const maxScroll = scrollHeight - visibleHeight;
  const scrollPercentage = maxScroll > 0 ? scrollTop / maxScroll : 0;
  
  // Apply to preview
  const previewMaxScroll = previewContent.scrollHeight - previewContent.clientHeight;
  const previewScrollTop = previewMaxScroll * scrollPercentage;
  
  previewContent.scrollTop = previewScrollTop;
  
  setTimeout(() => { isScrollSyncing = false; }, 50);
}

/**
 * Setup preview to editor scroll sync
 */
function setupPreviewScrollSync() {
  const previewContent = document.getElementById('preview-content');
  if (!previewContent) return;
  
  // Remove existing listener to avoid duplicates
  previewContent.removeEventListener('scroll', syncScrollToEditor);
  
  // Add new listener
  previewContent.addEventListener('scroll', syncScrollToEditor);
}

/**
 * Sync preview scroll position to editor
 */
function syncScrollToEditor() {
  if (!monacoEditor || isScrollSyncing || !scrollSyncEnabled) return;
  
  const previewContent = document.getElementById('preview-content');
  if (!previewContent) return;
  
  isScrollSyncing = true;
  
  // Get preview scroll info
  const previewScrollTop = previewContent.scrollTop;
  const previewScrollHeight = previewContent.scrollHeight;
  const previewVisibleHeight = previewContent.clientHeight;
  
  // Calculate scroll percentage
  const previewMaxScroll = previewScrollHeight - previewVisibleHeight;
  const scrollPercentage = previewMaxScroll > 0 ? previewScrollTop / previewMaxScroll : 0;
  
  // Apply to editor
  const editorScrollHeight = monacoEditor.getScrollHeight();
  const editorVisibleHeight = monacoEditor.getLayoutInfo().height;
  const editorMaxScroll = editorScrollHeight - editorVisibleHeight;
  const editorScrollTop = editorMaxScroll * scrollPercentage;
  
  monacoEditor.setScrollTop(editorScrollTop);
  
  setTimeout(() => { isScrollSyncing = false; }, 50);
}

/* ==========================================================================
   EDITOR SETTINGS PERSISTENCE
   ========================================================================== */

const SETTINGS_KEY = 'bitblog-editor-settings';

/**
 * Load editor settings from localStorage
 * Restores user preferences for theme, font size, and preview visibility
 */
function loadEditorSettings() {
  try {
    const saved = localStorage.getItem(SETTINGS_KEY);
    const settings = saved ? JSON.parse(saved) : {};
    
    return {
      isDarkTheme: settings.isDarkTheme || false,
      fontSize: settings.fontSize || 14,
      previewVisible: settings.previewVisible !== false, // Default to true
      scrollSyncEnabled: settings.scrollSyncEnabled !== false, // Default to true
      toolbarPosition: settings.toolbarPosition || { x: 10, y: 10 }
    };
  } catch (e) {
    console.warn('Failed to load editor settings:', e);
    return {
      isDarkTheme: false,
      fontSize: 14,
      previewVisible: true,
      toolbarPosition: { x: 10, y: 10 }
    };
  }
}

/**
 * Save current editor settings to localStorage
 * Persists user preferences across browser sessions
 */
function saveEditorSettings() {
  try {
    const preview = document.getElementById('preview-pane');
    const settings = {
      isDarkTheme: isDarkTheme,
      fontSize: currentFontSize,
      previewVisible: !preview.classList.contains('hidden-simple'),
      scrollSyncEnabled: scrollSyncEnabled,
      toolbarPosition: toolbarPosition
    };
    
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
  } catch (e) {
    console.warn('Failed to save editor settings:', e);
  }
}

/**
 * Apply loaded settings to the UI
 * Updates button text and preview visibility based on saved preferences
 */
function applyLoadedSettings(settings) {
  // Update theme button text
  const themeButton = document.querySelector('button[onclick="changeTheme()"]');
  if (themeButton) {
    themeButton.textContent = isDarkTheme ? TRANSLATIONS.themeLight : TRANSLATIONS.themeDark;
  }
  
  // Apply preview visibility
  const preview = document.getElementById('preview-pane');
  const previewButton = document.getElementById('preview-toggle');
  
  if (!settings.previewVisible && preview && previewButton) {
    preview.classList.add('hidden-simple');
    previewButton.textContent = TRANSLATIONS.showPreview;
  } else if (previewButton) {
    previewButton.textContent = TRANSLATIONS.hidePreview;
  }
  
  // Apply scroll sync setting
  const scrollSyncButton = document.getElementById('scroll-sync-toggle');
  if (scrollSyncButton) {
    scrollSyncButton.textContent = scrollSyncEnabled ? TRANSLATIONS.scrollSyncOn : TRANSLATIONS.scrollSyncOff;
    
    // Hide scroll-sync button if preview is hidden
    if (!settings.previewVisible) {
      scrollSyncButton.style.display = 'none';
    }
  }
}

/* ==========================================================================
   EDITOR CUSTOMIZATION - Theme and font size controls
   ========================================================================== */

let isDarkTheme = false;
let currentFontSize = 14;
let toolbarPosition = { x: 10, y: 10 };

function changeTheme() {
  isDarkTheme = !isDarkTheme;
  const theme = isDarkTheme ? 'vs-dark' : 'vs';
  const button = document.querySelector('button[onclick="changeTheme()"]');
  
  if (monacoEditor) {
    monaco.editor.setTheme(theme);
  }
  
  button.textContent = isDarkTheme ? TRANSLATIONS.themeLight : TRANSLATIONS.themeDark;
  saveEditorSettings();
}

function changeFontSize(delta) {
  currentFontSize = Math.max(10, Math.min(24, currentFontSize + delta));
  if (monacoEditor) {
    monacoEditor.updateOptions({ fontSize: currentFontSize });
  }
  saveEditorSettings();
}

/* ==========================================================================
   MARKDOWN TOOLBAR FUNCTIONS
   ========================================================================== */

function insertMarkdown(before, after) {
  if (!monacoEditor) return;
  
  const selection = monacoEditor.getSelection();
  const selectedText = monacoEditor.getModel().getValueInRange(selection);
  const newText = before + selectedText + after;
  
  monacoEditor.executeEdits('markdown-toolbar', [{
    range: selection,
    text: newText
  }]);
  
  // Set cursor position
  const newSelection = {
    startLineNumber: selection.startLineNumber,
    startColumn: selection.startColumn + before.length,
    endLineNumber: selection.endLineNumber,
    endColumn: selection.endColumn + before.length
  };
  monacoEditor.setSelection(newSelection);
  monacoEditor.focus();
}

function insertCodeBlock() {
  if (!monacoEditor) return;
  
  const codeBlock = '```\n' + TRANSLATIONS.enterCodeHere + '\n```\n\n';
  
  const selection = monacoEditor.getSelection();
  const selectedText = monacoEditor.getModel().getValueInRange(selection);
  
  let finalText;
  if (selectedText) {
    // If text is selected, wrap it in code block
    finalText = '```\n' + selectedText + '\n```\n\n';
  } else {
    finalText = codeBlock;
  }
  
  monacoEditor.executeEdits('insert-code-block', [{
    range: selection,
    text: finalText
  }]);
  
  // Position cursor inside code block if no text was selected
  if (!selectedText) {
    const newPosition = {
      lineNumber: selection.startLineNumber + 1,
      column: 1
    };
    monacoEditor.setPosition(newPosition);
  }
  
  monacoEditor.focus();
}

function insertTable() {
  const tableText = `| Header 1 | Header 2 | Header 3 |
|----------|----------|----------|
| Cell 1   | Cell 2   | Cell 3   |
| Cell 4   | Cell 5   | Cell 6   |

`;
  
  if (!monacoEditor) return;
  
  const position = monacoEditor.getPosition();
  monacoEditor.executeEdits('insert-table', [{
    range: {
      startLineNumber: position.lineNumber,
      startColumn: position.column,
      endLineNumber: position.lineNumber,
      endColumn: position.column
    },
    text: tableText
  }]);
  monacoEditor.focus();
}

function togglePreview() {
  const preview = document.getElementById('preview-pane');
  const button = document.getElementById('preview-toggle');
  const scrollSyncButton = document.getElementById('scroll-sync-toggle');
  
  if (preview.classList.contains('hidden-simple')) {
    preview.classList.remove('hidden-simple');
    button.textContent = TRANSLATIONS.hidePreview;
    // Show scroll-sync button when preview is visible
    if (scrollSyncButton) scrollSyncButton.style.display = '';
  } else {
    preview.classList.add('hidden-simple');
    button.textContent = TRANSLATIONS.showPreview;
    // Hide scroll-sync button when preview is hidden
    if (scrollSyncButton) scrollSyncButton.style.display = 'none';
  }
  
  if (monacoEditor) setTimeout(() => monacoEditor.layout(), 100);
  saveEditorSettings();
}

function toggleScrollSync() {
  scrollSyncEnabled = !scrollSyncEnabled;
  const button = document.getElementById('scroll-sync-toggle');
  
  if (button) {
    button.textContent = scrollSyncEnabled ? TRANSLATIONS.scrollSyncOn : TRANSLATIONS.scrollSyncOff;
  }
  
  saveEditorSettings();
}

/* ==========================================================================
   FULLSCREEN MODE MANAGEMENT
   Advanced fullscreen editing with draggable toolbar and state preservation
   ========================================================================== */

// State tracking for fullscreen functionality
let isFullscreenMode = false;
let wasPreviewVisibleBeforeFullscreen = false;

/**
 * Toggle between normal and fullscreen editing mode
 * - Hides unnecessary UI elements in fullscreen
 * - Shows draggable toolbar for essential actions
 * - Preserves cursor position during transitions
 * - Manages preview pane visibility state
 */
function toggleFullscreenMode() {
  isFullscreenMode = !isFullscreenMode;
  const fullscreenButton = document.getElementById('fullscreen-toggle');
  const preview = document.getElementById('preview-pane');
  const previewToggleButton = document.getElementById('preview-toggle');
  
  if (isFullscreenMode) {
    enterFullscreenMode(fullscreenButton, preview, previewToggleButton);
  } else {
    exitFullscreenMode(fullscreenButton, preview, previewToggleButton);
  }
  
  updateMonacoEditorLayout();
}

function enterFullscreenMode(fullscreenButton, preview, previewToggleButton) {
  document.body.classList.add('fullscreen-mode');
  fullscreenButton.textContent = TRANSLATIONS.normalMode;
  fullscreenButton.style.background = '#dc3545';
  
  // Remember and hide preview
  wasPreviewVisibleBeforeFullscreen = !preview.classList.contains('hidden-simple');
  if (!preview.classList.contains('hidden-simple')) {
    preview.classList.add('hidden-simple');
    previewToggleButton.textContent = TRANSLATIONS.showPreview;
  }
  
  // Initialize draggable toolbar
  setTimeout(() => initDraggableToolbar(), 100);
}

function exitFullscreenMode(fullscreenButton, preview, previewToggleButton) {
  document.body.classList.remove('fullscreen-mode');
  fullscreenButton.textContent = TRANSLATIONS.fullscreen;
  fullscreenButton.style.background = '#28a745';
  
  // Restore preview state
  if (wasPreviewVisibleBeforeFullscreen) {
    preview.classList.remove('hidden-simple');
    previewToggleButton.textContent = TRANSLATIONS.hidePreview;
  }
  
  resetToolbarPosition();
  resetLayoutStyles();
}

function resetToolbarPosition() {
  if (toolbarElement) {
    const contentToolbar = document.getElementById('content-toolbar');
    if (contentToolbar) {
      contentToolbar.appendChild(toolbarElement);
    }
    
    // Reset all toolbar styles
    ['left', 'top', 'right', 'bottom', 'transition'].forEach(prop => {
      toolbarElement.style[prop] = '';
    });
    toolbarElement.removeAttribute('data-draggable-toolbar');
    toolbarElement = null;
  }
}

function resetLayoutStyles() {
  // Clean reset - remove all inline styles
  [
    document.getElementById('editor-container'),
    document.getElementById('editor-section'),
    document.body,
    document.querySelector('main')
  ].forEach(element => {
    if (element) {
      element.removeAttribute('style');
    }
  });
}

function updateMonacoEditorLayout() {
  if (!monacoEditor) return;
  
  if (!isFullscreenMode) {
    // Aggressive reset for fullscreen exit - preserve cursor position
    requestAnimationFrame(() => {
      // Capture position BEFORE any layout changes
      const savedPosition = monacoEditor.getPosition();
      
      // Reset viewport
      monacoEditor.layout({ width: 0, height: 0 });
      
      setTimeout(() => {
        monacoEditor.layout();
        
        // Fix scroll boundaries first
        setTimeout(() => {
          const model = monacoEditor.getModel();
          if (model) {
            // Test scroll range to fix internal boundaries
            monacoEditor.revealLine(model.getLineCount());
            
            // Now restore the ORIGINAL position
            if (savedPosition) {
              monacoEditor.setPosition(savedPosition);
              monacoEditor.revealLineInCenter(savedPosition.lineNumber);
            }
          }
        }, 50);
      }, 100);
    });
  } else {
    // Simple layout update for fullscreen entry
    requestAnimationFrame(() => monacoEditor.layout());
  }
}

/* ==========================================================================
   KEYBOARD SHORTCUTS
   ========================================================================== */

document.addEventListener('keydown', function(e) {
  if (e.key === 'F11') {
    e.preventDefault();
    toggleFullscreenMode();
  } else if (e.key === 'Escape' && isFullscreenMode) {
    e.preventDefault();
    toggleFullscreenMode();
  }
});

/* ==========================================================================
   TAG MANAGEMENT SYSTEM
   ========================================================================== */
function addExistingTag(select) {
  const tag = select.value;
  if (tag && !hasTag(tag)) {
    addTag(tag);
    select.selectedIndex = 0;
  }
}

function addNewTag() {
  const input = document.getElementById('new-tag');
  const tag = input.value.trim().replace(/,/g, ''); // Remove commas from tag
  if (tag && !hasTag(tag)) {
    addTag(tag);
    input.value = '';
  }
}

function addTag(tag) {
  const container = document.getElementById('selected-tags');
  const span = document.createElement('span');
  span.className = 'tag-item';
  span.setAttribute('data-tag', tag);
  
  // Use textContent to prevent HTML injection
  const textNode = document.createTextNode(tag + ' ');
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'tag-remove';
  button.textContent = '×';
  button.onclick = function() { removeTag(this); };
  
  span.appendChild(textNode);
  span.appendChild(button);
  container.appendChild(span);
  updateTagsHidden();
}

function removeTag(button) {
  button.parentElement.remove();
  updateTagsHidden();
}

function hasTag(tag) {
  const existing = document.querySelectorAll('.tag-item');
  for (let item of existing) {
    if (item.getAttribute('data-tag') === tag) {
      return true;
    }
  }
  return false;
}

function updateTagsHidden() {
  const tags = [];
  const tagItems = document.querySelectorAll('.tag-item');
  tagItems.forEach(item => {
    tags.push(item.getAttribute('data-tag'));
  });
  document.getElementById('tags-hidden').value = tags.join(',');
}

// Enter key handler for new tag input
document.getElementById('new-tag').addEventListener('keypress', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    addNewTag();
  }
});

/* ==========================================================================
   TOKEN MANAGEMENT FOR PRIVATE POSTS
   ========================================================================== */

// PHP constants for JavaScript
const POST_STATUS_PRIVATE = '<?= Constants::POST_STATUS_PRIVATE ?>';
const TEXT_COPIED = '<?= Language::getText("copied") ?>';

/**
 * Toggle visibility of token section based on status selection
 */
function toggleTokenSection() {
  const statusSelect = document.getElementById('status-select');
  const tokenSection = document.getElementById('token-section');
  
  if (!statusSelect || !tokenSection) return;
  
  if (statusSelect.value === POST_STATUS_PRIVATE) {
    tokenSection.classList.remove('hidden');
  } else {
    tokenSection.classList.add('hidden');
  }
}

/**
 * Copy URL to clipboard
 */
function copyUrl() {
  const urlInput = document.getElementById('private-url');
  if (!urlInput) return;
  
  urlInput.select();
  document.execCommand('copy');
  
  // Visual feedback
  const btn = event.target;
  const originalText = btn.textContent;
  btn.textContent = '✓ ' + TEXT_COPIED;
  setTimeout(() => {
    btn.textContent = originalText;
  }, 2000);
}

// Initialize token section visibility on page load
document.addEventListener('DOMContentLoaded', function() {
  toggleTokenSection();
  // Initial UTC Zeit Anzeige
  updateUtcDateDisplay();
});

/* ==========================================================================
   DRAGGABLE TOOLBAR - Floating toolbar for fullscreen mode
   ========================================================================== */

let isDragging = false;
let dragOffset = { x: 0, y: 0 };
let toolbarElement = null;

function initDraggableToolbar() {
  toolbarElement = document.getElementById('editor-buttons');
  if (toolbarElement && isFullscreenMode) {
    toolbarElement.setAttribute('data-draggable-toolbar', 'true');
    document.body.appendChild(toolbarElement);
    
    // Apply saved toolbar position
    const settings = loadEditorSettings();
    updateToolbarPosition(settings.toolbarPosition.x, settings.toolbarPosition.y);
    
    setupDragListeners();
  }
}

/**
 * Setup drag and drop event listeners for the fullscreen toolbar
 * Supports both mouse and touch events for desktop and mobile compatibility
 */
function setupDragListeners() {
  if (!toolbarElement) return;
  
  // Mouse events for desktop interaction
  toolbarElement.addEventListener('mousedown', handleDragStart);
  document.addEventListener('mousemove', handleDrag);
  document.addEventListener('mouseup', handleDragStop);
  
  // Touch events for mobile device support
  toolbarElement.addEventListener('touchstart', handleTouchStart);
  document.addEventListener('touchmove', handleTouchMove);
  document.addEventListener('touchend', handleDragStop);
}

function handleDragStart(e) {
  if (!isFullscreenMode || !toolbarElement) return;
  
  isDragging = true;
  const rect = toolbarElement.getBoundingClientRect();
  dragOffset.x = e.clientX - rect.left;
  dragOffset.y = e.clientY - rect.top;
  toolbarElement.style.transition = 'none';
  e.preventDefault();
}

function handleTouchStart(e) {
  if (!isFullscreenMode || !toolbarElement) return;
  
  const touch = e.touches[0];
  isDragging = true;
  const rect = toolbarElement.getBoundingClientRect();
  dragOffset.x = touch.clientX - rect.left;
  dragOffset.y = touch.clientY - rect.top;
  toolbarElement.style.transition = 'none';
  e.preventDefault();
}

function handleDrag(e) {
  if (!isDragging || !isFullscreenMode || !toolbarElement) return;
  
  const x = Math.max(0, Math.min(
    window.innerWidth - toolbarElement.offsetWidth,
    e.clientX - dragOffset.x
  ));
  const y = Math.max(0, Math.min(
    window.innerHeight - toolbarElement.offsetHeight,
    e.clientY - dragOffset.y
  ));
  
  updateToolbarPosition(x, y);
  e.preventDefault();
}

function handleTouchMove(e) {
  if (!isDragging || !isFullscreenMode || !toolbarElement) return;
  
  const touch = e.touches[0];
  const x = Math.max(0, Math.min(
    window.innerWidth - toolbarElement.offsetWidth,
    touch.clientX - dragOffset.x
  ));
  const y = Math.max(0, Math.min(
    window.innerHeight - toolbarElement.offsetHeight,
    touch.clientY - dragOffset.y
  ));
  
  updateToolbarPosition(x, y);
  e.preventDefault();
}

function updateToolbarPosition(x, y) {
  if (toolbarElement) {
    toolbarElement.style.left = x + 'px';
    toolbarElement.style.top = y + 'px';  
    toolbarElement.style.right = 'auto';
    toolbarElement.style.bottom = 'auto';
    
    // Update position for saving
    toolbarPosition = { x, y };
  }
}

function handleDragStop() {
  if (isDragging && toolbarElement) {
    isDragging = false;
    toolbarElement.style.transition = '';
    
    // Save toolbar position when dragging stops
    saveEditorSettings();
  }
}

/* ==========================================================================
   UTC DATE DISPLAY
   Displays the corresponding UTC time for the local input (datetime-local)
   to avoid misunderstandings.
   Assumption: Input value represents local time.
   ========================================================================== */
function updateUtcDateDisplay() {
  const inp = document.querySelector('input[name="date"]');
  const out = document.getElementById('utc-date-display');
  if (!inp || !out) return;
  const v = inp.value; // Format YYYY-MM-DDTHH:MM (local time)
  if (!v) { out.textContent = ''; return; }
  // Interpretiere als lokale Zeit
  const d = new Date(v);
  if (isNaN(d.getTime())) { out.textContent = ''; return; }
  
  // Format UTC time in long format (e.g., "12. November 2025, 16:10 Uhr")
  const utcDate = new Date(d.getTime());
  const options = {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'UTC'
  };
  
  const locale = document.documentElement.lang || 'de-DE';
  const formatted = new Intl.DateTimeFormat(locale, options).format(utcDate);
  
  out.textContent = 'UTC: ' + formatted;
}
</script>
</body></html>
