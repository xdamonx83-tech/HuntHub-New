<?php
// tools/i18n/wrap_file_safe.php
declare(strict_types=1);

/**
 * Kontext-sicheres i18n-Wrapping für EINE Datei.
 * - PHP-String-Literals bei Zuweisungen ( = oder => ) -> t('key')
 * - HTML-Textknoten & UI-Attribute                 -> <?= t('key') ?>
 * - Ignoriert Bereiche in <script>, <style>, <template>, <textarea>
 * - Legt .bak-Backup an und ergänzt lang/de.php (Original) & lang/en.php (leer)
 *
 * Aufruf:
 *   php tools/i18n/wrap_file_safe.php \
 *     --file=theme/login.php \
 *     --langdir=lang --langs=de,en \
 *     --ignoretags=script,style,template,textarea
 */

$opts = getopt('', ['file:', 'langdir::', 'langs::', 'ignoretags::']);
$FILE        = $opts['file'] ?? '';
$LANGDIR     = rtrim($opts['langdir'] ?? 'lang', '/');
$LANGS       = array_filter(array_map('trim', explode(',', $opts['langs'] ?? 'de,en')));
$IGNORE_TAGS = array_filter(array_map('trim', explode(',', $opts['ignoretags'] ?? 'script,style,template,textarea')));

if ($FILE === '' || !is_file($FILE)) { fwrite(STDERR, "Datei nicht gefunden: {$FILE}\n"); exit(1); }
if (!is_dir($LANGDIR)) { fwrite(STDERR, "Sprachordner nicht gefunden: {$LANGDIR}\n"); exit(1); }

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
// Blöcke maskieren (script/style/template/textarea) damit sie unangetastet bleiben
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
function restoreMasks(string $html, array $map): string {
  return $map ? strtr($html, $map) : $html;
}
function decStr(string $raw): string {
  // $raw ist komplettes Literal inkl. Quotes
  $q = $raw[0];
  $s = substr($raw, 1, -1);
  return stripcslashes($s);
}

$base = pathinfo($FILE, PATHINFO_FILENAME);
$orig = file_get_contents($FILE);
if ($orig === false) { fwrite(STDERR, "Lesefehler.\n"); exit(1); }

$suggestions = [];  // key => text
$changed = false;

/* =========================================================
 * TEIL 1: PHP tokenisieren – String-Literale bei Zuweisung
 * ========================================================= */
$tokens = token_get_all($orig);
$out    = '';
$inPhp  = false;

for ($i=0,$n=count($tokens); $i<$n; $i++) {
  $tk = $tokens[$i];

  if (is_array($tk)) {
    [$id,$text] = $tk;

    if ($id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO) { $inPhp = true;  $out .= $text; continue; }
    if ($id === T_CLOSE_TAG)                                 { $inPhp = false; $out .= $text; continue; }

    if ($inPhp) {
      // PHP-Stringliteral?
      if ($id === T_CONSTANT_ENCAPSED_STRING) {
        // look-behind: war das eine Zuweisung '=' oder '=>'
        $j=$i-1; $prev='';
        while ($j>=0) {
          $pt = $tokens[$j];
          if (is_array($pt) && $pt[0] === T_WHITESPACE) { $j--; continue; }
          $prev = is_array($pt) ? $pt[1] : $pt; break;
        }
        $isAssign = ($prev === '=' || $prev === '=>');

        if ($isAssign) {
          $val = decStr($text);
          // schon i18nisiert?
          if (!preg_match("~^(?:\\s*t\\(|\\s*\\\$L\\[)~", $val) && preg_match('~[\\p{L}\\d]~u', $val)) {
            // kein HTML/JS-Müll
            if (!preg_match('~</?[a-z]~i', $val) && !preg_match('~document\\.|addEventListener\\(|=>~', $val)) {
              $trim = preg_replace('~\\s+~u',' ', trim(html_entity_decode($val, ENT_QUOTES|ENT_HTML5, 'UTF-8')));
              if ($trim !== '') {
                $key = "{$base}_" . slugify(mb_substr($trim, 0, 60)) . '_' . substr(sha1($FILE.'|php|string|'.$trim), 0, 8);
                $suggestions[$key] = $trim;
                $out .= "t('".$key."')";
                $changed = true;
                continue;
              }
            }
          }
        }
      }
      // Standard: übernehmen
      $out .= $text;
      continue;
    }

    // HTML-Kontext
    $out .= $text;

  } else {
    // einfache Zeichen
    $out .= $tk;
  }
}

