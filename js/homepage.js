/**
 * MapServer homepage adapter.
 *
 * Loads PBB Helpers components, fetches live operational status,
 * and wires endpoint actions plus diagnostics.
 */

const { uiLoader } = window;
const appBasePath = new URL('../', import.meta.url).pathname.replace(/\/$/, '');
const routePrefix = new URL('../index.php', import.meta.url).pathname.replace(/\/$/, '');
const routeUrl = (path) => `${routePrefix}${path.startsWith('/') ? path : `/${path}`}`;
const publicPath = (path) => `${appBasePath}${path.startsWith('/') ? path : `/${path}`}` || '/';
const absolutePublicUrl = (path) => new URL(publicPath(path), window.location.origin).toString();
const appBaseUrl = new URL(`${appBasePath || ''}/`, window.location.origin).toString();
const defaultFetchOptions = {
  credentials: 'same-origin',
};

if (!uiLoader) {
  console.error('[MapServer] uiLoader not available. Check PBB Helpers loading in index.php');
}

const endpointReference = [
  {
    id: 'health',
    method: 'GET',
    path: '/tiles/health',
    testPath: '/tiles/health',
    mimeType: 'application/json',
    description: 'Basic service health endpoint for uptime checks.',
    cached: false,
    cacheBehavior: 'BYPASS',
    expectedHeaders: ['Content-Type: application/json', 'X-Cache: BYPASS'],
    exampleLabel: '/tiles/health',
  },
  {
    id: 'status',
    method: 'GET',
    path: '/api/status',
    testPath: '/api/status',
    mimeType: 'application/json',
    description: 'Homepage operational payload with cache, upstream, and config metadata.',
    cached: false,
    cacheBehavior: 'BYPASS',
    expectedHeaders: ['Content-Type: application/json', 'X-Cache: BYPASS'],
    exampleLabel: '/api/status',
  },
  {
    id: 'raster',
    method: 'GET',
    path: '/tiles/raster/{z}/{x}/{y}.png',
    testPath: '/tiles/raster/0/0/0.png',
    mimeType: 'image/png',
    description: 'Raster map tiles proxied from the configured raster upstream.',
    cached: true,
    ttl: 'Filesystem cache until purged or replaced',
    cacheBehavior: 'MISS then HIT',
    expectedHeaders: ['Content-Type: image/png', 'X-Cache: MISS/HIT'],
    exampleLabel: '/tiles/raster/0/0/0.png',
  },
  {
    id: 'vector',
    method: 'GET',
    path: '/tiles/vector/{z}/{x}/{y}.pbf',
    testPath: '/tiles/vector/0/0/0.pbf',
    mimeType: 'application/x-protobuf',
    description: 'Vector tiles cached on disk and served with upstream encoding metadata.',
    cached: true,
    ttl: 'Filesystem cache until purged or replaced',
    cacheBehavior: 'MISS then HIT',
    expectedHeaders: ['Content-Type: application/x-protobuf', 'Content-Encoding: gzip (upstream-dependent)', 'X-Cache: MISS/HIT'],
    exampleLabel: '/tiles/vector/0/0/0.pbf',
  },
  {
    id: 'terrain',
    method: 'GET',
    path: '/tiles/terrain/{z}/{x}/{y}.png',
    testPath: '/tiles/terrain/0/0/0.png',
    mimeType: 'image/png',
    description: 'Terrain PNG tiles proxied from the terrain upstream.',
    cached: true,
    ttl: 'Filesystem cache until purged or replaced',
    cacheBehavior: 'MISS then HIT',
    expectedHeaders: ['Content-Type: image/png', 'X-Cache: MISS/HIT'],
    exampleLabel: '/tiles/terrain/0/0/0.png',
  },
  {
    id: 'glyphs',
    method: 'GET',
    path: '/tiles/glyphs/{fontstack}/{range}.pbf',
    testPath: '/tiles/glyphs/Open%20Sans%20Regular/0-255.pbf',
    mimeType: 'application/x-protobuf',
    description: 'Glyph ranges used by vector map renderers.',
    cached: true,
    ttl: 'Filesystem cache until purged or replaced',
    cacheBehavior: 'MISS then HIT',
    expectedHeaders: ['Content-Type: application/x-protobuf', 'Content-Encoding: gzip (upstream-dependent)', 'X-Cache: MISS/HIT'],
    exampleLabel: '/tiles/glyphs/Open%20Sans%20Regular/0-255.pbf',
  },
  {
    id: 'poi',
    method: 'GET',
    path: '/tiles/poi/{z}/{x}/{y}.pbf',
    testPath: '/tiles/poi/0/0/0.pbf',
    mimeType: 'application/x-protobuf',
    description: 'POI vector layer tiles proxied from the configured POI upstream.',
    cached: true,
    ttl: 'Filesystem cache until purged or replaced',
    cacheBehavior: 'MISS then HIT',
    expectedHeaders: ['Content-Type: application/x-protobuf', 'Content-Encoding: gzip (upstream-dependent)', 'X-Cache: MISS/HIT'],
    exampleLabel: '/tiles/poi/0/0/0.pbf',
  },
  {
    id: 'boundary',
    method: 'GET',
    path: '/boundaries/{scope}/{code}.geojson',
    testPath: '/boundaries/barangay/072217029.geojson',
    mimeType: 'application/geo+json',
    description: 'Public hub boundary GeoJSON overlays generated from vendored PSGC resources.',
    cached: true,
    ttl: 'Generated file cache with HTTP validators',
    cacheBehavior: 'Generated then HIT',
    expectedHeaders: ['Content-Type: application/geo+json', 'ETag', 'Access-Control-Allow-Origin: *'],
    exampleLabel: '/boundaries/barangay/072217029.geojson',
  },
];

