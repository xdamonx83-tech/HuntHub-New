<?php
declare(strict_types=1);
require_once __DIR__ . '/../auth/session_bootstrap.php';
header('Content-Type: text/plain');
echo "session_name: " . session_name() . PHP_EOL;
echo "cookies:\n"; var_export($_COOKIE); echo PHP_EOL.PHP_EOL;
echo "\$_SESSION:\n"; var_export($_SESSION);