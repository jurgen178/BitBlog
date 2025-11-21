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

    /**
     * Create a ZIP archive from a directory
     * 
     * @param string $sourceDir Source directory path
     * @param string $zipFilePath Destination ZIP file path
     * @return bool True on success, false on failure
     */
    public static function createZipArchive(string $sourceDir, string $zipFilePath): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $contentPath = realpath($sourceDir);
        if (!$contentPath) {
            $zip->close();
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contentPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($contentPath) + 1);
                // Normalize path separators to forward slashes for ZIP compatibility
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
    }

    /**
     * Recursively delete a directory and all its contents
     * 
     * @param string $dir Directory path to delete
     * @return void
     */
    public static function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) return;
        
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        
        rmdir($dir);
    }

    /**
     * Extract ZIP archive to a directory, replacing existing content
     * Creates automatic backup before extraction
     * 
     * @param string $zipFilePath Path to ZIP file
     * @param string $targetDir Target directory path
     * @return bool True on success, false on failure
     * @throws \RuntimeException On extraction errors
     */
    public static function extractZipReplace(string $zipFilePath, string $targetDir): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            return false;
        }

        $realTargetDir = realpath($targetDir);
        
        // Delete old content directory if it exists
        if ($realTargetDir && is_dir($realTargetDir)) {
            self::recursiveDelete($realTargetDir);
        }

        // Create new content directory
        if (!mkdir($targetDir, 0755, true)) {
            $zip->close();
            throw new \RuntimeException("Failed to create directory: $targetDir");
        }

        // Extract ZIP
        try {
            if (!$zip->extractTo($targetDir)) {
                $zip->close();
                throw new \RuntimeException("Failed to extract ZIP to: $targetDir");
            }
            $zip->close();
        } catch (\Exception $e) {
            $zip->close();
            throw new \RuntimeException("Exception during ZIP extraction: " . $e->getMessage());
        }

        return true;
    }
}
