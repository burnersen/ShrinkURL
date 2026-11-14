<?php
/* ShrinkURL – Kurzlinks: nachschlagen, zählen, weiterleiten. Läuft ohne Anmeldung und ohne Sitzung. */
defined('SU_VERSION') || exit;

/**
 * $path ist der Teil der Adresse hinter dem Installationsordner, z. B. „urlaub“.
 * Mit einem + am Ende („urlaub+“) wird nur gezeigt, wohin der Link führt.
 */
function suHandleShort(string $path): void {
    if ($path === 'robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        exit("User-agent: *\nDisallow: /\n");
    }
    if ($path === 'favicon.ico') {
        header('Location: ' . suBasePath() . '/?action=icon', true, 302);
        header('Cache-Control: public, max-age=604800');
        exit;
    }

    $preview = false;
    $code = $path;
    if (substr($code, -1) === '+') {
        $preview = true;
        $code = substr($code, 0, -1);
    }
    if (!preg_match('/^[A-Za-z0-9_\-]{1,60}$/', $code)) suShortNotFound();

    $st = suDb()->prepare('SELECT id, code, url, expires, max_clicks, clicks, pass, active FROM links WHERE code = ?');
    $st->execute([$code]);
    $link = $st->fetch();
    if (!$link) suShortNotFound();

    $now = time();
    $limited = $link['max_clicks'] !== null;
    if (!$link['active']
        || ($link['expires'] !== null && (int)$link['expires'] <= $now)
        || ($limited && (int)$link['clicks'] >= (int)$link['max_clicks'])) {
        suShortGone();
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $hasPass = $link['pass'] !== null && $link['pass'] !== '';

    if ($preview) suShortPreview($link, $hasPass);

    // Normalfall: direkt weiter. Gezählt werden nur echte Abrufe, keine HEAD-Anfragen.
    if (!$hasPass && !$limited) {
        if ($method === 'GET') {
            try { suCountClick((int)$link['id']); } catch (Throwable $e) { /* Zähler klemmt: trotzdem weiterleiten */ }
        }
        suRedirectTo($link['url'], 302);
    }

    // Passwort oder begrenzte Aufrufe: erst eine Zwischenseite. Sonst würde schon die Link-Vorschau
    // eines Messengers einen Einmal-Link verbrauchen, bevor ihn ein Mensch gesehen hat.
    $error = '';
    if ($method === 'POST') {
        $ok = true;
        if ($hasPass) {
            require_once SU_APP . '/lib/throttle.php';
            $bucket = 'link' . $link['id'];
            $wait = suThrottleWait($bucket);
            $given = $_POST['p'] ?? '';
            if ($wait > 0) {
                $ok = false;
                $error = suThrottleMessage($wait);
            } elseif (!is_string($given) || $given === '' || strlen($given) > 1000 || !password_verify($given, $link['pass'])) {
                $ok = false;
                suThrottleFail($bucket);
                $error = t('Das Passwort stimmt nicht.');
            } else {
                suThrottleClear($bucket);
            }
        }
        if ($ok) {
            if (!suCountClick((int)$link['id'])) suShortGone();
            suRedirectTo($link['url'], 303);
        }
    }
    suShortGate($link, $hasPass, $limited, $error);
}

/**
 * Zählt einen Aufruf: Gesamtzähler am Link und Zähler des heutigen Tages. Mehr wird nicht gespeichert.
 * Liefert false, wenn die erlaubte Zahl an Aufrufen schon erreicht ist.
 */
function suCountClick(int $id): bool {
    $db = suDb();
    $day = date('Y-m-d');
    $st = $db->prepare('UPDATE links SET clicks = clicks + 1, last_day = ? WHERE id = ? AND (max_clicks IS NULL OR clicks < max_clicks)');
    $st->execute([$day, $id]);
    if ($st->rowCount() < 1) return false;
    try {
        $db->prepare('INSERT INTO stats (link_id, day, clicks) VALUES (?, ?, 1) ON CONFLICT(link_id, day) DO UPDATE SET clicks = clicks + 1')
           ->execute([$id, $day]);
    } catch (Throwable $e) { /* der Tageszähler ist nicht wichtig genug, um den Aufruf scheitern zu lassen */ }
    return true;
}

function suRedirectTo(string $url, int $status): void {
    header('Location: ' . $url, true, $status);
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    // Die Zielseite erfährt nicht, über welchen Kurzlink der Besucher kam
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    exit;
}

function suShortNotFound(): void {
    require_once SU_APP . '/lib/page.php';
    suPageOut(t('Nicht gefunden'),
        '<div class="big">404</div><h1>' . h(t('Diesen Kurzlink gibt es nicht')) . '</h1>'
      . '<p>' . h(t('Vielleicht hat sich jemand vertippt, oder der Link wurde gelöscht.')) . '</p>', 404);
}

function suShortGone(): void {
    require_once SU_APP . '/lib/page.php';
    suPageOut(t('Nicht mehr gültig'),
        '<div class="big">410</div><h1>' . h(t('Dieser Kurzlink ist nicht mehr gültig')) . '</h1>'
      . '<p>' . h(t('Er ist abgelaufen, wurde abgeschaltet oder durfte nur begrenzt oft aufgerufen werden.')) . '</p>', 410);
}

/** Zeigt die Zieladresse so, dass man die Domain auf einen Blick erkennt */
function suTargetHtml(string $url): string {
    $host = (string)parse_url($url, PHP_URL_HOST);
    $pos = $host !== '' ? strpos($url, $host) : false;
    if ($pos === false) return h($url);
    return h(substr($url, 0, $pos)) . '<b>' . h($host) . '</b>' . h(substr($url, $pos + strlen($host)));
}

function suShortPreview(array $link, bool $hasPass): void {
    require_once SU_APP . '/lib/page.php';
    $short = suBaseUrl() . $link['code'];
    $html = '<h1>' . h(t('Wohin führt dieser Link?')) . '</h1>'
          . '<p>' . h(t('Vorschau für %s', $short)) . '</p>';
    if ($hasPass) {
        $html .= '<div class="box warn">' . h(t('Dieser Link ist mit einem Passwort geschützt. Das Ziel wird erst nach der Eingabe verraten.')) . '</div>';
    } else {
        $html .= '<span class="target">' . suTargetHtml($link['url']) . '</span>';
    }
    $html .= '<a class="btn" rel="noreferrer" href="' . h(suBasePath() . '/' . $link['code']) . '">' . h(t('Weiter zum Ziel')) . '</a>';
    suPageOut(t('Vorschau'), $html);
}

function suShortGate(array $link, bool $hasPass, bool $limited, string $error): void {
    require_once SU_APP . '/lib/page.php';
    $html = '<h1>' . h($hasPass ? t('Dieser Link ist geschützt') : t('Du wirst weitergeleitet')) . '</h1>';
    if ($error !== '') $html .= '<div class="box bad">' . h($error) . '</div>';
    $html .= '<form method="post" action="' . h(suBasePath() . '/' . $link['code']) . '" autocomplete="off">';
    if ($hasPass) {
        $html .= '<p>' . h(t('Bitte gib das Passwort ein, das du zu diesem Link bekommen hast.')) . '</p>'
               . '<label>' . h(t('Passwort')) . '<input type="password" name="p" required autofocus autocomplete="off"></label>';
    } else {
        $html .= '<p>' . h(t('Dieser Link führt zu:')) . '</p><span class="target">' . suTargetHtml($link['url']) . '</span>';
    }
    if ($limited) {
        $left = (int)$link['max_clicks'] - (int)$link['clicks'];
        $html .= '<div class="box warn">' . h($left === 1
            ? t('Dieser Link funktioniert nur noch ein einziges Mal. Danach ist er verbraucht.')
            : t('Dieser Link funktioniert nur noch %s Mal.', $left)) . '</div>';
    }
    $html .= '<button type="submit">' . h(t('Weiter zum Ziel')) . '</button></form>';
    suPageOut($hasPass ? t('Geschützter Link') : t('Weiterleitung'), $html, $error !== '' ? 403 : 200);
}
