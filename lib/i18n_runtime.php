<?php
// /lib/i18n_runtime.php
declare(strict_types=1);

function t(string $key, array $vars = []): string {
    /** @var array $L */
    $L = $GLOBALS['L'] ?? [];
    $s = $L[$key] ?? $key;
    foreach ($vars as $k => $v) {
        $s = str_replace('{'.$k.'}', (string)$v, $s);
    }
    return $s;
}
