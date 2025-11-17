<?php
// Dependencies are already loaded by admin.php
use BitBlog\Config;
use BitBlog\Constants;
use BitBlog\Language;
use BitBlog\Content;

$message = '';
$messageType = 'success';

// Check for success message from redirect
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $message = '✅ ' . Language::getText('signature_saved');
    $messageType = 'success';
}

// Available languages based on existing signature files
$availableLanguages = [];
$pagesDir = Config::CONTENT_DIR . '/pages';
foreach (scandir($pagesDir) as $file) {
    if (preg_match('/^signature-([a-z]{2})\.md$/', $file, $matches)) {
        $availableLanguages[] = $matches[1];
    }
}

// Use 'sig_lang' parameter instead of 'lang' to avoid changing UI language
// Default to first available language or 'de'
$selectedLang = $_GET['sig_lang'] ?? $availableLanguages[0] ?? 'de';

// Validate selected language
if (!in_array($selectedLang, $availableLanguages)) {
    $selectedLang = $availableLanguages[0] ?? 'de';
}

$signatureFile = $pagesDir . "/signature-{$selectedLang}.md";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (empty($_POST[Constants::SESSION_CSRF]) || $_SESSION[Constants::SESSION_CSRF] !== $_POST[Constants::SESSION_CSRF]) {
        $message = Language::getText('invalid_security_token');
        $messageType = 'error';
    } else {
        $content = $_POST['content'] ?? '';
        
        // Save markdown file
        if (file_put_contents($signatureFile, $content) !== false) {
            // Regenerate HTML cache
            try {
                $contentManager = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
                
                // Get posts from index to regenerate signature with correct categories
                $indexFile = Config::CACHE_DIR . '/index.json';
                $posts = [];
                if (file_exists($indexFile)) {
                    $posts = json_decode(file_get_contents($indexFile), true) ?: [];
                }
                
                // Use reflection to access the private PageGenerator and call generateSignatureForLanguage
                $reflection = new ReflectionClass($contentManager);
                $pageGeneratorMethod = $reflection->getMethod('getPageGenerator');
                $pageGeneratorMethod->setAccessible(true);
                $pageGenerator = $pageGeneratorMethod->invoke($contentManager);
                
                // Use reflection to call the private generateSignatureForLanguage method
                $signatureMethod = new ReflectionMethod($pageGenerator, 'generateSignatureForLanguage');
                $signatureMethod->setAccessible(true);
                $signatureMethod->invoke($pageGenerator, $posts, $selectedLang);
                
                // Redirect to prevent form resubmission on refresh (POST-Redirect-GET pattern)
                $redirectUrl = 'admin.php?action=signature&sig_lang=' . urlencode($selectedLang) . '&saved=1';
                header('Location: ' . $redirectUrl);
                exit;
            } catch (Exception $e) {
                $message = '⚠️ ' . Language::getText('signature_saved_cache_failed') . ': ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = '❌ ' . Language::getText('signature_save_failed');
            $messageType = 'error';
        }
    }
}

// Read current content
$currentContent = file_exists($signatureFile) ? file_get_contents($signatureFile) : '';

