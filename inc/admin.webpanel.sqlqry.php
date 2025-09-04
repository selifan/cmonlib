<?php
/**
* @package admin.webpanel
* @name admin.webpanel.sqlqry.php
* Plugin for mamaging DBMS (executing SQL queries, exporting record sets)
* @author Alexander Selifonov <alex [at] selifan {dot} ru>
* @copyright Alexander Selifonov
* @version 1.13.002
* modified 2025-04-24
*/
class awp_sqlqry extends WebPanelPlugin {
    const ASADM_QRYPARAM = 4;
    const ASADM_SQRYWHEIGHT = 100; // SQL query textarea height (px)
    const EXPORT_TYPE_XLSX = 'xlsx'; // xlsx: "Excel2007"
    const EXPORT_TYPE_PDF = 'pdf'; //

    static $RECORDS_LIMIT = 200;
    static $INCLUDE_DBNAME = 0; # exported data will have dbname.tablename-xxxxx.txt format, including db base. FALSE - w/out dbname
    private static $cb_afterUpdate = FALSE; # CallBack ti be executed after "updating" queries
    static $sheet_writers = array('xlsx' => 'Excel2007', 'pdf' => 'PDF');
    private $to_sheet = false;
    static $saveUsrQueries = FALSE; # using save/laod user queries
    const EXPORT_DATE_FORMAT = 'dd.mm.yyyy';

    # static $as_adm_qryaccess = 0; // SUPER-ACCESS (can UPDATE/DELETE/INSERT/DROP...)
    static private $stdsqls = array();
    static private $exportFolder = '';
    static $xls_export = false;
    private static $idiotProtect = TRUE; # protect from silly DELETE/UPDATE SQL without WHERE condition
    private $_expfiles = array();
    private $_pageid = '';
    private $operation = ''; # what operation called
    private $_filetype = 'txt';
    private $_fileext = '.txt';
    private $_explainPlan = FALSE;

    private $objPHPExcel = null;
    private $xlsSheet = null;
    private $_qryidx = 0;
    /*
    public static function setQueryAccess($access) {
        self::$as_adm_qryaccess = intval($access);
    }
    */
    public static function setXlsSupport($value = true) {
        self::$xls_export = $value;
    }

    // setting records limit for html output result
    public static function setRecordLimit($value) {
        self::$RECORDS_LIMIT = $value;
    }

    /**
    * activate "after update" callback
    * @param mixed $usrFunc name of callback function. Must receive 3 parameters:
    * "table_name", "executed SQL query"$sqlQuery"='', "number_of_affected_rows" = 0
    */
    public static function setAfterUpdateCallback($usrFunc) {
        self::$cb_afterUpdate = $usrFunc;
    }

    public static function setExportFolder($path) {
        self::$exportFolder = $path;
    }

    public static function addStdQueries($qrylist) {
        if(is_array($qrylist)) {
            foreach ($qrylist as $no => $qryitem) { # if query string is array, convert it to "qry1{LF}/{LF}qry2 ..."
                if (!empty($qryitem[1]) && is_array($qryitem[1])) $qryitem[1] = implode('{LF}/{LF}', $qryitem[1]);
                self::$stdsqls[] = $qryitem;
            }
            # self::$stdsqls = array_merge(self::$stdsqls,$qrylist);
        }
        elseif(is_file($qrylist)) {
            $tlst = file($qrylist);
            foreach($tlst as $strk) {
                $strk = trim($strk);
                if($strk=='' || $strk[0]=='#') continue;
                $tval = explode('|',$strk);
                if(count($tval)<2) continue;
                // if(!empty($tval[1]))
                self::$stdsqls[] = $tval; //[0] = $tval[1];
            }
        }
    }

