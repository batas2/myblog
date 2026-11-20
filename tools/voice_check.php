<?php
/**
 * tools/voice_check.php — the human-voice pass from docs/POST-PROMPT.md, run over the corpus.
 *
 * The prompt ships six greps and a rhythm script that an author runs by hand on one
 * draft. Doing that 29 times by hand is how a rule quietly stops being enforced, so
 * this is the same checks in one pass, per post, with a pass/fail line each.
 *
 *   php tools/voice_check.php                 every post
 *   php tools/voice_check.php <slug> [...]    just these
 *   php tools/voice_check.php --detail        every hit, with line numbers
 *
 * Exit code is 1 when any post fails, so it can gate a build.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/markdown.php';   // parse_post_file renders the body
require_once __DIR__ . '/../lib/blog.php';

/* ---------------- the rules ---------------- */

/** Hard bans: zero occurrences, no exceptions (POST-PROMPT "Vocabulary bans"). */
const VC_BANNED = 'delve|landscape|leverage|robust|crucial|pivotal|showcase|underscore|testament|realm|'
                . 'tapestry|meticulous|intricate|seamless|harness|unlock|foster|bolster|garner|vibrant|myriad|holistic';

/**
 * The 2026 tier: fine alone, a tell in aggregate. Budget is two per essay each,
 * one for `quietly`. Word => budget.
 *
 * `signal` is deliberately absent. POST-PROMPT rates it "fine as a noun in a
 * technical sense, not as 'sends a signal about'", and two posts here are about
 * a radar whose unit of work is literally called a signal. Budgeting every
 * occurrence would push those toward elegant variation, which the same document
 * bans harder. VC_RHETORICAL below catches the use it actually objects to.
 */
const VC_TIER = [
    'quietly' => 1, 'actually' => 2, 'really' => 2, 'real' => 2, 'matters' => 2,
    'shape' => 2, 'shapes' => 2,
    'compound' => 2, 'compounds' => 2, 'earn' => 2, 'earns' => 2, 'earned' => 2,
];

/**
 * The rhetorical `signal`, which has no budget: zero of these. Deliberately
 * narrow — "a signal that cannot cite its source" is the technical noun with a
 * relative clause, and matching on `signals that` would flag it.
 */
const VC_RHETORICAL = '(send(s|ing)?|sent) an? signal|\ba signal (about|to)\b|\bsignall?ing (that|a )';

/** Meta lines are `**Key:** value` by design; the inline-header grep must not see them. */
const VC_META_KEYS = 'Medium|DevTo|Scores|Scored|Tags|Updated|Publish|Status|Related';

/**
 * Posts that mirror a version already published somewhere else, word for word.
 *
 * Those essays are not drafts waiting for a style pass. They have an audience
 * and a view count on the other platform, and editing this copy would fork the
 * two, so the rules in POST-PROMPT do not apply to them and reporting them as
 * failures every run is noise that trains you to ignore the output.
 *
 * A post declaring **Medium:** or **DevTo:** is detected automatically. This
 * list is for the ones whose external URL is not recorded in the markdown yet;
 * once it is, the row can come out of here.
 */
const VC_FROZEN = [
    'technical-leader-identity-disorder',
    'the-automerger',
];

/**
 * Round 5, the cadence tier. Advisory: printed as `~`, never fails the run.
 *
 * Everything above catches what a draft contains. These catch how it moves, which is what was
 * left once the vocabulary was clean: a paragraph that sets up and then lands on a short line,
 * a fragment used as a drum hit, "It is not X. It is Y." written as two sentences so the
 * contrast grep cannot see it. Each is fine once. The tell is the rate.
 *
 * The limits come from the one long text here that no model drafted,
 * technical-leader-identity-disorder: 3% of its paragraphs end on a short sentence and it has
 * 4 fragments per thousand words. The ghostwritten posts run 13-59% and 4-19. So the bar is his
 * own hand with headroom, not a number from a style guide. They stay advisory because a rule
 * every post passes the same way becomes the next fingerprint (HUMAN-VOICE-PLAN, 4.0-1).
 */
const VC_CADENCE = [
    'punch-ending' => 25.0,   // % of multi-sentence paragraphs whose last sentence is <= 8 words
    'fragments'    => 6.0,    // sentences of <= 4 words, per 1000 words
    'neg-pair'     => 1,      // "It was not a wall. It was a queue." and "Not X. Y."
    'bold-closer'  => 1,      // paragraphs whose last sentence is bold
    'anaphora'     => 1,      // neighbouring sentences opening on the same two words
    'disclaimer'   => 0,      // the "notes, not a study" paragraph
];

