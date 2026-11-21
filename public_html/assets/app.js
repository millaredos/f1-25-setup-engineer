/** Workshop controller: DOM presentation and in-memory editing; the server owns validation and persistence. */
import { t, tn, locale } from '../i18n.php';
const $ = (selector) => document.querySelector(selector);
const DRAFT_KEY = 'f125-engineer-draft-v1';
const state = {
  catalog: [],
  symptoms: null,
  circuits: [],
  setups: [],
  current: {},
  reference: null,
  circuit: null,
  conditions: { surface: 'unknown', session: 'unknown' },
  referenceSource: 'unknown',
  csrf: '',
  ready: false,
  dirty: false,
  title: '',
  notes: '',
};
state.gameCatalog = { circuits: [], presets: [] };
state.season = '2026';
state.viewCircuit = null;
state.draftSeason = 'unknown';
let importMode = 'circuit';
let preview = null;
let compareSequence = 0;
let compareTimer;
let previewSequence = 0;
let statusTimer;
let selectedParameter = null;
let loadMode = 'edit';
let activePage = location.hash.slice(1) || 'inicio';
let navigating = false;
state.setupId = null;
state.groupNotes = {};
state.editing = false;
const groupIds = ['aero', 'transmission', 'geometry', 'suspension', 'brakes', 'tyres'];

// Los datos importados se muestran como texto, nunca como HTML ejecutable.
/** Create DOM with textContent so imported names and comments never become executable markup. */
function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) {
    node.className = className;
  }
  if (text !== undefined) {
    node.textContent = text;
  }
  return node;
}

/** Show recoverable success messages briefly and keep errors visible until the next operation. */
function status(message, error = false) {
  clearTimeout(statusTimer);
  $('#status').textContent = message;
  $('#status').classList.toggle('error', error);
  if (message && !error) {
    statusTimer = setTimeout(() => {
      $('#status').textContent = '';
    }, 5000);
  }
}

/** Send JSON through the shared CSRF session and turn HTTP failures into actionable errors.
 * @returns {Promise<object>} Validated response envelope from the server. */
async function api(action, body) {
  const response = await fetch(
    `api.php?action=${encodeURIComponent(action)}`,
    body === undefined
      ? { cache: 'no-store' }
      : {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf },
          body: JSON.stringify(body),
        }
  );
  let data;
  try {
    data = await response.json();
  } catch {
    throw new Error(
      t('El servidor no ha devuelto JSON válido. Revisa PHP y el registro de errores.')
    );
  }
  if (!response.ok) {
    throw new Error(data.error || t('No se pudo completar la operación.'));
  }
  return data;
}

/** Display setting precision and units consistently; missing values remain visibly empty. */
function format(parameter, value) {
  return value === null || value === undefined
    ? '—'
    : `${Number(value).toLocaleString(locale, { minimumFractionDigits: parameter.dec, maximumFractionDigits: parameter.dec })}${parameter.unit ? ` ${parameter.unit}` : ''}`;
}

/** Create an empty twenty-setting draft without inventing game presets. */
function blankValues() {
  return Object.fromEntries(state.catalog.map((parameter) => [parameter.id, null]));
}

/** Check completeness for UI actions; authoritative ranges and steps are still enforced on the server. */
function complete(values) {
  return (
    values &&
    state.catalog.every(
      (parameter) =>
        typeof values[parameter.id] === 'number' && Number.isFinite(values[parameter.id])
    )
  );
}

/** Snapshot the supported export/storage shape including metadata with no visible editor. */
function payload() {
  return {
    format: 'f125-setup-engineer',
    schemaVersion: 1,
    title: state.title.trim() || t('Reglaje sin nombre'),
    notes: state.notes,
    circuit: state.circuit,
    reference: state.reference,
    current: state.current,
    referenceSource: state.referenceSource,
    conditions: { ...state.conditions },
    season: state.draftSeason,
    groupNotes: { ...state.groupNotes },
  };
}

/** Mark the in-memory draft as changed so leaving the workshop requires a decision. */
function markDraftChanged() {
  state.dirty = true;
}

/** Require confirmation before replacing a meaningful modified draft with an import. */
function allowReplace(message) {
  const hasContent =
    state.reference ||
    Object.values(state.current).some((value) => value !== null) ||
    state.title.trim() ||
    state.notes.trim() ||
    Object.values(state.groupNotes).some((note) => note.trim());
  return !state.dirty || !hasContent || window.confirm(message);
}

/** Describe the draft circuit/season independently of read-only circuit browsing. */
function renderDraftContext() {
  $('#draft-context').textContent = state.circuit
    ? t('Circuito: {circuit} · Temporada: {season}', {
        circuit: state.circuit.name,
        season: state.draftSeason === 'unknown' ? t('Sin indicar') : state.draftSeason,
      })
    : t('Selecciona Nuevo reglaje o Editar reglaje para empezar.');
}

/** Build category navigation, setting rows and group notes once from the server catalog. */
function renderControls() {
  const root = $('#settings');
  const menu = $('#settings-menu');
  root.replaceChildren();
  menu.replaceChildren();
  [...new Set(state.catalog.map((parameter) => parameter.category))].forEach((category, index) => {
    appendParameterGroup(category, index, root, menu);
  });
  const summaryTab = element('button', 'workshop-category', t('Resumen'));
  summaryTab.type = 'button';
  summaryTab.setAttribute('aria-pressed', 'false');
  summaryTab.addEventListener('click', () => {
    selectWorkshopTab(summaryTab);
    $('#settings-layout').hidden = true;
    $('#summary-panel').hidden = false;
  });
  menu.append(summaryTab);
  selectParameter(state.catalog[0]);
  syncControls();
}

/** Select one category or summary tab while keeping its accessibility state synchronized. */
function selectWorkshopTab(activeTab) {
  $('#settings-menu')
    .querySelectorAll('button')
    .forEach((tab) => {
      tab.setAttribute('aria-pressed', String(tab === activeTab));
    });
}

