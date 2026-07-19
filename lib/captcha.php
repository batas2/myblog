<?php
/**
 * lib/captcha.php — Cloudflare Turnstile.
 *
 * Enabled only when config.php has both keys in the 'turnstile' block:
 *   'turnstile' => ['sitekey' => '0x4AAA…', 'secret' => '0x4AAA…']
 * With keys absent (tests, fresh clones) every caller falls back to the
 * built-in defenses (comments: HMAC math captcha; contact: none), so the
 * site never hard-depends on Cloudflare.
 *
 * The widget needs the CSP to allow challenges.cloudflare.com in
 * script-src and frame-src — wired in .htaccess.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function turnstile_enabled(): bool
{
    $t = (array) cfg('turnstile', []);
    return ($t['sitekey'] ?? '') !== '' && ($t['secret'] ?? '') !== '';
}

/** The widget placeholder. Theme 'auto' follows the visitor's color scheme. */
function turnstile_widget(string $action): string
{
    $key = e((string) ((array) cfg('turnstile', []))['sitekey']);
    $act = e($action);
    return '<div class="cf-turnstile" data-sitekey="' . $key . '" data-theme="auto" data-action="' . $act . '"></div>';
}

/** The loader tag — emit once per page, only when a widget is present. */
function turnstile_script(): string
{
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/**
 * Verify the token the widget posted (cf-turnstile-response) against
 * Cloudflare's siteverify endpoint. cURL first, stream fallback —
 * same approach as desta.pl's handler. Fails closed.
 */
function turnstile_verify_post(): bool
{
    $secret = (string) ((array) cfg('turnstile', []))['secret'];
    $token  = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    if ($secret === '' || $token === '') return false;

    $body = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $resp = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
    }

    if (($resp === false || $resp === '') && ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $body,
                'timeout'       => 8,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
    }

    if ($resp === false || $resp === '') return false;
    $data = json_decode($resp, true);
    return is_array($data) && !empty($data['success']);
}
