<?php
/**
 * tools/build.php — static site generator entrypoint.
 *
 * Reads   content/posts/YYYY-MM-DD-slug.md
 * Writes  blog/<slug>/index.php + index.md   (pre-rendered article + md mirror)
 *         blog/index.php                     (blog index)
 *         blog/tag/<tag>/index.php           (tag archives)
 *         blog/_latest.php                   (homepage partial)
 *         feed.xml, sitemap.xml, llms-full.txt
 *
 * Run: php tools/build.php
 * Tests include this file with BUILD_NO_RUN defined to get the functions only.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/markdown.php';
require __DIR__ . '/../lib/blog.php';
require __DIR__ . '/../lib/templates.php';

if (defined('BUILD_NO_RUN')) return;

$posts = load_posts();
if (!$posts) { fwrite(STDERR, "no posts found\n"); exit(1); }

@mkdir(APP_ROOT . '/blog', 0755, true);

$n = count($posts);
foreach ($posts as $i => $p) {
    $prev = $posts[$i + 1] ?? null;              // older
    $next = $i > 0 ? $posts[$i - 1] : null;      // newer
    $dir = APP_ROOT . '/blog/' . $p['slug'];
    @mkdir($dir, 0755, true);
    file_put_contents($dir . '/index.php', render_post($p, $prev, $next));
    file_put_contents($dir . '/index.md', render_md($p));
}

file_put_contents(APP_ROOT . '/blog/index.php', render_index($posts));
file_put_contents(APP_ROOT . '/blog/_latest.php', render_latest_partial($posts));

$tags = collect_tags($posts);
foreach ($tags as $tag => $tagged) {
    $dir = APP_ROOT . '/blog/tag/' . tag_slug($tag);
    @mkdir($dir, 0755, true);
    file_put_contents($dir . '/index.php', render_tag_page($tag, $tagged, $n));
}

file_put_contents(APP_ROOT . '/llms-full.txt', render_llms_full($posts));
file_put_contents(APP_ROOT . '/feed.xml', render_rss($posts));
file_put_contents(APP_ROOT . '/sitemap.xml', render_sitemap($posts));

echo "built {$n} posts + " . count($tags) . " tag pages → blog/, index, _latest partial, feed.xml, sitemap.xml\n";
