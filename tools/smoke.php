<?php
/**
 * tools/smoke.php — is the live site up, in one piece, and the commit we think it is?
 *
 *   php tools/smoke.php https://bfrackowiak.pl [--sha=<commit>] [--local]
 *
 * Run after every deploy (deploy.sh push, and the pipeline). Reads public URLs
 * only, follows no redirects, sends no forms. One line per check:
 * PASS | FAIL | WARN <name> <detail>. Exit 1 when a blocking check fails.
 *
 * --local is for a copy served by `php -S` (the restore test, a local run): no
 * TLS, no .htaccess, no 404 for unknown paths (php -S answers them with
 * index.php), so those checks are skipped. The copy keeps the production
 * site_url, so redirects may point anywhere and the newest post is fetched from
 * the copy by its path: a local run never calls the live site.
 */

declare(strict_types=1);

const SMOKE_SKIPPED_LOCALLY = ['not_found', 'https_redirect', 'cert', 'content_denied', 'git_hidden', 'deploy_env_hidden'];

/**
 * One GET, no redirects followed.
 * @return array{status:int, headers:array<string,string>, body:string, cert_days:?int}
 */
function smoke_http_fetch(string $url): array
{
    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15,
                   'header' => "User-Agent: bfrackowiak-smoke/1\r\n"],
        'ssl'  => ['capture_peer_cert' => true],
    ]);
    $fp = @fopen($url, 'r', false, $ctx);
    if ($fp === false) return ['status' => 0, 'headers' => [], 'body' => '', 'cert_days' => null];

    $raw = stream_get_meta_data($fp)['wrapper_data'] ?? [];
    $body = (string) stream_get_contents($fp);
    $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
    fclose($fp);

    $status = 0;
    $headers = [];
    foreach ((array) $raw as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $m)) { $status = (int) $m[1]; $headers = []; continue; }
        if (str_contains((string) $line, ':')) {
            [$k, $v] = explode(':', (string) $line, 2);
            $headers[strtolower(trim($k))] = trim($v);
        }
    }
    $days = null;
    if ($cert !== null && ($info = openssl_x509_parse($cert)) && isset($info['validTo_time_t'])) {
        $days = intdiv((int) $info['validTo_time_t'] - time(), 86400);
    }
    return ['status' => $status, 'headers' => $headers, 'body' => $body, 'cert_days' => $days];
}

/** the first <item><link> of an RSS document, or null when the feed does not parse */
function smoke_feed_newest(string $xml): ?string
{
    $doc = new DOMDocument();
    if ($xml === '' || !@$doc->loadXML($xml, LIBXML_NONET)) return null;
    $links = (new DOMXPath($doc))->query('/rss/channel/item[1]/link');
    return ($links && $links->length) ? trim($links->item(0)->textContent) : '';
}

function smoke_xml_ok(string $xml): bool
{
    return $xml !== '' && (bool) @(new DOMDocument())->loadXML($xml, LIBXML_NONET);
}

/**
 * Run every check against $base.
 * @param callable(string):array{status:int, headers:array<string,string>, body:string, cert_days:?int} $fetch
 * @return list<array{name:string, ok:bool, blocking:bool, detail:string}>
 */
