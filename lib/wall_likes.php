<?php
declare(strict_types=1);
// Debug-Logging zentral steuern
if (!defined('HH_DEBUG')) define('HH_DEBUG', false);

if (!function_exists('hh_debug_log')) {
  function hh_debug_log(string $msg): void {
    if (defined('HH_DEBUG') && HH_DEBUG) {
      hh_debug_log($msg);
    }
  }
}

/**
 * Reactions & Likes für Wall (Posts & Kommentare)
 */

function wall_reaction_icons(): array {
  return [
    'like'  => '/assets/images/reactions/like.webp',
    'love'  => '/assets/images/reactions/love.webp',
    'haha'  => '/assets/images/reactions/haha.webp',
    'wow'   => '/assets/images/reactions/wow.webp',
    'sad'   => '/assets/images/reactions/sad.webp',
    'angry' => '/assets/images/reactions/angry.webp',
  ];
}

function wall_reaction_count(PDO $db, string $type, int $id): array {
  $sql = "SELECT reaction, COUNT(*) AS cnt 
            FROM wall_likes 
           WHERE entity_type=:t AND entity_id=:id 
           GROUP BY reaction";
  $st = $db->prepare($sql);
  $st->execute([':t'=>$type, ':id'=>$id]);
  $out = [];
  while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $out[$r['reaction']] = (int)$r['cnt'];
  }
  return $out;
}

