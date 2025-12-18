<?php
/**
* @package ALFO
* @name app/cmsutils.php
* Функции получения данных из ALFO либо из внешней CMS Bitrix (если обнаружена)
* доп.ф-ции обработки данных для отчетов
* @version 1.21.003
* modified 2025-12-17
*/
class CmsUtils {
    const VERSION = '1.21';
    static $cmsUserData = FALSE;
    static $debug = 0;
    public static $lastUserData = [];

    public static function getVersion() { return self::VERSION; }

    public static function getUserInfo($userid, $cms_userid=FALSE) {
      # if (!appEnv::isProdEnv()) self::$debug = 1;
      $ret = [];
      $btxid = 0;
      if($userid > 0) {
          $dta = appEnv::$db->select(PM::T_USERS, array(
            'where' => array('userid'=>$userid)
            ,'fields' => 'userid,bitrix_id,deptid,usrlogin login,usrname lastname,firstname,secondname,code_ikp,usremail email,winaccount,winuserid'
               . ',usrphone phone, agentcode agentno, manager_id,is_test,b_blocked'
            ,'associative' => 1
           ,'singlerow' => 1
          ));
          if (self::$debug) writeDebugInfo("ALFO data/$userid: ", $dta);
          $btxid =  isset($dta['bitrix_id']) ? $dta['bitrix_id'] : '';
          if(isset(self::$lastUserData['userid']) && self::$lastUserData['userid']==$userid)
            $ret = self::$lastUserData;
          else $ret = self::$lastUserData = [
            'userid' => ($dta['userid'] ?? 0),
            'deptid' => ($dta['deptid'] ?? 0),
            'login' => ($dta['login'] ?? ''),
            'lastname' => (isset($dta['lastname']) ? $dta['lastname'] : ''),
            'firstname' => (isset($dta['firstname']) ? $dta['firstname'] : ''),
            'secondname' => (isset($dta['secondname']) ? $dta['secondname'] : ''),
            'email' => (isset($dta['email']) ? $dta['email'] : ''),
            'phone' => (!empty($dta['phone']) ? $dta['phone'] : ''),
            'agentno' => (!empty($dta['agentno']) ? $dta['agentno'] : ''),
            'manager_id' => (!empty($dta['manager_id']) ? $dta['manager_id'] : ''),
            'test' => (isset($dta['is_test']) ? $dta['is_test'] : 0),
            'blocked' => (isset($dta['b_blocked']) ? ($dta['b_blocked']==1) : 1),
            'code_ikp' => ($dta['code_ikp'] ?? ''),
            'winaccount' => ($dta['winaccount'] ?? ''),
            'winuserid' => ($dta['winuserid'] ?? ''),
          ];
          if (self::$debug) writeDebugInfo("1. ret data: ", $ret);
          if (!empty($_SESSION['debug_me'])) writeDebugInfo("1. userFio from ALFO: ", $ret);
      }
      elseif($cms_userid > 0) {
          $btxid = $cms_userid;
          $ret = [];
      }
      if ($btxid>0) {
            $usrInfo = self::getCmsUserInfo($btxid);
            if (!empty($_SESSION['debug_me'])) writeDebugInfo("2. usrInfro from Bitrix: ", $usrInfo);
            if (isset($usrInfo['EMAIL'])) {
                # echo 'user btx <pre>' . print_r($usrInfo,1). '</pre>';
                if (self::$debug) writeDebugInfo("bitrix user data: ", $usrInfo);
                $ret['email'] = $usrInfo['EMAIL'] ?? '';
                if (!empty($usrInfo['LOGIN'])) $ret['login'] = $usrInfo['LOGIN'];
                if (!empty($usrInfo['LAST_NAME'])) $ret['lastname'] = $usrInfo['LAST_NAME'];
                if (!empty($usrInfo['NAME'])) $ret['firstname'] = $usrInfo['NAME'];
                if (!empty($usrInfo['SECOND_NAME'])) $ret['secondname'] = $usrInfo['SECOND_NAME'];
                if (!empty($usrInfo['PERSONAL_PHONE'])) $ret['phone'] = $usrInfo['PERSONAL_PHONE'];
                if (empty($ret['agentno']) && !empty($usrInfo['UF_AGENT_CODE']))
                    $ret['agentno'] = $usrInfo['UF_AGENT_CODE']; # у ALFO-шного кода агента - приоритет!
                if (!empty($usrInfo['UF_AGENT_MANAGER'])) $ret['btx_manager'] = $usrInfo['UF_AGENT_MANAGER'];
                $ret['blocked'] = ($usrInfo['ACTIVE'] === 'N'); # {upd/2022-06-15}
                $ret = array_merge($ret,$usrInfo);
            }
      }
      if (!empty($_SESSION['debug_me'])) writeDebugInfo("3. final data for User: ", $ret);
      return $ret;
    }

    public static function getUserEmail($userid) {
        $dta = self::getUserInfo($userid);
        if (isset($dta['email'])) return $dta['email'];
        return '';
    }
    public static function getUserAgentNo($userid) {
        $dta = self::getUserInfo($userid);
        if (!empty($dta['agentno'])) return $dta['agentno'];
        return '';
    }

