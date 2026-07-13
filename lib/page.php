<?php
/**
 * lib/page.php — page chrome: <head>, navigation, footer (with the analytics beacon).
 */

declare(strict_types=1);

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
