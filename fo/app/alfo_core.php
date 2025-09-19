<?PHP
/**
* @package ALFO( ex.arjagent) - Фронт-Офис Allianz Life
* @author Alexander Selifonov, <Aleksandr.Selifonov@allianz.ru>
* @link: http://www.allianzlife.ru,
* @name alfo_core.php
* Base WebApp extending module
* @version 2.141.001
* Bitrix integration aware
* modified : 2025-09-18
**/
#ini_set('display_errors',1); ini_set('error_reporting',E_ALL);
# if(!defined('JQUERY_VERSION')) define('JQUERY_VERSION', '2.2.2.min');//    1.11.3.min | 2.2.2.min.js
define('USE_JQUERY_UI',1); # astedit.php will use jquery.ui css

include_once(__DIR__ . '/../cfg/_appcfg.php');
include_once(__DIR__ . '/../cfg/folders.inc');

define('APPDATE', '18.09.2025');
define('APP_VERS', '2.141');

if (!constant('IN_BITRIX') || !empty($_SESSION['log_errors'])) {
    # error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
    error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}
define('ENABLE_PACK_EXPORT', false); // выгрузка в пакеты : false = выключена

define('POOL_FILE_FLAG', 1); // Генерация номеров полисов с использ. единого файла-семафора (блокировка конкурентных запросов)

define('UNI_NUMBER_POOL', FALSE); # TRUE - один общий диапазон на все продукты
define('DEFAULT_CHARSET',MAINCHARSET);
ini_set('default_charset', MAINCHARSET);
if (!defined('MAINCHARSETCL'))
	define('MAINCHARSETCL',DEFAULT_CHARSET);
if (!defined('DB_DEFAULTCHARSET'))
	define('DB_DEFAULTCHARSET', MAINCHARSET);

# strpos, stripos... works with string as UTF-8 by default
mb_internal_encoding(MAINCHARSET);

# define ('UI_THEME', 'flick'); # jQuery.ui theme
define('FOLDER_TPL', 'app/tpl/'); # astedit/waCRUDER table definitions folder

define('USE_PSW_POLICIES',1); # использование раздельных парольных политик

# setlocale(LC_ALL, 'ru_RU.CP1251');
if (MAINCHARSET === 'WINDOWS-1251')
	setlocale (LC_CTYPE, ['ru_RU.CP1251', 'rus_RUS.1251']);
else
	setlocale (LC_CTYPE, 'ru_RU.utf8');

# Try using latest jQuery, jQuery.ui
define('USE_LAST_JQUERY',1);

# Latest - jquery-3.6.1.min.js
# define('UI_VERSION', '1.10.4.custom');
# define('UI_VERSION', ''); # без версии - последняя актуальная!

global $auth;
$auth = '';
$jsdebug = false;
if(is_file(__DIR__ . '/as_cssclass.php')) {
    # {2025-02-04} predefine CSS for astedit
    global $as_cssclass;
    $as_cssclass = include(__DIR__ . '/as_cssclass.php');
}

if (!defined('LIBPATH')) define('LIBPATH', '');
if(($libs = constant('LIBPATH'))) { # добавляю папку в include_path!
    set_include_path('./' . PATH_SEPARATOR . $libs . PATH_SEPARATOR . get_include_path());
}
if (!class_Exists('HeaderHelper')) {
	if (defined('IN_BITRIX') && constant('IN_BITRIX')>1) {
	    require_once('basefunc_btx.php');
	}
	else {
	    require_once('basefunc.php');
	}
}
CDebs::DebugSetOutput(ALFO_ROOT . '_debuginfo.log');

if ( constant('IN_BITRIX') ) {
    HeaderHelper::$mainheader = 'bitrix_mainheader.htm';
    HeaderHelper::$mainfooter = 'bitrix_footer.htm';
}

include_once('webapp.core.php');

class AppEnv extends WebApp {

    const DEFAULT_LANG = 'ru';
    const FOLDER_FILES  = './files/';
    static $FOLDER_APP = '';
    static $charset = 'UTF-8';
    const TABLES_PREFIX     = 'arjagent_';
    const TABLES_NEWPREFIX  = 'alf_'; # новый префикс, постепенно уходим от arjagent_
    static $UNI_NUMBER_POOL = FALSE; # Использовать общий диапазон номеров "UNIVERSAL" на ВСЕ продукты

    static $POOL_WARNING_THRESHOLD1 = 50; # за сколько номеров предупреждать об истечении в пуле номероа полисов
    static $POOL_WARNING_THRESHOLD2 = 20; # второй порог пердупреждения!
    static $mobile = NULL;

    const TABLE_UPLOADEDFILES ='alf_uploadedfiles'; # таблица список загруженных файлов документов
    # const TABLE_DEPT_PRODUCTS ='alf_dept_product';  # список прав и комиссий подразделений в продуктах
    # const TABLE_PROFESSIONS ='alf_professions';  # 24.04.2018 - список профессий
    static $FOLDER_FILES = 'files/';
    static $table_uploadedfiles ='alf_uploadedfiles';
    private static $userMetaType = FALSE;

    const RIGHT_DOCFLOW = 'docflow_oper'; # видны все полисы, есть право выгрузки в СЭД
    const RIGHT_DOCFILES = 'docfiles_edit'; # может загружать файлы шаблонов, документов
    const RIGHT_USERMANAGER = 'account_admin'; # ID права администратора ВСЕХ учеток (право сброса пароля и т.д.)
    const RIGHT_DEPTMANAGER = 'dept_editor'; # администратора списка подразд.
    static $ENABLE_SERVERTIME = 0; # Show dynamic server time block
    private static $docflow_access = null; // могу выгружать в СЭД - true
    private static $_filecategs = array();
    private static $_appStates = []; # текущие "состояния" для разных параметров/модулей setUsrAppState($key, $val), getUsrAppState($key)
    private static $tableDeps = []; # зарегистрированные зависимости таблиц (PK-> FK)
    public static $nonLifeModules = []; # зарегистрированные модули НЕ-Жизни

    protected static $cur_mb_state = '';
    const DEFAULT_PASSWORD = '1qaz!QAZ';
    static $primary_dept = '';

    static $meta_depts = array(); // Общий список "мета-подразд.", под которыми регистрируются "головные" подразд-я ("банки",...)
    static $superadmin = NULL;

    static $app_newfunc = 1; # Задает как выполнять обновленные функции - 0 - по-старому, 1 - по-новому (для тестирования нового ф-ционала

    # Добавляю описатели для вывода инфографики
    static private $_infographics = array();

    # список ИД плагинов, для которых работает экспорт в Лизу
    static $exportable_modules = array();
    static $_init_done = false;

    # объекты для вызовов ALFO-сервисов (API)
    static $svc_lastError = '';
    static $light_process = 0; # признак "упрошенного" процесса оформления полиса (пролажи с интернет-лэндингов, партнерских программ)

    # для работы из задания - занести ИД полиса и модуля - setPolicyId():
    static $currentPolicy = '';
    static $currentModule = '';

    # установить в TRUE если вызов по API с сайта, где клиент оформляет себе полис - AppEnv::setClientCall()
    static $client_call = FALSE;
    static $client_userid = 0;
    static $client_deptid = 0; # ID подразд-я, в которой живет единая учетка "eShop"

    # TRUE если запущено задание, а не обрабокта вызова от клиента
    static $in_job = FALSE;

    # массив строк предупреждений, по любым причинам. При вызове из API - будут переданы в result[message]
    static $warnings = [];
    public static $tmpdata = FALSE; // Контейнер для передачи данных между "несвязанными" вызовами
    static function getSvcLastError() {
        return self::$svc_lastError;
    }
    # вернет TRUE если работает внутри CMS 1c Bitrix
    public static function inBitrix() {
    	return class_exists('CUser');
	}

    # добавляем модуль НЕ-жизни
    public static function addNonLifeModule($mdname) {
        if (!in_array($mdname, self::$nonLifeModules)) self::$nonLifeModules[] = $mdname;
    }
    # установить режим "запрос от неаутентифицированного клиента"
    # можно передать ИД eShop-учетки, вместо "стандартной из настроек
    public static function setClientCall($param = TRUE) {
        self::$client_call = $param;
        if (self::$client_call) {
            if ($param > 1) $apiUserid = intval($param);
            else {
                $apiUserid = AppEnv::getConfigValue('account_clientplc');
                # $apiUserid = $cliList[0];
            }
            if ($apiUserid > 0) {
                $_SESSION['arjagentuserid'] = self::$client_userid = $apiUserid;
                $deptDta = AppEnv::$db->select(PM::T_USERS, [
                   'where'=>['userid' => $apiUserid ],
                   'fields' =>'deptid',
                   'singlerow' => 1
                ]);
                if (isset($deptDta['deptid'])) self::$client_deptid = $deptDta['deptid'];

            }

        }
    }

    public static function addWarning($strg) {
        self::$warnings[] = $strg;
    }
    # "запрос от неаутентифицированного клиента" ?
    public static function isClientCall() {
        return self::$client_call;
    }
    public static function setPolicyId($id, $module = false) {
        self::$currentPolicy = $id;
        self::$currentModule = $module;
    }
    public static function getPolicyId() { return self::$currentPolicy; }
    public static function getModuleId() { return self::$currentModule; }

