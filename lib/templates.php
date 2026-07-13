<?php
/**
 * lib/templates.php — every HTML/XML renderer: page chrome, cards, article
 * pages, blog index, tag pages, RSS and sitemap.
 */

declare(strict_types=1);

/** shared signal-log row list (with thumbnails; optional clickable tag chips) */
function log_rows(array $posts, bool $withTags = false, string $extraClass = ''): string
{
    $html = '<ul class="log log-thumbs' . ($extraClass ? ' ' . $extraClass : '') . '">';
    foreach ($posts as $p) {
        $h = hero_meta($p['slug']);
        $html .= '<li' . ($withTags && $p['tags'] ? ' class="has-tags"' : '') . '>';
        $html .= '<a href="/blog/' . e($p['slug']) . '/">';
        if ($h) $html .= '<span class="thumb"><img src="' . e($h['thumb']) . '" alt="" width="' . $h['tw'] . '" height="' . $h['th'] . '" loading="lazy" decoding="async"></span>';
        $html .= '<span class="log-main">';
        $html .= '<span class="date">' . log_date($p['date']) . '</span>';
        $html .= '<h3>' . e($p['title']) . '</h3><span class="sub">' . e($p['subtitle']) . '</span>';
        $html .= '</span>';
        $html .= '<span class="rt">' . $p['minutes'] . ' min</span>';
        $html .= '</a>';
        if ($withTags && $p['tags']) {
            $html .= '<span class="row-tags">';
            foreach (array_slice($p['tags'], 0, 3) as $t) $html .= tag_link($t);
            $html .= '</span>';
        }
        $html .= '</li>';
    }
    return $html . '</ul>';
}

function page_head(string $title, string $desc, string $canonical, string $extra = '', string $ogImage = ''): string
{
    $t = e($title); $d = e($desc); $c = e($canonical);
    $og = e($ogImage !== '' ? $ogImage : (string) cfg('og_image'));
    $siteName = e((string) cfg('site_name'));
    $author = e((string) cfg('author'));
    $feed = e(cfg('site_url') . '/feed.xml');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$t}</title>
<meta name="description" content="{$d}">
<meta name="author" content="{$author}">
<link rel="canonical" href="{$c}">
<meta property="og:title" content="{$t}">
<meta property="og:description" content="{$d}">
<meta property="og:url" content="{$c}">
<meta property="og:site_name" content="{$siteName}">
<meta property="og:image" content="{$og}">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="theme-color" content="#fbfbfd">
<link rel="alternate" type="application/rss+xml" title="Field Notes · RSS" href="{$feed}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/site.css">
{$extra}
</head>
<body>
HTML;
}

function site_nav(string $active = ''): string
{
    $blog = $active === 'blog' ? ' aria-current="page"' : '';
    return <<<HTML
<header class="nav">
  <div class="nav-inner">
    <a class="wordmark" href="/">bfrackowiak<span class="tld">.pl</span></a>
    <ul class="nav-links">
      <li><a href="/blog/"{$blog}>Writing</a></li>
      <li class="optional"><a href="/#journey">Journey</a></li>
      <li class="optional"><a href="/#services">Services</a></li>
      <li><a class="nav-cta" href="/#contact">Work with me</a></li>
    </ul>
  </div>
</header>
HTML;
}

function site_footer(): string
{
    $y = date('Y');
    return <<<HTML
<footer class="footer">
  <div class="footer-inner">
    <span>© {$y} Bartosz Frąckowiak</span>
    <nav>
      <a href="/blog/">Writing</a>
      <a href="/feed.xml">RSS</a>
      <a href="https://www.linkedin.com/in/bartoszfrackowiak/" rel="me">LinkedIn</a>
      <a href="https://medium.com/@bfrackowiak" rel="me">Medium</a>
      <a href="https://github.com/batas2" rel="me">GitHub</a>
    </nav>
  </div>
</footer>
<script>
if (navigator.doNotTrack !== '1' && navigator.sendBeacon) {
  navigator.sendBeacon('/a.php', JSON.stringify({p: location.pathname, r: document.referrer}));
}
</script>
</body>
</html>
HTML;
}

