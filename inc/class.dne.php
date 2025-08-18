<?php
/**
* @name class.dne.php
* Multi-Level Data Node Exchange (through xml), "DNE" - export full data node to XML and import into DB from XML
* Node: one row in "primary" table and ALL rows from all "child" tables binded to this primary by foreign key
* @version 1.02.001 2025-04-15
* @author Alexander Selifonov
*/
namespace DNExchange;
class DNE {
    const ERR_WRONGXMLFILE = 101;
    const ERR_WRITEFILE_DENIED = 102;
    const ERR_WRONG_STRUCTURE = 103;
    const FMT_VERSION = '2.0';
    static $charset = 'UTF-8';
    static $avoid_action = FALSE; # turn to TRUE to just show input params on import, no real action
    private static $configPath = '';
    private static $cfgFileMapping = [];
    private static $finderCallback = NULL; # user function that can find XML DNE file if it's not in default folder

    private $_debug = 0 ; # turn On debug logging
    private $_emulate = FALSE; # NO DATA UPDATE, emulate it!
    protected $dbObj = NULL;
    private $_transactional = TRUE;
    private $_errorcode = 0;
    protected $failure = FALSE;
    private $_errormessage = '';
    private $_primaryTable = '';
    private $_pkField = '';
    private $_uniqueField = '';
    private $_childTables = [];
    protected $oldRecords = []; // to save ID's of current records to be reused or deleted on finishing
    protected $oldPKeys  = []; // PK field names in oldRecords

    # private $fhan = 0;
    private $outBody = '';
    protected $skipEmptyStrings = FALSE; # TRUE is good only for ADDING new data node, but can lead to errors when update existing records!
    protected $fileFormat = 1;

    public function __construct(&$dbObj) {
        $this->dbObj =& $dbObj;
    }

    public function setEmulate($emul = 1) {
        $this->_emulate = $emul;
    }
    public static function setDefaultConfigPath($xmlPath) {
        self::$configPath = $xmlPath;
    }
    public static function setXmlFinder($callback) {
        self::$finderCallback = $callback;
    }

    # add a mapping from table name/ID to XML file with config
    public static function addMapping($tableid, $dneFilePath) {
        self::$cfgFileMapping[$tableid] = $dneFilePath;
    }

