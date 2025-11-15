<?php
declare(strict_types=1);

namespace BitBlog;

final class MarkdownProcessor
{
    public function readMarkdownWithMeta(string $path, ?string $raw = null): array
    {
        $raw = $raw ?? Utils::readFile($path);
        $meta = [];
        $body = $raw;
        
        // Normalize line endings first
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        
        if (strncmp($raw, "---\n", 4) === 0) {
            $end = strpos($raw, "\n---", 4);
            if ($end !== false) {
                $yaml = substr($raw, 4, $end - 4);
                $body = ltrim(substr($raw, $end + 4), "\r\n");
                $meta = $this->parseYamlMin($yaml);
            }
        }
        return ['meta' => $meta, 'body' => $body];
    }

    private function parseYamlMin(string $yaml): array
    {
        $data = [];
        $lines = preg_split("/\r?\n/", $yaml);
        
        foreach ($lines as $line) {
            // Skip empty lines and comments
            if (trim($line) === '' || str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, ':')) continue;
            
            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Parse value based on type
            $data[$key] = $this->parseYamlValue($value);
        }
        
        return $data;
    }
    
    private function parseYamlValue(string $value): mixed
    {
        // Quoted string: "hello" or 'hello'
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $unquoted = $m[2];
            // Unescape double-quoted strings (order matters: \\ first, then \")
            return $m[1] === '"' 
                ? Utils::decodeHtmlEntities(str_replace(['\\\\', '\\"'], ['\\', '"'], $unquoted))
                : Utils::decodeHtmlEntities($unquoted);
        }
        
        // Array: [a, b, c]
        if (preg_match('/^\[(.*)\]$/', $value, $m)) {
            if (trim($m[1]) === '') return [];
            return array_values(array_filter(
                array_map(fn($x) => $this->parseYamlValue(trim($x)), explode(',', $m[1])),
                fn($x) => $x !== ''
            ));
        }
        
        // Boolean and null
        return match($value) {
            'true' => true,
            'false' => false,
            'null', '~' => null,
            default => Utils::decodeHtmlEntities($value)
        };
    }

    public function inferDate(string $path): string
    {
        $base = basename($path, '.md');
        // Format: YYYY-MM-DDTHHMM.ID.md
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2})(\d{2})\./', $base, $m)) {
            return $m[1] . 'T' . $m[2] . ':' . $m[3] . ':00Z';
        }
        // Fallback to file modification time
        return gmdate('c', filemtime($path));
    }

    public function extractIdFromFilename(string $filename): ?int
    {
        // Extract ID from filename format: YYYY-MM-DDTHHMM.ID
        // Example: "2025-11-04T1430.2" should return 2
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{4}\.(\d+)$/', $filename, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    public function toStringArray($v): array
    {
        if (is_string($v)) return array_values(array_filter(array_map('trim', explode(',', $v))));
        if (is_array($v)) {
            return array_values(array_map(function($x) {
                // Flatten nested arrays (shouldn't happen with proper YAML, but handle it)
                if (is_array($x)) {
                    return implode(', ', array_map('strval', $x));
                }
                return (string)$x;
            }, $v));
        }
        return [];
    }
}
