<?php
use BitBlog\Utils;
use BitBlog\Language;
$title = $title ?? Language::getText('page_not_found');
?>
<article class="page">
  <h1><?= Utils::e($title) ?></h1>
  <section class="content">
    <?= $html ?>
  </section>
</article>
