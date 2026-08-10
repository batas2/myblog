<?php
/**
 * stemma/changelog/index.php — what shipped in Stemma, and when.
 *
 * Built to be lifted with its parent. Everything below the site chrome is
 * self-contained: one scoped stylesheet (`.sc-` prefix), no shared assets, no
 * dependency on /stemma/index.php. When Stemma gets its own domain this folder
 * moves with it and only page_head()/site_nav()/site_footer() are replaced.
 *
 * Shape: a spine. Dates in the gutter, releases beside them, newest first —
 * the reading order of somebody asking "is this thing still alive?".
 *
 * The entries are hand-curated rather than generated from the commit log. A
 * commit log is a record of work; a changelog is a claim about what a reader
 * can now do, and the two are not the same list. `stemma/changelog/index.md`
 * mirrors this array in plain text and tests/integration.php asserts every
 * release date here appears there, because a hand-kept twin drifts otherwise.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../lib/config.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/components.php';
require_once __DIR__ . '/../../lib/page.php';
require_once __DIR__ . '/../../lib/schema.php';

$url     = cfg('site_url') . '/stemma/changelog/';
$project = cfg('site_url') . '/stemma/';
$repo    = 'https://github.com/batas2/Stemma';

/* Newest first. `date` is the ISO date the work landed; `slug` anchors it so a single
   release can be linked on its own. `fix` marks an entry that repaired something rather
   than adding it — worth distinguishing, because a changelog that hides its repairs is
   advertising, and this page is not that. */
