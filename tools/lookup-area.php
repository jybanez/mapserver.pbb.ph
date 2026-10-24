<?php
declare(strict_types=1);

// Isolated worker: the existing prep readers have global names shared by index.php.
define('MAPSERVER_BOUNDARY_LIBRARY_ONLY', true);
require_once __DIR__ . '/prepare-boundaries.php';

const AREA_LOOKUP_REVISION = '1.0.0';
const AREA_LOOKUP_TOLERANCE = 1.0E-9; // Euclidean coordinate degrees, numerical only.

function lookup_json(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Required dataset metadata is unreadable.');
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid dataset metadata.');
    }
    return $data;
}

function lookup_coordinates(string $query): array
{
    $values = [];
    foreach (explode('&', $query) as $part) {
        [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
        $key = urldecode($key);
        if (preg_match('/^(lat|lon)(?:\[|$)/D', $key, $m)) {
            if ($key !== $m[1] || array_key_exists($key, $values)) {
                throw new InvalidArgumentException('Supply lat and lon exactly once as scalar numbers.');
            }
            $values[$key] = urldecode($value);
        }
    }
    $result = [];
    foreach (['lat' => 90, 'lon' => 180] as $key => $limit) {
        $raw = $values[$key] ?? '';
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $raw)
            || !is_finite($number = (float)$raw) || abs($number) > $limit) {
            throw new InvalidArgumentException('Supply finite WGS84 lat [-90,90] and lon [-180,180].');
        }
        $result[$key] = $number;
    }
    return $result;
}

function lookup_ring_relation(array $point, array $ring): string
{
    [$x, $y] = $point;
    for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
        [$ax, $ay] = $ring[$j];
        [$bx, $by] = $ring[$i];
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $length2 = $dx * $dx + $dy * $dy;
        $t = $length2 > 0 ? max(0, min(1, (($x - $ax) * $dx + ($y - $ay) * $dy) / $length2)) : 0;
        if (hypot($x - ($ax + $t * $dx), $y - ($ay + $t * $dy)) <= AREA_LOOKUP_TOLERANCE) {
            return 'boundary';
        }
    }
    return point_in_ring($point, $ring) ? 'interior' : 'outside';
}

function lookup_geometry_relation(array $point, array $geometry): string
{
    $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
    $relation = 'outside';
    foreach ($polygons as $rings) {
        $outer = lookup_ring_relation($point, $rings[0]);
        if ($outer === 'outside') {
            continue;
        }
        $insideHole = false;
        foreach (array_slice($rings, 1) as $hole) {
            $holeRelation = lookup_ring_relation($point, $hole);
            if ($holeRelation === 'boundary') {
                return 'boundary';
            }
            $insideHole = $insideHole || $holeRelation === 'interior';
        }
        if (!$insideHole) {
            if ($outer === 'boundary') {
                return 'boundary';
            }
            $relation = 'interior';
        }
    }
    return $relation;
}

function lookup_read($stream, int $length): string
{
    if ($length < 0 || $length > 256 * 1024 * 1024) {
        throw new RuntimeException('Invalid shapefile record length.');
    }
    $bytes = '';
    while (strlen($bytes) < $length && !feof($stream)) {
        $chunk = fread($stream, min(1048576, $length - strlen($bytes)));
        if ($chunk === false || $chunk === '') {
            break;
        }
        $bytes .= $chunk;
    }
    if (strlen($bytes) !== $length) {
        throw new RuntimeException('Truncated boundary data.');
    }
    return $bytes;
}

