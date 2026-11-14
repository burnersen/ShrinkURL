<?php
/* ShrinkURL – Öffentliche Abrufe: Schrift, Icon, Manifest, Aufräumen per Web-Cron */
defined('SU_VERSION') || exit;

if ($action === 'font') {
    suSecurityHeaders(false);
    header('Content-Type: font/woff2');
    header('Cache-Control: public, max-age=31536000, immutable');
    echo base64_decode(suFontData());
    exit;
}

if ($action === 'icon') {
    suSecurityHeaders(false);
    header('Cache-Control: public, max-age=604800');
    $s = (int)($_GET['s'] ?? 0);
    if ($s > 0) {
        // App-Icon als PNG (fertig eingebettet, braucht keine Grafik-Erweiterung)
        require SU_APP . '/lib/icons.php';
        header('Content-Type: image/png');
        echo base64_decode(suIconPng($s >= 512 ? 512 : 192));
        exit;
    }
    require_once SU_APP . '/lib/page.php';
    header('Content-Type: image/svg+xml');
    echo suLogoSvg(48);
    exit;
}

if ($action === 'manifest') {
    suSecurityHeaders(false);
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $en = suBrowserEnglish();
    $icon = fn(int $s, string $purpose) => ['src' => './?action=icon&s=' . $s, 'sizes' => "{$s}x{$s}", 'type' => 'image/png', 'purpose' => $purpose];
    echo json_encode([
        'name' => suSiteName(),
        'short_name' => suSiteName(),
        'description' => $en ? 'Shorten links on your own server' : 'Links kürzen auf dem eigenen Server',
        'start_url' => './',
        'scope' => './',
        'display' => 'standalone',
        'background_color' => '#0b1120',
        'theme_color' => '#0b1120',
        'icons' => [$icon(192, 'any'), $icon(512, 'any'), $icon(192, 'maskable'), $icon(512, 'maskable')],
        // „Teilen“ am Handy: die geteilte Adresse landet direkt im Eingabefeld
        'share_target' => ['action' => './', 'method' => 'GET', 'enctype' => 'application/x-www-form-urlencoded', 'params' => ['title' => 'title', 'text' => 'text', 'url' => 'url']],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Für Cronjobs, die nur eine Adresse aufrufen können. Harmlos: räumt höchstens alle 10 Minuten auf und verrät nichts.
if ($action === 'cron') {
    suSecurityHeaders(false);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    if ((int)suSetting('last_cleanup', '0') < time() - 600) {
        try { suCleanup(); } catch (Throwable $e) {}
    }
    exit("ok\n");
}
