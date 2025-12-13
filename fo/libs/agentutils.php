<?php
/**
* @package ALFO
* @name libs/agentutils.php
* @version 1.02.002 created: 2021-12-09
* Утилиты работы с данными агентов/коучей/кураторов, проверки прав ПП агентов/банков
* modified 2025-11-27
*/
namespace Libs;
class AgentUtils {
    static $debug = 0;
    static $debugUserId = -1; # 6750 - Ховрачева  8614 - Зверев
    static $agtListAddFromManagerId = 1; # в список агентов для менеджера добавить еще все учетки, у которых нужный manager_id
    /**
    * Готовлю блок данных для вывода в "подвале" печатных форм - данные об агенте, оформившем полис
    * @param mixed $dta массив исходных данных, в него же и заносить!
    */
    public static function getAgentPrintData(&$dta, $deptid = 0) {
        if (is_array($dta)) {
            $userid = $dta['userid'];
            $deptid = $dta['deptid'];
        }
        else {
            $userid = $dta;
        }
        $unfo = \CmsUtils::getUserInfo($userid);
        $ret = [];
        if (isset($unfo['lastname'])) {
            $ret['agent_fio'] = MakeFio($unfo['lastname'], $unfo['firstname'], $unfo['secondname']);
            $ret['agent_fullname'] = "$unfo[lastname] $unfo[firstname] $unfo[secondname]";
        }
        if (!empty($deptid)) {
            $ret['agent_deptname'] = \appEnv::findDeptProp('dept_name', $deptid);
            $ret['agent_offdeptname'] = \appEnv::findDeptProp('official_name', $deptid);
            $ret['agent_city'] = \appEnv::findDeptProp('city', $deptid);
        }
        if (empty($dta['agent_city'])) {
            $region = \appEnv::findDeptProp('region', $deptid);
            if ($region === 'msk') $ret['agent_city'] = 'Москва';
        }
        if (!empty($unfo['agentno'])) # код агента проставлен УЗ в Битрикс
            $ret['agent_code'] = $unfo['agentno'];

        if (!empty($_SESSION['debug_me'])) writeDebugInfo("getAgentPrintData result: ", $ret);

        if (is_array($dta)) $dta = array_merge($dta, $ret);
        return $ret;
    }

    public static function getAgentCode($userid) {
        $ret = \appEnv::$db->select(\PM::T_USERS, ['where' => ['userid'=>$userid],
          'fields'=>'agentcode','singlerow'=>1,'associative'=>0
        ]);
        return $ret;
    }
    public static function getCuratorId($param) {
        if (is_numeric($param)) $where = ['id' => $param];
        else $where = [ 'fullname'=>(string)$param ];
        $dta = \appEnv::$db->select(\PM::T_CURATORS, ['where'=>$where, 'fields'=>'id,fullname', 'singlerow'=>1]);
        return (isset($dta['id']) ? $dta['id'] : 0);
    }

