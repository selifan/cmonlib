<?php
/**
* @package astedit , Database tables create/upgrade/browse/edit engine  (CRUD++)
* @name astedit(x).php - main module, classes CFieldDefinition,CIndexDefinition,CTableDefinition
* @author Alexander Selifonov, < alex [at] selifan dot ru >
* @Version 1.86.003
* updated 2025-07-18
**/
# error_reporting (E_ALL ^ E_NOTICE);
# User field types, added by including your own classes
interface UserFieldType {
    public function isTextField();
    public function getEditCode($fldname, $values);
    public function encodeValue($fldname, $values);
    public function decodeValue($fldname, $values);
}
/*
class AstUserFieldTypes {
    static $list = array();
    public static function add($typeid, $className) {
        self::$list[$typeid] = $className;
    }
}
*/
class Astedit {
    static $_jQuery_dates = false;
    static $db = null;
    const PREFIX_MACRO = '%tabprefix%';
    const VERSION = '1.86';
    static $VERBOSE = 0; // set it to 1 if some 'debug' messages needed
    static $checkLoginFunc = FALSE; # user function to check new login against existing ones
    public static function ActivateDateModifier() { self::$_jQuery_dates = true; }
    static $time_monitoring = FALSE;
    static $create_comments = true;
    static $avoidChars = ["'" => '', '"'=> '', "`" =>''];
    public static $viewRecordCallback = FALSE; # callback func for renedring VIEW record page
    static $newRecordId = array();
    static $folderHelpPages = 'helppages/';
    static $RO_mode = FALSE; # global read-only mode

    public static function init($anotherDb = NULL) {
        if (is_object($anotherDb)) self::$dn =& $anotherDb;
        else {
            global $as_dbengine;
            if (!is_object(self::$db)) {
                if(class_exists('AppEnv') && isset(AppEnv::$db)) self::$db =& appEnv::$db;
                elseif(is_object($as_dbengine)) self::$db =& $as_dbengine;
                elseif (class_exists('CDbEngine')) self::$db = CDbEngine::getInstance();
            }
        }
    }

    public static function getEditingJsCode() {
        $ret = '';
        if(self::$_jQuery_dates) $ret .= '$(\'input.datefield\').datepicker().change(DateRepair);';
        return $ret;
    }
    public static function showError($text) {
      /*
      if (is_callable('appEnv::addInstantMessage'))
        appEnv::addInstantMessage($text,'astedit_update');
        else
      */
      echo "<div class=\"alarm w-600\">$text</div>";
    }

    /**
    * trun on/off monitoring time to render grid page
    * @since 1.56
    * @param mixed $val
    */
    public static function setTimeMonitoring($val = TRUE) {
        self::$time_monitoring = $val;
    }
    public static function setReadonlyMode($mode = true) {
        self::$RO_mode = $mode;
    }
    public static function localize($id, $default='') {
        if (is_callable('WebApp::getLocalized')) return WebApp::getLocalized($id, $default);
        return ($default ? $default : str_replace('_',' ',$id));
    }
    /**
    * Upgrade all tables in list
    *
    * @param mixed $tbar array with table names
    * @param mixed $tovar, TRUE=return result log strings, otherwise just exit(it)
    */
    public static function upgradeTables($tbar, $tovar=false) {
        # $tbar - Table names array, to check and create/update structures
        if (is_file(__DIR__ . '/astedittsu.php'))
            include_once(__DIR__ . '/astedittsu.php');
        else return "module astedittsu.php not found. Upgrade failed";
        $ret = '';

        if(!is_array($tbar) || count($tbar)<1) {
            $ret = 'Empty table list passed';
            if($tovar) return $ret;
            exit($ret);
        }
        $t_skipped = Astedit::localize('text_skipped','skipped');
        $t_error   = Astedit::localize('text_error','error');
        foreach($tbar as $tb) { #<2>
            $tbname = (is_array($tb)?$tb[0]:$tb);
            unset($tbl);
            $tbl = new CTableDefinition($tbname);

            if ($tbl->_errormessage) {
                $ret .= "<b>$tbname</b> skipped: {$tbl->_errormessage}<br>";
                continue;
            }
            $pgtitle = $tbl->desc;

            $ret .= "<b>{$tbl->id}</b> / $pgtitle : ";

            if(!$tbl->IsTableExist()) {
              # $arr = $tbl->CreateTable();
              $arr = AsteditTSU::createTable($tbl,self::$db);
            }
            else { #<2>
              # $arr = $tbl->UpgradeTable();
              $arr = AsteditTSU::upgradeTable($tbl,self::$db);
            }
            if(is_array($arr) && count($arr) > 0 ){ #<2>
              $res_text = '';
              $ret .= "<table class='zebra'>";
              foreach($arr as $qryname=>$qrytext) { #<4>
                self::$db->sql_query($qrytext); # debug
                $sqlerr = Astedit::$db->sql_error();
                if (!$sqlerr) {
                    $tx_result = 'OK';
                    $rows = self::$db->affected_rows();
                    if($rows>0) $tx_result .= " ($rows)";
                }
                else {
                    $tx_result = "$t_error : ".$sqlerr;
                }
                $ret .= "<tr><td>$qryname</td><td>$qrytext</td><td>$tx_result</td></tr>\n";
              } #<4>
              $ret .= '</table>';

            } #<2>
            else $ret .= ' ' . Astedit::localize('no_changes') . '<br>';
        } #<2>
        if(empty($tovar)) echo $ret;
        else return $ret;
    } #UpdateAllTables() end

    public static function dropTables($tbar, $tovar=false) { # $tbar - Table names array, to check and create/update structures

        $ret = '';
        if(!is_array($tbar) || count($tbar)<1) {
          $ret = 'Empty table list passed';
          if($tovar) return $ret;
          exit($ret);
        }

        foreach($tbar as $tb) { #<2>
          $tbname = (is_array($tb)?$tb[0]:$tb);
          unset($tbl);
          $tbl = new CTableDefinition($tbname);
          if(!empty($tbl->id)) {
              self::$db->sql_query("DROP TABLE $tbl->id");
              $errno = self::$db->sql_errno();
              $result = ($errno) ? self::$db->sql_error() : ' OK';
              $ret .= "Dropping $tbl->id ($tbl->desc): $result<br>";
          }
        }
        if(empty($tovar)) echo $ret;
        else return $ret;
    }
    /**
    * Convert tabe(s) to new charset
    * @since 1.58
    * @param mixed $tbar table to convert
    * @param mixed $cset destination character set
    * @param mixed $ignore not used for now
    */
    public static function convertTable($tbar, $cset, $ignore=false) {
        $ret = '';
        if(!is_array($tbar) || count($tbar)<1) {
          $ret = 'Empty table list passed';
          return $ret;
        }

        foreach($tbar as $tb) { #<2>
          $tbname = (is_array($tb)?$tb[0]:$tb);
          unset($tbl);
          $tbl = new CTableDefinition($tbname);
          if(!empty($tbl->id)) {
              self::$db->sql_query("ALTER TABLE {$tbl->id} CONVERT TO CHARACTER SET $cset");
              $errno = self::$db->sql_errno();
              $result = ($errno) ? self::$db->sql_error() : ' OK';
              $ret .= "Converting {$tbl->id} to $cset: $result<br>";
          }
        }
#        if(empty($tovar)) echo $ret;
        return $ret;
    }
    /**
    * Changes "DEFAULT CHARACTER SET" for specified tables
    *
    * @param mixed $tbar
    * @param mixed $cset
    * @param mixed $tovar
    */
    public static function changeCharSet($tbar, $cset, $tovar=false) {

        $ret = '';
        if(!is_array($tbar) || count($tbar)<1) {
          $ret = 'Empty table list passed';
          if($tovar) return $ret;
          exit($ret);
        }
        # auto add COLLATE:
        Astedit::autoCollate($cset);
        foreach($tbar as $tb) { #<2>
          $tbname = (is_array($tb)?$tb[0]:$tb);
          unset($tbl);
          $tbl = new CTableDefinition($tbname);
          if(!empty($tbl->id)) {
              self::$db->sql_query("ALTER TABLE $tbl->id DEFAULT CHARSET $cset");
              # TODO: scan all VARCHAR/CHAR/TEXT fields that might have specific CHARSET...
              $errno = self::$db->sql_errno();
              $errmsg = self::$db->sql_error();
              $result = ($errno) ? $errmsg : "change charset to $cset OK";
              $ret .= "$tbl->id ($tbl->desc): $result<br>";
          }
        }
        if(empty($tovar)) echo $ret;
        else return $ret;
    }
    public static function addTplFolder($path) {
        CTableDefinition::addTplFolder($path);
    }

    public static function getInsertedId($tableid) {
        return (isset(self::$newRecordId[$tableid]) ? self::$newRecordId[$tableid] : FALSE);
    }

    public static function autoCollate(&$charset) {
        switch(strtoupper($charset)) {
            case 'UTF8':
                $charset .= " COLLATE utf8_unicode_ci ";
                break;
            case 'CP1251':
                $charset .= " COLLATE cp1251_general_ci ";
                break;
        }
    }

    public static function ast_trim($par) {
        if(MAINCHARSET=='UTF-8') {
            try {
                $ret = @preg_replace('@^\s*|\s*$@u', '', $par); # Sometimes causes FATAL errors !
            }
            catch (Exception $e) {
                return trim($par);
                # echo ('exception raised: ' .$e->getMessage());
            }
        }
        else {
            $ret = trim($par);
        }
        return $ret;
    }
    # set user function to check if login exists
    public static function SetLoginCheckFunc($func) {
        self::$checkLoginFunc = $func;
    }

    # returns first N (40) chars of passed string with "..." if cutted. To replace global func brShortText
    public static function viewShortText($txt, $maxlen=40) {

        if(empty($txt)) return '';
        $txt = strip_tags($txt);
        $cset = defined('MAINCHARSET')? constant('MAINCHARSET') : '';

        $cr = strpos($txt, "\r");
        if($cr >0 && $cr<4 && $cr<$maxlen) $cr =strpos($txt, "\r", $cr+2);
        if($cr>0)
        $cutoff = min($cr, $maxlen);
        else $cutoff = $maxlen;
        if (substr($cset,0,3) === 'UTF') {
           $ret = ($cutoff < mb_strlen($txt, $cset)) ? mb_substr($txt,0,$cutoff,$cset) . '...' : $txt;
           return $ret;
        }
        return ($cutoff < strlen($txt))? substr($txt,0,$cutoff).'...' : $txt;
    }

    public static function BrShowBool($val=false) {
        if(defined('USE_JQUERY_UI')) return (empty($val)? ''
          : '<span class="ui-icon ui-icon-check"></span>'); # ui-state-default - add borders
        return (empty($val)? '' : '<img src="'.IMGPATH.'btCheck.png" width=18 height=18 />');
    }
    public static function BrShowURL($val='') {

        return ((!empty($val)) ? '' : "<a href=\"$val\" target='_blank'>$val</a>");
    }
    public static function splitDefault($strg, $getFirst = 0) {
        $char1 = substr($strg,0,1);
        $ret = array($strg);
        if (in_array($char1, array("'", '"',"`"))) {
            $spos = strpos($strg,$char1, 1);
            if ($spos>0) {
                $comma = strpos($strg, ',', $spos);
                if ($comma > 0 ) $ret = array(substr($strg,0,$comma), substr($strg,$comma+1));
            }
        }
        else $ret = explode(',', $strg);
        return ($getFirst ? $ret[0] : $ret);
    }

    # default record renderer for viewrecord AJAX command
    public static function defaltRecView() {
        $pars = array_merge($_GET, $_POST);
        return ('defaltRecView params <pre>' . print_r($pars,1). '</pre>');
    }
    # make charset name to MySQL supported name
    # Atas: new MySQL 8+ converts UTF8 to UTF8MB3 ! (UTF8 obsolete)
    public static function csetToDb($charset, $newMySQL=FALSE) {
        switch(strtolower($charset)) {
            case 'utf-8':
            case 'utf8':
                return ($newMySQL ? 'UTF8MB3':'UTF8');
            case 'windows-1251':
                return 'CP1251';
        }
        return $charset;
    }
    /**
    * getting Comprehensive error description (mySQL error codes)
    *
    * @param mixed $errno Error Number
    * @param mixed $errText Error Text, used if errno not described
    * @param mixed $tableiId
    */
    public static function decodeSqlError($errno, $errText, $tableiId='') {
        $sRet = '';
        if(isset(AsteditLocalization::$strings['sql_error_'.$errno]))
            $sRet = AsteditLocalization::$strings['sql_error_'.$errno];
        elseif(is_callable('AppEnv::getLocalised')) $sRet = Appenv::getLocalized('sql_error_'.$errno);
        if(empty($sRet)) $sRet = $errText;
        if(!empty($tableiId)) $sRet = str_replace('{tablename}', $tableiId, $sRet);
        return $sRet;
    }
    public static function brShortText($txt, $maxlen=40) {
        if(empty($txt)) return '';
        $txt = strip_tags($txt);
        $cset = defined('MAINCHARSET')? constant('MAINCHARSET') : '';

        $cr = strpos($txt, "\r");
        if($cr >0 && $cr<4 && $cr<$maxlen) $cr =strpos($txt, "\r", $cr+2);
        if($cr>0)
        $cutoff = min($cr, $maxlen);
        else $cutoff = $maxlen;
        if (substr($cset,0,3) === 'UTF') {
            $ret = ($cutoff < mb_strlen($txt, $cset)) ? mb_substr($txt,0,$cutoff,$cset) . '...' : $txt;
            return $ret;
        }
        return ($cutoff < strlen($txt))? substr($txt,0,$cutoff).'...' : $txt;

    }

    public static function evalValue($param, $par_arr=null) {
        if(empty($param)) return false;
        $ret = false;
        if('@' === substr($param,0,1)) { #<3>
          $fnc =substr($param,1);
          # writeDebugInfo("func:", $fnc);
          if(is_callable($fnc)){
              $ret = call_user_func($fnc, $par_arr);
          }
          else {
              $splt = explode('::', $fnc);
              if (count($splt)>1 && class_exists($splt[0])) {
                  $clsName = $splt[0]; $obj = $splt[1];
                  # WriteDebugInfo(" try to get prop: $clsName :: $obj");
                  $evaluated = $clsName::$obj($par_arr);
                  return $evaluated;
              }
          }
        } #<3>
        elseif('#' === substr($param,0,1) && stripos($param,'{ID}')!==false) {
          $fnc = substr($param,1);
          $fnc = str_ireplace('{ID}', $par_arr, $fnc);
          $ret = eval($fnc); # "{ID} > 1" returns eval(param>1)
        }
        elseif('~' === substr($param,0,1)) { #~SELECT ... operator, return result as array
          $qry = substr($fnc,1);
          $qry = str_ireplace('{ID}', $par_arr, $fnc);
          $lnk = Astedit::$db->sql_query($qry);
          $ret = array();
          while(($lnk) && ($r=Astedit::$db->fetch_row($lnk))) $ret[] = $r;
        }
        elseif('!' === substr($param,0,1)) { #<3> read option list from file
         $Lname = substr($param,1);
         $ret = array();
         if(is_readable($Lname) && ($ffh=fopen($Lname,'r'))>0) { #<4>
           while(!feof($ffh)) {
             $strk = trim(fgets($ffh,4096));
             if($strk[0]!='#') $ret[] = explode('|', $strk);
           }
           fclose($ffh);
         } #<4>
        } #<3>
        else {
          $ret = $param;
        }
        return $ret;
    }
} # astedit end

if(!defined('DEFAULT_TBLTYPE')) define ('DEFAULT_TBLTYPE', 'MYISAM'); # default table type for CREATE TABLE operation

if(!defined('MAINCHARSET')) {
    define ('MAINCHARSET', 'UTF-8'); # default charset in TPL/XML
}
define('XML_CHARSET','UTF-8'); # in XML data alwais encoded in UTF-8
# make 'ISO-8859-1' if You need it...
define ('DEFAULT_TPLTYPE', 'tpl'); # tpl or xml - preferred template type to read
define('AST_FORM_ELEMENT','FORMELEMENT');
define('AST_PKDELIMITER','|');
define('ASTDELIMITER1',"\t");
define('ASTDELIMITER2',chr(12)); # \f form feed char

# interface buttons links...
$astbtn['edit'] = IMGPATH.'btGo.png';
$astbtn['add'] = IMGPATH.'btAdd.png';
$astbtn['del'] = IMGPATH.'btDelete.png';
$astbtn['w'] = $astbtn['h'] = 18; # buttons weight & height, px

$ast_libpath = defined('LIBPATH')? LIBPATH : (dirname(__FILE__).'/');
if (!class_exists('CDbEngine')) {
  @include_once($ast_libpath.'as_dbutils.php'); # DB access wrapper class
}
$ast_browse_jsdrawn = false; # flag "browse JS functions drawn"
$ast_ajaxfnc_drawn = false;  # flag "AJAX functions already drawn"
$ast_ajaxtables = array(); # AsteditAddTable() to register ajax tables if AJAX master/detail used
# $ast_act = '';
# global vars for internal use (parsing xml callback functions fill them)
$astxmldepth=$astxmltag=$astxmldef=$astxmlfld=$astxmlidx=$astcustomcol=$astxmlchild=$astxmlparent=0;
$ast_wysiwyg_type = '';
# $_astglobalpars = array(); # global parameters for all CTableDef objects
# css classses names, You can override them to fine-tune pages design
global $as_cssclass, $ast_tips;

if(!isset($as_cssclass)) $as_cssclass = array();
if(empty($as_cssclass['textfield'])) $as_cssclass['textfield'] = 'form-control d-inline w-auto-custom';
if(empty($as_cssclass['trowhead'])) $as_cssclass['trowhead'] = 'head';
if(empty($as_cssclass['tdhead'])) $as_cssclass['tdhead'] = 'head';
#if(empty($as_cssclass['troweven'])) $as_cssclass['troweven'] = 'even';
if(empty($as_cssclass['trowodd'])) $as_cssclass['trowodd'] = 'odd';
if(empty($as_cssclass['trmouseover'])) $as_cssclass['trmouseover'] = 'mouseover';
if(empty($as_cssclass['pagelnk'])) $as_cssclass['pagelnk'] = 'pagelnk';
if(empty($as_cssclass['pagelnka'])) $as_cssclass['pagelnka'] = 'pagelnka';

if(empty($as_cssclass['button'])) $as_cssclass['button'] = 'btn btn-primary';
if(!isset($ast_hideserachbar)) $ast_hidesearchbar=false; # enable show/hide search toolbar
$ast_datarow = array(); # global var will hold data row array
$ast_frmfunctions = array(); # registered drawing form field functions for astedit


# You can prepare localized string vars ast_* and include them before this line
if(!isset($ast_tips) OR !is_array($ast_tips)) $ast_tips = array(); # localized interface here !
# titles for edited fields, fill this array only for needed fields ['table']['field']
if(!isset($ast_lang)) $ast_lang = 'ru'; # Set Your language ID here or before

if(is_file('./i18n/'.$ast_lang.'/strings-astedit.php'))
     @include('./i18n/'.$ast_lang.'/strings-astedit.php');
else @include_once("astedit-lang.$ast_lang.php");
if (!empty($ast_tips['charset']) && defined('MAINCHARSET') && constant('MAINCHARSET')!='') {
    if ($ast_tips['charset'] !== MAINCHARSET) mb_convert_variables(MAINCHARSET,$ast_tips['charset'], $ast_tips);
}

if(!isset($ast_tips['clone_record'])) $ast_tips['clone_record']='Clone record';
if(!isset($ast_tips['table_frozen'])) $ast_tips['table_frozen']='Table is frozen!';
if(!isset($ast_tips['tipadd'])) $ast_tips['tipadd']='Add';
if(!isset($ast_tips['tipedit'])) $ast_tips['tipedit']='Edit';
if(!isset($ast_tips['tipdelete'])) $ast_tips['tipdelete']='Delete';
if(!isset($ast_tips['tiphelpedit'])) $ast_tips['tiphelpedit']='Show some help for this page';
if(!isset($ast_tips['help_page_notfound'])) $ast_tips['help_page_notfound']='Help page not found.';
if(!isset($ast_tips['help'])) $ast_tips['help']='Help';


$self = $_SERVER['PHP_SELF'];
if(!isset($_SESSION)) @session_start(); # session engine is widely used, so start it!

# intercept 'sort' command, to set cookies before any output...

$ast_parm = array_merge($_GET,$_POST);

if(!empty($ast_parm['ast_act'])) $ast_act = $ast_parm['ast_act'];
if(isset($ast_parm['asteditajax']) ) {
    AsteditAjaxCalls();
    exit;
}

class CFieldDefinition
{ # one table field definition
  public $id = ''; # field "id" or var_name for this field in php form blocks
  public $external = FALSE;
  public $getter = FALSE; # for external fields only : getter and setter function
  public $setter = FALSE;
  public $desc = ''; # long description
  public $shortdesc = '' ; # short description (browse header)
  public $name = ''; # field name
  public $type = '';
  public $length = '';
  public $notnull = '';
  public $defvalue = ''; # DEFAULT value for field (for SQL CREATE TABLE operator) DEFAULT[,new_formula]
  public $showcond = ''; # show in browse if condition evals nonempty
  public $showformula = ''; # convert value with eval(this formula)
  public $showattr = ['','','','']; # align, color, bgcolor,class - additional attribs for field in grid: |@showformaul[,align[,color[,classes]]|...
  public $showhref = ''; # 14-th field - HREF with {ID} - makes linked href from browse page
  public $hrefaddon = '';# additional HTML tags for editing-mode : onClick="...." title="..."
  public $editcond = ''; # "editable"  condition for field, 'C'- auto-create for New records
  public $edittype = ''; # in what form edit: checkbox, listbox... + formula for options
  public $inputtags = ''; # some additional tags in <input ...> tag, i.e. onChange='myfunc()'
  public $idx_name  = ''; # if field must be indexed, here is the index's unique name
  public $idx_unique = 0; # non-empty value if index is unique
  public $derived = 0; # this field came from Parent table
  public $_autoinc = false; # auto-incremented field
  public $afterinputcode = ''; # additional html code after input field (some buttons with JS code etc...)
  public $editRowClass = ''; # edditional class name for whole row in edit form
  public $imgatt = '';
  public $subtype = false;
  public $specs = [];
  public $unique = FALSE;
  public function GetDefaultValue() {
    $defar = explode(',',$this->defvalue);
    $def = isset($defar[1]) ? $defar[1] : $defar[0];

    if($def!=='' && $def[0]==="'") $def = trim($def,"'");
    elseif($def!='' &&($def[0] === '@' || $def[0] === '#')) $def = astedit::evalValue($def);
    elseif($this->type=='DATE' || $this->type=='DATETIME') {
        if (in_array($def, array('now','{now}'))) $def=date('Y-m-d H:i:s');
        elseif (in_array($def, array('today','{today}'))) $def=date('Y-m-d');
        else $def = '';
    }
    return $def;
  }
} # CFieldDefinition class end

class CIndexDefinition
{ # one table field definition
  public $name = ''; # index name
  public $expr = ''; # field list, "field1,field2,..."
  public $unique = 0 ; # Uniqueness
  public $descending = 0; # 1 if DESCENDING order (MySQL - no support ?)
  public $derived = 0;
} # CIndexDefinition class end