/** Append one category with its controls and persistent group-note identity. */
function appendParameterGroup(category, index, root, menu) {
  const section = element('section', 'parameter-group');
  section.setAttribute('aria-label', category);
  section.hidden = index !== 0;
  const tab = element('button', 'workshop-category', category);
  tab.type = 'button';
  tab.setAttribute('aria-pressed', String(index === 0));
  tab.addEventListener('click', () => {
    root.querySelectorAll('.parameter-group').forEach((group) => {
      group.hidden = group !== section;
    });
    selectWorkshopTab(tab);
    $('#settings-layout').hidden = false;
    $('#summary-panel').hidden = true;
    selectParameter(state.catalog.find((parameter) => parameter.category === category));
  });
  menu.append(tab);
  const grid = element('div', 'parameter-grid');
  state.catalog
    .filter((parameter) => parameter.category === category)
    .forEach((parameter) => grid.append(createParameterCard(parameter)));
  const noteLabel = element('label', 'group-note', t('Comentarios'));
  const note = element('textarea');
  note.rows = 3;
  note.maxLength = 4000;
  note.dataset.groupNote = groupIds[index];
  note.placeholder = t('Anota qué quieres probar o por qué has cambiado estos ajustes.');
  noteLabel.append(note);
  section.append(grid, noteLabel);
  root.append(section);
}

/** Build a selectable row; all catalog labels are inserted as text rather than HTML. */
function createParameterCard(parameter) {
  const card = element('article', 'parameter');
  card.dataset.parameter = parameter.id;
  card.addEventListener('click', () => selectParameter(parameter));
  card.addEventListener('focusin', () => selectParameter(parameter));
  const head = element('div', 'parameter-head');
  const name = element('div');
  const label = element('label', 'parameter-title', parameter.name);
  label.htmlFor = `value-${parameter.id}`;
  name.append(label);
  head.append(name, element('span', 'ref-value', t('Ref. —')));
  card.append(head, createValueControls(parameter));
  return card;
}

/** Build numeric constraints and step controls from the server catalog, without inventing a starting value. */
function createValueControls(parameter) {
  const controls = element('div', 'value-controls');
  const minus = element('button', '', '−');
  minus.type = 'button';
  minus.dataset.spin = '-1';
  minus.setAttribute('aria-label', t('Disminuir {name}', { name: parameter.name }));
  const input = element('input');
  input.type = 'number';
  input.id = `value-${parameter.id}`;
  input.min = parameter.min;
  input.max = parameter.max;
  input.step = parameter.step;
  input.required = true;
  input.placeholder = `${parameter.min}…${parameter.max}`;
  input.inputMode = 'decimal';
  input.setAttribute('aria-describedby', 'parameter-info');
  const plus = element('button', '', '+');
  plus.type = 'button';
  plus.dataset.spin = '1';
  plus.setAttribute('aria-label', t('Aumentar {name}', { name: parameter.name }));
  controls.append(minus, input, plus, element('span', 'unit', parameter.unit));

  return controls;
}

/** Keep keyboard and pointer selection aligned with the contextual explanation. */
function selectParameter(parameter) {
  if (!parameter) {
    return;
  }
  selectedParameter = parameter.id;
  document
    .querySelectorAll('#settings [data-parameter]')
    .forEach((row) => row.classList.toggle('selected', row.dataset.parameter === parameter.id));
  renderParameterInfo(parameter);
}

/** Explain the selected setting and its current theoretical direction without assigning scores. */
function renderParameterInfo(parameter) {
  const root = $('#parameter-info');
  const before = state.reference?.[parameter.id],
    after = state.current[parameter.id];
  root.replaceChildren(
    element('h3', '', parameter.name),
    element('p', '', parameter.what),
    element('strong', '', t('EFECTO TEÓRICO'))
  );
  if (
    before !== undefined &&
    after !== null &&
    after !== undefined &&
    $(`#value-${parameter.id}`).validity.valid &&
    Math.abs(after - before) > 1e-7
  ) {
    root.append(element('p', '', after > before ? parameter.up : parameter.down));
  } else {
    root.append(
      element('p', '', t('Al subir el número: {effect}', { effect: parameter.up })),
      element('p', '', t('Al bajar el número: {effect}', { effect: parameter.down }))
    );
  }
  root.append(element('strong', '', t('Compromisos y límites')), element('p', '', parameter.note));
}

/** Project draft state into existing controls and refresh comparison after a complete replacement. */
function syncControls() {
  state.catalog.forEach((parameter) => {
    $(`#value-${parameter.id}`).value = state.current[parameter.id] ?? '';
    refreshCard(parameter);
  });
  document.querySelectorAll('[data-group-note]').forEach((note) => {
    note.value = state.groupNotes[note.dataset.groupNote] || '';
  });
  $('#editor').classList.toggle('editor-inactive', !state.editing);
  $('#settings-layout').inert = !state.editing;
  $('#settings-menu').inert = !state.editing;
  $('#save').disabled = !state.editing;
  $('#delete-setup').hidden = !state.setupId;
  renderDraftContext();
  scheduleComparison();
}

/** Show the difference from the reference only when the current numeric input is valid. */
function refreshCard(parameter) {
  const card = $(`[data-parameter="${parameter.id}"]`);
  const before = state.reference?.[parameter.id];
  const after = state.current[parameter.id];
  const valid = $(`#value-${parameter.id}`).validity.valid;
  const changed =
    valid && before !== undefined && after !== null && Math.abs(after - before) > 1e-7;
  card.classList.toggle('changed', changed);
  card.querySelector('.ref-value').textContent =
    before === undefined ? '' : t('Inicial: {value}', { value: format(parameter, before) });
  if (selectedParameter === parameter.id) {
    renderParameterInfo(parameter);
  }
}

/** Debounce comparison and reject stale responses so older requests cannot replace a newer summary. */
function scheduleComparison() {
  clearTimeout(compareTimer);
  const sequence = ++compareSequence;
  if (!state.reference) {
    $('#change-count').textContent = t('Sin valores iniciales');
    $('#summary').replaceChildren(
      element('p', 'empty', t('Este reglaje comenzó sin valores predeterminados.'))
    );
    return;
  }
  if (
    !complete(state.current) ||
    ![...document.querySelectorAll('#settings input')].every((input) => input.validity.valid)
  ) {
    $('#change-count').textContent = t('Revisa los valores');
    $('#summary').replaceChildren(
      element(
        'p',
        'empty',
        t('Completa los veinte valores dentro de sus rangos y pasos para obtener el resumen.')
      )
    );
    return;
  }
  $('#change-count').textContent = t('Comparando…');
  $('#summary').replaceChildren(
    element('p', 'empty', t('Actualizando la lectura teórica de los valores actuales…'))
  );
  compareTimer = setTimeout(() => requestComparison(sequence), 200);
}

