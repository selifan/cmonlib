<?php
/**
* @package ALFO
* @name app/alfojobs.php
* Все ф-ции для запуска в ежедневных заданиях (jobs) ALFO - здесь
* Тестовый вызов (без отправки почты, только вывод на экран)
* ./?p=alfojobs&jobsaction=sendPendingAgmtNotify&testing=1
* @version 106 modified 2025-09-10
*/
class AlfoJobs {
    /**
    * рассылка напоминаний агентам/менеджерам (и в ПП,UW) о зависших договорах
    * (приближается Макс.Дата выпуска - МДВ)
    * @param mixed $testing
    * @param mixed $debug
    */
    const BTXFIELD_POLICY = 9; # ID польз.поля в Битрикс - номер полиса у клиента (в таблице b_utm_user)
    static $verbose = 0;
    static private $seekEmails = []; # сюда буду сохранять результаты поиска по учеток email, чтоб не искать дважды один и тот же
    static private $keepAbandonedPlc = 5; # Через сколько дней глушить заброшенные онлайн-полисы
    private static $sqlError = '';
    private static $digisignAlertDays = 30; # за сколько дней до сгорания ЭЦП сертификатов начать кипеш

    public static function sendPendingAgmtNotify($testing=FALSE, $debug=0) {
        # $debug = 1; # 1 or TRUE: временное выключение отправки email, вывод писем в файлы, 2-только лог писем
        if(isset($_GET['testing'])) $testing = $_GET['testing'];
        if(!empty(AppEnv::$_p['testing'])) $testing = AppEnv::$_p['testing'];
        # через сколько дней с момента оформления начать бомбить:
        # Больше не использую, весь ф-ционал в alfo_pending_releasedays
        # $penDays = AppEnv::getConfigValue('alfo_send_pendingagmt');
        # if ($penDays <=0) return '';

        $uwEmail = AppEnv::getConfigValue('lifeag_email_uw');
        # {upd/2022-01-11} - напоминание агентам/менеджерам за N дней до макс.даты выпуска
        /*
        $beforeMaxRel = AppEnv::getConfigValue('alfo_send_before_maxrel', 0);

        if ($beforeMaxRel <=0 || (!$testing && PlcUtils::isHoliday())) # по выходным не делаю!
            return '';
        */
        $ret = '';
        $opts = [
          # 'created_days'=>$penDays,
          #'before_max_rel' => $beforeMaxRel,
          # 'alarm_max_rel' => 2, # страшное предупреждение за 2 дня до макс.даты выпуска
        ];
        # {updt/2022-06-15} - добавил к-во дней, через которые перестать слать уведомления
        # (отстать от чела - видать, помер):
        if($rdays = AppEnv::getConfigValue('alfo_pending_releasedays'))
            $opts['release_days'] = $rdays;
        else
            $opts['release_days'] = 0; # больше 60 дней не бомбить?

        if($testing) $ret .= "Дни до МДВ: " . $opts['release_days'] . '<br>';

        if($opts['release_days']<=0) return $ret;

        $pending = DataFind::getPoliciesByCriteria($opts);
        if($testing>1) $ret .= "seek policies SQL:".AppEnv::$db->getLastQuery() . '<br>error:'. AppEnv::$db->sql_error(). '<br>';
        # return $pending;
        # echo 'array to notify <pre>' . print_r($pending,1). '</pre>'; return;

        if(!is_array($pending) || count($pending)==0) return $ret;
        $users = [];
        foreach($pending as $item) {
            $userid = $item['userid'];
            if (!isset($users[$userid])) $users[$userid] = [];
            $users[$userid][] = $item;
        }
        unset($pending);

        # if($testing) $ret .= '<br>' . __FILE__ .':'.__LINE__.' users:<pre>' . print_r($users,1) . '</pre>';

        $ccAddr = appEnv::getConfigValue('alfo_send_pending_cc');
        if(!isValidEmail($ccAddr)) $ccAddr = '';

        $today = date('Y-m-d');
        foreach($users as $userid => $data) {

            $usrInfo = CmsUtils::getUserInfo($userid);
            $email = $usrInfo['email'];
            if(!empty($_GET['testblocked']) && $testing)
                $usrInfo['blocked'] = 1; # отладка пересылки от уволенных агентов

            if (!isValidEmail($email) || !empty($usrInfo['blocked'])) {
                # continue; # {upd/2022-06-15} Учетка блокирована, юзер может быть уже уволен - пропускаю!
                $email = 'PP'; # агент уволен либо некорр.адрес email, шлю в ПП
            }
            # PP - отправим письмо в ПП нужного канала
            if (!empty($usrInfo['test'])) continue; # тестовые учетки - ничего не шлем!

            if($email === 'PP')
                $message = "(Перенаправлено в ПП в связи с отключением учетной записи $usrInfo[lastname] $usrInfo[firstname] $usrInfo[secondname]).\n\n";
            else
                $message = "Уважаемый(ая) $usrInfo[firstname] $usrInfo[secondname] !\n\n";
            $yesterday = date('Y-m-d', strtotime('-1 days'));
            if($testing) $ret .= "Агент $usrInfo[lastname] $usrInfo[firstname]<br>" . self::drawGridFromArray($data);
            foreach($data as $item) {
                $uwMessage = '';
                # exit(__FILE__ .':'.__LINE__.' policy item:<pre>' . print_r($item,1) . '</pre>');
                $toPP = $toUW = 0; # письмо может еще отправляться в ПП и андеррайтерам
                # кому еще послать, в зависимости от статуса карточки
                switch($item['stateid']) {
                    case PM::STATE_DRAFT:
                    case PM::STATE_PROJECT:
                    case PM::STATE_IN_FORMING:
                    case PM::STATE_POLICY:
                    case PM::STATE_PAYED:
                        $toPP = 1;
                        break;
                    case PM::STATE_UNDERWRITING:
                        if(empty($item['docflowstate'])) {# в СЭД еще не ушло
                            $toPP = 1;
                        }
                        else { # андеррайтер уже завел карточку в СЭД, шлем еще и на UW
                            $toPP = $toUW = 1;
                        }
                        break;
                    case PM::STATE_UWAGREED:
                        $toPP = $toUW = 1;
                }
                $metaType = $item['metatype'];
                $viewLink = PlcUtils::getLinkViewAgr($item['module'], $item['stmt_id']);
                $plcLink = "<a href=\"$viewLink\">$item[policyno]</a>";
                $relMaxDMY = to_char($item['date_release_max']);
                $subj = "Выпуск полиса $item[policyno]";

                if($item['date_release_max'] < $yesterday) {
                    continue; # после 1 дня сгорания уже не пишу, вопрос - делать ли тут сброс в проект
                }
                elseif($item['date_release_max'] == $yesterday) {
                    $subjType = 2;
                    $subj = "Просрочен выпуск полиса $item[policyno]";
                    if($item['stateid'] == PM::STATE_UNDERWRITING)
                        $baseText = "Внимание! Просрочена Максимальная дата выпуска по Договору $plcLink."
                          ." Для дальнейшего выпуска полиса дождитесь обновления и согласования условий со стороны андеррайтера\n";
                    elseif($item['stateid'] == PM::STATE_UWAGREED)
                        $baseText = "Внимание! Просрочена Максимальная дата выпуска по Договору $plcLink."
                          . " Для дальнейшего выпуска полиса дождитесь пересчета условий со стороны андеррайтера и обновления Максимальной даты выпуска.\n";

                    else
                        $baseText = "Внимание! Просрочена Максимальная дата выпуска по Договору $plcLink."
                          . " Чтобы продолжить выпуск, необходимо обновить условия полиса.";
                     # Раскомментировать если надо сразу делать сброос в проект
                     # $expired = \PlcUtils::isPolicyExpired($item);
                     # if($expired == 2) PlcUtils::resetExpiredPolicy($plcdata,'auto');
                }
                else {
                    $subjType = 1;
                    if($metaType == OrgUnits::MT_BANK) {
                        $baseText =
                          "В системе \"Фронт-Офис\" по Договору $plcLink приближается дата, до которой возможно выпустить и оплатить полис на текущих условиях, "
                         ."в противном случае потребуется повторный пересчет и согласование новых условий страхования. Выпустить полис необходимо до $relMaxDMY включительно.";
                    }
                    else { # агенты и другие "каналы"
                        $baseText =
                          "В системе \"Фронт-Офис\" по Договору $plcLink приближается дата, до которой возможно оплатить и выпустить полис на текущих условиях, "
                         ."в противном случае потребуется повторный пересчет и согласование новых условий страхования. Выпустить полис необходимо до $relMaxDMY включительно.";
                    }
                }
                $subj = ($subjType==1) ? "Выпуск полиса $item[policyno]" : "Просрочен выпуск полиса $item[policyno]";
                $agentBody = $message . $baseText;
                $to = $email;
                if($email === 'PP')
                    $to = \OrgUnits::getEmailPP($item['metatype']);

                if($testing) {
                    $ret .= "<br>send to PP:[$toPP], to UW:[$toUW]<pre class='lt p-5'>to: $to\ncc:$ccAddr\nsubj:$subj <br>Text: $agentBody</pre>";
                    $sent = 1;
                }
                else {
                    $sent = appEnv::sendEmailMessage([
                       'to' => $to,
                       'cc' => $ccAddr,
                       'subj' => $subj,
                       'message' => $agentBody
                    ]);
                    if ($sent) {
                        $theme = ($subjType==1) ? 'невыпущенном полисе':'просроченном выпуске полиса';
                        $ret .= "На адрес $to отправлено уведомление о $theme $plcLink";
                        $sentAdm = FALSE;
                        if($toPP || $toUW) {
                            $toList = [];
                            # Если основное письмо вместо агента ушло на ПП, второй раз уже не шлю!
                            if($toPP && $email!=='PP') $toList[] = \OrgUnits::getEmailPP($item['metatype']);
                            if($toUW) { # UW - свой текст письма!
                                if(empty($uwMessage)) {
                                    if($subjType == 1)
                                        $uwMessage = "Внимание! Приближается Максимальная дата выпуска по Договору $plcLink."
                                        ."\nПросьба согласовать до $relMaxDMY включительно. Спасибо.";
                                    else
                                        $uwMessage = "Внимание! Просрочена Максимальная дата выпуска по Договору $plcLink ($relMaxDMY)."
                                        ."\nЧтобы продолжить выпуск, просьба обновить условия полиса и согласовать. Спасибо.";
                                }
                                $sentUw = appEnv::sendEmailMessage([
                                   'to' => $uwEmail,
                                   'cc' => $ccAddr,
                                   'subj' => $subj,
                                   'message' => $uwMessage
                                ]);
                            }
                            if(count($toList)) {
                                $sentAdm = appEnv::sendEmailMessage([
                                   'to' => $toList,
                                   'subj' => $subj,
                                   'message' => $baseText
                                ]);
                            }
                            if($sentAdm) $ret .= " (продублировано в ПП" . ($toUW ? ' и UW':'') . ')<br>';
                        }
                    }
                }
            }
        }
        return $ret;
    }
    /**
    * Проверка наличия графика траншей для всех типов полисов
    * @return text string - строку с результатами работы, для общего журнала задания
    * {upd/2019-12-06} - Добавлена подробная проверка наличия траншей (окон продаж) по тренд/купонным продуктам
    */
    public static function checkTrancheDates() {

        $allDisab = AppEnv::getConfigValue('alfo_disable_activity',0);
        $invDisab = AppEnv::getConfigValue('invprod_disable_activity',0);
        if($allDisab || $invDisab) return ''; # новые полисы не выписываются!

        $ret = "<h3>ИСЖ: Проверка справочника дат траншей</h3>\r\n<pre>";

        $types = InvIns::getActivePrgTypes(); # только типы от программ, которые действуют
        if(is_array($types)) foreach ($types as $prod) {
            $last_tranch = AppEnv::$db->select(PM::TABLE_TRANCHES,
                array('where'=>array('prodtype'=>$prod, 'closeday>=sysdate()'),'orderby'=>'openday DESC','singlerow'=>1,'associative'=>1)
            );
            if (!isset($last_tranch['closeday'])) {
                $ret .= "Полисы типа $prod: нет заведенных траншей на ближайший период !\n";
                continue;
            }
            $lastdate = intval($last_tranch['closeday'])? $last_tranch['closeday'] : $last_tranch['openday'];
            $today = date('Y-m-d');
            $monthplus = AddToDate($today,0,1,0);
            if ($lastdate<=$monthplus) $ret .= "Для полисов $prod заканчиваются заведенные даты траншей (заведите до ".to_char($lastdate).")\n";
            else  $ret .= "Для полисов $prod есть даты траншей до ".to_char($lastdate)."\n";
            # TODO: по необходимости вызывать импорт траншей из Лизы ?
        }
        if(!AppEnv::isDeadModule(PM::INVEST)) {
            $invbkend = appEnv::getPluginBackend(PM::INVEST);
            $trendChk = $invbkend->chkTranches();
            if ($trendChk || 1) {
                $ret .= "<h4>Проверка наличия траншей для TREND и купонных полисов</h4>" . $trendChk;
            }
        }
        return ($ret.'</pre>');
    }

