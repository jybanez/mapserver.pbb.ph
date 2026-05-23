<?php

declare(strict_types=1);

const POPULATOR_VERSION = '1.0.2';

function usage(): string
{
    return <<<TXT
PBB MapServer tile populator

Usage:
  php tools/populate-tiles.php --base-url http://localhost/mapserver --source-geojson boundaries.geojson --brgy-code 730600041 --zooms 10-14 --types raster,vector --report report.json
  php tools/populate-tiles.php --base-url http://localhost/mapserver --center 10.324155,123.8984849 --radius-km 2 --zooms 10-14 --dry-run
  php tools/populate-tiles.php --base-url http://localhost/mapserver --bbox 123.88,10.30,123.92,10.35 --zooms 10-14

Options:
  --base-url          MapServer base URL. Default: http://localhost/mapserver
  --source-geojson    GeoJSON FeatureCollection exported from PSGC barangay boundaries.
  --brgy-code         Barangay PSGC/code to match inside --source-geojson.
  --citymun-code      City/municipality PSGC/code to match inside --source-geojson.
  --prov-code         Province PSGC/code to match inside --source-geojson.
  --reg-code          Region PSGC/code to match inside --source-geojson.
  --barangay          Barangay name to match inside --source-geojson.
  --city              Optional city/municipality name filter for --barangay.
  --bbox              Bounding box as minLon,minLat,maxLon,maxLat.
  --center            Center as lat,lon. Requires --radius-km.
  --radius-km         Radius in kilometers for --center.
  --zooms             Zoom list/range, for example 10-14 or 10,12,14. Default: 10-14
  --types             Tile types: raster,vector,terrain,poi. Default: raster,vector
  --report            JSON report path. Default: storage/installer/tile-populate-report.json
  --dry-run           Compute coverage without fetching tiles.
  --max-tiles         Safety ceiling before fetching. Default: 5000
  --limit             Stop after this many fetch attempts.
  --timeout           HTTP timeout per tile in seconds. Default: 20
  --ca-bundle         CA bundle path for HTTPS tile requests.
  --no-ssl-verify     Disable HTTPS certificate verification for tile requests.
  --help              Show this help.

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
        'base-url' => 'http://localhost/mapserver',
        'source-geojson' => '',
        'brgy-code' => '',
        'citymun-code' => '',
        'prov-code' => '',
        'reg-code' => '',
        'barangay' => '',
        'city' => '',
        'bbox' => '',
        'center' => '',
        'radius-km' => '',
        'zooms' => '10-14',
        'types' => 'raster,vector',
        'report' => __DIR__ . '/../storage/installer/tile-populate-report.json',
        'dry-run' => false,
        'max-tiles' => '5000',
        'limit' => '',
        'timeout' => '20',
        'ca-bundle' => '',
        'no-ssl-verify' => false,
        'help' => false,
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--dry-run') {
            $args['dry-run'] = true;
            continue;
        }
        if ($arg === '--no-ssl-verify') {
            $args['no-ssl-verify'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $args['help'] = true;
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

function parse_number_list(string $value, int $min, int $max): array
{
    $items = [];
    foreach (explode(',', $value) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^(\d+)-(\d+)$/', $part, $m)) {
            $start = (int)$m[1];
            $end = (int)$m[2];
            if ($end < $start) {
                fail("Invalid range: {$part}");
            }
            for ($i = $start; $i <= $end; $i++) {
                $items[] = $i;
            }
            continue;
        }
        if (!ctype_digit($part)) {
            fail("Invalid numeric value: {$part}");
        }
        $items[] = (int)$part;
    }
    $items = array_values(array_unique($items));
    sort($items);
    foreach ($items as $item) {
        if ($item < $min || $item > $max) {
            fail("Value {$item} is outside supported range {$min}-{$max}");
        }
    }
    if ($items === []) {
        fail('Numeric list cannot be empty.');
    }
    return $items;
}

function parse_csv_floats(string $value, int $count, string $label): array
{
    $parts = array_map('trim', explode(',', $value));
    if (count($parts) !== $count) {
        fail("{$label} must contain {$count} comma-separated numbers.");
    }
    $numbers = [];
    foreach ($parts as $part) {
        if (!is_numeric($part)) {
            fail("{$label} contains a non-numeric value: {$part}");
        }
        $numbers[] = (float)$part;
    }
    return $numbers;
}

function parse_positive_int(string $value, string $label): int
{
    $value = trim($value);
    if ($value === '' || !ctype_digit($value) || (int)$value < 1) {
        fail("{$label} must be a positive integer.");
    }
    return (int)$value;
}

function parse_positive_float(string $value, string $label): float
{
    $value = trim($value);
    if ($value === '' || !is_numeric($value) || (float)$value <= 0) {
        fail("{$label} must be greater than zero.");
    }
    return (float)$value;
}

function env_value(string $key): string
{
    $value = getenv($key);
    return is_string($value) ? trim($value) : '';
}

function parse_bool_like(string $value, bool $default): bool
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return $default;
    }
    return match ($value) {
        '1', 'true', 'yes', 'on' => true,
        '0', 'false', 'no', 'off' => false,
        default => $default,
    };
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
    foreach (code_variants($target) as $variant) {
        if ($actual === $variant || str_ends_with($actual, $variant) || str_ends_with($variant, $actual)) {
            return true;
        }
    }
    return false;
}

