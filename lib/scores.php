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

/**
 * What each band means for the post, in the words the rubric uses. The admin
 * panel shows this next to the number: a score nobody can act on is decoration.
 */
const SC_BAND_DOC = [
    'strong' => ['range' => '7.5 and up', 'do' => 'Publish and push it: HN, LinkedIn, the newsletter, cross-posts.'],
    'solid'  => ['range' => '6.0 – 7.4',  'do' => 'Publish. Normal distribution effort.'],
    'weak'   => ['range' => '4.5 – 5.9',  'do' => 'Fix the lowest axis before promoting it anywhere.'],
    'floor'  => ['range' => 'below 4.5',  'do' => 'Rewrite it or drop it. It is not earning its place in the archive.'],
    'none'   => ['range' => 'not scored', 'do' => 'Add a **Scores:** line to the markdown source and rebuild.'],
];

/**
 * The rubric in machine-readable form: what each axis measures, the anchors at
 * 1/3/5/7/9, and why it carries the weight it does. This is a mirror of
 * docs/SCORING-RUBRIC.md kept next to the weights on purpose — the admin panel
 * explains every number from here, so a rubric change and the explanation of
 * that change cannot drift apart. Prose stays short: it is read in a popup.
 */
const SC_AXIS_DOC = [
    'tension' => [
        'what' => 'How much friction, stakes or discomfort the piece creates, and whether it sustains it.',
        'anchors' => [
            1 => 'Pure agreement. Nothing is at risk. The reader nods and leaves.',
            3 => 'A mild observation. A problem is named, but nobody loses anything by ignoring it.',
            5 => 'A real problem with a named cost, resolved comfortably before the end.',
            7 => 'Names something the reader recognises and would rather not admit. The cost is concrete.',
            9 => 'Puts a widely-held practice on trial and refuses to let the reader off. The discomfort outlives the last line.',
        ],
        'why' => 'Heaviest weight. In the Upworthy RCT archive (22,743 randomised headline trials) each extra negative word lifted click-through 2.3%, while positive words lowered it. Tension is charge, not pessimism: naming a real cost and then showing the way out scores high.',
    ],
    'originality' => [
        'what' => 'Distance from what the reference blogs have already published.',
        'anchors' => [
            1 => 'Restates a well-known post by a bigger voice without extending it.',
            3 => 'A known idea in fresh words.',
            5 => 'A known idea plus one genuine extension.',
            7 => 'A framing not found elsewhere: a search of the reference list turns up nothing equivalent.',
            9 => 'A new concept, with a name other people could cite.',
        ],
        'why' => 'Scored against an actual search of the reference blogs. A 7 or higher without that search is a guess, not a score.',
    ],
    'personal' => [
        'what' => 'How much of the author is load-bearing in the argument.',
        'anchors' => [
            1 => 'Could have been written by anyone. No first person, or first person with nothing behind it.',
            3 => 'First person, but the anchor is generic ("in my experience").',
            5 => 'One real situation, described lightly.',
            7 => 'A specific scene with a decision, a cost and the author\'s own mistake, inside the first four paragraphs.',
            9 => 'The essay could not exist without this author. The scar is load-bearing.',
        ],
        'why' => 'On the Hacker News front page personal blogs take 26% of items against corporate blogs\' 11%. First-person is not a style preference here, it is the distribution channel.',
    ],
    'contrarian' => [
        'what' => 'Distance from consensus — and whether the post defends the distance.',
        'anchors' => [
            1 => 'Consensus, undefended.',
            3 => 'Consensus with a caveat.',
            5 => 'A minority position, stated but not defended.',
            7 => 'A position the audience half-believes but rarely says out loud, defended with reasoning or data.',
            9 => 'Directly contradicts a norm the audience professes, and answers the obvious objection inside the text.',
        ],
        'why' => 'Hard rule: a position taken and not defended caps this axis at 4. Contrarian without argument is noise, and noise damages the brand it was meant to build.',
    ],
    'substance' => [
        'what' => 'Transferable knowledge per thousand words.',
        'anchors' => [
            1 => 'Opinion only. Nothing to reuse.',
            3 => 'One reusable idea, stated abstractly.',
            5 => 'A clear model or heuristic the reader can still name afterwards.',
            7 => 'A model, the conditions under which it fails, and at least one concrete artifact: a number, a diagram, code, a named tool.',
            9 => 'The reader can apply it on Monday and knows when not to.',
        ],
        'why' => 'Substance keeps the archive worth linking to. It is weighted below tension because it wins the second read, not the first click.',
    ],
    'punch' => [
        'what' => 'Sentence-level craft: rhythm, concrete verbs, lines that survive out of context.',
        'anchors' => [
            1 => 'Long undifferentiated paragraphs, hedged, adverb-heavy, no rhythm.',
            3 => 'Readable but flat. Sentences average 25+ words. No line worth quoting.',
            5 => 'Clean prose, some variation, one decent line.',
            7 => 'Deliberate rhythm, concrete verbs, an aphorism that earns its bold.',
            9 => 'Nearly every section has a line that survives out of context. No sentence could be cut without loss.',
        ],
        'why' => 'Adult web prose engages best around Flesch 60–70; shorter sentences track lower bounce and longer dwell. Craft is the multiplier on everything above it, not a substitute.',
    ],
    'trend' => [
        'what' => 'Proximity to what the market is actively arguing about, judged against the dated trend list in the rubric.',
        'anchors' => [
            1 => 'A topic nobody is discussing, and not by design.',
            3 => 'Evergreen, with no current hook.',
            5 => 'Adjacent to a live conversation; the connection is implicit.',
            7 => 'Explicitly inside a named live conversation from the list.',
            9 => 'On the leading edge of one, with a position most posts do not have yet.',
        ],
        'why' => 'Lowest weight because it decays: this axis is re-scored whenever the trend list is refreshed, while the other six stay put.',
    ],
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

/**
 * The reach score, taken apart: what every axis contributed and how much of the
 * total it is. This is the answer to "why is this 6.8" — the same arithmetic the
 * admin popup shows, computed once here so the page cannot invent its own.
 *
 * Returns axis => ['score', 'weight', 'points' (weight × score), 'share' (% of reach)],
 * ordered by points descending: the biggest reason first.
 */
function sc_contributions(array $scores): array
{
    $reach = sc_reach($scores);
    if ($reach === null) return [];
    $out = [];
    foreach (SC_AXES as $k => $ax) {
        $points = round($ax['weight'] * $scores[$k], 2);
        $out[$k] = [
            'score'  => (int) $scores[$k],
            'weight' => $ax['weight'],
            'points' => $points,
            'share'  => (int) round($points / $reach * 100),
        ];
    }
    uasort($out, fn(array $a, array $b): int => $b['points'] <=> $a['points']);
    return $out;
}

/**
 * The axis worth fixing first: the one where a rewrite buys the most reach.
 * Weight alone would always name Tension; headroom alone would name whatever
 * scored lowest. The lever is the product — weight × room left to 10.
 */
function sc_lever(array $scores): ?array
{
    if (!sc_complete($scores)) return null;
    $best = null;
    foreach (SC_AXES as $k => $ax) {
        $gain = round($ax['weight'] * (10 - $scores[$k]), 2);
        if ($best === null || $gain > $best['gain']) $best = ['axis' => $k, 'gain' => $gain, 'score' => (int) $scores[$k]];
    }
    return $best !== null && $best['gain'] > 0 ? $best : null;
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
 * Where the model and the audience disagree, per post: the post's rank by reach
 * score minus its rank by pageviews, over published posts that were live for the
 * whole window. Negative means it scored better than it was read (a distribution
 * problem, not a writing one); positive means readers found something the rubric
 * missed. Every other row gets null — a scheduled post has no audience yet, and
 * ranking it against ones that do would invent a finding.
 *
 * Returns the rows with a 'gap' key added, order untouched.
 */
function sc_rank_gaps(array $rows): array
{
    $eligible = array_keys(array_filter(
        $rows,
        fn(array $r): bool => $r['reach'] !== null && $r['status'] === 'published'
    ));
    if (count($eligible) < 3) return array_map(fn(array $r): array => $r + ['gap' => null], $rows);

    $byReach = $eligible;
    usort($byReach, fn($a, $b) => (float) $rows[$b]['reach'] <=> (float) $rows[$a]['reach']);
    $byViews = $eligible;
    usort($byViews, fn($a, $b) => (int) $rows[$b]['pv'] <=> (int) $rows[$a]['pv']);

    $reachRank = array_flip($byReach);
    $viewsRank = array_flip($byViews);
    $out = [];
    foreach ($rows as $i => $r) {
        $out[$i] = $r + ['gap' => isset($reachRank[$i]) ? $reachRank[$i] - $viewsRank[$i] : null];
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