const serviceStatus = {
  status: 'degraded',
  operational: false,
  message: 'Live status has not loaded yet.',
  version: '1.0.0',
  timestamp: new Date().toISOString(),
  time: new Date().toISOString(),
  uptime: 'Unavailable until the status endpoint responds.',
  cache_ready: false,
  cache: {
    root: '',
    root_exists: false,
    root_writable: false,
    strategy: 'filesystem',
    raster: '0 B',
    vector: '0 B',
    terrain: '0 B',
    glyphs: '0 B',
    poi: '0 B',
  },
  upstreams: {
    raster: { name: 'raster', configured: false, template: '', host: '', scheme: '' },
    vector: { name: 'vector', configured: false, template: '', host: '', scheme: '' },
    terrain: { name: 'terrain', configured: false, template: '', host: '', scheme: '' },
    glyphs: { name: 'glyphs', configured: false, template: '', host: '', scheme: '' },
    poi: { name: 'poi', configured: false, template: '', host: '', scheme: '' },
  },
  warnings: [],
  ssl_verify: true,
  log_file: '',
  purge_token_configured: false,
  configuration: {
    rate_limit: 'Unknown',
    cors_enabled: true,
    cors_origin: '*',
    compression: 'Unknown',
    cache_strategy: 'filesystem',
    configured_upstreams: [],
    ca_bundle_configured: false,
  },
};

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

async function copyTextToClipboard(text) {
  const value = String(text ?? '');
  if (!value) {
    return;
  }

  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(value);
    return;
  }

  const textarea = document.createElement('textarea');
  textarea.value = value;
  textarea.setAttribute('readonly', 'readonly');
  textarea.style.position = 'absolute';
  textarea.style.left = '-9999px';
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand('copy');
  document.body.removeChild(textarea);
}

function formatBoolean(flag, truthy = 'Yes', falsy = 'No') {
  return flag ? truthy : falsy;
}