    public static function checkStmtRanges() {
        $ret = "<b>Проверка наличия диапазонов номеров для полисов/договоров</b><br>";
        $thres = appEnv::$POOL_WARNING_THRESHOLD1; # 100 До конца диапазона - предупреждай!
        $lst = appEnv::$db->select(PM::T_STMT_RANGES, array('where'=>"(currentno+$thres >=endno) AND rangestate>0"));
        if(is_array($lst) && count($lst)>0) {
            foreach($lst as $rng) {
                $rest = max(0, $rng['endno'] - $rng['currentno']);
                $mdl = $rng['module'] ? getPluginTitle($rng['module']) : '[для всех]';
                $codestr = $rng['codelist'] ? "/ кодировки: $rng[codelist]" : '';
                $ret .= "В диапазоне $rng[rangeid] (модуль: $mdl $codestr) - остаток номеров : $rest\n<br>";
            }
            $ret .= "<br>Пожалуйста, своевременно убедитесь в наличии дополнительных диапазонов для указанных кодировок/продуктов или увеличьте конечные номера!<br>";
        }
        return $ret;
    }
    # получаем недостающие курсы валют с основного (прод) сервера ALFO
    public static function RatesFromProd($verbose=FALSE) {
        $prodAddr = appEnv::getConfigValue('mastersrv_url');
        $eol = (php_sapi_name()==="cli") ? "\n" : '<br>';
        $ret = '';
        if($prodAddr) {
            $ret = "Получение курсов валют с прод.ALFO $prodAddr ".$eol;
            $result = \Libs\DataSync::getCurrencyRates($verbose);
            if(is_array($result)) foreach($result as $curr=>$cnt) {
                $ret .= "$curr: $cnt $eol";
            }
            else $ret .= print_r($result,1);
        }
        return $ret;
    }
    # блокирую заявки на онлайн-оплату, у которых период действия истек
    public static function killExpiredPayments() {
        $updQry = "update ".PM::T_EQPAYMENTS . ' SET is_payment=11 WHERE is_payment=0 AND timeto>NOW()';
    }
    # красиво вывожу массив в табличку
    public static function drawGridFromArray($data) {
        $keys = array_keys($data[0]);
        $ret = "<table class='zebra'><tr><th>" . implode('</th><th>',$keys) . '</tr>';
        foreach($data as $row) {
            $ret .= "<tr>";
            foreach($keys as $key) {
                $ret .= '<td>' . ($row[$key] ?? '') . '</td>';
            }
            $ret .= '</tr>';
        }
        $ret .= '</table>';
        return $ret;
    }

