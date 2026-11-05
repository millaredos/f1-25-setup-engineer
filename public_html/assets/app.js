const $ = (selector) => document.querySelector(selector);
const DRAFT_KEY = 'f125-engineer-draft-v1';
const state = { catalog: [], symptoms: null, circuits: [], setups: [], current: {}, reference: null, circuit: null, referenceSource: 'unknown', csrf: '', ready: false, dirty: false };
let importMode = 'circuit';
let preview = null;
let compareSequence = 0;
let compareTimer;

// Los datos importados se muestran como texto, nunca como HTML ejecutable.
function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

function status(message, error = false) {
  $('#status').textContent = message;
  $('#status').classList.toggle('error', error);
}

async function api(action, body) {
  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, body === undefined ? { cache: 'no-store' } : {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf }, body: JSON.stringify(body),
  });
  let data;
  try { data = await response.json(); } catch { throw new Error('El servidor no ha devuelto JSON válido. Revisa PHP y el registro de errores.'); }
  if (!response.ok) throw new Error(data.error || 'No se pudo completar la operación.');
  return data;
}

function format(p, value) {
  return value === null || value === undefined ? '—' : `${Number(value).toLocaleString('es-ES', { minimumFractionDigits: p.dec, maximumFractionDigits: p.dec })}${p.unit ? ` ${p.unit}` : ''}`;
}

function blankValues() { return Object.fromEntries(state.catalog.map((p) => [p.id, null])); }

function complete(values) { return values && state.catalog.every((p) => typeof values[p.id] === 'number' && Number.isFinite(values[p.id])); }

function payload() {
  return {
    format: 'f125-setup-engineer', schemaVersion: 1,
    title: $('#setup-title').value.trim() || 'Reglaje sin nombre', notes: $('#notes').value,
    circuit: state.circuit, reference: state.reference, current: state.current, referenceSource: state.referenceSource,
    conditions: { surface: $('#surface').value, session: $('#session').value },
  };
}

function remember() {
  state.dirty = true;
  try { localStorage.setItem(DRAFT_KEY, JSON.stringify(payload())); }
  catch { status('No se pudo conservar el borrador en este navegador. Exporta JSON para conservarlo.', true); }
}

function allowReplace(message) { return !state.dirty || window.confirm(message); }

function renderReference() {
  const labels = { unknown: 'origen desconocido', 'game-assigned': 'asignados por el juego, aportados por ti', capture: 'capturas / ejemplo', personal: 'reglaje personal' };
  $('#reference-label').textContent = state.reference ? `Referencia: ${labels[state.referenceSource]}` : 'Referencia pendiente';
  $('#reference-help').textContent = state.reference ? 'La referencia se conserva al editar. Solo cambia mediante una acción explícita.' : 'Introduce los veinte valores que te asigna el juego o importa un reglaje.';
}

function renderControls() {
  const root = $('#settings');
  root.replaceChildren();
  const groups = [...new Set(state.catalog.map((p) => p.category))];
  groups.forEach((category, index) => {
    const section = element('section', 'parameter-group');
    const heading = element('div', 'group-heading');
    heading.append(element('span', 'group-number', String(index + 1).padStart(2, '0')), element('h3', '', category));
    const grid = element('div', 'parameter-grid');
    state.catalog.filter((p) => p.category === category).forEach((p) => {
      const card = element('article', 'parameter');
      card.dataset.parameter = p.id;
      const head = element('div', 'parameter-head');
      const name = element('div');
      const label = element('label', 'parameter-title', p.name);
      label.htmlFor = `value-${p.id}`;
      name.append(label, element('p', 'phase', p.phase));
      head.append(name, element('span', 'ref-value', 'Ref. —'));
      const controls = element('div', 'value-controls');
      const minus = element('button', '', '−');
      minus.type = 'button'; minus.dataset.spin = '-1'; minus.setAttribute('aria-label', `Disminuir ${p.name}`);
      const input = element('input');
      input.type = 'number'; input.id = `value-${p.id}`; input.min = p.min; input.max = p.max; input.step = p.step; input.required = true;
      input.placeholder = `${p.min}…${p.max}`; input.inputMode = 'decimal'; input.setAttribute('aria-describedby', `effect-${p.id}`);
      const plus = element('button', '', '+');
      plus.type = 'button'; plus.dataset.spin = '1'; plus.setAttribute('aria-label', `Aumentar ${p.name}`);
      controls.append(minus, input, plus, element('span', 'unit', p.unit));
      const theory = element('div', 'theory'); theory.id = `effect-${p.id}`;
      card.append(head, element('p', 'parameter-description', p.what), controls, theory);
      grid.append(card);
    });
    section.append(heading, grid); root.append(section);
  });
  syncControls();
}

