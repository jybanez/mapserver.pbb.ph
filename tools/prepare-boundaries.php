<?php

declare(strict_types=1);

const BOUNDARY_PREP_VERSION = '1.0.0';
const PSGC_REPO_RAW = 'https://raw.githubusercontent.com/altcoder/philippines-psgc-shapefiles/main/dist/';
const PSGC_ZIP_URL = 'https://github.com/altcoder/philippines-psgc-shapefiles/raw/main/dist/PH_Adm4_BgySubMuns.shp.zip';

function usage(): string
{
    return <<<TXT
PBB MapServer PSGC boundary prep

Usage:
  php tools/prepare-boundaries.php --city "Cebu City"
  php tools/prepare-boundaries.php --city "Cebu City" --barangay Guadalupe
  php tools/prepare-boundaries.php --brgy-code 730600043 --output C:\\pbb\\data\\boundaries\\lusaran.geojson
  php tools/prepare-boundaries.php --deployment-scope city --citymun-code 072217000
  php tools/prepare-boundaries.php --deployment-scope province --prov-code 072200000
  php tools/prepare-boundaries.php --deployment-scope region --reg-code 070000000

Options:
  --work-dir       Generated boundary work directory. Default: <mapserver>\\storage\\boundaries
  --source-zip     PSGC Level 4 shapefile ZIP. Default: <work-dir>\\PH_Adm4_BgySubMuns.shp.zip
  --adm3-csv       PSGC Level 3 city/municipality CSV. Default: <work-dir>\\PH_Adm3_MuniCities.csv
  --adm4-csv       PSGC Level 4 barangay CSV. Default: <work-dir>\\PH_Adm4_BgySubMuns.csv
  --city           City/municipality name to export, for example "Cebu City".
  --barangay       Optional barangay name filter.
  --brgy-code      Optional barangay PSGC/code filter.
  --citymun-code   Optional city/municipality PSGC/code filter.
  --prov-code      Optional province PSGC/code filter.
  --reg-code       Optional region PSGC/code filter.
  --deployment-scope barangay, city, province, region, or other. Other is treated as barangay.
  --output         GeoJSON output path. Default is based on city/barangay.
  --index          Barangay index JSON output path. Default: <output>.index.json
  --report         Prep report path. Default: <work-dir>\\prepare-boundaries-report.json
  --no-download    Do not download missing PSGC source files.
  --force-download Re-download PSGC source files.
  --help           Show this help.

TXT;
}

function fail(string $message, int $code = 2): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function default_boundary_work_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'boundaries';
}

function default_boundary_source_file(string $file, string $workDir): string
{
    $vendored = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . $file;
    return is_file($vendored) ? $vendored : $workDir . DIRECTORY_SEPARATOR . $file;
}

function parse_args(array $argv): array
{
    $args = [
        'work-dir' => default_boundary_work_dir(),
        'source-zip' => '',
        'adm3-csv' => '',
        'adm4-csv' => '',
        'city' => '',
        'barangay' => '',
        'brgy-code' => '',
        'citymun-code' => '',
        'prov-code' => '',
        'reg-code' => '',
        'deployment-scope' => '',
        'output' => '',
        'index' => '',
        'report' => '',
        'no-download' => false,
        'force-download' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
            continue;
        }
        if ($arg === '--no-download') {
            $args['no-download'] = true;
            continue;
        }
        if ($arg === '--force-download') {
            $args['force-download'] = true;
            continue;
        }
        if (strncmp($arg, '--', 2) !== 0) {
            fail("Unexpected argument: {$arg}");
        }
        $key = substr($arg, 2);
        if (!array_key_exists($key, $args)) {
            fail("Unknown option: {$arg}");
        }
        $i++;
        if (!isset($argv[$i])) {
            fail("Missing value for {$arg}");
        }
        $args[$key] = $argv[$i];
    }

    return $args;
}

function normalize_text(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return preg_replace('/[^a-z0-9 ]/', '', $value) ?? $value;
}