    public function getJsCode($options=false) {
        $parmEnd = self::ASADM_QRYPARAM;
        $bkuri = WebPanel::$baseURI;
        $errNoQryName = WebPanel::localize('err_no_query_id','Empty Query name!');
        $arQryNameLst = (class_exists('UserParams') ? \UserParams::getSpecParamNames(0,'usersql') : FALSE);
        if (is_array($arQryNameLst) && count($arQryNameLst) ) {
            foreach($arQryNameLst as &$item) {
                $item = str_replace("'", "\\'", $item);
            }
            $usrQueryNames = "'" . implode("','",$arQryNameLst) . "'";

        }
        else $usrQueryNames = '';
        # awp_sqlqry.usrQryNames
        $js = <<< EOJS

/* js code for awp_sqlqry pages */
awp_sqlqry = {
   bkend: '$bkuri'
  ,curPage : 0
  ,stdsqls : [] // awp_sqlqry.stdsqls array for predefined queries
  ,subpars : [] // awp_sqlqry.subpars
  ,usrQryNames : [$usrQueryNames]
  ,changeStdQry: function(pageid,obj) {
      var fm = $("#as_admt_sqlform_"+pageid);
      var iqry = obj.selectedIndex;
      var newval = (iqry<=0) ? 'select * from' : awp_sqlqry.stdsqls[iqry][0].replace(/{LF}/g,"\\r\\n");
      $('#asadm_sqltext'+pageid).val(newval);
      for(var ik=1; ik<=$parmEnd; ik++) {
        $('#sqprm_'+pageid+'_'+ik).html( ((iqry<=0 || awp_sqlqry.stdsqls[iqry][ik]==undefined)? ('&amp;P'+ik) : awp_sqlqry.stdsqls[iqry][ik]) );
      }
      if(typeof(awp_sqlqry.subpars[pageid][iqry])!='undefined') { $('input[name=subpars]',fm).val(awp_sqlqry.subpars[pageid][iqry]); }
  }
 ,runSqlQry : function(pageid,oper,_btnobj_) {
      var fm = document.getElementById("as_admt_sqlform_"+pageid);
      var sqltxt = $("#asadm_sqltext"+pageid).val();
      if(sqltxt=='') { alert('empty sqltext'); return false; }
      var params = 'wp_plugin=awp_sqlqry&pageid=' + pageid + '&'+$("#as_admt_sqlform_"+pageid).serialize();
      var selectedTxt = getSelectedText('asadm_sqltext'+pageid);
      if(selectedTxt !='') params+='&subqry='+encodeURIComponent(selectedTxt);
      if(oper) params += '&oper='+oper; //explain|export sub-cmd
      $(_btnobj_).prop('disabled',true);
      $.post(this.bkend, params, function(data) { //<3>

          $(_btnobj_).removeAttr('disabled');
          var spl = data.split("{|}");
          if(spl.length < 2) {
            $('#sqlresult_'+pageid).html('msg_wrongreply: <br>'+data);
          }
          else {
            $('#sqlresult_'+pageid).html(spl[1]);
          } //<4>
      }); //<3>
      return false;
  }
  ,saveUsrQry : function(pageid,_btnobj_) {
    awp_sqlqry.curPage = pageid;
    var qrname = $("#usrqry_"+pageid).val();
    var qry = $("#asadm_sqltext"+pageid).val();
    if(qrname == '') {
      showMessage("ERROR", "$errNoQryName", "msg_error");
      return;
    }
    var params = { wp_plugin:"awp_sqlqry", "pageid": pageid, "oper": "saveUserQuery",
      "qryname": qrname, "sql": qry };
    $.post(this.bkend, params, function(data) { //<3>
      var splt = data.split("|");
      if(splt[0] === "1") { // Qry added/updated
        $("#usrqry_"+splt[1]).val(splt[2]);
        $("#sqlresult_"+splt[1]).append(splt[3]+"<br>");
        if(awp_sqlqry.usrQryNames.indexOf(splt[2])==-1)
          awp_sqlqry.usrQryNames.push(splt[2]);
      }
      else if(splt[0] === "2") { // deleted
        $("#usrqry_"+splt[1]).val("");
        $("#sqlresult_"+splt[1]).append(splt[3]+"<br>");
        var nOff = awp_sqlqry.usrQryNames.indexOf(splt[2]);
        if(nOff>=0) awp_sqlqry.usrQryNames.splice(nOff,1);
      }
      else showMessage("Error", data, "msg_error");
    }); //<3>
    return false;
  }
  ,loadUsrQry : function(pageid,_btnobj_) {
    awp_sqlqry.curPage = pageid;
    var qrname = $("#usrqry_"+pageid).val();
    if(qrname == '') {
      showMessage("$errNoQryName", "msg_error");
      return;
    }
    var params = { "wp_plugin":"awp_sqlqry","pageid":pageid,"oper":"loadUserQuery",
      "qryname":qrname};
    $.post(this.bkend, params, function(data) { //<3>
      var splt = data.split("|");
      if(splt[0] === "1") {
        pageid = splt[1];
        $("#asadm_sqltext"+pageid).val(splt[2]);
      }
      else showMessage("Error", data, "msg_error")
    }); //<3>
    return false;

  }
  ,getFile: function(fname) {
      var pref = (this.bkend.indexOf('?')<0)?'?':'&';
      window.open('$bkuri'+pref+'wp_plugin=awp_sqlqry&oper=getfile&name='+fname,'_blank');
  }
  ,delFile: function(fname, objid){
      $.post(this.bkend, {wp_plugin:'awp_sqlqry',oper:'delfile','name': fname, divid:objid}, function(data){
          var sp = data.split('|');
          if(sp[0] === 'OK') $('#'+sp[1]).text(sp[2]);
          else  alert(data);
      });
  }
};
jQuery(document).ready(function() {
  $('input.in_usr_qryname').each(function() {
    $(this).autocomplete({source: awp_sqlqry.usrQryNames, minLength:0});
    var vid = this.id.substr(7);
    $("#openqrylist_"+vid).on('click', function() { $("#usrqry_"+vid).autocomplete("search", "");});
  });
});
EOJS;

        if(count(self::$stdsqls)>0) {
            $js .= "\n\nawp_sqlqry.stdsqls = [], awp_sqlqry.subpars = [];\n";
            $km=1;
            foreach (self::$stdsqls as $kk=>$stdsql) {
                $key = $stdsql[0];
                $subpars = '';
                if ( isset($stdsql[1]) && strlen($stdsql[1])>1 ) {
                    $allval = '"' .$stdsql[1] .'"';
                    for($nn=2;$nn<=self::ASADM_QRYPARAM+1;$nn++) {
                        if(isset($stdsql[$nn]) ) {
                            if($stdsql[$nn][0]==='#') $subpars .= ($subpars===''? '':'|').$stdsql[$nn];
                            else $allval .= ',"' .$stdsql[$nn] .'"';
                        }
                    }
                    $js .= "awp_sqlqry.stdsqls[$km] = [$allval];\nawp_sqlqry.subpars[$km] = \"$subpars\";\n";
                    $km++;
                }
            }

        }

        return $js;
    }

