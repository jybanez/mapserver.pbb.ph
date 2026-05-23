<?php

function loadEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $name = trim($parts[0]);
        $value = trim($parts[1]);

        if ($name === '') {
            continue;
        }

        if (strlen($value) >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
                if ($quote === '"') {
                    $value = stripcslashes($value);
                }
            }
        }

        if (getenv($name) === false) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/.env');

function env(string $name, ?string $default = null): ?string
{
    $val = getenv($name);
    return $val === false ? $default : $val;
}

function requiredEnv(string $name): string
{
    $val = env($name);
    if ($val === null || $val === '') {
        throw new RuntimeException("Environment variable {$name} is required.");
    }
    return $val;
}

$curlSslVerify = env('TILES_CURL_SSL_VERIFY');
if ($curlSslVerify === null) {
    $curlSslVerify = true;
} else {
    $curlSslVerify = filter_var($curlSslVerify, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($curlSslVerify === null) {
        $curlSslVerify = true;
    }
}

$rasterBaseUrl = env('OSM_TILE_BASE_URL') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

$vectorBaseUrl = env('VECTOR_TILE_BASE_URL');
if ($vectorBaseUrl === null || $vectorBaseUrl === '') {
    $vectorBaseUrl = 'https://tiles.stadiamaps.com/data/openmaptiles/{z}/{x}/{y}.pbf?api_key=' . requiredEnv('STADIAMAPS_API_KEY');
}

$glyphsBaseUrl = env('GLYPHS_BASE_URL');
if ($glyphsBaseUrl === null || $glyphsBaseUrl === '') {
    $glyphsBaseUrl = 'https://tiles.stadiamaps.com/fonts/{fontstack}/{range}.pbf?api_key=' . requiredEnv('STADIAMAPS_API_KEY');
}

$terrainBaseUrl = env('TERRAIN_TILE_BASE_URL');
if ($terrainBaseUrl === null || $terrainBaseUrl === '') {
    $terrainBaseUrl = 'https://api.maptiler.com/tiles/terrain-rgb/{z}/{x}/{y}.png?key=' . requiredEnv('MAPTILER_API_KEY');
}

$poiBaseUrl = env('POI_BASE_URL');
if ($poiBaseUrl === null || $poiBaseUrl === '') {
    $poiBaseUrl = 'https://api.maptiler.com/tiles/v3/{z}/{x}/{y}.pbf?key=' . requiredEnv('MAPTILER_API_KEY');
}

return [
    'cache_root' => env('TILES_CACHE_ROOT') ?: (__DIR__ . '/storage/tiles'),
    'log_file' => env('TILES_LOG_FILE') ?: (__DIR__ . '/storage/logs/tiles.log'),
    'purge_token' => env('TILES_PURGE_TOKEN') ?: '',
    'curl_ssl_verify' => $curlSslVerify,
    'curl_ca_bundle' => env('TILES_CURL_CA_BUNDLE') ?: '',

    // Upstream URL templates
    'raster_base_url' => $rasterBaseUrl,
    'vector_base_url' => $vectorBaseUrl,
    'glyphs_base_url' => $glyphsBaseUrl,
    'terrain_base_url' => $terrainBaseUrl,
    'poi_base_url' => $poiBaseUrl,
];
