<?php

declare(strict_types=1);

function usage(): string
{
    return <<<TXT
PBB MapServer province boundary pack builder

Usage:
  php tools/build-boundary-province-pack.php --province-code 0722 --aliases 0722,7022,7306,730600000 --label "Cebu" --output C:\\pbb\\kit-setup\\packages\\bundled\\pbb-mapserver-boundaries-province-0722.zip

Options:
  --province-code  Canonical pack code used in output paths, for example 0722.
  --aliases        Comma-separated code prefixes that select shapefile records.
  --label          Human-readable province label.
  --source-zip     Source WGS84 shapefile ZIP. Default: resources\\boundaries\\PH_Adm4_BgySubMuns.shp.zip
  --output         Trusted pack ZIP path.
  --work-dir       Temporary build directory. Default: storage\\boundaries\\packs\\build-<province-code>
  --help           Show this help.

TXT;
}

function fail_pack(string $message, int $code = 2): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function parse_pack_args(array $argv): array
{
    $args = [
        'province-code' => '',
        'aliases' => '',
        'label' => '',
        'source-zip' => '',
        'output' => '',
        'work-dir' => '',
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
            continue;
        }
        if (strncmp($arg, '--', 2) !== 0) {
            fail_pack("Unexpected argument: {$arg}");
        }
        $key = substr($arg, 2);
        if (!array_key_exists($key, $args)) {
            fail_pack("Unknown option: {$arg}");
        }
        $i++;
        if (!isset($argv[$i])) {
            fail_pack("Missing value for {$arg}");
        }
        $args[$key] = $argv[$i];
    }

    return $args;
}

function ensure_pack_dir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail_pack("Unable to create directory: {$dir}");
    }
}

function normalize_pack_code(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function read_le_int_pack(string $bytes, int $offset): int
{
    return unpack('V', substr($bytes, $offset, 4))[1];
}

function read_be_int_pack(string $bytes, int $offset): int
{
    return unpack('N', substr($bytes, $offset, 4))[1];
}

function read_le_double_pack(string $bytes, int $offset): float
{
    return unpack('e', substr($bytes, $offset, 8))[1];
}

function write_be_int_pack(int $value): string
{
    return pack('N', $value);
}

function write_le_int_pack(int $value): string
{
    return pack('V', $value);
}

function write_le_double_pack(float $value): string
{
    return pack('e', $value);
}

function put_bytes(string $bytes, int $offset, string $replacement): string
{
    return substr($bytes, 0, $offset) . $replacement . substr($bytes, $offset + strlen($replacement));
}

function extract_pack_zip(string $zipPath, string $targetDir): void
{
    ensure_pack_dir($targetDir);
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        fail_pack("Unable to open ZIP: {$zipPath}");
    }
    if (!$zip->extractTo($targetDir)) {
        $zip->close();
        fail_pack("Unable to extract ZIP: {$zipPath}");
    }
    $zip->close();
}

function locate_pack_shapefile(string $dir): array
{
    $candidates = glob($dir . DIRECTORY_SEPARATOR . '*.shp.shp') ?: [];
    foreach ($candidates as $shp) {
        $base = substr($shp, 0, -4);
        $dbf = $base . '.dbf';
        $prj = $base . '.prj';
        $cpg = $base . '.cpg';
        if (!is_file($dbf) || !is_file($prj)) {
            continue;
        }
        $projection = (string)file_get_contents($prj);
        if (stripos($projection, 'GEOGCS') !== false && stripos($projection, 'PROJCS') === false) {
            return [$shp, $dbf, $prj, is_file($cpg) ? $cpg : ''];
        }
    }

    fail_pack("No WGS84 shapefile found in {$dir}");
}

function read_dbf_header_pack($fh, string $path): array
{
    $header = fread($fh, 32);
    if ($header === false || strlen($header) !== 32) {
        fail_pack("Invalid DBF header: {$path}");
    }

    $recordCount = unpack('V', substr($header, 4, 4))[1];
    $headerLength = unpack('v', substr($header, 8, 2))[1];
    $recordLength = unpack('v', substr($header, 10, 2))[1];
    fseek($fh, 0);
    $headerBytes = fread($fh, $headerLength);
    if ($headerBytes === false || strlen($headerBytes) !== $headerLength) {
        fail_pack("Invalid DBF header bytes: {$path}");
    }

    $fields = [];
    $offset = 32;
    while ($offset < $headerLength - 1) {
        $descriptor = substr($headerBytes, $offset, 32);
        if ($descriptor === '' || ord($descriptor[0]) === 0x0D) {
            break;
        }
        $fields[] = [
            'name' => rtrim(substr($descriptor, 0, 11), "\0 "),
            'type' => $descriptor[11],
            'length' => ord($descriptor[16]),
        ];
        $offset += 32;
    }

    return [$recordCount, $headerLength, $recordLength, $headerBytes, $fields];
}

