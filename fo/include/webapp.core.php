<?php
/**
* @package webapp.core - Core classes / framework for [corporate] Web application
* @name webapp.core.php
* @version 1.30.002
* modified : 2025-09-26
* by Alexander Selifonov
**/
abstract class appPlugins {

    protected $_my_roles = [];
    protected $_my_folder = ''; # Must be redefined in real class according to the real class sub-folder in "plugins/"
    protected $backend = NULL; // will be "backend" object
    public $inReports = FALSE; // include objects in global reports
    /**
    * Returns TRUE, if user "primary role" is in "_my_roles" plugin list
    * Set your own $_my_roles in every plugin implementation !
    */
    public function setPrimaryRoles($roles) {
        $this->_my_roles = (is_scalar($roles) ? explode(',', $roles) : $roles);
    }

    public function getBackend() {
        if (is_object($this->backend)) return $this->backend;
        $clname = strtolower(get_class($this));
        $className = $clname . 'Backend';
        $plgFolder = WebApp::$FOLDER_ROOT . WebApp::$FOLDER_PLUGINS . $clname . '/';
        $bkModule = $plgFolder . 'backend.php';
        if (is_file($bkModule)) {
            include_once($bkModule);
            if (class_exists($className)) {
                $this->backend = new $className();
                return $this->backend;
            }
        }
        # throw new Exception("Cannot load $className class from ".$plgFolder);
        return FALSE;
    }

    public function dispatch($params=false) {

        if(!$params) {
            $params = decodePostData(true);
        }

        if ($this->backend === NULL) $this->backend = $this->getBackend();

        $action = isset($params['action']) ? $params['action'] : '';

        if(!empty($action) && is_string($action) && method_exists($this, $action))
            return $this->$action();

        if(is_object($this->backend)) {
            if(method_exists($this->backend, $action)) $this->backend->$action();
            elseif(method_exists($this->backend, 'dispatch')) $this->backend->dispatch();
        }
        return FALSE;
        # else die(get_class($this) . '-Backend Error: Unknown action for '.$action);
    }

    public function printPluginRoles() {
        echo get_class() . " : plugin roles are: <br>" . implode(',', $this->_my_roles) . '</b><br>';
    }
    public function listRolesRights() {
        $clname = strtolower(get_class($this));
        $plgRolesFile = WebApp::$FOLDER_ROOT . WebApp::$FOLDER_PLUGINS . $clname . '/roles.php';
        return(is_file($plgRolesFile) ? include($plgRolesFile) : FALSE);
    }
}

abstract class WebApp {

    const VERSION = '1.30';
    static $FOLDER_ROOT = '';
    static $FOLDER_APP = '';
    static $BASEURL = ''; # used in building "absolute" URI's in application
    static $sanitize_input = FALSE; // turn to true? to filter bad chars from self:$_p
    static $maintenanceMode = FALSE;
    private static $emailMakeBr = TRUE; # Auto NL2BR when sending HTML emails
    private static $emailConvert = FALSE; # user callback func to check/convert TO email addresses

    const FOLDER_APP     = 'app/';
    const FOLDER_APPJS   = 'js/';
    const FOLDER_PLUGINS = 'plugins/';
    const FOLDER_TESTS   = 'tests/';
    const FOLDER_CFG  = 'cfg/';
    const FOLDER_TEMPLATES  = 'templates/';
    const INIFILENAME = 'appconfig.ini';
    const ACTION_ADMIN = 'admin';
    const ACTION_HELPEDIT  = 'helpwriter';

    const EMAIL_TESTING_POSTFIX = 'fake'; # marks "testing" email address (no real Email sending)
    static $DEBUG = 0;
    static $TABLES_PREFIX = 'wa_';
    static $FOLDER_PLUGINS = 'plugins/'; # changable var!
    static $lang = '';
    static private $ini = NULL;
    static $cfgFiles = []; # all fiound xml def files
    static $userAppStates = []; # specific application states, setter: setUserAppState('key',$value), getter: getUserAppstate('key')
    private static $showMessages = [];
    /**
    *  working mode: FALSE = standard, 'REST' - as Rest API (REST service, all responses are JSON)
    * @var mixed use NOT implemented yet, just TODO!
    */

    static $apiMmode = FALSE;


    static $traceMe = FALSE; # WebApp::$traceMe

    static $workmode = 0; # 1: no updates=view only, 100 - full stop (no access)
    static $workmode_reason = ''; # HTTP text about current workmode reason (to print on start page)

    static protected $_appstate = 0; # 0 - normal state, 1...99- warnings, 100+ - fatal error
    static protected $_err_message = ''; # 0 - normal state, 1...99- warnings, 100+ - fatal error

    static public $msgSubst = []; # 'code'=>text substitutes for error messages

    static $_debug = 0; // Turn ON for debug logging
    static protected $INDEX_COLUMNS = 2;
    public static $_p = [];
    public static $db = null;
    static public $auth = null;
    static $_stubcode = [];
    static $isMobile = FALSE; # auto-detecting client Mobile devices
    static $deviceType = 'computer';
    static private $_deferredHeader = FALSE;
    static protected $inSoap = FALSE;
    static protected $_plg_name = ''; # active plugin|module id
    static protected $_subplg = ''; # if plg=myplg/subplugin, this will be "subplugin"
    static protected $_action = '';
    static protected $_headerDrawn = FALSE;
    static protected $_footerDrawn = FALSE;
    static protected $_page_title = '';
    static protected $i18n = array('charset'=>'windows-1251','_language_'=>'ru');
    static protected $postparams = [];
    static protected $getparams = [];
    static protected $paramsChain; // chain of params come in GET: p=goods/par1/pa2/par3/... gets [par1,par2,par23,...]
    static protected $err_message;
    static protected $_prmHandled = FALSE;
    static protected $_table_privileges = [];

    static protected $editRefPrenedData = [];

    static $deptlist_users = []; # dept list consumers

    static $_plugins = [];
    static $_tableSets = [];
    private static $_hooks = []; # Hook functions to add here: $_hooks['funcname'] = array('user_func_name',...);

    static public $_pwdmgr = null;
    static $_tableList = [];
    static private $_menuclass = 'jd_menu'; # default className for top-level menu <UL> element
    static $_mainmenu = [];
    static $mainmenuHtml = ''; # prepared HTML code for main menu
    static $_cached_deptcodes =  [];
    static $_cache =  [];
    static $_cachedagt = [];
    static $_defcfg = []; // configuration parameters definition
    static /*protected*/ $_cfg = []; // configuration parameters
    static $_cfgpages = []; // config pages definitions
    static $_optcache = []; # for cacheing request results
    static $allRoles = [];
    static protected $_rolesFilter = [];
    static protected $_tracer = null;
    static $tplFolders = []; // table definition (tpl) folders to search in
    static $tplMappings = []; // table definition (tpl) file mappings
    static $filesAllowedModules = [];
    static $start_dept = 0;
    private static $_filecategs = [];
    protected static $_startpage_uri = ''; # can be overwriten for some logged in users (depending on their primary role)
    protected static $SERIALIZE_DECIMALS = 4; # decimal accuracy for serializeData()
    private static $_testing_mode = FALSE;
    static $config_enabled = FALSE;
    static protected $pwd_salt = '#Tyjhgv38-45%HFD2687650987871.Asdtru-7wnFghtuYOps';
    static $gridObj = null; # astedit class instance
    static $IFACE_WIDTH = 0; # Global interface width (enable o limit width of all forms in app)
    static protected $_http_headers = [];
    static protected $_html_body = '';
    static $uploadFolders = []; # list of all folders where users can uplaod files
    static $fileFolders = []; # list of all folders that will be activ in web-admin panel "files"

    // vars for tracking memory using to avoid memory limit exception
    static private $_mem_limit = false;
    static private $_prev_used = false;
    static private $_mem_message = '';
    static $_apicall= false; # if API call beeng handled, holds true or user Token or something related to that call
    protected static $_intCall = FALSE; # Internal call mode - avoid die(HTML) if in API/int call mode
    public static $_failMessage = ''; # Last problem description
    private static $_nologged_starthtml = ''; # HTML code to draw on start page, if user not logged in.
    public static $errMailinfo = '';
    public static function getVersion() {
        return self::VERSION;
    }

    public static function getMailerror() { return self::$errMailinfo; }

    public static function setSerializeDecimals($num) {
        self::$SERIALIZE_DECIMALS = intval($num);
    }

    public static function addUploadFolder($path) {
        if (!in_array($path, self::$uploadFolders)) self::$uploadFolders[] = $path;
    }

    /**
    * Adding folder to the list for "Files" select box.
    *
    * @param mixed $path . If $path starts with "[", it will be treated as "option group" starting, "]" - ending
    * Can be an array of folder items. In this case all paths added inside "optgroup" with group title from #title
    * @param mixed $title title for the folder. If empty, path used to display, Group name for "[","]" cases
    */
    public static function addFileFolder($path, $title='') {
        if (is_array($path)) {
            self::$fileFolders[] = array('[', $title );
            foreach($path as $pItem) {
                if (is_array($pItem)) self::addFileFolder($pItem[0], (empty($pItem[1])?'':$pItem[1]));
                elseif (is_string($pItem)) self::addFileFolder($pItem);
            }
            self::$fileFolders[] = array(']', $title);
        }
        elseif (is_string($path)) {
            self::$fileFolders[] = array( $path, (($title) ? $title : $path) );
        }
    }

    public static function getFileFolders() {
        return self::$fileFolders;
    }
    public static function setTablesPrefix($pref) {
        self::$TABLES_PREFIX = $pref;
    }
    public static function setTestingMode($mode=TRUE) { self::$_testing_mode = $mode; }
    public static function isTestingMode() { return self::$_testing_mode; }

    public static function addFilesModule($module, $udFunction=FALSE) {
        self::$filesAllowedModules[$module] = $udFunction;
    }

    # set mode Internal call
    public static function setIntCallMode($state = TRUE) {
        self::$_intCall = $state;
    }

    public static function isIntCall() { return (self::$_intCall || self::$_apicall); }

    public static function setFailMessage($strg) { self::$_failMessage = $strg; }
    public static function getFailMessage() { return self::$_failMessage; }

    public static function addTableSet($setId, $tables=null) {
        if(is_array($tables)) {
            self::$_tableSets[$setId] = $tables;
            foreach($tables as $no=>$tableid) {
               if (!in_array($tableid, self::$_tableList)) self::$_tableList[$tableid] = $tableid;
            }
        }
        else unset(self::$_tableSets[$setId]);
    }
    public static function setDropdownMenuClass($classname) {
        self::$_menuclass = $classname;
    }
    public static function setHandled() {
        if (self::$_debug) WriteDebugInfo('setting Handled TRUE');
        self::$_prmHandled = TRUE;
    }
    public static function isParamsHandled() {
        return (self::$_prmHandled);
    }

    public static final function setHeader($hdid, $value) {
        # TODO: make "good-cased" headers ID : "content-type" => "Content-Type" ...
        self::$_http_headers[$hdid] = $value;
    }

    public static function sendHeaders() {
        if (!headers_sent()) {
            foreach (self::$_http_headers as $id =>$val) {
                header("$id: $val");
            }
        }
        self::$_http_headers = [];
    }

    # @since 0.9.43
    public static function setAppState($state=0, $err_msg='') {
        self::$_appstate = $state;
        if ($err_msg) self::$_err_message = $err_msg;
    }
    public static function getAppState() {
        return self::$_appstate;
    }
    # @since 1.12 Setter for User-defined app state
    public static function setUserAppState($key, $value) {
        self::$userAppStates[$key] = $value;
    }
    # @since 1.12 - getting current "user app state"
    public static function getUserAppstate($key) {
        return (isset(self::$userAppStates[$key]) ? self::$userAppStates[$key] : NULL);
    }

    public static function getError() {
        return self::$_err_message;
    }

    # returns TRUE if AJAX request detected, FALSE otherwise
    public static final function isAjax() {
        $ajx = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : 'none';
        return (strtoupper($ajx) === 'XMLHTTPREQUEST');
    }
    /**
    * sets flag of "API call"
    *
    * @param mixed $code
    */
    public static function setApiCall($code=true) {
        self::$_apicall = $code;
    }

    /**
    * getting API -state - are we in API call mode ?
    *
    */
    public static function isApiCall() {
        return self::$_apicall;
    }
    /**
    * Sets header for restricting frames
    *
    * @param mixed $mode = 0|'DENY' (deny frames), 1|'SAMEORIGIN', NULL (reset to normal mode, no header "X-Frame-Options")
    */
    public static function restrictFrameOptions($mode = 1) {
        if ($mode === null) unset(self::$_http_headers['X-Frame-Options']);
        else {
            if (is_int($mode)) $mode = ($mode === 0) ? 'DENY': 'SAMEORIGIN';
            self::$_http_headers['X-Frame-Options'] = $mode;
        }
    }

