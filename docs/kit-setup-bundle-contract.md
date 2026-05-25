# Kit Setup Bundle Contract

This document records MapServer's current contract with PBB Kit Setup.

## Canonical Bundle

Kit consumes the canonical package at:

```text
C:\wamp64\www\pbb\kit-setup\packages\bundled\pbb-mapserver-1.0.0.zip
```

Whenever the bundle is rebuilt, update the `pbb-mapserver` entry in:

```text
C:\wamp64\www\pbb\kit-setup\packages\packages.bundled.json
```

The bundle must include `checksums.sha256`, and extracted verification must report `bad_count=0`.

## Shared Provider Defaults

MapServer owns the shared Stadia Maps and MapTiler provider credentials for installed nodes. These credentials must not be collected in Kit Admin Inputs and must not be committed into the MapServer source tree.

Trusted package builds inject this file into the ZIP:

```text
resources/kit-setup/shared-install-defaults.json
```

The injected file is intentionally absent from normal source checkouts. It must be present inside the trusted ZIP and listed in that ZIP's `checksums.sha256`.

Expected shape:

```json
{
  "schema_version": 1,
  "app_id": "pbb-mapserver",
  "values": {
    "mapserver": {
      "stadiamaps_api_key": "...",
      "maptiler_api_key": "..."
    },
    "shared": {
      "secrets": {
        "values": {
          "stadiamaps_api_key": "...",
          "maptiler_api_key": "..."
        }
      }
    }
  },
  "redaction": {
    "stadiamaps_api_key": "secret",
    "maptiler_api_key": "secret"
  }
}
```

Kit merges only allowlisted values from this file. MapServer still owns writing `STADIAMAPS_API_KEY` and `MAPTILER_API_KEY` to installed `.env` files and redacting reports.

## Installed `.env` Shape

For the default provider path, generated `.env` should contain:

- `TILES_PURGE_TOKEN`
- `STADIAMAPS_API_KEY`
- `MAPTILER_API_KEY`

Do not write built-in/default URL templates or placeholder URLs such as `tiles.example.test`. `config.php` derives default provider URLs from the credentials at runtime. `TILES_CACHE_ROOT`, `TILES_LOG_FILE`, and `TILES_CURL_SSL_VERIFY=1` are also omitted in normal installs; write `TILES_CURL_SSL_VERIFY=0` only when SSL verification is intentionally disabled.

## Data Prep Coverage

Kit passes Hub deployment location into `mapserver.data_prep.prepare`:

- `deployment_scope=barangay` uses `barangay_code`, `brgy_code`, or `psgc_code`.
- `deployment_scope=city` uses `citymun_code`, `city_code`, or `municipality_code`.
- `deployment_scope=province` uses `prov_code` or `province_code`.
- `deployment_scope=region` uses `reg_code` or `region_code`.
- `deployment_scope=other` is treated as barangay.

MapServer resolves those codes using vendored boundary files under `resources/boundaries` and writes generated GeoJSON/cache outputs under `storage/boundaries`.

## Public Boundary Overlays

MapServer serves browser-consumable GeoJSON overlays from the same vendored boundary resources:

```http
GET /boundaries/{scope}/{code}.geojson
GET /api/boundaries/{scope}/{code}
GET /boundaries.geojson?scope={scope}&code={code}
```

Scopes are `barangay`, `city`, `province`, and `region`; `other` maps to `barangay`. Query aliases match Relay public hub identity fields: `relay_hub_id`/`brgy_code`, `citymun_code`, `prov_code`, and `reg_code`. Responses are public GeoJSON `FeatureCollection` documents with `Access-Control-Allow-Origin: *`, `ETag`, `Last-Modified`, `Cache-Control: public, max-age=86400, stale-while-revalidate=604800`, and `X-PBB-Boundary-*` metadata headers. Full contract: `docs/boundary-overlay-contract.md`.

## Data Prep TLS

Populate requests to `https://mapserver.pbb.ph` may need Kit's CA bundle because local PBB certificates are not always trusted by PHP cURL. Kit should prefer:

```json
{
  "mapserver": {
    "data_prep": {
      "prepare": {
        "curl_ssl_verify": true,
        "curl_ca_bundle": "C:\\ProgramData\\PBB\\KitSetup\\certs\\ca-bundle.pem"
      }
    }
  }
}
```

Controlled local fallback may set `curl_ssl_verify=false`, which MapServer forwards to `tools/populate-tiles.php` as `--no-ssl-verify`.

The child populator report includes:

```json
{
  "tls": {
    "ssl_verify": true,
    "ca_bundle_configured": true
  }
}
```

If every tile result has `status: 0` and `SSL certificate problem: unable to get local issuer certificate`, fix this populator-to-MapServer TLS path first. If the failure becomes an HTTP status such as `502`, the request is reaching MapServer and the remaining issue is server or upstream provider configuration.