    # Включение режима упрощенного процесса
    public static function setLightProcess($prcMode = 1) {
        self::$light_process = $prcMode;
    }
    # Запрос, включен ли режим упрощенного процесса
    public static function isLightProcess() {
        return self::$light_process;
    }
    # вернет описание модуля
    public static function getModuleDescription($module) {
        if(isset(self::$_plugins[$module]) && method_exists(self::$_plugins[$module], 'getDescription'))
            return self::$_plugins[$module]->getDescription();
        return '';
    }
    # установить режим "работает задание по расписанию"
    public static function setRunningJob($param = TRUE) {
        self::$in_job = $param;
    }
    public static function isRunningJob() {
        return self::$in_job;
    }
    public static function docFlowAccess() {
        if (self::$docflow_access === null) {
            self::$docflow_access = self::$auth->getAccessLevel(self::RIGHT_DOCFLOW);
        }
        return self::$docflow_access;
    }
    /**
    * получить список ИД "мета-подразделений"
    *
    * @param mixed $plugin
    * @param mixed $typesFilter - если надо отобрать только "БАНКИ", задать "1", только "АГЕНТЫ" - "100",
    * "Клиенты" - 110, можно список через зпт : "1,100"
    */
    static public function getMetaDepts2($plugin=NULL, $typesFilter=false) {
        $ret = array();
        $where = ($typesFilter ? "b_metadept IN($typesFilter)" : 'b_metadept>=1');
        $baseList = AppEnv::$db->select(PM::T_DEPTS,array(
            'fields'=>'dept_id'
            ,'where'=> $where
            ,'associative'=>1
            ,'orderby'=>'dept_name'
        ));
        # echo "sql: ".AppEnv::$db->getLastQuery() .'<br>err: '. AppEnv::$db->sql_error();
        if (is_array($baseList)) foreach($baseList as $item) {
            $ret[] = $item['dept_id'];
        }
        return $ret;
    }
    static public function getMetaDepts($plugin=NULL) {

        ##  Больше этимподходом не пользуюсь, все из таблицы подразд - по b_metadept

        if (!$plugin) {
            return array_keys(self::$meta_depts); # все зарегистрированные!
        }
        $ret = array();
        foreach (self::$meta_depts as $deptid => $dept_plugins) {
            if (in_array($plugin,$dept_plugins)) $ret[] = $deptid;
        }
        return $ret; # только те мета-подразд-я, на кот. настроен плагин с переданным ID
    }
    /**
    static public function getProductRange($codirovka) {
        foreach(self::$_productRanged as $id=>$item) {
           if(in_array($codirovka, $item)) return $id;
        }
        return $codirovka;
    }

    public static function authHookLogin() {
    	WriteDebugInfo("authHookLogin called");
    	return false;
	}
    **/
    # инициализация рабочих переменных
    static public function init($handleAuthRequests=TRUE) {
        self::initFolders(ALFO_ROOT);
        self::$warnings = [];
        if (defined('DEBUG_FOR_IP') && !empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']===constant('DEBUG_FOR_IP')) {
            self::$_debug = 10;
        }

        if (self::$_init_done) return;
        self::$_init_done = true;
        # включаю отдадки из настроек
        $debPlcUt = self::getConfigValue('z_debug_plcutils',0);
        if($debPlcUt) PlcUtils::$debug = $debPlcUt;

        if (defined('IF_LIMIT_WIDTH'))  self::$IFACE_WIDTH = constant('IF_LIMIT_WIDTH');
        # self::$_debug = TRUE; // Turn ON for debug logging
        parent::init(FALSE);
        if(superAdminMode()) {
            DneAssist::init();
        }

        if (is_object(self::$auth)) {
        	self::$auth->addUserManager(self::RIGHT_USERMANAGER); // global user manager right
            if (self::$auth->userid > 0 && self::$auth->deptid > 0) {
                self::$userMetaType = OrgUnits::getMetaType(self::$auth->deptid);
            }
            # writeDebugInfo("auth TODO my id:", self::$auth->userid);
        }
        include_once('Mobile_Detect.php');
        $detect = new Mobile_Detect();
        self::$mobile = $detect->isMobile();

        self::addTplFolder(ALFO_ROOT . 'app/tpl/');
        # конструктор стр.программ (из НСЖ.nsj)
        if(is_dir(ALFO_ROOT . 'iconst/tpl/')) self::addTplFolder(ALFO_ROOT . 'iconst/tpl/');

        if (class_exists('PolicyModel')) PolicyModel::init();
        parent::setTablesPrefix(self::TABLES_PREFIX);
        if(($dmob = self::getConfigValue('sys_detectmobile'))) {
            if(intval($dmob)===1) {
                # require_once('Mobile_Detect.php');
                # $detect = new Mobile_Detect;
                self::$isMobile   = self::$mobile;
                self::$deviceType = self::$isMobile ? ($detect->isTablet() ? 'tablet' : 'phone') : 'computer';
            }
            elseif (intval($dmob)>1) {
                self::$isMobile = true;
                self::$deviceType = ($dmob==2) ? 'tablet' : 'phone';
            }
            HeaderHelper::setMobileStyle(self::$isMobile);
        }
        if (!constant('IN_BITRIX')) {
	        if($idledays = self::getConfigValue('sys_block_idleusers')) self::$auth->setAutoBlockIdle($idledays);
	        if($traceact= self::getConfigValue('sys_traceactivity')) {
	            include_once('class.traceactivity.php');
	            self::$_tracer = new TraceActivity(array(
	                'tableprefix'=>self::TABLES_PREFIX
	               ,'dbobject'   => self::$db
	               ,'actions'    => array('auth_action','plg','action','plg_action','adm_action_type','p')
	            ));
	            # $actions = array('auth_action','plg_name','action','plg_action','adm_action_type','p');
	            # self::$_tracer->setActions($actions);

	        }
		}
        $usePswPolicy = (USE_PSW_POLICIES && self::isStandalone());
        $authopt = [ 'use_psw_policies' => $usePswPolicy];

        if(self::isStandalone() && class_exists('PasswordManager')) {
            PswPolicies::activatePswManager($authopt);
            # include(__DIR__ . '/activate_pswmgr.php');
        }

		if (is_object(self::$auth)) {
	        self::$auth->setOptions($authopt);
	        self::$auth->handleRequests(); # handle all 'auth_action' requests first!

	        if (SuperAdminMode()) self::enableConfigPage();
            /*
			# таблицы, которые может править супер-операционист
	        AppEnv::addRefPrivileges(PM::RIGHT_SUPEROPER, # was 'bank:superoper'
	          [ 'alf_countries','alf_product_config','alf_dept_product','stmt_ranges', 'alf_agmt_risks',
                PM::T_PROMOACTIONS, PM::TABLE_TRANCHES, PM::T_PROFESSIONS
              ]
	        );
            */

	        # добавляем в админ-панель списки системных таблиц
            if($usePswPolicy) {
	            self::addTableSet('acl', [self::TABLES_PREFIX.'acl_roles',
	                self::TABLES_PREFIX.'acl_rolerights',self::TABLES_PREFIX.'acl_rightdef',
	                self::TABLES_PREFIX.'pswpolicies'
	            ]);
            }
            else {
                self::addTableSet('acl', [self::TABLES_PREFIX.'acl_roles',
                    self::TABLES_PREFIX.'acl_rolerights',self::TABLES_PREFIX.'acl_rightdef',
                ]);
            }
	        self::addTableSet('help', array(self::TABLES_NEWPREFIX.'wikiarticles',self::TABLES_NEWPREFIX.'wikifiles'));

	        if (!isAjaxCall() && !empty($_SERVER['REMOTE_ADDR'])) {
	            self::buildMainMenu();
                if(isset($_SESSION['ALFO_LOGON_HANDLED']) && $_SESSION['ALFO_LOGON_HANDLED'] === 'NO') {
                    # первый вход в ALFO после авторизации в Битрикс, отрабатываю OnLogon
                    self::onAfterLogon();
                }
                self::handleAjaxErrors();
	        }
		}
    }
    public static function isMobileDevice($verbose=FALSE) {
        if(self::$mobile !== NULL) return self::$mobile;
        include_once('Mobile_Detect.php');
        $mobDetect = new Mobile_Detect();
        self::$mobile = $mobDetect->isMobile();

        /* include_once('MobileDetect/MobileDetect.php');
        if(class_exists('Detection\MobileDetect')) {
            $mobDetect = new Detection\MobileDetect();
            if($verbose) echo(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($mobDetect,1) . '</pre>');
            self::$mobile = $mobDetect->isMobile();
        }
        else self::$mobile = 0;
        */
        return self::$mobile;

    }
    public static function setPrimaryDept($id) {
        self::$primary_dept = $id;
    }
    # {upd/2024-04-22} wrapper для Appenv::$auth->getAccessLevel()
    public static function getRightLevel($rlist) {
        $ret = self::$auth->getAccessLevel($rlist);
        return $ret;
    }
    public static function buildMainMenu() {

      $admlev = self::$auth->getAccessLevel(AppEnv::ACTION_ADMIN);
      $suadmin = SuperAdminMode();
      $usradmin = ($admlev || $suadmin || self::$auth->getAccessLevel(self::RIGHT_USERMANAGER));
      $deptadmin = ($admlev || self::$auth->getAccessLevel(self::RIGHT_DEPTMANAGER));

      $suoper = (self::$auth->getAccessLevel(PM::RIGHT_SUPEROPER) || (self::$auth->getAccessLevel('nsj_oper')>=5));
      $mgrfiles = self::$auth->getAccessLevel(self::RIGHT_DOCFILES);
      $maxReportLevel = ReportHelper::ReportUserLevel();
      $agtSupport = \Libs\AgentUtils::isAgentSupport();
      # $agtReports = \Libs\AgentUtils::isAgentReports();
      $agtReports = ReportHelper::isAgentReports();
      $agtRepLevel = ReportHelper::AgentReportLevel();

      $bankReports = ReportHelper::isBankReports();
      $saleSupport = ($agtSupport || self::$auth->getAccessLevel('bank:superoper'));

      self::$_mainmenu = array();
      self::$_mainmenu['start'] = array('href'=>'./', 'title' => AppEnv::getLocalized('page.home'));

      # Заранее готовлю заглушки под "базовые" подменю:
      self::$_mainmenu['mnu_browse'] = array('title'=> AppEnv::getLocalized('mnu-browse'));
      self::$_mainmenu['inputdata']   = array('title' => AppEnv::getLocalized('mnu-input'));
      self::$_mainmenu['mnu_reports'] = array('title'=> AppEnv::getLocalized('mnu-reports'));
      self::$_mainmenu['mnu_infographics']   = array('title'=>AppEnv::getLocalized('mnu-infographics'));
      self::$_mainmenu['mnu_export']  = array('title'=> AppEnv::getLocalized('mnu-export'));
      self::$_mainmenu['mnu_admin']   = array('title'=>AppEnv::getLocalized('mnu-admin'));
      self::$_mainmenu['mnu_utils']   = array('title'=>AppEnv::getLocalized('mnu-utils'));
      self::$_mainmenu['mnu_docs']    = array('title'=>AppEnv::getLocalized('mnu-help'));

      include(__DIR__ . '/app_menu.php');

      if (SuperAdminMode() && is_file(__DIR__. '/admin_menus.php')) {
          include(__DIR__. '/admin_menus.php');
      }
      # self::runPlugins('infographics'); # дорисует подменю mnu-infographics если есть чем 'mnu_infographics'
      self::$mainmenuHtml = '';
      # if (class_exists('PolicyModel')) PolicyModel::updateUI();
      # if (self::$auth->SuperVisorMode()) file_put_contents('__mainmenu.ttt', print_r(AppEnv::$_mainmenu,1)); # debug
      self::generateMenu(self::$_mainmenu,self::$mainmenuHtml);
      return self::$mainmenuHtml;
    }

    # @since 2.18 получить объект указанного плагина
    public static function getPlugin($plgid) {
        if (!array_key_exists($plgid, self::$_plugins)) return FALSE;
        return self::$_plugins[$plgid];
    }
    # @since 2.18 получить объект бэкенда указанного логина
    public static function getPluginBackend($plgid) {
        if (!array_key_exists($plgid, self::$_plugins)) return FALSE;
        if (method_exists(self::$_plugins[$plgid], 'getBackend'))
            return self::$_plugins[$plgid]->getBackend();
        else die("$plgid - no getBackend Module!");
        return FALSE;
    }

    # добавляю JS модуль загрузки файлов на сервер
    public static function addAjaxUploader() {
        useJsModules('simpleajaxuploader');
    }
    /**
    * Формирует список всех подразделений, вложенных в список данных подразделений
    *
    * @param mixed $deptarray ИД стартового подразделенияю Если 0 - стартует со списка "головных" подразд.
    * @param mixed $onlyDirectChild - если не ноль, вернет только "детей" первого уровня вложенности
    */
    public static function getDeptChildren($startdept=0, $onlyDirectChild=false) {
        return OrgUnits::getDeptChildren($startdept, $onlyDirectChild);
    }
    /**
    * Выводит структуру подразделений [начиная с указанного, если передан ID ]
    *
    * @param mixed $deptid ИД стартового подразделения
    *
    public static function getDeptTreeHtml($deptid=0, $level=0) {

        $myaclev = self::$auth->getAccessLevel(AppEnv::ACTION_ADMIN);

        $mydepts = array();
        if($myaclev<=1) {
            $mydepts = self::getDeptChildren(self::$auth->deptid);
            $mydepts[] = self::$auth->deptid;
        }
        $ret = '';
        if($level>32) {
            WriteDebugInfo('Бесконечная вложенность подразделений, id=',$deptid);
            return '';
        }
        if(!$deptid) {
            $depts = AppEnv::$db->GetQueryResult(self::TABLES_PREFIX.'depts','dept_id,dept_name,b_active',array('parentid'=>0),1,1,0,'dept_name');
        }
        else {
            $depts = AppEnv::$db->GetQueryResult(self::TABLES_PREFIX.'depts','dept_id,dept_name,b_active',array('parentid'=>$deptid),1,1,0,'dept_name');
        }
        if(is_array($depts)) {
            $ret .= "<table id='table_depttree' border='0' cellspacing='1' cellpadding='2'>";
            foreach($depts as $dept) {
                $tclass = $dept['b_active'] ? '' : ' inactive';
                $txt = "$dept[dept_name] ($dept[dept_id])";
                if($myaclev>=AppEnv::ROLE_ADMIN || in_array($dept['dept_id'],$mydepts)) $txt = "<span id=\"sp_$dept[dept_id]\" style='padding:1px 6px' onclick='showPeopleInDept($dept[dept_id])' class='pnt'  title='Кликнуть для просмотра агентов'>$txt</span>";
                $ret .= "<tr class=\"even\"><td class='nesting{$tclass}' id='tddept_$dept[dept_id]'>$txt" . self::getDeptTreeHtml($dept['dept_id'],($level+1)) . '</td></tr>';
            }
            $ret .= '</table>';
        }
        return $ret;
    }
    **/
    # Расщепляет длинную строку на нужные длины (длину), с учетом концов слов
    # если надо разбить на блоки в 30,24,40 букв, передать соотв.массив длин $lens = array(30,24,40)
    public static function WrapLongLine($src, $lens, $charset=false) {

        if($charset===true) $charset='UTF-8';
        if(strlen($src)<$lens[0] OR !$lens) return array($src);
        if(!is_array($lens)) $lens = array(intval($lens));
        $ret = array();
        $rest = $src;
        $ilen = 0;

        while($rest!='') {

            $restlen = $charset ? mb_strlen($rest,$charset) : strlen($rest);
            if($restlen<=$lens[$ilen] ) {
                $ret[] = $rest;
                break;
            }
            for($ioff=$lens[$ilen];$ioff>0;$ioff--) {
               $ichar = $charset ? mb_substr($rest,$ioff,1,$charset) : mb_substr($rest,$ioff,1);
               if($ichar==' ' OR $ichar=='"' OR $ichar=="'" OR $ichar=='-') {
                   $ret[] = $charset ? mb_substr($rest,0,$ioff,$charset) : mb_substr($rest,0,$ioff);
                   break;
               }
            }
            if($ioff<=0) $ioff = $lens[$ilen]; # если пробелов не нашел - режу точно по длине
            $rest = $charset ? mb_substr($rest,$ioff,100000, $charset) : mb_substr($rest,$ioff);
            $ilen = $ilen<count($lens)-1 ? ($ilen+1) : 0;
        }
        # WriteDebugInfo("WrapLongLine result:", $ret);
        return $ret;
    }

