<?php
/**
 * tools/site_check.php — does the generated site hold together?
 *
 *   php tools/site_check.php [root]      root defaults to the repository
 *
 * Run after tools/build.php; reads files only, never the network. Checks that
 * the feed and sitemap parse, that every sitemap URL and every internal link or
 * image in the generated pages points at a file that exists, that every post
 * page carries its title, canonical, description and an og:image that exists,
 * and that nothing unpublished (draft, scheduled) leaked into the output.
 * ERROR lines fail the run (exit 1); WARN lines (oversized media) do not.
 */

declare(strict_types=1);

/** dynamic endpoints: linked from pages, served by PHP, no file per URL */
const SITE_CHECK_ENDPOINTS = ['/comments.php', '/n.php', '/a.php', '/admin.php', '/stats.php', '/review.php', '/preview.php'];
const SITE_CHECK_IMG_MAX = 500 * 1024;
const SITE_CHECK_VIDEO_MAX = 10 * 1024 * 1024;

/** does a site path (query and fragment ignored) map to a file under $root? */
function site_check_resolves(string $root, string $path): bool
{
    $path = rawurldecode((string) preg_replace('/[?#].*$/', '', $path));
    if ($path === '') $path = '/';
    if (in_array($path, SITE_CHECK_ENDPOINTS, true)) return true;
    if (str_contains($path, '..')) return false;
    $f = rtrim($root, '/') . $path;
    if (str_ends_with($path, '/')) return is_file($f . 'index.php') || is_file($f . 'index.html');
    return is_file($f) || is_file($f . '/index.php') || is_file($f . '/index.html');
}

/** the path of a URL on this site, or null for anything external */
function site_check_local_path(string $url, string $origin): ?string
{
    if (str_starts_with($url, '//')) return null;
    if (str_starts_with($url, '/')) return $url;
    if ($origin !== '' && ($url === $origin || str_starts_with($url, $origin . '/'))) {
        return substr($url, strlen($origin)) ?: '/';
    }
    return null;
}

/**
 * slug => status for every post, read by the site's own loader in a separate
 * process (the loader's functions must not collide with a caller's).
 * @return array<string,string>
 */
function site_check_post_states(string $root): array
{
    $code = 'require "lib/config.php"; require "lib/helpers.php"; require "lib/markdown.php"; require "lib/blog.php";'
          . '$o = []; foreach (load_posts(true) as $p) $o[$p["slug"]] = $p["status"]; echo json_encode($o);';
    exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>/dev/null', $out, $rc);
    $states = $rc === 0 ? json_decode(implode('', $out), true) : null;
    if (!is_array($states)) throw new RuntimeException('could not load the posts (lib/blog.php load_posts)');
    return $states;
}

function site_check_xml(string $file): ?DOMDocument
{
    $xml = is_file($file) ? (string) file_get_contents($file) : '';
    $doc = new DOMDocument();
    return ($xml !== '' && @$doc->loadXML($xml, LIBXML_NONET)) ? $doc : null;
}

/**
 * @return list<array{level:string, check:string, detail:string}>
 */