/** email capture — "one field note, most Mondays" */
function newsletter_box(string $variant = ''): string
{
    $action = (string) cfg('newsletter_action');
    if ($action === '') return '';
    return <<<HTML
<div class="newsletter {$variant}" id="newsletter">
  <div class="nl-text">
    <span class="eyebrow"><span class="blip blip-live"></span>The signal</span>
    <h3>Get the next field note</h3>
    <p>One essay on architecture, the people around it, and the corporate machine. Most Mondays, straight to your inbox.</p>
  </div>
  <form action="{$action}" method="post" class="nl-form">
    <label class="visually-hidden" for="nl-email">Email address</label>
    <input type="email" id="nl-email" name="email" required placeholder="you@example.com" autocomplete="email">
    <button type="submit">Subscribe</button>
    <span class="nl-note">No spam. Unsubscribe anytime.</span>
  </form>
</div>
HTML;
}

function render_post(array $p, ?array $prev, ?array $next): string
{
    $url   = cfg('site_url') . '/blog/' . $p['slug'] . '/';
    $title = $p['title'] . ' · Bartosz Frąckowiak';
    $series = '';
    if (isset(SERIES[$p['slug']])) {
        [$name, $part] = SERIES[$p['slug']];
        $series = ' · ' . e($name) . ', part ' . $part;
    }
    $hero = hero_meta($p['slug']);

    $published = $p['date'] . 'T09:00:00+01:00';
    $person = ['@type' => 'Person', '@id' => cfg('site_url') . '/#person', 'name' => cfg('author'), 'url' => cfg('site_url')];
    $posting = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $p['title'],
        'description' => $p['subtitle'],
        'datePublished' => $published,
        'dateModified' => $published,
        'author' => $person,
        'publisher' => $person,
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        'url' => $url,
        'inLanguage' => 'en',
        'wordCount' => $p['words'],
        'timeRequired' => 'PT' . $p['minutes'] . 'M',
        'keywords' => implode(', ', $p['tags']),
        'articleSection' => $p['tags'][0] ?? 'Software Architecture',
        'isPartOf' => ['@type' => 'Blog', '@id' => cfg('site_url') . '/blog/#blog', 'name' => 'Field Notes'],
    ];
    if ($hero) {
        $posting['image'] = ['@type' => 'ImageObject', 'url' => cfg('site_url') . $hero['src'], 'width' => $hero['w'], 'height' => $hero['h']];
    }
    if ($p['medium']) {
        $posting['sameAs'] = $p['medium'];
    }
    $crumbs = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => cfg('site_url') . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Field Notes', 'item' => cfg('site_url') . '/blog/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title'], 'item' => $url],
        ],
    ];
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    $extra  = '<script type="application/ld+json">' . json_encode($posting, $jsonFlags) . '</script>' . "\n";
    $extra .= '<script type="application/ld+json">' . json_encode($crumbs, $jsonFlags) . '</script>' . "\n";
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

    $html  = page_head($title, $p['subtitle'], $url, $extra,
                       $hero ? cfg('site_url') . $hero['src'] : (string) cfg('og_image'));
    $html .= site_nav('blog');

    $html .= '<main class="article-body">';
    $html .= '<div class="article-head rise">';
    $html .= '<a class="backlink" href="/blog/">&larr; All field notes</a>';
    $html .= '<h1>' . e($p['title']) . '</h1>';
    if ($p['subtitle']) $html .= '<p class="dek">' . e($p['subtitle']) . '</p>';
    $html .= '<p class="meta" style="margin-top:1.4rem">' . nice_date($p['date']) . ' · ' . $p['minutes'] . ' min read' . $series . '</p>';
    $html .= '<div class="rule"></div></div>';

    if ($hero) $html .= hero_figure($hero, $p['title']);

    if ($p['medium']) {
        $html .= '<div class="syndication"><a href="' . e($p['medium']) . '" target="_blank" rel="noopener">'
               . '<span class="m-badge" aria-hidden="true">M</span>Also published on Medium<span class="s-ext">↗</span></a></div>';
    }

    $html .= '<article class="prose rise-2">' . $p['html'] . '</article>';

    if ($p['tags']) {
        $html .= '<div class="tag-row">';
        foreach ($p['tags'] as $t) $html .= tag_link($t);
        $html .= '</div>';
    }

    if ($prev || $next) {
        $pagerCard = function (array $post, string $cls, string $label): string {
            $h = hero_meta($post['slug']);
            $out = '<a class="' . $cls . '" href="/blog/' . e($post['slug']) . '/">';
            if ($h) $out .= '<span class="pthumb"><img src="' . e($h['thumb']) . '" alt="" width="' . $h['tw'] . '" height="' . $h['th'] . '" loading="lazy" decoding="async"></span>';
            $out .= '<span class="ptxt"><span class="dir">' . $label . '</span><span class="t">' . e($post['title']) . '</span></span>';
            return $out . '</a>';
        };
        $html .= '<nav class="pager">';
        $html .= $prev ? $pagerCard($prev, 'prev', '&larr; Earlier') : '<span></span>';
        if ($next) $html .= $pagerCard($next, 'next', 'Later &rarr;');
        $html .= '</nav>';
    }

    $html .= <<<HTML
