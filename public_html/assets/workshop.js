/** Coordinates workspace views and events; draft state and HTTP transport have separate owners. */
import { Api } from './api.js';
import { Draft } from './draft.js';

const api = new Api();
const draft = new Draft();
const $ = (selector) => document.querySelector(selector);
let catalog;
let sequence = 0;
let timer;
let preview;
let previewId = null;
const groups = ['aero', 'transmission', 'geometry', 'suspension', 'brakes', 'tyres'];

/** External names and notes are always text nodes, never interpreted HTML. */
function node(tag, text, className = '') {
    const result = document.createElement(tag);
    if (text !== undefined) { result.textContent = text; }
    result.className = className;
    return result;
}

/** Keep failures visible until another operation reports a result. */
function status(message, error = false) {
    $('#status').textContent = message;
    $('#status').hidden = !message;
    $('#status').classList.toggle('alert-error', error);
}

/** Present only supported workspace pages while retaining one in-memory draft. */
function page(name, updateUrl = true) {
    document.querySelectorAll('[data-panel]').forEach((panel) => { panel.hidden = panel.dataset.panel !== name; });
    document.body.dataset.page = name;
    document.querySelectorAll('nav a').forEach((link) => {
        const current = new URL(link.href).searchParams.get('page') === name;
        if (current) { link.setAttribute('aria-current', 'page'); } else { link.removeAttribute('aria-current'); }
    });
    if (updateUrl) { history.pushState({}, '', `?page=${name}`); }
}

/** Catalog selection belongs to browsing and never changes an already-open draft. */
function edition() { return catalog.editions[$('#season').value]; }

/** Merge distributed identities with personal profiles without replacing a distributed identity. */
function availableCircuits(season = $('#season').value) {
    const rows = new Map((catalog.editions[season]?.circuits || []).map((row) => [row.id, row]));
    catalog.circuits.forEach((row) => {
        if (!rows.has(row.id) && row.origin !== 'game-catalog') { rows.set(row.id, row); }
    });
    return [...rows.values()];
}

/** Bind stable circuit identities to option values while keeping labels as text. */
function refreshCircuits() {
    const current = $('#circuit').value;
    $('#circuit').replaceChildren(new Option('Selecciona un circuito', ''));
    availableCircuits().forEach((row) => $('#circuit').add(new Option(row.name, row.id)));
    if ([...$('#circuit').options].some((option) => option.value === current)) { $('#circuit').value = current; }
    refreshSaved();
}

/** Convert distributed rows to the published identity snapshot only when a draft is created. */
function selectedCircuit() {
    const row = availableCircuits().find((item) => item.id === $('#circuit').value);
    return row ? (row.origin ? JSON.parse(JSON.stringify(row)) : { id: row.id, origin: 'game-catalog', name: row.name, location: row.location }) : null;
}

/** Require an explicit discard decision before replacing any dirty draft, including notes-only drafts. */
function canReplace() { return !draft.dirty || confirm('Hay cambios sin guardar. ¿Descartarlos y continuar?'); }

/** New drafts use only a confirmed preset for the selected edition; otherwise inputs remain empty. */
function createDraft() {
    const circuit = selectedCircuit();
    if (!circuit) { throw new Error('Selecciona un circuito.'); }
    if (!canReplace()) { return; }
    const row = edition().circuits.find((item) => item.id === circuit.id);
    const preset = row ? edition().presets[row.preset_id] : null;
    const values = preset ? { ...preset } : Object.fromEntries(edition().parameters.map((p) => [p.id, null]));
    draft.open({ format: 'f125-setup-engineer', schemaVersion: 1, season: $('#season').value, circuit, title: '', notes: '', reference: preset ? { ...values } : null, current: values, referenceSource: preset ? 'game-assigned' : 'unknown', conditions: { surface: 'unknown', session: 'unknown' }, groupNotes: {} }, null, true);
    renderEditor();
    page('workshop');
}

