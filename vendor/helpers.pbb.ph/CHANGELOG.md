# Changelog

All notable changes to `helpers.pbb.ph` are documented here.

## Versioning

- Current stable line: `v0.21.x`
- Latest documented release: `v0.21.83`
- Next planned line: `v0.22.x`

## Unreleased

- Added `ui.device.selector` as an adapter-driven device selection/test helper with browser-media adapters for camera, microphone, and speaker V1, regular/compact layouts, shared `ui.select` default presentation plus `menu`, `list`, and `native-select` presentation modes, app-owned selection persistence callbacks, demo coverage, and regression coverage.
- Added visual validation state to scalar incident type fields, so required text/select/multiselect and number min/max issues get warning styling, `aria-invalid`, and a warning badge instead of only returning errors from `validate()`.
- Improved incident type and team assignment drawer focus behavior: drawer search fields receive focus on open, and adding an incident/team moves focus into the first editable field on the newly added card.
- Added `mode: "compact"` to `createDevicePrimer(...)` and `createDevicePrimerModal(...)` for icon-strip device checks with a selected-check detail panel, shimmer checking state, and explicit retry for failed or blocked checks.
- Fixed `ui.window` title-bar layout so dense `headerActions` stay inline between an ellipsized title and fixed window controls, header actions no longer trigger drag, and maximized windows drop rounded outer chrome for flush workspace/map layouts.
- Added app-owned busy state support to `incidentTeamsAssignments(...)` via `busyAssignments`, item-level busy fields, and `setItemBusy(...)` / `clearItemBusy(...)`, rendering `aria-busy`, disabled card controls, and compact per-assignment status rows for async mutations.
- Added `statusContent` and `statusContentLabel` to `createNavbar(...)` for a persistent inline status region that remains visible beside the mobile hamburger without duplicating into the hamburger menu.
- Added `ui.signal.strength` as a transport-agnostic 0-4 bar connectivity status primitive with stable compact text, tones, bars-only mode, accessible labels, demo coverage, and regression coverage.
- Added `emptyText` and `showEmptyPlaceholder` options to `ui.kanban` so compact rails can leave empty lanes blank while the default `"No cards."` placeholder remains unchanged.
- Added `ui.audio.timeline` as a generic synchronized multi-track audio surface for arbitrary sources, including pending processing segments, and refactored `createAudioCallSession(...)` into an incident-media adapter over that timeline while preserving its role-oriented state contract.
- Split the audio demos so `demos/demo.audio.html` documents only the incident call-session adapter and `demos/demo.audio.timeline.html` documents the generic multi-track timeline contract.
- Added pending audio support to `createAudioCallSession(...)` so `processing: true` audio rows without playable paths render disabled preparing role tracks, avoid missing-URL loads, expose pending/playable state, and resolve normally on later `update(...)`.
- Changed `createDevicePrimerModal(...)` to auto-close by default after all checks complete successfully, with `autoCloseOnReady: false` available for flows that need a final Continue or inspection step.
- Added `ui.map.controls` as a MapLibre-oriented control dock for zoom, compass/bearing reset, pitch presets, locate, fit, and layer toggles, with vertical/horizontal orientation options, a dedicated demo, and browser regression coverage.
- Added `ui.elapsed.time` as a compact live elapsed-duration readout for active incident and team-assignment status cards, with fixed `dd:hh:mm:ss` and leading-zero-trimmed `format: "compact"` display modes, a shared module-level ticker for dense dashboards, threshold variants, `chrome: false`, pause/resume/stop methods, docs, demo, and regression coverage.
- Fixed `ui.kanban` fixed-height lane behavior so sparse card stacks keep intrinsic card height, align from the top, and overflowing card stacks scroll inside the lane body while remaining available as drop space.
- Split the combined timeline demo into focused Timeline and Timeline Scrubber pages so each helper's reference panel documents its own contract.
- Added lifecycle-managed custom item content slots to `createTimeline(...)` via `mountItemContent(host, item, context)`, with stable `id`/`contentKey` tracking, preserved hosts across updates, cleanup on removal/destroy, and nested-interactive event guarding.
- Added persistent busy/status toast support to `createToastStack(...)`, including update-capable toast handles, explicit resolve/close helpers, non-dismissible status rows, and per-update speech support.
- Navbar narrow-screen behavior now supports a shared hamburger-collapse mode that keeps the brand visible and flattens mobile content, items, and actions into one menu.
- Added `mobileCollapse`, `contentStartMobile`, `contentCenterMobile`, and `contentEndMobile` to `createNavbar(...)`, with mobile menu ordering of content entries first, then primary items, then actions.
- Added `ui.busy.overlay` as a shared fullscreen-or-scoped busy-state helper with the same spinner styling used by modal busy overlays, plus optional text and explicit cancel handling.
- Added normalized `onItemChange(nextItem, meta)` and `onChange(nextList, meta)` contracts to `incidentTypes(...)` and `incidentTeamsAssignments(...)`, while retaining the existing granular incident-editor callbacks.
- Unsaved incident-type and team-assignment rows now keep stable `_client_key` values so host apps can reconcile local edits and autosave flows without helper-owned persistence semantics.
- Added `group` custom field support to incident type details, including repeatable grouped entries, schema-style field aliases, JSON object/array storage in `detail_entries[].field_value`, viewer rendering, validation, docs, and regression coverage.
- Added shared `ui.field.group` with `createFieldGroup(...)`, and wired `createFieldset(...)` to support `type: "group"` rows for non-incident workflows such as evacuation registries, missing-person reports, addresses, vehicles, and contact lists.
- Added row-aware `fields` declarations to `ui.field.group`: field objects render as full-width rows, while nested field arrays render one row split into matching columns.
- Changed repeatable `ui.field.group` item headers to show compact `#1`, `#2` labels and icon-only remove buttons.
- Added `chrome: false` to `ui.field.group` so hosts can flatten the outer group wrapper when they already own the surrounding field chrome.
- Incident type detail editors now render grouped custom fields with `chrome: false` so the incident field row owns the outer field chrome.
- Fixed incident editor grouped-field layout so editor-owned labels/required chips render outside chrome-less groups and two-column child inputs stay inside the repeatable item border.
- Fixed chrome-less field groups so their internal label/required row is visually suppressed, preventing duplicate labels in incident type field rendering.
- Fixed incident type viewer grouped-field rendering so repeatable group cards use a stacked full-width layout instead of being squeezed into the scalar field value column.
- Changed incident type viewer repeatable group item headings to the same compact `#1`, `#2` numbering used by the editor.
- Added `ui.field.group.presets` with plain schema factories for `person`, `address`, `missingPerson`, and `evacuee`; missing-person and evacuee presets extend the base person fields.
- Added operational field-group presets for `family`, `casualtyPatient`, `infrastructureDamage`, `shelterDamage`, and `roadAccessStatus`, including descriptive SITREP metadata and dedicated demo/reference pages.
- Field groups now support `type: "number-stepper"` child fields backed by `createNumberStepper(...)`, and numeric preset fields now use the shared stepper controls.
- Added generic field-group `breakdown` support for collapsed subfields stored flat in the item object, plus simple additive computed fields such as the Family preset's read-only `member_count = adult_count + children_count`.
- Added non-blocking field-group validation warnings with inline warning indicators for breakdown fields, plus Family preset subtotal checks for overlapping population counts.
- Added `autoValidate` / `validateOnChange` support to field groups so warning indicators refresh during autosaved operator input without requiring an explicit validate button press.
- Changed breakdown-linked field-group validation so collapsed breakdowns do not show warnings until the breakdown is opened/enabled.
- Changed open breakdown validation copy to stay mounted as muted guidance when valid and highlight when invalid, avoiding pop-in/pop-out relayout during autosaved input.
- Tightened Family preset sex breakdown validation so adult male + female must equal adult count, and child male + female must equal children count.
- Added generic hidden field support to `ui.field.group` and hid Family `member_count` from the operator UI while keeping it computed in the value payload.
- Changed person-based field-group presets to collect `last_name` and `first_name`, while keeping hidden computed `name` payload values in `Last, First` format.
- Added string-template computed fields for grouped values, such as `computed: { template: "{last_name}, {first_name}" }`.
- Fixed field-group hosted `checkbox-group` controls so their label is not duplicated by an extra outer group label.
- Added `visibleWhen` support to `ui.field.group` and hid casualty/patient consciousness, triage, and transported fields when condition is `Deceased`.
- Added a lean repeatable `vehicleInvolved` field-group preset for bystander-friendly road accident vehicle details.
- Changed `vehicleInvolved.color` from free text to a select of common vehicle colors plus `Other` and `Unknown`.
- Added `SUV`, `Pick-up`, and `Heavy Equipment` to `vehicleInvolved.vehicle_type` options.
- Updated the Family preset to use `adult_count` and `children_count` as operator-entered base counts, with adult/children breakdown toggles and computed `member_count`.
- Grouped fields now resolve built-in presets from `preset`, `field_preset`, `config.preset`, or JSON `config_json.preset` when `fields[]` is omitted, including incident type editor/viewer rendering.
- Updated field-group demos and preset reference pages to show metadata-only preset declarations alongside factory-spread examples.
- Added individual field-group preset demo pages for person, address, missing person, and evacuee schemas.
- Added `demos/demo.field.group.html` as the focused reference page for standalone and fieldset-hosted repeatable grouped custom fields.
- Added shared `ui.checkbox` with boolean and explicit checked/unchecked value modes, plus a focused demo and regression coverage.
- Added shared `ui.checkbox.group` with array values, min/max validation, select-all/clear methods, field group and fieldset hosting, demo coverage, and regression coverage.
- Fixed `ui.checkbox.group` option labels so each label targets its own checkbox instead of the first option.
- Added additive `requestCancelReason(fromStatus, meta)` support to the team-assignment editor so host apps can replace native cancel-reason prompts with Helper modal UI while preserving the existing `confirmCancel(...)` and `onCancel(...)` boundaries.
- Extended `createReasonFormModal(...)` with additive `detailsRequiredFor` support so details can remain required for all reasons, no reasons, or only a selected subset such as `["other"]`.
- Extended `createMediaStrip(...)` so additive `processing: true` image/video items can render non-clickable placeholder cards without `srcUrl`, with optional `processingLabel` text and normal resolution later when the same `id` receives real media URLs.
- Added additive `viewerAudiographStyle` support to `createMediaStrip(...)` so media-strip viewer sessions can choose the delegated video audiograph render style when `showViewerAudiograph` is enabled.
- Added `createNumberStepper(...)` as a shared numeric stepper primitive with decrement/increment buttons, typed input, min/max/step bounds, and optional prefix/suffix text.
- Incident-type editable `Resources Needed` rows now use the shared numeric stepper and render label plus value control on one row where space allows.

## Release Line Index

- `v0.21.x`: shared window manager, iframe host, and desktop-style workspace foundations
- `v0.20.x`: schema form modal helper and modal-form demos
- `v0.19.x`: hierarchy map, real Cebu hierarchy sample generator, hierarchy demo
- `v0.18.x`: media viewer, modal action consistency, tree-grid search, regression harnesses
- `v0.17.x`: accessibility hardening across interactive UI components and demos
- `v0.16.x`: loader, toggle primitives, tree grid, chrome-less support
- `v0.15.x`: virtual list, scheduler, uploader chunk/resume hooks
- `v0.14.x`: workflow/layout/data primitives and tree/command-palette expansion
- `v0.13.x`: uploader, timeline refinements, kanban
- `v0.12.x`: command palette and tree
- `v0.11.x`: timeline scrubber
- `v0.10.x`: timeline
- `v0.9.x`: datepicker and action modal
- `v0.8.x`: toast and select
- `v0.7.x`: modal foundation and progress
- `v0.6.x`: grid virtualization and dedicated grid demo
- `v0.5.x`: navigation/menu refinements
- `v0.4.x`: navigation/menu utility layer
- `v0.3.x`: grid baseline
- `v0.2.x`: audio UI layer
- `v0.1.x`: initial public prototype

