<?php

declare(strict_types=1);

const MAPSERVER_DATA_PREP_VERSION = 1;

function fail(string $message, int $code = 2): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function parse_args(array $argv): array
{
    $args = [
        'mode' => '',
        'config' => '',
        'report' => '',
        'dry-run' => false,
        'verbose' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run') {
            $args['dry-run'] = true;
            continue;
        }
        if ($arg === '--verbose') {
            $args['verbose'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
            continue;
        }
        if (!in_array($arg, ['--mode', '--config', '--report'], true)) {
            fail("Unknown option: {$arg}");
        }
        $i++;
        if (!isset($argv[$i])) {
            fail("Missing value for {$arg}");
        }
        $args[substr($arg, 2)] = $argv[$i];
    }

    return $args;
}

function usage(): string
{
    return <<<TXT
PBB MapServer Data Prep: Prepare Data

Usage:
  php tools/data-prep/prepare.php --mode initial --config config.json --report report.json --dry-run

Modes:
  initial, repair, refresh, demo

TXT;
}

function read_json_file(string $path): array
{
    if ($path === '' || !is_file($path)) {
        fail("JSON file not found: {$path}");
    }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        fail("Invalid JSON file: {$path}");
    }
    return $data;
}

function config_value(array $config, array $path, mixed $default = null): mixed
{
    $value = $config;
    foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }
    return $value;
}

function first_config_section(array $config): array
{
    foreach ([
        ['mapserver', 'data_prep', 'prepare'],
        ['mapserver', 'populate'],
    ] as $path) {
        $section = config_value($config, $path, []);
        if (is_array($section) && $section !== []) {
            return $section;
        }
    }
    return [];
}

function write_json_file(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail("Unable to create report directory: {$dir}");
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        fail("Unable to write report: {$path}");
    }
}

function append_option(array &$command, string $name, mixed $value): void
{
    if ($value === null || $value === '' || $value === false) {
        return;
    }
    $command[] = '--' . $name;
    $command[] = (string)$value;
}

function first_value(array $source, array $keys, mixed $default = ''): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
            return $source[$key];
        }
    }
    return $default;
}

function bool_config(mixed $value, bool $default): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    if (is_string($value)) {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
    return $default;
}

function app_root(): string
{
    return dirname(__DIR__, 2);
}

function default_boundary_work_dir(): string
{
    return app_root() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'boundaries';
}

function is_absolute_path(string $path): bool
{
    return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\') || str_starts_with($path, '/');
}

function resolve_app_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return $path;
    }
    if (is_absolute_path($path)) {
        return $path;
    }
    return app_root() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function boundary_source_files(): array
{
    $sourceDir = app_root() . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'boundaries';
    $files = [
        'source_zip' => $sourceDir . DIRECTORY_SEPARATOR . 'PH_Adm4_BgySubMuns.shp.zip',
        'adm3_csv' => $sourceDir . DIRECTORY_SEPARATOR . 'PH_Adm3_MuniCities.csv',
        'adm4_csv' => $sourceDir . DIRECTORY_SEPARATOR . 'PH_Adm4_BgySubMuns.csv',
    ];

    return [
        'source_dir' => $sourceDir,
        'available' => is_dir($sourceDir),
        'files' => $files,
        'missing' => [],
    ];
}

function resolve_boundary_source_files(): array
{
    $source = boundary_source_files();
    foreach ($source['files'] as $label => $path) {
        if (!is_file($path)) {
            $source['missing'][] = $label;
        }
    }
    return $source;
}

function run_command(array $command): array
{
    $escaped = array_map(static fn(string $part): string => escapeshellarg($part), $command);
    $output = [];
    $exitCode = 0;
    exec(implode(' ', $escaped) . ' 2>&1', $output, $exitCode);
    return [$exitCode, $output];
}

function has_coverage_input(array $prepare): bool
{
    return first_value($prepare, ['source_geojson', 'boundary_geojson', 'geojson']) !== ''
        || first_value($prepare, ['bbox']) !== ''
        || first_value($prepare, ['center']) !== '';
}

