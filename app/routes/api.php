<?php
/* ShrinkURL – Alle JSON-Aktionen der Verwaltung. Jede braucht eine Anmeldung, POST und das Sicherheits-Token. */
defined('SU_VERSION') || exit;

if ($action === null) return;

suSecurityHeaders(false);
header('Cache-Control: no-store, no-cache, must-revalidate, private');

$suUser = suCurrentUser();
if ($suUser === null) jsonFail(t('Du bist nicht mehr angemeldet. Bitte die Seite neu laden.'), 401);
if (!$isPost) jsonFail(t('Unbekannte Aktion.'), 405);
if (!suCsrfCheck()) jsonFail(t('Sicherheits-Token ungültig oder fehlt. Bitte die Seite neu laden.'), 403);

$suUid = (int)$suUser['id'];
$suAdmin = $suUser['role'] === 'admin';
$db = suDb();

/** Link laden, den der angemeldete Benutzer bearbeiten darf (eigene, als Administrator alle) */
$suOwnLink = function (int $id) use ($db, $suUid, $suAdmin): array {
    $st = $db->prepare('SELECT l.*, u.name AS owner FROM links l JOIN users u ON u.id = l.user_id WHERE l.id = ?');
    $st->execute([$id]);
    $l = $st->fetch();
    if (!$l || (!$suAdmin && (int)$l['user_id'] !== $suUid)) jsonFail(t('Diesen Link gibt es nicht (mehr).'));
    return $l;
};

$suNeedAdmin = function () use ($suAdmin): void {
    if (!$suAdmin) jsonFail(t('Das darf nur ein Administrator.'), 403);
};

$suNeedPassword = function () use ($suUser): void {
    // Auch hier gebremst: wer an ein offenes Gerät kommt, soll das Passwort nicht durchprobieren können
    $wait = suThrottleWait('confirm');
    if ($wait > 0) jsonFail(suThrottleMessage($wait));
    if (!password_verify(suPostPass('password'), $suUser['pass'])) {
        suThrottleFail('confirm');
        jsonFail(t('Das Passwort stimmt nicht.'));
    }
    suThrottleClear('confirm');
};

