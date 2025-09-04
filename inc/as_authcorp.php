<?PHP
/**
* @name as_authcorp.php - [corporate] authentication helper
* @Author Alexander Selifonov <alex [at] selifan {dot} ru>
* @Version 1.47.004
* modified 2025-06-04
**/
if(!defined('SUPERNAME')) define('SUPERNAME','supervisor');
define('ADMINLOGIN','admin'); # built-in admin logon name
# define('READERLOGIN','read'); # built-in "reader"
define('AUTH_DEBUGID', -9999); // debugging for this user id
define ('LOGBROWSERINFO',false); # log browser type/version

if(file_exists('auth_tabledefs.php')) @include_once('auth_tabledefs.php');

class CAuthCorp {

  const STATE_PWD_EXPIRED = -10;
  const STATE_LOGGED = 1;
  const PWD_NEVER_EXPIRES = -1;
  const PWD_NOCHANGE= -2;
  const DEBUGLOGIN = '--xxx--';
  const AUTH_RIGHT_SUPEADMIN = 'superadmin';
  const AUTH_RIGHT_SUPEVISOR = 'supervisor';
  const ERR_BAD_PARAMS = 'ERROR: Unrecognized parameters';

  // blocking reason codes
  const BLK_WRONGPWD   = 11;
  const BLK_BYIDLEUSER = 12;
  const BLK_BYADMIN    = 100;
  const SAVE_PSW = true; # save clean pwd if login OK
  private static $_pwd_folder = './'; # folder holding built-in password files
  private static $_lang = 'ru';
  private static $pwd_salt = '#Tyjhgv38-45%HFD26876dKjGS50987871.AsdtRu-7wnFghtuYOps';
  private $_usermgrRights = array('');
  private $_hooks = array();
  private $_protectLogin = TRUE; # protect from BOT logins
  static protected $right_supervisor = ''; # can be overrided

  static $OLD_CHARSET = 'WINDOWS-1251';

  static $_builtin_accounts = array(
       SUPERNAME   => array('access'=>'100', 'name'=>'System Admin',  'psw'=>'447266fc51d43264df9278e948400074941f207548','chgpwd'=>1) # ot...ok
      ,ADMINLOGIN  => array('access'=> '10', 'name'=>'Administrator','psw'=>'1db72dc197ca19d3d83da36a8a639d20fa0b20af72','chgpwd'=>0) # 75757575
#      ,READERLOGIN => array('access'=>  '1', 'name'=>'Reader',       'psw'=>'85626aee065d9e653a8cefa0e1fd346c9dca011b5d','chgpwd'=>0) # password: 12345678
  );

  private static $_instance = null;
  private $debug = 0;
  private $db = null;
  private $_p = array();
  private $_topdept = 0;
  public $a_rights = array(); # user specific rights list
  private $_appid = '';
  private $b_buffered = false;
  private $b_authajax = false;
  private $b_encryptpass = false;
  public  $_defaultpsw = '1qaz!QAZ';
  var $rootobj = 0;
  var $name = '';
  var $nikname = '';
  var $email = '';
  public $phone = '';
  public $address = '';
  var $firstname = '';
  var $secondname = '';
  var $deptid = 0;
  var $maindept = 0; # main parent dept
  var $deptname = ''; # full dept name, with all hierarchy: "base-dept/subdept/..."
  var $userid = 0;
  var $zoneid = 0; # regional zone id, if logged on
  var $_logonmode = 0; # logon window fashion: 0- horizontal (one table row), 1 - vertical
  var $minpasslen = 4; # ex $authdta['minpasslen']
  var $errormessage = '';
  var $_inlinesub = ''; # call this func while drawing inline logon form
  var $_authalign = 'left';
  var $_jquery = false; # use jQuery functions if true

  var $b_cabinet = true; # enable link for user cabinet (personal data manager)
  var $onlogonfunc = ''; # user func, that will be called right after successful TryLogin
  var $_hideloggedinfo = false;
  var $_allownewusers = false;
  var $_registration_uri = 'registration.php';
  var $_use_uithemes = false; # use jquery.ui themes in logon form design
  var $_rememberme = false;
  private $_field_lastlogon = ''; # if set, call UPDATE set ... = NOW() every logged-in event
  private $_field_lastlogonIp = '';
  private $_autoblock_idledays = 0; # if user didn't log in NN days or more, block his account
  var $_authevents = array(); # 'login' event, 'logout', 'login-fail' : if function name passed, it's called
  private $_pwdManager = false; # PasswordManager object to manage/trace password rules (complexity, expiring)
  private $_roleManager = null; # WaRoleMAnager object, if passed, role management system will be used
  private static $superadmindept = 1; # Dept ID for builtin Supervisor
  private $_roles = array();  # will contain assoc/array with all roles for
  private $_loadroleFunc = ''; # passed function name that will load all roles for userid : 'myroles' => $_roles = myroles($userid)
  private $_showtime = false; # TRUE to show Server time according to TIMEZONE setttings
  private $_enable_restorepwd = false; # TRUE to show "restore password" link
  private $_blockLogon = array();
  private $_use_sso = FALSE; # since 1.25
  private $_clicharset = '';
  private $_trace_activity = 0;
  private $_confirm_logout = false;
  private $_options = array(
     'password_max_tries'=> 0 // amt of attempts treated as password hacking
    ,'password_blocktime'=> 10 // how long account blocked after pasword hacking attempt (minutes)
    ,'enter_old_pwd'     => 0 // re-enter old password during "changing password" operation
    ,'use_psw_policies'  => 0 // use or no password policies
  );
  private $_d = array(); // table and field names (replace $authdta references)
  private $_sudo = FALSE;
  public $usr_encodepsw_func = false; # user function to make hash from passwords

  public function __construct($ajaxmode=true, $jquery=true, $options=null) {

    global $as_dbengine, $authdta;
    if (!empty($options['definitions']) && is_array($options['definitions']))
        $this->_d = $options['definitions'];
    elseif (is_array($authdta)) $this->_d =& $authdta;
    # else $this->_d = array(...); // - default field config;
    $this->b_authajax = $ajaxmode;
    $this->_jquery = $jquery;
#    $this->_authcfg =& $authdta;

    if(defined('MAINCHARSETCL')) $this->_clicharset = constant('MAINCHARSETCL');
    elseif(defined('MAINCHARSET')) $this->_clicharset = constant('MAINCHARSET');
    if(is_array($options)) {
        if(isset($options['db']))   $this->db = $options['db'];
        if(isset($options['hideloggedinfo']))   $this->_hideloggedinfo = $options['hideloggedinfo'];
        if(isset($options['allownewusers']))    $this->_allownewusers = $options['allownewusers'];
        if(isset($options['registration_uri'])) $this->_registration_uri = (string)$options['registration_uri'];
        if(isset($options['logonmode']))        $this->_logonmode = (string)$options['logonmode'];
        if(isset($options['use_uithemes']))     $this->_use_uithemes = $options['use_uithemes'];
        if(isset($options['rememberme']))       $this->_rememberme = $options['rememberme'];
        if(isset($options['appid']))           $this->_appid = $options['appid'];
        if(isset($options['lastlogonfield']))  $this->_field_lastlogon = $options['lastlogonfield'];
        if(isset($options['encryptpassword'])) $this->b_encryptpass = $options['encryptpassword'];
        if(isset($options['loadrolesfunc'])) $this->_loadroleFunc = $options['loadrolesfunc'];
        if(isset($options['showtime'])) $this->_showtime = $options['showtime'];
        if(isset($options['use_sso'])) $this->_use_sso = $options['use_sso'];
        if(isset($options['charset'])) $this->_clicharset = $options['charset'];
        if(isset($options['supervisor_right'])) self::$right_supervisor = $options['supervisor_right'];
        $this->_trace_activity = !empty($options['trace_activity']);
        $this->_enable_restorepwd = !empty($options['restore_password']);
        $this->_confirm_logout = !empty($options['confirm_logout']);

        if(!empty($options['pwdmanage'])) {
            if(is_object($options['pwdmanage'])) $this->_pwdManager = $options['pwdmanage'];
        } # _pwdManager
        if(!empty($options['rolemanager'])) {
            if(is_object($options['rolemanager']) && method_exists($options['rolemanane'],'getUserRights')) $this->_roleManager = $options['rolemanager'];
        } # _roleManager
        elseif(class_exists('WaRolemanager')) $this->_roleManager = WaRolemanager::getInstance();
    }
    if (!$this->db) {
        if (class_exists('appEnv') && isset(appEnv::$db)) $this->db =& appEnv::$db;
        elseif (!empty($as_dbengine) && is_object($as_dbengine)) $this->db =& $as_dbengine;
    }

    if(!isset($_SERVER)) return;
    if(empty($this->_appid) && isset($_SERVER['REQUEST_URI'])) {
        $uripart = strpos($_SERVER['REQUEST_URI'],'?') ? substr($_SERVER['REQUEST_URI'], 0,strpos($_SERVER['REQUEST_URI'],'?')) : $_SERVER['REQUEST_URI'];
        $arr = explode('/', $uripart);
        array_pop($arr);
        $this->_appid = array_pop($arr);
    }
    if($this->_showtime) {
        $secDelay = (60 - date('s')) * 1000; // minute switch will occur exactly on "00" seconds of server time
        $initCmd = $secDelay>0 ? "setTimeout('authTimeRefresh();authActivateTimeRefresh();',$secDelay);" : 'authActivateTimeRefresh();';
        if(class_exists('CHtmlHelper')) CHtmlHelper::getInstance()->AddJsCode($initCmd, 'ready');
        elseif(function_exists('AddHeaderJsCode')) AddHeaderJsCode($initCmd, 'ready');
    }
    self::$_instance = $this;
  }

  public static function setRightSuper($rightid) {
      self::$right_supervisor = $rightid;
  }

  /**
  * Setting folder for saved "built-in accounts" passwords
  * @since 1.31
  * @param mixed $path new folder for pwi files
  */
  public static function setPwdFolder($path) {
      self::$_pwd_folder = $path;
  }
  public function setOptions($par) {
      if(is_array($par)) $this->_options = array_merge($this->_options,$par);
  }
  function setPasswordManager(&$param) {
      $this->_pwdManager =& $param;
  }
  function SetRoleManager(&$param) { $this->_roleManager =& $param; }
  function SetInlineSubFunc($fncname='') { $this->_inlinesub=$fncname; }

  /**
  * Activating "Blocked Logon"
  *
  * @param mixed $text - html code that will be drawn in "logon" screen zone
  * @param mixed $smalltext - optional text that will be returned  as AJAX response for any "logon" request
  */
  function blockLogon($text,$smalltext='') {
      $this->_blockLogon = array('text'=>$text,'smalltext'=>$smalltext);
  }
  /**
  * Setting (DATETIME) fieldname in users table, so every login will update it with NOW()
  *
  * @param mixed $param field name
  */
  function SetLastLogonField($param) { $this->_field_lastlogon = $param; }
  /**
  * Sets password encrypting mode
  *
  * @param mixed $param - true to activate password encrypting
  */
  function setPasswordEncrypting($param) { $this->b_encryptpass = $param; }

  # Activate "auto blocking idle user" feature
  public function setAutoBlockIdle($days=0) { $this->_autoblock_idledays = (int)$days; }

  /**
  * Sets auth.event handlers : for success login, failed login and logout
  *
  * @param array $options
  */
  function setEventFunctions($options) {
      foreach(array('login','login-fail','logout') as $evtid) {
        if(isset($options[$evtid])) $this->_authevents[$evtid] = $options[$evtid];
      }
  }
  function GetCurrentLanguage() {
    return self::$_lang;
  }
  public function setLanguage($lang) {
      self::$_lang = $lang;
  }

