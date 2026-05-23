<?php

declare(strict_types=1);

function fail(string $message, int $code = 2): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function parse_args(array $argv): array
{
    $args = ['mode' => '', 'config' => '', 'report' => '', 'dry-run' => false, 'verbose' => false, 'help' => false];
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

function http_check(string $baseUrl, string $path): array
{
    $url = rtrim($baseUrl, '/') . $path;
    $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $statusLine = (string)($headers[0] ?? '');
    preg_match('/\s(\d{3})\s/', $statusLine, $m);
    $statusCode = isset($m[1]) ? (int)$m[1] : 0;
    return [
        'id' => trim($path, '/'),
        'type' => 'http_endpoint',
        'action' => 'verify',
        'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'failed',
        'url' => $url,
        'http_status' => $statusCode,
        'bytes' => $body === false ? 0 : strlen($body),
        'failed' => $statusCode >= 200 && $statusCode < 300 ? 0 : 1,
    ];
}

$started = date('c');
$args = parse_args($argv);
if ($args['help']) {
    echo "PBB MapServer Data Prep: Verify\n";
    exit(0);
}
if (!in_array($args['mode'], ['initial', 'repair', 'refresh', 'demo'], true)) {
    fail('Provide --mode initial|repair|refresh|demo.');
}
if ($args['config'] === '' || $args['report'] === '') {
    fail('Provide --config and --report.');
}

$config = read_json_file((string)$args['config']);
$verify = config_value($config, ['mapserver', 'data_prep', 'verify'], []);
$baseUrl = is_array($verify) && isset($verify['base_url'])
    ? (string)$verify['base_url']
    : (string)config_value($config, ['app', 'app_url'], 'http://localhost/mapserver');

$results = [
    http_check($baseUrl, '/tiles/health'),
    http_check($baseUrl, '/api/status'),
];
$failed = array_filter($results, static fn(array $result): bool => $result['status'] !== 'success');
$status = count($failed) === 0 ? 'success' : 'failed';

$report = [
    'schema_version' => 1,
    'app' => 'pbb-mapserver',
    'tool' => 'data_prep_verify',
    'version' => 1,
    'mode' => (string)$args['mode'],
    'dry_run' => (bool)$args['dry-run'],
    'status' => $status,
    'summary' => $status === 'success' ? 'MapServer tile endpoints are usable.' : 'MapServer tile endpoint verification failed.',
    'started_at' => $started,
    'finished_at' => date('c'),
    'sources' => [],
    'results' => $results,
    'outputs' => [],
    'warnings' => [],
    'errors' => array_values(array_map(static fn(array $result): string => $result['url'] . ' returned HTTP ' . $result['http_status'], $failed)),
];
write_json_file((string)$args['report'], $report);
echo json_encode(['status' => $status, 'report' => (string)$args['report']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($status === 'success' ? 0 : 1);
