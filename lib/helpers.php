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
 * rate limits): temp dir + prefix + truncated salted hash — the raw IP never
 * lands on disk.
 */
function throttle_path(string $prefix, string $salt, string $ip): string
{
    return sys_get_temp_dir() . '/' . $prefix . substr(sha1($ip . '|' . $salt), 0, 16);
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