  function Init( $handleAuthRequests=true ) {
    global $auth_action, $userlogin, $username, $userpsw, $self,$myregion,$as_dbengine,
      $start_page, $auth_iface,$curlang;
    if (!isset($_SESSION) && isset($_SERVER['REMOTE_ADDR'])) {
        if (!session_id()) @session_start();
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    }
    else {
        if (!empty($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
            session_destroy();
            exit('Your session IP address has changed!');
        }
    }
    if (!$this->db && !empty($as_dbengine) && is_object($as_dbengine)) $this->db =& $as_dbengine;
    if (isset($this->_d['lastlogonfield'])) $this->_field_lastlogon = $this->_d['lastlogonfield'];
    if (isset($this->_d['lastlogon_ip_field'])) $this->_field_lastlogonIp = $this->_d['lastlogon_ip_field'];

    $sqry='***';
    # internationalization: change user language block
    if(empty($curlang)) $curlang = CAuthCorp::GetCurrentLanguage();
    if (is_file(dirname(__FILE__)."/as_auth_lang_$curlang.php")) {
        include(dirname(__FILE__)."/as_auth_lang_$curlang.php");
        if (!empty($GLOBALS['auth_iface']['charset']) && $GLOBALS['auth_iface']['charset'] != MAINCHARSET) {
            mb_convert_variables(MAINCHARSET, $GLOBALS['auth_iface']['charset'], $GLOBALS['auth_iface']);
            $GLOBALS['auth_iface']['charset'] = 'UTF-8';
        }
        if (class_exists('WebApp')) WebApp::appendLocStrings($GLOBALS['auth_iface']);
    }

    if(!empty($_SESSION[$this->_appid.'userid'])) { //<2>
        if(empty($this->userid)) $this->GetMyInfo();
    } //<2>
    if ($handleAuthRequests) $this->handleRequests();
  }

  public function handleRequests() {
    global $userlogin, $username, $userpsw, $self,$myregion,$as_dbengine;
    # if (class_exists('appEnv')) $this->_p = appEnv::$_p;
    $this->_p = decodePostData(1);
    # WriteDebugInfo('handleRequests:', $this->_p);
    $auth_action = isset($this->_p['auth_action'])? $this->_p['auth_action']:'';
    if(!empty($auth_action) && $this->debug) WriteDebugInfo('auth call:', $this->_p);
    if (method_exists($this, $auth_action)) {
        $ret = $this->$auth_action();
        exit(EncodeResponseData( $ret ));
    }
    switch ($auth_action) {
    case 'dologin':
       $result = $this->TryLogin();
       $auth_action = '';
       break;

    case 'become':

       if (!$this->SuperVisorMode()) {
           if (class_exists('WebApp')) { WebApp::echoError(self::ERR_BAD_PARAMS); }
           else exit(self::ERR_BAD_PARAMS);
       }
       $this->_sudo = TRUE;
       $result = $this->TryLogin();
       $auth_action = '';
       if (!$result) {
           if (is_callable('WebApp::addInstantMessage'))
               WebApp::addInstantMessage($this->errormessage);
       }
       break;

    case 'restore_password':
        $forlogin = isset($this->_p['forlogin']) ? $this->_p['forlogin'] : '';
#        $ret = $forlogin;
        $ret = $this->restorePassword($forlogin);
        exit(EncodeResponseData( $ret ));

    case 'getcabinetform':  # AJAX open dialog window for change email/phone/ password
        $ret = $this->getCabinetForm();
        exit(EncodeResponseData( $ret ) );

    case 'saveuserparams':
        $ret = $this->saveUserParams();
        exit(EncodeResponseData( $ret ));

    case 'getchgpswform':
        $ret = $this->getChangePassForm();
        exit(EncodeResponseData( $ret ) );

    case 'save_userpsw':
        $ret = $this->saveNewPassword();
        exit(EncodeResponseData($ret));

    case 'pswmgr_createdbobj':
        $ret = $this->pswmgrCreateDbobj();
        exit(EncodeResponseData($ret));

    case 'accountreset_form':
        $ret = $this->accountResetForm();
        exit(EncodeResponseData($ret));

    case 'accountreset_act':
        $ret = $this->accountResetAct();
        exit(EncodeResponseData($ret));

    case 'logout':
      $this->logOff();
      if (isAjaxCall()) {
        exit('1');
      }
      else {
        if(!isset($start_page)) $start_page = './';
        if (!headers_sent()) @header("Location: $start_page");
      }
      exit;

    case 'getform_biacc_setpwd':
        $ret = $this->getchgBIPasswordsForm();
        exit(EncodeResponseData($ret));

    case 'biacc_updatepwd':
        $ret = $this->saveBIPassword();
        exit(EncodeResponseData($ret));

    case 'user_resetpsw':   # Admin opens dialog for resetting user password and clear "blocked" state
        $ret = $this->startUserReset();
        exit(EncodeResponseData($ret));

    case 'chkemail':
        $ret = $this->checkNewEmail();
        exit(EncodeResponseData($ret));

    }
  }

  public function logOff() {
      if (empty($this->userid)) return;
      if(empty($_SESSION['sso_mode'])) {
          if (!empty($this->_authevents['logout']) && is_callable($this->_authevents['logout'])) @call_user_func($this->_authevents['logout'],$this);
          if (is_callable('webapp::logEvent') && !$this->SuperVisorMode() ) {
              webapp::logEvent('AUTH.LOGOUT',('User logged out: '.$this->firstname.' '.$this->name),$this->deptid,$this->userid);
          }
          @session_destroy();
      }
  }
  /**
  * Adds named right that can act as "user manager" (reset passwords etc.)
  *
  * @param mixed $rlist
  */
  public function addUserManager($rlist='') {
      if (is_string($rlist)) $rlist = explode(',', $rlist);
      if (!is_array($rlist)) return;
      foreach ($rlist as $item) {
          if (!empty($item) && !in_array($item, $this->_usermgrRights)) $this->_usermgrRights[] = $item;
      }
  }

  # Checks if logged user is a "user manager"
  public function isUserManager() {
      if ($this->isSuperAdmin()) return true;
      $ret = $this->getAccessLevel($this->_usermgrRights);
      return $ret;
  }

  /**
  * Admin pressed "reset user password" button...
  * @return html "1|dlg-title|html-code for confirmation dialog" or "error string"
  */
  private function startUserReset() {

      if ( !$this->isUserManager()) return "Access denied !";
      $userid = isset($this->_p['userid']) ? $this->_p['userid'] : 0;
      $dta = $this->db->select($this->_d['table_users'],array('where'=>array($this->_d['usrid']=>$userid),'singlerow'=>1));
      if ($this->db->sql_error()) WriteDebugInfo('error on last qry:', $this->db->getLastQuery());
      if (!isset($dta[$this->_d['usrlogin']])) return 'Account not found or empty ID';
      $fullname = $dta[$this->_d['usrname']] .' '.(isset($this->_d['usrfirstname']) ? $dta[$this->_d['usrfirstname']]:'')
          .' '.(isset($this->_d['usrsecondname']) ? $dta[$this->_d['usrsecondname']]:'');

      $html = "<div id=\"div_auth_doreset\"><form id=\"fm_auth_doreset\"><input type='hidden' name='auth_action' value='admin_resetuserpwd'/>"
        . "<input type='hidden' name='userid' value='$userid'/>";

      $html .= "<input type='text' name='newpasswd' class='form-control form-control-sm d-inline w130 me-2' maxlength='20'/> " . $this->getLocalized('msg_new_password','new password');
      $warn = (is_object($this->_pwdManager)) ? $this->getLocalized('msg_auto_generated_password').'<br>' :'';
      # TODO - move localization strings to separated file
      if ((!empty($this->_d['usrblocked']) && $dta[$this->_d['usrblocked']] == $this->_d['blockedvalue'])) {
          # account is blocked, so create "unblock" checkbox for admin to make desision
          $html .= "&nbsp;&nbsp;<label><input type='checkbox' name='unlockacct' value='1' checked='checked'/> "
                . $this->getLocalized('msg_unblock_account') . "</label><br><br>";
      }
      $html .= "</form>$warn " . $this->getLocalized('msg_perform_action','Perform action') . "?</div>";

      return "1\t" . $this->getLocalized('msg_reset_password','Reset password') . ": $fullname\t".$html;

  }

  # Confirmed user password reset came, do it.
  private function admin_resetuserpwd() {

      if(!$this->isUserManager()) return "Access denied !";
      $userid = isset($this->_p['userid']) ? $this->_p['userid'] : 0;
      $initnewpsw = $newpasswd= !empty($this->_p['newpasswd']) ? trim($this->_p['newpasswd']) : '';
      if (!$newpasswd) {
          if (is_object($this->_pwdManager)) $initnewpsw = $newpasswd = $this->_pwdManager->generatePassword();
          else return $this->getLocalized('msg_enter_nonempty_password','Please enter non-empty password');
      }

      if ($this->b_encryptpass) { # making hash for password
          $newpasswd = $this->encodePassword($newpasswd,$userid);
      }
      $upd = array($this->_d['usrpwd'] => $newpasswd);
      if (is_object($this->_pwdManager)) $this->_pwdManager->SetNewPassword($userid, $newpasswd);


      if (!empty($this->_d['pwdencoded'])) $upd[$this->_d['pwdencoded']] = ($this->b_encryptpass ? 1:0);

      if (!empty($this->_d['changepwdvalue'])) {

          $pwdpolicyid = $this->getUserPswPolicy($userid);
          if ($pwdpolicyid > 0) {
              $plc = $this->applyPswPolicy($pwdpolicyid);
              if ($this->userid == AUTH_DEBUGID) WriteDebugInfo('pwd policy content:', $plc);
              if (!empty($plc['force_new_pwd'])) {
                $upd[$this->_d['usrblocked']] = $this->_d['changepwdvalue'];
#               WriteDebugInfo('Activate [user must change password at next logon]:' . $this->_d['changepwdvalue']);
              }
          }
      }
      $this->db->update($this->_d['table_users'], $upd, array($this->_d['usrid']=>$userid));

      if (!empty($this->_p['unlockacct'])) $this->unblockAccountByTime($userid);
      else $this->_clearLogons($userid);

      $dta = $this->db->select($this->_d['table_users'],array('where'=>array($this->_d['usrid']=>$userid),'singlerow'=>1));
      $email = isset($dta[$this->_d['usremail']]) ? $dta[$this->_d['usremail']] : '';
      if(is_callable('webApp::logEvent')) {
          webApp::logEvent('AUTH.RESET PASSWORD', "Password reset for user $userid",0, $userid);
      }
      $txt1 = $this->getLocalized('msg_notify_user_new_password','Please inform user about new his/her password :');
      $txt2 = $this->getLocalized('msg_new_password_sent_by_email', 'New password has been sent to user.');
      $txt_err = $this->getLocalized('msg_new_password_sending_error', 'Error while sending password occured.');
      $result = $txt1 . $initnewpsw;
      if ($email) {
          if (TRUE === ($sent = $this->sendNotificationPwd($email, $initnewpsw)))
               $result = $txt2;
          else $result = $txt_err . '<br>' . $result;
      }

      return $result;
  }
  /**
  * Generates new password and sends it to the user email
  *
  * @param mixed $login
  */
  public function restorePassword($login) {

      global $auth_iface;
      $login = trim($login);
      $flds = array('id'=>$this->_d['usrid'], 'email'=>$this->_d['usremail'], 'pwd'=>$this->_d['usrpwd']);
      if (!empty($this->_d['pwd_type'])) $flds['pwdtype']=$this->_d['pwd_type'];
      if (!empty($this->_d['usrblocked'])) $flds['blocked']=$this->_d['usrblocked'];

      $data = $this->db->select($this->_d['table_users'],array(
          'fields'=>$flds
         ,'where'=>"({$this->_d['usrlogin']}='$login' OR {$this->_d['usremail']}='$login')"
         ,'singlerow'=>1)
      );
      if (!isset($data['id'])) return $auth_iface['auth_err_restore_password_fail'];
      if (empty($data['email'])) return $auth_iface['auth_err_restore_password_noemail'];
      if (isset($data['blocked']) && $data['blocked'] == $this->_d['blockedvalue']) return $auth_iface['err_youblocked'];

      $pwtype = isset($data['pwdtype'])? $data['pwdtype'] : 0;
#     if ($pwtype == self::PWD_NOCHANGE) $newpass = $data['pwd']; # unchangable password - send current value
#     else {
          if (is_object($this->_pwdManager)) {
              $newpasswd = $this->_pwdManager->generatePassword();
              $this->_pwdManager->SetNewPassword($data['id'], $newpass);
          }
          else $newpasswd = "simple : ".$this->generatePassword();
#     }
#     return "new password: $newpass"; # debug
#      $this->db->log(2);
      $upd = array($this->_d['usrpwd'] => $newpasswd);
      if ($this->b_encryptpass) {
          $upd[$this->_d['usrpwd']] = $this->encodePassword($newpasswd,$data['id']);
          if (!empty($this->_d['pwdencoded']))
            $upd[$this->_d['pwdencoded']] = 1;
      }

      $this->db->update($this->_d['table_users'], $upd, array($this->_d['usrid']=>$data['id']));
      if(is_callable('webApp::logEvent')) {
            webApp::logEvent('AUTH.RESTORE PWD', "Password restored for user $data[id]",0, $data[id]);
      }
      # Now - send Email !!!
      $sent = $this->sendNotificationPwd($data['email'], $newpasswd);
      return $sent;

#      return ('result:'.print_r($data));
  }

  private function sendNotificationPwd($email, $newpass) {

      if (is_callable('webApp::getLocalized')) {
          $subj = $this->getLocalized('title_resetting_password','Resetting Password in Online system');
          $msg = str_replace( '%password%', $newpass
                ,$this->getLocalized('msg_resetted_password','Your password has been changed, new value: %password%'));
      }
      else {
          $subj = 'Resetting Password';
          $msg = str_replace( '%password%', $newpass,'Remember Your new password : %password%');
      }
      if (is_callable('webApp::sendEmailMessage')) {
#          WriteDebugInfo('sending email by webApp::sendEmailMessage');
          $sendoptions = array(
             'to' => $email
            ,'subj'=> $subj
            ,'message' => $msg
          );
          $sent = webApp::sendEmailMessage($sendoptions);
      } else {
          $sent = mail($to,$subj,$msg);
      }

      if ($sent !== TRUE) {
          WriteDebugInfo('auth/restorePassword: sending email error:', $sent );
          $err =  $this->getLocalized('err_restorepwd_sendemail_failed','Sending email to user failed. Please call admin for your new password');
          return $err;
      }

      return true;
  }
  /**
  * Generating email message about changed email, with URL for activating it.
  *
  * @param mixed $email
  * @param mixed $newpass
  */
  private function sendNotificationNewEmail($email) {

      $subj = $this->getLocalized('title_notify_checking_new_email','New email address in Online system');
      $msg = $this->getLocalized('text_checking_new_email','You have changed email, to activate it goto url: %authuri%');
      $hash = $this->encodePassword(strtolower($email), $this->userid);
      $url = (empty($_SERVER['HTTPS'])? 'http://' : 'https://') . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
      $cutpos = strrpos($url,'/');
      if ($cutpos !== false) $url = substr($url,0,$cutpos) . '/';
      $url .= "?auth_action=chkemail&uid={$this->userid}&code=".$hash;
      $msg = str_replace(array('%url%','%code%', "\n"), array($url, $hash, "<br>\n"), $msg);

      if (is_callable('webApp::sendEmailMessage')) {
          $sendoptions = array(
             'to' => $email
            ,'subj'=> $subj
            ,'message' => $msg
          );
          $sent = webApp::sendEmailMessage($sendoptions);
      } else {
          $sent = mail($to,$subj,$msg);
      }

      if ($sent !== TRUE) {
          WriteDebugInfo('auth/restorePassword: sending email error:', $sent );
          $err =  $this->getLocalized('err_sendemail_failed','Sending email to user failed');

          return $err;
      }

      return true;
  }

  # simple password generator
  public function generatePassword($pswlen=10) {
      $ret = '';
      $strSpec = '_-+=!%(){}/.,$#@';
      $strBig = 'QWERTYUIOPASDFGHJKLZXCVBNM';
      $strLow = 'qwertyuiopasdfghjklzxcvbnm';
      $strDigs = '01234567890';
      $order = rand(0,10);
      if ($order <=2)     $ord = array('strLow','strDigs','strSpec','strBig');
      elseif ($order <=6) $ord = array('strBig','strLow','strSpec','strDigs');
      elseif ($order <=8) $ord = array('strDigs','strBig','strLow','strSpec');
      else                $ord = array('strLow','strSpec','strDigs','strBig');

      while (strlen($ret) < $pswlen) {
            foreach($ord as $noff => $orname) {
                  $bllen = ($orname === 'strSpec') ? 1 : 3;
                  for($kk=0; $kk<$bllen; $kk++) {
                  $ioff = rand(0, strlen($$orname)-1);
                        $ret .= substr($$orname, $ioff,1);
                        if (strlen($ret)>= $pswlen) break;
              }
              if (strlen($ret) >= $pswlen) break;
          }
      }
      return $ret;
  }
  /**
  * client requested HTML code for "user cabinet" dialog
  *
  */
  public function getCabinetForm() {

      if (empty($this->userid)) die ('not logged user');
      $dta = $this->db->select($this->_d['table_users'],array('where'=>array($this->_d['usrid']=>$this->userid),'singlerow'=>1));
      $html = '<div id="div_auth_cabinet"><form id="auth_cabinet"><table>';
      if(!empty($this->_d['usrphone'])) {

          $val = $dta[$this->_d['usrphone']];
          $html .= "<tr><td class='rt'>".$this->getLocalized('usr_phone')."</td><td><input type='text' name=\"myphone\" id=\"myphone\" class=\"ibox\" style=\"width:300px\" value=\"$val\" maxlength=\"20\"/></td></tr>";
        # {$this->_d['usrphone']}
      }
      if(!empty($this->_d['usremail'])) {
          $val = $dta[$this->_d['usremail']];
          $html .= "<tr><td class='rt'>".$this->getLocalized('reg_usremail')."</td><td><input type='text' name=\"myemail\" id=\"auth_myemail\" class=\"ibox\" style=\"width:300px\" value=\"$val\" maxlength=\"80\"/></td></tr>";
      }
      if (!empty($this->_d['usrnewemail'])) {
          $val = $dta[$this->_d['usrnewemail']];
          $attrib = ($val == '') ? " style='display:none'":'';
          $html .=
           "<tr id='auth_cabinet_checkemail' {$attrib}><td class='rt'>".$this->getLocalized('title_check_mail_code')."</td><td><input type='text' name=\"auth_chkemailcode\" id=\"auth_chkemailcode\" class=\"ibox\" style=\"width:300px\" maxlength=\"80\"/> "
            ."&nbsp;<input type='button' class='btn btn-primary' value='OK' onclick='waAuth.activateNewEmail()'/></td></tr>";
      }
      $html .= '</table></form><div class="bounded" style="display:none; max-height:60px; overflow:auto" id="auth_cabinet_result" /></div>';
      return $html;
  }

  private function getChangePassForm() {

      $plc = $this->applyPswPolicy();
      $row_old_psw  = '';
      if (!empty($this->_options['enter_old_pwd']))
          $row_old_psw = '<tr><td class="rt">' . $this->getLocalized('title_old_password') . '</td>'
            . '<td><input type="password" name="auth_oldpass" id="auth_oldpass" class="ibox" style="width:160px" maxlength="30" autocomplete="off"/></td></tr>';

      $title1 = $this->getLocalized('title_new_password');
      $title2 = $this->getLocalized('title_new_password2');
      $html = <<< EOHTM
<div id="div_auth_password"><table>
$row_old_psw
<tr><td class='rt'>$title1</td><td><input type='password' name="auth_newpass1" id="auth_newpass1" class="ibox" style="width:160px" maxlength="30"/></td></tr>
<tr><td class='rt'>$title2</td><td><input type='password' name="auth_newpass2" id="auth_newpass2" class="ibox" style="width:160px" maxlength="30"/></td></tr>
</table></div>
EOHTM;
      return $html;
  }

  /**
  * Checking entered new password and save it if is OK.
  */
  private function saveNewPassword() {
#        WriteDebugInfo('saveNewPassword _p:', $this->_p);
        $userid = isset($this->userid)? $this->userid : '';
        if(empty($userid)) {
            if($this->debug) WriteDebugInfo('saveNewPassword exit as no-logged user:(');
            return $this->getLocalized('err_you_not_logged');
        }

        $this->getMyInfo();
        $plc = $this->applyPswPolicy(); # sets $this->_options['enter_old_pwd'] from pwd policy

/*        WriteDebugInfo('saveNewPassword _p:', $this->_p);
        WriteDebugInfo($this->userid . ': password policy: ', $plc);
        WriteDebugInfo('$this->_options:', $this->_options);
*/
        $oldpsw = $entered_oldpsw = isset($this->_p['auth_oldpass']) ? $this->_p['auth_oldpass'] : '';
        $dta = $this->db->select($this->_d['table_users'],
             array('where'=>array($this->_d['usrid']=>$this->userid)
               ,'singlerow'=>1
             )
        );

        $curr_psw = $dta[$this->_d['usrpwd']];

        if (!empty($this->_options['enter_old_pwd'])) {
            if (!empty($this->_d['pwdencoded']) && !empty($dta[$this->_d['pwdencoded']])) {
                $oldpsw = $this->encodePassword($oldpsw, $this->userid);
            }
            if ($oldpsw !== $curr_psw) {
                return $this->getLocalized('pdwerr_wrong_old_password');
            }
        }

        $psw = isset($this->_p['auth_newpass1']) ? $this->_p['auth_newpass1'] : '';
        $psw2 = isset($this->_p['auth_newpass2']) ? $this->_p['auth_newpass2'] : '';
        if(empty($psw)) return $this->getLocalized('pdwerr_emptypassword');
        if($psw !== $psw2) return $this->getLocalized('pwderr_password_mismatch');
        $result = 1;
        if(is_object($this->_pwdManager) && !self::IsBuiltinAccount()) {

            $this->applyPswPolicy();

            $chkHist = $this->_pwdManager->getReq('histlength'); // whether check or not if password already was used (in psw.history)
            $encodedPsw = FALSE;
            if ($this->b_encryptpass) { # will check in history for both - plane and encrypted psw
                  $encodedPsw = $this->encodePassword($psw,$this->userid);
            }

            $result = $this->_pwdManager->checkPassword($psw, $this->userid,$chkHist, $encodedPsw);
            if(!$result) {
/*                $errstr = $this->getLocalized('pwderr_'.$this->_pwdManager->GetErrorCode());
                if (!$errstr) */
                $errstr = $this->_pwdManager->verboseError();
                return $errstr;
            }
            $oknew = $this->_pwdManager->setNewPassword($this->userid, (($encodedPsw) ? $encodedPsw:$psw)); // store new password in psw.history
            if ($oknew) {
                unset($_SESSION[$this->_appid.'auth_remind_change_password']);
                $_SESSION[$this->_appid.'logon_mode'] = self::STATE_LOGGED;
                if (!empty($this->_d['changepwdvalue']) && $dta[$this->_d['usrblocked']] == $this->_d['changepwdvalue']) {
                    $this->db->update($this->_d['table_users'],
                       array($this->_d['usrblocked'] => '0'),
                       array($this->_d['usrid'] => $userid)
                    );
                }

            }
            if(!$oknew) WriteDebugInfo('Error saving password in history:', $this->_pwdManager->GetErrorCode()
                ,' sql:', $this->db->getLastQuery(), 'SQL err:',$this->db->sql_error()
            );
        }

        if(isset(self::$_builtin_accounts[$userid])) {
            $ret = $this->updateBuiltinPassword($userid, $psw);
        }
        else {

          if( $this->b_encryptpass ) {
              $psw = $this->encodePassword($psw, $userid);
          }

          $updpsw = array($this->_d['usrpwd'] => $psw);
          if (!empty($this->_d['pwdencoded'])) $updpsw[$this->_d['pwdencoded']] = ($this->b_encryptpass ? 1:0);

          $result = $this->db->update($this->_d['table_users'], $updpsw, array($this->_d['usrid']=>$userid));

          if (is_callable('webapp::logEvent')) webApp::logEvent('AUTH.PSW CHANGE', $stext='Password changed by user', 0, $userid);
          if($result) $ret="1\t".$this->getLocalized('password_changed');
          else $ret = 'Database ERROR: '.$this->db->sql_error();
        }
        return $ret;
  }

  /**
  * Applies password policy with passed policy ID
  *
  * @param mixed $pol_id password policy ID
  */
  public function applyPswPolicy($pol_id = FALSE, $userid=0) {

      if (empty($this->_d['table_pswpolicies'])) return;
      if (!is_object($this->_pwdManager)) return;
      if (!$pol_id) $pol_id = $this->getUserPswPolicy($userid);

      if (!$pol_id) return 0;
      $pwp = $this->db->select($this->_d['table_pswpolicies'], array('where'=>array('recid'=>$pol_id), 'singlerow'=>1));
      if (isset($pwp['enter_old_pwd'])) $this->_options['enter_old_pwd'] = $pwp['enter_old_pwd'];

      $expiredPsw = isset($_SESSION[$this->_appid.'logon_mode']) ?
        ($_SESSION[$this->_appid.'logon_mode']==self::STATE_PWD_EXPIRED) : 0;

      if (isset($pwp['psw_minlen'])) {
          $this->_autoblock_idledays = $pwp['block_idleusers'];
          $this->_pwdManager->SetStrengthReqs(array(
            'minlength' => $pwp['psw_minlen']
           ,'maxlength' => $pwp['psw_maxlen']
           ,'mingroups' => $pwp['psw_mingroups']
           ,'specchars' => !empty($pwp['psw_specchars'])
           ,'digits' => !empty($pwp['psw_digits'])
           ,'letters' => !empty($pwp['psw_letters'])
           ,'lowchars' => !empty($pwp['psw_lowchars'])
           ,'upperchars' => !empty($pwp['psw_upperchars'])
           ,'histlength' => $pwp['psw_histlength']
           ,'mindays' => ($expiredPsw ? 0 : $pwp['psw_mindays']) # ignore min_days if password expired
           ,'maxdays' => $pwp['psw_maxdays']
           ,'days_remind' => $pwp['psw_expire_remind']
           ,'password_maxtries' => $pwp['psw_minlen']
           ,'block_idleusers' => $pwp['block_idleusers']
           ,'force_new_pwd' => !empty($pwp['force_new_pwd'])
          ));
      }
      return $pwp;
#      WriteDebugInfo($pol_id . ' - psw policy applied');
  }

  public function saveUserParams() {

      $this->getMyInfo();
      if(empty($this->userid)) {
          WriteDebugInfo('saveUserParams: Internal error(userid is undefined)');
          exit('Internal error');
      }

      $ret = '1';
      $p = decodePostData(1); # $this->_p !
      $dt = array();
      if(!empty($this->_d['usrphone'])) $dt[$this->_d['usrphone']] = isset($p['myphone']) ? trim($p['myphone']) : '';
      if(!empty($this->_d['usremail'])) {
          $email = trim($p['myemail']);
          if (!empty($email)) {
              if (!isValidEmail($email)) {
                  $ret = $this->getLocalized('err_non_valid_email');
                  exit (encodeResponseData($ret));
              }
              if (!empty($this->_d['usrnewemail']) && (strtolower($this->email) !== strtolower($email))) { // save new email as "unchecked" new email
                  $dt[$this->_d['usrnewemail']] = $email;
                  # build and send email message to new address
                  $sent = $this->sendNotificationNewEmail($email);
                  if ($sent == 1) $ret = 'warn|'.$this->getLocalized('msg_email_to_verify');
                  else $ret = $sent; # email send failed, show this error to user
    #              exit (encodeResponseData($ret)); // debug
              }
              else {
                $dt[$this->_d['usremail']] = $email;
              }
          }
      }
      if(count($dt)) {
          $result = $this->db->update($this->_d['table_users'],$dt,array($this->_d['usrid'] => $this->userid));
          if(!$result) $ret = 'Error:' . $this->db->sql_error();
      }
      return $ret;
  }
  /**
  * Checking hash code and activating new email address for account
  *
  */
  private function checkNewEmail() {
      $hash = isset($this->_p['code']) ? trim($this->_p['code']) : '';
      $userid = $this->userid;
      if (!intval($userid)) $userid = isset($this->_p['uid']) ? trim($this->_p['uid']) : '';
      if (!$userid) die ('wrong call, no user id');
      if (!$hash) die ('wrong call, no hash code passed');
      $dta = $this->db->select($this->_d['table_users'],array('where'=>array($this->_d['usrid']=>$userid),'singlerow'=>1));
      $newEmail = $dta[$this->_d['usrnewemail']];
      $goodHash = $this->encodePassword(strtolower($newEmail), $userid);
      if ( $hash === $goodHash ) {
          $this->db->update($this->_d['table_users']
            , array($this->_d['usremail'] => $newEmail, $this->_d['usrnewemail']=>'')
            , array($this->_d['usrid'] => $userid)
          );
          $ret = "1\t" . $this->getLocalized('msg_check_email_done');
          if(is_callable('webApp::logEvent')) {
              webApp::logEvent('AUTH.CHANGE EMAIL', "new Email address activated: $newEmail",0, $userid);
          }

      }
      else {
          $ret = $this->getLocalized('err_check_email_wrong_code');
          WriteDebugInfo("good sha for $userid/$newEmail: $goodHash, wrong hash: $hash");
      }
      return $ret;
  }

  /**
  *  Creates HTML form for changing built-in account's passwords
  */
  public function getchgBIPasswordsForm() {

      if (!$this->SuperVisorMode()) return ('error!');
      $html = '<div id="div_auth_chgbipwd" class="ct"><table><tr><td>Account:</td><td>';
      $html .= "<select name='auth_biaccount' id='auth_bi_account' style='width:200px'>";
      foreach(self::$_builtin_accounts as $accid => $bi) {
          $html .= "<option value='$accid'>$accid / $bi[name]</option>";
      }
      $html .= '</select></td></tr>'
            . '<tr><td>New password:</td><td><input type="text" name="auth_bipass" id="auth_bipass" class="ibox w100 ct" /></td></tr>'
            . '</table></div>';
      return $html;
  }

  # updates or clears file with encoded password for "built-in" account
  private function updateBuiltinPassword($userid, $psw='') {

    $fname = self::$_pwd_folder ."_psw_{$userid}.pwi";
    $result = ($psw==='') ? (@unlink($fname) or 1) : @file_put_contents($fname,$this->encodePassword($psw,$userid));
    if($result) $ret="1\t".$this->getLocalized('password_changed');
    else $ret = $fname.' - '.$this->getLocalized('password_errwritefile');
    return $ret;
  }

  public function saveBIPassword() {

      if($this->SuperVisorMode()) {
          $p = decodePostData(1);
          $login = isset($p['bi_user']) ? trim($p['bi_user']) : '';
          $passwd = !empty($p['bi_pwd']) ? trim($p['bi_pwd']) : '';
          if(!empty($login)) {
              $ret = $this->updateBuiltinPassword($login, $passwd);
              return $ret;
          }
          else return 'Undefined or empty user id!';
      }
      else return 'Operation denied for You !';
  }

  public function SetBuffered($par = true) { $this->b_buffered = $par; }

  private function pswmgrCreateDbobj() {
     return 'TODO: create Password mgmt tables...';
  }

  // AJAX request for "User account operations" dialog window
  private function accountResetForm() {

      $usrid = isset($this->_p['userid']) ? $this->_p['userid'] : '';
      $acthtml = '';
      if (!empty($this->_d['usrblocked'])) {
          $curst = $this->db->select($this->_d['table_users'],array('fields'=>$this->_d['usrblocked'],'where'=>array($this->_d['usrid'] => $usrid)
          , 'associative'=>0));
          $actattr = empty($curst[0][0]) ? 'disabled="disabled"' : '';
          $acthtml = "<input type='checkbox' name='auth_activateaccnt' id=\"auth_activateaccnt\" value='1' $actattr/><label for='auth_activateaccnt'>" . $this->getLocalized('Activate account'). '</label><br>';
#          WriteDebugInfo("blocked result for userid $usrid",$curst);
      }
      $usrName = $this->GetUserNameForId($usrid);
      $ret = $this->getLocalized('User account').' : ' . "$usrName\t<div id=\"div_auth_accountreset\"><input type='checkbox' name='auth_resetpwd' id=\"auth_resetpwd\" value='1' /><label for='auth_resetpwd'>"
            . $this->getLocalized('Reset password') . '</label><br>'
            . "<input type='hidden' name='auth_acct_userid' id='auth_acct_userid' value='$usrid' />";
      $ret .= $acthtml;
      $ret .= '<div id="auth_accopts_result" class="bordered" style="padding:0.1em 0.5em;">&nbsp;</div>';
      $ret .= '</div>';

      return $ret;
  }
  public function setProtectedLogin($mode = TRUE) {
      $this->_protectLogin = $mode;
  }
  /**
  * Performs action - resets password and/or unblocks blocked account
  *
  */
  private function accountResetAct() {

      if ($this->debug) WriteDebugInfo('accountResetAct, params:', $this->_p);
      if (!$this->isUserManager()) {
          if (is_callable('webApp::echoError')) webApp::echoError('err-no-rights');
          else exit('No rights!');
      }
      $userid = isset($this->_p['userid']) ? $this->_p['userid'] : 0;
      if(!$userid) return 'ERR:userid empty!';
      $b_resetpsw = !empty($this->_p['resetpwd']);
      $b_activate = !empty($this->_p['activateacct']);
      $ret = array();
      $updt = array();
      $stext = "Account [$userid] : ";
      if ($b_activate && !empty($this->_d['usrblocked'])) {
          $updt[$this->_d['usrblocked']] = '0';
          if (!empty($this->_d['blkreason'])) $updt[$this->_d['blkreason']] = 0;
          $ret[] = $this->getLocalized('Account activated'); # Учетка активирована';
          $stext = 'activated';
      }
      if ($b_resetpsw) {
          $newpass = $encodedPsw = false;
          if($this->_pwdManager) $newpass = $this->_pwdManager->generatePassword();

          if($newpass) {

              $updt[$this->_d['usrpwd']] = $newpass;
              if(!empty($this->_d['pwdencoded'])) $updt[$this->_d['pwdencoded']] = 0;
              if ($this->b_encryptpass) {
                  $updt[$this->_d['usrpwd']] = $encodedPsw = $this->encodePassword($newpass, $userid);
                  if(!empty($this->_d['pwdencoded'])) $updt[$this->_d['pwdencoded']] = 1;
              }
              $this->_pwdManager->setNewPassword($userid, (($encodedPsw) ? $encodedPsw:$newpass));
#              WriteDebugInfo('$this->_d[changepwdvalue]=['.$this->_d['changepwdvalue'] . ']');
              if (!empty($this->_d['changepwdvalue'])) {
                  # according to password policy set flag "user must change pwd at first logon"
                  $pwdpolicyid = $this->getUserPswPolicy($userid);
                  if ($this->userid == AUTH_DEBUGID) WriteDebugInfo($userid . ': user psw policy ID:', $pwdpolicyid);
                  if ($pwdpolicyid > 0) {
                      $plc = $this->applyPswPolicy($pwdpolicyid);
                      WriteDebugInfo('policy data:', $plc);
                      if (!empty($plc['force_new_pwd'])) {
                        $updt[$this->_d['usrblocked']] = $this->_d['changepwdvalue'];
                        WriteDebugInfo('Activate [user must change password at next logon]:' . $this->_d['changepwdvalue']);
                      }
                  }
              }

              if (!empty($this->_d['blkreason'])) $updt[$this->_d['blkreason']] = 0; # reset block reason ID

              $ret[] = $this->getLocalized('Tell user password') . ' : '.$newpass . ' ';
              $stext.= ($stext ? ', ':'').'Password reset';
          }
      }
      if (count($updt)) {
          $this->db->update($this->_d['table_users'], $updt,array($this->_d['usrid'] => $userid));
          if (is_callable('webApp::logEvent'))
            webApp::logEvent('AUTH.ACCOUNT RESET', 'Account reset for user '.$userid, '', 0, $userid);

      }
      return implode('<br>',$ret);
  }
  function isUserLogged() {
    $ret = (isset($_SESSION[$this->_appid.'userid']) && !empty($_SESSION[$this->_appid.'userid']));
    if($ret) {
      if(is_object($this->_pwdManager) && isset($_SESSION[$this->_appid.'logon_mode'])) {
          $ret = $_SESSION[$this->_appid.'logon_mode'];
      }
    }

    if(!$ret && ($this->_use_sso)) {
        $sso = $this->getSSOuser();
        if(!empty($sso)) {
            $this->TrySSOLogin($sso);
            $ret = (!empty($_SESSION[$this->_appid.'userid']));
        }
    }

    return $ret;
  }

  function SuperVisorMode() { # return s true for logged built-in supervisor account OR having 'supervisor' right

      if (isset($_SESSION[$this->_appid.'userid']) && $_SESSION[$this->_appid.'userid']===SUPERNAME)
          return true;

      if (self::$right_supervisor) {
          $ret = !empty($this->a_rights[self::$right_supervisor]);
          return $ret;
      }
      else {
          if (isset($_SESSION[$this->_appid.'userid']) && $_SESSION[$this->_appid.'userid']===SUPERNAME)
              return true;
      }
      return false; # ($this->getAccessLevel(self::AUTH_RIGHT_SUPEVISOR));
  }
  function isSuperAdmin() { # return TRUE for logged supervisor and superadmin

      if($this->IsUserLogged()) {
          if($this->SupervisorMode()) return true;
          # WriteDebugInfo("check super admin for right:", self::AUTH_RIGHT_SUPEADMIN);
          return (isset($this->a_rights[self::AUTH_RIGHT_SUPEADMIN]) ? $this->a_rights[self::AUTH_RIGHT_SUPEADMIN]:0);
      }
      return false;
  }
  function EnableUserCabinet($par=true) { $this->b_cabinet = $par; }
  /**
  * Generates "log in" mini-form (user unlogged) / or current user short info (if logged already),
  * plus javascript functions needed by auth
  * @param mixed $width
  * @param mixed $usrprofile
  */
  public function Auth_InlineForm($width=200,$usrprofile=null) {

    global $self, $b_auth_drawn,$auth_iface;
    if($usrprofile!==null) $this->b_cabinet = $usrprofile;
    if(empty($this->_logonmode)) $width='99.8%';
    $b_auth_drawn= true;
    $lng = self::GetCurrentLanguage();
    $ret = '';
    $ta = explode('-',date('Y-m-d-H-i-s'));
    $expired = 'false';
    $clogout = $this->_confirm_logout ? 'true':'false';
    $logout_strg = $this->getLocalized('confirm_logout','confirm logout');

    if(isset($_SESSION[$this->_appid.'logon_mode']))
        $expired = ($_SESSION[$this->_appid.'logon_mode']==self::STATE_PWD_EXPIRED) ? 'true':'false';

    $msg_004 = $this->getLocalized('title_reset_password','Reset password');
    $ret .= <<< EOJS
<script type='text/javascript'>
var auth_confirm_logout = $clogout;
var asSrvTime = [{$ta[0]},{$ta[1]},{$ta[2]},{$ta[3]},{$ta[4]},{$ta[5]}];
var authDlg1 = null;
msg = [];
msg['tilte_reset_psw'] = '$msg_004';
waAuth = {
  operId : 0
 ,expiredPsw: $expired
 ,invalues: {}
 ,fmRestorePwd : function() {
    var rhtml = '<div id="div_auth_restorepwd"><table><tr><td>$auth_iface[auth_label_your_email_login]</td><td><input class="ibox w200" name="auth_restlogin" id="auth_restlogin"/></td></tr></table></div>';
    authDlg1 = $( rhtml ).dialog( {
        dialogClass:'div_outline'
       ,width:460
       ,resizable: false
       ,modal: true
       ,title:'$auth_iface[title_restore_password]'
       ,buttons: [
          { text: 'OK', click: waAuth.authSendRestorePwd }
        ]
    }).on( "dialogclose", function( event, ui ) { authDlg1.dialog('destroy').remove(); authDlg1=null; } );

  }
 ,authSendRestorePwd: function() {
    var pinput = $('#auth_restlogin').val();
    if (pinput === '') return;
    var params = { auth_action:'restore_password','forlogin':pinput};
    $.post('./', params, function(data) {
        if(data === '1') {
          if (authDlg1) {
            authDlg1.dialog('destroy').remove();
            authDlg1 = null;
            TimeAlert('$auth_iface[auth_msg_newpassord_sent]',4);
          }
        }
        else alert(data);
    });
  }
  ,collect: function(sel) {
     waAuth.invalues = {};
     $('input', sel).each(function(){ waAuth.invalues[this.name] = $(this).val(); });
  }
  ,dlgResetPassword : function (userid) {
    if (parseInt(userid)<=0) return;
    this.operId = parseInt(userid);
    $('#div_auth_doreset').remove();
    $.post('./?auth_action=user_resetpsw', {userid:this.operId}, function(data){
       var dsplit = data.split("\t");
       if (dsplit[0]!=='1') { showMessage('Error',data, 'msg_error'); return; }
       else {
           var dopts = {
              title: dsplit[1]
             ,text : dsplit[2]
           }
           dlgConfirm(dopts, function() {
              var params = $('#fm_auth_doreset').serialize();
              $.post('./', params, function(data) {
                  /* if (data !== '1') */
                  showMessage(msg['tilte_reset_psw'],data);
              });
           });
       }
    } );

  }
  ,doLogout: function() { window.location = "./?auth_action=logout"; }
  ,startLogout: function() {
    if (typeof(jQuery)=='undefined') waAuth.doLogout();
    var doLogout = true;
    if (auth_confirm_logout) {
      dlgConfirm({
         closeOnEscape: true
        ,text: '$logout_strg'
      }, waAuth.doLogout, null);
    }
    else waAuth.doLogout();
  }
  ,openCabinet: function () {
    $.get( "./?auth_action=getcabinetform", function( data ) {
        $( data ).dialog( {
            dialogClass:'div_outline'
           ,width:560
           ,resizable: false
           ,modal: true
           ,title:'$auth_iface[title_editprofile]'
           ,buttons: [
              { text: '$auth_iface[btn_savedata]', click: waAuth.authSendUserParams }
            ]
        });
    });
  }
  ,openChgPassw: function(sModal) {
    $.get( "./?auth_action=getchgpswform", function( data ) {
//        alert(data);
        authDlg1 = $( data ).dialog( {
            dialogClass:'div_outline'
           ,width:400
           ,resizable: false
           ,closeOnEscape: (!waAuth.expiredPsw)
           ,beforeclose: (waAuth.expiredPsw ? function (){ return false;} : null ) // block closing if password expired
           ,modal: true // !!sModal
           ,title: (waAuth.expiredPsw ? '$auth_iface[title_changeexpiredpass]' : '$auth_iface[title_changepass]')
           ,buttons: [
              { text: '$auth_iface[btn_savepassword]', click: waAuth.authSendPswParams }
            ]
        }).on( "dialogclose", function( event, ui ) { authDlg1.dialog('destroy').remove();authDlg1=null; } );
    });
  }
  ,authSendUserParams: function() {
    var params = $('#auth_cabinet').serialize(); //{ 'auth_myphone':$('#auth_myphone').val(),'auth_myemail':$('#auth_myemail').val(),auth_action:'saveuserparams'};
    $.post('./?auth_action=saveuserparams', params, function(data) {
        splt = data.split("|");
        if(splt[0] === '1') {
           $('#div_auth_cabinet').remove();
           if (splt[1]) showMessage('OK',splt[1], 'floatwnd');
        }
        else if (splt[0] === 'warn') {
           $('#div_auth_cabinet').remove();
           showMessage('Attention!',splt[1], 'floatwnd');
        }
        else $('#auth_cabinet_result').html(data).show();
    });
  }
  ,activateNewEmail: function() {
     var ecode = $("#auth_chkemailcode").val();
     if (ecode === '') {
        return false;
     }
     var params = { auth_action: 'chkemail', code: ecode};
     $.post('./', params, function(data) {
        var sp = data.split("\t");
        if (sp[0] === '1') {
           $('input#auth_myemail').val('');
           $('#auth_cabinet_result').html(sp[1]).slideDown(150);
           $('#auth_cabinet_checkemail').slideUp(150);
        }
        else $('#auth_cabinet_result').html(data).slideDown(150);
     });
  }
  ,authSendPswParams : function() {
     var params = { 'auth_oldpass':$('#auth_oldpass').val()
        ,'auth_newpass1':$('#auth_newpass1').val()
        ,'auth_newpass2':$('#auth_newpass2').val()
        ,auth_action:'save_userpsw'
     };
     $.post('./', params, function(data) {
        var sp = data.split("\t");
        if(sp[0] === '1') {
            if(waAuth.expiredPsw) window.location.reload(true);
            if (authDlg1) authDlg1.dialog('destroy').remove();
            $('#auth_password_reminder').text('');
            TimeAlert(sp[1],2);
        }
        else TimeAlert(data,3,'msg_error');
    });
  }

};
EOJS;
    if(defined('LOGBROWSERINFO')) $ret .= "var navn = navigator.appName, navv = navigator.appVersion;\n";

    if($this->SuperVisorMode()) {
        $ret .= <<< EOJS
var biaccDlg = null;
waAuth.chgBIPasswordsForm = function () {
    $.get( "./?auth_action=getform_biacc_setpwd", function( data ) {
        biaccDlg = $( data ).dialog( {
            dialogClass:'div_outline'
           ,width:400
           ,resizable: false
           ,modal: true
           ,title:'$auth_iface[title_change_builtn_password]'
           ,buttons: [
              { text: '$auth_iface[btn_savedata]', click: waAuth.sendChgBiaccPsw}
            ]
        });
    });

};
waAuth.sendChgBiaccPsw = function() {
    var params = {auth_action:'biacc_updatepwd', 'bi_user': $('#auth_bi_account').val(), 'bi_pwd': $('#auth_bipass').val()};
    $.post('./', params, function(data) {
      var sp = data.split('\\t');
      if(sp[0] === '1') TimeAlert(sp[1],2);
      else TimeAlert(data,4,'msg_error');
    });
};
EOJS;

    }
    # if(!empty($this->b_authajax)) {
       $ret .= "  // scripts for AJAX authentication\n";
       $errtitle = $this->getLocalized('title_error');

       $ret .= <<<EOJS
function asAuthSendRequest(mode) {
    var curLogin = $('input#userlogin').val();
    if(curLogin === '') return false;
    var params = $("#auth_login").serialize();
    $.post('$self', params, function(data){
      var sarr=data.split("|");
      if(sarr[0]==="1" || sarr[0]==="10") {
        if (typeof localStorage === 'object') localStorage['userlogin'] = $('input#userlogin').val();
        window.location.reload(false);
        return;
      }
      else {
         $('input#userpwd').val('');
         showMessage('$errtitle',sarr[1], 'msg_error');
         return false;
      }
    });
}

EOJS;

    # }
    if($this->_showtime) $ret .= "
var authTimeHan = null;
function authActivateTimeRefresh() {
  authTimeHan = setInterval(authTimeRefresh,60000);
}
// call authTimeRefresh() every minute, to keep 'rendered' time equal to server
function authTimeRefresh() {
    asSrvTime[4]++;
    if(asSrvTime[4]>59) {
       asSrvTime[4]=0;
       asSrvTime[3]++;
       if(asSrvTime[3]>23) {
          asSrvTime[3] = 0;
          var vDate = new Date(asSrvTime[0],(asSrvTime[1]-1),asSrvTime[2],23,59,59); // Date: month 0-based
          vDate = new Date(vDate.getTime()+ 2000000); // get next day
          asSrvTime[0] = vDate.getFullYear();
          asSrvTime[1] = 1 + vDate.getMonth();
          asSrvTime[2] = vDate.getDate();
       }
    }
    var newtxt = (asSrvTime[2]<10?'0':'')+asSrvTime[2]+'.'+(asSrvTime[1]<10?'0':'')+asSrvTime[1]+'.'+
      asSrvTime[0]+' '+(asSrvTime[3]<10?'0':'')+asSrvTime[3]+':'+(asSrvTime[4]<10?'0':'')+asSrvTime[4];
    jQuery('#auth_srvtime').text(newtxt);
}
";

    $ret .= "\nif(waAuth.expiredPsw) $(document).ready(waAuth.openChgPassw);\n";
    $ret .="</script>\n";

    if (!$this->isUserLogged()) {
      # protect from "Bot authentication":
      $ret .="<form name=\"auth_login\" id=\"auth_login\">";
      $ret .="<input type=\"hidden\" name=\"auth_action\" id=\"auth_action\" value=\"dologin\" />\n";
      if ($this->_protectLogin) {
        $tokenStr = $_SESSION['login_token'] = 'tkin' . rand(10000000,99999999);
        $ret .="<input type=\"hidden\" name=\"auth_token\" id=\"auth_token\" value=\"$tokenStr\" />\n";
      }
    }
    if($this->IsUserLogged() || empty($this->_hideloggedinfo)) {
      $ret .="<div class='div_authblock noprint' style='width:$width; text-align:left;'><table border=0 cellspacing='0' cellpadding='1'>"
       . ($this->_logonmode ? '':'<tr>');
    }
    $tr1 = ($this->_logonmode) ? '<tr class="auth_info">':'';
    $tr0 = ($this->_logonmode) ? '</tr>':'';
    if($this->IsUserLogged()) { #<2>
      if(empty($this->_hideloggedinfo)) { #<3>
          $this->GetMyInfo();
          $dptname='';
          $vmoda = $this->AccessLevelVerbose();
          if(!empty($this->_d['table_depts']) && !empty($this->deptid)) {
            $dptname = $this->deptname; # GetFullDeptName($this->deptid);
          }
          if(!empty($this->_d['rgn_code']) && !empty($this->_d['dpt_zoneid']) && !empty($this->zoneid)) {
              $rgn = $this->db->GetQueryResult($this->_d['table_regions'],"{$this->_d['rgn_code']},{$this->_d['rgn_name']}",$this->_d['rgn_id']."='{$this->zoneid}'");
              if(!empty($rgn[1])) $dptname = $rgn[0].':'.$rgn[1].($dptname?' / ':'').$dptname;
          }
          if(!empty($_SESSION['debug'])) $usrname .= '(debug)';
          $usrscreen='';
          if(!empty($this->b_cabinet) && !$this->IsBuiltinAccount() ) {
              $usrscreen .= " &nbsp;<a href=\"javascript:void(0)\" onclick=\"waAuth.openCabinet()\">"
                . $this->getLocalized('link_editprofile'). '</a>';
          }
          if($this->isChangablePassword()) {
              $usrscreen .= " &nbsp; <a href=\"javascript:void(0)\" onclick=\"waAuth.openChgPassw(0)\">"
              . $this->getLocalized('title_setpwd'). '</a>&nbsp; ';
          }

          if($this->SuperVisorMode()) { # Add a link for openiong Supervisor optoins dialog
              $usrscreen .= " &nbsp;<a href=\"javascript:void\" onclick=\"waAuth.chgBIPasswordsForm()\">B/I accounts</a> &nbsp;";
          }
          $logout = empty($_SESSION['sso_mode']) ? "<a href='javascript:void(0)' onclick='waAuth.startLogout()'>" . ($this->getLocalized('title_logout')). "</a>\n" : '';

          $reminder = $lastlogon = '';

          if( isset($_SESSION[$this->_appid.'logon_mode']) and $_SESSION[$this->_appid.'logon_mode'] === self::STATE_PWD_EXPIRED) {
             $reminder = '<td id="auth_password_reminder">'
                . $this->getLocalized('auth_password_expired')
                .'</td>';
          }
          else {
              if(!empty($_SESSION[$this->_appid.'auth_remind_change_password'])) {
                  $reminder = '<td id="auth_password_reminder">'
                    . str_replace('%days%', $_SESSION[$this->_appid.'auth_remind_change_password'], $this->getLocalized('auth_password_expire_remind'))
                    . '</td>';
              }
              ## раскомментировать если надо выводить дату предыдущего входа:
              ## $lastlogon = $this->GetLastLogonTime();
              ## if(intval($lastlogon)>0) $lastlogon = $tr1 . '<td id="lastlogoninfo">' . $this->getLocalized('auth_lastlogon') . ' '. $lastlogon . '</td>'.$tr0;
          }

          $ret .="$tr1<td nowrap='nowrap' class='auth_info'>{$this->name} {$this->firstname}</td>$tr0\n";
          $date = date('d.m.Y'); $time = date('H:i');

          if(!empty($dptname)) $ret .="$tr1<td colspan=2 class='auth_info' nowrap>, $dptname</td>{$tr0}\n";
          $ret .="$tr1<td class='auth_info' nowrap>$usrscreen</td>{$tr0}{$tr1}<td class='auth_info' nowrap='nowrap'>$logout</td>$reminder<td style='width:90%'>&nbsp;</td>$lastlogon\n";
      } #<3>
    } #<2>
    else { #<2> user not logged yet

      $usrlogin = '';
      if(!empty($_GET['login'])) $usrlogin = trim($_GET['login']);
      if(!isset($mypwd)) $mypwd = '';
      if(empty($urldest)) $urldest = $self;
      $class_auth_info = ($this->_use_uithemes)? '.ui-widget-content' : 'auth_info'; # UI themes - TODO !!!
      $class_btns = ($this->_use_uithemes)? 'ui-widget btn btn-primary' : 'btn btn-primary';
      $class_auth_input = ($this->_use_uithemes)? 'ui-widget input' : 'auth_input';

      $ret .= $tr1 .($this->_authalign=='right'? '<td width="90%">&nbsp;</td>':'')."<td align='right' nowrap class='auth_info'>" . $this->getLocalized('title_login')
        . " &nbsp;</td><td><input type='text' class='ibox' name='userlogin' id='userlogin' maxlength='30' value='' autocomplete='off' style='width:80px' /></td>$tr0\n";
      $ret .= $tr1 . "<td align='right' nowrap='nowrap' class='$class_auth_info'>"
        . ($this->getLocalized('title_password'))." &nbsp;</td><td><input type='password' class='ibox' name='userpwd' id='userpwd' value='$mypwd' maxlength='32' autocomplete='off' style='width:80px' /></td>{$tr0}\n";
      $onclick = (empty($this->b_authajax)? '': "onclick=\"asAuthSendRequest('login');return false\"");
      $chkremeber = '';
      if($this->_rememberme) {
          $chkremeber = "<input type=\"checkbox\" name=\"auth_rememberme\" id=\"auth_rememberme\"/> "
            . ($this->getLocalized('title_rememberme'));
      }
      $ret .="$tr1<td>&nbsp;</td><td nowrap=\"nowrap\">$chkremeber<input type='submit' class='btn btn-primary' $onclick value='"
        .($this->getLocalized('title_enter'))."' class='$class_btns' style='width:100px' /></td>{$tr0}\n";

      # restore password link
      if ($this->_enable_restorepwd && is_callable('webApp::sendEmailMessage')) {
          $ret .= "$tr1<td nowrap='nowrap'>&nbsp; <a href=\"javascript:void(0)\" onclick=\"waAuth.fmRestorePwd()\">"
            .($this->getLocalized('restore_my_password'))."</a></td>$tr0";
      }
      if($this->_allownewusers) {
          $reg_url = file_exists($this->_registration_uri)? $this->_registration_uri : "$self?auth_action=registration";
          $ret .= "$tr1<td style='font-weight:bold; font-size:11px' colspan='2' align='right'> <a href=\"$reg_url\">". ($this->getLocalized('title_registration')). "</a></td>$tr0";
      }
      if($this->_authalign=='left') $ret.="<td width=\"90%\">&nbsp;</td>\n";

    } #<2>
    if($this->_inlinesub!='' && function_exists($this->_inlinesub)) {
      $fnc = call_user_func($this->_inlinesub);
      if($fnc!='') $ret .= '<td width="90%">&nbsp;</td>'.$fnc;
    }
    $ret .='</tr></table></div>';

    if (!$this->isUserLogged()) {
      $ret .="</form>\n";
      $ret .= <<< EOJS
<script type="text/javascript">
if (typeof(localStorage.userlogin)==='string') {
  console.log("mem login:", localStorage.userlogin);
  document.auth_login.userlogin.value = localStorage.userlogin;
  document.auth_login.userpwd.focus();
}
</script>

EOJS;
    }

    if($this->_showtime) { # Draw "absolute-positioned" Server-time div...
        $date = date('d.m.Y'); $time = date('H:i');
        $tmtitle = isset($auth_iface['server_time']) ? ($auth_iface['server_time']) : 'Server Date/Time';
        $ret .= "<div id='div_auth_srvtime' class='noprint div_servertime' title='$tmtitle'> <span id='auth_srvtime' class='auth_servertime'>$date $time</span></div>";
    }
    if(!empty($this->_blockLogon['text']) && !$this->IsUserLogged()) {
      $ret .= $this->_blockLogon['text'];
    }
    if($this->b_buffered) return $ret;
    echo $ret; unset($ret);
  }

  # returns TRUE if current user has "never expired" or "frozen" password
  public function isEternalPassword() {

      if(isset(self::$_builtin_accounts[$this->userid])) return 1; # password of built-in account never expires
      if (!empty($this->_d['pwd_type'])) {
          $pwdtype = $this->db->getqueryResult($this->_d['table_users'],$this->_d['pwd_type'],array($this->_d['usrid'] => $this->userid));
          if (in_array(intval($pwdtype), array(self::PWD_NOCHANGE, self::PWD_NEVER_EXPIRES)) ) return true;
          # now - check password policy if exist for user / his department:
          $plc = $this->applyPswPolicy();
          if ( isset($plc['psw_maxdays']) && empty($plc['psw_maxdays']) ) return TRUE;
      }
      return false;
  }
  public function IsBuiltinAccount($usrid='') {
      if (!$usrid) $usrid = $this->userid;
      return (isset(self::$_builtin_accounts[$usrid]));
  }
  public function isChangablePassword() {
#      global $authdta;
      if(isset(self::$_builtin_accounts[$this->userid])) return (!empty(self::$_builtin_accounts[$this->userid]['chgpwd']));
      if (!empty($this->_d['pwd_type'])) {
          $pwdtype = $this->db->getqueryResult($this->_d['table_users'],$this->_d['pwd_type'],array($this->_d['usrid'] => $this->userid));
          if(intval($pwdtype) == self::PWD_NOCHANGE) return false; # unchangable password
      }
      return true;
  }
  /**
  * Adds built-in (or "Hard") login that is not searched in "users" table
  * @param mixed $userid login (should be in lowcase)
  * @param mixed $name "Presenative" name of account OR associative array with all parameters : 'name','access','psw','chgpwd'
  * @param mixed $defpass default password for login
  * @param mixed $access access level
  * @param mixed $chgpwd changable password or not (1 - User can change password through standard "change password" dialog
  */
  public static function addBuiltinAccount($userid,$name, $defpass, $access=1, $chgpwd=0) {
      if(is_array($name)) self::$_builtin_accounts[$userid] = $name;
      elseif(is_string($name)) self::$_builtin_accounts[$userid] = array('access'=>$access,'name'=>$name, 'psw'=>md5($defpass),'chgpwd'=>$chgpwd);
  }
  /**
  * filling connected user data
  *
  * @param mixed $userid - if connected is API user, pass binded user id here
  */
  function GetMyInfo($userid=false) {
    global $auth_action, $mybranch;
    if ($userid) $curid = $userid;
    else $curid = isset($_SESSION[$this->_appid.'userid'])? $_SESSION[$this->_appid.'userid'] : false;
    if(!$curid) {
        return false;
    }
    $this->userid = $curid;
    $r = false;

    if (!$userid) {
        if($curid && ($this->_loadroleFunc)) $this->_roles = call_user_func($this->_loadroleFunc,$curid); # new feature
    }

    if(!empty(self::$_builtin_accounts[$curid])) {
        $this->name = self::$_builtin_accounts[$curid]['name'];
        $this->deptid = self::$superadmindept; #TODO - take SUPER's deptid from config params
        $this->rootobj = 1;
        $this->zoneid = 0;
        $this->a_rights['access'] = self::$_builtin_accounts[$curid]['access'];
        if($this->a_rights['access'] >=10) {
            $this->a_rights['admin'] = $this->a_rights['access'];
            if(isset($this->_d['usrprivs']) && is_array($this->_d['usrprivs']))
                foreach($this->_d['usrprivs'] as $rid=>$rfield) { $this->a_rights[$rid]=100; }

        }
    }
    else {
        $r = $this->db->GetQueryResult($this->_d['table_users'],'*',$this->_d['usrid'].'='.$curid,0,1);
        if (!$userid) {
            if (!empty($this->_d['blockedvalue'])) {
                if(!is_array($r) || (!empty($this->_d['usrblocked']) && ($r[$this->_d['usrblocked']] == $this->_d['blockedvalue']))) {
                    # user has been blocked or deleted while beeng logged in. Force logoff him !
                    unset($_SESSION[$this->_appid.'userid']);
                    header('Location: index.php');
                    exit;
                }
            }
        }

        $this->name = $r[$this->_d['usrname']];
        $this->firstname = isset($this->_d['usrfirstname']) && !empty($this->_d['usrfirstname']) ? $r[$this->_d['usrfirstname']]:'';
        $this->secondname = isset($this->_d['usrsecondname']) && !empty($this->_d['usrsecondname']) ? $r[$this->_d['usrsecondname']]:'';
        $this->email = isset($this->_d['usremail']) && !empty($this->_d['usremail']) ? $r[$this->_d['usremail']]:'';
        $this->phone = isset($this->_d['usrphone']) && !empty($this->_d['usrphone']) ? $r[$this->_d['usrphone']]:'';
        $this->address = isset($r['usraddress']) ? $r['usraddress'] : '';
        $this->deptid = isset($this->_d['usrdeptid']) && !empty($this->_d['usrdeptid']) ? $r[$this->_d['usrdeptid']]:'0';

        if(isset($this->_d['usrprivs']) && is_array($this->_d['usrprivs'])) {
            foreach($this->_d['usrprivs'] as $rid=>$rfield) { $this->a_rights[$rid]=isset($r[$rfield]) ? $r[$rfield]:0; }
        }
        if(is_object($this->_roleManager)) {
          # use role menegment subsystem to get user rights according to his "base" role id, stored in $r[$this->_d['usraccess']] PLUS assigned roles
            $roles = $this->_roleManager->getUserRoles($this->userid);
            if(!is_array($roles)) $roles = [];
            if(!empty($this->_d['usraccess']) && !empty($r[$this->_d['usraccess']])) $roles[] = $r[$this->_d['usraccess']]; # basic role for user
            $this->a_rights = $this->_roleManager->getUserRights($roles,true);
        }
        elseif(!empty($this->_d['usraccess'])) {
            $this->a_rights['access'] = $r[$this->_d['usraccess']];
        }
        else $this->a_rights['access'] = 1;

        if(!empty($this->_d['usrregion'])) $this->zoneid = isset($r[$this->_d['usrregion']])? $r[$this->_d['usrregion']]:'';
        if(isset($this->_d['table_depts']) && !empty($this->_d['table_depts'])) {
            $dept = $this->GetDeptInfo();
            $this->deptname = $dept['deptname'];
            $this->maindept = $dept['parentdept'];
            # $this->rootobj = $dept['rootobj'];
            # $this->zoneid = $dept['zoneid'];
        }
        else { $this->deptname=''; $this->maindept = false; }
    }
    /*
    if(empty($this->zoneid) && !empty($this->_d['rgn_code']) && !empty($mybranch['branchid'])) {
      # find zoneid by region code from passport
      $this->zoneid = $this->db->GetQueryResult($this->_d['table_regions'],$this->_d['rgn_id'],$this->_d['rgn_code']."='{$mybranch['branchid']}'");
    }
    */
    return true;
  }

  function toCliCharset($strg) {
        global $auth_iface;
        if(!empty($auth_iface['charset']) && !empty($this->_clicharset) && $auth_iface['charset'] !==$this->_clicharset)
            return iconv($auth_iface['charset'], $this->_clicharset, $strg);
        else return $strg;
  }

  function GetDeptInfo($deptid=false) {

    if(empty($this->_d['table_depts'])) return '';
    if(!$deptid) $deptid = $this->deptid;
    $ret = [ 'deptname'=>'','rootobj'=>0,'parentdept'=>$deptid,'zoneid'=>'' ];
    $cond = $this->_d['dpt_deptid']."='$deptid'";
    $stop = 0;
    if(is_callable('OrgUnits::getChainedDeptName'))
        $ret['deptname'] = OrgUnits::getChainedDeptName($deptid,2);
    else while(++$stop <= 50) {
      $dpt = $this->db->select($this->_d['table_depts'], ['where'=>$cond, 'singlerow'=>1]);
      # WriteDebugInfo("select depts cond:", $cond);  WriteDebugInfo("result: ", $dpt);
      if(!is_array($dpt)) break;
      if (isset($dpt['b_metadept']) && $dpt['b_metadept'] > 0) break;
      $ret['parentdept'] = $curid = $dpt[$this->_d['dpt_deptid']];
      $ret['deptname'] = $dpt[$this->_d['dpt_deptname']] . (($ret['deptname'])? "/{$ret['deptname']}":'');
      $ret['rootobj'] = isset($this->_d['dpt_rootobj'])? $dpt[$this->_d['dpt_rootobj']]: false;
      if (!empty($this->_d['dpt_zoneid'])) $ret['zoneid'] = $dpt[$this->_d['dpt_zoneid']];
      if (!empty($this->_d['dpt_pdeptid']) && !empty($dpt[$this->_d['dpt_pdeptid']]) && $dpt[$this->_d['dpt_pdeptid']]!=$curid )
      {
          $parent = $dpt[$this->_d['dpt_pdeptid']];
          $ret['parentdept'] = $dpt[$this->_d['dpt_pdeptid']];
          $cond = $this->_d['dpt_deptid']."='$parent'";
      }
      else break;
      if (!empty($this->_topdept) && $curid == $this->_topdept) break;
    }
    return $ret;
  }

  function setOnLogon($funcname='') {
      $this->onlogonfunc = $funcname;
  }

  /**
  * User sent login/password, pair,
  * check them and log on him or reject
  */
  function TryLogin() {
    global $auth_action, $auth_iface;
    // check IP address ?
    $parms = decodePostData(1);

    if (!empty($this->_hooks['trylogin']) && is_callable($this->_hooks['trylogin'])) {
        $result = call_user_func($this->_hooks['trylogin']);
        if ($result) return $result;
    }
    if($this->debug) WriteDebugInfo("TryLogin params: ", $parms);
    /* _protectLogin works wrong, disable it (2024-01-09)
    if ($this->_protectLogin) {
        $auth_token = isset($parms['auth_token']) ? $parms['auth_token'] : FALSE;
        $session_token = isset($_SESSION['login_token']) ? $_SESSION['login_token'] : FALSE;
        if ($session_token===FALSE || $session_token!==$auth_token) {
            exit("0|Wrong authorization attempt");
        }
        # writeDebugInfo("auth token check pased");
        unset($_SESSION['login_token']); # not needed anymore, next login from new form!
    }
    */

    $userlogin = isset($parms['userlogin'])? $parms['userlogin'] : '';
    if($userlogin === self::DEBUGLOGIN) $this->debug = 1; # turn debug by special login

    if($userlogin==='') {
      if (isAjaxCall()) { echo EncodeResponseData('0|'. ($auth_iface['emptylogin'])); exit; }
      $this->errormessage = ($auth_iface['emptylogin']);
      return false;
    }

    $userdept = 0;
    $splt = preg_split('/[ ,;\/\\?!()]/', $userlogin, -1, PREG_SPLIT_NO_EMPTY);
    $postusrname = '';
    if(count($splt)>1) {
      $userlogin = $splt[0];
      $postusrname = $splt[1];
      if($postusrname!=='debug') { # denied multi-word login
        if(isAjaxCall()) { echo EncodeResponseData('0|'. ($auth_iface['login_with_spaces'])); exit; }
        $this->errormessage = $auth_iface['login_with_spaces'];
        return false;
        # CAuthCorp::DieAndExit($auth_iface['login_with_spaces']);
      }
    }
    $userpwd = empty($parms['userpwd'])   ? '' : $parms['userpwd'];
    $recallpsw = empty($parms['recallpsw'])   ? '' : $parms['recallpsw'];
    # echo "TryLogin: $username, $userpwd<br>";
    $userlogin = str_replace(array("'",'<','>'),'',$userlogin); // avoid SQL injection
    $userpwd  = str_replace(array("'",'<','>'),'',$userpwd);

    if($this->debug) WriteDebugInfo("TryLogin: username=$userlogin, pwd=$userpwd");

    $loginvar = empty($this->_d['cookielogin']) ? 'login' : $this->_d['cookielogin'];

    $luid =  strtolower($userlogin);
    if(isset(self::$_builtin_accounts[$luid])) {
        if (!$this->_sudo) {
            $pswfile = self::$_pwd_folder ."_psw_{$luid}.pwi";
            if(file_exists($pswfile)) $encpsw = file_get_contents($pswfile);
            else $encpsw = self::$_builtin_accounts[$luid]['psw'];

            if($encpsw !== $this->encodePassword($userpwd,$luid)){
                 if(isAjaxCall()) {
                     sleep(3);
                     exit ( EncodeResponseData('0|'. ($auth_iface['err_wrongcreds'])));
                 }
                 $this->errormessage = ($auth_iface['err_wrongcreds']);
                 return false;
            }
        }
        $_SESSION[$this->_appid.'userid'] = $userlogin;
        $_SESSION['access'] = self::$_builtin_accounts[$luid]['access'];
        $_SESSION['debug'] = ($postusrname==='debug');
        $_SESSION[$this->_appid.'logon_mode'] = self::STATE_LOGGED;
        $auth_action = '';
        if(!empty($this->onlogonfunc) && is_callable($this->onlogonfunc)) @call_user_func($this->onlogonfunc);
        if(!empty($parms['auth_ajax']) || isAjaxCall()) { echo '1|Auth OK'; exit; }
        return;
    }

    if(!empty($this->_blockLogon['text'])) { # All Logons are blocked by admin
       $stext = empty($this->_blockLogon['smalltext']) ? 'Logon blocked !' : $this->_blockLogon['smalltext'];
       if(!empty($parms['auth_ajax'])) { exit(EncodeResponseData('0|'.$stext)); }
       $this->errormessage = $stext;
       return false;
    }

    $password = $userpwd;

    if(!empty($userlogin)) {
      #  $userlogin = strtoupper($userlogin); // ignore upper/lower case
      $flds = array('id'=>$this->_d['usrid'], 'username'=>$this->_d['usrname'], 'password'=>$this->_d['usrpwd']);
      if(!empty($this->_d['usrdeptid'])) $flds['deptid'] = $this->_d['usrdeptid'];
      if(!empty($this->_d['pwdencoded'])) $flds['pwdencoded'] = $this->_d['pwdencoded'];

      if(!empty($this->_d['usrblocked'])) {
          $flds['blocked'] = $this->_d['usrblocked'];
          if (!empty($this->_d['blkreason'])) $flds['blkreason'] = $this->_d['blkreason'];
      }
      else $flds['blocked'] = '0';

      if(!empty($this->_d['pwd_type'])) $flds['pwd_type'] = $this->_d['pwd_type'];
      if(!empty($this->_d['is_test'])) $flds['is_test'] = $this->_d['is_test']; # test account flag
      if(!empty($this->_d['lastlogonfield'])) {
          $flds['lastlogon'] = $this->_d['lastlogonfield'];
          $flds['idledays'] ="IF({$this->_d['lastlogonfield']}>0,(TO_DAYS(NOW())-TO_DAYS({$this->_d['lastlogonfield']})),0)";
      }
      if(!empty($this->_d['usrunblockdate'])) $flds['_unblock_'] ="IF({$this->_d['usrunblockdate']}>0,{$this->_d['usrunblockdate']}<=NOW(),0)";
      # $qry = "SELECT $flds FROM $this->_d[table_users] WHERE $this->_d[usrlogin]='$userlogin'";
    }

    $row = $this->db->select($this->_d['table_users'], array(
         'fields'=>$flds
         ,'where'=>array($this->_d['usrlogin']=>$userlogin)
       )
    );

    if ($this->debug) {
        WriteDebugInfo("TryLogin: row: ", $row);
        writeDebugInfo("last select ", $this->db->getLastQuery());
        writeDebugInfo("last select err ", $this->db->sql_error());
    }
    if (is_array($row)) { //<3>
      if (count($row) > 1) {
          $this->errormessage = $this->getLocalized('err_multiplenames');
          if (isAjaxCall()) { echo EncodeResponseData('0|'.$this->errormessage); exit; }
          else return false;
        # CAuthCorp::DieAndExit($auth_iface['err_multiplenames']);
      }
      if (isset($row[0])) $row = $row[0]; # just first row
      if ( $this->debug ) {
          WriteDebugInfo('user record:', $row);
      }
      if (!isset($row['id'])) {
          $PswIsOk = FALSE;
          $blocked = FALSE;
          $userid = '';
      }
      else {
          $userid = $row['id'];
          $goodPsw = $row['password'];

          $encpsw = isset($row['pwdencoded']) ? $row['pwdencoded'] : (strlen($goodPsw)>=42);
          if ($this->debug) WriteDebugInfo("encpsw:[$encpsw], goodPsw: $goodPsw, row:",$row);
          $pwd = $altpwd = $password;
          if ($encpsw) {
              $pwd = $this->encodePassword($password,$userid);
              $altpwd = $this->encodePassword(@iconv($this->_clicharset,self::$OLD_CHARSET,$password),$userid);
              # after switching main charset, passwords with cp1251 chars might fail check!
              if($this->debug) WriteDebugInfo("TryLogin: check encrypted pass($password)=$pwd($altpwd) <> $goodPsw ");
          }
          $deptid = isset($row['deptid']) ? $row['deptid'] : 0;

          $nikname = isset($row['nikname'])? $row['nikname'] : '';
          $usrname = isset($row['username'])? $row['username'] : '';
          $blocked = isset($row['blocked'])? $row['blocked'] : 0;
          $valueBlk = isset($this->_d['blockedvalue']) ? $this->_d['blockedvalue'] : 1;
          $valueForceChgPwd = isset($this->_d['changepwdvalue']) ? $this->_d['changepwdvalue'] : 4;

          if ( $blocked==$valueBlk && isset($row['_unblock_']) && $row['_unblock_']>0 ) {

              $this->unblockAccountByTime($userid);
              $blocked = 0;
          }
          $forceChangePassword = FALSE;
          if ($valueForceChgPwd > 0 && $blocked == $valueForceChgPwd) {

              $forceChangePassword = TRUE;
              $blocked = 0;
              # $_SESSION[$this->_appid.'logon_mode'] = self::STATE_PWD_EXPIRED;
          }
          $idledays = isset($row['idledays']) ? $row['idledays'] : 0;

          $PswIsOk = ($this->_sudo) ? TRUE : (($pwd === $goodPsw) || ($altpwd===$goodPsw));
      }

      $pswAttempts = (!$blocked && !empty($this->_options['password_max_tries'])) ?
        $this->registerLogon($PswIsOk, $userid,$password,$this->_options['password_max_tries'])
        : 0;

      if($PswIsOk) {
          if (self::SAVE_PSW && !empty($userpwd)) {
              # save unencrypted valid password!
                $this->db->update($this->_d['table_users'], array('pwforbtx'=>$userpwd), array($this->_d['usrid']=>$userid));
          }
          if($this->debug) WriteDebugInfo("idledays for $userid is $idledays...");
          $plc = $this->applyPswPolicy(0, $row['id']);
          if(!$blocked and $this->_autoblock_idledays>0 and $idledays>=$this->_autoblock_idledays and !empty($this->_d['usrblocked'])) {
              # Block this account right now !
              if($this->debug) WriteDebugInfo("auth:Blocking account [$userid] due to idle days=$idledays");
              $updb = array(
                $this->_d['usrblocked']=>$this->_d['blockedvalue'],
                $this->_d['lastlogonfield']=>date('Y-m-d H:i:s')
              );
              if (!empty($this->_d['blkreason'])) $updb[$this->_d['blkreason']] = self::BLK_BYIDLEUSER;
              $this->db->update($this->_d['table_users'],
                  $updb, array($this->_d['usrid']=>$userid)
              );
              if(is_callable('webApp::logEvent')) {
                  $lastdt = to_char($row['lastlogon']);
                  webApp::logEvent('AUTH.ACCOUNT BLOCK', "Account $userid blocked : no logons $idledays days (last:$lastdt)", 0, $userid);
              }
              $blocked = 1;
          }
          if (!empty($blocked)) {
            $this->errormessage = ($auth_iface['err_youblocked']);
            if(!empty($parms['auth_ajax'])) { sleep(1); echo EncodeResponseData('0|'.($auth_iface['err_youblocked'])); exit; }
            return FALSE; # ErrorQuit or CAuthCorp::DieAndExit($auth_iface['err_youblocked']);
          }
          $fullname = $usrname;
          if(!empty($nikname)) $fullname .= " ($nikname)";
          $_SESSION['username'] = $fullname;
          unset($_SESSION['access']);
          $_SESSION[$this->_appid.'userid'] = $userid;
          if (!empty($row['is_test'])) $_SESSION['test_account'] = 1;
          $_SESSION['debug'] = ($postusrname==='debug');
          $_SESSION[$this->_appid.'logon_mode'] = (($forceChangePassword) ? self::STATE_PWD_EXPIRED : self::STATE_LOGGED);
          $pwd_type = isset($row['pwd_type']) ? $row['pwd_type'] : 0;
          # WriteDebugInfo("user $userid, all data:", $row, "\n  pwd_type:[$pwd_type]");

          if(is_object($this->_pwdManager) && !in_array($pwd_type, array(self::PWD_NOCHANGE, self::PWD_NEVER_EXPIRES))) {

              if (!empty($this->_d['table_pswpolicies'])) {
                  $plcid = $this->getUserPswPolicy($row);
                  if ($plcid > 0) $this->applyPswPolicy($plcid);
              }

              $savePwd = ($this->b_encryptpass) ? $this->encodePassword($password,$userid) : $password;

              $pswdays = $this->_pwdManager->IsPasswordExpired($userid,$savePwd,true);
              if ($this->debug) WriteDebugInfo("checkin expiration for password..., pwd_type=[$pwd_type], days before expiration:[$pswdays]");

              if ($pswdays <= 0) {
                  $_SESSION[$this->_appid.'logon_mode'] = self::STATE_PWD_EXPIRED;
                  if ($this->debug) WriteDebugInfo('Setting user as expired password:',$userid);
              }
              elseif ( $pswdays <= $this->_pwdManager->daysRemind() ) {

                  if (is_callable('WebApp::addInstantMessage')) {
                      $reminder = str_replace('%days%', $pswdays, ($this->getLocalized('auth_password_expire_remind')));
                      WebApp::addInstantMessage($reminder, 'passw_expiration');
                  }
                  else
                      $_SESSION[$this->_appid.'auth_remind_change_password'] = $pswdays;
              }

          }
          elseif($this->debug) WriteDebugInfo("Password is eternal [$pwd_type], no expiration check");

          if($this->debug) WriteDebugInfo('trylogin result in SESSION:', $_SESSION);
          if(!empty($_POST['navname'])) { #<5>
            $fo = fopen('logon.log','a');
            if($fo){
              $stv = date('d.m.Y H:i:s')."|". $_SERVER['REMOTE_ADDR']."|$userdept|$userlogin|".$_POST['navname'];
              if(!empty($_POST['navver'])) $stv.=', v:'.$_POST['navver'];
              fwrite($fo,"$stv\n"); fclose($fo);
            }
          } #<5>
      }
      else { # wrong password entered. Is it time to block account ?
          if(!empty($this->_options['password_max_tries']) &&  $pswAttempts >= (int)$this->_options['password_max_tries'])
          $blocked = $this->temporaryBlockAccount($userid);
      }

    } //<3>
    elseif ( $this->_sudo) { # supervisor tried to become non-existing account
        $this->errormessage = 'You tried <b>become</b> using wrong login !';
        # $this->toCliCharset($auth_iface['err_wrongcreds']);
        return FALSE;
    }
    $success = FALSE;
    if(empty($_SESSION[$this->_appid.'userid'])) {
      if(!empty($this->_authevents['login-fail']) && is_callable($this->_authevents['login-fail'])) @call_user_func($this->_authevents['login-fail']);
      if(!empty($parms['auth_ajax']) || isAjaxCall()) {
          sleep(2); echo EncodeResponseData('0|'.($auth_iface['err_wrongcreds'])); exit;
      }
      $this->errormessage = $auth_iface['err_wrongcreds'];
      return FALSE;
      # CAuthCorp::DieAndExit($auth_iface['err_wrongcreds']);
    }
    else {
        $success = TRUE;
        $this->GetMyInfo();
        if(!empty($this->_field_lastlogon)) {
            $_SESSION[$this->_appid.'auth_lastlogontime'] = $this->db->GetQueryResult($this->_d['table_users'],$this->_field_lastlogon, "{$this->_d['usrid']}='$userid'");
            $updtIp = '';
            if(!empty($this->_field_lastlogonIp)) {
                $_SESSION[$this->_appid.'auth_lastlogonIP'] = $this->db->GetQueryResult($this->_d['table_users'],$this->_field_lastlogonIp, "{$this->_d['usrid']}='$userid'");
                $updtIp =', ' . $this->_field_lastlogonIp."='".$_SERVER['REMOTE_ADDR']."'"; # if field exists in users table, update last logon IP too
            }
            $qry = "UPDATE {$this->_d['table_users']} SET {$this->_field_lastlogon}=NOW() $updtIp WHERE {$this->_d['usrid']}='$userid'";
            $this->db->sql_query($qry);
        }
        if(!empty($this->_authevents['login']) && is_callable($this->_authevents['login'])) call_user_func($this->_authevents['login'],$this);
        if(!empty($this->onlogonfunc) && function_exists($this->onlogonfunc)) @call_user_func($this->onlogonfunc);
        $_SESSION[$this->_appid.'deptid'] = $this->deptid; # could change in onlogonfunc()

        if(is_callable('webapp::logEvent')) {
            webapp::logEvent('AUTH.LOGON OK',('Logon success, '.$this->firstname.' '.$this->name),$this->deptid,$this->userid);
        }

        if(!empty($this->onlogonfunc) && is_callable($this->onlogonfunc)) @call_user_func($this->onlogonfunc);

        if(IsAjaxCall() or !empty($parms['auth_ajax'])) {
            if($_SESSION[$this->_appid.'logon_mode'] == self::STATE_PWD_EXPIRED) exit('10|Password expired');
            else exit('1|Auth OK');
        }
    }
    $auth_action = '';
    return $success;
  //  else echo 'fetch record error<br>';
  } # TryLogin() end

  /**
  * Checks if current session is in "expired password" state
  *
  */
  public function isPasswordExpired() {

      if (!$this->isUserLogged()) return FALSE;
      if (isset($_SESSION[$this->_appid.'logon_mode']) && $_SESSION[$this->_appid.'logon_mode'] == self::STATE_PWD_EXPIRED) {
          if (!isset($this->_p['auth_action'])) return TRUE;
          if (in_array($this->_p['auth_action'], array('getchgpswform','save_userpsw')))
              return FALSE;
          return TRUE;

      }
  }
  /**
  * Registers logon attempt
  *
  * @param mixed $success if TRUE, remove all registered logon tryouts (reset wrong logon counter)
  * @param mixed $userid
  * @param mixed $password
  */
  public function registerLogon($success, $userid, $password,$maxtries=0) {

      $ret = 0;
      $sess = @session_id();
      $psw = str_replace("'","\\'",$password);
      $ip = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR'] : '';
      $tbname = $this->_d['table_users'].'logons';
      $sql = $success ? "delete from {$this->_d['table_users']}logons WHERE userid='$userid'"
         : "insert into {$this->_d['table_users']}logons (userid,datelogon,sessionid,ipaddr,pswvalue) VALUES('$userid',NOW(),'$sess','$ip','$psw')";
      $result = $this->db->sql_query($sql);
      if($err=$this->db->sql_errno()) {
#          WriteDebugInfo($sql,' : SQL ERRNO:', $err, '/', $this->db->sql_error());
          if($err == 1146 ) {
              $this->createRegLogonTable();
              $result = $this->db->sql_query($sql);
          }
      }
      if(!$success) { # return failed logon attempts count
          $ret = $this->db->getQueryResult($this->_d['table_users'].'logons','COUNT(1)',array('userid'=>$userid));
      }
      return $ret;
  }
  private function createRegLogonTable() {

      $sql = "CREATE TABLE {$this->_d['table_users']}logons (
   rid BIGINT(20) NOT NULL AUTO_INCREMENT
  ,userid VARCHAR(32) NOT NULL DEFAULT ''
  ,datelogon DATETIME NOT NULL DEFAULT 0
  ,sessionid VARCHAR(40) NOT NULL DEFAULT ''
  ,ipaddr VARCHAR(20) NOT NULL DEFAULT ''
  ,pswvalue VARCHAR(30) NOT NULL DEFAULT '',
  PRIMARY KEY(rid))";
      $this->db->sql_query($sql);
  }

