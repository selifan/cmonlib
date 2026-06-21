<?php
/**
* @name: plugins/chatbot/roiles.php
* список ролей-прав для модуля chatbot
* upd:2026-06-21
*/
$baseNm = 'Чат-бот';
$ret = array(
   'rights' => [
      chatbot::RIGHT_OPER => [ 'select',"$baseNm-пользователь",'0:нет;1:пользователь;2:продвинутый пользователь;10:Администратор' ]
  ]
  ,'roles' => array(
    chatbot::ROLE_OPER => [
       'title'  =>"$baseNm-пользователь"
      ,'rights' => [ chatbot::RIGHT_OPER =>1 ]
    ],
    chatbot::ROLE_ADVOPER => [
       'title'  =>"$baseNm-продвинутый пользователь"
      ,'rights' => [ chatbot::RIGHT_OPER =>2 ]
    ]
    ,chatbot::ROLE_SUPEROPER => [
       'title'  =>"$baseNm-Супер-Админ"
      ,'rights' => [ chatbot::RIGHT_OPER =>10 ]
    ]
  )
);
return $ret;
