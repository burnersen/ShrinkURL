<?php
/* ShrinkURL – Datei oder Notiz abholen. Läuft ohne Anmeldung und ohne Sitzung. */
defined('SU_VERSION') || exit;

function suFileJson(array $data, int $status = 200): void {
    http_response_code($status);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    jsonOut($data);
}

function suFileGone(): void {
    require_once SU_APP . '/lib/page.php';
    suPageOut(t('Nicht mehr da'),
        '<div class="big">410</div><h1>' . h(t('Hier gibt es nichts mehr abzuholen')) . '</h1>'
      . '<p>' . h(t('Die Datei oder Notiz wurde schon abgeholt, ist abgelaufen oder wurde gelöscht. Sie liegt nicht mehr auf dem Server.')) . '</p>', 410);
}

/**
 * $id ist der Teil der Adresse hinter /f/.
 *   GET            die Abholseite
 *   POST op=info   Beschreibung holen (prüft das Passwort, zählt nicht)
 *   POST op=start  Download beginnen: zählt einen Abruf und liefert ein Kennzeichen für die Stücke
 *   GET  ?t=…&c=n  ein Stück des verschlüsselten Inhalts
 *   POST op=done   Download fertig: war es der letzte erlaubte, wird sofort gelöscht
 */
function suHandleFile(string $id): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $api = $method === 'POST' || isset($_GET['c']);
    if (!$api) suFilesCleanupSoon();
    $f = suFileRow($id);
    $state = $f ? suFileState($f) : 'none';
    // Abgelaufenes sofort wegräumen, nicht erst beim nächsten Aufräumen
    if ($f && ($state === 'expired' || ($state === 'used' && !suFileTokens($f)))) {
        suFileDelete($f['id']);
        $f = null;
    }
    if (!$f || $state === 'pending') {
        if ($api) suFileJson(['ok' => false, 'gone' => true, 'message' => t('Die Datei oder Notiz liegt nicht mehr auf dem Server.')], 410);
        suFileGone();
    }

    if ($method === 'GET' && isset($_GET['c'])) suFileSendChunk($f);
    if ($method === 'POST') suFileAction($f, $state);
    if ($state !== 'active') suFileGone();

    require_once SU_APP . '/lib/page.php';
    $note = $f['kind'] === 'note';
    $hasPass = $f['pass'] !== null && $f['pass'] !== '';
    $left = (int)$f['max_downloads'] - (int)$f['downloads'];
    $html = '<h1>' . h($note ? t('Jemand hat dir eine Notiz geschickt') : t('Jemand hat dir eine Datei geschickt')) . '</h1>'
        . '<p>' . h(t('Der Inhalt ist verschlüsselt und wird erst hier in deinem Browser lesbar. Der Server kann ihn nicht einsehen.')) . '</p>'
        . '<div class="box bad" id="fErr" hidden></div>'
        . '<span class="target" id="fInfo" hidden><span class="fname" id="fName"></span><br><span id="fSize"></span></span>'
        . ($hasPass ? '<label id="fPassWrap">' . h(t('Passwort')) . '<input type="password" id="fPass" autocomplete="off" autofocus></label>' : '')
        . '<div class="box warn" id="fLeft">' . h($left === 1
            ? ($note ? t('Diese Notiz lässt sich nur ein einziges Mal anzeigen. Danach wird sie gelöscht.') : t('Diese Datei lässt sich nur ein einziges Mal herunterladen. Danach wird sie gelöscht.'))
            : t('Noch %s Abrufe möglich, danach wird gelöscht.', $left))
        . ' ' . h(t('Verfällt am %s.', date('d.m.Y H:i', (int)$f['expires']))) . '</div>'
        . '<div class="prog" id="fProg" hidden><i></i></div>'
        . '<textarea id="fNote" readonly hidden></textarea>'
        . '<button type="button" id="fGo">' . h($hasPass ? t('Weiter') : ($note ? t('Notiz anzeigen') : t('Herunterladen'))) . '</button>'
        . '<button type="button" class="btn second" id="fCopy" hidden>' . h(t('Text kopieren')) . '</button>'
        . '<noscript><div class="box bad">' . h(t('Zum Entschlüsseln braucht diese Seite JavaScript.')) . '</div></noscript>';
    $boot = [
        'url' => suBasePath() . '/f/' . $f['id'], 'note' => $note, 'pass' => $hasPass, 'dec' => suLang() === 'en' ? '.' : ',',
        't' => [
            'nokey' => t('In diesem Link fehlt der Schlüssel (der Teil hinter dem #). Bitte den vollständigen Link verwenden.'),
            'nocrypto' => t('Dieser Browser kann hier nicht entschlüsseln. Bitte einen aktuellen Browser und HTTPS verwenden.'),
            'badkey' => t('Der Schlüssel im Link passt nicht zu diesem Inhalt. Bitte den vollständigen Link verwenden.'),
            'net' => t('Die Verbindung ist abgebrochen. Bitte die Seite neu laden und noch einmal versuchen.'),
            'get' => $note ? t('Notiz anzeigen') : t('Herunterladen'),
            'busy' => t('Wird entschlüsselt…'),
            'done' => $note ? t('Das ist die Notiz. War das der letzte erlaubte Abruf, ist sie auf dem Server jetzt gelöscht.') : t('Fertig. Die Datei liegt jetzt in deinen Downloads.'),
            'copied' => t('Kopiert'),
            'note' => t('Notiz'),
        ],
    ];
    ob_start();
    require SU_APP . '/views/crypto.js.php';
    require SU_APP . '/views/file.js.php';
    $script = 'const FD = ' . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ";\n" . ob_get_clean();
    suPageOut($note ? t('Notiz') : t('Datei'), $html, 200, $script);
}

