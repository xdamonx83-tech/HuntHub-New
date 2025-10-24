<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/guards.php';
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../lib/qa_ai.php';

$pdo = db();
$me  = function_exists('require_admin') ? (require_admin() ?: null) : (function_exists('current_user') ? current_user() : null);
if (!$me || empty($me['is_admin'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Admin required']); exit;
}

// -----------------------------
// Helpers
// -----------------------------

function http_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'HunthubBot/1.0 (+https://hunthub.online)',
    ]);
    $resp  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    return [(int)$code, (string)$resp, (string)$final];
}

function validate_url(string $u): string {
    $u = trim($u);
    if ($u === '' || preg_match('/[<>\s]/', $u)) return '';
    if (!preg_match('~^https?://~i', $u)) return '';
    if (!filter_var($u, FILTER_VALIDATE_URL)) return '';
    return $u;
}

function absolutize_url(string $base, string $rel): string {
    if ($rel === '') return '';
    if (preg_match('~^https?://~i', $rel)) return $rel;
    $p = parse_url($base);
    if (!$p) return $rel;
    $scheme = $p['scheme'] ?? 'https';
    $host   = $p['host'] ?? '';
    if (strpos($rel, '/') === 0) {
        return $scheme.'://'.$host.$rel;
    }
    $pathBase = $p['path'] ?? '/';
    if (substr($pathBase, -1) !== '/') $pathBase = rtrim(dirname($pathBase), '/').'/';
    return $scheme.'://'.$host.$pathBase.$rel;
}

function path_len(string $u): int {
    $p = parse_url($u);
    $path = (string)($p['path'] ?? '');
    return strlen($path);
}

/** Choose best document URL among original/final/canonical (avoid generic canonicals like /detailed). */
function choose_doc_url(string $original, string $final, string $canonical): string {
    $orig = $original;
    $fin  = $final ?: $original;
    $can  = $canonical ? absolutize_url($fin, $canonical) : '';

    $containsNews = (stripos($orig, '/news/') !== false) || (stripos($fin, '/news/') !== false) || (stripos($can, '/news/') !== false);

    // Prefer URLs that contain '/news/' when any candidate does.
    $cands = [];
    foreach ([$can, $fin, $orig] as $u) {
        if (!$u) continue;
        $score = 0;
        $score += path_len($u);              // longer path is probably an article
        if (stripos($u, '/news/') !== false) $score += 1000; // strong bias for /news/
        // penalize obvious generic canonicals
        if (preg_match('~/detailed/?$~i', $u)) $score -= 800;
        if (preg_match('~/news/?$~i', $u)) $score -= 500;
        $cands[$u] = $score;
    }
    arsort($cands);
    $best = key($cands);
    return $best ?: $fin;
}

function extract_text_from_html(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xp = new DOMXPath($dom);

    // remove noisy nodes
    foreach (['//script','//style','//nav','//footer','//aside'] as $q) {
        foreach ($xp->query($q) as $n) { $n->parentNode->removeChild($n); }
    }

    $title = '';
    $nodes = $xp->query('//title');
    if ($nodes && $nodes->length) $title = trim($nodes->item(0)->textContent);

    $canonical = '';
    $cn = $xp->query('//link[@rel="canonical"]/@href');
    if ($cn && $cn->length) $canonical = trim($cn->item(0)->nodeValue);

    $pieces = [];
    foreach ($xp->query('//h1|//h2|//h3|//p|//li') as $n) {
        $t = trim(preg_replace('/\s+/', ' ', $n->textContent ?? ''));
        if ($t !== '' && mb_strlen($t) > 30) $pieces[] = $t;
    }
    $text = implode("\n\n", $pieces);

    $is404 = preg_match('/(404|nicht gefunden|page not found|seems you got lost)/i', $title) === 1;
    return [$title, $text, $is404, $canonical];
}

function chunk_text(string $text, int $targetChars=1100): array {
    $parts = preg_split('/\n{2,}/', $text) ?: [];
    $out = [];
    $buf = '';
    foreach ($parts as $p) {
        if (mb_strlen($buf) + mb_strlen($p) + 2 > $targetChars && $buf !== '') {
            $out[] = $buf;
            $buf = $p;
        } else {
            $buf = $buf ? ($buf."\n\n".$p) : $p;
        }
    }
    if ($buf !== '') $out[] = $buf;
    return $out;
}