function formatTimestamp(value) {
  if (!value) {
    return 'Unavailable';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return `${date.toLocaleString()} (${date.toISOString()})`;
}

function createBadge(text, tone = 'neutral') {
  const badge = document.createElement('span');
  badge.className = `ui-badge mapserver-badge mapserver-badge--${tone}`;
  badge.textContent = String(text ?? '');
  return badge;
}

function clearChildren(node) {
  if (!node) {
    return;
  }
  node.replaceChildren();
}

function appendParts(parent, parts) {
  parts.flat().forEach((part) => {
    if (part === null || part === undefined) {
      return;
    }
    if (part instanceof Node) {
      parent.appendChild(part);
      return;
    }
    parent.append(String(part));
  });
  return parent;
}

function createCode(text) {
  const code = document.createElement('code');
  code.textContent = String(text ?? '');
  return code;
}

function createParagraph(...parts) {
  return appendParts(document.createElement('p'), parts);
}

function createList(items = []) {
  const list = document.createElement('ul');
  items.forEach((item) => {
    const li = document.createElement('li');
    appendParts(li, Array.isArray(item) ? item : [item]);
    list.appendChild(li);
  });
  return list;
}

function createCodeBlock(text) {
  const pre = document.createElement('pre');
  pre.appendChild(createCode(text));
  return pre;
}

function alertToneClass(tone) {
  if (tone === 'good') {
    return 'success';
  }
  if (tone === 'danger') {
    return 'danger';
  }
  if (tone === 'warning') {
    return 'warning';
  }
  return 'info';
}

function getResultTone(result) {
  if (result.ok) {
    return 'good';
  }
  if (String(result.status) === 'TIMEOUT') {
    return 'warning';
  }
  return 'danger';
}

function normalizeServiceStatus(raw = {}) {
  return {
    ...serviceStatus,
    ...raw,
    cache: {
      ...serviceStatus.cache,
      ...(raw.cache || {}),
    },
    upstreams: {
      ...serviceStatus.upstreams,
      ...(raw.upstreams || {}),
    },
    warnings: Array.isArray(raw.warnings) ? raw.warnings : serviceStatus.warnings,
    configuration: {
      ...serviceStatus.configuration,
      ...(raw.configuration || {}),
    },
  };
}

function buildCurlCommand(endpoint) {
  return `curl -sS "${absolutePublicUrl(endpoint.testPath)}"`;
}

function buildEndpointSummary(endpoint) {
  const headers = endpoint.expectedHeaders.join(', ');
  return `${endpoint.method} ${endpoint.path}\nExpected headers: ${headers}`;
}

function createActionButton(label, onClick) {
  const button = document.createElement('button');
  button.className = 'ui-button ui-button-ghost ui-cell-action';
  button.type = 'button';
  button.textContent = label;
  button.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    onClick(event);
  });
  return button;
}

function createEndpointActionCell(endpoint, dependencies) {
  const host = document.createElement('div');
  host.className = 'ui-cell-actions';

  host.appendChild(createActionButton('Copy curl', async () => {
    try {
      await copyTextToClipboard(buildCurlCommand(endpoint));
      dependencies.toast.show(`Copied curl for ${endpoint.path}`, { type: 'good', title: 'Endpoints' });
    } catch (error) {
      dependencies.toast.show(`Copy failed: ${error.message}`, { type: 'error', title: 'Endpoints' });
    }
  }));

  host.appendChild(createActionButton('Test', () => {
    openEndpointTestModal(endpoint, dependencies);
  }));

  return host;
}

function buildStatusFieldsetData(status) {
  const upstreamNames = Object.values(status.upstreams)
    .filter((upstream) => upstream?.configured)
    .map((upstream) => upstream.name)
    .join(', ') || 'None';

  const rows = [
    [
      { type: 'display', name: 'service_state', label: 'Service State', value: status.status.toUpperCase() },
      { type: 'display', name: 'checked_at', label: 'Last Check', value: formatTimestamp(status.timestamp || status.time) },
    ],
    [
      { type: 'display', name: 'cache_ready', label: 'Cache Ready', value: formatBoolean(status.cache_ready, 'Yes', 'No') },
      { type: 'display', name: 'cache_root', label: 'Cache Root', value: status.cache.root || 'Not configured' },
    ],
    [
      { type: 'display', name: 'ssl_verify', label: 'SSL Verify', value: formatBoolean(status.ssl_verify, 'Enabled', 'Disabled') },
      { type: 'display', name: 'purge_token', label: 'Purge Token', value: formatBoolean(status.purge_token_configured, 'Configured', 'Missing') },
    ],
    [
      { type: 'display', name: 'configured_upstreams', label: 'Configured Upstreams', value: upstreamNames },
      { type: 'display', name: 'log_file', label: 'Log File', value: status.log_file || 'Not configured' },
    ],
    [
      { type: 'display', name: 'cache_raster', label: 'Raster Cache', value: status.cache.raster },
      { type: 'display', name: 'cache_vector', label: 'Vector Cache', value: status.cache.vector },
    ],
    [
      { type: 'display', name: 'cache_terrain', label: 'Terrain Cache', value: status.cache.terrain },
      { type: 'display', name: 'cache_poi', label: 'POI Cache', value: status.cache.poi },
    ],
    [
      {
        type: 'alert',
        tone: status.operational ? 'good' : 'warning',
        span: 2,
        content: status.message,
      },
    ],
  ];

  status.warnings.forEach((warning) => {
    rows.push([
      {
        type: 'alert',
        tone: 'warning',
        span: 2,
        content: warning,
      },
    ]);
  });

  return {
    legend: status.operational ? 'Service operational' : 'Service degraded',
    description: 'Live status from /api/status.',
    rows,
  };
}