$releases = [
    [
        'date' => '2026-08-08',
        'slug' => 'headless',
        'name' => 'The headless half',
        'lead' => 'Everything Stemma could do, it could only do with a mouse in it. This release is the '
                . 'other authoring surface: a model written as text, rendered by a command, and checked '
                . 'by a build that has never opened the app.',
        'items' => [
            ['<code>stemma render</code>',
             'A workspace and a view become an image, with no browser of your own open and no mouse. '
             . 'It runs a short-lived backend of its own, so two renders can run at once and neither '
             . 'disturbs a workspace you have open.'],
            ['A publication profile',
             'Outline boxes, no kind chip, no icon, no shadow, no background grid, no status badge. '
             . 'Transparent by default; scale, padding and palette are options. Rendering for a screen '
             . 'and rendering for a page are different jobs, and Stemma only had the first.'],
            ['Layout as a constraint',
             '<code>grid</code>, <code>row</code>, <code>column</code> and <code>stack</code>, solved to '
             . 'coordinates. A view can now say <em>two columns, these four, 56 apart</em> instead of '
             . 'carrying the residue of somebody’s mouse. A later drag records an offset against the '
             . 'constraint rather than replacing it, so the arrangement survives being corrected. '
             . 'Authorable as text, or from the canvas selection.'],
            ['<code>stemma validate</code>',
             'Ids are strings and nothing checks them: a typo produces a missing node or a missing arrow, '
             . 'silently. This reports dangling endpoints, view members that do not exist, links with one '
             . 'end outside the view, and a warning past seven boxes. <code>--json</code> for something '
             . 'acting on the answer rather than reading it.'],
            ['A render manifest, and <code>--check</code>',
             'Every render writes a sibling manifest fingerprinting the model, the view, the layout and '
             . 'the profile it was drawn from. <code>--check</code> re-computes it and exits non-zero '
             . 'when a committed image has stopped matching its source — the drift guarantee, extended '
             . 'past the app window.'],
            ['True vector SVG',
             'The publication profile emits SVG primitives out of the same component tree the canvas '
             . 'renders, instead of wrapping serialised HTML in a <code>foreignObject</code>. The old '
             . 'export opened in Chrome and essentially nowhere else.'],
            ['<code>Concept</code> and <code>Relation</code>',
             'A box that asserts nothing about a system, and an arrow that claims nothing beyond its '
             . 'label. Drawing an argument previously meant declaring something like “what the repository '
             . 'can answer” as a bounded context — which compiles, validates, and is false.'],
            ['A published schema, and a protocol reference',
             '<code>stemma.layout.json</code> has a JSON Schema, and every sidecar now carries a pointer '
             . 'to it. The protocol document explains the whole chain to someone who will never open the '
             . 'app, with three worked examples that were built and rendered exactly as written.'],
            ['Diagnostics',
             'A leveled, structured record of what the engine did, as JSON lines under '
             . '<code>~/.stemma/logs/</code>, capped and rotated without being asked. Shareable bundles '
             . 'are redacted by default. There is no network sink and none can be configured: a log '
             . 'record contains element names, ownership and sometimes your source.'],
            ['The sidecar stopped dropping what it did not model',
             'Free-form shapes and edge dock handles were discarded on their way to disk, so both '
             . 'survived only until reload. Unknown sections now round-trip verbatim.', 'fix'],
            ['Node titles wrap',
             'They clipped at around thirty characters, which was quietly shaping what things could be '
             . 'called.', 'fix'],
            ['The draw.io export became usable',
             'It ignored the view and the layout and emitted the whole model on a grid in default pastel. '
             . 'It now honours both. Six further defects, found by auditing the render pipeline end to '
             . 'end, went with it.', 'fix'],
        ],
    ],
    [
        'date' => '2026-08-03',
        'slug' => 'open',
        'name' => 'In the open',
        'lead' => 'The repository, a licence, and two workspaces worth reading before writing your own.',
        'items' => [
            ['<code>github.com/batas2/Stemma</code>',
             'Source-available, with the engine and the round-trip fidelity suite in it.'],
            ['PolyForm Noncommercial 1.0.0',
             'Free for personal use, study, hobby projects, and for charities, educational institutions, '
             . 'public research bodies and government. Commercial use of any kind needs a licence.'],
            ['Aurora Rail',
             'The reference workspace: a fictional rail-ticketing platform with 66 elements, four saved '
             . 'views and three narrative books, exercising every part of the DSL.'],
            ['Stemma’s own architecture, as a Stemma workspace',
             'The tool modelling itself, which is the cheapest honest test of whether the vocabulary is '
             . 'sufficient.'],
        ],
    ],
    [
        'date' => '2026-08-01',
        'slug' => 'stemma',
        'name' => 'Stemma, and a workspace with no solution in it',
        'lead' => 'A rename, and the removal of the largest thing standing between somebody and their '
                . 'first model.',
        'items' => [
            ['Renamed from Verso',
             'A <i>stemma codicum</i> is the family tree philologists draw for a manuscript: every '
             . 'surviving copy traced back to one archetype. That is the shape of the thing.'],
            ['Model-only workspaces',
             'A folder holding <code>Architecture/</code> and nothing else opens with no MSBuild, no SDK, '
             . 'no restore and no NuGet. Until this, the first step was owning a .NET solution.'],
            ['From scratch',
             'Start a model by name; the empty canvas says what to do next instead of sitting there.'],
            ['A desktop shell, decided',
             'Photino, with a window flag and a renderer benchmark behind the choice.'],
        ],
    ],
    [
        'date' => '2026-06-10',
        'slug' => 'report',
        'name' => 'The report',
        'lead' => 'An answer to the question every modelling tool eventually meets: how do I show this to '
                . 'somebody who will never install it?',
        'items' => [
            ['A single-file interactive HTML report',
             'One file. It opens offline, with no server, no login and no seat. Audience modes (builder, '
             . 'stakeholder, reviewer), per-view layer toggles, element search, and element-anchored '
             . 'comments that export as a pack and import back into the workspace. Styled to match the '
             . 'canvas it came from.'],
            ['Inline edge editing',
             'A unified properties blade, sticky inspector tabs, and a floating direction toolbar on a '
             . 'selected relationship.'],
            ['Keyboard shortcuts',
             'Delete, rename, zoom, view switching, selection.'],
            ['Dependency links appeared on the module map',
             'They were being computed and not drawn. Default-coloured arrowheads were invisible against '
             . 'the canvas, too.', 'fix'],
        ],
    ],
    [
        'date' => '2026-06-08',
        'slug' => 'docking',
        'name' => 'Docking, routing, and the rail',
        'lead' => 'The month the canvas stopped feeling like a prototype.',
        'items' => [
            ['draw.io-style edge docking',
             'Relationships anchor to connection dots on a node and reconnect to another box.'],
            ['Four routing modes',
             'Curved, elbow, step and straight, with per-end markers and predefined relationship types '
             . 'that carry their own styling.'],
            ['The inspector became an icon rail',
             'One panel at a time — properties, appearance, text, lifecycle, ownership, custom '
             . 'properties, comments, layout — plus a full-screen rich editor for notes.'],
            ['Per-view layout modes',
             'Organic, hierarchical, by-type and custom, each tunable and remembered for the view it '
             . 'belongs to.'],
            ['<code>.doc/</code> became the single source of truth',
             'Product, architecture, UX and UI as four pillars, with the pre-refactor specs archived '
             . 'rather than left to rot beside them.'],
        ],
    ],
    [
        'date' => '2026-05-19',
        'slug' => 'books',
        'name' => 'Books, and a second adapter',
        'lead' => 'Modelling a system is half the job. This is the half about getting a room to agree.',
        'items' => [
            ['Story books',
             'Ordered, audience-tagged sequences of pages, each naming a view and carrying the narrative '
             . 'you would actually say out loud. Present mode drives the canvas from the page; the whole '
             . 'thing exports as a multi-page PDF.'],
            ['A YAML adapter that preserves trivia',
             'Comments and formatting survive a write, which is the same rule the C# writer follows and '
             . 'the reason either one can be trusted.'],
            ['Free-form shapes and a comments sidecar',
             'Ink that carries no architectural meaning, kept out of the model on purpose.'],
            ['Data-layer kinds, and two more built-in views',
             'A data model and a resource tree, alongside the module map and the dependency graph.'],
        ],
    ],
    [
        'date' => '2026-05-04',
        'slug' => 'first',
        'name' => 'The first workspace',
        'lead' => 'Five spikes in a day, answering one question: can the C# on disk be the model database, '
                . 'rather than an export of it?',
        'items' => [
            ['C# source as storage',
             'The model read off the syntax tree by name, and written back through Roslyn — the '
             . 'foundation everything since has rested on.'],
            ['The canvas',
             'A drag-and-drop palette, custom views stored as code, two auto-arrange algorithms, '
             . 'snapping, alignment and multi-select.'],
            ['The layout sidecar',
             'Positions and styling committed beside the model, so presentation never enters it.'],
            ['Lifecycle, undo, and a file watcher',
             'Plus a validation engine with fixtures, and export to PNG, SVG and draw.io.'],
        ],
    ],
];