function normalize_code(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function code_variants(string $value): array
{
    $code = normalize_code($value);
    if ($code === '') {
        return [];
    }
    $variants = [$code];
    if (strlen($code) === 9 && $code[0] === '0' && $code[1] !== '0') {
        $variants[] = $code[1] . '0' . substr($code, 2);
    }
    if (strlen($code) === 6 && $code[0] === '0' && $code[1] !== '0') {
        $variants[] = $code[1] . '0' . substr($code, 2);
    }
    if (strlen($code) === 4 && $code[0] === '0' && $code[1] !== '0') {
        $variants[] = $code[1] . '0' . substr($code, 2);
    }
    if (strlen($code) === 8) {
        $variants[] = $code[0] . '0' . substr($code, 1);
    }
    return array_values(array_unique($variants));
}

function code_matches(string $actual, string $target): bool
{
    $actual = normalize_code($actual);
    if ($actual === '') {
        return false;
    }
    return in_array($actual, code_variants($target), true);
}

function provided_boundary_codes(array $args): array
{
    $codes = [];
    foreach (['brgy-code', 'citymun-code', 'prov-code', 'reg-code'] as $key) {
        $code = normalize_code((string)($args[$key] ?? ''));
        if ($code !== '') {
            array_push($codes, ...code_variants($code));
        }
    }
    return array_values(array_unique($codes));
}

function boundary_pack_matches_codes(array $pack, array $codes): bool
{
    if ($codes === []) {
        return false;
    }

    $aliases = [];
    foreach (($pack['aliases'] ?? []) as $alias) {
        $alias = normalize_code((string)$alias);
        if ($alias !== '') {
            array_push($aliases, ...code_variants($alias));
        }
    }
    $provinceCode = normalize_code((string)($pack['province_code'] ?? ''));
    if ($provinceCode !== '') {
        array_push($aliases, ...code_variants($provinceCode));
    }
    $aliases = array_values(array_unique($aliases));

    foreach ($codes as $code) {
        foreach ($aliases as $alias) {
            if ($code === $alias || str_starts_with($code, $alias) || str_starts_with($alias, $code)) {
                return true;
            }
        }
    }

    return false;
}

function resolve_boundary_source_zip(array $args, string $workDir): string
{
    if ((string)$args['source-zip'] !== '') {
        return (string)$args['source-zip'];
    }

    $packsRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . 'provinces';
    if (is_dir($packsRoot)) {
        $codes = provided_boundary_codes($args);
        $manifests = glob($packsRoot . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'pack.json') ?: [];
        foreach ($manifests as $manifestPath) {
            $pack = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($pack) || !boundary_pack_matches_codes($pack, $codes)) {
                continue;
            }
            $relativeZip = (string)($pack['outputs']['source_zip'] ?? '');
            $zipPath = $relativeZip !== ''
                ? dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeZip)
                : dirname($manifestPath) . DIRECTORY_SEPARATOR . 'BgySubMuns.shp.zip';
            if (is_file($zipPath)) {
                return $zipPath;
            }
        }
    }

    return default_boundary_source_file('PH_Adm4_BgySubMuns.shp.zip', $workDir);
}

function normalize_dbf_code(mixed $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d+)(?:\.0+)?$/', $value, $m)) {
        return $m[1];
    }
    return normalize_code($value);
}

function slugify(string $value): string
{
    $slug = normalize_text($value);
    $slug = trim((string)preg_replace('/\s+/', '-', $slug), '-');
    return $slug !== '' ? $slug : 'boundaries';
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail("Unable to create directory: {$dir}");
    }
}

function download_file(string $url, string $path, bool $force): bool
{
    if (is_file($path) && !$force) {
        return false;
    }
    ensure_dir(dirname($path));
    $ch = curl_init($url);
    if ($ch === false) {
        fail("Unable to initialize download: {$url}");
    }
    $fp = fopen($path, 'wb');
    if ($fp === false) {
        fail("Unable to write download target: {$path}");
    }
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FAILONERROR => true,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'PBB MapServer boundary prep',
    ]);
    $ok = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($fp);
    if ($ok === false) {
        @unlink($path);
        fail("Download failed ({$status}): {$url} {$error}");
    }
    return true;
}

