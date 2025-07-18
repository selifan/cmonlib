<?php
/**
* @package ALFO
* @name jsvc/test.php
* Страница для тестирования вызовов JSON API сервиса ALFO
* modified 2025-07-18
*/
define('SAVE_RAW_XML', 0); # сохранять в файлы XML содержимое запроса и ответа (включить трейсинг SOAP!)
# define('CLIENTCALL', TRUE); # TRUE - неавторизованнный клиент из еШопа (ALFO сам найдет ИД по своим настройкам)
define('TOKEN_PROD' ,'FK9S94ZJ5KD192FL0L4DY7W023V61');
define('TOKEN_TEST' ,'HF04726SFKFBNSJK2048CVMNRU758C6D');
$toServer = 'local'; # local|test1|test2|prod - куда слать запросы

$svcDebug = 0;

define('TEST_CLIENTID', '1001');
define('DEFAULT_MODULE', 'trmig');

if (!empty($_GET['debug'])) {
    error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}
class TestParams {
    static $fromClient = FALSE; # эмулирую вызовы от неавторизованного клиента сайта (сам себе оформляет полис)
    static $debug = 0;
    static $userSession = 1; # TRUE - имитирую передачу сессии пользователя (для лога калькуляций в eShop)
    static $rawRequest = '';
    # для проверки блокировки создания полиса на того же чела:
    const FIX_NAME = 'Тот-Же-Самый';
    const FIX_FIRSTNAME = 'Афиноген';
    const FIX_MDLNAME = 'Дармидонтович';
    const FIX_BIRTH = '21.01.1985';
    const FIX_DOCSER = '4501';
    const FIX_DOCNO = '787878';
}


if (is_file('../app/alfo_core.php'))
    include_once('../app/alfo_core.php');

$proto = $_SERVER['REQUEST_SCHEME'];
$host = $_SERVER['HTTP_HOST'];
$dir = dirname($_SERVER['REQUEST_URI']);

global $url;

$pars = array_merge($_GET, $_POST);

$userToken = TOKEN_TEST;

# выбор на какой сервер слать запрос:
if(!empty($pars['ask_server'])) $toServer = $pars['ask_server'];

if($svcDebug) {
    writeDebugInfo("pars: ", $pars);
    writeDebugInfo("toServer: ", $toServer);
}
if ($toServer === 'prod') {
    $url = "https://clientcab.group.zettains.ru/fo/jsvc/";
    $userToken = TOKEN_PROD; # token on PROD
}
elseif ($toServer === 'test1') {
    $url = "https://clientcabtest.group.zettains.ru/fo/jsvc/";
}
elseif ($toServer === 'test2') { # pre-prod
    $url = "o-alfo-ccab3lt.dmz.azru/fo/jsvc/";
    $userToken = TOKEN_PROD; # token on PROD
}
else { # local и все прочие
    $url = "$proto://$host{$dir}/"; # адрес вызываемого сервиса
    if ( appEnv::isProdEnv() )
        $userToken = TOKEN_PROD;
}
if($svcDebug) writeDebugInfo("url to call: $url, \n   token: $userToken");
if (!empty($_GET['verbose'])) {
    $url .= "?verbose=1";
}
# можно передать URL сервиса через строку адреса  &url=https://clientcabtest.allianzlife.ru/fo/jsvc
if (!empty($_GET['url'])) $url = $_GET['url'];


if (!empty($_GET['token']))
    $userToken = $_GET['token'];

$userid = ''; # Здесь ввести свой Uid
$myemail = 'supermail@yandex.ru';

$promo = isset($_GET['promo']) ? $_GET['promo'] : ''; # промо-код для Плана Б
$rass = isset($_GET['oplata']) ? $_GET['oplata'] : ''; # рассрочка

# Если есть, читаю файл конфига:
/*
if (is_file(__DIR__ . '/__testdata.php')) {
    include(__DIR__ . '/__testdata.php');
    if (!empty(SvcTestData::$userToken)) $userToken = SvcTestData::$userToken;
    if (!empty(SvcTestData::$userid)) $userid = SvcTestData::$userid;
    if (!empty(SvcTestData::$email)) $myemail = SvcTestData::$email;
    if (!empty(SvcTestData::$url))  $baseurl = SvcTestData::$url;
    # WriteDebugInfo("new user id: ", $userid);
}
*/
if (defined('CLIENTCALL') && constant('CLIENTCALL')) $userid = 'client';
if (!empty($_GET['userid']) && $_GET['userid']>0 ) $userid = intval($_GET['userid']);

# Класс для генерации случайных ФИО и города
class RandomNames {

