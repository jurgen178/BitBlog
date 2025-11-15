<?php
declare(strict_types=1);

namespace BitBlog;

final class Language
{
    private static ?array $translations = null;
    private static ?string $currentLanguage = null;
    private static ?string $detectedLanguage = null;

    /**
     * Detect language from browser Accept-Language header or URL parameter
     */
    public static function detect(): string
    {
        // Cache detection result per request
        if (self::$detectedLanguage !== null) {
            return self::$detectedLanguage;
        }

        // Check for URL parameter first (for easy testing)
        if (isset($_GET['lang']) && in_array($_GET['lang'], self::getSupportedLanguages())) {
            self::$detectedLanguage = $_GET['lang'];
            return self::$detectedLanguage;
        }

        $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        // Parse Accept-Language header (e.g., "de-DE,de;q=0.9,en;q=0.8,en-US;q=0.7")
        $languages = [];
        if (preg_match_all('/([a-z]{2})(?:-[A-Z]{2})?(?:;q=([0-9.]+))?/i', $acceptLang, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lang = strtolower($match[1]); // Ensure lowercase
                $quality = isset($match[2]) ? (float)$match[2] : 1.0;
                $languages[$lang] = $quality;
            }
            // Sort by quality (preference)
            arsort($languages);
        }

        // Check if we support any of the preferred languages
        $supportedLanguages = self::getSupportedLanguages();
        foreach (array_keys($languages) as $lang) {
            $mappedLang = self::applyLanguageOverrides($lang);
            if (in_array($mappedLang, $supportedLanguages)) {
                self::$detectedLanguage = $mappedLang;
                return $mappedLang;
            }
        }

        // Fallback to default language
        self::$detectedLanguage = Config::DEFAULT_LANGUAGE ?? 'de';
        return self::$detectedLanguage;
    }

    /**
     * Set language explicitly (useful for testing or admin override)
     */
    public static function setLanguage(string $lang): void
    {
        self::$currentLanguage = $lang;
    }

    /**
     * Get translated text for a key
     */
    public static function getText(string $key, ?string $lang = null): string
    {
        $lang = $lang ?? self::detect();
        
        if (self::$translations === null) {
            self::loadTranslations();
        }

        return self::$translations[$lang][$key] ?? self::$translations[Config::DEFAULT_LANGUAGE ?? 'de'][$key] ?? $key;
    }

    /**
     * Get translated text with sprintf formatting
     */
    public static function getTextf(string $key, ...$args): string
    {
        $text = self::getText($key);
        return sprintf($text, ...$args);
    }

    /**
     * Translate tag name - if empty, return translated "uncategorized"
     */
    public static function translateTagName(string $tag): string
    {
        // Empty tag means uncategorized
        if (empty($tag)) {
            return self::getText('uncategorized');
        }
        
        return $tag; // Return original tag name
    }

    /**
     * Get all supported languages by scanning the lang directory
     */
    public static function getSupportedLanguages(): array
    {
        static $supportedLanguages = null;
        
        if ($supportedLanguages === null) {
            $supportedLanguages = [];
            $langDir = __DIR__ . '/lang';
            
            if (is_dir($langDir)) {
                $files = scandir($langDir);
                foreach ($files as $file) {
                    if (preg_match('/^([a-z]{2})\.json$/i', $file, $matches)) {
                        $supportedLanguages[] = strtolower($matches[1]);
                    }
                }
            }
            
            // Error if no language files found - this should never happen in production
            if (empty($supportedLanguages)) {
                throw new \RuntimeException(
                    'No language files found in ' . $langDir . '! ' .
                    'Please create at least one .json file (e.g., de.json, en.json) in the lang directory.'
                );
            }
            
            // Build language overrides from all language files
            self::buildLanguageOverrides();
        }
        
        return $supportedLanguages;
    }
    
    /**
     * Language override mappings built from JSON files
     */
    private static ?array $languageOverrides = null;
    
    /**
     * Build language overrides from all language files
     */
    private static function buildLanguageOverrides(): void
    {
        self::$languageOverrides = [];
        
        // Load translations first to get override maps
        if (self::$translations === null) {
            self::loadTranslations();
        }
        
        // Collect language overrides from all translation files
        foreach (self::$translations as $lang => $translations) {
            if (isset($translations['_language_overrides']) && is_array($translations['_language_overrides'])) {
                self::$languageOverrides = array_merge(self::$languageOverrides, $translations['_language_overrides']);
            }
        }
    }
    
    /**
     * Apply language overrides to browser language detection
     */
    private static function applyLanguageOverrides(string $lang): string
    {
        if (self::$languageOverrides === null) {
            self::buildLanguageOverrides();
        }
        return self::$languageOverrides[$lang] ?? $lang;
    }

    /**
     * Get current language
     */
    public static function getCurrentLanguage(): string
    {
        // Use explicitly set language if available, otherwise detect
        return self::$currentLanguage ?? self::detect();
    }

    /**
     * Load translations from JSON files
     */
    private static function loadTranslations(): void
    {
        self::$translations = [];
        
        foreach (self::getSupportedLanguages() as $lang) {
            $langFile = __DIR__ . "/lang/{$lang}.json";
            
            if (file_exists($langFile)) {
                $jsonContent = file_get_contents($langFile);
                $decodedTranslations = json_decode($jsonContent, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTranslations)) {
                    self::$translations[$lang] = $decodedTranslations;
                } else {
                    // Fallback for invalid JSON
                    self::$translations[$lang] = [];
                }
            } else {
                // Fallback if language file doesn't exist
                self::$translations[$lang] = [];
            }
        }
    }

    /**
     * Get locale string for date formatting using automatic detection or override map
     */
    public static function getLocale(?string $lang = null): string
    {
        static $localeCache = [];
        
        $lang = $lang ?? self::detect();
        
        // Return cached result if available
        if (isset($localeCache[$lang])) {
            return $localeCache[$lang];
        }
        
        if (self::$translations === null) {
            self::loadTranslations();
        }
        
        // Get locale from language file - this is required!
        if (isset(self::$translations[$lang]['_locale'])) {
            $localeCache[$lang] = self::$translations[$lang]['_locale'];
            return $localeCache[$lang];
        }
        
        // Missing _locale field - this should not happen
        throw new \RuntimeException(
            "Language file for '{$lang}' is missing required '_locale' field. " .
            "Please add it to src/lang/{$lang}.json (e.g., \"_locale\": \"de_DE\")"
        );
    }
}
