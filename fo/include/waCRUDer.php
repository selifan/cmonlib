<?PHP
/**
* @package waCRUDer
* CRUD operations over table based on it's definition
* @author Alexander Selifonov, < alex [at] selifan dot ru >
* @link http://www.selifan.ru
* @Version 0.01.001
* modified 2016-08-11
*/
// interface for reading table structure from definition file
interface WaCRUDerRead {
    public function read($filename, $options=false);
    public function getFileExt(); // should return correct file extension, usually it's equal to "type"
}

abstract class WaCRUDerReadDef implements WaCRUDerRead {
    var $id = '';
    var $fields = array();
}
// One field full definition
class waFieldDef { // ex-CFieldDefinition
    public $id = ''; # field "id" or var_name for this field in php form blocks
    public $desc = ''; # long description
    public $shortdesc = '' ; # short description (browse header)
    public $name = ''; # field name
    public $type = '';
    public $length = '';
    public $notnull = '';
    public $defvalue = ''; # DEFAULT value for field (for SQL CREATE TABLE operator) DEFAULT[,new_formula]
    public $showcond = ''; # show in browse if condition evals nonempty
    public $showformula = ''; # convert value with eval(this formula)
    public $showattr = ['','','','']; # align, color, bgcolor,class - additional attribs for feild in gread: |@showformula[,align[,color...]]|...
    public $showhref = ''; # 14-th field - HREF with {ID} - makes linked href from browse page
    public $hrefaddon = '';# additional HTML tags for editing-mode : onClick="...." title="..."
    public $editcond = ''; # "editable"  condition for field, 'C'- auto-create for New records
    public $edittype = ''; # in what form edit: checkbox, listbox... + formula for options
    public $editoptions = '';
    public $inputtags = ''; # some additional tags in <input ...> tag, i.e. onChange='myfunc()'
    public $idx_name  = ''; # if field must be indexed, here is the index's unique name
    public $idx_unique = 0; # non-empty value if index is unique
    public $derived = 0; # this field came from Parent table
    public $_autoinc = false; # auto-incremented field
    public $afterinputcode = ''; # additional html code after input field (some buttons with JS code etc...)
    public $imgatt = '';
    public $subtype = false;

}
class WaIndexDef
{ # one table field definition
    public $name = ''; # index name
    public $expr = ''; # field list, "field1,field2,..."
    public $unique = 0 ; # Uniqueness
    public $descending = 0; # 1 if DESCENDING order (MySQL - no support ?)
    public $derived = 0;
} # WaIndexDef class end

// CRUD Page renderer
abstract class WaCRUDerRender {

