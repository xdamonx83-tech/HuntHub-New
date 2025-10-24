<?php
declare(strict_types=1);

/**
 * SMTP-Mailer (TLS/SSL, AUTH LOGIN) mit ausführlichem Logging.
 * Log: /var/mail.log (relativ zum Projektroot, also ../var von dieser Datei aus)
 */

function mailer_send_smtp(
  string $toEmail,
  string $toName,
  string $subject,
  string $textBody,
  array $cfg
): bool {
  $smtp   = $cfg['smtp'] ?? [];
  $host   = (string)($smtp['host'] ?? '');
  $port   = (int)($smtp['port'] ?? 587);
  $secure = (string)($smtp['secure'] ?? 'tls'); // 'tls'|'ssl'|'' (plain)
  $user   = (string)($smtp['user'] ?? '');
  $pass   = (string)($smtp['pass'] ?? '');
  $timeout= (int)($smtp['timeout'] ?? 12);

  $from     = (string)($cfg['mail']['from'] ?? 'no-reply@localhost');
  $fromName = (string)($cfg['mail']['from_name'] ?? 'No-Reply');

  $logDir = __DIR__ . '/../var';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $logFile = $logDir . '/mail.log';
  $log = function(string $m) use ($logFile) {
    @file_put_contents($logFile, '['.date('c')."] ".$m."\n", FILE_APPEND);
  };

  if ($host === '') { $log('ABORT: SMTP host missing'); return false; }
  $log(sprintf('CONNECT %s:%d secure=%s as user=%s from=%s → to=%s',
      $host, $port, $secure ?: 'none', $user ?: '(none)', $from, $toEmail));

  $transport = ($secure === 'ssl') ? "ssl://{$host}:{$port}" : "{$host}:{$port}";
  $context = stream_context_create(['ssl' => [
    'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false,
  ]]);
  $fp = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
  if (!$fp) { $log("CONNECT FAIL: $errstr ($errno)"); return false; }
  stream_set_timeout($fp, $timeout);

  $read = function() use ($fp, $log) {
    $resp = '';
    while (!feof($fp)) {
      $line = fgets($fp, 2048);
      if ($line === false) break;
      $resp .= $line;
      if (preg_match('/^\d{3}\s/', $line)) break; // letzte Zeile
      if (!preg_match('/^\d{3}-/', $line)) break; // nicht multi-line
    }
    $log('S: '.trim($resp));
    return $resp;
  };
  $write = function(string $cmd) use ($fp, $log) { $log('C: '.rtrim($cmd)); fwrite($fp, $cmd); };
  $expect = function(string $resp, array $ok=[220,250,235,334,354]) {
    return (bool)preg_match('/^('.implode('|',$ok).')\b/m', $resp);
  };

  $banner = $read(); if (!$expect($banner,[220])) { fclose($fp); return false; }
  $ehloHost = preg_replace('~[^a-z0-9\.-]~i','', $_SERVER['SERVER_NAME'] ?? 'hunthub.online');
  $write("EHLO {$ehloHost}\r\n"); $resp = $read(); if (!$expect($resp,[250])) { fclose($fp); return false; }

  if ($secure === 'tls') {
    $write("STARTTLS\r\n"); $resp = $read(); if (!$expect($resp,[220])) { fclose($fp); return false; }
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $log('TLS handshake fail'); fclose($fp); return false; }
    $write("EHLO {$ehloHost}\r\n"); $resp = $read(); if (!$expect($resp,[250])) { fclose($fp); return false; }
  }

  if ($user !== '' && $pass !== '') {
    $write("AUTH LOGIN\r\n"); $resp = $read(); if (!$expect($resp,[334])) { fclose($fp); return false; }
    $write(base64_encode($user)."\r\n"); $resp = $read(); if (!$expect($resp,[334])) { fclose($fp); return false; }
    $write(base64_encode($pass)."\r\n"); $resp = $read(); if (!$expect($resp,[235])) { fclose($fp); return false; }
  }

  $write("MAIL FROM:<{$from}>\r\n"); $resp = $read(); if (!$expect($resp,[250])) { fclose($fp); return false; }
  $write("RCPT TO:<{$toEmail}>\r\n"); $resp = $read(); if (!$expect($resp,[250,251])) { fclose($fp); return false; }
  $write("DATA\r\n"); $resp = $read(); if (!$expect($resp,[354])) { fclose($fp); return false; }

  $crlf = "\r\n";
  $subjEnc = '=?UTF-8?B?'.base64_encode($subject).'?=';
  $fromHdr = $fromName ? sprintf('"%s" <%s>', addslashes($fromName), $from) : $from;
  $toHdr   = $toName ? sprintf('"%s" <%s>', addslashes($toName), $toEmail) : $toEmail;

  $body = str_replace(["\r\n","\r"], "\n", $textBody);
  $body = preg_replace("/^\./m","..",$body);
  $body = str_replace("\n",$crlf,$body);

  $headers =
    "From: {$fromHdr}{$crlf}".
    "To: {$toHdr}{$crlf}".
    "Subject: {$subjEnc}{$crlf}".
    "MIME-Version: 1.0{$crlf}".
    "Content-Type: text/plain; charset=utf-8{$crlf}".
    "Content-Transfer-Encoding: 8bit{$crlf}";

  $write($headers.$crlf.$body.$crlf.".\r\n"); $resp = $read(); if (!$expect($resp,[250])) { fclose($fp); return false; }

  $write("QUIT\r\n"); $read();
  fclose($fp);
  $log('OK: accepted by server');
  return true;
}
