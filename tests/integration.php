<?php
/**
 * tests/integration.php — end-to-end suite against a sandboxed copy of the site.
 *
 * 1. Copies the repo into a temp dir (without runtime data, backups, docs).
 * 2. Runs the real build there and asserts on the generated artifacts.
 * 3. Serves the sandbox with `php -S` and drives every endpoint over HTTP:
 *    pages, analytics beacon + stats auth, and the full comment/captcha flow.
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

$cleanup = function () use (&$serverPid, $SANDBOX) {
    if ($serverPid) { exec('kill ' . (int) $serverPid . ' 2>/dev/null'); $serverPid = null; }
    if (is_dir($SANDBOX)) exec('rm -rf ' . escapeshellarg($SANDBOX));
};
register_shutdown_function($cleanup);

/* ---------------- sandbox + build ---------------- */
section('sandbox build');
exec('rsync -a --exclude=_remote-backup/ --exclude=_archive/ --exclude=docs/ --exclude=tests/ '
   . '--exclude=.claude/ --exclude=.git/ --exclude=content/comments/ --exclude=content/analytics.db '
   . '--exclude=content/analytics.seed --exclude=blog/ '
   . escapeshellarg($ROOT . '/') . ' ' . escapeshellarg($SANDBOX . '/'), $o, $rc);
t_eq(0, $rc, 'sandbox copied');
mkdir($SANDBOX . '/content/comments', 0755, true);

exec('php ' . escapeshellarg($SANDBOX . '/tools/build.php') . ' 2>&1', $out, $rc);
t_eq(0, $rc, 'build exits 0 (' . implode(' ', $out) . ')');

$postDirs = array_filter(glob($SANDBOX . '/blog/*'), fn($d) => is_dir($d) && basename($d) !== 'tag');
t_eq(23, count($postDirs), '23 post directories generated');
$mdOk = $phpOk = true;
foreach ($postDirs as $d) {
    if (!is_file("$d/index.php")) $phpOk = false;
    if (!is_file("$d/index.md")) $mdOk = false;
}
t_assert($phpOk, 'every post has index.php');
t_assert($mdOk, 'every post has markdown mirror');

define('BUILD_NO_RUN', true);
require $ROOT . '/tools/build.php';
$expectedTags = count(collect_tags(load_posts()));
$tagDirs = glob($SANDBOX . '/blog/tag/*', GLOB_ONLYDIR);
t_eq($expectedTags, count($tagDirs), "tag pages match unique tags ({$expectedTags})");

t_assert(simplexml_load_file($SANDBOX . '/feed.xml') !== false, 'generated feed.xml valid');
$sm = simplexml_load_file($SANDBOX . '/sitemap.xml');
t_assert($sm !== false, 'generated sitemap.xml valid');
t_eq(2 + 23 + $expectedTags, count($sm->url), 'sitemap url count = home + blog + posts + tags');
t_eq(23, substr_count((string) file_get_contents($SANDBOX . '/llms-full.txt'), "\n# "), 'llms-full contains all posts');

/* ---------------- generated page contracts ---------------- */
section('generated page contracts');
$post = (string) file_get_contents($SANDBOX . '/blog/mathematicians-vs-lawyers/index.php');
t_contains('rel="canonical" href="https://bfrackowiak.pl/blog/mathematicians-vs-lawyers/"', $post, 'canonical set');
t_contains('property="og:type" content="article"', $post, 'og:type article');
preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $post, $ld);
$ldOk = count($ld[1]) >= 2;
foreach ($ld[1] as $j) if (json_decode($j) === null) $ldOk = false;
t_assert($ldOk, 'post JSON-LD blocks parse');
t_contains('COMMENT_SLUG', $post, 'comments include wired');
t_contains('newsletter', $post, 'newsletter box on post');
t_contains('sendBeacon', $post, 'analytics beacon on post');
$legacy = (string) file_get_contents($SANDBOX . '/blog/stop-calling-it-legacy-code/index.php');
t_contains('Also published on Medium', $legacy, 'syndication badge on medium post');
t_contains('rel="syndication"', $legacy, 'syndication head link');
$index = (string) file_get_contents($SANDBOX . '/blog/index.php');
t_contains('class="featured2', $index, 'featured split card present');
t_eq(22, substr_count($index, 'class="pcard reveal"'), '22 grid cards');
t_not_contains('On Medium</h2>', $index, 'medium strip removed');

/* ---------------- HTTP layer ---------------- */
section('http server');
$serverPid = (int) exec(
    'cd ' . escapeshellarg($SANDBOX) . ' && nohup php -S 127.0.0.1:' . $PORT
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
[$code, $body] = http_req('GET', "$BASE/blog/");
t_eq(200, $code, 'blog index 200');
[$code, $body] = http_req('GET', "$BASE/blog/tag/software-architecture/");
t_eq(200, $code, 'tag page 200');
[$code, $body] = http_req('GET', "$BASE/blog/data-driven-theater/index.md");
t_eq(200, $code, 'markdown mirror served');
[$code] = http_req('GET', "$BASE/feed.xml");
t_eq(200, $code, 'feed served');

/* ---------------- analytics ---------------- */
section('analytics');
$beacon = fn(string $ua, string $payload) => http_req('POST', "$BASE/a.php", [
    'headers' => "User-Agent: {$ua}\r\nContent-Type: text/plain\r\n",
    'body' => $payload,
]);
[$code] = $beacon('Mozilla/5.0 (X11) TestChrome/126', '{"p":"/blog/data-driven-theater/","r":"https://news.ycombinator.com/"}');
t_eq(204, $code, 'valid beacon accepted');
$beacon('Mozilla/5.0 (X11) TestChrome/126', '{"p":"/","r":""}');
$beacon('Mozilla/5.0 (Macintosh) OtherBrowser/1.0', '{"p":"/","r":""}');
$beacon('Googlebot/2.1 (+http://www.google.com/bot.html)', '{"p":"/bot/","r":""}');
$beacon('Mozilla/5.0 (X11) TestChrome/126', 'not-json-at-all');
$beacon('Mozilla/5.0 (X11) TestChrome/126', '{"p":"javascript:alert(1)","r":""}');
t_assert(is_file($SANDBOX . '/content/analytics.db'), 'analytics db created in sandbox');

$statsKey = '';
if (preg_match("/'stats_key'\s*=>\s*'([^']+)'/", (string) file_get_contents($SANDBOX . '/config.php'), $m)) $statsKey = $m[1];
t_assert($statsKey !== '', 'stats key readable');
[$code] = http_req('GET', "$BASE/stats.php?key=totally-wrong");
t_eq(404, $code, 'stats rejects wrong key');
[$code, $body] = http_req('GET', "$BASE/stats.php?key={$statsKey}&days=7");
t_eq(200, $code, 'stats opens with key');
preg_match_all('#<b>([\d,]+)</b>#', $body, $nums);
t_eq('3', $nums[1][0] ?? '?', 'pageviews = 3 (bot + junk dropped)');
t_eq('2', $nums[1][1] ?? '?', 'uniques = 2 (per-UA visitors)');
t_contains('news.ycombinator.com', $body, 'referrer recorded');
t_not_contains('/bot/', $body, 'bot path never stored');

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

if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'integration.php') exit(t_summary());
