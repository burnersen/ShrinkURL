<?php
/* ShrinkURL – Links: prüfen, anlegen, Kurzcodes, Aufräumen */
defined('SU_VERSION') || exit;

/** Zeichen für zufällige Codes: ohne 0/O, 1/l/I, damit man sie auch abtippen und vorlesen kann */
const SU_CODE_ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';

/** Diese Namen sind keine Kurzlinks (Ordner des Programms oder künftige Funktionen) */
const SU_RESERVED = ['app', 'data', 'tools', 'docs', 'dist', 'index', 'admin', 'api', 'login', 'logout', 'f', 'file', 'files', 'n', 'note', 'robots', 'favicon'];

/** Prüft eine Zieladresse, ergänzt https:// und lässt nur http/https zu. Wirft bei Fehlern eine Exception mit Klartext. */
function suCleanUrl($url): string {
    $url = trim((string)$url);
    if ($url === '') throw new Exception(t('Die Zieladresse fehlt.'));
    if (strlen($url) > SU_MAX_URL) throw new Exception(t('Die Zieladresse ist zu lang (höchstens %s Zeichen).', SU_MAX_URL));
    if (!preg_match('~^https?://~i', $url)) {
        if (strpos($url, '://') !== false || preg_match('~^(javascript|data|vbscript|file|mailto|tel|about|blob):~i', $url)) {
            throw new Exception(t('Nur http- und https-Links sind erlaubt.'));
        }
        $url = 'https://' . $url;
    }
    if (preg_match('/[\s<>"\x00-\x1F\x7F]/', $url)) throw new Exception(t('Das ist keine gültige Adresse.'));
    $p = parse_url($url);
    if (!$p || empty($p['host']) || strpos($p['host'], '.') === false && strtolower($p['host']) !== 'localhost') {
        throw new Exception(t('Das ist keine gültige Adresse.'));
    }
    // Ein Kurzlink auf einen Kurzlink derselben Installation wäre eine Schleife
    $own = strtolower(preg_replace('/:\d+$/', '', suHost()) ?? '');
    if (strtolower($p['host']) === $own) {
        $path = trim((string)($p['path'] ?? ''), '/');
        $base = trim(suBasePath(), '/');
        if ($base === '' || $path === $base || strncmp($path, $base . '/', strlen($base) + 1) === 0) {
            throw new Exception(t('Links auf diese Installation selbst lassen sich nicht kürzen.'));
        }
    }
    return $url;
}

/** Entfernt bekannte Tracking-Anhängsel (utm_…, fbclid, gclid, …). Alles andere bleibt, wie es ist. */
function suStripTracking(string $url): string {
    $hashPos = strpos($url, '#');
    $frag = $hashPos === false ? '' : substr($url, $hashPos);
    $main = $hashPos === false ? $url : substr($url, 0, $hashPos);
    $qPos = strpos($main, '?');
    if ($qPos === false) return $url;
    $exact = ['fbclid', 'gclid', 'gclsrc', 'dclid', 'gbraid', 'wbraid', 'msclkid', 'mc_cid', 'mc_eid', 'igshid', 'igsh', 'yclid', 'twclid', 'ttclid',
              'li_fat_id', '_ga', '_gl', '_hsenc', '_hsmi', '__hssc', '__hstc', '__hsfp', 'hsctatracking', 'oly_anon_id', 'oly_enc_id', 'vero_id', 'vero_conv',
              'wickedid', 's_cid', 'mkt_tok', 'ml_subscriber', 'ml_subscriber_hash', 'rb_clickid', 'srsltid', 'si', 'ref_src', 'ref_url', 'cmpid', 'wt_mc', 'wt_zmc'];
    $keep = [];
    foreach (explode('&', substr($main, $qPos + 1)) as $part) {
        if ($part === '') continue;
        $key = strtolower(rawurldecode(explode('=', $part, 2)[0]));
        if (strncmp($key, 'utm_', 4) === 0 || strncmp($key, 'pk_', 3) === 0 || strncmp($key, 'mtm_', 4) === 0 || in_array($key, $exact, true)) continue;
        $keep[] = $part;
    }
    return substr($main, 0, $qPos) . ($keep ? '?' . implode('&', $keep) : '') . $frag;
}

/** Wunschname prüfen. Liefert den Namen oder wirft eine Exception. */
function suCleanAlias(string $alias): string {
    $alias = trim($alias);
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]{1,49}$/', $alias)) {
        throw new Exception(t('Der Wunschname braucht 2 bis 50 Zeichen: Buchstaben, Ziffern, Binde- oder Unterstrich, ohne Umlaute und Leerzeichen.'));
    }
    if (in_array(strtolower($alias), SU_RESERVED, true)) throw new Exception(t('Dieser Name ist für das Programm reserviert.'));
    return $alias;
}