    public static final function appendHtml($string) {

        $argv = func_get_args();
        if (0==func_num_args()) return;
        foreach ($argv as $onearg) {
            self::$_html_body .= $onearg;
        }
    }
    /**
    * analyzes if app works as stanalone or inside CMS infra-structure
    * detected CMS: Bitrix
    */
    public static function isStandalone() {
        if (isset($GLOBALS['APPLICATION']) && is_object($GLOBALS['APPLICATION'])) {
#            WriteDebugInfo('i am under Bitrix: ', $GLOBALS['APPLICATION']);
#        if (isset($_SESSION['BX_SESSION_SIGN']) || isset($_SESSION['BX_USER_ID'])) {
#            WriteDebugInfo('i am under Bitrix: ', $_SESSION);
            return FALSE;
        }
        return TRUE;
    }
    /**
    * Detects and returns CMS system name, if this application built in one supported...
    * Supported: 1C-Bitrix
    * @returns CMS ID ('bitrix') or FALSE
    */
    public static function getParentCms() {
        global $APPLICATION;
        if (!empty($APPLICATION) && is_object($APPLICATION)
            && (isset($_SESSION['BX_SESSION_SIGN']) || isset($_SESSION['BX_USER_ID'])))
            return 'bitrix';
        return FALSE;
    }
    /**
    * Sends all accumulated headers, then all accumulated body strings and exits
    *
    * @param mixed $options
    */
    public static function finalize($options=null) {
        if (empty($_SERVER['REMOTE_ADDR'])) exit;
        self::sendHeaders();
        if ( !self::$_headerDrawn && !self::isAjax() ) self::drawPageHeader(self::$_page_title);
        # TODO: convert charset if nessesary
        if ( !self::isSessionBlocked() ) {
            echo self::$_html_body;
            self::$_html_body = '';
        }
        if (!self::isAjax()) self::drawPageBottom();
        if (self::isStandalone()) exit;
    }
    # getting and setting "start" uri
    public static function getStartPage() { return self::$_startpage_uri; }
    public static function setStartPage($uri) { self::$_startpage_uri = $uri; }
    public static function deferPageHeader($param=1) { self::$_deferredHeader = $param; }
    public static function isDeferredPageHeader() { return self::$_deferredHeader; }

    public static function addTplFolder($folder) {
        $folder = str_replace("\\",'/',$folder);
        if(!in_array($folder,self::$tplFolders)) self::$tplFolders[] = $folder;
    }
    # add exact mapping for one table template file
    public static function addTplMapping($tplId, $basePath) {
        self::$tplMappings[$tplId] = $basePath;
    }
    public static function addTplMappings(array $arMappings) {
        self::$tplMappings = array_merge(self::$tplMappings, $arMappings);
    }
    public static function getRequestParams() { return self::$_p; }
    public static function getRequestParamsCount() { return count(self::$_p); }

    public static function getCached($type, $keyvalue) {
        if(!is_scalar($keyvalue)) {
            $keyvalue = '__wrong_keyvalue__';
        }
        if (is_array(self::$_optcache) && array_key_exists($type, self::$_optcache)) {
            if (isset(self::$_optcache[$type][$keyvalue])) return self::$_optcache[$type][$keyvalue];
        }
        return NULL;
    }
    public static function setCached($type, $keyvalue, $value) {
        if(!isset(self::$_optcache[$type])) self::$_optcache[$type] = [];
        self::$_optcache[$type][$keyvalue] = $value;
    }
    /**
    * Sets "last" error message. Can be message ID (for localization on-the-fly)
    *
    * @param mixed $msg
    */
    public static function setErrorMessage($msg){
        if (isset(self::$i18n[$msg])) self::$err_message = self::$i18n[$msg];
        else self::$err_message = $msg;
    }

    # Register table names for managing in admin panel
    public static function addAppTables($par){
        $lst = is_string($par) ? explode(',', $par) : (is_array($par)?$par:[]);
        foreach($lst as $k=>$v) {
            if (in_array($v, self::$_tableList)) continue;
            if (is_numeric($k)) self::$_tableList[] = $v;
            else self::$_tableList[$k] = $v;
        }
    }

    public static function initFolders($rootAppFolder = FALSE) {

        if (!empty($rootAppFolder)) self::$FOLDER_ROOT = $rootAppFolder;
        elseif ( defined('FOLDER_ROOT') ) {
            self::$FOLDER_ROOT = constant('FOLDER_ROOT');
        }
        if (defined('BASE_URL')) {
            self::$BASEURL = constant('BASE_URL');
        }
    }
    # get full path for "system" subfolder ($autoCreate=TRUE to create folder if not exist)
    public static function getAppFolder($folderType, $autoCreate=FALSE) {
        switch($folderType) {
            case 'templates':
                $ret = self::$FOLDER_ROOT.'templates/';
                break;
            case 'plugins':
                return self::$FOLDER_ROOT.'plugins/';
            default:
                $ret = self::$FOLDER_ROOT . $folderType . (substr($folderType,-1)==='/' ? '':'/');
        }
        if($autoCreate && !is_dir($ret)) {
            $crt = @mkdir($ret,0777, TRUE);
            if($crt) throw Exception("Creating folder ERROR: $ret");
        }
        return $ret;
    }

    public static function init($handleAuthRequests=TRUE) {
        global $auth, $pwdmgr, $as_dbengine, $authdta;
        # self::$_debug = TRUE; // Turn ON for debug logging
        if (defined('FOLDER_PLUGINS') && constant('FOLDER_PLUGINS')) {
            self::$FOLDER_PLUGINS = constant('FOLDER_PLUGINS');
        }
        self::$auth = $auth;
        self::$db = $as_dbengine;
        if (self::$traceMe>2) self::$db->log(10);

        if (is_object(self::$auth))
            self::$auth->init($handleAuthRequests);
        if (defined('IF_LIMIT_WIDTH'))  self::$IFACE_WIDTH = constant('IF_LIMIT_WIDTH');

        self::loadConfigDef();
        self::loadPlugins();
        self::getAppConfig();
        self::loadFinalConfigDef();
        if (!defined('API_CALL')) {
        # Do we need show "old password" field when changing password?
            if (is_object(self::$auth)) {
                $old_pwd = self::getConfigValue('ask_old_pwd',0);
                self::$auth->setOptions( array('enter_old_pwd' => $old_pwd) );
            }
        }
    }

    # make "admin::configuration" page available
    public static function enableConfigPage() {
        self::$config_enabled = TRUE;
    }

    /**
    * recursive function to create hierarhical html menu block
    *
    * @param mixed $data associative array with menu/submenu structure
    * @param mixed $menubody output HTML code
    * @param mixed $id start menu ID
    * @param mixed $crumbs
    * @param string $menustyle : if 'bootstrap',  will make all HTML code iwith bootstrap styles.
    */
    public static function generateMenu($data, &$menubody, $id='',$crumbs=[], $menustyle = '') {
        $rightMnu = ''; # login, logoff, signup - in the right menu!
        $mob = HeaderHelper::$mobile_style;
        $ulcls = $liSubMnuCls = $aSubmnuAttr = $apostfix = $licls = $aSubCls = '';

        $dropdown = !empty($menubody); # gonna make sub-menu in drop-down style!

        if ($menustyle === 'bootstrap') {
            # bootstrap menu styling
            $aclsDisabled = 'class="nav-link disabled"';
            $apostfix = '';
            $licls = ' class="nav-item"';
            $acls = ' class="nav-link"';
            $liSubMnuCls = 'dropdown';
            $aSubCls = 'dropdown-toggle';
            $aSubmnuAttr = ' data-toggle="dropdown"';
            if (!$dropdown) {
                # $ulcls = 'navbar-nav mr-auto';
                $ulcls = 'nav navbar-nav mr-auto';
                if ($id === 'rightmenu') $ulcls = 'navbar-nav navbar-right';
            }
            else { # gonna draw drop-down sub-menu
                $apostfix = '<span class="caret"></span>';
                $ulcls = 'dropdown-menu';
            }
        }
        else {
            $ulcls = self::$_menuclass;
            $licls = '';
            $acls = $aclsDisabled = '';
        }

        if(!$id) $id = 'mainmenu';

        if(empty($menubody)) {
            $cls = !empty(self::$_menuclass) ? ' class="'.self::$_menuclass . '"' : '';
            $menubody = "<ul id=\"$id\" class=\"$ulcls\">\n";
        }
        else {
            # recursive draw submenu
            $menubody .= "\n <ul class=\"$ulcls\">\n"; # another menu?
        }

        foreach($data as $key => $item) {
            if ( !empty($item['delimiter']) ) {
                # WriteDebugInfo("menu delimiter for item[$key] :", $item);
                # $menubody .= '<li style="height:3px">---------------</li>';
                continue;
            }
            # if (!empty($item['onclick'])) WriteDebugInfo('mnu item:', $item);

            $href = isset($item['href']) ? $item['href'] : null;
            $titlestr = isset($item['title']) ? $item['title'] : 'no title here';
            $onclick = '';
            if(!empty($item['onclick'])) {
                if(!is_string($item['onclick'])) {
                    # writeDebugInfo("non-string onclick: ", $item);
                    if(isset($item['onclick']['onclick'])) $onclick = $item['onclick']['onclick'];
                    else $onclick = '';
                }
                else
                    $onclick = $item['onclick'];
            }

            $icon = !empty($item['icon']) ? $item['icon'] : '';
            if(empty($href) && empty($onclick) && empty($item['submenu'])) continue;
            if(empty($href) && !empty($item['submenu']) && HeaderHelper::$mobile_style && $menustyle!=='bootstrap') {
                # create "href" that should build  page with all submenu items
                $full_key = implode('/',$crumbs);
                $full_key .=  ($full_key==='' ? '':'/') . $key; # TODO: add previous submenu id (like breadcrumbs/sub/dir/...)
                $href="./?drawmenu=$full_key"; # Your main dispatch MUST support "drawmenu" call !
            }
            if($onclick) {
                $href='javascript:void(0)';
                $onclick = "onclick=\"$onclick\"";
            }

            $thisCls = empty($item['disabled']) ? $acls : $aclsDisabled; # add disabled sub-class
            $out1 = $out2 = '';
            if (!empty($icon)) {
                if ($menustyle === 'bootstrap') { # add bootstrap icon span
                    $out1 = "<span class=\"glyphicon glyphicon-$icon\">";
                    $out2 = '</span>';
                }
            }

            if(isset($item['submenu']) && is_array($item['submenu'])) {
                # drop-down submenu starts...
                if ($href==='') $href='#';
                $menuItem = "  <li class=\"$liSubMnuCls\"><a class=\"$aSubCls\" $aSubmnuAttr href=\"$href\" >{$out1}$titlestr{$out2}$apostfix</a>";
                $menubody .= $menuItem;
                $cr2 = $crumbs;
                $cr2[] = $key;
                self::generateMenu($item['submenu'],$menubody,'',$cr2,$menustyle);
            }
            else {
                $menuItem = ($href!==null || $onclick) ? "  <li{$licls}><a {$thisCls} href=\"$href\" $onclick>{$out1}$titlestr{$out2}</a>" : "<li{$licls}>{$out2}{$out2}$titlestr";
                $menubody .= $menuItem;
            }

            $menubody .= "  </li>\n";
        }
        $menubody .= "\n</ul>";
    }

    public static function getTableList() { return self::$_tableList; }
    // SOAP features
    public static function setSoapMode($par=TRUE) { self::$inSoap = $par; }
    public static function isSoapMode() { return self::$inSoap; }

    /**
    *  Load Localization strings from i18n/{lang}/*.php
    * @param mixed $lang 2 char language code ("ru" default)
    */
    static final public function setLocalization($lang='ru') {

        self::$lang = $lang;
        $i18_path = self::$FOLDER_ROOT . "i18n/$lang/";

        $strgFiles = glob($i18_path . '*.php');
        foreach ($strgFiles as $onefl) {
            $locstrings = include($onefl);
            if(is_array($locstrings)) {
                self::$i18n = array_merge(self::$i18n, $locstrings);
            }
        }
        self::$i18n['_language_'] = $lang;
    }
    # appends localization strings from array
    public static final function appendLocStrings($strarr) {
        self::$i18n = array_merge(self::$i18n, $strarr);
    }

    public static function getClientLanguage() {
        return isset(self::$i18n['_language_']) ? self::$i18n['_language_'] : GetCurrentLanguage();
    }
    /**
    * Returns localized string for passed id
    * WebApp::$msgSubst can contain "substitute" pairs like ["%stateid%" => 'Denied', ...]
    * @param $strgid string ID
    * @param $default default value if localized nit found
    * @param $arSubst additional associative array ( '{key}'=>'value' ) to replace "placeholder" strings
    */
    public static function getLocalized($strgid, $default='', $arSubst = []) {
        if(!is_string($strgid) && !is_numeric($strgid)) return '';
        $ret = (array_key_exists($strgid,self::$i18n)? self::$i18n[$strgid] : ($default ? $default: '')); # $strgid
        if (is_array(self::$msgSubst) && count(self::$msgSubst))
            $ret = strtr($ret, self::$msgSubst);
        if (is_array($arSubst) && count($arSubst))
            $ret = strtr($ret, $arSubst);
        return $ret;
    }

    # Returns charset of localization strings
    public static function getLocCharset() {
        $ret = isset(self::$i18n['charset']) ? strtoupper(self::$i18n['charset']) : '';
        return $ret;
    }

    # find localized string by id and replace "in place"
    public static function localizeStrings(&$data) {
        foreach(self::$i18n as $lkey=>$lval) {
            if ($lkey === 'charset') continue;
            $data = str_replace("%$lkey%", $lval,$data);
        }
    }

