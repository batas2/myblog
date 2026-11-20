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
 * Not every stored row counts. Crawlers that admit it are dropped at the door
 * by an_is_bot(); the ones that do not — headless browsers running the beacon
 * from a UTC-clocked server — are written and then marked (an_looks_automated),
 * as is the author's own past browsing (an_mark). Marked rows stay in the table
 * and out of every total, so the rule that marked them can be revised later.
 *
 * Writers: a.php (beacon), tools/analytics_prune.php (verdicts).
 * Reader: admin.php (stats.php is a redirect shim).
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

/**
 * Is this address inside the given address or CIDR block? IPv4 and IPv6 both,
 * compared on the packed form so notation differences (leading zeros, ::)
 * cannot smuggle a match past a string compare. A block with no /bits is an
 * exact address.
 */
function an_ip_in_block(string $ip, string $block): bool
{
    $block = trim($block);
    if ($block === '') return false;
    [$net, $bits] = array_pad(explode('/', $block, 2), 2, null);

    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton((string) $net);
    // same family only: a v4 address is never inside a v6 block
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
    if ($bits === null || $bits === '') return $ipBin === $netBin;

    if (!preg_match('/^\d{1,3}$/', $bits)) return false;
    $bits = (int) $bits;
    if ($bits > strlen($ipBin) * 8) return false;

    $whole = intdiv($bits, 8);
    if ($whole > 0 && strncmp($ipBin, $netBin, $whole) !== 0) return false;
    $spare = $bits % 8;
    if ($spare === 0) return true;
    $mask = chr((0xFF << (8 - $spare)) & 0xFF);
    return ($ipBin[$whole] & $mask) === ($netBin[$whole] & $mask);
}

/**
 * Traffic from the author's own connection, never counted. Config
 * 'analytics_exclude_ips' holds plain addresses or CIDR blocks, v4 or v6.
 *
 * This complements the opt-out cookie rather than replacing it: the cookie
 * dies with a new browser, an incognito window or a cleared profile, while a
 * static address survives all three. The match happens in memory on the beacon
 * and the address is never written anywhere, so the privacy model is unchanged.
 */
function an_is_excluded_ip(string $ip, ?array $blocks = null): bool
{
    if ($ip === '') return false;
    $blocks ??= (array) cfg('analytics_exclude_ips', []);
    foreach ($blocks as $block) {
        if (is_string($block) && an_ip_in_block($ip, $block)) return true;
    }
    return false;
}

/** Bump when the schema below changes; an_db() fast-paths matching databases. */
const AN_SCHEMA_VERSION = 4;

/** Everything the v1 table lacked — an_db() migrates any older hits table in place. */
const AN_COLUMNS = [
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
    'excl'         => 'INTEGER NOT NULL DEFAULT 0',
];

/** Bookkeeping columns: real, but not dimensions anyone can break down by. */
const AN_NON_DIMENSIONS = ['duration', 'excl'];

/**
 * Why a row does not count. Nothing is ever deleted: an excluded hit stays in
 * the table and simply sits outside every total, so the rule that excluded it
 * can be widened, narrowed or reversed later (an_reclassify()) instead of the
 * traffic being gone for good.
 */
const AN_COUNTED   = 0;
const AN_AUTOMATED = 1;   // headless/scripted traffic the UA filter cannot see
const AN_OWNER     = 2;   // the author's own browsing, marked after the fact

/** Human label for a verdict, for the dashboard and the prune tool. */
function an_excl_label(int $verdict): string
{
    return [AN_COUNTED => 'counted', AN_AUTOMATED => 'automated', AN_OWNER => 'owner'][$verdict] ?? 'excluded';
}

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
    foreach (AN_COLUMNS as $col => $def) {
        if (!in_array($col, $have, true)) $db->exec("ALTER TABLE hits ADD COLUMN {$col} {$def}");
    }
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_day ON hits(day)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_day_visitor ON hits(day, visitor)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_hits_ts ON hits(ts)');
    /* Outbound clicks (schema v4). A separate table rather than a hits column:
       it is a different event with different dimensions, several can happen on
       one pageview, and none of them is a pageview. Same privacy shape as hits
       — the daily-rotating visitor hash, no address, no user-agent — and the
       same "nothing is deleted, excluded rows just sit outside the totals"
       rule, so `excl` is here too. */
    $db->exec("CREATE TABLE IF NOT EXISTS clicks (
        day TEXT NOT NULL, ts INTEGER NOT NULL, path TEXT NOT NULL,
        host TEXT NOT NULL, url TEXT NOT NULL, visitor TEXT NOT NULL,
        excl INTEGER NOT NULL DEFAULT 0)");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_clicks_day ON clicks(day)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_clicks_day_host ON clicks(day, host)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_clicks_day_visitor ON clicks(day, visitor)');
    // history predates the exclusion rule, so judge it once on the way in
    if (!in_array('excl', $have, true)) an_reclassify($db);
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

