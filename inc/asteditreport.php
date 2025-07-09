<?php
/**
* ассистент для выдачи быстрых отчетов по кнопке на тулбаре при просмотре таблиц (astedit pack)
* @name <include>/asteditreport.php
* @version 0.10.001 added 2025-07-09
* modified 2025-07-09
*/

class AsteditReport {
    static $b_inited = FALSE;
    public static function init() {
        if(!self::$b_inited) {
            self::$b_inited = TRUE;
        }
    }
    public static function getJsCode($tabName) {
        $ret = <<< EOJS
asteditReport = {
  run: function(table,reportid) {
    // var params = {action:"runReport", "table":table, "reportid":reportid};
    var repUri = "./?p=asteditreport&action=runreport&table="+table+"&reportid="+reportid;
    window.open(repUri, "_blank");
  }
};
EOJS;
        return $ret;
    }
    /**
    * Генерирую отчет
    */
    public static function runReport() {
        include_once ('astedit.php');
        $tablename = AppEnv::$_p['table'] ?? '';
        $reportid = AppEnv::$_p['reportid'] ?? '';
        $tbl = new CTableDefinition('imutual_programs');
        # writeDebugInfo("tbl: ", $tbl);
        if(!isset($tbl->reports[$reportid])) exit("Неизвестный ИД отчета: $reportid");
        $repFields = $tbl->reports[$reportid]['fields'] ?? '';
        $repWhere = $tbl->reports[$reportid]['filter'] ?? '';
        if($repFields ==='' || $repFields==='*') $repFields = array_keys($tbl->fields);
        else $repFields = explode(',', $repFields);
        $repQuery = "SELECT " . implode(',',$repFields) . ' FROM ' . $tablename;
        if(!empty($repWhere)) $repQuery .= " WHERE ". $repWhere;
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
              'width' => 20,
              'format' => '',
            ];
            $cfg['fields'][$fldname] = $arFld;
        }
        /*
        exit('1' . AjaxResponse::showMessage('finally CFG for flexreport: <pre>' . print_r($cfg,1)
          . "<br>reports:". print_r($tbl->reports,1). '</pre>'));
        */

        include_once('flexreport.php');
        $flexRep = new \SelifanLab\FlexReport($cfg);
        # appEnv::$_p['fr_format'] = $_GET['fr_format'] = 'xlsx';
        $flexRep->execute(['fr_format'=>'xlsx']); # TODO: pass params?

        return FALSE;
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