    public static function dispatch() {
        self::rememberUserAction();
        if (!empty(AppEnv::$_p['setdebug'])) {
            error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        }
        if (WebApp::$workmode>99 && !SuperAdminMode()) return;
        if (isset(AppEnv::$_p['p'])) {
            $pagename = trim(AppEnv::$_p['p']);
            if (is_callable("AppEnv::$pagename")) {
                self::setHandled();
                self::$pagename();
                if (self::isStandalone()) exit;
                return;
            }
        }
        parent::dispatch();
    }
    # запоминаю в сессии последнее д-вие (для монитора сессий)
    public static function rememberUserAction() {
        # writeDebugInfo("rememberUserAction start ", $_GET);
        $strAction = '';
        foreach(self::$_p as $key=>$val) {
            if(in_array($key,['p','plg','action', 'getfile']) || stripos($key, 'action') || strlen($strAction)<=60)
                if(is_scalar($val)) $strAction .= "$key:$val ";
            if(strlen($strAction)>60) break;
        }
        if($strAction) {
            if(isAjaxCall())
                $_SESSION['last_ajax'] = $strAction;
            else
                $_SESSION['last_action'] = $strAction;
        }
    }
    # получить "официальное" наим-е подразделения (для вывода в документы)
    public static function getOfficialDeptName($dept=0) {

        if(!$dept) $dept = self::$auth->deptid;
        $ret = AppEnv::$db->select(self::TABLES_PREFIX. 'depts', array('fields'=>'official_name,dept_name','where'=>array('dept_id'=>$dept)));
        if (!empty($ret[0]['official_name'])) $ret = $ret[0]['official_name'];
        elseif (!empty($ret[0]['dept_name'])) $ret = $ret[0]['dept_name'];
        else $ret = $dept;
        return $ret;
    }

    /**
    *  получить "доп" параметры подразделения (для вывода в документы) -
    * наши платежные реквизиты, реквизиты агента ...
    * @param mixed $deptid ИД "головного" подразделения (головного офиса банка и т.п.)
    * @param mixed $module
    * TODO: можно удаляить, переехала в OrgUnits::getOuRequizites()
    */
    public static function getDeptRequizites($deptid=0, $module='') {

        if(!$deptid) $deptid = OrgUnits::getPrimaryDept();
        $where = array('deptid'=>$deptid);
        $order = '';
        if ($module) {
            $where[] = "(module='' OR FIND_IN_SET('$module',module))";
            $order = 'module DESC';
        }
        $data = AppEnv::$db->select(PM::T_OU_PROPS, #  'alf_dept_properties'
            array('where'=>$where,'orderby'=>$order, 'singlerow'=>1)
        );
        # WriteDebugInfo("getDeptRequizites($deptid, $module):", $data);
        # return ("last sql:" . AppEnv::$db->getLastQuery());
        return $data;
    }

    public static function getDeptCity($deptid=0) {

        $curdept = ($deptid<=0) ? self::$auth->deptid : $deptid;
        $nest = 0;
        if (isset(self::$_cache['deptcity_'.$deptid])) return self::$_cache['deptcity_'.$deptid];

        while($nest++<20 && $curdept>0) {
            $dta = AppEnv::$db->select(PM::T_DEPTS
                ,array(
                  'fields'=>'parentid,city'
                 ,'where'=>array('dept_id'=>$curdept),'singlerow'=>1
                )
            );
            if(!isset($dta['city'])) return $curdept;
            if(!empty($dta['city'])) {
                self::$_cache['deptcity_'.$deptid] = $dta['city'];
                return $dta['city'];
            }
            if (empty($dta['parentid']) || $dta['parentid'] == $curdept) {
                self::$_cache['deptcity_'.$deptid] = '';
                return '';
            }
            $curdept = $dta['parentid'];
        }
    }
    /**
    * Получить ближайшее непустое значение атрибута подразделения, в иерархии снизу вверх
    * @since 1.30.107
    * @param mixed $propid имя поля, кот.хотим получить
    * @param mixed $deptid ИД подразделения или 0 чтобы начальное подр = подр.текущего юзера
    */
    public static function findDeptProp($propid, $deptid=0) {
        # if($propid==='city') writeDebugInfo("findDeptProp city for $deptid");
        $curdept = ($deptid<=0) ? self::$auth->deptid : $deptid;
        $primary = self::getMetaDepts2();
        $nest = 0;
        while($nest++<20 && $curdept>0) {
            $dta = self::$db->select(PM::T_DEPTS
                ,array(
                  'fields'=>array('parentid',$propid)
                 ,'where'=>array('dept_id'=>$curdept),'singlerow'=>1
                )
            );

            if(!isset($dta[$propid])) return '';
            if(!empty($dta[$propid])) {
                return $dta[$propid];
            }
            if (empty($dta['parentid']) || $dta['parentid'] == $curdept || in_array($dta['parentid'], $primary)) {
                # if($propid==='city') writeDebugInfo("return 005");
                return '';
            }
            $curdept = $dta['parentid'];
        }
        return '';
    }

    # получаю рег. привязку подразделения ("msk" - Москва, "reg" - регионы)
    # @since 2.30
    public static function getDeptRegion($deptid=0) {
        $ret = self::findDeptProp('region', $deptid);
        if (empty($ret)) { # регион не настроен, пытаюсь опознать по городу (если Москва, то вернет msk)
            $city = self::findDeptProp('city', $deptid);
            if (!empty($city)) {
                if (mb_strtolower($city) === 'москва') $ret = 'msk';
                else $ret = 'reg';
            }
        }
        return $ret;
    }

    # превращает 2005-07-20 в "20   июля 2007 г."
    public static function dateVerbose($dt) {
        if (!is_scalar($dt) || intval($dt)<=0) return '';
        if (intval($dt)<1000) $dt = to_date($dt);
        $rm = array('','января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря');
        return mb_substr($dt,8,2) .'   '. $rm[intval(mb_substr($dt,5,2))] . ' '. mb_substr($dt,0,4) . ' г.';
    }

    /**
    *  Ищем дату транша от даты продажи ($datesale), в контексте категории продукта
    * @since 2017-08-30 - теперь транши могут быть свои у отдельных кодировок!
    *
    * @param mixed $datesale дата продажи полиса
    * @param mixed $prodcategory INDEXX | TREND
    * @param mixed $codirovka (опционально)- кодировка
    */
    public static function getDateTranche($datesale, $prodcategory, $codirovka='') {
        if (self::$_debug) WriteDebugInfo("getDateTranche ($datesale, $prodcategory, '$codirovka')...");
        if (!$datesale) $datesale = date('Y-m-d');
        $from = to_date($datesale);
        $search = array(
             'fields'=>'tranchedate'
             ,'where'=> "prodtype = '$prodcategory' AND ('$from' BETWEEN openday AND closeday)"
             ,'singlerow'=>1, 'associative'=>1
        );

        if (!$codirovka) $search['where'] .= " AND (codirovka='')";
        else {
            $search['where'] .= " AND (codirovka='' OR FIND_IN_SET('$codirovka',codirovka))";
            $search['orderby'] = 'codirovka DESC';
        }
        $dta = AppEnv::$db->select(PM::TABLE_TRANCHES, $search );
        if (self::$_debug) {
            WriteDebugInfo("getDateTranche('$datesale','$prodcategory', '$codirovka') result:", $dta);
            WriteDebugInfo("sql_error:", AppEnv::$db->sql_error(), ' sql:', AppEnv::$db->getLastQuery());
        }
        return (isset($dta['tranchedate'])? $dta['tranchedate'] : '0000-00-00');
    }

    /**
    * Регистрация модуля как доступного для выгрузки в LISA
    * (вызывать в самом конце инициализирующего скрипта plugins/{module}/{module}.php
    * @param mixed $moduleid ИД модуля
    */
    public static final function setExportable($moduleid) {
        if (!in_array($moduleid,self::$exportable_modules))
        	self::$exportable_modules[] = $moduleid;
    }

    public static function getExportableModules() {
		return self::$exportable_modules;
    }

    public static function AuditUserList($tableid,$act,$recid) {
        switch($act) {
            case 'delete': $opt='DELETE'; $txt = 'Удаление'; break;
            case 'doadd': $opt='ADD'; $txt = 'Добавление'; break;
            case 'doedit': $opt='UPDATE'; $txt = 'Изменение'; break;
        }
        AppEnv::logEvent("USERS.$opt","$txt учетной записи $recid",'',$recid);

    }
    /**
    * Андеррайтинг: Глобальная проверка на все условия
    * (подключаемые плагины, содержащие ф-цию checkForUnderwriting($params)
    * @param mixed $params
    */
    public static function checkForUnderwriting($params, $prefix='insr') {
        foreach ( self::$_plugins as $plgid => &$plg) {
            if (method_exists($plg, 'checkForUnderwriting')) {
                $uwresult = $plg->checkForUnderwriting($params, $prefix);
                if ($uwresult) return $uwresult;
            }
        }
        return FALSE;
    }
	public static function setSafeMbmode() {
		self:: $cur_mb_state = mb_internal_encoding();
		mb_internal_encoding('ASCII');
	}
	public static function unsetSafeMbmode() {
		mb_internal_encoding(self:: $cur_mb_state);
		self:: $cur_mb_state = '';
	}
	# moved from webapp.core
    public static function addFilesModule($module, $udFunction=FALSE) {
        self::$filesAllowedModules[$module] = $udFunction;
    }

    /**
    * Разбор строки вида "parname=[parval,prval2];parname2=someval2; ..."
    * вернет ассоц.массив ['parname' => [parval1,parval2], 'parname2 => someval2, ...
    * @param mixed $strg
    */
    public static function parseConfigLine($strg) {
        if (substr($strg,0,1) ==='{') { # json encoding
            $ret = @json_decode($strg,TRUE);
            return $ret;
        }
        $sarr = preg_split("/[;|]/", $strg,-1, PREG_SPLIT_NO_EMPTY);
        $ret = [];
        foreach($sarr as $item) {
            if(($ipos = strpos($item, '='))) {
               $skey = substr($item,0, $ipos);
               $svals = substr($item, $ipos+1);
               if (substr($svals,0,1)==='[' && substr($svals,-1)===']') {
                   $svals = preg_split("/[,]/",substr($svals,1,-1), -1);
               }
            }
            $ret[$skey] = $svals;
        }
        return $ret;
    }

