<?php
/**
 * lib/geoip.php — offline IP → country lookup, no extensions, no services.
 *
 * Data file: content/geoip.dat, compiled from the free DB-IP "Country Lite"
 * CSV by tools/geoip_update.php (attribution: https://db-ip.com — CC BY 4.0).
 *
 * Binary format "GIP2", ranges sorted by start address:
 *   [0..3]   magic "GIP2"
 *   [4..7]   uint32 BE — number of IPv4 records
 *   [8..11]  uint32 BE — number of IPv6 records
 *   then IPv4 records, 10 bytes each:  start u32 BE · end u32 BE · country 2 ASCII
 *   then IPv6 records, 34 bytes each:  start 16B    · end 16B    · country 2 ASCII
 *
 * Lookup is a binary search over the file with fseek — nothing is loaded
 * into memory, one query costs ~20 tiny reads.
 */

declare(strict_types=1);

const GEOIP_MAGIC = 'GIP2';
const GEOIP_REC4  = 10;
const GEOIP_REC6  = 34;

function geoip_db_path(): string
{
    return CONTENT_DIR . '/geoip.dat';
}

/**
 * ISO 3166-1 alpha-2 country for an IP, or '' (no db, private/reserved
 * address, or address not in any range). Never throws.
 */
function geoip_country(string $ip, ?string $dbPath = null): string
{
    $dbPath ??= geoip_db_path();
    if ($ip === '' || !is_file($dbPath)) return '';

    $bin = @inet_pton($ip);
    if ($bin === false) return '';

    // IPv4-mapped IPv6 (::ffff:a.b.c.d) is really IPv4 — unwrap before the
    // range filter, which would otherwise flag the ::ffff: prefix as reserved
    if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\x00", 10) . "\xff\xff") {
        $bin = substr($bin, 12);
        $ip = (string) inet_ntop($bin);
    }

    // public addresses only — LAN and localhost say nothing about geography
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return '';

    $fh = @fopen($dbPath, 'rb');
    if (!$fh) return '';
    try {
        $head = (string) fread($fh, 12);
        if (strlen($head) !== 12 || substr($head, 0, 4) !== GEOIP_MAGIC) return '';
        ['n4' => $n4, 'n6' => $n6] = unpack('Nn4/Nn6', $head, 4);

        if (strlen($bin) === 4) {
            return geoip_bsearch($fh, $bin, 12, $n4, GEOIP_REC4, 4);
        }
        return geoip_bsearch($fh, $bin, 12 + $n4 * GEOIP_REC4, $n6, GEOIP_REC6, 16);
    } finally {
        fclose($fh);
    }
}

/** Greatest record with start <= needle; hit only when needle <= end. */
function geoip_bsearch($fh, string $needle, int $base, int $count, int $recSize, int $addrLen): string
{
    $lo = 0;
    $hi = $count - 1;
    $best = null;
    while ($lo <= $hi) {
        $mid = ($lo + $hi) >> 1;
        fseek($fh, $base + $mid * $recSize);
        $rec = (string) fread($fh, $recSize);
        if (strlen($rec) !== $recSize) return '';
        $start = substr($rec, 0, $addrLen);
        if (strcmp($start, $needle) <= 0) {
            $best = $rec;
            $lo = $mid + 1;
        } else {
            $hi = $mid - 1;
        }
    }
    if ($best === null) return '';
    $end = substr($best, $addrLen, $addrLen);
    if (strcmp($needle, $end) > 0) return '';
    $cc = substr($best, 2 * $addrLen, 2);
    return preg_match('/^[A-Z]{2}$/', $cc) ? $cc : '';
}

/**
 * Compile a DB-IP country CSV (lines: first_ip,last_ip,country) into the
 * GIP2 binary at $outPath. Returns [ipv4Count, ipv6Count].
 * Accepts a plain or gzipped ($gz) source; unknown/ZZ ranges are dropped.
 *
 * Streams both record families to temp files, so peak memory stays flat on
 * shared-hosting CLI limits. DB-IP exports arrive sorted; if a family turns
 * out unsorted it is sorted in memory as a fallback.
 */
function geoip_compile(string $csvPath, string $outPath, bool $gz = false): array
{
    $in = $gz ? gzopen($csvPath, 'rb') : fopen($csvPath, 'rb');
    if (!$in) throw new RuntimeException("geoip: cannot open $csvPath");

    $fam = [
        4  => ['tmp' => $outPath . '.v4', 'fh' => null, 'n' => 0, 'prev' => '', 'sorted' => true, 'rec' => GEOIP_REC4],
        16 => ['tmp' => $outPath . '.v6', 'fh' => null, 'n' => 0, 'prev' => '', 'sorted' => true, 'rec' => GEOIP_REC6],
    ];
    foreach ($fam as &$f) {
        $f['fh'] = fopen($f['tmp'], 'wb');
        if (!$f['fh']) throw new RuntimeException("geoip: cannot write {$f['tmp']}");
    }
    unset($f);

    while (($line = $gz ? gzgets($in) : fgets($in)) !== false) {
        $parts = str_getcsv(trim($line));
        if (count($parts) < 3) continue;
        [$a, $b, $cc] = $parts;
        $cc = strtoupper(trim($cc));
        if (!preg_match('/^[A-Z]{2}$/', $cc) || $cc === 'ZZ') continue;
        $pa = @inet_pton(trim($a));
        $pb = @inet_pton(trim($b));
        if ($pa === false || $pb === false || strlen($pa) !== strlen($pb)) continue;
        $f = &$fam[strlen($pa)];
        if ($f['prev'] !== '' && strcmp($pa, $f['prev']) < 0) $f['sorted'] = false;
        $f['prev'] = $pa;
        fwrite($f['fh'], $pa . $pb . $cc);
        $f['n']++;
        unset($f);
    }
    $gz ? gzclose($in) : fclose($in);
    foreach ($fam as &$f) { fclose($f['fh']); }
    unset($f);

    // fallback for unsorted input — loads just that family once
    foreach ($fam as &$f) {
        if ($f['sorted'] || $f['n'] === 0) continue;
        $recs = str_split((string) file_get_contents($f['tmp']), $f['rec']);
        sort($recs, SORT_STRING);
        file_put_contents($f['tmp'], implode('', $recs));
    }
    unset($f);

    $tmp = $outPath . '.tmp';
    $out = fopen($tmp, 'wb');
    if (!$out) throw new RuntimeException("geoip: cannot write $tmp");
    fwrite($out, GEOIP_MAGIC . pack('NN', $fam[4]['n'], $fam[16]['n']));
    foreach ($fam as $f) {
        $fh = fopen($f['tmp'], 'rb');
        stream_copy_to_stream($fh, $out);
        fclose($fh);
        unlink($f['tmp']);
    }
    fclose($out);
    if (!rename($tmp, $outPath)) throw new RuntimeException("geoip: cannot move $tmp to $outPath");

    return [$fam[4]['n'], $fam[16]['n']];
}

/** Age of the geoip database in days, or null when absent. */
function geoip_db_age_days(?string $dbPath = null): ?int
{
    $dbPath ??= geoip_db_path();
    if (!is_file($dbPath)) return null;
    return (int) floor((time() - (int) filemtime($dbPath)) / 86400);
}
