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
  'stateful' => 1, # stateful, Модель помнит прошлые ответы
  'user_convid' => 'previous_response_id', # stateful, в каком поле надо отправлять ИД текущей беседы (previous_response_id conversation_id,parent_id,session_id,chat_id,conversation)
  # похоже, LM studio воспринимает только ИД предыдущего ответа, но не сессии, т.е. id который она присылает, надо считать ид ответа, а не сессии
  'server_convid' => 'id', # stateful, в каком поле chat возвращает ИД текущей беседы ЛИБО последнего ответа
  # 'authorization' =>'conversation_id', # у локальной LM studio буду отправлять ИД сессии чата в http-загловке:
  # 'authorization' =>'Conversation-Id: <id>',
  # Authorization: conversation_id=<id> или Conversation-Id: <id>
  # LMstudio: в режиме responses указывать модель ОБЯЗАТЕЛЬНО! (в chat/completions - не нужно)
  'Model' => 'meta-llama-3.1-8b-instruct@iq4_xs',
  'maxTokens' => 4096,
];