    # page for showing loaded files
    public static function showfiles() {

        self::setPageTitle('mnu-showfiles');
        if (self::inBitrix()) {
            self::appendHtml('<h3>' . self::getLocalized('mnu-showfiles') . '</h3>');
        }
        $where = [];
        if (!SuperAdminMode()) {
            $pDept = OrgUnits::getPrimaryDept();
            $where[] = "(f_restrict = '' OR FIND_IN_SET('$pDept',f_restrict))";
        }
        $arr = self::$db->select(self::$table_uploadedfiles, array('where'=>$where, 'orderby'=>'category,id'));
        # WriteDebugInfo("files select:",self::$db->getLastQuery()); WriteDebugInfo("files list:",$arr);
        $categs = FileUtils::getFileCategories();
        $curcat = '***';
        $html = '';
        if(count($arr)) foreach($arr as $item) {
            if($item['category'] !== $curcat) {
                if($curcat !=='***') $html .= '</div>';
                $curcat = $item['category'];
                $html .= "<h4 class='darkhead'>".$categs[$curcat]."</h4><div class='lt' style='padding: 1em; '>";
            }
            $html .= "<a href=\"./?p=getfile&fid=$item[id]\">$item[description]</a><br>";
        }
        else $html .= self::getLocalized('no_available_files');
        self::appendHtml($html);

        self::finalize();

    }
    # проверяет, есть ли у юзера доступ к просмотру файла
    private static function checkFileAccess($finfo) {
        # WriteDebugInfo("checkFileAccess for ", $finfo);
        if ($finfo['f_restrict']=='') return TRUE; # head bank id list!
        if (SuperAdminMode()) return TRUE;
        $depts = explode(',', $finfo['f_restrict']);
        $pDept = OrgUnits::getPrimaryDept();
        if (!in_array($pDept, $depts)) return false;
        return 1;
    }
    /*
    public static function applyFilesRestrictions($flist) {
        $ret = array();
        # TODO: implement UDF restrictions
        return $flist;
    }
    */
    # Available files Editor/Loader
    public static function managefiles() {

        if(!SuperAdminMode() && !self::$auth->getAccessLevel(self::RIGHT_DOCFILES))
            ErrorQuit(self::getLocalized('err-no-rights'));

        include_once('astedit.php');

        $tbl = new CTableDefinition(PM::T_UPLOADEDFILES); # 'alf_uploadedfiles'
        # $tbl->setBlistSize('240px');
        # $tbl->setBrowseFilterFn('banki_usersfilter');
        if(self::isAjax()) {
            $tbl->MainProcess();
            exit;
        }

        self::drawPageHeader(self::getLocalized('mnu_managefiles'));
        $tbl->setBaseUri('./?p=managefiles');
        # TODO: filter only "banks" files !
        $txt = $tbl->mainProcess(1,1,1,1); # banki_isUserEditable
        if($txt) echo "<br>$txt<br>";
        self::finalize();
        exit;
    }

    # Обработка запроса на получение файла документа
    public static function getfile($idPar = FALSE, $checkAccess = TRUE) {
        if (!empty($idPar)) $fid = $idPar;
        else $fid = isset(self::$_p['fid']) ? self::$_p['fid'] : 0;
        if(!$fid) exit('Empty call (no file ID)');

        if(is_numeric($fid)) {
            $fileprefix = 'filebody'; # could be another pref...

            # check "per-moduleId" restrictions before send file.
            $finfo = self::$db->select(PM::T_UPLOADEDFILES ,array('where'=>array('id'=>$fid),'singlerow'=>1));
            if($checkAccess) {
                $access = self::checkFileAccess($finfo);
                if(!$access) exit(self::getLocalized('err-no-rights'));
            }
            $flFolder = self::getFocumentsFolder();
            # $flFolder = self::$FOLDER_FILES;
            $fullname = $flFolder . $fileprefix . '-'.str_pad($fid, 8,'0',STR_PAD_LEFT) . '.dat';
            $sendname = urlencode(str_replace(' ','_',$finfo['filename']));
        }
        else {
            $parts = explode('|',$fid);
            # стандартные файлы отправляю прямо здесь (согласие на ПДн 2х типов):
            if($parts[0] === 'soglasie_pdn') {
                if($parts[1] <='1')
                    $fullname = self::getAppFolder('templates/misc/') . 'EDO-Soglasie-PDn.pdf';
                elseif($parts[1] =='2') # ПДН взрослый + ребенок
                    $fullname = self::getAppFolder('templates/misc/') . 'EDO-soglasie-PDn-adult-child.pdf';
                else # if($parts[1] =='3') # тольео ПДн на ребенка
                    $fullname = self::getAppFolder('templates/misc/') . 'EDO-Soglasie-PDn-child.pdf';


                $sendname = 'soglasie-PDn.pdf';
                # writeDebugInfo("согласие на ПДн: $fullname");
            }
            elseif($parts[0] === 'unified_pdn') {
                # Запрошен унив.комплект ПДн: unified_pdn|moduleid|policyid
                $module = $parts[1] ?? '';
                $plcid = $parts[2] ?? 0;
                if(empty($module) || $plcid<=0) exit("Неверный вызов getfile!");
                $bkend = self::getPluginBackend($module);
                $bkend->generateUnifiedPdn($plcid);
                exit;
            }
            else { # все остальные "префиксы" считаю именем плагина и отправляю запрос в него
                $module = $parts[0];
                $fileType = $parts[1] ?? '';
                $fileId = $parts[2] ?? '';
                $bkend = self::getPluginBackend($module);
                if(is_object($bkend) && method_exists($bkend, 'getFile')) {
                    $bkend->getFile($fileType,$fileId);
                    exit;
                }
                else exit("Ошибка: не реализован [$module].backend::geFile()");
            }
        }
        if(!file_exists($fullname)) exit(self::getLocalized('err_file_not_found'));
        if(defined('IN_BITRIX') && constant('IN_BITRIX')) self::clear_ob();
        self::sendBinaryFile($fullname,$sendname);
        exit;
    }

    public static function deptProdParams($module, $headdept, $codirovka='') {
        $where = array('module'=>$module, 'deptid'=>$headdept,'b_active'=>1);
        if ($codirovka) $where[] = "(prodcodes='' OR FIND_IN_SET('$codirovka',prodcodes))";
        $ret = AppEnv::$db->select('alf_dept_product',
          array('where'=>$where, 'singlerow'=>1,'orderby'=>'prodcodes DESC')
        );
        return $ret;
    }

    /**
    * Удаляет из папки tmp/ все файлы созданные ранее чем NN часов назад
    * @param $hours - кол-во часов жизни файлов в папке
    */
    public static function cleanTmpFolder($hours = 2) {
        if ($hours <=0) $hours = 1;
        $tmfiles = glob(ALFO_ROOT . "tmp/*.*");
        $maxTimeStamp = strtotime ("-$hours hours");
        foreach($tmfiles as $tmpfile) {
            if (is_file($tmpfile) && filemtime($tmpfile) < $maxTimeStamp)
                @unlink($tmpfile);
        }
    }
    # Устанавливаю значение для нужного "состояния/параметра"
    public static function setUsrAppState($key, $value) {
        self::$_appStates[$key] = $value;
    }
    public static function getUsrAppState($key) {
        return (isset(self::$_appStates[$key])? self::$_appStates[$key] : null);
    }

    // AppEnv::log($any_params) - write to _debuginfo.log
    public static function log() {
        call_user_func_array('writeDebugInfo', func_get_args());
    }

    public static function isProdEnv() {
        $env = defined('APP_ENVIRONMENT') ? constant('APP_ENVIRONMENT') : 'prod';
        return ($env === 'prod');
    }

    public static function getFileExt($fname) {
        if(empty($fname)) return '';
        $path_info = pathinfo($fname);
        return mb_strtolower($path_info['extension']);
    }

    public static function logEvent($stype, $stext='', $deptid='', $itemid=0, $uid=FALSE,$module = '' ){

        global $authdta;
        $ipaddr = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'SYSTEM';
        if(!$uid && $uid === FALSE) $uid = self::getUserId();
        if( !$deptid ) {
            $deptid = self::$auth->deptid;
            if(!$deptid && $uid>0) $deptid = self::$db->GetQueryResult($authdta['table_users'],$authdta['usrdeptid'],array($authdta['usrid']=>$uid));
        }
        if (!$uid && $uid===FALSE) {
            if (!empty($_SESSION['SESS_AUTH']['NAME'])) {
                # Битриксовая сессия - хранит ФИО учетки ВНЕ ALFO
                $uid = $_SESSION['SESS_AUTH']['NAME'];
                /*
                $uid = MakeFIO($_SESSION['SESS_AUTH']['LAST_NAME'],
                   $_SESSION['SESS_AUTH']['FIRST_NAME'],
                   $_SESSION['SESS_AUTH']['SECOND_NAME']
                );
                */
            }
        }
        if(self::IsApiCall()) $stext .= ' [API]';
        $insdta = [
            'evdate' => date('Y-m-d H:i:s')
           ,'ipaddr' => $ipaddr
           ,'userid' => $uid
           ,'deptid' => $deptid
           ,'userid' => $uid
           ,'itemid' => $itemid
           ,'evtype' => $stype
           ,'evtext' => $stext
        ];
        if(!empty($module)) $insdta['module'] = $module;
        elseif (!empty(self::$_plg_name)) $insdta['module'] = self::$_plg_name; # since 0.8.031

        $ret = self::$db->insert(\PM::T_EVENTLOG, $insdta);
        return $ret;
    }
    /**
    * Добавляет "зависимую" таблицу для блокировки удаления записей в родительской
    *
    * @param mixed $parent таблица- Родитель (primary keys holder)
    * @param mixed $child - дочерняя таблица (сожержит записи с foreign keys)
    * @param mixed $fieldPK - PK field в родительской таблице
    * @param mixed $fieldFK - FK field в дочке
    * @param mixed $msgtext
    */
    public static function addTableDependency($parent, $child, $fieldPK, $fieldFK, $msgtext='') {

        if(!array_key_exists($parent, self::$tableDeps)) self::$tableDeps[$parent] = [];
        if(!array_key_exists($child, self::$tableDeps[$parent])) self::$tableDeps[$parent][$child] = [];
        self::$tableDeps[$parent][$child][] = [$fieldPK, $fieldFK, $msgtext];
    }

    # вернет список "зависимостей" для указанной таблицы или FALSE (вызывается в astedit перед удалением записи)
    public static function getTableDependencies($table) {
        return (array_key_exists($table, self::$tableDeps)) ? self::$tableDeps[$table] : FALSE;
    }
    public static function viewStmtState($stateid) {
        if (isset(PolicyModel::$stmt_states[$stateid])) return PolicyModel::$stmt_states[$stateid];
        return "[$stateid]";
    }

