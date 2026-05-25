<?php
$config = require __DIR__ . '/config.php';

if (!function_exists('curl_init')) {
    http_response_code(500);
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=86400');
    echo 'cURL is required.';
    exit;
}

$cacheRoot = rtrim((string)($config['cache_root'] ?? ''), "/\\");
$logFile = (string)($config['log_file'] ?? '');

function log_line(string $file, string $message): void
{
    if ($file === '') {
        return;
    }

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $stamp = date('Y-m-d H:i:s');
    @file_put_contents($file, "[$stamp] $message\n", FILE_APPEND);
}

function respond(int $status, array $headers, string $body = ''): void
{
    http_response_code($status);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    if ($body !== '') {
        echo $body;
    }
    exit;
}

function ensure_dir(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }
    return @mkdir($dir, 0775, true);
}

function validate_tile_coords(int $z, int $x, int $y, int $maxZoom = 22): bool
{
    if ($z < 0 || $z > $maxZoom || $x < 0 || $y < 0) {
        return false;
    }

    $limit = (int) pow(2, $z);
    if ($limit <= 0) {
        return false;
    }

    return $x < $limit && $y < $limit;
}

function parse_headers(string $headerText): array
{
    $headers = [];
    $lines = preg_split("/\r\n/", trim($headerText));
    foreach ($lines as $line) {
        if (strpos($line, ':') === false) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
    }
    return $headers;
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    if ($bytes === 0) {
        return '0 B';
    }

    $pow = (int)floor(log($bytes, 1024));
    $pow = min($pow, count($units) - 1);
    $value = $bytes / (1024 ** $pow);

    return round($value, 2) . ' ' . $units[$pow];
}

function get_directory_size(string $path): int
{
    if (!is_dir($path)) {
        return 0;
    }

    $bytes = 0;
    $flags = FilesystemIterator::SKIP_DOTS;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, $flags)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $bytes += $file->getSize();
        }
    }

    return $bytes;
}

function build_url_from_parts(array $parts): string
{
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $user = $parts['user'] ?? '';
    $pass = $parts['pass'] ?? '';
    $auth = '';
    if ($user !== '') {
        $auth = $user;
        if ($pass !== '') {
            $auth .= ':' . $pass;
        }
        $auth .= '@';
    }

    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

    return $scheme . $auth . $host . $port . $path . $query . $fragment;
}

function mask_sensitive_url(string $url): string
{
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }

    if (!isset($parts['query']) || $parts['query'] === '') {
        return $url;
    }

    parse_str($parts['query'], $query);
    foreach ($query as $key => $value) {
        $normalizedKey = strtolower((string)$key);
        if (in_array($normalizedKey, ['api_key', 'key', 'token', 'access_token'], true)) {
            $query[$key] = '***';
        }
    }
    $parts['query'] = http_build_query($query);

    return build_url_from_parts($parts);
}

function describe_upstream(string $name, string $url): array
{
    $maskedUrl = mask_sensitive_url($url);
    $parts = $maskedUrl !== '' ? parse_url($maskedUrl) : false;

    return [
        'name' => $name,
        'configured' => $url !== '',
        'template' => $maskedUrl,
        'host' => $parts['host'] ?? '',
        'scheme' => $parts['scheme'] ?? '',
    ];
}

function summarize_cache_bucket(string $path): string
{
    if (!is_dir($path)) {
        return 'No cache yet';
    }

    $entries = 0;
    $flags = FilesystemIterator::SKIP_DOTS;
    foreach (new FilesystemIterator($path, $flags) as $entry) {
        $entries++;
    }

    if ($entries === 0) {
        return 'Empty';
    }

    return $entries . ' top-level entries';
}

function fetch_url(string $url, array $requestHeaders, array $curlOptions): array
{
    $ch = curl_init($url);
    $baseOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_USERAGENT => 'mapserver-tile-proxy/1.0',
    ];
    curl_setopt_array($ch, $baseOptions + $curlOptions);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'code' => 502, 'error' => $error];
    }

    $headerText = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    $headerBlocks = preg_split("/\r\n\r\n/", trim($headerText));
    $lastHeaderText = $headerBlocks[count($headerBlocks) - 1] ?? '';
    $headers = parse_headers($lastHeaderText);

    return [
        'ok' => $code === 200,
        'code' => $code,
        'headers' => $headers,
        'body' => $body,
        'error' => '',
    ];
}

