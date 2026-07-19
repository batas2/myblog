<?php
/**
 * tests/unit.php — unit tests for the build pipeline, content model,
 * analytics core, geoip, admin core, and comments.php functions.
 * Run via tests/run.php (or standalone: php tests/unit.php).
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

define('BUILD_NO_RUN', true);
require dirname(__DIR__) . '/tools/build.php';
require_once dirname(__DIR__) . '/lib/analytics.php';
require_once dirname(__DIR__) . '/lib/admin.php';
require dirname(__DIR__) . '/comments.php';

/* ---------------- markdown: inline ---------------- */
section('md_inline');
t_eq('<strong>bold</strong>', md_inline('**bold**'), 'bold renders');
t_eq('<em>it</em>', md_inline('*it*'), 'italic renders');
t_eq('<a href="/blog/x/">link</a>', md_inline('[link](/blog/x/)'), 'link renders');
t_eq('<code>a **b** c</code>', md_inline('`a **b** c`'), 'inline code shields markdown inside');
t_contains('&lt;script&gt;', md_inline('<script>alert(1)</script>'), 'html is escaped');
t_eq('a <strong>b</strong> <em>c</em>', md_inline('a **b** *c*'), 'bold before italic, no bleed');
t_contains('&quot;quoted&quot;', md_inline('"quoted"'), 'quotes escaped');

/* ---------------- markdown: blocks ---------------- */
section('md_to_html');
$html = md_to_html(['First para.', '', 'Second para.']);
t_eq(2, substr_count($html, '<p>'), 'blank line splits paragraphs');
$html = md_to_html(['## Heading']);
t_contains('<h2>Heading</h2>', $html, 'h2 renders');
$html = md_to_html(['- one', '- two']);
t_eq(1, substr_count($html, '<ul>'), 'single ul for adjacent items');
t_eq(2, substr_count($html, '<li>'), 'two list items');
$html = md_to_html(['1. one', '2. two']);
t_contains('<ol>', $html, 'ordered list renders');
$html = md_to_html(['- a', '', '1. b']);
t_assert(str_contains($html, '</ul>') && str_contains($html, '<ol>'), 'ul closes before ol opens');
$html = md_to_html(['> quoted line one', '> and two']);
t_eq(1, substr_count($html, '<blockquote>'), 'multi-line quote groups');
t_contains('quoted line one and two', $html, 'quote lines joined');
$html = md_to_html(['---']);
t_contains('<hr>', $html, 'hr renders');
$html = md_to_html(['```', '$x = "**not bold**";', '```']);
t_contains('<pre><code>', $html, 'code fence opens');
t_contains('**not bold**', $html, 'no markdown inside fence');
t_contains('&quot;', $html, 'fence content escaped');

/* ---------------- tags ---------------- */
section('tags');
t_eq('software-architecture', tag_slug('Software Architecture'), 'basic slug');
t_eq('ai-and-ml', tag_slug('AI & ML'), 'ampersand becomes and');
t_eq('cqrs-es', tag_slug('CQRS/ES!!'), 'specials collapse');
t_contains('href="/blog/tag/data-quality/"', tag_link('Data Quality'), 'tag link URL');
t_contains('>#data-quality<', tag_link('Data Quality'), 'hashtag text');
$tags = collect_tags([
    ['slug' => 'p1', 'tags' => ['A', 'B']],
    ['slug' => 'p2', 'tags' => ['A']],
]);
t_eq(['A', 'B'], array_keys($tags), 'collect_tags sorts by count desc');
t_eq(2, count($tags['A']), 'tag groups posts');

/* ---------------- dates ---------------- */
section('dates');
t_eq('2026.07.13', log_date('2026-07-13'), 'log date format');
t_eq('Jul 13, 2026', nice_date('2026-07-13'), 'nice date format');

/* ---------------- post status model ---------------- */
section('post status');
$mkpost = function (string $name, string $extra = '') {
    $dir = sys_get_temp_dir() . '/bf-unit-posts';
    @mkdir($dir);
    $f = $dir . '/' . $name;
    file_put_contents($f, "# Fixture Title\n\n*Fixture subtitle line.*\n\nBody paragraph one with enough words to count.\n"
        . ($extra !== '' ? "\n{$extra}\n" : '') . "\n---\n\n**Tags:** One, Two\n");
    return $f;
};
$p = parse_post_file($mkpost('2020-01-05-fixture-past.md'));
t_eq('published', $p['status'], 'past date without markers = published');
t_eq(post_publish_ts('2020-01-05'), $p['publish_ts'], 'default publish ts from filename date');
$expect = (new DateTimeImmutable('2020-01-05 09:00', site_tz()))->getTimestamp();
t_eq($expect, $p['publish_ts'], 'default publish moment is 09:00 site time');
t_contains('+', $p['publish_iso'], 'publish_iso carries a timezone offset');

$p = parse_post_file($mkpost('2099-01-05-fixture-future.md'));
t_eq('scheduled', $p['status'], 'future date = scheduled');

$p = parse_post_file($mkpost('2020-01-05-fixture-draft.md', '**Status:** draft'));
t_eq('draft', $p['status'], 'Status: draft wins over past date');
t_not_contains('Status', $p['html'], 'status line stripped from body');

$p = parse_post_file($mkpost('2020-01-05-fixture-explicit.md', '**Publish:** 2099-06-01 22:15'));
t_eq('scheduled', $p['status'], 'explicit future Publish overrides filename date');
t_eq((new DateTimeImmutable('2099-06-01 22:15', site_tz()))->getTimestamp(), $p['publish_ts'], 'explicit publish ts exact');
t_not_contains('Publish', $p['html'], 'publish line stripped from body');

$p = parse_post_file($mkpost('2020-01-05-fixture-explicit-past.md', '**Publish:** 2020-06-01 07:30'));
t_eq('published', $p['status'], 'explicit past Publish = published');

