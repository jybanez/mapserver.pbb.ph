# MapServer Boundary Overlay Contract

MapServer exposes public GeoJSON boundary overlays for PBB apps that need to draw the installed hub coverage area. The endpoint is browser-consumable and safe for Hotline operator/command map surfaces because it serves only public PSGC boundary geometry from the vendored `resources/boundaries` dataset.

## Endpoints

Preferred path form:

```http
GET /boundaries/{scope}/{code}.geojson
GET /boundaries/{scope}/{code}/children.geojson
GET /boundaries/{scope}/{code}/barangays.geojson
```

API alias:

```http
GET /api/boundaries/{scope}/{code}
GET /api/boundaries/{scope}/{code}?mode=children
```

Query form for clients that map directly from Relay `/hub.json` fields:

```http
GET /boundaries.geojson?scope={scope}&code={code}
GET /boundaries.geojson?scope=barangay&relay_hub_id={relay_hub_id}
GET /boundaries.geojson?scope=barangay&brgy_code={brgy_code}
GET /boundaries.geojson?scope=city&citymun_code={citymun_code}
GET /boundaries.geojson?scope=province&prov_code={prov_code}
GET /boundaries.geojson?scope=region&reg_code={reg_code}
GET /boundaries.geojson?scope=city&citymun_code={citymun_code}&mode=children
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

Successful responses are GeoJSON `FeatureCollection` documents. The primary `/boundaries/{scope}/{code}.geojson` response returns one requested-area perimeter feature. For `barangay`, that is the barangay polygon. For `city`, `province`, and `region`, MapServer dissolves matching barangay polygons into an exterior `Polygon` or `MultiPolygon` so clients can render the parent outline without internal barangay boundaries.

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

Perimeter responses include `boundary_mode=perimeter`, `boundary_scope`, and `boundary_code` properties. Wider-scope perimeter responses also include `source_feature_count` and `perimeter_ring_count` so clients can inspect how many child polygons were dissolved.

Clients that need subordinate barangay polygons must request them explicitly:

```http
GET /boundaries/{scope}/{code}/children.geojson
GET /boundaries/{scope}/{code}/barangays.geojson
GET /boundaries/{scope}/{code}.geojson?mode=children
GET /api/boundaries/{scope}/{code}?mode=children
GET /boundaries.geojson?scope={scope}&code={code}&mode=children
```

Child responses return the original PSGC Level 4 features from the vendored boundary CSV/shapefile set. A city child response returns its barangays, a province child response returns its barangays, and a region child response returns its barangays.

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
X-PBB-Boundary-Mode: perimeter|children
X-PBB-Boundary-Source: resources/boundaries
X-PBB-Boundary-Version: {source-repository}@{captured_at}
X-PBB-MapServer-Build: {release.build.id}
```

`If-None-Match` and `If-Modified-Since` are supported and return `304 Not Modified` when valid.

## Runtime Behavior

Boundary source files are permanent package resources under `resources/boundaries`. Generated HTTP overlay files are cached under `storage/boundaries/http` and may be regenerated safely. Primary perimeter responses and explicit child responses use separate cache files, so changing modes does not reuse stale geometry. The first request for a scope/code may take longer because MapServer prepares the GeoJSON from the vendored boundary source; subsequent requests are served from the generated cache.

No API key, purge token, or auth token is required for boundary overlays.