function parse_dbf_record_pack(string $raw, array $fields): array
{
    if ($raw === '' || $raw[0] === '*') {
        return [];
    }

    $offset = 1;
    $record = [];
    foreach ($fields as $field) {
        $value = trim(substr($raw, $offset, $field['length']));
        $offset += $field['length'];
        $converted = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $value);
        $record[$field['name']] = $converted === false ? $value : trim($converted);
    }

    return $record;
}

function shape_bbox(string $content): array
{
    if (strlen($content) < 36) {
        return [INF, INF, -INF, -INF];
    }

    return [
        read_le_double_pack($content, 4),
        read_le_double_pack($content, 12),
        read_le_double_pack($content, 20),
        read_le_double_pack($content, 28),
    ];
}

function filter_shapefile(string $sourceShp, string $sourceDbf, string $sourcePrj, string $sourceCpg, string $targetDir, array $prefixes): array
{
    ensure_pack_dir($targetDir);
    $targetBase = $targetDir . DIRECTORY_SEPARATOR . 'BgySubMuns.shp';
    $targetShp = $targetBase . '.shp';
    $targetShx = $targetBase . '.shx';
    $targetDbf = $targetBase . '.dbf';
    $targetPrj = $targetBase . '.prj';
    $targetCpg = $targetBase . '.cpg';

    $dbf = fopen($sourceDbf, 'rb');
    $shp = fopen($sourceShp, 'rb');
    if ($dbf === false || $shp === false) {
        fail_pack('Unable to open source shapefile components.');
    }

    [$recordCount, , $recordLength, $dbfHeader, $fields] = read_dbf_header_pack($dbf, $sourceDbf);
    $shpHeader = fread($shp, 100);
    if ($shpHeader === false || strlen($shpHeader) !== 100) {
        fail_pack("Invalid SHP header: {$sourceShp}");
    }

    $selected = [];
    $bbox = [INF, INF, -INF, -INF];
    for ($i = 0; $i < $recordCount; $i++) {
        $rawDbf = fread($dbf, $recordLength);
        $shapeHeader = fread($shp, 8);
        if ($rawDbf === false || strlen($rawDbf) < $recordLength || $shapeHeader === false || strlen($shapeHeader) !== 8) {
            break;
        }
        $shapeLengthWords = read_be_int_pack($shapeHeader, 4);
        $content = fread($shp, $shapeLengthWords * 2);
        if ($content === false || strlen($content) !== $shapeLengthWords * 2) {
            break;
        }

        $record = parse_dbf_record_pack($rawDbf, $fields);
        $psgc = normalize_pack_code((string)($record['psgc_code'] ?? $record['PSGC_CODE'] ?? ''));
        $matches = false;
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($psgc, $prefix)) {
                $matches = true;
                break;
            }
        }
        if (!$matches) {
            continue;
        }

        $shapeBbox = shape_bbox($content);
        $bbox[0] = min($bbox[0], $shapeBbox[0]);
        $bbox[1] = min($bbox[1], $shapeBbox[1]);
        $bbox[2] = max($bbox[2], $shapeBbox[2]);
        $bbox[3] = max($bbox[3], $shapeBbox[3]);
        $selected[] = ['dbf' => $rawDbf, 'content' => $content, 'length_words' => $shapeLengthWords];
    }

    fclose($dbf);
    fclose($shp);

    if ($selected === []) {
        fail_pack('No shapefile records matched the requested province aliases.');
    }

    $shpBytes = $shpHeader;
    $shxBytes = $shpHeader;
    $offsetWords = 50;
    foreach ($selected as $index => $record) {
        $recordNumber = $index + 1;
        $shpBytes .= write_be_int_pack($recordNumber) . write_be_int_pack($record['length_words']) . $record['content'];
        $shxBytes .= write_be_int_pack($offsetWords) . write_be_int_pack($record['length_words']);
        $offsetWords += 4 + $record['length_words'];
    }

    $shpBytes = put_bytes($shpBytes, 24, write_be_int_pack((int)(strlen($shpBytes) / 2)));
    $shxBytes = put_bytes($shxBytes, 24, write_be_int_pack((int)(strlen($shxBytes) / 2)));
    foreach ([36 => $bbox[0], 44 => $bbox[1], 52 => $bbox[2], 60 => $bbox[3]] as $offset => $value) {
        $shpBytes = put_bytes($shpBytes, $offset, write_le_double_pack((float)$value));
        $shxBytes = put_bytes($shxBytes, $offset, write_le_double_pack((float)$value));
    }

    $dbfHeader = put_bytes($dbfHeader, 4, write_le_int_pack(count($selected)));
    $dbfBytes = $dbfHeader . implode('', array_column($selected, 'dbf')) . "\x1A";

    file_put_contents($targetShp, $shpBytes);
    file_put_contents($targetShx, $shxBytes);
    file_put_contents($targetDbf, $dbfBytes);
    copy($sourcePrj, $targetPrj);
    if ($sourceCpg !== '') {
        copy($sourceCpg, $targetCpg);
    }

    return [
        'records' => count($selected),
        'bbox' => ['min_lon' => $bbox[0], 'min_lat' => $bbox[1], 'max_lon' => $bbox[2], 'max_lat' => $bbox[3]],
        'files' => array_values(array_filter([$targetShp, $targetShx, $targetDbf, $targetPrj, is_file($targetCpg) ? $targetCpg : ''])),
    ];
}