function deployment_scope(array $prepare): string
{
    $scope = strtolower(trim((string)first_value($prepare, ['deployment_scope', 'scope'], 'barangay')));
    if ($scope === 'other' || $scope === '') {
        return 'barangay';
    }
    if (!in_array($scope, ['barangay', 'city', 'province', 'region'], true)) {
        fail('mapserver.data_prep.prepare.deployment_scope must be barangay, city, province, region, or other.');
    }
    return $scope;
}

function scope_code(array $prepare, string $scope): string
{
    return match ($scope) {
        'city' => (string)first_value($prepare, ['citymun_code', 'city_code', 'municipality_code', 'psgc_code']),
        'province' => (string)first_value($prepare, ['prov_code', 'province_code', 'psgc_code']),
        'region' => (string)first_value($prepare, ['reg_code', 'region_code', 'psgc_code']),
        default => (string)first_value($prepare, ['brgy_code', 'barangay_code', 'psgc_code']),
    };
}

function resolve_boundary_geojson(array &$prepare, string $reportPath): array
{
    $scope = deployment_scope($prepare);
    $code = scope_code($prepare, $scope);
    $brgyCode = $scope === 'barangay' ? $code : '';
    $citymunCode = $scope === 'city' ? $code : '';
    $provCode = $scope === 'province' ? $code : '';
    $regCode = $scope === 'region' ? $code : '';
    $barangay = (string)first_value($prepare, ['barangay', 'barangay_name']);
    if (has_coverage_input($prepare) || ($code === '' && $barangay === '')) {
        return ['status' => 'skipped', 'report' => '', 'output' => '', 'errors' => []];
    }

    $workDir = resolve_app_path((string)first_value($prepare, ['boundary_work_dir', 'work_dir'], default_boundary_work_dir()));
    $boundarySources = resolve_boundary_source_files();
    $boundaryReport = dirname($reportPath) . DIRECTORY_SEPARATOR . 'mapserver-boundary-prepare-' . date('YmdHis') . '.json';
    $slugCode = $code !== '' ? preg_replace('/\D+/', '', $code) : preg_replace('/[^a-z0-9]+/i', '-', strtolower($barangay));
    $output = $workDir . DIRECTORY_SEPARATOR . 'mapserver-data-prep-' . $scope . '-' . $slugCode . '.geojson';

    $command = [
        PHP_BINARY,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'prepare-boundaries.php',
        '--deployment-scope',
        $scope,
        '--work-dir',
        $workDir,
        '--output',
        $output,
        '--report',
        $boundaryReport,
    ];
    if ($boundarySources['missing'] === []) {
        append_option($command, 'source-zip', $boundarySources['files']['source_zip']);
        append_option($command, 'adm3-csv', $boundarySources['files']['adm3_csv']);
        append_option($command, 'adm4-csv', $boundarySources['files']['adm4_csv']);
    }
    append_option($command, 'brgy-code', $brgyCode);
    append_option($command, 'citymun-code', $citymunCode);
    append_option($command, 'prov-code', $provCode);
    append_option($command, 'reg-code', $regCode);
    append_option($command, 'barangay', $barangay);
    append_option($command, 'city', first_value($prepare, ['city', 'city_name', 'municipality', 'municipality_name']));
    if (!empty($prepare['no_download']) || ($boundarySources['missing'] === [] && empty($prepare['force_download']))) {
        $command[] = '--no-download';
    }
    if (!empty($prepare['force_download'])) {
        $command[] = '--force-download';
    }

    [$exitCode, $outputLines] = run_command($command);
    if ($exitCode !== 0) {
        return ['status' => 'failed', 'report' => $boundaryReport, 'output' => $output, 'errors' => $outputLines, 'boundary_sources' => $boundarySources];
    }

    $prepare['source_geojson'] = $output;
    if ($brgyCode !== '') {
        $prepare['brgy_code'] = $brgyCode;
    }
    if ($citymunCode !== '') {
        $prepare['citymun_code'] = $citymunCode;
    }
    if ($provCode !== '') {
        $prepare['prov_code'] = $provCode;
    }
    if ($regCode !== '') {
        $prepare['reg_code'] = $regCode;
    }
    if ($barangay !== '') {
        $prepare['barangay'] = $barangay;
    }
    return ['status' => 'success', 'report' => $boundaryReport, 'output' => $output, 'errors' => [], 'boundary_sources' => $boundarySources];
}

