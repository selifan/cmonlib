<?php
/**
* @name libs/aibus.php
* Шина для работы с движками ИИ (AI)
* @version 0.10.001 started 2025-12-23
*/
namespace libs;
class AiBus {
    static $cfg = [];
    static $engineInstance = NULL;
    static $useHistory = FALSE;
    static $errorMessage = '';
    static $debug = FALSE;
    /**
    * подключение к настроенному движку с указанным именем
    * @param mixed $engine имя движка
    */
    public static function init(string $engine, $contextId='',$funcName = FALSE,$params=[]) {
        $loname = strtolower($engine);
        $cfgFile = \AppEnv::getAppFolder('cfg/'). "openai-$loname.php";
        if(is_file($cfgFile)) {
            # Если есть openai-конфиг в папке cfg, сразу юзаю OpenAI с этим конфигом
            if(self::$debug) writeDebugInfo("Using directly openai.php for config $loname, cfg file: $cfgFile");
            require_once(__DIR__ . '/aiengines/openai.php');
            if(self::$debug) writeDebugInfo("aiengines/openai.php included");
            try {
                self::$engineInstance = new \Libs\aiengines\OpenAI($loname);
            } catch (Exception $e) {
                self::$errorMessage = 'new Instance OpenAI($loname) exception: '.  $e->getMessage();
                return FALSE;
            }
        }
        else {
            if(self::$debug) writeDebugInfo("$cfgFile not found. Try open aiengines/$engine.php");
            $aiClass = "\\libs\\aiengines\\$loname";
            if(self::$debug) writeDebugInfo("Try to open $aiClass ...");
            if(is_file(__DIR__ . "/aiengines/$loname.php")) {
                try {
                    self::$engineInstance = new $aiClass();
                }
                catch(Exception $e) {
                    writeDebugInfo("new $aiClass Exception raised: ' . $e->getMessage()");
                }
            }
            else {
                self::$errorMessage = "Cannot create LM instance for $engine";
                return FALSE;
            }
        }

        if(self::$debug) writeDebugInfo("KT-003");

        if(is_object(self::$engineInstance) && !empty($funcName) && method_exists(self::$engineInstance,$funcName))
        {
            # writeDebugInfo("calling $funcName in class, params: ", $params);
            return self::$engineInstance->$funcName($params);
        }
        if(self::$debug) writeDebugInfo("finally AI object is: ", self::$engineInstance);
        return self::$engineInstance;
    }
    public static function getErrorMEssage() {
        return self::$errorMessage;
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
    public static function listOpenAiConfigs() {
        $files = glob(\AppEnv::getAppFolder('cfg/') . 'openai-*.php');
        $arRet = [];
        foreach($files as $onefile) {
            $base = substr(basename($onefile), 7, -4);
            $arCfg = include($onefile);
            $cfgName = $arCfg['name'] ?? $base;
            $arRet[] = [$base, $cfgName];
        }
        return $arRet;
    }
}