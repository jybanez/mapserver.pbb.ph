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

$started = date('c');
$args = parse_args($argv);
if ($args['help']) {
    echo "PBB MapServer Data Prep: Apply Settings\n";
    exit(0);
}
if (!in_array($args['mode'], ['initial', 'repair', 'refresh', 'demo'], true)) {
    fail('Provide --mode initial|repair|refresh|demo.');
}
if ($args['config'] === '' || $args['report'] === '') {
    fail('Provide --config and --report.');
}

$report = [
    'schema_version' => 1,
    'app' => 'pbb-mapserver',
    'tool' => 'data_prep_apply_settings',
    'version' => 1,
    'mode' => (string)$args['mode'],
    'dry_run' => (bool)$args['dry-run'],
    'status' => 'success',
    'summary' => 'MapServer has no app-owned settings to apply for the initial Data Prep scope.',
    'started_at' => $started,
    'finished_at' => date('c'),
    'sources' => [],
    'results' => [
        [
            'id' => 'mapserver_settings',
            'type' => 'settings',
            'action' => 'none',
            'status' => 'success',
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 1,
            'failed' => 0,
        ],
    ],
    'outputs' => [],
    'warnings' => [],
    'errors' => [],
];
write_json_file((string)$args['report'], $report);
echo json_encode(['status' => 'success', 'report' => (string)$args['report']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
