<?php
/**
* @package ALFO
* class.acquiring.php - сервисы для работы с эквайрингом
* @version 0.1.1
* modified 2020-09-21
*/

class Acquiring {
    const VERSION = '0.1';
    static $emulate = 0; # 1|true = эмуляция вызовов API банка
    static $instant_redirect = 1; # false - не делать немедленный редирект на странице клиента(отладка)
    static $debug = 0;
    static $formed_on_pay = true; # при фиксации оплаты переводить в статус "ОФОРМЛЕН"
    static $email_on_pay = true; # при фиксации оплаты формировать письмо клиенту + продавцу
    static $testMode = false; // если перевести в TRUE, будут использованы тестовый сервер и пароли (для тестовых полисов)
    static $clientDebug = 0; # true -> в AJAX ответе агенту показываю ссылку на оплату для клиента.
    static $payDays = 1; # время действия ссылки на оплату, суток (до 23-00 нужного дня!)
    static $dieOnFail = false;
    static $acqObject = NULL;
    const STATE_PAYED  = 1; # успешно оплачен
    const STATE_FAILED = 2; # ошибка при оплате
    const STATE_POLICY_CHANGED = 11; # полис изменили - нельзя платить по этой ссылке

    const TABLE_PAYMENTS = 'alf_eqpayments';
    static $service_url = 'https://web.rbsuat.com/ab/rest/'; # TEST addr
    static $provider = ''; # имя класса провайдера эквайринга
    const DEFAULT_PROVIDER = 'alfabank'; # провайдеро по умолчанию (если не задали)

    // bank_order_id формат: 189cdd0a-0b78-7494-a97f-e93a00005183
    static $userName = 'allianz-api';
    static $password = 'allianz';

    static $clientLnk = '/payment/?cardid=%hash%';
    static $sellerLnk = '/payment/seller.php?cardid=%hash%';

    static $redirectBankUrl = "http://localhost/alfo/payment/pay_page.php?orderid=%orderid%";
    // при эмуляции - переправлю на эту "страницу оплаты банка"

    static $errorMessage = '';

    const REGISTER_CARD = 'register.do'; # первый вызов API - регистрация карточки оплаты
    const GET_ORDER_STATUS = 'getOrderStatus.do';

    public static function getVersion() { return self::VERSION; }

    # получить список "поставщиклв" эквайринга (по файлам "app/acquiring.{vendorname}.php")
    public static function getProviders() {
        $ret = [];
        foreach(glob(__DIR__ . '/acquiring.*.php') as $fname) {
            $bnames = explode('.', basename($fname));
            if(count($bnames)===3)
                $ret[] = $bnames[1];
        }
        return $ret;
    }
    /**
    * Если тестовый полис, можно переключить на использование ТЕСТОВОГО сервиса эквайринга
    *
    * @param mixed $mode
    */
    public static function setTestMode($mode = true) {
        self::$testMode = $mode;
        self::init();
    }

