<?php
/**
 * tests/integration.php — end-to-end suite against a sandboxed copy of the site.
 *
 * 1. Copies the repo into a temp dir (without runtime data, backups, docs),
 *    swaps in a deterministic test config, and injects fixture posts covering
 *    every publish status (published / scheduled / draft).
 * 2. Runs the real build there and asserts on the generated artifacts —
 *    including that scheduled + draft posts stay out of everything.
 * 3. Serves the sandbox with `php -S` and drives every endpoint over HTTP:
 *    pages, analytics beacon v2 (+ engagement), stats auth + dashboard,
 *    the full comment/captcha flow, and the admin login/rebuild/moderation flow.
 *
 * The real content/ (live comments, analytics) is never touched.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
// constants only (no config, no side effects) — the scores assertions below
// check the page against the same axis list and rubric version the site ships
require_once dirname(__DIR__) . '/lib/scores.php';

$ROOT = dirname(__DIR__);
$SANDBOX = sys_get_temp_dir() . '/bfrackowiak-test-' . getmypid();
$PORT = 8900 + (getmypid() % 90);
$BASE = "http://127.0.0.1:{$PORT}";
$serverPid = null;
const ADMIN_PW = 'itest-admin-pw';
const STATS_KEY = 'itest-stats-key';

$cleanup = function () use (&$serverPid, $SANDBOX) {
    if ($serverPid) { exec('kill ' . (int) $serverPid . ' 2>/dev/null'); $serverPid = null; }
    if (is_dir($SANDBOX)) exec('rm -rf ' . escapeshellarg($SANDBOX));
};
register_shutdown_function($cleanup);

/** run a php snippet with the sandbox as cwd, return trimmed stdout */
function sbx_php(string $code): string
{
    global $SANDBOX;
    exec('cd ' . escapeshellarg($SANDBOX) . ' && ' . PHP_BINARY . ' -r ' . escapeshellarg($code) . ' 2>/dev/null', $out);
    return trim(implode("\n", $out));
}

/* ---------------- sandbox + fixtures + build ---------------- */
section('sandbox build');
exec('rsync -a --exclude=_remote-backup/ --exclude=_archive/ --exclude=docs/ --exclude=tests/ '
   . '--exclude=.claude/ --exclude=.git/ --exclude=content/comments/ --exclude=content/analytics.db '
   . '--exclude=content/analytics.seed --exclude=content/geoip.dat --exclude=content/newsletter.db --exclude=content/newsletter.db-* '
   . '--exclude=content/scores.db --exclude=content/scores.db-* --exclude=blog/ '
   . escapeshellarg($ROOT . '/') . ' ' . escapeshellarg($SANDBOX . '/'), $o, $rc);
t_eq(0, $rc, 'sandbox copied');
mkdir($SANDBOX . '/content/comments', 0755, true);

// deterministic config: known keys, admin enabled, everything else prod-like
file_put_contents($SANDBOX . '/config.php', '<?php return ' . var_export([
    'site_url'            => 'https://bfrackowiak.pl',
    'site_name'           => 'bfrackowiak.pl',
    'author'              => 'Bartosz Frąckowiak',
    'og_image'            => 'https://bfrackowiak.pl/myself.jpeg',
    'newsletter_action'   => '/n.php',
    'comments_secret'     => 'itest-comments-secret',
    'stats_key'           => STATS_KEY,
    'admin_password_hash' => password_hash(ADMIN_PW, PASSWORD_DEFAULT),
    'timezone'            => 'Europe/Warsaw',
    'php_bin'             => PHP_BINARY,
    'smtp'                => ['host' => '', 'port' => 587, 'user' => '', 'pass' => '', 'from' => '', 'from_name' => '', 'to' => ''],
], true) . ";\n");

// fixture posts: one per status
$fixture = fn(string $title, string $extra = '') => "# {$title}\n\n*Integration fixture subtitle.*\n\n"
    . "A body paragraph long enough to register a word count for the fixture.\n"
    . ($extra !== '' ? "\n{$extra}\n" : '') . "\n---\n\n**Tags:** ITest Alpha, Software Architecture\n";
file_put_contents($SANDBOX . '/content/posts/2020-01-01-itest-past-post.md', $fixture('ITest Past Post'));
file_put_contents($SANDBOX . '/content/posts/2099-01-01-itest-future-post.md',
    str_replace('ITest Alpha', 'ITest Future', $fixture('ITest Future Post')));
file_put_contents($SANDBOX . '/content/posts/2024-01-01-itest-draft-post.md', $fixture('ITest Draft Post', '**Status:** draft'));
// one fully scored post with a rationale on file: the admin panel explains every
// number from these two sources, so the pane needs both to be exercised
$itestScores = 'tension=8 originality=6 personal=9 contrarian=4 substance=7 punch=5 trend=2';
file_put_contents($SANDBOX . '/content/posts/2021-02-02-itest-scored-post.md', $fixture(
    'ITest Scored Post',
    "**Scores:** {$itestScores}\n**Scored:** 2026-07-27 rubric=" . SC_RUBRIC . ' model=itest-model'
));
file_put_contents($SANDBOX . '/content/score-notes.json', json_encode([
    'itest-scored-post' => ['tension' => '8 — itest rationale quote for tension.'],
], JSON_UNESCAPED_SLASHES));

exec('cd ' . escapeshellarg($SANDBOX) . ' && ' . PHP_BINARY . ' tools/build.php 2>&1', $out, $rc);
$buildOut = implode("\n", $out);
t_eq(0, $rc, 'build exits 0 (' . $buildOut . ')');
t_contains('scheduled: itest-future-post', $buildOut, 'build reports the scheduled queue');
t_contains('draft:     itest-draft-post', $buildOut, 'build reports drafts');

// the sandbox's own model is the source of truth for expected counts
[$allN, $pubN, $tagN] = array_map('intval', explode(' ', sbx_php(
    'require "lib/config.php"; require "lib/helpers.php"; require "lib/markdown.php"; require "lib/blog.php";'
    . 'echo count(load_posts(true)), " ", count(load_posts()), " ", count(collect_tags(load_posts()));'
)));
t_assert($allN >= $pubN + 2 && $pubN > 0, "counts sane (all={$allN}, published={$pubN}, tags={$tagN})");

$postDirs = array_filter(glob($SANDBOX . '/blog/*'), fn($d) => is_dir($d) && !in_array(basename($d), ['tag', 'start'], true));
t_eq($pubN, count($postDirs), 'exactly one directory per published post');
$mdOk = $phpOk = true;
foreach ($postDirs as $d) {
    if (!is_file("$d/index.php")) $phpOk = false;
    if (!is_file("$d/index.md")) $mdOk = false;
}
t_assert($phpOk, 'every post has index.php');
t_assert($mdOk, 'every post has markdown mirror');

