<?php
declare(strict_types=1);

/**
 * Erscheinungsbild eines Users (Name-Farbe/-Klasse, Avatar-Rahmen, Badges …)
 * Quelle für Styles:
 *  - users.name_color      (Hex oder CSS-Farbe)
 *  - aktive Items aus user_items JOIN shop_items
 *      * type='color'  → color / css_class (auch in meta[color]/meta[css_class])
 *      * type='frame'  → css_class / style / image (Overlay)
 *      * type='badge'  → image/title/css_class/style
 *      * type='vip'    → Kennzeichen (optional)
 *
 * Rückgabewerte werden in Templates z. B. so benutzt:
 *   <h3 class="user-name <?= $ap['name_class'] ?>" style="<?= $ap['name_style'] ?>">
 */

if (!function_exists('build_user_appearance')) {
  function build_user_appearance(PDO $pdo, array $user): array {
    $uid = (int)($user['id'] ?? 0);

    // Aktive Items holen (max. 1 pro type aktiv per App-Logik)
    $st = $pdo->prepare("
      SELECT si.*, ui.is_active
      FROM user_items ui
      JOIN shop_items si ON si.id = ui.item_id
      WHERE ui.user_id = ? AND ui.is_active = 1
    ");
    $st->execute([$uid]);
    $active = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $r['meta_arr'] = $r['meta'] ? (json_decode((string)$r['meta'], true) ?: []) : [];
      $active[(string)$r['type']] = $r;
    }

    // ===== Name (Farbe + Klasse) =====
    // Farbe-Prio: users.name_color > aktives color-Item (meta[color] > spalte color)
    $hex = trim((string)($user['name_color'] ?? ''));
    if ($hex === '' && isset($active['color'])) {
      $ci  = $active['color'];
      $hex = trim((string)($ci['meta_arr']['color'] ?? ($ci['color'] ?? '')));
    }
    $nameStyle = $hex !== '' ? "color: {$hex}" : '';

    $nameClass = 'user-name';
    if (isset($active['color'])) {
      $ci  = $active['color'];
      $cls = (string)($ci['meta_arr']['css_class'] ?? ($ci['css_class'] ?? ''));
      if ($cls !== '') $nameClass .= ' ' . $cls;
    }
    // VIP-Flag optional als Klasse
    $isVip = (int)($user['is_vip'] ?? 0) === 1 || isset($active['vip']);
    if ($isVip) $nameClass .= ' is-vip';

    // ===== Avatar-Rahmen =====
    $frameClass = '';
    $frameStyle = '';
    $frameOverlay = ''; // z.B. <img class="frame-overlay" src="...">
    if (isset($active['frame'])) {
      $f   = $active['frame'];
      $cls = (string)($f['meta_arr']['css_class'] ?? ($f['css_class'] ?? ''));
      $sty = (string)($f['meta_arr']['style']     ?? ($f['style']     ?? ''));
      $img = (string)($f['meta_arr']['image']     ?? ($f['image']     ?? ''));
      if ($cls !== '') $frameClass = $cls;
      if ($sty !== '') $frameStyle = $sty;
      if ($img !== '') $frameOverlay = '<img class="frame-overlay" src="'.htmlspecialchars($img, ENT_QUOTES, 'UTF-8').'" alt="">';
    }

    // ===== Badges (mehrere möglich) =====
    $badges = [];
    foreach ($active as $t => $row) {
      if ($t !== 'badge') continue;
      $title = (string)($row['title'] ?? '');
      $img   = (string)($row['meta_arr']['image'] ?? ($row['image'] ?? ''));
      $cls   = (string)($row['meta_arr']['css_class'] ?? ($row['css_class'] ?? ''));
      $sty   = (string)($row['meta_arr']['style'] ?? ($row['style'] ?? ''));
      $badges[] = [
        'title' => $title,
        'image' => $img,
        'class' => $cls,
        'style' => $sty,
      ];
    }

    return [
      // Name
      'name_class'   => $nameClass,
      'name_style'   => $nameStyle,

      // Avatar-Rahmen
      'frame_class'  => $frameClass,
      'frame_style'  => $frameStyle,
      'frame_overlay_html' => $frameOverlay,

      // Badges
      'badges'       => $badges,

      // Flags
      'is_vip'       => $isVip ? 1 : 0,
    ];
  }
}
