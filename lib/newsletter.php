<?php
/**
 * lib/newsletter.php — self-hosted newsletter, no external service.
 *
 * Storage: content/newsletter.db (SQLite, WAL — same pattern as analytics).
 *   subscribers(email UNIQUE, status pending|confirmed, token, created_ts, confirmed_ts)
 *   sends(slug PK, sent_ts, recipients, note)   ← idempotency ledger per essay
 *
 * Flow: POST /n.php (subscribe) → confirmation mail (double opt-in) →
 * GET /n.php?confirm=<token> → confirmed. One-click out: /n.php?u=<token>
 * (GET and RFC 8058 POST), also sent as a List-Unsubscribe header.
 *
 * Sending: tools/newsletter_send.php (cron, after build). First ever run
 * seeds the ledger with every already-published essay WITHOUT mailing, so
 * the archive is never blasted at subscribers; afterwards each newly
 * published essay goes out exactly once.
 *
 * Privacy: the address and two timestamps — no IP, no user agent, nothing else.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function nl_db_path(): string
{
    return CONTENT_DIR . '/newsletter.db';
}

function nl_db(?string $path = null): PDO
{
    $db = new PDO('sqlite:' . ($path ?? nl_db_path()), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA busy_timeout=3000');
    $db->exec("CREATE TABLE IF NOT EXISTS subscribers (
        id INTEGER PRIMARY KEY,
        email TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'pending',
        token TEXT NOT NULL UNIQUE,
        created_ts INTEGER NOT NULL,
        confirmed_ts INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS sends (
        slug TEXT PRIMARY KEY,
        sent_ts INTEGER NOT NULL,
        recipients INTEGER NOT NULL,
        note TEXT NOT NULL DEFAULT ''
    )");
    return $db;
}

/**
 * Register (or re-register) an address as pending and return its token —
 * null when the address is invalid. Repeat calls are idempotent: an already
 * confirmed subscriber keeps status and token (no downgrade, no dupes).
 */
function nl_subscribe(PDO $db, string $email): ?string
{
    $email = mb_strtolower(trim($email));
    if (mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) return null;

    $row = $db->prepare('SELECT token FROM subscribers WHERE email = ?');
    $row->execute([$email]);
    if ($token = $row->fetchColumn()) return (string) $token;

    // ON CONFLICT: two concurrent first-time POSTs must not 500 on UNIQUE(email)
    $token = bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO subscribers (email, status, token, created_ts) VALUES (?, 'pending', ?, ?)
                  ON CONFLICT(email) DO NOTHING")
       ->execute([$email, $token, time()]);
    $row->execute([$email]);   // read whichever token actually landed
    return (string) $row->fetchColumn();
}

/** True when the token matched a subscriber (now confirmed). */
function nl_confirm(PDO $db, string $token): bool
{
    if (!preg_match('/^[0-9a-f]{32}$/', $token)) return false;
    $st = $db->prepare("UPDATE subscribers SET status = 'confirmed', confirmed_ts = COALESCE(confirmed_ts, ?) WHERE token = ?");
    $st->execute([time(), $token]);
    return $st->rowCount() > 0;
}

/** One-click unsubscribe: the row is deleted, not flagged. */
function nl_unsubscribe(PDO $db, string $token): bool
{
    if (!preg_match('/^[0-9a-f]{32}$/', $token)) return false;
    $st = $db->prepare('DELETE FROM subscribers WHERE token = ?');
    $st->execute([$token]);
    return $st->rowCount() > 0;
}

/** @return array<array{email: string, token: string}> */
function nl_confirmed(PDO $db): array
{
    return $db->query("SELECT email, token FROM subscribers WHERE status = 'confirmed' ORDER BY id")
              ->fetchAll(PDO::FETCH_ASSOC);
}

function nl_counts(PDO $db): array
{
    $c = ['confirmed' => 0, 'pending' => 0];
    foreach ($db->query('SELECT status, COUNT(*) n FROM subscribers GROUP BY status') as $r) {
        $c[$r['status']] = (int) $r['n'];
    }
    return $c;
}

/* ---------------- mail assembly ---------------- */

