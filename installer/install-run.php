<?php

declare(strict_types=1);

const MAPSERVER_APP_ID = 'pbb-mapserver';
const MAPSERVER_NAME = 'PBB MapServer';
const MAPSERVER_MILESTONE = 1;
const MAPSERVER_VERSION = '1.0.0';
const MAPSERVER_DISPLAY_VERSION = 'v1-1.0.0';
const MAPSERVER_REPOSITORY_URL = 'https://github.com/jybanez/mapserver.pbb.ph';

function installer_now(): string
{
    return date('c');
}

function normalize_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    $real = realpath($path);
    if ($real !== false) {
        return $real;
    }
    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function app_source_root(): string
{
    $releaseAppRoot = normalize_path(__DIR__ . '/../app');
    if (is_file($releaseAppRoot . DIRECTORY_SEPARATOR . 'index.php')) {
        return $releaseAppRoot;
    }
    return normalize_path(__DIR__ . '/..');
}

function read_json_file(string $path): array
{
    if ($path === '' || !is_file($path)) {
        throw new RuntimeException("JSON file not found: {$path}");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Unable to read JSON file: {$path}");
    }
    $data = json_decode($content, true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON file: {$path}");
    }
    return $data;
}

function release_metadata(): array
{
    static $release = null;
    if ($release !== null) {
        return $release;
    }
    $path = __DIR__ . '/../release.json';
    if (!is_file($path)) {
        $release = [];
        return $release;
    }
    try {
        $data = read_json_file($path);
    } catch (Throwable $error) {
        $data = [];
    }
    $release = [
        'milestone' => (int)($data['milestone'] ?? MAPSERVER_MILESTONE),
        'version' => (string)($data['version'] ?? MAPSERVER_VERSION),
        'display_version' => (string)($data['display_version'] ?? MAPSERVER_DISPLAY_VERSION),
        'repository' => $data['repository'] ?? ['type' => 'git', 'url' => MAPSERVER_REPOSITORY_URL],
        'build' => $data['build'] ?? null,
    ];
    return $release;
}

function write_json_file(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create directory: {$dir}");
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        throw new RuntimeException("Unable to write JSON file: {$path}");
    }
}

function log_message(string $installRoot, string $message): void
{
    $log = $installRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install.log';
    $dir = dirname($log);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($log, '[' . installer_now() . '] ' . $message . PHP_EOL, FILE_APPEND);
}

function parse_args(array $argv): array
{
    $args = [
        'config' => '',
        'report' => '',
        'mode' => '',
        'dry_run' => false,
        'verbose' => false,
        'no_service_register' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run') {
            $args['dry_run'] = true;
            continue;
        }
        if ($arg === '--verbose') {
            $args['verbose'] = true;
            continue;
        }
        if ($arg === '--no-service-register') {
            $args['no_service_register'] = true;
            continue;
        }
        if (in_array($arg, ['--config', '--report', '--mode'], true)) {
            $key = substr($arg, 2);
            $i++;
            if (!isset($argv[$i])) {
                throw new RuntimeException("Missing value for {$arg}");
            }
            $args[$key] = $argv[$i];
            continue;
        }
        throw new RuntimeException("Unknown argument: {$arg}");
    }

    if ($args['config'] === '') {
        throw new RuntimeException('Missing required --config path.');
    }
    if ($args['report'] === '') {
        throw new RuntimeException('Missing required --report path.');
    }

    return $args;
}

function config_value(array $config, array $path, $default = null)
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

function target_root(array $config): string
{
    $publicPath = (string) config_value($config, ['app', 'public_path'], '');
    if ($publicPath !== '') {
        return normalize_path($publicPath);
    }
    return normalize_path((string) config_value($config, ['app', 'install_path'], ''));
}

function install_boundary_root(array $config): string
{
    $installPath = (string) config_value($config, ['app', 'install_path'], '');
    if ($installPath !== '') {
        return normalize_path($installPath);
    }
    return target_root($config);
}

