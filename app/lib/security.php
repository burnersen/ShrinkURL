<?php
/* ShrinkURL – CSRF-Schutz und Security-Header */
defined('SU_VERSION') || exit;

require_once SU_APP . '/lib/throttle.php';

function suCsrfToken(): string {
    suStartSession();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 32) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function suCsrfCheck(): bool {
    suStartSession();
    $session = $_SESSION['csrf'] ?? '';
    if (!is_string($session) || $session === '') return false;
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && $token !== '' && hash_equals($session, $token);
}

/** Security-Header. Skripte laufen nur mit dem Einmal-Kennzeichen (Nonce) dieser Seite. */
function suSecurityHeaders(bool $html = false): void {
    global $SU_NONCE;
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (suIsHttps()) header('Strict-Transport-Security: max-age=31536000');
    if ($html) {
        header("Content-Security-Policy: default-src 'none'; "
            . "script-src 'nonce-{$SU_NONCE}'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "font-src 'self'; "
            . "img-src 'self' data: blob:; "
            . "connect-src 'self'; "
            . "manifest-src 'self'; "
            . "worker-src 'self'; "
            . "frame-ancestors 'none'; "
            . "base-uri 'none'; "
            . "form-action 'self'");
    } else {
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'");
    }
}
