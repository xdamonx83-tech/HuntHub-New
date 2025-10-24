<?php
declare(strict_types=1);

/**
 * Rebuilds pruned lang files from actually used keys in code.
 * - scan for t('key' ...) and $L['key']
 * - keep only used keys
 * - missing keys are added (de: key as placeholder, en: '')
 * - writes backups *.bak once (if not present)
 *
 * Dry-run:
 *   php tools/i18n/rebuild_lang_from_usage.php --root=. --langdir=lang --langs=de,en --dry=1
 *
 * Apply:
 *   php tools/i18n/rebuild_lang_from_usage.php --root=. --langdir=lang --langs=de,en --dry=0
 */

$opts = getopt('', [
  'root::','langdir::','langs::','exts::','skipdirs::','include_L::','dry::'
]);

$ROOT      = rtrim($opts['root'] ?? getcwd(), '/');
$LANGDIR   = rtrim($opts['langdir'] ?? 'lang', '/');
$LANGS     = array_filter(array_map('trim', explode(',', $opts['langs'] ?? 'de,en')));
$EXTS      = array_filter(array_map('trim', explode(',', $opts['exts'] ?? 'php,phtml,html,htm,tpl')));
$SKIPDIRS  = $opts['skipdirs'] ?? 'vendor|node_modules|uploads|public/uploads|assets|\.git|\.idea|cache|logs|lang';
$INCLUDE_L = ($opts['include_L'] ?? '1') === '1'; // also scan $L['...']
$DRY       = ($opts['dry'] ?? '1') === '1';

if (!is_dir($ROOT)) { fwrite(STDERR, "Root not found: $ROOT\n"); exit(1); }
@mkdir("$ROOT/$LANGDIR", 0777, true);

// ---------- helpers ----------
function load_lang_file(string $file): array {
  if (!is_file($file)) return [];
  $L = require $file;
  return is_array($L) ? $L : [];
}
function save_lang_file(string $file, array $L): void {
  ksort($L, SORT_NATURAL);
  $code = "<?php\nreturn " . var_export($L, true) . ";\n";
  file_put_contents($file, $code);
}
function ext_ok(string $path, array $exts): bool {
  $e = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');
  return $e && in_array($e, array_map('strtolower',$exts), true);
}

// ---------- collect files ----------
$rxSkip = '~/(?:'. $SKIPDIRS .')/|^\.(git|idea)/~i';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($rii as $it) {
  /** @var SplFileInfo $it */
  if ($it->isDir()) continue;
  $rel = ltrim(str_replace($ROOT.'/', '', $it->getPathname()), '/');
  if (preg_match($rxSkip, '/'.$rel)) continue;
  if (!ext_ok($rel, $EXTS)) continue;
  $files[] = $it->getPathname();
}

// ---------- scan keys ----------
$used = [];
$rxT = '~\bt\s*\(\s*[\'"]([^\'"]+)[\'"]~u';         // t('key'
$rxL = '~\$L\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]~u';    // $L['key']

foreach ($files as $path) {
  $code = @file_get_contents($path);
  if ($code === false || $code === '') continue;

  if (preg_match_all($rxT, $code, $m)) {
    foreach ($m[1] as $k) $used[$k] = true;
  }
  if ($INCLUDE_L && preg_match_all($rxL, $code, $m2)) {
    foreach ($m2[1] as $k) $used[$k] = true;
  }
}
$usedKeys = array_keys($used);
sort($usedKeys, SORT_NATURAL);

echo "Found ".count($usedKeys)." used keys.\n";

// ---------- rebuild per language ----------
foreach ($LANGS as $lang) {
  $file = "$ROOT/$LANGDIR/$lang.php";
  $orig = load_lang_file($file);

  $kept = [];
  $added = 0;

  foreach ($usedKeys as $k) {
    if (array_key_exists($k, $orig)) {
      $kept[$k] = $orig[$k];
    } else {
      // defaults: German keeps key as readable placeholder, others empty
      $kept[$k] = ($lang === 'de') ? $k : '';
      $added++;
    }
  }

  $removed = max(0, count($orig) - count($kept));

  if ($DRY) {
    echo "[DRY] $LANGDIR/$lang.php -> keep: ".count($kept).", add: $added, drop: $removed\n";
  } else {
    if (is_file($file) && !is_file("$file.bak")) {
      @copy($file, "$file.bak");
      echo "Backup: $LANGDIR/".basename($file).".bak\n";
    }
    save_lang_file($file, $kept);
    echo "Wrote: $LANGDIR/$lang.php (keep: ".count($kept).", add: $added, drop: $removed)\n";
  }
}

echo $DRY ? "Dry-run done.\n" : "Done.\n";
