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
    return nice_ts($p['publish_ts']) . ' · ' . $p['minutes'] . ' min read' . series_suffix($p['slug']);
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
function syndication_badge(array $links): string
{
    if (!$links) return '';
    $out = '<div class="syndication">';
    foreach ($links as $platform => $url) {
        $spec = SYNDICATION[$platform] ?? ['label' => $platform, 'badge' => strtoupper($platform[0])];
        $out .= '<a href="' . e($url) . '" target="_blank" rel="noopener">'
              . '<span class="m-badge" aria-hidden="true">' . e($spec['badge']) . '</span>'
              . 'Also published on ' . e($spec['label']) . '<span class="s-ext">↗</span></a>';
    }
    return $out . '</div>';
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
  <img src="/myself.jpeg" alt="Bartosz Frąckowiak" loading="lazy">
  <div>
    <div class="name">Bartosz Frąckowiak</div>
    <p>Solution architect. I write weekly about software architecture, the humans around it, and the corporate machine they form together.</p>
    <div class="links">
      <a href="/blog/start/">Start here</a>
      <a href="/services/">Work with me</a>
      <a href="https://www.linkedin.com/in/bartoszfrackowiak/">LinkedIn</a>
      <a href="#newsletter">Newsletter</a>
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
        $html .= '<span class="date">' . log_ts($p['publish_ts']) . '</span>';
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
    $out .= '<span class="meta">' . log_ts($p['publish_ts']) . ' · ' . $p['minutes'] . ' min</span>';
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

/** Featured split card for the newest essay. $eager only where it is the LCP image. */
function featured_card(array $p, bool $eager = true): string
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
        $img = $eager ? img_eager($hero['src'], $p['title'], $hero['w'], $hero['h'])
                      : img_lazy($hero['src'], $p['title'], $hero['w'], $hero['h']);
        $html .= '<span class="f2-media">' . $img . '</span>';
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
    require_once __DIR__ . '/captcha.php';
    $captcha = turnstile_enabled()
        ? '<div class="nl-captcha">' . turnstile_widget('newsletter') . '</div>'
        : '';
    $script = turnstile_enabled() ? turnstile_script() : '';
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
    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
    <button type="submit">Subscribe</button>
    {$captcha}
    <span class="nl-note">Double opt-in, one-click unsubscribe. Your address stays here.</span>
  </form>
</div>
{$script}
HTML;
}

/** Shared look of the private dashboard (admin.php): cards + tables. */
function dash_base_css(): string
{
    return <<<'CSS'
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .9rem; margin: 1.2rem 0 1.8rem; }
.scard { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: .95rem 1.15rem; }
.scard b { display: block; font-size: 1.45rem; font-weight: 800; color: var(--ink); }
.scard span { font-family: 'JetBrains Mono', monospace; font-size: .62rem; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); }
table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; font-size: .86rem; }
th, td { text-align: left; padding: .5rem .8rem; border-bottom: 1px solid var(--line); vertical-align: top; }
tr:last-child td { border-bottom: 0; }
th { font-family: 'JetBrains Mono', monospace; font-size: .62rem; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); }
td.r, th.r { text-align: right; }
td.n { font-family: 'JetBrains Mono', monospace; font-size: .78rem; }
td.dim, .dim { color: var(--muted); }
/* On a phone the stat row is the first thing between you and the actual data —
   at full size eight cards fill the whole screen, so they shrink to a strip. */
@media (max-width: 780px) {
  .cards { grid-template-columns: repeat(auto-fit, minmax(104px, 1fr)); gap: .5rem; margin: .8rem 0 1.2rem; }
  .scard { padding: .55rem .65rem; }
  .scard b { font-size: 1.15rem; }
  .scard span { font-size: .55rem; letter-spacing: .06em; }
}
CSS;
}

/**
 * First-party analytics beacon (a.php). Sends one pageview with coarse
 * client context, then cumulative engaged-seconds pings whenever the tab
 * hides (the server keeps the maximum). Respects Do Not Track.
 */
function beacon_script(): string
{
    return <<<'HTML'
<script>
(function () {
  if (navigator.doNotTrack === '1' || !navigator.sendBeacon) return;
  if (/(^|; )bfx=1/.test(document.cookie)) return;   // site owner opted out
  var send = function (o) { navigator.sendBeacon('/a.php', JSON.stringify(o)); };
  var tz = '';
  try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
  send({ p: location.pathname, r: document.referrer, q: location.search,
         l: navigator.language || '', w: (screen && screen.width) || 0, tz: tz });
  var t0 = Date.now(), acc = 0, vis = !document.hidden;
  var tick = function () { if (vis) acc += Date.now() - t0; t0 = Date.now(); };
  document.addEventListener('visibilitychange', function () {
    tick(); vis = !document.hidden;
    if (document.hidden && acc > 999) send({ e: 1, p: location.pathname, d: Math.round(acc / 1000) });
  });
  addEventListener('pagehide', function () {
    tick();
    if (acc > 999) send({ e: 1, p: location.pathname, d: Math.round(acc / 1000) });
  });
})();
</script>
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
