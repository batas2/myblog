<?php
/**
 * lib/pages.php — full page renderers: article pages, the blog index,
 * tag archives, and the homepage latest-posts partial.
 */

declare(strict_types=1);

/** Shared scaffold for listing pages (blog index, tag archives). */
function listing_page(string $title, string $desc, string $canonical, string $extra, string $header, string $content): string
{
    $html  = page_head($title, $desc, $canonical, $extra);
    $html .= site_nav('blog');
    $html .= '<main class="wrap">';
    $html .= '<div class="section-head blog-hero rise">' . $header . '</div>';
    $html .= $content;
    $html .= '</main>';
    $html .= reveal_script();
    return $html . site_footer();
}

/** Article page: SEO head, hero, syndication badge, prose, tags, pager, related, author, comments. */
function render_post(array $p, ?array $prev, ?array $next, array $all = []): string
{
    $url = post_url($p['slug']);
    $hero = hero_meta($p['slug']);
    $published = $p['publish_iso'];

    $extra  = ld_script(schema_blog_posting($p, $hero, $url, $published)) . "\n";
    // the blog is the site root now, so "Home" and "Field Notes" would be the
    // same URL twice — one crumb, not two
    $extra .= ld_script(schema_breadcrumbs([
        'Field Notes' => cfg('site_url') . '/',
        $p['title'] => $url,
    ])) . "\n";
    $extra .= '<meta property="og:type" content="article">' . "\n";
    $extra .= '<meta property="article:published_time" content="' . $published . '">' . "\n";
    if ($p['updated_iso'] ?? null) {
        $extra .= '<meta property="article:modified_time" content="' . $p['updated_iso'] . '">' . "\n";
    }
    $extra .= '<meta property="article:author" content="' . e(cfg('author')) . '">' . "\n";
    foreach ($p['tags'] as $t) $extra .= '<meta property="article:tag" content="' . e($t) . '">' . "\n";
    $extra .= '<link rel="alternate" type="text/markdown" href="' . $url . 'index.md" title="Markdown source">' . "\n";
    foreach ($p['syndication'] as $syndUrl) {
        $extra .= '<link rel="syndication" href="' . e($syndUrl) . '">' . "\n";
    }
    if ($hero) {
        // the card, not the hero: they are the same file unless the post ships a `.og.jpg`
        $extra .= '<meta property="og:image:width" content="' . $hero['cw'] . '">' . "\n";
        $extra .= '<meta property="og:image:height" content="' . $hero['ch'] . '">' . "\n";
        $extra .= '<meta property="og:image:alt" content="' . e($p['title']) . '">' . "\n";
        $extra .= '<link rel="preload" as="image" href="' . e($hero['src']) . '" fetchpriority="high">' . "\n";
    }

    $html  = page_head($p['title'] . ' · Bartosz Frąckowiak', $p['subtitle'], $url, $extra,
                       $hero ? cfg('site_url') . $hero['card'] : (string) cfg('og_image'));
    $html .= site_nav('blog');

    $html .= '<main class="article-body">';
    $html .= '<div class="article-head rise">';
    $html .= '<a class="backlink" href="/">&larr; All field notes</a>';
    $html .= '<h1>' . e($p['title']) . '</h1>';
    if ($p['subtitle']) $html .= '<p class="dek">' . e($p['subtitle']) . '</p>';
    $html .= '<p class="meta" style="margin-top:1.4rem">' . post_meta_line($p) . '</p>';
    $html .= '<div class="rule"></div></div>';

    if ($hero) $html .= hero_figure($hero, $p['title']);
    $html .= syndication_badge($p['syndication']);

    $html .= '<article class="prose rise-2">' . $p['html'] . '</article>';

    if ($p['tags']) {
        $html .= '<div class="tag-row">';
        foreach ($p['tags'] as $t) $html .= tag_link($t);
        $html .= '</div>';
    }

    if ($prev || $next) {
        $html .= '<nav class="pager">';
        $html .= $prev ? pager_card($prev, 'prev', '&larr; Earlier') : '<span></span>';
        if ($next) $html .= pager_card($next, 'next', 'Later &rarr;');
        $html .= '</nav>';
    }

    // keep the reader in the archive: closest essays by shared tags
    if ($all && ($rel = related_posts($p, $all))) {
        $html .= '<div class="related-wrap">';
        $html .= '<div class="related-head">' . eyebrow('Keep reading') . '<h2>Related field notes</h2></div>';
        $html .= log_rows($rel);
        $html .= '</div>';
    }

    $html .= author_box();
    $html .= '<div class="newsletter-wrap">' . newsletter_box() . '</div>';

    // the one dynamic island on an otherwise static page: file-based comments
    $html .= "\n<?php \$COMMENT_SLUG = '" . $p['slug'] . "'; include dirname(__DIR__, 2) . '/comments.php'; ?>\n";
    $html .= '</main>';

    return $html . site_footer();
}

