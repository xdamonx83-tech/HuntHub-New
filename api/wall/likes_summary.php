<?php
}
require_once __DIR__ . '/../../lib/hh_boot.php';


$postIds = array_values(array_unique($postIds));
$commentIds = array_values(array_unique($commentIds));


$uid = (int)$user['id'];
$result = ['ok' => true, 'posts' => new stdClass(), 'comments' => new stdClass()];


// Helper to build IN list
$inList = function(array $ids): string {
if (!$ids) return '(NULL)';
return '(' . implode(',', array_map('intval', $ids)) . ')';
};


// Posts summary
if ($postIds) {
// counts
$sqlC = "SELECT entity_id AS id, COUNT(*) AS c FROM wall_likes WHERE entity_type='post' AND entity_id IN " . $inList($postIds) . " GROUP BY entity_id";
$counts = [];
foreach ($db->query($sqlC) as $row) { $counts[(int)$row['id']] = (int)$row['c']; }


// liked by user
$sqlL = "SELECT entity_id AS id FROM wall_likes WHERE entity_type='post' AND user_id=:uid AND entity_id IN " . $inList($postIds);
$stmtL = $db->prepare($sqlL);
$stmtL->execute([':uid' => $uid]);
$liked = [];
foreach ($stmtL as $row) { $liked[(int)$row['id']] = true; }


$out = [];
foreach ($postIds as $id) {
$out[$id] = ['count' => $counts[$id] ?? 0, 'liked' => (bool)($liked[$id] ?? false)];
}
$result['posts'] = $out;
}


// Comments summary
if ($commentIds) {
$sqlC = "SELECT entity_id AS id, COUNT(*) AS c FROM wall_likes WHERE entity_type='comment' AND entity_id IN " . $inList($commentIds) . " GROUP BY entity_id";
$counts = [];
foreach ($db->query($sqlC) as $row) { $counts[(int)$row['id']] = (int)$row['c']; }


$sqlL = "SELECT entity_id AS id FROM wall_likes WHERE entity_type='comment' AND user_id=:uid AND entity_id IN " . $inList($commentIds);
$stmtL = $db->prepare($sqlL);
$stmtL->execute([':uid' => $uid]);
$liked = [];
foreach ($stmtL as $row) { $liked[(int)$row['id']] = true; }


$out = [];
foreach ($commentIds as $id) {
$out[$id] = ['count' => $counts[$id] ?? 0, 'liked' => (bool)($liked[$id] ?? false)];
}
$result['comments'] = $out;
}


echo json_encode($result);
} catch (Throwable $e) {
http_response_code(500);
echo json_encode(['ok' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}