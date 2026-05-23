# MapServer Homepage Improvements Proposal

**Date:** 2026-03-25  
**Status:** Proposal (PBB Helpers Edition)  
**Purpose:** Enhance the MapServer homepage to serve as a meaningful operational and integration interface for PBB ecosystem teams.  
**UI Framework:** PBB Helpers (vendored locally)

## 0. PBB Helpers Integration Strategy

This proposal adopts [PBB Helpers](https://github.com/jybanez/helpers.pbb.ph) as the shared UI framework, following the **helper-first** and **styling-first** principles from the [refactor playbook](https://github.com/jybanez/helpers.pbb.ph/blob/main/docs/pbb-refactor-playbook.md):

**Design principles applied:**
- **Helper-first**: Use shared component factories (`createForm Modal`, `createGridFieldset`, `createToggleButton`) instead of building custom UI
- **Styling-first**: Apply `ui.components.css` tokens and shared button/badge primitives (`.ui-button-primary`, `.ui-button-ghost`, `.ui-cell-actions`) before custom CSS
- **Registry-aware**: Load components via `uiLoader` for automatic CSS+JS injection by registry key
- **Composition**: Narrow component contracts (modal, fieldset, grid, toggle) compose into higher-level workflows
- **Data normalization**: Helper-agnostic API payloads; project handles normalization in adapter functions

**Vendoring**: Helper assets will be vendored locally in `vendor/helpers.pbb.ph/` with controlled updates.

---

## 1. Current State

The current homepage at `/` is a **minimal, static documentation page** that covers:
- Basic project description
- Available endpoints list
- Troubleshooting tips
- Quick test examples

### Strengths
- Functional endpoint listing
- Clean, semantic HTML structure
- Accessible baseline

### Gaps
- No operational/health status visibility
- No configuration transparency
- No self-test/diagnostics capability
- Limited integration guidance for PBB teams
- No security/deployment documentation
- Limited mobile responsiveness
- No cache management UI

---

## 2. Proposed Improvements

### 1. Operational Status & Health Dashboard  
**Implementation**: `createFieldset(...)` + `createGrid(...)` + `createIcon(...)`

Display live operational information using PBB Helpers shared form and data display components:
- **Service Status Card**: Use `createFieldset(...)` with helper-owned `display` rows showing:
  - GET `/tiles/health` response (status, timestamp)
  - Status rendered with helper semantic icons (`createIcon("status.ok")`)
  - Timestamp formatted via helper locale support
- **Configuration Display**: Grouped fieldset showing:
  - SSL verification state (`.ui-badge` for visual emphasis)
  - Upstreams active (raster, vector, terrain, POI, glyphs)
  - Mask API keys as `***` (no helper exposure of secrets)
  - Log file location reference
  - Purge token configured (boolean indicator, no value exposure)
- **Cache Readiness**: Use `createProgress(...)` or simple display row showing `storage/tiles` mount status

### 2. Enhanced Endpoint Matrix with Live Examples  
**Implementation**: `createGrid(...)` + `createModal(...)` + copy-friendly code blocks

Display endpoint reference matrix using shared grid component:
- **Grid Columns**: method, path, MIME type, description, test action
  - Use `createGrid(...)` with `local` mode (client-side filtering)
  - Enable `enableSearch` to filter by endpoint name
  - Enable `enableSort` for method/path/MIME columns
- **Cell Actions**: Each row includes:
  - "Copy curl" button → copies to clipboard and shows toast (`createToastStack`)
  - "Test" button → opens `createModal(...)` with embedded curl command
- **Modal Content** (when "Test" clicked):
  - Pre-filled curl command for that endpoint
  - Expected response headers (`X-Cache`, `Content-Encoding`)
  - Example coordinate ranges (0/0/0 for zoom 0)
  - "Copy curl" action in modal header
  - "Close" simplifies exit
- **Badge Styling**: Use shared `.ui-badge` for MIME type (e.g., `.ui-badge` for `image/png`, `application/octet-stream`)

### 3. Self-Test & Diagnostics Interface  
**Implementation**: `createFormModal(...)` preset + `createToastStack(...)` + test result display

Interactive testing interface for operators to verify endpoint health:
- **"Run Diagnostics" Button**: Opens `createFormModal(...)` with:
  - Checkboxes for endpoints to test (raster, vector, terrain, POI, glyphs, health)
  - "Run Tests" submit → fetches selected endpoints and displays results in-modal
- **Results Display**:
  - Use `createGrid(...)` (chrome: false) to show results table
  - Columns: endpoint, status (badge), latency (ms), cache status
  - Status badges use helper semantic styling (success/error/warning tones)
  - Green badge for 200, red for errors, yellow for timeouts
- **Toast Notifications** (`createToastStack`):
  - Show toast for each test completion: "Raster health: OK (12ms)"
  - Error toasts: "Vector health: endpoint unreachable"
- **Debug Mode Link**: Discrete link to `?debug=1` documentation

### 4. Security & Deployment Guidance  
**Implementation**: `createFieldset(...)` with richer content rows + `createIcon(...)`

Dedicated operational guidance section using shared form fieldset component:
- **Security Checklist** (`createFieldset` with `alert` rows):
  - Required environment variables (STADIAMAPS_API_KEY, MAPTILER_API_KEY)
  - `.env` best practices ("never commit keys; use `.gitignore`")
  - CORS implications of `Access-Control-Allow-Origin: *`
  - Purge endpoint token should be long + rotate regularly
  - Use helper `alert` rows for emphasis per topic
- **Deployment Checklist**:
  - SSL/TLS certificate setup
  - Log file monitoring path
  - Cache directory permissions
  - Upstream endpoint availability checks
- **Helper Component Usage**:
  - Grouped fieldsets per topic (Security, Deployment, Monitoring)
  - Icon bullets via `createIcon("status.info")` for tips
  - `.ui-form-error` styling for warnings ("never expose keys")

### 5. PBB Integration Contract  
**Implementation**: `createFieldset(...)` + code block section + `createIcon(...)`  

Formalize MapServer's role in the PBB ecosystem by documenting integration expectations:
- **Integration Contract** section:
  - Base URL recommendation: `https://mapserver.pbb.ph`
  - Health check endpoint: GET `/tiles/health` (5-10 second polling recommended)
  - Cache behavior (MISS on first request, HIT on subsequent)
  - Error recovery paths (retry with exponential backoff on 5xx)
  - Upstream failure tolerance (timeouts default to 30s, configurable)
- **Recommended Usage** (`createFieldset` with code block rows):
  - Basic tile fetch example (curl + response headers)
  - Health check polling pattern
  - Cache validation using `X-Cache` header
  - Error handling patterns
- **Shared Styling**: Use `.ui-badge` for status indicators, `.ui-eyebrow` for section headers

### 6. Quick Operations Reference  
**Implementation**: `createFieldset(...)` with `display` rows + copy buttons via `createIcon(...)`

Copy-to-clipboard reference for common operations:
- Fieldset layout with rows for each operation:
  - **Start Development Server**: `php -S localhost:8000` (copy button)
  - **Check Health**: `curl http://localhost:8000/tiles/health` (copy + test button)
  - **Monitor Logs**: `tail -f storage/logs/tiles.log` (copy button)
  - **Purge Endpoint**: `curl -X POST http://localhost:8000/tiles/purge/raster/0/0/0.png?token=TOKEN` (token placeholder warning)
- **Helper Integration**:
  - Each command in a `display` row with icon indicator
  - Copy button uses helper-owned clipboard action
  - Toast feedback: "Copied to clipboard" via `createToastStack`

### 7. Docker/Deployment Reference (Optional)  
**Implementation**: `createFieldset(...)` + `createTabs(...)` for multi-runtime documentation

Minimal guidance for containerized deployment scenarios:
- **Deployment Tabs** (`createTabs`):
  - Tab 1: WAMP/Apache (current production)
  - Tab 2: Docker (future reference)
  - Tab 3: Kubernetes (future reference)
- **Docker Tab Content** (when selected):
  - Reference Dockerfile structure for PHP 8.2+ + Apache
  - Environment variable mounting (`.env` as secret or configmap)
  - Volume binding for `storage/tiles` cache persistence
  - Health check endpoint configuration for orchestrators
  - Health check Liveliness Probe: GET `/tiles/health` interval 30s
- **Styling**: Use `.ui-code` or `<pre>` for Dockerfile snippets

---

## 3. Benefits

**Operational Quality:**
1. **Improved Discoverability**: PBB teams understand MapServer capabilities and status at a glance
2. **Operational Visibility**: Real-time health status + live configuration display reduces integration friction
3. **Self-Service Diagnostics**: Teams can verify endpoint health without manual curl testing or ticket escalation
4. **Security by Design**: Clear guidance on secrets management, environment variables, and deployment best practices

**Development Experience:**
5. **UX Consistency**: Shared PBB Helpers components and styling create familiarity for PBB teams
6. **Reduced Maintenance**: Shared UI components means fewer custom CSS/JS rules to maintain across PBB projects
7. **Helper Pattern Alignment**: Follows established helper-first refactor playbook, lowering onboarding friction

**Architecture:**
8. **Modularity**: Dynamic loading via `uiLoader` keeps homepage performant
9. **Reusable Patterns**: Form, grid, modal, fieldset patterns are available for future MapServer interfaces
10. **Accessibility**: Helpers ship with tested semantic HTML, ARIA labels, and keyboard support

---

## 4. Implementation Notes

**PBB Helpers Integration:**
- Vendor helper assets locally in `vendor/helpers.pbb.ph/`
- Use `uiLoader` registry by component key (`ui.fieldset`, `ui.grid`, `ui.modal`, etc.)
- Follow helper-first principle: reach for documented components before custom UI
- Data normalization occurs in homepage adapter layer (not inside helpers)
- Shared CSS tokens from `ui.components.css` for colors, spacing, typography

**Security & Data Hygiene:**
- Mask API keys as `***` when displaying upstream configuration
- Never expose purge token value; display only "configured: yes/no"
- All secret reads use existing `config.php` `requiredEnv()` / `env()` functions
- Dynamic health checks fetch `/tiles/health` endpoint only (no backend secrets in homepage)

**Progressive Enhancement:**
- Keep core endpoint listing functional without JavaScript
- Enhancements (status dashboard, grid sorting/search, test modal) progressively load via `uiLoader`
- Fallback text/links for pre-JavaScript navigation

**Testing & Accessibility:**
- All test buttons use helper-owned modal + toast patterns (no custom overlays)
- Icon usage via `createIcon(...)` ensures consistent visual language
- Form fieldsets use semantic `<fieldset>`/`<legend>` per helper contract
- Grids expose `ariaLabel` and keyboard navigation support

---

## 5. Related Documents & Resources

**MapServer Documentation:**
- [implementation-checklist.md](homepage-implementation-checklist.md) - Phase-by-phase implementation roadmap

**PBB Helpers Reference:**
- [PBB Helpers README](https://github.com/jybanez/helpers.pbb.ph) - Component catalog and demos
- [PBB Refactor Playbook](https://github.com/jybanez/helpers.pbb.ph/blob/main/docs/pbb-refactor-playbook.md) - Integration best practices
- Live Demos: https://jybanez.github.io/helpers.pbb.ph/ (for component reference)

**Key Helper Components Used:**
- `createFieldset(...)` - Grouped form sections with support for display, alert, and code rows
- `createGrid(...)` - Data table with local search, sort, pagination, cell actions
- `createModal(...)`/`createActionModal(...)` - Overlay containers with header/footer actions
- `createToastStack(...)` - Transient notifications (info, success, error, warning)
- `createIcon(...)` - Categorized SVG icons with semantic status variants
- `createTabs(...)` - Tab-based content switching (for deployment reference tabs)

---

## 6. Success Criteria

✓ Homepage loads and renders without JavaScript errors  
✓ All shared PBB Helpers components load via `uiLoader` with correct CSS injection  
✓ Health status fetches from `/tiles/health` and displays within 1 second  
✓ Grid endpoint matrix supports search, sort, and copy-to-clipboard actions  
✓ Self-test modal executes diagnostics and renders results correctly  
✓ All form fieldsets are accessible via keyboard (Tab, Enter, Escape)  
✓ Secret values (API keys, purge token) never exposed in page HTML or network requests  
✓ Mobile viewport works on devices ≥320px wide  
✓ All curl examples copy to clipboard without modification  
✓ Helper styling is visually consistent with PBB ecosystem standards