function col_exists(PDO $pdo, string $table, string $col): bool {
    $like = $pdo->quote($col);
    $sql  = "SHOW COLUMNS FROM `{$table}` LIKE " . $like;
    $st   = $pdo->query($sql);
    if ($st === false) return false;
    return (bool)$st->fetch();
}

function extract_links(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xp = new DOMXPath($dom);
    $out = [];
    foreach ($xp->query('//a[@href]') as $a) {
        /** @var DOMElement $a */
        $href = trim($a->getAttribute('href'));
        if ($href !== '') $out[] = $href;
    }
    return $out;
}

function upsert_source(PDO $pdo, string $url, string $lang): int {
    $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';
    $st = $pdo->prepare("INSERT INTO qa_sources (url, domain, lang, active) VALUES (?,?,?,1)
                         ON DUPLICATE KEY UPDATE domain=VALUES(domain), lang=VALUES(lang), active=1, id=LAST_INSERT_ID(id)");
    $st->execute([$url, $domain, $lang]);
    return (int)$pdo->lastInsertId();
}

function save_doc(PDO $pdo, int $source_id, string $url, string $title, string $lang, string $content): int {
    $hash = hash('sha256', $content);
    $st = $pdo->prepare("INSERT IGNORE INTO qa_docs (source_id, url, title, lang, fetched_at, content, version_hash)
                         VALUES (?,?,?,?,NOW(),?,?)");
    $st->execute([$source_id, $url, $title, $lang, $content, $hash]);
    $id = (int)$pdo->lastInsertId();
    if ($id === 0) {
        // Already exists -> update URL/title if the new one looks more specific
        $st2 = $pdo->prepare("SELECT id, url, title FROM qa_docs WHERE source_id=? AND version_hash=? ORDER BY id DESC LIMIT 1");
        $st2->execute([$source_id, $hash]);
        $row = $st2->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $id = (int)$row['id'];
            $oldUrl = (string)$row['url'];
            if (path_len($url) > path_len($oldUrl)) {
                $st3 = $pdo->prepare("UPDATE qa_docs SET url=?, title=? WHERE id=?");
                $st3->execute([$url, $title, $id]);
            }
        }
    }
    return $id;
}

function save_chunks_with_embeddings(PDO $pdo, int $doc_id, string $lang, array $chunks): int {
    $hasTok = col_exists($pdo, 'qa_chunks', 'token_count');
    $hasEmb = col_exists($pdo, 'qa_chunks', 'embedding');

    if ($hasTok && $hasEmb) {
        $sql = "INSERT IGNORE INTO qa_chunks (doc_id, lang, ord, text, token_count, embedding) VALUES (?,?,?,?,?,?)";
    } elseif ($hasEmb) {
        $sql = "INSERT IGNORE INTO qa_chunks (doc_id, lang, ord, text, embedding) VALUES (?,?,?,?,?)";
    } else {
        $sql = "INSERT IGNORE INTO qa_chunks (doc_id, lang, ord, text) VALUES (?,?,?,?)";
    }
    $ins = $pdo->prepare($sql);

    $count = 0; $ord = 0;
    foreach ($chunks as $ch) {
        $vec  = qa_embed($ch);
        $blob = ($hasEmb && $vec) ? qa_pack_f32($vec) : null;
        $tok  = (int)ceil(mb_strlen($ch)/4);

        if ($hasTok && $hasEmb) {
            $ins->execute([$doc_id, $lang, $ord++, $ch, $tok, $blob]);
        } elseif ($hasEmb) {
            $ins->execute([$doc_id, $lang, $ord++, $ch, $blob]);
        } else {
            $ins->execute([$doc_id, $lang, $ord++, $ch]);
        }
        $count++;
    }
    return $count;
}

// -----------------------------
// Input
// -----------------------------
$url  = trim($_POST['url'] ?? $_GET['url'] ?? '');
$url  = validate_url($url);
$all  = isset($_POST['all']) || isset($_GET['all']);
$discover = isset($_POST['discover']) || isset($_GET['discover']);
$lang = $_POST['lang'] ?? $_GET['lang'] ?? (function_exists('detect_lang') ? detect_lang() : 'de');
$lang = in_array($lang, ['de','en'], true) ? $lang : 'de';

$imported = [];

// -----------------------------
// Modes
// -----------------------------
if ($url && $discover) {
    // 1) Discovery
    $pat = $_GET['pat'] ?? $_POST['pat'] ?? '/news/';
    $max = max(1, min(20, (int)($_GET['max'] ?? $_POST['max'] ?? 8)));
    [$code, $html, $final] = http_get($url);
    if ($code >= 400 || !$html) { echo json_encode(['ok'=>false,'message'=>'Fetch failed (HTTP '.$code.')']); exit; }
    $links = array_unique(extract_links($html));
    $base = $final ?: $url;
    $host = parse_url($base, PHP_URL_HOST) ?: '';

    $picked = [];
    foreach ($links as $href) {
        $abs = absolutize_url($base, $href);
        if (parse_url($abs, PHP_URL_HOST) !== $host) continue;
        if ($pat && strpos($abs, $pat) === false) continue;
        $picked[] = $abs;
        if (count($picked) >= $max) break;
    }

    $sid = upsert_source($pdo, $url, $lang);
    foreach ($picked as $link) {
        [$c2, $h2, $f2] = http_get($link);
        if ($c2 >= 400 || !$h2) continue;
        [$title, $text, $is404, $canonical] = extract_text_from_html($h2);
        if ($is404 || mb_strlen($text) < 200) continue;
        $doc_url = choose_doc_url($link, $f2, $canonical);
        $doc_id  = save_doc($pdo, $sid, $doc_url, $title, $lang, $text);
        $chunks  = chunk_text($text, 1100);
        $n       = save_chunks_with_embeddings($pdo, $doc_id, $lang, $chunks);
        $imported[] = ['url'=>$doc_url, 'title'=>$title, 'chunks'=>$n];
    }
} elseif ($url) {
    // 2) Single URL
    [$code, $html, $final] = http_get($url);
    if ($code >= 400 || !$html) { echo json_encode(['ok'=>false,'message'=>'Fetch failed (HTTP '.$code.')']); exit; }
    [$title, $text, $is404, $canonical] = extract_text_from_html($html);
    if ($is404) { echo json_encode(['ok'=>false,'message'=>'Page looks like 404/Not Found']); exit; }
    if (mb_strlen($text) < 200) { echo json_encode(['ok'=>false,'message'=>'Too little text']); exit; }
    $sid     = upsert_source($pdo, $url, $lang);
    $doc_url = choose_doc_url($url, $final, $canonical);
    $doc_id  = save_doc($pdo, $sid, $doc_url, $title, $lang, $text);
    $chunks  = chunk_text($text, 1100);
    $n       = save_chunks_with_embeddings($pdo, $doc_id, $lang, $chunks);
    $imported[] = ['url'=>$doc_url, 'title'=>$title, 'chunks'=>$n];
} elseif ($all) {
    // 3) All seeds
    $rs = $pdo->query("SELECT id,url,lang FROM qa_sources WHERE active=1 ORDER BY priority DESC");
    while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
        $sid  = (int)$row['id'];
        $surl = (string)$row['url'];
        $slang= (string)$row['lang'];
        [$code, $html, $final] = http_get($surl);
        if ($code >= 400 || !$html) continue;
        [$title, $text, $is404, $canonical] = extract_text_from_html($html);
        if ($is404 || mb_strlen($text) < 200) continue;
        $doc_url = choose_doc_url($surl, $final, $canonical);
        $doc_id  = save_doc($pdo, $sid, $doc_url, $title, $slang, $text);
        $chunks  = chunk_text($text, 1100);
        $n       = save_chunks_with_embeddings($pdo, $doc_id, $slang, $chunks);
        $imported[] = ['url'=>$doc_url, 'title'=>$title, 'chunks'=>$n];
    }
} else {
    echo json_encode(['ok'=>false,'message'=>'Use ?url=... or ?all=1']); exit;
}

// -----------------------------
echo json_encode(['ok'=>true,'imported'=>$imported], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
