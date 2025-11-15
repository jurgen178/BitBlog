<?php
declare(strict_types=1);

namespace BitBlog;

final class Config
{
    // Default values (fallbacks)
    public const SITE_TITLE = 'My Blog';
    public const CONTENT_DIR = __DIR__ . '/../content';
    public const CACHE_DIR = __DIR__ . '/../cache';
    public const POSTS_PER_PAGE = 5;
    public const RSS_POSTS_LIMIT = 100; // 0 = alle Posts
    public const TIMEZONE = 'UTC';
    public const DEFAULT_LANGUAGE = 'de';
    public const SITE_URL = 'https://mysite.com/blog'; // Production URL fallback

    // Security (not configurable via admin)
    // ⚠️ IMPORTANT: Set your admin credentials here
    // Use setup mode (login.php) to create a secure hash
    public const ADMIN_USER = '';
    public const ADMIN_PASSWORD_HASH = '';

    // Runtime cache
    private static ?string $baseUrlCache = null;
    private static ?array $settingsCache = null;
    
    // Settings that can be configured via admin
    public const CONFIGURABLE_SETTINGS = [
        'SITE_TITLE' => ['type' => 'text', 'label' => 'label_site_title', 'group' => 'general'],
        'POSTS_PER_PAGE' => ['type' => 'number', 'label' => 'label_posts_per_page', 'min' => 1, 'max' => 50, 'group' => 'display'],
        'RSS_POSTS_LIMIT' => ['type' => 'number', 'label' => 'label_rss_posts_limit', 'min' => 0, 'max' => 500, 'group' => 'feeds'],
        'TIMEZONE' => ['type' => 'select', 'label' => 'label_timezone', 'options' => [
            'UTC' => 'UTC',
            'America/Los_Angeles' => 'UTC-8/-7 (Seattle, San Francisco, LA)',
            'America/Denver' => 'UTC-7/-6 (Denver, Phoenix)',
            'America/Chicago' => 'UTC-6/-5 (Chicago, Houston, Dallas)',
            'America/New_York' => 'UTC-5/-4 (New York, Miami, Boston)',
            'Europe/London' => 'UTC+0/+1 (London)',
            'Europe/Paris' => 'UTC+1/+2 (Paris, Madrid, Rome)',
            'Europe/Berlin' => 'UTC+1/+2 (Berlin, Bingen, Sponsheim)',
            'Europe/Athens' => 'UTC+2/+3 (Athens, Helsinki, Istanbul)',
            'Asia/Dubai' => 'UTC+4 (Dubai)',
            'Asia/Kolkata' => 'UTC+5:30 (India)',
            'Asia/Shanghai' => 'UTC+8 (China)',
            'Asia/Tokyo' => 'UTC+9 (Japan, Korea)',
            'Australia/Sydney' => 'UTC+10/+11 (Sydney, Melbourne)',
            'Pacific/Auckland' => 'UTC+12/+13 (New Zealand)'
        ], 'group' => 'general']
    ];

    /**
     * Get setting value (with override support)
     */
    public static function get(string $key): mixed
    {
        self::loadSettings();
        
        // Check override first
        if (isset(self::$settingsCache[$key])) {
            return self::$settingsCache[$key];
        }
        
        // Fallback to constant
        return constant('self::' . $key) ?? null;
    }

    /**
     * Load settings from JSON (cached)
     */
    private static function loadSettings(): void
    {
        if (self::$settingsCache !== null) {
            return; // Already loaded
        }
        
        $settingsFile = __DIR__ . '/../settings.json';
        
        if (file_exists($settingsFile)) {
            $json = file_get_contents($settingsFile);
            self::$settingsCache = json_decode($json, true) ?? [];
        } else {
            self::$settingsCache = [];
        }
    }

    /**
     * Save settings to JSON
     */
    public static function saveSettings(array $settings): bool
    {
        $settingsFile = __DIR__ . '/../settings.json';
        
        // Validate settings
        $validSettings = [];
        foreach (self::CONFIGURABLE_SETTINGS as $key => $config) {
            if (isset($settings[$key])) {
                $value = $settings[$key];
                
                // Type validation
                switch ($config['type']) {
                    case 'number':
                        $value = (int)$value;
                        if (isset($config['min']) && $value < $config['min']) $value = $config['min'];
                        if (isset($config['max']) && $value > $config['max']) $value = $config['max'];
                        break;
                    case 'text':
                        $value = trim((string)$value);
                        break;
                    case 'select':
                        // Support both array formats: ['value'] and ['key' => 'label']
                        $validOptions = is_array($config['options']) && !array_is_list($config['options']) 
                            ? array_keys($config['options']) 
                            : $config['options'];
                        if (!in_array($value, $validOptions)) {
                            continue 2; // Skip invalid option
                        }
                        break;
                }
                
                $validSettings[$key] = $value;
            }
        }
        
        $success = file_put_contents($settingsFile, json_encode($validSettings, JSON_PRETTY_PRINT));
        
        if ($success) {
            self::$settingsCache = $validSettings; // Update cache
        }
        
        return $success !== false;
    }

    /**
     * Get all current settings (defaults + overrides)
     */
    public static function getAllSettings(): array
    {
        $settings = [];
        foreach (self::CONFIGURABLE_SETTINGS as $key => $config) {
            $settings[$key] = self::get($key);
        }
        return $settings;
    }

    /**
     * Get auto-detected BASE_URL (cached)
     */
    public static function BASE_URL(): string
    {
        if (self::$baseUrlCache === null) {
            if (isset($_SERVER['HTTP_HOST'])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                
                // Handle subdirectory installations
                $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
                $dir = dirname($scriptName);
                $dir = ($dir === '/' || $dir === '\\') ? '' : $dir;
                
                self::$baseUrlCache = $protocol . '://' . $host . $dir;
            } else {
                // Fallback for CLI or when HTTP_HOST is not available
                // Use a generic placeholder that can be configured per environment
                self::$baseUrlCache = self::SITE_URL;
            }
        }
        return self::$baseUrlCache;
    }
}
