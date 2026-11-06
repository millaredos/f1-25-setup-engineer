import { t } from '../i18n.php';

const menu = document.querySelector('.version-menu');
if (menu) {
  const toggle = menu.querySelector('#version-toggle');
  const panel = menu.querySelector('#version-panel');
  let closeTimer;
  let pinned = false;
  function open(value) {
    clearTimeout(closeTimer);
    panel.hidden = !value;
    toggle.setAttribute('aria-expanded', String(value));
  }
  toggle.addEventListener('click', () => { pinned = !pinned; open(pinned); });
  toggle.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); open(true); panel.querySelector('a').focus(); }
  });
  menu.addEventListener('pointerenter', (event) => { if (event.pointerType === 'mouse') open(true); });
  menu.addEventListener('pointerleave', (event) => {
    if (event.pointerType === 'mouse' && !pinned && !menu.contains(document.activeElement)) closeTimer = setTimeout(() => open(false), 180);
  });
  menu.addEventListener('focusout', (event) => { if (!menu.contains(event.relatedTarget)) { pinned = false; open(false); } });
  document.addEventListener('click', (event) => { if (!menu.contains(event.target)) { pinned = false; open(false); } });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) { pinned = false; open(false); toggle.focus(); }
  });
  let checking = false;
  let lastCheck = 0;
  let notified = '';
  async function checkForUpdates() {
    if (checking || document.hidden || Date.now() - lastCheck < 60000) return;
    checking = true; lastCheck = Date.now();
    try {
      const response = await fetch('api.php?action=update-status', { cache: 'no-store' });
      if (!response.ok) return;
      const result = await response.json();
      if (result.known === false) return; // A failed check does not dismiss a known notification.
      const newer = result.newer === true && typeof result.available === 'string';
      const message = newer ? t('Nueva versión disponible: {version}', { version: result.available }) : '';
      menu.querySelectorAll('[data-update-dot]').forEach((dot) => { dot.hidden = !newer; });
      const version = menu.querySelector('[data-update-version]');
      version.hidden = !newer; version.textContent = message;
      menu.querySelector('[data-update-announcement]').textContent = message;
      if (message !== notified) { menu.querySelector('[data-update-live]').textContent = message; notified = message; }
    } catch { /* A temporary network failure leaves the menu and editor available. */ }
    finally { checking = false; }
  }
  // The workshop first establishes its CSRF session; maintenance pages already have one.
  if (document.querySelector('#settings')) document.addEventListener('f125-ready', checkForUpdates, { once: true });
  else checkForUpdates();
  setInterval(checkForUpdates, 300000);
  document.addEventListener('visibilitychange', checkForUpdates);
}
