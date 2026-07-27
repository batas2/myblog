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

// CLI only — shipped to the server for the cron/admin rebuild, never web-run
if (PHP_SAPI !== 'cli' && !defined('BUILD_NO_RUN')) { http_response_code(404); exit; }

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/markdown.php';
require __DIR__ . '/../lib/blog.php';
require __DIR__ . '/../lib/schema.php';
require __DIR__ . '/../lib/components.php';
require __DIR__ . '/../lib/page.php';
require __DIR__ . '/../lib/pages.php';
require __DIR__ . '/../lib/feeds.php';

/**
 * Remove generated blog/<slug>/ and blog/tag/<slug>/ pages that no longer
 * correspond to a published post / live tag (post deleted, renamed, moved
 * back to draft, or still scheduled). A post directory is only treated as
 * generator-owned when it carries BOTH build artifacts (index.php AND
 * index.md) — anything hand-made is left untouched — and only those two
 * files are ever deleted. Returns the removed slugs.
 */
function build_cleanup_stale(array $keepSlugs, array $keepTagSlugs): array
{
    $removed = [];
    $sweep = function (string $parent, array $keep, string $label, bool $needPair) use (&$removed): void {
        foreach (glob($parent . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if ($slug === 'tag' || $slug === 'start' || in_array($slug, $keep, true)) continue;
            if ($needPair && !(is_file("$dir/index.php") && is_file("$dir/index.md"))) continue;
            foreach (['index.php', 'index.md'] as $f) {
                if (is_file("$dir/$f")) unlink("$dir/$f");
            }
            if (@rmdir($dir)) $removed[] = $label . $slug;
        }
    };
    $sweep(APP_ROOT . '/blog', $keepSlugs, '', true);
    if (is_dir(APP_ROOT . '/blog/tag')) $sweep(APP_ROOT . '/blog/tag', $keepTagSlugs, 'tag/', false);
    return $removed;
}

if (defined('BUILD_NO_RUN')) return;

/** Write a live-served file via tmp+rename: a request mid-build never sees a truncated page. */
function build_write(string $path, string $content): void
{
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $content) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        fwrite(STDERR, "warning: could not write {$path}\n");
    }
}

// one build at a time — cron and the admin "rebuild now" button can overlap
$buildLock = @fopen(CONTENT_DIR . '/.buildlock', 'c');
if ($buildLock === false || !flock($buildLock, LOCK_EX)) {
    fwrite(STDERR, "could not acquire the build lock\n");
    exit(1);
}

$all = load_posts(true);
$posts = array_values(array_filter($all, fn(array $p): bool => $p['status'] === 'published'));
if (!$posts) {
    // still sweep: un-publishing the last post must take its page down
    build_cleanup_stale([], []);
    fwrite(STDERR, $all ? "no published posts — generated pages swept\n" : "no posts found\n");
    exit(0);
}

@mkdir(APP_ROOT . '/blog', 0755, true);

$n = count($posts);
foreach ($posts as $i => $p) {
    $prev = $posts[$i + 1] ?? null;              // older
    $next = $i > 0 ? $posts[$i - 1] : null;      // newer
    $dir = APP_ROOT . '/blog/' . $p['slug'];
    @mkdir($dir, 0755, true);
    build_write($dir . '/index.php', render_post($p, $prev, $next, $posts));
    build_write($dir . '/index.md', render_md($p));
}

build_write(APP_ROOT . '/blog/index.php', render_index($posts));
build_write(APP_ROOT . '/blog/_latest.php', render_latest_partial($posts));
@mkdir(APP_ROOT . '/blog/start', 0755, true);
build_write(APP_ROOT . '/blog/start/index.php', render_start_page($posts));
build_write(APP_ROOT . '/404.php', render_404_page($posts));

$tags = collect_tags($posts);
foreach ($tags as $tag => $tagged) {
    $dir = APP_ROOT . '/blog/tag/' . tag_slug($tag);
    @mkdir($dir, 0755, true);
    build_write($dir . '/index.php', render_tag_page($tag, $tagged, $n));
}

// drop generated pages whose post is gone or no longer published, and stale tag archives
$removed = build_cleanup_stale(
    array_column($posts, 'slug'),
    array_map('tag_slug', array_keys($tags))
);

build_write(APP_ROOT . '/llms-full.txt', render_llms_full($posts));
if (!update_llms_selected(APP_ROOT . '/llms.txt', $posts)) {
    fwrite(STDERR, "warning: llms.txt has no 'Selected posts:' list to refresh\n");
}
build_write(APP_ROOT . '/feed.xml', render_rss($posts));
build_write(APP_ROOT . '/sitemap.xml', render_sitemap($posts));

echo "built {$n} posts + " . count($tags) . " tag pages → blog/, index, _latest partial, feed.xml, sitemap.xml\n";
if ($removed) echo 'removed stale pages: ' . implode(', ', $removed) . "\n";

// mirror the editorial scores into content/scores.db for the admin panel
// (docs/SCORING-RUBRIC.md). Best effort: an unwritable mirror is a reporting
// problem, never a reason to fail a site build that already produced pages.
try {
    echo 'scores: mirrored ' . sc_sync(sc_db(), $all) . " posts → content/scores.db\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'warning: could not sync content/scores.db: ' . $e->getMessage() . "\n");
}

// the publish gate: a published post with no scores was never judged
$unscored = array_column(array_filter($posts, fn(array $p): bool => !sc_complete($p['scores'])), 'slug');
if ($unscored) echo 'UNSCORED published posts: ' . implode(', ', $unscored) . "\n";

// a rename that left its old filename behind (an additive deploy will do this):
// the post is fine, but it is listed twice and only one of the two is current
foreach (post_duplicate_sources() as $slug => $files) {
    fwrite(STDERR, "DUPLICATE SOURCE for '{$slug}': " . implode(' + ', $files)
        . " — the newest file wins, delete the rest\n");
}

// mixed rubric versions make the numbers incomparable — see the rubric's calibration rules
$rubrics = array_unique(array_filter(array_column(array_column($all, 'scored'), 'rubric')));
if (count($rubrics) > 1) {
    fwrite(STDERR, 'warning: mixed rubric versions in the corpus (' . implode(', ', $rubrics) . ") — re-score to one version before comparing\n");
}

// publishing queue — what is waiting, and when it goes live (server cron rebuilds pick it up)
foreach ($all as $p) {
    if ($p['status'] === 'scheduled') {
        echo "scheduled: {$p['slug']} → goes live " . date('Y-m-d H:i', $p['publish_ts']) . "\n";
    } elseif ($p['status'] === 'draft') {
        echo "draft:     {$p['slug']}\n";
    }
}
