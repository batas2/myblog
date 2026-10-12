<?php
/**
 * lib/admin.php — the admin area's testable core: session auth, CSRF,
 * login rate limiting, publishing-pipeline data, comment moderation,
 * and the rebuild action. The page itself is admin.php.
 *
 * Auth model — config.php keys:
 *   admin_user             the login name, compared in constant time
 *   admin_password_hash    password_hash() value; either one empty = admin off
 *   admin_totp_secret      base32 secret for a one-time code after the password
 *                          (lib/totp.php); '' = password only
 *   admin_allow_countries  ISO codes the sign-in is open from, e.g. ['PL'];
 *                          [] = anywhere. Judged by the offline GeoIP database
 *                          and re-checked on every request, so a stolen cookie
 *                          stops working the moment it crosses the border.
 *                          Private addresses (local dev, tests) always pass; a
 *                          public one with no known country never does.
 *   admin_alerts           true = mail smtp.to on every sign-in and lockout
 *   admin_trusted_ips      the owner's own networks, addresses or CIDR blocks:
 *                          from there the password alone signs in (no code
 *                          asked), and they neither trigger nor sit out the
 *                          site-wide freeze. [] = the code is asked everywhere.
 *
 * Brute force: ADMIN_MAX_FAILS wrong attempts lock the address for 15 minutes,
 * and every further lock is four times longer (1 h, 4 h, then a day). Above
 * that a site-wide freeze: ADMIN_FLOOD_MAX failures from anywhere inside ten
 * minutes close the form for everyone for a quarter of an hour — except the
 * trusted networks, which neither trigger it nor sit it out. Every attempt
 * lands in content/auth.log.
 *
 * A stats-only session (minted by the stats key via stats.php) unlocks just
 * the analytics tabs. Both session kinds are bound to the network they were
 * opened from and end after ADMIN_SESSION_MAX seconds, or ADMIN_IDLE_MAX idle.
 */

declare(strict_types=1);

require_once __DIR__ . '/comment_store.php';
require_once __DIR__ . '/analytics.php';   // an_ip_in_block() for the trusted networks, and geoip
require_once __DIR__ . '/totp.php';

const ADMIN_MAX_FAILS    = 5;       // wrong attempts per address…
const ADMIN_LOCK_SECS    = 900;     // …then locked out for 15 minutes,
const ADMIN_LOCK_MAX     = 86400;   // quadrupling per repeat, up to a day
const ADMIN_RESET_SECS   = 86400;   // a quiet day forgets an address's history
const ADMIN_FLOOD_MAX    = 30;      // failures from anywhere…
const ADMIN_FLOOD_WINDOW = 600;     // …inside 10 minutes…
const ADMIN_FLOOD_LOCK   = 900;     // …close the sign-in for everyone for 15 minutes
const ADMIN_TOTP_TRIES   = 5;       // wrong codes before the password is asked again
const ADMIN_TOTP_GRACE   = 300;     // seconds to type the code after the password
const ADMIN_SESSION_MAX  = 43200;   // a session lives 12 hours at most…
const ADMIN_IDLE_MAX     = 7200;    // …and ends after 2 hours unused
const ADMIN_LOG_LINES    = 1000;    // auth.log is trimmed back to this many entries
const ADMIN_ALERT_GAP    = 1800;    // at most one lockout mail per half hour

function admin_enabled(): bool
{
    return (string) cfg('admin_user', '') !== '' && (string) cfg('admin_password_hash', '') !== '';
}

function admin_totp_enabled(): bool
{
    return (string) cfg('admin_totp_secret', '') !== '';
}

/** The address a request comes from. The server talks to browsers directly, so REMOTE_ADDR is the truth. */
function admin_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/* ---------------- country rule ---------------- */

/**
 * Is the admin open from this address? Private and reserved addresses cannot
 * be located and cannot arrive from the internet, so they pass (local dev,
 * the test server); a public one must resolve to a listed country — no
 * database, or an address the database does not know, fails closed.
 */