class CTableDefinition
{ # class for holding info about table structure
  const DEFAULTDELIMS = '/[,; ]/';
  static $ast_tableprefix = '';
  static private $tplFolder = array('');
  static $tplMerged = FALSE;
  public $_tplfile = ''; # loaded definition filename (w/o .tpl)
  private $debug = 0;
  private $toolBar = []; # toolBar html fragments
  public $filename = ''; # file name this def came from (and save to)
  public $tabletype = ''; // myisam etc.
  public $charset = '';
  public $datacharset = '';
  public $collate = '';
  public $id = ''; # Table ID/name
  public $desc = ''; # Table long title
  public $imgatt = FALSE;
  public $helppage = ''; # haelp page with instruction text for editing record
  public $shortdesc = ''; # short title for table
  public $browsefilter = array(); # filter for browse mode
  public $browsefilter_fn = ''; # filter as User Defined (Dynamic) function
  public $childtables = array(); #
  public $browsewidth = ''; # browsing screen width, value for <table width=NNN[%]>
  public $browseheight = ''; # if set to some 'XXXpx', scrolling style will be used
  private $blist_height = '100px'; # BLIST area max height, width
  public $blist_width = '99.9%';
  public $_pkfield = ''; # Primary Key field name(identified if there is PK-PKA field def
  public $_pkfields = array(); # array with all fields defined as PRIMARY KEY
  public $_pkflistset = false;
  public $rpp = 20 ; # rows per page - browse limit
  public $pagelinks = 1; # maximal - show ALL pages HREFS, 0-no href, 1-only previous and next
  public $editformwidth = '100%';
  public $pagelinksinrow = 25; # how many page links in one row (pages list in the bottom)
  public $browseorder = ''; # order expression for browsing
  public $userfilter = '';  # You can add your browse conditions
  public $search = ''; # search list (comma separated) - to draw & handle search form
  public $searchcols = 1; # columns per row in the search bar
  public $bwtextfields = 0; # show text fields mode in browse: 0 - normal, >=1 - TEXTAREA (readonly) (nn of rows)
  public $groupby = ''; # field1,field2,...| TODO!
  public $recursive = ''; # field name that is 'parent record's id in the same table
  public $recursive_show = ''; # field name where we draw PADDING chars/pics, to inform about nesting level
  public $sumfields = ''; # SUM(data1),AVG(data2),... amust if groupby used !
  # buttons for Edit, delete & add actions on browse page
  public $_addcol = 1; # columns in browse table, to show correct placed "ADD" button
  public $brenderfunc = ''; # if set, this func wil be called to render browse row
  public $beforeedit = ''; # call this function on source field values before editing
  public $beforedelete = ''; # call this function before Deleting recors. If this returns FALSE, deletion won't be done
  public $afteredit  = ''; # call this function on modified values before saving to DB
  public $afterupdate = ''; # action after update (insert/update/delete) record
  public $safrm = 0;      #  if non-empty, show simple ADD form in BROWSE-SCREEN last row
  public $confirmdel = 0; # if non-empty, echo onsubmit=Delconfirm... js call
  public $recdeletefunc = ''; # override function to perform DELETE record, if returns non-empty string,
  public $updatefunc = ''; # override normal UPDATE by astedit if You need it
  public $editform = ''; # function that draws record editing form rather than astedit generating
  public $editsubform = ''; # function to draw something after edit form echoed(sub-forms, init.code)
  public $aftereditsubform = ''; # this code will be added after </FORM> tag
#  public $derived = 0;   # becomes 1 if the table has "parent" definition[s]
  public $editmode = ''; # ='endless' : after adding/updating record You return to edit form
  public $dropunknown = 0; # 1: UpgradeTable() will drop 'unknown' fields from table
  public $canview=1;
  public $canedit = NULL;
  public $candelete = NULL;
  public $caninsert = NULL;
  public $ajaxmode = 0;  # set it to 1 if You need to update browse view in AJAX manner
  public $wwtoolbar_ready = 0;
  public $tbrowseid = ''; # unique id for current table views
  public $parenttables = array(); # internal, holds parent table names list
  public $fields = array(); # field list (CFieldDefinition)
  public $reports = [];
  protected $extfields = [];
  public $indexes = array(); # all indexes in the table
  public $customcol = array(); # additional columns (hrefs, images etc)in Browse:
  public $viewfields = array(); # fields to view in browse screen, can be overriden by SetView()
  public $events = array(); # editing-time javascript events for some data types: ['date.onClick']='DateRepair(this)'
  public $windowededit = 0; # dimensions and start position for standalone editing window
  public $rowclassfunc = ''; # will be called for every row to get row's css class (browse page)
  public $ftindexes = array(); # FULLTEXT|f1,f2... sets FULLTEXT index for the table
  public $_picobj = 0; # will becode CImageManager object if there are linked pictures
  public $_mouseoverevents = 1; # by default all rows hilighted with onmouseover event
  public $_wysiwyg = array(); # all WYSIWYG-edited fields will be listed here
  public $_multipart = 0; # becomes TRUE if 'FILE' filed exist, to add ENCTYPE="multipart/form-data" form tag

  # [0]-"file name" field name, [1] - file type (extension) field name, [2]-UDF name to store file (if not standart folder placing)
  public $_savefile_pm = array();

  public $_converters = array(); # ['myfield'] = 'FieldFuncConvertor'
  public $_recursive_level = 0; # current level of recursion (to draw left-padding chars)
  public $_errormessage = ''; # last error message
  public $_halign = 'center';
  public $_drawbrheader = true; # if false, no header in browse screen will be drawn
  public $_browseheadertags = array(); # user <TD> tags for the header line
  public $_drawtdid = false; # if true, every <td> in grid will have id
  public $_auditing = false; # set to function name, to perform auditing tasks (add,update,delete operations)
  private $updateResult = ''; # string result of add/update record
  private $fixFields = [];
  public $errorState = FALSE;
  public $_browsetags = array(); # each column can have specific tags in the browse grid
  public $_frozen = false;
  public $_udf_js = '';
  protected $fullBlistForm = true; // true = draw BLIST items with ID "[x] ID-title"

  public $_updt_all = false; # if true, will override false editcond field property
  public $_enablesinglequote = false;
  protected $clonable = 0; # 1 - clonable (only main record), 2 - record and all child tables data
  protected $clonable_field = '';
  protected $clone_subs = []; # child table[s] ['childtable' => fk_fieldname]
  protected $confirm_clone_child = ''; # confrim text for clone child table recorde
  private $edit_template = FALSE;
  public $_togglefilters = 1; # hideable search toolbar
  public $_viewmode = false; # in VIEW mode no edition possible, all row when clicked, opens "details" for view (func.
  public $_undo_upgrade = true; # safe structure upgrade, false - unsafe
  public $_prefixeditfield = false; # if true, add trable_name to generated <input> tags for fields,
  public $_multiselect = '';
  public $_multiselectFunc = '';
  public $_adjacentLinks = 4;
  public $_edit_jscode = ''; # javascript code template to be called at 'EDIT' event
  public $reset_chain = array(); # reset current filters chains when "parent" filter is changed
  private $file_folder = ''; # Folder to store uploaded files
  protected $baseuri = '';
  protected $_jscode = '';
  private $_inJs = false; # going lines inside <script> ... </script> block
  private $onsubmit = false; # js code for "obsubmit" form tag
  private $strt_time = 0;
  public $filterPrefix = '';

  # constructor (works in PHP5+)
/*  function __construct() {
      if(constant('ADJACENTLINKS')) $this->_adjacentLinks = intval(constant('ADJACENTLINKS'));
  }
*/
  public static function GetVersion() { return Astedit::VERSION; }
  /**
  * @desc Set editor for WYSIWYG fields, editors supported : 'spaw', 'tinymce'.
  */
  public function getDescription($short=false) {
      return ($short ? $this->shortdesc : $this->desc);
  }
  function SetWysiwygType($par=''){ $GLOBALS['ast_wysiwyg_type'] = strtolower($par); }
  function AllFieldsUpdatable($par=true) { $this->_updt_all=$par; }

  /**
  * Overrides default height (and width) for BLIST fields
  *
  * @param mixed $height
  * @param mixed $width
  */
  public function setBlistSize($height, $width=false) {
      $this->blist_height = $height;
      if ($width) $this->blist_width = $width;
  }

  # returns string "success|error" for last update operation
  public function getUpdateResult() { return $this->updateResult; }
  /**
  * @desc ParseTplDef() reads table structure from tpl meta-file
  */
  function ParseTplDef($filename,$foredit=0,$ischild=0) {
    $splitted = preg_split("/[\\/]/",strtolower($filename));
    if(count($splitted)==1) $this->id = $splitted[0];
    else $this->id = $splitted[count($splitted)-2];
    $b_comment = 0;
    if(is_file($filename)) $lst = @file($filename,1);
    else $lst = array();
    if(!is_array($lst) or count($lst)<1) {
        $this->_errormessage = 'Definition file not found or has no fields or read error';
        return false;
    }
    $fieldNo = 0;
    foreach($lst as $lineno => $strkFull){ #<3>

        $strk = trim($strkFull);
        if($b_comment) {
            if (substr($strk,-2)==='*/') $b_comment = false;
            continue;
        }
        if(in_array(strtolower($strk), array('<script>','<js>'))) {
            $this->_inJs = true;
            continue;
        }
        if(in_array(strtolower($strk), array('</script>','</js>'))) {
            $this->_inJs = false;
            continue;
        }

        if($this->_inJs) { # Add this line to JS block
            $this->_jscode .= ($this->_jscode? "\r\n":'') . rtrim($strkFull);
            continue;
        }

        if (substr($strk,0,1) == '#') continue;
        elseif (substr($strk,0,2)=='/*') { $b_comment=1; continue; }
        elseif (substr($strk,-2)==='*/') { $b_comment=0; continue; }
        # charset converting to whole string if needed
        $tar = explode('|', $strk);

        if(!isset($tar[1])) $tar[1] = '';
        $key = strtoupper($tar[0]);
        if ($key==='IF') { # Conditional declaration: IF|{EXPRESSION}|FIELD... etc.
            array_shift($tar);
            if (count($tar)<3) contiue; # wrong IF line
            $ifExp = array_shift($tar);
            $evaluated = astedit::evalValue($ifExp);
            if($evaluated) {
                $key = $tar[0];
            }
            else continue;
        }
        switch($key) { #<4>
        case 'RPP':
          $this->rpp = intval($tar[1])? intval($tar[1]):20;
          break;
        case 'DERIVEDFROM': case 'PARENTTABLES': # get parent table(s) def.
          $derive = explode(',',$tar[1]);
          if(count($derive)>0) {
            $this->parenttables = array_merge($this->parenttables, $derive);
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
          }
          $this->charset = astedit::evalValue($tar[1]);
          if(strtoupper($this->charset)=='UTF8') $this->charset = 'UTF-8';
          break;

        case 'DATACHARSET':
            $this->datacharset = astedit::evalValue($tar[1]);
            if(strtoupper($this->datacharset)=='UTF8') $this->datacharset = 'UTF-8';
          break;
        case 'HELPPAGE':
          $this->helppage = trim($tar[1]); # help page ID (base HTML file name)

        case 'COLLATE':
          $this->collate = strtoupper($tar[1]); # Cyrillic UTF: utf8_general_ci
          break;
        case 'ID':
          $this->id = str_ireplace(Astedit::PREFIX_MACRO,self::$ast_tableprefix,trim($tar[1]));
          if($this->tbrowseid==='') $this->tbrowseid=$this->id;
          break;
        case 'BROWSEID': case 'VIEWID':
          $this->tbrowseid = str_ireplace(Astedit::PREFIX_MACRO,self::$ast_tableprefix,trim($tar[1]));
          break;
        case 'DESCR':
          $this->desc = trim($tar[1]);
          break;
        case 'SDESCR':
          $this->shortdesc = $tar[1];
          break;
        case 'BRFILTER':
          if(substr($tar[1],0,1)==='@') {
              $func = substr($tar[1],1);
              if(is_callable($func)) $oneFilter = call_user_func($func, $this->id);
              else $oneFilter = FALSE;
          }
          else $oneFilter = $tar[1];
          $brlist = explode(',', $oneFilter);
          $this->browsefilter = array_merge($this->browsefilter, $brlist);
          break;
        case 'BRFILTERFN':
          $this->browsefilter_fn = $tar[1];
          break;
        case 'BRHEADER':
          $this->_drawbrheader = !empty($tar[1]);
          break;
        case 'BRORDER': case 'ORDERBY':
          $this->browseorder .= ($this->browseorder==''? '':',').$tar[1];
          break;
        case 'BRWIDTH': case 'GRIDWIDTH':
          $this->browsewidth = $tar[1];
          if (defined('IF_LIMIT_WIDTH')) { # restrict maximal grid width, if IF_LIMIT_WIDTH set
              if (strpos($this->browsewidth,'%')===FALSE)
                $this->browsewidth = min($this->browsewidth, constant('IF_LIMIT_WIDTH'));
          }
          $intVal = intval($this->browsewidth);
          if("$this->browsewidth" === "$intVal") $this->browsewidth .= 'px';
          break;

        case 'EDITFORMWIDTH':
          $this->editformwidth = $tar[1];
          if (defined('IF_LIMIT_WIDTH')) { # restrict maximal grid width, if IF_LIMIT_WIDTH set
              if (strpos($this->editformwidth,'%')===FALSE)
                $this->editformwidth = min($this->editformwidth, constant('IF_LIMIT_WIDTH'));
          }
          break;
        case 'LOADJSCODE':
          # callback for getting js code block OR javascript file name (2025-07-10)
          $loaderFn = $tar[1] ?? '';
          if(is_callable($loaderFn)) {
              $this->_jscode .= call_user_func($loaderFn, $this->id);
          }
          elseif(is_file($loaderFn)) $this->_jscode .= file_get_contents($loaderFn);
          break;

        case 'SEARCH':
          $expr = $tar[1];
          if(substr($expr,0,1)==='@') {
              $func = substr($expr, 1);
              if(is_callable($func)) $expr = call_user_func($func);
              else $expr = '';
          }
          if($expr) $this->search .= ($this->search==''? '':',') . $expr;
          break;

        case 'SEARCHCOLS':
          $this->searchcols = intval($tar[1]);
          break;
        case 'BLISTHEIGHT':
          $this->blist_height = trim($tar[1]);
          if ($this->blist_height == '') $this->blist_height = '100px';
          break;
        case 'BLISTFULLFORM': case 'BFF':
          $this->fullBlistForm = !empty($tar[1]);
          break;
        case 'DROPUNKNOWN':
          $this->dropunknown = $tar[1];
          break;
        case 'DEBUG':
          $this->debug = $tar[1];
          break;
        case 'SAFRM': # Simple Adding Form in last row
          $vl = empty($tar[1]) ? '' : $tar[1];
          $this->safrm = astedit::evalValue($vl);
          if(empty($this->safrm)) $this->safrm = '';
          break;
        case 'CONFIRMDEL': case 'CONFIRMDELETE':
          $vl = empty($tar[1]) ? '' : $tar[1];
          $this->confirmdel = $vl;
          break;
        case 'WINDOWEDEDIT':
          if(empty($tar[1]) || empty($tar[2])) $this->windowededit = 0;
          else {
            $this->windowededit = array('width'=>$tar[1], 'height'=>$tar[2],
              'left'=>(empty($tar[3])?-1:intval($tar[3])),
              'top'=>(empty($tar[4])?-1:intval($tar[4]))
            );
          }
          break;
        case 'ROWCLASSFUNC':
          $this->rowclassfunc = empty($tar[1])? '' : $tar[1];
          break;
        case 'CHILDTABLE':
          $tblname = empty($tar[1])? '' : $tar[1]; # child table name
          $fld1 = empty($tar[2])? $this->AllPkFields() : $tar[2]; # field in this table
          $fld2 = empty($tar[3])? $fld1 : $tar[3]; # FK-field in child table
          $addcondition = empty($tar[4])? '' : $tar[4]; # additional condition to select records in child table
          $del_protect = empty($tar[5])? '' : $tar[5]; # error message if existing children protect from deleteing parent rec
          if(!empty($tblname) && !empty($fld1) ) {
            $this->childtables[] = array('table'=>$tblname, 'field'=>$fld1, 'childfield'=>$fld2,
            'condition'=>$addcondition,'protect'=>$del_protect);
          }
          break;
        case 'CHILDTABLES': # user function that return array of "child table" definitions, row[] = array(child_table,local_field,child_field,message)
          if (is_callable($tar[1])) {
              $charr = call_user_func($tar[1]);
              if (is_array($charr)) foreach ($charr as $citem) {
                  if (count($citem)>=4) $this->childtables[] = array('table'=>$citem[0], 'field'=>$citem[1], 'childfield'=>$citem[2],
                    'condition'=>'','protect'=>$citem[3],'_func'=>FALSE);
              }
              #  file_put_contents('_st.log', print_r($this->childtables,1));
          }
          break;
        case 'PAGELINKS':
          $this->pagelinks = empty($tar[1])? 0 : $tar[1];
          break;
        case 'ADJACENTLINKS':
          $this->_adjacentLinks = intval($tar[1]);
          break;
        case 'BRENDERFUNC':
          $this->brenderfunc = empty($tar[1])? '' : $tar[1];
          break;
        case 'BROWSESTYLES':
          $flds = preg_split("/[\s,;]+/",$tar[1]);
          foreach($flds as $fldid) {
              if(!empty($fldid)) {
                  if(!isset($tar[2]) || empty($tar[2])) unset($this->_browsetags[$fldid]);
                  else $this->_browsetags[$fldid] = trim($tar[2]);
              }
          }
        case 'RECDELETEFUNC':
          $this->recdeletefunc = empty($tar[1])? '' : $tar[1];
          break;
        case 'UPDATEFUNC':
          $this->updatefunc = empty($tar[1])? '' : $tar[1];
          break;
        case 'EDITFORM':
          $this->editform = empty($tar[1])? '' : $tar[1];
          break;
        case 'EDITTEMPLATE':
          $etplFname = empty($tar[1])? '' : $tar[1];
          if (!empty($etplFname)) {
              if (!is_file($etplFname)) {
                  $tdir = dirname($this->_tplfile);
                  if (is_file("$tdir/$etplFname")) $etplFname = "$tdir/$etplFname";
                  else $etplFname = '';
              }
              if ($etplFname) $this->edit_template = $etplFname;
          }
          break;
        case 'EDITSUBFORM':
          $this->editsubform = empty($tar[1])? '' : $tar[1];
          break;
        case 'AFTEREDITSUBFORM':
          $this->aftereditsubform = empty($tar[1])? '' : $tar[1];
          break;
        case 'EDITMODE':
          $this->editmode = empty($tar[1])? '' : $tar[1];
          break;
        case 'EVENT': case 'EDITEVENT':
          $etype = isset($tar[1])? strtoupper($tar[1]) : '';
          $eevent = isset($tar[2])? $tar[2] : '';
          $efunc = isset($tar[3])? $tar[3] : '';
          if(!empty($etype) && !empty($eevent)) {
            if(!isset($this->events[$etype])) $this->events[$etype] = array();
            $this->events[$etype][$eevent] = $efunc;
          }
          break;

        case 'CLONABLE':

          $this->clonable = isset($tar[1]) ? $tar[1] : 0;
          $this->clonable_field = isset($tar[2]) ? $tar[2] : ''; # what field will contain 'newname' in cloned record
          $subs = isset($tar[3]) ? $tar[3] : '';
          if ($subs != '') {
              $subList = preg_split( '/[,;]/', $subs, -1, PREG_SPLIT_NO_EMPTY );
              foreach($subList as $item) {
                  $pair = explode(':',$item);
                  if (count($pair)>1)
                    $this->clone_subs[$pair[0]] = $pair[1];
              }
          }
          # if non empty confirm text, user will be asked for cloning child table records
          if (!empty($tar[4])) $this->confirm_clone_child = $tar[4];

          break;

        case 'BEFOREEDIT':
          $this->beforeedit = isset($tar[1]) ? $tar[1] : '';
          break;

        case 'BEFOREDELETE':
          $this->beforedelete = isset($tar[1]) ? $tar[1] : '';
          break;
        case 'ONSUBMIT':
          $this->onsubmit = isset($tar[1]) ? trim($tar[1]) : '';
          break;

        case 'AFTEREDITFUNC': case 'AFTEREDIT': case 'BEFOREUPDATE':
          $this->afteredit = isset($tar[1]) ? $tar[1] : '';
          # WriteDebugInfo("afetreditfunc: ", $this->afteredit);
          break;
        case 'AFTERUPDATE':
          $this->afterupdate = isset($tar[1]) ? $tar[1] : '';
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
          $this->_auditing = $tar[1];
          break;
        case 'FILEFOLDER':
          $this->file_folder = astedit::evalValue(trim($tar[1]));
          # FileFolder can be user callback func
          break;

        case 'BLISTSIZE': // BLISTSIZE|200px [|100px] - Height and width of BLIST field while edit

          $height = trim($tar[1]);
          $width = isset($trim[2]) ? trim($tar[2]) : false;
          $this->setBlistSize($height, $width);
          break;

        case 'FIELD': # parse field def.
        case 'EXTFIELD': # External Field (stored outside of this table!

          $fieldNo++;
          $fld = new CFieldDefinition();
          $fld->derived = $ischild;
          $k = count($this->fields)+1;
          $fldid = $fld->id = empty($tar[1])? "id".$fieldNo : trim($tar[1]);

          # $fldid = $fld->id = strtolower($fldid); # fieldname is alwais low case ?
          $fld->specs = empty($tar[2])? FALSE : trim($tar[2]);

          if( isset($this->fields[$fldid]) ) {
              # WriteDebugInfo("$fldid already set:", $this->fields[$fldid]);
              continue 2; #field exists!
          }

          $fld->desc = empty($tar[3])? $fldid : trim($tar[3]);
          $fld->shortdesc = empty($tar[4])? ($foredit? '' : $fld->desc) : $tar[4];
          # $fld->shortdesc = empty($tar[4])? ($foredit? '' : $fld->desc) : Astedit::ast_trim($tar[4]);

          $fftype = isset($tar[5])? preg_split("/[\s,]+/", $tar[5]) : array('INT');
          $fld->type = '';
          $fld->length = isset($tar[6]) ? trim($tar[6]) : '';
          $notNull = FALSE;
          foreach($fftype as $tpitem) {
            $tpitem=strtoupper($tpitem);
            if($tpitem==='LOGIN') {
                $fld->subtype = 'LOGIN';
            }
            elseif($tpitem==='PK' || $tpitem==='PKA') {
                if(!$this->_pkflistset) {
                    $this->_pkfields[]=$fldid;
                }

                if(!$this->_pkfield) $this->_pkfield = $fldid;
                if($tpitem==='PKA') $fld->_autoinc=true;
                if(empty($fld->type)) $fld->type='BIGINT';
                if(empty($fld->length)) $fld->length=20;
                $notNull = TRUE;
            }
            else $fld->type = $tpitem; # CHAR/VARCHAR/INT,...
          }

          if ($fld->type === 'NUMBER') { # Oracle 'NUMBER' - to INT or DECIMAL (MySQL)
              $splen = explode(',',$fld->length);
              $fld->type = (count($splen)>1 && $splen[1]>0) ? 'DECIMAL':'INT';
          }
          # if($fld->type=='TINYINT' && !$fld->type) $fld->type=1;

          if($fld->type === 'DATE' || $fld->type === 'DATETIME' || $fld->type === 'TIMESTAMP' || $fld->type === 'TIME'
            || strpos($fld->type,'TEXT')!==false)
                 $fld->length = ''; # error length protect
          $fld->notnull = (!empty($tar[7]) || $notNull);
          $fld->defvalue = isset($tar[8])?$tar[8]:'';
          $fld->showcond = empty($tar[9])? '' : astedit::evalValue($tar[9]);

          $fld->showformula = empty($tar[10])? '' : trim($tar[10]);

          if ($fld->showformula !=='') {
              $splt = explode(',',$fld->showformula);
              $fld->showformula = array_shift($splt);
              $fld->showattr = $splt; # [0]=text-align,[1]-bgcolor(may be @function),[2]-css class in grid
          }
          $fld->editcond = empty($tar[11])? 0 : trim($tar[11]);
          $trimmedType = trim($tar[12]);
          if ($fld->type === 'TIME' && empty($trimmedType)) {
              $trimmedType = 'TIME'; # native browser TIME input
              writeDebugInfo("time input for $fld->id");
          }
          $fld->edittype = empty($trimmedType)? 'TEXT' : $trimmedType;
          $_arr = explode('^',$fld->edittype);
          if(count($_arr)<2) $_arr = explode(',',$fld->edittype);
          if(strtoupper('WYSIWYG'===$_arr[0]))  $this->_wysiwyg[] = $fld->id;
          elseif('FILE'===strtoupper($_arr[0])) {
              $this->_multipart=true;
              $tp = $_arr; array_shift($tp);
              $this->_savefile_pm[$fldid] = $tp;
          }

          if(!empty($tar[13])) { # INDEX_NAME[,UNIQUE]
             $tar[13] = trim($tar[13]);
             $_arr = explode(',', $tar[13]);
             $fld->idx_name = $_arr[0];
             $fld->unique = empty($_arr[1]) ? 0 : 1;
          }

          $fld->showhref = empty($tar[14])? '' : trim($tar[14]);
          $fld->hrefaddon = empty($tar[15])? '' : trim($tar[15]); # доп.атрибуты для <a href>
          $fld->afterinputcode = isset($tar[16])? $tar[16] : '';

          if(!empty($tar[17])) $fld->editRowClass = trim($tar[17]);

          if ($key === 'EXTFIELD') {
              $fld->external = TRUE;
                if (!empty($tar[2])) {
                      # external field: here should be "getter,setter" function names
                      $gs = explode(',',$tar[2]);
                      $fld->getter = $gs[0];
                      $fld->setter = !empty($gs[1]) ? $gs[1] : FALSE;
                      $this->extfields[$fldid] = $fld;
              }
          }
          $this->fields[$fldid] = $fld;

          break;

        case 'PRIMARYKEY': case 'PK': # fields in PRIMARY KEY() list
          $this->_pkfields=preg_split('/[, ;]+/',$tar[1]);
          $this->_pkflistset=true;
          break;

        case 'INDEX':
          $idx = new CIndexDefinition();
          $idx->name  = empty($tar[1])? "idx_{$this->id}{$k}" : trim($tar[1]);
          $idx->expr = empty($tar[2])? '' : trim($tar[2]);

          $idx->unique = empty($tar[3])?'':$tar[3];
          $idx->derived = $ischild;
          # $fld->descending = !empty($tar[4]);

          if(!empty($idx->expr) || !empty($foredit))
            $this->indexes[] = $idx;

          break;

        case 'FULLTEXT': case 'FTINDEX':
          $idxname = count($tar)>1 ? trim($tar[1]) : ('ft_'.$this->$id.(count($this->ftindexes)+1) );
          $idxlst  = count($tar)>2 ? $tar[2] : $tar[1];
          $this->ftindexes[$idxname] = $idxlst;
          break;
        case 'CUSTOMCOLUMN': # non-field columns in browse page #ex-BRCUSTOMHREF
          $htmlcode  = isset($tar[1])? $tar[1] : '';
          if(!empty($htmlcode)) $htmlcode = astedit::evalValue($htmlcode, $this->id);
          $htitle  = isset($tar[2])? $tar[2] : '';
          $addon = isset($tar[3])? $tar[3] : ''; # доп.атрибуты для <a href="" ...>
          if(!empty($htmlcode)) {
            $this->customcol[] = array('htmlcode'=>$htmlcode, 'title'=>$htitle, 'addon'=>$addon,'derived'=>$ischild);
            $cnt = count($this->customcol)-1;
          }
          break;

        case 'REPORT':
          $rep_id = !empty($tar[1]) ? $tar[1] : ('report'.(count($this->reports)+1));
          $rep_fields = $tar[2] ?? '';
          $rep_filter = $tar[3] ?? '';
          $rep_label = $tar[4] ?? '';

          if(!empty($rep_label)) $rep_label = astedit::evalValue($rep_label, [$this->id, $rep_id]);
          $rep_title = $tar[5] ?? '';
          if(!empty($rep_title)) $rep_title = astedit::evalValue($rep_title, [$this->id, $rep_id]);

          $this->reports[$rep_id] = [
            'fields' => $rep_fields,
            'filter' => $rep_filter,
            'label' => $rep_label,
            'title' => $rep_title,
          ];
          if(is_callable('asteditReport::getReportButtonHtml')) {
            $reportButton = asteditReport::getReportButtonHtml($this->id, $rep_id, $rep_label,$rep_title);
          }
          else {
            # auto-add HTML button on toolbar (for executing report)
            $rTitle = $rep_title ? "title='$rep_title'" : '';
            $reportButton = "<input type=\"button\" id=\"$rep_id\" class=\"btn\" value=\"$rep_label\" "
              . "onclick=\"asteditReport.run('$this->id','$rep_id')\" $rTitle/>";
          }
          $this->toolBar[] = $reportButton;
          break;

        case 'TOOLBAR': # HTML code for toolbar (buttons etc.)
          $htmlcode  = isset($tar[1])? $tar[1] : '';
          if($htmlcode) $this->toolBar[] = astedit::evalValue($htmlcode, $this->id);
          break;
        case 'RECURSIVE':
          $this->recursive = $tar[1];
          $this->recursive_show = empty($tar[2])? '': $tar[2];
          break;
        case 'VIEWMODE':
          $this->SetViewMode($tar[1]);
          break;

        case 'MULTISELECT':
          $this->_multiselect=true;
          $this->_multiselectFunc = isset($tar[1]) ? trim($tar[1]) : '';
          break;
        case 'TYPE': case 'ENGINE':
          $this->tabletype = $tar[1];
          break;
        case 'RESETCHAIN':
          if(!empty($tar[1])) $this->reset_chain[] = preg_split( self::DEFAULTDELIMS, $tar[1], -1, PREG_SPLIT_NO_EMPTY );
          break;

        case 'RIGHTS': # rights|funcName - should return array with up to 4 items: [view,edit,delete,insert]. No value means "no right"
          # OR: RIGHTS|NV|NE|ND|NA : NV - view right, NE-edit, ND-delete, NA-add
          if (!empty($tar[1])) {
            if(is_callable($tar[1])) {
              list($this->canview, $this->canedit, $this->candelete, $this->caninsert) = call_user_func($tar[1], $this->id);
            }
            else {
                $this->canview = $tar[1] ?? FALSE;
                $this->canedit = $tar[2] ?? FALSE;
                $this->candelete = $tar[3] ?? FALSE;
                $this->caninsert = $tar[4] ?? FALSE;
                # writeDebugInfo("rights list:VIEW=[$this->canview]EDIT=[$this->canedit]DEL=[$this->candelete]A=[$this->caninsert]");
            }
          }
          break;

        } #<4> switch
     } #<3>
     if ($this->datacharset=='') $this->datacharset = $this->charset;
     # localization block with appEnv, if defined
/*     if(class_exists('appenv')) {
         if($locr=appEnv::getLocalized($this->id.'.'.'descr') && $locr!==$this->id.'.'.'descr') $this->desc =  $locr;
         if($locr=appEnv::getLocalized($this->id.'.'.'sdescr' && $locr!==$this->id.'.'.'sdescr')) $this->shortdesc =  $locr;
         foreach($this->fields as $fid=>$fld) {
            if ($locr=appEnv::getLocalized('t.'.$this->id.'.'.$fld->id) && $lock !=='t.'.$this->id.'.'.$fld->id) $this->fields[$fid]->desc =  $locr;
            if ($locr=appEnv::getLocalized('ts.'.$this->id.'.'.$fld->id) && 'ts.'.$this->id.'.'.$fld->id) $this->fields[$fid]->shortdesc =  $locr;
            if (empty($this->fields[$fid]->shortdesc)) $this->fields[$fid]->shortdesc = $this->fields[$fid]->desc;
         }
     }
*/
     # auto conect online Help if file exist
     $helpFile = dirname($this->_tplfile). "/" . $this->id . ".help.htm";
     if (is_file($helpFile)) $this->helppage = 1;

     # auto conect form if file exist
     $formFile = dirname($this->_tplfile). "/" . $this->id . ".form.htm";
     if (is_file($formFile) && empty($this->edit_template)) $this->edit_template = $formFile;
     # Add "externally added dependensies"
     if (class_exists('appEnv') && is_callable('appEnv::getTableDependencies') && $deps = appEnv::getTableDependencies($this->id))
     {
        foreach($deps as $childName=>$items) {
            if (is_array($items) && isset($items[0])) foreach($items as $no => $item) {
                $this->childtables[] = array('table'=>$childName, 'field'=>$item[0], 'childfield'=>$item[1],
                'condition'=>'','protect'=>$item[2]);
            }
        }
     }

     return 1;
  }