/* =========================================================
 * TEIL 2: HTML – Textknoten & UI-Attribute
 *  - maskiert <script>/<style>/<template>/<textarea>
 * ========================================================= */
$html = $out;

// 2a) maskieren
list($html, $maskedMap) = maskBlocks($html, $IGNORE_TAGS);

// 2b) Textknoten ersetzen
$patternText = '~(>)([^<]+)(<)~u';
$html = preg_replace_callback($patternText, function($m) use (&$suggestions,&$changed,$FILE,$base) {
  $open = $m[1]; $raw = $m[2]; $close = $m[3];
  $txt  = trim(html_entity_decode($raw, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
  if ($txt === '' || !preg_match('~[\\p{L}\\d]~u', $txt)) return $m[0];
  if (str_contains($raw,'<?') || str_contains($raw,'?>')) return $m[0];
  // rudimentäre JS-Fragmente im Text überspringen
  if (preg_match('~document\\.|addEventListener\\(|=>|\\bvar\\b|\\blet\\b|\\bconst\\b~', $raw)) return $m[0];

  $trim = preg_replace('~\\s+~u',' ', $txt);
  $key  = "{$base}_" . slugify(mb_substr($trim, 0, 60)) . '_' . substr(sha1($FILE.'|html|text|'.$trim), 0, 8);
  $suggestions[$key] = $trim;
  $changed = true;
  return $open.'<?= t(\''.$key.'\') ?>'.$close;
}, $html);

// 2c) nur „UI-Attribute“ (keine Events wie onclick)
foreach (['title','placeholder','alt','aria-label','value'] as $a) {
  $rx = '~\b'.preg_quote($a,'~').'\s*=\s*("([^"]+)"|\'([^\']+)\')~u';
  $html = preg_replace_callback($rx, function($m) use (&$suggestions,&$changed,$FILE,$base,$a){
    $val = $m[2] !== '' ? $m[2] : $m[3];
    if (preg_match('~t\\s*\\([\'"]~', $val) || preg_match('~\\$L\\[[\'"]~', $val)) return $m[0];
    if (str_contains($val,'<?') || str_contains($val,'?>')) return $m[0];

    $txt = trim(html_entity_decode($val, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
    if ($txt === '' || !preg_match('~[\\p{L}\\d]~u', $txt)) return $m[0];

    $trim = preg_replace('~\\s+~u',' ', $txt);
    $key  = "{$base}_{$a}_" . slugify(mb_substr($trim, 0, 60)) . '_' . substr(sha1($FILE.'|html|attr|'.$a.'|'.$trim), 0, 8);
    $suggestions[$key] = $trim;
    $changed = true;
    $q = $m[2] !== '' ? '"' : "'";
    return $a.'='.$q.'<?= t(\''.$key.'\') ?>'.$q;
  }, $html);
}

// 2d) Demaskieren
$html = restoreMasks($html, $maskedMap);

if (!$changed) { echo "Kein sicher ersetzbarer Text gefunden.\n"; exit(0); }

// Backup & Schreiben
if (!file_exists($FILE.'.bak')) file_put_contents($FILE.'.bak', $orig);
file_put_contents($FILE, $html);
echo "Geändert: {$FILE} (+".count($suggestions)." Keys)\n";

// Sprachdateien ergänzen
$LANGS = $LANGS ?: ['de','en'];
foreach ($LANGS as $lang) {
  $langFile = $LANGDIR.'/'.$lang.'.php';
  $L = loadLang($langFile); $added=0;
  foreach ($suggestions as $k=>$text) {
    if (!array_key_exists($k,$L)) { $L[$k] = ($lang==='de') ? $text : ''; $added++; }
  }
  if ($added>0) { @mkdir(dirname($langFile),0777,true); saveLang($langFile,$L); echo "Updated {$langFile} (+{$added})\n"; }
  else { echo "No additions for {$langFile}\n"; }
}

echo "Done.\n";
