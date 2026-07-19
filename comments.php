<?php
/**
 * comments.php — file-based commenting, no database.
 *
 * Two modes:
 *  1. POST target (form action="/comments.php") — validates, appends the comment
 *     to content/comments/<slug>.json, redirects back to the post.
 *  2. Included from a generated post page with $COMMENT_SLUG set — renders the
 *     thread + form. This is the only dynamic part of an otherwise static page.
 *
 * Identity: commenters identify with a valid email address; it is stored in the
 * JSON but rendered publicly in masked form (j***@example.com).
 *
 * Spam defense, layered:
 *  - stateless math captcha — challenge is HMAC-signed into the form, verified on POST
 *  - honeypot field ("website") that humans never see
 *  - time trap (form must be 4s–24h old)
 *  - per-IP rate limit (hashed IP, transient file)
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/lib/blog.php';
require_once __DIR__ . '/lib/comment_store.php';
require_once __DIR__ . '/lib/captcha.php';

if (!defined('C_MAX_TEXT')) {
    define('C_MAX_TEXT', 2000);
    define('C_MIN_GAP', 15);   // seconds between posts from one IP
}

/** HMAC key for captcha tokens (config: comments_secret); rotation only voids in-flight forms. */
function c_secret(): string { return (string) cfg('comments_secret'); }

function c_valid_slug($slug): bool
{
    if (!is_string($slug) || !preg_match('/^[a-z0-9-]{3,80}$/', $slug)) return false;
    // the generated page is the source of truth for "public right now":
    // comments stay in lockstep with what visitors can actually see
    // (two stat calls on the hot path — tag archives have no index.md)
    $dir = APP_ROOT . '/blog/' . $slug;
    if (is_file($dir . '/index.php') && is_file($dir . '/index.md')) return true;
    // fallback for a not-yet-built site (fresh clone, tests before first build)
    return post_is_public($slug);
}

function c_load(string $slug): array
{
    return c_store_load($slug);
}

/** j***@example.com — identity without handing addresses to harvesters */
function c_mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($local === '' || $domain === '') return 'anonymous';
    return mb_substr($local, 0, 1) . '***@' . $domain;
}

/** stateless captcha: [a, b, signed token valid 24h] */
function c_challenge(): array
{
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $exp = time() + 86400;
    $payload = "{$a}|{$b}|{$exp}";
    $token = base64_encode($payload) . '.' . hash_hmac('sha256', $payload, c_secret());
    return [$a, $b, $token];
}

function c_verify_challenge(string $token, string $answer): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return false;
    $payload = base64_decode($parts[0], true);
    if ($payload === false || !hash_equals(hash_hmac('sha256', $payload, c_secret()), $parts[1])) return false;
    [$a, $b, $exp] = array_pad(explode('|', $payload), 3, 0);
    if ((int) $exp < time()) return false;
    return trim($answer) !== '' && (int) trim($answer) === (int) $a + (int) $b;
}

/* ---------------- mode 1: handle submission ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c_submit']) && !isset($COMMENT_SLUG)) {
    $slug = $_POST['c_slug'] ?? '';
    if (!c_valid_slug($slug)) { header('Location: /blog/'); exit; }
    $back = "/blog/{$slug}/";

    $email  = trim((string) ($_POST['c_email'] ?? ''));
    $text   = trim((string) ($_POST['c_text'] ?? ''));
    $trap   = (string) ($_POST['c_website'] ?? '');   // honeypot — humans never see it
    $ts     = (int) ($_POST['c_ts'] ?? 0);
    $age    = time() - $ts;
    $chal   = (string) ($_POST['c_chal'] ?? '');
    $answer = (string) ($_POST['c_answer'] ?? '');

    // bots: pretend success, store nothing
    if ($trap !== '' || $age < 4 || $age > 86400) { header("Location: {$back}#comments"); exit; }

    // Turnstile when configured, the stateless math captcha otherwise
    if (turnstile_enabled()) {
        if (!turnstile_verify_post()) { header("Location: {$back}?cerr=captcha#comment-form"); exit; }
    } elseif (!c_verify_challenge($chal, $answer)) {
        header("Location: {$back}?cerr=captcha#comment-form"); exit;
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) { header("Location: {$back}?cerr=email#comment-form"); exit; }
    if ($text === '') { header("Location: {$back}?cerr=empty#comment-form"); exit; }
    if (mb_strlen($text) > C_MAX_TEXT || mb_strlen($email) > 254) { header("Location: {$back}?cerr=long#comment-form"); exit; }

    $rl = throttle_path('c_rl_', 'bfrackowiak-comments', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (is_file($rl) && time() - (int) filemtime($rl) < C_MIN_GAP) { header("Location: {$back}?cerr=slow#comment-form"); exit; }

    c_store_mutate($slug, fn(array $list): array => [...$list, [
        'email' => mb_substr(strip_tags($email), 0, 254),
        'text'  => mb_substr(strip_tags($text), 0, C_MAX_TEXT),
        'date'  => date('Y-m-d H:i'),
    ]]);
    touch($rl);
    header("Location: {$back}#comments");
    exit;
}

/* direct GET to /comments.php — nothing to see here (CLI include: just expose functions) */
if (!isset($COMMENT_SLUG)) {
    if (PHP_SAPI !== 'cli') { header('Location: /blog/'); exit; }
    return;
}