  protected function _getRecordData($recid) {
      $ret = Astedit::$db->select($this->id, [
        'where'=> [$this->_pkfield => $recid],
        'singlerow'=>1,
        'associative' => 1,
      ]);
      return $ret;
  }
  /**
  * @desc AddField adds one field to the structure definition
  */
  function AddField($fldid,$ftype='VARCHAR',$flen=10, $fdesc='',$sdesc='',$notnull=0,$defvalue='',
    $showcond=1,$showformula='',$econd=1,$etype='',$idx='',$showhref='',$hrefaddon='') {
    $fldid = strtolower($fldid);
    if( isset($this->fields[$fldid]) ) return false; #field exists!
    $fld = new CFieldDefinition;
    $fld->id = $fld->name = $fldid;
    $fld->type = empty($ftype)? 'VARCHAR' : trim($ftype);
    $fld->desc = empty($fdesc)? '' : trim($fdesc);
    $fld->shortdesc = empty($sdesc)? $fld->desc : trim($sdesc);
    $fld->type = strtoupper($fld->type);
    if($fld->type == 'PKA' || $fld->type == 'PK') { #<3>
      $fld->type ='BIGINT'; #
      $fld->length=20;
      if(!$this->_pkflistset) $this->_pkfields[]=$fld->id;
    } #<3>
    $fld->length = empty($flen) ? '' : trim($flen);
    if($fld->type === 'DATE' || $fld->type == 'DATETIME' || $fld->type == 'TIMESTAMP' ||
      strpos($fld->type,'TEXT')!==false) $fld->length = ''; # error length protect
    $fld->notnull = $notnull;
    $fld->defvalue = $defvalue;
    $fld->showcond = $showcond;
    $fld->showformula = $showformula;
    $fld->editcond = $econd;
    $fld->edittype = $etype;
    $_arr = explode('^',$fld->edittype);
    if(count($_arr)<2) $_arr=explode(',',$fld->edittype);
    $upetype = strtoupper($_arr[0]);
    if($upetype==='WYSIWYG') $this->_wysiwyg[] = $fldid;
    elseif($upetype==='FILE') $this->_multipart = true;
    if(!empty($idx)) {
      $idx = trim($idx);
      $idx_ar = explode(',', $idx);
      $fld->idx_name = $idx_ar[0];
      $fld->unique = empty($idx_ar[1]) ? 0 : 1;
    }
    $fld->showhref = $showhref;
    $fld->hrefaddon = $hrefaddon;
    $this->fields[$fldid] = $fld;
    if(astedit::evalValue($fld->showcond)) $this->viewfields[] = $fldid;
    return true;
  }
  function SetRecursive($fldname='',$showrecursion='') {
    $this->recursive = $fldname;
    $this->recursive_show = $showrecursion;
  }
  function AddCustomColumn($htmlcode,$title='',$addon='') {
    $this->customcol[] = array('htmlcode'=>$htmlcode,'title'=>$title,'addon'=>$addon);
  }
  function SetBrowseHeaderTags($fieldid,$tags='') {
    $this->_browseheadertags[$fieldid] = $tags;
  }
  function SetPrefixField($parm) { $this->_prefixeditfield = $parm; }
  /**
  * @desc Binds this table with "pictures" engine, so You can attach/view photos for each record
  */
  function SetLinkedPictures($tablename,$subfolder,$viewscript='',$width=0,$height=0,$tn_w=0,$tn_h=0,$jslibrary=0){
    if(class_exists('cimagemanager'))
       $this->_picobj = new CImageManager(array(
         'tablename' => $tablename,
         'picfolder' => $subfolder,
         'width' => $width,
         'height' => $height,
         'tn_width' => $tn_w,
         'tn_height' => $tn_h,
         'jslibrary' => $jslibrary,
         'adminmode' => 1
    ));
    if(is_object($this->_picobj)) {
      if($viewscript) $this->_picobj->SetViewScript($viewscript);
#      $this->_picobj->UseJsLibrary($jslibrary);
      $this->_multipart = true;
    }
  }

  /**
  * reads table definition from .tpl file
  * @param $fileName - tpl file name to read from
  * @param $csetTo - if set, all attribs will be converted
  * @param $foredit - if non-empty, reads only non-child fields (for modify & save)
  * @param $ischild - flag that means this is a child table
  */
  function ReadDefinition($fileName, $csetTo='', $foredit=0, $ischild=0) {
    # writeDebugInfo("$fileName, set to: [$csetTo]");
    $fext = strtolower(substr($fileName,-4));
    $this->_tplfile = $src_file = $fileName;
    $folders = self::$tplFolder;
    if (class_exists('appEnv') && isset(appEnv::$tplFolders) && is_array(appEnv::$tplFolders))
        $folders = array_merge($folders, appEnv::$tplFolders);
     if($fext!=='.tpl' && $fext !=='.xml') {
        $fext = '.'.DEFAULT_TPLTYPE;
        if(file_exists($fileName.$fext)) {
          $this->_tplfile = $src_file = $fileName.$fext;
        }
        elseif( count($folders)>0) {
            foreach($folders as $tpfold) {
                if(file_exists($tpfold . $fileName.$fext)) {
                    $this->_tplfile = $src_file = $tpfold . $fileName.$fext;
                    break;
                }
            }
        }
        else {
            if(!$this->LoadTableDefFromDB($fileName)) {
                $this->_errormessage="{$fileName}{$fext} does not exist";
                return false;
            }
        }
        $fileName .= $fext;
     }
     else {
         $src_file = $fileName;
     }
#     $this->charset = MAINCHARSET; # why that ???
#     if($fext=='.xml') $result = $this->ParseXmlDef($src_file,$foredit,$ischild);
#     else

     $result = $this->ParseTplDef($src_file,$foredit,$ischild);
     if(function_exists('AstStructModifier')) AstStructModifier($this);
     $this->filename = $fileName;
     foreach($this->fields as $fld) {
       if(astedit::evalValue($fld->showcond)) $this->viewfields[] = $fld->id;
     }
     if($csetTo=='') $csetTo = MAINCHARSET;
     if($this->charset!='' && $csetTo!=$this->charset && is_string($csetTo)) {
         # writeDebugInfo("calling ConvertCharSet to [$csetTo]");
         $this->ConvertCharSet($csetTo);
     }

     if(empty($foredit) && count($this->parenttables)) {
       foreach($this->parenttables as $parentname) {
         if($parentname=='') continue;
         $parent = new CTableDefinition($parentname,$csetTo,$foredit,0,1);
         foreach($parent->fields as $fid=>$f) {
           if(isset($this->fields[$fid])) continue;
           $this->fields[$fid] = $f;
           if(astedit::evalValue($f->showcond)) $this->viewfields[] = $f->id;
         }
         if(count($parent->_pkfields)) $this->_pkfields = array_merge($this->_pkfields,$parent->_pkfields);
         $this->childtables = array_merge($this->childtables,$parent->childtables);
         $this->indexes = array_merge($this->indexes,$parent->indexes);
         $this->customcol = array_merge($this->customcol,$parent->customcol);
         $this->ftindexes = array_merge($this->ftindexes,$parent->ftindexes);
         if(empty($this->rowclassfunc)) $this->rowclassfunc = $parent->rowclassfunc;
       }
     }

     return $result;
  }
  function setBaseUri($uri='') {
      $this->baseuri = ($uri ? $uri : $_SERVER['PHP_SELF']);
      $this->baseuri .= (strpos($this->baseuri,'?') ? '&' : '?');
  }

  public static function setTplFolder($path) { self::$tplFolder = is_array($path)? $path : explode(',',$path); }

  public static function addTplFolder($path) {
      if (is_scalar($path)) $path = explode(',', $path);
      if (is_array($path)) {
          foreach($path as $item) { # avoid duplicated folders
            if(!in_array($item, self::$tplFolder)) self::$tplFolder[] = $item;
          }
      }
  }

  public function __construct($tablename='', $charset='', $foredit = false, $ajax=0, $ischild=0) {

    astedit::init();
    if (defined('IF_LIMIT_WIDTH') && constant('IF_LIMIT_WIDTH')<800 ) { # restrict maximal grid width, if IF_LIMIT_WIDTH set
       $this->searchcols = 1;
    }

    $this->setBaseUri();

    if(defined('FOLDER_TPL')) self::addTplFolder(constant('FOLDER_TPL'));
    #    self::$tplFolder = defined('FOLDER_TPL') ? array('',constant('FOLDER_TPL')) : array('');
    if(!defined('IMGPATH')) define('IMGPATH','img/'); # path to all button pics and other graphics
    if(defined('ADJACENTLINKS')) $this->_adjacentLinks = intval(constant('ADJACENTLINKS'));
    # Support table prefix substitute, if set in appEnv class or global defined const : "%tabprefix%name" will converted to "somePrefix_name"
    if(class_exists('appEnv') && property_exists('appEnv','TABLES_PREFIX')) self::$ast_tableprefix = appEnv::TABLES_PREFIX;
    elseif(defined('AST_TABLEPREFIX')) self::$ast_tableprefix = constant('AST_TABLEPREFIX');

    $this->ajaxmode = $ajax;
    $result = true;
    if($tablename)  $result=$this->ReadDefinition($tablename,$charset, $foredit,$ischild);
    return $result;
  }
  /**
  * Tries to load table metadata from database
  *
  * @param mixed $tablename
  */
  function LoadTableDefFromDB($tablename) {

      $flds = Astedit::$db->GetFieldList($tablename);
      if(!is_array($flds)) return false;
      $this->id = $tablename;
      foreach($flds as $dbfld) {
          $fldid = $dbfld[0];
          $splt = explode('(',$dbfld[1]); # 'int(20)' => 'int,20,''
          $newfld = new CFieldDefinition();
          $newfld->id = $newfld->desc = $fldid;
          $newfld->type = $splt[0];
          $newfld->showcond = 1;
          $newfld->editcond = 1;
          $newfld->edittype = 'TEXT';
          if($dbfld[3]=='PRI') {
            $newfld->type = 'PK' . (($dbfld[5]=='auto_increment')?'A':'');
            $newfld->editcond=false;
          }
          $newfld->length = isset($splt[1]) ? intval($splt[1]) : '';
          $this->fields[$fldid] = $newfld;
      }
      return true;
  }
  function SetRowsPerPage($ipar) {
    $this->rpp = $ipar; # rows per page in browse screen
  }
  function SetAjaxMode($ajax=1) { $this->ajaxmode = $ajax; }

  function SetViewMode($par=true) { $this->_viewmode=$par; }

  /**
  * @desc RegisterFunction - registers function in $ast_frmfunctions
  * @param $fegtype: 'formelement' - function for drawing FORM element for <$fieldtype> type:
  * CTableDefinition::RegisterFunction('FORMELEMENT','DATE','MyDrawFunction'); MyDrawFunction(action,fldname[,$data_array] [,$addon])
  **/
  function RegisterFunction($regtype,$fieldtype,$funcname) {
    global $ast_frmfunctions;
    $regtype = strtoupper($regtype);
    if(empty($regtype)) $regtype = AST_FORM_ELEMENT;
    $fieldtype = strtoupper($fieldtype);
    if(!isset($ast_frmfunctions[$regtype])) $ast_frmfunctions[$regtype] = array();
    $ast_frmfunctions[$regtype][$fieldtype] = $funcname;
  }

  function BrowseHeader() { # returns "<tr>...</tr>" for browsing header row
     global $self, $cursortfld,$as_cssclass, $cursortord,$astbtn;
     $rowclass = defined('USE_JQUERY_UI') ? 'ui-jqgrid-labels' : $as_cssclass['trowhead']; # ui-jqgrid-labels
     $tdclass = defined('USE_JQUERY_UI') ? 'ui-th-column' : $as_cssclass['tdhead']; # ui-widget-header ui-th-div-ie
     $ret = "<thead><tr class=\"$rowclass\">\n";
     $tcnt = 0;
     $this->_addcol = 1;
     if(!isset($cursortfld))
       $cursortfld = $_SESSION[$this->filterPrefix.'ast_sortfld_'.$this->id] ??  '';

     if(!isset($cursortord))
         $cursortord = $_SESSION[$this->filterPrefix.'ast_sortord_'.$this->id] ?? '0';

     if(!empty($this->groupby)) {
       $flds = explode(',', $this->groupby);
       for($kk=0; $kk<count($flds); $kk++)
         echo "<th class='$tdclass'>".$flds[$kk]."</th>";

       $flds = explode(',', $this->sumfields);
       for($kk=0; $kk<count($flds); $kk++)
         echo "<th class='$tdclass'>".$flds[$kk]."</th>";
       return $ret;
     }
     foreach($this->viewfields as $fno=>$fid) {
       $desc='';
       if(!isset($this->fields[$fid]) || $fid[0]=='@') {
         $desc = @call_user_func(substr($fid,1),'header');
       } # UDF field
       else { #<4>
       $fld = $this->fields[$fid];
         if(!empty($fld->showcond)){ #<5>
           $desc = empty($fld->shortdesc)? $fld->desc : $fld->shortdesc;
           if($fld->showcond === 'S') { #<6> sorted field
             if($cursortfld===$fld->id) {
               # current fld is in ORDER now
               $img = IMGPATH. ($cursortord ? 'sortup.gif' : 'sortdown.gif');
               $desc = "$desc &nbsp;<img src='$img' width=6 height=6 border=0 />";
             }
             $desc ="<a href='{$this->baseuri}ast_act=sort&ast_t=$this->id&ast_f=$fld->id'>$desc</a>";
           } #<6>
         } #<5>
       } #<4>
       if($desc!=='') { #<4>
         $stags = isset($this->_browseheadertags[$fid])? $this->_browseheadertags[$fid]:'';
         $ret .= "<th class='$tdclass' $stags>$desc</th>";
         $tcnt++;
       } #<4>
     }
     if(count($this->customcol)>0){
       foreach($this->customcol as $custcol) {
          $ttl = $custcol['title'];
          if($ttl=='') $ttl = '&nbsp;';
          $ret .="<th class='$tdclass' width=0>$ttl</th>"; # title for custom column
          $tcnt++;
       }
     }
     $btWidth = $astbtn['w'] ?? 16;
     if($this->canedit && empty($this->_viewmode)  && count($this->_pkfields)) {# add href for editing row
       $ret .= "<th class='$tdclass' width='$btWidth'><i class='bi bi-pencil'></i></th>\n";
       $tcnt++;
     }
#     $ret .= '<td><a href="'.$_SERVER['PHP_SELF']."?action=edit&id=$id</td>";
     if(($this->candelete) && empty($this->_viewmode) && count($this->_pkfields)){# add href for delete
       $ret .= "<th class='$tdclass' width='$btWidth'><i class='bi bi-x'></i></th>\n";
       $tcnt++;
     }
     if($this->_multiselect) { # checkboxes in the header and every grid row, for multi-selecting
        $ret .="<th class='$tdclass'><input type='checkbox' id='multi_{$this->tbrowseid}' onclick='EvtclickMulti{$this->tbrowseid}(this)' /></th>";
        $tcnt++;
     }

     if($this->_addcol < $tcnt) { $this->_addcol = $tcnt; }
     if($this->_viewmode) { $ret .="<th class='$tdclass'>&nbsp;</th>"; $tcnt++; }
     $ret .= "</tr></thead>\n";
     # delimiter row:
     # $ret .= "<tr><td colspan=\"$tcnt\" class=\"head\"><span style='font-size:2px;'>&nbsp</span></td></tr>\n";
     return $ret;
  } # BrowseHeader()
  function AddSearchFields($strlist) {
    $this->search .= ($this->search==''? '':',').$strlist;
  }
  /**
  * drawSearchBar - draws a bar for filter/search conditions
  *
  */
  function drawSearchBar() {
     global $ast_tips,$as_cssclass,$self,$ast_hidesearchbar;
     $txtclass = empty($as_cssclass['textfield'])?'':"class='{$as_cssclass['textfield']}'";
     $btnclass = empty($as_cssclass['button'])?'':"class='{$as_cssclass['button']}'";
     if(empty($this->search)) return;
     $showreset=0;
     $tid = 'flt_'.$this->tbrowseid; # id in $_SESSION
     if(!empty($this->filterPrefix))
        $tid = $this->filterPrefix . '-' . $tid;

     $searchcoll = explode(',',$this->search);
     if(count($searchcoll)<1) return;
     $sbody = "<!-- astedit search bar -->\n
       <table class='table mx-auto w-auto border-primary table-light'>\n"; # astedit-search
     $envelope = array('',''); #search bar envelope
     $icol = 0;
     $rowstarted = false;

     foreach($searchcoll as $isc=>$item) { #<2>
       $tsplit = explode('/', $item); # case "...,fieldname/range,..." - ranged search !
       $fname = $tsplit[0];
       $sr_type = empty($tsplit[1])? '' : strtolower($tsplit[1]);
       $searchcode = '';
       $icol++;
       $fltfunc = '';
       if(substr($fname,0,1)=='@') { # @filter_function
         $fltfunc = substr($fname,1);
         if(is_callable($fltfunc)) {
           $searchcode = "<input type='hidden' name='search_function' value='$fltfunc' />".call_user_func($fltfunc,'form');
         }
       }
       else { #<4> draw standard search form for a field
         $ffunc = '';
         $fsplit = explode(':',$fname);
         if(count($fsplit)>1) {
             $fname = $fsplit[0];
             $ffunc = is_callable($fsplit[1])?$fsplit[1]:'';
         }

         $values = $_SESSION[$tid] ?? [];
       # TODO : field=2 is not good for SELECTED
         if (!isset($this->fields[$fname])) continue; # wrong field name
         $fld = $this->fields[$fname];
         if($sr_type === 'range') {
            $search1 = $this->drawInput($fname,'from','',$values);
            $search2 = $this->drawInput($fname,'to','',$values);
            $lb_from = is_callable('appEnv::getLocalized') ? appEnv::getLocalized('label_range_from','') : ' ';
            $lb_to   = is_callable('appEnv::getLocalized') ? appEnv::getLocalized('label_range_to','...') : '...';
            $searchHtml = $fld->shortdesc . ': '. $lb_from . ' '.$search1 . ' ' . $lb_to .' '. $search2;
         }
         else {
            $searchHtml = $this->drawInput($fname,true);
         }
         # $searchcode .= "<td nowrap style='vertical-align:top; text-align:right; width:90%'>".$searchHtml . '</td>';
         $searchcode .= $searchHtml;
       } #<4> <std code>

       if(!$rowstarted) {
           $rowstarted = true;
           $sbody .= "<tr>"; #  class='{$as_cssclass['troweven']}'>";
       }
       $formcode = "<form name='search{$isc}' method='post'>
                      <input type='hidden' name='ast_act' value='setfilter'>
                      <input type='hidden' name='flt_name' value='{$fname}' />";
       $sbody .= "<td class='nowrap'>$formcode"
        . " <table><tr><div class='d-flex justify-content-end'><div>$searchcode</div>&nbsp;"
        . " <input type='submit' {$btnclass} name='ast_submit_flt' value='{$ast_tips['setfilter']}'>";

       if(true) { # ($showreset) add 'RESET' mini-form (one button !)
         if($fltfunc) {
           $fld_id = "@$fltfunc";
           $disabl = call_user_func($fltfunc,'isfilterset') ? '' : 'disabled="disabled"';
         }
         elseif(is_object($fld)) {
            $fld_id = $fld->id;
            $disabl = isset($_SESSION[$tid][$fld->id]) ? '':'disabled="disabled"';
         }
         $sbody .="&nbsp;&nbsp;<input type='submit' name='clearfilter' $btnclass value=\"{$ast_tips['tipresetfilter']}\" $disabl />";
       }
      //  else $sbody .='<td>&nbsp;</td>'; # fill table row for looking good
       $sbody .="</div></tr></table></form></td>\n";

       if($rowstarted && ($icol >= $this->searchcols))
       {
           $rowstarted = false;
           $sbody .= "</tr>\n";
           $icol = 0;
       }

     } #<2>
     if($rowstarted) $sbody .= '</tr>'; # vaild table row ending !
     if(!empty($ast_hidesearchbar)) {
       $envelope[0]="<table border=0 cellspacing=0 cellpadding=0><td id='' valign='top'><a href='javascript:#' onclick='alert(\"Show/Hide\")'>[+]</a></td>
       <td id='astedit_searchbar'>";
       $envelope[1]="</td></tr></table>";
     }
     $sbody = $envelope[0].$sbody."</table>\n<!-- search bar end -->\n".$envelope[1];
     if($this->_togglefilters) {
       $state = !empty($_COOKIE['ast_hidesearch']);
       $srdisp = ($state)? 'show':'';
       $tgltitle= isset($ast_tips['title_showsearchbar']) ? $ast_tips['title_showsearchbar']:'Show/hide search';
       if (defined('USE_JQUERY_UI')) {
           $curClass = ($state) ? 'ui-icon-triangle-1-s': 'ui-icon-triangle-1-n';
           $htmlToggle = "<span id='img_srchhide' class='ui-state-default ui-icon $curClass pnt' onclick=\"ToggleSearchBar()\" title='$tgltitle'></span>";
       }
       else {
           $btimg = ($state)? 'plus.gif':'minus.gif';
           $htmlToggle = "<a href=\"#\" onclick=\"return ToggleSearchBar()\"><img id=\"img_srchhide\" src=\"".IMGPATH.$btimg."\" border=0 width=12 height=12 title=\"$tgltitle\"/></a>";
       }


     $sbody = "<div class='accordion'>
     <div class='accordion-item'>
        <h2 class='accordion-header' id='panelsStayOpen-headingOne' onclick=\"ToggleSearchBar()\" title='$tgltitle'>
          <button class='accordion-button' type='button' data-bs-toggle='collapse' data-bs-target='#tr_astsearchbar' aria-expanded='$state' aria-controls='tr_astsearchbar'>
          <i class='bi bi-search'></i>&nbsp;Искать
          </button>
        </h2>
        <div id='tr_astsearchbar' class='accordion-collapse collapse bg-light $srdisp' aria-labelledby='panelsStayOpen-headingOne'>
          $sbody
        </div>
      </div>
      </div>";



     }
     echo $sbody;

  } # DrawSearchBar

