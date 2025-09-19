<?php
/**
* \Acquiring\Sberbank class - оплата через эквайринг сбер-банка
* @version 1.15.001
* modified 2024-12-19
**/
namespace Acquiring;

class Sberbank {

    private static $login = "mylogin";
    private static $password = "nopass";
    private static $serviceUrl = "https://3dsec.sberbank.ru/payment/rest/"; # тестовый URL
    protected $logging = 1; # логировать все вызовы в API эквайринга в applogs/
    protected $logHandle = 0;
    const BANK_NO_ACTIVITY = 0; # код из банка, когда ордер еще не пытались оплатить
    const BANK_PAY_OK = 2;  # платеж выполнен успешно
    const BANK_PAY_FAIL = 6; # платеж не получился ИЛИ просрочен(сгорел)
    const BANK_ORDER_EXPIRED = 9999; # 2022-08-24: В Сбере нет статуса "ордер Сгорел" - присылает ту же 6-ку!

    const REGISTER_CARD = 'register.do'; # первый вызов API - регистрация карточки оплаты
    const GET_ORDER_STATUS = 'getOrderStatus.do';
    const GET_ORDER_STATUS_EXT = 'getOrderStatusExtended.do';
    const GET_SBP_CODE= 'sbp/c2b/qr/dynamic/get.do';
    const CANCEL_ORDER = 'decline.do'; # отмена неоплаченного заказа: userName,password, orderId (char 36) OR orderNumber(char 32)

    static $debug = 0;
    const LOGFILE = 'applogs/acquiring.sber.{date}.log';
    private static $logDay = '';
    public static $rawResult = []; # полный массив данных из ответа, полученного от API
    protected $makeBundle = FALSE; # делать ли блок с корзиной (не тестовой среде Bundle вызывает внутреннюю ошибку (по сост. на 2020-12-28)
    public function __construct($params = FALSE) {
        if (is_array($params)) $this->setParams($params);
        # $this->makeBundle = \appEnv::isProdEnv(); # Bundle работает только на боевой среде (сост.на 03.02.2021)!
        $this->makeBundle = TRUE; # Bundle работает только на боевой среде (сост.на 03.02.2021)!
        # TODO: когда в Сбере починят тестовую среду, должно быть всегда TRUE!
        # if (\appEnv::isProdEnv()) self::$debug = 0;
        if($zDeb = \AppEnv::getConfigValue('z_debug_acquiring'))
            self::$debug = $zDeb; # вкл.отладки через настройку

        self::$logDay =  ALFO_ROOT. str_replace('{date}', date('Ymd'), self::LOGFILE);
        if(self::$debug) writeDebugInfo("Sberbank object created, log File: ", self::$logDay);
    }

    public function setParams($pars) {
        if (!empty($pars['login'])) self::$login = $pars['login'];
        if (!empty($pars['password'])) self::$password = $pars['password'];
        if (!empty($pars['serviceUrl'])) self::$serviceUrl = $pars['serviceUrl'];
    }

