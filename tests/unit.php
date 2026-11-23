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

/* Pipe tables. A run of pipe-prefixed lines is only a table if the second line is a delimiter,
   which is what lets a stray pipe in prose render oddly instead of disappearing. */
section('md_to_html tables');
$html = md_to_html(['| A | B |', '|---|---|', '| 1 | 2 |']);
t_contains('<th>A</th><th>B</th>', $html, 'header cells render');
t_contains('<td>1</td><td>2</td>', $html, 'body cells render');
t_contains('class="table-wrap"', $html, 'the table is wrapped so a wide one scrolls inside itself');
t_not_contains('|', $html, 'no pipes survive into the output');

$html = md_to_html(['| L | C | R |', '|:---|:---:|---:|', '| a | b | c |']);
t_contains('<th style="text-align:center">C</th>', $html, 'centre alignment comes off the delimiter');
t_contains('<td style="text-align:right">c</td>', $html, 'right alignment reaches the body cells');
t_not_contains('<th style="text-align:left"', $html, 'left is the default and needs no style');

$html = md_to_html(['| **b** | `c` |', '|---|---|', '| [x](/y/) | a \| b |']);
t_contains('<strong>b</strong>', $html, 'inline markdown works inside cells');
t_contains('<a href="/y/">x</a>', $html, 'links work inside cells');
t_contains('<td>a | b</td>', $html, 'an escaped pipe is a literal pipe, not a cell break');

$html = md_to_html(['| A | B | C |', '|---|---|---|', '| 1 |', '| 1 | 2 | 3 | 4 |']);
t_contains('<td>1</td><td></td><td></td>', $html, 'a short row is padded to the header width');
t_not_contains('<td>4</td>', $html, 'a long row is truncated to the header width');

$html = md_to_html(['| <script>alert(1)</script> |', '|---|', '| x |']);
t_contains('&lt;script&gt;', $html, 'cell content is escaped');

$html = md_to_html(['| not a table, no delimiter under it']);
t_contains('<p>| not a table', $html, 'a lone pipe line falls back to prose rather than vanishing');
t_not_contains('<table>', $html, 'and does not open a table');

$html = md_to_html(['Prose.', '', '| A |', '|---|', '| 1 |', '', 'After.']);
t_contains('<p>Prose.</p>', $html, 'a paragraph before a table closes cleanly');
t_contains('<p>After.</p>', $html, 'a paragraph after a table opens cleanly');

$html = md_to_html(['```', '| A | B |', '|---|---|', '```']);
t_not_contains('<table>', $html, 'pipes inside a code fence stay text');

/* Theme-paired figures. A draw.io export is a transparent PNG and reads on either background, but
   a diagram captured out of a running app carries that app's canvas, so it needs a twin. The pair
   is opt-in by filename: -light with a -dark sibling on disk, and nothing else changes. */
$diag = '/assets/img/posts/we-deleted-the-wrong-half-of-agile/two-jobs-light.png';
$html = md_to_html(["![Cap.]({$diag})"]);
t_contains('<picture>', $html, 'a -light image with a -dark twin becomes a picture');
t_contains('media="(prefers-color-scheme: dark)"', $html, 'the dark twin is behind a colour-scheme query');
t_contains('two-jobs-dark.png', $html, 'the source points at the dark file');
t_contains('src="' . $diag . '"', $html, 'the img itself stays the light file for anything without picture support');
$plain = md_to_html(['![Cap.](/assets/img/posts/the-bottleneck-moved/the-queue-moved.png)']);
t_not_contains('<picture>', $plain, 'an image with no -light suffix is left alone');
$orphan = md_to_html(['![Cap.](/assets/img/posts/nope-light.png)']);
t_not_contains('<picture>', $orphan, 'a -light name with no twin on disk is left alone');

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
$noonTs = strtotime('2026-07-13 12:00');
t_eq('2026.07.13', log_ts($noonTs), 'log timestamp format');
t_eq('Jul 13, 2026', nice_ts($noonTs), 'nice timestamp format');

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

// optional **Updated:** revision marker
$p = parse_post_file($mkpost('2020-01-05-fixture-updated.md', '**Updated:** 2021-03-04 10:00'));
t_eq((new DateTimeImmutable('2021-03-04 10:00', site_tz()))->getTimestamp(), $p['updated_ts'], 'explicit Updated parsed in site tz');
t_contains('+', (string) $p['updated_iso'], 'updated_iso carries a timezone offset');
t_not_contains('Updated', $p['html'], 'updated line stripped from body');
t_eq(null, parse_post_file($mkpost('2020-01-05-fixture-noupdate.md'))['updated_ts'], 'no Updated marker = null');
t_eq(null, parse_post_file($mkpost('2020-01-05-fixture-badupdate.md', '**Updated:** garbage'))['updated_ts'], 'unparsable Updated ignored');
t_eq(null, parse_post_file($mkpost('2020-01-05-fixture-preupdate.md', '**Updated:** 2019-01-01'))['updated_ts'], 'Updated before publish ignored');

// meta keys are table-driven: anything not in the table is prose, and a key
// whose value does not fit its shape is prose too, not a silently wrong value
$p = parse_post_file($mkpost('2020-01-05-fixture-unknownmeta.md', '**Footnote:** not a meta key'));
t_contains('<strong>Footnote:</strong> not a meta key', $p['html'], 'an unrecognised **Key:** line stays in the body');
$p = parse_post_file($mkpost('2020-01-05-fixture-badstatus.md', '**Status:** draft but with prose'));
t_eq('published', $p['status'], 'a Status value that is not one word is not a status');
t_contains('Status', $p['html'], 'and it stays visible in the body rather than vanishing');
$p = parse_post_file($mkpost('2020-01-05-fixture-badmedium.md', '**Medium:** two words here'));
t_eq([], $p['syndication'], 'a Medium value that is not a bare URL is not a link');

// a syndication URL lands in an href and in sameAs, so it is validated, not trusted
t_eq([], parse_post_file($mkpost('2020-01-05-fixture-jsurl.md', '**Medium:** javascript:alert(1)'))['syndication'], 'a javascript: URL is refused');
t_eq([], parse_post_file($mkpost('2020-01-05-fixture-httpmed.md', '**Medium:** http://medium.com/@x/y'))['syndication'], 'plain http is refused');
t_eq([], parse_post_file($mkpost('2020-01-05-fixture-wronghost.md', '**DevTo:** https://medium.com/@x/y'))['syndication'], 'a DevTo line pointing at Medium is refused');
t_eq([], parse_post_file($mkpost('2020-01-05-fixture-lookalike.md', '**Medium:** https://notmedium.com/@x/y'))['syndication'], 'a look-alike host is refused');
t_eq(['Medium' => 'https://blog.medium.com/x'], parse_post_file($mkpost('2020-01-05-fixture-subdomain.md', '**Medium:** https://blog.medium.com/x'))['syndication'], 'a subdomain of the real host is accepted');
$bothSynd = parse_post_file($mkpost('2020-01-05-fixture-both.md', "**Medium:** https://medium.com/@x/y\n**DevTo:** https://dev.to/x/y"))['syndication'];
t_eq(['Medium', 'DevTo'], array_keys($bothSynd), 'both platforms parse, in table order');
$p = parse_post_file($mkpost('2020-01-05-fixture-statusok.md', '**Status:** published'));
t_eq('published', $p['status'], 'a non-draft Status word leaves the post published');
$p = parse_post_file($mkpost('2020-01-05-fixture-twice.md', "**Publish:** 2021-01-01 08:00\n**Publish:** 2099-01-01 08:00"));
t_eq('scheduled', $p['status'], 'a repeated meta key takes the last value');

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

/* ---------------- editorial scores (docs/SCORING-RUBRIC.md) ---------------- */
section('scores: parsing');
$full = 'tension=7 originality=6 personal=9 contrarian=5 substance=8 punch=4 trend=3';
$sc = sc_parse_scores($full);
t_eq(7, count($sc), 'all seven axes parsed');
t_eq(array_keys(SC_AXES), array_keys($sc), 'axes come back in canonical order, not written order');
t_eq($sc, sc_parse_scores('trend=3 punch=4 substance=8 contrarian=5 personal=9 originality=6 tension=7'), 'written order does not matter');
t_assert(sc_complete($sc), 'full set is complete');
t_eq(3, count(sc_parse_scores('tension=7 punch=4 trend=3')), 'partial set parses what is there');
t_assert(!sc_complete(sc_parse_scores('tension=7')), 'partial set is not complete');
t_eq([], sc_parse_scores('nonsense'), 'no pairs = empty');
t_eq([], sc_parse_scores('tension=0 punch=11 trend=-4'), 'out-of-range values dropped, never clamped');
t_eq(['tension' => 7], sc_parse_scores('tension=7 vibes=9 charisma=10'), 'unknown axes dropped');
t_eq(['tension' => 7], sc_parse_scores('TENSION = 7'), 'case and spacing tolerated');

$meta = sc_parse_scored('2026-07-27 rubric=v1 model=claude-opus-5');
t_eq('2026-07-27', $meta['on'], 'scored date parsed');
t_eq('v1', $meta['rubric'], 'rubric version parsed');
t_eq('claude-opus-5', $meta['model'], 'model parsed');
t_eq(['on' => '', 'rubric' => '', 'model' => ''], sc_parse_scored(''), 'empty Scored line yields empty parts');
t_eq($full, sc_fmt_scores($sc), 'format round-trips the canonical line');

section('scores: reach');
t_eq(1.0, round(array_sum(array_column(SC_AXES, 'weight')), 10), 'axis weights sum to exactly 1.0');
$flat = array_fill_keys(array_keys(SC_AXES), 7);
t_eq(7.0, sc_reach($flat), 'all-sevens scores exactly 7.0 (weights normalised)');
t_eq(1.0, sc_reach(array_fill_keys(array_keys(SC_AXES), 1)), 'floor of the scale is 1.0');
t_eq(10.0, sc_reach(array_fill_keys(array_keys(SC_AXES), 10)), 'ceiling of the scale is 10.0');
t_eq(null, sc_reach(sc_parse_scores('tension=7')), 'incomplete set has no reach score');
t_eq(6.4, sc_reach($sc), 'weighted composite of a mixed set');
t_eq('strong', sc_band(7.5), 'band boundary: 7.5 is strong');
t_eq('solid', sc_band(7.4), 'band boundary: 7.4 is solid');
t_eq('solid', sc_band(6.0), 'band boundary: 6.0 is solid');
t_eq('weak', sc_band(5.9), 'band boundary: 5.9 is weak');
t_eq('weak', sc_band(4.5), 'band boundary: 4.5 is weak');
t_eq('floor', sc_band(4.4), 'band boundary: 4.4 is the floor');
t_eq('none', sc_band(null), 'unscored posts have no band');

section('scores: explaining the number');
// every axis the model scores must be explainable, or the admin popup shows a
// number with nothing behind it
foreach (SC_AXES as $axKey => $axDef) {
    t_assert(isset(SC_AXIS_DOC[$axKey]), "axis {$axKey} has rubric documentation");
    t_eq([1, 3, 5, 7, 9], array_keys(SC_AXIS_DOC[$axKey]['anchors']), "axis {$axKey} documents all five anchors");
    t_assert(SC_AXIS_DOC[$axKey]['what'] !== '' && SC_AXIS_DOC[$axKey]['why'] !== '', "axis {$axKey} says what it measures and why it is weighted");
}
foreach (['strong', 'solid', 'weak', 'floor', 'none'] as $bandKey) {
    t_assert(isset(SC_BAND_DOC[$bandKey]['do']), "band {$bandKey} says what to do about it");
}

