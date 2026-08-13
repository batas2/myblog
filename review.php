<?php
/**
 * review.php — read a post in its real typography, mark up sentences, fix small things in place.
 *
 * Admin only, and deliberately not a CMS. Two modes over one document:
 *
 *   read    the post as it will ship. Nothing is editable, nothing can be knocked out of place.
 *   review  every prose block becomes editable and any selection can carry a margin note.
 *
 * The split matters. Most visits are reading, and a page where a stray click starts an edit is a
 * page you stop trusting. You opt into changing the thing.
 *
 * Notes never render publicly. They exist to be pulled down by `deploy.sh --pull` and handed to an
 * agent, which resolves them and pushes the result back, so this page is one end of a round trip
 * rather than a place where writing happens.
 *
 * Publication is untouched on purpose: no status, schedule or publish control lives here.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/admin.php';
require_once __DIR__ . '/lib/review_blocks.php';
require_once __DIR__ . '/lib/review_store.php';

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

function rv_404(): never
{
    http_response_code(404);
    exit("Not found\n");
}

/** Every write answers JSON; the page never reloads to save something. */
function rv_json(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!admin_session_active()) rv_404();

$slug = (string) ($_GET['slug'] ?? $_POST['slug'] ?? '');
if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) rv_404();
$file = post_source_file($slug);
if ($file === null) rv_404();
$post = parse_post_file($file);
if ($post === null) rv_404();

/* ---------------- writes ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_check($_POST['csrf'] ?? null)) rv_json(['error' => 'csrf'], 403);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'block') {
        $err = rb_write_block($file, (string) ($_POST['id'] ?? ''), (string) ($_POST['md'] ?? ''), (string) ($_POST['hash'] ?? ''));
        if ($err !== '') rv_json(['error' => $err], $err === 'stale' ? 409 : 400);
        $b = rb_find(rb_load($file), (string) $_POST['id']);
        rv_json(['ok' => true, 'hash' => rb_hash($b['md'] ?? ''), 'md' => $b['md'] ?? '']);
    }

    if ($action === 'comment') {
        $note = rv_add($slug, [
            'block' => (string) ($_POST['block'] ?? ''),
            'section' => (string) ($_POST['section'] ?? ''),
            'quote' => (string) ($_POST['quote'] ?? ''),
            'prefix' => (string) ($_POST['prefix'] ?? ''),
            'suffix' => (string) ($_POST['suffix'] ?? ''),
        ], (string) ($_POST['body'] ?? ''));
        $note === null ? rv_json(['error' => 'empty'], 400) : rv_json(['ok' => true, 'comment' => $note]);
    }

    if ($action === 'comment_update') {
        $patch = [];
        if (isset($_POST['body'])) $patch['body'] = (string) $_POST['body'];
        if (isset($_POST['status'])) $patch['status'] = (string) $_POST['status'];
        rv_update($slug, (string) ($_POST['id'] ?? ''), $patch)
            ? rv_json(['ok' => true]) : rv_json(['error' => 'not found'], 404);
    }

    if ($action === 'comment_delete') {
        rv_delete($slug, (string) ($_POST['id'] ?? ''))
            ? rv_json(['ok' => true]) : rv_json(['error' => 'not found'], 404);
    }

    rv_json(['error' => 'unknown action'], 400);
}

/* ---------------- the page ---------------- */

$parsed = rb_load($file);
$comments = rv_reanchor(rv_load($slug)['comments'], $parsed);
$open = count(array_filter($comments, fn($c) => ($c['status'] ?? 'open') !== 'resolved' && $c['anchor'] !== null));
$orphans = array_values(array_filter($comments, fn($c) => $c['anchor'] === null));

/** The nearest preceding heading, so a note can say which section it came from. */
$sectionOf = [];
$cur = '';
foreach ($parsed['blocks'] as $b) {
    if ($b['type'] === 'h2') $cur = trim(preg_replace('/^#{2}\s+/', '', $b['md']) ?? '');
    $sectionOf[$b['id']] = $cur;
}

$mode = ($_GET['mode'] ?? 'read') === 'review' ? 'review' : 'read';
$statusLabel = ['draft' => 'draft', 'scheduled' => 'scheduled', 'published' => 'published'][$post['status']] ?? $post['status'];
$csrf = admin_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($post['title']) ?> · review</title>
<link rel="stylesheet" href="/assets/fonts.css">
<link rel="stylesheet" href="/assets/site.css">
<link rel="stylesheet" href="/assets/review.css">
</head>
<body class="rv-body" data-mode="<?= $mode ?>">