$latest = $releases[0];
$first  = end($releases);

/* The releases again, as data. A changelog is a list of dated things and answer
   engines are asked dated questions about it — "what's new in Stemma", "when did
   X ship" — so the same array the page renders is emitted as an ItemList whose
   members carry their own anchor URL. Nothing here is a claim the visible page
   does not already make, which is the only condition under which markup is
   allowed to exist. */
$releaseList = [
    '@type' => 'ItemList',
    '@id' => $url . '#releases',
    'name' => 'Stemma releases',
    'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
    'numberOfItems' => count($releases),
    'itemListElement' => array_map(fn(int $i, array $r): array => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'item' => [
            '@type' => 'CreativeWork',
            '@id' => $url . '#' . $r['slug'],
            'url' => $url . '#' . $r['slug'],
            'name' => $r['name'],
            'abstract' => $r['lead'],
            'datePublished' => $r['date'],
            'inLanguage' => 'en',
            'author' => ['@id' => cfg('site_url') . '/#person'],
            'about' => ['@id' => $project . '#app'],
        ],
    ], array_keys($releases), $releases),
];

$extra = ld_script([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            '@id' => $url . '#page',
            'url' => $url,
            'name' => 'Stemma changelog — what shipped, and when',
            'description' => 'Every release of Stemma since 4 May 2026: the headless render pipeline, the '
                           . 'public repository, story books, the HTML report, and the first workspace.',
            'inLanguage' => 'en',
            'isPartOf' => ['@id' => cfg('site_url') . '/#website'],
            'about' => ['@id' => $project . '#app'],
            'author' => ['@id' => cfg('site_url') . '/#person'],
            'datePublished' => $first['date'],
            'dateModified' => $latest['date'],
            'mainEntity' => ['@id' => $url . '#releases'],
        ],
        $releaseList,
    ],
]) . "\n";
$extra .= ld_script(schema_breadcrumbs([
    'Field Notes' => cfg('site_url') . '/',
    'Stemma' => $project,
    'Changelog' => $url,
])) . "\n";
$extra .= '<meta property="og:image:width" content="1200">' . "\n"
        . '<meta property="og:image:height" content="630">' . "\n"
        . '<meta property="og:image:alt" content="Stemma changelog — every release since 4 May 2026.">' . "\n"
        . '<meta name="twitter:image:alt" content="Stemma changelog — every release since 4 May 2026.">' . "\n";
$extra .= '<link rel="alternate" type="text/markdown" href="' . e($url) . 'index.md" title="Markdown source">' . "\n";

$extra .= <<<'CSS'
<style>
/* ============================================================================
   Stemma changelog.
   Scoped to .sc-*. Inherits the site tokens and declares nothing it does not
   use, so this folder moves with /stemma/ as a unit.
   ========================================================================== */