/**
 * Automation the user-agent string does not admit to. Headless Chrome, scraper
 * farms and preview renderers ship a stock browser UA and do execute the
 * beacon, so an_is_bot() never sees them — but they run on servers, and a
 * server's clock is UTC. Every real consumer device reports a region zone
 * (Europe/Warsaw, America/Los_Angeles); a browser claiming plain 'UTC' is the
 * strongest signal available without fingerprinting anyone.
 *
 * A row with no client context at all — no timezone, no screen, no language —
 * is either a hand-rolled POST to a.php or a pre-v2 row, and counts as neither
 * a reader nor a mystery.
 *
 * The second tell is a contradiction: a desktop OS (Windows, macOS, Linux,
 * ChromeOS) reporting a phone-sized screen. The beacon sends screen.width —
 * the physical display, not the window — and no Mac or PC is 599 pixels
 * wide. That is an emulated mobile viewport under a desktop user agent,
 * which is how scraper frameworks come configured. The 2026-09-11 crawl of
 * the whole archive (23 hits, Chrome on "macOS", phone screen, a region
 * timezone, zero seconds read on every page) is the row that wrote this rule.
 *
 * The cost is Tor Browser, which normalizes to UTC on purpose, and a phone
 * in "request desktop site" mode, which presents as macOS or Linux on a
 * phone screen. That is the trade, and it is reversible: excluded rows are
 * kept, counted separately in admin → System, and an_reclassify() re-runs
 * this rule over all history whenever it changes.
 *
 * Two more contradictions, added 2026-11-20 after a month in which ~60 of 86
 * counted pageviews were automation:
 *
 * The clock and the address name different continents. A US address on an
 * Asia/Bangkok clock, an Irish one on America/Los_Angeles: a residential
 * proxy pool walking the archive one post per IP, and Meta's preview renderer.
 * The comparison is by continent (the zone's first segment against every zone
 * the country has), so a commuter, a VPN exit in the next country and the
 * deprecated aliases browsers still send (Asia/Calcutta) all pass. The cost is
 * a reader on a VPN exit on another continent, or a traveller whose laptop
 * kept the home clock.
 *
 * Windows on a tablet-width screen (600–1099 px). A PC display that narrow in
 * CSS pixels is a scaling setting nobody uses; an emulated viewport is how the
 * 2026-11-16 datacenter-proxy cluster came. Linux and ChromeOS stay counted,
 * because an Android tablet asks for the desktop site as Linux and a
 * Chromebook tablet is that width, and macOS stays because that is an iPad.
 */
const AN_DESKTOP_OS = ['Windows', 'macOS', 'Linux', 'ChromeOS'];

/** Continent of an IANA zone ('Europe/Warsaw' → 'Europe'), '' for Etc/… and bare abbreviations. */
function an_tz_region(string $tz): string
{
    $region = strstr($tz, '/', true);
    return ($region === false || strcasecmp($region, 'Etc') === 0) ? '' : $region;
}

/** False only when the zone's continent is one the country has no zone on; unknowns pass. */
function an_tz_fits_country(string $tz, string $cc): bool
{
    static $regions = [];
    $region = an_tz_region($tz);
    if ($region === '' || !preg_match('/^[A-Z]{2}$/', $cc)) return true;
    if (!isset($regions[$cc])) {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $cc);
        $regions[$cc] = array_unique(array_map('an_tz_region', $zones));
    }
    return !$regions[$cc] || in_array($region, $regions[$cc], true);
}