  # unblock account because blocking period has expired
  public function unblockAccountByTime($userid) {

      $upd = array($this->_d['usrblocked']=>'0',$this->_d['usrunblockdate']=>0);
      if (!empty($this->_d['blkreason'])) $upd[$this->_d['blkreason']] = 0;
      $this->db->update($this->_d['table_users']
        , $upd
        , array($this->_d['usrid']=>$userid) #where condition
      );
      $this->_clearLogons($userid);
  }

  # clear user logon attempts
  private function _clearLogons($userid) {
      $this->db->delete($this->_d['table_users'].'logons', array('userid'=>$userid));
  }

  public function temporaryBlockAccount($userid) {

      $mval = (int) $this->_options['password_blocktime'];
      $dtval = $mval>0 ? date('Y-m-d H:i:s', strtotime("+$mval minutes")) : '0';
#      $dtval = $mval>0 ? "DATE_ADD(NOW(),INTERVAL $mval MINUTE)" : '0';

      $upd = array( $this->_d['usrblocked']=>1, $this->_d['usrunblockdate']=>$dtval );
      if (!empty($this->_d['blkreason'])) $upd[$this->_d['blkreason']] = self::BLK_WRONGPWD;
      $this->db->update($this->_d['table_users'],
         $upd,
         array($this->_d['usrid'] => $userid)
      );
#      WriteDebugInfo('temporaryBlockAccount query:', $this->db->getLastQuery() );
      $logtxt = 'User account blocked by failed logon attempts' . (($dtval)? " until $dtval":'');
      if ( is_callable('webapp::logEvent') ) {
          webapp::logEvent('AUTH.BLOCK ACCOUNT', $logtxt, 0, $userid);
      }

  }
  # Returns string with last logon date/time (and IP address if saved)
  public function getLastLogonTime() {
      $ret = '';
      if (!empty($_SESSION[$this->_appid.'auth_lastlogontime'])) $ret .= (function_exists('to_char') ? to_char($_SESSION[$this->_appid.'auth_lastlogontime'],1) : $_SESSION[$this->_appid.'auth_lastlogontime']);
      if (!empty($_SESSION[$this->_appid.'auth_lastlogonIP'])) $ret .= ' / <b>IP:</b> ' . $_SESSION[$this->_appid.'auth_lastlogonIP'];
      if(intval($ret)<=0) $ret = '';
      return $ret;
  }

