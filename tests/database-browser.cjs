const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.F125_PLAYWRIGHT || 'playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.F125_TEST_BROWSER === 'chromium' ? {} : { channel: 'msedge' }) });
  try {
    const page = await browser.newPage();
    await page.route('**/api.php?action=update-status', route => route.fulfill({ json: { known: true, newer: false } }));
    await page.goto(process.env.F125_TEST_URL + 'database.php');
    await page.getByRole('button', { name: 'Actualizar base de datos', exact: true }).waitFor();
    assert.match(await page.locator('main').innerText(), /24 circuitos oficiales/);
    assert.equal(await page.locator('style,[style]').count(), 0);
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: 950 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      if (process.env.F125_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.F125_SCREENSHOTS, `database-${width}.png`), fullPage: true });
    }
    await page.getByRole('button', { name: 'Actualizar base de datos', exact: true }).click();
    await page.getByRole('status').filter({ hasText: 'Base de datos actualizada.' }).waitFor();
    assert.equal(await page.locator('main ol li').count(), 5);
    assert.equal(await page.getByRole('button', { name: 'Actualizar base de datos', exact: true }).count(), 0);
    await page.reload();
    assert.equal(await page.locator('main ol li').count(), 5);
    console.log('OK: migration page GET, valid POST, redirect and completed state (storage fixture).');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
