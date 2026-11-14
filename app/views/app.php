<?php
/* ShrinkURL – Die Hauptseite für angemeldete Benutzer */
defined('SU_VERSION') || exit;

$suUser = suCurrentUser();
if ($suUser === null) exit;
require_once SU_APP . '/lib/page.php';
suLazyCleanup();
suFilesCleanupSoon();

$suPrefs = suUserPrefs($suUser);
$suIsAdmin = $suUser['role'] === 'admin';
$suBoot = [
    'version' => SU_VERSION,
    'csrf' => suCsrfToken(),
    'lang' => suLang(),
    'dict' => suLang() === 'en' ? suDict() : new stdClass(),
    'base' => suBaseUrl(),
    'site' => suSiteName(),
    'minPass' => SU_MIN_PASSWORD,
    'user' => [
        'id' => (int)$suUser['id'], 'name' => $suUser['name'], 'admin' => $suIsAdmin,
        'totp' => !empty($suUser['totp']), 'recovery' => !empty($suUser['recovery']), 'mustChange' => !empty($suUser['must_change']),
    ],
    'prefs' => $suPrefs,
    'settings' => $suIsAdmin ? [
        'site_name' => suSetting('site_name'), 'imprint_url' => suSetting('imprint_url'), 'privacy_url' => suSetting('privacy_url'),
        'keep_expired_days' => suKeepExpiredDays(),
        'files_max_mb' => (int)(suFilesMaxBytes() / 1048576), 'files_total_gb' => (int)(suFilesTotalBytes() / 1073741824), 'files_max_days' => suFilesMaxDays(),
    ] : null,
    'files' => ['chunk' => suChunkSize(), 'maxBytes' => suFilesMaxBytes(), 'maxDays' => suFilesMaxDays(), 'noteMax' => SU_NOTE_MAX_BYTES, 'on' => suFilesTotalBytes() > 0],
    'https' => suIsHttps(),
];
$suFooter = suFooterLinks();
?><!DOCTYPE html>
<html lang="<?= suLang() ?>" data-theme="<?= h($suPrefs['theme']) ?>" data-accent="<?= h($suPrefs['accent']) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="#0b1120">
<title><?= h(suSiteName()) ?></title>
<link rel="icon" type="image/svg+xml" href="?action=icon">
<link rel="apple-touch-icon" href="?action=icon&amp;s=192">
<link rel="manifest" href="?action=manifest">
<?php require SU_APP . '/views/app.css.php'; ?>
</head>
<body>
<div class="wrap">
    <header class="top">
        <a class="logo" href="./"><?= suLogoSvg(38) ?><b><?= h(suSiteName()) ?></b></a>
        <span class="who"><?= h($suUser['name']) ?></span>
        <button class="ibtn" id="btnHelp" type="button" title="<?= h(t('Hilfe & Schnell-Kürzen')) ?>" aria-label="<?= h(t('Hilfe & Schnell-Kürzen')) ?>"></button>
<?php if ($suIsAdmin): ?>
        <button class="ibtn" id="btnAdmin" type="button" title="<?= h(t('Verwaltung')) ?>" aria-label="<?= h(t('Verwaltung')) ?>"></button>
<?php endif; ?>
        <button class="ibtn" id="btnAccount" type="button" title="<?= h(t('Konto & Aussehen')) ?>" aria-label="<?= h(t('Konto & Aussehen')) ?>"></button>
        <form method="post" action="./" id="logoutForm">
            <input type="hidden" name="form" value="logout"><input type="hidden" name="csrf" value="<?= h($suBoot['csrf']) ?>">
            <button class="ibtn" id="btnLogout" type="submit" title="<?= h(t('Abmelden')) ?>" aria-label="<?= h(t('Abmelden')) ?>"></button>
        </form>
    </header>

    <section class="hero glass">
        <div class="seg modes" id="modes" role="tablist"></div>
        <div id="modeLink">
        <h1><?= h(t('Link kürzen')) ?></h1>
        <p class="sub"><?= h(t('Lange Adresse rein, kurze Adresse raus. Die kurze funktioniert für jeden, ganz ohne Anmeldung.')) ?></p>
        <form id="newForm" autocomplete="off">
            <div class="shorten">
                <input class="in" id="newUrl" type="text" inputmode="url" placeholder="https://…" aria-label="<?= h(t('Lange Adresse')) ?>" required maxlength="<?= SU_MAX_URL ?>">
                <button class="btn btn-primary" type="submit" id="newGo"><?= h(t('Kürzen')) ?></button>
            </div>
            <div class="optbar">
                <button class="link" type="button" id="optToggle" aria-expanded="false" aria-controls="newOpts"><span><?= h(t('Optionen')) ?></span></button>
            </div>
            <div class="opts" id="newOpts" hidden></div>
        </form>
        </div>
        <div id="modeFile" hidden></div>
        <div id="modeNote" hidden></div>
        <div id="result"></div>
    </section>

    <div class="seg views" id="views" role="tablist"></div>

    <div class="bar" id="bar">
        <div class="search"><input class="in" id="q" type="search" placeholder="<?= h(t('Suchen in Kurzname, Ziel und Notiz…')) ?>" aria-label="<?= h(t('Suchen')) ?>"></div>
        <select class="in" id="filter" aria-label="<?= h(t('Filter')) ?>">
            <option value=""><?= h(t('Alle Links')) ?></option>
            <option value="active"><?= h(t('Nur gültige')) ?></option>
            <option value="ended"><?= h(t('Abgelaufen oder aus')) ?></option>
            <option value="limited"><?= h(t('Mit Ablauf, Limit oder Passwort')) ?></option>
        </select>
        <select class="in" id="sort" aria-label="<?= h(t('Sortierung')) ?>">
            <option value="new"><?= h(t('Neueste zuerst')) ?></option>
            <option value="old"><?= h(t('Älteste zuerst')) ?></option>
            <option value="clicks"><?= h(t('Meiste Aufrufe')) ?></option>
            <option value="code"><?= h(t('Kurzname A bis Z')) ?></option>
        </select>
<?php if ($suIsAdmin): ?>
        <button class="ibtn" id="btnAll" type="button" title="<?= h(t('Links aller Benutzer zeigen')) ?>" aria-label="<?= h(t('Links aller Benutzer zeigen')) ?>" aria-pressed="false"></button>
<?php endif; ?>
    </div>
    <p class="sum" id="sum"></p>
    <div class="cards" id="cards"></div>
    <div class="more"><button class="btn btn-second" id="more" type="button" hidden><?= h(t('Mehr laden')) ?></button></div>

    <footer class="foot">
        ShrinkURL <?= h(SU_VERSION) ?> · <?= h(t('Keine IP-Adressen, keine Cookies für Besucher, kein Tracking.')) ?>
        <?php if ($suFooter !== ''): ?><br><?= $suFooter ?><?php endif; ?>
    </footer>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script type="application/json" id="boot"><?= json_encode($suBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script nonce="<?= h($SU_NONCE) ?>">
<?php require SU_APP . '/views/crypto.js.php'; ?>
</script>
<?php require SU_APP . '/views/app.js.php'; ?>
</body>
</html>