function path_is_absolute(string $path): bool
{
    return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
        || str_starts_with($path, '\\\\')
        || str_starts_with($path, '//')
        || str_starts_with($path, DIRECTORY_SEPARATOR);
}

function path_within(string $path, string $base): bool
{
    $path = normalize_path($path);
    $base = normalize_path($base);
    if ($path === '' || $base === '') {
        return false;
    }

    $pathCompare = strtolower(str_replace('/', DIRECTORY_SEPARATOR, $path));
    $baseCompare = strtolower(rtrim(str_replace('/', DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR));

    return $pathCompare === $baseCompare
        || str_starts_with($pathCompare, $baseCompare . DIRECTORY_SEPARATOR);
}

function resolve_runtime_path(string $boundaryRoot, string $configuredPath, string $defaultRelativePath): string
{
    $path = trim($configuredPath);
    if ($path === '') {
        $path = $defaultRelativePath;
    }
    if (!path_is_absolute($path)) {
        $path = $boundaryRoot . DIRECTORY_SEPARATOR . $path;
    }
    return normalize_path($path);
}

function runtime_paths(array $config): array
{
    $map = is_array($config['mapserver'] ?? null) ? $config['mapserver'] : [];
    $installRoot = install_boundary_root($config);
    $publicRoot = target_root($config);
    $cacheRoot = resolve_runtime_path($installRoot, (string)($map['cache_root'] ?? ''), 'storage' . DIRECTORY_SEPARATOR . 'tiles');
    $logFile = resolve_runtime_path($installRoot, (string)($map['log_file'] ?? ''), 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'tiles.log');

    return [
        'install_path' => $installRoot,
        'public_path' => $publicRoot,
        'cache_root' => $cacheRoot,
        'log_file' => $logFile,
        'log_dir' => dirname($logFile),
    ];
}

function check_result(string $id, string $label, string $status, string $message, string $remediation = ''): array
{
    $check = [
        'id' => $id,
        'label' => $label,
        'status' => $status,
        'message' => $message,
    ];
    if ($remediation !== '') {
        $check['remediation'] = $remediation;
    }
    return $check;
}

function directory_writable_or_creatable(string $path): bool
{
    if ($path === '') {
        return false;
    }
    if (is_dir($path)) {
        return is_writable($path);
    }
    $parent = dirname($path);
    while ($parent !== '' && $parent !== dirname($parent)) {
        if (is_dir($parent)) {
            return is_writable($parent);
        }
        $parent = dirname($parent);
    }
    return false;
}

function upstream_url_value(array $map, string $key): string
{
    return trim((string)($map[$key] ?? ''));
}

function is_placeholder_upstream_url(string $url): bool
{
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    return $host === ''
        || str_ends_with($host, '.example.test')
        || str_ends_with($host, '.example.com')
        || str_contains($host, 'example.');
}

function is_default_raster_url(string $url): bool
{
    return trim($url) === 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
}

function has_custom_upstream_url(array $map, string $key): bool
{
    $url = upstream_url_value($map, $key);
    return $url !== '' && !is_placeholder_upstream_url($url) && !($key === 'raster_base_url' && is_default_raster_url($url));
}

function preflight(array $config): array
{
    $checks = [];
    $root = target_root($config);
    $runtime = runtime_paths($config);
    $map = is_array($config['mapserver'] ?? null) ? $config['mapserver'] : [];

    $checks[] = version_compare(PHP_VERSION, '8.2.0', '>=')
        ? check_result('php.version', 'PHP version', 'passed', 'PHP ' . PHP_VERSION . ' is supported.')
        : check_result('php.version', 'PHP version', 'failed', 'PHP ' . PHP_VERSION . ' is not supported.', 'Run with C:\\wamp64\\bin\\php\\php8.2.29\\php.exe or another PHP >= 8.2 binary.');

    foreach (['curl', 'json', 'openssl', 'SPL'] as $extension) {
        $checks[] = extension_loaded($extension)
            ? check_result('php.extension.' . strtolower($extension), "PHP extension {$extension}", 'passed', "{$extension} is loaded.")
            : check_result('php.extension.' . strtolower($extension), "PHP extension {$extension}", 'failed', "{$extension} is not loaded.", "Enable the {$extension} PHP extension.");
    }

    $checks[] = $root !== ''
        ? check_result('path.public', 'Public path', directory_writable_or_creatable($root) ? 'passed' : 'failed', directory_writable_or_creatable($root) ? "Public path can be written: {$root}" : "Public path is not writable or creatable: {$root}", 'Choose a writable public_path or create it with appropriate permissions.')
        : check_result('path.public', 'Public path', 'failed', 'app.public_path or app.install_path is required.');

    $boundaryRoot = $runtime['install_path'];
    foreach (['cache_root' => $runtime['cache_root'], 'log_file' => $runtime['log_file']] as $key => $value) {
        $path = $key === 'log_file' ? dirname($value) : $value;
        if ($boundaryRoot === '' || !path_within($value, $boundaryRoot)) {
            $checks[] = check_result(
                "mapserver.{$key}.boundary",
                "{$key} install boundary",
                'failed',
                "{$key} resolves outside app.install_path: {$value}",
                "Use a relative mapserver.{$key} or a path under app.install_path."
            );
            continue;
        }
        $checks[] = directory_writable_or_creatable($path)
            ? check_result("mapserver.{$key}", $key, 'passed', "{$key} can be written: {$value}")
            : check_result("mapserver.{$key}", $key, 'failed', "{$key} is not writable or creatable: {$value}", "Provide mapserver.{$key} with a writable path under app.install_path.");
    }

    $hasVectorUrl = has_custom_upstream_url($map, 'vector_base_url');
    $hasGlyphsUrl = has_custom_upstream_url($map, 'glyphs_base_url');
    $hasTerrainUrl = has_custom_upstream_url($map, 'terrain_base_url');
    $hasPoiUrl = has_custom_upstream_url($map, 'poi_base_url');
    $hasStadia = trim((string)($map['stadiamaps_api_key'] ?? config_value($config, ['secrets', 'values', 'stadiamaps_api_key'], ''))) !== '';
    $hasMaptiler = trim((string)($map['maptiler_api_key'] ?? config_value($config, ['secrets', 'values', 'maptiler_api_key'], ''))) !== '';

    $checks[] = ($hasVectorUrl && $hasGlyphsUrl) || $hasStadia
        ? check_result('upstream.stadia', 'Vector/glyph upstream credentials', 'passed', 'Vector and glyph upstreams are configured.')
        : check_result('upstream.stadia', 'Vector/glyph upstream credentials', 'failed', 'Stadia key or explicit vector/glyph URLs are required.', 'Provide mapserver.stadiamaps_api_key or both vector_base_url and glyphs_base_url.');

    $checks[] = ($hasTerrainUrl && $hasPoiUrl) || $hasMaptiler
        ? check_result('upstream.maptiler', 'Terrain/POI upstream credentials', 'passed', 'Terrain and POI upstreams are configured.')
        : check_result('upstream.maptiler', 'Terrain/POI upstream credentials', 'failed', 'MapTiler key or explicit terrain/POI URLs are required.', 'Provide mapserver.maptiler_api_key or both terrain_base_url and poi_base_url.');

    $purgeToken = trim((string)($map['purge_token'] ?? config_value($config, ['secrets', 'values', 'purge_token'], '')));
    $checks[] = $purgeToken !== ''
        ? check_result('secret.purge_token', 'Purge token', 'passed', 'Purge token is configured.')
        : check_result('secret.purge_token', 'Purge token', 'warning', 'Purge token is not configured; purge routes will reject all purge requests.', 'Provide mapserver.purge_token for production installs.');

    $failed = array_filter($checks, static fn(array $check): bool => $check['status'] === 'failed');

    return [
        'status' => count($failed) === 0 ? 'passed' : 'failed',
        'checks' => $checks,
    ];
}

function copy_tree(string $source, string $target, bool $copyCache): void
{
    $skipTop = ['.git', '.artifacts', '.vscode', 'test-results'];
    if (!$copyCache) {
        $skipTop[] = 'storage';
    }
    $skipFiles = ['.env', 'pbb.ph.key', 'pbb.ph.crt'];

    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        throw new RuntimeException("Unable to create target directory: {$target}");
    }

    $source = normalize_path($source);
    $target = normalize_path($target);
    if ($source === $target) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $parts = explode(DIRECTORY_SEPARATOR, $relative);
        if (in_array($parts[0], $skipTop, true)) {
            continue;
        }
        if ($item->isFile() && in_array($item->getFilename(), $skipFiles, true)) {
            continue;
        }
        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new RuntimeException("Unable to create directory: {$destination}");
            }
            continue;
        }
        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create directory: {$dir}");
        }
        if (!copy($item->getPathname(), $destination)) {
            throw new RuntimeException("Unable to copy {$relative}");
        }
    }
}

