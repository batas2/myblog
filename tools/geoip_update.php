<?php
/**
 * tools/geoip_update.php — refresh the offline IP→country database.
 *
 * Downloads the free DB-IP "Country Lite" CSV (CC BY 4.0, https://db-ip.com),
 * compiles it into the compact binary content/geoip.dat that lib/geoip.php
 * reads. DB-IP publishes a new file monthly; run this monthly (cron or by
 * hand) and redeploy — or run it directly on the server.
 *
 * Usage: php tools/geoip_update.php [YYYY-MM]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/geoip.php';

$month = $argv[1] ?? gmdate('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    fwrite(STDERR, "usage: php tools/geoip_update.php [YYYY-MM]\n");
    exit(1);
}

$tmpGz = tempnam(sys_get_temp_dir(), 'dbip') . '.csv.gz';
$ctx = stream_context_create(['http' => ['timeout' => 120, 'follow_location' => 1, 'user_agent' => 'bfrackowiak.pl geoip updater']]);

$fetched = false;
foreach ([$month, gmdate('Y-m', strtotime($month . '-01 -1 month'))] as $try) {
    $url = "https://download.db-ip.com/free/dbip-country-lite-{$try}.csv.gz";
    echo "fetching {$url}\n";
    // stream to disk — the archive never has to fit in memory_limit
    if (@copy($url, $tmpGz, $ctx) && filesize($tmpGz) > 100000) {
        $fetched = true;
        break;
    }
    echo "  not available yet, trying previous month\n";
}
if (!$fetched) {
    fwrite(STDERR, "download failed — check connectivity or fetch the CSV manually from https://db-ip.com/db/download/ip-to-country-lite\n");
    exit(1);
}

[$n4, $n6] = geoip_compile($tmpGz, geoip_db_path(), true);
unlink($tmpGz);

$size = round(filesize(geoip_db_path()) / 1048576, 1);
echo "compiled content/geoip.dat: {$n4} IPv4 + {$n6} IPv6 ranges, {$size} MB\n";
echo "attribution: IP geolocation by DB-IP (https://db-ip.com), CC BY 4.0\n";