    # {upd/2020-09-15} $route - номер конфигурации, на какой сервис отправлять (1 - АЖ(альфабанк), 2 - Альянс(сбербанк) и т.д.)
    public static function init($route=1) {
        if (self::$testMode)
            $cfgFile = ALFO_ROOT . 'cfg/cfg-acquiring-test.php';
        else
            $cfgFile = ALFO_ROOT . 'cfg/cfg-acquiring.php';

        if (is_file($cfgFile)) $params = include($cfgFile);
        $cfgBranch  = (string)$route;
        if (isset($params[$cfgBranch])) {
            if (isset($params[$cfgBranch]['provider'])) {
                if (!empty($params[$cfgBranch]['provider'])) self::$provider = $params[$cfgBranch]['provider'];
                else self::$provider = self::DEFAULT_PROVIDER;

                if (!empty($params[$cfgBranch]['serviceUrl'])) self::$service_url = $params[$cfgBranch]['serviceUrl'];
                if (!empty($params[$cfgBranch]['login'])) self::$userName = $params[$cfgBranch]['login'];
                if (!empty($params[$cfgBranch]['password'])) self::$password = $params[$cfgBranch]['password'];
            }
            else die ("provider id not set in $cfgBranch / $cfgFile");

            if (isset($params[$cfgBranch]['emulate'])) self::$emulate = $params[$cfgBranch]['emulate'];
        }
        else die("No params in config file for route $route/$cfgFile");
        $clsFile = __DIR__ . '/acquiring.'. strtolower(self::$provider). '.php';
        if (is_file($clsFile)) {
            include_once($clsFile);
            $clsName = "\\Acquiring\\" . self::$provider;
            self::$acqObject = new $clsName($params[$cfgBranch]);
            # self::$acqObject -> setParams($params[$cfgBranch]);

        }
        else die("No provider class: $clsFile");
    }
    /**
    * Если есть, вернет карточку для указанного полиса (защита на 2 часа от повторных отправок ссылки клиенту)
    *
    * @param mixed $module
    * @param mixed $policyid
    */
    public static function findExistingCard($module, $policyid) {
        # $plcdata =
        $data = appEnv::$db->select(self::TABLE_PAYMENTS, [
          'fields' => "id,bank_order_id, is_payment, payment_date,timeto,sellerlink, TIMEDIFF(created, now() ) crtpass",
          'where'=>['module'=>$module, 'policyid' => $policyid, "is_payment=0"], # отбираю только НЕсгоревшие заявки
          'orderby'=>'id DESC'
        ]);
        if (is_array($data) && count($data)) {
            foreach($data as $row) {
                # if ($row['is_payment'] == self::STATE_PAYED) return "По данному полису уже прошла успешная оплата!";
                # if ($row['is_payment'] >=2) return "Заявка на оплату недействительна";
                if ($row['is_payment'] == 0 && $row['timeto']>date('Y-m-d H:i')) {
                    $delta = explode(':', $row['crtpass']); # время от создания прошлой карточки
                    if (intval($delta[0])<2) {
                        $url = $row['sellerlink'];
                        $ret ="Для данного полиса уже отправляли ссылку менее 2х часов назад, и она пока не использована:"
                            . "<a href=\"$url\" target=\"_blank\">Смотреть оплату</a>";
                        return $ret;
                    }
                }
            }
        }
        else return '';
    }
    /**
    * Создаем карточку для оплаты, генерим ее hash
    *
    * @param array $params
    * @return array
    */
    public static function createPayCard($params) {
        $hash = md5(date('Y-m-d H:i:s') . $params['policyno'] . $params['paysum'].$params['clientemail']);

        # номер конфигурации эквайринга

        $cfgno = isset($params['acqtype']) ? $params['acqtype'] : '1';

        $urlBase = appEnv::getConfigValue('comp_url');
        $customerLink = self::$acqObject->getClientLink($hash);
        $sellerLink   = self::$acqObject->getSellerLink($hash);

        $maxdate = strtotime("+".self::$payDays. " days");
        $dta = [
          'module'     => $params['module'],
          'userid'     => appEnv::$auth->userid,
          'policyid'   => $params['policyid'],
          'policyno'   => $params['policyno'],
          'hashsum'    => $hash,
          'clientemail'=> $params['clientemail'],
          'emailseller'=> $params['emailseller'],
          # 'payment_sum'     => $params['paysum'],
          'payment_sum_rub' => $params['paysum'],
          'created'    => date('Y-m-d H:i:s'),
          'timeto'    => date('Y-m-d', $maxdate).' 23:00',

          'customerlink' => $customerLink,
          'sellerlink' => $sellerLink,
        ];

        $result = appEnv::$db->insert(self::TABLE_PAYMENTS, $dta);
        $dta['id'] = appEnv::$db->insert_id();
        # WriteDebugInfo("insert card SQL:", appEnv::$db->getLastQuery());
        # WriteDebugInfo("insert result:", $result);
        return $dta;
    }