function buildSecurityFieldsetData(status) {
  return {
    legend: 'Security and deployment guardrails',
    description: 'Homepage guidance is aligned to the current PHP proxy behavior.',
    rows: [
      [
        {
          type: 'alert',
          tone: 'info',
          span: 2,
          content: 'Secrets stay in .env and config-derived runtime values. The homepage never prints upstream API keys or purge tokens.',
        },
      ],
      [
        { type: 'display', name: 'cors_origin', label: 'CORS Origin', value: status.configuration.cors_origin || '*' },
        { type: 'display', name: 'ssl_policy', label: 'SSL Verification', value: formatBoolean(status.ssl_verify, 'Enabled', 'Disabled') },
      ],
      [
        { type: 'display', name: 'ca_bundle', label: 'CA Bundle', value: formatBoolean(status.configuration.ca_bundle_configured, 'Configured', 'Not configured') },
        { type: 'display', name: 'purge_status', label: 'Purge Authentication', value: formatBoolean(status.purge_token_configured, 'Token required', 'Token missing') },
      ],
      [
        {
          type: 'alert',
          tone: status.configuration.cors_origin === '*' ? 'warning' : 'info',
          span: 2,
          content: status.configuration.cors_origin === '*'
            ? 'CORS currently allows every origin. Keep this only if broad public access is intended.'
            : `CORS is restricted to ${status.configuration.cors_origin}.`,
        },
      ],
      [
        {
          type: 'alert',
          tone: status.ssl_verify ? 'good' : 'warning',
          span: 2,
          content: status.ssl_verify
            ? 'Upstream TLS certificates are verified.'
            : 'Upstream TLS certificate verification is disabled. Re-enable it before production if possible.',
        },
      ],
      [
        {
          type: 'alert',
          tone: status.purge_token_configured ? 'good' : 'warning',
          span: 2,
          content: status.purge_token_configured
            ? 'Purge routes are protected by a configured token.'
            : 'Purge routes exist, but no purge token is configured yet.',
        },
      ],
    ],
  };
}

function buildIntegrationFieldsetData(status) {
  const errorGuidance = '400 for invalid tile coordinates, 404 for missing resources or purged cache targets, and 502 when an upstream request fails.';
  return {
    legend: 'PBB integration contract',
    description: 'Concrete guidance based on the routes currently implemented by MapServer.',
    rows: [
      [
        { type: 'display', name: 'base_url', label: 'Base URL', value: appBaseUrl },
        { type: 'display', name: 'health_url', label: 'Health Check', value: absolutePublicUrl('/tiles/health') },
      ],
      [
        {
          type: 'alert',
          tone: 'good',
          span: 2,
          content: 'Cache behavior: raster, vector, terrain, glyph, and POI tile routes return X-Cache MISS on first fetch and HIT after a cached copy exists.',
        },
      ],
      [
        {
          type: 'alert',
          tone: 'info',
          span: 2,
          content: `Error behavior: ${errorGuidance}`,
        },
      ],
      [
        {
          type: 'alert',
          tone: 'info',
          span: 2,
          content: 'Boundary overlays are public GeoJSON at /boundaries/{scope}/{code}.geojson and are safe for operator and command map clients.',
        },
      ],
      [
        {
          type: 'alert',
          tone: 'info',
          span: 2,
          content: 'Health polling can use GET /tiles/health every 5 to 10 seconds. /api/status is better when operators need cache and configuration metadata.',
        },
      ],
      [
        {
          type: 'alert',
          tone: 'warning',
          span: 2,
          content: 'Consumers should retry 5xx upstream failures with backoff and may fall back to raster tiles if vector payloads are temporarily unavailable.',
        },
      ],
    ],
  };
}

function buildOperationsFieldsetData(status) {
  const commands = [
    {
      label: 'Health Check',
      value: `curl -sS "${absolutePublicUrl('/tiles/health')}"`,
    },
    {
      label: 'Status Payload',
      value: `curl -sS "${absolutePublicUrl('/api/status')}"`,
    },
    {
      label: 'Fetch Raster Sample',
      value: `curl -sS -o sample-raster.png "${absolutePublicUrl('/tiles/raster/0/0/0.png')}"`,
    },
    {
      label: 'Purge Raster Sample',
      value: `curl -X POST "${absolutePublicUrl('/tiles/purge/raster/0/0/0.png')}?token=YOUR_TOKEN"`,
    },
  ];

  const rows = commands.map((command) => ([
    {
      type: 'display',
      name: `command_${command.label.replace(/\s+/g, '_').toLowerCase()}`,
      label: command.label,
      value: command.value,
      span: 2,
    },
  ]));

  rows.push([
    {
      type: 'alert',
      tone: status.purge_token_configured ? 'info' : 'warning',
      span: 2,
      content: status.purge_token_configured
        ? 'Replace YOUR_TOKEN with the configured purge token before running purge requests.'
        : 'Purge command shown for reference only. Configure TILES_PURGE_TOKEN before using purge routes.',
    },
  ]);

  return {
    legend: 'Quick operations',
    description: 'Commands are generated from the current deployment path.',
    rows,
  };
}

