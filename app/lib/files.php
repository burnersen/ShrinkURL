<?php
/* ShrinkURL – Dateien und Notizen zum Abholen: Ablage, Grenzen, Löschen */
defined('SU_VERSION') || exit;

/*
 * Der Inhalt wird im Browser des Absenders verschlüsselt (AES-256-GCM) und stückweise hochgeladen.
 * Auf dem Server liegt nur Unlesbares: data/files/<Kennung>.bin. Der Schlüssel steht im Link hinter
 * dem # und wird vom Browser nie an den Server geschickt. Auch Dateiname und Typ sind verschlüsselt.
 */

/** Einstellungen der Verwaltung, mit den Vorgaben aus der index.php */
function suFilesMaxBytes(): int {
    return max(1, min(102400, (int)suSetting('files_max_mb', (string)SU_FILES_MAX_MB))) * 1048576;
}
function suFilesTotalBytes(): int {
    return max(0, min(10000, (int)suSetting('files_total_gb', (string)SU_FILES_TOTAL_GB))) * 1073741824;
}
function suFilesMaxDays(): int {
    return max(1, min(365, (int)suSetting('files_max_days', (string)SU_FILES_MAX_DAYS)));
}

/** Größe einer PHP-Angabe wie „64M“ in Bytes (0 = unbegrenzt) */
function suIniBytes(string $v): int {
    $v = trim($v);
    if ($v === '' || (int)$v <= 0) return 0;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

/**
 * So groß darf ein hochgeladenes Stück sein (unverschlüsselt). Richtet sich danach, was PHP auf diesem
 * Server pro Anfrage annimmt, damit auch große Dateien durch enge Grenzen des Hosters passen.
 */
function suChunkSize(): int {
    $limit = suIniBytes((string)ini_get('post_max_size'));
    $size = 8 * 1048576;
    if ($limit > 0) $size = min($size, $limit - 65536);
    return max(65536, $size - $size % 65536);
}

function suFilesDir(): string {
    suConfig();
    if (!is_dir(SU_FILES_DIR)) @mkdir(SU_FILES_DIR, 0700, true);
    return SU_FILES_DIR;
}

function suFileIdOk(string $id): bool {
    return (bool)preg_match('/^[a-z0-9]{16}$/', $id);
}

function suFilePath(string $id): string {
    return suFilesDir() . '/' . $id . '.bin';
}

function suNewFileId(): string {
    $abc = 'abcdefghijkmnpqrstuvwxyz23456789';
    do {
        $id = '';
        for ($i = 0; $i < 16; $i++) $id .= $abc[random_int(0, 31)];
    } while (suFileRow($id) !== null);
    return $id;
}

function suFileRow(string $id): ?array {
    if (!suFileIdOk($id)) return null;
    $st = suDb()->prepare('SELECT f.*, u.name AS owner FROM files f JOIN users u ON u.id = f.user_id WHERE f.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Alle Dateien einer Sendung (ein Link kann mehrere Dateien enthalten), in der Reihenfolge des Hochladens */
function suFileGroup(string $grp): array {
    if (!suFileIdOk($grp)) return [];
    $st = suDb()->prepare('SELECT f.*, u.name AS owner FROM files f JOIN users u ON u.id = f.user_id WHERE f.grp = ? ORDER BY f.idx, f.created');
    $st->execute([$grp]);
    return $st->fetchAll();
}

/** Laufende Downloads: Kennzeichen => Ablaufzeit. Abgelaufene fallen heraus. */
function suFileTokens(array $f): array {
    $t = json_decode((string)$f['tokens'], true);
    if (!is_array($t)) return [];
    $now = time();
    return array_filter($t, fn($exp) => (int)$exp > $now);
}

/** active, pending (Upload nicht fertig), expired oder used (alle Abrufe verbraucht) */
function suFileState(array $f): string {
    if (!$f['complete']) return 'pending';
    if ((int)$f['expires'] <= time()) return 'expired';
    if ((int)$f['downloads'] >= (int)$f['max_downloads']) return 'used';
    return 'active';
}

function suFileDelete(string $id): void {
    if (!suFileIdOk($id)) return;
    // Erst die Datei, dann der Eintrag: klappt das Löschen der Datei nicht, bleibt der Eintrag
    // stehen und das nächste Aufräumen versucht es wieder, statt die Datei zu vergessen.
    $path = suFilePath($id);
    if (is_file($path)) {
        @unlink($path);
        clearstatcache(true, $path);
        if (is_file($path)) {
            // Notlösung, falls der Server das Löschen verweigert: Inhalt leeren, damit nichts lesbar liegen bleibt
            @file_put_contents($path, '');
            suDb()->prepare('UPDATE files SET expires = 0, complete = 0 WHERE id = ?')->execute([$id]);
            return;
        }
    }
    suDb()->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
}

/**
 * Aufräumen ohne Cronjob: höchstens alle 10 Minuten, angestoßen von ganz normalen Aufrufen
 * (Verwaltung öffnen, Abholseite, ab und zu ein Kurzlink).
 */
function suFilesCleanupSoon(): void {
    try {
        if ((int)suSetting('last_files_cleanup', '0') > time() - 600) return;
        suSetSetting('last_files_cleanup', (string)time());
        suFilesCleanup();
    } catch (Throwable $e) {}
}

/** Belegter Platz aller Dateien zusammen (auch unfertiger Uploads, mit ihrer angekündigten Größe) */
function suFilesUsed(): int {
    return (int)suDb()->query('SELECT COALESCE(SUM(size), 0) FROM files')->fetchColumn();
}

/** So geht ein Eintrag an die Oberfläche */
function suFileOut(array $f): array {
    return [
        'id' => $f['id'], 'kind' => $f['kind'], 'label' => $f['label'], 'size' => (int)$f['plain_size'],
        'created' => (int)$f['created'], 'expires' => (int)$f['expires'],
        'max_downloads' => (int)$f['max_downloads'], 'downloads' => (int)$f['downloads'],
        'has_pass' => $f['pass'] !== null && $f['pass'] !== '', 'state' => suFileState($f),
        'owner' => $f['owner'] ?? null, 'user_id' => (int)$f['user_id'], 'grp' => $f['grp'], 'idx' => (int)$f['idx'],
    ];
}

/**
 * Löscht, was weg muss: Abgelaufenes, Verbrauchtes ohne laufenden Download, liegen gebliebene Uploads
 * (älter als ein Tag) und Dateien in data/files/, zu denen es keinen Eintrag mehr gibt. Liefert die Anzahl.
 */
function suFilesCleanup(): int {
    $db = suDb();
    $now = time();
    $n = 0;
    foreach ($db->query('SELECT * FROM files') as $f) {
        $gone = (int)$f['expires'] <= $now
             || (!$f['complete'] && (int)$f['created'] < $now - 86400)
             || ((int)$f['downloads'] >= (int)$f['max_downloads'] && !suFileTokens($f));
        if ($gone) { suFileDelete($f['id']); $n++; }
    }
    if (is_dir(SU_FILES_DIR)) {
        $known = array_flip($db->query('SELECT id FROM files')->fetchAll(PDO::FETCH_COLUMN));
        foreach (glob(SU_FILES_DIR . '/*.bin') ?: [] as $path) {
            $id = basename($path, '.bin');
            if (!isset($known[$id]) && (int)@filemtime($path) < $now - 3600) @unlink($path);
        }
    }
    return $n;
}