function an_looks_automated(array $r): bool
{
    $tz = strtoupper(trim((string) ($r['tz'] ?? '')));
    if ($tz !== '' && in_array($tz, ['UTC', 'ETC/UTC', 'GMT', 'ETC/GMT', 'UCT', 'ZULU'], true)) return true;
    if (($r['screen'] ?? '') === 'phone' && in_array($r['os'] ?? '', AN_DESKTOP_OS, true)) return true;
    if (($r['screen'] ?? '') === 'tablet' && ($r['os'] ?? '') === 'Windows') return true;
    if ($tz !== '') return !an_tz_fits_country(trim((string) $r['tz']), strtoupper((string) ($r['country'] ?? '')));
    return ($r['screen'] ?? '') === '' && ($r['lang'] ?? '') === '';
}

/**
 * Re-apply an_looks_automated() to every stored row. An AN_OWNER verdict is
 * left alone — that one came from a human — but automated/counted is decided
 * fresh, so relaxing the rule brings traffic back instead of losing it for
 * good. Returns how many verdicts changed — or, with $apply false, would.
 */
function an_reclassify(PDO $db, bool $apply = true): int
{
    $rows = $db->query('SELECT rowid AS rid, tz, screen, lang, os, country, excl FROM hits')->fetchAll(PDO::FETCH_ASSOC);
    $set = $db->prepare('UPDATE hits SET excl = ? WHERE rowid = ?');
    $changed = 0;
    // the migration calls this from inside the beacon's request; own the
    // transaction only if nobody else already does
    $own = !$db->inTransaction();
    if ($own) $db->beginTransaction();
    foreach ($rows as $r) {
        if ((int) $r['excl'] === AN_OWNER) continue;
        $want = an_looks_automated($r) ? AN_AUTOMATED : AN_COUNTED;
        if ((int) $r['excl'] === $want) continue;
        if ($apply) $set->execute([$want, $r['rid']]);
        $changed++;
    }
    if ($own) $db->commit();
    return $changed;
}

/**
 * Set a verdict on every row in a UTC-day range matching an exact dimension
 * filter, and report how many rows that is. This is how the author's own past
 * browsing gets marked: the visitor hash rotates daily and carries no address,
 * so there is no id to chase — when it happened and what the client looked
 * like is the only handle on "that was me". Counts without writing unless
 * $apply, because the filter is easy to get wrong and rows are not recoverable
 * from anywhere else.
 */
