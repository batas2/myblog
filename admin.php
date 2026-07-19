<?php
/**
 * admin.php — private control room for the blog.
 *
 * Login: password checked against config 'admin_password_hash'
 * (php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;").
 * Empty hash = admin disabled, this page only shows setup instructions.
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
 *  - analytics overview + link into stats.php (session unlocks it, no key)
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
    header('Location: /admin.php');
    exit;
}

$loggedIn = admin_session_active();
$flash = $flashC = '';
if ($loggedIn) {
    an_optout_cookie(true);   // sliding 1-year refresh: admin visits keep this browser out of the stats
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
    // queue first: scheduled (soonest up top), then drafts, then published (newest first)
    $rank = ['scheduled' => 0, 'draft' => 1, 'published' => 2];
    usort($pipeline, fn($a, $b) => [$rank[$a['status']], $a['status'] === 'scheduled' ? $a['publish_ts'] : -$a['publish_ts']]
                               <=> [$rank[$b['status']], $b['status'] === 'scheduled' ? $b['publish_ts'] : -$b['publish_ts']]);
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
<link rel="stylesheet" href="/assets/fonts.css">
<link rel="stylesheet" href="/assets/site.css">
<style>
<?= dash_base_css() ?>
.awrap { max-width: 1840px; margin: 2rem auto 4rem; padding-inline: 32px; }
.ahead { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem 1.4rem; margin-bottom: .4rem; }
h1 { font-size: 1.7rem; font-weight: 800; color: var(--ink); margin-right: auto; letter-spacing: -.01em; }
h2 { font-size: 1.05rem; font-weight: 700; color: var(--ink); margin: 2.4rem 0 .8rem; display: flex; align-items: baseline; gap: .7rem; }
h2 .cnt { font-family: 'JetBrains Mono', monospace; font-size: .72rem; color: var(--muted); font-weight: 400; }
.toplinks a { font-size: .85rem; margin-left: 1.1rem; }
td.when { font-family: 'JetBrains Mono', monospace; font-size: .78rem; white-space: nowrap; }
.badge { display: inline-block; font-family: 'JetBrains Mono', monospace; font-size: .66rem; letter-spacing: .06em; text-transform: uppercase; padding: .2rem .6rem; border-radius: 999px; border: 1px solid var(--line); }
.b-published { color: #0a7a45; border-color: #0a7a4540; background: #0a7a4512; }
.b-scheduled { color: #b45309; border-color: #b4530940; background: #b4530912; }
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
  <?php $csrf = e(admin_csrf_token()); ?>
  <div class="ahead" style="margin-top:.4rem">
    <?= eyebrow('Control room', true) ?>
  </div>
  <div class="ahead">
    <h1>bfrackowiak.pl · admin</h1>
    <span class="toplinks">
      <a href="/stats.php">analytics →</a>
      <a href="/blog/" target="_blank" rel="noopener">view site ↗</a>
    </span>
    <form method="POST" action="/admin.php">
      <input type="hidden" name="action" value="logout">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button class="mini hold" type="submit">log out</button>
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

  <h2 id="pipeline">Publishing pipeline <span class="cnt"><?= count($pipeline) ?> posts</span></h2>
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
    <tr><th>Status</th><th>Goes live</th><th>Post</th><th class="r">Read</th><th class="r">Comments</th><th>Change status</th></tr>
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

  <div class="two-zone">
    <div>
      <h2 id="comments">Comments <span class="cnt"><?= count($comments) ?></span></h2>
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
    </div>

    <div>
      <h2>System</h2>
      <table>
        <tr><td>Analytics database</td><td class="n r"><?= is_file(an_db_path()) ? round(filesize(an_db_path()) / 1024) . ' KB' : 'not created yet' ?></td></tr>
        <tr><td>GeoIP database</td><td class="n r"><?= geoip_db_age_days() === null ? 'missing — run php tools/geoip_update.php' : round(filesize(geoip_db_path()) / 1048576, 1) . ' MB · ' . geoip_db_age_days() . 'd old' ?></td></tr>
        <tr><td>Timezone</td><td class="n r"><?= e((string) cfg('timezone', 'Europe/Warsaw')) ?></td></tr>
        <tr><td>PHP</td><td class="n r"><?= PHP_VERSION ?></td></tr>
        <tr><td>Last build</td><td class="n r"><?= admin_last_build_ts() ? date('Y-m-d H:i', admin_last_build_ts()) : 'never' ?></td></tr>
      </table>
    </div>
  </div>
<?php endif; ?>

</div>
</body>
</html>