function ensureDiagnosticsHosts(diagnosticsOutput) {
  let summary = diagnosticsOutput.querySelector('[data-diagnostics-summary]');
  let gridHost = diagnosticsOutput.querySelector('[data-diagnostics-grid]');

  if (!summary || !gridHost) {
    clearChildren(diagnosticsOutput);
    summary = document.createElement('div');
    summary.dataset.diagnosticsSummary = 'true';
    gridHost = document.createElement('div');
    gridHost.dataset.diagnosticsGrid = 'true';
    diagnosticsOutput.append(summary, gridHost);
  }

  return { summary, gridHost };
}

function renderDiagnosticsSummary(summaryHost, message, tone = 'info') {
  clearChildren(summaryHost);
  const alert = document.createElement('div');
  alert.className = `ui-fieldset-alert is-${alertToneClass(tone)}`;
  const pre = document.createElement('pre');
  pre.style.whiteSpace = 'pre-wrap';
  pre.style.margin = '0';
  pre.textContent = String(message ?? '');
  alert.appendChild(pre);
  summaryHost.appendChild(alert);
}

async function fetchWithTimeout(url, options = {}, timeoutMs = 8000) {
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, {
      ...defaultFetchOptions,
      ...options,
      signal: controller.signal,
    });
  } finally {
    window.clearTimeout(timer);
  }
}

async function readResponsePreview(response) {
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json') || contentType.startsWith('text/')) {
    const text = await response.clone().text();
    return text.length > 220 ? `${text.slice(0, 220)}...` : text;
  }

  if (contentType.startsWith('image/')) {
    return `Binary image payload (${contentType})`;
  }

  if (contentType.includes('protobuf') || contentType.includes('octet-stream')) {
    return `Binary payload (${contentType || 'application/octet-stream'})`;
  }

  const text = await response.clone().text();
  return text.length > 220 ? `${text.slice(0, 220)}...` : text;
}

async function runEndpointDiagnostic(endpoint, timeoutMs = 8000) {
  const startedAt = performance.now();
  const url = routeUrl(endpoint.testPath);

  try {
    const response = await fetchWithTimeout(url, { method: endpoint.method || 'GET' }, timeoutMs);
    const latencyMs = Math.round(performance.now() - startedAt);
    const preview = await readResponsePreview(response);
    const cacheHeader = response.headers.get('X-Cache') || 'n/a';
    const contentType = response.headers.get('content-type') || endpoint.mimeType || 'unknown';

    return {
      id: endpoint.id,
      endpoint: endpoint.path,
      requestPath: endpoint.testPath,
      status: response.status,
      ok: response.ok,
      latencyMs,
      cacheStatus: cacheHeader,
      contentType,
      preview,
    };
  } catch (error) {
    const timedOut = error.name === 'AbortError';
    return {
      id: endpoint.id,
      endpoint: endpoint.path,
      requestPath: endpoint.testPath,
      status: timedOut ? 'TIMEOUT' : 'ERROR',
      ok: false,
      latencyMs: Math.round(performance.now() - startedAt),
      cacheStatus: 'n/a',
      contentType: 'n/a',
      preview: timedOut ? 'Request exceeded timeout window.' : error.message,
    };
  }
}

