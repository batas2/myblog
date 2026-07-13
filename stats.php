<?php
/**
 * stats.php — private dashboard for the first-party analytics (a.php).
 * Access: /stats.php?key=<STATS_KEY>. Change the key freely; it's read-only.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
define('STATS_KEY', (string) cfg('stats_key'));
define('A_DB', CONTENT_DIR . '/analytics.db');

if (!hash_equals(STATS_KEY, (string) ($_GET['key'] ?? ''))) { http_response_code(404); exit('Not found'); }

$days = max(7, min(90, (int) ($_GET['days'] ?? 30)));
$since = gmdate('Y-m-d', time() - $days * 86400);

$daily = $paths = $refs = [];
$totPv = $totUv = 0;
if (is_file(A_DB)) {
    $db = new PDO('sqlite:' . A_DB, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $daily = $db->query("SELECT day, COUNT(*) pv, COUNT(DISTINCT visitor) uv FROM hits WHERE day >= '$since' GROUP BY day ORDER BY day")->fetchAll(PDO::FETCH_ASSOC);
    $paths = $db->query("SELECT path, COUNT(*) pv, COUNT(DISTINCT visitor) uv FROM hits WHERE day >= '$since' GROUP BY path ORDER BY pv DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
    $refs  = $db->query("SELECT ref, COUNT(*) pv FROM hits WHERE day >= '$since' AND ref != '' GROUP BY ref ORDER BY pv DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($daily as $d) { $totPv += $d['pv']; $totUv += $d['uv']; }
}
$maxPv = max(1, ...array_map(fn($d) => (int) $d['pv'], $daily ?: [['pv' => 1]]));
function e2(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
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
.swrap { max-width: 980px; margin: 3rem auto; padding-inline: 24px; }
.cards { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin: 1.6rem 0 2.2rem; }
.scard { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 1.1rem 1.3rem; }
.scard b { display: block; font-size: 1.7rem; font-weight: 800; color: var(--ink); }
.scard span { font-family: 'JetBrains Mono', monospace; font-size: .68rem; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
.chart { display: flex; align-items: flex-end; gap: 3px; height: 140px; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 1rem; }
.chart div { flex: 1; background: var(--accent); opacity: .75; border-radius: 3px 3px 0 0; min-height: 2px; }
.chart div:hover { opacity: 1; }
table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; margin-bottom: 2rem; font-size: .9rem; }
th, td { text-align: left; padding: .55rem .9rem; border-bottom: 1px solid var(--line); }
th { font-family: 'JetBrains Mono', monospace; font-size: .66rem; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); }
td.n { text-align: right; font-family: 'JetBrains Mono', monospace; font-size: .8rem; }
h1 { font-size: 1.6rem; font-weight: 800; color: var(--ink); }
h2 { font-size: 1rem; font-weight: 600; color: var(--ink); margin: 1.6rem 0 .6rem; }
.range a { font-size: .8rem; margin-left: .8rem; }
</style>
</head>
<body>
<div class="swrap">
  <span class="eyebrow"><span class="blip blip-live"></span>First-party analytics</span>
  <h1 style="margin-top:.6rem">bfrackowiak.pl · last <?= $days ?> days
    <span class="range">
      <a href="?key=<?= e2(STATS_KEY) ?>&days=7">7d</a>
      <a href="?key=<?= e2(STATS_KEY) ?>&days=30">30d</a>
      <a href="?key=<?= e2(STATS_KEY) ?>&days=90">90d</a>
    </span>
  </h1>

  <div class="cards">
    <div class="scard"><b><?= number_format($totPv) ?></b><span>Pageviews</span></div>
    <div class="scard"><b><?= number_format($totUv) ?></b><span>Unique visitors (daily-unlinkable)</span></div>
    <div class="scard"><b><?= count($daily) ?></b><span>Active days</span></div>
  </div>

  <h2>Daily pageviews</h2>
  <div class="chart">
    <?php foreach ($daily as $d): ?>
      <div style="height:<?= max(2, (int) round($d['pv'] / $maxPv * 100)) ?>%" title="<?= e2($d['day']) ?>: <?= (int) $d['pv'] ?> pv / <?= (int) $d['uv'] ?> uv"></div>
    <?php endforeach; ?>
    <?php if (!$daily): ?><p style="color:var(--muted);font-size:.85rem">No data yet. The beacon starts counting as soon as real browsers visit.</p><?php endif; ?>
  </div>

  <h2>Top pages</h2>
  <table><tr><th>Path</th><th style="text-align:right">Views</th><th style="text-align:right">Uniques</th></tr>
  <?php foreach ($paths as $p): ?>
    <tr><td><?= e2($p['path']) ?></td><td class="n"><?= (int) $p['pv'] ?></td><td class="n"><?= (int) $p['uv'] ?></td></tr>
  <?php endforeach; ?>
  </table>

  <h2>Referrers</h2>
  <table><tr><th>Source</th><th style="text-align:right">Views</th></tr>
  <?php foreach ($refs as $r): ?>
    <tr><td><?= e2($r['ref']) ?></td><td class="n"><?= (int) $r['pv'] ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$refs): ?><tr><td colspan="2" style="color:var(--muted)">No external referrers yet.</td></tr><?php endif; ?>
  </table>
</div>
</body>
</html>