/** Render the draft from its own edition, even when the browse selectors point elsewhere. */
function renderEditor() {
    const value = draft.value;
    $('#draft-empty').hidden = !!value;
    $('#editor').hidden = !value;
    if (!value) { return; }
    $('#draft-context').textContent = `${value.circuit?.name || 'Sin circuito'} · ${value.season}`;
    $('#setup-title').value = value.title;
    $('#setup-notes').value = value.notes;
    $('#surface').value = value.conditions.surface;
    $('#session').value = value.conditions.session;
    const definitions = catalog.editions[value.season].parameters;
    const categories = [...new Set(definitions.map((p) => p.category))];
    $('#parameter-groups').replaceChildren();
    $('#category-tabs').replaceChildren();
    categories.forEach((category, groupIndex) => {
        const section = node('section', undefined, 'panel parameter-group');
        section.hidden = groupIndex !== 0;
        const tab = node('button', category, 'tab');
        tab.type = 'button';
        tab.setAttribute('aria-pressed', String(groupIndex === 0));
        tab.addEventListener('click', () => {
            $('#parameter-info').hidden = false;
            document.querySelectorAll('.parameter-group').forEach((group) => { group.hidden = group !== section; });
            $('#category-tabs').querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', String(button === tab)));
            showParameter(definitions.find((parameter) => parameter.category === category));
        });
        $('#category-tabs').append(tab);
        section.append(node('h2', category));
        const grid = node('div', undefined, 'parameter-grid');
        definitions.filter((p) => p.category === category).forEach((parameter) => grid.append(parameterControl(parameter)));
        section.append(grid);
        const label = node('label', 'Comentarios del grupo');
        const notes = node('textarea');
        notes.maxLength = 4000;
        notes.value = value.groupNotes[groups[groupIndex]] || '';
        notes.addEventListener('input', () => { value.groupNotes[groups[groupIndex]] = notes.value; draft.changed(); });
        label.append(notes);
        section.append(label);
        $('#parameter-groups').append(section);
    });
    appendSummary(definitions);
    $('#parameter-info').hidden = false;
    showParameter(definitions[0]);
    scheduleComparison();
}

/** Review all values together without duplicating editable controls or creating another draft. */
function appendSummary(definitions) {
    const section = node('section', undefined, 'panel parameter-group table-scroll');
    section.hidden = true;
    const tab = node('button', 'Resumen', 'tab');
    tab.type = 'button';
    tab.setAttribute('aria-pressed', 'false');
    tab.addEventListener('click', () => {
        const table = node('table');
        const header = node('tr');
        ['Ajuste', 'Referencia', 'Actual'].forEach((label) => header.append(node('th', label)));
        table.append(header);
        definitions.forEach((parameter) => {
            const row = node('tr');
            row.append(node('th', parameter.name), node('td', draft.value.reference?.[parameter.id] ?? 'Sin fijar'), node('td', draft.value.current[parameter.id] ?? 'Sin valor'));
            table.append(row);
        });
        section.replaceChildren(node('h2', 'Resumen del reglaje'), table);
        document.querySelectorAll('.parameter-group').forEach((group) => { group.hidden = group !== section; });
        $('#category-tabs').querySelectorAll('button').forEach((button) => button.setAttribute('aria-pressed', String(button === tab)));
        $('#parameter-info').hidden = true;
    });
    $('#category-tabs').append(tab);
    $('#parameter-groups').append(section);
}

/** Selection changes the explanatory panel, never the draft's numerical values. */
function showParameter(parameter) {
    $('#parameter-info').replaceChildren(node('h3', parameter.name), node('p', parameter.what), node('strong', 'EFECTO TEÓRICO'), node('p', `Al subir: ${parameter.up}`), node('p', `Al bajar: ${parameter.down}`), node('strong', 'Compromisos y límites'), node('p', parameter.note));
}

/** Explain the parameter's intent and constraints next to a numeric input without silently coercing it. */
function parameterControl(parameter) {
    const card = node('article', undefined, 'parameter');
    const label = node('label', parameter.name + (parameter.unit ? ` (${parameter.unit})` : ''));
    const input = node('input');
    input.type = 'number';
    input.min = parameter.min;
    input.max = parameter.max;
    input.step = parameter.step;
    input.value = draft.value.current[parameter.id] ?? '';
    input.dataset.parameter = parameter.id;
    input.setAttribute('aria-label', parameter.name);
    input.addEventListener('focus', () => showParameter(parameter));
    input.addEventListener('input', () => {
        draft.value.current[parameter.id] = input.value === '' ? null : Number(input.value);
        draft.changed();
        scheduleComparison();
    });
    label.append(input);
    card.append(label, node('p', `Referencia: ${draft.value.reference?.[parameter.id] ?? 'Sin fijar'}`, 'reference'));
    return card;
}