function syncControls() {
  state.catalog.forEach((p) => {
    $(`#value-${p.id}`).value = state.current[p.id] ?? '';
    refreshCard(p);
  });
  renderReference();
  scheduleComparison();
}

function refreshCard(p) {
  const card = $(`[data-parameter="${p.id}"]`);
  const before = state.reference?.[p.id];
  const after = state.current[p.id];
  const valid = $(`#value-${p.id}`).validity.valid;
  const changed = valid && before !== undefined && after !== null && Math.abs(after - before) > 1e-7;
  card.classList.toggle('changed', changed);
  card.querySelector('.ref-value').textContent = `Ref. ${format(p, before)}`;
  const theory = card.querySelector('.theory');
  theory.replaceChildren(element('strong', '', 'EFECTO TEÓRICO'));
  if (changed) {
    theory.append(element('p', '', after > before ? p.up : p.down));
  } else {
    const detail = element('details');
    detail.append(element('summary', '', 'Qué implica subir o bajar'));
    detail.append(element('p', '', `Al subir el número: ${p.up}`), element('p', '', `Al bajar el número: ${p.down}`));
    theory.append(detail);
  }
  const note = element('details');
  note.append(element('summary', '', 'Compromisos y límites'), element('p', '', p.note));
  theory.append(note);
}

function scheduleComparison() {
  clearTimeout(compareTimer);
  const sequence = ++compareSequence;
  if (!state.reference) {
    $('#change-count').textContent = 'Referencia pendiente';
    $('#summary').replaceChildren(element('p', 'empty', 'Introduce los valores iniciales y fija la referencia para comparar.'));
    return;
  }
  if (!complete(state.current) || ![...document.querySelectorAll('#settings input')].every((input) => input.validity.valid)) {
    $('#change-count').textContent = 'Revisa los valores';
    $('#summary').replaceChildren(element('p', 'empty', 'Completa los veinte valores dentro de sus rangos y pasos para obtener el resumen.'));
    return;
  }
  $('#change-count').textContent = 'Comparando…';
  $('#summary').replaceChildren(element('p', 'empty', 'Actualizando la lectura teórica de los valores actuales…'));
  compareTimer = setTimeout(async () => {
    try {
      const result = await api('compare', { reference: state.reference, current: state.current });
      if (sequence !== compareSequence) return;
      $('#change-count').textContent = `${result.changes.length} de 20 modificados`;
      const root = $('#summary'); root.replaceChildren();
      if (!result.changes.length) root.append(element('p', 'empty', 'Sin cambios respecto a la referencia.'));
      result.changes.forEach((change) => {
        const p = state.catalog.find((item) => item.id === change.id);
        const row = element('div', 'summary-item');
        row.append(element('strong', '', change.name), element('span', 'summary-values', `${format(p, change.before)} → ${format(p, change.after)}`), element('p', '', `En teoría: ${change.theory}`));
        root.append(row);
      });
      root.append(element('p', 'source-note', result.notice));
    } catch (error) {
      if (sequence !== compareSequence) return;
      $('#change-count').textContent = 'Resumen no disponible';
      $('#summary').replaceChildren(element('p', 'error-text', error.message));
    }
  }, 200);
}

const characteristicLabels = { corners: 'Curvas', straights: 'Rectas', braking: 'Frenadas', traction: 'Tracción', kerbs: 'Pianos y baches', aero: 'Compromiso aerodinámico' };
const downforceLabels = { low: 'Carga baja', medium: 'Carga media', high: 'Carga alta', mixed: 'Perfil mixto' };

