<?php
/**
 * a.php — first-party, privacy-first analytics beacon. No cookies, no external
 * services, no personal data at rest.
 *
 * The page JS sends navigator.sendBeacon('/a.php', JSON{p: path, r: referrer}).
 * We store: day, timestamp, path, referrer host, and a visitor hash built from
 * (daily-rotating salt + IP + UA). The salt rotation makes visitors unlinkable
 * across days; raw IP/UA are never written.
 *
 * Storage: SQLite at content/analytics.db (web access to content/ is denied
 * via .htaccess). Dashboard: stats.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';

define('A_DB', CONTENT_DIR . '/analytics.db');
define('A_SEED', CONTENT_DIR . '/analytics.seed');

http_response_code(204);
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|headless|lighthouse|python|curl|wget|monitor|scan/i', $ua)) exit;

$raw = file_get_contents('php://input', false, null, 0, 1024);
$in  = json_decode((string) $raw, true);
if (!is_array($in)) exit;

$path = (string) ($in['p'] ?? '');
if (!preg_match('#^/[a-zA-Z0-9\-_/.]{0,200}$#', $path)) exit;

$refHost = '';
if (!empty($in['r']) && is_string($in['r'])) {
    $h = parse_url(substr($in['r'], 0, 500), PHP_URL_HOST);
    if (is_string($h) && $h !== ($_SERVER['HTTP_HOST'] ?? '')) $refHost = substr($h, 0, 100);
}

// one-time random seed, then a salt that rotates daily
if (!is_file(A_SEED)) @file_put_contents(A_SEED, bin2hex(random_bytes(32)), LOCK_EX);
$seed    = (string) @file_get_contents(A_SEED);
$salt    = hash('sha256', gmdate('Y-m-d') . '|' . $seed);
$visitor = substr(hash('sha256', $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua), 0, 16);

try {
    $db = new PDO('sqlite:' . A_DB, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('CREATE TABLE IF NOT EXISTS hits (
        day TEXT NOT NULL, ts INTEGER NOT NULL, path TEXT NOT NULL,
        ref TEXT NOT NULL DEFAULT "", visitor TEXT NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_day ON hits(day)');
    $st = $db->prepare('INSERT INTO hits (day, ts, path, ref, visitor) VALUES (?, ?, ?, ?, ?)');
    $st->execute([gmdate('Y-m-d'), time(), $path, $refHost, $visitor]);
} catch (Throwable $e) {
    // analytics must never break the site; swallow
}