/** Read SHP/DBF in lockstep directly from the ZIP. No extraction or fallback. */
function lookup_records(string $path, callable $callback): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('Cannot open installed boundary ZIP.');
    }
    $shp = $dbf = null;
    try {
        $base = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name) || !str_ends_with($name, '.shp.shp')) {
                continue;
            }
            $candidate = substr($name, 0, -4);
            $projection = $zip->getFromName($candidate . '.prj');
            if (is_string($projection) && stripos($projection, 'GEOGCS') !== false && stripos($projection, 'PROJCS') === false) {
                if ($base !== null) {
                    throw new RuntimeException('Multiple WGS84 shapefiles in boundary ZIP.');
                }
                $base = $candidate;
            }
        }
        if ($base === null || !($shp = $zip->getStream($base . '.shp')) || !($dbf = $zip->getStream($base . '.dbf'))) {
            throw new RuntimeException('WGS84 SHP/DBF/PRJ set is missing.');
        }
        $header = lookup_read($dbf, 32);
        $count = unpack('V', substr($header, 4, 4))[1];
        $headerLength = unpack('v', substr($header, 8, 2))[1];
        $recordLength = unpack('v', substr($header, 10, 2))[1];
        if ($count < 1 || $headerLength < 33 || ($headerLength - 33) % 32 !== 0 || $recordLength < 2) {
            throw new RuntimeException('Invalid DBF structure.');
        }
        $descriptors = lookup_read($dbf, $headerLength - 32);
        $fields = [];
        $total = 1;
        for ($i = 0; $i < strlen($descriptors) - 1; $i += 32) {
            $field = substr($descriptors, $i, 32);
            $fields[] = ['name' => rtrim(substr($field, 0, 11), "\0 "), 'type' => $field[11], 'length' => ord($field[16])];
            $total += ord($field[16]);
        }
        if ($total !== $recordLength || substr($descriptors, -1) !== "\r") {
            throw new RuntimeException('Invalid DBF fields.');
        }
        $shpHeader = lookup_read($shp, 100);
        $shapeBytes = read_be_int($shpHeader, 24) * 2;
        if (read_be_int($shpHeader, 0) !== 9994 || read_le_int($shpHeader, 28) !== 1000
            || !in_array(read_le_int($shpHeader, 32), [5, 15, 25], true)) {
            throw new RuntimeException('Invalid polygon shapefile header.');
        }
        $consumed = 100;
        for ($i = 0; $i < $count; $i++) {
            $record = parse_dbf_record(lookup_read($dbf, $recordLength), $fields);
            $shapeHeader = lookup_read($shp, 8);
            $length = read_be_int($shapeHeader, 4) * 2;
            $content = lookup_read($shp, $length);
            $consumed += 8 + $length;
            if ($length < 4) {
                throw new RuntimeException('Empty shapefile record.');
            }
            $type = read_le_int($content, 0);
            if ($type !== 0) {
                if (!in_array($type, [5, 15, 25], true) || $length < 44) {
                    throw new RuntimeException('Unsupported boundary geometry.');
                }
                $parts = read_le_int($content, 36);
                $points = read_le_int($content, 40);
                if ($parts < 1 || $points < 4 || 44 + 4 * $parts + 16 * $points > $length) {
                    throw new RuntimeException('Invalid polygon record.');
                }
                $previous = -1;
                for ($p = 0; $p < $parts; $p++) {
                    $start = read_le_int($content, 44 + 4 * $p);
                    $end = $p + 1 < $parts ? read_le_int($content, 48 + 4 * $p) : $points;
                    if (($p === 0 && $start !== 0) || $start <= $previous || $end - $start < 4 || $end > $points) {
                        throw new RuntimeException('Invalid polygon rings.');
                    }
                    $previous = $start;
                }
            }
            $callback($record, $content);
        }
        if ($consumed !== $shapeBytes || fread($shp, 1) !== '') {
            throw new RuntimeException('Shapefile record count or size mismatch.');
        }
    } finally {
        if (is_resource($shp)) { fclose($shp); }
        if (is_resource($dbf)) { fclose($dbf); }
        $zip->close();
    }
}

function lookup_metadata(string $root): array
{
    $cities = [];
    foreach (read_csv_rows($root . '/PH_Adm3_MuniCities.csv') as $row) {
        if (!preg_match('/^\d+$/D', $row['adm3_psgc'] ?? '')) {
            throw new RuntimeException('Invalid city metadata.');
        }
        $cities[$row['adm3_psgc']] = $row;
    }
    $barangays = [];
    foreach (read_csv_rows($root . '/PH_Adm4_BgySubMuns.csv') as $row) {
        foreach (['adm1_psgc', 'adm2_psgc', 'adm3_psgc', 'adm4_psgc'] as $field) {
            if (!preg_match('/^\d+$/D', $row[$field] ?? '')) {
                throw new RuntimeException('Invalid administrative metadata.');
            }
        }
        $city = $cities[$row['adm3_psgc']] ?? null;
        $row['citymun_name'] = $city['adm3_en'] ?? null;
        $code = $row['adm4_psgc'];
        if (isset($barangays[$code]) && $barangays[$code] !== $row) {
            throw new RuntimeException('Conflicting canonical barangay metadata.');
        }
        $barangays[$code] = $row;
    }
    if (!$barangays || !$cities) {
        throw new RuntimeException('Empty administrative metadata.');
    }
    return $barangays;
}