    # {upd/2020-09-17} если у учетки включен флажок "тестовая", вернуть TRUE
    public static function isTestAccount($userid=0) {
        if (!empty($userid) && $userid != self::$auth->userid) {
                $usrdta = AppEnv::$db->select(PM::T_USERS,['fields'=>'is_test',
                  'where'=>['userid'=>self::$auth->userid],'singlerow'=>1]
                );
                return !empty($usrdta['is_test']);
        }
        else {
            if (!isset($_SESSION['test_account'])) {
                $usrdta = AppEnv::$db->select(PM::T_USERS,['where'=>['userid'=>self::$auth->userid],'singlerow'=>1]);
                $_SESSION['test_account'] = $isTest = !empty($usrdta['is_test']);
            }
            return (!empty($_SESSION['test_account']));
        }
    }
    # секретная - показать что в сессии (и включить отладку в сессии)
    public static function viewSess() {
        if (isset(self::$_p['setdebug'])) { # вкл/выкл отладочный режим для сессии
            $addText ='';
            $dbgFile = ALFO_ROOT . 'tmp/_debugsess_'.self::$auth->userid.'.log';
            if(self::$_p['setdebug'] ==='171') {
                $_SESSION['userdebug'] = 1;
                if (is_file($dbgFile)) @unlink($dbgFile);
            }
            elseif(self::$_p['setdebug'] ==='172') {
                $_SESSION['userdebug'] = 2;
                if (is_file($dbgFile)) @unlink($dbgFile);
            }
            else unset($_SESSION['userdebug']);

            if (!empty($_SESSION['userdebug'])) $addText = "отладка - уровень ".$_SESSION['userdebug'];

        }
        appenv::appendHtml('_SESSION: <pre>' . print_r($_SESSION,1). '</pre>'.$addText);
        AppEnv::finalize();
    }

    # показать журнал отладки
    public static function viewDebug() {

        $dbgFile = ALFO_ROOT . 'tmp/_debugsess_'.self::$auth->userid.'.log';
        if(is_file($dbgFile)) AppEnv::appendHtml(file_get_contents($dbgFile));
        else AppEnv::appendHtml('Файл журнала отладки не найден');
        AppEnv::finalize();
    }
    # сессия под режимом отладки?
    public static function isDebug() {
        return (isset($_SESSION['userdebug']) ? $_SESSION['userdebug'] : FALSE);
    }

    #  мой ИД учетки
    public static function myId() {
        return (isset(self::$auth->userid)? self::$auth->userid : 0);
    }
    public static function getMyMetaType() { return self::$userMetaType; }
    public static function getUserId() { return self::$auth->userid; }
    public static function getUserDept() { return self::$auth->deptid; }

    # локальная среда разработки?
    public static function isLocalEnv() {
        return is_dir('C:/');
    }
    # отрежет https:// и др. протоколы
    # @since 2.66
    public static function stripProtocol($url) {
        $ret = strtr($url, ['http://'=>'','https://'=>'', 'ftp://'=>'']);
        return $ret;
    }
    # {upd/2023-01-18}
    public static function logSqlError($file, $func, $line=0) {
        writeDebugInfo("SQL ERROR in $file, $func()/$line, SQL: ", self::$db->getLastQuery(), "\n  error:" . self::$db->sql_error() );
    }
    public static function isDeadModule($module) {
        return in_array($module, PM::$deadProducts);
    }
    # {upd/2023-11-01} - ф-ция определения уровня прав юзера по данному ИД
    public static function accessLevel($rightId) {
        $ret = self::$auth->getAccessLevel($rightId);
        return $ret;
    }
    # callback, должна вызываться после входа в систему
    public static function onAfterLogon($context = FALSE) {
        # writeDebugInfo("Logon executed... invins: [" . in_array('invins', array_keys(self::$_plugins)). ']');
        /*
        if(isAjaxCall()) {
            writeDebugInfo("ajax call, skip logon callback");
            $_SESSION['ALFO_LOGON_HANDLED'] = 'NO';
            return; # AJAX запросы, включая момент авторизации - игнорим!
        }
        */
        self::performAfterLogon($context);
    }
    public static function performAfterLogon($context = FALSE) {

        OrgUnits::checkBankDept($context); # для учетки в банке - рег.напоминание о смене точки проджаж при необх.
        foreach(self::$_plugins as $plgid=>$plgObj) {
            if(method_exists($plgObj, 'onAfterLogon')) {
                $cbResult = $plgObj->onAfterLogon($context);
                if($cbResult === 'STOP') break; # если надо прервать цепочку вызовов, callback должен вернуть 'STOP'
            }
        }
        unset($_SESSION['ALFO_LOGON_HANDLED']);
    }
    # {upd/2024-04-16} обоаботка ошибок в AJAX запросах (moved from basefunc.php)
    public static function handleAjaxErrors($alertState0 = FALSE) {

        $alert0 = ($alertState0) ? '' : "if(x.status!=0) ";
        $jscode = <<<EOF
$.ajaxSetup({ error:function(x,e){
  var errtext = 'Unknown Error.<br>'+x.responseText;
  in500 = false;
  console.log("ajax fail :", x);
  if(x.status==0){ errtext='You are offline!!<br> Please Check Your Network.'; }
  else if(x.status==404){ errtext='Requested URL not found.'; }
  else if(x.status==500){
    errtext='Internal Server Error (500):<br>'+ x.responseText;
    if(!in500) {
      in500 = true;
      $.post("./?p=appalerts&alertaction=error500", {"errtext":x.responseText}, function(data) {console.log(data);in500=false;});
    }
  }
  else if(e=='parsererror'){ errtext='Error.\\nParsing JSON Request failed : '+ x.responseText;}
  else if(e=='timeout'){ errtext='Request Time out.'; }
  if(typeof(ShowSpinner)!='undefined') ShowSpinner(false);
  ajax_busy = ajaxbusy = false;
  $alert0 showMessage('Ошибка!', errtext, 'msg_error', 600);
}});
EOF;
        AddHeaderJsCode($jscode,'ready');
    }
    # для загрузчика курсов валют!
    public static function getCurrencyList() {
        return AppLists::getCurrencyList();
    }
    # получает режим активности авто-оплаты рассрочек
    public static function AutoPaymentActive($super = FALSE) {
        $cfgVal = self::getConfigValue('alfo_auto_payments');
        if(empty($cfgVal)) return FALSE;
        if($cfgVal>=10) return TRUE;
        # режим тестирования - доступен только для супер-админа
        else return $super;
    }
    /**
    * получает папку для складывания загруженных файлов правил, шаблонов...
    *
    */
    public static function getFocumentsFolder() {
        $ret = 'files/';
        $sysPath = self::getConfigValue('upload_root_folder');
        if(!empty($sysPath)) {
            $sysRet = $sysPath . 'files';
            if(!is_dir($sysRet)) {
                $created = @mkdir($sysRet, 0777, TRUE);
            }
            if(is_dir($sysRet)) return "$sysRet/";
        }
        return $ret;
    }
    # {upd/2025-01-24} определяет, активен ли режим "Избранное"
    public static function isFavActive() {
        return (class_exists('Favority') && self::getConfigValue('enable_favorities'));
    }
    # overrides webDev::setApiCall()
    public static function setApiCall($code=true) {
        parent::setApiCall($code);
        if(is_integer($code) && $code>1 ) {
            if(!session_id()) session_start();
            # передали учетку, заношу в сессию ИД учетки и подразд. (аутентификация без пароля)
            $_SESSION['arjagentuserid'] = self::$client_userid = $code;
            $_SESSION['arjagentdeptid'] = self::$client_deptid = self::$db->select(PM::T_USERS, [
              'where'=>['userid'=>$code], 'fields'=>'deptid', 'singlerow'=>1, 'associative'=>0
            ]);
        }
    }
    # получит реальный статус блокировки учетки для показа в ALFO-списке
    public static function getUserStatus($usrid=0) {
        global $ast_datarow;
        $blk = $ast_datarow['b_blocked'] ?? 0;
        $btxid = $ast_datarow['bitrix_id'] ?? 0;
        $ret = ($blk ? 0 : 1);
        $pref = '';
        if(defined('BITRIX_DB') && !empty($btxid)) {
            $db = constant('BITRIX_DB');
            $btxUsr = AppEnv::$db->select("$db.b_user", ['where'=>['ID'=>$btxid],'fields'=>'ACTIVE,BLOCKED', 'singlerow'=>1]);
            if(isset($btxUsr['ACTIVE'])) {
                $pref = 'BX:';
                $ret = $btxUsr['ACTIVE'] ==='Y' ? 1 : 0;
                if(!empty($btxUsr['BLOCKED']) && $btxUsr['BLOCKED'] ==='Y') $ret = 0;
            }
        }
        return $pref.($ret ? 'Активна':'Блокир');
    }

    # обрезает слишком длинные строки в элементах массива (рекурсивно), например BASE64 строки для краткости
    public static function array_shorten_strings($arValues, $maxLen=200) {
        if(is_array($arValues)) foreach($arValues as $key=>&$val) {
            $val = self::array_shorten_strings($val,$maxLen);
        }
        else {
            $vlen = strlen($arValues);
            if($vlen>$maxLen) $arValues = substr($arValues,0,$maxLen) . "...($vlen Bytes)";
        }
        return $arValues;
    }

} # AppEnv end

define('APPVERSION',"Front-Office v." . APP_VERS. ' ('.APPDATE.')');

# WebApp::$traceMe = 10;
# преобразование email адресов перед отправкой письма:
AppEnv::setEmailConvertor('ClientUtils::checkEmailAddr');

HeaderHelper::setAppVersion(to_date(APPDATE));

if (constant('IN_BITRIX') > 1) {
    AppEnv::$FOLDER_APP = __DIR__ . DIRECTORY_SEPARATOR;
    AppEnv::$FOLDER_ROOT = dirname(__DIR__) . DIRECTORY_SEPARATOR;
}
else {
    AppEnv::$FOLDER_ROOT = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    set_error_handler('ErrorHandler'); # activate error intersepting
    register_shutdown_function( 'shutdownHandler' );
    define('NEW_JQUERY',FALSE);
}
$mycomp = AppEnv::getConfigValue('comp_title');
define('APPCOPYRIGHT',$mycomp);

# подключаю авто-загрузчик: app/, include_path, или c namespace=folder
spl_autoload_register(function ($name) {
    $loname = strtolower($name);
    if (strpos($loname, '\\')!==FALSE) {
        # libs\classname, \plugins\plgname\ClassName - подключаю plugins/plgname/classname.php
        $items = preg_split( "/[\\\]/", $loname, -1, PREG_SPLIT_NO_EMPTY );
        $flname = ALFO_ROOT . implode('/',$items). '.php';
        if(is_file($flname)) include_once($flname);
    }
    else {
        if (is_file(__DIR__ . "/$loname.php"))
            include_once(__DIR__ . "/$loname.php");
        elseif (is_file(__DIR__ . "/class.$loname.php"))
            include_once(__DIR__ . "/class.$loname.php");
        else {
            # модуль из папок include_path
            WebApp::isModuleInPath("$loname.php", TRUE) or WebApp::isModuleInPath("class.$loname.php", TRUE) ;
        }
    }
});

if(file_exists(AppEnv::$FOLDER_ROOT.'cfg/dbconnect.inc.php'))
	include_once(AppEnv::$FOLDER_ROOT.'cfg/dbconnect.inc.php');
elseif(file_exists('../dbconnect.inc.php')) include_once('../dbconnect.inc.php');

/*
# защита от коротких сессий на вирт.хостинге
if(!empty($sitecfg['folder_sessions']) && is_dir($_SERVER['DOCUMENT_ROOT'].$sitecfg['folder_sessions']))
  ini_set('session.save_path', $_SERVER['DOCUMENT_ROOT'].$sitecfg['folder_sessions']);
*/
# Load Localization strings:
$lang = AppEnv::DEFAULT_LANG; # empty($_COOKIES['userlang']) ? AppEnv::DEFAULT_LANG : trim($_COOKIES['userlang']);

AppEnv::SetLocalization($lang);

include_once(ALFO_ROOT . 'app/auth_tabledefs.php');
$auth_iface = [];
$auth_iface['server-time'] = '';

require_once('waRolemanager.php');
$options = array( 'tableprefix' => AppEnv::TABLES_PREFIX);
$rolemgr = new WaRolemanager($options);

if (!class_exists('CAuthCorp')) {
    if (constant('IN_BITRIX')> 1)
        require_once('as_authcorp_btx.php');

    else
        require_once('as_authcorp.php');
        # if (!class_exists('PasswordManager')) require_once('as_passwordmgr.php');
}
require_once('dbaccess.pdo.php');

$GLOBALS['as_dbengine'] = new CDbEngine();
if(AppEnv::$_debug) {
    WriteDebugInfo('call URI:', $_SERVER['REQUEST_URI']);
    WriteDebugInfo('_GET:', $_GET);
    WriteDebugInfo('_POST:', $_POST);
}