function site_check_run(string $root): array
{
    $root = rtrim($root, '/');
    $found = [];
    $add = function (string $level, string $check, string $detail) use (&$found): void {
        $found[] = ['level' => $level, 'check' => $check, 'detail' => $detail];
    };

    // xml + sitemap_files
    $feed = site_check_xml("{$root}/feed.xml");
    if ($feed === null) $add('error', 'xml', 'feed.xml is missing or does not parse');
    $sitemap = site_check_xml("{$root}/sitemap.xml");
    if ($sitemap === null) $add('error', 'xml', 'sitemap.xml is missing or does not parse');
    $origin = '';
    foreach ($sitemap ? $sitemap->getElementsByTagName('loc') : [] as $loc) {
        $url = trim($loc->textContent);
        $parts = parse_url($url);
        if ($origin === '' && isset($parts['scheme'], $parts['host'])) $origin = "{$parts['scheme']}://{$parts['host']}";
        if (!site_check_resolves($root, $parts['path'] ?? '/')) $add('error', 'sitemap_files', "{$url} has no file");
    }

    // links: every internal href/src in the generated pages
    $pages = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/blog", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->getFilename() === 'index.php') $pages[] = $f->getPathname();
    sort($pages);
    foreach ($pages as $page) {
        $html = (string) file_get_contents($page);
        preg_match_all('/\b(?:href|src)="([^"]+)"/', $html, $m);
        foreach (array_unique($m[1]) as $url) {
            $path = site_check_local_path(html_entity_decode($url), $origin);
            if ($path === null || str_starts_with($path, '/#')) continue;
            if (!site_check_resolves($root, $path)) {
                $add('error', 'links', substr($page, strlen($root)) . " links to {$url}, which does not exist");
            }
        }
    }

    // post_meta + unpublished
    $states = site_check_post_states($root);
    foreach ($states as $slug => $status) {
        $page = "{$root}/blog/{$slug}/index.php";
        if ($status === 'published') {
            $html = is_file($page) ? (string) file_get_contents($page) : '';
            if ($html === '') { $add('error', 'post_meta', "{$slug}: no page at blog/{$slug}/index.php"); continue; }
            if (!preg_match('#<title>\s*\S.*?</title>#s', $html)) $add('error', 'post_meta', "{$slug}: no <title>");
            if (!preg_match('#<link rel="canonical" href="[^"]+"#', $html)) $add('error', 'post_meta', "{$slug}: no canonical link");
            if (!preg_match('#<meta name="description" content="[^"]+"#', $html)) $add('error', 'post_meta', "{$slug}: no description");
            if (!preg_match('#<meta property="og:image" content="([^"]+)"#', $html, $og)) {
                $add('error', 'post_meta', "{$slug}: no og:image");
            } else {
                $path = site_check_local_path(html_entity_decode($og[1]), $origin);
                if ($path !== null && !site_check_resolves($root, $path)) $add('error', 'post_meta', "{$slug}: og:image {$og[1]} does not exist");
            }
            continue;
        }
        if (is_dir("{$root}/blog/{$slug}")) $add('error', 'unpublished', "{$slug} ({$status}) has a page in blog/");
        foreach (['feed.xml', 'sitemap.xml', 'llms-full.txt'] as $file) {
            if (is_file("{$root}/{$file}") && str_contains((string) file_get_contents("{$root}/{$file}"), "/blog/{$slug}/")) {
                $add('error', 'unpublished', "{$slug} ({$status}) appears in {$file}");
            }
        }
    }

    // size: media heavy enough to hurt a page
    foreach (['assets/img' => SITE_CHECK_IMG_MAX, 'assets/video' => SITE_CHECK_VIDEO_MAX] as $dir => $max) {
        if (!is_dir("{$root}/{$dir}")) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getSize() > $max) {
                $add('warn', 'size', sprintf('%s is %d KB (limit %d KB)', substr($f->getPathname(), strlen($root) + 1), intdiv($f->getSize(), 1024), intdiv($max, 1024)));
            }
        }
    }

    return $found;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $root = realpath($argv[1] ?? dirname(__DIR__));
    if ($root === false || !is_dir($root)) { fwrite(STDERR, "usage: php tools/site_check.php [root]\n"); exit(2); }
    try {
        $found = site_check_run($root);
    } catch (Throwable $e) {
        echo "ERROR site_check {$e->getMessage()}\n";
        exit(1);
    }
    $errors = 0;
    foreach ($found as $f) {
        echo strtoupper($f['level']) . " {$f['check']} {$f['detail']}\n";
        if ($f['level'] === 'error') $errors++;
    }
    printf("site check: %d error(s), %d warning(s)\n", $errors, count($found) - $errors);
    exit($errors === 0 ? 0 : 1);
}