<div class="author-wrap"><div class="author">
  <img src="/mysefl.jpeg" alt="Bartosz Frąckowiak" loading="lazy">
  <div>
    <div class="name">Bartosz Frąckowiak</div>
    <p>Solution architect. I write weekly about software architecture, the humans around it, and the corporate machine they form together.</p>
    <div class="links">
      <a href="/#contact">Work with me</a>
      <a href="https://www.linkedin.com/in/bartoszfrackowiak/">LinkedIn</a>
      <a href="/feed.xml">RSS</a>
    </div>
  </div>
</div></div>
HTML;

    $html .= '<div class="newsletter-wrap">' . newsletter_box() . '</div>';

    // the one dynamic island on an otherwise static page: file-based comments
    $html .= "\n<?php \$COMMENT_SLUG = '" . $p['slug'] . "'; include dirname(__DIR__, 2) . '/comments.php'; ?>\n";
    $html .= '</main>';

    return $html . site_footer();
}

function render_index(array $posts): string
{
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $blogLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        '@id' => cfg('site_url') . '/blog/#blog',
        'name' => 'Field Notes',
        'url' => cfg('site_url') . '/blog/',
        'description' => 'Weekly essays on software architecture, the people around it, and the corporate machine they form together.',
        'inLanguage' => 'en',
        'author' => ['@type' => 'Person', '@id' => cfg('site_url') . '/#person', 'name' => cfg('author'), 'url' => cfg('site_url')],
    ];
    $itemsLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'itemListElement' => array_map(
            fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p['title'], 'url' => cfg('site_url') . '/blog/' . $p['slug'] . '/'],
            $posts, array_keys($posts)
        ),
    ];
    $extra  = '<script type="application/ld+json">' . json_encode($blogLd, $jsonFlags) . '</script>' . "\n";
    $extra .= '<script type="application/ld+json">' . json_encode($itemsLd, $jsonFlags) . '</script>';

    $html  = page_head(
        'Field Notes · Bartosz Frąckowiak',
        'Weekly essays on software architecture, the people around it, and the corporate machine they form together. By Bartosz Frąckowiak, solution architect.',
        cfg('site_url') . '/blog/',
        $extra
    );
    $html .= site_nav('blog');

    $count = count($posts);
    $html .= <<<HTML
<main class="wrap">
  <div class="section-head blog-hero rise">
    <span class="eyebrow"><span class="blip blip-live"></span>The blog</span>
    <h1 style="font-size:clamp(2.2rem,5vw,3.2rem)">Field Notes</h1>
    <p>Weekly essays on software architecture, the people around it, and the corporate machine they form together.</p>
    <p class="meta" style="margin-top:1rem">{$count} essays · new signal most Mondays · <a href="/feed.xml">RSS</a></p>
  </div>