function extract_zip(string $zipPath, string $targetDir): void
{
    ensure_dir($targetDir);
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        fail("Unable to open ZIP: {$zipPath}");
    }
    if (!$zip->extractTo($targetDir)) {
        $zip->close();
        fail("Unable to extract ZIP: {$zipPath}");
    }
    $zip->close();
}

function read_csv_rows(string $path): array
{
    if (!is_file($path)) {
        fail("CSV file not found: {$path}");
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        fail("Unable to read CSV file: {$path}");
    }
    $headers = fgetcsv($fh);
    if (!is_array($headers)) {
        fclose($fh);
        fail("CSV has no header: {$path}");
    }
    if (isset($headers[0])) {
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$headers[0]) ?? (string)$headers[0];
    }
    $rows = [];
    while (($values = fgetcsv($fh)) !== false) {
        $row = [];
        foreach ($headers as $i => $header) {
            $row[(string)$header] = isset($values[$i]) ? trim((string)$values[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

function psgc_compact_legacy(string $psgc): string
{
    return strlen($psgc) === 9 && $psgc[1] === '0' ? $psgc[0] . substr($psgc, 2) : $psgc;
}

function clean_dbf_value(string $raw, string $type): mixed
{
    $value = trim($raw);
    if ($value === '') {
        return '';
    }
    $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
    if ($converted !== false) {
        $value = trim($converted);
    }
    if (in_array($type, ['N', 'F'], true)) {
        return $value;
    }
    return $value;
}

function read_dbf_records(string $path): array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        fail("Unable to read DBF: {$path}");
    }
    $header = fread($fh, 32);
    if ($header === false || strlen($header) !== 32) {
        fclose($fh);
        fail("Invalid DBF header: {$path}");
    }
    $recordCount = unpack('V', substr($header, 4, 4))[1];
    $headerLength = unpack('v', substr($header, 8, 2))[1];
    $recordLength = unpack('v', substr($header, 10, 2))[1];

    $fields = [];
    while (ftell($fh) < $headerLength - 1) {
        $descriptor = fread($fh, 32);
        if ($descriptor === false || strlen($descriptor) !== 32) {
            fclose($fh);
            fail("Invalid DBF field descriptor: {$path}");
        }
        if (ord($descriptor[0]) === 0x0D) {
            break;
        }
        $name = rtrim(substr($descriptor, 0, 11), "\0 ");
        $fields[] = [
            'name' => $name,
            'type' => $descriptor[11],
            'length' => ord($descriptor[16]),
        ];
    }
    fseek($fh, $headerLength);

    $records = [];
    for ($i = 0; $i < $recordCount; $i++) {
        $raw = fread($fh, $recordLength);
        if ($raw === false || strlen($raw) < $recordLength) {
            break;
        }
        if ($raw[0] === '*') {
            $records[] = [];
            continue;
        }
        $offset = 1;
        $record = [];
        foreach ($fields as $field) {
            $value = substr($raw, $offset, $field['length']);
            $offset += $field['length'];
            $record[$field['name']] = clean_dbf_value($value, $field['type']);
        }
        $records[] = $record;
    }
    fclose($fh);
    return $records;
}

function read_dbf_schema($fh, string $path): array
{
    $header = fread($fh, 32);
    if ($header === false || strlen($header) !== 32) {
        fail("Invalid DBF header: {$path}");
    }
    $recordCount = unpack('V', substr($header, 4, 4))[1];
    $headerLength = unpack('v', substr($header, 8, 2))[1];
    $recordLength = unpack('v', substr($header, 10, 2))[1];

    $fields = [];
    while (ftell($fh) < $headerLength - 1) {
        $descriptor = fread($fh, 32);
        if ($descriptor === false || strlen($descriptor) !== 32) {
            fail("Invalid DBF field descriptor: {$path}");
        }
        if (ord($descriptor[0]) === 0x0D) {
            break;
        }
        $fields[] = [
            'name' => rtrim(substr($descriptor, 0, 11), "\0 "),
            'type' => $descriptor[11],
            'length' => ord($descriptor[16]),
        ];
    }
    fseek($fh, $headerLength);
    return [$recordCount, $recordLength, $fields];
}

function parse_dbf_record(string $raw, array $fields): array
{
    if ($raw === '' || $raw[0] === '*') {
        return [];
    }
    $offset = 1;
    $record = [];
    foreach ($fields as $field) {
        $value = substr($raw, $offset, $field['length']);
        $offset += $field['length'];
        $record[$field['name']] = clean_dbf_value($value, $field['type']);
    }
    return $record;
}

function read_le_int(string $bytes, int $offset): int
{
    return unpack('V', substr($bytes, $offset, 4))[1];
}

function read_be_int(string $bytes, int $offset): int
{
    return unpack('N', substr($bytes, $offset, 4))[1];
}

function read_le_double(string $bytes, int $offset): float
{
    return unpack('e', substr($bytes, $offset, 8))[1];
}

function point_in_ring(array $point, array $ring): bool
{
    [$lon, $lat] = $point;
    $inside = false;
    $count = count($ring);
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $xi = (float)$ring[$i][0];
        $yi = (float)$ring[$i][1];
        $xj = (float)$ring[$j][0];
        $yj = (float)$ring[$j][1];
        $intersect = (($yi > $lat) !== ($yj > $lat))
            && ($lon < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1.0E-12) + $xi);
        if ($intersect) {
            $inside = !$inside;
        }
    }
    return $inside;
}