## Releases

### v0.21.83

- Brought toast semantic icon sizing in line with the upgraded dialog icon language so toast status badges are larger and more legible.

### v0.21.82

- Refined shared dialog semantic icons for `uiAlert(...)`, `uiConfirm(...)`, and `uiPrompt(...)` so success/info/warning/error variants use more legible glyphs and a larger visual treatment.

### v0.21.81

- Updated incident-type and team-assignment helper styles to use shared theme tokens instead of hard-coded colors, so the panels, drawers, and chips adapt to the active UI theme.

### v0.21.80

- Expanded the generated helper bundle and bundle-preferring loader path so `incident.*` registry entries now resolve from `dist/helpers.ui.bundle.min.js` and `.css` alongside `ui.*`.
- This removes the bundle-mode source fallback where incident helpers could re-fetch shared `ui.*` modules through direct ESM imports after the main helper bundle was already loaded.

### v0.21.79

- Extended `createNavbar(...)` with optional `brandMedia` so app shells can render an icon or logo beside the brand text without replacing the helper layout.
- Added additive navbar custom-content slots through `contentStart`, `contentCenter`, and `contentEnd`, with demo/manual/regression coverage for text, DOM-node, and callback-backed content.

### v0.21.78

- Added a helper-owned `avatar` field type to `createFormModal(...)` for circular profile-photo selection with live preview and `File`-based submit values.
- Extended `createAccountFormModal(...)` with additive `avatar` support, removed the need for user-facing avatar-path editing in the preset flow, and kept avatar upload local-render only when cross-origin Workspace bridging is active.
- Updated the dedicated account preset demo and preset regression coverage to exercise avatar preview and selected-file submission behavior.

### v0.21.77

- Added an optional minified `ui.*` distribution bundle at `dist/helpers.ui.bundle.min.js` plus `dist/helpers.ui.bundle.min.css`, generated through `npm run build:ui-bundle`.
- Extended `uiLoader` with opt-in bundle preference via `preferBundles` / `setPreferBundles(true)`, while keeping the default modular source-loading path unchanged.
- Hardened loader request behavior by parallelizing dependency walks and normalizing `ui.form.modal.presets.js` versioned URLs so the same helper file is not referenced under mixed cache keys.

### v0.21.76

- Added narrow declarative `visibleWhen` support to `createFormModal(...)` so dependent fields can hide and show based on current helper-owned form values without app-local row regeneration.
- Hidden `visibleWhen` fields now drop out of helper validation and `getValues()` / submit payloads while still retaining their previous value internally for restoration when they become visible again.

### v0.21.75

- Added `showSummary` support to `createDevicePrimerModal(...)` so engineers can hide the aggregate summary strip in the modal wrapper the same way they can in the inline helper.
- Tightened modal device-primer footer behavior so `Retry Failed` is only rendered when at least one check is currently retryable instead of showing a permanently disabled action.

### v0.21.74

- Hardened `ui.device.primer` audio playback checks again so the helper waits briefly for delayed `AudioContext` resume transitions before reporting the audio path as blocked.
- Extended device-primer regression coverage with a delayed-resume audio stub to better match real-origin browser timing.

### v0.21.73

- Fixed `ui.device.primer` audio playback retries so a direct user-triggered retry preserves browser user activation instead of always deferring through a promise queue before calling `AudioContext.resume()`.
- Extended device-primer regression coverage with a transient-gesture audio stub to catch real-origin autoplay-policy failures that localhost-style stubs would miss.

### v0.21.72

- Added `cancelBusy` support to `createModal(...)` and `modal.setBusy(...)`, allowing busy overlays to expose an optional cancel action that can abort app-owned work before clearing helper busy state.
- Updated the modal demo to exercise the busy overlay cancel flow and extended modal busy regression coverage around the new overlay action.

### v0.21.71

- Updated `uiAlert(...)`, `uiConfirm(...)`, and `uiPrompt(...)` so the header close icon is hidden by default, keeping dialog dismissal focused on the helper-owned footer actions unless callers explicitly opt back in with `showCloseButton: true`.
- Extended dialog regression coverage so the shared async dialog suite now asserts both the right-aligned footer action row and the hidden-by-default header close button contract.

### v0.21.70

- Updated `ui.modal` mobile behavior so phone-sized viewports render modal-family panels as fullscreen surfaces with no border or rounded corners, preserving header/body/footer structure while giving the body the scroll region.
- Disabled header dragging for compact/mobile modal viewports and added dedicated browser regression coverage in `tests/modal.mobile.regression.html` and `tests/modal.mobile.regression.mjs`.

### v0.21.69

- Added `kind: "password"` support to `ui.property.editor`, reusing the shared `ui.password` control so secret-bearing settings can stay inside the normal property-editor schema and change lifecycle.
- Extended property-editor loader wiring, demo coverage, and browser regression coverage for hosted password rows, including masked-by-default rendering and shared show/hide toggle behavior.

### v0.21.68

- Added `ui.tree.select` as a shared hierarchical single-select picker for grouped taxonomies, with parent-context search, branch expand/collapse, and the same floating body-level menu model used by `ui.select`.
- Added `type: "ui.treeSelect"` hosting support to `createFormModal(...)`, including value participation, helper-applied error state, focus targeting, and floating-menu behavior inside modal containers.
- Added the dedicated `demos/demo.tree.select.html` page plus focused tree-select and form-modal regression coverage.

### v0.21.67

- Added additive `toolbarStart` and `toolbarEnd` extension seams to `ui.grid` so app-owned toolbar content can coexist with helper-owned search and page-size controls without DOM reinjection.
- Toolbar slots accept text, DOM nodes, arrays, or a function form with grid render context including query, selection, visible rows, and current total row count.
- Updated the grid demo and README reference so downstream apps have a documented supported path for count pills, add buttons, and similar toolbar controls.
- Added focused grid browser regression coverage proving custom toolbar content survives search, sort, and pagination-driven grid re-renders.

### v0.21.66

- Expanded `ui.icons` again with shared settings/account glyphs for app-shell and profile surfaces:
  - `actions.settings`
  - `actions.options`
  - `people.profile`
  - `people.account`
- Updated the icon spec, README references, and browser regression coverage for the new icon ids.
- Bumped the icon revision chain to `0.21.66` so browsers stop serving stale icon module graphs after the catalog update.

### v0.21.65

- Expanded `ui.icons` with two new shared glyphs needed by device and notification flows:
  - `media.microphone`
  - `comms.notification`
- Refreshed `ui.device.primer` visuals without changing its public API:
  - added stronger summary hierarchy with semantic status icon treatment
  - added row-level device/capability icons
  - added icon-backed status pills and retry affordances
  - increased ready/blocked visual contrast through row and summary state styling
- Updated the loader so `ui.device.primer` pulls shared icon styling and versioned primer assets together.
- Updated icon and device-primer browser regression coverage for the new icon ids and primer icon rendering.

### v0.21.64

- Updated the shared password-field helper to use icon-based show/hide toggles instead of visible `Show` / `Hide` button text.
- Added `actions.hide` to `ui.icons` so password fields can pair the existing view glyph with a shared hidden-state eye-slash icon.
- Tightened `ui.password` styling for the new compact icon toggle while preserving accessible labels through `showLabel` / `hideLabel`.
- Bumped the password, icon, and overlay revision chains to `0.21.64` so login, re-auth, change-password, and standalone password-field demos stop serving stale cached modules.
- Updated password and icon regression coverage plus the password demo/README copy to reflect the icon-based toggle behavior.

### v0.21.63

- Expanded the shared `ui.icons` registry with new reusable icon groups for:
  - `people`
  - `workflow`
  - `places`
  - `time`
  - `comms`
  - `assets`
- Added new action and navigation icons so shared demos and downstream apps can stop repeating common inline SVG:
  - `actions.view`
  - `actions.refresh`
  - `actions.more-horizontal`
  - `actions.save`
  - `actions.attach`
  - `actions.export`
  - `actions.sort`
  - `navigation.home`
  - `navigation.menu`
  - `data.list`
- Added workflow/state icons for incident-focused UIs:
  - `workflow.assigned`
  - `workflow.requested`
  - `workflow.accepted`
  - `workflow.en-route`
  - `workflow.on-scene`
  - `workflow.completed`
  - `workflow.cancelled`
- Added new domain-oriented shared icons for location, time, communication, and assets:
  - `places.pin`, `places.route`, `places.map`, `places.home-base`
  - `time.clock`, `time.history`, `time.calendar`, `time.timer`
  - `comms.phone`, `comms.radio`, `comms.message`, `comms.signal`
  - `assets.vehicle`, `assets.document`, `assets.camera`, `assets.clipboard`
- Updated `demos/demo.icons.html` catalog copy so the shared icon demo explicitly reflects the expanded registry.
- Updated icon documentation and regression coverage so the spec, README, and browser test reflect the new categories and icon ids.
- Bumped the icon revision chain to `0.21.63` so browsers stop serving stale `ui.icons` module graphs after catalog updates.

### v0.21.61

- Added helper-owned async pending support across the dialog family: `uiAlert(...)`, `uiConfirm(...)`, and `uiPrompt(...)`.
- Dialog primary actions can now stay open while local async handlers run:
  - `onAcknowledge` for alerts
  - `onConfirm` for confirms
  - `onSubmit` for prompts
- While a promise-returning primary handler is pending, dialogs now lock duplicate actions, show modal busy state, and keep the shell open until the async work resolves.
- Rejected async handlers now keep the dialog open and surface inline error text instead of closing immediately.
- Added dialog async demos plus a dedicated regression harness for confirm/prompt async pending and inline-error behavior.
- Normalized the overlay-routing revision chain to `0.21.61` so dialog, modal, form-modal, and workspace-bridge imports stop serving stale overlay modules.

### v0.21.60

- Changed `ui.audio.audiograph` `classic-waveform` to an oscilloscope-style continuous line renderer inspired by WaveForge's waveform mode.
- `classic-waveform` now draws one current-frame waveform path plus a faint mirrored reflection instead of the previous vertical-stroke approximation.
- Bumped the audio revision chain again so browsers load the new waveform renderer cleanly.

### v0.21.59

- Fixed another `ui.audio.audiograph` `classic-waveform` regression where the current-frame waveform renderer referenced `ms` without defining it locally inside `drawClassicWaveform(...)`.
- Bumped the audio revision chain again so browsers stop loading the broken `0.21.58` audiograph module graph.

### v0.21.58

- Fixed a `ReferenceError` in `ui.audio.audiograph` `classic-waveform` where the current-frame helper functions were moved outside the component scope and could no longer access analyser state.
- Bumped the audio revision chain again so browsers stop loading the broken `0.21.57` audiograph module graph.

### v0.21.57

- Replaced the experimental `ui.audio.audiograph` `classic-waveform` history/write-head model with a simpler oscilloscope-style current-frame renderer inspired by WaveForge's waveform mode.
- `classic-waveform` now draws the current time-domain frame as a continuous line with a subtle mirrored reflection instead of trying to simulate a buffered packet window.
- Bumped the audio revision chain again so browsers load the simplified waveform renderer cleanly.

### v0.21.56

- Changed `ui.audio.audiograph` `classic-waveform` from a rolling rebucket window to a fixed-slot write-head model.
- This keeps column positions stable while new waveform energy is written forward at a controlled rate, improving legibility during live input.
- Bumped the audio revision chain again so browsers load the write-head waveform model cleanly.

### v0.21.55

- Slowed `ui.audio.audiograph` `classic-waveform` history updates so the buffered waveform window remains readable during live input.
- The renderer now appends a small downsampled batch per interval instead of pushing the full analyser frame every paint, avoiding overly fast right-to-left motion.
- Bumped the audio revision chain again so browsers load the readability fix cleanly.

### v0.21.54

