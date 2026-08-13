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
 * Two ways in, and nothing else is a way in:
 *
 *   1. An admin session. A stats session is not enough, since drafts are
 *      content, not analytics.
 *   2. A share link carrying `k=<token>` — an HMAC over the slug
 *      (preview_share_token()), for handing a draft to one or two people
 *      before it goes out.
 *
 * Everything else is a 404, including for slugs that exist: an unpublished
 * essay should not confirm that it is there.
 *
 * A share link is for a person, never for a machine. Known crawlers and link
 * unfurlers are refused even with a valid token, so pasting the link into a
 * chat does not put the draft in somebody else's cache, and the page keeps its
 * noindex headers on top of that. Nothing links here, and the generator writes
 * no page, so there is no path to it that does not start with the token.
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
require_once __DIR__ . '/lib/analytics.php';   // an_is_bot(), the crawler filter

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
// the token rides in the query string, so it must not travel in a Referer
// header when the reader clicks a link out of the draft
header('Referrer-Policy: no-referrer');

/** Every refusal looks identical from outside: the route reveals nothing. */
function preview_404(): void
{
    http_response_code(404);
    exit("Not found\n");
}

$slug = (string) ($_GET['slug'] ?? '');
if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) preview_404();

/* Who is asking. The admin sees the working banner with a way back; a share
   link gets a guest banner and no admin surface at all. */
$isAdmin = admin_session_active();
$isGuest = false;
if (!$isAdmin) {
    $token = preview_share_token($slug);
    $given = (string) ($_GET['k'] ?? '');
    // hash_equals: a token check that leaks timing is a token check somebody
    // can walk character by character
    if ($token === null || $given === '' || !hash_equals($token, $given)) preview_404();
    // a valid token in a crawler's hands is still a crawler
    if (an_is_bot((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) preview_404();
    $isGuest = true;
}

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

$barCss = '<style>'
     . '.pvbar{position:sticky;top:0;z-index:99;display:flex;flex-wrap:wrap;align-items:center;gap:.6rem 1.1rem;'
     . 'padding:.55rem 1.2rem;background:#b45309;color:#fff;font:600 .8rem/1.4 system-ui,sans-serif}'
     . '.pvbar .tag{font-family:ui-monospace,monospace;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;'
     . 'border:1px solid #ffffff66;border-radius:999px;padding:.15rem .55rem}'
     . '.pvbar .sp{margin-left:auto}.pvbar a{color:#fff;text-decoration:underline}'
     . '.pvbar a.go{text-decoration:none;border:1px solid #ffffff88;border-radius:999px;'
     . 'padding:.2rem .7rem;white-space:nowrap;transition:background .12s}'
     . '.pvbar a.go:hover{background:#ffffff26}'
     . '</style>';

/* The guest banner carries no editorial internals. Word count and status are
   fine to show a reader; the reach score is a private editorial judgement and
   the admin link is a door they cannot open, so neither is here. */
$bar = $barCss . '<div class="pvbar">'
     . '<span class="tag">' . ($isGuest ? 'unpublished draft' : 'preview') . '</span>'
     . ($isGuest
        ? '<span>Shared with you before it goes out' . ($post['status'] === 'scheduled' ? ', ' . e($when) : '')
          . '. Not public and not indexed, so please do not pass the link on.</span>'
        : '<span>' . e($post['status']) . ' · ' . e($when) . '</span>'
          . '<span>' . number_format($post['words']) . ' words · ' . $post['minutes'] . ' min'
          . ' · reach ' . ($reach !== null ? number_format($reach, 1) : 'unscored') . '</span>'
          . '<span class="sp"><a class="go" href="/review.php?slug=' . e($slug) . '">✎ Comment &amp; edit</a></span>'
          . '<span><a href="/admin.php#pipeline">back to admin</a></span>')
     . '</div>';

// one <body> in the shell (lib/page.php); substr_replace avoids regex backrefs
// firing on anything inside the banner
$at = strpos($html, '<body>');
echo $at === false ? $bar . $html : substr_replace($html, '<body>' . $bar, $at, strlen('<body>'));
