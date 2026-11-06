/* Sitio temporal propio: prueba estados y redirecciones sin tocar la configuración real. */
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');
const { spawn, spawnSync } = require('node:child_process');
const assert = require('node:assert/strict');

(async () => {
  const repository = path.resolve(__dirname, '..');
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'f125-web-'));
  let server;
  const serverLog = path.join(root, 'php-server.log');
  try {
    for (const directory of ['src', 'data', 'public_html']) fs.cpSync(path.join(repository, directory), path.join(root, directory), { recursive: true });
    fs.mkdirSync(path.join(root, 'config'));
    const publicName = process.env.F125_TEST_PUBLIC_NAME || 'public_html';
    assert.match(publicName, /^[A-Za-z0-9_-]+$/);
    if (publicName !== 'public_html') fs.renameSync(path.join(root, 'public_html'), path.join(root, publicName));
    fs.copyFileSync(path.join(__dirname, 'fixtures/github.php'), path.join(root, 'config/github-fixture.php'));
    const phpQuote = (value) => "'" + value.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
    const fixturePackage = process.env.F125_TEST_PACKAGE || '';
    fs.writeFileSync(path.join(root, 'config/local.php'), `<?php require __DIR__ . '/github-fixture.php'; return ['github_transport' => fixtureGitHub(${phpQuote(fixturePackage)})];`);

    const mount = process.env.F125_TEST_BASE_PATH || '/';
    assert.match(mount, /^\/(?:[a-zA-Z0-9_-]+\/)*$/, 'ruta de montaje de pruebas válida');
    const routerArgs = mount === '/' ? [] : [path.join(root, 'router.php')];
    if (routerArgs.length) fs.copyFileSync(path.join(__dirname, 'web-router.php'), routerArgs[0]);
    const port = await new Promise((resolve) => {
      const socket = net.createServer();
      socket.listen(0, '127.0.0.1', () => { const selected = socket.address().port; socket.close(() => resolve(selected)); });
    });
    const env = { ...process.env };
    for (const key of ['F125_DSN', 'F125_DB_USER', 'F125_DB_PASSWORD']) delete env[key];
    const zipArgs = process.env.F125_TEST_ZIP_DIR ? ['-d', `extension_dir=${process.env.F125_TEST_ZIP_DIR}`, '-d', 'extension=zip'] : [];
    // The child browser runs synchronously below. A pipe could fill while this event loop is blocked.
    const logHandle = fs.openSync(serverLog, 'w');
    server = spawn(process.env.F125_TEST_PHP || 'php', ['-n', ...zipArgs, '-S', `127.0.0.1:${port}`, '-t', path.join(root, publicName), ...routerArgs], { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', logHandle] });
    fs.closeSync(logHandle);
    let launchError;
    server.on('error', (error) => { launchError = error; });
    const host = process.env.F125_TEST_HOST || '127.0.0.1';
    const base = `http://${host}:${port}${mount}`;
    let response;
    for (let attempt = 0; attempt < 100; attempt++) {
      if (launchError) throw launchError;
      try { response = await fetch(base, { redirect: 'manual' }); break; }
      catch { await new Promise((resolve) => setTimeout(resolve, 50)); }
    }
    assert.ok(response, 'servidor temporal disponible');
    assert.equal(response.status, 302);
    assert.equal(response.headers.get('location'), 'install.php');
    const statePath = path.join(root, 'config/installation.json');
    response = await fetch(base + 'install.php');
    const html = await response.text();
    assert.equal(response.status, 200);
    assert.match(html, /<h1>Instalación<\/h1>/);
    assert.doesNotMatch(html, /Prepara tu taller|usando la MariaDB|No crea usuarios|no modifica Apache|<style\b|\bstyle\s*=/i);
    assert.equal(fs.existsSync(statePath), false, 'GET no marca instalación');
    response = await fetch(base + 'install.php', { method: 'POST', body: new URLSearchParams({ csrf: 'incorrecto' }) });
    assert.equal(response.status, 403);
    assert.equal(fs.existsSync(statePath), false, 'POST inválido no modifica estado');
    response = await fetch(base + 'api.php?action=bootstrap');
    assert.equal(response.status, 409, 'API cerrada hasta completar instalación');
    fs.writeFileSync(statePath, '{"installed":false}');
    response = await fetch(base, { redirect: 'manual' });
    assert.equal(response.status, 302);
    fs.writeFileSync(statePath, '{');
    response = await fetch(base, { redirect: 'manual' });
    assert.equal(response.status, 503, 'estado corrupto no permite reinstalación automática');
    fs.writeFileSync(statePath, '{"installed":true}');
    response = await fetch(base, { redirect: 'manual' });
    assert.equal(response.status, 200, 'estado true abre taller sin comprobar conexión');
    response = await fetch(base + 'install.php', { redirect: 'manual' });
    assert.equal(response.status, 302);
    assert.equal(response.headers.get('location'), './');
    response = await fetch(base + 'install.php', { method: 'POST', redirect: 'manual' });
    assert.equal(response.status, 303, 'POST directo instalado vuelve al inicio');
    response = await fetch(base + 'api.php?action=bootstrap');
    assert.equal((await response.json()).storageReady, false, 'fallo de conexión no cambia instalado');
    assert.equal(JSON.parse(fs.readFileSync(statePath, 'utf8')).installed, true);
    response = await fetch(base + 'database.php');
    assert.equal(response.status, 503, 'database outage does not reopen installer');
    assert.match(await response.text(), /No se pudo preparar o completar/);
    response = await fetch(base + 'database.php', { method: 'POST', body: new URLSearchParams({ csrf: 'incorrecto' }) });
    assert.equal(response.status, 403, 'database migration requires valid CSRF');
    assert.equal(JSON.parse(fs.readFileSync(statePath, 'utf8')).installed, true);
    const browser = spawnSync(process.execPath, [path.join(__dirname, 'browser.cjs')], { env: { ...env, F125_TEST_URL: base }, windowsHide: true, encoding: 'utf8' });
    process.stdout.write(browser.stdout || ''); process.stderr.write(browser.stderr || '');
    assert.equal(browser.status, 0, 'recorrido del taller con estado instalado');
    if (mount !== '/') {
      for (const resource of ['config/local.php', 'src/Domain.php', 'data/parameters.json']) {
        response = await fetch(base + resource);
        assert.equal(response.status, 404, 'montaje simulado solo expone puntos de entrada y assets públicos');
      }
    }
    response = await fetch(base + 'options.php');
    const optionsHtml = await response.text();
    assert.equal(response.status, 200);
    assert.match(optionsHtml, /value="release" selected/);
    const cookie = response.headers.get('set-cookie').split(';')[0];
    const csrf = optionsHtml.match(/name="csrf" value="([a-f0-9]+)"/)[1];
    const previousSettings = fs.readFileSync(path.join(root, 'config/update-settings.json'), 'utf8');
    const post = (page, data) => fetch(base + page, { method: 'POST', headers: { Cookie: cookie }, body: new URLSearchParams({ language: 'es', ...data }), redirect: 'manual' });
    response = await post('options.php', { csrf: 'invalid', channel: 'alpha' });
    assert.equal(response.status, 403, 'channel change requires CSRF');
    assert.equal(fs.readFileSync(path.join(root, 'config/update-settings.json'), 'utf8'), previousSettings);
    response = await post('options.php', { csrf, channel: 'main' });
    assert.equal(response.status, 422, 'invalid channel rejected');
    response = await post('options.php', { csrf, channel: 'alpha', language: '../../outside' });
    assert.equal(response.status, 422, 'invalid language rejected');
    assert.equal(response.headers.get('set-cookie'), null, 'invalid language does not write a cookie');
    assert.equal(fs.readFileSync(path.join(root, 'config/update-settings.json'), 'utf8'), previousSettings, 'invalid language does not change channel');
    response = await post('options.php', { csrf, channel: 'alpha' });
    assert.equal(response.status, 303);
    assert.equal(JSON.parse(fs.readFileSync(path.join(root, 'config/update-settings.json'))).channel, 'alpha');
    response = await fetch(base + 'update.php', { headers: { Cookie: cookie } });
    const updateHtml = await response.text();
    assert.doesNotMatch(updateHtml, /type="file"|name="key"|update_key/);
    assert.match(updateHtml, /id="current-channel">Alpha/);
    response = await post('update.php', { csrf: 'invalid', action: 'install' });
    assert.equal(response.status, 403);
    if (process.env.F125_TEST_PACKAGE && process.env.F125_TEST_ZIP_DIR) {
      const identity = updateHtml.match(/name="identity" value="([a-f0-9]+)"/)?.[1];
      // The test package may equal the checkout version; simulate an older installation.
      fs.writeFileSync(path.join(root, 'src/VERSION'), '0.2.0\n');
      response = await fetch(base + 'update.php', { headers: { Cookie: cookie } });
      const candidateHtml = await response.text();
      const selected = candidateHtml.match(/name="identity" value="([a-f0-9]+)"/)?.[1];
      assert.ok(selected, 'ready Alpha package available');
      const privateConfig = fs.readFileSync(path.join(root, 'config/local.php'), 'utf8');
      response = await post('update.php', { csrf, action: 'install', identity: '0'.repeat(64) });
      assert.equal(response.status, 422, 'forged package identity rejected');
      response = await post('update.php', { csrf, action: 'install', identity: selected });
      assert.equal(response.status, 303, 'verified GitHub fixture package installed: ' + (response.status === 303 ? '' : await response.text()));
      assert.equal(response.headers.get('location'), 'update.php');
      assert.equal(fs.readFileSync(path.join(root, 'src/VERSION'), 'utf8').trim(), fs.readFileSync(path.join(repository, 'src/VERSION'), 'utf8').trim());
      assert.equal(fs.readFileSync(path.join(root, 'config/local.php'), 'utf8'), privateConfig);
      assert.equal(JSON.parse(fs.readFileSync(statePath)).installed, true);
      assert.equal(JSON.parse(fs.readFileSync(path.join(root, 'config/update-settings.json'))).channel, 'alpha');
      assert.equal(fs.existsSync(path.join(root, 'config/update-journal.json')), false);
      // Returning to Release does not downgrade this Alpha installation.
      response = await post('options.php', { csrf, channel: 'release' });
      assert.equal(response.status, 303);
      response = await fetch(base + 'update.php', { headers: { Cookie: cookie } });
      assert.match(await response.text(), /No se vuelve autom/);
      console.log('OK: web channel options, CSRF, verified GitHub fixture download, backup and preserved private state.');
    }
    // Substitute storage only after all real outage/package checks, inside the temporary site.
    fs.copyFileSync(path.join(__dirname, 'fixtures/migration-store.php'), path.join(root, 'src/Store.php'));
    fs.copyFileSync(path.join(__dirname, 'fixtures/game-catalog.json'), path.join(root, 'config/game-catalog.json'));
    // Restart PHP so its realpath cache cannot retain the previously loaded storage class.
    server.kill();
    await new Promise((resolve) => server.once('exit', resolve));
    const fixtureLog = fs.openSync(serverLog, 'a');
    server = spawn(process.env.F125_TEST_PHP || 'php', ['-n', ...zipArgs, '-S', `127.0.0.1:${port}`, '-t', path.join(root, publicName), ...routerArgs], { cwd: root, env, windowsHide: true, stdio: ['ignore', 'ignore', fixtureLog] });
    fs.closeSync(fixtureLog);
    for (let attempt = 0; attempt < 100; attempt++) {
      try { response = await fetch(base + 'database.php'); break; }
      catch { await new Promise((resolve) => setTimeout(resolve, 50)); }
    }

    assert.equal(response.status, 200);
    assert.match(await response.text(), /Actualización pendiente/);
    assert.equal(fs.existsSync(path.join(root, 'config/migration-applied')), false, 'GET does not apply migrations');
    const databaseBrowser = spawnSync(process.execPath, [path.join(__dirname, 'database-browser.cjs')], { env: { ...env, F125_TEST_URL: base }, windowsHide: true, encoding: 'utf8' });
    process.stdout.write(databaseBrowser.stdout || ''); process.stderr.write(databaseBrowser.stderr || '');
    assert.equal(databaseBrowser.status, 0);
    const catalogBrowser = spawnSync(process.execPath, [path.join(__dirname, 'catalog-browser.cjs')], { env: { ...env, F125_TEST_URL: base }, windowsHide: true, encoding: 'utf8' });
    process.stdout.write(catalogBrowser.stdout || ''); process.stderr.write(catalogBrowser.stderr || '');
    assert.equal(catalogBrowser.status, 0);
    console.log(`OK: estados, redirecciones, POST, API y caída de conexión; montaje ${mount}, host ${host}, puerto ${port}; sitio temporal aislado.`);
  } catch (error) {
    if (fs.existsSync(serverLog)) process.stderr.write(fs.readFileSync(serverLog, 'utf8').slice(-4000));
    throw error;
  } finally {
    if (server && server.exitCode === null) {
      const stopped = new Promise((resolve) => server.once('exit', resolve));
      server.kill(); await stopped;
    }
    // Único destino eliminado: directorio temporal absoluto creado por este test.
    const resolved = path.resolve(root);
    if (path.dirname(resolved) !== path.resolve(os.tmpdir()) || !path.basename(resolved).startsWith('f125-web-')) throw new Error('Destino de limpieza inválido');
    fs.rmSync(resolved, { recursive: true, force: true });
  }
})().catch((error) => { console.error(error); process.exitCode = 1; });
