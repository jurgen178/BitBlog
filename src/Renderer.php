<?php
declare(strict_types=1);

namespace BitBlog;

final class Renderer
{
    private string $tplDir;
    private string $siteTitle;
    private string $baseUrl;

    public function __construct(string $tplDir, string $siteTitle, string $baseUrl)
    {
        $this->tplDir = rtrim($tplDir, '/');
        $this->siteTitle = $siteTitle;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function render(string $view, array $vars = []): string
    {
        $vars['siteTitle'] = $this->siteTitle;
        $vars['baseUrl'] = $this->baseUrl;
        
        // Don't show signature for private posts
        $showSignature = true;
        if (isset($vars['post']) && isset($vars['post']['status']) && $vars['post']['status'] === Constants::POST_STATUS_PRIVATE) {
            $showSignature = false;
        }
        
        $vars['signatureHtml'] = $showSignature ? $this->getSignatureHtml() : '';
        extract($vars, EXTR_OVERWRITE);
        ob_start();
        require $this->tplDir . '/' . $view . '.php';
        $content = (string)ob_get_clean();
        ob_start();
        require $this->tplDir . '/layout.php';
        return (string)ob_get_clean();
    }

    private function getSignatureHtml(): string
    {
        $currentLang = Language::getCurrentLanguage();
        $signatureFile = Config::CACHE_DIR . '/signature-' . $currentLang . '.html';
        
        if (file_exists($signatureFile)) {
            return (string)file_get_contents($signatureFile);
        }
        
        // Fallback to German signature if language-specific doesn't exist
        $fallbackSignatureFile = Config::CACHE_DIR . '/signature-de.html';
        if (file_exists($fallbackSignatureFile)) {
            return (string)file_get_contents($fallbackSignatureFile);
        }
        
        // Last resort fallback
        return '<p>' . Language::getText('built_with') . '</p>';
    }
}