$contrib = sc_contributions($sc);
t_eq(7, count($contrib), 'every axis contributes a line');
t_eq(6.4, round(array_sum(array_column($contrib, 'points')), 1), 'contributions add up to the reach score');
t_eq('personal', array_key_first($contrib), 'biggest contributor first (9 × 0.16 beats 7 × 0.20)');
t_eq(1.44, $contrib['personal']['points'], 'contribution is weight × score');
t_assert(array_sum(array_column($contrib, 'share')) >= 98, 'shares account for the whole score');
t_eq([], sc_contributions(sc_parse_scores('tension=7')), 'an incomplete set explains nothing');

// the lever is weight × headroom: not simply the lowest axis, not simply the heaviest
// contrarian=5 (0.15 × 5 = 0.75) beats both the lowest axis (trend=3, 0.56)
// and the heaviest one (tension=7, 0.60)
t_eq('contrarian', sc_lever($sc)['axis'], 'lever picks the axis where a rewrite buys the most reach');
t_eq(0.75, sc_lever($sc)['gain'], 'lever reports the reach it would add');
t_eq(null, sc_lever(array_fill_keys(array_keys(SC_AXES), 10)), 'a perfect post has no lever left');
t_eq(null, sc_lever(sc_parse_scores('tension=7')), 'incomplete set has no lever');

section('scores: score against traffic');
$mkrow = fn(string $slug, ?float $reach, int $pv, string $status = 'published'): array => [
    'slug' => $slug, 'reach' => $reach, 'pv' => $pv, 'status' => $status,
];
$gapped = sc_rank_gaps([
    $mkrow('best-unread', 9.0, 1),
    $mkrow('mid', 7.0, 50),
    $mkrow('worst-viral', 5.0, 900),
    $mkrow('scheduled-one', 8.0, 0, 'scheduled'),
    $mkrow('never-scored', null, 400),
]);
t_eq(-2, $gapped[0]['gap'], 'top score with no readers ranks two places lower in views: a distribution problem');
t_eq(2, $gapped[2]['gap'], 'weakest score with the most readers ranks two places higher: the rubric missed something');
t_eq(0, $gapped[1]['gap'], 'a post the model ranked exactly where readers did has no gap');
t_eq(null, $gapped[3]['gap'], 'a scheduled post has no audience to compare against');
t_eq(null, $gapped[4]['gap'], 'an unscored post has no prediction to compare against');
$tiny = sc_rank_gaps([$mkrow('a', 9.0, 10), $mkrow('b', 5.0, 90)]);
t_eq(null, $tiny[0]['gap'], 'two posts are not a ranking — no gap invented');

section('scores: in the post source');
$sf = $mkpost('2020-01-05-fixture-scored.md', "**Scores:** {$full}\n**Scored:** 2026-07-27 rubric=v1 model=test-model");
$p = parse_post_file($sf);
t_eq(7, count($p['scores']), 'scores parsed off the post');
t_eq(6.4, sc_reach($p['scores']), 'reach computed from the post');
t_eq('test-model', $p['scored']['model'], 'scoring model recorded on the post');
t_not_contains('Scores', $p['html'], 'scores line stripped from the rendered body');
t_not_contains('Scored', $p['html'], 'scored line stripped from the rendered body');
t_eq([], parse_post_file($mkpost('2020-01-05-fixture-unscored.md'))['scores'], 'unscored post has an empty score set');

// a scores line inside a fence is documentation, not metadata
$sfence = sys_get_temp_dir() . '/bf-unit-posts/2020-01-05-fixture-scorefence.md';
file_put_contents($sfence, "# S\n\n*Sub.*\n\nHow to score:\n\n```\n**Scores:** tension=1 originality=1 personal=1 contrarian=1 substance=1 punch=1 trend=1\n```\n\n**Tags:** One\n");
t_eq([], parse_post_file($sfence)['scores'], 'fenced Scores example is not metadata');

section('scores: writer');
$swf = $mkpost('2026-01-05-fixture-scorewrite.md');
t_assert(sc_write_meta($swf, $sc, 'test-model', '2026-07-27'), 'score write ok');
$p = parse_post_file($swf);
t_eq(6.4, sc_reach($p['scores']), 'written scores parse back identically');
t_eq('v1', $p['scored']['rubric'], 'writer stamps the current rubric version');
t_contains('Fixture subtitle line.', (string) file_get_contents($swf), 'body untouched by score write');
// the file is rewritten before every publish: repeated writes must not drift
$after1 = (string) file_get_contents($swf);
t_assert(sc_write_meta($swf, $sc, 'test-model', '2026-07-27'), 'second identical write ok');
t_eq($after1, (string) file_get_contents($swf), 'rewriting identical scores is byte-for-byte idempotent');
t_assert(sc_write_meta($swf, array_fill_keys(array_keys(SC_AXES), 2), 'test-model', '2026-07-27'), 'rescore ok');
$raw = (string) file_get_contents($swf);
t_eq(1, substr_count($raw, '**Scores:**'), 'rescoring keeps exactly one Scores line');
t_eq(1, substr_count($raw, '**Scored:**'), 'rescoring keeps exactly one Scored line');
t_eq(2.0, sc_reach(parse_post_file($swf)['scores']), 'rescored values win');
t_assert(!sc_write_meta($swf, sc_parse_scores('tension=7'), 'test-model'), 'incomplete set refused: half-scored looks measured');
t_assert(!sc_write_meta($swf, $sc, 'test-model', 'yesterday'), 'bad scoring date refused');
t_eq(2.0, sc_reach(parse_post_file($swf)['scores']), 'refused writes change nothing');
// status writes and score writes share post_meta_write and must not disturb each other
t_assert(post_write_status($swf, 'draft'), 'status write on a scored file ok');
$p = parse_post_file($swf);
t_eq('draft', $p['status'], 'status applied');
t_eq(2.0, sc_reach($p['scores']), 'scores survive a status write');

section('scores: correlation');
t_eq(1.0, sc_spearman([1, 2, 3, 4], [10, 20, 30, 40]), 'perfectly concordant series = +1');
t_eq(-1.0, sc_spearman([1, 2, 3, 4], [40, 30, 20, 10]), 'perfectly discordant series = -1');
t_eq(1.0, sc_spearman([1, 2, 3, 4], [1, 4, 9, 16]), 'rank-based: monotonic but non-linear still +1');
t_eq(null, sc_spearman([5, 5, 5, 5], [1, 2, 3, 4]), 'a flat series correlates with nothing');
t_eq(null, sc_spearman([1, 2], [1, 2]), 'fewer than three pairs is not a correlation');
t_eq(null, sc_spearman([1, 2, 3], [1, 2]), 'mismatched lengths rejected');
t_eq([1.5, 1.5, 3.0], sc_ranks([4, 4, 9]), 'ties share their average rank');
// one runaway post must not decide the whole correlation — the reason this is rank-based
t_assert(sc_spearman([1, 2, 3, 4, 5], [1, 2, 3, 4, 500]) === 1.0, 'an outlier cannot inflate a rank correlation');

section('scores: analytics join');
$rows = [
    ['slug' => 'a', 'scores' => array_fill_keys(array_keys(SC_AXES), 9)],
    ['slug' => 'b', 'scores' => array_fill_keys(array_keys(SC_AXES), 5)],
    ['slug' => 'c', 'scores' => array_fill_keys(array_keys(SC_AXES), 2)],
    ['slug' => 'd', 'scores' => []],
];
$merged = sc_merge_analytics($rows, [
    ['path' => '/blog/a/', 'pv' => 300, 'uv' => 200, 'avg_time' => 180.4],
    ['path' => '/blog/b/', 'pv' => 100, 'uv' => 80, 'avg_time' => 90.0],
    ['path' => '/blog/c/', 'pv' => 10, 'uv' => 9, 'avg_time' => 20.0],
]);
t_eq(4, count($merged), 'every post survives the join');
t_eq(300, $merged[0]['pv'], 'traffic attached by post path');
t_eq(180, $merged[0]['avg_time'], 'average engaged time rounded to whole seconds');
t_eq(0, $merged[3]['pv'], 'a post with no recorded traffic joins as zero, not as missing');
t_eq('strong', $merged[0]['band'], 'band attached on the joined row');
t_eq('none', $merged[3]['band'], 'unscored row carries no band');

$corr = sc_correlations($merged);
t_eq(3, $corr['n'], 'only scored posts with traffic enter the correlation');
t_eq(1.0, $corr['axes']['tension']['pv'], 'higher-scored posts read more in this fixture');
t_eq(1.0, $corr['reach']['pv'], 'reach score correlates with pageviews in this fixture');
t_eq(0, sc_correlations([['slug' => 'x', 'scores' => [], 'reach' => null, 'pv' => 0, 'avg_time' => 0]])['n'], 'nothing to correlate = n of zero');

section('scores: mirror db');
$scTmp = sys_get_temp_dir() . '/bf-unit-scores.db';
@unlink($scTmp);
$sdb = sc_db($scTmp);
$posts = [
    ['slug' => 'scored-one', 'title' => 'One', 'status' => 'published', 'publish_ts' => 1700000000, 'words' => 900,
     'scores' => $sc, 'scored' => ['on' => '2026-07-27', 'rubric' => 'v1', 'model' => 'test-model']],
    ['slug' => 'unscored-one', 'title' => 'Two', 'status' => 'draft', 'publish_ts' => 1700000001, 'words' => 100,
     'scores' => [], 'scored' => ['on' => '', 'rubric' => '', 'model' => '']],
];
t_eq(2, sc_sync($sdb, $posts), 'both posts mirrored');
$row = $sdb->query("SELECT * FROM scores WHERE slug = 'scored-one'")->fetch(PDO::FETCH_ASSOC);
t_eq(7, (int) $row['tension'], 'axis stored per column');
t_eq(6.4, round((float) $row['reach'], 1), 'reach stored alongside the axes');
t_eq('v1', $row['rubric'], 'rubric version stored for comparability checks');
$row2 = $sdb->query("SELECT * FROM scores WHERE slug = 'unscored-one'")->fetch(PDO::FETCH_ASSOC);
t_eq(null, $row2['reach'], 'unscored post stores a null reach, not a zero');
// the mirror is a cache: a re-sync must not leave a removed post behind as still-scored
t_eq(1, sc_sync($sdb, [$posts[0]]), 're-sync with one post');
t_eq(1, (int) $sdb->query('SELECT COUNT(*) FROM scores')->fetchColumn(), 'removed post swept from the mirror');
$sdb = null;
@unlink($scTmp); @unlink($scTmp . '-wal'); @unlink($scTmp . '-shm');

