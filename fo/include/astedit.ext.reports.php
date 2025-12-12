<?php
/**
* @package webapp/astedit
* @name include/astedit.ext.reports.php
* Astedit extension
* кнопка и генератор отчета по текущей таблице, замена макросу REPORT|...
* modified 2025-12-12 / created 2025-12-09
* @version 0.10.003
*/
namespace astedit;
class Reports {

    static $b_inited = FALSE;
    static $baseUrl = '';
    static $asteditObj = NULL;
    static $table = '';
    static $reportid = '';
    static $fields = '';
    static $filter = '';
    static $btnLabel = '';
    static $btnTitle = '';
    static $outFormat = 'xlsx'; # Can be html, xml, txt
    public static function init() {
        if(!self::$b_inited) {
            self::$b_inited = TRUE;
        }
    }
    /** @param $params: report_id, field list, filter,text, title
    * [0] => ins_programs
    * [1] => id,programname,visible_name,codirovka,prikaz_date,prikaz_no
    * [2] => b_active=1
    * [3] => текст в кнопке
    * [4] => title кнопки
    */
    public function __construct(\CTableDefinition $asteditObj, $params=NULL)  {
        self::$asteditObj = $asteditObj;
        $tableName = self::$table = $asteditObj->tbrowseid;
        self::$baseUrl = $_SERVER['REQUEST_URI'] ?? $asteditObj->getBaseUri();
        # writeDebugInfo("SERVER ", $_SERVER);
        self::$reportid = $params[0] ?? 'report_'.$tableName;
        self::$fields = $params[1] ?? implode(',', $asteditObj->viewfields);
        self::$btnLabel = $params[3] ?? \AppEnv::getLocalized('astedit_report_label','Отчет');
        self::$btnTitle = $params[4] ?? \AppEnv::getLocalized('astedit_report_title','Выгрузить в отчет');
        $buttonHtml = self::getButtonHtml($tableName,self::$reportid);
        self::$asteditObj->addToolbarElement($buttonHtml);
        # кнопа выгрузки как customColumn:
        # $dneJs = \DneAssist::getJsCode([$tableName, $reloadGridCode]);
        $myJs = self::getJsCode();
        self::$asteditObj->addJsCode($myJs);
    }
    # Генерация HTML кода кнопки "импорт из XML", для вывода на тулбар под гридом astedit
    public static function getJsCode() {
        $baseUrl = self::$baseUrl;
        # self::$baseUrl;
        if(strpos($baseUrl, '?')!==FALSE) $baseUrl .='&';
        else $baseUrl .='?';
        $baseUrl .= "ajax=1&astreport_action=runreport";

        $nocache = rand(100000, 999999999);
        $retJs = <<< EOJS
asteditReport = {
  run: function(table,reportid) {
    // var params = {action:"runReport", "table":table, "reportid":reportid};
    var randNo = Math.floor(Math.random() * 99999999);
    var repUri = "$baseUrl&table="+table+"&reportid="+reportid+"&rnd="+randNo;
    window.open(repUri, "_blank");
  }
};
EOJS;
        return $retJs;
    }

    public static function getButtonHtml($tableid, $reportid) {
        $rep_label = self::$btnLabel;
        $rep_title = self::$btnTitle;
        $rTitle = $rep_title ? "title='$rep_title'" : '';
        $btnHtml = "<input type=\"button\" id=\"$reportid\" class=\"btn btn-primary me-1\" value=\"$rep_label\" "
          . "onclick=\"asteditReport.run('$tableid','$reportid')\" $rTitle/>";

        return $btnHtml;
    }

