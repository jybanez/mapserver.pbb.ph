<?php

declare(strict_types=1);

function status_read_json(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        return null;
    }
    $data = json_decode($content, true);
    return is_array($data) ? $data : null;
}

$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$manifestPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installer' . DIRECTORY_SEPARATOR . 'install-manifest.json';
$manifest = status_read_json($manifestPath);
$release = status_read_json($root . DIRECTORY_SEPARATOR . 'release.json') ?? [];

if ($manifest === null) {
    $status = [
        'schema_version' => 1,
        'app' => 'pbb-mapserver',
        'milestone' => (int)($release['milestone'] ?? 1),
        'version' => (string)($release['version'] ?? '1.0.0'),
        'display_version' => (string)($release['display_version'] ?? 'v1-1.0.0'),
        'repository' => $release['repository'] ?? null,
        'build' => $release['build'] ?? null,
        'installed' => false,
        'status' => 'not-installed',
        'mode' => 'new',
        'health' => [
            'http' => 'unknown',
            'ready' => 'unknown',
            'details' => [
                'cache_ready' => false,
                'services_running' => true,
            ],
        ],
        'services' => [],
        'warnings' => ['Install manifest not found.'],
    ];
} else {
    $health = is_array($manifest['health'] ?? null) ? $manifest['health'] : [];
    $healthStatus = (string)($health['status'] ?? 'unknown');
    $status = [
        'schema_version' => 1,
        'app' => 'pbb-mapserver',
        'milestone' => (int)($manifest['milestone'] ?? ($release['milestone'] ?? 1)),
        'version' => (string)($manifest['version'] ?? '1.0.0'),
        'display_version' => (string)($manifest['display_version'] ?? ($release['display_version'] ?? 'v1-1.0.0')),
        'repository' => $manifest['repository'] ?? ($release['repository'] ?? null),
        'build' => $manifest['build'] ?? ($release['build'] ?? null),
        'installed' => true,
        'status' => $healthStatus === 'healthy' ? 'healthy' : 'degraded',
        'mode' => 'installed',
        'health' => [
            'http' => $healthStatus === 'unhealthy' ? 'failed' : 'ok',
            'ready' => $healthStatus === 'unhealthy' ? 'failed' : 'ok',
            'details' => [
                'cache_ready' => is_dir((string)($manifest['cache']['root'] ?? '')),
                'services_running' => true,
            ],
        ],
        'services' => [],
        'warnings' => $healthStatus === 'skipped' ? ['HTTP health checks were skipped because app_url was not provided.'] : [],
    ];
}

header('Content-Type: application/json');
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
