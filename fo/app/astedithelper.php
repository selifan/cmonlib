<?php
/**
* @package ALFO
* @name app/astedithelper.php
* Вспомогательные функции для Astedit - CRUD утилиты
* modified 2025-07-11 / started 2023-08-16
*/
class AsteditHelper {
    private static $debug = FALSE;
    # полное удаление всех файлов и записей к полису, перед его окончательным удалением
    # alf_agreements.tpl : BEFOREDELETE|AgreemBeforeDelete
    public static function AgreemBeforeDelete($recid) {
        $realdelete = TRUE; # false - для тестирования, без реального удаления данных
        $recdta = AppEnv::$db->select(PM::T_POLICIES, array('where'=>"stmt_id=$recid", 'singlerow'=>1));
        $plcno = $recdta['policyno'];
        $module = strtoupper($recdta['module']);
        if ( $bkend = AppEnv::getPluginBackend(strtolower($module)) )
            $pref = $bkend->getLogPref();
        else $pref = $module;

        if (!$realdelete && self::$debug) WriteDebugInfo("$module/$plcno ($recid) row to delete:", $recdta);
        $files = AppEnv::$db->select(PM::T_UPLOADS, array('where'=>"stmt_id=$recid", 'orderby'=>'id'));
        if (is_array($files)) foreach($files as $no => $fone) {
            $fullname = $fone['path'] . $fone['filename'];
            if ($realdelete) {
                if (is_file($fullname))  {
                    unlink($fullname);
                    # WriteDebugInfo("$plcno: File $fullname deleted");
                    AppEnv::logEvent("$module.DEL FILE", "$plcno : Удаление файла $fone[filename]", false, $recid);
                }
                else {
                    if(self::$debug) WriteDebugInfo("[$no] To delete file in policy:", $fullname);
                }
            }

        }
        if ($realdelete) {
            # AppEnv::$db->log(3);
            AppEnv::$db->delete(PM::T_UPLOADS, "stmt_id=$recid");
            AppEnv::$db->delete(PM::T_INDIVIDUAL, "stmt_id=$recid");
            AppEnv::$db->delete(PM::T_AGMTDATA, "policyid=$recid");
            AppEnv::$db->delete(PM::T_SPECDATA, "stmt_id=$recid");
            AppEnv::$db->delete(PM::T_BENEFICIARY, "stmt_id=$recid");
            AppEnv::$db->delete(PM::T_AGRISKS, "stmt_id=$recid");
            AppEnv::logEvent($pref . "POLICY DEL", "$plcno : полное удаление полиса из БД администратором",false, $recid);
            # AppEnv::$db->log(0);
            return true;
        }
        else {
        # WriteDebugInfo("files in policy:", $files);
            exit("AgreemBeforeDelete, emulate deleting $plcno record ". $recid);
        }
    }
    # аолучить js код для работы отчетов из грида astedit
    public static function getJsCodeReport($tabName) {
        return asteditReport::getJsCodeReport($tabName, 'astedithelper');
        /*
        $nocache = rand(100000, 999999999);
        $ret = <<< EOJS
asteditReport = {
  run: function(table,reportid) {
    // var params = {action:"runReport", "table":table, "reportid":reportid};
    var repUri = "./?ajax=1&nocache=$nocache&p=astedithelper&action=runreport&table="+table+"&reportid="+reportid;
    window.open(repUri, "_blank");
  }
};
EOJS;
        return $ret;
    */
    }
    # формирует HTML код кнопки "Отчет"
    public static function getReportButtonHtml($tableid, $reportid, $rep_label='Отчет', $rep_title='') {
        if(empty($rep_label)) $rep_label = self::getReportButtonLabel([$tableid, $reportid]);
        if(empty($rep_title)) $rep_title = self::getReportButtonTitle([$tableid, $reportid]);
        $rTitle = $rep_title ? "title='$rep_title'" : '';
        $btnHtml = "<input type=\"button\" id=\"$reportid\" class=\"btn btn-primary me-1\" value=\"$rep_label\" "
          . "onclick=\"asteditReport.run('$tableid','$reportid')\" $rTitle/>";
        return $btnHtml;
    }
    # Пытаюсь получить локализ.строку для кнопки Отчет
    public static function getReportButtonLabel($params=[]) {
        $table = isset($params[0]) ? $params[0] : '';
        $reportid = isset($params[1]) ? $params[1] : '';
        $ret = AppEnv::getLocalized("astedit_report_label_{$table}_{$reportid}", 'Отчёт');
        return $ret;
    }
    # Пытаюсь получить локализ.строку для атрибута title кнопки Отчет
    public static function getReportButtonTitle($params=[]) {
        $table = isset($params[0]) ? $params[0] : '';
        $reportid = isset($params[1]) ? $params[1] : '';
        $ret = AppEnv::getLocalized("astedit_report_title_{$table}_{$reportid}", '');
        return $ret;
    }

    public static function runReport() {
        asteditrep::runReport();
        // $tablename = AppEnv::$_p['table'] ?? '';
        // $reportid = AppEnv::$_p['reportid'] ?? '';
    }
}
$callP = AppEnv::$_p['p'] ?? '';
$action = AppEnv::$_p['action'] ?? '';
if(strtolower($callP) == 'astedithelper') {
    if(!empty($action)) {
        if(method_exists('AsteditHelper', $action))
            AsteditReport::$action();
        else exit("AsteditHelper::$action - unsupported operation!");
    }
}
