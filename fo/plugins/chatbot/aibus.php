<?php
/**
* @name plugins/chatbot/aibus.php
* Шина для работы с движками ИИ (AI)
* @version 0.10.001 started 2025-12-23
* modified 2026-06-21
*/
namespace plugins\chatbot;
use \AppEnv;

class AiBus {
    static $cfg = [];
    static $engineInstance = NULL;
    static $useHistory = FALSE;
    static $errorMessage = '';
    static $imageLimitMode = 1; # Лимит на отправляемый файл изобр., 1 - малый лимит 512-x512, 2+ - большой размер
    static $debug = FALSE;
    /**
    * подключение к настроенному движку с указанным именем
    * @param string $engine имя движка
    */
    public static function init(string $engine='', $funcName = FALSE,$params=[]) {
        if(!$engine) $engine = AppEnv::getConfigValue('chatbot_engine');
        $aiClass = "\\plugins\\chatbot\\aiengines\\$engine";
        # writeDebugInfo("engine : $engine");
        if($engine === 'stub') {
            try {
                self::$engineInstance = new aiengines\stub();
                # writeDebugInfo("stub created: ", self::$engineInstance);
            } catch (Exception $e) {
                self::$errorMessage = "new Instance stub exception: ". $e->getMessage();
                # writeDebugInfo("create obj Exception ", self::$errorMessage);
                return FALSE;
            }
        }
        elseif($engine === 'openai') {
            $openaiConfig = AppEnv::getConfigValue('chatbot_openai_config');
            $cfgFile = __DIR__ . "/configs/$openaiConfig";
            # writeDebugInfo("cfgFile: ", $cfgFile);
            if(is_file($cfgFile)) {
                # Если есть openai-конфиг в папке cfg, сразу юзаю OpenAI с этим конфигом
                if(self::$debug) writeDebugInfo("Using directly openai.php for config $openaiConfig, cfg file: $cfgFile");
                try {
                    self::$engineInstance = new aiengines\OpenAI($cfgFile);
                } catch (Exception $e) {
                    self::$errorMessage = "new Instance OpenAI($cfgFile) exception: ". $e->getMessage();
                    writeDebugInfo("create obj Exception ", self::$errorMessage);
                    return FALSE;
                }
            }
        }
        else {

            if(self::$debug) writeDebugInfo("Try to open $aiClass from aiengines/$engine.php...");
            if(is_file(__DIR__ . "/aiengines/$engine.php")) {
                try {
                    self::$engineInstance = new $aiClass();
                }
                catch(Exception $e) {
                    writeDebugInfo("new $aiClass Exception raised: ' . $e->getMessage()");
                    self::$errorMessage = "Cannot create LM instance for $engine: ".$e->getMessage();
                    return FALSE;
                }
            }
            else {
                self::$errorMessage = "Cannot create LM instance for $engine";
                return FALSE;
            }
        }

        if(is_object(self::$engineInstance) && !empty($funcName) && method_exists(self::$engineInstance,$funcName))
        {
            # writeDebugInfo("calling $funcName in class, params: ", $params);
            return self::$engineInstance->$funcName($params);
        }
        if(self::$debug) writeDebugInfo("finally AI object is: ", self::$engineInstance);
        return self::$engineInstance;
    }
    public static function getErrorMessage() {
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
    /*
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
    */
    public static function getOutputFiles() {
        $ret = $_SESSION['chat_outfiles'] ?? [];
        return $ret;
    }
    # клиент подгружает файл
    public static function uploadFile() {
        # TODO: реализовать
    }

    /** уменьшаем при необходимости размер картинки:
    * для режима низкого разрешения — изображение 512×512 пикселей,
    * для режима высокого разрешения — короткая сторона изображения должна быть меньше 768 пикселей,
    * а длинная — меньше 2000 пикселей
    */
    public static function resizeImageforOutput(array $fileData) {
        list($width, $height) = getimagesize($fileData['filepath']);
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil($width-($width*abs($r-$w/$h)));
            } else {
                $height = ceil($height-($height*abs($r-$w/$h)));
            }
            $newwidth = $w;
            $newheight = $h;
        } else {
            if ($w/$h > $r) {
                $newwidth = $h*$r;
                $newheight = $h;
            } else {
                $newheight = $w/$r;
                $newwidth = $w;
            }
        }
        $src = imagecreatefromjpeg($file);
        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        return $dst;

    }

    public static function clearOutputFiles() {
        $outFiles = $_SESSION['chat_outfiles'] ?? [];
        if(count($outFiles)) {
            foreach($outFiles as $item) {
                if(is_file($item['filepath'])) @unlink($item['filepath']);
            }
            $_SESSION['chat_outfiles'] = $outFiles = [];
        }
    }

}