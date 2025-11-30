<?php
declare(strict_types=1);

namespace BitBlog;

final class Constants
{
    // Post status constants
    public const POST_STATUS_DRAFT = 'draft';
    public const POST_STATUS_PUBLISHED = 'published';
    public const POST_STATUS_PRIVATE = 'private';
    
    // File extensions
    public const MARKDOWN_EXTENSION = 'md';
    
    // Default values
    public const DEFAULT_POST_STATUS = self::POST_STATUS_DRAFT;
    
    // Cache keys
    public const CACHE_INDEX_FILE = 'index.json';
    
    // Session keys
    public const SESSION_ADMIN = 'admin';
    public const SESSION_CSRF = 'csrf';
    
    // Routes
    public const ROUTE_HOME = 'home';
    public const ROUTE_POST = 'post';
    public const ROUTE_POST_BY_NAME = 'post_by_name';
    public const ROUTE_TAG = 'tag';
    public const ROUTE_PAGE = 'page';
    public const ROUTE_FEED = 'feed';
    public const ROUTE_SITEMAP = 'sitemap';
    

}