/**
 * Beendet einen Download: Das Kennzeichen wird ungültig. Sind damit alle erlaubten Abrufe verbraucht
 * und läuft kein anderer Download mehr, wird die Datei sofort gelöscht. Liefert true, wenn gelöscht wurde.
 */
function suFileFinish(string $id, string $token): bool {
    $db = suDb();
    $db->exec('BEGIN IMMEDIATE');
    $cur = suFileRow($id);
    if (!$cur) { $db->exec('COMMIT'); return true; }
    $tokens = suFileTokens($cur);
    unset($tokens[$token]);
    $last = (int)$cur['downloads'] >= (int)$cur['max_downloads'] && !$tokens;
    if (!$last) $db->prepare('UPDATE files SET tokens = ? WHERE id = ?')->execute([json_encode((object)$tokens), $id]);
    $db->exec('COMMIT');
    if ($last) suFileDelete($id);
    return $last;
}

function suFileAction(array $f, string $state): void {
    $op = (string)($_POST['op'] ?? '');
    $db = suDb();

    if ($op === 'done') {
        suFileJson(['ok' => true, 'deleted' => suFileFinish($f['id'], (string)($_POST['t'] ?? ''))]);
    }

    if ($op !== 'info' && $op !== 'start') suFileJson(['ok' => false, 'message' => t('Unbekannte Aktion.')], 400);
    if ($state !== 'active') suFileJson(['ok' => false, 'gone' => true, 'message' => t('Die Datei oder Notiz liegt nicht mehr auf dem Server.')], 410);

    if ($f['pass'] !== null && $f['pass'] !== '') {
        require_once SU_APP . '/lib/throttle.php';
        $bucket = 'file' . $f['id'];
        $wait = suThrottleWait($bucket);
        if ($wait > 0) suFileJson(['ok' => false, 'message' => suThrottleMessage($wait)], 429);
        $given = $_POST['p'] ?? '';
        if (!is_string($given) || $given === '' || strlen($given) > 1000 || !password_verify($given, $f['pass'])) {
            suThrottleFail($bucket);
            suFileJson(['ok' => false, 'message' => t('Das Passwort stimmt nicht.')], 403);
        }
        suThrottleClear($bucket);
    }

    $out = ['ok' => true, 'meta' => $f['meta'], 'chunks' => (int)$f['chunks'], 'size' => (int)$f['plain_size']];
    if ($op === 'info') suFileJson($out);

    // Abruf zählen und ein Kennzeichen ausgeben, mit dem die Stücke geholt werden dürfen
    $db->exec('BEGIN IMMEDIATE');
    $cur = suFileRow($f['id']);
    if (!$cur || suFileState($cur) !== 'active') {
        $db->exec('COMMIT');
        suFileJson(['ok' => false, 'gone' => true, 'message' => t('Die Datei oder Notiz liegt nicht mehr auf dem Server.')], 410);
    }
    $tokens = suFileTokens($cur);
    $token = bin2hex(random_bytes(16));
    $tokens[$token] = time() + SU_DOWNLOAD_TOKEN_HOURS * 3600;
    $db->prepare('UPDATE files SET downloads = downloads + 1, tokens = ? WHERE id = ?')->execute([json_encode((object)$tokens), $cur['id']]);
    $db->exec('COMMIT');
    $out['token'] = $token;
    suFileJson($out);
}

function suFileSendChunk(array $f): void {
    $token = (string)($_GET['t'] ?? '');
    $n = (int)$_GET['c'];
    $tokens = suFileTokens($f);
    if ($token === '' || !isset($tokens[$token])) suFileJson(['ok' => false, 'message' => t('Dieser Download ist nicht (mehr) freigegeben.')], 403);
    if (!$f['complete'] || $n < 0 || $n >= (int)$f['chunks']) suFileJson(['ok' => false, 'message' => t('Unbekannte Aktion.')], 400);
    $cs = (int)$f['chunk_size'] + 16;
    $len = $n < (int)$f['chunks'] - 1 ? $cs : (int)$f['size'] - $cs * ((int)$f['chunks'] - 1);
    $fp = @fopen(suFilePath($f['id']), 'rb');
    if (!$fp || fseek($fp, $n * $cs) !== 0) suFileJson(['ok' => false, 'gone' => true, 'message' => t('Die Datei oder Notiz liegt nicht mehr auf dem Server.')], 410);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $len);
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    while ($len > 0 && !feof($fp)) {
        $buf = fread($fp, min(1048576, $len));
        if ($buf === false || $buf === '') break;
        $len -= strlen($buf);
        echo $buf;
    }
    fclose($fp);
    // Das letzte Stück ist vollständig raus: dieser Download ist fertig. War es der letzte erlaubte,
    // löscht der Server jetzt selbst, ohne auf eine Rückmeldung des Browsers zu warten.
    if ($len === 0 && $n === (int)$f['chunks'] - 1) {
        @ob_flush();
        flush();
        if (!connection_aborted()) {
            ignore_user_abort(true);
            try { suFileFinish($f['id'], $token); } catch (Throwable $e) {}
        }
    }
    exit;
}
