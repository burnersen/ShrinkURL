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
 * $grp ist der Teil der Adresse hinter /f/: die Kennung der Sendung. Eine Sendung enthält eine Notiz
 * oder eine bis mehrere Dateien. f=<Kennung> wählt bei den Aktionen die einzelne Datei.
 *   GET                 die Abholseite
 *   POST op=info        Liste der Dateien samt Beschreibung holen (prüft das Passwort, zählt nicht)
 *   POST op=start&f=…   Download beginnen: zählt einen Abruf und liefert ein Kennzeichen für die Stücke
 *   GET  ?f=…&t=…&c=n   ein Stück des verschlüsselten Inhalts
 *   POST op=done&f=…    Download fertig (der Server löscht auch von selbst nach dem letzten Stück)
 */
function suHandleFile(string $grp): void {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $api = $method === 'POST' || isset($_GET['c']);
    if (!$api) suFilesCleanupSoon();
    $gone = function () use ($api): void {
        if ($api) suFileJson(['ok' => false, 'gone' => true, 'message' => t('Die Datei oder Notiz liegt nicht mehr auf dem Server.')], 410);
        suFileGone();
    };

    // Abgelaufenes sofort wegräumen, nicht erst beim nächsten Aufräumen
    $rows = [];
    foreach (suFileGroup($grp) as $f) {
        $state = suFileState($f);
        if ($state === 'expired' || ($state === 'used' && !suFileTokens($f))) { suFileDelete($f['id']); continue; }
        if ($state !== 'pending') $rows[$f['id']] = $f;
    }
    if (!$rows) $gone();
    $active = array_filter($rows, fn($f) => suFileState($f) === 'active');
    $first = reset($rows);

    if ($api) {
        $fid = (string)($_POST['f'] ?? $_GET['f'] ?? '');
        if ($fid === '' && count($rows) === 1) $fid = (string)$first['id'];
        $one = $rows[$fid] ?? null;
        if ($method === 'GET') {
            if (!$one) $gone();
            suFileSendChunk($one);
        }
        suFileAction($first, $active, $one);
    }
    if (!$active) suFileGone();

    require_once SU_APP . '/lib/page.php';
    $note = $first['kind'] === 'note';
    $hasPass = $first['pass'] !== null && $first['pass'] !== '';
    $count = count($active);
    $left = (int)reset($active)['max_downloads'] - (int)reset($active)['downloads'];
    if ($note) {
        $hint = $left === 1 ? t('Diese Notiz lässt sich nur ein einziges Mal anzeigen. Danach wird sie gelöscht.') : t('Noch %s Abrufe möglich, danach wird gelöscht.', $left);
    } elseif ($count > 1) {
        $hint = t('Jede Datei wird nach ihrem letzten erlaubten Abruf vom Server gelöscht.');
    } else {
        $hint = $left === 1 ? t('Diese Datei lässt sich nur ein einziges Mal herunterladen. Danach wird sie gelöscht.') : t('Noch %s Abrufe möglich, danach wird gelöscht.', $left);
    }
    $html = '<h1>' . h($note ? t('Jemand hat dir eine Notiz geschickt') : ($count > 1 ? t('Jemand hat dir %s Dateien geschickt', $count) : t('Jemand hat dir eine Datei geschickt'))) . '</h1>'
        . '<p>' . h(t('Der Inhalt ist verschlüsselt und wird erst hier in deinem Browser lesbar. Der Server kann ihn nicht einsehen.')) . '</p>'
        . '<div class="box bad" id="fErr" hidden></div>'
        . '<div id="fList" hidden></div>'
        . ($hasPass ? '<label id="fPassWrap">' . h(t('Passwort')) . '<input type="password" id="fPass" autocomplete="off" autofocus></label>' : '')
        . '<div class="box warn" id="fLeft">' . h($hint) . ' ' . h(t('Verfällt am %s.', date('d.m.Y H:i', (int)$first['expires']))) . '</div>'
        . '<div class="prog" id="fProg" hidden><i></i></div>'
        . '<textarea id="fNote" readonly hidden></textarea>'
        . '<button type="button" id="fGo">' . h($hasPass ? t('Weiter') : ($note ? t('Notiz anzeigen') : ($count > 1 ? t('Alle herunterladen') : t('Herunterladen')))) . '</button>'
        . '<button type="button" class="btn second" id="fCopy" hidden>' . h(t('Text kopieren')) . '</button>'
        . '<noscript><div class="box bad">' . h(t('Zum Entschlüsseln braucht diese Seite JavaScript.')) . '</div></noscript>';
    $boot = [
        'url' => suBasePath() . '/f/' . $grp, 'note' => $note, 'pass' => $hasPass, 'dec' => suLang() === 'en' ? '.' : ',',
        't' => [
            'nokey' => t('In diesem Link fehlt der Schlüssel (der Teil hinter dem #). Bitte den vollständigen Link verwenden.'),
            'nocrypto' => t('Dieser Browser kann hier nicht entschlüsseln. Bitte einen aktuellen Browser und HTTPS verwenden.'),
            'badkey' => t('Der Schlüssel im Link passt nicht zu diesem Inhalt. Bitte den vollständigen Link verwenden.'),
            'net' => t('Die Verbindung ist abgebrochen. Bitte die Seite neu laden und noch einmal versuchen.'),
            'get' => $note ? t('Notiz anzeigen') : t('Herunterladen'),
            'all' => t('Alle herunterladen'),
            'busy' => t('Wird entschlüsselt…'),
            'done' => $note ? t('Das ist die Notiz. War das der letzte erlaubte Abruf, ist sie auf dem Server jetzt gelöscht.') : t('Fertig. Die Datei liegt jetzt in deinen Downloads.'),
            'doneall' => t('Fertig. Die Dateien liegen jetzt in deinen Downloads.'),
            'got' => t('abgeholt'),
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
 * kurz.example.org/f#drei-woerter-zahl: Der Abholcode steht hinter dem # und erreicht den Server nie.
 * Der Browser berechnet daraus Kennung und Schlüssel und springt dann zur eigentlichen Abholseite.
 * Ohne Code in der Adresse gibt es ein Feld zum Eintippen.
 */
function suHandleFileCode(): void {
    require_once SU_APP . '/lib/page.php';
    $html = '<h1>' . h(t('Etwas abholen')) . '</h1>'
        . '<p id="cSub">' . h(t('Gib den Abholcode ein, den du bekommen hast: drei Wörter und eine Zahl.')) . '</p>'
        . '<div class="box bad" id="cErr" hidden></div>'
        . '<label id="cWrap">' . h(t('Abholcode')) . '<input type="text" id="cCode" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="' . h(t('wort-wort-wort-12')) . '" autofocus></label>'
        . '<button type="button" id="cGo">' . h(t('Weiter')) . '</button>'
        . '<noscript><div class="box bad">' . h(t('Zum Entschlüsseln braucht diese Seite JavaScript.')) . '</div></noscript>';
    $boot = ['base' => suBasePath() . '/f/', 't' => [
        'nocrypto' => t('Dieser Browser kann hier nicht entschlüsseln. Bitte einen aktuellen Browser und HTTPS verwenden.'),
        'busy' => t('Einen Moment…'),
        'bad' => t('Das sieht nicht nach einem Abholcode aus. Er besteht aus drei Wörtern und einer Zahl, zum Beispiel wort-wort-wort-12.'),
    ]];
    ob_start();
    require SU_APP . '/views/crypto.js.php';
    require SU_APP . '/views/code.js.php';
    $script = 'const CD = ' . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ";\n" . ob_get_clean();
    suPageOut(t('Abholen'), $html, 200, $script);
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

/**
 * $first: irgendeine Datei der Sendung (trägt das Passwort), $active: die noch abholbaren Dateien,
 * $one: die mit f=… gewählte Datei oder null
 */
function suFileAction(array $first, array $active, ?array $one): void {
    $op = (string)($_POST['op'] ?? '');
    $db = suDb();
    $goneMsg = ['ok' => false, 'gone' => true, 'message' => t('Die Datei oder Notiz liegt nicht mehr auf dem Server.')];

    if ($op === 'done') {
        suFileJson(['ok' => true, 'deleted' => $one ? suFileFinish($one['id'], (string)($_POST['t'] ?? '')) : true]);
    }

    if ($op !== 'info' && $op !== 'start') suFileJson(['ok' => false, 'message' => t('Unbekannte Aktion.')], 400);
    if (!$active) suFileJson($goneMsg, 410);

    if ($first['pass'] !== null && $first['pass'] !== '') {
        require_once SU_APP . '/lib/throttle.php';
        $bucket = 'file' . $first['grp'];
        $wait = suThrottleWait($bucket);
        if ($wait > 0) suFileJson(['ok' => false, 'message' => suThrottleMessage($wait)], 429);
        $given = $_POST['p'] ?? '';
        if (!is_string($given) || $given === '' || strlen($given) > 1000 || !password_verify($given, $first['pass'])) {
            suThrottleFail($bucket);
            suFileJson(['ok' => false, 'message' => t('Das Passwort stimmt nicht.')], 403);
        }
        suThrottleClear($bucket);
    }

    if ($op === 'info') {
        $list = [];
        foreach ($active as $f) {
            $list[] = ['id' => $f['id'], 'idx' => (int)$f['idx'], 'meta' => $f['meta'], 'chunks' => (int)$f['chunks'], 'size' => (int)$f['plain_size'],
                       'left' => (int)$f['max_downloads'] - (int)$f['downloads']];
        }
        suFileJson(['ok' => true, 'files' => $list]);
    }

    // Abruf zählen und ein Kennzeichen ausgeben, mit dem die Stücke geholt werden dürfen
    if (!$one || !isset($active[$one['id']])) suFileJson($goneMsg, 410);
    $db->exec('BEGIN IMMEDIATE');
    $cur = suFileRow($one['id']);
    if (!$cur || suFileState($cur) !== 'active') {
        $db->exec('COMMIT');
        suFileJson($goneMsg, 410);
    }
    $tokens = suFileTokens($cur);
    $token = bin2hex(random_bytes(16));
    $tokens[$token] = time() + SU_DOWNLOAD_TOKEN_HOURS * 3600;
    $db->prepare('UPDATE files SET downloads = downloads + 1, tokens = ? WHERE id = ?')->execute([json_encode((object)$tokens), $cur['id']]);
    $db->exec('COMMIT');
    suFileJson(['ok' => true, 'token' => $token, 'chunks' => (int)$cur['chunks']]);
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
