<?php
declare(strict_types=1);

require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Utils.php';
require __DIR__ . '/../src/Content.php';

use BitBlog\Config;
use BitBlog\Content;

$c = new Content(Config::CONTENT_DIR, Config::CACHE_DIR, Config::BASE_URL());
$c->rebuildIndex();
echo "Index rebuilt.\n";
