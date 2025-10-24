<?php
declare(strict_types=1);
require_once __DIR__ . '/_util.php';

$me = lfg_require_auth();
$id = lfg_int($_POST['id'] ?? null, 1);

if (!$id) lfg_json(['ok'=>false,'error'=>'invalid_id'], 400);

// check ownership
$chk = $pdo->prepare("SELECT user_id FROM lfg_posts WHERE id=:id");
$chk->execute([':id'=>$id]);
$row = $chk->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['user_id'] !== (int)$me['id']) {
    lfg_json(['ok'=>false,'error'=>'not_owner'], 403);
}

$platform  = lfg_in($_POST['platform']  ?? 'pc',   ['pc','xbox','ps'], 'pc');
$region    = lfg_in($_POST['region']    ?? 'eu',   ['eu','na','sa','asia','oce'], 'eu');
$mmr       = lfg_int($_POST['mmr']      ?? null, 0, 10000);
$kd        = lfg_num($_POST['kd']       ?? null, 0.00, 99.99);
$weapon    = trim((string)($_POST['primary_weapon'] ?? ''));
$mode      = lfg_in($_POST['mode']      ?? 'bounty', ['bounty','clash','both'], 'bounty');
$playstyle = lfg_in($_POST['playstyle'] ?? 'balanced', ['offensive','defensive','balanced'], 'balanced');
$headset   = lfg_bool($_POST['headset'] ?? 1);
$languages = lfg_clean_csv($_POST['languages'] ?? '');
$timeslots = $_POST['timeslots'] ?? null;
$looking   = lfg_in($_POST['looking_for'] ?? 'any', ['solo','duo','trio','any'], 'any');
$notes     = trim((string)($_POST['notes'] ?? ''));
$visible   = lfg_bool($_POST['visible'] ?? 1);
$expires   = trim((string)($_POST['expires_at'] ?? ''));

if ($timeslots !== null) {
    json_decode((string)$timeslots);
    if (json_last_error() !== JSON_ERROR_NONE) $timeslots = null;
}

$sql = "UPDATE lfg_posts SET
 platform=:platform, region=:region, mmr=:mmr, kd=:kd, primary_weapon=:weapon,
 mode=:mode, playstyle=:playstyle, headset=:headset, languages=:languages,
 timeslots=:timeslots, looking_for=:looking, notes=:notes, visible=:visible, expires_at=:expires
WHERE id=:id";
$st = $pdo->prepare($sql);
$st->execute([
    ':platform'=>$platform, ':region'=>$region, ':mmr'=>$mmr, ':kd'=>$kd, ':weapon'=>$weapon ?: null,
    ':mode'=>$mode, ':playstyle'=>$playstyle, ':headset'=>$headset, ':languages'=>$languages,
    ':timeslots'=>$timeslots, ':looking'=>$looking, ':notes'=>$notes ?: null, ':visible'=>$visible,
    ':expires'=>$expires ?: null, ':id'=>$id
]);

lfg_json(['ok'=>true]);