function property_value(array $properties, array $keys): string
{
    $lower = [];
    foreach ($properties as $key => $value) {
        $lower[strtolower((string)$key)] = is_scalar($value) ? (string)$value : '';
    }
    foreach ($keys as $key) {
        $key = strtolower($key);
        if (array_key_exists($key, $lower)) {
            return $lower[$key];
        }
    }
    return '';
}

function feature_label(array $feature): string
{
    $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
    $name = property_value($properties, ['brgy_name', 'bgy_name', 'barangay', 'name', 'adm4_en', 'adm4_name']);
    $city = property_value($properties, ['city_name', 'citymun_name', 'municipality', 'adm3_en', 'adm3_name']);
    $code = property_value($properties, ['brgy_code', 'bgy_code', 'source_psgc_code', 'psgc_10d', 'psgc', 'adm4_pcode', 'adm4_psgc', 'code']);
    return trim($name . ($city !== '' ? ", {$city}" : '') . ($code !== '' ? " ({$code})" : ''));
}

function load_geojson_feature(string $path, string $brgyCode, string $barangay, string $city): array
{
    if (!is_file($path)) {
        fail("GeoJSON file not found: {$path}");
    }
    $json = file_get_contents($path);
    if ($json === false) {
        fail("Unable to read GeoJSON file: {$path}");
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fail("Invalid GeoJSON JSON: {$path}");
    }
    $features = $data['type'] === 'FeatureCollection' ? ($data['features'] ?? []) : [$data];
    if (!is_array($features)) {
        fail('GeoJSON does not contain features.');
    }

    $targetCode = normalize_code($brgyCode);
    $targetName = normalize_text($barangay);
    $targetCity = normalize_text($city);

    foreach ($features as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $codes = [
            property_value($properties, ['brgy_code', 'bgy_code', 'psgc_10d', 'adm4_pcode', 'adm4_psgc', 'code']),
            property_value($properties, ['source_psgc_code', 'psgc']),
        ];
        $name = normalize_text(property_value($properties, ['brgy_name', 'bgy_name', 'barangay', 'name', 'adm4_en', 'adm4_name']));
        $cityName = normalize_text(property_value($properties, ['city_name', 'citymun_name', 'municipality', 'adm3_en', 'adm3_name']));

        $codeMatches = $targetCode !== '' && array_reduce($codes, static fn(bool $match, string $code): bool => $match || code_matches($code, $targetCode), false);
        $nameMatches = $targetName !== '' && ($name === $targetName || str_contains($name, $targetName) || str_contains($targetName, $name));
        $cityMatches = $targetCity === '' || $cityName === '' || $cityName === $targetCity || str_contains($cityName, $targetCity) || str_contains($targetCity, $cityName);

        if (($codeMatches || ($nameMatches && $cityMatches)) && isset($feature['geometry'])) {
            return $feature;
        }
    }

    fail('No matching barangay feature found in GeoJSON.');
}

