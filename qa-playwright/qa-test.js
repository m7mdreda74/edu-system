// QA Round 5 — File upload + Console/Network audit
// Target: https://altafawwuq-one.vercel.app
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'https://altafawwuq-one.vercel.app';
const findings = { console: [], network: [], upload: {}, steps: [] };
const log = (m) => { console.log(m); findings.steps.push(m); };

async function attachAudits(page, label) {
  page.on('console', (msg) => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      findings.console.push({ page: label, type: msg.type(), text: msg.text().slice(0, 300) });
    }
  });
  page.on('pageerror', (err) => {
    findings.console.push({ page: label, type: 'pageerror', text: String(err).slice(0, 300) });
  });
  page.on('response', (res) => {
    if (res.status() >= 400) {
      findings.network.push({ page: label, status: res.status(), url: res.url().slice(0, 200) });
    }
  });
}

(async () => {
  // generate a small fake receipt PNG (1x1 red pixel)
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64'
  );
  const receiptPath = path.join(__dirname, 'qa_receipt.png');
  fs.writeFileSync(receiptPath, png);

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1366, height: 768 } });
  const page = await ctx.newPage();
  attachAudits(page, 'session');

  // ---- LOGIN as student ----
  await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await page.getByRole('textbox', { name: 'البريد الإلكتروني أو رقم الهاتف *' }).fill('student@altafawwuq.com');
  await page.locator('input[type=password]').fill('password');
  await page.getByRole('button', { name: 'تسجيل الدخول' }).click();
  await page.waitForURL('**/dashboard', { timeout: 15000 });
  log('LOGIN student: OK');

  // ---- CONSOLE/NETWORK AUDIT across student + public pages ----
  const pages = [
    ['home', '/'],
    ['student-dashboard', '/dashboard'],
    ['my-classes', '/my-classes'],
    ['my-grade', '/my-grade'],
    ['my-schedule', '/my-schedule'],
    ['chat', '/chat'],
    ['profile', '/profile'],
    ['public-teachers', '/teachers'],
    ['teacher-profile', '/teachers/3'],
    ['grade', '/grades/grade_10'],
    ['contact', '/contact'],
  ];
  for (const [name, p] of pages) {
    try {
      await page.goto(BASE + p, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(1500);
      log(`VISIT ${name}: OK`);
    } catch (e) {
      log(`VISIT ${name}: FAIL ${String(e).slice(0, 120)}`);
    }
  }

  // ---- FILE UPLOAD: receipt on pending checkout 87 ----
  try {
    await page.goto(BASE + '/checkout/87', { waitUntil: 'domcontentloaded', timeout: 20000 });
    await page.waitForTimeout(1500);
    const phone = page.getByRole('textbox', { name: 'رقم الهاتف الذي حوّلت منه' });
    if (await phone.count()) {
      await phone.fill('01099670724');
    }
    const fileInput = page.locator('input[type=file]');
    const n = await fileInput.count();
    findings.upload.fileInputsFound = n;
    if (n > 0) {
      await fileInput.first().setInputFiles(receiptPath);
      await page.waitForTimeout(800);
      const submit = page.getByRole('button', { name: 'إرسال إثبات التحويل' });
      await submit.click();
      await page.waitForTimeout(4000);
      const body = await page.content();
      findings.upload.submitted = true;
      findings.upload.successMessage = /تم إرسال|بانتظار المراجعة|قيد التحقق|تم رفع/.test(body);
      findings.upload.stillOnCheckout = page.url().includes('/checkout/87');
      findings.upload.pageUrlAfter = page.url();
      // check my-classes for pending verification state
      await page.goto(BASE + '/my-classes', { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(1500);
      const body2 = await page.content();
      findings.upload.pendingVisible = /قيد التحقق|بانتظار المراجعة|إثبات/.test(body2);
    }
    log('UPLOAD test done: ' + JSON.stringify(findings.upload));
  } catch (e) {
    findings.upload.error = String(e).slice(0, 300);
    log('UPLOAD test ERROR: ' + findings.upload.error);
  }

  // ---- HEADER SEARCH network check (does autocomplete fire?) ----
  try {
    await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
    let fired = false;
    page.on('request', (r) => { if (r.url().includes('search-autocomplete')) fired = true; });
    await page.getByRole('textbox', { name: 'ابحث عن معلم أو مادة...' }).fill('رياضيات');
    await page.waitForTimeout(3000);
    findings.headerSearchAutocompleteFired = fired;
    log('HEADER SEARCH autocomplete fired: ' + fired);
  } catch (e) {
    log('HEADER SEARCH check error: ' + String(e).slice(0, 120));
  }

  await browser.close();

  // ---- REPORT ----
  const dedupNet = [];
  const seen = new Set();
  for (const n of findings.network) {
    const k = n.status + n.url;
    if (!seen.has(k)) { seen.add(k); dedupNet.push(n); }
  }
  console.log('\n================ ROUND 5 RESULTS ================');
  console.log('CONSOLE ISSUES (' + findings.console.length + '):');
  findings.console.slice(0, 30).forEach((c) => console.log('  [' + c.type + '] ' + c.page + ': ' + c.text));
  console.log('NETWORK FAILURES (dedup ' + dedupNet.length + '):');
  dedupNet.slice(0, 40).forEach((n) => console.log('  ' + n.status + ' ' + n.url));
  console.log('UPLOAD: ' + JSON.stringify(findings.upload, null, 1));
  console.log('AUTOCOMPLETE FIRED: ' + findings.headerSearchAutocompleteFired);
  fs.writeFileSync(path.join(__dirname, 'round5-results.json'), JSON.stringify(findings, null, 2));
  console.log('Saved: round5-results.json');
})().catch((e) => { console.error('FATAL', e); process.exit(1); });