- Reworked `ui.audio.audiograph` `classic-waveform` again to use a rolling sample-history buffer with per-column peak aggregation.
- This shifts the style from current-frame-only rendering toward the buffered waveform-window approach commonly used for classic waveform visuals.
- Bumped the audio revision chain again so browsers load the buffered waveform renderer cleanly.

### v0.21.53

- Reworked `ui.audio.audiograph` `classic-waveform` again to use peak-envelope bucketing per column instead of raw per-point sampling.
- This fixes the low-frequency lobe/oval look from the previous raw renderer and brings the style closer to a dense classic waveform packet.
- Bumped the audio revision chain again so browsers load the updated waveform behavior cleanly.

### v0.21.52

- Changed `ui.audio.audiograph` `classic-waveform` to a purely raw rendering mode.
- Removed tapering, normalization, and extra amplitude shaping from `classic-waveform`; the style now uses the current time-domain frame directly with only the explicit sensitivity multiplier applied.
- Bumped the audio revision chain again so browsers load the raw waveform renderer cleanly.

### v0.21.51

- Refined `ui.audio.audiograph` `classic-waveform` again to use a mirrored peak-envelope interpretation of the current time-domain frame.
- Reduced shaped/tapered behavior further so the style reads more like raw waveform energy around the center line and less like a beautified signed packet.
- Bumped the audio revision chain again so browsers load the refined waveform behavior cleanly.

### v0.21.50

- Changed `ui.audio.audiograph` `classic-waveform` from a running history trace to a stationary live waveform.
- `classic-waveform` now updates in place across the width instead of scrolling/running, which better matches the intended classic waveform visual treatment.
- Bumped the audio revision chain again so browsers load the stationary waveform renderer cleanly.

### v0.21.49

- Fixed `ui.audio.audiograph` `classic-waveform` history direction so fresh waveform content reads left-to-right instead of appearing to run right-to-left.
- Bumped the audio revision chain again so browsers load the corrected waveform history mapping cleanly.

### v0.21.48

- Reworked `ui.audio.audiograph` `classic-waveform` again to use a short rolling history of dense mirrored vertical strokes.
- Removed the grid treatment for `classic-waveform` and shifted the style closer to a thin classic waveform silhouette rather than an instrument-panel graph.
- Bumped the audio revision chain again so browsers load the reworked waveform behavior cleanly.

### v0.21.47

- Tuned `ui.audio.audiograph` `classic-waveform` again to avoid over-normalized center blocks on short spoken phrases.
- Reduced normalization aggressiveness and added softer amplitude shaping so normal speech retains more internal contour instead of collapsing into a near-solid center mass.
- Bumped the audio revision chain again so browsers load the refined waveform behavior cleanly.

### v0.21.46

- Reworked `ui.audio.audiograph` `classic-waveform` to use vertical mirrored waveform strokes instead of connected ribbon-style outlines.
- Reduced grid prominence, removed peak-marker emphasis for this style, and tightened the packet envelope so it reads closer to a classic waveform silhouette.
- Bumped the audio revision chain again so browsers load the reworked renderer cleanly.

### v0.21.45

- Refined `ui.audio.audiograph` `classic-waveform` again to reduce the ribbon-like look.
- Switched the renderer to a more line-dominant, denser, and more center-packed waveform packet so it reads closer to a classic waveform silhouette.
- Bumped the audio revision chain again so browsers load the updated renderer cleanly.

### v0.21.44

- Increased `ui.audio.audiograph` `classic-waveform` sensitivity for live sources.
- Added peak-normalized waveform scaling so ordinary speech produces a clearer waveform packet instead of requiring very loud input.
- Bumped the audio revision chain again so browsers load the refined waveform sensitivity changes cleanly.

### v0.21.43

- Refined `ui.audio.audiograph` `classic-waveform` so it renders closer to a true classic waveform packet instead of a ribbon-like shape.
- Increased time-domain detail, added stronger packet tapering at the edges, and tightened the stroke/fill treatment for a sharper centered waveform look.
- Bumped the audio revision chain again so browsers load the refined waveform renderer cleanly.

### v0.21.42

- Added `style: "classic-waveform"` to `ui.audio.audiograph`.
- `classic-waveform` provides a centered live waveform-inspired visual style without timeline/history semantics.
- Added:
  - `docs/ui-audio-audiograph-classic-waveform-addendum.md`
  - `docs/ui-audio-audiograph-classic-waveform-checklist.md`
- Updated `demos/demo.audio.audiograph.stream.html` so the new style can be exercised directly.
- Bumped the audio revision chain again so browsers pick up the updated audiograph runtime and demo imports cleanly.

### v0.21.41

- Added `ui.device.primer` and `createDevicePrimerModal(...)`.
- Added project-configurable startup checks for:
  - microphone
  - camera
  - geolocation
  - speech synthesis
  - speech recognition
  - notifications
  - audio playback readiness
  - media-device enumeration
- Added:
  - `demos/demo.device.primer.html`
  - `tests/device.primer.regression.html`
  - `tests/device.primer.regression.mjs`
- `ui.device.primer` defaults to auto-run so configured checks begin immediately unless the app explicitly sets `autoRun: false`.

### v0.21.40

- Fixed `ui.audio.audiograph` `transparentBackground` so it also skips the renderer-painted gradient background inside the canvas.
- Bumped the audio loader revision again so updated audio JS and CSS load cleanly in browsers:
  - `ui.audio.player`
  - `ui.audio.audiograph`
  - `ui.audio.callSession`

### v0.21.39

- Fixed stale-browser-module loading for the audio helper chain after the `ui.audio.audiograph` livestream update.
- Added revisioned loader paths for:
  - `ui.audio.player`
  - `ui.audio.audiograph`
  - `ui.audio.callSession`
- Updated `js/ui/ui.audio.callSession.js` to import the revisioned audio runtime modules directly so browser caches do not keep serving the old audiograph contract.
- Added `transparentBackground` option to `ui.audio.audiograph` so the graph shell and canvas fill can render fully transparent against the parent surface.

### v0.21.38

- Added stream-native support to `ui.audio.audiograph`.
- Added:
  - `attachMediaStream(stream)`
  - `attachAudioNode(node)`
  - `resume()`
- Kept `unlockAudioContext()` as a compatibility alias to `resume()`.
- The audiograph now reports the active source through `getState().sourceType`:
  - `none`
  - `media-element`
  - `media-stream`
  - `audio-node`
- Added dedicated livestream demo and regression coverage:
  - `demos/demo.audio.audiograph.stream.html`
  - `tests/audio.audiograph.stream.regression.html`
  - `tests/audio.audiograph.stream.regression.mjs`

### v0.21.37

- Fixed `demos/demo.audio.html` sample compatibility handling.
- The audio demo now filters out incompatible sample payloads whose audio paths do not match the demo's `baseUrl` strategy.
- Corrected stale demo reference metadata for `ui.audio.callSession`:
  - documented actual options
  - documented actual events
  - documented actual instance methods
- Added a narrow demo boot error state instead of failing silently when no compatible audio sample can be mounted.

### v0.21.36

- Updated shared `ui.select` multiple-select option rows to improve selected-state visibility.
- Added:
  - stable left padding for multi-select menu items
  - left-side check affordance for multi-select rows
  - green selected check icon when an item is selected
- Updated:
  - `js/ui/ui.select.js`
  - `css/ui/ui.select.css`

### v0.21.35

- Extended `ui.property.editor` to support hosted shared `ui.select` rows.
- Added multi-value property editing support through:
  - `kind: "ui.select"`
  - `items`
  - `multiple`
  - shared `ui.select` options such as `searchable`, `clearable`, and `closeOnSelect`
- Updated:
  - `js/ui/ui.property.editor.js`
  - `css/ui/ui.property.editor.css`
  - `docs/ui-property-editor-proposal.md`
  - `docs/ui-property-editor-v1-spec.md`
  - `docs/ui-property-editor-checklist.md`
  - `demos/demo.property.editor.html`
  - `tests/property.editor.regression.html`

### v0.21.34

- Added `ui.property.editor` as a shared inspector-style editing surface.
- Added grouped property sections with:
  - stable label/value/action row layout
  - selection header support
  - typed rows for `display`, `text`, `textarea`, `number`, `checkbox`, `toggle`, `select`, `color`, `color-select`, `action`, and `divider`
  - read-only and mixed-value handling
  - property-level error rendering
  - structured `onPropertyChange(...)` and `onAction(...)` callbacks
- Added:
  - `docs/ui-property-editor-proposal.md`
  - `docs/ui-property-editor-v1-spec.md`
  - `docs/ui-property-editor-checklist.md`
  - `demos/demo.property.editor.html`
  - `tests/property.editor.regression.html`
  - `tests/property.editor.regression.mjs`
- Updated:
  - `js/ui/ui.loader.js`
  - `js/demo/demo.shell.js`
  - `demos/index.html`
  - `README.md`
  - `docs/pbb-refactor-playbook.md`

### v0.21.33

- Added opt-in measured-window virtualization to `ui.chat.thread`.
- Added narrow thread virtualization options:
  - `enableVirtualization`
  - `virtualThreshold`
  - `virtualOverscan`
  - `bottomAnchorThreshold`
- Preserved append/prepend reading behavior for long threads without changing the existing message data contract.
- Updated the chat regression harness to cover:
  - virtualized activation above threshold
  - append while pinned near bottom
  - append while reading older messages
  - prepend history preservation
- Updated:
  - `js/ui/ui.chat.thread.js`
  - `css/ui/ui.chat.thread.css`
  - `demos/demo.chat.thread.html`
  - `tests/chat.regression.html`
  - `docs/ui-chat-thread-virtualization-proposal.md`
  - `docs/ui-chat-thread-virtualization-v1-spec.md`
  - `docs/ui-chat-thread-virtualization-checklist.md`
  - `README.md`

### v0.21.32

- Extended `ui.chat.upload.queue` with visual per-item upload state.
- Queue items now support:
  - `status`
    - `queued`
    - `uploading`
    - `uploaded`
    - `failed`
  - `progress`
  - `progressLabel`
  - `errorText`
- Kept the boundary narrow:
  - the queue renders visual progress and failure state
  - apps still own upload transport, retry, and backend orchestration
- Updated:
  - `demos/demo.chat.upload.queue.html`
  - `tests/chat.regression.html`
  - `docs/ui-chat-upload-queue-v1-spec.md`
  - `docs/ui-chat-upload-queue-progress-addendum.md`
  - `docs/ui-chat-upload-queue-progress-checklist.md`
  - `README.md`
  - `docs/pbb-refactor-playbook.md`

### v0.21.31

- Extended `ui.chat.thread` with helper-owned per-message action menus.
- Added narrow thread options:
  - `showMessageMenuTrigger`
  - `getMessageMenuItems(message, state)`
  - `messageMenuOptions`
  - `onMessageMenuSelect(message, item, meta)`
- Kept menu actions app-defined; the helper now owns only:
  - trigger rendering
  - shared menu mounting
  - selection routing
- Updated:
  - `demos/demo.chat.thread.html`
  - `tests/chat.regression.html`
  - `docs/ui-chat-thread-v1-spec.md`
  - `docs/ui-chat-thread-message-actions-addendum.md`
  - `docs/ui-chat-thread-message-actions-checklist.md`
  - `README.md`
  - `docs/pbb-refactor-playbook.md`

### v0.21.30

- Extended `ui.chat.composer` so the attach control now owns a hidden native file input instead of only exposing a thin click callback.
- Added composer-side file-picker options:
  - `accept`
  - `multiple`
  - `capture`
  - `onFilesSelected(files, meta)`
- Added shared draft attachment queue helper:
  - `ui.chat.upload.queue`
  - `createChatUploadQueue(container, data, options)`
- `ui.chat.upload.queue` now renders:
  - grouped `image` / `video` attachments through `ui.media.strip`
  - listed `audio` / `file` rows with remove actions
