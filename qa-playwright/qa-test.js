// Browser QA: read-only console/network audit with opt-in upload coverage.
// Always supply an approved local or staging target through QA_BASE_URL.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = String(process.env.QA_BASE_URL || '').replace(/\/+$/, '');
const STUDENT_EMAIL = process.env.QA_STUDENT_EMAIL || '';
const STUDENT_PASSWORD = process.env.QA_STUDENT_PASSWORD || '';
const ALLOW_MUTATIONS = process.env.QA_ALLOW_MUTATIONS === 'true';
const RESULTS_PATH = process.env.QA_RESULTS_PATH || path.join(__dirname, 'round5-results.json');
const findings = { console: [], network: [], upload: {}, steps: [] };
const log = (message) => {
  console.log(message);
  findings.steps.push(message);
};

function assertQaConfiguration() {
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

  if (!STUDENT_EMAIL || !STUDENT_PASSWORD) {
    throw new Error('QA_STUDENT_EMAIL and QA_STUDENT_PASSWORD are required; credentials are never stored in this script.');
  }
}

function attachAudits(page, label) {
  page.on('console', (message) => {
    if (message.type() === 'error' || message.type() === 'warning') {
      findings.console.push({ page: label, type: message.type(), text: message.text().slice(0, 300) });
    }
  });
  page.on('pageerror', (error) => {
    findings.console.push({ page: label, type: 'pageerror', text: String(error).slice(0, 300) });
  });
  page.on('response', (response) => {
    if (response.status() >= 400) {
      findings.network.push({ page: label, status: response.status(), url: response.url().slice(0, 200) });
    }
  });
}

function createQaReceipt() {
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64'
  );
  const receiptPath = path.join(__dirname, 'qa_receipt.png');
  fs.writeFileSync(receiptPath, png);
  return receiptPath;
}

(async () => {
  assertQaConfiguration();

  let browser;
  try {
    browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const page = await context.newPage();
    attachAudits(page, 'session');

    await page.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#tab-password').click();
    await page.locator('#login_field').fill(STUDENT_EMAIL);
    await page.locator('#password').fill(STUDENT_PASSWORD);
    await page.locator('#login-submit-btn').click();
    await page.waitForURL('**/dashboard', { timeout: 15000 });
    log('LOGIN student: OK');

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

    for (const [name, route] of pages) {
      try {
        await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(1500);
        log('VISIT ' + name + ': OK');
      } catch (error) {
        log('VISIT ' + name + ': FAIL ' + String(error).slice(0, 120));
      }
    }

    if (!ALLOW_MUTATIONS) {
      findings.upload = {
        skipped: true,
        reason: 'Set QA_ALLOW_MUTATIONS=true with an approved target to run upload coverage.',
      };
      log('UPLOAD test skipped: mutation guard is disabled');
    } else {
      const checkoutId = process.env.QA_CHECKOUT_ID || '';
      const transferPhone = process.env.QA_TRANSFER_PHONE || '';
      if (!checkoutId || !transferPhone) {
        throw new Error('QA_CHECKOUT_ID and QA_TRANSFER_PHONE are required when upload coverage is enabled.');
      }

      try {
        const receiptPath = createQaReceipt();
        await page.goto(BASE + '/checkout/' + checkoutId, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(1500);
        const phone = page.locator('input[type="tel"], input[name="phone"]').first();
        if (await phone.count()) {
          await phone.fill(transferPhone);
        }

        const fileInput = page.locator('input[type="file"]');
        findings.upload.fileInputsFound = await fileInput.count();
        if (findings.upload.fileInputsFound > 0) {
          await fileInput.first().setInputFiles(receiptPath);
          await page.locator('button[type="submit"]').last().click();
          await page.waitForTimeout(4000);
          findings.upload.submitted = true;
          findings.upload.pageUrlAfter = page.url();
        }
        log('UPLOAD test done: ' + JSON.stringify(findings.upload));
      } catch (error) {
        findings.upload.error = String(error).slice(0, 300);
        log('UPLOAD test ERROR: ' + findings.upload.error);
      }
    }

    try {
      await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
      let fired = false;
      page.on('request', (request) => {
        if (request.url().includes('search-autocomplete')) {
          fired = true;
        }
      });
      const search = page.locator('input[type="search"]').first();
      if (await search.count()) {
        await search.fill('math');
        await page.waitForTimeout(3000);
      }
      findings.headerSearchAutocompleteFired = fired;
      log('HEADER SEARCH autocomplete fired: ' + fired);
    } catch (error) {
      log('HEADER SEARCH check error: ' + String(error).slice(0, 120));
    }

    const dedupedNetwork = [];
    const seen = new Set();
    for (const networkFailure of findings.network) {
      const key = networkFailure.status + networkFailure.url;
      if (!seen.has(key)) {
        seen.add(key);
        dedupedNetwork.push(networkFailure);
      }
    }

    console.log('\n================ BROWSER QA RESULTS ================');
    console.log('CONSOLE ISSUES (' + findings.console.length + '):');
    findings.console.slice(0, 30).forEach((issue) => console.log('  [' + issue.type + '] ' + issue.page + ': ' + issue.text));
    console.log('NETWORK FAILURES (dedup ' + dedupedNetwork.length + '):');
    dedupedNetwork.slice(0, 40).forEach((failure) => console.log('  ' + failure.status + ' ' + failure.url));
    console.log('UPLOAD: ' + JSON.stringify(findings.upload, null, 1));
    console.log('AUTOCOMPLETE FIRED: ' + findings.headerSearchAutocompleteFired);
    fs.writeFileSync(RESULTS_PATH, JSON.stringify(findings, null, 2));
    console.log('Saved: ' + RESULTS_PATH);
  } finally {
    if (browser) {
      await browser.close();
    }
  }
})().catch((error) => {
  console.error('FATAL', error);
  process.exit(1);
});
