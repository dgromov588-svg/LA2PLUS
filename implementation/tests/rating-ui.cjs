const { chromium } = require('../../work/node_modules/playwright');
const assert = require('assert/strict');
const path = require('path');
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  for (const width of [390, 768, 1440]) {
    const page = await browser.newPage({ viewport: { width, height: 960 } });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.goto('http://127.0.0.1:8080/preview-rating#tab-top_castle', { waitUntil: 'networkidle' });
    assert.equal(await page.locator('[data-rating="castles"]').getAttribute('aria-selected'), 'true');
    await page.locator('[data-rating-table="castles"]').getByText('Asterion', { exact: false }).waitFor();
    assert.equal(await page.locator('[data-rating-table="castles"] img').evaluate(i => i.naturalWidth > 0), true);
    for (const tab of ['pvp','pk','exp','clans','clanpvp','alliances','castles']) {
      await page.locator(`[data-rating="${tab}"]`).click();
      assert.equal(await page.locator(`[data-rating-table="${tab}"]`).isVisible(), true);
      assert.equal(await page.locator('.rating__table:not(.hidden)').count(), 1);
      assert.ok(await page.locator(`[data-rating-table="${tab}"] tbody tr`).count() >= 1);
    }
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await page.screenshot({ path: path.join(__dirname, `../test-output/rating-${width}.png`), fullPage: true });
    await page.locator('[data-rating="castles"]').focus();
    await page.keyboard.press('Home');
    assert.equal(await page.locator('[data-rating="pvp"]').getAttribute('aria-selected'), 'true');
    assert.deepEqual(errors, []); await page.close();
  }
  await browser.close(); console.log('PASS: seven tabs, castle deep link/images, keyboard navigation, updated timestamp and responsive table');
})().catch(e => { console.error(e); process.exit(1); });
