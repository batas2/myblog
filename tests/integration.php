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
   . '--exclude=content/analytics.seed --exclude=content/geoip.dat --exclude=blog/ '
   . escapeshellarg($ROOT . '/') . ' ' . escapeshellarg($SANDBOX . '/'), $o, $rc);
t_eq(0, $rc, 'sandbox copied');
mkdir($SANDBOX . '/content/comments', 0755, true);

// deterministic config: known keys, admin enabled, everything else prod-like
file_put_contents($SANDBOX . '/config.php', '<?php return ' . var_export([
    'site_url'            => 'https://bfrackowiak.pl',
    'site_name'           => 'bfrackowiak.pl',
    'author'              => 'Bartosz Frąckowiak',
    'og_image'            => 'https://bfrackowiak.pl/mysefl.jpeg',
    'newsletter_action'   => 'https://buttondown.com/api/emails/embed-subscribe/bfrackowiak',
    'comments_secret'     => 'itest-comments-secret',
    'stats_key'           => STATS_KEY,
    'goatcounter_url'     => '',
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

$postDirs = array_filter(glob($SANDBOX . '/blog/*'), fn($d) => is_dir($d) && basename($d) !== 'tag');
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
t_eq(2 + $pubN + $tagN, count($sm->url), 'sitemap url count = home + blog + posts + tags');
t_eq($pubN, substr_count((string) file_get_contents($SANDBOX . '/llms-full.txt'), "\n# "), 'llms-full contains every published post');

$post = (string) file_get_contents($SANDBOX . '/blog/mathematicians-vs-lawyers/index.php');
t_contains('rel="canonical" href="https://bfrackowiak.pl/blog/mathematicians-vs-lawyers/"', $post, 'canonical set');
t_contains('property="og:type" content="article"', $post, 'og:type article');
t_contains('article:published_time" content="2026-07-13T09:00:00+02:00"', $post, 'published_time in site timezone');
preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $post, $ld);
$ldOk = count($ld[1]) >= 2;
foreach ($ld[1] as $j) if (json_decode($j) === null) $ldOk = false;
t_assert($ldOk, 'post JSON-LD blocks parse');
t_contains('COMMENT_SLUG', $post, 'comments include wired');
t_contains('newsletter', $post, 'newsletter box on post');
t_contains('sendBeacon', $post, 'analytics beacon on post');
t_contains('pagehide', $post, 'engagement beacon on post');
$legacy = (string) file_get_contents($SANDBOX . '/blog/stop-calling-it-legacy-code/index.php');
t_contains('Also published on Medium', $legacy, 'syndication badge on medium post');
t_contains('rel="syndication"', $legacy, 'syndication head link');
t_contains('class="featured2', $index, 'featured split card present');
t_eq($pubN - 1, substr_count($index, 'class="pcard reveal"'), 'grid cards = published minus featured');
t_not_contains('On Medium</h2>', $index, 'medium strip removed');

/* ---------------- HTTP layer ---------------- */
section('http server');
$serverPid = (int) exec(
    'cd ' . escapeshellarg($SANDBOX) . ' && nohup ' . PHP_BINARY . ' -S 127.0.0.1:' . $PORT
    . ' >/dev/null 2>&1 & echo $!'
);
$ready = false;
for ($i = 0; $i < 40; $i++) {
    usleep(150000);
    [$code] = http_req('GET', "$BASE/");
    if ($code === 200) { $ready = true; break; }
}
t_assert($ready, "php -S serving on :{$PORT}");

[$code, $body] = http_req('GET', "$BASE/");
t_eq(200, $code, 'homepage 200');
t_contains('Get the next field note', $body, 'newsletter on homepage');
t_contains('pagehide', $body, 'engagement beacon on homepage');
[$code, $body] = http_req('GET', "$BASE/blog/");
t_eq(200, $code, 'blog index 200');
[$code, $body] = http_req('GET', "$BASE/blog/tag/software-architecture/");
t_eq(200, $code, 'tag page 200');
[$code, $body] = http_req('GET', "$BASE/blog/data-driven-theater/index.md");
t_eq(200, $code, 'markdown mirror served');
[$code] = http_req('GET', "$BASE/feed.xml");
t_eq(200, $code, 'feed served');

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

$adb = new PDO('sqlite:' . $SANDBOX . '/content/analytics.db');
t_eq('3', (string) $adb->query('SELECT COUNT(*) FROM hits')->fetchColumn(), '3 rows stored (bot + junk dropped)');
$row = $adb->query("SELECT * FROM hits WHERE path = '/blog/data-driven-theater/'")->fetch(PDO::FETCH_ASSOC);
t_eq('news.ycombinator.com', $row['ref'], 'referrer host stored');
t_eq('news', $row['ref_group'], 'referrer classified as news');
t_eq('Chrome', $row['browser'], 'browser parsed');
t_eq('Linux', $row['os'], 'os parsed');
t_eq('desktop', $row['device'], 'device parsed');
t_eq('pl', $row['lang'], 'language stored as primary subtag');
t_eq('laptop', $row['screen'], 'screen bucketed');
t_eq('Europe/Warsaw', $row['tz'], 'timezone stored');
t_eq('newsletter', $row['utm_source'], 'utm source stored');
t_eq('july', $row['utm_campaign'], 'utm campaign stored');
t_eq('', $row['country'], 'loopback has no country');
t_eq(42, (int) $row['duration'], 'engagement seconds attached to the pageview row');

[$code, , , $probeHdrs] = http_req('GET', "$BASE/stats.php?key=totally-wrong");
t_eq(404, $code, 'stats rejects wrong key');
t_assert(!array_filter($probeHdrs, fn($h) => stripos($h, 'Set-Cookie:') === 0), 'anonymous probe gets no session cookie');
[$code, $body, , $statsHdrs] = http_req('GET', "$BASE/stats.php?key=" . STATS_KEY . "&days=7");
t_eq(200, $code, 'stats opens with key');
t_not_contains('key=' . STATS_KEY, $body, 'dashboard links never carry the key');
$statsCookie = '';
foreach ($statsHdrs as $h) {
    if (stripos($h, 'Set-Cookie: bfadmin=') === 0 && preg_match('/bfadmin=([^;]+)/', $h, $m)) $statsCookie = 'bfadmin=' . $m[1];
}
t_assert($statsCookie !== '', 'valid key mints a stats session');
[$code2] = http_req('GET', "$BASE/stats.php?days=7", ['headers' => "User-Agent: T/1\r\nCookie: {$statsCookie}\r\n"]);
t_eq(200, $code2, 'keyless follow-up works on the stats session');
preg_match('#<b>([\d,]+)</b><span>Pageviews#', $body, $m);
t_eq('3', $m[1] ?? '?', 'stats card: pageviews = 3');
preg_match('#<b>([\d,]+)</b><span>Visitors#', $body, $m);
t_eq('2', $m[1] ?? '?', 'stats card: visitors = 2 (per-UA hashes)');
t_contains('news.ycombinator.com', $body, 'referrer on dashboard');
t_contains('Chrome', $body, 'browser table populated');
t_contains('newsletter', $body, 'campaign table populated');
t_contains('42s', $body, 'engaged time rendered');
t_not_contains('/bot/', $body, 'bot path never stored');
[$code, $body] = http_req('GET', "$BASE/stats.php?key=" . STATS_KEY . '&days=7&path=' . rawurlencode('/blog/data-driven-theater/'));
t_eq(200, $code, 'path drilldown renders');
preg_match('#<b>([\d,]+)</b><span>Pageviews#', $body, $m);
t_eq('1', $m[1] ?? '?', 'drilldown narrows to one view');

/* ---------------- comments flow ---------------- */
section('comments flow');
// clear the per-IP rate-limit residue from earlier runs (same derivation as comments.php)
@unlink(sys_get_temp_dir() . '/c_rl_' . substr(sha1('127.0.0.1|bfrackowiak-comments'), 0, 16));

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
@unlink(sys_get_temp_dir() . '/c_rl_' . substr(sha1('127.0.0.1|bfrackowiak-comments'), 0, 16));

/* ---------------- admin flow ---------------- */
section('admin flow');
@unlink(sys_get_temp_dir() . '/adm_rl_' . substr(sha1('127.0.0.1|bfrackowiak-admin'), 0, 16));

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
preg_match('/name="csrf" value="([^"]+)"/', $body, $m);
$csrf = $m[1] ?? '';
t_assert($csrf !== '', 'csrf token in dashboard forms');
preg_match('/name="key" value="([0-9a-f]{12})"/', $body, $m);
$commentKey = $m[1] ?? '';
t_assert($commentKey !== '', 'comment delete form carries content fingerprint');

[$code, $body] = $asAdmin('GET', "$BASE/stats.php");
t_eq(200, $code, 'stats open via admin session, no key');

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

[$code, , $loc] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query([
    'action' => 'delcomment', 'csrf' => $csrf, 'slug' => 'nobody-owns-the-sum', 'key' => $commentKey,
])]);
t_eq(302, $code, 'comment delete accepted');
$storedAfter = json_decode((string) file_get_contents($cfile), true);
t_eq(0, count($storedAfter), 'comment removed from disk');
[, $page3] = http_req('GET', $postUrl);
t_not_contains('Integration test comment.', $page3, 'deleted comment gone from the page');

[$code, , $loc] = $asAdmin('POST', "$BASE/admin.php", ['body' => http_build_query(['action' => 'logout', 'csrf' => $csrf])]);
t_eq(302, $code, 'logout redirects');
[$code, $body] = $asAdmin('GET', "$BASE/admin.php");
t_contains('Sign in', $body, 'session gone after logout');
@unlink(sys_get_temp_dir() . '/adm_rl_' . substr(sha1('127.0.0.1|bfrackowiak-admin'), 0, 16));

if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'integration.php') exit(t_summary());