function env_quote(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (preg_match('/\s|#|=|"|\'/', $value)) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
    return $value;
}

function write_env(array $config, string $root): void
{
    $map = is_array($config['mapserver'] ?? null) ? $config['mapserver'] : [];
    $secrets = is_array(config_value($config, ['secrets', 'values'], [])) ? config_value($config, ['secrets', 'values'], []) : [];
    $env = [
        'TILES_PURGE_TOKEN' => (string)($map['purge_token'] ?? ($secrets['purge_token'] ?? '')),
        'STADIAMAPS_API_KEY' => (string)($map['stadiamaps_api_key'] ?? ($secrets['stadiamaps_api_key'] ?? '')),
        'MAPTILER_API_KEY' => (string)($map['maptiler_api_key'] ?? ($secrets['maptiler_api_key'] ?? '')),
        'TILES_CURL_CA_BUNDLE' => (string)($map['curl_ca_bundle'] ?? ''),
    ];
    if (array_key_exists('curl_ssl_verify', $map) && (bool)$map['curl_ssl_verify'] === false) {
        $env['TILES_CURL_SSL_VERIFY'] = '0';
    }
    $upstreamEnv = [
        'OSM_TILE_BASE_URL' => ['config_key' => 'raster_base_url', 'default' => true],
        'VECTOR_TILE_BASE_URL' => ['config_key' => 'vector_base_url', 'default' => false],
        'GLYPHS_BASE_URL' => ['config_key' => 'glyphs_base_url', 'default' => false],
        'TERRAIN_TILE_BASE_URL' => ['config_key' => 'terrain_base_url', 'default' => false],
        'POI_BASE_URL' => ['config_key' => 'poi_base_url', 'default' => false],
    ];
    foreach ($upstreamEnv as $envKey => $meta) {
        $configKey = (string)$meta['config_key'];
        if (has_custom_upstream_url($map, $configKey)) {
            $env[$envKey] = upstream_url_value($map, $configKey);
        }
    }

    $lines = [
        '# Generated by PBB MapServer installer on ' . installer_now(),
    ];
    foreach ($env as $key => $value) {
        if ($value === '' && $key === 'TILES_CURL_CA_BUNDLE') {
            continue;
        }
        $lines[] = $key . '=' . env_quote($value);
    }
    $path = $root . DIRECTORY_SEPARATOR . '.env';
    if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
        throw new RuntimeException("Unable to write {$path}");
    }
}

