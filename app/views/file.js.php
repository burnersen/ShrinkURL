<?php defined('SU_VERSION') || exit; ?>
/* Abholseite: Beschreibung holen, Stücke laden, im Browser entschlüsseln, speichern oder anzeigen */
(() => {
'use strict';
const $ = id => document.getElementById(id);
const go = $('fGo'), err = $('fErr'), bar = $('fProg');
const fail = msg => { err.textContent = msg; err.hidden = false; go.disabled = false; bar.hidden = true; };
const fmtSize = b => (b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : b < 1073741824 ? (b / 1048576).toFixed(1) + ' MB' : (b / 1073741824).toFixed(2) + ' GB').replace('.', FD.dec);

async function post(data) {
    const body = new URLSearchParams(data);
    let res;
    try { res = await fetch(FD.url, { method: 'POST', body, cache: 'no-store', credentials: 'omit' }); } catch (e) { throw new Error(FD.t.net); }
    let json;
    try { json = await res.json(); } catch (e) { throw new Error(FD.t.net); }
    if (!json.ok) { const er = new Error(json.message || FD.t.net); er.gone = !!json.gone; throw er; }
    return json;
}

let key = null, meta = null, info = null, pass = '';

async function loadInfo() {
    info = await post({ op: 'info', p: pass });
    try { meta = await SUC.decMeta(key, info.meta); } catch (e) { throw new Error(FD.t.badkey); }
    if (!FD.note) {
        $('fName').textContent = String(meta.name || '').slice(0, 300);
        $('fSize').textContent = fmtSize(info.size);
        $('fInfo').hidden = false;
    }
    if ($('fPassWrap')) $('fPassWrap').hidden = true;
    go.textContent = FD.t.get;
}

async function fetchChunk(token, n) {
    for (let attempt = 0; ; attempt++) {
        try {
            const res = await fetch(FD.url + '?t=' + token + '&c=' + n, { cache: 'no-store', credentials: 'omit' });
            if (res.ok) return new Uint8Array(await res.arrayBuffer());
            if (res.status === 403 || res.status === 410 || res.status === 400) throw Object.assign(new Error(FD.t.net), { fatal: true });
        } catch (e) { if (e.fatal || attempt >= 3) throw new Error(FD.t.net); }
        await new Promise(r => setTimeout(r, 1000 * (attempt + 1)));
    }
}

async function download() {
    // Große Dateien, wenn der Browser es kann, direkt auf die Platte schreiben statt in den Arbeitsspeicher.
    // Der Speichern-Dialog kommt VOR dem Start, damit ein Abbruch keinen Abruf verbraucht.
    let sink = null;
    const name = String(meta.name || 'download').replace(/[\\/:*?"<>|\x00-\x1f]/g, '_').slice(0, 200) || 'download';
    if (!FD.note && info.size > 200 * 1048576 && window.showSaveFilePicker) {
        try { sink = await (await window.showSaveFilePicker({ suggestedName: name })).createWritable(); }
        catch (e) { if (e && e.name === 'AbortError') { go.disabled = false; return; } sink = null; }
    }
    const start = await post({ op: 'start', p: pass });
    bar.hidden = false;
    go.textContent = FD.t.busy;
    const parts = [];
    try {
        for (let n = 0; n < start.chunks; n++) {
            const enc = await fetchChunk(start.token, n);
            let plain;
            try { plain = await SUC.decChunk(key, n, start.chunks, enc); } catch (e) { throw new Error(FD.t.badkey); }
            if (sink) await sink.write(plain); else parts.push(plain);
            bar.firstChild.style.width = Math.round(((n + 1) / start.chunks) * 100) + '%';
        }
        if (sink) await sink.close();
    } catch (e) {
        if (sink) try { await sink.abort(); } catch (e2) {}
        throw e;
    }
    // Dem Server sagen, dass alles angekommen ist (er löscht auch von selbst, sobald das letzte Stück raus ist)
    try { await post({ op: 'done', t: start.token }); } catch (e) {}
    if (FD.note) {
        const text = new TextDecoder().decode(await new Blob(parts).arrayBuffer());
        $('fNote').value = text;
        $('fNote').hidden = false;
        $('fCopy').hidden = false;
        $('fCopy').onclick = () => { navigator.clipboard.writeText(text).then(() => { $('fCopy').textContent = FD.t.copied; }, () => { $('fNote').select(); }); };
    } else if (!sink) {
        // Immer als Download speichern, nie im Browser öffnen
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(parts, { type: 'application/octet-stream' }));
        a.download = name;
        document.body.append(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 60000);
    }
    bar.hidden = true;
    go.hidden = true;
    const left = $('fLeft');
    left.className = 'box good';
    left.textContent = FD.t.done;
}

async function init() {
    if (!SUC.ok) return fail(FD.t.nocrypto);
    const frag = location.hash.replace(/^#/, '');
    let raw;
    try { raw = SUC.b64d(frag); } catch (e) { raw = null; }
    if (!raw || raw.length !== 32) { go.disabled = true; return fail(FD.t.nokey); }
    key = await SUC.load(raw);
    go.addEventListener('click', async () => {
        err.hidden = true;
        go.disabled = true;
        try {
            if (!info) {
                if (FD.pass) pass = $('fPass').value;
                await loadInfo();
                go.disabled = false;
                return;
            }
            await download();
        } catch (e) {
            fail(e.message);
            if (e.gone) go.hidden = true;
        }
    });
    if (FD.pass) {
        $('fPass').addEventListener('keydown', e => { if (e.key === 'Enter') go.click(); });
    } else {
        // Ohne Passwort gleich zeigen, um welche Datei es geht (zählt nicht als Abruf)
        go.disabled = true;
        try { await loadInfo(); go.disabled = false; } catch (e) { fail(e.message); if (e.gone || e.message === FD.t.badkey) go.hidden = true; }
    }
}
init();
})();
