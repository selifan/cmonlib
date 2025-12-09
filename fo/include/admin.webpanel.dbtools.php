<?php
/**
* @package admin.webpanel
* @name admin.webpanel.dbtools.php
* Plugin panel : dbtools - misc. tools for MySQL data
* @author Alexander Selifonov <as-works@narod.ru>, <alex@selifan.ru>
* @copyright Alexander Selifonov <alex@selifan.ru>
* @link http://www.selifan.ru
* @version 0.8.2
* modified 2017-04-12 GAGARIN RULES!
*/
class awp_dbtools extends WebPanelPlugin {

    const DEFAULT_TMP_FOLDER = 'tmp/';
    static $debug = 0;
    static $tableSets = array();
    static $tables = array();
    static $upgrade_class = 'astedit.php';
    static $db_engines = array('MYISAM','INNODB'); # engines for MySQL db
	static $master_cfg = array(
//	  'clientcabtest'=> array('host'=>'host_or_ip','dbname'=>'my_db','user'=>'username', 'password'=>'pass')
	); # Master db server(s) config
    public static function addTableSet($id, $set) {
        self::$tableSets[$id] = $set;
    }
    # Setting one master server parameters ('host','port','dbname','login','password' as keys in assoc.array)
    public static function setMasterServer($masterid, $cfg = false) {
        # if (self::$debug) WriteDebugInfo("set master :$masterid");
        if (is_array($cfg))
    		 self::$master_cfg[$masterid] = $cfg;
    	else unset(self::$master_cfg[$masterid]);
	}

	# Add all servers from assoc.array
    public static function addMasterServers(array $cfgarr) {
    	self::$master_cfg = array_merge(self::$master_cfg, $cfgarr);
	}
    public static function addTables($list) {
        if(is_array($list)) self::$tables = array_merge(self::$tables, $list);
    }
    public static function addTableSets($setarray) {
        if (is_array($setarray)) self::$tableSets = array_merge(self::$tableSets, $setarray);
    }

    public static function init() {
        if(!is_array(self::$tables) or count(self::$tables)<1) self::$tables = WebPanel::getGlobalOptions('tablelist');
        if(!is_array(self::$tables) or count(self::$tables)<1) self::$tables = WebPanel::$db->GetTableList();
        if(!is_array(self::$tableSets) or count(self::$tableSets)<1) self::$tableSets = WebPanel::getGlobalOptions('tablesets');
    }

    public function getJsCode($options=false) {

        self::init();
        $folders = array();
        $backend = WebPanel::getBaseUri();
        $admin = (WebPanel::getAdminLevel()>1) ? 'true':'false';

        $msg = array(
           'lab_01' => WebPanel::localize('confirm_delete', 'Delete selected files ?')
        );

        $sets = '';
        if (is_array(self::$tableSets)) foreach(self::$tableSets as $id=>$set) {
            $sets .= "       ,'$id': ['" . implode("','",$set)."']\n";
        }

        $ret = <<< EOJS

awp_dbtools = {
    pageid: 0
   ,tbSets: {  '0': []
$sets
    }
   ,toggleAllTables: function(obj, pageid) {
        $('input[id^=tb_]', '#fm_awp_dbtools_'+pageid).each(function() { this.checked = obj.checked; });
   }
    ,setTableSet: function(obj, pageid) {
        this.pageid = pageid;
        var vsel = jQuery(obj).val();
        if(vsel !== '0') {
           $('#awp_dbtools_all_'+pageid).prop('checked',false);
        }

        $('input[id^=tb_]', '#fm_awp_dbtools_'+pageid).each(function() {
            var ifind = awp_dbtools.tbSets[vsel].indexOf(this.value);
            this.checked = (ifind>-1);
        });
    }
   ,chgOperation: function(obj,pageid) {
      var vOper = jQuery(obj).val();
      jQuery('span[id^=dbtools_oper_'+pageid+']').hide();
      jQuery('#dbtools_oper_'+pageid+'_'+vOper).show();
   }
   ,sendRequest : function(pageid) {
      var voper = jQuery('select[name=action]','#fm_awp_dbtools_'+pageid).val();
      var doit = true;
      if (voper === 'drop')  doit = confirm('Really drop tables ?');
      else if (voper === 'truncate')  doit = confirm('Really truncate tables ?');
      else if (voper === 'getfrommaster')  doit = confirm('Current data will be rewritten ! Continue ?');
      if(!doit) return;

      var params = jQuery('#fm_awp_dbtools_'+pageid).serialize();
      jQuery.post('$backend',params, function(response) { //<3>
          var spl = response.split("\\t");
          if(spl.length < 2) {
            alert(response);
          }
          else {
            awpUtil.addResponse('awp_dbtools_result_'+spl[0], spl[1]);
          }
      });
      return false;
   }
};
EOJS;
        return $ret;
    }

