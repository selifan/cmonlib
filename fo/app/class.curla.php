<?php
/**
* @name class.curla.php
* Модуль работы с внешними источниками через curl_ функции
* @author Alexander Selifonov
* @version 1.0.1
* @date 2019-12-11
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
    public static $disableSSLcheck = TRUE; # отключение проверки сертификатов SSL
    public static $disableIPV6 = FALSE; # отключение поддержки IPV6
    public static $user_agent = 'CURLA library for Web Applications';
    public static $max_redir = 4;
    public static $disable_proxy = FALSE; # force disable proxy even if proxy params not empty

    public static $timeout = 5;
    public static $curl_error_code = 0;
    public static $curl_error_message = '';
    public static $response = '';
    private static $debug = 1;
    static $POST = 'AUTO';
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

    public static function init($url = '', $postFields = FALSE, $timeout = 0 ) {
        self::getProxyParams();
        self::$curlobj = curl_init($url);
        if ($timeout>0) self::$timeout = $timeout;
        curl_setopt(self::$curlobj, CURLOPT_CONNECTTIMEOUT, self::$timeout);
        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, true);
        if (self::$user_agent)
            curl_setopt(self::$curlobj, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt(self::$curlobj, CURLOPT_MAXREDIRS, self::$max_redir);
        curl_setopt(self::$curlobj, CURLOPT_FOLLOWLOCATION, true);

        if (is_array($postFields) && count($postFields)) {
            curl_setopt(self::$curlobj, CURLOPT_POST, true);
            curl_setopt(self::$curlobj, CURLOPT_POSTFIELDS, http_build_query($postFields, '', '&'));
        }
        if(!empty(self::$proxy['addr']) && !self::$disable_proxy) {
            curl_setopt(self::$curlobj, CURLOPT_PROXY, self::$proxy['addr']);
            if(self::$proxy['port']) curl_setopt(self::$curlobj, CURLOPT_PROXYPORT, self::$proxy['port']);

            if(!empty(self::$proxy['login'])) {
                $authType = empty(self::$proxy['auth']) ? CURLAUTH_ANY : self::$proxy['auth'];
                curl_setopt(self::$curlobj, CURLOPT_PROXYAUTH, $authType);
                # CURLAUTH_BASIC | CURLAUTH_NTLM |CURLAUTH_ANYSAFE
                $loginpass = self::$proxy['login'] . ':' . self::$proxy['password'];
                curl_setopt(self::$curlobj, CURLOPT_PROXYUSERPWD, $loginpass);
            }
        }

        if (self::$disableSSLcheck) {
            curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, false);
        }
        if (self::$disableIPV6) {
            if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4'))
               curl_setopt(self::$curlobj, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        if (self::$debug) writeDebugInfo("init() result code: ", self::$curlobj);
        return self::$curlobj;
    }

    # получаю данные по URL из интернет (через прокси если настроен)
    public static function getFromUrl($url, $postFields = FALSE, $timeout = 0) {
        $curlObj = self::init($url, $postFields);
        if (!is_resource($curlObj)) {
            self::$curl_error_code = curl_errno($curlObj);
            self::$curl_error_message = curl_error($curlObj);
            return NULL;
        }
        self::$response = @curl_exec($curlObj);
        self::$curl_error_code = curl_errno($curlObj);
        self::$curl_error_message = curl_error($curlObj);
        if (self::$debug) {
            writeDebugInfo($url, ' params : ', $postFields);
            writeDebugInfo("curl_exec response: ", self::$response);
            writeDebugInfo("curl_exec errno: ", self::$curl_error_code);
            writeDebugInfo("curl_exec error message: ", self::$curl_error_message);
        }

        if (empty(self::$response) && self::$curl_error_code != 0)
            return [ 'result' => 0, 'error'=> self::$curl_error_code, 'errorMessage' => self::$curl_error_message ];

        return self::$response;
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

    public static function getErrno() {
        return self::$curl_error_code;
    }
    public static function getErrMessage() {
        return self::$curl_error_message;
    }
}