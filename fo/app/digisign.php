<?php
/**
* @package ALFO
* @name app/digisign.php
* Модуль работы с сервисом цифровой подписи (lois) для PDF файлов
* @author Alexander Selifonov
* @version 1.09.002
* modified 2025-09-19
*/

class DigiSign {
    const VERSION = '1.09';
    # public static $service_url = 'https://hqmsksonic02.hq.corp.rosno.ru:8081/LoisCryptoPRO/PdfService'; # NEW 30.05.2019
    static $service_url = '';
    static $working = TRUE; # сервис активен, false - не работает!
    static $login = 'Admin';
    static $password = 'adminGuideh';
    private static $testing = FALSE; # я тестирую, не поднимать алярмы!
    # позиция и размеры прямоуголника под оттиск сертификата: (реальную страницу подставить перед выводом) (1mm = 2.844pt)
    static $signatureData = ['addStamp' => 1, 'page'=>1, 'x'=>25, 'y'=>25, 'width'=>260, 'height' => 150];
    private static $digiErrors = [ # найденные методом тыка ошибки к кодам сервиса
      '0x80090010' => 'Сертификат недействителен',
    ];
    static $soapCli = null;
    static $context = NULL;
    static $aliasKey = ''; # ИД дефолтного контейнера(сертификата) : test_maa, eosago2018
    private static $aliasFixed = FALSE; # TRUE при задании своего алиаса
    static $userAlias = ''; # ИД пользователького контейнера(сертификата)
    static $emulate = FALSE; # true|1 для эмуляции (без реальных вызовов)
    static $sessionid = '';
    static $traceSoap = FALSE;
    static $testFileName = 'test.pdf'; # Файл для теста процедуры подписи testSign()
    static $errorMessage = '';
    static $returnUnsigned = TRUE; # TRUE= если сервис не работает - вернуть неподписанный файл, FALSE = вернуть ошибку
    static $sendErrorsToAdmin = TRUE; # если ЭЦП колбасит, нашкрябать пахану маляву про шухер
    static $skipLoadUrl = FALSE; # TRUE когда передал свой адрес сервиса и не хочешь чтоб его перебили
    static $verbose = 0; # поставить в 1 чтобы посмотреть ответы от сервиса
    static $debug = 0;
    static $logErrors = 0;
    static $disable_cache = TRUE;
    static $curOperation = '';
    static $makeHeaders = 0; # пробую добавить в запрос заголовки (непонятные отказы на Firewall - bad http headers)
    static $logActions = 1; # логировать операции подписаний PDF: 1 - только ошибки, 2 - и успехи

    public static function getVersion() {
        return self::VERSION;
    }
    /**
    * В зависимости от настройки вернет TRUE (сервис работает) либо FALSE (сервис отключен)
    */
    public static function isServiceActive($module='') {
        self::_loadConfig($module);
        return self::$working;
    }
    private static function _loadConfig($module = '', $svcUrl = FALSE) {
        $cfgname = __DIR__ . '/../cfg/cfg-digisign.php';
        # writeDebugInfo("module: [$module], trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        if (is_file($cfgname)) {
            include_once($cfgname);
            if (!self::$skipLoadUrl) {
                if (!empty(DigiSignParams::$service_url)) self::$service_url = DigiSignParams::$service_url;
            }
            if (!empty(DigiSignParams::$login)) self::$login = DigiSignParams::$login;
            if (!empty(DigiSignParams::$password)) self::$password = DigiSignParams::$password;
            if (!empty(DigiSignParams::$aliasKey) && !self::$aliasFixed) self::$aliasKey = DigiSignParams::$aliasKey;
            if (isset(DigiSignParams::$emulate)) self::$emulate = DigiSignParams::$emulate;
            if (isset(DigiSignParams::$traceSoap)) self::$traceSoap = DigiSignParams::$traceSoap;
            if (isset(DigiSignParams::$working)) self::$working = DigiSignParams::$working;
        }
        # а теперь перешибаем значениями из настроек, если заполнены
        if ($url = appEnv::getConfigValue('digisign_url')) self::$service_url = $url;
        if ($login = appEnv::getConfigValue('digisign_login')) self::$login = $login;
        if ($passw = appEnv::getConfigValue('digisign_password')) self::$password = $passw;
        if (!self::$aliasFixed && $key = appEnv::getConfigValue('digisign_aliaskey')) self::$aliasKey = $key;