$started = date('c');
$args = parse_args($argv);
if ($args['help']) {
    echo usage();
    exit(0);
}
if (!in_array($args['mode'], ['initial', 'repair', 'refresh', 'demo'], true)) {
    fail('Provide --mode initial|repair|refresh|demo.');
}
if ($args['config'] === '' || $args['report'] === '') {
    fail('Provide --config and --report.');
}

$config = read_json_file((string)$args['config']);
$prepare = first_config_section($config);
foreach (['curl_ca_bundle', 'ca_bundle'] as $key) {
    if (!array_key_exists($key, $prepare) || $prepare[$key] === '') {
        $value = config_value($config, ['mapserver', $key], '');
        if ($value !== '') {
            $prepare[$key] = $value;
        }
    }
}
if (!array_key_exists('curl_ssl_verify', $prepare)) {
    $prepare['curl_ssl_verify'] = config_value($config, ['mapserver', 'curl_ssl_verify'], null);
}
$baseUrl = (string)($prepare['base_url'] ?? config_value($config, ['app', 'app_url'], 'http://localhost/mapserver'));
$childReport = dirname((string)$args['report']) . DIRECTORY_SEPARATOR . 'mapserver-populate-child-' . date('YmdHis') . '.json';
$boundary = resolve_boundary_geojson($prepare, (string)$args['report']);
if ($boundary['status'] === 'failed') {
    $report = [
        'schema_version' => 1,
        'app' => 'pbb-mapserver',
        'tool' => 'data_prep_prepare',
        'version' => MAPSERVER_DATA_PREP_VERSION,
        'mode' => (string)$args['mode'],
        'dry_run' => (bool)$args['dry-run'],
        'status' => 'failed',
        'summary' => 'MapServer could not resolve barangay coverage for tile cache preparation.',
        'started_at' => $started,
        'finished_at' => date('c'),
        'sources' => [],
        'results' => [],
        'outputs' => [],
        'details' => ['boundary_report' => $boundary['report'], 'boundary_output' => $boundary['output']],
        'warnings' => [],
        'errors' => array_slice($boundary['errors'], 0, 25),
    ];
    write_json_file((string)$args['report'], $report);
    echo json_encode(['status' => 'failed', 'report' => (string)$args['report']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

$command = [
    PHP_BINARY,
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'populate-tiles.php',
    '--base-url',
    $baseUrl,
    '--report',
    $childReport,
];
if ($args['dry-run']) {
    $command[] = '--dry-run';
}
foreach ([
    'source_geojson' => 'source-geojson',
    'boundary_geojson' => 'source-geojson',
    'geojson' => 'source-geojson',
    'brgy_code' => 'brgy-code',
    'barangay_code' => 'brgy-code',
    'psgc_code' => 'brgy-code',
    'citymun_code' => 'citymun-code',
    'city_code' => 'citymun-code',
    'municipality_code' => 'citymun-code',
    'prov_code' => 'prov-code',
    'province_code' => 'prov-code',
    'reg_code' => 'reg-code',
    'region_code' => 'reg-code',
    'barangay' => 'barangay',
    'barangay_name' => 'barangay',
    'city' => 'city',
    'city_name' => 'city',
    'municipality' => 'city',
    'municipality_name' => 'city',
    'bbox' => 'bbox',
    'center' => 'center',
    'radius_km' => 'radius-km',
    'zooms' => 'zooms',
    'types' => 'types',
    'max_tiles' => 'max-tiles',
    'limit' => 'limit',
    'timeout' => 'timeout',
    'curl_ca_bundle' => 'ca-bundle',
    'ca_bundle' => 'ca-bundle',
] as $configKey => $optionName) {
    if ($optionName === 'source-geojson' && in_array('--source-geojson', $command, true)) {
        continue;
    }
    if ($optionName === 'brgy-code' && in_array('--brgy-code', $command, true)) {
        continue;
    }
    if ($optionName === 'barangay' && in_array('--barangay', $command, true)) {
        continue;
    }
    if ($optionName === 'city' && in_array('--city', $command, true)) {
        continue;
    }
    if ($optionName === 'citymun-code' && in_array('--citymun-code', $command, true)) {
        continue;
    }
    if ($optionName === 'prov-code' && in_array('--prov-code', $command, true)) {
        continue;
    }
    if ($optionName === 'reg-code' && in_array('--reg-code', $command, true)) {
        continue;
    }
    if ($optionName === 'ca-bundle' && in_array('--ca-bundle', $command, true)) {
        continue;
    }
    append_option($command, $optionName, $prepare[$configKey] ?? null);
}
if (array_key_exists('curl_ssl_verify', $prepare) && bool_config($prepare['curl_ssl_verify'], true) === false) {
    $command[] = '--no-ssl-verify';
}

[$exitCode, $output] = run_command($command);
$child = is_file($childReport) ? json_decode((string)file_get_contents($childReport), true) : null;
if (!is_array($child)) {
    $child = [];
}

$status = $exitCode === 0 ? 'success' : 'failed';
$coverage = is_array($child['coverage'] ?? null) ? $child['coverage'] : [];
$results = is_array($child['results'] ?? null) ? $child['results'] : [];
$report = [
    'schema_version' => 1,
    'app' => 'pbb-mapserver',
    'tool' => 'data_prep_prepare',
    'version' => MAPSERVER_DATA_PREP_VERSION,
    'mode' => (string)$args['mode'],
    'dry_run' => (bool)$args['dry-run'],
    'status' => $status,
    'summary' => $status === 'success' ? 'MapServer tile cache preparation completed.' : 'MapServer tile cache preparation failed.',
    'started_at' => $started,
    'finished_at' => date('c'),
    'sources' => [
        [
            'id' => 'tile_coverage',
            'type' => (string)($child['source']['type'] ?? 'configured_area'),
            'label' => (string)($child['source']['label'] ?? ''),
        ],
    ],
    'results' => [
        [
            'id' => 'tile_cache',
            'type' => 'tile_cache',
            'action' => $args['dry-run'] ? 'plan' : 'populate',
            'status' => $status,
            'planned' => (int)($coverage['requests_planned'] ?? 0),
            'attempted' => (int)($results['attempted'] ?? 0),
            'succeeded' => (int)($results['succeeded'] ?? 0),
            'failed' => (int)($results['failed'] ?? 0),
        ],
    ],
    'outputs' => [
        [
            'id' => 'mapserver_tile_cache',
            'kind' => 'tile_cache',
            'target_app' => 'pbb-mapserver',
            'status' => $args['dry-run'] ? 'planned' : ($status === 'success' ? 'prepared' : 'failed'),
        ],
    ],
    'details' => [
        'base_url' => $baseUrl,
        'coverage' => $coverage,
        'child_report' => $childReport,
        'boundary_resolution' => $boundary,
    ],
    'warnings' => [],
    'errors' => $status === 'success' ? [] : array_slice($output, 0, 25),
];

write_json_file((string)$args['report'], $report);
echo json_encode([
    'status' => $report['status'],
    'dry_run' => $report['dry_run'],
    'planned' => $report['results'][0]['planned'],
    'attempted' => $report['results'][0]['attempted'],
    'succeeded' => $report['results'][0]['succeeded'],
    'failed' => $report['results'][0]['failed'],
    'report' => (string)$args['report'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($exitCode === 0 ? 0 : 1);