function feature_matches_admin_codes(array $feature, string $citymunCode, string $provCode, string $regCode): bool
{
    if ($citymunCode === '' && $provCode === '' && $regCode === '') {
        return true;
    }
    $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
    $featureCitymun = normalize_code(property_value($properties, ['adm3_psgc', 'citymun_code', 'city_code', 'municipality_code']));
    $featureProv = normalize_code(property_value($properties, ['adm2_psgc', 'prov_code', 'province_code']));
    $featureReg = normalize_code(property_value($properties, ['adm1_psgc', 'reg_code', 'region_code']));
    $sourcePsgc = normalize_code(property_value($properties, ['source_psgc_code', 'psgc']));
    $sourceCitymun = strlen($sourcePsgc) >= 6 ? substr($sourcePsgc, 0, 6) : '';

    return ($citymunCode === '' || code_matches($featureCitymun, $citymunCode) || code_matches($sourceCitymun, $citymunCode))
        && ($provCode === '' || code_matches($featureProv, $provCode))
        && ($regCode === '' || code_matches($featureReg, $regCode));
}

function load_geojson_features(string $path, string $brgyCode, string $barangay, string $city, string $citymunCode, string $provCode, string $regCode): array
{
    if ($brgyCode !== '' || $barangay !== '' || $city !== '') {
        $feature = load_geojson_feature($path, $brgyCode, $barangay, $city);
        return [
            'features' => [$feature],
            'label' => feature_label($feature),
        ];
    }

    if (!is_file($path)) {
        fail("GeoJSON file not found: {$path}");
    }
    $json = file_get_contents($path);
    if ($json === false) {
        fail("Unable to read GeoJSON file: {$path}");
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fail("Invalid GeoJSON JSON: {$path}");
    }
    $features = $data['type'] === 'FeatureCollection' ? ($data['features'] ?? []) : [$data];
    if (!is_array($features)) {
        fail('GeoJSON does not contain features.');
    }
    $features = array_values(array_filter($features, static fn($feature): bool => is_array($feature) && isset($feature['geometry']) && feature_matches_admin_codes($feature, $citymunCode, $provCode, $regCode)));
    if ($features === []) {
        fail('GeoJSON does not contain usable geometry features for the requested administrative code filters.');
    }
    return [
        'features' => $features,
        'label' => count($features) . ' boundary feature(s)',
    ];
}

function geometry_polygons(array $geometry): array
{
    $type = $geometry['type'] ?? '';
    $coordinates = $geometry['coordinates'] ?? null;
    if (!is_array($coordinates)) {
        fail('GeoJSON geometry has no coordinates.');
    }
    if ($type === 'Polygon') {
        return [$coordinates];
    }
    if ($type === 'MultiPolygon') {
        return $coordinates;
    }
    fail("Unsupported GeoJSON geometry type: {$type}");
}

function validate_lon_lat(float $lon, float $lat, string $label): void
{
    if (!is_finite($lon) || !is_finite($lat) || $lon < -180.0 || $lon > 180.0 || $lat < -90.0 || $lat > 90.0) {
        fail("{$label} has invalid lon/lat coordinates.");
    }
}

function validate_bbox(array $bbox): array
{
    [$minLon, $minLat, $maxLon, $maxLat] = $bbox;
    validate_lon_lat((float)$minLon, (float)$minLat, 'Bounding box southwest corner');
    validate_lon_lat((float)$maxLon, (float)$maxLat, 'Bounding box northeast corner');
    if ($minLon > $maxLon) {
        fail('Invalid bounding box coordinates. Antimeridian-crossing boxes are not supported; split the area into two runs.');
    }
    if ($minLat > $maxLat) {
        fail('Invalid bounding box coordinates.');
    }
    return [
        (float)$minLon,
        max((float)$minLat, -85.05112878),
        (float)$maxLon,
        min((float)$maxLat, 85.05112878),
    ];
}

function validate_polygons(array $polygons): void
{
    foreach ($polygons as $polygonIndex => $polygon) {
        if (!is_array($polygon) || count($polygon) === 0 || !is_array($polygon[0]) || count($polygon[0]) < 4) {
            fail("GeoJSON polygon {$polygonIndex} has no valid exterior ring.");
        }
        foreach ($polygon as $ringIndex => $ring) {
            if (!is_array($ring) || count($ring) < 4) {
                fail("GeoJSON polygon {$polygonIndex} ring {$ringIndex} must contain at least four positions.");
            }
            foreach ($ring as $pointIndex => $point) {
                if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
                    fail("GeoJSON polygon {$polygonIndex} ring {$ringIndex} contains an invalid position at index {$pointIndex}.");
                }
                validate_lon_lat((float)$point[0], (float)$point[1], "GeoJSON polygon {$polygonIndex} ring {$ringIndex}");
            }
        }
    }
}

