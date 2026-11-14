<?php
/* ShrinkURL – Gemeinsames Aussehen: Farben, Logo und die kleinen Seiten (Anmeldung, Hinweise, Link-Abfragen) */
defined('SU_VERSION') || exit;

function suLogoSvg(int $size = 40): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="' . $size . '" height="' . $size . '" aria-hidden="true">'
         . '<defs><linearGradient id="sug" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#a78bfa"/><stop offset="1" stop-color="#6d28d9"/></linearGradient></defs>'
         . '<rect width="48" height="48" rx="12" fill="url(#sug)"/>'
         . '<path d="M11 15l9 9-9 9M37 15l-9 9 9 9" fill="none" stroke="#fff" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/** Farbwerte als CSS-Variablen. Dunkel ist die Grundlage, hell und die Akzentfarben überschreiben sie. */
function suCssTokens(): string {
    $light = '--bg1:#f8fafc;--bg2:#f1f5f9;--bg3:#ede9fe;--card:rgba(255,255,255,.86);--solid:#ffffff;--line:#d7dde8;--line2:#e5e9f0;'
           . '--text:#0f172a;--muted:#5b6678;--field:#ffffff;--hover:rgba(15,23,42,.06);--acc-text:#6d28d9;--shadow:0 10px 30px -12px rgba(15,23,42,.25);'
           . '--ok:#047857;--bad:#b91c1c;--warn:#b45309;--scheme:light;';
    $lightBlue = '--bg3:#dbeafe;--acc-text:#1d4ed8;';
    $lightGreen = '--bg3:#d1fae5;--acc-text:#047857;';
    return ':root{--bg1:#0b1120;--bg2:#0f172a;--bg3:#1e1b4b;--card:rgba(30,41,59,.72);--solid:#131c31;--line:rgba(71,85,105,.6);--line2:rgba(51,65,85,.5);'
         . '--text:#f1f5f9;--muted:#94a3b8;--field:rgba(15,23,42,.85);--hover:rgba(148,163,184,.12);--acc-text:#c4b5fd;--shadow:0 16px 40px -16px rgba(0,0,0,.7);'
         . '--ok:#6ee7b7;--bad:#fca5a5;--warn:#fcd34d;--scheme:dark;'
         . '--acc:#8b5cf6;--acc2:#7c3aed;--acc3:#a78bfa;--acc-soft:rgba(139,92,246,.18);--acc-glow:rgba(139,92,246,.45);}'
         . 'html[data-accent=blue]{--acc:#3b82f6;--acc2:#2563eb;--acc3:#60a5fa;--acc-soft:rgba(59,130,246,.18);--acc-glow:rgba(59,130,246,.45);--bg3:#172554;--acc-text:#93c5fd;}'
         . 'html[data-accent=green]{--acc:#10b981;--acc2:#059669;--acc3:#34d399;--acc-soft:rgba(16,185,129,.18);--acc-glow:rgba(16,185,129,.4);--bg3:#052e2b;--acc-text:#6ee7b7;}'
         . 'html[data-theme=light]{' . $light . '}'
         . 'html[data-theme=light][data-accent=blue]{' . $lightBlue . '}'
         . 'html[data-theme=light][data-accent=green]{' . $lightGreen . '}'
         . '@media (prefers-color-scheme: light){html[data-theme=auto]{' . $light . '}'
         . 'html[data-theme=auto][data-accent=blue]{' . $lightBlue . '}'
         . 'html[data-theme=auto][data-accent=green]{' . $lightGreen . '}}';
}

/** Impressum und Datenschutz, falls der Administrator Adressen dafür hinterlegt hat */
function suFooterLinks(): string {
    $out = [];
    foreach (['imprint_url' => t('Impressum'), 'privacy_url' => t('Datenschutz')] as $key => $label) {
        $url = suSetting($key);
        if ($url !== '') $out[] = '<a href="' . h($url) . '" rel="noopener noreferrer">' . h($label) . '</a>';
    }
    return implode(' · ', $out);
}

/**
 * Gibt eine kleine, eigenständige Seite aus und beendet die Anfrage.
 * $inner ist fertiges HTML (alles Fremde darin muss schon mit h() entschärft sein).
 * $script ist JavaScript für diese Seite (nur die Abholseite für Dateien braucht welches).
 */
function suPageOut(string $title, string $inner, int $status = 200, string $script = ''): void {
    global $SU_NONCE;
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        if (suIsHttps()) header('Strict-Transport-Security: max-age=31536000');
        // Kein form-action: nach dem Absenden eines Link-Passworts geht es auf eine fremde Seite weiter
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; font-src 'self'; img-src 'self' data:; manifest-src 'self'; frame-ancestors 'none'; base-uri 'none'"
            . ($script !== '' ? "; script-src 'nonce-{$SU_NONCE}'; connect-src 'self'" : ''));
    }
    $base = suBasePath() . '/';
    $footer = suFooterLinks();
    ?><!DOCTYPE html>