    # method should return HTML code for client interface page
    public function drawPage($options=array()) {
        # WriteDebugInfo("drawPage/$padeid options:", $options);
        $userid = (class_exists('appEnv') ? AppEnv::getUserId() : 0);
        self::$saveUsrQueries = (class_exists('UserParams') && !empty($userid));

        $lwidth = isset($options['width'])? $options['width']: 800;
        $width_result = max(100,$lwidth-6);
        $lheight = isset($options['height'])? $options['height']: 600;
        $htop = max(140, intval($lheight*0.3));
        $rest_h = max(80, $lheight - 160);
        $pageid = isset($options['page'])? $options['page'] : -1;
        if($pageid==-1 && isset($options[0])) $pageid = $options[0];
        $locResult = WebPanel::localize('label_result','Result');
        $locQry = WebPanel::localize('label_queries','SQL query(ies)');
        $locPset = WebPanel::localize('label_preset','Preset');
        $locExprt = WebPanel::localize('label_export_to_files','Export to file(s)');
        $saveUsrQry = WebPanel::localize('label_save_user_qry','Save query');
        $loadUsrQry = WebPanel::localize('label_load_user_qry','Load query');
        $locExec = WebPanel::localize('label_execute','Execute');
        $html = <<< EOHTM
<form name="as_admt_sqlform_$pageid" id="as_admt_sqlform_$pageid">
<input type='hidden' name='subpars' value='' />
<div class="bounded"><table><tr>
EOHTM;
        if(count(self::$stdsqls)>0) {
            $html .= "<td>$locPset<br><select name='stdqry' id='stdqry$pageid' style='width:240px' onchange='awp_sqlqry.changeStdQry($pageid,this)'>
            <option value='0'>----</option>";
            $ingroup = false;
            foreach(self::$stdsqls as $kk=>$stdsql) /* as $kname=>$kval)*/ {
                $kname = $stdsql[0];
                if(!empty($stdsql[1])) {
                    $html .= "<option value='$kname'>$kname</option>";
                } else {
                    if($ingroup) $html .= '</optgroup>';
                    $html .= "<optgroup label='$kname'>";
                }
            }
            if($ingroup) $html .= '</optgroup>';

            $html .= "</select></td>";
        }
        for($kkp=1; $kkp<=self::ASADM_QRYPARAM; $kkp++) {
            if($kkp>4 && ($kkp % 6 ==1)) { $rest_h -=36; echo "</tr><tr>"; } // NN parameter per line
            $html .= "<td><span id='sqprm_{$pageid}_{$kkp}'> &amp;P{$kkp}</span><br><input type='text' name='qparm{$kkp}' class='ibox' style='width:100px'></td>\n";
        }
        $html .= "</tr></table></div>"; # pre-set qry + params block ended

        $attrib = (WebPanel::getAdminLevel()>=1)? '':'readonly="readonly"';
        // with $as_adm_qryaccess=0 user won't even see SQL query text - just parameter fields
        if(WebPanel::getAdminLevel()>0) {
            $rest_h -= $htop+16;
            # {$lwidth}px
            $html .= "<div class='bounded' style='text-align:left; padding:0.5em;'>$locQry<br>"
              . "<textarea name='sqltext' id='asadm_sqltext{$pageid}' class='ibox' style=\"resize: vertical; width:99%; height:{$htop}px {$attrib}\" >"
              . "select * from </textarea></div>";
        }
        else {
            $html .= "<input type='hidden' name='sqltext' id='asadm_sqltext{$pageid}' value=''>";
        }
        # buttons...
        $html .= "<div class='bounded' style='text-align:left;padding-left:0.4em;'><table><tr><td><input type='button' class='btn btn-primary' name='runsql' onclick='awp_sqlqry.runSqlQry($pageid,0, this);return false' value='$locExec'/> &nbsp;</td>";
        $sheetOption = '';
        if (self::$xls_export || WebApp::isModuleInPath('PHPExcel.php')) {
            # if(class_exists('PHPExcel_Reader_Excel2007'))
                $sheetOption .= "<option value='xlsx'>XLSX</option>";
                # $sheetOption .= "<option value='pdf'>PDF</option>";
        }
        if(WebPanel::getAdminLevel()>=1) {
            $html .= "<td><input type='button' class='btn btn-primary' name='expsql' onClick=\"awp_sqlqry.runSqlQry($pageid,'explain',this);return false\" value='Explain Plan'/> &nbsp;</td>"
                  . "<td class='bounded'><table class='noborder'><tr><td><select name='filetype' id='filetype{$pageid}' style='width:80px'><option value='txt'>TXT</option>" . $sheetOption
                  . "<option value='sql'>SQL inserts</option>"
                  . "<option value='js-str'>JS string array</option>"
                  . "<option value='js-obj'>JS object array</option>"
                  . "<option value='php-arr'>PHP assoc. array</option>"
                  . "</select>"
                  . "&nbsp; <input type='button' class='btn btn-primary' name='sqltotxt' onclick='awp_sqlqry.runSqlQry($pageid,2,this);return false' value='$locExprt' /></td>"
                  . "</tr></table></td>";
            if(self::$saveUsrQueries) {
                $html .= "<td class='bounded'><table class='noborder'><tr><td><input type='text' class='ibox w100 in_usr_qryname' placeholder='Qry_Name' id='usrqry_{$pageid}'/>"
                  . "<td><span id=\"openqrylist_{$pageid}\" class=\"ui-icon ui-icon-triangle-1-s bordered2 \"/></td>"
                  . "<td> <input type='button' class='btn btn-primary' name='saveUsrQry' onclick='awp_sqlqry.saveUsrQry($pageid,this);return false' value='$saveUsrQry' /></td>"
                  . "<td><input type='button' class='btn btn-primary' name='loadUsrQry' onclick='awp_sqlqry.loadUsrQry($pageid,this);return false' value='$loadUsrQry' /></td></tr></table></td>";
            }
        }
        $html .= "</tr></table></div>";
        $html .= "</form>\n";
        $html .= "<div class='bordered p-2 text-start'>$locResult </div><div class='bounded'>"
          . "<div id='sqlresult_$pageid' class='resultarea p-2 text-start' style='height:{$rest_h}px; overflow:auto;'>&nbsp;</div></div>";
        $html .= "<!-- page [$pageid] / awp_sqlqry end -->";
        return $html;
    }