function circuitContent(circuit) {
  const block = element('div', 'circuit-info');
  const heading = element('div', 'circuit-title');
  heading.append(element('h3', '', circuit.name), element('span', 'count', downforceLabels[circuit.downforce]));
  block.append(heading, element('p', 'source-note', `${circuit.layout || 'Trazado sin indicar'} · Versión del juego: ${circuit.gameVersion}`), element('p', 'circuit-summary', circuit.summary));
  const list = element('dl', 'circuit-characteristics');
  Object.entries(characteristicLabels).forEach(([key, label]) => {
    if (!circuit.characteristics[key]) return;
    const pair = element('div'); pair.append(element('dt', '', label), element('dd', '', circuit.characteristics[key])); list.append(pair);
  });
  block.append(list);
  if (circuit.hypotheses) block.append(element('p', 'notice', `Hipótesis y límites: ${circuit.hypotheses}`));
  block.append(sourcesContent(circuit.sources));
  return block;
}

function sourcesContent(sources) {
  const root = element('details', 'sources'); root.append(element('summary', '', 'Fuentes y fecha de consulta'));
  sources.forEach((source) => {
    const link = element('a', '', `${source.title} · ${source.accessedAt}`);
    link.href = source.url; link.target = '_blank'; link.rel = 'noopener noreferrer'; root.append(link);
  });
  return root;
}

function renderCircuit() {
  const root = $('#circuit-card'); root.replaceChildren(); root.hidden = !state.circuit;
  if (state.circuit) root.append(circuitContent(state.circuit));
  renderAdvice();
}

function refreshLists() {
  const select = $('#circuit'); select.replaceChildren(new Option('Selecciona o importa una ficha', ''));
  const list = [...state.circuits];
  if (state.circuit && !list.some((c) => c.id === state.circuit.id)) list.push(state.circuit);
  list.forEach((c) => select.add(new Option(c.name, c.id)));
  select.value = state.circuit?.id || '';
  const saved = $('#saved'); saved.replaceChildren(new Option('Selecciona una versión guardada', ''));
  state.setups.forEach((s) => saved.add(new Option(`${s.title} · ${s.circuit_id} · ${new Date(s.created_at).toLocaleString('es-ES')}`, s.id)));
}

function renderAdvice() {
  const root = $('#advice');
  const symptom = state.symptoms?.items.find((item) => item.id === $('#symptom').value);
  root.replaceChildren();
  if (!symptom) {
    root.append(element('p', 'empty', 'Primero identifica en qué fase aparece. Entrada, apoyo y salida no piden las mismas pruebas.')); return;
  }
  root.append(element('p', 'advice-phase', symptom.phase), element('p', 'advice-check', symptom.check));
  if (state.circuit) root.append(element('p', 'notice', `Contexto de ${state.circuit.name}: ${state.circuit.characteristics.aero || state.circuit.summary}`));
  const list = element('ol', 'advice-steps');
  symptom.steps.forEach((step) => {
    const p = state.catalog.find((item) => item.id === step.id);
    const item = element('li');
    item.append(element('strong', '', p.name), element('p', '', step.why), element('p', 'cost', `Compromiso: ${step.cost}`));
    const value = state.current[p.id];
    if (value !== null && value !== undefined && step.direction !== 'inspect') {
      const next = Number((value + (step.direction === 'up' ? p.step : -p.step)).toFixed(p.dec));
      item.append(element('p', next < p.min || next > p.max ? 'boundary' : 'source-note', next < p.min || next > p.max ? 'Ya estás en el límite para esta dirección. No fuerces un valor fuera de rango; pasa a la siguiente prueba.' : `Un paso desde tu valor actual: ${format(p, value)} → ${format(p, next)}. Tú decides si probarlo.`));
    }
    list.append(item);
  });
  const combined = element('div', 'combined'); combined.append(element('strong', '', 'DESPUÉS, SI PROCEDE'), element('p', '', symptom.combined));
  root.append(list, combined, element('p', 'source-note', state.symptoms.notice), sourcesContent(state.symptoms.sources));
}

function installDraft(data) {
  state.current = { ...data.current }; state.reference = data.reference ? { ...data.reference } : null;
  state.referenceSource = data.referenceSource || 'unknown'; state.circuit = data.circuit || null;
  $('#setup-title').value = data.title || ''; $('#notes').value = data.notes || '';
  $('#surface').value = data.conditions?.surface || 'unknown'; $('#session').value = data.conditions?.session || 'unknown';
  $('#reference-source').value = state.referenceSource;
  syncControls(); refreshLists(); renderCircuit();
}

