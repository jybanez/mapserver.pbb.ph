<?php

declare(strict_types=1);

function usage(): string
{
    return <<<TXT
PBB MapServer release metadata updater

Usage:
  php tools/update-build-metadata.php [--version 1.0.1] [--milestone 1] [--builder local] [--build-id id] [--dry-run]

Updates release.json to the Kit Setup versioning shape:
  milestone + version + display_version=v{milestone}-{version}
  build.version, build.id, build.built_at, build.git_commit, build.builder
  update.contract_version metadata for Kit's app bundle update contract

This is source/build tooling. Do not include it in a distributable app bundle unless Kit Setup explicitly asks for source tooling.

TXT;
}

function fail(string $message, int $code = 2): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function parse_args(array $argv): array
{
    $args = [
        'version' => '',
        'milestone' => '',
        'builder' => 'local-source',
        'build-id' => '',
        'dry-run' => false,
        'help' => false,
    ];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run') {
            $args['dry-run'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
            continue;
        }
        if (!array_key_exists(ltrim($arg, '-'), $args)) {
            fail("Unknown option: {$arg}");
        }
        $key = ltrim($arg, '-');
        $i++;
        if (!isset($argv[$i])) {
            fail("Missing value for {$arg}");
        }
        $args[$key] = $argv[$i];
    }
    return $args;
}

function read_json(string $path): array
{
    $content = file_get_contents($path);
    if ($content === false) {
        fail("Unable to read {$path}");
    }
    $data = json_decode($content, true);
    if (!is_array($data)) {
        fail("Invalid JSON in {$path}");
    }
    return $data;
}

function write_json(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        fail("Unable to write {$path}");
    }
}

function prepare_release_for_json(array $release): array
{
    if (isset($release['requires']) && is_array($release['requires'])) {
        $apps = $release['requires']['apps'] ?? null;
        if (is_array($apps) && count($apps) === 0) {
            $release['requires']['apps'] = new stdClass();
        }
    }
    return $release;
}

function run_git(string $root, array $args): string
{
    $command = 'git -C ' . escapeshellarg($root);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
    $output = [];
    $code = 0;
    @exec($command . ' 2>NUL', $output, $code);
    return $code === 0 ? trim(implode("\n", $output)) : '';
}

$args = parse_args($argv);
if ($args['help']) {
    echo usage();
    exit(0);
}

$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$releasePath = $root . DIRECTORY_SEPARATOR . 'release.json';
$release = read_json($releasePath);

$milestone = $args['milestone'] !== '' ? (int)$args['milestone'] : (int)($release['milestone'] ?? 1);
if ($milestone < 1) {
    fail('--milestone must be a positive integer.');
}

$version = $args['version'] !== '' ? (string)$args['version'] : (string)($release['version'] ?? '1.0.0');
if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
    fail('--version must look like semantic versioning, for example 1.0.1.');
}

$now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM);
$commit = run_git($root, ['rev-parse', 'HEAD']);
$shortCommit = $commit !== '' ? substr($commit, 0, 12) : 'unknown';
$buildId = $args['build-id'] !== '' ? (string)$args['build-id'] : 'source-' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd-His') . '-' . $shortCommit;

$release['milestone'] = $milestone;
$release['version'] = $version;
$release['display_version'] = "v{$milestone}-{$version}";
$release['repository'] = [
    'type' => 'git',
    'url' => 'https://github.com/jybanez/mapserver.pbb.ph',
];
$release['build'] = [
    'version' => $version,
    'id' => $buildId,
    'built_at' => $now,
    'git_commit' => $commit !== '' ? $commit : 'unknown',
    'builder' => (string)$args['builder'],
];
$release['update'] = array_replace([
    'contract_version' => 1,
    'channel' => 'testing',
    'immutable_release' => false,
    'from_versions' => [$version],
    'compatibility' => 'same-version-rebuild',
    'requires_database_migration' => false,
    'requires_data_prep_rerun' => false,
    'requires_service_restart' => false,
    'rollback_supported' => true,
], is_array($release['update'] ?? null) ? $release['update'] : []);
$release['update']['contract_version'] = 1;
if (!is_array($release['update']['from_versions'] ?? null) || $release['update']['from_versions'] === []) {
    $release['update']['from_versions'] = [$version];
}

if ($args['dry-run']) {
    echo json_encode(prepare_release_for_json($release), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

write_json($releasePath, prepare_release_for_json($release));
echo json_encode([
    'status' => 'updated',
    'release' => $releasePath,
    'display_version' => $release['display_version'],
    'build' => $release['build'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
