<?php
declare(strict_types=1);

/**
 * Hunthub QA – Smalltalk + FAQ-first + RAG
 * Rückgabe immer valides JSON. Keine PHP-Warnings in der Ausgabe.
 */

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/qa_ai.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ---------- helpers ---------- */
function json_out(array $payload, int $code = 200): void {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function nv(string $s): string { return preg_replace('/\b2\.(\d)\b/u','2$1',$s); } // "2.4"->"24"
function de2en(string $q): string {
  $s = mb_strtolower($q);
  $map = ['welches'=>'','welcher'=>'','welche'=>'','läuft'=>'current','aktuell'=>'current','momentan'=>'current','derzeit'=>'current','event'=>'event','ereignis'=>'event','gauntlet'=>'gauntlet','update'=>'update','patch'=>'update','hotfix'=>'hotfix','letzten'=>'last','letzte'=>'last','zuletzt'=>'last','was wurde'=>'what changed','gemacht'=>''];
  $s = strtr($s,$map); $s = nv($s); $s = preg_replace('/\s+/', ' ', trim($s));
  return $s ?: $q;
}
function badurl(string $u): bool {
  $u = strtolower($u);
  return $u==='' || str_contains($u,'/detailed') || str_contains($u,'/tagged/')
      || str_contains($u,'#stickynav') || preg_match('~/(news|blog)/?$~',$u);
}
function normtext(string $t): string {
  $t = mb_strtolower($t);
  $t = preg_replace('/\s+/', ' ', $t);
  $t = preg_replace('/[^\p{L}\p{N}\s]/u','', $t);
  return trim($t);
}
function extract_update_ver(string $q): ?int {
  if (preg_match('~\b2\.(\d{1,2})\b~', $q, $m)) return (int)('2'.$m[1]);  // 2.1 -> 21
  if (preg_match('~\b2(\d{1,2})\b~',    $q, $m)) return (int)('2'.$m[1]);  // 21  -> 21
  if (preg_match('~\bupdate\s*(?:2[\.\-:]?(\d{1,2})|(\d{2}))\b~i', $q, $m)) {
    $minor = $m[1] ?: (($m[2] && str_starts_with($m[2],'2')) ? substr($m[2],1) : null);
    return $minor ? (int)('2'.$minor) : null;
  }
  return null;
}
function ver_matches(string $title, string $url, int $ver): bool {
  $minor = substr((string)$ver, 1);
  $hay = strtolower($title.' '.$url);
  return (bool)preg_match("~update[^0-9]*(?:{$ver}|2[\\.:\\-_ ]?{$minor})\\b~i", $hay);
}

/* ---------- smalltalk ---------- */
function is_smalltalk(string $q): bool {
  $q = mb_strtolower($q);
  if (preg_match('~\b(hallo|hi|hey|moin|servus|guten (morgen|tag|abend)|wie gehts|wie geht\'s|wie geht es dir|alles klar|was geht|danke|thx|tsch(ö|o)ss|tschüs+|ciao|wer bist du|was kannst du)\b~u', $q)) return true;
  if (preg_match('~\b(hello|hi|hey|how are you|who are you|what can you do|thanks|thank you|bye|good (morning|evening)|see you)\b~i', $q)) return true;
  return false;
}
function smalltalk_reply(string $q, string $lang): string {
  $q_lc = mb_strtolower($q);
  if (preg_match('~(wer bist du|who are you)~u', $q_lc)) {
    return $lang==='de'
      ? "Ich bin der **Hunthub-Assistent** – ich helfe bei Fragen zu **Hunt: Showdown** und **Hunthub** (Registrierung, Regeln, Uploads, Datenschutz). Frag einfach los! 🙂"
      : "I'm the **Hunthub assistant** — I can help with **Hunt: Showdown** and **Hunthub** (registration, rules, uploads, privacy). Ask me anything! 🙂";
  }
  if (preg_match('~(danke|thx|thanks)~u', $q_lc))  return $lang==='de' ? "Gern geschehen! ✌️" : "You're welcome! ✌️";
  if (preg_match('~(tsch(ö|o)ss|tschüs+|ciao|bye|see you)~u', $q_lc)) return $lang==='de' ? "Bis bald 👋" : "See you soon 👋";

  $de = [
    "Mir geht’s gut – bereit für Fragen zu Hunt & Hunthub! 😊 Wobei kann ich helfen?",
    "Alles super hier. Frag mich gern zu Updates, Events oder Hunthub-Funktionen. 🚀"
  ];
  $en = [
    "I'm doing well — ready for Hunt & Hunthub questions! 😊 How can I help?",
    "All good here. Ask me about updates, events or Hunthub features. 🚀"
  ];
  $arr = $lang==='de' ? $de : $en;
  return $arr[array_rand($arr)];
}

/* ---------- inputs ---------- */
$pdo  = db();
$q    = trim((string)($_POST['q'] ?? $_GET['q'] ?? ''));
$lang = $_POST['lang'] ?? $_GET['lang'] ?? (function_exists('detect_lang') ? detect_lang() : 'de');
$lang = in_array($lang, ['de','en'], true) ? $lang : 'de';
$style = strtolower(trim((string)($_POST['style'] ?? $_GET['style'] ?? 'bullets'))); // bullets|paragraph
$scope = $_POST['scope'] ?? $_GET['scope'] ?? ''; // '' | 'hunthub'
$user = function_exists('optional_auth') ? optional_auth() : (function_exists('current_user') ? current_user() : null);

if ($q === '') json_out(['ok'=>false,'message'=>'Keine Frage übergeben.'], 400);

/* ---------- smalltalk early return ---------- */
if (is_smalltalk($q)) {
  $msg = smalltalk_reply($q, $lang);

  $_SESSION['hhqa_hist'][] = ['role'=>'user','content'=>$q];
  $_SESSION['hhqa_hist'][] = ['role'=>'assistant','content'=>$msg];
  $_SESSION['hhqa_hist']   = array_slice($_SESSION['hhqa_hist'] ?? [], -12);

  json_out([
    'ok' => true,
    'answer' => [
      'html'        => nl2br(htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')),
      'citations'   => [],
      'confidence'  => 0.99,
      'style'       => $style,
      'mode'        => 'chat',
    ],
  ]);
}

/* ---------- faq first ---------- */
function hh_table_exists(PDO $pdo, string $name): bool {
  try {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) { return false; }
}

$q_lc = mb_strtolower($q);
$autoHunthub = (bool)preg_match('~\b(hunthub|konto|registrier|login|einloggen|regeln|moderation|chat|datenschutz|privacy|impressum|support|kontakt|upload|videos?|bilder?|profil|account|löschen|delete)\b~i', $q_lc);
$faqHit = null;

if (hh_table_exists($pdo, 'qa_faq')) {
  try {
    $sql = "SELECT answer_html FROM qa_faq
            WHERE lang=:lang AND (
              MATCH(question,tags,answer_html) AGAINST (:qq IN NATURAL LANGUAGE MODE)
              OR question LIKE :like OR tags LIKE :like
            )
            ORDER BY priority ASC LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':lang'=>$lang, ':qq'=>$q_lc, ':like'=>'%'.$q_lc.'%']);
    $faqHit = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { /* ignore */ }

  if (!$faqHit) {
    try {
      $sql = "SELECT answer_html FROM qa_faq
              WHERE lang=:lang AND (LOWER(question) LIKE :like OR LOWER(tags) LIKE :like)
              ORDER BY priority ASC LIMIT 1";
      $st = $pdo->prepare($sql);
      $st->execute([':lang'=>$lang, ':like'=>'%'.$q_lc.'%']);
      $faqHit = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) { $faqHit = null; }
  }
}

if ($faqHit && !empty($faqHit['answer_html'])) {
  json_out([
    'ok' => true,
    'answer' => [
      'html'        => (string)$faqHit['answer_html'],
      'citations'   => [],
      'confidence'  => 0.99,
      'style'       => $style,
      'mode'        => 'faq',
    ],
  ]);
}

/* ---------- RAG ---------- */
function kb_search(PDO $pdo, string $q, ?string $lang=null, int $limit=80): array {
  $filters = " AND d.url NOT LIKE '%/detailed%' AND d.url NOT LIKE '%/tagged/%'
               AND d.url NOT LIKE '%#stickynav%' AND c.text NOT REGEXP '(404|Whoooops|Seite wurde nicht gefunden|page you are looking for)' ";
  $cols = "c.id, c.doc_id, c.text, c.lang, d.title, d.url, d.fetched_at,
           MATCH(c.text) AGAINST (:q IN NATURAL LANGUAGE MODE) AS bm25";
  if ($lang) {
    $sql = "SELECT {$cols} FROM qa_chunks c JOIN qa_docs d ON d.id=c.doc_id
            WHERE c.lang=:lang {$filters} HAVING bm25>0
            ORDER BY bm25 DESC LIMIT {$limit}";
    $st = $pdo->prepare($sql); $st->execute([':q'=>$q, ':lang'=>$lang]);
  } else {
    $sql = "SELECT {$cols} FROM qa_chunks c JOIN qa_docs d ON d.id=c.doc_id
            WHERE 1 {$filters} HAVING bm25>0
            ORDER BY bm25 DESC LIMIT {$limit}";
    $st = $pdo->prepare($sql); $st->execute([':q'=>$q]);
  }
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

$qe        = qa_embed($q);
$used_norm = false;
$q_norm    = nv($q);

$list = kb_search($pdo, $q_norm, $lang, 80);
if (!$list) $list = kb_search($pdo, $q_norm, null, 80);

if (!$list && $lang==='de' && (!$qe || count($qe)===0)) {
  $q2 = de2en($q);
  if ($q2 !== $q) { $list = kb_search($pdo, $q2, 'en', 80); $used_norm=true; $q_norm=$q2; }
}

if (!$list) json_out(['ok'=>false,'message'=>($lang==='de'?'Keine passenden Quellen gefunden.':'No matching sources found.')], 404);

$targetVer    = extract_update_ver($q_norm);
$preferDomain = ($scope === 'hunthub' || $autoHunthub) ? 'hunthub.online' : null;

$scored=[]; 
$kw_update = preg_match('/\b(update|patch|hotfix|2[0-9]|\d+\.?\d*)/i',$q_norm);
$kw_event  = preg_match('/\b(event|gauntlet|festival|murder circus)\b/i',$q_norm);

foreach ($list as $row){
  $url=(string)$row['url']; if (badurl($url)) continue;
  $bm25=(float)($row['bm25']??0); $sim=0.0;

  if ($qe){
    $st=$pdo->prepare("SELECT embedding FROM qa_chunks WHERE id=?");
    $st->execute([$row['id']]);
    $blob=(string)($st->fetchColumn()?:'');
    if ($blob!==''){ $vec=qa_unpack_f32($blob); $sim=qa_dot($qe,$vec); }
  }

  $age=365.0; if(!empty($row['fetched_at'])) $age=max(0.0,(time()-strtotime($row['fetched_at']))/86400.0);
  $rec = max(0.0,(540.0-$age)/540.0);

  $title=strtolower((string)($row['title']??'')); $url_l=strtolower($url);

  $kw=0.0;
  if($kw_update && (str_contains($title,'update')||str_contains($url_l,'update')||str_contains($title,'patch'))) $kw+=1.2;
  if($kw_event  && (str_contains($title,'event') ||str_contains($url_l,'event') ||str_contains($title,'gauntlet'))) $kw+=1.0;
  if($preferDomain && str_contains($url_l, $preferDomain)) $kw+=2.5;

  $verBoost = 0.0;
  if ($targetVer) {
    if (ver_matches((string)$row['title'], $url, $targetVer)) $verBoost = 3.5;
    else $verBoost = -1.8;
  }

  $row['bm25']=$bm25;
  $row['score']=$bm25 + ($qe?($sim*0.1):0) + $rec + $kw + $verBoost;
  $scored[]=$row;
}

usort($scored, fn($a,$b)=>($b['score']<=>$a['score']));

if ($targetVer) {
  $scoredOnly = array_values(array_filter($scored, fn($r)=>ver_matches((string)$r['title'], (string)$r['url'], $targetVer)));
  if ($scoredOnly) $scored = $scoredOnly;
}

$best_per_doc=[]; foreach($scored as $r){ $doc=(int)$r['doc_id']; if(!isset($best_per_doc[$doc])) $best_per_doc[$doc]=$r; }
$scored = array_values($best_per_doc);

if (!$scored || (float)$scored[0]['bm25']<0.35) {
  json_out(['ok'=>false,'message'=>($lang==='de'?'Keine verlässliche Grundlage gefunden.':'No reliable basis found.')], 404);
}

/* Dedupe + Top N */
$hashes=[]; $top=[];
foreach($scored as $r){
  $h=md5(substr(normtext((string)$r['text']),0,900));
  if(isset($hashes[$h])) continue; $hashes[$h]=true; $top[]=$r; if(count($top)>=4) break;
}

/* Kontext zusammenbauen (und vorsichtig deckeln) */
$citations=[]; $context='';
foreach($top as $i=>$t){
  $url=(string)($t['url']??''); if($url) $citations[]=$url;
  $snippet = trim($t['text']);
  $context.="Snippet ".($i+1).": ".($t['title']?($t['title']." — "):"").$url."\n---\n".$snippet."\n\n";
}
$context = trim($context);
if (mb_strlen($context) > 8000) $context = mb_substr($context, 0, 8000).'…';

/* kurzer Verlauf (max 8 Zeilen), schmalisieren */
$historyTxt = '';
if (!empty($_SESSION['hhqa_hist'])) {
  $last = array_slice($_SESSION['hhqa_hist'], -8);
  foreach ($last as $m) {
    $role = strtoupper($m['role']);
    $content = trim(preg_replace('~\s+~u',' ', (string)$m['content']));
    $historyTxt .= "{$role}: ".mb_substr($content,0,300)."\n";
  }
}

$system = ($lang==='de')
  ? "Du bist der freundliche Hunthub-Assistent. Antworte natürlich, knapp und korrekt.
     Wenn Quellen-Kontext vorhanden ist, fasse präzise zusammen und hänge 'Quellen' an.
     Bei Smalltalk bitte locker antworten – ohne Quellen. Übersetze 'Hunter' niemals."
  : "You are the friendly Hunthub assistant. Be natural, concise and correct.
     If context (sources) is present, summarize precisely and cite them.
     For small talk, reply casually — without sources. Never translate 'Hunter'.";

if ($style==='bullets') {
  $task_de = "Gib zuerst eine **fette 1-Zeilen-Zusammenfassung (tl;dr)**. Danach **3–6 Bulletpoints** mit den wichtigsten Fakten (kurze Punkte, keine Füllsätze). Keine Zitate, keine Spekulationen. Am Ende 'Quellen:' + URLs.";
  $task_en = "Give a **bold one-line tl;dr** followed by **3–6 bullet points** with key facts (short bullets, no fluff). No quotes, no speculation. End with 'Sources:' + URLs.";
} else {
  $task_de = "Fasse die Fakten prägnant in 4–7 Sätzen auf Deutsch zusammen. Keine Zitate, keine Spekulationen. Am Ende 'Quellen:' + URLs.";
  $task_en = "Summarize the facts in 4–7 concise sentences in English. No quotes, no speculation. End with 'Sources:' + URLs.";
}

$prompt = ($lang==='de')
  ? "Rolle: Du bist freundlich, knapp und hilfsbereit.\n"
    . "Chatverlauf (Auszug):\n{$historyTxt}\n"
    . "Frage: {$q}".($used_norm?" (normalisiert aus: {$q_norm})":"")
    . "\n\nKontext (Quellen-Snippets):\n{$context}\n\nAufgabe: {$task_de}"
  : "Role: You are friendly, concise and helpful.\n"
    . "Chat history (excerpt):\n{$historyTxt}\n"
    . "Question: {$q}\n\nContext (source snippets):\n{$context}\n\nTask: {$task_en}";

$answer = '';
try {
  $answer = qa_llm_answer($prompt, $system);
} catch (Throwable $e) {
  json_out(['ok'=>false,'message'=>'LLM nicht erreichbar'], 502);
}

/* Fallback */
$confidence=0.0;
if ($answer==='' || strlen(trim($answer))<30){
  if ($style==='bullets') {
    $parts = array_filter(array_map('trim', preg_split('/\n+/', strip_tags($context))));
    $bul = array_slice($parts,0,5);
    $tl = $bul ? $bul[0] : 'Kurzfassung nicht verfügbar';
    $answer = "**tl;dr:** ".$tl."\n\n• " . implode("\n• ", array_map(fn($x)=>mb_substr($x,0,160).'…', $bul));
  } else {
    $answer = mb_substr(strip_tags($context),0,800).'…';
  }
  $confidence = 0.6;
} else {
  $confidence = 0.85;
}

/* HTML + Quellen */
$html  = nl2br($answer);
$cites = array_values(array_unique(array_filter($citations)));
if ($cites) {
  $links = array_map(
    fn($u)=>'<a href="'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'" target="_blank" rel="nofollow noopener">'.htmlspecialchars($u,ENT_QUOTES,'UTF-8').'</a>',
    $cites
  );
  $html .= '<div class="mt-8 text-sm opacity-70">'.($lang==='de'?'Quellen: ':'Sources: ').implode(' • ', $links).'</div>';
}

/* Verlauf aktualisieren */
$plain = strip_tags($answer);
$_SESSION['hhqa_hist'][] = ['role'=>'user','content'=>$q];
$_SESSION['hhqa_hist'][] = ['role'=>'assistant','content'=>$plain];
$_SESSION['hhqa_hist']   = array_slice($_SESSION['hhqa_hist'], -12);

/* Log */
$logId = null;
try {
  $st = $pdo->prepare("INSERT INTO qa_logs (user_id, lang, question, answer, citations, confidence) VALUES (?,?,?,?,?,?)");
  $st->execute([$user['id']??null, $lang, $q, $html, json_encode($cites, JSON_UNESCAPED_SLASHES), $confidence]);
  $logId = (int)$pdo->lastInsertId();
} catch(Throwable $e){}


/* OK */
json_out([
  'ok'=>true,
  'answer'=>[
    'html'=>$html,
    'citations'=>$cites,
    'confidence'=>$confidence,
    'style'=>$style,
    'mode'=>'rag',
	'log_id'=>$logId 
  ]
]);
