const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await (await browser.newContext()).newPage();
  await page.goto('https://altafawwuq-one.vercel.app/login', { waitUntil: 'domcontentloaded' });
  await page.getByRole('textbox', { name: 'البريد الإلكتروني أو رقم الهاتف *' }).fill('admin@altafawwuq.com');
  await page.locator('input[type=password]').fill('password');
  await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
  await page.waitForURL('**/admin/dashboard', { timeout: 15000 });
  await page.goto('https://altafawwuq-one.vercel.app/admin/payments?status=pending_verification', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);
  const body = await page.content();
  const m = body.match(/(\d+)\s*عملية دفع/);
  const has87 = body.includes('طالب تجريبي');
  console.log('PENDING COUNT:', m ? m[1] : '?', '| contains student@ (87):', has87);
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
