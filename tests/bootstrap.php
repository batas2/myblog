<?php
/**
 * tests/bootstrap.php — micro test harness, zero dependencies.
 * Collects failures instead of dying, prints a summary, sets the exit code.
 */

declare(strict_types=1);

$GLOBALS['__t'] = ['pass' => 0, 'fail' => 0, 'failures' => [], 'section' => ''];

function section(string $name): void
{
    $GLOBALS['__t']['section'] = $name;
    echo "\n== {$name} ==\n";
}

function t_assert(bool $cond, string $msg): void
{
    $t = &$GLOBALS['__t'];
    if ($cond) {
        $t['pass']++;
        echo "  ok  {$msg}\n";
    } else {
        $t['fail']++;
        $t['failures'][] = "[{$t['section']}] {$msg}";
        echo "  FAIL {$msg}\n";
    }
}

function t_eq($expected, $actual, string $msg): void
{
    $ok = $expected === $actual;
    t_assert($ok, $msg . ($ok ? '' : sprintf(' (expected %s, got %s)',
        var_export($expected, true), var_export(is_string($actual) ? substr($actual, 0, 120) : $actual, true))));
}

function t_contains(string $needle, string $haystack, string $msg): void
{
    t_assert(str_contains($haystack, $needle), $msg . (str_contains($haystack, $needle) ? '' : " (missing: {$needle})"));
}

function t_not_contains(string $needle, string $haystack, string $msg): void
{
    t_assert(!str_contains($haystack, $needle), $msg);
}

function t_summary(): int
{
    $t = $GLOBALS['__t'];
    echo "\n----------------------------------------\n";
    echo "PASS: {$t['pass']}   FAIL: {$t['fail']}\n";
    foreach ($t['failures'] as $f) echo "  ✗ {$f}\n";
    echo $t['fail'] === 0 ? "ALL GREEN\n" : "SUITE FAILED\n";
    return $t['fail'] === 0 ? 0 : 1;
}

/** minimal HTTP client on PHP streams; returns [status, body, location] */
function http_req(string $method, string $url, array $opt = []): array
{
    $headers = $opt['headers'] ?? "User-Agent: Mozilla/5.0 (X11; Linux x86_64) TestChrome/126\r\n";
    if (isset($opt['body']) && stripos($headers, 'content-type') === false) {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $ctx = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $opt['body'] ?? '',
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 15,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $code = 0;
    $loc = '';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int) $m[1];
        if (stripos($h, 'Location:') === 0) $loc = trim(substr($h, 9));
    }
    return [$code, (string) $body, $loc];
}