/** The ritual stake-lowering line. Eleven posts carried a variant of it by round 3. */
const VC_DISCLAIMER = '(what follows is|this is) (a |an |one )[^.]{0,60}, (not|no) (a |an )?\w+'
                    . '|\b(notes|observations|a count|a map|a field report), not (a |an )?(study|science|data|paper|standard|victory lap)'
                    . '|private (notes|observations|opinion)[^.]{0,40}(not|no) (science|study)'
                    . '|not science\b|no study behind';

/* ---------------- helpers ---------------- */

/** Every line of the file, 1-indexed, so hits can be reported where they live. */
function vc_lines(string $file): array
{
    return preg_split('/\r?\n/', (string) file_get_contents($file));
}

/** [lineNo => line] for lines matching $re. */
function vc_grep(array $lines, string $re, ?callable $reject = null): array
{
    $hits = [];
    foreach ($lines as $i => $line) {
        if (!preg_match($re, $line)) continue;
        if ($reject !== null && $reject($line)) continue;
        $hits[$i + 1] = trim($line);
    }
    return $hits;
}

/** All case-insensitive whole-word matches of $words, as "line: word" strings. */
function vc_words(array $lines, string $words): array
{
    $hits = [];
    foreach ($lines as $i => $line) {
        if (preg_match_all('/\b(' . $words . ')\b/i', $line, $m)) {
            foreach ($m[1] as $w) $hits[] = ($i + 1) . ': ' . strtolower($w);
        }
    }
    return $hits;
}

/**
 * Body prose only: no headings, no images, no meta block, no code fences, no
 * blockquotes. The rhythm targets are measured on what a reader reads as prose.
 */
function vc_prose(array $lines): string
{
    $out = [];
    $inFence = false;
    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) { $inFence = !$inFence; continue; }
        if ($inFence) continue;
        $t = trim($line);
        if ($t === '---') break;                              // meta block starts here
        if (preg_match('/^## (Sources|References)\s*$/i', $t)) break;   // a reference list is not prose
        if ($t === '' || $t[0] === '#' || $t[0] === '>') continue;
        if (preg_match('/^!\[.*\]\(.*\)$/', $t)) continue;     // figure lines
        if (preg_match('/^\*\*(' . VC_META_KEYS . '):\*\*/', $t)) continue;
        if (preg_match('/^\*[^*]+\*$/', $t)) continue;         // the italic subtitle
        $out[] = trim((string) preg_replace('/<!--.*?-->/', '', $t));
    }
    return implode(' ', $out);
}

/** Sentence-length stats over body prose: mean, CV, short share, long count. */
function vc_rhythm(string $prose): array
{
    $prose = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $prose);   // links → their text
    $prose = preg_replace('/[*`_]/', '', $prose);
    $sentences = preg_split('/(?<=[.!?])\s+/', $prose, -1, PREG_SPLIT_NO_EMPTY);
    $w = [];
    foreach ($sentences as $s) {
        $n = str_word_count($s);
        if ($n > 1) $w[] = $n;
    }
    if (count($w) < 2) return ['n' => count($w), 'mean' => 0.0, 'cv' => 0.0, 'short' => 0.0, 'long' => 0];

    $n = count($w);
    $mean = array_sum($w) / $n;
    $var = 0.0;
    foreach ($w as $x) $var += ($x - $mean) ** 2;
    $sd = sqrt($var / ($n - 1));

    return [
        'n'     => $n,
        'mean'  => $mean,
        'cv'    => $mean > 0 ? $sd / $mean : 0.0,
        'short' => 100 * count(array_filter($w, fn(int $x): bool => $x <= 6)) / $n,
        'long'  => count(array_filter($w, fn(int $x): bool => $x >= 35)),
    ];
}

/**
 * Prose paragraphs, each as its list of sentences. Same exclusions as vc_prose(), plus list
 * items and table rows: a bullet has no cadence to judge and a table cell is not a sentence.
 */