    # рисую код на форме просмотра - просмотр текущего лид-агента плюс кнопка для смены
    public static function viewLeadAgentCode($dt, $canChange=FALSE) {
        $ret = "<tr><td>Агент по договору</td><td id='td_setagent'>";
        $right = self::tdLeadAgentHtml($dt, $canChange);
        return ($ret . $right . "</td></tr>");
    }
    public static function tdLeadAgentHtml($dt, $canChange=FALSE) {
        $info = [];
        if (!empty($dt['agentid'])) {
            $info = \CmsUtils::getUserInfo($dt['agentid']);
        }
        if (!empty($info['lastname'])) $ret = "$info[lastname] $info[firstname] $info[secondname]";
        else $ret = "Не выбран";

        if($canChange && empty($dt['docflowstate']) && !in_array($dt['stateid'], [\PM::STATE_BLOCKED,\PM::STATE_CANCELED, \PM::STATE_ANNUL])) {
            $ret .= '<span style="float:right"><input type="button" class="btn btn-primary" value="Изменить" onclick="investprod.setPolicyAgent()"></span>';
        }
        return $ret;
    }
    /**
    * Получить ИД коуча/куратора, если учетка с заданным ИД является коучем/куратором
    *
    * @param mixed $userid ИД учетки, либо FALSE, если надо для текущего пользователя
    * @param mixed $fullInfo передать TRUE|1 если нужен массив с полными данными куратора
    * @return ID по справочнику кураторов/коучей
    */
    public static function userIsCurator($userid=FALSE, $fullInfo = FALSE) {
        if($userid <=0) $userid = \appEnv::$auth->userid;
        if ( !$userid || !is_numeric($userid) ) return FALSE;
        $fullInfo = intval($fullInfo);
        $cacheKey = "usrCurator-$userid-$fullInfo";

        if (!isset(\appEnv::$_cache[$cacheKey])) {
            \appEnv::$_cache[$cacheKey] = FALSE;
            $usrDta = \appEnv::$db->select(\PM::T_USERS,[
              'where'=>['userid'=> $userid],
              'fields'=>'winaccount,winuserid,code_ikp','singlerow'=>1
            ]);
            if (!empty($usrDta['code_ikp'])) {
                $coucheDta = \appEnv::$db->select(\PM::T_CURATORS,[
                  'where' => [ 'ikp'=>$usrDta['code_ikp'] ],
                  'fields' => ($fullInfo ? '' : 'id,b_curator,b_couche'),
                  'singlerow' => 1
                ]);

                if (!empty($coucheDta['id']) && ($coucheDta['b_curator']>0 || $coucheDta['b_couche']>0))
                    \appEnv::$_cache[$cacheKey] = ($fullInfo) ? $coucheDta : $coucheDta['id'];
            }
        }
        return \appEnv::$_cache[$cacheKey];
    }
    /**
    * получить список ИД+ФИО агентов заданного куратора, для формирования SELECT-box выбора
    *
    * @param mixed $curatorId ID куратора (из справочника кураторов, а не из базы пользователей!)
    */
    public static function getAgentsForCuratorNone($curatorId) {
        # $cacheKey = "CuratorChilds-$curatorId";
        $agDta = \appEnv::$db->select(\PM::T_USERS,[
          'where' => [ 'manager_id'=>$curatorId ],
          'fields' => 'userid,fullname',
          'orderby' => 'fullname'
        ]);
        $ret = [['0','--не выбран--']];
        if (is_array($agDta) && count($agDta))
            $ret = array_merge($ret ,$agDta);

        return $ret;
    }

    # Получить всю запись о кураторе из справочника кураторов
    public static function getCuratorData($id) {
        $ret = \appEnv::$db->select(\PM::T_CURATORS,[
          'where' => [ 'id'=>$id ],
          'singlerow' => 1
        ]);
        return $ret;
    }
    # добавляю поля агента для выгрузки в XML(LISA integration)
    public static function fieldsForExport(&$data) {
        $plcid = $data['stmt_id'];
        $userid = $data['userid'];
        $headDept = $data['headdeptid'];

        $metaType = \OrgUnits::getMetaType($headDept);
        if ($metaType == \PM::META_AGENTS) {
            # номер агента заношу только если полис выпущен юзером из агентской сети.
            $usr = \CmsUtils::getUserInfo($userid);
            if (!empty($usr['agentno'])) {
                $data['agentno'] = $usr['agentno'];
                # \writeDebugInfo("added agent AD: ", $usr['agentno']);
            }
        }
    }

    # {upd/2029-09-25} получить ИД коуча для данного подразделения (банковский сектор)
    public static function getUserCurator($userid = 0) {
        if(!$userid) $userid = \AppEnv::getUserId();
        $ret = \appEnv::$db->select(\PM::T_USERS, ['where'=>['userid'=>$userid],
          'fields'=>'manager_id','singlerow'=>1,'associative'=>0]
        );

        return $ret;
    }
    # {upd/2029-09-25} получить ИД коуча для данного подразделения (банковский сектор)
    public static function getDeptCouch($deptid, $what = FALSE) {
        $arDta = \OrgUnits::getNestedUnitAttribs($deptid, ['couche_id']);
        $couchId = !empty($arDta['couche_id']) ? $arDta['couche_id'] : 0;
        return $couchId;
    }

