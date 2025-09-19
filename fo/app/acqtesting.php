<?php
/**
* @package ALFO
* @name jsvc/test.php
* Страница для тестирования вызовов JSON API сервиса ALFO
* modified 2025-09-12
*/

class AcqTesting {

    private static $acqObject = NULL;

    public static function form() {
        $acqConfig = include('cfg/cfg-acquiring.php');
        $acqOptions = '';
        foreach($acqConfig as $key=>$cfg) {
            $acqOptions  .= "<option value=\"$key\">$cfg[name]</option>\n";
        }
        $jsCode = self::getJs();
        AddHeaderJsCode($jsCode);
        AppEnv::setPageTitle("Тесты эквайринга");
        $html = <<< EOHTM
<center>
<form id="fm_apitests" onsubmit="return false">
<div class="bordered p-2">
<input type="hidden" name="action" value="execute" />
 <table>

 <tr>
   <td class="p-2">Настройка эквайринга:<br><select name="acqconfig" id="acqconfig" class="ibox w200">
   $acqOptions
   </select>
   </td>
   <td class="p-2">операция:<br><select name="exec_func" id="exec_func" class="iboxm w300" onchange="acqTest.chgFunction()">
     <option value="">Выбрать!</option>
     <option value="registerOrder">Создание обычной оплаты</option>
     <option value="registerOrderSBP">Создание СБП оплаты</option>
     <option value="getOrderStatus">Получить статус ордера</option>
     <option value="getSbpCode">Получить код ордера для СБП</option>
   </select>
   </td>
   <td>Или имя метода<br><input type="text" name="usr_func" id="usr_func" class="ibox w120" /></td>
 </tr>
   <tr>
       <td class="p-2">номер полиса<br><input type="text" class="ibox w160" name="policyno" id="policyno" value="SOL-010000001" /></td>
       <td class="p-2">ФИО плательщика<br><input type="text" class="ibox w300" name="fio" id="fio" value="Непотребный Маврикий Закидонович" /></td>
       <td class="p-2">сумма платежа, р.<br><input type="text" class="ibox w100" name="premium" id="premium" value="5000" /></td>
   </tr>
   <tr class="ordnum" style="_display:none">
     <td class="p-2">Номер ордера<br><input type="text" name="order_id" id="order_id" class="ibox w300" ></td>
   </tr>

   <tr>
     <td class="p-2" colspan="4">
         <div class="area-buttons card-footer" >
           <button class="btn btn-primary" onclick="acqTest.runTest()">Выполнить запрос</button>
           <button class="btn btn-primary" onclick="acqTest.clearLog()">Очистить лог</button>
         </div>
     </td>
   </tr>
 </table>
</div>
</form>
<pre id="results" class="bordered" style="min-height:100px; max-height:400px; overflow:auto;">results...
</pre>

<a href="./">back to FO</a><br>
</center>

EOHTM;
        AppEnv::appendHtml($html);
        appEnv::finalize();
    }
    public static function getJs() {
        $jsRet = <<< EOJS
acqTest = {
  chgFunction: function() {}
  ,runTest: function() {
    var pars = $("#fm_apitests").serialize();
    asJs.sendRequest("./?p=acqtesting", pars, true);
  }
  ,clearLog: function() {
    $("#results").html();
  }
};
EOJS;
        return $jsRet;
    }
    /**
    * Выполняю запрос acquiring API
    *
    */
    public static function execute() {
        $cfg = AppEnv::$_p['acqconfig'] ?? '';
        $func = AppEnv::$_p['exec_func'] ?? '';
        $acqConfig = include('cfg/cfg-acquiring.php');
        $myCfg = $acqConfig[$cfg] ?? [];
        $provider = $myCfg['provider'] ?? '';

        if( empty($provider) )
            exit('1' . AjaxResponse::showError('Выбранная конфигурация н имеет провайдера'));

        include_once("app/acquiring.$provider.php");
        $clsName = "\\Acquiring\\" . $provider;
        self::$acqObject = new $clsName($myCfg);
        if(!method_exists(self::$acqObject, $func))
            exit('1' . AjaxResponse::showError("В модуле <b>$provider</b> метод не <b>$func</b> поддерживается!"));

        $acqParams = [];
        switch($func) {
            case 'registerOrder':
                $acqParams['policyno'] = AppEnv::$_p['policyno'];
                $acqParams['email'] = (empty(AppEnv::$_p['email']) ? 'aleksandr.selifonov@zetains.ru' : AppEnv::$_p['email']);
                $acqParams['fullname'] = AppEnv::$_p['fio'] ?? 'Проверочный Макар Сергеевич';
                $acqParams['premium'] = AppEnv::$_p['premium'] ?? 4800;
                $acqParams['url_success'] = AppEnv::$_p['url_success'] ?? 'https://life.zettains.ru/';
                $acqParams['description'] = AppEnv::$_p['description'] ?? 'Оплата тестового договора '.AppEnv::$_p['policyno'];
                break;
            case 'getOrderStatus':
            case 'getSbpCode':
                $acqParams = AppEnv::$_p['order_id'];
                break;

        }
        if(AppEnv::isLocalEnv() && $provider!== 'emulator')
            exit('1' . AjaxResponse::setHtml('results', "$func, params: <pre>" . print_r(AppEnv::$_p,1)
               .'<br>acqParams:'. print_r($acqParams,1).'</pre>'));

        $result = self::$acqObject->$func($acqParams);
        writeDebugInfo("Raw calling $func result: ", $result);
        exit('1' . AjaxResponse::setHtml('results', "Raw calling $func result: <pre>". print_r($result,1).'</pre>'));
     }
}
$action = AppEnv::$_p['action'] ?? 'form';
if(!empty($action)) {
    if(method_exists('AcqTesting', $action)) AcqTesting::$action();
    else exit("Undefined action $action");
}