if (!defined('API_CALL')) {

    $usingStyles = [HeaderHelper::$bootstrapIconscss,HeaderHelper::$bootstrapcss, HeaderHelper::$maincss, HeaderHelper::$zettacss];

    if (constant('IN_BITRIX')) {
        UseJsModules('jquery,dropdown,ui,asjs,floatwindow,js/alfo_core,js/bootstrap');
        $js_sticky = '';
        WebApp::setDropdownMenuClass('dropdown');

        array_push($usingStyles, HeaderHelper::$cssinbitrix); #add css for bitrix
    }
    else {
        UseJsModules('jquery,dropdown,ui,datepicker,asjs,floatwindow,js/alfo_core,sticky,js/bootstrap');
        $js_sticky = '$("#toplinks").sticky({topSpacing:-2});';
        WebApp::setDropdownMenuClass('dropdown'); # jdmenu
    }

    foreach ($usingStyles as $style) {
        UseCssModules('css/' . $style);
    }

    $today = date('d.m.Y');
    $todayYmd = date('Y-m-d');
    // старый js код для datepicker:
    // $('input.datebirth').not("[readonly]").on('click', function(){ $(this).datepicker({yearRange:'-80:+0',changeYear: true}).change(DateRepair);});
    //$('.datepicker').live('click', function() { $(".datepicker" ).datepicker(); });
    // $(document).on('focus',"input.datefield", function(){ $(this).datepicker({changeYear: true}); });

    $readyjs = <<< EOJS
    $("input.datefield").not("[readonly]").datepicker({yearRange:'-100:+35',changeYear: true}).change(DateRepair);
    $('input.datebirth').not("[readonly]").datepicker({yearRange:'-100:+0',changeYear: true}).change(DateRepair);
    $('input.number').change(NumberRepair);$js_sticky
    jsToday = '$today'; jsTodayYmd = '$todayYmd';
EOJS;

    AddHeaderJsCode($readyjs,'ready');
}

# $doctype = '';
# $tariftypes = false;

define('STARTPAGE','./'); # index.php
$auth = new CAuthCorp(1,1,array(
     'loadrolesfunc' => 'GetUserRoles'
    ,'appid' => (constant('IN_BITRIX')? 'alfo_' : 'arjagent')
    ,'showtime' => FALSE // ((constant('IN_BITRIX')) ? 0 : AppEnv::$ENABLE_SERVERTIME)
    ,'use_uithemes' => 1
    ,'confirm_logout' => FALSE
    ,'encryptpassword' => 1
    ,'supervisor_right' => 'alfo_super' # supervisor
#    ,'restore_password' => 1 # у незалогиненного юзера показывать ссылку "восстановить пароль"
));

# $auth->SetOnLogon('appAfterLogon'); # перешибает код подразделения, если агент с данным кодом есть в arjagent_agents
$auth->SetOnLogon('AppEnv::onAfterLogon'); # станд.коллбэк после входа

AppEnv::init();

if (!constant('IN_BITRIX') && AppEnv::isTracingActivity()) AppEnv::traceActivity();

$ifwidth = defined('IF_LIMIT_WIDTH') ? constant('IF_LIMIT_WIDTH')-66 : WebApp::$IFACE_WIDTH-20;
if ($ifwidth <= 0 || $ifwidth>720) $ifwidth= 720;

$ifaceTop = (constant('IN_BITRIX') ? '80':'10');
$headjS = "\n imgpath=\"" . IMGPATH. "\";\n gridimgpath = \"".FOLDER_JS ."ui-themes/".UI_THEME."/images/\";"
  . "\n ifaceWidth=$ifwidth;\nifaceTop=$ifaceTop;\n";

AddHeaderJsCode($headjS);

# auth additional functions, site-dependent
function SuperAdminMode(){
    if (AppEnv::$superadmin === NULL) AppEnv::$superadmin = AppEnv::$auth->IsSuperAdmin();
    return AppEnv::$superadmin;
}
# разрешать ли настройку парольных политик
function pwdPolicyEditable() {
	return (AppEnv::isStandalone() && SuperAdminMode());
}
# админ системы, но не обязат-но supervisor
function SysAdminMode() {
    return SuperAdminMode();
}

function SuperVisorMode() {
    return AppEnv::$auth->SuperVisorMode();
}


function GetEmployeeFIO($id) {
  return AppEnv::$auth->GetUserNameForId($id);
}

function MonthRus($nmon) {
  switch(intval($nmon)) {
    case 1 : return 'января';
    case 2 : return 'февраля';
    case 3 : return 'марта';
    case 4 : return 'апреля';
    case 5 : return 'мая';
    case 6 : return 'июня';
    case 7 : return 'июля';
    case 8 : return 'августа';
    case 9 : return 'сентября';
    case 10 : return 'октября';
    case 11 : return 'ноября';
    case 12 : return 'декабря';
  }
  return $nmon;
}
# Месяц - В Именительном падеже
function MonthRusImen($nmon) {
  switch(intval($nmon)) {
    case 1 : return 'январь';
    case 2 : return 'февраль';
    case 3 : return 'март';
    case 4 : return 'апрель';
    case 5 : return 'май';
    case 6 : return 'июнь';
    case 7 : return 'июль';
    case 8 : return 'август';
    case 9 : return 'сентябрь';
    case 10 : return 'октябрь';
    case 11 : return 'ноябрь';
    case 12 : return 'декабрь';
  }
  return $nmon;
}

function DataAudit($table,$action,$rec_id, $changedList = FALSE) {

  $opt = $txt = $postData = '';

  switch($action) {
    case 'delete': $opt='DELETE'; $txt = 'Удаление'; break;
    case 'doadd': $opt='ADD'; $txt = 'Добавление'; break;
    case 'doedit':
        $opt='UPDATE'; $txt = 'Редактирование';
        if (is_array($changedList) && count($changedList)) {
            $postData = ", изменены: " . implode(',', $changedList);
        }
        break;
  }
  AppEnv::logEvent("$table $opt","$txt записи".$postData,'',$rec_id);
}

function DocAccessForUser($rw, $action='inputdata') {
  global $auth;
  if(SuperAdminMode()) return 10;
  if($auth->getAccessLevel(PM::RIGHT_SUPEROPER)) return 10;
  $myaclev = $auth->getAccessLevel($action);
  if(empty($myaclev)) return 0;
  if($rw->_plcdata['emp_id']== AppEnv::$auth->userid) return 1;
  if($rw->_plcdata['comp_id']==AppEnv::$auth->deptid && ($myaclev >= AppEnv::ROLE_DEPTHEAD) ) return 1;
  return 0;
}
function ShowUrStatus($par) {
  return ($par=='F')? 'физ':'юр.';
}

# Returns all standard roles list
function GetUserRoles($userid=0) {
    global $auth;
    $ret = array();
    if(!$userid) $userid = isset($auth->userid) ? $auth->userid : 0;
    if(empty($userid)) return $ret;
    if($userid === SUPERNAME) {
        $ret[] = 'supervisor';
        $ret[] = 'admin';
    }
    return $ret;
}

# Возможные роли сотрудника
function GetRolesList($all=false) {

    $ret = array(array('0','нет'));
    $ret2 = WaRolemanager::getInstance()->getAllRolesList($all);
    if (is_array($ret2) && count($ret2)) $ret = array_merge($ret, $ret2);
    return $ret;
}

function getGrantableRoles($onlyid=false) {

    $roles = WaRolemanager::getInstance()->getGrantableRolesList(AppEnv::$auth->userid);
    if($onlyid) {
        return array_keys($roles);
    }
    return $roles;
}

function GetExportTypes() {
    return array(
        '0'  => 'нет'
       ,'10' => 'SOAP/instant'
       ,'20' => 'Bordero/night job'
    );
}

function intformat($num,$dec=2) {
    return number_format($num,$dec,'.',' ');
}

function fmtMoney($value) {
    return ($value==='-') ? $value : number_format(floatval($value),2,'.',' ');
}
function intMoney($value) {
    return ($value==='-') ? $value : number_format(floatval($value),0,'.',' ');
}
function strUpper($str) {
    return mb_strtoupper($str,'UTF-8');
}
function toAscii($utf) {
    return iconv('UTF-8',MAINCHARSET,$utf);
}
function toUTF($ascii) {
    return iconv(MAINCHARSET,'UTF-8',$ascii);
}

function decodeRole($id) {
    return AppEnv::decodeRole($id);
}
function AvailableDeptsList($action='inputdata') {
    global $auth;
    if($auth->getAccessLevel($action)<=AppEnv::ROLE_DEPTHEAD) $cond = 'dept_id='.$auth->deptid;
    else $cond = '';
    $ret = AppEnv::$db->GetQueryResult(AppEnv::TABLES_PREFIX.'depts','dept_id,dept_name',$cond,1,0,0,'dept_name');
#    WriteDebugInfo("AvailableDeptsList with access level ".$auth->a_rights['access'], 'sql:',AppEnv::$db->GetLastQuery());
    return $ret;
}
function getUserFullName($userid) {
    $ret = AppEnv::$db->GetQueryResult(AppEnv::TABLES_PREFIX.'users','fullname',"userid='$userid'");
    return $ret;
}
# вывод очищенного phpinfo()
function pinfo() {

    AppEnv::drawPageHeader('PHP info'); # AppEnv::drawPageBottom();

    ob_start();
    phpinfo();
    $info = ob_get_clean();
    $spos = mb_strpos($info, '<div ');
    $info = mb_substr($info, $spos); # отрезал стартовые HTML мета-теги
    $gitBranch = AppLists::gitDevBranch(2);
    $ALFO = "ALFO v." . APP_VERS . " (".APPDATE.")";
    if($gitBranch) $gitBranch = "<div class='bounded ct' style='font-size:1.3em;'>$ALFO, Ветка : <b>$gitBranch</b></div>";
    echo $gitBranch . "<style>td {border:1px solid #aaa}</style>".$info;
    AppEnv::finalize();
}


/**
* Вывод списка ролей и прав пользователя
*
*/
function myrights() {

    AppEnv::setPageTitle('mnu-view-my-rights');
    $wdth = (defined('IF_LIMIT_WIDTH') ? (IF_LIMIT_WIDTH-30) : '700');
    $ret = '<br><div class="div_outline ct">';
    $userid = AppEnv::$auth->userid;
    if(SuperAdminMode() && !empty($_GET['userid'])) {
        $userid = intval($_GET['userid']);
    }
    $ret .= WaRolemanager::getInstance()->showUserRightsVerbose($userid);

    $plgadd = AppEnv::runPlugins('showMyRights');
    if(count($plgadd)) {
        foreach($plgadd as $item) {
            $ret .= $item;
        }
    }
    if (AppEnv::isTestAccount()) $ret .= "<div class='attention'>ВНИМАНИЕ: это ТЕСТОВАЯ учетная запись!</div>";
    $invank = InvestAnketa::isInvestAnketaActive('');
    $ret .= "<br><div class='bounded'>Инвест-анкета: " .($invank ? 'Включена' : 'недоступна'). '</div>';
    $ret .= '</div>';
    AppEnv::appendHtml($ret);
    AppEnv::finalize();
}

