<?php
/**
 * lib/components.php — reusable HTML fragments shared across pages:
 * images, eyebrows, cards, list rows, pager, author box, badges, forms.
 */

declare(strict_types=1);

/* ---------------- primitives ---------------- */

/** Lazy-loaded image with explicit dimensions (no layout shift). */
function img_lazy(string $src, string $alt, $w, $h): string
{
    return '<img src="' . e($src) . '" alt="' . e($alt) . '" width="' . $w . '" height="' . $h . '" loading="lazy" decoding="async">';
}

/** Eagerly-loaded LCP image with explicit dimensions. */
function img_eager(string $src, string $alt, $w, $h): string
{
    return '<img src="' . e($src) . '" alt="' . e($alt) . '" width="' . $w . '" height="' . $h . '" loading="eager" decoding="async" fetchpriority="high">';
}

/** The radar-blip eyebrow label; $live adds the ping animation. */
function eyebrow(string $label, bool $live = false): string
{
    $blip = $live ? 'blip blip-live' : 'blip';
    return '<span class="eyebrow"><span class="' . $blip . '"></span>' . $label . '</span>';
}

/** Right arrow icon used in CTAs. */
function arrow_icon(): string
{
    return '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';
}

/** "<date> · N min read[ · series]" meta line for a post. */
function post_meta_line(array $p): string
{
    return nice_date($p['date']) . ' · ' . $p['minutes'] . ' min read' . series_suffix($p['slug']);
}

/** Thumbnail wrapped in a named span, when the post has a hero image. */
function thumb_span(?array $h, string $class): string
{
    if (!$h) return '';
    return '<span class="' . $class . '">' . img_lazy($h['thumb'], '', $h['tw'], $h['th']) . '</span>';
}

/* ---------------- post presentation ---------------- */

/** Hero <figure> with license credit line. */
function hero_figure(array $h, string $alt): string
{
    $credit = 'Photo';
    if ($h['creator'] !== '' && strtolower($h['creator']) !== 'unknown') $credit .= ': ' . e($h['creator']);
    if ($h['license'] !== '') {
        $credit .= ' · ' . ($h['license'] === 'CC0' || $h['license'] === 'PDM' ? e($h['license']) : 'CC ' . e($h['license']));
    }
    // no creator/license recorded: fall back to the stored caption (e.g. "Image from the Medium original")
    if ($credit === 'Photo' && $h['caption'] !== '') $credit = e($h['caption']);
    $link = $h['source'] !== '' ? ' · <a href="' . e($h['source']) . '" rel="noopener nofollow">source</a>' : '';
    return '<figure class="post-hero">' . img_eager($h['src'], $alt, $h['w'], $h['h'])
         . '<figcaption>' . $credit . $link . '</figcaption></figure>';
}

/** "Also published on Medium" badge under the hero. */
function syndication_badge(?string $mediumUrl): string
{
    if (!$mediumUrl) return '';
    return '<div class="syndication"><a href="' . e($mediumUrl) . '" target="_blank" rel="noopener">'
         . '<span class="m-badge" aria-hidden="true">M</span>Also published on Medium<span class="s-ext">↗</span></a></div>';
}

/** Earlier/later navigation card on article pages. */
function pager_card(array $post, string $cls, string $label): string
{
    $out = '<a class="' . $cls . '" href="' . e(post_path($post['slug'])) . '">';
    $out .= thumb_span(hero_meta($post['slug']), 'pthumb');
    $out .= '<span class="ptxt"><span class="dir">' . $label . '</span><span class="t">' . e($post['title']) . '</span></span>';
    return $out . '</a>';
}

/** Author bio box at the end of article pages. */
function author_box(): string
{
    return <<<HTML
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
}

/* ---------------- lists & cards ---------------- */

/** shared signal-log row list (with thumbnails; optional clickable tag chips) */
function log_rows(array $posts, bool $withTags = false, string $extraClass = ''): string
{
    $html = '<ul class="log log-thumbs' . ($extraClass ? ' ' . $extraClass : '') . '">';
    foreach ($posts as $p) {
        $html .= '<li' . ($withTags && $p['tags'] ? ' class="has-tags"' : '') . '>';
        $html .= '<a href="' . e(post_path($p['slug'])) . '">';
        $html .= thumb_span(hero_meta($p['slug']), 'thumb');
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

/** one essay card for the magazine grid */
function post_card(array $p): string
{
    $out  = '<a class="pcard reveal" href="' . e(post_path($p['slug'])) . '">';
    $out .= thumb_span(hero_meta($p['slug']), 'pcard-media');
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

/** Featured split card for the newest essay. */
function featured_card(array $p): string
{
    $hero = hero_meta($p['slug']);
    $html  = '<a class="featured2 rise-2" href="' . e(post_path($p['slug'])) . '">';
    $html .= '<span class="f2-body">';
    $html .= eyebrow('Latest signal', true);
    $html .= '<h2>' . e($p['title']) . '</h2>';
    $html .= '<p>' . e($p['subtitle']) . '</p>';
    $html .= '<span class="meta">' . post_meta_line($p) . '</span>';
    $html .= '<span class="f2-go">Read the essay ' . arrow_icon() . '</span>';
    $html .= '</span>';
    if ($hero) {
        $html .= '<span class="f2-media">' . img_eager($hero['src'], $p['title'], $hero['w'], $hero['h']) . '</span>';
    }
    return $html . '</a>';
}

/** Magazine grid of essay cards. */
function post_grid(array $posts): string
{
    $html = '<div class="post-grid">';
    foreach ($posts as $p) $html .= post_card($p);
    return $html . '</div>';
}

/* ---------------- forms & scripts ---------------- */

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
