<?php
// /api/global_chat/cleanup.php
require_once __DIR__ . '/../../auth/db.php';
$db = db();
$del = $db->prepare("DELETE FROM global_chat_messages WHERE created_at < (NOW() - INTERVAL 24 HOUR)");
$del->execute();
header('Content-Type: application/json');
echo json_encode(["ok" => true, "deleted" => $del->rowCount()]);