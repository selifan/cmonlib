<?php
/**
* @name app/ai.engines/stub.php
* Заглушка для имитации вызовов AI движков
* @version 0.01.001
* modified 2025-11-13
**/
namespace Libs\aiengines;
class Stub {
    public function __construct() {
    }
    public function setContext($params = '') {
        return "Stub Context set, params: <pre>".print_r($params,1).'</pre>';
    }
    public function request($requestString = '', $arHist = [], $context = '') {
        # if($arHist) writeDebugInfo("передана история зпросов: ", $arHist);
        return "TODO: передан запрос:<br> $requestString";
    }
}
