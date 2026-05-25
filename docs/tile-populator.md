# Tile Populator

`tools/populate-tiles.php` preloads MapServer cache by requesting tiles from the local MapServer HTTP endpoints. It does not write cache files directly, so normal upstream fetching, headers, logging, and cache paths remain centralized in `index.php`.

Populate requests can exercise two TLS paths:

- Populator-to-MapServer TLS, controlled by `--ca-bundle`, `--no-ssl-verify`, `curl_ca_bundle`, and `curl_ssl_verify`.
- MapServer-to-upstream-provider TLS, controlled by the installed `.env` values consumed by `config.php`, especially `TILES_CURL_CA_BUNDLE` and `TILES_CURL_SSL_VERIFY`.

If a populate report shows every tile with `status: 0` and `SSL certificate problem: unable to get local issuer certificate`, the request did not reach MapServer. Fix the populator-to-MapServer trust path by providing a CA bundle or, only for controlled local setup fallback, disabling SSL verification for the populate run. If disabling verification changes the failure to an HTTP status such as `502`, the TLS handoff is fixed and the remaining problem is MapServer/upstream configuration.

## Boundary Source

MapServer ships the required PSGC boundary source files under `resources/boundaries` so installed nodes do not depend on a live third-party repository during Data Prep. Generated GeoJSON, reports, and extracted shapefile cache files belong under `storage/boundaries`.

Directory ownership:

- `resources/boundaries` is permanent package data and should survive normal install cleanup. It contains `PH_Adm4_BgySubMuns.shp.zip`, `PH_Adm3_MuniCities.csv`, `PH_Adm4_BgySubMuns.csv`, and `manifest.json`.
- `storage/boundaries` is operational output. It may contain extracted shapefiles, generated `.geojson` files, generated `.index.json` files, and boundary prep reports.
- `storage/tiles` is the tile cache populated through MapServer HTTP endpoints.

Data Prep reads the vendored ZIP/CSV files in place. It does not copy the large boundary source files into `storage/boundaries`.

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\prepare-boundaries.php `
  --city "Cebu City" `
  --output storage\boundaries\cebu-city-barangays.geojson `
  --index storage\boundaries\cebu-city-barangays.index.json
```

To prepare a single barangay:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\prepare-boundaries.php `
  --city "Cebu City" `
  --barangay Guadalupe `
  --output storage\boundaries\cebu-city-guadalupe.geojson `
  --index storage\boundaries\cebu-city-guadalupe.index.json
```

`tools/prepare-boundaries.php` reads source ZIP/CSV files from `resources/boundaries` by default, extracts the WGS84 Level 4 shapefile into `storage/boundaries`, writes plain GeoJSON, and writes an index JSON containing barangay names and PSGC codes. It does not require GDAL or Composer dependencies. Download remains a manual fallback only when source files are missing and `--no-download` is not supplied.

The same vendored boundary resources power the public overlay endpoint `GET /boundaries/{scope}/{code}.geojson`. See `docs/boundary-overlay-contract.md` for the browser/API contract used by Hotline and other map clients.

Some Hub/Kit barangay inputs may use legacy Cebu City-style codes such as `072217029` or `072217003`. MapServer accepts these by matching against the shapefile source PSGC code and resolves them to the current PSGC CSV barangay code when writing GeoJSON properties. Verified examples:

| Input code | Resolved barangay | Current `brgy_code` | `source_psgc_code` |
| --- | --- | --- | --- |
| `072217029` | Guadalupe, Cebu City | `730600029` | `702217029` |
| `072217003` | Apas, Cebu City | `730600003` | `702217003` |

City-scope legacy inputs are also accepted. `citymun_code=072217` resolves against source PSGC prefix `702217` and produces the current Cebu City barangay set (`80` features with current `730600xxx` barangay codes).

If GDAL is already available, a full export is still possible:

```powershell
ogr2ogr -f GeoJSON storage\boundaries\PH_Adm4_BgySubMuns.geojson storage\boundaries\PH_Adm4_BgySubMuns\PH_Adm4_BgySubMuns.shp.shp
```

`ogr2ogr` is intentionally not required by MapServer itself. The populator consumes plain GeoJSON so node kits can ship a prepared subset or a full prepared boundary file.

## Dry Run

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\populate-tiles.php `
  --base-url http://localhost/mapserver `
  --source-geojson storage\boundaries\mapserver-data-prep-barangay-730600041.geojson `
  --brgy-code 730600041 `
  --zooms 10-14 `
  --types raster,vector `
  --ca-bundle C:\ProgramData\PBB\KitSetup\certs\ca-bundle.pem `
  --dry-run `
  --report C:\pbb\reports\mapserver-populate-lahug-dry-run.json
