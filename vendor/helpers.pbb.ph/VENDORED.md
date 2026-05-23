# Vendored Helper Runtime

Source: `C:\wamp64\www\hotline-helpers`

Package: `hotline-helpers` 0.21.83

This MapServer copy intentionally contains only bundled production runtime assets:

- `dist/`
- `js/ui/ui.loader.js`
- `boot.*.json`
- package/provenance docs

MapServer sets `uiLoader.setPreferBundles(true)`, so `ui.*` and `incident.*` components resolve through `dist/helpers.ui.bundle.min.js` and `dist/helpers.ui.bundle.min.css`.

Excluded from MapServer runtime packages: modular component source folders, demos, docs, samples, scripts, tests, `.git`, `node_modules`, temporary files, and source-only development artifacts.