function vc_paragraphs(array $lines): array
{
    $paras = [];
    $cur = [];
    $inFence = false;
    $flush = function () use (&$paras, &$cur): void {
        if ($cur) $paras[] = implode(' ', $cur);
        $cur = [];
    };
    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) { $inFence = !$inFence; $flush(); continue; }
        if ($inFence) continue;
        $t = trim($line);
        if ($t === '---') break;
        if ($t === '') { $flush(); continue; }
        if ($t[0] === '#' || $t[0] === '>' || $t[0] === '|') { $flush(); continue; }
        if (preg_match('/^!\[.*\]\(.*\)$/', $t) || preg_match('/^([-*]|\d+\.) /', $t)) { $flush(); continue; }
        if (preg_match('/^\*\*(' . VC_META_KEYS . '):\*\*/', $t) || preg_match('/^\*[^*]+\*$/', $t)) { $flush(); continue; }
        $cur[] = $t;
    }
    $flush();

    $out = [];
    foreach ($paras as $p) {
        $p = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $p);
        $p = trim((string) preg_replace('/<!--.*?-->/', '', (string) $p));   // the round-5 marker is not prose
        $s = preg_split('/(?<=[.!?])(?:\*\*)?["\x{201D})]?\s+/u', (string) $p, -1, PREG_SPLIT_NO_EMPTY);
        if ($s) $out[] = $s;
    }
    return $out;
}

/** Words in a sentence, markup ignored. Digits count: "40 people" is two words to a reader. */
function vc_wc(string $s): int
{
    return (int) preg_match_all("/[A-Za-z0-9][A-Za-z0-9'\x{2019}%-]*/u", (string) preg_replace('/[*`_]/', '', $s));
}

/**
 * The cadence tier: label => [ok, summary, hits]. See VC_CADENCE for why these are advisory.
 */
function vc_cadence(array $lines, int $words, float $shortPct = 0.0): array
{
    $paras = vc_paragraphs($lines);
    $multi = array_values(array_filter($paras, fn(array $s): bool => count($s) >= 2));
    $neg = '/\b(not|no|never|nobody|nothing|none)\b|n[\'\x{2019}]t\b/iu';

    $punch = $frag = $pair = $closer = $ana = [];
    foreach ($paras as $s) {
        $last = $s[count($s) - 1];
        if (count($s) >= 2 && vc_wc($last) <= 8) $punch[] = trim($last);
        if (preg_match('/\*\*[^*]+[.!?]\*\*\s*$/', $last)) $closer[] = trim($last);
        foreach ($s as $i => $x) {
            $plain = (string) preg_replace('/[*`_]/', '', $x);
            if (vc_wc($x) <= 4 && vc_wc($x) >= 1) $frag[] = trim($plain);
            if (!isset($s[$i + 1])) continue;
            $next = (string) preg_replace('/[*`_]/', '', $s[$i + 1]);

            // "It was not a wall. It was a queue." — a short denial, then a short claim
            // that opens on a pronoun and denies nothing. Also the fragment form "Not X. Y."
            $denial = preg_match($neg, $plain) && vc_wc($plain) <= 18;
            $claim = vc_wc($next) <= 14 && !preg_match($neg, $next)
                  && preg_match('/^(It|That|They|This|These|Those|The|There|He|She|We|I|Everybody|Everyone)\b/', $next);
            if (($denial && $claim) || (preg_match('/^Not\b/', $plain) && vc_wc($plain) <= 8 && !preg_match($neg, $next))) {
                $pair[] = trim($plain) . ' ' . trim($next);
            }

            $a = array_slice(preg_split('/\s+/', trim($plain)), 0, 2);
            $b = array_slice(preg_split('/\s+/', trim($next)), 0, 2);
            if (count($a) === 2 && $a === $b) $ana[] = implode(' ', $a) . ' … / ' . implode(' ', $b) . ' …';
        }
    }

    $punchPct = $multi ? 100 * count($punch) / count($multi) : 0.0;
    $fragK = $words ? 1000 * count($frag) / $words : 0.0;
    $disc = vc_grep($lines, '/' . VC_DISCLAIMER . '/i');
    $L = VC_CADENCE;

    return [
        'punch-ending' => [$punchPct <= $L['punch-ending'], sprintf('%.0f%% of paragraphs end on a short line (≤%d%%)', $punchPct, $L['punch-ending']), $punch],
        'fragments'    => [$fragK <= $L['fragments'], sprintf('%.1f fragments/1k (≤%d)', $fragK, $L['fragments']), $frag],
        'neg-pair'     => [count($pair) <= $L['neg-pair'], count($pair) . ' denial-then-claim pairs (≤' . $L['neg-pair'] . ')', $pair],
        'bold-closer'  => [count($closer) <= $L['bold-closer'], count($closer) . ' paragraphs close in bold (≤' . $L['bold-closer'] . ')', $closer],
        'anaphora'     => [count($ana) <= $L['anaphora'], count($ana) . ' repeated sentence openings (≤' . $L['anaphora'] . ')', $ana],
        'disclaimer'   => [!$disc, count($disc) . ' ritual disclaimer', $disc],
        'drum'         => [$shortPct <= 22.0, sprintf('%.0f%% of sentences are ≤6 words (his own: 11-12%%)', $shortPct), []],
    ];
}