function get_service_status(): array
{
    global $config, $cacheRoot, $logFile;

    $cacheRootExists = $cacheRoot !== '' && is_dir($cacheRoot);
    $cacheRootWritable = $cacheRoot !== ''
        && (($cacheRootExists && is_writable($cacheRoot)) || is_writable(dirname($cacheRoot)));
    $cacheReady = $cacheRoot !== '' && $cacheRootWritable;

    $cacheBuckets = ['raster', 'vector', 'terrain', 'glyphs', 'poi'];
    $cacheUsage = [];
    foreach ($cacheBuckets as $bucket) {
        $bucketPath = $cacheRoot . DIRECTORY_SEPARATOR . $bucket;
        $cacheUsage[$bucket] = summarize_cache_bucket($bucketPath);
    }

    $upstreams = [
        'raster' => describe_upstream('raster', (string)($config['raster_base_url'] ?? '')),
        'vector' => describe_upstream('vector', (string)($config['vector_base_url'] ?? '')),
        'terrain' => describe_upstream('terrain', (string)($config['terrain_base_url'] ?? '')),
        'glyphs' => describe_upstream('glyphs', (string)($config['glyphs_base_url'] ?? '')),
        'poi' => describe_upstream('poi', (string)($config['poi_base_url'] ?? '')),
    ];
    $configuredUpstreams = array_values(array_map(
        static fn(array $upstream): string => $upstream['name'],
        array_filter($upstreams, static fn(array $upstream): bool => $upstream['configured'] === true)
    ));

    $warnings = [];
    if (!$cacheReady) {
        $warnings[] = 'Cache root is not writable; responses may bypass persistence.';
    }
    if (((string)($config['purge_token'] ?? '')) === '') {
        $warnings[] = 'Purge token is not configured.';
    }
    if (($config['curl_ssl_verify'] ?? true) === false) {
        $warnings[] = 'SSL verification is disabled for upstream requests.';
    }

    $operational = $cacheReady;
    $message = $operational
        ? 'Homepage diagnostics and cache storage are available.'
        : 'Tile proxy is reachable, but cache storage is not fully ready.';

    return [
        'status' => $operational ? 'ok' : 'degraded',
        'operational' => $operational,
        'message' => $message,
        'version' => '1.0.0',
        'timestamp' => date('c'),
        'time' => date('c'),
        'uptime' => 'Process uptime is not currently tracked by the PHP runtime.',
        'cache_ready' => $cacheReady,
        'endpoints' => [
            'health' => '/tiles/health',
            'raster' => '/tiles/raster/{z}/{x}/{y}.png',
            'vector' => '/tiles/vector/{z}/{x}/{y}.pbf',
            'terrain' => '/tiles/terrain/{z}/{x}/{y}.png',
            'glyphs' => '/tiles/glyphs/{fontstack}/{range}.pbf',
            'poi' => '/tiles/poi/{z}/{x}/{y}.pbf',
            'boundary' => '/boundaries/{scope}/{code}.geojson',
        ],
        'cache' => [
            'root' => $cacheRoot,
            'root_exists' => $cacheRootExists,
            'root_writable' => $cacheRootWritable,
            'strategy' => 'filesystem',
            'raster' => $cacheUsage['raster'],
            'vector' => $cacheUsage['vector'],
            'terrain' => $cacheUsage['terrain'],
            'glyphs' => $cacheUsage['glyphs'],
            'poi' => $cacheUsage['poi'],
        ],
        'upstreams' => $upstreams,
        'warnings' => $warnings,
        'ssl_verify' => (bool)($config['curl_ssl_verify'] ?? true),
        'log_file' => $logFile,
        'purge_token_configured' => ((string)($config['purge_token'] ?? '')) !== '',
        'configuration' => [
            'rate_limit' => 'Not configured in this PHP proxy',
            'cors_enabled' => true,
            'cors_origin' => '*',
            'compression' => 'gzip requested for vector, glyph, and POI upstreams',
            'cache_strategy' => 'filesystem',
            'configured_upstreams' => $configuredUpstreams,
            'ca_bundle_configured' => !empty($config['curl_ca_bundle']),
        ],
    ];
}

function release_build_id(): string
{
    static $buildId = null;
    if ($buildId !== null) {
        return $buildId;
    }

    $releasePath = __DIR__ . DIRECTORY_SEPARATOR . 'release.json';
    $buildId = 'unknown';
    if (is_file($releasePath)) {
        $release = json_decode((string)@file_get_contents($releasePath), true);
        if (is_array($release)) {
            $candidate = (string)($release['build']['id'] ?? $release['version'] ?? '');
            if ($candidate !== '') {
                $buildId = $candidate;
            }
        }
    }

    return $buildId;
}

function boundary_source_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $manifestPath = __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . 'manifest.json';
    $version = 'unknown';
    if (is_file($manifestPath)) {
        $manifest = json_decode((string)@file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            $capturedAt = (string)($manifest['captured_at'] ?? '');
            $source = (string)($manifest['source_repository'] ?? '');
            $version = trim($source . '@' . $capturedAt, '@') ?: 'unknown';
        }
    }

    return $version;
}

function normalize_boundary_scope(string $scope): string
{
    $scope = strtolower(trim($scope));
    $aliases = [
        'brgy' => 'barangay',
        'barangay' => 'barangay',
        'other' => 'barangay',
        'city' => 'city',
        'citymun' => 'city',
        'municipality' => 'city',
        'municipal' => 'city',
        'province' => 'province',
        'prov' => 'province',
        'region' => 'region',
        'reg' => 'region',
    ];

    return $aliases[$scope] ?? '';
}

