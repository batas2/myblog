<?php
/**
 * lib/feeds.php — machine-readable outputs: RSS 2.0 (full content) and the sitemap.
 */

declare(strict_types=1);

function render_rss(array $posts): string
{
    $site = (string) cfg('site_url');
    $author = e((string) cfg('author'));
    $items = '';
    foreach ($posts as $p) {
        $url = post_url($p['slug']);
        $pub = date('r', $p['publish_ts']);
        $cats = '';
        foreach ($p['tags'] as $t) $cats .= '      <category>' . e($t) . "</category>\n";
        $content = str_replace(']]>', ']]&gt;', $p['html']);
        $items .= "    <item>\n"
            . '      <title>' . e($p['title']) . "</title>\n"
            . "      <link>{$url}</link>\n"
            . "      <guid isPermaLink=\"true\">{$url}</guid>\n"
            . "      <pubDate>{$pub}</pubDate>\n"
            . "      <dc:creator>{$author}</dc:creator>\n"
            . '      <description>' . e($p['subtitle']) . "</description>\n"
            . "      <content:encoded><![CDATA[{$content}]]></content:encoded>\n"
            . $cats
            . "    </item>\n";
    }
    $now = date('r');
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>Field Notes · Bartosz Frąckowiak</title>
    <link>{$site}/blog/</link>
    <atom:link href="{$site}/feed.xml" rel="self" type="application/rss+xml"/>
    <description>Weekly essays on software architecture, the people around it, and the corporate machine they form together.</description>
    <language>en</language>
    <lastBuildDate>{$now}</lastBuildDate>
{$items}  </channel>
</rss>
XML;
}

function render_sitemap(array $posts): string
{
    $site = (string) cfg('site_url');
    $latest = $posts ? date('Y-m-d', $posts[0]['publish_ts']) : date('Y-m-d');
    // the root is the blog listing, so there is no separate /blog/ entry to
    // submit; /services/ is deliberately absent, it carries noindex and is only
    // ever reached from /about/ or from a link sent by hand. /stemma/ is the
    // opposite case — a project page meant to be found, and one day to move to
    // its own domain, so it is submitted like any other page.
    // …and it changes on its own schedule rather than the publishing one, so it dates itself
    // from its own source instead of borrowing the newest essay's date.
    // …and its changelog dates itself the same way, from the file that holds the releases.
    $stemmaSrc = APP_ROOT . '/stemma/index.php';
    $stemmaMod = is_file($stemmaSrc) ? date('Y-m-d', (int) filemtime($stemmaSrc)) : $latest;
    $logSrc = APP_ROOT . '/stemma/changelog/index.php';
    $logMod = is_file($logSrc) ? date('Y-m-d', (int) filemtime($logSrc)) : $stemmaMod;
    $urls = "  <url><loc>{$site}/</loc><lastmod>{$latest}</lastmod><priority>1.0</priority></url>\n"
          . "  <url><loc>{$site}/blog/start/</loc><lastmod>{$latest}</lastmod><priority>0.8</priority></url>\n"
          . "  <url><loc>{$site}/blog/archive/</loc><lastmod>{$latest}</lastmod><priority>0.6</priority></url>\n"
          . "  <url><loc>{$site}/about/</loc><lastmod>{$latest}</lastmod><priority>0.7</priority></url>\n"
          . "  <url><loc>{$site}/stemma/</loc><lastmod>{$stemmaMod}</lastmod><priority>0.8</priority></url>\n"
          . "  <url><loc>{$site}/stemma/changelog/</loc><lastmod>{$logMod}</lastmod><priority>0.5</priority></url>\n";
    foreach ($posts as $p) {
        // a revised essay advertises its revision, not its birthday
        $lastmod = date('Y-m-d', $p['updated_ts'] ?? $p['publish_ts']);
        $urls .= '  <url><loc>' . post_url($p['slug']) . '</loc><lastmod>' . $lastmod . "</lastmod><priority>0.7</priority></url>\n";
    }
    foreach (collect_tags($posts) as $tag => $tagged) {
        $urls .= '  <url><loc>' . $site . tag_path($tag) . '</loc><lastmod>' . date('Y-m-d', $tagged[0]['publish_ts']) . "</lastmod><priority>0.4</priority></url>\n";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}</urlset>\n";
}