<html lang="<?= suLang() ?>" data-theme="auto" data-accent="violet">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="dark light">
<title><?= h($title) ?> · <?= h(suSiteName()) ?></title>
<meta name="theme-color" content="#0b1120">
<link rel="icon" type="image/svg+xml" href="<?= h($base) ?>?action=icon">
<link rel="apple-touch-icon" href="<?= h($base) ?>?action=icon&amp;s=192">
<link rel="manifest" href="<?= h($base) ?>?action=manifest">
<style>
@font-face { font-family: 'Inter'; font-style: normal; font-weight: 100 900; font-display: swap; src: url('<?= h($base) ?>?action=font') format('woff2'); }
<?= suCssTokens() ?>

*, *::before, *::after { box-sizing: border-box; }
html { color-scheme: var(--scheme); }
body { margin: 0; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;
       padding: 1.25rem; background: linear-gradient(165deg, var(--bg1) 0%, var(--bg2) 45%, var(--bg3) 100%) fixed;
       color: var(--text); font: 16px/1.55 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
.card { width: 100%; max-width: 27rem; background: var(--card); border: 1px solid var(--line); border-radius: 1.25rem; padding: 1.75rem;
        box-shadow: var(--shadow); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); }
.brand { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; }
.brand svg { border-radius: 12px; box-shadow: 0 6px 18px -6px var(--acc-glow); flex-shrink: 0; }
.brand b { font-size: 1.15rem; letter-spacing: -.01em; }
h1 { margin: 0 0 .4rem; font-size: 1.35rem; letter-spacing: -.02em; line-height: 1.25; }
p { margin: 0 0 1rem; color: var(--muted); font-size: .94rem; }
p.text { color: var(--text); }
label { display: block; margin: 0 0 .9rem; font-size: .82rem; font-weight: 600; color: var(--muted); }
input[type=text], input[type=password] { display: block; width: 100%; margin-top: .35rem; padding: .7rem .85rem; border-radius: .7rem; border: 1px solid var(--line);
        background: var(--field); color: var(--text); font: inherit; font-size: 1rem; outline: none; transition: border-color .15s, box-shadow .15s; }
input:focus { border-color: var(--acc); box-shadow: 0 0 0 3px var(--acc-soft); }
button, .btn { display: block; width: 100%; border: 0; border-radius: .75rem; padding: .8rem 1rem; font: inherit; font-weight: 600; color: #fff; cursor: pointer;
        text-align: center; text-decoration: none; background: linear-gradient(135deg, var(--acc), var(--acc2)); box-shadow: 0 6px 16px -6px var(--acc-glow); transition: filter .15s, transform .1s; }
button:hover, .btn:hover { filter: brightness(1.1); }
button:active, .btn:active { transform: scale(.98); }
.btn.second { background: transparent; color: var(--text); border: 1px solid var(--line); box-shadow: none; margin-top: .6rem; }
.box { border-radius: .7rem; padding: .7rem .85rem; margin-bottom: 1rem; font-size: .9rem; border: 1px solid; }
.bad { background: rgba(239,68,68,.12); border-color: rgba(239,68,68,.4); color: var(--bad); }
.good { background: rgba(16,185,129,.12); border-color: rgba(16,185,129,.4); color: var(--ok); }
.warn { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.4); color: var(--warn); }
.target { display: block; padding: .7rem .85rem; margin-bottom: 1rem; border-radius: .7rem; background: var(--field); border: 1px solid var(--line);
        font-size: .9rem; word-break: break-all; color: var(--text); }
.target b { color: var(--acc-text); }
.links { margin: 1rem 0 0; text-align: center; font-size: .86rem; }
a { color: var(--acc-text); }
footer { font-size: .8rem; color: var(--muted); text-align: center; }
footer a { color: var(--muted); }
.prog { height: .6rem; border-radius: .3rem; background: var(--field); border: 1px solid var(--line); overflow: hidden; margin-bottom: 1rem; }
.prog i { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--acc), var(--acc3)); transition: width .2s; }
textarea { display: block; width: 100%; min-height: 9rem; margin-bottom: 1rem; padding: .7rem .85rem; border-radius: .7rem; border: 1px solid var(--line); background: var(--field);
        color: var(--text); font: .95rem/1.5 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; resize: vertical; outline: none; }
[hidden] { display: none !important; }
.frow { display: flex; align-items: center; gap: .7rem; padding: .65rem .85rem; margin-bottom: .5rem; border-radius: .7rem; background: var(--field); border: 1px solid var(--line); }
#fList { margin-bottom: 1rem; }
.frow.got { border-color: rgba(16,185,129,.45); }
.ftext { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
.fname { font-weight: 600; word-break: break-all; font-size: .95rem; }
.fsize { font-size: .82rem; color: var(--muted); }
.fbtn { width: auto; flex-shrink: 0; padding: .5rem .8rem; font-size: .84rem; }
button:disabled { opacity: .55; cursor: default; }
.big { font-size: 3rem; font-weight: 800; letter-spacing: -.04em; line-height: 1; margin-bottom: .5rem; background: linear-gradient(135deg, var(--acc3), var(--acc2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
</style>
</head>
<body>
<main class="card">
<div class="brand"><?= suLogoSvg(40) ?><b><?= h(suSiteName()) ?></b></div>
<?= $inner ?>

</main>
<?php if ($footer !== ''): ?><footer><?= $footer ?></footer><?php endif; ?>
<?php if ($script !== ''): ?><script nonce="<?= h($SU_NONCE) ?>"><?= $script ?></script>
<?php endif; ?>
</body>
</html>
<?php
    exit;
}