    public function loadConfig($tableId) {
        # exit('1' . AjaxResponse::showMessage('params: <pre>' . print_r($AppEnv::$_p,1) . '</pre>'));
        if(!empty(self::$cfgFileMapping[$tableId])) {
            exit("try mapped : ". self::$cfgFileMapping[$tableId]);
            $xmlPath = self::$cfgFileMapping[$tableId];
        }
        else {
            $baseXmlName = "dne.$tableId.xml";
            $xmlPath = self::$configPath . $baseXmlName; # full path from tablename "app/dneconfig/dne.$table.xml"
            if(!is_file($xmlPath) && self::$finderCallback && is_callable(self::$finderCallback)) {
                $xmlPath = call_user_func(self::$finderCallback, $tableId);
            }
            if(empty($xmlPath) || !is_file($xmlPath)) exit("[$tableId]: DNE Config file not found: $baseXmlName");
        }
        # if(!is_file($xmlPath)) throw new \ErrorException("[$tableId]: Config file not found: $xmlPath");
        if(!is_file($xmlPath)) exit("[$tableId]: Config file not found: $xmlPath");

        $xml = @simplexml_load_file($xmlPath);
        if(!$xml) {
          $this->_errorcode = self::ERR_WRONGXMLFILE;
          $this->_errormessage = "$xmlPath failed to read!";
          # echo "Error {$this->_errorcode} - {$this->_errormessage}<br />";
          return FALSE;
        }
        if(!isset($xml->baseparameters)) {
          $this->_errorcode = self::ERR_WRONGXMLFILE;
          $this->_errormessage = "$xmlPath has wrong XML format (no baseparameters Tag)!";
          # echo "Error {$this->_errorcode} - {$this->_errormessage}<br />";
          return FALSE;
        }
        if(empty($xml->baseparameters['primarytable']) || empty($xml->baseparameters['pkfield'])) {
          $this->_errorcode = self::ERR_WRONG_STRUCTURE;
          $this->_errormessage = "Definition has no primarytable or primarytable pkfield";
          return FALSE;
        }
        $this->_errorcode=0;  $this->_errormessage='';

        $this->_primaryTable = (string)$xml->baseparameters['primarytable'];

        $this->_pkField = (string)$xml->baseparameters['pkfield'];
        $this->_uniqueField = (string)$xml->baseparameters['uniquefield'];

        if(isset($xml->childtables)) {
            foreach($xml->childtables->children() as $cid=>$obj) {
                switch($cid) {
                    case 'childtable':
                        $tbname = (string) $obj['name'];
                        $this->_childTables[$tbname] = $this->parseChildTable($obj);
                        break;
                }
            }
        }
        return TRUE;
    }
    public function getStructure() {
        return $this->_childTables;
    }
    # recursive func for parsing child table
    public function parseChildTable($xObj) {
        $ret = [
          'pkfield' => (string) ($xObj['pkfield'] ?? ''),
          'fkfield' => (string) ($xObj['fkfield'] ?? ''),
          'backfkfield' => (string) ($xObj['backfkfield'] ?? ''),
          'uniquefield' => (string) ($xObj['uniquefield'] ?? ''),
          'orderby' => (string) ($xObj['orderby'] ?? ''),
          'childtables'=>[]
        ];
        if(!empty($xObj->childtables)) {
            foreach($xObj->childtables->children() as $childtag=>$childObj) {
                if($childtag === 'childtable') {
                    $childName = (string) $childObj['name'];
                    $ret['childtables'][$childName] = $this->parseChildTable($childObj);
                }
            }
        }
        return $ret;
    }
    public function loadPrimaryRecord($id) {
        $row = $this->dbObj->select($this->_primaryTable, [
          'where' => [$this->_pkField => $id], 'singlerow'=>1, 'associative'=>1
        ]);
        if($errNo = $this->dbObj->sql_errno()) $this->failure = $errNo;
        return $row;
    }
    public function loadChildRecords($id, $tableid, $tableDef = FALSE, $parentRow = []) {
        if(!$tableDef) $tableDef = $this->_childTables[$tableid];

        # writeDebugInfo("child table [$tableid] def: ", $tableDef);
        $pkfield = $tableDef['pkfield'];
        $fkfield = $tableDef['fkfield'];
        $backfkfield = $tableDef['backfkfield'] ?? '';
        if($this->_debug) writeDebugInfo("$tableid: fkfield: [$fkfield], backfkfield:[$backfkfield]");
        if( empty($fkfield) && empty($backfkfield) ) return [];
        $orderby = ($tableDef['orderby']) ? $tableDef['orderby'] : $pkfield;
        $arRet = [];
        if(!empty($fkfield))
            $data = $this->dbObj->select($tableid, [
              'where' => [$fkfield => $id], 'associative'=>1, 'orderby'=>$orderby
            ]);
        elseif(!empty($backfkfield)) {
            # обратный foreign key - сидит в основной записи
            $data = []; # TODO
            $whereBack = [];
            foreach(explode(",",$backfkfield) as $oneKey) {
                if(!empty($parentRow[$oneKey]))
                    $findValue = $parentRow[$oneKey];
                    if(!is_numeric($findValue)) $findValue = "'$findValue'";
                    $whereBack[] = "$pkfield=$findValue";
            }
            $whereBack = implode (' OR ', $whereBack);
            if($this->_debug) writeDebugInfo("tbackfkfield where condition: [$whereBack]");
            if(!empty($whereBack)) {
                $data = $this->dbObj->select($tableid, [
                  'where' => $whereBack, 'associative'=>1, 'orderby'=>$orderby
                ]);
                if($this->_debug) {
                    writeDebugInfo("обратный foreign key - сидит в основной записи $backfkfield, SQL: ", $this->dbObj->getLastQuery());
                }
            }
            if($this->_debug) {
                writeDebugInfo("found child table rows: ",$data);
            }
            if($errNo = $this->dbObj->sql_errno()) {
                $this->failure = $errNo;
                return [];
            }
        }
        else return []; // no PK field value, return nothing

        # make data subtree  if sub-children exist
        if(is_array($data)) foreach($data as $oneRow) {
            $element = [ 'record'=>$oneRow ];
            $children = [];
            if(count($tableDef['childtables'])) foreach($tableDef['childtables'] as $subTableId => $subDef) {
                $childRecs = $this->loadChildRecords($oneRow[$pkfield],$subTableId,$subDef);
                if(is_array($childRecs) && count($childRecs))
                    $children[$subTableId] = $childRecs;
            }
            if( count($children)) $element['children'] = $children;
            $arRet[] = $element;
        }
        return $arRet;
    }
    public function loadNode($nodeid) {
        $primary = $this->loadPrimaryRecord($nodeid);
        $childData = [];
        foreach(array_keys($this->_childTables) as $childTable) {
            $childData[$childTable ] = $this->loadChildRecords($nodeid, $childTable, FALSE, $primary);
        }
        return [ 'main' => $primary, 'children' => $childData];
    }
    # deletes chars that not allowed in file names
    public static function safeName($orig) {
        return preg_replace("/[?*\(\)\{\}\:\;\>\<\'\\\" ]/", '_', $orig);
    }

