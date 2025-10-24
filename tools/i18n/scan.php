<?php
/**
 * tools/i18n/scan.php
 * Minimaler i18n-Scanner für Hunthub (PHP/HTML/JS).
 * Sucht harte Texte, ignoriert t()/i18n.t() und $L[...], erzeugt i18n_report.json.
 */
declare(strict_types=1);

ini_set('memory_limit', '1024M');
mb_internal_encoding('UTF-8');

$opt = getopt('', [
  'root::',   // Projekt-Wurzel
  'out::',    // Ziel-Datei (JSON)
  'ext::',    // Dateiendungen, komma-getrennt
  'ignore::', // ignorierte Ordner, komma-getrennt
]);
$ROOT   = realpath($opt['root'] ?? getcwd()) ?: getcwd();
$OUT    = $opt['out'] ?? $ROOT . '/i18n_report.json';
$EXTS   = array_map('strtolower', preg_split('/[,\s]+/', $opt['ext'] ?? 'php,js,ts,html,htm', -1, PREG_SPLIT_NO_EMPTY));
$IGNORE = array_filter(preg_split('/[,\s]+/', $opt['ignore'] ?? 'vendor,node_modules,uploads,public/uploads,assets/fonts,assets/images,assets/svg,.git,.idea,cache,logs,migrations', -1, PREG_SPLIT_NO_EMPTY));

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isDir()) continue;
    $path = str_replace('\\','/',$f->getPathname());
    $rel  = ltrim(str_replace(str_replace('\\','/',$ROOT), '', $path), '/');

    // Ordner ignorieren
    $skip = false;
    foreach ($IGNORE as $ig) {
        if (stripos($rel, trim($ig,'/').'/') === 0) { $skip=true; break; }
    }
    if ($skip) continue;

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $EXTS, true)) continue;

    // Sprachdateien überspringen
    if (preg_match('~/(lang|languages)/[a-z]{2}\.php$~i', $rel)) continue;

    $files[] = $path;
}

$report = [
  'generated_at' => date('c'),
  'root' => $ROOT,
  'total_candidates' => 0,
  'by_file' => [],
  'summary' => ['php'=>0,'js'=>0,'html'=>0],
  'hints' => [
    'ignore_line' => 'Füge // i18n-ignore ans Zeilenende ein (oder /* i18n-ignore */ ... /* end-i18n-ignore */ für Blöcke).',
    'wrap_php' => "Empfehlung: <?= esc(t('key')) ?> oder \$L['key']",
    'wrap_js'  => "Empfehlung: t('key') oder i18n.t('key')",
  ],
];

foreach ($files as $path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $src = @file_get_contents($path);
    if ($src === false) continue;

    $items = [];
    if ($ext === 'php')        $items = scan_php($src);
    elseif ($ext === 'js' || $ext === 'ts') $items = scan_js($src);
    else                       $items = scan_html($src);

    if ($items) {
        $report['by_file'][$path] = $items;
        $report['total_candidates'] += count($items);
        if ($ext === 'php')        $report['summary']['php']  += count($items);
        elseif ($ext==='js'||$ext==='ts') $report['summary']['js']   += count($items);
        else                        $report['summary']['html'] += count($items);
    }
}