    # method returns HTML code for client interface page
    public function drawPage($options=array()) {
#        WriteDebugInfo(get_class($this),' drawPage options:',$options);
        self::init();
        $pageid = isset($options['page'])? $options['page'] : -1;
        if($pageid==-1 && isset($options[0])) $pageid = $options[0];

        $lwidth = isset($options['width'])? $options['width']: 800;
        $lheight = isset($options['height'])? $options['height']: 600;
        $r_width = $lwidth;
        $r_height = max(80, intval($lheight*0.30));
        $rest_h = max(40, $lheight - $r_height-109);

        $bckpfolder = false;
        $tblistfile = '';

        if(isset($options['backup_folder'])) $bckpfolder = $options['backup_folder'];

        $html = "<form name=\"fm_awp_dbtools_$pageid\" id=\"fm_awp_dbtools_$pageid\">";
        $html .= "<input type='hidden' name=\"wp_plugin\" value=\"awp_dbtools\"/>";
        $html .= "<input type='hidden' name=\"pageid\" value=\"$pageid\"/>";
        $loc01 = WebPanel::localize('label_all_tables','All tables');
        $loc02 = WebPanel::localize('label_choose_set','Choose a set');
        $loc03 = WebPanel::localize('label_table_set','Predefined Table Set');
        $locUpgr = WebPanel::localize('label_upgrade_tables','Create/Upgrade Table(s)');
        $locOpt  = WebPanel::localize('label_optimize_tables','Optimize table(s)');
        $locCset = WebPanel::localize('label_change_charset_tables','Change Charset');
        $locConv = WebPanel::localize('label_convert_data','Convert data');
        $ignoreLoss = WebPanel::localize('label_ignore_loss','Ignore data loss');
        $locEngine = WebPanel::localize('label_set_engine','Change db engine');
        $locTrunc = WebPanel::localize('label_truncate_tables','Truncate table(s)');
        $locRename = WebPanel::localize('label_rename_tables','Rename table(s)');
        $locDrop  = WebPanel::localize('label_drop_tables','Drop table(s)');
        $locgetMaster  = WebPanel::localize('label_get_from_master','Replicate from Master');
        $locExec = WebPanel::localize('label_execute','Execute');
        # ...
        $label_result = WebPanel::localize('label_result','Result');
        $engoptions = '';
        foreach(self::$db_engines as $en) {
            $engoptions .= "<option value='$en'>$en</option>";
        }
        $html .= <<< EOHTM

<div id="dbtools_checkbox_$pageid" class="bounded">
  <input type="checkbox" name="_all_tables_" id="awp_dbtools_all_$pageid" value="1" onclick="awp_dbtools.toggleAllTables(this,$pageid)" /><label for="awp_dbtools_all_$pageid"> $loc01</label>

EOHTM;

        if(is_array(self::$tableSets) && count(self::$tableSets)) {
            $html .= "&nbsp; <select id='dbtools_tableset{$pageid}' onchange=\"awp_dbtools.setTableSet(this,$pageid)\" style='width:200px'><option value=\"0\"> -- $loc02 --</option>";
            foreach(self::$tableSets as $id=>$item) {
                $html .= "<option value='$id'>$id</option>";
            }
            $html .= '</select> ' . $loc03;
        }

        $html .= "</div><div class=\"bounded\" style='overflow:auto; height:{$r_height}px;'><div style='max-width:{$lwidth}px;'>"
            . "<table><tr>";
        $colcnt = 0;
        $colsperrow = max(4, intval($r_width/140));

        foreach(self::$tables as $kkp=>$tbname) {
            $colcnt++;
            $simplename = WebPanel::$db->getRealTableName($tbname);
            if($colcnt> $colsperrow ) { $colcnt=1; $html .= "\n</tr><tr>\n"; } // 4 parameter per line
            $html .= "<td nowrap><input type='checkbox' name='tb[]' id='tb_{$simplename}' value='$tbname'><label for='tb_{$simplename}'> $simplename</label> </td>\n";
        }
        $html .= "</tr></table>";
        $optUpgrade = is_file(dirname(__FILE__).'/'.self::$upgrade_class) ?
           '<option value="upgrade">' . $locUpgr . '</option>' : '';
        $option_master = (count(self::$master_cfg)> 0) ? "<option value=\"getfrommaster\">$locgetMaster</option>" : '';

        $html_master = '';
        if (count(self::$master_cfg)> 0) { # build selection for "master server id"
            $masteroptions = '';
            foreach(self::$master_cfg as $mid => $mopt) {
            	$masteroptions .= "<option>$mid</option>\n";
            }
           	$html_master = "<span id=\"dbtools_oper_{$pageid}_getfrommaster\" style=\"display:none;\">"
              . "<select name='repl_masterid' style='min-width:100px; max-width:350px'>$masteroptions</select> "
              . "&nbsp;<label><input type='checkbox' name='repl_checkmaster' value='1' /> test connection (no real import)</label>"
              . "&nbsp;<label><input type='checkbox' name='repl_recreate' value='1' /> Re-create table(s)</label></span>";
		}
        $html .= <<< EOHTM
 </div>
</div>
<div class='bounded'>
<select name="action" id="dbtools_seloper_$pageid" onchange="awp_dbtools.chgOperation(this,$pageid)" style="min-width:200px">
  $optUpgrade
  <option value="optimize">$locOpt</option>
  <option value="setcharset">$locCset</option>
  <option value="convertdata">$locConv</option>
  <option value="setengine">$locEngine</option>
  <option value="rename">$locRename</option>
  <option value="truncate">$locTrunc</option>
  <option value="drop">$locDrop</option>
  $option_master
</select>
<span id="dbtools_oper_{$pageid}_setcharset" style="display:none;">
  <input type="text" name="newcharset" class="ibox" style="width:160px" placeholder="new charset name" />
</span>
<span id="dbtools_oper_{$pageid}_convertdata" style="display:none;margin-top:16px">
  to <input type="text" name="cn_newcharset" class="ibox" style="width:160px" placeholder="convert to charset" />
<!--  <label style="position:relative; top:4px"><input type="checkbox" name="cn_ignore_loss" value="1" />$ignoreLoss</label> -->
</span>
<span id="dbtools_oper_{$pageid}_setengine" style="display:none;">
  <select name="enginename" style="width:160px" >
  $engoptions
  </select>
</span>
<span id="dbtools_oper_{$pageid}_rename" style="display:none;">
  From: <input type="text" name="ren_from" class="ibox" style="width:160px" placeholder="name part to change" title='If empty, new value will be inserted at the beginning of names'/>&nbsp;
  To: <input type="text" name="ren_to" class="ibox" style="width:160px" placeholder="rename to" />
</span>
$html_master
&nbsp;
 <div style="float:right; text-align:right">
   <input type='button' class='btn btn-primary' id='runbackup' onclick='awp_dbtools.sendRequest($pageid);return false' value="$locExec" />
 </div>
</div>
</form>
<div class="bounded" style="margin-bottom:1px">$label_result <input type="button" class="btn btn-primary" onclick="awpUtil.clearLog('awp_dbtools_result_$pageid')" value="clear"/></div>
<div id="awp_dbtools_result_$pageid" class="resultarea" style="overflow:auto; height:{$rest_h}px; text-align:left"></div>
<!-- page [$pageid] /awp_dbtools  end -->
EOHTM;

        return $html;

    }