/* ---------------- load_posts against real content ---------------- */
section('load_posts');
$all = load_posts(true);
// a floor, not an exact count: this must catch a glob or parse regression that
// silently drops posts, without needing an edit every time one is written
t_assert(count($all) >= 25, count($all) . ' posts on disk (all statuses)');
t_eq(max(array_column($all, 'publish_ts')), $all[0]['publish_ts'], 'newest publish moment first in all-mode');
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
t_assert(!empty($bySlug['stop-calling-it-legacy-code']['syndication']['Medium']), 'medium link parsed for stop-calling-it-legacy-code');
t_eq([], $bySlug['nobody-owns-the-sum']['syndication'], 'no syndication where none declared');
t_not_contains('**Tags:**', $bySlug['data-driven-theater']['html'], 'tags line stripped from body');
t_not_contains('**Medium:**', $bySlug['stop-calling-it-legacy-code']['html'], 'medium line stripped from body');
t_not_contains($bySlug['data-driven-theater']['subtitle'], $bySlug['data-driven-theater']['html'], 'subtitle not duplicated into body');

/* ---------------- figures: dimensions and schema ---------------- */
// 13 diagrams across the corpus render into post bodies. Without intrinsic
// dimensions each one reflows the article as it loads, which readers feel and
// Core Web Vitals scores. Nothing covered this before.
section('figures');
$realImg = '/assets/img/posts/a-feature-is-not-a-service/identity.png';
[$iw, $ih] = getimagesize(APP_ROOT . $realImg);

$fig = md_to_html(["![A caption that stands alone.]({$realImg})"]);
t_contains('<figure class="post-figure">', $fig, 'standalone image line becomes a figure');
t_contains('width="' . $iw . '" height="' . $ih . '"', $fig, 'figure carries the intrinsic dimensions');
t_contains('<figcaption', $fig, 'alt text is rendered as the visible caption');
t_contains('alt="A caption that stands alone."', $fig, 'alt text preserved');
t_contains('loading="lazy"', $fig, 'body figures stay lazy');

t_contains('width="' . $iw . '"', md_inline("![x]({$realImg})"), 'inline images are measured too');
t_not_contains('width=', md_to_html(['![x](/assets/img/posts/does-not-exist.png)']), 'a missing file yields no dimensions rather than zeros');
t_not_contains('width=', md_to_html(['![x](https://example.com/remote.png)']), 'remote images are never probed from disk');
t_not_contains('width=', md_to_html(['![x](//cdn.example.com/p.png)']), 'protocol-relative URLs are not treated as local paths');
t_eq('', md_img_dimensions('/../config.php'), 'a non-image path yields nothing');

$figPost = parse_post_file(post_source_file('a-feature-is-not-a-service'));
$bodyImgs = post_body_images($figPost);
t_eq(3, count($bodyImgs), 'body figures found in the markdown');
t_assert($bodyImgs[0]['w'] > 0 && $bodyImgs[0]['h'] > 0, 'each body figure is measured');
t_assert($bodyImgs[0]['caption'] !== '', 'each body figure carries its caption');
t_eq([], post_body_images(['md' => 'no images here at all']), 'a post without figures yields none');
t_eq([], post_body_images(['md' => 'inline ![x](/a.png) mid-sentence']), 'only standalone image lines count as figures');

$figSchema = schema_blog_posting($figPost, hero_meta('a-feature-is-not-a-service'), post_url('a-feature-is-not-a-service'), $figPost['publish_iso']);
t_assert(isset($figSchema['image'][0]), 'schema image is a list when the post has diagrams');
t_eq(4, count($figSchema['image']), 'hero plus three diagrams are all published to structured data');
t_contains('a-feature-is-not-a-service.jpg', $figSchema['image'][0]['url'], 'hero leads the image list');
t_assert(!empty($figSchema['image'][1]['caption']), 'diagram captions reach the schema');
$noFig = schema_blog_posting(parse_post_file(post_source_file('nobody-owns-the-sum')), hero_meta('nobody-owns-the-sum'), post_url('nobody-owns-the-sum'), '2025-12-22T09:00:00+01:00');
t_assert(isset($noFig['image']['@type']), 'a post with only a hero keeps a single image object');

/* ---------------- gates that fail silently ---------------- */
section('publication gate');
// post_is_public decides whether comments.php will accept a comment at all.
// A regression here opens drafts and scheduled essays to the public.
//
// The unpublished cases use fixtures rather than real essays. post_is_public
// resolves through post_source_file, which globs the live content directory, so
// the fixtures have to land there and be removed again — via shutdown, so a
// failing assertion above cannot leave them behind. Pointing these assertions at
// a genuinely scheduled post is what broke here before: the post went live on
// its own publish date and the test started failing on a calendar, not a
// regression. 2099 is the same never-arrives date the integration fixtures use.
$gateFixtures = [];
$mkGate = function (string $file, string $extra) use (&$gateFixtures) {
    $path = CONTENT_DIR . '/posts/' . $file;
    file_put_contents($path, "# Gate Fixture\n\n*Gate fixture subtitle.*\n\nBody paragraph with enough words to count as one.\n\n{$extra}\n\n**Tags:** One\n");
    $gateFixtures[] = $path;
};
register_shutdown_function(function () use (&$gateFixtures) {
    foreach ($gateFixtures as $f) @unlink($f);
});
$mkGate('2099-01-01-unit-gate-scheduled.md', '**Publish:** 2099-01-01 09:00');
$mkGate('2020-01-01-unit-gate-draft.md', '**Status:** draft');

t_assert(post_is_public('mathematicians-vs-lawyers'), 'a published post is public');
t_assert(!post_is_public('unit-gate-scheduled'), 'a scheduled post is not public');
t_assert(!post_is_public('unit-gate-draft'), 'a draft post is not public');
t_assert(!post_is_public('no-such-post-anywhere'), 'an unknown slug is not public');
t_assert(!post_is_public('../../config'), 'a traversal attempt is not public');
t_assert(!post_is_public(''), 'an empty slug is not public');

foreach ($gateFixtures as $f) unlink($f);
$gateFixtures = [];

section('analytics ip exclusion');
// getting the mask wrong here silently excludes everybody, or nobody
t_assert(an_ip_in_block('203.0.113.7', '203.0.113.7'), 'exact IPv4 match');
t_assert(!an_ip_in_block('203.0.113.8', '203.0.113.7'), 'neighbouring address is not a match');
t_assert(an_ip_in_block('203.0.113.7', '203.0.113.0/24'), 'inside a /24');
t_assert(!an_ip_in_block('203.0.114.7', '203.0.113.0/24'), 'outside a /24');
t_assert(an_ip_in_block('203.0.113.7', '203.0.113.0/29'), 'inside a /29, partial byte');
t_assert(!an_ip_in_block('203.0.113.8', '203.0.113.0/29'), 'just past a /29 boundary');
t_assert(an_ip_in_block('203.0.113.7', '0.0.0.0/0'), 'a /0 matches everything in family');
t_assert(an_ip_in_block('203.0.113.7', '203.0.113.7/32'), 'a /32 is the address itself');
t_assert(!an_ip_in_block('203.0.113.6', '203.0.113.7/32'), 'and only that address');

t_assert(an_ip_in_block('2001:db8:1234::1', '2001:db8:1234::/48'), 'inside an IPv6 /48');
t_assert(!an_ip_in_block('2001:db8:9999::1', '2001:db8:1234::/48'), 'outside an IPv6 /48');
// packed comparison, so notation cannot slip past
t_assert(an_ip_in_block('2001:0db8:0000::1', '2001:db8::1'), 'IPv6 written two ways is one address');
t_assert(!an_ip_in_block('203.0.113.7', '2001:db8::/32'), 'a v4 address is never inside a v6 block');
t_assert(!an_ip_in_block('2001:db8::1', '203.0.113.0/24'), 'and the reverse');

t_assert(!an_ip_in_block('203.0.113.7', ''), 'an empty block matches nothing');
t_assert(!an_ip_in_block('203.0.113.7', 'not-an-ip'), 'garbage matches nothing');
t_assert(!an_ip_in_block('203.0.113.7', '203.0.113.0/99'), 'an impossible prefix length matches nothing');
t_assert(!an_ip_in_block('', '203.0.113.0/24'), 'an empty address matches nothing');

$exBlocks = ['203.0.113.7', '198.51.100.0/24', '2001:db8::/32'];
t_assert(an_is_excluded_ip('203.0.113.7', $exBlocks), 'excluded by exact address');
t_assert(an_is_excluded_ip('198.51.100.55', $exBlocks), 'excluded by IPv4 block');
t_assert(an_is_excluded_ip('2001:db8:aaaa::9', $exBlocks), 'excluded by IPv6 block');
t_assert(!an_is_excluded_ip('192.0.2.1', $exBlocks), 'an unrelated visitor is still counted');
t_assert(!an_is_excluded_ip('', $exBlocks), 'a missing REMOTE_ADDR is not an exclusion');
t_assert(!an_is_excluded_ip('203.0.113.7', []), 'no configured blocks excludes nobody');

section('analytics column guard');
// an_check_col is the only thing standing between a query parameter and an
// interpolated SQL identifier in an_breakdown.
foreach (['country', 'browser', 'os', 'device', 'lang', 'screen', 'tz', 'ref', 'path', 'utm_source'] as $col) {
    $ok = true;
    try { an_check_col($col); } catch (Throwable) { $ok = false; }
    t_assert($ok, "known dimension accepted: {$col}");
}
foreach (['duration', 'excl', 'visitor; DROP TABLE hits', '1=1', '', 'DAY', 'ref--'] as $bad) {
    $threw = false;
    try { an_check_col($bad); } catch (InvalidArgumentException) { $threw = true; }
    t_assert($threw, 'rejected: ' . ($bad === '' ? '(empty)' : $bad));
}

$whereArgs = [];
t_eq('excl = 0 AND day >= ?', an_where('2026-01-01', '', $whereArgs), 'bare period clause skips excluded rows');
t_eq(['2026-01-01'], $whereArgs, 'period bound as an argument, never interpolated');
$whereArgs = [];
t_eq('excl = 0 AND day >= ? AND day < ? AND path = ?', an_where('2026-01-01', '/blog/x/', $whereArgs, '2026-02-01'), 'period plus until plus path');
t_eq(['2026-01-01', '2026-02-01', '/blog/x/'], $whereArgs, 'all three bound in clause order');

/* ---------------- one slug, two source files ---------------- */
// Rescheduling renames a post file. The deploy is additive, so the old name can
// survive on the server: the post then parses twice, builds twice and is listed
// twice in the pipeline. This is that exact situation, reproduced on disk.
section('duplicate post sources');
t_eq([], post_duplicate_sources(), 'the real corpus has no duplicate slugs');

$canon = post_source_file('mathematicians-vs-lawyers');
$stale = CONTENT_DIR . '/posts/2099-01-01-mathematicians-vs-lawyers.md';
copy($canon, $stale);
// the leftover must be older than the live file, relative to that file's own
// mtime: "an hour ago" is not necessarily older than a post edited this morning
touch($stale, filemtime($canon) - 3600);
try {
    $dupes = post_duplicate_sources();
    t_assert(isset($dupes['mathematicians-vs-lawyers']), 'duplicate slug detected');
    t_eq(2, count($dupes['mathematicians-vs-lawyers'] ?? []), 'both filenames reported for the operator');
    t_eq($canon, post_source_file('mathematicians-vs-lawyers'), 'writers target the newest file, never the leftover');

    $dupAll = load_posts(true);
    t_eq(1, count(array_filter($dupAll, fn($p) => $p['slug'] === 'mathematicians-vs-lawyers')), 'the post loads once, not twice');
    t_eq(count(array_unique(array_column($dupAll, 'slug'))), count($dupAll), 'every loaded slug is unique');

    $dupPub = load_posts();
    t_eq(1, count(array_filter($dupPub, fn($p) => $p['slug'] === 'mathematicians-vs-lawyers')), 'published-only load dedupes too');

    // and the other way round: the newer file wins even when its name sorts last
    touch($stale, filemtime($canon) + 3600);
    t_eq($stale, post_source_file('mathematicians-vs-lawyers'), 'tie-break follows mtime, not filename order');
} finally {
    unlink($stale);
}
t_eq([], post_duplicate_sources(), 'fixture removed, corpus clean again');

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
t_contains('Photo', $fig, 'a photographic hero is credited as one');