$args = parse_pack_args($argv);
if ($args['help']) {
    echo usage();
    exit(0);
}

$root = dirname(__DIR__);
$provinceCode = normalize_pack_code((string)$args['province-code']);
if ($provinceCode === '') {
    fail_pack('Provide --province-code.');
}
$aliases = array_values(array_unique(array_filter(array_map('normalize_pack_code', explode(',', (string)$args['aliases'])))));
if ($aliases === []) {
    $aliases = [$provinceCode];
}
$label = trim((string)$args['label']) ?: $provinceCode;
$sourceZip = (string)$args['source-zip'] !== ''
    ? (string)$args['source-zip']
    : $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . 'PH_Adm4_BgySubMuns.shp.zip';
$output = (string)$args['output'];
if ($output === '') {
    fail_pack('Provide --output.');
}
$workDir = (string)$args['work-dir'] !== ''
    ? (string)$args['work-dir']
    : $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . 'packs' . DIRECTORY_SEPARATOR . 'build-' . $provinceCode;

if (!is_file($sourceZip)) {
    fail_pack("Source ZIP not found: {$sourceZip}");
}
if (is_dir($workDir)) {
    $realWorkDir = realpath($workDir);
    $storageRoot = realpath($root . DIRECTORY_SEPARATOR . 'storage') ?: '';
    if ($realWorkDir !== false && $storageRoot !== '' && str_starts_with($realWorkDir, $storageRoot)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
ensure_pack_dir($workDir);

$extractDir = $workDir . DIRECTORY_SEPARATOR . 'extract';
$shapeStage = $workDir . DIRECTORY_SEPARATOR . 'shape';
$packStage = $workDir . DIRECTORY_SEPARATOR . 'pack';
extract_pack_zip($sourceZip, $extractDir);
[$sourceShp, $sourceDbf, $sourcePrj, $sourceCpg] = locate_pack_shapefile($extractDir);
$filter = filter_shapefile($sourceShp, $sourceDbf, $sourcePrj, $sourceCpg, $shapeStage, $aliases);

$nestedZip = $workDir . DIRECTORY_SEPARATOR . 'BgySubMuns.shp.zip';
if (is_file($nestedZip)) {
    unlink($nestedZip);
}
$zip = new ZipArchive();
if ($zip->open($nestedZip, ZipArchive::CREATE) !== true) {
    fail_pack("Unable to create nested ZIP: {$nestedZip}");
}
foreach ($filter['files'] as $file) {
    $zip->addFile($file, basename($file));
}
$zip->close();

$packPath = $packStage . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . 'provinces' . DIRECTORY_SEPARATOR . $provinceCode;
ensure_pack_dir($packPath);
copy($nestedZip, $packPath . DIRECTORY_SEPARATOR . 'BgySubMuns.shp.zip');

$metadata = [
    'schema_version' => 1,
    'kind' => 'pbb-mapserver-boundary-pack',
    'scope' => 'province',
    'province_code' => $provinceCode,
    'label' => $label,
    'aliases' => $aliases,
    'source' => [
        'zip' => $sourceZip,
        'selected_prefixes' => $aliases,
    ],
    'outputs' => [
        'source_zip' => 'resources/boundaries/provinces/' . $provinceCode . '/BgySubMuns.shp.zip',
    ],
    'records' => $filter['records'],
    'bbox' => $filter['bbox'],
    'built_at' => date('c'),
];
$metadata['outputs']['source_zip_sha256'] = hash_file('sha256', $packPath . DIRECTORY_SEPARATOR . 'BgySubMuns.shp.zip');
$metadata['outputs']['source_zip_bytes'] = filesize($packPath . DIRECTORY_SEPARATOR . 'BgySubMuns.shp.zip');
file_put_contents($packPath . DIRECTORY_SEPARATOR . 'pack.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

ensure_pack_dir(dirname($output));
if (is_file($output)) {
    unlink($output);
}
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE) !== true) {
    fail_pack("Unable to create pack ZIP: {$output}");
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packStage, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($packStage) + 1));
    $zip->addFile($path, $relative);
}
$zip->close();

$metadata['outputs']['pack_zip'] = $output;
$metadata['outputs']['pack_zip_sha256'] = hash_file('sha256', $output);
$metadata['outputs']['pack_zip_bytes'] = filesize($output);

echo json_encode(['status' => 'success'] + $metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