function openEndpointTestModal(endpoint, dependencies) {
  const curlCommand = buildCurlCommand(endpoint);
  let modal = null;

  modal = dependencies.createFormModal({
    title: `Test ${endpoint.path}`,
    size: 'md',
    submitLabel: 'Run Test',
    cancelLabel: 'Close',
    closeOnSuccess: false,
    extraActionsPlacement: 'start',
    extraActions: [
      {
        id: 'copy-curl',
        label: 'Copy curl',
        variant: 'ghost',
        async onClick() {
          try {
            await copyTextToClipboard(curlCommand);
            dependencies.toast.show(`Copied curl for ${endpoint.path}`, { type: 'good', title: 'Endpoints' });
          } catch (error) {
            dependencies.toast.show(`Copy failed: ${error.message}`, { type: 'error', title: 'Endpoints' });
          }
          return false;
        },
      },
    ],
    rows: [
      [
        { type: 'display', name: 'method', label: 'Method', value: endpoint.method },
        { type: 'display', name: 'mime', label: 'MIME Type', value: endpoint.mimeType },
      ],
      [
        { type: 'display', name: 'example_path', label: 'Sample Request', value: routeUrl(endpoint.testPath) },
        { type: 'display', name: 'cache_mode', label: 'Cache Behavior', value: endpoint.cacheBehavior },
      ],
      [
        {
          type: 'textarea',
          name: 'curl_command',
          label: 'curl',
          value: curlCommand,
          readonly: true,
          span: 2,
        },
      ],
      [
        {
          type: 'alert',
          tone: 'info',
          span: 2,
          content: buildEndpointSummary(endpoint),
        },
      ],
      [
        { type: 'display', name: 'last_status', label: 'Last Status', value: 'Not run yet' },
        { type: 'display', name: 'last_latency', label: 'Latency', value: '-' },
      ],
      [
        {
          type: 'textarea',
          name: 'last_preview',
          label: 'Last Response Preview',
          value: 'Run the endpoint test to inspect the latest result.',
          readonly: true,
          span: 2,
        },
      ],
    ],
    async onSubmit(_values, ctx) {
      try {
        ctx.clearFormError();
        dependencies.toast.show(`Testing ${endpoint.path}...`, { type: 'info', title: 'Endpoints' });
        const result = await runEndpointDiagnostic(endpoint);
        modal.setValues({
          last_status: result.ok ? `${result.status} OK (${result.cacheStatus})` : String(result.status),
          last_latency: `${result.latencyMs} ms`,
          last_preview: result.preview,
        });
        dependencies.toast.show(
          result.ok ? `${endpoint.id}: OK in ${result.latencyMs} ms` : `${endpoint.id}: ${result.status}`,
          { type: result.ok ? 'good' : 'error', title: 'Endpoints' }
        );
      } catch (error) {
        ctx.setFormError(`Endpoint test failed: ${error.message}`);
        dependencies.toast.show(`Test failed: ${error.message}`, { type: 'error', title: 'Endpoints' });
      }
      return false;
    },
  });

  modal.open();
}

async function initializePhase1() {
  console.log('[MapServer] Phase 1: Infrastructure verification');
  const placeholders = document.querySelectorAll('[data-helper-placeholder]');
  console.log(`[MapServer] Found ${placeholders.length} helper placeholders`);
  return Boolean(window.uiLoader);
}

async function fetchServiceStatus() {
  try {
    const response = await fetch(routeUrl('/api/status'), {
      ...defaultFetchOptions,
      cache: 'no-store',
    });
    if (!response.ok) {
      throw new Error(`Status request failed with ${response.status}`);
    }
    const payload = await response.json();
    return normalizeServiceStatus(payload);
  } catch (error) {
    console.warn('[MapServer] Falling back to static status payload:', error.message);
    return normalizeServiceStatus({
      ...serviceStatus,
      message: `Live status fetch failed: ${error.message}`,
      warnings: ['Homepage is using fallback status data.'],
    });
  }
}

function renderStatusFieldset(createFieldset, status) {
  const container = document.getElementById('status-fieldset');
  if (!container) {
    return null;
  }
  return createFieldset(container, buildStatusFieldsetData(status));
}

function renderSecurityFieldset(createFieldset, status) {
  const container = document.getElementById('security-fieldset');
  if (!container) {
    return;
  }
  createFieldset(container, buildSecurityFieldsetData(status));
}

function renderIntegrationFieldset(createFieldset, status) {
  const container = document.getElementById('integration-fieldset');
  if (!container) {
    return;
  }
  createFieldset(container, buildIntegrationFieldsetData(status));
}

function renderOperationsFieldset(createFieldset, status) {
  const container = document.getElementById('operations-fieldset');
  if (!container) {
    return;
  }
  createFieldset(container, buildOperationsFieldsetData(status));
}

