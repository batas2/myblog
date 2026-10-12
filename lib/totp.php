<?php
/**
 * lib/totp.php — RFC 6238 one-time codes with nothing but hash_hmac.
 *
 * The admin sign-in's second factor: a base32 secret in config.php
 * ('admin_totp_secret') that also lives in an authenticator app (Google
 * Authenticator, Aegis, 1Password…). Six digits, 30-second steps, SHA-1 —
 * the defaults every app assumes, so enrolment is typing the secret in or
 * scanning the otpauth:// URI that totp_uri() builds.
 *
 * tools/admin_totp.php prints the current code from the config secret, which
 * is the way back in when the phone is gone: the secret is in config.php,
 * the app only ever held a copy.
 */

declare(strict_types=1);

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totp_base32_encode(string $bin): string
{
    $out = '';
    $bits = 0;
    $acc = 0;
    foreach (str_split($bin) as $ch) {
        $acc = ($acc << 8) | ord($ch);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= TOTP_ALPHABET[($acc >> $bits) & 31];
        }
    }
    if ($bits > 0) $out .= TOTP_ALPHABET[($acc << (5 - $bits)) & 31];
    return $out;
}

/** Binary secret for a base32 string (case and spaces forgiven), '' when it is not one. */
function totp_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[\s=-]/', '', $b32) ?? '');
    if ($b32 === '' || !preg_match('/^[A-Z2-7]+$/', $b32)) return '';
    $out = '';
    $bits = 0;
    $acc = 0;
    foreach (str_split($b32) as $ch) {
        $acc = ($acc << 5) | strpos(TOTP_ALPHABET, $ch);
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($acc >> $bits) & 0xFF);
        }
    }
    return $out;
}

/** A fresh secret: 20 random bytes, the length RFC 4226 recommends, as 32 base32 characters. */
function totp_secret(): string
{
    return totp_base32_encode(random_bytes(20));
}

/** HOTP (RFC 4226): the code for one counter value. */
function hotp(string $secretBin, int $counter, int $digits = TOTP_DIGITS): string
{
    $mac = hash_hmac('sha1', pack('J', $counter), $secretBin, true);
    $off = ord($mac[19]) & 0x0F;
    $num = ((ord($mac[$off]) & 0x7F) << 24)
         | (ord($mac[$off + 1]) << 16)
         | (ord($mac[$off + 2]) << 8)
         | ord($mac[$off + 3]);
    return str_pad((string) ($num % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/** The time step a moment falls in. */
function totp_step(?int $ts = null): int
{
    return intdiv($ts ?? time(), TOTP_PERIOD);
}

/** The code valid right now (or at $ts) for a base32 secret; '' for a bad secret. */
function totp_code(string $b32, ?int $ts = null): string
{
    $bin = totp_base32_decode($b32);
    return $bin === '' ? '' : hotp($bin, totp_step($ts));
}

/**
 * Check a typed code against the secret, allowing $window steps of clock
 * drift either way. Returns the step that matched, so the caller can refuse
 * to accept it a second time, or null. Constant-time on the digits.
 */
function totp_verify(string $b32, string $code, ?int $ts = null, int $window = 1): ?int
{
    $code = preg_replace('/\s+/', '', $code) ?? '';
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) return null;
    $bin = totp_base32_decode($b32);
    if ($bin === '') return null;
    $now = totp_step($ts);
    $hit = null;
    // every candidate is computed, so a late match costs the same as an early one
    for ($d = -$window; $d <= $window; $d++) {
        if (hash_equals(hotp($bin, $now + $d), $code) && $hit === null) $hit = $now + $d;
    }
    return $hit;
}

/** The enrolment URI an authenticator app reads (as text, or as a QR code you make from it). */
function totp_uri(string $b32, string $account, string $issuer): string
{
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
        . '?' . http_build_query([
            'secret'    => $b32,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => TOTP_DIGITS,
            'period'    => TOTP_PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
}
