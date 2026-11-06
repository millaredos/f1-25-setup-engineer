import { t, tn, locale } from '../i18n.php';
const $ = (selector) => document.querySelector(selector);
const DRAFT_KEY = 'f125-engineer-draft-v1';
const state = { catalog: [], symptoms: null, circuits: [], setups: [], current: {}, reference: null, circuit: null, conditions: { surface: 'unknown', session: 'unknown' }, referenceSource: 'unknown', csrf: '', ready: false, dirty: false };
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
  try { data = await response.json(); } catch { throw new Error(t('El servidor no ha devuelto JSON válido. Revisa PHP y el registro de errores.')); }
  if (!response.ok) throw new Error(data.error || t('No se pudo completar la operación.'));
  return data;
}

function format(p, value) {
  return value === null || value === undefined ? '—' : `${Number(value).toLocaleString(locale, { minimumFractionDigits: p.dec, maximumFractionDigits: p.dec })}${p.unit ? ` ${p.unit}` : ''}`;
}

function blankValues() { return Object.fromEntries(state.catalog.map((p) => [p.id, null])); }

function complete(values) { return values && state.catalog.every((p) => typeof values[p.id] === 'number' && Number.isFinite(values[p.id])); }

function payload() {
  return {
    format: 'f125-setup-engineer', schemaVersion: 1,
    title: $('#setup-title').value.trim() || t('Reglaje sin nombre'), notes: $('#notes').value,
    circuit: state.circuit, reference: state.reference, current: state.current, referenceSource: state.referenceSource,
    conditions: { ...state.conditions },
  };
}

function remember() {
  state.dirty = true;
  try { localStorage.setItem(DRAFT_KEY, JSON.stringify(payload())); }
  catch { status(t('No se pudo conservar el borrador en este navegador. Exporta JSON para conservarlo.'), true); }
}

function allowReplace(message) { return !state.dirty || window.confirm(message); }

function renderReference() {
  const labels = { unknown: t('origen desconocido'), 'game-assigned': t('asignados por el juego, aportados por ti'), capture: t('capturas / ejemplo'), personal: t('reglaje personal') };
  $('#reference-label').textContent = state.reference ? t('Referencia: {source}', { source: labels[state.referenceSource] }) : t('Referencia pendiente');
  $('#reference-help').textContent = state.reference ? t('La referencia se conserva al editar. Solo cambia mediante una acción explícita.') : t('Introduce los veinte valores que te asigna el juego o importa un reglaje.');
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
      head.append(name, element('span', 'ref-value', t('Ref. —')));
      const controls = element('div', 'value-controls');
      const minus = element('button', '', '−');
      minus.type = 'button'; minus.dataset.spin = '-1'; minus.setAttribute('aria-label', t('Disminuir {name}', { name: p.name }));
      const input = element('input');
      input.type = 'number'; input.id = `value-${p.id}`; input.min = p.min; input.max = p.max; input.step = p.step; input.required = true;
      input.placeholder = `${p.min}…${p.max}`; input.inputMode = 'decimal'; input.setAttribute('aria-describedby', `effect-${p.id}`);
      const plus = element('button', '', '+');
      plus.type = 'button'; plus.dataset.spin = '1'; plus.setAttribute('aria-label', t('Aumentar {name}', { name: p.name }));
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
  card.querySelector('.ref-value').textContent = t('Ref. {value}', { value: format(p, before) });
  const theory = card.querySelector('.theory');
  theory.replaceChildren(element('strong', '', t('EFECTO TEÓRICO')));
  if (changed) {
    theory.append(element('p', '', after > before ? p.up : p.down));
  } else {
    const detail = element('details');
    detail.append(element('summary', '', t('Qué implica subir o bajar')));
    detail.append(element('p', '', t('Al subir el número: {effect}', { effect: p.up })), element('p', '', t('Al bajar el número: {effect}', { effect: p.down })));
    theory.append(detail);
  }
  const note = element('details');
  note.append(element('summary', '', t('Compromisos y límites')), element('p', '', p.note));
  theory.append(note);
}

