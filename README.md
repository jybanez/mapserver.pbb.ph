# Tile Proxy/Cache (Plain PHP)

## Setup
1. Place `index.php`, `config.php`, and `.htaccess` in your web root (this repo).
2. Ensure PHP has cURL enabled.
3. Create the cache root folder (e.g. `C:/tiles-cache`) and ensure the web server can write to it.
4. Optionally set env vars for manual/non-Kit installs:
   - `TILES_CACHE_ROOT`
   - `OSM_TILE_BASE_URL`
   - `VECTOR_TILE_BASE_URL`
   - `GLYPHS_BASE_URL`
   - `TERRAIN_TILE_BASE_URL`
   - `POI_BASE_URL`
   - `TILES_LOG_FILE`
   - `TILES_PURGE_TOKEN`
   - `TILES_CURL_SSL_VERIFY` (set to `0` to disable SSL verification)
   - `TILES_CURL_CA_BUNDLE` (path to CA bundle file)

For Kit Setup installs, the installer normally writes only `TILES_PURGE_TOKEN`, `STADIAMAPS_API_KEY`, and `MAPTILER_API_KEY` into `.env` for the default provider path. Built-in/default tile URL templates and placeholder URLs such as `example.test` are intentionally omitted so `config.php` can build the provider URLs from the shared keys.

## Apache mod_rewrite (WAMP)
```
# httpd.conf or vhost
LoadModule rewrite_module modules/mod_rewrite.so

<Directory "C:/wamp64/www/tiles">
    AllowOverride All
    Require all granted
</Directory>

# .htaccess in /tiles
RewriteEngine On
RewriteRule ^tiles/.*$ index.php [L,QSA]
```

## Endpoints
- `GET /tiles/raster/{z}/{x}/{y}.png`
- `GET /tiles/vector/{z}/{x}/{y}.pbf`
- `GET /tiles/terrain/{z}/{x}/{y}.png`
- `GET /tiles/glyphs/{fontstack}/{range}.pbf`
- `GET /tiles/poi/{z}/{x}/{y}.pbf` (POI vector tiles for MapLibre)
- `GET /boundaries/{scope}/{code}.geojson` (public GeoJSON overlays for `barangay`, `city`, `province`, and `region`)
- `GET /tiles/health`

## Purge Endpoint
Purge requires `TILES_PURGE_TOKEN` set, and the token passed via query string or `X-Purge-Token` header.
- `POST /tiles/purge/raster/{z}/{x}/{y}.png?token=...`
- `POST /tiles/purge/vector/{z}/{x}/{y}.pbf?token=...`
- `POST /tiles/purge/terrain/{z}/{x}/{y}.png?token=...`
- `POST /tiles/purge/glyphs/{fontstack}/{range}.pbf?token=...`
- `POST /tiles/purge/poi/{z}/{x}/{y}.pbf?token=...`

## Test Caching
1. Request a tile twice and check `X-Cache`:
```
curl -I http://localhost/tiles/raster/0/0/0.png
curl -I http://localhost/tiles/raster/0/0/0.png
```
First response should show `X-Cache: MISS`, second should show `X-Cache: HIT`.

2. For vector/glyphs, verify gzip handling:
```
curl -I --compressed http://localhost/tiles/vector/0/0/0.pbf
curl -I --compressed http://localhost/tiles/vector/0/0/0.pbf
```
Look for `Content-Encoding: gzip` (if upstream gzips) and `X-Cache` toggling to `HIT` on the second request.

3. Verify cache files are written:
- `C:/tiles-cache/raster/0/0/0.png`
- `C:/tiles-cache/vector/0/0/0.pbf`


4. For POI vector tiles:
```
curl -I --compressed http://localhost/tiles/poi/0/0/0.pbf
curl -I --compressed http://localhost/tiles/poi/0/0/0.pbf
```
Second response should show `X-Cache: HIT`.

## Data Prep Boundaries
MapServer includes vendored PSGC boundary source files under `resources/boundaries`:

- `PH_Adm4_BgySubMuns.shp.zip`
- `PH_Adm3_MuniCities.csv`
- `PH_Adm4_BgySubMuns.csv`

These are permanent runtime assets. Generated boundary outputs and extracted shapefile cache files belong under `storage/boundaries`, while populated tiles remain under `storage/tiles`.

The shapefile ZIP is intentionally pruned to the WGS84 `BgySubMuns.shp.*` set used by `tools/prepare-boundaries.php`. The upstream projected UTM copy is not shipped because MapServer rejects `PROJCS` shapefiles for boundary prep and overlay generation.

Run barangay tile population through the Data Prep wrapper:

```
C:\wamp64\bin\php\php8.2.29\php.exe tools\data-prep\prepare.php --mode initial --config C:\pbb\configs\mapserver-data-prep.json --report C:\pbb\reports\mapserver-data-prep-prepare.json
```

When Data Prep populates tiles through an HTTPS PBB domain with a local/self-signed certificate, provide `mapserver.data_prep.prepare.curl_ca_bundle` in the Kit config. For controlled local setup runs only, `mapserver.data_prep.prepare.curl_ssl_verify=false` disables verification for the populator.

The wrapper can resolve Hub/Kit barangay codes such as `072217029` and `072217003` from the vendored boundary data, then populate the matching local tile coverage. See `docs/tile-populator.md` for the full Data Prep contract and verified population examples.

## Boundary Overlay API
MapServer also serves public boundary overlays for client apps such as Hotline:

```
GET /boundaries/barangay/072217029.geojson
GET /boundaries/city/072217.geojson
GET /boundaries.geojson?scope=barangay&relay_hub_id=072217029
```

The first request prepares GeoJSON from the vendored boundary resources and caches it under `storage/boundaries/http`; later requests return the generated GeoJSON with `ETag`, `Last-Modified`, CORS, and public cache headers. See `docs/boundary-overlay-contract.md` for the full Relay `/hub.json` mapping contract.

## Kit Bundle Defaults
Shared MapServer provider credentials are not committed to this source tree. Trusted package builds inject `resources/kit-setup/shared-install-defaults.json` into the canonical `pbb-mapserver-1.0.0.zip` bundle and add that injected file to the bundle's `checksums.sha256`. Kit Setup reads that file as hidden shared install defaults and passes the allowlisted values to MapServer install config; operators should not see these keys in Kit Admin Inputs.

Source checkouts may not contain `resources/kit-setup/shared-install-defaults.json`. That is expected. The file should exist inside the trusted canonical bundle only.

## Debugging Upstream Errors
If you see `Upstream Error`, try adding `?debug=1` to get JSON with the upstream status and cURL error:
```
http://localhost/mapserver/tiles/raster/0/0/0.png?debug=1
```

