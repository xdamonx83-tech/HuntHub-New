<?php
declare(strict_types=1);

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $cfg = require __DIR__ . '/config.php';
  $pdo = new PDO($cfg['db']['dsn'], $cfg['db']['user'], $cfg['db']['pass'], $cfg['db']['options']);
  return $pdo;
}