    public static function drawUserBlock($htmlcode, $title_id='', $prepend=FALSE) {
        if (is_array($htmlcode)) $htmlcode = implode('<br>', $htmlcode); # TODO: if too much items, make multi-column block
        $title = ($title_id ? (isset(self::$i18n[$title_id])?self::$i18n[$title_id]:$title_id) : '**');
        if($prepend) array_unshift(self::$_stubcode, "<fieldset class=\"mainpage\">$title $htmlcode </fieldset>");
        else self::$_stubcode[] = "<div class=\"custom-card\" data-label='$title' ><div class='custom-card__container'> $htmlcode  </div></div>";
    }

    public static function drawIndexBlocks() {

        if (!empty(self::$_hooks['drawindexblocks']) ) {
            $result = false;
            foreach(self::$_hooks['drawindexblocks'] as $onefunc) {
                if (is_callable($onefunc))
                    $result = call_user_func($onefunc);
                if ($result) break;
            }
            if ($result) return;
        }
        $html = '';
        if ( self::$workmode < 100 || self::$auth->SupervisorMode() ) {
            $wdt = round(100 / self::$INDEX_COLUMNS, 2);
            if(count(self::$_stubcode)<1) return;
            $curCol = $openRow = 0;
            $html = '<div class="row">';
            foreach(self::$_stubcode as $item) {
                // if(!$openRow) { echo '<tr>'; $openRow = 1; $curCol = 0; }
                $html .= "<div class='col-12 col-md-6 py-2'>$item</div>";
                // if(++$curCol >= self::$INDEX_COLUMNS) { $html .= '</tr>'; $openRow = 0; }
            }
            // if($openRow) $html .= '</tr>';
            $html .= '</div>';
        }
        if (self::$workmode > 0) $html .= self::$workmode_reason;
        self::appendHtml($html);
    }
    # insert submenu before specified submenu ($id_defore)
    public static function insertSubmenu($menuid, $title, $id_before='') {
        if(empty($id_before)) $id_before = 'mnu_docs';
        if(isset(self::$_mainmenu[$menuid])) return FALSE;
        $titlestr = isset(self::$i18n[$title]) ? self::$i18n[$title] : $title;
        $a_keys = array_keys(self::$_mainmenu);
        $no = array_search($id_before, $a_keys);
        if($no===FALSE) return FALSE;
        $newelem = array($menuid => array('title'=>$titlestr, 'submenu'=>[]));
        self::$_mainmenu = array_merge( array_slice(self::$_mainmenu,0,$no), $newelem,  array_slice(self::$_mainmenu,$no));
        return $no;
    }
    /**
    * Adding item in existing submenu
    *
    * @param mixed $menuid menu ID or nested ID list separated by "/" : "mainmenu/submenu1/submenu2"
    * @param mixed $itemid new item ID OR array items list, if You want to add multiple menu items at once
    * @param mixed $title item title
    * @param mixed $href value for &lt;a href=...&gt; tag
    * @param mixed $onclick value for "onclick" tag
    * @param mixed $submenu array of child items, if we adding submenu
    */
    public static function addSubmenuItem($menuid, $itemid, $title='', $href='', $onclick='', $submenu=FALSE) {

        if (is_array($itemid)) {
            foreach($itemid as $subid => $subitem) {
                self::addSubmenuItem($menuid,$subid,
                   (isset($subitem['title'])?$subitem['title']:'no-title')
                  ,(isset($subitem['href'])?$subitem['href']:'')
                  ,(isset($subitem['onclick'])?$subitem['onclick']:'')
                  ,(isset($subitem['submenu'])?$subitem['submenu']:FALSE)
                );
            }
            return;
        }
        $idlist = explode('/',$menuid);
        # $deb = ($idlist[0] === 'mnu_utils' && !empty($idlist[1]) && $idlist[1] === 'mnu_supervisor');
        if(!isset(self::$_mainmenu[$idlist[0]])) return FALSE;
        if(!isset(self::$_mainmenu[$idlist[0]]['submenu'])) {
            $findMnuId = 'mnu-' .$idlist[0];
            self::$_mainmenu[$idlist[0]]['submenu'] = array('title'=>self::getLocalized('mnu-'.$findMnuId, $findMnuId));
        }
        $objref =& self::$_mainmenu[$idlist[0]]['submenu'];

        # recursive search/create needed submenu object in array
        if (count($idlist)>1) for ($inest=1; $inest <count($idlist); $inest++) {
            $subid = $idlist[$inest];
            if (empty($subid)) {
                # if ($deb) WriteDebugInfo("$menuid => $itemid : exiting with empty subid, inest= $inest");
                return; # wrong menuid filled !
            }
            if (!isset($objref[$subid])) {
                $objref[$subid] = array('title'=>  self::getLocalized($subid,$subid));
                # WriteDebugInfo('try to find string '.$subid);
            }
            if (!isset($objref[$subid]['submenu'])) $objref[$subid]['submenu'] = [];
            $objref =& $objref[$subid]['submenu'];
        }

        if(is_array($title)) {
            $objref[$itemid] = $title; # ready submenu array passed, just use it!
        }
        else {

            if (!is_array($objref)) {
                $objref = []; # PHP 7.* requires it!
            }

            if (isset(self::$i18n[$title])) $title = self::$i18n[$title]; # string ID passed
            $objref[$itemid] = [];
            $objref[$itemid]['title'] = $title;

            if(!empty($href)) $objref[$itemid]['href'] = $href;
            if(!empty($onclick)) {
                $objref[$itemid]['href'] = 'javasript:void()';
                $objref[$itemid]['onclick'] = $onclick;
            }
            if(is_array($submenu) && count($submenu)>0) {
                if(!isset($objref[$itemid]['submenu'])) {
                    $objref[$itemid]['submenu'] = $submenu;
                }
                else { # there is already some items in submenu
                    $objref[$itemid]['submenu'] = array_merge($objref[$itemid]['submenu'], $submenu);
                }
            }
        }
        # if($itemid === 'mnu_prgconst') writeDebugInfo("mnu_prgconst, finally: ", $objref);
        return count($objref[$itemid]);
    }

    public static function addSubmenuItems($menuid, $items) {

        if(!isset(self::$_mainmenu[$menuid])) return FALSE;
        if(!isset(self::$_mainmenu[$menuid]['submenu'])) self::$_mainmenu[$menuid]['submenu'] = [];
        foreach($items as $id=>$item) {
            self::$_mainmenu[$menuid]['submenu'][$id] = $item;
        }
    }

    # Adding "consumer" for department list, to prevent dept deletions
    public static function addDeptListUser($moduleid, $ctable, $cfield, $message) {
        foreach(self::$deptlist_users as $item) { // avoid duplicates
            if ($item['module'] === $moduleid && $item['table'] === $ctable && $item['field'] === $cfield ) return;
        }
        self::$deptlist_users[] = array('module'=>$moduleid, 'table'=>$ctable, 'field'=>$cfield, 'message'=>$message);
    }
    # returns "ready-to-use" array of foreign-key constraints, in format Astedit::CHILDTABLES|userFunc
    public static function getDeptListUsers() {
        $ret = [];
        foreach (self::$deptlist_users as $no => $item) {
            $ret[] = array($item['table'], 'dept_id', $item['field'], $item['message']);
        }
        return $ret;
    }

    /**
    * Loads meta-data about configuraton variables for the application - their names, edit type, caption etc.
    * @param $xmlname if passed, adds config definition from this file (for plugins configuration)
    */
    public static function loadConfigDef($xmlname='') {
        if (!empty($xmlname)) {
            $files = [$xmlname];
            $finals = [];
        }
        else {
            $files = $finals = [];
            $mask = self::getAppFolder('app/defvars/') . 'cfg*.xml';

            foreach(glob($mask) as $flname) {
                $basenm = basename($flname);
                # if(substr($basenm,0,8) === 'cfg.end_') $finals[] = $flname;
                $files[] = $flname;
            }
            # writeDebugInfo("final XML: ", $finals);
        }
        foreach($files as $oneXml) { # <3>
            self::readCfgDef($oneXml);
        }   # <3>
        return (count(self::$_defcfg));
    }
    # load all "final" config defs
    public static function loadFinalConfigDef() {
        $mask = self::getAppFolder('app/defvars/') . 'end_*.xml';
        $files = [];
        foreach(glob($mask) as $flname) {
            $files[] = $flname;
        }

        if(is_array($files) && count($files)) foreach($files as $oneXml) { # <3>
            self::readCfgDef($oneXml);
        }   # <3>
    }
    # parse one cfg def file
    private static function readCfgDef($cfgXmlFile) {
        if(is_file($cfgXmlFile)) {
            $xml = @simplexml_load_file($cfgXmlFile);
            if(!($xml)) {
                self::logErr('loadConfigDef: Failed to read '.$cfgXmlFile);
                # die('Reading config XML file error, call developers!');
                return FALSE;
            }
            self::$cfgFiles[] = $cfgXmlFile;
            foreach($xml->children() as $pgkey=>$pageitem) {

                if($pgkey!='page') continue;
                $pageid = isset($pageitem['id']) ? (string)$pageitem['id'] : '';
                $enablefunc = isset($pageitem['enabled']) ? ((string)$pageitem['enabled']) : '';
                $pageTitle = isset($pageitem['title']) ? self::toClientCset((string)$pageitem['title']) : '';
                $longTitle = isset($pageitem['longtitle']) ? self::toClientCset((string)$pageitem['longtitle']) : '';
                $footer = isset($pageitem->footer) ? self::toClientCset((string)$pageitem->footer) : '';

                if(!$pageid) continue;
                if (isset(self::$_defcfg[$pageid])) {
                    $firstXml = isset(self::$_defcfg[$pageid]['xmlfile']) ? self::$_defcfg[$pageid]['xmlfile'] : '';
                    self::logErr("Webapp: Duplicated configuration page id: $pageid in file: $cfgXmlFile / $firstXml");
                    continue;
                }
                $pagelog = isset($pageitem['log']) ? (int)$pageitem['log'] : 0; # logging all changes of parameters
                $fields = [];
                # if($pageid === 'config_zdebug') writeDebugInfo(" fields: ", $pageitem->fields);
                foreach($pageitem->fields->children() as $fkey=>$fdef) {
                    $fname = isset($fdef['name']) ? (string)$fdef['name'] : '';
                    if(!$fname) continue;
                    $ftype = isset($fdef['type']) ? (string)$fdef['type'] : 'text';
                    $arrButt = '';
                    if($ftype === 'toolbar') {
                        # if($pageid === 'config_zdebug')
                        # writeDebugInfo("toolbar def:", $fdef);
                        $arrButt = []; $btnId = 0;
                        # if(!empty($fdef->button))
                        foreach($fdef->children() as $key=>$child) {
                            # writeDebugInfo("$key in toolbar: ", $child);
                            if($key==='button') {
                                $btnId++;
                                $arrButt[] = [
                                    'type' => $key,
                                    'name' => ((string)$child['name'] ?? "$fname_{$btnId}"),
                                    'label' => ((string)$child['label'] ?? 'new btn'),
                                    'onclick' => ((string)$child['onclick'] ?? ''),
                                    'title' => ((string)$child['title'] ?? ''),
                                ];
                            }
                        }
                    }

                    $width = isset($fdef['width']) ? (string)$fdef['width'] : 0;
                    $height = isset($fdef['height']) ? (string)$fdef['height'] : 0;
                    $default = isset($fdef['default']) ? (string)$fdef['default'] : '';
                    $onchange = isset($fdef['onchange']) ? (string)$fdef['onchange'] : '';
                    $onclick = isset($fdef['onclick']) ? (string)$fdef['onclick'] : '';
                    $attribs = isset($fdef['attribs']) ? (string)$fdef['attribs'] : '';
                    $label = isset($fdef['label']) ? self::toClientCset((string)$fdef['label']) : $fname;
                    $title = isset($fdef['title']) ? self::toClientCset((string)$fdef['title']) : '';
                    $logging = isset($fdef['log']) ? (int)$fdef['log'] : 0; # log "change value" event
                    $groupid = isset($fdef['groupid']) ? (string)$fdef['groupid'] : '';
                    $rowclass = isset($fdef['rowclass']) ? (string)$fdef['rowclass'] : '';
                    $onclick = isset($fdef['onclick']) ? (string)$fdef['onclick'] : '';
                    $checkevent = isset($fdef['checkevent']) ? (string)$fdef['checkevent'] : '';
                    $options = isset($fdef['options']) ? self::toClientCset((string)$fdef['options']) : '';
                    $fields[$fname] = array('name'=>$fname, 'type'=>$ftype,'label'=>$label,'title'=>$title
                       ,'width'=>$width, 'height'=> $height, 'onchange'=>$onchange,'onclick'=>$onclick
                       ,'options'=>$options,'attribs'=>$attribs,'groupid' => $groupid
                       ,'default'=>$default, 'log'=>$logging, 'onclick' => $onclick, 'rowclass'=>$rowclass
                       ,'checkevent' => $checkevent
                       ,'children' => $arrButt
                    );
                }

                if(isset(self::$_defcfg[$pageid])) {
                     self::$_defcfg[$pageid]['fields'] = array_merge(self::$_defcfg[$pageid]['fields'],
                            ['title'=> $pageTitle, 'longtitle'=>$longTitle, 'fields'=>$fields, 'enabled'=>$enablefunc,]
                     );
                }
                else {
                    self::$_defcfg[$pageid] = ['title'=> $pageTitle,'longtitle'=>$longTitle, 'fields'=>$fields
                      , 'enabled'=>$enablefunc, 'log'=>$logging,
                      'xmlfile' => $cfgXmlFile
                    ];
                }
                if(!empty($footer))
                    self::$_defcfg[$pageid]['footer'] = $footer;
                # save <script> section with js code:
                if(!empty($xml->script)) self::$_defcfg[$pageid]['script'] = $xml->script;
            }

        }
        return TRUE;
    }
    public static function getFolderCfg() {
        return (self::$FOLDER_ROOT . self::FOLDER_CFG);
    }