<header class="rv-bar">
  <a class="rv-back" href="/admin.php#posts">← admin</a>
  <span class="rv-title"><?= e($post['title']) ?></span>
  <span class="rv-chip rv-chip-<?= e($post['status']) ?>"><?= e($statusLabel) ?></span>

  <?php /* Named for what each one lets you do. "Read / Review" told you nothing about which was
           which, and the whole point of the split is that you opt into changing the thing. */ ?>
  <div class="rv-modes" role="group" aria-label="Mode">
    <a class="rv-mode<?= $mode === 'read' ? ' is-on' : '' ?>" href="?slug=<?= e($slug) ?>&amp;mode=read">
      <span aria-hidden="true">◱</span> Preview</a>
    <a class="rv-mode<?= $mode === 'review' ? ' is-on' : '' ?>" href="?slug=<?= e($slug) ?>&amp;mode=review">
      <span aria-hidden="true">✎</span> Comment &amp; edit</a>
  </div>

  <?php /* With more than a handful of notes the rail becomes a list you scroll past rather than
           work through, so it gets a filter and a way to step between them without the mouse. */ ?>
  <div class="rv-filter" id="rv-filter" role="group" aria-label="Which notes">
    <button type="button" data-f="open" class="is-on">Open <b id="rv-n-open">0</b></button>
    <button type="button" data-f="resolved">Done <b id="rv-n-done">0</b></button>
    <button type="button" data-f="all">All</button>
  </div>
  <button type="button" class="rv-jump" id="rv-jump" title="Next note (n)">↓ next</button>
  <span class="rv-save" id="rv-save" aria-live="polite"></span>
  <?php if ($post['status'] === 'published'): ?>
    <a class="rv-back" href="/blog/<?= e($slug) ?>/" target="_blank" rel="noopener">live ↗</a>
  <?php endif; ?>
</header>

<?php if ($mode === 'review'): ?>
<div class="rv-hint">
  <span><b>Select any text</b> to leave a note in the margin.</span>
  <span><b>Click a paragraph</b> to correct it. Edits save on their own.</span>
  <span class="rv-hint-k">n / p step through notes · ⌘Z undoes a block · ⌘B ⌘I · Esc leaves a block</span>
</div>
<?php else: ?>
<?php /* Preview is the default and does nothing but show the post, so it has to say out loud where
         the other half is. Landing here with no way forward is exactly how this got lost before. */ ?>
<div class="rv-cta">
  <div>
    <b>You are reading it.</b>
    <span>Nothing here can be changed by accident<?= $open ? ", and {$open} note" . ($open === 1 ? '' : 's') . " are waiting" : '' ?>.</span>
  </div>
  <a class="rv-cta-go" href="?slug=<?= e($slug) ?>&amp;mode=review">✎ Comment &amp; edit</a>
</div>
<?php endif; ?>

<main class="rv-main">
  <article class="rv-doc">
    <div class="article-head" style="margin-top:0;padding-inline:0;max-width:none">
      <h1><?= e($post['title']) ?></h1>
      <?php if ($post['subtitle'] !== ''): ?><p class="rv-sub"><em><?= e($post['subtitle']) ?></em></p><?php endif; ?>
    </div>

    <div class="prose rv-prose" style="margin-top:1.6rem;padding-inline:0;max-width:none">
      <?php foreach ($parsed['blocks'] as $b):
          $editable = $mode === 'review' && $b['editable'];
          $html = md_to_html(explode("\n", $b['md'])); ?>
        <div class="rv-b<?= $editable ? ' is-editable' : '' ?>"
             data-id="<?= e($b['id']) ?>"
             data-type="<?= e($b['type']) ?>"
             data-hash="<?= e(rb_hash($b['md'])) ?>"
             data-section="<?= e($sectionOf[$b['id']] ?? '') ?>"
             <?= $editable ? 'contenteditable="true" spellcheck="true"' : '' ?>><?= $html ?></div>
      <?php endforeach; ?>
    </div>
  </article>

  <aside class="rv-rail" id="rv-rail" aria-label="Notes"></aside>
</main>

<?php if ($orphans): ?>
<section class="rv-orphans">
  <h2>Notes whose text is gone (<?= count($orphans) ?>)</h2>
  <p>The sentence these were written against no longer exists. They still travel with the export.</p>
  <?php foreach ($orphans as $o): ?>
    <div class="rv-orphan" data-id="<?= e($o['id']) ?>">
      <blockquote><?= e($o['quote']) ?></blockquote>
      <p><?= e($o['body']) ?></p>
      <button type="button" class="rv-del" data-id="<?= e($o['id']) ?>">delete</button>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div class="rv-tip" id="rv-tip" hidden><button type="button" id="rv-tip-btn">Note</button></div>

<script id="rv-data" type="application/json"><?= json_encode([
    'slug' => $slug,
    'csrf' => $csrf,
    'mode' => $mode,
    'comments' => array_values(array_filter($comments, fn($c) => $c['anchor'] !== null)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="/assets/review.js"></script>
</body>
</html>