  function GetFieldNo($nName) { # GetFieldNo
    if(isset($this->fields[$nName])) return $nName;
    return -1;
  } # GetFieldNo
  function FullPkFieldAsString($rowdata=false) {
    global $ast_datarow;
    if(!is_array($rowdata) && !empty($ast_datarow)) $rowdata=$ast_datarow;
    $arr = array();
    foreach($this->_pkfields as $onekey) { $arr[]=isset($rowdata[$onekey])? $rowdata[$onekey]:''; }
    $ret = implode(AST_PKDELIMITER,$arr);
    return $ret;
  }
  # for "001|MMM|..." builds WHERE expression: pkfld1='001' AND pkfld2='MMM' ...
  public function pkEqExpression($values) {
    $valar = is_array($values)? $values : explode(AST_PKDELIMITER,$values);
    $ret = [];
    foreach($this->_pkfields as $kk=>$fldName) {
      if(!isset($valar[$kk])) break;
      $ret[] = "$fldName = '" . $valar[$kk] . "'"; # ($ret==''? '':' AND ').$this->_pkfields[$kk]."='{$valar[$kk]}'";
    }
    return implode(' AND ', $ret);
  }

  function SetBrowseTags($fldid, $tags='') { $this->_browsetags[$fldid]=$tags; }

  # $ast_datarow contains assoc. array with field values
  function BrowseRow($no, $ajax=0){

    global $ast_datarow, $ast_tips,$as_cssclass,$astbtn;
    $rowcls = '';
    if(!empty($this->rowclassfunc) && is_callable($this->rowclassfunc)) {
      $rowcls = call_user_func($this->rowclassfunc,$ast_datarow, $no);
    }
#    if(!is_string($cls)) $cls=($no % 2)? $as_cssclass['troweven']:$as_cssclass['trowodd'];
    $pkvalue = (count($this->_pkfields))? $this->FullPkFieldAsString() : 'xx';
    $pkvalid = str_replace(AST_PKDELIMITER,'_',$pkvalue);
    $clsattr = ($rowcls) ? "class='$rowcls'" : '';
    $ret = ($ajax==0)? "<tr id='ast_brow{$pkvalid}' $clsattr>":''; # class='$cls' {$moe}
    if(!empty($this->groupby) && !empty($this->sumfields)){
      // while(list($skey, $sval) = each($ast_datarow)) # for($k=0; $k<count($ast_datarow); $k++) each() -> Relying on this function is highly discouraged in php8
      // $ret .= "  <td align=right>$sval</td>\n";
      foreach($ast_datarow as $sval){
        $ret .= "  <td align='right'>$sval</td>\n";
      }
    }
    else
    foreach($this->viewfields as $fno=>$fid) {
      $tdid = $fid.$pkvalid;
      $tdtags = isset($this->_browsetags[$fid]) ? $this->_browsetags[$fid] : '';

      $fdef = isset($this->fields[$fid]) ? $this->fields[$fid] : 0;

      if (!empty($fdef->getter) && is_callable($fdef->getter)) {
          $ast_datarow[$fid] = call_user_func($fdef->getter, $ast_datarow);
      }
      $val = isset($ast_datarow[$fid]) ? $ast_datarow[$fid] : '';

      if(empty($tdtags) && is_object($fdef) /*&& substr($this->fields[$fid]->edittype,0,6)!=='SELECT'*/) {
          $tdstyle = '';
          $cls = array();
          if (!empty($fdef->showattr[0])) $tdstyle .="text-align:" . $fdef->showattr[0] . ';text-wrap:nowrap;';
          if (!empty($fdef->showattr[1])) { if ($at=astedit::evalValue($fdef->showattr[1], $val)) $tdstyle .="color:$at;"; }
          if (!empty($fdef->showattr[2])) { if ($at=astedit::evalValue($fdef->showattr[2], $val)) $tdstyle .="background-color:$at;"; }
          if (!empty($fdef->showattr[3])) { if ($at=astedit::evalValue($fdef->showattr[3], $val)) $cls[] = $at; }
          if (!$tdstyle) {
              if ( $this->fields[$fid]->type === 'DECIMAL' ) $cls[] = 'ast-td-dec';
              elseif ( in_array($this->fields[$fid]->type, array('INT','TINYINT','BIGINT','INTEGER'))) {
                  if ( substr($this->fields[$fid]->edittype,0,5)==='CHECK') {
                      $cls[] = 'ast-td-checkbox';
                  }
                  else{
                      $spltedit = explode(',',$this->fields[$fid]->edittype);
                      if (empty($spltedit[1]) && empty($this->fields[$fid]->showformula)) {
                          # if (!$cls) $cls = 'ast-td-int'; # int не транслируется в строки!
                          $cls[] = 'ast-td-int'; # int не транслируется в строки!
                      }
                  }
              }
              elseif ( in_array($this->fields[$fid]->type, array('DATE','DATETIME','TIME'))) {
                  $cls[] = 'ast-td-date';
              }
          }
          if (count($cls)) $tdtags .= " class='" . implode(' ', $cls) . "'";
          if ($tdstyle) $tdtags .= "style='$tdstyle'";
      }

      if(substr($fid,0,1)=='@') { # user def.field, rendered by func.
        $val=@call_user_func(substr($fid,1),$ast_datarow);
        $ret .="<td id=\"$tdid\" $tdtags>$val</td>";
        continue;
      }

      if(!is_object($fdef)) continue;

      if(!empty($fdef->showcond)) { #<4>
          $val = $this->GetViewValue($fid);
          $tdtags .= ($this->_drawtdid)? " id=\"{$tdid}\"":'';
          $ret .= "<td {$tdtags}>$val</td>\n";
        } #<4>
    }

     if(count($this->_pkfields)>0 && empty($this->groupby)){ # <3-pkfield>
       $id = $pkvalue;

       if(count($this->customcol)>0) {
        foreach($this->customcol as $custcol) {
          $turl = $custcol['htmlcode'];
          $addon = empty($custcol['addon'])?'':$custcol['addon'];
          if(substr($turl,0,1)=='@') $turl = astedit::evalValue($turl, $ast_datarow);
          else $turl = str_replace('{ID}',$id, $turl);
          $ret .="<td id=\"$tdid\" nowrap $addon>$turl</td>\n"; # empty placeholder for "link"
        }
       }

       if(!empty($this->canedit)) {
           $canedit_rec = 1;
           if(is_string($this->canedit)) {
               if (is_callable($this->canedit)) $canedit_rec = call_user_func($this->canedit,$ast_datarow);
           }
           elseif (is_array($this->canedit)) { # get biggest eval from all array items
              $canedit_rec = 0;
              foreach($this->canedit as $item) {
                  $caned = (is_callable($item)) ? call_user_func($item,$ast_datarow) : astedit::evalValue($item);
                  $canedit_rec = max($canedit_rec, $caned);
              }
           }

           if(($canedit_rec) && empty($this->_viewmode)) {# add href/form for editing row
             $ret .="<td class='text-center'>" . (defined('USE_JQUERY_UI')? "<span role='button' onclick=\"AstDoAction('edit','$id')\" title=\"{$ast_tips['tipedit']}\"><i class='bi bi-pencil-square font12'></i></span>" :
               "<img border=0 src=\"{$astbtn['edit']}\" onclick=\"AstDoAction('edit','$id')\" {$this->imgatt} />")
               .'</td>';
           }
           else {
             $ret .='<td>&nbsp;</td>';
           }
       }

       if(!empty($this->candelete) && empty($this->_viewmode)) { # add href for delete
         # $this->confirmdel = (substr($vl,0,1)=='@') ? astedit::evalValue($vl) : $vl;
         if (is_scalar($this->candelete))
            $delthis = is_callable($this->candelete)? call_user_func($this->candelete,$ast_datarow) : astedit::evalValue($this->candelete);
         elseif (is_array($this->candelete)) {
             $delthis = 0;
             foreach($this->candelete as $item) {
                 $onedel = is_callable($item)? call_user_func($item,$ast_datarow) : astedit::evalValue($item);
                 $delthis = max($delthis, $onedel);
             }
         }
         # $delthis = astedit::evalValue($this->candelete); # can be deleted THIS record ?
         if($delthis) {
           $onclick = "AstDoAction('delete','$id')";
           $ret .= "<td>" . (defined('USE_JQUERY_UI') ?
              "<span role='button' onclick=\"$onclick\" title=\"{$ast_tips['tipdelete']}\"><i class='bi bi-trash text-danger font12'></i></span>" :
            "<input name=\"submit\" type=\"image\" border=0  src=\"{$astbtn['del']}\" onclick=\"$onclick\"
            title=\"{$ast_tips['tipdelete']}\" {$this->imgatt} />") . '</td>';
         }
         else $ret .='<td>&nbsp;</td>';
       }
       if($this->_multiselect) { # checkboxes in the header and every grid row, for multi-selecting
           $ret .="<td class='ct'><input type='checkbox' id='chk_{$this->tbrowseid}' value='{$id}' onclick='selRow{$this->tbrowseid}(this)' /></td>";
       }

     } # <3-pkfield>

     if($this->_viewmode) {
       $vtxt = isset($ast_tips['link_viewrecord'])?$ast_tips['link_viewrecord']:'View this record';
       $ret .="<td><a href=\"javascript://\" onclick=\"astViewRecord('$id')\">$vtxt</td>";
     }
     $ret .= "</tr>\n";

     if(!empty($this->recursive) && count($this->_pkfields)>0) { #<5>
       $filterrec = "{$this->recursive}='".$ast_datarow[$this->_pkfields[0]]."'"; # <TODO> !!!
       $qryrec = "SELECT * FROM {$this->id} WHERE $filterrec".($this->browseorder ? " ORDER BY {$this->browseorder}":'');
       $lnkrec = Astedit::$db->sql_query($qryrec);
       if(($lnkrec) && Astedit::$db->affected_rows()>0) {
         $this->_recursive_level++;
         $subno = $no+1;
         while(($ast_datarow=Astedit::$db->fetch_assoc($lnkrec))) {
           $ret.=$this->BrowseRow($subno++);
         }
         Astedit::$db->free_result($lnkrec);
         $this->_recursive_level--;
       }
     }#<5>

     return $ret;
  } # BrowseRow() end

    /**
    * returns "viewable" string value of a field to insert into grid
    *
    */
  function GetViewValue($fldid, $rowdata=false, $forajax=false) {
      global $ast_datarow;
      if(!is_array($rowdata)) $rowdata=$ast_datarow;
      if(substr($fldid,0,1)=='@') return @call_user_func(substr($fldid,1),$rowdata);
      $pkvalue = $this->FullPkFieldAsString();
      $fld = isset($this->fields[$fldid])? $this->fields[$fldid] : false;
      $efmt = explode('^',$fld->edittype);
      if(count($efmt)<2) $efmt = explode(',' , $fld->edittype);
      if(true) { #<4> !empty($fld->showcond)
          $val = (isset($rowdata[$fldid]) ? $rowdata[$fldid] : ''); #$fld->id.' - NO FIELD !');
          $shfunc = $fld->showformula;
          if(!empty($shfunc)) {
             $val = astedit::evalValue($shfunc, $val);
             if(is_array($val)) $val = 'N/A:'.$val;
          }
          elseif($efmt[0] === 'CHECKBOX') {
             $val = astedit::BrShowBool($val);
          }
          elseif($efmt[0]==='SELECT') { # auto-decode field value that is selected from known List
              if(!empty($efmt[1])) {
                  $lar = GetArrayFromString($efmt[1]);
                  if(is_array($lar) and count($lar)>0) foreach($lar as $ino=>$item) {
                      if(is_array($item)) {
                          if(isset($item[1]) and $item[0]==$val) return $item[1];
                      }
                      elseif($ino == $val) return $item;
                  }
              }
          }
          else { # for empty show formula - browse format depends on field type
             if($fld->type === 'DATE' || $fld->type === 'DATETIME')
               $val = intval($val) ? to_char($val,($fld->type==='DATETIME')) : ''; # 'YYYY-MM-DD' -> 'DD.MM.YYYY'
             else
             { #<5>
               if(IsTextType($fld->type))
               {
                 if($fld->type=='TEXT' && strlen($val)>70 && ($this->bwtextfields))
                   $val = strlen($val)>40 ? (substr($val,37).'...') : $val;
                   # else  $val = stripslashes($val); # \n -> <br>, ...
               }
             } #<5>
          }
          if($forajax) $val = EncodeResponseData($val);
          if(!empty($fld->showhref) && !empty($pkvalue))
          { # field will has a href to some URL
            # $id = $rowdata[$this->_pkfield];
            $haddon = str_replace('{ID}', $pkvalue, $fld->hrefaddon);
            $val = '<a href="'.str_replace('{ID}', $pkvalue,$fld->showhref).'" '.$haddon.">$val</a>";
          }
          if($this->_recursive_level>0 && $fld->id===$this->recursive_show) {
            $val = str_repeat('&nbsp; &nbsp; ',$this->_recursive_level).$val;
          }
        } #<4>
        return $val;
  }
  public function setBrowseFilter($expr=null) {
      $this->browsefilter = array();
      if ($expr!=='' && $expr!== null) $this->browsefilter[] = $expr;
  }
  public function addBrowseFilter($expr) {
      if ($expr) $this->browsefilter[] = $expr;
  }
  function setBrowseFilterFn($par) {
      $this->browsefilter_fn = $par;
  }
  public function setFieldOptions($fieldid, $opts=array()) {
      if(!isset($this->fields[$fieldid])) return false;
      if(isset($opts['defvalue'])) $this->fields[$fieldid]->defvalue = $opts['defvalue'];
      if(isset($opts['showcond'])) $this->fields[$fieldid]->showcond = $opts['showcond'];
      if(isset($opts['showformula'])) $this->fields[$fieldid]->showformula = $opts['showformula'];
      if(isset($opts['editcond'])) $this->fields[$fieldid]->editcond = $opts['editcond'];
      if(isset($opts['edittype'])) $this->fields[$fieldid]->edittype = $opts['edittype'];
      if(isset($opts['idx_name'])) $this->fields[$fieldid]->idx_name = $opts['idx_name'];
  }
  # PrepareFilter - builds full filter based on all sub-filters and search conds.
  function PrepareFilter() {
     global $fulltextindex_support; # set it to non-empty if your tables support FT-search
     $filt = array();

     if(!empty($this->browsefilter_fn) && is_callable($this->browsefilter_fn)) {
         $filt1 = call_user_func($this->browsefilter_fn);
         if($filt1) $filt[] .= "($filt1)";
     }

     if(is_array($this->browsefilter) && count($this->browsefilter)>0) {
         foreach($this->browsefilter as $flt) {
             if (!empty($flt) && ($realflt = astedit::evalValue($flt))) $filt[] = "($realflt)";
         }
     }
     elseif (is_string($this->browsefilter) && $this->browsefilter!=='') $filt[] = "($this->browsefilter)";

     if(!empty($this->userfilter)) $filt[] = '('.$this->userfilter . ')';

     # WriteDebugInfo('final filter:', $filt, 'browsefilter:', $this->browsefilter);
     # add to filter all $_SESSION['flt_tableName'][...] values, come from search forms
     $fltid = 'flt_'.$this->tbrowseid;
     if(!empty($this->filterPrefix)) $fltid = $this->filterPrefix . '-' . $fltid;

     $sflds = explode(',',$this->search);
     foreach($sflds as $fldid) { #<3> - for
       $f_arr = explode('^',$fldid);
       $fldid = $f_arr[0]; # separate field id from possible ":List_Maker_Expression"
       $fltexpr = '';
       if($fldid==='') continue;
       if($fldid[0]==='@') { #<4>
         $sfunc = substr($fldid,1);
         $fltexpr = (is_callable($sfunc))? call_user_func($sfunc,'compute') : '';
       } #<4>
       if($fltexpr) $filt[] = "($fltexpr)";
     } #<3> - for

     # now scan all defined filters in $_SESSION (may be added "manually")
     foreach($this->fields as $fldid=>$fld) { #<3>

       $fltexpr = '';
       $fname = $fldid;
       if (!isset($_SESSION[$fltid][$fldid])) continue;

       $fval = $_SESSION[$fltid][$fldid];

       if(!empty($this->_converters[$fname]) && is_callable($this->_converters[$fname]))
         $fltexpr = call_user_func($this->_converters[$fname],$fval);
       else {
         $b_exact = true;
         if( is_array($fval) && count($fval)>1 ) { # range search for field
            $val1 = (substr($fld->type,0,4)==='DATE') ? dateRepair($fval[0]) : $fval[0];
            $val2 = (substr($fld->type,0,4)==='DATE') ? dateRepair($fval[1]) : $fval[1];
            $fexpr = array();
            if (!empty($val1)) $fexpr[] = "($fname >= '$val1')";
            if (!empty($val2)) $fexpr[] = "($fname <= '$val2')";
            $fltexpr = '(' . implode(' AND ', $fexpr) . ')';
         } # range case end
         else {

             if(substr($fld->edittype,0,4)==='TEXT' || $fld->type=='TEXT' || $fld->type=='CHAR' ||
               $fld->type=='VARCHAR') $b_exact = false;
             if(substr($fld->type,0,4)==='DATE') { # convert from entered dd.mm.yyyy to DB format 'Y-M-D'
               if(intval($fval)==0) $fval ='0';
               else $fval = dateRepair($fval);
             }

             $splt = explode('^',$fld->edittype);
             if(count($splt)<2) $splt = explode(',',$fld->edittype);
             $edtype = strtoupper($splt[0]);
             if ($edtype=='SELECT') $b_exact=true; # varchar but select from list - exact search
             if ($edtype === 'BLIST')
                 $fltexpr = "FIND_IN_SET('$fval',$fname)";
             elseif($b_exact)
                 $fltexpr= "$fname='$fval'";
             else { # search on substring, " like "val%"
                 if(empty($fulltextindex_support))
                   $fltexpr = "$fname LIKE '$fval%'"; # standard like ... options
                 else
                   $fltexpr = "MATCH($fname) AGAINST('$fval')"; # MySQL full-text searching !!!
                 # table must be MyISAM, and fulltext index must be created otherwise error will raise:
                 # {Can't find FULLTEXT index matching the column list}
             }
         }
       }

       if($fltexpr) $filt[] = "($fltexpr)";
     } #<3>
     return implode(' AND ',$filt);
  } # PrepareFilter

  /**
  * @desc sets passed associative array values equal default field values
  */
  function GetDefaultValues(&$param) {
    if(!is_array($param)) $param = array();
    foreach($this->fields as $fld) {
      if(!$fld->_autoinc) $param[$fld->id] = str_replace("'",'',$fld->defvalue);
    }
  }
  function GetRowContents($pkid) { # return for per-row refresh through AJAX
    global $ast_datarow, $ast_tips,$ast_parm;
    if(!empty($this->groupby) && !empty($this->sumfields))
    { # compose a SPECIAL sql
        $sqlqry = "SELECT $this->groupby,$this->sumfields FROM $this->id";
    }
    else $sqlqry = "SELECT * FROM {$this->id}";
    $sqlqry .= " WHERE ".$this->pkEqExpression($pkid); #{$this->_pkfield}='{$pkid}'";
    $lnk = Astedit::$db->sql_query($sqlqry);
    if(($lnk) && ($ast_datarow = Astedit::$db->fetch_assoc($lnk))) {
      $ret=$this->BrowseRow(0,1);
      Astedit::$db->free_result($lnk);
    }
    else $ret = "<td colspan=10>error in mysql: ".Astedit::$db->sql_error()."</td>";
    return $ret;
  }
  function ClearCurPageNo() {
      if(isset($_SESSION[$this->filterPrefix.$this->tbrowseid]['page']))
        unset($_SESSION[$this->filterPrefix.$this->tbrowseid]['page']);
  }
  public function prepareOrder() {
     global $cursortfld, $cursortord;
     if(empty($cursortfld)) {
        $cursortfld = $_SESSION[$this->filterPrefix.'ast_sortfld_'.$this->id] ?? '';
        $cursortord = $_SESSION[$this->filterPrefix.'ast_sortord_'.$this->id] ?? '0';
     }
     if(!empty($cursortfld)) {
        $ord = empty($cursortord)?' DESC':'';
        return ($cursortfld . $ord);
     }

     return $this->browseorder;
  }

