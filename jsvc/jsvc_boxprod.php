<?php
/**
* @name jsvc/jsvc_boxprod.php модуль обработки API-запросов для плагина boxprod (коробочные продукты)
* modified 2025-07-18
*/
namespace jsvc;
class jsvc_boxprod {
    static $MODULE = 'boxprod';
    protected $backend = NULL;
    protected $params = [];

    public function executeRequest($params) {
        # writeDebugInfo(__FUNCTION__, " params: ", $params);
        $this->backend = \AppEnv::getPluginBackend(self::$MODULE);
        $this->params = $params['params'];
        $result = [ 'result'=>'ERROR', 'message'=>'Неверный вызов' ];
        $apiCall = \AppEnv::isApiCall();

        $func = $params['execFunc'] ?? '';
        # writeDebugInfo("apiCall=[$apiCall] func: $func");
        $fromModule = [];
        if(!empty($func)) {
            if(method_exists($this,$func)) {
                $fromModule = $this->$func();
                # writeDebugInfo("apiCall=[$apiCall] called inner $func, result: ", $fromModule);
            }
            elseif(method_exists($this->backend,$func)) {
                $fromModule = $bkEnd->$func($this->params);
                # writeDebugInfo("apiCall=[$apiCall] called inner $func, result: ", $fromBk);
            }
            else $result['message'] = "Неизвестное имя функции: $func";
            # writeDebugInfo("call from backend: ", $result);
            if(is_array($fromModule) && count($fromModule))
                $result = array_merge($result, $fromModule);
            /*
            if(!empty($fromModule['result']) && $fromModule['result']==='ERROR') {
                $result['result'] = 'ERROR';
                $result['message'] = $fromModule['message'] ??  'неизвестная ошибка';
            }
            else {
                $result['result'] = 'OK';
                $result['message'] = 'Данные получены';
                $result['data'] = $fromModule;
            }
            */
        }
        else $result['message'] = "Не передано имя вызываемой функции";
        # writeDebugInfo("returning: ", $result);
        return $result;
    }
    # калькуляция
    public function calculatePolicy() {
        $apiCall = \AppEnv::isApiCall();
        # writeDebugInfo("apiCall[$apiCall] calling Calculate from backend");
        \AppEnv::$_p = $this->params;
        $result = $this->backend->calculate(TRUE);
        # writeDebugInfo("calculate returned ", $result);
        return $result;
    }
    # Создание проекта полиса
    public function savePolicy() {
        $this->params['insrfam'] = ''; # отладка ошибки
        $this->params['insrbirth'] = ''; # отладка ошибки
        \AppEnv::$_p = $this->params;
        # $result = "TODO saveAgmt!";
        # \PolicyModel::$debug = 1;
        $result = $this->backend->saveAgmt(TRUE);
        # writeDebugInfo("saveAgmt result ", $result);
        return $result;
    }
    # Получить список программ
    public function getAvailablePrograms($pars=0) {
        # writeDebugInfo("getPifPrograms params: ", $pars);
        $prgList = \boxprod::getAvailablePrograms();
        # writeDebugInfo("getAvailablePrograms return: ", $prgList);
        return $prgList;
    }
}
