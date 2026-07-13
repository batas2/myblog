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

    // HMAC key for the comment captcha tokens; rotate freely
    'comments_secret' => 'CHANGE-ME-openssl-rand-hex-24',

    // access key for the analytics dashboard: /stats.php?key=<this>
    'stats_key' => 'CHANGE-ME-openssl-rand-hex-24',

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