function ring_area_abs(array $ring): float
{
    $area = 0.0;
    for ($i = 0, $count = count($ring), $j = $count - 1; $i < $count; $j = $i++) {
        $area += ((float)$ring[$j][0] * (float)$ring[$i][1]) - ((float)$ring[$i][0] * (float)$ring[$j][1]);
    }
    return abs($area / 2.0);
}

function rings_to_geometry(array $rings): array
{
    if ($rings === []) {
        return ['type' => 'Polygon', 'coordinates' => []];
    }
    $containers = [];
    foreach ($rings as $i => $ring) {
        $sample = $ring[0] ?? null;
        $containers[$i] = [];
        if (!is_array($sample)) {
            continue;
        }
        foreach ($rings as $j => $candidate) {
            if ($i === $j || count($candidate) < 4) {
                continue;
            }
            if (point_in_ring($sample, $candidate)) {
                $containers[$i][] = $j;
            }
        }
    }

    $exteriors = [];
    foreach ($rings as $i => $ring) {
        if ((count($containers[$i]) % 2) === 0) {
            $exteriors[$i] = [$ring];
        }
    }
    if ($exteriors === []) {
        $exteriors[0] = [$rings[0]];
    }

    foreach ($rings as $i => $ring) {
        if (isset($exteriors[$i])) {
            continue;
        }
        $best = null;
        $bestArea = INF;
        foreach ($containers[$i] as $container) {
            if (!isset($exteriors[$container])) {
                continue;
            }
            $area = ring_area_abs($rings[$container]);
            if ($area < $bestArea) {
                $best = $container;
                $bestArea = $area;
            }
        }
        if ($best !== null) {
            $exteriors[$best][] = $ring;
        }
    }

    $polygons = array_values($exteriors);
    if (count($polygons) === 1) {
        return ['type' => 'Polygon', 'coordinates' => $polygons[0]];
    }
    return ['type' => 'MultiPolygon', 'coordinates' => $polygons];
}

