<?php
/**
 * lib/config.php — configuration loader and shared path constants.
 * Every entrypoint (index.php, comments.php, a.php, stats.php, tools/build.php)
 * requires this file first.
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
    define('CONTENT_DIR', APP_ROOT . '/content');
}

/** Read a config value (dot notation for one level: 'smtp.host'). */
function cfg(string $key, $default = null)
{
    static $config = null;
    if ($config === null) {
        $file = APP_ROOT . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Missing config.php — copy config.example.php and fill it in.');
        }
        $config = require $file;
        // one clock everywhere: dates render in the site timezone on any host
        date_default_timezone_set((string) ($config['timezone'] ?? 'Europe/Warsaw'));
    }
    if (str_contains($key, '.')) {
        [$a, $b] = explode('.', $key, 2);
        return $config[$a][$b] ?? $default;
    }
    return $config[$key] ?? $default;
}