/**
 * Every check for one post: label => [ok, summary, hit lines].
 * The thresholds are POST-PROMPT's, not softened.
 */
function vc_check(string $file): array
{
    $lines = vc_lines($file);
    $prose = vc_prose($lines);
    $r = vc_rhythm($prose);
    $words = str_word_count(preg_replace('/[*`_]/', '', $prose));

    // 1. spaced em dash — legal only inside an external link's verbatim title
    $emdash = vc_grep($lines, '/ — /', fn(string $l): bool => str_contains($l, ']('));

    // 5. inline-header bullets and paragraphs, meta lines excluded.
    // Wider than POST-PROMPT's grep on purpose: that one misses numbered lists
    // ("1. **A timebox.**") and any bold lead longer than 45 characters, and the
    // rule it enforces is about the layout, not about the item's punctuation.
    $inline = vc_grep(
        $lines,
        '/^([-*]|\d+\.) \*\*|^\*\*[A-Z][^*]{1,80}[.:]\*\*\s/',
        fn(string $l): bool => (bool) preg_match('/^\*\*(' . VC_META_KEYS . '):\*\*/', trim($l))
    );

    $tier = [];
    foreach (vc_words($lines, implode('|', array_keys(VC_TIER))) as $hit) {
        $tier[explode(': ', $hit)[1]][] = $hit;
    }
    $overBudget = [];
    foreach ($tier as $word => $hits) {
        $budget = VC_TIER[$word] ?? 2;
        if (count($hits) > $budget) $overBudget[] = "{$word} " . count($hits) . "/{$budget}";
    }

    $checks = [];

    // Round 5: a paragraph the model wrote with no source sentence behind it is marked in the
    // draft, and the mark comes off when the author has rewritten or accepted it. The renderer
    // would print the marker as text, so one left behind is a failure twice over.
    $marker = vc_grep($lines, '/<!--\s*M\s*-->/');
    $checks['unclaimed'] = [!$marker, count($marker) . ' paragraph still marked <!-- M -->', $marker];

    $checks['em-dash'] = [!$emdash, count($emdash) . ' spaced em-dash outside a link title', $emdash];

    $banned = vc_words($lines, VC_BANNED);
    $checks['banned'] = [!$banned, count($banned) . ' hard-banned word', $banned];

    $checks['tier-2026'] = [!$overBudget, implode(', ', $overBudget), $tier];

    $rhetorical = vc_grep($lines, '/' . VC_RHETORICAL . '/i');
    $checks['signal-as-verb'] = [!$rhetorical, count($rhetorical) . ' rhetorical "signal"', $rhetorical];

    $contrast = vc_grep($lines, "/(it'?s not [^.]{0,40}, it'?s|is not [^.]{0,30}\. (it|this|that)'?s|not just [^.]{0,30} but|rather than a [^.]{0,30},)/i");
    $checks['contrast'] = [!$contrast, count($contrast) . ' contrast reframe', $contrast];

    // "…, further cementing its role as…" — a comma plus an -ing verb asserting importance
    $participial = vc_grep($lines, '/,\s+(further |thereby |thus )?(cementing|highlighting|reflecting|underscoring|emphasi[sz]ing|showcasing|solidifying|reinforcing|demonstrating|signal|marking|illustrating)\w*\s/i');
    $checks['participial'] = [!$participial, count($participial) . ' significance clause', $participial];

    $titleCase = vc_grep($lines, '/^#{2,3} .*[a-z]+ [A-Z][a-z]+ [A-Z][a-z]/');
    $checks['title-case'] = [!$titleCase, count($titleCase) . ' Title Case heading', $titleCase];

    // max one inline-header list, three items — one contiguous run of at most three
    $runs = [];
    $prev = null;
    foreach (array_keys($inline) as $ln) {
        if ($prev !== null && $ln - $prev <= 2) $runs[count($runs) - 1][] = $ln;
        else $runs[] = [$ln];
        $prev = $ln;
    }
    $inlineOk = count($runs) <= 1 && (!$runs || count($runs[0]) <= 3);
    $checks['inline-header'] = [$inlineOk, count($inline) . ' in ' . count($runs) . ' run(s)', $inline];

    $checks['words'] = [$words >= 650 && $words <= 1500, "{$words} words (650–1500)", []];
    // Recalibrated in round 5. The old floors (CV 0.60, 15% short) were set just under the
    // ghostwritten corpus average, and the two texts he wrote himself fail both: CV 0.49-0.50,
    // 11-12% short. A gate his own hand cannot pass was rewarding the model's drum. The floor now
    // sits under his numbers, and too much short is reported by the cadence tier instead.
    $checks['cv'] = [$r['cv'] >= 0.42, sprintf('CV %.2f (≥0.42)', $r['cv']), []];
    $checks['short'] = [$r['short'] >= 7, sprintf('%.0f%% ≤6w (≥7%%)', $r['short']), []];
    $checks['long'] = [$r['long'] >= 1, "{$r['long']} sentence ≥35w (≥1)", []];

    return ['checks' => $checks, 'cadence' => vc_cadence($lines, $words, $r['short']), 'rhythm' => $r, 'words' => $words];
}

