<?php
/**
 * tests/unit.php — unit tests for tools/build.php and comments.php functions.
 * Run via tests/run.php (or standalone: php tests/unit.php).
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

define('BUILD_NO_RUN', true);
require dirname(__DIR__) . '/tools/build.php';
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
    ['tags' => ['A', 'B']],
    ['tags' => ['A']],
]);
t_eq(['A', 'B'], array_keys($tags), 'collect_tags sorts by count desc');
t_eq(2, count($tags['A']), 'tag groups posts');

/* ---------------- dates ---------------- */
section('dates');
t_eq('2026.07.13', log_date('2026-07-13'), 'log date format');
t_eq('Jul 13, 2026', nice_date('2026-07-13'), 'nice date format');

/* ---------------- load_posts against real content ---------------- */
section('load_posts');
$posts = load_posts();
t_eq(23, count($posts), '23 posts loaded');
t_eq('mathematicians-vs-lawyers', $posts[0]['slug'], 'newest post first');
$sorted = true;
for ($i = 1; $i < count($posts); $i++) if ($posts[$i - 1]['date'] < $posts[$i]['date']) $sorted = false;
t_assert($sorted, 'posts sorted descending by date');
$allOk = true;
foreach ($posts as $p) {
    if ($p['title'] === '' || $p['subtitle'] === '' || count($p['tags']) !== 5 || $p['minutes'] < 1 || $p['html'] === '') {
        $allOk = false;
        echo "    problem post: {$p['slug']}\n";
    }
}
t_assert($allOk, 'every post has title, subtitle, exactly 5 tags, minutes, html');
$bySlug = array_column($posts, null, 'slug');
foreach (['stop-calling-it-legacy-code', 'the-automerger', 'technical-leader-identity-disorder'] as $s) {
    t_assert(!empty($bySlug[$s]['medium']), "medium link parsed for {$s}");
}
t_eq(null, $bySlug['mathematicians-vs-lawyers']['medium'], 'no medium link where none declared');
t_not_contains('**Tags:**', $bySlug['data-driven-theater']['html'], 'tags line stripped from body');
t_not_contains('**Medium:**', $bySlug['the-automerger']['html'], 'medium line stripped from body');
t_not_contains($bySlug['data-driven-theater']['subtitle'], $bySlug['data-driven-theater']['html'], 'subtitle not duplicated into body');
$noDash = true;
foreach ($posts as $p) {
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
t_contains('buttondown.com/api/emails/embed-subscribe', $nl, 'newsletter posts to buttondown');
t_contains('type="email"', $nl, 'newsletter has email field');

/* ---------------- comments: pure functions ---------------- */
section('comments');
t_eq('j***@example.com', c_mask_email('john@example.com'), 'email masked');
t_eq('anonymous', c_mask_email('not-an-email'), 'invalid email masks to anonymous');
t_assert(c_valid_slug('mathematicians-vs-lawyers'), 'real slug validates');
t_assert(!c_valid_slug('tag'), 'tag pseudo-slug rejected');
t_assert(!c_valid_slug('../../etc/passwd'), 'traversal rejected');
t_assert(!c_valid_slug('no-such-post'), 'unknown slug rejected');

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

if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'unit.php') exit(t_summary());