function admin_geo_allowed(string $ip, ?array $allow = null, ?string $dbPath = null): bool
{
    $allow ??= (array) cfg('admin_allow_countries', []);
    $allow = array_map('strtoupper', array_filter($allow, 'is_string'));
    if (!$allow) return true;
    if ($ip === '') return PHP_SAPI === 'cli';   // tools and tests have no peer; a web request always does
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    // a dual-stack socket reports IPv4 peers as ::ffff:a.b.c.d, which PHP files under
    // "reserved" — unwrap first, or every such peer would pass as a LAN address
    if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\x00", 10) . "\xff\xff") $ip = (string) inet_ntop(substr($bin, 12));
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
    return in_array(geoip_country($ip, $dbPath), $allow, true);
}

/* ---------------- sessions ---------------- */

/** Start (once) the admin session with hardened cookie settings. */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // same threat model as the rate-limit markers (helpers.php): the default
    // save_path can be a world-shared tmp on some hosts — keep session files
    // in our own 0700 directory instead
    $sess = CONTENT_DIR . '/.sessions';
    if (!is_dir($sess)) @mkdir($sess, 0700, true);
    if (is_dir($sess) && is_writable($sess)) session_save_path($sess);
    // reject attacker-supplied session ids (fixation defense beyond the login-time regenerate)
    ini_set('session.use_strict_mode', '1');
    // the collector and admin_session_valid() agree on what "too old to keep" means
    ini_set('session.gc_maxlifetime', (string) ADMIN_IDLE_MAX);
    session_name('bfadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => admin_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

/** The network a session is pinned to: the IPv4 address itself, or the IPv6 /64 (phones rotate the host half). */
function admin_net_key(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin === false) return $ip;
    return strlen($bin) === 16 ? bin2hex(substr($bin, 0, 8)) . '/64' : $ip;
}

/** Stamp the current session with a flag and the facts admin_session_valid() will hold it to. */
function admin_session_open(string $flag, string $ip): void
{
    $now = time();
    $_SESSION[$flag]    = true;
    $_SESSION['opened'] = $now;
    $_SESSION['seen']   = $now;
    $_SESSION['net']    = admin_net_key($ip);
}

/**
 * Does the session carry $flag (admin_ok | stats_ok), and is it still good?
 * Good means: young enough, used recently, on the network it was opened
 * from, and from an allowed country. Anything else ends it on the spot.
 */
function admin_session_valid(string $flag): bool
{
    if (!admin_enabled()) return false;
    // no cookie, no session: never mint session files for anonymous probes
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['bfadmin'])) return false;
    $ip = admin_client_ip();
    if (!admin_geo_allowed($ip)) return false;
    admin_session_start();
    if (($_SESSION[$flag] ?? false) !== true) return false;
    $now = time();
    $good = $now - (int) ($_SESSION['opened'] ?? 0) <= ADMIN_SESSION_MAX
         && $now - (int) ($_SESSION['seen'] ?? 0) <= ADMIN_IDLE_MAX
         && hash_equals((string) ($_SESSION['net'] ?? ''), admin_net_key($ip));
    if (!$good) {
        admin_logout();
        return false;
    }
    $_SESSION['seen'] = $now;
    return true;
}

function admin_session_active(): bool
{
    return admin_session_valid('admin_ok');
}

/**
 * Dashboard access: a full admin session, or a stats-only session minted by
 * presenting the stats key once (the stats.php shim promotes the key, then
 * redirects into admin.php's analytics tabs — keyless from there on).
 */
function dash_session_active(): bool
{
    return admin_session_active() || admin_session_valid('stats_ok');
}

