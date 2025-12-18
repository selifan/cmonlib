<?php
/**
* @name cfg/openai-openrouter.php
* Настройки для работы с Openrouter.ai
* https://openrouter.ai/docs/quickstart
*/
return [
  'baseUrl' => 'https://openrouter.ai/api/v1/chat/completions', # URL вызова AI провайдера
  'apiKey' => 'sk-or-v1-MY_KEY', # API key
  'Temperature' => 0.5,
  'Model' => 'openai/gpt-4', # openai/gpt-4o / gpt-4-turbo
  'maxTokens' => 8192,
];
