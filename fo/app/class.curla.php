<?php
/**
* @name app/class.curla.php
* Модуль работы с внешними источниками через curl_ функции
* @author Alexander Selifonov
* @version 1.07.003
* @date 2025-06-10
*/
class CurlA {

    public static $curlobj = NULL;
    private static $proxy = [
      'addr' => NULL,
      'port' => 0,
      'auth' => '',
      'login' => '',
      'password' => '',
    ];
    private static $sslCertPath = ''; # если передать путь к папке с сертификатами, все вызовы будут с проверкой серт.сервера
    public static $disableIPV6 = FALSE; # отключение поддержки IPV6
    public static $user_agent = 'CURLA library for Web Applications';
    public static $max_redir = 4;
    public static $disable_proxy = FALSE; # force disable proxy even if proxy params not empty
    static $getResponseHeaders = FALSE;
    static $respHeader = '';
    static $multipart = FALSE;
    static $testSiteTest = 'https://ecomtest.sberbank.ru';
    static $testSiteProd = 'https://ecommerce.sberbank.ru'; # сайт с новым SSL сертификатом от Мин.Цифры (2023)
    private static $checking = FALSE; # временно включается перед вызовом проверки работы с сертификатами - checkSslCert()
    private static $disableSSL = FALSE; # принуд.отключение проверки SSL сертиф.
    static $boundary = '';
    public static $timeout = 20;
    public static $curl_error_code = 0;
    public static $curl_error_message = '';
    public static $response = '';
    static $debug = 0;
    # static $POST = 'AUTO';

    public static function setDebug($val) {
        self::$debug = $val;
    }
    public static function responseHeaders($val) {
        self::$getResponseHeaders = $val;
    }

