<?php
/**
* ассистент для выдачи быстрых отчетов по кнопке на тулбаре при просмотре таблиц (astedit pack)
* @name <include>/asteditreport.php
* @version 0.10.002 added 2025-07-09
* modified 2025-07-11
*/

class AsteditReport {
    static $b_inited = FALSE;
    static $outFormat = 'xlsx'; # Can be html, xml, txt
    public static function init() {
        if(!self::$b_inited) {
            self::$b_inited = TRUE;
        }
    }

    public static function getJsCodeReport($tabName, $pagecall='asteditreport') {
        $nocache = rand(100000, 999999999);
        $retJs = <<< EOJS
asteditReport = {
  run: function(table,reportid) {
    // var params = {action:"runReport", "table":table, "reportid":reportid};
    var repUri = "./?ajax=1&nocache=$nocache&p=$pagecall&action=runreport&table="+table+"&reportid="+reportid;
    window.open(repUri, "_blank");
  }
};
EOJS;
        return $retJs;
    }

    public static function getReportButtonHtml($tableid, $reportid, $rep_label='Отчет', $rep_title='') {
        if(class_exists('asteditHelper') && is_callable('asteditHelper::getReportButtonHtml'))
            $btnHtml = asteditHelper::getReportButtonHtml($tableid, $reportid, $rep_label, $rep_title);
        else {
            if(empty($rep_label)) $rep_label = self::getButtonLabel([$tableid, $reportid]);
            if(empty($rep_title)) $rep_title = self::getButtonTitle([$tableid, $reportid]);
            $rTitle = $rep_title ? "title='$rep_title'" : '';
            $btnHtml = "<input type=\"button\" id=\"$reportid\" class=\"button\" value=\"$rep_label\" "
              . "onclick=\"asteditReport.run('$tableid','$reportid')\" $rTitle/>";
        }
        return $btnHtml;
    }

    /**
    * Generate report
    */
    public static function runReport() {
        include_once ('astedit.php');
        $tablename = AppEnv::$_p['table'] ?? '';
        $reportid = AppEnv::$_p['reportid'] ?? '';
        if(empty($tablename) || empty($reportid)) throw new Exception(__FIL__ . ':'. __LINE__ . " - Empty tanle or report name!");
        $tbl = new CTableDefinition($tablename);
        # writeDebugInfo("tbl: ", $tbl);

        if(!isset($tbl->reports[$reportid])) throw new Exception("$tablename: Unknown report name: $reportid");

        $repFields = $tbl->reports[$reportid]['fields'] ?? '';
        $repWhere = $tbl->reports[$reportid]['filter'] ?? '';

        $viewFilter = $tbl->PrepareFilter();
        if($repWhere && $viewFilter) $repWhere = "$repWhere AND $viewFilter";
        elseif(!empty($viewFilter)) $repWhere = $viewFilter;

        if($repFields ==='') $repFields = $tbl->viewfields; # вывести поля, показываемые в гриде
        elseif($repFields==='*') $repFields = array_keys($tbl->fields); # вывести ВСЕ поля
        else $repFields = preg_split('/[;, ]/', $repFields,-1,PREG_SPLIT_NO_EMPTY); # строка со списком через зпт

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
            AppEnv::appendHtml($htmlResult);
            AppEnv::finalize();
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
$callP = AppEnv::$_p['p'] ?? '';
$action = AppEnv::$_p['action'] ?? '';
if(strtolower($callP) == 'asteditreport') {
    if(!empty($action)) {
        if(method_exists('AsteditReport', $action))
            AsteditReport::$action();
        else exit("AsteditReport::$action - unsupported operation!");
    }
}