    # получаю "дополнительные" детали о полисе по данным из карточки на оплату
    public static function getPolicyDetails(&$ret) {
        if (self::$debug) WriteDebugInfo("getPolicyDetails for ", $ret);
        if (isset($ret['module'])) {
            if ($ret['module'] === 'investprod') {
                $plcDetails = appEnv::$db->select('bn_policy p, bn_individual i',[
                  'fields' => "p.stateid, p.datefrom, p.datetill, concat(i.lastname,' ',i.firstname,' ',i.middlename) clientname",
                  'where' => "p.id=>$ret[policyid] AND i.id=p.insurerid",
                  'singlerow' => 1
                ]);
            }
            else {
                $plcDetails = appEnv::$db->select('alf_agreements', [
                  'fields' => "stateid, datefrom, datetill,insurer_fullname clientname,programid,prodcode",
                  'where' => ['stmt_id'=>$ret['policyid']],
                  'singlerow' => 1
                ]);
            }
            if (!isset($plcDetails['datefrom'])) {
                WriteDebugInfo("Ошибка запроса ".appEnv::$db->sql_error());
                WriteDebugInfo("запрос ".appEnv::$db->getLastQuery());
                die ("Ошибка получения данных о полисе");
            }
            $ret['stateid'] = $plcDetails['stateid'];
            $ret['datefrom'] = to_char($plcDetails['datefrom']);
            $ret['datetill'] = to_char($plcDetails['datetill']);
            $ret['clientname'] = $plcDetails['clientname'];
            $ret['product'] = $ret['module'];
            $plg = $ret['module'];
            switch($plg) {

                case 'investprod' : $ret['product'] = 'Инвестиционного страхования'; break;
                case 'planb' : $ret['product'] = 'План Б'; break;
                case 'garcl' : $ret['product'] = 'Allianz Гарантия классик'; break;
                default:
                    if (method_exists(appEnv::$_plugins[$plg],'getProgramName'))
                        $ret['product'] = appEnv::$_plugins[$plg]->getProgramName($plcDetails['prodcode']);
                    else $ret['product'] = $plg;
                    break;
            }
        }
    }
    /**
    * Отправляет сообщение с URL страницы оплаты,
    * на EMAIL клиента и(если есть) на телефон
    * @param mixed $params массив с данныи полиса либо ИД созданной "карточки" оплаты
    * @param string $sendPolicy посылать ли клиенту PDF с полисом
    * @param string $serviceNo 1 для оплаты в АЖ, 1 - в Альянс (полисы НЕ-жизни: имущество и т.д.) @since 1.3
    * @return array ['email' - результат_отправки_email, 'sms' => результат_СМС]
    */
    public static function sendPaymentLink($params, $phone = '', $sendPolicy = false, $serviceNo='1') {

        self::init($serviceNo);
        $emailResult = $smsResult = 0;
        $ret = ['sms'=>0, 'email'=>0];
        if (is_array($params)) {
            $data = $params;
        }
        elseif(is_atring($params)) {
            $data = self::getCard($params);
        }
        if (!isset($data['datetill'])) self::getPolicyDetails($data);

        if (!is_array($data)) die('Неверный вызов');
        $clientemail = $data['clientemail'];
        $selleremail = $data['emailseller'];
        $customerlink = $data['customerlink'];
        $sellerlink = $data['sellerlink'];

        $resSell = $resCli = false;
        $clientName = isset($data['clientname']) ? $data['clientname'] : 'Клиент';
        $productName = isset($data['product']) ? $data['product'] : $data['module'];
        $feedbackEmail = appEnv::getConfigValue($data['module'] . '_feedback_email');
        if (!$feedbackEmail) $feedbackEmail = 'info@allianz.ru';
        # WriteDebugInfo("feedback from cfg:", $feedbackEmail);
        $subst = [
          '%clientname%' => $clientName,
          '%productname%' => $productName,
          '%policyno%' => $data['policyno'],
          '%max_paydate%' => to_char($data['timeto']),
          '%paysum%' => fmtMoney($data['payment_sum_rub']),
          '%feedbackemail%' => $feedbackEmail,
          '%pay_url%' => $customerlink,
          '%seller_url%' => $sellerlink,
        ];

        if (!empty($data['clientemail'])) {
            $templateFile = ($sendPolicy) ? 'toclient-with-policy.htm' : 'toclient.htm';
            $msgbody = @file_get_contents(ALFO_ROOT . 'templates/eqpayments/'.$templateFile);
            $msgbody = strtr($msgbody, $subst);
            $files = [];
            if ($sendPolicy) {
                $files[] = PlcUtils::createPdfPolicy($data['module'], $data['policyid'], 'draft');
            }
            $resCli = appEnv::sendEmailMessage(array(
                'to' => $params['clientemail']
                ,'subj' => "Ссылка для оплаты Вашего полиса"
                ,'message' => $msgbody
              ),
              $files
            );
        }

        if (!empty($data['emailseller'])) {
            $msgSell = file_get_contents(ALFO_ROOT . 'templates/eqpayments/toseller.htm');
            $msgSell = strtr($msgSell, $subst);
            $resSell = appEnv::sendEmailMessage(array(
                'to' => $params['emailseller']
                ,'subj' => "Ссылка для проверки оплаты договора $data[policyno]"
                ,'message' => $msgSell
            ));
            $cliRef = (self::$clientDebug) ? "<a href='$customerlink' target='_blank'>Ссылка для оплаты</a>" : 'Ссылка для оплаты';
            if ($resCli) $ret['email'] = "$cliRef отправлена клиенту на Email.";
            if ($resSell) $ret['email'] .= "<br><a href='$sellerlink' target='_blank'>Ссылка для проверки статуса</a> отправлена Вам на email";
        }

        if (!empty($phone)) {
            include_once(__DIR__ . '/sms_sender.php');
            $smsText = "ссылка для оплаты $data[policyno] ".$customerlink;
            $ret['sms'] = SmsSender::send($phone, $smsText) ? 'Ссылка отправлена клиенту на СМС' : '';
        }
        if (self::$debug) WriteDebugInfo("sendPaymentLink ret:", $ret);
        return $ret;
    }