function ensure_runtime_dirs(array $config): void
{
    $runtime = runtime_paths($config);
    foreach ([$runtime['cache_root'], $runtime['log_dir']] as $dir) {
        if ($dir !== '' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create runtime directory: {$dir}");
        }
    }
}

function manifest(array $config, string $root, string $mode, array $health): array
{
    $release = release_metadata();
    $runtime = runtime_paths($config);
    return [
        'schema_version' => 1,
        'app' => MAPSERVER_APP_ID,
        'name' => MAPSERVER_NAME,
        'milestone' => $release['milestone'],
        'version' => $release['version'],
        'display_version' => $release['display_version'],
        'repository' => $release['repository'],
        'build' => $release['build'],
        'installed_at' => installer_now(),
        'install_mode' => $mode,
        'install_path' => $runtime['install_path'],
        'public_path' => $root,
        'app_url' => (string) config_value($config, ['app', 'app_url'], ''),
        'environment' => (string) config_value($config, ['app', 'app_env'], 'production'),
        'filesystem_paths' => $runtime,
        'cache' => [
            'root' => $runtime['cache_root'],
            'log_file' => $runtime['log_file'],
        ],
        'services' => [
            [
                'id' => 'pbb-mapserver-web',
                'manager' => (string) config_value($config, ['services', 'manager'], 'web-server'),
                'registered' => false,
                'artifact' => '',
            ],
        ],
        'health' => $health,
    ];
}