.sc-page { --sc-fix: #b0790a; }
@media (prefers-color-scheme: dark) { .sc-page { --sc-fix: #e0aa3e; } }

/* ---- head ---------------------------------------------------------------- */
.sc-head { padding: 5rem 0 0; }
.sc-h1 {
  font-family: 'Inter', system-ui, sans-serif; font-weight: 700; color: var(--ink);
  font-size: clamp(2.4rem, 6vw, 3.8rem); letter-spacing: -0.045em; line-height: 1.0;
  margin: 1.1rem 0 1.2rem; text-wrap: balance;
}
.sc-sub { max-width: 40rem; font-size: clamp(1rem, 1.5vw, 1.14rem); color: var(--muted); line-height: 1.65; text-wrap: pretty; }
.sc-sub a { text-decoration: underline; text-underline-offset: 2px; }

/* ---- the fact strip ------------------------------------------------------- */
.sc-strip { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); margin-top: 3.2rem; }
.sc-strip dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
.sc-strip dl > div { padding: 1.5rem 1.6rem 1.5rem 1.8rem; border-left: 1px solid var(--line); }
.sc-strip dl > div:first-child { padding-left: 0; border-left: 0; }
.sc-strip dt { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.6rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.45rem; }
.sc-strip dd { font-size: 1rem; font-weight: 600; color: var(--ink); line-height: 1.35; }
.sc-strip dd span { display: block; font-weight: 400; font-size: 0.84rem; color: var(--muted); margin-top: 0.15rem; }

/* ---- the spine -----------------------------------------------------------
   Date in the gutter, release beside it. The rule down the left of the body is
   the spine; the blip is where a release joins it. */
.sc-log { padding: 4.5rem 0 2rem; }
.sc-rel { display: grid; grid-template-columns: 12.5rem minmax(0, 1fr); gap: 2.8rem; }
.sc-when { position: sticky; top: 84px; align-self: start; padding-top: 0.1rem; }
.sc-date {
  display: block; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.72rem;
  letter-spacing: 0.06em; color: var(--ink); font-weight: 600;
}
.sc-ago { display: block; font-size: 0.74rem; color: var(--faint); margin-top: 0.3rem; }
.sc-perma { display: block; margin-top: 0.7rem; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.62rem; letter-spacing: 0.1em; text-transform: uppercase; color: var(--faint); }
.sc-perma:hover { color: var(--accent-deep); }

.sc-body { border-left: 1px solid var(--line); padding: 0 0 4.5rem 2.6rem; position: relative; }
.sc-rel:last-child .sc-body { padding-bottom: 1rem; }
.sc-body::before {
  content: ""; position: absolute; left: -4.5px; top: 0.55rem;
  width: 8px; height: 8px; border-radius: 50%; background: var(--accent);
}
.sc-rel:not(:first-child) .sc-body::before { background: var(--faint); }

.sc-name { font-size: clamp(1.35rem, 2.6vw, 1.85rem); font-weight: 700; color: var(--ink); letter-spacing: -0.025em; line-height: 1.15; margin-bottom: 0.7rem; text-wrap: balance; }
.sc-lead { font-size: 1rem; color: var(--muted); line-height: 1.7; max-width: 44rem; text-wrap: pretty; }
.sc-lead + ul { margin-top: 1.9rem; }

.sc-items { list-style: none; display: flex; flex-direction: column; gap: 1.15rem; max-width: 46rem; }
.sc-items li { font-size: 0.94rem; line-height: 1.68; color: var(--muted); }
.sc-items b { color: var(--ink); font-weight: 600; }
.sc-items code { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.88em; color: var(--ink); }
.sc-items i { font-style: italic; }
.sc-items em { font-style: italic; color: var(--body); }
/* The repairs, marked. A changelog that hides them is a brochure. */
.sc-fix {
  font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 0.56rem; letter-spacing: 0.12em;
  text-transform: uppercase; font-weight: 600; color: var(--sc-fix); border: 1px solid currentColor;
  border-radius: 999px; padding: 0.25em 0.6em; margin-right: 0.55rem; vertical-align: 0.18em;
  white-space: nowrap;
}

/* ---- foot ---------------------------------------------------------------- */
.sc-foot { border-top: 1px solid var(--line); padding: 3rem 0 1rem; }
.sc-foot p { max-width: 44rem; font-size: 0.92rem; color: var(--muted); line-height: 1.7; }
.sc-foot p + p { margin-top: 1rem; }
.sc-foot a { text-decoration: underline; text-underline-offset: 2px; }
.sc-cta { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 2rem; }
.sc-cta a { text-decoration: none; }

/* ---- responsive ----------------------------------------------------------- */
@media (max-width: 860px) {
  .sc-rel { grid-template-columns: minmax(0, 1fr); gap: 0.9rem; }
  .sc-when { position: static; }
  .sc-perma { display: none; }
  .sc-body { border-left: 0; padding-left: 0; padding-bottom: 3.2rem; }
  .sc-body::before { display: none; }
  .sc-strip dl > div { padding: 1.1rem 0; border-left: 0; border-top: 1px solid var(--line); }
  .sc-strip dl > div:first-child { border-top: 0; }
}
@media (max-width: 700px) {
  .sc-head { padding-top: 3rem; }
  .sc-log { padding: 3rem 0 1rem; }
}
</style>
CSS;

// Google cuts around 155 characters, so the newest release has to survive the first clause.
echo page_head(
    'Stemma changelog — what shipped, and when',
    'Every Stemma release since 4 May 2026 — newest first: the headless render pipeline and CLI, the '
    . 'public repository, story books, and the single-file HTML report.',
    $url,
    $extra,
    cfg('site_url') . '/assets/img/stemma/og.jpg'
);

echo site_nav('stemma');

/** "3 months ago" — coarse on purpose; a changelog is read for order, not for arithmetic. */
function sc_ago(string $date): string
{
    $days = (int) floor((time() - (int) strtotime($date)) / 86400);
    if ($days <= 0)  return 'today';
    if ($days === 1) return 'yesterday';
    if ($days < 31)  return $days . ' days ago';
    $months = (int) round($days / 30.4);
    if ($months < 12) return $months === 1 ? 'a month ago' : $months . ' months ago';
    $years = (int) round($months / 12);
    return $years === 1 ? 'a year ago' : $years . ' years ago';
}
?>

<main class="sc-page">

  <section class="sc-head">
    <div class="wrap">
      <?= eyebrow('Stemma · changelog', true) ?>
      <h1 class="sc-h1">What shipped, and when.</h1>
      <p class="sc-sub">
        Stemma is a side project, built on evenings and weekends since 4 May 2026. This is the whole
        record — releases, not commits, and the repairs alongside the additions. The tool itself is
        described on the <a href="/stemma/">project page</a>; where it is going next is the
        <a href="/stemma/#roadmap">roadmap</a>.
      </p>

      <div class="sc-strip">
        <dl>
          <div><dt>Latest</dt><dd><?= e(date('j F Y', (int) strtotime($latest['date']))) ?><span><?= e($latest['name']) ?></span></dd></div>
          <div><dt>Releases</dt><dd><?= count($releases) ?><span>Since <?= e(date('j F Y', (int) strtotime($first['date']))) ?></span></dd></div>
          <div><dt>Built by</dt><dd>One person<span>Evenings and weekends</span></dd></div>
          <div><dt>Licence</dt><dd>PolyForm Noncommercial<span>Commercial use needs a licence</span></dd></div>
        </dl>
      </div>
    </div>
  </section>

  <section class="sc-log">
    <div class="wrap">
      <?php foreach ($releases as $r): ?>
      <article class="sc-rel" id="<?= e($r['slug']) ?>">
        <div class="sc-when">
          <time class="sc-date" datetime="<?= e($r['date']) ?>"><?= e(date('j F Y', (int) strtotime($r['date']))) ?></time>
          <span class="sc-ago"><?= e(sc_ago($r['date'])) ?></span>
          <a class="sc-perma" href="#<?= e($r['slug']) ?>">Link</a>
        </div>
        <div class="sc-body">
          <h2 class="sc-name"><?= e($r['name']) ?></h2>
          <p class="sc-lead"><?= e($r['lead']) ?></p>
          <ul class="sc-items">
            <?php foreach ($r['items'] as $it): ?>
            <li>
              <?php if (($it[2] ?? '') === 'fix'): ?><span class="sc-fix">fix</span><?php endif; ?>
              <b><?= $it[0] ?></b> — <?= $it[1] ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <div class="sc-foot">
    <div class="wrap">
      <p>
        Dates are when the work landed in the repository, not when it was announced — there is nothing
        here to announce it to. Anything described as shipped has a feature record and, where it touches
        the engine, round-trip fixtures behind it; that is the whole quality bar, stated plainly so you
        can discount it accordingly.
      </p>
      <p>
        Reading this with a machine? This page is also
        <a href="/stemma/changelog/index.md">plain markdown</a>, and the site's context file is
        <a href="/llms.txt">llms.txt</a>.
      </p>
      <p class="sc-cta">
        <a class="btn btn-primary" href="/stemma/">The project page <?= arrow_icon() ?></a>
        <a class="btn btn-ghost" href="<?= e($repo) ?>" rel="noopener">The repository</a>
      </p>
    </div>
  </div>

  <section class="sc-log" style="padding-top:0">
    <div class="wrap">
      <?= newsletter_box() ?>
    </div>
  </section>

</main>

<?= site_footer() ?>
