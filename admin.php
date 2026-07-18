<?php
/**
 * admin.php — private control room for the blog.
 *
 * Login: password checked against config 'admin_password_hash'
 * (php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;").
 * Empty hash = admin disabled, this page only shows setup instructions.
 *
 * What it does:
 *  - publishing pipeline: published / scheduled / draft posts with publish
 *    times ("**Status:** draft" and "**Publish:** YYYY-MM-DD HH:MM" lines in
 *    content/posts/*.md; content is authored locally and deployed — the admin
 *    intentionally does not edit post files)
 *  - "Rebuild site now": runs tools/build.php, which publishes anything whose
 *    time has come (the server cron does the same automatically)
 *  - analytics overview + link into stats.php (session unlocks it, no key needed)
 *  - comment moderation (runtime data on the server, safe to mutate here)
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/analytics.php';
require_once __DIR__ . '/lib/admin.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

/* ---------------- actions (PRG: every POST redirects) ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && admin_enabled()) {
    admin_session_start();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login') {
        $ok = admin_login((string) ($_POST['password'] ?? ''), $ip);
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
        header('Location: /admin.php#build');
        exit;
    }
    if ($action === 'delcomment') {
        $done = admin_delete_comment((string) ($_POST['slug'] ?? ''), (string) ($_POST['key'] ?? ''));
        $_SESSION['flash_c'] = $done ? 'Comment deleted.' : 'Comment not found (already gone?).';
        header('Location: /admin.php#comments');
        exit;
    }
    header('Location: /admin.php');
    exit;
}

$loggedIn = admin_session_active();
$flash = $flashC = '';
if ($loggedIn) {
    $flash  = (string) ($_SESSION['flash'] ?? '');
    $flashC = (string) ($_SESSION['flash_c'] ?? '');
    unset($_SESSION['flash'], $_SESSION['flash_c']);
}

/* ---------------- data for the dashboard ---------------- */