/* A hero rendered from a model is a drawing: no photographer, no licence, and calling it a photo
   is simply false. It also keeps its own proportions, because covering a diagram into the photo
   band cuts half of it off. */
$png = hero_meta('turn-the-sequence-diagram-on-its-side');
if ($png !== null) {
    t_contains('.png', $png['src'], 'a png hero is preferred over a photograph');
    $pf = hero_figure($png, 'Drawn');
    t_not_contains('>Photo<', $pf, 'a rendered hero is never credited as a photo');
    t_not_contains('<figcaption>', $pf, 'an uncredited drawing gets no caption line');
    t_contains('<img', $pf, 'the figure still renders its image');
}
t_contains('img[src$=".png"]', file_get_contents(APP_ROOT . '/assets/site.css'), 'png heroes are exempted from the photo crop');

/* ---------------- render_md ---------------- */
section('render_md');
$md = render_md($bySlug['stop-calling-it-legacy-code']);
t_contains('# Stop Calling It Legacy Code', $md, 'md mirror has title');
t_contains('Canonical: https://bfrackowiak.pl/blog/stop-calling-it-legacy-code/', $md, 'md mirror has canonical');
t_contains('Also on Medium: https://medium.com', $md, 'md mirror lists medium link');
$md2 = render_md($bySlug['nobody-owns-the-sum']);
t_not_contains('Also on', $md2, 'no syndication lines when there are none');
$md3 = render_md($bySlug['mathematicians-vs-lawyers']);
t_contains('Also on Medium: https://medium.com', $md3, 'mirror lists the Medium copy');
t_contains('Also on DEV: https://dev.to', $md3, 'mirror lists the DEV copy');

/* ---------------- feeds ---------------- */
section('feeds');
$rss = render_rss(array_slice($posts, 0, 3));
t_assert(simplexml_load_string($rss) !== false, 'rss is valid xml');
t_contains('<atom:link', $rss, 'rss has atom self link');
t_contains('<content:encoded>', $rss, 'rss carries full content');
t_contains(date('D, d M Y', $posts[0]['publish_ts']), $rss, 'rss pubDate from publish moment');
t_contains('<dc:creator>', $rss, 'rss items credit the author');
t_contains('xmlns:dc=', $rss, 'dc namespace declared');
$sm = render_sitemap($posts);
t_assert(simplexml_load_string($sm) !== false, 'sitemap is valid xml');
t_contains(cfg('site_url') . '/blog/mathematicians-vs-lawyers/', $sm, 'sitemap has post url');
t_contains(cfg('site_url') . '/blog/tag/software-architecture/', $sm, 'sitemap has tag url');
$upPost = $posts[0];
$upPost['updated_ts'] = (new DateTimeImmutable('2026-06-01 12:00', site_tz()))->getTimestamp();
t_contains('<lastmod>2026-06-01</lastmod>', render_sitemap([$upPost]), 'sitemap lastmod uses the revision date');

/* ---------------- cards / boxes ---------------- */
section('components');
$card = post_card($posts[1]);
t_contains('class="pcard"', $card, 'card renders without a scroll-reveal class');
t_not_contains('reveal', $card, 'cards never hide until an observer fires');
t_contains('.thumb.jpg', $card, 'card uses thumbnail');
t_not_contains('<a class="tag"', $card, 'no nested anchors inside card link');
$nl = newsletter_box();
t_contains('action="/n.php"', $nl, 'newsletter posts to the self-hosted endpoint');
t_contains('type="email"', $nl, 'newsletter has email field');
t_contains('name="website"', $nl, 'newsletter form carries the honeypot');
/* api.js renders every .cf-turnstile on the page the moment it loads, so two copies
   re-render the widgets the first one already drew. /about/ is the page that found
   this: a subscribe form and a contact form, each asking for the script it needs,
   neither able to know about the other. turnstile_script() therefore emits once per
   request — which is a property of the first call, so this must precede any other
   test that renders a protected form. */
if (turnstile_enabled()) {
    t_contains('turnstile/v0/api.js', $nl, 'the first protected form on a page loads turnstile');
    t_not_contains('turnstile/v0/api.js', newsletter_box(), 'a second form on the same page does not load it again');
}
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
// automation that wears a browser UA: the beacon runs, the clock gives it away
t_assert(an_looks_automated(['tz' => 'UTC', 'screen' => 'laptop', 'lang' => 'en']), 'a browser on a UTC clock is a server');
t_assert(an_looks_automated(['tz' => 'Etc/UTC', 'screen' => 'desktop', 'lang' => 'en']), 'Etc/UTC counts too');
t_assert(an_looks_automated(['tz' => '', 'screen' => '', 'lang' => '']), 'no client context at all = hand-rolled POST or pre-v2 row');
t_assert(!an_looks_automated(['tz' => 'Europe/Warsaw', 'screen' => 'phone', 'lang' => 'pl']), 'a region zone is a real device');
t_assert(!an_looks_automated(['tz' => 'Europe/London', 'screen' => 'laptop', 'lang' => 'en']), 'London is not UTC, whatever the offset says');
t_assert(!an_looks_automated(['tz' => '', 'screen' => 'phone', 'lang' => 'en']), 'a missing timezone alone is not enough');
t_assert(an_looks_automated(['tz' => 'Asia/Shanghai', 'screen' => 'phone', 'os' => 'macOS', 'lang' => 'en']), 'a Mac with a phone-sized screen is an emulated viewport');
t_assert(an_looks_automated(['tz' => 'Europe/Berlin', 'screen' => 'phone', 'os' => 'Windows', 'lang' => 'de']), 'so is Windows on a phone screen');
t_assert(!an_looks_automated(['tz' => 'Europe/Warsaw', 'screen' => 'tablet', 'os' => 'macOS', 'lang' => 'pl']), 'an iPad presents as macOS on a tablet screen and stays counted');
t_assert(!an_looks_automated(['tz' => 'Europe/Warsaw', 'screen' => 'phone', 'os' => 'Android', 'lang' => 'pl']), 'a phone OS on a phone screen is a phone');
t_assert(!an_looks_automated(['tz' => 'Europe/Warsaw', 'screen' => 'laptop', 'os' => 'Linux', 'lang' => 'pl']), 'a desktop OS on a desktop screen is a desktop');
// the clock and the address disagree about the continent: a proxy pool, or a renderer in somebody's datacenter
$auto = fn(array $o): bool => an_looks_automated(array_merge(['tz' => 'Europe/Warsaw', 'country' => 'PL', 'screen' => 'desktop', 'os' => 'Windows', 'lang' => 'en'], $o));
t_assert($auto(['country' => 'US', 'tz' => 'Asia/Bangkok']), 'a US address on a Bangkok clock is a proxy exit');
t_assert($auto(['country' => 'IE', 'tz' => 'America/Los_Angeles', 'os' => 'Android']), 'an Irish address on a California clock is a preview renderer');
t_assert($auto(['country' => 'VE', 'tz' => 'Asia/Rangoon']), 'a deprecated alias still names its continent');
t_assert(!$auto(['country' => 'PL', 'tz' => 'Europe/London']), 'same continent is a VPN exit or a commuter, not a contradiction');
t_assert(!$auto(['country' => 'US', 'tz' => 'Pacific/Honolulu']), 'Hawaii is in the US');
t_assert(!$auto(['country' => 'ES', 'tz' => 'Atlantic/Canary']), 'the Canaries are in Spain');
t_assert(!$auto(['country' => 'RU', 'tz' => 'Asia/Novosibirsk']), 'a country on two continents accepts both');
t_assert(!$auto(['country' => 'IN', 'tz' => 'Asia/Calcutta']), 'the alias Chrome still reports for India matches');
t_assert(!$auto(['country' => '', 'tz' => 'Asia/Bangkok']), 'no country, no contradiction');
t_assert(!$auto(['country' => 'EU', 'tz' => 'Asia/Bangkok']), 'a registry pseudo-code has no zones to disagree with');
t_assert(!$auto(['country' => 'PL', 'tz' => 'Etc/GMT-2']), 'an Etc offset names no continent');
// a Windows PC never has a tablet-width display; an emulated viewport does
t_assert($auto(['country' => 'US', 'tz' => 'America/Los_Angeles', 'screen' => 'tablet']), 'Windows on a tablet-width screen is an emulated viewport');
t_assert(!$auto(['screen' => 'tablet', 'os' => 'Linux']), 'an Android tablet asks for the desktop site as Linux and stays counted');
t_assert(!$auto(['screen' => 'tablet', 'os' => 'ChromeOS']), 'a Chromebook tablet stays counted');

// campaign tags map back to the platform that hid its own referrer
t_eq('blogs', an_utm_group('medium'), 'utm medium tag = blogs');
t_eq('blogs', an_utm_group('devto'), 'utm devto tag = blogs');
t_eq('social', an_utm_group('LinkedIn'), 'utm source matching is case-insensitive');
t_eq('email', an_utm_group('newsletter'), 'newsletter = email');
t_eq('campaign', an_utm_group('conference-flyer'), 'an unknown tag stays a campaign');
t_eq('', an_utm_group(''), 'no tag, no group');

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
foreach (['country', 'browser', 'duration', 'utm_campaign', 'excl'] as $c) {
    t_assert(in_array($c, $cols, true), "migration added column {$c}");
}
t_eq('1', (string) $db->query("SELECT COUNT(*) FROM hits WHERE visitor='legacyvis'")->fetchColumn(), 'v1 rows survive migration');
// a v1 row carries no client context, so the migration's first pass judges it
t_eq(AN_AUTOMATED, (int) $db->query("SELECT excl FROM hits WHERE visitor='legacyvis'")->fetchColumn(), 'migration classifies the history it inherits');

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

// source groups classified at query time; a naked utm tag names its own platform
$rec(['visitor' => 'g1', 'path' => '/sg/', 'ref' => 'www.google.com']);
$rec(['visitor' => 'g1', 'path' => '/sg/', 'ref' => 'news.ycombinator.com']);
$rec(['visitor' => 'g2', 'path' => '/sg/', 'ref' => '', 'utm_source' => 'newsletter']);
$rec(['visitor' => 'g3', 'path' => '/sg/', 'ref' => '', 'utm_source' => 'devto']);
$rec(['visitor' => 'g4', 'path' => '/sg/', 'ref' => '', 'utm_source' => 'flyer']);
$sg = an_source_groups($db, $since, '/sg/');
$byGroup = array_column($sg, null, 'v');
t_eq(1, $byGroup['search']['pv'] ?? 0, 'google classified as search at read time');
t_eq(1, $byGroup['email']['pv'] ?? 0, 'newsletter tag lands in email, not a blanket campaign bucket');
t_eq(1, $byGroup['blogs']['pv'] ?? 0, 'a dev.to click with no referrer is still credited to dev.to');
t_eq(1, $byGroup['campaign']['pv'] ?? 0, 'an unrecognized tag is still a campaign');
t_eq(1, $byGroup['news']['uv'] ?? 0, 'per-group uniques exact');

