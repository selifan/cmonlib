<?php
/**
* @package ALFO Фронт-Офис
* @author Alexander Selifonov, <Aleksandr.Selifonov@zettains.ru>
* @name app/apputils.php
* всякие полезняшки, включая вызовы из API, AJAX
* @version 0.10.003
* modified : 2025-10-21, created 2025-09-19
*/
class AppUtils {

    const VERSION = '0.10';
    private static $debug = 0;
    private static $debugRemote = 0;
    private static $shortenLongLines = 256; # обрезать длинные строки в текстовых логах при показе

    public static function getVersion() { return self::VERSION; }

    # вернёт 1 если работа с БД сервера по сети
    public static function isRemoteWorking() {
        $remoteWrk = defined('REMOTE_CONNECT') ? defined('REMOTE_CONNECT') : FALSE;
        return $remoteWrk;
    }

    # вызов данных с мастер-сервера через API вызов
    public static function makeApiCall($funcName, $arParams) {
        $apiUrl = AppEnv::getConfigValue('mastersrv_url');
        $apiToken = AppEnv::getConfigValue('mastersrv_token');
        if(empty($apiUrl) || empty($apiToken))
            return ['result'=>'ERROR', 'message'=>'Не настроен URL или токен мастер-сервера'];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        $apiData = [
            'userToken' => $apiToken,
            'execFunc' => $funcName,
            'params' => $arParams
        ];

        $rawRequest = json_encode($apiData,JSON_UNESCAPED_UNICODE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawRequest);
        $result = curl_exec($ch);
        curl_close($ch);

        if(self::$debug) writeDebugInfo("makeApiCall result : ", $result);
        $ret = json_decode( $result, TRUE );
        if(isAjaxCall()) exit($ret['data'] ?? 'No data string');

        return $ret;
    }

    # AJAX запрос на вывод содержимого лог-файла в папке applogs/
    public static function viewAppLogFile($logName=FALSE) {
        if(empty($logName)) $logName = AppEnv::$_p['filename'] ?? '';
        if(empty($logName)) $ret = 'Не передан файл';
        else {
            if(self::isRemoteWorking()) {
                # получаю файл вызовом мастер-сервера через API вызов
                $apiResult = self::makeApiCall('getResource', ['function'=>'AppUtils::viewAppLogFile','parameters'=>$logName]);
                if(isset($apiResult['data'])) $ret = $apiResult['data'];
                else $ret = $apiResult['message'] ?? 'Ошибка вызова API';
                if(self::$debugRemote) {
                    writeDebugInfo("from master server, apiResult: ", $apiResult);
                    writeDebugInfo("from master server, ret to return: ", $ret);
                }
            }
            else {
                $realName = AppEnv::getAppFolder('applogs/') .$logName;
                if(!is_file($realName)) $ret = AjaxResponse::timedNotify("$logName - лог-файл не найден!");
                else $ret = AjaxResponse::showMessage("Содержимое файла $logName:<hr><pre>" . self::shortenTextFile($realName) . '</pre>');
            }
        }

        if(AppEnv::isAPiCall()) {
            return ['result'=>'OK', 'data' => ('1' . $ret)];
        }
        exit('1' . $ret);
    }

    # обрезаю слишком длинные строки в текстовом файле (для показа элементов типа base64 file body)
    public static function shortenTextFile($fname) {
        $ret = '';
        if(self::$shortenLongLines <= 0) return @file_get_contents($fname);
        $lines = @file($fname);
        if(is_array($lines)) {
            foreach($lines as &$oneLine) {
                $sLen = mb_strlen($oneLine, 'UTF-8');
                if($sLen > self::$shortenLongLines)
                    $oneLine = mb_substr($oneLine, 0, (self::$shortenLongLines-6), 'UTF-8') . " ...($sLen B)\n";
            }
            $ret = implode('', $lines);
        }
        return $ret;
    }

    # {upd/2025-09-18} полное удаление полиса, с файлами и прочим
    # $policyid можно передать массив со списком ИЛ (stmt_id) или "*" = "Удалить ВСЕ"
    public static function killAgreement($module, $policyid, $forced = FALSE) {
        if(!SuperAdminMode()) return "Нет прав для полного удаления!";
        if($policyid === '*') {
            $idList = AppEnv::$db->select(PM::T_POLICIES, ['fields'=>'stmt_id', 'where'=>['module'=>$module], 'associative'=>0]);
            if(is_array($idList) && count($idList))
                $ret = self::killAgreement($module, $idList, $forced);
            else $ret = "No [$module] policies!";

            return $ret;
        }

        if(is_array($policyid)) {
            $ret = '';
            foreach($policyid as $idItem) $ret .= self::killAgreement($module, $idItem, $forced);
            return $ret;
        }
        if($policyid<=0) return "Bad policyid";

        if(in_array($module, ['plsign','agentvr','investprod'])) return "В данном модуле удаление не работает";
        $plcData = PlcUtils::loadPolicyData($module, $policyid, FALSE);

        if(empty($plcData['stmt_id']) ) return "Полис $module:$policyid не найден";

        if(!empty($plcData['docflowstate']) && !$forced)
            return "Полис $policyid находится в СЭД и удалению не подлежит!";
        $dFiles = FileUtils::deleteFilesInAgreement($module, $policyid,'*');
        $ret = "Удалено файлов сканов:$dFiles<br>";
        $dInd = AppEnv::$db->delete(PM::T_INDIVIDUAL, ['stmt_id'=>$policyid]);
        $dBen = AppEnv::$db->delete(PM::T_BENEFICIARY, ['stmt_id'=>$policyid]);
        $ret .= "Удалено записей ФЛ/ЮЛ/ВП: ".($dInd+$dBen)."<br>";

        AppEnv::$db->delete(PM::T_SPECDATA, ['stmt_id'=>$policyid]);
        $dRisks = AppEnv::$db->delete(PM::T_AGRISKS, ['stmt_id'=>$policyid]);
        $ret .= "Удалено записей с рисками: $dRisks<br>";
        $dPol = AppEnv::$db->delete(PM::T_POLICIES, ['module'=>$module, 'stmt_id'=>$policyid]);
        $ret .= "Удаление записи полиса: $dPol<br>";
        AppEnv::logEvent("$module.DELETE PLC","Полное удаление договора $module:$policyid",0, $policyid,FALSE,$module);
        return "Удаление карточки [$module:$policyid]<br>".$ret;
    }

}

if(!empty(AppEnv::$_p['utlaction'])) {
    $utlaction = trim(AppEnv::$_p['utlaction']);
    if(method_exists('apputils',$utlaction))
        AppUtils::$utlaction();
}