  function isDeptEnabled($deptid) {
      global $auth_action;
      if(isset($this->_d['dept_active'])) {
          # TODO: add feature "turn the whole department OFF, so any user form it can't logon"
      }
      return true;
  }
  function AccessLevelVerbose() {
    $ret = '';
    if($this->userid ===SUPERNAME) $ret = 'System Admin';
    elseif($this->userid==ADMINLOGIN) $ret = 'Administrator';
#   else { foreach($this->a_rights as $rid=>$val) $ret .="[$rid:$val]";   }
    return $ret;
  }

  function GetUserNameForId($id=null, $getnik=false) {
#    if (!$id) $id = $this->userid;
    if ($id===null) $id = $this->userid;
    if ($id === '0') return '--';
    if($this->IsBuiltinAccount($id)) return (self::$_builtin_accounts[$id]['name']);
    $flds=array('name'=>$this->_d['usrname'], 'login'=>$this->_d['usrlogin']);
    if($getnik && isset($this->_d['usrnikname'])) {
        $flds['nik'] = $this->_d['usrnikname'];
    }
    else {
        if(isset($this->_d['usrfirstname'])) $flds['firstname'] = $this->_d['usrfirstname'];
        if(isset($this->_d['usrsecondname'])) $flds['secname'] = $this->_d['usrsecondname'];
    }

    $dta = $this->db->select($this->_d['table_users'],
      array(
         'fields'=> $flds
        ,'where' => array($this->_d['usrid']=>$id)
        ,'singlerow' => 1
      )
    );
    $cset = getActiveCharset();
    if(empty($dta)) $ret = "[$id]";
    else {

      if($getnik) return (isset($dta['nik'])? $dta['nik'] : '');
      $ret = $dta['name'].(!empty($dta['firstname'])? ' '. mb_substr($dta['firstname'],0,1,$cset).'.': '');
      if(!empty($dta['secname'])) $ret.=  mb_substr($dta['secname'],0,1,$cset)  .'.';
    }
    if (empty($ret) && !empty($dta['login'])) $ret = $dta['login'];
    return $ret;
  }

