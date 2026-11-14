<?php
/* ShrinkURL – Bremse gegen Durchprobieren (Anmeldung, Wiederherstellung, Link-Passwörter) */
defined('SU_VERSION') || exit;

/*
 * Gespeichert wird nur nach einem FEHLversuch, und dann nur eine Prüfsumme der Adresse
 * (mit dem geheimen Wert der Installation verrechnet, also nicht zurückrechenbar) samt Zähler.
 * Die Dateien verschwinden nach spätestens einem Tag wieder. Wer einen Kurzlink einfach nur
 * aufruft, hinterlässt hier nichts.
 */

function suClientIp(): string {
    // Nur REMOTE_ADDR, X-Forwarded-For kann jeder fälschen
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return '0';
    // IPv6 auf das /64-Netz kürzen, sonst hätte ein Angreifer Milliarden Adressen frei
    if (strpos($ip, ':') !== false) {
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16) return bin2hex(substr($bin, 0, 8)) . '/64';
    }
    return $ip;
}

function suThrottleFile(string $bucket): string {
    if (!is_dir(SU_THROTTLE_DIR)) @mkdir(SU_THROTTLE_DIR, 0700, true);
    return SU_THROTTLE_DIR . '/t_' . hash_hmac('sha256', $bucket . '|' . suClientIp(), suConfig()['secret']) . '.json';
}

/** 1. Fehler: noch frei, 2.: 3 s, 3.: 10 s, 4.: 30 s, 5.: 60 s, danach steiler bis höchstens 15 Minuten */
function suThrottleLockSeconds(int $fails): int {
    if ($fails <= 1) return 0;
    if ($fails === 2) return 3;
    if ($fails === 3) return 10;
    if ($fails === 4) return 30;
    if ($fails === 5) return 60;
    return min(900, 60 * (int)pow(2, min($fails - 5, 4)));
}

/** Sekunden, die noch gewartet werden muss (0 = frei) */
function suThrottleWait(string $bucket): int {
    $file = suThrottleFile($bucket);
    if (!is_file($file)) return 0;
    $data = json_decode((string)@file_get_contents($file), true);
    return is_array($data) ? max(0, (int)($data['until'] ?? 0) - time()) : 0;
}

function suThrottleMessage(int $wait): string {
    if ($wait >= 60) return t('Zu viele Fehlversuche. Bitte in ca. %s Minute(n) erneut versuchen.', (int)ceil($wait / 60));
    return t('Zu viele Fehlversuche. Bitte in %s Sekunde(n) erneut versuchen.', $wait);
}

function suThrottleFail(string $bucket): void {
    $now = time();
    $fp = @fopen(suThrottleFile($bucket), 'c+');
    if ($fp === false) return;
    if (flock($fp, LOCK_EX)) {
        $data = json_decode((string)stream_get_contents($fp), true);
        $fails = (is_array($data) && (int)($data['last'] ?? 0) > $now - 86400 ? (int)($data['fails'] ?? 0) : 0) + 1;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, (string)json_encode(['fails' => $fails, 'until' => $now + suThrottleLockSeconds($fails), 'last' => $now]));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    if (random_int(1, 10) === 1) suThrottlePrune();
}

function suThrottleClear(string $bucket): void {
    @unlink(suThrottleFile($bucket));
}

function suThrottlePrune(): void {
    $now = time();
    foreach (glob(SU_THROTTLE_DIR . '/t_*.json') ?: [] as $f) {
        if ((int)@filemtime($f) < $now - 86400) @unlink($f);
    }
}