    public static function getAppConfig() {
        self::initIniEngine();
        self::$maintenanceMode = self::$ini->getValue('system','maintenancemode');
        if (self::$maintenanceMode) {
            self::$workmode = 100;
            self::$workmode_reason = self::getLocalized('text_maintenance_mode');
        }
        //, get_class_methods());
        foreach(self::$ini->_iniParsedArray as $pageid=>$page) {
            # if(!isset(self::$_cfg[$pageid])) self::$_cfg[$pageid] = [];
            # if(!is_array($page))
            self::$_cfg = array_merge(self::$_cfg, $page); # TODO: split array with "plugin" names (avoid overwriting vars)
        }

    }

    public static function getConfigValue($varname,$default=NULL) {
        if (!self::$_cfg) self::getAppConfig();
        if (!isset(self::$_cfg[$varname])) return $default;
        if (self::$_cfg[$varname] === '' && $default !==NULL) return $default;
        return self::$_cfg[$varname];
    }
    private static function initIniEngine() {
        if(self::$ini === NULL) {
            require_once('class.iniparser.php');
            $path = self::getAppFolder('cfg/');
            if($path!='' && !is_dir($path)) mkdir($path,0777,TRUE);
            self::$ini = new iniParser($path . self::INIFILENAME);
        }
    }
    /**
    * Saves new configuration values
    *
    * @param mixed $newvalues associative array ['key'] => 'newvalue'
    */
    public static function saveAppConfig($newvalues) {
        self::initIniEngine();
        foreach(self::$_defcfg as $pageid=>$page) {
            $savepage = (!empty($page['enabled']) && function_exists($page['enabled'])) ? call_user_func($page['enabled']) : TRUE;
            if(!$savepage) continue;
            $page_log = !empty($page['log']);
            # don't save params if this page not allowed to user
            foreach($page['fields'] as $no=>$fdef) {
                $fid = $fdef['name'];
                if ( in_array($fdef['type'], ['header','button']) ) continue;
                $nval = isset($newvalues[$fid]) ? $newvalues[$fid] : '';
                if ( is_array($nval) ) {
                    $nval = implode(',', $nval);
                }
                $realpage = ($fid==='maintenancemode') ? 'system' : $pageid;
                $oldval = self::getConfigValue($fid);
                if ($oldval !== $nval) {
                    if( self::isMacroRecording() ) {
                        self::recordMacro(['cmd'=>'saveConfig',
                           'page'=>$realpage,
                           'paramName'=>$fid,
                           'paramVal'=>$nval]
                        );
                        continue;
                    }
                    if (!empty($fdef['log']) || $page_log) {
                        self::logEvent('CFG.CHANGE',"Cfg change: $fid , [$oldval] to [$nval])");
                    }
                }

                self::$ini->set($realpage, $fid, $nval);
            }
        }

        self::$ini->save();
        self::getAppConfig(); // read new values back to appEnv object

        if(self::isStandalone()) {
            if(!empty( $newvalues['sys_psw_histlength']) or !empty( $newvalues['sys_psw_maxdays'])) {
                # если в настройках включены лимиты жизни пароля или защита от повторов (история), надо создать объекты:
                if(self::$_pwdmgr) {
                    self::$_pwdmgr->SetStrengthReqs(array(
                        'histlength' => self::getConfigValue('sys_psw_histlength')
                       ,'maxdays'    => self::getConfigValue('sys_psw_maxdays')
                    ));
                    $crt = (self::$_pwdmgr) ? self::$_pwdmgr->createSystemObjects() : '$_pwdmgr - no obj';
                }
            }
        }
    }
    # Saves in ini one application value. @since 1.19
    public static function setAppParam($paramName, $paramValue) {
        self::initIniEngine();
        self::$ini->set('app_parameters', $paramName, $paramValue);
        self::$ini->save();
    }
    # Restores saved application value from main ini. @since 1.19
    public static function getAppParam($paramName) {
        self::initIniEngine();
        $ret = self::$ini->getValue('app_parameters', $paramName);
        return $ret;
    }
    /**
    * Start / or Stop "maintenance" mode
    *
    * @param mixed $mode TRUE|number_of_minutes - blocks all functions for ordinary users, FALSE: back to normal mode
    * @since 1.05
    */
    public static function setMaintenanceMode($mode = TRUE) {
        self::initIniEngine();
        if(empty($mode)) $newval = 0;
        elseif ($mode === TRUE) $newval = '1';
        else $newval = strtotime("+$mode minutes");
        $ini->set('system','maintenancemode',$newval);
        $ini->save();
        return $mode;
    }
    /**
    * Checks if session is in "blocked" state (due to password expired or other reasons
    * $returns TRUE if something blocks user session
    */
    public static function isSessionBlocked() {
        if (self::isStandalone() && self::$auth->isPasswordExpired()) return TRUE;
        return FALSE;
        # TODO: other conditions to check...
    }

    # @since 1.22 returns TRUE if maxro recording mode is active
    public static function isMacroRecording() {
        return (isset(self::$_SESSION['macro_rec']));
    }
    public static function startMacroRecording() {
        self::$_SESSION['macro_rec'] = [];
    }

    # @since 1.22 stop recording macro, return recorded macros list
    public static function stopMacroRecording() {
        $arRet = self::$_SESSION['macro_rec'] ?? [];
        unset(self::$_SESSION['macro_rec']);
        return $arRet;
    }

    # @since 1.22 get macros collected so far
    public static function getRecordedMacros() {
        return (self::$_SESSION['macro_rec'] ?? NULL);
    }
    public static function recordMacro($arData) {
        # TODO: save into SESSION recorder macro
        if(!isset($_SESSION['macro_rec']))
            $_SESSION['macro_rec'] = [];
        $_SESSION['macro_rec'][] = $arData;
    }
    public static function logErr() {
        if (is_callable('writeDebugInfo')) call_user_func_array('writeDebugInfo',func_get_args());
    }
    /**
    * Seeks for plugin(s) that has a method named as passed action, and calls them.
    *
    * @param mixed $opertype
    */
    public static function runPlugins($opertype, $params=null, $pluginName='') {
        $result = [];
        if($pluginName && !empty(self::$_plugins[$pluginName])) {
            # если передано точное имя класса плагина, выполняю только его методы:
            if (method_exists(self::$_plugins[$pluginName], 'dispatch'))
                $result[] = self::$_plugins[$pluginName] -> dispatch($params);
            elseif (!empty($opertype) && method_exists(self::$_plugins[$pluginName], $opertype))
                $result[] = self::$_plugins[$pluginName] -> $opertype($params);
            else {
                $err = "Wrong plugin method call:$pluginName/$opertype";
                WriteDebugInfo('Error call:', $err);
                exit($err);
            }
        }
        else foreach(self::$_plugins as $plgid=>$plgObj) {
            # if(!empty($pluginName) && strtolower($pluginName) != strtolower(get_class($plgObj))) continue;
            if(class_exists('PM') && isset(PM::$deadProducts) && in_array($pluginName, PM::$deadProducts)) continue; # устаревшие модули пропускаю!
            if(method_exists($plgObj,$opertype)) {
                if(self::$_debug>1) WriteDebugInfo("Calling $opertype in object of class ".get_class($plgObj));
                $oneresult = $plgObj->$opertype($params);
                $result[] = $oneresult;
                # if($result!==FALSE && $result!==null) break;
            }
            else {
                if(self::$_debug>1) WriteDebugInfo("$opertype not found in plugin ".get_class($plgObj));
            }
        }
        return $result;
    }

    public static function registerPlugin($plugin, $pluginId=FALSE) {
        $classFname = '';
        if(is_string($plugin)) {
            $plgname = ($pluginId ? $pluginId : strtolower($plugin));
            if(isset(self::$_plugins[$plgname])) {
                # if(self::$_debug) {
                $reflector = new ReflectionClass(self::$_plugins[$plgname]);
                $classFname = $reflector->getFileName();
                WriteDebugInfo("Class for $plgname already defined in $classFname");
                # }
                return;
            }
            if(class_exists($plugin)) self::$_plugins[$plgname] = new $plugin();
        }
        elseif(is_object($plugin)) {
            $plgname = ($pluginId ? $pluginId : strtolower(get_class($plugin)));
            if(isset(self::$_plugins[$plgname])) {
                # if(self::$_debug) {
                $reflector = new ReflectionClass(self::$_plugins[$plgname]);
                $classFname = $reflector->getFileName();
                WriteDebugInfo("Class for $plgname already defined in $classFname");
                # }
                return;
            }
            self::$_plugins[$plgname] = $plugin;
        }
    }
    /**
    * Returns ID (class name) of called plugin, if plg=nnnnn exist in parameters
    * @return string
    */
    public static function currentPlugin() {
        if (!empty(self::$_plg_name)) {
            return self::$_plg_name;
        }
        if (count(self::$_p)<1) self::$_p = decodePostData(1);
        $plgid = (isset(self::$_p['plg'])) ? self::$_p['plg'] : (isset(self::$_p['plugin'])?self::$_p['plugin']:'');
        return $plgid;
    }
    public static function subPlugin() {
        return self::$_subplg;
    }
    public static function getAction() {
        return self::$_action;
    }