    # включаем принуд.режим MULTIPART кодирования тела с параметрами
    public static function setMultiPart($mode=TRUE) {
        self::$multipart = $mode;
    }
    # показываем папку где лежат SSL сертификаты
    public static function setSslCertPath($path) {
        self::$sslCertPath = $path;
        // curl_setopt($ch, CURLOPT_CAPATH, '/etc/ssl/certs');
    }
    public static function disableSSL($val = TRUE) {
        self::$disableSSL = $val;
    }
    public static function getProxyParams() {
        if (self::$proxy['addr'] !== NULL) return;
        self::$proxy['addr'] = appEnv::getConfigValue('proxy_addr');
        if (strlen(self::$proxy['addr'])) {
            self::$proxy['port'] = appEnv::getConfigValue('proxy_port');
            self::$proxy['auth'] = appEnv::getConfigValue('proxy_auth');
            self::$proxy['login'] = appEnv::getConfigValue('proxy_login');
            self::$proxy['password'] = appEnv::getConfigValue('proxy_password');
        }
    }
    /**
    * инициализация curl объекта
    *
    * @param mixed $url
    * @param mixed $postFields поля для POST запроса
    * @param mixed $timeout
    * @param mixed $headers массив доп. HTTP заголовков
    */
    public static function init($url = '', $postFields = FALSE, $timeout = 0, $headers = FALSE, $asJson = FALSE, $files = FALSE ) {
        self::getProxyParams();
        self::$curlobj = curl_init($url);
        if(!self::$curlobj) return FALSE;
        if(!is_array($headers)) $headers = [];
        if ($timeout>0) self::$timeout = $timeout;
        if(self::$debug) writeDebugInfo("CURLA url to call: [$url]");
        curl_setopt(self::$curlobj, CURLOPT_CONNECTTIMEOUT, self::$timeout);
        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, TRUE);
        # curl_setopt($ch, CURLOPT_RETURNTRANSFER, 44455); // to avoid error 56 proxy reject
        curl_setopt(self::$curlobj, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if (self::$user_agent)
            curl_setopt(self::$curlobj, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt(self::$curlobj, CURLOPT_MAXREDIRS, self::$max_redir);
        curl_setopt(self::$curlobj, CURLOPT_FOLLOWLOCATION, true);
        if(is_array($files) && count($files)) {
            self::$multipart = TRUE;
        }

        if(self::$multipart) {
            self::$boundary = uniqid();
            # все поля и файлы кодирую в MULTIPART пакет
            $data = self::buildMultiPartBody($postFields, $files);
        }
        elseif (is_array($postFields) && count($postFields)) {
            curl_setopt(self::$curlobj, CURLOPT_POST, TRUE);
            if ($asJson) {
                # $encodedPost = urlencode(json_encode($postFields,JSON_UNESCAPED_UNICODE));
                $encodedPost = json_encode($postFields,JSON_UNESCAPED_UNICODE);
                $postLen = strlen($encodedPost);
                if(self::$debug) writeDebugInfo("кодирую POST в JSON: ", $encodedPost);
                # curl_setopt(self::$curlobj, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                if( $asJson ===2 ) $headers['Content-Type'] = 'application/json';
                curl_setopt(self::$curlobj, CURLOPT_POSTFIELDS,$encodedPost);
                curl_setopt(self::$curlobj, CURLOPT_HTTPHEADER, ["Content-Length: $postLen"]);
                if(self::$debug) writeDebugInfo("POST fields as Json, header added: Content-Type: application/json");
            }
            else {
                $encodedPost =  http_build_query($postFields, '', '&');
                if(self::$debug) writeDebugInfo("кодирую POST (стандарт): ", $encodedPost);
                curl_setopt(self::$curlobj, CURLOPT_POSTFIELDS,$encodedPost);
                if(self::$debug) writeDebugInfo("POST fields in Std form");
            }
            if(self::$debug) writeDebugInfo("CURLA encoded POST fields: ", $encodedPost);
        }
        if(self::$multipart) {
            $headers['Content-Type'] = 'multipart/form-data';
        }

        if (count($headers) ) {
            # http headers passed
            $arHeaders = [];
            foreach($headers as $key => $val) {
                $arHeaders[] = "$key: $val";
            }
            curl_setopt(self::$curlobj, CURLOPT_HTTPHEADER, $arHeaders);
            if (self::$debug) writeDebugInfo("send http headers: ", $arHeaders);
        }

        if(!empty(self::$proxy['addr']) && !self::$disable_proxy) {
            if(self::$debug) writeDebugInfo("activate proxy for CURl operation: ", self::$proxy['addr']);
            curl_setopt(self::$curlobj, CURLOPT_PROXY, self::$proxy['addr']);
            if(self::$proxy['port']) curl_setopt(self::$curlobj, CURLOPT_PROXYPORT, self::$proxy['port']);

            if(!empty(self::$proxy['login'])) {
                $authType = empty(self::$proxy['auth']) ? CURLAUTH_ANY : self::$proxy['auth'];
                curl_setopt(self::$curlobj, CURLOPT_PROXYAUTH, $authType);
                # CURLAUTH_BASIC | CURLAUTH_NTLM |CURLAUTH_ANYSAFE
                $loginpass = self::$proxy['login'] . ':' . self::$proxy['password'];
                curl_setopt(self::$curlobj, CURLOPT_PROXYUSERPWD, $loginpass);
            }
            if(self::$debug) writeDebugInfo("CURL using proxy: ", self::$proxy['addr'], " loginPassw=[$loginpass]");
        }
        if(!self::$checking) {
            # беру путь из настроек
            self::$sslCertPath = AppEnv::getConfigValue('ssl_cert_path', '');
        }

        if (empty(self::$sslCertPath) || self::$disableSSL) {
            curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, FALSE);
            if(self::$debug) writeDebugInfo("SSL check is OFF");
        }
        else {
            if(self::$debug) writeDebugInfo("SSL check is ON!  cert-path:", self::$sslCertPath);
            if(self::$sslCertPath === '1') {
                # просто включен режим, пути к SSL не устанавливаю
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, 2);
            }
            elseif(is_dir(self::$sslCertPath)) {
                curl_setopt(self::$curlobj, CURLOPT_CAPATH, self::$sslCertPath);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, 2);
            }
            elseif(is_file(self::$sslCertPath)) {
                curl_setopt(self::$curlobj, CURLOPT_CAINFO, self::$sslCertPath);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, 2);
            }
            else { # не файл и не папка? снова отключаю проверку SSL серт.
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, FALSE);
            }
        }
        if (self::$disableIPV6) {
            if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
               curl_setopt(self::$curlobj, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
               if(self::$debug) writeDebugInfo("disabled IPV6");
            }
        }
        if (self::$debug) writeDebugInfo("init() result code: ", self::$curlobj);
        return self::$curlobj;
    }

    # получаю данные по URL из интернет (через прокси если настроен)
    public static function getFromUrl($url, $postFields = FALSE, $timeout = 0, $headers = 0, $asJson = 0, $files=FALSE) {
        self::init($url, $postFields, $timeout, $headers, $asJson, $files);
        if (!self::$curlobj) {
            self::$curl_error_code = -1000;
            self::$curl_error_message = 'curl_init returned FALSE';
            return NULL;
        }
        if(self::$debug || self::$getResponseHeaders) {
            curl_setopt(self::$curlobj, CURLOPT_HEADER, 1);
        }

        self::$response = @curl_exec(self::$curlobj);
        if(self::$debug) writeDebugInfo("full CURL response: ", self::$response);
        if(self::$debug || self::$getResponseHeaders) {
            $header_size = curl_getinfo(self::$curlobj, CURLINFO_HEADER_SIZE);
            self::$respHeader = substr(self::$response, 0, $header_size);
            if(self::$debug) writeDebugInfo("response header(size=$header_size):", self::$respHeader);
            self::$response = substr(self::$response, $header_size);
        }
        self::$curl_error_code = curl_errno(self::$curlobj);
        self::$curl_error_message = curl_error(self::$curlobj);
        if (self::$debug) {
            writeDebugInfo("curl_exec response: ", self::$response);
            writeDebugInfo("curl_exec errno: ", self::$curl_error_code);
            writeDebugInfo("curl_exec error message: ", self::$curl_error_message);
        }
        curl_close(self::$curlobj);
        if (empty(self::$response) && self::$curl_error_code != 0)
            return [ 'result' => 0, 'error'=> self::$curl_error_code, 'errorMessage' => self::$curl_error_message ];

        return self::$response;
    }

    public static function getResponseHeaders() {
        return self::$respHeader;
    }

    public static function isFailedCall() {
        return (self::$curl_error_code !=0);
    }

    public static function setUserAgent($ag) {
        self::$user_agent = $ag;
    }

    public static function disableProxy($par = TRUE) {
        self::$disable_proxy = $par;
    }

    public static function getErrNo() {
        return self::$curl_error_code;
    }
    public static function getErrMessage() {
        return self::$curl_error_message;
    }

    public static function getRawResponse() {
        return self::$response;
    }

    # Если надо передавать файлы, кодируем всё в multipart/fromdata тело
    # source: с https://gist.github.com/maxivak/18fcac476a2f4ea02e5f80b303811d5f
    public static function buildMultiPartBody($fields, $files, $boundary =''){
        $data = '';
        $eol = "\r\n";
        if(empty($boundary)) $boundary = self::$boundary;
        if(empty($boundary)) $boundary = uniqid();

        $delimiter = '-------------' . $boundary;

        if(is_array($fields) && count($fields))
        foreach ($fields as $name => $content) {
            $data .= "--" . $delimiter . $eol
                  . 'Content-Disposition: form-data; name="' . $name . "\"".$eol.$eol
                  . $content . $eol;
        }

        if(is_array($files) && count($files))
        foreach ($files as $name) {
            if(!is_file($name) || !is_readable($name)) continue;
            $content = file_get_contents($name);
            $basename = basename($name);
            $data .= "--" . $delimiter . $eol
                  . 'Content-Disposition: form-data; name="' . $basename . '"; filename="' . $basename . '"' . $eol
                  //. 'Content-Type: image/png'.$eol
                  . 'Content-Transfer-Encoding: binary'.$eol;

            $data .= $eol;
            $data .= $content . $eol;
        }
        $data .= "--" . $delimiter . "--".$eol;

        return $data;
    }
    /**
    *  проверка работы с сертификатами
    * @param mixed $certPath путь к файлам сертификатов (или имя одного файла)
    * @param mixed $useMC - TRUE = использовать для проверки вызов сайта с сертификатом от Мин-цифры
    */
    public static function checkSslCert($certPath = FALSE, $useMC=TRUE) {
        $webCall = FALSE;
        self::$checking = TRUE;
        if(!$certPath) {
            $certPath = AppEnv::$_p['certpath'] ?? '';
            $webCall = TRUE;
        }
        # if($certPath === '') exit('Передана пустая строка (работа без проверки SSL сертификатов)!');
        if(!empty($certPath) && !is_file($certPath) && !is_dir($certPath) && $certPath!=='1')
            exit('Строка не является путем или файлом!');
        if(!empty($certPath))
            self::setSslCertPath($certPath);
        /*
        if(isset(AppEnv::$_p['ucert']))
            $useMC = AppEnv::$_p['ucert'];
        if($useMC)
            $testUrl = self::$testSite;
            # $result = self::getFromUrl(self::$testSiteMC);
        else {
        */
        if(AppEnv::isProdEnv())
            $testUrl = self::$testSiteProd;
        else
            $testUrl = self::$testSiteTest;

        $result = self::getFromUrl($testUrl);
        # writeDebugInfo("call result: ", $result);
        $error = FALSE;
        if($err = self::getErrNo()) {
            $errMsg = self::getErrMessage();
            $error = TRUE;
            $ret = "Ошибка при открытии $testUrl - $err: $errMsg";
            # exit('1'.AjaxResponse::showError());
        }
        else {
            $htmlcode = htmlentities( mb_substr($result,0,80, 'UTF-8') );
            $ret = "Начальный кусок кода c сайта $testUrl:<br>".$htmlcode;
        }
        if($webCall)
            exit('1' . AjaxResponse::showMessage($ret,'Проверка работы с SSL сертификатами',
              ($error? 'msg_error':'msg_ok'))
            );

        return $ret;
    }
}
if(!empty(appEnv::$_p['curlaction'])) {
    $action = appEnv::$_p['curlaction'];
    if(method_exists('Curls', $action)) Curla::$action();
    else exit("Curla: undefined call ($action)");
}