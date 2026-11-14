<?php
/* ShrinkURL – Einrichtung, Anmeldung, Wiederherstellung, Abmelden. Wer angemeldet ist, läuft hier einfach durch. */
defined('SU_VERSION') || exit;

/** Vom Schnell-Kürzen-Knopf oder „Teilen“ mitgebrachte Adresse über die Anmeldung retten */
$suBack = (function (): string {
    $keep = [];
    foreach (['url', 'title', 'text'] as $k) {
        if (isset($_GET[$k]) && is_string($_GET[$k]) && $_GET[$k] !== '') $keep[$k] = substr($_GET[$k], 0, 2000);
    }
    return suBasePath() . '/' . ($keep ? '?' . http_build_query($keep) : '');
})();

$suForm = $isPost && is_string($_POST['form'] ?? null) ? $_POST['form'] : '';

if ($suForm === 'logout') {
    if (suCsrfCheck()) suLogout();
    header('Location: ' . suBasePath() . '/', true, 303);
    exit;
}

if (suCurrentUser() !== null) return;

require_once SU_APP . '/lib/page.php';

$suError = '';
$suFirst = suUserCount() === 0;
$suMode = $suFirst ? 'setup' : (isset($_GET['recover']) ? 'recover' : 'login');
$suName = suPostStr('name', 40);

if ($suForm !== '') {
    if (!suCsrfCheck()) {
        $suError = t('Die Seite war zu lange offen. Bitte noch einmal absenden.');
    } elseif ($suForm === 'setup' && $suFirst) {
        $pass = suPostPass('pass');
        if (!hash_equals($pass, suPostPass('pass2'))) {
            $suError = t('Die beiden Passwörter stimmen nicht überein.');
        } else {
            $res = suCreateUser($suName, $pass, 'admin');
            if (is_int($res)) {
                suFinishLogin(suUserById($res));
                header('Location: ' . $suBack, true, 303);
                exit;
            }
            $suError = $res;
        }
    } elseif ($suForm === 'login' && !$suFirst) {
        $suError = suAttemptLogin($suName, suPostPass('pass'), suPostStr('code', 12));
        if ($suError === '') {
            header('Location: ' . $suBack, true, 303);
            exit;
        }
    } elseif ($suForm === 'recover' && !$suFirst) {
        $suMode = 'recover';
        $pass = suPostPass('pass');
        if (!hash_equals($pass, suPostPass('pass2'))) {
            $suError = t('Die beiden Passwörter stimmen nicht überein.');
        } else {
            $suError = suRecover($suName, suPostStr('recovery', 60), $pass);
            if ($suError === '') {
                header('Location: ' . $suBack, true, 303);
                exit;
            }
        }
    }
}

$suCsrf = suCsrfToken();
$suAction = h($suBack);
$suHtml = '';
if ($suError !== '') $suHtml .= '<div class="box bad">' . h($suError) . '</div>';
if (!suIsHttps()) {
    $suHtml .= '<div class="box warn">' . h(t('Diese Seite läuft ohne HTTPS. Dein Passwort geht so im Klartext durchs Netz. Bitte HTTPS einschalten.')) . '</div>';
}

if ($suMode === 'setup') {
    $suTitle = t('Einrichtung');
    $suHtml = '<h1>' . h(t('Willkommen bei ShrinkURL')) . '</h1>'
        . '<p>' . h(t('Lege jetzt das erste Konto an. Dieses Konto ist der Administrator und kann später weitere Konten anlegen.')) . '</p>'
        . $suHtml
        . '<form method="post" action="' . $suAction . '">'
        . '<input type="hidden" name="form" value="setup"><input type="hidden" name="csrf" value="' . h($suCsrf) . '">'
        . '<label>' . h(t('Benutzername')) . '<input type="text" name="name" value="' . h($suName) . '" required autofocus autocomplete="username" maxlength="32"></label>'
        . '<label>' . h(t('Passwort (mindestens %s Zeichen)', SU_MIN_PASSWORD)) . '<input type="password" name="pass" required autocomplete="new-password" minlength="' . SU_MIN_PASSWORD . '"></label>'
        . '<label>' . h(t('Passwort wiederholen')) . '<input type="password" name="pass2" required autocomplete="new-password"></label>'
        . '<button type="submit">' . h(t('Konto anlegen')) . '</button></form>';
} elseif ($suMode === 'recover') {
    $suTitle = t('Passwort vergessen');
    $suHtml = '<h1>' . h(t('Passwort vergessen')) . '</h1>'
        . '<p>' . h(t('Mit deinem Wiederherstellungscode setzt du ein neues Passwort. Die Zwei-Faktor-Anmeldung wird dabei abgeschaltet. Ohne Code kann dir ein Administrator ein neues Passwort geben.')) . '</p>'
        . $suHtml
        . '<form method="post" action="' . h(suBasePath() . '/?recover=1') . '">'
        . '<input type="hidden" name="form" value="recover"><input type="hidden" name="csrf" value="' . h($suCsrf) . '">'
        . '<label>' . h(t('Benutzername')) . '<input type="text" name="name" value="' . h($suName) . '" required autofocus autocomplete="username" maxlength="32"></label>'
        . '<label>' . h(t('Wiederherstellungscode')) . '<input type="text" name="recovery" required autocomplete="off" spellcheck="false"></label>'
        . '<label>' . h(t('Neues Passwort (mindestens %s Zeichen)', SU_MIN_PASSWORD)) . '<input type="password" name="pass" required autocomplete="new-password" minlength="' . SU_MIN_PASSWORD . '"></label>'
        . '<label>' . h(t('Passwort wiederholen')) . '<input type="password" name="pass2" required autocomplete="new-password"></label>'
        . '<button type="submit">' . h(t('Neues Passwort setzen')) . '</button></form>'
        . '<p class="links"><a href="' . h(suBasePath() . '/') . '">' . h(t('Zurück zur Anmeldung')) . '</a></p>';
} else {
    $suTitle = t('Anmelden');
    $suHtml = '<h1>' . h(t('Anmelden')) . '</h1>'
        . '<p>' . h(t('Kurzlinks funktionieren ohne Anmeldung. Ein Konto brauchst du nur, um Links anzulegen und zu verwalten.')) . '</p>'
        . $suHtml
        . '<form method="post" action="' . $suAction . '">'
        . '<input type="hidden" name="form" value="login"><input type="hidden" name="csrf" value="' . h($suCsrf) . '">'
        . '<label>' . h(t('Benutzername')) . '<input type="text" name="name" value="' . h($suName) . '" required ' . ($suName === '' ? 'autofocus ' : '') . 'autocomplete="username" maxlength="32"></label>'
        . '<label>' . h(t('Passwort')) . '<input type="password" name="pass" required ' . ($suName !== '' ? 'autofocus ' : '') . 'autocomplete="current-password"></label>'
        . '<label>' . h(t('2FA-Code (nur falls eingeschaltet)')) . '<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456"></label>'
        . '<button type="submit">' . h(t('Anmelden')) . '</button></form>'
        . '<p class="links"><a href="' . h(suBasePath() . '/?recover=1') . '">' . h(t('Passwort vergessen?')) . '</a></p>';
}

suPageOut($suTitle, $suHtml);
