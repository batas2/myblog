<?php
/**
 * lib/helpers.php — tiny shared utilities.
 */

declare(strict_types=1);

/** HTML-escape for output. The single escaping function used everywhere. */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Per-IP throttle marker path (shared idiom of the comment and admin-login
 * rate limits): prefix + truncated salted hash — the raw IP never lands on
 * disk. Markers live under content/.ratelimit (owner-only, web-denied), not
 * the world-shared system temp dir where another tenant could read or plant
 * them on shared hosting.
 */
function throttle_path(string $prefix, string $salt, string $ip): string
{
    $dir = CONTENT_DIR . '/.ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/' . $prefix . substr(sha1($ip . '|' . $salt), 0, 16);
}

/** The site's timezone (config 'timezone'); publish times are interpreted in it. */
function site_tz(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        try {
            $tz = new DateTimeZone((string) cfg('timezone', 'Europe/Warsaw'));
        } catch (Exception) {
            $tz = new DateTimeZone('UTC');
        }
    }
    return $tz;
}