function polygons_bbox(array $polygons): array
{
    $bbox = [INF, INF, -INF, -INF];
    foreach ($polygons as $polygon) {
        foreach ($polygon as $ring) {
            foreach ($ring as $point) {
                if (!is_array($point) || count($point) < 2) {
                    continue;
                }
                $lon = (float)$point[0];
                $lat = (float)$point[1];
                $bbox[0] = min($bbox[0], $lon);
                $bbox[1] = min($bbox[1], $lat);
                $bbox[2] = max($bbox[2], $lon);
                $bbox[3] = max($bbox[3], $lat);
            }
        }
    }
    if (!is_finite($bbox[0])) {
        fail('Unable to compute geometry bounds.');
    }
    return $bbox;
}

function point_in_ring(float $lon, float $lat, array $ring): bool
{
    $inside = false;
    $count = count($ring);
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        if (!isset($ring[$i][0], $ring[$i][1], $ring[$j][0], $ring[$j][1])) {
            continue;
        }
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

function point_in_polygons(float $lon, float $lat, array $polygons): bool
{
    foreach ($polygons as $polygon) {
        if (!is_array($polygon) || count($polygon) === 0 || !point_in_ring($lon, $lat, $polygon[0])) {
            continue;
        }
        for ($i = 1; $i < count($polygon); $i++) {
            if (point_in_ring($lon, $lat, $polygon[$i])) {
                continue 2;
            }
        }
        return true;
    }
    return false;
}

function bbox_from_center(float $lat, float $lon, float $radiusKm): array
{
    validate_lon_lat($lon, $lat, '--center');
    $latDelta = $radiusKm / 110.574;
    $lonDelta = $radiusKm / (111.320 * max(cos(deg2rad($lat)), 0.01));
    return validate_bbox([
        max($lon - $lonDelta, -180.0),
        max($lat - $latDelta, -90.0),
        min($lon + $lonDelta, 180.0),
        min($lat + $latDelta, 90.0),
    ]);
}

function lon_to_tile_x(float $lon, int $zoom): int
{
    $n = 2 ** $zoom;
    return (int)floor(($lon + 180.0) / 360.0 * $n);
}

function lat_to_tile_y(float $lat, int $zoom): int
{
    $lat = max(min($lat, 85.05112878), -85.05112878);
    $rad = deg2rad($lat);
    $n = 2 ** $zoom;
    return (int)floor((1.0 - log(tan($rad) + 1.0 / cos($rad)) / M_PI) / 2.0 * $n);
}

function tile_x_to_lon(int $x, int $zoom): float
{
    return $x / (2 ** $zoom) * 360.0 - 180.0;
}

function tile_y_to_lat(int $y, int $zoom): float
{
    $n = M_PI - 2.0 * M_PI * $y / (2 ** $zoom);
    return rad2deg(atan(sinh($n)));
}

function tile_center(int $x, int $y, int $zoom): array
{
    return [
        (tile_x_to_lon($x, $zoom) + tile_x_to_lon($x + 1, $zoom)) / 2.0,
        (tile_y_to_lat($y, $zoom) + tile_y_to_lat($y + 1, $zoom)) / 2.0,
    ];
}

function tile_bounds(int $x, int $y, int $zoom): array
{
    $west = tile_x_to_lon($x, $zoom);
    $east = tile_x_to_lon($x + 1, $zoom);
    $north = tile_y_to_lat($y, $zoom);
    $south = tile_y_to_lat($y + 1, $zoom);
    return [$west, $south, $east, $north];
}

function point_in_bbox(float $lon, float $lat, array $bbox): bool
{
    return $lon >= $bbox[0] && $lon <= $bbox[2] && $lat >= $bbox[1] && $lat <= $bbox[3];
}

function orientation(float $ax, float $ay, float $bx, float $by, float $cx, float $cy): float
{
    return ($by - $ay) * ($cx - $bx) - ($bx - $ax) * ($cy - $by);
}

function on_segment(float $ax, float $ay, float $bx, float $by, float $cx, float $cy): bool
{
    $epsilon = 1.0E-12;
    return $bx <= max($ax, $cx) + $epsilon
        && $bx + $epsilon >= min($ax, $cx)
        && $by <= max($ay, $cy) + $epsilon
        && $by + $epsilon >= min($ay, $cy);
}

function segments_intersect(array $a, array $b, array $c, array $d): bool
{
    [$ax, $ay] = [(float)$a[0], (float)$a[1]];
    [$bx, $by] = [(float)$b[0], (float)$b[1]];
    [$cx, $cy] = [(float)$c[0], (float)$c[1]];
    [$dx, $dy] = [(float)$d[0], (float)$d[1]];

    $o1 = orientation($ax, $ay, $bx, $by, $cx, $cy);
    $o2 = orientation($ax, $ay, $bx, $by, $dx, $dy);
    $o3 = orientation($cx, $cy, $dx, $dy, $ax, $ay);
    $o4 = orientation($cx, $cy, $dx, $dy, $bx, $by);
    $epsilon = 1.0E-12;

    if (($o1 > $epsilon && $o2 < -$epsilon || $o1 < -$epsilon && $o2 > $epsilon)
        && ($o3 > $epsilon && $o4 < -$epsilon || $o3 < -$epsilon && $o4 > $epsilon)) {
        return true;
    }
    if (abs($o1) <= $epsilon && on_segment($ax, $ay, $cx, $cy, $bx, $by)) {
        return true;
    }
    if (abs($o2) <= $epsilon && on_segment($ax, $ay, $dx, $dy, $bx, $by)) {
        return true;
    }
    if (abs($o3) <= $epsilon && on_segment($cx, $cy, $ax, $ay, $dx, $dy)) {
        return true;
    }
    if (abs($o4) <= $epsilon && on_segment($cx, $cy, $bx, $by, $dx, $dy)) {
        return true;
    }
    return false;
}

function segment_intersects_bbox(array $a, array $b, array $bbox): bool
{
    if (point_in_bbox((float)$a[0], (float)$a[1], $bbox) || point_in_bbox((float)$b[0], (float)$b[1], $bbox)) {
        return true;
    }
    [$west, $south, $east, $north] = $bbox;
    $edges = [
        [[$west, $south], [$west, $north]],
        [[$west, $north], [$east, $north]],
        [[$east, $north], [$east, $south]],
        [[$east, $south], [$west, $south]],
    ];
    foreach ($edges as $edge) {
        if (segments_intersect($a, $b, $edge[0], $edge[1])) {
            return true;
        }
    }
    return false;
}

function tile_intersects_polygons(int $x, int $y, int $zoom, array $polygons): bool
{
    $bounds = tile_bounds($x, $y, $zoom);
    [$west, $south, $east, $north] = $bounds;
    $checks = [
        tile_center($x, $y, $zoom),
        [$west, $south],
        [$west, $north],
        [$east, $south],
        [$east, $north],
    ];
    foreach ($checks as $point) {
        if (point_in_polygons($point[0], $point[1], $polygons)) {
            return true;
        }
    }
    foreach ($polygons as $polygon) {
        foreach ($polygon as $ring) {
            $count = count($ring);
            for ($i = 0; $i < $count; $i++) {
                $a = $ring[$i];
                $b = $ring[($i + 1) % $count];
                if (!isset($a[0], $a[1], $b[0], $b[1])) {
                    continue;
                }
                if (point_in_polygons((float)$a[0], (float)$a[1], $polygons) && point_in_bbox((float)$a[0], (float)$a[1], $bounds)) {
                    return true;
                }
                if (segment_intersects_bbox($a, $b, $bounds)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function tiles_for_area(array $bbox, array $zooms, ?array $polygons): array
{
    [$minLon, $minLat, $maxLon, $maxLat] = validate_bbox($bbox);

    $tiles = [];
    foreach ($zooms as $zoom) {
        $n = (2 ** $zoom) - 1;
        $x1 = max(0, min($n, lon_to_tile_x($minLon, $zoom)));
        $x2 = max(0, min($n, lon_to_tile_x($maxLon, $zoom)));
        $y1 = max(0, min($n, lat_to_tile_y($maxLat, $zoom)));
        $y2 = max(0, min($n, lat_to_tile_y($minLat, $zoom)));
        for ($x = min($x1, $x2); $x <= max($x1, $x2); $x++) {
            for ($y = min($y1, $y2); $y <= max($y1, $y2); $y++) {
                if ($polygons !== null && !tile_intersects_polygons($x, $y, $zoom, $polygons)) {
                    continue;
                }
                $tiles[] = ['z' => $zoom, 'x' => $x, 'y' => $y];
            }
        }
    }
    return $tiles;
}

function tile_path(string $type, array $tile): string
{
    $ext = in_array($type, ['raster', 'terrain'], true) ? 'png' : 'pbf';
    return "/tiles/{$type}/{$tile['z']}/{$tile['x']}/{$tile['y']}.{$ext}";
}

function fetch_tile(string $url, int $timeout, array $curlOptions): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'bytes' => 0, 'cache' => '', 'error' => 'curl_init failed'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_HTTPHEADER => ['Accept-Encoding: gzip, deflate'],
    ] + $curlOptions);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'status' => $status, 'bytes' => 0, 'cache' => '', 'error' => $error];
    }
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    preg_match('/^X-Cache:\s*(.+)$/mi', $headers, $m);
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'bytes' => strlen($body),
        'cache' => isset($m[1]) ? trim($m[1]) : '',
        'error' => $status >= 200 && $status < 300 ? '' : "HTTP {$status}",
    ];
}

