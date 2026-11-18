const { chromium } = require('../../work/node_modules/playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
(async () => {
  const output = path.join(__dirname, '../test-output'); fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  let count = 0;
  for (const width of [390, 768, 1440]) {
    const page = await browser.newPage({ viewport: { width, height: 960 } });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    for (const view of ['home', 'security', 'warehouse', 'exchange', 'admin']) {
      const response = await page.goto(`http://127.0.0.1:8080/preview?page=${view}`, { waitUntil: 'networkidle' });
      assert.equal(response.status(), 200);
      assert.equal(await page.locator('[data-portal-root]').count(), 1);
      assert.equal(await page.locator('body').innerText().then(s => /Fatal error|Parse error|Warning:/.test(s)), false);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
      assert.equal(overflow, false, `Overflow: ${view} at ${width}`);
      await page.screenshot({ path: path.join(output, `${view}-${width}.png`), fullPage: true }); count++;
    }
    await page.goto('http://127.0.0.1:8080/preview?page=exchange&tab=wallet', { waitUntil: 'networkidle' });
    await page.locator('[data-withdraw-amount]').fill('100');
    assert.match(await page.locator('[data-withdraw-preview]').innerText(), /72\.00 USDT/);
    await page.locator('[data-transfer-amount]').fill('10');
    assert.match(await page.locator('[data-transfer-preview]').innerText(), /10\.00 CoL/);
    await page.goto('http://127.0.0.1:8080/preview?page=home');
    await page.locator('[data-portal-teleport]:enabled').click();
    assert.equal(await page.locator('dialog[open]').count(), 1);
    await page.locator('dialog [name="pin"]').fill('123456');
    let attempts = 0, keys = [];
    await page.route('**/preview?page=request', async route => {
      attempts++; const body = route.request().postData();
      keys.push(body.match(/name="request_key"\r\n\r\n([^\r]+)/)[1]);
      await route.fulfill({ status: attempts === 1 ? 400 : 200, contentType: 'application/json', body: JSON.stringify(attempts === 1 ? { ok: false, error: 'Попробуйте снова' } : { ok: true, result: {} }) });
    });
    page.on('dialog', d => d.accept());
    await page.locator('dialog [type="submit"]').click();
    await page.waitForFunction(() => document.querySelector('[data-portal-status]').textContent === 'Попробуйте снова');
    assert.equal(await page.locator('dialog [type="submit"]').isEnabled(), true);
    await page.locator('dialog [type="submit"]').click();
    await page.waitForTimeout(200);
    assert.equal(attempts, 2); assert.equal(keys[0], keys[1]);
    assert.deepEqual(errors, []); await page.close();
  }
  await browser.close(); console.log(`PASS: ${count} rendered pages, widths 390/768/1440, no overflow, withdrawal/conversion previews, modal, API failure retry key`);
})().catch(error => { console.error(error); process.exit(1); });
