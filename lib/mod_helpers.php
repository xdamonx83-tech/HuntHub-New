<?php
function require_not_muted(PDO $pdo, int $userId, string $scope = 'forum'): void {
  // Falls du mod_require_not_muted() schon hast, nimm die:
  if (function_exists('mod_require_not_muted')) { mod_require_not_muted($pdo, $userId, $scope); return; }

  // Fallback: direkter SQL-Check gegen moderation_mutes
  $sql = "SELECT reason, muted_until
          FROM moderation_mutes
          WHERE user_id=? AND active=1 AND muted_until > NOW()
            AND (scope='all' OR scope=?)
          ORDER BY muted_until DESC LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$userId, $scope]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    http_response_code(423); // locked
    echo json_encode([
      'ok'          => false,
      'error'       => 'muted',
      'scope'       => $scope,
      'reason'      => (string)($row['reason'] ?? ''),
      'muted_until' => (string)($row['muted_until'] ?? '')
    ]);
    exit;
  }
}