    protected $_crudObj = false; // WaTableDef instance
    protected $_buffered = false;
    abstract public function go($options=null);
    public function __construct($tobj) {
        $this->_crudObj = $tobj;
    }
}
// all table parameters
class WaTableDef {
    static $default_rpp = 20; // default rows per page value
    static $default_adjlinks = 4; // default adjacent links amt in pagination block
    public $id = ''; # Table ID/name
    public $_tplfile = ''; # loaded definition filename (w/o .tpl)
    public $debug = 0;
    public $filename = ''; # file name this def came from (and save to)
    public $charset = '';
    public $collate = '';
    public $desc = ''; # Table long title
    public $shortdesc = ''; # short title for table
    public $browsefilter = array(); # filter for browse mode
    public $browsefilter_fn = ''; # filter as User Defined (Dynamic) function
    public $childtables = array(); # список связей с дочерними таблицами([0]-table, [1]-fld,[2]-child fld,[3 - add.condition]
    public $childtables_fn = '';
    public $browsewidth = ''; # browsing screen width, value for <table width=NNN[%]>
    public $browseheight = ''; # if set to some 'XXXpx', scrolling style will be used
    public $blist_height = '100px'; # BLIST area max height, width
    public $fullBlistForm = TRUE; #  true = draw BLIST items with ID "[x] ID-title"
    public $blist_width = '99.9%';
    public $_pkfield = ''; # Primary Key field name(identified if there is PK-PKA field def
    public $_pkfields = []; # array with all fields defined as PRIMARY KEY
    public $_pkflistset = false;
    public $rpp = 20 ; # rows per page - browse limit
    public $pagelinks = 1; # maximal - show ALL pages HREFS, 0-no href, 1-only previous and next
    public $editformwidth = '100%';
    public $pagelinksinrow = 25; # how many page links in one row (pages list in the bottom)
    public $browseorder = ''; # order expression for browsing
    public $userfilter = '';  # You can add your browse conditions
    public $search = ''; # search list (comma separated) - to draw & handle search form
    public $searchcols = 2; # columns per row in the search bar
    public $bwtextfields = 0; # show text fields mode in browse: 0 - normal, >=1 - TEXTAREA (readonly) (nn of rows)
    public $groupby = ''; # field1,field2,...| TODO!
    public $recursive = ''; # field name that is 'parent record's id in the same table
    public $recursive_show = ''; # field name where we draw PADDING chars/pics, to inform about nesting level
    public $sumfields = ''; # SUM(data1),AVG(data2),... amust if groupby used !
    # buttons for Edit, delete & add actions on browse page
    public $tabletype = ''; // myisam etc.
    public $_addcol = 1; # columns in browse table, to show correct placed "ADD" button
    public $brenderfunc = ''; # if set, this func wil be called to render browse row
    public $beforeedit = ''; # call this function on source field values before editing
    protected $beforedelete = ''; # call this function before Deleting recors. If this returns FALSE, deletion won't be done
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
    public $canview = 0;
    public $canedit = 0;
    public $candelete = 0;
    public $caninsert = 0;
    public $ajaxmode = 0;  # set it to 1 if You need to update browse view in AJAX manner
    public $wwtoolbar_ready = 0;
    public $tbrowseid = ''; # unique id for current table views
    public $parenttables = array(); # internal, holds parent table names list
    public $fields = array(); # field list (CFieldDefinition)
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
    public $_errormessage = ''; # internal public with last error message
    public $_halign = 'center';
    public $_drawbrheader = true; # if false, no header in browse screen will be drawn
    public $_browseheadertags = array(); # user <TD> tags for the header line
    public $_drawtdid = false; # if true, every <td> in grid will have id
    public $_auditing = false; # set to function name, to perform auditing tasks (add,update,delete operations)
    public $_browsetags = array(); # each column can have specific tags in the browse grid
    public $_frozen = false;
    public $_udf_js = '';
    public $_updt_all = false; # if true, will override false editcond field property
    public $_enablesinglequote = false;
    public $clonable = 0; # 1 - clonable (only main record), 2 - record and all child tables data
    public $clonable_field = '';
    public $_togglefilters = 1; # hideable search toolbar
    public $_viewmode = false; # in VIEW mode no edition possible, all row when clicked, opens "details" for view (func.
    public $_undo_upgrade = true; # safe structure upgrade, false - unsafe
    public $_prefixeditfield = false; # if true, add table_name to generated <input> tags for fields,
    public $_multiselect = '';
    public $_multiselectFunc = '';
    public $_adjacentLinks = 4;
    public $_edit_jscode = ''; # javascript code template to be called at 'EDIT' event
    public $reset_chain = array(); # reset current filters chains when "parent" filter is changed
    public $file_folder = ''; # Folder to store uploaded files
    public $baseuri = '';
    public $_jscode = '';
    public $onsubmit = false; # js code for "obsubmit" form tag

}
// interface for storing table structure to definition file
abstract class WaCRUDerWriteDef {
    abstract public function write($obj, $outfile, $srccset='UTF-8');
}

class WaCRUDer {

    const PREFIX_MACRO = '%tabprefix%';
    const DEFAULTDELIMS = '/[,; ]/';

    const ERR_FILE_NOT_WRITABLE = 1001;
    static $db =  NULL; # database access object
    static $tablesprefix = '';
    static $cset = null;
    private static $_structLoader = false;
    private static $_structWriter = false;
    public static $tplFolder = ['./'];
    static private $appFoldersLoaded = FALSE;
    static $folderHelpPages = 'helppages/';
    static $errList = [];
    static $defaultDefType = 'tpl';
    static $defaultRender = 'std';
    private $_tplfile = ''; # loaded definition filename (w/o tpl/xml extension)
    private $_renderobj = null; // WaCRUDrender implemented subclass object, to draw HTML code with data grid etc.
    public $def = null; // will be a object with all table definition loaded by WaCRUDerRead instance

