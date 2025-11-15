<?php
require_once '../src/RenderMarkdown.php';
require_once '../src/Language.php';

use BitBlog\RenderMarkdown;
use BitBlog\Language;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => Language::getText('method_not_allowed')]);
    exit;
}

$markdown = $_POST['markdown'] ?? '';

if (trim($markdown) === '') {
    echo json_encode(['success' => true, 'html' => '<em>' . Language::getText('preview_placeholder') . '</em>']);
    exit;
}

try {
    $html = RenderMarkdown::toHtml($markdown);
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => Language::getText('rendering_failed')
    ]);
}
?>
