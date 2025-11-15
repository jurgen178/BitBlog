<?php
/**
 * Utility Functions Collection
 * 
 * Essential helper functions for:
 * - HTML escaping and entity handling
 * - File system operations with proper error handling
 * - Date/time formatting utilities
 * - Path normalization across platforms
 */

declare(strict_types=1);

namespace BitBlog;

final class Utils
{
    /**
     * HTML escape function - secure output for templates
     * Converts special characters to HTML entities to prevent XSS
     * 
     * @param string $s The string to escape
     * @return string HTML-safe string
     */
    public static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Comprehensive HTML entity decoder
     * Handles standard entities, numeric references, and hexadecimal entities
     * 
     * @param string $s The string containing HTML entities
     * @return string Decoded string with UTF-8 characters
     */
    public static function decodeHtmlEntities(string $s): string
    {
        // Decode standard HTML entities including numeric character references
        $decoded = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Handle additional numeric entities that html_entity_decode might miss
        $decoded = preg_replace_callback('/&#(\d+);/', function($matches) {
            $charCode = (int)$matches[1];
            return mb_chr($charCode, 'UTF-8');
        }, $decoded);
        
        // Handle hexadecimal numeric entities
        $decoded = preg_replace_callback('/&#x([0-9a-fA-F]+);/', function($matches) {
            $charCode = hexdec($matches[1]);
            return mb_chr($charCode, 'UTF-8');
        }, $decoded);
        
        return $decoded;
    }

    public static function iso(string|int $time): string
    {
        $t = is_int($time) ? $time : strtotime($time);
        return gmdate('c', $t);
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $real = realpath($path);
        return $real !== false ? $real : $path;
    }

    public static function readFile(string $path): string
    {
        $norm = self::normalizePath($path);
        if (!is_file($norm)) {
            throw new \RuntimeException("Cannot read file. Not found: " . $norm);
        }
        $c = file_get_contents($norm);
        if ($c === false) {
            throw new \RuntimeException("Cannot read file contents: " . $norm);
        }
        return $c;
    }

    public static function writeFile(string $path, string $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: $dir");
        }
        if (file_put_contents($path, $data) === false) {
            throw new \RuntimeException("Cannot write $path");
        }
    }

    /**
     * Generate a cryptographically secure random token
     * Used for private post access tokens
     * 
     * @param int $length Token length in bytes (default 16 = 32 hex chars)
     * @return string Hexadecimal token string
     */
    public static function generateSecureToken(int $length = 16): string
    {
        return bin2hex(random_bytes($length));
    }
}
