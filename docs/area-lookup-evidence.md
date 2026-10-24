# Coordinate lookup review evidence

Routing Server task 336; branch `codex/area-lookup`.
Date: 2026-10-24, Asia/Manila. Windows WAMP PHP CLI 8.2.29.

## Fixture suite

```powershell
& C:/wamp64/bin/php/php8.2.29/php.exe -d allow_url_fopen=0 -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client tests/area-lookup.php
```

40 checks passed using temporary synthetic SHP/DBF ZIPs and CSVs. No installed
resources were modified. Cases include interior, preserved zero-prefixed codes,
hierarchy/bbox, shared edges, deterministic ordering, overlaps, lone boundary,
holes/islands, both sides of outer/hole tolerance, duplicates/conflicts, invalid
and repeated/array inputs, valid extrema, scoped miss, corrupt/missing data and
dataset replacement changing both identity and actual matches. A truncated DBF,
declared checksum mismatch, and corrupt pack alongside a valid national ZIP also
fail explicitly rather than returning partial results or falling back.

Atlas review regressions: conflicting city metadata returns 503 in either row
order; identical city duplicates succeed. Unclosed original outer/hole rings
return 503 before the shared parser can repair them, including a hole in a
feature outside the query bbox. The legacy overlay parser is unchanged. Lookup
algorithm revision is now 1.0.1, changing its content identity accordingly.

## Real-data HTTP checks

A temporary loopback PHP server used `index.php` as router on port 18980:

- `/api/areas/lookup?lat=10.317&lon=123.892`: 200, Capitol Site, canonical
  `730600020`, source `702217020`, City of Cebu; completeness unknown.
- Repeated `lat` and encoded `lat[]`: 400 `invalid_coordinates`.
- JSON, CORS `*`, `Cache-Control: no-store` headers confirmed.
- `/boundaries/city/072217.geojson`: one perimeter feature.
- `/boundaries/city/072217/children.geojson`: 80 original barangay features.
- PHP syntax checks and `git diff --check` passed.

## Network denial and performance limits

Fixture tests and the HTTP worker run with outbound URL opening and cURL/socket
entry points disabled. Real-data lookup succeeds under these constraints without
tile providers or external geocoders. This is process-level network denial,
not a physical WAN-disconnection exercise of a complete PBB node/browser map.
No broader field acceptance is claimed.

The real national-data request took approximately 5–6 seconds on this workstation.
Requests scan installed sources and hash their contents twice, with no lookup
cache. Concurrency/load and other hardware were not benchmarked. This latency is
explicitly submitted for review; indexing/caching would be separate work if needed.
Streaming avoids loading national geometry into memory or writing national GeoJSON.

The exact tested commit is supplied in the task submission, avoiding a self-
referential hash in this tracked file. No deployment or release acceptance implied.