    /**
    *  performs action according passed params (came from request)
    * and returns response that will be sent to client (AJAX)
    * @param $params merged GET & POST params. If not passed, might be calculated on the fly
    */
    public function executeAction($params=false) {
        # WriteDebugInfo(get_class($this), ' executeAction params:', $params);
        if (!is_object(WebPanel::$db) OR !method_exists(WebPanel::$db, 'sql_query'))
            return ('Please connect CDbEngine DB wrapper class with sql_query()!');

        # if (self::$debug) WriteDebugInfo("executeAction, paramsL:", $params);

        $pageid = isset($params['pageid'])? $params['pageid'] : '0';
        $action = isset($params['action'])? $params['action'] : '';
        $tables = isset($params['tb'])? $params['tb'] : array();
        if (count($tables)<1) {
            return WebPanel::localize('err_no_tables_selected','No tables selected');
        }
        if (in_array($action, array('upgrade','setcharset','drop','convertdata'))) {
            include_once(self::$upgrade_class);
        }

        $response = '';

        switch($action) {
            case 'upgrade':
                $response = Astedit::upgradeTables($tables, true);
                break;

            case 'setcharset':
                $response = Astedit::changeCharSet($tables, $params['newcharset'],true);
                break;

            case 'convertdata': # @since 0.7 change charset AND convert data to NEW charset !!!

#                $ignore = !empty($params['cn_ignore_loss']);
#                $dtUtils = new adminDataUtils($GLOBALS['as_dbengine']);
#                $response = $dtUtils->convertData($tables, $params['cn_oldcharset'], $params['cn_newcharset'], $ignore);
                $response = Astedit::convertTable($tables, $params['cn_newcharset']);
                break;

            case 'drop':
                $response = Astedit::dropTables($tables, true);
                break;

            case 'setengine':
                $response = date('Y-m-d H:i:s').' - setting engine to '.$params['enginename'] . '...';
                foreach($tables as $onetb) {
                    $ok = WebPanel::$db->sql_query("ALTER TABLE $onetb ENGINE = $params[enginename]", 1);
                    $err = WebPanel::$db->sql_error();
                    $response .= '<br>' . "$onetb : " . ($err ? $err : 'change engine OK');
                }
                break;

            case 'optimize':
                $response = date('Y-m-d H:i:s').' - optimizing tables...';
                foreach($tables as $onetb) {
                    $ok = WebPanel::$db->sql_query("OPTIMIZE TABLE $onetb", 1);
                    if(isset($ok[0])) $response .= "<br>$ok[0] - $ok[3]";
                    else $response .= "<br>$onetb : " .print_r($ok,1);
                }
                break;

            case 'truncate':
                $response = date('Y-m-d H:i:s').' - truncating tables...';
                foreach($tables as $onetb) {
                    $ok = WebPanel::$db->sql_query("TRUNCATE TABLE $onetb", 1);
                    $err = WebPanel::$db->sql_error();
                    $response .= '<br>' . "truncate $onetb : " . ($err ? $err : 'OK');
                }
                break;

            case 'rename':
                $from = trim($params['ren_from']);
                $to = trim($params['ren_to']);
                $response .= "from $from - to $to<br>";
                foreach($tables as $onetb) {
                    if ($from ==='') $newtb = $to.$onetb;
                    else $newtb = str_replace($from, $to, $onetb);
                    if ($newtb === $onetb) $responce .= "<br>$onetb - skipped (the same name !)";
                    else {
                        $ok = WebPanel::$db->sql_query("RENAME TABLE $onetb TO $newtb", 1);
                        $err = WebPanel::$db->sql_error();
                        $response .= '<br>' . "rename $onetb to $newtb : " . ($err ? $err : 'OK');
                    }
                }
                break;

            case 'getfrommaster':
                $response = $this->_doGetFromMaster($tables, $params, $pageid);
                break;
        }

        return "$pageid\t" . $response;
#        return "$pageid\t" . ob_get_clean()."Operation parameters :<pre>".print_r($params,1).'</pre>'; # debug

    }