function an_mark(PDO $db, string $from, string $to, array $dims, int $verdict, bool $apply = false): int
{
    $sql = 'day >= ? AND day <= ?';
    $args = [$from, $to];
    foreach ($dims as $col => $val) {
        an_check_col((string) $col);
        $sql .= " AND {$col} = ?";
        $args[] = (string) $val;
    }
    if (!$apply) {
        $st = $db->prepare("SELECT COUNT(*) FROM hits WHERE {$sql}");
        $st->execute($args);
        return (int) $st->fetchColumn();
    }
    $st = $db->prepare("UPDATE hits SET excl = ? WHERE {$sql}");
    $st->execute(array_merge([$verdict], $args));
    return $st->rowCount();
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

/**
 * Which acquisition group a campaign tag belongs to. Medium, DEV and LinkedIn
 * all strip or hide the referrer — interstitials, rel="noreferrer", the lnkd.in
 * wrapper — so a click from a cross-post arrives naked and the utm_source we
 * put on the link ourselves is its only identification. Mapping the tag back to
 * the platform's own group keeps that traffic next to the traffic that did
 * carry a referrer, instead of in a 'campaign' bucket that hides which platform
 * actually worked. Exact matches only: a substring rule would put 'x' in half
 * the lists.
 */
function an_utm_group(string $source): string
{
    $s = strtolower(trim($source));
    if ($s === '') return '';
    foreach ([
        'social' => ['linkedin', 'li', 'lnkd', 'ig', 'instagram', 'facebook', 'fb', 'twitter', 'x', 'threads', 'mastodon', 'bsky', 'reddit', 'telegram', 'tg', 'discord'],
        'blogs'  => ['medium', 'devto', 'dev.to', 'substack', 'hashnode'],
        'news'   => ['hn', 'hackernews', 'ycombinator', 'lobsters', 'dailydev', 'daily.dev'],
        'email'  => ['newsletter', 'email', 'mail', 'rss', 'feed'],
    ] as $group => $tokens) {
        if (in_array($s, $tokens, true)) return $group;
    }
    return 'campaign';
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

/**
 * Insert one enriched pageview row, with the automation verdict decided here so
 * the beacon cannot forget to ask for it.
 */
function an_record(PDO $db, array $r): void
{
    $st = $db->prepare('INSERT INTO hits
        (day, ts, path, ref, visitor, ref_group, country, browser, os, device, lang, screen, tz, utm_source, utm_medium, utm_campaign, duration, excl)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)');
    $st->execute([
        $r['day'], $r['ts'], $r['path'], $r['ref'] ?? '', $r['visitor'],
        $r['ref_group'] ?? '', $r['country'] ?? '', $r['browser'] ?? '', $r['os'] ?? '',
        $r['device'] ?? '', $r['lang'] ?? '', $r['screen'] ?? '', $r['tz'] ?? '',
        $r['utm_source'] ?? '', $r['utm_medium'] ?? '', $r['utm_campaign'] ?? '',
        $r['excl'] ?? (an_looks_automated($r) ? AN_AUTOMATED : AN_COUNTED),
    ]);
}

/**
 * Split a clicked link into (host, url), or null if it is not worth recording.
 *
 * The beacon already filters same-host links, but it is the client and this is
 * the server, so the rule is enforced again here. Query strings and fragments
 * are dropped: every outbound link on this site is one the site itself wrote,
 * so a query adds nothing but the risk of storing something that turns out to
 * carry state.
 *
 * `mailto:` counts, and lands under the host `mailto` — somebody opening a
 * mail client from a page is the strongest intent signal the site can observe,
 * and the only address involved is the author's own.
 *
 * $selfHosts is every name this site answers to. It has to be a list, not the
 * request's Host header: that header carries a port on any non-default one,
 * which no URL's host component ever does, so a single-value comparison
 * silently stops matching the moment the site is served anywhere but :443.
 * an_self_hosts() builds the list.
 */
function an_outbound(string $raw, array $selfHosts): ?array
{
    $raw = trim(substr($raw, 0, 500));
    if ($raw === '') return null;

    if (stripos($raw, 'mailto:') === 0) return ['host' => 'mailto', 'url' => 'mailto:'];

    $parts = @parse_url($raw);
    if (!is_array($parts)) return null;
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host   = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') return null;
    if (in_array($host, $selfHosts, true)) return null;               // not outbound after all

    $url = $scheme . '://' . $host . ($parts['path'] ?? '');
    return ['host' => substr($host, 0, 100), 'url' => substr($url, 0, 300)];
}

/**
 * Every host name that is still "this site": the canonical one from config,
 * its www. form, and whatever the request actually arrived on, port stripped.
 */
function an_self_hosts(string $requestHost = ''): array
{
    $hosts = [];
    $canonical = strtolower((string) parse_url((string) cfg('site_url'), PHP_URL_HOST));
    if ($canonical !== '') {
        $hosts[] = $canonical;
        $hosts[] = str_starts_with($canonical, 'www.') ? substr($canonical, 4) : 'www.' . $canonical;
    }
    $req = strtolower(trim($requestHost));
    if ($req !== '') {
        // strip :port — a URL's host component never carries one
        $req = (string) preg_replace('/:\d+$/', '', $req);
        if ($req !== '') $hosts[] = $req;
    }
    return array_values(array_unique(array_filter($hosts)));
}

/**
 * Record one outbound click.
 *
 * The verdict is inherited from whatever the same visitor's pageviews were
 * judged to be today, because a click carries none of the fields the automation
 * rule reads — no timezone, no screen, no language. A headless browser that
 * follows links therefore stays excluded, and an ordinary reader stays counted,
 * without a second rule that could disagree with the first.
 */
function an_click(PDO $db, array $r): void
{
    $verdict = $r['excl'] ?? null;
    if ($verdict === null) {
        $st = $db->prepare('SELECT MAX(excl) FROM hits WHERE day = ? AND visitor = ?');
        $st->execute([$r['day'], $r['visitor']]);
        $seen = $st->fetchColumn();
        // no pageview from this visitor today: the click arrived without one, which
        // is what a script does and a reader does not
        $verdict = $seen === false || $seen === null ? AN_AUTOMATED : (int) $seen;
    }
    $st = $db->prepare('INSERT INTO clicks (day, ts, path, host, url, visitor, excl) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$r['day'], $r['ts'], $r['path'], $r['host'], $r['url'], $r['visitor'], (int) $verdict]);
}

/**
 * Re-derive every click verdict from the pageviews of the same visitor-day.
 *
 * an_mark() and an_reclassify() work on `hits`, and a click has none of the
 * dimensions those filter on. Rather than teach them a second filter, the
 * clicks follow their pageviews afterwards. Returns rows changed.
 */
function an_sync_click_verdicts(PDO $db): int
{
    $st = $db->prepare('UPDATE clicks SET excl = COALESCE(
            (SELECT MAX(h.excl) FROM hits h WHERE h.day = clicks.day AND h.visitor = clicks.visitor), excl)
        WHERE excl <> COALESCE(
            (SELECT MAX(h.excl) FROM hits h WHERE h.day = clicks.day AND h.visitor = clicks.visitor), excl)');
    $st->execute();
    return $st->rowCount();
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

/**
 * Shared WHERE fragment: counted rows only, period (since ≤ day < until), plus
 * an optional exact-path drilldown. Excluded rows are filtered here rather than
 * at each call site so a new query cannot quietly start counting the author and
 * the scrapers again.
 */
function an_where(string $since, string $path, array &$args, string $until = ''): string
{
    $sql = 'excl = 0 AND day >= ?';
    $args[] = $since;
    if ($until !== '') { $sql .= ' AND day < ?'; $args[] = $until; }
    if ($path !== '') { $sql .= ' AND path = ?'; $args[] = $path; }
    return $sql;
}

/**
 * Totals for a period: pageviews, daily visitors, engaged-time stats, live-now.
 *
 * 'uv' is not unique people. The visitor hash is salted with the UTC day and is
 * unlinkable across days by design, so a distinct count over any period longer
 * than one day is the sum of that period's daily uniques — somebody reading on
 * Monday and Thursday is two. There is no way to do better without a durable
 * identifier, which is the one thing this design refuses to store, so the
 * number is labelled for what it is rather than quietly renamed.
 */
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

    $live = $db->prepare('SELECT COUNT(DISTINCT visitor) FROM hits WHERE excl = 0 AND ts >= ?' . ($path !== '' ? ' AND path = ?' : ''));
    $live->execute($path !== '' ? [time() - 300, $path] : [time() - 300]);
    $row['live'] = (int) $live->fetchColumn();

    // readers who saw more than one page that day (whole-site metric)
    $mArgs = [$since];
    $mSql = 'excl = 0 AND day >= ?';
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
    $ok ??= array_merge(['path', 'ref'], array_diff(array_keys(AN_COLUMNS), AN_NON_DIMENSIONS));
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
        $g = $r['ref'] === '' && $r['utm_source'] !== ''
            ? an_utm_group((string) $r['utm_source'])
            : an_ref_group((string) $r['ref']);
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

/**
 * campaign => source => ['pv' => n, 'uv' => n], for the tagged links only.
 *
 * The campaign tag share_urls() writes is the post slug and the source is the
 * platform it was hand-carried to, so this is the one query that can answer
 * "what did posting that on Medium actually bring back". Untagged traffic has
 * no campaign and is skipped: every syndication host hides the referrer, so an
 * untagged click is indistinguishable from someone typing the address.
 */
function an_campaign_sources(PDO $db, string $since): array
{
    $args = [];
    $where = an_where($since, '', $args);
    $st = $db->prepare("SELECT utm_campaign c, utm_source s, COUNT(*) pv, COUNT(DISTINCT visitor) uv
        FROM hits WHERE {$where} AND utm_campaign != '' AND utm_source != ''
        GROUP BY utm_campaign, utm_source");
    $st->execute($args);

    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['c']][$r['s']] = ['pv' => (int) $r['pv'], 'uv' => (int) $r['uv']];
    }
    return $out;
}

/** Top pages with engagement. */
function an_top_pages(PDO $db, string $since, int $limit = 25): array
{
    $st = $db->prepare('SELECT path, COUNT(*) pv, COUNT(DISTINCT visitor) uv,
            COALESCE(AVG(CASE WHEN duration > 0 THEN duration END), 0) avg_time
        FROM hits WHERE excl = 0 AND day >= ? GROUP BY path ORDER BY pv DESC LIMIT ' . max(1, $limit));
    $st->execute([$since]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------------- outbound clicks ----------------
   an_where() is reused verbatim: `clicks` carries day, path and excl under the
   same names and the same meaning, where `path` is the page the reader left
   from. Passing a $path therefore scopes every exit query to one page, exactly
   as it scopes the pageview queries. */

/**
 * Where readers go when they leave: destination hosts, ranked.
 *
 * Columns are named v/pv/uv so the dashboard's shared breakdown table renders
 * this the same way it renders referrers and countries — the mirror image of
 * the same question, and it should not look like a different kind of answer.
 */
function an_click_hosts(PDO $db, string $since, int $limit = 15, string $path = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args);
    $st = $db->prepare("SELECT host AS v, COUNT(*) pv, COUNT(DISTINCT visitor) uv
        FROM clicks WHERE {$where} GROUP BY host ORDER BY pv DESC LIMIT " . max(1, $limit));
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** The individual links, with the page each was clicked from. */
function an_click_links(PDO $db, string $since, int $limit = 25, string $path = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args);
    $st = $db->prepare("SELECT url, path, COUNT(*) clicks, COUNT(DISTINCT visitor) visitors, MAX(ts) last_ts
        FROM clicks WHERE {$where} GROUP BY url, path ORDER BY clicks DESC LIMIT " . max(1, $limit));
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Totals for the range: clicks, and how many distinct visitor-days produced one. */
function an_click_summary(PDO $db, string $since, string $path = '', string $until = ''): array
{
    $args = [];
    $where = an_where($since, $path, $args, $until);
    $st = $db->prepare("SELECT COUNT(*) clicks, COUNT(DISTINCT visitor) visitors FROM clicks WHERE {$where}");
    $st->execute($args);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    return ['clicks' => (int) ($row['clicks'] ?? 0), 'visitors' => (int) ($row['visitors'] ?? 0)];
}

/** The latest exits, newest first. Excluded rows included, like Recent visits. */
function an_click_recent(PDO $db, int $limit = 20, string $path = ''): array
{
    $sql = 'SELECT ts, day, path, host, url, excl FROM clicks';
    $args = [];
    if ($path !== '') { $sql .= ' WHERE path = ?'; $args[] = $path; }
    $st = $db->prepare($sql . ' ORDER BY ts DESC LIMIT ' . max(1, $limit));
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The standing destinations: the pages that are not essays and do not come and go.
 *
 * Kept as a list rather than derived, because "which pages are permanent" is an editorial
 * fact about the site, not something the database can know. Label first, path second.
 */
const AN_DESTINATIONS = [
    '/'                 => 'Home · essay index',
    '/blog/start/'      => 'Start here',
    '/stemma/'          => 'Stemma · project page',
    '/stemma/changelog/' => 'Stemma · changelog',
    '/stemma/index.md'  => 'Stemma · plain text (agents)',
    '/stemma/changelog/index.md' => 'Stemma · changelog, plain text (agents)',
    '/about/'           => 'About',
    '/services/'        => 'Services (unlisted)',
];

/**
 * Stats for an explicit list of paths, in the order given, including the ones with no
 * traffic in the range.
 *
 * an_top_pages() answers "what is being read" and ranks everything together, so a standing
 * page competes with thirty essays for twenty-five rows and vanishes on a good writing month.
 * This answers the other question — how is each permanent destination doing — which is the
 * one you cannot ask from a leaderboard. A zero row is a real answer here, not a missing one.
 */
function an_paths(PDO $db, string $since, array $paths, string $until = ''): array
{
    if (!$paths) return [];
    $out = [];
    foreach ($paths as $p) $out[$p] = ['path' => $p, 'pv' => 0, 'uv' => 0, 'avg_time' => 0.0];

    $args = [$since];
    $sql = 'excl = 0 AND day >= ?';
    if ($until !== '') { $sql .= ' AND day < ?'; $args[] = $until; }
    $sql .= ' AND path IN (' . implode(',', array_fill(0, count($paths), '?')) . ')';
    foreach ($paths as $p) $args[] = $p;

    $st = $db->prepare("SELECT path, COUNT(*) pv, COUNT(DISTINCT visitor) uv,
            COALESCE(AVG(CASE WHEN duration > 0 THEN duration END), 0) avg_time
        FROM hits WHERE {$sql} GROUP BY path");
    $st->execute($args);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['path']] = $r;
    return array_values($out);
}

/**
 * Latest pageviews, newest first — excluded ones included and flagged. This is
 * the one view that shows them: a filter you cannot watch working is a filter
 * you cannot tune, and the rows it drops are exactly the ones worth eyeballing.
 */
/** The activity list shows every hit in the chosen range up to this many, newest first. */
const AN_RECENT_CAP = 300;

/**
 * Newest hits first, every verdict included — the one reader that does not go
 * through an_where(), because a filter you cannot watch working is a filter
 * you cannot tune. $since (a UTC day) scopes it to the dashboard's range so
 * the range selector means the same thing on this tab as on every other;
 * '' means all of history.
 */
function an_recent(PDO $db, int $limit = 30, string $path = '', string $since = ''): array
{
    [$where, $args] = an_recent_where($since, $path);
    $st = $db->prepare('SELECT ts, path, ref, ref_group, country, browser, os, device, lang, tz, duration, excl FROM hits'
        . $where . ' ORDER BY ts DESC LIMIT ' . max(1, $limit));
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** How many hits an_recent() is choosing from, so the pane can say "newest 300 of 1,204". */
function an_recent_total(PDO $db, string $since = '', string $path = ''): int
{
    [$where, $args] = an_recent_where($since, $path);
    $st = $db->prepare('SELECT COUNT(*) FROM hits' . $where);
    $st->execute($args);
    return (int) $st->fetchColumn();
}

function an_recent_where(string $since, string $path): array
{
    $cond = [];
    $args = [];
    if ($since !== '') { $cond[] = 'day >= ?'; $args[] = $since; }
    if ($path !== '')  { $cond[] = 'path = ?'; $args[] = $path; }
    return [$cond ? ' WHERE ' . implode(' AND ', $cond) : '', $args];
}

/** How a period's rows split across the verdicts, for admin → System. */
function an_excl_counts(PDO $db, string $since): array
{
    $st = $db->prepare('SELECT excl, COUNT(*) n FROM hits WHERE day >= ? GROUP BY excl');
    $st->execute([$since]);
    $out = [AN_COUNTED => 0, AN_AUTOMATED => 0, AN_OWNER => 0];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['excl']] = (int) $r['n'];
    return $out;
}

/**
 * Two independent counts of the same event for one local day: beacon rows
 * written, and beacon requests the web server logged. The log should always be
 * the larger number — it also counts engagement pings and the hits a.php drops
 * on purpose — so rows > requests means one of the two is lossy and neither can
 * be trusted alone. Null when no log directory is configured or that day's file
 * is missing; the files are expected to be named YYYY-MM-DD.log.
 */
function an_log_recon(PDO $db, string $localDay, string $dir = ''): ?array
{
    $dir = $dir !== '' ? $dir : (string) cfg('analytics_access_log_dir', '');
    if ($dir === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDay)) return null;
    $file = rtrim($dir, '/') . '/' . $localDay . '.log';
    $fh = @fopen($file, 'r');
    if ($fh === false) return null;

    $requests = 0;
    while (($line = fgets($fh)) !== false) {
        if (str_contains($line, '"POST /a.php')) $requests++;
    }
    fclose($fh);

    // the log stamps local time, the rows store epochs: compare on the epoch
    $tz = site_tz();
    $from = (new DateTimeImmutable($localDay . ' 00:00:00', $tz))->getTimestamp();
    $to   = (new DateTimeImmutable($localDay . ' 23:59:59', $tz))->getTimestamp();
    $st = $db->prepare('SELECT COUNT(*) FROM hits WHERE ts BETWEEN ? AND ?');
    $st->execute([$from, $to]);
    $rows = (int) $st->fetchColumn();

    return ['day' => $localDay, 'requests' => $requests, 'rows' => $rows, 'ok' => $rows <= $requests];
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
