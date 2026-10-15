// Read-only role route smoke. Supply an approved target and test credentials.
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = String(process.env.QA_BASE_URL || '').replace(/\/+$/, '');
const ROLE = process.env.QA_ROLE || 'student';
const EMAIL = process.env.QA_LOGIN_EMAIL || '';
const PASSWORD = process.env.QA_LOGIN_PASSWORD || '';
const EXPECTED_PATH = process.env.QA_EXPECTED_PATH || {
  admin: '/admin/dashboard',
  teacher: '/teacher/dashboard',
  student: '/dashboard',
  parent: '/parent/dashboard',
}[ROLE] || '/dashboard';
const DEFAULT_ROUTES = {
  admin: ['/admin/dashboard', '/admin/payments?status=pending_verification'],
  teacher: [
    '/teacher/dashboard',
    '/teacher/my-groups',
    '/teacher/my-students',
    '/teacher/groups/1',
    '/teacher/groups/1/students',
    '/teacher/groups/1/roster',
    '/teacher/groups/1/materials',
    '/teacher/groups/1/worksheets',
    '/teacher/gradebook',
    '/teacher/analytics',
    '/teacher/announcements',
    '/teacher/resource-library',
    '/teacher/teaching-schedule',
    '/teacher/live-sessions',
    '/teacher/question-bank',
  ],
  student: ['/dashboard', '/my-classes', '/my-grade', '/my-schedule', '/my-assignments', '/my-assessments', '/my-resources'],
  parent: ['/parent/dashboard'],
};
const ROUTES = (process.env.QA_ROUTES || '').split(',').map((route) => route.trim()).filter(Boolean);
const RESULTS_PATH = process.env.QA_RESULTS_PATH || '';

function assertConfiguration() {
  if (!BASE || !EMAIL || !PASSWORD) {
    throw new Error('QA_BASE_URL, QA_LOGIN_EMAIL, and QA_LOGIN_PASSWORD are required.');
  }

  const target = new URL(BASE);
  if (!['http:', 'https:'].includes(target.protocol)) {
    throw new Error('QA_BASE_URL must use http or https.');
  }

  if (target.hostname === 'altafawwuq-one.vercel.app' && process.env.QA_ALLOW_PRODUCTION !== 'true') {
    throw new Error('Refusing the known production host. Set QA_ALLOW_PRODUCTION=true only with explicit approval.');
  }
}

(async () => {
  assertConfiguration();
  const browser = await chromium.launch({ headless: true });
  const issues = [];
  const results = [];

  try {
    const page = await (await browser.newContext({ viewport: { width: 1366, height: 768 } })).newPage();
    page.on('console', (message) => {
      if (['error', 'warning'].includes(message.type())) {
        issues.push('console:' + message.type() + ':' + message.text());
      }
    });
    page.on('pageerror', (error) => issues.push('pageerror:' + String(error)));
    page.on('response', (response) => {
      if (response.status() >= 400) {
        issues.push('http:' + response.status() + ':' + response.url());
      }
    });

    await page.goto(BASE + '/login', { waitUntil: 'networkidle' });
    await page.locator('button').nth(2).click();
    await page.locator('#login_field').fill(EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.locator('#login-submit-btn').click();
    await page.waitForURL('**' + EXPECTED_PATH, { timeout: 15000 });

    const routes = ROUTES.length ? ROUTES : (DEFAULT_ROUTES[ROLE] || []);
    if (!routes.length) {
      throw new Error('No routes configured for QA_ROLE=' + ROLE + '.');
    }

    for (const route of routes) {
      const response = await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 20000 });
      results.push({ route, status: response && response.status(), url: page.url() });
    }
  } finally {
    await browser.close();
  }

  const report = { role: ROLE, routes: results, issues };
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
