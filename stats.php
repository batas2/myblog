<?php
/**
 * stats.php — private analytics dashboard (data: a.php → content/analytics.db).
 *
 * Access: /stats.php?key=<STATS_KEY> once (mints a keyless stats session),
 * or any logged-in admin session. Read-only.
 * Filters: ?days=7|30|90|365|all and ?path=/… for a per-page drilldown.
 *
 * IP geolocation by DB-IP (https://db-ip.com), CC BY 4.0.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/admin.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$key = (string) cfg('stats_key');
if ($key !== '' && hash_equals($key, (string) ($_GET['key'] ?? ''))) {
    // one-time key → stats session, so every link below stays keyless
    dash_grant_stats();
} elseif (!dash_session_active()) {
    http_response_code(404);
    exit('Not found');
}
an_optout_cookie(true);   // whoever reads the stats shouldn't be in them

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
// previous period of the same length, for the trend arrows on the cards
$prev = null;
if ($days > 0) {
    $prev = an_summary($db, gmdate('Y-m-d', time() - 2 * $days * 86400), $path, $since);
}

$maxPv = max(1, ...array_map(fn($d) => (int) $d['pv'], $daily ?: [['pv' => 1]]));
$maxHour = max(1, ...$hours);
$rangeLabel = $days === 0 ? 'all time' : "last {$days} days";

/** ↑ 12% / ↓ 4% vs the previous period ('' when unknowable) */
function s_delta(?array $prev, string $k, int $cur): string
{
    if ($prev === null || ($prev[$k] ?? 0) <= 0) return '';
    $d = ($cur - $prev[$k]) / $prev[$k] * 100;
    if (abs($d) < 1) return '<span class="delta flat">→ 0%</span>';
    $cls = $d > 0 ? 'up' : 'down';
    return '<span class="delta ' . $cls . '">' . ($d > 0 ? '↑' : '↓') . ' ' . abs((int) round($d)) . '%</span>';
}