    static $lastNames = ['Неваляйко', 'Раздолбайко', 'Бывалый','Закарпатский','Забияка','Крупненький','Непроливайко','Канареечка','Килиманджаро',
      'Лошара','Пендаль','Хитрейший','Козлодранцев','Невпопадла'];
    static $firstNames  = ['Шустрик','Оголтей', 'Евстахий','Марзикарим','Колобас','Пироксидий','Афигений','Кочубей','Павлодарий','Зурбаган','Карбофос'];
    static $middleNames = ['Стоеросович', 'Михрютович','Грицацуевич','Колбасятович','Закидонович','Пирожкович','Януариевич','Невпихалович','Почемучич'];
    static $cities = ['Волгоград', 'Воронеж','Санкт-Петербург','Москва','Ярославль','Иркутск','Тверь','Кострома','Орёл','Великие Чукчи'];

    static function getLastName() {
        $ioff = rand(0, count(self::$lastNames)-1);
        return self::$lastNames[$ioff];
    }
    static function getFirstName() {
        $ioff = rand(0, count(self::$firstNames)-1);
        return self::$firstNames[$ioff];
    }
    static function getMiddleName() {
        $ioff = rand(0, count(self::$middleNames)-1);
        return self::$middleNames[$ioff];
    }

    static function getCity() {
        $ioff = rand(0, count(self::$cities)-1);
        return self::$cities[$ioff];
    }
    public static function getFullName() {
        $ret = self::getLastName() . ' '. self::getFirstName() . ' ' . self::getMiddleName();
        return $ret;
    }
}

$req = isset($pars['req']) ? trim($pars['req']) : '';
$policyId = isset($pars['id']) ? trim($pars['id']) : '';
$birth = isset($pars['birth']) ? trim($pars['birth']) : '';
$module = (!empty($pars['module']) ? trim($pars['module']) : '');
$programid = (!empty($pars['programid']) ? trim($pars['programid']) : '');
$subtypeid = (!empty($pars['subtypeid']) ? trim($pars['subtypeid']) : '');
$serialize = $pars['serialize'] ?? 0;

if (empty($req)) {
    testForm();
 /*    die('
Call tests options:<br><pre>
?req=calc - test calculatePolicy(), (&progid={yourproduct} to test another program, trmig by default)
?req=next - test GetApplNumb(), next Policy number
?req=save - test savePolicy(), (create new policy, <b>trmig</b> product, OR update if &id=NNN)
?req=list - test getPolicyList()
?req=pay - test setAgreementPayed(), pass policy ID: &id={ID} [&online=1 - online payment flag] [&doc=NNNNNN] - номер пл.документа
?req=cancel - test cancelPolicy(), pass policy ID: &id={ID}
?req=getpolicy - test getPolicyData(), pass policy ID: &id={ID}
?req=getpdfpolicy - test getPdfPolicy(), policy ID: &id={ID}
?req=getpdfbill - test getPdfBill(), (Bill for policy) policy ID: &id={ID}
?req=getpdfagr - test getPdfAgreement(), policy ID: &id={ID}
?req=getbyinn - test getPolicyHolderData(), inn: &inn={INN_NUM}
?req=countries - запрос списка стран
</pre>
');
*/
}


$saveRequestDetails = 0;
$randomizeNames = TRUE; # генерить случайные ФИО поверх сохранёенных тест-кейсов "cacldata-<module>.php"

if (empty($birth)) $birth = '01.01.' . rand(1962,1999);

/**
$params = [
    'userToken' => $userToken,
    'module' => $module,
    'execFunc' => '',
    'params' => [
        'datefrom' => date('Y-m-d', strtotime('+10 days')),
        'datetill' => date('Y-m-d', strtotime('+2 years 9 days')),
        'vakcina' => '1',
        'insuredlist' => [
           [ 'birth'=>'02.05.1988', 'sex' => 'M', 'fullname' => 'Олух Олег Бывалович', 'fullname_lat' => 'Oluh', 'rez_country' => 'Молдова',
             'sex' => 'M',
           ]
        ]
    ],
];
**/

