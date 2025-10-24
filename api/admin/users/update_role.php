<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$id   = (int)($in['id'] ?? 0);
$role = strtolower(trim((string)($in['role'] ?? '')));
if ($id<=0 || !in_array($role, ['user','mod','admin'], true)) {
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>'bad_params']); exit;
}
$st = $pdo->prepare("UPDATE users SET role=:r, is_admin = IF(:r='admin',1,is_admin) WHERE id=:id");
$st->execute([':r'=>$role, ':id'=>$id]);
echo json_encode(['ok'=>true]);