    /**
    * dispatcher must be called in index.php
    *
    */
    public static function dispatch() {

        $subpars = $pagefile = $pagename = '';

        if (self::isSessionBlocked()) self::finalize();

        $prm = self::$_p = DecodePostData(1,1);
        if (!is_array($prm) || count($prm)<1) { # try to parse "overrided" startpage
            $starturi = self::getStartPage();
            if( $starturi ) {
                $parsed = parse_url($starturi);
                if(!empty($parsed['query'])) {
                    parse_str($parsed['query'], $prm2);
                }
            }
        }
        elseif (self::$sanitize_input) {
            self::sanitizeUserInput();
        }

        if (self::$_debug and count(self::$_p)>0) WriteDebugInfo('request :', $_SERVER['REQUEST_URI'],' params:', self::$_p);

        # test=xxxxxxxx [&param1=...]- execute tests/xxxxxxxx.php module and exit
        if(!self::isProdEnv() && isset(self::$_p['test'])) { # test units must be called

            $test_module = self::FOLDER_TESTS . trim(self::$_p['test']) . '.php';
            if (is_file($test_module)) {
                include($test_module);
                exit;
            }
            if (self::$_debug) WriteDebugInfo('test skipped (no such file):', $test_module);
            self::echoError("Test module not found: ".$test_module);
            return;
        }


        $pgtitle = ''; $submnu = [];
        self::$_action = $action = isset(self::$_p['action']) ? self::$_p['action'] : '';
        if(!empty($prm['drawmenu'])) {

            $menuid = explode('/',$prm['drawmenu']);
            if (self::$_debug) WriteDebugInfo('dispatch: drawing menu, ', $prm['drawmenu']);
            $fullid = implode('_',$menuid);
            if (count($menuid)===1) {
                $pgtitle = isset(self::$_mainmenu[$menuid[0]]['title']) ? self::$_mainmenu[$menuid[0]]['title'] : '';
                $submnu  = isset(self::$_mainmenu[$menuid[0]]['submenu']) ? self::$_mainmenu[$menuid[0]]['submenu'] : [];
            }
            elseif (count($menuid)===2) {
                $pgtitle = isset(self::$_mainmenu[$menuid[0]]['submenu'][$menuid[1]]['title'])?
                    self::$_mainmenu[$menuid[0]]['submenu'][$menuid[1]]['title'] : '';
                $submnu = self::$_mainmenu[$menuid[0]]['submenu'][$menuid[1]]['submenu'];
            }
            elseif (count($menuid)===3) {
                $submnu = self::$_mainmenu[$menuid[0]][$menuid[1]][$menuid[2]];
            }
            self::setPageTitle($pgtitle);
            $html = "<div class='custom-card' id='$fullid' style='text-align:left;margin-top:20px;' data-label='$pgtitle'><div class='custom-card__container'>";
            foreach($submnu as $mid=>$mitem) {
                if (!is_array($mitem)) continue;
                $mtitle = isset($mitem['title']) ? $mitem['title'] : '';
                if (!$mtitle) {
                    continue;
                }
                $onclick = empty($mitem['onclick'])?'': " onclick=\"$mitem[onclick]\"";
                $href = empty($mitem['href'])?'': " href=\"$mitem[href]\"";
                if($onclick==='' && $href==='' && !empty($mitem['submenu'])) $href = " href=\"./?drawmenu=".implode('/',$menuid)."/$mid\"";
                $html .= "<br><a{$href}{$onclick}>$mtitle</a><br>";
            }
            $html .= '<br></div></div>';
            self::appendHtml($html);
            self::setHandled();
            self::finalize();
        }
        $loopNo = 0;
        foreach($prm as $key=>$val) {
            $loopNo++;
            if($key==='p' or $key==='m') {
                $arr = explode('/',$val);
                if($key=='m' || $key=='p') {
                    if(is_file($val)) {
                        # passed p=folder/filename.php
                        $pagefile = $pagename = $val;
                    }
                    elseif(is_file($val.'.php')) {
                        # passed p=folder/filename
                        $pagefile = $pagename = $val.'.php';
                    }
                    else {
                        $pagename = array_shift($arr);
                        $pagefile =  $pagename . '.php';
                        self::$paramsChain = $arr;
                    }
                }
            }
            elseif($key==='plg_name' or $key==='plg') {
                $pl_arr = explode('/',strtolower($val));
                self::$_plg_name = $plg_name = $pl_arr[0];
                self::$_subplg = (count($pl_arr)>1) ? $pl_arr[1] : '';
                $result = '';
                if (self::$_debug) WriteDebugInfo("checking request [$plg_name] against plugin:[$val]");

                if(isset(self::$_plugins[$plg_name])) {
                    if (self::$_debug) WriteDebugInfo('plugin: '.$plg_name.', action:'.self::$_action);
                    if (method_exists(self::$_plugins[$plg_name],'dispatch')) {
                        self::setHandled();
                        $result = self::$_plugins[$plg_name]->dispatch(self::$_p);
                        if (self::$_debug) WriteDebugInfo("calling dispatch() from plugin $plg_name result:", $result);
                    }
                    elseif (method_exists(self::$_plugins[$plg_name],$action)) {
                        $result = self::$_plugins[$plg_name]->$action(self::$_p);
                        if (self::$_debug) WriteDebugInfo("$action() from $val result: [$result]");
                        self::setHandled();
                    }
                    if(self::isAjax() && ($result)) {
                        exit ( EncodeResponseData($result) );
                    }
                    if(self::$_debug) WriteDebugInfo("calling dispatch() from plugin $plg_name returned control, BAD! ");
                    if (self::$_prmHandled) break;
                }
            }
        }
        if (self::$_debug) WriteDebugInfo('plugins did not handle request...');
        # if(empty($pagefile)) $pagefile = 'pagedefault.php';
        if($pagename) {

            self::setHandled();
            if(self::$_debug) WriteDebugInfo("dispatch generate page [$pagename], src params:", array_merge($_GET,$_POST));
            if(is_callable($pagename)) {
                # appEnv::drawPageHeader($pagename);
                if(self::$_debug) WriteDebugInfo('dispatch:trying existing function ', $pagename);
                call_user_func($pagename);
                # appEnv::drawPageBottom();
                if (self::isStandalone()) exit;

            }
            # elseif (method_exists('AppEnv', $pagename)) {
            elseif (method_exists(static::class, $pagename)) {
                if ( self::$_debug ) WriteDebugInfo('dispatch:child method ', $pagename);
                self::$pagename();
                if (self::isStandalone()) exit;
            }
            elseif(method_exists('webapp',$pagename)) {
                if ( self::$_debug ) WriteDebugInfo('dispatch:webapp method ', $pagename);
                WebApp::$pagename();
                if (self::isStandalone()) exit;
            }
            elseif(file_exists($pagefile) || file_exists(self::getAppFolder('app/') . $pagefile)) {
                $realfile = file_exists($pagefile) ? $pagefile : (self::getAppFolder('app/').$pagefile);

                if(self::$_debug) WriteDebugInfo('dispatch:trying existing pagefile '.$realfile);
                $pageObject = include($realfile);
                if(!empty($prm['action']) && is_object($pageObject) && method_exists($pageObject, $prm['action'])) {
                    $pAction = $prm['action'];
                    if(self::$_debug) writeDebugInfo("try to call method $pAction in $pagefile");
                    $result = $pageObject->$pAction();
                    return $result;
                }
                if(is_object($pageObject) && method_exists($pageObject,'getpagetitle')) {
                    $pgTitle = $pageObject->getPageTitle();
                    self::drawPageHeader($pgTitle);
                    $pgBody = $pageObject->drawPageBody();
                    if(is_string($pgBody)) echo $pgBody;
                    if(!empty(self::$err_message)) echo '<p style="text-align:center;font:bold #e10 verdana;">'.self::$err_message.'</p>';
                    self::drawPageBottom();
                    if (self::isStandalone()) exit;
                    self::setHandled();
                    return;
                }
            }
            else { # seek function in all registered plugins

                if (self::$_debug) WriteDebugInfo('dispatch last resort...');
                # $plgname = isset($_POST['plg_name'])? $_POST['plg_name'] : '';
                if(self::$_debug) WriteDebugInfo('dispatch:trying plugins for '.$pagename);
                $result = self::runPlugins($pagename);
                if($result) {
                    self::setHandled();
                    return;
                }
            }
            if (self::$_debug) WriteDebugInfo('none executed for ',$pagename);
            self::$err_message = self::getLocalized('err-wrong-page');
        }
    }
    // delete "prohobited" chars from all user input passed from client
    public static function sanitizeUserInput() {
        foreach(self::$_p as $key => &$val) {
            $val = str_replace(array('\x','\d'), '', $val);
            // TODO: collect all html codes for "bad" chars
        }
    }
    public static function getErrorMessage() { return self::$err_message; }

    public static function getFileCategories() {
        if (function_exists('getFileCategories')) return getFileCategories();
        return self::$_filecategs;
    }
    # returns all chained parameters as array or single element, if correct non-negative offset passed
    public static function getParamsChain($offset=-1) {
        if($offset<0) return self::$paramsChain;
        elseif($offset<count(self::$paramsChain)) return self::$paramsChain[$offset];
        return FALSE;
    }
    public static function registerSystemError($module,$errtype='') {

        Cdebs::DebugSetOutput('./_app_errors.log');
        WriteDebugInfo('System Error in '.$module . ($errtype? ", type: $errtype" : ''));
        if($errtype=='DB') WriteDebugInfo('last query:'. self::$db->GetLastQuery() . ', SQL error: '.self::$db->sql_error());
        Cdebs::DebugSetOutput('');
    }

    public static function echoError($msg_id=FALSE) {
        if (!$msg_id) $msg_id = self::$err_message;
        $txt = (isset(self::$i18n[$msg_id])) ? self::$i18n[$msg_id] : $msg_id;
        if($txt ==='') $txt = $msg_id;
        if (count(self::$msgSubst)) $txt = strtr($txt, self::$msgSubst);
        self::$_appstate = 101; # fatal error state
        self::$_err_message = $txt; # fatal error state

        if (empty($_SERVER['REMOTE_ADDR']))
            exit($txt);

        if(self::isApiCall()) {
            # writeDebugInfo("Error $txt, trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
            return ['result'=>'ERROR', 'message'=>$txt];
        }

        if($ajax = self::isAjax()) {
            # if(class_exists('ajaxResponse')) exit('1' . AjaxResponse::showError($txt));
            exit($txt);
        }

        self::drawPageHeader(self::$_page_title ? self::$_page_title : self::getLocalized('error'));
        echo "<br><div class='msg_error' style='width:70%'><br>$txt<br><br></div>";
        self::finalize();
    }

    public static function fileExtension($filename) {
        $pinfo = pathinfo($filename);
        return strtolower($pinfo['extension']);
    }

    public static function GetMimeType($filename) {
        if (function_exists('finfo_open') && is_file($filename)) { # works in PHP 5.3.0+
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = @finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mime;
        }
        # if PHP 5.2.x - file extension analizing
        $fext = self::fileExtension($filename);
        switch($fext) {
          case 'jpg': case 'jpeg': case 'jpe': return 'image/jpeg';
          case 'bmp': case 'png': case 'gif': return "image/$fext";
          case 'tif': case 'tiff': return 'image/tiff';
          case 'swf': return 'application/x-shockwave-flash';
          case 'wav': case 'mp3': case 'ogg': return "audio/$fext";
          case 'rtf': case 'pdf': case 'zip' : return 'application/'.$fext;
          case 'xls': case 'xlsx': return 'application/vnd.ms-excel';
          case 'pps': case 'ppt': case 'ppz': return 'application/vnd.ms-powerpoint';
          case 'doc': case 'docx': case 'dot': case 'wrd': return 'application/vnd.ms-word';
          case 'ods': return 'application/vnd.oasis.opendocument.spreadsheet';
          case 'odt': return 'application/vnd.oasis.opendocument.text';
          case 'tgz': case 'gtar': return 'application/x-gtar';
          case 'gz': return 'application/x-gzip';
          case 'rar': return 'application/x-rar-compressed';
          case 'ico': return 'image/x-icon';
          case 'txt': return 'text/plain';
          case 'htm': case 'html' :return 'text/html';
          case 'xml': case 'xsl': return 'text/xml';
          case 'mpg': case 'mpe': case 'mpeg': return 'video/mpeg';
          case 'qt': case 'mov': return 'video/quicktime';
          case 'avi': return 'video/x-ms-video';
          case 'wm': case 'wmv': case 'wmx': return "video/x-ms-$fext";
        }
        return 'octet-stream';
    }

    #
    public static function minPageHeader($pagetitle) {
        if(self::$_debug) WriteDebugInfo("drawing minPageHeader:pageTitle=$pagetitle");
        $ret = <<< EOHTM
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head><title>$pagetitle</title>
<meta http-equiv="Content-Type" Content="text/html; charset=windows-1251">
<link rel="stylesheet" href="styles.css" type="text/css" />
</head>
EOHTM;
        echo $ret;
    }
    # echoing common page header
    public static function drawPageHeader($pagetitle='', $showTitle=1) {
        if(self::$_headerDrawn or self::isAjax()) return;
        if (self::$_debug) {
            $runinfo = debug_backtrace(NULL,3);
            $pref = isset($runinfo[0]) ? ($runinfo[0]['file'].'['.$runinfo[0]['line']).']' : '';
        }

        if(!empty($_SESSION['instant_msg'])) addJsCode('showInstantMessage();','ready');

        $finalTitle = self::getLocalized($pagetitle);
        if (!$finalTitle) $finalTitle = $pagetitle;

        if ('bitrix' === self::getParentCms()) {
            global $APPLICATION;
            $APPLICATION->SetTitle($finalTitle);
        }
        else {
            HeaderHelper::AddReplacer('<!-- APPHEADER -->',self::getLocalized('apptitle'));
            $title = self::getLocalized($pagetitle);
            if(!$title) $title = empty($pagetitle) ? self::getLocalized('starttitle') : $finalTitle;

            $GLOBALS['metatitle'] = $title;

            self::sendHeaders(); // if some headers pre-set, send them before header!
        }

        if (!self::$_headerDrawn) {
            HTML_Header($finalTitle,$showTitle);
            self::$_headerDrawn = TRUE;
            if ('bitrix' !== self::getParentCms()) {
                if($showTitle>-2) self::$auth->Auth_InlineForm(200,TRUE);
            }
            elseif(method_exists(self::$auth,'authJsCodeCms')) {
                // add "minmalistic js code (reset password by admin)
                self::$auth->authJsCodeCms();
            }

            if (!self::isParamsHandled()) self::runPlugins('stub');
        }
        return TRUE;
    }
    static final function setPageTitle($title) { self::$_page_title = $title; }
    # echoing common page bottom (final) part
    public static function drawPageBottom() {
        if (self::$_html_body && !self::isSessionBlocked()) {
            echo self::$_html_body;
            self::$_html_body = '';
        }
        if (!self::$_footerDrawn) {
            self::$_footerDrawn = TRUE;
            HTML_Footer(FALSE,FALSE);
        }
    }

    public static function addNologgedPageCode($htmlcode) {
        self::$_nologged_starthtml .= $htmlcode;
    }

    public static function drawNologgedPage() {
        if (self::$_nologged_starthtml) echo self::$_nologged_starthtml;
    }
    public static function setRolesFilter($par) {
        self::$_rolesFilter = is_array($par) ? $par : explode(',',(string)$par);
    }

    public static function getRolesFilter() { return self::$_rolesFilter; }
    public static function decodeRole($id) {
        if(!isset(self::$allRoles[$id])) self::$allRoles[$id] = waRoleManager::getInstance()->getRoleDesc($id);
        return self::$allRoles[$id];
    }