- Added:
  - `demos/demo.chat.upload.queue.html`
  - `docs/ui-chat-upload-queue-v1-spec.md`
  - `docs/ui-communication-upload-queue-checklist.md`
- Updated:
  - `demos/demo.chat.composer.html`
  - `tests/chat.regression.html`
  - `README.md`
  - `docs/pbb-refactor-playbook.md`

### v0.21.29

- Added first-wave communication helpers:
  - `ui.chat.thread`
  - `ui.chat.composer`
- Added shared chat-thread rendering for:
  - incoming, outgoing, and system messages
  - grouped message runs
  - outgoing delivery/read states
  - grouped image/video attachments through `ui.media.strip`
  - listed audio/file attachments
  - helper-owned empty state
- Added shared chat-composer behavior for:
  - multiline message entry
  - send guard for empty input
  - `Enter` submit and `Shift+Enter` newline
  - busy/disabled state
  - optional attachment trigger
- Added:
  - `demos/demo.chat.thread.html`
  - `demos/demo.chat.composer.html`
  - `tests/chat.regression.html`
  - `tests/chat.regression.mjs`
  - `docs/ui-communication-first-wave-checklist.md`
- Updated:
  - `README.md`
  - `docs/pbb-refactor-playbook.md`
  - `demos/index.html`
  - `js/demo/demo.shell.js`

### v0.21.28

- Added optional `brandSubtitle` support to `ui.navbar` so apps can render one compact muted metadata line below the main brand text without replacing the shared navbar shell.
- Kept single-line brand rendering unchanged when `brandSubtitle` is omitted.
- Updated:
  - `demos/demo.navbar.html`
  - `tests/navbar.regression.html`
  - `README.md`
- This covers Workspace-style build stamps such as `Build app-DWv_LvMM` while keeping the full brand region as one click target.

### v0.21.27

- added session-style cross-origin bridged preset submit updates through:
  - `modal.form.session.open`
  - `modal.form.update`
  - `modal.form.close`
- shipped child-driven busy/error round-trip for cross-origin preset submit flows so parent-owned bridged login/reauth/account/change-password modals can stay open while the child app runs async submit logic
- added explicit busy-submit coverage to the cross-origin harness and browser regressions
- added a manual `Open Login Busy` trigger to `demos/demo.workspace.bridge.cross.origin.html`
- added plain operational guidance in `docs/ui-workspace-overlay-routing-guide.md` so downstream teams have one helper-owned rule set for what auto-routes, what requires the explicit cross-origin bridge, and when admin forms such as `Add User` should move onto the `generic-form` bridge/session path
- bumped overlay-routing revision chain to `v0.21.27`

## Release Notes
### v0.21.26

- Fixed direct cross-origin `showWorkspaceFormModal(...)` calls so they now default `ownerTitle` from the child document title when the payload omits it.
- Practical effect:
  - schema-style `generic-form` bridge calls now keep the same owning-window subtitle behavior that preset-based bridged modals already had
  - child apps using the raw form bridge no longer need to wire `ownerTitle` manually just to preserve Workspace ownership context
- Bumped the overlay-routing revision chain again so downstream Workspace/child app refreshes fetch the owner-title fallback for direct bridge calls instead of stale cached modules.

### v0.21.25

- Extended the explicit cross-origin `modal.form.open` runtime to support `intent: "generic-form"` for schema-style row-based admin forms.
- The parent Workspace host now accepts generic row payloads under the existing JSON-safe row contract and reuses the shared `createFormModal(...)` renderer instead of introducing a separate bridge-only form engine.
- Added generic-form coverage to:
  - `samples/iframe/workspace-ui-bridge.fixture.html`
  - `samples/iframe/workspace-ui-bridge.cross-origin.child.html`
  - `tests/workspace.bridge.regression.html`
  - `tests/workspace.bridge.cross.origin.regression.html`
- The generic-form bridge path now proves:
  - parent-owned rendering
  - owner-title subtitle preservation
  - form-level error rendering
  - field-level error rendering
  - `ui.select` item transport
  - no local child fallback modal remains visible
- Bumped the overlay-routing revision chain again so downstream Workspace/child app refreshes fetch the generic-form bridge support instead of stale cached modules.

### v0.21.24

- Added a first-class local cross-origin Workspace bridge harness so bridge changes can be validated in this repo before downstream teams spend time testing them in live apps.
- Added a two-origin manual demo surface:
  - `demos/demo.workspace.bridge.cross.origin.html`
  - `samples/iframe/workspace-ui-bridge.cross-origin.child.html`
- Added a local launcher script:
  - `node scripts/run-workspace-bridge-cross-origin-demo.mjs`
- Added automated browser regression coverage with two local origins on different ports:
  - `tests/workspace.bridge.cross.origin.regression.html`
  - `tests/workspace.bridge.cross.origin.regression.mjs`
- Current cross-origin regression proves:
  - bridged login renders in the parent shell
  - owner-title subtitle is preserved
  - no local child fallback modal remains visible
  - bridged account preserves serialized `Change Password` footer action
  - bridged account extra-action round-trip returns the correct `actionId`

### v0.21.23

- Fixed bridged account preset footer parity so cross-origin Workspace-rendered account modals no longer drop serialized `extraActions` such as `Change Password`.
- The cross-origin `modal.form.open` transport now carries narrow serializable footer extensions:
  - `extraActions`
  - `extraActionsPlacement`
- Parent Workspace renders those footer actions and returns `reason: "action"` plus the selected `actionId` and current form values back to the child app.
- Child preset logic now runs the existing local `extraActions[].onClick(values, ctx, actionId)` contract after the bridged action round-trip, then reopens the bridged account modal when the extra action remains non-closing.
- Bumped the overlay-routing revision chain again so downstream Workspace/child app refreshes fetch the bridged footer-action fix instead of stale cached modules.

### v0.21.22

- Fixed bridged preset modal ownership subtitles so cross-origin Workspace-rendered login, re-auth, account, and change-password modals no longer depend on each child app explicitly passing `ownerTitle`.
- Bridged preset payloads now resolve `ownerTitle` in this order:
  - explicit `options.ownerTitle`
  - child document title
  - empty string
- Practical effect:
  - Workspace-hosted child apps such as HQ now show the owning window/app title under the bridged modal title by default, provided the child document has a meaningful `document.title`.
- Bumped the overlay-routing revision chain again so downstream Workspace/child app refreshes fetch the owner-title fix instead of stale cached preset modules.

### v0.21.21

- Extended the explicit cross-origin Workspace form bridge beyond auth-only flows so `modal.form.open` now supports:
  - `intent: "account"`
  - `intent: "change-password"`
- Updated `createAccountFormModal(...)` and `createChangePasswordFormModal(...)` so cross-origin iframe apps can delegate those preset flows to the parent Workspace shell when same-origin parent mounting is unavailable.
- Kept the transport model unchanged:
  - parent Workspace renders the helper-owned modal shell
  - child apps still own API submission, password/account business rules, and follow-up state handling
- Added bridge fixture and browser regression coverage for:
  - accepted bridged account form requests
  - accepted bridged change-password form requests
  - cancel/result round-trip for both new intents
- Bumped the overlay-routing revision chain again so downstream Workspace/child app refreshes fetch the widened bridge/preset modules instead of stale cached builds.

### v0.21.20

- Improved the shared `ui.modal` shell with default header-only dragging (`draggable: true`) so modals can be moved aside while inspecting content behind them.
- Added optional `ownerTitle` rendering below the main modal title so parent-owned bridged Workspace modals can keep visible ownership context such as `PBB HQ`.
- Kept drag initiation narrow:
  - only the modal header starts drag
  - interactive header controls such as the close button and header actions do not start movement
- Drag state is clamped to the viewport and resets on close/reopen so reopened modals return to their default centered position.
- `createActionModal(...)` and `createFormModal(...)` now inherit the upgraded shell behavior automatically.
- Workspace-bridged form modals can now pass `ownerTitle` through the normalized payload into the parent-owned modal shell.
- Updated the modal demo and regression harness to cover:
  - draggable header affordance
  - owner-title rendering
  - close-button non-drag behavior
  - drag reset on reopen

### v0.21.19

- Fixed the first shipped cross-origin Workspace form bridge so interactive parent-owned login and re-auth modals no longer fall back locally after the old 900ms request timeout.
- Interactive bridge requests now:
  - probe bridge availability first
  - keep the actual parent-owned dialog/modal request open until the user responds
- This prevents duplicate modal copies in real Workspace-hosted child apps such as `workspace.pbb.ph` hosting `hub.pbb.ph`, where the parent modal was visible but the child later opened a local fallback after timing out.
- Tightened browser regression coverage so bridged login and re-auth flows are held open past the old timeout before asserting cancel/result round-trip.
- Added a parent-document stylesheet safety fix for parent-mounted form modals so bridged login and re-auth forms keep the correct hidden submit proxy and password-toggle styling when rendered outside the child iframe.

### v0.21.18

- Added explicit cross-origin Workspace form-bridge support for helper-owned login and re-auth modal flows.
- `ui.workspace.bridge` now supports:
  - `namespace: "pbb.workspace.ui.bridge.v2"`
  - `method: "modal.form.open"`
- Added child-side helper access through:
  - `showWorkspaceFormModal(payload, options)`
- Added parent host handling for serializable cross-origin form-modal payloads with:
  - `intent: "login"`
  - `intent: "reauth"`
- Kept the contract narrow:
  - parent renders the helper-owned modal shell
  - child app still owns API/auth/business logic
  - only JSON-safe row payloads are accepted
  - arbitrary cross-origin DOM mirroring is still not supported
- Updated login and re-auth presets so cross-origin iframe apps can delegate those flows to the parent Workspace surface when same-origin parent mounting is unavailable.
- Extended the workspace bridge fixture and browser regression harness to verify:
  - explicit login form parent rendering
  - explicit re-auth form parent rendering
  - result round-trip back to the child iframe

### v0.21.17

- Added a cache-busting follow-up for the new same-origin Workspace overlay routing chain.
- Versioned the live loader/import graph for:
  - `ui.workspace.bridge`
  - `ui.modal`
  - `ui.dialog`
  - `ui.form.modal`
  - `ui.form.modal.presets`
- This release exists to prevent stale browser ES-module caches from reusing pre-routing builds after teams refresh vendored helper copies.
- Practical effect:
  - Workspace-hosted child apps such as HQ should fetch the current modal/bridge modules after a helper refresh and hard browser reload, instead of continuing to render modal-family overlays inside the child iframe because of cached older modules.

### v0.21.16

- Added automatic same-origin Workspace overlay routing for modal-family helpers.
- When a trusted same-origin Workspace host is installed, plain helper overlays can now mount into the parent Workspace surface without manual bridge code:
  - `createModal(...)`
  - `createActionModal(...)`
  - `createFormModal(...)`
  - presets built over `createFormModal(...)`
- Added shared `renderTarget` support on the modal shell with:
  - `"auto"`
  - `"local"`
  - `"parent"`
- Kept existing explicit bridge behavior for:
  - delegated toast delivery
  - delegated alert / confirm / prompt dialogs
  - explicit simple `showWorkspaceActionModal(...)`
- Extended the Workspace bridge fixture/demo and browser regression harness to verify:
  - local plain-helper modal fallback with no host installed
  - parent rendering for plain action modals in a same-origin Workspace host
  - parent rendering for plain form modals in a same-origin Workspace host
  - no duplicate child modal copy when parent routing is active

### v0.21.15

- Added narrow validation parity to `ui.fieldset` so grouped page forms can now render field-level and form-level errors without widening the component into a second submit engine.
- Added `createFieldset(...)` methods:
  - `getErrors()`
  - `getFormError()`
  - `setErrors(errors)`
  - `clearErrors()`
  - `setFormError(message)`
  - `clearFormError()`
  - `applyApiErrors(response)`