    # проверка прав, для контроля доступа к договорам и отчетам агентов
    public static function isAgentSupport() {
        $level1 = \AppEnv::$auth->getAccessLevel('lifeag_oper');
        $level2 = \AppEnv::$auth->getAccessLevel('nsj_oper');
        $level3 = \AppEnv::$auth->getAccessLevel('irisky_oper');
        $level4 = \AppEnv::$auth->getAccessLevel('oncob_oper');
        $level5 = \AppEnv::$auth->getAccessLevel('pochvo_oper');
        $summLevel = max($level1,$level2, $level3, $level4, $level5);
        # writeDebugInfo("lifeag_oper: $level1, nsj_oper:$level2");
        if($summLevel >=10) return TRUE;
        $myMeta = \OrgUnits::getMetaType();
        if($summLevel == \PM::LEVEL_IC_ADMIN) return ($myMeta == \OrgUnits::MT_AGENT);
        return FALSE;

    }
    # проверка прав, для контроля доступа к отчетам по агентам и тд
    public static function isAgentReports() {
        $level1 = \AppEnv::$auth->getAccessLevel('lifeag_reports');
        $level2 = \AppEnv::$auth->getAccessLevel('nsj_reports');
        $level3 = \AppEnv::$auth->getAccessLevel('irisky_reports');
        $summLevel = max($level1,$level2, $level3);
        # writeDebugInfo("isAgentReports: $level");
        if($summLevel >=10) return TRUE;
        $myMeta = \OrgUnits::getMetaType();
        if($summLevel == \PM::LEVEL_IC_ADMIN) return ($myMeta == \OrgUnits::MT_AGENT);
        return FALSE;
    }
    # проверка прав, для контроля доступа к сопровожд./изменению полисов в банковском канале
    public static function isBankSupport() {
        $level1 = \AppEnv::$auth->getAccessLevel('nsj_oper');
        $level2 = \AppEnv::$auth->getAccessLevel('irisky_oper');
        $level3 = \AppEnv::$auth->getAccessLevel('bank:oper');
        # $level4 = \AppEnv::$auth->getAccessLevel('lifeag_oper');
        $summLevel = max($level1,$level2, $level3);
        # writeDebugInfo("isAgentReports: $level");
        if($summLevel >=10) return TRUE;
        $myMeta = \OrgUnits::getMetaType();
        if($summLevel == \PM::LEVEL_IC_ADMIN) return ($myMeta == \OrgUnits::MT_BANK);
        return FALSE;
    }
    # проверка прав, для контроля доступа к отчетам в банковском канале
    public static function isBankReports() {
        $level1 = \AppEnv::$auth->getAccessLevel('nsj_reports');
        $level2 = \AppEnv::$auth->getAccessLevel('irisky_reports');
        $level3 = \AppEnv::$auth->getAccessLevel('bank:reports');
        # $level4 = \AppEnv::$auth->getAccessLevel('lifeag_reports');
        $summLevel = max($level1,$level2, $level3);
        # writeDebugInfo("isAgentReports: $level");
        if($summLevel >=10) return TRUE;
        $myMeta = \OrgUnits::getMetaType();
        if($summLevel == \PM::LEVEL_IC_ADMIN) return ($myMeta == \OrgUnits::MT_BANK);
        return FALSE;
    }

