<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Utils.php';
require_once __DIR__ . '/src/Constants.php';

use BitBlog\Utils;
use BitBlog\Constants;

function cleanMarkdownFile(string $filePath): bool {
    $content = Utils::readFile($filePath);
    $originalContent = $content;
    
    // Remove trailing <br /> tags and variations
    $content = preg_replace('/<br\s*\/?>\s*$/', '', $content);
    $content = preg_replace('/<br>\s*$/', '', $content);
    
    // Remove trailing empty lines
    $content = rtrim($content);
    
    // Always ensure file ends with exactly one newline
    $content = $content . "\n";
    
    if ($content !== $originalContent) {
        Utils::writeFile($filePath, $content);
        return true;
    }
    
    return false;
}

// Find all markdown files in content/posts directory
$postsDir = __DIR__ . '/content/posts';
$iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($postsDir, \FilesystemIterator::SKIP_DOTS)
);

$changedFiles = [];
$totalFiles = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== Constants::MARKDOWN_EXTENSION) {
        continue;
    }
    
    $totalFiles++;
    $filePath = $file->getPathname();
    $fileName = basename($filePath);
    
    echo "Checking: $fileName ... ";
    
    try {
        if (cleanMarkdownFile($filePath)) {
            $changedFiles[] = $fileName;
            echo "CLEANED\n";
        } else {
            echo "OK\n";
        }
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nSummary:\n";
echo "Total files checked: $totalFiles\n";
echo "Files changed: " . count($changedFiles) . "\n";

if (!empty($changedFiles)) {
    echo "\nChanged files:\n";
    foreach ($changedFiles as $file) {
        echo "- $file\n";
    }
}
