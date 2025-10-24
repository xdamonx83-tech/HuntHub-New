<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
error_reporting(E_ALL);

$ROOT  = dirname(__DIR__);
$LEGAL = __DIR__;

function safe_require(string $path): bool {
  if (is_file($path)) { require_once $path; return true; }
  return false;
}

$haveLayout = safe_require($ROOT . '/lib/layout.php');

// Config optional
$cfg = [];
$cfgPath = $ROOT . '/auth/config.php';
if (is_file($cfgPath)) {
  $tmp = require $cfgPath;
  if (is_array($tmp)) $cfg = $tmp;
}
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');

// Whitelist
$docs = [
  'privacy'    => ['privacy.de.md',    'Datenschutzerklärung'],
  'terms'      => ['terms.de.md',      'Nutzungsbedingungen'],
  'netiquette' => ['netiquette.de.md', 'Netiquette'],
  'impressum'  => ['impressum.de.md',  'Impressum'],
];

$doc = $_GET['doc'] ?? 'privacy';
$partial = isset($_GET['partial']) && $_GET['partial'] === '1';

if (!isset($docs[$doc])) {
  http_response_code(404);
  if ($partial) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Dokument nicht gefunden.']);
    exit;
  }
  $title = '404';
  $body  = '<h1>404</h1><p>Dokument nicht gefunden.</p>';
  if ($haveLayout && function_exists('layout')) {
    echo layout($title, fn()=>print('<div class="legal-page">'.$body.'</div>'));
  } else {
    echo "<!doctype html><meta charset='utf-8'><title>{$title}</title><div class='legal-page'>{$body}</div>";
  }
  exit;
}

[$file, $title] = $docs[$doc];
$mdPath = $LEGAL . '/' . $file;
$md     = is_file($mdPath) ? (string)file_get_contents($mdPath) : "";

// Markdown -> HTML (optional Parsedown)
$haveParsedown = safe_require($LEGAL . '/Parsedown.php');
if ($haveParsedown) {
  $pd = new Parsedown();
  if (method_exists($pd, 'setSafeMode')) $pd->setSafeMode(true);
  $html = $pd->text($md);
} else {
  $html = '<pre style="white-space:pre-wrap">'.htmlspecialchars($md ?: "# Datei fehlt\n\n{$file} nicht gefunden.", ENT_QUOTES, 'UTF-8').'</pre>';
}

// PARTIAL: nur JSON für Modals
if ($partial) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok'=>true,'title'=>$title,'html'=>$html], JSON_UNESCAPED_UNICODE);
  exit;
}

// Vollseite
$wrap = '<div class="legal-page">'.$html.'</div>';
if ($haveLayout && function_exists('layout')) {
  echo layout($title, fn()=>print($wrap));
} else {
  echo "<!doctype html><meta charset='utf-8'><title>{$title}</title>{$wrap}";
}