  function DrawBrowsePage($ajax=0) {
     global $ast_datarow, $ast_tips, $cursortfld, $cursortord,$as_cssclass,
     $ast_browse_jsdrawn, $astbtn, $ast_parm;
     if (astedit::$time_monitoring) WriteDebugInfo($this->id . ':render grid start, time: '.microtime());

     if($ajax) ob_start();
     if(isset($ast_parm['ast_act']) && 'setfilter'==$ast_parm['ast_act'] && isset($_SESSION[$this->filterPrefix.$this->tbrowseid]['page']))
     {
        $this->ClearCurPageNo();
     }
     $npage = $_SESSION[$this->filterPrefix.$this->tbrowseid]['page'] ?? 0;
     if($npage < 0)
     {
       $page = 0;
       $_SESSION[$this->filterPrefix.$this->tbrowseid]['page'] = $npage;
     }
     $delconf = astedit::evalValue($this->confirmdel);
    //  $wdth = ($this->browsewidth) ? " style=\"width:{$this->browsewidth} !important\"" : '';

     $sqlqry = "SELECT * FROM $this->id";
     if(!empty($this->groupby) && !empty($this->sumfields))
     { # compose a SPECIAL sql
        $sqlqry = "SELECT $this->groupby,$this->sumfields FROM $this->id";
     }

     # add filter - WHERE ...
     $filt = $this->PrepareFilter();
     # all conditions placed into filter !
     if ($filt) $sqlqry .= " WHERE $filt";
     if(!empty($this->groupby) && !empty($this->sumfields))
       $sqlqry .= ' GROUP BY '.$this->groupby;


     $browseorder = $this->prepareOrder();
     # add ORDER BY
     if(!empty($browseorder))
       $sqlqry .= ' ORDER BY '.$browseorder;

     # add page output - LIMIT nPage, PageSize
     if($this->rpp && empty($this->groupby)){
       $strt = $npage * $this->rpp;
       $sqlqry .= " LIMIT $this->rpp" . ($strt>0 ? " OFFSET $strt" : '');
     }
     if($this->debug || !empty($_SESSION['debug']))#  || (!empty($_SESSION['userpin']) && $_SESSION['userpin']=='supervisor'))
     {

       $fltid = 'flt_'.$this->tbrowseid;
       if($this->filterPrefix) $fltid = $this->filterPrefix.'-'.$fltid;
       if(!empty($_SESSION[$fltid]))
         foreach($_SESSION[$fltid] as $flkey=>$flval) echo "filter[$flkey] = ($flval)<br>";
       echo "<div id='qry'>[debug] query: $sqlqry</div>\n";
     }
     $r_res = Astedit::$db->sql_query($sqlqry); # todo: limit ...
     # writeDebugInfo("browse qry: ", $sqlqry);
     if(empty($r_res))
     {
         $sqlErr = Astedit::$db->sql_error();
         $sqlErrno = Astedit::$db->sql_errno();
         if($sqlErrno) {
             $errText = Astedit::decodeSqlError($sqlErrno, $sqlErr, $this->id);
             echo "<div class='msg_error' style='padding:1em 2em;margin:1em 2em'>$errText</div>";
             $this->errorState = $sqlErrno;
             /*
             echo "Error [$sqlErrno] while executing query - <i>$sqlqry</i>:<br>". Astedit::$db->sql_error()
              . "<br>Please call programmer to check template file !<br>\n";
             */
             return;
         }
     }
     $scrolltag = ($this->browseheight=='')? '' : "style='overflow:auto; height:{$this->browseheight}'";
     if(empty($ajax)) {
       echo "<div id='astbrowse_{$this->tbrowseid}' align='{$this->_halign}' class='table-responsive' $scrolltag>";
     }
     if(empty($this->brenderfunc))
       echo "<table id='astpage_{$this->tbrowseid}' class='table table-bordered table-hover table-striped'>\n".(($this->_drawbrheader)? $this->BrowseHeader() : '');

     $nrow = 0;
     if($r_res) {
       while(($ast_datarow = Astedit::$db->fetch_assoc($r_res)))
       {
           if(connection_aborted()) { if(!empty($_SESSION['debug'])) WriteDebugInfo('USER CONNECTION ABORTED!'); exit; }
           if(!empty($this->brenderfunc) && is_callable($this->brenderfunc)) {
            call_user_func($this->brenderfunc, $ast_datarow); # call_user_func_array
           }
           else
            echo $this->BrowseRow(($nrow++));
       }
     }
     $strt_time = microtime();
     if (astedit::$time_monitoring) {
         $elapsed = microtime() - $this->strt_time;
         WriteDebugInfo($this->id . ':render grid end, time:'. microtime() . " elapsed: ".$elapsed);
     }

     # draw mini-form for fast adding records
     if($this->safrm && empty($this->groupby) && !empty($this->caninsert)) { #<3>-safrm
        $formname = $this->id;
        $mform = "<tr><form name=\"$formname\" id=\"$formname\" action=\"{$this->baseuri}\" method=POST>\n<input type=hidden name=ast_act value='doadd'>\n";
        $fcnt = 0;

        foreach($this->fields as $fldid=>$fld)
        { #<4>
          $shfunc = $fld->showcond;
          $fldname = $fldid;
          if(!empty($shfunc))
          {
             $shfunc = astedit::evalValue($shfunc, 1);
          }

          if(!empty($shfunc) || ($fld->editcond==='H') ||
          ($fld->edittype=='HIDDEN')) {
             $fcnt++;
             $frmel = $this->DrawFormElement($fldid, 'add',0,0);
             if(($fld->editcond==='H') || ($fld->edittype=='HIDDEN')) {
               if($frmel === '(New)') $mform .= "<td>{$ast_tips['title_newrec']}</td>";
               else   $mform .= $frmel; # hidden fields without table <td> !
             }
             else
               $mform .= "<td>$frmel</td>";
          }
        } #<4> for
        if($fcnt>0) { # at least one field in mini-form exist, so draw a mini-form !
          $mform .='<td>' .
          (defined('USE_JQUERY_UI') ? "<input type='submit' class='ui-state-default ui-icon ui-icon-plus pnt' title=\"{$ast_tips['tipadd']}\" />" :
          "<input type='image' name='ast_submit_add' BORDER=0  SRC=\"{$astbtn['add']}\" {$this->imgatt} title=\"{$ast_tips['tipadd']}\">")
            ."</td>\n";
          echo $mform."</tr></form>";
        }
     } #<3>-safrm

     if($this->caninsert && empty($this->brenderfunc) && empty($this->groupby))  {
        if(empty($this->safrm)) {
            # "add" is a button on bottom toolbar
            $this->toolBar[] = '<span class="ms-auto"><input type="button" class="btn btn-primary" onclick="AstDoAction(\'add\',0)" value="' . $ast_tips['tipadd']. '" /></span>';
        }
        else {
            $col = $this->_addcol-1;
            echo "<tr><td colspan='$col'>&nbsp;
             </td><td>" .
             (defined('USE_JQUERY_UI') ? "<span class='ui-state-default ui-icon ui-icon-plus pnt' onclick=\"AstDoAction('add','0')\" title=\"$ast_tips[tipadd]\" />" :
             "<input name=\"submit\" type=\"image\" src=\"$astbtn[add]\" onclick=\"AstDoAction('add','0')\"
                title=\"{$ast_tips['tipadd']}\" {$this->imgatt} />")
                ."</td></tr>\n";
        }
     }

     if(empty($this->brenderfunc)) echo "</TABLE></div>";

     # draw bottom toolbar if any html code exists
     if(count($this->toolBar)) {
      echo "<div class='area-buttons bounded'>" . implode(' ',$this->toolBar) . '</div>';
      # echo "<tr><td colspan=\"{$this->_addcol}\" ><div class='area-buttons'>" . implode(' ',$this->toolBar) . '</div></td></tr>';
      }



     if(empty($ajax)) {
      //  echo "</div>";
       if( empty($this->groupby)) $this->DrawPageLinks();
     }
     else {
       $ret = ob_get_contents();
       ob_end_clean();
       return $ret;
     }
  } #DrawBrowsePage() end

  function SetPageLimit($nlimit) { $this->rpp = $nlimit; }

  # $forsearch can be "from", "to" - in "range" search for field, drawing two input fields with flt_value_from and flt_value_to names
  function drawInput($fldid, $forsearch=false,$act='add', $row=0) {  # drawInput($fname,'from','',$values)

     global $ast_datarow,$as_cssclass,$ast_frmfunctions, $ast_wysiwyg_type;
     if (is_string($fldid)) {
         $ar = $this->fields[$fldid];
     }
     else $ar = $fldid;
     # writeDebugInfo("filed ar: ", $ar);
     $id = $ar->id;
     $econd = astedit::evalValue($ar->editcond);

     # если поле заводится только при создании, или { одно из PRIMARY-KEYS и операция-редактирование }
     if($econd==='C' || (in_array($id,$this->_pkfields) && $act==='edit')) {
       return '';
     }
     $ftype = $ar->type;
     $splt = explode('^', $ar->edittype);
     if(count($splt)<2) $splt = explode(',', $ar->edittype);
     $etype = empty($splt[0])? '' : strtoupper($splt[0]);
     if(in_array($etype,array('SELECT','BLIST','BLISTEXT','BLISTLINK'))) {
       $elist = $splt[1] ?? ''; # values list for SELECT
       $intags = $splt[2] ?? ''; # add.tags for <INPUT ...> - onClick()
       $lnkTitle = $splt[3] ?? '';
     }
     else {
       $intags = empty($splt[1])? '' : $splt[1];
     }

     $elen  = $ar->length;
     $disab = '';
     $def = $ar->GetDefaultValue();  # =isset($defar[0]) ? $defar[0] : '';

     $fltid = 'flt_'.$this->id;
     if(!empty($this->filterPrefix)) $fltid = $this->filterPrefix . '-'. $fltid;
     $newval = $def; # isset($defar[1]) ? $defar[1] : $defar[0]; # formula or spec.word for "new" value
     #if(substr($newval,0,1) == '@' || substr($newval,0,1) == '#')
     #  $newval = astedit::evalValue($newval); else
     if(substr($def,0,1) === '+')
     { # авто-инкремент (+10 - на 10 от текущего максимума
        $incqry = "select max($id) $newval from $this->id";
        # if some filters active, keep them in mind
        for($kkk=0; $kkk<count($this->fields); $kkk++)
        { # if some filters active, use them in select query
           if(isset($_SESSION[$fltid][$id]) && $etype==='SELECT')
               $incqry .= " AND $id='".$_SESSION[$fltid][$id]."'";
        }
        $lnk = Astedit::$db->sql_query($incqry);
        if($lnk && ($rw=Astedit::$db->fetch_row($lnk)))  $newval = $rw[0];
     }
     else # Now - for ALL fields, if it's filtered, use filter value as default in additing, was: elseif($def== '=' || $def == '')
     { # беру по тек.критерию отбора по данному полю
        $curfilt = isset($_SESSION[$fltid][$id]) ? $_SESSION[$fltid][$id] : '*?*';
        if($curfilt!=='*?*') { $disab='readonly'; $newval = $curfilt; }
        else if(empty($newval) && !empty($def)) $newval = $def;
     }
     if(!is_array($row) && isset($newval)) { # new record, make date field if 'now' used as 'new value'
        if($newval=='now') $newval = date('d.m.Y H:i:s');
        elseif($newval == 'today') $newval = date('d.m.Y');
     }
     $inputattrib = '';
     if( $ar->_autoinc) {
       #  $ret = empty($fullform) ? '(New)':'';
       return '';
     }
     $val = $ar->id;
     $edstyle = ''; # I can 'hide' edit field in some cases, so they can be 're-enabled' from js

     if(empty($econd) && empty($forsearch)) return '';# non-editable fields skipped !

     if(($econd === 'E' && ($act === 'add'))
      || ($econd === 'A' && ($act !== 'add') # A=только при вводе новой
      || ($econd) === 'H')) {
       $edstyle = 'style="display:none;"'; # initially hidden row
     }
     elseif($econd==='R' && !$forsearch) {# ReadOnly mode
       $inputattrib .= ' readonly="readonly"';
     }
     else {
       # $shfunc = substr($econd,1);
       $val = astedit::evalValue($econd, $row);
     }
     if(empty($val) && empty($forsearch)) return '';

     if($ar->subtype === 'LOGIN' && !$forsearch) {
         $inputattrib .= " onchange=\"astCheckUniqueness('$this->id',this)\"";
     }
     #     echo "<!-- act: $act, field: {$ar->id}, type: $ftype -->\n";
     if ($forsearch === 'from') {
         $def = isset($row[$id][0]) ? $row[$id][0] : '';
     }
     elseif ($forsearch === 'to') {
         $def = isset($row[$id][1]) ? $row[$id][1] : '';
     }
     elseif($act =='edit' && is_array($row)) {
       if ($ar->external) { # try to get external field value
         if (is_callable($ar->getter)) {
              $def = call_user_func($ar->getter, $row);
         }
       }
       elseif(isset($row[$ar->id])) {

         $def = $row[$ar->id];
         if(IsTextType($ftype)) $this->MakeEditable($def);
       }
     }
     else {
        if($newval=='now') $newval = date('d.m.Y H:i:s');
        elseif($newval == 'today') $newval = date('d.m.Y');
        $def = $newval; # calculated value
        # if($ftype == 'DATE') $def = intval($def)? to_char($def) : '';
        # elseif($ftype == 'DATETIME') $def = intval($def)? to_char($def,1) : '';
        if($def === "''") $def = '';
     }
     if (is_array($def)) {
         # writeDebugInfo("$this->id/" . print_r($fldid,1)." default value is array : " . print_r($def,1));
         # can be array if RANGE filter set or 2 default values set
         $def = array_pop($def);
     }
     if(substr($def,0,1)=="'" ) { # cut apostrofs 'value' -> value
        $def = substr($def,1);
        if(substr($def,-1) == "'") $def = substr($def,0,-1);
     }
     $etype = strtoupper($etype);
     $addon = '';
     if($etype !== 'BLISTLINK') {
         if($intags==='' && count($this->events)) {
           if(isset($this->events[$ftype]) && is_array($this->events[$ftype]))
             $t_ar = $this->events[$ftype];
             if(!empty($t_ar)) foreach($t_ar as $ev_type=>$ev_func) {
               $intags .= " $ev_type='$ev_func'";
             }
         }
         $addon = $intags; # additional html code
     }
     $elen = empty($elen) ? 40 : $elen;
     $edlen = min(72, $elen);
     if ($forsearch) {
         $editid = 'flt_value';
         if($forsearch === 'from' OR $forsearch === 'to') $editid .= '_'.$forsearch;
     }
     else $editid = ($this->_prefixeditfield ? $this->id.'_': '') . $ar->id;
     $relem = " class='even' id='row_$editid'"; # <tr id=row_fieldname...>
     $addon .= (empty($addon)?'':' ').$inputattrib; # READONLY or whatelse if needed
     $fldclasses = array();
     # $prompt = '<td align=right nowrap>'.$ar->desc.'</td>'; # <td>title</td> if needed
     if( ($econd === 'H') ) {
       $etype = 'HIDDEN'; # make a hidden field in mini-form !
     }
     $txtclass = '';

     if(stripos($addon, 'class=')===false) {
        $txtclass = empty($as_cssclass['textfield'])?'':"class='{$as_cssclass['textfield']}'";
     }
     # echo "field $id: ($etype) value: [$def] (from $newval)<br>";
     # if($etype=='BLIST' && empty($fullform)) $etype=''; # CHECKBOX-list only in FULL screen form!

     if($this->charset) {
         # WriteDebugInfo($this->id,"/$editid: charset is ",$this->charset);
         $def = self::dropQuotes($def); # htmlspecialchars(ENT_QUOTES,$this->charset); # get rid of quote chars, htmlentities()?
     }

     switch($etype)
     { # $ar[1] - field_id, [2] - descr, [3] - addit.params
     case 'HIDDEN':
           $ret = "<input type='hidden' id='$editid' name='$editid' value='$def' />\n";
           break;
     case 'CHECKBOX':
           # $onClick = (empty($astedit_jsHandler[$this->id][$id]) ? '':"onClick='".$astedit_jsHandler[$this->id][$id]."'");
           $checked = empty($def)? '' : 'checked="checked"';
           $eltitle = empty($fullform)? '' : $ar->desc;
           # $prompt = "<td align=right>$eltitle</td>";
           if(strpos($addon,'value=')===false) $addon.='value="1"';
           $ret = "<input type=\"checkbox\" id=\"$editid\" name=\"$editid\" $checked $addon />\n";
           break;

     case 'TEXT': case '':
           $etype = 'text';
           if(stripos($addon, 'class=')===false) {
             if(!empty($as_cssclass['textfield'])) $fldclasses['txt'] = $as_cssclass['textfield'];
           }
           if(substr($ftype,0,4) === 'DATE') {
             $addon = empty($addon)? "size='10' maxlength=$elen" : $addon;
             $fldclasses['date']='datefield form-control w80 d-inline'; # ".datefield" - can attach calendar...
             if (intval($def)<=0) $def = '';
             elseif (!$forsearch) $def = to_char($def);
           }
           elseif($ftype=='TIME') {
             $fldclasses['time']='timefield';
             $etype='time'; # native drowser time input
           }
           elseif(IsNumberType($ftype,1))
             $addon = empty($addon)? " maxlength=$elen" : $addon;
           elseif($ftype=='TEXT' || $ftype=='LONGTEXT' || $ftype=='MEDIUMTEXT')
             $addon = empty($addon)? 'style="width:100%; height:80px;"' : $addon;
           else
             $addon = empty($addon)? "size='$edlen' maxlength='$elen'" : $addon;

           $txtclass = count($fldclasses)? (' class="'.implode(' ',$fldclasses).'"') : '';
           if(!empty($ast_frmfunctions[AST_FORM_ELEMENT][$ftype])) {
             $ret = call_user_func($ast_frmfunctions[AST_FORM_ELEMENT][$ftype],$id,$def);
           }
           else $ret = "<input type=\"$etype\" id=\"$editid\" name=\"$editid\" $txtclass $addon value='$def' />";
           break;

     case 'DATE':
           if(!empty($ast_frmfunctions[AST_FORM_ELEMENT][$ftype])) {
             $ret = call_user_func($ast_frmfunctions[AST_FORM_ELEMENT][$ftype],$id,$def);
           }
           else $ret = "<input type=\"date\" id=\"$editid\" name=\"$editid\" $txtclass $addon value='$def' />";
           break;

     case 'DATETIME':

           $defymd = substr($def,0,10);
           $defhh = substr($def,11,2);
           $defmm = substr($def,14,2);
           if (intval($defymd)<=0) $defymd = '';
           elseif (!$forsearch) $defymd = to_char($defymd);

           if(!empty($ast_frmfunctions[AST_FORM_ELEMENT]['DATE'])) {
             $ret = call_user_func($ast_frmfunctions[AST_FORM_ELEMENT]['DATE'],$id,substr($def,0,10));
           }
           else {
               $ret = "<input type=\"text\" id=\"$editid\" name=\"$editid\" class=\"datefield d-inline w100 form-control text-center\" value='$defymd' />";
               $tmtitle = Astedit::localize('title_time','time');
               $ret .= "&nbsp; $tmtitle: <input type=\"text\" id=\"{$editid}_hh\" name=\"{$editid}_hh\" $txtclass style='width:20px;text-align:center' value='$defhh' maxlength='2'/>" .
                " : <input type=\"text\" id=\"{$editid}_mm\" name=\"{$editid}_mm\" $txtclass style='width:20px;text-align:center' value='$defmm'  maxlength='2'/>";
               if (defined('USE_JQUERY_UI') /*&& constant('USE_JQUERY_UI')*/) { # add UI spinners to hours and mins
                   $hhmmjs = <<< EOJS
$(document).ready(function() {
   $('input[name$=_hh]').spinner({numberFormat:'n',min:0, max:23, step:1} );
   $('input[name$=_mm]').spinner({numberFormat:'n',min:0, max:55, step:5} );
});
EOJS;
                   addHeaderJsCode($hhmmjs, 'footer');
               }
           }
            break;

     case 'TEXTAREA':
           $addon = empty($addon) ? 'style="width:99%; height:40px;"' : $addon;
           $ret = "<textarea id=\"$editid\" name=\"$editid\" {$txtclass} $addon>$def</textarea>";
           break;

     case 'WYSIWYG': # TODO: place to var before post!
           # $ret .= $this->BuildWYSIWIGEditBLock($id,$def,$addon);
           # $def = str_replace("\n",'<br>', $def);
           switch($ast_wysiwyg_type) {
             case 'spaw':
               ob_start();
               if(class_exists('SpawEditor')) {
                 $editobj= new SpawEditor($id,$def);
                 $editobj->show();
               } else echo "ERROR: spaw editor class not included !";
               $ret = ob_get_clean();
               break;
             case 'tinymce':
               $def = htmlspecialchars($def,ENT_NOQUOTES);
               $ret = "<textarea id='$editid' name='$editid' class='tinymce' $addon>$def</textarea>";
               break;
             case 'jquery.wysiwyg':
               $addon = empty($addon) ? 'style="width:100%; height:40px;"' : $addon;
               $ret = "<textarea id=\"$editid\" name=\"$editid\" {$txtclass} $addon style='overflow:auto;'>$def</textarea>";
               break;
             default:
              $addon = empty($addon) ? "class='wysiwyg'" : $addon;
              $ret = DrawWysiwigToolbar($this)."<input type=hidden name='$editid'>";
              $ret .="<div id='$editid' contentEditable $addon>$def</div>";
              break;
           }
           break;
     case 'SELECT':
           # $onChange = (empty($astedit_jsHandler[$this->id][$id]) ? '':"onChange='".$astedit_jsHandler[$this->id][$id]."'");
           $ret = "<select id='$editid' name='$editid' $addon $disab>\n";
           $lar = empty($elist) ?  array() : GetArrayFromString($elist);
           if(is_array($lar)) { #<3>
              $ret .= DrawSelectOptions($lar,$def,true);
              $vstring = '';
           } #<3>
           $ret .= "</select>\n";
           break;

     case 'BLIST':
     case 'BLISTEXT':
     case 'BLISTLINK':
           $ret = '';
           # $def = empty($row[$id])? '':$row[$id];
           if(!empty($ar->edittype)) { #<4>
             if(!empty($elist)) {
               $lar = GetArrayFromString($elist);
               if ($forsearch) {
                   $ret = "<select id='$editid' name='$editid' $addon>\n";
                   $ret .= DrawSelectOptions($lar,$def,true) . '</select>';
                   $vstring = '';
               }
               else {
                   if ($etype === 'BLIST')
                       $ret = self::DrawBinaryList($id, $lar,$def);
                   elseif($etype === 'BLISTEXT')
                       $ret = self::DrawBinaryListExt($id, $lar,$def);
                   elseif($etype === 'BLISTLINK')
                       $ret = self::DrawBinaryListLink($id, $lar,$def);
               }
             } #<5>
           } #<4>
           break;
     /*
     case 'BLISTEXT':
           $ret = '';
           # $def = empty($row[$id])? '':$row[$id];
           if(!empty($ar->edittype)) { #<4>
             if(!empty($elist)) {
               if ($forsearch) {
                   $ret = "<select id='$editid' name='$editid' $addon>\n";
                   $lar = GetArrayFromString($elist);
                   $ret .= DrawSelectOptions($lar,$def,true) . '</select>';
                   $vstring = '';
               }
               else {
                   $lar = GetArrayFromString($elist);
                   $ret = self::DrawBinaryListExt($id, $lar,$def);
               }
             } #<5>
           } #<4>
           break;
     */
     case 'FILE':
       $ret = "<input type='file' name='$editid' id='{$ar->id}' {$txtclass} style='width:280px' />";
       break;
     case 'UDF':
       if(!empty($intags) && is_callable($intags)) $ret=call_user_func($intags,$ar->id, $act, $row);
       else $ret = "undefined UDF: ($intags)";
       break;
     default:
       $ret = ($this->debug)? "AStedit: unknown edit type {$ar->id} = $etype":'';
       break;
     }
     $afterinput = empty($ar->afterinputcode)? '': $ar->afterinputcode;
     if($afterinput) {
        if(substr($afterinput,0,1)=='@') $afterinput = eval($afterinput);
        else $afterinput = str_replace(array('{imgpath}','{ID}'), array(IMGPATH,$this->FullPkFieldAsString($row)),$afterinput);
        $ret .= '&nbsp;'.$afterinput;
     }
     if ($ar->subtype === 'LOGIN' && !$forsearch) $ret .= " &nbsp;<span id='chk_{$this->id}_{$ar->id}'>&nbsp; </span>"; # here will be drawn check result
     if($forsearch) $ret = (($forsearch==='from' || $forsearch==='to')? '': ($ar->shortdesc . ' ')) .$ret;
     return $ret;
  }
  public function dropQuotes($strg) {
      return strtr($strg, ['"'=>'&quot;']);
  }
  /**
  * makes html code for editing field
  *
  * @param mixed $fldid
  * @param mixed $act
  * @param mixed $row
  * @param mixed $fullform : 1 - full mode, 'input' - return only <input> html code, no label, <TR>,</TR> envelope
  * @return String html code
  */
  function DrawFormElement($fldid, $act, $row=0, $fullform=1){
     # $this->fields[$nElem]);

     global $ast_datarow,$as_cssclass,$ast_frmfunctions, $ast_wysiwyg_type;

     $ar = $this->fields[$fldid];
     if(isset($this->fixFields[$fldid])) {
        return "<input type='hidden' name='$fldid' value='" . $this->fixFields[$fldid] . "' />";
     }
     else
        $ret = $this->drawInput($ar,false,$act,$row);

     if ($fullform === 'input') return $ret;

     $id = $ar->id; # имя (id) поля
     $econd = astedit::evalValue($ar->editcond);
     if(in_array($id, $this->_pkfields)) $econd = 'H';
     $edstyle = ''; # I can 'hide' edit field in some cases, so they can be 're-enabled' from js
     if(empty($econd)) return '';# non-editable fields skipped !
     if(($econd === 'E' && ($act === 'add'))
      || ($econd === 'A' && ($act !== 'add') # A=только при вводе новой
      || ($econd) === 'H')) {
       $edstyle = 'style="display:none;"'; # initially hidden row
     }

     # если поле заводится только при создании, или { одно из PRIMARY-KEYS и операция-редактирование }
     if($econd==='C' || (in_array($fldid,$this->_pkfields) && $act==='edit')) {
         return ((empty($fullform)&& $ar->showcond)?'<td>&nbsp;</td>':'');
     }
     $editid = ($this->_prefixeditfield ? $this->id.'_': '') . $ar->id;
     $relem = " id=\"row_$editid\""; # <tr id=row_fieldname...>
     if(!empty($ar->editRowClass)) $relem .= " class=\"$ar->editRowClass\"";

     # $addon .= (empty($addon)?'':' ').$inputattrib; # READONLY or whatelse if needed
     $prompt = '<td class="text-end" >'.$ar->desc.'</td>'; # <td>title</td> if needed
     if($fullform) { # full screen form, so add all tags
       $ptompt = $ar->desc;
       $ret = "<tr $relem $edstyle>$prompt<td>$ret</td></tr>\n";
     }
     return $ret;
  } # DrawFormelement() end
  /**
  * @desc converts some chars, so they return to the 'editable' format
  */
  function MakeEditable(&$vparam) {
    $vparam = str_replace(array('&amp;','&',),array('&amp;','&amp;'), $vparam); # to avoid showing '&lt;' as '<' etc.
  }

