<?php
declare(strict_types=1);

/**
 * /lib/logger.php – Standalone Logger ohne Abhängigkeiten
 * Legt Logs in /uploads/logs/hunthub.log ab
 */

if (!defined('HH_LOG_REL_DIR'))  define('HH_LOG_REL_DIR',  '/uploads/logs');
if (!defined('HH_LOG_FILENAME')) define('HH_LOG_FILENAME', 'hunthub.log');

if (!function_exists('hh_log_path')) {
    function hh_log_path(): string {
        $base = rtrim(__DIR__ . '/..', '/');
        $dir  = $base . HH_LOG_REL_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . HH_LOG_FILENAME;
    }
}

if (!function_exists('hh_log')) {
    function hh_log(string $level, string $message, array $context = []): void {
        static $file = null;
        if ($file === null) $file = hh_log_path();

        // Context als key=value Paare
        $ctx = '';
        if (!empty($context)) {
            $pairs = [];
            foreach ($context as $k => $v) {
                if (is_scalar($v) || $v === null) {
                    $pairs[] = $k . '=' . str_replace(["\n","\r"], ' ', (string)$v);
                } else {
                    $json = json_encode($v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                    $pairs[] = $k . '=' . substr((string)$json, 0, 2000);
                }
            }
            $ctx = ' ' . implode(' ', $pairs);
        }

        $time = date('Y-m-d H:i:s');
        $line = sprintf("%s [%s] %s%s\n", $time, strtoupper($level), str_replace(["\n","\r"], ' ', $message), $ctx);

        // Rotation bei >5 MB
        if (is_file($file) && @filesize($file) > 5 * 1024 * 1024) {
            @rename($file, $file . '.' . date('Ymd_His') . '.bak');
        }
        @file_put_contents($file, $line, FILE_APPEND);
    }
}

if (!function_exists('hh_setup_error_handlers')) {
    function hh_setup_error_handlers(?callable $userResolver = null): void {
        $getUser = function() use ($userResolver): array {
            try {
                if ($userResolver) {
                    $u = $userResolver();
                    if (is_array($u) && !empty($u)) {
                        return [
                            'user_id' => (string)($u['id']    ?? ''),
                            'email'   => (string)($u['email']  ?? ''),
                            'role'    => (string)($u['role']   ?? ''),
                        ];
                    }
                }
            } catch (Throwable) {}
            return [];
        };

        set_error_handler(function(int $severity, string $message, string $file = '', int $line = 0) use ($getUser) {
            if (!(error_reporting() & $severity)) return false; // @-operator respektieren
            $levels = [
                E_ERROR=>'E_ERROR',E_WARNING=>'E_WARNING',E_PARSE=>'E_PARSE',E_NOTICE=>'E_NOTICE',
                E_CORE_ERROR=>'E_CORE_ERROR',E_CORE_WARNING=>'E_CORE_WARNING',E_COMPILE_ERROR=>'E_COMPILE_ERROR',
                E_COMPILE_WARNING=>'E_COMPILE_WARNING',E_USER_ERROR=>'E_USER_ERROR',E_USER_WARNING=>'E_USER_WARNING',
                E_USER_NOTICE=>'E_USER_NOTICE',E_STRICT=>'E_STRICT',E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR',
                E_DEPRECATED=>'E_DEPRECATED',E_USER_DEPRECATED=>'E_USER_DEPRECATED'
            ];
            $lvl = $levels[$severity] ?? 'E_UNKNOWN';
            hh_log('php', "$lvl: $message in $file:$line", $getUser());
            return false; // Standard-Handler weiterlaufen lassen
        });

        set_exception_handler(function(Throwable $e) use ($getUser) {
            hh_log('exception', get_class($e).': '.$e->getMessage(), array_merge([
                'file'=>$e->getFile(), 'line'=>$e->getLine(),
            ], $getUser()));
            $trace = substr($e->getTraceAsString(), 0, 8000);
            hh_log('trace', $trace, []);
            http_response_code(500);
        });

        register_shutdown_function(function() use ($getUser) {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                hh_log('fatal', $err['message'] ?? 'fatal error', array_merge([
                    'file'=>$err['file'] ?? '', 'line'=>$err['line'] ?? 0,
                ], $getUser()));
            }
        });
    }
}
