# MapServer Homepage Implementation Checklist

**Date:** 2026-03-25  
**Reference:** `docs/homepage-improvements-proposal.md`  
**UI Framework:** PBB Helpers (vendored in `vendor/helpers.pbb.ph/`)  
**Target Completion:** Phase-based rollout  
**Approach:** Helper-first + styling-first per PBB refactor playbook

**Status Update (2026-04-06):**
- Phase 1 is substantially complete.
- Phase 2 is substantially implemented, with a few presentation refinements still open.
- Phase 3 is substantially implemented, with diagnostics results rendered in the diagnostics section instead of inside the modal body.
- Phase 4 is partially implemented with deployment tabs for WAMP/Apache, Docker, and Kubernetes.
- Local `localhost/mapserver` support was fixed by switching homepage assets to relative URLs and API calls to `index.php/...` route fallback.

---

## Phase 1: Foundation & Helper Setup

Essential setup and core static content. Target: establish helper infrastructure + page shell.

### Infrastructure Tasks
- [x] Vendor PBB Helpers locally to `vendor/helpers.pbb.ph/`
  - [x] Copy from `https://github.com/jybanez/helpers.pbb.ph` or git submodule
  - [x] Verify `vendor/helpers.pbb.ph/js/ui/ui.loader.js` exists
  - [x] Verify `vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.js` exists
  - [x] Verify `vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.css` exists
  - [x] Test `uiLoader` can load at least one helper (e.g., `ui.fieldset`)
- [x] Update homepage `<head>` to include:
  - [x] Link to `vendor/helpers.pbb.ph/dist/helpers.ui.bundle.min.css`
  - [x] Script import of `uiLoader` from `vendor/helpers.pbb.ph/js/ui/ui.loader.js`
  - [x] Enable `uiLoader.setPreferBundles(true)` so components resolve through the dist bundle
- [x] Create `js/homepage.js` as the page adapter:
  - [x] Import `uiLoader`
  - [x] Declare data structures (endpoints array, config object)
  - [x] Implement adapter functions to normalize API responses for helpers
- [x] Update `.gitignore` if needed to allow `vendor/helpers.pbb.ph/` in version control

### Content & Static Layout
- [x] Create page section structure using semantic HTML:
  - [x] `<header>` - Title and intro
  - [x] `<section id="endpoints">` - Endpoint matrix (grid placeholder)
  - [x] `<section id="security">` - Security guidance (fieldset placeholder)
  - [x] `<section id="integration">` - PBB integration contract (fieldset placeholder)
  - [x] `<section id="operations">` - Quick operations (fieldset placeholder)
  - [x] `<section id="deployment">` - Deployment tabs (tabs placeholder)
- [x] Add static introductory text before each section
- [x] Style page with shared primitives from `ui.components.css` (`.ui-button`, `.ui-badge`, etc.)
- [x] Keep fallback copy visible for JavaScript-off scenarios

### Code Organization (index.php)
- [x] Extract homepage HTML into `renderHomepage()` helper function
- [x] Ensure no sensitive values (API keys, tokens) appear in markup
- [x] Return HTML with placeholder container divs for helper-populated sections
- [x] Add comment about `/api/status` endpoint (implementation in Phase 2)

### Testing & Validation
- [x] Verify page loads without JavaScript errors
- [x] Verify `uiLoader` can be imported and called
- [x] Test semantic HTML structure with accessibility checker
- [x] Confirm no console warnings about missing CSS files
- [x] Mobile viewport test (≥320px width)

---

## Phase 2: Operational Status & Helper Components

Integrate PBB Helpers components for live data display. Target: operational visibility.

### Backend Enhancements
- [x] Create `/api/status` endpoint (or extend `/tiles/health`) returning JSON:
  ```json
  {
    "status": "ok",
    "timestamp": "2026-03-25T10:30:00Z",
    "cache_ready": true,
    "upstreams": {
      "raster": "https://...",
      "vector": "https://...",
      "terrain": "https://..."
    },
    "ssl_verify": true,
    "log_file": "/path/to/tiles.log"
  }
  ```
- [x] Validate JSON schema against example responses
- [x] Add error handling (return 500 + error message on failure)

### Helper Component 1: Status Display (Phase 2a)
**Component**: `createFieldset(...)` for configuration display

- [x] Create container `<div id="status-fieldset"></div>` in HTML
- [ ] In `homepage.js`:
  ```js
  const statusData = {
    legend: "Service Status",
    rows: [
      [{ type: "display", name: "service", label: "Service Status", value: "OK" }],
      [{ type: "display", name: "timestamp", label: "Last Check", value: "..." }],
      [{ type: "display", name: "upstreams", label: "Upstreams", value: "..." }]
    ]
  };
  const statusFieldset = await uiLoader.create("ui.fieldset", 
    document.getElementById("status-fieldset"), 
    statusData
  );
  ```
- [x] Fetch `/api/status` on page load
- [x] Normalize response data (mask API keys)
- [x] Call `statusFieldset.update(nextData)` when fresh status arrives
- [x] Add loading + error states