$pipeline = $comments = [];
$sum7 = null;
$statusCounts = ['published' => 0, 'scheduled' => 0, 'draft' => 0];
if ($loggedIn) {
    $comments = admin_all_comments();
    $pipeline = admin_pipeline(array_count_values(array_column($comments, 'slug')));
    foreach ($pipeline as $p) $statusCounts[$p['status']]++;
    if (is_file(an_db_path())) {
        try { $sum7 = an_summary(an_db(), gmdate('Y-m-d', time() - 7 * 86400)); } catch (Throwable) { $sum7 = null; }
    }
}
$lockout = admin_lockout_remaining($ip);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin · bfrackowiak.pl</title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/site.css">
<style>
.awrap { max-width: 1080px; margin: 2.5rem auto 4rem; padding-inline: 24px; }
.ahead { display: flex; flex-wrap: wrap; align-items: baseline; gap: 1rem; margin-bottom: 1.6rem; }
h1 { font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-right: auto; }
h2 { font-size: .95rem; font-weight: 600; color: var(--ink); margin: 2rem 0 .55rem; }
.toplinks a { font-size: .82rem; margin-left: 1rem; }
<?= dash_base_css() ?>
td.when { font-family: 'JetBrains Mono', monospace; font-size: .78rem; white-space: nowrap; }
.badge { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: .64rem; letter-spacing: .06em; text-transform: uppercase; padding: .14rem .55rem; border-radius: 999px; border: 1px solid var(--line); }
.b-published { color: #0a7a45; border-color: #0a7a4533; background: #0a7a450d; }
.b-scheduled { color: #b45309; border-color: #b4530933; background: #b453090d; }
.b-draft { color: var(--muted); }
.btn-line { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin: .8rem 0; }
button.act { font: inherit; font-weight: 600; font-size: .85rem; padding: .5rem 1.1rem; border-radius: 10px; border: 1px solid var(--line); background: var(--ink); color: var(--bg, #fff); cursor: pointer; }
button.small { padding: .2rem .7rem; font-size: .74rem; background: transparent; color: #b91c1c; border-color: #b91c1c44; }
pre.buildout { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 1rem; font-size: .76rem; overflow-x: auto; white-space: pre-wrap; }
.login { max-width: 380px; margin: 12vh auto; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 1.6rem; }
.login input[type=password] { width: 100%; font: inherit; padding: .6rem .8rem; margin: .8rem 0; border: 1px solid var(--line); border-radius: 10px; background: transparent; color: var(--ink); }
.form-err { color: #b91c1c; font-size: .85rem; margin: .4rem 0; }
.note { font-size: .78rem; color: var(--muted); margin: .5rem 0 0; }
.cmt-text { max-width: 46ch; overflow-wrap: anywhere; }
code { font-family: 'JetBrains Mono', monospace; font-size: .8em; background: var(--surface); border: 1px solid var(--line); border-radius: 6px; padding: .05rem .35rem; }
</style>
</head>
<body>
<div class="awrap">

<?php if (!admin_enabled()): ?>
  <div class="login">
    <?= eyebrow('Admin') ?>
    <h1 style="margin:.6rem 0">Not configured</h1>
    <p class="note">Set <code>admin_password_hash</code> in <code>config.php</code>:</p>
    <p class="note"><code>php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"</code></p>
  </div>

<?php elseif (!$loggedIn): ?>
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
  <div class="ahead">
    <?= eyebrow('Control room', true) ?>
  </div>
  <div class="ahead">
    <h1>bfrackowiak.pl · admin</h1>
    <span class="toplinks">
      <a href="/stats.php">analytics →</a>
      <a href="/blog/" target="_blank" rel="noopener">view site ↗</a>
    </span>
    <form method="POST" action="/admin.php" style="display:inline">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
      <button class="act small" type="submit" style="color:var(--muted);border-color:var(--line)">log out</button>
    </form>
  </div>

  <div class="cards">
    <div class="scard"><b><?= $statusCounts['published'] ?></b><span>Published</span></div>
    <div class="scard"><b><?= $statusCounts['scheduled'] ?></b><span>Scheduled</span></div>
    <div class="scard"><b><?= $statusCounts['draft'] ?></b><span>Drafts</span></div>
    <div class="scard"><b><?= $sum7 ? number_format($sum7['pv']) : '—' ?></b><span>Views · 7d</span></div>
    <div class="scard"><b><?= $sum7 ? number_format($sum7['uv']) : '—' ?></b><span>Visitors · 7d</span></div>
    <div class="scard"><b><?= $sum7 ? $sum7['live'] : '—' ?></b><span>On site now</span></div>
    <div class="scard"><b><?= count($comments) ?></b><span>Comments</span></div>
  </div>

  <h2 id="build">Publishing</h2>
  <div class="btn-line">
    <form method="POST" action="/admin.php">
      <input type="hidden" name="action" value="rebuild">
      <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
      <button class="act" type="submit">Rebuild site now</button>
    </form>
    <span class="note">last build: <?= admin_last_build_ts() ? date('Y-m-d H:i', admin_last_build_ts()) : 'never' ?>
      · publishes every scheduled post whose time has passed<?= $statusCounts['scheduled'] ? ' · cron does this automatically if configured (see README)' : '' ?></span>
  </div>
  <?php if ($flash !== ''): ?><pre class="buildout"><?= e($flash) ?></pre><?php endif; ?>

  <table>
    <tr><th>Status</th><th>Goes live</th><th>Post</th><th class="r">Read</th><th class="r">Comments</th><th>Tags</th></tr>
    <?php foreach ($pipeline as $p): ?>
    <tr>
      <td><span class="badge b-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
      <td class="when"><?= date('Y-m-d H:i', $p['publish_ts']) ?><?php if ($p['status'] === 'scheduled'): ?><br><span class="dim"><?= e(admin_countdown($p['publish_ts'])) ?></span><?php endif; ?></td>
      <td>
        <?php if ($p['status'] === 'published' && $p['built']): ?>
          <a href="<?= e(post_path($p['slug'])) ?>" target="_blank" rel="noopener"><?= e($p['title']) ?></a>
        <?php else: ?>
          <?= e($p['title']) ?><?= $p['status'] === 'published' && !$p['built'] ? ' <span class="dim">· not built yet — rebuild</span>' : '' ?>
        <?php endif; ?>
        <br><span class="dim" style="font-size:.78rem"><?= e($p['subtitle']) ?></span>
      </td>
      <td class="r n"><?= $p['minutes'] ?> min</td>
      <td class="r n"><?= $p['comments'] ?: '·' ?></td>
      <td class="dim" style="font-size:.76rem"><?= e(implode(', ', array_map('tag_slug', array_slice($p['tags'], 0, 3)))) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <p class="note">Scheduling lives in the markdown: a future filename date (goes live at 09:00), an explicit
    <code>**Publish:** YYYY-MM-DD HH:MM</code> line, or <code>**Status:** draft</code> to keep a post out entirely.
    Author locally, deploy — the server cron (or the button above) flips posts live on time.</p>

  <h2 id="comments">Comments</h2>
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
          <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
          <input type="hidden" name="slug" value="<?= e($c['slug']) ?>">
          <input type="hidden" name="key" value="<?= e($c['key']) ?>">
          <button class="act small" type="submit">delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <h2>System</h2>
  <table>
    <tr><td>Analytics database</td><td class="n"><?= is_file(an_db_path()) ? round(filesize(an_db_path()) / 1024) . ' KB' : 'not created yet' ?></td></tr>
    <tr><td>GeoIP database</td><td class="n"><?= geoip_db_age_days() === null ? 'missing — run php tools/geoip_update.php' : round(filesize(geoip_db_path()) / 1048576, 1) . ' MB · ' . geoip_db_age_days() . ' days old' ?></td></tr>
    <tr><td>Timezone</td><td class="n"><?= e((string) cfg('timezone', 'Europe/Warsaw')) ?></td></tr>
    <tr><td>PHP</td><td class="n"><?= PHP_VERSION ?></td></tr>
  </table>
<?php endif; ?>

</div>
</body>
</html>