/** Only the newest request may replace the summary, including its error state. */
async function requestComparison(sequence) {
  try {
    const result = await api('compare', { reference: state.reference, current: state.current });
    if (sequence !== compareSequence) {
      return;
    }
    renderComparisonResult(result);
  } catch (error) {
    if (sequence !== compareSequence) {
      return;
    }
    $('#change-count').textContent = t('Resumen no disponible');
    $('#summary').replaceChildren(element('p', 'error-text', error.message));
  }
}

/** Render independent theoretical changes returned by the domain, without aggregating effects. */
function renderComparisonResult(result) {
  $('#change-count').textContent = tn(
    '{count} de 20 modificado',
    '{count} de 20 modificados',
    result.changes.length
  );
  const root = $('#summary');
  root.replaceChildren();
  if (!result.changes.length) {
    root.append(element('p', 'empty', t('Sin cambios respecto a los valores iniciales.')));
  }
  result.changes.forEach((change) => {
    const parameter = state.catalog.find((item) => item.id === change.id);
    const row = element('div', 'summary-item');
    row.append(
      element('strong', '', change.name),
      element(
        'span',
        'summary-values',
        `${format(parameter, change.before)} → ${format(parameter, change.after)}`
      ),
      element('p', '', t('En teoría: {effect}', { effect: change.theory }))
    );
    root.append(row);
  });
  root.append(element('p', 'source-note', result.notice));
}

const characteristicLabels = {
  corners: t('Curvas'),
  straights: t('Rectas'),
  braking: t('Frenadas'),
  traction: t('Tracción'),
  kerbs: t('Pianos y baches'),
  aero: t('Compromiso aerodinámico'),
};
const downforceLabels = {
  low: t('Carga baja'),
  medium: t('Carga media'),
  high: t('Carga alta'),
  mixed: t('Perfil mixto'),
};

/** Render supported circuit snapshots as text; legacy profiles remain an explicit compatibility path. */
function circuitContent(circuit) {
  if (circuit.origin === 'custom-profile') {
    return customCircuitContent(circuit);
  }
  if (circuit.origin === 'game-catalog') {
    return catalogCircuitContent(circuit);
  }
  return legacyCircuitContent(circuit);
}

/** Present the editable profile fields; absent optional facts are omitted rather than invented. */
function customCircuitContent(circuit) {
  const block = element('div', 'circuit-info');

  block.append(element('h3', '', circuit.name));
  if (circuit.location) {
    block.append(element('p', 'subtle', circuit.location));
  }
  const list = element('dl', 'circuit-characteristics circuit-catalog-description');
  const fields = [
    [
      t('Longitud'),
      circuit.lengthKm == null
        ? ''
        : `${circuit.lengthKm.toLocaleString(locale, { maximumFractionDigits: 20 })} km`,
    ],
    [t('Curvas'), circuit.cornerCount == null ? '' : String(circuit.cornerCount)],
    [t('Categoría de carga'), circuit.downforce],
    [t('Curvas y rectas'), circuit.cornersAndStraights],
    [t('Características de conducción'), circuit.drivingCharacteristics],
  ];
  fields
    .filter(([, value]) => value)
    .forEach(([label, value]) => {
      const pair = element('div');
      pair.append(element('dt', '', label), element('dd', '', value));
      list.append(pair);
    });
  block.append(list);
  return block;
}

/** Join authoritative circuit identity with separately sourced descriptions, never setup values. */
function catalogCircuitContent(circuit) {
  const block = element('div', 'circuit-info');

  block.append(element('h3', '', circuit.name));
  block.append(element('p', 'subtle', circuit.location));
  const description = state.circuitDescriptions?.[circuit.id];
  if (description) {
    const list = element('dl', 'circuit-characteristics circuit-catalog-description');
    const fields = [
      [t('Longitud'), `${description[0]} km`],
      [t('Curvas'), String(description[1])],
      [t('Categoría de carga'), description[2]],
      [t('Curvas y rectas'), description[3]],
      [t('Características de conducción'), description[4]],
    ];
    fields.forEach(([label, value]) => {
      const pair = element('div');
      pair.append(element('dt', '', label), element('dd', '', value));
      list.append(pair);
    });
    block.append(list, sourcesContent(description[5]));
  }
  return block;
}

/** Render the supported v1 snapshot with its source evidence and declared limitations. */
function legacyCircuitContent(circuit) {
  const block = element('div', 'circuit-info');
  const heading = element('div', 'circuit-title');
  heading.append(
    element('h3', '', circuit.name),
    element('span', 'count', downforceLabels[circuit.downforce])
  );
  block.append(
    heading,
    element(
      'p',
      'source-note',
      t('Trazado: {layout} · Versión del juego: {version}', {
        layout: circuit.layout || t('Trazado sin indicar'),
        version: circuit.gameVersion,
      })
    ),
    element('p', 'circuit-summary', circuit.summary)
  );
  const list = element('dl', 'circuit-characteristics');
  Object.entries(characteristicLabels).forEach(([key, label]) => {
    if (!circuit.characteristics[key]) {
      return;
    }
    const pair = element('div');
    pair.append(element('dt', '', label), element('dd', '', circuit.characteristics[key]));
    list.append(pair);
  });
  block.append(list);
  if (circuit.hypotheses) {
    block.append(
      element('p', 'notice', t('Hipótesis y límites: {text}', { text: circuit.hypotheses }))
    );
  }
  block.append(sourcesContent(circuit.sources));
  return block;
}

/** Display server-validated HTTP(S) sources without interpreting imported titles as HTML. */
function sourcesContent(sources) {
  const root = element('details', 'sources');
  root.append(element('summary', '', t('Fuentes y fecha de consulta')));
  sources.forEach((source) => {
    const link = element('a', '', `${source.title} · ${source.accessedAt}`);
    link.href = source.url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    root.append(link);
  });
  return root;
}

/** Refresh only the circuit browsing card; advice continues to use the active workshop context. */
function renderCircuit() {
  const root = $('#circuit-card');
  root.replaceChildren();
  root.hidden = !state.viewCircuit;
  if (state.viewCircuit) {
    root.append(circuitContent(state.viewCircuit));
  }
  renderAdvice();
}

