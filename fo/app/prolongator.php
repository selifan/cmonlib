<?php
/**
* app/prolongator.php
* Класс с ф-циями для пролонгации полисов
* @version 1.21.002
* modified 2025-12-16
*/
class Prolongator {

    public static $DAYS_ADVANCE = 30; # За сколько дней до оконч можно стартовать пролонгацию
    public static $DAYS_AFTER_END = 3; # 7; # В теч-е скольких дней после оконч можно стартовать пролонгацию
    public static $lossBlocking = FALSE; # TRUE = наличие убытков по полису блокирует пролонгацию
    static $debug = 0;
    static $tailJs = '';
    static $prolongAddrMode = FALSE; # при пролонгации: режим получения адреса стр-ля/застрах из ALFO,есди в нем есть старый полис
    static $pModules = ['lifeag', 'oncob','pochvo' ]; # в этих модулях приоритетный поиск данных о пролонгации - в ALFO
    # список имен полей спец-параметров, которые можно автоматом брать из пролонгированного в новый полис
    static $passSpecParams = [ 'work_type','work_company','work_address','work_inn','work_duty','work_action','tax_rezident','notify_payment','send_korresp'];

    # @since 1.09 {upd/2022-08-10} инициализация переменных
    public static function init($debug = NULL) {
        if($debug !== NULL) self::$debug = $debug;
        $dAdvance = Appenv::getConfigValue('prolong_days_advance');
        $dAfter = Appenv::getConfigValue('prolong_days_afterend');
        if($dAdvance || $dAfter)
            self::setLimits($dAdvance, $dAfter);
    }
    # задать свои лимиты пролонгации - дней ДО и ПОСЛЕ даты окончания пролонгируемого полиса
    public static function setLimits($daysBefore, $daysAfter=0) {
        if ($daysBefore>0) self::$DAYS_ADVANCE = $daysBefore;
        if ($daysAfter>0) self::$DAYS_AFTER_END = $daysAfter;
    }
    public static function setBlockingLoss($value = TRUE) {
        self::$lossBlocking = $value;
    }

