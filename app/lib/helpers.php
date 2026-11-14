<?php
/* ShrinkURL – Helfer: Adressen, Sprache, Ausgabe */
defined('SU_VERSION') || exit;

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function suIsHttps(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Ordner, in dem ShrinkURL liegt, als Web-Pfad: '' bei einer eigenen (Sub-)Domain, sonst z. B. '/kurz' */
function suBasePath(): string {
    static $base = null;
    if ($base !== null) return $base;
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
    $dir = '/' . trim($dir, '/.');
    $base = $dir === '/' ? '' : (preg_match('~^[A-Za-z0-9._\-/]+$~', $dir) ? $dir : '');
    return $base;
}

function suHost(): string {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    return preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/', $host) ? $host : 'localhost';
}

/** Vollständige Adresse dieser Installation mit Schrägstrich am Ende, z. B. https://kurz.example.org/ */
function suBaseUrl(): string {
    return (suIsHttps() ? 'https://' : 'http://') . suHost() . suBasePath() . '/';
}

/** Teil der Adresse hinter dem Installationsordner, ohne Abfrage: '' = Startseite, sonst z. B. 'urlaub' oder 'urlaub+' */
function suRequestPath(): string {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $q = strpos($uri, '?');
    if ($q !== false) $uri = substr($uri, 0, $q);
    $path = rawurldecode($uri);
    $base = suBasePath();
    if ($base !== '' && strncmp($path, $base, strlen($base)) === 0) $path = substr($path, strlen($base));
    return trim($path, '/');
}

/* ---------- Sprache: Deutsch ist die Grundlage, Englisch steht in lang.php ---------- */

$SU_LANG = null;

function suBrowserEnglish(): bool {
    $al = strtolower(trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')));
    return $al !== '' && strncmp($al, 'de', 2) !== 0;
}

function suLang(): string {
    global $SU_LANG;
    if ($SU_LANG === null) $SU_LANG = suBrowserEnglish() ? 'en' : 'de';
    return $SU_LANG;
}

function suDict(): array {
    static $dict = null;
    if ($dict === null) $dict = require SU_APP . '/lib/lang.php';
    return $dict;
}

/** Übersetzt einen deutschen Text. %s-Platzhalter werden mit den weiteren Angaben gefüllt. */
function t(string $de, ...$args): string {
    $s = $de;
    if (suLang() === 'en') {
        $dict = suDict();
        if (isset($dict[$de])) $s = $dict[$de];
    }
    return $args ? vsprintf($s, $args) : $s;
}

/* ---------- Ein- und Ausgabe ---------- */

function jsonOut(array $data): void {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function jsonFail(string $message, int $status = 200): void {
    if ($status !== 200) http_response_code($status);
    jsonOut(['success' => false, 'message' => $message]);
}

function cleanText($s, int $max): string {
    if (!is_string($s) && !is_numeric($s)) return '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$s) ?? '';
    $s = trim($s);
    if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
    return substr($s, 0, $max);
}

function suPostStr(string $key, int $max = 500): string {
    return cleanText($_POST[$key] ?? '', $max);
}

/** Passwörter werden nicht gekürzt oder bereinigt, nur auf Text und eine vernünftige Länge geprüft. */
function suPostPass(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) && strlen($v) <= 1000 ? $v : '';
}
