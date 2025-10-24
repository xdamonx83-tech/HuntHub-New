<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../auth/db.php';

try {
    $db = db();
    $rows = $db->query("SELECT NOW() as t")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["ok"=>true,"rows"=>$rows]);
} catch (Throwable $e) {
    echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}