function suCodeTaken(string $code, int $exceptId = 0): bool {
    $st = suDb()->prepare('SELECT 1 FROM links WHERE code = ? AND id != ?');
    $st->execute([$code, $exceptId]);
    return (bool)$st->fetchColumn();
}

/** Zufälliger, noch freier Kurzcode. Wird es eng, werden die Codes von selbst länger. */
function suNewCode(): string {
    $len = SU_CODE_LENGTH;
    for ($try = 0; $try < 40; $try++) {
        $code = '';
        for ($i = 0; $i < $len; $i++) $code .= SU_CODE_ALPHABET[random_int(0, 31)];
        if (!in_array($code, SU_RESERVED, true) && !suCodeTaken($code)) return $code;
        if ($try % 4 === 3) $len++;
    }
    throw new Exception(t('Es ließ sich kein freier Kurzcode finden. Bitte noch einmal versuchen.'));
}

/** Zustand eines Links: active, off, expired oder used (Aufrufe verbraucht) */
function suLinkState(array $l): string {
    if (!$l['active']) return 'off';
    if ($l['expires'] !== null && (int)$l['expires'] <= time()) return 'expired';
    if ($l['max_clicks'] !== null && (int)$l['clicks'] >= (int)$l['max_clicks']) return 'used';
    return 'active';
}

/** So geht ein Link an die Oberfläche: ohne Passwort-Prüfsumme, mit fertiger Kurzadresse */
function suLinkOut(array $l): array {
    return [
        'id' => (int)$l['id'], 'code' => $l['code'], 'short' => suBaseUrl() . $l['code'], 'url' => $l['url'], 'note' => $l['note'],
        'created' => (int)$l['created'], 'expires' => $l['expires'] === null ? null : (int)$l['expires'],
        'max_clicks' => $l['max_clicks'] === null ? null : (int)$l['max_clicks'], 'clicks' => (int)$l['clicks'],
        'has_pass' => $l['pass'] !== null && $l['pass'] !== '', 'active' => (bool)$l['active'], 'last_day' => $l['last_day'],
        'state' => suLinkState($l), 'owner' => $l['owner'] ?? null, 'user_id' => (int)$l['user_id'],
    ];
}

/** Wie lange abgelaufene Links noch aufgehoben werden (0 = nie automatisch löschen) */
function suKeepExpiredDays(): int {
    $v = suSetting('keep_expired_days', (string)SU_KEEP_EXPIRED_DAYS);
    return max(0, min(3650, (int)$v));
}

/**
 * Aufräumen: abgelaufene und verbrauchte Links nach der eingestellten Frist löschen, alte Tageszähler,
 * verwaiste Sitzungen und Sperr-Dateien entfernen. Liefert die Zahl gelöschter Links.
 */
function suCleanup(): int {
    $db = suDb();
    $now = time();
    $deleted = 0;
    $days = suKeepExpiredDays();
    if ($days > 0) {
        $st = $db->prepare('DELETE FROM links WHERE expires IS NOT NULL AND expires <= ?');
        $st->execute([$now - $days * 86400]);
        $deleted += $st->rowCount();
        $st = $db->prepare('DELETE FROM links WHERE max_clicks IS NOT NULL AND clicks >= max_clicks AND last_day IS NOT NULL AND last_day <= ?');
        $st->execute([date('Y-m-d', $now - $days * 86400)]);
        $deleted += $st->rowCount();
    }
    if (function_exists('suFilesCleanup')) suFilesCleanup();
    $db->prepare('DELETE FROM stats WHERE day < ?')->execute([date('Y-m-d', $now - SU_STATS_DAYS * 86400)]);
    foreach (glob(SU_SESSION_DIR . '/sess_*') ?: [] as $f) {
        $age = $now - (int)@filemtime($f);
        if ($age < 7200) continue;
        // Besucher, die sich nie angemeldet haben (Bots auf der Anmeldeseite), nach 2 Stunden wegräumen
        if ($age > SU_SESSION_DAYS * 86400 || strpos((string)@file_get_contents($f, false, null, 0, 2000), 'uid|i:') === false) @unlink($f);
    }
    if (is_file(SU_APP . '/lib/throttle.php')) {
        require_once SU_APP . '/lib/throttle.php';
        suThrottlePrune();
    }
    suSetSetting('last_cleanup', (string)$now);
    return $deleted;
}

/** Ohne Cronjob: einmal am Tag räumt der erste Aufruf der Verwaltung auf */
function suLazyCleanup(): void {
    if ((int)suSetting('last_cleanup', '0') > time() - 86400) return;
    try { suCleanup(); } catch (Throwable $e) {}
}