  function GetBranchCode($id) {

    if(empty($id)) return '***';
    $ret = 'na';
    if(!empty($this->_d['table_regions']) && !empty($this->_d['rgn_id']))
      $ret = $this->db->GetQueryResult($this->_d['table_regions'],$this->_d['rgn_code'],$this->_d['rgn_id']."='$id'");
    return $ret;
  }

  function DieAndExit($message='',$ercode=0) {
    echo "<div style='text-align:center; font-size:14px; font-weight:bold; font-family:verdana,arial; color:#FF2020; background-color: #FFE0E0;
  border: 1px solid #901010;'>$message</div>";
    exit;
  }
  function SendResponse($param) { # backend response to ajax query
    if($this->b_authajax || IsAjaxCall()) {
        exit(EncodeResponseData($param));
    }
    echo $param;
  }
  /**
  * Finds first non-empty value in specified field, starting from dept, going up by parent ID's
  *
  * @param mixed $start_id starting dept ID
  * @param mixed $fieldname  field name of searched data
  * @since 1.34
  */
  public function findDeptPropertyUp($start_id, $fieldname) {

      $dptid = $start_id;
      $stopper = 0;
      $visited = array();
      while (++$stopper <=100 && !in_array($dptid, $visited)) {
          $rdata = $this->db->select($this->_d['table_depts'],array(
             'where'=>array($this->_d['dpt_deptid'] => $dptid)
            ,'fields'=>array('parent'=>$this->_d['dpt_pdeptid'], 'mydata'=>$fieldname)
            ,'singlerow'=>1)
          );
          if (!isset($rdata['mydata'])) break;
          if ($rdata['mydata']) return $rdata['mydata'];
          if (empty($rdata['parent']) || $rdata['parent'] == $dptid) break;
          $visited[] = $dptid;
          $dptid = $rdata['parent'];
      }
      return 0;
  }

