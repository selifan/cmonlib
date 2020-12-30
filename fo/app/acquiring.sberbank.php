<?php
/**
* \Acquiring\Sberbank class - оплата через эквайринг сбер-банка
* TODO: дописать!
**/
namespace Acquiring;

class Sberbank {
    private static $login = "login";
    private static $password = "password";
    private static $serviceUrl = "https://3dsec.sberbank.ru/payment/rest/"; #
    protected $logging = 1;
    static $debug = 1;

    public function __construct($params = FALSE) {
        if (is_array($params)) $this->setParams($params);
    }

    public function setParams($pars) {
        if (!empty($pars['login'])) self::$login = $pars['login'];
        if (!empty($pars['password'])) self::$password = $pars['password'];
        if (!empty($pars['url'])) self::$serviceUrl = $pars['url'];
    }
    function getOrderStatus( $orderId ) {
        // https:/server/application_context/rest/getOrderStatus.do? orderId=b8d70aa7-bfb3-4f94-b7bb-aec7273e1fce&language=ru&password=password&userName=userName
        $pars = [
        'orderId' => $orderId,
        'language' => 'ru',
        'userName' => self::$login,
        'password' => self::$password,
        ];
        $url = self::$serviceUrl . "getOrderStatus.do?" . http_build_query($pars);
        $response = \Curla::getFromUrl($url);

        if( $response ){

            $response = json_decode($response, true);

            if( $response['ErrorMessage'] ){

                if( $response['OrderStatus'] == 2 ){
                    return "Спасибо, заказ №".$response['OrderNumber']." оплачен.";
                } else {
                    return "Статус заказа: ".$response['ErrorMessage'];
                }
            }
        }
        return false;
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
        $order_id = isset($params['orderid']) ? $params['orderid'] : date('YmdHis').rand(10000,99999);
        $vars = [
            'userName' => self::$login,
            'password' => self::$password,
            'orderNumber' => $order_id,         /* ID заказа в магазине */
        ];
        echo "login:".self::$login . ' passw:'.self::$password;

        /* Корзина для чека (необязательно)
        $cart = array(
            array(
                'positionId' => 1,
                'name' => 'Название товара',
                'quantity' => array(
                    'value' => 1,
                    'measure' => 'шт'
                ),
                'itemAmount' => 1000 * 100,
                'itemCode' => '123456',
                'tax' => array(
                    'taxType' => 0,
                    'taxSum' => 0
                ),
                'itemPrice' => 1000 * 100,
            )
        );
        **/
        $email = isset($params['email']) ? $params['email'] : '';
        $insrname = isset($params['fullname']) ? $params['fullname'] : '';
        $premium = number_format($params['premium'],2);
        $premiumCop = round($params['premium'] * 100);

        # $description = "DOG_NUMBER: (1-pol) $params[policyno] (1-sum) $premium\nINSURER_NAME: $insrname";
        $description = "Оплата полиса $params[policyno]";
        # поле в формате как Егор сказал, для дальнейшей корректной обработки у нас
        $vars['description'] = $description;
        $cart = [];
        $cart[] = [
          'positionId' => 1,
          'name' => 'оплата полиса', # $description
          'quantity'=> ['value'=>1, 'measure'=>'pcs'],
          'itemAmount' => $premiumCop,
          'itemCode' => rand(1000,900000), # $params['policyno'],
        ];

        $vars['orderBundle'] = json_encode(
            [
              'customerDetails' => [
                'email' => $email,
                'fullName' => $insrname,
              ],

                'cartItems' => array(
                    'items' => $cart
                )
            ]
            , JSON_UNESCAPED_UNICODE
        );

        /* Сумма заказа в копейках */
        $vars['amount'] = $premiumCop;

        /* URL куда клиент вернется в случае успешной оплаты */
        $vars['returnUrl'] = $params['url_success'];
        /* URL куда клиент вернется в случае ошибки */
        $vars['failUrl'] = $params['url_fail'];

        /* Описание заказа, не более 24 символов, запрещены % + \r \n */
        # $vars['description'] = 'Оплата полиса страхования '  . $params['policyno'] . ", ордер $order_id";

        $result = \Curla::getFromUrl(self::$serviceUrl . 'register.do', $vars, 10);
        if (self::$debug) writeDebugInfo("curl cal result: ", $result);
        /*
        $ch = curl_init(self::$serviceUrl . '?' . http_build_query($vars));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($this->logging) writeDebugInfo(__FUNCTION__, ":raw curl response: ", $response);
        */
        if(!empty($result) && is_string($result)) {

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
                'message' => 'Вызов не был обработан'
            ];
        }

        return $ret;
    }

}