$p = parse_post_file($mkpost('2020-01-05-fixture-badpublish.md', '**Publish:** not-a-date'));
t_eq('published', $p['status'], 'unparseable Publish falls back to filename date');
t_eq(post_publish_ts('2020-01-05'), $p['publish_ts'], 'fallback ts = filename default');

// meta markers inside code fences are examples, not metadata
$f = sys_get_temp_dir() . '/bf-unit-posts/2020-01-05-fixture-fenced.md';
file_put_contents($f, "# Fenced\n\n*Sub.*\n\nHow to schedule:\n\n```\n**Status:** draft\n**Publish:** 2099-01-01 00:00\n```\n\nAfter the fence.\n\n**Tags:** One\n");
$p = parse_post_file($f);
t_eq('published', $p['status'], 'fenced Status/Publish examples ignored');
t_contains('**Status:** draft', $p['html'], 'fenced example survives into rendered body');
t_contains('After the fence.', $p['html'], 'body continues after fence');

/* ---------------- status writer (admin "change status") ---------------- */
section('post status writer');
$wf = $mkpost('2026-01-05-fixture-write.md');
t_assert(post_write_status($wf, 'draft'), 'write draft ok');
$p = parse_post_file($wf);
t_eq('draft', $p['status'], 'file now parses as draft');
t_contains('Fixture subtitle line.', (string) file_get_contents($wf), 'body untouched by status write');
t_assert(post_write_status($wf, 'publish'), 'write publish ok');
$p = parse_post_file($wf);
t_eq('published', $p['status'], 'publish-now makes it published');
t_assert(abs($p['publish_ts'] - time()) < 180, 'publish-now moment is about now');
t_not_contains('**Status:**', (string) file_get_contents($wf), 'draft flag removed on publish');
t_assert(post_write_status($wf, 'schedule', '2099-03-04 10:30'), 'schedule ok');
$p = parse_post_file($wf);
t_eq('scheduled', $p['status'], 'scheduled after schedule action');
t_eq((new DateTimeImmutable('2099-03-04 10:30', site_tz()))->getTimestamp(), $p['publish_ts'], 'scheduled moment exact');
t_assert(post_write_status($wf, 'schedule', '2099-06-01 08:00'), 'reschedule ok');
t_eq(1, substr_count((string) file_get_contents($wf), '**Publish:**'), 'repeated writes keep exactly one Publish line');
t_assert(!post_write_status($wf, 'schedule', 'not-a-datetime'), 'garbage datetime rejected');
t_assert(!post_write_status($wf, 'explode'), 'unknown action rejected');
$p = parse_post_file($wf);
t_eq('scheduled', $p['status'], 'rejected writes change nothing');
// fenced meta examples survive a status write untouched
$ff = sys_get_temp_dir() . '/bf-unit-posts/2026-01-05-fixture-fencedwrite.md';
file_put_contents($ff, "# F\n\n*S.*\n\nExample:\n\n```\n**Status:** draft\n```\n\n**Tags:** One\n");
t_assert(post_write_status($ff, 'publish'), 'write on fenced file ok');
$raw = (string) file_get_contents($ff);
t_contains("```\n**Status:** draft\n```", $raw, 'fenced example preserved verbatim');
t_eq('published', parse_post_file($ff)['status'], 'fenced file published');
t_assert(!post_write_status($ff . '.missing', 'publish'), 'missing file rejected');

/* ---------------- load_posts against real content ---------------- */
section('load_posts');
$all = load_posts(true);
t_eq(25, count($all), '25 posts on disk (all statuses)');
t_eq('a-feature-is-not-a-service', $all[0]['slug'], 'newest post first in all-mode');
$posts = load_posts();
t_eq(count(array_filter($all, fn($p) => $p['status'] === 'published')), count($posts), 'default load = published subset of all');
foreach ($posts as $p) if ($p['status'] !== 'published') { t_assert(false, 'published-only load leaked ' . $p['slug']); }
t_assert((bool) $posts, 'at least one published post');
$sorted = true;
for ($i = 1; $i < count($all); $i++) if ($all[$i - 1]['publish_ts'] < $all[$i]['publish_ts']) $sorted = false;
t_assert($sorted, 'posts sorted descending by publish moment');
$allOk = true;
foreach ($all as $p) {
    if ($p['title'] === '' || $p['subtitle'] === '' || count($p['tags']) !== 5 || $p['minutes'] < 1 || $p['html'] === '') {
        $allOk = false;
        echo "    problem post: {$p['slug']}\n";
    }
}
t_assert($allOk, 'every post has title, subtitle, exactly 5 tags, minutes, html');
$bySlug = array_column($all, null, 'slug');
foreach (['stop-calling-it-legacy-code', 'the-automerger', 'technical-leader-identity-disorder'] as $s) {
    t_assert(!empty($bySlug[$s]['medium']), "medium link parsed for {$s}");
}
t_eq(null, $bySlug['mathematicians-vs-lawyers']['medium'], 'no medium link where none declared');
t_not_contains('**Tags:**', $bySlug['data-driven-theater']['html'], 'tags line stripped from body');
t_not_contains('**Medium:**', $bySlug['the-automerger']['html'], 'medium line stripped from body');
t_not_contains($bySlug['data-driven-theater']['subtitle'], $bySlug['data-driven-theater']['html'], 'subtitle not duplicated into body');