function scheduleComparison() {
  clearTimeout(compareTimer);
  const sequence = ++compareSequence;
  if (!state.reference) {
    $('#change-count').textContent = t('Referencia pendiente');
    $('#summary').replaceChildren(element('p', 'empty', t('Introduce los valores iniciales y fija la referencia para comparar.')));
    return;
  }
  if (!complete(state.current) || ![...document.querySelectorAll('#settings input')].every((input) => input.validity.valid)) {
    $('#change-count').textContent = t('Revisa los valores');
    $('#summary').replaceChildren(element('p', 'empty', t('Completa los veinte valores dentro de sus rangos y pasos para obtener el resumen.')));
    return;
  }
  $('#change-count').textContent = t('Comparando…');
  $('#summary').replaceChildren(element('p', 'empty', t('Actualizando la lectura teórica de los valores actuales…')));
  compareTimer = setTimeout(async () => {
    try {
      const result = await api('compare', { reference: state.reference, current: state.current });
      if (sequence !== compareSequence) return;
      $('#change-count').textContent = tn('{count} de 20 modificado', '{count} de 20 modificados', result.changes.length);
      const root = $('#summary'); root.replaceChildren();
      if (!result.changes.length) root.append(element('p', 'empty', t('Sin cambios respecto a la referencia.')));
      result.changes.forEach((change) => {
        const p = state.catalog.find((item) => item.id === change.id);
        const row = element('div', 'summary-item');
        row.append(element('strong', '', change.name), element('span', 'summary-values', `${format(p, change.before)} → ${format(p, change.after)}`), element('p', '', t('En teoría: {effect}', { effect: change.theory })));
        root.append(row);
      });
      root.append(element('p', 'source-note', result.notice));
    } catch (error) {
      if (sequence !== compareSequence) return;
      $('#change-count').textContent = t('Resumen no disponible');
      $('#summary').replaceChildren(element('p', 'error-text', error.message));
    }
  }, 200);
}

const characteristicLabels = { corners: t('Curvas'), straights: t('Rectas'), braking: t('Frenadas'), traction: t('Tracción'), kerbs: t('Pianos y baches'), aero: t('Compromiso aerodinámico') };
const downforceLabels = { low: t('Carga baja'), medium: t('Carga media'), high: t('Carga alta'), mixed: t('Perfil mixto') };

function circuitContent(circuit) {
  const block = element('div', 'circuit-info');
  const heading = element('div', 'circuit-title');
  heading.append(element('h3', '', circuit.name), element('span', 'count', downforceLabels[circuit.downforce]));
  block.append(heading, element('p', 'source-note', t('Trazado: {layout} · Versión del juego: {version}', { layout: circuit.layout || t('Trazado sin indicar'), version: circuit.gameVersion })), element('p', 'circuit-summary', circuit.summary));
  const list = element('dl', 'circuit-characteristics');
  Object.entries(characteristicLabels).forEach(([key, label]) => {
    if (!circuit.characteristics[key]) return;
    const pair = element('div'); pair.append(element('dt', '', label), element('dd', '', circuit.characteristics[key])); list.append(pair);
  });
  block.append(list);
  if (circuit.hypotheses) block.append(element('p', 'notice', t('Hipótesis y límites: {text}', { text: circuit.hypotheses })));
  block.append(sourcesContent(circuit.sources));
  return block;
}

function sourcesContent(sources) {
  const root = element('details', 'sources'); root.append(element('summary', '', t('Fuentes y fecha de consulta')));
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
  const select = $('#circuit'); select.replaceChildren(new Option(t('Selecciona o importa una ficha'), ''));
  const list = [...state.circuits];
  if (state.circuit && !list.some((c) => c.id === state.circuit.id)) list.push(state.circuit);
  list.forEach((c) => select.add(new Option(c.name, c.id)));
  select.value = state.circuit?.id || '';
  const saved = $('#saved'); const selected = saved.value;
  saved.replaceChildren(new Option(t('Selecciona una versión guardada'), ''));
  state.setups.filter((s) => !state.circuit || s.circuit_id === state.circuit.id).forEach((s) => saved.add(new Option(`${s.title} · ${new Date(s.created_at).toLocaleString(locale)}`, s.id)));
  saved.value = [...saved.options].some((option) => option.value === selected) ? selected : '';
}