    /**
    * Generate report
    */
    public static function runReport() {
        /*
        if(!class_exists('CTableDefinition')) {
            if(WebApp::$useDataTables) include_once('astedit.datatables.php');
            else include_once('astedit.php');
        }
        */
        # writeDebugInfo("params: ", \Appenv::$_p);
        # writeDebugInfo("text ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        $tablename = \AppEnv::$_p['table'] ?? self::$table;
        $reportid = \AppEnv::$_p['reportid'] ?? self::$reportid;
        if(empty($tablename) || empty($reportid)) throw new Exception(__FILE__ . ':'. __LINE__ . " - Empty tanle or report name!");


        $repFields = self::$fields;
        # writeDebugInfo("KT-001 fields:", $repFields);
        $repWhere = self::$filter;
        if(is_object(self::$asteditObj)) { $tbl = self::$asteditObj; writeDebugInfo("use old"); }
        else {
            $tbl = new \CTableDefinition($tablename);
        }
        $viewFilter = $tbl->PrepareFilter();
        if($repWhere && $viewFilter) $repWhere = "$repWhere AND $viewFilter";
        elseif(!empty($viewFilter)) $repWhere = $viewFilter;

        $params = \astedit::$extensionParams["reports-$tablename-$reportid"] ?? [];
        $repFields = $params[1] ?? $tbl->viewfields;

        if($repFields==='*') $repFields = array_keys($tbl->viewfields); # вывести ВСЕ поля
        elseif($repFields=='*') $repFields = array_keys($tbl->viewfields); # вывести ВСЕ поля
        else $repFields = preg_split('/[;, ]/', $repFields,-1,PREG_SPLIT_NO_EMPTY); # строка со списком через зпт

        # exit(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($repFields,1) . '</pre>');

        $repQuery = "SELECT " . implode(',',$repFields) . ' FROM ' . $tablename;
        if(!empty($repWhere)) $repQuery .= " WHERE ". $repWhere;

        if($orderBy = $tbl->prepareOrder()) {
            $repQuery .= " ORDER BY " . $orderBy;
        }
        # exit($repQuery); # debug
        # Делаю готовый конфиг для FlexReport
        $cfg = [
          'headings' => [
          'background' => 'EEEEEE',
          'border' => '000000',
          'color' => '000000',
          'align' => 'C',
          ],
          'title' => ($tbl->reports[$reportid]['title'] ?? 'Report'),
          'export' => [ 'filename' => $reportid ],
          'query' => $repQuery,
          'fields' => [],
        ];
        foreach($repFields as $fldname) {
            $flDef = $tbl->fields[$fldname] ?? FALSE;
            if(!$flDef) continue;
            $arFld = [
              'title' => (!empty($flDef->shortdesc) ? $flDef->shortdesc : (!empty($flDef->desc) ? $flDef->desc : $fldname)),
              'width' => self::calculateFieldWidth($flDef,20),
              'format' => '',
            ];
            $cfg['fields'][$fldname] = $arFld;
        }
        /**
        exit('1' . AjaxResponse::showMessage('finally CFG for flexreport: <pre>' . print_r($cfg,1)
          . "<br>reports:". print_r($tbl->reports,1). '</pre>'));
        **/

        include_once('flexreport.php');
        $flexRep = new \SelifanLab\FlexReport($cfg);
        if(self::$outFormat === 'html') {
            $htmlResult = $flexRep->execute(['fr_format'=>'html']);
            \AppEnv::appendHtml($htmlResult);
            \AppEnv::finalize();
        }
        else {
            $flexRep->execute(['fr_format'=>self::$outFormat]);
        }
    }
    # определяет ширину колонок при выгрузке в XLSX (по типу и длине поля)
    public static function calculateFieldWidth($fDef, $defaultVal=16) {
        # echo(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($fDef,1) . '</pre>'); return 10;
        $flen = intval($fDef->length);
        switch($fDef->type) {
            case 'VARCHAR':
                return min(32, floor($flen/2.5));
            case 'CHAR': return max(10, $flen);
            case 'BIGINT': case 'PKI': case 'PK': case 'DECIMAL': case 'FLOAT':
                return 12;
            case 'INT': case 'TINYINT': case 'BOOL':
                return 8;
            case 'TEXT': case 'SMALLTEXT':
                return 30;
            default: return $defaultVal;
        }
    }

}
$callP = \AppEnv::$_p['p'] ?? '';
$action = \AppEnv::$_p['astreport_action'] ?? '';
if(!empty($action)) {
    if(method_exists('\astedit\Reports', $action))
        Reports::$action();
    else exit("Astedit\\Reports::$action - unsupported operation!");
}

