<?php
// /tools/i18n/scan_echoes.php
declare(strict_types=1);

/**
 * Scannt PHP-Dateien nach harten String-Literalen in echo/print/<?= ... ?> und
 * erstellt Vorschläge für i18n-Keys + ergänzt fehlende Keys in lang/*.php.
 *
 * Aufruf:
 *   php tools/i18n/scan_echoes.php --root=. --langs=de,en --langdir=lang
 */

$opts = getopt('', ['root::','langs::','langdir::']);
$ROOT    = rtrim($opts['root'] ?? '.', '/');
$LANGS   = array_filter(array_map('trim', explode(',', $opts['langs'] ?? 'de,en')));
$LANGDIR = rtrim($opts['langdir'] ?? 'lang', '/');

if (!is_dir($ROOT)) { fwrite(STDERR, "Root not found: $ROOT\n"); exit(1); }
if (!is_dir("$ROOT/$LANGDIR")) { fwrite(STDERR, "Lang dir not found: $ROOT/$LANGDIR\n"); exit(1); }

function slugify(string $s): string {
    $s = trim($s);
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

$suggestions = []; // [ key => <?= t('scan_echoes_text_file_line_count_n_25ec1e83') ?> < $n; $i++) {
        $tk = $tokens[$i];

        // echo/print
        $isEcho = false;
        if (is_array($tk) && ($tk[0] === T_ECHO)) $isEcho = true;
        if (is_array($tk) && ($tk[0] === T_PRINT)) $isEcho = true;

   
        $isShortEcho = is_array($tk) && $tk[0] === T_OPEN_TAG_WITH_ECHO;

        if (!$isEcho && !$isShortEcho) continue;

        // Nächstes Stringliteral finden
        $j = $i+1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n) continue;

        // Wir akzeptieren nur unmittelbare String-Literale "..." oder '...'
        if (is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
            $raw = $tokens[$j][1]; // inklusive Quotes
            $quote = $raw[0];
            $text = stripcslashes(substr($raw, 1, -1));

            // Heuristik: sehr kurze, reine Platzhalter ignorieren, oder bereits vorhandene Keys
            $trim = trim($text);
            if ($trim === '' || preg_match('~^\{\{.*\}\}$~', $trim)) continue;
            if (preg_match('~^\$L\[[\'"]~', $trim)) continue;

            // Key vorschlagen (Datei-Basis + Text-Slug + 8char Hash)
            $base = pathinfo($rel, PATHINFO_FILENAME);
            $slug = slugify(mb_substr($trim, 0, 60));
            $hash = substr(sha1($rel.'|'.$trim), 0, 8);
            $key  = "{$base}_{$slug}_{$hash}";

            if (!isset($suggestions[$key])) {
                $suggestions[$key] = [
                    'text' => $text,
                    'file' => $rel,
                    'line' => is_array($tk) ? $tk[2] : 0,
                    'count'=> 1,
                ];
            } else {
                $suggestions[$key]['count']++;
            }
        }
    }
}

// Vorschläge speichern
$outFile = "$ROOT/i18n_suggestions.json";
file_put_contents($outFile, json_encode($suggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Wrote suggestions: $outFile (".count($suggestions)." keys)\n";

// Sprachen aktualisieren
foreach ($LANGS as $lang) {
    $langFile = "$ROOT/$LANGDIR/$lang.php";
    $L = loadLang($langFile);
    $added = 0;
    foreach ($suggestions as $k => $item) {
        if (!array_key_exists($k, $L)) {
            // de: Originaltext, en: leere Übersetzung als Platzhalter
            $L[$k] = ($lang === 'de') ? $item['text'] : '';
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
