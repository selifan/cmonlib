<?php
/**
* @name jsvc/jsvc_boxprod.php модуль обработки API-запросов для плагина boxprod (коробочные продукты)
* modified 2025-09-04
*/
namespace jsvc;
class jsvc_boxprod {
    static $MODULE = 'boxprod';
    static $debugSave = 0;
    protected $backend = NULL;
    protected $params = [];
    static $testingMode = ['']; # 1 - временно включить "заглушки" вместо реальной работы на некоторых ф-циях
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
            if(is_array($fromModule) && count($fromModule)) {
                $result = array_merge($result, $fromModule);
                # writeDebugInfo("from call: ", $fromModule);
                if( ($fromModule['result']??'')=='OK' || !isset($fromModule['message'])) {
                    $result['result'] = 'OK';
                    $result['message'] = 'Успех';
                }
            }
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

    # TODO: Преобразование входных параметров к формату ALFO, добавляю не заданные обязательные (с дефолтными значениями)
    public function importParams($funcName, $arData) {
        $ret = $arData;
        if(in_array($funcName, ['calculatePolicy','savePolicy','saveAgreement'])) {
            if(!empty($arData['policyid'])) {
                $ret['stmt_id'] = $arData['policyid'];
            }
            if(!isset($ret['subtypeid'])) $ret['subtypeid'] = '1'; # подтип варианта страховки
            if($funcName==='calculatePolicy' && !isset($ret['insrbirth']))
                $ret['insrbirth'] = date('d.m.Y', strtotime('-30 years -5 months')); # дату рождения беру на шару

            if(!isset($ret['no_benef']) && empty($ret['beneffullname1']))
                $ret['no_benef'] = 1; # сам включаю "ВП по закону"
        }
        if(!isset($ret['insurer_type'])) $ret['insurer_type'] = '1'; # ФЛ
        if(!isset($ret['equalinsured'])) $ret['equalinsured'] = '1'; # Стр-ль  = Застрахованный

        # конвертирую названия регонов, краёв в код по справочнику regions
        # TODO: адреса ВП с кодом 1,2,...
        foreach($ret as $key=>&$val) {
            if(substr($key, -11) ==='adr_country') {# все поля регионов - insradr_country, insd...
                $newVal = \DataFind::getRegionCodeByName($val);
                # writeDebugInfo("converted [$val] to: ", $newVal);
                $val = $newVal;
            }
        }

        if($funcName === 'saveAgreement') {
            # if(empty($ret['insrbirth_country'])) $ret['insrbirth_country'] = '-'; # место рождения
            if(empty($ret['insradr_countryid'])) $ret['insradr_countryid'] = \PlcUtils::ID_RUSSIA; # адрес рег, страна-Россия
            if(empty($ret['insrfadr_countryid'])) $ret['insrfadr_countryid'] = \PlcUtils::ID_RUSSIA; # адрес факт, страна-Россия
            if(empty($ret['tax_rezident'])) $ret['tax_rezident'] = \PlcUtils::ID_RUSSIA; # налоговое гражданство Стр-ля
            if(!isset($ret['send_korresp'])) $ret['send_korresp'] = 1; # куда слать корресп.
        }

        if(!$ret['equalinsured'] && !empty($ret['insdfam'])) {
            if(!isset($ret['insdrez_country'])) $ret['insdrez_country'] = \PlcUtils::ID_RUSSIA; # гражданство-Россия
            if(!isset($ret['insdadr_countryid'])) $ret['insdadr_countryid'] = \PlcUtils::ID_RUSSIA; # адрес рег, страна-Россия
            if(!isset($ret['insdfadr_countryid'])) $ret['insdfadr_countryid'] = \PlcUtils::ID_RUSSIA; # адрес факт, страна-Россия
        }
        if($funcName ==='generateDoc' && $this->params['doctypeid'] === 'soglasie_pdn') {
            if(empty($ret['insurer_fullname'])) {
                if(!empty($ret['insrfam']))
                    $ret['insurer_fullname'] = $ret['insrfam'] . ' ' . $ret['insrimia'] . ' '. ($ret['insrotch'] ?? '');
            }
            if(empty($ret['insrfulladdr'])) $ret['insrfulladdr'] = $ret['insradr_full'] ?? '';
            if(empty($ret['insrfulldoc']) && !empty($ret['insrdocno']))
                $ret['insrfulldoc'] = \PlcUtils::buildFullDocument($ret, 'insr',1);
        }
        return $ret;
    }
    # TODO: Преобразование результатов к выходному формату для обработки на сайте
    public function exportParams($funcName, $arData) {
        if(isset($arData['stmt_id'])) {
            $arData['policyid'] = $arData['stmt_id'];
            unset($arData['stmt_id']);
        }
        unset($arData['datefrom_max'],$arData['datefrom_max'],$arData['date_release_max'],$arData['clientid']
          ,$arData['insrid'],$arData['fin_plan'],$arData['stmt_stateid'],$arData['tranchedate'],$arData['comission'],$arData['policy_sa']);
        return $arData;
    }