    /**
    * Вернет HTML блок с кнопкой "Пролонгация" для поиска пролонгируемого полиса
    *
    * @param mixed $callback имя callback ф-ции "получить данные"
    * @param mixed $action
    * @param mixed $mandatory - TRUE если обязательна пролонгация
    */
    public static function buttonBlock($callback = '', $action = '', $mandatory = FALSE, $toggleEvt = '') {
        if (empty($callback)) $callback = "policyModel.seekProlongData('$action')";
        if ($mandatory) {
            $ret = <<< EOHTM
<div class="p-2 bordered" id="div_prolongation" title="Загрузить данные полиса, подлежащего пролонгации">Пролонгация:
   Введите номер пролонгируемого полиса: <input type="text" class="iboxm w160" name="prolong_policyno" id="prolong_policyno" required />
   <input type="button" class="btn btn-primary" onclick="$callback" value="Получить данные" />
</div>
EOHTM;
        }
        else {
            if (empty($toggleEvt)) $toggleEvt = "policyModel.toggleProlongInput('$action')";
            $toggleHide = empty($toggleEvt) ? 'policyModel.toggleProlongInput()' : $toggleEvt;
            $ret = <<< EOHTM
<div id="div_prolongation" class="row p-2 bordered">
<div class="col-2">
<input type="button" class="btn btn-primary" onclick="$toggleEvt" value="Пролонгация" title="Загрузить данные полиса, подлежащего пролонгации" />
</div>
<div class="col-md-10 col-12" id="blk_prolong_input" style="display:none">
<span class="text-nowrap">Введите номер пролонгируемого полиса:</span>
<input type="text" class="form-control d-inline w160" id="prolong_policyno" required />
<input type="button" class="btn btn-primary" onclick="$callback" value="Получить данные" />
<input type="button" class="btn btn-primary" onclick="$toggleHide" value="Отмена" />
</div>
</div>
EOHTM;
        }
        return $ret;
    }
    /**
    * ajax запрос на поиск пролонгируемого полиса
    * Можно передать $action - имя операции, в которой выполняется поиск (agredit - ввод данных, calc - на калькуляторе
    * @param mixed $action
    */
    public static function findOriginalPolicy($pno = FALSE, $module = FALSE, $return=FALSE) {
        if ($pno) $policyno = $pno;
        else $policyno = (appEnv::$_p['policyno'] ?? '');
        $clientid = intval(appEnv::$_p['clientid'] ?? 0);
        # TODO: если передан ИД клиента, и Страх-тель = Застрах-ный, надо сверять дату рожд-я (и ФИО не помешает)
        # exit('1' . AjaxResponse::showMessage('params: <pre>' . print_r(AppEnv::$_p,1) . '</pre>'));
        self::init();
        if (self::$debug) writeDebugInfo("findOriginalPolicy $policyno, days_advance: ", self::$DAYS_ADVANCE);
        if (self::$debug) writeDebugInfo("findOriginalPolicy params: ", appEnv::$_p);
        if(!$module && !empty(appEnv::$_p['module'])) $module = appEnv::$_p['module'];
        $action= isset(appEnv::$_p['action']) ? appEnv::$_p['action'] : '';
        # if(in_array($module, DataFind::$fAlfoModules)) DataFind::setSeekOrderAlfo(1);

        $prol = DataFind::isPolicyProlonged($policyno);
        if ($prol) {
            # writeDebugInfo("pars ", appEnv::$_p);
            if (empty(appEnv::$_p['stmt_id']))
            # if($return) return FALSE;
                exit('1'.AjaxResponse::showError("Указаный полис уже был пролонгирован,<br>новый : ". $prol['policyno'],'Пролонгация недопустима'));
        }

        $data = DataFind::findPolicyByPolicyNo($policyno, 1, $module, 'P');
        if (!isset($data['policyno'])) {
            $errMsg = "Полис $policyno не найден!";
            if($return) return ['result'=>'ERROR', 'message'=>$errMsg];
            exit('1'. AjaxResponse::showError($errMsg));
        }
        if (self::$debug>1) writeDebugInfo("found policy for $module: ", $data);

        if($clientid) {
            $cliData = \BindClient::getClientData($clientid,'',100,TRUE);
            $birthPrev = $data['insured'][0]['birth'] ?? $data['pholder']['birth'] ?? '';
            $birthCli  = $cliData['birth'] ?? '';

            if($birthCli != $birthPrev) {
                if(self::$debug) writeDebugInfo("даты рождения: в пролоонгуируемом: $birthPrev, client: ", $cliData);
                $errMsg = "Даты рождения Застрахованного в пролонгируемом полисе (".to_char($birthPrev) . ')<br> и у Клиента ('.to_char($birthCli). ') не совпадают!';
                if($return) return ['result'=>'ERROR', 'message'=>$errMsg];
                exit('1' . AjaxResponse::showError($errMsg));
            }
        }


        $bkEnd = FALSE;
        if($module) {
            $bkEnd = appEnv::getPluginBackend($module);
        }
        if (is_object($bkEnd) && method_exists($bkEnd, 'isLossBlockingProlong')) {
            self::$lossBlocking = $bkEnd->isLossBlockingProlong();
        }

        if (self::$lossBlocking && !empty($data['loss'])) {
            exit('1'. AjaxResponse::showError("По полису $policyno есть убытки, пролонгация невозможна!"));
        }
        $err = FALSE;
        if(self::$debug) writeDebugInfo("Policy to prolong: ", $data);
        if( !$return && (!PlcUtils::$prolongDebug || appEnv::isProdEnv()) ) {
            # проверяю дату окончания пролонгируемого!
            $mindate = date('Y-m-d', strtotime('-'.self::$DAYS_AFTER_END. ' days'));
            $maxDate = date('Y-m-d', strtotime('+'.self::$DAYS_ADVANCE. ' days'));
            if(self::$debug) writeDebugInfo("prolongation: check if dateill $data[datetill] between $mindate AND $maxDate");
            $ended = to_char($data['datetill']);
            if ($data['datetill'] < $mindate) $err = "Период действия полиса $policyno закончился ($ended) более "
              . self::$DAYS_AFTER_END.' дней назад, пролонгации не подлежит!';
            elseif ($data['datetill'] > $maxDate) $err = "До окончания действия полиса $policyno ($ended) более "
              . self::$DAYS_ADVANCE.' дней!';
        }
        if ($err) exit('1'. AjaxResponse::showError($err));

        # {upd/2023-03-23} - сразу блокирую пролонгацию при превышении настроенного максимума
        if(!empty($data['history']) && is_array($data['history'])) {
            $maxProlong = AppEnv::getConfigValue('ins_limit_prolong_count',0);
            $pAction = AppEnv::getConfigValue('ins_limit_prolong_action', 'B');
            # writeDebugInfo("prolong cfg: $maxProlong / $pAction history", $data['history']);
            if($maxProlong>1 && count($data['history']) >= $maxProlong && $pAction === 'B') {
                exit('1' . AjaxResponse::showError('Договор не подлежит пролонгации, превышено допустимое число пролонгаций'));
            }
        }
        # writeDebugInfo("applyProlongPolicyData-$action:");
        $specMethod = 'applyProlongPolicyData' . $action;
        if(self::$debug) writeDebugInfo("KT-001,$module, try plugin method ", $specMethod);

        if (is_object($bkEnd) && method_exists($bkEnd, $specMethod)) {
            $ret = $bkEnd->$specMethod($data); # метод может и сам сделать exit(code) и сюда уже не придти!
            if (self::$debug) writeDebugInfo("ajax code returned by plugin/$specMethod: ",$ret);
            if ($return) return $ret;
            exit($ret);
        }

        # writeDebugInfo("prolong: start normal fields fill"); # считаю что нахожусь на agredit форме (ФИО стр, Застрах...)
        $fullFields = ['rez_country','birth_country','doctype','docser','docno','docpodr','docissued','married','phone',
          'adr_zip','adr_countryid','adr_country','adr_region','adr_city','adr_street','adr_house','adr_corp','adr_build','adr_flat','sameaddr',
          'fadr_zip','fadr_countryid','fadr_country','fadr_region','fadr_city','fadr_street','fadr_house','fadr_corp','fadr_build','fadr_flat',
        ];
        $fullDates = ['docdate'];
        $postCmd = '';
        if(self::$debug) writeDebugInfo("KT-002");
        # Готовлю данные для отправки на форму ввода полиса
        $phNames = preg_split("/[ ]/", $data['pholder']['fullname'],-1,PREG_SPLIT_NO_EMPTY);
        $ul = (empty($data['pholder']['birth']) || intval($data['pholder']['birth'])==0); # стр-ЮЛ
        if ($ul) {
            $formData = [
              'insurer_type' => '2',
              'prolong' => (isset($data['alfo_id']) ? $data['alfo_id'] : $data['policyno']),
              'datefrom' => to_char(AddToDate($data['datetill'],0,0,1)),
              'insrurname' => $data['pholder']['fullname'],
              'insrbirth' => '',
              'insremail' => $data['pholder']['email'],
            ];
            $postCmd .= AjaxResponse::enable('#instype_1', 0);
        }
        else {
            $formData = [
              'insurer_type' => '1',
              'prolong' => (isset($data['alfo_id']) ? $data['alfo_id'] : $data['policyno']),
              'datefrom' => to_char(AddToDate($data['datetill'],0,0,1)),
              'insrfam' => $phNames[0],
              'insrimia' => (!empty($phNames[1]) ? $phNames[1]:''),
              'insrotch' => (!empty($phNames[2]) ? $phNames[2]:''),
              'insrbirth' => (intval($data['pholder']['birth']) ? to_char($data['pholder']['birth']):''),
              'insremail' => $data['pholder']['email'],
            ];
            $postCmd .= AjaxResponse::enable('#instype_2', 0);
            if (!empty($formData['insrotch'])) {
                $last2 = mb_strtolower(mb_substr($formData['insrotch'],-2,2,MAINCHARSET));
                if($last2 == 'на') $formData['insrsex'] = 'F';
                else $formData['insrsex'] = 'M';
            }
        }
        if (!empty($data['pholder']['fulladdr'])) { # данные из LISA, адрес одной строкой, пытаюсь выцепить что можно
            # TODO: если полис есть и в ALFO, адреса лучше брать из него - будет более точный
            if(self::$prolongAddrMode) {
                $alfoPholder = self::findPersonInAlfo($policyno, 'insr');
            }
            $adrData = RusUtils::parseFullAddress($data['pholder']['fulladdr']);
            # writeDebugInfo("parse ".$data['pholder']['fulladdr'] .' to: ', $adrData);
            if (is_array($adrData) && count($adrData)) foreach($adrData as $key=>$val) {
                $formData['insradr_'.$key] = $val;
            }
            if (!empty($data['pholder']['mobilephone'])) {
                list($fpref, $ffone) = RusUtils::SplitPhone($data['pholder']['mobilephone']);
                if (!empty($fpref) && substr($fpref,0,1)==='9') {
                    # $formData['insrphonepref'] = $fpref;
                    $formData['insrphone'] = "($fpref)$ffone";
                }
            }
        }
        else {
            foreach($fullFields as $fld) {
                if (isset($data['pholder'][$fld])) $formData['insr'.$fld] = $data['pholder'][$fld];
            }
            foreach($fullDates as $fld) {
                if (!empty($data['pholder'][$fld])) $formData['insr'.$fld] = to_char($data['pholder'][$fld]);
            }
        }
        if(!empty($data['child'])) {
            foreach($fullFields as $fld) {
                if (isset($data['child'][0][$fld]) && $data['child'][0][$fld]!='')
                    $formData['child'.$fld] = $data['child'][0][$fld];
            }
            foreach($fullDates as $fld) {
                if (isset($data['child'][0][$fld]) && intval($data['child'][0][$fld]))
                    $formData['child'.$fld] = to_char($data['child'][0][$fld]);

            }
        }
        if(is_object($bkEnd) && method_exists($bkEnd, 'prepareProlongData'))
            $bkEnd->prepareProlongData($formData, $data);

        $ret = '1';

        # writeDebugInfo("orig/data:", $data);
        $fromAlfo = !empty($data['alfo_id']); # признак, что пролонгируемый полис взят из ALFO!
        $equalinsured = !empty($data['insured'][0]['is_pholder']) || !empty($data['equalinsured']);
        # Страх = ЗАстрахованному!
        if ($equalinsured) {
            if ($return) $formData['equalinsured'] = 1;
            else $ret .= AjaxResponse::setValue('equalinsured', 1);
        }
        else {
            # надо заполнить 1-ого застрахованного
            if ($return) $formData['equalinsured'] = 0;
            else $ret .= AjaxResponse::setValue('equalinsured', 0);
            $insd = 'insd';
            # Р-К детский: главный застрахованный имеет свой префикс в полях ввода!
            if (method_exists($bkEnd, 'getInsuredPrefix'))
                $insd = $bkEnd->getInsuredPrefix($data);
            if (empty($insd)) $insd = 'insd';
            if (self::$debug) writeDebugInfo("insured prefix: $insd");
            # $phNames = preg_split("/[ ]/", $data['insured'][0]['fullname'],-1,PREG_SPLIT_NO_EMPTY);
            if(empty($formData[$insd.'fam']) && !empty($data['insured'][0]['imia'])) {
                $formData[$insd.'imia'] = $data['insured'][0]['imia'];
                $formData[$insd.'fam'] = $data['insured'][0]['fam'];
                $formData[$insd.'otch'] = $data['insured'][0]['otch'];
                $formData[$insd.'birth'] = !empty($data['insured'][0]['birth']) ? to_char($data['insured'][0]['birth']) : '';
            }
            # $formData[$insd.'sex'] = !empty($data['insured'][0]['sex']) ?$data['insured'][0]['sex']) : '';
        }
        if(isset($data['no_benef'])) $formData['no_benef'] = $data['no_benef'];
        if($fromAlfo) {
            # исходный полис в ALFO - добираю в пролонгацию всё что можно
            $bkend = AppEnv::getPluginBackend($module);
            $specParams = PlcUtils::loadSpecParams($data['alfo_id']);
            # writeDebugInfo("spec params for $data[alfo_id]: ", $specParams);
            foreach(self::$passSpecParams as $parname) {
                if(isset($specParams[$parname])) {
                    $formData[$parname] = $specParams[$parname];
                }
            }
            # догружаю ВПриобр
            if(empty($data['no_benef'])) {
                $bendata = Persons::loadBeneficiaries($bkend,$data['alfo_id'],'benef',TRUE);
                if(isset($bendata['beneffullname1'])) foreach($bendata as $benKey => $benVal) {
                    if($benVal!=='')
                        $formData[$benKey] = $benVal;
                }

            }
        }
        if (self::$debug) writeDebugInfo("return: ", $formData);
        if ($return) return $formData;

        foreach($formData as $fld => $val) {
            if (self::$debug>2) writeDebugInfo("prolong val $fld = $val");
            $ret .= AjaxResponse::setValue($fld, $val);
        }
        if($postCmd) $ret .= $postCmd;
        # writeDebugInfo("formData ", $formData);
        $dt1 = to_char($data['datefrom']);
        $dt2 = to_char($data['datetill']);
        $remark = "Пролонгация полиса $data[policyno], период действия с $dt1 по $dt2";
        # Вместо блока ввода будет строка про пролонгируемый полис! (запрет повторных поисков)
        $ret.= AjaxResponse::setHtml('div_prolongation', $remark);
        if (self::$debug) writeDebugInfo("ajax return code: ", $ret);

        # если в процессе загрузки "прошлого2 полиса появились сообщений, показать тут же!
        if ($sMessages = appEnv::getShowMessages())
            $ret .= AjaxResponse::showMessage($sMessages, 'Внимание!');

        if(self::$tailJs) { # финальные директивы для исполнения
            $ret .= self::$tailJs;
            self::$tailJs = '';
        }
        exit($ret);
        #exit('1'. AjaxResponse::showMessage("Полис $policyno нашелся!"));
    }
    /**
    * в хвост отправляемой цепочки команд findOriginalPolicy() добавить финальные инструкции в формате AjaxResponse
    * @since 1.07
    */
    public static function appendJsCommand($param) {
        self::$tailJs .= $param;
    }
    # {upd/2021-09-28} Создали проект договора, который надо пересчитать с UW-коэффициентами - посылаю письмо андеррайтеру
    public static function demandRecalcUw($module, $data, $toAddr = '') {
        $pno = $data['policyno'];
        $prevNo = $data['previous_id'];
        $recid = $data['stmt_id'];
        $ag = CmsUtils::getUserInfo($data['userid']);
        $agentFio = "$ag[lastname] $ag[firstname] $ag[secondname]";
        $compTitle = appEnv::getConfigValue('comp_title');
        $url = PlcUtils::getLinkViewAgr($module, $recid);

        if (empty($toAddr))
            $toAddr = appEnv::getConfigValue('lifeag_email_uw');
        $msg = <<< EOHTM
В системе ALFO (Фронт-Офис $compTitle) создан проект договора $pno (пролонгация договора $prevNo),
для которого агент запросил андеррайтерский перерасчет.
Инициатор договора (агент) : $agentFio ( $ag[email] ).
Просьба зайти по указанной ниже ссылке сделать перерасчет с сохранением.

Ссылка : <a href="$url">$url</a>.

Конфиденциальная информация!
EOHTM;
        $opts = [
            'to' => $toAddr,
            'subj'=>"ALFO: Выполнить перерасчет по договору $pno",
            'message' => $msg
        ];
        $logPref = strtoupper($module);
        $sent = appEnv::sendEmailMessage($opts);
        if ($sent)
            appenv::logEvent("$logPref.ASK UW_RECALC", "Андеррайтеру послано уведомлении о необх.перерасчета",0, $recid);
        else
            appenv::logEvent("$logPref.ASK UW_RECALC ERROR", "Неудачная попытка отправки сообщ-я Андеррайтеру!",0, $recid);
        return $sent;
    }

