<?PHP
/**
* @package ACL management subsystem
* @name waRolemanager.php - calculating effective user rights from his roles
* @Author Alexander Selifonov <alex [at] selifan {dot} ru>
* @link http://www.selifan.ru
* @version 0.61.002 started 20.07.2012
* @uses CDbEngine class implemented in as_dbutils.php
* modified 2025-11-25
**/
class WaRolemanager {
    const RTYPE_CHECKBOX = 1;
    const RTYPE_SELECT   = 2;
    private $_cachedAllRoles = -1;
    private static $_instance = null;
    private static $_roleFilter = null;
    private static $db = null;
    private static $_locstrings = array(
       'title-creating-objects' =>'Creating ACL data objects...'
      ,'Yes' => '[Yes]'
      ,'No'  => '[No]'
    );
    var $_acl_tprefix  = ''; # table prefix for ACL tables (acl_roles, acl_rolerights, acl_rightdef etc)

    public function __construct($options=null) {
        if($options) {
            $this->SetParams($options);
        }
        self::$_instance = $this; # for getInstance()
        if (isset(webApp::$db)) {
            self::$db = webApp::$db;
            # WriteDebugInfo('using webApp::$db',self::$db);
        }
        else {
            global $as_dbengine;
            self::$db =& $as_dbengine;
        }
        # WriteDebugInfo('__construct, db is ',self::$db);
    }

    public function SetParams($options) {
        if(is_array($options)) {
            if(isset($options['tableprefix']))   $this->_acl_tprefix = $options['tableprefix'];
        }
        elseif(is_string($options)) $this->_acl_tprefix = trim($options);
    }
    public static function getLocalized($id) {
        $ret = '';
        if(is_callable('appEnv::getLocalized')) $ret = appEnv::getLocalized($id);
        if($ret) return $ret;
        return isset(self::$_locstrings[$id]) ? self::$_locstrings[$id] : "[$id]";
    }
    /**
    * Creates tables for ACL management
    *
    */
    public function createDbObjects($createRecords=false) {
        # acl_roles, acl_rolerights, acl_rightdef, acl_userroles
        echo '<br>'.self::getLocalized('title-creating-objects') . '<br>';
        $sql = array(
             'DROP TABLE '.$this->_acl_tprefix. 'acl_roles' # roles list
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_rolerights' # rights list in every role
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_rightdef'   # right definitions
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_userroles'   # roles assigned to users (many2many)
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_locum'   # temporary delegated roles

            ,'acl_roles' => "CREATE TABLE {$this->_acl_tprefix}acl_roles ( roleid INT(20) NOT NULL AUTO_INCREMENT,rolename VARCHAR(40) NOT NULL DEFAULT '',
               roledesc VARCHAR(80) NOT NULL DEFAULT '', PRIMARY KEY(roleid))"

            ,'acl_rolerights' => "CREATE TABLE {$this->_acl_tprefix}acl_rolerights ( rrid INT(20) NOT NULL AUTO_INCREMENT,roleid INT(20) DEFAULT 0,
               rightid INT(20) DEFAULT 0, rightvalue VARCHAR(20) DEFAULT '', grantable INT(1) default 0, PRIMARY KEY(rrid), KEY ix_role(roleid))"