- Fieldset validation now:
  - renders inline error messages
  - sets and clears `aria-invalid` on matching controls
  - resolves dotted API error keys back to the base field name
  - keeps grouped two-column field alignment stable when one side shows an error and the other does not
- Updated:
  - `demos/demo.fieldset.html`
  - `tests/fieldset.regression.html`
  - `README.md`

### v0.21.14

- Promoted `ui.fieldset` into the documented/shared helper surface with loader registration, demo coverage, and regression coverage.
- `createFieldset(container, options)` now has a dedicated public contract for semantic grouped form sections using form-modal-style `rows[]`.
- Added:
  - `demos/demo.fieldset.html`
  - `tests/fieldset.regression.html`
  - `tests/fieldset.regression.mjs`
- Updated the demo shell, catalog, README, and playbook so grouped form sections are discoverable alongside other form/shared UI primitives.

### v0.21.13

- Split the overloaded `demos/demo.ui.html` catch-all page into focused utility demos:
  - `demos/demo.toast.html`
  - `demos/demo.select.html`
  - `demos/demo.toggle.button.html`
  - `demos/demo.toggle.group.html`
  - `demos/demo.buttons.html`
- Repurposed `demos/demo.ui.html` into a lightweight utilities overview/router page.
- Updated demo navigation, catalog, and helper docs so toast/select/toggle/button references point to focused pages instead of the old utility catch-all.

### v0.21.12

- Changed the shared `ui.modal` shell so long content scrolls only inside the modal body region instead of scrolling the entire panel.
- The modal header now stays fixed at the top of the shell and the footer stays fixed at the bottom while the body scrolls.
- Added browser regression coverage proving tall modal content keeps the header and footer stationary while the body scrolls.

### v0.21.11

- Extended `ui.navbar` so primary navigation items can now host shared menu dropdowns, not only right-side actions.
- Added item-level menu support through:
  - `items[].menuItems`
  - `items[].menuOptions`
  - `onItemMenuSelect(item, menuItem, meta)`
  - `onItemMenuOpenChange(item, open)`
- Kept backward compatibility:
  - plain `items[]` still use `onNavigate(item)`
  - existing `actions[].menuItems` behavior remains unchanged
- Updated `demos/demo.navbar.html` to demonstrate a Workspace-style primary-nav `Apps` menu alongside the existing right-side profile menu.
- Split the overloaded navigation documentation surface into focused demos:
  - `demos/demo.navbar.html`
  - `demos/demo.sidebar.html`
  - `demos/demo.breadcrumbs.html`
  - `demos/demo.dropdown.html`
  - `demos/demo.dropup.html`
- Repurposed `demos/demo.nav.html` into a lightweight navigation overview/router page instead of a five-helper catch-all.

### v0.21.10

- Reorganized the demo/catalog navigation so the window subsystem now lives under its own `Window` group instead of being mixed into generic `Utilities`.
- Split the former combined `Window` demo into:
  - `demos/demo.window.html` for the single managed window shell
  - `demos/demo.window.manager.html` for multi-window workspace and taskbar behavior
- Updated the demo catalog in `demos/index.html` so window-related pages are grouped as:
  - `Window`
  - `Window Manager`
  - `Iframe Host`
  - `Workspace Bridge`
- Updated public docs/spec references so `demo.window.manager.html` is treated as the manager/workspace reference page while `demo.window.html` remains the focused shell demo.

### v0.21.9

- Upgraded `ui.window` taskbar behavior to support both the original minimized-only recovery strip and a workspace-style all-open-window taskbar.
- Added manager options:
  - `taskbarMode`
  - `showTaskbarClose`
  - `taskbarItemOrder`
- Added manager method:
  - `getTaskbarWindows()`
- `taskbarMode: "auto"` now resolves to:
  - `"minimized-only"` for body-level managers
  - `"always"` for contained managers used as workspace surfaces
- In workspace-style taskbars:
  - all open windows now render taskbar items
  - active window items are highlighted
  - minimized window items remain visible with minimized state styling
  - clicking an open item focuses it
  - clicking a minimized item restores and focuses it
  - overflowing taskbar items now scroll horizontally instead of collapsing the item pills
- Updated contained window demos to use the workspace-style taskbar:
  - `demos/demo.window.manager.html`
  - `demos/demo.workspace.bridge.html`
- Expanded window browser regression coverage to explicitly verify:
  - minimized-only hidden taskbar behavior
  - always-on taskbar behavior
  - taskbar-based focus switching
  - minimized restore from the taskbar
  - maximized windows reserving visible taskbar height

### v0.21.8

- Added `ui.workspace.bridge` as an explicit trusted parent/child bridge for iframe-hosted apps that need parent-owned helper surfaces instead of local iframe overlays.
- Added parent-side host installation via:
  - `installWorkspaceUiBridgeHost(options)`
- Added child-side bridge access via:
  - `getWorkspaceUiBridge(options)`
  - `showWorkspaceActionModal(payload, options)`
- Integrated delegated workspace fallback into shared helpers:
  - `createToastStack(...)` can delegate toast delivery to the parent workspace host when available
  - `uiAlert(...)`, `uiConfirm(...)`, and `uiPrompt(...)` can delegate dialog rendering to the parent workspace host when available
- Kept `createModal(...)` and `createFormModal(...)` local in V1 to avoid widening the bridge before the workspace contract is proven.
- Added dedicated demo and fixture coverage:
  - `demos/demo.workspace.bridge.html`
  - `samples/iframe/workspace-ui-bridge.fixture.html`
- Added dedicated browser regression coverage for:
  - bridge handshake
  - delegated toast rendering in the parent document
  - local fallback when no host is installed
  - `tests/workspace.bridge.regression.html`
  - `tests/workspace.bridge.regression.mjs`

### v0.21.7

- Added `ui.iframe.host` with `createIframeHost(options)` as a narrow shared iframe surface for embedded apps and local helper-owned fixtures.
- Helper-owned iframe host now standardizes:
  - iframe creation and lifecycle
  - loading state
  - deterministic error state for empty or invalid source
  - narrow source controls via `setSrc(...)`, `reload()`, and `update(...)`
- Added dedicated demo coverage:
  - `demos/demo.iframe.host.html`
  - shared navigation entry under `Utilities`
  - home catalog card on `demos/index.html`
- Added dedicated browser regression coverage:
  - `tests/iframe.host.regression.html`
  - `tests/iframe.host.regression.mjs`
- Fixed iframe-host status-surface visibility so helper-owned loading/error panels correctly honor hidden-state toggles instead of visually overriding ready iframe content.
- Updated the dedicated iframe-host demo to use a same-origin fixture file for deterministic browser behavior:
  - `samples/iframe/iframe-host.fixture.html`
- Added full-bleed `ui.window` composition for iframe-hosted content so embedded surfaces can occupy the full window body rather than inheriting generic padded content styling.

### v0.21.6

- Added `ui.icons` as a shared categorized SVG icon registry with:
  - `createIcon(name, options)`
  - `getIconDefinition(name)`
  - `listIcons()`
  - `listIconCategories()`
- Added the initial curated outline icon pack across:
  - `actions`
  - `navigation`
  - `status`
  - `media`
  - `data`
- Added dedicated demo/catalog coverage:
  - `demos/demo.icons.html`
  - shared navigation entry under `Utilities`
  - home catalog card on `demos/index.html`
- Added baseline regression coverage for registry integrity and SVG creation:
  - `tests/icons.regression.html`
  - `tests/icons.regression.mjs`

### v0.21.5

- Added `createAccountFormModal(options)` as a narrow account/profile wrapper over `createFormModal(...)` with helper-owned baseline rows for:
  - `Name`
  - `Email`
- Added `extraRows` support to the new account preset so projects can append instructions, warnings, and additional row content without forking the shared account structure.
- Added `createChangePasswordFormModal(options)` as a narrow password-change wrapper with helper-owned rows for:
  - `Current Password`
  - `New Password`
  - `Confirm Password`
- Added dedicated demo coverage for both new presets:
  - `demos/demo.form.modal.account.html`
  - `demos/demo.form.modal.change.password.html`
- Extended preset browser regression coverage to include:
  - account preset additive `extraRows`
  - account preset `extraActions` / `extraActionsPlacement`
  - change-password preset remapped fields
  - change-password preset successful native-submit close behavior
  - `tests/form.modal.presets.regression.html`
  - `tests/form.modal.presets.regression.mjs`

### v0.21.4

- Added additive `extraActions` support to `createFormModal(...)` so teams can extend the helper-owned footer without replacing the standard cancel/submit contract.
- Footer ordering is now:
  - extra actions in provided order
  - helper-owned `Cancel`
  - helper-owned `Submit`
- Added `extraActionsPlacement` to `createFormModal(...)` with:
  - `"end"` for the existing shared end cluster
  - `"start"` to visually split extra actions to the start side of the footer
- Extra footer actions now receive current form values plus helper-owned context in `onClick(values, ctx)` and default to non-closing behavior unless `closeOnClick: true` is explicitly provided.
- Added an HQ-style account footer-actions acceptance example to:
  - `demos/demo.form.modal.html`
- Added browser regression coverage for:
  - extra-action render order
  - callback execution
  - default non-closing behavior
  - busy-state disable behavior
  - `tests/form.modal.regression.html`
  - `tests/form.modal.regression.mjs`

### v0.21.3

- Added `ui.password` with `createPasswordField(container, options)` as a narrow reusable password-entry primitive:
  - shared show/hide toggle
  - standalone value/visibility API
  - disabled/readonly support
  - login/re-auth-aligned behavior
- Updated `ui.form.modal` password rows to compose over `ui.password` so login and re-auth now inherit the same password toggle behavior as standalone usage.
- Added dedicated demo coverage:
  - `demos/demo.password.html`
  - shared navigation entry under `Utilities`
  - home catalog card on `demos/index.html`
- Added dedicated browser regression coverage:
  - `tests/password.regression.html`
  - `tests/password.regression.mjs`
- Updated public documentation and integration guidance in:
  - `README.md`
  - `docs/pbb-refactor-playbook.md`

### v0.21.2

- Hardened `ui.media.viewer` against several real integration and demo-surface issues:
  - fixed the single-item no-navigation layout bug where one image/video could render near-empty while repeated items worked
  - fixed tall-media fit behavior so `contain` and `cover` now diverge correctly on portrait/tall assets
  - fixed pan geometry so the viewer moves the rendered content box rather than a viewport-sized wrapper
  - fixed drag vibration by disabling transform easing during active pan
  - fixed wheel-zoom feel so zoom now stays anchored to the viewport center instead of appearing to drift/pan unpredictably
- Added a dedicated portrait/tall repro asset and focused repro actions in `demos/demo.media.viewer.html`
- Added dedicated browser regression coverage:
  - `tests/media.viewer.regression.html`
  - `tests/media.viewer.regression.mjs`

### v0.21.1

- Reworked `demos/demo.window.manager.html` so the page now proves `ui.window` through actual wrapper-style workspaces instead of a generic pane-only shell:
  - incident review workspace over `ui.window` + `ui.data.inspector`
  - media review workspace over `ui.window` + `ui.media.viewer` + `ui.data.inspector`
- Added the next bounded follow-on proposal for the subsystem:
  - `docs/ui-window-taskbar-improvement-proposal.md`
- Updated `docs/ui-window-proposal.md` to point future taskbar expansion at the dedicated follow-on proposal before any docking or snapping discussion.

### v0.21.0

- Added `ui.window` with `createWindowManager(options)` and managed `createWindow(options)` instances for desktop-style non-modal tools:
  - drag by title bar
  - edge/corner resize
  - active-window stacking
  - minimize/maximize/restore
  - manager-owned taskbar recovery
- Added dedicated documentation for the window subsystem:
  - `docs/ui-window-proposal.md`
  - `docs/ui-window-v1-spec.md`
  - `docs/ui-window-v1-checklist.md`
