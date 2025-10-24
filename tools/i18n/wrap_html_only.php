<?php
// tools/i18n/wrap_html_only.php
declare(strict_types=1);

/**
 * Ersetzt NUR reinen HTML-Text zwischen Tags durch <?= t('key') ?>.
 * Ignoriert: <script>, <style>, <template>, <textarea> <?= t('wrap_html_only_und_alle_php_blocke_abfa9fd4') ?><? ... ?>).
 * Ergänzt Keys in lang/de.php (Original) und lang/en.php (leer).
 *
 * Aufruf (Dry-Run):
 *   php tools/i18n/wrap_html_only.php --root=. --langdir=lang --langs=de,en --dry=1
 *
 * Anwenden:
 *   php tools/i18n/wrap_html_only.php --root=. --langdir=lang --langs=de,en --dry=0
 *
 * Nur eine Datei:
 *   php tools/i18n/wrap_html_only.php --file=theme/overview.php --langdir=lang --langs=de,en --dry=0
 */

$opts = getopt('', [
  'root::','file::','langdir::','langs::',
  'exts::','skipdirs::','ignoretags::','minlen::','dry::'
]);

$ROOT        = rtrim($opts['root'] ?? getcwd(), '/');
$ONE_FILE    = $opts['file'] ?? '';
$LANGDIR     = rtrim($opts['langdir'] ?? 'lang', '/');
$LANGS       = array_filter(array_map('trim', explode(',', $opts['langs'] ?? 'de,en')));
$EXTS        = array_filter(array_map('trim', explode(',', $opts['exts'] ?? 'php,phtml,html,htm,tpl')));
$SKIPDIRS_RX = '~^(' . ($opts['skipdirs'] ?? 'uploads|public/uploads|vendor|node_modules|assets|\.git|\.idea|cache|logs|lang') . ')/~i';
$IGNORE_TAGS = array_filter(array_map('trim', explode(',', $opts['ignoretags'] ?? 'script,style,template,textarea')));
$MINLEN      = max((int)($opts['minlen'] ?? 2), 1);
$DRY         = ($opts['dry'] ?? '1') === '1';

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
function isLikelyVisible(string $s, int $minlen): bool {
  $t = trim(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
  if ($t === '') return false;
  if (!preg_match('~[\pL\d]~u', $t)) return false;
  $t = preg_replace('~\s+~u',' ', $t);
  return mb_strlen($t) >= $minlen;
}
// PHP-Blöcke maskieren
function maskPhp(string $html): array {
  $map = [];
  $html = preg_replace_callback('~<\?(php|=).*?\?>~is', function($m) use (&$map) {
    $id = '%%I18N_MASK_PHP_'.count($map).'%%';
    $map[$id] = $m[0];
    return $id;
  }, $html);
  return [$html, $map];
}
// Tag-Blöcke maskieren (script/style/…)
function maskTags(string $html, array $tags): array {
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
function unmask(string $html, array $maps): string {
  foreach ($maps as $map) if ($map) $html = strtr($html, $map);
  return $html;
}
function extMatch(string $rel, array $exts): bool {
  $e = pathinfo($rel, PATHINFO_EXTENSION);
  return $e && in_array(strtolower($e), array_map('strtolower',$exts), true);
}

$totalFiles = 0; $changedFiles = 0; $totalKeys = 0;
$keyBucket  = []; // alle neuen Keys -> Text

$files = [];
if ($ONE_FILE !== '') {
  $path = $ONE_FILE;
  if (!is_file($path)) $path = "$ROOT/".ltrim($ONE_FILE,'/');
  if (!is_file($path)) { fwrite(STDERR, "File not found: $ONE_FILE\n"); exit(1); }
  $files[] = $path;
} else {
  $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
  foreach ($rii as $spl) {
    /** @var SplFileInfo $spl */
    if ($spl->isDir()) continue;
    $rel = ltrim(str_replace($ROOT.'/', '', $spl->getPathname()), '/');
    if (!extMatch($rel, $EXTS)) continue;
    if (preg_match($SKIPDIRS_RX, $rel)) continue;
    $files[] = $spl->getPathname();
  }
}

foreach ($files as $file) {
  $totalFiles++;
  $rel  = ltrim(str_replace($ROOT.'/', '', $file), '/');
  $base = pathinfo($rel, PATHINFO_FILENAME);

  $src = file_get_contents($file);
  if ($src === false || $src === '') continue;

  // 1) PHP komplett maskieren
  [$masked, $phpMap] = maskPhp($src);

  // 2) Script/Style/… maskieren
  [$masked, $blockMap] = maskTags($masked, $IGNORE_TAGS);

  $changed = false; $added = 0;

  // 3) nur Textknoten >...< ersetzen
  $masked = preg_replace_callback('~>([^<]+)<~u', function($m) use ($rel,$base,$file,$MINLEN,&$changed,&$added,&$keyBucket) {
    $raw = $m[1];

    // wenn Platzhalter (Masken) im Inneren, Finger weg
    if (str_contains($raw, '%%I18N_MASK_')) return $m[0];

    // bereits PHP drin? Dann nicht anfassen
    if (str_contains($raw, '<?') || str_contains($raw, '?>')) return $m[0];

    // sichtbaren Text prüfen
    if (!isLikelyVisible($raw, $MINLEN)) return $m[0];

    // führende/trailing Spaces erhalten
    if (!preg_match('~^(\s*)(.*?)(\s*)$~us', $raw, $mm)) return $m[0];
    $pre = $mm[1]; $inner = $mm[2]; $post = $mm[3];

    // Sicherheit: wenn inner bereits t('..') o.ä. enthält, skip
    if (preg_match('~t\s*\([\'"]~', $inner) || preg_match('~\$L\[[\'"]~', $inner)) return $m[0];

    $txtVisible = trim(html_entity_decode($inner, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
    if ($txtVisible === '' || !preg_match('~[\pL\d]~u',$txtVisible)) return $m[0];

    $slug = slugify(mb_substr($txtVisible, 0, 60));
    $hash = substr(sha1($rel.'|text|'.$txtVisible), 0, 8);
    $key  = "{$base}_{$slug}_{$hash}";

    // merken für Sprachdateien
    if (!isset($keyBucket[$key])) $keyBucket[$key] = $txtVisible;

    $changed = true; $added++;
    return '>'.$pre.'<?= t(\''.$key.'\') ?>'.$post.'<';
  }, $masked);

  if ($changed) {
    $changedFiles++;
    $totalKeys += $added;

    // 4) Demaskieren
    $out = unmask($masked, [$blockMap, $phpMap]);

    if ($DRY) {
      echo "[DRY] Would change: $rel (+$added keys)\n";
    } else {
      if (!file_exists("$file.bak")) file_put_contents("$file.bak", $src);
      file_put_contents($file, $out);
      echo "Changed: $rel (+$added)\n";
    }
  }
}

// 5) Sprachdateien aktualisieren
if (!$DRY && $keyBucket) {
  foreach ($LANGS as $lang) {
    $langFile = "$ROOT/$LANGDIR/$lang.php";
    $L = loadLang($langFile);
    $add = 0;
    foreach ($keyBucket as $k => $text) {
      if (!array_key_exists($k,$L)) {
        $L[$k] = ($lang === 'de') ? $text : '';
        $add++;
      }
    }
    if ($add > 0) {
      @mkdir(dirname($langFile), 0777, true);
      saveLang($langFile, $L);
      echo "Updated $LANGDIR/$lang.php (+$add)\n";
    } else {
      echo "No additions for $LANGDIR/$lang.php\n";
    }
  }
}

echo $DRY
  ? "Dry-run done. Files scanned: $totalFiles. Would change: $changedFiles. Keys: $totalKeys\n"
  : "Done. Files changed: $changedFiles. Keys added: $totalKeys\n";