/** Absolute URLs for a mail client: /assets/… and /blog/… have no base there. */
function nl_absolutize(string $html): string
{
    $site = (string) cfg('site_url');
    return str_replace(['href="/', 'src="/'], ['href="' . $site . '/', 'src="' . $site . '/'], $html);
}

/**
 * Compose one essay as [subject, html, text]. The unsubscribe link is
 * per-recipient — substitute %%UNSUB%% before sending.
 */
function nl_compose(array $p): array
{
    $site = (string) cfg('site_url');
    $url = $site . '/blog/' . $p['slug'] . '/';
    $subject = $p['title'];
    $body = nl_absolutize($p['html']);
    $sub = e($p['subtitle']);

    $title = e($p['title']);
    $html = <<<HTML
<!doctype html>
<html><body style="margin:0;padding:0;background:#fbfbfd">
<div style="max-width:640px;margin:0 auto;padding:28px 20px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2a2a33;line-height:1.65">
  <p style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#8a8a96;margin:0 0 18px">Field Notes · bfrackowiak.pl</p>
  <h1 style="font-size:26px;line-height:1.25;color:#17171e;margin:0 0 6px">{$title}</h1>
  <p style="font-size:15px;color:#6b6b77;margin:0 0 22px"><em>{$sub}</em></p>
  {$body}
  <p style="margin:28px 0 0"><a href="{$url}" style="color:#4f52e5">Read and comment on the site &rarr;</a></p>
  <hr style="border:none;border-top:1px solid #e4e4ea;margin:30px 0 14px">
  <p style="font-size:12px;color:#8a8a96;margin:0">
    You get one field note like this most Mondays because you subscribed at bfrackowiak.pl.<br>
    <a href="%%UNSUB%%" style="color:#8a8a96">Unsubscribe with one click</a> — no questions, no retention screen.
  </p>
</div>
</body></html>
HTML;

    $text = $p['title'] . "\n" . $p['subtitle'] . "\n\n"
          . "Read it: {$url}\n\n"
          . "—\nOne field note most Mondays. Unsubscribe: %%UNSUB%%\n";

    return [$subject, $html, $text];
}

/* ---------------- sending ---------------- */

/**
 * Mail every published-but-unsent essay to the confirmed list.
 * First run on an empty ledger only seeds it (nothing is mailed).
 * $dryRun prints what would happen and touches nothing.
 * Returns human-readable log lines.
 *
 * Concurrency: an exclusive lock on content/newsletter.lock makes overlapping
 * cron runs a no-op, and each slug is claimed in the ledger BEFORE its send
 * loop — a crash mid-list can lose at most that one run's remaining sends,
 * never double-mail people who already got the essay. A run where nobody
 * could be mailed (SMTP down) releases the claim so the next run retries.
 *
 * Caveat: the ledger is keyed by slug — renaming an already-sent essay makes
 * it look new and re-mails the list. Rename in sends first:
 *   UPDATE sends SET slug='new-name' WHERE slug='old-name';
 */