function parse_shape_record(string $content, bool $includeGeometry): array
{
    if (strlen($content) < 4) {
        return ['shape_type' => 0, 'point_count' => 0, 'bbox' => null, 'geometry' => null];
    }
    $shapeType = read_le_int($content, 0);
    if (!in_array($shapeType, [5, 15, 25], true) || strlen($content) < 44) {
        return ['shape_type' => $shapeType, 'point_count' => 0, 'bbox' => null, 'geometry' => null];
    }
    $bbox = [
        read_le_double($content, 4),
        read_le_double($content, 12),
        read_le_double($content, 20),
        read_le_double($content, 28),
    ];
    $numParts = read_le_int($content, 36);
    $numPoints = read_le_int($content, 40);
    if (!$includeGeometry || $numParts < 1 || $numPoints < 1) {
        return ['shape_type' => $shapeType, 'point_count' => $numPoints, 'bbox' => $bbox, 'geometry' => null];
    }

    $parts = [];
    $offset = 44;
    for ($i = 0; $i < $numParts; $i++) {
        $parts[] = read_le_int($content, $offset);
        $offset += 4;
    }
    $pointsOffset = $offset;
    $points = [];
    for ($i = 0; $i < $numPoints; $i++) {
        $points[] = [read_le_double($content, $pointsOffset), read_le_double($content, $pointsOffset + 8)];
        $pointsOffset += 16;
    }

    $rings = [];
    for ($i = 0; $i < $numParts; $i++) {
        $start = $parts[$i];
        $end = $parts[$i + 1] ?? $numPoints;
        $ring = array_slice($points, $start, $end - $start);
        if (count($ring) >= 4) {
            if ($ring[0] !== $ring[count($ring) - 1]) {
                $ring[] = $ring[0];
            }
            $rings[] = $ring;
        }
    }

    return ['shape_type' => $shapeType, 'point_count' => $numPoints, 'bbox' => $bbox, 'geometry' => rings_to_geometry($rings)];
}

function read_shapes(string $path, callable $callback): void
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        fail("Unable to read SHP: {$path}");
    }
    fseek($fh, 100);
    $index = 0;
    while (!feof($fh)) {
        $header = fread($fh, 8);
        if ($header === '' || $header === false) {
            break;
        }
        if (strlen($header) !== 8) {
            break;
        }
        $length = read_be_int($header, 4) * 2;
        $content = $length > 0 ? fread($fh, $length) : '';
        if ($content === false || strlen($content) !== $length) {
            break;
        }
        $callback($index, $content);
        $index++;
    }
    fclose($fh);
}

function read_shape_records(string $shpPath, string $dbfPath, callable $callback): void
{
    $dbf = fopen($dbfPath, 'rb');
    if ($dbf === false) {
        fail("Unable to read DBF: {$dbfPath}");
    }
    [$recordCount, $recordLength, $fields] = read_dbf_schema($dbf, $dbfPath);

    $shp = fopen($shpPath, 'rb');
    if ($shp === false) {
        fclose($dbf);
        fail("Unable to read SHP: {$shpPath}");
    }
    fseek($shp, 100);

    for ($index = 0; $index < $recordCount; $index++) {
        $rawRecord = fread($dbf, $recordLength);
        if ($rawRecord === false || strlen($rawRecord) < $recordLength) {
            break;
        }
        $shapeHeader = fread($shp, 8);
        if ($shapeHeader === false || strlen($shapeHeader) !== 8) {
            break;
        }
        $shapeLength = read_be_int($shapeHeader, 4) * 2;
        $content = $shapeLength > 0 ? fread($shp, $shapeLength) : '';
        if ($content === false || strlen($content) !== $shapeLength) {
            break;
        }
        $callback($index, parse_dbf_record($rawRecord, $fields), $content);
    }

    fclose($shp);
    fclose($dbf);
}

function locate_wgs84_shapefile(string $extractDir): array
{
    $candidates = glob($extractDir . DIRECTORY_SEPARATOR . '*.shp.shp') ?: [];
    foreach ($candidates as $shp) {
        $base = substr($shp, 0, -4);
        $prj = $base . '.prj';
        $dbf = $base . '.dbf';
        if (!is_file($dbf) || !is_file($prj)) {
            continue;
        }
        $projection = file_get_contents($prj);
        if ($projection !== false && stripos($projection, 'GEOGCS') !== false && stripos($projection, 'PROJCS') === false) {
            return [$shp, $dbf, $prj];
        }
    }
    fail("No extracted WGS84 barangay shapefile found in {$extractDir}.");
}

