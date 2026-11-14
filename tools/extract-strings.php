<?php
/**
 * ShrinkURL – Listet alle deutschen Texte aus dem Programm auf und prüft, ob jeder eine englische Fassung hat.
 * Nur für die Kommandozeile:  php tools/extract-strings.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('SU_VERSION', 'tools');
$root = dirname(__DIR__);
$found = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php' || $f->getFilename() === 'lang.php' || $f->getFilename() === 'font.php') continue;
    $src = (string)file_get_contents($f->getPathname());
    // t('…') in PHP und T('…') in JavaScript, jeweils mit einfachen Anführungszeichen
    if (preg_match_all("~(?<![A-Za-z0-9_>\$])[tT]\\('((?:[^'\\\\]|\\\\.)*)'~u", $src, $m)) {
        foreach ($m[1] as $s) $found[stripslashes($s)] = true;
    }
}
$dict = is_file($root . '/app/lib/lang.php') ? require $root . '/app/lib/lang.php' : [];
$missing = array_diff(array_keys($found), array_keys($dict));
$unused = array_diff(array_keys($dict), array_keys($found));
if (in_array('--all', $argv, true)) { foreach (array_keys($found) as $s) echo $s, "\n"; exit; }
foreach ($missing as $s) echo "FEHLT:     $s\n";
foreach ($unused as $s) echo "UNBENUTZT: $s\n";
printf("%d Texte, %d ohne englische Fassung, %d unbenutzt.\n", count($found), count($missing), count($unused));
exit($missing ? 1 : 0);
