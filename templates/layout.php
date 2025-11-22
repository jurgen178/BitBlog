<?php 
use BitBlog\Utils;
use BitBlog\Language; 
?>
<!doctype html>
<html lang="<?= Language::getCurrentLanguage() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Utils::e(($title ?? $siteTitle) . ' - ' . $siteTitle) ?></title>
<link rel="stylesheet" href="<?= Utils::e($baseUrl) ?>/assets/style.css">
<link rel="alternate" type="application/rss+xml" title="RSS" href="<?= Utils::e($baseUrl) ?>/rss.php">
<style>
.search-container { position: relative; margin: 1rem 0; max-width: 400px; }
.search-toggle { cursor: pointer; color: #4a90e2; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem; user-select: none; }
.search-toggle:hover { color: #357abd; }
.search-input-wrapper { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
.search-input-wrapper.expanded { max-height: 500px; }
.search-input { width: 100%; padding: 0.5rem 2.5rem 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; margin-top: 0.5rem; box-sizing: border-box; }
.search-input:focus { outline: none; border-color: #4a90e2; }
.search-icon { position: absolute; right: 0.75rem; top: calc(0.5rem + 0.5rem + 50%); transform: translateY(-50%); pointer-events: none; opacity: 0; transition: opacity 0.3s; }
.search-input-wrapper.expanded .search-icon { opacity: 0.5; }
.search-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; max-height: 400px; overflow-y: auto; display: none; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.search-results.active { display: block; }
.search-result-item { display: block; padding: 0.75rem; border-bottom: 1px solid #eee; cursor: pointer; text-decoration: none; color: inherit; }
.search-result-item:hover { background: #f5f5f5; text-decoration: none; }
.search-result-item:last-child { border-bottom: none; }
.search-result-title { font-weight: bold; margin-bottom: 0.25rem; color: #0066cc; }
.search-result-item:hover .search-result-title { text-decoration: underline; }
.search-result-excerpt { font-size: 0.875rem; color: #666; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
.search-no-results { padding: 1rem; text-align: center; color: #666; }
</style>
</head>
<body>
<header class="site-header">
  <div class="container">
    <h1 class="site-title"><a href="<?= Utils::e($baseUrl) ?>"><?= Utils::e($siteTitle) ?></a></h1>
    <div class="search-container">
      <div id="search-toggle" class="search-toggle">
        <span>🔍</span>
        <span><?= Language::getText('search_placeholder') ?></span>
      </div>
      <div id="search-input-wrapper" class="search-input-wrapper">
        <input type="text" id="search-input" class="search-input" placeholder="<?= Language::getText('search_placeholder') ?>" autocomplete="off">
        <span class="search-icon">🔍</span>
        <div id="search-results" class="search-results"></div>
      </div>
    </div>
  </div>
</header>
<main class="container">
  <section class="content">
    <?= $content ?>
  </section>
</main>
<footer class="site-footer">
  <div class="container">
    <?= $signatureHtml ?>
  </div>
</footer>
<script>
(function() {
  let searchIndex = null;
  const searchToggle = document.getElementById('search-toggle');
  const searchInputWrapper = document.getElementById('search-input-wrapper');
  const searchInput = document.getElementById('search-input');
  const searchResults = document.getElementById('search-results');
  
  // Toggle search field
  searchToggle.addEventListener('click', function() {
    const isExpanded = searchInputWrapper.classList.toggle('expanded');
    if (isExpanded) {
      setTimeout(() => searchInput.focus(), 300);
    } else {
      searchInput.value = '';
      searchResults.classList.remove('active');
    }
  });
  
  // Load search index
  fetch('<?= Utils::e($baseUrl) ?>/cache/search-index.json?v=<?= @filemtime(__DIR__ . "/../cache/search-index.json") ?: 0 ?>')
    .then(r => r.json())
    .then(data => searchIndex = data)
    .catch(() => console.warn('Search index not available'));
  
  // Search on input
  searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    
    if (!query || !searchIndex) {
      searchResults.classList.remove('active');
      return;
    }
    
    const words = query.split(/\s+/);
    const results = [];
    
    for (const id in searchIndex) {
      const post = searchIndex[id];
      const searchText = post.title + ' ' + post.content;
      
      // AND search: all words must be present
      if (words.every(word => searchText.includes(word))) {
        results.push({ id, ...post });
      }
    }
    
    // Sort by date (newest first)
    results.sort((a, b) => b.date - a.date);
    
    // Display results
    if (results.length === 0) {
      searchResults.innerHTML = '<div class="search-no-results"><?= Language::getText('no_search_results') ?></div>';
    } else {
      const maxResults = 10;
      searchResults.innerHTML = results.slice(0, maxResults).map(post => 
        `<a href="${post.url}" class="search-result-item">
          <div class="search-result-title">${highlightText(post.original_title, words)}</div>
          <div class="search-result-excerpt">${highlightText(getExcerptWithMatch(post.original_content, post.content, words, 200), words)}</div>
        </a>`
      ).join('');
    }
    
    searchResults.classList.add('active');
  });
  
  // Close results when clicking outside
  document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.classList.remove('active');
    }
  });
  
  // Helper: Capitalize first letter
  function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }
  
  // Helper: Get excerpt around first match
  function getExcerptWithMatch(originalText, lowerText, words, maxLength) {
    let firstMatchPos = -1;
    let matchLength = 0;
    
    // Find position of first matching word
    for (const word of words) {
      const pos = lowerText.indexOf(word);
      if (pos !== -1 && (firstMatchPos === -1 || pos < firstMatchPos)) {
        firstMatchPos = pos;
        matchLength = word.length;
      }
    }
    
    // If no match found (shouldn't happen), use beginning
    if (firstMatchPos === -1) {
      return originalText.substring(0, maxLength) + (originalText.length > maxLength ? '...' : '');
    }
    
    // Position match in first third of window (not center)
    const matchMiddle = firstMatchPos + Math.floor(matchLength / 2);
    let start = Math.max(0, matchMiddle - Math.floor(maxLength / 3));
    let end = start + maxLength;
    
    // Adjust if we exceed text length
    if (end > originalText.length) {
      end = originalText.length;
      start = Math.max(0, end - maxLength);
    }
    
    // Add ellipsis as needed
    let excerpt = originalText.substring(start, end);
    if (start > 0) excerpt = '...' + excerpt;
    if (end < originalText.length) excerpt = excerpt + '...';
    
    return excerpt;
  }
  
  // Helper: Truncate text
  function truncate(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
  }
  
  // Helper: Highlight search terms
  function highlightText(text, words) {
    let highlighted = capitalize(text);
    words.forEach(word => {
      // Escape regex special characters
      const escaped = word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const regex = new RegExp('(' + escaped + ')', 'gi');
      highlighted = highlighted.replace(regex, '<mark style="background: #fff59d;">$1</mark>');
    });
    return highlighted;
  }
})();

// Share post functionality
function sharePost(button) {
  const url = button.dataset.url;
  const title = button.dataset.title;
  
  // Try native share API first (works on mobile and some desktop browsers)
  if (navigator.share) {
    navigator.share({
      title: title,
      url: url
    }).catch(() => {
      // User cancelled, do nothing
    });
  } else {
    // Fallback: Copy to clipboard
    navigator.clipboard.writeText(url).then(() => {
      // Show temporary success message
      const originalHTML = button.innerHTML;
      button.innerHTML = '✓';
      button.style.color = '#28a745';
      setTimeout(() => {
        button.innerHTML = originalHTML;
        button.style.color = '';
      }, 2000);
    }).catch(() => {
      // Fallback for older browsers
      alert('<?= Language::getText('copy_link') ?>: ' + url);
    });
  }
}
</script>
</body>
</html>
