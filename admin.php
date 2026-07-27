<?php
/**
 * admin.php — the one private dashboard: control room + first-party analytics.
 *
 * Login: password checked against config 'admin_password_hash'
 * (php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;").
 * Empty hash = admin disabled, this page only shows setup instructions.
 *
 * Access tiers:
 *  - admin session (password login) → every tab
 *  - stats session (stats key presented once at /stats.php?key=…, which
 *    redirects here) → the analytics tabs only, read-only, no admin data
 *
 * What it does:
 *  - publishing pipeline: change any post's status right here — publish now,
 *    back to draft, or schedule for a specific moment. The change is written
 *    into the markdown source (Status/Publish meta lines) and the site is
 *    rebuilt immediately, so it takes effect in one click.
 *    NOTE: posts are authored locally and rsynced; a status changed on the
 *    SERVER lasts until the next deploy overwrites content/posts — mirror the
 *    change in the local file (or run the same action locally) before deploying.
 *  - "Rebuild site now": runs tools/build.php (server cron does the same)
 *  - analytics (data: a.php → content/analytics.db): overview, content,
 *    acquisition, audience, recent activity; ?days=7|30|90|365|all and
 *    ?path=/… for a per-page drilldown. IP geolocation by DB-IP, CC BY 4.0.
 *  - comment moderation (runtime data on the server, safe to mutate here)
 *  - newsletter dashboard: list health, weekly growth, send ledger, and
 *    per-subscriber actions (manual confirm for lost mails, GDPR removal).
 *    The subscriber DB lives on the server only — mutations here are real.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/newsletter.php';
require_once __DIR__ . '/lib/admin.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

/* ---------------- actions (PRG: every POST redirects) ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_enabled()) {
    admin_session_start();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        $ok = admin_login((string) ($_POST['password'] ?? ''), $ip);
        // the owner's browser never counts itself: set the exclusion cookie on login
        if ($ok) an_optout_cookie(true);
        header('Location: /admin.php' . ($ok ? '' : '?err=login'));
        exit;
    }

    if (!admin_session_active() || !admin_csrf_check($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Forbidden');
    }

    if ($action === 'logout') {
        admin_logout();
        header('Location: /admin.php');
        exit;
    }
    if ($action === 'rebuild') {
        [$rc, $out] = admin_run_build();
        $_SESSION['flash'] = ($rc === 0 ? "Site rebuilt.\n" : "BUILD FAILED (exit {$rc}).\n") . $out;
        header('Location: /admin.php#pipeline');
        exit;
    }
    if ($action === 'setstatus') {
        $when = str_replace('T', ' ', (string) ($_POST['when'] ?? ''));
        [$ok, $msg] = admin_set_post_status(
            (string) ($_POST['slug'] ?? ''),
            (string) ($_POST['status_action'] ?? ''),
            $when
        );
        if ($ok) {
            [$rc, $out] = admin_run_build();
            $msg .= $rc === 0 ? "\nSite rebuilt — change is live." : "\nBUILD FAILED (exit {$rc}):\n{$out}";
        }
        $_SESSION['flash'] = $msg;
        header('Location: /admin.php#pipeline');
        exit;
    }
    if ($action === 'delcomment') {
        $done = admin_delete_comment((string) ($_POST['slug'] ?? ''), (string) ($_POST['key'] ?? ''));
        $_SESSION['flash_c'] = $done ? 'Comment deleted.' : 'Comment not found (already gone?).';
        header('Location: /admin.php#comments');
        exit;
    }
    // subscriber moderation reuses the tested core: confirm for "the mail never
    // came" support requests, delete for GDPR erasure — both keyed by token
    if ($action === 'nlconfirm' || $action === 'nldelete') {
        $token = (string) ($_POST['token'] ?? '');
        $done = false;
        try {
            $done = $action === 'nlconfirm' ? nl_confirm(nl_db(), $token) : nl_unsubscribe(nl_db(), $token);
        } catch (Throwable) {
        }
        $_SESSION['flash_n'] = $done
            ? ($action === 'nlconfirm' ? 'Subscriber confirmed.' : 'Subscriber removed.')
            : 'Subscriber not found (already gone?).';
        header('Location: /admin.php#newsletter');
        exit;
    }
    header('Location: /admin.php');
    exit;
}

$isAdmin = admin_session_active();
$isDash  = dash_session_active();   // admin, or a stats session minted via /stats.php?key=…
$flash = $flashC = $flashN = '';
if ($isDash) an_optout_cookie(true);   // whoever reads the dashboards shouldn't be in the stats
if ($isAdmin) {
    $flash  = (string) ($_SESSION['flash'] ?? '');
    $flashC = (string) ($_SESSION['flash_c'] ?? '');
    $flashN = (string) ($_SESSION['flash_n'] ?? '');
    unset($_SESSION['flash'], $_SESSION['flash_c'], $_SESSION['flash_n']);
}

/* ---------------- admin data (full session only) ---------------- */

$pipeline = $comments = [];
$sum7 = $nl = null;
$statusCounts = ['published' => 0, 'scheduled' => 0, 'draft' => 0];
if ($isAdmin) {
    $comments = admin_all_comments();
    $pipeline = admin_pipeline(array_count_values(array_column($comments, 'slug')));
    foreach ($pipeline as $p) $statusCounts[$p['status']]++;
    if (is_file(an_db_path())) {
        try { $sum7 = an_summary(an_db(), gmdate('Y-m-d', time() - 7 * 86400)); } catch (Throwable) { $sum7 = null; }
    }
    try {
        $ndb = nl_db();
        $nl = ['stats' => nl_stats($ndb), 'weekly' => nl_weekly($ndb), 'subs' => nl_all($ndb), 'sends' => nl_sends($ndb)];
    } catch (Throwable) { $nl = null; }   // store unreadable: the pane says so, the rest of admin still works
}
$lockout = admin_lockout_remaining($ip);

/* ---------------- analytics data (any dashboard session) ---------------- */

$daysRaw = (string) ($_GET['days'] ?? '30');
$days = $daysRaw === 'all' ? 0 : max(7, min(365, (int) $daysRaw));
$since = $days === 0 ? '0000-00-00' : gmdate('Y-m-d', time() - $days * 86400);
$path = (string) ($_GET['path'] ?? '');
if (!preg_match(AN_PATH_RE, $path)) $path = '';
$rangeLabel = $days === 0 ? 'all time' : "last {$days} days";

