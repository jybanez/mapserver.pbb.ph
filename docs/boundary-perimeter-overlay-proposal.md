# Boundary Perimeter Overlay Proposal

## Context

Implementation status: implemented in MapServer. Primary boundary overlay responses now return requested-area perimeter geometry, while subordinate barangay collections are available through explicit children/barangays endpoints or `mode=children`.

PBB Support System needs two different map boundary behaviors on the same operational map:

- A fixed context boundary for the parent operating area, such as Cebu City.
- Toggleable source boundaries for source hubs, such as selected barangays contributing SITREPs.

Current MapServer boundary overlay behavior serves deployment-scoped GeoJSON through:

```http
GET /boundaries/{scope}/{code}.geojson
GET /api/boundaries/{scope}/{code}
GET /boundaries.geojson?scope={scope}&code={code}
```

The current contract allows wider scopes to return subordinate barangay features. For example, `GET /boundaries/city/072217.geojson` returns a `FeatureCollection` containing Cebu City's barangay polygons. When a client renders that as a line layer, it draws all internal barangay boundaries, not only the city perimeter.

For Support's dashboard this creates visual ambiguity because the city context outline looks the same as source barangay boundary overlays.

## Proposal

Change the boundary overlay contract so a request for a scope returns only the perimeter of that requested administrative area, not subordinate/internal boundaries.

Proposed behavior:

| Request | Desired response |
| --- | --- |
| `/boundaries/barangay/{brgy_code}.geojson` | One barangay perimeter. |
| `/boundaries/city/{citymun_code}.geojson` | One dissolved city/municipality perimeter. |
| `/boundaries/province/{prov_code}.geojson` | One dissolved province perimeter. |
| `/boundaries/region/{reg_code}.geojson` | One dissolved region perimeter. |

Response should remain a GeoJSON `FeatureCollection`, but the geometry should represent the exterior perimeter for the requested scope. It may be a `Polygon` or `MultiPolygon` depending on islands/disconnected administrative areas.

## Compatibility Option

If existing clients still need subordinate boundaries, expose them explicitly through a separate endpoint instead of the primary overlay endpoint.

Possible additive endpoints:

```http
GET /boundaries/{scope}/{code}/children.geojson
GET /boundaries/{scope}/{code}/barangays.geojson
GET /boundaries.geojson?scope=city&citymun_code={code}&mode=children
```

With this split:

- `/boundaries/city/072217.geojson` returns Cebu City's outside perimeter only.
- `/boundaries/city/072217/barangays.geojson` returns Cebu City's barangay polygons.

This keeps the common app integration simple while preserving access to detailed subordinate geometry when an app explicitly asks for it.

## Support System Use Case

Support will use:

```http
GET /boundaries/city/072217.geojson
```

as the fixed context outline, rendered as a line-only layer.

Support will separately use:

```http
GET /boundaries/barangay/{brgy_code}.geojson
```

for source hub boundaries, controlled by source-card show/hide toggles.

This means:

- City boundary stays fixed.
- Source boundaries remain independently toggleable.
- Internal city barangay boundaries are not shown unless Support explicitly requests child boundaries.

## Implementation Notes

MapServer already generates wider-scope boundary sets from barangay geometry. The missing step is producing a dissolved geometry for the requested administrative scope.

Recommended implementation direction:

1. Filter source features by requested scope/code as MapServer already does.
2. Dissolve/union matching polygons into one area for the requested administrative scope.
3. Emit only the dissolved exterior geometry in the primary `/boundaries/{scope}/{code}.geojson` response.
4. Preserve subordinate-feature output behind an explicit children/barangays endpoint if needed.

If pure PHP geometry union is too heavy, MapServer can support this through a generation-time helper, an optional precomputed perimeter cache, or generated perimeter files under `storage/boundaries/http`.

## Acceptance Criteria

- `GET /boundaries/barangay/072217029.geojson` renders only Guadalupe's boundary.
- `GET /boundaries/city/072217.geojson` renders only Cebu City's outer perimeter, with no internal barangay lines.
- `GET /boundaries/province/0722.geojson` renders only Cebu Province's outer perimeter, with no city/barangay internal lines.
- Existing response headers and cache behavior remain compatible.
- Existing clients that require child boundaries have an explicit endpoint or query mode.
