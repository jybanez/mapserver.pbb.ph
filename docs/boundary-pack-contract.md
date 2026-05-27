# Boundary Pack Contract

MapServer can run with deployment-scoped boundary packs instead of the national barangay shapefile ZIP. This lets Kit build a deployment-specific installer that carries only the boundary data needed by the target hub.

## App Bundle

The app/runtime bundle can omit:

```text
resources/boundaries/PH_Adm4_BgySubMuns.shp.zip
```

It should keep:

```text
resources/boundaries/PH_Adm3_MuniCities.csv
resources/boundaries/PH_Adm4_BgySubMuns.csv
resources/boundaries/manifest.json
```

Those CSV files provide current PSGC metadata and legacy/current code resolution while a scoped boundary pack provides geometry.

## Province Pack Layout

Kit should extract a province pack into the installed MapServer root. The pack contains:

```text
resources/boundaries/provinces/{prov_code}/BgySubMuns.shp.zip
resources/boundaries/provinces/{prov_code}/pack.json
```

`BgySubMuns.shp.zip` contains a filtered WGS84 shapefile set:

```text
BgySubMuns.shp.cpg
BgySubMuns.shp.dbf
BgySubMuns.shp.prj
BgySubMuns.shp.shp
BgySubMuns.shp.shx
```

The projected UTM `PH_Adm4_BgySubMuns.shp.*` files are not required. `tools/prepare-boundaries.php` intentionally selects only shapefiles whose projection contains `GEOGCS` and does not contain `PROJCS`.

## Pack Metadata

Example `pack.json`:

```json
{
  "schema_version": 1,
  "kind": "pbb-mapserver-boundary-pack",
  "scope": "province",
  "province_code": "0722",
  "label": "Cebu",
  "aliases": ["0722", "7022", "072217", "702217", "7306", "730600000"],
  "outputs": {
    "source_zip": "resources/boundaries/provinces/0722/BgySubMuns.shp.zip",
    "source_zip_sha256": "...",
    "source_zip_bytes": 5993406
  },
  "records": 1283
}
```

Aliases are required because Hub/Kit deployment codes may be legacy-style, while the current metadata CSV and source shapefile use different representations:

- Hub/Kit Cebu province: `0722`
- Shapefile source prefix for Cebu: `7022`
- Hub/Kit Cebu City: `072217`
- Shapefile source prefix for Cebu City: `702217`
- Current metadata for Guadalupe/Cebu City: `730600029` / `730600000`

MapServer matches request codes and code variants against pack aliases. A barangay request for `072217029` resolves to source variant `702217029`, which matches alias `7022`; a city request for `072217` resolves to `702217`, which also matches.

## Build Tool

Prototype pack builder:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\build-boundary-province-pack.php `
  --province-code 0722 `
  --aliases 0722,7022,072217,702217,7306,730600000 `
  --label Cebu `
  --output C:\wamp64\www\pbb\kit-setup\packages\bundled\pbb-mapserver-boundaries-province-0722.zip
```

No GDAL or `ogr2ogr` is required. The builder uses PHP to filter the WGS84 shapefile records, writes new `.shp`, `.shx`, and `.dbf` files, copies `.prj` and `.cpg`, and packages the result.

## Runtime Resolution

`tools/prepare-boundaries.php` now checks installed province packs before falling back to the national shapefile ZIP. If a matching pack exists, Data Prep and HTTP overlay generation use:

```text
resources/boundaries/provinces/{prov_code}/BgySubMuns.shp.zip
```

The existing endpoints continue to work:

```http
GET /boundaries/barangay/072217029.geojson
GET /api/boundaries/city/072217
```

If no matching pack exists, MapServer falls back to `resources/boundaries/PH_Adm4_BgySubMuns.shp.zip` when present.