/** Discard stale comparison responses so typing cannot display a previous snapshot's interpretation. */
function scheduleComparison() {
    clearTimeout(timer);
    const request = ++sequence;
    timer = setTimeout(async () => {
        const value = draft.value;
        if (!value?.reference || Object.values(value.current).some((item) => typeof item !== 'number' || !Number.isFinite(item))) {
            $('#comparison').replaceChildren(node('p', 'Completa los valores para comparar con la referencia.'));
            return;
        }
        try {
            const result = await api.request('compare', { season: value.season, reference: value.reference, current: value.current });
            if (request !== sequence) { return; }
            $('#comparison-notice').textContent = result.notice;
            $('#comparison').replaceChildren(...result.changes.map((item) => {
                const block = node('div', undefined, 'change');
                block.append(node('strong', `${item.name}: ${item.before} → ${item.after}`), node('p', item.theory), node('p', item.caution, 'muted'));
                return block;
            }));
        } catch (error) { if (request === sequence) { $('#comparison').textContent = error.message; } }
    }, 250);
}

/** Saving is always explicit; a manually entered draft offers a reference decision rather than inventing one. */
async function save(copy = false) {
    if (!draft.value) { return; }
    if (!draft.value.title.trim()) { throw new Error('Indica un nombre.'); }
    if (!draft.value.reference) {
        if (!confirm('¿Usar los valores actuales como referencia personal?')) { return; }
        draft.value.reference = { ...draft.value.current };
        draft.value.referenceSource = 'personal';
    }
    const value = draft.snapshot();
    const result = await api.request(draft.id && !copy ? 'update-setup' : 'save-setup', draft.id && !copy ? { id: draft.id, setup: value } : value);
    draft.id = result.id || draft.id;
    draft.dirty = false;
    draft.value.reference = { ...draft.value.current };
    draft.value.referenceSource = 'personal';
    renderEditor();
    catalog.setups = result.setups;
    catalog.circuits = result.circuits;
    refreshSaved();
    status('Reglaje guardado.');
}