/** Merge catalog identities and stored snapshots by ID, preserving the current snapshot precedence. */
function availableCircuits() {
  return [
    ...new Map(
      [...state.gameCatalog.circuits.map((item) => item.profile), ...state.circuits].map((item) => [
        item.id,
        item,
      ])
    ).values(),
  ];
}

// Browsing a setup never replaces the editable workshop draft.
/** Load a read-only preview; sequence numbers discard older asynchronous selection responses. */
async function renderSetupPreview() {
  const root = $('#setup-preview');
  const id = $('#saved').value;
  const sequence = ++previewSequence;
  root.replaceChildren();
  if (!id) {
    root.append(
      element(
        'p',
        'empty',
        state.season === '2025'
          ? t('Los valores predeterminados de 2025 están pendientes de recopilar.')
          : t('Selecciona un reglaje para ver sus valores.')
      )
    );
    return;
  }
  root.append(element('p', 'empty', t('Cargando reglaje…')));
  try {
    const preset =
      id.startsWith('preset:') &&
      state.gameCatalog.presets.find((item) => `preset:${item.id}` === id);
    const values = preset ? preset.values : (await api('load-setup', { id })).current;
    if (sequence !== previewSequence) {
      return;
    }
    root.replaceChildren();
    renderPreviewValues(root, values);
  } catch (error) {
    if (sequence !== previewSequence) {
      return;
    }
    root.replaceChildren(
      element('p', 'error', t('No se pudo cargar el reglaje: {error}', { error: error.message }))
    );
  }
}

/** Build read-only category navigation inside the current preview container. */
function renderPreviewValues(root, values) {
  const groups = [...new Set(state.catalog.map((parameter) => parameter.category))];
  const menu = element('div', 'setup-preview-menu');
  menu.setAttribute('aria-label', t('Categorías del reglaje'));
  const content = element('div', 'setup-preview-values');
  /** Replace only the selected preview category without modifying workshop values. */
  function showCategory(category, activeButton) {
    menu
      .querySelectorAll('button')
      .forEach((button) => button.setAttribute('aria-pressed', String(button === activeButton)));
    content.replaceChildren(element('h3', '', category));
    const list = element('dl', 'setup-preview-list');
    state.catalog
      .filter((parameter) => parameter.category === category)
      .forEach((parameter) => {
        const row = element('div', 'setup-preview-row');
        row.append(
          element('dt', '', parameter.name),
          element('dd', '', format(parameter, values?.[parameter.id]))
        );
        list.append(row);
      });
    content.append(list);
  }
  groups.forEach((category, index) => {
    const button = element('button', 'setup-preview-category', category);
    button.type = 'button';
    button.addEventListener('click', () => showCategory(category, button));
    menu.append(button);
    if (index === 0) {
      showCategory(category, button);
    } else {
      button.setAttribute('aria-pressed', 'false');
    }
  });
  root.append(menu, content);
}

/** Rebuild browsing choices while retaining the selected setup when it remains available. */
function refreshLists() {
  const select = $('#circuit');
  select.replaceChildren(new Option(t('Selecciona un circuito'), ''));
  const list = availableCircuits();
  if (state.circuit && !list.some((c) => c.id === state.circuit.id)) {
    list.push(state.circuit);
  }
  list.forEach((c) =>
    select.add(
      new Option(c.location && c.location !== c.name ? `${c.location} · ${c.name}` : c.name, c.id)
    )
  );
  select.value = state.viewCircuit?.id || '';
  const saved = $('#saved');
  const selected = saved.value;
  saved.replaceChildren(new Option(t('Selecciona una versión guardada'), ''));
  const record = state.gameCatalog.circuits.find((item) => item.id === state.viewCircuit?.id);
  const preset =
    state.season === '2026'
      ? state.gameCatalog.presets.find((item) => item.id === record?.preset_id)
      : null;
  if (preset) {
    saved.add(
      new Option(
        t('Predeterminado {number} · {name} (juego)', { number: preset.id, name: t(preset.name) }),
        `preset:${preset.id}`
      )
    );
  }
  state.setups
    .filter(
      (s) =>
        (!state.viewCircuit || s.circuit_id === state.viewCircuit.id) &&
        (!s.season || s.season === 'unknown' || s.season === state.season)
    )
    .forEach((s) =>
      saved.add(
        new Option(
          `${s.title} · ${new Date(s.created_at).toLocaleString(locale)}${!s.season || s.season === 'unknown' ? ` · ${t('Temporada sin indicar')}` : ''}`,
          s.id
        )
      )
    );
  saved.value = [...saved.options].some((option) => option.value === selected) ? selected : '';
  if (!saved.value && preset) {
    saved.value = `preset:${preset.id}`;
  }
  renderSetupPreview();
}

/** Describe symptom-led experiments using current draft values and declared circuit context. */
function renderAdvice() {
  const root = $('#advice');
  const symptom = state.symptoms?.items.find((item) => item.id === $('#symptom').value);
  root.replaceChildren();
  if (!symptom) {
    root.append(
      element(
        'p',
        'empty',
        t(
          'Primero identifica en qué fase aparece. Entrada, apoyo y salida no piden las mismas pruebas.'
        )
      )
    );
    return;
  }
  root.append(
    element('p', 'advice-phase', symptom.phase),
    element('p', 'advice-check', symptom.check)
  );
  if (
    state.circuit &&
    (state.circuit.characteristics?.aero ||
      state.circuit.summary ||
      state.circuit.drivingCharacteristics)
  ) {
    root.append(
      element(
        'p',
        'notice',
        t('Contexto de {name}: {text}', {
          name: state.circuit.name,
          text:
            state.circuit.characteristics?.aero ||
            state.circuit.summary ||
            state.circuit.drivingCharacteristics,
        })
      )
    );
  }
  const list = element('ol', 'advice-steps');
  symptom.steps.forEach((step) => list.append(createAdviceStep(step)));
  const combined = element('div', 'combined');
  combined.append(
    element('strong', '', t('DESPUÉS, SI PROCEDE')),
    element('p', '', symptom.combined)
  );
  root.append(
    list,
    combined,
    element('p', 'source-note', state.symptoms.notice),
    sourcesContent(state.symptoms.sources)
  );
}