            ,'acl_rightdef' => "CREATE TABLE {$this->_acl_tprefix}acl_rightdef ( rdefid INT(20) NOT NULL AUTO_INCREMENT,rightkey VARCHAR(20) NOT NULL DEFAULT '',
               rightname VARCHAR(40) NOT NULL DEFAULT '',righttype INT(2) DEFAULT 0, rightoptions VARCHAR(250) NOT NULL DEFAULT '', PRIMARY KEY(rdefid))"

            ,'acl_userroles' => "CREATE TABLE {$this->_acl_tprefix}acl_userroles ( urid INT(20) NOT NULL AUTO_INCREMENT,userid VARCHAR(20) NOT NULL DEFAULT '', roleid INT(20) DEFAULT 0"
               . ", grantable INT(1) default 0, PRIMARY KEY(urid), KEY ix_userid(userid), KEY ix_roleid(roleid) )"
            ,'acl_locum' => "CREATE TABLE {$this->_acl_tprefix}acl_locum ( lcid INT(20) NOT NULL AUTO_INCREMENT,userid VARCHAR(20) NOT NULL DEFAULT '', subjectid INT(20) DEFAULT 0,
               roleid INT(20) DEFAULT 0, datefrom DATE NOT NULL DEFAULT 0, dateto DATE NOT NULL DEFAULT 0,
               PRIMARY KEY(lcid), KEY ix_userid(userid), KEY ix_subjectid(subjectid) )"
        );
        foreach($sql as $id => $sqlitem) {
          $result = self::$db->sql_query($sqlitem);
          if(is_string($id)) {
            echo "<br>$id : " .( self::$db->sql_error() ? self::$db->sql_error() : 'OK');
          }
        }
        if($createRecords) {
            $sql = array(
              'create basic rights' => "INSERT INTO {$this->_acl_tprefix}acl_rightdef (rdefid,rightkey,rightname,righttype,rightoptions)
               (1,'sysadmin','System Manager','1',''),(2,'useredit','User Editor','1',''),(3,'reader','Common Data View','1','')"
             ,'create basic roles' => "INSERT INTO {$this->_acl_tprefix}acl_roles (roleid,rolename,roledesc)
               (1,'System Administrator','System administration, whole system access')"
             ,'filling roles with rights' => "INSERT INTO {$this->_acl_tprefix}acl_rightdef (rrid,roleid,rightid,rightvalue)
               (1,'1','1', '1')"
            );
            foreach($sql as $id => $sqlitem) {
              $result = self::$db->sql_query($sqlitem);
              if(is_string($id)) {
                echo "<br>$id : " .( self::$db->sql_error() ? self::$db->sql_error() : 'OK');
              }
            }
        }
    }

    public function upgradeDbObjects() {

        echo '<br>'.self::getLocalized('title-upgrading-objects') . '<br>';
        $sql = array(
            'acl_rolerights' => "ALTER TABLE {$this->_acl_tprefix}acl_rolerights ADD grantable INT(1) NOT NULL DEFAULT 0"
            ,'acl_userroles' => "ALTER TABLE {$this->_acl_tprefix}acl_userroles ADD grantable INT(1) NOT NULL DEFAULT 0"

        );
        foreach($sql as $id => $sqlitem) {
          $result = self::$db->sql_query($sqlitem);
          if(is_string($id)) {
            echo "<br>$id : " .( self::$db->sql_error() ? self::$db->sql_error() : 'OK');
          }
        }
    }
    public function dropDbObjects() {
        $sql = array(
             'DROP TABLE '.$this->_acl_tprefix. 'acl_roles'
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_rolerights'
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_rightdef'
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_userroles'
            ,'DROP TABLE '.$this->_acl_tprefix. 'acl_locum'
        );
        foreach($sql as $id => $sqlitem) {
          $result = self::$db->sql_query($sqlitem);
        }
    }
    public function getTableNames() {
        return array(
             'acl_roles'      => $this->_acl_tprefix. 'acl_roles'
            ,'acl_rolerights' => $this->_acl_tprefix. 'acl_rolerights'
            ,'acl_rightdef'   => $this->_acl_tprefix. 'acl_rightdef'
            ,'acl_userroles'  => $this->_acl_tprefix. 'acl_userroles'
            ,'acl_locum'      => $this->_acl_tprefix. 'acl_locum'
        );
    }
    public function getTablePrefix() { return $this->_acl_tprefix; }

    public static function getInstance($options=null) {
      if (null === self::$_instance) {
          self::$_instance = new self();
      }
      if($options) self::$_instance->SetParams($options);
      return self::$_instance;
    }

    # returns ID's of all roles granted to user
    public function getUserRoles($userid) {
        $ret = self::$db->GetQueryResult($this->_acl_tprefix.'acl_userroles','roleid',array('userid'=>$userid),true);
        return $ret;
    }

    # returns all rights granted to the role in form array(array(right_id, access_level),...)
    # $getname if true, return keynames instead of integer key values
    public function getRoleRights($roleid, $getname=true) {
        $flist = $getname ? 'k.rightkey,rightvalue' : 'rightid,rightvalue';
        /*
        $rdata = self::$db->GetQueryResult(
            ($this->_acl_tprefix.'acl_rolerights r,'.$this->_acl_tprefix.'acl_rightdef k')
            ,$flist,"r.roleid=$roleid AND k.rdefid=r.rightid",1,1);
        */
        $rdata = self::$db->select( ['r'=> $this->_acl_tprefix.'acl_rolerights', 'k'=> $this->_acl_tprefix.'acl_rightdef'],
            ['fields'=> $flist,'where' => "r.roleid=$roleid AND k.rdefid=r.rightid"]);
        # writeDebugInfo("rights SQL: ", AppEnv::$db->getLastQuery());
        # writeDebugInfo("rights rdata: ", $rdata);
        $ret = array();
        if(is_array($rdata)) foreach($rdata as $rd) {
            if(!is_numeric( $rd['rightvalue'])) continue; # странная запись "Супероперационист"
            if($getname) $ret[$rd['rightkey']] = $rd['rightvalue'];
            else $ret[$rd['rightid']] = $rd['rightvalue'];
        }
        return $ret;
    }
    /**
    * Applies possibly non-empty roles filter, that must be in $_SESSION['_roles_filter_']
    *
    * @param array role data: [0] - roleid, [1] - rolename, [2] - roledesc
    * @returns true if role is OK, false if role is filtered away (not grantable)
    */
    public function applyRoleFilter($param) {
        global $auth;
        if(!empty($auth->userid)) {
            if($auth->isSuperAdmin()) {
                return true; # admin alwais has full access to all roles
            }
            if (self::$_roleFilter === null) {
                self::$_roleFilter = $this->getGrantableRoles($auth->userid);
            }
        }
        return (is_array(self::$_roleFilter) and in_array($param[0],self::$_roleFilter));
    }

    /**
    * Returns assoc. array with all right values addressed by their "keys" (not numeric ID)
    *
    * @param mixed $param user id OR array with all roles to merge
    * @return mixed
    */
    public function getUserRights($param) {
        $ret = array();
        $roles = is_scalar($param) ? $this->getUserRoles($param) : $param;
        if(!is_array($roles) || !count($roles)) return array();
        if(is_scalar($param)) {
            # search for "locum" (temporary deputy) for the user
            $locumPeople = self::$db->GetQueryResult($this->_acl_tprefix.'acl_locum','subjectid,roleid',
            "userid='$param' AND (datefrom=0 OR datefrom<=SYSDATE()) AND (datefrom=0 OR datefrom>=SYSDATE())",1,0);
            if(is_array($locumPeople)) foreach($locumPeople as $lsubj) {
                if($lsubj[0]) {
                    $add_roles = $this->getUserRoles($lsubj[0]);
                    if(is_array($add_roles)) foreach($add_roles as $arole) { if(!in_array($arole,$roles)) $roles[] = $arole; }
                }
                if($lsubj[1] && !in_array($lsubj[1],$roles)) { $roles[] = $lsubj[1]; }
            }
        }
        foreach($roles as $ro) {
            $rlist = $this->getRoleRights($ro);
            # WriteDebugInfo('getUserRights: rights for role ',$ro,'=',$rlist);
            if(is_array($rlist) && count($rlist)) foreach($rlist as $rid => $value) {
                if($value>0) $ret[$rid] = isset($ret[$rid]) ? max($ret[$rid], $value) : $value; # Get biggest value for the right from all roles
            }
        }
        return $ret;
    }
    /**
    * Renders HTML code for active user rights levels.
    *
    * @param mixed $userparam if scalar, treated as userid, if array, treated as roles array ('role'=>level form)
    */
    public function showUserRightsVerbose($userid, $rights = 0) {
        global $auth;
        $ret = '';

        $roles = $this->getUserRoles($userid);
        $rlist = is_array($roles) ? implode(',',$roles) : '0';
        $roles = self::$db->getQueryResult($this->_acl_tprefix.'acl_userroles u,'.$this->_acl_tprefix.'acl_roles r'
          ,'u.roleid,u.grantable,r.rolename,r.roledesc',"u.userid='$userid' AND u.roleid IN($rlist) AND r.roleid=u.roleid",1,1);
        if(!is_array($roles)) $roles = array();

        if($auth) {
            $prole = $auth->getPrimaryRole($userid, true);
            if($prole) {
                $roledef = self::$db->getQueryResult($this->_acl_tprefix.'acl_roles'
                    ,'roleid,0 `grantable`,rolename,roledesc',"roleid='$prole'",0,1);
                array_unshift($roles, $roledef);
                $rlist = ($rlist==='0') ? $prole : "$prole,$rlist";
            }
        }
        $yes = self::getLocalized('Yes');
        if(is_array($roles)) {
            $ret .= '<h4 class="p-3">' . self::getLocalized('acl_title_your_roles')
              . '<table class="table table-striped table-hover table-bordered"><tr><th>'
              . self::getLocalized('acl_identifier') .'</th><th>'
              . self::getLocalized('acl_description')
              . '</th><th>' . self::getLocalized('acl_grantable') . '</th></tr>';
            foreach($roles as $role) {
                $ret .= "<tr><td>$role[rolename]</td><td>$role[roledesc]</td><td class='ct'>". ($role['grantable']?$yes:' ').'</td></tr>';
            }
            $ret .= '</table><br>';
        }
#        return $ret;
        $rlistArr = is_scalar($rights) ? $this->getUserRights(explode(',',$rlist)) : $rights;

        $ret .= self::getLocalized('acl_right_list') . '<table class="table table-striped table-hover">';

        if(is_array($rlistArr)) foreach($rlistArr as $rkey=>$rval) {

            $rdef = self::$db->GetQueryResult($this->_acl_tprefix.'acl_rightdef','*',array('rightkey'=>$rkey),0,1);
            $rtitle = $rdef['rightname'];
            $valtitle = $rval;
            if($rdef['righttype'] == self::RTYPE_CHECKBOX) $valtitle = '&nbsp;'; #($rval ? self::getLocalized('Yes') : self::getLocalized('No')); # TODO: localize
            else { # RTYPE_SELECT
                $options = self::decodeOptions($rdef['rightoptions']);
                if(isset($options[$rval])) $valtitle = $options[$rval];
            }
            $ret .= "<tr><td> $rdef[rightkey] </td><td>$rtitle</td><td>$valtitle</td></tr>";
        }
        # TODO: show temporary granted roles from acl_locum
        $ret .= '</table>';
        return $ret;
    }
    # creates assoc.array from "options" string like "1:normal;2:another options;3:One more..."
    public static function decodeOptions($stroptions) {
        $splt = preg_split('/[;,\|]+/',$stroptions,-1,PREG_SPLIT_NO_EMPTY );
        $ret = array();
        foreach($splt as $item) {
            $sp2 = preg_split('/[:=]+/',$item,-1,PREG_SPLIT_NO_EMPTY );
            $ret[$sp2[0]] = isset($sp2[1]) ? $sp2[1] : $sp2[0];
        }
        return $ret;
    }

    # returns assoc/array roleid=>rolename, for creating select bvox etc.
    public function getAllRolesList($all=false) {

#        WriteDebugInfo("getAllRolesList($all)...");
        if($this->_cachedAllRoles === -1) {
            $dta = self::$db->GetQueryResult($this->_acl_tprefix . 'acl_roles','roleid,rolename,roledesc','',1,0,0,'rolename');
            $this->_cachedAllRoles = array();
            if(is_array($dta)) foreach($dta as $item) {
                if(!$all and !$this->applyRoleFilter($item)) {
                   # WriteDebugInfo('пропускаю роль ',$item);
                    continue;
                }
                $this->_cachedAllRoles[] = array($item[0], $item[2] . ' ('.$item[1].')');
            }
        }
        return $this->_cachedAllRoles;
    }
    /**
    * getting only roles that user can grant
    * (in assoc array roleid => 'role description'
    * @param mixed $userid - user ID
    * @param $filter - if only specific roles must be shown
    * @param $skipList - "modules" to skip (not include to result)
    */
    public function getGrantableRolesList($userid=0, $filter='', $skipList = FALSE) {
        global $auth;
        # if(!empty($auth) and $auth->getAccessLevel('admin'))
        $cond = [];
        if(!empty($filter)) {
            if(is_string($filter)) $cond[] = $filter;
            elseif(is_array($filter)) $cond = $filter;
        }
        /*
        else {
            $grnt = $this->getGrantableRoles($userid);
            if(!is_array($grnt) or count($grnt)<1) return array();
            $cond = 'roleid in(' . implode(',',$grnt) . ')';
        }
        */
        # $dta = self::$db->GetQueryResult($this->_acl_tprefix . 'acl_roles','roleid,rolename,roledesc',$cond,1,1,0,'rolename');
        $dta = self::$db->select($this->_acl_tprefix . 'acl_roles', ['fields'=>'roleid,rolename,roledesc', 'where'=>$cond,'orderby'=>'roledesc']);
        # writeDebugInfo("roles SQL ", self::$db->getLastQuery());
        $ret = [];
        if(is_array($dta)) foreach($dta as $item){
            if(is_array($skipList)) {
                list($module) = preg_split("/[\s\_\-]+/",$item['rolename'], -1,PREG_SPLIT_NO_EMPTY);
                # $module = $arLst[0];
                if(in_array($module,$skipList)) continue;
            }
            $ret[$item['roleid']] = $item['roledesc'] . ' ('.$item['rolename'].')';
        }
        return $ret;
    }
    /**
    * Returns ID list of all roles that user can grant
    *
    * @param mixed $userid User ID
    * @returns plain array
    */
    public function getGrantableRoles($userid) {

        $dt = self::$db->getQueryResult($this->_acl_tprefix . 'acl_userroles','roleid',
            array('grantable'=>1, 'userid'=>$userid),1,0,0,'roleid');
        return $dt;
    }
    public function getRoleDesc($id) {
        $dt = self::$db->getQueryResult($this->_acl_tprefix . 'acl_roles','roledesc',array('roleid'=>$id));
        return empty($dt)? $id: $dt;
    }
    public function getRoleName($id) {
        $dt = self::$db->getQueryResult($this->_acl_tprefix . 'acl_roles','rolename',array('roleid'=>$id));
        return empty($dt)? $id: $dt;
    }
}