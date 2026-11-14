<?php defined('SU_VERSION') || exit; ?>
/* Verschlüsselung für Dateien und Notizen. Läuft nur im Browser (WebCrypto, AES-256-GCM).
   Jedes Stück hat seine eigene Nummer im Startwert und ist an seine Position gebunden,
   vertauschen, weglassen oder anhängen fällt beim Entschlüsseln auf. */
const SUC = {
    ok: !!(window.crypto && crypto.subtle && window.isSecureContext !== false),
    b64e(bytes) {
        let s = '';
        for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    },
    b64d(str) {
        const bin = atob(str.replace(/-/g, '+').replace(/_/g, '/'));
        const out = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out;
    },
    newKey() { return crypto.getRandomValues(new Uint8Array(32)); },
    load(raw) { return crypto.subtle.importKey('raw', raw, 'AES-GCM', false, ['encrypt', 'decrypt']); },
    /* Startwert je Stück: Art, Nummer der Datei in der Sendung, Nummer des Stücks. So wiederholt sich
       unter einem Schlüssel nie ein Startwert, auch wenn eine Sendung mehrere Dateien enthält. */
    iv(kind, n, idx) {
        const iv = new Uint8Array(12);
        iv[0] = kind; // 0 = Inhalt, 1 = Beschreibung (Name, Typ, Größe)
        iv[1] = (idx >>> 16) & 255; iv[2] = (idx >>> 8) & 255; iv[3] = idx & 255;
        new DataView(iv.buffer).setUint32(8, n);
        return iv;
    },
    params(kind, n, total, idx) {
        idx = idx || 0;
        return { name: 'AES-GCM', iv: SUC.iv(kind, n, idx), additionalData: new TextEncoder().encode(kind + ':' + n + '/' + total + (idx ? '#' + idx : '')) };
    },
    async encChunk(key, n, total, data, idx) { return new Uint8Array(await crypto.subtle.encrypt(SUC.params(0, n, total, idx), key, data)); },
    async decChunk(key, n, total, data, idx) { return new Uint8Array(await crypto.subtle.decrypt(SUC.params(0, n, total, idx), key, data)); },
    async encMeta(key, obj, idx) {
        return SUC.b64e(new Uint8Array(await crypto.subtle.encrypt(SUC.params(1, 0, 1, idx), key, new TextEncoder().encode(JSON.stringify(obj)))));
    },
    async decMeta(key, b64, idx) {
        return JSON.parse(new TextDecoder().decode(await crypto.subtle.decrypt(SUC.params(1, 0, 1, idx), key, SUC.b64d(b64))));
    },

    /* Abholcode aus Wörtern („wort-wort-wort-12“). Groß/klein und Leerzeichen statt Bindestrichen sind egal. */
    normPhrase(s) {
        return String(s).toLowerCase().trim().replace(/[\s_.,]+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/-+/g, '-').replace(/^-|-$/g, '');
    },
    /* Aus dem Code entstehen die Kennung (die bekommt der Server) und der Schlüssel (der bleibt im Browser).
       Die Rechnung ist absichtlich langsam (PBKDF2, eine Million Runden): Wer Codes durchprobieren will,
       braucht für jeden Versuch dieselbe Zeit. Von der Kennung lässt sich nicht auf den Schlüssel schließen. */
    async fromPhrase(phrase) {
        const enc = new TextEncoder();
        const base = await crypto.subtle.importKey('raw', enc.encode(SUC.normPhrase(phrase)), 'PBKDF2', false, ['deriveBits']);
        const bits = new Uint8Array(await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: enc.encode('ShrinkURL Abholcode v1'), iterations: 1000000 }, base, 336));
        const abc = 'abcdefghijkmnpqrstuvwxyz23456789';
        let id = '', acc = 0, n = 0;
        for (let i = 0; i < 10; i++) {
            acc = (acc << 8) | bits[i]; n += 8;
            while (n >= 5) { id += abc[(acc >>> (n - 5)) & 31]; n -= 5; }
        }
        return { id, raw: bits.slice(10, 42) };
    },
};