$result = NULL;
$policyId = isset($pars['policyid']) ? $pars['policyid'] : 0;
/* global $url;
writeDebugInfo("now url: $url, _POST: ", $_POST);
if (!empty($_GET['url']))
  $url = $_GET['url'];

  if (!empty($_GET['token']))
  $userToken = $_GET['token'];
*/
switch($req) {
    # Сначала простые запросы
    case 'countries':
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'getCountryList',
            'serialize' => $serialize,
        ];
        # $data = performCall($url, $params);
        break;

    case 'finddmspolicy':
        # запрос поиск ДМС полиса в таблице CHECK_POLICY_DMS, загружаемой из Диасофт
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'findDmsPolicy',
            'serialize' => $serialize,
            'params' => [
              'policySeries' => $pars['dms_ser'],
              'policyNo' => $pars['dms_no'],
            ]
        ];
        # $result = performCall($url, $params);
        break;

    case 'methodlist':
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'methodList',
            'serialize' => $serialize,
        ];
        # $result = performCall($url, $params);
        break;

    case 'finddmsbso':
        # запрос поиск БСО в таблице CHECK_BSO_DMS
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'findDmsBso',
            'serialize' => $serialize,
            'params' => [
              'bsoNum' => $pars['dms_bsonum'],
              # 'policyNo' => $pars['dms_no'],
            ]
        ];
        break;

    case 'calc':
        # $params = new stdClass();
        # $params->programId = 'Endowment';
        $calcParams = jsvcPreparePolicyParams($module, $programid, $subtypeid);
        $params = [
          'userToken' => $userToken,
          'module' => $module,
          'execFunc' => 'calculatePolicy',
          'serialize' => $serialize,
          'params' => $calcParams
        ];

        /*
        switch($module) {
            case 'planb':
                if ($rass!=='') $params->data->rassrochka = $rass;
                break;

            case 'invonline':
                $params->term = (empty($_GET['term']) ? 5 : intval($_GET['term'])); // 3 или 5 лет

                if (!empty($_GET['sa'])) $params->data->plc_sa = $_GET['sa'];
                elseif (!empty($_GET['premium'])) $params->data->premium = $_GET['premium'];
                else $params->data->plc_sa = 1000000;

                if (!empty($_GET['warr'])) $params->data->warranty = $_GET['warr'];  # гарантия
                else $params->data->warranty = 100;

                if (!empty($_GET['ba'])) $params->data->baseactive = $_GET['ba']; # Базовый актив

                break;

            case 'RiskControl': # Риск-Контроль
                # $params->birthDate = '01.02.2016'; # проверка вывода ошибки по возрасту
                $params->term = 1;
                $params->data->pppperiodicity = '1';
                if ($rass!=='') $params->data->pppperiodicity = $rass;
                # pppperiodicity = оплата : 1=ежегодно (раз в год), 2=раз в полгода, 4=ежеквартально, 12=ежемес, -1=единоврем
                # стандартные типы ALFO:   12=ежегодно              6=полгода        3=ежекварт 1=ежемес !
                $params->data->currency = 'rub';
                $params->data->in3sarub = '1000000';
                $params->data->in3from = '2'; # от Стр.суммы (1 - от премии, in3prub)
                $params->data->in4sarub = '500000';
                $params->data->in4from = '2';
                $params->data->in5sarub = '500000';
                $params->data->in5from = '2';
                # ...
                break;

            case 'RiskControl_Lite': # Риск-Контроль детский
                $params->term = 1; # единственный допустимый срок - 1 год
                $params->data->pppperiodicity = '1';
                if ($rass!=='') $params->data->pppperiodicity = $rass;
                $params->data->currency = 'rub';
                $params->birthDate = '01.01.2015';
                $params->data->in4sarub = '500000';
                $params->data->in4from = '2';
                $params->data->in5sarub = '300000';
                $params->data->in5from = '2';
                break;
        }
        */
        # $params->term = 1;
        try {
            $result = performCall($url, $params);
            # echo 'call calculate result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (Exception $e) {
            echo ('calculatePolicy: ERROR: <pre>'.  print_r($e,1) . '</pre>');
        }
        break;

    case 'next':
        try {
            # $params = new stdClass();
            $result = performCall($url, $params);
            echo 'call GetApplNumb result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (exception $e) {
            echo ('getNextStatementNo: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            showLastReqHtml();
        }
        break;

    case 'save':
        # $result = callSavePolicy($pars); меняю на стандартный вызов из подключаемого модуля
        $calcParams = jsvcPreparePolicyParams($module, $programid, $subtypeid);
        if(!empty(AppEnv::$_p['policyid'])) {
            # обновляю ранее созданный полис
            $calcParams['stmt_id'] = AppEnv::$_p['policyid'];
        }
        $params = [
          'userToken' => $userToken,
          'module' => $module,
          'execFunc' => 'savePolicy',
          'serialize' => $serialize,
          'params' => $calcParams
        ];

        try {
            $result = performCall($url, $params);
            # echo 'call calculate result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (Exception $e) {
            echo ('createPolicy: ERROR: <pre>'.  print_r($e,1) . '</pre>');
        }

        break;

    case 'pay':
        $result = callSetPolicyPayed($pars);
        break;

    case 'getpdfpolicy':

        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getPdfPolicy',
            'serialize' => $serialize,
            'params' => [
                'policyid' => $policyId,
            ]
        ];
        if (!empty($_GET['sum'])) $params['params']['payedsum'] = floatval($_GET['sum']);

        $result = performCall($url, $params);
        break;

    case 'getpdfbill':

        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getPdfBill',
            'params' => [
                'policyid' => $policyId,
            ]
        ];
        $result = performCall($url, $params);
        break;

    case 'getpdfagr':

        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getPdfAgreement',
            'params' => [
                'policyid' => $policyId,
            ]
        ];
        $result = performCall($url, $params);
        break;

    case 'getbyinn':

        $inn = isset($pars['inn']) ? trim($pars['inn']) : '000000000';
        $params = [
            'userToken' => $userToken,
            'module' => 'trmig',
            'execFunc' => 'getPolicyHolderData',
            'params' => [
                'inn' => $inn,
            ]
        ];
        $result = performCall($url, $params);
        break;

    case 'getprograms':
        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getAvailablePrograms',
            'params' => [
                'dummy' =>'zzz',
            ]
        ];
        $result = performCall($url, $params);
        break;

    case 'getprogramsubtypes':
        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getProgramSubtypes',
            'params' => [
                'programid' => (isset($pars['programid']) ? $pars['programid'] : ''),
            ]
        ];
        $result = performCall($url, $params);
        break;

    case 'getEventLog':
        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getEventLog',
            'serialize' => $serialize,
            # 'params' => [
                'page' => (isset($pars['evt_page']) ? $pars['evt_page'] : ''),
                'rows' => (isset($pars['evt_rows']) ? $pars['evt_rows'] : ''),
            # ]
        ];
        $result = performCall($url, $params);
        break;
    case 'sessions':
        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'getActiveSessions',
            'serialize' => $serialize,
        ];
        $result = performCall($url, $params);
        break;

    case 'gettranches':
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'getTranches',
            'serialize' => $serialize,
            'params' => ['subtype' => ($pars['subtype'] ?? 'TR3R2') ]
        ];
        $result = performCall($url, $params);
        break;

    case 'daystats':
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'periodStats',
            'period' => 20,
            'serialize' => $serialize,
        ];
        $result = performCall($url, $params);
        break;

    case 'getrates':
    $days2 = date('Y-m-d', strtotime('-4 days'));
        $params = [
            'userToken' => $userToken,
            'execFunc' => 'getCurrencyRates',
            'serialize' => $serialize,
            'params' => [
                'lastdates' => [ 'USD' => $days2, 'EUR'=> $days2 ],

            ]
        ];
        $result = performCall($url, $params);
        break;

    case 'decodePolicyQrCode': # расшифровка QR-кода на полисе
        # writeDebugInfo("pars: ", $pars);
        $params = [
            'userToken' => $userToken,
            'module' => $module,
            'execFunc' => 'decodePolicyQrCode',
            'serialize' => $serialize,
            'params' => [
                'hexcode' => ($pars['qrcode'] ?? ''),
                'format' => ($pars['qrformat'] ?? 'html')
            ]
        ];

        $result = performCall($url, $params);
        break;

    /*
    case 'cancel':

        try {
            $result = $soapCli->cancelPolicy($userToken, $userid, $module, $policyId);
            echo "cancelPolicy($policyId) result:<pre>".print_r($result,1) . '</pre>';
        }
        catch (SoapFault $e) {
            echo ('cancelPolicy: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            echo ("Last request:<pre>" . htmlentities( $soapCli->__getLastRequest()) . '</pre>');
        }
        break;

    case 'list':

        $clientid = empty($_GET['client']) ? '' : TEST_CLIENTID;
        try {
            $result = $soapCli->getPolicyList($userToken, $userid, $module, $clientid);
            echo 'getPolicyList() result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (SoapFault $e) {
            echo ('getPolicyList: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            showLastReqHtml();
        }
        break;

    case 'addscan':
        $fileName = 'полис.pdf';
        $realName = 'testfile.pdf';
        if (!is_file($realName)) exit("Нет тестового файла в папке: $realName");
        $fileBody = @base64_encode(file_get_contents($realName));
        try {
            $result = $soapCli->addPolicyScan($userToken, $userid, $module, $policyId, $fileName, $fileBody);
            echo 'addPolicyScan() result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (SoapFault $e) {
            echo ('addPolicyScan: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            showLastReqHtml();
        }
        break;

    case 'scanlist':

        try {
            $result = $soapCli->getPolicyScanList($userToken, $userid, $module, $policyId);
            echo 'getPolicyScanList() result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (SoapFault $e) {
            echo ('getPolicyScanList: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            showLastReqHtml();
        }
        break;

    case 'delscan':
        $fileId = isset($_GET['fileid']) ? $_GET['fileid'] : 0;
        try {
            $result = $soapCli->deletePolicyScan($userToken, $userid, $module, $policyId, $fileId);
            echo 'deletePolicyScan() result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (SoapFault $e) {
            echo ('deletePolicyScan: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            showLastReqHtml();
        }
        break;

    case 'getpolicy':

        try {
            $result = $soapCli->getPolicyData($userToken, $userid, $module, $policyId);
            echo 'getPolicyData() result:<pre>'.print_r($result,1) . '</pre>';
        }
        catch (SoapFault $e) {
            echo ('getPolicyData: SoapFault exception: <pre>'.  print_r($e,1) . '</pre>');
            showLastReqHtml();
        }
        break;
    */
    default:
        die("<b>$req</b> - Неподдерживаемая команда !");
}
# $verbose = 1;
if ($result === NULL) try {
    if (!empty($verbose)) $url .= "?verbose=1";
    $result = performCall($url, $params);
    # echo 'call calculate result:<pre>'.print_r($result,1) . '</pre>';
}
catch (Exception $e) {
    echo ('Exception: <pre>'.  print_r($e,1) . '</pre>');
}

