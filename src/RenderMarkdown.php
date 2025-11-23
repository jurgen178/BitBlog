<?php
declare(strict_types=1);

namespace BitBlog;

final class RenderMarkdown
{
    private static ?\ParsedownExtra $parsedown = null;
    
    public static function toHtml(string $md): string
    {
        if (self::$parsedown === null) {
            require_once __DIR__ . '/Parsedown.php';
            require_once __DIR__ . '/ParsedownExtra.php';
            self::$parsedown = new \ParsedownExtra();
            self::$parsedown->setSafeMode(false);
            self::$parsedown->setMarkupEscaped(false);
        }
        
        $html = self::$parsedown->text($md);
        
        // PARSEDOWN EXTRA BUG FIX:
        // ParsedownExtra escapes HTML entities in attributes and content, even when
        // setSafeMode(false) and setMarkupEscaped(false) are set. This breaks HTML
        // tags like <p>test</p> which become <p>&lt;p&gt;test&lt;&sol;p&gt;</p>
        // Our fix: globally decode all escaped entities back to proper HTML
        return self::decodeAllHtmlEntities($html);
    }
    
    /**
     * Fixes ParsedownExtra's HTML entity escaping
     * 
     * PROBLEM: ParsedownExtra converts HTML like <div style="color:red;"> into
     *          <div style&equals;&quot;color&colon;red&semi;&quot;> even with safe mode off
     * 
     * SOLUTION: Decode entities outside of code blocks to preserve HTML while
     *           keeping code examples safe
     * 
     * @param string $html HTML with potentially escaped entities
     * @return string Clean HTML with properly decoded entities
     */
    private static function decodeAllHtmlEntities(string $html): string
    {
        // Split HTML into parts: code blocks and everything else
        // Pattern matches <pre><code>...</code></pre> blocks
        $parts = preg_split('/(<pre[^>]*>.*?<\/pre>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $result = '';
        foreach ($parts as $i => $part) {
            // Even indices are outside code blocks, odd indices are code blocks
            if ($i % 2 === 0) {
                // Decode entities outside code blocks
                // ParsedownExtra double-escapes entities: ↩ → &larrhk; → &amp;larrhk;
                // So we need to decode TWICE to get back to UTF-8
                // Note: A 3rd decode would make no further changes
                $part = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $part = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Additional manual replacements for entities that html_entity_decode might miss
                $part = str_replace([
                    '&equals;', '&colon;', '&semi;', '&percnt;', '&comma;', '&period;',
                    '&lpar;', '&rpar;', '&lowbar;', '&NewLine;', '&Tab;', '&excl;',
                    '&quest;', '&num;', '&dollar;', '&sol;', '&bsol;', '&ast;',
                    '&plus;', '&Hat;', '&grave;', '&vert;', '&tilde;', '&lbrace;', '&rbrace;',
                    '&lcub;', '&rcub;', '&lbrack;', '&rbrack;', '&lsqb;', '&rsqb;'
                ], [
                    '=', ':', ';', '%', ',', '.',
                    '(', ')', '_', "\n", "\t", '!',
                    '?', '#', '$', '/', '\\', '*',
                    '+', '^', '`', '|', '~', '{', '}',
                    '{', '}', '[', ']', '[', ']'
                ], $part);
            }
            // Code blocks remain untouched
            $result .= $part;
        }
        
        return $result;
    }
}