function GetDeptList() {

    $ret = AppEnv::$db->GetQueryResult(AppEnv::TABLES_PREFIX.'depts','dept_id,dept_name','',1,0,0,'dept_name');
    if(!is_array($ret)) $ret = array();
    return $ret;
}
# получает список для "подразделения" в списке диапазонов (при активности плагина может быть свой список!)
function getDeptsForRange() {

    $plg = AppEnv::currentPlugin();
    if(!isset(AppEnv::$_cache['DeptsForRange-'.$plg])) {

        $ret = array(array('0','Все подразделения'));
        if($plg && isset(AppEnv::$_plugins[$plg]) && method_exists(AppEnv::$_plugins[$plg], 'getDeptsForRange'))
             $dpt = AppEnv::$_plugins[$plg]->getDeptsForRange();
        else $dpt = OrgUnits::getDeptsTree();
        if(is_array($dpt)) $ret = array_merge($ret, $dpt);

        AppEnv::$_cache['DeptsForRange-'.$plg] = $ret;
    }
    return AppEnv::$_cache['DeptsForRange-'.$plg];
}
/**
* Получение очередного номера полиса/заявления из пула диапазонов
* (новая, взамен AppEnv::getNextPolicyNo - после отладки и ввода диапазонов заменить везде !)
* @since 0.96
* @param mixed $code Кодировка продукта
* @param mixed $dept подразделение. Ищется сначала для данного подразд-я, потом - в "универсальных" диапазонах
* @param mixed $module ID плагина
* @param mixed $plen до какой длины добить нулями
*/
function getNextStatementNo($code, $dept=0, $module = '', $plen=0) {
    $retid = NumberProvide::getNext($code, $dept, $module, $plen);
    # WriteDebugInfo("returning: ", $retid);
    return $retid;
}
/**
* Формирует массив вложенных подразделений для формирования <option> списка
*
* @param mixed $startDept с какого подразделения начать
* @param mixed $action для какого действия
* @param mixed $onlyactive выводить только активных (не использ-ся)
*/
function getDeptsTree($startDept=0, $action='', $onlyactive=false) {
    $result = array();

    if (!empty(AppEnv::$primary_dept)) {
        $metaList = (is_array(AppEnv::$primary_dept) ? implode(',', AppEnv::$primary_dept) : AppEnv::$primary_dept);
        $result = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'depts',array(
          'fields'=>'dept_id,dept_name', 'associative'=>0,'where'=>"parentid IN($metaList)",'orderby'=>'dept_name'));
    }
    else {
        OrgUnits::__getOptionsDepts($result,$startDept,0, $action, $onlyactive);
    }
    return $result;
}

function __getOptionsDepts(&$result, $startDept=0, $level=0, $action='', $onlyactive=false) {
    if (empty($level) && isset(AppEnv::$_cache['deptTree_'.$startDept])) {
        # writeDebugInfo("return cached for $startDept");
        return AppEnv::$_cache['deptTree_'.$startDept];
    }

    if($level>60) return; # exit("getOptionsDepts($startDept): Endless recursion !");
    if(empty($level)) $result = [];
    $prefix = empty($level) ? '' : str_repeat(' &nbsp; &nbsp;',$level);
    $condpref = array();
    if ($onlyactive) $condpref[] = "b_active=1";

    if ($startDept==0) $startDept = AppEnv::$start_dept;
    if ($startDept==0) {
        if (AppEnv::$auth->isUserManager() || AppEnv::$auth->getAccessLevel([AppEnv::RIGHT_USERMANAGER,AppEnv::RIGHT_DEPTMANAGER])) {
            $startDept=0;
        }
        elseif(empty($action)) {
            $startDept = AppEnv::$auth->deptid;
            $result = array(array($startDept,
                AppEnv::$db->GetQueryResult(AppEnv::TABLES_PREFIX.'depts','dept_name',array('dept_id'=>$startDept))
            ));
        }
        elseif(AppEnv::$auth->getAccessLevel($action) >= 20 /*AppEnv::ROLE_DIRECTOR*/) {
            $startDept=0;
        }
        elseif(AppEnv::$auth->getAccessLevel($action) == 12 /*AppEnv::ROLE_DEPTHEAD*/) {
            __getOptionsDepts($result, AppEnv::$auth->deptid,0,$action,$onlyactive);
            $startDept = AppEnv::$auth->deptid;
        }
        else {
            return; # простой менеджер и агент никаких подразделений не видят!
        }
		$condpref[] = ($startDept==0) ? "parentid IN(0,dept_id)" : "parentid=$startDept";
    }
    else {

        $startdpt = AppEnv::$db->GetQueryResult(AppEnv::TABLES_PREFIX.'depts','dept_name',"dept_id='$startDept'");
        $found = false;
        if(is_array($result)) foreach($result as $item) {
            if($item[0] == $startDept) { $found = true; break; }
        }
        if(!$found) $result[] = AppEnv::$db->GetQueryResult(AppEnv::TABLES_PREFIX.'depts','dept_id,dept_name,b_active','dept_id='.$startDept,0);
        # if($level==0) $result[] = array($startDept, $prefix.$startdpt . ((AppEnv::$_debug || $auth->SuperVisorMode()) ? " ($startDept)" : ''));
		$condpref[] = "parentid=$startDept";
    }

    $dpts = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'depts', array(
      'fields'=>'dept_id,dept_name,b_active',
      'where' => $condpref, # .'parentid='.$startDept,1,0,0,'dept_name'
      'associative'=>0,
      'orderby' =>'dept_name'
    ));

    if(is_array($dpts)) {
        $level2 = $level+1;
        foreach($dpts as $dept) {
            if($dept[0] == $startDept) continue;
            $prefix = str_repeat(' &nbsp; &nbsp;',$level2);
            $strid = (AppEnv::$_debug || SuperAdminMode()) ? " ($dept[0])" : '';
            $ditem = array($dept[0], $prefix.$dept[1].$strid); # для debug вывожу еще ИД подразд.
            if($dept[2]=='0') $ditem['options'] = array('class'=>'inactive');
            $result[] = $ditem;
            __getOptionsDepts($result, $dept[0], $level2,$action, $onlyactive);
        }
    }
    if (empty($level)) {
        AppEnv::$_cache['deptTree_'.$startDept] = $result;
    }
}
# TODO: GetDeptName унес в orgunits.php, заменить все вызовы и убрать отсюда!
function GetDeptName($deptid, $official = false) {
    if (empty($deptid) || intval($deptid)<=0) return '-';
    if (!isset(AppEnv::$_cache['deptname-'.$deptid])) {
        $dta = AppEnv::$db->select(PM::T_DEPTS, array('where'=>"dept_id='$deptid'",'singlerow'=>1,'fields'=>'dept_name,official_name'));
        AppEnv::$_cache['deptname-'.$deptid] = $dta;
    }
    if (!$official) return (AppEnv::$_cache['deptname-'.$deptid]['dept_name']??'');
    return (empty(AppEnv::$_cache['deptname-'.$deptid]['official_name'])?
         AppEnv::$_cache['deptname-'.$deptid]['dept_name']
       : AppEnv::$_cache['deptname-'.$deptid]['official_name']
    );
}

function getDeptRawData($deptid) {
    $ret = AppEnv::$db->select(PM::T_DEPTS, array(
      'where' => array('dept_id'=>$deptid)
      ,'singlerow' => 1
      ,'associative'=>1
      )
    );
    return $ret;
}
function decodeDeptAccess($val) {
    if ($val == '') return 'Неогр';
    return 'Огранич.';
}
function ruTimeZones() {
    return array(
     'Etc/GMT'
    ,'Europe/London'
    ,'Europe/Moscow'
    ,'Europe/Kaliningrad'
    ,'Europe/Minsk'
    ,'Europe/Uzhgorod'
    ,'Europe/Volgograd'
    ,'Europe/Samara'
    ,'Asia/Krasnoyarsk'
    ,'Asia/Magadan'
    ,'Asia/Novokuznetsk'
    ,'Asia/Novosibirsk'
    ,'Asia/Omsk'
    ,'Asia/Sakhalin'
    ,'Asia/Vladivostok'
    ,'Asia/Yakutsk'
    ,'Asia/Yekaterinburg'
    );
}

function getUserFio($userid) {

    $ret = AppEnv::getCached('user_fio',$userid);
    if($ret === null) {
        $ret = AppEnv::$auth->GetUserNameForId($userid);
        AppEnv::setCached('user_fio',$userid, $ret);
    }
    return $ret;
}
# любую кривую дату (или дату из XLE-ячейки) - в формат "YYYY-MM-DD" или "DD.MM.YYYY"
function ParseAnyDate($par, $fmt='') {
    if(is_numeric($par) && class_exists('PHPExcel_Style_NumberFormat')) {
        $tofmt = empty($fmt)? 'YYYY-MM-DD' : ($fmt=='d.m.Y' ? 'DD.MM.YYYY' : 'YYYY-MM-DD');
        $ret = PHPExcel_Style_NumberFormat::toFormattedString($par, $tofmt);
#        WriteDebugInfo('ParseAnyDate/Xls parse/',$par, $ret);
        return $ret;
    }
    $sp = preg_split("/[-.\/]+/",$par); # 12.05.10
    if(!isset($sp[2])) return '';
    if($sp[2] <=30) $sp[2] += 2000;
    elseif($sp[2] <=99) $sp[2] += 1900;
    if($fmt=='d.m.Y') $ret = implode('.',$sp);
    else $ret = $sp[2] . '-' . $sp[1] . '-' . $sp[0];
#    WriteDebugInfo('ParseAnyDate/',$par, $ret);
    return $ret;
}

# Получить из базы курсы валют на дату
function GetRates($fordate='') {
    require_once('class.currencyrates.php');
    $ret = CurrencyRates::GetRates($fordate);
    $ret['RUR'] = 1.0;
    return $ret;
}

function packValues($idlist,$data,$brackets=0) {
    if(!is_array($brackets)) $brackets = array('<','>');
    $ret = '';
    foreach($idlist as $fid) {
        if(isset($data[$fid]) && $data[$fid]!=='') $ret .= "<$fid>" . htmlspecialchars($data[$fid],ENT_COMPAT, MAINCHARSET) . "</$fid>";
    }
    return $ret;
}
function unpackValues($idlist, $stringdata, $brackets=0) {
    $ret = array();
    foreach($idlist as $id) {
        $ret[$id] = htmlspecialchars_decode( GetXmlTagValue($stringdata, $id), ENT_COMPAT);
    }
    return $ret;
}
# "Иванов Сергей Петрович" --> "Иванов С.П." - на удаление, перенесена в app/rusutils.php!
function MakeFIO($fullname, $imia='', $otch='') {
    if (empty($fullname)) return '';
    $st = preg_split('/[ .,]+/',$fullname, -1, PREG_SPLIT_NO_EMPTY);
    if ( $imia ) $st[1] = $imia;
    if ( $otch ) $st[2] = $otch;
    $ret = $st[0] . (isset($st[1]) ? ' '.mb_substr($st[1],0,1,MAINCHARSET).'.':'') . (isset($st[2]) ? (mb_substr($st[2],0,1,MAINCHARSET).'.') :'');
    return $ret;
}
# типы файлов "правил, шаблонов" и проч, доступных для загрузки
function getMyDeptId() {
    return AppEnv::$auth->deptid;
}
function getMyUserId() { return AppEnv::$auth->userid; }

# Ф-ция проверки - Разрешать ли поле редактирования осн.роли в справочнике сотрудников
function isPrimaryRoleEditable() {
    global $ast_datarow;
    if (SuperAdminMode()) return true;
    if (isset($ast_datarow['usr_role'])) {
        $myroles = getGrantableRoles(true);
        return (is_array($myroles) && in_array($ast_datarow['usr_role'], $myroles));
    }
    else return true;
}

# Ф-ции для грида редактирования учеток пользователей (app/users.php)
function isUserEditable($row=null) {
    if (AppEnv::$auth->getAccessLevel(AppEnv::RIGHT_USERMANAGER)) return TRUE;
    return isPrimaryRoleEditable($row);
}
function isUserDeletable($row=null) {
    if (AppEnv::$auth->getAccessLevel(AppEnv::RIGHT_USERMANAGER)) return TRUE;
    return isPrimaryRoleEditable($row);
}
# Вывожу разрешение на сброс пароля только если учетка доступна мне для редактирования
function getResetPswLink($row = null) {
    $lnk = '';
    if ( AppEnv::$auth->getAccessLevel(AppEnv::RIGHT_USERMANAGER) || isPrimaryRoleEditable($row))
        $lnk = '<a href="javascript:void(0)" onclick="waAuth.dlgResetPassword({ID})">сброс пароля</a>';
    return str_replace('{ID}', $row['userid'], $lnk);
}