/** Download only the current explicit snapshot; no backend or browser persistence is implied. */
function download(name, data) {
    const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2) + '\n'], { type: 'application/json' }));
    const link = node('a');
    link.href = url;
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Saved records stay filtered by the selected edition/circuit and expose explicit open/delete actions. */
function refreshSaved() {
    const circuit = selectedCircuit();
    $('#circuit-profile').replaceChildren(node('h2', circuit?.name || 'Selecciona un circuito'), node('p', circuit?.location || ''));
    const profile = catalog.circuitProfiles[circuit?.id];
    if (profile) {
        const list = node('dl');
        ['Longitud (km)', 'Curvas', 'Categoría de carga', 'Curvas y rectas', 'Características de conducción'].forEach((label, index) => { list.append(node('dt', label), node('dd', String(profile[index]))); });
        const sources = node('details');
        sources.append(node('summary', 'Fuentes y fecha de consulta'));
        profile[5].forEach((source) => {
            const link = node('a', `${source.title} · ${source.accessedAt}`);
            if (/^https?:\/\//.test(source.url)) { link.href = source.url; link.rel = 'noopener noreferrer'; link.target = '_blank'; }
            sources.append(node('p'), link);
        });
        $('#circuit-profile').append(list, sources);
    } else if (circuit) {
        [circuit.cornersAndStraights, circuit.drivingCharacteristics, circuit.summary].filter(Boolean).forEach((text) => $('#circuit-profile').append(node('p', text)));
    }
    $('#saved-setups').replaceChildren();
    $('#browse-setup').replaceChildren(new Option('Selecciona un reglaje', ''));
    const distributed = edition().circuits.find((row) => row.id === circuit?.id);
    if (distributed && edition().presets[distributed.preset_id]) {
        $('#browse-setup').add(new Option(`Predeterminado ${distributed.preset_id} (juego)`, `preset:${distributed.preset_id}`));
    }
    $('#browse-values').replaceChildren();
    catalog.setups.filter((row) => row.circuit_id === circuit?.id && (row.season === $('#season').value || row.season === 'unknown')).forEach((row) => {
        $('#browse-setup').add(new Option(row.title, row.id));
        const card = node('article', undefined, 'panel');
        const open = node('button', 'Abrir', 'button');
        const remove = node('button', 'Eliminar', 'button button-danger');
        open.addEventListener('click', run(async () => {
            if (!canReplace()) { return; }
            const value = await api.request('load-setup', { id: row.id });
            if (value.season === 'unknown') { openImportPreview(value, row.id); return; }
            // Editing a saved record compares against the values loaded for this editing session.
            value.reference = { ...value.current };
            value.referenceSource = 'personal';
            draft.open(value, row.id);
            renderEditor();
            page('workshop');
        }));
        const exportSaved = node('button', 'Exportar', 'button button-secondary');
        exportSaved.addEventListener('click', run(async () => download('reglaje.json', await api.request('load-setup', { id: row.id }))));
        remove.addEventListener('click', run(async () => {
            if (!confirm(`¿Eliminar ${row.title}?`)) { return; }
            const result = await api.request('delete-setup', { id: row.id });
            catalog.setups = result.setups;
            if (draft.id === row.id) { draft.id = null; draft.changed(); }
            refreshSaved();
        }));
        card.append(node('h3', row.title), node('p', `${row.season} · ${row.created_at}`, 'muted'), open, exportSaved, remove);
        $('#saved-setups').append(card);
    });
}

/** Review a validated snapshot before replacing the draft, including explicitly assigning old unknown seasons. */
function openImportPreview(value, id = null) {
    preview = value;
    previewId = id;
    $('#import-info').textContent = [value.title, ...(value.warnings || [])].join(' · ');
    const table = node('table');
    const header = node('tr');
    ['Ajuste', 'Referencia', 'Actual'].forEach((label) => header.append(node('th', label)));
    table.append(header);
    catalog.editions[Object.keys(catalog.editions)[0]].parameters.forEach((parameter) => {
        const row = node('tr');
        row.append(node('th', parameter.name), node('td', value.reference?.[parameter.id] ?? 'Sin valor'), node('td', value.current[parameter.id] ?? 'Sin valor'));
        table.append(row);
    });
    $('#import-values').replaceChildren(table);
    $('#import-season').value = value.season === 'unknown' ? '' : value.season;
    refreshImportCircuits();
    $('#import-dialog').showModal();
}

/** Import choices follow the reviewed season, independently from the circuit browser selection. */
function refreshImportCircuits() {
    $('#import-track').replaceChildren(new Option('Conservar circuito del archivo', ''));
    availableCircuits($('#import-season').value).forEach((row) => $('#import-track').add(new Option(row.name, row.id)));
}

/** Read-only browsing never installs a draft or alters its reference. */
async function browseValues() {
    const selected = $('#browse-setup').value;
    if (!selected) { $('#browse-values').replaceChildren(); return; }
    const values = selected.startsWith('preset:') ? edition().presets[selected.slice(7)] : (await api.request('load-setup', { id: selected })).current;
    const root = $('#browse-values');
    root.replaceChildren();
    [...new Set(edition().parameters.map((p) => p.category))].forEach((category) => {
        const details = node('details', undefined, 'panel');
        details.append(node('summary', category));
        const list = node('dl');
        edition().parameters.filter((p) => p.category === category).forEach((p) => { list.append(node('dt', p.name), node('dd', String(values[p.id]))); });
        details.append(list);
        root.append(details);
    });
}

/** File input is bounded before sending JSON to authoritative server validation. */
async function readFile(input) {
    const file = input.files[0];
    input.value = '';
    if (!file) { return null; }
    if (file.size > 262144) { throw new Error('El JSON supera 256 KiB.'); }
    return JSON.parse(await file.text());
}

/** Event wrappers show errors without losing the live draft. */
function run(operation) { return async (event) => { try { await operation(event); } catch (error) { status(error.message, true); } }; }

/** One registration point prevents duplicate handlers after a draft is re-rendered. */
function bind() {
    $('#season').addEventListener('change', refreshCircuits);
    $('#circuit').addEventListener('change', refreshSaved);
    $('#browse-setup').addEventListener('change', run(browseValues));
    $('#new-setup').addEventListener('click', run(createDraft));
    $('#save-setup').addEventListener('click', run(() => save()));
    $('#save-copy').addEventListener('click', run(() => save(true)));
    $('#export-setup').addEventListener('click', run(() => download('reglaje.json', draft.snapshot())));
    ['title', 'notes'].forEach((key) => $(`#setup-${key}`).addEventListener('input', (event) => { draft.value[key] = event.target.value; draft.changed(); }));
    ['surface', 'session'].forEach((key) => $(`#${key}`).addEventListener('change', (event) => { draft.value.conditions[key] = event.target.value; draft.changed(); }));
    $('#import-setup').addEventListener('click', () => $('#setup-file').click());
    $('#setup-file').addEventListener('change', run(async () => {
        const input = await readFile($('#setup-file'));
        if (!input) { return; }
        openImportPreview(await api.request('import-setup', input));
    }));
    $('#apply-import').addEventListener('click', run(() => {
        if (!$('#import-season').value) { throw new Error('Selecciona la temporada del archivo.'); }
        if (!canReplace()) { return; }
        preview.season = $('#import-season').value;
        if ($('#import-track').value) {
            const row = availableCircuits($('#import-season').value).find((item) => item.id === $('#import-track').value);
            preview.circuit = row.origin ? row : { ...row, origin: 'game-catalog' };
        }
        if (!preview.circuit) { throw new Error('Selecciona un circuito.'); }
        delete preview.warnings;
        draft.open(preview, previewId, true);
        $('#import-dialog').close();
        renderEditor();
        page('workshop');
    }));
    $('#import-season').addEventListener('change', refreshImportCircuits);
    bindCircuitImport();
    $('#recover-draft').addEventListener('click', run(async () => {
        if (!canReplace()) { return; }
        const raw = localStorage.getItem('f125-engineer-draft-v1');
        if (!raw || new Blob([raw]).size > 262144) { throw new Error('Borrador de navegador inválido.'); }
        const value = await api.request('recover-setup', JSON.parse(raw));
        if (value.season === 'unknown' || !value.circuit) { openImportPreview(value); }
        else { draft.open(value, null, true); renderEditor(); page('workshop'); }
        $('#recovery').hidden = true;
    }));
    $('#discard-recovery').addEventListener('click', run(() => {
        if (confirm('¿Eliminar el borrador anterior de este navegador?')) { localStorage.removeItem('f125-engineer-draft-v1'); $('#recovery').hidden = true; }
    }));
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || link.origin !== location.origin || event.ctrlKey || event.metaKey || event.shiftKey) { return; }
        const destination = new URL(link.href).searchParams.get('page') || 'home';
        if (['circuits', 'workshop', 'tests'].includes(destination)) { event.preventDefault(); page(destination); }
        else if (draft.dirty && !confirm('Hay cambios sin guardar. ¿Salir y descartarlos?')) { event.preventDefault(); }
    });
    window.addEventListener('popstate', () => page(new URL(location.href).searchParams.get('page') || 'circuits', false));
    window.addEventListener('beforeunload', (event) => { if (draft.dirty) { event.preventDefault(); event.returnValue = ''; } });
    $('#symptom').addEventListener('change', showAdvice);
}

