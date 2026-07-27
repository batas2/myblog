<?php
/**
 * lib/scores.php — the editorial scoring model.
 *
 * Every post carries seven 1–10 scores written into its own markdown source:
 *
 *   **Scores:** tension=7 punch=8 trend=5 personal=9 substance=6 originality=7 contrarian=8
 *   **Scored:** 2026-07-27 rubric=v1 model=claude-opus-5
 *
 * The markdown is the source of truth (git-tracked, survives every rsync deploy,
 * same idiom as Status/Publish). tools/build.php mirrors the parsed scores into
 * content/scores.db so the admin panel can put them next to what analytics
 * actually recorded for that page, and so the weights can eventually be re-fitted
 * against measured traffic instead of guessed from research.
 *
 * The rubric — anchored band descriptors per axis, the evidence-quote rule, the
 * reference blogs, the dated trend list, and the research the weights came from —
 * lives in docs/SCORING-RUBRIC.md. Read it before changing anything here.
 */

declare(strict_types=1);

/**
 * Rubric version stamped into every **Scored:** line. Bump it whenever an axis
 * definition or a weight changes, and re-score the whole corpus: scores from two
 * different rubrics must never be compared, and the admin panel flags the mix.
 */
const SC_RUBRIC = 'v1';

/**
 * The seven axes, in display order: label, category, and reach-score weight.
 * Weights sum to 1.0 and are a research-backed prior, not a measurement — see
 * the "Reach score" section of docs/SCORING-RUBRIC.md. Re-fitting them against
 * real pageviews and engaged time is a one-line edit here plus a rubric bump.
 */
const SC_AXES = [
    'tension'     => ['label' => 'Tension',     'cat' => 'Hook',      'weight' => 0.20],
    'originality' => ['label' => 'Originality', 'cat' => 'Market',    'weight' => 0.18],
    'personal'    => ['label' => 'Personal',    'cat' => 'Voice',     'weight' => 0.16],
    'contrarian'  => ['label' => 'Contrarian',  'cat' => 'Position',  'weight' => 0.15],
    'substance'   => ['label' => 'Substance',   'cat' => 'Substance', 'weight' => 0.13],
    'punch'       => ['label' => 'Punch',       'cat' => 'Craft',     'weight' => 0.10],
    'trend'       => ['label' => 'Trend fit',   'cat' => 'Market',    'weight' => 0.08],
];

/** Reach-score bands: [floor, key, label]. Ordered high to low. */
const SC_BANDS = [
    [7.5, 'strong', 'strong'],
    [6.0, 'solid',  'solid'],
    [4.5, 'weak',   'weak'],
    [0.0, 'floor',  'floor'],
];

/* ---------------- parsing ---------------- */

/**
 * Parse a `**Scores:**` payload ("tension=7 punch=8 …") into axis => int.
 * Unknown keys, non-numeric values, and anything outside 1–10 are dropped:
 * a typo must not silently become a score. Partial sets are allowed through
 * here and reported as incomplete by sc_complete().
 */
function sc_parse_scores(string $raw): array
{
    $out = [];
    if (!preg_match_all('/([a-z_]+)\s*=\s*(-?\d+)/i', $raw, $m, PREG_SET_ORDER)) return $out;
    foreach ($m as $pair) {
        $key = strtolower($pair[1]);
        $val = (int) $pair[2];
        if (!isset(SC_AXES[$key]) || $val < 1 || $val > 10) continue;
        $out[$key] = $val;
    }
    // keep canonical axis order regardless of how the line was written
    $ordered = [];
    foreach (array_keys(SC_AXES) as $k) {
        if (isset($out[$k])) $ordered[$k] = $out[$k];
    }
    return $ordered;
}

/**
 * Parse a `**Scored:**` payload ("2026-07-27 rubric=v1 model=claude-opus-5").
 * Every part is optional; missing ones come back as ''.
 */
function sc_parse_scored(string $raw): array
{
    $on = preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $raw, $m) ? $m[1] : '';
    $rubric = preg_match('/\brubric\s*=\s*(\S+)/i', $raw, $m) ? $m[1] : '';
    $model = preg_match('/\bmodel\s*=\s*(\S+)/i', $raw, $m) ? $m[1] : '';
    return ['on' => $on, 'rubric' => $rubric, 'model' => $model];
}

/** True when every axis has a score — the only state a reach score is computed for. */
function sc_complete(array $scores): bool
{
    foreach (array_keys(SC_AXES) as $k) {
        if (!isset($scores[$k])) return false;
    }
    return true;
}