/* ---------------- llms.txt selected-posts refresh ---------------- */
section('llms_selected');
$tmp = tempnam(sys_get_temp_dir(), 'llms');
file_put_contents($tmp, "# Header\n\nSelected posts:\n\n- Old Post: https://example.com/blog/old/\n\nTrailing prose stays.\n");
t_assert(update_llms_selected($tmp, [
    ['title' => 'Newest', 'slug' => 'newest'],
    ['title' => 'Older', 'slug' => 'older'],
]), 'selected-posts block found and rewritten');
$llmsOut = (string) file_get_contents($tmp);
t_contains('- Newest: ' . post_url('newest'), $llmsOut, 'new post listed with canonical URL');
t_not_contains('Old Post', $llmsOut, 'stale entry replaced');
t_contains('Trailing prose stays.', $llmsOut, 'prose after the block untouched');
t_assert(!update_llms_selected($tmp . '.missing', []), 'missing file returns false');
// list block at EOF without trailing newline must be replaced, not duplicated
file_put_contents($tmp, "Head\n\nSelected posts:\n\n- Old A: https://x/a/\n- Old B: https://x/b/");
t_assert(update_llms_selected($tmp, [['title' => 'New', 'slug' => 'new']]), 'EOF list block matched');
$llmsOut = (string) file_get_contents($tmp);
t_not_contains('Old B', $llmsOut, 'unterminated last entry replaced too');
t_eq(1, substr_count($llmsOut, '- New: '), 'no duplicated entries at EOF');
unlink($tmp);
$noDash = true;
foreach ($all as $p) {
    $clean = str_replace('Technical Leader — Identity Disorder', '', $p['html']);
    if (str_contains($clean, ' — ')) { $noDash = false; echo "    dash in: {$p['slug']}\n"; }
}
t_assert($noDash, 'no spaced em-dashes in any rendered body (Medium title excepted)');

/* ---------------- hero meta ---------------- */
section('hero_meta');
$h = hero_meta('mathematicians-vs-lawyers');
t_assert($h !== null, 'hero exists for known slug');
t_assert($h['w'] > 400 && $h['h'] > 200, 'hero has real dimensions');
t_contains('.thumb.jpg', $h['thumb'], 'thumbnail variant used');
t_eq(null, hero_meta('no-such-post-xyz'), 'missing hero returns null');
$fig = hero_figure($h, 'Alt Title');
t_contains('alt="Alt Title"', $fig, 'hero figure carries alt');
t_contains('width="', $fig, 'hero figure carries dimensions');

/* ---------------- render_md ---------------- */
section('render_md');
$md = render_md($bySlug['the-automerger']);
t_contains('# The Automerger', $md, 'md mirror has title');
t_contains('Canonical: https://bfrackowiak.pl/blog/the-automerger/', $md, 'md mirror has canonical');
t_contains('Also on Medium: https://medium.com', $md, 'md mirror lists medium link');
$md2 = render_md($bySlug['mathematicians-vs-lawyers']);
t_not_contains('Also on Medium', $md2, 'no medium line when absent');

/* ---------------- feeds ---------------- */
section('feeds');
$rss = render_rss(array_slice($posts, 0, 3));
t_assert(simplexml_load_string($rss) !== false, 'rss is valid xml');
t_contains('<atom:link', $rss, 'rss has atom self link');
t_contains('<content:encoded>', $rss, 'rss carries full content');
t_contains(date('D, d M Y', $posts[0]['publish_ts']), $rss, 'rss pubDate from publish moment');
$sm = render_sitemap($posts);
t_assert(simplexml_load_string($sm) !== false, 'sitemap is valid xml');
t_contains(cfg('site_url') . '/blog/mathematicians-vs-lawyers/', $sm, 'sitemap has post url');
t_contains(cfg('site_url') . '/blog/tag/software-architecture/', $sm, 'sitemap has tag url');

/* ---------------- cards / boxes ---------------- */
section('components');
$card = post_card($posts[1]);
t_contains('class="pcard reveal"', $card, 'card has reveal class');
t_contains('.thumb.jpg', $card, 'card uses thumbnail');
t_not_contains('<a class="tag"', $card, 'no nested anchors inside card link');
$nl = newsletter_box();
t_contains('action="/n.php"', $nl, 'newsletter posts to the self-hosted endpoint');
t_contains('type="email"', $nl, 'newsletter has email field');
t_contains('name="website"', $nl, 'newsletter form carries the honeypot');
$bs = beacon_script();
t_contains("sendBeacon('/a.php'", $bs, 'beacon targets a.php');
t_contains('doNotTrack', $bs, 'beacon honors DNT');
t_contains('pagehide', $bs, 'beacon sends engagement on pagehide');

/* ---------------- analytics: enrichment ---------------- */
section('analytics enrichment');
$uaChrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
$uaIphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
$uaTablet = 'Mozilla/5.0 (Linux; Android 14; SM-X910) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
$uaEdge   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36 Edg/126.0.0.0';
$uaFfLin  = 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';
t_eq(['browser' => 'Chrome', 'os' => 'Windows', 'device' => 'desktop'], an_ua_parse($uaChrome), 'chrome/windows/desktop');
t_eq(['browser' => 'Safari', 'os' => 'iOS', 'device' => 'mobile'], an_ua_parse($uaIphone), 'safari/ios/mobile');
t_eq('tablet', an_ua_parse($uaTablet)['device'], 'android without Mobile = tablet');
t_eq('Edge', an_ua_parse($uaEdge)['browser'], 'edge detected before chrome');
t_eq(['browser' => 'Firefox', 'os' => 'Linux', 'device' => 'desktop'], an_ua_parse($uaFfLin), 'firefox/linux/desktop');
t_assert(an_is_bot('Googlebot/2.1'), 'googlebot is a bot');
t_assert(an_is_bot(''), 'empty UA is a bot');
t_assert(!an_is_bot($uaChrome), 'chrome is not a bot');
t_eq('search', an_ref_group('www.google.com'), 'google = search');
t_eq('news', an_ref_group('news.ycombinator.com'), 'HN = news');
t_eq('social', an_ref_group('www.linkedin.com'), 'linkedin = social');
t_eq('ai', an_ref_group('chatgpt.com'), 'chatgpt = ai');
t_eq('blogs', an_ref_group('medium.com'), 'medium = blogs');
t_eq('direct', an_ref_group(''), 'empty = direct');
t_eq('other', an_ref_group('example.org'), 'unknown = other');
t_eq('phone', an_screen_bucket(390), 'phone bucket');
t_eq('laptop', an_screen_bucket(1440), 'laptop bucket');
t_eq('desktop', an_screen_bucket(2560), 'desktop bucket');
t_eq('', an_screen_bucket(0), 'zero width = empty');
t_eq('en', an_lang('en-US'), 'language primary subtag');
t_eq('pl', an_lang('PL'), 'language lowercased');
t_eq('', an_lang('!!'), 'garbage language rejected');
$utm = an_utm('?utm_source=newsletter&utm_medium=email&utm_campaign=july<script>');
t_eq('newsletter', $utm['source'], 'utm source parsed');
t_eq('email', $utm['medium'], 'utm medium parsed');
t_eq('julyscript', $utm['campaign'], 'utm campaign sanitized');
t_eq('Europe/Warsaw', an_tz('Europe/Warsaw'), 'valid tz kept');
t_eq('', an_tz('java script:x()'), 'bad tz rejected');
$_COOKIE[AN_OPTOUT_COOKIE] = '1';
t_assert(an_is_opted_out(), 'exclusion cookie recognized');
$_COOKIE[AN_OPTOUT_COOKIE] = '0';
t_assert(!an_is_opted_out(), 'wrong cookie value not excluded');
unset($_COOKIE[AN_OPTOUT_COOKIE]);
t_assert(!an_is_opted_out(), 'no cookie, counted');
t_eq('🇵🇱', an_flag('PL'), 'flag emoji from country code');
t_eq('', an_flag('x1'), 'no flag for junk');
t_eq('1m 35s', an_fmt_secs(95), 'seconds formatted');
t_eq('–', an_fmt_secs(0), 'zero seconds is a dash');

