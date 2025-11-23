// Search functionality
(function() {
  let searchIndex = null;
  const searchToggle = document.getElementById('search-toggle');
  const searchInputWrapper = document.getElementById('search-input-wrapper');
  const searchInput = document.getElementById('search-input');
  const searchResults = document.getElementById('search-results');
  const searchHelp = document.getElementById('search-help');
  
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
      if (searchHelp) searchHelp.classList.remove('active');
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
      if (searchHelp) searchHelp.classList.remove('active');
      return;
    }
    
    // Show help when search is active
    if (searchHelp) searchHelp.classList.add('active');
    
    // Parse query to extract phrases (in quotes) and individual words
    const phrases = [];
    const regexPatterns = [];
    const words = [];
    let remaining = query;
    
    // Extract regex patterns: r"pattern" or r'pattern' (balanced quotes)
    const regexQuoteRegex = /r(["'])(.+?)\1/g;
    let match;
    let invalidRegexPattern = null;
    while ((match = regexQuoteRegex.exec(query)) !== null) {
      try {
        // Test if valid regex (match[2] is the pattern)
        new RegExp(match[2], 'i');
        regexPatterns.push(match[2]);
        remaining = remaining.replace(match[0], ' ');
      } catch (e) {
        // Invalid regex - save for error message
        invalidRegexPattern = match[2];
        remaining = remaining.replace(match[0], ' ');
      }
    }
    
    // Show error if invalid regex found
    if (invalidRegexPattern !== null) {
      const invalidRegexText = searchInput.dataset.invalidRegexText || 'Invalid regex pattern';
      searchResults.innerHTML = '<div class="search-no-results" style="color: #d32f2f; border-left: 3px solid #d32f2f;">' + invalidRegexText + ': <code>' + escapeHtml(invalidRegexPattern) + '</code></div>';
      searchResults.classList.add('active');
      return;
    }
    
    // Extract phrases in quotes (both " and ') - balanced quotes only
    const quoteRegex = /(["'])(.+?)\1/g;
    while ((match = quoteRegex.exec(remaining)) !== null) {
      phrases.push(match[2].toLowerCase());
      remaining = remaining.replace(match[0], ' ');
    }
    
    // Extract individual words from remaining text
    // Don't remove quotes - treat them as searchable characters
    remaining.split(/\s+/).forEach(word => {
      word = word.trim();
      if (word) {
        words.push(word.toLowerCase());
      }
    });
    
    // If no search terms remain (e.g., only invalid regex), show no results
    if (regexPatterns.length === 0 && phrases.length === 0 && words.length === 0) {
      searchResults.classList.remove('active');
      return;
    }
    
    const results = [];
    
    for (const id in searchIndex) {
      const post = searchIndex[id];
      const searchText = post.title + ' ' + post.content;
      
      // AND search: all regex patterns, phrases and words must match
      const allRegexMatch = regexPatterns.every(pattern => {
        try {
          const regex = new RegExp(pattern, 'i');
          return regex.test(searchText);
        } catch (e) {
          return false;
        }
      });
      const allPhrasesMatch = phrases.every(phrase => searchText.includes(phrase));
      const allWordsMatch = words.every(word => searchText.includes(word));
      
      if (allRegexMatch && allPhrasesMatch && allWordsMatch) {
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
      
      searchResults.innerHTML = results.slice(0, maxResults).map(post => {
        // Extract regex matches for THIS post
        const regexMatches = [];
        if (regexPatterns.length > 0) {
          regexPatterns.forEach(pattern => {
            try {
              const regex = new RegExp(pattern, 'gi');
              const sampleText = post.title + ' ' + post.content;
              let match;
              let count = 0;
              const maxSamples = 20;
              
              while ((match = regex.exec(sampleText)) !== null && count++ < maxSamples) {
                if (match[0] && match[0].length > 0 && !regexMatches.includes(match[0])) {
                  regexMatches.push(match[0]);
                }
                if (match.index === regex.lastIndex) {
                  regex.lastIndex++;
                }
              }
            } catch (e) {
              // Ignore invalid regex
            }
          });
        }
        
        const highlightTerms = [...allTerms, ...regexMatches];
        
        return '<a href="' + escapeHtml(post.url) + '" class="search-result-item">' +
          '<div class="search-result-title">' + highlightText(post.original_title, highlightTerms) + '</div>' +
          '<div class="search-result-excerpt">' + highlightText(getExcerptWithMatch(post.original_content, post.content, allTerms, regexPatterns, 200), highlightTerms) + '</div>' +
        '</a>';
      }).join('');
    }
    
    searchResults.classList.add('active');
  });
  
  // Close results when clicking outside
  document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.classList.remove('active');
    }
  });
  
  // Helper: Escape HTML entities to prevent XSS
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Helper: Get excerpt around first match
  function getExcerptWithMatch(originalText, lowerText, terms, regexPatterns, maxLength) {
    let firstMatchPos = -1;
    let matchLength = 0;
    
    // Check simple terms (phrases and words)
    for (const term of terms) {
      const pos = lowerText.indexOf(term.toLowerCase());
      if (pos !== -1 && (firstMatchPos === -1 || pos < firstMatchPos)) {
        firstMatchPos = pos;
        matchLength = term.length;
      }
    }
    
    // Check regex patterns
    for (const pattern of regexPatterns) {
      try {
        const regex = new RegExp(pattern, 'i');
        const match = regex.exec(lowerText);
        if (match && (firstMatchPos === -1 || match.index < firstMatchPos)) {
          firstMatchPos = match.index;
          matchLength = match[0].length;
        }
      } catch (e) {
        // Invalid regex, skip
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
    // First escape HTML to prevent XSS and broken HTML
    let highlighted = escapeHtml(text);
    
    // Sort by length (longest first) to avoid partial highlighting issues
    const sortedTerms = [...terms].sort((a, b) => b.length - a.length);
    
    // Limit number of terms to prevent performance issues with regex patterns
    const maxTerms = 50;
    const limitedTerms = sortedTerms.slice(0, maxTerms);
    
    limitedTerms.forEach(term => {
      // Escape the search term for HTML
      const escapedTerm = escapeHtml(term);
      // Escape regex special characters
      const escaped = escapedTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      // Match the escaped version in the already-escaped text
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
