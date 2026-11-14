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
        'id' => './',
        'start_url' => './',
        'scope' => './',
        'lang' => $en ? 'en' : 'de',
        'categories' => ['productivity', 'utilities'],
        'display' => 'standalone',
        'background_color' => '#0b1120',
        'theme_color' => '#0b1120',
        'icons' => [$icon(192, 'any'), $icon(512, 'any'), $icon(192, 'maskable'), $icon(512, 'maskable')],
        // „Teilen“ am Handy: die geteilte Adresse landet direkt im Eingabefeld
        'share_target' => ['action' => './', 'method' => 'GET', 'enctype' => 'application/x-www-form-urlencoded', 'params' => ['title' => 'title', 'text' => 'text', 'url' => 'url']],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Kleiner Helfer im Hintergrund der App. Android installiert ShrinkURL erst mit ihm als „richtige“ App,
// und nur dann taucht es im Teilen-Menü auf. Er speichert bewusst NICHTS zwischen und zeigt ohne Netz
// lediglich eine freundliche Seite statt des Browser-Fehlers.
if ($action === 'sw') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; connect-src 'self'");
    $en = suBrowserEnglish();
    $offline = '<!DOCTYPE html><html lang="' . ($en ? 'en' : 'de') . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="theme-color" content="#0b1120"><title>' . h(suSiteName()) . '</title><style>'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;font-family:system-ui,-apple-system,sans-serif;'
        . 'color:#f1f5f9;background:linear-gradient(165deg,#0b1120 0%,#0f172a 45%,#1e1b4b 100%);text-align:center}'
        . 'h1{font-size:1.15rem;margin:0 0 .4rem}p{color:#94a3b8;font-size:.9rem;line-height:1.5;margin:0 0 1.25rem}'
        . 'a{display:inline-block;padding:.75rem 1.5rem;border-radius:.7rem;background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;font-weight:600;text-decoration:none}'
        . '</style></head><body><div><h1>' . ($en ? 'No connection' : 'Keine Verbindung') . '</h1><p>'
        . ($en ? 'The server cannot be reached right now.' : 'Der Server ist gerade nicht erreichbar.') . '</p><a href="./">' . ($en ? 'Try again' : 'Erneut versuchen') . '</a></div></body></html>';
    echo 'const OFFLINE = ' . json_encode($offline, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n"
        . "self.addEventListener('install', () => self.skipWaiting());\n"
        . "self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));\n"
        . "self.addEventListener('fetch', e => {\n"
        . "    const r = e.request;\n"
        . "    if (r.mode !== 'navigate' || r.method !== 'GET') return;\n"
        . "    e.respondWith(fetch(r).catch(() => new Response(OFFLINE, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } })));\n"
        . "});\n";
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