/** Weighted composite, 1.0–10.0 to one decimal. Null for an incomplete set. */
function sc_reach(array $scores): ?float
{
    if (!sc_complete($scores)) return null;
    $sum = 0.0;
    foreach (SC_AXES as $k => $a) $sum += $a['weight'] * $scores[$k];
    return round($sum, 1);
}

/** Band key for a reach score: strong / solid / weak / floor. */
function sc_band(?float $reach): string
{
    if ($reach === null) return 'none';
    foreach (SC_BANDS as [$floor, $key]) {
        if ($reach >= $floor) return $key;
    }
    return 'floor';
}

/** Render an axis map back into a `**Scores:**` payload, in canonical order. */
function sc_fmt_scores(array $scores): string
{
    $parts = [];
    foreach (array_keys(SC_AXES) as $k) {
        if (isset($scores[$k])) $parts[] = $k . '=' . (int) $scores[$k];
    }
    return implode(' ', $parts);
}

/**
 * Write (or replace) the Scores/Scored meta in a post source, atomically.
 * Returns false for an incomplete or invalid set — half-scored posts are worse
 * than unscored ones, because they look measured.
 */
function sc_write_meta(string $file, array $scores, string $model, string $on = ''): bool
{
    $scores = sc_parse_scores(sc_fmt_scores($scores));   // revalidate whatever the caller passed
    if (!sc_complete($scores)) return false;
    $on = $on !== '' ? $on : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) return false;
    $model = preg_replace('/\s+/', '-', trim($model)) ?: 'unknown';

    return post_meta_write($file, '/^\*\*(Scores|Scored):\*\*/', [
        '**Scores:** ' . sc_fmt_scores($scores),
        '**Scored:** ' . $on . ' rubric=' . SC_RUBRIC . ' model=' . $model,
    ]);
}

/* ---------------- rationale notes (optional) ---------------- */

/**
 * Per-axis evidence quotes and one-line rationales, keyed slug => axis => text.
 * Optional by design: the numbers work without it. Lives in content/ (web-denied
 * by content/.htaccess) and is git-tracked so it deploys with the posts.
 */
function sc_notes_path(): string
{
    return CONTENT_DIR . '/score-notes.json';
}

function sc_notes(): array
{
    static $notes = null;
    if ($notes === null) {
        $f = sc_notes_path();
        $notes = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }
    return $notes;
}

/* ---------------- the mirror database ---------------- */

const SC_SCHEMA_VERSION = 1;

function sc_db_path(): string
{
    return CONTENT_DIR . '/scores.db';
}