    /**
    * Creates HTML code for main application menu, taking current application context
    *
    */
    public static function BuildMainMenu() {

      $admlev = $auth->getAccessLevel('admin');
      self::$_mainmenu = [];
      self::$_mainmenu['start'] = array('href'=>'./', 'title' => self::getLocalized('page.home'));

      # Заранее готовлю заглушки под "базовые" подменю:
      self::$_mainmenu['mnu_browse']  = array('title' => self::getLocalized('mnu-browse'));
      self::$_mainmenu['inputdata']   = array('title' => self::getLocalized('mnu-input'));
      self::$_mainmenu['mnu_reports'] = array('title'=> self::getLocalized('mnu-reports'));
      self::$_mainmenu['mnu_export']  = array('title'=> self::getLocalized('mnu-export'));
      self::$_mainmenu['mnu_admin']   = array('title'=> self::getLocalized('mnu-admin'));
      self::$_mainmenu['mnu_utils']   = array('title'=> self::getLocalized('mnu-utils'));
      self::$_mainmenu['mnu_docs']    = array('title'=> self::getLocalized('mnu-help'));
      if($auth->IsUserLogged()) {

        $subm_in = [];
#        if($admlev) $subm_in['in_stmt_numbers'] = array('href'=>'./?p=fm_setstmtnumbers','title'=>self::getLocalized('mnu_set_stmt_numbers'));

        if(count($subm_in)>0) self::$_mainmenu['inputdata']['submenu'] = $subm_in;

        if($admlev) {
            $submenu = [];
            if(SuperVisorMode()) $submenu['config'] = array('href'=>'config.php', 'title'=>self::getLocalized('title-appconfig'));
            $submenu['log'] =  array('href'=>'eventlog.php', 'title'=>self::getLocalized('page.browse.eventlog'));
            $submenu['depts'] = array('href'=>'depts.php', 'title'=>self::getLocalized('mnu-deptlist'));
            $submenu['users'] = array('href'=>'users.php', 'title'=>self::getLocalized('mnu-userlist'));
            $submenu['ranges'] = array('href'=>'stmt_ranges.php', 'title'=>self::getLocalized('mnu-stmt_ranges'));

            if($auth->isSuperAdmin()) {
                $submenu['datamgr'] = array('href'=>'./?p=adminpanel', 'title'=>self::getLocalized('mnu-datamgr'));
                $submenu['acledit'] = array('href'=>'./?p=acleditor', 'title'=>self::getLocalized('title-acldesigner'));
            }
            self::$_mainmenu['mnu_admin']['submenu'] = $submenu;
        }

        $submenu2 = [];
        $submenu2['myrights'] = array('title'=>self::getLocalized('mnu-view-my-rights'), 'href'=>'./?p=myrights');

        if(self::$auth->getAccessLevel(self::ACTION_ADMIN)) {
            $submenu2['imp_depts'] = array('href'=>'import-depts-lisa.php','title'=>self::getLocalized('mnu-import-depts-lisa'));
            $submenu2['imp_agents'] = array('href'=>'import-agents.php','title'=>self::getLocalized('mnu-import-agents'));
        }

        if(self::$auth->SuperVisorMode()) { # only for SUPERVISOR
            $submenu2['pinfo'] = array('title'=>'PHP Info', 'href'=>'./?p=pinfo');
            $submenu2['tests'] = array('title'=>'Tests page', 'href'=>'./?p=tests');
        }
        if ($auth->getAccessLevel('admin')) $submenu2['org-structure'] = array('title'=>self::getLocalized('mnu-view-dept_tree'), 'href'=>'./?p=viewDeptTree');

        self::$_mainmenu['mnu_utils']['submenu'] = $submenu2;
        self::$_mainmenu['mnu_docs']['submenu'] = array(
             'help_main'  => array('href'=>'help.php','title'=>self::getLocalized('title-help'))
            # ,'help_files' => array('href'=>'./?p=showfiles','title'=>self::getLocalized('mnu-showfiles'))
        );

      }
      # self::runPlugins('init');
      self::runPlugins('modify_mainmenu');
      # return $menu;
      # WriteDebugInfo(self::$_mainmenu);
      self::$mainmenuHtml = '';
      self::generateMenu(self::$_mainmenu,self::$mainmenuHtml);
      return self::$mainmenuHtml;
    }

    /**
    * Loads all php modules in plugins/ folder
    *
    */
    public static function loadPlugins() {

        $lang = self::getClientLanguage();
        # $dead = PM::$deadProducts ?? 'No deads';  writeDebugInfo("dead: ", $dead);
        $incl = [];
        $files = glob(self::$FOLDER_ROOT . self::$FOLDER_PLUGINS . '*.php');

        if(is_array($files)) foreach($files as $fullname) {
            if (self::$traceMe > 3) echo "loadPlugins(): including $fullname...<br>";
            @include_once($fullname);
            $incl[] = basename($fullname);
            $justname = substr(basename($fullname),0,-4);

            $xmlName = self::$FOLDER_ROOT . self::$FOLDER_PLUGINS . "$justname/cfgdef.xml";
            if (is_file($xmlName)) self::loadConfigDef($xmlName);
            else {
                $xmlName = self::$FOLDER_ROOT . self::$FOLDER_PLUGINS . $justname. '.cfgdef.xml';
                if (is_file($xmlName)) self::loadConfigDef($xmlName);
            }
            # load localization if exist 1) in plugin subfolder/strings.<lang>.php OR 2) in i18n/<lng>/<plgname.lng.php

            $lngName1 = self::$FOLDER_ROOT . self::$FOLDER_PLUGINS ."$justname/strings.$lang.php";
            $plglang = is_file($lngName1) ? include($lngName1) : FALSE;
            if(is_array($plglang)) self::$i18n = array_merge(self::$i18n, $plglang);
        }
        # 2) scan subfolders that contains whole plugins inside: plugins/myplug/myplug.php
        $subfold = glob(self::$FOLDER_ROOT . self::$FOLDER_PLUGINS . '*', GLOB_ONLYDIR);

        if (!empty($subfold[0])) foreach($subfold as $sf) {
            $justnm = basename($sf);
            $startPhp = $sf . "/$justnm.php";
            if (is_file($startPhp) && !in_array("$justnm.php", $incl)) {
                # not used products - no page in cinfig!
                /* if(class_exists('PM') && isset(PM::$deadProducts) && in_array($justnm, PM::$deadProducts)) {
                    continue;
                } **/

                try {
                    if (self::$traceMe > 3) echo "loadPlugins(): including $startPhp...<br>";
                    include_once($startPhp);
                    $lngName = "$sf/strings.$lang.php";
                    $xmlName = "$sf/cfgdef.xml";
                    if (is_file($xmlName)) self::loadConfigDef($xmlName);
                    if (is_file($lngName)) {
                        $plglang = include($lngName);
                        if (is_array($plglang)) self::$i18n = array_merge(self::$i18n, $plglang);
                    }
                } catch (Exception $ce) { die ($ce->getMessage()); };
            }
        }

        foreach(self::$_plugins as $plgid=>$plgObj) {
            if(method_exists($plgObj, 'usedJsModules')) {
                if($jslist = $plgObj->usedJsModules()) UseJsModules($jslist);
            }
            if(method_exists($plgObj, 'init')) {
                if (self::$traceMe > 3) echo "$plgid: calling init()...<br>";
                $plgObj->init();
                if (self::$traceMe > 3) echo "$plgid: init() done<br>";
            }
        }

        # postInit: method to be called when ALL modules loaded and INITed
        # (to perform cross-plugin-dependent actions)
        foreach(self::$_plugins as $plgObj) {
            if(method_exists($plgObj, 'postInit')) $plgObj->postInit();
        }
        if (self::$traceMe > 3) {
            echo "mainmenu after init() <pre>" . print_r(self::$_mainmenu,1) . '</pre>';
        }

    }
    # function below is hidden, use separate app/config.php !
    public static function __config() {

        include_once('as_propsheet.php');

        $jsdebug = 0;

        $params = DecodePostData(TRUE,TRUE);
        if(!empty($params['action'])) {
            $result = self::saveAppConfig($params);
            exit(self::getLocalized('title_config_saved')); # "Настройки программы сохранены");
        }

        $pagetitle = self::getLocalized('title-appconfig');

        $jscode = <<< EOJS
function saveAppConfig() {
    var params = $('#appconfig').serialize();
    $.post('./?p=__config',params,function(data) {
        TimeAlert(data,3);
    });
}
EOJS;

        AddHeaderJsCode($jscode);
        self::drawPageHeader($pagetitle);
        $wdth = (defined('IF_LIMIT_WIDTH') ? (IF_LIMIT_WIDTH-80) : '800');
        $msheet = new CPropertySheet('appconfig',$wdth,220,CPropertySheet::STYLE_TABS,CPropertySheet::TABS_LEFT);
        foreach(self::$_defcfg as $pageid=>$page) {

            $draw_page = (!empty($page['enabled']) && function_exists($page['enabled'])) ? call_user_func($page['enabled']) : TRUE;
            if(!$draw_page) continue; # don't draw config page if callback function says "no" (FALSE)

            $fieldlist = [];
            foreach($page['fields'] as $fid=>$fdef) {
                # WriteDebugInfo('config fdef:', $fdef);
                $fid = $fdef['name'];
                $title = isset($fdef['title']) ? $fdef['title'] : '';
                $label = isset($fdef['label']) ? $fdef['label'] : '';
                $fieldlist[$fid] = new CFormField($fid, array(
                     'type'  => $fdef['type']
                    ,'prompt'=> $label
                    ,'title' => $fdef['title']
                    ,'initvalue' => self::getConfigValue($fid)
                    ,'options'   => (empty($fdef['options']) ? '' : $fdef['options'])
                    ,'maxlength' => (empty($fdef['maxlength']) ? '' : $fdef['maxlength'])
                    ,'width'     => (empty($fdef['width']) ? '' : $fdef['width'])
                    ,'onchange'  => (empty($fdef['onchange']) ? '' : $fdef['onchange'])
                    ,'onclick'   => (empty($fdef['onclick']) ? '' : $fdef['onclick'])
                    ,'addparams' => (empty($fdef['attribs']) ? '' : $fdef['attribs'])
                    ,'groupid'   => (empty($fdef['groupid']) ? '' : $fdef['groupid'])

                ));
            }
            $msheet->AddPage($page['title'],$fieldlist);
        }
        echo "<form id='appconfig'><input type='hidden' name='action' value='save_config' /><br>";
        $msheet->Draw(0);
        echo "<br><input type='button' class='btn btn-primary w200' onclick='saveAppConfig()' value='".self::getLocalized('save')."' /><br>";
        echo '</form>';
        self::finalize();
    }

    public static function isTracingActivity() {
        return is_object(self::$_tracer);
    }
    public static function traceActivity() {
        if(is_object(self::$_tracer)) {
            $userid = isset(self::$auth->userid)? self::$auth->userid : 'SYSTEM';
            self::$_tracer->writeEvent($userid, '');
        }
    }