    private function _doGetFromMaster($tables, $params, $pageid) {

        $masterid = trim($params['repl_masterid']);
        $checkconn = !empty($params['repl_checkmaster']);
        $recreate = !empty($params['repl_recreate']);
        $response = "Direct importing data from <b>$masterid</b>...<br>";
        try {
            $host = isset(self::$master_cfg[$masterid]['host'])? self::$master_cfg[$masterid]['host'] : '';
            if (!empty(self::$master_cfg[$masterid]['port']) && !empty(self::$master_cfg[$masterid]['host']))
                $host .= ':' . self::$master_cfg[$masterid]['port'];
            $user = isset(self::$master_cfg[$masterid]['user'])? self::$master_cfg[$masterid]['user'] : '';
            $password = isset(self::$master_cfg[$masterid]['password'])? self::$master_cfg[$masterid]['password'] : '';
            $dbname = isset(self::$master_cfg[$masterid]['dbname'])? self::$master_cfg[$masterid]['dbname'] : '';
            $dbobj = new CDbEngine(0, $host,$user,$password,$dbname);
		}
		catch (Exception $e) {
		   $response .= 'Connect to <b>$masterid</b> failed: ' .$e->getMessage();
		   return $response;
		}
		if (!$dbobj->isConnected()) {
		   	$response .= 'Connect to <b>$masterid</b> failed: ' .$dbobj->sql_error();
			return $response;
		}
		if ($checkconn) {
			return "<b>$masterid</b>: Connection tested OK";
		}

        # force UTF8 mode
		$dbobj->sql_query('SET NAMES UTF8');
        $response .= "UT8 mode activated<br>";
        foreach($tables as $onetb) {

            $crtSql = $dbobj->sql_query("SHOW CREATE TABLE $onetb",1);
            if (empty($crtSql[1])) {
            	$response .= "$onetb does not exist in Master DB<br>";
            	continue;
			}
            if ($recreate) {
                $result = WebPanel::$db->sql_query("DROP TABLE IF EXISTS $onetb");
                $result = WebPanel::$db->sql_query($crtSql[1]);

                $response .= "table $onetb re-creating : ".WebPanel::$db->sql_error();
                if (WebPanel::$db->sql_error()) {
                    $response .= "error: ".WebPanel::$db->sql_error() . "<br>Task aborted! Please restore $onetb from backup";
                    break;
				}
				else $response .= "OK...<br>";
			}
			else {
                if (WebPanel::$db->IsTableExist($onetb)) {
                    $result = WebPanel::$db->sql_query("TRUNCATE TABLE $onetb");
                    $err = WebPanel::$db->sql_error();
                    if ($err) {
                	    return "$onetb: Truncating data error $err, task aborted!";
				    }
                }
                else {
                    $result = WebPanel::$db->sql_query($crtSql[1]);
                    $response .= "table $onetb re-creating : ".WebPanel::$db->sql_error();
                    if (WebPanel::$db->sql_error()) {
                        $response .= "error: ".WebPanel::$db->sql_error() . "<br>Task aborted! Please restore $onetb from backup";
                        break;
                    }
                }
			}
			# $response .= "Create $onetb SQL:<pre>" . print_r($crtSql[1],1).'</pre><br>' . $dbobj->sql_error();

			$lnk = $dbobj->sql_query("SELECT * FROM $onetb");
			$added = 0;
			while(($lnk) && ($row = $dbobj->fetch_assoc($lnk))) {
                $this->_prepareRow($row);
				if (WebPanel::$db->insert($onetb, $row)) $added++;
				else {
					$response .= "Inserting data error : " . WebPanel::$db->sql_error()
						. "<br>Task aborted. Please restore table $onetb from backup<br>";
					break;
				}
			}
			$response .= "$onetb: Added records: $added<br>";
        }

        $dbobj->Disconnect();
        return $response;
	}
	# Makes "binary" content ready to using in SQL statement
    private function _prepareRow(&$r) {
		foreach ($r as $key=> &$val) {
			$val = addslashes($val);
		}
    }
} // awp_dbtools end

