<?php defined('SU_VERSION') || exit; ?>
<script nonce="<?= h($SU_NONCE) ?>">
(() => {
'use strict';

const BOOT = JSON.parse(document.getElementById('boot').textContent);
const $ = id => document.getElementById(id);

/* ---------- Sprache: Deutsch ist die Grundlage, die englischen Texte kommen vom Server ---------- */
function T(s, ...args) {
    let r = Object.prototype.hasOwnProperty.call(BOOT.dict, s) ? BOOT.dict[s] : s;
    args.forEach(a => { r = r.replace('%s', a); });
    return r;
}
const LOCALE = BOOT.lang === 'en' ? 'en-GB' : 'de-DE';
const num = n => Number(n).toLocaleString(LOCALE);
const fmtDate = ts => new Date(ts * 1000).toLocaleDateString(LOCALE, { day: '2-digit', month: '2-digit', year: 'numeric' });
const fmtDateTime = ts => new Date(ts * 1000).toLocaleString(LOCALE, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const fmtDay = (day, long) => new Date(day + 'T12:00:00').toLocaleDateString(LOCALE, long ? { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' } : { day: '2-digit', month: '2-digit' });

/* ---------- Symbole (Lucide, ISC-Lizenz), eingebettet ---------- */
const ICONS = {
    copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    qr: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20v.01M17 20h4v-3"/>',
    chart: '<path d="M3 3v18h18"/><path d="M8 17v-5M13 17V8M18 17v-9"/>',
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    trash: '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/>',
    power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    hash: '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
    user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    settings: '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M2 14h4M10 8h4M18 16h4"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    help: '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    search: '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    dice: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h.01M16 8h.01M12 12h.01M8 16h.01M16 16h.01"/>',
};
const icon = name => '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' + ICONS[name] + '</svg>';

/* ---------- Kleine Helfer ---------- */

/** Baut ein Element. Fremde Texte landen immer über „text“ oder als Kind im Dokument, nie als HTML. */
function el(tag, props, ...kids) {
    const e = document.createElement(tag);
    for (const [k, v] of Object.entries(props || {})) {
        if (v == null || v === false) continue;
        if (k === 'class') e.className = v;
        else if (k === 'text') e.textContent = v;
        else if (k === 'icon') e.innerHTML = icon(v);
        else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
        else e.setAttribute(k, v === true ? '' : v);
    }
    for (const kid of kids.flat()) {
        if (kid != null && kid !== false) e.append(kid);
    }
    return e;
}
/** Ersetzt den Inhalt eines Elements. Leere Angaben (null, false) werden übergangen. */
function fill(node, ...kids) {
    node.replaceChildren(...kids.flat().filter(k => k != null && k !== false));
}
function ibtn(name, title, onclick, cls) {
    return el('button', { class: 'ibtn' + (cls ? ' ' + cls : ''), type: 'button', title, 'aria-label': title, icon: name, onclick });
}
function btn(label, cls, onclick, iconName) {
    const b = el('button', { class: 'btn ' + (cls || 'btn-second'), type: 'button', onclick });
    if (iconName) b.innerHTML = icon(iconName);
    b.append(el('span', { text: label }));
    return b;
}
function field(label, input, hint) {
    return el('label', { class: 'f' }, label, hint ? el('span', { class: 'hint', text: ' ' + hint }) : null, input);
}
function input(props) {
    return el('input', Object.assign({ class: 'in', type: 'text', autocomplete: 'off' }, props));
}
function select(options, value, props) {
    const s = el('select', Object.assign({ class: 'in' }, props || {}), options.map(([v, label]) => el('option', { value: v, text: label })));
    s.value = value;
    return s;
}

function toast(text, bad) {
    const box = $('toasts');
    // Ein offenes Fenster liegt über allem, die Meldung muss deshalb mit hinein
    const open = [...document.querySelectorAll('dialog[open]')].pop();
    (open || document.body).append(box);
    const t = el('div', { class: 'toast ' + (bad ? 'bad' : 'good') });
    t.innerHTML = icon(bad ? 'x' : 'check');
    t.append(el('span', { text }));
    box.append(t);
    setTimeout(() => t.remove(), bad ? 6000 : 2800);
}

async function api(action, data) {
    const body = new FormData();
    body.append('action', action);
    for (const [k, v] of Object.entries(data || {})) {
        if (v == null) continue;
        body.append(k, v === true ? '1' : v === false ? '' : v);
    }
    let res, json;
    try {
        res = await fetch('./', { method: 'POST', body, headers: { 'X-CSRF-Token': BOOT.csrf }, credentials: 'same-origin', cache: 'no-store' });
        json = await res.json();
    } catch (e) {
        throw new Error(T('Der Server antwortet nicht. Bitte die Verbindung prüfen.'));
    }
    if (res.status === 401) { location.reload(); throw new Error(json.message || ''); }
    if (!json.success) throw new Error(json.message || T('Das hat nicht geklappt. Bitte noch einmal versuchen.'));
    return json;
}
/** Führt eine Aktion aus und zeigt Fehler als Meldung. Liefert das Ergebnis oder null. */
async function run(action, data, okText) {
    try {
        const r = await api(action, data);
        if (okText || r.message) toast(okText || r.message);
        return r;
    } catch (e) {
        if (e.message) toast(e.message, true);
        return null;
    }
}

async function copyText(text, okText) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        const ta = el('textarea', { style: 'position:fixed;opacity:0' });
        ta.value = text;
        ([...document.querySelectorAll('dialog[open]')].pop() || document.body).append(ta);
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (e2) {}
        ta.remove();
        if (!ok) { toast(T('Kopieren hat nicht geklappt. Bitte von Hand markieren und kopieren.'), true); return false; }
    }
    toast(okText || T('Kopiert'));
    return true;
}

/* ---------- Fenster ---------- */

function dialog({ title, body, foot, wide, locked }) {
    const d = el('dialog', { class: wide ? 'wide' : '' });
    const close = () => d.close();
    fill(d,
        el('div', { class: 'dhead' }, el('h2', { text: title }), locked ? null : ibtn('x', T('Schließen'), close)),
        el('div', { class: 'dbody' }, body),
        foot && foot.length ? el('div', { class: 'dfoot' }, foot) : null
    );
    d.addEventListener('close', () => { document.body.append($('toasts')); d.remove(); });
    d.addEventListener('cancel', e => { if (locked) e.preventDefault(); });
    // Klick neben das Fenster schließt es (nur wenn der Klick auch dort begonnen hat, nicht beim Markieren von Text)
    let downOutside = false;
    d.addEventListener('mousedown', e => { downOutside = e.target === d; });
    d.addEventListener('click', e => { if (!locked && downOutside && e.target === d) close(); });
    document.body.append(d);
    d.showModal();
    return d;
}

function confirmBox(title, text, okLabel, danger) {
    return new Promise(resolve => {
        let answer = false;
        const d = dialog({
            title,
            body: el('p', { text, style: 'color:var(--text);margin:0' }),
            foot: [btn(T('Abbrechen'), 'btn-second', () => d.close()), btn(okLabel, danger ? 'btn-danger' : 'btn-primary', () => { answer = true; d.close(); })],
        });
        d.addEventListener('close', () => resolve(answer));
    });
}

/** Fragt das eigene Passwort ab (für heikle Aktionen). Liefert es oder null. */
function askPassword(title, text) {
    return new Promise(resolve => {
        let answer = null;
        const pw = input({ type: 'password', autocomplete: 'current-password', required: true });
        const form = el('form', { class: 'stack' }, el('p', { text }), field(T('Dein Passwort'), pw));
        const d = dialog({ title, body: form, foot: [btn(T('Abbrechen'), 'btn-second', () => d.close()), btn(T('Weiter'), 'btn-primary', () => form.requestSubmit())] });
        form.addEventListener('submit', e => { e.preventDefault(); answer = pw.value; d.close(); });
        d.addEventListener('close', () => resolve(answer));
        pw.focus();
    });
}

/* ---------- Optionen eines Links (beim Anlegen und beim Bearbeiten dieselben Felder) ---------- */

function toLocalInput(ts) {
    const d = new Date(ts * 1000);
    const p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
}

function buildOptions(link) {
    const host = BOOT.base.replace(/^https?:\/\//, '');
    const alias = input({ placeholder: link ? '' : T('zufällig'), maxlength: 50, spellcheck: 'false', autocapitalize: 'none', value: link ? link.code : '' });
    const expSel = select([
        ['', T('Nie')], ['3600', T('In 1 Stunde')], ['86400', T('In 1 Tag')], ['604800', T('In 7 Tagen')],
        ['2592000', T('In 30 Tagen')], ['31536000', T('In 1 Jahr')], ['date', T('Datum wählen…')],
    ], link && link.expires ? 'date' : '');
    const expDate = input({ type: 'datetime-local', value: link && link.expires ? toLocalInput(link.expires) : '' });
    const expDateWrap = el('div', { hidden: !(link && link.expires) }, expDate);
    expSel.addEventListener('change', () => {
        expDateWrap.hidden = expSel.value !== 'date';
        if (expSel.value === 'date' && !expDate.value) expDate.value = toLocalInput(Date.now() / 1000 + 7 * 86400);
    });
    const max = input({ type: 'number', min: 1, step: 1, inputmode: 'numeric', placeholder: T('unbegrenzt'), value: link && link.max_clicks ? link.max_clicks : '' });
    const pass = input({ type: 'password', autocomplete: 'new-password', placeholder: link && link.has_pass ? T('unverändert') : T('ohne') });
    const passOff = link && link.has_pass ? el('input', { type: 'checkbox' }) : null;
    const note = input({ maxlength: 300, placeholder: T('nur für dich, z. B. wofür der Link ist'), value: link ? link.note : '' });
    const strip = el('input', { type: 'checkbox' });
    strip.checked = link ? false : BOOT.prefs.strip;

    const node = el('div', { class: 'stack' },
        el('div', { class: 'grid2' },
            field(T('Wunschname'), el('div', { class: 'pre' }, el('span', { text: host }), alias)),
            field(T('Notiz'), note)
        ),
        el('div', { class: 'grid3' },
            field(T('Läuft ab'), el('div', {}, expSel, expDateWrap)),
            field(T('Höchstens so viele Aufrufe'), max, link && link.max_clicks ? T('(bisher %s)', num(link.clicks)) : ''),
            field(T('Passwort für den Link'), pass)
        ),
        passOff ? el('label', { class: 'check' }, passOff, T('Passwortschutz entfernen')) : null,
        el('label', { class: 'check' }, strip, T('Tracking-Anhängsel entfernen (utm_…, fbclid, gclid und ähnliche)')),
        el('p', { style: 'margin:.2rem 0 0;color:var(--muted);font-size:.8rem', text: T('Links mit Passwort oder begrenzten Aufrufen zeigen erst eine Zwischenseite. So verbraucht die Vorschau eines Messengers keinen Aufruf.') })
    );

    return {
        node,
        reset() {
            alias.value = ''; expSel.value = ''; expDate.value = ''; expDateWrap.hidden = true; max.value = ''; pass.value = ''; note.value = '';
            strip.checked = BOOT.prefs.strip;
        },
        read() {
            let expires = '';
            if (expSel.value === 'date') {
                const ms = new Date(expDate.value).getTime();
                if (!expDate.value || isNaN(ms)) throw new Error(T('Bitte ein Ablaufdatum wählen.'));
                expires = String(Math.floor(ms / 1000));
            } else if (expSel.value !== '') {
                expires = String(Math.floor(Date.now() / 1000) + parseInt(expSel.value, 10));
            }
            return {
                code: alias.value.trim(), note: note.value, expires, max_clicks: max.value.trim(),
                pass_mode: passOff && passOff.checked ? 'remove' : (pass.value !== '' ? 'set' : 'keep'), pass: pass.value, strip: strip.checked,
            };
        },
    };
}

/* ---------- QR-Code (eigene, kleine Umsetzung: Byte-Modus, Version 1 bis 6, ohne fremde Bibliothek) ---------- */

const QR = (() => {
    const EXP = new Uint8Array(512), LOG = new Uint8Array(256);
    for (let i = 0, x = 1; i < 255; i++) { EXP[i] = x; LOG[x] = i; x <<= 1; if (x & 256) x ^= 0x11d; }
    for (let i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
    const mul = (a, b) => (a && b ? EXP[LOG[a] + LOG[b]] : 0);

    function rsRemainder(data, n) {
        let g = [1];
        for (let i = 0; i < n; i++) {
            const ng = new Array(g.length + 1).fill(0);
            for (let j = 0; j < g.length; j++) { ng[j] ^= g[j]; ng[j + 1] ^= mul(g[j], EXP[i]); }
            g = ng;
        }
        const r = new Array(n).fill(0);
        for (const d of data) {
            const f = d ^ r.shift();
            r.push(0);
            for (let j = 0; j < n; j++) r[j] ^= mul(g[j + 1], f);
        }
        return r;
    }

    // [Daten-Codewörter, Korrektur-Codewörter je Block, Blöcke] für Version 1 bis 6
    const LEVELS = [
        { bits: 0, table: [null, [16, 10, 1], [28, 16, 1], [44, 26, 1], [64, 18, 2], [86, 24, 2], [108, 16, 4]] },  // M: verträgt 15 % Schaden
        { bits: 1, table: [null, [19, 7, 1], [34, 10, 1], [55, 15, 1], [80, 20, 1], [108, 26, 1], [136, 18, 2]] },   // L: 7 %, dafür mehr Platz
    ];

    /** Liefert die Module als Feld von Zeilen (true = dunkel) oder null, wenn der Text zu lang ist. */
    function matrix(text) {
        const bytes = new TextEncoder().encode(text);
        let ver = 0, lvl = null;
        for (const L of LEVELS) {
            for (let v = 1; v <= 6 && !ver; v++) if (bytes.length <= L.table[v][0] - 2) { ver = v; lvl = L; }
            if (ver) break;
        }
        if (!ver) return null;
        const [dataCw, ecCw, blocks] = lvl.table[ver];

        // Datenstrom: Modus (Byte), Länge, Inhalt, Abschluss, Füllbytes
        const bits = [];
        const put = (val, len) => { for (let i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); };
        put(4, 4); put(bytes.length, 8);
        bytes.forEach(b => put(b, 8));
        put(0, Math.min(4, dataCw * 8 - bits.length));
        while (bits.length % 8) bits.push(0);
        const data = [];
        for (let i = 0; i < bits.length; i += 8) data.push(parseInt(bits.slice(i, i + 8).join(''), 2));
        for (let pad = 0xec; data.length < dataCw; pad ^= 0xec ^ 0x11) data.push(pad);

        // In Blöcke teilen, Korrekturdaten anhängen, verschränken
        const size = dataCw / blocks, dBlocks = [], eBlocks = [];
        for (let b = 0; b < blocks; b++) {
            const part = data.slice(b * size, (b + 1) * size);
            dBlocks.push(part);
            eBlocks.push(rsRemainder(part, ecCw));
        }
        const stream = [];
        for (let i = 0; i < size; i++) dBlocks.forEach(b => stream.push(b[i]));
        for (let i = 0; i < ecCw; i++) eBlocks.forEach(b => stream.push(b[i]));

        const n = 17 + 4 * ver;
        const m = Array.from({ length: n }, () => new Array(n).fill(false));
        const fn = Array.from({ length: n }, () => new Array(n).fill(false));
        const set = (x, y, dark) => { m[y][x] = !!dark; fn[y][x] = true; };

        // Suchmuster in drei Ecken samt Trennrand
        for (const [cx, cy] of [[3, 3], [n - 4, 3], [3, n - 4]]) {
            for (let dy = -4; dy <= 4; dy++) for (let dx = -4; dx <= 4; dx++) {
                const x = cx + dx, y = cy + dy;
                if (x < 0 || y < 0 || x >= n || y >= n) continue;
                const dist = Math.max(Math.abs(dx), Math.abs(dy));
                set(x, y, dist !== 2 && dist !== 4);
            }
        }
        // Taktlinien
        for (let i = 8; i < n - 8; i++) { set(i, 6, i % 2 === 0); set(6, i, i % 2 === 0); }
        // Ausrichtungsmuster (ab Version 2 genau eines)
        if (ver >= 2) {
            const c = n - 7;
            for (let dy = -2; dy <= 2; dy++) for (let dx = -2; dx <= 2; dx++) set(c + dx, c + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
        }
        // Platz für die Format-Angaben freihalten
        const format = mask => {
            const d = (lvl.bits << 3) | mask;
            let rem = d;
            for (let i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
            const f = ((d << 10) | rem) ^ 0x5412;
            const bit = i => ((f >>> i) & 1) === 1;
            for (let i = 0; i <= 5; i++) set(8, i, bit(i));
            set(8, 7, bit(6)); set(8, 8, bit(7)); set(7, 8, bit(8));
            for (let i = 9; i < 15; i++) set(14 - i, 8, bit(i));
            for (let i = 0; i < 8; i++) set(n - 1 - i, 8, bit(i));
            for (let i = 8; i < 15; i++) set(8, n - 15 + i, bit(i));
            set(8, n - 8, true);
        };
        format(0);

        // Daten im Zickzack von rechts unten einfüllen
        let k = 0;
        for (let right = n - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (let vert = 0; vert < n; vert++) {
                for (let j = 0; j < 2; j++) {
                    const x = right - j;
                    const y = ((right + 1) & 2) === 0 ? n - 1 - vert : vert;
                    if (fn[y][x]) continue;
                    if (k < stream.length * 8) m[y][x] = ((stream[k >>> 3] >>> (7 - (k & 7))) & 1) === 1;
                    k++;
                }
            }
        }

        const MASKS = [
            (x, y) => (x + y) % 2 === 0, (x, y) => y % 2 === 0, (x, y) => x % 3 === 0, (x, y) => (x + y) % 3 === 0,
            (x, y) => (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0, (x, y) => (x * y) % 2 + (x * y) % 3 === 0,
            (x, y) => ((x * y) % 2 + (x * y) % 3) % 2 === 0, (x, y) => ((x + y) % 2 + (x * y) % 3) % 2 === 0,
        ];
        const applyMask = i => { for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) if (!fn[y][x] && MASKS[i](x, y)) m[y][x] = !m[y][x]; };

        // Die Maske mit dem ruhigsten Bild gewinnt (Bewertung nach dem QR-Standard)
        function penalty() {
            let p = 0, dark = 0;
            const line = get => {
                for (let a = 0; a < n; a++) {
                    let run = 1, hist = 0;
                    for (let b = 0; b < n; b++) {
                        const v = get(a, b);
                        if (b > 0) {
                            if (v === get(a, b - 1)) { run++; if (run === 5) p += 3; else if (run > 5) p++; } else run = 1;
                        }
                        hist = ((hist << 1) | (v ? 1 : 0)) & 0x7ff;
                        if (b >= 10 && (hist === 0x5d0 || hist === 0x05d)) p += 40;
                    }
                }
            };
            line((y, x) => m[y][x]);
            line((x, y) => m[y][x]);
            for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
                if (m[y][x]) dark++;
                if (x < n - 1 && y < n - 1 && m[y][x] === m[y][x + 1] && m[y][x] === m[y + 1][x] && m[y][x] === m[y + 1][x + 1]) p += 3;
            }
            return p + Math.floor(Math.abs(dark * 20 - n * n * 10) / (n * n)) * 10;
        }
        let best = 0, bestP = Infinity;
        for (let i = 0; i < 8; i++) {
            applyMask(i); format(i);
            const p = penalty();
            if (p < bestP) { bestP = p; best = i; }
            applyMask(i);
        }
        applyMask(best); format(best);
        return m;
    }

    /** SVG als Text, schwarze Module auf weißem Grund, mit dem vorgeschriebenen hellen Rand */
    function svg(text, border = 4) {
        const m = matrix(text);
        if (!m) return null;
        const n = m.length, full = n + border * 2;
        let d = '';
        for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) if (m[y][x]) d += 'M' + (x + border) + ' ' + (y + border) + 'h1v1h-1z';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + full + ' ' + full + '" width="' + full * 8 + '" height="' + full * 8 + '" shape-rendering="crispEdges">'
             + '<rect width="' + full + '" height="' + full + '" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
    }
    return { matrix, svg };
})();
window.suQR = QR; // für die automatischen Tests

function download(name, blob) {
    const a = el('a', { href: URL.createObjectURL(blob), download: name });
    document.body.append(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
}

function qrDialog(link) {
    const svg = QR.svg(link.short);
    const pic = el('div', { class: 'pic' });
    if (svg) pic.innerHTML = svg;
    const savePng = () => {
        const img = new Image();
        img.onload = () => {
            const c = el('canvas', { width: 1024, height: 1024 });
            const ctx = c.getContext('2d');
            ctx.imageSmoothingEnabled = false;
            ctx.drawImage(img, 0, 0, 1024, 1024);
            c.toBlob(b => download('qr-' + link.code + '.png', b), 'image/png');
        };
        img.src = 'data:image/svg+xml;base64,' + btoa(svg);
    };
    dialog({
        title: T('QR-Code'),
        body: el('div', { class: 'qr' },
            svg ? pic : el('div', { class: 'msg warn', text: T('Diese Adresse ist zu lang für einen QR-Code.') }),
            el('div', { class: 'url', text: link.short }),
            el('p', { style: 'margin:0;text-align:center', text: T('Der Code wird in deinem Browser berechnet. Es wird nichts an fremde Dienste geschickt.') })
        ),
        foot: svg ? [
            btn(T('Link kopieren'), 'btn-second', () => copyText(link.short), 'copy'),
            btn('SVG', 'btn-second', () => download('qr-' + link.code + '.svg', new Blob([svg], { type: 'image/svg+xml' })), 'download'),
            btn('PNG', 'btn-primary', savePng, 'download'),
        ] : [],
    });
}

/* ---------- Statistik ---------- */

function niceMax(v) {
    if (v <= 4) return 4;
    const pow = Math.pow(10, Math.floor(Math.log10(v)));
    for (const f of [1, 2, 4, 5, 8, 10]) if (v <= f * pow) return f * pow;
    return 10 * pow;
}

function barChart(days) {
    const W = 640, H = 220, L = 34, R = 6, TOP = 10, B = 24;
    const plotW = W - L - R, plotH = H - TOP - B;
    const max = niceMax(Math.max(...days.map(d => d.clicks)));
    const step = plotW / days.length;
    const gap = step >= 8 ? 2 : (step >= 3 ? 1 : 0);
    const bw = Math.max(1, step - gap);
    const r = Math.min(4, bw / 2);
    const NS = 'http://www.w3.org/2000/svg';
    const s = (tag, attrs, text) => {
        const e = document.createElementNS(NS, tag);
        for (const [k, v] of Object.entries(attrs)) e.setAttribute(k, v);
        if (text != null) e.textContent = text;
        return e;
    };
    const svg = s('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': T('Aufrufe pro Tag') });
    for (let i = 0; i <= 4; i++) {
        const y = TOP + plotH - (plotH * i) / 4;
        svg.append(s('line', { class: 'grid', x1: L, x2: W - R, y1: y, y2: y }));
        if (i % 2 === 0) svg.append(s('text', { class: 'axis', x: L - 6, y: y + 4, 'text-anchor': 'end' }, num((max * i) / 4)));
    }
    const wrap = el('div', { class: 'chart' });
    const tip = el('div', { class: 'tip', hidden: true });
    days.forEach((d, i) => {
        const x = L + i * step + gap / 2;
        const h = d.clicks > 0 ? Math.max(2, (plotH * d.clicks) / max) : 0;
        const y = TOP + plotH - h;
        const rr = Math.min(r, h);
        const hit = s('rect', { class: 'hit', x: L + i * step, y: TOP, width: step, height: plotH });
        // Balken: oben abgerundet, unten gerade auf der Grundlinie
        const bar = s('path', { class: 'bar', 'pointer-events': 'none',
            d: h > 0 ? 'M' + x + ' ' + (y + h) + 'V' + (y + rr) + 'Q' + x + ' ' + y + ' ' + (x + rr) + ' ' + y + 'H' + (x + bw - rr) + 'Q' + (x + bw) + ' ' + y + ' ' + (x + bw) + ' ' + (y + rr) + 'V' + (y + h) + 'Z' : '' });
        const show = () => {
            tip.hidden = false;
            tip.replaceChildren(el('b', { text: num(d.clicks) }), ' ', el('span', { text: fmtDay(d.day, true) }));
            const box = svg.getBoundingClientRect();
            const px = ((x + bw / 2) / W) * box.width;
            tip.style.left = Math.max(60, Math.min(box.width - 60, px)) + 'px';
            tip.style.top = Math.max(0, ((y - 8) / H) * box.height) + 'px';
            bar.classList.add('hot');
        };
        const hide = () => { tip.hidden = true; bar.classList.remove('hot'); };
        hit.addEventListener('pointerenter', show);
        hit.addEventListener('pointerdown', show);
        hit.addEventListener('pointerleave', hide);
        svg.append(hit, bar);
    });
    // Datum nur an drei Stellen: Anfang, Mitte, Ende
    [[0, 'start'], [Math.floor((days.length - 1) / 2), 'middle'], [days.length - 1, 'end']].forEach(([i, anchor]) => {
        const x = anchor === 'start' ? L : anchor === 'end' ? W - R : L + i * step + step / 2;
        svg.append(s('text', { class: 'axis', x, y: H - 6, 'text-anchor': anchor }, fmtDay(days[i].day)));
    });
    wrap.append(svg, tip);
    return wrap;
}

function statsDialog(link) {
    const body = el('div', {});
    let range = 30;
    const seg = el('div', { class: 'seg' }, [[30, T('30 Tage')], [90, T('90 Tage')], [365, T('1 Jahr')]].map(([v, label]) =>
        el('button', { type: 'button', text: label, 'data-v': v, onclick: () => { range = v; load(); } })));
    async function load() {
        seg.querySelectorAll('button').forEach(b => b.classList.toggle('on', Number(b.dataset.v) === range));
        const r = await run('stats', { id: link.id, days: range });
        if (!r) return;
        const days = r.days;
        const sum = days.reduce((a, d) => a + d.clicks, 0);
        const best = days.reduce((a, d) => (d.clicks > a.clicks ? d : a), days[0]);
        const rows = days.filter(d => d.clicks > 0).reverse();
        body.replaceChildren(
            el('div', { class: 'kpis' },
                el('div', { class: 'kpi' }, el('b', { text: num(r.link.clicks) }), el('span', { text: T('Aufrufe insgesamt') })),
                el('div', { class: 'kpi' }, el('b', { text: num(sum) }), el('span', { text: T('im gewählten Zeitraum') })),
                el('div', { class: 'kpi' }, el('b', { text: best.clicks > 0 ? num(best.clicks) : '0' }), el('span', { text: best.clicks > 0 ? T('am stärksten Tag (%s)', fmtDay(best.day)) : T('am stärksten Tag') }))
            ),
            el('div', { class: 'row', style: 'margin-bottom:.7rem' }, el('b', { class: 'grow', text: T('Aufrufe pro Tag') }), seg),
            barChart(days),
            el('details', { class: 'tbl' }, el('summary', { text: T('Als Tabelle zeigen') }),
                el('div', { class: 'box' }, rows.length
                    ? el('table', { class: 't' }, el('thead', {}, el('tr', {}, el('th', { text: T('Tag') }), el('th', { class: 'num', text: T('Aufrufe') }))),
                        el('tbody', {}, rows.map(d => el('tr', {}, el('td', { text: fmtDay(d.day, true) }), el('td', { class: 'num', text: num(d.clicks) })))))
                    : el('p', { text: T('In diesem Zeitraum gab es keine Aufrufe.') }))),
            el('p', { style: 'margin:.9rem 0 0;font-size:.8rem', text: T('Gezählt wird nur, wie oft der Link an einem Tag aufgerufen wurde. Wer ihn aufgerufen hat, wird nicht gespeichert.') })
        );
    }
    dialog({ title: T('Statistik für %s', link.code), body, wide: true });
    load();
}

/* ---------- Liste ---------- */

const state = { q: '', filter: '', sort: 'new', all: false, links: [], total: 0, clicks: 0 };

function relTime(ts) {
    const s = ts - Date.now() / 1000;
    if (s < 3600) return T('%s Min.', Math.max(1, Math.round(s / 60)));
    if (s < 48 * 3600) return T('%s Std.', Math.round(s / 3600));
    return T('%s Tage', Math.round(s / 86400));
}

function targetNode(url) {
    const node = el('div', { class: 'to', title: url });
    try {
        const u = new URL(url);
        node.append(el('b', { text: u.host.replace(/^www\./, '') }), (u.pathname === '/' ? '' : u.pathname) + u.search + u.hash);
    } catch (e) { node.textContent = url; }
    return node;
}

function card(l) {
    const host = BOOT.base.replace(/^https?:\/\//, '');
    const tags = [];
    const tag = (cls, iconName, text, title) => {
        const t = el('span', { class: 'tag ' + cls, title });
        if (iconName) t.innerHTML = icon(iconName);
        t.append(text);
        tags.push(t);
    };
    if (l.state === 'off') tag('bad', 'power', T('abgeschaltet'));
    if (l.state === 'expired') tag('bad', 'clock', T('abgelaufen'), fmtDateTime(l.expires));
    else if (l.expires) tag('warn', 'clock', T('noch %s', relTime(l.expires)), T('Läuft ab am %s', fmtDateTime(l.expires)));
    if (l.max_clicks) tag(l.state === 'used' ? 'bad' : 'warn', 'hash', l.state === 'used' ? T('verbraucht (%s von %s)', num(l.clicks), num(l.max_clicks)) : T('%s von %s Aufrufen', num(l.clicks), num(l.max_clicks)));
    if (l.has_pass) tag('acc', 'lock', T('Passwort'));
    if (state.all && l.owner) tag('', 'user', l.owner);
    tag('', null, fmtDate(l.created), T('Angelegt am %s', fmtDateTime(l.created)));

    return el('article', { class: 'card glass' + (l.state === 'active' ? '' : ' ended') },
        el('div', { class: 'head' },
            el('a', { class: 'code', href: l.short + '+', target: '_blank', rel: 'noopener noreferrer', title: T('Vorschau öffnen (zählt nicht als Aufruf)') },
                el('span', { text: host }), el('b', { text: l.code })),
            el('div', { class: 'clicks', title: l.last_day ? T('Zuletzt aufgerufen am %s', fmtDay(l.last_day, true)) : T('Noch nie aufgerufen') },
                el('b', { text: num(l.clicks) }), el('small', { text: l.clicks === 1 ? T('Aufruf') : T('Aufrufe') }))
        ),
        targetNode(l.url),
        l.note ? el('div', { class: 'note', text: l.note }) : null,
        el('div', { class: 'tags' }, tags),
        el('div', { class: 'acts' },
            btn(T('Kopieren'), 'btn-soft', () => copyText(l.short), 'copy'),
            ibtn('qr', T('QR-Code'), () => qrDialog(l)),
            ibtn('chart', T('Statistik'), () => statsDialog(l)),
            ibtn('edit', T('Bearbeiten'), () => editDialog(l)),
            ibtn('power', l.active ? T('Abschalten') : T('Einschalten'), async () => {
                const r = await run('toggle', { id: l.id, active: !l.active }, l.active ? T('Der Link ist abgeschaltet.') : T('Der Link ist wieder an.'));
                if (r) replaceLink(r.link);
            }, l.active ? '' : 'on'),
            ibtn('trash', T('Löschen'), async () => {
                if (!await confirmBox(T('Link löschen?'), T('%s funktioniert danach nicht mehr, auch die Statistik ist weg. Das lässt sich nicht rückgängig machen.', l.short), T('Löschen'), true)) return;
                if (await run('delete', { id: l.id }, T('Gelöscht.'))) load(true);
            }, 'danger')
        )
    );
}

function replaceLink(link) {
    const i = state.links.findIndex(x => x.id === link.id);
    if (i >= 0) state.links[i] = link; else state.links.unshift(link);
    render();
}

function render() {
    const box = $('cards');
    if (!state.links.length) {
        const e = el('div', { class: 'empty', style: 'grid-column:1/-1' });
        e.innerHTML = icon('link');
        e.append(el('div', { text: state.q || state.filter ? T('Dazu gibt es keine Links.') : T('Noch keine Links. Oben eine lange Adresse einfügen und auf „Kürzen“ drücken.') }));
        box.replaceChildren(e);
    } else {
        box.replaceChildren(...state.links.map(card));
    }
    $('sum').textContent = !state.total ? '' : state.total === 1 ? T('1 Link, %s Aufrufe', num(state.clicks)) : T('%s Links, zusammen %s Aufrufe', num(state.total), num(state.clicks));
    $('more').hidden = state.links.length >= state.total;
}

let loadSeq = 0;
async function load(reset) {
    const seq = ++loadSeq;
    const offset = reset ? 0 : state.links.length;
    const r = await run('list', { q: state.q, filter: state.filter, sort: state.sort, all: state.all, offset });
    if (!r || seq !== loadSeq) return;
    state.links = reset ? r.links : state.links.concat(r.links);
    state.total = r.total;
    state.clicks = r.clicks;
    render();
}

/* ---------- Anlegen ---------- */

const newOpts = buildOptions(null);
$('newOpts').append(newOpts.node);
$('optToggle').insertAdjacentHTML('beforeend', icon('down'));
$('optToggle').addEventListener('click', () => {
    const open = $('newOpts').hidden;
    $('newOpts').hidden = !open;
    $('optToggle').setAttribute('aria-expanded', open ? 'true' : 'false');
});

function showResult(link) {
    const host = BOOT.base.replace(/^https?:\/\//, '');
    $('result').replaceChildren(el('div', { class: 'result' },
        el('a', { class: 'short', href: link.short + '+', target: '_blank', rel: 'noopener noreferrer', title: T('Vorschau öffnen (zählt nicht als Aufruf)') }, el('span', { text: host }), link.code),
        btn(T('Kopieren'), 'btn-primary', () => copyText(link.short), 'copy'),
        btn(T('QR-Code'), 'btn-second', () => qrDialog(link), 'qr')
    ));
}

$('newForm').addEventListener('submit', async e => {
    e.preventDefault();
    let opts;
    try { opts = newOpts.read(); } catch (err) { toast(err.message, true); return; }
    $('newGo').disabled = true;
    const r = await run('save', Object.assign({ url: $('newUrl').value }, opts));
    $('newGo').disabled = false;
    if (!r) return;
    $('newUrl').value = '';
    newOpts.reset();
    showResult(r.link);
    // Gleich in die Zwischenablage, wenn der Browser das an dieser Stelle erlaubt
    navigator.clipboard && navigator.clipboard.writeText(r.link.short).then(() => toast(T('Gekürzt und kopiert')), () => toast(T('Gekürzt')));
    if (!navigator.clipboard) toast(T('Gekürzt'));
    load(true);
});

/* ---------- Bearbeiten ---------- */

function editDialog(link) {
    const url = input({ value: link.url, maxlength: 4000, required: true, inputmode: 'url' });
    const opts = buildOptions(link);
    const form = el('form', { class: 'stack' }, field(T('Zieladresse'), url), opts.node);
    const d = dialog({
        title: T('Link bearbeiten'), body: form, wide: true,
        foot: [btn(T('Abbrechen'), 'btn-second', () => d.close()), btn(T('Speichern'), 'btn-primary', () => form.requestSubmit())],
    });
    form.addEventListener('submit', async e => {
        e.preventDefault();
        let o;
        try { o = opts.read(); } catch (err) { toast(err.message, true); return; }
        const r = await run('save', Object.assign({ id: link.id, url: url.value }, o), T('Gespeichert.'));
        if (r) { d.close(); replaceLink(r.link); }
    });
}

/* ---------- Konto & Aussehen ---------- */

function segmented(options, value, onpick) {
    const seg = el('div', { class: 'seg' });
    options.forEach(([v, label, color]) => {
        const b = el('button', { type: 'button', class: v === value ? 'on' : '', onclick: () => { seg.querySelectorAll('button').forEach(x => x.classList.remove('on')); b.classList.add('on'); onpick(v); } },
            color ? el('span', { class: 'dot', style: 'background:' + color }) : null, label);
        seg.append(b);
    });
    return seg;
}

async function savePrefs(change) {
    Object.assign(BOOT.prefs, change);
    document.documentElement.dataset.theme = BOOT.prefs.theme;
    document.documentElement.dataset.accent = BOOT.prefs.accent;
    const r = await run('prefs', BOOT.prefs);
    if (r && 'lang' in change) location.reload();
}

function passwordForm(onDone, firstTime) {
    const old = input({ type: 'password', autocomplete: 'current-password', required: true });
    const p1 = input({ type: 'password', autocomplete: 'new-password', required: true, minlength: BOOT.minPass });
    const p2 = input({ type: 'password', autocomplete: 'new-password', required: true });
    const form = el('form', { class: 'stack' },
        field(firstTime ? T('Bisheriges (vorläufiges) Passwort') : T('Bisheriges Passwort'), old),
        el('div', { class: 'grid2' }, field(T('Neues Passwort'), p1, T('(mindestens %s Zeichen)', BOOT.minPass)), field(T('Neues Passwort wiederholen'), p2)),
        el('div', {}, el('button', { class: 'btn ' + (firstTime ? 'btn-primary' : 'btn-second'), type: 'submit', text: T('Passwort ändern') }))
    );
    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (p1.value !== p2.value) { toast(T('Die beiden Passwörter stimmen nicht überein.'), true); return; }
        const r = await run('password', { password: old.value, new: p1.value });
        if (r) { form.reset(); onDone && onDone(); }
    });
    return form;
}

function accountDialog() {
    const twoFa = el('div', {});
    const recBox = el('div', {});

    function drawTwoFa() {
        if (BOOT.user.totp) {
            fill(twoFa,
                el('div', { class: 'msg good', text: T('Die Zwei-Faktor-Anmeldung ist an.') }),
                btn(T('Zwei-Faktor-Anmeldung abschalten'), 'btn-danger', async () => {
                    const pw = await askPassword(T('Zwei-Faktor-Anmeldung abschalten'), T('Danach reicht wieder das Passwort allein.'));
                    if (pw === null) return;
                    if (await run('totp_disable', { password: pw })) { BOOT.user.totp = false; drawTwoFa(); }
                })
            );
            return;
        }
        twoFa.replaceChildren(
            el('p', { text: T('Zusätzlich zum Passwort fragt die Anmeldung dann einen 6-stelligen Code aus einer App ab (z. B. Aegis, 2FAS, Google Authenticator).') }),
            btn(T('Zwei-Faktor-Anmeldung einrichten'), 'btn-second', async () => {
                const pw = await askPassword(T('Zwei-Faktor-Anmeldung einrichten'), T('Zur Sicherheit bitte noch einmal dein Passwort.'));
                if (pw === null) return;
                const r = await run('totp_begin', { password: pw });
                if (!r) return;
                const code = input({ inputmode: 'numeric', maxlength: 7, placeholder: '123456', autocomplete: 'one-time-code', required: true });
                const pic = el('div', { class: 'pic' });
                const svg = QR.svg(r.uri);
                if (svg) pic.innerHTML = svg;
                const form = el('form', { class: 'stack' },
                    el('p', { text: T('1. Diesen Code mit der Authenticator-App einlesen. Geht das nicht, den Schlüssel darunter von Hand eintragen.') }),
                    el('div', { class: 'qr' }, svg ? pic : null, el('div', { class: 'codebox mono', text: r.secret.replace(/(.{4})/g, '$1 ').trim() })),
                    el('p', { text: T('2. Den 6-stelligen Code eingeben, den die App jetzt anzeigt.') }),
                    el('div', { class: 'row' }, el('div', { class: 'grow' }, code), el('button', { class: 'btn btn-primary', type: 'submit', text: T('Einschalten') }))
                );
                form.addEventListener('submit', async e => {
                    e.preventDefault();
                    if (await run('totp_enable', { code: code.value })) { BOOT.user.totp = true; drawTwoFa(); }
                });
                twoFa.replaceChildren(form);
                code.focus();
            })
        );
    }

    function drawRecovery(code) {
        fill(recBox,
            el('p', { text: T('Mit dem Wiederherstellungscode kommst du wieder hinein, wenn du das Passwort vergessen oder das Handy mit der Authenticator-App verloren hast. Er wird nur ein einziges Mal angezeigt.') }),
            code ? el('div', { class: 'stack', style: 'margin-bottom:.8rem' },
                el('div', { class: 'codebox mono', text: code }),
                el('div', { class: 'msg warn', text: T('Jetzt aufschreiben oder im Passwort-Manager ablegen. Nach dem Schließen ist der Code nicht mehr zu sehen.') }),
                btn(T('Code kopieren'), 'btn-second', () => copyText(code), 'copy')) : null,
            !code && BOOT.user.recovery ? el('div', { class: 'msg good', text: T('Es gibt einen Wiederherstellungscode. Ein neuer macht den alten ungültig.') }) : null,
            !code ? btn(BOOT.user.recovery ? T('Neuen Code erzeugen') : T('Wiederherstellungscode erzeugen'), 'btn-second', async () => {
                const pw = await askPassword(T('Wiederherstellungscode'), T('Zur Sicherheit bitte noch einmal dein Passwort.'));
                if (pw === null) return;
                const r = await run('recovery_new', { password: pw });
                if (r) { BOOT.user.recovery = true; drawRecovery(r.code); }
            }) : null
        );
    }

    const strip = el('input', { type: 'checkbox' });
    strip.checked = BOOT.prefs.strip;
    strip.addEventListener('change', () => { savePrefs({ strip: strip.checked }); newOpts.reset(); });

    const exportForm = el('form', { method: 'post', action: './' },
        el('input', { type: 'hidden', name: 'action', value: 'export' }), el('input', { type: 'hidden', name: 'csrf', value: BOOT.csrf }),
        el('button', { class: 'btn btn-second', type: 'submit', text: T('Meine Links als CSV herunterladen') }));

    drawTwoFa();
    drawRecovery(null);
    dialog({
        title: T('Konto & Aussehen'), wide: true,
        body: el('div', {},
            el('h3', { text: T('Aussehen') }),
            el('div', { class: 'row' },
                segmented([['auto', T('Automatisch')], ['dark', T('Dunkel')], ['light', T('Hell')]], BOOT.prefs.theme, v => savePrefs({ theme: v })),
                segmented([['violet', T('Violett'), '#8b5cf6'], ['blue', T('Blau'), '#3b82f6'], ['green', T('Grün'), '#10b981']], BOOT.prefs.accent, v => savePrefs({ accent: v })),
                segmented([['', T('Wie der Browser')], ['de', 'Deutsch'], ['en', 'English']], BOOT.prefs.lang, v => savePrefs({ lang: v }))
            ),
            el('h3', { text: T('Kürzen') }),
            el('label', { class: 'check' }, strip, T('Tracking-Anhängsel bei neuen Links von Haus aus entfernen')),
            el('h3', { text: T('Passwort') }),
            passwordForm(null, false),
            el('h3', { text: T('Zwei-Faktor-Anmeldung') }), twoFa,
            el('h3', { text: T('Wiederherstellungscode') }), recBox,
            el('h3', { text: T('Export') }), exportForm
        ),
    });
}

/* ---------- Verwaltung (nur Administrator) ---------- */

function randomPassword() {
    const abc = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const buf = new Uint32Array(16);
    crypto.getRandomValues(buf);
    return Array.from(buf, v => abc[v % abc.length]).join('');
}

function userDialog(user, onDone) {
    const name = input({ value: user ? user.name : '', maxlength: 32, required: true, disabled: !!user, autocapitalize: 'none', spellcheck: 'false' });
    const pass = input({ placeholder: user ? T('unverändert') : '', required: !user, minlength: BOOT.minPass, class: 'in mono' });
    const role = select([['user', T('Benutzer')], ['admin', T('Administrator')]], user ? user.role : 'user');
    const active = el('input', { type: 'checkbox' });
    active.checked = user ? user.active : true;
    const self = user && user.id === BOOT.user.id;
    const form = el('form', { class: 'stack' },
        field(T('Benutzername'), name),
        self ? null : field(user ? T('Neues vorläufiges Passwort') : T('Vorläufiges Passwort'),
            el('div', { class: 'row', style: 'margin-top:.3rem' }, el('div', { class: 'grow' }, pass), ibtn('dice', T('Zufälliges Passwort erzeugen'), () => { pass.value = randomPassword(); })),
            T('(mindestens %s Zeichen)', BOOT.minPass)),
        self ? null : el('p', { text: user
            ? T('Ein neues Passwort meldet das Konto überall ab und schaltet dessen Zwei-Faktor-Anmeldung aus. Beim nächsten Anmelden muss ein eigenes Passwort gesetzt werden.')
            : T('Gib das vorläufige Passwort auf sicherem Weg weiter. Beim ersten Anmelden muss ein eigenes gesetzt werden.') }),
        self ? el('p', { text: T('Das ist dein eigenes Konto. Rolle und Sperre lassen sich hier nicht ändern, das Passwort änderst du unter „Konto“.') }) : field(T('Rolle'), role),
        self ? null : el('label', { class: 'check' }, active, T('Konto ist aktiv (abgewählt = gesperrt, die Links des Kontos funktionieren weiter)'))
    );
    pass.style.marginTop = '0';
    const d = dialog({
        title: user ? T('Konto bearbeiten') : T('Neues Konto'), body: form,
        foot: [btn(T('Abbrechen'), 'btn-second', () => d.close()), self ? null : btn(T('Speichern'), 'btn-primary', () => form.requestSubmit())],
    });
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const r = await run('user_save', { id: user ? user.id : 0, name: name.value, pass: pass.value, role: role.value, active: active.checked });
        if (r) { d.close(); onDone(); }
    });
}

function adminDialog() {
    const usersBox = el('div', { class: 'scrollx' });
    async function loadUsers() {
        const r = await run('users');
        if (!r) return;
        usersBox.replaceChildren(el('table', { class: 't' },
            el('thead', {}, el('tr', {}, el('th', { text: T('Konto') }), el('th', { class: 'num', text: T('Links') }), el('th', { class: 'num', text: T('Aufrufe') }), el('th', { text: T('Zuletzt angemeldet') }), el('th', {}))),
            el('tbody', {}, r.users.map(u => el('tr', {},
                el('td', {}, el('b', { text: u.name }), ' ',
                    u.role === 'admin' ? el('span', { class: 'tag acc', text: T('Administrator') }) : null, ' ',
                    !u.active ? el('span', { class: 'tag bad', text: T('gesperrt') }) : null, ' ',
                    u.totp ? el('span', { class: 'tag', text: '2FA' }) : null),
                el('td', { class: 'num', text: num(u.links) }),
                el('td', { class: 'num', text: num(u.clicks) }),
                el('td', { text: u.last_login ? fmtDateTime(u.last_login) : T('noch nie') }),
                el('td', {}, el('div', { class: 'row', style: 'justify-content:flex-end;flex-wrap:nowrap' },
                    ibtn('edit', T('Bearbeiten'), () => userDialog(u, loadUsers)),
                    u.id === BOOT.user.id ? null : ibtn('trash', T('Löschen'), async () => {
                        if (!await confirmBox(T('Konto löschen?'), T('Das Konto „%s“ und alle seine %s Links werden gelöscht. Die Kurzlinks funktionieren danach nicht mehr.', u.name, num(u.links)), T('Löschen'), true)) return;
                        if (await run('user_delete', { id: u.id })) { loadUsers(); load(true); }
                    }, 'danger')))
            )))
        ));
    }

    const s = BOOT.settings;
    const site = input({ value: s.site_name, maxlength: 40, placeholder: 'ShrinkURL' });
    const imprint = input({ value: s.imprint_url, placeholder: 'https://…', inputmode: 'url' });
    const privacy = input({ value: s.privacy_url, placeholder: 'https://…', inputmode: 'url' });
    const keep = input({ type: 'number', min: 0, max: 3650, value: s.keep_expired_days });
    const form = el('form', { class: 'stack' },
        el('div', { class: 'grid2' }, field(T('Name der Seite'), site), field(T('Abgelaufene Links löschen nach (Tagen)'), keep, T('(0 = nie)'))),
        el('div', { class: 'grid2' }, field(T('Adresse des Impressums'), imprint), field(T('Adresse der Datenschutzerklärung'), privacy)),
        el('p', { text: T('Impressum und Datenschutz erscheinen als Links unter der Anmeldung und unter den Hinweisseiten der Kurzlinks.') }),
        el('div', {}, el('button', { class: 'btn btn-second', type: 'submit', text: T('Einstellungen speichern') }))
    );
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const data = { site_name: site.value, imprint_url: imprint.value, privacy_url: privacy.value, keep_expired_days: keep.value };
        if (await run('settings', data)) Object.assign(BOOT.settings, data);
    });

    dialog({
        title: T('Verwaltung'), wide: true,
        body: el('div', {},
            el('h3', { text: T('Konten') }),
            el('p', { text: T('Neue Konten legst nur du an, es gibt keine offene Registrierung. Jeder sieht nur seine eigenen Links, als Administrator kannst du dir alle anzeigen lassen.') }),
            usersBox,
            el('div', { style: 'margin-top:.8rem' }, btn(T('Neues Konto'), 'btn-second', () => userDialog(null, loadUsers), 'plus')),
            el('h3', { text: T('Einstellungen') }), form,
            el('h3', { text: T('Aufräumen (Cronjob)') }),
            el('p', { text: T('Ohne Cronjob räumt ShrinkURL einmal am Tag auf, sobald jemand die Verwaltung öffnet. Mit Cronjob passiert das auch, wenn niemand da ist. Einmal täglich reicht. Entweder diese Adresse aufrufen lassen:') }),
            el('div', { class: 'codebox mono', style: 'font-size:.85rem;text-align:left', text: BOOT.base + '?action=cron' }),
            el('p', { style: 'margin-top:.8rem', text: T('oder die index.php als Skript starten lassen (PHP auf der Kommandozeile).') })
        ),
    });
    loadUsers();
}

/* ---------- Hilfe ---------- */

function helpDialog() {
    const mark = el('a', { class: 'btn btn-soft', href: "javascript:void(open('" + BOOT.base + "?url='+encodeURIComponent(location.href)))", text: T('Mit %s kürzen', BOOT.site), onclick: e => { e.preventDefault(); toast(T('Nicht anklicken, sondern in die Lesezeichen-Leiste ziehen.'), true); } });
    const kbd = (k, text) => el('tr', {}, el('td', {}, el('span', { class: 'tag mono', text: k })), el('td', { text }));
    dialog({
        title: T('Hilfe & Schnell-Kürzen'), wide: true,
        body: el('div', {},
            el('h3', { text: T('Schnell-Kürzen aus dem Browser') }),
            el('p', { text: T('Zieh diesen Knopf in deine Lesezeichen-Leiste. Ein Klick darauf öffnet ShrinkURL mit der Adresse der Seite, die du gerade ansiehst.') }),
            mark,
            el('h3', { text: T('Am Handy') }),
            el('p', { text: T('Installiere ShrinkURL über das Browser-Menü als App („Zum Startbildschirm hinzufügen“). Danach taucht es im Teilen-Menü auf, und geteilte Adressen landen direkt im Eingabefeld.') }),
            el('h3', { text: T('Gut zu wissen') }),
            el('table', { class: 't' }, el('tbody', {},
                kbd('+', T('Ein Plus hinter dem Kurzlink zeigt, wohin er führt, ohne weiterzuleiten und ohne zu zählen.')),
                kbd('N', T('Springt ins Eingabefeld für eine neue Adresse.')),
                kbd('S  /', T('Springt in die Suche.')),
                kbd('Esc', T('Schließt das offene Fenster.'))
            )),
            el('h3', { text: T('Datenschutz') }),
            el('p', { text: T('Beim Aufruf eines Kurzlinks wird nur ein Zähler für den Tag erhöht. Es werden keine IP-Adressen, keine Cookies, keine Browser-Kennungen und keine Herkunftsseiten gespeichert, und die Zielseite erfährt nicht, über welchen Kurzlink jemand kam. Es wird nichts von fremden Servern geladen.') })
        ),
    });
}

/* ---------- Verdrahten ---------- */

const setIcon = (id, name) => { const b = $(id); if (b) b.innerHTML = icon(name); };
setIcon('btnHelp', 'help'); setIcon('btnAdmin', 'users'); setIcon('btnAccount', 'settings'); setIcon('btnLogout', 'logout'); setIcon('btnAll', 'users');
document.querySelector('.bar .search').insertAdjacentHTML('afterbegin', icon('search'));

$('btnHelp').addEventListener('click', helpDialog);
$('btnAccount').addEventListener('click', accountDialog);
if ($('btnAdmin')) { setIcon('btnAdmin', 'settings'); setIcon('btnAccount', 'user'); $('btnAdmin').addEventListener('click', adminDialog); } else setIcon('btnAccount', 'user');
if ($('btnAll')) $('btnAll').addEventListener('click', () => {
    state.all = !state.all;
    $('btnAll').classList.toggle('on', state.all);
    $('btnAll').setAttribute('aria-pressed', state.all ? 'true' : 'false');
    load(true);
});

let searchTimer = 0;
$('q').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { state.q = $('q').value.trim(); load(true); }, 220);
});
$('filter').addEventListener('change', () => { state.filter = $('filter').value; load(true); });
$('sort').addEventListener('change', () => { state.sort = $('sort').value; load(true); });
$('more').addEventListener('click', () => load(false));

