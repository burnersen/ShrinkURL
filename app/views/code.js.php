<?php defined('SU_VERSION') || exit; ?>
/* Abholcode: aus den Wörtern Kennung und Schlüssel berechnen und zur Abholseite springen.
   Der Schlüssel wandert dabei hinter das # der neuen Adresse, also wieder nicht zum Server. */
(() => {
'use strict';
const $ = id => document.getElementById(id);
const fail = msg => { $('cErr').textContent = msg; $('cErr').hidden = false; $('cGo').disabled = false; $('cWrap').hidden = false; };
async function go(code) {
    const phrase = SUC.normPhrase(code);
    if (!/^[a-z]+(-[a-z]+)+-\d+$/.test(phrase)) return fail(CD.t.bad);
    $('cErr').hidden = true;
    $('cGo').disabled = true;
    $('cGo').textContent = CD.t.busy;
    try {
        const r = await SUC.fromPhrase(phrase);
        location.replace(CD.base + r.id + '#' + SUC.b64e(r.raw));
    } catch (e) { fail(CD.t.nocrypto); }
}
if (!SUC.ok) { fail(CD.t.nocrypto); return; }
$('cGo').addEventListener('click', () => go($('cCode').value));
$('cCode').addEventListener('keydown', e => { if (e.key === 'Enter') go($('cCode').value); });
const frag = decodeURIComponent(location.hash.replace(/^#/, ''));
if (frag) { $('cCode').value = frag; $('cWrap').hidden = true; go(frag); }
})();
