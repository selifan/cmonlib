<?PHP
/**
* @package ALFO( ex.arjagent) - Фронт-Офис Allianz Life
* @author Alexander Selifonov, <Aleksandr.Selifonov@allianz.ru>
* @link: http://www.allianzlife.ru,
* @name alfo_core.php
* Base WebApp extending module
* @version 2.58.01
* Bitrix integration aware
* modified : 2021-12-20
**/
#ini_set('display_errors',1); ini_set('error_reporting',E_ALL);
# if(!defined('JQUERY_VERSION')) define('JQUERY_VERSION', '1.11.3.min.js');//    1.11.3.min | 2.2.2.min.js
include_once(__DIR__ . '/../cfg/_appcfg.php');
include_once(__DIR__ . '/../cfg/folders.inc');

define('APPDATE', '20.12.2021');
define('APP_VERS', '2.58');

if (!constant('IN_BITRIX') || !empty($_SESSION['log_errors'])) {
    # error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
    error_reporting(E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}
define('ENABLE_PACK_EXPORT', false); // выгрузка в пакеты : false = выключена

define('POOL_FILE_FLAG', 1); // Генерация номеров полисов с использ. единого файла-семафора (блокировка конкурентных запросов)

# define('UNI_NUMBER_POOL', true); # TRUE - один общий диапазон на все продукты
define('UNI_NUMBER_POOL', false);

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

define('USE_JQUERY_UI',1); # astedit.php will use jquery.ui css

define('APPVERSION','Allianz Life Front-Office v.'.APP_VERS. ' ('.APPDATE.')');
define('APPCOPYRIGHT','ООО СК "Альянс Жизнь"');

define('USE_PSW_POLICIES',1); # использование раздельных парольных политик

# setlocale(LC_ALL, 'ru_RU.CP1251');
if (MAINCHARSET === 'WINDOWS-1251')
	setlocale (LC_CTYPE, ['ru_RU.CP1251', 'rus_RUS.1251']);
else
	setlocale (LC_CTYPE, 'ru_RU.utf8');

# Try using latest jQuery, jQuery.ui
define('USE_LAST_JQUERY',1);
#define('JQUERY_VERSION', '1.11.1.min'); define('UI_VERSION', '1.10.4.custom');
# define('JQUERY_VERSION', '3.6.0.min');
# Latest - jquery-3.6.0.min.js
# define('UI_VERSION', '1.10.4.custom');
# define('UI_VERSION', ''); # без версии - последняя актуальная!
#define('JQUERY_VERSION', '1.11.1.min'); define('UI_VERSION', '1.10.4.custom');

global $auth;
$auth = '';
$jsdebug = false;

# require_once( ALFO_ROOT . 'cfg/folders.inc');

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
    HeaderHelper::$maincss = 'styles_bitrix.css';
}

include_once('webapp.core.php');

class appEnv extends WebApp {

    const DEFAULT_LANG = 'ru';
    const FOLDER_FILES  = './files/';
    static $FOLDER_APP = '';
    static $charset = 'UTF-8';
    const TABLES_PREFIX     = 'arjagent_';
    const TABLES_NEWPREFIX  = 'alf_'; # новый префикс, постепенно уходим от arjagent_
    static $UNI_NUMBER_POOL = false; # Использовать общий диапазон номеров "UNIVERSAL" на ВСЕ продукты

    static $POOL_WARNING_THRESHOLD1 = 100; # за сколько номеров предупреждать об истечении в пуле номероа полисов
    static $POOL_WARNING_THRESHOLD2 = 20; # второй порог пердупреждения!

    const TABLE_DEPTS ='arjagent_depts'; # подразделения, структура компании
    const TABLE_USERS ='arjagent_users'; # учетки пользователей
    const TABLE_STMTNUMBERS ='alf_stmt_numbers'; # таблица с выданными номерами заявлений по кодировкам (продуктам) - не используется!
    const TABLE_TRANCHES ='alf_tranches'; # таблица дат окон продаж и траншей
    const TABLE_STMTRANGES ='alf_stmt_ranges'; # таблица с диапазонами номеров полисов/заявл.
    const TABLE_UPLOADEDFILES ='alf_uploadedfiles'; # таблица список загруженных файлов документов
    const TABLE_DEPT_PRODUCTS ='alf_dept_product';  # список прав и комиссий подразделений в продуктах
    const TABLE_EVENTLOG ='arjagent_eventlog';  # журнал
    const TABLE_PROFESSIONS ='alf_professions';  # 24.04.2018 - список профессий
    static $FOLDER_FILES = 'files/';
    static $table_uploadedfiles ='alf_uploadedfiles';
    private static $userMetaType = FALSE;

    const RIGHT_DOCFLOW = 'docflow_oper'; # видны все полисы, есть право выгрузки в СЭД
    const RIGHT_DOCFILES = 'docfiles_edit'; # может загружать файлы шаблонов, документов
    const RIGHT_USERMANAGER = 'account_admin'; # ID права администратора ВСЕХ учеток (право сброса пароля и т.д.)
    const RIGHT_DEPTMANAGER = 'dept_editor'; # администратора списка подразд.
    # const RIGHT_ALLREPORTS = 'all_reports'; # Право доступа к любым общим отчетам
    static $ENABLE_SERVERTIME = 0; # Show dynamic server time block
    private static $docflow_access = null; // могу выгружать в СЭД - true
    private static $_filecategs = array();
    # static $filesAllowedModules = array();
    private static $_appStates = []; # текущие "состояния" для разных параметров/модулей setUsrAppState($key, $val), getUsrAppState($key)
    private static $tableDeps = []; # зарегистрированные зависимости таблиц (PK-> FK)
    public static $nonLifeModules = []; # зарегистрированные модули НЕ-Жизни

    protected static $cur_mb_state = '';
#    const ACTION_HELPEDIT  = 'helpwriter';
    const DEFAULT_PASSWORD = '1qaz!QAZ';
#    const INDEX_COLUMNS = 2; # в сколько колонок выводить блоки ссылок (от плагинов) на стартовой странице
    static $primary_dept = '';

    static $meta_depts = array(); // Общий список "мета-подразд.", под которыми регистрируются "головные" подразд-я ("банки",...)
    static $superadmin = NULL;

    # массив модулей отвечающих за ведение страховых полисов. Добавлять в список из соотв.плагинов, вызовом appEnv::registerAgmtModule
    # формат строки: ['pluginid'] = array('title', {codirovki});
    # {codirovki} - либо массив array() кодировок, либо имя функции, которая вернет актуальный массив кодировок для данного модуля

    static $prod_categories = array('INDEXX'=>'INDEXX', 'TREND'=>'TREND') ;

    static $app_newfunc = 1; # Задает как выполнять обновленные функции - 0 - по-старому, 1 - по-новому (для тестирования нового ф-ционала

    # Добавляю описатели для вывода инфографики
    static private $_infographics = array();

    # список ИД плагинов, для которых работает экспорт в Лизу
    static $exportable_modules = array();
    static $_init_done = false;

    # объекты для вызовов ALFO-сервисов (API)
    static $svc_lastError = '';

    # для работы из задания - занести ИД полиса и модуля - setPolicyId():
    static $currentPolicy = '';
    static $currentModule = '';