document.addEventListener('keydown', e => {
    if (e.ctrlKey || e.metaKey || e.altKey || document.querySelector('dialog[open]')) return;
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (e.key === 'n' || e.key === 'N') { e.preventDefault(); $('newUrl').focus(); }
    if (e.key === 's' || e.key === 'S' || e.key === '/') { e.preventDefault(); $('q').focus(); }
});

// Vom Schnell-Kürzen-Knopf oder vom Teilen-Menü mitgebrachte Adresse übernehmen
(() => {
    const p = new URLSearchParams(location.search);
    let url = p.get('url') || '';
    if (!url) {
        const m = (p.get('text') || '').match(/https?:\/\/\S+/);
        if (m) url = m[0];
    }
    if (location.search) history.replaceState(null, '', location.pathname);
    if (url) $('newUrl').value = url;
    $('newUrl').focus();
})();

// Konto vom Administrator angelegt: erst ein eigenes Passwort setzen
if (BOOT.user.mustChange) {
    const d = dialog({
        title: T('Bitte ein eigenes Passwort setzen'), locked: true,
        body: el('div', { class: 'stack' },
            el('p', { text: T('Dein Konto hat noch das vorläufige Passwort vom Administrator. Setze jetzt ein eigenes, das nur du kennst.') }),
            passwordForm(() => { BOOT.user.mustChange = false; d.close(); }, true)),
    });
}

load(true);
})();
</script>