/* ---------------- exclusion verdicts ---------------- */
section('analytics exclusions');
$exDb = sys_get_temp_dir() . '/bf-unit-analytics-excl-' . getmypid() . '.db';
@unlink($exDb);
$xdb = an_db($exDb);
$xrec = fn(array $o) => an_record($xdb, array_merge([
    'day' => $today, 'ts' => time(), 'path' => '/blog/x/', 'ref' => '', 'visitor' => 'r1',
    'ref_group' => 'direct', 'country' => 'PL', 'browser' => 'Chrome', 'os' => 'Linux',
    'device' => 'desktop', 'lang' => 'pl', 'screen' => 'desktop', 'tz' => 'Europe/Warsaw',
    'utm_source' => '', 'utm_medium' => '', 'utm_campaign' => '',
], $o));
$xrec([]);                                                        // a reader
$xrec(['visitor' => 'h1', 'tz' => 'UTC', 'lang' => 'en']);        // headless, judged on the way in
$xrec(['visitor' => 'h2', 'tz' => 'UTC', 'lang' => 'en']);
$xrec(['visitor' => 'h3', 'os' => 'macOS', 'screen' => 'phone', 'tz' => 'Asia/Shanghai', 'country' => 'CN', 'lang' => 'en']);   // emulated phone under a desktop UA
t_eq(1, an_summary($xdb, $since)['pv'], 'automation is written but never counted');
t_eq(4, (int) $xdb->query('SELECT COUNT(*) FROM hits')->fetchColumn(), 'and is still on disk to look at');
t_eq(4, count(an_recent($xdb, 30)), 'recent activity shows the filtered rows');
$xCounts = an_excl_counts($xdb, $since);
t_eq([AN_COUNTED => 1, AN_AUTOMATED => 3, AN_OWNER => 0], $xCounts, 'verdict split reported for the System pane');

// marking the author's own past browsing: dry by default, exact-match filtered
$dims = ['tz' => 'Europe/Warsaw', 'os' => 'Linux'];
t_eq(1, an_mark($xdb, $today, $today, $dims, AN_OWNER, false), 'dry run counts without writing');
t_eq(1, an_summary($xdb, $since)['pv'], 'and really does not write');
an_mark($xdb, $today, $today, $dims, AN_OWNER, true);
t_eq(0, an_summary($xdb, $since)['pv'], 'owner rows drop out of the totals');
t_eq(0, an_summary($xdb, $since)['live'], 'including live-now');
t_eq(0, count(an_top_pages($xdb, $since)), 'and out of the page table');

// a rescan re-decides automation but never overrules the human verdict
t_eq(0, an_reclassify($xdb), 'a settled table has nothing to change');
$xdb->exec('UPDATE hits SET excl = 0');
t_eq(3, an_reclassify($xdb), 'rescan re-flags the automated rows');
t_eq(AN_COUNTED, (int) $xdb->query("SELECT excl FROM hits WHERE visitor = 'r1'")->fetchColumn(), 'a cleared owner verdict is not re-invented');
an_mark($xdb, $today, $today, $dims, AN_OWNER, true);
$xdb->exec("UPDATE hits SET tz = 'Europe/Warsaw', lang = 'pl' WHERE visitor = 'h1'");
an_reclassify($xdb);
t_eq(AN_COUNTED, (int) $xdb->query("SELECT excl FROM hits WHERE visitor = 'h1'")->fetchColumn(), 'relaxing the rule gives traffic back');
$xdb->exec("UPDATE hits SET screen = 'tablet' WHERE visitor = 'h3'");
an_reclassify($xdb);
t_eq(AN_COUNTED, (int) $xdb->query("SELECT excl FROM hits WHERE visitor = 'h3'")->fetchColumn(), 'a tablet-sized screen on a Mac UA is an iPad, counted again');
// history written under an older rule: the rescan has to read the country too, dry first
$xrec(['visitor' => 'p1', 'country' => 'US', 'tz' => 'Asia/Bangkok', 'excl' => AN_COUNTED]);
t_eq(1, an_reclassify($xdb, false), 'a dry rescan counts the row the clock/address rule now catches');
t_eq(AN_COUNTED, (int) $xdb->query("SELECT excl FROM hits WHERE visitor = 'p1'")->fetchColumn(), 'and writes nothing');
t_eq(1, an_reclassify($xdb), 'the real rescan re-judges it');
t_eq(AN_AUTOMATED, (int) $xdb->query("SELECT excl FROM hits WHERE visitor = 'p1'")->fetchColumn(), 'as automated');
$xdb->exec("DELETE FROM hits WHERE visitor = 'p1'");

// the activity list follows the dashboard range: an old row is history, not "recent"
$xrec(['visitor' => 'old', 'day' => '2020-01-01', 'ts' => 1577836800]);
t_eq(5, count(an_recent($xdb, 30)), 'no range: every row, every verdict');
t_eq(4, count(an_recent($xdb, 30, '', $today)), 'a range hides the older rows');
t_eq(4, an_recent_total($xdb, $today), 'and the total says how many the range holds');
t_eq(5, an_recent_total($xdb), 'no range: the whole table counts');
t_eq(2, count(an_recent($xdb, 2, '', $today)), 'the cap holds inside the range');
t_eq(0, an_recent_total($xdb, $today, '/blog/other/'), 'the page filter still applies');
t_eq('2020-01-01', (string) $xdb->query("SELECT day FROM hits WHERE visitor = 'old'")->fetchColumn(), 'the old row is really there');
t_eq(AN_OWNER, (int) $xdb->query("SELECT excl FROM hits WHERE visitor = 'r1'")->fetchColumn(), 'and leaves the owner verdict alone');
$xdb = null;
@unlink($exDb);

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

/* the admin's country rule, judged on the same tiny database */
section('admin country rule');
t_assert(admin_geo_allowed('83.9.1.2', ['PL'], $gdb), 'a Polish address gets in');
t_assert(!admin_geo_allowed('1.0.0.1', ['PL'], $gdb), 'an Australian one does not');
t_assert(admin_geo_allowed('1.0.0.1', [], $gdb), 'no rule, no wall');
t_assert(admin_geo_allowed('83.9.1.2', ['pl'], $gdb), 'codes are case-insensitive');
t_assert(admin_geo_allowed('192.168.1.1', ['PL'], $gdb), 'a LAN address passes: it cannot come from the internet');
t_assert(admin_geo_allowed('127.0.0.1', ['PL'], $gdb), 'loopback passes (tests, local dev)');
t_assert(!admin_geo_allowed('9.9.9.9', ['PL'], $gdb), 'a public address the database does not know fails closed');
t_assert(!admin_geo_allowed('83.9.1.2', ['PL'], $gdb . '.missing'), 'no database, no entry');
t_assert(!admin_geo_allowed('not-an-ip', ['PL'], $gdb), 'garbage fails closed');
t_assert(admin_geo_allowed('::ffff:83.9.1.2', ['PL'], $gdb), 'a v4-mapped v6 peer is judged as its v4 address');
t_assert(!admin_geo_allowed('::ffff:1.0.0.1', ['PL'], $gdb), 'and does not slip through as "reserved"');
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
$h = password_hash('right-pw', PASSWORD_DEFAULT);
t_assert(admin_credentials_ok('bob', 'right-pw', 'bob', $h), 'right name + right password');
t_assert(!admin_credentials_ok('bob', 'wrong', 'bob', $h), 'wrong password');
t_assert(!admin_credentials_ok('alice', 'right-pw', 'bob', $h), 'right password under the wrong name');
t_assert(!admin_credentials_ok('BOB', 'right-pw', 'bob', $h), 'the name is exact');
t_assert(!admin_credentials_ok('', 'right-pw', '', $h), 'an unconfigured name matches nothing, not even nothing');
t_assert(!admin_credentials_ok('bob', '', 'bob', ''), 'an empty hash verifies nothing');

t_eq('203.0.113.7', admin_net_key('203.0.113.7'), 'a v4 session is pinned to its address');
t_eq(admin_net_key('2001:db8:1:2:aaaa::1'), admin_net_key('2001:db8:1:2:bbbb::2'), 'a v6 session survives a rotating host half');
t_assert(admin_net_key('2001:db8:1:2::1') !== admin_net_key('2001:db8:1:3::1'), 'but not a different /64');

/* per-address lockout: a ladder that a success, or a quiet day, resets */
$fakeIp = '203.0.113.77';
@unlink(admin_rl_file($fakeIp));
t_eq(0, admin_lockout_remaining($fakeIp), 'fresh ip not locked');
$locks = [];
for ($i = 0; $i < ADMIN_MAX_FAILS; $i++) { $l = admin_note_login($fakeIp, false); if ($l) $locks[] = $l; }
t_eq([ADMIN_LOCK_SECS], $locks, 'the fifth miss locks for 15 minutes, the first four lock nothing');
t_assert(admin_lockout_remaining($fakeIp) > 0 && admin_lockout_remaining($fakeIp) <= ADMIN_LOCK_SECS, 'ip locked after max fails');
for ($i = 0; $i < ADMIN_MAX_FAILS; $i++) $l = admin_note_login($fakeIp, false);
t_eq(ADMIN_LOCK_SECS * 4, $l, 'the second lock is four times longer');
for ($i = 0; $i < ADMIN_MAX_FAILS; $i++) $l = admin_note_login($fakeIp, false);
t_eq(ADMIN_LOCK_SECS * 16, $l, 'the third, sixteen');
for ($r = 0; $r < 3; $r++) for ($i = 0; $i < ADMIN_MAX_FAILS; $i++) $l = admin_note_login($fakeIp, false);
t_eq(ADMIN_LOCK_MAX, $l, 'and never more than a day');
admin_note_login($fakeIp, true);
t_eq(0, admin_lockout_remaining($fakeIp), 'successful login clears the lock');
t_eq(0, admin_note_login($fakeIp, false), 'and the history with it: one miss after a success locks nothing');
file_put_contents(admin_rl_file($fakeIp), json_encode(['fails' => 0, 'locks' => 4, 'last' => time() - ADMIN_RESET_SECS - 1]));
for ($i = 0; $i < ADMIN_MAX_FAILS; $i++) $l = admin_note_login($fakeIp, false);
t_eq(ADMIN_LOCK_SECS, $l, 'a day of quiet starts the ladder over');
@unlink(admin_rl_file($fakeIp));

