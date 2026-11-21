/** JSON transport; session ownership and error presentation remain outside business objects. */
export class Api {
    constructor() { this.token = ''; }

    /** Bootstrap establishes the session before parallel requests are allowed. */
    async request(action, body) {
        const options = body === undefined ? { cache: 'no-store' } : {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.token }, body: JSON.stringify(body),
        };
        const response = await fetch(`?api=${encodeURIComponent(action)}`, options);
        let data;
        try { data = await response.json(); } catch { throw new Error('El servidor no ha devuelto JSON válido.'); }
        if (!response.ok) { throw new Error(data.error || 'No se pudo completar la operación.'); }
        if (data.csrf) { this.token = data.csrf; }
        return data;
    }
}