/* ---------------- scheduling lifecycle ---------------- */
section('scheduling lifecycle');
t_assert(is_dir($SANDBOX . '/blog/itest-past-post'), 'published fixture built');
t_assert(!is_dir($SANDBOX . '/blog/itest-future-post'), 'scheduled fixture not built');
t_assert(!is_dir($SANDBOX . '/blog/itest-draft-post'), 'draft fixture not built');
$index = (string) file_get_contents($SANDBOX . '/blog/index.php');
t_contains('ITest Past Post', $index, 'published fixture listed on index');
t_not_contains('ITest Future Post', $index, 'scheduled fixture absent from index');
t_not_contains('ITest Draft Post', $index, 'draft fixture absent from index');
$feed = (string) file_get_contents($SANDBOX . '/feed.xml');
t_contains('ITest Past Post', $feed, 'published fixture in RSS');
t_not_contains('ITest Future Post', $feed, 'scheduled fixture absent from RSS');
$smRaw = (string) file_get_contents($SANDBOX . '/sitemap.xml');
t_not_contains('itest-future-post', $smRaw, 'scheduled fixture absent from sitemap');
t_assert(is_dir($SANDBOX . '/blog/tag/itest-alpha'), 'tag page for published fixture tag');
t_assert(!is_dir($SANDBOX . '/blog/tag/itest-future'), 'no tag page from scheduled-only tag');
t_not_contains('ITest Future Post', (string) file_get_contents($SANDBOX . '/llms-full.txt'), 'scheduled fixture absent from llms-full');

// a post pulled back out of publication loses its generated page on rebuild;
// a hand-made directory (no index.md pair) is never touched
mkdir($SANDBOX . '/blog/itest-stale', 0755, true);
file_put_contents($SANDBOX . '/blog/itest-stale/index.php', 'stale');
file_put_contents($SANDBOX . '/blog/itest-stale/index.md', 'stale');
mkdir($SANDBOX . '/blog/itest-handmade', 0755, true);
file_put_contents($SANDBOX . '/blog/itest-handmade/index.php', 'hand-authored page');
exec('cd ' . escapeshellarg($SANDBOX) . ' && ' . PHP_BINARY . ' tools/build.php 2>&1', $out2, $rc2);
t_eq(0, $rc2, 'rebuild exits 0');
t_assert(!is_dir($SANDBOX . '/blog/itest-stale'), 'stale generated page removed by rebuild');
t_assert(is_file($SANDBOX . '/blog/itest-handmade/index.php'), 'hand-made page without index.md pair survives cleanup');
exec('rm -rf ' . escapeshellarg($SANDBOX . '/blog/itest-handmade'));

/* ---------------- generated page contracts ---------------- */
section('generated page contracts');
t_assert(simplexml_load_file($SANDBOX . '/feed.xml') !== false, 'generated feed.xml valid');
$sm = simplexml_load_file($SANDBOX . '/sitemap.xml');
t_assert($sm !== false, 'generated sitemap.xml valid');
t_eq(4 + $pubN + $tagN, count($sm->url), 'sitemap url count = home + blog + start + services + posts + tags');
t_contains('/blog/start/', $smRaw, 'start page in sitemap');
t_contains('/services/', $smRaw, 'services page in sitemap');
t_assert(is_file($SANDBOX . '/blog/start/index.php'), 'start page generated');
t_assert(is_file($SANDBOX . '/404.php'), '404 page generated');
t_contains('http_response_code(404)', (string) file_get_contents($SANDBOX . '/404.php'), 'generated 404 sets status');
t_contains('ErrorDocument 404 /404.php', (string) file_get_contents($SANDBOX . '/.htaccess'), '404 wired in .htaccess');
t_eq($pubN, substr_count((string) file_get_contents($SANDBOX . '/llms-full.txt'), "\n# "), 'llms-full contains every published post');

$post = (string) file_get_contents($SANDBOX . '/blog/mathematicians-vs-lawyers/index.php');
t_contains('rel="canonical" href="https://bfrackowiak.pl/blog/mathematicians-vs-lawyers/"', $post, 'canonical set');
t_contains('property="og:type" content="article"', $post, 'og:type article');
t_not_contains('property="og:type" content="website"', $post, 'no duplicate website og:type on articles');
t_contains('max-image-preview:large', $post, 'robots meta allows large image previews');
t_contains('"sameAs"', $post, 'author entity with sameAs on the article page');
t_contains('article:published_time" content="2026-07-13T09:00:00+02:00"', $post, 'published_time in site timezone');
preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $post, $ld);
$ldOk = count($ld[1]) >= 2;
foreach ($ld[1] as $j) if (json_decode($j) === null) $ldOk = false;
t_assert($ldOk, 'post JSON-LD blocks parse');
t_contains('COMMENT_SLUG', $post, 'comments include wired');
t_contains('newsletter', $post, 'newsletter box on post');
t_contains('sendBeacon', $post, 'analytics beacon on post');
t_contains('pagehide', $post, 'engagement beacon on post');
t_contains('Related field notes', $post, 'related essays under the post');
t_not_contains('fonts.googleapis.com', $post, 'no external fonts on generated pages');
t_contains('/assets/fonts.css', $post, 'self-hosted fonts linked');
$legacy = (string) file_get_contents($SANDBOX . '/blog/stop-calling-it-legacy-code/index.php');
t_contains('Also published on Medium', $legacy, 'syndication badge on medium post');
t_contains('rel="syndication"', $legacy, 'syndication head link');
t_contains('class="featured2', $index, 'featured split card present');
t_eq($pubN - 1, substr_count($index, 'class="pcard reveal"'), 'grid cards = published minus featured');
t_not_contains('On Medium</h2>', $index, 'medium strip removed');

/* ---------------- HTTP layer ---------------- */
section('http server');
/* The port is derived from the pid, so two runs can collide: `php -S` then loses
   the bind, dies silently under nohup, and every request lands on the *other*
   run's leftover server — which serves a different sandbox and fails assertions
   in ways that look like product bugs (this ate a deploy on 2026-07-27). Serve a
   token only this sandbox knows and refuse any server that cannot echo it back;
   walk to the next port instead of testing a stranger. */