    /** получаю результат оплаты из банка
    * если $returnFmt = 'array' - вернет массив ['result'=>XX,'message'=>XX]
    * иначе при $returnFmt=TRUE: 'OK'|'FAIL'|0, FALSE: текст об оплате/неоплате
    */
    public function getOrderStatus( $orderId, $returnFmt = 'array' ) {
        // https:/server/application_context/rest/getOrderStatus.do? orderId=b8d70aa7-bfb3-4f94-b7bb-aec7273e1fce&language=ru&password=password&userName=userName
        $debAcq = \AppEnv::getConfigValue('z_debug_acquiring', FALSE);
        $pars = [
        'orderId' => $orderId,
        'language' => 'ru',
        'userName' => self::$login,
        'password' => self::$password,
        ];
        $url = self::$serviceUrl . self::GET_ORDER_STATUS; # . http_build_query($pars);"getOrderStatus.do"
        $response = \Curla::getFromUrl($url,$pars,10);
        if ($this->logging) {

            if($debAcq || 1) {
                $callFrom = "call _URI:" .$_SERVER['REQUEST_URI'] . ", params:".print_r($_GET,1)
                  . "\n call trace: ". print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6),1);
                @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . $callFrom. "\n", FILE_APPEND );
            }

            @file_put_contents(self::$logDay, "\n".date('Y-m-d H:i:s') . ' getOrderstatus url: '.$url. "\n", FILE_APPEND );
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' getOrderstatus fields: '.print_r($pars,1)."\n", FILE_APPEND );
            $rawResponse = \Curla::getRawResponse();
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' getOrderstatus raw response: '.$rawResponse. "\n", FILE_APPEND );
        }

        if( $response ){
            # Строка ответа:
            $result = self::$rawResult = @json_decode($response, true);
            $errMsg = $result['ErrorMessage'] ?? 'Неизвестная ошибка';
            $errCode = $result['ErrorCode'] ?? 0;

            if( isset($result['OrderStatus']) ){
                if ($returnFmt === 'array') {
                    if($result['OrderStatus'] == self::BANK_PAY_OK)
                        $result['result'] = 'OK';
                    elseif($result['OrderStatus'] == self::BANK_PAY_FAIL) {
                        $result['result'] = 'FAIL';
                        if(isset($result['ErrorMessage']))
                            $result['message'] = $result['ErrorMessage'];
                    }
                    elseif($result['OrderStatus'] == self::BANK_ORDER_EXPIRED) {
                        $result['result'] = 'EXPIRED';
                        if(isset($result['ErrorMessage']))
                            $result['message'] = $result['ErrorMessage'];
                    }
                    elseif($result['OrderStatus'] == self::BANK_NO_ACTIVITY) {
                        $result['result'] =  0;
                    }
                    # writeDebugInfo("to return: ", $result);
                    return $result;
                }

                if( $result['OrderStatus'] == self::BANK_PAY_OK ){
                    if ($returnFmt) $ret = 'OK';
                    else $ret = "Оплата произведена";
                }
                elseif( $result['OrderStatus'] == self::BANK_PAY_FAIL ){
                    if ($returnFmt) $ret = 'FAIL';
                    else $ret = "Оплата не прошла: ".$result['ErrorMessage'];
                }
                else {
                    if ($returnFmt) $ret = 0;
                    else $ret = "Оплата еще не выполнена";
                }
                # writeDebugInfo("getOrderStatus: return : ", $ret);
                return $ret;
            }
            elseif(stripos($response, 'Out of service')) {
                $this->_errorMessage = 'Сервис временно остановлен';
                return (($returnFmt ==='array')? ['result'=>0, 'message'=>$this->_errorMessage] : 0);
            }
            else {
                $this->_errorMessage = $errMsg;
                return (($returnFmt ==='array')? ['result'=>0, 'message'=>$errMsg, 'errorCode'=>$errCode] : 0);
            }

        }
        return (($returnFmt ==='array')? ['result'=>0, 'message'=>'Ошибка обращения к сервису'] : 0);
    }

    /** получаю результат оплаты из банка
    * если $returnFmt = 'array' - вернет массив ['result'=>XX,'message'=>XX]
    * иначе при $returnFmt=TRUE: 'OK'|'FAIL'|0, FALSE: текст об оплате/неоплате
    */
    public function getOrderStatusExt( $orderId, $returnFmt = 'array' ) {
        // https:/server/application_context/rest/getOrderStatus.do? orderId=b8d70aa7-bfb3-4f94-b7bb-aec7273e1fce&language=ru&password=password&userName=userName
        $debAcq = \AppEnv::getConfigValue('z_debug_acquiring', FALSE);
        $pars = [
        'orderId' => $orderId,
        'language' => 'ru',
        'userName' => self::$login,
        'password' => self::$password,
        ];
        $url = self::$serviceUrl . self::GET_ORDER_STATUS_EXT;
        $response = \Curla::getFromUrl($url,$pars,10);
        if ($this->logging) {
            @file_put_contents(self::$logDay, "\n".date('Y-m-d H:i:s') . ' getOrderStatusExtended.do url: '.$url. "\n", FILE_APPEND );
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' getOrderStatusExtended.do fields: '.print_r($pars,1)."\n", FILE_APPEND );
            $rawResponse = \Curla::getRawResponse();
            if($debAcq) {
                $callFrom = "call _URI:" .$_SERVER['REQUEST_URI'] . ", params:".print_r($_GET,1). "\n call trace: "
                  . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6),1);
                @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . $callFrom. "\n", FILE_APPEND );
            }
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' getOrderStatusExtended.do raw response: '.$rawResponse. "\n", FILE_APPEND );
        }

        if( $response ){
            # Строка ответа:
            $result = self::$rawResult = @json_decode($response, true);
            # writeDebugInfo("decoded result: ", $result);

            if(!empty($result['actionCodeDescription'])) {
                # writeDebugInfo("actionCodeDescription: ", $result['actionCodeDescription']);
                # $result['actionCode'] = 0 единственный код "успеха оплаты", все не-нули - причины отказа
                if(mb_stripos($result['actionCodeDescription'], 'Операция отклонена', 'UTF-8')!==FALSE
                  || mb_stripos($result['actionCodeDescription'], 'Время сессии истекло', 'UTF-8')!==FALSE
                  || (isset($result['actionCode']) && intval($result['actionCode']) !=0)
                  ) {
                    $result['result'] = 'FAIL';
                    # writeDebugInfo("returning FAIL: ", $result);
                    return $result;
                }
            }
            # writeDebugInfo("KT-2000");
            $orderStatus = $result['OrderStatus'] ?? $result['orderStatus'] ?? FALSE;
            if( $orderStatus !== FALSE ){

                # writeDebugInfo("KT-3001");
                if($orderStatus == self::BANK_PAY_OK)
                    $result['result'] = 'OK';
                elseif($orderStatus == self::BANK_PAY_FAIL) {
                    $result['result'] = 'FAIL';
                    if(isset($result['ErrorMessage']))
                        $result['message'] = $result['ErrorMessage'];
                }
                elseif($orderStatus == self::BANK_ORDER_EXPIRED) {
                    $result['result'] = 'EXPIRED';
                    if(isset($result['ErrorMessage']))
                        $result['message'] = $result['ErrorMessage'];
                }
                elseif($orderStatus == self::BANK_NO_ACTIVITY) {
                    $result['result'] =  0;
                }
                if($returnFmt) return $result;

                if( $orderStatus == self::BANK_PAY_OK ){
                    $ret = 'OK';
                }
                elseif( $orderStatus == self::BANK_PAY_FAIL ){
                    $ret = 'FAIL';
                    # else $ret = "Оплата не прошла: ".$result['ErrorMessage'];
                }
                else {
                    $ret = 0;
                }
                # writeDebugInfo("getOrderStatus: return : ", $ret);
                return $ret;
            }
            elseif(stripos($response, 'Out of service')) {
                $this->_errorMessage = 'Сервис временно остановлен';
                return (($returnFmt ==='array')? ['result'=>0, 'message'=>$this->_errorMessage] : 0);
            }
            else return (($returnFmt ==='array')? ['result'=>0, 'message'=>$this->_errorMessage] : 0);
        }
        return (($returnFmt ==='array')? ['result'=>0, 'message'=>'Ошибка обращения к сервису'] : 0);
    }
    # отмена неоплаченного заказа
    public function cancelOrder( $orderId, $returnFmt = 'array' ) {
        $pars = [
        'orderId' => $orderId,
        'language' => 'ru',
        'userName' => self::$login,
        'password' => self::$password,
        ];
        $url = self::$serviceUrl . self::CANCEL_ORDER;
        $response = \Curla::getFromUrl($url,$pars,10);
        if ($this->logging) {
            @file_put_contents(self::$logDay, "\n".date('Y-m-d H:i:s') . ' decline url: '.$url. "\n", FILE_APPEND );
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' decline fields: '.print_r($pars,1)."\n", FILE_APPEND );
            $rawResponse = \Curla::getRawResponse();
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' decline raw response: '.$rawResponse. "\n", FILE_APPEND );
        }

        if( $response ){
            # Строка ответа:
            $result = self::$rawResult = @json_decode($response, true);
            if( isset($result['errorCode']) ){
                if ($returnFmt === 'array') {
                    if($result['errorCode'] == 0)
                        $result['result'] = 'OK';
                    else {
                        $result['result'] = 'FAIL';
                        if(isset($result['ErrorMessage']))
                            $result['message'] = $this->_errorMessage = $result['ErrorMessage'];
                    }
                    # writeDebugInfo("to return: ", $result);
                    return $result;
                }

                return FALSE;
            }

            elseif(stripos($response, 'Out of service')) {
                $this->_errorMessage = 'Сервис временно остановлен';
                return (($returnFmt ==='array')? ['result'=>0, 'message'=>$this->_errorMessage] : 0);
            }
            else { # errorCode нет - значит, и ошибки нет
                if ($returnFmt === 'array') {
                    $result['result'] = 'OK';
                    # writeDebugInfo("to return: ", $result);
                    return $result;
                }
                return TRUE;
            }
        }
        return (($returnFmt ==='array')? ['result'=>'FAIL', 'message'=>'Ошибка обращения к сервису'] : 0);
    }
    /**
    * регистрация заявки на оплату
    *
    * @param mixed $params:
    * 'premium' = > сумма в рублях
    * 'orderid' = > наш внутренний ИД ордера
    * 'policyno' = > номер полиса
    * 'email' = > email страхователя
    * 'fullname' = > ФИО страхователя
    * 'url_success' = > на какой URL перекинуть клиента при успехе оплаты
    * 'url_fail' = > на какой URL перекинуть клиента при ошибке
    */
    function registerOrder($params){
        $order_id = isset($params['orderid']) ? $params['orderid'] : (date('YmdHis').'/'.time());
        $vars = [
            'userName' => self::$login,
            'password' => self::$password,
            'orderNumber' => $order_id, #  ID заказа в магазине
        ];
        # echo "login:".self::$login . ' passw:'.self::$password;

        /* # Корзина для чека (для фискализации обязательно!) */
        $email = isset($params['email']) ? $params['email'] : '';
        $insrname = isset($params['fullname']) ? $params['fullname'] : '';
        $premium = number_format($params['premium'],2,'.','');
        $premiumCop = round($params['premium'] * 100);
        $description = "DOG_NUMBER: (1-pol) $params[policyno] (1-sum) $premium INSURER_NAME: $insrname";
        # $description = "Оплата полиса $params[policyno]";
        # поле в формате как Егор сказал, для дальнейшей корректной обработки у нас
        $vars['description'] = $description;

        if ( $this->makeBundle ) {

            $cart = [];
            $cart[] = [
              'positionId' => 1,
              'name' => $description,
              'quantity'=> ['value'=>1, 'measure'=>'pcs'],
              'itemAmount' => $premiumCop,
              'itemCode' => $params['policyno'], # rand(1000,900000) | $params['policyno'],
            ];
        $currentTime = Time();
        $orderBundle = [
                "orderCreationDate" => $currentTime,
                "customerDetails" => [
                    "email" => $email,
                    "fullName" => $insrname,
                    # "passport" => $passport,
                    # "inn" => $inn, # если передавать, должен быть валидирован!
                ],
                "cartItems" => [
                    "items" => [
                    [
                        "positionId" => "1",
                        "name" => $description,
                        "quantity" => ["value" => 1, "measure" => "pcs"],
                        "itemAmount" => $premiumCop,
                        "itemCode" => $description,
                        "itemPrice" => $premiumCop,
                    ],
                    ],
                ],
            ];

            $vars['orderBundle'] = json_encode($orderBundle); # ,JSON_UNESCAPED_UNICODE - плохо с русским в банке!
        }
        /* Сумма заказа в копейках */
        $vars['amount'] = $premiumCop;

        /* URL куда клиент вернется в случае успешной оплаты */
        $vars['returnUrl'] = $params['url_success'];

        # {upd/2022-08-10} если передана дата-время сгорания ордера (Формат: yyyy-MM-ddTHH:mm:ss )
        if(!empty($params['order_expire'])) {
            $vars['expirationDate'] = self::formatDateTime($params['order_expire']);
            if(self::$debug) writeDebugInfo("передаю в запрос дату истечения expirationDate: ", $vars['expirationDate']);
        }
        /* URL куда клиент вернется в случае ошибки */
        if (!empty($params['url_fail']))
            $vars['failUrl'] = $params['url_fail'];

        if (self::$debug) writeDebugInfo("params to call API ".self::$serviceUrl . "register.do: ", $vars);
        if ($this->logging) {
            $this->logHandle = @fopen(self::$logDay, 'wa');
        }
        if ($this->logHandle) @fwrite($this->logHandle, date('Y-m-d H:i:s') . ' '.self::$serviceUrl. "register.do params:\n" . print_r($vars,1));
        try {
            if(self::$debug) writeDebugInfo("all vars for RegisterOrder: ", $vars);
            # \Curla::setDebug(1); # включаю отладку CURLA
            $result = \Curla::getFromUrl(self::$serviceUrl . self::REGISTER_CARD , $vars, 10); # 'register.do' as Json
            if ($this->logHandle) {
                $rawResp = \Curla::getRawResponse();
                @fwrite($this->logHandle, date('Y-m-d H:i:s') . ' register.do Raw response : '.$rawResp);
                if(is_array($result))
                    @fwrite($this->logHandle, date('Y-m-d H:i:s') . ' register.do response : '.print_r($result,1));
                @fclose($this->logHandle);
            }
        }
        catch (Exception $e) {
            $result = [
              'errorMessage' => 'Неудача вызова https/API ' .self::$serviceUrl . 'register.do : '. $e->getMessage()
            ];
        }
        if (self::$debug) writeDebugInfo("Sber register order: curl call result: ", $result);

        if(is_array($result) && !empty($result['errorMessage'])) {
            $ret = [
                'result' => 0,
                'message' => 'Ошибка при вызове сервиса эквайринга: '.$result['errorMessage']
            ];
            return $ret;
        }
        elseif(!empty($result) && is_string($result)) {

            $result = json_decode($result, true);
            if( !empty($result['orderId'])) {
                $ret = [
                    'result' => 1,
                    'orderid' => $result['orderId'],
                    'formurl' => $result['formUrl']
                ];
                #return $ret; # 'redirect:'.$response['formUrl'];
            }
            else {
                $ret = [
                  'result' => 0,
                  'errorCode' => (isset($result['errorCode'])?$result['errorCode']:'-1'),
                  'message' => (isset($result['errorMessage'])?$result['errorMessage']:'неизвестная ошибка'),
                ];
            }
        }
        else {
            $ret = [
                'result' => 0,
                'message' => 'Вызов не был обработан: ', # . print_r($result,1),
            ];
            writeDebugInfo("вызов сервиса эквайринга - результат: ", $result);
        }

        return $ret;
    }

    /**
    * пробую получить QR-код для СБП оплаты
    * https://3dsec.sberbank.ru/payment/rest/sbp/c2b/qr/dynamic/get.do
    *   {"mdOrder":"4781900e-f4c0-7a8d-86f6-abb3011a1708","qrHeight":10,"qrWidth":10,"qrFormat":"matrix"}
    * В ответе при успехе будет "qrId": "79af398345835..."
    */
    public function getSbpCode($orderId, $returnFmt = 'array') {
        $pars = [
          'mdOrder' => $orderId,
          'language' => 'ru',
          'userName' => self::$login,
          'password' => self::$password,
          'qrHeight' => 12,
          'qrWidth' => 12,
          'qrFormat' => 'matrix',
        ];

        $func = self::GET_SBP_CODE;
        $url = self::$serviceUrl . self::GET_SBP_CODE;
        $response = \Curla::getFromUrl($url,$pars,10);
        if ($this->logging) {
            @file_put_contents(self::$logDay, "\n".date('Y-m-d H:i:s') . " $func url: $url\n", FILE_APPEND );
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . ' $func fields: '.print_r($pars,1)."\n", FILE_APPEND );
            $rawResponse = \Curla::getRawResponse();
            if($debAcq) {
                $callFrom = "call _URI:" .$_SERVER['REQUEST_URI'] . ", _p:".print_r(AppEnv::$_p,1). "\n call trace: "
                  . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4),1);
                @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . $callFrom. "\n", FILE_APPEND );
            }
            @file_put_contents(self::$logDay, date('Y-m-d H:i:s') . " $func raw response: $rawResponse\n", FILE_APPEND );
        }

        if( $response ){
            # Строка ответа:
            $result = self::$rawResult = @json_decode($response, true);
            # writeDebugInfo("decoded result: ", $result);
            $ret = $result;
            if(isset($result['errorMessage'])) {
                $ret['result'] = 'ERROR';
                $ret ['message'] = $result['errorMessage'];
            }
            if(!empty($ret['qrId'])) {
                $ret['result'] = 'OK';
            }
        }
        return $ret;
    }
    # превращаю дату в формат "yyyy-mm-ddThh:mm:ss" (типа UTC)
    public static function formatDateTime($strg) {
        $dtPart = substr($strg,0,10);
        $timePart = substr($strg,11);
        $ret = to_date($dtPart);
        if(!empty($timePart)) $ret .= 'T' . $timePart;
        return $ret;
    }

}