    /**
    * получаю по хешу карточку на оплату
    *
    * @param mixed $data - array полная запись о платеже в alf_eqpayments
    */
    public static function getCard($hash) {
        if (is_array($hash)) $where = $hash;
        else $where = "hashsum='$hash' OR order_number='$hash' OR bank_order_id='$hash'";
        $ret = appEnv::$db->select(self::TABLE_PAYMENTS, ['where'=>$where,'singlerow'=>1]);
        if (self::$debug > 1) {
            WriteDebugInfo("getCard select:", appEnv::$db->getLastQuery());
            WriteDebugInfo("found card data: ", $ret);
        }
        self::getPolicyDetails($ret);
        return $ret;
    }

    /**
    * Регистрирую заявку на оплату в банке и делаю режирект на страницу оплаты (эквайринг) в банке
    * @param mixed $data - array полная запись о платеже в alf_eqpayments
    */
    public static function registerPaymentInBank(&$data) {

        self::init();
        if (!empty($data['bankurl'])) {
            WriteDebugInfo("registerPaymentInBank: повторно редиректим клиента на уже выданную ссылку");
            # от банка уже приходил URL, мы его зафиксировали и послали клиента, но он мог закрыть окно и зайти заново

            if (self::$instant_redirect)
                header("Location: $data[bankurl]");
            else {
                echo "(Повторное открытие ссылки)<br>Redirect отключен.<br>Нажмите сами на ссылку для оплаты в банке:<br><a href=\"$data[bankurl]\">$data[bankurl]</a>.";
            }
        }

        # формирую новый orderNumber и регистрирую запрос на оплату в банке
        $OrderNumber = $data['id']."/".time();
        appEnv::$db->update(EqPayments::TABLE_PAYMENTS, [
          'order_number' => $OrderNumber],
          "id=".$data['id']
        );
        $data['order_number'] = $OrderNumber;
        if (self::$debug) WriteDebugInfo("$data[id] - обновил OrderNumber : $OrderNumber");
        $amount = round(floatval($data['payment_sum_rub']) * 100);
        $description = substr($data['policyno'].":".$data['clientemail']."/".$data['payment_sum_rub'], 0, 50);
        #банк ошибочно ограничил 50ю знаками
        $currentTime = Time();
        $customerlink = $data['customerlink'];
        $orderBundle = array(
                "orderCreationDate" => $currentTime,
                "customerDetails" => array(
                    "email" => $data['clientemail'],
                ),
                "cartItems" => array(
                    "items" => array(
                    array(
                        "positionId" => "1",
                        "name" => $description,
                        "quantity" => array(
                            "value" => 1,
                            "measure" => "pcs",
                        ),
                        "itemAmount" => $amount,
                        "itemCode" => $description,
                        "tax" => array(
                            "taxType" => 0,
                        ),
                        "itemPrice" => $amount,
                    ),
                    ),
                ),
        );
        $orderBundle = json_encode($orderBundle);
        $post_fields = array(
            "userName" => self::$userName,
            "password" => self::$password,
            "orderNumber" => $OrderNumber, #это номер заказа для банка, в ответ получим ордер ид
            "amount" => $amount,
            "returnUrl" => $customerlink,
            "description" => $description,
            "orderBundle" => $orderBundle,
            "taxSystem" => 0,
        );
        if (self::$debug > 1) WriteDebugInfo(self::REGISTER_CARD , ' call data:', $post_fields);

        #с фискализацией (актуально)
        if (self::$emulate) {
            $ordTmp = md5($OrderNumber); // игрушечный ИД ордера от банка
            $orderid = strtolower(substr($ordTmp,0,8).'-'.substr($ordTmp,9,4).'-'.substr($ordTmp,13,4).'-'.substr($ordTmp,-12));

            $backUrl = str_replace('%orderid%',$orderid, self::$redirectBankUrl);
            # WriteDebugInfo("debug send, $orderid, url = $backUrl");
            // sleep(1);
        }
        else {
            $response = CurlA::getFromUrl(self::$service_url . self::REGISTER_CARD,$post_fields);
            if(self::$debug) {
                writeDebugInfo("create card response:", $response);
                writeDebugInfo("create card errno:", CurlA::getErrno());
                writeDebugInfo("create card error msg::", CurlA::getErrMessage());
            }
            /*
            $ch = curl_init(self::$service_url . self::REGISTER_CARD); // "register.do"
            # TODO: использовать прокси, если задан в настройках!
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8); // N секунд жду коннекта
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields, '', '&'));
            $response = @curl_exec($ch);
            $error = self::isFailedCall($ch, $response);
            */
            if (self::$debug) WriteDebugInfo(self::REGISTER_CARD, ": call response:", $response);
            $error = self::isFailedCall(CurlA::$curlobj, $response);

            $response_fields = json_decode($response);

            $orderid = isset($response_fields->orderId) ? $response_fields->orderId : '';
            $backUrl = isset($response_fields->formUrl) ? $response_fields->formUrl : '';
        }
        if ($orderid && $backUrl) {
            #записали ид заказа от банка URL для перенаправления в банк на оплату
            appEnv::$db->update(
                self::TABLE_PAYMENTS,
                ['bank_order_id'=>$orderid, 'bankurl'=>$backUrl],
                "id=".$data['id']
            );
            $data['bank_order_id'] = $orderid; # вернет массив обратно через ссылку
            # ($card_id, "bank_order_id", $response_fields->orderId);
            #идем на страницу оплаты в банк
            if (self::$instant_redirect)
                header("Location: $backUrl");
            else {
                echo "Redirect отключен.<br>Нажмите сами на ссылку для оплаты в банке:<br><a href=\"$backUrl\">$backUrl</a>.";
            }
            #банк нас перекинет обратно через returnUrl, а в гет передаст orderId
            exit();
        }
        else {
            $err = 'Ошибка регистрации запроса на оплату:<pre>' . $response_fields->errorMessage .'</pre>';
            echo "<div class='warn'>$err</div>";
            return 0;
        }

        return $ret;
    }