function openImport(mode) {
  importMode = mode; preview = null;
  $('#import-title').textContent = mode === 'circuit' ? 'Pegar ficha de circuito' : 'Importar reglaje';
  $('#import-help').textContent = mode === 'circuit' ? 'Pega el bloque f125-circuit preparado en la conversación. Se validará antes de guardarlo en MariaDB.' : 'Admite el JSON del comparador anterior, mapas de veinte valores y el formato nuevo. Revisa la referencia antes de aplicar.';
  $('#json-input').value = ''; $('#json-file').value = ''; $('#import-preview').replaceChildren(); $('#import-error').textContent = ''; $('#apply-import').hidden = true;
  $('#import-dialog').showModal();
}

function clearPreview() { preview = null; $('#apply-import').hidden = true; $('#import-preview').replaceChildren(); $('#import-error').textContent = ''; }

async function validateImport() {
  clearPreview();
  try {
    const raw = JSON.parse($('#json-input').value);
    const result = await api(importMode === 'circuit' ? 'import-circuit' : 'import-setup', raw);
    preview = result;
    const root = $('#import-preview');
    if (importMode === 'circuit') {
      root.append(circuitContent(result.circuit));
      $('#apply-import').textContent = 'Guardar ficha y seleccionar circuito';
    } else {
      root.append(element('p', 'notice', [...result.warnings, 'Se importarán referencia y actuales. No se sobrescribe ninguna versión guardada.'].join(' ')));
      if (result.circuit) root.append(circuitContent(result.circuit));
      else root.append(element('p', 'source-note', state.circuit ? `Este JSON no incluye circuito. Al aplicar lo asociarás a la ficha seleccionada: ${state.circuit.name}.` : 'Este JSON no incluye circuito: tendrás que asociar una ficha antes de guardar.'));
      const table = element('table', 'preview-values');
      const head = element('tr'); ['Parámetro', 'Referencia', 'Actual'].forEach((label) => head.append(element('th', '', label))); table.append(head);
      state.catalog.forEach((p) => { const row = element('tr'); row.append(element('td', '', p.name), element('td', '', format(p, result.reference[p.id])), element('td', '', format(p, result.current[p.id]))); table.append(row); });
      root.append(table); $('#apply-import').textContent = 'Usar como borrador';
    }
    $('#apply-import').hidden = false;
  } catch (error) { $('#import-error').textContent = error.message; }
}

async function applyImport() {
  if (!preview) return;
  if (!allowReplace('Esta importación sustituirá el borrador en pantalla. ¿Continuar?')) return;
  const button = $('#apply-import'); button.disabled = true;
  try {
    if (importMode === 'circuit') {
      const result = await api('save-circuit', { format: 'f125-circuit', schemaVersion: 1, circuit: preview.circuit });
      state.circuits = result.circuits;
      installDraft({ current: blankValues(), reference: null, circuit: preview.circuit });
      status('Ficha guardada en MariaDB. Introduce ahora los valores que el juego asigna a este circuito.');
    } else {
      installDraft({ ...preview, circuit: preview.circuit || state.circuit }); status('Reglaje importado como borrador. Revisa origen de referencia, circuito y condiciones.');
    }
    remember(); $('#import-dialog').close();
  } catch (error) { $('#import-error').textContent = error.message; }
  finally { button.disabled = false; }
}

