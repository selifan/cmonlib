<?php
/**
* @name cfg/openai-deepseek.php
* Настройки для работы с DeepSeek
* https://api.deepseek.com/v3
* https://api.deepseek.com/chat/completions
* Пример: https://b.deepseek3.ru/create-chat-completion/
*/
return [
  'baseUrl' => 'https://api.deepseek.com/v1/', # URL вызова AI провайдера
  'apiKey' => 'YOUR_API_KEY', # deepsek API key
  'Temperature' => 0.5,
  'Model' => 'deepseek-chat-33b', # gpt-4-turbo
  'maxTokens' => 4096,
];