    # калькуляция
    public function calculatePolicy() {
        # $apiCall = \AppEnv::isApiCall();
        # writeDebugInfo("apiCall[$apiCall] calling Calculate from backend");
        \AppEnv::$_p = $this->importParams(__FUNCTION__, $this->params);
        $result = $this->backend->calculate(TRUE);
        # writeDebugInfo("calculate returned ", $result);
        return $this->exportParams(__FUNCTION__, $result);
    }

    # проверка данных ФЛ по спискам/блокировкам/паспорт (finmonitor)
    public function checkCompliance() {
        $dta = \AppEnv::$_p = $this->importParams(__FUNCTION__, $this->params);
        $fmBackEnd = \AppEnv::getPluginBackend('finmonitor'); # $_plugins['finmonitor']->getBackend();
        if(self::$debugSave) writeDebugInfo("checkCompliance: parsed dta ", $dta);
        \AppEnv::setLightProcess(1);
        $pref = $this->params['pref'] ?? 'insr';
        $req = [
            'lastname' => ($dta[$pref.'fam'] ?? ''),
            'firstname' => ($dta[$pref.'imia'] ?? ''),
            'patrname' => ($dta[$pref.'otch'] ?? ''),
            'birthdate' => ($dta[$pref.'birth'] ?? ''),
            'document' => trim(($dta[$pref.'docser'] ?? ''). ' '. ($dta[$pref.'docno'] ?? '')),
        ];
        if(self::$debugSave) writeDebugInfo("check input Params: ", $this->params);
        if(self::$debugSave) writeDebugInfo("check prepared Params: ", $req);
        if(empty($req['lastname']) || empty($req['firstname']) || empty($req['lastname'])) {
            $ret = ['result'=>'ERROR', 'message'=>'Не задана фамилия, имя или дата рождения'];
            if(self::$debugSave) writeDebugInfo("error return: ", $ret);
            return $ret;
        }
        $result = $fmBackEnd->isError() ? FALSE : $fmBackEnd->request($req, 0);

        if(self::$debugSave) writeDebugInfo("raw check result: ", $result);

        if($result) {
            $fmStates = $fmBackEnd->getStates();
            $blocked = $fmStates['terrorist'] || $fmStates['blocked'] || $fmStates['terrorist'] || $fmStates['badpassport'] || $fmStates['blacklist'];
            $ret = ['result'=>'OK', 'data' => array_merge(['is_blocked'=>$blocked], $fmStates)];
            $req['time'] = time(); # для уникальности ПЭП кода
            $sesCode = \UniPep::createPepHash($req);
            $ret['data']['sescode'] = $sesCode;
        }
        else $ret = ['result'=>'ERROR', 'message'=>'Ошибка вызова сервиса проверки по спискам '];
        return $ret;
    }
    /**
    * генерация PDF - полис, согласие на обраб.ПДН и т.д.
    * пока заглушка!
    */
    public function generateDoc() {
        $docType = $this->params['doctypeid'] ?? '';
        $policyid = $this->params['policyid'] ?? '';
        \AppEnv::setLightProcess(1); # включаю упрощенное оформление

        if(empty($docType)) return ['result'=>'ERROR', 'message'=>'Не указан тип документа'];

        if(in_array(__FUNCTION__, self::$testingMode)) {
            # ф-ция пока в режиме "заглушки"
            $tmpName = \AppEnv::getAppFolder('app/') . 'test.pdf';
            $fileBody = file_get_contents($tmpName);
            $ret = [
              'result' => 'OK',
              'message' =>'Успех',
              'filename' => 'test.pdf',
              'filesize' => filesize($tmpName),
              'filebody' => base64_encode($fileBody),
            ];
            return $ret;
        }

        switch($docType) {
            case 'policy':

                if(!$policyid) return ['result'=>'ERROR', 'message'=>'Не передан ИД договора'];

                $rights = $this->backend->checkDocumentRights($policyid);
                if(empty($rights))
                    return ['result'=>'ERROR', 'message'=>'К документу нет доступа'];
                $plcPdf = $this->backend->print_pack($policyid, TRUE);
                # writeDebugInfo("print_pack result: ", $plcPdf);
                if(!empty($plcPdf)) {
                    if(is_array($plcPdf) && $plcPdf['result'] ==='OK') {
                        /*
                        [result] => OK
                        [filename] => policy-KEP-DRAFT2508190326-draft.pdf
                        [filesize] => 829866
                        [filepath] => C:\webdev\docs\alfo\tmp/policy-KEP-DRAFT2508190326-draft.pdf
                        */
                        $ret = ['result'=>'OK',
                          'filename' => strtr($plcPdf['filename'], ['-draft.'=>'.']),
                          'filesize' => $plcPdf['filesize'],
                          'filebody' => base64_encode(@file_get_contents($plcPdf['filepath'])),
                        ];
                    }
                    elseif(is_string($plcPdf) && is_file($plcPdf)) {
                        $ret = ['result'=>'OK',
                          'filename' => basename($plcPdf),
                          'filesize' => filesize($plcPdf),
                          'filebody' => base64_encode(@file_get_contents($plcPdf)),
                        ];
                    }
                    elseif(is_string($plcPdf) && substr($plcPdf,0,4)=='%PDF') {
                        $ret = ['result'=>'OK',
                          'filename' => "policy-$policyid.pdf",
                          'filesize' => strlen($plcPdf),
                          'filebody' => base64_encode($plcPdf),
                        ];
                    }
                    else {
                        $ret = ['result'=>'ERROR','message'=>'Ошибка при формировании PDF документа'];
                        writeDebugInfo("API/ALARM: Ошибка выдачи PDF полиса !");
                    }
                }
                else {
                    $ret = ['result'=>'ERROR','message'=>'Ошибка при формировании PDF документа'];
                    writeDebugInfo("API/ALARM: Ошибка выдачи PDF полиса !");
                }
                return $ret;

            case 'soglasie_pdn':
                # PDF с согласием на обраб.ПДн
                $pdnParams = self::importParams(__FUNCTION__, $this->params);
                if(!empty($this->params['policyid']))
                    $pdnParams = $this->params['policyid'];
                else {
                    /*
                    writeDebugInfo("no policyid, passed params ", $this->params);

                    $pdnParams['insurer_fullname'] = ($pdnParams['insrfam'] ?? 'XX'). ' '. ($pdnParams['insrimia'] ??'XX')
                      . ' '.( $pdnParams['insrotch'] ?? '');
                    $pdnParams['insrfulladdr'] = $pdnParams['insar_full'] ?? 'Без адреса';
                    $pdnParams['insrfulldoc'] = $pdnParams['insrfulldoc'] ?? 'Данных о паспорте нет';
                    */
                }
                # writeDebugInfo("pdn_soglasie params: ",$pdnParams);
                $ret = $this->backend->generateUnifiedPdn($pdnParams);
                return $ret;
            default:
                $ret = ['result'=>'ERROR', 'message'=>'Неизвестный тип документа: '.$docType];
                break;
        }

        return $ret;
    }