    public static function setDefaultDefType($dtype) { self::$defaultDefType = $dtype; }
    public static function setDefaultRender($render) { self::$defaultRender = $render; }
    public $sourceFolder = ''; # folder where source template was found
    public static function addTplFolder($path) {
        if (is_scalar($path)) $path = explode(',', $path);
        if (is_array($path)) {
          foreach($path as $item) { # avoid duplicated folders
            if(!in_array($item, self::$tplFolder)) self::$tplFolder[] = $item;
          }
        }
        # self::log('all template folders:' . implode(', ', self::$tplFolder));
    }
    /**
    * Sets structure reader for specific meta-file
    *
    * @param string|object $type meta-file type. Respective module "waCRUDer.read.<type>.php" should exist!
    */
    public static function setDefReader($type) {
        if (is_object($type)) {
            self::$_structLoader = $type;
            return;
        }
        $flname = __DIR__ . '/waCRUDer.read.'.strtolower($type).'.php';
        if (!is_file($flname)) {
            self::addError('Reader class file not found : '.$flname);
            return FALSE;
        }
        $rclass = 'WaCRUDerRead'.$type;
        @include_once($flname);
        if ( class_exists($rclass) ) {
            self::$_structLoader = new $rclass;
        }
        else {
            self::log("ERROR: class $rclass NOT FOUND in $flname");
            # writeDebugInfo("ERROR: class $rclass NOT FOUND in $flname");
        }

    }

    public static function setDefWriter($type) {
        if (is_object($type)) {
            self::$_structLoader = $type;
            return;
        }
        $flname = __DIR__ . '/waCRUDer.write.'.strtolower($type).'.php';
        $wclass = 'WaCRUDerWrite'.$type;
        include_once($flname);
        if ( class_exists($wclass) ) self::$_structWriter = new $wclass;
    }

    public function __construct($tableid='', $options = false) {
        if (self::$cset === null) {
            if (defined('DEFAULT_CHARSET')) self::$cset = constant('DEFAULT_CHARSET');
            elseif (defined('MAINCHARSET')) self::$cset = constant('MAINCHARSET');
        }
        if ($tableid!='') $this->loadDefinition($tableid, $options);
    }

    /**
    * Searches definition file in all registered folders
    *
    * @param mixed $tableid table name / id / "base" filename (without extension)
    * @return full pathnema or FALSE
    */
    public function findDefinitionFile($tableid, $fext='', $pref = '') {
        writeDebugInfo("findDefinitionFile($tableid, $fext, $pref");
        $this->_tplfile = false;
        if (!$fext) $fext = self::$_structLoader->getFileExt();
        if (!self::$appFoldersLoaded && class_exists('WebApp') && isset(WebApp::$tplFolders) && is_array(WebApp::$tplFolders)) {
            self::addTplFolder(WebApp::$tplFolders);
            self::$appFoldersLoaded = TRUE; # do it once!
        }

        $basename = (($pref!='') ? "$pref.$tableid.$fext" : "$tableid.$fext");
        if( count(self::$tplFolder)>0) {
            foreach(self::$tplFolder as $tpfold) {
                if(file_exists($tpfold . $basename)) {
                    $this->sourceFolder = $tpfold;
                    return ($this->_tplfile = $tpfold . $basename);
                }
            }
        }
        return false; // no such file in registered folders
    }

    public function loadDefinition($tableid, $options = false) {
        if (!is_object(self::$_structLoader)) {
            $deftype = isset($options['deftype']) ? $options['deftype'] : self::$defaultDefType;
            self::setDefReader($deftype);
            if (!is_object(self::$_structLoader)) {
                self::addError('reader not found for '.$deftype);
                return FALSE;
            }
        }
        $prefix = method_exists(self::$_structLoader, 'getFilePrefix') ? self::$_structLoader->getFilePrefix() : '';
        $ext = self::$_structLoader->getFileExt();
        $deffile = $this->findDefinitionFile( $tableid, $ext, $prefix );
        writeDebugInfo("found template fullname: [$deffile]");
        if (!$deffile) {
            $err = "definiiton file not found for $tableid";
            self::addError($err);
            writeDebugInfo("not found template for $tableid, all folders: ", WaCRUDer::$tplFolder);
            return FALSE;
        }
        $this->def = self::$_structLoader->read($deffile, $options);
        $this->def->file_folder = dirname($deffile) . '/';
        # self::log($this->def); // debug
    }
  /**
  * @desc AddField adds one field to the structure definition
  */
    public function addField($fldid,$ftype='VARCHAR',$flen=10, $fdesc='',$sdesc='',$notnull=0,$defvalue='',
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
        $this->def->fields[$fldid] = $fld;
        if(self::evaluate($fld->showcond)) $this->def->viewfields[] = $fldid;
        return true;
    }

