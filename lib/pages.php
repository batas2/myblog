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

/** Article page: SEO head, hero, syndication badge, prose, tags, pager, author, comments. */
function render_post(array $p, ?array $prev, ?array $next): string
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
    $header .= '<p class="meta" style="margin-top:1rem">' . $count . ' essays · new signal most Mondays · <a href="/feed.xml">RSS</a></p>';

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

/** Homepage partial: the three newest posts as compact rows. */
function render_latest_partial(array $posts): string
{
    return log_rows(array_slice($posts, 0, 3));
}