function feature_bbox(array $bbox): array
{
    return [
        'min_lon' => $bbox[0],
        'min_lat' => $bbox[1],
        'max_lon' => $bbox[2],
        'max_lat' => $bbox[3],
    ];
}

function write_json_file(string $path, array $data): void
{
    ensure_dir(dirname($path));
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        fail("Unable to write JSON file: {$path}");
    }
}

if (!extension_loaded('curl') || !extension_loaded('zip')) {
    fail('PHP curl and zip extensions are required.');
}

$started = date('c');
$args = parse_args($argv);
if ($args['help']) {
    echo usage();
    exit(0);
}
$scope = strtolower(trim((string)$args['deployment-scope']));
if ($scope === 'other') {
    $scope = 'barangay';
}
if ($scope !== '' && !in_array($scope, ['barangay', 'city', 'province', 'region'], true)) {
    fail('--deployment-scope must be barangay, city, province, region, or other.');
}
if (
    (string)$args['city'] === ''
    && (string)$args['barangay'] === ''
    && (string)$args['brgy-code'] === ''
    && (string)$args['citymun-code'] === ''
    && (string)$args['prov-code'] === ''
    && (string)$args['reg-code'] === ''
) {
    fail('Provide --city, --barangay, --brgy-code, --citymun-code, --prov-code, or --reg-code.');
}

$workDir = (string)$args['work-dir'];
ensure_dir($workDir);
$sourceZip = resolve_boundary_source_zip($args, $workDir);
$adm3Csv = (string)$args['adm3-csv'] !== '' ? (string)$args['adm3-csv'] : default_boundary_source_file('PH_Adm3_MuniCities.csv', $workDir);
$adm4Csv = (string)$args['adm4-csv'] !== '' ? (string)$args['adm4-csv'] : default_boundary_source_file('PH_Adm4_BgySubMuns.csv', $workDir);
$scopeSlug = $scope !== '' ? $scope : 'boundary';
$codeSlug = (string)$args['brgy-code'] !== ''
    ? (string)$args['brgy-code']
    : ((string)$args['citymun-code'] !== ''
        ? (string)$args['citymun-code']
        : ((string)$args['prov-code'] !== '' ? (string)$args['prov-code'] : (string)$args['reg-code']));
$citySlug = (string)$args['city'] !== '' ? slugify((string)$args['city']) : $scopeSlug . '-' . slugify($codeSlug);
$barangaySlug = (string)$args['barangay'] !== '' ? '-' . slugify((string)$args['barangay']) : '';
$output = (string)$args['output'] !== '' ? (string)$args['output'] : $workDir . DIRECTORY_SEPARATOR . $citySlug . $barangaySlug . '-barangays.geojson';
$indexPath = (string)$args['index'] !== '' ? (string)$args['index'] : preg_replace('/\.geojson$/i', '', $output) . '.index.json';
$reportPath = (string)$args['report'] !== '' ? (string)$args['report'] : $workDir . DIRECTORY_SEPARATOR . 'prepare-boundaries-report.json';

$downloaded = [];
if (!$args['no-download']) {
    if (download_file(PSGC_ZIP_URL, $sourceZip, (bool)$args['force-download'])) {
        $downloaded[] = $sourceZip;
    }
    if (download_file(PSGC_REPO_RAW . 'PH_Adm3_MuniCities.csv', $adm3Csv, (bool)$args['force-download'])) {
        $downloaded[] = $adm3Csv;
    }
    if (download_file(PSGC_REPO_RAW . 'PH_Adm4_BgySubMuns.csv', $adm4Csv, (bool)$args['force-download'])) {
        $downloaded[] = $adm4Csv;
    }
}
foreach ([$sourceZip, $adm3Csv, $adm4Csv] as $required) {
    if (!is_file($required)) {
        fail("Required PSGC source file not found: {$required}");
    }
}