- Added dedicated demo coverage:
  - `demos/demo.window.html`
  - `demos/demo.window.manager.html`
  - shared navigation entry under `Utilities`
  - demo-catalog card on `demos/index.html`
- Added targeted browser regression coverage:
  - `tests/window.regression.html`
  - `tests/window.regression.mjs`

### v0.20.7

- Extended `ui.form.modal` for real acceptance-proof coverage of the approved improvement stage:
  - added narrow top-level `context` support for geodata-driven hub flows
  - exposed instance-level `applyApiErrors(response)`
  - mapped dotted backend keys such as `uplink_hub_ids.0` back onto the base field when possible
- Hardened `ui.form.modal` hidden-field layout behavior:
  - hidden-only rows no longer render empty visible grid rows
  - hidden fields now render in a dedicated non-visual form container while still participating in payload values
- Fixed `ui.select` overflow behavior in modals and other clipped containers:
  - menus now render in a floating body-level layer instead of inside the local overflow container
  - floating menu positioning now follows the trigger during scroll and resize
- Updated `demos/demo.form.modal.html` so the proof-oriented examples align to the real PBB HQ targets:
  - geodata `Hub Add`
  - `other` deployment `Hub Edit`
  - `Add Uplink`
- Expanded `tests/form.modal.regression.html` to cover:
  - narrow context-strip rendering
  - dotted backend error mapping to hosted `ui.select`
  - hidden-only row collapse behavior
  - body-level floating `ui.select` menus

### v0.20.6

- Split modal-form regression coverage into two targeted browser harnesses:
  - `tests/form.modal.regression.html` / `tests/form.modal.regression.mjs` for the base `createFormModal(...)` helper
  - `tests/form.modal.presets.regression.html` / `tests/form.modal.presets.regression.mjs` for preset-wrapper behavior
- Added `node tests/form.modal.presets.regression.mjs` to the documented validation commands
- This separation makes preset-wrapper failures easier to debug without mixing them with base helper validation/render regressions

### v0.20.5

- Expanded `tests/form.modal.regression.html` to cover the preset-wrapper layer:
  - `createLoginFormModal(...)`
  - `createReauthFormModal(...)`
  - `createStatusUpdateFormModal(...)`
  - `createReasonFormModal(...)`
- Added regression coverage for:
  - remapped field names
  - locked re-auth identifier behavior
  - app-supplied status/reason option flows
  - confirmation-phrase enforcement in the reason-required preset
- Improved `demo.form.modal.html` with compact payload previews so engineers can see the remapped output shape before opening each preset

### v0.20.4

- Expanded `js/ui/ui.form.modal.presets.js` with:
  - `createStatusUpdateFormModal(options)`
  - `createReasonFormModal(options)`
- Added loader keys:
  - `ui.form.modal.status`
  - `ui.form.modal.reason`
- Preset wrappers accept engineer-provided field-name mappings plus app-supplied option lists for:
  - status values
  - reason categories
- Expanded `demo.form.modal.html` with focused preset coverage for:
  - status update form
  - categorized reason-required form

### v0.20.3

- Added `js/ui/ui.form.modal.presets.js` with:
  - `createLoginFormModal(options)`
  - `createReauthFormModal(options)`
- Added loader keys:
  - `ui.form.modal.login`
  - `ui.form.modal.reauth`
- Preset wrappers keep helper-owned structure while allowing engineer-provided field-name mappings for cross-project integration
- Expanded `demo.form.modal.html` with focused preset coverage for:
  - login wrapper with remapped field names
  - re-auth wrapper with locked identifier

### v0.20.2

- Migrated the former hand-built busy modal form example in `demo.ui.html` to `createFormModal(...)`
- Added `tests/form.modal.regression.html` and `tests/form.modal.regression.mjs` covering:
  - helper required validation
  - app-supplied field errors
  - form-level error rendering
  - truthy submit close-on-success behavior
- Added `node tests/form.modal.regression.mjs` to the documented validation commands

### v0.20.1

- Added `demo.form.modal.html` as a focused demo page for `createFormModal(...)`
- Added `Form Modal` to the shared demo navigation and `index.html`
- Migrated the legacy login modal example in `demo.ui.html` to use `createFormModal(...)` instead of a hand-built modal implementation

### v0.20.0

- Added `ui.form.modal` with `createFormModal(options)` as a schema-driven helper for short modal-bound forms built on `createActionModal(...)`
- Shipped the V1 row-based form contract:
  - one item in a row => full width
  - two items in a row => equal-width columns
  - more than two items => rejected or normalized conservatively
- Added helper-owned modal-form APIs for:
  - `getValues()`
  - `setValues(values)`
  - `setErrors(fieldErrors)`
  - `clearErrors()`
  - `setFormError(message)`
  - `clearFormError()`
- Reused helper-owned modal busy-state behavior for form submit flows instead of creating a second busy-overlay system
- Expanded `demo.ui.html` with a dedicated schema-form modal section covering:
  - login field-error flow
  - form-level error flow
  - successful async submit flow
  - two-column row layout

### v0.19.7

- Added `js/ui/ui.semantic.icons.js` so `ui.dialog` and `ui.toast` share one semantic status icon source instead of carrying duplicate inline SVG maps
- Expanded the `demo.ui.html` toast section with a comparison matrix and explicit examples for:
  - default semantic icons
  - `showVariantIcon: false`
  - `variantIcon` overrides

### v0.19.6

- Added default semantic status icons to `ui.toast` so `success`, `info`, `warn`, `error`, and `neutral` toasts share the same status language as `ui.dialog`
- Added toast icon control options:
  - `showVariantIcon`
  - `variantIcon`
- Added opt-in speech support to `ui.dialog` helpers:
  - `speak`
  - `speakText`
  - `voiceName`
  - `speakRate`
  - `speakPitch`
  - `speakVolume`
- Updated `demo.ui.html` to expose dialog voice selection and speech toggling alongside the existing toast speech controls

### v0.19.5

- Improved `demo.ui.html` dialog coverage with a compact comparison matrix so users can see the intended feature combination before opening each dialog:
  - variant
  - built-in status icon visibility
  - description support
  - default primary action emphasis

### v0.19.4

- Expanded `demo.ui.html` dialog coverage to show the current `ui.dialog` feature set more explicitly:
  - `success`
  - `info`
  - `warning`
  - `error`
  - `description`
  - built-in semantic status icons
  - `showVariantIcon: false` opt-out behavior

### v0.19.3

- Added `description` to `ui.dialog` helpers so alert/confirm/prompt flows can show a secondary guidance line below the primary message without forcing custom modal content
- Added shared description styling in `css/ui/ui.dialog.css`
- Updated `demo.ui.html` dialog examples to demonstrate title + message + description composition

### v0.19.2

- Added default semantic status icons to `ui.dialog` for non-`default` variants:
  - `success`
  - `info`
  - `warning`
  - `error`
- Added dialog icon control options:
  - `showVariantIcon`
  - `variantIcon`
- Kept the icon behavior at the dialog layer so `ui.modal` remains the neutral shell while `ui.dialog` owns semantic presentation

### v0.19.1

- Added semantic dialog variants to `ui.dialog`:
  - `default`
  - `success`
  - `info`
  - `warning`
  - `error`
- `uiAlert(...)`, `uiConfirm(...)`, and `uiPrompt(...)` now accept `variant` and apply dialog-level accent styling through `css/ui/ui.dialog.css`
- `uiConfirm(...)` and `uiPrompt(...)` now use safer default action emphasis for `warning` and `error` dialogs by promoting their primary action to the shared `danger` button variant unless the caller overrides it explicitly
- Updated `demo.ui.html` dialog examples to exercise semantic dialog variants directly:
  - alert => `info`
  - confirm => `warning`
  - prompt => `success`

### v0.19.0

- Added `ui.hierarchy.map` as a hierarchy-first visual explorer with:
  - primary tree rendering
  - external entity lane
  - overlay relationship links
  - search
  - zoom/pan
  - selection
  - chrome-less support
- Added `demo.hierarchy.map.html` wired to a real Cebu hierarchy sample and synthetic foundation support overlays
- Added `scripts/generate.hierarchy.sample.ps1` to regenerate the demo hierarchy from local MySQL data in `pbb_hq_ph`
- Added `samples/samplehierarchy_cebu.json` containing:
  - Philippines
  - Region VII
  - Cebu
  - Cebu City
  - Mandaue City
  - Lapu-Lapu City (Opon)
  - all actual barangays under those cities
  - five synthetic foundations and overlay support links for stress testing

### v0.18.16

- Added a browser-rendered modal busy-state regression harness:
  - `tests/modal.busy.regression.html`
  - `tests/modal.busy.regression.mjs`
- The harness protects the shared `ui.modal` contract against regressions where `setBusy(...)` could visually dismiss an open modal by dropping runtime state classes during `update(...)`
- Documented `node tests/modal.busy.regression.mjs` as part of the supported repo validation commands

### v0.18.14

- Added `.ui-form-error` to `ui.components.css` as the shared inline form/auth error primitive
- Updated the login-failure modal flow in `demo.ui.html` to use `.ui-form-error` instead of inline error styling
- Documented form feedback primitives in `README.md` so async modal/login flows can reuse shared error presentation instead of app-local styles

### v0.18.13

- Added modal-level busy-state support to `createModal(...)`:
  - `busy`
  - `busyMessage`
  - `closeWhileBusy`
  - `backdropCloseWhileBusy`
  - `escapeCloseWhileBusy`
  - `setBusy(isBusy, { message? })`
  - `isBusy()`
- Busy state now applies a helper-owned overlay, sets `aria-busy`, disables interactive controls, and prevents duplicate modal interaction while active
- Added helper-managed `autoBusy` support to `createActionModal(...)` for promise-returning action handlers
- Updated `demo.ui.html` to show:
  - explicit busy-state control via `setBusy(...)`
  - automatic busy handling for async action-modal save flows
- Expanded the modal documentation in `README.md` so busy-state handling is part of the public shared contract

### v0.18.12

- Updated `demo.grid.html` to use the same shared dense action-cell pattern as `demo.tree.grid.html`
- Standardized flat-grid demo actions on:
  - `ui-button`
  - `ui-button-icon`
  - `ui-button-borderless`
  - `ui-button-danger`
- This keeps `ui.grid` and `ui.tree.grid` aligned on the recommended cell-action implementation instead of letting each demo invent a different button/icon pattern

### v0.18.11

- Formalized the `ui.toggle.button` / `ui.toggle.group` contract in `README.md`
- Documented the actual callback payloads emitted by the current implementation instead of abstract proposal signatures:
  - button `onChange({ id, pressed, button, event })`
  - group `onChange({ items, changedItem, changedIndex, group, value })`
- Expanded the public toggle documentation to cover:
  - variants
  - tones
  - sizing
  - group modes
  - returned instance APIs
  - accessibility rules
- Treated the README toggle section as the formal contract surface for consuming apps

### v0.18.10

- Added shared dense-cell action primitives in `ui.components.css`:
  - `.ui-cell-actions`
  - `.ui-cell-action`
- Updated `demo.tree.grid.html` to demonstrate the recommended action-cell pattern using:
  - `ui-button`
  - `ui-button-icon`
  - `ui-button-borderless`
  - `ui-button-danger`
- Documented tree-grid/list/grid cell actions as shared styling contracts instead of leaving consuming apps to create raw unstyled buttons inside cells

### v0.18.9

- Added shared button-style variants in `ui.components.css`:
  - `.ui-button-borderless`
  - `.ui-button-quiet`
  - `.ui-button-link`
  - `.ui-button-icon`
- Normalized `.ui-button` to `inline-flex` so text/icon layouts stay consistent across shared UI components
- Updated `demo.ui.html` with a visible button-variants section so engineers can inspect the styling contract directly
- Updated `README.md` to document the expanded shared button-style surface

### v0.18.8

- Added a browser-rendered regression harness for `ui.tree.grid`:
  - `tests/tree.grid.regression.html`
  - `tests/tree.grid.regression.mjs`
