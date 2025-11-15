<?php
use BitBlog\Utils;
use BitBlog\Language;

// Translate tag name (handles uncategorized automatically)
$displayTag = Language::translateTagName($tag);

$title = Language::getText('tags') . ': ' . $displayTag;
require __DIR__ . '/list.php';
