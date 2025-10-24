<?php
// /tools/i18n/apply_echoes.php
declare(strict_types=1);

/**
 * Wendet i18n-Ersetzungen auf PHP-String-Literale in echo/print/short-echo an.
 * Nutzt i18n_suggestions.json als Quelle.
 *
 * Aufruf:
 *   php tools/i18n/apply_echoes.php --root=. --dry=0
 */

$opts = getopt('', ['root::','dry::']);
$ROOT = rtrim($opts['root'] ?? '.', '/');
$DRY  = ($opts['dry'] ?? '1') === '1';

$suggFile = "$ROOT/i18n_suggestions.json";
if (!file_exists($suggFile)) { fwrite(STDERR, "Not found: $suggFile\n"); exit(1); }
$suggestions = json_decode(file_get_contents($suggFile), true);
if (!is_array($suggestions)) { fwrite(STDERR, "Invalid JSON: $suggFile\n"); exit(1); }

// Map: file => <?= t('apply_echoes_text_key_byfile_foreach_suggestions_as_9caa910b') ?><$n; $i++) {
        $tk = $tokens[$i];

        
        if (is_array($tk) && $tk[0] === T_OPEN_TAG_WITH_ECHO) {
            $out .= $tk[1]; // "<?="
            // skip whitespaces
            $j = $i+1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $out .= $tokens[$j][1]; $j++;
            }
            if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                $raw = $tokens[$j][1];
                $text = stripcslashes(substr($raw, 1, -1));
                if (isset($textToKey[$text])) {
                    $out .= "t('".$textToKey[$text]."')";
                    $changed = true;
                    $i = $j; // skip string
                    continue;
                }
            }
            // fallback: normal weiter
            continue;
        }

        // echo/print Ersetzungen
        if (is_array($tk) && ($tk[0] === T_ECHO || $tk[0] === T_PRINT)) {
            $out .= is_array($tk) ? $tk[1] : $tk; // "echo" oder "print"
            // sammle bis Semikolon
            $buf = '';
            $j = $i+1;
            while ($j < $n) {
                $tt = $tokens[$j];
                $buf .= is_array($tt) ? $tt[1] : $tt;
                if ($tt === ';') break;
                $j++;
            }
            // versuche einfachen Fall: nur ein String-Literal
            if (preg_match('~^\s*([\'"])(.*)\1\s*;~s', $buf, $m)) {
                $text = stripcslashes($m[2]);
                if (isset($textToKey[$text])) {
                    $out = rtrim($out);
                    $out .= " t('".$textToKey[$text]."');";
                    $changed = true;
                    $i = $j; // bis Semikolon verbraucht
                    continue;
                }
            }
            // sonst original einfügen
            $i = $j;
            continue;
        }

        // Standard: Original weiterreichen
        $out .= is_array($tk) ? $tk[1] : $tk;
    }

    if ($changed) {
        $totalChanges++;
        if ($DRY) {
            echo "[DRY] Would change: $rel\n";
        } else {
            // Backup
            if (!file_exists("$path.bak")) file_put_contents("$path.bak", $code);
            file_put_contents($path, $out);
            echo "Changed: $rel\n";
        }
    }
}
echo $DRY ? "Dry run complete.\n" : "Applied to $totalChanges file(s).\n";