function renderEndpointsGrid(createGrid, dependencies) {
  const container = document.getElementById('endpoints-grid');
  if (!container) {
    return null;
  }

  return createGrid(container, endpointReference, {
    mode: 'local',
    columns: [
      { key: 'method', label: 'Method', width: '72px' },
      { key: 'path', label: 'Path', width: '250px' },
      { key: 'mimeType', label: 'MIME Type', width: '180px' },
      {
        key: 'cached',
        label: 'Cache',
        width: '120px',
        renderCell({ row }) {
          return createBadge(row.cached ? row.cacheBehavior : 'BYPASS', row.cached ? 'good' : 'neutral');
        },
      },
      { key: 'description', label: 'Description', width: '320px' },
      {
        key: 'actions',
        label: 'Actions',
        width: '210px',
        sortable: false,
        align: 'right',
        renderCell({ row }) {
          return createEndpointActionCell(row, dependencies);
        },
      },
    ],
    enableSort: true,
    enableSearch: true,
    enablePagination: false,
  });
}

function renderDeploymentTabs(createTabs) {
  const container = document.getElementById('deployment-tabs');
  if (!container) {
    return;
  }

  createTabs(container, {
    activeId: 'apache',
    tabs: [
      {
        id: 'apache',
        label: 'WAMP/Apache',
        render(panel) {
          clearChildren(panel);
          panel.append(
            createParagraph('Use the local MapServer directory as the document root or virtual-host target.'),
            createList([
              ['Enable ', createCode('mod_rewrite'), ' and keep ', createCode('AllowOverride All'), ' enabled.'],
              ['Point traffic at ', createCode(appBaseUrl), ' or a dedicated virtual host such as ', createCode('https://mapserver.pbb.ph'), '.'],
              ['Health checks should target ', createCode(absolutePublicUrl('/tiles/health')), '.'],
            ]),
          );
        },
      },
      {
        id: 'docker',
        label: 'Docker',
        render(panel) {
          clearChildren(panel);
          panel.append(
            createParagraph('Container deployments should persist cache and inject environment variables.'),
            createCodeBlock(`docker run -d -p 8080:80 \\
  -v $(pwd)/storage:/app/storage \\
  --env-file .env \\
  mapserver:latest`),
            createParagraph('Expose ', createCode('/tiles/health'), ' as the liveness/readiness endpoint.'),
          );
        },
      },
      {
        id: 'kubernetes',
        label: 'Kubernetes',
        render(panel) {
          clearChildren(panel);
          panel.append(
            createParagraph(
              'Use ConfigMaps or Secrets for environment variables and a persistent volume for ',
              createCode('storage/tiles'),
              '.',
            ),
            createList([
              ['Readiness probe: ', createCode(`GET ${publicPath('/tiles/health')}`)],
              'Persist cache with a writable volume mount.',
              'Keep purge credentials in a Secret, not the manifest body.',
            ]),
          );
        },
      },
    ],
  });
}