/** Show one theoretical experiment and its bounded next step from the active draft value. */
function createAdviceStep(step) {
  const parameter = state.catalog.find((item) => item.id === step.id);
  const item = element('li');
  item.append(
    element('strong', '', parameter.name),
    element('p', '', step.why),
    element('p', 'cost', t('Compromiso: {cost}', { cost: step.cost }))
  );
  const value = state.current[parameter.id];
  if (value !== null && value !== undefined && step.direction !== 'inspect') {
    const next = Number(
      (value + (step.direction === 'up' ? parameter.step : -parameter.step)).toFixed(parameter.dec)
    );
    item.append(
      element(
        'p',
        next < parameter.min || next > parameter.max ? 'boundary' : 'source-note',
        next < parameter.min || next > parameter.max
          ? t(
              'Ya estás en el límite para esta dirección. No fuerces un valor fuera de rango; pasa a la siguiente prueba.'
            )
          : t('Un paso desde tu valor actual: {before} → {after}. Tú decides si probarlo.', {
              before: format(parameter, value),
              after: format(parameter, next),
            })
      )
    );
  }
  return item;
}

/** Install validated values and metadata into memory; caller owns editing identity and dirty state. */
function installDraft(data) {
  state.current = { ...data.current };
  state.reference = data.reference ? { ...data.reference } : null;
  state.referenceSource = data.referenceSource || 'unknown';
  state.circuit = data.circuit || null;
  state.draftSeason = data.season || 'unknown';
  state.groupNotes = { ...(data.groupNotes || {}) };
  state.title = data.title || '';
  state.notes = data.notes || '';
  // Existing imports and saved versions keep their metadata; choosing a preset does not invent conditions.
  state.conditions = {
    surface: data.conditions?.surface || 'unknown',
    session: data.conditions?.session || 'unknown',
  };
  syncControls();
  refreshLists();
  renderCircuit();
}

/** Keep creation and saved-editing choices separate and require a season/circuit selection. */
function refreshLoadDialog() {
  const year = $('#load-season').value;
  const circuitId = $('#load-circuit').value;
  const select = $('#load-setup');
  select.replaceChildren(new Option(t('Selecciona un reglaje'), ''));
  const record = state.gameCatalog.circuits.find((item) => item.id === circuitId);
  const preset =
    year === '2026'
      ? state.gameCatalog.presets.find((item) => item.id === record?.preset_id)
      : null;
  state.setups
    .filter((item) => item.circuit_id === circuitId && item.season === year)
    .forEach((item) => {
      select.add(
        new Option(`${item.title} · ${new Date(item.created_at).toLocaleString(locale)}`, item.id)
      );
    });
  $('#apply-saved-setup').disabled = loadMode === 'new' ? !circuitId || !year : true;
  $('#load-setup-label').hidden = loadMode === 'new';
  $('#load-setup-help').textContent = !year
    ? t('Selecciona una temporada.')
    : !circuitId
      ? t('Selecciona un circuito.')
      : loadMode === 'new'
        ? preset
          ? t('Se cargará el predeterminado del juego.')
          : t('No hay predeterminado disponible; introduce los valores manualmente.')
        : select.options.length === 1
          ? t('No hay reglajes disponibles para este circuito y temporada.')
          : '';
}

/** Prepare the shared new/edit dialog without changing the draft until a selection is applied. */
function openSavedSetup(mode = 'edit') {
  loadMode = mode;
  $('#saved-setup-title').textContent = mode === 'new' ? t('Nuevo reglaje') : t('Editar reglaje');
  $('#apply-saved-setup').textContent = mode === 'new' ? t('Aceptar') : t('Abrir reglaje');
  const select = $('#load-circuit');
  select.replaceChildren(new Option(t('Selecciona un circuito'), ''));
  availableCircuits().forEach((circuit) =>
    select.add(
      new Option(
        circuit.location && circuit.location !== circuit.name
          ? `${circuit.location} · ${circuit.name}`
          : circuit.name,
        circuit.id
      )
    )
  );
  $('#load-season').value = ['2025', '2026'].includes(state.draftSeason) ? state.draftSeason : '';
  select.value = state.circuit?.id || '';
  refreshLoadDialog();
  $('#saved-setup-dialog').showModal();
}

/** Reset the shared import dialog; only circuit imports need the template and FAQ. */
function openImport(mode) {
  importMode = mode;
  preview = null;
  $('#circuit-template').hidden = mode !== 'circuit';
  $('#circuit-faq').hidden = mode !== 'circuit';
  $('#circuit-template-help').hidden = mode !== 'circuit';
  $('#import-title').textContent =
    mode === 'circuit' ? t('Pegar ficha de circuito') : t('Importar reglaje');
  $('#import-help').textContent =
    mode === 'circuit'
      ? t('Descarga la plantilla, completa los campos y selecciona el archivo para validarlo.')
      : t('Selecciona un archivo JSON de reglaje para revisarlo en el taller.');
  $('#json-input').value = '';
  $('#json-file').value = '';
  $('#import-preview').replaceChildren();
  $('#import-error').textContent = '';
  $('#apply-import').hidden = true;
  $('#import-dialog').showModal();
  if (mode === 'setup') {
    $('#json-file').click();
  }
}

/** Invalidate the validated snapshot whenever the source JSON changes. */
function clearPreview() {
  preview = null;
  $('#apply-import').hidden = true;
  $('#import-preview').replaceChildren();
  $('#import-error').textContent = '';
}

/** Ask the domain to validate external JSON before displaying or applying its snapshot. */
async function validateImport() {
  clearPreview();
  try {
    const raw = JSON.parse($('#json-input').value);
    const result = await api(importMode === 'circuit' ? 'import-circuit' : 'import-setup', raw);
    preview = result;
    const root = $('#import-preview');
    if (importMode === 'circuit') {
      root.append(circuitContent(result.circuit));
      $('#apply-import').textContent = t('Guardar ficha');
    } else {
      root.append(
        element(
          'p',
          'notice',
          [
            ...result.warnings,
            t('Se importarán referencia y actuales. No se sobrescribe ninguna versión guardada.'),
          ].join(' ')
        )
      );
      if (result.circuit) {
        root.append(circuitContent(result.circuit));
      } else {
        root.append(
          element(
            'p',
            'source-note',
            t('Este JSON no incluye circuito: tendrás que asociar una ficha antes de guardar.')
          )
        );
      }
      const table = element('table', 'preview-values');
      const head = element('tr');
      [t('Parámetro'), t('Referencia'), t('Actual')].forEach((label) =>
        head.append(element('th', '', label))
      );
      table.append(head);
      state.catalog.forEach((parameter) => {
        const row = element('tr');
        row.append(
          element('td', '', parameter.name),
          element('td', '', format(parameter, result.reference[parameter.id])),
          element('td', '', format(parameter, result.current[parameter.id]))
        );
        table.append(row);
      });
      root.append(table);
      $('#apply-import').textContent = t('Usar como borrador');
    }
    $('#apply-import').hidden = false;
  } catch (error) {
    $('#import-error').textContent = error.message;
  }
}