        if (!empty($module)) {
            if ($module === 'plgkpp') $module = 'nsj'; # общий ключ на все НСЖ - старые и новые!
            $key = appEnv::getConfigValue('digisign_aliaskey_'.$module);
            # writeDebugInfo("digisign_aliaskey_$module is [$key]");
            if(empty($key) && method_exists(AppEnv::$_plugins[$module], 'moduleType')) {
                $mdType = AppEnv::$_plugins[$module]->moduleType();
                # writeDebugInfo("moduleType returned: ", $mdType);
                if($mdType === \PM::MODULE_DMS) {
                    $key = appEnv::getConfigValue('digisign_aliaskey_dms');
                }
            }
            if (!empty($key)) self::$aliasKey = $key;
        }
        # writeDebugInfo("loaded digiSign config, aliasKey: ".self::$aliasKey);
    }
    public static function setAliasKey($newKey) {
        self::$aliasKey = $newKey;
        self::$aliasFixed = TRUE;
        # writeDebugInfo("set new aliasKey: $newKey");
    }
    public static function setCertificateAlias($alias) {
        self::$userAlias = $alias;
    }
    /**
    * Создание клиента и сразу логин к сервису, с получением session_id
    *
    * @param mixed $skipLogin - если true, логин не делает (потом сам делай!).
    * @return TRUE (подключение успешно, сессия сохранена) | FALSE (ошибки) - см. $errorMessage
    */
    public static function init($skipLogin = false, $module = '', $srvUrl = FALSE) {
        self::_loadConfig($module, $srvUrl);
        if(self::$debug) writeDebugInfo("init( skipLogin=[$skipLogin], module='$module', srvUrl=[$srvUrl] ) ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        self:$errorMessage = '';
        if (self::$emulate) {
            self::$errorMessage = 'Режим эмуляции!';
            return 'EMULATED';
        }
        if ( is_object(self::$soapCli) && !empty(self::$sessionid) ) return true;

        ini_set('default_socket_timeout', 10);

        if (self::$disable_cache) {
            ini_set('soap.wsdl_cache_enabled',0);
            ini_set('soap.wsdl_cache_ttl',0);
        }
        if (appEnv::isApiCall()) self::$verbose = false;
        # $myIp = (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'SYSTEM');

        /*
        $opts = array(
            'http'=>array(
                'user_agent' => 'ALFO PHPSoapClient',
            ),
            'ssl' => array(
               'verify_peer' => false,
               'verify_peer_name' => false,
               'allow_self_signed' => true
            ),
            // 'socket' => ['bindto' => "$myIp:0"],
        );
        $context = stream_context_create($opts);
        */

        $options = [
            # 'stream_context' => $context,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'user_agent' => 'ALFO PHPclient',
            "soap_version"=>SOAP_1_1,
            'trace' => self::$traceSoap,
            /*
            'ssl' => array(
               'verify_peer' => false,
               'verify_peer_name' => false,
               'allow_self_signed' => true
            ),
            */
        ];
        if(!empty($srvUrl))
            $runUrl = $srvUrl;
        else $runUrl = self::$service_url;
        $options['location'] = $runUrl;

        # self::$digisign_wsdl = __DIR__ . '/PdfService.xml'; # заменяю сохраненной копией
        try {

            $options['uri'] = 'http://signpdf.lois.ru/';

            if(self::$debug) writeDebugInfo("KT-001: options for create SOAP object: ", $options);

            # {upd/2021-02-10} - PHP7: происходит ошибка при подключении
            # SoapClient::__doRequest(): SSL operation failed with code 1. OpenSSL Error messages: error:1416F086:SSL routines:tls_process_server_certificate:certificate verify failed
            self::$context = stream_context_create([
                'ssl' => [
                    // set some SSL/TLS specific options
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            $options['stream_context'] = self::$context;
            self::$soapCli = new SoapClient(null, $options);
            if(self::$debug) WriteDebugInfo("KT-002: After new SoapClient(), ", self::$soapCli);
            if(self::$verbose) echo "created SoapClient object:<pre>".print_r(self::$soapCli,1).'</pre>';

            if ($skipLogin) return true;
            if (self::$makeHeaders) {
                $headerNs = new SoapHeader($options['uri'], 'requestid', md5(rand(100000,900000000)));
                $headArr = [$headerNs];
                self::$soapCli->__setSoapHeaders($headArr);
                if(self::$debug) WriteDebugInfo("optional headers created");
            }
            $login = new SoapParam(self::$login, 'login');
            $password = new SoapParam(self::$password, 'password');

            self::$curOperation = 'login';
            $loginResult = self::$soapCli->login($login, $password); # ($params);
            if (self::$verbose) echo 'login result:<pre>'.print_r($loginResult,1) . '</pre>';

            if (self::$traceSoap && self::$verbose) {
                # WriteDebugInfo("soap login response headers:", self::$soapCli->__getLastResponseHeaders() );
                echo 'login response headers:<pre>'.print_r(self::$soapCli->__getLastResponseHeaders(),1) . '</pre>';
                # WriteDebugInfo("soap login response:", self::$soapCli->__getLastResponse() );
                echo ("login response:<pre>" .print_r(self::$soapCli->__getLastResponse(),1). '</pre>' );
            }
            if (self::$verbose) echo 'login Result:<pre>'.print_r($loginResult,1) . '</pre>';
            if(self::$debug)  WriteDebugInfo("PdfService-login result: ", $loginResult);
            if (isset($loginResult->status) && $loginResult->status == 'true') {
                self::$sessionid = isset($loginResult->session_id) ? (string)$loginResult->session_id : '';
                if(self::$debug) writeDebugInfo("session_id after login: ", self::$sessionid);
                self::$errorMessage = '';
                AppAlerts::resetAlert('DIGISIGN', 'Авторизация на сервисе ЭЦП восстановлена');
                return TRUE;
            }
            else {
                self::$errorMessage = $loginResult->message;
                if(!self::$testing) {
                    AppAlerts::raiseAlert('DIGISIGN', 'Авторизация в сервисе ЭЦП не сработала, ошибка: '.$loginResult->message);
                }
                return FALSE;
            }
        }
        catch (Exception $e) {

            if (self::$verbose) echo 'Exception raised:<pre>'.print_r($e,1) . '</pre>';
            if (self::$debug) WriteDebugInfo('Digisign (login) Exception raised:', $e);

            self::$errorMessage = $e->getMessage();
            if (is_object(self::$soapCli) && self::$verbose) {
                echo 'response headers:<pre>'.print_r(self::$soapCli->__getLastResponseHeaders(),1) . '</pre>';
                echo ("soap login response:<pre>" .print_r(self::$soapCli->__getLastResponse(),1). '</pre>' );
            }
            else {
            # WriteDebugInfo("PdfService-login failed: ", self::$errorMessage);
                if (self::$verbose) echo 'Login call Fault:<pre>'.print_r(self::$errorMessage,1) . '</pre>';
            }
            if (self::$sendErrorsToAdmin) {
                AppAlerts::raiseAlert('DIGISIGN',
                  $_SERVER['HTTP_HOST'] . " - Нет связи с сервисом ЭЦП ($options[location]),<br>"
                  . self::$errorMessage
                );
                /*
                $failText = "Внимание, на сервере <b>$_SERVER[HTTP_HOST]</b> произошел сбой при вызове сервиса ЭЦП по адресу $options[location]\n"
                   . "Необходимо проверить наличие доступа к сервису и его функциональность."
                   . "\nОперация :".self::$curOperation
                   . "\nОшибка : " . self::$errorMessage;
                appEnv::sendSystemNotification($_SERVER['HTTP_HOST'] . ' - DIGISIGN FAIL', $failText);
                */
            }
            return FALSE;
        }

        return (!empty(self::$sessionid));
    }

    public static function close() {
        if (self::$emulate || self::$soapCli == null) return true;
        $param = new SoapParam(self::$sessionid, 'session_id');
        $result = self::$soapCli->logout($param);
        if (self::$verbose) echo "logout result for session:<pre>". print_r($result,1).'</pre>';
        self::$sessionid = '';
        self::$soapCli = null;
    }

    /**
    * Подписывает переданный файл
    * @param string $params array: 'filepath' = полный путь к файлу, либо 'body' => содержимое PDF файла
    * @since 0.8:
    * @param $signature FALSE либо номер страницы, на которой впечатать оттиск с данными о сертификате ЭЦП
    *
    * @return array $result : 'success'=>1|0, 'errorMessage' - текст ошибки, 'body' => содержимое подписанного файла
    */
    public static function signFile($params = false, $signature = FALSE) {
        $err = '';
        if(self::$debug > 2) writeDebugInfo("signFile params: ", $params);
        $svcUrl = $params['serviceUrl'] ?? FALSE;
        if (isset($params['body'])) $body = $params['body'];
        elseif(isset($params['filepath'])) $body = @file_get_contents($params['filepath']);

        if (empty($body)) {
            $ret = ['success' => 0, 'errorMessage'=>'Не передано корректное имя (filepath) или содержимое исходного (body) PDF файла'];
        }
        else {
            if (self::$emulate || !self::$working) {
                $ret = [
                  'success' => 1,
                  'body' => $body
                ];
            }
            else {

                if (!empty($params['serviceUrl'])) {
                    self::$service_url = $params['serviceUrl'];
                }

                $result = self::init(FALSE,'',$svcUrl);
                if (!$result) {
                    # инициализация не прокатила, подписание отменяется!
                    if(self::$debug) writeDebugInfo("digiSign init() - ошибка!", self::$errorMessage);
                    self::$errorMessage = 'Сервис ЭЦП не работает, либо неверные настроечные параметры';
                    if (self::$returnUnsigned) {
                        $ret = [
                          'success' => 1,
                          'body' => $body,
                          'errorMessage'=>'Сервис ЭЦП не работает, возвращен неподписанный файл'
                        ];
                    }
                    else {
                        $ret = ['success' => 0, 'errorMessage'=>self::$errorMessage];
                        PlcUtils::setStateFailed('digisign', self::$errorMessage);
                    }
                    return $ret;
                }
                $curModule = FALSE;

                if (!empty(self::$userAlias)) {# могли установить алиас через setCertificateAlias()
                   self::$aliasKey = self::$userAlias;
                   if(self::$debug) WriteDebugInfo("user defined alias used:", self::$aliasKey);
                }
                else {
                    # {upd/2025-09-10} если в настройки штампа задан свой ЭЦП алиас, буду использовать его!
                    $curAlias = AppEnv::getUsrAppState('digisign_use_alias'); # если алиас указан в настройки подписанта - беру его!
                    if(!empty($curAlias)) {
                        self::$aliasKey = $curAlias;
                        if (self::$debug) writeDebugInfo("digisign alias взят из digisign_use_alias: $curAlias");
                    }
                    else {
                        $curModule = appEnv::getUsrAppState('sign_module');
                        if (!empty($curModule)) {
                            if ($alias = appEnv::getConfigValue($curModule . '_digisign_alias')) {
                                self::$aliasKey = $alias;
                                if (self::$debug) WriteDebugInfo("signFile/AliasKey for module $curModule used: ", $alias);
                            }
                        }
                    }
                }
                # переданный в params перезатирает всё:
                if(!empty($params['aliasKey'])) self::$aliasKey = $params['aliasKey'];

                # беру базовый алиас, если все прочие не настроены:

                if(empty(self::$aliasKey)) self::$aliasKey = AppEnv::getConfigValue('digisign_aliaskey');
                if (self::$debug) WriteDebugInfo("$curModule: finally alias used: ", self::$aliasKey);

                $body64 = base64_encode($body);

                try {

                    $session_id = new SoapParam(self::$sessionid, 'session_id');
                    if(!empty($params['aliasKey'])) self::$aliasKey = $params['aliasKey'];
                    # exit ("aliasKey: " . self::$aliasKey . '<pre>'.print_r($params,1) .'</pre>');
                    # exit("aliasKey: " . self::$aliasKey);
                    $aliasKey = new SoapParam(self::$aliasKey, 'aliasKey');
                    if (self::$debug) WriteDebugInfo("SignPDF: session id = ",self::$sessionid, " aliasKey: ", self::$aliasKey);
                    $body = new SoapParam($body64, 'pdf_file');
                    self::$curOperation = 'signPdf';

                    if (empty($signature) && !empty($params['signature']))
                        $signature = intval($params['signature']);

                    if ($signature) {
                        /* # печатаю оттиск ЭЦП
                        <page_rectangle>
                            <addStamp>true</addStamp>
                            <page>5</page>
                            <x>25</x>
                            <y>25</y>
                            <width>200</width>
                            <height>120</height>
                        </page_rectangle>
                        */
                        $ottVar = self::$signatureData;
                        if(is_array($signature)) {
                            $ottVar = array_merge($ottVar, $signature);
                        }
                        else {
                            $ottVar['page'] = intval($signature);
                        }
                        # готовлю Soap параметр объектного типа:
                        $ottParam = new SoapParam(new SoapVar($ottVar, SOAP_ENC_OBJECT), 'page_rectangle');
                        if (self::$verbose) echo 'Параметры рамки с сертификатом:<pre>' . print_r($ottVar,1). '</pre>';
                        # writeDebugInfo("KT-033 vefore call signPDF");
                        $result = self::$soapCli->signPdf($session_id, $aliasKey, $body, $ottParam);
                    }
                    else {
                        $result = self::$soapCli->signPdf($session_id, $aliasKey, $body);
                    }
                    if(is_object($result)) $result->testvar = FALSE;

                    if (self::$verbose) echo "signPdf result: <pre>" . print_r($result,1).'</pre>';
                    if (self::$debug) {
                        WriteDebugInfo("signPdf() executed");
                        WriteDebugInfo("result->signed_file length: " . (empty($result->signed_file)? 'EMPTY!' : strlen($result->signed_file)));
                        writeDebugInfo("signPdf result: ", $result);
                    }
                    if (empty($result->signed_file)) {
                        # сбой при подписывании определенным сертификатом!
                        if (self::$debug) WriteDebugInfo("Failed sign: EMPTY signed file");
                        if(!empty($result->message)) {
                            $signError = self::parseError($result->message);
                        }
                        else $signError = 'Undefined';

                        if(self::$debug) writeDebugInfo("parsed Error from message: [$signError] aliaskey: ", $aliasKey->param_data);
                        $errTxt = 'Ошибка при подписании PDF файла ЭЦП / '.$aliasKey->param_data;

                        if (!empty($result->message)) $errTxt .= '; Причина: '.$signError;

                        if(!self::$testing) {
                            if(self::$debug) writeDebugInfo("sign error, AppEnv::_p: ", AppEnv::$_p);
                            AppAlerts::raiseAlert('DIGISIGN',"ЭПП-сбой подписания, error: $errTxt");
                            if (self::$logActions) {
                                $module = AppEnv::$_p['plg'] ?? AppEnv::$_p['module'] ?? '';
                                $plcid = AppEnv::$_p['id'] ?? 0;
                                $logPref = '';
                                if($module) {
                                    $bkEnd = AppEnv::getPluginBackend($module);
                                    if(is_object($bkEnd) && method_exists($bkEnd, 'getLogPref'))
                                        $logPref = $bkEnd->getLogPref();
                                }
                                appEnv::logEvent("{$logPref}DIGISIGN.ERROR","ЭПП-сбой подписания: $errTxt",0,$plcid,FALSE,$module);
                            }

                            PlcUtils::setStateFailed('digisign', $errTxt);
                            appEnv::setUsrAppState('digisign_result', ['result'=>'ERROR', 'message'=>$errTxt]);
                        }
                        if(appEnv::isApiCall()) {
                            # writeDebugInfo("failed digisign id API call");
                            return ['result'=>'ERROR', 'message'=>$errTxt];
                        }
                        else {
                            appEnv::echoError($errTxt);
                            exit; //['success'=>0, 'errorMessage'=>$errTxt];
                        }
                    }
                    else { # все ОК, сбросить флажок тревоги если был поднят
                        AppAlerts::resetAlert("DIGISIGN");
                    }
                }
                catch (Exception $e) {
                    self::$errorMessage = $e->getMessage();
                    $fname = isset($params['filepath']) ? $params['filepath'] : '';
                    if (self::$debug || self::$logErrors)
                        WriteDebugInfo("$fname signPdf raised Exception, reason: ", self::$errorMessage);
                    if(self::$sendErrorsToAdmin) {
                        $failText = "Внимание, на сервере <b>$_SERVER[HTTP_HOST]</b> произошел сбой при вызове сервиса ЭЦП.\n"
                           . "Необходимо проверить наличие доступа к сервису и его функциональность."
                           . "\nОперация :".self::$curOperation
                           . "\nОшибка : " . self::$errorMessage;
                        appEnv::sendSystemNotification($_SERVER['HTTP_HOST'] . ' - DIGISIGN FAIL', $failText);
                    }
                    $ret = ['success' => 0, 'errorMessage'=>'Ошибка при вызове сервиса: '.self::$errorMessage ];
                    PlcUtils::setStateFailed('digisign', self::$errorMessage);
                    if (self::$logActions) {
                        appEnv::logEvent('DIGISIGN.ERROR','Ошибка работы сервиса ЭЦП:'.self::$errorMessage);
                    }

                    if (!is_object($result)) $result = new stdClass();
                    $result->signed_file = '';
                    $result->message = "Ошибка подписания файла: ". self::$errorMessage;
                    appEnv::setUsrAppState('digisign_result', ['result'=>'ERROR', 'message'=>self::$errorMessage]);
                    # writeDebugInfo("fail result: ", $result);# return $ret;
                }

                if (!empty($result->signed_file)) {
                    appEnv::setUsrAppState('digisign_result', ['result'=>'OK']);
                    $ret = [
                      'success' => 1,
                      'body' => base64_decode($result->signed_file)
                    ];
                    if (self::$logActions >= 2) {
                        appEnv::logEvent('DIGISIGN.SUCCESS','PDF файл подписан ЭЦП / '.self::$aliasKey);
                    }

                }
                else {
                    $ret = [
                      'success' => 0,
                      'errorMessage' => $result->message
                    ];

                    if (self::$logActions) {
                        appEnv::logEvent('DIGISIGN.ERROR','Ошибка подписания PDF файла / '.self::$aliasKey
                          . ' ' .$result->message
                        );
                    }
                }
            }
        }
        self::close();
        return $ret;
    }
    /**
    * Получение данных о сертификате
    * @param mixed $params массив ( 'aliasKey'=> "ailas_name") или строка с алиасом
    */
    public static function getAliasInfo($params = false) {
        $err = '';
        if(self::$debug) writeDebugInfo("getAliasInfo params: ", $params);
        if(empty($params)) {
            $params = AppEnv::$_p;
            $alias = $params['aliasKey'] ?? $params['alias'] ?? '';
        }
        elseif(is_string($params)) $alias = $params;

        if (empty($alias)) {
            $ret = ['success' => 0, 'errorMessage'=>'Не передан алиас (aliasKey, alias)'];
            return $ret;
        }

        if (self::$emulate || !self::$working) {
            return [
              'success' => 1,
              'dateExpire' =>  date('Y-m-d', strtotime("+1 years"))
            ];
        }

        if (!empty($params['serviceUrl'])) {
            self::$service_url = $params['serviceUrl'];
        }
        else self::$service_url = AppEnv::getConfigValue('digisign_url');

        $svcUrl = self::$service_url;
        $result = self::init(FALSE,'',$svcUrl);
        if(self::$debug) writeDebugInfo("init ($svcUrl) done, result: ", $result);
        if (!$result) {
            # инициализация не прокатила, операция отменяется!
            if(self::$debug) writeDebugInfo("digiSign init($svcUrl) - ошибка!", self::$errorMessage);
            self::$errorMessage = 'Сервис ЭЦП не работает, либо неверные настроечные параметры';
            if (self::$returnUnsigned) {
                $ret = [
                  'result' => 'ERROR',
                  'message'=>'Сервис ЭЦП не работает: '
                ];
            }
            else {
                $ret = ['success' => 0, 'errorMessage'=>self::$errorMessage];
                PlcUtils::setStateFailed('digisign', self::$errorMessage);
            }
            return $ret;
        }

        try {

            $session_id = new SoapParam(self::$sessionid, 'session_id');
            self::$aliasKey = $alias;
            # exit ("aliasKey: " . self::$aliasKey . '<pre>'.print_r($params,1) .'</pre>');
            # exit("aliasKey: " . self::$aliasKey);
            $aliasKey = new SoapParam($alias, 'aliasKey');
            $session_id = new SoapParam(self::$sessionid, 'session_id');

            self::$curOperation = $func = 'getAliasInfo';
            # $result = self::$soapCli->$func($session_id, $aliasKey);
            $result = self::$soapCli->__call($func, [$session_id, $aliasKey]);

            if (self::$debug) {
                WriteDebugInfo("$func() session id = ",self::$sessionid, " aliasKey: ", self::$aliasKey);
                writeDebugInfo("$func() result: ", $result);
            }
            if (empty($result->status) || $result->status!=='true') {
                $errTxt = "Ошибка при получении данных:<br>" . $result->message;
                $ret = ['result'=>'ERROR',
                  'message' => $errTxt
                ];
            }
            else {
                $ret = array_merge(['result'=>'OK'],  get_object_vars($result));
                /*
                $ret = ['result'=>'OK', 'dateExpire' => substr($result->dateExpire,0,10),
                  'signerName' => (string) ($result->signerName ?? ''),
                ];
                if(isset($result->MCHDGuid)) $ret['MCHDGuid'] = (string) $result->MCHDGuid;
                if(isset($result->inn)) $ret['inn'] = (string) $result->inn;
                */
                $ret['minDateExpire'] = $ret['dateExpire'] ?? '';
                if(isset($ret['dateExpireMCHD']) && intval($ret['dateExpireMCHD']))
                    $ret['minDateExpire'] = min($ret['minDateExpire'],$ret['dateExpireMCHD']);

            }
        }
        catch (Exception $e) {
            self::$errorMessage = $e->getMessage();
            if (self::$debug || self::$logErrors)
                WriteDebugInfo("getAliasInfo raised Exception, reason: ", self::$errorMessage);
            if(self::$sendErrorsToAdmin) {
                $failText = "Внимание, на сервере <b>$_SERVER[HTTP_HOST]</b> произошел сбой при вызове сервиса ЭЦП."
                   . "\nОперация :".self::$curOperation
                   . "\nОшибка : " . self::$errorMessage;
                appEnv::sendSystemNotification($_SERVER['HTTP_HOST'] . ' - DIGISIGN FAIL', $failText);
            }
            $ret = ['result' => 'ERROR', 'message'=>'Ошибка при вызове сервиса: '.self::$errorMessage ];
            PlcUtils::setStateFailed('digisign', self::$errorMessage);
            if (self::$logActions) {
                appEnv::logEvent('DIGISIGN.ERROR','Ошибка работы сервиса ЭЦП:'.self::$errorMessage);
            }
        }

        self::close();
        return $ret;
    }

    /**
    * Получение общих данных о сертификате (проверка) (ждет параметр "bytes")
    * ERR: certInfo,allCert - Не указан параметр bytes
    */
    public static function call($funcName, $arParams=[]) {
        $err = '';

        if (self::$emulate || !self::$working) {
            return [
              'result' => 'OK',
              'message' =>  "Режим эмуляции!"
            ];
        }

        self::$service_url = AppEnv::getConfigValue('digisign_url');

        $svcUrl = self::$service_url;
        $result = self::init(FALSE,'',$svcUrl);
        if (!$result) {
            # инициализация не прокатила, операция отменяется!
            if(self::$debug) writeDebugInfo("digiSign init($svcUrl) - ошибка!", self::$errorMessage);
            self::$errorMessage = 'Сервис ЭЦП не работает, либо неверные настроечные параметры';
            if (self::$returnUnsigned) {
                $ret = [
                  'result' => 'ERROR',
                  'message'=>'Сервис ЭЦП не работает: '
                ];
            }
            else {
                $ret = ['result' => 'ERROR', 'error'=>self::$errorMessage];
                PlcUtils::setStateFailed('digisign', self::$errorMessage);
            }
            return $ret;
        }

        try {
            $soapParams = [
                new SoapParam(self::$sessionid, 'session_id')
            ];
            if(is_array($arParams) && count($arParams)) foreach($arParams as $key=>$val) {
                if(is_numeric($key)) $key = "param_".$key; # надо все-таки передавать имена Soap полей!
                $soapParams[] = new SoapParam($val, $key);
            }

            self::$curOperation = $funcName;
            $result = self::$soapCli->__call($funcName, $soapParams);

            if (self::$debug) {
                WriteDebugInfo("calling $funcName() params: ",$arParams);
                writeDebugInfo("raw result: ", $result);
            }
            if (empty($result->status) || $result->status!=='true') {
                $errTxt = $result->message;
                $ret = ['result'=>'ERROR',
                  'message' => $errTxt
                ];
            }
            else {
                $ret = array_merge(['result'=>'OK'], get_object_vars($result));
            }
        }
        catch (Exception $e) {
            self::$errorMessage = $e->getMessage();
            if (self::$debug || self::$logErrors)
                WriteDebugInfo("certInfo raised Exception, reason: ", self::$errorMessage);
            if(self::$sendErrorsToAdmin) {
                $failText = "Внимание, на сервере <b>$_SERVER[HTTP_HOST]</b> произошел сбой при вызове сервиса ЭЦП."
                   . "\nОперация :".self::$curOperation
                   . "\nОшибка : " . self::$errorMessage;
                # appEnv::sendSystemNotification($_SERVER['HTTP_HOST'] . ' - DIGISIGN FAIL', $failText);
            }
            $ret = ['result' => 'ERROR', 'message'=>'Ошибка при вызове сервиса: '.self::$errorMessage ];
            /*
            PlcUtils::setStateFailed('digisign', self::$errorMessage);
            if (self::$logActions) {
                appEnv::logEvent('DIGISIGN.ERROR','Ошибка работы сервиса ЭЦП:'.self::$errorMessage);
            }
            */
        }

        self::close();
        return $ret;
    }

    /**
    * Залогинитьсяч и получить список типов и функций
    *
    */
    public static function viewFunctions() {
        $result = self::init(true);
        if (is_object(self::$soapCli)) {
            $types = self::$soapCli->__getTypes();
            $funcs = self::$soapCli->__getFunctions();
            return "Types:<pre>".print_r($types,1) . "<hr>Functions:<br>".print_r($funcs,1).'</pre>';
        }
        else die ('Connecting to SOAP service failed');
    }
    /**
    * Выдираю собственно текст ошибки из длинного лога
    *
    */
    public static function parseError($strg) {
        # writeDebugInfo("message strg to parse: ", $strg);
        if(mb_stripos($strg, 'Не найден aliasKey',0,'UTF-8')!==FALSE)
            $ret = $strg;
        else {
            $startPos = stripos($strg, 'MSCAPI ERROR:');
            $ret = '';

            if ($startPos) {
                $ret = mb_substr($strg, $startPos+14, 10, 'UTF-8');
            }

            if(!empty($ret) && isset(self::$digiErrors[$ret])) $ret = self::$digiErrors[$ret];
            else $ret = 'Undefined Error';
        }
        if(self::$debug) writeDebugInfo("parsed error: [$ret]");
        return $ret;
    }
    public static function logout() {

        if (self::$emulate) return true;
        if ( is_object(self::$soapCli) || empty(self::$sessionid) ) return false;
        $result = self::$soapCli->logout(self::$sessionid);
        self::$sessionid = '';
        if (self::$verbose) echo "logout result: " . print_r($result,1).'<br>';

        # WriteDebugInfo("logout result:", $result);
        return $result;
    }
    public static function testLogin() { // DigiSign::testLogin()
        self::init(false);
        if (self::$sessionid != '') {
            $logoutResult = self::logout();
            echo 'logout Result:<pre>'.print_r($logoutResult,1) . '</pre>';
        }
        return '';
    }
    /**
    * Тестовый вызов подписания файла
    * $ottisk - TRUE чтобы протестить печать оттиска с данными подписанта
    * DigiSign::testSign('Filatova20201006',0,0,1) - тест с выводом ЭЦП блока данных о сертификате
    */
    public static function testSign($alias=FALSE, $altSrv = FALSE, $verbose = FALSE, $ottisk=FALSE) {
        # self::$testing = 1;
        if(self::$debug) writeDebugInfo("testSign((alias=[$alias], altSrv=[$altSrv], verbose=[$verbose], ottisk=[$ottisk])");
        if (!self::isServiceActive()) exit("Сервис отключен в настройках");
        if ($altSrv) {
            self::$service_url = $altSrv;
            self::$skipLoadUrl = TRUE;
        }
        if ($verbose) self::$verbose = $verbose;

        self::init(FALSE,'',$altSrv);

        if (empty(self::$sessionid)) {
            $errText = "Оишбка инициализации: ". self::$errorMessage;
            if(self::$testing) exit('1' . AjaxResponse::showError($errText));
            else die($errText);
        }

        $params = ['filepath' => ALFO_ROOT . '/app/' . self::$testFileName ];

        # если надо протестить другой сервер, передать его базовый URL:
        # if (!empty($altSrv)) $params['serviceUrl'] = trim($altSrv);
        if (!empty($alias)) $params['aliasKey'] = trim($alias); # self::$aliasKey = $alias;
        if (!empty($altSrv)) $params['serviceUrl'] = trim($altSrv);

        /*
        if ($ottisk) { # выдать ЭЦП-оттиск на странице PDF файла
            $signature = [
              'page' => 1,
              'x'=>20, 'y'=>20, # отступы от левого нижнего угла. а не сверху!!!
              # 'width'=>240, # 200pt = 70mm !
              'height'=>200,
            ];
            if (is_array($ottisk)) $signature = array_merge($signature, $ottisk);
        }
        */
        $result = self::signFile($params, $ottisk);
        if (self::$sessionid) {
            self::close();
        }
        if (isset($result['body'])) {
            $destfile = 'tmp/test_signed.pdf';
            $saved = file_put_contents(ALFO_ROOT . $destfile, $result['body']);
            $fsize = filesize($destfile);
            $result['body'] = "Файл подписан, размер подписанного : $fsize, "
             . ($saved ? "сохранен под именем <a href='$destfile' target='_blank'>$destfile</a>" : "ошибка при сохранении в $destfile");
        }
        if ($verbose) echo 'signFile result:<pre>'.print_r($result,1) . '</pre>';
        return $result;
    }

    public static function getErrorMessage() { return self::$errorMessage; }

    # запрос списка сертификатов? не работает - [message] => Не указан параметр bytes
    public static function allCert() {
        $result = self::init();
        if (!$result) exit('Ошибка инициализации');
        $session_id = new SoapParam(self::$sessionid, 'session_id');
        $bytes = "";
        $result = self::$soapCli->allCert($session_id, self::$aliasKey, $bytes);
        return $result;
    }
    public static function getWSDL() {
        $options = [
            # 'stream_context' => $context,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'user_agent' => 'ALFO PHPclient',
            'encoding' => 'UTF-8',
            "soap_version"=>SOAP_1_1,
            'trace' => 1,
            'soapaction' => '',
        ];
        try {
            $wsdlUrl = self::$service_url . '?wsdl';
            self::$soapCli = new SoapClient($wsdlUrl, $options);
            $funcs = self::$soapCli->__getFunctions();
            $types = self::$soapCli->__getTypes();
            $ret = "functions from WSDL: <pre>".print_r($funcs,1).'</pre>';
            $ret .= "Types from WSDL: <pre>".print_r($types,1).'</pre>';
            return $ret;
        }
        catch (Exception $e) {

            echo 'creating SoapClient(WSDL) Exception :<pre>'.print_r($e,1) . '</pre>';
        }
    }
    /**
    * Для вызова из PrintFormPdf сразу по окончании генерации PDF файла
    *
    * @param mixed $param string полный путь к PDF файлу ИЛИ его содержимое
    * @return зависит от того что передали: если имя файла на диске, его же и вернет (подпишет и сохранит там же)
    * Если передано тело PDF, фунция вернет тело уже подписанного файла.
    * Ну и при любой ошибке вернет false
    */
    public static function signPdfBody($param, $signature = FALSE) {
        # writeDebugInfo("signPdfBody ", $param);
        $mode = 'F';
        if (strlen($param) <= 500 && is_file($param)) {
            $body = file_get_contents($param);
        }
        else {
            $body = $param;
            $mode = 'S';
        }
        # WriteDebugInfo("signPdfBody, mode=$mode, len(body):", strlen($body));
        $params = ['body' => $body];
        # {upd/2020-08-12} - добавляю вывод блока с данныи об ЭЦП, если делается печать ЭДО полиса
        if (!$signature && PlcUtils::isPrintEdoMode() ) {
            # беру накопленные параметры для ЭЦП
            $signParams = PlcUtils::getSignatureData();
            # writeDebugInfo("параметры для ЭЦП: ", $signParams);
            $signature = FALSE;
            $pageNo = 0;
            if (!empty($signParams['signature_page'])) {
                # writeDebugInfo("signature page is: ", $signParams['signature_page'], ', will convert to int!');
                $pageNo = intval($signParams['signature_page']);
            }
            if($pageNo<=0) $pageNo = PlcUtils::getMarkedPage();
            if ($pageNo > 0) {
                $signature = [ 'page' => intval($pageNo) ];
                if (isset($signParams['signature_x'])) $signature['x'] = floatval($signParams['signature_x']);
                if (isset($signParams['signature_y'])) $signature['y'] = floatval($signParams['signature_y']);
                if (isset($signParams['signature_w'])) $signature['width'] = floatval($signParams['signature_w']);
                if (isset($signParams['signature_h'])) $signature['height'] = floatval($signParams['signature_h']);
                if(self::$debug) writeDebugInfo("final params for signature: ", $signature);
            }
        }

        $result = self::signFile($params, $signature);

        if (!empty($result['body'])) {
            if ($mode === 'F') {
                file_put_contents($param, $result['body']);
                # clearstatcache(); // filesize() refresh
                # WriteDebugInfo("signed file stored to $param, new size:", filesize($param));
                return $param;
            }
            // передали тело PDF, и возвращаю тело PDF
            # WriteDebugInfo("returning signed body, len:", strlen($result['body']));
            return $result['body'];
        }
        else {
            return $result;
        }
    }
    # проверка ЭЦП со страницы настроек (инфа об алиасе)
    public static function checkWorking($url='', $login='', $passw = '', $alias='') {
        # self::$debug = 1;
        if (!self::isServiceActive()) return ("Сервис отключен в настройках");
        if(!$url) $url = AppEnv::$_p['url'] ?? '';
        if(!$login) $login = AppEnv::$_p['login'] ?? '';
        if(!$passw) $passw = AppEnv::$_p['password'] ?? '';
        if(!$alias) $alias = AppEnv::$_p['alias'] ?? '';
        if(self::$debug) writeDebugInfo("checkWorking([$url], [$login], [$passw], [$alias]");
        /*
        $result = self::TestSign($alias,$url,0,TRUE);
        if(!empty($result['success'])) return ('1'.AjaxResponse::showMessage($result['body'], 'Успех'));
        else {
            self::$errorMessage = $result['message'] ?? 'Undefined';
            return ('1' . AjaxResponse::showError(self::$errorMessage));
        }
        */
        $svcUrl = $url;

        if (!self::isServiceActive()) exit("Сервис отключен в настройках");
        $params = [
          'serviceUrl'=> $url,
          'aliasKey' => $alias
        ];
        $result = self::getAliasInfo($alias,$url,0,TRUE);
        # writeDebugInfo("result ", $result);
        # $result: 'dateExpire' = дата оконч-я д-вия, 'signerName' - ФИО подписанта 'MCHDGuid' =>МЧД строка
        if(isset($result['dateExpire'])) {
            $msgResult = 'ФИО подписанта: '.$result['signerName'];
            if(!empty($result['MCHDGuid'])) {
                $msgResult .= '<br>МЧД: '.$result['MCHDGuid'];
                if(!empty($result['dateExpireMCHD'])) {
                    $msgResult .= '<br>МЧД действует до '. date('d.m.Y H:i', strtotime($result['dateExpireMCHD']));
                    if(strtotime($result['dateExpireMCHD']) < time()) $msgResult .= " <span style='color:red'>ИСТЁК!</span>";
                }
            }

            if(!empty($result['inn']))
                $msgResult .= '<br>ИНН: '.$result['inn'];

            $dateExp = $result['dateExpire'];
            $timeExp = strtotime($dateExp);
            if($timeExp > time() ) {
                $msgResult .= "<br><br>Сертификат истекает ". date('d.m.Y H:i', strtotime($dateExp));
                $restTime = $timeExp - time();
                if($restTime < (10 * 86400)) $msgResult .= ",<br><span style='color:red'>ИСТЕКАЕТ менее чем через 10 дней!</span>";
                exit('1' . AjaxResponse::showMessage($msgResult, "Данные по сертификату $alias"));
            }
            else {
                $msgResult .= "<br><br>Внимание, сертификат сгорел ".date('d.m.Y H:i', strtotime($dateExp));
                exit('1' . AjaxResponse::showError($msgResult, "Данные по сертификату $alias"));
            }
        }
        else {
            $errTxt = $result['message'] ?? 'Ошибка при выполнении запроса';
            exit('1' . AjaxResponse::showError($errTxt,'Ошибка сервиса'));
        }

        # exit('1' . AjaxResponse::showMessage('<pre>'.print_r($result).'</pre>'));
    }
}