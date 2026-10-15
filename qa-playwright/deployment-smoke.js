// Read-only deployment parity smoke. Supply an explicitly approved target.
const fs = require('fs');

const BASE = String(process.env.QA_BASE_URL || '').replace(/\/+$/, '');
const RESULTS_PATH = process.env.QA_RESULTS_PATH || '';
const REQUEST_TIMEOUT_MS = 30000;
const CHECKS = [
  { path: '/', expectedStatus: 200, kind: 'html' },
  { path: '/health', expectedStatus: 200, kind: 'health' },
  { path: '/login', expectedStatus: 200, kind: 'html' },
  { path: '/register', expectedStatus: 200, kind: 'html' },
  { path: '/api/search-autocomplete?q=%D8%B1%D9%8A%D8%A7%D8%B6%D9%8A%D8%A7%D8%AA', expectedStatus: 200, kind: 'json' },
];

function assertConfiguration() {
  if (!BASE) {
    throw new Error('QA_BASE_URL is required.');
  }

  const target = new URL(BASE);
  if (!['http:', 'https:'].includes(target.protocol)) {
    throw new Error('QA_BASE_URL must use http or https.');
  }

  if (target.hostname === 'altafawwuq-one.vercel.app' && process.env.QA_ALLOW_PRODUCTION !== 'true') {
    throw new Error('Refusing the known production host. Set QA_ALLOW_PRODUCTION=true only with explicit approval.');
  }
}

function extractTitle(body) {
  const match = body.match(/<title[^>]*>(.*?)<\/title>/is);

  return match ? match[1].replace(/\s+/g, ' ').trim() : '';
}

async function fetchCheck(check) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  const url = BASE + check.path;

  try {
    const response = await fetch(url, {
      redirect: 'manual',
      signal: controller.signal,
      headers: { Accept: check.kind === 'json' || check.kind === 'health' ? 'application/json' : 'text/html' },
    });
    const body = await response.text();
    const result = {
      path: check.path,
      status: response.status,
      contentType: response.headers.get('content-type') || '',
    };
    const issues = [];

    if (response.status !== check.expectedStatus) {
      issues.push(`expected HTTP ${check.expectedStatus}, received ${response.status}`);
    }

    if (check.kind === 'html') {
      result.title = extractTitle(body);
    }

    if (check.kind === 'health' && response.status === 200) {
      try {
        const payload = JSON.parse(body);
        result.health = payload;
        for (const dependency of ['database', 'cache']) {
          if (payload.checks?.[dependency] !== 'ok') {
            issues.push(`health ${dependency} is not ok`);
          }
        }
      } catch {
        issues.push('health response is not valid JSON');
      }
    }

    if (check.kind === 'json' && response.status === 200) {
      try {
        const payload = JSON.parse(body);
        result.jsonType = Array.isArray(payload) ? 'array' : typeof payload;
      } catch {
        issues.push('JSON endpoint returned invalid JSON');
      }
    }

    return { result, issues };
  } catch (error) {
    return {
      result: { path: check.path, status: null, contentType: '' },
      issues: [String(error?.name === 'AbortError' ? 'request timed out' : error)],
    };
  } finally {
    clearTimeout(timeout);
  }
}

(async () => {
  assertConfiguration();
  const results = [];
  const issues = [];

  for (const check of CHECKS) {
    const outcome = await fetchCheck(check);
    results.push(outcome.result);
    issues.push(...outcome.issues.map((issue) => `${check.path}: ${issue}`));
  }

  const report = { baseUrl: BASE, results, issues };
  console.log(JSON.stringify(report, null, 2));

  if (RESULTS_PATH) {
    fs.writeFileSync(RESULTS_PATH, JSON.stringify(report, null, 2));
  }

  if (issues.length) {
    process.exit(1);
  }
})().catch((error) => {
  console.error('FATAL', error);
  process.exit(1);
});
