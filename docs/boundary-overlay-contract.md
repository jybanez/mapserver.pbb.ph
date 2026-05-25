# MapServer Boundary Overlay Contract

MapServer exposes public GeoJSON boundary overlays for PBB apps that need to draw the installed hub coverage area. The endpoint is browser-consumable and safe for Hotline operator/command map surfaces because it serves only public PSGC boundary geometry from the vendored `resources/boundaries` dataset.

## Endpoints

Preferred path form:

```http
GET /boundaries/{scope}/{code}.geojson
```

API alias:

```http
GET /api/boundaries/{scope}/{code}
```

Query form for clients that map directly from Relay `/hub.json` fields:

```http
GET /boundaries.geojson?scope={scope}&code={code}
GET /boundaries.geojson?scope=barangay&relay_hub_id={relay_hub_id}
GET /boundaries.geojson?scope=barangay&brgy_code={brgy_code}
GET /boundaries.geojson?scope=city&citymun_code={citymun_code}
GET /boundaries.geojson?scope=province&prov_code={prov_code}
GET /boundaries.geojson?scope=region&reg_code={reg_code}
```

## Supported Scopes

| Scope | Aliases | Relay `/hub.json` key |
| --- | --- | --- |
| `barangay` | `brgy`, `other` | `brgy_code`, with `relay_hub_id` accepted when it is the hub barangay PSGC code |
| `city` | `citymun`, `municipality`, `municipal` | `citymun_code` |
| `province` | `prov` | `prov_code` |
| `region` | `reg` | `reg_code` |

For deployment selection, consuming apps should use Relay `/hub.json` `deployment` as the scope. If `deployment` is `other`, treat it as `barangay`.

## Hotline Mapping

Given Relay's public `https://relay.pbb.ph/hub.json`, Hotline should resolve the overlay request like this:

1. Normalize `deployment` to one of `barangay`, `city`, `province`, or `region`; map `other` to `barangay`.
2. Pick the matching code:
   - `barangay`: prefer `brgy_code`; fallback to `relay_hub_id` only when it is the barangay code.
   - `city`: use `citymun_code`.
   - `province`: use `prov_code`.
   - `region`: use `reg_code`.
3. Request `GET {map_server_url}/boundaries/{scope}/{code}.geojson`.

Example:

```http
GET https://mapserver.pbb.ph/boundaries/barangay/072217029.geojson
```

## Response Shape

Successful responses are GeoJSON `FeatureCollection` documents:

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "adm1_psgc": "700000000",
        "adm2_psgc": "730600000",
        "adm3_psgc": "730600000",
        "adm3_en": "City of Cebu",
        "geo_level": "City",
        "city_name": "Cebu City",
        "citymun_name": "City of Cebu",
        "adm4_psgc": "730600029",
        "adm4_en": "Guadalupe",
        "brgy_code": "730600029",
        "brgy_name": "Guadalupe",
        "source_psgc_code": "702217029"
      },
      "geometry": {
        "type": "Polygon",
        "coordinates": []
      }
    }
  ]
}
```

Properties come from the vendored PSGC Level 4 boundary CSV/shapefile set. Scope responses may contain multiple barangay features: a city returns its barangays, a province returns its barangays, and a region returns its barangays. Clients should render the full `FeatureCollection` rather than assuming a single feature.

## Headers

MapServer returns:

```http
Content-Type: application/geo+json; charset=UTF-8
Access-Control-Allow-Origin: *
Cache-Control: public, max-age=86400, stale-while-revalidate=604800
ETag: "{sha256-of-geojson}"
Last-Modified: {generated-file-mtime}
X-PBB-Boundary-Scope: {scope}
X-PBB-Boundary-Code: {code}
X-PBB-Boundary-Source: resources/boundaries
X-PBB-Boundary-Version: {source-repository}@{captured_at}
X-PBB-MapServer-Build: {release.build.id}
```

`If-None-Match` and `If-Modified-Since` are supported and return `304 Not Modified` when valid.

## Runtime Behavior

Boundary source files are permanent package resources under `resources/boundaries`. Generated HTTP overlay files are cached under `storage/boundaries/http` and may be regenerated safely. The first request for a scope/code may take longer because MapServer prepares the GeoJSON from the vendored boundary source; subsequent requests are served from the generated cache.

No API key, purge token, or auth token is required for boundary overlays.