    public static function getCuratorFullName($recid) {
        $sRet = \appEnv::$db->select(\PM::T_CURATORS,[
          'where' => [ 'id'=>$recid ],
          'fields'=>'fullname','singlerow'=>1,
          'associative'=>0
        ]);
        return $sRet;
    }
    /**
    * Получить список всех УЗ, входящих подразделение и его дочки
    *
    * @param mixed $deptid ИД подразделения
    * @param mixed $rolename идентификатор роли, которая должна быть у агента
    * @param mixed $skipMe TRUE если себя в список включать не надо
    * @param mixed $onlyActive TRUE только действующие учетки (пока не реализовано!)
    * @param mixed $keyVal TRUE вернуть в виде [ID => 'Fullname',...] , иначе - [ [ID, Fullname],... ]
    * @param mixed $mgrId ИД менеджера, если еще надо отобрать учетки с таким manager_id
    */
    public static function getAllUsersInDept($deptid=0,$rolename='', $skipMe = FALSE, $onlyActive=FALSE, $keyVal=TRUE, $mgrId=FALSE) {
        global $clcabDB, $bitrixDB;
        if (empty($bitrixDB)) $bitrixDB = (defined('BITRIX_DB') ? constant('BITRIX_DB') : 'cc20prd');
        if(!$deptid) {
            $deptid = \AppEnv::getUserDept();
        }
        $deptList = \OrgUnits::getDeptsTree($deptid,'',TRUE,TRUE);
        $myid = \AppEnv::getUserId();
        $exCond = $skipMe ? "AND u.userid<>'$myid'" : '';
        $strDepts = implode(',' ,$deptList);
        $roleid = ($rolename ? \AppEnv::$db->select('arjagent_acl_roles',[ 'fields'=>'roleid', 'where'=>['rolename'=>$rolename],'singlerow'=>2,'associative'=>0]) : 0);
        $fldList = 'u.userid,u.fullname';
        $tblList = ['u'=>\PM::T_USERS];
        $where = "(u.deptid IN($strDepts)" . (($mgrId>0) ? " OR (manager_id=$mgrId)" : '') . ')';

        if($skipMe) $where .= " AND u.userid<>'$myid'";

        if($roleid) {
            $tblList['ro'] = 'arjagent_acl_userroles';
            $where .= " AND ro.userid=u.userid AND ro.roleid=$roleid";
        }

        if($onlyActive) { # надо еще прицепить поле с признаком активности!
            if(constant('IN_BITRIX')) { # выясняю активность УЗ по b_user.ACTIVE в битрикс
                $tblList['bu'] = "$bitrixDB.b_user";
                $where .= " AND (bu.ID=u.bitrix_id) AND (bu.ACTIVE='Y')";
            }
            else { # по полю b_blocked
                $where .= " AND (b_blocked<>1)";
            }
        }

        $accounts = \AppEnv::$db->select($tblList,
          [ 'distinct'=>1,
            'fields'=>$fldList,
            'where'=>$where,
            'orderby'=>'u.fullname',
            'associative'=>0,
          ]);
        # writeDebugInfo("SQL for users in depts, mgrId=[$mgrId]: ", \AppEnv::$db->getLastQuery(), "\n    err: ", \AppEnv::$db->sql_error());
        if($keyVal) {
            $arRet = [];
            foreach($accounts as $row) { $arRet[$row[0]] = $row[1]; }
            return $arRet;
        }
        return $accounts;
    }
    /**
    * {upd/2025-11-20} Если текущий пользователь - менеджер в данном модуле $module,
    * вернет список агентов, от имени которых может оформлять полисы в данном модуле
    * @param mixed $module
    * @param mixed $right_oper имя роли ЛИБО "*" если надо вернуть всех агентов непзависимо от наличия роли
    */
    public static function amIManagerWithAgents($module, $onlyActive=TRUE, $right_oper='', $forUser=FALSE) {
        if($forUser>0) {
            $userid = $forUser;
            $userDept = \AppEnv::$db->select(\PM::T_USERS,['fields'=>'deptid',
              'where'=>['userid'=>$userid],'singlerow'=>1, 'associative'=>0]);
        }
        else {
            $userid = \AppEnv::getUserId();
            $userDept = \AppEnv::getUserDept();
        }
        if(empty($userDept)) return NULL;
        if($userid == self::$debugUserId) self::$debug = 1;
        if(self::$debug) writeDebugInfo("seek for user $userid/ dept $userDept");
        $mgrRightName = \AppEnv::$_plugins[$module]->ROLE_MANAGER ?? $module.'_manager';
        if(self::$debug) writeDebugInfo("module: [$module], check right id: ", $mgrRightName);
        if(!isset(\AppEnv::$_plugins[$module])) return NULL;
        if($right_oper === '*') $operRightName = ''; # хочу ВСЕХ агентов без учета наличия роли
        else $operRightName = empty($right_oper) ? (\AppEnv::$_plugins[$module]::ROLE_OPER ?? $module.'_oper') : $right_oper;
        $mgrId = FALSE;
        if(self::$agtListAddFromManagerId) {
            $myFio = \AppEnv::$db->select(['u'=>\PM::T_USERS],['where'=>['userid'=>$userid],
              'fields'=>'fullname', 'singlerow'=>1, 'associative' => 0
            ]);

            if(!empty($myFio)) {
                $mgrId = \AppEnv::$db->select(\PM::T_CURATORS, ['fields'=>'id', 'where'=>['fullname'=>$myFio],
                  'singlerow'=>1, 'associative'=>0]);
            }
        }

        $arRet = self::getAllUsersInDept($userDept,$operRightName, TRUE, $onlyActive, TRUE, $mgrId);
        if(self::$debug) writeDebugInfo("$module/агенты($right_oper/$operRightName): ", $arRet);
        if(is_array($arRet) && count($arRet)) return $arRet;
        return 0;
    }
    # вернет список УЗ у которых в manager_id сидит ИД меня как куратора (поиск "меня" в таблице кураторов - по ФИО!)
    public static function getMyAgentAsCurator($userid=0) {
        if(!$userid) $userid = \AppEnv::getUserId();
        $myFio = \AppEnv::$db->select(['u'=>\PM::T_USERS],['where'=>['userid'=>$userid],
          'fields'=>'fullname', 'singlerow'=>1, 'associative' => 0
        ]);
        # $myFio = 'Ховрачева Анна Павловна'; # debug!
        $myagents = \AppEnv::$db->select(['u' => \PM::T_USERS],
          ['fields'=>'u.userid,u.fullname',
           'where'=>"u.manager_id IN (select id FROM ". \PM::T_CURATORS . " WHERE fullname='$myFio')",
           'orderby'=>'u.fullname']);
        # writeDebugInfo("SQL: ", \AppEnv::$db->getLastQuery(), ' ERR:', \AppEnv::$db->sql_error() );
        if(is_array($myagents) && count($myagents)) {
            $arRet = [];
            foreach($myagents as $row) {
                $arRet[$row['userid']] = $row['fullname'];
            }
            return $arRet;
        }
        return FALSE;
    }
}