function write_report(string $path, array $report): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail("Unable to create report directory: {$dir}");
    }
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL) === false) {
        fail("Unable to write report: {$path}");
    }
}

$started = date('c');
$args = parse_args($argv);
if ($args['help']) {
    echo usage();
    exit(0);
}
if (!extension_loaded('curl')) {
    fail('PHP cURL extension is required. Run with C:\\wamp64\\bin\\php\\php8.2.29\\php.exe.');
}

$zooms = parse_number_list((string)$args['zooms'], 0, 22);
$types = array_map('trim', explode(',', strtolower((string)$args['types'])));
$types = array_values(array_unique(array_filter($types, static fn(string $type): bool => $type !== '')));
$allowedTypes = ['raster', 'vector', 'terrain', 'poi'];
foreach ($types as $type) {
    if (!in_array($type, $allowedTypes, true)) {
        fail("Unsupported tile type: {$type}");
    }
}
if ($types === []) {
    fail('--types must include at least one tile type.');
}

$source = [
    'type' => '',
    'label' => '',
];
$polygons = null;
if ((string)$args['source-geojson'] !== '') {
    $loaded = load_geojson_features((string)$args['source-geojson'], (string)$args['brgy-code'], (string)$args['barangay'], (string)$args['city'], (string)$args['citymun-code'], (string)$args['prov-code'], (string)$args['reg-code']);
    $polygons = [];
    foreach ($loaded['features'] as $feature) {
        $polygons = array_merge($polygons, geometry_polygons($feature['geometry']));
    }
    validate_polygons($polygons);
    $bbox = validate_bbox(polygons_bbox($polygons));
    $source = ['type' => 'geojson', 'label' => (string)$loaded['label'], 'features' => count($loaded['features'])];
} elseif ((string)$args['bbox'] !== '') {
    $bbox = validate_bbox(parse_csv_floats((string)$args['bbox'], 4, '--bbox'));
    $source = ['type' => 'bbox', 'label' => implode(',', $bbox)];
} elseif ((string)$args['center'] !== '') {
    [$lat, $lon] = parse_csv_floats((string)$args['center'], 2, '--center');
    $bbox = bbox_from_center($lat, $lon, parse_positive_float((string)$args['radius-km'], '--radius-km'));
    $source = ['type' => 'center-radius', 'label' => "{$lat},{$lon} radius {$args['radius-km']}km"];
} else {
    fail('Provide --source-geojson, --bbox, or --center.');
}

