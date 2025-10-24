<?php
// tools/i18n/fix_bad_short_echo.php
declare(strict_types=1);

/**
 * Repariert Fälle wie: $title = '<?= t("key") ?>';
 * -> macht daraus:     $title = t("key");
 */
$ROOT = getcwd();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$rxExt = '~\.php$~i';
$skip  = '~^(uploads|public/uploads|vendor|node_modules|assets|\.git|\.idea|cache|logs|lang)/~i';
$changed = 0;

foreach ($it as $f) {
  $rel = ltrim(str_replace($ROOT.'/', '', $f->getPathname()),'/');
  if ($f->isDir() || !preg_match($rxExt,$rel) || preg_match($skip,$rel)) continue;
  $src = @file_get_contents($f->getPathname());
  if ($src === false || $src === '') continue;


  $out = preg_replace(
    '~([\'"])\s*<\?=\s*t\(\s*([\'"])([^\'"]+)\2\s*\)\s*\?>\s*\1~',
    't("$3")',
    $src,
    -1,
    $cnt1
  );
  // Falls innen Singlequotes gewünscht:
  $out = preg_replace(
    '~([\'"])\s*<\?=\s*t\(\s*([\'"])([^\'"]+)\2\s*\)\s*\?>\s*\1~',
    "t('$3')",
    $out,
    -1,
    $cnt2
  );

  if (($cnt1 + $cnt2) > 0) {
    if (!file_exists($f->getPathname().'.bak')) file_put_contents($f->getPathname().'.bak', $src);
    file_put_contents($f->getPathname(), $out);
    echo "Fixed: $rel ($cnt1+$cnt2)\n";
    $changed++;
  }
}
echo $changed ? "Done ($changed files).\n" : "No issues found.\n";