  /**
  * Draws multiple checkboxes zone one per $lar item (for BLIST-type field)
  */
  function DrawBinaryList($flid, $lar,$def='') {

    $scrolltag = "";
    $ret = "<div style=\"overflow:auto; max-height:{$this->blist_height}; width:{$this->blist_width}\" id=\"blist_$flid\"><table class='zebra'>";
    if (is_callable($lar)) {
        $lar = call_user_func($lar);
    }

    if(is_array($lar) && count($lar)>0) { #<3>
      $spinit = is_array($def)? $def: explode(',', $def);
      foreach($lar as $kkk => $item) { #<4>
        if (is_array($item) && $item[0]==='<') { # optgoup - title before sub-list
            $ret .="<tr><td><b>$item[1]</td></td></tr>\n";
            continue;
        }

        $pid = $pname = '';
        if (is_array($item)) {
            if (count($item)>1) {
                $pid = $item[0];
                $pname = $item[1];
            }
            else $pid = $pname = $item[0];
        }
        else $pid = $pname = $item;

        if (!is_numeric($kkk)) $pid = $kkk;
        $chk = in_array($pid, $spinit) ? 'checked="checked"' : '';
        if ($this->fullBlistForm)
            $ret .="<tr><td><label><input type='checkbox' name='_tmp_{$flid}_$pid' id='_tmp_{$flid}_$pid' value='$pid' $chk /> $pid-$pname</label></td></tr>\n";
        else
            $ret .="<tr><td><label><input type='checkbox' name='_tmp_{$flid}_$pid' id='_tmp_{$flid}_$pid' value='$pid' $chk /> $pname</label></td></tr>\n";
      } #<4>
    } #<3>
    $ret .= "</table></div>\n";
    return $ret;
  }
  # вывод BLISTEXT = BLIST с полями ввода текста
  function DrawBinaryListExt($flid, $lar,$def='') {
    $scrolltag = "";
    $blext_w = 30; #default width for additiona numeric field
    $blext_chg = "onchange='NumberRepair(this)'";
    $ret = "<div style=\"overflow:auto; max-height:{$this->blist_height}; width:{$this->blist_width}\" id=\"blist_$flid\"><table class='zebra'>";
    if(is_array($lar) && count($lar)>0) { #<3>
      $spinit = is_array($def)? $def: explode(',', $def);
      $curvals = array();
      foreach($spinit as $itm) {
            $sp4 = explode(':',$itm);
            $curvals[$sp4[0]] = isset($sp4[1])? $sp4[1] : '';
      }
      foreach($lar as $kkk => $item) { #<4>
        if (is_array($item) && $item[0]==='<') { # optgoup - title before sub-list
            $ret .="<tr><td><b>$item[1]</td></td></tr>\n";
            continue;
        }
        if (is_array($item)) {
            $pid = $item[0];
            $pname = count($item)>1 ? "$pid - $item[1]" : $item;
        }
        elseif (is_scalar($item)) {
            $pid = $pname = $item;
        }
        $chk = key_exists($pid, $curvals) ? 'checked="checked"' : '';
        $sval = ($chk) ? $curvals[$pid] : '';
        $ret .="<tr><td><label><input type='checkbox' name='_tmp_{$flid}_$pid' id='_tmp_{$flid}_$pid' value='$pid' $chk /> $pname</label></td>"
          . "<td><input type='text' name='_tmp_{$flid}_{$pid}_extval_' id='_tmp_{$flid}_{$pid}_extval_' class='ibox ct' style='width:{$blext_w}px' $blext_chg maxlength='6' value='$sval'></td>"
          . "</tr>\n";
      } #<4>
    } #<3>
    $ret .= "</table></div>\n";
    return $ret;
  }
  # вывод BLISTLINK = BLIST со ссылкой (для вызова отдельного окна настроек)
  function DrawBinaryListLink($flid, $lar,$def='',$func='',$lnkTitle='') {
    $scrolltag = "";
    static $rrr = 0;
    # if(!$rrr)   writeDebugInfo("this: ", $this);
    # writeDebugInfo("$flid: fields def ", $this->fields);
    $editElems = explode(',', ($this->fields[$flid]->edittype ?? 'xxx'));
    $rrr++;
    $blext_w = 30; #default width for additiona numeric field
    if(empty($func)) $func = $editElems[2] ?? "astedit.openSubForm('{id}')";
    if(empty($lnkTitle)) $lnkTitle = $editElems[3] ?? 'Add Params';

    $ret = "<div style=\"overflow:auto; max-height:{$this->blist_height}; width:{$this->blist_width}\" id=\"blist_$flid\"><table class='zebra'>";
    if(is_array($lar) && count($lar)>0) { #<3>
      $spinit = is_array($def)? $def: explode(',', $def);
      $curvals = array();
      foreach($spinit as $itm) {
            $sp4 = explode(':',$itm);
            $curvals[$sp4[0]] = isset($sp4[1])? $sp4[1] : '';
      }
      foreach($lar as $kkk => $item) { #<4>
        if (is_array($item) && $item[0]==='<') { # optgoup - title before sub-list
            $ret .="<tr><td><b>$item[1]</td></td></tr>\n";
            continue;
        }
        if (is_array($item)) {
            $pid = $item[0];
            $pname = count($item)>1 ? "$pid - $item[1]" : $item;
        }
        elseif (is_scalar($item)) {
            $pid = $pname = $item;
        }
        $chk = key_exists($pid, $curvals) ? 'checked="checked"' : '';
        $sval = ($chk) ? $curvals[$pid] : '';

        $oneFunc = str_replace('{id}',$pid, $func );

        # writeDebugInfo("2) func:($pid) ", $oneFunc);
        $hide = !empty($item['hide']); # if "hide" element passed? don't show link-button!
        $btnHtml = $hide ? '' : "<td><input type='button' class='btn btn-primary' onclick=\"$oneFunc\" value='$lnkTitle'></td>";
        $ret .= "<tr><td><label><input type='checkbox' name='_tmp_{$flid}_$pid' id='_tmp_{$flid}_$pid' value='$pid' $chk /> $pname</label></td>"
             . $btnHtml . "</td></tr>\n";
      } #<4>
    } #<3>
    $ret .= "</table></div>\n";
    return $ret;
  }
  # remembers fixed Field values (if filter active etc.) Call before DrawEditForm() !
  public static function setFixFields($arList) {
      $this->fixFields = $arList;
  }
  /**
  * Draws full editing form to edit record
  * $fixFields - array with fixed field values to avoid change them
  */
  public function DrawEditForm($pact='',$pid='',$backlink=true, $returnBody = FALSE, $fixFields = []) {
    global $id, $ast_act, $ast_tips, $ast_datarow,$as_cssclass, $ast_wysiwyg_type;

    if(is_array($fixFields) && count($fixFields)) $this->fixFields = $fixFields;
    $strRet = '';
    if(!empty($pid) && empty($id))
        $id = $pid;
    if(!empty($pact) && is_string($pact))
        $ast_act = $pact;
    if(!empty($GLOBALS['ast_act'])) $ast_act = $GLOBALS['ast_act'];
    if(!empty($GLOBALS['ast_edit_id'])) $id = $GLOBALS['ast_edit_id'];
    if(!empty($this->beforeedit) && is_callable($this->beforeedit)) {
        call_user_func($this->beforeedit, $ast_datarow, $pact);
    }
    if($pact=='') $titul = ($ast_act == 'add') ? $ast_tips['titleadd'] : $ast_tips['titleedit'];
    else $titul = $pact;
    $submittxt = ($ast_act == 'edit') ? $ast_tips['buttsave'] : $ast_tips['buttadd'];
    $canceltxt = isset($ast_tips['buttcancel'])? $ast_tips['buttcancel'] : 'Cancel';

    $btnclass = empty($as_cssclass['button'])?'':"class='{$as_cssclass['button']}'";

    $ast_datarow = [];
    # writeDebugInfo("ast_act: $ast_act, id: $id");
    if($ast_act == 'edit' && !empty($id)) {
      # $ast_datarow = Astedit::$db->GetQueryResult($this->id,'*',$this->pkEqExpression($id),0,1); # old select style!
      $ast_datarow = Astedit::$db->select($this->id,['where'=>$this->pkEqExpression($id), 'singlerow'=>1]);
      # writeDebugInfo("data row: ", $ast_datarow );
    }
    $js = '';
    if($this->clonable) {
        $fldid = $this->clonable_field;
        $fldPrompt = (isset($this->fields[$fldid])) ? $this->fields[$fldid]->desc : $fldid;

        $childs = ($this->confirm_clone_child) ?
           "<br><label><input type=\"checkbox\" name=\"clonechild\" id=\"clonechild\" value=\"1\">$this->confirm_clone_child</label>"
         : '';
        $parval = ($this->confirm_clone_child) ? '$(\'input#clonechild\').is(\':checked\') ? 1:0' : '1';
        $js .= <<< EOJS
function Ast_ConfirmCloneRecord(id) {
    cloneId = id;
    var cHtml = '$fldPrompt:<br><input type="text" id="cloned_newvals" class="ibox w300">$childs<br><br>$ast_tips[clone_record] ?';
    dlgConfirm(cHtml, ast_startClone, null);
}
function ast_startClone() {
  var clones = $('input#cloned_newvals').val();
  var bChild = $parval;
  var request = 'ast_act=clone&sourceid='+cloneId+'&clonechild='+bChild+'&newvals='+encodeURIComponent(clones);
  //alert(request);
  window.location.href = '{$this->baseuri}'+request;
}
EOJS;
    }
    if ($this->helppage) {
        $js .= <<< EOJS

function asteditShowHelp() {
    $.post("$this->baseuri", {'asteditajax':1,'ast_act':'showhelp', 'tableid':'$this->tbrowseid'}, function(response) {
        var winW = Math.floor( $(window).width()*0.8);
        var winH = Math.floor( $(window).height()*0.8);
        var dlgOpts = {width:winW, resizable:true, zIndex: 500, height:winH
          ,buttons: [{text: "OK",click: function() {\$( this ).dialog( "close" ).remove();}}]
          ,open: function(event,ui) {
            $('.ui-dialog').css('z-index',9002);
            $('.ui-widget-overlay').css('z-index',9001);
           }
        };

        dlgOpts.title = '$ast_tips[help]';
        dlgOpts.dialogClass = 'floatwnd';
        $('<div id="dlg_helppage" style="z-index:9900">'+response+'</div>').dialog(dlgOpts);
    });
}

EOJS;

    }
    /*if($this->loginFieldsExists())*/ $js .= <<< EOJS
function astCheckUniqueness(tblid,obj,recid) {
    var recid = $('#_astkeyvalue_').val();
    SendServerRequest('{$this->baseuri}',{ast_act:'check_loginunique', tableid:tblid, fieldid: obj.name,fvalue: obj.value, record:recid});
}
EOJS;

    if(!empty($js)) $strRet .= "<script type='text/javascript'>$js</script>\n";
    if(!$returnBody) {
        $strRet .=  "<div id='editing_record' style='text-align:center'>";
        if(!empty($titul)) $strRet .=  "<h4>$titul</h4>\n";
        if(($backlink===TRUE) && empty($this->windowededit)) $strRet .= "<a href=\"{$this->baseuri}\" >{$ast_tips['tobrowse']}</a>\n";
        if($this->clonable && !empty($id)) {
          #   echo "&nbsp; <button class='btn btn-primary' onclick=\"return Ast_ConfirmCloneRecord($id)\">{$ast_tips['clone_record']}</button>";
          $strRet .= "&nbsp; <a href=\"#\" onclick=\"Ast_ConfirmCloneRecord('$id')\">{$ast_tips['clone_record']}</a>";
        }
    }
    $wdth = $this->editformwidth;
    $brnum = intval($wdth);
    if ("$brnum" === "$wdth") $wdth .= 'px';
    $strRet .= "<div id='edit_layout'>";
    if(!empty($this->editform) && is_callable($this->editform)) {
        # call user function for drawing EDIT form
        if(!$returnBody) echo $strRet;
        call_user_func($this->editform, $ast_act,$ast_datarow, $this);
        return;
    }

    $onsubmit = empty($this->onsubmit) ? '' : $this->onsubmit.";";

      # openwysiwyg support:
    if(count($this->_wysiwyg)>0 && $ast_wysiwyg_type=='openwysiwyg') {
        $strRet .= "<script type=\"text/javascript\">\n";
        foreach($this->_wysiwyg as $wysid) { $strRet .= "WYSIWYG.attach('$wysid'); alert('$wysid');\n"; }
        $strRet .= "</script>";
    }
    # simple built-in WYSIWYG editor
    if(count($this->_wysiwyg) && $ast_wysiwyg_type=='') { # there are 'WYSIWYG fields, so we need some onSubmit function:
?>
<script type="text/javascript">
function MakeHref() {
    var clpbrd = '';
    try { clipboardData.getData("Text"); } catch(serror) { clpbrd=''; alert('err catched'); }
    if(clpbrd.length>4 && 'www.' == clpbrd.substring(0,4)) clpbrd = 'http:#'+clpbrd;
    if(clpbrd.length>4 && clpbrd.indexOf(':#')>0) bUser = false;
    else bUser = true;
    document.execCommand('CreateLink',bUser,clpbrd);
    return false;
}
function MakeImage() {
    document.execCommand('InsertImage',false,'image.png');
    return false;
}
function SaveWysiwygField(flid) {
    var fm = $("#<?=$this->id?>").get(0);
    eval('var val = '+flid+'.innerHTML;');
    eval('fm.'+flid+'.value = val;');
}
function SaveAllWysiwygFields() {
<?php  foreach($this->_wysiwyg as $wfld) echo "    SaveWysiwygField('$wfld');\n"; ?>
      return true;
}
</script>
<?php
        $onsubmit .= "return SaveAllWysiwygFields();";
      }
      if(is_object($this->_picobj)) {
        $this->_picobj->DrawImageJsFunctions(true);
      }

      $frmtags = '';
      if($this->_multipart) $frmtags .= ' enctype="multipart/form-data"';
      if ($onsubmit) $frmtags .= " onsubmit='$onsubmit'";
      if(!$returnBody)  $strRet .= "<form name=\"astedit_{$this->id}\" id=\"astedit_{$this->id}\" action=\"{$this->baseuri}\" method=\"post\"{$frmtags} >
        <div class='card'><table class='table align-middle'>";

      else $strRet .= "<form name=\"astedit_{$this->id}\" id=\"astedit_{$this->id}\" ><table class='table'>";

      $strRet .= "<input type=\"hidden\" id=\"ast_act\" name=\"ast_act\" value=\"do{$ast_act}\" />";

      if(!empty($id)) $strRet .= "<input type=\"hidden\" id=\"_astkeyvalue_\" name=\"_astkeyvalue_\" value=\"$id\" />";
      if (!empty($this->edit_template)) {
          $frmBody = file_get_contents($this->edit_template);
          $fsubst = [];
          foreach($this->fields as $fldid=>$fld) {
              $fsubst['%'.$fldid."_label%"] = $fld->desc;
              $fsubst['%'.$fldid."_input%"] = $this->drawInput( $this->fields[$fldid],false,$ast_act,$ast_datarow);
              # $this->DrawFormElement($fldid, $ast_act, $ast_datarow, 'input');
          }
          $frmBody = strtr($frmBody, $fsubst);
          $strRet .= $frmBody;
          if(count($this->fixFields)) foreach($this->fixFields as $fixKey=>$fixVal) {
            $strRet .= "<input type='hidden' name='$fixKey' value='$fixVal' />";
          }
      }
      else foreach($this->fields as $fldid=>$fld) {
        $strRet .= $this->DrawFormElement($fldid, $ast_act, $ast_datarow);
      }

      if(is_object($this->_picobj) && $ast_act == 'edit') {
          $strRet .= '<tr><td colspan="2">';
          $this->_picobj->DrawPhotos($id,'800',true);
          $strRet .= '</td></tr>';
      }
      if(is_object($this->_picobj)) {
          $strRet .= '<tr><td colspan="2">';
          ob_start();
          $this->_picobj->DrawUploadPhotos(4);
          $strRet .= ob_get_clean();
          $strRet .= "</td></tr>";
      }

      if(!empty($this->editsubform)) {
          if(is_callable($this->editsubform)) {
            $code = call_user_func_array($this->editsubform, array($ast_act,$id));
            if (!empty($code) && is_string($code)) $strRet .= "<tr><td colspan=\"2\">$code</td></tr>";
          }
      }
      $canc = ($this->windowededit)? "<input type=\"submit\" onClick=\"window.close()\" $btnclass value=\"$canceltxt\" />":'';
      $helpBtn = '';
      if ($this->helppage && !$returnBody) {
        $helpBtn = "<a class=\"helplink\" href='javascript:void()' onclick=\"asteditShowHelp()\" title=\"$ast_tips[tiphelpedit]\">?</a>";
      }
      // echo "<tr><td colspan='2' class='area-buttons'><input type='submit' name='ast_submit_edit' {$btnclass} value='$submittxt' />&nbsp; $canc $helpBtn</td></tr>\n";

      if(!empty($this->aftereditsubform) && is_callable($this->aftereditsubform)) {
          $code = call_user_func_array($this->aftereditsubform, array($ast_act,$id));
          if (!empty($code) && is_string(code)) echo $code;
      }
      $strRet .= '</table>';
      if($returnBody) {
          $strRet .= '</form>';
      }
      else {
          $strRet .= "<div class='card-footer justify-content-between'><span></span> <span><input type='submit' name='ast_submit_edit' {$btnclass} value='$submittxt' /> $canc</span> <span>$helpBtn</span></div>\n";
          $strRet .= "</form>\n";
          $strRet .= "</div> <!-- editing_record -->\n";
      }
      if($returnBody) return $strRet;

      echo $strRet;
      if($this->windowededit ) {
          exit("</body></html>");
      };
  }

  function SetEditButton($picurl,$width=0, $height=0) {
    global $asbtn;
    $astbtn['edit'] = $picurl;
    if($width) $astbtn['w'] = $width;
    if($height) $astbtn['h']= $height;
  }

  function SetDelButton($picurl,$width=0, $height=0) {
    global $asbtn;
    $astbtn['del'] = $picurl;
    if($width) $astbtn['w'] = $width;
    if($height) $astbtn['h']= $height;
  }

  function SetAddButton($picurl,$width=0, $height=0) {
    global $asbtn;
    $astbtn['add'] = $picurl;
    if($width) $astbtn['w'] = $width;
    if($height) $astbtn['h']= $height;
  }

  function UpdateDataIntoTable($act, $pkeyval=0, $newdata=false)
  { # $act = 'doedit' : 'doadd'
      global $ast_tips,$ast_parm;
      $oldState = NULL;
      if (Astedit::$RO_mode) { return $ast_tips['err_readonly_mode']; }
      $params = is_array($newdata)? $newdata : $ast_parm;

      $keyvalues='';
      if(!empty($params['_astkeyvalue_'])) $keyvalues=$params['_astkeyvalue_'];
      elseif(!empty($pkeyval)) $keyvalues = $pkeyval;
      else
        foreach($this->_pkfields as $pkf) { $keyvalues .=($keyvalues==''?'':AST_PKDELIMITER).(isset($params[$pkf])? $params[$pkf]:''); }

      if ($this->_auditing && $act ==='doedit') {
          $oldState = $this->_getRecordData($keyvalues);
      }
      if(!empty($this->afteredit)) {
         if(is_callable($this->afteredit)) { # check/modify edited fields or do something else before saving
            $newparams = call_user_func($this->afteredit,$params,$act); #
            if(is_array($newparams)) $params = array_merge($params, $newparams);
         }
         # else WriteDebugInfo("undefined callback for afterdit: ", $this->afteredit);
      }

      if(!is_array($params) || !count($params)) {
          $this->updateResult = 'error';
          return 'Update failed: No passed Data!'; # something goes wrong, don't save !
      }

      if(!empty($this->updatefunc) && is_callable($this->updatefunc)) {
          $res_text = call_user_func($this->updatefunc,$act,$params);
          if($this->_auditing) {
              $delta = NULL;
              if ($act ==='doedit') {
                  $newState = $this->_getRecordData($keyvalues);
                  $delta = $this->getChangedRecords($oldState, $newState);
              }
              @call_user_func($this->_auditing,$this->id,$act,$keyvalues, $delta);
          }
          return $res_text;
      }
      $vlst = array(); # assoc.array: $vlst['pole1'] => 'pole 1 value' ...
      $uqry = 'none';

      if (count($this->_savefile_pm) >0) {
          foreach($this->_savefile_pm as $fldname => $svdata) {
              if(isset($_FILES[$fldname])) {
                  if(!empty($svdata[0]) && !empty($_FILES[$fldname]['name'])) {
                      # $fsplit = preg_split('/[\:\/\\\]+/',$_FILES[$fldname]['name']);
                      # $params[$svdata[0]] = substr($fsplit[count($fsplit)-1], -160);
                      $params[$svdata[0]] = substr($_FILES[$fldname]['name'], -160);
                      # $params[$svdata[0]] = $_FILES[$fldname]['name']; # Save original file name "myfile.png"
                  }
                  if(!empty($svdata[1]) && !empty($_FILES[$fldname]['type']))
                    $params[$svdata[1]] = $_FILES[$fldname]['type']; # Save original file type: "image/png"
              }
          }
      }
      foreach($this->fields as $fid=>$fld) { #<2> for
         if ($fld->external) {
            continue; # skip external field values
         }
         if($act==='doedit' && in_array($fid,$this->_pkfields)) continue; # dont change primary keys
         $ftype = $fld->type;

         $fetype = explode('^',$fld->edittype);
         if(count($fetype)<2) $fetype = explode(',',$fld->edittype);

         if (strtoupper($fetype[0]) === 'CHECKBOX' && !isset($params[$fld->id])) $params[$fld->id] = '0';

         $econd = ($this->_updt_all)? true : astedit::evalValue($fld->editcond);
         $editfld = isset($params[$this->id .'_'. $fid])? ($this->id.'_'.$fid) : $fid;
         if($econd === 'A' and $act!=='doadd') continue;

         $val = isset($params[$editfld]) ? ($params[$editfld]) : '';
         $standalone = true;
         if (is_callable('appEnv::isStandalone')){
             $standalone =  appEnv::isStandalone();
         }
         if($fld->subtype === 'LOGIN') {
             if ($standalone) {
                 if($val==='') return "Пустое значение для логин-полей недопустимо, операция отклонена !";
                 $result = preg_match("/^[@.a-z0-9_]*$/i", $val);
                 if(!$result) return "Значение логина содержит недопустимые символы, операция отклонена !";
                 if(class_exists('CAuthCorp')) {
                     $auth = CAuthCorp::getInstance();
                     if($auth->IsBuiltinAccount(strtolower($val))) return 'Выбранный логин запрещен !';
                 }
             }
             $where = "($fid='$val')" . ( $act==='doedit' ? " AND NOT (".$this->pkEqExpression($keyvalues).')':'');
             $cnt = Astedit::$db->getQueryResult($this->id,'COUNT(1)',$where);
             if($cnt>0) return 'Выбранный логин уже был занят, выберите другой. Операция ввода отклонена ! !';
         }
         if($econd==='C' && $act==='doadd') {
           if($fld->_autoinc) continue;
           $val ='';
           $newarr = astedit::SplitDefault($fld->defvalue);
           $newval = (isset($newarr[1])?$newarr[1] : '');
           if($newval==='now' || $newval==='{now}') { $val = date('Y-d-m H:i:s'); }
           elseif($newval==='today' || $newval==='{today}') $val = date('Y-d-m');
           else $val = astedit::evalValue($newval);
           if(!empty($val)) $vlst[$fld->id] = $val;
         }
         elseif(!empty($econd) && (isset($params[$fld->id]) || isset($params[$this->id.'_'.$fld->id])
           || in_array($fetype[0],['BLIST','LISTEXT']) || $act==='doedit'))
         { #<3>
           # $this->_prefixeditfield - returned fields may contain tablename_ prefix
           if($val=='' && IsNumberType($ftype,1)) $val = '0';

           if(in_array($fetype[0], ['BLIST','BLISTEXT','BLISTLINK'])) { # <4> merge _tmp_$flid_$val into "n1,n2,..." text field
              # TODO: refactor to _tmp_field_blist[] (array input) !
              $vals = array();
              $blist_preflen = strlen("_tmp_{$fid}_");
              foreach($params as $parid=>$val) { #<5>
                if(substr($parid,0, $blist_preflen) ==="_tmp_{$fid}_" && strrpos($parid,'_extval_')===FALSE) {
                    $oneval = $val;
                    if (!empty($params[$parid.'_extval_'])) # BLISTEXT: creating id:val pair
                        $oneval .= ':' . $params[$parid.'_extval_'];
                    $vals[] = $oneval;
                }
              } #<5>
              $val = $params[$fid] = implode(',',$vals); # merged list
              # exit("final blist/ext value:" . $val);
           } #<4>
           if($ftype ==='DATE'){
               if(in_array($val, array('now','{now}','today','{today}'))) $val = date('Y-m-d');
               else $val = to_date($val,2);
           }
           elseif($ftype==='DATETIME') {

               if(empty($val)) {
                   $def = astedit::SplitDefault($this->fields[$fid]->defvalue);
                   if(isset($def[1]) && in_array($def[1], array('now','{now}','today','{today}'))) $val = date('Y-m-d');
               }
               else {
                   $val = to_date($val);
                   if (isset($params[$fld->id.'_hh']))
                      $val .=  ' '.str_pad(intval($params[$fld->id.'_hh']),2,'0',STR_PAD_LEFT)
                        . ':' . (isset($params[$fld->id.'_mm'])? str_pad(intval($params[$fld->id.'_mm']),2,'0',STR_PAD_LEFT):'00');
                   else $val = to_date($val,2);
               }
           }
           elseif($fetype[0] === 'CHECKBOX') {
               $vlst[$fld->id] = empty($params[$fld->id])? 0 : 1;
           }
           elseif(IsTextType($ftype)){
               if(empty($this->_enablesinglequote)) $val = str_replace("'",'"',$val);
               $mgquotes = ini_get('magic_quotes_gpc'); #magic_quotes_runtime - from MySQL
               if(empty($mgquotes))
                  $val = addslashes($val);
           }
           elseif(IsNumberType($ftype)) {
               $val = empty($val)? '0' : floatval($val);
           }
           if($econd !=='C' || $act=='doadd')
               $vlst[$fld->id] = $val;
         } #<3>
      } #<2>
      /*
      if ($this->debug == 99) {
          appEnv::appendHtml('Prepared data to add:<pre>' . print_r($vlst,1) .'</pre>');
          appEnv::finalize();
      }
      */
      $result = -1;
      if(count($vlst)>0) { # <2> values OK, perform update/add action
          if ($act=='doedit') {
            $result =  Astedit::$db->update($this->id, $vlst, $this->pkEqExpression($keyvalues));
          }
          else {
              $result = Astedit::$db->insert($this->id, $vlst);
              Astedit::$newRecordId[$this->id] = Astedit::$db->insert_id();
          }
      }
      $new_id = $keyvalues;
      if($result) {
          # Storing external fields
          $this->_saveExternalFields($act, $params, $result);

        if($act==='doadd') { # конструирую полное значение ключа новой записи
          $new_id = Astedit::$db->insert_id();
          foreach($this->fields as $tf) {
              if($tf->_autoinc) {
                  $params[$tf->id]= $new_id;
                  break;
              }
          }
          $keyvalues='';
          foreach($this->_pkfields as $tf) $keyvalues .=($keyvalues? AST_PKDELIMITER:'').$params[$tf];
        }
        $res_text = Astedit::$VERBOSE ? $ast_tips['updated'] : (isset($GLOBALS['ast_text_added'])?$GLOBALS['ast_text_added']:'Data saved');
        if(is_object($this->_picobj)) { #delete old/insert new photos
          $this->_picobj->UpdatePictureInfo($keyvalues); # multi-pk-field - no work !
          # WriteDebugInfo("$this->id : some pictures may be uploaded for record $id");
        }

        # save uploaded files...
        if (count($this->_savefile_pm) >0) {
          foreach($this->_savefile_pm as $fldname => $svdata) {
              # WriteDebugInfo("FILE to save info($fldname): ", $svdata);
              if(isset($_FILES[$fldname]) && !empty($_FILES[$fldname]['tmp_name'])) {  # $rec_id, $filename='',$tmp_filename
                  if(!empty($svdata[2]) and is_callable($svdata[2])) call_user_func($svdata[2],$new_id,$_FILES[$fldname]); # Save original file name "myfile.png"
                  else $this->saveUploaded($new_id, $fldname, $_FILES[$fldname]);
              }
          }
        }

        if ($this->_auditing && is_callable($this->_auditing)) {
            $delta = NULL;
            if ($act ==='doedit') {
                $delta = $this->getChangedRecords($oldState, $vlst);
            }
            @call_user_func($this->_auditing,$this->id,$act,$keyvalues, $delta);
        }
        $this->updateResult = 'success';
      }
      else {
        $res_text = "Error on executing operator:<br>$uqry<br>".Astedit::$db->sql_error();
        $this->updateResult = 'error';
      }
      return $res_text;
  }
  # finds fields changed during editing record
  protected function getChangedRecords($old, $new) {
      $ret = [];
      foreach($this->fields as $fid => $def) {
          if (isset($new[$fid]) && $def->editcond && $old[$fid] != $new[$fid]) $ret[] = $fid;
      }
      return $ret;
  }
  # Save external fields if they have respective setter function
  private function _saveExternalFields($act, $vals,$result) {

        foreach($this->fields as $fid=>$fld) {
              if ($fld->external && is_callable($fld->setter) && isset($vals[$fid])) {
                    $updResult = call_user_func($fld->setter, $vals, $vals[$fid]);
#                    WriteDebugInfo("Saving external fld $fid result:", $updResult);
          }
      }
        return TRUE;
  }
  # saves uploaded file into $this->file_folder
  public function saveUploaded($id, $fileprefix, $finfo) {
      $result = 0;
      if(is_array($finfo) && !empty($finfo['tmp_name'])) {
          if(!is_dir($this->file_folder)) {
              @mkdir($this->file_folder);
              @chmod($this->file_folder,0666);
          }
          $destname = $this->file_folder . $fileprefix . '-'.str_pad($id, 8,'0',STR_PAD_LEFT) . '.dat';
          $result = @copy($finfo['tmp_name'], $destname);
          if(!$result) {
              $fullfolder = realpath($this->file_folder);
              # WriteDebugInfo('Error writing file to ',$destname, ' ', $fullfolder);
              AppAlerts::raiseAlert("DOC FILES WRITE", 'Не удалось записать файл в папку документов - '.$fullfolder);
              echo "<div class=\"alarm\">Не удалось записать файл в папку документов - $fullfolder</div>";
          }
          else AppAlerts::resetAlert("DOC FILES WRITE");
          @unlink($finfo['tmp_name']);
      }
      return $result;
  }