    # установить в TRUE если вызов по API с сайта, где клиент оформляет себе полис - appEnv::setClientCall()
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
    # вернет TRUE ессли работает внутри CMS 1c Bitrix
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
                $apiUserid = appEnv::getConfigValue('account_clientplc');
                # $apiUserid = $cliList[0];
            }
            if ($apiUserid > 0) {
                $deptDta = appEnv::$db->select(appEnv::TABLE_USERS, [
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
        $baseList = appEnv::$db->select(appEnv::TABLE_DEPTS,array(
            'fields'=>'dept_id'
            ,'where'=> $where
            ,'associative'=>1
            ,'orderby'=>'dept_name'
        ));
        # echo "sql: ".appEnv::$db->getLastQuery() .'<br>err: '. appEnv::$db->sql_error();
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

    static public function getProductRange($codirovka) {
        foreach(self::$_productRanged as $id=>$item) {
           if(in_array($codirovka, $item)) return $id;
        }
        return $codirovka;
    }

    public static function wapluginator() {

        include_once('waPluginator/waPluginator.php');
        waPluginator::setBaseUri('./?p=wapluginator');
        waPluginator::autoLocalize();
        waPluginator::addLanguage('ru' , 'Русский');
        waPluginator::addStdCompilers();
        waPluginator::setOptions(array(
                'appname' =>'AlFO: Allianz Front Office'
               ,'author' =>'Alexander Selifonov'
               ,'email' =>'aleksandr.selifonov@allianz.ru'
               ,'link' =>''
            )
        );
        if(!empty(self::$_p['action'])) {
            waPluginator::performAction(self::$_p);
            exit;
        }
        else {
            self::setPageTitle('Plugin generator');
            self::appendHtml('<br>');
            self::appendHtml(waPluginator::designerForm(true));
            self::finalize();
        }
    }

    static public function getProductCategories() { return self::$prod_categories; }

    public static function authHookLogin() {
    	WriteDebugInfo("authHookLogin called");
    	return false;
	}

    # инициализация рабочих переменных
    static public function init($handleAuthRequests=TRUE) {
        global $pwdmgr, $as_dbengine, $authdta;

        self::initFolders(ALFO_ROOT);
        self::$warnings = [];
        if (defined('DEBUG_FOR_IP') && !empty($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']===constant('DEBUG_FOR_IP')) {
            self::$_debug = 10;
        }

        if (self::$_init_done) return;
        self::$_init_done = true;
        if (defined('IF_LIMIT_WIDTH'))  self::$IFACE_WIDTH = constant('IF_LIMIT_WIDTH');
        # self::$_debug = TRUE; // Turn ON for debug logging
        parent::init(FALSE);
        if (is_object(self::$auth)) {
        	self::$auth->addUserManager(self::RIGHT_USERMANAGER); // global user manager right
            if (self::$auth->userid > 0 && self::$auth->deptid > 0) {
                self::$userMetaType = OrgUnits::getMetaType(self::$auth->deptid);
            }
            # writeDebugInfo("auth TODO my id:", self::$auth->userid);
        }
        self::addTplFolder(ALFO_ROOT . 'app/tpl/');
        if (class_exists('PolicyModel')) PolicyModel::init();
        parent::setTablesPrefix(self::TABLES_PREFIX);
        if(($dmob = self::getConfigValue('sys_detectmobile'))) {
            if(intval($dmob)===1) {
                require_once('Mobile_Detect.php');
                $detect = new Mobile_Detect;
                self::$isMobile   = $detect->isMobile();
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
	               ,'dbobject'   => $as_dbengine
	               ,'actions'    => array('auth_action','plg_name','plg','action','plg_action','adm_action_type','p')
	            ));
	#           $actions = array('auth_action','plg_name','action','plg_action','adm_action_type','p');
	#           self::$_tracer->setActions($actions);

	        }
		}

        $authopt = array(
           'use_psw_policies' => USE_PSW_POLICIES
        );
        if(class_exists('PasswordManager')) {
            self::$_pwdmgr = new PasswordManager(array(
                'table_users' => $authdta['table_users']
               ,'field_userid' => $authdta['usrid']
               ,'field_userlogin' => $authdta['usrlogin']
               ,'field_userpwd' => $authdta['usrpwd']
               ,'field_userstatus' => $authdta['usrblocked'] # при авто-блокировке PasswordManager занесет сюда значение 1
               ,'status_disabled' => '1'
               ,'char_repeat' => 1
               ,'char_seq' => 2
               ,'tables_prefix' => (self::TABLES_PREFIX . 'pwdmgr_')
               ,'unicode' => false
            ));
            self::$_pwdmgr->SetLocalization(self::$i18n); # all localized strings will be available to PasswordManager :
            $ban_sequences = array(
               '1234567890-='
              ,'йцукенгшщзхъфывапролджэячсмитьбю'
              ,'qazwsxedcrfvtgbyhnujmik,ol.p;'
              ,'йфяцычувскамепинртгоьшлбщдюзж.хэъ'
              ,'ЙЦУКЕНГШЩЗХЪФЬШАПРОЛДЖЭ5ИСМИТЬБЮ.'
              ,'ЙФЯЦЫЧУВСКАМЕПИНРТГОЬШЛБЩДЮЗЖ.ХЭЪ.'
              ,'1йфя2цьгчЗувс4кам5епи6нрт7гоь8шлб9шдю0зж.-хэ^ь'
              ,'1ЙФЯ2ЦЫЧЗУВС4КАМ5ЕПИ6НРТ7ГОЬ8ШЛБ9ЩДЮ03Ж.-ХЭ'
              ,'1QAZ2WSX3EDC4RFV5TGB6YHN7UJM8QC,9OL.0Py-[\'=]'
              ,'1qaz2wsx3edc4rfv5tgb6yhn7ujm8ik,9ol.0p;/-[\'=]'
              ,"1q2w3e4r5t6y7u8i9o0p-[=]azsxdcfvgbhnjmk,l.y'qawsedrftgyhujikolp;[']"
              ,"1Q2W3E4R5T6Y7U8I9O0P-[=]A2SXDCЈ7VGBHNJMK,L.y'QAWSBDRFTGYHUJIKOLP;[']"
              ,"1Й2ЦЗУ4К5Е6Н7Г8Ш9Щ03-Х=ЪФЯЬ1ЧВСАМПИРТОЬЛБДЮЖ.ЭЙФЦЬ1УВ1САЕПЬП>ГОШЛЩДЗЖХЭЪ"
              ,"1й2цЗу4к5е6н7г8ш9щ0з-х=ьфяычвсампиртоьлбдюж.эйфцыувкаепнргошлщдзжхэъ"
            );

            self::$_pwdmgr->AddNationalSequence($ban_sequences);
            self::$_pwdmgr->AddNationalChars('йцукеёнгшщзхъфывапролджэячсмитьбю');

            self::$_pwdmgr->SetStrengthReqs(array(
                'minlength' => self::getConfigValue('sys_psw_minlen')
               ,'maxlength' => self::getConfigValue('sys_psw_maxlen') // 16
               ,'mingroups' => self::getConfigValue('sys_psw_mingroups') // 1
               ,'histlength' => self::getConfigValue('sys_psw_histlength') // 0
               ,'days_remind' => self::getConfigValue('sys_psw_expire_remind') // 0
               ,'char_repeat' => true
               ,'char_seq' => 1 // 1/true, 0/false
               ,'mindays' => self::getConfigValue('sys_psw_mindays')
               ,'maxdays' => self::getConfigValue('sys_psw_maxdays')
            ));
			if (is_object(self::$auth))
            	self::$auth->SetPasswordManager(self::$_pwdmgr);

            $authopt['password_max_tries'] = self::getConfigValue('sys_psw_maxtries',0);
            $authopt['password_blocktime'] = self::getConfigValue('sys_psw_blocktime',0);
        }

		if (is_object(self::$auth)) {
	        self::$auth->setOptions($authopt);
	        self::$auth->handleRequests(); # handle all 'auth_action' requests first!

	        if (SuperAdminMode()) self::enableConfigPage();

			# таблицы, которые может править супер-операционист
	        AppEnv::addRefPrivileges(PM::RIGHT_SUPEROPER, # was 'bank:superoper'
	          [ 'alf_countries','alf_product_config','alf_dept_product','stmt_ranges', 'alf_agmt_risks',
                PM::T_PROMOACTIONS, PM::TABLE_TRANCHES, PM::T_PROFESSIONS
              ]
	        );

	        # добавляем в админ-панель списки системных таблиц
	        self::addTableSet('acl', array(self::TABLES_PREFIX.'acl_roles',
	            self::TABLES_PREFIX.'acl_rolerights',self::TABLES_PREFIX.'acl_rightdef',
	            self::TABLES_PREFIX.'pswpolicies'
	        ));
	        self::addTableSet('help', array(self::TABLES_NEWPREFIX.'wikiarticles',self::TABLES_NEWPREFIX.'wikifiles'));

	        if (!isAjaxCall() && !empty($_SERVER['REMOTE_ADDR'])) {
	            self::BuildMainMenu();
	        }
		}

    }

    public static function setPrimaryDept($id) {
        self::$primary_dept = $id;
    }

    public static function BuildMainMenu() {

      $admlev = self::$auth->getAccessLevel(appEnv::ACTION_ADMIN);
      $suadmin = SuperAdminMode();
      $usradmin = ($admlev || $suadmin || self::$auth->getAccessLevel(self::RIGHT_USERMANAGER));
      $deptadmin = ($admlev || appEnv::$auth->getAccessLevel(self::RIGHT_DEPTMANAGER));

      $suoper = (self::$auth->getAccessLevel(PM::RIGHT_SUPEROPER) || (self::$auth->getAccessLevel('nsj_oper')>=5));
      $mgrfiles = self::$auth->getAccessLevel(self::RIGHT_DOCFILES);
      $saleSupport = (self::$auth->getAccessLevel('nsj_oper')>=4) ||(self::$auth->getAccessLevel('bank:superoper'));

      self::$_mainmenu = array();
      self::$_mainmenu['start'] = array('href'=>'./', 'title' => appEnv::getLocalized('page.home'));

      # Заранее готовлю заглушки под "базовые" подменю:
      self::$_mainmenu['mnu_browse'] = array('title'=> appEnv::getLocalized('mnu-browse'));
      self::$_mainmenu['inputdata']   = array('title' => appEnv::getLocalized('mnu-input'));
      self::$_mainmenu['mnu_reports'] = array('title'=> appEnv::getLocalized('mnu-reports'));
      self::$_mainmenu['mnu_infographics']   = array('title'=>appEnv::getLocalized('mnu-infographics'));
      self::$_mainmenu['mnu_export']  = array('title'=> appEnv::getLocalized('mnu-export'));
      self::$_mainmenu['mnu_admin']   = array('title'=>appEnv::getLocalized('mnu-admin'));
      self::$_mainmenu['mnu_utils']   = array('title'=>appEnv::getLocalized('mnu-utils'));
      self::$_mainmenu['mnu_docs']    = array('title'=>appEnv::getLocalized('mnu-help'));

      if(self::$auth->IsUserLogged()) {
        $invanketa = PlcUtils::isInvestAnketaActive();
        if ($invanketa && self::$workmode < 100) { # ссылка для просмотра инвест-анкет
            appEnv::addSubmenuItem('mnu_browse', 'mnu_invanketas', appEnv::getLocalized('mnu_invanketas'),'./?p=invanketas');
        }

        $subm_in = array();
        if ($invanketa && self::$workmode==0) { #(==2)  ссылка для ввода инвест-анкеты БЕЗ ввода договора
            appEnv::addSubmenuItem('inputdata', 'mnu_newinvanketa', appEnv::getLocalized('mnu_newinvanketa'),'./?p=addinvanketa');
        }

        if(count($subm_in)>0) self::$_mainmenu['inputdata']['submenu'] = $subm_in;

        $submenu = array();
        if(appEnv::$config_enabled) $submenu['config'] = array('href'=>'./?p=config', 'title'=>appEnv::getLocalized('title-appconfig'));
        if($admlev || $suadmin)
        {
            $submenu['log'] =  array('href'=>'./?p=eventlog', 'title'=>appEnv::getLocalized('page.browse.eventlog'));
        }
        if ($deptadmin)
            $submenu['depts'] = array('href'=>'./?p=depts', 'title'=>appEnv::getLocalized('mnu-deptlist'));

		if ($usradmin) {
            $submenu['users'] = array('href'=>'./?p=users', 'title'=>appEnv::getLocalized('mnu-userlist'));
		}
        if ($suadmin || $mgrfiles || $suoper)
            $submenu['manage_files'] = array('title'=>appEnv::getLocalized('title-managefiles'), 'href'=>'./?p=managefiles');

        $submnu_sysrep = [];

        if ($admlev || $suadmin || $suoper) { # служебные отчеты видны супер-оперу (НСЖ)
            $submnu_sysrep['smslog'] = ['href'=>'./?p=flexreps&name=smslog', 'title'=>appEnv::getLocalized('mnu_smslog') ];
            $submnu_sysrep['report_finmonitor'] = ['href'=>'./?p=flexreps&name=finmonitoring', 'title'=>appEnv::getLocalized('mnu_report_finmionitor') ];
        }
        if ($suadmin) {

            $submenu['datamgr'] = array('href'=>'./?p=adminpanel', 'title'=>appEnv::getLocalized('mnu-datamgr'));
            $submenu['acledit'] = array('href'=>'./?p=acleditor', 'title'=>appEnv::getLocalized('title-acldesigner'));
            if (self::isStandalone())
                $submenu['psw_policies'] = array('href'=>'./?p=editref&t=arjagent_pswpolicies', 'title'=>appEnv::getLocalized('title-psw_policies'));
            # $submenu['dailyjobs'] = array('title'=>appEnv::getLocalized('title-dailyjobs'), 'href'=>'dailyjobs.php');

            $submenui = array(
               'ref_countries'  => array('href'=>'./?p=editref&t=alf_countries', 'title'=>appEnv::getLocalized('mnu-list-countries'))
              ,'ref_regions'    => array('href'=>'./?p=editref&t=regions', 'title'=>appEnv::getLocalized('mnu-list-regions'))
              ,'ref_prod_config'  => array('href'=>'./?p=editref&t=alf_product_config', 'title'=>appEnv::getLocalized('mnu-list-product_config'))
              ,'ref_dept_rekv'    => array('href'=>'./?p=dept_props', 'title'=>appEnv::getLocalized('mnu-dept_properties'))

              ,'alf_dept_product' => array('href'=>'./?p=editref&t=alf_dept_product', 'title'=>appEnv::getLocalized('mnu-list-dept_product'))
              ,'stmt_ranges' => array('href'=>'./?p=stmt_ranges', 'title'=>appEnv::getLocalized('mnu-stmt_ranges'))
              ,'globalrisks' => array('href'=>'./?p=editref&t=alf_agmt_risks', 'title'=>appEnv::getLocalized('mnu-list_global_risks'))
              ,'cumul_limits' => array('href'=>'./?p=editref&t='.PM::TABLE_CUMLIR, 'title'=>appEnv::getLocalized('mnu-list_cumlir'))
              ,'promoactions' => array('href'=>'./?p=editref&t=alf_promoactions', 'title'=>appEnv::getLocalized('mnu-list_promoactions'))
              # ,'bnk_alf_svcusers' => array('title'=>appEnv::getLocalized('title_alf_svcusers'), 'href'=> './?p=editref&t=alf_svcusers')
              ,'bnk_alf_tranches' => array('title'=>appEnv::getLocalized('title_alf_tranches'), 'href'=> './?p=editref&t='.self::TABLE_TRANCHES)
              ,'list_professions' => array('title'=>appEnv::getLocalized('title_professions_list'), 'href'=> './?p=editref&t='.self::TABLE_PROFESSIONS)
              ,'list_curators' => array('title'=>appEnv::getLocalized('title_curators'), 'href'=> './?p=editref&t='.PM::T_CURATORS)
            );
            # if ($admlev || $suadmin ) {
               $submenui['alf_exportcfg'] = array('href'=>'./?p=editref&t=alf_exportcfg', 'title'=>appEnv::getLocalized('mnu-list_alf_exportcfg'));
               if (method_exists('appEnv', 'prependEditRef'))
                  appEnv::prependEditRef('alf_exportcfg','appEnv::addAjaxUploader');
            # }
            $submenu['mnu_ins_block'] = array('title'=>'Страхование...', 'submenu' => $submenui);
            $submenu['mnu_all_tariffs'] = array('title'=>'Настройка продуктов/тарифов...', 'submenu' => '');

            # WriteDebugInfo("added mnu_all_tariffs");
            # self::$_mainmenu['mnu_reports']['sysreps'] = ['title'=> 'Системные', 'submenu' => $submnu_sysrep];
        }

        if ($saleSupport) {
            $submnu_sysrep['invAnketas_bank'] = ['href'=>'./?p=flexreps&name=investAnketas', 'title'=>appEnv::getLocalized('mnu_invanketa_bank') ];
            $submnu_sysrep['invAnketas_agents'] = ['href'=>'./?p=flexreps&name=investAnketasAgent', 'title'=>appEnv::getLocalized('mnu_invanketa_agents') ];
            $submnu_sysrep['reworkPolicies'] = ['href'=>'./?p=flexreps&name=reworkPolicies', 'title'=>appEnv::getLocalized('mnu_report_reworkpolicies') ];
            $submnu_sysrep['reworkPolicies02'] = ['href'=>'./?p=flexreps&name=reworkPolicies02', 'title'=>appEnv::getLocalized('mnu_report_reworkpolicies02') ];

        }
        if (count($submnu_sysrep))
            appEnv::addSubmenuItem('mnu_reports', 'sys_reports', appEnv::getLocalized('service_reports'),'',   false, $submnu_sysrep);

        if(count($submenu)) self::$_mainmenu['mnu_admin']['submenu'] = $submenu;

        $submenu2 = array();
        $submenu2['myrights'] = array('title'=>appEnv::getLocalized('mnu-view-my-rights'), 'href'=>'./?p=myrights');

        if ($admlev || $usradmin || $suoper || $suadmin)
            $submenu2['seek_uwinfo'] = array('title'=>appEnv::getLocalized('mnu_seek_uwinfo'), 'href'=>'./?p=seekuwinfo');
            # appEnv::addSubmenuItem('mnu_utils','seek_uwinfo','mnu_seek_uwinfo','./?p=seekuwinfo');

        # if (self::$auth->getAccessLevel('admin')) $submenu2['org-structure'] = array('title'=>appEnv::getLocalized('mnu-view-dept_tree'), 'href'=>'./?p=viewDeptTree');

        self::$_mainmenu['mnu_utils']['submenu'] = $submenu2;

        if(self::$auth->SuperVisorMode()) { # only for SUPERVISOR
            $supermnu = array('pinfo' => array('title'=>'PHP Info', 'href'=>'./?p=pinfo')
                             ,'_patcher_' => array('title'=>'Patcher', 'href'=>'./?p=patcher')
                             ,'tests' => array('title'=>'Test/evals page', 'href'=>'./?p=evals')
            );
            if (function_exists('opcache_get_status')) {
                $supermnu['opcache_stat'] = array('title'=>'Статус OPcache', 'href'=>'./?p=opcachestat');
            }
            if (webApp::isDeveloperEnv() && self::isStandalone())
               $supermnu['wapluginator'] = array('title'=>'Plugin generator', 'href'=>'./?p=wapluginator');

            appEnv::addSubmenuItem('mnu_utils', 'mnu_supervisor', 'Super-Admin...', '','',$supermnu);
        }

	    $hlphref = (constant('IN_BITRIX') ? './?p=helpme' : './?p=help');
		self::$_mainmenu['mnu_docs']['submenu'] = array();

/*	    self::$_mainmenu['mnu_docs']['submenu'] = array(
	         'help_main'  => array('href'=>$hlphref, 'title'=>appEnv::getLocalized('title-help'))
	        ,'help_files' => array('href'=>'./?p=showfiles','title'=>appEnv::getLocalized('mnu-showfiles'))
	    );
*/
        if (!constant('IN_BITRIX')) { # под Bitrix справку временно прикрываю!
           self::$_mainmenu['mnu_docs']['submenu']['help_main'] = array('href'=>$hlphref, 'title'=>appEnv::getLocalized('title-help'));

		}
		self::$_mainmenu['mnu_docs']['submenu']['help_files'] = array('href'=>'./?p=showfiles','title'=>appEnv::getLocalized('mnu-showfiles'));
      }
      if(class_exists('CfgAnketa') && $suadmin) {
          appEnv::addSubmenuItem('mnu_admin', 'cfg_anketas', appEnv::getLocalized('mnu_cfg_anketas'),'./?p=editref&t='.CfgAnketa::T_ANKETALST);
      }
      self::runPlugins('init');
      if ($suadmin || WebApp::$workmode<100) {
          self::runPlugins('modify_mainmenu');
          # {upd/15.04.2015} Заполняю пункты меню инфографики
	      if (appEnv::isStandalone()) # под битрикcом инфографику вырубаю!
          foreach(self::$_plugins as $plgid=>$plgObj) {
            if(method_exists($plgObj,'infographics')) {
                $lst = $plgObj->infographics();
                if (is_array($lst) && count($lst)>0) {
                    $sub_info = array();
                    foreach ($lst as $lstitem){ # array: [action_id, title]
                        $mnuid = $lstitem[0];
                        $mnutext = empty($lstitem[1]) ? "$plgid:infogr-$mnuid" : $lstitem[1];
                        $mnutitle = appEnv::getLocalized($mnutext,$mnutext);
                        $sub_info[$mnuid] = array('href'=>"./?plg={$plgid}&action=infographics&f=$mnuid",'title'=>$mnutitle);
                    }
                    appEnv::addSubmenuItem('mnu_infographics', "info-$plgid",(appEnv::getLocalized($plgid.':title',$plgid).'...'), '','',$sub_info);
                }
            }
          }
      }
      if (SuperAdminMode() && is_file(__DIR__. '/admin_menus.php')) {
          include_once(__DIR__. '/admin_menus.php');
      }
      # self::runPlugins('infographics'); # дорисует подменю mnu-infographics если есть чем 'mnu_infographics'
      self::$mainmenuHtml = '';
      # if (class_exists('PolicyModel')) PolicyModel::updateUI();
      # if (self::$auth->SuperVisorMode()) file_put_contents('__mainmenu.ttt', print_r(appEnv::$_mainmenu,1)); # debug
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
    #

    /**
    * Определяет права юзера на данную запись
    * ex. GetPolicyAccess()
    * TODO: переписать !
    * @param mixed $id - ИД полиса
    * @param mixed $pdata - даннве полиса, если
    * @return mixed 0|1|2 (0-нет доступа, 1-только просмотр, 2-редактирование)
    */
    public static function GetRecordAccess($id, $pdata=0) {

        if(!is_array($pdata)) $pdata = GetRawPolicyData($id);
        $prodid = isset($pdata['prodid']) ? $pdata['prodid'] : 1;
        $myid = isset(self::$auth->userid)? self::$auth->userid:0;
        $mydept = isset(self::$auth->deptid)? self::$auth->deptid:0;

        if(SuperAdminMode() OR $pdata['emp_id'] == $myid) $ret = 2;
        elseif($pdata['comp_id']==$mydept) {
            if(self::$auth->UserHasRoles("$prodid.editorpriv,'company.admin,*.admin,supervisor,control")) $ret = 2;
            elseif(self::$auth->UserHasRoles("$prodid.viewer")) $ret = 1;
        }
        elseif($pdata['zoneid']==self::$auth->zoneid && self::$auth->UserHasRoles('region.admin')) $ret = 2;
        else $ret = 0;

        if($pdata['b_printed']) $ret = min(1,$ret); # напечатанные полисы - только просмотр
        return $ret;
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

        if(isset(self::$_cache['subdept'][$startdept])) return self::$_cache['subdept'][$startdept];
        $ret = array();
        if(!$startdept) {
            $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id',"parentid='0'",1);
            if($onlyDirectChild) return $ret;
        }
        else self::__recDeptChildren($ret,$startdept, $onlyDirectChild);

        if(!isset(self::$_cache['subdept'])) self::$_cache['subdept'] = array();
        self::$_cache['subdept'][$startdept] = $ret;

        return $ret;
    }

    private static function __recDeptChildren(&$ret, $deptid, $onlydirect=false) {

        $deptarray = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id',"parentid='$deptid'",1);
        if(is_array($deptarray) && count($deptarray)) foreach($deptarray as $dpt) {
            if($dpt>0) {
                $ret[] = $dpt;
                if(count($ret)> 5000) exit('ERROR: Endless Nesting depts, Dept id:'.$dpt);
                if(!$onlydirect) self::__recDeptChildren($ret, $dpt,$onlydirect); # recursive search for sub-depts
            }
        }
    }
    public static function GetUserAgentNo($id) {
#        global $auth;
        if(!$id) $id = self::$auth->userid;
        $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'users','agentcode',"userid='$id'");
        return $ret;
    }
    public static function getUserAgentCode($userid=0) {

        if(!$userid) {
            if(self::$auth->SuperVisorMode()) return SUPERNAME;
            $userid = self::$auth->userid;
        }
#        appEnv::$db->log();
        $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'users','agentcode',array('userid'=>$userid));
        return $ret;
    }

    public static function getDeptIdByCode($deptid) {
        if(!isset(self::$_cached_deptcodes[$deptid])) {
            self::$_cached_deptcodes[$deptid] = ($deptid) ? CDbEngine::getInstance()->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id',array('dept_code'=>$deptid)): 0;
        }
        return self::$_cached_deptcodes[$deptid];
    }

    public static function createLogin($fullname) {

        $splt = preg_split('/[ ,;.:|\/\@\!]/',$fullname,-1,PREG_SPLIT_NO_EMPTY);
        $ruslogin = $splt[0] . (empty($splt[1])?'':mb_substr($splt[1],0,1)) . (empty($splt[2])?'':mb_substr($splt[2],0,1));
        $newlogin = $enlogin = Translit($ruslogin,0,true);
        $shifter = 0;
#        appEnv::$db->log(2);
        while($shifter<200) { # find unique login
            $rd = appEnv::$db->GetQueryResult(self::TABLES_PREFIX.'users','COUNT(1)',array('login'=>$newlogin));
            if(!$rd) break;
            $shifter++;
            $newlogin = $enlogin.$shifter;
        }
#        appEnv::$db->log(0);
        return $newlogin;
    }
    /**
    * Формирует массив ИД подразделений от текущего до "головного" включительно
    * @param $deptid - ИД стартового подразд.
    * @param $stopdept - на каком остановиться (если нет, смотрим список "головных" мета-подразд.
    * @since 1.64
    */
    public static function getDeptChainUp($deptid, $stopdept=0) {
    	$ret = array($deptid);
    	$curdept = $deptid;
    	$metas = ($stopdept) ? array($stopdept) : self::$meta_depts;
    	while(true) {
    		$parent = appEnv::$db->select(appEnv::TABLE_DEPTS, array('where'=>array('dept_id'=>$curdept),
    		  'fields'=>'parentid', 'singlerow'=>1, 'associative'=>1
    		));
    		$curdept = $parent['parentid'];
    		if (empty($curdept)) return $ret;
    		if (in_array($curdept, $ret)) break;
    		$ret[] = $curdept;
    		if (in_array($curdept, $metas)) break;
		}
		return $ret;
	}
    /**
    * Выводит структуру подразделений [начиная с указанного, если передан ID ]
    *
    * @param mixed $deptid ИД стартового подразделения
    */
    public static function getDeptTreeHtml($deptid=0, $level=0) {

        $myaclev = self::$auth->getAccessLevel(appEnv::ACTION_ADMIN);

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
            $depts = appEnv::$db->GetQueryResult(self::TABLES_PREFIX.'depts','dept_id,dept_name,b_active',array('parentid'=>0),1,1,0,'dept_name');
        }
        else {
#            $dept = appEnv::$db->GetQueryResult(self::TABLES_PREFIX.'depts','dept_id,dept_name,b_active',array('dept_id'=>$deptid),0,1);
#            $ret = "$dept[dept_name] ($dept[dept_id])";
            $depts = appEnv::$db->GetQueryResult(self::TABLES_PREFIX.'depts','dept_id,dept_name,b_active',array('parentid'=>$deptid),1,1,0,'dept_name');
        }
        if(is_array($depts)) {
            $ret .= "<table id='table_depttree' border='0' cellspacing='1' cellpadding='2'>";
            foreach($depts as $dept) {
                $tclass = $dept['b_active'] ? '' : ' inactive';
                $txt = "$dept[dept_name] ($dept[dept_id])";
                if($myaclev>=appEnv::ROLE_ADMIN || in_array($dept['dept_id'],$mydepts)) $txt = "<span id=\"sp_$dept[dept_id]\" style='padding:1px 6px' onclick='showPeopleInDept($dept[dept_id])' class='pnt'  title='Кликнуть для просмотра агентов'>$txt</span>";
                $ret .= "<tr class=\"even\"><td class='nesting{$tclass}' id='tddept_$dept[dept_id]'>$txt" . self::getDeptTreeHtml($dept['dept_id'],($level+1)) . '</td></tr>';
            }
            $ret .= '</table>';
        }
        return $ret;
    }

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
        if (!empty(appEnv::$_p['setdebug'])) {
            error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        }
        if (WebApp::$workmode>99 && !SuperAdminMode()) return;
        if (isset(appEnv::$_p['p'])) {
            $pagename = trim(appEnv::$_p['p']);
            if (is_callable("self::$pagename")) {
                self::setHandled();
                self::$pagename();
                if (self::isStandalone()) exit;
                return;
            }
        }
        parent::dispatch();
    }
    /**
    * Получает очередной номер для полиса/заявления указанной серии (кода продукта)
    *  OBSOLETE - больее не пользуется !
    * @param mixed $prod - код серии
    */
    public static function getNextPolicyNo($prod=false, $testing=false) {

        if(!$prod) $prod = isset($this->_p['prod'])? $this->_p['prod'] : 'BCA';

        $deptid = self::$auth->deptid;
        $userid = self::$auth->userid;
        $rangeId = self::getProductRange($prod);
        $result = self::$db->sql_query("INSERT INTO " . self::TABLE_STMTNUMBERS . " (prodtype,deptid,userid,dt_created) VALUES ('$rangeId','$deptid','$userid',NOW())");
        if(!$result) return 0;
        $newid = appEnv::$db->insert_id();
        self::$db->sql_query('LOCK TABLES '.self::TABLE_STMTNUMBERS.' READ'); # блокирую для избежания выдачи двоим одного и того же номера
        $inprod = "'$prod'";
        if($testing) sleep(4);
        # общий пул для кодировок одного продукта
/*        if($prod ==='BCA' or $prod==='BEA') $inprod = "'BCA','BEA'";
        elseif($prod ==='BEL' or $prod==='BCL') $inprod = "'BEL','BCL'";
*/
        $next = appEnv::$db->getQueryResult(self::TABLE_STMTNUMBERS,'MAX(stmtnumber)+1',array('prodtype'=>$rangeId));

        self::$db->sql_query('LOCK TABLES '.self::TABLE_STMTNUMBERS.' WRITE'); # блокирую для избежания выдачи двоим одного и того же номера
        self::$db->update(self::TABLE_STMTNUMBERS, array('stmtnumber'=>$next), array('id'=>$newid));
        self::$db->sql_query("UNLOCK TABLES");
        return $next;
    }

    # получить "официальное" наим-е подразделения (для вывода в документы)
    public static function getOfficialDeptName($dept=0) {

        if(!$dept) $dept = self::$auth->deptid;
        $ret = appEnv::$db->select(self::TABLES_PREFIX. 'depts', array('fields'=>'official_name,dept_name','where'=>array('dept_id'=>$dept)));
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

        if(!$deptid) $deptid = getPrimaryDept();
        $where = array('deptid'=>$deptid);
        $order = '';
        if ($module) {
            $where[] = "(module='' OR FIND_IN_SET('$module',module))";
            $order = 'module DESC';
        }
        $data = appEnv::$db->select(PM::T_OU_PROPS, #  'alf_dept_properties'
            array('where'=>$where,'orderby'=>$order, 'singlerow'=>1)
        );
        # WriteDebugInfo("getDeptRequizites($deptid, $module):", $data);
        # return ("last sql:" . appEnv::$db->getLastQuery());
        return $data;
    }

    /**
    * Формирую полный почтовый адрес из непустых компонент (индекс, область, район, город, улица, дом, строение, кв.)
    *
    * @param mixed $adr
    */
    public static function BuilPostAddress($adr) {
        $ar = array();
        if (!empty($adr['country']) && !PlcUtils::isRF($adr['country'])) {
            $ar['country'] = PlcUtils::decodeCountry($adr['country']);
        }
        if(!empty($adr['postcode'])) $ar['postcode'] = $adr['postcode'];
        if(!empty($adr['region'])) {
            $ar['region'] = plcUtils::getRegionName($adr['region']);
        }
        if(!empty($adr['district'])) $ar['district'] = $adr['district'];
        if(!empty($adr['city'])) {
            $ar['city'] = $adr['city'];
            if(mb_stripos($adr['city'], 'г.')===false && mb_stripos($adr['city'], 'пос.')===false )
            $ar['city'] = 'г. '.$ar['city'];
        }
        if(!empty($adr['street'])) {
            $ar['street'] = $adr['street'];
            if (   mb_stripos($adr['street'], 'ул.')===false
                && mb_stripos($adr['street'], 'пер.')===false
                && mb_stripos($adr['street'], 'проспект')===false
            )  $ar['street'] = 'ул. '.$ar['street'];
        }
        if(!empty($adr['house'])) {
            $ar['house'] = $adr['house'];
            if(!empty($adr['corp'])) $ar['house'] .= '/корп. '.$adr['corp'];
            if(!empty($adr['build'])) $ar['house'] .= ' стр. '.$adr['build'];
        }
        if(!empty($adr['flat'])) $ar['flat'] = 'кв. ' . $adr['flat'];

        return implode(', ', $ar);
    }
    public static function getDeptCity($deptid=0) {

        $curdept = ($deptid<=0) ? self::$auth->deptid : $deptid;
        $nest = 0;
        if (isset(self::$_cache['deptcity_'.$deptid])) return self::$_cache['deptcity_'.$deptid];

        while($nest++<20 && $curdept>0) {
            $dta = appEnv::$db->select(self::TABLE_DEPTS
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
        $curdept = ($deptid<=0) ? self::$auth->deptid : $deptid;
        $primary = self::getMetaDepts2();
        $nest = 0;
        while($nest++<20 && $curdept>0) {
            $dta = self::$db->select(self::TABLE_DEPTS
                ,array(
                  'fields'=>array('parentid',$propid)
                 ,'where'=>array('dept_id'=>$curdept),'singlerow'=>1
                )
            );
            if(!isset($dta[$propid])) return '';
            if(!empty($dta[$propid])) return $dta[$propid];
            if (empty($dta['parentid']) || $dta['parentid'] == $curdept || in_array($dta['parentid'], $primary)) return '';
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
    * Получаем список для формирования SELECT со всеми "головными" банками, подразделениями и т.п.
    * <optgroup> делаем из названия "первичного" подразделения/подразделений ("БАНКИ",...)
    */
    public static function getHeadDepts() {
        if (!isset(self::$_cache['head-depts'])) {
            $idlist = self::getMetaDepts2();
            $ret = array();
            foreach ($idlist as $headid) {
                if (!$headid) continue;
                $lst = self::$db->select(self::TABLE_DEPTS, array(
                    'fields' => 'dept_id,dept_name'
                    ,'where' => array('parentid'=>$headid)
                    ,'orderby' => 'dept_name'
                    ,'associative'=>0
                    )
                );
                if (!is_array($lst) or count($lst)<1) continue;
                $ret[] = array('<' , GetDeptName($headid));
                $ret = array_merge($ret, $lst);
            }
            self::$_cache['head-depts'] = $ret;
        }
        return self::$_cache['head-depts'];
    }

    # то же что getHeadDepts() но включая опцию "0" = Все подразделения
    public static function getHeadDeptsAll() {
        $ret = array_merge(array(array('0','Все подразделения')), getAllPrimaryDepts());
        return $ret;
    }

    public static function GetDeptNameAll($deptid) {
        if ($deptid==0) return 'Все подразделения';
        return getDeptName($deptid);
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
        $dta = appEnv::$db->select(appEnv::TABLE_TRANCHES, $search );
        if (self::$_debug) {
            WriteDebugInfo("getDateTranche('$datesale','$prodcategory', '$codirovka') result:", $dta);
            WriteDebugInfo("sql_error:", appEnv::$db->sql_error(), ' sql:', appEnv::$db->getLastQuery());
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
        appEnv::logEvent("USERS.$opt","$txt учетной записи $recid",'',$recid);

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

    // формирует полное название подразд/филиала, до "головного": "Банк ВТБ / Ростовский филиал / X-ое Отделение"
    public static function getChainedDeptName($deptid) {

        $did = $deptid;
        $ret = '';
        $stopMe = 0;
        while(++$stopMe<=20) {
            $dpt = self::$db->select(self::TABLE_DEPTS, array('where'=>"dept_id=$did",'fields'=>'dept_name,parentid,b_metadept','singlerow'=>1));
            if (!empty($dpt['b_metadept'])) {
                $ret = "<b>$dpt[dept_name]</b> : $ret";
                break;
            }
            $ret = $dpt['dept_name'] . (($ret) ? " / $ret" :'');

            if (empty($dpt['parentid']) || $dpt['parentid']==$did) break;
            $did = $dpt['parentid'];
        }
        return $ret;
    }
    // функции, дающие список для выбора настроек печати анкет в заявлении/полисе
    # анкета ФЛ(страхователя и застрахованного. Отбирает по маске akketa-fl-{any_name}.xml, для застрахованного доджен бытьпарный файл akketa-fl-{any_name}.xml
    public static function AnketaTypes() {
        $ret = array( ['0','Не печатать'] );
        if (is_file(ALFO_ROOT . 'templates/anketa/anketa-fl.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/anketa-fl-EDO.xml'))
                $ret[] = [ '1','Стандартная (с ЭДО)'];
            else $ret[] = ['1','Стандартная'];
        }

        $files = glob(ALFO_ROOT . 'templates/anketa/anketa-fl-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
            if(strlen($bname) > 10) $ret[] = array(substr($bname,10), substr($bname,10));
        }
        return $ret;
    }
    public static function AnketaTypesUL() {
        $ret = array(['0','Не печатать']);
        if (is_file(ALFO_ROOT . 'templates/anketa/anketa-ul.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/anketa-ul-EDO.xml'))
                $ret[] = [ '1','Стандартная (с ЭДО)'];
            else $ret[] = ['1','Стандартная'];
        }

        $files = glob('templates/anketa/anketa-ul-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
           if(strlen($bname) > 10) $ret[] = array(substr($bname,10), substr($bname,10));
        }
        return $ret;
    }

    public static function AnketaBenefTypes() {
        $ret = array(['0','Не печатать']);
        if (is_file(ALFO_ROOT . 'templates/anketa/anketa-benef.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/anketa-benef-EDO.xml'))
                $ret[] = [ '1','Стандартная (с ЭДО)'];
            else $ret[] = ['1','Стандартная'];
        }

        $files = glob('templates/anketa/anketa-benef-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
            if(strlen($bname) > 13) $ret[] = array(substr($bname,13), substr($bname,13));
        }
        return $ret;
    }

    public static function OpListTypes() {
        $ret = array(array('0','Не печатать'));
        if (is_file(ALFO_ROOT . 'templates/anketa/oplist-fl.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/oplist-fl-EDO.xml'))
                $ret[] = [ '1','Стандартный oplist-fl (с ЭДО)'];
            else $ret[] = ['1','Стандартный oplist-fl'];
        }

        $files = glob(ALFO_ROOT . 'templates/anketa/oplist-fl-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
            $subName = substr($bname,10);
            if(strlen($bname) > 10) {
                $edoName = substr($bname,0, -4) . '-EDO.xml';
                if (is_file(ALFO_ROOT . 'templates/anketa/'.$edoName))
                    $ret[] = array($subName, $subName.' (с ЭДО)');
                else $ret[] = array($subName, $subName);
            }
        }
        return $ret;
    }
    // список для выбора "дополнительных" листов печати в полис (уведомление клиенту, спец-анкеты и т.д.
    public static function AdditionalPrintouts() {
        $ret = array(array('','нет'));
        $files = glob(ALFO_ROOT . 'templates/anketa/*.xml');
        if(is_array($files)) foreach($files as $fname) {
            $bname = basename($fname);
            if (fnmatch ('anketa*.xml', $bname)) continue;
            if (fnmatch ('oplist*.xml', $bname)) continue;
            if (substr($bname, -8) === '-EDO.xml') continue;
            $edoName = substr($bname,0, -4) . '-EDO.xml';
            if (is_file(ALFO_ROOT . 'templates/anketa/'.$edoName))
                $ret[] = array($bname, $bname.' (с ЭДО)');
            else $ret[] = array($bname, $bname);
        }
        return $ret;
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
            $pDept = getPrimaryDept();
            $where[] = "(f_restrict = '' OR FIND_IN_SET('$pDept',f_restrict))";
        }
        $arr = self::$db->select(self::$table_uploadedfiles, array('where'=>$where, 'orderby'=>'category,id'));
        # WriteDebugInfo("files select:",self::$db->getLastQuery()); WriteDebugInfo("files list:",$arr);
        $categs = self::getFileCategories();
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
        $pDept = getPrimaryDept();
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

        $tbl = new CTableDefinition(self::TABLE_UPLOADEDFILES); # 'alf_uploadedfiles'
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
    public static function getfile($idPar = FALSE) {
        if (!empty($idPar)) $fid = $idPar;
        else $fid = isset(self::$_p['fid']) ? self::$_p['fid'] : 0;
        if(!$fid) exit('Empty call (no file ID)');
        $fileprefix = 'filebody'; # could be another pref...

        # check "per-moduleId" restrictions before send file.
        $finfo = self::$db->select(self::TABLE_UPLOADEDFILES ,array('where'=>array('id'=>$fid),'singlerow'=>1));

        $access = self::checkFileAccess($finfo);
        if(!$access) exit(self::getLocalized('err-no-rights'));

        $fullname = self::$FOLDER_FILES .  $fileprefix . '-'.str_pad($fid, 8,'0',STR_PAD_LEFT) . '.dat';
        if(!file_exists($fullname)) exit(self::getLocalized('err_file_not_found'));
        if(defined('IN_BITRIX') && constant('IN_BITRIX')) self::clear_ob();
        if(!headers_sent()) {
            $unixftime = filemtime($fullname);
            $fdate = gmdate('D, d M Y H:i:s', $unixftime) . ' GMT';
            Header('Pragma: no-cache');
            Header('Pragma: public');
            Header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            if(!empty($finfo['filetype'])) Header('Content-Type: '.$finfo['filetype']);
            $sendname = urlencode(str_replace(' ','_',$finfo['filename']));
            Header("Content-Disposition: attachment; filename=\"$sendname\"");
            Header('Content-Length: '.filesize($fullname));
            header('Last-Modified: ' . $fdate);
        }
        readfile($fullname);
        exit;
    }

    public static function deptProdParams($module, $headdept, $codirovka='') {
        $where = array('module'=>$module, 'deptid'=>$headdept,'b_active'=>1);
        if ($codirovka) $where[] = "(prodcodes='' OR FIND_IN_SET('$codirovka',prodcodes))";
        $ret = appEnv::$db->select('alf_dept_product',
          array('where'=>$where, 'singlerow'=>1,'orderby'=>'prodcodes DESC')
        );
        return $ret;
    }

    /**
    * Вернет строку с названием документа в родительном падеже: "паспорта", "свид-ва о рождении"...
       'pass'    => array(1,'Паспорт РФ', 0) # серия, номер, дата выдачи, место выдачи, код подр.
      ,'voenbil' => array(3,'Военный билет', 1)
      ,'svro'    => array(2,'Свид-во о рождении', 4) # серия, номер, дата выдачи, место регистрации
      ,'inopass' => array(6,'Паспорт иностр.гражд.', 6) # одно длинное поле (номер и проч.)
      ,'migcard' => array(20,'Миграционная карта', -1) # серия, номер, дата оконч-я срока пребывания (обяз?)
      ,'other'    => array(99,'Иной документ', 99)  # одно длинное поле (номер и проч.)
    *
    * @param mixed $doctype
    */
    public static function decodeDocTypeRp($doctype) {
        switch( $doctype ) {
            case '1':
                return 'паспорта';
            case '2':
                return 'свид-ва о рождении';
            case '3':
                return 'военного билета';
            case '4':
                return 'занранпаспорта';
            case '6':
                return 'иностранного паспорта';
            case '20':
                return 'миграционной карты';
        }
        return 'иного документа';
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

    // appEnv::log($any_params) - write to _debuginfo.log
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

    public static function logEvent($stype, $stext='', $deptid='', $itemid=0, $uid=0){

        global $auth, $authdta;
        $ipaddr = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'SYSTEM';
        if(!$uid) $uid = isset($auth->userid)? $auth->userid : '0';
        if( !$deptid ) {
            if(!empty($auth->deptid)) $deptid = $auth->deptid;
            elseif($uid>0) $deptid = self::$db->GetQueryResult($authdta['table_users'],$authdta['usrdeptid'],array($authdta['usrid']=>$uid));
        }
        if ($uid == 0) {
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
        $insdta = array(
            'evdate' => date('Y-m-d H:i:s')
           ,'ipaddr' => $ipaddr
           ,'userid' => $uid
           ,'deptid' => $deptid
           ,'userid' => $uid
           ,'itemid' => $itemid
           ,'evtype' => $stype
           ,'evtext' => $stext
        );
        if (!empty(self::$_plg_name)) $insdta['module'] = self::$_plg_name; # since 0.8.031

        $ret = self::$db->insert(self::$TABLES_PREFIX . 'eventlog', $insdta);
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
    # trim for UNICODE strings
    public static function mbtrim($var) {
        $chars = [' ',"\t", "\n", "\r"];
        while(in_array(mb_substr($var,0,1,self::$charset), $chars)) {
            $var = mb_substr($var,1,NULL, self::$charset);
        }
        while(in_array(mb_substr($var,-1,NULL,self::$charset), $chars)) {
            $var = mb_substr($var,0,-1, self::$charset);
        }
        return $var;
    }
    public static function viewStmtState($stateid) {
        if (isset(PolicyModel::$stmt_states[$stateid])) return PolicyModel::$stmt_states[$stateid];
        return "[$stateid]";
    }

    # {upd/2020-09-17} если у учетки включен флажок "тестовая", вернуть TRUE
    public static function isTestAccount($userid=0) {
        if (!empty($userid) && $userid != appEnv::$auth->userid) {
                $usrdta = appEnv::$db->select(PM::T_USERS,['fields'=>'is_test',
                  'where'=>['userid'=>self::$auth->userid],'singlerow'=>1]
                );
                return !empty($usrdta['is_test']);
        }
        else {
            if (!isset($_SESSION['test_account'])) {
                $usrdta = appEnv::$db->select(PM::T_USERS,['where'=>['userid'=>self::$auth->userid],'singlerow'=>1]);
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
        appEnv::finalize();
    }

    # показать журнал отладки
    public static function viewDebug() {

        $dbgFile = ALFO_ROOT . 'tmp/_debugsess_'.self::$auth->userid.'.log';
        if(is_file($dbgFile)) appEnv::appendHtml(file_get_contents($dbgFile));
        else appEnv::appendHtml('Файл журнала отладки не найден');
        appEnv::finalize();
    }
    # сессия под режимом отладки?
    public static function isDebug() {
        return (isset($_SESSION['userdebug']) ? $_SESSION['userdebug'] : FALSE);
    }

    # получение "системных" папок приложения
    public static function getAppFolder($folderType) {
        switch($folderType) {
            case 'templates': return ALFO_ROOT.'templates/';
            case 'plugins': return ALFO_ROOT.'plugins/';
            default:
                $ret = ALFO_ROOT . $folderType . (substr($folderType,-1)==='/' ? '':'/');
                return $ret;
        }
        return ALFO_ROOT;
    }
    #  мой ИД учетки
    public static function myId() {
        return (isset(self::$auth->userid)? self::$auth->userid : 0);
    }
    public static function getMyMetaType() { return self::$userMetaType; }
    public static function getUserId() {
        return self::$auth->userid;
    }
} # appEnv end

# WebApp::$traceMe = 10;

HeaderHelper::setAppVersion(to_date(APPDATE));

if (constant('IN_BITRIX') > 1) {
    appEnv::$FOLDER_APP = __DIR__ . DIRECTORY_SEPARATOR;
    appEnv::$FOLDER_ROOT = dirname(__DIR__) . DIRECTORY_SEPARATOR;
}
else {
    appEnv::$FOLDER_ROOT = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    set_error_handler('ErrorHandler'); # activate error intersepting
    register_shutdown_function( 'shutdownHandler' );
    define('NEW_JQUERY',FALSE);
}

# подключаю авто-загрузку из папки app/ или c namespace
spl_autoload_register(function ($name) {
    $loname = strtolower($name);
    if (strpos($loname, '\\')!==FALSE) {
        # \plugins\plgname\ClassName - подключаю plugins/plgname/classname.php
        $items = preg_split( "/[\\\]/", $loname, -1, PREG_SPLIT_NO_EMPTY );
        $flname = ALFO_ROOT . implode('/',$items). '.php';
        if(is_file($flname)) include_once($flname);
    }
    else {
        if (is_file(__DIR__ . "/$loname.php"))
            include(__DIR__ . "/$loname.php");
        elseif (is_file(__DIR__ . "/class.$loname.php"))
            include(__DIR__ . "/class.$loname.php");
    }
});

if(file_exists(appEnv::$FOLDER_ROOT.'cfg/dbconnect.inc.php'))
	include_once(appEnv::$FOLDER_ROOT.'cfg/dbconnect.inc.php');
elseif(file_exists('../dbconnect.inc.php')) include_once('../dbconnect.inc.php');

/*
# защита от коротких сессий на вирт.хостинге
if(!empty($sitecfg['folder_sessions']) && is_dir($_SERVER['DOCUMENT_ROOT'].$sitecfg['folder_sessions']))
  ini_set('session.save_path', $_SERVER['DOCUMENT_ROOT'].$sitecfg['folder_sessions']);
*/
# Load Localization strings:
$lang = appEnv::DEFAULT_LANG; # empty($_COOKIES['userlang']) ? appEnv::DEFAULT_LANG : trim($_COOKIES['userlang']);

appEnv::SetLocalization($lang);

include_once(ALFO_ROOT . 'app/auth_tabledefs.php');
$auth_iface = array();
$auth_iface['server-time'] = '';

require_once('waRolemanager.php');
$options = array( 'tableprefix' => appEnv::TABLES_PREFIX);
$rolemgr = new WaRolemanager($options);

if (constant('IN_BITRIX')> 1) {
    if (!class_exists('CAuthCorp'))
        include_once(ALFO_INCLUDES . 'as_authcorp_btx.php');
}
else {
    if (!class_exists('CAuthCorp'))
        require_once('as_authcorp.php');
    if (!class_exists('PasswordManager'))
        require_once('as_passwordmgr.php');
}

if (version_compare(PHP_VERSION, '7.0') <0) {
    if (!class_exists('CDbEngine'))
    	include_once(ALFO_INCLUDES . 'as_dbutils_btx.php');
}
else {
    if (version_compare(PHP_VERSION, '7.0')>0 || (defined('USE_PDO') && constant('USE_PDO'))) {
        require_once('dbaccess.pdo.php');
        $GLOBALS['as_dbengine'] = new CDbEngine();
    }
    else {
    	if (!class_exists('CDbEngine'))
        	require_once('as_dbutils.php');
    }
}

if(appEnv::$_debug) {
    WriteDebugInfo('call URI:', $_SERVER['REQUEST_URI']);
    WriteDebugInfo('_GET:', $_GET);
    WriteDebugInfo('_POST:', $_POST);
}

if (!defined('API_CALL')) {
    if (constant('IN_BITRIX')) {
        UseJsModules('jquery,dropdown,ui,as_jsfunclib,floatwindow,js/alfo_core');
        WebApp::setDropdownMenuClass('dropdown');
    }
    else {
        UseJsModules('jquery,dropdown,ui,datepicker,as_jsfunclib,floatwindow,js/alfo_core,sticky');
        WebApp::setDropdownMenuClass('dropdown'); # jdmenu
    #    UseJsModules('jquery,jdmenu,datepicker,as_jsfunclib,floatwindow,js/alfo_core,sticky');
    }
    # UseJsModules('../js/jquery.sticky.js');

    # if(@constant('NEW_JQUERY')) UseJsModules('jquery-migrate,modernizr');
    UseCssModules('css/' . HeaderHelper::$maincss); # styles.css

    $today = date('d.m.Y');
    $todayYmd = date('Y-m-d');
    // старый js код для datepicker:
    // $('input.datebirth').not("[readonly]").on('click', function(){ $(this).datepicker({yearRange:'-80:+0',changeYear: true}).change(DateRepair);});
    //$('.datepicker').live('click', function() { $(".datepicker" ).datepicker(); });
    // $(document).on('focus',"input.datefield", function(){ $(this).datepicker({changeYear: true}); });

    $js_sticky = (constant('IN_BITRIX')) ? '' : '$("#toplinks").sticky({topSpacing:-2});';
    $readyjs = <<< EOJS
    $("input.datefield").not("[readonly]").datepicker({yearRange:'-100:+35',changeYear: true}).change(DateRepair);
    $('input.datebirth').not("[readonly]").datepicker({yearRange:'-100:+0',changeYear: true}).change(DateRepair);
    $('input.number').change(NumberRepair);

    $js_sticky
    jsToday = '$today'; jsTodayYmd = '$todayYmd';
EOJS;

    AddHeaderJsCode($readyjs,'ready');
}
$copyright = defined('OUR_COMPANY_NAME') ? constant('OUR_COMPANY_NAME') : 'Allianz Life';

$doctype = '';
# $tariftypes = false;

define('STARTPAGE','./'); # index.php
$auth = new CAuthCorp(1,1,array(
     'loadrolesfunc' => 'GetUserRoles'
    ,'appid' => (constant('IN_BITRIX')? 'alfo_' : 'arjagent')
    ,'showtime' => FALSE // ((constant('IN_BITRIX')) ? 0 : appEnv::$ENABLE_SERVERTIME)
    ,'use_uithemes' => 1
    ,'confirm_logout' => FALSE
    ,'encryptpassword' => 1
    ,'supervisor_right' => 'alfo_super' # supervisor
#    ,'restore_password' => 1 # у незалогиненного юзера показывать ссылку "восстановить пароль"
));

# $auth->SetOnLogon('appAfterLogon'); # перешибает код подразделения, если агент с данным кодом есть в arjagent_agents

appEnv::init(ALFO_ROOT);

if (!constant('IN_BITRIX') && appEnv::isTracingActivity()) appEnv::traceActivity();

$ifwidth = defined('IF_LIMIT_WIDTH') ? constant('IF_LIMIT_WIDTH')-66 : WebApp::$IFACE_WIDTH-20;
if ($ifwidth <= 0 || $ifwidth>720) $ifwidth= 720;

$ifaceTop = (constant('IN_BITRIX') ? '80':'10');
$headjS = "\n imgpath=\"" . IMGPATH. "\";\n gridimgpath = \"".FOLDER_JS ."ui-themes/".UI_THEME."/images/\";"
  . "\n ifaceWidth=$ifwidth;\nifaceTop=$ifaceTop;\n";

AddHeaderJsCode($headjS);

handleAjaxErrors();

# auth additional functions, site-dependent
function SuperAdminMode(){
    if (appEnv::$superadmin === NULL) appEnv::$superadmin = appEnv::$auth->IsSuperAdmin();
    return appEnv::$superadmin;
}
# разрешать ли настройку парольных политик
function pwdPolicyEditable() {
	return (appEnv::isStandalone() && SuperAdminMode());
}
# админ системы, но не обязат-но supervisor
function SysAdminMode() {
    return SuperAdminMode();
}

function SuperVisorMode() {
    return appEnv::$auth->SuperVisorMode();
}


function GetEmployeeFIO($id) {
  return appEnv::$auth->GetUserNameForId($id);
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
  appEnv::logEvent("$table $opt","$txt записи".$postData,'',$rec_id);
}

function DocAccessForUser($rw, $action='inputdata') {
  global $auth;
  if(SuperAdminMode()) return 10;
  if($auth->getAccessLevel(PM::RIGHT_SUPEROPER)) return 10;
  $myaclev = $auth->getAccessLevel($action);
  if(empty($myaclev)) return 0;
  if($rw->_plcdata['emp_id']== appEnv::$auth->userid) return 1;
  if($rw->_plcdata['comp_id']==appEnv::$auth->deptid && ($myaclev >= appEnv::ROLE_DEPTHEAD) ) return 1;
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

    $roles = WaRolemanager::getInstance()->getGrantableRolesList(appEnv::$auth->userid);
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
    return appEnv::decodeRole($id);
}
function AvailableDeptsList($action='inputdata') {
    global $auth;
    if($auth->getAccessLevel($action)<=appEnv::ROLE_DEPTHEAD) $cond = 'dept_id='.$auth->deptid;
    else $cond = '';
    $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id,dept_name',$cond,1,0,0,'dept_name');
#    WriteDebugInfo("AvailableDeptsList with access level ".$auth->a_rights['access'], 'sql:',appEnv::$db->GetLastQuery());
    return $ret;
}
function getUserFullName($userid) {
    $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'users','fullname',"userid='$userid'");
    return $ret;
}
# вывод очищенного phpinfo()
function pinfo() {

    appEnv::drawPageHeader('PHP info'); # appEnv::drawPageBottom();

    ob_start();
    phpinfo();
    $info = ob_get_clean();
    $spos = mb_strpos($info, '<div ');
    $info = mb_substr($info, $spos); # отрезал стартовые HTML мета-теги
    echo "<style>td {border:1px solid #aaa}</style>".$info;
    appEnv::finalize();
}


/**
* Вывод списка ролей и прав пользователя
*
*/
function myrights() {

    appEnv::setPageTitle('mnu-view-my-rights');
    $wdth = (defined('IF_LIMIT_WIDTH') ? (IF_LIMIT_WIDTH-30) : '700');
    $ret = '<br><br><div class="div_outline ct" style="width:' . $wdth . 'px;">';
    $ret .= WaRolemanager::getInstance()->showUserRightsVerbose(appEnv::$auth->userid);

    $plgadd = appEnv::runPlugins('showMyRights');
    if(count($plgadd)) {
        foreach($plgadd as $item) {
            $ret .= $item;
        }
    }
    if (appEnv::isTestAccount()) $ret .= "<div class='attention'>ВНИМАНИЕ: это ТЕСТОВАЯ учетная запись!</div>";
    $invank = PlcUtils::isInvestAnketaActive('');
    $ret .= "<br><div class='bounded'>Инвест-анкета: " .($invank ? 'Включена' : 'недоступна'). '</div>';
    $ret .= '</div>';
    appEnv::appendHtml($ret);
    appEnv::finalize();
}

function GetDeptList() {

    $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id,dept_name','',1,0,0,'dept_name');
    if(!is_array($ret)) $ret = array();
    return $ret;
}
# получает список для "подразделения" в списке диапазонов (при активности плагина может быть свой список!)
function getDeptsForRange() {

    $plg = appEnv::currentPlugin();
    if(!isset(appEnv::$_cache['DeptsForRange-'.$plg])) {

        $ret = array(array('0','Все подразделения'));
        if($plg && isset(appEnv::$_plugins[$plg]) && method_exists(appEnv::$_plugins[$plg], 'getDeptsForRange'))
             $dpt = appEnv::$_plugins[$plg]->getDeptsForRange();
        else $dpt = OrgUnits::getDeptsTree();
        if(is_array($dpt)) $ret = array_merge($ret, $dpt);

        appEnv::$_cache['DeptsForRange-'.$plg] = $ret;
    }
    return appEnv::$_cache['DeptsForRange-'.$plg];
}
/**
* Получение очередного номера полиса/заявления из пула диапазонов
* (новая, взамен appEnv::getNextPolicyNo - после отладки и ввода диапазонов заменить везде !)
* @since 0.96
* @param mixed $code Кодировка продукта
* @param mixed $dept подразделение. Ищется сначала для данного подразд-я, потом - в "универсальных" диапазонах
*/
function getNextStatementNo($code, $dept=0, $module = '') {

    # WriteDebugInfo("getNextStatementNo($code, $dept, $module ) call...");
    $descriptor = null;
    if (constant('POOL_FILE_FLAG')) {
        $filename = __DIR__ . '/../tmp/next-nomer.$$$';
        if (!file_exists($filename)) {
            file_put_contents($filename, 'ALFO Next statement lock file, created '.date('Y-m-d H:i:s'));
        }

        $descriptor = fopen($filename, 'w+');
        if (!$descriptor) {
            WriteDebugInfo("ALARM: Opening/creating semaphore file error $filename");
            return 0;
        }
        $lock_ok = 0;
        $try = 0;
        while(empty($lock_ok) && $try<100) {
            $lock_ok = flock($descriptor, LOCK_EX | LOCK_NB);
            if ($lock_ok) break;
            $try++;
            usleep(100000);
        }
        if(!$lock_ok) {
            WriteDebugInfo("ALARM: getNextStatementNo() Wait for locking file $filename failed, $try attempts");
            return 0;
        }


    }
    # WriteDebugInfo("getNextStatementNo($code, $dept)...");
    if (!$dept) $dept = 0;
    $code = mb_strtoupper(trim($code));

    # Если включен режим единого пула - ищу диапазон с ИД "UNIVERSAL"
    if(appEnv::$UNI_NUMBER_POOL)
        $code = 'UNIVERSAL';
    else {
        if(empty($code) && empty($module)) return 0;
        $dept = $calldept = intval($dept);
        if (empty($dept) && !empty(appEnv::$auth->deptid))
            $dept = $calldept = appEnv::$auth->deptid;

        $headdept = ($dept) ? getPrimaryDept($dept) : 0;
    }
    $retid = 0;

    appEnv::$db->sql_query('LOCK TABLES '. PM::T_STMT_RANGES .' READ');
    $iter = 0;

    if(appEnv::$UNI_NUMBER_POOL) {
        # dept не учитывать, ищем диапазоный с UNIVERSAL или пустым списком кодировок
        $where = "(rangestate>0) AND (currentno<=endno) AND ( codelist='' OR FIND_IN_SET('$code',codelist) )";
        $orderby = 'currentno';
    }
    else {
        $where = ['rangestate>0', 'currentno<=endno', 'rangestate>0',"deptid IN($headdept,0)"];
        if ($module!='') {
            if (substr($module,0,1) === '_')
                $where['module'] = $module;
            else $where[] = "(module='' OR FIND_IN_SET('$module',module))";
            if ($code) $where[] = "(codelist='' OR FIND_IN_SET('$code',codelist))";

        }
        else {
            $where[] = "FIND_IN_SET('$code',codelist)";
        }
        # $where = "(rangestate>0) AND (currentno<=endno) AND ( FIND_IN_SET('$code',codelist) ) AND (deptid IN($headdept,0))";
        $orderby = 'deptid DESC,module DESC, codelist DESC,currentno';
    }

    $lst = appEnv::$db->select(PM::T_STMT_RANGES, array('where'=>$where, 'orderby'=>$orderby));

    /*
    WriteDebugInfo("getNextStatementNo, qry:", appEnv::$db->getLastQuery());
    WriteDebugInfo("getNextStatementNo, err:", appEnv::$db->sql_error());
    WriteDebugInfo("found list",$lst);
    */
    $retid = 0;
    if(is_array($lst) and count($lst)>0) {
        foreach($lst as $recno => $range) {
            $next = $range['currentno'] + 1;
            if ($next <= $range['endno']) {
                $retid = $next;
            }
            if ($retid>0) {
                $dta = array('currentno'=>$next);
                if( $next>= $range['endno'] ) {
                    $dta['rangestate'] = '0'; # блокирую диапазон по достижению конеч.значения
                    $txt = "<b>Внимание !</b>\r\n"
                        . "В системе только что выдан последний номер полиса из диапазона $range[rangeid] (конечный: $range[endno]), импользуемый для кодировки $code \r\n"
                        . "Убедитесь в наличии в системе нового диапазона либо увеличьте конечный номер у этого !";
                    appEnv::$db->sql_query('UNLOCK TABLES');
                    AppAlerts::raiseAlert("POOL-WARN3-$code", $txt);
                    # appEnv::sendSystemNotification('RANGE OUT!',$txt);
                }
                elseif( $next>= $range['endno'] - appEnv::$POOL_WARNING_THRESHOLD2 ) {
                    appEnv::$db->sql_query('UNLOCK TABLES');
                    AppAlerts::resetAlert("POOL-WARN3-$code", "Проблема отсутствия номеров для $code устранена.");
                    AppAlerts::resetAlert("POOL-WARN1-$code", "Проблема запаса номеров для $code устранена.");
                    $last = appEnv::$POOL_WARNING_THRESHOLD2;
                    $txt = "<b>Внимание, второе предупреждение !</b>\r\n"
                        . "В системе ALFO осталось не более $last номеров полиса в диапазоне $range[rangeid] (конечный: $range[endno]), кодировка $code \r\n"
                        . "Своевременно проверяйте наличие в системе новых диапазонов либо увеличьте конечный номер у этого !";
                    AppAlerts::raiseAlert("POOL-WARN2-$code", $txt);
                    # appEnv::sendSystemNotification('RANGE OUT!',$txt);
                }
                elseif( $next>= $range['endno'] - appEnv::$POOL_WARNING_THRESHOLD1 ) {
                    appEnv::$db->sql_query('UNLOCK TABLES');
                    AppAlerts::resetAlert("POOL-WARN3-$code", "Проблема отсутствия номеров для $code устранена.");
                    AppAlerts::resetAlert("POOL-WARN2-$code", "Проблема запаса номеров для $code устранена.");
                    $last = appEnv::$POOL_WARNING_THRESHOLD1;
                    $txt = "<b>Внимание!</b>\r\n"
                        . "В системе ALFO осталось не более $last номеров полиса в диапазоне $range[rangeid] (конечный: $range[endno]), кодировка $code \r\n"
                        . "Своевременно проверяйте наличие в системе новых диапазонов либо увеличьте конечный номер у этого !";
                    AppAlerts::raiseAlert("POOL-WARN1-$code", $txt);
                    # appEnv::sendSystemNotification('RANGE OUT!',$txt);
                }
                else {
                    appEnv::$db->sql_query('UNLOCK TABLES');
                    AppAlerts::resetAlert("POOL-WARN1-$code", "Проблема запаса номеров для $code устранена.");
                    AppAlerts::resetAlert("POOL-WARN2-$code", "Проблема запаса номеров для $code устранена.");
                    AppAlerts::resetAlert("POOL-WARN3-$code", "Проблема отсутствия номеров для $code устранена.");
                }
                appEnv::$db->sql_query('LOCK TABLES '. PM::T_STMT_RANGES .' WRITE'); # блокирую во избежание выдачи двоим одного и того же номера
                $err = appEnv::$db->sql_error();
                if ($err) writeDebugInfo("sql error on LOCK TABLES: ", $err);
                appEnv::$db->update(PM::T_STMT_RANGES, $dta ,array('rangeid'=>$range['rangeid']));
                $err = appEnv::$db->sql_error();
                if ($err) writeDebugInfo("sql error on UPDATE smtm_ranges: ", $err);

                break;
            }
        }
    }
    #appEnv::$db->log(0);

    appEnv::$db->sql_query('UNLOCK TABLES');
    if (is_resource($descriptor)) {
        $unlock_ok = flock($descriptor, LOCK_UN);
        fclose($descriptor);
    }
    if($retid < 1) {
        appEnv::$svc_lastError = "Среди диапазонов номеров не найден свободный номер полиса для кодировки/продукта $code. Немедленно добавьте или расширьте диапазон!";
        AppAlerts::raiseAlert("POOL-ALARM-$code", appEnv::$svc_lastError);
        if (WebApp::$_debug) WriteDebugInfo(appEnv::$svc_lastError);
    }
    else AppAlerts::resetAlert("POOL-ALARM-$code", 1);
    # WriteDebugInfo("returning: ", $retid);
    return $retid;
    # appEnv::$db->log(0);
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
#    WriteDebugInfo("getDeptsTree (startDept=$startDept, action=$action)...");
    if (!empty(appEnv::$primary_dept)) {
#        WriteDebugInfo('for primary: ',appEnv::$primary_dept);
        $metaList = (is_array(appEnv::$primary_dept) ? implode(',', appEnv::$primary_dept) : appEnv::$primary_dept);
        $result = appEnv::$db->select(appEnv::TABLES_PREFIX.'depts',array(
          'fields'=>'dept_id,dept_name', 'associative'=>0,'where'=>"parentid IN($metaList)",'orderby'=>'dept_name'));
    }
    else {
        OrgUnits::__getOptionsDepts($result,$startDept,0, $action, $onlyactive);
    }
    return $result;
}

function __getOptionsDepts(&$result, $startDept=0, $level=0, $action='', $onlyactive=false) {
    if ($level == 0 && isset(appEnv::$_cache['deptTree_'.$startDept])) {
        # writeDebugInfo("return cached for $startDept");
        return appEnv::$_cache['deptTree_'.$startDept];
    }

    if($level>60) return; # exit("getOptionsDepts($startDept): Endless recursion !");
    if($level==0) $result = array();
    $prefix = ($level==0) ? '' : str_repeat(' &nbsp; &nbsp;',$level);
    $condpref = array();
    if ($onlyactive) $condpref[] = "b_active=1";

    if ($startDept==0) $startDept = appEnv::$start_dept;
    if ($startDept==0) {
        if (appEnv::$auth->isUserManager() || appEnv::$auth->getAccessLevel([appEnv::RIGHT_USERMANAGER,appEnv::RIGHT_DEPTMANAGER])) {
            $startDept=0;
        }
        elseif(empty($action)) {
            $startDept = appEnv::$auth->deptid;
            $result = array(array($startDept,
                appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_name',array('dept_id'=>$startDept))
            ));
        }
        elseif(appEnv::$auth->getAccessLevel($action) >= 20 /*appEnv::ROLE_DIRECTOR*/) {
            $startDept=0;
        }
        elseif(appEnv::$auth->getAccessLevel($action) == 12 /*appEnv::ROLE_DEPTHEAD*/) {
            __getOptionsDepts($result, appEnv::$auth->deptid,0,$action,$onlyactive);
            $startDept = appEnv::$auth->deptid;
        }
        else {
            return; # простой менеджер и агент никаких подразделений не видят!
        }
		$condpref[] = ($startDept==0) ? "parentid IN(0,dept_id)" : "parentid=$startDept";
    }
    else {

        $startdpt = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_name',"dept_id='$startDept'");
        $found = false;
        if(is_array($result)) foreach($result as $item) {
            if($item[0] == $startDept) { $found = true; break; }
        }
        if(!$found) $result[] = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id,dept_name,b_active','dept_id='.$startDept,0);
        # if($level==0) $result[] = array($startDept, $prefix.$startdpt . ((appEnv::$_debug || $auth->SuperVisorMode()) ? " ($startDept)" : ''));
		$condpref[] = "parentid=$startDept";
    }

    $dpts = appEnv::$db->select(appEnv::TABLES_PREFIX.'depts', array(
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
            $strid = (appEnv::$_debug || SuperAdminMode()) ? " ($dept[0])" : '';
            $ditem = array($dept[0], $prefix.$dept[1].$strid); # для debug вывожу еще ИД подразд.
            if($dept[2]=='0') $ditem['options'] = array('class'=>'inactive');
            $result[] = $ditem;
            __getOptionsDepts($result, $dept[0], $level2,$action, $onlyactive);
        }
    }
    if ($level == 0 ) {
        appEnv::$_cache['deptTree_'.$startDept] = $result;
    }
}

function GetDeptName($deptid, $official = false) {
    if (empty($deptid) || intval($deptid)<=0) return '-';
    if (!isset(appEnv::$_cache['deptname-'.$deptid])) {
        $dta = appEnv::$db->select(appEnv::TABLE_DEPTS, array('where'=>"dept_id='$deptid'",'singlerow'=>1,'fields'=>'dept_name,official_name'));
        appEnv::$_cache['deptname-'.$deptid] = $dta;
    }
    if (!$official) return appEnv::$_cache['deptname-'.$deptid]['dept_name'];
    return (empty(appEnv::$_cache['deptname-'.$deptid]['official_name'])?
         appEnv::$_cache['deptname-'.$deptid]['dept_name']
       : appEnv::$_cache['deptname-'.$deptid]['official_name']
    );
}

function getDeptRawData($deptid) {
    $ret = appEnv::$db->select(appEnv::TABLE_DEPTS, array(
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

    $ret = appEnv::getCached('user_fio',$userid);
    if($ret === null) {
        $ret = appEnv::$auth->GetUserNameForId($userid);
        appEnv::setCached('user_fio',$userid, $ret);
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
    $st = preg_split('/[ .,]+/',$fullname,NULL, PREG_SPLIT_NO_EMPTY);
    if ( $imia ) $st[1] = $imia;
    if ( $otch ) $st[2] = $otch;
    $ret = $st[0] . (isset($st[1]) ? ' '.mb_substr($st[1],0,1,MAINCHARSET).'.':'') . (isset($st[2]) ? (mb_substr($st[2],0,1,MAINCHARSET).'.') :'');
    return $ret;
}
# типы файлов "правил, шаблонов" и проч, доступных для загрузки
function getFileCategories() {
    return array(
        'blank'   => 'Бланки, шаблоны документов'
       ,'normdoc' => 'Нормативные Документы'
       ,'instruct'=> 'Инструкции, руководства'
       ,'other'   => 'Прочие файлы'
    );
}
function getMyDeptId() {
    return appEnv::$auth->deptid;
}
function getMyUserId() { return appEnv::$auth->userid; }

# Ф-ция проверки - Разрешать ли поле редактирования осн.роли в справочнике сотрудников
function isPrimaryRoleEditable() {
    global $ast_datarow;
    if (SuperAdminMode()) return true;
    if (isset($ast_datarow['usr_role'])) {
        $myroles = getGrantableRoles(true);
#        WriteDebugInfo($auth->userid, "Cur role: $ast_datarow[usr_role],  grantable roles:", $myroles);
        return (is_array($myroles) && in_array($ast_datarow['usr_role'], $myroles));
    }
    else return true;
}

# Ф-ции для грида редактирования учеток пользователей (app/users.php)
function isUserEditable($row=null) {
    if (appEnv::$auth->getAccessLevel(appEnv::RIGHT_USERMANAGER)) return TRUE;
    return isPrimaryRoleEditable($row);
}
function isUserDeletable($row=null) {
    if (appEnv::$auth->getAccessLevel(appEnv::RIGHT_USERMANAGER)) return TRUE;
    return isPrimaryRoleEditable($row);
}
# Вывожу разрешение на сброс пароля только если учетка доступна мне для редактирования
function getResetPswLink($row = null) {
    $lnk = '';
    if ( appEnv::$auth->getAccessLevel(appEnv::RIGHT_USERMANAGER) || isPrimaryRoleEditable($row))
        $lnk = '<a href="javascript://void()" onclick="waAuth.dlgResetPassword({ID})">сброс пароля</a>';
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
        $lst = appEnv::$db->select(appEnv::TABLES_PREFIX.'acl_roles', array(
            'fields'=>'roleid'
           ,'where' =>array('rolename'=>$rolename)
           ,'associative'=>0
        ));
        $roleid = isset($lst[0]) ? $lst[0] : 0;
    }
    if(!$roleid) return array();

    $add = appEnv::$db->select(appEnv::TABLES_PREFIX.'acl_userroles', array(
        'fields'=>'userid'
       ,'where' =>array('roleid'=>$roleid)
       ,'associative'=>0
    ));

    $where = "(usr_role='$roleid')";
    if(is_array($add) && count($add)>0) $where .= " OR (userid IN(" . implode(',',$add).'))';
    $agts = appEnv::$db->select(appEnv::TABLES_PREFIX.'users', array(
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
    $lst = appEnv::$db->select('alf_agmt_risks', array('fields'=>"id,riskename",'where'=>"parentrisk=0 $where",'orderby'=>'id','associative'=>0));
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
        $data['createdby'] = $data['updatedby'] = (empty(appEnv::$auth->userid) ? 'system' : appEnv::$auth->userid);
    }
    elseif ($act === 'doedit') $data['updatedby'] = (empty(appEnv::$auth->userid) ? 'system' : appEnv::$auth->userid);
    return $data;
}
/**
* Формирует список опций для выбора парольной политики для подразд.
*
*/
function optionsPswPolicies() {
    $ret = array(array('0','--нет--'));
    $pdata = appEnv::$db->select(
        appEnv::TABLES_PREFIX.'pswpolicies'
        ,array(
           'fields' =>'recid,plname'
          ,'orderby' => 'plname'
          ,'associative'=>0
        )
    );
    if (is_array($pdata)) $ret = array_merge($ret, $pdata);
    return $ret;
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
    $pplist = appEnv::$db->select(appEnv::TABLES_PREFIX.'pswpolicies'
      , array('fields'=>'recid,plname','orderby'=>'plname','associative'=>0)
    ); # table name!!!
    # [prefix]pswpolicies.recid
    if (is_array($pplist)) $data = array_merge($data, $pplist);
    return $data;
}
function showUserRole($roleid) {
    $dta = appEnv::$db->select(appEnv::TABLES_PREFIX.'acl_roles'
       ,array('where' => array('roleid'=>$roleid)
            ,'fields'=>'roledesc'
            ,'singlerow' => 1
       )
    );
    return (isset($dta['roledesc'])? $dta['roledesc'] : $roleid);
}
function getPluginTitle($plgid) {
    $ret = appEnv::getLocalized("$plgid:title");
    if (!$ret) $ret = appEnv::getLocalized("$plgid:main_title");
    if (!$ret) $ret = "[$plgid]";
    return $ret;
}
/**
* получает ИД головного агента [по заданному "мета-подразделению" либо от зарегистр.списка мета-подр.]
* {upd/16.09.2014} - теперь "головной банк" может быть списком (несколько "деревьев" головной-дерево дочерних подр)
* @param $metadept - код "мета-подразделения" ЛИБО ИД настроечной config-переменной, хранящей его
*/
function getPrimaryDept( $deptid=0) {

    global $authdta;
    if (appEnv::isClientCall()) {
        $deptid = appEnv::$client_deptid;
        # WriteDebugInfo("getPrimaryDept: API client dept $deptid");
    }
    $ret = $initdept = ($deptid> 0) ? $deptid : appEnv::$auth->deptid;

    $chkdept = appEnv::getCached('primaryDept', $ret);
    if($chkdept !== NULL) {
        return $chkdept;
    }

    $stopId = appEnv::getMetadepts2();

    if ( in_array($ret, $stopId) ) return $ret;
    $kkk = 0; # limit recursion
    while ($kkk++ < 32) {
        $pr = appEnv::$db->select(appEnv::TABLE_DEPTS, array(
            'fields'=>'parentid'
            ,'where'=>"dept_id='$ret'"
            ,'singlerow'=>1
        ));
        $parent = isset($pr['parentid']) ? intval($pr['parentid']) : 0;
        if($parent==0 || $parent == intval($ret) or in_array($parent,$stopId)) {
            break;
        }
        $ret = $parent;
    }
    # WriteDebugInfo("primary dept for $initdept is $ret");
    appEnv::setCached('primaryDept', $initdept, $ret);
    return $ret;
}
/**
* Выдает список для формирования <option>-list со всеми "головными" подразделениями
* (мета-подразд-я будут в роли <optgroup>)
*
*/
function getAllPrimaryDepts($moduleid = null) {

    $ret = appEnv::getCached('_getprimarydepts',0);
    if (!$ret) {
        # $baseList = appEnv::getMetaDepts2($moduleid);
        # Новый  подход - список мета-подразд получаю из прямо depts (у них поле b_metadept = 1)
        $ret = array();
        $baseList = appEnv::$db->select(appEnv::TABLE_DEPTS,array(
            'fields'=>'dept_id,dept_name'
            ,'where'=>'b_metadept>=1' # 1 - мета-подр для банков, 100 - "Все агенты"
            ,'associative'=>1
            ,'orderby'=>'dept_name'
        ));

        # TODO: сделать выбор только доступных в рамках текущих прав супер-операциониста
        foreach($baseList as $bdept) {
            $metaid = $bdept['dept_id'];
            $headname = $bdept['dept_name'];
            $dta = appEnv::$db->select(appEnv::TABLE_DEPTS,array(
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

        appEnv::setCached('_getprimarydepts',0, $ret);
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
	return (appEnv::isStandalone());
}
function userPwdEditable($p1=0,$p2=0) {

	if(!SuperAdminMode() || !appEnv::isStandalone()) return false;
	return (appEnv::$auth->isPasswordEncrypting() ? 'A' : true);
}

/**
* Basic functionality for pluginBackend Modules
*/
class stdBackend {
    # Creating roles & rights for the module (method backend::listRolesRights should exist
    # call: ./?plg={plugin}&action=makeroles
    public function makeRoles() {

        $dim = get_class($this);
        include_once(appEnv::$FOLDER_APP . 'acl.tools.php');
        aclTools::setPrefix(appEnv::TABLES_PREFIX);
        if (!method_exists($this,'listRolesRights')) {
            appEnv::appendHtml($dim . ' - plugin has no listRolesRights function !<br>');
            return;
        }
        $arr = $this->listRolesRights();
        # $mid = appEnv::currentPlugin();
        aclTools::upgradeRoles($mid, $arr);
        # if(appEnv::isStandalone()) exit;
    }
}

function getEditPrivileges($tableid) {
    $admlev = false;
    $super = SuperAdminMode();
    $ret = [0,0,0];
    switch($tableid) {
        case appEnv::TABLE_TRANCHES: # 'alf_tranches'
            $admlev = appEnv::$auth->getAccessLevel('bank:superoper');
            $ret = ($admlev || $siuper) ? [1,1,1] : [0,0,0];
            break;
        case 'alf_sentemail': # никаикх редактирований, только просмотр!
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
        $val = html_entity_decode($val, ENT_QUOTES );
    }
}
function evShowUser($usrid) {
    if ( !is_numeric($usrid) ) return $usrid;
    if ( $usrid == 0 ) return '--';
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
    $pars = appEnv::$_p;
    $tbname = isset(appEnv::$_p['t']) ? appEnv::$_p['t'] : '';
    $id = isset(appEnv::$_p['_astkeyvalue_']) ? appEnv::$_p['_astkeyvalue_'] : '';
    $ret = "AstGenerateView: <pre>Это данные для показа записи<br>" . print_r($pars,1). '</pre>';
    switch ($tbname) {
        case 'alf_sentemail':
            $rec = appEnv::$db->select($tbname, ['where'=>['id'=>$id], 'singlerow'=>1]);
            $ret = "<table class='zebra' style='width:100%'>"
              . '<tr><td>Дата</td><td>' . to_char($rec['send_date'],1). '</td></tr>'
              . '<tr><td>Адрес получателя</td><td>' . $rec['to_address']. '</td></tr>'
              . '<tr><td colspan="2">Текст сообщения<div class="bordered" style="padding:4px">' . nl2br($rec['msgbody']). '</div></td></tr>'
              . '</table>';
              ;
            break;
    }
    exit($ret);
}