/* site-wide freeze: the real marker is put back afterwards, this is the live content dir */
$floodBak = is_file(admin_flood_file()) ? file_get_contents(admin_flood_file()) : null;
@unlink(admin_flood_file());
t_eq(0, admin_flood_remaining(), 'no freeze on a quiet site');
for ($i = 0; $i < ADMIN_FLOOD_MAX - 1; $i++) admin_note_flood();
t_eq(0, admin_flood_remaining(), 'one short of the limit is still open');
t_eq(ADMIN_FLOOD_LOCK, admin_note_flood(), 'the limit closes the sign-in');
t_assert(admin_flood_remaining() > 0, 'freeze in force');
t_assert(admin_closed_for($fakeIp) > 0, 'an unknown address sits out the freeze');
$own = (array) cfg('admin_trusted_ips', []);
if ($own && is_string($own[0]) && !str_contains($own[0], '/')) {
    t_eq(0, admin_closed_for($own[0]), 'a trusted network (admin_trusted_ips) never sits out the freeze');
}
t_assert(admin_trusted_ip('203.0.113.7', ['203.0.113.7']), 'a listed address is trusted');
t_assert(admin_trusted_ip('203.0.113.99', ['203.0.113.0/24']), 'a listed block is trusted');
t_assert(!admin_trusted_ip('203.0.114.7', ['203.0.113.0/24']), 'the next block over is not');
t_assert(!admin_trusted_ip('', ['203.0.113.7']), 'no address, no trust');
t_assert(!admin_trusted_ip('203.0.113.7', []), 'an empty list trusts no one');
t_assert(!admin_trusted_ip('203.0.113.7', ['garbage', 7]), 'junk entries trust no one');
file_put_contents(admin_flood_file(), json_encode(['t' => array_fill(0, ADMIN_FLOOD_MAX - 1, time() - ADMIN_FLOOD_WINDOW - 5)]));
t_eq(0, admin_note_flood(), 'failures older than the window do not count');
@unlink(admin_flood_file());
if ($floodBak !== null) file_put_contents(admin_flood_file(), $floodBak);

/* sign-in log */
$alog = tempnam(sys_get_temp_dir(), 'alog');
admin_auth_log('fail', '203.0.113.7', ['lock' => 900], $alog);
admin_auth_log('ok', '203.0.113.8', [], $alog);
$rec = admin_auth_recent(10, $alog);
t_eq(2, count($rec), 'two entries logged');
t_eq('ok', $rec[0]['ev'], 'newest first');
t_eq(900, $rec[1]['lock'], 'extra fields kept');
t_eq('203.0.113.7', $rec[1]['ip'], 'the address is kept in the clear');
t_eq('wrong login or password', admin_auth_label('fail'), 'events carry a human label');
unlink($alog);

/* ---------------- one-time codes ---------------- */
section('totp');
$rfc = totp_base32_encode('12345678901234567890');
t_eq('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $rfc, 'base32 encodes the RFC 6238 secret');
t_eq('12345678901234567890', totp_base32_decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'), 'base32 decodes, case and spaces forgiven');
t_eq('', totp_base32_decode('not base32 !'), 'garbage is not a secret');
foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 20000000000 => '353130'] as $t => $want) {
    t_eq($want, totp_code($rfc, $t), "RFC 6238 vector at T={$t}");
}
t_eq(1, totp_verify($rfc, '287082', 59), 'code verifies in its own step');
t_eq(1, totp_verify($rfc, '287082', 59 + 30), 'and one step later (clock drift)');
t_eq(null, totp_verify($rfc, '287082', 59 + 61), 'but not two steps later');
t_eq(null, totp_verify($rfc, '287083', 59), 'wrong digits fail');
t_eq(null, totp_verify($rfc, '28708', 59), 'five digits fail');
t_eq(1, totp_verify($rfc, ' 287 082 ', 59), 'spaces in a typed code are forgiven');
t_eq(null, totp_verify('', '287082', 59), 'no secret, no code');
t_assert(preg_match('/^[A-Z2-7]{32}$/', totp_secret()) === 1, 'fresh secrets are 32 base32 characters');
t_contains('otpauth://totp/bfrackowiak.pl:admin?secret=X&issuer=bfrackowiak.pl', totp_uri('X', 'admin', 'bfrackowiak.pl'), 'enrolment uri shape');
$tl = tempnam(sys_get_temp_dir(), 'tlast');
unlink($tl);
t_assert(admin_totp_fresh(100, $tl), 'a step is accepted the first time');
t_assert(!admin_totp_fresh(100, $tl), 'and never again');
t_assert(!admin_totp_fresh(99, $tl), 'nor an older one');
t_assert(admin_totp_fresh(101, $tl), 'a newer step is fine');
unlink($tl);

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
t_eq('', syndication_badge([]), 'no badge without any syndication');
t_contains('Also published on Medium', syndication_badge(['Medium' => 'https://medium.com/x']), 'badge for Medium');
$twoBadges = syndication_badge(['Medium' => 'https://medium.com/x', 'DevTo' => 'https://dev.to/y']);
t_contains('Also published on DEV', $twoBadges, 'badge for DEV');
t_eq(2, substr_count($twoBadges, '<a href='), 'both platforms get their own link');
t_eq(1, substr_count($twoBadges, '<div class="syndication">'), 'both sit in one row');
$fc = featured_card($posts[0]);
t_contains('class="featured2', $fc, 'featured card class');
t_contains('Latest essay', $fc, 'featured card eyebrow');
t_not_contains('Latest signal', $fc, 'radar jargon is gone from the featured card');

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

section('security headers');
// a fresh process, so header() runs before any output exactly as on a served page
$served = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('?>' . security_headers_prologue() . '<!DOCTYPE html>') . ' 2>&1');
t_eq('<!DOCTYPE html>', $served, 'the prologue prints nothing ahead of the page');
foreach (SECURITY_HEADERS as $h) {
    t_contains(var_export($h, true), security_headers_prologue(), 'prologue sends ' . strstr($h, ':', true));
}
t_contains("frame-ancestors 'self'", implode("\n", SECURITY_HEADERS), 'the CSP is part of the set');
t_not_contains('includeSubDomains', implode("\n", SECURITY_HEADERS), 'HSTS stays off the subdomains (the homelab lives there)');

section('fonts & theme');
$head = page_head('T', 'D', 'https://x/');
t_contains('property="og:locale" content="en_US"', $head, 'og locale set');
t_contains('max-image-preview:large', $head, 'robots meta allows large image previews');
t_contains('property="og:type" content="website"', $head, 'default og:type is website');
t_contains('/llms.txt', $head, 'llms.txt advertised in every head');
$headArt = page_head('T', 'D', 'https://x/', '<meta property="og:type" content="article">');
t_eq(1, substr_count($headArt, 'og:type'), 'article og:type suppresses the website default');
$headNoIdx = page_head('T', 'D', 'https://x/', '<meta name="robots" content="noindex, follow">');
t_eq(1, substr_count($headNoIdx, 'name="robots"'), 'noindex extra suppresses the default robots meta');
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

/* The site gutter. Any rule that also lands on a .wrap element must set padding
   per side: a `padding:` shorthand there silently zeroes the inline padding and
   the text runs into the edge of a phone screen — invisible on a desktop, where
   the max-width keeps content off the edge anyway. */
$wrapUsers = ['.hero'];   // classes used together with .wrap in the markup
foreach ($wrapUsers as $sel) {
    preg_match('~\\' . $sel . ' \{([^}]*)\}~', $css, $rule);
    t_assert(isset($rule[1]), "{$sel} rule found in site.css");
    t_assert(!preg_match('~(^|;|\s)padding:~', $rule[1] ?? ''), "{$sel} does not use a padding shorthand over the .wrap gutter");
}
t_assert(preg_match('~\.wrap \{[^}]*padding-left:[^}]*padding-right:~s', $css) === 1, '.wrap sets its gutter per side');
t_contains('env(safe-area-inset-left)', $css, 'gutters respect the notch in landscape');
t_contains('text-size-adjust: 100%', $css, 'iOS is told not to inflate text in landscape');
t_contains('scroll-padding-top', $css, 'anchor targets clear the sticky nav');
$svcCss = (string) file_get_contents(dirname(__DIR__) . '/services/index.php');
t_assert(!preg_match('~\.svc-hero \{[^}]*(^|;|\s)padding:~m', $svcCss), 'services hero keeps the .wrap gutter too');

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
// the soft door leads to the person, not to a price list: nothing on a reading
// page points at the commercial page any more
t_contains('/about/', $sp, 'soft door to About present');
t_not_contains('/services/', $sp, 'start page never links the commercial page');
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
// image is a single object for a hero-only post and a list once the post has
// diagrams; either way the hero leads and carries its dimensions
$bpHero = $bp['image'][0] ?? $bp['image'];
t_assert(isset($bpHero['width'], $bpHero['height']), 'posting image has dimensions');
t_eq($posts[0]['publish_iso'], $bp['datePublished'], 'posting datePublished = publish moment');
// the fallback needs a post that carries no **Updated:** line, which the newest
// post is not guaranteed to be: a revision pass adds one to everything it edits
$noUpd = null;
foreach ($posts as $p) if ($p['updated_ts'] === null) { $noUpd = $p; break; }
t_assert($noUpd !== null, 'corpus still has an unrevised post to test the fallback with');
if ($noUpd !== null) {
    $bpNo = schema_blog_posting($noUpd, null, post_url($noUpd['slug']), $noUpd['publish_iso']);
    t_eq($noUpd['publish_iso'], $bpNo['dateModified'], 'dateModified falls back to the publish moment');
}
t_contains('linkedin.com', implode(',', $bp['author']['sameAs']), 'article author entity carries sameAs');
t_eq('Solution Architect', $bp['author']['jobTitle'], 'article author entity carries jobTitle');
$pu = $posts[0];
$pu['updated_iso'] = '2026-06-01T12:00:00+02:00';
t_eq('2026-06-01T12:00:00+02:00', schema_blog_posting($pu, null, 'https://x/', $pu['publish_iso'])['dateModified'], 'dateModified uses the Updated meta');
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
t_contains('/blog/start/', $sm0, 'empty-archive sitemap still lists static pages');
t_contains('/about/', $sm0, 'about page is in the sitemap');
t_not_contains('/services/', $sm0, 'the unlisted commercial page stays out of the sitemap');
t_not_contains('<loc>https://bfrackowiak.pl/blog/</loc>', $sm0, 'no second entry for the old listing URL');
t_assert(simplexml_load_string($sm0) !== false, 'empty-archive sitemap is valid XML');

// RSS: ']]>' inside content cannot break the CDATA envelope
$rssPost = ['slug' => 'cdata', 'title' => 'C', 'subtitle' => 's', 'html' => '<p>evil ]]> here</p>',
            'publish_ts' => 1700000000, 'publish_iso' => date('c', 1700000000), 'minutes' => 1, 'tags' => []];
t_assert(simplexml_load_string(render_rss([$rssPost])) !== false, 'CDATA breakout escaped, feed stays valid');
// /blog/ is a 301 to the root; pointing crawlers at it lands it in Search Console's redirect bucket
t_contains('<link>https://bfrackowiak.pl/</link>', render_rss([$rssPost]), 'feed channel links the root, not the old listing URL');
t_not_contains('bfrackowiak.pl/blog/ ', render_llms_full([$rssPost]), 'llms-full names the root as the canonical listing');

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

/* ---------------- contact form + captcha ---------------- */
section('contact & captcha');
require_once dirname(__DIR__) . '/lib/contact.php';