  public function dropUploaded($id, $fileprefix) {
      $flname = $this->file_folder . $fileprefix . '-'.str_pad($id, 8,'0',STR_PAD_LEFT) . '.dat';
      return (file_exists($flname) ? @unlink($flname) : 0);
  }

  public function SetFieldParameters($fld_id, $defvalue='',$showcond='',$editcond='') {
    if(isset($this->fields[$fld_id])) {
      $this->fields[$fld_id]->defvalue = $defvalue;
      $this->fields[$fld_id]->showcond = $showcond;
      $this->fields[$fld_id]->editcond = $editcond;
      return true;
    }
    return false;
  }
  /**
  * sets table in "frozen" mode - protect from update operations (add, update,delete,clone)
  * @param mixed $parm true (default) - freezes table, false - return to editable mode
  */
  public function FreezeTable($parm=true) { $this->_frozen = $parm; }
  public function drawJsCode() {
      if($this->_jscode) echo "<script type=\"text/javascript\">\n"
        . str_ireplace('{baseuri}',$this->baseuri,$this->_jscode)
        . "\n</script>\n";
  }
  # Setting prefix for filter field names in current context
  public function setFilterPrefix($strPrefix) {
    $this->filterPrefix = $strPrefix;
  }
  public function MainProcess($dobrowse=1, $canedit=false, $candelete=false, $caninsert=false)
  { # call this for handle all 'ast_act' values. Returns
    global $ast_parm, $ast_act, $id, $ast_tips, $ast_browse_jsdrawn,$ast_datarow,
        $astbtn,$imgPath,$cursortfld,$cursortord;
    if (astedit::$time_monitoring) {
        $this->strt_time = microtime();
        # $elapsed = microtime() - $this->strt_time;
        WriteDebugInfo($this->id . ':MainProcess start, mktime:' . $this->strt_time);
    }

    if(empty($ast_parm)) {
        $ast_parm = DecodePostData(1);
    }
    if (!isAjaxCall() ) {
        $this->drawJsCode();
    }
    if(!empty($ast_parm['ast_act']) && $ast_parm['ast_act']==='sort') {
      $tb = empty($ast_parm['ast_t'])?'':$ast_parm['ast_t'];
      $fl = empty($ast_parm['ast_f'])?'':$ast_parm['ast_f'];
      if(!empty($fl) && !empty($tb)) {
          $cursortfld = $fl;
          $cursortord = (!empty($_SESSION[$this->filterPrefix.'ast_sortfld_'.$tb]) && $_SESSION[$this->filterPrefix.'ast_sortfld_'.$tb]===$fl
            && isset($_SESSION[$this->filterPrefix.'ast_sortord_'.$tb] ))?
              $_SESSION[$this->filterPrefix.'ast_sortord_'.$tb]  : 0;
          $cursortord = $cursortord? 0:1; # flip
          $_SESSION[$this->filterPrefix.'ast_sortfld_'.$tb] = $fl;
          $_SESSION[$this->filterPrefix.'ast_sortord_'.$tb] = $cursortord;
      }
    }
    $btWidth = $astbtn['w'] ?? 16;
    $btnHeight = $astbtn['h'] ?? 16;
    $this->imgatt = isset($astbtn['class']) ? "class=\"$astbtn[class]\"" : "width='$btWidth' height='$btnHeight'";

    if(empty($this->id)) { echo 'No meta-data loaded'; return; }
    if(empty($ast_act) && !empty($ast_parm['ast_act'])) $ast_act=$ast_parm['ast_act'];
    if($ast_act==='clone') {
      $id=isset($ast_parm['sourceid'])?$ast_parm['sourceid']:0;
      if($id>0 && ($this->clonable) && ($caninsert)) $this->CloneRecord($id);
    }

    $this->canedit=$canedit;  $this->candelete=$candelete; $this->caninsert=$caninsert;
    $page = $_SESSION[$this->filterPrefix.$this->tbrowseid]['page'] ?? 0;
    $lnkrow = floor($page/$this->pagelinksinrow); # current visible row in page-hrefs
    if(empty($ast_act)) $ast_act = empty($ast_parm['ast_act']) ? '' : $ast_parm['ast_act'];

    if(empty($id))  $id  = empty($ast_parm['id'])? '' : $ast_parm['id'];
    # if came from "endless" mode, added record, get in "EDIT" mode for this record
    if(!empty($GLOBALS['ast_endless_act'])) $ast_act = $GLOBALS['ast_endless_act'];
    if(!empty($GLOBALS['ast_edit_id'])) $id = $GLOBALS['ast_edit_id'];
    $res_text = '';
    if(!empty($ast_parm['ast_act']))   $ast_act = $ast_parm['ast_act'];
    $showbrowse = $dobrowse; # if nothing happens, I'll draw normal browse page
    if(($ast_act ==='doedit' || $ast_act==='delete') && empty($id) && !empty($ast_parm['_astkeyvalue_']))
      $id = $ast_parm['_astkeyvalue_'];  # <TODO>!!!

    if(isset($ast_parm['astpage']))
        $_SESSION[$this->filterPrefix.$this->tbrowseid]['page'] = $ast_parm['astpage'];


    switch($ast_act)
    { #<3> switch
    case 'crttable':
      $qry= $this->CreateTable();
      reset($qry);
      $res_text = '';
      $showbrowse = 1; # 0 - debug
      foreach($qry as $qryname=>$qrytext) { #<3>
        if(Astedit::$db->sql_query($qrytext))
          $res_text .= "$qryname: <b>$qrytext</b> : {$ast_tips['done']}<br>\n"; #'Таблица создана';
        else
        {
          $res_text .= $qryname.': '.$ast_tips['sql_error'].'<br>' . Astedit::$db->sql_error().'<br><b>'.$qrytext."</b><br>\n";
          $showbrowse = 0;
        }
      } #<3>

      break;

    case 'edit':
    case 'add':
      $this->DrawEditForm();

      $showbrowse = 0;
      break;

    case 'delete':
      # do deleting from table
      if(!isset($ast_tips['deleteprompt'])) $ast_tips['deleteprompt']='Deleting record. Are You sure ?';
      if($this->_frozen) $res_text .=$ast_tips['table_frozen'];
      elseif(!empty($id) ) {
        # WriteDebugInfo($this->recdeletefunc, "func exists:[".is_callable($this->recdeletefunc).']');
        if (!empty($this->recdeletefunc) && is_callable($this->recdeletefunc))
        {
          $result = call_user_func($this->recdeletefunc,$id);
          if(empty($result) && !empty($this->afterupdate) && is_callable($this->afterupdate))
            call_user_func($this->afterupdate,'delete', $id, Astedit::$db->sql_errno());
          $res_text .= (empty($result)? '' : $result); # $ast_tips['rec_deleted']
          if(empty($res_text) && $this->_auditing) @call_user_func($this->_auditing,$this->id,$ast_act,$id);
        }
        else {
            $res_text.=$this->DeleteRecord($id);
        }
      }
      break;

    case 'doedit': # update record with edited values
      if($this->_frozen) $res_text .=$ast_tips['table_frozen'];
      else { #<3>
        $res_text = $this->UpdateDataIntoTable($ast_act);
        if(!empty($this->afterupdate) && is_callable($this->afterupdate)) {
            call_user_func($this->afterupdate,'update', $id, Astedit::$db->sql_errno());
        }

        if($this->editmode == 'endless' || $this->editmode==1) {
          $ast_act = $GLOBALS['ast_endless_act'] = 'edit';
          $id = $GLOBALS['ast_edit_id']=$ast_parm['_astkeyvalue_'];
          $this->DrawEditForm();
          $showbrowse = 0;
        }
      } #<3>
      break;

    case 'doadd':  # add a record
      if($this->_frozen) $res_text .=$ast_tips['table_frozen'];
      else { #<3>
        $res_text = $this->UpdateDataIntoTable($ast_act);
        if(!empty($this->afterupdate) && is_callable($this->afterupdate)) {
          $id = Astedit::getInsertedId($this->id);
          call_user_func($this->afterupdate,'add', $id, Astedit::$db->sql_errno());
        }
        if($this->editmode == 'endless') {
          $ast_act = $GLOBALS['ast_act'] = 'edit';
          $GLOBALS['ast_edit_id']=$id;
          $this->DrawEditForm();
          $showbrowse = 0;
        }
      } #<3>
      # $res_text = $result? "Обновление выполнено" : "Ошибка при выполнении оператора:<br>$uqry<br>".mysql_error();
      break;

    case 'setfilter': # user sets some search criteria - so set a filter
      $tid = 'flt_'.$this->tbrowseid; # table name, must exist in passed attributes
      if(!empty($this->filterPrefix)) $tid = $this->filterPrefix . '-' . $tid;
      $this->ClearCurPageNo();
      if(!empty($ast_parm['search_function'])) {
        $searchfnc = $ast_parm['search_function'];
        if(is_callable($searchfnc)) call_user_func($searchfnc,'setfilter');
      }
      else {
          $fname = isset($ast_parm['flt_name'])? $ast_parm['flt_name'] : '';
          if(!isset($this->fields[$fname])) break;
          $val = isset($ast_parm['flt_value']) ? $ast_parm['flt_value'] : '';
          $valfrom = isset($ast_parm['flt_value_from']) ? $ast_parm['flt_value_from'] : null;
          $valto = isset($ast_parm['flt_value_to']) ? $ast_parm['flt_value_to'] : null;
          if(!empty($ast_parm['clearfilter'])) {
              unset($_SESSION[$tid][$fname]);
          }
          else {
            if($this->fields[$fname]->edittype == 'CHECKBOX' or $this->fields[$fname]->type=='BOOL') $val = empty($val) ? 0:1;
            if($valfrom!==null OR $valto!==null) {
                $_SESSION[$tid][$fname] = array($valfrom,$valto);
            }
            else $_SESSION[$tid][$fname] = $val; # single value filter
          }

      }
      break;

    case 'resetfilter':
      $this->ClearCurPageNo();
      if(!empty($ast_parm['search_function'])) {
        $searchfnc = $ast_parm['search_function'];
        if(is_callable($searchfnc)) call_user_func($searchfnc,'reset');
      }
      else {
        $tid = 'flt_'.$this->tbrowseid; # table name, must exist in passed attributes
        if(!empty($this->filterPrefix)) $tid = $this->filterPrefix . '-' . $tid;
        $fname = $ast_parm['flt_name']; # field name to reset filter
        if(isset($_SESSION[$tid][$fname])) unset($_SESSION[$tid][$fname]);
      }
      break;

    case 'viewrecord': # ajax request for the record
      echo "debug return"; exit;
      $this->PerformViewRecord();
      break;

    case 'check_loginunique': # 'login' field changed: check if value is unique

      $loc_str = array(
          'err_login_wrong_chars' => 'Login has wrong chars'
          ,'err_login_used' => 'This login already used'
          ,'new_login_is_ok' => 'This login can be used'
      );
      if (is_callable('WebApp::getLocalized')) {
            foreach($loc_str as $msg_id => &$val) {
                  if ($strk=WebApp::getLocalized($msg_id)) $val = $strk;
          }
      }

      $field = isset($ast_parm['fieldid']) ? $ast_parm['fieldid'] : '';
      $fval  = isset($ast_parm['fvalue']) ? $ast_parm['fvalue'] : '';
      $recid = isset($ast_parm['record']) ? $ast_parm['record'] : 0;
      $where = "$field='$fval'" . ($recid>0 ? " AND $this->_pkfield <> $recid":'');
      # WriteDebugInfo('обработка check_loginunique, where=', $field);

      $result = preg_match("/^[a-z0-9_@.\-]*$/i", $fval);
      if(!$result) $ret =  $loc_str['err_login_wrong_chars']; # "Значение логина содержит недопустимые символы !";
      else {
          $ret = '';
          if (!empty(Astedit::$checkLoginFunc) && is_callable(Astedit::$checkLoginFunc)) {
              # check login uniquness by user function
              $exists = call_user_func(Astedit::$checkLoginFunc, $fval);
              if ($exists) $ret = $loc_str['err_login_used'] . (is_string($exists) ? " $exists" : '');
          }
          else {
              if(class_exists('CAuthCorp')) {
                  $auth = CAuthCorp::getInstance();
                  if($auth->IsBuiltinAccount(strtolower($fval))) $ret = $loc_str['err_login_used']; #'Выбранный логин запрещен !';
              }
              if($ret ==='') {
                  $cnt = Astedit::$db->getQueryResult($this->id, 'COUNT(1)', $where);
                  $ret = ($cnt>0) ? $loc_str['err_login_used'] : $loc_str['new_login_is_ok'];
                  # "Login alray used!" : "Login can be used";
              }
          }
      }
      exit( EncodeResponseData("1\thtml\fchk_{$this->id}_{$field}\f".$ret));

    } #<3> switch
    if($showbrowse) { #<2>
      if(empty($ast_browse_jsdrawn)) {
        $ast_browse_jsdrawn = true;
        $cdel = astedit::evalValue($this->confirmdel);
        $defprompt = $ast_tips['deleteprompt'];
        $deltxt = empty($cdel) ? '': $defprompt.($cdel=='1'?'':"\\n$cdel");
        if (defined('USE_JQUERY_UI')) {
            $jsSearchHide = $jsSearchShow = '$("#img_srchhide").toggleClass("ui-icon-triangle-1-s ui-icon-triangle-1-n");';
        }
        else {
            if(!isset($imgPath)) $imgPath = 'img/';
            $jsSearchShow = "\$('#img_srchhide').attr('src','{$imgPath}minus.gif');";
            $jsSearchHide = "\$('#img_srchhide').attr('src','{$imgPath}plus.gif');";
        }
        $hidesearch = empty($_COOKIE['ast_hidesearch'])? 0 : 1;
?>
<div style="display:none"><form name="astfm" id="astfm" method="post" action="<?=$this->baseuri?>" >
<input type="hidden" id="ast_act" name="ast_act" />
<input type="hidden" id="id" name="id" />
</form></div>
<script type="text/javascript">
multilist_<?=$this->tbrowseid?> = '';
var astedit_curpageNo = [];
var lnkrow = [];
astMainId = 0;
var ast_fm = (jQuery)? $("#astfm").get(0) : document.getElementById("astfm");
var ast_delprompt = "<?=$deltxt?>";
var ast_hidesearch= <?=$hidesearch?>;
function ToggleSearchBar() {
  if(ast_hidesearch) {
    ast_hidesearch=0;
    if($.cookie) $.cookie("ast_hidesearch",0); else setCookie("ast_hidesearch",0);
    <?=$jsSearchShow?>
  }
  else {
    ast_hidesearch=1;
    if($.cookie) $.cookie("ast_hidesearch",1, { expires: 120}); else setCookie("ast_hidesearch",1,120);
    <?=$jsSearchHide?>
  }
}
function AstDoDeleteRecord() {
    $.post('<?=$this->baseuri?>',{tableid:'<?=$this->_tplfile?>',asteditajax:'delete','_astkeyvalue_':astMainId},function(data) {
        if(data=='1') window.location.href = '<?=$this->baseuri?>'; // window.location.reload();
        else showMessage('ERROR!', data, 'msg_error');
    });
}
function AstDoAction(astact,id) {
  astMainId = id;
  if(astact=='delete') {
    if(ast_delprompt!="") dlgConfirm(ast_delprompt, AstDoDeleteRecord);
    else AstDoDeleteRecord();
    return;
  }
  <?php
  $sess_id = substr(session_id(),-8);
  if($this->_edit_jscode) {
      echo str_replace('{id}','id', $this->_edit_jscode);
  }
  elseif($this->windowededit) { # open separate window with editing fullform
    echo "  if(astact=='edit' || astact=='add') {
    var simg=window.open(\"{$this->baseuri}&ast_act=\"+astact+\"&_astkeyvalue_=\"+id, \"_astedit{$this->id}{$sess_id}\",\"height={$this->windowededit['height']},width={$this->windowededit['width']},location=0,menubar=1,resizable=1,scrollbars=1,status=0,toolbar=0,top={$this->windowededit['top']},left={$this->windowededit['left']}\");
    simg.focus(); return false;
}";
   }
   else echo "ast_fm.ast_act.value=astact; ast_fm.id.value=id; ast_fm.submit();";
?>
}
<?php if($this->_viewmode) {
    echo <<< EOCODE
function astViewRecord(id) {
  $.post("$this->baseuri>",{tableid:"$this->id",asteditajax:"viewrecord",_astkeyvalue_:id},function(data){
    showMessage("Содержимое записи", data);
  });
}
EOCODE;
}

?>
function AsteditSetPage(id,nom,maxpage) {
  elid = 'lnkrow_'+id+'_'+lnkrow[id];
  nextn = lnkrow[id] + nom;
  if(nextn>=0 && nextn<maxpage) {
    window.document.getElementById('lnkrow_'+id+'_'+lnkrow[id]).style.display='none';
    lnkrow[id] = nextn;
    window.document.getElementById('lnkrow_'+id+'_'+nextn).style.display='';
  }
  return false;
}
function PromptDeleteRecord(addtxt) {
   return confirm("<?=$ast_tips['deleteprompt']?>\n"+addtxt);
}
function EvtclickMulti<?=$this->tbrowseid?>(obj) {
   chk = obj.checked;
   multilist_<?=$this->tbrowseid?> = '';
   $("input[id=chk_<?=$this->tbrowseid?>]").each(function() {
       this.checked = chk;
       if(chk) multilist_<?=$this->tbrowseid?> += (multilist_<?=$this->tbrowseid?>?',':'')+this.value;
   });
   <?=$this->_multiselectFunc?>(multilist_<?=$this->tbrowseid?>); // user function to show/hide spec.controls for selected rows
}
function selRow<?=$this->tbrowseid?>(obj) {
   multilist_<?=$this->tbrowseid?> = '';
   $("input[id=chk_<?=$this->tbrowseid?>]").each(function() {
       if(this.checked)  multilist_<?=$this->tbrowseid?> += (multilist_<?=$this->tbrowseid?>?',':'')+this.value;
   });
   <?=$this->_multiselectFunc?>(multilist_<?=$this->tbrowseid?>); // user function to show/hide spec.controls for selected rows
}

<?php if(!empty($this->_udf_js)) echo $this->_udf_js; ?>
</script>
<?php }
   $this->updateFilters();
   if($this->ajaxmode) $this->PrintAjaxFunctions(); # ex. global Astedit_PrintAjaxFunctions
   $this->DrawSearchBar(); # search bar - filter & search forms

   if(!empty($res_text) && $this->updateResult === 'error') {
       Astedit::showError($res_text);
       # echo "<br>$res_text<br>";
   }
       # HERE draw all needed JS code !
       $this->DrawBrowsePage();
    } #<2>
     if (astedit::$time_monitoring) {
         $elapsed = microtime() - $this->strt_time;
         # $elapsed = microtime() - $this->strt_time;
         WriteDebugInfo($this->id . ':MainProcess end, mktime:' . microtime() . " elapsed: ",$elapsed);
     }

  } # MainProcess() end

  public function IsTableExist() {

     return Astedit::$db->IsTableExist($this->id);
  }
  # Setting js code that will be called at "add/edit/delete" event
  function SetEditJsCode($strcode) { $this->_edit_jscode = $strcode; }