if (!empty(TestParams::$rawRequest)) {
    $showBody = fineView(TestParams::$rawRequest);
    $cutToken = substr($userToken,0,4) . '...' . substr($userToken,-4);
    $showBody = str_replace($userToken,$cutToken, $showBody);
    echo "url $url, raw Request body: <pre style='overflow:auto'>$showBody</pre>";
}
if ($result) {
    if (is_array($result))
        $data = 'array <pre>'.print_r($result,1) . '</pre>';

    elseif (is_string($result)) {
        if (substr($result,0,1) =='{')
            $data = 'JSON string, decoded: <pre>'. print_r(json_decode($result,TRUE),1). '</pre>';
        else $data = $result;
    }
    else $data = "other returned: <pre>" . print_r($result,1). '</pre>';
    echo "$req call result: ". $data;
}
else echo 'empty servise response:  <pre>' . print_r($result,1). '</pre>';

/**
* вывожу форму для набора параметров тестирования
*
*/
function testForm() {
    $title = 'Тестировние JSON API ALFO';
    $html = <<< EOHTM
<!DOCTYPE html>
<html>
<head><title>$title</title>
<meta charset="UTF-8">
<link rel="stylesheet" href="../js/ui-themes/redmond/jquery-ui-1.10.4.custom.css" type="text/css" />
<link rel="stylesheet" href="../css/styles.css" type="text/css" />
<link rel="stylesheet" href="../css/styles-zetta.css" type="text/css" />

<script type="text/javascript" src="../js/jquery-3.6.0.min.js"></script>
<script type="text/javascript" src="../js/jquery-migrate-3.0.0.js"></script>
<script type="text/javascript" src="../js/jquery-ui.min.js"></script>
<script type="text/javascript" src="../js/i18n/jquery.ui.datepicker-ru.js"></script>
<script type="text/javascript" src="../js/asjs.js"></script>
<script type="text/javascript" src="../js/jquery.cookie.js"></script>

<script type="text/javascript" >
tst = {
  runForm: function() {
    $.post('./test.php', $("#fm_apitests").serialize(), function(data){
      $("#results").html(data);
    });
  },
  chgFunction: function() {
    var func = $("#req").val();
    if(func === "finddmspolicy") $("tr.dmspolicy").show();
    else $("tr.dmspolicy").hide();
    if(func === "finddmsbso") $("tr.dmsbso").show();
    else $("tr.dmsbso").hide();
    if(func === "getbyinn") $("tr.innrequest").show();
    else $("tr.innrequest").hide();

    if(func === "getEventLog") $("tr.eventlog").show();
    else $("tr.eventlog").hide();

    if(func === "decodePolicyQrCode") $("tr.qrcode").show();
    else $("tr.qrcode").hide();

    if(func === "gettranches") $("tr.gettranches").show();
    else $("tr.gettranches").hide();

    if(func === "save" || func=='getpdfpolicy' || func=='getpdfbill' || func=='getpdfagr' || func=='pay')
        $("tr.policyid").show();
    else $("tr.policyid").hide();

  }
};
</script>

</head>
<body style="padding:0.5rem;">
<h4 class="ct">$title</h4>

<center>
<form id="fm_apitests" onsubmit="return false">
<div class="bordered p-2">
 <table>
 <tr>
   <td class="p-5">модуль:<br><select name="module" id="module" class="ibox w200">
   <option value="">---</option>
   <option value="boxprod">Коробочные</option>
   <option value="trmig">Мигранты (trmig)</option>
   <option value="nsj">НСЖ (nsj)</option>
   <option value="lifeag">агентские прод.</option>
   <option value="irisky">Рисковые</option>
   <option value="ndc">Здоровая Жизнь/Global Life (ndc)</option>
   <option value="invins">ИСЖ(новое)</option>
   <option value="oncob">Онко-Барьер</option>
   <option value="pochvo">Поч.Возраст</option>
   </select>
   </td>
   <td class="p-5">операция:<br><select name="req" id="req" class="iboxm w300" onchange="tst.chgFunction()">
   <option value="">Выбрать!</option>
   <option value="countries">GetCountries (список стран)</option>
   <option value="finddmspolicy">findDmsPolicy (запрос по полису ДМС из Диасофт)</option>
   <option value="finddmsbso">findDmsBso (запрос о бланке БСО</option>
   <option value="getprograms">Список доступных программ</option>
   <option value="getprogramsubtypes">Список доступных суб-типов в программе</option>
   <option value="calc">calc - Выполнить расчет</option>
   <option value="save">save - Создать/Сохранить полис</option>
   <option value="pay">Отметка об оплате</option>
   <option value="getpdfpolicy">Получить PDF полиса</option>
   <option value="getpdfbill">Получить PDF счета на оплату</option>
   <option value="getpdfagr">Получить PDF договора</option>
   <option value="getbyinn">Получить данные ЮЛ по ИНН</option>
   <option value="decodePolicyQrCode">Раскодировка QR-кода с полиса</option>
   <option value="getEventLog">Журнал действий</option>
   <option value="sessions">Активные сессии пользователей</option>
   <option value="gettranches">Загрузка траншей</option>
   <option value="daystats">Статистика за день</option>
   <option value="getrates">Синхронизация курсов валют</option>
   <option value="methodlist">Получить список всех методов</option>
   </select>
   </td>
   <td class="p-5">номер/ИД программы<br><input type="text" class="ibox w100" name="programid" id="programid">
   </td>
   <td class="p-5">номер/ИД подпрограммы<br><input type="text" class="ibox w100" name="subtypeid" id="subtypeid">
   </td>
   </tr>
   <tr class="dmspolicy" style="display:none">
     <td class="p-5">Серия<br><input type="text" name="dms_ser" class="ibox w100" ></td>
     <td class="p-5">и Номер полиса<br><input type="text" name="dms_no" class="ibox w200"></td>
   </tr>
   <tr class="dmsbso" style="display:none">
     <td class="p-5">Номер БСО<br><input type="text" name="dms_bsonum" class="ibox w200"></td>
   </tr>
   <tr class="policyid" style="display:none">
     <td class="p-5">Номер полиса(если сохранить)<br><input type="text" id="policyid" name="policyid" class="ibox w200"></td>
   </tr>
   <tr class="innrequest" style="display:none">
     <td class="p-5">Номер ИНН для поиска<br><input type="text" id="inn" name="inn" class="ibox w200"></td>
   </tr>
   <tr class="eventlog" style="display:none">
     <td class="p-5">Номер страницы<br><input type="text" id="evt_page" name="evt_page" class="ibox w80" value="0"></td>
     <td class="p-5">Кол-во записей на странице<br><input type="number" id="evt_rows" name="evt_rows" class="ibox w80" value="20"></td>
   </tr>
   <tr class="qrcode" style="display:none">
     <td  class="p-5" colspan="2">Строка из QR-кода<br><input type="text" id="qrcode" name="qrcode" class="ibox w100prc" ></td>
     <td class="p-5">Формат<br><select id="qrformat" name="qrformat" class="ibox w100"><option>html</option><option>json</option><option>array</option></select></td>
   </tr>
   <tr class="gettranches" style="display:none">
     <td  class="p-5" colspan="2">Кодировка<br><input type="text" id="subtype" name="subtype" class="ibox w100" ></td>
   </tr>

   <tr>
     <td class="p-5">Сериализовать data<br>
       <select name="serialize" id="serialize" class="ibox w200">
       <option value="0">Нет</option>
       <option value="1">Простая сериализация</option>
       <option value="2">Сериализация с именами полей</option>
       </select>
     </td>
     <td class="p-5">Запрос на сервер<br>
       <select name="ask_server" id="ask_server" class="ibox w200">
       <option value="local">Локально</option>
       <option value="test1">Тест-сервер 1 (PHP7)</option>
       <option value="test2">Тест-сервер 2 (PHP8)</option>
       <option value="prod">Продуктовый</option>
       </select>
     </td>
   </tr>
 </table>
 <div class="area-buttons">
   <button class="btn btn-primary" onclick="tst.runForm()">Выполнить запрос</button>
 </div>
</div>
</form>
<pre id="results" class="bordered" style="min-height:100px; max-height:400px; overflow:auto;">results...
</pre>

<a href="../">back to FO</a><br>
</center>

</body>
</html>
EOHTM;
    exit($html);

}
# готовлю набор параметров для расчета или создания нового полиса
function jsvcPreparePolicyParams($module, $programid='', $subtypeid='') {
    global $randomizeNames;
    $paramFile = __DIR__ . "/calcdata-$module" . ($programid!='' ? "-$programid" : '')
      . ($subtypeid!='' ? "-$subtypeid" : '') . ".php";

    if(is_file($paramFile)) {
        $calcParams = include($paramFile);
        # writeDebugInfo("юзаю файл параметров: $paramFile");
        $calcParams['datefrom'] = date('d.m.Y',strtotime('+5 days'));
        if($randomizeNames && stream_resolve_include_path("class.randomdata.lang-ru.php")) {
            $sex = strtolower($calcParams['insrsex'] ?? 'm');
            include_once("class.randomdata.php");
            include_once("class.randomdata.lang-ru.php");
            $calcParams['insrfam'] = \RandomData::getLastName($sex,'',0.25);
            $calcParams['insrimia'] = \RandomData::getFirstName($sex);
            $calcParams['insrotch'] = \RandomData::getMiddleName($sex);
        }
    }
    else $calcParams = [
            'datefrom' => date('Y-m-d', strtotime('+10 days')),
            'datetill' => date('Y-m-d', strtotime('+2 years 9 days')),
            'vakcina' => '1', # набор расчета для мигрантов
            'insuredlist' => [
               [ 'birth'=>'02.05.1988', 'sex' => 'M', 'fullname' => 'Олух Олег Бывалович', 'fullname_lat' => 'Oluh', 'rez_country' => 'Молдова',
                 'sex' => 'M',
               ]
            ]
    ];
    return $calcParams;
}

function callSavePolicy($params) {

    global $url, $userToken, $policyId, $userid, $promo, $myemail, $module, $rass, $birth;
    $make_insured = 0; // !TestParams::$fromClient; # делать отд.застрахованного ? для тестов "от клиента" - НЕТ
    if (isset($params['module'])) $module = $params['module'];

    if ($module === 'invonline') $make_insured = 0; # стр-ль и застр-ый - всегда одно лицо
    $fixname = !empty($params['fixname']); # заполняю ФИО "одним и тем же"
    $policyid = isset($params['policyid']) ? $params['policyid'] : 0;

    if ($fixname) $birth = TestParams::FIX_BIRTH;
    elseif (empty($birth)) $birth = ('01.01.' . rand(1988, 1998));

    $country = isset($params['country'])? intval($params['country']) : 114;
    if (!$country) $country = 114;

    switch($module) {
        case 'planb': # устарело!
            $otParams = new stdClass();
            $otParams->promocode = $promo;
            if (!TestParams::$fromClient)
                $otParams->income_source_work = 1;
            if ($rass!=='') $otParams->rassrochka = $rass;
            if (!empty($params['premium'])) $otParams->premium = $params['premium'];
            if (!empty($params['sa'])) $otParams->plc_sa = $params['sa'];

            break;

        case 'invonline' : # устарело, не юзать!

            $otParams = new stdClass();
            $otParams->term = (empty($params['term']) ? 5 : intval($params['term'])); // 3 или 5 лет
            $make_insured = FALSE;
            if (!empty($params['sa'])) $otParams->plc_sa = $params['sa'];
            elseif (!empty($params['premium'])) $otParams->premium = $params['premium'];
            else $otParams->plc_sa = 1000000;

            if (!empty($params['warr'])) $otParams->warranty = $params['warr'];  # гарантия
            if (!empty($params['ba'])) $otParams->baseactive = $params['ba']; # Базовый актив
            # if (!TestParams::$fromClient)
            #    $otParams->income_source_work = 1;
            break;

        case 'trmig':
            # exit('TRMIG TODO params:<pre>'. print_r($params,1). '</pre>');
            $insuredLast = isset($params['last']) ? $params['last'] : RandomNames::getLastName();
            $insuredFirst = isset($params['first']) ? $params['first'] : RandomNames::getLastName();
            $insuredMid = isset($params['mid']) ? $params['mid'] : RandomNames::getMiddleName();
            $birth = isset($params['birth']) ? $params['birth'] : '01.02.1980';

            $params = [
                'userToken' => $userToken,
                'module' => 'trmig',
                'execFunc' => 'saveAgreement',
                'params' => [
                    'datefrom' => date('Y-m-d', strtotime('+10 days')),
                    'datetill' => date('Y-m-d', strtotime('+2 years 9 days')),
                    'vakcina' => '1',
                    'old_insured' => '0',
                    'ph_type' => '2',
                    'ph_ulname' => 'ООО "Трава у Дома" limited',
                    'ph_inn' => '7700432545',
                    'ph_ogrn' => '200300044',
                    'ph_kpp' => '200КПП0111',
                    'ph_bankname' => 'Банк Безнадежные Кредиты (ПАО)',
                    'ph_bankbik' => '445077911',
                    'ph_bankrs' => '20005000400030001033',
                    'ph_bankks' => '90005000400030001001',
                    'ph_docser' => '2736',
                    'ph_docno' => '1622569',
                    'ph_docdate' => '22.06.2017',
                    'ph_docissued' => 'Росреестр РФ, отделение по Москве 37',
                    'ph_phone' => '910-2993344',
                    'ph_phone2' => '495-3333333',
                    'ph_email' => 'myFirma@superPuper.com',
                    'ph_addressreg' => '113044 Москва Адрес регистырации Юр-Лица Страхователя',
                    'ph_sameaddr' => '1',
                    'ph_headduty' => 'Самого главного Дворника',
                    'ph_headname' => 'Укуркина Андрея Михайловича',
                    'ph_headbase' => 'Доверенности 34 от 12.03.2018г.',
                    'contact_name' => RandomNames::getFullName(),
                    'contact_birth' => '23.08.' . rand(1960,1990),
                    'contact_sex' => 'M',
                    'contact_address' => '115011 Москва, Адрес контактного лица',
                    'contact_phone' => '916-711-2233',
                    'contact_email' => 'на деревню Дедушке',
                    'ikp_agent' => '250005-09', # Ж-250005-09 Шевелев Василий Владимирович
                    # 'ikp_agent' => '000082-19', # Ж-000082-19 Макарова Татьяна Леонидовна
                    'ikp_curator' => '000241-13', # 000356-03 Васютина (есть вин-логин и ИД), 000977-03 - ЛЯШКО ЛЮДМИЛА ВАЛЕНТИНОВНА
                    'insuredlist' => [
                       [ 'birth' => $birth,
                         'sex' => 'M',
                         'lastname' => $insuredLast,
                         'firstname' => $insuredFirst,
                         'midname' => $insuredMid,
                         'fullname_lat' => translit("$insuredLast $insuredFirst $insuredMid"),
                         'inn'=>'58867712',
                         'rez_country' => 'Молдова',
                         'docser' => rand(1000,9999),'docno' => rand(100000,999999),
                         'docdate' => '12.06.2014',
                         'docissued' => 'Молдавский УВД в Кишиневе',
                         'migcard_ser' => rand(1000,9999),'migcard_no' => rand(100000,999999),
                         'docdate' => '12.06.2014',
                         'docfrom' => '01.01.2020',
                         'doctill' => '12.10.2021',
                         'phone' => '922-3334455',
                         'email' => 'proverka100@yandex.ru',
                         'work_duty' => 'строитель',
                         'work_company' => 'Строй-монтаж 33',
                         'address' => '112002 Москва, ул.Шухова, дом 5 кв.12',
                       ]
                    ]
                ],
            ];

            if (!empty($policyid)) $params['params']['policyid'] = intval($policyid);
            break;
        default:
            exit("Unsupported module: $module");
    }
    return perFormCall($url, $params);

}

# вызов простановки об оплате
function callSetPolicyPayed($params) {
    global $url, $userToken, $policyId, $userid, $promo, $myemail, $module, $rass, $birth;
    if (isset($params['module'])) $module = $params['module'];
    $payDdoc = empty($params['doc']) ? ('PAY-'.rand(100000, 900000)) : trim($params['doc']);
    $online = isset($params['online']) ? $params['online'] : '';
    $policyid = isset($params['policyid']) ? $params['policyid'] : 0;
    $params = [
        'userToken' => $userToken,
        'module' => $module,
        'execFunc' => 'setAgreementPayed',
        'params' => [
            'policyid' => $policyid,
            'paydocno' => $payDdoc,
            'online' => $online,
        ]
    ];
    if (!empty($params['sum'])) $params['params']['payedsum'] = floatval($params['sum']);

    $result = performCall($url, $params);
    return $result;
}

function performCall($url, $data) {
    $ch = curl_init($url);
    TestParams::$rawRequest = json_encode($data,JSON_UNESCAPED_UNICODE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, TestParams::$rawRequest);
    $result = curl_exec($ch);
    curl_close($ch);

    $ret = json_decode( $result, TRUE );
    return $ret;
}
function fineView($strg) {
    $ret = strtr($strg, [ '","' => "\",\n  \"",']' => "\n]\n", "}" => "}\n" ]);
    return $ret;
}