    /**
    *  performs action according passed params (came from request)
    * and returns response that will be sent to client (AJAX)
    * @param $params merged GET & POST params. If not passed, might be calculated on the fly
    */
    public function executeAction($params=false) {
        include_once('PHPExcel.php');
        $this->_qryidx = 0;
        $ret = '';
        if(!is_array($params)) $params = decodePostData(1);
        $this->_expfiles = array();
        # WriteDebugInfo('sqlqry::executeAction params:',$params);
        $this->_pageid = $pageid = isset($params['pageid'])? $params['pageid'] : '1';
        $oper = $this->operation = isset($params['oper']) ? $params['oper'] : 'query';
        # 1|explain - explain plan, 2 - save Query result to TXT (tdf)

        if ($oper == '1' || $oper ==='explain') $this->_explainPlan = TRUE;
        $this->_filetype = isset($params['filetype'])? $params['filetype'] : 'txt';
        if( $this->_filetype === 'js-str' || $this->_filetype === 'js-obj') $this->_fileext = '.js';
        if( $this->_filetype === 'php-arr' ) $this->_fileext = '.phps';
        elseif($this->_filetype === 'sql') $this->_fileext = '.sql';
        elseif ($oper == '2' && $this->_filetype === self::EXPORT_TYPE_XLSX) {
            $this->_fileext = self::EXPORT_TYPE_XLSX;
            $this->to_sheet = true;
        }
        /* */
        elseif ($this->_filetype === self::EXPORT_TYPE_PDF) {
            $this->_fileext = self::EXPORT_TYPE_PDF;
            $this->to_sheet = true;
        }
        /* */
        if($oper === 'getfile') return $this->getFile($params);
        elseif($oper === 'delfile') return $this->deleteFile($params);
        elseif(method_exists($this, $oper)) return $this->$oper($params);

        $sqry = !empty($params['sqltext'])? $params['sqltext'] : '';
        if(!empty($params['subqry'])) $sqry = $params['subqry']; # "selected" fragment in textarea

        if (class_exists('CUser')) {
        	self::_sqlUnbrake($sqry); # F*cking Bitrix inserts space in "select", "from", etc...
		}
        $subpars = empty($params['subpars'])? '': explode('|',$params['subpars']); // additional parameters: "href columns" etc.
        $subst = array();

        $dbname = isset($params['_dbname_']) ? $params['_dbname_']: '';
        if(strlen($dbname)) { $seldb = WebPanel::$db->select_db($dbname); }
        for($kk=1 ; $kk<=self::ASADM_QRYPARAM; $kk++) { if(isset($params['qparm'.$kk])) { $subst['&P'.$kk] = $params['qparm'.$kk]; } }
        # $sqry = isset($params['sqltext'])? $params['sqltext'] : '';
        # if(!empty($params['subqry'])) $sqry = $params['subqry']; # "selected" fragment in textarea
        $sqry = str_replace(array_keys($subst), array_values($subst), $sqry);
        $sqry = trim(stripslashes($sqry));
        if(empty($sqry)) return $ret;
        $ret = "$pageid{|}";
        $qrylist = explode("/\n",$sqry);
        $qry2 = explode("/\r",$sqry);
        if(count($qry2)>count($qrylist)) $qrylist = $qry2;

        unset($qry2);

        if ($oper == '2' && $this->to_sheet && class_exists('PHPExcel')) {
            // Create XLS spreedsheet object to save results
            $this->objPHPExcel = new PHPExcel();
        }
        else { $this->objPHPExcel = null; $this->xlsSheet = null; }

        # $this->_explainPlan = empty($params['b_explain'])?false:true;
        if (count($qrylist) == 1) {
            $result = $this->runOneSql(trim($qrylist[0]),$oper,$subpars);
            if (is_array($result)) $ret .= 'Records: '. $result[0] . '<br>' . $result[1];
            else $ret .= $result;
        }
        else {
            if ($oper == 'query' || empty($oper)) { # sql data to tabs!
                # create as_propsheet "tabs set" with personal tab for every SQL query result
                include_once('as_propsheet.php');
                $sql_sheet = new CPropertySheet('wa_qsheet_'.$pageid,'100%','100%',TABSTYLE,CPropertySheet::TABS_LEFT);
            }
            foreach($qrylist as $no=>$oneqry) {
                $oneqry = trim($oneqry);
                if (empty($oneqry)) continue;
                $result = $this->runOneSql($oneqry,$oper,$subpars);
                $tbname = '1';
                if (is_array($result) && count($result)>1) { # select ... returns rows
                    $tbname = $this->getTableFromQuery($oneqry);
                    $tabtitle = (($tbname) ? $tbname : ($no+1).':') . " ($result[0])";
                    $tabBody = $result[1];
                }
                else {
                    $tabtitle = "qry ".($no+1);
                    $tabBody = $result;
                }
                $tabBody = "<div class='innergrid'>$tabBody</div>";
                if ($tbname !==false) {
	                if (empty($oper) || $oper == 'query') {
                        $sql_sheet->addPage($tabtitle,$tabBody);
                    }
	                else $ret .= $tabBody .'<br>';
				}
            }
            if ($oper==0 || $oper =='query') $ret .= $sql_sheet->draw(0,TRUE,FALSE);
        }

        if ($this->objPHPExcel) {
            // save XLS object to final file
            if ($this->_filetype === 'pdf') {
                # add tcPDF as writer !
                PHPExcel_Settings::setPdfRenderer(PHPExcel_Settings::PDF_RENDERER_TCPDF, 'tcpdf');
            }
            $writer = PHPExcel_IOFactory::createWriter($this->objPHPExcel, self::$sheet_writers[$this->_filetype]);
            # exit($this->_filetype);
            # if( $this->_filetype=== 'PDF' ) PHPExcel_Settings::setPdfRendererName('mPDF');

            $outname = 'queries-'.date('Y-m-d-his'). '.' . $this->_fileext;
            $saveFilename = self::$exportFolder . $outname;
            $writer->save($saveFilename);
            $divid = 'fl_'.$this->_pageid .'_'. rand(1000000,99999999);
            $ret .= "<div id='$divid'>File created, <input type='button' class='btn btn-primary' onclick='awp_sqlqry.getFile(\"$outname\")' value='download' /> "
                 . "&nbsp; <input type='button' class='btn btn-primary' onclick='awp_sqlqry.delFile(\"$outname\",\"$divid\")' value='Please delete after download !' /></div>";
        }
        return $ret;
    }
    # trying to grab table nme from SQL query
    public static function getTableFromQuery($qry) {

        # $ok = preg_match('/\bfrom\b\s*(\w+)/i',$qry,$matches);
        # if($ok) return $matches[1];

        $data = preg_split("/[\s,\(\)]+/",$qry,-1, PREG_SPLIT_NO_EMPTY);
        $tname = '';
        foreach($data as $no=>$elem) {
            if (in_array(strtolower($elem), ['update','from','into','table']) && $no < count($data)-1)
                { $tname = $data[$no+1]; break; }
        }
        if ($tname && !self::$INCLUDE_DBNAME) {
            $tname2 = explode('.', $tname);
            if (count($tname2)>1) return array_pop($tname2);
            else return $tname2[0];
        }
        # $ok = preg_match('/\btable\b\s*(\w+)/i',$qry,$matches);
        # if($ok) return $matches[1];

        $words = preg_split('/[\s,]+/', $qry, -1, PREG_SPLIT_NO_EMPTY);
        if (strtolower($words[0]) === 'use') return false;
        return '_noname_';
    }
    /**
    * Unbrake SQL if working under Bitrix
    *
    * @param mixed $strg "broken" SQL text
    */
    private static function _sqlUnbrake(&$strg) {
    	$strg = preg_replace("/\bsel ect\b/i", "SELECT", $strg);
    	$strg = preg_replace("/\bfr om\b/i", "FROM", $strg);
    	$strg = preg_replace("/\bcre ate\b/i", "CREATE", $strg);
    	$strg = preg_replace("/\bupd ate\b/i", "UPDATE", $strg);
    	$strg = preg_replace("/\bse t\b/i", "SET", $strg);
    	$strg = preg_replace("/\bins ert\b/i", "INSERT", $strg);
    	$strg = preg_replace("/\bin to\b/i", "INTO", $strg);
    	$strg = preg_replace("/\bwh ere\b/i", "WHERE", $strg);
        $strg = preg_replace("/\blim it\b/i", "LIMIT", $strg);
    	$strg = preg_replace("/\balt er\b/i", "ALTER", $strg);
    	# file_put_contents("_unbraked_query.log", $strg);
	}
    private function runOneSql($querytext,$submode=0, $subpars='') {
        # $tableName = 'nonameObj';
        $reccnt = 'x';
        # $tb = preg_match('/\bfrom\b\s*(\w+)/i',$querytext,$matches);
        # if($tb && count($matches)>0) $tableName = $matches[1];
        $tableName = $this->getTableFromQuery($querytext);
        $ret = $ret_title = '';

        $qarr = preg_split("/[\s,]+/",trim($querytext), -1, PREG_SPLIT_NO_EMPTY);
        $first = strtolower($qarr[0]);

        $bUpdate = (!in_array($first, array('select','show','desc','describe','explain')) && !$this->_explainPlan);
        if($bUpdate && WebPanel::getAdminLevel()<2) {
            return "UPDATES NOT ALLOWED (or $first - operator denied or unknown)";
        }
        if($bUpdate && self::$idiotProtect) {
            if(stripos($querytext, 'delete') !== FALSE || stripos($querytext, 'update') !== FALSE) {
                $where = stripos($querytext, 'where ');
                if($where === FALSE) {
                    $errMsg = 'UPDATE or DELETE operator without WHERE, denied';
                    if(class_exists('AppEnv'))
                        $errMsg = AppEnv::getLocalized('err_update_no_where', $errMsg);
                    return $errMsg;
                }
            }
        }

        $result = ($this->_explainPlan)? WebPanel::$db->sql_explain($querytext) : WebPanel::$db->sql_query($querytext);
        $reccnt = WebPanel::$db->affected_rows();
        # WriteDebugInfo('sql :', $querytext);
        # WriteDebugInfo('sql_query result :', $result);
        $maxlenghts = [];

        if($result) { //<2>

            $fhan = 0;

            if (!$bUpdate && $submode!=='query') {

                if ($this->to_sheet) {

                    $maxlenghts = [];
                    if ($this->objPHPExcel) {
                        if ( empty($this->_qryidx) ) {
                            $this->xlsSheet = $this->objPHPExcel->getActiveSheet();
                        }
                        else {
                            $this->xlsSheet = $this->objPHPExcel->createSheet($this->_qryidx);
                        }
                        $this->xlsSheet->setTitle($tableName);
                        $this->_qryidx++;
                    }
                }
                else {
                    $outfilename = "qry-$tableName-" . date('Ymd-His').rand(1000,9999). $this->_fileext;
                    $fhan = fopen(self::$exportFolder . $outfilename,'w');
                    if(!$fhan) return array(0, self::$exportFolder . WebPanel::localize(' File write error - please check folder existing'));
                    $this->_expfiles[] = $outfilename;
                }
            }
            if(($result) && !$bUpdate) { //<3> // show/export recordset
                if (!$this->objPHPExcel && !$fhan) {
                    $ret .="<table class='zebra'>\n";
                }
                $header = 0;
                $ii=0;
                $maxrec = ($fhan || $this->to_sheet)? 0 : self::$RECORDS_LIMIT;
                $rowno = 0;

                while (($row = WebPanel::$db->fetch_assoc($result)) && ($maxrec==0 || $ii<$maxrec))
                { //<4>
                   $values = array_values($row); // I'll need index-based values for HREF column composing
                   if ($this->operation == 2 && $this->to_sheet) {
                        $xlsrow = 1;
                        foreach(array_keys($row) as $colNo => $fldname) {
                            $maxlenghts[$colNo] = mb_strlen($fldname);
                            $this->xlsSheet->setCellValueByColumnAndRow($colNo, $xlsrow, $fldname);
                        }
                   }
                   else if($header < 1)
                   { //<5>
                      $header = 1;
                      if($fhan) {
                          $date = date('Y-m-d H:i:s');
                          switch($this->_filetype) {
                              case 'txt':
                                fwrite($fhan, implode("\t",array_keys($row))."\r\n");
                                break;
                              case 'js-str':
                              case 'js-obj':
                                fwrite($fhan, "/** generated by admin.webpanel.sqlqry $date **/\r\nvar $tableName = [\r\n");
                                break;
                              case 'php-arr':
                                fwrite($fhan, "<?PHP\r\n/** generated by admin.webpanel.sqlqry $date **/\r\n \${$tableName}_arr = [\r\n");
                                break;
                              case 'sql':
                                fwrite($fhan, "/** generated by admin.webpanel.sqlqry $date **/\r\n");
                                break;
                          }
                      }
                      else {
                        $ret .="<tr>"; // class='{$as_cssclass['trowhead']}'
                        foreach($row as $col_name=>$col_value) {
                            if(is_string($col_name)) $ret .="<th>$col_name</th>";
                        }
                        if(is_array($subpars)) for($ipar=0;$ipar<count($subpars);$ipar++) $ret.="<th>&nbsp;</th>";
                        if(is_array($subpars)) for($ipar=0;$ipar<count($subpars);$ipar++) $ret.="<th>&nbsp;</th>";
                        $ret .='</tr>';
                      }
                   } //<5>

                   $ii++;

                   if ($this->operation == 2 && $this->to_sheet) {
                       foreach(array_values($row) as $colNo => $cellVal) {
                           $maxlenghts[$colNo] = max($maxlenghts[$colNo], mb_strlen($cellVal)* 0.8);
                           if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $cellVal, $parts)) {
                               $dtPattern = str_replace('/','.', self::EXPORT_DATE_FORMAT);
                               $cellVal = strtr($dtPattern, array('yyyy'=>$parts[1], 'mm'=>$parts[2], 'dd'=>$parts[3]));

                               # $cellName = PHPExcel_Cell::stringFromColumnIndex($colNo) . $ii;
                               # $this->xlsSheet->getStyle($cellName)->getNumberFormat()->setFormatCode(self::EXPORT_DATE_FORMAT);
                               # $this->xlsSheet->setCellValue($cellName, $cellVal);
                               $this->xlsSheet->setCellValueByColumnAndRow($colNo, $ii+1, $cellVal);
                           }
                           else {
                               $this->xlsSheet->setCellValueByColumnAndRow($colNo, $ii+1, $cellVal);
                           }
                       }
                   }
                   elseif($this->operation == 2 && $fhan) {
                       switch($this->_filetype) {
                          case 'txt':
                              foreach($row as $key=>$val) { $row[$key] = nl2br($val); } # mask html chars ? (< > &
                              fwrite($fhan, implode("\t",$row)."\r\n");
                              break;
                          case 'js-str':
                              $akeys = array_keys($row);
                              if (count($row)<2) fwrite($fhan, ($ii>1 ? '  ,':'   ') . "'".$row[$akeys[0]] . "'\r\n");
                              else {
                                  $strk = '';
                                  foreach($row as $k=>$val) {
                                      $strk .= ($strk?',':'') . '"'.str_replace('"','\\"',$val) . '"';
                                  }
                                  fwrite($fhan, ($ii>1 ? '  ,':'   ')."[$strk]\r\n");
                              }
                              break;
                          case 'js-obj':
                              $strk = array();
                              foreach($row as $k=>$val) {
                                  $strk[] =  "$k: \"".str_replace('"','\\"',$val) . '"';
                              }
                              $strk = '{ ' . implode(',', $strk) . ' }';
                              fwrite($fhan, ($ii>1 ? '  ,':'   ')."$strk\r\n");

                              break;

                          case 'php-arr':
                              $strk = array();
                              foreach($row as $k=>$val) {
                                  $strk[] =  "'$k' => \"".str_replace('"','\\"',$val) . '"';
                              }
                              $strk = '[ ' . implode(', ', $strk) . ' ]';
                              fwrite($fhan, ($ii>1 ? '  ,':'   ')."$strk\r\n");

                              break;
                          case 'sql':
                              $fields = implode(',', array_keys($row));
                              $vals = '';
                              foreach($row as $k=>$val) {
                                  $vals .= ($vals?',':'') . "'".str_replace("'","\\'",$val) . "'";
                              }
                              fwrite($fhan, "insert into $tableName ($fields) values ($vals);\r\n");

                       }
                   }
                   else {
                       $ret .= "\n<tr>";
                       foreach($row as $col_name=>$col_value)
                       if(is_string($col_name)) $ret .= "<td>$col_value</td>";

                       if(is_array($subpars)) for($ipar=0;$ipar<count($subpars);$ipar++) {
                            $onepar = explode('^',$subpars[$ipar]);
                            $colvalue = '';
                            switch($onepar[0]) {
                                case '#HREF': $colvalue=@str_replace('{ID}',$values[$onepar[1]],$onepar[2]); break;
                                default: $colvalue=$onepar[0]; break;
                            }
                            $ret .="<td>$colvalue</td>";
                       }

                       $ret .='</tr>';
                   }
                } //<4>
                // modify column width to show long strings
                if ($this->to_sheet) {
                    foreach($maxlenghts as $colNo=>$mlen) {
                        $colName = PHPExcel_Cell::stringFromColumnIndex($colNo);
                        if (!isset($maxlenghts[$colNo])) break;
                        $len = min(80,floor(max(10, $mlen * 1.3)));
                        if ($len > 10)
                            $this->xlsSheet->getColumnDimension($colName)->setWidth($len);
                    }
                }

                WebPanel::$db->free_result($result);
                $ret_title = "$reccnt";
                if(!$fhan && !$this->xlsSheet) {
                    $ret .="</table>\n";
                    if ($ii != $reccnt) $ret_title = "$ii/$reccnt";
                    if ( intval($reccnt)==0 ) $ret = 'no data found';
                }

                else switch($this->_filetype) {
                    case 'js-str':
                    case 'js-obj':
                    case 'php-arr':
                        fwrite($fhan, "];");
                }
            }//<3>
            else {
                # not select, update or insert or ...
                $ret .= WebPanel::localize('msg_qrydone','Query done'). ' (rows affected: '.WebPanel::$db->affected_rows().')';
            }
            if($fhan) {
                $qryno = count($this->_expfiles);
                $divid = 'fl_'.$this->_pageid .'_'. rand(100000,999999);
                fclose($fhan);
                $ret .= "<div id='$divid'>Query [$qryno] saved to $outfilename, <input type='button' class='btn btn-primary' onclick='awp_sqlqry.getFile(\"$outfilename\")' value='download' /> "
                . "&nbsp; <input type='button' class='btn btn-primary' onclick='awp_sqlqry.delFile(\"$outfilename\",\"$divid\")' value='Please delete after download !' /></div>";
            }

