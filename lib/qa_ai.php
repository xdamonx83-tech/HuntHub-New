<?php
declare(strict_types=1);

/**
 * lib/qa_ai.php
 * Provider-Wrapper für Embeddings und LLM (optional).
 * Funktioniert ohne Konfiguration im reinen BM25/FULLTEXT-Fallback.
 *
 * Konfiguration:
 *  - In /auth/config.php optional:
 *      'ai' => [
 *        'provider' => 'ollama' | 'openai' | 'none',
 *        'openai' => ['api_key' => 'sk-...', 'base' => 'https://api.openai.com/v1', 'embedding_model' => 'text-embedding-3-small', 'chat_model' => 'gpt-4o-mini'],
 *        'ollama' => ['base' => 'http://127.0.0.1:11434', 'embedding_model' => 'nomic-embed-text', 'chat_model' => 'llama3.1:8b-instruct']
 *      ]
 *  - oder via ENV:
 *      AI_PROVIDER, OPENAI_API_KEY, OPENAI_BASE, OPENAI_EMBED_MODEL, OPENAI_CHAT_MODEL,
 *      OLLAMA_BASE, OLLAMA_EMBED_MODEL, OLLAMA_CHAT_MODEL
 */

function qa_ai_cfg(): array {
    $cfg = [];
    $path = __DIR__ . '/../auth/config.php';
    if (is_file($path)) {
        $arr = require $path;
        if (is_array($arr) && isset($arr['ai'])) {
            $cfg = $arr['ai'];
        }
    }
    // ENV overrides / fallback
    $cfg['provider'] = $cfg['provider'] ?? (getenv('AI_PROVIDER') ?: 'none');
    if ($cfg['provider'] === 'openai') {
        $cfg['openai'] = $cfg['openai'] ?? [];
        $cfg['openai']['api_key'] = $cfg['openai']['api_key'] ?? getenv('OPENAI_API_KEY') ?: '';
        $cfg['openai']['base']    = $cfg['openai']['base']    ?? getenv('OPENAI_BASE') ?: 'https://api.openai.com/v1';
        $cfg['openai']['embedding_model'] = $cfg['openai']['embedding_model'] ?? getenv('OPENAI_EMBED_MODEL') ?: 'text-embedding-3-small';
        $cfg['openai']['chat_model']      = $cfg['openai']['chat_model']      ?? getenv('OPENAI_CHAT_MODEL') ?: 'gpt-4o-mini';
    } elseif ($cfg['provider'] === 'ollama') {
        $cfg['ollama'] = $cfg['ollama'] ?? [];
        $cfg['ollama']['base'] = $cfg['ollama']['base'] ?? getenv('OLLAMA_BASE') ?: 'http://127.0.0.1:11434';
        $cfg['ollama']['embedding_model'] = $cfg['ollama']['embedding_model'] ?? getenv('OLLAMA_EMBED_MODEL') ?: 'nomic-embed-text';
        $cfg['ollama']['chat_model']      = $cfg['ollama']['chat_model']      ?? getenv('OLLAMA_CHAT_MODEL') ?: 'llama3.1:8b-instruct';
    }
    return $cfg;
}

function qa_http_json(string $url, array $payload, array $headers=[]): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP error: " . $err);
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$resp, true);
    if (!is_array($data)) $data = ['raw' => $resp];
    $data['_status'] = $code;
    return $data;
}

/** Embedding als float[] zurückgeben (oder leeres Array bei none/Fehler) */
function qa_embed(string $text): array {
    $cfg = qa_ai_cfg();
    $provider = strtolower((string)($cfg['provider'] ?? 'none'));

    if ($provider === 'openai') {
        $api = rtrim($cfg['openai']['base'] ?? '', '/');
        $model = (string)($cfg['openai']['embedding_model'] ?? 'text-embedding-3-small');
        $key = (string)($cfg['openai']['api_key'] ?? '');
        if (!$key) return [];
        try {
            $res = qa_http_json($api.'/embeddings', [
                'model' => $model,
                'input' => $text,
            ], ["Authorization: Bearer {$key}"]);
            if (!empty($res['data'][0]['embedding']) && is_array($res['data'][0]['embedding'])) {
                return array_map('floatval', $res['data'][0]['embedding']);
            }
        } catch (Throwable $e) { /* swallow */ }
        return [];
    }
    if ($provider === 'ollama') {
        $base = rtrim($cfg['ollama']['base'] ?? 'http://127.0.0.1:11434', '/');
        $model = (string)($cfg['ollama']['embedding_model'] ?? 'nomic-embed-text');
        try {
            $res = qa_http_json($base.'/api/embeddings', [
                'model' => $model,
                'prompt' => $text,
            ]);
            if (!empty($res['embedding']) && is_array($res['embedding'])) {
                return array_map('floatval', $res['embedding']);
            }
        } catch (Throwable $e) { /* swallow */ }
        return [];
    }
    return []; // 'none' fallback
}

/** Einfache Chat-Antwort (LLM). Gibt String zurück oder ''. */
function qa_llm_answer(string $prompt, string $system='You are a helpful assistant.'): string {
    $cfg = qa_ai_cfg();
    $provider = strtolower((string)($cfg['provider'] ?? 'none'));
    if ($provider === 'openai') {
        $api = rtrim($cfg['openai']['base'] ?? '', '/');
        $model = (string)($cfg['openai']['chat_model'] ?? 'gpt-4o-mini');
        $key = (string)($cfg['openai']['api_key'] ?? '');
        if (!$key) return '';
        try {
            $res = qa_http_json($api.'/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>$prompt]
                ],
                'temperature' => 0.2,
            ], ["Authorization: Bearer {$key}"]);
            $txt = $res['choices'][0]['message']['content'] ?? '';
            return is_string($txt) ? $txt : '';
        } catch (Throwable $e) { return ''; }
    }
    if ($provider === 'ollama') {
        $base = rtrim($cfg['ollama']['base'] ?? 'http://127.0.0.1:11434', '/');
        $model = (string)($cfg['ollama']['chat_model'] ?? 'llama3.1:8b-instruct');
        try {
            $res = qa_http_json($base.'/api/chat', [
                'model' => $model,
                'messages' => [
                    ['role'=>'system','content'=>$system],
                    ['role'=>'user','content'=>$prompt]
                ],
                'stream' => false
            ]);
            $txt = $res['message']['content'] ?? '';
            return is_string($txt) ? $txt : '';
        } catch (Throwable $e) { return ''; }
    }
    return ''; // 'none'
}

/** Dot-Product von zwei Vektoren gleicher Länge */
function qa_dot(array $a, array $b): float {
    $n = min(count($a), count($b));
    $s = 0.0;
    for ($i=0;$i<$n;$i++) $s += ((float)$a[$i]) * ((float)$b[$i]);
    return $s;
}

/** Float-Array -> Binär (float32) */
function qa_pack_f32(array $vec): string {
    $bin = '';
    foreach ($vec as $v) {
        $bin .= pack('f', (float)$v);
    }
    return $bin;
}

/** Binär (float32) -> Float-Array */
function qa_unpack_f32(string $blob): array {
    $len = strlen($blob);
    if ($len % 4 !== 0) return [];
    $out = [];
    for ($i=0;$i<$len;$i+=4) {
        $arr = unpack('f', substr($blob, $i, 4));
        $out[] = (float)$arr[1];
    }
    return $out;
}
