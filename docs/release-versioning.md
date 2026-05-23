# Release Versioning

Official repository: `https://github.com/jybanez/mapserver.pbb.ph`

MapServer follows the Kit Setup release identity shape:

- `milestone`
- `version`
- `display_version` formatted as `v{milestone}-{version}`
- `repository.type`
- `repository.url`
- `build.version`
- `build.id`
- `build.built_at`
- `build.git_commit`
- `build.builder`
- `update.contract_version`
- `update.channel`
- `update.compatibility`

Update source release metadata with:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\update-build-metadata.php --version 1.0.1 --milestone 1 --builder local-source
```

Preview without writing:

```powershell
C:\wamp64\bin\php\php8.2.29\php.exe tools\update-build-metadata.php --version 1.0.1 --dry-run
```

`tools\update-build-metadata.php` is source/build tooling. It should stay in the project repository, but release packaging should exclude source-only build tooling unless Kit Setup explicitly approves including it in a distributable bundle. The updater preserves or creates a testing `release.json.update` block for Kit's app bundle versioning/update contract.

Current MapServer testing rebuilds use:

```json
{
  "update": {
    "contract_version": 1,
    "channel": "testing",
    "immutable_release": false,
    "from_versions": ["1.0.0"],
    "compatibility": "same-version-rebuild",
    "requires_database_migration": false,
    "requires_data_prep_rerun": false,
    "requires_service_restart": false,
    "rollback_supported": true
  }
}
```

## Trusted Kit Bundle Build

The canonical Kit package is `C:\wamp64\www\pbb\kit-setup\packages\bundled\pbb-mapserver-1.0.0.zip`. After rebuilding it, update `C:\wamp64\www\pbb\kit-setup\packages\packages.bundled.json` with the ZIP SHA256 so Kit can verify the embedded package.

Shared provider credentials are build-time injected defaults. Do not commit live `STADIAMAPS_API_KEY` or `MAPTILER_API_KEY` values into this repo. During a trusted package build, inject `resources/kit-setup/shared-install-defaults.json` into the ZIP with this shape:

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

The injected file must also be represented in the ZIP's `checksums.sha256`. The source tree should not contain the live `resources/kit-setup/shared-install-defaults.json` after the build. A valid bundle verification should include:

- PHP lint for installer and Data Prep tools.
- Source checksum scan with `bad_count=0`.
- Extracted bundle checksum scan with `bad_count=0`.
- Bundle SHA256 matching `packages.bundled.json`.
- `release.json.version` matching `pbb-mapserver-1.0.0.zip`.
- Unique `release.json.build.id` for the produced ZIP.
- `release.json.update.compatibility=same-version-rebuild` while the `1.0.0` bundle remains in testing.
- Presence/schema check for the injected shared defaults file.
- Data Prep dry-run for a known code such as `072217029`.
