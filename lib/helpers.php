<?php
/**
 * lib/helpers.php — tiny shared utilities.
 */

declare(strict_types=1);

/** HTML-escape for output. The single escaping function used everywhere. */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