/** Open (creating as needed) the scores mirror. */
function sc_db(?string $path = null): PDO
{
    $db = new PDO('sqlite:' . ($path ?? sc_db_path()), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA busy_timeout=3000');
    if ((int) $db->query('PRAGMA user_version')->fetchColumn() === SC_SCHEMA_VERSION) return $db;
    $cols = implode(', ', array_map(fn(string $k): string => $k . ' INTEGER', array_keys(SC_AXES)));
    $db->exec("CREATE TABLE IF NOT EXISTS scores (
        slug TEXT PRIMARY KEY, title TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '',
        publish_ts INTEGER NOT NULL DEFAULT 0, words INTEGER NOT NULL DEFAULT 0,
        {$cols}, reach REAL,
        rubric TEXT NOT NULL DEFAULT '', model TEXT NOT NULL DEFAULT '',
        scored_on TEXT NOT NULL DEFAULT '', synced_ts INTEGER NOT NULL DEFAULT 0)");
    $db->exec('CREATE INDEX IF NOT EXISTS idx_scores_reach ON scores(reach)');
    $db->exec('PRAGMA user_version = ' . SC_SCHEMA_VERSION);
    return $db;
}

/**
 * Refresh the mirror from the parsed posts. This is a cache, not a ledger:
 * the table is replaced wholesale so a deleted or renamed post cannot leave a
 * stale row behind pretending to be scored. Returns the number of rows written.
 */
function sc_sync(PDO $db, array $posts): int
{
    $axes = array_keys(SC_AXES);
    $cols = array_merge(['slug', 'title', 'status', 'publish_ts', 'words'], $axes, ['reach', 'rubric', 'model', 'scored_on', 'synced_ts']);
    $st = $db->prepare('INSERT INTO scores (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');

    $db->beginTransaction();
    try {
        $db->exec('DELETE FROM scores');
        $now = time();
        foreach ($posts as $p) {
            $scores = $p['scores'] ?? [];
            $row = [$p['slug'], $p['title'], $p['status'], $p['publish_ts'], $p['words']];
            foreach ($axes as $k) $row[] = $scores[$k] ?? null;
            $row[] = sc_reach($scores);
            $row[] = (string) ($p['scored']['rubric'] ?? '');
            $row[] = (string) ($p['scored']['model'] ?? '');
            $row[] = (string) ($p['scored']['on'] ?? '');
            $row[] = $now;
            $st->execute($row);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return count($posts);
}

/* ---------------- reading: scores next to reality ---------------- */

/**
 * Attach what analytics recorded for each post's page to its scores.
 * $pages is an_top_pages() output (path, pv, uv, avg_time); posts with no
 * recorded traffic in range come back with zeros rather than being dropped —
 * "nobody read it" is the finding, not a missing row.
 */
function sc_merge_analytics(array $posts, array $pages): array
{
    $byPath = [];
    foreach ($pages as $pg) $byPath[(string) $pg['path']] = $pg;

    $out = [];
    foreach ($posts as $p) {
        $scores = $p['scores'] ?? [];
        $hit = $byPath[post_path($p['slug'])] ?? null;
        $out[] = $p + [
            'reach'    => sc_reach($scores),
            'band'     => sc_band(sc_reach($scores)),
            'pv'       => (int) ($hit['pv'] ?? 0),
            'uv'       => (int) ($hit['uv'] ?? 0),
            'avg_time' => (int) round((float) ($hit['avg_time'] ?? 0)),
        ];
    }
    return $out;
}

/**
 * Spearman rank correlation between two equal-length numeric series, or null
 * when there is nothing to correlate (fewer than 3 pairs, or a series with no
 * variance). Rank-based on purpose: one post that hit Hacker News would
 * dominate a Pearson correlation over a 25-post corpus.
 */
function sc_spearman(array $x, array $y): ?float
{
    $n = count($x);
    if ($n < 3 || $n !== count($y)) return null;
    $rx = sc_ranks(array_values($x));
    $ry = sc_ranks(array_values($y));
    $mx = array_sum($rx) / $n;
    $my = array_sum($ry) / $n;
    $num = $dx = $dy = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $a = $rx[$i] - $mx;
        $b = $ry[$i] - $my;
        $num += $a * $b;
        $dx += $a * $a;
        $dy += $b * $b;
    }
    if ($dx <= 0.0 || $dy <= 0.0) return null;   // a flat series correlates with nothing
    return round($num / sqrt($dx * $dy), 2);
}

/** Fractional ranks, ties sharing their average rank. */
function sc_ranks(array $v): array
{
    $idx = array_keys($v);
    $n = count($idx);
    usort($idx, fn($a, $b) => (float) $v[$a] <=> (float) $v[$b]);
    $ranks = [];
    for ($i = 0; $i < $n;) {
        $j = $i;
        while ($j + 1 < $n && (float) $v[$idx[$j + 1]] === (float) $v[$idx[$i]]) $j++;
        $avg = (float) (($i + $j) / 2 + 1);   // always float: untied ranks are whole, still fractional in type
        for ($k = $i; $k <= $j; $k++) $ranks[$idx[$k]] = $avg;
        $i = $j + 1;
    }
    ksort($ranks);
    return array_values($ranks);
}

/**
 * Per-axis Spearman correlation against measured pageviews and engaged time,
 * over the fully-scored posts that have any recorded traffic. This is the whole
 * point of the exercise: it answers "which axis actually gets read", and once
 * the numbers are stable they are what the SC_AXES weights should be re-fitted to.
 *
 * Returns ['n' => pairs used, 'axes' => [axis => ['pv' => rho|null, 'time' => rho|null]], 'reach' => …].
 */
function sc_correlations(array $rows): array
{
    $usable = array_values(array_filter($rows, fn(array $r): bool => $r['reach'] !== null && $r['pv'] > 0));
    $pv = array_column($usable, 'pv');
    $time = array_column($usable, 'avg_time');

    $axes = [];
    foreach (array_keys(SC_AXES) as $k) {
        $series = array_map(fn(array $r): int => (int) $r['scores'][$k], $usable);
        $axes[$k] = ['pv' => sc_spearman($series, $pv), 'time' => sc_spearman($series, $time)];
    }
    $reach = array_map(fn(array $r): float => (float) $r['reach'], $usable);
    return [
        'n'     => count($usable),
        'axes'  => $axes,
        'reach' => ['pv' => sc_spearman($reach, $pv), 'time' => sc_spearman($reach, $time)],
    ];
}