$token = bin2hex(random_bytes(8));
file_put_contents($SANDBOX . '/sandbox-id.txt', $token);
$ready = false;
for ($try = 0; $try < 6 && !$ready; $try++) {
    $port = $PORT + $try * 91;   // 91 = one past the pid-derived spread, so tries never overlap
    $base = "http://127.0.0.1:{$port}";
    /* opcache off: several assertions rewrite the sandbox config.php and expect
       the very next request to see it. With opcache on (it is, in the built-in
       server) the compiled file is only revalidated every opcache.revalidate_freq
       seconds — 2 by default — so a fast run reads the previous config and the
       owner-IP exclusion silently does not apply. */
    $pid = (int) exec(
        'cd ' . escapeshellarg($SANDBOX) . ' && nohup ' . PHP_BINARY
        . ' -d opcache.enable=0 -d opcache.enable_cli=0 -S 127.0.0.1:' . $port
        . ' >/dev/null 2>&1 & echo $!'
    );
    for ($i = 0; $i < 20; $i++) {
        usleep(150000);
        [$code, $body] = http_req('GET', "$base/sandbox-id.txt");
        if ($code === 200 && trim((string) $body) === $token) { $ready = true; break; }
        if ($code === 200) break;   // someone else's server owns this port — move on
    }
    if ($ready) { $serverPid = $pid; $PORT = $port; $BASE = $base; break; }
    exec('kill ' . $pid . ' 2>/dev/null');
}
t_assert($ready, "php -S serving this sandbox on :{$PORT}");

[$code, $body] = http_req('GET', "$BASE/");
t_eq(200, $code, 'homepage 200');
t_contains('Get the next field note', $body, 'newsletter on homepage');
t_contains('pagehide', $body, 'engagement beacon on homepage');
t_contains('bfx=1', $body, 'beacon script honors the owner exclusion cookie');
[$code, $body] = http_req('GET', "$BASE/blog/");
t_eq(200, $code, 'blog index 200');
[$code, $body] = http_req('GET', "$BASE/blog/tag/software-architecture/");
t_eq(200, $code, 'tag page 200');
[$code, $body] = http_req('GET', "$BASE/blog/start/");
t_eq(200, $code, 'start page 200');
t_contains('Then pick your door', $body, 'start page doors served');
t_contains('href="/blog/mathematicians-vs-lawyers/"', $body, 'start page hook served');
[$code, $body] = http_req('GET', "$BASE/services/");
t_eq(200, $code, 'services page 200');
t_contains('Architecture Health Check', $body, 'services page lists engagements');
t_not_contains('PLN', $body, 'no prices on the public services page');
t_contains('/#contact', $body, 'services page falls back to contact CTA when calendly unset');
[, $body] = http_req('GET', "$BASE/blog/nobody-owns-the-sum/");
t_contains('href="/services/"', $body, 'post pages link to services (author box + footer)');
[, $body] = http_req('GET', "$BASE/blog/nobody-owns-the-sum/");
t_contains('/blog/start/', $body, 'post pages link to start (nav + author box)');
preg_match('~<ul class="nav-links">(.*?)</ul>~s', $body, $navM);
$nav = $navM[1] ?? '';
t_contains('>Blog<', $nav, 'top nav labels the archive "Blog"');
t_not_contains('>Writing<', $nav, 'top nav no longer says "Writing"');
t_not_contains('nav-cta', $nav, 'top nav drops the "Work with me" CTA');
t_not_contains('/services/', $nav, 'top nav no longer links services');
t_eq(true, strpos($nav, '/blog/start/') < strpos($nav, 'href="/blog/"'), 'start here sits left of blog in the nav');
[, $body] = http_req('GET', "$BASE/blog/");
t_contains('start here', $body, 'blog index invites new readers to start page');
[$code, $body] = http_req('GET', "$BASE/blog/data-driven-theater/index.md");
t_eq(200, $code, 'markdown mirror served');
[$code] = http_req('GET', "$BASE/feed.xml");
t_eq(200, $code, 'feed served');
[$code] = http_req('GET', "$BASE/assets/fonts.css");
t_eq(200, $code, 'fonts.css served');
$woff = basename((string) (glob($SANDBOX . '/assets/fonts/*.woff2')[0] ?? ''));
[$code] = http_req('GET', "$BASE/assets/fonts/{$woff}");
t_eq(200, $code, 'woff2 files served');
[$code, $body] = http_req('GET', "$BASE/404.php");
t_eq(404, $code, 'branded 404 answers with 404 status');
t_contains('No signal on this frequency', $body, 'branded 404 body served');