$tiles = tiles_for_area($bbox, $zooms, $polygons);
$attemptsPlanned = count($tiles) * count($types);
$maxTiles = parse_positive_int((string)$args['max-tiles'], '--max-tiles');
if (!$args['dry-run'] && $attemptsPlanned > $maxTiles) {
    fail("Refusing to fetch {$attemptsPlanned} tile requests because it exceeds --max-tiles {$maxTiles}. Narrow the area/zooms or raise --max-tiles.");
}

$baseUrl = rtrim((string)$args['base-url'], '/');
$limit = (string)$args['limit'] === '' ? 0 : parse_positive_int((string)$args['limit'], '--limit');
$timeout = parse_positive_int((string)$args['timeout'], '--timeout');
$caBundle = (string)$args['ca-bundle'];
if ($caBundle === '') {
    $caBundle = env_value('TILES_CURL_CA_BUNDLE') !== '' ? env_value('TILES_CURL_CA_BUNDLE') : env_value('CURL_CA_BUNDLE');
}
if ($caBundle === '') {
    $caBundle = env_value('SSL_CERT_FILE');
}
$sslVerify = !$args['no-ssl-verify'];
if (!$args['no-ssl-verify']) {
    $sslVerify = parse_bool_like(env_value('TILES_CURL_SSL_VERIFY'), true);
}
$curlOptions = [];
if (!$sslVerify) {
    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
} elseif ($caBundle !== '') {
    if (!is_file($caBundle)) {
        fail("CA bundle file not found: {$caBundle}");
    }
    $curlOptions[CURLOPT_CAINFO] = $caBundle;
}
$results = [
    'attempted' => 0,
    'succeeded' => 0,
    'failed' => 0,
    'bytes' => 0,
    'by_type' => [],
    'errors' => [],
];
foreach ($types as $type) {
    $results['by_type'][$type] = ['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'bytes' => 0, 'cache' => []];
}

if (!$args['dry-run']) {
    foreach ($tiles as $tile) {
        foreach ($types as $type) {
            if ($limit > 0 && $results['attempted'] >= $limit) {
                break 2;
            }
            $url = $baseUrl . tile_path($type, $tile);
            $result = fetch_tile($url, $timeout, $curlOptions);
            $results['attempted']++;
            $results['by_type'][$type]['attempted']++;
            $results['bytes'] += $result['bytes'];
            $results['by_type'][$type]['bytes'] += $result['bytes'];
            $cacheKey = $result['cache'] !== '' ? $result['cache'] : 'UNKNOWN';
            $results['by_type'][$type]['cache'][$cacheKey] = ($results['by_type'][$type]['cache'][$cacheKey] ?? 0) + 1;
            if ($result['ok']) {
                $results['succeeded']++;
                $results['by_type'][$type]['succeeded']++;
            } else {
                $results['failed']++;
                $results['by_type'][$type]['failed']++;
                if (count($results['errors']) < 25) {
                    $results['errors'][] = [
                        'url' => $url,
                        'status' => $result['status'],
                        'error' => $result['error'],
                    ];
                }
            }
        }
    }
}

$report = [
    'schema_version' => 1,
    'tool' => 'pbb-mapserver-tile-populator',
    'version' => POPULATOR_VERSION,
    'started_at' => $started,
    'finished_at' => date('c'),
    'status' => $args['dry-run'] || $results['failed'] === 0 ? 'success' : 'partial',
    'dry_run' => (bool)$args['dry-run'],
    'source' => $source,
    'base_url' => $baseUrl,
    'bbox' => [
        'min_lon' => $bbox[0],
        'min_lat' => $bbox[1],
        'max_lon' => $bbox[2],
        'max_lat' => $bbox[3],
    ],
    'zooms' => $zooms,
    'types' => $types,
    'tls' => [
        'ssl_verify' => $sslVerify,
        'ca_bundle_configured' => $caBundle !== '',
    ],
    'coverage' => [
        'tiles' => count($tiles),
        'requests_planned' => $attemptsPlanned,
    ],
    'results' => $results,
];

write_report((string)$args['report'], $report);
echo json_encode([
    'status' => $report['status'],
    'dry_run' => $report['dry_run'],
    'tiles' => $report['coverage']['tiles'],
    'requests_planned' => $report['coverage']['requests_planned'],
    'attempted' => $results['attempted'],
    'succeeded' => $results['succeeded'],
    'failed' => $results['failed'],
    'report' => (string)$args['report'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($report['status'] === 'partial' ? 1 : 0);