    public static function clear_ob() {
        while($ob = ob_get_status() && $ob['level']>0) {
            # WriteDebugInfo('cleaning ob_state level '. $ob['level']);
            ob_end_flush();
        }
    }
    # Sending file to the client stream
    public static function sendBinaryFile($fname, $outname='', $body = null, $charset=FALSE) {

       if (!file_exists($fname) && $body===null) {
           self::echoError($fname. ' file not found'); # 'err_file_not_found');
           return;
       }
       $curmb = false;
       if ($body!==null && is_callable('mb_internal_encoding')) {
              $curmb = mb_internal_encoding();
              mb_internal_encoding('ASCII'); # substr sucks in UTF internal encoding, under PHP 5.4!
       }

       $size = ($body!==null) ? mb_strlen($body, 'WINDOWS-1251') : filesize($fname);
       $outname = str_replace(' ','_',$outname);
       if (!$outname && !empty($fname)) $outname = basename($fname);
       $mime = self::GetMimeType($outname);

       if ($charset) $mime .= "; charset=".strtolower($charset); # update 2020-04-23

       if ($curmb) mb_internal_encoding($curmb);
       if(!headers_sent()) {
           Header('Pragma: no-cache');
           Header('Pragma: public');
           Header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
           Header("Content-Type: $mime");
           if ( stripos ( $_SERVER['HTTP_USER_AGENT'], "MSIE" ) > 0 ) {
               header ( 'Content-Disposition: attachment; filename="' . rawurlencode ( $outname ) . '"' );
           }
           else {
               header( 'Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode ( $outname ) );
           }
           Header("Content-Length: $size");
       }
       if ($body) exit($body);
       else readfile($fname);
       exit;
    }
    public static function runScheduledTasks() {
        $ret = '';
        foreach(self::$_plugins as $id=>$obj) {
            if (method_exists($obj, 'scheduledTasks')) {
                if (self::$DEBUG>1) echo "Plugin $id:: call scheduledTasks()\n";

                $line = $obj->scheduledTasks();
                if ($line) $ret .= ($ret ?'<br>':'') . $line;
            }
        }
        return $ret;
    }
    /**
    * Sends email to the admin
    *
    * @param mixed $subj
    * @param mixed $text
    */
    public static function sendSystemNotification($subj, $body) {

        $toAddr = self::getConfigValue('admin_email');
        if(!IsValidEmail($toAddr)) return FALSE;
        $sent = self::sendEmailMessage(array(
            'to'=>$toAddr
           ,'subj' => $subj
           ,'message'=> $body
        ));
        return $sent;
    }
    public static function setEmailConvertor($sFuncName) {
        self::$emailConvert = $sFuncName;
    }
    /**
    * Sending email message to adressee
    *
    * @param mixed $params : assoc array with keys: 'to', 'from','subj','message'
    */
    public static function sendEmailMessage($params = [], $files = [], $withDebug = NULL) {

        $toAddr = isset($params['to']) ? $params['to'] : '';
        if($toAddr == '') return FALSE;
        $toCC = isset($params['cc']) ? $params['cc'] : '';
        $toBCC = isset($params['bcc']) ? $params['bcc'] : '';
        if(!empty(self::$emailConvert) && is_callable(self::$emailConvert)) {
            $toAddr = call_user_func(self::$emailConvert, $toAddr);
            if($toCC) $toCC = call_user_func(self::$emailConvert, $toCC);
            if($toBCC) $toBCC = call_user_func(self::$emailConvert, $toBCC);
        }
        $from = isset($params['from']) ? $params['from'] : self::getConfigValue('notification_fromaddr');
        $subj = isset($params['subj']) ? $params['subj'] : "System Notification";
        $body = isset($params['message']) ? $params['message'] : '';
        if(!$from) $from = 'admin@yourcompany.com'; # You better make sure it's correct !

        if($withDebug!== NULL) $debug = $withDebug;
        else $debug = self::getConfigValue('z_debug_email', FALSE);

        if (self::getConfigValue('emulate_email_sending')) {
            $fhan = fopen(WebApp::$FOLDER_ROOT . '/_email-send.log','a');
            if(self::$emailMakeBr) $body =  nl2br($body, false);
            if ($fhan) {
                fwrite($fhan, ">>===== Emulated Email Sending [".date('Y-m-d H:i:s') . "] =============================\n");
                fwrite($fhan, "[TO: " . (is_array($toAddr)?implode(',',$toAddr):$toAddr)."]\n");
                if (!empty($toCC))
                    fwrite($fhan, "[CC: " . (is_array($toCC)?implode(',',$toCC):$toCC)."]\n");
                if (!empty($toBCC))
                    fwrite($fhan, "[BCC: " . (is_array($toBCC)?implode(',',$toBCC):$toBCC)."]\n");

                fwrite($fhan, "[FROM: $from]\n");
                fwrite($fhan, "[SUBJ: $subj]\n");
                fwrite($fhan, $body . "\n\n");
                if (is_array($files)) foreach($files as $fl) {
                    fwrite($fhan, "-- File Attachment: " . print_r($fl,1)."\n");
                }
                if (!empty($params['embedded']) && is_array($params['embedded'])) foreach($params['embedded'] as $embedid=>$path) {
                    fprintf($fhan, "-- File embedded/%s: %s\n", $embedid, $path);
                }
                fwrite($fhan,"\n");
                fclose($fhan);
            }
            else {
                writeDebugInfo("Write error to file: ", WebApp::$FOLDER_ROOT . '/_email-send.log');
                return FALSE;
            }
            return TRUE;
        }
        if(empty($toAddr)) return 1; # toAddr was cleaned by ClientUtils::checkEmailAddr, treat it as "Sent"
        if($debug) writeDebugInfo("mail:KT-001");
        require_once('PHPMailerAutoload.php');
        if($debug) writeDebugInfo("mail:KT-002");

        $mail = new PHPMailer();

        if($debug) {
            writeDebugInfo("mail:KT-003");
            $mail->SMTPDebug = 1;
            $mail->Debugoutput = 'writeDebugInfo'; # включаю отладку через свой вывод сообщений
        }
        $mailMethod = self::getConfigValue('smtp_method', 'smtp');
        $mail->Mailer = $mailMethod;
        $mail->Host = self::getConfigValue('smtpserver_addr','localhost');


        if ($smtpUser = self::getConfigValue('smtp_username')) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = self::getConfigValue('smtp_password');
        }
        $mail->SMTPSecure = strtolower( self::getConfigValue('smtpserver_secure',''));
        if($debug) writeDebugInfo("mail:KT-004, param set");

        if (strtolower($mail->SMTPSecure) === 'tls' && $mailMethod==='smtp') {
            # found possible solution for TLS problem:
            # https://stackoverflow.com/questions/39927267/phpmailer-cant-send-via-postfix
            $mail->SMTPOptions = array
              (
                'ssl' => array
                (
                  'verify_peer' => false,
                  'verify_peer_name' => false,
                  'allow_self_signed' => true
                )
            );
        }
        $cset = isset($params['charset']) ? $params['charset'] : '';
        if(!$cset) $cset = constant('MAINCHARSET') ? constant('MAINCHARSET') : '';
        $mail->CharSet = $cset;

        $mail->SetFrom($from, $from);
        $mail->AddReplyTo($from, $from);
        $toList = is_array($toAddr) ? $toAddr : preg_split('/[, ;]/', $toAddr,-1, PREG_SPLIT_NO_EMPTY);
        $toCount = 0;

        if($debug) writeDebugInfo("mail:KT-05");

        foreach($toList as $oneTo) {
            $splitAddr = explode('.', $oneTo);
            $lastPart = strtolower(array_pop($splitAddr));
            if($lastPart === self::EMAIL_TESTING_POSTFIX) {
                continue; # skip addreses like xxxxx@zzzz.testing
            }
            if (isValidEmail($oneTo)) {
                $mail->AddAddress($oneTo, $oneTo);
                ++$toCount;
            }
        }
        if($debug) writeDebugInfo("mail:KT-06");
        if (!empty($toCC)) {
            $toList = is_array($toCC) ? $toCC : preg_split('/[, ;]/', $toCC,-1, PREG_SPLIT_NO_EMPTY);
            foreach($toList as $oneTo) {
                if (isValidEmail($oneTo))
                    $mail->AddCC($oneTo, $oneTo);
            }
        }
        if($debug) writeDebugInfo("mail:KT-07");
        if (!empty($toBCC)) {
            $toList = is_array($toBCC) ? $toBCC : preg_split('/[, ;]/', $toBCC,-1, PREG_SPLIT_NO_EMPTY);
            foreach($toList as $oneTo) {
                if (isValidEmail($oneTo)) {
                    $mail->AddBCC($oneTo, $oneTo);
                }
            }
        }
        if($toCount == 0)
            return FALSE; # no real TO addresses, sending aborted!

        # 'embedded': array with embedded images for HTML format letter
        if (!empty($params['embedded']) && is_array($params['embedded'])) foreach($params['embedded'] as $embedid=>$path) {
            if (is_string($path) && is_file($path)) {
                $mail->AddEmbeddedImage($path, $embedid);
            }
            # else die('1'.ajaxResponse::showError("Not found file for embedded $embedid<pre>".print_r($path,1).'</pre>'));
        }
        $mail->Subject = $subj;
        if($debug) writeDebugInfo("mail:KT-08");

        # $mail->AltBody    = "To view the message, please use an HTML compatible email viewer!";
        if(self::$emailMakeBr) $body = nl2br($body);
        if(stripos($body, '<html') === FALSE) $body = "<html><body>\n" .$body . "\n</body></html>";
        $mail->MsgHTML($body);
        if($debug) writeDebugInfo("mail:KT-09");

        // add attachments if passed
        if (is_array($files) && count($files)>0) {
            foreach($files as $attfile) {
                $attPath = $attName = '';
                if (is_string($attfile)) {
                    $attPath = $attfile;
                    $attName = basename($attfile);
                }
                else {
                   $attPath = $attfile['path'] ?? $attfile['fullpath'] ?? '';
                   $attName = $attfile['name'] ?? $attfile['filename'] ?? '';
                }
                if( !empty($attPath) && is_file($attPath) ) {
                    $mail->addAttachment($attPath, $attName); //, $encoding = 'base64', $type = '', $disposition = 'attachment')
                }
                elseif($debug) writeDebugInfo("file to attach not exist: ", $attfile);
            }
        }
        if($debug) writeDebugInfo("mail:KT-10");
        $sent = $mail->Send();
        if($debug) writeDebugInfo("mail:KT-11, Send() done, result:[$sent]");
        if(!$sent) {
            self::$errMailinfo = $mail->ErrorInfo;
            if($debug) writeDebugInfo("sent error information: ",self::$errMailinfo);
        }
        return $sent;
    }

    //эта функция рекурсивно обходит все папки и составляет список файлов
    //результат её работы можете посмотреть, выведя var_dump($allfiles) после её вызова
    public static function recoursiveDir($dir){
        $allfiles = [];

        if ($files = glob($dir.'/*'))
            foreach($files as $file){
                if (is_dir($file))
                    foreach(recoursiveDir($file) as $value) $allfiles[] = $value;
                else $allfiles[]    =   $file;
            }

        return $allfiles;
    }
    /**
    * Adding privileges for editing specific table in context of specific plugin
    *
    * @param mixed $right_def right ID
    * @param mixed $tables table list (array or comma separated string: "table1,table2,..."
    * @param mixed $privilege privilege level, default 1
    */
    public static function addRefPrivileges($right_def,$tables, $privilege=1) {
        $tblist = is_array($tables)? $tables : explode(",", $tables);
        foreach ($tblist as $onetable) {
            if (!isset(self::$_table_privileges[$onetable])) self::$_table_privileges[$onetable] = [];
            self::$_table_privileges[$onetable][$right_def] = $privilege;
        }
    }
    /**
    * Define current user privilege for editing table $tableid
    *
    * @param mixed $tableid table ID
    */
    public static function getRefPrivileges($tableid) {

        if (isset(self::$_table_privileges[$tableid])) {
            $retlevel = array([],[],[]);
            foreach(self::$_table_privileges[$tableid] as $oneright=>$rlevel) {
                $right_val = self::$auth->getAccessLevel($oneright);
                if (!$rlevel) continue;
                if ( !$right_val) continue; # user has no such right!
                if (is_numeric($rlevel)) $rt = array($rlevel, $rlevel, $rlevel);
                elseif (!is_array($rlevel)) continue;
                else $rt = array_values($rlevel);
                $retlevel[0] = $rt[0];
                $retlevel[1] = isset($rt[1]) ? $rt[1] : $rt[0];
                $retlevel[2] = isset($rt[2]) ? $rt[2] : $retlevel[1];
            }
            return $retlevel;
        }
        return FALSE;
    }
    // add user function to call before start Editing ref.
    public static function prependEditRef($tableid, $userfunc) {
        if (!empty($userfunc))
            self::$editRefPrenedData[$tableid] = $userfunc;
        else unset(self::$editRefPrenedData[$tableid]);
    }
    /**
    * common func for CRUD-ing tables
    * ACL will be be computed by calling getRefPrivileges()
    */
    public static function editref() {

        $tabid = isset(self::$_p['t']) ? self::$_p['t'] : '';
        if (empty($tabid) && !empty(self::$_p['tableid']))
            $tabid = self::$_p['tableid'];

        if(!$tabid) {
            self::echoError('Wrong call!');
            return;
        }

        $privs = FALSE;

        require_once('astedit.php');
        self::$gridObj = new CTableDefinition($tabid);
        # if (self::$auth->isSuperAdmin()) $privs = array(1,1,1);
        if ($tpriv = self::getRefPrivileges($tabid)) {
            $privs = is_array($tpriv) ? $tpriv : array($tpriv,$tpriv,$tpriv);
        }
        if (!$privs && is_callable('getEditPrivileges')) {
            $privs = getEditPrivileges($tabid);
        }
        if (!$privs &&  WebApp::$auth->SupervisorMode())
            $privs = [1,1,1,1];
        if (!$privs) {
            self::echoError('err-no-rights');
            return;
        }
        # drowse, $canedit=false, $candelete=false, $caninsert
        if(self::$gridObj->canedit !== NULL) $privs[0] = min($privs[0],self::$gridObj->canedit);
        if(self::$gridObj->candelete !== NULL) $privs[1] = min(($privs[1]??0),self::$gridObj->candelete);
        if(self::$gridObj->caninsert !== NULL) $privs[2] = min(($privs[2]??0),self::$gridObj->caninsert);

        # writeDebugInfo("astedit for $tabid");
        if (array_key_exists($tabid, self::$editRefPrenedData) && is_callable(self::$editRefPrenedData[$tabid]))
            call_user_func(self::$editRefPrenedData[$tabid], self::$gridObj);
        self::$gridObj->setBaseUri("./?p=editref&t=$tabid");

        $title = self::$gridObj->desc;
        $jscode = self::$gridObj->PrintAjaxFunctions(1,1);
        addHeaderJsCode($jscode);
        self::setPageTitle($title);
        self::drawPageHeader($title);

        $canedit = $candelete = $caninsert = 0;
        if (is_array($privs)) {
            $canedit = $privs[0];
            $candelete = isset($privs[1]) ? $privs[1] : FALSE;
            $caninsert = isset($privs[2]) ? $privs[2] : FALSE;
        }
        else $canedit = $candelete = $caninsert = $privs;

        self::$gridObj->MainProcess(1,$canedit,$candelete,$caninsert);
        # ($dobrowse=1, $canedit, $candelete, $caninsert)
        self::finalize();
        if (self::isStandalone()) exit;
    }

    /**
    * Stores in session message for "instant" viewing on nearest page refresh
    *
    */
    public static function addInstantMessage($txt,$id='') {
        if(!$id) $id = 'msg'.rand(10000000,999999999);
        if(!isset($_SESSION['instant_msg'])) $_SESSION['instant_msg'] = [];
        $_SESSION['instant_msg'][$id] = $txt;
    }
    public static function addShowMessage($strg) {
        if (!in_array($strg,self::$showMessages))
            self::$showMessages[] = $strg;
    }
    public static function getShowMessages() {
        if (self::$showMessages) {
            $ret = implode('<br>',self::$showMessages);
            self::$showMessages = [];
            return $ret;
        }
        return FALSE;
    }
    public static function instantMessagesExist() {
        return (!empty($_SESSION['instant_msg']) && count($_SESSION['instant_msg'])>0);
    }