/** Promote a valid stats key into a stats-only session. */
function dash_grant_stats(): void
{
    admin_session_start();
    session_regenerate_id(true);
    admin_session_open('stats_ok', admin_client_ip());
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    session_destroy();
    // the browser forgets the id too, so the next request starts clean instead of carrying a dead one
    @setcookie('bfadmin', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => admin_https(), 'httponly' => true, 'samesite' => 'Strict']);
    unset($_COOKIE['bfadmin']);
}

/* ---------------- lockouts ---------------- */

function admin_rl_file(string $ip): string
{
    return throttle_path('adm_rl_', 'bfrackowiak-admin', $ip);
}

function admin_flood_file(): string
{
    $dir = CONTENT_DIR . '/.ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/adm_flood';
}

function admin_rl_read(string $file): array
{
    return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
}

/** Seconds until this address may try again (0 = not locked). */
function admin_lockout_remaining(string $ip): int
{
    return max(0, (int) (admin_rl_read(admin_rl_file($ip))['until'] ?? 0) - time());
}

/** Seconds the site-wide freeze has left (0 = open). */
function admin_flood_remaining(): int
{
    return max(0, (int) (admin_rl_read(admin_flood_file())['until'] ?? 0) - time());
}

/**
 * Is this one of the owner's own networks (config 'admin_trusted_ips', plain
 * addresses or CIDR blocks, v4 and v6)? From there the password alone signs
 * in, and the site-wide freeze neither counts nor refuses the address. A
 * trust decision of its own, deliberately not analytics_exclude_ips: a café
 * added there to keep the stats clean must not also drop the code.
 */
function admin_trusted_ip(string $ip, ?array $blocks = null): bool
{
    if ($ip === '') return false;
    $blocks ??= (array) cfg('admin_trusted_ips', []);
    foreach ($blocks as $b) {
        if (is_string($b) && an_ip_in_block($ip, $b)) return true;
    }
    return false;
}

/** Seconds before this address may attempt a sign-in: its own lock, or the freeze unless it is trusted. */
function admin_closed_for(string $ip): int
{
    return max(admin_lockout_remaining($ip), admin_trusted_ip($ip) ? 0 : admin_flood_remaining());
}

/**
 * Record one attempt for an address. Success clears its history. Every
 * ADMIN_MAX_FAILS failures lock it, each lock four times longer than the
 * last, until a day of quiet resets the count. Returns the lock just
 * imposed in seconds, 0 when none.
 */
function admin_note_login(string $ip, bool $ok): int
{
    $f = admin_rl_file($ip);
    if ($ok) {
        @unlink($f);
        return 0;
    }
    $st = admin_rl_read($f);
    if (time() - (int) ($st['last'] ?? 0) > ADMIN_RESET_SECS) $st = [];
    $st['fails'] = (int) ($st['fails'] ?? 0) + 1;
    $st['last']  = time();
    $lock = 0;
    if ($st['fails'] >= ADMIN_MAX_FAILS) {
        $st['locks'] = (int) ($st['locks'] ?? 0) + 1;
        $lock = (int) min(ADMIN_LOCK_MAX, ADMIN_LOCK_SECS * 4 ** ($st['locks'] - 1));
        $st['until'] = time() + $lock;
        $st['fails'] = 0;
    }
    @file_put_contents($f, json_encode($st), LOCK_EX);
    return $lock;
}

/**
 * Count one failure toward the site-wide freeze: ADMIN_FLOOD_MAX inside
 * ADMIN_FLOOD_WINDOW seconds close the sign-in for ADMIN_FLOOD_LOCK seconds.
 * Attempts made while it is closed are refused before they get here, so a
 * flood that keeps coming cannot keep the door shut forever. Returns the
 * freeze just imposed in seconds, 0 when none.
 */
