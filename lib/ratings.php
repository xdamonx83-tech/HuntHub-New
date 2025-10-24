<?php
function hhr_get_summary(PDO $pdo, int $userId): array {
// Aggregat über alle Ratings für ratee_id = $userId
$sql = "
SELECT
COUNT(*) AS cnt,
AVG(play) AS avg_play,
AVG(friendly) AS avg_friendly,
AVG(helpful) AS avg_helpful,
AVG((play + friendly + helpful) / 3.0) AS avg_overall
FROM user_ratings
WHERE ratee_id = ?
";
$st = $pdo->prepare($sql);
$st->execute([$userId]);
$row = $st->fetch(PDO::FETCH_ASSOC) ?: [];


$cnt = (int)($row['cnt'] ?? 0);
$avg_play = $cnt ? (float)$row['avg_play'] : 0.0;
$avg_friendly = $cnt ? (float)$row['avg_friendly'] : 0.0;
$avg_helpful = $cnt ? (float)$row['avg_helpful'] : 0.0;
$avg_overall = $cnt ? (float)$row['avg_overall'] : 0.0;
$stars = $cnt ? hhr_round_stars($avg_overall) : 0;


// Verteilung 1..6 nach GERUNDETEM Einzelergebnis
$dist = [1=>0,2=>0,3=>0,4=>0,5=>0,6=>0];
if ($cnt) {
$sql2 = "
SELECT ROUND((play + friendly + helpful)/3.0) AS r, COUNT(*) AS c
FROM user_ratings
WHERE ratee_id = ?
GROUP BY r
";
$st2 = $pdo->prepare($sql2);
$st2->execute([$userId]);
while ($d = $st2->fetch(PDO::FETCH_ASSOC)) {
$r = (int)$d['r'];
if ($r < 1) $r = 1; if ($r > 6) $r = 6;
$dist[$r] = (int)$d['c'];
}
}


return [
'count' => $cnt,
'avg_play' => $avg_play,
'avg_friendly' => $avg_friendly,
'avg_helpful' => $avg_helpful,
'avg_overall_exact' => $avg_overall,
'stars_rounded' => $stars,
'dist' => $dist,
];
}


/**
* Liste der Einzelbewertungen für ein Profil (Ratee)
*/
function hhr_list_ratings(PDO $pdo, int $userId, int $offset = 0, int $limit = 10): array {
$limit = max(1, min(50, $limit));
$sql = "
SELECT r.id, r.rater_id, r.play, r.friendly, r.helpful, r.comment, r.created_at, r.updated_at,
u.display_name, u.rater_id, u.slug, u.avatar_path
FROM user_ratings r
JOIN users u ON u.id = r.rater_id
WHERE r.ratee_id = ?
ORDER BY r.updated_at DESC
LIMIT ? OFFSET ?
";
$st = $pdo->prepare($sql);
$st->bindValue(1, $userId, PDO::PARAM_INT);
$st->bindValue(2, $limit, PDO::PARAM_INT);
$st->bindValue(3, $offset, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];


foreach ($rows as &$row) {
$row['score_exact'] = hhr_score_single((int)$row['play'], (int)$row['friendly'], (int)$row['helpful']);
$row['score_stars'] = hhr_round_stars((float)$row['score_exact']);
}
return $rows;
}