# Local coordinate-to-area lookup (V1)

`GET /api/areas/lookup?lat={latitude}&lon={longitude}` resolves a WGS84 point
against installed barangay geometry. This is geographic containment, not a
geocoder, nearest-area search, routing service or road-coverage check.

## Input and HTTP behavior

Supply `lat` and `lon` exactly once as decimal scalar numbers. Scientific notation
is accepted. Latitude is inclusive `[-90,90]`, longitude `[-180,180]`. Values must
be finite. Missing, malformed, repeated (including URL-encoded duplicate keys),
array or mixed scalar/array inputs return HTTP 400 before dataset access.
A literal `+` in a query is a space; encode a numeric plus sign as `%2B`.
Other query parameters are ignored. GET is the only supported method.

Responses use JSON, CORS `*`, and `Cache-Control: no-store`.
Clients cannot select a file path, dataset or upstream URL.

## Successful responses

HTTP 200 contains `schema_version: 1`, normalized numeric `query`, `outcome`,
`matches`, `coverage`, and `dataset`. Outcomes:

- `matched`: exactly one candidate, even when its relation is `boundary`.
- `ambiguous`: multiple candidates, including shared edges, overlapping
  interiors, or conflicting geometries with the same canonical code.
- `coverage_unknown`: no candidates in the installed sources.

All successful responses have `coverage.completeness: "unknown"`. A match does
not certify that every possible candidate was found. An empty result does not
prove no administrative area exists or that the point is outside an authoritative
coverage domain. V1 has no `no_match` or `outside_installed_coverage` outcome.
Routing must handle uncertainty explicitly and validate its own graph coverage.

Each match includes canonical string codes from the installed CSV, explicit
administrative hierarchy, names, a distinct shapefile source alias, named WGS84
bbox bounds, `interior`/`boundary` relation and a relative boundary reference.
Region/province names are `null` because the bundled CSVs do not provide them.
City names are joined using explicit `adm3_psgc`, not sliced codes. Existing
correction records resolve source aliases to canonical identities; V1 does not
invent additional legacy aliases. The installed code format is preserved.

`boundary` is relative to the **MapServer service base URL**: resolve it against
`http://localhost/mapserver/`, for example, not against the lookup endpoint URL.
`geometry_id` is SHA-256 of normalized geometry; full geometry is not embedded.
The bbox is a feature extent, not routing coverage or a buffer.

Matches sort lexically by barangay code, then geometry ID and source code.
Identical canonical/source/geometry records across sources are deduplicated;
different geometries remain separate candidates even when their codes agree.
Conflicting canonical metadata or alias mappings fail rather than select a record.

Example (hashes abbreviated only for documentation):

```json
{
  "schema_version": 1,
  "query": {"lat": 10.317, "lon": 123.892},
  "outcome": "matched",
  "matches": [{
    "barangay_code": "730600020",
    "name": "Capitol Site",
    "hierarchy": {
      "region": {"code": "700000000", "name": null},
      "province": {"code": "730600000", "name": null},
      "city": {"code": "730600000", "name": "City of Cebu"},
      "barangay": {"code": "730600020", "name": "Capitol Site"}
    },
    "aliases": {"source_psgc_code": "702217020"},
    "bbox": {"west": 123.88836978600011, "south": 10.30806709500007,
             "east": 123.89546137400009, "north": 10.325038440000071},
    "relation": "interior",
    "boundary": "boundaries/barangay/730600020.geojson",
    "geometry_id": "<sha256>"
  }],
  "coverage": {"completeness": "unknown"},
  "dataset": {
    "source": "https://github.com/altcoder/philippines-psgc-shapefiles",
    "version": "2026-05-22T02:15:00Z",
    "content_id": "<sha256>",
    "build": "pbb-mapserver-20260621-113628",
    "algorithm": "1.0.0",
    "boundary_tolerance_degrees": 1e-9,
    "installed_scopes": [{"scope": "national", "code": null}]
  }
}
```

An unmatched result uses the same envelope with `outcome: "coverage_unknown"`,
`matches: []`, and the query, coverage and provenance retained. Ambiguous results
include every candidate, each with its own relation.

## Geometry and tolerance

Lookup uses original WGS84 barangay Polygon/MultiPolygon geometry, preserving
holes and islands. It never tests the dissolved parent perimeter. A hole interior
is excluded; a hole edge is `boundary`; all containing shared-edge candidates
are returned.

Revision `1.0.0` uses point-to-segment Euclidean distance in longitude/latitude
space with fixed numerical tolerance **1e-9 degrees**. The same tolerance expands
the bbox test. This is solely an edge classification rule, not positional
accuracy, a physical distance or a routing buffer. Fixtures pin distances of
0.5e-9 and 2e-9 degrees from outer and hole edges.

## Local data, failures and freshness

Runtime requires PHP CLI 8.2+, ZipArchive and enabled `exec`, consistent with the
existing boundary worker. The HTTP adapter uses `boundary_php_binary()` and runs
the worker with outbound URL opening and cURL/socket entry points disabled.
There is no download, geocoder or upstream path.

Required sources: the boundary `manifest.json`, both administrative CSVs, and at
least one national ZIP or province pack. All installed sources are inspected,
including packs alongside a national ZIP. Each installed province directory
requires `pack.json` and its canonical `BgySubMuns.shp.zip`. A missing/unusable
pack fails even if another source could answer, avoiding a spatial completeness
index. Declared national/pack checksums are verified.

The worker streams SHP/DBF from ZIP using existing ring-assembly/format helpers;
it checks headers, lengths/counts, polygon structure, candidate coordinates and
metadata. It never extracts the national geometry or silently replaces a source.

HTTP 503 example:

```json
{"schema_version":1,"query":{"lat":10.317,"lon":123.892},"error":{
  "code":"dataset_unavailable",
  "message":"Installed boundary data is missing or unusable. Verify the boundary ZIP, metadata CSVs and manifests; consult the server log."
}}
```

Technical causes go to the server log. Public errors omit local paths and
credentials. Worker startup/protocol failure also produces 503 with an actionable
worker error. HTTP 400 example:

```json
{"schema_version":1,"error":{"code":"invalid_coordinates","message":"Supply lat and lon exactly once as scalar numbers."}}
```

V1 has no lookup-result or extracted-data cache. Every request reads current
installed bytes. `dataset.content_id` hashes all input files plus algorithm and
tolerance revision. Rehashing before success detects concurrent replacement and
fails explicitly. Changed datasets cannot reuse prior matches. This trades
national-scan latency for a small implementation; it is not a high-throughput
indexed endpoint.

Existing overlay modes, index field names and error envelopes remain unchanged.
Run `php tests/area-lookup.php`; see [review evidence](area-lookup-evidence.md)
for dated checks and limitations. Production acceptance remains with the reviewer.