function admin_note_flood(): int
{
    $f = admin_flood_file();
    $st = admin_rl_read($f);
    $now = time();
    $t = array_values(array_filter((array) ($st['t'] ?? []), fn($x) => is_int($x) && $now - $x <= ADMIN_FLOOD_WINDOW));
    $t[] = $now;
    $lock = 0;
    if (count($t) >= ADMIN_FLOOD_MAX) {
        $lock = ADMIN_FLOOD_LOCK;
        $st['until'] = $now + $lock;
        $t = [];
    }
    $st['t'] = $t;
    @file_put_contents($f, json_encode($st), LOCK_EX);
    return $lock;
}

/* ---------------- sign-in log + alerts ---------------- */

function admin_auth_log_path(): string
{
    return CONTENT_DIR . '/auth.log';
}

/**
 * One JSON line per attempt, newest last: when, what, from where, which
 * browser. The address is kept in the clear — this is the one file where a
 * security log earns it — but never the name or the password that was tried,
 * because what people type into a login box is sometimes the other secret.
 * Trimmed back to ADMIN_LOG_LINES entries now and then.
 */
function admin_auth_log(string $event, string $ip, array $extra = [], ?string $file = null): void
{
    $file ??= admin_auth_log_path();
    $row = ['ts' => time(), 'ev' => $event, 'ip' => $ip, 'cc' => geoip_country($ip)] + $extra;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua !== '') $row['ua'] = substr($ua, 0, 120);
    $line = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    // a flood writes fast; trim on a coin toss, not on every line
    if (random_int(1, 25) === 1 && is_file($file) && filesize($file) > ADMIN_LOG_LINES * 250) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($lines) > ADMIN_LOG_LINES) {
            @file_put_contents($file, implode("\n", array_slice($lines, -ADMIN_LOG_LINES)) . "\n", LOCK_EX);
        }
    }
}

