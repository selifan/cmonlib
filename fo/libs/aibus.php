<?php
/**
* @name libs/aibus.php
* Шина для работы с движками ИИ (AI)
* @version 0.1.001 started 2025-11-07
*/
namespace libs;
class AiBus {
    static $cfg = [];
    static $engineInstance = NULL;
    static $useHistory = FALSE;
    static $errorMessage = '';
    /**
    * подключение к настроенному движку с указанным именем
    * @param mixed $engine имя движка
    */
    public static function init(string $engine, $contextId='',$funcName = FALSE,$params=[]) {
        $loname = strtolower($engine);
        $aiClass = "\\libs\\aiengines\\$loname";
        if(is_file(__DIR__ . "/aiengines/$loname.php")) {
            self::$engineInstance = new $aiClass();
        }
        else {
            self::$engineInstance = NULL;
            self::$errorMessage = "Undefined ai engine name: $engine";
        }
        if(is_object(self::$engineInstance) && !empty($funcName) && method_exists(self::$engineInstance,$funcName))
        {
            # writeDebugInfo("calling $funcName in class, params: ", $params);
            return self::$engineInstance->$funcName($params);
        }
        return self::$engineInstance;
    }

    public static function setContext($strContext) {
        if(is_object(self::$engineInstance) && method_exists(self::$engineInstance, 'setContext'))
            $result = self::$engineInstance->setContext($strContext);
        else $result = NULL;
        return $result;
    }

    # Отправляем запрос, получаем ответ (текст)
    public static function request($strRequest) {
        if(is_object(self::$engineInstance) && method_exists(self::$engineInstance, 'request'))
            $result = self::$engineInstance->request($strRequest);
        else $result = NULL;
        return $result;
    }
    public static function resetHistory() {
        if(!self::$useHistory) {
            $response = 'История не поддерживается';
        }
        else {
            $response = 'История вопросов очищена';
            # TODO: очистить историю
        }
        exit('1' . \AjaxResponse::timedNotify($response,1));
    }
    # получить список доступных для выбора ИИ движков
    public static function listEngines() {
        $files = glob(__DIR__ . '/aiengines/*.php');
        $arRet = [];
        foreach($files as $enfile) {
            $namef = substr(basename($enfile), 0,-4);
            if(substr($namef,0,4)=='cfg.') continue;
            $arRet[] = [$namef, $namef];
        }
        return $arRet;
    }
}