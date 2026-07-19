<?php
/**
 * n.php — self-hosted newsletter endpoint (dynamic island, like comments.php).
 *
 *   POST n.php  {email, website(honeypot)}  → pending + confirmation mail → "check your inbox"
 *   GET  n.php?confirm=<token>              → double opt-in complete
 *   GET  n.php?u=<token>                    → one-click unsubscribe
 *   POST n.php?u=<token>                    → RFC 8058 one-click (mail clients' List-Unsubscribe button)
 *
 * Responses are deliberately neutral — the endpoint never reveals whether an
 * address is on the list (no enumeration). No IP, no user agent is stored.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/components.php';
require_once __DIR__ . '/lib/page.php';
require_once __DIR__ . '/lib/newsletter.php';

const NL_MIN_GAP = 30;   // seconds between subscribe attempts per IP

/** Small branded status page. */
function nl_page(string $title, string $heading, string $body, string $cta = ''): void
{
    echo page_head($title . ' · bfrackowiak.pl', $body, cfg('site_url') . '/n.php');
    echo site_nav();
    echo '<main class="wrap" style="padding:7rem 24px 4rem;max-width:44rem">';
    echo eyebrow('The signal');
    echo '<h1 style="font-size:clamp(1.9rem,4vw,2.6rem);margin:1rem 0 0.9rem">' . $heading . '</h1>';
    echo '<p style="color:var(--muted);font-size:1.02rem;line-height:1.7">' . $body . '</p>';
    echo $cta !== '' ? '<p style="margin-top:1.8rem">' . $cta . '</p>' : '';
    echo '</main>';
    echo site_footer();
    exit;
}

$db = nl_db();
$uTok = (string) ($_GET['u'] ?? '');
$cTok = (string) ($_GET['confirm'] ?? '');

/* ---- one-click unsubscribe (GET link, or RFC 8058 POST from the mail client) ---- */
if ($uTok !== '') {
    nl_unsubscribe($db, $uTok);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(200); exit('OK'); }
    nl_page(
        'Unsubscribed',
        'Done. You\'re out.',
        'That address will not get another email. If this was a slip, the form on any page adds you back in ten seconds.',
        '<a class="btn btn-ghost" href="/blog/">Keep reading anyway &rarr;</a>'
    );
}

/* ---- double opt-in confirmation ---- */
if ($cTok !== '') {
    $ok = nl_confirm($db, $cTok);
    nl_page(
        $ok ? 'Subscription confirmed' : 'Link expired',
        $ok ? 'You\'re in.' : 'That link didn\'t work.',
        $ok
            ? 'One field note lands in your inbox most Mondays — architecture, the people around it, and the corporate machine. Every issue has a one-click unsubscribe.'
            : 'The confirmation link is invalid or was already used. Subscribe again from any page and a fresh one will arrive.',
        $ok ? '<a class="btn btn-primary" href="/blog/start/">Start with the best essays ' . arrow_icon() . '</a>'
            : '<a class="btn btn-ghost" href="/#newsletter">Back to the form &rarr;</a>'
    );
}

/* ---- subscribe ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // honeypot: pretend success, store nothing
    if (($_POST['website'] ?? '') !== '') {
        nl_page('Almost there', 'Check your inbox.', 'A confirmation email is on its way. Click the link inside and you\'re in.');
    }

    $rl = throttle_path('nl_', 'bfrackowiak-newsletter', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (is_file($rl) && time() - (int) filemtime($rl) < NL_MIN_GAP) {
        nl_page('Slow down', 'One at a time.', 'That was quick. Give it half a minute and try again.');
    }

    $token = nl_subscribe($db, (string) ($_POST['email'] ?? ''));
    if ($token === null) {
        nl_page('Invalid address', 'That address didn\'t parse.', 'Check the email address and try again from any page on the site.',
            '<a class="btn btn-ghost" href="/#newsletter">Back to the form &rarr;</a>');
    }
    touch($rl);

    // pending → (re)send the confirmation; already-confirmed → quietly do nothing.
    $status = $db->prepare('SELECT status FROM subscribers WHERE token = ?');
    $status->execute([$token]);
    if ($status->fetchColumn() === 'pending') {
        nl_send_confirmation(mb_strtolower(trim((string) $_POST['email'])), $token);
    }
    nl_page('Almost there', 'Check your inbox.',
        'A confirmation email is on its way (look in spam the first time). Click the link inside and you\'re in — one essay most Mondays, unsubscribe any time with one click.');
}

/* plain GET with no token: nothing to see */
header('Location: /#newsletter');