```

## Populate

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\populate-tiles.php `
  --base-url http://localhost/mapserver `
  --source-geojson storage\boundaries\mapserver-data-prep-barangay-730600041.geojson `
  --brgy-code 730600041 `
  --zooms 10-14 `
  --types raster,vector `
  --ca-bundle C:\ProgramData\PBB\KitSetup\certs\ca-bundle.pem `
  --max-tiles 5000 `
  --report C:\pbb\reports\mapserver-populate-lahug.json
```

## Data Prep Contract

MapServer exposes the standalone Data Prep tool layout expected by Kit Setup:

- `tools/data-prep/prepare.php` wraps `tools/populate-tiles.php` and satisfies **Prepare Data** by planning or populating the tile cache.
- `tools/data-prep/apply-settings.php` reports a successful no-op for **Apply Settings** in the initial scope. Consuming apps own their map URL/style settings.
- `tools/data-prep/verify.php` satisfies **Verify** by checking `/tiles/health` and `/api/status`.

Each tool accepts the common contract:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\data-prep\prepare.php `
  --mode initial `
  --config C:\pbb\configs\mapserver-data-prep.json `
  --report C:\pbb\reports\mapserver-data-prep-prepare.json `
  --dry-run
```

`prepare.php` reads `mapserver.data_prep.prepare` first, then falls back to the existing `mapserver.populate` installer config section. Supported keys map to the tile populator options: `base_url`, `source_geojson`, `brgy_code`, `barangay`, `city`, `bbox`, `center`, `radius_km`, `zooms`, `types`, `max_tiles`, `limit`, `timeout`, `curl_ca_bundle`, and `curl_ssl_verify`.

If `boundary_work_dir` is omitted, MapServer uses `<app>/storage/boundaries`. If Kit supplies a relative value such as `storage\boundaries`, MapServer resolves it under the installed app root.

Preferred Kit contract for current-barangay Data Prep:

```json
{
  "mapserver": {
    "data_prep": {
      "prepare": {
        "base_url": "http://localhost/mapserver",
        "deployment_scope": "barangay",
        "barangay_code": "730600041",
        "barangay": "Lahug",
        "city": "Cebu City",
        "boundary_work_dir": "storage\\boundaries",
        "zooms": "10-14",
        "types": "raster,vector",
        "max_tiles": 5000,
        "curl_ssl_verify": true,
        "curl_ca_bundle": "C:\\ProgramData\\PBB\\KitSetup\\certs\\ca-bundle.pem"
      }
    }
  }
}
```

Minimum coverage input is one of:

- `deployment_scope` plus the matching PSGC code: `barangay` -> `barangay_code`/`brgy_code`, `city` -> `citymun_code`, `province` -> `prov_code`, `region` -> `reg_code`. `other` is treated as `barangay`.
- `boundary_geojson` or `source_geojson` plus `barangay_code`/`brgy_code`, `citymun_code`, `prov_code`, `reg_code`, or `barangay`.
- A scope code with optional names; MapServer will call `tools/prepare-boundaries.php` to resolve a small boundary GeoJSON into `boundary_work_dir`.
- `bbox` for an already-known area.
- `center` plus `radius_km` for a point-derived fallback.