function nl_send_new(array $posts, bool $dryRun = false, ?PDO $db = null): array
{
    $db ??= nl_db();
    $out = [];

    $lock = @fopen(CONTENT_DIR . '/newsletter.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) fclose($lock);
        return ['another send is in progress — skipping'];
    }

    try {
    $ledgerEmpty = ((int) $db->query('SELECT COUNT(*) FROM sends')->fetchColumn()) === 0;
    if ($ledgerEmpty) {
        foreach ($posts as $p) {
            if (!$dryRun) {
                $db->prepare("INSERT OR IGNORE INTO sends (slug, sent_ts, recipients, note) VALUES (?, ?, 0, 'baseline')")
                   ->execute([$p['slug'], time()]);
            }
        }
        $out[] = 'baseline: seeded ' . count($posts) . ' already-published essays, nothing mailed';
        return $out;
    }

    $sent = array_column($db->query('SELECT slug FROM sends')->fetchAll(PDO::FETCH_ASSOC), 'slug');
    $fresh = array_values(array_filter($posts, fn($p) => !in_array($p['slug'], $sent, true)));
    if (!$fresh) { $out[] = 'nothing new to send'; return $out; }

    $subs = nl_confirmed($db);
    usort($fresh, fn($a, $b) => $a['publish_ts'] <=> $b['publish_ts']);

    foreach ($fresh as $p) {
        if ($dryRun) {
            $out[] = 'would send "' . $p['slug'] . '" to ' . count($subs) . ' subscribers';
            continue;
        }
        // claim before mailing — a crash mid-loop must never re-blast the list
        $db->prepare("INSERT OR IGNORE INTO sends (slug, sent_ts, recipients, note) VALUES (?, ?, 0, 'sending')")
           ->execute([$p['slug'], time()]);
        $ok = 0;
        [$subject, $html, $text] = nl_compose($p);
        foreach ($subs as $s) {
            $unsub = cfg('site_url') . '/n.php?u=' . $s['token'];
            if (nl_smtp_send($s['email'], $subject, str_replace('%%UNSUB%%', $unsub, $html), str_replace('%%UNSUB%%', $unsub, $text), $unsub)) {
                $ok++;
            } else {
                $out[] = 'FAIL ' . $p['slug'] . ' → ' . $s['email'];
            }
            usleep(250000);   // gentle on the shared host's rate limits
        }
        if ($ok === 0 && $subs) {
            // nobody was reachable (SMTP outage): release the claim, retry next run
            $db->prepare("DELETE FROM sends WHERE slug = ? AND note = 'sending'")->execute([$p['slug']]);
            $out[] = 'send failed for "' . $p['slug'] . '" — released for retry next run';
            continue;
        }
        $db->prepare("UPDATE sends SET recipients = ?, note = '' WHERE slug = ?")->execute([$ok, $p['slug']]);
        $out[] = 'sent "' . $p['slug'] . '" to ' . $ok . '/' . count($subs) . ' subscribers';
    }
    return $out;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Mail is configured at all? Empty SMTP password = disabled (tests, fresh clones). */
function nl_mail_enabled(): bool
{
    return ((array) cfg('smtp', []))['pass'] !== '';
}

/** One SMTP delivery via PHPMailer; List-Unsubscribe headers included. */
function nl_smtp_send(string $to, string $subject, string $html, string $text, string $unsubUrl): bool
{
    if (!nl_mail_enabled()) return false;
    $smtp = (array) cfg('smtp', []);

    require_once APP_ROOT . '/vendor/phpmailer/Exception.php';
    require_once APP_ROOT . '/vendor/phpmailer/PHPMailer.php';
    require_once APP_ROOT . '/vendor/phpmailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = (string) $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) $smtp['user'];
        $mail->Password   = (string) $smtp['pass'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($smtp['port'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom((string) $smtp['from'], 'Bartosz Frąckowiak · Field Notes');
        $mail->addReplyTo('kontakt@bfrackowiak.pl', 'Bartosz Frąckowiak');
        $mail->addAddress($to);
        $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubUrl . '>');
        $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** The double-opt-in confirmation mail. */
function nl_send_confirmation(string $email, string $token): bool
{
    $link = cfg('site_url') . '/n.php?confirm=' . $token;
    $unsub = cfg('site_url') . '/n.php?u=' . $token;
    $html = <<<HTML
<!doctype html>
<html><body style="margin:0;padding:0;background:#fbfbfd">
<div style="max-width:560px;margin:0 auto;padding:28px 20px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2a2a33;line-height:1.65">
  <p style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#8a8a96;margin:0 0 18px">Field Notes · bfrackowiak.pl</p>
  <h1 style="font-size:22px;color:#17171e;margin:0 0 12px">One click and you're in</h1>
  <p style="margin:0 0 20px">You asked for one field note most Mondays — essays on software architecture, the people around it, and the corporate machine. Confirm it was really you:</p>
  <p style="margin:0 0 24px"><a href="{$link}" style="display:inline-block;background:#17171e;color:#fbfbfd;padding:11px 20px;border-radius:8px;text-decoration:none">Confirm subscription</a></p>
  <p style="font-size:13px;color:#8a8a96;margin:0">Didn't sign up? Ignore this email and nothing will ever arrive again.</p>
</div>
</body></html>
HTML;
    $text = "Confirm your Field Notes subscription (one essay, most Mondays):\n{$link}\n\nDidn't sign up? Ignore this email.\n";
    return nl_smtp_send($email, 'Confirm your Field Notes subscription', $html, $text, $unsub);
}