function renderAdvice() {
  const root = $('#advice');
  const symptom = state.symptoms?.items.find((item) => item.id === $('#symptom').value);
  root.replaceChildren();
  if (!symptom) {
    root.append(element('p', 'empty', t('Primero identifica en qué fase aparece. Entrada, apoyo y salida no piden las mismas pruebas.'))); return;
  }
  root.append(element('p', 'advice-phase', symptom.phase), element('p', 'advice-check', symptom.check));
  if (state.circuit) root.append(element('p', 'notice', t('Contexto de {name}: {text}', { name: state.circuit.name, text: state.circuit.characteristics.aero || state.circuit.summary })));
  const list = element('ol', 'advice-steps');
  symptom.steps.forEach((step) => {
    const p = state.catalog.find((item) => item.id === step.id);
    const item = element('li');
    item.append(element('strong', '', p.name), element('p', '', step.why), element('p', 'cost', t('Compromiso: {cost}', { cost: step.cost })));
    const value = state.current[p.id];
    if (value !== null && value !== undefined && step.direction !== 'inspect') {
      const next = Number((value + (step.direction === 'up' ? p.step : -p.step)).toFixed(p.dec));
      item.append(element('p', next < p.min || next > p.max ? 'boundary' : 'source-note', next < p.min || next > p.max ? t('Ya estás en el límite para esta dirección. No fuerces un valor fuera de rango; pasa a la siguiente prueba.') : t('Un paso desde tu valor actual: {before} → {after}. Tú decides si probarlo.', { before: format(p, value), after: format(p, next) })));
    }
    list.append(item);
  });
  const combined = element('div', 'combined'); combined.append(element('strong', '', t('DESPUÉS, SI PROCEDE')), element('p', '', symptom.combined));
  root.append(list, combined, element('p', 'source-note', state.symptoms.notice), sourcesContent(state.symptoms.sources));
}

function installDraft(data) {
  state.current = { ...data.current }; state.reference = data.reference ? { ...data.reference } : null;
  state.referenceSource = data.referenceSource || 'unknown'; state.circuit = data.circuit || null;
  $('#setup-title').value = data.title || ''; $('#notes').value = data.notes || '';
  // Existing imports and saved versions keep their metadata; choosing a preset does not invent conditions.
  state.conditions = { surface: data.conditions?.surface || 'unknown', session: data.conditions?.session || 'unknown' };
  $('#reference-source').value = state.referenceSource;
  syncControls(); refreshLists(); renderCircuit();
}

function openImport(mode) {
  importMode = mode; preview = null;
  $('#import-title').textContent = mode === 'circuit' ? t('Pegar ficha de circuito') : t('Importar reglaje');
  $('#import-help').textContent = mode === 'circuit' ? t('Pega el bloque f125-circuit preparado en la conversación. Se validará antes de guardarlo en MariaDB.') : t('Admite el JSON del comparador anterior, mapas de veinte valores y el formato nuevo. Revisa la referencia antes de aplicar.');
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
      $('#apply-import').textContent = t('Guardar ficha y seleccionar circuito');
    } else {
      root.append(element('p', 'notice', [...result.warnings, t('Se importarán referencia y actuales. No se sobrescribe ninguna versión guardada.')].join(' ')));
      if (result.circuit) root.append(circuitContent(result.circuit));
      else root.append(element('p', 'source-note', state.circuit ? t('Este JSON no incluye circuito. Al aplicar lo asociarás a la ficha seleccionada: {name}.', { name: state.circuit.name }) : t('Este JSON no incluye circuito: tendrás que asociar una ficha antes de guardar.')));
      const table = element('table', 'preview-values');
      const head = element('tr'); [t('Parámetro'), t('Referencia'), t('Actual')].forEach((label) => head.append(element('th', '', label))); table.append(head);
      state.catalog.forEach((p) => { const row = element('tr'); row.append(element('td', '', p.name), element('td', '', format(p, result.reference[p.id])), element('td', '', format(p, result.current[p.id]))); table.append(row); });
      root.append(table); $('#apply-import').textContent = t('Usar como borrador');
    }
    $('#apply-import').hidden = false;
  } catch (error) { $('#import-error').textContent = error.message; }
}

