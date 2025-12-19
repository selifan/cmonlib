<?php
/**
* @package ALFO
* @name cfg/cfg-sms-beeline.php
* Настройки для отправки СМС через провайдера BEELINE
* (тестовый)!
*/
return [
   'service_url' => 'https://a2p-sms-https.beeline.ru/proto/http/rest',
   # 'apiKey' => 'YOUR-ApiKEY', # если здесь задан API-токен, логин и пароль не нужны
   'login' => 'zettaLife',
   'password' => '1234567890864297531',

   # можно указать раздельно для разных каналов
   'login-bnk' => '', # для банковского канала
   'password-bnk' => '',
   # 'apiKey-bnk' => 'API_BANK', # API учетка для рассылок банковсвкого канала
   'login-avr' => '', # для агентских вз-расчетов
   'password-avr' => '',
   # 'apiKey-avr' => 'API_AVR', # учетка для рассылок АВР (акты агентам) - Лихолетов
   # 'sender_name' => 'Our Name as Sender',
   'emulate' => 1, # если надо включить эмуляцию отправки СМС
];