function wall_user_reaction(PDO $db, int $uid, string $type, int $id): ?string {
  $st = $db->prepare("SELECT reaction FROM wall_likes WHERE entity_type=:t AND entity_id=:id AND user_id=:u LIMIT 1");
  $st->execute([':t'=>$type, ':id'=>$id, ':u'=>$uid]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ? $r['reaction'] : null;
}

function wall_reaction_set(PDO $db, int $uid, string $type, int $id, string $reaction): array {
	hh_debug_log("SET REACTION: uid=$uid type=$type id=$id reaction=$reaction");

  $allowed = ['like','love','haha','wow','sad','angry'];

  $reaction = trim($reaction);
  $isDelete = ($reaction === '');

  if (!$isDelete && !in_array($reaction, $allowed, true)) {
    $reaction = 'like';
  }

  $cur = wall_user_reaction($db, $uid, $type, $id);

  if ($isDelete) {
    $del = $db->prepare("DELETE FROM wall_likes WHERE entity_type=:t AND entity_id=:id AND user_id=:u");
    $del->execute([':t'=>$type, ':id'=>$id, ':u'=>$uid]);
    $new = null;
  } elseif ($cur === $reaction) {
    $del = $db->prepare("DELETE FROM wall_likes WHERE entity_type=:t AND entity_id=:id AND user_id=:u");
    $del->execute([':t'=>$type, ':id'=>$id, ':u'=>$uid]);
    $new = null;
  } else {
    $ins = $db->prepare("
      INSERT INTO wall_likes (entity_type,entity_id,user_id,reaction,created_at)
      VALUES(:t,:id,:u,:r,NOW())
      ON DUPLICATE KEY UPDATE reaction=VALUES(reaction), created_at=VALUES(created_at)
    ");
    $ins->execute([':t'=>$type, ':id'=>$id, ':u'=>$uid, ':r'=>$reaction]);
    $new = $reaction;
  }

  $counts = wall_reaction_count($db, $type, $id);
  $total  = array_sum($counts);
  return ['reaction'=>$new, 'counts'=>$counts, 'total'=>$total];
}

function wall_like_users(PDO $db, string $type, int $id): array {
  if (!function_exists('wall_user_select_sql')) require_once __DIR__ . '/wall.php';
  $sel = wall_user_select_sql($db);

  $sql = "SELECT u.id AS user_id, $sel, l.reaction
            FROM wall_likes l
            JOIN users u ON u.id = l.user_id
           WHERE l.entity_type=:t AND l.entity_id=:id
           ORDER BY l.id DESC";
  $st = $db->prepare($sql);
  $st->execute([':t'=>$type, ':id'=>$id]);
  return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Like-Button (Icon + Label)
 */
function wall_like_button_html(PDO $db, string $type, int $id, int $uid=0): string {
	hh_debug_log("UID=$uid, TYPE=$type, ID=$id => REACTION=" . wall_user_reaction($db,$uid,$type,$id));

  if ($uid <= 0 && !empty($_SESSION['user']['id'])) {
    $uid = (int)$_SESSION['user']['id'];
  }

  $reaction = null;
if ($uid > 0) {
    $reaction = wall_user_reaction($db, $uid, $type, $id);
    if (!$reaction) {
        // DEBUG-Ausgabe direkt ins HTML (nur zum Test!)
        $reaction = 'DEBUG-KEIN-EINTRAG';
    }
}

  $counts   = wall_reaction_count($db, $type, $id);
  $total    = array_sum($counts);

  $icons = wall_reaction_icons();
  $labels = [
    'like'  => 'Gefällt mir',
    'love'  => 'Love',
    'haha'  => 'Haha',
    'wow'   => 'Wow',
    'sad'   => 'Traurig',
    'angry' => 'Wütend',
  ];

  $r       = $reaction ?: '';
  $label   = $r ? ($labels[$r] ?? 'Gefällt mir') : 'Gefällt mir';
  $iconUrl = $r && isset($icons[$r]) ? $icons[$r] : $icons['like'];

  $html  = '<div class="like-bar" data-entity-type="'.htmlspecialchars($type).'" data-entity-id="'.(int)$id.'">';
  $html .= '<button class="btn-like flex items-center gap-2 text-base text-w-neutral-1'.($r ? ' is-reacted' : '').'"'
        . ' data-entity-type="'.htmlspecialchars($type).'"'
        . ' data-entity-id="'.(int)$id.'"'
        . ' data-reaction="'.htmlspecialchars($r).'"'
        . ' aria-pressed="'.($r ? 'true' : 'false').'">';
  $html .= '<span class="like-icon"><img src="'.htmlspecialchars($iconUrl).'" alt="'.htmlspecialchars($r ?: 'like').'" class="inline-block w-5 h-5"/></span>';
  $html .= '<span class="like-label">'.$label.'</span>';

  $html .= '</button>';

  $html .= '<div class="reactions-popup" aria-hidden="true">';
  foreach ($icons as $rr => $src) {
    $html .= '<button type="button" data-reaction="'.htmlspecialchars($rr).'">'
          .  '<img src="'.htmlspecialchars($src).'" alt="'.htmlspecialchars($rr).'" class="w-8 h-8"/>'
          .  '</button>';
  }
  $html .= '</div>';

  $html .= '</div>';
  return $html;
}

function wall_like_summary_html(PDO $db, string $type, int $id): string {
  $counts = wall_reaction_count($db, $type, $id);
  $total  = array_sum($counts);
  if ($total === 0) return '';

  $icons = wall_reaction_icons();
  arsort($counts);
  $top = array_slice(array_keys($counts), 0, 3);

  $html  = '<a href="#" class="btn-likers flex items-center gap-1"';
  $html .= ' data-entity-type="'.htmlspecialchars($type).'"';
  $html .= ' data-entity-id="'.$id.'">';

  foreach ($top as $r) {
    $src = $icons[$r] ?? '';
    if ($src) {
      $html .= '<img src="'.$src.'" alt="'.$r.'" class="w-4 h-4"/>';
    }
  }

  $html .= '<span class="likers-count ml-1">'.$total.'</span>';
  $html .= '</a>';

  return $html;
}

// Alias für Legacy
function wall_like_toggle(PDO $db, int $uid, string $type, int $id): array {
  $current = wall_user_reaction($db, $uid, $type, $id);
  $newReaction = ($current === 'like') ? '' : 'like';
  return wall_reaction_set($db, $uid, $type, $id, $newReaction);
}
