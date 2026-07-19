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

    // access key for the analytics dashboard: /stats.php?key=<this>
    // (a logged-in admin session also opens stats.php without the key)
    'stats_key' => 'CHANGE-ME-openssl-rand-hex-24',

    // admin.php password as a PHP password hash; empty = admin disabled
    // generate: php -r "echo password_hash('your-password', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_password_hash' => '',

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

    // contact form (PHPMailer over SMTP); leave 'pass' empty to disable sending
    'smtp' => [
        'host'      => 'smtp.example.com',
        'port'      => 587,
        'user'      => '',
        'pass'      => '',
        'from'      => '',
        'from_name' => '',
        'to'        => '',
    ],
];
