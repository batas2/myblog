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

    // newsletter embed endpoint (Buttondown:
    // https://buttondown.com/api/emails/embed-subscribe/<username>)
    // leave '' to hide the subscribe boxes
    'newsletter_action' => '',

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

    // self-hosted GoatCounter base URL, e.g. 'https://stats.bfrackowiak.pl'
    // (see docs/ANALYTICS-SETUP.md); leave '' to omit the tracker snippet
    'goatcounter_url' => '',

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