try {
    switch ($action) {

    /* ---------- Links ---------- */

    case 'list': {
        $q = suPostStr('q', 200);
        $all = $suAdmin && !empty($_POST['all']);
        $where = [];
        $args = [];
        if (!$all) { $where[] = 'l.user_id = ?'; $args[] = $suUid; }
        if ($q !== '') {
            $like = '%' . strtr($q, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $where[] = "(l.code LIKE ? ESCAPE '\\' OR l.url LIKE ? ESCAPE '\\' OR l.note LIKE ? ESCAPE '\\')";
            array_push($args, $like, $like, $like);
        }
        $now = time();
        switch ((string)($_POST['filter'] ?? '')) {
            case 'active': $where[] = "l.active = 1 AND (l.expires IS NULL OR l.expires > $now) AND (l.max_clicks IS NULL OR l.clicks < l.max_clicks)"; break;
            case 'ended':  $where[] = "(l.active = 0 OR (l.expires IS NOT NULL AND l.expires <= $now) OR (l.max_clicks IS NOT NULL AND l.clicks >= l.max_clicks))"; break;
            case 'limited': $where[] = '(l.expires IS NOT NULL OR l.max_clicks IS NOT NULL OR l.pass IS NOT NULL)'; break;
        }
        $order = ['new' => 'l.created DESC, l.id DESC', 'old' => 'l.created ASC, l.id ASC', 'clicks' => 'l.clicks DESC, l.id DESC', 'code' => 'l.code COLLATE NOCASE ASC']
                 [(string)($_POST['sort'] ?? 'new')] ?? 'l.created DESC, l.id DESC';
        $limit = 60;
        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $st = $db->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(l.clicks), 0) AS c FROM links l $sqlWhere");
        $st->execute($args);
        $sum = $st->fetch();
        $st = $db->prepare("SELECT l.*, u.name AS owner FROM links l JOIN users u ON u.id = l.user_id $sqlWhere ORDER BY $order LIMIT $limit OFFSET $offset");
        $st->execute($args);
        jsonOut(['success' => true, 'links' => array_map('suLinkOut', $st->fetchAll()), 'total' => (int)$sum['n'], 'clicks' => (int)$sum['c'], 'offset' => $offset, 'limit' => $limit]);
    }

    case 'save': {
        $id = (int)($_POST['id'] ?? 0);
        $old = $id > 0 ? $suOwnLink($id) : null;
        $url = suCleanUrl($_POST['url'] ?? '');
        if (!empty($_POST['strip'])) $url = suCleanUrl(suStripTracking($url));
        $alias = suPostStr('code', 80);
        $note = suPostStr('note', 300);

        $expires = null;
        $rawExp = suPostStr('expires', 20);
        if ($rawExp !== '') {
            $expires = (int)$rawExp;
            if ($expires <= time() && (!$old || (int)$old['expires'] !== $expires)) throw new Exception(t('Das Ablaufdatum liegt in der Vergangenheit.'));
            if ($expires > time() + 50 * 365 * 86400) throw new Exception(t('Das Ablaufdatum liegt zu weit in der Zukunft.'));
        }
        $max = null;
        $rawMax = suPostStr('max_clicks', 12);
        if ($rawMax !== '') {
            $max = (int)$rawMax;
            if ($max < 1 || $max > 1000000000) throw new Exception(t('Die Zahl der erlaubten Aufrufe muss mindestens 1 sein.'));
        }

        $pass = $old ? $old['pass'] : null;
        $passMode = (string)($_POST['pass_mode'] ?? 'keep');
        if ($passMode === 'remove') $pass = null;
        if ($passMode === 'set') {
            $p = suPostPass('pass');
            if (strlen($p) < 4) throw new Exception(t('Das Link-Passwort braucht mindestens 4 Zeichen.'));
            $pass = password_hash($p, PASSWORD_DEFAULT);
        }

        if ($old) {
            $code = $old['code'];
            if ($alias !== '' && strcasecmp($alias, $code) !== 0) {
                $code = suCleanAlias($alias);
                if (suCodeTaken($code, $id)) throw new Exception(t('Dieser Wunschname ist schon vergeben.'));
            } elseif ($alias !== '') {
                $code = suCleanAlias($alias); // nur Groß-/Kleinschreibung geändert
            }
            $db->prepare('UPDATE links SET code = ?, url = ?, note = ?, expires = ?, max_clicks = ?, pass = ? WHERE id = ?')
               ->execute([$code, $url, $note, $expires, $max, $pass, $id]);
        } else {
            if ($alias !== '') {
                $code = suCleanAlias($alias);
                if (suCodeTaken($code)) throw new Exception(t('Dieser Wunschname ist schon vergeben.'));
            } else {
                $code = suNewCode();
            }
            $db->prepare('INSERT INTO links (code, url, user_id, note, created, expires, max_clicks, pass) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$code, $url, $suUid, $note, time(), $expires, $max, $pass]);
            $id = (int)$db->lastInsertId();
        }
        jsonOut(['success' => true, 'link' => suLinkOut($suOwnLink($id))]);
    }

    case 'toggle': {
        $l = $suOwnLink((int)($_POST['id'] ?? 0));
        $db->prepare('UPDATE links SET active = ? WHERE id = ?')->execute([empty($_POST['active']) ? 0 : 1, $l['id']]);
        jsonOut(['success' => true, 'link' => suLinkOut($suOwnLink((int)$l['id']))]);
    }

    case 'delete': {
        $l = $suOwnLink((int)($_POST['id'] ?? 0));
        $db->prepare('DELETE FROM links WHERE id = ?')->execute([$l['id']]);
        jsonOut(['success' => true]);
    }

    case 'stats': {
        $l = $suOwnLink((int)($_POST['id'] ?? 0));
        $days = in_array((int)($_POST['days'] ?? 30), [30, 90, 365], true) ? (int)$_POST['days'] : 30;
        $from = date('Y-m-d', time() - ($days - 1) * 86400);
        $st = $db->prepare('SELECT day, clicks FROM stats WHERE link_id = ? AND day >= ?');
        $st->execute([$l['id'], $from]);
        $have = [];
        foreach ($st as $r) $have[$r['day']] = (int)$r['clicks'];
        $out = [];
        $d = new DateTime($from);
        for ($i = 0; $i < $days; $i++) {
            $key = $d->format('Y-m-d');
            $out[] = ['day' => $key, 'clicks' => $have[$key] ?? 0];
            $d->modify('+1 day');
        }
        jsonOut(['success' => true, 'link' => suLinkOut($l), 'days' => $out]);
    }

    case 'export': {
        // Eigene Links als CSV (öffnet sich in jeder Tabellenkalkulation)
        $st = $db->prepare('SELECT * FROM links WHERE user_id = ? ORDER BY created DESC');
        $st->execute([$suUid]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="shrinkurl-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['short', 'url', 'note', 'created', 'expires', 'max_clicks', 'clicks', 'password', 'active'], ';', '"', '');
        foreach ($st as $l) {
            // Zellen, die mit = + - @ beginnen, würde eine Tabellenkalkulation als Formel ausführen
            $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
            fputcsv($out, [suBaseUrl() . $l['code'], $safe($l['url']), $safe($l['note']), date('Y-m-d H:i', (int)$l['created']),
                $l['expires'] === null ? '' : date('Y-m-d H:i', (int)$l['expires']), (string)($l['max_clicks'] ?? ''), (string)$l['clicks'],
                $l['pass'] ? '1' : '0', $l['active'] ? '1' : '0'], ';', '"', '');
        }
        exit;
    }

    /* ---------- Dateien und Notizen ---------- */

    case 'file_init': {
        // Kündigt einen Upload an. Der Inhalt folgt stückweise mit file_chunk, schon im Browser verschlüsselt.
        $kind = ($_POST['kind'] ?? '') === 'note' ? 'note' : 'file';
        $size = (int)($_POST['size'] ?? -1);
        $chunkSize = (int)($_POST['chunk_size'] ?? 0);
        $meta = (string)($_POST['meta'] ?? '');
        if ($size < 0 || $chunkSize < 1024 || $chunkSize > suChunkSize() || !preg_match('/^[A-Za-z0-9_\-]{20,6000}$/', $meta)) {
            throw new Exception(t('Der Upload ist nicht gültig. Bitte die Seite neu laden und noch einmal versuchen.'));
        }
        if ($kind === 'note' && $size > SU_NOTE_MAX_BYTES) throw new Exception(t('Die Notiz ist zu lang (höchstens %s Zeichen).', number_format(SU_NOTE_MAX_BYTES, 0, ',', '.')));
        if ($size > suFilesMaxBytes()) throw new Exception(t('Die Datei ist zu groß. Erlaubt sind höchstens %s MB.', (int)(suFilesMaxBytes() / 1048576)));
        $chunks = max(1, (int)ceil($size / $chunkSize));
        $encSize = $size + $chunks * 16;
        if (suFilesUsed() + $encSize > suFilesTotalBytes()) throw new Exception(t('Der Platz für Dateien ist voll. Bitte erst ältere löschen oder warten, bis sie ablaufen.'));
        $ttl = max(300, min(suFilesMaxDays() * 86400, (int)($_POST['ttl'] ?? 0)));
        $max = max(1, min(1000, (int)($_POST['max_downloads'] ?? 1)));
        $pass = null;
        $p = suPostPass('pass');
        if ($p !== '') {
            if (strlen($p) < 4) throw new Exception(t('Das Passwort braucht mindestens 4 Zeichen.'));
            $pass = password_hash($p, PASSWORD_DEFAULT);
        }
        // Kennung: Bei einem Abholcode aus Wörtern berechnet sie der Browser aus dem Code, sonst vergibt sie der Server.
        // Weitere Dateien derselben Sendung hängen sich mit grp an die erste an.
        $grp = (string)($_POST['grp'] ?? '');
        $idx = max(0, min(999, (int)($_POST['idx'] ?? 0)));
        if ($grp !== '') {
            $first = suFileGroup($grp);
            if (!$first || (int)$first[0]['user_id'] !== $suUid || $kind === 'note' || $idx < 1) throw new Exception(t('Der Upload ist nicht gültig. Bitte die Seite neu laden und noch einmal versuchen.'));
            foreach ($first as $row) if ((int)$row['idx'] === $idx) throw new Exception(t('Der Upload ist durcheinandergeraten. Bitte noch einmal von vorn.'));
            $id = suNewFileId();
        } else {
            $id = (string)($_POST['id'] ?? '');
            if ($id === '') $id = suNewFileId();
            elseif (!suFileIdOk($id) || suFileRow($id) !== null || suFileGroup($id)) jsonOut(['success' => false, 'taken' => true, 'message' => t('Dieser Abholcode ist schon vergeben. Bitte noch einmal versuchen.')]);
            $grp = $id;
            $idx = 0;
        }
        $db->prepare('INSERT INTO files (id, grp, idx, user_id, kind, label, meta, size, plain_size, chunk_size, chunks, created, expires, max_downloads, pass) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$id, $grp, $idx, $suUid, $kind, suPostStr('label', 200), $meta, $encSize, $size, $chunkSize, $chunks, time(), time() + $ttl, $max, $pass]);
        if (@file_put_contents(suFilePath($id), '') === false) {
            suFileDelete($id);
            throw new Exception(t('Auf dem Server lässt sich die Datei nicht anlegen. Bitte die Schreibrechte von data/ prüfen.'));
        }
        jsonOut(['success' => true, 'id' => $id, 'grp' => $grp, 'chunks' => $chunks]);
    }

    case 'file_chunk': {
        // Ein Stück anhängen. Der Inhalt kommt roh im Rumpf der Anfrage, die Stücke streng der Reihe nach.
        $f = suFileRow((string)($_GET['id'] ?? ''));
        if (!$f || (int)$f['user_id'] !== $suUid || $f['complete']) throw new Exception(t('Diesen Upload gibt es nicht (mehr).'));
        $n = (int)($_GET['n'] ?? -1);
        if ($n < (int)$f['received']) jsonOut(['success' => true, 'received' => (int)$f['received']]); // kam schon an, die Antwort ging nur verloren
        if ($n !== (int)$f['received']) throw new Exception(t('Der Upload ist durcheinandergeraten. Bitte noch einmal von vorn.'));
        $cs = (int)$f['chunk_size'] + 16;
        $last = $n === (int)$f['chunks'] - 1;
        $expect = $last ? (int)$f['size'] - $cs * ((int)$f['chunks'] - 1) : $cs;
        $path = suFilePath($f['id']);
        $fp = @fopen($path, 'c+b');
        if (!$fp || !flock($fp, LOCK_EX)) throw new Exception(t('Auf dem Server lässt sich die Datei nicht anlegen. Bitte die Schreibrechte von data/ prüfen.'));
        // Immer genau an die Stelle dieses Stücks schreiben, ein halb angekommener Versuch wird so überschrieben
        ftruncate($fp, $n * $cs);
        fseek($fp, $n * $cs);
        $in = fopen('php://input', 'rb');
        $got = (int)stream_copy_to_stream($in, $fp, $expect + 1);
        fclose($in);
        if ($got !== $expect) {
            ftruncate($fp, $n * $cs);
            flock($fp, LOCK_UN);
            fclose($fp);
            throw new Exception(t('Ein Stück der Datei kam unvollständig an. Bitte noch einmal versuchen.'));
        }
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $db->prepare('UPDATE files SET received = ?, complete = ? WHERE id = ?')->execute([$n + 1, $last ? 1 : 0, $f['id']]);
        jsonOut(['success' => true, 'received' => $n + 1, 'complete' => $last]);
    }

    case 'file_list': {
        $all = $suAdmin && !empty($_POST['all']);
        $st = $db->prepare('SELECT f.*, u.name AS owner FROM files f JOIN users u ON u.id = f.user_id WHERE ' . ($all ? '1' : 'f.user_id = ?') . ' ORDER BY f.created DESC, f.idx LIMIT 2000');
        $st->execute($all ? [] : [$suUid]);
        $out = [];
        foreach ($st as $f) {
            // Abgelaufenes gleich wegräumen, damit die Liste stimmt
            if ((int)$f['expires'] <= time() || ((int)$f['downloads'] >= (int)$f['max_downloads'] && !suFileTokens($f))) { suFileDelete($f['id']); continue; }
            $out[] = suFileOut($f);
        }
        jsonOut(['success' => true, 'files' => $out, 'used' => suFilesUsed(), 'total' => suFilesTotalBytes()]);
    }

    case 'file_delete': {
        // Löscht eine ganze Sendung, also alle Dateien unter einem Link
        foreach (suFileGroup((string)($_POST['grp'] ?? '')) as $f) {
            if ($suAdmin || (int)$f['user_id'] === $suUid) suFileDelete($f['id']);
        }
        jsonOut(['success' => true]);
    }

    /* ---------- Eigenes Konto ---------- */

    case 'prefs': {
        $p = suUserPrefs(['prefs' => json_encode([
            'lang' => $_POST['lang'] ?? '', 'theme' => $_POST['theme'] ?? '', 'accent' => $_POST['accent'] ?? '', 'strip' => !empty($_POST['strip']),
        ])]);
        $db->prepare('UPDATE users SET prefs = ? WHERE id = ?')->execute([json_encode($p), $suUid]);
        jsonOut(['success' => true, 'prefs' => $p]);
    }

    case 'password': {
        $suNeedPassword();
        $new = suPostPass('new');
        if (($e = suCheckPassword($new)) !== '') throw new Exception($e);
        if (hash_equals(suPostPass('password'), $new)) throw new Exception(t('Das neue Passwort muss sich vom alten unterscheiden.'));
        // epoch + 1: alle anderen Geräte werden abgemeldet, dieses hier bleibt drin
        $db->prepare('UPDATE users SET pass = ?, must_change = 0, epoch = epoch + 1 WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $suUid]);
        $_SESSION['epoch'] = (int)$suUser['epoch'] + 1;
        jsonOut(['success' => true, 'message' => t('Das Passwort ist geändert. Andere Geräte wurden abgemeldet.')]);
    }

    case 'totp_begin': {
        $suNeedPassword();
        $secret = suTotpNewSecret();
        $_SESSION['totp_pending'] = $secret;
        jsonOut(['success' => true, 'secret' => $secret, 'uri' => suTotpUri($secret, $suUser['name'])]);
    }

    case 'totp_enable': {
        $secret = (string)($_SESSION['totp_pending'] ?? '');
        if ($secret === '') throw new Exception(t('Bitte die Einrichtung noch einmal von vorn beginnen.'));
        $counter = suTotpVerify($secret, suPostStr('code', 12), 0);
        if ($counter === 0) throw new Exception(t('Der 6-stellige Code stimmt nicht oder ist abgelaufen.'));
        $db->prepare('UPDATE users SET totp = ?, totp_last = ? WHERE id = ?')->execute([$secret, $counter, $suUid]);
        unset($_SESSION['totp_pending']);
        jsonOut(['success' => true, 'message' => t('Die Zwei-Faktor-Anmeldung ist jetzt an.')]);
    }

    case 'totp_disable': {
        $suNeedPassword();
        $db->prepare('UPDATE users SET totp = NULL, totp_last = 0 WHERE id = ?')->execute([$suUid]);
        jsonOut(['success' => true, 'message' => t('Die Zwei-Faktor-Anmeldung ist aus.')]);
    }

    case 'recovery_new': {
        $suNeedPassword();
        jsonOut(['success' => true, 'code' => suNewRecoveryCode($suUid)]);
    }

    /* ---------- Verwaltung (nur Administrator) ---------- */

    case 'users': {
        $suNeedAdmin();
        $rows = $db->query('SELECT u.id, u.name, u.role, u.active, u.created, u.last_login, u.totp IS NOT NULL AS totp, u.must_change,
                                   (SELECT COUNT(*) FROM links l WHERE l.user_id = u.id) AS links,
                                   (SELECT COALESCE(SUM(clicks), 0) FROM links l WHERE l.user_id = u.id) AS clicks
                            FROM users u ORDER BY u.id')->fetchAll();
        foreach ($rows as &$r) {
            foreach (['id', 'created', 'links', 'clicks'] as $k) $r[$k] = (int)$r[$k];
            foreach (['active', 'totp', 'must_change'] as $k) $r[$k] = (bool)$r[$k];
            $r['last_login'] = $r['last_login'] === null ? null : (int)$r['last_login'];
        }
        jsonOut(['success' => true, 'users' => $rows]);
    }

    case 'user_save': {
        $suNeedAdmin();
        $id = (int)($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
        $pass = suPostPass('pass');
        if ($id === 0) {
            $res = suCreateUser(suPostStr('name', 40), $pass, $role, true);
            if (!is_int($res)) throw new Exception($res);
            jsonOut(['success' => true, 'message' => t('Das Konto ist angelegt. Beim ersten Anmelden muss ein eigenes Passwort gesetzt werden.')]);
        }
        $u = suUserById($id);
        if (!$u) throw new Exception(t('Dieses Konto gibt es nicht (mehr).'));
        $active = empty($_POST['active']) ? 0 : 1;
        if ($id === $suUid && (!$active || $role !== 'admin')) throw new Exception(t('Du kannst dich nicht selbst sperren oder herabstufen.'));
        $db->prepare('UPDATE users SET role = ?, active = ? WHERE id = ?')->execute([$role, $active, $id]);
        if (!$active) $db->prepare('UPDATE users SET epoch = epoch + 1 WHERE id = ?')->execute([$id]);
        if ($pass !== '') {
            if ($id === $suUid) throw new Exception(t('Dein eigenes Passwort änderst du unter „Konto“.'));
            if (($e = suCheckPassword($pass)) !== '') throw new Exception($e);
            // Neues Passwort vom Administrator: 2FA und Wiederherstellungscode weg, alle Sitzungen beendet
            $db->prepare('UPDATE users SET pass = ?, must_change = 1, totp = NULL, totp_last = 0, recovery = NULL, epoch = epoch + 1 WHERE id = ?')
               ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        }
        jsonOut(['success' => true, 'message' => t('Gespeichert.')]);
    }

    case 'user_delete': {
        $suNeedAdmin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $suUid) throw new Exception(t('Dein eigenes Konto kannst du nicht löschen.'));
        if (!suUserById($id)) throw new Exception(t('Dieses Konto gibt es nicht (mehr).'));
        // Erst die Dateien des Kontos von der Platte nehmen, die Links verschwinden mit dem Konto (ON DELETE CASCADE)
        $st = $db->prepare('SELECT id FROM files WHERE user_id = ?');
        $st->execute([$id]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $fid) suFileDelete($fid);
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        jsonOut(['success' => true, 'message' => t('Das Konto, seine Links und seine Dateien sind gelöscht.')]);
    }

    case 'settings': {
        $suNeedAdmin();
        $name = suPostStr('site_name', 40);
        $urls = [];
        foreach (['imprint_url', 'privacy_url'] as $k) {
            $v = suPostStr($k, 500);
            if ($v !== '' && (!preg_match('~^https?://[^\s<>"\']+$~i', $v) || !filter_var($v, FILTER_VALIDATE_URL))) {
                throw new Exception(t('Impressum und Datenschutz brauchen eine vollständige Adresse mit https://.'));
            }
            $urls[$k] = $v;
        }
        suSetSetting('site_name', $name);
        suSetSetting('imprint_url', $urls['imprint_url']);
        suSetSetting('privacy_url', $urls['privacy_url']);
        suSetSetting('keep_expired_days', (string)max(0, min(3650, (int)($_POST['keep_expired_days'] ?? SU_KEEP_EXPIRED_DAYS))));
        suSetSetting('files_max_mb', (string)max(1, min(102400, (int)($_POST['files_max_mb'] ?? SU_FILES_MAX_MB))));
        suSetSetting('files_total_gb', (string)max(0, min(10000, (int)($_POST['files_total_gb'] ?? SU_FILES_TOTAL_GB))));
        suSetSetting('files_max_days', (string)max(1, min(365, (int)($_POST['files_max_days'] ?? SU_FILES_MAX_DAYS))));
        jsonOut(['success' => true, 'message' => t('Gespeichert.')]);
    }

    default:
        jsonFail(t('Unbekannte Aktion.'), 400);
    }
} catch (PDOException $e) {
    jsonFail(t('Die Datenbank ist gerade beschäftigt. Bitte noch einmal versuchen.'));
} catch (Exception $e) {
    jsonFail($e->getMessage());
} catch (Throwable $e) {
    jsonFail(t('Das hat nicht geklappt. Bitte noch einmal versuchen.'), 500);
}