function initializeDiagnostics(createFormModal, createGrid, toast) {
  const diagnosticsBtn = document.getElementById('run-diagnostics-btn');
  const diagnosticsOutput = document.getElementById('diagnostics-placeholder');
  if (!diagnosticsBtn || !diagnosticsOutput) {
    return;
  }

  let diagnosticsGrid = null;
  diagnosticsBtn.style.display = 'inline-flex';

  diagnosticsBtn.addEventListener('click', () => {
    const modal = createFormModal({
      title: 'Run Diagnostics',
      size: 'md',
      submitLabel: 'Run Tests',
      cancelLabel: 'Close',
      closeOnSuccess: false,
      rows: [
        [
          {
            type: 'text',
            content: 'Select one or more endpoints. Results will render below the diagnostics section with status, latency, cache, and preview data.',
            span: 2,
          },
        ],
        [
          { type: 'checkbox', name: 'diag_health', label: '/tiles/health' },
          { type: 'checkbox', name: 'diag_status', label: '/api/status' },
        ],
        [
          { type: 'checkbox', name: 'diag_raster', label: '/tiles/raster/0/0/0.png' },
          { type: 'checkbox', name: 'diag_vector', label: '/tiles/vector/0/0/0.pbf' },
        ],
        [
          { type: 'checkbox', name: 'diag_terrain', label: '/tiles/terrain/0/0/0.png' },
          { type: 'checkbox', name: 'diag_glyphs', label: '/tiles/glyphs/Open%20Sans%20Regular/0-255.pbf' },
        ],
        [
          { type: 'checkbox', name: 'diag_poi', label: '/tiles/poi/0/0/0.pbf' },
          { type: 'display', name: 'timeout_hint', label: 'Timeout', value: '8 seconds per endpoint' },
        ],
      ],
      initialValues: {
        diag_health: true,
        diag_status: true,
        diag_raster: true,
      },
      async onSubmit(values, ctx) {
        const selectedEndpoints = endpointReference.filter((endpoint) => values[`diag_${endpoint.id}`]);
        if (!selectedEndpoints.length) {
          ctx.setFormError('Select at least one endpoint before running diagnostics.');
          return false;
        }

        try {
          ctx.clearFormError();
          toast.show(`Running ${selectedEndpoints.length} diagnostics...`, { type: 'info', title: 'Diagnostics' });
          const { summary, gridHost } = ensureDiagnosticsHosts(diagnosticsOutput);
          renderDiagnosticsSummary(summary, 'Running diagnostics...', 'info');

          const results = [];
          for (const endpoint of selectedEndpoints) {
            const result = await runEndpointDiagnostic(endpoint);
            results.push(result);
            toast.show(
              result.ok
                ? `${endpoint.id}: OK (${result.latencyMs} ms)`
                : `${endpoint.id}: ${result.status}`,
              { type: result.ok ? 'good' : 'error', title: 'Diagnostics' }
            );
          }

          const passed = results.filter((result) => result.ok).length;
          renderDiagnosticsSummary(
            summary,
            `Diagnostics complete: ${passed}/${results.length} succeeded.`,
            passed === results.length ? 'good' : 'warning'
          );

          if (!diagnosticsGrid) {
            diagnosticsGrid = createGrid(gridHost, results, {
              chrome: false,
              mode: 'local',
              enableSort: true,
              enableSearch: false,
              enablePagination: false,
              columns: [
                { key: 'endpoint', label: 'Endpoint', width: '250px' },
                {
                  key: 'status',
                  label: 'Status',
                  width: '110px',
                  renderCell({ row }) {
                    return createBadge(String(row.status), getResultTone(row));
                  },
                },
                { key: 'latencyMs', label: 'Latency (ms)', width: '110px' },
                {
                  key: 'cacheStatus',
                  label: 'X-Cache',
                  width: '100px',
                  renderCell({ row }) {
                    const tone = row.cacheStatus === 'HIT'
                      ? 'good'
                      : row.cacheStatus === 'MISS'
                        ? 'warning'
                        : 'neutral';
                    return createBadge(row.cacheStatus, tone);
                  },
                },
                { key: 'contentType', label: 'Content-Type', width: '180px' },
                { key: 'preview', label: 'Preview', width: '320px' },
              ],
            });
          } else {
            diagnosticsGrid.setRows(results);
          }
        } catch (error) {
          ctx.setFormError(`Diagnostics failed: ${error.message}`);
          toast.show(`Diagnostics failed: ${error.message}`, { type: 'error', title: 'Diagnostics' });
        }

        return false;
      },
    });

    modal.open();
  });
}

async function initializeHomepage() {
  console.log('[MapServer Homepage] Initializing...');
  const phase1Ready = await initializePhase1();
  if (!phase1Ready) {
    console.error('[MapServer] Cannot continue without uiLoader.');
    return;
  }

  const status = await fetchServiceStatus();
  const [
    createFieldset,
    createGrid,
    createTabs,
    createFormModal,
    createToastStack,
  ] = await Promise.all([
    uiLoader.get('ui.fieldset'),
    uiLoader.get('ui.grid'),
    uiLoader.get('ui.tabs'),
    uiLoader.get('ui.form.modal'),
    uiLoader.get('ui.toast'),
  ]);

  const toast = createToastStack({ position: 'bottom-left', max: 5 });

  const statusFieldset = renderStatusFieldset(createFieldset, status);
  renderSecurityFieldset(createFieldset, status);
  renderIntegrationFieldset(createFieldset, status);
  renderOperationsFieldset(createFieldset, status);
  renderDeploymentTabs(createTabs);
  renderEndpointsGrid(createGrid, { createFormModal, toast });
  initializeDiagnostics(createFormModal, createGrid, toast);

  if (statusFieldset?.update) {
    window.setInterval(async () => {
      try {
        const latestStatus = await fetchServiceStatus();
        statusFieldset.update(buildStatusFieldsetData(latestStatus));
      } catch (error) {
        console.warn('[MapServer] Status refresh failed:', error.message);
      }
    }, 60000);
  }

  console.log('[MapServer] Homepage initialized successfully');
}

document.addEventListener('DOMContentLoaded', async () => {
  try {
    await initializeHomepage();
  } catch (error) {
    console.error('[MapServer] Homepage initialization error:', error);
  }
});

export {
  endpointReference,
  serviceStatus,
  routeUrl,
  absolutePublicUrl,
  buildCurlCommand,
};
