<?php
/**
 * stats.php — private analytics dashboard (data: a.php → content/analytics.db).
 *
 * Access: /stats.php?key=<STATS_KEY>, or any logged-in admin session
 * (admin.php). Read-only. Filters: ?days=7|30|90|365|all and ?path=/… for a
 * per-page drilldown.
 *
 * IP geolocation by DB-IP (https://db-ip.com), CC BY 4.0.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/admin.php';

$key = (string) cfg('stats_key');
if ($key !== '' && hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    // one-time key → stats session, so every link below stays keyless
    dash_grant_stats();
} elseif (!dash_session_active()) {
    http_response_code(404);
    exit('Not found');
}

/* ---------------- inputs ---------------- */

$daysRaw = (string) ($_GET['days'] ?? '30');
$days = $daysRaw === 'all' ? 0 : max(7, min(365, (int) $daysRaw));
$since = $days === 0 ? '0000-00-00' : gmdate('Y-m-d', time() - $days * 86400);
$path = (string) ($_GET['path'] ?? '');
if (!preg_match(AN_PATH_RE, $path)) $path = '';

/** dashboard link that keeps the current filters (auth rides in the session) */
function s_url(array $over = []): string
{
    global $daysRaw, $path;
    $q = array_merge([
        'days' => $daysRaw,
        'path' => $path !== '' ? $path : null,
    ], $over);
    return 'stats.php?' . http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
}

/* ---------------- data ---------------- */

// an_db() creates an empty schema on a fresh install — same as the beacon's
// first hit would — so every query below just returns zeros/empty lists
$db = an_db();
$sum    = an_summary($db, $since, $path);
$daily  = an_daily($db, $since, $path);
$hours  = an_hours($db, $since, $path);
$pages  = $path === '' ? an_top_pages($db, $since) : [];
$recent = an_recent($db, 30, $path);
$sources = an_source_groups($db, $since, $path);
$dims = [];
foreach (['ref', 'country', 'browser', 'os', 'device', 'lang', 'screen', 'tz', 'utm_source', 'utm_campaign'] as $col) {
    $dims[$col] = an_breakdown($db, $since, $col, 12, $path);
}
$maxPv = max(1, ...array_map(fn($d) => (int) $d['pv'], $daily ?: [['pv' => 1]]));
$maxHour = max(1, ...$hours);
$rangeLabel = $days === 0 ? 'all time' : "last {$days} days";

