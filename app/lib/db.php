<?php
/* ShrinkURL – Datenordner, Datenbank (SQLite), Einstellungen */
defined('SU_VERSION') || exit;

define('SU_SCHEMA', 2);

/**
 * Legt data/ an und sperrt den Ordner nach außen. In data/config.php stehen der zufällige Name
 * der Datenbank-Datei und ein geheimer Wert. Eine .php-Datei kann man von außen nicht herunterladen,
 * und ohne den Namen findet auch niemand die Datenbank, falls die .htaccess einmal nicht greift.
 */
function suConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = SU_DATA_DIR . '/config.php';
    if (is_file($file)) {
        $cfg = require $file;
        if (is_array($cfg) && !empty($cfg['db']) && !empty($cfg['secret'])) return $cfg;
    }
    if (!is_dir(SU_DATA_DIR) && !@mkdir(SU_DATA_DIR, 0700, true) && !is_dir(SU_DATA_DIR)) {
        suFatal('Der Ordner data/ konnte nicht angelegt werden. PHP braucht Schreibrechte im Verzeichnis von ShrinkURL.',
                'The folder data/ could not be created. PHP needs write permission in the ShrinkURL directory.');
    }
    if (!is_file(SU_DATA_DIR . '/.htaccess')) {
        @file_put_contents(SU_DATA_DIR . '/.htaccess', "# ShrinkURL: hier liegt alles Geheime, nichts davon darf von außen abrufbar sein.\n"
            . "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\nOptions -Indexes\n");
    }
    if (!is_file(SU_DATA_DIR . '/index.html')) @file_put_contents(SU_DATA_DIR . '/index.html', '');
    $cfg = ['db' => 'su_' . bin2hex(random_bytes(12)) . '.sqlite', 'secret' => bin2hex(random_bytes(32))];
    $php = "<?php\n// ShrinkURL: automatisch angelegt. Nicht löschen, nicht weitergeben.\nreturn " . var_export($cfg, true) . ";\n";
    if (@file_put_contents($file, $php, LOCK_EX) === false) {
        suFatal('In den Ordner data/ kann nicht geschrieben werden. Bitte die Schreibrechte prüfen.',
                'Cannot write to the folder data/. Please check the permissions.');
    }
    @chmod($file, 0600);
    suEnsureRootHtaccess();
    return $cfg;
}

/** Damit kurz.example.org/abc bei der index.php ankommt. Eine vorhandene .htaccess wird nie angefasst. */
function suEnsureRootHtaccess(): void {
    $file = SU_ROOT . '/.htaccess';
    if (is_file($file)) return;
    @file_put_contents($file, "# ShrinkURL: alle Kurzlinks laufen über die index.php\n"
        . "Options -Indexes\n"
        . "<IfModule mod_rewrite.c>\n"
        . "RewriteEngine On\n"
        . "RewriteRule ^data(/|$) - [F,L]\n"
        . "RewriteCond %{REQUEST_FILENAME} !-f\n"
        . "RewriteCond %{REQUEST_FILENAME} !-d\n"
        . "RewriteRule ^ index.php [L]\n"
        . "</IfModule>\n");
}

function suFatal(string $de, string $en): void {
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $de . "\n"); exit(1); }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit($de . "\n" . $en . "\n");
}

function suDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        suFatal('Die PHP-Erweiterung „pdo_sqlite“ fehlt. Ohne sie läuft ShrinkURL nicht.',
                'The PHP extension "pdo_sqlite" is missing. ShrinkURL cannot run without it.');
    }
    $cfg = suConfig();
    try {
        $pdo = new PDO('sqlite:' . SU_DATA_DIR . '/' . $cfg['db'], null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $ver = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        if ($ver < SU_SCHEMA) suMigrate($pdo, $ver);
    } catch (Throwable $e) {
        suFatal('Die Datenbank in data/ lässt sich nicht öffnen.', 'The database in data/ cannot be opened.');
    }
    return $pdo;
}