/**
 * The home page, which is the blog: featured newest essay + magazine grid +
 * newsletter. Built to blog/_home.php and included by the tracked index.php at
 * the root, so the generated HTML stays inside the one directory .gitignore
 * already treats as build output.
 *
 * There is deliberately no hero, no portrait and no call to action above the
 * essays. A reader arriving from a link should meet the writing first; the
 * person behind it is one click away on /about/ for anyone who wants it.
 */
function render_index(array $posts): string
{
    $extra  = ld_script(schema_website()) . "\n";
    $extra .= ld_script(schema_blog()) . "\n" . ld_script(schema_item_list($posts));

    $count = count($posts);
    $header  = eyebrow('Field Notes', true);
    $header .= '<h1 style="font-size:clamp(2.2rem,5vw,3.2rem)">Field Notes</h1>';
    $header .= '<p>Weekly essays on software architecture, the people around it, and the corporate machine they form together.</p>';
    $header .= '<p class="meta" style="margin-top:1rem">' . $count . ' essays · new signal most Mondays'
             . ' · <a href="/blog/start/">new here? start here</a>'
             . ' · <a href="#newsletter">by email</a>'
             . ' · <a href="/feed.xml">RSS</a></p>';

    $content  = featured_card($posts[0]);
    $content .= post_grid(array_slice($posts, 1));
    $content .= '<div style="margin-top:4rem">' . newsletter_box() . '</div>';

    return listing_page(
        'Field Notes · Bartosz Frąckowiak',
        'Weekly essays on software architecture, the people around it, and the corporate machine they form together. By Bartosz Frąckowiak, solution architect.',
        cfg('site_url') . '/',
        $extra,
        $header,
        $content
    );
}

/**
 * /blog/ used to be the listing and is now the home page, so the old URL is a
 * permanent redirect rather than a second copy of the same grid. Individual
 * essays keep living under /blog/<slug>/, so nothing else moves and no
 * inbound link to an essay breaks.
 */
function render_blog_redirect(): string
{
    $home = cfg('site_url') . '/';
    return "<?php\n"
        . "/* generated by tools/build.php — the listing moved to the site root */\n"
        . "header('Location: " . addslashes($home) . "', true, 301);\n"
        . "header('Cache-Control: max-age=3600');\n"
        . "echo '<!DOCTYPE html><meta charset=\"utf-8\"><title>Moved</title>'\n"
        . "   . '<link rel=\"canonical\" href=\"" . e($home) . "\">'\n"
        . "   . '<p>The field notes now live at <a href=\"" . e($home) . "\">" . e($home) . "</a>.</p>';\n";
}

/** Tag archive: hashtag header + magazine grid. */
function render_tag_page(string $tag, array $posts, int $allCount): string
{
    $slug = tag_slug($tag);
    $n = count($posts);
    $noun = $n === 1 ? 'essay' : 'essays';

    $extra = ld_script(schema_collection($tag, $posts)) . "\n" . ld_script(schema_breadcrumbs([
        'Field Notes' => cfg('site_url') . '/',
        '#' . $slug => cfg('site_url') . "/blog/tag/{$slug}/",
    ]));

    $header  = eyebrow('Tag', true);
    $header .= '<h1 class="tag-title">#' . $slug . '</h1>';
    $header .= '<p class="meta" style="margin-top:0.6rem">' . e($tag) . ' · ' . $n . ' ' . $noun . ' · <a href="/">all ' . $allCount . ' field notes &rarr;</a></p>';

    return listing_page(
        e($tag) . ' · Field Notes · Bartosz Frąckowiak',
        "Essays tagged \"{$tag}\" on software architecture, people, and organizational systems.",
        cfg('site_url') . "/blog/tag/{$slug}/",
        $extra,
        $header,
        post_grid($posts)
    );
}

/**
 * Branded 404 (generated to /404.php, wired via .htaccess ErrorDocument).
 * A dead link becomes an entrance: start-here CTA + the newest essays.
 */