/** one breakdown table; $fmt maps a raw value to a display label */
function s_table(string $title, array $rows, ?callable $fmt = null, string $unit = 'views'): void
{
    echo '<div class="tbl"><h2>' . e($title) . '</h2><table><tr><th></th><th class="r">' . e($unit) . '</th><th class="r">visitors</th></tr>';
    if (!$rows) echo '<tr><td colspan="3" class="dim">no data in range</td></tr>';
    foreach ($rows as $r) {
        $label = $fmt ? $fmt((string) $r['v']) : e((string) $r['v']);
        echo '<tr><td>' . $label . '</td><td class="r n">' . (int) $r['pv'] . '</td><td class="r n">' . (int) $r['uv'] . '</td></tr>';
    }
    echo '</table></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Stats · bfrackowiak.pl</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/site.css">
<style>
.swrap { max-width: 1180px; margin: 2.5rem auto 4rem; padding-inline: 24px; }
.shead { display: flex; flex-wrap: wrap; align-items: baseline; gap: .8rem 1.4rem; margin-bottom: 1.4rem; }
h1 { font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-right: auto; }
.range a, .range span { font-size: .8rem; margin-left: .7rem; } .range span { color: var(--ink); font-weight: 600; }
.live { font-family: 'JetBrains Mono', monospace; font-size: .72rem; color: var(--muted); }
.live b { color: var(--ink); }
.pathchip { font-family: 'JetBrains Mono', monospace; font-size: .75rem; background: var(--surface); border: 1px solid var(--line); border-radius: 999px; padding: .25rem .8rem; }
.pathchip a { margin-left: .5rem; text-decoration: none; }
<?= dash_base_css() ?>
h2 { font-size: .95rem; font-weight: 600; color: var(--ink); margin: 1.5rem 0 .55rem; }
.chart { display: flex; align-items: flex-end; gap: 2px; height: 150px; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: .9rem; }
.chart .bar { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; border-radius: 3px 3px 0 0; background: transparent; }
.chart .pv { background: var(--accent); opacity: .32; border-radius: 3px 3px 0 0; }
.chart .uv { background: var(--accent); opacity: .95; border-radius: 0 0 0 0; }
.chart .bar:hover .pv { opacity: .5; }
.hours { display: flex; align-items: flex-end; gap: 3px; height: 84px; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: .8rem .9rem .4rem; }
.hours div { flex: 1; background: var(--accent); opacity: .55; border-radius: 2px 2px 0 0; min-height: 2px; }
.hours div:hover { opacity: 1; }
.hourlabels { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: .62rem; color: var(--muted); padding: .3rem .4rem 0; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 0 1.6rem; align-items: start; }
table { margin-bottom: 1rem; }
.flag { margin-right: .45rem; }
.wide table { margin-bottom: 1.6rem; }
.recent td { white-space: nowrap; } .recent td.pathcell { white-space: normal; word-break: break-all; }
.foot { margin-top: 2rem; font-size: .72rem; color: var(--muted); }
a.plink { text-decoration: none; }
</style>
</head>
<body>
<div class="swrap">
  <div class="shead">
    <?= eyebrow('First-party analytics', true) ?>
    <span class="live">live now: <b><?= $sum['live'] ?></b> · <a href="/admin.php">admin</a></span>
  </div>
  <div class="shead">
    <h1>bfrackowiak.pl · <?= e($rangeLabel) ?></h1>
    <span class="range">
      <?php foreach (['7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y', 'all' => 'all'] as $d => $label): ?>
        <?php if ($daysRaw === $d): ?><span><?= $label ?></span><?php else: ?><a href="<?= e(s_url(['days' => $d])) ?>"><?= $label ?></a><?php endif; ?>
      <?php endforeach; ?>
    </span>
  </div>
  <?php if ($path !== ''): ?>
    <p class="pathchip">filtering: <?= e($path) ?> <a href="<?= e(s_url(['path' => null])) ?>">× clear</a></p>
  <?php endif; ?>

  <div class="cards">
    <div class="scard"><b><?= number_format($sum['pv']) ?></b><span>Pageviews</span></div>
    <div class="scard"><b><?= number_format($sum['uv']) ?></b><span>Visitors (daily-unlinkable)</span></div>
    <div class="scard"><b><?= an_fmt_secs($sum['avg_time']) ?></b><span>Avg engaged time</span></div>
    <div class="scard"><b><?= number_format($sum['multi']) ?></b><span>Multi-page readers</span></div>
    <div class="scard"><b><?= $sum['countries'] ?></b><span>Countries</span></div>
    <div class="scard"><b><?= $sum['days'] ?></b><span>Active days</span></div>
  </div>

  <h2>Daily traffic <span class="dim">(dark = unique visitors, light = pageviews)</span></h2>
  <div class="chart">
    <?php foreach ($daily as $d): ?>
      <div class="bar" title="<?= e($d['day']) ?>: <?= (int) $d['pv'] ?> views / <?= (int) $d['uv'] ?> visitors">
        <div class="pv" style="height:<?= (int) round(($d['pv'] - $d['uv']) / $maxPv * 100) ?>%"></div>
        <div class="uv" style="height:<?= max(2, (int) round($d['uv'] / $maxPv * 100)) ?>%"></div>
      </div>
    <?php endforeach; ?>
    <?php if (!$daily): ?><p class="dim" style="font-size:.85rem">No data yet. The beacon starts counting as soon as real browsers visit.</p><?php endif; ?>
  </div>

  <h2>Hour of day <span class="dim">(<?= e((string) cfg('timezone', 'Europe/Warsaw')) ?>)</span></h2>
  <div class="hours">
    <?php foreach ($hours as $h => $pv): ?>
      <div style="height:<?= max(2, (int) round($pv / $maxHour * 100)) ?>%" title="<?= sprintf('%02d:00 — %d views', $h, $pv) ?>"></div>
    <?php endforeach; ?>
  </div>
  <div class="hourlabels"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>

  <?php if ($path === ''): ?>
  <div class="wide">
    <h2>Top pages</h2>
    <table><tr><th>Path</th><th class="r">Views</th><th class="r">Visitors</th><th class="r">Avg time</th></tr>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td class="pathcell"><a class="plink" href="<?= e(s_url(['path' => $p['path']])) ?>"><?= e($p['path']) ?></a></td>
        <td class="r n"><?= (int) $p['pv'] ?></td><td class="r n"><?= (int) $p['uv'] ?></td>
        <td class="r n"><?= an_fmt_secs((int) round((float) $p['avg_time'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pages): ?><tr><td colspan="4" class="dim">no data in range</td></tr><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="grid">
    <?php
    s_table('Where from · source groups', $sources);
    s_table('Referrers', $dims['ref']);
    s_table('Countries', $dims['country'], fn(string $cc) => '<span class="flag">' . an_flag($cc) . '</span>' . e(an_country_name($cc)));
    s_table('Timezones (visitor-reported)', $dims['tz']);
    s_table('Browsers', $dims['browser']);
    s_table('Operating systems', $dims['os']);
    s_table('Devices', $dims['device']);
    s_table('Screens', $dims['screen']);
    s_table('Languages', $dims['lang'], fn(string $l) => e(strtoupper($l)));
    s_table('Campaigns · utm_source', $dims['utm_source']);
    if ($dims['utm_campaign']) s_table('Campaigns · utm_campaign', $dims['utm_campaign']);
    ?>
  </div>

  <div class="wide recent">
    <h2>Recent visits</h2>
    <table><tr><th>When</th><th></th><th>Path</th><th>From</th><th>Client</th><th class="r">Read</th></tr>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td class="n"><?= date('m-d H:i', (int) $r['ts']) ?></td>
        <td title="<?= e(an_country_name((string) $r['country'])) ?>"><?= an_flag((string) $r['country']) ?: '·' ?></td>
        <td class="pathcell"><?= e((string) $r['path']) ?></td>
        <td><?= $r['ref'] !== '' ? e((string) $r['ref']) : '<span class="dim">' . e((string) $r['ref_group']) . '</span>' ?></td>
        <td class="dim"><?= e(trim($r['browser'] . ' · ' . $r['os'] . ' · ' . $r['device'], ' ·')) ?></td>
        <td class="r n"><?= an_fmt_secs((int) $r['duration']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="6" class="dim">nothing yet</td></tr><?php endif; ?>
    </table>
  </div>

  <p class="foot">No cookies, no external services. Visitor hashes rotate daily. IP geolocation by <a href="https://db-ip.com">DB-IP</a> (CC BY 4.0)<?= geoip_db_age_days() === null ? ' — <strong>geoip.dat missing: run php tools/geoip_update.php</strong>' : ', database ' . geoip_db_age_days() . ' days old' ?>.</p>
</div>
</body>
</html>