/** The newest $n entries of the sign-in log, newest first. */
function admin_auth_recent(int $n = 25, ?string $file = null): array
{
    $file ??= admin_auth_log_path();
    if (!is_file($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $out = [];
    foreach (array_reverse(array_slice($lines, -$n)) as $l) {
        $r = json_decode($l, true);
        if (is_array($r)) $out[] = $r;
    }
    return $out;
}

/** What an auth.log event means, for the System pane. */
function admin_auth_label(string $ev): string
{
    return match ($ev) {
        'ok'         => 'signed in (password)',
        'totp_ok'    => 'signed in (password + code)',
        'pw_ok'      => 'password accepted, code pending',
        'fail'       => 'wrong login or password',
        'totp_fail'  => 'wrong code',
        'captcha'    => 'spam check failed',
        'locked'     => 'refused: locked out',
        'geo'        => 'refused: country not allowed',
        'logout'     => 'signed out',
        default      => $ev,
    };
}

/**
 * Mail the owner (smtp.to) about a sign-in or a lockout. Off with
 * admin_alerts=false or without an SMTP password. A sign-in is always
 * reported; lockout mails are spaced ADMIN_ALERT_GAP apart so a flood is
 * one message, not a thousand. Never throws — mail must not decide whether
 * a login succeeds.
 */
function admin_alert(string $subject, string $body, bool $spaced = false): bool
{
    if (!cfg('admin_alerts', true)) return false;
    $to = (string) (((array) cfg('smtp', []))['to'] ?? '');
    if ($to === '') return false;
    if ($spaced) {
        $mark = CONTENT_DIR . '/.ratelimit/adm_alert';
        if (is_file($mark) && time() - (int) filemtime($mark) < ADMIN_ALERT_GAP) return false;
        if (!is_dir(dirname($mark))) @mkdir(dirname($mark), 0700, true);
        @touch($mark);
    }
    try {
        require_once __DIR__ . '/mail.php';
        $mail = smtp_mailer();
        if ($mail === null) return false;
        $mail->addAddress($to);
        $mail->Subject = '[' . cfg('site_name', 'admin') . '] ' . $subject;
        $mail->isHTML(false);
        $mail->Body = $body;
        return $mail->send();
    } catch (Throwable $e) {
        error_log('admin: alert mail failed: ' . $e->getMessage());
        return false;
    }
}

/** "203.0.113.7 (PL) · Mozilla/5.0 …" for alert mails. */
function admin_describe(string $ip): string
{
    $cc = geoip_country($ip);
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160);
    return $ip . ($cc !== '' ? " ({$cc})" : '') . ($ua !== '' ? " · {$ua}" : '');
}

/**
 * Bookkeeping for one attempt at $step ('login' = name + password, 'totp' =
 * the code, 'captcha' = the widget): the sign-in log, the address counter,
 * the site-wide counter, and a mail when a lock just kicked in.
 */
function admin_record(string $ip, bool $ok, string $step): void
{
    if ($ok) {
        admin_note_login($ip, true);
        return;
    }
    $lock  = admin_note_login($ip, false);
    $flood = admin_trusted_ip($ip) ? 0 : admin_note_flood();
    $ev = match ($step) { 'login' => 'fail', 'totp' => 'totp_fail', default => $step };
    admin_auth_log($ev, $ip, array_filter(['lock' => $lock, 'flood' => $flood]));
    error_log("admin: failed {$step} from {$ip}");
    if ($lock > 0 || $flood > 0) {
        $lines = ["Sign-in attempts are being refused at " . cfg('site_url', '') . "/admin.php", ''];
        if ($lock > 0) $lines[] = 'Address ' . admin_describe($ip) . ' was locked out for ' . round($lock / 60) . ' min after ' . ADMIN_MAX_FAILS . ' wrong attempts.';
        if ($flood > 0) $lines[] = 'Site-wide freeze: ' . ADMIN_FLOOD_MAX . ' failures inside ' . round(ADMIN_FLOOD_WINDOW / 60) . ' min — the sign-in is closed for everyone for ' . round($flood / 60) . ' min (your own networks excepted).';
        $lines[] = '';
        $lines[] = 'Recent attempts:';
        foreach (admin_auth_recent(10) as $r) {
            $lines[] = sprintf('  %s  %-28s %s %s', date('Y-m-d H:i', (int) $r['ts']), admin_auth_label((string) $r['ev']), (string) $r['ip'], (string) ($r['cc'] ?? ''));
        }
        admin_alert('Sign-in attempts blocked', implode("\n", $lines), true);
    }
}

/** The moment a session becomes an admin session: log it, mail it. $withCode: was a one-time code part of it? */
function admin_signed_in(string $ip, bool $withCode): void
{
    admin_auth_log($withCode ? 'totp_ok' : 'ok', $ip);
    $how = $withCode ? 'password + one-time code'
         : (admin_totp_enabled() ? 'password only — trusted network, no code asked' : 'password only');
    admin_alert('Signed in to the admin', implode("\n", [
        'Someone just signed in to ' . cfg('site_url', '') . '/admin.php',
        '',
        'When: ' . date('Y-m-d H:i:s') . ' (' . cfg('timezone', 'Europe/Warsaw') . ')',
        'From: ' . admin_describe($ip),
        'How:  ' . $how,
        '',
        'Not you? Change admin_password_hash (and admin_totp_secret) in config.php, deploy, and look at Admin → System → Sign-in attempts.',
    ]));
}

/* ---------------- sign-in ---------------- */

/**
 * Name and password, both checked always and in constant time, so a wrong
 * name costs exactly what a wrong password costs and neither can be
 * confirmed on its own. The expected values default to the config.
 */
function admin_credentials_ok(string $user, string $password, ?string $wantUser = null, ?string $hash = null): bool
{
    $wantUser ??= (string) cfg('admin_user', '');
    $hash ??= (string) cfg('admin_password_hash', '');
    $userOk = hash_equals($wantUser, $user);
    $pwOk = password_verify($password, $hash);
    return $userOk && $pwOk && $wantUser !== '' && $hash !== '';
}

/**
 * The password step. Refused outright from a disallowed country or while
 * the address (or the site) is locked. On success the session becomes an
 * admin session — or, with a second factor configured and the address not
 * on a trusted network, is parked one step short until admin_totp_login()
 * accepts the code.
 */
function admin_login(string $user, string $password, string $ip): bool
{
    if (!admin_enabled() || !admin_geo_allowed($ip) || admin_closed_for($ip) > 0) return false;
    $ok = admin_credentials_ok($user, $password);
    admin_record($ip, $ok, 'login');
    if (!$ok) return false;
    admin_session_start();
    session_regenerate_id(true);
    // the code is for everywhere else: on the owner's own network the password is the whole sign-in
    if (admin_totp_enabled() && !admin_trusted_ip($ip)) {
        $_SESSION['totp_pending'] = time();
        $_SESSION['totp_tries']   = 0;
        $_SESSION['totp_net']     = admin_net_key($ip);
        admin_auth_log('pw_ok', $ip);
        return true;
    }
    admin_session_open('admin_ok', $ip);
    admin_signed_in($ip, false);
    return true;
}

/** True while a password has been accepted and the code is still owed (and the offer has not expired). */
function admin_totp_pending(): bool
{
    if (!admin_totp_enabled()) return false;
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['bfadmin'])) return false;
    admin_session_start();
    return isset($_SESSION['totp_pending']) && time() - (int) $_SESSION['totp_pending'] <= ADMIN_TOTP_GRACE;
}