/* ---------------- run ---------------- */

$argvRest = array_slice($argv, 1);
$detail = in_array('--detail', $argvRest, true);
$only = array_values(array_filter($argvRest, fn(string $a): bool => !str_starts_with($a, '--')));

$files = glob(CONTENT_DIR . '/posts/*.md');
sort($files);

$failed = 0;
$total = 0;
$frozen = 0;
$cadenced = 0;
foreach ($files as $file) {
    $slug = preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', basename($file, '.md'));
    if ($only && !in_array($slug, $only, true)) continue;

    // an essay that already exists elsewhere is a mirror, not a draft: report it
    // and move on, so the pass/fail count only covers posts anyone can still edit
    $post = parse_post_file($file);
    if (in_array($slug, VC_FROZEN, true) || ($post !== null && $post['syndication'])) {
        $where = $post !== null && $post['syndication']
            ? implode(' + ', array_map(fn(string $p): string => SYNDICATION[$p]['label'], array_keys($post['syndication'])))
            : 'published elsewhere';
        printf("%s %-46s %s\n", 'frozen', $slug, 'mirrors ' . $where . ' — not edited');
        $frozen++;
        continue;
    }
    $total++;

    $res = vc_check($file);
    $bad = array_keys(array_filter($res['checks'], fn(array $c): bool => !$c[0]));
    if ($bad) $failed++;

    printf("%s %-46s %s\n", $bad ? 'FAIL' : 'ok  ', $slug,
        $bad ? implode(' · ', array_map(fn(string $k): string => $k . ': ' . $res['checks'][$k][1], $bad)) : '');

    if ($detail && $bad) {
        foreach ($bad as $k) {
            foreach ($res['checks'][$k][2] as $key => $hit) {
                $text = is_array($hit) ? implode(', ', $hit) : $hit;
                printf("       %-14s %s\n", $k, is_int($key) ? "L{$key}: {$text}" : "{$key}: {$text}");
            }
        }
    }

    // the cadence tier: reported, never counted as a failure
    $soft = array_keys(array_filter($res['cadence'], fn(array $c): bool => !$c[0]));
    if ($soft) {
        $cadenced++;
        printf("   ~ %-46s %s\n", '', implode(' · ', array_map(fn(string $k): string => $res['cadence'][$k][1], $soft)));
        if ($detail) {
            foreach ($soft as $k) {
                foreach ($res['cadence'][$k][2] as $key => $hit) {
                    printf("       %-14s %s\n", $k, mb_strimwidth((is_int($key) && $k === 'disclaimer' ? "L{$key}: " : '') . $hit, 0, 150, '…'));
                }
            }
        }
    }
}

printf("\n%d/%d editable posts clean%s\n", $total - $failed, $total,
    $frozen ? " ({$frozen} frozen: already published elsewhere)" : '');
if ($cadenced) {
    printf("%d of them carry a cadence note (~). Advisory: --detail quotes the lines.\n", $cadenced);
}
exit($failed ? 1 : 0);
