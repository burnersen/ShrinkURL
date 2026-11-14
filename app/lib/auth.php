<?php
/* ShrinkURL – Sitzung, Konten, Anmeldung, Wiederherstellung */
defined('SU_VERSION') || exit;

function suStartSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    suConfig(); // legt data/ an, falls es den Ordner noch nicht gibt
    // Eigener Session-Ordner: nicht im gemeinsamen /tmp des Hosters, und die Laufzeit bestimmen wir selbst
    if (ini_get('session.save_handler') === 'files') {
        if (!is_dir(SU_SESSION_DIR)) @mkdir(SU_SESSION_DIR, 0700, true);
        if (is_dir(SU_SESSION_DIR) && is_writable(SU_SESSION_DIR)) {
            @ini_set('session.save_path', SU_SESSION_DIR);
            @ini_set('session.gc_probability', '1');
            @ini_set('session.gc_divisor', '50');
        }
    }
    @ini_set('session.gc_maxlifetime', (string)(SU_SESSION_DAYS * 86400));
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_trans_sid', '0');
    session_name(SU_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SU_SESSION_DAYS * 86400, 'path' => suBasePath() . '/', 'secure' => suIsHttps(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    @session_start();
}

/* ---------- Konten ---------- */

function suUserCount(): int {
    return (int)suDb()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function suUserById(int $id): ?array {
    $st = suDb()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function suUserByName(string $name): ?array {
    $st = suDb()->prepare('SELECT * FROM users WHERE name = ?');
    $st->execute([$name]);
    return $st->fetch() ?: null;
}

/** Fehlertext oder '' */
function suCheckName(string $name): string {
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-]{1,31}$/', $name)) {
        return t('Der Benutzername braucht 2 bis 32 Zeichen: Buchstaben, Ziffern, Punkt, Binde- oder Unterstrich.');
    }
    return '';
}

function suCheckPassword(string $pass): string {
    if (strlen($pass) < SU_MIN_PASSWORD) return t('Das Passwort braucht mindestens %s Zeichen.', SU_MIN_PASSWORD);
    return '';
}

/** Legt ein Konto an. Liefert die neue ID oder einen Fehlertext. */
function suCreateUser(string $name, string $pass, string $role = 'user', bool $mustChange = false) {
    if (($e = suCheckName($name)) !== '') return $e;
    if (($e = suCheckPassword($pass)) !== '') return $e;
    if (suUserByName($name)) return t('Diesen Benutzernamen gibt es schon.');
    if (suUserCount() >= SU_MAX_USERS) return t('Die Höchstzahl an Konten ist erreicht.');
    $st = suDb()->prepare('INSERT INTO users (name, pass, role, must_change, created) VALUES (?, ?, ?, ?, ?)');
    $st->execute([$name, password_hash($pass, PASSWORD_DEFAULT), $role === 'admin' ? 'admin' : 'user', $mustChange ? 1 : 0, time()]);
    return (int)suDb()->lastInsertId();
}

function suUserPrefs(?array $user): array {
    $p = $user ? json_decode((string)$user['prefs'], true) : null;
    if (!is_array($p)) $p = [];
    return [
        'lang' => in_array($p['lang'] ?? '', ['de', 'en'], true) ? $p['lang'] : '',
        'theme' => in_array($p['theme'] ?? '', ['dark', 'light', 'auto'], true) ? $p['theme'] : 'auto',
        'accent' => in_array($p['accent'] ?? '', ['violet', 'blue', 'green'], true) ? $p['accent'] : 'violet',
        'strip' => !isset($p['strip']) || !empty($p['strip']),
    ];
}

/* ---------- Wer ist angemeldet? ---------- */

function suCurrentUser(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (empty($_COOKIE[SU_SESSION_NAME])) return null; // ohne Cookie gar nicht erst eine Sitzung anlegen
    suStartSession();
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) return null;
    $u = suUserById($uid);
    // Passwort geändert oder Konto gesperrt: alle alten Sitzungen sind sofort ungültig
    if (!$u || !$u['active'] || (int)$u['epoch'] !== (int)($_SESSION['epoch'] ?? 0)) {
        $_SESSION = [];
        return null;
    }
    $user = $u;
    $prefs = suUserPrefs($u);
    if ($prefs['lang'] !== '') $GLOBALS['SU_LANG'] = $prefs['lang'];
    return $user;
}