    # {upd/2024-05-27} для джоба - авто-добавление новых полисов (из Лизы) в учётку клиента
    public static function autoAddPolicyToClient() {
        global $clcabDB, $bitrixDB;
        $activity = \AppEnv::getConfigValue('alfo_auto_plc_bind');
        if(empty($activity)) return '';
        $testing = ($activity < 10) ; # режим тестирования выбирается через глоб.настройки ALFO
        self::$seekEmails = [];
        if (empty($clcabDB)) $clcabDB = 'AGENTCAB20';
        if (empty($bitrixDB)) $bitrixDB = 'cc20prd';
        $where = ['state_id=1'];

        # на проде беру БОЛЬШЕ крайних полисов
        $maxRows = (AppEnv::isProdEnv() ? 100 : 20);
        $newPolicies = \AppEnv::$db->select("$clcabDB.usrInsurPolicyContractLisa",
          ['fields'=>'contract_id,policyno_no,ph_email,ph_fullname',
            'where'=>$where,
            'orderby'=>'contract_id DESC',
            'rows' => $maxRows
          ]);
        if(!$newPolicies) return "no policies by Query:<br> ".AppEnv::$db->getLastQuery();
        # return $newPolicies; # PIT STOP 1

        if(!is_array($newPolicies) || !count($newPolicies)) return '';
        $testMode = ($testing ? '(тестовый режим)' : '');
        $ret = "<h3>Авто-привязка новых полисов к УЗ клиентов $testMode</h3>";
        $errCount = 0;

        foreach($newPolicies as $lisaPol) {
            # 1) ищу, не привязан ли уже этот полис к кому-нибудь
            $binded = AppEnv::$db->select("$bitrixDB.b_utm_user", [
              'where' => [ 'VALUE'=> $lisaPol['policyno_no'], 'FIELD_ID'=>self::BTXFIELD_POLICY ],
              'fields'=>'ID,VALUE_ID,VALUE',
              'singlerow'=>1
            ]);
            if(!empty($binded['VALUE_ID'])) {
                if($testing || self::$verbose) $ret .= "<br>Полис $lisaPol[policyno_no] уже имеет привязку к УЗ $binded[VALUE_ID]";
                continue;
            }
            # Ищу по email клиента, кому привязать полис
            $email = self::parseBtxEmail($lisaPol['ph_email']);

            if(empty($email)) continue;
            $clients = self::seekAccountByEmail($email);
            if(!is_array($clients) || !count($clients)) {
                if($testing || self::$verbose) $ret .= "<br>$lisaPol[policyno_no]: УЗ по email $email не найдена"; #.AppEnv::$db->getLastQuery();
                /*
                if($testing) {
                    $arReg = [
                      'contractid' => $lisaPol['contract_id'],
                      'policyno' => $lisaPol['policyno_no'],
                      'userid' => '9999999',
                      'linkdate' => '{now}',
                    ];
                    # тест-регистрирую новую привязку в своем логе
                    $regLink = \AppEnv::$db->insert(PM::T_PLCBIND, $arReg);
                }
                */
                continue;
            }
            if(count($clients)>1) { # несколько учеток с таким email
                if($testing || self::$verbose) $ret .= "<br>$lisaPol[policyno_no]: по email $email найдено более одной активной УЗ"; #.AppEnv::$db->getLastQuery();
                continue;
            }
            # TODO: добавляем полис в список клиенту
            if($testing)
                $ret .= "<br><b>$lisaPol[policyno_no] ($lisaPol[contract_id]) надо добавить клиенту ".$clients[0]['ID']
                  .' / '.$clients[0]['LAST_NAME'].' '.$clients[0]['NAME'] . '</b>';
            else {
                $arAdd = [
                  'FIELD_ID' => self::BTXFIELD_POLICY,
                  'VALUE_ID' => $clients[0]['ID'],
                  'VALUE' => $lisaPol['policyno_no']
                ];

                $added = AppEnv::$db->insert("$bitrixDB.b_utm_user",$arAdd);
                if($added) {
                    $ret .= "Полис <b>$lisaPol[policyno_no]</b> ($lisaPol[contract_id]) зарегистрирован на УЗ ".$clients[0]['ID']
                      . ' / '.$clients[0]['LAST_NAME'].' '.$clients[0]['NAME'] . '<br>';
                    $arReg = [
                      'contractid' => $lisaPol['contract_id'],
                      'policyno' => $lisaPol['policyno_no'],
                      'userid' => $clients[0]['ID'],
                      'linkdate' => '{now}',
                    ];
                    # регистрирую новую привязку в своем логе
                    $regLink = \AppEnv::$db->insert(PM::T_PLCBIND, $arReg);
                    if(!$regLink) $ret .= "/ош.записи в журнал: ". \AppEnv::$db->sql_error();
                }
                else {
                    $ret .= "<br><font color='red'>Полис $lisaPol[policyno_no] - ОШИБКА регистрации  в УЗ ".$clients[0]['ID']
                      . ', err: '.AppEnv::$db->sql_error().'</font>';
                    if(++$errCount > 10) break; # после N ошибок прекращаем
                }
            }
        }
        return $ret;
    }
    #  В учетках битрикса ищу УЗ по адресу EMAIL (с кешированием в пределах работы скрипта)
    public static function seekAccountByEmail($email) {
        global $clcabDB, $bitrixDB;
        $email = strtolower($email);
        if(!isset(self::$seekEmails[$email])) {
            self::$seekEmails[$email] = \AppEnv::$db->select("$bitrixDB.b_user", [
                  'where'=> ['EMAIL'=>$email, 'ACTIVE'=>'Y', 'LID'=>'s1'],
                  'fields'=>'ID,LOGIN,LAST_NAME,NAME,SECOND_NAME,EMAIL,DATE_REGISTER',
                  'orderby'=>'ID DESC',
            ]);
            if($err = AppEnv::$db->sql_error()) self::$sqlError = $err;
        }
        # else writeDebugInfo("use cached data for $email");

        return self::$seekEmails[$email];
    }
    /**
    *  в Полисах бывают адреса email типа nsandrey@gmail.com'; 'voran@mail.ru' - полный пиндец.
    * вырезаю первый из указанных, удаляю кавычки
    */
    public static function parseBtxEmail($strEmail) {
        if(empty($strEmail)) return '';
        $noQuotes = strtr((string)$strEmail, ["'"=>'', '"'=>'']);
        $split = preg_split("/[ ,;]/",$noQuotes,-1, PREG_SPLIT_NO_EMPTY);
        return $split[0];
    }

