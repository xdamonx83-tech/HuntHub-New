<?php
// tools/i18n/scan_report.php
declare(strict_types=1);

/**
 * Read-only Scanner:
 *  - findet sichtbare Texte zwischen Tags
 *  - findet UI-Attribute (title, placeholder, alt, aria-label, value)
 *  - ignoriert <script>, <style>, <template>, <textarea>
 *  - schreibt i18n_report.json + i18n_report.csv
 *
 * Aufruf: php tools/i18n/scan_report.php
 */

$ROOT = getcwd();
$ATTRS = ['title','placeholder','alt','aria-label','value'];
$IGNORE_TAGS = ['script','style','template','textarea'];
$EXT_RX = '~\.(php|phtml|html|htm|tpl)$~i';
$SKIP   = '~^(uploads|public/uploads|vendor|node_modules|assets|\.git|\.idea|cache|logs|lang)/~i';

function slugify(string $s): string {
  $s = trim($s);
  $s = preg_replace('~\s+~u', ' ', $s);
  $s = preg_replace('~[^\pL\d]+~u', '_', $s);
  $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  $s = preg_replace('~[^-\w]+~', '', $s);
  $s = trim($s, '_');
  $s = preg_replace('~_+~', '_', $s);
  $s = strtolower($s);
  return $s === '' ? 'text' : $s;
}
function maskBlocks(string $html, array $tags): array {
  $map = [];
  foreach ($tags as $tag) {
    $rx = '~<'.preg_quote($tag,'~').'\b[^>]*>.*?</'.preg_quote($tag,'~').'>~is';
    $html = preg_replace_callback($rx, function($m) use (&$map, $tag) {
      $id = '%%I18N_MASK_'.strtoupper($tag).'_'.count($map).'%%';
      $map[$id] = $m[0];
      return $id;
    }, $html);
  }
  return [$html, $map];
}
function restoreMasks(string $html, array $map): string { return $map ? strtr($html,$map) : $html; }
function getLine(string $src, int $pos): int { return 1 + substr_count(substr($src, 0, $pos), "\n"); }

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$suggestions = []; // array of rows
$totalText = 0; $totalAttr = 0;

foreach ($it as $f) {
  if ($f->isDir()) continue;
  $path = $f->getPathname();
  $rel  = ltrim(str_replace($ROOT.'/', '', $path), '/');
  if (!preg_match($EXT_RX, $rel)) continue;
  if (preg_match($SKIP, $rel))    continue;

  $src = @file_get_contents($path);
  if ($src === false || $src === '') continue;

  list($masked, $map) = maskBlocks($src, $IGNORE_TAGS);

  // Textknoten >...<
  if (preg_match_all('~>([^<]+)<~u', $masked, $m, PREG_OFFSET_CAPTURE)) {
    foreach ($m[1] as [$raw,$pos]) {
      $txt = trim(html_entity_decode($raw, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
      if ($txt === '' || !preg_match('~[\p{L}\d]~u',$txt)) continue;
      if (str_contains($txt,'<?') || str_contains($txt,'?>')) continue;

      $key = pathinfo($rel, PATHINFO_FILENAME).'_'.slugify(mb_substr($txt,0,60)).'_'.substr(sha1($rel.'|text|'.$txt),0,8);
      $suggestions[] = [
        'file'=>$rel,'line'=>getLine($masked,$pos),'type'=>'text','attr'=>'',
        'key'=>$key,'text'=>$txt
      ];
      $totalText++;
    }
  }

  // Attribute
  foreach ($ATTRS as $a) {
    $rx = '~\b'.preg_quote($a,'~').'\s*=\s*("([^"]+)"|\'([^\']+)\')~u';
    if (preg_match_all($rx, $masked, $ma, PREG_OFFSET_CAPTURE)) {
      foreach ($ma[0] as $i=>$full) {
        $val = $ma[2][$i][0] ?? $ma[3][$i][0] ?? '';
        $pos = $ma[0][$i][1];
        $txt = trim(html_entity_decode($val, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
        if ($txt === '' || !preg_match('~[\p{L}\d]~u',$txt)) continue;

        $key = pathinfo($rel, PATHINFO_FILENAME).'_'.$a.'_'.slugify(mb_substr($txt,0,60)).'_'.substr(sha1($rel.'|attr|'.$a.'|'.$txt),0,8);
        $suggestions[] = [
          'file'=>$rel,'line'=>getLine($masked,$pos),'type'=>'attr','attr'=>$a,
          'key'=>$key,'text'=>$txt
        ];
        $totalAttr++;
      }
    }
  }

  restoreMasks($masked, $map);
}

// Ausgaben
@file_put_contents('i18n_report.json', json_encode($suggestions, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$csv = fopen('i18n_report.csv','w'); fputcsv($csv, ['file','line','type','attr','key','text']);
foreach ($suggestions as $r) fputcsv($csv, [$r['file'],$r['line'],$r['type'],$r['attr'],$r['key'],$r['text']]);
fclose($csv);

echo "Report geschrieben: i18n_report.json (".count($suggestions)." keys; texts=$totalText, attrs=$totalAttr)\n";