/* ---------------- analytics: db + queries ---------------- */
section('analytics db');
$adb = sys_get_temp_dir() . '/bf-unit-analytics-' . getmypid() . '.db';
@unlink($adb);
// v1 schema first — an_db() must migrate it in place
$old = new PDO('sqlite:' . $adb);
$old->exec('CREATE TABLE hits (day TEXT NOT NULL, ts INTEGER NOT NULL, path TEXT NOT NULL, ref TEXT NOT NULL DEFAULT "", visitor TEXT NOT NULL)');
$old->exec("INSERT INTO hits VALUES ('2020-01-01', 1577871000, '/old/', '', 'legacyvis')");
$old = null;
$db = an_db($adb);
$cols = array_column($db->query('PRAGMA table_info(hits)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach (['country', 'browser', 'duration', 'utm_campaign'] as $c) {
    t_assert(in_array($c, $cols, true), "migration added column {$c}");
}
t_eq('1', (string) $db->query("SELECT COUNT(*) FROM hits WHERE visitor='legacyvis'")->fetchColumn(), 'v1 rows survive migration');

$today = gmdate('Y-m-d');
$rec = fn(array $o) => an_record($db, array_merge([
    'day' => $today, 'ts' => time(), 'path' => '/blog/x/', 'ref' => '', 'visitor' => 'v1',
    'ref_group' => 'direct', 'country' => 'PL', 'browser' => 'Chrome', 'os' => 'Linux',
    'device' => 'desktop', 'lang' => 'en', 'screen' => 'laptop', 'tz' => 'Europe/Warsaw',
    'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => '',
], $o));
$rec([]);
$rec(['visitor' => 'v2', 'country' => 'DE', 'path' => '/']);
$rec(['visitor' => 'v2', 'path' => '/blog/x/']);
t_assert(an_engage($db, $today, 'v1', '/blog/x/', 120), 'engage matches its pageview row');
t_assert(!an_engage($db, $today, 'nobody', '/blog/x/', 99), 'engage without a matching row reports false (day-rollover retry signal)');
an_engage($db, $today, 'v1', '/blog/x/', 60);   // cumulative reports keep the max
$v = an_visitor('seed', '2026-01-01', '1.2.3.4', 'UA');
t_eq($v, an_visitor('seed', '2026-01-01', '1.2.3.4', 'UA'), 'visitor hash deterministic');
t_assert($v !== an_visitor('seed', '2026-01-02', '1.2.3.4', 'UA'), 'visitor hash rotates across days');
$since = gmdate('Y-m-d', time() - 7 * 86400);
$sum = an_summary($db, $since);
t_eq(3, $sum['pv'], 'summary pageviews');
t_eq(2, $sum['uv'], 'summary uniques');
t_eq(2, $sum['countries'], 'summary distinct countries');
t_eq(1, $sum['multi'], 'one multi-page reader');
t_eq(120, $sum['avg_time'], 'avg engaged time from engaged rows only, max kept');
$bd = an_breakdown($db, $since, 'country');
t_eq('PL', $bd[0]['v'], 'top country');
$tp = an_top_pages($db, $since);
t_eq('/blog/x/', $tp[0]['path'], 'top page');
t_eq(2, (int) $tp[0]['pv'], 'top page views');
$sumPath = an_summary($db, $since, '/');
t_eq(1, $sumPath['pv'], 'path drilldown narrows summary');
try { an_breakdown($db, $since, 'visitor; DROP TABLE hits'); t_assert(false, 'bad column rejected'); }
catch (InvalidArgumentException) { t_assert(true, 'bad column rejected'); }

// source groups classified at query time; campaign = utm without referrer
$rec(['visitor' => 'g1', 'path' => '/sg/', 'ref' => 'www.google.com']);
$rec(['visitor' => 'g1', 'path' => '/sg/', 'ref' => 'news.ycombinator.com']);
$rec(['visitor' => 'g2', 'path' => '/sg/', 'ref' => '', 'utm_source' => 'newsletter']);
$sg = an_source_groups($db, $since, '/sg/');
$byGroup = array_column($sg, null, 'v');
t_eq(1, $byGroup['search']['pv'] ?? 0, 'google classified as search at read time');
t_eq(1, $byGroup['campaign']['pv'] ?? 0, 'utm without referrer = campaign group');
t_eq(1, $byGroup['news']['uv'] ?? 0, 'per-group uniques exact');

// hour-of-day buckets respect each day's own DST offset (Warsaw: +01 winter, +02 summer)
$winter = (new DateTimeImmutable('2026-01-15 13:00', new DateTimeZone('UTC')))->getTimestamp();
$summer = (new DateTimeImmutable('2026-07-15 13:00', new DateTimeZone('UTC')))->getTimestamp();
$rec(['visitor' => 'w', 'path' => '/dst/', 'ts' => $winter, 'day' => '2026-01-15']);
$rec(['visitor' => 's', 'path' => '/dst/', 'ts' => $summer, 'day' => '2026-07-15']);
$hours = an_hours($db, '2026-01-01', '/dst/');
t_eq(1, $hours[14], 'winter 13:00 UTC lands at 14:00 Warsaw (+01)');
t_eq(1, $hours[15], 'summer 13:00 UTC lands at 15:00 Warsaw (+02)');
@unlink($adb);

/* ---------------- geoip ---------------- */
section('geoip');
$csv = tempnam(sys_get_temp_dir(), 'gcsv');
// deliberately unsorted (83.x before 1.x) to exercise the fallback sort
file_put_contents($csv,
    "83.0.0.0,83.15.255.255,PL\n" .
    "1.0.0.0,1.0.0.255,AU\n" .
    "not,a,line,at all\n" .
    "2001:4860::,2001:4860:ffff:ffff:ffff:ffff:ffff:ffff,US\n" .
    "9.9.9.0,9.9.9.255,ZZ\n");
$gdb = tempnam(sys_get_temp_dir(), 'gdat');
[$n4, $n6] = geoip_compile($csv, $gdb);
t_eq(2, $n4, 'two ipv4 ranges compiled (ZZ dropped)');
t_eq(1, $n6, 'one ipv6 range compiled');
t_eq('AU', geoip_country('1.0.0.1', $gdb), 'v4 hit inside range');
t_eq('AU', geoip_country('1.0.0.255', $gdb), 'v4 hit on range end boundary');
t_eq('PL', geoip_country('83.9.1.2', $gdb), 'v4 hit second range');
t_eq('', geoip_country('1.0.1.0', $gdb), 'v4 miss just past range');
t_eq('', geoip_country('9.9.9.9', $gdb), 'ZZ range not stored');
t_eq('US', geoip_country('2001:4860:4860::8888', $gdb), 'v6 hit');
t_eq('AU', geoip_country('::ffff:1.0.0.1', $gdb), 'v4-mapped v6 normalized');
t_eq('', geoip_country('192.168.1.1', $gdb), 'private v4 never resolves');
t_eq('', geoip_country('nonsense', $gdb), 'garbage ip is empty');
t_eq('', geoip_country('1.0.0.1', $gdb . '.missing'), 'missing db is empty, no error');
unlink($csv);
unlink($gdb);

/* ---------------- admin core ---------------- */
section('admin core');
t_eq('', admin_countdown(time() - 5), 'no countdown for past moments');
t_contains('in ', admin_countdown(time() + 90), 'minute countdown');
t_contains('d ', admin_countdown(time() + 3 * 86400 + 7200), 'day countdown');
$tok = admin_csrf_token();
t_assert(strlen($tok) >= 32, 'csrf token generated');
t_eq($tok, admin_csrf_token(), 'csrf token stable per session');
t_assert(admin_csrf_check($tok), 'csrf token verifies');
t_assert(!admin_csrf_check('wrong'), 'wrong csrf rejected');
t_assert(!admin_csrf_check(null), 'missing csrf rejected');
t_assert(!c_store_mutate('../../etc/passwd', fn($l) => $l), 'store rejects traversal slugs');
t_assert(str_ends_with(c_store_path('some-post'), '/content/comments/some-post.json'), 'store path shape');
$c1 = ['date' => '2026-01-01 10:00', 'email' => 'a@x.com', 'text' => 'first'];
t_eq(admin_comment_key($c1), admin_comment_key($c1), 'comment key stable');
t_assert(admin_comment_key($c1) !== admin_comment_key(['date' => '2026-01-01 10:00', 'email' => 'a@x.com', 'text' => 'second']), 'comment key differs by content');
$fakeIp = '203.0.113.77';
@unlink(admin_rl_file($fakeIp));
t_eq(0, admin_lockout_remaining($fakeIp), 'fresh ip not locked');
for ($i = 0; $i < ADMIN_MAX_FAILS; $i++) admin_note_login($fakeIp, false);
t_assert(admin_lockout_remaining($fakeIp) > 0, 'ip locked after max fails');
admin_note_login($fakeIp, true);
t_eq(0, admin_lockout_remaining($fakeIp), 'successful login clears the lock');
@unlink(admin_rl_file($fakeIp));

/* ---------------- comments: pure functions ---------------- */
section('comments');
t_eq('j***@example.com', c_mask_email('john@example.com'), 'email masked');
t_eq('anonymous', c_mask_email('not-an-email'), 'invalid email masks to anonymous');
t_assert(c_valid_slug('mathematicians-vs-lawyers'), 'real published slug validates');
t_assert(!c_valid_slug('tag'), 'tag pseudo-slug rejected');
t_assert(!c_valid_slug('../../etc/passwd'), 'traversal rejected');
t_assert(!c_valid_slug('no-such-post'), 'unknown slug rejected');
foreach ($all as $p) {
    if ($p['status'] !== 'published') {
        t_assert(!c_valid_slug($p['slug']), "unpublished post rejects comments ({$p['slug']})");
        break;
    }
}

[$a, $b, $token] = c_challenge();
t_assert($a >= 2 && $a <= 9 && $b >= 2 && $b <= 9, 'challenge operands in range');
t_assert(c_verify_challenge($token, (string) ($a + $b)), 'correct answer verifies');
t_assert(c_verify_challenge($token, ' ' . ($a + $b) . ' '), 'whitespace tolerated');
t_assert(!c_verify_challenge($token, (string) ($a + $b + 1)), 'wrong answer rejected');
t_assert(!c_verify_challenge('garbage', '5'), 'garbage token rejected');
$forged = base64_encode('2|2|9999999999') . '.' . str_repeat('a', 64);
t_assert(!c_verify_challenge($forged, '4'), 'forged hmac rejected');
$expiredPayload = '3|4|' . (time() - 100);
$expired = base64_encode($expiredPayload) . '.' . hash_hmac('sha256', $expiredPayload, c_secret());
t_assert(!c_verify_challenge($expired, '7'), 'expired token rejected');

/* ---------------- refactor helpers ---------------- */
section('urls & series');
t_eq('/blog/my-post/', post_path('my-post'), 'post_path relative');
t_eq('https://bfrackowiak.pl/blog/my-post/', post_url('my-post'), 'post_url absolute');
t_eq('/blog/tag/data-quality/', tag_path('Data Quality'), 'tag_path from name');
t_eq('', series_suffix('mathematicians-vs-lawyers'), 'no series suffix outside series');
t_contains('Tools That Scale, part 1', series_suffix('from-signals-to-blips'), 'series suffix for series post');
t_contains('· 5 min read', post_meta_line(['publish_ts' => post_publish_ts('2026-07-13'), 'minutes' => 5, 'slug' => 'x']), 'post meta line');
t_contains('Jul 13, 2026', post_meta_line(['publish_ts' => post_publish_ts('2026-07-13'), 'minutes' => 5, 'slug' => 'x']), 'meta line date from publish moment');

section('components');
t_eq('<img src="/a.jpg" alt="x" width="10" height="5" loading="lazy" decoding="async">', img_lazy('/a.jpg', 'x', 10, 5), 'lazy img attrs');
t_contains('fetchpriority="high"', img_eager('/a.jpg', 'x', 10, 5), 'eager img priority');
t_eq('<span class="eyebrow"><span class="blip"></span>Tag</span>', eyebrow('Tag'), 'eyebrow static');
t_contains('blip-live', eyebrow('Live', true), 'eyebrow live variant');
t_eq('', thumb_span(null, 'thumb'), 'thumb span empty without hero');
t_eq('', syndication_badge(null), 'no badge without medium url');
t_contains('Also published on Medium', syndication_badge('https://medium.com/x'), 'badge with medium url');
$fc = featured_card($posts[0]);
t_contains('class="featured2', $fc, 'featured card class');
t_contains('Latest signal', $fc, 'featured card eyebrow');

section('related posts');
$mk = fn(string $slug, array $tags, int $ts) => ['slug' => $slug, 'tags' => $tags, 'publish_ts' => $ts];
$pool = [
    $mk('self', ['A', 'B', 'C'], 100),
    $mk('two-shared-old', ['A', 'B', 'X'], 50),
    $mk('two-shared-new', ['A', 'B', 'Y'], 90),
    $mk('one-shared', ['C', 'Z', 'W'], 99),
    $mk('none-shared', ['Q', 'Z', 'W'], 999),
];
$rel = related_posts($pool[0], $pool, 3);
t_eq(['two-shared-new', 'two-shared-old', 'one-shared'], array_column($rel, 'slug'), 'ranked by shared tags, then recency');
t_not_contains('self', implode(',', array_column($rel, 'slug')), 'post never relates to itself');
t_eq([], related_posts($mk('lonely', ['QQ'], 1), $pool, 3), 'no shared tags, no related');
t_eq(1, count(related_posts($pool[0], $pool, 1)), 'limit respected');
$withRel = render_post($all[0], null, null, load_posts());
t_contains('Related field notes', $withRel, 'post page carries related block');
$noRel = render_post($all[0], null, null);
t_not_contains('Related field notes', $noRel, 'no related block without a pool');

section('404 page');
$nf = render_404_page($posts);
t_contains('http_response_code(404)', $nf, '404 page sets the status code');
t_contains('No signal on this frequency', $nf, '404 copy present');
t_contains('href="/blog/start/"', $nf, '404 routes to start here');
t_contains(e($posts[0]['title']), $nf, '404 shows the newest essay');
t_contains('noindex', $nf, '404 is noindex');

section('fonts & theme');
$head = page_head('T', 'D', 'https://x/');
t_contains('/assets/fonts.css', $head, 'pages load self-hosted fonts');
t_not_contains('fonts.googleapis.com', $head, 'no external font CSS');
t_not_contains('fonts.gstatic.com', $head, 'no external font host');
t_contains('prefers-color-scheme: dark', $head, 'dark theme-color meta present');
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/site.css');
t_contains('@media (prefers-color-scheme: dark)', $css, 'dark palette defined');
t_assert(count(glob(dirname(__DIR__) . '/assets/fonts/*.woff2')) >= 10, 'woff2 files shipped');
$fcss = (string) file_get_contents(dirname(__DIR__) . '/assets/fonts.css');
t_contains('/assets/fonts/', $fcss, 'fonts.css points at local files');
t_not_contains('gstatic', $fcss, 'fonts.css has no external URLs');
t_contains('latin-ext', $fcss, 'Polish glyph subset included');

section('start page');
$sp = render_start_page($posts);
t_contains('rel="canonical" href="https://bfrackowiak.pl/blog/start/"', $sp, 'start page canonical');
t_contains('href="/blog/mathematicians-vs-lawyers/"', $sp, 'hook essay linked');
foreach (['nobody-owns-the-sum', 'from-signals-to-blips', 'a-company-of-one-staffed-by-agents'] as $s) {
    t_contains('href="/blog/' . $s . '/"', $sp, "door flagship linked: {$s}");
}
t_contains('Then pick your door', $sp, 'doors section present');
t_contains('Tools That Scale', $sp, 'series map note present');
t_contains('action="/n.php"', $sp, 'newsletter form on start page');
t_contains('/#services', $sp, 'soft services link present');
t_contains('llms.txt', $sp, 'AI-agent follow link present');
t_contains(count($posts) . ' essays in', $sp, 'live essay count in headline');
preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $sp, $ldS);
$ldOk = count($ldS[1]) >= 2;
foreach ($ldS[1] as $j) if (json_decode($j) === null) $ldOk = false;
t_assert($ldOk, 'start page JSON-LD parses');
$spEmpty = render_start_page([]);
t_contains('essays in', $spEmpty, 'renders without any posts (no fatal)');
t_not_contains('class="d-flag"', $spEmpty, 'missing curated slugs skipped silently');