            if($bUpdate && !empty(self::$cb_afterUpdate) && is_callable(self::$cb_afterUpdate)) {
                # perfrom callback if updating SQL was executed
                call_user_func(self::$cb_afterUpdate, $tableName, $querytext, $reccnt);
            }
        }//<2>
        else $ret .= WebPanel::localize('msg_qryerror').' : <br>'.WebPanel::$db->getLastQuery().'<br>'.WebPanel::$db->sql_error();
        return array($ret_title, $ret);

    }
    private function getFile($params) {
        # WriteDebugInfo('getfile, params:', $params);
        $realname = self::$exportFolder . $params['name'];
        if(is_file($realname)) {
            # TODO: headers for MIME/filesize/nocache...
            Header('Pragma: no-cache');
            Header('Pragma: public');
            Header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            Header('Content-Type: text/plain');
            Header("Content-Disposition: attachment; filename=\"$params[name]\"");
            Header('Content-Length: '.filesize($realname));
            exit(file_get_contents($realname));
        }
        else exit("File not found !");
    }
    /**
    * Deleting export file by client request
    *
    * @param mixed $params clientside params: 'name' - file name, 'divid' = > DOM element id to change text
    */
    private function deleteFile($params) {
        $realname = self::$exportFolder . $params['name'];
        $divid = isset($params['divid']) ? $params['divid'] : '';
        if(is_file($realname)) {
            unlink($realname);
            return ("OK|$divid|$params[name] - File deleted"); # TODO: localize
        }
        else return ("OK|$divid|$params[name] - File not found !");
    }
    /**
    * Save user Query(ies) to "User Defined queries' storage (with UserParams engine)
    *
    * @param mixed $params
    */
    public function saveUserQuery($params) {
        # writeDebugInfo("saveUserQuery params: ", $params);
        $pageid = $params['pageid'] ?? 0;
        $qryName = $params['qryname'] ?? '';
        $qryName = self::sanitizeName($qryName);
        if($qryName === '') return 'Error: Empty or wrong name, choose another!';
        $sql = $params['sql'] ?? '';
        if($sql === 'select * from') $sql = '';

        $result = \UserParams::updateSpecParam(0, 'usersql',$qryName,$sql);
        if($result==1) {
            return "1|$pageid|$qryName|Query saved as [$qryName]";
        }
        elseif($result==2) {
            return "2|$pageid|$qryName|Query [$qryName] deleted";
        }
        else
            return "Error saving in Parameters storage";
    }

    # make shure that passed name will be OK, by converting/deleting bad chars
    public static function sanitizeName($strg) {
        $ret = strtr($strg, [' '=>'_','%'=>'_', "'" => '', '?' => '_', '*'=>'_']);
        return $ret;
    }
    # load saved user query into SQL textarea
    public function loadUserQuery($params) {
        # writeDebugInfo("loadUserQuery params: ", $params);
        $pageid = $params['pageid'] ?? 0;
        $qryName = $params['qryname'] ?? '';
        if(empty($qryName)) return "Empty name passed";

        $sql = \UserParams::getSpecParamValue(0,'usersql',$qryName);

        if(empty($sql)) return "$qryName: User Query Not Found";
        return "1|$pageid|".$sql;
    }

}