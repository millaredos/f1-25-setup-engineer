/** Owns an editable snapshot; it never persists or rewrites a saved setup implicitly. */
export class Draft {
    constructor() { this.value = null; this.id = null; this.dirty = false; }

    /** Copy imports and stored snapshots so DOM edits cannot mutate their source. */
    open(value, id = null, dirty = false) {
        this.value = JSON.parse(JSON.stringify(value));
        this.id = id;
        this.dirty = dirty;
    }

    /** Editing marks a decision as pending; only an explicit save clears it. */
    changed() { this.dirty = true; }

    /** Build an export without UI-only fields and without sharing mutable nested objects. */
    snapshot() {
        if (!this.value) { throw new Error('No hay un borrador abierto.'); }
        return JSON.parse(JSON.stringify(this.value));
    }
}
