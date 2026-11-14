<?php
/* ShrinkURL – Zwei-Faktor (TOTP) */
defined('SU_VERSION') || exit;

/* ---------- Zwei-Faktor (TOTP, z. B. Aegis, Google Authenticator, 2FAS) ---------- */

function suBase32Encode(string $bin): string {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $abc[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function suBase32Decode(string $s): string {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
    $bits = '';
    foreach (str_split($s) as $c) {
        $p = strpos($abc, $c);
        if ($p === false) continue;
        $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    return $out;
}

function suTotpAt(string $secretBin, int $counter): string {
    $h = hash_hmac('sha1', pack('J', $counter), $secretBin, true);
    $o = ord($h[19]) & 0x0f;
    $n = (unpack('N', substr($h, $o, 4))[1] & 0x7fffffff) % 1000000;
    return str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

/** Prüft einen 6-stelligen Code (±30 s Toleranz). Ergebnis: verwendeter Zähler oder 0. */
function suTotpVerify(string $secretB32, string $code, int $lastCounter): int {
    $code = preg_replace('/\D/', '', $code) ?? '';
    $bin = suBase32Decode($secretB32);
    if (strlen($code) !== 6 || strlen($bin) < 10) return 0;
    $now = intdiv(time(), 30);
    $hit = 0;
    for ($c = $now - 1; $c <= $now + 1; $c++) {
        if (hash_equals(suTotpAt($bin, $c), $code) && $c > $lastCounter) $hit = $c;
    }
    return $hit;
}

function suTotpNewSecret(): string {
    return suBase32Encode(random_bytes(20));
}

/** Adresse für den QR-Code, den die Authenticator-App einliest (bewusst kurz, damit der QR-Code klein bleibt) */
function suTotpUri(string $secretB32, string $user): string {
    $issuer = preg_replace('/[^A-Za-z0-9 ._\-]/', '', suSiteName()) ?? '';
    $issuer = trim(substr($issuer, 0, 24));
    if ($issuer === '') $issuer = 'ShrinkURL';
    return 'otpauth://totp/' . rawurlencode($user) . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer);
}
