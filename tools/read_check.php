<?php
/**
 * tools/read_check.php — how much work is the reader doing.
 *
 *   php tools/read_check.php            every editable post, one line each
 *   php tools/read_check.php <slug>     just that one
 *   php tools/read_check.php --detail   name the sentences that cost the most
 *
 * This is not voice_check. That one asks whether a machine wrote the post, and the corpus passes it.
 * This asks the question that survived: whether a person can read the post at speed without going
 * back over a sentence. Those are different axes, and a few of the voice rules push against this
 * one — a long clause-heavy sentence is excellent for burstiness and expensive for comprehension.
 *
 * The thresholds are not borrowed from a formula book. They are where this corpus already splits:
 * the three posts that read easily sit at 0.49-0.55 commas per sentence and 14-30 nominalisations
 * per thousand words, the six that read hard sit at 0.75-1.07 and 35-53. So the bar is set by the
 * author's own best work rather than by a grade level nobody writes to.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/markdown.php';
require_once __DIR__ . '/../lib/blog.php';

/* Two levels, because a gate that fails everything teaches nothing. FLOOR is where a post is
   genuinely hard work; AIM is where this author's own easiest posts already sit.
   Calibrated against all 31 posts, not against a grade level nobody writes to.

   Length is deliberately not measured. Every post here has a sentence over 34 words and the voice
   rules ask for one; a long sentence is fine. A *deep* one is not, so what is counted is how many
   clauses the reader has to hold open at once. */
const RC_FLOOR = ['flesch' => 52.0, 'commas' => 0.85, 'nominal' => 42.0, 'heavy1k' => 5.0, 'maxload' => 6];
const RC_AIM   = ['flesch' => 62.0, 'commas' => 0.60, 'nominal' => 28.0, 'heavy1k' => 2.5, 'maxload' => 4];

const RC_SUBORD = '/\b(which|that|who|whose|where|when|while|because|although|though|if|unless|whereas|since|so that)\b/i';
const RC_NOMINAL = '/\b\w{4,}(tion|ment|ness|ity|ance|ence|ism|sion)\b/i';

/** Body prose only: no headings, figures, tables, fences, subtitle or meta. */
function rc_prose(string $file): string
{
    $t = preg_split('/\n---\n/', (string) file_get_contents($file))[0];
    $t = preg_replace('/```.*?```/s', '', $t);
    $t = preg_replace('/^#.*$/m', '', (string) $t);
    $t = preg_replace('/!\[.*?\]\(.*?\)/', '', (string) $t);
    $t = preg_replace('/^\|.*$/m', '', (string) $t);
    $t = preg_replace('/^\*[^*]+\*\s*$/m', '', (string) $t);
    $t = preg_replace('/^\*\*[A-Za-z]+:\*\*.*$/m', '', (string) $t);
    $t = preg_replace('/\[([^\]]+)\]\([^)\s]+\)/', '$1', (string) $t);
    // A list item is a unit the reader finishes, but it carries no full stop, so without this the
    // whole list counts as one enormous sentence and every bulleted post looks unreadable.
    $t = preg_replace('/^\s*(?:[-*]|\d+\.)\s+(.*?)\s*$/m', '$1.', (string) $t);
    return trim(preg_replace('/[*`_]/', '', (string) $t) ?? '');
}

function rc_syllables(string $w): int
{
    $w = strtolower(trim($w, ".,;:!?()\"'-"));
    if ($w === '') return 0;
    $n = 0; $prev = false;
    foreach (str_split($w) as $ch) {
        $cur = str_contains('aeiouy', $ch);
        if ($cur && !$prev) $n++;
        $prev = $cur;
    }
    if (str_ends_with($w, 'e') && $n > 1) $n--;
    return max(1, $n);
}

/**
 * Abstract nouns, counting each distinct word at most three times.
 *
 * A post about decisions says "decision" nineteen times, and that is its subject rather than
 * nineteen separate abstractions the reader has to unpack. What actually loads a reader is the
 * *variety* of abstract vocabulary, so a repeated term is charged once for arriving and then
 * stops counting.
 */
function rc_abstract_load(string $t): int
{
    preg_match_all(RC_NOMINAL, $t, $m);
    $seen = [];
    $total = 0;
    foreach ($m[0] as $w) {
        $k = strtolower($w);
        $seen[$k] = ($seen[$k] ?? 0) + 1;
        if ($seen[$k] <= 3) $total++;
    }
    return $total;
}

/**
 * How many turns the reader has to hold open at once: subordinators, plus commas that separate
 * clauses.
 *
 * A series is not a clause. "a, b, c, and d" has three commas and costs the reader one move,
 * because a list is walked down rather than held open, so each series is charged once and its
 * remaining commas are refunded. Without that refund a plain enumeration scores as though it were
 * nested reasoning, which is the opposite of what this is measuring.
 */