HTML;

    // featured = latest: split card
    $f = $posts[0];
    $fs = '';
    if (isset(SERIES[$f['slug']])) { [$n, $pt] = SERIES[$f['slug']]; $fs = ' · ' . e($n) . ', part ' . $pt; }
    $fh = hero_meta($f['slug']);
    $arrow = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
    $html .= '<a class="featured2 rise-2" href="/blog/' . e($f['slug']) . '/">';
    $html .= '<span class="f2-body">';
    $html .= '<span class="eyebrow"><span class="blip blip-live"></span>Latest signal</span>';
    $html .= '<h2>' . e($f['title']) . '</h2>';
    $html .= '<p>' . e($f['subtitle']) . '</p>';
    $html .= '<span class="meta">' . nice_date($f['date']) . ' · ' . $f['minutes'] . ' min read' . $fs . '</span>';
    $html .= '<span class="f2-go">Read the essay ' . $arrow . '</span>';
    $html .= '</span>';
    if ($fh) $html .= '<span class="f2-media"><img src="' . e($fh['src']) . '" alt="' . e($f['title']) . '" width="' . $fh['w'] . '" height="' . $fh['h'] . '" loading="eager" decoding="async" fetchpriority="high"></span>';
    $html .= '</a>';

    // the rest as a magazine grid
    $html .= '<div class="post-grid">';
    foreach (array_slice($posts, 1) as $p) $html .= post_card($p);
    $html .= '</div>';

    $html .= '<div style="margin-top:4rem">' . newsletter_box() . '</div>';

    $html .= '</main>';
    $html .= reveal_script();

    return $html . site_footer();
}

function render_latest_partial(array $posts): string
{
    return log_rows(array_slice($posts, 0, 3));
}

/** one essay card for the magazine grid */
function post_card(array $p): string
{
    $h = hero_meta($p['slug']);
    $out  = '<a class="pcard reveal" href="/blog/' . e($p['slug']) . '/">';
    if ($h) {
        $out .= '<span class="pcard-media"><img src="' . e($h['thumb']) . '" alt="" width="' . $h['tw'] . '" height="' . $h['th'] . '" loading="lazy" decoding="async"></span>';
    }
    $out .= '<span class="pcard-body">';
    $out .= '<span class="meta">' . log_date($p['date']) . ' · ' . $p['minutes'] . ' min</span>';
    $out .= '<h3>' . e($p['title']) . '</h3>';
    $out .= '<span class="sub">' . e($p['subtitle']) . '</span>';
    if ($p['tags']) {
        $out .= '<span class="pcard-tags">';
        foreach (array_slice($p['tags'], 0, 2) as $t) {
            $out .= '<span class="tag">#' . tag_slug($t) . '</span>';
        }
        $out .= '</span>';
    }
    $out .= '</span></a>';
    return $out;
}

