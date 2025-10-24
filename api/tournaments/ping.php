<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';


try {
$me = require_admin_user();
json_ok(['pong'=>true,'user_id'=>(int)($me['id'] ?? 0)]);
} catch (Throwable $e) {
json_err('auth_or_routing', 401, ['detail'=>$e->getMessage()]);
}


/* ====== /api/tournaments/create.php ==================================== */
?>