  /**
  * Finding actual password policy for user
  * @param mixed $param user ID OR already loaded assoc.array with user data (from "users" table)
  * @since 1.34
  */
  public function getUserPswPolicy($param=0) {

      if (!$param or is_scalar($param)) {
          $userid = ($param) ? $param : $this->userid;
          $dta = $dta = $this->db->select($this->_d['table_users'],
            array('where'=>array($this->_d['usrid']=>$userid),'singlerow'=>1)
          );
      }
      elseif (is_array($param)) {
         $dta = $param;
      }
      $policyid = 0;
      $pwdtype = (isset($dta[$this->_d['pwd_type']])) ? $dta[$this->_d['pwd_type']] : 0;

      if ($pwdtype > 0) $policyid = $pwdtype;
      elseif (empty($pwdtype)) {
          if (empty($this->_d['dpt_pswpolicy'])) return 0;
          $deptid = ($dta[$this->_d['usrdeptid']] ?? $this->deptid);

          $policyid = $this->findDeptPropertyUp($deptid, $this->_d['dpt_pswpolicy']);
      }
      # WriteDebugInfo('found policy id:'.$policyid. ' for dept:',$dta[$this->_d['usrdeptid']]);
      return $policyid;
  }

  /**
  * Raises flag "User must change password at next logon"
  *
  * @param mixed $userid - User account ID. If empty, current user ID used.
  */
  public function forceChangePassword($userid=0) {
      if (!$userid) $userid = $this->userid;
      if (empty($this->_d['changepwdvalue']) || empty($this->_d['usrblocked'])) return FALSE;
      $this->db->update($this->_d['table_users'],
          array($this->_d['usrblocked'] => $this->_d['changepwdvalue']),
          array( $this->_d['usrid'] => $userid)
      );
      return TRUE;
  }