$an = null;
if ($isDash) {
    // an_db() creates an empty schema on a fresh install — same as the beacon's
    // first hit would — so every query below just returns zeros/empty lists
    try {
        $adb = an_db();
        $dims = [];
        foreach (['ref', 'country', 'browser', 'os', 'device', 'lang', 'screen', 'tz', 'utm_source', 'utm_campaign'] as $col) {
            $dims[$col] = an_breakdown($adb, $since, $col, 12, $path);
        }
        $an = [
            'sum'     => an_summary($adb, $since, $path),
            'daily'   => an_daily($adb, $since, $path),
            'hours'   => an_hours($adb, $since, $path),
            'pages'   => $path === '' ? an_top_pages($adb, $since) : [],
            'recent'  => an_recent($adb, 30, $path),
            'sources' => an_source_groups($adb, $since, $path),
            'dims'    => $dims,
            // previous period of the same length, for the trend arrows on the cards
            'prev'    => $days > 0 ? an_summary($adb, gmdate('Y-m-d', time() - 2 * $days * 86400), $path, $since) : null,
        ];
    } catch (Throwable) { $an = null; }
}

/* ---------------- editorial scores (admin only) ---------------- */
/* Assembled after the analytics block so the Scores tab honours the same date
   range: the whole point of the view is scores standing next to what the page
   actually did in that window. */

$scoreRows = [];
$scoreCorr = null;
$scoreSort = (string) ($_GET['sort'] ?? 'reach');
if ($isAdmin) {
    $pages = [];
    if (is_file(an_db_path())) {
        // every post, not just the top 25 — an unread post is exactly the row we need
        try { $pages = an_top_pages(an_db(), $since, 500); } catch (Throwable) { $pages = []; }
    }
    $scoreRows = sc_merge_analytics($pipeline, $pages);
    $scoreCorr = sc_correlations($scoreRows);

    // each column sorts in its own interesting direction: weakest scores first,
    // biggest audiences first. Unscored posts sink to the bottom either way.
    $key = match (true) {
        $scoreSort === 'pv'          => fn(array $r) => -$r['pv'],
        $scoreSort === 'time'        => fn(array $r) => -$r['avg_time'],
        $scoreSort === 'date'        => fn(array $r) => -$r['publish_ts'],
        $scoreSort === 'reach_desc'  => fn(array $r) => -($r['reach'] ?? -1),
        isset(SC_AXES[$scoreSort])   => fn(array $r) => $r['scores'][$scoreSort] ?? 99,
        default                      => fn(array $r) => $r['reach'] ?? 99,
    };
    usort($scoreRows, fn(array $a, array $b) => [$key($a), $b['publish_ts']] <=> [$key($b), $a['publish_ts']]);
}
$scoreNotes = $isAdmin ? sc_notes() : [];
$scored = array_values(array_filter($scoreRows, fn(array $r): bool => $r['reach'] !== null));
$avgReach = $scored ? round(array_sum(array_column($scored, 'reach')) / count($scored), 1) : null;

/* ---------------- small view helpers ---------------- */

/** dashboard link that keeps the current analytics filters (auth rides in the session) */
function s_url(array $over, string $daysRaw, string $path): string
{
    $q = array_merge([
        'days' => $daysRaw,
        'path' => $path !== '' ? $path : null,
    ], $over);
    return 'admin.php?' . http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
}

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

/** Sortable column header for the scores table (keeps the analytics range). */
function sc_th(string $key, string $label, string $current, string $daysRaw, string $cls = ''): string
{
    $on = $key === $current;
    $href = 'admin.php?' . http_build_query(['days' => $daysRaw, 'sort' => $key]) . '#scores';
    return '<th class="' . $cls . ($on ? ' sorted' : '') . '"><a href="' . e($href) . '">' . e($label) . '</a></th>';
}

/** One score cell: the number, shaded in proportion to it, with the rationale on hover. */
function sc_cell(?int $v, string $note): string
{
    if ($v === null) return '<td class="sc dim">·</td>';
    $title = $note !== '' ? ' title="' . e($note) . '"' : '';
    return '<td class="sc" style="--w:' . ($v * 10) . '%"' . $title . '>' . $v . '</td>';
}

/** Spearman rho as a signed, coloured cell ('—' when there was nothing to correlate). */
function sc_rho(?float $rho): string
{
    if ($rho === null) return '<td class="r n dim">—</td>';
    $cls = abs($rho) < 0.2 ? 'flat' : ($rho > 0 ? 'up' : 'down');
    return '<td class="r n"><span class="delta ' . $cls . '">' . ($rho > 0 ? '+' : '') . number_format($rho, 2) . '</span></td>';
}

