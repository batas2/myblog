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
    $extra .= ld_script(schema_breadcrumbs([
        'Home' => cfg('site_url') . '/',
        'Field Notes' => cfg('site_url') . '/blog/',
        $p['title'] => $url,
    ])) . "\n";
    $extra .= '<meta property="og:type" content="article">' . "\n";
    $extra .= '<meta property="article:published_time" content="' . $published . '">' . "\n";
    $extra .= '<meta property="article:author" content="' . e(cfg('author')) . '">' . "\n";
    foreach ($p['tags'] as $t) $extra .= '<meta property="article:tag" content="' . e($t) . '">' . "\n";
    $extra .= '<link rel="alternate" type="text/markdown" href="' . $url . 'index.md" title="Markdown source">' . "\n";
    if ($p['medium']) {
        $extra .= '<link rel="syndication" href="' . e($p['medium']) . '">' . "\n";
    }
    if ($hero) {
        $extra .= '<meta property="og:image:width" content="' . $hero['w'] . '">' . "\n";
        $extra .= '<meta property="og:image:height" content="' . $hero['h'] . '">' . "\n";
        $extra .= '<meta property="og:image:alt" content="' . e($p['title']) . '">' . "\n";
        $extra .= '<link rel="preload" as="image" href="' . e($hero['src']) . '" fetchpriority="high">' . "\n";
    }

    $html  = page_head($p['title'] . ' · Bartosz Frąckowiak', $p['subtitle'], $url, $extra,
                       $hero ? cfg('site_url') . $hero['src'] : (string) cfg('og_image'));
    $html .= site_nav('blog');

    $html .= '<main class="article-body">';
    $html .= '<div class="article-head rise">';
    $html .= '<a class="backlink" href="/blog/">&larr; All field notes</a>';
    $html .= '<h1>' . e($p['title']) . '</h1>';
    if ($p['subtitle']) $html .= '<p class="dek">' . e($p['subtitle']) . '</p>';
    $html .= '<p class="meta" style="margin-top:1.4rem">' . post_meta_line($p) . '</p>';
    $html .= '<div class="rule"></div></div>';

    if ($hero) $html .= hero_figure($hero, $p['title']);
    $html .= syndication_badge($p['medium']);

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

/** Blog index: featured newest essay + magazine grid + newsletter. */
function render_index(array $posts): string
{
    $extra = ld_script(schema_blog()) . "\n" . ld_script(schema_item_list($posts));

    $count = count($posts);
    $header  = eyebrow('The blog', true);
    $header .= '<h1 style="font-size:clamp(2.2rem,5vw,3.2rem)">Field Notes</h1>';
    $header .= '<p>Weekly essays on software architecture, the people around it, and the corporate machine they form together.</p>';
    $header .= '<p class="meta" style="margin-top:1rem">' . $count . ' essays · new signal most Mondays · <a href="/blog/start/">new here? start here</a> · <a href="#newsletter">get them by email</a></p>';

    $content  = featured_card($posts[0]);
    $content .= post_grid(array_slice($posts, 1));
    $content .= '<div style="margin-top:4rem">' . newsletter_box() . '</div>';

    return listing_page(
        'Field Notes · Bartosz Frąckowiak',
        'Weekly essays on software architecture, the people around it, and the corporate machine they form together. By Bartosz Frąckowiak, solution architect.',
        cfg('site_url') . '/blog/',
        $extra,
        $header,
        $content
    );
}