function lookup_response(string $query, string $root, string $build): array
{
    try {
        $point = lookup_coordinates($query);
    } catch (InvalidArgumentException $error) {
        return [400, ['schema_version' => 1, 'error' => ['code' => 'invalid_coordinates', 'message' => $error->getMessage()]]];
    }
    try {
        $manifest = lookup_json($root . '/manifest.json');
        if (!is_string($manifest['source_repository'] ?? null) || !is_string($manifest['captured_at'] ?? null)) {
            throw new RuntimeException('Boundary source provenance is missing.');
        }
        $metadata = lookup_metadata($root);
        $paths = [$root . '/manifest.json', $root . '/PH_Adm3_MuniCities.csv', $root . '/PH_Adm4_BgySubMuns.csv'];
        $datasets = [];
        $expectedHashes = [];
        foreach ($manifest['files'] ?? [] as $file) {
            if (is_string($file['path'] ?? null) && is_string($file['sha256'] ?? null)) {
                $expectedHashes[$file['path']] = $file['sha256'];
            }
        }
        $national = $root . '/PH_Adm4_BgySubMuns.shp.zip';
        // Inspect every installed source, including packs alongside a national ZIP.
        if (is_file($national)) {
            $datasets[] = ['path' => $national, 'scope' => 'national', 'code' => null];
        }
        foreach (glob($root . '/provinces/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $pack = lookup_json($dir . '/pack.json');
            if (($pack['scope'] ?? '') !== 'province' || !is_string($pack['province_code'] ?? null)
                || !preg_match('/^\d+$/D', $pack['province_code'])) {
                throw new RuntimeException('Invalid installed pack metadata.');
            }
            $paths[] = $dir . '/pack.json';
            if (isset($pack['outputs']['source_zip_sha256'])) {
                $expectedHashes[substr($dir, strlen($root) + 1) . '/BgySubMuns.shp.zip'] = $pack['outputs']['source_zip_sha256'];
            }
            $datasets[] = ['path' => $dir . '/BgySubMuns.shp.zip', 'scope' => 'province', 'code' => $pack['province_code']];
        }
        if (!$datasets) {
            throw new RuntimeException('No installed boundary geometry.');
        }
        foreach ($datasets as $dataset) { $paths[] = $dataset['path']; }
        $fingerprints = [];
        foreach ($paths as $path) {
            $hash = @hash_file('sha256', $path);
            if ($hash === false) { throw new RuntimeException('Required boundary source is unreadable.'); }
            $relative = substr($path, strlen($root) + 1);
            if (isset($expectedHashes[$relative]) && $expectedHashes[$relative] !== $hash) {
                throw new RuntimeException('Boundary source checksum does not match installed metadata.');
            }
            $fingerprints[$relative] = $hash;
        }
        ksort($fingerprints);
        $identity = hash('sha256', json_encode([AREA_LOOKUP_REVISION, AREA_LOOKUP_TOLERANCE, $fingerprints], JSON_THROW_ON_ERROR));
        $rawMatches = [];
        $aliases = [];
        foreach ($datasets as $dataset) {
            lookup_records($dataset['path'], function (array $record, string $content) use (&$rawMatches, &$aliases, $metadata, $point): void {
                $source = normalize_dbf_code($record['psgc_code'] ?? '');
                if (read_le_int($content, 0) === 0) {
                    $corr = normalize_dbf_code($record['corr_code'] ?? '');
                    if ($source !== '' && $corr !== '' && isset($metadata[$source])) {
                        if (isset($aliases[$corr]) && $aliases[$corr] !== $source) {
                            throw new RuntimeException('Conflicting source-code aliases.');
                        }
                        $aliases[$corr] = $source;
                    }
                    return;
                }
                $summary = parse_shape_record($content, false);
                [$w, $s, $e, $n] = $summary['bbox'];
                if (!is_finite($w) || !is_finite($s) || !is_finite($e) || !is_finite($n)
                    || $w < -180 || $e > 180 || $s < -90 || $n > 90 || $w > $e || $s > $n) {
                    throw new RuntimeException('Invalid boundary bounds.');
                }
                $t = AREA_LOOKUP_TOLERANCE;
                if ($point['lon'] < $w - $t || $point['lon'] > $e + $t || $point['lat'] < $s - $t || $point['lat'] > $n + $t) { return; }
                $geometry = parse_shape_record($content, true)['geometry'];
                $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
                foreach ($polygons as $rings) {
                    foreach ($rings as $ring) {
                        foreach ($ring as [$lon, $lat]) {
                            if (!is_finite($lon) || !is_finite($lat) || $lon < $w || $lon > $e || $lat < $s || $lat > $n) {
                                throw new RuntimeException('Invalid polygon coordinates.');
                            }
                        }
                    }
                }
                $relation = lookup_geometry_relation([$point['lon'], $point['lat']], $geometry);
                if ($relation !== 'outside') {
                    $rawMatches[] = ['source' => $source, 'relation' => $relation, 'bbox' => ['west' => $w, 'south' => $s, 'east' => $e, 'north' => $n],
                        'geometry_hash' => hash('sha256', json_encode($geometry, JSON_THROW_ON_ERROR))];
                }
            });
        }
        $matches = [];
        $seen = [];
        foreach ($rawMatches as $raw) {
            $source = $raw['source'];
            $code = $aliases[psgc_compact_legacy($source)] ?? (isset($metadata[$source]) ? $source : null);
            if ($code === null) { throw new RuntimeException('Containing geometry has no canonical metadata mapping.'); }
            $meta = $metadata[$code];
            $key = $code . ':' . $raw['geometry_hash'] . ':' . $source;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $hierarchy = [];
            foreach (['region' => ['adm1_psgc', null], 'province' => ['adm2_psgc', null], 'city' => ['adm3_psgc', 'citymun_name'], 'barangay' => ['adm4_psgc', 'adm4_en']] as $level => [$field, $name]) {
                $hierarchy[$level] = ['code' => (string)$meta[$field], 'name' => $name === null ? null : $meta[$name]];
            }
            $matches[] = ['barangay_code' => (string)$code, 'name' => $meta['adm4_en'], 'hierarchy' => $hierarchy,
                'aliases' => ['source_psgc_code' => $source], 'bbox' => $raw['bbox'], 'relation' => $raw['relation'],
                'boundary' => 'boundaries/barangay/' . $code . '.geojson', 'geometry_id' => $raw['geometry_hash']];
        }
        usort($matches, static fn(array $a, array $b): int => strcmp($a['barangay_code'], $b['barangay_code']) ?: strcmp($a['geometry_id'], $b['geometry_id']) ?: strcmp($a['aliases']['source_psgc_code'], $b['aliases']['source_psgc_code']));
        // No result cache: reread installed bytes on every request. Detect concurrent replacement.
        foreach ($paths as $path) {
            if (@hash_file('sha256', $path) !== $fingerprints[substr($path, strlen($root) + 1)]) {
                throw new RuntimeException('Dataset changed during lookup; retry after installation completes.');
            }
        }
        return [200, ['schema_version' => 1, 'query' => $point,
            'outcome' => count($matches) === 0 ? 'coverage_unknown' : (count($matches) === 1 ? 'matched' : 'ambiguous'),
            'matches' => $matches, 'coverage' => ['completeness' => 'unknown'],
            'dataset' => ['source' => $manifest['source_repository'] ?? null, 'version' => $manifest['captured_at'] ?? null,
                'content_id' => $identity, 'build' => $build, 'algorithm' => AREA_LOOKUP_REVISION, 'boundary_tolerance_degrees' => AREA_LOOKUP_TOLERANCE,
                'installed_scopes' => array_map(static fn(array $d): array => ['scope' => $d['scope'], 'code' => $d['code']], $datasets)]]];
    } catch (Throwable $error) {
        error_log('MapServer area lookup: ' . $error->getMessage());
        return [503, ['schema_version' => 1, 'query' => $point, 'error' => ['code' => 'dataset_unavailable',
            'message' => 'Installed boundary data is missing or unusable. Verify the boundary ZIP, metadata CSVs and manifests; consult the server log.']]];
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $query = base64_decode($argv[1] ?? '', true);
    $build = $argv[2] ?? 'unknown';
    $result = lookup_response($query === false ? '' : $query, dirname(__DIR__) . '/resources/boundaries', $build);
    echo json_encode(['http_status' => $result[0], 'body' => $result[1]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}