/** ↑ 3 / ↓ 2 vs the previous 30 days — absolute, the list is small ('' when no history). */
function a_delta(int $cur, int $prev): string
{
    if ($cur === 0 && $prev === 0) return '';
    $d = $cur - $prev;
    if ($d === 0) return '<span class="delta flat">→ 0</span>';
    return '<span class="delta ' . ($d > 0 ? 'up' : 'down') . '">' . ($d > 0 ? '↑ ' : '↓ ') . abs($d) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin · bfrackowiak.pl</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/fonts.css">
<link rel="stylesheet" href="/assets/site.css">
<style>
<?= dash_base_css() ?>
.awrap { max-width: 1840px; margin: 2rem auto 4rem; padding-inline: 32px; }
.ahead { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem 1.4rem; margin-bottom: .4rem; }
h1 { font-size: 1.7rem; font-weight: 800; color: var(--ink); margin-right: auto; letter-spacing: -.01em; }
h2 { font-size: 1.05rem; font-weight: 700; color: var(--ink); margin: 2.4rem 0 .8rem; display: flex; align-items: baseline; gap: .7rem; }
h2 .cnt, h2 .dim { font-family: 'JetBrains Mono', monospace; font-size: .72rem; color: var(--muted); font-weight: 400; }
h3 { font-size: .92rem; font-weight: 600; color: var(--ink); margin: 1.4rem 0 .55rem; }
.toplinks a { font-size: .85rem; margin-left: 1.1rem; }
.live { font-family: 'JetBrains Mono', monospace; font-size: .74rem; color: var(--muted); }
.live b { color: var(--ink); }
td.when { font-family: 'JetBrains Mono', monospace; font-size: .78rem; white-space: nowrap; }
.badge { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: .66rem; letter-spacing: .06em; text-transform: uppercase; padding: .2rem .6rem; border-radius: 999px; border: 1px solid var(--line); }
.b-published, .b-confirmed { color: #0a7a45; border-color: #0a7a4540; background: #0a7a4512; }
.b-scheduled, .b-pending { color: #b45309; border-color: #b4530940; background: #b4530912; }
.b-draft { color: var(--muted); background: var(--surface); }
tr.r-published td:first-child { box-shadow: inset 3px 0 0 #0a7a4570; }
tr.r-scheduled td:first-child { box-shadow: inset 3px 0 0 #b4530980; }
tr.r-draft td:first-child { box-shadow: inset 3px 0 0 var(--line); }
.countdown { font-size: .72rem; color: #b45309; }
.post-t { font-weight: 600; }
.post-t a { text-decoration: none; }
.post-sub { display: block; color: var(--muted); font-size: .8rem; margin-top: .15rem; max-width: 56ch; }
.acts { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
.acts form { display: inline-flex; gap: .4rem; align-items: center; margin: 0; }
button.act { font: inherit; font-weight: 600; font-size: .85rem; padding: .55rem 1.2rem; border-radius: 10px; border: 1px solid var(--line); background: var(--ink); color: var(--bg, #fff); cursor: pointer; }
button.mini { font: inherit; font-size: .74rem; font-weight: 600; padding: .3rem .7rem; border-radius: 8px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); cursor: pointer; white-space: nowrap; }
button.mini:hover { border-color: var(--ink); }
button.mini.go { color: #0a7a45; border-color: #0a7a4540; }
button.mini.hold { color: var(--muted); }
button.mini.danger { color: #b91c1c; border-color: #b91c1c40; }
input.dt { font-family: 'JetBrains Mono', monospace; font-size: .72rem; padding: .28rem .45rem; border: 1px solid var(--line); border-radius: 8px; background: transparent; color: var(--ink); max-width: 12.5rem; }
.btn-line { display: flex; align-items: center; gap: 1.1rem; flex-wrap: wrap; margin: .9rem 0 1rem; }
pre.buildout { background: var(--surface); border: 1px solid var(--line); border-left: 3px solid var(--accent); border-radius: 12px; padding: 1rem 1.2rem; font-size: .78rem; overflow-x: auto; white-space: pre-wrap; margin: .6rem 0 1.2rem; }
.two-zone { display: grid; grid-template-columns: 2fr 1fr; gap: 1.8rem; align-items: start; }
@media (max-width: 1100px) { .two-zone { grid-template-columns: 1fr; } }
.login { max-width: 400px; margin: 12vh auto; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 1.8rem; }
.login input[type=password] { width: 100%; font: inherit; padding: .65rem .9rem; margin: .8rem 0; border: 1px solid var(--line); border-radius: 10px; background: transparent; color: var(--ink); }
.form-err { color: #b91c1c; font-size: .85rem; margin: .4rem 0; }
.note { font-size: .8rem; color: var(--muted); margin: .6rem 0 0; line-height: 1.55; }
.cmt-text { overflow-wrap: anywhere; }
code { font-family: 'JetBrains Mono', monospace; font-size: .8em; background: var(--surface); border: 1px solid var(--line); border-radius: 6px; padding: .05rem .35rem; }
/* tabbed panes; the hash is the state, so views are deep-linkable.
   Without JS the .js class never lands and everything stays stacked. */
.tabs { display: flex; gap: .15rem; overflow-x: auto; position: sticky; top: 0; z-index: 5;
  background: var(--bg, #fbfbfd); padding: .7rem 0 0; margin: 1.5rem 0 .2rem; border-bottom: 1px solid var(--line); }
.tabs a { padding: .5rem 1.15rem; font-weight: 600; font-size: .87rem; color: var(--muted); text-decoration: none;
  border: 1px solid transparent; border-bottom: none; border-radius: 10px 10px 0 0; white-space: nowrap; }
.tabs a:hover { color: var(--ink); }
.tabs a.active { color: var(--ink); background: var(--surface); border-color: var(--line); position: relative; top: 1px; }
.tabs .tsep { flex: 0 0 1px; background: var(--line); margin: .5rem .55rem; }
html:not(.js) .tabs { display: none; }
.js .pane { display: none; }
.js .pane.on { display: block; }
.an-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem 1.4rem; margin: 1.1rem 0 .2rem; }
.seg { display: inline-flex; background: var(--surface); border: 1px solid var(--line); border-radius: 999px; padding: .2rem; gap: .1rem; }
.seg a, .seg span { font-size: .8rem; font-weight: 600; padding: .3rem .85rem; border-radius: 999px; text-decoration: none; color: var(--muted); }
.seg span { background: var(--ink); color: var(--bg, #fff); }
.seg a:hover { color: var(--ink); }
.pathchip { font-family: 'JetBrains Mono', monospace; font-size: .78rem; background: var(--surface); border: 1px solid var(--accent); border-radius: 999px; padding: .35rem .95rem; display: inline-block; }
.pathchip a { margin-left: .6rem; text-decoration: none; }
.scard { position: relative; }
.delta { font-family: 'JetBrains Mono', monospace; font-size: .68rem; font-weight: 500; margin-left: .45rem; vertical-align: .35em; }
.delta.up { color: #0a7a45; } .delta.down { color: #b91c1c; } .delta.flat { color: var(--muted); }
.charts-zone { display: grid; grid-template-columns: 2.2fr 1fr; gap: 1.8rem; align-items: start; }
@media (max-width: 1100px) { .charts-zone { grid-template-columns: 1fr; } }
.chartbox { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 1rem 1.1rem .7rem; margin-bottom: 1.6rem; }
.chartbox .cap { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: .66rem; color: var(--muted); margin-bottom: .55rem; }
.chart { display: flex; align-items: flex-end; gap: 2px; height: 190px; }
.chart .bar { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; border-radius: 3px 3px 0 0; }
.chart .pv, .chart .lite { background: var(--accent); opacity: .28; border-radius: 3px 3px 0 0; }
.chart .uv, .chart .main { background: var(--accent); opacity: .95; }
.chart .bar:hover .pv, .chart .bar:hover .lite { opacity: .5; }
.axis { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: .64rem; color: var(--muted); padding-top: .45rem; }
.hours { display: flex; align-items: flex-end; gap: 3px; height: 190px; }
.hours div { flex: 1; background: var(--accent); opacity: .55; border-radius: 2px 2px 0 0; min-height: 2px; }
.hours div:hover { opacity: 1; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 0 1.8rem; align-items: start; }
.pane table { margin-bottom: 1.1rem; }
td.cell-bar { position: relative; background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 14%, transparent) var(--w), transparent var(--w)); }
.tbl h3 { margin-top: 1.2rem; }
.flag { margin-right: .5rem; }
.wide table { margin-bottom: 1.6rem; }
.recent td { white-space: nowrap; } .recent td.pathcell { white-space: normal; word-break: break-all; }
td.n { font-variant-numeric: tabular-nums; }
.foot { margin-top: 2.4rem; font-size: .74rem; color: var(--muted); }
a.plink { text-decoration: none; }
.warn { font-size: .85rem; color: #b45309; background: #b4530910; border: 1px solid #b4530940; border-radius: 10px; padding: .6rem .9rem; margin: .9rem 0; }
td.email { font-family: 'JetBrains Mono', monospace; font-size: .78rem; overflow-wrap: anywhere; }
/* editorial scores: seven 1–10 axes per post, shaded in place (docs/SCORING-RUBRIC.md) */
td.sc { font-family: 'JetBrains Mono', monospace; font-size: .78rem; text-align: right; font-variant-numeric: tabular-nums;
  background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 22%, transparent) var(--w), transparent var(--w)); }
th.ax { font-size: .68rem; font-weight: 600; text-align: right; white-space: nowrap; }
th.ax a, .scores-t th a { text-decoration: none; color: inherit; }
th.sorted, th.sorted a { color: var(--ink); text-decoration: underline; }
td.reach { font-family: 'JetBrains Mono', monospace; font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
.b-strong { color: #0a7a45; border-color: #0a7a4540; background: #0a7a4512; }
.b-solid  { color: var(--ink); }
.b-weak   { color: #b45309; border-color: #b4530940; background: #b4530912; }
.b-floor  { color: #b91c1c; border-color: #b91c1c40; background: #b91c1c12; }
.b-none   { color: var(--muted); background: var(--surface); }
tr.r-floor td:first-child { box-shadow: inset 3px 0 0 #b91c1c80; }
.scores-t td.pathcell { max-width: 34ch; }
</style>
</head>
<body>
<div class="awrap">

<?php if (!$isDash && !admin_enabled()): ?>
  <div class="login">
    <?= eyebrow('Admin') ?>
    <h1 style="margin:.6rem 0">Not configured</h1>
    <p class="note">Set <code>admin_password_hash</code> in <code>config.php</code>:</p>
    <p class="note"><code>php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"</code></p>
  </div>

<?php elseif (!$isDash): ?>
  <div class="login">
    <?= eyebrow('Admin', true) ?>
    <h1 style="margin:.6rem 0">bfrackowiak.pl</h1>
    <?php if (($_GET['err'] ?? '') === 'login'): ?>
      <p class="form-err"><?= $lockout > 0 ? 'Too many attempts. Locked for ' . (int) ceil($lockout / 60) . ' min.' : 'Wrong password.' ?></p>
    <?php endif; ?>
    <form method="POST" action="/admin.php">
      <input type="hidden" name="action" value="login">
      <label for="pw" class="note">Password</label>
      <input type="password" id="pw" name="password" autofocus autocomplete="current-password" <?= $lockout > 0 ? 'disabled' : '' ?>>
      <button class="act" type="submit" <?= $lockout > 0 ? 'disabled' : '' ?>>Sign in</button>
    </form>
  </div>

<?php else: ?>
  <?php $csrf = $isAdmin ? e(admin_csrf_token()) : ''; ?>
  <div class="ahead" style="margin-top:.4rem">
    <?= eyebrow($isAdmin ? 'Control room' : 'First-party analytics', true) ?>
  </div>
  <div class="ahead">
    <h1>bfrackowiak.pl · <?= $isAdmin ? 'admin' : 'analytics' ?></h1>
    <span class="toplinks">
      <a href="/blog/" target="_blank" rel="noopener">view site ↗</a>
    </span>
    <?php if ($isAdmin): ?>
    <form method="POST" action="/admin.php">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button class="mini hold" type="submit">log out</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($isAdmin): ?>
  <div class="cards">
    <div class="scard"><b><?= $statusCounts['published'] ?></b><span>Published</span></div>
    <div class="scard"><b><?= $statusCounts['scheduled'] ?></b><span>Scheduled</span></div>
    <div class="scard"><b><?= $statusCounts['draft'] ?></b><span>Drafts</span></div>
    <div class="scard"><b><?= $sum7 ? number_format($sum7['pv']) : '—' ?></b><span>Views · 7d</span></div>
    <div class="scard"><b><?= $sum7 ? number_format($sum7['uv']) : '—' ?></b><span>Visitors · 7d</span></div>
    <div class="scard"><b><?= $sum7 ? $sum7['live'] : '—' ?></b><span>On site now</span></div>
    <div class="scard"><b><?= count($comments) ?></b><span>Comments</span></div>
    <div class="scard"><b><?= $nl !== null ? number_format($nl['stats']['confirmed']) : '—' ?></b><span>Subscribers</span></div>
  </div>
  <?php endif; ?>

  <nav class="tabs">
    <?php if ($isAdmin): ?>
    <a href="#pipeline" class="active">Pipeline</a>
    <a href="#scores">Scores</a>
    <a href="#newsletter">Newsletter</a>
    <a href="#comments">Comments</a>
    <span class="tsep"></span>
    <?php endif; ?>
    <a href="#overview" <?= $isAdmin ? '' : 'class="active"' ?>>Overview</a>
    <?php if ($path === ''): ?><a href="#content">Content</a><?php endif; ?>
    <a href="#acquisition">Acquisition</a>
    <a href="#audience">Audience</a>
    <a href="#activity">Activity</a>
    <?php if ($isAdmin): ?>
    <span class="tsep"></span>
    <a href="#system">System</a>
    <?php endif; ?>
  </nav>

  <!-- analytics toolbar: JS shows it on the analytics tabs only -->
  <div class="an-bar" id="anbar">
    <nav class="seg">
      <?php foreach (['7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y', 'all' => 'all'] as $d => $label): ?>
        <?php if ($daysRaw === $d): ?><span><?= $label ?></span><?php else: ?><a href="<?= e(s_url(['days' => $d], $daysRaw, $path)) ?>"><?= $label ?></a><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <span class="live"><?= e($rangeLabel) ?><?= $an !== null ? ' · on site now: <b>' . $an['sum']['live'] . '</b>' : '' ?></span>
    <?php if ($path !== ''): ?>
      <span class="pathchip">page: <?= e($path) ?> <a href="<?= e(s_url(['path' => null], $daysRaw, $path)) ?>">× all pages</a></span>
    <?php endif; ?>
  </div>

<?php if ($isAdmin): ?>
  <div class="pane on" id="pipeline">
  <h2>Publishing pipeline <span class="cnt"><?= count($pipeline) ?> posts</span></h2>
  <div class="btn-line">
    <form method="POST" action="/admin.php">
      <input type="hidden" name="action" value="rebuild">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button class="act" type="submit">Rebuild site now</button>
    </form>
    <span class="note">last build: <?= admin_last_build_ts() ? date('Y-m-d H:i', admin_last_build_ts()) : 'never' ?>
      · status changes below rebuild automatically · the server cron publishes scheduled posts on time</span>
  </div>
  <?php if ($flash !== ''): ?><pre class="buildout"><?= e($flash) ?></pre><?php endif; ?>

  <table>
    <tr><th>Status</th><th>Goes live</th><th>Post</th><th class="r">Reach</th><th class="r">Read</th><th class="r">Comments</th><th>Change status</th></tr>
    <?php foreach ($pipeline as $p): ?>
    <tr class="r-<?= e($p['status']) ?>">
      <td><span class="badge b-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
      <td class="when">
        <?= $p['status'] === 'draft' ? '—' : date('Y-m-d H:i', $p['publish_ts']) ?>
        <?php if ($p['status'] === 'scheduled'): ?><br><span class="countdown"><?= e(admin_countdown($p['publish_ts'])) ?></span><?php endif; ?>
      </td>
      <td>
        <span class="post-t">
        <?php if ($p['status'] === 'published' && $p['built']): ?>
          <a href="<?= e(post_path($p['slug'])) ?>" target="_blank" rel="noopener"><?= e($p['title']) ?></a>
        <?php else: ?>
          <?= e($p['title']) ?><?= $p['status'] === 'published' && !$p['built'] ? ' <span class="dim">· not built yet</span>' : '' ?>
        <?php endif; ?>
        </span>
        <span class="post-sub"><?= e($p['subtitle']) ?></span>
      </td>
      <?php $r = sc_reach($p['scores']); ?>
      <td class="r"><a class="plink badge b-<?= e(sc_band($r)) ?>" href="#scores" title="editorial reach score, rubric <?= e(SC_RUBRIC) ?>"><?= $r !== null ? number_format($r, 1) : 'unscored' ?></a></td>
      <td class="r n"><?= $p['minutes'] ?> min</td>
      <td class="r n"><?= $p['comments'] ?: '·' ?></td>
      <td>
        <div class="acts">
          <?php if ($p['status'] !== 'published'): ?>
          <form method="POST" action="/admin.php">
            <input type="hidden" name="action" value="setstatus">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
            <input type="hidden" name="status_action" value="publish">
            <button class="mini go" type="submit">▶ publish now</button>
          </form>
          <?php endif; ?>
          <?php if ($p['status'] !== 'draft'): ?>
          <form method="POST" action="/admin.php">
            <input type="hidden" name="action" value="setstatus">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
            <input type="hidden" name="status_action" value="draft">
            <button class="mini hold" type="submit">⏸ to draft</button>
          </form>
          <?php endif; ?>
          <form method="POST" action="/admin.php">
            <input type="hidden" name="action" value="setstatus">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
            <input type="hidden" name="status_action" value="schedule">
            <input class="dt" type="datetime-local" name="when" required value="<?= date('Y-m-d\TH:i', max($p['publish_ts'], time())) ?>">
            <button class="mini" type="submit">⏱ schedule</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="note">Statuses live in the markdown itself (<code>**Status:** draft</code> / <code>**Publish:** YYYY-MM-DD HH:MM</code>) —
    the buttons rewrite the source file and rebuild, so a change here is immediately real.
    On the server, remember the next deploy re-syncs <code>content/posts/</code> from your machine: mirror any status change locally before deploying.</p>
  </div><!-- /pipeline -->

  <div class="pane wide scores-t" id="scores">
  <?php
    $unscored = count($scoreRows) - count($scored);
    $floors = array_values(array_filter($scored, fn(array $r): bool => $r['band'] === 'floor'));
    $rubrics = array_unique(array_filter(array_column(array_column($scoreRows, 'scored'), 'rubric')));
  ?>
  <h2>Editorial scores <span class="cnt">rubric <?= e(SC_RUBRIC) ?> · <?= count($scored) ?>/<?= count($scoreRows) ?> scored</span></h2>

  <div class="cards">
    <div class="scard"><b><?= $avgReach !== null ? number_format($avgReach, 1) : '—' ?></b><span>Avg reach score</span></div>
    <div class="scard"><b><?= count($floors) ?></b><span>Below the floor (&lt; 4.5)</span></div>
    <div class="scard"><b><?= $unscored ?></b><span>Unscored</span></div>
    <?php foreach (['tension', 'originality', 'personal'] as $ax): ?>
      <?php $vals = array_column(array_column($scored, 'scores'), $ax); ?>
      <div class="scard"><b><?= $vals ? number_format(array_sum($vals) / count($vals), 1) : '—' ?></b><span>Avg <?= e(SC_AXES[$ax]['label']) ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php if (count($rubrics) > 1): ?>
    <p class="warn">Mixed rubric versions in the corpus (<?= e(implode(', ', $rubrics)) ?>). Scores from different rubrics are not comparable. Re-score everything to one version.</p>
  <?php endif; ?>
  <?php if ($unscored > 0): ?>
    <p class="warn"><?= $unscored ?> post<?= $unscored === 1 ? '' : 's' ?> carry no scores. Add a <code>**Scores:**</code> line to the markdown source and rebuild.</p>
  <?php endif; ?>

  <table>
    <tr>
      <?= sc_th('reach', 'Reach ↑', $scoreSort, $daysRaw, 'r') ?>
      <?= sc_th('date', 'Post', $scoreSort, $daysRaw) ?>
      <?php foreach (SC_AXES as $k => $ax): ?>
        <?= sc_th($k, $ax['label'], $scoreSort, $daysRaw, 'ax') ?>
      <?php endforeach; ?>
      <?= sc_th('pv', 'Views', $scoreSort, $daysRaw, 'r') ?>
      <?= sc_th('time', 'Avg time', $scoreSort, $daysRaw, 'r') ?>
    </tr>
    <?php foreach ($scoreRows as $r): ?>
    <tr class="r-<?= e($r['band']) ?>">
      <td class="reach"><span class="badge b-<?= e($r['band']) ?>"><?= $r['reach'] !== null ? number_format($r['reach'], 1) : '—' ?></span></td>
      <td class="pathcell">
        <span class="post-t"><?php if ($r['status'] === 'published'): ?><a href="<?= e(post_path($r['slug'])) ?>" target="_blank" rel="noopener"><?= e($r['title']) ?></a><?php else: ?><?= e($r['title']) ?><?php endif; ?></span>
        <span class="post-sub"><?= e(date('Y-m-d', $r['publish_ts'])) ?> · <?= number_format($r['words']) ?> words<?= $r['status'] !== 'published' ? ' · ' . e($r['status']) : '' ?><?= $r['scored']['model'] !== '' ? ' · ' . e($r['scored']['model']) : '' ?></span>
      </td>
      <?php foreach (array_keys(SC_AXES) as $k): ?>
        <?= sc_cell($r['scores'][$k] ?? null, (string) ($scoreNotes[$r['slug']][$k] ?? '')) ?>
      <?php endforeach; ?>
      <td class="r n"><?= $r['pv'] ? number_format($r['pv']) : '<span class="dim">0</span>' ?></td>
      <td class="r n"><?= an_fmt_secs($r['avg_time']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$scoreRows): ?><tr><td colspan="<?= 4 + count(SC_AXES) ?>" class="dim">no posts</td></tr><?php endif; ?>
  </table>
  <p class="note">Views and average time are for <?= e($rangeLabel) ?> (use the range selector above). Hover a score for its rationale.
    Column headers sort; <a href="<?= e('admin.php?' . http_build_query(['days' => $daysRaw, 'sort' => 'reach_desc']) . '#scores') ?>">best first</a>.</p>

  <h2>Which axis actually gets read? <span class="dim">— Spearman rank correlation, <?= (int) $scoreCorr['n'] ?> posts with traffic</span></h2>
  <?php if ($scoreCorr['n'] < 3): ?>
    <p class="dim" style="font-size:.86rem">Not enough scored posts with recorded traffic yet. This table fills in as analytics accumulates; it needs at least 3.</p>
  <?php else: ?>
  <div class="two-zone">
    <div>
      <table>
        <tr><th>Axis</th><th class="r">Weight</th><th class="r">ρ vs views</th><th class="r">ρ vs engaged time</th></tr>
        <?php foreach (SC_AXES as $k => $ax): ?>
        <tr>
          <td><?= e($ax['label']) ?> <span class="dim" style="font-size:.75rem"><?= e($ax['cat']) ?></span></td>
          <td class="r n dim"><?= number_format($ax['weight'], 2) ?></td>
          <?= sc_rho($scoreCorr['axes'][$k]['pv']) ?>
          <?= sc_rho($scoreCorr['axes'][$k]['time']) ?>
        </tr>
        <?php endforeach; ?>
        <tr>
          <td><b>Reach score</b></td>
          <td class="r n dim">—</td>
          <?= sc_rho($scoreCorr['reach']['pv']) ?>
          <?= sc_rho($scoreCorr['reach']['time']) ?>
        </tr>
      </table>
    </div>
    <div>
      <p class="note">ρ runs from −1 to +1. Above about +0.4 means posts scoring high on that axis really do get read more; near 0 means the axis predicts nothing here.
        <b>Rank-based on purpose</b>: one post that hits Hacker News would otherwise dominate the whole correlation.</p>
      <p class="note">With <?= (int) $scoreCorr['n'] ?> posts this is directional, not proof. Once the sign and size are stable across a few months,
        re-fit the weights in <code>SC_AXES</code> (<code>lib/scores.php</code>), bump <code>SC_RUBRIC</code>, and re-score the corpus.
        The current weights are a prior taken from published research, not from this blog's own traffic.</p>
    </div>
  </div>
  <?php endif; ?>
  </div><!-- /scores -->

  <div class="pane" id="newsletter">
  <?php $ns = $nl['stats'] ?? null; ?>
  <h2>Newsletter — Field Notes <span class="cnt"><?= $ns ? ($ns['confirmed'] + $ns['pending']) . ' addresses' : '' ?></span></h2>
  <?php if ($flashN !== ''): ?><p class="note"><?= e($flashN) ?></p><?php endif; ?>
  <?php if ($nl === null): ?>
    <p class="dim" style="font-size:.86rem">Subscriber store unavailable — <code>content/newsletter.db</code> could not be opened.</p>
  <?php else: ?>
  <?php if (!nl_mail_enabled()): ?><p class="warn">SMTP is not configured — confirmation mails and essay sends are <b>off</b>. New signups will pile up as pending.</p><?php endif; ?>

  <div class="cards">
    <div class="scard"><b><?= number_format($ns['confirmed']) ?></b><span>Confirmed</span></div>
    <div class="scard"><b><?= number_format($ns['pending']) ?></b><span>Pending opt-in</span></div>
    <div class="scard"><b><?= $ns['new30'] ?><?= a_delta($ns['new30'], $ns['prev30']) ?></b><span>New confirms · 30d</span></div>
    <div class="scard"><b><?= $ns['confirm_rate'] !== null ? $ns['confirm_rate'] . '%' : '—' ?></b><span>Confirm rate</span></div>
    <div class="scard"><b><?= nl_fmt_span($ns['median_confirm_secs']) ?></b><span>Median time to confirm</span></div>
    <div class="scard"><b><?= $ns['essays_mailed'] ?></b><span>Essays mailed</span></div>
    <div class="scard"><b><?= $ns['last_send'] ? date('M j', (int) $ns['last_send']['sent_ts']) : '—' ?></b>
      <span><?= $ns['last_send'] ? 'Last send · ' . (int) $ns['last_send']['recipients'] . ' inboxes' : 'Last send' ?></span></div>
  </div>
  <?php if ($ns['new30'] || $ns['prev30']): ?><p class="dim" style="font-size:.72rem;margin-top:-.9rem">trend vs the previous 30 days · gross growth — unsubscribes leave no trace</p><?php endif; ?>

  <?php
    $wk = $nl['weekly'];
    $maxWk = max(1, ...array_map(fn($w) => $w['confirmed'] + $w['pending'], $wk));
    $anyWk = array_sum(array_map(fn($w) => $w['confirmed'] + $w['pending'], $wk)) > 0;
  ?>
  <div class="chartbox">
    <div class="cap"><span>weekly signups · 26 weeks — dark = confirmed, light = still pending</span><span>peak <?= $maxWk ?>/wk</span></div>
    <?php if ($anyWk): ?>
    <div class="chart">
      <?php foreach ($wk as $w): ?>
        <div class="bar" title="week of <?= e($w['week']) ?> — <?= (int) $w['confirmed'] ?> confirmed, <?= (int) $w['pending'] ?> pending">
          <div class="lite" style="height:<?= (int) round($w['pending'] / $maxWk * 100) ?>%"></div>
          <div class="main" style="height:<?= (int) round($w['confirmed'] / $maxWk * 100) ?>%"></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="axis"><span><?= e($wk[0]['week']) ?></span><span><?= e(end($wk)['week']) ?></span></div>
    <?php else: ?>
    <p class="dim" style="font-size:.85rem">No signups in the last 26 weeks<?= ($ns['confirmed'] + $ns['pending']) > 0 ? ' — the whole list is older than that' : '. The form is on every essay page.' ?></p>
    <?php endif; ?>
  </div>

  <div class="two-zone">
    <div>
      <h2>Subscribers <span class="cnt"><?= count($nl['subs']) ?></span></h2>
      <?php if (!$nl['subs']): ?>
        <p class="dim" style="font-size:.86rem">Nobody on the list yet.</p>
      <?php else: ?>
      <table>
        <tr><th>Email</th><th>Status</th><th>Signed up</th><th>Confirmed</th><th class="r">Took</th><th></th></tr>
        <?php foreach ($nl['subs'] as $s): ?>
        <tr>
          <td class="email"><?= e($s['email']) ?></td>
          <td><span class="badge b-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
          <td class="when"><?= date('Y-m-d H:i', (int) $s['created_ts']) ?></td>
          <td class="when"><?= $s['confirmed_ts'] ? date('Y-m-d H:i', (int) $s['confirmed_ts']) : '—' ?></td>
          <td class="r n"><?= $s['confirmed_ts']
              ? e(nl_fmt_span((int) $s['confirmed_ts'] - (int) $s['created_ts']))
              : '<span class="dim">waiting ' . e(nl_fmt_span(time() - (int) $s['created_ts'])) . '</span>' ?></td>
          <td class="r">
            <div class="acts">
              <?php if ($s['status'] === 'pending'): ?>
              <form method="POST" action="/admin.php">
                <input type="hidden" name="action" value="nlconfirm">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="token" value="<?= e($s['token']) ?>">
                <button class="mini go" type="submit" title="manual double opt-in — for when the confirmation mail never arrived">✓ confirm</button>
              </form>
              <?php endif; ?>
              <form method="POST" action="/admin.php" onsubmit="return confirm('Remove this subscriber? The row is deleted outright.')">
                <input type="hidden" name="action" value="nldelete">
                <input type="hidden" name="csrf" value="<?= $csrf ?>">
                <input type="hidden" name="token" value="<?= e($s['token']) ?>">
                <button class="mini danger" type="submit">remove</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <p class="note">Stored per subscriber: the address, two timestamps, one token — nothing else, by design.
        Unsubscribing deletes the row outright, so there is no churn history to browse.</p>
    </div>

    <div>
      <h2>Send ledger <span class="cnt"><?= count($nl['sends']) ?> essays</span></h2>
      <?php if (!$nl['sends']): ?>
        <p class="dim" style="font-size:.86rem">Ledger is empty — the first cron run seeds it with the already-published archive (nothing is mailed).</p>
      <?php else: ?>
      <?php $titles = array_column($pipeline, 'title', 'slug'); ?>
      <table>
        <tr><th>Sent</th><th>Essay</th><th class="r">Inboxes</th></tr>
        <?php foreach ($nl['sends'] as $sd): ?>
        <tr>
          <td class="when"><?= date('Y-m-d', (int) $sd['sent_ts']) ?></td>
          <td><a href="<?= e(post_path((string) $sd['slug'])) ?>" target="_blank" rel="noopener"><?= e($titles[$sd['slug']] ?? (string) $sd['slug']) ?></a></td>
          <td class="r n"><?= $sd['note'] === 'baseline' ? '<span class="dim">pre-launch</span>'
              : ($sd['note'] === 'sending' ? '<span class="dim">in flight…</span>' : (int) $sd['recipients']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <p class="note">“pre-launch” rows were seeded so the archive never got blasted at the list.
        “in flight” is a send in progress — or one that crashed mid-run; the next cron pass finishes or retries it.</p>
    </div>
  </div>
  <?php endif; ?>
  </div><!-- /newsletter -->

  <div class="pane" id="comments">
      <h2>Comments <span class="cnt"><?= count($comments) ?></span></h2>
      <?php if ($flashC !== ''): ?><p class="note"><?= e($flashC) ?></p><?php endif; ?>
      <?php if (!$comments): ?>
        <p class="dim" style="font-size:.86rem">No comments yet.</p>
      <?php else: ?>
      <table>
        <tr><th>Date</th><th>Post</th><th>Author</th><th>Comment</th><th></th></tr>
        <?php foreach ($comments as $c): ?>
        <tr>
          <td class="when"><?= e($c['date']) ?></td>
          <td><a href="<?= e(post_path($c['slug'])) ?>#comments" target="_blank" rel="noopener"><?= e($c['slug']) ?></a></td>
          <td class="n"><?= e($c['email']) ?></td>
          <td class="cmt-text"><?= nl2br(e(mb_substr($c['text'], 0, 400))) ?><?= mb_strlen($c['text']) > 400 ? '…' : '' ?></td>
          <td class="r">
            <form method="POST" action="/admin.php" onsubmit="return confirm('Delete this comment?')">
              <input type="hidden" name="action" value="delcomment">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="slug" value="<?= e($c['slug']) ?>">
              <input type="hidden" name="key" value="<?= e($c['key']) ?>">
              <button class="mini danger" type="submit">delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
  </div><!-- /comments -->
<?php endif; ?>

  <?php if ($an === null): ?>
  <div class="pane <?= $isAdmin ? '' : 'on' ?>" id="overview">
    <h2>Analytics</h2>
    <p class="dim" style="font-size:.86rem">Analytics store unavailable — <code>content/analytics.db</code> could not be opened.</p>
  </div>
  <div class="pane" id="content"></div>
  <div class="pane" id="acquisition"></div>
  <div class="pane" id="audience"></div>
  <div class="pane" id="activity"></div>
  <?php else: ?>
  <?php
    $sum = $an['sum'];
    $maxPv = max(1, ...array_map(fn($d) => (int) $d['pv'], $an['daily'] ?: [['pv' => 1]]));
    $maxHour = max(1, ...$an['hours']);
  ?>
  <div class="pane <?= $isAdmin ? '' : 'on' ?>" id="overview">
  <div class="cards">
    <div class="scard"><b><?= number_format($sum['pv']) ?><?= s_delta($an['prev'], 'pv', $sum['pv']) ?></b><span>Pageviews</span></div>
    <div class="scard"><b><?= number_format($sum['uv']) ?><?= s_delta($an['prev'], 'uv', $sum['uv']) ?></b><span>Visitors (daily-unlinkable)</span></div>
    <div class="scard"><b><?= an_fmt_secs($sum['avg_time']) ?><?= s_delta($an['prev'], 'avg_time', $sum['avg_time']) ?></b><span>Avg engaged time</span></div>
    <div class="scard"><b><?= number_format($sum['multi']) ?><?= s_delta($an['prev'], 'multi', $sum['multi']) ?></b><span>Multi-page readers</span></div>
    <div class="scard"><b><?= $sum['countries'] ?></b><span>Countries</span></div>
    <div class="scard"><b><?= $sum['days'] ?></b><span>Active days</span></div>
  </div>
  <?php if ($an['prev'] !== null): ?><p class="dim" style="font-size:.72rem;margin-top:-.9rem">trend vs the previous <?= $days ?> days</p><?php endif; ?>

  <h2>Traffic</h2>
  <div class="charts-zone">
    <div class="chartbox">
      <div class="cap"><span>daily — dark = visitors, light = pageviews</span><span>peak <?= number_format($maxPv) ?>/day</span></div>
      <div class="chart">
        <?php foreach ($an['daily'] as $d): ?>
          <div class="bar" title="<?= e($d['day']) ?> — <?= (int) $d['pv'] ?> views, <?= (int) $d['uv'] ?> visitors">
            <div class="pv" style="height:<?= (int) round(($d['pv'] - $d['uv']) / $maxPv * 100) ?>%"></div>
            <div class="uv" style="height:<?= max(2, (int) round($d['uv'] / $maxPv * 100)) ?>%"></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$an['daily']): ?><p class="dim" style="font-size:.85rem">No data yet. The beacon starts counting as soon as real browsers visit.</p><?php endif; ?>
      </div>
      <?php if ($an['daily']): ?><div class="axis"><span><?= e($an['daily'][0]['day']) ?></span><span><?= e(end($an['daily'])['day']) ?></span></div><?php endif; ?>
    </div>
    <div class="chartbox">
      <div class="cap"><span>hour of day · <?= e((string) cfg('timezone', 'Europe/Warsaw')) ?></span><span>peak <?= $maxHour ?></span></div>
      <div class="hours">
        <?php foreach ($an['hours'] as $h => $pv): ?>
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
    <?php $topPage = max(1, ...array_map(fn($p) => (int) $p['pv'], $an['pages'] ?: [['pv' => 1]])); ?>
    <?php $totPage = max(1, array_sum(array_map(fn($p) => (int) $p['pv'], $an['pages']))); ?>
    <?php foreach ($an['pages'] as $p): ?>
      <tr>
        <td class="pathcell cell-bar" style="--w:<?= (int) round($p['pv'] / $topPage * 100) ?>%"><a class="plink" href="<?= e(s_url(['path' => $p['path']], $daysRaw, $path)) ?>"><?= e($p['path']) ?></a></td>
        <td class="r n"><?= number_format((int) $p['pv']) ?></td>
        <td class="r n dim"><?= round($p['pv'] / $totPage * 100) ?>%</td>
        <td class="r n"><?= (int) $p['uv'] ?></td>
        <td class="r n"><?= an_fmt_secs((int) round((float) $p['avg_time'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$an['pages']): ?><tr><td colspan="5" class="dim">no data in range</td></tr><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>

  <div class="pane" id="acquisition">
  <h2>Acquisition <span class="dim">— where readers come from</span></h2>
  <div class="grid">
    <?php
    s_table('Source groups', $an['sources']);
    s_table('Referrers', $an['dims']['ref']);
    s_table('Campaigns · utm_source', $an['dims']['utm_source']);
    if ($an['dims']['utm_campaign']) s_table('Campaigns · utm_campaign', $an['dims']['utm_campaign']);
    ?>
  </div>
  </div><!-- /acquisition -->

  <div class="pane" id="audience">
  <h2>Audience <span class="dim">— who and from where</span></h2>
  <div class="grid">
    <?php
    s_table('Countries', $an['dims']['country'], fn(string $cc) => '<span class="flag">' . an_flag($cc) . '</span>' . e(an_country_name($cc)));
    s_table('Timezones (visitor-reported)', $an['dims']['tz']);
    s_table('Languages', $an['dims']['lang'], fn(string $l) => e(strtoupper($l)));
    s_table('Browsers', $an['dims']['browser']);
    s_table('Operating systems', $an['dims']['os']);
    s_table('Devices', $an['dims']['device']);
    s_table('Screens', $an['dims']['screen']);
    ?>
  </div>
  </div><!-- /audience -->

  <div class="pane wide recent" id="activity">
    <h2>Recent visits <span class="dim">— last <?= count($an['recent']) ?></span></h2>
    <table><tr><th>When</th><th></th><th>Path</th><th>From</th><th>Client</th><th class="r">Read</th></tr>
    <?php foreach ($an['recent'] as $r): ?>
      <tr>
        <td class="n"><?= date('m-d H:i', (int) $r['ts']) ?></td>
        <td title="<?= e(an_country_name((string) $r['country'])) ?>"><?= an_flag((string) $r['country']) ?: '·' ?></td>
        <td class="pathcell"><?= e((string) $r['path']) ?></td>
        <td><?= $r['ref'] !== '' ? e((string) $r['ref']) : '<span class="dim">' . e((string) $r['ref_group']) . '</span>' ?></td>
        <td class="dim"><?= e(trim($r['browser'] . ' · ' . $r['os'] . ' · ' . $r['device'], ' ·')) ?></td>
        <td class="r n"><?= an_fmt_secs((int) $r['duration']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$an['recent']): ?><tr><td colspan="6" class="dim">nothing yet</td></tr><?php endif; ?>
    </table>
  </div><!-- /activity -->
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <div class="pane" id="system">
      <h2>System</h2>
      <table>
        <tr><td>Analytics database</td><td class="n r"><?= is_file(an_db_path()) ? round(filesize(an_db_path()) / 1024) . ' KB' : 'not created yet' ?></td></tr>
        <tr><td>Newsletter database</td><td class="n r"><?= is_file(nl_db_path()) ? round(filesize(nl_db_path()) / 1024) . ' KB' : 'not created yet' ?></td></tr>
        <tr><td>Outbound mail (SMTP)</td><td class="n r"><?= nl_mail_enabled() ? 'configured' : 'disabled — empty smtp password' ?></td></tr>
        <tr><td>GeoIP database</td><td class="n r"><?= geoip_db_age_days() === null ? 'missing — run php tools/geoip_update.php' : round(filesize(geoip_db_path()) / 1048576, 1) . ' MB · ' . geoip_db_age_days() . 'd old' ?></td></tr>
        <tr><td>Timezone</td><td class="n r"><?= e((string) cfg('timezone', 'Europe/Warsaw')) ?></td></tr>
        <tr><td>PHP</td><td class="n r"><?= PHP_VERSION ?></td></tr>
        <tr><td>Last build</td><td class="n r"><?= admin_last_build_ts() ? date('Y-m-d H:i', admin_last_build_ts()) : 'never' ?></td></tr>
      </table>
  </div><!-- /system -->
  <?php endif; ?>

  <p class="foot" id="anfoot">No cookies for visitors, no external services. Visitor hashes rotate daily. IP geolocation by <a href="https://db-ip.com">DB-IP</a> (CC BY 4.0)<?= geoip_db_age_days() === null ? ' — <strong>geoip.dat missing: run php tools/geoip_update.php</strong>' : ', database ' . geoip_db_age_days() . ' days old' ?>.<br>
  <strong>This browser is excluded from the stats</strong> (owner cookie, refreshed on every visit here) — re-enable via <a href="/a.php?me=on">/a.php?me=on</a>, exclude another device by opening <a href="/a.php?me=off">/a.php?me=off</a> on it or just logging into admin.</p>
<?php endif; ?>

</div>
<script>
// tabbed panes; the hash is the state, so views are deep-linkable and the PRG
// redirects (#pipeline / #comments / #newsletter) land on their tab.
// Without JS the .js class never lands and everything stays stacked.
document.documentElement.classList.add('js');
(function () {
  var tabs = [].slice.call(document.querySelectorAll('.tabs a'));
  if (!tabs.length) return;
  var ids = tabs.map(function (a) { return a.hash.slice(1); });
  // the range selector also drives the Scores tab (scores sit next to traffic there);
  // the privacy footnote belongs to the analytics tabs only
  var barIds = { overview: 1, content: 1, acquisition: 1, audience: 1, activity: 1, scores: 1 };
  var anIds = { overview: 1, content: 1, acquisition: 1, audience: 1, activity: 1 };
  var bar = document.getElementById('anbar'), foot = document.getElementById('anfoot');
  function show(id) {
    if (ids.indexOf(id) < 0) id = ids[0];
    tabs.forEach(function (a) { a.classList.toggle('active', a.hash.slice(1) === id); });
    ids.forEach(function (p) {
      var el = document.getElementById(p);
      if (el) el.classList.toggle('on', p === id);
    });
    // the range selector and the privacy footnote belong to the analytics tabs
    if (bar) bar.style.display = barIds[id] ? '' : 'none';
    if (foot) foot.style.display = anIds[id] ? '' : 'none';
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