section('schema builders');
$bp = schema_blog_posting($posts[0], hero_meta($posts[0]['slug']), post_url($posts[0]['slug']), $posts[0]['publish_iso']);
t_eq('BlogPosting', $bp['@type'], 'blog posting type');
t_assert(isset($bp['image']['width']), 'posting image has dimensions');
t_eq($posts[0]['publish_iso'], $bp['datePublished'], 'posting datePublished = publish moment');
$bc = schema_breadcrumbs(['Home' => 'https://x/', 'Blog' => 'https://x/blog/']);
t_eq(2, count($bc['itemListElement']), 'breadcrumb items');
t_eq(1, $bc['itemListElement'][0]['position'], 'breadcrumb positions start at 1');
t_assert(json_decode(ld_script(schema_blog()) ? substr(ld_script(schema_blog()), 51, -9) : '') !== null || str_contains(ld_script(schema_blog()), '"@type":"Blog"'), 'ld_script serializes');

/* ---------------- bug-hunt regressions (2026-07 review) ---------------- */
section('review regressions');

// unclosed code fence: the page must not render inside <pre> forever
$h = md_to_html(['```', '$x = 1;']);
t_contains('</code></pre>', $h, 'unclosed fence auto-closed at EOF');

// filename slug contract matches every runtime consumer
$dir = sys_get_temp_dir() . '/bf-unit-posts'; @mkdir($dir);
file_put_contents($dir . '/2020-01-05-My_Post.md', "# T\n\nBody.\n");
t_eq(null, parse_post_file($dir . '/2020-01-05-My_Post.md'), 'uppercase/underscore slug rejected at parse');
file_put_contents($dir . '/2020-01-05-ok-slug.md', "# T\n\n*Sub.*\n\nBody words here.\n");
t_assert(parse_post_file($dir . '/2020-01-05-ok-slug.md') !== null, 'conforming slug still parses');

