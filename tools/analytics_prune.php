<?php
/**
 * tools/analytics_prune.php — decide which stored hits count.
 *
 * Two kinds of traffic end up in content/analytics.db without belonging in the
 * numbers, and neither can be caught by the user-agent filter in a.php:
 *
 *   automated — headless browsers that run the beacon from a UTC-clocked
 *               server. an_looks_automated() judges these at write time; here
 *               --rescan re-judges every row when that rule changes.
 *   owner     — your own browsing from a device that never opened the
 *               dashboard (the bfx cookie only lands in browsers that did) and
 *               from before analytics_exclude_ips covered your line. The
 *               visitor hash rotates daily and holds no address, so the only
 *               handle left is when it happened and what the client looked
 *               like: --owner marks a day range narrowed by exact dimensions.
 *
 * Nothing is deleted. A marked row stays in the table and out of every total,
 * and --restore puts it back, so a filter set too wide costs an edit and not a
 * week of traffic. Every mode counts first and writes only with --apply.
 *
 * Usage:
 *   php tools/analytics_prune.php --list [--days=30]
 *   php tools/analytics_prune.php --rescan [--apply]
 *   php tools/analytics_prune.php --owner --from=2026-07-01 --to=2026-07-27 \
 *          --tz=Europe/Warsaw --os=Linux --browser=Chrome --country=PL [--apply]
 *   php tools/analytics_prune.php --restore --from=… --to=… [dimensions] [--apply]
 *
 * Dimension flags accept any breakdown column: country, browser, os, device,
 * lang, screen, tz, ref, path, utm_source, utm_medium, utm_campaign, ref_group.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/analytics.php';

$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!preg_match('/^--([a-z_]+)(?:=(.*))?$/', $arg, $m)) {
        fwrite(STDERR, "unknown argument: {$arg}\n");
        exit(1);
    }
    $opts[$m[1]] = $m[2] ?? true;
}

$usage = function (string $why = ''): never {
    if ($why !== '') fwrite(STDERR, "{$why}\n\n");
    fwrite(STDERR, "usage:\n"
        . "  php tools/analytics_prune.php --list [--days=30]\n"
        . "  php tools/analytics_prune.php --rescan [--apply]\n"
        . "  php tools/analytics_prune.php --owner   --from=YYYY-MM-DD --to=YYYY-MM-DD [--tz=… --os=… …] [--apply]\n"
        . "  php tools/analytics_prune.php --restore --from=YYYY-MM-DD --to=YYYY-MM-DD [--tz=… --os=… …] [--apply]\n");
    exit(1);
};

if (!is_file(an_db_path())) $usage('no analytics database yet: ' . an_db_path());
$db = an_db();
$apply = !empty($opts['apply']);

/* ---------------- --list: what is in there, and how it is judged ---------------- */

if (!isset($opts['rescan']) && !isset($opts['owner']) && !isset($opts['restore'])) {
    $days = max(1, (int) ($opts['days'] ?? 30));
    $since = gmdate('Y-m-d', time() - $days * 86400);
    $counts = an_excl_counts($db, $since);
    printf("last %d days (since %s, UTC days)\n\n", $days, $since);
    foreach ($counts as $verdict => $n) printf("  %-10s %5d\n", an_excl_label($verdict), $n);

    // the shape of what is excluded, so a wrong rule is visible rather than silent
    $st = $db->prepare("SELECT excl, country, browser, os, device, screen, tz, lang, COUNT(*) n
        FROM hits WHERE day >= ? AND excl != 0
        GROUP BY excl, country, browser, os, device, screen, tz, lang ORDER BY n DESC LIMIT 25");
    $st->execute([$since]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        echo "\nexcluded clients:\n";
        foreach ($rows as $r) {
            printf("  %-10s %-3s %-16s %-9s %-8s %-8s %-20s %-3s  %d\n",
                an_excl_label((int) $r['excl']), $r['country'] ?: '??', $r['browser'], $r['os'],
                $r['device'], $r['screen'] ?: '–', $r['tz'] ?: '(none)', $r['lang'] ?: '–', $r['n']);
        }
    }
    exit(0);
}

/* ---------------- --rescan: re-judge automation across all history ---------------- */

if (!empty($opts['rescan'])) {
    if (!$apply) {
        // count the disagreements without writing any of them
        $rows = $db->query('SELECT tz, screen, lang, excl FROM hits')->fetchAll(PDO::FETCH_ASSOC);
        $changed = 0;
        foreach ($rows as $r) {
            if ((int) $r['excl'] === AN_OWNER) continue;
            if ((int) $r['excl'] !== (an_looks_automated($r) ? AN_AUTOMATED : AN_COUNTED)) $changed++;
        }
        printf("%d of %d rows would change verdict — re-run with --apply\n", $changed, count($rows));
        exit(0);
    }
    printf("%d rows re-judged\n", an_reclassify($db));
    printf("%d outbound click(s) followed their pageviews\n", an_sync_click_verdicts($db));
    exit(0);
}

/* ---------------- --owner / --restore: mark a range by hand ---------------- */

$from = (string) ($opts['from'] ?? '');
$to   = (string) ($opts['to'] ?? '');
foreach (['from' => $from, 'to' => $to] as $flag => $val) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) $usage("--{$flag} must be a UTC date, YYYY-MM-DD");
}

$dims = [];
foreach ($opts as $k => $v) {
    if (in_array($k, ['owner', 'restore', 'rescan', 'list', 'apply', 'from', 'to', 'days'], true)) continue;
    if ($v === true) $usage("--{$k} needs a value");
    try { an_check_col($k); } catch (InvalidArgumentException $e) { $usage($e->getMessage()); }
    $dims[$k] = (string) $v;
}

$verdict = !empty($opts['restore']) ? AN_COUNTED : AN_OWNER;
$filter = $dims ? implode(', ', array_map(fn($k, $v) => "{$k}={$v}", array_keys($dims), $dims)) : 'no dimension filter — every row in range';

printf("%s → %s\n  range   %s .. %s\n  match   %s\n",
    $verdict === AN_COUNTED ? 'restore to counted' : 'mark as owner', an_excl_label($verdict), $from, $to, $filter);

$n = an_mark($db, $from, $to, $dims, $verdict, false);
if (!$apply) {
    printf("\n%d row(s) would change — re-run with --apply\n", $n);
    exit(0);
}
printf("\n%d row(s) changed\n", an_mark($db, $from, $to, $dims, $verdict, true));
// clicks carry none of the dimensions the filter reads, so they follow their pageviews
printf("%d outbound click(s) followed their pageviews\n", an_sync_click_verdicts($db));