function smoke_run(string $base, ?string $sha, bool $local, callable $fetch): array
{
    $base = rtrim($base, '/');
    $https = str_starts_with($base, 'https://');
    $out = [];
    $add = function (string $name, bool $ok, string $detail, bool $blocking = true) use (&$out, $local): void {
        if ($local && in_array($name, SMOKE_SKIPPED_LOCALLY, true)) return;
        $out[] = ['name' => $name, 'ok' => $ok, 'blocking' => $blocking, 'detail' => $detail];
    };
    $get = fn(string $path) => $fetch($base . $path);
    $sameSite = fn(string $loc) => $loc === $base || str_starts_with($loc, $base . '/');

    $home = $get('/');
    $add('home', $home['status'] === 200, "/ → {$home['status']}");

    $blog = $get('/blog/');
    $loc = $blog['headers']['location'] ?? '';
    $add('blog', $blog['status'] === 200 || (in_array($blog['status'], [301, 302, 307, 308], true) && ($local || $sameSite($loc))),
        "/blog/ → {$blog['status']}" . ($loc !== '' ? " {$loc}" : ''));

    $archive = $get('/blog/archive/');
    $add('archive', $archive['status'] === 200, "/blog/archive/ → {$archive['status']}");

    $feed = $get('/feed.xml');
    $newest = $feed['status'] === 200 ? smoke_feed_newest($feed['body']) : null;
    $add('feed', $feed['status'] === 200 && $newest !== null, "/feed.xml → {$feed['status']}" . ($newest === null ? ', does not parse' : ''));
    if ($newest) {
        if ($local) $newest = $base . (parse_url($newest, PHP_URL_PATH) ?: '/');
        $n = $fetch($newest);
        $add('newest', $n['status'] === 200, "{$newest} → {$n['status']}");
    } else {
        $add('newest', false, 'no newest post in the feed');
    }

    $sitemap = $get('/sitemap.xml');
    $add('sitemap', $sitemap['status'] === 200 && smoke_xml_ok($sitemap['body']), "/sitemap.xml → {$sitemap['status']}");

    if (!$local) {
        $missing = '/__smoke-' . bin2hex(random_bytes(4));
        $nf = $get($missing);
        $add('not_found', $nf['status'] === 404, "{$missing} → {$nf['status']}");
    }

    if (!$local) {
        $c = $get('/content/score-notes.json');
        $add('content_denied', $c['status'] === 403, "/content/score-notes.json → {$c['status']}");
    }

    $cfg = $get('/config.php');
    $add('config_silent', in_array($cfg['status'], [403, 404], true) || ($cfg['status'] === 200 && $cfg['body'] === ''),
        "/config.php → {$cfg['status']}, " . strlen($cfg['body']) . ' bytes');

    if (!$local) {
        $g = $get('/.git/HEAD');
        $add('git_hidden', !in_array($g['status'], [0, 200], true), "/.git/HEAD → {$g['status']}");
        $e = $get('/deploy.env');
        $add('deploy_env_hidden', !in_array($e['status'], [0, 200], true), "/deploy.env → {$e['status']}");
    }

    if ($https && !$local) {
        $plain = $fetch('http://' . substr($base, strlen('https://')) . '/');
        $to = $plain['headers']['location'] ?? '';
        $add('https_redirect', in_array($plain['status'], [301, 308], true) && str_starts_with($to, $base),
            "http:// → {$plain['status']} {$to}");
    }

    $want = ['content-security-policy', 'x-content-type-options', 'referrer-policy'];
    if ($https && !$local) $want[] = 'strict-transport-security';
    $absent = array_values(array_filter($want, fn($h) => !isset($home['headers'][$h])));
    $add('headers', $absent === [], $absent === [] ? 'security headers present' : 'missing: ' . implode(', ', $absent));

    if ($https && !$local) {
        $days = $home['cert_days'];
        $add('cert', $days !== null && $days >= 14, $days === null ? 'certificate not readable' : "certificate valid {$days} more day(s)", false);
    }

    $admin = $get('/admin.php');
    $add('admin', in_array($admin['status'], [200, 403], true), "/admin.php → {$admin['status']}");

    if ($sha !== null && $sha !== '') {
        $b = $get('/build.txt');
        $live = preg_match('/^sha=(\S+)$/m', $b['body'], $m) ? $m[1] : '?';
        $add('build_sha', $b['status'] === 200 && $live === $sha, "live {$live}, expected {$sha}");
    }

    return $out;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $base = null;
    $sha = null;
    $local = false;
    foreach (array_slice($argv, 1) as $a) {
        if (str_starts_with($a, '--sha=')) $sha = substr($a, 6);
        elseif ($a === '--local') $local = true;
        elseif (preg_match('#^https?://#', $a)) $base = $a;
        else { fwrite(STDERR, "usage: php tools/smoke.php <base-url> [--sha=<commit>] [--local]\n"); exit(2); }
    }
    if ($base === null) { fwrite(STDERR, "usage: php tools/smoke.php <base-url> [--sha=<commit>] [--local]\n"); exit(2); }

    $failed = 0;
    foreach (smoke_run($base, $sha, $local, 'smoke_http_fetch') as $r) {
        $tag = $r['ok'] ? 'PASS' : ($r['blocking'] ? 'FAIL' : 'WARN');
        if (!$r['ok'] && $r['blocking']) $failed++;
        printf("%s %-18s %s\n", $tag, $r['name'], $r['detail']);
    }
    echo $failed === 0 ? "smoke: all blocking checks passed\n" : "smoke: {$failed} blocking check(s) failed\n";
    exit($failed === 0 ? 0 : 1);
}