- The harness validates:
  - initial render
  - tree-aware search
  - ancestor-path preservation
  - empty-search state
  - `clearSearch()` recovery
  - multi-match highlighting
- Documented `node tests/tree.grid.regression.mjs` as part of the repo validation commands

### v0.18.7

- Added built-in tree-aware search to `ui.tree.grid`:
  - `searchTerm`
  - `searchFields`
  - `autoExpandMatches`
  - `highlightMatches`
  - `emptySearchText`
  - `setSearchTerm(term)`
  - `clearSearch()`
- `ui.tree.grid.getState()` now includes `search: { active, term, matchCount, visibleCount }`
- Search keeps ancestor paths visible for descendant matches and feeds virtualization from the filtered visible-row list
- Match highlighting now marks all occurrences within rendered tree-grid cell text, not just the first occurrence
- Updated `demo.tree.grid.html` with live search controls for the standard and virtualized tree-grid demos

### v0.18.6

- Extended `ui.dialog` convenience helpers to preserve the same declarative modal-action icon contract:
  - `uiAlert(...)` now accepts `headerActions` plus `okIcon*` options
  - `uiConfirm(...)` now accepts `headerActions` plus `cancelIcon*` / `confirmIcon*` options
  - `uiPrompt(...)` now accepts `headerActions` plus `cancelIcon*` / `submitIcon*` options
- Updated `demo.ui.html` dialog examples to exercise header actions and icon-capable buttons for alert, confirm, and prompt flows

### v0.18.5

- Added action-button icon support for `createActionModal(...)` header and footer actions:
  - `icon`
  - `iconPosition`
  - `iconOnly`
  - `ariaLabel`
- Updated `demo.ui.html` action-modal example to show icon and icon-only actions

### v0.18.4

- Added declarative `headerActions[]` support to `createActionModal(...)`
- Added `setHeaderActions(actions[])` to the action-modal helper
- Header and footer action objects now use the same contract in `createActionModal(...)`
- Updated `demo.ui.html` action-modal example to show declarative header actions

### v0.18.3

- Added `headerActions` slot support to `createModal(...)`
- Added `setHeaderActions(...)` modal instance method
- Updated `demo.ui.html` modal example to show header actions in use

### v0.18.2

- Expanded `ui.media.strip` viewer pass-through options:
  - `viewerAriaLabel`
  - `showViewerHeader`
  - `showViewerCounter`
  - `showViewerClose`
  - `showViewerPrevNext`
  - `showViewerToolbar`
- Updated `demo.ui.html` media-strip section to exercise shared viewer options directly from the strip launcher

### v0.18.1

- Refactored `ui.media.strip` to delegate full-view behavior to `ui.media.viewer`
- `ui.media.strip` now loads `ui.media.viewer` through the loader dependency graph
- Preserved strip launcher APIs:
  - `openByIndex(index)`
  - `openById(id)`
  - `update(nextItems, nextOptions?)`
  - `getState()`
- Added strip-side pass-through options for shared viewer behavior:
  - `viewerFit`
  - `showViewerFooter`
  - `showViewerAudiograph`

### v0.18.0

- Added `ui.media.viewer`:
  - standalone image/video modal viewer
  - fixed-size dialog shell
  - transform-based zoom/pan
  - fit modes (`contain`, `cover`, `original`)
  - gallery navigation (`prev`, `next`, `setIndex`)
  - optional video audiograph via `ui.audio.audiograph`
- Added `css/ui/ui.media.viewer.css`
- Added `demo.media.viewer.html`
- Added loader registry/group support:
  - `ui.media.viewer`
  - included in `media` group
- Added `ui.media.viewer` API and usage documentation
- Added dedicated demo link from `index.html`

### v0.17.8

- Completed the focused demo audit for timeline interactions
- `css/ui/ui.timeline.css`
  - added visible keyboard focus treatment for timeline items
- `css/ui/ui.timeline.scrubber.css`
  - added visible keyboard focus treatment for the scrubber rail, thumb, handles, and zoom controls
- `demo.timeline.html`
  - added explicit keyboard guidance for timeline/scrubber interaction
  - event log is now keyboard-focusable and labeled

### v0.17.7

- Continued the accessibility pass on timeline-focused utilities
- `ui.timeline`
  - added `ariaLabel`
  - timeline roots now expose region semantics
  - timeline lists expose list semantics
  - clickable timeline items are now keyboard-activatable with `Enter` / `Space`
- `ui.timeline.scrubber`
  - added `ariaLabel`, `valueLabel`, `rangeStartLabel`, and `rangeEndLabel`
  - scrubber root now exposes region semantics
  - main seek rail now exposes accessible value text
  - range handles now expose slider semantics with keyboard adjustment support
- `demo.timeline.html`
  - now passes explicit accessibility labels to both timeline instances and the scrubber demo

### v0.17.6

- Continued the accessibility pass on heavy interactive components
- `ui.file.uploader`
  - added `ariaLabel` and `dropzoneAriaLabel`
  - uploader root now exposes region semantics
  - dropzone is keyboard-activatable and behaves as an explicit button target
  - upload queue exposes list semantics and a polite live status channel
- `ui.audio.player`
  - added `ariaLabel` and `seekLabel`
  - play/pause button now exposes `aria-pressed`
  - seek slider now exposes `aria-valuetext`
- `ui.audio.audiograph`
  - root now exposes labeled region semantics
  - mute button now exposes `aria-pressed`
- `ui.audio.callSession`
  - added `ariaLabel`
  - session root and track list now expose region/list semantics
- `demo.audio.html`
  - now passes an explicit `ariaLabel` to the audio session demo

### v0.17.5

- Audited `ui.kanban` drag behavior and accessibility
- `ui.kanban`
  - added `ariaLabel` support on the board root
  - lane roots are now labeled regions and card stacks expose list semantics
  - cards can now trigger click handlers via `Enter` / `Space`
  - drag-and-drop now treats the entire lane as a valid drop surface
  - insertion index is resolved from pointer position, making cross-lane and between-card drops less fragile
- `demo.ui.html`
  - now passes an explicit `ariaLabel` to the kanban demo

### v0.17.4

- Performed a focused accessibility audit on demo pages that exercise the updated primitives
- `demo.ui.html`
  - now passes explicit `ariaLabel` values to select, datepicker, and command-palette demos
- `demo.nav.html`
  - now passes explicit `ariaLabel` values to navbar, sidebar, and breadcrumbs demos
  - added an accessible label for the breadcrumb-input control

### v0.17.3

- Continued the accessibility hardening pass on navigation and tab primitives
- `ui.tabs`
  - now wires tabs and tabpanel together with `id`, `aria-controls`, and `aria-labelledby`
- `ui.navbar`
  - added `ariaLabel` support for the navigation landmark
  - active navigation items now expose `aria-current="page"`
- `ui.sidebar`
  - added `ariaLabel` support for the navigation landmark
  - active items now expose `aria-current="page"`
- `ui.breadcrumbs`
  - current crumb now exposes `aria-current="page"`

### v0.17.2

- Continued the accessibility hardening pass on selection/launcher/date primitives
- `ui.select`
  - added `ariaLabel` support
  - synchronizes trigger/listbox wiring with `aria-controls`
  - exposes active option state via `aria-activedescendant`
- `ui.command.palette`
  - added `ariaLabel` support
  - added `Home` / `End` keyboard navigation
  - restores focus to the previously focused element on close
- `ui.datepicker`
  - added `ariaLabel` support
  - synchronizes trigger/panel wiring with `aria-controls`
  - restores focus after outside-click or `Escape` close

### v0.17.1

- Continued the accessibility hardening pass on interactive primitives
- `ui.modal`
  - added `ariaLabel` fallback when no visible title is present
  - binds `aria-labelledby` to the rendered modal title when available
- `ui.drawer`
  - now exposes dialog semantics (`role="dialog"`, `aria-modal="true"`)
  - restores focus to the previously focused element on close
  - supports `Escape`-to-close consistently
- `ui.menu`
  - now synchronizes trigger accessibility state (`aria-haspopup`, `aria-expanded`, `aria-controls`)
  - added `ariaLabel` support for the menu surface
  - added keyboard `Home` / `End` navigation
  - restores focus to the trigger after close

### v0.17.0

- Started the dedicated accessibility hardening line with low-risk ARIA baseline improvements on wrapper/data primitives
- Added `ariaLabel` support to:
  - `ui.virtual.list`
  - `ui.scheduler`
  - `ui.empty.state`
  - `ui.data.inspector`
- Added explicit landmark/list semantics where applicable:
  - `ui.virtual.list` now exposes labeled list/listitem semantics
  - `ui.scheduler`, `ui.empty.state`, and `ui.data.inspector` now expose labeled region semantics

### v0.16.12

- Added a public `Chrome-less Components` support matrix to the README
- Documented the rule that `chrome: false` is only exposed by components with a real library-managed outer shell

### v0.16.11

- Expanded `demo.scheduler.html` with side-by-side scheduler rendering:
  - framed scheduler
  - chrome-less scheduler inside a host-owned shell

### v0.16.10

- Added `chrome: false` support to `ui.scheduler`
- Clarified the shared contract: only components with a real library-owned outer shell should expose `chrome: false`; components without distinct wrapper chrome should not add no-op chrome flags

### v0.16.9

- Added `chrome: false` support to `ui.empty.state`
- Expanded `demo.empty.state.html` with:
  - framed empty-state rendering
  - chrome-less empty-state rendering inside host-owned layout

### v0.16.8

- Added `chrome: false` support to additional wrapper-style data components:
  - `ui.virtual.list`
  - `ui.data.inspector`
- Kept `ui.timeline` unchanged because it does not currently own a distinct outer chrome shell; no-op chrome flags are avoided unless the component has actual wrapper styling to disable

### v0.16.7

- Fixed `ui.tree.grid` lazy hierarchy rendering so rows with `hasChildren: true` are treated as expandable before their children are loaded
- Added loading/error visual state to tree-grid disclosure controls during async child loading
- Expanded `demo.tree.grid.html` with:
  - a lazy-loaded tree-grid section
  - `chrome: false` rendering example
  - explicit `refreshChildren(...)` and `setExpanded(...)` exercise paths

### v0.16.6

- Added `chrome: false` presentation support for wrapper-style data components:
  - `ui.grid`
  - `ui.tree`
  - `ui.tree.grid`
- Added explicit lazy child loading APIs:
  - `ui.tree.loadChildren(nodeId)`
  - `ui.tree.refreshChildren(nodeId)`
  - `ui.tree.grid.loadChildren(rowId)`
  - `ui.tree.grid.refreshChildren(rowId)`
- Added lazy child loading support to `ui.tree.grid` using the same `lazyLoadChildren(row, state)` / `onLoadChildren(...)` pattern used by `ui.tree`

### v0.16.5

- Added optional fixed-row-height virtualization to `ui.tree.grid`
  - virtualization operates on the flattened visible rows after expand/collapse state is applied
  - intended for large hierarchical datasets with stable row heights
- Updated `demo.tree.grid.html` with a large virtualized tree-grid section

### v0.16.4

- Added hierarchical tree grid component:
  - `js/ui/ui.tree.grid.js`
  - `css/ui/ui.tree.grid.css`
- Added loader registry entry:
  - `ui.tree.grid`
- Added `ui.tree.grid` to the `data` loader group
- Added dedicated demo page:
  - `demo.tree.grid.html`
- Linked tree grid demo from `index.html`

### v0.16.3

- Added toggle primitives:
  - `js/ui/ui.toggle.button.js`
  - `js/ui/ui.toggle.group.js`
  - `css/ui/ui.toggle.css`
- Added loader registry entries:
  - `ui.toggle.button`
  - `ui.toggle.group`
- Added toggle components to the `forms` loader group
- Updated `demo.ui.html`:
  - standalone toggle button demo
  - multi toggle group demo
  - single-select segmented toggle group demo

