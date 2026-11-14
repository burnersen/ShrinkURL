<?php
/**
 * ShrinkURL – Links kürzen auf dem eigenen Webspace
 *
 * Diese Datei ist nur der Einstieg: Einstellungen und Reihenfolge. Der eigentliche Code liegt in app/:
 *   app/lib/     Funktionen (Datenbank, Weiterleitung, Konten, Anmeldung, Links, Oberfläche)
 *   app/routes/  Antworten ohne Seite (Schrift, Icon, Manifest, Aufräumen, alle JSON-Aktionen)
 *   app/views/   Anmeldeseite und Hauptseite (HTML, CSS, JavaScript)
 *
 * Reines PHP mit SQLite (eine Datei in data/), keine Abhängigkeiten, nichts wird von fremden
 * Servern nachgeladen. Kurzlinks funktionieren für jeden ohne Anmeldung, anlegen und verwalten
 * kann sie nur, wer ein Konto hat.
 *
 * Datenschutz: Beim Aufruf eines Kurzlinks wird nur ein Zähler pro Tag erhöht. Keine IP-Adresse,
 * kein Cookie, kein Browser-Kennzeichen, keine Herkunftsseite.
 *
 * Der Code enthält KEINE Zugangsdaten, Schlüssel oder persönlichen Angaben.
 * Alles Geheime entsteht erst beim ersten Aufruf und liegt dann im Ordner data/.
 * Den Ordner data/ niemals veröffentlichen oder ins Git legen.
 *
 * Lizenz: GNU Affero General Public License v3.0, siehe Datei LICENSE.
 * Schrift: Inter (SIL Open Font License 1.1, https://github.com/rsms/inter), eingebettet.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Europe/Berlin');

define('SU_VERSION', '1.0.0');
define('SU_ROOT', __DIR__);          // hier liegt die index.php, daneben data/
define('SU_APP', SU_ROOT . '/app');  // Programmteile
define('SU_DATA_DIR', SU_ROOT . '/data');
define('SU_SESSION_DIR', SU_DATA_DIR . '/sessions');
define('SU_THROTTLE_DIR', SU_DATA_DIR . '/throttle'); // Fehlversuche bei Anmeldung und Link-Passwörtern
define('SU_SESSION_NAME', 'SUSESSID');
define('SU_SESSION_DAYS', 30);     // so lange bleibt man an einem Gerät angemeldet
define('SU_CODE_LENGTH', 5);       // Länge der zufälligen Kurzcodes
define('SU_MAX_URL', 4000);        // längste Zieladresse
define('SU_MAX_USERS', 100);
define('SU_MIN_PASSWORD', 10);
define('SU_STATS_DAYS', 400);      // so lange bleiben die Tageszähler erhalten
define('SU_KEEP_EXPIRED_DAYS', 30); // Vorgabe: abgelaufene Links nach so vielen Tagen löschen (in der Verwaltung änderbar)

$SU_NONCE = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');

if (!is_file(SU_APP . '/lib/db.php')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("ShrinkURL ist unvollständig: Der Ordner app/ fehlt. Bitte alle Dateien der Version hochladen, nicht nur die index.php.\n"
       . "ShrinkURL is incomplete: the folder app/ is missing. Please upload all files of the release, not just index.php.\n");
}

require SU_APP . '/lib/helpers.php';
require SU_APP . '/lib/db.php';

/* ---------- Aufruf von der Kommandozeile: nur Aufräumen (für den Cronjob) ---------- */

if (PHP_SAPI === 'cli') {
    require SU_APP . '/lib/links.php';
    $n = suCleanup();
    echo "ShrinkURL: aufgeräumt, {$n} Link(s) gelöscht.\n";
    exit(0);
}

/* ---------- Kurzlink? Dann so wenig wie möglich laden und sofort weiterleiten ---------- */

$suPath = suRequestPath();
if ($suPath !== '' && $suPath !== 'index.php') {
    require SU_APP . '/lib/redirect.php';
    suHandleShort($suPath);
    exit;
}

/* ---------- Alles andere: Verwaltung ---------- */

foreach (['security', 'auth', 'totp', 'links', 'font'] as $suPart) {
    require SU_APP . '/lib/' . $suPart . '.php';
}
unset($suPart);

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!is_string($action) || $action === '') $action = null;
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// Schrift, Icon, Manifest, Aufräumen per Web-Cron (antworten und beenden die Anfrage)
require SU_APP . '/routes/public.php';
// Alle JSON-Aktionen (antworten und beenden die Anfrage)
require SU_APP . '/routes/api.php';

suSecurityHeaders(true);
header('Cache-Control: no-store, no-cache, must-revalidate, private');

// Nicht angemeldet: Einrichtung, Anmeldung oder Wiederherstellung (beendet die Anfrage)
require SU_APP . '/views/login.php';
// Angemeldet: die eigentliche Seite
require SU_APP . '/views/app.php';