/** IntersectionObserver stagger; no-JS and reduced-motion stay fully visible */
function reveal_script(): string
{
    return <<<HTML
<script>
document.documentElement.classList.add('js');
(function () {
  var els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
    els.forEach(function (el) { el.classList.add('in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
  els.forEach(function (el, i) {
    el.style.setProperty('--d', (i % 3) * 80 + 'ms');
    io.observe(el);
  });
})();
</script>
HTML;
}

function render_tag_page(string $tag, array $posts, int $allCount): string
{
    $slug = tag_slug($tag);
    $n = count($posts);
    $noun = $n === 1 ? 'essay' : 'essays';
    $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $collLd = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $tag . ' · Field Notes',
        'url' => cfg('site_url') . "/blog/tag/{$slug}/",
        'inLanguage' => 'en',
        'isPartOf' => ['@type' => 'Blog', '@id' => cfg('site_url') . '/blog/#blog'],
        'about' => $tag,
        'mainEntity' => [
            '@type' => 'ItemList',
            'itemListElement' => array_map(
                fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $p['title'], 'url' => cfg('site_url') . '/blog/' . $p['slug'] . '/'],
                $posts, array_keys($posts)
            ),
        ],
    ];
    $crumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => cfg('site_url') . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Field Notes', 'item' => cfg('site_url') . '/blog/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => '#' . $slug, 'item' => cfg('site_url') . "/blog/tag/{$slug}/"],
        ],
    ];
    $extra  = '<script type="application/ld+json">' . json_encode($collLd, $jsonFlags) . '</script>' . "\n";
    $extra .= '<script type="application/ld+json">' . json_encode($crumbLd, $jsonFlags) . '</script>';

    $html  = page_head(
        e($tag) . ' · Field Notes · Bartosz Frąckowiak',
        "Essays tagged \"{$tag}\" on software architecture, people, and organizational systems.",
        cfg('site_url') . "/blog/tag/{$slug}/",
        $extra
    );
    $html .= site_nav('blog');
    $html .= '<main class="wrap">';
    $html .= '<div class="section-head blog-hero rise">';
    $html .= '<span class="eyebrow"><span class="blip blip-live"></span>Tag</span>';
    $html .= '<h1 class="tag-title">#' . $slug . '</h1>';
    $html .= '<p class="meta" style="margin-top:0.6rem">' . e($tag) . ' · ' . $n . ' ' . $noun . ' · <a href="/blog/">all ' . $allCount . ' field notes &rarr;</a></p>';
    $html .= '</div>';
    $html .= '<div class="post-grid">';
    foreach ($posts as $p) $html .= post_card($p);
    $html .= '</div>';
    $html .= '</main>';
    $html .= reveal_script();
    return $html . site_footer();
}

/* ============================== feeds ============================== */

function render_rss(array $posts): string
{
    $site = (string) cfg('site_url');
    $items = '';
    foreach ($posts as $p) {
        $url = cfg('site_url') . '/blog/' . $p['slug'] . '/';
        $pub = date('r', strtotime($p['date'] . ' 09:00:00'));
        $cats = '';
        foreach ($p['tags'] as $t) $cats .= '      <category>' . e($t) . "</category>\n";
        $content = str_replace(']]>', ']]&gt;', $p['html']);
        $items .= "    <item>\n"
            . '      <title>' . e($p['title']) . "</title>\n"
            . "      <link>{$url}</link>\n"
            . "      <guid isPermaLink=\"true\">{$url}</guid>\n"
            . "      <pubDate>{$pub}</pubDate>\n"
            . '      <description>' . e($p['subtitle']) . "</description>\n"
            . "      <content:encoded><![CDATA[{$content}]]></content:encoded>\n"
            . $cats
            . "    </item>\n";
    }
    $now = date('r');
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Field Notes · Bartosz Frąckowiak</title>
    <link>{$site}/blog/</link>
    <atom:link href="{$site}/feed.xml" rel="self" type="application/rss+xml"/>
    <description>Weekly essays on software architecture, the people around it, and the corporate machine they form together.</description>
    <language>en</language>
    <lastBuildDate>{$now}</lastBuildDate>
{$items}  </channel>
</rss>
XML;
}


function render_sitemap(array $posts): string
{
    $latest = $posts[0]['date'];
    $urls = "  <url><loc>" . cfg('site_url') . "/</loc><lastmod>{$latest}</lastmod><priority>1.0</priority></url>\n"
          . "  <url><loc>" . cfg('site_url') . "/blog/</loc><lastmod>{$latest}</lastmod><priority>0.9</priority></url>\n";
    foreach ($posts as $p) {
        $urls .= '  <url><loc>' . cfg('site_url') . '/blog/' . $p['slug'] . "/</loc><lastmod>{$p['date']}</lastmod><priority>0.7</priority></url>\n";
    }
    foreach (collect_tags($posts) as $tag => $tagged) {
        $urls .= '  <url><loc>' . cfg('site_url') . '/blog/tag/' . tag_slug($tag) . "/</loc><lastmod>{$tagged[0]['date']}</lastmod><priority>0.4</priority></url>\n";
    }
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}</urlset>\n";
}