/** Persist a validated circuit or install a new unsaved setup, preserving its explicit reference. */
async function applyImport() {
  if (!preview) {
    return;
  }
  if (
    importMode !== 'circuit' &&
    !allowReplace(t('Esta importación sustituirá el borrador en pantalla. ¿Continuar?'))
  ) {
    return;
  }
  const button = $('#apply-import');
  button.disabled = true;
  try {
    if (importMode === 'circuit') {
      const result = await api('save-circuit', {
        format: 'f125-circuit',
        schemaVersion: preview.circuit.origin === 'custom-profile' ? 2 : 1,
        circuit: preview.circuit,
      });
      state.circuits = result.circuits;
      state.viewCircuit = preview.circuit;
      refreshLists();
      renderCircuit();
      status(t('Ficha de circuito guardada.'));
    } else {
      state.setupId = null;
      state.editing = true;
      installDraft(preview);
      markDraftChanged();
      status(t('Reglaje importado. Revisa los valores y guárdalo cuando quieras.'));
    }
    $('#import-dialog').close();
  } catch (error) {
    $('#import-error').textContent = error.message;
  } finally {
    button.disabled = false;
  }
}

/** Validate a complete snapshot before downloading JSON without saving a database record. */
async function exportSetup() {
  try {
    if (!complete(state.current)) {
      throw new Error(t('Completa los veinte valores antes de exportar.'));
    }
    const input = payload();
    if (!input.reference) {
      input.reference = { ...input.current };
      input.referenceSource = 'personal';
    }
    await api('import-setup', input);
    const exported = { ...input, created: new Date().toISOString() };
    const blob = new Blob([JSON.stringify(exported, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = element('a');
    link.href = url;
    link.download = `F1_25_${state.circuit?.id || 'borrador'}.json`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    status(t('JSON exportado. El archivo conserva referencia y actuales.'));
  } catch (error) {
    status(error.message, true);
  }
}

/** Collect missing save metadata and resolve false on cancellation without persisting. */
function askSetupName() {
  const dialog = $('#save-dialog'),
    circuit = $('#save-circuit');
  $('#save-name').value = state.title;
  $('#save-season').value = ['2025', '2026'].includes(state.draftSeason) ? state.draftSeason : '';
  $('#save-season-label').hidden = !!$('#save-season').value;
  circuit.replaceChildren(new Option(t('Selecciona un circuito'), ''));
  const circuits = availableCircuits();
  if (state.circuit && !circuits.some((item) => item.id === state.circuit.id)) {
    circuits.push(state.circuit);
  }
  circuits.forEach((item) => circuit.add(new Option(item.name, item.id)));
  circuit.value = state.circuit?.id || '';
  $('#save-circuit-label').hidden = !!state.circuit;
  $('#save-dialog-error').textContent = '';
  return new Promise((resolve) => {
    $('#save-cancel').onclick = () => {
      dialog.close();
      resolve(false);
    };
    dialog.oncancel = (event) => {
      event.preventDefault();
      dialog.close();
      resolve(false);
    };
    $('#save-dialog-form').onsubmit = (event) => {
      event.preventDefault();
      const name = $('#save-name').value.trim();
      if (!name || !$('#save-season').value || !circuit.value) {
        $('#save-dialog-error').textContent = t('Indica nombre, temporada y circuito.');
        return;
      }
      state.title = name;
      state.draftSeason = $('#save-season').value;
      state.circuit = circuits.find((item) => item.id === circuit.value);
      dialog.close();
      resolve(true);
    };
    dialog.showModal();
    $('#save-name').focus();
  });
}

/** Create or update the selected setup, then start a new in-memory comparison baseline on success. */
async function saveWorkshop() {
  const button = $('#save');
  button.disabled = true;
  try {
    if (!state.editing) {
      throw new Error(t('Crea o abre un reglaje antes de guardar.'));
    }
    if (
      !complete(state.current) ||
      ![...document.querySelectorAll('#settings input')].every((input) => input.validity.valid)
    ) {
      throw new Error(t('Completa los veinte valores dentro de sus rangos y pasos.'));
    }
    if (!state.setupId && !(await askSetupName())) {
      return false;
    }
    const data = payload();
    if (!data.reference) {
      data.reference = { ...data.current };
      data.referenceSource = 'personal';
    }
    const result = state.setupId
      ? await api('update-setup', { id: state.setupId, setup: data })
      : await api('save-setup', data);
    state.setupId = state.setupId || result.id;
    state.setups = result.setups;
    state.circuits = result.circuits;
    state.reference = { ...state.current };
    state.referenceSource = 'personal';
    state.dirty = false;
    try {
      localStorage.removeItem(DRAFT_KEY);
    } catch {
      /* El guardado en servidor ya se ha completado. */
    }
    refreshLists();
    syncControls();
    status(t('Reglaje guardado.'));
    return true;
  } catch (error) {
    status(error.message, true);
    return false;
  } finally {
    button.disabled = !state.editing;
  }
}

/** Clear editing identity and values without deleting any saved setup. */
function discardWorkshop() {
  state.dirty = false;
  state.setupId = null;
  state.editing = false;
  state.groupNotes = {};
  state.current = blankValues();
  state.reference = null;
  state.circuit = null;
  state.draftSeason = 'unknown';
  state.title = '';
  state.notes = '';
  try {
    localStorage.removeItem(DRAFT_KEY);
  } catch {
    /* La caché del navegador es opcional. */
  }
  syncControls();
}

/** Run navigation only after save/discard; cancellation retains the active draft. */
function requestLeave(next) {
  if (!state.dirty) {
    if (state.editing) {
      discardWorkshop();
    }
    next();
    return;
  }
  const dialog = $('#unsaved-dialog');
  const finish = (action) => {
    dialog.close();
    if (action) {
      next();
    }
  };
  $('#unsaved-save').onclick = async () => {
    dialog.close();
    if (await saveWorkshop()) {
      discardWorkshop();
      next();
    }
  };
  $('#unsaved-discard').onclick = () => {
    discardWorkshop();
    finish(true);
  };
  $('#unsaved-cancel').onclick = () => finish(false);
  dialog.showModal();
}

/** Connect creation and editing choices without coupling them to circuit browsing. */
function bindSetupDialogEvents() {
  $('#new-setup').addEventListener('click', () => requestLeave(() => openSavedSetup('new')));
  $('#open-saved-setup').addEventListener('click', () =>
    requestLeave(() => openSavedSetup('edit'))
  );
  $('#load-season').addEventListener('change', refreshLoadDialog);
  $('#load-circuit').addEventListener('change', refreshLoadDialog);
  $('#load-setup').addEventListener('change', () => {
    $('#apply-saved-setup').disabled = !$('#load-setup').value;
  });
  $('#apply-saved-setup').addEventListener('click', loadWorkshopSelection);
}

/** Apply a confirmed dialog choice; saved editing starts a fresh comparison baseline. */
async function loadWorkshopSelection() {
  const year = $('#load-season').value,
    circuitId = $('#load-circuit').value,
    id = $('#load-setup').value;
  const circuit = availableCircuits().find((item) => item.id === circuitId);
  if (!circuit || !year || (loadMode === 'edit' && !id)) {
    return;
  }
  const button = $('#apply-saved-setup');
  button.disabled = true;
  try {
    if (loadMode === 'new') {
      const record = state.gameCatalog.circuits.find((item) => item.id === circuitId);
      const preset =
        year === '2026' && state.gameCatalog.presets.find((item) => item.id === record?.preset_id);
      const values = preset ? { ...preset.values } : blankValues();
      state.setupId = null;
      state.editing = true;
      installDraft({
        circuit,
        season: year,
        current: values,
        reference: preset ? { ...values } : null,
        referenceSource: preset ? 'game-assigned' : 'unknown',
        title: '',
        conditions: { surface: 'unknown', session: 'unknown' },
      });
    } else {
      const setup = await api('load-setup', { id });
      if (setup.circuit?.id !== circuitId || setup.season !== year) {
        throw new Error(
          t('El reglaje ya no corresponde al circuito o la temporada seleccionados.')
        );
      }
      state.setupId = id;
      state.editing = true;
      installDraft({ ...setup, reference: { ...setup.current }, referenceSource: 'personal' });
    }
    state.dirty = loadMode === 'new';
    $('#saved-setup-dialog').close();
    status(t('Reglaje cargado en el taller.'));
  } catch (error) {
    $('#load-setup-help').textContent = error.message;
  } finally {
    button.disabled = false;
  }
}

/** Refresh read-only circuit/setup views and symptom advice. */
function bindBrowseEvents() {
  $('#season').addEventListener('change', () => {
    state.season = $('#season').value;
    refreshLists();
    renderCircuit();
  });
  $('#circuit').addEventListener('change', () => {
    state.viewCircuit = availableCircuits().find((c) => c.id === $('#circuit').value) || null;
    refreshLists();
    renderCircuit();
  });
  $('#saved').addEventListener('change', renderSetupPreview);
  $('#symptom').addEventListener('change', renderAdvice);
}

/** Connect parameter changes and persistence actions to the active draft. */
function bindEditorEvents() {
  $('#settings').addEventListener('input', (event) => {
    if (event.target.matches('[data-group-note]')) {
      state.groupNotes[event.target.dataset.groupNote] = event.target.value;
      markDraftChanged();
      return;
    }
    if (!event.target.matches('input')) {
      return;
    }
    const parameter = state.catalog.find((item) => `value-${item.id}` === event.target.id);
    state.current[parameter.id] =
      event.target.value === '' || !Number.isFinite(event.target.valueAsNumber)
        ? null
        : event.target.valueAsNumber;
    refreshCard(parameter);
    markDraftChanged();
    scheduleComparison();
    renderAdvice();
  });
  $('#settings').addEventListener('click', (event) => {
    const button = event.target.closest('[data-spin]');
    if (!button) {
      return;
    }
    const parameter = state.catalog.find(
      (item) => item.id === button.closest('[data-parameter]').dataset.parameter
    );
    const input = $(`#value-${parameter.id}`);
    if (!input.validity.valid) {
      input.reportValidity();
      return;
    }
    const value = Number(
      (state.current[parameter.id] + Number(button.dataset.spin) * parameter.step).toFixed(
        parameter.dec
      )
    );
    if (value < parameter.min || value > parameter.max) {
      status(t('{name}: ya estás en el límite permitido.', { name: parameter.name }), true);
      return;
    }
    state.current[parameter.id] = value;
    input.value = value;
    refreshCard(parameter);
    markDraftChanged();
    scheduleComparison();
    renderAdvice();
  });
  $('#export-workshop').addEventListener('click', () => exportSetup());
  $('#save').addEventListener('click', saveWorkshop);
  $('#delete-setup').addEventListener('click', async () => {
    if (
      !state.setupId ||
      !window.confirm(t('¿Eliminar este reglaje? Esta acción no se puede deshacer.'))
    ) {
      return;
    }
    try {
      const result = await api('delete-setup', { id: state.setupId });
      state.setups = result.setups;
      discardWorkshop();
      refreshLists();
      status(t('Reglaje eliminado.'));
    } catch (error) {
      status(error.message, true);
    }
  });
}

/** Validate external JSON and invalidate previews whenever its source changes. */
function bindImportEvents() {
  $('#open-circuit').addEventListener('click', () => openImport('circuit'));
  $('#circuit-template').addEventListener('click', () => {
    const template = {
      format: 'f125-circuit',
      schemaVersion: 2,
      circuit: {
        nombre: '',
        ubicacion: '',
        longitud_km: null,
        curvas: null,
        categoria_carga: '',
        curvas_y_rectas: '',
        caracteristicas_conduccion: '',
      },
    };
    const url = URL.createObjectURL(
      new Blob([JSON.stringify(template, null, 2) + '\n'], { type: 'application/json' })
    );
    const link = element('a');
    link.href = url;
    link.download = 'plantilla-circuito.json';
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  });
  $('#open-setup').addEventListener('click', () => openImport('setup'));
  $('#json-input').addEventListener('input', clearPreview);
  $('#json-file').addEventListener('change', async (event) => {
    clearPreview();
    const file = event.target.files[0];
    if (!file) {
      return;
    }
    if (file.size > 262144) {
      $('#import-error').textContent = t('El JSON supera 256 KiB.');
      return;
    }
    $('#json-input').value = await file.text();
    if (importMode === 'setup') {
      await validateImport();
      if (preview) {
        await applyImport();
      }
    }
  });
  $('#validate-import').addEventListener('click', validateImport);
  $('#apply-import').addEventListener('click', applyImport);
}

/** Offer recovery of older browser drafts; never create new persistent browser drafts. */
function bindRecoveryEvents() {
  $('#recover').addEventListener('click', recoverBrowserDraft);
  $('#dismiss-recovery').addEventListener('click', () => {
    if (window.confirm(t('¿Descartar el borrador anterior de este navegador?'))) {
      localStorage.removeItem(DRAFT_KEY);
      $('#recovery').hidden = true;
    }
  });
  window.addEventListener('beforeunload', (event) => {
    if (state.dirty) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
}

/** Revalidate old browser data through the server before restoring partial values and metadata. */
async function recoverBrowserDraft() {
  try {
    const raw = JSON.parse(localStorage.getItem(DRAFT_KEY));
    state.editing = true;
    state.setupId = null;
    // Revalidar en servidor: localStorage también puede contener datos ajenos o antiguos.
    if (complete(raw.current) && raw.reference) {
      installDraft(await api('import-setup', raw));
    } else {
      // Legacy defaults are temporary validation fillers, not recovered user values or a reference.
      // Restore empty slots to null after the server has validated the available values and metadata.
      const valid = await api('import-setup', {
        ...raw,
        current: Object.fromEntries(
          state.catalog.map((parameter) => [
            parameter.id,
            raw.current?.[parameter.id] ?? parameter.base,
          ])
        ),
        reference: raw.reference || undefined,
      });
      const partial = Object.fromEntries(
        state.catalog.map((parameter) => [parameter.id, raw.current?.[parameter.id] ?? null])
      );
      installDraft({ ...valid, current: partial, reference: raw.reference || null });
    }
    state.dirty = true;
    $('#recovery').hidden = true;
    status(t('Borrador recuperado; todavía no es una versión guardada en MariaDB.'));
  } catch (error) {
    status(t('No se pudo recuperar el borrador: {error}', { error: error.message }), true);
  }
}

/** Register UI handlers once, after bootstrap has supplied the catalog and CSRF token. */
function bindEvents() {
  bindSetupDialogEvents();
  bindBrowseEvents();
  bindEditorEvents();
  bindImportEvents();
  bindRecoveryEvents();
}

/** Bootstrap rules and storage metadata before creating controls or binding handlers. */
async function start() {
  try {
    const data = await api('bootstrap');
    state.gameCatalog = data.gameCatalog || { circuits: [], presets: [] };
    state.circuitDescriptions = data.circuitDescriptions || {};
    $('#database-update').hidden = !data.databaseUpdateRequired;
    document.dispatchEvent(new Event('f125-ready'));
    state.csrf = data.csrf;
    state.catalog = data.catalog.parameters;
    state.symptoms = data.symptoms;
    state.circuits = data.circuits;
    state.setups = data.setups;
    state.ready = data.storageReady;
    state.current = blankValues();
    state.symptoms.items.forEach((s) => $('#symptom').add(new Option(s.label, s.id)));
    renderControls();
    refreshLists();
    bindEvents();
    try {
      $('#recovery').hidden = !localStorage.getItem(DRAFT_KEY);
    } catch {
      /* El taller funciona sin caché local. */
    }
    status(
      state.ready
        ? ''
        : t(
            'No se pudo conectar con la base de datos. Puedes seguir editando y exportar tu borrador mientras se recupera la conexión.'
          ),
      !state.ready
    );
    // Wait for bootstrap to establish the session cookie before background requests.
  } catch (error) {
    status(t('No se pudo cargar el taller: {error}', { error: error.message }), true);
  }
}

start();

// Mantener las secciones en el mismo documento conserva el borrador al navegar.
/** Project supported hash routes into visible sections while keeping one in-memory workshop. */
function showPage() {
  const requested = location.hash.slice(1) || 'inicio';
  const page = ['inicio', 'circuito', 'taller', 'pruebas'].includes(requested)
    ? requested
    : 'inicio';
  document.querySelector('.workspace').hidden = !['taller', 'pruebas'].includes(page);
  document.querySelectorAll('[data-page]').forEach((section) => {
    section.hidden = section.dataset.page !== page;
  });
  document.querySelectorAll('.app-nav a').forEach((link) => {
    if (link.hash === `#${page}`) {
      link.setAttribute('aria-current', 'page');
    } else {
      link.removeAttribute('aria-current');
    }
  });
  const brand = document.querySelector('.brand');
  if (page === 'inicio') {
    brand.setAttribute('aria-current', 'page');
  } else {
    brand.removeAttribute('aria-current');
  }
  document.title = `${document.querySelector('.app-nav [aria-current]')?.textContent || t('Inicio')} · F1 25 / 26`;
}
window.addEventListener('hashchange', () => {
  const target = location.hash.slice(1) || 'inicio';
  if (!navigating && activePage === 'taller' && target !== 'taller' && state.editing) {
    navigating = true;
    history.replaceState(null, '', '#taller');
    requestLeave(() => {
      activePage = target;
      location.hash = target;
      showPage();
    });
    navigating = false;
    return;
  }
  activePage = target;
  showPage();
});
document.addEventListener('click', (event) => {
  const link = event.target.closest('a[href]');
  if (
    !link ||
    activePage !== 'taller' ||
    !state.editing ||
    link.target === '_blank' ||
    link.hash ||
    event.ctrlKey ||
    event.metaKey ||
    event.shiftKey
  ) {
    return;
  }
  event.preventDefault();
  requestLeave(() => {
    location.href = link.href;
  });
});
showPage();
