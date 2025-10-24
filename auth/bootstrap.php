<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/guards.php';
require_once __DIR__.'/csrf.php';
$cfg = require __DIR__.'/config.php';
$APP_BASE = rtrim($cfg['app_base'] ?? '', '/');
require_once __DIR__.'/session_bootstrap.php'; // setzt Session-Cookie-Params + session_start()