<?php
/**
 * lib/page.php — page chrome: <head>, navigation, footer (with the analytics beacon).
 */

declare(strict_types=1);

/*
 * Response headers every HTML page sends. They used to live in .htaccess, but on
 * the live host (nginx in front of Apache) those Header lines never reach a
 * response — checked 2026-10-26 — so PHP sends them: hand-written pages through
 * page_head(), generated pages through the prologue the build writes into them.
 * The Stemma film is served from here, not embedded from a video platform, so
 * nothing had to be opened up: default-src 'self' already covers media-src, and
 * the footer's "no cookies, no trackers" stays literally true. HSTS stays
 * without includeSubDomains, as it always was: the homelab answers on a subdomain.
 */
const SECURITY_HEADERS = [
    'X-Content-Type-Options: nosniff',
    'X-Frame-Options: SAMEORIGIN',
    'Referrer-Policy: strict-origin-when-cross-origin',
    'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()',
    'Strict-Transport-Security: max-age=31536000',
    "Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; connect-src 'self'; frame-src 'self' https://challenges.cloudflare.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
];

/** Sends SECURITY_HEADERS, leaving alone any a page already set (preview's DENY, no-referrer). */
function send_security_headers(): void
{
    if (headers_sent()) return;
    $set = array_map(fn(string $h): string => strtolower(strstr($h, ':', true)), headers_list());
    foreach (SECURITY_HEADERS as $h) {
        if (!in_array(strtolower(strstr($h, ':', true)), $set, true)) header($h);
    }
}

/** The same set as a line of PHP for the top of a generated page, which runs no page_head() when served. */
function security_headers_prologue(): string
{
    return '<?php ' . implode(' ', array_map(fn(string $h): string => 'header(' . var_export($h, true) . ');', SECURITY_HEADERS)) . " ?>\n";
}

function page_head(string $title, string $desc, string $canonical, string $extra = '', string $ogImage = ''): string
{
    send_security_headers();
    $t = e($title); $d = e($desc); $c = e($canonical);
    $og = e($ogImage !== '' ? $ogImage : (string) cfg('og_image'));
    $siteName = e((string) cfg('site_name'));
    $author = e((string) cfg('author'));
    $feed = e(cfg('site_url') . '/feed.xml');
    $llms = e(cfg('site_url') . '/llms.txt');
    // article pages inject their own og:type via $extra — only default the rest;
    // same for robots: a page that says noindex (404, token pages) must not also say index
    $ogType = str_contains($extra, 'og:type') ? '' : '<meta property="og:type" content="website">' . "\n";
    // The default card is a known 1200×630; a page that passes its own image (an essay hero,
    // the About portrait) already declares that image's dimensions in $extra, so do not guess
    // for it and do not emit the pair twice.
    $ogDims = ($ogImage === '' && !str_contains($extra, 'og:image:width'))
        ? '<meta property="og:image:width" content="1200">' . "\n"
        . '<meta property="og:image:height" content="630">' . "\n"
        . '<meta property="og:image:alt" content="Field Notes: essays on software architecture, the people around it, and the machine they form together">' . "\n"
        : '';
    $robots = str_contains($extra, 'name="robots"') ? '' : '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">' . "\n";
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$t}</title>
<meta name="description" content="{$d}">
<meta name="author" content="{$author}">
{$robots}<link rel="canonical" href="{$c}">
{$ogType}<meta property="og:title" content="{$t}">
<meta property="og:description" content="{$d}">
<meta property="og:url" content="{$c}">
<meta property="og:site_name" content="{$siteName}">
<meta property="og:locale" content="en_US">
<meta property="og:image" content="{$og}">
{$ogDims}<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#fbfbfd">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0f1014">
<link rel="alternate" type="application/rss+xml" title="Field Notes · RSS" href="{$feed}">
<link rel="alternate" type="text/plain" href="{$llms}" title="LLM context">
<link rel="stylesheet" href="/assets/fonts.css">
<link rel="stylesheet" href="/assets/site.css">
{$extra}
</head>
<body>
HTML;
}

/**
 * Site navigation. $active: 'blog' | 'about' | 'stemma' | 'start' | '' — marks the current place.
 *
 * Three destinations. The blog is the site, so it is the root; Stemma is the
 * project the writing keeps circling; About is the one page about the person.
 * Everything commercial is still reachable from About and from nowhere else,
 * because a reader who smells a funnel stops reading, and the reading is what
 * this site is for.
 */
function site_nav(string $active = ''): string
{
    $blog   = $active === 'blog' ? ' aria-current="page"' : '';
    $stemma = $active === 'stemma' ? ' aria-current="page"' : '';
    $about  = $active === 'about' ? ' aria-current="page"' : '';
    return <<<HTML
<header class="nav">
  <div class="nav-inner">
    <a class="wordmark" href="/">bfrackowiak<span class="tld">.pl</span></a>
    <ul class="nav-links">
      <li><a href="/"{$blog}>Blog</a></li>
      <li><a href="/stemma/"{$stemma}>Stemma</a></li>
      <li><a href="/about/"{$about}>About</a></li>
    </ul>
  </div>
</header>
HTML;
}

function site_footer(): string
{
    $y = date('Y');
    $beacon = beacon_script();
    $liIco = '<svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.327-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>';
    $ghIco = '<svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>';
    return <<<HTML
<footer class="footer">
  <div class="footer-inner">
    <div class="f-brand">
      <a class="wordmark" href="/">bfrackowiak<span class="tld">.pl</span></a>
      <p>Field notes on software architecture, the people around it, and the corporate machine they form together. A new essay most Mondays.</p>
    </div>
    <div class="f-cols">
      <div class="f-col">
        <span class="f-label">Read</span>
        <a href="/">All essays</a>
        <a href="/blog/start/">Start here</a>
      </div>
      <div class="f-col">
        <span class="f-label">Me</span>
        <a href="/about/">About</a>
        <a href="/stemma/">Stemma</a>
        <a href="/about/#contact">Contact</a>
      </div>
      <div class="f-col">
        <span class="f-label">Elsewhere</span>
        <a href="https://www.linkedin.com/in/bartoszfrackowiak/" rel="me">{$liIco}LinkedIn<span class="f-ext">↗</span></a>
        <a href="https://github.com/batas2" rel="me">{$ghIco}GitHub<span class="f-ext">↗</span></a>
        <a href="https://medium.com/@bfrackowiak" rel="me"><span class="f-m">M</span>Medium<span class="f-ext">↗</span></a>
      </div>
    </div>
  </div>
  <div class="footer-base">
    <div class="fb-inner">
      <span>© {$y} Bartosz Frąckowiak · Warsaw</span>
      <span>No cookies, no trackers — analytics is first-party and anonymous.</span>
    </div>
  </div>
</footer>
{$beacon}
</body>
</html>
HTML;
}
