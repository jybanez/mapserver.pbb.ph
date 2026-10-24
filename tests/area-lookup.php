<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/tools/lookup-area.php';

// Self-contained, synthetic SHP/DBF fixtures; no provider credentials or networking.
$checks = 0;
function check(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
    echo 'PASS ' . $label . PHP_EOL;
}
function square(float $w, float $s, float $e, float $n): array
{
    return [[$w, $s], [$e, $s], [$e, $n], [$w, $n], [$w, $s]];
}
function shape(array $rings): string
{
    $points = array_merge(...$rings);
    $xs = array_column($points, 0); $ys = array_column($points, 1);
    $bytes = pack('V', 5) . pack('e4', min($xs), min($ys), max($xs), max($ys)) . pack('V2', count($rings), count($points));
    $start = 0;
    foreach ($rings as $ring) { $bytes .= pack('V', $start); $start += count($ring); }
    foreach ($points as [$x, $y]) { $bytes .= pack('e2', $x, $y); }
    return $bytes;
}
function fixture_zip(string $path, array $records): void
{
    $contents = '';
    foreach ($records as $i => $r) {
        $body = shape($r['rings']);
        $contents .= pack('N2', $i + 1, strlen($body) / 2) . $body;
    }
    $header = pack('N', 9994) . str_repeat("\0", 20) . pack('N', (100 + strlen($contents)) / 2)
        . pack('V2', 1000, 5) . pack('e8', -180, -90, 180, 90, 0, 0, 0, 0);
    $dbf = chr(3) . str_repeat("\0", 3) . pack('Vvv', count($records), 65, 13) . str_repeat("\0", 20);
    $dbf .= str_pad('psgc_code', 11, "\0") . 'C' . str_repeat("\0", 4) . chr(12) . str_repeat("\0", 15) . "\r";
    foreach ($records as $r) { $dbf .= ' ' . str_pad($r['code'], 12); }
    $dbf .= chr(26);
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('BgySubMuns.shp.shp', $header . $contents);
    $zip->addFromString('BgySubMuns.shp.dbf', $dbf);
    $zip->addFromString('BgySubMuns.shp.prj', 'GEOGCS["WGS 84"]');
    $zip->close();
}