async function applyImport() {
  if (!preview) return;
  if (!allowReplace(t('Esta importación sustituirá el borrador en pantalla. ¿Continuar?'))) return;
  const button = $('#apply-import'); button.disabled = true;
  try {
    if (importMode === 'circuit') {
      const result = await api('save-circuit', { format: 'f125-circuit', schemaVersion: 1, circuit: preview.circuit });
      state.circuits = result.circuits;
      installDraft({ current: blankValues(), reference: null, circuit: preview.circuit });
      status(t('Ficha guardada en MariaDB. Introduce ahora los valores que el juego asigna a este circuito.'));
    } else {
      installDraft({ ...preview, circuit: preview.circuit || state.circuit }); status(t('Reglaje importado como borrador. Revisa origen de referencia, circuito y condiciones.'));
    }
    remember(); $('#import-dialog').close();
  } catch (error) { $('#import-error').textContent = error.message; }
  finally { button.disabled = false; }
}

async function exportSetup(legacy = false) {
  try {
    if (!state.reference) throw new Error(t('Fija una referencia antes de exportar.'));
    const data = await api('import-setup', payload());
    const exported = legacy ? { format: 'f125-comparador-v2', created: new Date().toISOString(), reference: data.reference, current: data.current, referenceIsCapture: state.referenceSource === 'capture' } : { ...payload(), created: new Date().toISOString() };
    const blob = new Blob([JSON.stringify(exported, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob); const link = element('a'); link.href = url; link.download = legacy ? 'F1_25_reglaje.json' : `F1_25_${state.circuit?.id || 'borrador'}.json`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    status(t('JSON exportado. El archivo conserva referencia y actuales.'));
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
    if (value < p.min || value > p.max) { status(t('{name}: ya estás en el límite permitido.', { name: p.name }), true); return; }
    state.current[p.id] = value; input.value = value; refreshCard(p); remember(); scheduleComparison(); renderAdvice();
  });
  $('#set-reference').addEventListener('click', async () => {
    try {
      await api('compare', { reference: state.current, current: state.current });
      if (state.reference && !window.confirm(t('Vas a sustituir la referencia del borrador por los valores actuales. Las versiones guardadas se conservan. ¿Continuar?'))) return;
      state.reference = { ...state.current }; state.referenceSource = $('#reference-source').value;
      syncControls(); remember(); status(t('Referencia fijada. Los siguientes cambios se compararán con estos valores.'));
    } catch (error) { status(error.message, true); }
  });
  $('#restore').addEventListener('click', () => {
    if (!state.reference) { status(t('Todavía no has fijado una referencia.'), true); return; }
    if (!window.confirm(t('¿Descartar los cambios de los veinte parámetros y volver a la referencia?'))) return;
    state.current = { ...state.reference }; syncControls(); renderAdvice(); remember(); status(t('Valores actuales restaurados desde la referencia.'));
  });
  $('#example').addEventListener('click', () => {
    if (!allowReplace(t('El ejemplo sustituirá los valores y la referencia del borrador. ¿Continuar?'))) return;
    state.current = Object.fromEntries(state.catalog.map((p) => [p.id, p.base])); state.reference = { ...state.current }; state.referenceSource = 'capture';
    $('#reference-source').value = 'capture'; syncControls(); renderAdvice(); remember(); status(t('Ejemplo de capturas cargado. No es el reglaje que el juego asigna al circuito.'), true);
  });
  $('#circuit').addEventListener('change', () => {
    const previous = state.circuit?.id || '';
    const next = state.circuits.find((c) => c.id === $('#circuit').value) || null;
    const hasValues = state.reference || Object.values(state.current).some((v) => v !== null);
    if (previous && (hasValues || state.dirty) && !window.confirm(t('Al cambiar de circuito se vaciará el borrador para no atribuirle otro reglaje. ¿Continuar?'))) { $('#circuit').value = previous; return; }
    if (!previous && next && hasValues && !window.confirm(t('¿Asociar los valores de este borrador a {name}? Comprueba que corresponden a ese circuito.', { name: next.name }))) { $('#circuit').value = previous; return; }
    state.circuit = next;
    if (previous) {
      state.current = blankValues(); state.reference = null; state.referenceSource = 'unknown';
      state.conditions = { surface: 'unknown', session: 'unknown' };
      $('#reference-source').value = 'game-assigned'; $('#setup-title').value = ''; $('#notes').value = '';
    }
    syncControls(); refreshLists(); renderCircuit(); remember();
  });
  $('#symptom').addEventListener('change', renderAdvice);
  ['#setup-title', '#notes'].forEach((selector) => $(selector).addEventListener('input', remember));
  $('#open-circuit').addEventListener('click', () => openImport('circuit'));
  $('#open-setup').addEventListener('click', () => openImport('setup'));
  $('#json-input').addEventListener('input', clearPreview);
  $('#json-file').addEventListener('change', async (event) => {
    clearPreview(); const file = event.target.files[0]; if (!file) return;
    if (file.size > 262144) { $('#import-error').textContent = t('El JSON supera 256 KiB.'); return; }
    $('#json-input').value = await file.text();
  });
  $('#validate-import').addEventListener('click', validateImport);
  $('#apply-import').addEventListener('click', applyImport);
  $('#export').addEventListener('click', () => exportSetup());
  $('#export-legacy').addEventListener('click', () => exportSetup(true));
  $('#save').addEventListener('click', async () => {
    const button = $('#save'); button.disabled = true;
    try {
      if (!state.reference) throw new Error(t('Fija una referencia antes de guardar.'));
      const result = await api('save-setup', payload());
      state.setups = result.setups; state.circuits = result.circuits; refreshLists(); state.dirty = false;
      try { localStorage.removeItem(DRAFT_KEY); } catch { /* El guardado principal ya está confirmado. */ }
      status(t('Nueva versión guardada en MariaDB. La referencia y las versiones anteriores se conservan.'));
    } catch (error) { status(error.message, true); }
    finally { button.disabled = false; }
  });
  $('#load').addEventListener('click', async () => {
    const id = $('#saved').value; if (!id) { status(t('Selecciona una versión guardada.'), true); return; }
    if (!allowReplace(t('Abrir esta versión sustituirá el borrador en pantalla. ¿Continuar?'))) return;
    try { installDraft(await api('load-setup', { id })); remember(); status(t('Versión abierta como borrador. Guardar creará otra versión.')); }
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
      state.dirty = true; $('#recovery').hidden = true; status(t('Borrador recuperado; todavía no es una versión guardada en MariaDB.'));
    } catch (error) { status(t('No se pudo recuperar el borrador: {error}', { error: error.message }), true); }
  });
  $('#dismiss-recovery').addEventListener('click', () => { if (window.confirm(t('¿Descartar el borrador anterior de este navegador?'))) { localStorage.removeItem(DRAFT_KEY); $('#recovery').hidden = true; } });
  window.addEventListener('beforeunload', (event) => { if (state.dirty) { event.preventDefault(); event.returnValue = ''; } });
}

