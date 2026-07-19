<?php
/**
 * lib/contact.php — contact form handler (PHPMailer over SMTP).
 * All credentials come from config.php ('smtp' block).
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/captcha.php';
require_once APP_ROOT . '/vendor/phpmailer/Exception.php';
require_once APP_ROOT . '/vendor/phpmailer/PHPMailer.php';
require_once APP_ROOT . '/vendor/phpmailer/SMTP.php';

/**
 * Process a contact-form POST if present.
 * @return array{sent: bool, error: bool, message: string}
 */
function handle_contact_form(): array
{
    $result = ['sent' => false, 'error' => false, 'message' => ''];

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['contact_submit'])) {
        return $result;
    }

    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if (!$name || !$email || !$subject || !$message) {
        return ['sent' => false, 'error' => true, 'message' => 'Please fill in all fields with a valid email address.'];
    }

    if (turnstile_enabled() && !turnstile_verify_post()) {
        return ['sent' => false, 'error' => true, 'message' => 'The spam check did not pass. Please try again.'];
    }

    $smtp = (array) cfg('smtp', []);
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = (string) ($smtp['host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) ($smtp['user'] ?? '');
        $mail->Password   = (string) ($smtp['pass'] ?? '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($smtp['port'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom((string) ($smtp['from'] ?? ''), (string) ($smtp['from_name'] ?? ''));
        $mail->addReplyTo($email, htmlspecialchars($name, ENT_QUOTES));
        $mail->addAddress((string) ($smtp['to'] ?? ''));

        $mail->Subject = '[' . cfg('site_name') . '] ' . htmlspecialchars($subject, ENT_QUOTES);
        $mail->Body    = "Name: {$name}\nEmail: {$email}\n\nMessage:\n{$message}";

        $mail->send();
        return ['sent' => true, 'error' => false, 'message' => ''];
    } catch (Exception $e) {
        return ['sent' => false, 'error' => true, 'message' => 'Something went wrong. Please try emailing me directly.'];
    }
}
