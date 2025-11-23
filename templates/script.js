// Search functionality
(function() {
  let searchIndex = null;
  const searchToggle = document.getElementById('search-toggle');
  const searchInputWrapper = document.getElementById('search-input-wrapper');
  const searchInput = document.getElementById('search-input');
  const searchResults = document.getElementById('search-results');
  
  if (!searchToggle || !searchInputWrapper || !searchInput || !searchResults) {
    return; // Elements not found
  }
  
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
  const baseUrl = searchInput.dataset.baseUrl || '';
  const cacheVersion = searchInput.dataset.cacheVersion || '0';
  
  fetch(baseUrl + '/cache/search-index.json?v=' + cacheVersion)
    .then(r => r.json())
    .then(data => searchIndex = data)
    .catch(() => console.warn('Search index not available'));
  
  // Search on input
  searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    
    if (!query || !searchIndex) {
      searchResults.classList.remove('active');
      return;
    }
    
    // Parse query to extract phrases (in quotes) and individual words
    const phrases = [];
    const words = [];
    let remaining = query;
    
    // Extract phrases in quotes (both " and ')
    const quoteRegex = /["']([^"']+)["']/g;
    let match;
    while ((match = quoteRegex.exec(query)) !== null) {
      phrases.push(match[1].toLowerCase());
      remaining = remaining.replace(match[0], ' ');
    }
    
    // Extract individual words from remaining text
    remaining.split(/\s+/).forEach(word => {
      word = word.trim();
      if (word) {
        words.push(word.toLowerCase());
      }
    });
    
    const results = [];
    
    for (const id in searchIndex) {
      const post = searchIndex[id];
      const searchText = post.title + ' ' + post.content;
      
      // AND search: all phrases and words must be present
      const allPhrasesMatch = phrases.every(phrase => searchText.includes(phrase));
      const allWordsMatch = words.every(word => searchText.includes(word));
      
      if (allPhrasesMatch && allWordsMatch) {
        results.push({ id, ...post });
      }
    }
    
    // Sort by date (newest first)
    results.sort((a, b) => b.date - a.date);
    
    // Display results
    const noResultsText = searchInput.dataset.noResultsText || 'No results found';
    
    if (results.length === 0) {
      searchResults.innerHTML = '<div class="search-no-results">' + noResultsText + '</div>';
    } else {
      const maxResults = 10;
      const allTerms = [...phrases, ...words];
      searchResults.innerHTML = results.slice(0, maxResults).map(post => 
        '<a href="' + post.url + '" class="search-result-item">' +
          '<div class="search-result-title">' + highlightText(post.original_title, allTerms) + '</div>' +
          '<div class="search-result-excerpt">' + highlightText(getExcerptWithMatch(post.original_content, post.content, allTerms, 200), allTerms) + '</div>' +
        '</a>'
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
  function getExcerptWithMatch(originalText, lowerText, terms, maxLength) {
    let firstMatchPos = -1;
    let matchLength = 0;
    
    // Find position of first matching term (phrase or word)
    for (const term of terms) {
      const pos = lowerText.indexOf(term);
      if (pos !== -1 && (firstMatchPos === -1 || pos < firstMatchPos)) {
        firstMatchPos = pos;
        matchLength = term.length;
      }
    }
    
    // If no match found (shouldn't happen), use beginning
    if (firstMatchPos === -1) {
      return originalText.substring(0, maxLength) + (originalText.length > maxLength ? '...' : '');
    }
    
    // Position match in first third of window
    const matchMiddle = firstMatchPos + Math.floor(matchLength / 2);
    let start = Math.max(0, matchMiddle - Math.floor(maxLength / 3));
    let end = Math.min(originalText.length, start + maxLength);
    
    // Ensure match stays in first third even if text is short
    // If match would be beyond first third of actual excerpt, adjust start
    const actualLength = end - start;
    const matchPosInExcerpt = firstMatchPos - start;
    if (matchPosInExcerpt > actualLength / 3) {
      // Recalculate: match should be at 1/3 position
      start = Math.max(0, firstMatchPos - Math.floor(actualLength / 3));
      end = Math.min(originalText.length, start + maxLength);
    }
    
    // Add ellipsis as needed
    let excerpt = originalText.substring(start, end);
    if (start > 0) excerpt = '...' + excerpt;
    if (end < originalText.length) excerpt = excerpt + '...';
    
    return excerpt;
  }
  
  // Helper: Highlight search terms
  function highlightText(text, terms) {
    let highlighted = capitalize(text);
    // Sort by length (longest first) to avoid partial highlighting issues
    const sortedTerms = [...terms].sort((a, b) => b.length - a.length);
    sortedTerms.forEach(term => {
      // Escape regex special characters
      const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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
      const copyLinkText = button.dataset.copyLinkText || 'Copy link';
      alert(copyLinkText + ': ' + url);
    });
  }
}