/** one breakdown table with in-row share bars; $fmt maps a value to a label */
function s_table(string $title, array $rows, ?callable $fmt = null, string $unit = 'views'): void
{
    $tot = max(1, array_sum(array_map(fn($r) => (int) $r['pv'], $rows)));
    $top = max(1, ...array_map(fn($r) => (int) $r['pv'], $rows ?: [['pv' => 1]]));
    echo '<div class="tbl"><h3>' . e($title) . '</h3><table><tr><th></th><th class="r">' . e($unit) . '</th><th class="r">share</th><th class="r">visitors</th></tr>';
    if (!$rows) echo '<tr><td colspan="4" class="dim">no data in range</td></tr>';
    foreach ($rows as $r) {
        $label = $fmt ? $fmt((string) $r['v']) : e((string) $r['v']);
        $bar = (int) round($r['pv'] / $top * 100);
        $share = round($r['pv'] / $tot * 100);
        echo '<tr>'
           . '<td class="cell-bar" style="--w:' . $bar . '%">' . $label . '</td>'
           . '<td class="r n">' . number_format((int) $r['pv']) . '</td>'
           . '<td class="r n dim">' . $share . '%</td>'
           . '<td class="r n">' . (int) $r['uv'] . '</td>'
           . '</tr>';
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
<link rel="stylesheet" href="/assets/fonts.css">
<link rel="stylesheet" href="/assets/site.css">
<style>
<?= dash_base_css() ?>
.swrap { max-width: 1840px; margin: 2rem auto 4rem; padding-inline: 32px; }
.shead { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem 1.4rem; }
h1 { font-size: 1.7rem; font-weight: 800; color: var(--ink); margin-right: auto; letter-spacing: -.01em; }
h2 { font-size: 1.05rem; font-weight: 700; color: var(--ink); margin: 2.6rem 0 .9rem; }
h2 .dim, h3 .dim { font-weight: 400; font-size: .78rem; }
h3 { font-size: .92rem; font-weight: 600; color: var(--ink); margin: 1.4rem 0 .55rem; }
.live { font-family: 'JetBrains Mono', monospace; font-size: .74rem; color: var(--muted); }
.live b { color: var(--ink); }
.seg { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: 999px; padding: .2rem; gap: .1rem; }
.seg a, .seg span { font-size: .8rem; font-weight: 600; padding: .3rem .85rem; border-radius: 999px; text-decoration: none; color: var(--muted); }
.seg span { background: var(--ink); color: var(--bg, #fff); }
.seg a:hover { color: var(--ink); }
.pathchip { font-family: 'JetBrains Mono', monospace; font-size: .78rem; background: var(--surface); border: 1px solid var(--accent); border-radius: 999px; padding: .35rem .95rem; display: inline-block; margin-top: .9rem; }
.pathchip a { margin-left: .6rem; text-decoration: none; }
/* tabbed panes: one concern per screen; no-JS falls back to everything stacked */
.tabs { display: flex; gap: .15rem; overflow-x: auto; position: sticky; top: 0; z-index: 5;
  background: var(--bg, #fbfbfd); padding: .7rem 0 0; margin: 1.5rem 0 1.4rem; border-bottom: 1px solid var(--line); }
.tabs a { padding: .5rem 1.15rem; font-weight: 600; font-size: .87rem; color: var(--muted); text-decoration: none;
  border: 1px solid transparent; border-bottom: none; border-radius: 10px 10px 0 0; white-space: nowrap; }
.tabs a:hover { color: var(--ink); }
.tabs a.active { color: var(--ink); background: var(--surface); border-color: var(--line); position: relative; top: 1px; }
html:not(.js) .tabs { display: none; }
.js .pane { display: none; }
.js .pane.on { display: block; }
.pane > h2:first-child { margin-top: .4rem; }
.scard { position: relative; }
.delta { font-family: 'JetBrains Mono', monospace; font-size: .68rem; font-weight: 500; margin-left: .45rem; vertical-align: .35em; }
.delta.up { color: #0a7a45; } .delta.down { color: #b91c1c; } .delta.flat { color: var(--muted); }
.charts-zone { display: grid; grid-template-columns: 2.2fr 1fr; gap: 1.8rem; align-items: start; }
@media (max-width: 1100px) { .charts-zone { grid-template-columns: 1fr; } }
.chartbox { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.1rem .7rem; }
.chartbox .cap { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: .66rem; color: var(--muted); margin-bottom: .55rem; }
.chart { display: flex; align-items: flex-end; gap: 2px; height: 190px; }
.chart .bar { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; border-radius: 3px 3px 0 0; }
.chart .pv { background: var(--accent); opacity: .28; border-radius: 3px 3px 0 0; }
.chart .uv { background: var(--accent); opacity: .95; }
.chart .bar:hover .pv { opacity: .5; }
.axis { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: .64rem; color: var(--muted); padding-top: .45rem; }
.hours { display: flex; align-items: flex-end; gap: 3px; height: 190px; }
.hours div { flex: 1; background: var(--accent); opacity: .55; border-radius: 2px 2px 0 0; min-height: 2px; }
.hours div:hover { opacity: 1; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 0 1.8rem; align-items: start; }
table { margin-bottom: 1.1rem; }
td.cell-bar { position: relative; background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 14%, transparent) var(--w), transparent var(--w)); }
.tbl h3 { margin-top: 1.2rem; }
.flag { margin-right: .5rem; }
.wide table { margin-bottom: 1.6rem; }
.recent td { white-space: nowrap; } .recent td.pathcell { white-space: normal; word-break: break-all; }
td.n { font-variant-numeric: tabular-nums; }
.foot { margin-top: 2.4rem; font-size: .74rem; color: var(--muted); }
a.plink { text-decoration: none; }
</style>
</head>
<body>
<div class="swrap">
  <div class="shead" style="margin-bottom:.5rem">
    <?= eyebrow('First-party analytics', true) ?>
    <span class="live">on site now: <b><?= $sum['live'] ?></b> · <a href="/admin.php">admin →</a></span>
  </div>
  <div class="shead">
    <h1>bfrackowiak.pl · <?= e($rangeLabel) ?></h1>
    <nav class="seg">
      <?php foreach (['7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y', 'all' => 'all'] as $d => $label): ?>
        <?php if ($daysRaw === $d): ?><span><?= $label ?></span><?php else: ?><a href="<?= e(s_url(['days' => $d])) ?>"><?= $label ?></a><?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
  <?php if ($path !== ''): ?>
    <p class="pathchip">page: <?= e($path) ?> <a href="<?= e(s_url(['path' => null])) ?>">× all pages</a></p>
  <?php endif; ?>

  <nav class="tabs">
    <a href="#overview" class="active">Overview</a>
    <?php if ($path === ''): ?><a href="#content">Content</a><?php endif; ?>
    <a href="#acquisition">Acquisition</a>
    <a href="#audience">Audience</a>
    <a href="#activity">Activity</a>
  </nav>

  <div class="pane on" id="overview">
  <div class="cards">
    <div class="scard"><b><?= number_format($sum['pv']) ?><?= s_delta($prev, 'pv', $sum['pv']) ?></b><span>Pageviews</span></div>
    <div class="scard"><b><?= number_format($sum['uv']) ?><?= s_delta($prev, 'uv', $sum['uv']) ?></b><span>Visitors (daily-unlinkable)</span></div>
    <div class="scard"><b><?= an_fmt_secs($sum['avg_time']) ?><?= s_delta($prev, 'avg_time', $sum['avg_time']) ?></b><span>Avg engaged time</span></div>
    <div class="scard"><b><?= number_format($sum['multi']) ?><?= s_delta($prev, 'multi', $sum['multi']) ?></b><span>Multi-page readers</span></div>
    <div class="scard"><b><?= $sum['countries'] ?></b><span>Countries</span></div>
    <div class="scard"><b><?= $sum['days'] ?></b><span>Active days</span></div>
  </div>
  <?php if ($prev !== null): ?><p class="dim" style="font-size:.72rem;margin-top:-.9rem">trend vs the previous <?= $days ?> days</p><?php endif; ?>

  <h2>Traffic</h2>
  <div class="charts-zone">
    <div class="chartbox">
      <div class="cap"><span>daily — dark = visitors, light = pageviews</span><span>peak <?= number_format($maxPv) ?>/day</span></div>
      <div class="chart">
        <?php foreach ($daily as $d): ?>
          <div class="bar" title="<?= e($d['day']) ?> — <?= (int) $d['pv'] ?> views, <?= (int) $d['uv'] ?> visitors">
            <div class="pv" style="height:<?= (int) round(($d['pv'] - $d['uv']) / $maxPv * 100) ?>%"></div>
            <div class="uv" style="height:<?= max(2, (int) round($d['uv'] / $maxPv * 100)) ?>%"></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$daily): ?><p class="dim" style="font-size:.85rem">No data yet. The beacon starts counting as soon as real browsers visit.</p><?php endif; ?>
      </div>
      <?php if ($daily): ?><div class="axis"><span><?= e($daily[0]['day']) ?></span><span><?= e(end($daily)['day']) ?></span></div><?php endif; ?>
    </div>
    <div class="chartbox">
      <div class="cap"><span>hour of day · <?= e((string) cfg('timezone', 'Europe/Warsaw')) ?></span><span>peak <?= $maxHour ?></span></div>
      <div class="hours">
        <?php foreach ($hours as $h => $pv): ?>
          <div style="height:<?= max(2, (int) round($pv / $maxHour * 100)) ?>%" title="<?= sprintf('%02d:00 — %d views', $h, $pv) ?>"></div>
        <?php endforeach; ?>
      </div>
      <div class="axis"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
    </div>
  </div>
  </div><!-- /overview -->

  <?php if ($path === ''): ?>
  <div class="pane wide" id="content">
    <h2>Content <span class="dim">— click a path to drill down</span></h2>
    <table><tr><th>Path</th><th class="r">Views</th><th class="r">Share</th><th class="r">Visitors</th><th class="r">Avg time</th></tr>
    <?php $topPage = max(1, ...array_map(fn($p) => (int) $p['pv'], $pages ?: [['pv' => 1]])); ?>
    <?php $totPage = max(1, array_sum(array_map(fn($p) => (int) $p['pv'], $pages))); ?>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td class="pathcell cell-bar" style="--w:<?= (int) round($p['pv'] / $topPage * 100) ?>%"><a class="plink" href="<?= e(s_url(['path' => $p['path']])) ?>"><?= e($p['path']) ?></a></td>
        <td class="r n"><?= number_format((int) $p['pv']) ?></td>
        <td class="r n dim"><?= round($p['pv'] / $totPage * 100) ?>%</td>
        <td class="r n"><?= (int) $p['uv'] ?></td>
        <td class="r n"><?= an_fmt_secs((int) round((float) $p['avg_time'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pages): ?><tr><td colspan="5" class="dim">no data in range</td></tr><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="pane" id="acquisition">
  <h2>Acquisition <span class="dim">— where readers come from</span></h2>
  <div class="grid">
    <?php
    s_table('Source groups', $sources);
    s_table('Referrers', $dims['ref']);
    s_table('Campaigns · utm_source', $dims['utm_source']);
    if ($dims['utm_campaign']) s_table('Campaigns · utm_campaign', $dims['utm_campaign']);
    ?>
  </div>
  </div><!-- /acquisition -->

  <div class="pane" id="audience">
  <h2>Audience <span class="dim">— who and from where</span></h2>
  <div class="grid">
    <?php
    s_table('Countries', $dims['country'], fn(string $cc) => '<span class="flag">' . an_flag($cc) . '</span>' . e(an_country_name($cc)));
    s_table('Timezones (visitor-reported)', $dims['tz']);
    s_table('Languages', $dims['lang'], fn(string $l) => e(strtoupper($l)));
    s_table('Browsers', $dims['browser']);
    s_table('Operating systems', $dims['os']);
    s_table('Devices', $dims['device']);
    s_table('Screens', $dims['screen']);
    ?>
  </div>
  </div><!-- /audience -->

  <div class="pane wide recent" id="activity">
    <h2>Recent visits <span class="dim">— last <?= count($recent) ?></span></h2>
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
  </div><!-- /activity -->

  <p class="foot">No cookies for visitors, no external services. Visitor hashes rotate daily. IP geolocation by <a href="https://db-ip.com">DB-IP</a> (CC BY 4.0)<?= geoip_db_age_days() === null ? ' — <strong>geoip.dat missing: run php tools/geoip_update.php</strong>' : ', database ' . geoip_db_age_days() . ' days old' ?>.<br>
  <strong>This browser is excluded from the stats</strong> (owner cookie, refreshed on every visit here) — re-enable via <a href="/a.php?me=on">/a.php?me=on</a>, exclude another device by opening <a href="/a.php?me=off">/a.php?me=off</a> on it or just logging into admin.</p>
</div>
<script>
// tabbed panes; the hash is the state, so views are deep-linkable.
// Without JS the .js class never lands and everything stays stacked.
document.documentElement.classList.add('js');
(function () {
  var tabs = [].slice.call(document.querySelectorAll('.tabs a'));
  var ids = tabs.map(function (a) { return a.hash.slice(1); });
  function show(id) {
    if (ids.indexOf(id) < 0) id = 'overview';
    tabs.forEach(function (a) { a.classList.toggle('active', a.hash.slice(1) === id); });
    ids.forEach(function (p) {
      var el = document.getElementById(p);
      if (el) el.classList.toggle('on', p === id);
    });
  }
  addEventListener('hashchange', function () { show(location.hash.slice(1)); scrollTo(0, 0); });
  show(location.hash.slice(1));
  // switching the date range keeps you on the same tab
  [].forEach.call(document.querySelectorAll('.seg a'), function (a) {
    a.addEventListener('click', function () { if (location.hash) this.href += location.hash; });
  });
})();
</script>
</body>
</html>
