<?php
// tools/i18n/make_copies.php
declare(strict_types=1);

/**
 * Nimmt i18n_report.json und erzeugt patchede Kopien unter i18n_patched/.
 * - ersetzt nur in den Kopien:
 *   - Textknoten   -> <?= t('key') ?>
 *   - Attribute    -> <?= t('key') ?>
 * - ignoriert <script>, <style>, <template>, <textarea>
 <?= t('make_copies_originale_bleiben_unverandert_aufruf_php_tools_i_dd4b1ecb') ?><'.preg_quote($tag,'~').'\b[^>]*>.*?</'.preg_quote($tag,'~').'>~is';
    $src = preg_replace_callback($rx, function($m) use (&$map, $tag) {
      $id = '%%I18N_MASK_'.strtoupper($tag).'_'.count($map).'%%';
      $map[$id] = $m[0];
      return $id;
    }, $src);
  }

  // sortiere längere Texte zuerst (stabilere Ersetzung)
  usort($rows, function($a,$b){ return mb_strlen($b['text']) <=> <?= t('make_copies_mb_strlen_a_text_foreach_rows_as_r_d6a01cde') ?>< -> ><?= t('key') ?><
      $src = preg_replace(
        '~><?= t('make_copies_preg_quote_txt_c946e1be') ?><~u',
        "><?= t('".$key."') ?><",
        $src, 1
      );
    } else {
      // attr="txt" / 'txt'
      $a = $r['attr'];
      $src = preg_replace(
        '~\b'.preg_quote($a,'~').'\s*=\s*"'.preg_quote($txt,'~').'"~u',
        $a.'="<?= t(\''.$key.'\') ?>"',
        $src, 1
      );
      $src = preg_replace(
        '~\b'.preg_quote($a,'~').'\s*=\s*\''.preg_quote($txt,'~').'\'~u',
        $a.'=\'<?= t(\''.$key.'\') ?>\'',
        $src, 1
      );
    }
  }

  // Demaskieren
  if ($map) $src = strtr($src, $map);

  // Datei in i18n_patched/ schreiben
  $out = $OUT.'/'.$rel;
  @mkdir(dirname($out), 0777, true);
  file_put_contents($out, $src);
  $doneFiles++;
}
echo "Patchede Kopien geschrieben im: i18n_patched/  (".$doneFiles." Dateien)\n";
echo "Vergleich z.B.:  diff -ruN . i18n_patched | less\n";