// subtitle line must not swallow multi-emphasis body lines
file_put_contents($dir . '/2020-01-05-sub-multi.md', "# T\n\n*One* word *two*\n\nBody words here.\n");
$sp2 = parse_post_file($dir . '/2020-01-05-sub-multi.md');
t_eq('', $sp2['subtitle'], 'multi-emphasis line is body, not subtitle');

// tag variants merge into one archive, first spelling wins, no dupes per post
$tp = fn(string $slug, array $tags) => ['slug' => $slug, 'tags' => $tags, 'publish_ts' => 1, 'title' => $slug, 'subtitle' => '', 'minutes' => 1];
$merged = collect_tags([$tp('a', ['AI', 'ai']), $tp('b', ['ai'])]);
t_eq(1, count($merged), 'case variants collapse to one tag group');
t_eq('AI', array_key_first($merged), 'first-seen spelling is the display name');
t_eq(2, count($merged['AI']), 'post tagged with both spellings counted once');

// empty archive: sitemap renders without notices
$sm0 = render_sitemap([]);
t_contains('/services/', $sm0, 'empty-archive sitemap still lists static pages');
t_assert(simplexml_load_string($sm0) !== false, 'empty-archive sitemap is valid XML');

// RSS: ']]>' inside content cannot break the CDATA envelope
$rssPost = ['slug' => 'cdata', 'title' => 'C', 'subtitle' => 's', 'html' => '<p>evil ]]> here</p>',
            'publish_ts' => 1700000000, 'publish_iso' => date('c', 1700000000), 'minutes' => 1, 'tags' => []];
