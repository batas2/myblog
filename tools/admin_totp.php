<?php
/**
 * tools/admin_totp.php — the admin's one-time codes from the command line.
 *
 *   php tools/admin_totp.php          the code valid right now for the
 *                                     admin_totp_secret in config.php, with
 *                                     the seconds it has left — the way in
 *                                     when the phone is gone (the secret lives
 *                                     in config.php; the app only held a copy)
 *   php tools/admin_totp.php --setup  the secret and its otpauth:// URI, for
 *                                     enrolling (another) authenticator app
 *   php tools/admin_totp.php --new    a fresh secret + URI to paste into
 *                                     config.php as admin_totp_secret
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/totp.php';

$mode    = $argv[1] ?? '';
$issuer  = (string) cfg('site_name', 'admin');
$account = (string) cfg('admin_user', 'admin');

if ($mode === '--new') {
    $s = totp_secret();
    echo "admin_totp_secret: {$s}\n", totp_uri($s, $account, $issuer), "\n";
    exit(0);
}

$s = (string) cfg('admin_totp_secret', '');
if ($s === '') {
    fwrite(STDERR, "admin_totp_secret is empty in config.php — run with --new to make one\n");
    exit(1);
}
if ($mode === '--setup') {
    echo "secret: {$s}\n", totp_uri($s, $account, $issuer), "\n";
    exit(0);
}
$code = totp_code($s);
if ($code === '') {
    fwrite(STDERR, "admin_totp_secret in config.php is not valid base32\n");
    exit(1);
}
echo $code, '  (valid ', TOTP_PERIOD - time() % TOTP_PERIOD, "s more)\n";