Aliases accepted from Kit/HQ setup data: `barangay_code`, `psgc_code`, `barangay_name`, `city_name`, `municipality`, `city_code`, `municipality_code`, `province_code`, and `region_code`.

Reports use the operator-safe Data Prep shape with `schema_version`, `app`, `tool`, `mode`, `dry_run`, `status`, `summary`, `sources`, `results`, `outputs`, `warnings`, and `errors`.

Child populator reports also include a `tls` block:

```json
{
  "tls": {
    "ssl_verify": true,
    "ca_bundle_configured": true
  }
}
```

Use that block to confirm whether Kit's `curl_ca_bundle` or `curl_ssl_verify=false` setting actually reached the child populator process.

## Verified Population Examples

The following current-repo runs were verified against `http://localhost/mapserver` with zooms `10-14` and types `raster,vector`:

| Input code | Resolved source label | Coverage tiles | Requests | Result |
| --- | --- | ---: | ---: | --- |
| `072217029` | Guadalupe, Cebu City (`730600029`) | 15 | 30 | 30 succeeded, 0 failed |
| `072217003` | Apas, Cebu City (`730600003`) | 8 | 16 | 16 succeeded, 0 failed |
| city `072217`, zooms `10-12` | Cebu City, 80 boundary features | 16 | 32 | 32 succeeded, 0 failed |

City-wide polygon filtering at zooms `10-14` can be slow because the populator checks all generated city barangay polygons against candidate tiles. For operator use, start city/province/region scopes with a dry-run and conservative zooms, then raise zooms only after reviewing the planned tile count and runtime.

Example real population command through the Data Prep wrapper:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\data-prep\prepare.php `
  --mode initial `
  --config C:\pbb\configs\mapserver-data-prep.json `
  --report C:\pbb\reports\mapserver-data-prep-prepare.json
```

## Fallbacks

When a barangay polygon is not available yet, use a known center point and radius from PBB Hub geodata:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\populate-tiles.php `
  --base-url http://localhost/mapserver `
  --center 10.324155,123.8984849 `
  --radius-km 2 `
  --zooms 10-14 `
  --types raster,vector `
  --dry-run
```

For controlled tests, use an explicit bounding box:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\populate-tiles.php `
  --base-url http://localhost/mapserver `
  --bbox 123.88,10.30,123.92,10.35 `
  --zooms 10-14 `
  --types raster `
  --dry-run
```

## Guardrails

- Default zooms are `10-14`; avoid high zooms until the planned tile count is known.
- Default types are `raster,vector`; `terrain` and `poi` can be added when MapTiler credentials are configured.
- `--max-tiles` prevents accidental large upstream pulls.
- `--limit` can cap a live run after the coverage is computed.
- HTTPS population through a local/self-signed PBB domain must either provide a trusted CA bundle through `curl_ca_bundle` / `--ca-bundle`, or explicitly set `curl_ssl_verify=false` / `--no-ssl-verify` for controlled local setup runs. Prefer a CA bundle whenever Kit has one.
- The populator also reads `TILES_CURL_CA_BUNDLE`, `CURL_CA_BUNDLE`, `SSL_CERT_FILE`, and `TILES_CURL_SSL_VERIFY` from the process environment. Explicit config/CLI options are clearer for Kit reports.
- Numeric guardrails such as `--max-tiles`, `--limit`, `--timeout`, and `--radius-km` must be positive values; invalid values fail instead of being silently coerced.
- GeoJSON and bounding-box coordinates are validated as lon/lat. Antimeridian-crossing boxes are not supported; split those into two runs if ever needed.
- Reports are JSON and include coverage, cache HIT/MISS counts, failures, and a small error sample.

## Coverage Notes

The populator converts requested lon/lat bounds to Web Mercator tile x/y ranges, clamps latitude to the Web Mercator limit, and filters GeoJSON runs by polygon intersection. Polygon filtering supports `Polygon` and `MultiPolygon`, respects interior rings as holes for point-in-polygon checks, and includes tiles touched by polygon edges so thin or small barangays are not dropped at low zoom.