    # Создание проекта полиса / обновление данных, пока заглушка!
    public function saveAgreement() {

        # $this->params['insrfam'] = ''; # отладка ошибки
        # $this->params['insrbirth'] = ''; # отладка ошибки

        # $result = "TODO saveAgmt!";
        # \PolicyModel::$debug = 1;
        if(in_array(__FUNCTION__, self::$testingMode)) {
            $result = [
              'result'=>'OK',
              'data' => [
                'policyid' => rand(10000,90000),
                'policyno' => 'KEP-'.str_pad(rand(10000,900000), 8,'0',STR_PAD_LEFT)
              ]
            ];
        }
        else {
            \AppEnv::setLightProcess(1); # включаю упрощенное оформление
            # \PolicyModel::$debug = 3;
            \AppEnv::$_p = $this->importParams(__FUNCTION__, $this->params);
            if(self::$debugSave) writeDebugInfo("data to save in agmt: ", \AppEnv::$_p);
            $result = $this->backend->saveAgmt(TRUE);
            # writeDebugInfo("saveAgmt raw result ", $result);
            $result = $this->exportParams(__FUNCTION__, $result);
            if(self::$debugSave) writeDebugInfo("saveAgr result: ", $result);
            if(!empty($result['result']) && $result['result'] === 'OK' && isset($this->params['scan_files'])) {
                $policyid = $result['data']['policyid'] ?? $this->params['policyid'] ?? 0;

                # с данными полиса пришли и сканы документов (паспорт...)
                if($policyid) {
                    $result['data']['uploaded'] = [];
                    foreach($this->params['scan_files'] as $oneFile) {
                        $oneFile['policyid'] = $policyid;
                        $uploaded = $this->uploadDocument($oneFile);
                        # writeDebugInfo("upload result: ", $uploaded);
                        # кладу сообщение об успехе в массив с результатами
                        if($uploaded['result'] == 'OK')
                            $result['data']['uploaded'][] = "Загрузка файла $oneFile[scantype] - Успех";
                        else
                            $result['data']['uploaded'][] = "Загрузка файла $oneFile[scantype] - Неудача: ".$uploaded['message'];
                    }
                }
            }
        }
        return $result;
    }

