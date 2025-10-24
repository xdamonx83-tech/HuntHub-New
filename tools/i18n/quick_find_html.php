<?php
declare(strict_types=1);

/**
 * Listet harte Texte in HTML/PHP-Templates:
 *  - sichtbare Textknoten zwischen >...<
 *  - Attribute: title, placeholder, alt, aria-label, value
 * Skipped: uploads, vendor, node_modules, assets, .git, .idea, cache, logs, lang
 */

$ROOT  = getcwd();
$ATTRS = ['title','placeholder','alt','aria-label','value'];

$rxExt = '~\.(php|phtml|html|htm|tpl)$~i';
$rxSkip= '~^(uploads|public/uploads|vendor|node_modules|assets|\.git|\.idea|cache|logs|lang)/~i';

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$hits = 0;

foreach ($it as $spl) {
  if ($spl->isDir()) continue;
  $path = $spl->getPathname();
  $rel  = ltrim(str_replace($ROOT.'/', '', $path), '/');
  if (!preg_match($rxExt, $rel)) continue;
  if (preg_match($rxSkip, $rel)) continue;

  $s = @file_get_contents($path);
  if ($s === false || $s === '') continue;

  // -------- Textknoten: > ... <
  if (preg_match_all('~>([^<]+)<~u', $s, $m, PREG_OFFSET_CAPTURE)) {
    foreach ($m[1] as [$raw, $pos]) {
      $txt = trim(html_entity_decode($raw, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
      if ($txt === '' || !preg_match('~[\p{L}\d]~u', $txt)) continue;           // nur Satzzeichen/Leerz. -> skip
      if (str_contains($txt, '<?') || str_contains($txt, '?>')) continue;       // PHP im Text -> skip
      if (preg_match('~t\s*\([\'"]~', $txt) || preg_match('~\$L\[[\'"]~', $txt)) continue; // schon i18n

      $line = 1 + substr_count(substr($s, 0, $pos), "\n");
      $snippet = preg_replace('~\s+~u',' ', mb_strimwidth($txt, 0, 120, '…'));
      printf("%s:%d: TEXT  %s\n", $rel, $line, $snippet);
      $hits++;
    }
  }

  // -------- Attribute
  foreach ($ATTRS as $a) {
    $rx = '~\b'.preg_quote($a,'~').'\s*=\s*("([^"]+)"|\'([^\']+)\')~u';
    if (preg_match_all($rx, $s, $ma, PREG_OFFSET_CAPTURE)) {
      foreach ($ma[0] as $i => $full) {
        $val = $ma[2][$i][0] ?? $ma[3][$i][0] ?? '';
        $pos = $full[1];
        $txt = trim(html_entity_decode($val, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
        if ($txt === '' || !preg_match('~[\p{L}\d]~u', $txt)) continue;
        if (preg_match('~t\s*\([\'"]~', $txt) || preg_match('~\$L\[[\'"]~', $txt)) continue;

        $line = 1 + substr_count(substr($s, 0, $pos), "\n");
        $snippet = preg_replace('~\s+~u',' ', mb_strimwidth($txt, 0, 120, '…'));
        printf("%s:%d: ATTR %s=%s\n", $rel, $line, $a, $snippet);
        $hits++;
      }
    }
  }
}

if ($hits === 0) {
  echo "Keine offensichtlichen Hardcodes in Textknoten/Attributen gefunden.\n";
} else {
  echo "----\nTreffer: $hits\n";
}
