<?php
/**
* \Acquiring\Emulator class - эмуляция сервиса онлайн-эквайринга
* @version 0.80.001
* modified 2021-12-23
**/
namespace Acquiring;

class Emulator {
    const T_ORDERS = 'alf_acqemulator';
    # private static $login = "login";
    # private static $password = "empty";
    private static $serviceUri = "?p=acquiring.emulator&acqaction=form"; #
    static $debug = 0;
    static $orderData = NULL;
    protected $makeBundle = FALSE; # делать ли блок с корзиной (не тестовой среде Bundle вызывает внутреннюю ошибку (по сост. на 2020-12-28)

    public function __construct($params = FALSE) {
        if (is_array($params)) $this->setParams($params);
    }

    public function setParams($pars) {
        # if (!empty($pars['login'])) self::$login = $pars['login'];
        # if (!empty($pars['password'])) self::$password = $pars['password'];
    }

    # получаю статус оплаты из "банка"
    function getOrderStatus( $orderId, $returnBool = FALSE ) {
        // https:/server/application_context/rest/getOrderStatus.do? orderId=b8d70aa7-bfb3-4f94-b7bb-aec7273e1fce&language=ru&password=password&userName=userName
        $dta = self::getOrder($orderId);
        if (!isset($dta['stateid'])) {
            if ($returnBool) return FALSE;
            else return "Неизвестный ИД ордера!";

        }
        if( $dta['stateid'] == 'Y' ){
            if ($returnBool) return TRUE;
            return "Спасибо, заказ $orderId оплачен.";
        }
        else {
            if ($returnBool) return FALSE;
            return "Заказ $orderId не оплачен";
        }
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
        if (self::$debug) writeDebugInfo("registerOrder, params:", $params);
        $order_id = isset($params['orderid']) ? $params['orderid'] : (date('YmdHis').'/'.time());

        $returnUrl = $params['url_success'];
        $failUrl = (!empty($params['url_fail'])) ? $params['url_fail'] : '';

        $oData = [ 'src_orderid' => $order_id,
          'policyno' => (isset($params['policyno']) ? $params['policyno'] : 'ZZZ'),
          'stateid' => '0',
          'returnurl'=> $returnUrl,
          'failurl' => $failUrl,
          'created'=>'{now}',
        ];
        $newid = \appEnv::$db->insert(self::T_ORDERS,$oData);
        if (self::$debug) writeDebugInfo("add order result:", $newid, ' query:', \appEnv::$db->getLastQuery());

        if (!$newid) {
            writeDebugInfo("SQL error ", \appEnv::$db->sql_error(), ' SQL:', \appEnv::$db->getLastQuery());
            return ['result'=>0, 'message' =>'Ошибка создания записи'];
        }
        $formurl = \appEnv::getConfigValue('comp_url');
        if (substr($formurl,-1) !='/') $formurl .= '/';
        $formurl .= self::$serviceUri . "&orderid=$newid";

        $ret = [
            'result' => 1,
            'orderid' => $newid,
            'formurl' => $formurl,
        ];
        if (self::$debug) writeDebugInfo("returning:", $ret);

        return $ret;
    }
    static function getOrder($id) {
        if (!isset(self::$orderData['id']) || self::$orderData['id']!=$id)
            self::$orderData = \appEnv::$db->select(self::T_ORDERS,['where'=>['id'=>$id],'singlerow'=>1]);
        return self::$orderData;
    }
    # рисую форму для клиента - выбор варианта - оплатить или симулировать сбой оплаты
    public static function form() {
        $id = isset(\appEnv::$_p['orderid']) ? \appEnv::$_p['orderid'] : 0;
        if (self::$debug) writeDebugInfo("open form, _p:", \appEnv::$_p);
        self::getOrder($id);
        $policyno = isset(self::$orderData['policyno']) ? self::$orderData['policyno'] : '';
        $title = 'ТЕСТИРОВАНИЕ: Симуляция оплаты договора '. $policyno;
        \appEnv::setPageTitle($title);
        \appEnv::appendHtml("<h3>$title</h3>");
        self::getOrder($id);
        if (!isset(self::$orderData['id'])) {
            \appEnv::appendHtml('Неверный ИД ордера!');
        }
        elseif(!empty(self::$orderData['stateid'])) {
            $state = (self::$orderData['stateid'] == 'Y') ? 'Оплата уже произведена!' : 'Оплата по ордеру не прошла. Надо заказать новый ордер на оплату';
            \appEnv::appendHtml("<p class='ct error'>$state</p>");
        }
        else {
            # кнопки выбора чего сделать
            # $returnPayed = self::$orderData['returnurl'];
            # $returnFail = self::$orderData['failurl'];
            $id = self::$orderData['id'];
            $jsCode = <<< EOJS
ackEmul = {
  setPay: function(val) {
    SendServerRequest("./?p=acquiring.emulator&acqaction=setPayed&result=", {result:val, orderid:'$id'},true, true);
  },
};
EOJS;
            \addJsCode($jsCode,'head');

            $html = <<< EOHTM
<div class="bounded ct" style="padding:3em; margin:3em; line-height:3em;">
  <input type="button" class="button" style="margin-right:2em" onclick="ackEmul.setPay('Y')" value="ОПЛАТИТЬ договор" />
  <input type="button" class="button" onclick="ackEmul.setPay('N')" value="Эмулировать ОШИБКУ" />
</div>
EOHTM;

            \appEnv::appendHtml($html);
        }
        \appEnv::finalize();
    }

    # ajax команда с формы клиента - проставить оплату или нет
    public static function setPayed() {
        $result = \appEnv::$_p['result'];
        $id = \appEnv::$_p['orderid'];
        # $ret = '1' . \ajaxResponse::showMessage("TODO: set $result for order $id");
        $dta = self::getOrder($id);
        $err = FALSE;
        if (!isset($dta['id'])) $err = 'Ошибка в параметрах (orderid)';
        elseif (!empty($dta['stateid'])) $err = 'Отметка в ордере уже проставлена: '. ($dta['stateid'] =='Y' ? 'УСПЕХ':'ОШИБКА');
        else {
            $updtResult = \appEnv::$db->update(self::T_ORDERS, ['stateid'=>$result], ['id'=>$id]);
            if (self::$debug) writeDebugInfo("update emul payment result = [$updtResult], SQL :", \appEnv::$db->getLastQuery());
            if (!$updtResult) $err = 'Не сработало, попробуйте позднее! (Ошибка при записи в БД)';
        }
        if ($err) exit('1' . \AjaxResponse::showError($err));

        $finalUrl = ($result === 'Y') ? $dta['returnurl'] : $dta['failurl'];
        # $ret = '1' . \AjaxResponse::showMessage($finalUrl);
        $ret = '1' . \AjaxResponse::gotoUrl($finalUrl); # клиента сразу перебросит на URL
        exit($ret);
    }
}

if (!empty(\appEnv::$_p['acqaction'])) {
    $action = trim(\appEnv::$_p['acqaction']);
    if(method_exists('\Acquiring\Emulator', $action)) {
        Emulator::$action();
        exit;
    }
    else exit("Неизвестная операция - $action");
}