function render_404_page(array $posts): string
{
    $extra = '<meta name="robots" content="noindex, follow">';
    $html  = "<?php http_response_code(404); ?>\n";
    $html .= page_head(
        'Signal lost · bfrackowiak.pl',
        'That page does not exist — but the archive does.',
        cfg('site_url') . '/404',
        $extra
    );
    $html .= site_nav();
    $html .= '<main class="wrap" style="padding:6rem 24px 5rem;max-width:880px;margin-inline:auto">';
    $html .= eyebrow('404 · signal lost');
    $html .= '<h1 class="display" style="font-size:clamp(2rem,4.5vw,3rem);margin:1rem 0">No signal on this frequency.</h1>';
    $html .= '<p style="color:var(--muted);max-width:56ch">The page you\'re after doesn\'t exist. Maybe the link aged, maybe a slug changed — or the essay is scheduled and not live yet. The archive, however, is very much alive:</p>';
    $html .= '<p style="margin:1.8rem 0 3rem"><a class="btn btn-primary" href="/blog/start/">Start here ' . arrow_icon() . '</a> ';
    $html .= '<a class="btn btn-ghost" href="/" style="margin-left:.6rem">All essays</a></p>';
    $html .= '<div class="related-head">' . eyebrow('Newest signals', true) . '</div>';
    $html .= log_rows(array_slice($posts, 0, 3));
    $html .= '</main>';
    return $html . site_footer();
}

/**
 * "Start here" page (/blog/start/): the guided entrance to the archive.
 * Hook essay ("read only one") + three doors matching the blog's pillars,
 * follow strip, newsletter, one soft services link. Built from the live
 * post list, so titles, read times, and counts never go stale; a slug
 * missing from $posts (unpublished) is skipped silently.
 */