$SRV = $_SERVER; $G = $_GET; $P = $_POST;
$_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = []; $_POST = [];
t_eq(['sent' => false, 'error' => false, 'message' => ''], handle_contact_form(), 'plain GET is a no-op');
$_GET = ['sent' => '1'];
t_eq(true, handle_contact_form()['sent'], 'PRG landing (?sent=1) reports success');
$_GET = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['contact_submit' => '1', 'name' => '', 'email' => 'x', 'subject' => '', 'message' => ''];
$r = handle_contact_form();
t_eq(true, $r['error'], 'missing fields rejected');
t_contains('valid email', $r['message'], 'validation message is actionable');
$_SERVER = $SRV; $_GET = $G; $_POST = $P;

if (turnstile_enabled()) {
    t_contains('data-sitekey=', turnstile_widget('x'), 'widget carries the sitekey');
    t_contains('data-action="a&quot;b"', turnstile_widget('a"b'), 'widget action attribute is escaped');
} else {
    t_eq(false, turnstile_enabled(), 'turnstile disabled without keys');
}

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

// admin dashboard queries: fixed clock, hand-planted timestamps
$NOW = strtotime('2026-07-20 12:00:00');   // a Monday
$st0 = nl_stats($ndb, $NOW);
t_eq(0, $st0['confirmed'], 'stats on an empty list: zero confirmed');
t_eq(null, $st0['confirm_rate'], 'confirm rate unknowable on an empty list');
t_eq(null, $st0['median_confirm_secs'], 'no median without a single confirm');
$ins = $ndb->prepare('INSERT INTO subscribers (email, status, token, created_ts, confirmed_ts) VALUES (?, ?, ?, ?, ?)');
$ins->execute(['old@example.com',  'confirmed', str_repeat('a', 32), $NOW - 100 * 86400, $NOW - 99 * 86400]);
$ins->execute(['prev@example.com', 'confirmed', str_repeat('b', 32), $NOW - 40 * 86400,  $NOW - 35 * 86400]);
$ins->execute(['new@example.com',  'confirmed', str_repeat('c', 32), $NOW - 10 * 86400,  $NOW - 9 * 86400]);
$ins->execute(['wait@example.com', 'pending',   str_repeat('d', 32), $NOW - 2 * 86400,   null]);
$st = nl_stats($ndb, $NOW);
t_eq(3, $st['confirmed'], 'stats count confirmed');
t_eq(1, $st['pending'], 'stats count pending');
t_eq(75, $st['confirm_rate'], 'confirm rate = confirmed / all addresses');
t_eq(1, $st['new30'], 'only the last-30-days confirm lands in new30');
t_eq(1, $st['prev30'], 'the 30 days before that counted separately');
t_eq(86400, $st['median_confirm_secs'], 'median confirm lag (lags 1d, 5d, 1d → 1d)');
t_eq(1, $st['essays_mailed'], 'baseline seeds excluded from essays mailed');
t_eq('unit-essay', $st['last_send']['slug'], 'last send is the latest real send');
$ndb->exec("INSERT INTO sends (slug, sent_ts, recipients, note) VALUES ('inflight', " . ($NOW + 60) . ", 0, 'sending')");
t_eq('unit-essay', nl_stats($ndb, $NOW)['last_send']['slug'], 'an in-flight send is not "last send" yet');
$ndb->exec("DELETE FROM sends WHERE slug = 'inflight'");

$wkb = nl_weekly($ndb, 4, $NOW);
t_eq(4, count($wkb), 'weekly growth: one bucket per requested week');
t_eq('2026-07-20', end($wkb)['week'], 'last bucket is the current ISO week (Monday)');
t_eq(1, array_sum(array_column($wkb, 'confirmed')), 'a confirm counts in its confirm week only');
t_eq(1, array_sum(array_column($wkb, 'pending')), 'a still-pending signup counts in its signup week');

$all = nl_all($ndb);
t_eq(4, count($all), 'nl_all lists every subscriber');
t_eq('wait@example.com', $all[0]['email'], 'newest signup first');
$sl = nl_sends($ndb);
t_eq(2, count($sl), 'send ledger listed in full, baseline included');
t_eq('unit-essay', $sl[0]['slug'], 'newest send first');

t_eq('—', nl_fmt_span(null), 'span: null renders as em dash');
t_eq('42s', nl_fmt_span(42), 'span: seconds');
t_eq('18m', nl_fmt_span(1130), 'span: minutes');
t_eq('3h 20m', nl_fmt_span(12000), 'span: hours');
t_eq('2d 5h', nl_fmt_span(2 * 86400 + 5 * 3600 + 60), 'span: days');

// smtp guard: with no configured password the sender must refuse quietly
$smtpCfg = (array) cfg('smtp', []);
if (($smtpCfg['pass'] ?? '') === '') {
    t_assert(!nl_smtp_send('x@example.com', 's', 'h', 't', 'https://u'), 'smtp disabled without a password');
} else {
    t_assert(true, 'smtp guard skipped (live credentials present — not sending from tests)');
}

$ndb = null;
@unlink($nlTmp); @unlink($nlTmp . '-wal'); @unlink($nlTmp . '-shm');


/* ---------------- review: block addressing ---------------- */
section('review_blocks');
require_once APP_ROOT . '/lib/review_blocks.php';
require_once APP_ROOT . '/lib/review_store.php';

$rbFix = [
    '# A Title',
    '',
    '*A subtitle in italics.*',
    '',
    'First paragraph.',
    '',
    '## A heading',
    '',
    '- one',
    '- two',
    '',
    '> quoted',
    '',
    '```',
    '## not a heading',
    '```',
    '',
    '![Cap.](/x.png)',
    '',
    '**Tags:** A, B',
];
$rb = rb_blocks($rbFix);
$types = array_column($rb['blocks'], 'type');
t_eq(['p', 'h2', 'ul', 'quote', 'code', 'figure'], $types, 'every block kind is recognised in order');
t_eq(4, $rb['blocks'][0]['from'], 'the first paragraph keeps its source line');
t_eq([8, 9], [$rb['blocks'][2]['from'], $rb['blocks'][2]['to']], 'a list spans its own lines');
t_assert($rb['blocks'][4]['editable'] === false, 'a code fence is never editable');
t_assert($rb['blocks'][5]['editable'] === false, 'a figure is never editable');
t_assert($rb['blocks'][0]['editable'] === true, 'a paragraph is editable');
foreach ($rb['blocks'] as $b) {
    t_not_contains('**Tags:**', $b['md'], 'meta never becomes an editable block');
}
t_not_contains('A subtitle', implode(' ', array_column($rb['blocks'], 'md')), 'the subtitle is not a block');
t_eq('one two', rb_plain("- one\n- two"), 'rb_plain strips list markers');
t_eq('bold and code and link', rb_plain('**bold** and `code` and [link](/x/)'), 'rb_plain unwraps inline markup');

// a surgical write: one line replaced, every other byte identical
$rbTmp = CONTENT_DIR . '/.rbtest.md';
file_put_contents($rbTmp, implode("\n", $rbFix));
$before = file_get_contents($rbTmp);
$blk = rb_find(rb_load($rbTmp), 'b0');
t_eq('', rb_write_block($rbTmp, 'b0', 'Rewritten paragraph.', rb_hash($blk['md'])), 'a block write succeeds');
$after = file_get_contents($rbTmp);
t_eq(substr_count($before, "\n"), substr_count($after, "\n"), 'the write changes no line count');
t_contains('Rewritten paragraph.', $after, 'the new text landed');
t_contains('**Tags:** A, B', $after, 'the meta block is untouched');
t_contains('## not a heading', $after, 'the code fence is untouched');
t_eq('stale', rb_write_block($rbTmp, 'b0', 'again', rb_hash($blk['md'])), 'a stale hash is refused');
t_eq('block is not editable', rb_write_block($rbTmp, 'b4', 'x', ''), 'a code fence refuses to be written');
t_eq('refusing to empty a block', rb_write_block($rbTmp, 'b1', '  ', ''), 'a block cannot be emptied');
$blk2 = rb_find(rb_load($rbTmp), 'b1');
rb_write_block($rbTmp, 'b1', "## Two\nlines", rb_hash($blk2['md']));
t_not_contains("## Two\nlines", file_get_contents($rbTmp), 'a single-line block cannot smuggle in a newline');
@unlink($rbTmp);

/* ---------------- review: notes and anchoring ---------------- */
section('review_store');
$rvSlug = 'unit-review-fixture';
@unlink(rv_path($rvSlug));
$rvParsed = rb_blocks(['# T', '', 'The quick brown fox jumps over the lazy dog.']);
$sel = ['block' => 'b0', 'quote' => 'brown fox', 'prefix' => 'The quick ', 'suffix' => ' jumps'];

t_eq(null, rv_add($rvSlug, $sel, '   '), 'an empty note body is refused');
t_eq(null, rv_add($rvSlug, ['quote' => ''], 'body'), 'a note with no quote is refused');
$note = rv_add($rvSlug, $sel, 'tighten this');
t_assert(is_array($note) && $note['status'] === 'open', 'a note is stored open');

$located = rv_reanchor(rv_load($rvSlug)['comments'], $rvParsed);
t_eq('b0', $located[0]['anchor']['block'], 'a fresh note anchors to its own block');
t_eq(10, $located[0]['anchor']['start'], 'the anchor lands on the quoted text');

// the sentence moves: the note follows it rather than pointing at the old offset
$moved = rb_blocks(['# T', '', 'Something else entirely first. The quick brown fox jumps on.']);
$after = rv_reanchor(rv_load($rvSlug)['comments'], $moved);
t_eq(41, $after[0]['anchor']['start'], 'a note re-anchors after the text around it changes');

// the sentence goes: the note is orphaned rather than matched to something else
$gone = rb_blocks(['# T', '', 'Nothing remotely similar remains in this paragraph.']);
$orph = rv_reanchor(rv_load($rvSlug)['comments'], $gone);
t_eq(null, $orph[0]['anchor'], 'a note whose text is deleted is orphaned, never guessed');

t_assert(rv_update($rvSlug, $note['id'], ['status' => 'resolved']), 'a note resolves');
t_eq(1, rv_counts($rvSlug, $rvParsed)['resolved'], 'counts follow the status');
t_assert(rv_update($rvSlug, $note['id'], ['status' => 'open']), 'a note reopens');
t_eq(null, rv_load($rvSlug)['comments'][0]['resolution'], 'reopening clears the resolution note');
t_assert(!rv_update($rvSlug, 'nope', ['body' => 'x']), 'patching an unknown note reports failure');
t_assert(rv_delete($rvSlug, $note['id']), 'a note deletes');
t_eq([], rv_load($rvSlug)['comments'], 'the thread is empty again');
@unlink(rv_path($rvSlug));

// review.php must never answer to a visitor
t_contains('admin_session_active', file_get_contents(APP_ROOT . '/review.php'), 'review.php gates on an admin session');
t_contains('admin_csrf_check', file_get_contents(APP_ROOT . '/review.php'), 'every review write checks CSRF');

/* ---------------- root favicon ---------------- */
/* Browsers, bookmark tools and crawlers ask for /favicon.ico whatever the head
   says; 199 of the last 90 days' 404s were exactly that, each one rendering the
   branded 404 page for nothing. The file is built from favicon-32.png:
   magick favicon-32.png -define icon:auto-resize=32,16 favicon.ico */
