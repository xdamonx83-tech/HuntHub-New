<?php
require __DIR__ . '/../../lib/qa_ai.php';
$txt = qa_llm_answer("Fasse in 2 Sätzen auf Deutsch zusammen: Update 24 bringt das Event 'Judgement of the Fool' und neue Mechaniken.", 
                     "Du bist ein deutscher Assistent.");
header('Content-Type: text/plain; charset=utf-8');
echo $txt ?: "LLM nicht erreichbar";
