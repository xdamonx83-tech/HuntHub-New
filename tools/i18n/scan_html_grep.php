<?php
// /tools/i18n/scan_html_grep.php
declare(strict_types=1);

/**
 * Aggressiver HTML-Scanner (regex-basiert) für sichtbare Textknoten und UI-Attribute.
 * Findet Kandidaten in .php/.phtml/.html/.htm/.tpl – auch in PHP-Templates mit Inline-HTML.
 *
 * Erzeugt:
 *   - i18n_html_suggestions.json  (Liste aller Funde mit Dateiname, Zeile, Kontext)
 *   - ergänzt fehlende Keys in lang/de.php (mit Originaltext) und lang/en.php (leer)
 *
 * Aufruf-Beispiel:
 *   php tools/i18n/scan_html_grep.php --root=. --langs=de,en --langdir=lang \
 *     --attrs=title,placeholder,alt,aria-label,value --minlen=2 --verbose=1
 */

$opts = getopt('', [
  'root::', 'langs::', 'langdir::', 'attrs::', 'minlen::', 'verbose::'
]);

$ROOT     = rtrim($opts['root'] ?? '.', '/');
$LANGS    = array_filter(array_map('trim', explode(',', $opts['langs'] ?? 'de,en')));
$LANGDIR  = rtrim($opts['langdir'] ?? 'lang', '/');
$ATTRS    = array_filter(array_map('trim', explode(',', $opts['attrs'] ?? 'title,placeholder,alt,aria-label,value')));
$MINLEN   = max( (int)($opts['minlen'] ?? 2), 1 );
$VERBOSE  = (int)($opts['verbose'] ?? 0);

