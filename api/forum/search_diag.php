<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$report = [
  'php_version' => PHP_VERSION,
  'steps' => [],
];

function step(&$report, $name, callable $fn){
  try {
    $val = $fn();
    $report['steps'][] = ['name'=>$name, 'ok'=>true, 'data'=>$val];
  } catch (Throwable $e) {
    $report['steps'][] = ['name'=>$name, 'ok'=>false, 'error'=>$e->getMessage(), 'file'=>$e->getFile(), 'line'=>$e->getLine()];
    echo json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
  }
}

/* 1) Includes */
step($report, 'require auth/db/guards', function(){
  require_once __DIR__ . '/../../auth/auth.php';
  require_once __DIR__ . '/../../auth/db.php';
  require_once __DIR__ . '/../../auth/guards.php';
  return ['included'=>true];
});

/* 2) DB connect */
step($report, 'db connect + attributes', function(){
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return ['driver'=>$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)];
});

/* 3) Tabellen vorhanden? */
step($report, 'check tables', function(){
  $pdo = db();
  $tables = [];
  foreach (['threads','posts'] as $t){
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t));
    $tables[$t] = $stmt->fetchColumn() ? 'present' : 'missing';
  }
  return $tables;
});

/* 4) Spalten vorhanden? */
step($report, 'check columns', function(){
  $pdo = db();
  $cols = [];
  foreach (['threads'=>['id','title'], 'posts'=>['id','thread_id','content']] as $t=>$expected){
    $stmt = $pdo->query("SHOW COLUMNS FROM `$t`");
    $have = array_map(fn($r)=>$r['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    $cols[$t] = ['expected'=>$expected, 'have'=>$have];
  }
  return $cols;
});

/* 5) Simple LIKE Probes */
step($report, 'probe like queries', function(){
  $pdo = db();
  $like = '%test%';
  $r1 = $pdo->prepare("SELECT id,title FROM threads WHERE title LIKE :q ORDER BY id DESC LIMIT 1");
  $r1->execute([':q'=>$like]);
  $r2 = $pdo->prepare("SELECT id,thread_id,content FROM posts WHERE content LIKE :q ORDER BY id DESC LIMIT 1");
  $r2->execute([':q'=>$like]);
  return [
    'threads_like_example' => $r1->fetch(PDO::FETCH_ASSOC),
    'posts_like_example'   => $r2->fetch(PDO::FETCH_ASSOC),
  ];
});

echo json_encode($report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