// Generate CSRF token
if (empty($_SESSION[Constants::SESSION_CSRF])) {
    $_SESSION[Constants::SESSION_CSRF] = bin2hex(random_bytes(16));
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Language::getText('signature_editor') ?> - Admin</title>
<link rel="stylesheet" href="<?= Config::BASE_URL() ?>/admin/admin.css">

<!-- Monaco Editor -->
<script src="https://unpkg.com/monaco-editor@0.54.0/min/vs/loader.js"></script>
<style>
  /* Editor Layout - Split-screen with preview like the main editor */
  #signature-editor-container {
    display: flex;
    gap: 10px;
    border: 1px solid var(--border);
    height: 70vh;
    margin: 10px 0;
    border-radius: 8px;
  }
  
  #signature-editor-section {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
  }
  
  #signature-editor-container:has(#signature-preview-pane.hidden-simple) #signature-editor-section {
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
    font-size: 14px;
  }
  
  #signature-preview-pane {
    flex: 1;
    min-width: 0;
    border-left: 1px solid #ddd;
    background: #f9f9f9;
    display: flex;
    flex-direction: column;
  }
  
  #signature-preview-pane .section-header {
    background: #e9ecef;
  }
  
  #signature-preview-content {
    padding: 10px;
    flex: 1;
    overflow-y: auto;
  }
  
  #signature-editor-container #signature-preview-pane.hidden-simple {
    display: none;
  }
  
  .language-selector {
    margin: 10px 0;
  }
  .language-selector select {
    padding: 8px;
    border-radius: 4px;
    border: 1px solid var(--border);
    font-size: 14px;
  }
  .editor-controls {
    display: flex;
    gap: 15px;
    align-items: center;
    margin: 10px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
    flex-wrap: wrap;
    justify-content: space-between;
  }
  .control-group {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .control-group label {
    font-size: 14px;
    font-weight: 500;
  }
  .control-group select, .control-group input {
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid var(--border);
    font-size: 12px;
  }
  
  .editor-controls-left {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
  }
  
  .editor-controls-right {
    display: flex;
    gap: 5px;
    align-items: center;
  }
  
  /* Button Styles wie im Editor */
  .btn-secondary {
    padding: 4px 8px;
    font-size: 12px;
    background: #6c757d;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
  }
  
  .btn-primary {
    padding: 6px 12px;
    font-size: 12px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  
  /* Preview Content Styling to match blog */
  #signature-preview-content h1, 
  #signature-preview-content h2, 
  #signature-preview-content h3, 
  #signature-preview-content h4, 
  #signature-preview-content h5, 
  #signature-preview-content h6 {
    margin: 1.5rem 0 0.5rem 0;
    line-height: 1.3;
    font-weight: 600;
  }
  
  #signature-preview-content h1 { font-size: 1.8rem; }
  #signature-preview-content h2 { font-size: 1.5rem; }
  #signature-preview-content h3 { font-size: 1.3rem; }
  #signature-preview-content h4 { font-size: 1.1rem; }
  #signature-preview-content h5 { font-size: 1rem; }
  #signature-preview-content h6 { font-size: 0.9rem; }
  
  #signature-preview-content pre {
    background: #f8f9fa;
    color: #000;
    padding: 12px;
    border: 1px solid #d0d0d0;
    border-radius: 4px;
    overflow: auto;
  }
  
  #signature-preview-content code {
    background: #f5f5f5;
    color: #E21144;
    padding: 2px 4px;
    border: 1px solid #e1e1e8;
    border-radius: 3px;
  }
  
  #signature-preview-content pre code {
    background: transparent;
    color: #000;
    padding: 0;
    border: none;
  }
  
  #signature-preview-content img {
    max-width: 100%;
    height: auto;
  }
</style>
</head><body>
<header>
    <strong>📝 <?= Language::getText('signature_editor') ?></strong>
  <nav>
    <button type="submit" form="signature-form">💾 <?= Language::getText('save_signature') ?></button>
    <a href="admin.php">📊 <?= Language::getText('dashboard') ?></a>
    <a href="admin.php?action=logout">🚪 <?= Language::getText('logout') ?></a>
  </nav>
</header>
<main>
  <?php if ($message): ?>
    <p class="notice <?= $messageType === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($message, ENT_QUOTES) ?>
    </p>
<?php endif; ?>

  <div class="editor-controls">
    <div class="editor-controls-left">
      <div class="control-group">
        <label for="lang-select"><?= Language::getText('select_language') ?>:</label>
        <select id="lang-select" onchange="location.href='admin.php?action=signature&sig_lang='+this.value">
          <?php foreach ($availableLanguages as $lang): ?>
            <option value="<?= $lang ?>" <?= $lang === $selectedLang ? 'selected' : '' ?>>
              <?= strtoupper($lang) ?>
            </option>
<?php endforeach; ?>
        </select>
      </div>
    </div>
    
    <div class="editor-controls-right">
      <button type="button" id="theme-toggle" class="btn-secondary">
        🌙 <?= Language::getText('theme_dark') ?>
      </button>
      <button type="button" id="font-decrease" class="btn-secondary">A-</button>
      <button type="button" id="font-increase" class="btn-secondary">A+</button>
      <button type="button" id="preview-toggle" class="btn-primary">
        <?= Language::getText('hide_preview') ?>
      </button>
    </div>
  </div>

  <form method="post" id="signature-form">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION[Constants::SESSION_CSRF], ENT_QUOTES) ?>">
    
    <div id="signature-editor-container">
      <div id="signature-editor-section">
        <div class="section-header">
          <?= Language::getText('signature_editor') ?>
        </div>
        <div id="monaco-editor"></div>
      </div>
      <div id="signature-preview-pane">
        <div class="section-header">
          <?= Language::getText('preview') ?>
        </div>
        <div id="signature-preview-content"><?= Language::getText('preview_appears_here') ?></div>
      </div>
    </div>
    
    <textarea name="content" id="content-textarea"><?= htmlspecialchars($currentContent, ENT_QUOTES) ?></textarea>
  </form>
</main>

<script>
// Translations for JavaScript (similar to editor.php)
const TRANSLATIONS = {
  themeLight: '<?= Language::getText('theme_light') ?>',
  themeDark: '<?= Language::getText('theme_dark') ?>',
  showPreview: '<?= Language::getText('show_preview') ?>',
  hidePreview: '<?= Language::getText('hide_preview') ?>',
  previewPlaceholder: '<?= Language::getText('preview_appears_here') ?>',
  error: '<?= Language::getText('error') ?>'
};

