// Read-only admin payment verification. Supply all target and credentials explicitly.
const { chromium } = require('playwright');

const BASE = String(process.env.QA_BASE_URL || '').replace(/\/+$/, '');
const ADMIN_EMAIL = process.env.QA_ADMIN_EMAIL || '';
const ADMIN_PASSWORD = process.env.QA_ADMIN_PASSWORD || '';

(async () => {
  if (!BASE) {
    throw new Error('QA_BASE_URL is required; refusing an implicit deployment target.');
  }

  const target = new URL(BASE);
  if (!['http:', 'https:'].includes(target.protocol)) {
    throw new Error('QA_BASE_URL must use http or https.');
  }

  if (target.hostname === 'altafawwuq-one.vercel.app' && process.env.QA_ALLOW_PRODUCTION !== 'true') {
    throw new Error('Refusing the known production host. Set QA_ALLOW_PRODUCTION=true only with explicit approval.');
  }

  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    throw new Error('QA_ADMIN_EMAIL and QA_ADMIN_PASSWORD are required; credentials are never stored in this script.');
  }

  const browser = await chromium.launch({ headless: true });
  try {
    const page = await (await browser.newContext()).newPage();
    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /كلمة المرور/ }).click();
    await page.locator('#login_field').fill(ADMIN_EMAIL);
    await page.locator('#password').fill(ADMIN_PASSWORD);
    await page.locator('#login-submit-btn').click();
    await page.waitForURL('**/admin/dashboard', { timeout: 15000 });
    await page.goto(BASE + '/admin/payments?status=pending_verification', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2500);
    const body = await page.content();
    console.log('PENDING PAYMENT PAGE LOADED:', body.length > 0);
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error('FATAL', error);
  process.exit(1);
});