    public function exportNode($nodeid, $outfile = '', $tofile = FALSE) {
        if($this->_debug) writeDebugInfo("$nodeid, outfile='$outfile', tofile='$tofile'");
        $nData = $this->loadNode($nodeid);
        # if (empty($outfile) && $tofile) $outfile = "node.".$this->_primaryTable. '-' . date('Ymd-His').'.xml';
        # $this->fhan = @fopen($outfile, 'w');
        $this->outBody = '';
        $this->_outputData("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<dnedata>\n");
        $fnameBase = $nodeid;
        $version = self::FMT_VERSION;
        $today = date('Y-m-d H:i');
        $this->_outputData("<info fmtversion=\"$version\" exportdate=\"$today\" />\n");

        $this->_outputData("<primarydata name=\"$this->_primaryTable\" pkfield=\"$this->_pkField\" uniquefield=\"$this->_uniqueField\" >\n");

        if (!empty($this->_uniqueField)) {
            $ukey = $this->_uniqueField;
            if (!empty($nData['main'][$ukey])) {
                $fnameBase = self::safeName($nData['main'][$ukey]);
                if ($fnameBase === '') $fnameBase = $nodeid;
            }
        }
        $this->_outputData(" <datarow>\n");
        $this->encodeRow($nData['main'], $this->_pkField, FALSE);
        $this->_outputData(" </datarow>\n");

        $this->_outputData("</primarydata>\n");
        if($this->_debug) writeDebugInfo("primary data: ", $this->outBody);
        if (isset($nData['children']) && is_array($nData['children']) && count($nData['children'])) {
            $pkKeyValue = $nData['main'][$this->_pkField];
            $this->saveChildrenData($this->_childTables, $pkKeyValue, $nData['children']);
        }

        $this->_outputData("</dnedata>");
        # fclose($this->fhan);
        $size = mb_strlen($this->outBody, 'WINDOWS-1251');

        if (!empty($outfile)) {
            $saved = file_put_contents($outfile, $this->outBody);
            if($this->_debug) writeDebugInfo("save to disk '$outfile'");
            if (!$saved) {
                $this->_errorcode = self::ERR_WRITEFILE_DENIED;
                $this->_errormessage = 'Cannot write to output XML file: '.$outfile;
                return FALSE;
            }
            $this->outBody = '';
            return $size;
        }
        else { # output to client stream
            $xmlName = 'dne-' . $this->_primaryTable ."-$fnameBase.xml";
            if($this->_debug) writeDebugInfo("stream to client browser as $xmlName");
            Header('Pragma: no-cache');
            Header('Pragma: public');
            Header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            Header("Content-Type: text/xml");

            if ( !empty($_SERVER) && stripos( $_SERVER['HTTP_USER_AGENT'], "MSIE" ) > 0 ) {
               header ( 'Content-Disposition: attachment; filename="' . rawurlencode($xmlName) . '"' );
               }
            else {
               header( 'Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($xmlName) );
            }
            Header("Content-Length: $size");
            exit($this->outBody);
        }
    }

    /**
    * save to XML all chi;dren data rows for this parent data row
    *
    * @param mixed $tDef child table definittion
    * @param mixed $fkeyVal parent (foreign) key field value
    * @param mixed $arData children data array
    */
    private function saveChildrenData($tDef, $fkeyVal, $arData) {
        static $level = 1;

        $spacesParent = str_repeat('  ', $level);
        $this->_outputData("$spacesParent<childtables>\n");

        $level++;
        $spaces = str_repeat('  ', $level);
        # writeDebugInfo("level : $level ", $tDef);
        foreach($arData as $tbname => $rows) {
            if($this->_debug) writeDebugInfo("export child table ", $tbname, " rows obj:", $rows);

            if (!is_array($rows) || count($rows)<1) continue;
            $fkFld = $tDef[$tbname]['fkfield'];
            $backfkFld = $tDef[$tbname]['backfkfield'];
            $pkFld = $tDef[$tbname]['pkfield'];
            $uniqFld = $tDef[$tbname]['uniquefield'];
            $this->_outputData("$spaces<childtable name=\"$tbname\" pkfield=\"$pkFld\" fkfield=\"$fkFld\" uniquefield=\"$uniqFld\">\n");
            foreach($rows as $onerow) {
                $this->_outputData("$spaces  <datarow>\n");
                $this->encodeRow($onerow, $tDef[$tbname]['pkfield'],$pkFld,"$spaces  ", $tDef[$tbname]);
                $this->_outputData("$spaces  </datarow>\n");
            }

            $this->_outputData("$spaces</childtable>\n");
        }

        $this->_outputData("$spacesParent</childtables>\n");

        $level--;
    }
    private function _outputData($string) {
        $this->outBody .= $string;
    }

    # $tDef - rthis subTable definition, including possible child tables
    public function encodeRow($row, $pkfield, $fkfield = FALSE, $spaces = '', $tDef=FALSE) {
        if(!is_array($row)) return;
        if($this->_debug) writeDebugInfo("encoding row:  ", $row);
        if($this->_debug) writeDebugInfo("tdef:  ", $tDef);
        if(isset($row['record']) && is_array($row['record']))
            $rowObj = $row['record'];
        else $rowObj = $row;

        $this->_outputData("$spaces  <rowdata>\n");
        foreach($rowObj as $key => $val) {
            if ($key === $pkfield) continue;
            if ($key === 'childtables' || is_array($val)) continue;
            if (!empty($fkfield) && $key === $fkfield) continue;
            if ($this->skipEmptyStrings && $val === '') continue;
            $xmlVal = self::encodeXmlValue($val);
            $this->_outputData(sprintf("$spaces  <%s>%s</%s>\n", $key, $xmlVal, $key));
        }

        $this->_outputData("$spaces  </rowdata>\n");

        if(!empty($tDef) && !empty($tDef['childtables']) && !empty($row['children'])) {
            $this->saveChildrenData($tDef['childtables'], $rowObj[$pkfield], $row['children']);
        }
    }
    /**
    * Importing full data node from XML file into current DB
    *
    * @param mixed $filename XML file name
    */
    public function importNode($file, $inParams = FALSE) {
        $this->oldRecords = $this->oldPKeys = [];
        if(is_array($inParams) && count($inParams))
            $params = $inParams;
        else
            $params = isset(\AppEnv::$_p) ? \AppEnv::$_p : array_merge(G_GET, G_POST);

        if(self::$avoid_action) {
            $ret['result'] = 'OK';
            $ret['message'] = 'Data: <pre>' . print_r($params,1) . '</pre>';
            return $ret;
        }
        $origTable = $params['tablename'] ?? '';
        $delFile = FALSE;
        if(is_array($file) && isset($file['tmp_name'])) {
            if($this->_debug) writeDebugInfo("file param is array: ", $file);
            $filename = $file['tmp_name'];
            $delFile = TRUE;
            if($this->_debug) writeDebugInfo("import from tmp file and delete: $filename");
        }
        elseif(is_string($file)) {
            $filename = $file;
            if($this->_debug > 1) writeDebugInfo("simple local filename passed: ", $filename);
        }
        $ret = ['result' => 'ERROR','message'=>'No file passed'];
        if(!$filename || !is_file($filename)) {

            $ret['message'] = $this->_errormessage = "File not found or ampty XML file name passed";
            if($this->_debug) writeDebugInfo("No file: ", $filename);
            return $ret;
        }
        $xml = @simplexml_load_file($filename);
        if(!$xml || !isset($xml->primarydata)) {
          $this->_errorcode = self::ERR_WRONGXMLFILE;
          $this->_errormessage = "$filename has wrong XML format or non XML at all!";
          if($this->_debug) {
              writeDebugInfo("simplexml_load_file failed ($filename)");
              $delFile = FALSE;
          }
          # echo "Error {$this->_errorcode} - {$this->_errormessage}<br />";
          $ret['message'] = $this->_errormessage = 'Ошибка парсинга XML файла';
          return $ret;
        }

        $fmtVersion = $this->fileFormat = (float) ($xml->info['fmtversion'] ?? '1'); # (string) $xml->info['fmtversion'] : 0;
        # version 2+ - new format (unlimited node tree depth)
        $ret['log'] = [];

        $this->_errorcode=0;  $this->_errormessage='';

        $primaryTable = (string)$xml->primarydata['name'];
        $primaryPkf =(string)$xml->primarydata['pkfield'];
        $primaryUnf =(string)$xml->primarydata['uniquefield'];
        if(!empty($origTable) && $origTable !== $primaryTable) {
            return ['result'=>'ERROR', 'message'=>'Wrong XML File!'];
        }
        # return ['result'=>'ERROR', 'message'=>"$origTable =  $primaryTable"];

        if($this->_debug > 1 ) writeDebugInfo("primarydata : ", $xml->primarydata);

        $updRow = $rowData = $this->_getRecordData($xml->primarydata->datarow);

        # $updRow = $rowData['data'];
        if($this->_debug > 1) writeDebugInfo("main updRow: ", $updRow) ; #, "\n  primarydata: ", $xml->primarydata);
        # return ["primary updRow ", $updRow];
        $pkValue = $updRow['data'][$primaryUnf] ?? '';
        # writeDebugInfo("primaryUnf=[$primaryUnf] = [$pkValue] ", $updRow);
        $dataKey = '';
        if($pkValue) {
            $curRec = $this->dbObj->select($primaryTable, ['fields'=>$primaryPkf, 'where'=>[$primaryUnf=>$pkValue],
              'associative' => 0, 'orderby' => $primaryPkf]);

            if($errNo = $this->dbObj->sql_errno()) {
                $this->failure = $errNo;
                $ret['result'] = 'ERROR';
                $ret['message'] = "Импорт в $primaryTable невозможен: ". $this->dbObj->sql_error;
                return $ret;
            }

            if($this->_debug) writeDebugInfo("primary table curRec: ", $curRec, " sql: ", $this->dbObj->getLastQuery());
            $dataKey = $curRec[0] ?? '';
            if($curRec) {
                $this->oldRecords[$primaryTable] = $curRec;
                $this->oldPKeys[$primaryTable] = $primaryPkf;
            }
        }
        # Collect current ID's of all child tables, to update with new values
        # writeDebugInfo("main childtables ", $xml->childtables);
        if(!empty($xml->childtables)) foreach($xml->childtables->children() as $tag=>$obj) {
            # $this->getCurrentSubTree($primaryTable, $xml->childtables, $dataKey,0);
            $this->getCurrentSubTree($primaryTable, $obj, $dataKey,0);
            # return $this->oldRecords;
        }
        if($this->_debug) {
            $ret['oldRecords-pk'] = $this->oldPKeys;
            $ret['oldRecords-before'] = $this->oldRecords;
        }

        if($errNo = $this->dbObj->sql_errno()) {
            $this->failure = $errNo;
            $ret['result'] = 'ERROR';
            $ret['message'] = "Импорт в $primaryTable невозможен: ". $this->dbObj->sql_error;
            return $ret;
        }

        if($this->_transactional && !$this->_debug) {
            $this->dbObj->beginTransaction();
        }

        list($recId, $logString) = $this->_upsertRow($primaryTable, $updRow,$primaryPkf,$primaryUnf);
        if($this->_debug) writeDebugInfo("_upsertRow($primaryTable) result: id=", $recId, ' logString:', $logString);
        # $recId will be a foreign key for each child table
        $ret['log'] = array_merge(($ret['log'] ?? []) , $logString);
        # return $xml->childtables;
        if(isset($xml->childtables)) {
            foreach($xml->childtables->children() as $cid=>$obj) {
                $resultStrg = $this->importSubNode($obj,$recId);
                $ret['log'] = array_merge(($ret['log'] ?? []) , $resultStrg);
                if($this->_debug) writeDebugInfo("importSubNode(recId = ",$recId,") result: ", $resultStrg);
            }
        }
        # return $this->oldRecords; # 'KT-003';
        $ret['result'] = 'OK';
        $ret['message'] = "Импорт [$pkValue] в $primaryTable успешно произведен";
        if($this->_debug) writeDebugInfo("final result:", $ret);
        if($delFile) {
            @unlink($filename);
            if($this->_debug) writeDebugInfo("tmp file deleted: ", $filename);
        }
        # delete unused old records
        foreach($this->oldRecords as $tabname => $arId) {
            if(is_array($arId) && count($arId)) {
                if($this->_debug) writeDebugInfo("TODO: delete records from $tabname: ", $arId, "field:", $this->oldPKeys[$tabname]);
                if(!$this->_emulate) {
                    $where = $this->oldPKeys[$tabname] . ' IN(' . implode(',',$arId) . ')';
                    $delRows = $this->dbObj->delete($tabname, $where);
                    if($this->_debug) writeDebugInfo("deleting unused records SQL: ", $this->dbObj->getLastQuery(), " deleted: $delRows");
                    $ret["Cleaning unused in $tabname"] = $delRows;
                }
                else
                    $ret["records to clean $tabname"] = implode(',',$arId);
            }
        }
        if($this->_transactional && !$this->_debug) {
            if($this->failure)
                $this->dbObj->rollBack();
            else
                $this->dbObj->commit();
        }
        if($this->_debug)
            $ret['oldRecords-after'] = $this->oldRecords;

        return $ret;
    }
    public function getErrorMessage() {
        return $this->_errormessage;
    }
    /**
    * Deleting all child data for current primary row if exist
    * (before import new children)
    * @param mixed $tdef sutrable definition
    * @param mixed $pkeyValue parent key value (FK for this table)
    */
    private function getCurrentSubTree($parentTable, $tdef, $pkeyValue, $nesting=0) {
        # writeDebugInfo("$parentTable($nesting)/getCurrentSubTree tdef: ", $tdef, " parent key values ", $pkeyValue);
        if(isset($tdef->childtable))
            $myObj = $tdef->childtable;
        else $myObj = $tdef;
        if(!isset($myObj['name']) )
            return;
        $retSql = [];
        $myName = (string) ($myObj['name'] ?? '');
        $myPkField = (string) ($myObj['pkfield'] ?? '');
        $myFkField = (string) ($myObj['fkfield'] ?? '');
        if(empty($myName)) {
            writeDebugInfo("wrong tdef passed - no name attr!");
            return;
        }
        if(isset($myObj->datarow)) foreach($myObj->children() as $subDef) {
            # writeDebugInfo("KT-700 datarow->children item(subDef): ", $subDef);
            $pkvalue = (string)($subDef->datarow->rowdata[$myPkField] ?? '');
            $where = is_array($pkeyValue) ? "$myFkField IN(".implode(',',$pkeyValue).')' : [$myFkField=>$pkeyValue];

            $existRecs = $this->dbObj->select($myName, [ 'fields'=>$myPkField,'where'=>$where,
             'associative'=>0, 'orderby'=>$myPkField ]);
            # writeDebugInfo("KT-701 $myName/$pkeyValue exist Records: ", $existRecs, "  sql: ", $this->dbObj->getLastQuery());
            if(is_array($existRecs) && count($existRecs)) {
                $this->oldRecords[$myName] = array_merge(($this->oldRecords[$myName] ?? []),$existRecs);
                $this->oldPKeys[$myName] = $myPkField;
                if(isset($subDef->childtables)) foreach($subDef->childtables as $tag=>$subObj)
                    $this->getCurrentSubTree($myName, $subObj, $existRecs, ($nesting+1));
            }
            break;
        }
        return TRUE;
    }
    /**
    * insert or update (if exist) record
    *
    * @param mixed $table table name
    * @param mixed $data associative array - data to apply
    * @param mixed $primaryPkf primary key field name
    * @param mixed $uniqueKey
    * @param mixed $fkKey foreignKey field name
    * @param mixed $uniqueKey unique field name
    * @param mixed $uniqueValue unique value
    * @param mixed $fkValue foreignKey field value
    */
    private function _upsertRow($table, $data, $primaryPkf, $uniqueKey, $fkKey = NULL, $fkValue=NULL) {
        $pkValue = FALSE;
        $uniqueValue = FALSE;
        $ret = [];
        if($this->_debug>1) {
            writeDebugInfo("_upsertRow($table,pk='$primaryPkf', uniq='$uniqueKey',fkey='$fkKey',fkval='$fkValue') data: ", $data);
            writeDebugInfo('trace ', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
        }
        if(isset($data->rowdata)) {
            $myData = $this->_getRecordData($data->rowdata);
            $newRowData = $myData['data'];
        }
        elseif(isset($data['data']))
            $newRowData = $data['data'];
        else {
            $myData = $this->_getRecordData($data);
            $newRowData = $myData['data'];
        }

        $existRecord = 0;
        if(isset($this->oldRecords[$table]) && count($this->oldRecords[$table])>0)
            $existRecord = array_shift($this->oldRecords[$table]);
        # use first existing record to update not used yet
        if ($fkKey && $fkValue) {
            $newRowData[$fkKey] = $fkValue;
            $canAdd = TRUE;
            # writeDebugInfo("$table, switched FK value $fkKey=[$fkValue]");
        }
        else $canAdd = FALSE;
        # if(!$canAdd) die("$table: No FK field $fkKey value to substitute!");
        if ($uniqueKey && $uniqueValue !== FALSE) {
            $newRowData[$uniqueKey] = $uniqueValue; # uniqueField value!
        }

        if ($existRecord) {
            # update existing record
            $pkValue = $existRecord;
            if (!$this->_emulate) {
                $upResult = $this->dbObj->update($table, $newRowData, [$primaryPkf=>$existRecord]);
                if($errNo = $this->dbObj->sql_errno()) {
                    if($this->_debug) writeDebugInfo("$table update error with data: ",$newRowData);
                    $this->failure = $errNo;
                    $ret[] = "$table update error";
                }
                else {
                    $affected = $this->dbObj->affected_rows();
                    # writeDebugInfo("update SQL:", $this->dbObj->getLastQuery(), " affected=$affected");
                    $ret[] = "$table: updated row $existRecord: affected $affected";
                }
            }
            else $ret[] = "$table/emulate:to update record $primaryPkf=$existRecord";
        }
        else {
            # No record with desired uniq-key, add new one!
            if ($this->_emulate) {
                $pkValue = rand(10000,90000);
                $ret[] = "$table/emulate:to insert record $pkValue" . print_r($newRowData,1);
            }
            else {
                $upResult = $this->dbObj->insert($table, $newRowData);
                # writeDebugInfo("inserting into $table ", $newRowData);
                $pkValue = $this->dbObj->insert_id();
                if($errNo = $this->dbObj->sql_errno()) {
                    writeDebugInfo("$table insert error with data: ",$newRowData);
                    $this->failure = $errNo;
                }
                $affected = $this->dbObj->affected_rows();
                $ret[] = "$table: insert new row $pkValue: affected $affected";
            }
        }

        if($this->_debug) writeDebugInfo("upsertRow $table result($pkValue) ", $ret);
        if(!$this->failure && !empty($data->childtables)) {
            # writeDebugInfo("KT14 childtabes ", $data->childtables);
            foreach($data->childtables->children() as $keytag => $childObj) {
                if($this->_debug > 1) writeDebugInfo("KT15 Adding child records $keytag datarow ", $childObj);
                $childName = (string)$childObj['name'];
                $childPk = (string)$childObj['pkfield'];
                $childFk = (string)$childObj['fkfield'];
                foreach($childObj->datarow as $item) {
                    # writeDebugInfo("_upsertRow($childName for datarow item ", $item);
                    $ret[] = $this->_upsertRow($childName, $item, $childPk,'',$childFk, $pkValue);
                }
            }
        }
        return [$pkValue, $ret];
    }

    private function _getRecordData($xmlrow) {
        $arData = [];
        if(isset($xmlrow->rowdata)) {
            foreach($xmlrow->rowdata->children() as $ukey => $uval) {
                $arData[$ukey] = (string) $uval;
            }
        }
        else {
            foreach($xmlrow->children() as $ukey => $uval) {
                $arData[$ukey] = (string) $uval;
            }
        }
        $arRet = ['data' => $arData];
        # writeDebugInfo("xmlrow: ", $xmlrow, ', trace: ', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
        if($this->fileFormat >=2 && isset($xmlrow->children)) {
            # children exists
            $arRet['children'] = 'TODO';
        }

        return $arRet;
    }
    /**
    * Importing records from chiltable in node
    *
    * @param mixed $node node data
    * @param mixed $fkValue parent Primary Key value (foreign key for all records in this recordset)
    */
    public function importSubNode($node, $fkValue) {
        $tbname = (string) $node['name'];
        $pkField = (string) $node['pkfield'];
        $fkField = (string) $node['fkfield'];
        $unqField = (string) ($node['uniquefield'] ?? '');

        $recno = 0;
        $ret[] = "Importing data into child table [$tbname], pkfield:$pkField, foreign: $fkField, unique:$unqField";
        if($this->fileFormat < 2) {
            foreach($node->children() as $nodekey => $record) {
                # writeDebugInfo("child $nodekey: ", $record);
                if ($nodekey !== 'datarow') continue;
                $updDta = $this->_getRecordData($record);
                $result = $this->_upsertRow($tbname, $updDta, $pkField, $unqField, $fkField, $fkValue);
                $ret[] = $result;
                $recno++;
            }
        }
        else { #
            foreach($node->datarow as $record) {
                $result = $this->_upsertRow($tbname, $record, $pkField, $unqField, $fkField, $fkValue);
                $ret[] = $result;
                $recno++;
            }
        }
        # $ret = "subtable: $tbname, pkfield: $pkField, fk: $fkField, unique: $unqField";
        return $ret;
    }
    # wrap value with CDATA if it contains "dangerous" chars
    public static function encodeXmlValue($strg) {
        if(preg_match("/[<>\n\r]/", $strg)) {
            $strg = str_replace("]]>", "]]&gt;", $strg);
            $ret = "<![CDATA[$strg]]>";
        }
        else $ret = $strg;
        # writeDebugInfo("encoded: $ret");
        return $ret;
    }

    public function viewMe() {
        return '<pre>' . print_r($this,1) . '</pre>';
    }
    public function getChildTables() {
        return $this->_childTables;
    }

    /**
    * returns javascript code for import XML interface in the grid
    *
    * @param mixed $tabName table name (or template ID)
    * @return string with JS code ready to insert into HTML
    */
    public static function getJsCode($tabName) {
        $ret = <<< EOJS
$(document).ready( function() {
  $.getScript( "js/SimpleAjaxUploader.js" );
});
dneLoader = {
  backend: './?p=editref&t=$tabName',
  uploader : null,
  exportNodeToXML: function(id) {
      window.open("./?p=dneexport&ajax=1&dneaction=export&t=$tabName&id="+id);
  },
  uploadFileDone: function(filename, response) {
    // console.log('Upload done, server response: '+response);
    var splt = response.split('|');
    if (splt[0] === '1') {
        asJs.timedNotification(splt[1],4);
        // window.location.reload();
    }
    else {
        asJs.timedNotification(("Ошибка : "+splt[1]),4);
    }
  },
  initImportDialog: function() {
      // console.log('dialog initialization starts.');
      dneLoader.uploader = new ss.SimpleUpload({
      // button: 'btn_uploadaccounts', // file upload button
        url: "./?p=dneexport&dneaction=import",
        name: 'file', // upload parameter name
        data: { 'tablename':'$tabName' },
        allowedExtensions: ['xml', 'XML'],
        maxSize: 100, // KB
        multiple: true, // можно drag-drop-нуть несколько файлов разом
        dropzone: 'exp_dropzone', // 'exp_dropzone',div_import_dialog
        autoSubmit: true,
        onChange: function(filename, extension, uploadBtn, fileSize, file) {
           // console.log('file: ' + filename + ' '+fileSize);
        },
        onComplete: dneLoader.uploadFileDone
     });
  },
  closeImportDialog: function() {
      dneLoader.uploader.destroy();
      dneLoader.uploader = null;
      $('#exp_dropzone').remove();
  },
  yesPressed : function() {
      console.log('yesPressed go!');
  },
  importFromXml: function() {
    $("#div_import_dialog").remove();
    var dbody = "<div id='exp_dropzone' class='bounded ct' style='height:150px'>Перетащите сюда XML файл из проводника.</div>";
    opts = {
        title : 'Импорт из XML файла',
        closeOnEscape: true,
        resizable: false,
        dialogClass: 'floatwnd',
        open: dneLoader.initImportDialog,
        width:400,
        close: dneLoader.closeImportDialog,
        buttons : {
            "Закрыть": function(){ $(this).dialog("close"); return true; }
        }
    };
    var htmlCode = "<div id='div_import_dialog'>"+dbody+"</div>";
    $(htmlCode).dialog(opts);
  }
}

EOJS;
        return $ret;
    }
}