async function exportSetup(legacy = false) {
  try {
    if (!state.reference) throw new Error('Fija una referencia antes de exportar.');
    const data = await api('import-setup', payload());
    const exported = legacy ? { format: 'f125-comparador-v2', created: new Date().toISOString(), reference: data.reference, current: data.current, referenceIsCapture: state.referenceSource === 'capture' } : { ...payload(), created: new Date().toISOString() };
    const blob = new Blob([JSON.stringify(exported, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob); const link = element('a'); link.href = url; link.download = legacy ? 'F1_25_reglaje.json' : `F1_25_${state.circuit?.id || 'borrador'}.json`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    status('JSON exportado. El archivo conserva referencia y actuales.');
  } catch (error) { status(error.message, true); }
}

function bindEvents() {
  $('#settings').addEventListener('input', (event) => {
    if (!event.target.matches('input')) return;
    const p = state.catalog.find((item) => `value-${item.id}` === event.target.id);
    state.current[p.id] = event.target.value === '' || !Number.isFinite(event.target.valueAsNumber) ? null : event.target.valueAsNumber;
    refreshCard(p); remember(); scheduleComparison(); renderAdvice();
  });
  $('#settings').addEventListener('click', (event) => {
    const button = event.target.closest('[data-spin]'); if (!button) return;
    const p = state.catalog.find((item) => item.id === button.closest('[data-parameter]').dataset.parameter);
    const input = $(`#value-${p.id}`);
    if (!input.validity.valid) { input.reportValidity(); return; }
    const value = Number((state.current[p.id] + Number(button.dataset.spin) * p.step).toFixed(p.dec));
    if (value < p.min || value > p.max) { status(`${p.name}: ya estás en el límite permitido.`, true); return; }
    state.current[p.id] = value; input.value = value; refreshCard(p); remember(); scheduleComparison(); renderAdvice();
  });
  $('#set-reference').addEventListener('click', async () => {
    try {
      await api('compare', { reference: state.current, current: state.current });
      if (state.reference && !window.confirm('Vas a sustituir la referencia del borrador por los valores actuales. Las versiones guardadas se conservan. ¿Continuar?')) return;
      state.reference = { ...state.current }; state.referenceSource = $('#reference-source').value;
      syncControls(); remember(); status('Referencia fijada. Los siguientes cambios se compararán con estos valores.');
    } catch (error) { status(error.message, true); }
  });
  $('#restore').addEventListener('click', () => {
    if (!state.reference) { status('Todavía no has fijado una referencia.', true); return; }
    if (!window.confirm('¿Descartar los cambios de los veinte parámetros y volver a la referencia?')) return;
    state.current = { ...state.reference }; syncControls(); renderAdvice(); remember(); status('Valores actuales restaurados desde la referencia.');
  });
  $('#example').addEventListener('click', () => {
    if (!allowReplace('El ejemplo sustituirá los valores y la referencia del borrador. ¿Continuar?')) return;
    state.current = Object.fromEntries(state.catalog.map((p) => [p.id, p.base])); state.reference = { ...state.current }; state.referenceSource = 'capture';
    $('#reference-source').value = 'capture'; syncControls(); renderAdvice(); remember(); status('Ejemplo de capturas cargado. No es el reglaje que el juego asigna al circuito.', true);
  });
  $('#circuit').addEventListener('change', () => {
    const previous = state.circuit?.id || '';
    const next = state.circuits.find((c) => c.id === $('#circuit').value) || null;
    const hasValues = state.reference || Object.values(state.current).some((v) => v !== null);
    if (previous && (hasValues || state.dirty) && !window.confirm('Al cambiar de circuito se vaciará el borrador para no atribuirle otro reglaje. ¿Continuar?')) { $('#circuit').value = previous; return; }
    if (!previous && next && hasValues && !window.confirm(`¿Asociar los valores de este borrador a ${next.name}? Comprueba que corresponden a ese circuito.`)) { $('#circuit').value = previous; return; }
    state.circuit = next;
    if (previous) {
      state.current = blankValues(); state.reference = null; state.referenceSource = 'unknown';
      $('#reference-source').value = 'game-assigned'; $('#setup-title').value = ''; $('#notes').value = '';
    }
    syncControls(); refreshLists(); renderCircuit(); remember();
  });
  $('#symptom').addEventListener('change', renderAdvice);
  ['#surface', '#session', '#setup-title', '#notes'].forEach((selector) => $(selector).addEventListener('input', remember));
  $('#open-circuit').addEventListener('click', () => openImport('circuit'));
  $('#open-setup').addEventListener('click', () => openImport('setup'));
  $('#json-input').addEventListener('input', clearPreview);
  $('#json-file').addEventListener('change', async (event) => {
    clearPreview(); const file = event.target.files[0]; if (!file) return;
    if (file.size > 262144) { $('#import-error').textContent = 'El JSON supera 256 KiB.'; return; }
    $('#json-input').value = await file.text();
  });
  $('#validate-import').addEventListener('click', validateImport);
  $('#apply-import').addEventListener('click', applyImport);
  $('#export').addEventListener('click', () => exportSetup());
  $('#export-legacy').addEventListener('click', () => exportSetup(true));
  $('#save').addEventListener('click', async () => {
    const button = $('#save'); button.disabled = true;
    try {
      if (!state.reference) throw new Error('Fija una referencia antes de guardar.');
      const result = await api('save-setup', payload());
      state.setups = result.setups; state.circuits = result.circuits; refreshLists(); state.dirty = false;
      try { localStorage.removeItem(DRAFT_KEY); } catch { /* El guardado principal ya está confirmado. */ }
      status('Nueva versión guardada en MariaDB. La referencia y las versiones anteriores se conservan.');
    } catch (error) { status(error.message, true); }
    finally { button.disabled = false; }
  });
  $('#load').addEventListener('click', async () => {
    const id = $('#saved').value; if (!id) { status('Selecciona una versión guardada.', true); return; }
    if (!allowReplace('Abrir esta versión sustituirá el borrador en pantalla. ¿Continuar?')) return;
    try { installDraft(await api('load-setup', { id })); remember(); status('Versión abierta como borrador. Guardar creará otra versión.'); }
    catch (error) { status(error.message, true); }
  });
  $('#recover').addEventListener('click', async () => {
    try {
      const raw = JSON.parse(localStorage.getItem(DRAFT_KEY));
      // Revalidar en servidor: localStorage también puede contener datos ajenos o antiguos.
      if (complete(raw.current) && raw.reference) installDraft(await api('import-setup', raw));
      else {
        const valid = await api('import-setup', { ...raw, current: Object.fromEntries(state.catalog.map((p) => [p.id, raw.current?.[p.id] ?? p.base])), reference: raw.reference || undefined });
        const partial = Object.fromEntries(state.catalog.map((p) => [p.id, raw.current?.[p.id] ?? null]));
        installDraft({ ...valid, current: partial, reference: raw.reference || null });
      }
      state.dirty = true; $('#recovery').hidden = true; status('Borrador recuperado; todavía no es una versión guardada en MariaDB.');
    } catch (error) { status(`No se pudo recuperar el borrador: ${error.message}`, true); }
  });
  $('#dismiss-recovery').addEventListener('click', () => { if (window.confirm('¿Descartar el borrador anterior de este navegador?')) { localStorage.removeItem(DRAFT_KEY); $('#recovery').hidden = true; } });
  window.addEventListener('beforeunload', (event) => { if (state.dirty) { event.preventDefault(); event.returnValue = ''; } });
}

async function start() {
  try {
    const data = await api('bootstrap');
    state.csrf = data.csrf; state.catalog = data.catalog.parameters; state.symptoms = data.symptoms; state.circuits = data.circuits; state.setups = data.setups; state.ready = data.storageReady; state.current = blankValues();
    $('#catalog-notice').textContent = data.catalog.notice;
    state.symptoms.items.forEach((s) => $('#symptom').add(new Option(s.label, s.id)));
    renderControls(); refreshLists(); bindEvents();
    try { $('#recovery').hidden = !localStorage.getItem(DRAFT_KEY); } catch { /* El taller funciona sin caché local. */ }
    status(state.ready ? 'Taller listo. Selecciona el circuito y aporta los valores que te asigna el juego.' : 'No se pudo conectar con la base de datos. Puedes seguir editando y exportar tu borrador mientras se recupera la conexión.', !state.ready);
    // Wait for bootstrap to establish the session cookie before background requests.
    checkForUpdates();
  } catch (error) { status(`No se pudo cargar el taller: ${error.message}`, true); }
}

start();

// Mantener las secciones en el mismo documento conserva el borrador al navegar.
function showPage() {
  const requested = location.hash.slice(1) || 'inicio';
  const page = ['inicio', 'circuito', 'taller', 'pruebas', 'versiones'].includes(requested) ? requested : 'inicio';
  document.querySelectorAll('[data-page]').forEach((section) => { section.hidden = section.dataset.page !== page; });
  document.querySelectorAll('.app-nav a').forEach((link) => {
    if (link.hash === `#${page}`) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });
  document.title = `${document.querySelector('.app-nav [aria-current]')?.textContent || 'Inicio'} · F1 25`;
}
window.addEventListener('hashchange', showPage);
showPage();

// Background discovery never interrupts editing or starts an installation.
async function checkForUpdates() {
  if (!state.csrf) return;
  try {
    const update = await api('update-status');
    const link = document.querySelector('.app-nav a[href="update.php"]');
    if (link) link.textContent = update.newer ? `Updates (${update.available})` : 'Updates';
  } catch { /* Keep the editor available when GitHub or the network is unavailable. */ }
}
setInterval(() => { if (!document.hidden) checkForUpdates(); }, 300000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) checkForUpdates(); });