$extractDir = $workDir . DIRECTORY_SEPARATOR . 'PH_Adm4_BgySubMuns';
if (!is_dir($extractDir) || (count(glob($extractDir . DIRECTORY_SEPARATOR . '*.shp.shp') ?: []) === 0)) {
    extract_zip($sourceZip, $extractDir);
}
[$shpPath, $dbfPath] = locate_wgs84_shapefile($extractDir);

$adm3ByCode = [];
foreach (read_csv_rows($adm3Csv) as $row) {
    $name = trim((string)($row['adm3_en'] ?? ''));
    $alias = stripos($name, 'City of ') === 0 ? trim(substr($name, 8)) . ' City' : $name;
    $row['city_name'] = $alias;
    $row['citymun_name'] = $name;
    $adm3ByCode[normalize_code((string)($row['adm3_psgc'] ?? ''))] = $row;
}

$adm4ByCode = [];
foreach (read_csv_rows($adm4Csv) as $row) {
    $code = normalize_code((string)($row['adm4_psgc'] ?? ''));
    $city = $adm3ByCode[normalize_code((string)($row['adm3_psgc'] ?? ''))] ?? [];
    $adm4ByCode[$code] = $city + $row + [
        'brgy_code' => $code,
        'brgy_name' => trim((string)($row['adm4_en'] ?? '')),
    ];
}

$legacyToCurrent = [];
read_shape_records($shpPath, $dbfPath, function (int $index, array $record, string $content) use ($adm4ByCode, &$legacyToCurrent): void {
    $shape = parse_shape_record($content, false);
    if (($shape['point_count'] ?? 0) > 0) {
        return;
    }
    $psgc = normalize_dbf_code($record['psgc_code'] ?? '');
    $corr = normalize_dbf_code($record['corr_code'] ?? '');
    if ($psgc !== '' && $corr !== '' && isset($adm4ByCode[$psgc])) {
        $legacyToCurrent[$corr] = $adm4ByCode[$psgc];
    }
});

$targetCity = normalize_text((string)$args['city']);
$targetBarangay = normalize_text((string)$args['barangay']);
$targetCode = normalize_code((string)$args['brgy-code']);
$targetCitymunCode = normalize_code((string)$args['citymun-code']);
$targetProvCode = normalize_code((string)$args['prov-code']);
$targetRegCode = normalize_code((string)$args['reg-code']);
$features = [];
$index = [];

