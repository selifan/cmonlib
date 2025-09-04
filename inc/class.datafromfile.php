<?PHP
/**
* @name class.datafromfile.php
* Class for reading "data rows" from xls/xlsx/txt/csv files
* @Author Alexander Selifonov, <alex [at] selifan {dot} ru>
* @copyright Alexander Selifonov, <alex [at] selifan {dot} ru>
* @version 1.08.002 2025-02-18 (started 2012-09-04)
* @Link: http://www.selifan.ru
* @license http://www.opensource.org/licenses/bsd-license.php    BSD
**/
class DataFromFile {
    const VERSION = '1.06';
    private $csv_delimiter = ';'; # default delimiter, enclosure and escape chars for parsing CSV files
    private $csv_enclosure = '"';
    private $csv_escape    = '\\';
    static $UTF_TYPE = 'UTF-8'; # default UTF set, to mb_str... functions
    private $fmtDefs = array();
    private $errRecords = 0;
    private $err_message = array();
    const BOM_MARK = "\xEF\xBB\xBF"; # UTF format mark EF BB BF
    private $filetype = '';
    private $headerRowno = -1; # this row will be used as field headers container, to detect columns by header text in data file
    private $skiprows = 0; # how many lines/rows belong to "header block" and must be skipped
    private $stopRow = 0; # debug mode - if not zero stop after this line
    private $debug = 0;
    private $objReader = null;
    private $objPHPExcel = null;
    private $sheet = null;
    private $sheetnames = array(); # xls sheets to read
    private $sheetId = 0; # sheet to read data
    private $srcCurRow = 0;
    private $lastRow = 0;
    private $lastCol = 0;
    private $headers = array();
    private $emptyRows = 0;
    private $incharset = '';
    private $outcharset = '';
    private $dateFormat = 'YYYY-MM-DD'; # default format for date values
    private $txtHandle = null;
    private $txtdelimiter = "\t"; # default field delimiter in text file
    private $_supportedTypes = ['txt','csv']; # xls|xlsx|ods|xml will be added depending on included PHPExcel reader modules
    protected $missedFields = [];
    public function __construct($options=false) {
        if (!class_exists('PHPExcel_IOFactory')) {
            require_once('PHPExcel.php');
            require_once('PHPExcel/IOFactory.php');
        }
        if(class_exists('PHPExcel_IOFactory')) {
            if(class_exists('PHPExcel_Reader_Excel5')) $this->_supportedTypes[] = 'xls';
            if(class_exists('PHPExcel_Reader_Excel2007')) $this->_supportedTypes[] = 'xlsx';
            if(class_exists('PHPExcel_Reader_OOCalc')) $this->_supportedTypes[] = 'ods';
            if(class_exists('PHPExcel_Reader_Excel2003XML')) $this->_supportedTypes[] = 'xml';
        }

        if(is_array($options)) {
            $this->setOptions($options);
        }
    }
    /**
    * can be called more then once, to change active (XLS) sheet for reading data from
    *
    * @param mixed $options
    */
    public function setOptions($options) {
        if(is_array($options)) {
            if(!empty($options['fields']) && is_array($options['fields'])) $this->fmtDefs = $options['fields'];
            if(!empty($options['delimiter'])) $this->txtdelimiter = $options['delimiter'];
            if(!empty($options['incharset'])) $this->incharset = $options['incharset'];
            if(!empty($options['outcharset'])) $this->outcharset = $options['outcharset'];
            if(!empty($options['skiprows'])) $this->skiprows = intval($options['skiprows']);
            if(!empty($options['stoprow'])) $this->stopRow = intval($options['stoprow']);
            if(isset($options['debug'])) $this->debug = $options['debug'];
            if(!empty($options['headerrow'])) {
                $this->headerRowno = intval($options['headerrow']);
                $this->skiprows = max($this->skiprows,$this->headerRowno);
            }
            if(!empty($options['sheetid'])) {
                $this->sheetId = $options['sheetid'];
            }
            else $this->sheetId = 0;

            if(!empty($options['dateformat'])) $this->dateFormat = trim($options['dateformat']);

            if(!empty($options['csv_delimiter'])) $this->csv_delimiter = $options['csv_delimiter'];
            if(!empty($options['csv_enclosure'])) $this->csv_enclosure = $options['csv_enclosure'];
            if(!empty($options['csv_escape'])) $this->csv_escape = $options['csv_escape'];
        }
    }
    /**
    * Opens source file for reading structurized data
    *
    * @param mixed $filename file name
    * @param mixed $filetype file type (if not passed, file extention will be used)
    */
    public function open($filename, $filetype='') {
        if(!$filetype) {
            $ext = pathinfo($filename,PATHINFO_EXTENSION);
            $filetype = strtolower($ext);
        }
        if(!in_array($filetype, $this->_supportedTypes)) {
            $this->err_message[] = 'Unsupported/undefined file type : '.$filetype;
            return false;
        }
        $this->filetype = $filetype;
        if($filetype==='xls' || $filetype==='xlsx' || $filetype==='ods' || $filetype==='xml') {

            try {
                $this->objReader = PHPExcel_IOFactory::createReaderForFile($filename);
                if(is_array($this->sheetnames) && count($this->sheetnames)) $this->objReader->setLoadSheetsOnly($this->sheetnames);
                $this->objReader->setReadDataOnly(false);
                $this->objPHPExcel = $this->objReader->load($filename);

                if(is_numeric($this->sheetId)) {
                    $this->sheet = $this->objPHPExcel->getSheet($this->sheetId);
                }
                elseif(is_string($this->sheetId)) {
                    $this->sheet = $this->objPHPExcel->getSheetByName($this->sheetId);
                }
                else {
                    $this->err_message[] = 'Wrong sheet ID passed (must be Integer No OR string name';
                    return FALSE;
                }

                # $this->objPHPExcel = PHPExcel_IOFactory::load($filename);  # "one cmd" opening
                # $lastColumm = $this->sheet->getHighestColumn(); # Now we know maximal colums and rows in the sheet.
                $this->lastRow = $this->sheet->getHighestRow();
                $this->lastCol = PHPExcel_Cell::columnIndexFromString($this->sheet->getHighestColumn());
                $this->srcCurRow = $this->skiprows + 1;
                # load headers from first xls row
                # echo "last col: ", $this->lastCol . ' header row: '.$this->headerRowno . '<br>';
                for($col=0;$col<=$this->lastCol;$col++) {
                    $this->headers[$col] = @$this->sheet->getCellByColumnAndRow( $col, $this->headerRowno)->getCalculatedValue();
                }
                # echo 'headers::<pre>'.print_r($this->headers,1) . '</pre>';
            } catch(Exception $e) {
                $this->err_message[] = $filename . ' - XLS opening exception: ' .$e->getMessage();
                return FALSE;
            }

            if(!$this->objPHPExcel) {
                $this->err_message[] = 'Error opening Excel file '.$filename;
                die('Error opening Excel file '.$filename);
                return false;
            }
            $this->incharset = 'UTF-8';
        }
        elseif($filetype=='txt' || $filetype=='csv') {
            $this->txtHandle = @fopen($filename,'r');

            if($this->txtHandle) {
                if($this->skiprows>0) for($kk=1; $kk<=$this->skiprows;$kk++) {
                    $skipme = @fgets($this->txtHandle); # skip "header" lines
                    if($kk===$this->headerRowno) {
                        if ($filetype==='csv' && function_exists('str_getcsv'))
                             $this->headers = str_getcsv($skipme, $this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                        else $this->headers = preg_split( '/[;\t]/', rtrim($skipme), -1);
                        if (mb_substr($this->headers[0],0,3,'WINDOWS-1251') === self::BOM_MARK)
                            $this->headers[0] = mb_substr($this->headers[0],3);
                    }
                }
                if ($this->debug) WriteDebugInfo("headers:".$this->headers);
            }
            else {
                $err = $this->err_message[] = 'Error opening text file '.$filename;
                return false;
            }
        }
        if(count($this->headers)) {
            foreach($this->headers as $no=>$hd) {
                $this->headers[$no] = $this->convertValue($hd);
            }
            if($this->debug) WriteDebugInfo('ROW headers from header row:', $this->headers);

            # if fields already passed, analyze 'header' attribute and find actual column positions according to header strings
            $this->_analyzeHeaders();
        }

        return true;
    }

    private function _analyzeHeaders() {

        if(count($this->fmtDefs)<1 OR count($this->headers)<1) return false;
        $this->missedFields = [];

        foreach($this->fmtDefs as $fldid=>$def) {
            if(!empty($def['header'])) {
                $findHd = mb_strtolower($def['header'], self::$UTF_TYPE);
                $realCol = array_search($def['header'], $this->headers);
                if($realCol === FALSE) {
                    foreach($this->headers as $no=>$hd) { # Case insensitive search...
                        if(empty($hd)) { # Column has empty header? skip it
                            continue;
                        }
                        if($findHd == mb_strtolower((string)$hd,self::$UTF_TYPE)) { $realCol = $no; break; }
                    }
                    if($realCol===FALSE && $this->debug>1) WriteDebugInfo("Not found [$findHd] in header array:", $this->headers);
                }
                if($realCol !== FALSE) {
                    $this->fmtDefs[$fldid]['col'] = $realCol;
                    if($this->debug>1) WriteDebugInfo("$fldid/$def[header] found column No = $realCol");
                }
                else {
                    $this->missedFields[] = $fldid;
                    if($this->debug>1) WriteDebugInfo("$fldid - column not found !");
                }
            }
        }
        if($this->debug) WriteDebugInfo('after analyzing headers:',$this->fmtDefs);
#        echo 'this->fmtDefs:<pre>'.print_r($this->fmtDefs,1) . '</pre>';
    }
    /**
    * Sets field definition array.
    *
    * @param mixed $fldDefs assoc.array with all needed fields to be loaded
    */
    public function SetFieldDefinitions($fldDefs) {
        if(is_array($fldDefs)) $this->fmtDefs = $fldDefs;
        $this->_analyzeHeaders();
    }
    /**
    * closes source fiule and frees memory
    *
    */
    public function close() {
        if($this->txtHandle) {
            @fclose($this->txtHandle);
            $this->txtHandle = null;
        }
        elseif(($this->objPHPExcel)) {
            $this->objPHPExcel = $this->objReader = $this->sheet = null;
        }
    }
    /**
    * Returns "caret" to the beginning, to read again from the very first noskipped row
    *
    */
    public function fileRewind() {

        $ret = false;
        if($this->txtHandle) {
            @rewind($this->txtHandle);
            if($this->skiprows>0) for($kk=0;$kk<$this->skiprows; $kk++) {
                fgets($this->txtHandle);
                if(feof($this->txtHandle)) break;
            }
            $this->srcCurRow = $this->skiprows + 1;
            $ret = true;
        }
        elseif(is_object($this->objPHPExcel)) {
            $this->srcCurRow = $this->skiprows + 1;
            $ret = true;
        }
        if($this->debug) WriteDebugInfo('dataFromFile:fileRewind, cur.pos = ',$this->srcCurRow, " skiprows: ",$this->skiprows);
        return $ret;
    }
    /**
    * Parses and returns one row data from open source file
    * @return assoc.array with pairs "key"->value according to passed field definitions or FALSE if file end reached
    */
    public function getDataRow() {
        if($this->debug>1) WriteDebugInfo('getDataRow() start: cur.position=',$this->srcCurRow);
        if($this->stopRow>0 && $this->srcCurRow>$this->stopRow) {
            return false; # stop by row number
        }

        $ret = array();
        if(($this->txtHandle)) {
            while(!feof($this->txtHandle)) {
                $rawline = fgets($this->txtHandle);
                if ($this->filetype === 'txt')
                    $splt = explode("\t", rtrim($rawline));
                elseif($this->filetype === 'csv') {
                    if (function_exists('str_getcsv')) # PHP 5.3+ !
                        $splt = str_getcsv($rawline, $this->csv_delimiter, $this->csv_enclosure, $this->csv_escape);
                    else {
                        $splt = explode($this->csv_delimiter, $rawline, -1);
                        for($ii=0;$ii<count($splt); $ii++) {
                            if(substr($splt[$ii],0,1)===$this->csv_enclosure && substr($splt[$ii],-1)===$this->csv_enclosure ) # cut enclosure chars
                               $splt[$ii] = substr($splt[$ii],1,-1);
                            $splt[$ii] = str_replace($this->csv_escape.$this->csv_enclosure, $this->csv_enclosure, $splt[$ii]);
                        }
                    }
                }

                $this->srcCurRow++;
                if(count($splt)<2 && $splt[0]=='') continue; # skip empty strings

                if(count($this->fmtDefs)<1) {
                    return $splt; # no fields defs, so just return scattered array
                }
                $curCol = 0;
                foreach($this->fmtDefs as $fldid=>$fld) {

                    $colno = isset($fld['col']) ? intval($fld['col']) : -1;
                    if($colno>=0 && isset($splt[$colno])) {
                        $ret[$fldid] = $splt[$colno];
                        if(!empty($fld['convert']) && is_callable($fld['convert']))
                             $ret[$fldid] = call_user_func($fld['convert'],$ret[$fldid]);
                        else $ret[$fldid] = $this->convertValue($ret[$fldid]);
                    }
                    $curCol++;
                }
                return $ret;
            }
            return false;
        }
        elseif(is_object($this->sheet)) { # xls

            if($this->srcCurRow > $this->lastRow) return false;
            if($this->stopRow > 0 && $this->srcCurRow > $this->stopRow) return false;

            # foreach ($this->sheet->getRowIterator() as $row) {
                # if($this->skiprows>0 && $this->srcCurRow<=$this->skiprows) continue; # skip header rows
                $ret = array();
                if(count($this->fmtDefs)>0) { # get only columns listed in field defs.
                    foreach($this->fmtDefs as $fldid=>$fld) {
                        $colno = isset($fld['col']) ? intval($fld['col']) : -1;
                        $vtype = isset($fld['type']) ? strtolower($fld['type']) : '';
                        if($colno>=0) {
                            try {
                                $cell = @$this->sheet->getCellByColumnAndRow($colno, $this->srcCurRow);
                                $ret[$fldid] = $cell->getCalculatedValue();
                                # writeDebugInfo($this->srcCurRow." alternate $fldid: ", $ret[$fldid]);
                            }
                            catch(Exception $e) {
                                # WriteDebugInfo("getCellByColumnAndRow raised exception, row={$this->srcCurRow}, col=$colno");
                                try {
                                    $cell = $this->sheet->getCellByColumnAndRow($colno, $this->srcCurRow);
                                    $ret[$fldid] = $cell->getValue();
                                    # writeDebugInfo($this->srcCurRow." alternate $fldid: ", $ret[$fldid]);
                                }
                                catch(Exception $e) {
                                    if (is_function('WriteDebugInfo'))
                                        WriteDebugInfo('getCellByColumnAndRow() exception:',$e->getMessage());
                                }
                            }

                            if (PHPExcel_Shared_Date::isDateTime($cell)) {
                                $exValue = $ret[$fldid];
                                $ret[$fldid] = PHPExcel_Style_NumberFormat::ToFormattedString( $ret[$fldid], $this->dateFormat);
                                if(is_numeric($exValue)) {
                                    # {ups/2025-02-18} PHPExcel BUGFix : для дат до 01.01.1970 ToFormattedString вычисляет ЛАЖУ! Нашкл преобр. в Linux timestamp
                                    $unix_date = ($exValue - 25569) * 86400;
                                    $newDate = date('Y-m-d', $unix_date);
                                    if($this->dateFormat === 'YYYY-MM-DD' || $this->dateFormat === 'Y-m-d')
                                        $ret[$fldid] = $newDate;
                                }
                                # writeDebugInfo("$fldid: date value $exValue converted to ", $ret[$fldid], " by unix: $newDate");
                            }

                            if(!empty($fld['convert']) && is_callable($fld['convert'])) {
                                $old = $ret[$fldid];
                                $ret[$fldid] = call_user_func($fld['convert'],$ret[$fldid]);
                            }
                            else {
                                $ret[$fldid] = $this->convertValue($ret[$fldid], $vtype);
                            }

                            # WriteDebugInfo("row={$this->srcCurRow}, col=$colno : ", $ret);
                        }
                    }

                }
                else { # no field defs, so return whole data row from the sheet
                    $row = new PHPExcel_Worksheet_Row($this->sheet, $this->srcCurRow);
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $colno = 0;
                    foreach ($cellIterator as $cell) {
                        if (!is_null($cell)) {
                            $ret[$colno] = $this->convertValue( $cell->getCalculatedValue() );
                            if (PHPExcel_Shared_Date::isDateTime($cell)) {
                                $ret[$colno] = PHPExcel_Style_NumberFormat::ToFormattedString( $ret[$colno], $this->dateFormat);
                            }
                        }
                        $colno++;
                    }
                }
                $this->srcCurRow++;

                if ($this->debug>1)
                    WriteDebugInfo('one data row from Sheet: ',$ret);
                return $ret;
            }
            else {
                WriteDebugInfo("Not a sheet , exiting");
                return false; # unsupported fmt
            }
    }

    public function convertValue($val, $datatype='') {
        switch($datatype) {
            case 'date':
                return (is_int($val) || $val>=10000) && class_exists('PHPExcel_Style_NumberFormat') ?
                    PHPExcel_Style_NumberFormat::toFormattedString($val, $this->dateFormat ) : $val; # 'M/D/YYYY'
            case 'int':
                return intval($val);
            case 'bool':
                return !empty($val);
        }
        if($this->incharset!='' && $this->outcharset!='' && $this->incharset != $this->outcharset) {
            $detect_charset = mb_detect_encoding($val,($this->incharset . ',ASCII'));
            return iconv($detect_charset,$this->outcharset,$val);
        }
        return $val;
    }

    # Check for existance of ALL columns(fields) in file. Retruns TRUE if fine, or an Error Message
    public function checkFile($fileName) {
        $open = $this->open($fileName);
        if(!$open) {
            return $this->getErrorMessages(1);
        }

        if(count($this->missedFields)) {
            $errMsg = "";
            $erList = [];
            foreach($this->missedFields as $fid) {
                $erList[] = $this->fmtDefs[$fid]['header'];
            }
            if(is_callable('AppEnv::getLocalized'))
                $pref = AppEnv::getLocalized('importdata_err_missed_columns', 'Missed columns in the file');
            else $pref = 'Missed columns in the file';
            $this->err_message[] = $err = $pref . ':<br>' . implode('<br>', $erList);
            return $err;
        }
        return TRUE; # File contains ALL listed columns
    }
    public function getErrorMessages($asString=false) {
        return ($asString) ? implode(',',$this->err_message) : $this->err_message;
    }
}