function boundary_code_from_request(string $scope, array $query): string
{
    $keysByScope = [
        'barangay' => ['code', 'brgy_code', 'barangay_code', 'psgc_code', 'relay_hub_id'],
        'city' => ['code', 'citymun_code', 'city_code', 'municipality_code', 'psgc_code'],
        'province' => ['code', 'prov_code', 'province_code', 'psgc_code'],
        'region' => ['code', 'reg_code', 'region_code', 'psgc_code'],
    ];

    foreach ($keysByScope[$scope] ?? ['code'] as $key) {
        $value = trim((string)($query[$key] ?? ''));
        if ($value !== '') {
            return preg_replace('/\D+/', '', $value) ?? '';
        }
    }

    return '';
}

function boundary_code_option(string $scope): string
{
    return [
        'barangay' => 'brgy-code',
        'city' => 'citymun-code',
        'province' => 'prov-code',
        'region' => 'reg-code',
    ][$scope] ?? 'brgy-code';
}

function boundary_php_binary(): string
{
    $configured = trim((string)(getenv('MAPSERVER_PHP_BINARY') ?: ''));
    if ($configured !== '' && is_file($configured)) {
        return $configured;
    }

    $wampPhp = 'C:\\wamp64\\bin\\php\\php8.2.29\\php.exe';
    if (DIRECTORY_SEPARATOR === '\\' && is_file($wampPhp)) {
        return $wampPhp;
    }

    return PHP_BINARY;
}

function boundary_http_paths(string $scope, string $code): array
{
    $safeCode = preg_replace('/[^0-9A-Za-z_-]+/', '', $code) ?? '';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'boundaries' . DIRECTORY_SEPARATOR . 'http';
    $base = $baseDir . DIRECTORY_SEPARATOR . $scope . '-' . $safeCode;

    return [
        'dir' => $baseDir,
        'geojson' => $base . '.geojson',
        'index' => $base . '.index.json',
        'report' => $base . '.report.json',
        'lock' => $base . '.lock',
    ];
}

function prepare_boundary_geojson(string $scope, string $code, array $paths): array
{
    if (is_file($paths['geojson'])) {
        return ['ok' => true, 'output' => 'cached'];
    }

    if (!ensure_dir($paths['dir'])) {
        return ['ok' => false, 'error' => 'Boundary cache directory is not writable.'];
    }

    $lock = @fopen($paths['lock'], 'c');
    if ($lock === false) {
        return ['ok' => false, 'error' => 'Boundary cache lock could not be opened.'];
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            return ['ok' => false, 'error' => 'Boundary cache lock could not be acquired.'];
        }

        if (is_file($paths['geojson'])) {
            return ['ok' => true, 'output' => 'cached'];
        }

        $tool = __DIR__ . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'prepare-boundaries.php';
        $command = [
            boundary_php_binary(),
            $tool,
            '--deployment-scope',
            $scope,
            '--work-dir',
            __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'boundaries',
            '--output',
            $paths['geojson'],
            '--index',
            $paths['index'],
            '--report',
            $paths['report'],
            '--no-download',
            '--' . boundary_code_option($scope),
            $code,
        ];
        $shellCommand = implode(' ', array_map('escapeshellarg', $command));
        $output = [];
        $exitCode = 1;
        exec($shellCommand . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || !is_file($paths['geojson'])) {
            return [
                'ok' => false,
                'error' => 'Boundary generation failed.',
                'details' => array_slice($output, -8),
            ];
        }

        return ['ok' => true, 'output' => implode("\n", $output)];
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function boundary_response_headers(string $scope, string $code, string $geojsonPath): array
{
    $etag = '"' . hash_file('sha256', $geojsonPath) . '"';
    $lastModified = gmdate('D, d M Y H:i:s', filemtime($geojsonPath) ?: time()) . ' GMT';

    return [
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
        'Content-Type' => 'application/geo+json; charset=UTF-8',
        'ETag' => $etag,
        'Last-Modified' => $lastModified,
        'X-Cache' => 'HIT',
        'X-PBB-Boundary-Scope' => $scope,
        'X-PBB-Boundary-Code' => $code,
        'X-PBB-Boundary-Source' => 'resources/boundaries',
        'X-PBB-Boundary-Version' => boundary_source_version(),
        'X-PBB-MapServer-Build' => release_build_id(),
    ];
}

function serve_boundary_geojson(string $scope, string $code, array $commonHeaders): void
{
    $scope = normalize_boundary_scope($scope);
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if ($scope === '' || $code === '') {
        respond(400, $commonHeaders + ['Content-Type' => 'application/json'], json_encode([
            'status' => 'error',
            'error' => 'Provide a supported boundary scope and numeric PSGC code.',
            'supported_scopes' => ['barangay', 'city', 'province', 'region'],
        ]));
    }

    $paths = boundary_http_paths($scope, $code);
    $prepared = prepare_boundary_geojson($scope, $code, $paths);
    if (!$prepared['ok']) {
        $status = (($prepared['error'] ?? '') === 'Boundary generation failed.') ? 404 : 500;
        respond($status, $commonHeaders + ['Content-Type' => 'application/json'], json_encode([
            'status' => 'error',
            'error' => $prepared['error'] ?? 'Boundary unavailable.',
            'details' => $prepared['details'] ?? [],
        ]));
    }

    $headers = boundary_response_headers($scope, $code, $paths['geojson']);
    $ifNoneMatch = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch !== '' && hash_equals($headers['ETag'], $ifNoneMatch)) {
        respond(304, $headers);
    }

    $ifModifiedSince = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
    $mtime = filemtime($paths['geojson']) ?: time();
    if ($ifModifiedSince !== false && $ifModifiedSince >= $mtime) {
        respond(304, $headers);
    }

    respond(200, $headers, (string)file_get_contents($paths['geojson']));
}