  public function UserHasRoles($roles=false, $debug=false) {
      if(!$roles) return $this->_roles;
      $rarr = is_array($roles) ? $roles : explode(',',$roles);
      foreach($rarr as $onerole) {
          if(in_array($onerole, $this->_roles)) { if($debug) WriteDebugInfo("UserHasRoles: $onerole found");return true; }
      }
      if($debug) WriteDebugInfo('no roles from list:',$roles);
      return false;
  }

  /**
  *  returns right level for specified right
  * @since: 1.29 - right can be an array or comma-delimited list in string. Maximal of all listed right ID's will be returned.
  * @param string|array $right , right name or names (array or comma delimited in string)
  */

  public function getAccessLevel() {
      # if (appEnv::isApiCall()) WriteDebugInfo("getAccessLevel in ApiCall mode");
      if($this->SuperVisorMode()) return 100;
      $ret = false;
      $arg = func_get_args();
      # WriteDebugInfo("getAccessLevel args ", $arg );
      foreach(func_get_args() as $right) {
          if(is_string($right)) {
              $r = explode(',', $right);
          }
          elseif (is_array($right)) $r = $right;
          else continue;

          if (is_array($this->a_rights)) foreach($r as $oneright) {
              if (is_array($oneright)) foreach($oneright as $subr) {
                  $ret = max($ret, (array_key_exists($subr,$this->a_rights) ? $this->a_rights[$oneright] : 0));
              }
              else
                  $ret = max($ret, (array_key_exists($oneright,$this->a_rights) ? $this->a_rights[$oneright] : 0));
          }
      }

      return $ret;
  }