function rc_load(string $s): int
{
    $commas = substr_count($s, ',');
    if (preg_match_all('/(?:,[^,;:]{1,70}){2,},\s+(?:and|or)\s/i', $s, $m)) {
        foreach ($m[0] as $series) $commas -= max(0, substr_count($series, ',') - 1);
    }
    return max(0, $commas) + preg_match_all(RC_SUBORD, $s);
}

function rc_measure(string $file): ?array
{
    $t = rc_prose($file);
    $sents = array_values(array_filter(preg_split('/(?<=[.!?])["\x{201D}]?\s+/u', $t) ?: [], fn($s) => str_word_count($s) > 1));
    preg_match_all("/[A-Za-z][A-Za-z'-]*/", $t, $m);
    $words = $m[0];
    if (!$sents || !$words) return null;

    $syl = array_sum(array_map('rc_syllables', $words));
    $lens = array_map('str_word_count', $sents);
    $loads = array_map('rc_load', $sents);

    return [
        'flesch'  => 206.835 - 1.015 * (count($words) / count($sents)) - 84.6 * ($syl / count($words)),
        'commas'  => substr_count($t, ',') / count($sents),
        'nominal' => 1000 * rc_abstract_load($t) / count($words),
        'heavy1k' => 1000 * count(array_filter($loads, fn($l) => $l >= 4)) / count($words),
        'maxload' => max($loads),
        'sents'   => $sents,
        'loads'   => $loads,
        'lens'    => $lens,
    ];
}

/** Per metric: 'fail' below the floor, 'warn' short of the aim, '' when clear. */
function rc_checks(array $r): array
{
    $label = [
        'flesch'  => fn($v) => sprintf('Flesch %.0f', $v),
        'commas'  => fn($v) => sprintf('%.2f commas/sentence', $v),
        'nominal' => fn($v) => sprintf('%.0f abstract nouns/1k', $v),
        'heavy1k' => fn($v) => sprintf('%.1f dense sentences/1k', $v),
        'maxload' => fn($v) => sprintf('%d clauses in one sentence', $v),
    ];
    $higherIsBetter = ['flesch' => true];
    $out = [];
    foreach (RC_FLOOR as $k => $floor) {
        $v = $r[$k];
        $aim = RC_AIM[$k];
        $bad = ($higherIsBetter[$k] ?? false) ? $v < $floor : $v > $floor;
        $off = ($higherIsBetter[$k] ?? false) ? $v < $aim : $v > $aim;
        $out[$k] = [$bad ? 'fail' : ($off ? 'warn' : ''), $label[$k]($v),
                    sprintf(($higherIsBetter[$k] ?? false) ? 'aim ≥%s' : 'aim ≤%s', rtrim(rtrim(number_format($aim, 2, '.', ''), '0'), '.'))];
    }
    return $out;
}

/* ---------------- run ---------------- */

$rest = array_slice($_SERVER['argv'], 1);
$detail = in_array('--detail', $rest, true);
$only = array_values(array_filter($rest, fn($a) => !str_starts_with($a, '--')));

$posts = load_posts(true);
$fail = 0;
$seen = 0;

foreach ($posts as $p) {
    if ($only && !in_array($p['slug'], $only, true)) continue;
    $file = post_source_file($p['slug']);
    if ($file === null) continue;
    $r = rc_measure($file);
    if ($r === null) continue;
    $seen++;

    $checks = rc_checks($r);
    $fails = array_filter($checks, fn($c) => $c[0] === 'fail');
    $warns = array_filter($checks, fn($c) => $c[0] === 'warn');
    if ($fails) $fail++;

    $state = $fails ? 'HARD' : ($warns ? 'ok  ' : 'easy');
    $notes = array_map(fn($c) => $c[1] . ' (' . $c[2] . ')', $fails ?: $warns);
    printf("%-5s %-46s %s\n", $state, $p['slug'], implode(' · ', $notes));

    $bad = $fails ?: $warns;
    if ($detail && $bad) {
        $idx = range(0, count($r['sents']) - 1);
        usort($idx, fn($a, $b) => [$r['loads'][$b], $r['lens'][$b]] <=> [$r['loads'][$a], $r['lens'][$a]]);
        foreach (array_slice($idx, 0, 3) as $i) {
            if ($r['loads'][$i] < 3) continue;
            printf("      %d clauses, %dw: %s\n", $r['loads'][$i], $r['lens'][$i],
                mb_substr(preg_replace('/\s+/', ' ', $r['sents'][$i]) ?? '', 0, 150) . '…');
        }
    }
}

printf("\n%d of %d clear the floor%s\n", $seen - $fail, $seen,
    $fail ? "  ·  --detail names the sentences that cost the most" : '');
exit($fail > 0 ? 1 : 0);
