<?php defined('SU_VERSION') || exit; ?>
/* Abholseite: Liste holen, Stücke laden, im Browser entschlüsseln, speichern oder anzeigen */
(() => {
'use strict';
const $ = id => document.getElementById(id);
const go = $('fGo'), err = $('fErr'), bar = $('fProg'), list = $('fList');
const fail = msg => { err.textContent = msg; err.hidden = false; go.disabled = false; bar.hidden = true; };
const fmtSize = b => (b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : b < 1073741824 ? (b / 1048576).toFixed(1) + ' MB' : (b / 1073741824).toFixed(2) + ' GB').replace('.', FD.dec);
const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };

async function post(data) {
    const body = new URLSearchParams(data);
    let res;
    try { res = await fetch(FD.url, { method: 'POST', body, cache: 'no-store', credentials: 'omit' }); } catch (e) { throw new Error(FD.t.net); }
    let json;
    try { json = await res.json(); } catch (e) { throw new Error(FD.t.net); }
    if (!json.ok) { const er = new Error(json.message || FD.t.net); er.gone = !!json.gone; throw er; }
    return json;
}

let key = null, files = null, pass = '', busy = false;

async function loadInfo() {
    const info = await post({ op: 'info', p: pass });
    for (const f of info.files) {
        try { f.meta = await SUC.decMeta(key, f.meta, f.idx); } catch (e) { throw new Error(FD.t.badkey); }
        f.name = String(f.meta.name || 'download').replace(/[\\/:*?"<>|\x00-\x1f]/g, '_').slice(0, 200) || 'download';
    }
    files = info.files;
    if ($('fPassWrap')) $('fPassWrap').hidden = true;
    if (!FD.note) {
        const many = files.length > 1;
        list.replaceChildren(...files.map(f => {
            const row = el('div', 'frow');
            const text = el('div', 'ftext');
            text.append(el('span', 'fname', f.name), el('span', 'fsize', fmtSize(f.size)));
            row.append(text);
            if (many) {
                f.btn = el('button', 'fbtn', FD.t.get);
                f.btn.type = 'button';
                f.btn.addEventListener('click', () => run([f], true));
                row.append(f.btn);
            }
            f.row = row;
            return row;
        }));
        list.hidden = false;
        go.textContent = many ? FD.t.all : FD.t.get;
    } else {
        go.textContent = FD.t.get;
    }
}

async function fetchChunk(f, token, n) {
    for (let attempt = 0; ; attempt++) {
        try {
            const res = await fetch(FD.url + '?f=' + f.id + '&t=' + token + '&c=' + n, { cache: 'no-store', credentials: 'omit' });
            if (res.ok) return new Uint8Array(await res.arrayBuffer());
            if (res.status === 403 || res.status === 410 || res.status === 400) throw Object.assign(new Error(FD.t.net), { fatal: true });
        } catch (e) { if (e.fatal || attempt >= 3) throw new Error(FD.t.net); }
        await new Promise(r => setTimeout(r, 1000 * (attempt + 1)));
    }
}

/** Holt eine Datei (oder die Notiz). Liefert bei einer Notiz den Text. */
async function download(f, direct) {
    // Große Dateien, wenn der Browser es kann, direkt auf die Platte schreiben statt in den Arbeitsspeicher.
    // Der Speichern-Dialog kommt VOR dem Start, damit ein Abbruch keinen Abruf verbraucht.
    let sink = null;
    if (direct && !FD.note && f.size > 200 * 1048576 && window.showSaveFilePicker) {
        try { sink = await (await window.showSaveFilePicker({ suggestedName: f.name })).createWritable(); }
        catch (e) { if (e && e.name === 'AbortError') return false; sink = null; }
    }
    const start = await post({ op: 'start', f: f.id, p: pass });
    bar.hidden = false;
    bar.firstChild.style.width = '0';
    const parts = [];
    try {
        for (let n = 0; n < start.chunks; n++) {
            const enc = await fetchChunk(f, start.token, n);
            let plain;
            try { plain = await SUC.decChunk(key, n, start.chunks, enc, f.idx); } catch (e) { throw new Error(FD.t.badkey); }
            if (sink) await sink.write(plain); else parts.push(plain);
            bar.firstChild.style.width = Math.round(((n + 1) / start.chunks) * 100) + '%';
        }
        if (sink) await sink.close();
    } catch (e) {
        if (sink) try { await sink.abort(); } catch (e2) {}
        throw e;
    }
    // Dem Server sagen, dass alles angekommen ist (er löscht auch von selbst, sobald das letzte Stück raus ist)
    try { await post({ op: 'done', f: f.id, t: start.token }); } catch (e) {}
    f.left--;
    if (FD.note) return new TextDecoder().decode(await new Blob(parts).arrayBuffer());
    if (!sink) {
        // Immer als Download speichern, nie im Browser öffnen
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(parts, { type: 'application/octet-stream' }));
        a.download = f.name;
        document.body.append(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 60000);
    }
    return true;
}

/** Holt die angegebenen Dateien nacheinander ab */
async function run(todo, direct) {
    if (busy) return;
    busy = true;
    err.hidden = true;
    go.disabled = true;
    const label = go.textContent;
    go.textContent = FD.t.busy;
    try {
        for (const f of todo) {
            if (f.left <= 0) continue;
            if (f.btn) f.btn.disabled = true;
            const res = await download(f, direct && todo.length === 1);
            if (res === false) { if (f.btn) f.btn.disabled = false; continue; }
            if (FD.note) {
                $('fNote').value = res;
                $('fNote').hidden = false;
                $('fCopy').hidden = false;
                $('fCopy').onclick = () => { navigator.clipboard.writeText(res).then(() => { $('fCopy').textContent = FD.t.copied; }, () => { $('fNote').select(); }); };
            }
            if (f.row) f.row.classList.add('got');
            if (f.btn) { if (f.left > 0) f.btn.disabled = false; else f.btn.textContent = FD.t.got; }
            // Kurze Pause, sonst verschluckt mancher Browser mehrere Downloads direkt hintereinander
            if (todo.length > 1) await new Promise(r => setTimeout(r, 400));
        }
    } catch (e) {
        busy = false;
        go.textContent = label;
        fail(e.message);
        if (e.gone) go.hidden = true;
        return;
    }
    busy = false;
    bar.hidden = true;
    go.textContent = label;
    go.disabled = false;
    if (FD.note || files.every(f => f.left <= 0)) {
        go.hidden = true;
        const left = $('fLeft');
        left.className = 'box good';
        left.textContent = FD.note ? FD.t.done : (files.length > 1 ? FD.t.doneall : FD.t.done);
    }
}

async function init() {
    if (!SUC.ok) return fail(FD.t.nocrypto);
    const frag = location.hash.replace(/^#/, '');
    let raw;
    try { raw = SUC.b64d(frag); } catch (e) { raw = null; }
    if (!raw || raw.length !== 32) { go.disabled = true; return fail(FD.t.nokey); }
    key = await SUC.load(raw);
    const first = async () => {
        err.hidden = true;
        go.disabled = true;
        try {
            if (FD.pass) pass = $('fPass').value;
            await loadInfo();
            go.disabled = false;
        } catch (e) {
            fail(e.message);
            if (e.gone || e.message === FD.t.badkey) go.hidden = true;
        }
    };
    go.addEventListener('click', () => { if (!files) first(); else run(files, true); });
    if (FD.pass) $('fPass').addEventListener('keydown', e => { if (e.key === 'Enter') go.click(); });
    else first(); // ohne Passwort gleich zeigen, worum es geht (zählt nicht als Abruf)
}
init();
})();