    /**
    * получем от банка статус оплаты по нашей карточке
    *
    * @param mixed $data
    */
    public static function getOrderStatus(&$data) {

        self::init();

        # if (self::$emulate) return 0;

        $success = 0;
        $response = '';
        $post_fields = array(
            "userName" => self::$userName,
            "password" => self::$password,
            "orderId"  => $data['bank_order_id'],
        );
        if (self::$emulate) { // эмуляция вызовов
            $response_fields = new stdClass();
            $response_fields->OrderStatus = 2;
            $response_fields->Amount = $data['payment_sum_rub']*100;
        }
        else {
            $response = CurlA::getFromUrl(self::$service_url . self::GET_ORDER_STATUS,$post_fields);
            /*
            $ch = curl_init(self::$service_url . self::GET_ORDER_STATUS);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8); // N секунд жду коннекта
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields, '', '&'));
            $response = curl_exec($ch);
            */
            if (self::$debug) WriteDebugInfo(self::GET_ORDER_STATUS, ' call params:', $post_fields);
            $error = self::isFailedCall(CurlA::$curlobj, $response);

            $response_fields = json_decode($response);
            if (self::$debug) WriteDebugInfo(self::REGISTER_CARD, " call response:", $response);
            if ($error || !empty($response_fields->ErrorMessage)) {
                if (!empty($response_fields->ErrorMessage)) self::$errorMessage = $response_fields->ErrorMessage;
            }
        }

        if (isset($response_fields->OrderStatus)) {
            if ($response_fields->OrderStatus == 2) {
                #если банк ответил, что оплата прошла успешно, то отмечаем в карточке
                $success = 1;
                $upd = ['is_payment' => self::STATE_PAYED,
                   'payment_date' => date('Y-m-d H:i:s'),
                   'payment_sum' => round($response_fields->Amount / 100, 2),
                ];
                appEnv::$db->update(self::TABLE_PAYMENTS, $upd, ['id'=>$data['id']]);
                if (self::$debug) WriteDebugInfo("update eq_payments SQL:", appEnv::$db->getLastQuery());

                $data = array_merge($data, $upd);
                self::fixPaymentInFO($data);
                /*
                CIBlockElement::SetPropertyValues($card_id, $IBLOCK_ID, '1', "is_payment");
                CIBlockElement::SetPropertyValueCode($card_id, "payment_sum", $response_fields->Amount / 100);
                CIBlockElement::SetPropertyValueCode($card_id, "payment_date", date("d.m.Y H:i:s"));

                #отправляем уведомление по почте, что оплата прошла
                    $res = CIBlockElement::GetByID($card_id);
                    #if($obRes = $res->GetNextElement())
                    #{
                    #    $ar_res = $obRes->GetProperties();
                    #}
                    $toSend = Array();
                    $toSend["in_policy"] = $policy_number;
                    $toSend["in_emailcustomer"] = $emailcustomer;
                    $toSend["in_emailseller"] = $emailseller;
                    $toSend["in_premiumrur"] = $policy_sum_rub;
                    CEvent::SendImmediate ("payment_safe_retail_successcustomer", SITE_ID, $toSend); #письмо клиенту
                    CEvent::SendImmediate ("payment_safe_retail_success", SITE_ID, $toSend); #письмо продавцу
                #отправляем уведомление по почте, что оплата прошла
                */
            }
            elseif($response_fields->OrderStatus == 0) {
                # оплату еще не делали
                $success = 0;
            }
            else { # не 2 - Ошибка при оплате в банке
                $success = self::STATE_FAILED;
                if (empty(self::$errorMessage)) self::$errorMessage = $response_fields->errorMessage;
                $data['is_payment'] = self::STATE_FAILED;
                $data['errormessage'] = self::$errorMessage;
                $upd = [ 'is_payment' => self::STATE_FAILED, 'errormessage' => self::$errorMessage];
                if (self::$debug) {
                    WriteDebugInfo("карточка оплаты (новое сост):", $data);
                    WriteDebugInfo("Ответ от банка (статус не ОК): ", $response);
                }
                appEnv::$db->update(self::TABLE_PAYMENTS, $upd, ['id'=>$data['id']]);
            }
        }
        return $success;
    }
    /**
    * фиксирую оплаты в основной таблице полисов ФО
    *
    * @param mixed $data
    */
    public static function fixPaymentInFO($data) {
        if (self::$debug )WriteDebugInfo("fixPaymentInFO start, passed data: ", $data);
        $module = $data['module'];
        $bkend = appEnv::getPluginBackend($module);
        $id = $data['policyid'];
        $dtpay = (!empty($data['payment_date']) && intval($data['payment_date'])) ? $data['payment_date'] : date('Y-m-d');
        if($module === 'investprod') {
            $curplc = appenv::$db->select('bn_policy',['where'=>['id'=>$data['policyid']],'singlerow'=>1]);
            if (intval($curplc['datepay'])) return; # уже проставили, больше не надо
            $upd = ['datepay'=>$dtpay, 'updated'=>'{now}'];
            if (self::$formed_on_pay) $upd['stateid'] = 11;
            appEnv::$db->update('bn_policy', $upd, ['id'=>$id]);
            appEnv::logEvent("BN.PAY ONLINE", "Произведена онлайн-оплата полиса $data[policyno]", false, $id);
            if ($upd['stateid'] == 11) {
                appEnv::logEvent("BN.STATE FORMED", "Полис $data[policyno] переведен в статус Оформлен", false, $id);
            }
        }
        else {
            $curplc = appenv::$db->select('alf_agreements',['where'=>['stmt_id'=>$data['policyid']],'singlerow'=>1]);
            if ( intval($curplc['datepay']) || floatval($curplc['eqpayed'])>0 ) {
                if (self::$debug) WriteDebugInfo("оплату не фиксирую: ", $curplc);
                return; # уже проставили, больше не надо
            }

            $upd = [ 'datepay' => $dtpay, 'platno' => $data['bank_order_id'], 'eqpayed'=>$data['payment_sum_rub'] ];
            if ($curplc['stateid']<9 && $curplc['stateid']!=2) # не на андеррайтинге
                $upd['stateid'] = (self::$formed_on_pay ? 11 : 7);

            # platno - что занести в номер платежки ? (пока заношу orderId от банка)
            $result = appEnv::$db->update('alf_agreements',
                $upd,
                ['stmt_id'=>$data['policyid'], 'module'=>$data['module']]
            );
            if(self::$debug) {
                WriteDebugInfo("[$result]: alf_agreements update ERR = ", appEnv::$db->sqL_error());
                WriteDebugInfo("alf_agreements-pay fixation SQL: ", appEnv::$db->getLastquery());
            }
            if (is_object($bkend)) {
                $logpref = $bkend->getLogPref();
            }
            else $logpref = strtoupper($module) . '.';

            appEnv::logEvent($logpref."PAY ONLINE", "Произведена онлайн-оплата полиса $data[policyno]", false, $id);
            if ($upd['stateid'] == 11)
                appEnv::logEvent($logpref."STATE FORMED", "Полис $data[policyno] переведен в статус Оформлен", false, $id);

        }
        # меняю статус "заявки на оплату"
        # appEnv::$db->update('alf_eqpayments', ['is_payment'=>100], ['id'=>$data['id']]);

        # отправляю письма клиенту и сотруднику об успешной оплате
        if (self::$email_on_pay) {
            self::getPolicyDetails($data);
            if (self::$debug )WriteDebugInfo("отправляю письма об оплате");

            $resSell = $resCli = false;
            $clientName = isset($data['clientname']) ? $data['clientname'] : 'Клиент';
            $productName = isset($data['product']) ? $data['product'] : $data['module'];

            $siteUrl = appEnv::getConfigValue('comp_url');
            if (substr($siteUrl, -1) !== '/') $siteUrl .= '/';

            $viewlink = # ($data['module'] === 'investprod') ? "{$siteUrl}?plg=investprod&action=viewagr&id=$dataid]" :
                "{$siteUrl}?plg=$data[module]&action=viewagr&id=$data[policyid]";

            $clientTpl =  PlcUtils::getHtmlTemplate('eqpayments/toclient-payed-email', $module);
            # writeDebugInfo("to-client template $module: ", $clientTpl);
            # $clientTpl =  @file_get_contents(ALFO_ROOT . 'templates/eqpayments/toclient-payed.htm');

            if ($clientTpl) {
                $msgPS = '';

                $files = [];
                # прицепить PDF с полисом?
                $sendPolicy = FALSE;
                if(method_exists($bkend, 'payedPolicySendPdf'))
                    $sendPolicy = $bkend->payedPolicySendPdf($data['policyid']);

                if ($sendPolicy) { # да, цепляем!
                    appEnv::setIntCallMode(1);
                    $flname = PlcUtils::createPdfPolicy($data['module'], $data['policyid']);
                    if (is_string($flname) && is_file($flname)) {
                        $files[] = $flname;
                        $msgPS = appEnv::getLocalized('ps_pdf_sent_to_email');
                    }
                }
                # writeDebugInfo("sendPolicy=[$sendPolicy] final files : ", $files);
                $subst = [
                  '%clientname%' => $clientName,
                  '%productname%' => $productName,
                  '%datefrom%' => $data['datefrom'],
                  '%datetill%' => $data['datetill'],
                  '%policyno%' => $data['policyno'],
                  '%company_phone%' => appEnv::getConfigValue($module.'_feedback_phones'),
                  '%company_email%' => appEnv::getConfigValue($module.'_feedback_email'),
                  '%paysum%' => fmtMoney($data['payment_sum_rub']),
                  '%seller_url%' => $viewlink, # $data['sellerlink'], # URL на открытие полиса сотрудником
                  '%msg-postfix%' => $msgPS,
                ];
                $msgbody = strtr($clientTpl, $subst);

                $resCli = appEnv::sendEmailMessage(array(
                    'to' => $data['clientemail']
                    ,'subj' => "Полис $data[policyno] успешно оплачен"
                    ,'message' => $msgbody
                  ),
                  $files
                );
            }
            appEnv::setIntCallMode(FALSE);

            $sellerTpl =  PlcUtils::getHtmlTemplate('eqpayments/toseller-payed');
            # $sellerTpl =  @file_get_contents(ALFO_ROOT . 'templates/eqpayments/toseller-payed.htm');
            if ($sellerTpl && !empty($data['emailseller'])) {
                $msgbody = strtr($sellerTpl, $subst);
                $resCli = appEnv::sendEmailMessage(array(
                    'to' => $data['emailseller']
                    ,'subj' => "Полис $data[policyno] оплачен клиентом"
                    ,'message' => $msgbody
                  )
                );
            }

        }

    }
    /**
    * Если при вызове сервиса приключилась ошибка - сообщаю и прерываю работу
    *
    * @param mixed $ch
    * @param mixed $response
    */
    public static function isFailedCall($ch, $response) {
        # $err = curl_errno($ch);
        # $errmsg = curl_error($ch);
        $err = CurLA::getErrno();
        $errmsg = CurlA::getErrMessage();
        $stopped = (stripos($response, 'Out of service')!==FALSE);
        if (empty($response) || $err > 0 || $stopped) {
            self::$errorMessage = 'Ошибка при вызове сервиса Банка. ';
            if ($err) self::$errorMessage .= " Код:$err, текст: $errmsg";
            if ($stopped) self::$errorMessage .= 'Сервис оплаты временно приостановлен.<br>Попробуйте через некоторое время обновить страницу';
            if(self::$debug) WriteDebugInfo('curl error:', self::$errorMessage);
            if(self::$dieOnFail) die (self::$errorMessage);
            return true;
        }
        else {
            $resp = json_decode($response, true); // в ассоц.массив!
            $err = (!empty($resp['ErrorCode']) ? $resp['ErrorCode'] : 0);
            $errMsg = (!empty($resp['ErrorMessage']) ? $resp['ErrorMessage'] : '');
            if ($err > 0) {
                self::$errorMessage = "Ошибка при вызове сервиса Банка, код: $err, текст: $errMsg";
                if(self::$debug) WriteDebugInfo('curl error:', self::$errorMessage);
                if(self::$dieOnFail) die (self::$errorMessage);
                return true;
            }
        }
        return false; // no error
    }