if (!is_dir($ROOT)) { fwrite(STDERR, "Root not found: $ROOT\n"); exit(1); }
if (!is_dir("$ROOT/$LANGDIR")) { fwrite(STDERR, "Lang dir not found: $ROOT/$LANGDIR\n"); exit(1); }

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
function loadLang(string $file): array {
  if (!file_exists($file)) return [];
  $L = require $file;
  return is_array($L) ? $L : [];
}
function saveLang(string $file, array $L): void {
  ksort($L, SORT_NATURAL);
  $code = "<?php\nreturn " . var_export($L, true) . ";\n";
  file_put_contents($file, $code);
}
function looksVisible(string $s, int $minlen): bool {
  $t = html_entity_decode(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  if ($t === '') return false;
  if (!preg_match('~[\pL\d]~u', $t)) return false; // nur Satzzeichen => nein
  $t2 = preg_replace('~\s+~u', ' ', $t);
  return mb_strlen($t2) >= $minlen;
}
function getLineForPos(string $txt, int $pos): int {
  return 1 + substr_count(substr($txt, 0, max(0,$pos)), "\n");
}
function contextAround(string $txt, int $pos, int $len, int $ctx=35): string {
  $start = max(0, $pos - $ctx);
  $end   = min(strlen($txt), $pos + $len + $ctx);
  $pre   = substr($txt, $start, $pos - $start);
  $hit   = substr($txt, $pos, $len);
  $post  = substr($txt, $pos + $len, $end - ($pos + $len));
  return $pre.'⟪'.$hit.'⟫'.$post;
}

$EXT_RX = '~\.(php|phtml|html|htm|tpl)$~i';
$SKIP   = '~^(uploads|public/uploads|vendor|node_modules|assets|\.git|\.idea|cache|logs|lang)/~i';

$suggestions = [];
$totalTxt = 0; $totalAttr = 0;

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $spl) {
  /** @var SplFileInfo $spl */
  if ($spl->isDir()) continue;
  $path = $spl->getPathname();
  $rel  = ltrim(str_replace($ROOT, '', $path), '/');

  if (!preg_match($EXT_RX, $rel)) continue;
  if (preg_match($SKIP, $rel))    continue;

  $src = file_get_contents($path);
  if ($src === false || $src === '') continue;

  $perFileTxt = 0; $perFileAttr = 0;

  // 1) Text zwischen >...<
  if (preg_match_all('~>([^<]+)<~u', $src, $m, PREG_OFFSET_CAPTURE)) {
    foreach ($m[1] as $match) {
      [$raw, $pos] = $match;
      $cand = $raw;

      // Dinge ignorieren, die klar nicht übersetzt werden müssen
      // - bereits t('...') oder $L[...] in unmittelbarer Nähe
      // - nur Whitespaces / nur Sonderzeichen
      if (preg_match("~t\s*\(['\"][^)]+['\"]\)~", $cand)) continue;
      if (preg_match("~\\$L\\[['\"][^\\]]+['\"]\\]~", $cand)) continue;

      // Heuristik: PHP-Ausdrücke in Textknoten überspringen
      if (str_contains($cand, '<?') || str_contains($cand, '?>')) continue;

      if (!looksVisible($cand, $MINLEN)) continue;

      $trim = preg_replace('~\s+~u', ' ', trim(html_entity_decode($cand, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
      $base = pathinfo($rel, PATHINFO_FILENAME);
      $slug = slugify(mb_substr($trim, 0, 60));
      $hash = substr(sha1($rel.'|text|'.$trim), 0, 8);
      $key  = "{$base}_{$slug}_{$hash}";
      if (!isset($suggestions[$key])) {
        $suggestions[$key] = [
          'type'    => 'text',
          'text'    => $trim,
          'file'    => $rel,
          'line'    => getLineForPos($src, $pos),
          'context' => contextAround($src, $pos, strlen($raw)),
          'count'   => 1,
        ];
        $totalTxt++; $perFileTxt++;
      } else {
        $suggestions[$key]['count']++;
      }
    }
  }

  // 2) Attribute
  if (!empty($ATTRS)) {
    // Doppelte + einfache Anführungszeichen
    foreach ($ATTRS as $attr) {
      $rx = '~\b'.preg_quote($attr, '~').'\s*=\s*("([^"]+)"|\'([^\']+)\')~u';
      if (preg_match_all($rx, $src, $ma, PREG_OFFSET_CAPTURE)) {
        foreach ($ma[0] as $i => $mfull) {
          $val = $ma[2][$i][0] ?? $ma[3][$i][0] ?? '';
          $pos = $ma[0][$i][1];

          if (preg_match("~t\s*\(['\"][^)]+['\"]\)~", $val)) continue;
          if (preg_match("~\\$L\\[['\"][^\\]]+['\"]\\]~", $val)) continue;
          if (!looksVisible($val, $MINLEN)) continue;

          $trim = preg_replace('~\s+~u', ' ', trim(html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
          $base = pathinfo($rel, PATHINFO_FILENAME);
          $slug = slugify(mb_substr($trim, 0, 60));
          $hash = substr(sha1($rel.'|attr|'.$attr.'|'.$trim), 0, 8);
          $key  = "{$base}_{$attr}_{$slug}_{$hash}";

          if (!isset($suggestions[$key])) {
            $suggestions[$key] = [
              'type'    => 'attr',
              'attr'    => $attr,
              'text'    => $trim,
              'file'    => $rel,
              'line'    => getLineForPos($src, $pos),
              'context' => contextAround($src, $pos, strlen($mfull[0])),
              'count'   => 1,
            ];
            $totalAttr++; $perFileAttr++;
          } else {
            $suggestions[$key]['count']++;
          }
        }
      }
    }
  }

  if ($VERBOSE && ($perFileTxt || $perFileAttr)) {
    echo sprintf("%s: texts=%d, attrs=%d\n", $rel, $perFileTxt, $perFileAttr);
  }
}

// Ausgabe JSON
$outFile = "$ROOT/i18n_html_suggestions.json";
file_put_contents($outFile, json_encode($suggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Wrote suggestions: $outFile (".count($suggestions)." keys; texts=$totalTxt, attrs=$totalAttr)\n";

// Sprachen ergänzen
foreach ($LANGS as $lang) {
  $langFile = "$ROOT/$LANGDIR/$lang.php";
  $L = loadLang($langFile);
  $added = 0;
  foreach ($suggestions as $k => $it) {
    if (!array_key_exists($k, $L)) {
      $L[$k] = ($lang === 'de') ? $it['text'] : '';
      $added++;
    }
  }
  if ($added > 0) {
    @mkdir(dirname($langFile), 0777, true);
    saveLang($langFile, $L);
    echo "Updated $langFile (+$added)\n";
  } else {
    echo "No additions for $langFile\n";
  }
}

echo "Done.\n";