### v0.16.2

- Strengthened `uiLoader` contract:
  - export-aware registry entries via `export`
  - `uiLoader.get(name)` for resolved exports
  - `uiLoader.create(name, ...args)` for factory-style creation by registry key
  - grouped loading via `loadGroup(name)` and `loadManyGroup(names)`
  - loader diagnostics via `getFailedCss()`, `getFailedModules()`, `getDiagnostics()`, `setDebug(...)`
- Added alias registry keys for common utility exports:
  - `ui.action.modal`
  - `ui.dialog.alert`
  - `ui.dialog.confirm`
  - `ui.dialog.prompt`
  - `ui.dom.createElement`
  - `ui.dom.clearNode`
  - `ui.events.createEventBag`
- Expanded shared primitive CSS in `ui.components.css`:
  - `ui-surface`, `ui-panel`
  - `ui-field`, `ui-label`
  - `ui-badge`, `ui-eyebrow`
  - `ui-shell-header`, `ui-shell-search`
  - normalized button variants (`ui-button-primary`, `ui-button-ghost`, `ui-button-danger`)
- Added registry contract test:
  - `node tests/registry.contract.mjs`
- Tightened integration guidance in README and playbook:
  - app integrations should use `uiLoader` by registry key
  - direct path imports are treated as internal-library usage

### v0.16.1

- Converted all `demo*.html` pages to use `uiLoader` instead of manual component stylesheet tags and direct component module imports
- Standardized demo boot flow:
  - head bootstrap uses `window.__demoLoaderReady = uiLoader.loadMany([...])`
  - page scripts await loader readiness and import modules via `uiLoader.import(...)`
- Demos now serve as the canonical reference for loader-based integration across the library

### v0.16.0

- Added registry-based component loader:
  - `js/ui/ui.loader.js`
  - `uiLoader.load(name)`
  - `uiLoader.loadMany(names)`
  - `uiLoader.import(name)`
- Added explicit loader registry coverage for:
  - `ui.*` browser utilities
  - `incident.*` helper modules
- Added deduplicated stylesheet injection so engineers can load CSS and JS through one API instead of manual asset wiring
- Updated README and playbook to document `uiLoader` as the preferred shared loading path for project integrations

### v0.15.2

- Added file-uploader chunk/resume hooks (roadmap v0.15 progress):
  - `useChunkUpload`, `chunkSize`
  - `onGetResumeState`, `onCreateUploadSession`
  - `onUploadChunk`, `onPersistResumeState`
  - `onFinalizeUpload`, `onClearResumeState`
- Updated `demo.ui.html` uploader demo to exercise chunk upload + localStorage resume-state flow

### v0.15.1

- Added scheduler/calendar primitive (roadmap v0.15 progress):
  - `ui.scheduler.js`
  - `ui.scheduler.css`
  - month/week views with event and slot callbacks
- Added dedicated `demo.scheduler.html`
- Linked new demo from `index.html`

### v0.15.0

- Added virtual list primitive (roadmap v0.15 progress):
  - `ui.virtual.list.js`
  - `ui.virtual.list.css`
  - windowed rendering with configurable `height`, `rowHeight`, and `overscan`
  - API: `update`, `setItems`, `scrollToIndex`, `getState`, `destroy`
- Added dedicated `demo.virtual.list.html`
- Linked new demo from `index.html`

### v0.14.2

- Expanded tree capabilities (roadmap v0.14 progress):
  - async/lazy child loading:
    - `lazyLoadChildren(node, state)`
    - `onLoadChildren(node, children, state)`
  - optional virtualization for large trees:
    - `enableVirtualization`
    - `virtualHeight`
    - `virtualRowHeight`
    - `virtualOverscan`
  - `getState().visibleRows` added for flattened visible tree snapshot
- Updated `demo.ui.html` tree section to demonstrate:
  - lazy loading on demand
  - large-node virtualization behavior

### v0.14.1

- Expanded command-palette capabilities (roadmap v0.14 progress):
  - async command providers (`providers[]`)
  - grouped sections rendering
  - pinned/recent command groups
  - history persistence (`historyStorageKey`)
  - history change hook (`onHistoryChange`)
- Updated `demo.ui.html` command palette demo to exercise provider + pinned/recent behavior

### v0.14.0

- Added workflow/layout/data primitives:
  - `ui.stepper.js` + `ui.stepper.css`
  - `ui.splitter.js` + `ui.splitter.css`
  - `ui.data.inspector.js` + `ui.data.inspector.css`
  - `ui.empty.state.js` + `ui.empty.state.css`
  - `ui.skeleton.js` + `ui.skeleton.css`
- Added dedicated demo pages:
  - `demo.stepper.html`
  - `demo.splitter.html`
  - `demo.inspector.html`
  - `demo.empty.state.html`
  - `demo.skeleton.html`
- Kept `demo.ui.html` focused on general UI playground utilities

### v0.13.3

- Hardened timeline scrubber interactions (roadmap v0.13 progress):
  - improved pointer interaction stability (`preventDefault` + propagation control on drag gestures)
  - keyboard seek controls on scrubber rail:
    - `ArrowLeft/ArrowRight`, `Home/End`, `PageUp/PageDown`
    - configurable `seekStepMs` and `seekStepMsFast` (Shift)
  - wheel-to-horizontal-scroll handling in zoomed scrubber viewport
  - `preventPageScrollOnInteract` option (default `true`)

### v0.13.2

- Expanded kanban capabilities (roadmap v0.13 progress):
  - intra-lane reorder support (drag/drop and programmatic `toIndex`)
  - lane WIP limits via `wipLimits`
  - move validation hook via `validateMove(...)`
  - rejected-move event via `onMoveRejected(...)`
  - keyboard-accessible card moves (arrow keys)
- Added kanban utility:
  - `ui.kanban.js`
  - `ui.kanban.css`
  - drag-drop lane card movement with callbacks
- Updated `demo.ui.html`:
  - new sections for command palette, tree, and kanban

### v0.13.1

- Improved timeline behavior (roadmap v0.13 progress):
  - added component-level linked range filtering in `ui.timeline`:
    - `options.linkedRange = { startMs, endMs, anchorMs? }`
    - `setLinkedRange(range|null)`
    - `getState().visibleItems`
  - updated `demo.timeline.html` to use timeline API linked-range filtering (not demo-only external filtering)

### v0.13.0

- Added file uploader utility:
  - `ui.file.uploader.js`
  - `ui.file.uploader.css`
  - drag/drop queue, validation, progress, retry/cancel/remove, upload adapter hook
- Improved timeline demo integration:
  - scrubber range now filters visible timeline items
  - seek highlight operates on filtered result set
- Polished UX:
  - fixed-height, scrollable event logs across demo pages
  - global themed scrollbar styling in `ui.tokens.css`

### v0.12.0

- Added command palette utility:
  - `ui.command.palette.js`
  - `ui.command.palette.css`
  - shortcut-driven quick actions (`Ctrl/Cmd + K`)
- Added tree utility:
  - `ui.tree.js`
  - `ui.tree.css`
  - expandable/selectable/checkable hierarchy

### v0.11.0

- Added timeline scrubber utility:
  - `ui.timeline.scrubber.js`
  - `ui.timeline.scrubber.css`
  - seek/playhead, optional range handles, zoom levels
- Added dedicated timeline demo:
  - `demo.timeline.html`
  - scrubber-driven active item highlighting + horizontal auto-scroll
- Updated `demo.ui.html`:
  - timeline/scrubber moved out to dedicated page for clearer utility coverage

### v0.10.0

- Added timeline utility:
  - `ui.timeline.js`
  - `ui.timeline.css`
  - supports `vertical` and `horizontal` orientations
  - supports optional vertical date grouping and item/action callbacks
- Updated `demo.ui.html`:
  - new Timeline section with vertical + horizontal examples

### v0.9.0

- Added datepicker utility:
  - `ui.datepicker.js`
  - `ui.datepicker.css`
  - supports `single` and `range` modes with optional time inputs
- Added action modal wrapper:
  - `createActionModal(...)` in `ui.modal.js` for declarative footer actions
- Refactored dialog helpers to use action modal internals:
  - `uiAlert`, `uiConfirm`, `uiPrompt`
- Updated `demo.ui.html`:
  - new Datepicker section (single + range/time examples)
- Enhanced toast speech controls:
  - optional delayed dismiss until speech ends (`waitForSpeechBeforeDismiss`)
  - voice selection support (`voiceName`, `getVoices()`)

### v0.8.0

- Added toast notifications utility:
  - `ui.toast.js`
  - `ui.toast.css`
- Added select component utility:
  - `ui.select.js`
  - `ui.select.css`
- Updated `demo.ui.html`:
  - new Toast section (info/success/warn/error actions)
  - new Select section (single + multi searchable select)

### v0.7.0

- Added general-purpose modal foundation:
  - `ui.modal.js`
  - `ui.modal.css`
  - reusable modal API with focus trap, escape/backdrop close, sizing, lifecycle hooks
- Refactored dialog helpers to use modal foundation:
  - `uiAlert`
  - `uiConfirm`
  - `uiPrompt`
- Added progress UI library:
  - `ui.progress.js`
  - `ui.progress.css`
  - styles: `linear`, `striped`, `gradient`, `segmented`, `steps`, `radial`, `ring`, `indeterminate`
- Added `demo.progress.html` and linked it from `index.html`

### v0.6.0

- Improved grid capabilities:
  - added optional row virtualization:
    - `enableVirtualization`
    - `virtualRowHeight`
    - `virtualOverscan`
    - `virtualThreshold`
  - large-list rendering stability fixes (virtual windowing + row-event cleanup)
  - retained column-resize support in virtualized mode
- Restructured demos:
  - added dedicated `demo.grid.html` with:
    - local grid
    - remote grid
    - large virtualized fixed-height grid
  - removed grid section from `demo.ui.html`

### v0.5.0

- Expanded navigation/menu capabilities:
  - unified icon contract across nav components (`icon`, `iconPosition`, `iconOnly`)
  - breadcrumb built-in state helpers (`setItems`, `addCrumb`, `getItems`, `reset`)
  - navbar action menus (`menuItems`, `menuOptions`) with callbacks
  - menu alignment support (`align: left|right`) and placement refinements
- Improved navigation UX:
  - animated sidebar collapse/expand
  - animated dropdown/dropup show/hide with deferred unmount
  - sidebar collapsed icon-only item rendering
- Updated `demo.nav.html`:
  - sidebar-responsive layout collapse behavior
  - breadcrumb add/reset/truncate wiring via library API

### v0.4.0

- Added navigation/menu utility layer:
  - `ui.menu`, `ui.dropdown`, `ui.dropup`
  - `ui.navbar`, `ui.sidebar`, `ui.breadcrumbs`
- Added `ui.nav.css` styles
- Added `demo.nav.html` for interactive navigation demos

### v0.3.0

- Added `ui.grid` component with local/remote modes
- Added optional sorting/search/pagination capabilities (especially for remote data)
- Added row selection (`single`/`multi`) and query/state APIs
- Added grid demo section in `demo.ui.html`

### v0.2.0

- Added audio UI utility layer:
  - `ui.audio.player`
  - `ui.audio.audiograph` (standalone)
  - `ui.audio.callSession`
- Added `demo.audio.html` with sample selector + live style/sensitivity controls
- Added advanced audiograph styles:
  - `neon`, `particle`, `shockwave`, `tsunami`, `plasma`, `burst`, `heartbeat`
- Added silence-gate + attack/release + freeze-on-pause visualization behavior
- Added overlay-header mode for audiographs to maximize graph area

### v0.1.0

- Initial public prototype published
- Incident helper set (`teams.assignments`, `types`, details editor/viewer)
- Shared UI utility layer (`ui.dom`, `ui.events`, `ui.drawer`, `ui.search`, `ui.dialog`, `ui.tabs`, `ui.strips`)
- Demo pages published via GitHub Pages