    public static function blockAllCards($module, $policyid) {

        $upd = appEnv::$db->update(self::TABLE_PAYMENTS,
          ['is_payment'=> self::STATE_POLICY_CHANGED],
          ['module'=>$module, 'policyid'=>$policyid, 'is_payment'=>0]
        );
        $ret = appEnv::$db->affected_rows();
        # WriteDebugInfo( "blockAllCards query:" , appEnv::$db->getLastquery() );
        # WriteDebugInfo( "affected records:" , $ret );
        return $ret;
    }
    /**
    * Вернет данные из последней "действующей" или оплаченной ссылки для данного полиса
    *
    * @param mixed $module
    * @param mixed $policyid
    */
    public static function getLastLink($module, $policyid) {
        $ret = appEnv::$db->select(self::TABLE_PAYMENTS,
          ['where' => [ 'module'=>$module,'policyid'=>$policyid ],
          'orderby' => 'id DESC',
          'singlerow' => 1
        ]);
        return $ret;
    }
    /**
    * тестовые вызовы сервиса
    *
    * @param mixed $params
    */
    public static function test($provider='2', $params=FALSE) {
        $paypar = [
          'fullname' => (isset($params['fullname']) ? $params['fullname'] : 'Зеленый Крокодил Чебурашкович'),
          'email' => (isset($params['email']) ? $params['email'] : 'as-works@narod.ru'),
          'policyno' => (isset($params['policyno']) ? $params['policyno'] : 'EKT-000'.rand(1000000,5000000)),
          'url_success' => "https://www.selifan.ru/my/id=900&result=OK",
          'url_fail' => "https://www.selifan.ru/my/id=900&result=FAIL",
          'premium' => 12500,
        ];
        self::init($provider);
        if (is_object(self::$acqObject)) {
            $result = self::$acqObject->registerOrder($paypar);
            return $result;
        };
        return FALSE;
    }
}