/* ---------------- mode 2: render thread + form ---------------- */

if (!c_valid_slug($COMMENT_SLUG)) return;

$c_list = c_load($COMMENT_SLUG);
$c_n    = count($c_list);
$c_err  = $_GET['cerr'] ?? '';
$c_msgs = [
    'empty'   => 'Please write a comment.',
    'email'   => 'A valid email address is required. It will be shown masked.',
    'captcha' => 'The spam-check answer was wrong. There is a new question below, try again.',
    'long'    => 'That comment is a bit too long. The limit is 2000 characters.',
    'slow'    => 'You are commenting faster than I can read. Give it a few seconds.',
];
[$c_a, $c_b, $c_token] = c_challenge();
?>
<section class="comments" id="comments">
  <div class="comments-head">
    <span class="eyebrow"><span class="blip"></span>Discussion</span>
    <h2><?= $c_n === 0 ? 'Comments' : 'Comments (' . $c_n . ')' ?></h2>
  </div>

  <?php if ($c_n === 0): ?>
    <p class="comments-empty">No comments yet. The floor is yours.</p>
  <?php else: ?>
    <ol class="comment-list">
      <?php foreach ($c_list as $c): ?>
      <li class="comment">
        <div class="comment-meta">
          <span class="comment-name"><?= e(c_mask_email($c['email'] ?? ($c['name'] ?? ''))) ?></span>
          <span class="comment-date"><?= e($c['date'] ?? '') ?></span>
        </div>
        <p><?= nl2br(e($c['text'] ?? '')) ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <div class="comment-form" id="comment-form">
    <h3>Leave a comment</h3>
    <?php if (isset($c_msgs[$c_err])): ?>
      <div class="form-err"><?= e($c_msgs[$c_err]) ?></div>
    <?php endif; ?>
    <form method="POST" action="/comments.php">
      <input type="hidden" name="c_slug" value="<?= e($COMMENT_SLUG) ?>">
      <input type="hidden" name="c_ts" value="<?= time() ?>">
      <input type="hidden" name="c_chal" value="<?= e($c_token) ?>">
      <p class="c-website-row" aria-hidden="true"><label>Website<input type="text" name="c_website" tabindex="-1" autocomplete="off"></label></p>
      <?php if (turnstile_enabled()): ?>
      <div class="field">
        <label for="c_email">Email</label>
        <input type="email" id="c_email" name="c_email" required maxlength="254" placeholder="you@example.com" autocomplete="email">
      </div>
      <div class="field">
        <label>Spam check</label>
        <?= turnstile_widget('comment') ?>
      </div>
      <?php else: ?>
      <div class="two-col">
        <div class="field">
          <label for="c_email">Email</label>
          <input type="email" id="c_email" name="c_email" required maxlength="254" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="field">
          <label for="c_answer">Spam check: what is <?= $c_a ?> + <?= $c_b ?>?</label>
          <input type="text" id="c_answer" name="c_answer" required inputmode="numeric" pattern="[0-9]*" maxlength="3" placeholder="<?= $c_a ?> + <?= $c_b ?> = …" autocomplete="off">
        </div>
      </div>
      <?php endif; ?>
      <div class="field">
        <label for="c_text">Comment</label>
        <textarea id="c_text" name="c_text" required maxlength="2000" placeholder="Agree, disagree, or bring a war story of your own…"></textarea>
      </div>
      <button class="submit" type="submit" name="c_submit" value="1">Post comment</button>
      <p class="comment-note">Your email is required but published only in masked form (j***@example.com). Nothing else is stored.</p>
    </form>
  </div>
  <?php if (turnstile_enabled()) echo turnstile_script(); ?>
</section>