/** Accept a time step once: true (and remembered) only when newer than the last step that got in. */
function admin_totp_fresh(int $step, ?string $file = null): bool
{
    $file ??= CONTENT_DIR . '/.ratelimit/adm_totp_last';
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0700, true);
    $last = is_file($file) ? (int) file_get_contents($file) : 0;
    if ($step <= $last) return false;
    @file_put_contents($file, (string) $step, LOCK_EX);
    return true;
}

/**
 * The code step. One step of clock drift either way is tolerated, a code is
 * never accepted twice, and a wrong code counts like a wrong password.
 * ADMIN_TOTP_TRIES misses (or ADMIN_TOTP_GRACE seconds) send the visitor
 * back to the password.
 */
function admin_totp_login(string $code, string $ip): bool
{
    if (!admin_totp_pending() || !admin_geo_allowed($ip) || admin_closed_for($ip) > 0) return false;
    if (!hash_equals((string) ($_SESSION['totp_net'] ?? ''), admin_net_key($ip))) return false;
    $step = totp_verify((string) cfg('admin_totp_secret'), $code);
    $ok = $step !== null && admin_totp_fresh($step);
    admin_record($ip, $ok, 'totp');
    if (!$ok) {
        $_SESSION['totp_tries'] = (int) ($_SESSION['totp_tries'] ?? 0) + 1;
        if ($_SESSION['totp_tries'] >= ADMIN_TOTP_TRIES) unset($_SESSION['totp_pending'], $_SESSION['totp_tries'], $_SESSION['totp_net']);
        return false;
    }
    unset($_SESSION['totp_pending'], $_SESSION['totp_tries'], $_SESSION['totp_net']);
    session_regenerate_id(true);
    admin_session_open('admin_ok', $ip);
    admin_signed_in($ip, true);
    return true;
}

/* ---------------- CSRF ---------------- */

function admin_csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function admin_csrf_check(?string $token): bool
{
    admin_session_start();
    return is_string($token) && $token !== '' && hash_equals($_SESSION['csrf'] ?? '', $token);
}

/* ---------------- publishing pipeline ---------------- */

/** All posts (drafts + scheduled + published) with comment counts attached. */
function admin_pipeline(?array $counts = null): array
{
    $counts ??= admin_comment_counts();
    $posts = load_posts(true);
    foreach ($posts as &$p) {
        $p['comments'] = $counts[$p['slug']] ?? 0;
        $p['built'] = is_file(APP_ROOT . '/blog/' . $p['slug'] . '/index.php');
    }
    return $posts;
}

