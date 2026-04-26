<?php
/**
* @name cfg/openai-lmstudio_translator.php
* Настройки для работы с LM studio (в качестве простого переводчика)
* configured 2026-04-26
*/
return [
  'name' => 'LM studio translate',
  'baseUrl' => 'http://localhost:1234/v1/', # URL вызова AI провайдера
  'chatModel' => 'responses',
  'Temperature' => 0.1,
  'Model' => 'Qwen2.5-7B-Instruct', # Q4_K_M !
  # 'Model' => 'gpt-4o-mini', # 'openai/gpt-4', # 'openai/gpt-4o' / gpt-4-turbo
  'system_prompt' => 'You are a professional translator. Translate the following Russian text to English. Keep it concise, use technical terminology correctly. Output ONLY the translation.',
  'maxTokens' => 256,
];