t_assert(simplexml_load_string(render_rss([$rssPost])) !== false, 'CDATA breakout escaped, feed stays valid');

// JSON-LD cannot be broken out of via a </script> in author content
t_not_contains('</script></script>', ld_script(['x' => 'a</script>b']), 'HEX_TAG keeps </script> encoded');
t_contains('</script', ld_script(['x' => 'a</script>b']), 'literal </script> is hex-encoded');

// corrupt comment thread: mutation refuses, history bytes survive
$corrupt = c_store_path('unit-corrupt-thread');
@mkdir(c_store_dir(), 0755, true);
file_put_contents($corrupt, '[{"email":"a@b.c","text":"first"}');   // truncated JSON
t_assert(!c_store_mutate('unit-corrupt-thread', fn($l) => [...$l, ['text' => 'new']]), 'corrupt thread aborts the mutate');
t_contains('first', (string) file_get_contents($corrupt), 'damaged bytes left intact for forensics');
@unlink($corrupt);
// aborted mutate on a fresh slug leaves no empty file behind
t_assert(!c_store_mutate('unit-abort-thread', fn($l) => null), 'abort returns false');
t_assert(!is_file(c_store_path('unit-abort-thread')), 'aborted mutate leaves no empty thread file');

/* ---------------- newsletter core ---------------- */
section('newsletter');
require_once dirname(__DIR__) . '/lib/newsletter.php';
$nlTmp = sys_get_temp_dir() . '/bfx-nl-unit-' . getmypid() . '.db';
@unlink($nlTmp);
$ndb = nl_db($nlTmp);