    # STUB for boxprod
    public function updateStatusAgreement() {

        \AppEnv::setLightProcess(1); # включаю упрощенныое оформление
        $status = $this->params['status'] ?? '';
        $policyid = $this->params['policyid'] ?? '';
        if(empty($policyid) || empty($status))
            return ['result'=>'ERROR', 'message'=>'Не передан ИД полиса или статус'];

        if(in_array(__FUNCTION__, self::$testingMode))
            return ['result' => 'OK', 'data'=> ['policyid'=>$policyid, 'policyno'=>'KEP-00101012']];

        $rights = $this->backend->checkDocumentRights($policyid);
        if(empty($rights))
            return ['result'=>'ERROR', 'message'=>'К данному документу нет доступа'];
        switch(strtolower($status)) {
            case 'payed':
                if(empty($this->params['sescode'])) return ['result'=>'ERROR', 'message'=>'Не передан ПЭП-код (sescode)'];
                \AppEnv::$_p['plg'] = self::$MODULE;
                \AppEnv::$_p['id'] = $policyid;
                \AppEnv::$_p['datepay'] = date('Y-m-d');
                \AppEnv::$_p['eqpayed'] = '1'; # считаю что оплата была через эквайринг
                \AppEnv::$_p['sescode'] = $this->params['sescode'] ?? UniPep::createPepHash(1000, 'boxprod');

                $ret = $this->backend->setPayed();

                break;
            case 'cancel':
                # $ret = ['result'=>'ERROR', 'message' =>'Еще не готово'];
                $ret = $this->backend->cancelPolicy($policyid);
                break;
        }
        return $ret;
    }
    # получить договор
    public function getAgreement() {
        $policyid = $this->params['policyid'] ?? '';
        if(!$policyid ) return ['result'=>'ERROR', 'message'=>'Не передан ИД полиса'];
        /*
        $rights = $this->backend->checkDocumentRights($policyid);
        if(empty($rights))
            return ['result'=>'ERROR', 'message'=>'К данному документу нет доступа'];
        */
        if(in_array(__FUNCTION__, self::$testingMode))
            return ['result' => 'OK', 'data'=> ['policyid'=>$policyid, 'policyno'=>'KEP-00101012']];

        $rights = $this->backend->checkDocumentRights($policyid);
        if(empty($rights))
            return ['result'=>'ERROR', 'message'=>'К данному документу нет доступа'];
        $arPlc = $this->backend->loadPolicy($policyid, 'print');

        $ret = ['result' => 'OK', 'data'=> $this->exportParams(__FUNCTION__, $arPlc) ];
        return $ret;
    }

    public function readyToPay() {
        \AppEnv::$_p = $this->params;
        return ['result'=>'ERROR', 'message'=>'Пока не готов'];
    }
    public function checkPolicyState() {
        \AppEnv::$_p = $this->params;
        $policyid = $this->params['policyid'] ?? '';
        if(!$policyid ) return ['result'=>'ERROR', 'message'=>'Не передан ИД полиса'];
        # $myUserId = \AppEnv::getUserId(); # ID учетки, от имени которой делался полис через API
        $rights = $this->backend->checkDocumentRights($policyid);
        if(empty($rights))
            return ['result'=>'ERROR', 'message'=>'К документу нет доступа'];

        $plcdata = \PlcUtils::getPolicyData($this->module, $policyid);
        $arRet = ['result'=>'OK', 'message'=>'данные получены',
          'stateid'=>$plcdata['stateid'],
          'created'=>$plcdata['created'],
        ];
        if(!empty($plcdata['reasonid']) && $plcdata['stateid']< \PM::STATE_UWAGREED) {
            $arRet['uw_warning'] = 'Требуется андеррайтинг!';
            $agmtdata = \AgmtData::getData($module,$policyid);
            $uwReasons = $agmtdata['calc_uwreasons'];
            if(!empty($agmtdata['all_uwreasons'])) $uwReasons .= ($uwReasons ? ',':'') . $agmtdata['all_uwreasons'];
            if(empty($uwReasons)) $uwReasons = $plcdata['reasonid'];
            $arRet['uw_codes'] = $uwReasons;
        }
        return $arRet;
    }

