<?php
/**
 * stats.php — back-compat shim. The analytics dashboard now lives inside
 * /admin.php (Overview/Content/Acquisition/Audience/Activity tabs).
 *
 * Kept so old bookmarks and the key flow still work:
 *   /stats.php?key=<STATS_KEY>  → mints a stats-only session, then redirects
 *   /stats.php?days=…&path=…    → redirects with the filters preserved
 * The #tab fragment survives the redirect on its own — browsers re-apply the
 * original fragment when a Location header carries none.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/admin.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$key = (string) cfg('stats_key');
if ($key !== '' && hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    // one-time key → stats session, so the dashboard stays keyless
    dash_grant_stats();
} elseif (!dash_session_active()) {
    http_response_code(404);
    exit('Not found');
}

$q = array_filter([
    'days' => (string) ($_GET['days'] ?? ''),
    'path' => (string) ($_GET['path'] ?? ''),
], fn($v) => $v !== '');
header('Location: /admin.php' . ($q ? '?' . http_build_query($q) : ''));
exit;