$root = sys_get_temp_dir() . '/mapserver-lookup-test-' . bin2hex(random_bytes(6));
mkdir($root);
$files = [];
try {
    file_put_contents($root . '/manifest.json', json_encode(['source_repository' => 'synthetic-tests', 'captured_at' => 'fixture-v1']));
    file_put_contents($root . '/PH_Adm3_MuniCities.csv', "adm1_psgc,adm2_psgc,adm3_psgc,adm3_en\n010000000,010100000,010101000,Test City\n");
    $csv = "adm1_psgc,adm2_psgc,adm3_psgc,adm4_psgc,adm4_en\n";
    foreach (['001', '002', '003', '004'] as $suffix) {
        $csv .= "010000000,010100000,010101000,010101$suffix,Barangay $suffix\n";
    }
    file_put_contents($root . '/PH_Adm4_BgySubMuns.csv', $csv);
    $a = ['code' => '010101001', 'rings' => [square(0, 0, 2, 2), square(.5, .5, 1.5, 1.5)]];
    $b = ['code' => '010101002', 'rings' => [square(2, 0, 4, 2)]];
    $c = ['code' => '010101003', 'rings' => [square(10, 0, 11, 1), square(12, 0, 13, 1)]];
    $zipPath = $root . '/PH_Adm4_BgySubMuns.shp.zip';
    fixture_zip($zipPath, [$b, $c, $a]); // Deliberately out of order.
    $cityCsv = file_get_contents($root . '/PH_Adm3_MuniCities.csv');
    $cityHeader = "adm1_psgc,adm2_psgc,adm3_psgc,adm3_en\n";
    $cityRow = "010000000,010100000,010101000,Test City\n";
    $conflictingCityRow = "010000000,010100000,010101000,Conflicting City\n";
    foreach ([$cityRow . $conflictingCityRow, $conflictingCityRow . $cityRow] as $order => $rows) {
        file_put_contents($root . '/PH_Adm3_MuniCities.csv', $cityHeader . $rows);
        [$cityStatus, $cityError] = lookup_response('lat=.25&lon=.25', $root, 'test');
        check($cityStatus === 503 && $cityError['error']['code'] === 'dataset_unavailable', 'conflicting city metadata rejected in row order ' . $order);
    }
    file_put_contents($root . '/PH_Adm3_MuniCities.csv', $cityHeader . $cityRow . $cityRow);
    check(lookup_response('lat=.25&lon=.25', $root, 'test')[0] === 200, 'identical city metadata duplicates accepted');
    file_put_contents($root . '/PH_Adm3_MuniCities.csv', $cityCsv);
    $unclosed = $a;
    $unclosed['rings'][0][4] = [0.0, 0.1];
    fixture_zip($zipPath, [$unclosed]);
    [$ringStatus, $ringError] = lookup_response('lat=.25&lon=.25', $root, 'test');
    check($ringStatus === 503 && $ringError['error']['code'] === 'dataset_unavailable', 'unclosed original outer ring rejected');
    $unclosedHole = $a;
    $unclosedHole['rings'][1][4] = [.5, .6];
    fixture_zip($zipPath, [$unclosedHole]);
    check(lookup_response('lat=50&lon=50', $root, 'test')[0] === 503, 'unclosed original hole rejected even outside candidate bbox');
    fixture_zip($zipPath, [$b, $c, $a]);
    [$status, $first] = lookup_response('lat=0.25&lon=0.25', $root, 'fixture-build');
    check($status === 200 && $first['outcome'] === 'matched' && $first['matches'][0]['barangay_code'] === '010101001', 'interior and preserved leading-zero code');
    check($first['coverage']['completeness'] === 'unknown' && $first['matches'][0]['hierarchy']['province']['name'] === null, 'unknown completeness and no invented names');
    check($first['matches'][0]['bbox'] === ['west' => 0.0, 'south' => 0.0, 'east' => 2.0, 'north' => 2.0], 'named bbox');
    [, $edge] = lookup_response('lat=0.25&lon=2', $root, 'test');
    check($edge['outcome'] === 'ambiguous' && array_column($edge['matches'], 'barangay_code') === ['010101001', '010101002'], 'shared edge and stable canonical order');
    check(array_column($edge['matches'], 'relation') === ['boundary', 'boundary'], 'explicit boundary relations');
    [, $hole] = lookup_response('lat=1&lon=1', $root, 'test');
    check($hole['outcome'] === 'coverage_unknown' && $hole['matches'] === [], 'hole interior excluded');
    [, $holeEdge] = lookup_response('lat=1&lon=0.5', $root, 'test');
    check($holeEdge['outcome'] === 'matched' && $holeEdge['matches'][0]['relation'] === 'boundary', 'lone hole boundary remains matched');
    [, $island] = lookup_response('lat=0.5&lon=12.5', $root, 'test');
    check($island['outcome'] === 'matched' && $island['matches'][0]['barangay_code'] === '010101003', 'MultiPolygon second island');
    [, $near] = lookup_response('lat=0.25&lon=-0.0000000005', $root, 'test');
    [, $far] = lookup_response('lat=0.25&lon=-0.000000002', $root, 'test');
    check($near['outcome'] === 'matched' && $near['matches'][0]['relation'] === 'boundary' && $far['outcome'] === 'coverage_unknown', 'outer tolerance inside/outside');
    [, $nearHole] = lookup_response('lat=1&lon=0.5000000005', $root, 'test');
    [, $farHole] = lookup_response('lat=1&lon=0.500000002', $root, 'test');
    check($nearHole['matches'][0]['relation'] === 'boundary' && $farHole['matches'] === [], 'hole tolerance inside/outside');
    $overlap = ['code' => '010101004', 'rings' => [square(.1, .1, .4, .4)]];
    fixture_zip($zipPath, [$a, $b, $c, $overlap, $a]);
    [, $updated] = lookup_response('lat=0.25&lon=0.25', $root, 'test');
    check($updated['outcome'] === 'ambiguous' && count($updated['matches']) === 2, 'overlapping interiors and exact duplicate deduplication');
    check($first['dataset']['content_id'] !== $updated['dataset']['content_id'], 'dataset replacement changes content identity and matches');
    $zip = new ZipArchive();
    $zip->open($zipPath);
    $dbf = $zip->getFromName('BgySubMuns.shp.dbf');
    $zip->addFromString('BgySubMuns.shp.dbf', substr($dbf, 0, 70));
    $zip->close();
    check(lookup_response('lat=.25&lon=.25', $root, 'test')[0] === 503, 'truncated DBF fails rather than returning partial matches');
    $conflict = ['code' => '010101001', 'rings' => [square(.05, .05, .45, .45)]];
    fixture_zip($zipPath, [$a, $conflict]);
    [, $conflicting] = lookup_response('lat=.25&lon=.25', $root, 'test');
    check(count($conflicting['matches']) === 2 && $conflicting['outcome'] === 'ambiguous', 'conflicting same-code geometries retained');
    foreach (['', 'lat=1', 'lat=NaN&lon=0', 'lat=INF&lon=0', 'lat=91&lon=0', 'lat=0&lon=-181', 'lat=1e999&lon=0', 'lat=x&lon=0', 'lat=1&lat=2&lon=0', 'lat=1&%6cat=2&lon=0', 'lat[]=1&lon=0', 'lat=1&lat%5B0%5D=2&lon=0', 'lat=1&lon=0&lon=1', 'lat=+1&lon=0'] as $query) {
        [$status, $bad] = lookup_response($query, '/absent', 'test');
        check($status === 400 && $bad['error']['code'] === 'invalid_coordinates', 'invalid input rejected before data access: ' . $query);
    }
    check(lookup_coordinates('lat=%2B90&lon=-180') === ['lat' => 90.0, 'lon' => -180.0], 'finite extrema and encoded plus accepted');
    mkdir($root . '/provinces'); mkdir($root . '/provinces/01');
    file_put_contents($root . '/provinces/01/pack.json', json_encode(['scope' => 'province', 'province_code' => '01']));
    fixture_zip($root . '/provinces/01/BgySubMuns.shp.zip', [$a]);
    unlink($zipPath);
    [, $scoped] = lookup_response('lat=50&lon=50', $root, 'test');
    check($scoped['outcome'] === 'coverage_unknown' && $scoped['dataset']['installed_scopes'][0]['code'] === '01', 'scoped miss never certifies absence');
    file_put_contents($root . '/provinces/01/BgySubMuns.shp.zip', 'broken');
    fixture_zip($zipPath, [$a]);
    check(lookup_response('lat=0&lon=0', $root, 'test')[0] === 503, 'corrupt installed pack fails explicitly');
    unlink($zipPath);
    unlink($root . '/provinces/01/BgySubMuns.shp.zip');
    check(lookup_response('lat=0&lon=0', $root, 'test')[0] === 503, 'missing declared pack fails explicitly');
    unlink($root . '/provinces/01/pack.json'); rmdir($root . '/provinces/01'); rmdir($root . '/provinces');
    check(lookup_response('lat=0&lon=0', $root, 'test')[0] === 503, 'missing geometry dataset fails explicitly');
    fixture_zip($zipPath, [$a]);
    file_put_contents($root . '/manifest.json', json_encode(['source_repository' => 'synthetic-tests', 'captured_at' => 'fixture-v1',
        'files' => [['path' => 'PH_Adm4_BgySubMuns.shp.zip', 'sha256' => str_repeat('0', 64)]]]));
    check(lookup_response('lat=0&lon=0', $root, 'test')[0] === 503, 'declared source checksum mismatch fails explicitly');
    file_put_contents($root . '/manifest.json', json_encode(['source_repository' => 'synthetic-tests', 'captured_at' => 'fixture-v1']));
    file_put_contents($root . '/PH_Adm4_BgySubMuns.csv', 'corrupt');
    check(lookup_response('lat=0&lon=0', $root, 'test')[0] === 503, 'corrupt required CSV fails explicitly');
    echo "SUCCESS $checks checks; networking is absent from the worker and fixtures.\n";
} finally {
    // Only this test's explicit random temporary directory is removed.
    foreach (glob($root . '/*') ?: [] as $path) { if (is_file($path)) { unlink($path); } }
    if (is_dir($root . '/provinces/01')) {
        foreach (glob($root . '/provinces/01/*') ?: [] as $path) { unlink($path); }
        rmdir($root . '/provinces/01'); rmdir($root . '/provinces');
    }
    rmdir($root);
}
