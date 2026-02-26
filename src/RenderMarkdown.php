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

        // Math support: protect $...$ and $$...$$ from Markdown parsing.
        // Without this, underscores/asterisks inside TeX get interpreted as emphasis.
        [$mdProtected, $mathMap] = self::protectMathInMarkdown($md);

        $html = self::$parsedown->text($mdProtected);
        
        // PARSEDOWN EXTRA BUG FIX:
        // ParsedownExtra escapes HTML entities in attributes and content, even when
        // setSafeMode(false) and setMarkupEscaped(false) are set. This breaks HTML
        // tags like <p>test</p> which become <p>&lt;p&gt;test&lt;&sol;p&gt;</p>
        // Our fix: globally decode all escaped entities back to proper HTML
        // Note: decodeAllHtmlEntities() also applies our <img> attribute enhancements
        // outside of code blocks.
        $html = self::decodeAllHtmlEntities($html);

        // Restore math placeholders after HTML entity decoding.
        return self::restoreMathPlaceholders($html, $mathMap);
    }

    /**
     * Protects TeX math segments from Parsedown/Markdown emphasis parsing.
     *
     * Strategy:
     * - Ignore fenced code blocks (```...``` and ~~~...~~~)
     * - Replace $$...$$ (multiline) and $...$ (single-line) with placeholders
     * - Restore placeholders after Markdown->HTML conversion
     *
     * @return array{0:string,1:array<string,string>} [protectedMarkdown, placeholderToMath]
     */
    private static function protectMathInMarkdown(string $md): array
    {
        if ($md === '' || (strpos($md, '$') === false)) {
            return [$md, []];
        }

        $mathMap = [];
        $counter = 0;

        // Split by fenced code blocks, keep delimiters.
        $parts = preg_split('/(```[\s\S]*?```|~~~[\s\S]*?~~~)/', $md, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [$md, []];
        }

        foreach ($parts as $i => $part) {
            // Odd indices are fenced code blocks; leave untouched.
            if ($i % 2 === 1) {
                continue;
            }

            // Display math: $$...$$ (can be multiline)
            $part = (string)preg_replace_callback('/\$\$(.+?)\$\$/s', function(array $m) use (&$mathMap, &$counter): string {
                $placeholder = '@@MATH' . $counter++ . '@@';
                $mathMap[$placeholder] = '$$' . $m[1] . '$$';
                return $placeholder;
            }, $part);

            // Inline math: $...$ (single-line). Avoid matching $$...$$ and escaped dollars.
            $part = (string)preg_replace_callback('/(?<!\\\\)\$(?!\$)([^\n$]+?)(?<!\\\\)\$/', function(array $m) use (&$mathMap, &$counter): string {
                $inner = trim($m[1]);

                // Heuristic: don't treat pure numbers as math (e.g. "$9.99").
                if ($inner !== '' && preg_match('/^[0-9][0-9.,]*$/', $inner) === 1) {
                    return '$' . $m[1] . '$';
                }

                $placeholder = '@@MATH' . $counter++ . '@@';
                $mathMap[$placeholder] = '$' . $m[1] . '$';
                return $placeholder;
            }, $part);

            $parts[$i] = $part;
        }

        return [implode('', $parts), $mathMap];
    }

    /**
     * Restores TeX segments into the final HTML.
     * Values are escaped as text so they cannot inject HTML.
     */
    private static function restoreMathPlaceholders(string $html, array $mathMap): string
    {
        if ($html === '' || $mathMap === []) {
            return $html;
        }

        // Replace placeholders with escaped TeX delimiters.
        foreach ($mathMap as $placeholder => $math) {
            $safeMath = htmlspecialchars($math, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = str_replace($placeholder, $safeMath, $html);
        }
        return $html;
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

                // Enhance <img> tags outside code blocks.
                $part = self::addLazyImageAttributes($part);
            }
            // Code blocks remain untouched
            $result .= $part;
        }
        
        return $result;
    }

    /**
     * Ensures that all <img> tags in the given HTML have:
     *   loading="lazy" and decoding="async"
     *
     * Does not duplicate attributes and does not override explicit values.
     */
    private static function addLazyImageAttributes(string $html): string
    {
        if ($html === '' || stripos($html, '<img') === false) {
            return $html;
        }

        return (string)preg_replace_callback('/<img\b[^>]*>/i', function(array $m): string {
            $tag = $m[0];

            $hasLoading = preg_match('/\bloading\s*=/i', $tag) === 1;
            $hasDecoding = preg_match('/\bdecoding\s*=/i', $tag) === 1;
            if ($hasLoading && $hasDecoding) {
                return $tag;
            }

            $attrsToAdd = '';
            if (!$hasLoading) {
                $attrsToAdd .= ' loading="lazy"';
            }
            if (!$hasDecoding) {
                $attrsToAdd .= ' decoding="async"';
            }

            // Insert before closing ">" while preserving self-closing form.
            $pos = strrpos($tag, '>');
            if ($pos === false) {
                return $tag;
            }

            $before = substr($tag, 0, $pos);
            $beforeTrimmed = rtrim($before);

            $selfClosingSuffix = '';
            if (str_ends_with($beforeTrimmed, '/')) {
                $beforeTrimmed = rtrim(substr($beforeTrimmed, 0, -1));
                $selfClosingSuffix = ' /';
            }

            return $beforeTrimmed . $attrsToAdd . $selfClosingSuffix . '>';
        }, $html);
    }
}