    public function go($options=null) {

        if (!$this->def->canview) {
            if (is_callable('WebApp::echoError')) WebApp::echoError('err-no-rights');
            die ('You have no rights to view that!');
        }
        if (!empty(self::$defaultRender)) {
            $rclass = 'WaCRUDerRender'.self::$defaultRender;
        }
        $flname = __DIR__ . '/waCRUDer.render.'.strtolower(self::$defaultRender).'.php';
        if (!is_file($flname)) die ('Render class file not found : '.$flname);
        include_once($flname);
        if ( class_exists($rclass) ) {
#            WriteDebugInfo('class is '.$rclass);
            $renderer = new $rclass ($this);
        }
        else {
            self::addError("ERROR: class $rclass NOT FOUND in $flname");
            return FALSE;
        }
        $renderer->go($options);
    }

    public static function addError($text) {
        self:$errList[] = $text;
    }
    public static function getErrorList() {
        return (implode('<br>', self::$errList));
    }
    public static function log($text) {
        $runinfo = debug_backtrace();
        $pref = str_pad(basename($runinfo[0]['file']).'['.$runinfo[0]['line'].']', 30,'_',STR_PAD_RIGHT);
        if (is_scalar($text)) echo "$pref : $text<br>";
        else echo "$pref : <pre>" . print_r($text,1) . '</pre>';
    }

    public static function evaluate($param, $par_arr=null) {
       if(empty($param)) return false;
       $ret = false;
       if('@' === substr($param,0,1)) { #<3>
          $fnc =substr($param,1);
          if(is_callable($fnc)){
              $ret = call_user_func($fnc, $par_arr);
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
          $ret = WaCRUDER::$db->sql_query($qry,true,false,true); # not assoc!
       }
       elseif('!' === substr($param,0,1)) { #<3> read option list from file
         $Lname = substr($param,1);
         $ret = array();
         if(is_readable($Lname) && ($ffh=fopen($Lname,'r'))>0) { #<4>
           while(!feof($ffh)) {
             $strk = trim(fgets($ffh));
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
    public static function utrim($par) {
        $cset = defined('DEFAULT_CHARSET') ? constant('DEFAULT_CHARSET') : '';
        if($cset=='UTF-8') return preg_replace('@^\s*|\s*$@u', '', $par);
        return trim($par);
    }
    public function setBaseUri($uri='') {
        $this->def->baseuri = ($uri ? $uri : $_SERVER['PHP_SELF']);
        $this->def->baseuri .= (strpos($this->baseuri,'?') ? '&' : '?');
    }
    /**
    * Writes table definition to the file using "active" writer module
    *
    * @param mixed $outname
    */
    public function saveDefinition($outname) {
        $ret = false;
        if (self::$_structWriter) $ret = self::$_structWriter->write($this->def, $outname);
        return $ret;
    }
    public static function toUtf($strg, $srcCharset='') {
        if ($srcCharset!='' && $srcCharset !=='UTF-8') return iconv($srcCharset, 'UTF-8',$strg);
        return $strg;
    }
/*
    public static function autoloader($type) {
        include_once( __DIR__ . '/waCRUDer.read.' . $type . '.php');
    }
*/
}
/**
* Reading table structure directly rom DB and pack it as CRUD fields array
*/
class WaCRUDerReadDb extends WaCRUDerReadDef { # reading table definition from database
    public function read($filename, $options=false) {
        $ret = false;
        $cols = WebApp::$db->sql_query('describe '. $filename, true, true, true);
        if (is_array($cols) && count($cols)) {
            $ret = new WaTableDef;
            $ret->id = $ret->tbrowseid = $ret->desc = $ret->shortdesc = $filename;
            foreach ($cols as $no => $col) {
                $id = $col['Field'];
                $strtype = isset($col['Type']) ? $col['Type'] : 'VARCHAR(20)';
                $null = isset($col['Null']) ? $col['Null'] : 'YES';
                $key  = isset($col['Key']) ? $col['Key'] : '';
                $default = isset($col['Default']) ? $col['Default'] : '';
                $extra   = isset($col['Extra']) ? $col['Extra'] : '';
                $fld = new waFieldDef;
                $fld->id = $fld->name = $fld->desc = $fld->shortdesc = $id;
                $fld->confirmdel = 1;
                $stype = preg_split('/[(),]/', $strtype);
                $fld->type = array_shift($stype);
                if (count($stype)>0) $fld->length = implode(',', $stype); // length NN[,MM]

                if ($key === 'PRI') {
                    $ret->_pkfield = $id;
                    $ret->_pkfields[] = $id;
                    $fld->editcond = false;
                }
                else {
                    $fld->editcond = 1;
                    $fld->edittype = 'TEXT';
                }
                $ret->fields[$id] = $fld;
            }
        }
        return $ret;
    }
    public function getFileExt() { return 'db'; }

}

// spl_autoload_register(WaCRUDer::autoloader);