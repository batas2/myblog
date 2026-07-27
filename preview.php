<?php
/**
 * preview.php — read an unpublished essay in its real layout, admin only.
 *
 * The generator writes pages only for published posts, which is the right
 * default: a draft must not be reachable at a guessable URL. That leaves no
 * way to see a draft the way a reader will before deciding to publish it.
 * This renders the page on demand with the same render_post() the build uses,
 * writes nothing to disk, and is linked only from the admin pipeline.
 *
 * Without an admin session every response is a 404, including for slugs that
 * exist: an unpublished essay should not confirm that it is there. A stats
 * session is not enough, since drafts are content, not analytics.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/schema.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/page.php';
require_once __DIR__ . '/lib/pages.php';
require_once __DIR__ . '/lib/admin.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

/** Every refusal looks identical from outside: the route reveals nothing. */
function preview_404(): void
{
    http_response_code(404);
    exit("Not found\n");
}

if (!admin_session_active()) preview_404();

$slug = (string) ($_GET['slug'] ?? '');
if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) preview_404();

$file = post_source_file($slug);
if ($file === null) preview_404();
$post = parse_post_file($file);
if ($post === null) preview_404();

/* Neighbours: a published post keeps its real ones. An unpublished post is
   shown where it will actually land, directly after the newest published
   essay, so the pager reads the way it will on the day it goes live. */
$published = load_posts();
$i = array_search($slug, array_column($published, 'slug'), true);
$prev = $i === false ? ($published[0] ?? null) : ($published[$i + 1] ?? null);
$next = $i === false ? null : ($i > 0 ? $published[$i - 1] : null);

$html = render_post($post, $prev, $next, $published);

/* The generated page carries one PHP island, the comment thread. Preview never
   executes generated source; it substitutes a note instead. Nothing is lost:
   comments.php gates on post_is_public(), so an unpublished post renders no
   thread anyway. */
$html = (string) preg_replace(
    '/<\?php.*?\?>/s',
    '<p class="dek" style="text-align:center;opacity:.6">The comment thread appears here once the post is published.</p>',
    $html
);

$reach = sc_reach($post['scores']);
$when = $post['status'] === 'published'
    ? 'published ' . date('j M Y', $post['publish_ts'])
    : 'goes live ' . date('D j M Y, H:i', $post['publish_ts']);

$bar = '<style>'
     . '.pvbar{position:sticky;top:0;z-index:99;display:flex;flex-wrap:wrap;align-items:center;gap:.6rem 1.1rem;'
     . 'padding:.55rem 1.2rem;background:#b45309;color:#fff;font:600 .8rem/1.4 system-ui,sans-serif}'
     . '.pvbar .tag{font-family:ui-monospace,monospace;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;'
     . 'border:1px solid #ffffff66;border-radius:999px;padding:.15rem .55rem}'
     . '.pvbar .sp{margin-left:auto}.pvbar a{color:#fff;text-decoration:underline}'
     . '</style>'
     . '<div class="pvbar">'
     . '<span class="tag">preview</span>'
     . '<span>' . e($post['status']) . ' · ' . e($when) . '</span>'
     . '<span>' . number_format($post['words']) . ' words · ' . $post['minutes'] . ' min'
     . ' · reach ' . ($reach !== null ? number_format($reach, 1) : 'unscored') . '</span>'
     . '<span class="sp"><a href="/admin.php#pipeline">back to admin</a></span>'
     . '</div>';

// one <body> in the shell (lib/page.php); substr_replace avoids regex backrefs
// firing on anything inside the banner
$at = strpos($html, '<body>');
echo $at === false ? $bar . $html : substr_replace($html, '<body>' . $bar, $at, strlen('<body>'));
