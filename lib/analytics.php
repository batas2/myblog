<?php
/**
 * lib/analytics.php — first-party analytics: storage, enrichment, queries.
 *
 * Privacy model (unchanged from v1): no cookies, no external services, no
 * personal data at rest. The visitor hash is built from a daily-rotating
 * salt + IP + UA, so visitors are unlinkable across days and the raw IP/UA
 * are never written. New in v2, all of it coarse: country (offline GeoIP),
 * browser/OS/device family, primary language, screen bucket, timezone,
 * referrer group, UTM tags, and engaged seconds per view.
 *
 * Writers: a.php (beacon). Reader: admin.php (stats.php is a redirect shim).
 */

declare(strict_types=1);

require_once __DIR__ . '/geoip.php';

/** Beacon path contract — shared by the writer (a.php) and the drilldown filter (stats.php). */
const AN_PATH_RE = '#^/[a-zA-Z0-9\-_/.]{0,200}$#';

/**
 * Self-exclusion cookie: browsers carrying it are never counted. Set and
 * refreshed (1-year sliding) by every authenticated admin/stats view, and
 * togglable by hand via /a.php?me=off|on. Deliberately JS-readable so the
 * beacon can skip sending at the source; a.php drops carried-over hits as
 * a second line of defense. Not a secret — forging it only removes the
 * forger from the stats.
 */
const AN_OPTOUT_COOKIE = 'bfx';

function an_is_opted_out(): bool
{
    return ($_COOKIE[AN_OPTOUT_COOKIE] ?? '') === '1';
}

/** Set ($on) or clear the exclusion cookie on the current response. */
function an_optout_cookie(bool $on): void
{
    setcookie(AN_OPTOUT_COOKIE, $on ? '1' : '', [
        'expires'  => $on ? time() + 31536000 : time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,   // the beacon JS reads it to skip sending
        'samesite' => 'Lax',
    ]);
}

/** Bump when the schema below changes; an_db() fast-paths matching databases. */
const AN_SCHEMA_VERSION = 2;

/** Columns added by v2 — an_db() migrates any older hits table in place. */
const AN_V2_COLUMNS = [
    'ref_group'    => "TEXT NOT NULL DEFAULT ''",
    'country'      => "TEXT NOT NULL DEFAULT ''",
    'browser'      => "TEXT NOT NULL DEFAULT ''",
    'os'           => "TEXT NOT NULL DEFAULT ''",
    'device'       => "TEXT NOT NULL DEFAULT ''",
    'lang'         => "TEXT NOT NULL DEFAULT ''",
    'screen'       => "TEXT NOT NULL DEFAULT ''",
    'tz'           => "TEXT NOT NULL DEFAULT ''",
    'utm_source'   => "TEXT NOT NULL DEFAULT ''",
    'utm_medium'   => "TEXT NOT NULL DEFAULT ''",
    'utm_campaign' => "TEXT NOT NULL DEFAULT ''",
    'duration'     => 'INTEGER NOT NULL DEFAULT 0',
];

function an_db_path(): string
{
    return CONTENT_DIR . '/analytics.db';
}