function run_health(array $config): array
{
    $appUrl = rtrim((string) config_value($config, ['app', 'app_url'], ''), '/');
    if ($appUrl === '') {
        return [
            'last_checked_at' => installer_now(),
            'status' => 'skipped',
            'checks' => [
                ['id' => 'http', 'status' => 'skipped', 'message' => 'app.app_url was not provided.'],
            ],
        ];
    }

    $checks = [];
    foreach (['/tiles/health', '/api/status'] as $path) {
        $url = $appUrl . $path;
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $statusLine = $headers[0] ?? '';
        $ok = strpos($statusLine, ' 200 ') !== false;
        $checks[] = [
            'id' => $path,
            'status' => $ok ? 'passed' : 'failed',
            'message' => $ok ? "{$path} returned 200." : "{$path} did not return 200.",
            'bytes' => $body === false ? 0 : strlen($body),
        ];
    }
    $failed = array_filter($checks, static fn(array $check): bool => $check['status'] === 'failed');
    return [
        'last_checked_at' => installer_now(),
        'status' => count($failed) === 0 ? 'healthy' : 'unhealthy',
        'checks' => $checks,
    ];
}

function make_report(array $config, string $mode, string $status, array $steps, array $warnings, array $errors, array $health): array
{
    $release = release_metadata();
    $runtime = $config === [] ? [] : runtime_paths($config);
    return [
        'schema_version' => 1,
        'app' => MAPSERVER_APP_ID,
        'milestone' => $release['milestone'],
        'version' => $release['version'],
        'display_version' => $release['display_version'],
        'repository' => $release['repository'],
        'build' => $release['build'],
        'run_id' => (string) config_value($config, ['kit', 'run_id'], 'mapserver_' . date('Ymd_His')),
        'mode' => $mode,
        'status' => $status,
        'started_at' => $GLOBALS['started_at'] ?? installer_now(),
        'finished_at' => installer_now(),
        'summary' => $status === 'success' ? 'PBB MapServer installer completed successfully.' : 'PBB MapServer installer did not complete successfully.',
        'steps' => $steps,
        'urls' => [
            'app' => (string) config_value($config, ['app', 'app_url'], ''),
            'health' => rtrim((string) config_value($config, ['app', 'app_url'], ''), '/') . '/tiles/health',
            'status' => rtrim((string) config_value($config, ['app', 'app_url'], ''), '/') . '/api/status',
        ],
        'filesystem_paths' => $runtime,
        'services' => [
            [
                'id' => 'pbb-mapserver-web',
                'status' => 'web-only',
                'message' => 'MapServer runs through the configured web server; no background daemon is required.',
            ],
        ],
        'health' => $health,
        'warnings' => $warnings,
        'errors' => $errors,
    ];
}

function step(string $id, string $status, string $message): array
{
    return ['id' => $id, 'status' => $status, 'message' => $message];
}

$started_at = installer_now();
$steps = [];
$warnings = [];
$errors = [];
$report = [];