// subscribe: validation + normalization + idempotency
t_eq(null, nl_subscribe($ndb, 'not-an-email'), 'garbage address rejected');
t_eq(null, nl_subscribe($ndb, str_repeat('a', 250) . '@example.com'), 'overlong address rejected');
$tok = nl_subscribe($ndb, '  Reader@Example.COM ');
t_assert(is_string($tok) && strlen($tok) === 32, 'valid address stored, 32-hex token');
t_eq('reader@example.com', $ndb->query('SELECT email FROM subscribers')->fetchColumn(), 'address trimmed + lowercased');
t_eq($tok, nl_subscribe($ndb, 'reader@example.com'), 'resubscribe returns the same token, no dupe row');
t_eq(1, (int) $ndb->query('SELECT COUNT(*) FROM subscribers')->fetchColumn(), 'one row per address');

// confirm: format gate, wrong token, happy path, no status downgrade
t_assert(!nl_confirm($ndb, 'zzz'), 'malformed token rejected');
t_assert(!nl_confirm($ndb, str_repeat('0', 32)), 'unknown token confirms nothing');
t_assert(nl_confirm($ndb, $tok), 'valid token confirms');
t_eq('confirmed', $ndb->query('SELECT status FROM subscribers')->fetchColumn(), 'status flipped to confirmed');
$firstConfirmTs = (int) $ndb->query('SELECT confirmed_ts FROM subscribers')->fetchColumn();
t_eq($tok, nl_subscribe($ndb, 'reader@example.com'), 'subscribe after confirm keeps the row');
t_eq('confirmed', $ndb->query('SELECT status FROM subscribers')->fetchColumn(), 'confirmed never downgraded to pending');
nl_confirm($ndb, $tok);
t_eq($firstConfirmTs, (int) $ndb->query('SELECT confirmed_ts FROM subscribers')->fetchColumn(), 'reconfirm keeps original confirmed_ts');

// counts + unsubscribe
nl_subscribe($ndb, 'pending@example.com');
t_eq(['confirmed' => 1, 'pending' => 1], nl_counts($ndb), 'counts split by status');
t_assert(!nl_unsubscribe($ndb, str_repeat('f', 32)), 'unknown unsubscribe token is a no-op');
t_assert(nl_unsubscribe($ndb, $tok), 'unsubscribe by token works');
t_eq(0, (int) $ndb->query("SELECT COUNT(*) FROM subscribers WHERE email = 'reader@example.com'")->fetchColumn(), 'unsubscribe deletes the row outright');

// mail assembly
t_eq('<a href="https://bfrackowiak.pl/blog/x/">', nl_absolutize('<a href="/blog/x/">'), 'hrefs absolutized');
t_contains('src="https://bfrackowiak.pl/assets/i.jpg"', nl_absolutize('<img src="/assets/i.jpg">'), 'img srcs absolutized');
$fakePost = ['slug' => 'unit-essay', 'title' => 'Unit Essay', 'subtitle' => 'A subtitle.',
             'html' => '<p>Body with <a href="/blog/other/">a link</a>.</p>', 'publish_ts' => time(), 'minutes' => 3];
[$nsub, $nhtml, $ntext] = nl_compose($fakePost);
t_eq('Unit Essay', $nsub, 'subject = essay title');
t_contains('href="https://bfrackowiak.pl/blog/other/"', $nhtml, 'body links absolute in mail');
t_contains('%%UNSUB%%', $nhtml, 'html carries the per-recipient unsubscribe placeholder');
t_contains('%%UNSUB%%', $ntext, 'text alternative carries it too');
t_contains('https://bfrackowiak.pl/blog/unit-essay/', $nhtml, 'read-on-site link present');

// sends ledger: baseline seeds without mailing, then exactly-once per new essay
$pendTok = (string) $ndb->query("SELECT token FROM subscribers WHERE email = 'pending@example.com'")->fetchColumn();
nl_confirm($ndb, $pendTok);   // one confirmed recipient for the dry-run below
$old = ['slug' => 'old-essay', 'title' => 'Old', 'subtitle' => 's', 'html' => '<p>x</p>', 'publish_ts' => time() - 86400, 'minutes' => 1];
$log = nl_send_new([$old], false, $ndb);
t_contains('baseline: seeded 1', implode("\n", $log), 'first run seeds the ledger');
t_eq('baseline', $ndb->query("SELECT note FROM sends WHERE slug = 'old-essay'")->fetchColumn(), 'baseline rows marked');
$log = nl_send_new([$old], false, $ndb);
t_contains('nothing new to send', implode("\n", $log), 'seen essay never sent again');
$log = nl_send_new([$old, $fakePost], true, $ndb);
t_contains('would send "unit-essay" to 1 subscribers', implode("\n", $log), 'dry-run plans the fresh essay only');
t_eq(1, (int) $ndb->query('SELECT COUNT(*) FROM sends')->fetchColumn(), 'dry-run writes nothing to the ledger');
// real send path with zero confirmed recipients: records the essay, mails nobody
nl_unsubscribe($ndb, $pendTok);
$log = nl_send_new([$old, $fakePost], false, $ndb);
t_contains('sent "unit-essay" to 0/0 subscribers', implode("\n", $log), 'fresh essay recorded even with an empty list');
t_eq(2, (int) $ndb->query('SELECT COUNT(*) FROM sends')->fetchColumn(), 'ledger now covers both essays');

// smtp guard: with no configured password the sender must refuse quietly
$smtpCfg = (array) cfg('smtp', []);
if (($smtpCfg['pass'] ?? '') === '') {
    t_assert(!nl_smtp_send('x@example.com', 's', 'h', 't', 'https://u'), 'smtp disabled without a password');
} else {
    t_assert(true, 'smtp guard skipped (live credentials present — not sending from tests)');
}

$ndb = null;
@unlink($nlTmp); @unlink($nlTmp . '-wal'); @unlink($nlTmp . '-shm');

if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'unit.php') exit(t_summary());
