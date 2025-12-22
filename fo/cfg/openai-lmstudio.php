<?php
/**
* @name cfg/openai-lmstudio.php
* Настройки для работы с работающим LM studio (дефолтное подключение)
*/
return [
  'name' => 'LM studio',
  'baseUrl' => 'http://localhost:1234/v1/', # URL вызова AI провайдера
  # 'modelsUrl' => 'http://localhost:1234/v1/models', # URL для завпроса списка доступных моделей
  'Temperature' => 0.5,
  # 'Model' => 'gpt-4o-mini', # 'openai/gpt-4', # 'openai/gpt-4o' / gpt-4-turbo
  'maxTokens' => 4096,
];