section('root favicon');
$ico = APP_ROOT . '/favicon.ico';
t_assert(is_file($ico), 'favicon.ico exists at the site root');
t_eq("\x00\x00\x01\x00", (string) substr((string) @file_get_contents($ico), 0, 4), 'and is a real ICO container');
t_contains('image/x-icon', (string) file_get_contents(APP_ROOT . '/.htaccess'), 'and is cached like the other images');


/* ---------------- homepage retention (branch homepage-retention) ---------------- */
section('homepage: hero and jargon');
$home = render_index($posts);
t_contains('src="/myself.jpeg"', $home, 'homepage hero shows the author');
t_not_contains('co-lead', $home, 'homepage hero is about the writing, not the job title');
t_contains('class="home-promise"', $home, 'homepage promises what a reader gets');
t_contains('href="/about/#contact"', $home, 'homepage hero links to contact');
t_contains('href="#newsletter"', $home, 'homepage hero links to the email subscription');
t_not_contains('href="/feed.xml"', $home, 'no visible RSS link on the homepage');
t_not_contains('>RSS<', $home, 'no RSS label on the homepage');
t_not_contains('href="/feed.xml"', site_footer(), 'no RSS link in the footer');
t_contains('href="/about/#contact"', site_footer(), 'footer keeps the contact link');
t_contains('class="btn btn-primary" href="/blog/start/"', $home, 'Start here is a real button');
t_contains('class="proof"', $home, 'proof chips moved onto the homepage');
t_not_contains('new signal most Mondays', $home, 'no "new signal" in the meta line');
t_not_contains('Latest signal', $home, 'no "Latest signal" eyebrow');
t_not_contains('>The signal<', $home, 'no "The signal" eyebrow on the newsletter box');
t_contains('A new essay most Mondays', site_footer(), 'footer speaks plain English');
t_not_contains('New signal', site_footer(), 'footer lost the radar jargon');
if (($nlb = newsletter_box()) !== '') {
    t_contains('>Newsletter<', $nlb, 'newsletter eyebrow is the word Newsletter');
}
t_contains('Newest essays', render_404_page($posts), '404 lists newest essays, not signals');
$start = render_start_page($posts);
t_contains('co-lead', $start, 'start page says co-lead');
t_not_contains('currently leading', $start, 'start page no longer claims to lead alone');

section('homepage: doors, most read, archive');
$paths = reading_paths();
t_eq(3, count($paths), 'three reading paths');
foreach ($paths as $d) t_eq(3, count($d['reads']), 'each path names three essays');
t_contains('class="doors"', $home, 'the three doors render on the homepage');
t_contains(e($paths[0]['title']), $home, 'first door title on the homepage');
t_contains(e($paths[0]['title']), $start, 'start page still renders the same doors');
t_not_contains('Most read', $home, 'no most-read block on the homepage');
t_assert(!function_exists('most_read_block'), 'the most-read component is gone with it');
// the intro is two columns: who and what on the left, what a reader gets on the right
t_contains('class="home-hero"', $home, 'hero wrapper present');
t_contains('class="hero-main"', $home, 'hero left column');
t_contains('class="hero-side"', $home, 'hero right column');
$side = substr($home, strpos($home, 'class="hero-side"'));
t_contains('class="home-promise"', substr($side, 0, 2000), 'the promise sits in the right column');
$main = substr($home, strpos($home, 'class="hero-main"'), strpos($home, 'class="hero-side"') - strpos($home, 'class="hero-main"'));
t_contains('Start here', $main, 'the buttons sit in the left column');
t_contains('src="/myself.jpeg"', $main, 'the face sits in the left column');
t_eq(HOME_LATEST_COUNT, 10, 'the homepage shows the latest ten');
$latestBlock = substr($home, strpos($home, 'Latest ' . HOME_LATEST_COUNT));
t_eq(0, substr_count($home, '<li class="arow">'), 'no text-only archive rows on the homepage');
t_contains('href="/blog/archive/"', $home, 'homepage links to the full archive');
t_contains('class="thumb', $latestBlock, 'latest essays carry small thumbnails');
$arch = render_archive_page($posts);
t_eq(count($posts), substr_count($arch, '<li><a href="/blog/'), 'archive page lists every published essay');
t_contains('class="thumb', $arch, 'archive page rows carry thumbnails');
t_contains('rel="canonical" href="' . cfg('site_url') . '/blog/archive/"', $arch, 'archive page is canonical at /blog/archive/');
t_contains(cfg('site_url') . '/blog/archive/', render_sitemap($posts), 'archive page is in the sitemap');
t_not_contains('class="pcard', $home, 'no magazine grid on the homepage');
t_not_contains('class="featured2 rise-2"', $home, 'featured card no longer waits for a load-in animation');
t_not_contains('reveal', post_card($posts[0]), 'cards no longer hide until scrolled into view');

section('series');
t_contains('Reviewability, part 1', series_suffix('the-bottleneck-moved'), 'bottleneck opens the reviewability series');
t_eq(['the-bottleneck-moved', 'we-deleted-the-wrong-half-of-agile'], series_slugs('the-bottleneck-moved'), 'series slugs in order');
t_eq([], series_slugs('mathematicians-vs-lawyers'), 'no series, no slugs');
$allPosts = load_posts(true);
$nav = series_nav('we-deleted-the-wrong-half-of-agile', $allPosts);
t_contains('class="series-nav"', $nav, 'series nav renders for a series post');
t_contains('aria-current="page"', $nav, 'series nav marks the current part');
t_contains('The Bottleneck Moved', $nav, 'series nav lists the sibling part');
t_eq('', series_nav('mathematicians-vs-lawyers', $allPosts), 'no series nav outside a series');
t_contains('Reviewability, part 1', $arch, 'archive rows carry the series label');
$bySlug = array_column($allPosts, null, 'slug');
t_contains('class="series-nav"', render_post($bySlug['the-bottleneck-moved'], null, null, $posts), 'post page carries the series nav');

section('list thumbnails are always the main image of the post');
foreach (['a-feature-is-not-a-service', 'the-bottleneck-moved', 'turn-the-sequence-diagram-on-its-side', 'mathematicians-vs-lawyers'] as $slug) {
    $h = hero_meta($slug);
    $row = log_rows([$bySlug[$slug]]);
    t_contains('src="' . $h['thumb'] . '"', $row, "{$slug}: row uses the hero thumbnail");
    t_not_contains('thumb-figure', $row, "{$slug}: no diagram thumbnail");
}
t_assert(!function_exists('figure_thumb_meta'), 'the diagram-thumbnail lookup is gone');
t_not_contains('thumb-figure', (string) file_get_contents(APP_ROOT . '/assets/site.css'), 'and so is its styling');

section('post page: byline, badges, related');
$css = (string) file_get_contents(APP_ROOT . '/assets/site.css');
t_contains('width: 100%; aspect-ratio: 21 / 9; object-fit: cover;', $css, 'post hero is the full 21:9 picture again');
t_not_contains('clamp(180px, 24vw, 320px)', $css, 'the strip hero is gone');
$pp = render_post($bySlug['the-bottleneck-moved'], null, null, $posts);
t_contains('class="byline"', $pp, 'byline under the title');
t_assert(strpos($pp, 'class="byline"') < strpos($pp, '<article'), 'byline sits before the article');
t_contains('href="/about/"', substr($pp, 0, strpos($pp, '<article')), 'byline links to About');
$legacyPage = render_post($bySlug['stop-calling-it-legacy-code'], null, null, $posts);
t_assert(strpos($legacyPage, 'class="syndication"') > strpos($legacyPage, '</article>'), 'syndication badges moved below the article');
t_contains('data-appearance="interaction-only"', turnstile_widget('newsletter'), 'turnstile stays invisible unless it needs a click');
t_contains('srcset=', featured_card($posts[0]), 'featured image ships a srcset');
t_contains('sizes=', featured_card($posts[0]), 'and sizes');
$rp = parse_post_file($mkpost('2020-01-05-fixture-related.md', '**Related:** nobody-owns-the-sum, data-driven-theater'));
t_eq(['nobody-owns-the-sum', 'data-driven-theater'], $rp['related'], 'Related meta parses into slugs');
t_eq([], parse_post_file($mkpost('2020-01-05-fixture-norelated.md'))['related'], 'no Related meta = empty list');
$manual = ['slug' => 'manual', 'tags' => ['Nothing Shared'], 'publish_ts' => 1, 'related' => ['data-driven-theater', 'no-such-post', 'nobody-owns-the-sum']];
t_eq(['data-driven-theater', 'nobody-owns-the-sum'], array_column(related_posts($manual, $posts), 'slug'), 'manual related keeps author order and skips unknown slugs');
$auto = ['slug' => 'auto', 'tags' => ['Software Architecture'], 'publish_ts' => 1, 'related' => []];
t_eq(3, count(related_posts($auto, $posts)), 'without a manual list, tags still fill the block');
t_contains('Picked by the author', $pp, 'manually related essays are labelled as picked');


section('one story about the author on three pages');
$homeX = render_index($posts);
$startX = render_start_page($posts);
$aboutSrc = (string) file_get_contents(APP_ROOT . '/about/index.php');
foreach (['15+ years', 'PhD', 'Top 3% on Toptal', 'global SaaS', 'people left'] as $fact) {
    t_contains($fact, $homeX, "homepage tells: {$fact}");
    t_contains($fact, $startX, "start page tells: {$fact}");
    t_contains($fact, $aboutSrc, "about page tells: {$fact}");
}
// one number everywhere, matching LinkedIn: 15+
foreach (['12+', 'welve'] as $old) {
    t_not_contains($old, $homeX, "homepage has no old years count ({$old})");
    t_not_contains($old, $startX, "start page has no old years count ({$old})");
    t_not_contains($old, $aboutSrc, "about page has no old years count ({$old})");
    t_not_contains($old, (string) file_get_contents(APP_ROOT . '/llms.txt'), "llms.txt has no old years count ({$old})");
    t_not_contains($old, (string) file_get_contents(APP_ROOT . '/services/index.php'), "services page has no old years count ({$old})");
}
t_not_contains(' — ', substr($startX, strpos($startX, 'start-intro'), 1500), 'start intro has no spaced em dash');

section('doors carry a picture of what they are');
$kinds = array_column(reading_paths(), 'art');
t_eq(['architecture', 'ai', 'open'], $kinds, 'one door per kind: architecture, AI, built in the open');
t_eq(3, substr_count($homeX, 'class="door-art'), 'every homepage door has an illustration');
t_eq(3, substr_count($startX, 'class="door-art'), 'every start-page path has the same illustration');
foreach (['Architecture', 'AI', 'Built in the open'] as $label) {
    t_contains('aria-label="' . $label . '"', $homeX, "door picture is labelled {$label}");
}
t_contains('<svg', door_art('ai'), 'door art is an inline drawing');
t_eq('', door_art('nope'), 'unknown door kind draws nothing');


section('door label above the picture');
$doorsHtml = doors_block($posts);
foreach (explode('<section class="door">', $doorsHtml) as $i => $door) {
    if ($i === 0) continue;
    t_assert(strpos($door, 'class="k"') !== false && strpos($door, 'class="k"') < strpos($door, 'class="door-art'), "door {$i}: label sits above the picture");
}

if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'unit.php') exit(t_summary());