    # UW-перерасчет сделан, шлю агенту уведомление - можно продолжить работу
    public static function sendNotifeRecalcDone($module, $data) {
        $url = PlcUtils::getLinkViewAgr($module, $data['stmt_id']);
        $fullUrl = "<a href=\"$url\">$url</a>";
        $pno = $data['policyno'];
        $opts = [
            'to' => Cmsutils::getUserEmail($data['userid']),
            'subj'=>"ALFO: $pno - перерасчет выполнен",
            'message' => "По заведенному Вами договору $pno андеррайтером выполнен перерасчет."
              . "\nМожно продолжить работу. Ссылка: $fullUrl\n\nКонфиденциальная информация!"
        ];
        $sent = appEnv::sendEmailMessage($opts);
        return $sent;
    }
    /**
    * стандартная проверка веденных данных на соотв-вие с пролонгируемым полисом
    *
    * @param mixed $obj backend-объект модуля
    * @param mixed $policyno номер старого полиса
    * @since 1.11 2023-04-20
    */
    public static function checkNewData($obj, $policyno) {
        $module = $obj->module;
        $par =& AppEnv::$_p;
        $data = DataFind::findPolicyByPolicyNo($policyno, 1, $module, 'P');
        $oldEqualInsured = !empty($data['insured'][0]['is_pholder']);
        $newEqualInsured = !empty($par['equalinsured']);
        if($oldEqualInsured != $newEqualInsured) $obj->_err[] = 'Пролонгация: недопустимо менять параметр [Застрахованный = Страхователю]';
        if($par['insurer_type'] == 1) {
            if($data['pholder']['fam'] != $par['insrfam'] || $data['pholder']['imia'] != $par['insrimia']
              || $data['pholder']['otch'] != $par['insrotch']) {
                # $obj->_err[] = 'Пролонгация: изменение ФИО Страхователя';
                PlcUtils::setUwReasonHardness(1,PM::UW_REASON_PERSON_CHANGE);
            }
        }

        if(self::$debug) {
            writeDebugInfo("checkNewData, prev policy data: ", $data);
            writeDebugInfo("uqualinsured:[$oldEqualInsured]=[$newEqualInsured] passed data: ", $par);
        }
        else {
            # TODO: проверка названия ЮЛ
        }
        if(!$newEqualInsured || $par['insurer_type'] == 2) {
            if($data['insured']['fam'] != $par['insdfam'] || $data['insured']['imia'] != $par['insdimia']
              || $data['insured']['otch'] != $par['insdotch']) {
                # $obj->_err[] = 'Пролонгация: изменение ФИО Застрахованного';
                PlcUtils::setUwReasonHardness(1,PM::UW_REASON_PERSON_CHANGE);
            }
        }
    }
    # Ищу полис в ALFO, и если есть - беру из него данные о субъекте ('insr' - Страхователь, 'insd'-Застрахованный
    public static function findPersonInAlfo($policyno, $ptype='insr') {
        $ret = FALSE;
        $plc = \AppEnv::$db->select(PM::T_POLICIES, ['fields'=>'stmt_id,module,policyno', 'where'=>['policyno'=>$policyno],'singlerow'=>1]);
        if(!empty($plc['stmt_id']))
            $ret = Persons::getPersonData($plc['stmt_id'], $ptype);
        return $ret;
    }
}

if (isAjaxCall()) {
    if (!empty(appEnv::$_p['prolongaction'])) {
        $action = appEnv::$_p['prolongaction'];
        if(method_exists('Prolongator', $action))
            Prolongator::$action();
    }
}