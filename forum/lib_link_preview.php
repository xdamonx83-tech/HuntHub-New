<?php
declare(strict_types=1);

/**
 * URLs aus HTML holen (a[href] + Fallback auf nackte URLs im Text).
 */
function lp_extract_urls(string $html): array {
  $urls = [];

  // a[href]
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  if ($dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOWARNING|LIBXML_NOERROR)) {
    $as = $dom->getElementsByTagName('a');
    foreach ($as as $a) {
      $href = trim((string)$a->getAttribute('href'));
      if ($href !== '' && preg_match('~^https?://~i', $href)) $urls[] = $href;
    }
  }
  libxml_clear_errors();

  // nackte URLs (einfacher Fallback)
  if (preg_match_all('~\bhttps?://[^\s<>"\']+~i', $html, $m)) {
    $urls = array_merge($urls, $m[0]);
  }

  // deduplizieren + auf max. 3 begrenzen
  $urls = array_values(array_unique($urls));
  return array_slice($urls, 0, 3);
}

/**
 * Holt OG/Twitter/HTML-Meta. Lädt nur bis ~300kB.
 */
function lp_fetch_meta(string $url, int $timeoutSec = 4): array {
  $ua = 'LinkPreviewBot/1.0 (+https://example.com)';
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_CONNECTTIMEOUT => $timeoutSec,
    CURLOPT_TIMEOUT        => $timeoutSec,
    CURLOPT_USERAGENT      => $ua,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    // wir stoppen nach 300kB
    CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$buf) {
      static $len = 0;
      $buf .= $data;
      $len += strlen($data);
      return ($len > 300*1024) ? 0 : strlen($data); // abbrechen
    },
  ]);
  $buf = '';
  $ok  = curl_exec($ch);
  curl_close($ch);
  if ($ok === false || $buf === '') return [];

  $site = null; $title=null; $desc=null; $img=null;

  // sehr einfache OG-Extraktion
  $getMeta = function($prop, $name=null) use ($buf) {
    $p = preg_quote($prop, '~');
    if (preg_match('~<meta[^>]+property=["\']'.$p.'["\'][^>]*content=["\']([^"\']+)["\']~i', $buf, $m)) return html_entity_decode($m[1], ENT_QUOTES|ENT_HTML5);
    if ($name && preg_match('~<meta[^>]+name=["\']'.preg_quote($name,'~').'["\'][^>]*content=["\']([^"\']+)["\']~i', $buf, $m)) return html_entity_decode($m[1], ENT_QUOTES|ENT_HTML5);
    return null;
  };

  $site  = $getMeta('og:site_name');
  $title = $getMeta('og:title')       ?: $getMeta('twitter:title') ?: null;
  $desc  = $getMeta('og:description') ?: $getMeta('twitter:description') ?: null;
  $img   = $getMeta('og:image')       ?: $getMeta('twitter:image') ?: null;

  if (!$title && preg_match('~<title[^>]*>(.*?)</title>~is', $buf, $m)) {
    $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES|ENT_HTML5));
  }
  if (!$desc && preg_match('~<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']+)["\']~i', $buf, $m)) {
    $desc = html_entity_decode($m[1], ENT_QUOTES|ENT_HTML5);
  }

  // relative Bild-URL auflösen
  if ($img && !preg_match('~^https?://~i', $img)) {
    $parts = parse_url($url);
    if (!empty($parts['scheme']) && !empty($parts['host'])) {
      $base = $parts['scheme'].'://'.$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
      if (isset($img[0]) && $img[0] === '/') $img = $base.$img;
      else $img = rtrim($base,'/').'/'.ltrim($img,'/');
    }
  }

  return [
    'site_name'   => $site,
    'title'       => $title,
    'description' => $desc,
    'image_url'   => $img,
  ];
}

/**
 * Speichert Previews zu einem Post. Idempotent pro URL.
 */
function lp_store_previews(PDO $pdo, int $postId, string $html): void {
  $urls = lp_extract_urls($html);
  if (!$urls) return;

  $ins = $pdo->prepare("
    INSERT INTO link_previews (post_id, url, url_hash, site_name, title, description, image_url)
    VALUES (:pid,:url,:hash,:site,:title,:desc,:img)
    ON DUPLICATE KEY UPDATE
      site_name=VALUES(site_name),
      title=VALUES(title),
      description=VALUES(description),
      image_url=VALUES(image_url),
      fetched_at=NOW()
  ");

  foreach ($urls as $u) {
    $meta = lp_fetch_meta($u);
    if (!$meta) continue;
    $ins->execute([
      ':pid'  => $postId,
      ':url'  => $u,
      ':hash' => sha1($u),
      ':site' => $meta['site_name'],
      ':title'=> $meta['title'],
      ':desc' => $meta['description'],
      ':img'  => $meta['image_url'],
    ]);
  }
}

/** Previews für Rendering holen */
function lp_get_previews(PDO $pdo, int $postId): array {
  $s = $pdo->prepare("SELECT * FROM link_previews WHERE post_id=? ORDER BY id ASC");
  $s->execute([$postId]);
  return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
