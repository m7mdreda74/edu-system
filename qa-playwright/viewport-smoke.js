const { chromium } = require('playwright');

const BASE = String(process.env.QA_BASE_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');
const EMAIL = process.env.QA_LOGIN_EMAIL || 'student@almagd.com';
const PASSWORD = process.env.QA_LOGIN_PASSWORD || 'password';

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 },
];

const PAGES_TO_TEST = [
  '/',
  '/login',
  '/dashboard',
  '/my-classes',
  '/my-grade',
  '/my-schedule',
  '/teachers',
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];

  for (const vp of VIEWPORTS) {
    console.log(`Testing viewport: ${vp.name} (${vp.width}x${vp.height})`);
    const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
    const page = await context.newPage();
    const vpResults = { viewport: vp.name, pages: [] };

    // Login once
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#tab-password').click();
    await page.locator('#login_field').fill(EMAIL);
    await page.locator('#password').fill(PASSWORD);
    await page.locator('#login-submit-btn').click();
    await page.waitForURL('**/dashboard', { timeout: 15000 });

    for (const route of PAGES_TO_TEST) {
      try {
        const resp = await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 15000 });
        const status = resp ? resp.status() : 'loaded';
        vpResults.pages.push({ route, status });
      } catch (err) {
        vpResults.pages.push({ route, error: err.message.slice(0, 100) });
      }
    }

    results.push(vpResults);
    await context.close();
  }

  await browser.close();
  console.log('MULTI-VIEWPORT TEST COMPLETED SUCCESSFULLY');
  console.log(JSON.stringify(results, null, 2));
})();