/** Tag archive: hashtag header + magazine grid. */
function render_tag_page(string $tag, array $posts, int $allCount): string
{
    $slug = tag_slug($tag);
    $n = count($posts);
    $noun = $n === 1 ? 'essay' : 'essays';

    $extra = ld_script(schema_collection($tag, $posts)) . "\n" . ld_script(schema_breadcrumbs([
        'Home' => cfg('site_url') . '/',
        'Field Notes' => cfg('site_url') . '/blog/',
        '#' . $slug => cfg('site_url') . "/blog/tag/{$slug}/",
    ]));

    $header  = eyebrow('Tag', true);
    $header .= '<h1 class="tag-title">#' . $slug . '</h1>';
    $header .= '<p class="meta" style="margin-top:0.6rem">' . e($tag) . ' · ' . $n . ' ' . $noun . ' · <a href="/blog/">all ' . $allCount . ' field notes &rarr;</a></p>';

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
    $html .= '<a class="btn btn-ghost" href="/blog/" style="margin-left:.6rem">All essays</a></p>';
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
            'k'       => 'Architecture & orgs',
            'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
            'who'     => 'For architects and tech leads meeting people problems dressed as technical ones.',
            'reads'   => [
                ['nobody-owns-the-sum',                        'The sharpest org observation on this blog.'],
                ['decisions-have-an-architecture-too',         ''],
                ['the-anti-corruption-layer-is-for-people-too', ''],
            ],
        ],
        [
            'k'       => 'The AI transformation',
            'series'  => 'The “Tools That Scale” series — read in order',
            'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/></svg>',
            'who'     => 'For leaders told to "do AI" — how I run adoption across fifteen squads.',
            'reads'   => [
                ['from-signals-to-blips',      'A tech radar becomes the engine of a transformation.'],
                ['probabilistic-metrics',      ''],
                ['prompt-driven-architecture', ''],
            ],
        ],
        [
            'k'       => 'Building in the open',
            'icon'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"/></svg>',
            'who'     => 'For anyone building alone — my products, shipped with AI agents, receipts included.',
            'reads'   => [
                ['a-company-of-one-staffed-by-agents', 'What AI actually changes about building solo.'],
                ['the-diagram-that-cannot-lie',        ''],
                ['edge-cases-written-by-parliament',   ''],
            ],
        ],
    ];

    $n = count($posts);
    $url = cfg('site_url') . '/blog/start/';

    // structured data: a CollectionPage over the featured picks
    $featured = array_values(array_filter(array_merge(
        [$hook],
        ...array_map(fn($d) => array_map(fn($r) => $pick($r[0]), $d['reads']), $doors)
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
        'Home' => cfg('site_url') . '/',
        'Field Notes' => cfg('site_url') . '/blog/',
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
    $html .= '<img class="start-face" src="/mysefl.jpeg" alt="Bartosz Frąckowiak" width="76" height="76" loading="lazy" decoding="async">';
    $html .= eyebrow('Start here', true);
    $html .= '<h1 style="font-size:clamp(2.1rem,4.6vw,3rem)">' . $n . ' essays in. Here is the map.</h1>';
    $html .= '<p>I\'m <strong>Bartosz Frąckowiak</strong> — solution architect, currently leading an AI transformation across several hundred engineers. Every week I publish one field note about systems, the people around them, and the corporate machine they form together. This page is the shortest path to the good stuff.</p>';
    $html .= '<p class="proof" style="margin-top:1.1rem"><span>Solution Architect @ EcoVadis</span><span>PhD research, University of Warsaw</span><span>Top 3% on Toptal</span><span>' . $n . ' essays and counting</span></p>';
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

    // ---- three doors
    $html .= '<div class="section-head" style="margin-top:4rem">' . eyebrow('The map') . '<h2>Then pick your door</h2>';
    $html .= '<p>Three threads run through everything here. Follow the one that sounds like your week.</p></div>';
    $html .= '<div class="grid-3 start-doors">';
    foreach ($doors as $d) {
        $html .= '<article class="card door">';
        $html .= '<div class="card-top"><div class="icon-tile">' . $d['icon'] . '</div><span class="k">' . e($d['k']) . '</span></div>';
        $html .= '<p class="d-promise">' . e($d['who']) . '</p>';
        $html .= '<ol class="d-path">';
        foreach ($d['reads'] as $i => [$slug, $why]) {
            $p = $pick($slug);
            if (!$p) continue;
            $html .= '<li><a href="' . e(post_path($slug)) . '">';
            $html .= '<span class="d-no">' . sprintf('%02d', $i + 1) . '</span>';
            $html .= '<span class="d-read"><span class="d-title">' . e($p['title']) . '</span>';
            if ($why !== '') $html .= '<span class="d-why">' . e($why) . '</span>';
            $html .= '</span>';
            $html .= '<span class="d-min">' . $p['minutes'] . ' min</span>';
            $html .= '</a></li>';
        }
        $html .= '</ol>';
        if (!empty($d['series'])) $html .= '<span class="d-series">' . e($d['series']) . '</span>';
        $html .= '</article>';
    }
    $html .= '</div>';
    $html .= '<p style="margin-top:1.8rem"><a class="btn btn-ghost" href="/blog/">Browse all ' . $n . ' essays &rarr;</a></p>';

    // ---- how to follow
    $html .= '<div class="start-follow">';
    $html .= '<span class="lbl">Follow along</span>';
    $html .= '<a href="#newsletter">Newsletter — one essay, most Mondays</a>';
    $html .= '<a href="https://www.linkedin.com/in/bartoszfrackowiak/" rel="me">LinkedIn</a>';
    $html .= '<a href="/llms.txt">for AI agents: llms.txt</a>';
    $html .= '</div>';

    $html .= '<div style="margin-top:2.6rem">' . newsletter_box() . '</div>';

    // ---- one soft services door, as the plan prescribes
    $html .= '<p class="start-services">If this is the kind of thinking you want inside your organization — architecture consulting and AI training are what I do in daylight. <a href="/#services">Work with me &rarr;</a></p>';

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