/** Circuit creation has its own server validation and never mutates the active setup. */
function bindCircuitImport() {
    $('#circuit-template').addEventListener('click', () => download('plantilla-circuito.json', { format: 'f125-circuit', schemaVersion: 2, circuit: { nombre: '', ubicacion: '', longitud_km: null, curvas: null, categoria_carga: '', curvas_y_rectas: '', caracteristicas_conduccion: '' } }));
    $('#import-circuit').addEventListener('click', () => $('#circuit-file').click());
    $('#circuit-file').addEventListener('change', run(async () => {
        const input = await readFile($('#circuit-file'));
        if (!input) { return; }
        const preview = await api.request('import-circuit', input);
        if (!confirm(`¿Guardar la ficha ${preview.circuit.name}?`)) { return; }
        const result = await api.request('save-circuit', { format: 'f125-circuit', schemaVersion: 2, circuit: preview.circuit });
        catalog.circuits = result.circuits;
        refreshCircuits();
    }));
}

/** Render source advice as text; its theoretical scope remains distinct from measured effects. */
function showAdvice() {
    const advice = catalog.symptoms.items.find((item) => item.id === $('#symptom').value);
    $('#advice').replaceChildren();
    if (!advice) { return; }
    const card = node('section', undefined, 'panel');
    card.append(node('h2', advice.label));
    Object.entries(advice).forEach(([key, value]) => {
        if (key === 'id' || key === 'label') { return; }
        if (typeof value === 'string') { card.append(node('p', value)); }
        else if (Array.isArray(value)) { value.forEach((step) => card.append(node('p', typeof step === 'string' ? step : Object.values(step).filter((part) => typeof part === 'string').join(' · ')))); }
    });
    $('#advice').append(card);
}

/** Establish the session and catalogs before registering controls or background requests. */
async function start() {
    try {
        catalog = await api.request('bootstrap');
        document.dispatchEvent(new Event('setup-ready'));
        refreshCircuits();
        catalog.symptoms.items.forEach((item) => $('#symptom').add(new Option(item.label, item.id)));
        bind();
        try { $('#recovery').hidden = !localStorage.getItem('f125-engineer-draft-v1'); } catch { /* Browser storage is optional and no new drafts are written there. */ }
        page(document.body.dataset.page, false);
        if (!catalog.storageReady) { status('MariaDB no está disponible. Puedes editar, comparar e importar; el guardado requiere recuperar la conexión.', true); }
    } catch (error) { status(error.message, true); }
}

start();