# not used:
class adminDataUtils {

    private $db = NULL;
    static $supported_sets = array(
       'UTF8'
      ,'CP1251'
    );
    public function __construct ($dbconn) {
        $this->db = $dbconn;
    }
    /**
    * Fully Converts table from "old" charset to new, altering CHARACTER SET option for the table
    *
    * @param mixed $tables array with all table names to be converted
    * @param mixed $oldset original charset
    * @param mixed $newset new charset
    * @param mixed $ignore ignore data loss that may occure brcause of "unpaired" non-utf charsets
    */
    public function convertData($tables, $oldset, $newset, $ignore=FALSE) { # MySQL only supported for now!
        $ret = array();
        if (empty($newset)) return 'New character set not entered!';
        $newset = strtoupper($newset);
        if (!in_array($newset, self::$supported_sets)) return "Unsupported charset entered: $newset";

        foreach($tables as $tbname) {
            $ddl = $this->db->sql_query("SHOW CREATE TABLE $tbname",TRUE,0,1);
            $origcset = $oldset;
            $crtsql = $ddl[0][1];
              $pattern = "/DEFAULT CHARSET=([A-Za-z0-9]+)/i";
              $result = preg_match($pattern,$crtsql, $match);
              if (!empty($match[1]) && empty($oldet)) $origcset = $match[1]; # grabbed character set from CREATE TABLE
#              WriteDebugInfo('match for ', $crtsql, ' ::', $match);

            $meta = $this->db->sql_query("describe $tbname",TRUE,1,1);
            $crt_tmp = preg_replace("/\b$tbname\b/i", "_tmp_$tbname", $crtsql);
            $sql = array();
            $sql[] = $crt_tmp;
            $ret[] = "table <b>$tbname</b> from $origcset TO $newset: <pre>" . print_r($match,1) . "</pre>"; # <pre> ".print_r($meta,1). '</pre>';
        }
#        $ret[] = '<pre>' . print_r($this->db,1) . '</pre>';
        return implode('<br>',$ret);
    }
}