file_put_contents($OUT, json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo "✔ i18n_report.json geschrieben: $OUT\n";

/* ---------------- helpers ---------------- */

function scan_php(string $src): array {
    // i18n-ignore Blöcke entfernen
    $src = preg_replace('~/\*\s*i18n-ignore\s*\*/.*?/\*\s*end-i18n-ignore\s*\*/~si','',$src);

    $items = [];
    $tokens = token_get_all($src);
    $n = count($tokens);

    for ($i=0; $i<$n; $i++) {
        $tok = $tokens[$i];

        // t(...) / __(...) / tr(...) überspringen
        if (is_array($tok) && $tok[0] === T_STRING) {
            $name = strtolower($tok[1]);
            if (in_array($name, ['t','__','tr'], true)) {
                [$i] = skip_brackets($tokens, $i);
                continue;
            }
        }

        // inline HTML → Textnodes herausziehen
        if (is_array($tok) && $tok[0] === T_INLINE_HTML) {
            $html = $tok[1];
            $line = $tok[2];
            foreach (extract_text_nodes($html) as $node) {
                if (should_flag_textnode($node)) {
                    $items[] = [
                        'line' => $line,
                        'kind' => 'html-text',
                        'snippet' => snippet($node),
                        'suggested_key' => suggest_key($node),
                        'recommendation' => "Als <?= esc(t('".suggest_key($node)."')) ?> ausgeben.",
                    ];
                }
            }
            continue;
        }

        // PHP-Strings finden
        if (is_array($tok) && in_array($tok[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $line = $tok[2];
            $str  = stripcslashes(trim(trim($tok[1]),"'\""));

            // $L['key']-Kontexte überspringen
            if (looks_like_lang_key_context($tokens, $i)) continue;

            if (should_flag_string($str)) {
                $key = suggest_key($str);
                $items[] = [
                    'line' => $line,
                    'kind' => 'php-string',
                    'snippet' => snippet($str),
                    'suggested_key' => $key,
                    'recommendation' => "In PHP mit t('{$key}') oder \$L['{$key}'] verwenden.",
                ];
            }
        }
    }
    return dedupe($items);
}

function scan_js(string $src): array {
    // i18n-ignore Blöcke entfernen
    $src = preg_replace('~\/\*\s*i18n-ignore\s*\*\/.*?\/\*\s*end-i18n-ignore\s*\*\/~si','',$src);

    $items = [];
    // Strings, die NICHT innerhalb t(...) / i18n.t(...) stehen
    if (preg_match_all('/(?<!t\s*\(|i18n\.t\s*\()(?:"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/s', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $raw = $hit[0];
            $off = $hit[1];
            $line = substr_count($src, "\n", 0, $off) + 1;
            $val = stripcslashes(trim($raw, "\"'"));
            if (should_flag_string($val)) {
                $key = suggest_key($val);
                $items[] = [
                    'line' => $line,
                    'kind' => 'js-string',
                    'snippet' => snippet($val),
                    'suggested_key' => $key,
                    'recommendation' => "In JS mit t('{$key}') oder i18n.t('{$key}') verwenden.",
                ];
            }
        }
    }
    return dedupe($items);
}

function scan_html(string $src): array {
    $items = [];
    // scripts/styles entfernen
    $src = preg_replace('~<script\b[^>]*>.*?</script>~is','',$src);
    $src = preg_replace('~<style\b[^>]*>.*?</style>~is','',$src);
    if (preg_match_all('/>([^<]+)</', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[1] as $hit) {
            $text = trim(html_entity_decode($hit[0], ENT_QUOTES|ENT_HTML5, 'UTF-8'));
            if ($text === '') continue;
            if (should_flag_textnode($text)) {
                $line = substr_count($src, "\n", 0, $hit[1]) + 1;
                $key = suggest_key($text);
                $items[] = [
                    'line' => $line,
                    'kind' => 'html-text',
                    'snippet' => snippet($text),
                    'suggested_key' => $key,
                    'recommendation' => "Als i18n-Ausgabe t('{$key}') verwenden.",
                ];
            }
        }
    }
    return dedupe($items);
}

/* ---------- Utility ---------- */

function skip_brackets(array $tokens, int $i): array {
    $depth = 0;
    $n = count($tokens);
    for ($j=$i+1; $j<$n; $j++) {
        $t = $tokens[$j];
        $s = is_array($t) ? $t[1] : $t;
        if ($s === '(') { $depth++; }
        elseif ($s === ')') {
            $depth--;
            if ($depth <= 0) return [$j];
        }
    }
    return [$i];
}

function looks_like_lang_key_context(array $tokens, int $i): bool {
    // naive: $L['key'] oder t("key") wird ohnehin oben geskippt
    // -> Hier nur $L[...] prüfen
    for ($j=$i-1; $j>=0 && $j>$i-6; $j--) {
        $t = $tokens[$j];
        if (is_array($t) && $t[0] === T_VARIABLE && trim($t[1]) === '$L') return true;
    }
    return false;
}

function should_flag_textnode(string $txt): bool {
    if ($txt === '' || mb_strlen($txt) < 3) return false;
    // nicht nur Zahlen/Zeichen
    if (!preg_match('/[A-Za-zÄÖÜäöüß]/u', $txt)) return false;
    // Platzhalter / Tokens / Shortcodes ignorieren
    if (preg_match('/^({{.*}}|<%.*%>|:\w+|%\w|{\w+})$/u', $txt)) return false;
    // UI-Glyphen
    if (preg_match('/^[\-\–—•→←↑↓#|\/\\\\]+$/u', $txt)) return false;
    return true;
}

function should_flag_string(string $s): bool {
    $s = trim($s);
    if ($s === '' || mb_strlen($s) < 3) return false;
    // URLs, Klassen, Pfade, SQL, Keys
    if (preg_match('~^(https?://|/|\.|#[A-Za-z0-9_-]+)~', $s)) return false;
    if (preg_match('/^(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE)\b/i', $s)) return false;
    if (preg_match('/^[a-z0-9_.-]{3,}$/', $s)) return false; // vermutlich Key
    if (!preg_match('/[A-Za-zÄÖÜäöüß]/u', $s)) return false;
    return true;
}

function suggest_key(string $txt): string {
    $s = mb_strtolower($txt, 'UTF-8');
    $s = preg_replace('~["\'`´’]~u','',$s);
    $s = preg_replace('~[^a-z0-9äöüß]+~iu','_',$s);
    $s = trim($s,'_');
    if (mb_strlen($s,'UTF-8') > 40) $s = mb_substr($s,0,40,'UTF-8');
    return $s ?: 'text_label';
}

function snippet(string $s): string {
    $s = preg_replace('/\s+/', ' ', $s);
    return (mb_strlen($s,'UTF-8') > 120) ? (mb_substr($s,0,117,'UTF-8').'…') : $s;
}

function dedupe(array $items): array {
    $seen = [];
    $out  = [];
    foreach ($items as $it) {
        $k = $it['line'].'|'.$it['kind'].'|'.$it['snippet'];
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $it;
    }
    return $out;
}

function extract_text_nodes(string $html): array {
    $html = preg_replace('~<script\b[^>]*>.*?</script>~is','',$html);
    $html = preg_replace('~<style\b[^>]*>.*?</style>~is','',$html);
    $res = [];
    if (preg_match_all('/>([^<]+)</', $html, $m)) {
        foreach ($m[1] as $t) {
            $txt = trim(html_entity_decode($t, ENT_QUOTES|ENT_HTML5, 'UTF-8'));
            if ($txt !== '') $res[] = $txt;
        }
    }
    return $res;
}
