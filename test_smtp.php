<?php
declare(strict_types=1);
$cfg = require __DIR__ . '/auth/config.php';
require __DIR__ . '/lib/mailer.php';

$to = 'krispiearmy@web.de'; // <-- hier deine Mail
$ok = mailer_send_smtp($to, 'Hunthub Tester', 'SMTP Test (Hunthub)',
    "Hi,\r\nSMTP-Test von hunthub.online.\r\n", $cfg);

header('Content-Type: text/plain; charset=utf-8');
echo $ok ? "OK: Mail an $to gesendet.\n" : "FAIL: siehe var/mail.log\n";