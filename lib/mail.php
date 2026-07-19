<?php
/**
 * lib/mail.php — the one place SMTP is configured.
 * Both outbound paths (contact form, newsletter) build their PHPMailer here,
 * so host/auth/TLS/charset and the sender identity can never drift apart.
 * All values come from config.php's 'smtp' block; an empty password means
 * mail is disabled (tests, fresh clones) and the factory returns null.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once APP_ROOT . '/vendor/phpmailer/Exception.php';
require_once APP_ROOT . '/vendor/phpmailer/PHPMailer.php';
require_once APP_ROOT . '/vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function mail_enabled(): bool
{
    return ((array) cfg('smtp', []))['pass'] !== '';
}

/** A connected-and-configured PHPMailer with the site's From identity, or null when mail is disabled. */
function smtp_mailer(): ?PHPMailer
{
    if (!mail_enabled()) return null;
    $smtp = (array) cfg('smtp', []);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = (string) ($smtp['host'] ?? '');
    $mail->SMTPAuth   = true;
    $mail->Username   = (string) ($smtp['user'] ?? '');
    $mail->Password   = (string) ($smtp['pass'] ?? '');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int) ($smtp['port'] ?? 587);
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom((string) ($smtp['from'] ?? ''), (string) ($smtp['from_name'] ?? ''));
    return $mail;
}

/** The Reply-To for list mail — 'reply_to' when set, the From address otherwise. */
function mail_reply_to(): string
{
    $smtp = (array) cfg('smtp', []);
    return (string) (($smtp['reply_to'] ?? '') !== '' ? $smtp['reply_to'] : ($smtp['from'] ?? ''));
}