    # С сайта загрузили скан (полиса,...)
    public function uploadDocument($pars = FALSE){

        \AppEnv::setLightProcess(1); # включаю упрощенное оформление
        if(!$pars) $pars = $this->params;
        $policyid = $pars['policyid'] ?? '';
        if(self::$debugSave) {
            $tmParams = $pars;
            if(isset($pars['filebody'])) $tmParams['filebody'] = substr($tmParams['filebody'],0,40) . '...'; #
            writeDebugInfo("uploadDocument passed params: ", $tmParams);
        }
        if(empty($policyid))
            return ['result'=>'ERROR', 'message'=>'Не указан ИД полиса'];

        $rights = $this->backend->checkDocumentRights($policyid);
        if(empty($rights)) {
            $ret = ['result'=>'ERROR', 'message'=>'К документу нет доступа'];
            if(self::$debugSave) writeDebugInfo("error: ", $ret);
            return $ret;
        }
        $fileType = $pars['scantype'] ?? 'passport_insr'; # default - паспорт страхователя
        $fileName = $pars['filename'] ?? '';
        $binBody = isset($pars['filebody']) ?
            @base64_decode($pars['filebody']) : FALSE;

        if(empty($binBody) || empty($fileType) || empty($fileName)) {
            $ret = ['result'=>'ERROR', 'message'=>'Не указан тип документа, имя файла либо не передано тело файла'];
            if(self::$debugSave) writeDebugInfo("error: ", $ret);
            return $ret;
        }

        # TODO: реализовать метод загрузки файла через $bkend->addScan

        $scparams = [
          'plg' => self::$MODULE,
          'doctype' => $fileType, # passport, stmt, agmt, anketa_ph, anketa_insd (анкета застрахованного),anketa_benef
          'id' => $policyid,
          'filename' => $fileName,
          'filebody' => $binBody,
        ];
        try {
            $ret = $this->backend->addScan($scparams);
        }
        catch (Exception $e) {
            # if (self::$debug)
            WriteDebugInfo('$bkend->addScan exception:', $e);
            $ret = ['result'=>'ERROR', 'message' => ($e->getMessage() . ' line '.$e->getLine() . ' file '.$e->getFile()) ];
        }

        if(self::$debugSave) writeDebugInfo("addScan ret: ", $ret);

        return $ret;
    }
    /**
    * Генерация отчета для партнера
    * Параметры: module, report_id, date_from, date_till, ...
    * generateReport - перенес в alfoservices - пусть будет единый вызов
    * moduleReport() останется для особых вызоыов внутри плагина
    */
    public function moduleReport() {
        $report_id = $this->params['report_id'] ?? '';
        $date_from = $this->params['date_from'] ?? '';
        $date_till = $this->params['date_till'] ?? '';
        $format = $this->params['format'] ?? 'array'; # "array" или "txt"
        if(empty($report_id)) return ['result'=>'ERROR', 'message' => 'Не указан ИД отчета' ];
        # тестовые данные (заглушка)
        $reportData = [
          [ 'deptid'=>'001', 'incomde'=>200.45],
          [ 'deptid'=>'002', 'incomde'=>400.45],
          [ 'deptid'=>'003', 'incomde'=>680.40],
        ];
        $ret = [
          'result'=>'OK',
          'data' => ['rows'=> $reportData]
        ];
        return $ret;
    }
    # Получить список программ - under construction!
    public function getAvailablePrograms($pars=0) {
        # writeDebugInfo("getPifPrograms params: ", $pars);
        $prgList = \boxprod::getAvailablePrograms();
        # writeDebugInfo("getAvailablePrograms return: ", $prgList);
        return $prgList;
    }
}