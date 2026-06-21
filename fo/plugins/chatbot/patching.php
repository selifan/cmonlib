<?php
/**
* @name plugins/chatbot/patching.php
* код для отработки из патчера ALFO (модуль cht botе)
* modified 2025-06-16
*/
# базовая настройка реквизитов продукта
$module = 'chatbot';

self::initTable(chatbot::T_CHATBOT_CONTEXTS);
self::initTable(chatbot::T_CHATBOT_HIST);
self::upgradeRoles($module);
return '';
# return 'Финальный текст, если надо вывести';