/* ---------------- newsletter (self-hosted) ---------------- */
section('newsletter');
[$code, $body] = http_req('POST', "$BASE/n.php", [
    'headers' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'body' => 'email=reader%40example.com&website=',
]);
t_eq(200, $code, 'subscribe accepted');
t_contains('Check your inbox', $body, 'subscribe shows double-opt-in page');
$nlTok = sbx_php('require "lib/config.php"; require "lib/newsletter.php";
    echo nl_db()->query("SELECT token FROM subscribers WHERE email = \'reader@example.com\'")->fetchColumn();');
t_assert(strlen($nlTok) === 32, 'pending subscriber stored with token');
[$code, $body] = http_req('POST', "$BASE/n.php", [
    'headers' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'body' => 'email=bot%40example.com&website=spam',
]);
t_contains('Check your inbox', $body, 'honeypot pretends success');
t_eq('', sbx_php('require "lib/config.php"; require "lib/newsletter.php";
    echo (string) nl_db()->query("SELECT token FROM subscribers WHERE email = \'bot@example.com\'")->fetchColumn();'), 'honeypot stores nothing');
[$code, $body] = http_req('GET', "$BASE/n.php?confirm={$nlTok}");
t_contains('You\'re in', $body, 'confirmation page served');
t_eq('confirmed', sbx_php('require "lib/config.php"; require "lib/newsletter.php";
    echo nl_db()->query("SELECT status FROM subscribers WHERE email = \'reader@example.com\'")->fetchColumn();'), 'subscriber confirmed');
[$code, $body, , $nlHdrs] = http_req('GET', "$BASE/n.php?confirm=" . str_repeat('0', 32));
t_contains('didn', $body, 'bad confirm token rejected politely');
t_assert((bool) array_filter($nlHdrs, fn($h) => stripos($h, 'X-Robots-Tag: noindex') === 0), 'newsletter token pages are noindex');

// sender: first run seeds the ledger without mailing, second run has nothing new
$nlOut = '';
exec('cd ' . escapeshellarg($SANDBOX) . ' && ' . PHP_BINARY . ' tools/newsletter_send.php 2>&1', $nlO1);
$nlOut = implode("\n", $nlO1);
t_contains('baseline: seeded', $nlOut, 'first sender run seeds baseline');
t_contains('nothing mailed', $nlOut, 'baseline mails nothing');
exec('cd ' . escapeshellarg($SANDBOX) . ' && ' . PHP_BINARY . ' tools/newsletter_send.php 2>&1', $nlO2);
t_contains('nothing new to send', implode("\n", $nlO2), 'second sender run idempotent');
// pull one slug out of the ledger — dry-run must plan exactly that essay for the 1-name list
sbx_php('require "lib/config.php"; require "lib/newsletter.php";
    nl_db()->exec("DELETE FROM sends WHERE slug = \'itest-past-post\'");');
exec('cd ' . escapeshellarg($SANDBOX) . ' && ' . PHP_BINARY . ' tools/newsletter_send.php --dry-run 2>&1', $nlO3);
$nlDry = implode("\n", $nlO3);
t_contains('would send "itest-past-post" to 1 subscribers', $nlDry, 'dry-run plans the unsent essay for the confirmed list');
t_contains('1 confirmed', $nlDry, 'sender reports list size');

// per-IP rate limit: a third quick POST (honeypot skipped the limiter) is refused
[$code, $body] = http_req('POST', "$BASE/n.php", [
    'headers' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'body' => 'email=fast%40example.com&website=',
]);
t_contains('One at a time', $body, 'per-IP rate limit kicks in');

// sender resilience: mail "enabled" but failing → slug released for retry
sbx_php('require "lib/config.php"; require "lib/newsletter.php"; $db = nl_db();
    $db->exec("UPDATE subscribers SET status = \'confirmed\' WHERE email = \'reader@example.com\'");');
$nlRetry = sbx_php('require "lib/config.php"; require "lib/helpers.php"; require "lib/newsletter.php";
    $p = ["slug" => "itest-fresh", "title" => "T", "subtitle" => "s", "html" => "<p>x</p>", "publish_ts" => time(), "minutes" => 1];
    echo implode("|", nl_send_new([$p], false));');
t_contains('send failed for "itest-fresh"', $nlRetry, 'unreachable SMTP releases the claim');
t_eq('', sbx_php('require "lib/config.php"; require "lib/newsletter.php";
    echo (string) nl_db()->query("SELECT slug FROM sends WHERE slug = \'itest-fresh\'")->fetchColumn();'), 'failed essay not ledgered — will retry');

// overlap guard: a held lock turns a second sender run into a no-op
$nlLock = sbx_php('require "lib/config.php"; require "lib/helpers.php"; require "lib/newsletter.php";
    $l = fopen(CONTENT_DIR . "/newsletter.lock", "c"); flock($l, LOCK_EX);
    echo implode("|", nl_send_new([], false));');
t_contains('another send is in progress', $nlLock, 'concurrent sender run skipped under lock');

// one-click unsubscribe: browser GET shows the branded page…
[$code, $body] = http_req('GET', "$BASE/n.php?u={$nlTok}");
t_contains('You\'re out', $body, 'one-click unsubscribe page served');
// …and the RFC 8058 POST variant stays a bare 200/OK, idempotent on a gone token
[$code, $body] = http_req('POST', "$BASE/n.php?u={$nlTok}");
t_eq(200, $code, 'RFC 8058 POST unsubscribe answers 200');
t_eq('OK', trim($body), 'RFC 8058 POST body is bare OK');
t_eq('', sbx_php('require "lib/config.php"; require "lib/newsletter.php";
    echo (string) nl_db()->query("SELECT token FROM subscribers WHERE email = \'reader@example.com\'")->fetchColumn();'), 'unsubscribe deletes the row');

/* ---------------- contact form ---------------- */
section('contact form');
[$code, $body] = http_req('POST', "$BASE/", [
    'headers' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'body' => 'contact_submit=1&name=Jane&email=jane%40example.com&subject=Hi&message=Hello+there',
]);
t_eq(200, $code, 'valid contact POST with mail disabled does not 500');
t_contains('Something went wrong', $body, 'friendly error when SMTP is unconfigured');
[$code, , $loc] = http_req('POST', "$BASE/", [
    'headers' => "Content-Type: application/x-www-form-urlencoded\r\n",
    'body' => 'contact_submit=1&website=spam&name=B&email=b%40example.com&subject=s&message=m',
]);
t_eq(302, $code, 'honeypot contact POST fake-succeeds');
t_contains('/?sent=1', (string) $loc, 'honeypot redirected to the PRG landing');
[$code, $body] = http_req('GET', "$BASE/?sent=1");
t_contains('Message sent.', $body, 'PRG landing renders the success card');

/* ---------------- analytics v2 ---------------- */
section('analytics');
$beacon = fn(string $ua, string $payload) => http_req('POST', "$BASE/a.php", [
    'headers' => "User-Agent: {$ua}\r\nContent-Type: text/plain\r\n",
    'body' => $payload,
]);
$chromeUa = 'Mozilla/5.0 (X11; Linux x86_64) TestChrome/126';
[$code] = $beacon($chromeUa, json_encode([
    'p' => '/blog/data-driven-theater/', 'r' => 'https://news.ycombinator.com/item?id=1',
    'q' => '?utm_source=newsletter&utm_medium=email&utm_campaign=july',
    'l' => 'pl-PL', 'w' => 1440, 'tz' => 'Europe/Warsaw',
]));
t_eq(204, $code, 'valid beacon accepted');
$beacon($chromeUa, '{"p":"/","r":""}');
$beacon('Mozilla/5.0 (Macintosh) OtherBrowser/1.0', '{"p":"/","r":""}');
$beacon('Googlebot/2.1 (+http://www.google.com/bot.html)', '{"p":"/bot/","r":""}');
$beacon($chromeUa, 'not-json-at-all');
$beacon($chromeUa, '{"p":"javascript:alert(1)","r":""}');
[$code] = $beacon($chromeUa, '{"e":1,"p":"/blog/data-driven-theater/","d":42}');
t_eq(204, $code, 'engagement beacon accepted');
t_assert(is_file($SANDBOX . '/content/analytics.db'), 'analytics db created in sandbox');

/* owner self-exclusion */
[$code, $body, , $meHdrs] = http_req('GET', "$BASE/a.php?me=off");
t_eq(200, $code, 'exclusion toggle responds');
t_contains('excluded from analytics', $body, 'toggle confirms exclusion');
t_assert((bool) array_filter($meHdrs, fn($h) => stripos($h, 'Set-Cookie: bfx=1') === 0), 'toggle sets the bfx cookie');
[$code] = http_req('POST', "$BASE/a.php", [
    'headers' => "User-Agent: {$chromeUa}\r\nContent-Type: text/plain\r\nCookie: bfx=1\r\n",
    'body' => '{"p":"/owner-visit/","r":""}',
]);
t_eq(204, $code, 'excluded beacon still answers 204');
[, , , $meHdrs] = http_req('GET', "$BASE/a.php?me=on");
t_assert((bool) array_filter($meHdrs, fn($h) => stripos($h, 'Set-Cookie: bfx=;') === 0 || stripos($h, 'Set-Cookie: bfx=deleted') === 0), 'toggle clears the cookie');

$adb = new PDO('sqlite:' . $SANDBOX . '/content/analytics.db');
t_eq('3', (string) $adb->query('SELECT COUNT(*) FROM hits')->fetchColumn(), '3 rows stored (bot + junk + owner dropped)');
t_eq('0', (string) $adb->query("SELECT COUNT(*) FROM hits WHERE path = '/owner-visit/'")->fetchColumn(), 'opted-out browser never lands in the db');
$row = $adb->query("SELECT * FROM hits WHERE path = '/blog/data-driven-theater/'")->fetch(PDO::FETCH_ASSOC);
t_eq('news.ycombinator.com', $row['ref'], 'referrer host stored');
t_eq('news', $row['ref_group'], 'referrer classified as news');
t_eq('Chrome', $row['browser'], 'browser parsed');
t_eq('Linux', $row['os'], 'os parsed');
t_eq('desktop', $row['device'], 'device parsed');
t_eq('pl', $row['lang'], 'language stored as primary subtag');

/* owner exclusion by IP: the cookie only covers one browser, a static address
   covers the whole connection. Proven both ways so a pass cannot come from the
   beacon being broken. */
$sbxConfig = function (array $over) use ($SANDBOX): void {
    $cfg = require $SANDBOX . '/config.php';
    file_put_contents($SANDBOX . '/config.php', '<?php return ' . var_export(array_merge($cfg, $over), true) . ";\n");
};
$hitCount = fn(string $path): int => (int) $adb->query(
    "SELECT COUNT(*) FROM hits WHERE path = " . $adb->quote($path))->fetchColumn();

$sbxConfig(['analytics_exclude_ips' => ['127.0.0.1', '::1']]);
[$code] = $beacon($chromeUa, '{"p":"/from-my-line/","r":""}');
t_eq(204, $code, 'beacon from an excluded address still answers 204');
t_eq(0, $hitCount('/from-my-line/'), 'a hit from an excluded address is never stored');

$sbxConfig(['analytics_exclude_ips' => ['198.51.100.0/24']]);
[$code] = $beacon($chromeUa, '{"p":"/from-someone-else/","r":""}');
t_eq(204, $code, 'beacon from a non-excluded address accepted');
t_eq(1, $hitCount('/from-someone-else/'), 'and it is stored, so the exclusion was what dropped the other one');

// leave the fixture exactly as it was found: later assertions count these rows
$adb->exec("DELETE FROM hits WHERE path = '/from-someone-else/'");
$sbxConfig(['analytics_exclude_ips' => []]);
t_eq('laptop', $row['screen'], 'screen bucketed');
t_eq('Europe/Warsaw', $row['tz'], 'timezone stored');
t_eq('newsletter', $row['utm_source'], 'utm source stored');
t_eq('july', $row['utm_campaign'], 'utm campaign stored');
t_eq('', $row['country'], 'loopback has no country');
t_eq(42, (int) $row['duration'], 'engagement seconds attached to the pageview row');

[$code, , , $probeHdrs] = http_req('GET', "$BASE/stats.php?key=totally-wrong");
t_eq(404, $code, 'stats shim rejects wrong key');
t_assert(!array_filter($probeHdrs, fn($h) => stripos($h, 'Set-Cookie:') === 0), 'anonymous probe gets no session cookie');
[$code, , $loc, $statsHdrs] = http_req('GET', "$BASE/stats.php?key=" . STATS_KEY . "&days=7");
t_eq(302, $code, 'valid key redirects to the unified dashboard');
t_contains('admin.php?days=7', $loc, 'redirect keeps the range filter');
t_assert(!str_contains($loc, 'key='), 'redirect target never carries the key');
$statsCookie = '';
foreach ($statsHdrs as $h) {
    if (stripos($h, 'Set-Cookie: bfadmin=') === 0 && preg_match('/bfadmin=([^;]+)/', $h, $m)) $statsCookie = 'bfadmin=' . $m[1];
}
t_assert($statsCookie !== '', 'valid key mints a stats session');
$asStats = fn(string $method, string $url) => http_req($method, $url, ['headers' => "User-Agent: T/1\r\nCookie: {$statsCookie}\r\n"]);
[$code2, , $loc2] = $asStats('GET', "$BASE/stats.php?days=7");
t_eq(302, $code2, 'keyless shim follow-up still redirects on the stats session');
t_contains('admin.php?days=7', $loc2, 'shim preserves filters without the key');
[$code, $body] = $asStats('GET', "$BASE/admin.php?days=7");
t_eq(200, $code, 'unified dashboard opens on the stats session');
t_not_contains('key=' . STATS_KEY, $body, 'dashboard links never carry the key');
preg_match('#<b>([\d,]+)</b><span>Pageviews#', $body, $m);
t_eq('3', $m[1] ?? '?', 'stats card: pageviews = 3');
preg_match('#<b>([\d,]+)</b><span>Visitors#', $body, $m);
t_eq('2', $m[1] ?? '?', 'stats card: visitors = 2 (per-UA hashes)');
t_contains('news.ycombinator.com', $body, 'referrer on dashboard');
t_contains('Chrome', $body, 'browser table populated');
t_contains('newsletter', $body, 'campaign table populated');
t_contains('42s', $body, 'engaged time rendered');
t_not_contains('/bot/', $body, 'bot path never stored');
t_not_contains('Publishing pipeline', $body, 'stats session sees no admin tabs');
t_not_contains('Field Notes', $body, 'stats session sees no subscriber data');
t_not_contains('Rebuild site', $body, 'stats session sees no admin actions');
[$code, $body] = $asStats('GET', "$BASE/admin.php?days=7&path=" . rawurlencode('/blog/data-driven-theater/'));
t_eq(200, $code, 'path drilldown renders');
preg_match('#<b>([\d,]+)</b><span>Pageviews#', $body, $m);
t_eq('1', $m[1] ?? '?', 'drilldown narrows to one view');

/* ---------------- comments flow ---------------- */
section('comments flow');
// clear per-IP rate-limit residue (markers live in the sandbox's content/.ratelimit)
$rlDir = $SANDBOX . '/content/.ratelimit';
@unlink($rlDir . '/c_rl_' . substr(sha1('127.0.0.1|bfrackowiak-comments'), 0, 16));

$postUrl = "$BASE/blog/nobody-owns-the-sum/";
[, $page] = http_req('GET', $postUrl);
preg_match('/what is (\d) \+ (\d)\?/', $page, $q);
preg_match('/name="c_chal" value="([^"]+)"/', $page, $c);
preg_match('/name="c_ts" value="(\d+)"/', $page, $ts);
t_assert(isset($q[1], $c[1], $ts[1]), 'captcha challenge present on page');
$answer = (int) $q[1] + (int) $q[2];
$form = fn(array $over = []) => http_build_query(array_merge([
    'c_slug' => 'nobody-owns-the-sum',
    'c_ts' => (string) (time() - 10),
    'c_chal' => $c[1],
    'c_answer' => (string) $answer,
    'c_email' => 'tester@example.com',
    'c_text' => 'Integration test comment.',
    'c_website' => '',
    'c_submit' => '1',
], $over));
$postC = fn(string $body) => http_req('POST', "$BASE/comments.php", ['body' => $body]);

[$code, , $loc] = $postC($form(['c_answer' => '99']));
t_contains('cerr=captcha', $loc, 'wrong captcha bounces');
[, , $loc] = $postC($form(['c_email' => 'not-an-email']));
t_contains('cerr=email', $loc, 'invalid email bounces');
[, , $loc] = $postC($form(['c_website' => 'http://spam.example']));
t_assert(str_contains($loc, '#comments') && !str_contains($loc, 'cerr'), 'honeypot pretends success');
[, , $loc] = $postC($form(['c_ts' => (string) time()]));
t_assert(!str_contains($loc, 'cerr'), 'too-fast form silently dropped');
[, , $loc] = $postC($form(['c_slug' => 'tag']));
t_eq('/blog/', $loc, 'invalid slug redirected away');
[, , $loc] = $postC($form(['c_slug' => 'itest-future-post']));
t_eq('/blog/', $loc, 'scheduled post refuses comments');
[, , $loc] = $postC($form(['c_slug' => 'itest-draft-post']));
t_eq('/blog/', $loc, 'draft post refuses comments');
$cfile = $SANDBOX . '/content/comments/nobody-owns-the-sum.json';
t_assert(!is_file($cfile), 'nothing stored by any rejected attempt');

[$code, , $loc] = $postC($form());
t_eq(302, $code, 'valid comment redirects');
t_contains('#comments', $loc, 'redirect lands on thread');
t_assert(is_file($cfile), 'comment stored');
$stored = json_decode((string) file_get_contents($cfile), true);
t_eq(1, count($stored), 'exactly one comment stored');
t_eq('tester@example.com', $stored[0]['email'] ?? '', 'email stored in full');
[, $page2] = http_req('GET', $postUrl);
t_contains('t***@example.com', $page2, 'email rendered masked');
t_contains('Comments (1)', $page2, 'thread count updated');
t_contains('Integration test comment.', $page2, 'comment text rendered');

[, , $loc] = $postC($form(['c_text' => 'second, too fast']));
t_contains('cerr=slow', $loc, 'rate limit blocks immediate second post');
t_assert(is_file($rlDir . '/c_rl_' . substr(sha1('127.0.0.1|bfrackowiak-comments'), 0, 16)), 'rate-limit marker lives in content/.ratelimit, not shared /tmp');
@unlink($rlDir . '/c_rl_' . substr(sha1('127.0.0.1|bfrackowiak-comments'), 0, 16));

/* ---------------- admin flow ---------------- */
section('admin flow');
@unlink($rlDir . '/adm_rl_' . substr(sha1('127.0.0.1|bfrackowiak-admin'), 0, 16));

[$code, $body] = http_req('GET', "$BASE/admin.php");
t_eq(200, $code, 'admin login page 200');
t_contains('Sign in', $body, 'login form shown when logged out');
t_not_contains('Control room', $body, 'dashboard hidden when logged out');

[$code, , $loc] = http_req('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'login', 'password' => 'wrong'])]);
t_eq(302, $code, 'wrong password redirects');
t_contains('err=login', $loc, 'wrong password flagged');

[$code, , $loc, $hdrs] = http_req('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'login', 'password' => ADMIN_PW])]);
t_eq(302, $code, 'correct password redirects');
t_assert(!str_contains($loc, 'err'), 'no error on correct password');
t_assert((bool) array_filter($hdrs, fn($h) => stripos($h, 'Set-Cookie: bfx=1') === 0), 'login excludes the owner browser from stats');
$cookie = '';
foreach ($hdrs as $h) {
    if (stripos($h, 'Set-Cookie: bfadmin=') === 0 && preg_match('/bfadmin=([^;]+)/', $h, $m)) $cookie = 'bfadmin=' . $m[1];
}
t_assert($cookie !== '', 'session cookie issued');
$asAdmin = fn(string $method, string $url, array $opt = []) => http_req($method, $url, array_merge($opt, [
    'headers' => ($opt['headers'] ?? "User-Agent: TestAdmin/1\r\n") . "Cookie: {$cookie}\r\n",
]));

[$code, $body] = $asAdmin('GET', "$BASE/admin.php");
t_eq(200, $code, 'dashboard 200 with session');
t_contains('Control room', $body, 'dashboard rendered');
t_contains('ITest Future Post', $body, 'scheduled post visible in pipeline');
t_contains('b-scheduled', $body, 'scheduled badge shown');
t_contains('ITest Draft Post', $body, 'draft post visible in pipeline');
t_contains('tester@example.com', $body, 'comment listed for moderation (full email for admin)');
t_contains('Acquisition', $body, 'analytics tabs folded into the admin page');
t_contains('Recent visits', $body, 'activity pane rendered inline');
t_contains('Rebuild site', $body, 'admin actions present for the full session');

// editorial scores pane (docs/SCORING-RUBRIC.md): the fixtures are unscored on
// purpose, so this also covers the unscored path end to end
t_contains('Editorial scores', $body, 'scores pane rendered');
t_contains('rubric ' . SC_RUBRIC, $body, 'rubric version shown so mixed-version scores are visible');
t_contains('Avg reach', $body, 'reach summary card present');
t_contains('carry no scores', $body, 'unscored fixtures are flagged, not silently skipped');
t_contains('Which axis actually gets read?', $body, 'score-vs-traffic correlation section present');
foreach (SC_AXES as $axKey => $ax) {
    t_contains('sort=' . $axKey, $body, "scores table sortable by {$axKey}");
}
[, $bodySorted] = $asAdmin('GET', "$BASE/admin.php?sort=pv");
t_eq(200, $code, 'scores table accepts a sort parameter');
t_contains('Editorial scores', $bodySorted, 'sorted view still renders');
[, $bodyBadSort] = $asAdmin('GET', "$BASE/admin.php?sort=" . rawurlencode("'; DROP TABLE hits;--"));
t_contains('Editorial scores', $bodyBadSort, 'unknown sort key falls back instead of breaking the page');

/* every number in the pane must be explainable: the popup is fed by one JSON
   payload, and a score with no reasoning behind it is the failure mode */
preg_match('~<script type="application/json" id="sc-data">(.*?)</script>~s', $body, $scM);
$scData = json_decode($scM[1] ?? '', true);
t_assert(is_array($scData), 'scores pane ships a reasoning payload');
t_eq(count(SC_AXES), count($scData['axes'] ?? []), 'payload documents every axis');
t_eq([1, 3, 5, 7, 9], array_keys($scData['axes']['tension']['anchors'] ?? []), 'payload carries the rubric anchors');
t_assert(isset($scData['bands']['floor']['do']), 'payload carries what to do about each band');
$scRow = $scData['rows']['itest-scored-post'] ?? null;
t_assert($scRow !== null, 'the scored fixture is in the payload');
t_eq(8, $scRow['scores']['tension'] ?? 0, 'payload carries the post\'s own scores');
t_contains('itest rationale quote', (string) ($scRow['notes']['tension'] ?? ''), 'payload carries the stored rationale');
t_eq(1.6, round((float) ($scRow['contrib']['tension']['points'] ?? 0), 2), 'payload carries the arithmetic behind the reach score');
t_assert(($scRow['lever']['axis'] ?? '') !== '', 'payload names the axis worth fixing first');
t_contains('data-slug="itest-scored-post" data-axis="tension"', $body, 'each score is a button that opens its reasoning');
t_contains('The rubric in machine-readable form', file_get_contents($ROOT . '/lib/scores.php'), 'rubric text lives next to the weights');

// filters answer "what needs a decision"; each keeps the sort and the range
foreach (['floor', 'underread', 'unscored'] as $scope) {
    [$scCode, $scBody] = $asAdmin('GET', "$BASE/admin.php?scope={$scope}&sort=reach&dir=desc");
    t_eq(200, $scCode, "scores scope={$scope} renders");
    t_contains('Editorial scores', $scBody, "scores scope={$scope} keeps the pane");
}
// the pipeline pane lists every post too, so the filter must be judged on the
// scores pane alone
$scorePane = function (string $html): string {
    preg_match('~<div class="pane wide scores-t" id="scores">(.*?)<!-- /scores -->~s', $html, $m);
    return $m[1] ?? '';
};
[, $scUnscored] = $asAdmin('GET', "$BASE/admin.php?scope=unscored");
t_not_contains('>ITest Scored Post<', $scorePane($scUnscored), 'the unscored filter really excludes the scored post');
t_contains('>ITest Past Post<', $scorePane($scUnscored), 'and keeps the posts that carry no scores');
[, $scAll] = $asAdmin('GET', "$BASE/admin.php");
t_contains('>ITest Scored Post<', $scorePane($scAll), 'the default view shows everything');
[, $scBad] = $asAdmin('GET', "$BASE/admin.php?scope=" . rawurlencode('../../etc/passwd'));
t_contains('Editorial scores', $scBad, 'unknown filter falls back instead of breaking the page');
[, $scAsc] = $asAdmin('GET', "$BASE/admin.php?sort=reach&dir=asc");
t_contains('dir=desc', $scAsc, 'the active column offers the opposite direction');
[, $scLegacy] = $asAdmin('GET', "$BASE/admin.php?sort=reach_desc");
t_contains('Editorial scores', $scLegacy, 'the old reach_desc deep link still works');

preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
$csrf = $m[1] ?? '';
t_assert($csrf !== '', 'csrf token in dashboard forms');
preg_match('/name="key" value="([0-9a-f]{12})"/', $body, $m);
$commentKey = $m[1] ?? '';
t_assert($commentKey !== '', 'comment delete form carries content fingerprint');

// a renamed post whose old filename survived a deploy must be visible as a
// problem in admin, not silently listed twice
// the title legitimately appears more than once per page (pipeline and scores),
// so calibrate against the clean page rather than assuming a number
[, $bodyClean] = $asAdmin('GET', "$BASE/admin.php");
$rowsClean = substr_count($bodyClean, '>ITest Past Post<');
t_assert($rowsClean > 0, 'baseline: the post is listed on a clean page');

$dupFile = $SANDBOX . '/content/posts/2099-06-06-itest-past-post.md';
copy($SANDBOX . '/content/posts/2020-01-01-itest-past-post.md', $dupFile);
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Duplicate source files', $body, 'admin warns about a duplicate source file');
t_contains('2099-06-06-itest-past-post.md', $body, 'the warning names the stale filename');
t_eq($rowsClean, substr_count($body, '>ITest Past Post<'), 'the leftover adds a warning, not an extra row');
unlink($dupFile);
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_not_contains('Duplicate source files', $body, 'warning clears once the leftover is gone');

/* ---------------- draft preview ---------------- */
// unpublished posts have no page on disk; preview.php renders one on demand,
// for the admin only, and must not confirm anything to anyone else
foreach (['itest-draft-post', 'itest-future-post', 'itest-past-post', 'no-such-post-at-all'] as $s) {
    [$code] = http_req('GET', "$BASE/preview.php?slug={$s}");
    t_eq(404, $code, "preview 404s without a session ({$s})");
    [$code] = $asStats('GET', "$BASE/preview.php?slug={$s}");
    t_eq(404, $code, "preview 404s for a stats-only session ({$s})");
}
[$code, $body, , $hdrs] = $asAdmin('GET', "$BASE/preview.php?slug=itest-draft-post");
t_eq(200, $code, 'admin previews a draft');
t_contains('ITest Draft Post', $body, 'draft title rendered');
t_contains('Integration fixture subtitle.', $body, 'draft body rendered');
t_contains('pvbar', $body, 'preview banner present');
t_contains('draft', $body, 'banner names the status');
t_not_contains('<?php', $body, 'the generated comment island is never emitted as source');
t_assert((bool) array_filter($hdrs, fn($h) => stripos($h, 'X-Robots-Tag:') === 0 && stripos($h, 'noindex') !== false),
    'preview sends noindex');
t_assert((bool) array_filter($hdrs, fn($h) => stripos($h, 'Cache-Control:') === 0 && stripos($h, 'no-store') !== false),
    'preview sends no-store');

[$code, $body] = $asAdmin('GET', "$BASE/preview.php?slug=itest-future-post");
t_eq(200, $code, 'admin previews a scheduled post');
t_contains('goes live', $body, 'banner shows the publish moment for a scheduled post');
[$code, $body] = $asAdmin('GET', "$BASE/preview.php?slug=itest-past-post");
t_eq(200, $code, 'admin previews a published post too');
t_contains('published', $body, 'banner marks it as already published');

foreach (['../../config', 'Bad_Slug', '', str_repeat('a', 90)] as $bad) {
    [$code] = $asAdmin('GET', "$BASE/preview.php?slug=" . rawurlencode($bad));
    t_eq(404, $code, "preview rejects a malformed slug (" . substr($bad, 0, 14) . ')');
}
[$code] = $asAdmin('GET', "$BASE/preview.php?slug=nothing-here-either");
t_eq(404, $code, 'preview 404s on a well-formed slug with no source file');

// the pipeline has to actually offer the link, which is the whole point
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('preview.php?slug=itest-draft-post', $body, 'pipeline links the draft to its preview');
t_contains('preview.php?slug=itest-future-post', $body, 'pipeline links the scheduled post to its preview');

[$code, , $loc] = $asAdmin('GET', "$BASE/stats.php");
t_eq(302, $code, 'old stats URL redirects the admin session');
t_contains('admin.php', $loc, 'shim points at the unified dashboard');

[$code] = http_req('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'rebuild', 'csrf' => 'x'])]);
t_eq(403, $code, 'rebuild without session forbidden');
[$code] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'rebuild', 'csrf' => 'forged'])]);
t_eq(403, $code, 'rebuild with bad csrf forbidden');

[$code, , $loc] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'rebuild', 'csrf' => $csrf])]);
t_eq(302, $code, 'rebuild accepted');
[$code, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Site rebuilt.', $body, 'rebuild flash with build output');
t_contains('scheduled: itest-future-post', $body, 'rebuild output lists the queue');
t_assert(is_dir($SANDBOX . '/blog/itest-past-post'), 'rebuild kept published pages');
t_assert(!is_dir($SANDBOX . '/blog/itest-future-post'), 'rebuild still excludes scheduled post');

/* status cycle from the dashboard: draft → publish now → reschedule → draft */
$setStatus = fn(string $slug, string $act, string $when = '') => $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(
    ['action' => 'setstatus', 'csrf' => $csrf, 'slug' => $slug, 'status_action' => $act] + ($when !== '' ? ['when' => $when] : [])
)]);
$draftFile = $SANDBOX . '/content/posts/2024-01-01-itest-draft-post.md';

[$code] = $setStatus('itest-draft-post', 'publish');
t_eq(302, $code, 'publish-now accepted');
t_assert(is_dir($SANDBOX . '/blog/itest-draft-post'), 'publish-now built the page (auto rebuild)');
t_contains('ITest Draft Post', (string) file_get_contents($SANDBOX . '/blog/index.php'), 'publish-now listed the post');
t_contains('**Publish:**', (string) file_get_contents($draftFile), 'publish moment written into the source');
t_not_contains('**Status:** draft', (string) file_get_contents($draftFile), 'draft flag removed from the source');
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('itest-draft-post → published', $body, 'status flash shown');
t_contains('Site rebuilt', $body, 'auto-rebuild reported');

[$code] = $setStatus('itest-draft-post', 'schedule', '2099-05-01T12:00');
t_eq(302, $code, 'reschedule accepted');
t_assert(!is_dir($SANDBOX . '/blog/itest-draft-post'), 'rescheduled post unbuilt again');
t_contains('**Publish:** 2099-05-01 12:00', (string) file_get_contents($draftFile), 'schedule moment written');

[$code] = $setStatus('itest-draft-post', 'draft');
t_eq(302, $code, 'back-to-draft accepted');
t_contains('**Status:** draft', (string) file_get_contents($draftFile), 'draft flag restored');
t_not_contains('ITest Draft Post', (string) file_get_contents($SANDBOX . '/blog/index.php'), 'draft absent from index again');

[$code] = $setStatus('no-such-post', 'publish');
t_eq(302, $code, 'unknown slug redirects');
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('No source file', $body, 'unknown slug reported, nothing broken');

[$code, , $loc] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query([
    'action' => 'delcomment', 'csrf' => $csrf, 'slug' => 'nobody-owns-the-sum', 'key' => $commentKey,
])]);
t_eq(302, $code, 'comment delete accepted');
$storedAfter = json_decode((string) file_get_contents($cfile), true);
t_eq(0, count($storedAfter), 'comment removed from disk');
[, $page3] = http_req('GET', $postUrl);
t_not_contains('Integration test comment.', $page3, 'deleted comment gone from the page');

/* newsletter tab: pane data + subscriber moderation */
$subTok = sbx_php('require "lib/config.php"; require "lib/newsletter.php"; echo nl_subscribe(nl_db(), "moderate@example.com");');
t_assert(strlen($subTok) === 32, 'fixture subscriber planted');
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Newsletter — Field Notes', $body, 'newsletter pane rendered');
t_contains('moderate@example.com', $body, 'subscriber listed with full address');
t_contains('badge b-pending', $body, 'pending badge on the unconfirmed row');
t_contains('SMTP is not configured', $body, 'mail-off warning shown when smtp pass is empty');
t_contains('value="' . $subTok . '"', $body, 'moderation forms carry the subscriber token');

[$code] = http_req('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'nlconfirm', 'csrf' => 'x', 'token' => $subTok])]);
t_eq(403, $code, 'subscriber moderation without session forbidden');
[$code, , $loc] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'nlconfirm', 'csrf' => $csrf, 'token' => $subTok])]);
t_eq(302, $code, 'manual confirm accepted');
t_contains('#newsletter', $loc, 'confirm lands back on the newsletter tab');
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Subscriber confirmed.', $body, 'confirm flash shown');
t_contains('badge b-confirmed', $body, 'subscriber shows as confirmed');

[$code] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'nldelete', 'csrf' => $csrf, 'token' => $subTok])]);
t_eq(302, $code, 'subscriber delete accepted');
[, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Subscriber removed.', $body, 'delete flash shown');
t_not_contains('moderate@example.com', $body, 'subscriber row gone — GDPR erasure is immediate');

[$code, , $loc] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'logout', 'csrf' => $csrf])]);
t_eq(302, $code, 'logout redirects');
[$code, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Sign in', $body, 'session gone after logout');
@unlink($rlDir . '/adm_rl_' . substr(sha1('127.0.0.1|bfrackowiak-admin'), 0, 16));

/* dashboards send private-page headers */
[, , , $h1] = http_req('GET', "$BASE/admin.php");
t_assert((bool) array_filter($h1, fn($h) => stripos($h, 'X-Frame-Options: DENY') === 0), 'admin sends X-Frame-Options');
t_assert((bool) array_filter($h1, fn($h) => stripos($h, 'Cache-Control: no-store') === 0), 'admin sends no-store');

if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'integration.php') exit(t_summary());
