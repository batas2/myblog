<?php
/**
 * a.php — first-party, privacy-first analytics beacon. No cookies, no external
 * services, no personal data at rest.
 *
 * The page JS (beacon_script() in lib/components.php) sends two kinds of
 * sendBeacon POSTs:
 *   pageview   {p: path, r: referrer, q: query, l: language, w: screen width, tz: timezone}
 *   engagement {e: 1, p: path, d: engaged seconds}   — cumulative, on tab hide
 *
 * Stored per pageview: day, ts, path, referrer host + group, UTM tags,
 * country (offline GeoIP, lib/geoip.php), browser/OS/device family, primary
 * language, screen bucket, timezone, and a visitor hash built from a
 * daily-rotating salt + IP + UA (unlinkable across days; raw IP/UA never
 * written). Engagement updates the matching row's duration.
 *
 * Storage: SQLite at content/analytics.db (web access to content/ is denied).
 * Dashboards: stats.php, admin.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/analytics.php';

define('A_SEED', CONTENT_DIR . '/analytics.seed');

header('Cache-Control: no-store');

// self-exclusion toggle for the site owner: /a.php?me=off stops this browser
// from being counted (1-year cookie, auto-refreshed by admin/stats visits),
// /a.php?me=on counts it again. Public on purpose — it only excludes yourself.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($_GET['me'] ?? '', ['off', 'on'], true)) {
    $off = $_GET['me'] === 'off';
    an_optout_cookie($off);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    exit($off ? "This browser is now excluded from analytics.\n" : "This browser is counted again.\n");
}

http_response_code(204);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
if (an_is_opted_out()) exit;
// the owner's own connection, from any browser on it: the cookie above only
// covers the browser that was told, this covers the line it came in on
if (an_is_excluded_ip((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) exit;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (an_is_bot($ua)) exit;

$raw = file_get_contents('php://input', false, null, 0, 2048);
$in  = json_decode((string) $raw, true);
if (!is_array($in)) exit;

$path = (string) ($in['p'] ?? '');
if (!preg_match(AN_PATH_RE, $path)) exit;

// one-time random seed, then a salt that rotates daily → per-day visitor hash
if (!is_file(A_SEED)) @file_put_contents(A_SEED, bin2hex(random_bytes(32)), LOCK_EX);
$seed    = (string) @file_get_contents(A_SEED);
$ip      = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$day     = gmdate('Y-m-d');
$visitor = an_visitor($seed, $day, $ip, $ua);

try {
    $db = an_db();

    // engagement ping: attach seconds to the row the pageview created;
    // sessions straddling the UTC day rollover retry with yesterday's identity
    if (!empty($in['e'])) {
        $secs = (int) ($in['d'] ?? 0);
        if (!an_engage($db, $day, $visitor, $path, $secs)) {
            $yday = gmdate('Y-m-d', time() - 86400);
            an_engage($db, $yday, an_visitor($seed, $yday, $ip, $ua), $path, $secs);
        }
        exit;
    }

    $refHost = '';
    if (!empty($in['r']) && is_string($in['r'])) {
        $h = parse_url(substr($in['r'], 0, 500), PHP_URL_HOST);
        if (is_string($h) && $h !== ($_SERVER['HTTP_HOST'] ?? '')) $refHost = substr($h, 0, 100);
    }

    $utm = an_utm(is_string($in['q'] ?? null) ? substr($in['q'], 0, 500) : '');
    $client = an_ua_parse($ua);

    an_record($db, [
        'day'          => $day,
        'ts'           => time(),
        'path'         => $path,
        'ref'          => $refHost,
        'visitor'      => $visitor,
        'ref_group'    => an_ref_group($refHost),
        'country'      => geoip_country($ip),
        'browser'      => $client['browser'],
        'os'           => $client['os'],
        'device'       => $client['device'],
        'lang'         => an_lang(is_string($in['l'] ?? null) ? $in['l'] : ''),
        'screen'       => an_screen_bucket((int) ($in['w'] ?? 0)),
        'tz'           => an_tz(is_string($in['tz'] ?? null) ? $in['tz'] : ''),
        'utm_source'   => $utm['source'],
        'utm_medium'   => $utm['medium'],
        'utm_campaign' => $utm['campaign'],
    ]);
} catch (Throwable $e) {
    // analytics must never break the site; swallow
}
