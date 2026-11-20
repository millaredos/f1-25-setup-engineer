/* Browser checks against an isolated installation without a database. */
const { chromium } = require(process.env.F125_PLAYWRIGHT || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

(async () => {
  const browser = await chromium.launch({headless:true, ...(process.env.F125_TEST_BROWSER === 'chromium' ? {} : {channel:'msedge'})});
  const base = process.env.F125_TEST_URL || 'http://127.0.0.1:8125/';
  try {
    const context = await browser.newContext({viewport:{width:1440,height:1000},acceptDownloads:true});
    assert.equal((await context.request.get(new URL('install.php',base).href,{maxRedirects:0})).status(),302);
    const page = await context.newPage(); const errors = [];
    page.on('pageerror',e=>errors.push(e.message)); page.on('dialog',d=>d.accept());
    await page.route('**/api.php?action=update-status',r=>r.fulfill({json:{known:true,newer:false}}));
    const response = await page.goto(base);
    assert.match(response.headers()['content-security-policy'],/style-src 'self'/);
    await page.locator('#settings input').first().waitFor({state:'attached'});
    assert.equal(await page.locator('#settings input').count(),20);
    assert.equal(await page.locator('style,[style]').count(),0);
    await page.locator('.app-nav a[href="#taller"]').click();
    await page.locator('#open-setup').click();
    const legacy = JSON.parse(fs.readFileSync(path.join(__dirname,'fixtures/legacy-setup.json'),'utf8'));
    await page.locator('#json-file').setInputFiles({name:'legacy.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(legacy))});
    await page.locator('#import-dialog').waitFor({state:'hidden'});
    await page.locator('#value-ala_del').fill('22');
    await page.waitForFunction(()=>document.querySelector('#change-count').textContent.includes('modificado'));
    const download = page.waitForEvent('download');
    await page.locator('#export-workshop').click();
    const data = JSON.parse(fs.readFileSync(await (await download).path(),'utf8'));
    assert.equal(data.current.ala_del,22);
    await page.locator('.app-nav a[href="#circuito"]').click();
    await page.locator('#unsaved-dialog').waitFor({state:'visible'});
    await page.locator('#unsaved-discard').click();
    await page.locator('#open-circuit').click();
    const fixture = {format:'f125-circuit',schemaVersion:1,circuit:{id:'test-browser',name:'<img src=x onerror=alert(1)>',downforce:'mixed',summary:'Test',sources:[{title:'Test',url:'https://example.com',accessedAt:'2026-11-04'}]}};
    await page.locator('#json-input').fill(JSON.stringify(fixture));
    await page.locator('#validate-import').click();
    await page.locator('#apply-import').waitFor({state:'visible'});
    assert.equal(await page.locator('#import-preview img').count(),0);
    assert.match(await page.locator('#import-preview').innerText(),/<img/);
    await page.locator('#import-dialog .dialog-close').click();
    assert.equal((await context.request.post(new URL('api.php?action=compare',base).href,{data:{}})).status(),403);
    for (const width of [1440,768,390]) {
      await page.setViewportSize({width,height:1000});
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
    }
    await page.locator('#version-toggle').click();
    await page.locator('#version-panel a[href="options.php"]').click();
    assert.equal(await page.locator('#language').inputValue(),'es');
    await page.locator('#update-channel').selectOption('alpha');
    await page.getByRole('button',{name:'Guardar opciones'}).click();
    await page.waitForURL(/options\.php\?saved=1$/);
    assert.equal((await context.cookies()).find(c=>c.name==='f125_language').value,'es');
    await page.getByRole('link',{name:'Buscar actualizaciones'}).click();
    assert.equal(await page.locator('#current-channel').innerText(),'Alpha');
    await page.locator('#version-toggle').click();
    await page.locator('#version-panel a[href="options.php"]').click();
    await page.locator('#update-channel').selectOption('release');
    await page.getByRole('button',{name:'Guardar opciones'}).click();
    await page.waitForURL(/options\.php\?saved=1$/);
    assert.deepEqual(errors,[]);
    await context.close();
    console.log('OK: isolated browser navigation, import, export, XSS, CSRF, options and responsive layout.');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exitCode=1;});
