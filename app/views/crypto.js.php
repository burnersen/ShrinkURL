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
    iv(kind, n) {
        const iv = new Uint8Array(12);
        iv[0] = kind; // 0 = Inhalt, 1 = Beschreibung (Name, Typ, Größe)
        new DataView(iv.buffer).setUint32(8, n);
        return iv;
    },
    params(kind, n, total) {
        return { name: 'AES-GCM', iv: SUC.iv(kind, n), additionalData: new TextEncoder().encode(kind + ':' + n + '/' + total) };
    },
    async encChunk(key, n, total, data) { return new Uint8Array(await crypto.subtle.encrypt(SUC.params(0, n, total), key, data)); },
    async decChunk(key, n, total, data) { return new Uint8Array(await crypto.subtle.decrypt(SUC.params(0, n, total), key, data)); },
    async encMeta(key, obj) {
        return SUC.b64e(new Uint8Array(await crypto.subtle.encrypt(SUC.params(1, 0, 1), key, new TextEncoder().encode(JSON.stringify(obj)))));
    },
    async decMeta(key, b64) {
        return JSON.parse(new TextDecoder().decode(await crypto.subtle.decrypt(SUC.params(1, 0, 1), key, SUC.b64d(b64))));
    },
};
