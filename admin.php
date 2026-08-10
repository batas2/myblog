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
$exclCounts = null;
$logRecon = [];
$statusCounts = ['published' => 0, 'scheduled' => 0, 'draft' => 0];
if ($isAdmin) {
    $comments = admin_all_comments();
    $pipeline = admin_pipeline(array_count_values(array_column($comments, 'slug')));
    foreach ($pipeline as $p) $statusCounts[$p['status']]++;
    if (is_file(an_db_path())) {
        try {
            $sdb = an_db();
            $sum7 = an_summary($sdb, gmdate('Y-m-d', time() - 7 * 86400));
            $exclCounts = an_excl_counts($sdb, gmdate('Y-m-d', time() - 30 * 86400));
            // yesterday is a whole day, today says whether it still works right now
            foreach ([date('Y-m-d', time() - 86400), date('Y-m-d')] as $d) {
                $r = an_log_recon($sdb, $d);
                if ($r !== null) $logRecon[] = $r;
            }
        } catch (Throwable) { $sum7 = null; }
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
            // the standing pages, listed whether or not they out-rank the essays
            'dest'    => $path === '' ? an_paths($adb, $since, array_keys(AN_DESTINATIONS)) : [],
            'destPrev' => $path === '' && $days > 0
                ? an_paths($adb, gmdate('Y-m-d', time() - 2 * $days * 86400), array_keys(AN_DESTINATIONS), $since)
                : [],
            'recent'  => an_recent($adb, 30, $path),
            'sources' => an_source_groups($adb, $since, $path),
            'dims'    => $dims,
            // exits: where readers go when they leave, scoped by ?path= like everything else
            'exitSum'   => an_click_summary($adb, $since, $path),
            'exitPrev'  => $days > 0 ? an_click_summary($adb, gmdate('Y-m-d', time() - 2 * $days * 86400), $path, $since) : null,
            'exitHosts' => an_click_hosts($adb, $since, 15, $path),
            'exitLinks' => an_click_links($adb, $since, 25, $path),
            'exitRecent' => an_click_recent($adb, 20, $path),
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
/* sort=<column>, dir=asc|desc. Every column has a direction that is interesting
   by default (weakest scores first, biggest audiences first), and clicking the
   header you are already on flips it. */
$scoreSort = (string) ($_GET['sort'] ?? 'reach');
$scoreDirRaw = (string) ($_GET['dir'] ?? '');
$scoreFilter = (string) ($_GET['scope'] ?? 'all');
if ($isAdmin) {
    $pages = [];
    if (is_file(an_db_path())) {
        // every post, not just the top 25 — an unread post is exactly the row we need
        try { $pages = an_top_pages(an_db(), $since, 500); } catch (Throwable) { $pages = []; }
    }
    $scoreRows = sc_rank_gaps(sc_merge_analytics($pipeline, $pages));
    $scoreCorr = sc_correlations($scoreRows);
}
$scoreNotes = $isAdmin ? sc_notes() : [];
$scoreAll = $scoreRows;   // pre-filter, so the filter chips can show honest counts

/* Which rows are worth a decision. 'floor' and 'unscored' are the rubric's own
   verdicts; 'underread' is where the score and the audience disagree most —
   at least five places lower in views than in score is a distribution problem,
   not a writing one. */
$scoreScopes = [
    'all'       => ['label' => 'All posts',    fn(array $r): bool => true],
    'floor'     => ['label' => 'Below floor',  fn(array $r): bool => $r['band'] === 'floor'],
    'underread' => ['label' => 'Under-read',   fn(array $r): bool => ($r['gap'] ?? 0) <= -5],
    'unscored'  => ['label' => 'Unscored',     fn(array $r): bool => $r['reach'] === null],
];
if (!isset($scoreScopes[$scoreFilter])) $scoreFilter = 'all';
$scoreCounts = [];
foreach ($scoreScopes as $k => $scope) $scoreCounts[$k] = count(array_filter($scoreAll, $scope[0]));

if ($isAdmin) {
    $scoreRows = array_values(array_filter($scoreAll, $scoreScopes[$scoreFilter][0]));

    // [value, natural direction] per column; unscored posts sort last either way
    [$key, $natural] = match (true) {
        $scoreSort === 'pv'        => [fn(array $r) => $r['pv'], 'desc'],
        $scoreSort === 'time'      => [fn(array $r) => $r['avg_time'], 'desc'],
        $scoreSort === 'date'      => [fn(array $r) => $r['publish_ts'], 'desc'],
        $scoreSort === 'gap'       => [fn(array $r) => $r['gap'] ?? 0, 'asc'],
        $scoreSort === 'words'     => [fn(array $r) => $r['words'], 'desc'],
        isset(SC_AXES[$scoreSort]) => [fn(array $r) => $r['scores'][$scoreSort] ?? null, 'asc'],
        default                    => [fn(array $r) => $r['reach'], 'asc'],
    };
    // legacy deep links (sort=reach_desc) still mean what they used to
    if ($scoreSort === 'reach_desc') { $scoreSort = 'reach'; $scoreDirRaw = 'desc'; }
    $scoreDir = in_array($scoreDirRaw, ['asc', 'desc'], true) ? $scoreDirRaw : $natural;
    $sign = $scoreDir === 'desc' ? -1 : 1;
    usort($scoreRows, function (array $a, array $b) use ($key, $sign): int {
        $ka = $key($a);
        $kb = $key($b);
        // a missing value is not a small value: those rows go to the bottom
        if ($ka === null || $kb === null) return ($ka === null ? 1 : 0) <=> ($kb === null ? 1 : 0);
        return $sign * ($ka <=> $kb) ?: $b['publish_ts'] <=> $a['publish_ts'];
    });
} else {
    $scoreDir = 'asc';
}
$scored = array_values(array_filter($scoreAll, fn(array $r): bool => $r['reach'] !== null));
$avgReach = $scored ? round(array_sum(array_column($scored, 'reach')) / count($scored), 1) : null;

/* ---------------- syndication (admin only) ---------------- */
/* One place to answer "where is this essay published, and did posting it there
   bring anybody back". Columns are SHARE_TARGETS, so adding a destination is
   one row in lib/blog.php rather than another column here. A destination that
   also has a canonical URL (Medium, DEV) shows the link; one that is only ever
   hand-carried (LinkedIn, the newsletter) shows the clicks it earned. */

$syndClicks = [];
if ($isAdmin && is_file(an_db_path())) {
    try { $syndClicks = an_campaign_sources(an_db(), $since); } catch (Throwable) { $syndClicks = []; }
}

/* SHARE_TARGETS key => SYNDICATION key, for the destinations that have both.
   Matched on the lowercased name rather than assumed, so a future platform
   whose two spellings disagree is simply absent here instead of silently
   showing every post as unpublished. */
$syndPlatform = [];
foreach (array_keys(SYNDICATION) as $platform) {
    $key = strtolower($platform);
    if (isset(SHARE_TARGETS[$key])) $syndPlatform[$key] = $platform;
}

$syndPosts = array_values(array_filter($pipeline, fn(array $p): bool => $p['status'] !== 'draft'));
$syndMissing = [];
foreach ($syndPlatform as $key => $platform) {
    $syndMissing[$key] = count(array_filter(
        $syndPosts,
        fn(array $p): bool => $p['status'] === 'published' && !isset($p['syndication'][$platform])
    ));
}
$syndNowhere = count(array_filter(
    $syndPosts,
    fn(array $p): bool => $p['status'] === 'published' && !$p['syndication']
));

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

/** A scores-tab URL that keeps the range, the filter and the sort you are on. */
function sc_url(array $over, string $daysRaw, string $scope): string
{
    $q = array_merge(['days' => $daysRaw, 'scope' => $scope === 'all' ? null : $scope], $over);
    return 'admin.php?' . http_build_query(array_filter($q, fn($v) => $v !== null && $v !== '')) . '#scores';
}

/**
 * Sortable column header. Clicking the column you are already on flips the
 * direction, and the arrow always points the way the rows actually run, so the
 * table never sorts in a direction the header denies.
 */
function sc_th(string $key, string $label, string $current, string $dir, string $daysRaw, string $scope, string $cls = '', string $hint = ''): string
{
    $on = $key === $current;
    $next = $on && $dir === 'asc' ? 'desc' : ($on ? 'asc' : '');
    $arrow = $on ? ' <span class="sarrow">' . ($dir === 'asc' ? '↑' : '↓') . '</span>' : '';
    $href = sc_url(['sort' => $key, 'dir' => $next], $daysRaw, $scope);
    $title = $hint !== '' ? ' title="' . e($hint) . '"' : '';
    return '<th class="' . $cls . ($on ? ' sorted' : '') . '"' . $title . '>'
        . '<a href="' . e($href) . '">' . e($label) . $arrow . '</a></th>';
}

/**
 * One score cell. The number, shaded in proportion to it, and a button that
 * opens the reasoning behind it — a title attribute alone is unreachable on a
 * phone, which is where half of this dashboard gets read.
 */
function sc_cell(string $slug, string $axis, ?int $v, string $note): string
{
    $label = SC_AXES[$axis]['label'];
    if ($v === null) return '<td class="sc dim" data-ax="' . e($label) . '">·</td>';
    return '<td class="sc" style="--w:' . ($v * 10) . '%" data-ax="' . e($label) . '">'
        . '<button type="button" class="scbtn" data-slug="' . e($slug) . '" data-axis="' . e($axis) . '"'
        . ($note !== '' ? ' title="' . e($note) . '"' : '')
        . ' aria-label="' . e($label . ' ' . $v . ' — why?') . '">' . $v . '</button></td>';
}

/** Rank gap as a signed cell: negative = read less than it scored. */
function sc_gap_cell(?int $gap): string
{
    if ($gap === null) return '<td class="r n dim" data-ax="Gap">—</td>';
    if ($gap === 0) return '<td class="r n" data-ax="Gap"><span class="delta flat">→ 0</span></td>';
    $cls = $gap > 0 ? 'up' : 'down';
    return '<td class="r n" data-ax="Gap"><span class="delta ' . $cls . '">' . ($gap > 0 ? '+' : '−')
        . abs($gap) . '</span></td>';
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
.awrap { max-width: 1840px; margin: 2rem auto 4rem;
  padding-left: max(32px, env(safe-area-inset-left)); padding-right: max(32px, env(safe-area-inset-right)); }
@media (max-width: 780px) {
  /* 64px of gutter is a quarter of a phone screen */
  .awrap { margin-top: 1rem; padding-left: max(16px, env(safe-area-inset-left)); padding-right: max(16px, env(safe-area-inset-right)); }
  h1 { font-size: 1.35rem; }
  .tabs { margin-top: 1rem; }
}
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
/* one syndication cell: the link or the copy button, with its click count kept
   on the same line so a column reads as "where, and did it work" */
td.synd { white-space: nowrap; }
td.synd .dim { margin-left: .45rem; font-variant-numeric: tabular-nums; font-size: .8rem; }
/* the tagged-link drawer stays shut until the post is actually being shared.
   The flex row is a child, not the <details> itself: giving <details> a display
   other than block is the one way to break its own open/closed rendering. */
.share { margin-top: .45rem; font-size: .74rem; }
.share summary { color: var(--muted); cursor: pointer; width: max-content; }
.share summary:hover { color: var(--ink); }
.share-row { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; margin-top: .4rem; }
.share-row .dim { font-size: .7rem; }
button.mini.cp.done { color: #0a7a45; border-color: #0a7a4540; }
/* rows the totals leave out: visible, legible, obviously not part of the count */
tr.excluded td { opacity: .5; }
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
/* iOS zooms the page for any field under 16px and never zooms back; the
   schedule input is the one place in here you type into on a phone */
@media (pointer: coarse) { input.dt, .login input[type=password] { font-size: 16px; } }
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
/* the bar grows leftwards out of the number it belongs to, so a column of them
   reads as seven small meters instead of one wall of colour */
td.sc { font-family: 'JetBrains Mono', monospace; font-size: .78rem; text-align: right; font-variant-numeric: tabular-nums; padding: 0; width: 4.6rem;
  background: linear-gradient(to left, color-mix(in srgb, var(--accent) 20%, transparent) var(--w), transparent var(--w)); }
td.sc .scbtn { font: inherit; color: var(--ink); background: none; border: 0; width: 100%; padding: .5rem .8rem; text-align: right; cursor: pointer; }
td.sc .scbtn:hover { color: var(--accent); text-decoration: underline; }
td.sc.dim { padding: .5rem .8rem; }
th.ax { font-size: .68rem; font-weight: 600; text-align: right; white-space: nowrap; }
th.ax a, .scores-t th a { text-decoration: none; color: inherit; }
th.sorted, th.sorted a { color: var(--ink); }
th.sorted .sarrow { color: var(--accent); }
td.reach { font-family: 'JetBrains Mono', monospace; font-weight: 700; text-align: right; font-variant-numeric: tabular-nums; }
td.reach .scbtn { font: inherit; font-size: .74rem; cursor: pointer; }
td.reach .scbtn:hover { border-color: var(--accent); }
.b-strong { color: #0a7a45; border-color: #0a7a4540; background: #0a7a4512; }
.b-solid  { color: var(--ink); }
.b-weak   { color: #b45309; border-color: #b4530940; background: #b4530912; }
.b-floor  { color: #b91c1c; border-color: #b91c1c40; background: #b91c1c12; }
.b-none   { color: var(--muted); background: var(--surface); }
tr.r-floor td:first-child { box-shadow: inset 3px 0 0 #b91c1c80; }
.sc-table td.pathcell { max-width: 34ch; }
.lead { font-size: .9rem; color: var(--body); max-width: 78ch; margin: .2rem 0 1rem; line-height: 1.6; }
.lead b { color: var(--ink); }
/* the rubric, folded away until asked for */
.legend { border: 1px solid var(--line); border-radius: 12px; background: var(--surface); margin: 0 0 1.2rem; }
.legend > summary { cursor: pointer; padding: .7rem 1rem; font-size: .85rem; font-weight: 600; color: var(--ink); }
.legend > summary::marker { color: var(--muted); }
.legend-body { display: grid; grid-template-columns: 1fr 1fr; gap: 1.6rem; padding: .2rem 1rem 1rem; }
.legend-body h3 { margin-top: .2rem; }
table.mini { font-size: .8rem; border: 0; background: none; }
table.mini td { padding: .35rem .6rem .35rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
table.mini tr:last-child td { border-bottom: 0; }
.sc-controls { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; margin: 0 0 1rem; }
.ctl-label { font-family: 'JetBrains Mono', monospace; font-size: .64rem; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); }
/* the reasoning popup */
dialog.why { border: 1px solid var(--line); border-radius: 16px; background: var(--surface); color: var(--body);
  max-width: 40rem; width: calc(100% - 2rem); margin: auto; max-height: 86dvh; overflow-y: auto;
  padding: 1.4rem 1.5rem 1.6rem; box-shadow: 0 24px 60px rgba(0,0,0,.22); overscroll-behavior: contain; }
dialog.why::backdrop { background: rgba(10,10,15,.45); }
.why-x { position: absolute; top: .5rem; right: .7rem; font-size: 1.3rem; line-height: 1; background: none; border: 0; color: var(--muted); cursor: pointer; padding: .3rem .5rem; }
.why-x:hover { color: var(--ink); }
.why h3 { margin: 0 0 .1rem; font-size: 1.05rem; }
.why .why-post { font-size: .78rem; color: var(--muted); margin-bottom: .9rem; }
.why .why-big { display: flex; align-items: baseline; gap: .6rem; margin: .2rem 0 .9rem; }
.why .why-big b { font-family: 'JetBrains Mono', monospace; font-size: 2.1rem; font-weight: 700; color: var(--ink); line-height: 1; }
.why p { font-size: .87rem; line-height: 1.6; margin: .55rem 0; }
.why .why-lbl { font-family: 'JetBrains Mono', monospace; font-size: .62rem; letter-spacing: .09em; text-transform: uppercase; color: var(--muted); margin: 1.1rem 0 .35rem; }
.why .quote { border-left: 3px solid var(--accent); background: var(--wash); border-radius: 0 8px 8px 0; padding: .6rem .85rem; font-size: .87rem; color: var(--ink); }
.why .ladder { list-style: none; margin: .2rem 0 0; }
.why .ladder li { display: grid; grid-template-columns: 2.1rem 1fr; gap: .6rem; padding: .32rem 0; font-size: .83rem; color: var(--muted); border-bottom: 1px solid var(--line); }
.why .ladder li:last-child { border-bottom: 0; }
.why .ladder li b { font-family: 'JetBrains Mono', monospace; color: var(--faint); }
.why .ladder li.here { color: var(--ink); }
.why .ladder li.here b { color: var(--accent); }
.why .ladder li.here::after { content: "this post"; grid-column: 2; font-family: 'JetBrains Mono', monospace; font-size: .6rem; letter-spacing: .09em; text-transform: uppercase; color: var(--accent); }
.why table { font-size: .82rem; }
.why td.bar { width: 7rem; padding-right: 0; }
.why td.bar span { display: block; height: 8px; border-radius: 999px; background: color-mix(in srgb, var(--accent) 55%, transparent); width: var(--w); min-width: 3px; }
.why .sum td { border-top: 1px solid var(--line); font-weight: 700; color: var(--ink); }
.why td.mult { white-space: nowrap; }
@media (max-width: 520px) { .why td.bar, .why .sum td:nth-child(3) { display: none; } .why td, .why th { padding: .45rem .4rem; } }
@media (max-width: 900px) { .legend-body { grid-template-columns: 1fr; gap: .8rem; } }
/* Phone: twelve columns cannot be a table. Each post becomes a card — reach and
   title on top, the seven axes as a wrapped strip of labelled chips, the
   measured numbers at the foot. Every chip is still the button that explains
   itself, which is the whole point of the view. */
@media (max-width: 780px) {
  .scores-t table.sc-table, .sc-table tbody, .sc-table tr, .sc-table td { display: block; width: auto; }
  .sc-table thead { display: none; }
  .sc-table { border: 0; background: none; }
  .sc-table tbody tr { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; margin-bottom: .7rem; padding: .8rem .9rem; position: relative; }
  .sc-table tbody tr.r-floor { border-color: #b91c1c55; }
  .sc-table td { border-bottom: 0; padding: 0; }
  .sc-table td.reach { position: absolute; top: .8rem; right: .9rem; }
  .scores-t td.pathcell { max-width: none; padding-right: 4rem; margin-bottom: .6rem; }
  .sc-table tbody td.sc, .sc-table tbody td[data-ax] { display: inline-flex; align-items: baseline; gap: .35rem;
    width: auto; margin: 0 .35rem .35rem 0; padding: .2rem .5rem; border: 1px solid var(--line); border-radius: 999px; background: var(--bg, #fbfbfd); }
  .sc-table tbody td[data-ax]::before { content: attr(data-ax); font-family: 'JetBrains Mono', monospace; font-size: .58rem;
    letter-spacing: .07em; text-transform: uppercase; color: var(--muted); }
  .sc-table tbody td.sc .scbtn { width: auto; padding: 0; }
  .sc-table tbody td.reach::before { content: none; }
  /* judged and measured must not look alike: the seven scores are solid chips,
     what analytics recorded is dashed */
  .sc-table tbody td[data-ax="Views"], .sc-table tbody td[data-ax="Avg time"], .sc-table tbody td[data-ax="Gap"] {
    border-style: dashed; background: none; }
  .sc-controls { gap: .4rem .6rem; }
  .sc-controls .seg { flex-wrap: wrap; }
}
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
    <a href="#syndication">Published on</a>
    <a href="#newsletter">Newsletter</a>
    <a href="#comments">Comments</a>
    <span class="tsep"></span>
    <?php endif; ?>
    <a href="#overview" <?= $isAdmin ? '' : 'class="active"' ?>>Overview</a>
    <?php if ($path === ''): ?><a href="#content">Content</a><?php endif; ?>
    <a href="#acquisition">Acquisition</a>
    <a href="#exits">Exits</a>
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
  <?php $dupes = post_duplicate_sources(); ?>
  <?php if ($dupes): ?>
    <p class="warn"><b>Duplicate source files.</b> Rescheduling renames a post, and the deploy is additive, so an old filename can survive on the server. Each of these slugs is listed twice below; the newest file is the one in use, the others are stale and safe to delete.<br>
    <?php foreach ($dupes as $slug => $files): ?>
      <code><?= e($slug) ?></code>: <?= e(implode(' + ', $files)) ?><br>
    <?php endforeach; ?>
    </p>
  <?php endif; ?>

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
          <?php /* no page exists on disk for these, so send them to the on-demand preview */ ?>
          <a href="/preview.php?slug=<?= e($p['slug']) ?>" target="_blank" rel="noopener"
             title="read it in the real layout without publishing"><?= e($p['title']) ?></a>
          <?= $p['status'] === 'published' && !$p['built'] ? ' <span class="dim">· not built yet</span>' : '' ?>
        <?php endif; ?>
        </span>
        <span class="post-sub"><?= e($p['subtitle']) ?></span>
        <?php /* every cross-post host hides the referrer, so an untagged link comes back as 'direct' */ ?>
        <details class="share">
          <summary>tagged links</summary>
          <div class="share-row">
            <?php foreach (share_urls($p['slug']) as $sh): ?>
              <?php /* the title is the no-clipboard fallback: the URL stays readable and selectable */ ?>
              <button type="button" class="mini cp" data-url="<?= e($sh['url']) ?>" title="<?= e($sh['url']) ?>"><?= e($sh['label']) ?></button>
            <?php endforeach; ?>
            <span class="dim">copy · post these instead of the bare URL and Acquisition can tell them apart</span>
          </div>
        </details>
        <?php /* an unpublished essay has no public URL, so the only way to let somebody read it
                 is the token link. Refused to crawlers and unfurlers, so pasting it in a chat
                 shows a bare URL and puts nothing in anyone's cache. */ ?>
        <?php if ($p['status'] !== 'published'): $shareUrl = preview_share_url($p['slug']); ?>
        <details class="share">
          <summary>share this draft</summary>
          <div class="share-row">
            <?php if ($shareUrl !== null): ?>
              <button type="button" class="mini cp" data-url="<?= e($shareUrl) ?>" title="<?= e($shareUrl) ?>">copy read link</button>
              <span class="dim">anyone with this link can read it · no session needed · bots get a 404 · rotate <code>preview_secret</code> to kill every link</span>
            <?php else: ?>
              <span class="dim">set <code>preview_secret</code> in config.php to enable share links</span>
            <?php endif; ?>
          </div>
        </details>
        <?php endif; ?>
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
    $unscored = count($scoreAll) - count($scored);
    $floors = array_values(array_filter($scored, fn(array $r): bool => $r['band'] === 'floor'));
    $underread = array_values(array_filter($scoreAll, fn(array $r): bool => ($r['gap'] ?? 0) <= -5));
    $rubrics = array_unique(array_filter(array_column(array_column($scoreAll, 'scored'), 'rubric')));
    // where the corpus loses the most reach: weight × the room left to 10, summed
    $lost = [];
    foreach (SC_AXES as $k => $ax) {
        $vals = array_column(array_column($scored, 'scores'), $k);
        $lost[$k] = $vals ? $ax['weight'] * (10 - array_sum($vals) / count($vals)) : 0;
    }
    arsort($lost);
    $worstAxis = (string) array_key_first($lost);
  ?>
  <h2>Editorial scores <span class="cnt">rubric <?= e(SC_RUBRIC) ?> · <?= count($scored) ?>/<?= count($scoreAll) ?> scored · traffic for <?= e($rangeLabel) ?></span></h2>

  <p class="lead">Every post is graded 1–10 on seven axes before it ships. <b>Reach</b> is their weighted average —
    this model's estimate of how far a post travels, on a 1–10 scale. The two right-hand columns are what actually happened.
    <b>Click any number to see the reasoning behind it.</b></p>

  <div class="cards">
    <div class="scard"><b><?= $avgReach !== null ? number_format($avgReach, 1) : '—' ?></b><span>Avg reach · <?= e(sc_band($avgReach)) ?></span></div>
    <div class="scard"><b><?= count($floors) ?></b><span>Below floor · rewrite</span></div>
    <div class="scard"><b><?= count($underread) ?></b><span>Under-read · distribute</span></div>
    <div class="scard"><b><?= $unscored ?></b><span>Unscored</span></div>
    <div class="scard"><b><?= e(SC_AXES[$worstAxis]['label']) ?></b><span>Weakest axis corpus-wide</span></div>
    <div class="scard"><b><?= $scoreCorr !== null && $scoreCorr['reach']['pv'] !== null ? ($scoreCorr['reach']['pv'] > 0 ? '+' : '') . number_format($scoreCorr['reach']['pv'], 2) : '—' ?></b><span>Reach ρ vs views</span></div>
  </div>

  <?php if (count($rubrics) > 1): ?>
    <p class="warn">Mixed rubric versions in the corpus (<?= e(implode(', ', $rubrics)) ?>). Scores from different rubrics are not comparable. Re-score everything to one version.</p>
  <?php endif; ?>
  <?php if ($unscored > 0): ?>
    <p class="warn"><?= $unscored ?> post<?= $unscored === 1 ? '' : 's' ?> carry no scores. Add a <code>**Scores:**</code> line to the markdown source and rebuild.</p>
  <?php endif; ?>

  <details class="legend">
    <summary>What the numbers mean, and what to do about them</summary>
    <div class="legend-body">
      <div>
        <h3>Reach bands</h3>
        <table class="mini">
          <?php foreach (['strong', 'solid', 'weak', 'floor'] as $b): ?>
          <tr>
            <td><span class="badge b-<?= e($b) ?>"><?= e($b) ?></span></td>
            <td class="n dim"><?= e(SC_BAND_DOC[$b]['range']) ?></td>
            <td><?= e(SC_BAND_DOC[$b]['do']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <p class="note"><b>Gap</b> compares a published post's rank by score with its rank by views.
          <span class="delta down">−5</span> means it sits five places lower in views than in score: the writing landed, the distribution did not.
          <span class="delta up">+5</span> is the reverse — readers found something the rubric does not measure yet.</p>
      </div>
      <div>
        <h3>The seven axes</h3>
        <table class="mini">
          <?php foreach (SC_AXES as $k => $ax): ?>
          <tr>
            <td><b><?= e($ax['label']) ?></b><br><span class="dim"><?= e($ax['cat']) ?></span></td>
            <td class="n dim"><?= number_format($ax['weight'] * 100) ?>%</td>
            <td><?= e(SC_AXIS_DOC[$k]['what']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <p class="note">Weights are a research-backed prior, not a measurement of this blog. The correlation table below is what will eventually replace them.
          Full rubric: <code>docs/SCORING-RUBRIC.md</code>.</p>
      </div>
    </div>
  </details>

  <div class="sc-controls">
    <span class="ctl-label">Show</span>
    <div class="seg">
      <?php foreach ($scoreScopes as $k => $scope): ?>
        <?php if ($scoreFilter === $k): ?>
          <span><?= e($scope['label']) ?> <?= (int) $scoreCounts[$k] ?></span>
        <?php else: ?>
          <a href="<?= e(sc_url(['sort' => $scoreSort, 'dir' => $scoreDir], $daysRaw, $k)) ?>"><?= e($scope['label']) ?> <?= (int) $scoreCounts[$k] ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <span class="ctl-label">Jump to</span>
    <div class="seg">
      <a href="<?= e(sc_url(['sort' => 'reach', 'dir' => 'asc'], $daysRaw, $scoreFilter)) ?>">Weakest first</a>
      <a href="<?= e(sc_url(['sort' => 'reach', 'dir' => 'desc'], $daysRaw, $scoreFilter)) ?>">Best first</a>
      <a href="<?= e(sc_url(['sort' => 'pv', 'dir' => 'desc'], $daysRaw, $scoreFilter)) ?>">Most read</a>
      <a href="<?= e(sc_url(['sort' => 'gap', 'dir' => 'asc'], $daysRaw, $scoreFilter)) ?>">Biggest gap</a>
      <a href="<?= e(sc_url(['sort' => 'date', 'dir' => 'desc'], $daysRaw, $scoreFilter)) ?>">Newest</a>
    </div>
  </div>

  <table class="sc-table">
    <thead>
    <tr>
      <?= sc_th('reach', 'Reach', $scoreSort, $scoreDir, $daysRaw, $scoreFilter, 'r', 'Weighted average of the seven axes, 1–10') ?>
      <?= sc_th('date', 'Post', $scoreSort, $scoreDir, $daysRaw, $scoreFilter, '', 'Sort by publish date') ?>
      <?php foreach (SC_AXES as $k => $ax): ?>
        <?= sc_th($k, $ax['label'], $scoreSort, $scoreDir, $daysRaw, $scoreFilter, 'ax', SC_AXIS_DOC[$k]['what'] . ' · weight ' . number_format($ax['weight'] * 100) . '%') ?>
      <?php endforeach; ?>
      <?= sc_th('pv', 'Views', $scoreSort, $scoreDir, $daysRaw, $scoreFilter, 'r', 'Pageviews in the selected range') ?>
      <?= sc_th('time', 'Avg time', $scoreSort, $scoreDir, $daysRaw, $scoreFilter, 'r', 'Average engaged time per view') ?>
      <?= sc_th('gap', 'Gap', $scoreSort, $scoreDir, $daysRaw, $scoreFilter, 'r', 'Rank by score minus rank by views: negative = read less than it scored') ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($scoreRows as $r): ?>
    <tr class="r-<?= e($r['band']) ?>">
      <td class="reach" data-ax="Reach">
        <?php if ($r['reach'] !== null): ?>
          <button type="button" class="badge b-<?= e($r['band']) ?> scbtn" data-slug="<?= e($r['slug']) ?>" data-axis=""
            aria-label="Reach <?= number_format($r['reach'], 1) ?> — how it adds up"><?= number_format($r['reach'], 1) ?></button>
        <?php else: ?>
          <span class="badge b-none">—</span>
        <?php endif; ?>
      </td>
      <td class="pathcell">
        <span class="post-t"><a href="<?= $r['status'] === 'published' ? e(post_path($r['slug'])) : '/preview.php?slug=' . e($r['slug']) ?>" target="_blank" rel="noopener"><?= e($r['title']) ?></a></span>
        <span class="post-sub"><?= e(date('Y-m-d', $r['publish_ts'])) ?> · <?= number_format($r['words']) ?> words<?= $r['status'] !== 'published' ? ' · ' . e($r['status']) : '' ?><?= $r['scored']['model'] !== '' ? ' · ' . e($r['scored']['model']) : '' ?></span>
      </td>
      <?php foreach (array_keys(SC_AXES) as $k): ?>
        <?= sc_cell($r['slug'], $k, $r['scores'][$k] ?? null, (string) ($scoreNotes[$r['slug']][$k] ?? '')) ?>
      <?php endforeach; ?>
      <td class="r n" data-ax="Views"><?= $r['pv'] ? number_format($r['pv']) : '<span class="dim">0</span>' ?></td>
      <td class="r n" data-ax="Avg time"><?= an_fmt_secs($r['avg_time']) ?></td>
      <?= sc_gap_cell($r['gap']) ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$scoreRows): ?><tr><td colspan="<?= 5 + count(SC_AXES) ?>" class="dim">no posts in this view</td></tr><?php endif; ?>
    </tbody>
  </table>
  <p class="note">Views, average time and gap are for <?= e($rangeLabel) ?> — change it with the range selector above.
    Column headers sort, and clicking the one you are on flips the direction.</p>

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

  <?php
  /* Everything the "why is this number what it is" popup needs, computed here so
     the page and the popup can never disagree: the rubric text, the band advice,
     and per post the stored rationale plus the arithmetic behind the reach score. */
  $scPayload = ['axes' => [], 'bands' => SC_BAND_DOC, 'rubric' => SC_RUBRIC, 'range' => $rangeLabel, 'rows' => []];
  foreach (SC_AXES as $k => $ax) {
      $scPayload['axes'][$k] = SC_AXIS_DOC[$k] + ['label' => $ax['label'], 'cat' => $ax['cat'], 'weight' => $ax['weight']];
  }
  foreach ($scoreAll as $r) {
      $scPayload['rows'][$r['slug']] = [
          'title'  => $r['title'],
          'status' => $r['status'],
          'reach'  => $r['reach'],
          'band'   => $r['band'],
          'scores' => (object) $r['scores'],
          'notes'  => (object) array_intersect_key((array) ($scoreNotes[$r['slug']] ?? []), SC_AXES),
          'contrib' => (object) sc_contributions($r['scores']),
          'lever'  => sc_lever($r['scores']),
          'pv'     => $r['pv'],
          'time'   => an_fmt_secs($r['avg_time']),
          'gap'    => $r['gap'],
          'scored' => $r['scored']['on'] . ($r['scored']['model'] !== '' ? ' · ' . $r['scored']['model'] : ''),
      ];
  }
  ?>
  <script type="application/json" id="sc-data"><?= json_encode($scPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <dialog id="sc-why" class="why" aria-label="Why this score">
    <form method="dialog"><button class="why-x" aria-label="Close">×</button></form>
    <div id="sc-why-body"></div>
  </dialog>
  </div><!-- /scores -->

  <div class="pane wide" id="syndication">
  <h2>Published on <span class="cnt"><?= $statusCounts['published'] ?> published · <?= $statusCounts['scheduled'] ?> scheduled · tagged clicks for <?= e($rangeLabel) ?></span></h2>

  <p class="lead">Every place an essay exists, in one table. A platform column shows the republished URL when the
    post declares one, and the copy button when it does not, so the row you are looking at is also the row you act on.
    <b>Clicks are tagged clicks only.</b> Medium, DEV and LinkedIn all strip the referrer, so an untagged link comes back
    as "direct" and never appears here.</p>

  <div class="cards">
    <?php foreach ($syndPlatform as $key => $platform): ?>
      <div class="scard"><b><?= $syndMissing[$key] ?></b><span>Not on <?= e(SYNDICATION[$platform]['label']) ?> yet</span></div>
    <?php endforeach; ?>
    <div class="scard"><b><?= $syndNowhere ?></b><span>Published here only</span></div>
    <div class="scard"><b><?= count($syndClicks) ?></b><span>Posts with tagged clicks</span></div>
  </div>

  <table>
    <tr>
      <th>Post</th>
      <th>Canonical</th>
      <?php foreach (SHARE_TARGETS as $spec): ?><th><?= e($spec['label']) ?></th><?php endforeach; ?>
    </tr>
    <?php foreach ($syndPosts as $p): ?>
      <?php $share = share_urls($p['slug']); $clicks = $syndClicks[$p['slug']] ?? []; ?>
    <tr class="r-<?= e($p['status']) ?>">
      <td>
        <span class="post-t"><?= e($p['title']) ?></span>
        <span class="post-sub"><?= $p['status'] === 'scheduled' ? 'goes live ' . date('Y-m-d', $p['publish_ts']) : date('Y-m-d', $p['publish_ts']) ?></span>
      </td>
      <td><a class="plink" href="<?= e(post_path($p['slug'])) ?>" target="_blank" rel="noopener">/blog/<?= e($p['slug']) ?>/ ↗</a></td>
      <?php foreach (SHARE_TARGETS as $key => $spec): ?>
        <?php
          $platform = $syndPlatform[$key] ?? null;
          $url = $platform !== null ? ($p['syndication'][$platform] ?? null) : null;
          $pv = $clicks[$key]['pv'] ?? 0;
        ?>
      <td class="synd">
        <?php if ($url !== null): ?>
          <a class="plink" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">live ↗</a>
        <?php else: ?>
          <?php /* nothing to link to yet, so hand over the tagged URL instead of a dash */ ?>
          <button type="button" class="mini cp" data-url="<?= e($share[$key]['url']) ?>" title="<?= e($share[$key]['url']) ?>">copy</button>
        <?php endif; ?>
        <span class="dim n"><?= $pv ?: '·' ?></span>
      </td>
      <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
  </table>

  <p class="note">The links live in the markdown, one line per platform (<code>**Medium:** &lt;url&gt;</code>,
    <code>**DevTo:** &lt;url&gt;</code>), and each one renders an "Also published on …" badge under the hero,
    adds <code>rel="syndication"</code> and a <code>sameAs</code> entry, and leaves this page canonical.
    A URL that is not https on that platform's own host is ignored with a warning at build time.
    Adding a destination is one row in <code>SYNDICATION</code> / <code>SHARE_TARGETS</code> (<code>lib/blog.php</code>);
    this table grows a column by itself.</p>
  </div><!-- /syndication -->

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
  <div class="pane" id="exits"></div>
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
    <div class="scard" title="Hashes are salted per day and unlinkable across days, so this is the sum of each day's uniques — one person reading on Monday and Thursday counts twice. Returning readers are deliberately uncountable."><b><?= number_format($sum['uv']) ?><?= s_delta($an['prev'], 'uv', $sum['uv']) ?></b><span>Daily visitors, summed</span></div>
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
    <h2>Destinations <span class="dim">— the standing pages, ranked or not</span></h2>
    <table><tr><th>Page</th><th>Path</th><th class="r">Views</th><th class="r">vs prev</th><th class="r">Visitors</th><th class="r">Avg time</th></tr>
    <?php $prevBy = []; foreach ($an['destPrev'] as $r) $prevBy[$r['path']] = (int) $r['pv']; ?>
    <?php foreach ($an['dest'] as $p): ?>
      <?php
        $pv = (int) $p['pv'];
        $prev = $prevBy[$p['path']] ?? null;
        // no previous period, or nothing to compare against, is not a 0% change
        $delta = ($prev === null || $prev === 0) ? null : round(($pv - $prev) / $prev * 100);
      ?>
      <tr>
        <td><?= e(AN_DESTINATIONS[$p['path']] ?? $p['path']) ?></td>
        <td class="pathcell"><a class="plink" href="<?= e(s_url(['path' => $p['path']], $daysRaw, $path)) ?>"><?= e($p['path']) ?></a></td>
        <td class="r n"><?= number_format($pv) ?></td>
        <td class="r n dim"><?= $delta === null ? '—' : ($delta > 0 ? '+' : '') . $delta . '%' ?></td>
        <td class="r n"><?= (int) $p['uv'] ?></td>
        <td class="r n"><?= an_fmt_secs((int) round((float) $p['avg_time'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </table>
    <p class="note">A zero here is information: it means nobody reached the page in this range, not that
      the page is missing. <span class="mono">/stemma/index.md</span> is the plain-text mirror, so views on
      it are mostly agents and answer engines rather than people.</p>

    <h2 style="margin-top:2rem">Content <span class="dim">— everything, ranked. Click a path to drill down</span></h2>
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

  <?php
    /* Exits — Acquisition read backwards. A pageview says somebody arrived; only this
       says they left on purpose, and for where. Scoped by ?path= like every other
       analytics view, so drilling into /stemma/ answers "who leaves that page, and to
       what" rather than the site-wide version of the question. */
    $ex = $an['exitSum'];
    // clicks per hundred pageviews. Not a per-reader rate: one reader can click several,
    // so this can exceed 100 and still be correct.
    $exRate = $sum['pv'] > 0 ? $ex['clicks'] / $sum['pv'] * 100 : 0.0;
  ?>
  <div class="pane wide" id="exits">
  <h2>Exits <span class="dim">— where readers go when they leave<?= $path !== '' ? ', from ' . e($path) : '' ?></span></h2>
  <div class="cards">
    <div class="scard"><b><?= number_format($ex['clicks']) ?><?= s_delta($an['exitPrev'], 'clicks', $ex['clicks']) ?></b><span>Outbound clicks</span></div>
    <div class="scard" title="Daily visitor hashes again — the same person leaving on two days counts twice."><b><?= number_format($ex['visitors']) ?><?= s_delta($an['exitPrev'], 'visitors', $ex['visitors']) ?></b><span>Visitor-days with an exit</span></div>
    <div class="scard" title="Outbound clicks per 100 pageviews in this range. One reader can click more than one link, so this is not a percentage of readers and can pass 100."><b><?= number_format($exRate, 1) ?></b><span>Exits per 100 views</span></div>
  </div>

  <div class="grid">
    <?php s_table('Destinations', $an['exitHosts'], null, 'clicks'); ?>
  </div>

  <h2 style="margin-top:2rem">Links <span class="dim">— what was clicked, and the page it was clicked from</span></h2>
  <table><tr><th>Destination</th><th>From</th><th class="r">Clicks</th><th class="r">Visitors</th><th class="r">Last</th></tr>
  <?php $topEx = max(1, ...array_map(fn($r) => (int) $r['clicks'], $an['exitLinks'] ?: [['clicks' => 1]])); ?>
  <?php foreach ($an['exitLinks'] as $r): ?>
    <tr>
      <td class="pathcell cell-bar" style="--w:<?= (int) round($r['clicks'] / $topEx * 100) ?>%"><?= e((string) $r['url']) ?></td>
      <td class="pathcell"><a class="plink" href="<?= e(s_url(['path' => $r['path']], $daysRaw, $path)) ?>"><?= e((string) $r['path']) ?></a></td>
      <td class="r n"><?= number_format((int) $r['clicks']) ?></td>
      <td class="r n"><?= (int) $r['visitors'] ?></td>
      <td class="r n dim"><?= date('m-d H:i', (int) $r['last_ts']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$an['exitLinks']): ?><tr><td colspan="5" class="dim">no exits in range</td></tr><?php endif; ?>
  </table>

  <h2 style="margin-top:2rem">Recent exits <span class="dim">— last <?= count($an['exitRecent']) ?>, filtered ones included</span></h2>
  <table><tr><th>When</th><th>From</th><th>To</th></tr>
  <?php foreach ($an['exitRecent'] as $r): $xv = (int) $r['excl']; ?>
    <tr<?= $xv ? ' class="excluded"' : '' ?>>
      <td class="n"><?= date('m-d H:i', (int) $r['ts']) ?></td>
      <td class="pathcell"><?= e((string) $r['path']) ?></td>
      <td class="pathcell"><?= e((string) $r['url']) ?>
        <?= $xv ? '<span class="badge b-draft">' . e(an_excl_label($xv)) . '</span>' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$an['exitRecent']): ?><tr><td colspan="3" class="dim">nothing yet</td></tr><?php endif; ?>
  </table>

  <p class="note">A click is recorded when a link leaves this host — <span class="mono">github.com</span>,
    a syndication mirror, a cited source — plus <span class="mono">mailto</span>, which is the strongest
    intent the site can observe. Query strings and fragments are dropped before storage; the destination
    path is kept. A click carries no timezone or screen, so it inherits whatever verdict that visitor's
    pageviews got that day, and a click with no pageview behind it is treated as automated. Nothing new
    is stored about the reader: same daily-rotating hash, no address, no user agent.</p>
  </div><!-- /exits -->

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
    <h2>Recent visits <span class="dim">— last <?= count($an['recent']) ?>, filtered ones included</span></h2>
    <p class="note">The only view that shows hits the totals leave out: a filter you cannot watch working is a filter you cannot tune. Timezone is the tell — a browser reporting plain UTC is running on a server.</p>
    <table><tr><th>When</th><th></th><th>Path</th><th>From</th><th>Client</th><th>Timezone</th><th class="r">Read</th></tr>
    <?php foreach ($an['recent'] as $r): $ex = (int) $r['excl']; ?>
      <tr<?= $ex ? ' class="excluded"' : '' ?>>
        <td class="n"><?= date('m-d H:i', (int) $r['ts']) ?></td>
        <td title="<?= e(an_country_name((string) $r['country'])) ?>"><?= an_flag((string) $r['country']) ?: '·' ?></td>
        <td class="pathcell"><?= e((string) $r['path']) ?></td>
        <td><?= $r['ref'] !== '' ? e((string) $r['ref']) : '<span class="dim">' . e((string) $r['ref_group']) . '</span>' ?></td>
        <td class="dim"><?= e(trim($r['browser'] . ' · ' . $r['os'] . ' · ' . $r['device'], ' ·')) ?></td>
        <td class="dim"><?= e((string) $r['tz']) ?: '·' ?>
          <?= $ex ? '<span class="badge b-draft">' . e(an_excl_label($ex)) . '</span>' : '' ?></td>
        <td class="r n"><?= an_fmt_secs((int) $r['duration']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$an['recent']): ?><tr><td colspan="7" class="dim">nothing yet</td></tr><?php endif; ?>
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
        <?php $ipExcluded = an_is_excluded_ip($ip); ?>
        <tr><td>Your connection<br><span class="dim" style="font-size:.78rem">add it to <code>analytics_exclude_ips</code> in config.php to stop counting this line</span></td>
          <td class="n r"><code><?= e($ip !== '' ? $ip : 'unknown') ?></code><br>
            <span class="badge b-<?= $ipExcluded ? 'published' : 'draft' ?>"><?= $ipExcluded ? 'excluded by IP' : 'counted' ?></span></td></tr>
        <tr><td>Excluded networks</td><td class="n r"><?php $exIps = (array) cfg('analytics_exclude_ips', []); ?>
          <?= $exIps ? e(implode(', ', array_map('strval', $exIps))) : '<span class="dim">none</span>' ?></td></tr>
        <?php if ($exclCounts !== null): $exTotal = $exclCounts[AN_AUTOMATED] + $exclCounts[AN_OWNER]; ?>
        <tr><td>Hits not counted · 30d<br><span class="dim" style="font-size:.78rem">headless browsers run the beacon and wear a normal user agent; these are kept, not deleted — <code>php tools/analytics_prune.php --list</code></span></td>
          <td class="n r"><?= $exTotal ?> of <?= $exTotal + $exclCounts[AN_COUNTED] ?><br>
            <span class="dim"><?= $exclCounts[AN_AUTOMATED] ?> automated · <?= $exclCounts[AN_OWNER] ?> owner</span></td></tr>
        <?php endif; ?>
        <?php $logDir = (string) cfg('analytics_access_log_dir', ''); ?>
        <tr><td>Beacon vs web server log<br><span class="dim" style="font-size:.78rem">two counts of the same event — requests should always exceed rows, which also count engagement pings and deliberate drops</span></td>
          <td class="n r">
            <?php if ($logRecon): foreach ($logRecon as $lr): ?>
              <?= e($lr['day']) ?>: <?= $lr['rows'] ?> rows / <?= $lr['requests'] ?> requests
              <span class="badge b-<?= $lr['ok'] ? 'published' : 'draft' ?>"><?= $lr['ok'] ? 'consistent' : 'rows exceed requests' ?></span><br>
            <?php endforeach; elseif ($logDir === ''): ?>
              <span class="dim">set <code>analytics_access_log_dir</code> in config.php to cross-check</span>
            <?php else: ?>
              <span class="dim">no readable <code>YYYY-MM-DD.log</code> in <?= e($logDir) ?></span>
            <?php endif; ?>
          </td></tr>
        <tr><td>Timezone</td><td class="n r"><?= e((string) cfg('timezone', 'Europe/Warsaw')) ?></td></tr>
        <tr><td>PHP</td><td class="n r"><?= PHP_VERSION ?></td></tr>
        <tr><td>Last build</td><td class="n r"><?= admin_last_build_ts() ? date('Y-m-d H:i', admin_last_build_ts()) : 'never' ?></td></tr>
      </table>
  </div><!-- /system -->
  <?php endif; ?>

  <p class="foot" id="anfoot">No cookies for visitors, no external services. Visitor hashes rotate daily, so “daily visitors, summed” is exactly that — the same reader on two days is two, and there is no way to tell otherwise without storing an identifier this site refuses to keep. IP geolocation by <a href="https://db-ip.com">DB-IP</a> (CC BY 4.0)<?= geoip_db_age_days() === null ? ' — <strong>geoip.dat missing: run php tools/geoip_update.php</strong>' : ', database ' . geoip_db_age_days() . ' days old' ?>.<br>
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
  var barIds = { overview: 1, content: 1, acquisition: 1, audience: 1, activity: 1, scores: 1, syndication: 1 };
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

// tagged share links: one click to the clipboard, because a link that has to be
// hand-assembled is a link that gets posted bare
document.addEventListener('click', function (ev) {
  var b = ev.target.closest && ev.target.closest('button.cp');
  if (!b || !navigator.clipboard) return;
  navigator.clipboard.writeText(b.dataset.url).then(function () {
    var was = b.textContent;
    b.textContent = 'copied';
    b.classList.add('done');
    setTimeout(function () { b.textContent = was; b.classList.remove('done'); }, 1200);
  });
});

// "why is this number what it is": every score in the table opens the rubric
// text, the stored rationale for this post, and its arithmetic share of the
// reach total. Hover titles stay as the no-JS fallback.
(function () {
  var el = document.getElementById('sc-data'), dlg = document.getElementById('sc-why');
  if (!el || !dlg || typeof dlg.showModal !== 'function') return;
  var SC = JSON.parse(el.textContent), body = document.getElementById('sc-why-body');
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  };
  var fmt = function (n, d) { return Number(n).toFixed(d === undefined ? 2 : d); };

  function measured(row) {
    var gap = row.gap === null ? '' : ' · gap <b>' + (row.gap > 0 ? '+' : '−') + Math.abs(row.gap) + '</b>';
    if (row.status !== 'published') return '<p class="note">Not published yet — no audience to compare against.</p>';
    return '<p class="note">Measured over ' + esc(SC.range) + ': <b>' + row.pv + '</b> views · <b>' + esc(row.time) + '</b> avg time' + gap + '</p>';
  }

  // one axis: what it measures, where this post sits on the ladder, why it scored that, what it is worth
  function axisView(row, key) {
    var ax = SC.axes[key], v = row.scores[key], c = row.contrib[key], note = row.notes[key];
    var h = '<h3>' + esc(ax.label) + ' <span class="dim" style="font-weight:400;font-size:.8rem">' + esc(ax.cat) + ' · weight ' + Math.round(ax.weight * 100) + '%</span></h3>'
          + '<div class="why-post">' + esc(row.title) + (row.scored ? ' · scored ' + esc(row.scored) : '') + '</div>'
          + '<div class="why-big"><b>' + (v == null ? '—' : v) + '</b><span class="dim">out of 10</span></div>'
          + '<p>' + esc(ax.what) + '</p>';
    h += '<div class="why-lbl">Why this post scored ' + (v == null ? '—' : v) + '</div>';
    h += note ? '<div class="quote">' + esc(note) + '</div>'
              : '<p class="note">No rationale recorded for this axis. Rubric v1 wants one verbatim quote per score — add it to <code>content/score-notes.json</code>.</p>';
    h += '<div class="why-lbl">The rubric band it sits in</div><ul class="ladder">';
    [1, 3, 5, 7, 9].forEach(function (a, i) {
      var next = [1, 3, 5, 7, 9][i + 1];
      var here = v != null && v >= a && (next === undefined || v < next);
      h += '<li class="' + (here ? 'here' : '') + '"><b>' + a + '</b><span>' + esc(ax.anchors[a]) + '</span></li>';
    });
    h += '</ul>';
    if (c) {
      h += '<div class="why-lbl">What it is worth</div>'
        + '<p>' + fmt(ax.weight) + ' × ' + v + ' = <b>' + fmt(c.points) + '</b> of this post\'s ' + fmt(row.reach, 1)
        + ' reach score — ' + c.share + '% of the total. One more point here would add ' + fmt(ax.weight) + '.</p>';
    }
    h += '<div class="why-lbl">Why it carries this weight</div><p class="note">' + esc(ax.why) + '</p>';
    return h;
  }

  // the reach score, taken apart into the seven numbers that made it
  function reachView(row) {
    var band = SC.bands[row.band] || SC.bands.none;
    var h = '<h3>Reach ' + fmt(row.reach, 1) + ' <span class="badge b-' + esc(row.band) + '">' + esc(row.band) + '</span></h3>'
          + '<div class="why-post">' + esc(row.title) + (row.scored ? ' · scored ' + esc(row.scored) : '') + '</div>'
          + '<p><b>' + esc(band.range) + '.</b> ' + esc(band.do) + '</p>'
          + '<div class="why-lbl">How it adds up</div><table><tbody>';
    Object.keys(row.contrib).forEach(function (k) {
      var c = row.contrib[k];
      h += '<tr><td>' + esc(SC.axes[k].label) + '</td>'
         + '<td class="r n mult">' + c.score + ' × ' + fmt(c.weight) + '</td>'
         + '<td class="bar"><span style="--w:' + Math.max(4, c.share * 3) + '%"></span></td>'
         + '<td class="r n">' + fmt(c.points) + '</td>'
         + '<td class="r n dim">' + c.share + '%</td></tr>';
    });
    h += '<tr class="sum"><td>Reach score</td><td class="r n"></td><td></td><td class="r n">' + fmt(row.reach, 1) + '</td><td class="r n dim">100%</td></tr>'
       + '</tbody></table>';
    if (row.lever) {
      h += '<div class="why-lbl">Biggest lever</div><p><b>' + esc(SC.axes[row.lever.axis].label) + '</b> sits at ' + row.lever.score
         + '. Taking it to 10 would add <b>' + fmt(row.lever.gain) + '</b> — more than any other axis, because it combines weight with room left.</p>';
    }
    h += measured(row);
    h += '<p class="note">Reach is a weighted average of seven 1–10 judgements made before publishing, not a measurement of traffic. '
       + 'It is a prediction to check against the columns on the right, and rubric ' + esc(SC.rubric) + ' is what it was scored under.</p>';
    return h;
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('.scbtn') : null;
    if (!btn) return;
    var row = SC.rows[btn.getAttribute('data-slug')];
    if (!row) return;
    var axis = btn.getAttribute('data-axis');
    body.innerHTML = axis ? axisView(row, axis) : reachView(row);
    dlg.showModal();
  });
  // clicking the backdrop closes it; clicking the card must not
  dlg.addEventListener('click', function (ev) { if (ev.target === dlg) dlg.close(); });
})();
</script>
</body>
</html>