    /**
    * Tracks memory using:
    * if alomost all available memory comsumed, return TRUE
    * @param mixed $action 'init' - initializes tracking
    * @param mixed $params : params['message']- text to Show user when memory limit is almost reached
    */
    public static function trackMemory($action = '', $params=0) {

        if ($action === 'init' || !self::$_mem_limit) {
            self::$_prev_used = memory_get_usage();
            self::$_mem_message = '';
            $strlimit = ini_get('memory_limit');
            self::$_mem_limit = intval($strlimit);
            if (substr($strlimit, -1) == 'M') self::$_mem_limit *= 1024*1024;
            elseif (substr($strlimit, -1) == 'K') self::$_mem_limit *= 1024;
            /*
            WriteDebugInfo("init: memory_limit = $strlimit  ( " . number_format(self::$_mem_limit,0,'.',' ') . ' Bytes), used: '
              . number_format(self::$_prev_used,0,'.',' '));
            */
            return self::$_mem_limit;
        }
        if (isset($params['message'])) self::$_mem_message = $params['message'];
        $cur_used = memory_get_usage();
        $delta = $cur_used - self::$_prev_used;
        # WriteDebugInfo("mem limit:",self::$_mem_limit, ' used :',$cur_used);
        if ($delta > 0 && ((self::$_prev_used + $delta * 5) >= self::$_mem_limit)) {
            if (self::$_mem_message)
                self::addInstantMessage(self::$_mem_message, 'memory_limit');
            WriteDebugInfo("stopping by memory ($cur_used Bytes), message: ", self::$_mem_message);
            return true; # memory's almost ended
        }
        self::$_prev_used = $cur_used;
        return false; // memory is OK, you can continue run script
    }
    # handle ajax request "getinstantmessage"
    public static function getInstantMessage() {
        $ret = '';
        if(!empty($_SESSION['instant_msg'])) $ret = implode('<br>', $_SESSION['instant_msg']);
        unset($_SESSION['instant_msg']);
        exit (encodeResponseData($ret));

    }
    public static function instantMessageExist() {
        return (!empty($_SESSION['instant_msg']) && count($_SESSION['instant_msg'])>0);
    }
    public static function setPlugin($plgid) {
        self::$_plg_name = $plgid;
    }

    # Suppress unwanted headers generated by parent CMS (supported CMS for now: 1C-Bitrix)
    public static function avoidExternalHeaders() {
        global $APPLICATION;
        if (isset($APPLICATION) && is_object($APPLICATION) && method_exists($APPLICATION, 'RestartBuffer'))
            $APPLICATION->RestartBuffer();
    }

    public static function logEvent($stype, $stext='', $deptid='', $itemid=0, $uid=0){

        global $auth, $authdta;
        $ipaddr = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'SYSTEM';
        if(!$uid) $uid = isset($auth->userid)? $auth->userid : '0';
        if (empty($authdta['usrdeptid'])) $deptid = 'NODEPT';
        elseif(!$deptid) {
            if(!empty($auth->deptid)) $deptid = $auth->deptid;
            elseif($uid) $deptid = self::$db->GetQueryResult($authdta['table_users'],$authdta['usrdeptid'],array($authdta['usrid']=>$uid));
        }
        if(self::IsSoapMode()) $stext .= ' [SVC]';
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
    * Finds in eventlog "previous" logon record for the user (excluding current session!)
    *
    * @param mixed $userid user ID
    */
    public static function getLastLogonDate($userid=0) {

        if(!$userid) {
            global $auth;
            $userid = $auth->userid;
        }
        $logon = CAuthCorp::getInstance()->getLastLogonTime();
        if(!empty($logon)) return to_date(substr($logon,0,10));
        $dta = self::$db->sql_query('select evdate from '.self::$TABLES_PREFIX . "eventlog WHERE userid='$userid' AND evtype='AUTH OK' ORDER BY evdate DESC limit 3",1,1,1);
        # WriteDebugInfo('lastlogon/logevent:', (isset($dta[1])?$dta[1]:'none'));
        return (isset($dta[1]['evdate']) ? substr($dta[1]['evdate'],0,10) : '');
    }

    /**
    * Serializing assoc.array into string in format "[varname1=value1][var2=value2]..."
    * @param $data array to be serialized
    * @param $mode 0 -normal mode (adaptive), 1 - data is a indexed array of assoc.rows - so we add index into var name: [0.var1=value1]...
    * 'json' - encode to json
    * @param $fordb if not empty, converft JSON result : replacing "\" to "\\" to prevent eating "\" by SQL insert @since 1.04
    */
    public static function serializeData($data, $mode=0, $fordb = 0) {

        if (empty($data)) return '';
        $sfrom = array('=','[',']', "'");
        $sto = array('\x3D','\x5B','\x5D', '"');
        $ret = '';
        if ($mode === 'json') { # @since
            $opts = JSON_HEX_QUOT | (defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
            $ret = json_encode($data,$opts);
            if ($fordb) $ret = str_replace("\\", "\\\\", $ret);
        }
        elseif (!$mode) { # universal mode
            foreach($data as $k=>$val) {
                if (is_scalar($val)) {
                    if (is_numeric($val) && round($val,self::$SERIALIZE_DECIMALS)!=$val) $val = number_format($val,self::$SERIALIZE_DECIMALS,'.','');
                    elseif (is_string($val)) $val = str_replace($sfrom, $sto, $val);
                    $ret .= "[$k=$val]";
                }
                elseif ( is_array($val) ) {
                    foreach($val as $kel=>$valel) {
                        if (!is_scalar($valel)) continue;
                        if (is_numeric($valel) && floor($valel)!=$valel)
                            $valel = number_format($valel,self::$SERIALIZE_DECIMALS,'.','');
                        elseif (is_string($valel)) $valel = str_replace($sfrom, $sto, $valel);
                        $ret .= "[$k.$kel=$valel]";
                    }
                }
            }
        }
        else { # данные  - нумерованный массив строк. Складываю построчно. [0.field1=value][0.field2=value2]...[1.field1=value11]...
            foreach ($data as $no => $row) {
                if(!is_array($row)) continue;
                foreach($row as $kel=>$valel) {
                    $ret .= "[$no.$kel=$valel]";
                }
            }
        }
        return $ret;
    }
    /**
    * Restoring assoc.array from serialized string (see serializeData function)
    * @param $body serialized string (may be json_encoded)
    * @return restored array
    */
    public static function unserializeData($body) {
        if(substr($body,0,1) === '{') { # auto-detect and decode JSON
            $ret = @json_decode($body, TRUE);
            return $ret;
        }
        $pattern = "/\[([^\]]+)=([^\]]+){0,}\]/i";
        $sfrom = array('\x3D','\x5B','\x5D');
        $sto = array('=','[',']');
        if ($body=='') return [];
        if (substr($body,0,1) === '{') { # encoded to json
            $ret = @json_decode($body,TRUE);
            return $ret;
        }
        preg_match_all($pattern, $body, $matches,PREG_SET_ORDER);
        $ret = [];
        if (is_array($matches)) foreach($matches as $elem) {
            $splt = explode('.', $elem[1]);
            $vval = isset($elem[2])? $elem[2] : '';
            if (is_string($vval)) $vval = str_replace($sfrom, $sto, $vval);

            $vkey = $elem[1];
            if(count($splt)>1) { # [0.var1=value01] will become $ret[0]['var1'] = 'value1'
                $ino = is_numeric($splt[0]) ? intval($splt[0]) : $splt[0];
                if (!isset($ret[$ino])) $ret[$ino] = [];
                $ret[$ino][$splt[1]] = $vval;
            }
            else $ret[$elem[1]] = $vval;
        }
        return $ret;
    }

    /**
    * Adding hook function for some Webapp method
    *
    * @param mixed $hookedfunc
    * @param mixed $userfunc
    */
    public static function addHook($hookedfunc, $userfunc) {
        if (!isset(self::$_hooks[$hookedfunc])) self::$_hooks[$hookedfunc] = [];
        self::$_hooks[$hookedfunc][] = $userfunc;
    }

    # If some hook(s) registered, call them until non-empty result
    public static function callHooks() {
        $arg = func_get_args();
        $fncname = array_shift($arg);
        if (isset(self::$_hooks[$fncname])) foreach (self::$_hooks[$fncname] as $onefunc) {
            $result = call_user_func_array($onefunc,$arg);
            if ($result) return $result;
        }
        return null;
    }
    /**
    * Creates salted hash for user password
    *
    * @param mixed $passw password to be hashed
    * @param mixed $userid user id
    */
    public static function encode_password($passw, $userid=0) {
        return sha1("[$userid]".self::$pwd_salt.$passw);
    }
    public static final function isDeveloperEnv() {
        return (self::getConfigValue('developer_env'));
    }
    public static function ActivatePTS() {
        if (!self::$auth->SuperVisorMode()) {
            self::echoError('err-no-rights');
            return;
        }
        self::setPageTitle('Activating password managin system');
        $html = self::$_pwdmgr->activatePTS(1);
        self::appendHtml($html);
        self::finalize();
    }
    /**
    * Parsing markdown lines using Parsedown.php module
    *
    * @param mixed $strg original string to be parsed
    * @return HTML code generated from original
    */
    public static function parseMarkDown($strg) {
        include_once('Parsedown.php');
        $parsedown = new Parsedown();
        $parsedown->setUrlsLinked(false);
        $strg = str_replace("\r\n", "\n", $strg);
        $strg = str_replace("\r", "\n", $strg);
        $strg = $parsedown->text($strg);
        unset($parsedown);
        return $strg;
    }
    public static function toClientCset($value) {
      $cset = constant('MAINCHARSET') ? constant('MAINCHARSET') : '';
      if ($cset !== '' && $cset !=='UTF-8') return @iconv('UTF-8', $cset, $value);
      return $value;
    }
    /**
    * Rotate/Delete files in folder by mask created $days ago and more
    *
    * @param mixed $folder fodler to be cleaned: 'reports/' etc
    * @param mixed $mask file mask, like 'myfile*.zip'
    * @param mixed $days days amount
    * @return deleted files amount
    */
    public static final function rotateFiles($folder, $mask='', $days=10) {

        $watermark = strtotime("-$days days");
        $darr = [];
        $deleted = 0;

        if (is_dir($folder) && $mask!='' && $days > 0) {

            foreach (glob($folder.'{'.$mask.'}', GLOB_BRACE) as $filename) {
                if ( is_file($filename) && filemtime($filename) <= $watermark ) {
                  $darr[] = $filename;
                }
            }

            foreach($darr as $fname) {
              $deleted += (unlink($fname)) ? 1 : 0;
            }
        }
        return $deleted;
    }

    /**
    * Perform instant redirect to desired page/URI
    *
    * @param mixed $uri URI to redirect client
    * @since 1.02
    */
    public static function localRedirect($uri)
    {
        if (headers_sent()) {
            # headers already sent, so redirect by <head> tags
            echo "<html><head><meta http-equiv=\"refresh\" content=\"0;$uri\"></head><body>Redirecting...</body></html>";
        }
        else {
            header( "Location: $uri", true, 303 );
            exit; # ("Redirecting to $uri ...");
        }
        // with 303 redirect to local page
    }

    # return TRUE if production environment
    public static function isProdEnv() {
        $env = defined('APP_ENVIRONMENT') ? constant('APP_ENVIRONMENT') : 'prod';
        return ($env === 'prod');
    }
    # get full path to cache folder
    public static function getCacheFolder() {
        $dirname = self::$FOLDER_ROOT .'appcache';
        if(!is_dir($dirname)) @mkdir($dirname, 0777,TRUE);
        if(is_dir($dirname)) return "$dirname/";
        return FALSE;
    }
    # switch auto NL 2 BR ON or OFF (for html code in email letters)
    public static function setEmailNl2Br($br=FALSE) { self::$emailMakeBr = $br; }
    # stub for callback, that must be executed right after user logon
    public static function onAfterLogon($context = FALSE) { }

    /**
    * checks if file is in one of include_path folder
    * @since 1.23 - 2023-12-25
    * @param mixed $filename php file name to find
    * @param mixed $autoInclude if TRUWE|1 include_once() if found!
    * @returns TRUE if file found
    */
    public static function isModuleInPath($filename, $autoInclude=FALSE) {
        foreach(explode(PATH_SEPARATOR, get_include_path()) as $incPath) {
            if(is_file($incPath . DIRECTORY_SEPARATOR . $filename)) {
                if($autoInclude)
                    include_once($filename);
                return TRUE;
            }
        }
        return FALSE; # file not found in include folders
    }
    # returns plugin current version (to use in js/css/ resource links inside this plugin)
    public static function getModuleVersion($pluginId) {
        if(isset(self::$_plugins[$pluginId]) && is_object(self::$_plugins[$pluginId])
          && method_exists(self::$_plugins[$pluginId], 'getVersion'))
        $ret = self::$_plugins[$pluginId]->getVersion();
        else $ret = '';
        return $ret;
    }
} // WebApp class end

function webapp_shutdown_handler() {

    $error = error_get_last();
    // ignore "mysql_pconnect(): Headers and client library minor version mismatch"
    $env = defined('APP_ENV') ? constant('APP_ENV') : 'prod';
    if( $error !== NULL && $env=='dev') {
        if (stripos($error['message'],'minor version mismatch')) return true;
        echo ("<div class='fatal'>ERROR : type=$error[type],<br>message=$error[message],"
          ."<br>errfile=$error[file], <br>line=$error[line]</div>"
        );
    }
}
 if (WebApp::getParentCms() === FALSE)
#if (0)
{
#    error_reporting(0);
#    ini_set('display_errors',0);
    register_shutdown_function( 'webapp_shutdown_handler' );
}