function render_homepage(): string
{
    $generatedAt = date('Y-m-d H:i:s');
    $localChatLogPath = dirname(__DIR__) . '/pbb/chat_log.md';
    $chatLogLinkHtml = '';
    if (is_file($localChatLogPath)) {
        $chatLogLinkHtml = ' • <a href="../pbb/chat_log.md" target="_blank" rel="noopener noreferrer">Chat Log</a>';
    }

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PBB MapServer — Tile API</title>
  
  <!-- PBB Helpers: bundled runtime CSS. uiLoader imports bundled JS components on demand. -->
  <link rel="stylesheet" href="vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.css" />
  <script>document.documentElement.classList.add('js');</script>
  
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      margin: 0;
      padding: 0;
      background: var(--ui-bg, #0d1523);
      color: var(--ui-text, #d7e0f4);
      line-height: 1.6;
    }
    main.page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 20px;
    }
    .page-header {
      margin-bottom: 40px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--ui-border, #2b3750);
    }
    .page-header h1 {
      margin: 0 0 8px 0;
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--ui-text, #d7e0f4);
    }
    .page-header p {
      margin: 0;
      font-size: 1.1rem;
      color: var(--ui-muted, #9fb2d8);
    }
    .page-header .ui-eyebrow {
      margin-bottom: 10px;
    }
    section {
      margin: 40px 0;
    }
    .section-label {
      margin-bottom: 10px;
    }
    section > h2 {
      font-size: 1.8rem;
      margin: 0 0 20px 0;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--ui-border-strong, #344769);
      color: var(--ui-text, #d7e0f4);
    }
    .section-intro {
      font-size: 1.05rem;
      color: var(--ui-muted, #9fb2d8);
      margin-bottom: 24px;
    }
    a {
      color: #6f8ef9;
      text-decoration: underline;
      text-underline-offset: 0.18em;
      text-decoration-thickness: 1.5px;
    }
    a:hover {
      text-decoration-thickness: 2px;
    }
    a:focus-visible,
    button:focus-visible,
    .ui-button:focus-visible,
    .ui-tab:focus-visible,
    [role="tab"]:focus-visible,
    input:focus-visible,
    textarea:focus-visible,
    select:focus-visible {
      outline: 2px solid #8cc7ff;
      outline-offset: 2px;
      box-shadow: 0 0 0 3px rgba(92, 147, 230, 0.28);
    }
    footer {
      margin-top: 60px;
      padding-top: 20px;
      border-top: 1px solid var(--ui-border, #2b3750);
      text-align: center;
      color: var(--ui-muted, #9fb2d8);
      font-size: 0.95rem;
    }
    /* Placeholder containers for helper components */
    [data-helper-placeholder] {
      min-height: 100px;
      padding: 20px;
      background: var(--ui-bg-soft, #101c31);
      border-radius: 8px;
      border: 1px dashed var(--ui-border, #2b3750);
      color: var(--ui-text, #d7e0f4);
    }
    .js #status-fieldset {
      min-height: 840px;
    }
    .js #endpoints-grid {
      min-height: 560px;
    }
    .js #security-fieldset {
      min-height: 520px;
    }
    .js #integration-fieldset {
      min-height: 440px;
    }
    .js #operations-fieldset {
      min-height: 560px;
    }
    .js #diagnostics-placeholder {
      min-height: 150px;
    }
    .js #deployment-tabs {
      min-height: 260px;
    }
    .fallback-copy,
    .noscript-panel p,
    .noscript-panel li {
      color: var(--ui-muted, #9fb2d8);
    }
    .fallback-copy {
      margin: 0;
      font-size: 0.95rem;
    }
    .noscript-panel {
      margin: 0 0 28px 0;
      padding: 16px 18px;
      border: 1px solid var(--ui-border, #2b3750);
      border-radius: 8px;
      background: rgba(16, 28, 49, 0.85);
    }
    .noscript-panel h2,
    .noscript-panel p,
    .noscript-panel ul {
      margin: 0;
    }
    .noscript-panel ul {
      padding-left: 18px;
      display: grid;
      gap: 6px;
      margin-top: 10px;
    }
    .mapserver-badge {
      font-size: 11px;
      letter-spacing: 0.02em;
    }
    #status-fieldset .ui-fieldset,
    #endpoints-grid .ui-grid,
    #security-fieldset .ui-fieldset,
    #integration-fieldset .ui-fieldset,
    #operations-fieldset .ui-fieldset,
    #diagnostics-placeholder .ui-grid,
    #deployment-tabs .ui-tabs,
    .ui-fieldset-rows,
    .ui-fieldset-row,
    .ui-fieldset-field,
    .ui-tabpanel,
    .ui-tablist {
      min-width: 0;
      max-width: 100%;
    }
    .ui-fieldset-display-value,
    .ui-fieldset-alert,
    .ui-tabpanel,
    .ui-tabpanel p,
    .ui-tabpanel li {
      min-width: 0;
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    .ui-tabpanel pre,
    .ui-tabpanel code {
      max-width: 100%;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
      word-break: break-word;
    }
    #endpoints-grid .ui-grid-table-wrap,
    #diagnostics-placeholder .ui-grid-table-wrap {
      max-width: 100%;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    .mapserver-badge--good {
      border-color: #3e6d4b;
      background: rgba(25, 67, 39, 0.45);
      color: #d8ffe4;
    }
    .mapserver-badge--warning {
      border-color: #86622e;
      background: rgba(93, 62, 14, 0.42);
      color: #ffe9bf;
    }
    .mapserver-badge--danger {
      border-color: #8d3b48;
      background: rgba(85, 22, 31, 0.42);
      color: #ffd9df;
    }
    .mapserver-badge--neutral {
      border-color: var(--ui-border-strong, #344769);
      background: rgba(22, 35, 58, 0.55);
      color: var(--ui-text, #d7e0f4);
    }
    @media (max-width: 640px) {
      main.page {
        padding: 24px 12px 48px;
      }
      [data-helper-placeholder] {
        padding: 14px;
      }
      .js #status-fieldset,
      .js #endpoints-grid,
      .js #security-fieldset,
      .js #integration-fieldset,
      .js #operations-fieldset,
      .js #diagnostics-placeholder,
      .js #deployment-tabs {
        min-height: unset;
      }
      .page-header h1 {
        font-size: 2rem;
      }
      section > h2 {
        font-size: 1.45rem;
      }
      footer {
        padding-inline: 12px;
      }
    }
  </style>
</head>
<body>
  <main class="page" data-theme="dark">
    <header class="page-header">
      <p class="ui-eyebrow">PBB Infrastructure</p>
      <h1>PBB MapServer</h1>
      <p>Map tile proxy and cache service for the PBB ecosystem</p>
    </header>

    <noscript>
      <section class="noscript-panel" aria-label="JavaScript disabled guidance">
        <h2>Static Fallback</h2>
        <p>Live helper components require JavaScript, but the core service routes remain available without it.</p>
        <ul>
          <li><code>GET /tiles/health</code> for lightweight health checks</li>
          <li><code>GET /api/status</code> for operational metadata</li>
          <li><code>GET /boundaries/{scope}/{code}.geojson</code> for public hub boundary overlays</li>
          <li><code>GET /tiles/raster/0/0/0.png</code> for a sample tile fetch</li>
        </ul>
      </section>
    </noscript>

    <!-- Service Status Section (Phase 2) -->
    <section id="status-section" aria-labelledby="status-heading">
      <p class="ui-eyebrow section-label">Phase 2</p>
      <h2 id="status-heading">Service Status</h2>
      <p class="section-intro">Real-time operational status and configuration.</p>
      <div id="status-fieldset" data-helper-placeholder="createFieldset">
        <p class="fallback-copy">JavaScript loads the live status fieldset here. You can still query <code>/api/status</code> directly.</p>
      </div>
    </section>

    <!-- Endpoint Matrix (Phase 2) -->
    <section id="endpoints-section" aria-labelledby="endpoints-heading">
      <p class="ui-eyebrow section-label">Phase 2</p>
      <h2 id="endpoints-heading">Available Endpoints</h2>
      <p class="section-intro">Complete endpoint reference with examples and cache behavior.</p>
      <div id="endpoints-grid" data-helper-placeholder="createGrid">
        <p class="fallback-copy">JavaScript loads the searchable endpoint grid here. Core routes include <code>/tiles/health</code>, <code>/api/status</code>, <code>/boundaries/{scope}/{code}.geojson</code>, and the tile proxy paths.</p>
      </div>
    </section>

    <!-- Security Guidance (Phase 2) -->
    <section id="security-section" aria-labelledby="security-heading">
      <p class="ui-eyebrow section-label">Phase 2</p>
      <h2 id="security-heading">Security & Deployment</h2>
      <p class="section-intro">Best practices for secure operation and integration.</p>
      <div id="security-fieldset" data-helper-placeholder="createFieldset">
        <p class="fallback-copy">Security guidance is rendered here when JavaScript is enabled. Secrets remain in <code>.env</code> and are never printed into the page.</p>
      </div>
    </section>

    <!-- PBB Integration (Phase 2) -->
    <section id="integration-section" aria-labelledby="integration-heading">
      <p class="ui-eyebrow section-label">Phase 4</p>
      <h2 id="integration-heading">PBB Integration Contract</h2>
      <p class="section-intro">How PBB projects should integrate with MapServer.</p>
      <div id="integration-fieldset" data-helper-placeholder="createFieldset">
        <p class="fallback-copy">Integration guidance loads here with helper components. Use <code>/tiles/health</code> for polling and inspect <code>X-Cache</code> on tile responses.</p>
      </div>
    </section>

    <!-- Quick Operations (Phase 2) -->
    <section id="operations-section" aria-labelledby="operations-heading">
      <p class="ui-eyebrow section-label">Phase 2</p>
      <h2 id="operations-heading">Quick Operations</h2>
      <p class="section-intro">Copy-paste ready commands for common tasks.</p>
      <div id="operations-fieldset" data-helper-placeholder="createFieldset">
        <p class="fallback-copy">Helper-rendered command references load here. Without JavaScript, use <code>/tiles/health</code> and <code>/api/status</code> directly.</p>
      </div>
    </section>

    <!-- Diagnostics (Phase 3) -->
    <section id="diagnostics-section" aria-labelledby="diagnostics-heading">
      <p class="ui-eyebrow section-label">Phase 3</p>
      <h2 id="diagnostics-heading">Diagnostics</h2>
      <p class="section-intro">Test endpoint health and verify configuration.</p>
      <button id="run-diagnostics-btn" class="ui-button ui-button-primary" style="display: none;">
        Run Diagnostics
      </button>
      <div id="diagnostics-placeholder" data-helper-placeholder="createFormModal">
        <p class="fallback-copy">Interactive diagnostics require JavaScript. You can manually test the service with <code>GET /tiles/health</code> or <code>GET /api/status</code>.</p>
      </div>
    </section>

    <!-- Deployment (Phase 4) -->
    <section id="deployment-section" aria-labelledby="deployment-heading">
      <p class="ui-eyebrow section-label">Phase 4</p>
      <h2 id="deployment-heading">Deployment</h2>
      <p class="section-intro">Environment-specific setup and configuration guides.</p>
      <div id="deployment-tabs" data-helper-placeholder="createTabs">
        <p class="fallback-copy">Deployment tabs load here with JavaScript enabled. Apache/WAMP, Docker, and Kubernetes notes remain available in the project docs.</p>
      </div>
    </section>
  </main>

  <footer>
    <p>
      MapServer is part of the <strong>PBB</strong> ecosystem.
      <a href="https://github.com/jybanez/helpers.pbb.ph" target="_blank" rel="noopener noreferrer">UI Helpers</a> •
      <a href="README.md" target="_blank" rel="noopener noreferrer">README</a>{$chatLogLinkHtml} •
      <a href="docs/homepage-improvements-proposal.md" target="_blank" rel="noopener noreferrer">Proposal</a> •
      <a href="docs/homepage-implementation-checklist.md" target="_blank" rel="noopener noreferrer">Checklist</a>
    </p>
    <p>Latest update: {$generatedAt}</p>
  </footer>
 
  <!-- PBB Helpers: uiLoader for dynamic component loading. /api/status powers the live homepage status block. -->
  <script type="module">
    // Import uiLoader from PBB Helpers registry
    import { uiLoader } from './vendor/helpers.pbb.ph/js/ui/ui.loader.js';
    
    // Make uiLoader globally available for homepage adapter
    window.uiLoader = uiLoader;
    
    // Enable helper debug logging only when explicitly requested.
    const homepageDebug = new URLSearchParams(window.location.search).get('debug') === '1';
    uiLoader.setDebug(homepageDebug);
    uiLoader.setPreferBundles(true);
    
    console.log('[MapServer] uiLoader initialized and ready');
  </script>

  <!-- MapServer homepage adapter (Phase 1+) -->
  <script type="module" src="js/homepage.js"></script>
</body>
</html>
HTML;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawPathInfo = (string)($_SERVER['PATH_INFO'] ?? '');
$rawRequestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = $rawPathInfo !== '' ? $rawPathInfo : (parse_url($rawRequestUri, PHP_URL_PATH) ?? '/');
if ($path === '') {
    $path = '/';
}
if ($path[0] !== '/') {
    $path = '/' . $path;
}

$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$scriptBase = basename(str_replace('\\', '/', $scriptName));
$debugEnabled = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debugEnabled) {
    header('X-Debug-Request-Uri: ' . $rawRequestUri);
    header('X-Debug-Path-Info: ' . $rawPathInfo);
    header('X-Debug-Script-Name: ' . $scriptName);
    header('X-Debug-Script-Dir: ' . $scriptDir);
}

if ($scriptBase === 'index.php' && $scriptName !== '' && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
} else {
    if ($scriptBase === 'index.php' && $scriptDir !== '' && $scriptDir !== '.' && $scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
        $path = substr($path, strlen($scriptDir));
    }
}
if ($path === '') {
    $path = '/';
}
if ($path[0] !== '/') {
    $path = '/' . $path;
}

if ($debugEnabled) {
    header('X-Debug-Path: ' . $path);
}

$commonHeaders = [
    'Access-Control-Allow-Origin' => '*',
    'Cache-Control' => 'public, max-age=86400',
];

if ($path === '/' && $method === 'GET') {
    $headers = [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'public, max-age=300',
    ];
    respond(200, $headers, render_homepage());
}

if ($path === '/api/status' && $method === 'GET') {
    $headers = $commonHeaders + [
        'Content-Type' => 'application/json',
        'X-Cache' => 'BYPASS',
    ];
    respond(200, $headers, json_encode(get_service_status()));
}

if (($path === '/tiles/health' || $path === '/health') && $method === 'GET') {
    $headers = $commonHeaders + [
        'Content-Type' => 'application/json',
        'X-Cache' => 'BYPASS',
    ];
    respond(200, $headers, json_encode(['status' => 'ok', 'time' => date('c')]));
}

if (preg_match('~^/boundaries/([^/]+)/([A-Za-z0-9_-]+)\.geojson$~', $path, $m) && $method === 'GET') {
    serve_boundary_geojson($m[1], $m[2], $commonHeaders);
}

if (preg_match('~^/api/boundaries/([^/]+)/([A-Za-z0-9_-]+)$~', $path, $m) && $method === 'GET') {
    serve_boundary_geojson($m[1], $m[2], $commonHeaders);
}

if ($path === '/boundaries.geojson' && $method === 'GET') {
    $scope = normalize_boundary_scope((string)($_GET['scope'] ?? $_GET['level'] ?? ''));
    $code = $scope !== '' ? boundary_code_from_request($scope, $_GET) : '';
    serve_boundary_geojson($scope, $code, $commonHeaders);
}

$isPurge = false;
if (strncmp($path, '/tiles/purge/', 13) === 0) {
    $isPurge = true;
    $subpath = substr($path, strlen('/tiles/purge'));
    if ($subpath === '' || $subpath[0] !== '/') {
        respond(404, $commonHeaders, 'Not Found');
    }
    $path = '/tiles' . $subpath;

    if ($method !== 'POST' && $method !== 'DELETE') {
        respond(405, ['Allow' => 'POST, DELETE'] + $commonHeaders, 'Method Not Allowed');
    }

    $purgeToken = (string)($config['purge_token'] ?? '');
    $providedToken = (string)($_GET['token'] ?? ($_SERVER['HTTP_X_PURGE_TOKEN'] ?? ''));
    if ($purgeToken === '' || $providedToken === '' || !hash_equals($purgeToken, $providedToken)) {
        respond(403, $commonHeaders, 'Forbidden');
    }
} elseif ($method !== 'GET') {
    respond(405, ['Allow' => 'GET'] + $commonHeaders, 'Method Not Allowed');
}

$type = null;
$z = $x = $y = null;
$fontstack = null;
$range = null;
$contentType = null;
$cacheFile = null;
$encodingFile = null;
$upstreamUrl = null;
$acceptHeaders = [];

if (preg_match('~^/tiles/raster/(\d+)/(\d+)/(\d+)\.png$~', $path, $m)) {
    $type = 'raster';
    [$z, $x, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    $contentType = 'image/png';
    $cacheFile = $cacheRoot . DIRECTORY_SEPARATOR . 'raster' . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x . DIRECTORY_SEPARATOR . $y . '.png';
    $upstreamUrl = (string)($config['raster_base_url'] ?? '');
    if (!validate_tile_coords($z, $x, $y)) {
        respond(400, $commonHeaders, 'Bad Request');
    }
} elseif (preg_match('~^/tiles/vector/(\d+)/(\d+)/(\d+)\.pbf$~', $path, $m)) {
    $type = 'vector';
    [$z, $x, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    $contentType = 'application/x-protobuf';
    $cacheFile = $cacheRoot . DIRECTORY_SEPARATOR . 'vector' . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x . DIRECTORY_SEPARATOR . $y . '.pbf';
    $encodingFile = $cacheFile . '.encoding';
    if (!validate_tile_coords($z, $x, $y)) {
        respond(400, $commonHeaders, 'Bad Request');
    }
    $upstreamUrl = (string)($config['vector_base_url'] ?? '');
    $acceptHeaders[] = 'Accept-Encoding: gzip';
} elseif (preg_match('~^/tiles/terrain/(\d+)/(\d+)/(\d+)\.png$~', $path, $m)) {
    $type = 'terrain';
    [$z, $x, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    $contentType = 'image/png';
    $cacheFile = $cacheRoot . DIRECTORY_SEPARATOR . 'terrain' . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x . DIRECTORY_SEPARATOR . $y . '.png';
    $upstreamUrl = (string)($config['terrain_base_url'] ?? '');
    if (!validate_tile_coords($z, $x, $y)) {
        respond(400, $commonHeaders, 'Bad Request');
    }
} elseif (preg_match('~^/tiles/glyphs/([^/]+)/([^/]+)\.pbf$~', $path, $m)) {
    $type = 'glyphs';
    $fontstack = urldecode($m[1]);
    $range = urldecode($m[2]);

    if ($fontstack === '' || $range === '') {
        respond(400, $commonHeaders, 'Bad Request');
    }

    if (strpos($fontstack, '..') !== false || strpos($range, '..') !== false) {
        respond(400, $commonHeaders, 'Bad Request');
    }

    if (!preg_match('/^[A-Za-z0-9 _\-,]+$/', $fontstack)) {
        respond(400, $commonHeaders, 'Bad Request');
    }

    if (!preg_match('/^\d+-\d+$/', $range)) {
        respond(400, $commonHeaders, 'Bad Request');
    }

    $fontKey = rawurlencode($fontstack);
    $fontKey = str_replace('%2C', ',', $fontKey);
    $rangeKey = rawurlencode($range);

    $contentType = 'application/x-protobuf';
    $cacheFile = $cacheRoot . DIRECTORY_SEPARATOR . 'glyphs' . DIRECTORY_SEPARATOR . $fontKey . DIRECTORY_SEPARATOR . $rangeKey . '.pbf';
    $encodingFile = $cacheFile . '.encoding';
    $upstreamUrl = (string)($config['glyphs_base_url'] ?? '');
    $acceptHeaders[] = 'Accept-Encoding: gzip';
} elseif (preg_match('~^/tiles/poi/(\d+)/(\d+)/(\d+)\.pbf$~', $path, $m)) {
    $type = 'poi';
    [$z, $x, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    $contentType = 'application/x-protobuf';
    $upstreamUrl = (string)($config['poi_base_url'] ?? '');
    if (!validate_tile_coords($z, $x, $y)) {
        respond(400, $commonHeaders, 'Bad Request');
    }
    $cacheFile = $cacheRoot . DIRECTORY_SEPARATOR . 'poi' . DIRECTORY_SEPARATOR . $z . DIRECTORY_SEPARATOR . $x . DIRECTORY_SEPARATOR . $y . '.pbf';
    $encodingFile = $cacheFile . '.encoding';
    $acceptHeaders[] = 'Accept-Encoding: gzip';
} else {
    respond(404, $commonHeaders, 'Not Found');
}

if ($cacheRoot === '') {
    respond(500, $commonHeaders, 'Server Misconfigured');
}

if ($isPurge) {
    $deleted = false;
    if ($cacheFile !== null && is_file($cacheFile)) {
        $deleted = @unlink($cacheFile) || $deleted;
    }
    if ($encodingFile !== null && is_file($encodingFile)) {
        $deleted = @unlink($encodingFile) || $deleted;
    }

    $headers = $commonHeaders + [
        'Content-Type' => 'application/json',
        'X-Cache' => 'BYPASS',
    ];

    if ($deleted) {
        log_line($logFile, "PURGE $path (200)");
        respond(200, $headers, json_encode(['status' => 'purged']));
    }

    log_line($logFile, "PURGE $path (404)");
    respond(404, $headers, json_encode(['status' => 'missing']));
}

if ($upstreamUrl === '') {
    respond(500, $commonHeaders, 'Server Misconfigured');
}

if ($cacheFile !== null && is_file($cacheFile)) {
    $headers = $commonHeaders + [
        'Content-Type' => $contentType,
        'X-Cache' => 'HIT',
    ];

    if ($encodingFile !== null && is_file($encodingFile)) {
        $encoding = trim((string)@file_get_contents($encodingFile));
        if ($encoding !== '') {
            $headers['Content-Encoding'] = $encoding;
        }
    }

    log_line($logFile, "HIT $path");
    http_response_code(200);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    readfile($cacheFile);
    exit;
}

$resolvedUrl = $upstreamUrl;
if ($type === 'glyphs') {
    $resolvedUrl = str_replace(['{fontstack}', '{range}'], [$fontKey, $rangeKey], $resolvedUrl);
} else {
    $resolvedUrl = str_replace(['{z}', '{x}', '{y}'], [$z, $x, $y], $resolvedUrl);
}

$curlOptions = [];
if (($config['curl_ssl_verify'] ?? true) === false) {
    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
}
if (!empty($config['curl_ca_bundle'])) {
    $curlOptions[CURLOPT_CAINFO] = (string)$config['curl_ca_bundle'];
}

$result = fetch_url($resolvedUrl, $acceptHeaders, $curlOptions);
if (!$result['ok']) {
    $code = $result['code'] ?? 502;
    $status = ($code === 404) ? 404 : 502;
    $headers = $commonHeaders + [
        'X-Cache' => 'MISS',
    ];
    $error = (string)($result['error'] ?? '');
    log_line($logFile, "MISS $path -> $resolvedUrl ($status) $error");
    if ($debugEnabled) {
        $headers['Content-Type'] = 'application/json';
        respond($status, $headers, json_encode([
            'status' => 'upstream_error',
            'code' => $code,
            'error' => $error,
            'url' => $resolvedUrl,
        ]));
    }
    respond($status, $headers, 'Upstream Error');
}

$body = (string)($result['body'] ?? '');
$headers = $commonHeaders + [
    'Content-Type' => $contentType,
    'X-Cache' => 'MISS',
];

$encoding = '';
if ($encodingFile !== null) {
    $encoding = (string)($result['headers']['content-encoding'] ?? '');
    if ($encoding !== '') {
        $headers['Content-Encoding'] = $encoding;
    }
}

$dir = dirname($cacheFile);
if (ensure_dir($dir)) {
    @file_put_contents($cacheFile, $body);
    if ($encodingFile !== null) {
        @file_put_contents($encodingFile, $encoding);
    }
}

log_line($logFile, "MISS $path -> $resolvedUrl (200)");
respond(200, $headers, $body);