function render_start_page(array $posts): string
{
    $by = array_column($posts, null, 'slug');
    $pick = fn(string $slug): ?array => $by[$slug] ?? null;

    $hook = $pick('mathematicians-vs-lawyers');
    $doors = [
        [
            'k'     => 'Architecture & orgs',
            'title' => 'People problems, dressed as technical ones',
            'desc'  => 'Ownership gaps, decision rights, and the org chart hiding inside your architecture. For architects and tech leads who keep fixing the same "technical" issue that never was one.',
            'reads' => ['nobody-owns-the-sum', 'decisions-have-an-architecture-too', 'the-anti-corruption-layer-is-for-people-too'],
        ],
        [
            'k'      => 'The AI transformation',
            'title'  => 'Running AI adoption at real scale',
            'desc'   => 'Fifteen squads, several hundred developers, one transformation: the tech radar that drives it, the metrics that survive contact with reality, and the architecture practice that grew out of both.',
            'series' => 'The “Tools That Scale” series',
            'reads'  => ['from-signals-to-blips', 'probabilistic-metrics', 'prompt-driven-architecture'],
        ],
        [
            'k'     => 'Building in the open',
            'title' => 'One person, three products, AI agents',
            'desc'  => 'Field reports from shipping my own products solo, with AI doing the typing: what actually changes, what breaks, and the receipts to prove it.',
            'reads' => ['a-company-of-one-staffed-by-agents', 'the-diagram-that-cannot-lie', 'edge-cases-written-by-parliament'],
        ],
    ];

    $n = count($posts);
    $url = cfg('site_url') . '/blog/start/';

    // structured data: a CollectionPage over the featured picks
    $featured = array_values(array_filter(array_merge(
        [$hook],
        ...array_map(fn($d) => array_map($pick, $d['reads']), $doors)
    )));
    $extra  = ld_script([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Start here · Field Notes',
        'url' => $url,
        'description' => 'The guided entrance to Field Notes: where to start reading, by interest.',
        'isPartOf' => ['@type' => 'Blog', '@id' => cfg('site_url') . '/blog/#blog'],
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => schema_item_list_elements($featured)],
    ]) . "\n";
    $extra .= ld_script(schema_breadcrumbs([
        'Field Notes' => cfg('site_url') . '/',
        'Start here' => $url,
    ]));

    $html  = page_head(
        'Start here · Field Notes · Bartosz Frąckowiak',
        "New to Field Notes? The shortest path into {$n} essays on software architecture, AI transformation, and building products — picked by the author.",
        $url,
        $extra
    );
    $html .= site_nav('start');
    $html .= '<main class="wrap start">';

    // ---- intro: who writes this, and why trust him
    $html .= '<div class="section-head blog-hero rise start-intro">';
    $html .= '<img class="start-face" src="/myself.jpeg" alt="Bartosz Frąckowiak" width="76" height="76" loading="lazy" decoding="async">';
    $html .= eyebrow('Start here', true);
    $html .= '<h1 style="font-size:clamp(2.1rem,4.6vw,3rem)">' . $n . ' essays in. Here is the map.</h1>';
    $html .= '<p>I\'m <strong>Bartosz Frąckowiak</strong> — solution architect, currently leading an AI transformation across several hundred engineers. Every week I publish one field note about systems, the people around them, and the corporate machine they form together. This page is the shortest path to the good stuff.</p>';
    $html .= '<p class="proof" style="margin-top:1.1rem"><span>Solution Architect in global SaaS</span><span>PhD research, University of Warsaw</span><span>Top 3% on Toptal</span><span>' . $n . ' essays and counting</span></p>';
    $html .= '</div>';

    // ---- the hook: if you read only one
    if ($hook) {
        $html .= '<div class="start-hook rise-2">';
        $html .= '<a class="featured2" href="' . e(post_path($hook['slug'])) . '">';
        $html .= '<span class="f2-body">';
        $html .= eyebrow('If you read only one');
        $html .= '<h2>' . e($hook['title']) . '</h2>';
        $html .= '<p>' . e($hook['subtitle']) . '</p>';
        $html .= '<span class="meta">' . $hook['minutes'] . ' min read · the essay I\'d hand you first</span>';
        $html .= '<span class="f2-go">Read it ' . arrow_icon() . '</span>';
        $html .= '</span>';
        if ($h = hero_meta($hook['slug'])) {
            $html .= '<span class="f2-media">' . img_lazy($h['src'], $hook['title'], $h['w'], $h['h']) . '</span>';
        }
        $html .= '</a></div>';
    }

    // ---- three reading paths (full-width rows: pitch left, essays right)
    $html .= '<div class="section-head" style="margin-top:4rem">' . eyebrow('The map') . '<h2>Then pick your door</h2>';
    $html .= '<p>Three threads run through all ' . $n . ' essays. Each path below tells you exactly what it covers and where to start.</p></div>';
    $html .= '<div class="paths">';
    foreach ($doors as $di => $d) {
        $reads = array_values(array_filter(array_map($pick, $d['reads'])));
        if (!$reads) continue;
        $total = array_sum(array_column($reads, 'minutes'));
        $html .= '<section class="path">';
        $html .= '<div class="path-info">';
        $html .= '<span class="path-k">' . sprintf('%02d', $di + 1) . ' · ' . e($d['k']) . '</span>';
        $html .= '<h3>' . e($d['title']) . '</h3>';
        $html .= '<p>' . e($d['desc']) . '</p>';
        $html .= '<span class="path-meta">' . count($reads) . ' essays · ' . $total . ' min total'
               . (!empty($d['series']) ? ' · ' . e($d['series']) . ', read in order' : '') . '</span>';
        $html .= '<a class="btn btn-primary path-cta" href="' . e(post_path($reads[0]['slug'])) . '">Start reading ' . arrow_icon() . '</a>';
        $html .= '</div>';
        $html .= '<ol class="path-list">';
        foreach ($reads as $i => $p) {
            $html .= '<li><a href="' . e(post_path($p['slug'])) . '">';
            $html .= '<span class="pl-no">' . sprintf('%02d', $i + 1) . '</span>';
            $html .= thumb_span(hero_meta($p['slug']), 'pl-thumb');
            $html .= '<span class="pl-txt"><span class="pl-title">' . e($p['title']) . '</span>';
            $html .= '<span class="pl-sub">' . e($p['subtitle']) . '</span></span>';
            $html .= '<span class="pl-min">' . $p['minutes'] . ' min</span>';
            $html .= '</a></li>';
        }
        $html .= '</ol>';
        $html .= '</section>';
    }
    $html .= '</div>';
    $html .= '<p style="margin-top:1.8rem"><a class="btn btn-ghost" href="/">Browse all ' . $n . ' essays &rarr;</a></p>';

    // ---- how to follow
    $html .= '<div class="start-follow">';
    $html .= '<span class="lbl">Follow along</span>';
    $html .= '<a href="#newsletter">Newsletter — one essay, most Mondays</a>';
    $html .= '<a href="https://www.linkedin.com/in/bartoszfrackowiak/" rel="me">LinkedIn</a>';
    $html .= '<a href="/llms.txt">for AI agents: llms.txt</a>';
    $html .= '</div>';

    $html .= '<div style="margin-top:2.6rem">' . newsletter_box() . '</div>';

    // ---- one soft door to the person, not to a price list
    $html .= '<p class="start-services">All of this comes out of a day job and three side projects. If you want to know whose notes these are, <a href="/about/">that story is here</a>.</p>';

    $html .= '</main>';
    $html .= reveal_script();
    return $html . site_footer();
}

/** Homepage partial: featured newest essay + the next three as compact rows. */
function render_latest_partial(array $posts): string
{
    // below the fold on the homepage — never compete with the hero portrait for priority
    $html = featured_card($posts[0], false);
    $more = array_slice($posts, 1, 3);
    if ($more) {
        $html .= "\n" . '<div style="margin-top:1.6rem">' . log_rows($more) . '</div>';
    }
    return $html;
}