    /**
    * {upd/2025-09-03} Аннулирует проекты полисов, оформленных через API (онлайн продажи)
    * и остающихся в "проекте" более NN дней с момента создания/обновления статусов
    */
    public static function handleAbandonedPolicies() {
        $thrDate = date('Y-m-d', strtotime('-'.self::$keepAbandonedPlc . ' days'));
        $emul = 0; # для тестирования - включить эмуляцию удалений
        $arPlc = AppEnv::$db->select(PM::T_POLICIES,[
          'fields'=>'module,stmt_id,stateid,created,policyno,deptid,headdeptid,userid,programid,subtypeid,insurer_fullname',
          'where'=>"stateid=0 AND GREATEST(updated,created,statedate,bpstate_date)<'$thrDate' AND userid IN (SELECT userid FROM alf_apiclients)",'orderby'=>'stmt_id']);

        $ret = '';
        if(is_array($arPlc) && count($arPlc)) foreach($arPlc as $plc) {
            $logPref = strtoupper($plc['module']);
            if($emul) $updt = 1;
            else $updt = PlcUtils::updatePolicy($plc['module'],$plc['stmt_id'],['stateid'=>PM::STATE_ANNUL]);
            if($updt) {
                $crtDate = to_char($plc['created']);
                if(!$emul) {
                    AppEnv::logEvent("$logPref.ANNULATE","Аннуляция неактивного онлайн-полиса",0,$plc['stmt_id'],0,$plc['module']);
                    # 2) почистить карточку от файлов:
                    \FileUtils::deleteFilesInAgreement($plc['module'],$plc['stmt_id'],'*');
                }
                $ret .= "Аннулирован онлайн-полис $plc[policyno] от $crtDate ($plc[module]:$plc[stmt_id])<br>";

            }
        }
        if($ret) $ret = "<h3>Аннуляция брошенных онлайн-полисов</h3>$ret";
        return $ret;
        # return $arPlc;
    }