try {
    $args = parse_args($argv);
    $config = read_json_file($args['config']);
    $mode = $args['mode'] !== '' ? $args['mode'] : (string)($config['mode'] ?? '');
    if (!in_array($mode, ['fresh', 'upgrade', 'repair', 'preflight'], true)) {
        throw new InvalidArgumentException("Unsupported mode: {$mode}");
    }
    $root = target_root($config);
    if ($root === '') {
        throw new InvalidArgumentException('app.public_path or app.install_path is required.');
    }
    $source = app_source_root();

    $preflight = preflight($config);
    $steps[] = step('preflight', $preflight['status'] === 'passed' ? 'success' : 'failed', $preflight['status'] === 'passed' ? 'Preflight checks passed.' : 'Preflight checks failed.');

    foreach ($preflight['checks'] as $check) {
        if ($check['status'] === 'warning') {
            $warnings[] = $check['message'];
        }
        if ($check['status'] === 'failed') {
            $errors[] = $check['message'];
        }
    }

    $health = ['last_checked_at' => installer_now(), 'status' => 'skipped', 'checks' => []];

    if ($preflight['status'] !== 'passed') {
        $report = make_report($config, $mode, 'failed', $steps, $warnings, $errors, $health);
        write_json_file($args['report'], $report);
        exit(1);
    }

    if ($mode === 'preflight' || $args['dry_run']) {
        $steps[] = step($args['dry_run'] ? 'dry_run' : 'preflight_only', 'success', $args['dry_run'] ? 'Dry run completed without mutation.' : 'Preflight-only mode completed without mutation.');
        $report = make_report($config, $mode, 'success', $steps, $warnings, $errors, $health);
        write_json_file($args['report'], $report);
        exit(0);
    }

    log_message($root, "Starting {$mode} install from {$source}");
    copy_tree($source, $root, (bool) config_value($config, ['options', 'copy_cache'], false));
    $steps[] = step('copy_files', 'success', $source === $root ? 'Source and target are the same; file copy skipped.' : 'Application files copied.');

    ensure_runtime_dirs($config);
    $steps[] = step('runtime_dirs', 'success', 'Runtime cache and log directories are ready.');

    if ((bool) config_value($config, ['options', 'write_env'], true)) {
        $envPath = $root . DIRECTORY_SEPARATOR . '.env';
        $preserveEnv = in_array($mode, ['upgrade', 'repair'], true)
            && is_file($envPath)
            && !(bool) config_value($config, ['options', 'overwrite_env'], false);
        if ($preserveEnv) {
            $steps[] = step('write_env', 'skipped', '.env already exists and was preserved.');
        } else {
            write_env($config, $root);
            $steps[] = step('write_env', 'success', '.env written.');
        }
    } else {
        $steps[] = step('write_env', 'skipped', 'Config disabled .env writing.');
    }

    if ((bool) config_value($config, ['options', 'validate_after_install'], true)) {
        $health = run_health($config);
        $steps[] = step('health', $health['status'] === 'unhealthy' ? 'warning' : 'success', 'Health checks completed with status: ' . $health['status']);
        if ($health['status'] === 'unhealthy') {
            $warnings[] = 'HTTP health validation did not pass. Check web server routing and app_url.';
        }
    }

    $manifest = manifest($config, $root, $mode, $health);
    write_json_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install-manifest.json', $manifest);
    $steps[] = step('manifest', 'success', 'Install manifest written.');

    $report = make_report($config, $mode, 'success', $steps, $warnings, $errors, $health);
    write_json_file($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install-report.json', $report);
    write_json_file($args['report'], $report);
    log_message($root, "{$mode} install completed");
    exit(0);
} catch (InvalidArgumentException $e) {
    $errors[] = $e->getMessage();
    $reportPath = $args['report'] ?? '';
    if ($reportPath !== '') {
        write_json_file($reportPath, make_report($config ?? [], $mode ?? 'unknown', 'failed', $steps, $warnings, $errors, ['last_checked_at' => installer_now(), 'status' => 'skipped', 'checks' => []]));
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
    $reportPath = $args['report'] ?? '';
    if ($reportPath !== '') {
        write_json_file($reportPath, make_report($config ?? [], $mode ?? 'unknown', 'failed', $steps, $warnings, $errors, ['last_checked_at' => installer_now(), 'status' => 'skipped', 'checks' => []]));
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