/** Open (creating/migrating as needed) the analytics database. */
function an_db(?string $path = null): PDO
{
    $db = new PDO('sqlite:' . ($path ?? an_db_path()), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA busy_timeout=3000');
    // beacon hot path: skip all schema DDL once the version stamp matches
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() === AN_SCHEMA_VERSION) return $db;
    $db->exec("CREATE TABLE IF NOT EXISTS hits (
        day TEXT NOT NULL, ts INTEGER NOT NULL, path TEXT NOT NULL,
        ref TEXT NOT NULL DEFAULT '', visitor TEXT NOT NULL)");
    $have = array_column($db->query('PRAGMA table_info(hits)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (AN_V2_COLUMNS as $col => $def) {
        if (!in_array($col, $have, true)) $db->exec("ALTER TABLE hits ADD COLUMN {$col} {$def}");
    }
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_day ON hits(day)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_day_visitor ON hits(day, visitor)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_ts ON hits(ts)');
    $db->exec('PRAGMA user_version = ' . AN_SCHEMA_VERSION);
    return $db;
}

/* ---------------- enrichment ---------------- */

/** Crawlers, previews, and tooling — never counted. */
function an_is_bot(string $ua): bool
{
    return $ua === '' || preg_match(
        '/bot|crawl|spider|slurp|preview|headless|lighthouse|python|curl|wget|monitor|scan|fetch|http[s]?client|facebookexternalhit|feed|validator|archive|dataprovider|pingdom|uptime/i',
        $ua
    ) === 1;
}

/** Coarse browser / OS / device family from a user-agent string. */
function an_ua_parse(string $ua): array
{
    $browser = 'Other';
    foreach ([
        'Edg[eA]?/'         => 'Edge',
        'OPR/|Opera'        => 'Opera',
        'SamsungBrowser/'   => 'Samsung Internet',
        'Vivaldi'           => 'Vivaldi',
        'Brave'             => 'Brave',
        'DuckDuckGo'        => 'DuckDuckGo',
        'Firefox/|FxiOS'    => 'Firefox',
        'CriOS|Chrome/'     => 'Chrome',
        'Version/.*Safari/' => 'Safari',
        'MSIE|Trident'      => 'IE',
    ] as $re => $name) {
        if (preg_match("#{$re}#", $ua)) { $browser = $name; break; }
    }

    $os = 'Other';
    foreach ([
        'Windows NT'         => 'Windows',
        'iPhone|iPad|iPod'   => 'iOS',
        'Mac OS X|Macintosh' => 'macOS',
        'Android'            => 'Android',
        'CrOS'               => 'ChromeOS',
        'Linux|X11'          => 'Linux',
    ] as $re => $name) {
        if (preg_match("#{$re}#", $ua)) { $os = $name; break; }
    }

    if (preg_match('/iPad|Tablet/', $ua) || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile'))) {
        $device = 'tablet';
    } elseif (preg_match('/Mobi|iPhone|Android/', $ua)) {
        $device = 'mobile';
    } else {
        $device = 'desktop';
    }

    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

/** Traffic source group for a referrer host ('' host = direct). */
function an_ref_group(string $host): string
{
    if ($host === '') return 'direct';
    $host = strtolower($host);
    foreach ([
        'ai'     => ['chatgpt.com', 'chat.openai.com', 'claude.ai', 'gemini.google.com', 'copilot.microsoft.com', 'perplexity.ai', 'phind.com'],
        'search' => ['google.', 'bing.com', 'duckduckgo.com', 'yandex.', 'baidu.com', 'ecosia.org', 'qwant.com', 'startpage.com', 'search.brave.com', 'kagi.com', 'seznam.cz'],
        'social' => ['linkedin.', 'lnkd.in', 'facebook.com', 'fb.com', 'instagram.com', 'threads.net', 't.co', 'twitter.com', 'x.com', 'mastodon', 'bsky.app', 'reddit.com', 'old.reddit.com', 'youtube.com', 't.me', 'telegram', 'whatsapp', 'discord'],
        'news'   => ['news.ycombinator.com', 'lobste.rs', 'slashdot.org', 'daily.dev', 'devurls.com', 'hackernewsletter'],
        'blogs'  => ['medium.com', 'substack.com', 'dev.to', 'hashnode.'],
    ] as $group => $needles) {
        foreach ($needles as $n) {
            if (str_contains($host, $n)) return $group;
        }
    }
    return 'other';
}

/** Coarse viewport class from screen width. */
function an_screen_bucket(int $w): string
{
    if ($w <= 0) return '';
    if ($w < 600) return 'phone';
    if ($w < 1100) return 'tablet';
    if ($w < 1600) return 'laptop';
    return 'desktop';
}

/** Primary language subtag ('en-US' → 'en'), or ''. */
function an_lang(string $raw): string
{
    return preg_match('/^([a-zA-Z]{2,8})\b/', trim($raw), $m) ? strtolower($m[1]) : '';
}

/** Sanitized utm_source / utm_medium / utm_campaign from a query string. */
function an_utm(string $query): array
{
    parse_str(ltrim($query, '?'), $q);
    $clean = static function ($v): string {
        if (!is_string($v)) return '';
        $v = substr(trim($v), 0, 60);
        return preg_replace('/[^\w .\-\/]/', '', $v);
    };
    return [
        'source'   => $clean($q['utm_source'] ?? ($q['ref'] ?? '')),
        'medium'   => $clean($q['utm_medium'] ?? ''),
        'campaign' => $clean($q['utm_campaign'] ?? ''),
    ];
}

/** IANA timezone-ish string or ''. */
function an_tz(string $raw): string
{
    $raw = trim($raw);
    return preg_match('#^[A-Za-z][A-Za-z0-9_+\-/]{0,49}$#', $raw) ? $raw : '';
}

/* ---------------- writing ---------------- */

/** Insert one enriched pageview row. */
function an_record(PDO $db, array $r): void
{
    $st = $db->prepare('INSERT INTO hits
        (day, ts, path, ref, visitor, ref_group, country, browser, os, device, lang, screen, tz, utm_source, utm_medium, utm_campaign, duration)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
    $st->execute([
        $r['day'], $r['ts'], $r['path'], $r['ref'] ?? '', $r['visitor'],
        $r['ref_group'] ?? '', $r['country'] ?? '', $r['browser'] ?? '', $r['os'] ?? '',
        $r['device'] ?? '', $r['lang'] ?? '', $r['screen'] ?? '', $r['tz'] ?? '',
        $r['utm_source'] ?? '', $r['utm_medium'] ?? '', $r['utm_campaign'] ?? '',
    ]);
}

/**
 * Attach engaged seconds to the visitor's latest view of a path on $day.
 * The beacon reports cumulative time on every hide, so keep the maximum.
 * Returns whether a row matched — the caller retries with the previous
 * day's identity for sessions that straddle the UTC day rollover.
 */
function an_engage(PDO $db, string $day, string $visitor, string $path, int $seconds): bool
{
    $st = $db->prepare('UPDATE hits SET duration = MAX(duration, ?)
        WHERE rowid = (SELECT rowid FROM hits WHERE day = ? AND visitor = ? AND path = ? ORDER BY ts DESC LIMIT 1)');
    // bind as INT: SQLite ranks TEXT above INTEGER, so a string-bound "60" would always win MAX()
    $st->bindValue(1, max(0, min($seconds, 1800)), PDO::PARAM_INT);
    $st->bindValue(2, $day);
    $st->bindValue(3, $visitor);
    $st->bindValue(4, $path);
    $st->execute();
    return $st->rowCount() > 0;
}

/** The daily-rotating visitor hash for a given UTC day. */
function an_visitor(string $seed, string $utcDay, string $ip, string $ua): string
{
    $salt = hash('sha256', $utcDay . '|' . $seed);
    return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 16);
}

/* ---------------- reading (dashboard queries) ---------------- */

/** Shared WHERE fragment: period (since ≤ day < until) + optional exact-path drilldown. */
function an_where(string $since, string $path, array &$args, string $until = ''): string
{
    $sql = 'day >= ?';
    $args[] = $since;
    if ($until !== '') { $sql .= ' AND day < ?'; $args[] = $until; }
    if ($path !== '') { $sql .= ' AND path = ?'; $args[] = $path; }
    return $sql;
}

/** Totals for a period: pageviews, uniques, engaged-time stats, live-now. */
function an_summary(PDO $db, string $since, string $path = '', string $until = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args, $until);
    $st = $db->prepare("SELECT COUNT(*) pv, COUNT(DISTINCT visitor) uv,
            COALESCE(AVG(CASE WHEN duration > 0 THEN duration END), 0) avg_time,
            COUNT(DISTINCT day) days,
            COUNT(DISTINCT CASE WHEN country != '' THEN country END) countries
        FROM hits WHERE {$where}");
    $st->execute($args);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $live = $db->prepare('SELECT COUNT(DISTINCT visitor) FROM hits WHERE ts >= ?' . ($path !== '' ? ' AND path = ?' : ''));
    $live->execute($path !== '' ? [time() - 300, $path] : [time() - 300]);
    $row['live'] = (int) $live->fetchColumn();

    // readers who saw more than one page that day (whole-site metric)
    $mArgs = [$since];
    $mSql = 'day >= ?';
    if ($until !== '') { $mSql .= ' AND day < ?'; $mArgs[] = $until; }
    $multi = $db->prepare("SELECT COUNT(*) FROM (SELECT day, visitor FROM hits WHERE {$mSql} GROUP BY day, visitor HAVING COUNT(*) > 1)");
    $multi->execute($mArgs);
    $row['multi'] = (int) $multi->fetchColumn();

    return [
        'pv'        => (int) ($row['pv'] ?? 0),
        'uv'        => (int) ($row['uv'] ?? 0),
        'avg_time'  => (int) round((float) ($row['avg_time'] ?? 0)),
        'days'      => (int) ($row['days'] ?? 0),
        'countries' => (int) ($row['countries'] ?? 0),
        'live'      => $row['live'],
        'multi'     => $row['multi'],
    ];
}

/** Per-day pageviews + uniques, oldest first. */
function an_daily(PDO $db, string $since, string $path = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args);
    $st = $db->prepare("SELECT day, COUNT(*) pv, COUNT(DISTINCT visitor) uv FROM hits WHERE {$where} GROUP BY day ORDER BY day");
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Pageviews per hour of day 0–23 in the site timezone. Grouped per UTC
 * day+hour first, then shifted with that day's own offset, so summer and
 * winter (DST) rows both land in the right local bucket.
 */
function an_hours(PDO $db, string $since, string $path = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args);
    $st = $db->prepare("SELECT strftime('%Y-%m-%d %H', ts, 'unixepoch') dh, COUNT(*) pv
        FROM hits WHERE {$where} GROUP BY dh");
    $st->execute($args);
    $tz = site_tz();
    $utc = new DateTimeZone('UTC');
    $out = array_fill(0, 24, 0);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H|', (string) $r['dh'], $utc);
        if ($dt === false) continue;
        $out[(int) $dt->setTimezone($tz)->format('G')] += (int) $r['pv'];
    }
    return $out;
}

/** Guard: only known columns are ever interpolated into SQL. */
function an_check_col(string $col): void
{
    // derived from the schema so a new dimension is whitelisted automatically
    static $ok = null;
    $ok ??= array_merge(['path', 'ref'], array_diff(array_keys(AN_V2_COLUMNS), ['duration']));
    if (!in_array($col, $ok, true)) throw new InvalidArgumentException("bad column: {$col}");
}

/**
 * Traffic source groups, classified at query time from the stored referrer
 * host (+ the campaign rule: no referrer but a utm_source = 'campaign').
 * Recomputing here instead of trusting the row's ref_group snapshot means
 * additions to an_ref_group() reclassify history automatically.
 */
function an_source_groups(PDO $db, string $since, string $path = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args);
    $st = $db->prepare("SELECT ref, utm_source, visitor, COUNT(*) pv
        FROM hits WHERE {$where} GROUP BY ref, utm_source, visitor");
    $st->execute($args);
    $groups = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $g = $r['ref'] === '' && $r['utm_source'] !== '' ? 'campaign' : an_ref_group((string) $r['ref']);
        $groups[$g]['pv'] = ($groups[$g]['pv'] ?? 0) + (int) $r['pv'];
        $groups[$g]['vis'][$r['visitor']] = true;
    }
    $out = [];
    foreach ($groups as $g => $d) {
        $out[] = ['v' => $g, 'pv' => $d['pv'], 'uv' => count($d['vis'])];
    }
    usort($out, fn($a, $b) => $b['pv'] <=> $a['pv']);
    return $out;
}

/** Top values of one dimension with pv + uv counts. Empty values are skipped. */
function an_breakdown(PDO $db, string $since, string $col, int $limit = 12, string $path = ''): array
{
    an_check_col($col);
    $args = [];
    $where = an_where($since, $path, $args);
    $st = $db->prepare("SELECT {$col} v, COUNT(*) pv, COUNT(DISTINCT visitor) uv
        FROM hits WHERE {$where} AND {$col} != '' GROUP BY {$col} ORDER BY pv DESC LIMIT " . max(1, $limit));
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Top pages with engagement. */
function an_top_pages(PDO $db, string $since, int $limit = 25): array
{
    $st = $db->prepare('SELECT path, COUNT(*) pv, COUNT(DISTINCT visitor) uv,
            COALESCE(AVG(CASE WHEN duration > 0 THEN duration END), 0) avg_time
        FROM hits WHERE day >= ? GROUP BY path ORDER BY pv DESC LIMIT ' . max(1, $limit));
    $st->execute([$since]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Latest pageviews, newest first. */
function an_recent(PDO $db, int $limit = 30, string $path = ''): array
{
    $sql = 'SELECT ts, path, ref, ref_group, country, browser, os, device, lang, duration FROM hits';
    $args = [];
    if ($path !== '') { $sql .= ' WHERE path = ?'; $args[] = $path; }
    $st = $db->prepare($sql . ' ORDER BY ts DESC LIMIT ' . max(1, $limit));
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------- presentation helpers ---------------- */

/** 'PL' → 🇵🇱 (regional-indicator pair), '' for unknown. */
function an_flag(string $cc): string
{
    if (!preg_match('/^[A-Z]{2}$/', $cc)) return '';
    $f = '';
    foreach (str_split($cc) as $ch) $f .= mb_chr(0x1F1E6 + ord($ch) - 65, 'UTF-8');
    return $f;
}

/** English country name for a code (fallback: the code itself). */
function an_country_name(string $cc): string
{
    if (class_exists('Locale')) {
        $n = Locale::getDisplayRegion('-' . $cc, 'en');
        if ($n !== '' && $n !== $cc && stripos($n, 'unknown') === false) return $n;
    }
    return $cc;
}

/** 95 → "1m 35s", 0 → "–". */
function an_fmt_secs(int $s): string
{
    if ($s <= 0) return '–';
    if ($s < 60) return $s . 's';
    return intdiv($s, 60) . 'm ' . ($s % 60) . 's';
}