  function updateFilters() {
     global $ast_parm;
     if(isset($ast_parm['ast_act']) && 'setfilter'==$ast_parm['ast_act']) {
        $fltname= $ast_parm['flt_name'];
        if(count($this->reset_chain)) foreach($this->reset_chain as $reseter) {
           $sbros = false;
           foreach($reseter as $fldname) {
               if($fltname == $fldname) { $sbros = true; continue; }
               if($sbros) {
                   $tid = 'flt_'.$this->tbrowseid;
                   if(!empty($this->filterPrefix)) $tid = $this->filterPrefix . '-' . $tid;

                   unset($_SESSION[$tid][$fldname]);
               }
           }
        }
     }

  }
  function SetUserFilter($filterval = '') {
    $this->userfilter = $filterval;
  }
  function SetFieldAttribs($fldname,$prm1,$edit='',$newval='') {
  # override fields attributes
    $fld = strtolower($fldname);
    if(!isset($this->fields[$fldname])) return false;
    if(strtolower(get_class($prm1))=='cfielddefinition') $this->fields[$fldno] = $prm1;
    else {
      $this->fields[$fldno]->showcond=$prm1;
      if($edit !=='') $this->fields[$fldname]->editcond=$edit;
      if($newval !=='') $this->fields[$fldname]->defvalue=$newval;
    }
  }
  /**
  * Deleting record
  *
  * @param mixed $id record primary key value
  */
  function DeleteRecord($id) {# <DeleteRecord>
    global $ast_act, $ast_tips;
    if (Astedit::$RO_mode || !empty($_SESSION['astedit_readonly'])) { return $ast_tips['err_readonly_mode']; }

    $ret = false;
    $del_warning = array();

    if(!empty($this->beforedelete)) {
         if(is_callable($this->beforedelete)) { # check/modify edited fields or do something else before saving
            $result = call_user_func($this->beforedelete, $id); #
            if (!$result) return false;
         }
         # else WriteDebugInfo("undefined callback for beforedelete: ", $this->beforedelete);
    }

    if(count($this->childtables)>0) { #<4>

      $del_sql = array(); # fill with "DELETE FROM <child_table> ..." operators for each child table
      $thisrec = Astedit::$db->select($this->id,['where' =>$this->pkEqExpression($id),'singlerow'=>1]);
      if ( is_array($thisrec) ) { #<5>
        foreach($this->childtables as $onet){ #<6>
          $whcond = [];
          $fi1 = preg_split('/[; ,]/',$onet['field']);
          $fi2 = preg_split('/[; ,]/',$onet['childfield']);
          for($kkk=0;$kkk<min(count($fi1),count($fi2));$kkk++) {
            if(!empty($thisrec[$fi1[$kkk]])) {
                $whcond[$fi2[$kkk] ] = $thisrec[$fi1[$kkk]];
            }
          }

          if(!empty($onet['condition'])) $whcond[] = $onet['condition'];
          # TODO - handle grand-children record deletion ???
          # $parval = isset($askresult[$parentfld]) ? $askresult[$parentfld] : '';
          if(!empty($onet['protect'])) {
              $cnt = Astedit::$db->GetQueryResult($onet['table'],'COUNT(1)',$whcond);
              # writeDebugInfo("protect SQL: [$cnt] ", Astedit::$db->getLastQuery());
              if($cnt>0) {
                  $del_warning[] = intval($onet['protect']) ? (self::localize('err_record_cant_be_deleted') . $onet['table']):$onet['protect'];
              }
          }
          else $del_sql[] = ['table' => $onet['table'], 'cond' => $whcond ];
        }

        if(count($del_warning)) {
            $del_warning = implode('<br>', $del_warning);
            if(!empty($ast_parm['ast_ajaxmode']) || isAjaxCall()) { $this->SendAjaxResponse($del_warning); exit; }
            else return $del_warning;
        }
        if(count($del_sql))
           foreach($del_sql as $item) {
               $deleted = Astedit::$db->delete($item['table'], $item['cond']);
               # writeDebugInfo("deleted $deleted records in child $item[table] by SQL: ", Astedit::$db->getLastQuery());
           } # drop records from ALL child tables done.
      } #<5>
    } #<4>
    if(is_object($this->_picobj)) $this->_picobj->DropFiles($id); # delete image files and records from

    # deleting uploaed file(s) if attached to the record
    if (count($del_warning)==0 && count($this->_savefile_pm) >0) {
        foreach($this->_savefile_pm as $fldname => $svdata) {
            # if UDF used for file saving, use it for file removing too...
            if(!empty($svdata[2]) and is_callable($svdata[2])) call_user_func($svdata[2],$id,false); # Save original file name "myfile.png"
            else $this->dropUploaded($id, $fldname);
        }
    }

    # $where = $this->pkEqExpression($id);  $sqry = "DELETE FROM {$this->id} WHERE $where";
    $whereExp = $this->pkEqExpression($id);
    if (!empty($whereExp) || (is_array($whereExp) && count($whereExp)>0)) {
        Astedit::$db->delete($this->id, $whereExp);
        if(Astedit::$db->sql_errno()) $ret = $ast_tips['rec_delerror'];
        else $ret = '1';
    }
    else $ret = 'Error - no key field in table';

    if($ret=='1') {
        if($this->_auditing) @call_user_func($this->_auditing,$this->id,'delete',$id);
        if(!empty($this->afterupdate) && is_callable($this->afterupdate))
          @call_user_func($this->afterupdate,'delete', $id, Astedit::$db->sql_errno());
    }

    if(!empty($ast_parm['ast_ajaxmode']) or isAjaxCall()) $this->SendAjaxResponse($ret);
    return $ret;
  } # <DeleteRecord>
  function PerformViewRecord($parms) {
    if(function_exists("AstGenerateView")) AstGenerateView();
    else echo "no AstGenerateView function for {$this->id}";
    exit;
  }
  function SendAjaxResponse($msg) {
    $msg = EncodeResponseData($msg);
    echo $msg;
    exit;
  }
 function DrawPageLinks() {
  global $as_cssclass;
  if(empty($this->pagelinks)) return;
  $flt = $this->PrepareFilter();

  $page = $_SESSION[$this->filterPrefix.$this->tbrowseid]['page'] ?? 0;
  $lnkrow = floor($page/$this->pagelinksinrow); // current visible row in page-hrefs
  $nrows=Astedit::$db->select($this->id, array('fields'=>'count(1) cnt','where'=>$flt,'singlerow'=>1));

  if(!$nrows) return 0;
  $nrows=$nrows['cnt'];
  if(empty($this->rpp)) $this->rpp = 20;
?>
<script type="text/javascript">
 lnkrow['<?=$this->tbrowseid?>'] = <?=$lnkrow?>;
 curpage = <?=$page?>;
</script>
<?php
  $pages=ceil($nrows/$this->rpp); // pages count

  if(($page >= $pages) && ($page > 0))
  { // if somehow pages count becomes low, correct cur.page number !
    $page = max(0,$pages-1);
    $_SESSION[$this->filterPrefix.$this->tbrowseid]['page'] = $page;
  }
  $retcode = '';
  if($pages<=1) return $pages;
  $lnkpages = floor($pages/$this->pagelinksinrow)+1;
  // $whoref = $this->baseuri.'&astpage';
  $retcode.="<script type='text/javascript'>astedit_curpageNo['{$this->tbrowseid}'] = $page; </script>\n";
  $retcode.="<div align='center' class='mt-2'><table><tr><td>";
  if($this->pagelinks>1 && $lnkpages>1) $retcode.="<td class='custom-page-link {$as_cssclass['pagelnk']}'><a id='{$this->tbrowseid}1' href='javascript://' onClick='AsteditSetPage(\"{$this->tbrowseid}\",-1,$lnkpages)'>&lt;&lt;</td>";
  // $stepleft = $page-1;
  // $stepright = $page+1;
  // $icol = 0; $i=1;
  // $lnrow = 0;
  if($this->pagelinks>1) { //<3>
   for($lrow=0; $lrow<$lnkpages; $lrow++) { #<4>//while($i<=$pages) {
    $style = $lnkrow==$lrow ? '': "style='display:none'";
    $retcode.="<td id='lnkrow_{$this->tbrowseid}_{$lrow}' $style><table border=0 cellspacing=1 cellpadding=0><tr>";
    for($iii=0;$iii<$this->pagelinksinrow; $iii++) {
      $pgno = $lrow*$this->pagelinksinrow + $iii;
      $pgno1 = $pgno+1;
      if($pgno>=$pages) break; //echo "<td class='{$as_cssclass['pagelnk']}'> &nbsp; </td>";
      $cls = ($pgno==$page)? $as_cssclass['pagelnka']: $as_cssclass['pagelnk'];
      $jspgref = ($this->ajaxmode)? "AsteditSetCurPage(\"{$this->tbrowseid}\",$pgno)": "window.location=\"{$this->baseuri}astpage=$pgno\"";
      // $pghref = "href='javascript://' onClick='$jspgref'";
      $retcode.="<td class='custom-page-link $cls' id='astlnkpg_{$this->tbrowseid}_{$pgno}' onClick='$jspgref' > $pgno1 </td>";
    }
    $retcode.="</tr></table></td>\n";
   } #<4>
  } //<3>
  elseif($this->pagelinks==1){ # simple links: 1,2,3,4,5 >> end
    $lnlist = array(0);
    for($kk=max($page - $this->_adjacentLinks,1);$kk<=min($page + $this->_adjacentLinks,$pages-1);$kk++) $lnlist[] = $kk;
    if(!in_array(($pages-1),$lnlist)) $lnlist[] = $pages-1; # last page
    for($kk=0;$kk<count($lnlist);$kk++) {
      $pgno=$lnlist[$kk];
      $pgno1=$pgno+1;
      $cls = ($pgno==$page)? $as_cssclass['pagelnka']: $as_cssclass['pagelnk'];
      $jspgref = ($this->ajaxmode)? "AsteditSetCurPage(\"{$this->tbrowseid}\",$pgno)": "window.location=\"{$this->baseuri}astpage=$pgno\"";
      // $pghref = "href='javascript://' onClick='$jspgref'";
      if($kk>0 && $lnlist[$kk-1]+1 != $pgno) $retcode.="<td> &nbsp; ... &nbsp; </td>";
      $retcode.="<td class='custom-page-link $cls' id='astlnkpg_{$this->tbrowseid}_{$pgno}' onClick='$jspgref' style='cursor:hand'> $pgno1 </td>";
    }
  }else {
    $retcode.="<td class='{$as_cssclass['pagelnka']}'> $page </td>";
  }
  $retcode.="</td>";
  if($this->pagelinks>1 && $lnkpages>1) $retcode.="<td class='{$as_cssclass['pagelnk']}'><a id='{$this->tbrowseid}2' href='javascript://' onClick='AsteditSetPage(\"{$this->tbrowseid}\",1,$lnkpages)'>&gt;&gt;</td>";
  $retcode.="</tr></table></div>";
  echo $retcode; # TODO: buffered mode
  return $pages;
 }

  function CreateTableNow(){   return $this->CreateTable(true);  }
  function SetBrowseId($strk) { $this->tbrowseid = $strk; }

  /**
  * Explicitly sets field list to view in the data grid
  *
  * @param mixed $ar_fields
  */
  function SetView($ar_fields) {
    $this->viewfields = is_string($ar_fields)? preg_split("/[\s,;|]+/",$ar_fields): $ar_fields;
    foreach($this->viewfields as $fid) {
        if(isset($this->fields[$fid]))
          $this->fields[$fid]->showcond = in_array($this->fields[$fid]->showcond,array('1','S'))? $this->fields[$fid]->showcond : 1;
    }
  }
  /**
  * @desc CloneRecord - clones record with passed id, return new record's id
  * @param int/string $record_id - source record id (PK value)
  */
  function CloneRecord($record_id) {
    global $ast_tips, $ast_parm;
    if (Astedit::$RO_mode) { return $ast_tips['err_readonly_mode']; }

    $ret = 0;
    if(count($this->_pkfields)<1) return 0;
    $nevals = isset($ast_parm['newvals']) ? $ast_parm['newvals'] : '';
    $cloneChild = !empty($ast_parm['clonechild']);

    $dta = Astedit::$db->GetQueryResult($this->id,'*',$this->pkEqExpression($record_id),false, true);
    if(Astedit::$db->affected_rows()<1) return 0;

    foreach($this->_pkfields as $pkf) {
      if($this->fields[$pkf]->_autoinc) unset($dta[$pkf]);
    }
    if ($nevals == '')  $newArr = [''];
    else $newArr = preg_split( '/[,;\|]/', $nevals, -1, PREG_SPLIT_NO_EMPTY );

    foreach($newArr as $itemNo => $newval) {
        if(!empty($this->clonable_field)) {
            if (!empty($newval))
                $dta[$this->clonable_field] = $newval;
            else {
                $dta[$this->clonable_field] .= '_clone' . (($itemNo>0) ? $itemNo : '');
            }
        }
    #    echo '</pre>';
        Astedit::$db->insert($this->id,$dta);
        if(Astedit::$db->affected_rows()==1) $ret = Astedit::$db->insert_id();

        # if there are child tables, clone related records (if user confirmed)
        if(($ret) && $cloneChild && count($this->clone_subs)>0 ) { #<2>
          # foreach($this->fields as $ofld) { if($ofld->_autoinc) $dta[$ofld->id]=$record_id; }
    #      $dta[$this->_pkfield]=$record_id; # restore value for child tables processing
          foreach($this->clone_subs as $tblname => $fkfield) { #<3>

            # $parr  = preg_split('/[,; ]/',$child['field']); # PK fields in this(parent table)
            # $charr = preg_split('/[,; ]/',$child['childfield']); # FK fileds in child table
            $childCond = [$fkfield => $record_id];
            # for($kc=0;$kc<count($parr);$kc++) $whcond .= ' AND '.$charr[$kc]."='".$dta[$parr[$kc]]."'";
            $pkname = Astedit::$db->GetPrimaryKeyField($tblname);
            $chrecs = Astedit::$db->select($tblname, ['where'=>$childCond, 'orderby'=>$pkname]);
            if (is_array($chrecs)) foreach($chrecs as $rchild) {
              if(!empty($pkname)) unset($rchild[$pkname]);
              $rchild[$fkfield] = $ret; # new parent record ID
              /*
              for($kc=0;$kc<count($parr);$kc++) {
                  $rchild[$charr[$kc]]=$dta[$parr[$kc]]; # all Foreign key-fields values
              }
              */
              astedit::$db->insert($tblname,$rchild);
            } #<4>
          } #<3>
        } #<2>
    }
    return $ret;
  }
  function SetMultiPart($parm=true) { $this->_multipart = $parm; }
  /**
  * @desc registering converter function, that is used to get real condition from flt_ value for a field
  */
  function RegisterFilterConvertor($fieldname,$funcname) {
    if(!empty($funcname)) $this->_converters[$fieldname] = $funcname;
    else unset($this->_converters[$fieldname]);
  }
  /**
  * @desc convert all definition strings to desired charset
  */
  function ConvertCharSet($csetto) {
      # writeDebugInfo("ConvertCharSet from [$this->charset] to [$csetto]");
      mb_convert_variables($csetto,$this->charset,$this);
      $this->charset = $csetto;
  }
  function AllPkFields() {
    $ret = '';
    return implode(',',$this->_pkfields);
  }

    public function PrintAjaxFunctions($nojstag=false,$to_var=false) {
      global $self, $ast_ajaxfnc_drawn, $as_cssclass;
      if(!empty($ast_ajaxfnc_drawn)) return;
      $ast_ajaxfnc_drawn = true;
      $self = $_SERVER['PHP_SELF'];
      $ret = ($nojstag)? '' : "<script type=\"text/javascript\">\n";
      $delim1 = ASTDELIMITER1;
      $delim2 = ASTDELIMITER2;

      $ret .= <<<EOJS
//- js function for working in AJAX mode

    function AsteditSetCurPage(tableid, pageno) {
      var params = {asteditajax:"setpage", 'tableid':tableid, astpage:pageno};
      $.post("{$this->baseuri}",params,function(data) {
        if(xmlreq.responseText=='{refresh}') { window.location.reload(); return; }
        var brobj=document.getElementById('astbrowse_'+tableid);
        if(typeof(brobj)=='object') { $('#astbrowse_'+tableid).html(response); }
        if(astedit_curpageNo[tableid]!=undefined)
          document.getElementById('astlnkpg_'+tableid+'_'+astedit_curpageNo[tableid]).className='{$as_cssclass['pagelnk']}';
        var tlnk=document.getElementById('astlnkpg_'+tableid+'_'+pageno);
        if(typeof(tlnk)=='object') tlnk.className = '{$as_cssclass['pagelnka']}';
        astedit_curpageNo[tableid]=pageno;
        // TODO: switch 'class' on page links !
      });
      return false;
    }
    function AsteditSetFilter(tableid,flt_id,flt_val) {
      alert('AstSetFilter call: '+tableid+'/'+flt_id+'/'+flt_val);
      return false;
    }
    _ast_formobj = false;
    function AsteditInitFormValues(tableid,pkvalue) {
      var _ast_formobj= (jQuery)? $("form[name=astedit_"+tableid+"]").get(0) : asGetObj('astedit_'+tableid);
      if(typeof(_ast_formobj)=='undefined') {
        _ast_formobj= (jQuery)? $("form[name="+tableid+"]").get(0) : asGetObj(tableid);
      }
      var params = {'asteditajax':'initvalues','tableid':tableid, '_astkeyvalue_':pkvalue};
      jQuery.post('{$this->baseuri}',params,function(data) {
         var spt = data.split("\t");
         if (spt[0]==='1') handleResponseData(data,1);
      });
      return false;
    }
EOJS;
       if(!$nojstag) $ret .= "</script>\n";
       if($to_var) return $ret;
       echo $ret;
    }

    # Append some buttons or whatelse HTML code to toolbar below the grid
    public function appendToolbarCode($htmlCode) {
        $this->toolBar[] = $htmlCode;
    }
#   function SetAjaxedInit($bvalue=true) { $this->_ajaxedinit = $bvalue; }
} # CTableDefinition class definition end

function BrShortText($txt, $maxlen=40) {
    return Astedit::BrShortText($txt, $maxlen);
}


function IsTextType($ftype) {
  $flow = strtolower($ftype);
  return in_array($flow,array('text','tinytext','mediumtext','longtext','char','varchar','blist','blistext','blistlink'));
}

function IsIntType($ftype) {
  $flow = strtolower($ftype);
  return in_array($flow,array('bigint','int','uint','tinyint','smallint','mediumint','integer','bit','bool'));
}

function IsNumberType($ftype, $withint=true) {
  if(($withint) && IsIntType($ftype)) return true;
  $flow = strtolower($ftype);
  return in_array($flow,array('real','float','double','decimal','dec','numeric'));
}

function DrawWysiwigToolbar($obj) {
  $pth = IMGPATH;
  if($obj->wwtoolbar_ready) return '';
  $obj->wwtoolbar_ready = 1;
  return
"<table id='WysiwigToolbar' border=0 cellspacing=0 cellpadding=0><tr>
<td><input type=image src='{$pth}bteBold.png' onClick=\"document.execCommand('Bold');return false;\" /></td>

<td><input type=image src='{$pth}bteItalic.png' onClick=\"document.execCommand('Italic');return false;\" /></td>

<td><input type=image src='{$pth}bteJustleft.png' onClick=\"document.execCommand('JustifyLeft');return false;\"></td>

<td><input type=image src='{$pth}bteJustcenter.png' onClick=\"document.execCommand('JustifyCenter');return false;\"></td>
<td><input type=image src='{$pth}bteJustright.png' onClick=\"document.execCommand('JustifyRight');return false;\"></td>

<td><input type=image src='{$pth}bteHref.png' onClick=\"return MakeHref();\"></td>
<!-- <td><input type=image src='{$pth}bteImage.png' onClick=\"return MakeImage();\"> img: <input type=text id='img_name' style='width:80px'>
width:<input type=text id='img_w' style='width:24px' />
height:<input type=text id='img_h' style='width:24px' />
</td>
-->
</tr><tr><td><img height=2 /></td></tr></table>";
}

function AsteditRegisterTable($tid, $tablename) {
    global $ast_ajaxtables;
    $ast_ajaxtables[$tid]=$tablename;
}
# AsteditAjaxCalls(): call this func after session_start() but before any HTML output in your ajax-based module
function AsteditAjaxCalls() {
  global $ast_ajaxtables, $ast_parm, $ast_tips;

  $ret = "0\x04Wrong ajax call";
  if(empty($ast_parm)) $ast_parm = DecodePostData(1);
  if ( !empty($ast_parm['asteditajax']) ) { #<2>
    $tblid = isset($ast_parm['tableid'])?$ast_parm['tableid']:'';
    if(empty($tblid)) {
      exit('astedit/ajax error: no tableid passed');
    }

    $tblname = empty($ast_ajaxtables[$tblid])? $tblid : $ast_ajaxtables[$tblid];
    $ajxtbl = new CTableDefinition($tblname);

    if(empty($ajxtbl->id)) {
        # WriteDebugInfo('AsteditAjaxCalls: table def not found:',$tblname);
        return;
    }
    $action = isset($ast_parm['ast_act']) ? $ast_parm['ast_act'] : (isset($ast_parm['asteditajax'])?$ast_parm['asteditajax']:'not-set!');
    switch($action) {
     case 'do_edit':
      $recid = isset($ast_parm['id']) ? $ast_parm['id'] : 0;
      $this->UpdateDataIntoTable($action,$recid,$ast_parm);
      break;
     case 'setpage':
      $tpage = isset($ast_parm['astpage'])?$ast_parm['astpage']:0;
      if(function_exists('astedit_adjustview')) astedit_adjustview($ajxtbl); # adjust showing rules
      $_SESSION[$ajxtbl->filterPrefix.$ajxtbl->tbrowseid]['page'] = $tpage;
      $ret = $ajxtbl->DrawBrowsePage(1);
      break;
      # STOP HERE!
    case 'initvalues': # send initial values for the table (onLoad AJAX call to fill all form controls)
      $recid = isset($ast_parm['_astkeyvalue_'])? $ast_parm['_astkeyvalue_']: 0;
      $ret = '';
      if(empty($recid)) {
        $vals = array();
        foreach($ajxtbl->fields as $fldid=>$fld) {
          $vals[$fldid] = $fld->GetDefaultValue();
        }
      }
      else {
        $vals = Astedit::$db->GetQueryResult($tblname,'*',$ajxtbl->pkEqExpression($recid),0,true);
      }
      $ret = '1';
      if(is_array($vals)) foreach($vals as $vkey=>$vvalue) {
          if(strtoupper($ajxtbl->fields[$vkey]->type)==='DATE') {
             if (intval($vvalue)>0) $vvalue=to_char($vvalue);
             else $vvalue = ''; # dont send '0000-00-00'!
          }
          if(in_array($vkey,$ajxtbl->_pkfields)) continue;

          list($edttype) = explode(',', $ajxtbl->fields[$vkey]->edittype);

          if (in_array($edttype, ['BLIST','BLISTLINK'])) { # make BLIST checkboxes checked

              if ($vvalue!=='') {
                  $splt = explode(',', $vvalue);
                  foreach ($splt as $oneval) {
                      $fldid = '_tmp_'.$vkey.'_'.$oneval;
                      $ret .= ASTDELIMITER1 . 'set' . ASTDELIMITER2 . $fldid . ASTDELIMITER2. '1';
                  }
              }
          }
          elseif ($edttype === 'BLISTEXT') { # make "id1:40,id2:50,..." BLISTEXT checkboxes checked, and numeric values
              if ($vvalue!=='') {
                  $splt = explode(',', $vvalue);
                  foreach ($splt as $oneval) {
                      $sp2 = explode(':',$oneval);
                      $itemid = $sp2[0];
                      $itemval = isset($sp2[1])? $sp2[1] : '';
                      $fldid = '_tmp_'.$vkey.'_'.$itemid;
                      $fldid2 = $fldid . '_extval_';
                      $ret .= ASTDELIMITER1 . 'set' . ASTDELIMITER2 . $fldid . ASTDELIMITER2. '1';
                      $ret .= ASTDELIMITER1 . 'set' . ASTDELIMITER2 . $fldid2 . ASTDELIMITER2. $itemval;
                  }
              }
          }
          else {
              $ret .= ASTDELIMITER1 . 'set' . ASTDELIMITER2 . $vkey . ASTDELIMITER2.$vvalue; # for handleResponseData js function
          }
      }
      else $ret = '__nodata__'.ASTDELIMITER2.'0';
      break;

    case 'viewrecord':
      if (!empty(Astedit::$viewRecordCallback) && is_callable(Astedit::$viewRecordCallback)) {
          $callBack = Astedit::$viewRecordCallback;
          $ret = call_user_func($callBack, $tblid);
      }
      elseif(function_exists('AstGenerateView')) $ret = AstGenerateView();
      else {
          $ret = Astedit::defaltRecView(); # "ajax return / no function for viewing record<pre>".print_r($pars,1).'</pre>';
      }
      break;

    case 'delete':
      if (Astedit::$RO_mode) { return $ast_tips['err_readonly_mode']; }
      $recid = isset($ast_parm['_astkeyvalue_'])? $ast_parm['_astkeyvalue_']: 0;
      if($recid) {
          if($ajxtbl->recdeletefunc && is_callable($ajxtbl->recdeletefunc)) $ret = call_user_func($ajxtbl->recdeletefunc,$recid);
          else $ret = $ajxtbl->DeleteRecord($recid) ;
      }
      else $ret = 'Wrong Call, empty ID passed';
      if(!$ret) $ret = '1'; # flag "OK, deleted"
      break;

    case 'showhelp':

      $pageFile = $ajxtbl->helppage;
      if ($pageFile == 1) $pageFile = $ajxtbl->tbrowseid . '.help';
      $helpFile = dirname($ajxtbl->_tplfile). "/$pageFile.htm";
      if (is_file($helpFile)) {
          exit(file_get_contents($helpFile));
      }
      else exit($ast_tips['help_page_notfound']);
      break;
   }
   # $ret = EncodeResponseData($ret); # basefunc.php must be included for this func!
   if(defined('MAINCHARSETCL') && constant('MAINCHARSETCL')!='') @header("Content-Type: text/html; charset=".MAINCHARSETCL);
   die($ret);
  } #<2>
}
