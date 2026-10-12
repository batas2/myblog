<?php
/**
 * config.example.php — copy to config.php and fill in your values.
 *
 * config.php is gitignored (it holds secrets) but IS shipped by deploy.sh,
 * because the live endpoints read it at runtime.
 *
 * Generate secrets with:  openssl rand -hex 24
 */

return [
    // site identity
    'site_url'    => 'https://example.com',      // no trailing slash
    'site_name'   => 'example.com',
    'author'      => 'Your Name',
    'og_image'    => 'https://example.com/photo.jpg',

    // newsletter form target — '/n.php' for the built-in self-hosted
    // newsletter (double opt-in over your own SMTP, list in
    // content/newsletter.db, sends via tools/newsletter_send.php cron);
    // an external embed URL also works; '' hides the subscribe boxes
    'newsletter_action' => '/n.php',

    // Calendly event link for the free discovery call on /services/
    // (e.g. 'https://calendly.com/you/discovery-30'); '' = contact-form CTA instead
    'calendly_url' => '',

    // HMAC key for the comment captcha tokens; rotate freely
    'comments_secret' => 'CHANGE-ME-openssl-rand-hex-24',

    // analytics-only access key: /stats.php?key=<this> mints a read-only
    // session and redirects into admin.php's analytics tabs
    // (a logged-in admin session sees the same tabs without the key)
    // HMAC key for shareable draft links: /preview.php?slug=<slug>&k=<token>.
    // Lets one person read an unpublished essay without an admin session.
    // Leave it as-is and sharing stays off; rotating it revokes every link.
    'preview_secret' => 'CHANGE-ME-openssl-rand-hex-24',

    'stats_key' => 'CHANGE-ME-openssl-rand-hex-24',

    // admin.php sign-in: a login name and the password as a PHP password hash;
    // either one empty = admin disabled
    // generate: php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_user'          => '',
    'admin_password_hash' => '',

    // second factor: a base32 TOTP secret shared with an authenticator app
    // (Google Authenticator, Aegis, 1Password…). '' = password only.
    //   php tools/admin_totp.php --new    a fresh secret + its enrolment URI
    //   php tools/admin_totp.php          the code valid right now — the way
    //                                     back in when the phone is gone
    'admin_totp_secret'   => '',

    // where the admin is open from: ISO 3166-1 alpha-2 codes, judged by the
    // offline GeoIP database (content/geoip.dat) on every request, not just at
    // sign-in. [] = anywhere. Private addresses (local dev) always pass.
    'admin_allow_countries' => [],

    // mail smtp.to about every sign-in and every lockout (needs the smtp block)
    'admin_alerts'        => true,

    // your own networks — plain addresses or CIDR blocks, v4 and v6. From here
    // the password alone signs in (the one-time code is asked everywhere else),
    // and a burst of failed logins elsewhere never freezes you out. A static
    // home address belongs here; a café does not. [] = the code everywhere.
    'admin_trusted_ips'   => [],

    // your own connection, never counted in analytics. Plain addresses or CIDR
    // blocks, IPv4 and IPv6: ['203.0.113.7', '2001:db8:1234::/48', '10.0.0.0/8'].
    // Complements the /a.php?me=off cookie, which only covers one browser and
    // dies with an incognito window. The address is compared in memory on the
    // beacon and is never stored, so nothing about the privacy model changes.
    // Admin → System shows the address you are connecting from right now.
    'analytics_exclude_ips' => [],

    // where the web server keeps its daily access logs, named YYYY-MM-DD.log.
    // Optional, read-only, and only ever counted: Admin → System compares the
    // beacon rows written for a day against the POST /a.php requests the server
    // logged, which is the only way to notice one of the two going lossy.
    // MyDevil: /usr/local/devil/logs/httpd/users/<user>/<domain>
    'analytics_access_log_dir' => '',

    // publish times ("**Publish:**" lines and filename dates) are read in this zone
    'timezone' => 'Europe/Warsaw',

    // CLI php used by the admin "Rebuild site now" button
    'php_bin' => 'php',

    // Cloudflare Turnstile captcha (contact form + comments). Both keys set =
    // Turnstile replaces the built-in math captcha; leave empty to keep the
    // dependency-free defenses. Remember to allow this hostname on the widget.
    'turnstile' => [
        'sitekey' => '',
        'secret'  => '',
    ],

    // outbound mail (contact form + newsletter, via lib/mail.php);
    // leave 'pass' empty to disable all sending
    'smtp' => [
        'host'      => 'smtp.example.com',
        'port'      => 587,
        'user'      => '',
        'pass'      => '',
        'from'      => '',            // envelope From for everything sent
        'from_name' => '',            // display name on all outbound mail
        'to'        => '',            // where contact-form messages land
        'reply_to'  => '',            // Reply-To on newsletter mail ('' = same as from)
    ],
];