    /**
    * {upd/2025-09-08} Проверка всех алиасов на ЭЦП сервере, коорые зарегистрированы в ALFO
    * на дату истечения срока действия
    */
    public static function checkDSAliases() {
        $alertDays = self::$digisignAlertDays;
        $alertDate = date('Y-m-d', strtotime("+$alertDays days"));

        $aliases = [];
        # Ищу непустые настройки алиаса при подписантах/штампах
        $stampAliases = AppEnv::$db->select(stamps::TABLE_STAMPS, ['where'=>"signer_digialias<>''",
          'fields'=>'stampid,stampname,signer_name,signer_digialias,signer_email']
        );
        if(is_array($stampAliases)) foreach($stampAliases as $stamp) {
            $aliases[] = [ $stamp['signer_digialias'], "Сертификат подписанта $stamp[stampname] ($stamp[stampid])", $stamp['signer_email'], $stamp['signer_name'] ];
        }

        if($alias = AppEnv::getConfigValue('digisign_aliaskey'))
            $aliases[] = [$alias, 'Основной для полисов страхования жизни'];
        if($alias = AppEnv::getConfigValue('digisign_aliaskey_dms'))
            $aliases[] = [$alias, 'Сертификат для ДМС полисов'];
        if($alias = AppEnv::getConfigValue('plsign_digisign_alias'))
            $aliases[] = [$alias, 'Сертификат для ДМС ПЭП полисов'];

        # return $aliases;
        $url = AppEnv::getConfigValue('digisign_url');
        if(empty($url)) return '';

        $ret = '';
        $arDone = [];
        foreach($aliases as $item) {
            $alias = $item[0];
            if(in_array($alias, $arDone)) {
                # $ret .= "$alias skipped<br>";
                continue; # незачем один и тот же проверять
            }
            $arDone[] = $alias;
            $chkResult = \DigiSign::getAliasInfo($alias);
            $email = $item[2] ?? '';
            $fio = $chkResult['signerName'] ?? $item[3] ?? '';
            if(empty($email)) $email = self::findEmailForAlias($alias, $fio);
            # echo (__FILE__ .':'.__LINE__." chkResult/$alias:<pre>" . print_r($chkResult,1) . '</pre>');
            $itemTitle = $item[1] ?? 'Сертификат';
            $alertText = FALSE;
            $alarm = FALSE;
            if($chkResult['result'] === 'OK') {

                $expDate = $chkResult['minDateExpire'] ?? $chkResult['dateExpire'] ?? '';
                $dmY = date('d.m.Y H:i', strtotime($expDate));

                if(!empty($expDate) && $expDate<$alertDate) {
                    $alertText = "ВНИМАНИЕ! $itemTitle / $item[0] истекает $dmY";
                    $clr = '#e4a';
                    $alarm = 1;
                    appEnv::sendSystemNotification("ALFO-ЭЦП сертификат истекает",
                      "ЭЦП сертификат $alias сгорит $dmY !\nСвоевременно убедитесь в наличии нового и поменяйте настройку ALFO."
                    );
                }
                else {
                    $alertText = "$itemTitle / $alias действует до $dmY";
                    $alarm = 2;
                    $clr = '#000';
                }
                if($alarm && !empty($email)) {
                    # Шлю письмо самому ЭЦП-Подписанту
                    appEnv::sendEmailMessage(
                      ['to'=> $email,
                        'subj'=>"Ваш ЭЦП сертификат пора обновить!",
                        'message'=> "$alertText !\nПожалуйста, зарегистрируйте новый сертификат ЭЦП и передайте его в ИТ для внесения в сервисы ЭЦП"
                      ]
                    );
                }
            }
            else {
                $alertText = "ВНИМАНИЕ! $itemTitle / $alias: ".$chkResult['message'];
                $clr = '#f44';
                appEnv::sendSystemNotification("ALFO-проблема с ЭЦП сертификатом",
                  "проблема с ЭЦП сертификатом $alias: $chkResult[message] !\nСрочно заведите новый или поменяйте настройку ALFO!"
                );
            }
            $ret .= "<div style='color:$clr;'>$alertText / email: $email</div>";
        }
        if($ret) return "<h4>Проверка сертификатов ЭЦП</h4>".$ret;
        return $ret;
    }
    # пытаюсь найти email адрес ЭЦП-подписанта по его алиасу($alias) или ФИО($fullname)
    public static function findEmailForAlias($alias, $fullname='') {
        $signers = AppEnv::$db->select(stamps::TABLE_STAMPS, ['where'=>['signer_digialias'=>$alias]]);
        $ret = '';
        if(is_array($signers)) foreach($signers as $row) {
            if(!empty($row['signer_name']) && empty($fullname))
                $fullname = $row['signer_name'];
            if(!empty($row['signer_email']) && empty($ret)) {
                $ret = $row['signer_email'];
                break;
            }
        }
        if($ret) return $ret;
        if(!empty($fullname)) {
            $user = \CmsUtils::getUserByFullname($fullname);
            if(is_array($user) && isset($user[0]))
                $ret = $user[0]['EMAIL'] ?? $user[0]['usremail'] ?? '';
        }
        return $ret;
    }
} # AlfoJobs
# тестовые запуски через URL разрешаю только админу системы!
if( !empty($_GET['jobsaction']) && SuperAdminMode() ) {
    $action = trim($_GET['jobsaction']);
    if(method_exists('AlfoJobs',$action)) {
        $result = AlfoJobs::$action();
        AppEnv::appendHtml($result);
        AppEnv::finalize();
        exit;
    }
    else exit("wrong command $action");
}