require.config({ paths: { 'vs': 'https://unpkg.com/monaco-editor@0.54.0/min/vs' }});
require(['vs/editor/editor.main'], function () {
    let editor;
    
    // Load saved preferences
    const savedTheme = localStorage.getItem('signature-editor-theme') || 'vs-dark';
    const savedFontSize = localStorage.getItem('signature-editor-font-size') || '14';
    const savedPreviewVisible = localStorage.getItem('signature-editor-preview-visible') !== 'false'; // Default to true
    
    // Initialize editor
    editor = monaco.editor.create(document.getElementById('monaco-editor'), {
        value: document.getElementById('content-textarea').value,
        language: 'markdown',
        theme: savedTheme,
        fontSize: parseInt(savedFontSize),
        minimap: { enabled: true },
        wordWrap: 'on',
        lineNumbers: 'on',
        automaticLayout: true
    });

    // Set initial UI values
    document.getElementById('theme-toggle').textContent = savedTheme === 'vs-dark' ? TRANSLATIONS.themeLight : TRANSLATIONS.themeDark;
    
    // Apply saved preview visibility
    const previewPane = document.getElementById('signature-preview-pane');
    const previewButton = document.getElementById('preview-toggle');
    if (!savedPreviewVisible) {
        previewPane.classList.add('hidden-simple');
        previewButton.textContent = TRANSLATIONS.showPreview;
    } else {
        previewButton.textContent = TRANSLATIONS.hidePreview;
    }

    // Theme toggle handler
    document.getElementById('theme-toggle').addEventListener('click', function() {
        const currentTheme = localStorage.getItem('signature-editor-theme') || 'vs-dark';
        const newTheme = currentTheme === 'vs-dark' ? 'vs' : 'vs-dark';
        monaco.editor.setTheme(newTheme);
        this.textContent = newTheme === 'vs-dark' ? TRANSLATIONS.themeLight : TRANSLATIONS.themeDark;
        localStorage.setItem('signature-editor-theme', newTheme);
    });

    // Font size handlers
    document.getElementById('font-decrease').addEventListener('click', function() {
        const currentSize = parseInt(localStorage.getItem('signature-editor-font-size')) || 14;
        const newSize = Math.max(10, currentSize - 1);
        editor.updateOptions({ fontSize: newSize });
        localStorage.setItem('signature-editor-font-size', newSize.toString());
    });
    
    document.getElementById('font-increase').addEventListener('click', function() {
        const currentSize = parseInt(localStorage.getItem('signature-editor-font-size')) || 14;
        const newSize = Math.min(24, currentSize + 1);
        editor.updateOptions({ fontSize: newSize });
        localStorage.setItem('signature-editor-font-size', newSize.toString());
    });
    
    // Preview toggle handler
    document.getElementById('preview-toggle').addEventListener('click', function() {
        const preview = document.getElementById('signature-preview-pane');
        const isHidden = preview.classList.contains('hidden-simple');
        
        if (isHidden) {
            preview.classList.remove('hidden-simple');
            this.textContent = TRANSLATIONS.hidePreview;
            localStorage.setItem('signature-editor-preview-visible', 'true');
        } else {
            preview.classList.add('hidden-simple');
            this.textContent = TRANSLATIONS.showPreview;
            localStorage.setItem('signature-editor-preview-visible', 'false');
        }
        
        // Re-layout editor after preview toggle
        setTimeout(() => editor.layout(), 100);
    });

    // Update textarea and preview when editor content changes
    editor.onDidChangeModelContent(() => {
        document.getElementById('content-textarea').value = editor.getValue();
        updatePreview();
    });

    // Save with Ctrl+S
    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, () => {
        // Make sure textarea is updated before submit
        document.getElementById('content-textarea').value = editor.getValue();
        document.getElementById('signature-form').submit();
    });
    
    // Initial preview update
    updatePreview();
});

// Preview update function (similar to editor.php)
function updatePreview() {
    const editor = monaco.editor.getModels()[0]; // Get the first (and only) editor model
    if (!editor) return;
    
    const markdown = editor.getValue();
    if (!markdown.trim()) {
        document.getElementById('signature-preview-content').innerHTML = '<em>' + TRANSLATIONS.previewPlaceholder + '</em>';
        return;
    }
    
    fetch('<?= Config::BASE_URL() ?>/admin/preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'markdown=' + encodeURIComponent(markdown)
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('signature-preview-content').innerHTML = 
            data.success ? data.html : '<em>' + TRANSLATIONS.error + '</em>';
    })
    .catch(error => {
        console.error('Preview error:', error);
        document.getElementById('signature-preview-content').innerHTML = '<em>' + TRANSLATIONS.error + '</em>';
    });
}
</script>
</body></html>