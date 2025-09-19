<?php
/**
* @package waCRUDer
* @name waCRUDer.read.tpl.php
* reading table definition from ".tpl" text file ("|"-delimited strings)
*/

class WaCRUDerReadTpl extends WaCRUDerReadDef { # reading table definition from "TPL" file
#     WaCRUDerReadtpl
    static $EOL = '<br>';
    public function getFileExt() { return 'tpl'; }

    function read($filename,$options = false) { # ex. ParseTplDef($filename, $foredit=0,$ischild=0)
        $isCLI = ( php_sapi_name() == 'cli' );
        if ($isCLI) self::$EOL = "\r\n";

        $ret = new WaTableDef;
        $ret->_tplfile = $filename;

        $foredit = isset($options['foredit'])? $options['foredit'] : 0;
        $ischild = isset($options['ischild'])? $options['ischild'] : 0;
        $inJs = false;

        list($baseId) = explode('.', basename($filename)); # get "tabelname" from "path/../tablename.tpl"
        $ret->id = $baseId;

        $b_comment = 0;
        if(is_file($filename)) $lst = @file($filename, FILE_SKIP_EMPTY_LINES);
        else $lst = array();
        if(!is_array($lst) or count($lst)<1) {
            $this->_errormessage = 'Definition file not found or has no fields or read error';
            return false;
        }

        foreach($lst as $strkFull){ #<3>
            $strk = trim($strkFull);

            if($b_comment) {
                if (substr($strk,-2)==='*/') $b_comment = false;
                continue;
            }
            if(in_array(strtolower($strk), array('<script>','<js>'))) {
                $inJs = true;
                continue;
            }
            if(in_array(strtolower($strk), array('</script>','</js>'))) {
                $inJs = false;
                continue;
            }

            if($inJs) { # Add this line to JS block
                $ret->_jscode .= ($ret->_jscode? "\r\n":'') . rtrim($strkFull);
                continue;
            }

            if (substr($strk,0,1) == '#') continue;
            elseif (substr($strk,0,2)=='/*') { $b_comment=1; continue; }
            elseif (substr($strk,-2)==='*/') { $b_comment=0; continue; }
            # charset converting to whole string if needed
            $tar = explode('|', $strk);
        #        if(count($tar)<2 && $tar[0]) continue;
            if(!isset($tar[1])) $tar[1] = '';
            $key = strtoupper($tar[0]);
            switch($key)
            { #<4>
            case 'RPP':
              $ret->rpp = intval($tar[1])? intval($tar[1]):20;
              break;
            case 'DERIVEDFROM': case 'PARENTTABLES': # get parent table(s) def.
              $derive = explode(',',$tar[1]);
              if(count($derive)>0) {
                $ret->parenttables = array_merge($ret->parenttables, $derive);
              }
              break;
            case 'CHARSET':
              if (substr($tar[1],0,1) ==='@') {
                  $setfunc = substr($tar[1],1);
                  if (is_callable($setfunc))
                    $this->charset = call_user_func($setfunc);
              }
              else {
                  $this->charset = strtoupper($tar[1]);
                  if($this->charset=='UTF8') $this->charset = 'UTF-8';
              }
              break;

            case 'DATACHARSET':
              if (substr($tar[1],0,1) ==='@') {
                  $setfunc = substr($tar[1],1);
                  if (is_callable($setfunc))
                    $this->datacharset = call_user_func($setfunc);
              }
              else {
                  $this->datacharset = strtoupper($tar[1]);
                  if($this->datacharset=='UTF8') $this->datacharset = 'UTF-8';
              }
              break;
            case 'HELPPAGE':
              $this->helppage = trim($tar[1]); # help page ID (base HTML file name)

            case 'COLLATE':
              $this->collate = strtoupper($tar[1]); # Cyrillic UTF: utf8_general_ci
              if($this->charset=='UTF8') $this->charset = 'UTF-8';
              break;

            case 'ID':
              $ret->id = str_ireplace(WaCRUDer::PREFIX_MACRO,WaCRUDer::$tablesprefix,trim($tar[1]));
              if($ret->tbrowseid==='') $ret->tbrowseid=$ret->id;
              break;
            case 'BROWSEID': case 'VIEWID':
              $ret->tbrowseid = str_ireplace(WaCRUDer::PREFIX_MACRO,WaCRUDer::$tablesprefix,trim($tar[1]));
              break;
            case 'DESCR':
              $ret->desc = trim($tar[1]);
              break;
            case 'SDESCR':
              $ret->shortdesc = $tar[1];
              break;
            case 'BRFILTER':
              $brlist = explode(',', $tar[1]);
              $ret->browsefilter = array_merge($ret->browsefilter, $brlist);
              break;
            case 'BRFILTERFN':
              $ret->browsefilter_fn = $tar[1];
              break;
            case 'BRHEADER':
              $ret->_drawbrheader = !empty($tar[1]);
              break;
            case 'BRORDER': case 'ORDERBY':
              $ret->browseorder .= ($ret->browseorder==''? '':',').$tar[1];
              break;
            case 'BRWIDTH': case 'GRIDWIDTH':
              $ret->browsewidth = $tar[1];
              break;
            case 'EDITFORMWIDTH':
              $ret->editformwidth = $tar[1];
              if (defined('IF_LIMIT_WIDTH')) { # restrict maximal grid width, if IF_LIMIT_WIDTH set
                  if (strpos($ret->editformwidth,'%')===FALSE)
                    $ret->editformwidth = min($ret->editformwidth, constant('IF_LIMIT_WIDTH'));
              }

              break;
            case 'SEARCH':
              $ret->search .= ($ret->search==''? '':',').$tar[1];
              break;
            case 'SEARCHCOLS':
              $ret->searchcols = intval($tar[1]);
              break;
            case 'BLISTHEIGHT':
              $ret->blist_height = trim($tar[1]);
              if ($ret->blist_height == '') $ret->blist_height = '100px';
              break;
            case 'BLISTFULLFORM': case 'BFF':
              $ret->fullBlistForm = !empty($tar[1]);
              break;
            case 'DROPUNKNOWN':
              $ret->dropunknown = $tar[1];
              break;
            case 'DEBUG':
              $ret->debug = $tar[1];
              break;

            case 'SAFRM': # Simple Adding Form in last row
              $vl = empty($tar[1]) ? '' : $tar[1];
              $ret->safrm = WaCRUDer::evaluate($vl);
              if(empty($ret->safrm)) $ret->safrm = '';
              break;

            case 'CONFIRMDEL': case 'CONFIRMDELETE':
              $vl = empty($tar[1]) ? '' : $tar[1];
              $ret->confirmdel = $vl;
              break;

            case 'WINDOWEDEDIT':
              if(empty($tar[1]) || empty($tar[2])) $ret->windowededit = 0;
              else {
                $ret->windowededit = array('width'=>$tar[1], 'height'=>$tar[2],
                  'left'=>(empty($tar[3])?-1:intval($tar[3])),
                  'top'=>(empty($tar[4])?-1:intval($tar[4]))
                );
              }
              break;

            case 'ROWCLASSFUNC':
              $ret->rowclassfunc = empty($tar[1])? '' : $tar[1];
              break;

            case 'CHILDTABLE':
              $tblname = empty($tar[1])? '' : $tar[1]; # child table name
              $fld1 = empty($tar[2])? $ret->AllPkFields() : $tar[2]; # field in this table
              $fld2 = empty($tar[3])? $fld1 : $tar[3]; # FK-field in child table
              $addcondition = empty($tar[4])? '' : $tar[4]; # additional condition to select records in child table
              $del_protect = empty($tar[5])? '' : $tar[5]; # error message if existing children protect from deleteing parent rec
              if(!empty($tblname) && !empty($fld1) ) {
                $ret->childtables[] = array('table'=>$tblname, 'field'=>$fld1, 'childfield'=>$fld2,
                'condition'=>$addcondition,'protect'=>$del_protect);
              }
              break;

            case 'CHILDTABLES': # user function that return array of "child table" definitions, row[] = array(child_table,local_field,child_field,message)
              $ret->childtables_fn = $tar[1];
              if (is_callable($tar[1])) {
                  $charr = call_user_func($tar[1]);
                  if (is_array($charr)) foreach ($charr as $citem) {
                      if (count($citem)>=4) $ret->childtables[] = array('table'=>$citem[0], 'field'=>$citem[1], 'childfield'=>$citem[2],
                        'condition'=>'','protect'=>$citem[3],'_func'=>1); # mark it as "came from func"
                  }
        #              file_put_contents('_st.log', print_r($ret->childtables,1));
              }
              break;
            case 'PAGELINKS':
              $ret->pagelinks = empty($tar[1])? 0 : $tar[1];
              break;
            case 'ADJACENTLINKS':
              $ret->_adjacentLinks = intval($tar[1]);
              break;
            case 'BRENDERFUNC':
              $ret->brenderfunc = empty($tar[1])? '' : $tar[1];
              break;
            case 'BROWSESTYLES':
              $flds = preg_split("/[\s,;]+/",$tar[1]);
              foreach($flds as $fldid) {
                  if(!empty($fldid)) {
                      if(!isset($tar[2]) || empty($tar[2])) unset($ret->_browsetags[$fldid]);
                      else $ret->_browsetags[$fldid] = trim($tar[2]);
                  }
              }
            case 'RECDELETEFUNC':
              $ret->recdeletefunc = empty($tar[1])? '' : $tar[1];
              break;
            case 'UPDATEFUNC':
              $ret->updatefunc = empty($tar[1])? '' : $tar[1];
              break;
            case 'EDITFORM':
              $ret->editform = empty($tar[1])? '' : $tar[1];
              break;
            case 'EDITSUBFORM':
              $ret->editsubform = empty($tar[1])? '' : $tar[1];
              break;
            case 'AFTEREDITSUBFORM':
              $ret->aftereditsubform = empty($tar[1])? '' : $tar[1];
              break;
            case 'EDITMODE':
              $ret->editmode = empty($tar[1])? '' : $tar[1];
              break;
            case 'EVENT': case 'EDITEVENT':
              $etype = isset($tar[1])? strtoupper($tar[1]) : '';
              $eevent = isset($tar[2])? $tar[2] : '';
              $efunc = isset($tar[3])? $tar[3] : '';
              if(!empty($etype) && !empty($eevent)) {
                if(!isset($ret->events[$etype])) $ret->events[$etype] = array();
                $ret->events[$etype][$eevent] = $efunc;
              }
              break;
            case 'CLONABLE':
              $ret->clonable = isset($tar[1]) ? $tar[1] : 0;
              $ret->clonable_field = isset($tar[2]) ? $tar[2] : ''; # what field will contain '(clone)' for cloned record
              break;
            case 'BEFOREEDIT':
              $ret->beforeedit = isset($tar[1]) ? $tar[1] : '';
              break;
            case 'BEFOREDELETE':
              $this->beforedelete = isset($tar[1]) ? $tar[1] : '';
              break;
            case 'ONSUBMIT':
              $ret->onsubmit = isset($tar[1]) ? trim($tar[1]) : '';
              break;

            case 'AFTEREDITFUNC': case 'AFTEREDIT': case 'BEFOREUPDATE':
              $ret->afteredit = isset($tar[1]) ? $tar[1] : '';
              break;
            case 'AFTERUPDATE':
              $ret->afterupdate = isset($tar[1]) ? $tar[1] : '';
              break;
            case 'PICTURES':
              $ptable = isset($tar[1]) ? $tar[1] : '';
              $pfolder = isset($tar[2]) ? $tar[2] : '';
              $pscript = isset($tar[3]) ? $tar[3] : '';
              $pw = isset($tar[4]) ? $tar[4] : 0;
              $ph = isset($tar[5]) ? $tar[5] : 0;
              $thw= isset($tar[6]) ? $tar[6] : 0;
              $thh= isset($tar[7]) ? $tar[7] : 0;
              $pop= isset($tar[8]) ? $tar[8] : 0;
              if(!empty($ptable)) {
                $this->SetLinkedPictures($ptable,$pfolder,$pscript,$pw,$ph,$thw,$thh,$pop);
              }
              break;
            case 'AUDITING':
              $ret->_auditing = $tar[1];
              break;
            case 'FILEFOLDER':
              $ret->file_folder = trim($tar[1]);
              break;

            case 'FIELD': # parse field def.
              $fld = new waFieldDef;
              $fld->derived = $ischild;
              $fldid = empty($tar[1])? "id$k" : trim($tar[1]);
              $fldid = $fld->id = strtolower($fldid); # mysql - fieldname is alwais low case
              $fld->name = empty($tar[2])? $fld->id : trim($tar[2]);
              if( isset($ret->fields[$fldid]) ) continue; #field exists!
              $fld->desc = empty($tar[3])? $fldid : trim($tar[3]);
              $fld->shortdesc = empty($tar[4])? ($foredit? '' : $fld->desc): WaCRUDer::utrim($tar[4]);
        #          WriteDebugInfo("$fldid short desc:",$fld->shortdesc, "src:", $tar[4]);
              $fftype = isset($tar[5])? preg_split("/[\s,]+/", $tar[5]) : array('INT');
              $fld->type = '';
              $fld->length = isset($tar[6]) ? trim($tar[6]) : '';
              foreach($fftype as $tpitem) {
                $tpitem=strtoupper($tpitem);
                if($tpitem==='LOGIN') {
                    $fld->subtype = 'LOGIN';
                }
                elseif($tpitem==='PK' || $tpitem==='PKA') {
                    if(!$ret->_pkflistset) $ret->_pkfields[]=$fldid;
                    if(!$ret->_pkfield) $ret->_pkfield = $fldid;
                    if($tpitem==='PKA') $fld->_autoinc=true;
                    if(empty($fld->type)) $fld->type='BIGINT';
                    if(empty($fld->length)) $fld->length=20;
                }
                else $fld->type = $tpitem; # CHAR/VARCHAR/INT,...
              }
              # if($fld->type=='TINYINT' && !$fld->type) $fld->type=1;
              if($fld->type === 'DATE' || $fld->type === 'DATETIME' || $fld->type === 'TIMESTAMP' ||
                strpos($fld->type,'TEXT')!==false)
                     $fld->length = ''; # error length protect
              $fld->notnull = !empty($tar[7]);
              $fld->defvalue = isset($tar[8])?$tar[8]:'';
              $fld->showcond = empty($tar[9])? '' : WaCRUDer::evaluate($tar[9]);

              $fld->showformula = empty($tar[10])? '' : trim($tar[10]);
              if ($fld->showformula !=='') {
                  $splt = explode(',',$fld->showformula);
                  $fld->showformula = array_shift($splt);
                  $fld->showattr = $splt; # [0]=text-align,[1]-bgcolor(may be @function),[2]-reserved
              }
              $fld->editcond = empty($tar[11])? 0 : trim($tar[11]);
              $fld->edittype = empty($tar[12])? 'TEXT' : trim($tar[12]);
              $_arr = explode('^',$fld->edittype);
              if(count($_arr)<2) $_arr = explode(',',$fld->edittype);
              $fld->edittype = strtoupper(array_shift($_arr));
              if(in_array($fld->edittype,['SELECT','BLIST']) && count($_arr)) {
                  $fld->editoptions = array_shift($_arr);
              }

              # if (count($_arr)>0) $fld->editoptions = $_arr; # SELECT,@funs,formats,... => editoptions = ['@func', formats,...]

              if($fld->edittype === 'WYSIWYG')  $ret->_wysiwyg[] = $fld->id;
              elseif('FILE'===$fld->edittype) {
                  $ret->_multipart=true;
                  $tp = $_arr; array_shift($tp);
                  $ret->_savefile_pm[$fldid] = $tp;
              }
              if(!empty($tar[13])) { # INDEX_NAME[,UNIQUE]
                 $tar[13] = trim($tar[13]);
                 $_arr = explode(',', $tar[13]);
                 $fld->idx_name = $_arr[0];
                 $fld->unique = empty($_arr[1]) ? 0 : 1;
              }
              # echo "Field $fld->id=$fld->name, type:$fld->type($fld->length)<br>"; # debug
              $fld->showhref = empty($tar[14])? '' : trim($tar[14]);
              $fld->hrefaddon = empty($tar[15])? '' : trim($tar[15]); # доп.атрибуты для <a href>
              $fld->afterinputcode = isset($tar[16])? $tar[16] : '';

              $ret->fields[$fldid] = $fld;
              break;
            case 'PRIMARYKEY': case 'PK': # fields in PRIMARY KEY() list
              $ret->_pkfields=preg_split('/[, ;]+/',$tar[1], -1, PREG_SPLIT_NO_EMPTY);
              $ret->_pkflistset=true;
              break;
            case 'INDEX':
              $idx = new WaIndexDef();
              $idx->name  = empty($tar[1])? "idx_{$ret->id}{$k}" : trim($tar[1]);
              $idx->expr = empty($tar[2])? '' : trim($tar[2]);

              $idx->unique = empty($tar[3])?'':$tar[3];
              $idx->derived = $ischild;
        #          $fld->descending = !empty($tar[4]);

              if(!empty($idx->expr) || !empty($foredit))
                $ret->indexes[] = $idx;

              break;
            case 'FULLTEXT': case 'FTINDEX':
              $idxname = count($tar)>1 ? trim($tar[1]) : ('ft_'.$ret->$id.(count($ret->ftindexes)+1) );
              $idxlst  = count($tar)>2 ? $tar[2] : $tar[1];
              $ret->ftindexes[$idxname] = $idxlst;
              break;
            case 'CUSTOMCOLUMN': # non-field columns in browse page #ex-BRCUSTOMHREF
              $htmlcode  = isset($tar[1])? $tar[1] : '';
              $htitle  = isset($tar[2])? $tar[2] : '';
              $addon = isset($tar[3])? $tar[3] : ''; # доп.атрибуты для <a href="" ...>
              if(!empty($htmlcode)) {
                $ret->customcol[] = array('htmlcode'=>$htmlcode, 'title'=>$htitle, 'addon'=>$addon,'derived'=>$ischild);
                $cnt = count($ret->customcol)-1;
              }
              break;
            case 'RECURSIVE':
              $ret->recursive = $tar[1];
              $ret->recursive_show = empty($tar[2])? '': $tar[2];
              break;
            case 'MULTISELECT':
              $ret->_multiselect=true;
              $ret->_multiselectFunc = isset($tar[1]) ? trim($tar[1]) : '';
              break;
            case 'TYPE':
              $ret->tabletype = $tar[1];
              break;
            case 'RESETCHAIN':
              if(!empty($tar[1])) $ret->reset_chain[] = preg_split( WaCRUDer::DEFAULTDELIMS, $tar[1], -1, PREG_SPLIT_NO_EMPTY );
              break;

            case 'RIGHTS': # rights|funcName - should return array with up to 4 items: [view,edit,delete,insert]. No value means "no right"
              if (!empty($tar[1]) && (is_callable($tar[1]))) {
                  list($ret->canview, $ret->canedit, $ret->candelete, $ret->caninsert) = call_user_func($tar[1]);
              }
              break;
            case '':
              break;
            default:
              echo "unsuppported tag $key, string: ".$strk . self::$EOL;
              break;
            } #<4> switch
        } #<3>
         # localization block with appEnv, if defined
        /*     if(class_exists('appenv')) {
             if($locr=appEnv::getLocalized($ret->id.'.'.'descr') && $locr!==$ret->id.'.'.'descr') $ret->desc =  $locr;
             if($locr=appEnv::getLocalized($ret->id.'.'.'sdescr' && $locr!==$ret->id.'.'.'sdescr')) $ret->shortdesc =  $locr;
             foreach($ret->fields as $fid=>$fld) {
                if ($locr=appEnv::getLocalized('t.'.$ret->id.'.'.$fld->id) && $lock !=='t.'.$ret->id.'.'.$fld->id) $ret->fields[$fid]->desc =  $locr;
                if ($locr=appEnv::getLocalized('ts.'.$ret->id.'.'.$fld->id) && 'ts.'.$ret->id.'.'.$fld->id) $ret->fields[$fid]->shortdesc =  $locr;
                if (empty($ret->fields[$fid]->shortdesc)) $ret->fields[$fid]->shortdesc = $ret->fields[$fid]->desc;
             }
         }
        */
        return $ret;
    }


}