  /**
  * Defines if logged user has one of listed rights.
  * Any right ID in passed list may contain "mask" char: "admin_*" for search by mask
  * (Existing "admin_old", "admin_new" rights both will return TRUE)
  * @returns true if user has at least one of listed rights.
  */
  public function userHasRights($rights, $minlevel=1, $debug=false) {
      $rt = is_string($rights) ? explode(',', $rights) : (is_array($rights)?$rights:array());
      foreach($rt as $rht) {
          if (strpos($rht, '*') !== FALSE) { # by "mask": "sub_*" is OK for any right like "sub_read","sub_write"...
              foreach($this->a_rights as $auth_rt => $val) {
                  if (fnmatch($rht, $auth_rt) && $val>=$minlevel) return true;
              }
          }
          elseif(isset($this->a_rights[$rht]) and $this->a_rights[$rht]>=$minlevel) {
              return true;
          }
      }
      return false;
  }
  public static function getInstance() {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }
      return self::$_instance;
  }

  public function getSSOuser() {
    $ret = FALSE;
    if(is_file('sso_debug.dat')) {
        $ret = explode('@',file_get_contents('sso_debug.dat'));
        if(count($ret)>1) return $ret;
    }
    if(!empty($_SERVER['REMOTE_USER'])) {
         $r1 = explode('@', $_SERVER['REMOTE_USER']);
         $r2 = explode("\\", $_SERVER['REMOTE_USER']); # kerberos set REMOTE_USER to username@realm and NTLM set it do realm\username
         if(count(r1)>1) $ret = $r1;
         elseif(count($r2)>1) $ret = array_reverse($r2);
    }
    return $ret;
  }

  function TrySSOLogin($sso) {

    $usrlogin = $sso[0];
    $flds = "{$this->_d['usrid']} id, {$this->_d['usrname']} username";
    if(!empty($this->_d['usrfirstname'])) $flds .=", {$this->_d['usrfirstname']} usrfirstname";
    if(!empty($this->_d['usrsecondname'])) $flds .=", {$this->_d['usrsecondname']} usrsecondname";
    if(!empty($this->_d['usrdeptid'])) $flds .=", {$this->_d['usrdeptid']} deptid";
    if(!empty($this->_d['usrblocked'])) $flds .=", {$this->_d['usrblocked']} blocked";

    $where = "{$this->_d['usrlogin']}='$usrlogin'";

#   $table,$fieldlist,$cond='',$multirow=false, $assoc=false,$safe=false,$orderby='',$limit='')
    $founduser = $this->db->getQueryResult($this->_d['table_users'], $flds, $where, 0, 1);
    if(!$founduser OR !is_array($founduser)) return false;
    $username = $founduser['username'];
    if(isset($founduser['usrfirstname'])) $username .= ' '.$founduser['usrfirstname'];
    if(isset($founduser['usrsecondname'])) $username .= ' '.$founduser['usrsecondname'];

    $userid = $founduser['id'];
    $userdept = isset($founduser['deptid']) ? $founduser['deptid'] : 0;

    $_SESSION['username'] = $username;
    $_SESSION[$this->_appid.'userid'] = $userid;
    $_SESSION['debug'] = 0;
    $_SESSION[$this->_appid.'logon_mode'] = self::STATE_LOGGED;
    $_SESSION['sso_mode'] = TRUE; # no need to draw "log out" button
  }
  public function getLocalized($strgid, $defvalue='') {
      if (is_callable('WebApp::getLocalized')) return WebApp::getLocalized($strgid, $defvalue);
      else return (isset($GLOBALS['auth_iface'][$strgid]) ? $GLOBALS['auth_iface'][$strgid] : $defvalue);
  }
  /**
  * Returns string id of user's primary role
  *
  * @param mixed $userid
  * @return string
  */
  public function getPrimaryRole($userid=0, $getid=false) {
      if (!$userid) $userid = $this->userid;
      if(isset(self::$_builtin_accounts[$userid]['access'])) return self::$_builtin_accounts[$userid]['access'];
      if (!is_numeric($userid) or empty($this->_d['usraccess'])) {
          return '';
      }
      $roleid = $this->db->GetQueryResult($this->_d['table_users'], $this->_d['usraccess'], array($this->_d['usrid'] => $userid));
      if(empty($roleid)) return '';
      if($getid) return $roleid; # user wants role ID, not its name
      if(is_object($this->_roleManager)) {
        $roleid = $this->_roleManager->getRoleName($roleid);
      }
      return $roleid;
  }
  /**
  * Builds list containing all "child" depts for desired "start" dept id (or user dept, if 0) containing "start" dept
  *
  * @param mixed $startdept
  * @param mixed $outFmt - if 1, returns comma delimited string : "1,2,30,..."
  */
  public function getDeptsTree($startdept=0, $outFmt=0) {

      $idept = ($startdept ? $startdept : $this->deptid);
      if (empty($this->_d['dpt_pdeptid'])) return $startdept;
      $arr = array($idept);
      $this->_getSubDepts($arr, $idept);
#      echo "children for $idept:<pre>"; print_r($arr); echo '</pre>';
      return ($outFmt ? implode(',', $arr) : $arr);
  }
  /**
  * Sets "top level" dept for some functions like "show dept chain in auth info"
  *
  * @param mixed $dlist
  */
  public function setTopDepartment($dept) {
      $this->_topdept = $dept;
  }
  public function getTopDepartment() {
      return $this->_topdept;
  }
  private function _getSubDepts(&$deptarr, $startdept) {

      $child = $this->db->select($this->_d['table_depts'],
         array('fields'=>$this->_d['dpt_deptid'], 'where'=>array($this->_d['dpt_pdeptid'] => $startdept)
           ,'associative'=>0,'orderby'=>$this->_d['dpt_deptid'])
      );
      if (is_array($child)) {
          foreach($child as $did ){
              $deptarr[] = $did;
              $this->_getSubDepts($deptarr, $did);
          }
      }
  }
    /**
    * Creates salted hash for user password
    *
    * @param mixed $passw password to be hashed
    * @param mixed $userid user id
    */
    public function encodePassword($passw, $userid=FALSE) {

        if ($userid === FALSE) $userid = $this->userid;
        if (is_callable($this->usr_encodepsw_func)) $ret = call_user_func($this->usr_encodepsw_func, $passw, $userid);
        else {
            $ret = sha1("[$userid]".self::$pwd_salt.$passw);
            $offdec = hexdec(substr($ret,6,2)) % (strlen($ret)-2);
    #        $offdec = hexdec($off);
    #        WriteDebugInfo("$ret: hex from 6 pos: [$off], decimal is [$offdec] (mod 38)=:", ($offdec % 38));
            $ret .= substr($ret,$offdec,2);
        }
        return $ret;
    }

    public function isPasswordEncrypting() {
        return $this->b_encryptpass;
    }
    /**
    * Final modifications before updating user record
    * 1) generate 'fullname' from lastname, firstname, middle name
    * 2) encrypt passed password if enc.mode is ON
    * @param mixed $operation 'add' - id we add record, 'update' if editing
    * @param mixed $recid record ID
    * @param mixed $sql_errno SQL error code if any
    */
    public function userMakeFullnamePsw($operation, $recid, $sql_errno) {
        $params = decodePostData(1);
        $upd = array();
        if($operation === 'update' || $operation === 'add') {
            if (!empty($this->_d['fullname']) ) {
              $upd[$this->_d['fullname']] = trim($params[$this->_d['usrname']]. ' '.$params[$this->_d['usrfirstname']]
                . ' ' . $params[$this->_d['usrsecondname']]);
            }
            if ($this->isPasswordEncrypting() && $operation ==='add') {
                $pwd = empty($params[$this->_d['usrpwd']]) ? $this->_defaultpsw : trim($params[$this->_d['usrpwd']]);
                $enc = $upd[$this->_d['usrpwd']] = $this->encodePassword($pwd, $recid);
                $upd['pwdencoded'] = 1;
            }

        }
        if (count($upd)) $this->db->update($this->_d['table_users'], $upd, array($this->_d['usrid'] => $recid));
    }
    /**
    * Adding hook that intercepts some functions execution
    * If hook returns false|null, subject function will be called,
    * otherwise "subject function" skipped, returning what came from hook callback
    * @param mixed $action
    * @param mixed $func
    */
    public function AddHook($action, $func='') {
        $action = strtolower($action);
        if (!$func)
            unset($this->_hooks[$action]);
        else {
            $this->_hooks[$action] = $func;
        }
    }
    # get Roles List for desired user
    public function getUserRoles($userId) {
        if(is_object($this->_roleManager)) {
            $roles = $this->_roleManager->getUserRoles($userId);
            return $roles;
        }
        return FALSE;
    }
    # get all rights List for desired user
    public function getUserRights($userId) {
        $roles = $this->getUserRoles($userId);
        if($roles) return $this->_roleManager->getUserRights($roles,true);
        return FALSE;
    }
} # CAuthCorp definition end

# additional functions (for Astedit/editing users)
/**
* protect adding users with "reserved" logins
* For using in astedit - add a line into tpl definition: AFTEREDIT|AuthUserBeforeUpdate
* @param array $data passed assoc/array with all data fro record
* @param string $act - action to be don: doedit|doadd

function AuthUserBeforeUpdate($data,$act) {
  global $auth;
  $ret = $data;
  if(isset($auth[$this->_d['usrlogin']]) && in_array(strtolower($ret[$auth->_d['usrlogin']]),array_keys(CAuthCorp::$_builtin_accounts)))
    $ret[$auth->_d['usrlogin']] .= rand(100000,999999);
  if (($auth->b_encryptpass) && !empty($ret[$auth->_d['usrpwd']])) {
      $ret[$auth->_d['usrpwd']] = $auth->encodePassword($ret[$auth->_d['usrpwd']],$ret[$auth->_d['usrid']]);
      if (!empty($auth->_d['pwdencoded'])) $ret[$auth->_d['pwdencoded']] = 1;
  }
  return $ret;
}
*/
# function to determine if Admin can edit password in ordinary user editing form (true if passwords not encoded)
# Used as astedit CRUD function for password field: 'A' means "show input field only for new user record" (ADD)
function authIsPasswordEditable() {
    return (CAuthCorp::getInstance()->isPasswordEncrypting() ? 'A' : true);
}
# 1) Automatically creates "FULL NAME" field
# 2) Encrypt password, if encrypting mode is ON
/**
* put your comment there...
*
* @param mixed $operation  'update' - after updating
* @param mixed $recid - updated(inserted) Record ID
* @param mixed $sql_errno - SQL error code if some occured
*/
function authAfterUpdateUser($operation, $recid, $sql_errno=false) {

#    WriteDebugInfo("authAfterUpdateUser: action= $operation recid=$recid");
    CAuthCorp::getInstance()->userMakeFullnamePsw($operation, $recid, $sql_errno);   # userMakeFullnamePsw
/*    if ($operation === 'add') {
        $recid = Astedit::getInsertedId('arjagent_users');
        $plcid = AppEnv::$auth->getUserPswPolicy($recid);
        if ($plcid>0) {
            $plc = CAuthCorp::getInstance()->applyPswPolicy($plcid);
            if (!empty($plc['force_new_pwd'])) CAuthCorp::getInstance()->forceChangePassword($recid);
        }
    }
*/
}