### Helper Component 2: Endpoint Matrix (Phase 2b)
**Component**: `createGrid(...)` for endpoint reference

- [x] Create container `<div id="endpoints-grid"></div>` in HTML
- [x] Define endpoints array with columns:
  - `method` (GET, POST)
  - `path` (e.g., `/tiles/raster/{z}/{x}/{y}.png`)
  - `mime_type` (e.g., `image/png`)
  - `description`
  - `actions` (test, copy curl)
- [x] Initialize grid:
  ```js
  const endpointsGrid = await uiLoader.create("ui.grid", 
    document.getElementById("endpoints-grid"),
    endpointsData,
    { mode: "local", enableSearch: true, enableSort: true, ... }
  );
  ```
- [x] Implement cell action handlers:
  - [x] **"Copy curl"**: Show toast `"Copied curl command"` via `createToastStack`
  - [x] **"Test"**: Open modal with endpoint details
- [x] Test grid sorting, search, and action buttons

### Helper Component 3: Security Fieldset (Phase 2c)
**Component**: `createFieldset(...)` with alert rows

- [x] Create sections via fieldsets:
  - [x] Security checklist fieldset
  - [ ] Deployment checklist fieldset
- [x] Use `alert` row type for emphasis:
  ```js
  { type: "alert", tone: "warning", content: "Never commit .env to version control" }
  ```
- [x] Use `display` rows for configuration values
- [ ] Include icon bullets via `createIcon("status.info")`

### Styling & Shared Primitives
- [x] Apply `.ui-badge` class to status indicators
- [x] Use `.ui-button-primary` for main actions (Test, Run Diagnostics)
- [x] Use `.ui-button-ghost` for secondary actions (Copy, Close)
- [x] Apply `.ui-eyebrow` to section headers
- [x] Test color contrast for accessibility

### Testing (Phase 2)
- [x] Status card fetches and displays `/api/status` correctly
- [x] Error states handled gracefully (show error message, not blank)
- [x] Endpoint grid search filters by method/path/description
- [x] Copy curl button copies to clipboard (verify via browser devtools)
- [x] Toast notifications appear on action completion
- [x] Mobile responsiveness on fieldsets and grid

---


## Phase 3: Self-Test & Diagnostics (Optional)

Interactive testing interface using PBB Helpers modals. Target: reduced integration friction.

### Helper Component: Test Modal (Phase 3a)
**Component**: `createFormModal(...)` preset + results grid

- [x] Create "Run Diagnostics" button in operations section
- [x] Button click opens `createFormModal(...)` with:
  - [x] Checkboxes for endpoints to test (health, raster, vector, terrain, POI, glyphs)
  - [x] "Run Tests" submit button → triggers async test execution
- [ ] Test execution:
  - [x] Fetch HEAD/GET for each selected endpoint
  - [x] Collect status code, latency (ms), X-Cache header
  - [x] Handle timeouts gracefully (5-10 second limit per endpoint)
- [ ] Results display in modal using `createGrid(...)` (chrome: false):
  - [x] Columns: endpoint, status (badge), latency, cache_status
  - [ ] Status badges: Green (200), Yellow (timeout), Red (4xx/5xx)
  - [ ] Status badges use helper tone system (`.ui-badge-success`, etc.)
- [ ] Toast notifications during test via `createToastStack(...)`
  - [x] Per-endpoint: "Testing raster: OK (12ms)"
  - [x] Final: "Tests complete: 5/6 passed"

### Implementation  
- [x] Tests execute in `homepage.js` adapter layer (async/fetch)
- [x] Responses normalized before passing to helpers
- [x] No secrets exposed in results

### Testing (Phase 3)
- [x] Test modal opens/closes correctly
- [x] Tests execute as background operations
- [x] Success and error responses display properly
- [x] Timeout handling (5-10s per endpoint) works
- [ ] Cross-browser compatibility verified

---

## Phase 4: Documentation & Integration (Optional)

Add deployment guidance and PBB integration contract using helpers.

### Helper Component: Deployment Tabs (Phase 4a)
**Component**: `createTabs(...)` for multi-runtime documentation

- [x] Create `<div id="deployment-tabs"></div>` in HTML
- [x] Initialize tabs with options:
  - [x] Tab 1: WAMP/Apache (current production)
  - [x] Tab 2: Docker (future reference)
  - [x] Tab 3: Kubernetes (future reference)
- [x] Content per tab:
  - [x] Requirements (PHP version, modules)
  - [x] Dockerfile / manifest reference
  - [x] Volume/environment setup
  - [x] Health check configuration
- [x] Use `<pre>` blocks for code samples

### Integration Guidance Section (Phase 4b)
**Component**: `createFieldset(...)` with code block rows

- [x] Create "PBB Integration Contract" fieldset with rows:
  - [x] Base URL recommendation
  - [x] Health check polling pattern
  - [x] Cache behavior (MISS/HIT reference)
  - [x] Error recovery strategies
  - [x] Curl/JavaScript usage examples