async function start() {
  try {
    const data = await api('bootstrap');
    $('#database-update').hidden = !data.databaseUpdateRequired;
    document.dispatchEvent(new Event('f125-ready'));
    state.csrf = data.csrf; state.catalog = data.catalog.parameters; state.symptoms = data.symptoms; state.circuits = data.circuits; state.setups = data.setups; state.ready = data.storageReady; state.current = blankValues();
    $('#catalog-notice').textContent = data.catalog.notice;
    state.symptoms.items.forEach((s) => $('#symptom').add(new Option(s.label, s.id)));
    renderControls(); refreshLists(); bindEvents();
    try { $('#recovery').hidden = !localStorage.getItem(DRAFT_KEY); } catch { /* El taller funciona sin caché local. */ }
    status(state.ready ? t('Taller listo. Elige un circuito o abre un reglaje guardado.') : t('No se pudo conectar con la base de datos. Puedes seguir editando y exportar tu borrador mientras se recupera la conexión.'), !state.ready);
    // Wait for bootstrap to establish the session cookie before background requests.

  } catch (error) { status(t('No se pudo cargar el taller: {error}', { error: error.message }), true); }
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
  const brand = document.querySelector('.brand');
  if (page === 'inicio') brand.setAttribute('aria-current', 'page');
  else brand.removeAttribute('aria-current');
  document.title = `${document.querySelector('.app-nav [aria-current]')?.textContent || t('Inicio')} · F1 25`;
}
window.addEventListener('hashchange', showPage);
showPage();
