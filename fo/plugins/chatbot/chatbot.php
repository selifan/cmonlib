<?PHP
/**
* @package alfo - Allianz Life Фронт Офис
* @author Alexander Selifonov,
* @name plugins/chatbot/chatbot.php,
*  chatbot plugin
* last modified : 2026-06-21
**/

class ChatBot extends appPlugins {
    const ME = 'chatbot';
    const VERSION = '0.10';
    const T_CHATBOT_CONTEXTS = 'chatbot_contexts';
    const T_CHATBOT_HIST = 'chatbot_hist';
	const RIGHT_OPER = 'chatbot';
    const ROLE_OPER = 'chatbot-oper';
    const ROLE_ADVOPER = 'chatbot-oper-advance';
	const ROLE_SUPEROPER = 'chatbot-admin';

    static $initDone = FALSE;
    public $product_type = '';
    public static function getVersion() {
        return self::VERSION;
    }
    public static function getDescription() {
        return 'Модуль чат-бот';
    }
    # "базовое" название программы/продукта
    public static function ProgramTitle() {
        return 'Chat Bot';
    }
    public static function getModuleName() { return 'Чат Бот'; }
    public function moduleType() { return PM::MODULE_INS; }

    # ИД для выгрузки ролей в учетную с-му СЭД
    public static function roleAlias() { return 'CHATBOT'; }

    public function __construct() {
        $this->_my_folder = appEnv::$FOLDER_PLUGINS . 'chatbot/';
        $this->product_type = PM::PRODTYPE_TOOLS;
    }

    public function init() {

		if (!self::$initDone) {
            self::$initDone = 1;
            appEnv::addTplFolder(__DIR__ . '/tpl/');
		}

    }

    public function modify_mainmenu() {

        $submenu = array();
        $b_edit = appEnv::$auth->getAccessLevel(self::RIGHT_OPER);

        # $b_restrict = appEnv::getConfigValue('chatbot_disable_activity');
        if($b_edit==1 ) {
            appEnv::addSubmenuItem('mnu_utils', 'chatbot_form', AppEnv::getLocalized('chatbot:main_title'),
                './?plg=chatbot&action=form');
        }
        elseif($b_edit>=10) {
            $chatMenu = [
              'chatbot_open' => array('title'=>AppEnv::getLocalized('chatbot:main_title'), 'href'=>'./?plg=chatbot&action=form'),
              'chatbot_context' => array('title'=>AppEnv::getLocalized('chatbot:contexts'), 'href'=>'./?p=editref&t='.chatbot::T_CHATBOT_CONTEXTS ),
              'chatbot_hist' => array('title'=>AppEnv::getLocalized('chatbot:reqhist'), 'href'=>'./?p=editref&t='.chatbot::T_CHATBOT_HIST ),
              'chatbot_mcpassistant' => array('title'=>AppEnv::getLocalized('chatbot:mcpassistant'), 'href'=>'./?plg=chatbot&action=mcpassistant' ),
            ];
            AppEnv::addSubmenuItem('mnu_utils', "mnu_chatbot",AppEnv::getLocalized('chatbot:main_title'), '','',$chatMenu);
        }
    }

    public function stub($mode=FALSE) {

        $code = [];
        $b_edit = appEnv::$auth->getAccessLevel(self::RIGHT_OPER);

        if($b_edit) {
            $b_drawblock = true;
            $code[] = "<a href=\"./?plg=".self::ME. "&action=form\">" . AppEnv::getLocalized('chatbot:main_title') . "</a>";
        }

        if($b_edit>=10) {
            $code[] = '<a href="./?p=editref&t='.chatbot::T_CHATBOT_CONTEXTS.'">' . AppEnv::getLocalized('chatbot:contexts') . '</a>';
            $code[] = '<a href="./?p=editref&t='.chatbot::T_CHATBOT_HIST.'">' . AppEnv::getLocalized('chatbot:reqhist') . '</a>';
            $code[] = '<a href="./?plg=chatbot&action=mcpassistant">' . AppEnv::getLocalized('chatbot:mcpassistant') . '</a>';
        }

        if (count($code))
          appEnv::drawUserBlock(implode('<br>',$code), AppEnv::getLocalized('chatbot:main_title'));
    }

    # текущий список конфигураций LLM движков
    public static function getEngineList() {
        $arRet = [ ['','---']];
        $cfgDir = __DIR__ . '/aiengines/';
        foreach(glob($cfgDir."*.php") as $oneFile) {
            $justfile = substr(basename($oneFile), 0,-4);
            $arRet[] = [$justfile, $justfile];
        }
        return $arRet;
    }
    # текущий список конфигураций openai настроек
    public static function getOpenAiConfigList() {
        $arRet = [ ['','---'] ];
        $cfgDir = __DIR__ . '/configs/';
        foreach(glob($cfgDir."openai-*.*") as $oneFile) {
            $fileExt = GetFileExtension($oneFile);
            if(in_array($fileExt, ['php','json'])) {
                $arRet[] = [basename($oneFile), basename($oneFile)];
            }
        }
        return $arRet;
    }
    # конвертирую php конфиги в json
    public static function convertConfigs() {
        $cfgDir = __DIR__ . '/configs/';
        $cfgFiles = glob($cfgDir."*.php");
        if(!count($cfgFiles)) return 'В папке configs нет php-настроек';
        $ret = [];
        foreach($cfgFiles as $oneFile) {
            $destFile = substr($oneFile, 0,-3) . 'json';
            $justName = basename($destFile);
            if(is_file($destFile)) $ret[] = "$justName - уже есть!";
            else {
                $arData = include($oneFile);
                $jsonData = json_encode($arData, (JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $size = @file_put_contents($destFile, $jsonData);
                $ret[] = is_file($destFile) ? "$justName создан, размер $size Бт" : "$justName - не создан!";
            }
        }
        return $ret;
    }
}
appEnv::registerPlugin(chatbot::ME);
