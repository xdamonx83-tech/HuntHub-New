<?php
// /tools/i18n/scan_html.php
declare(strict_types=1);

/**
 * Scannt PHP/HTML-Dateien nach hardcodierten Texten in:
 *   1) sichtbaren Textknoten zwischen HTML-Tags
 *   2) ausgewählten Attributen (title, placeholder, alt, aria-label, value, ...)
 *
 * Erzeugt:
 *   - i18n_html_suggestions.json  (Liste aller Funde mit Kontext)
 *   - ergänzt fehlende Keys in lang/de.php (mit Originaltext) und lang/en.php (leer)
 *
 * Aufruf-Beispiel:
 *   php tools/i18n/scan_html.php --root=. --langs=de,en --langdir=lang --attrs=title,placeholder,alt,aria-label,value --minlen=2
 */

ini_set('mbstring.internal_encoding', 'UTF-8');

$opts = getopt('', [
  'root::',       // Projekt-Root
  'langs::',      // "de,en"
  'langdir::',    // "lang"
  'attrs::',      // "title,placeholder,alt,aria-label,value"
  'minlen::',     // Mindestlänge sichtbarer Texte
]);

$ROOT    = rtrim($opts['root'] ?? '.', '/');
$LANGS   = array_filter(array_map('trim', explode(',', $opts['langs'] ?? 'de,en')));
$LANGDIR = rtrim($opts['langdir'] ?? 'lang', '/');
$ATTRS   = array_filter(array_map('trim', explode(',', $opts['attrs'] ?? 'title,placeholder,alt,aria-label,value')));
$MINLEN  = max( (int)($opts['minlen'] ?? 2), 1 );

if (!is_dir($ROOT))    { fwrite(STDERR, "Root not found: $ROOT\n"); exit(1); }
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
  if ($s === '') $s = 'text';
  return $s;
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
function isLikelyVisibleText(string $s, int $minlen): bool {
  // weg mit whitespace, html-entities einzeln sind ok, aber zu kurz ignorieren
  $t = trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  if ($t === '') return false;
  // nur Interpunktion/Trenner?
  if (!preg_match('~[\pL\d]~u', $t)) return false;
  // Mindestlänge (nach Verdichtung mehrfacher Leerzeichen)
  $t2 = preg_replace('~\s+~u',' ',$t);
  return mb_strlen($t2) >= $minlen;
}
function stripPhpBlocks(string $code): string {

  return preg_replace_callback('~<\?(php|=).*?\?>~is', function($m){
    return str_repeat(' ', strlen($m[0]));
  }, $code);
}
function findLineAndContext(string $haystack, string $needle, int $contextLen = 40): array {
  $pos = mb_stripos($haystack, $needle);
  if ($pos === false) return [0, ''];
  $pre  = mb_substr($haystack, max(0, $pos - $contextLen), $contextLen);
  $hit  = mb_substr($haystack, $pos, mb_strlen($needle));
  $post = mb_substr($haystack, $pos + mb_strlen($needle), $contextLen);
  // Zeile bestimmen
  $prefix = mb_substr($haystack, 0, $pos);
  $line = 1 + substr_count($prefix, "\n");
  $ctx = $pre . '⟪' . $hit . '⟫' . $post;
  return [$line, $ctx];
}

$EXT_RX = '~\.(php|phtml|html|htm|tpl)$~i';
$SKIP   = '~^(uploads|public/uploads|vendor|node_modules|assets|\.git|\.idea|cache|logs|lang)/~i';

$suggestions = []; // key => <?= t('scan_html_item_counttext_0_countattr_0_rii_new_recursi_98458c6f') ?><?xml') === false) {
    $html = '<!DOCTYPE html><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . $html;
  }
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $ok  = $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOWARNING|LIBXML_NOERROR);
  libxml_clear_errors();
  if (!$ok) continue;

  // 1) sichtbare Textknoten
  $xpath = new DOMXPath($dom);
  // keine Texte in script/style/noscript/template
  $nodes = $xpath->query('//text()[normalize-space()]');
  foreach ($nodes as $node) {
    /** @var DOMText $node */
    $parent = $node->parentNode;
    if (!$parent) continue;
    $pn = strtolower($parent->nodeName);
    if (in_array($pn, ['script','style','noscript','template'])) continue;

    $text = html_entity_decode($node->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // UI-Schrott ignorieren (nur whitespace, nur Sonderzeichen)
    if (!isLikelyVisibleText($text, $MINLEN)) continue;

    $trim = preg_replace('~\s+~u',' ', trim($text));
    // Key bauen
    $base = pathinfo($rel, PATHINFO_FILENAME);
    $slug = slugify(mb_substr($trim, 0, 60));
    $hash = substr(sha1($rel.'|text|'.$trim), 0, 8);
    $key  = "{$base}_{$slug}_{$hash}";

    if (!isset($suggestions[$key])) {
      [$line, $ctx] = findLineAndContext($clean, $trim);
      $suggestions[$key] = [
        'type'    => 'text',
        'text'    => $trim,
        'file'    => $rel,
        'line'    => $line,
        'context' => $ctx,
        'count'   => 1,
      ];
      $countText++;
    } else {
      $suggestions[$key]['count']++;
    }
  }

  // 2) Attribute
  foreach ($ATTRS as $attr) {
    $attrNodes = $xpath->query(sprintf('//*[@%s]', $attr));
    foreach ($attrNodes as $el) {
      /** @var DOMElement $el */
      $val = $el->getAttribute($attr);
      if (!isLikelyVisibleText($val, $MINLEN)) continue;
      $trim = preg_replace('~\s+~u',' ', trim(html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

      $base = pathinfo($rel, PATHINFO_FILENAME);
      $slug = slugify(mb_substr($trim, 0, 60));
      $hash = substr(sha1($rel.'|attr|'.$attr.'|'.$trim), 0, 8);
      $key  = "{$base}_{$attr}_{$slug}_{$hash}";

      if (!isset($suggestions[$key])) {
        [$line, $ctx] = findLineAndContext($clean, $trim);
        $suggestions[$key] = [
          'type'    => 'attr',
          'attr'    => $attr,
          'text'    => $trim,
          'file'    => $rel,
          'line'    => $line,
          'context' => $ctx,
          'count'   => 1,
        ];
        $countAttr++;
      } else {
        $suggestions[$key]['count']++;
      }
    }
  }
}

// Ausgabe
$outFile = "$ROOT/i18n_html_suggestions.json";
file_put_contents($outFile, json_encode($suggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Wrote suggestions: $outFile (".count($suggestions)." keys; texts=$countText, attrs=$countAttr)\n";

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