read_shape_records($shpPath, $dbfPath, function (int $recordIndex, array $record, string $content) use ($args, $adm4ByCode, $adm3ByCode, $legacyToCurrent, $targetCity, $targetBarangay, $targetCode, $targetCitymunCode, $targetProvCode, $targetRegCode, &$features, &$index): void {
    $psgc = normalize_dbf_code($record['psgc_code'] ?? '');
    $shapeSummary = parse_shape_record($content, false);
    if (($shapeSummary['point_count'] ?? 0) < 1) {
        return;
    }

    $meta = $adm4ByCode[$psgc] ?? null;
    $legacy = psgc_compact_legacy($psgc);
    if (isset($legacyToCurrent[$legacy])) {
        $meta = $legacyToCurrent[$legacy];
    }
    if ($meta === null) {
        $adm3Pcode = (string)($record['adm3_pcode'] ?? '');
        $adm3Digits = normalize_code($adm3Pcode);
        $city = $adm3ByCode[$adm3Digits . '000'] ?? $adm3ByCode[$adm3Digits] ?? [];
        $meta = $city + [
            'adm4_psgc' => $psgc,
            'brgy_code' => $psgc,
            'adm4_en' => trim((string)($record['adm4_en'] ?? ($record['name'] ?? ''))),
            'brgy_name' => trim((string)($record['adm4_en'] ?? ($record['name'] ?? ''))),
        ];
    }

    $cityValues = [
        normalize_text((string)($meta['city_name'] ?? '')),
        normalize_text((string)($meta['citymun_name'] ?? '')),
        normalize_text((string)($meta['adm3_en'] ?? '')),
    ];
    if ($targetCity !== '' && !in_array($targetCity, $cityValues, true)) {
        return;
    }

    $brgyName = (string)($meta['brgy_name'] ?? ($meta['adm4_en'] ?? ''));
    $brgyCode = normalize_code((string)($meta['brgy_code'] ?? ($meta['adm4_psgc'] ?? $psgc)));
    if ($targetBarangay !== '' && normalize_text($brgyName) !== $targetBarangay) {
        return;
    }
    if ($targetCode !== '' && !code_matches($brgyCode, $targetCode) && !code_matches($psgc, $targetCode)) {
        return;
    }
    $sourceCitymunCode = strlen($psgc) >= 6 ? substr($psgc, 0, 6) : '';
    if ($targetCitymunCode !== '' && !code_matches((string)($meta['adm3_psgc'] ?? ''), $targetCitymunCode) && !code_matches($sourceCitymunCode, $targetCitymunCode)) {
        return;
    }
    if ($targetProvCode !== '' && normalize_code((string)($meta['adm2_psgc'] ?? '')) !== $targetProvCode) {
        return;
    }
    if ($targetRegCode !== '' && normalize_code((string)($meta['adm1_psgc'] ?? '')) !== $targetRegCode) {
        return;
    }

    $shape = parse_shape_record($content, true);
    if ($shape['geometry'] === null) {
        return;
    }

    $properties = $meta;
    $properties['brgy_code'] = $brgyCode;
    $properties['brgy_name'] = $brgyName;
    $properties['city_name'] = (string)($meta['city_name'] ?? $args['city'] ?? '');
    $properties['citymun_name'] = (string)($meta['citymun_name'] ?? ($meta['adm3_en'] ?? ''));
    $properties['source_psgc_code'] = $psgc;

    $features[] = [
        'type' => 'Feature',
        'properties' => $properties,
        'geometry' => $shape['geometry'],
    ];
    $index[] = [
        'brgy_code' => $brgyCode,
        'brgy_name' => $brgyName,
        'city_name' => $properties['city_name'],
        'citymun_name' => $properties['citymun_name'],
        'source_psgc_code' => $psgc,
        'bbox' => feature_bbox($shapeSummary['bbox']),
    ];
});

usort($features, static fn(array $a, array $b): int => strcmp((string)$a['properties']['brgy_code'], (string)$b['properties']['brgy_code']));
usort($index, static fn(array $a, array $b): int => strcmp((string)$a['brgy_code'], (string)$b['brgy_code']));

if ($features === []) {
    fail('No matching boundaries found. Check --city, --barangay, and --brgy-code.');
}

write_json_file($output, ['type' => 'FeatureCollection', 'features' => $features]);
write_json_file($indexPath, [
    'schema_version' => 1,
    'city' => (string)$args['city'],
    'barangay' => (string)$args['barangay'],
    'brgy_code' => (string)$args['brgy-code'],
    'count' => count($index),
    'barangays' => $index,
]);

$report = [
    'schema_version' => 1,
    'tool' => 'pbb-mapserver-boundary-prep',
    'version' => BOUNDARY_PREP_VERSION,
    'started_at' => $started,
    'finished_at' => date('c'),
    'status' => 'success',
    'source' => [
        'repository' => 'altcoder/philippines-psgc-shapefiles',
        'source_zip' => $sourceZip,
        'adm3_csv' => $adm3Csv,
        'adm4_csv' => $adm4Csv,
        'shapefile' => $shpPath,
        'downloaded' => $downloaded,
    ],
    'filters' => [
        'deployment_scope' => $scope !== '' ? $scope : null,
        'city' => (string)$args['city'],
        'barangay' => (string)$args['barangay'],
        'brgy_code' => (string)$args['brgy-code'],
        'citymun_code' => (string)$args['citymun-code'],
        'prov_code' => (string)$args['prov-code'],
        'reg_code' => (string)$args['reg-code'],
    ],
    'outputs' => [
        'geojson' => $output,
        'index' => $indexPath,
    ],
    'features' => count($features),
];
write_json_file($reportPath, $report);

echo json_encode([
    'status' => 'success',
    'features' => count($features),
    'geojson' => $output,
    'index' => $indexPath,
    'report' => $reportPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