    /**
    * Полный набор полей по учетке из Битрикс, включая пользовательские
    * UF_AGENT_CODE UF_AGENT_MANAGER UF_FIO_KURATOR
    *
    * @param mixed $btxid ИД учетки в Битрикс
    * @return mixed
    */
    public static function getCmsUserInfo($btxid) {
        global $clcabDB, $bitrixDB;
        if (class_exists('CUser')) {
            $oUser = new CUser;
            $rsUser = $oUser->GetByID($btxid);
            if ($rsUser) self::$cmsUserData = $rsUser->Fetch();
            else self::$cmsUserData = 0;
            return self::$cmsUserData;
        }
        else {
            if (empty($bitrixDB)) $bitrixDB = 'cc20prd';
            self::$cmsUserData = \AppEnv::$db->select("$bitrixDB.b_user", ['where'=>['ID'=>$btxid],'singlerow'=>1]);
            return self::$cmsUserData;
        }
    }

    public static function getFoundUserData() {
        return self::$cmsUserData;
    }
    /**
    * Поиск учетки по "коду агента" Вернет массив с эл-тами:
    * ID,LOGIN,NAME,SECOND_NAME,LAST_NAME,EMAIL,PERSONAL_PHONE,ACTIVE("Y"),DATE_REGISTER,...
    *
    * @param mixed $agentcode
    */
    public static function getUserByAgentCode($agentcode) {

        if (class_exists('CUser') ) {
            $oUser = new CUser;
            $filter = ['UF_AGENT_CODE' => $agentcode];
            $by ='timestamp_x';
            $order='NAME';
            $rsUsers = CUser::GetList($by, $order, $filter);
            if ($rsUsers) $result = $rsUsers->Fetch();
            else $result = $rsUsers;
            return $result;
            # 'Result:<pre>'.print_r($result,1).'</pre>'; # ->Fetch();
            # else return 'Not found';
        }
        else return FALSE; # 'No CUSER, Bitrix not active';
    }
    # Ищу учетку Битрикс по адресу email, если битрикса нет - ищу в ALFO
    public static function getUserByEmail($email, $options = FALSE) {

        if (class_exists('CUser') ) {
            $oUser = new CUser;
            $filter = ['EMAIL' => $email];
            $by ='timestamp_x';
            $order='LAST_NAME';
            $rsUsers = CUser::GetList($by, $order, $filter);
            if ($rsUsers) $result = $rsUsers->Fetch();
            else $result = $rsUsers;
            return $result;
            # 'Result:<pre>'.print_r($result,1).'</pre>'; # ->Fetch();
            # else return 'Not found';
        }
        else {
            $rsUsers = AppEnv::$db->select(PM::T_USERS,['fields'=>'userid,usrname LAST_NAME,firstname FIRST_NAME,secondname, usremail EMAIL',
              'where'=>['email'=>$email]
            ]);
            return ($rsUsers[0] ?? FALSE); # 'No CUSER, Bitrix not active';
        }
    }
    # пытаюсь найти учетку по Фамилии Имени Отчеству - сначала в CMS, затем у себя
    public static function getUserByFullname($fullName, $onlyActive=TRUE) {
        $btxDb = (defined('BITRIX_DB') ? constant('BITRIX_DB') : '');
        $nmParts = preg_split("/[ ]/",$fullName, -1, PREG_SPLIT_NO_EMPTY);
        if($btxDb) {
            $where = ['LAST_NAME'=> $nmParts[0]];
            if(!empty($nmParts[1])) $where['NAME'] = $nmParts[1];
            if(!empty($nmParts[2])) $where['SECOND_NAME'] = $nmParts[2];
            $where[] = "(EMAIL IS NOT NULL AND EMAIL<>'')"; # с пустым Email неинтересно
            if($onlyActive) $where['ACTIVE'] = 'Y';
            $foundRec = AppEnv::$db->select("$btxDb.b_user", ['where'=>$where, 'orderby'=>'ID DESC']);
            # writeDebugInfo("in Btx: ", AppEnv::$db->getLastQuery(), AppEnv::$db->sql_error(), " result:", $foundRec);
            if(is_array($foundRec) && count($foundRec)) return $foundRec;
        }
        $where = ['usrname'=> $nmParts[0]];
        if(!empty($nmParts[1])) $where['firstname'] = $nmParts[1];
        if(!empty($nmParts[2])) $where['secondname'] = $nmParts[2];
        $where[] = "(usremail IS NOT NULL AND usremail<>'')"; # с пустым Email неинтересно
        if($onlyActive) $where['b_blocked']=0;
        $foundRec = AppEnv::$db->select(PM::T_USERS, ['where'=>$where, 'orderby'=>'userid DESC']);
        return $foundRec;
    }
    # Полное Фамилия Имя Отчекство по ИД юзера
    public static function getUserFullname($userid) {
        $dta = self::getUserInfo($userid);
        $ret = ($dta['lastname'] ?? '') . ' '.($dta['firstname'] ?? '') . ' '.($dta['secondname'] ?? '');
        $ret = \RusUtils::mb_trim($ret);
        return $ret;
    }

}
# error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
# ini_set('display_errors', 1);