/**
* Получаю список пользователей, у которых есть указанная роль
*
* @param mixed $rolename
* @returns array (row = [user ID, full_name])
*/
function getUsersForRole($rolename) {
    if(is_numeric($rolename)) $roleid = intval($rolename);
    else {
        $lst = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'acl_roles', array(
            'fields'=>'roleid'
           ,'where' =>array('rolename'=>$rolename)
           ,'associative'=>0
        ));
        $roleid = isset($lst[0]) ? $lst[0] : 0;
    }
    if(!$roleid) return array();

    $add = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'acl_userroles', array(
        'fields'=>'userid'
       ,'where' =>array('roleid'=>$roleid)
       ,'associative'=>0
    ));

    $where = "(usr_role='$roleid')";
    if(is_array($add) && count($add)>0) $where .= " OR (userid IN(" . implode(',',$add).'))';
    $agts = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'users', array(
        'fields'=>'userid,usrlogin,deptid,fullname'
       ,'where' => $where
       ,'orderby' => 'fullname'
       ,'associative'=>1
    ));
    return $agts;
}

/**
* Возвращает список для построения select-опций "родительских" рисков для данного риска
* (для astedit)
*/
function getParentRiskList() {
    global $ast_datarow;
    $ret = array(array(0,'нет'));
    $where = (isset($ast_datarow['id']) ? " AND (id<>$ast_datarow[id])" : '');
    $lst = AppEnv::$db->select('alf_agmt_risks', array('fields'=>"id,riskename",'where'=>"parentrisk=0 $where",'orderby'=>'id','associative'=>0));
#    WriteDebugInfo('ast_datarow:', $ast_datarow);  WriteDebugInfo('lst:', $lst);
    return array_merge($ret, $lst);
}
/**
* Выполняется перед занесением изменений в users
* - заношу ИД сотрудника создавшего запись, если запись новая
* @param mixed $data
* @param mixed $act
*/
function setCreatedBy($data, $act) {
    if ($act === 'doadd') {
        if(empty($data['created'])) $data['created'] = date('Y-m-d H:i:s');
        $data['createdby'] = $data['updatedby'] = (empty(AppEnv::$auth->userid) ? 'system' : AppEnv::$auth->userid);
    }
    elseif ($act === 'doedit') $data['updatedby'] = (empty(AppEnv::$auth->userid) ? 'system' : AppEnv::$auth->userid);
    return $data;
}

/**
* список опций выбора парольной политики для учетки
*
*/
function pwdTypeListOptions($initval=0) {
    $data = array(
      array('0','Станд.(гр.политика)')
     ,array('-1','бессрочный')
     ,array('-2', 'несменяемый')
    );
    $pplist = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'pswpolicies'
      , array('fields'=>'recid,plname','orderby'=>'plname','associative'=>0)
    ); # table name!!!
    # [prefix]pswpolicies.recid
    if (is_array($pplist)) $data = array_merge($data, $pplist);
    return $data;
}
function showUserRole($roleid) {
    $dta = AppEnv::$db->select(AppEnv::TABLES_PREFIX.'acl_roles'
       ,array('where' => array('roleid'=>$roleid)
            ,'fields'=>'roledesc'
            ,'singlerow' => 1
       )
    );
    return (isset($dta['roledesc'])? $dta['roledesc'] : $roleid);
}
function getPluginTitle($plgid) {
    $ret = AppEnv::getLocalized("$plgid:title");
    if (!$ret) $ret = AppEnv::getLocalized("$plgid:main_title");
    if (!$ret) $ret = "[$plgid]";
    return $ret;
}
/**
* получает ИД головного агента [по заданному "мета-подразделению" либо от зарегистр.списка мета-подр.]
* {upd/16.09.2014} - теперь "головной банк" может быть списком (несколько "деревьев" головной-дерево дочерних подр)
* @param $metadept - код "мета-подразделения" ЛИБО ИД настроечной config-переменной, хранящей его
* TODO: удалить после рефакторинга!
*/
function getPrimaryDept( $deptid=0) {
    writeDebugInfo("obsolete call getPrimaryDept");
    return OrgUnits::getPrimaryDept($deptid);
}
/**
* Выдает список для формирования <option>-list со всеми "головными" подразделениями
* (мета-подразд-я будут в роли <optgroup>)
* Перенес в OrgUnits, удалить после тестирования
*/
function getAllPrimaryDepts() {

    $ret = AppEnv::getCached('_getprimarydepts',0);
    if (!$ret) {
        # $baseList = OrgUnits::getMetaDepts2();
        # Новый  подход - список мета-подразд получаю из прямо depts (у них поле b_metadept = 1)
        $ret = array();
        $baseList = AppEnv::$db->select(PM::T_DEPTS,array(
            'fields'=>'dept_id,dept_name'
            ,'where'=>'b_metadept>=1' # 1 - мета-подр для банков, 100 - "Все агенты"
            ,'associative'=>1
            ,'orderby'=>'dept_name'
        ));

        # TODO: сделать выбор только доступных в рамках текущих прав супер-операциониста
        foreach($baseList as $bdept) {
            $metaid = $bdept['dept_id'];
            $headname = $bdept['dept_name'];
            $dta = AppEnv::$db->select(PM::T_DEPTS,array(
                'fields'=>'dept_id,dept_name'
                ,'where'=>array('parentid'=>$metaid)
                ,'associative'=>0
                ,'orderby'=>'dept_name'
            ));
            $ret[] = array('<', $headname); # Стартует в селекте <optgroup>
            if (is_array($dta) && count($dta)) {
                $ret = array_merge($ret, $dta);
            }
        }

        AppEnv::setCached('_getprimarydepts',0, $ret);
    }

    return $ret;
}

# Весь список "головных" подразд. плюс нулевая опция "без привязки"
function getAllPrimaryDeptsNone($moduleid = null) {
	$ret = array(array('0','Без привязки'));
	$depts = getAllPrimaryDepts($moduleid);
	if (is_array($depts) && count($depts)>0)
		$ret = array_merge($ret, $depts);
	return $ret;
}

function YearMon3($yyyymm) {
    $mons = array('','Янв','Фев','Март','Апр','Май','Июнь','Июль','Авг','Сент','Окт','Ноя','Дек');
    $ym = explode('.',$yyyymm);
    $ret = $mons[intval($ym[1])] . ' '.$ym[0];
    return $ret;
}

# functions for enabling/disabling editing fields in "Users"
function userDetailsEditable() {
	if(!SuperAdminMode()) return false;
	return (AppEnv::isStandalone());
}
function userPwdEditable($p1=0,$p2=0) {

	if(!SuperAdminMode() || !AppEnv::isStandalone()) return false;
	return (AppEnv::$auth->isPasswordEncrypting() ? 'A' : true);
}

/**
* Basic functionality for pluginBackend Modules
*/
class stdBackend {
    # Creating roles & rights for the module (method backend::listRolesRights should exist
    # call: ./?plg={plugin}&action=makeroles
    public function makeRoles() {

        $mid = get_class($this);
        include_once(AppEnv::$FOLDER_APP . 'acl.tools.php');
        aclTools::setPrefix(AppEnv::TABLES_PREFIX);
        if (!method_exists($this,'listRolesRights')) {
            AppEnv::appendHtml($mid . ' - plugin has no listRolesRights function !<br>');
            return;
        }
        $arr = $this->listRolesRights();
        # $mid = AppEnv::currentPlugin();
        aclTools::upgradeRoles($mid, $arr);
        # if(AppEnv::isStandalone()) exit;
    }
}

function getEditPrivileges($tableid) {
    $admlev = false;
    $super = SuperAdminMode();
    $ret = [0,0,0];
    if(substr($tableid, 0,2) === 'v_') return $ret; # view
    switch($tableid) {
        case PM::TABLE_TRANCHES: # 'alf_tranches'
            $admlev = AppEnv::$auth->getAccessLevel('bank:superoper');
            $ret = ($admlev || $siuper) ? [1,1,1] : [0,0,0];
            break;
        case PM::T_SENTEMAIL: # alf_sentemail, : # никаких редактирований, только просмотр!
        case PM::T_SMS_CHECKLOG:
            return [0,0,0];
            break;
        default:
            if ($admlev || $super) $ret = [1,1,1];
            else $ret = [0,0,0];
            break;
    }
    return $ret;
    # return ($admlev ? array(1,1,1) : false);
}
/**
* Список ISO кодов валют, в которых оформляются договора
*
*/
function getActiveCurrencies() {
	return array('RUR','USD');
}
function invertLogical($par) {
    return (!$par);
}
# вывести стек вызовов:
function traceCalls($maxitem = 6) {
    $trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, $maxitem);
    foreach($trace as $no=> $item) {
        WriteDebugInfo("trace[$no]: "," $item[file] / line: $item[line], func: $item[function]");
    }
}

function unescapeDbData(&$params) {
    foreach($params as $id => $val) {
        if (is_array($val)) {
            unescapeDbData($val);
            continue;
        }
        if(is_string($val)) $val = html_entity_decode($val, ENT_QUOTES );
    }
}
function evShowUser($usrid) {
    if ( !is_numeric($usrid) ) return $usrid;
    if ( empty($usrid) ) return '--';
    return getUserFio($usrid);
}
# array_column() есть в PHP 5.5+, неполный polyfill для более ранних версий PHP
if (!function_exists('array_column')) {
    function array_column($arr, $colname, $indexes=NULL) {
        $ret = [];
        foreach($arr as $item) {
            if (isset($item[$colname])) $ret[] = $item[$colname];
        }
        return $ret;
    }
}
# callaback для astedit - вывод содержимого записи
function AstGenerateView() {
    $pars = AppEnv::$_p;
    $tbname = isset(AppEnv::$_p['t']) ? AppEnv::$_p['t'] : '';
    $id = isset(AppEnv::$_p['_astkeyvalue_']) ? AppEnv::$_p['_astkeyvalue_'] : '';
    $ret = "AstGenerateView: <pre>Это данные для показа записи<br>" . print_r($pars,1). '</pre>';
    switch ($tbname) {
        case 'alf_sentemail':
            $rec = AppEnv::$db->select($tbname, ['where'=>['id'=>$id], 'singlerow'=>1]);
            $ret = "<table class='table table-striped table-hover table-bordered'>"
              . '<tr><td>Дата</td><td>' . to_char($rec['send_date'],1). '</td></tr>'
              . '<tr><td>Адрес получателя</td><td>' . $rec['to_address']. '</td></tr>'
              . '<tr><td>Тема</td><td>' . $rec['subj']. '</td></tr>'
              . '<tr><td colspan="2">Текст сообщения<div class="bordered" style="padding:4px">' . nl2br($rec['msgbody']). '</div></td></tr>'
              . '</table>'
              ;
            break;
        case 'alf_sms_check_log':
            $rec = AppEnv::$db->select($tbname, ['where'=>['id'=>$id], 'singlerow'=>1]);
            $docid = $rec['docid'] . ( $rec['docid'] === 'HACKER' ? ' / <span class="alarm">Попытка подбора хэш-кода!</span>':'' );
            $result = $rec['result'];
            if($result != 'SUCCESS') $result = "<div class='alarm'>$result</div>";
            $ret = "<table class='table table-striped table-hover table-bordered'>"
              . '<tr><td>Дата события</td><td>' . to_char($rec['evt_date'],1). '</td></tr>'
              . '<tr><td>Модуль</td><td>' . $rec['module']. '</td></tr>'
              . '<tr><td>ИД документа</td><td>' . $docid. '</td></tr>'
              . '<tr><td>IP Адрес запроса</td><td>' . $rec['ipaddr']. '</td></tr>'
              . '<tr><td>Строка USER AGENT браузера</td><td>' . $rec['useragent']. '</td></tr>'
              . '<tr><td>Результат</td><td>' . $result. '</td></tr>'
              . '</table>'
              ;
            break;
    }
    exit($ret);
}