function suFinishLogin(array $user): void {
    suStartSession();
    @session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$user['id'], 'epoch' => (int)$user['epoch'], 'csrf' => bin2hex(random_bytes(32))];
    suDb()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([time(), $user['id']]);
}

function suLogout(): void {
    suStartSession();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) @session_destroy();
    setcookie(SU_SESSION_NAME, '', ['expires' => 1, 'path' => suBasePath() . '/', 'secure' => suIsHttps(), 'httponly' => true, 'samesite' => 'Lax']);
}

/** Anmeldung. Liefert '' bei Erfolg, sonst den Fehlertext. */
function suAttemptLogin(string $name, string $pass, string $code): string {
    $wait = suThrottleWait('login');
    if ($wait > 0) return suThrottleMessage($wait);
    $user = $name !== '' ? suUserByName($name) : null;
    // Auch bei unbekanntem Namen ein Passwort prüfen, damit die Antwortzeit nichts verrät
    $hash = $user ? $user['pass'] : password_hash('kein-konto', PASSWORD_DEFAULT);
    $ok = password_verify($pass, $hash) && $user && $user['active'];
    if ($ok && !empty($user['totp'])) {
        $counter = suTotpVerify($user['totp'], $code, (int)$user['totp_last']);
        if ($counter === 0) {
            suThrottleFail('login');
            return trim($code) === '' ? t('Für dieses Konto ist die Zwei-Faktor-Anmeldung an. Bitte auch den 6-stelligen Code eingeben.')
                                      : t('Der 6-stellige Code stimmt nicht oder ist abgelaufen.');
        }
        suDb()->prepare('UPDATE users SET totp_last = ? WHERE id = ?')->execute([$counter, $user['id']]);
    }
    if (!$ok) {
        suThrottleFail('login');
        return t('Benutzername oder Passwort stimmt nicht.');
    }
    suThrottleClear('login');
    if (password_needs_rehash($user['pass'], PASSWORD_DEFAULT)) {
        suDb()->prepare('UPDATE users SET pass = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
    }
    suFinishLogin($user);
    return '';
}

/* ---------- Wiederherstellungscode ---------- */

function suNormalizeRecovery(string $code): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
}

/** Neuer Code für ein Konto. Gespeichert wird nur die Prüfsumme, der Code selbst wird genau einmal gezeigt. */
function suNewRecoveryCode(int $userId): string {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $raw = '';
    for ($i = 0; $i < 24; $i++) $raw .= $abc[random_int(0, 31)];
    suDb()->prepare('UPDATE users SET recovery = ? WHERE id = ?')->execute([password_hash($raw, PASSWORD_DEFAULT), $userId]);
    return implode('-', str_split($raw, 4));
}

/** Passwort vergessen oder Handy weg: mit dem Wiederherstellungscode ein neues Passwort setzen. '' = geklappt. */
function suRecover(string $name, string $code, string $newPass): string {
    $wait = suThrottleWait('login');
    if ($wait > 0) return suThrottleMessage($wait);
    if (($e = suCheckPassword($newPass)) !== '') return $e;
    $user = $name !== '' ? suUserByName($name) : null;
    $code = suNormalizeRecovery($code);
    if (!$user || !$user['active'] || empty($user['recovery']) || strlen($code) < 16 || !password_verify($code, $user['recovery'])) {
        suThrottleFail('login');
        return t('Benutzername oder Wiederherstellungscode stimmt nicht.');
    }
    // Der Code ist verbraucht, 2FA wird abgeschaltet (Handy weg ist der häufigste Grund), alte Sitzungen fliegen raus
    suDb()->prepare('UPDATE users SET pass = ?, recovery = NULL, totp = NULL, totp_last = 0, must_change = 0, epoch = epoch + 1 WHERE id = ?')
          ->execute([password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
    suThrottleClear('login');
    suFinishLogin(suUserById((int)$user['id']));
    return '';
}
