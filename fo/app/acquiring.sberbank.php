<?php
/**
* \Acquiring\Sberbank class - оплата через эквайринг сбер-банка
* modified 2021-11-02
**/
namespace Acquiring;

class Sberbank {

    private static $login = "login";
    private static $password = "empty";
    private static $serviceUrl = "https://3dsec.sberbank.ru/payment/rest/"; #
    protected $logging = 1;
    protected $logHandle = 0;
    const BANK_PAY_OK = 2;
    const BANK_PAY_FAIL = 6;
    static $debug = 0;
    const LOGFILE = 'tmp/acquiring.sber.log';
    protected $makeBundle = FALSE; # делать ли блок с корзиной (не тестовой среде Bundle вызывает внутреннюю ошибку (по сост. на 2020-12-28)
    public function __construct($params = FALSE) {
        if (is_array($params)) $this->setParams($params);
        $this->makeBundle = \appEnv::isProdEnv(); # Bundle работает только на боевой среде (сост.на 03.02.2021)!
        # TODO: когда в Сбере починят тестовую среду, должно быть всегда TRUE!
        if (\appEnv::isProdEnv()) self::$debug = 0;
    }

    public function setParams($pars) {
        if (!empty($pars['login'])) self::$login = $pars['login'];
        if (!empty($pars['password'])) self::$password = $pars['password'];
        if (!empty($pars['serviceUrl'])) self::$serviceUrl = $pars['serviceUrl'];
    }

    # получаю результат оплаты из банка
    function getOrderStatus( $orderId, $returnBool = 'array' ) {
        // https:/server/application_context/rest/getOrderStatus.do? orderId=b8d70aa7-bfb3-4f94-b7bb-aec7273e1fce&language=ru&password=password&userName=userName
        $pars = [
        'orderId' => $orderId,
        'language' => 'ru',
        'userName' => self::$login,
        'password' => self::$password,
        ];
        $url = self::$serviceUrl . "getOrderStatus.do"; # . http_build_query($pars);
        $response = \Curla::getFromUrl($url,$pars,10);
        if ($this->logging) {
            file_put_contents(ALFO_ROOT.self::LOGFILE, "\n".date('Y-m-d H:i:s') . ' getOrderstatus url: '.$url. "\n", FILE_APPEND );
            file_put_contents(ALFO_ROOT.self::LOGFILE, date('Y-m-d H:i:s') . ' getOrderstatus fields: '.print_r($pars,1)."\n", FILE_APPEND );
            $rawResponse = \Curla::getRawResponse();
            file_put_contents(ALFO_ROOT.self::LOGFILE, date('Y-m-d H:i:s') . ' getOrderstatus raw response: '.$rawResponse. "\n", FILE_APPEND );
        }

        if( $response ){
            # Строка ответа:
            $result = @json_decode($response, true);
            if( isset($result['OrderStatus']) ){
                if ($returnBool === 'array') {
                    $result['result'] = ($result['OrderStatus'] == self::BANK_PAY_OK) ? 'OK'
                      : ($result['OrderStatus'] == self::BANK_PAY_FAIL ? 'FAIL':'NODATA');
                    return $result;
                }

                if( $result['OrderStatus'] == self::BANK_PAY_OK ){
                    if ($returnBool === TRUE) return TRUE;
                    else return "Спасибо, заказ оплачен.";
                }
                else {
                    if ($returnBool) return FALSE;
                    else return "Оплата не прошла: ".$response['ErrorMessage'];
                }
            }
            elseif(stripos($response, 'Out of service')) {
                $this->_errorMessage = 'Сервис временно остановлен';
                return (($returnBool ==='array')? ['result'=>0, 'message'=>$this->_errorMessage] : 0);
            }
        }
        return (($returnBool ==='array')? ['result'=>0, 'message'=>'Ошибка обращения к сервису'] : 0);
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

        /* URL куда клиент вернется в случае ошибки */
        if (!empty($params['url_fail']))
            $vars['failUrl'] = $params['url_fail'];

        if (self::$debug) writeDebugInfo("params to call API ".self::$serviceUrl . "register.do: ", $vars);
        if ($this->logging) {
            $this->logHandle = @fopen(ALFO_ROOT . self::LOGFILE, 'wa');
        }
        if ($this->logHandle) @fwrite($this->logHandle, date('Y-m-d H:i:s') . ' '.self::$serviceUrl. "register.do params:\n" . print_r($vars,1));

        $result = \Curla::getFromUrl(self::$serviceUrl . 'register.do', $vars, 10);
        if ($this->logHandle) {
            @fwrite($this->logHandle, date('Y-m-d H:i:s') . ' response : '.print_r($result,1));
            @fclose($this->logHandle);
        }

        if (self::$debug) writeDebugInfo("register: curl call result: ", $result);

        if(is_array($result) && !empty($result['errorMessage'])) {
            $ret = [
                'result' => 0,
                'message' => 'Ошибка при вызове серсиса эквайринга: '.$result['errorMessage']
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

}