/** slug => number of comments on disk. */
function admin_comment_counts(): array
{
    return array_map('count', c_store_all());
}

/** "in 3d 4h" / "in 12m" for a future ts, '' otherwise. */
function admin_countdown(int $ts): string
{
    $d = $ts - time();
    if ($d <= 0) return '';
    if ($d < 3600) return 'in ' . max(1, intdiv($d, 60)) . 'm';
    if ($d < 86400) return 'in ' . intdiv($d, 3600) . 'h ' . intdiv($d % 3600, 60) . 'm';
    return 'in ' . intdiv($d, 86400) . 'd ' . intdiv($d % 86400, 3600) . 'h';
}

/* ---------------- comment moderation ---------------- */

/** Content fingerprint of one stored comment — stable delete target. */
function admin_comment_key(array $c): string
{
    return substr(sha1(($c['date'] ?? '') . '|' . ($c['email'] ?? '') . '|' . ($c['text'] ?? '')), 0, 12);
}

/** Newest-first flat list of all comments: [slug, key, email, text, date]. */
function admin_all_comments(): array
{
    $out = [];
    foreach (c_store_all() as $slug => $list) {
        foreach ($list as $c) {
            $out[] = [
                'slug'  => $slug,
                'key'   => admin_comment_key($c),
                'email' => (string) ($c['email'] ?? ($c['name'] ?? '')),
                'text'  => (string) ($c['text'] ?? ''),
                'date'  => (string) ($c['date'] ?? ''),
            ];
        }
    }
    usort($out, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $out;
}

/**
 * Delete one comment addressed by its content fingerprint (positions shift
 * after every splice, so an index would delete the wrong comment from a
 * stale page). Runs through the shared store, under the writer's lock.
 */
function admin_delete_comment(string $slug, string $key): bool
{
    if (!preg_match('/^[0-9a-f]{12}$/', $key)) return false;
    return c_store_mutate($slug, function (array $list) use ($key): ?array {
        foreach ($list as $i => $c) {
            if (hash_equals($key, admin_comment_key($c))) {
                array_splice($list, $i, 1);
                return $list;
            }
        }
        return null;    // not found — abort, write nothing
    });
}

/**
 * Change a post's publication status from the dashboard. Writes the meta
 * into the markdown source (post_write_status), so it survives rebuilds.
 * Returns [ok, human message].
 */
function admin_set_post_status(string $slug, string $action, string $when = ''): array
{
    if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) return [false, 'Bad slug.'];
    $file = post_source_file($slug);
    if ($file === null) return [false, "No source file for {$slug}."];
    if (!post_write_status($file, $action, $when)) {
        return [false, "Could not update {$slug} (bad action or datetime?)."];
    }
    $post = parse_post_file($file);
    $label = match ($post['status']) {
        'draft'     => 'draft',
        'scheduled' => 'scheduled — goes live ' . date('Y-m-d H:i', $post['publish_ts']),
        default     => 'published',
    };
    return [true, "{$slug} → {$label}."];
}

/* ---------------- rebuild ---------------- */

/** Run the static build (publishes anything whose time has come). [exit code, output]. */
function admin_run_build(): array
{
    if (!function_exists('exec')) {
        return [1, 'exec() is disabled on this host — run "php tools/build.php" over SSH or let the cron do it.'];
    }
    $bin = (string) cfg('php_bin', 'php');
    if (!preg_match('#^[A-Za-z0-9_./-]+$#', $bin)) $bin = 'php';
    $cmd = 'cd ' . escapeshellarg(APP_ROOT) . ' && ' . escapeshellarg($bin) . ' tools/build.php 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
}

function admin_last_build_ts(): ?int
{
    $f = APP_ROOT . '/blog/index.php';
    return is_file($f) ? (int) filemtime($f) : null;
}