- [x] Use `display` and `text` row types
- [ ] Include icons via `createIcon("status.info")`

### Content Tasks
- [x] Reference against current `config.php`, `index.php`
- [x] Ensure guidance works locally and in production
- [x] Add links to README.md and chat_log.md
- [ ] Get feedback from consuming PBB teams

### Testing (Phase 4)
- [x] Tab switching works; content displays correctly
- [x] Code samples render without encoding issues
- [x] External links resolve (README, chat_log)
- [ ] Team review + feedback collected

---

## Implementation Order & Timeline

### Recommended Schedule
1. **Phase 1** (Days 1-2): PBB Helpers setup + static content + foundations
   - Vendor helpers, set up `uiLoader`, create page shell
   - Add static sections with semantic HTML
2. **Phase 2** (Days 3-5): Dynamic components + status display
   - `/api/status` endpoint
   - `createFieldset(...)` for status display
   - `createGrid(...)` for endpoint matrix
   - Test actions and clipboard copy
3. **Phase 3** (Days 6-8, optional): Interactive testing
   - `createFormModal(...)` for test selection
   - Results grid with status badges
   - Toast notifications
4. **Phase 4** (Days 9+, optional): Documentation & integration
   - `createTabs(...)` for deployment guides
   - PBB Integration fieldset
   - Review + feedback from consuming teams

### Phase Gate Decisions
Before starting each phase, confirm with team:
- [ ] **Gate 1** (after Phase 1): Approve static structure + continue to Phase 2?
- [ ] **Gate 2** (after Phase 2): Approve status + list + do Phase 3 (test interface)?
- [ ] **Gate 3** (after Phase 3): Approve diagnostics + do Phase 4 (docs)?

---

## Quality Gates (All Phases)

### Code Quality
- [x] No console errors or warnings in browser devtools
- [x] No hardcoded sensitive values in network requests or HTML
- [x] All async operations have proper error handling
- [ ] Tests pass on both PhpUnit command line and browser execution
- [x] No external dependencies (except PBB Helpers, already vendored)

### Accessibility & UX
- [x] Semantic HTML structure (no div salad)
- [x] Proper ARIA labels on form inputs and regions
- [x] Color contrast meets WCAG AA standards
- [x] Keyboard navigation: Tab, Enter, Escape work as expected
- [x] Focus indicator visible on interactive elements
- [ ] Screen reader testing (NVDA/JAWS on Win, VoiceOver on Mac)

### Performance
- [x] Homepage initial load < 1.5 seconds
- [x] Status fetch doesn't block DOM rendering (async)
- [x] Grid with 20+ rows remains responsive
- [x] No layout shifts after JS loads (Cumulative Layout Shift < 0.1)
- [x] Mobile viewport 320px+: readable without horizontal scroll

### Security
- [x] API keys masked as `***` when displayed
- [x] Purge token never exposed (only "configured: yes/no")
- [x] XSS protection on dynamic content (no innerHTML without sanitizing)
- [x] CSRF protection if accepting POST actions
  - [x] Not applicable for the current homepage UI (browser actions are GET/read-only; no state-changing POST is issued)
- [x] No sensitive logs in browser console
- [x] Fetch requests use appropriate credentials mode

### Testing Across Environments
- [x] Local PHP 8.2 dev server
- [x] Apache WAMP environment (production-like)
- [ ] HTTPS context (if CDN/reverse proxy used)
- [ ] Cross-browser: Chrome, Firefox, Safari, Edge (latest versions)
- [ ] Mobile browsers: iOS Safari, Android Chrome

---

## Success Criteria

A successful implementation will deliver a homepage that:

✅ **Structure**: Clear, semantic HTML with PBB Helpers components  
✅ **Functionality**: Status display, endpoint matrix, optional test suite  
✅ **Security**: Zero secrets exposed; robust input handling  
✅ **Accessibility**: WCAG AA compliant, keyboard navigable, screen reader friendly  
✅ **Performance**: Fast load times, responsive to interactions  
✅ **Integration**: Clear guidance for PBB consuming teams  
✅ **Maintainability**: Uses shared helpers instead of project-local UI  

Final checklist before launch:
- [ ] All phases marked complete (or consciously deferred)
- [ ] Quality gates passed for implemented phases
- [ ] Team review + approval
- [ ] Smoke test on production domain
- [ ] Monitor error tracking (Sentry, etc.) for 24h after deploy

---

## Reference Documents

- [homepage-improvements-proposal.md](homepage-improvements-proposal.md) - Full proposal and component details
- [../README.md](../README.md) - Project setup and tile proxy overview
- [PBB Helpers Refactor Playbook](https://github.com/jybanez/helpers.pbb.ph/blob/main/docs/pbb-refactor-playbook.md) - Integration best practices
- [PBB Helpers Component Reference](https://jybanez.github.io/helpers.pbb.ph/) - Live demos
