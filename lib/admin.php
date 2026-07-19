<?php
/**
 * lib/admin.php — the admin area's testable core: session auth, CSRF,
 * login rate limiting, publishing-pipeline data, comment moderation,
 * and the rebuild action. The page itself is admin.php.
 *
 * Auth model: one password, stored in config as a password_hash() value
 * ('admin_password_hash'). Empty hash = admin disabled. A logged-in admin
 * session also unlocks stats.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/comment_store.php';

const ADMIN_MAX_FAILS = 5;      // failed logins per IP…
const ADMIN_LOCK_SECS = 900;    // …then locked out for 15 minutes

function admin_enabled(): bool
{
    return (string) cfg('admin_password_hash', '') !== '';
}

/** Start (once) the admin session with hardened cookie settings. */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('bfadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_session_active(): bool
{
    if (!admin_enabled()) return false;
    // no cookie, no session: never mint session files for anonymous probes
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['bfadmin'])) return false;
    admin_session_start();
    return ($_SESSION['admin_ok'] ?? false) === true;
}

/**
 * Dashboard access: a full admin session, or a stats-only session minted by
 * presenting the stats key once (stats.php promotes the key so its links can
 * stay keyless afterwards).
 */
function dash_session_active(): bool
{
    if (admin_session_active()) return true;
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['bfadmin'])) return false;
    admin_session_start();
    return ($_SESSION['stats_ok'] ?? false) === true;
}

/** Promote a valid stats key into a stats-only session. */
function dash_grant_stats(): void
{
    admin_session_start();
    session_regenerate_id(true);
    $_SESSION['stats_ok'] = true;
}

/* ---------------- login / rate limit ---------------- */

function admin_rl_file(string $ip): string
{
    return throttle_path('adm_rl_', 'bfrackowiak-admin', $ip);
}

/** Seconds until this IP may try again (0 = not locked). */
function admin_lockout_remaining(string $ip): int
{
    $f = admin_rl_file($ip);
    if (!is_file($f)) return 0;
    $st = json_decode((string) file_get_contents($f), true) ?: [];
    if (($st['fails'] ?? 0) < ADMIN_MAX_FAILS) return 0;
    return max(0, ((int) ($st['last'] ?? 0)) + ADMIN_LOCK_SECS - time());
}

function admin_note_login(string $ip, bool $ok): void
{
    $f = admin_rl_file($ip);
    if ($ok) { @unlink($f); return; }
    $st = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    // a stale window starts over
    if (time() - (int) ($st['last'] ?? 0) > ADMIN_LOCK_SECS) $st = [];
    $st = ['fails' => (int) ($st['fails'] ?? 0) + 1, 'last' => time()];
    @file_put_contents($f, json_encode($st), LOCK_EX);
}

/** Verify the password and promote the session. */
function admin_login(string $password, string $ip): bool
{
    if (!admin_enabled() || admin_lockout_remaining($ip) > 0) return false;
    $ok = password_verify($password, (string) cfg('admin_password_hash'));
    admin_note_login($ip, $ok);
    if ($ok) {
        admin_session_start();
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
    } else {
        error_log('admin: failed login from ' . $ip);
    }
    return $ok;
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    session_destroy();
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
    $cmd = 'cd ' . escapeshellarg(APP_ROOT) . ' && ' . $bin . ' tools/build.php 2>&1';
    exec($cmd, $out, $rc);
    return [$rc, implode("\n", $out)];
}

function admin_last_build_ts(): ?int
{
    $f = APP_ROOT . '/blog/index.php';
    return is_file($f) ? (int) filemtime($f) : null;
}