function suMigrate(PDO $pdo, int $from): void {
    if ($from < 1) {
        // WAL: Leser und Schreiber bremsen sich nicht gegenseitig. Klappt das auf dem Server nicht, bleibt es beim Standard.
        try { $pdo->query('PRAGMA journal_mode = WAL')->fetchAll(); } catch (Throwable $e) {}
        $pdo->exec('BEGIN IMMEDIATE');
        // Zwei Anfragen gleichzeitig beim allerersten Start: die zweite findet alles schon vor
        if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() >= 1) { $pdo->exec('COMMIT'); $from = 1; }
        else {
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL UNIQUE COLLATE NOCASE,
            pass TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            active INTEGER NOT NULL DEFAULT 1,
            must_change INTEGER NOT NULL DEFAULT 0,
            totp TEXT,
            totp_last INTEGER NOT NULL DEFAULT 0,
            recovery TEXT,
            prefs TEXT NOT NULL DEFAULT '{}',
            epoch INTEGER NOT NULL DEFAULT 1,
            created INTEGER NOT NULL,
            last_login INTEGER
        )");
        $pdo->exec("CREATE TABLE links (
            id INTEGER PRIMARY KEY,
            code TEXT NOT NULL UNIQUE COLLATE NOCASE,
            url TEXT NOT NULL,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            note TEXT NOT NULL DEFAULT '',
            created INTEGER NOT NULL,
            expires INTEGER,
            max_clicks INTEGER,
            clicks INTEGER NOT NULL DEFAULT 0,
            pass TEXT,
            active INTEGER NOT NULL DEFAULT 1,
            last_day TEXT
        )");
        $pdo->exec('CREATE INDEX links_user ON links (user_id, created)');
        $pdo->exec('CREATE INDEX links_expires ON links (expires) WHERE expires IS NOT NULL');
        // Statistik: nur ein Zähler pro Link und Tag, sonst nichts
        $pdo->exec('CREATE TABLE stats (
            link_id INTEGER NOT NULL REFERENCES links(id) ON DELETE CASCADE,
            day TEXT NOT NULL,
            clicks INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (link_id, day)
        ) WITHOUT ROWID');
        $pdo->exec('CREATE TABLE settings (k TEXT PRIMARY KEY, v TEXT NOT NULL) WITHOUT ROWID');
        $pdo->exec('PRAGMA user_version = 1');
        $pdo->exec('COMMIT');
        }
    }
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() < 2) {
        $pdo->exec('BEGIN IMMEDIATE');
        if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() >= 2) { $pdo->exec('COMMIT'); return; }
        // Dateien und Notizen: Der Inhalt liegt verschlüsselt in data/files/, den Schlüssel kennt der Server nie.
        // meta = Name, Typ und Größe, ebenfalls verschlüsselt. size = Größe des verschlüsselten Inhalts in Bytes.
        $pdo->exec("CREATE TABLE files (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            kind TEXT NOT NULL DEFAULT 'file',
            label TEXT NOT NULL DEFAULT '',
            meta TEXT NOT NULL,
            size INTEGER NOT NULL,
            plain_size INTEGER NOT NULL,
            chunk_size INTEGER NOT NULL,
            chunks INTEGER NOT NULL,
            received INTEGER NOT NULL DEFAULT 0,
            complete INTEGER NOT NULL DEFAULT 0,
            created INTEGER NOT NULL,
            expires INTEGER NOT NULL,
            max_downloads INTEGER NOT NULL DEFAULT 1,
            downloads INTEGER NOT NULL DEFAULT 0,
            pass TEXT,
            tokens TEXT NOT NULL DEFAULT '{}'
        ) WITHOUT ROWID");
        $pdo->exec('CREATE INDEX files_user ON files (user_id, created)');
        $pdo->exec('PRAGMA user_version = 2');
        $pdo->exec('COMMIT');
    }
}

/* ---------- Einstellungen der Installation ---------- */

function suSetting(string $key, string $default = ''): string {
    static $all = null;
    if ($all === null || $key === "\0reset") {
        $all = [];
        foreach (suDb()->query('SELECT k, v FROM settings') as $r) $all[$r['k']] = $r['v'];
    }
    return $all[$key] ?? $default;
}

function suSetSetting(string $key, string $value): void {
    $st = suDb()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v');
    $st->execute([$key, $value]);
    suSetting("\0reset");
}

function suSiteName(): string {
    $n = suSetting('site_name');
    return $n !== '' ? $n : 'ShrinkURL';
}
