<?php
/**
 * lib/contact.php — contact form handler (PHPMailer over SMTP via lib/mail.php).
 *
 * Defense parity with the other write endpoints: honeypot field, per-IP
 * throttle, optional Turnstile — and POST/redirect/GET so a refresh never
 * resubmits (/about/ shows the success state from ?sent=1).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/captcha.php';
require_once __DIR__ . '/mail.php';

const CONTACT_MIN_GAP = 30;   // seconds between messages per IP

/** Where the form lives, and so where POST/redirect/GET lands. */
const CONTACT_PAGE = '/about/';

/**
 * Process a contact-form POST if present.
 * @return array{sent: bool, error: bool, message: string}
 */
function handle_contact_form(): array
{
    $result = ['sent' => false, 'error' => false, 'message' => ''];

    // PRG landing: the redirect target after a successful send
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['sent'])) {
        return ['sent' => true, 'error' => false, 'message' => ''];
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['contact_submit'])) {
        return $result;
    }

    // honeypot: bots that fill the invisible field get a fake success
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        header('Location: ' . CONTACT_PAGE . '?sent=1#contact');
        exit;
    }

    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if (!$name || !$email || !$subject || !$message) {
        return ['sent' => false, 'error' => true, 'message' => 'Please fill in all fields with a valid email address.'];
    }

    $rl = throttle_path('ct_', 'bfrackowiak-contact', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (is_file($rl) && time() - (int) filemtime($rl) < CONTACT_MIN_GAP) {
        return ['sent' => false, 'error' => true, 'message' => 'That was quick — give it half a minute and send again.'];
    }

    if (turnstile_enabled() && !turnstile_verify_post()) {
        return ['sent' => false, 'error' => true, 'message' => 'The spam check did not pass. Please try again.'];
    }

    $mail = smtp_mailer();
    if ($mail === null) {
        return ['sent' => false, 'error' => true, 'message' => 'Something went wrong. Please try emailing me directly.'];
    }

    // header values are plain text — PHPMailer encodes them; just keep CRLF out
    $clean = fn(string $v): string => str_replace(["\r", "\n"], ' ', $v);
    try {
        $mail->addReplyTo($email, $clean($name));
        $mail->addAddress((string) (((array) cfg('smtp', []))['to'] ?? ''));
        $mail->Subject = '[' . cfg('site_name') . '] ' . $clean($subject);
        $mail->Body    = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}";
        $mail->send();
    } catch (Exception $e) {
        return ['sent' => false, 'error' => true, 'message' => 'Something went wrong. Please try emailing me directly.'];
    }

    touch($rl);
    header('Location: ' . CONTACT_PAGE . '?sent=1#contact');
    exit;
}
