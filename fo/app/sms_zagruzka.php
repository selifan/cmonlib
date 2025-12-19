<?php
/**
* @name app/sms_zagruzka.php
* Отправка СМС через zagruzka.com (старый провайдер)
*/
class sms_zagruzka {

    static $debug = 0;
    static $curlobj = NULL;
    static $timeout = 5; # timeout seconds
    static $cfg = [];
    static $verbose = 0;
    static $emulate = 0;
    static $finalUrl = '';
    static $account = FALSE; # для переключения на другой комплект логина и пароля
    static $ONLY_RUSSIAN_PHONES = TRUE; # разрешаю только номера с 9-кой в начале (+7-9XX-XXX-XXXX)
    static $method = 'GET'; # а можно POST
    const TEST_PHONE = '9101000000'; # при таком телефоне СМС не послылается (тестовый)
    const TEST_SMSCODE = '1234'; # вечный СМС для тестового телефона
    static $proxy = FALSE;
    public static function setAccount($accId) {
        self::$account = $accId;
    }

    public static function sendSMS($phone, $text, $hash='', $module='', $plcid=0) {
        $result = FALSE;
        $login = $password = '';
        if (!$hash) $hash = session_id();

        if (self::$debug) {
            writeDebugInfo("sendSms(phone=[$phone], text=[$text], hash=[$hash], module=[$module], plcid=[$plcid]) started");
            if(self::$debug>1) writeDebugInfo("sendSms trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        }
        if ($phone === self::TEST_PHONE) return TRUE;
        $cfgName = AppEnv::getAppFolder('cfg/') . '/cfg-smsutils.php';
        if (is_file($cfgName)) self::$cfg = include($cfgName);
        if (!empty(self::$cfg['method'])) self::$method = self::$cfg['method'];
        if (isset(self::$cfg['emulate'])) self::$emulate = self::$cfg['emulate'];
        $sendPhone = self::sanitizeMobileNumber($phone);
        if (!$sendPhone) {

            if (self::$verbose) echo "sanitize number for $phone returned BAD [$sendPhone]<br>";
            if (self::$debug) writeDebugInfo("bad phone number after sanitizing, sms aborted: src:[$phone] sanitized:[$sendPhone], return FALSE");
            return FALSE;
        }

        if (self::$emulate) { # реальную посылку не делаю, эмуляция!
            $result = "OK\r\n\r\n". (string)(time() - strtotime('2020-08-01'));
            if(self::$debug || \AppEnv::isLocalEnv()) writeDebugInfo("emulate SMS send to $phone: [$text], result = $result");
        }
        else { # not emualte
            if (empty(self::$cfg['service_url'])) {
                if (self::$debug) writeDebugInfo("not found config value service_url, SMS not sent");
                return FALSE;
            }

            self::_InitCurl();

            # {upd/2024-10-25}
            $acc = self::$account;

            if(empty($acc) && !empty($module)) {
                # пытаюсь сам определить какой учеткой пользоваться - по модулю или каналу продаж
                if($module === 'agentvr') $acc = 'avr';
                elseif($module === 'plsign') $acc = ''; # всегда для агентов!
                else {
                    $bkend = \AppEnv::getPluginBackend($module);
                    $meta = $bkend->_rawAgmtData['metatype'] ?? '';
                    if(!$meta) $meta = \AppEnv::$db->select(PM::T_POLICIES,['fields'=>'metatype','where'=>['stmt_id'=>$plcid],
                      'singlerow'=>1,'associative'=>0]);
                    if(!empty($meta) && $meta == \OrgUnits::MT_BANK) $acc = 'bnk';
                    if(self::$debug) writeDebugInfo("metatype: ", $meta, " login chosen:[$acc]");
                }
            }
            $params = [];
            $login = self::$cfg['login'];
            $password = self::$cfg['password'];
            if(self::$debug) writeDebugInfo("find login for ", 'login_'.$acc, ' and passw ', 'password_'.$acc, " cfg: ", self::$cfg);

            if(!empty($acc) && !empty(self::$cfg['login_'.$acc]) && !empty(self::$cfg['password_'.$acc])) {
                $login = self::$cfg['login_'.$acc];
                $password = self::$cfg['password_'.$acc];
                if(self::$debug) writeDebugInfo("изменен СМС логин и пароль [$acc]: $login / $password");
            }
            $params[self::$cfg['field_login']] = $login;
            $params[self::$cfg['field_password']] = $password;
            $params[self::$cfg['field_phone']] = '7'.$sendPhone; # по требованию сервиса - форма 7xxxXXXXXXX ?
            $params[self::$cfg['field_message']] = $text;

            $url = self::$cfg['service_url'];
            $url = self::$finalUrl = str_replace('{login}', $login, $url); # {upd/2024-11-05} логин как часть URI сервиса
            if (self::$method === 'GET') {
                $arParams = [];
                foreach($params as $k=>$value) {
                    $arParams [] = "$k=" . urlencode($value);
                }
                $url .= '?' . implode('&', $arParams); # делаю https://server/login?param1=val1&param2=val2...
            }
            else {
                curl_setopt(self::$curlobj, CURLOPT_POST, 1);
                curl_setopt(self::$curlobj, CURLOPT_POSTFIELDS, $params);
            }

            if(self::$verbose) {
                self::$cfg['final-Url'] = $url;
                self::$cfg['method'] = self::$method;
                echo '<br>sms params: <pre>' . print_r(self::$cfg,1) . '</pre>';
            }
            if (self::$debug) writeDebugInfo("SMS sending final URL: ", $url);

            curl_setopt(self::$curlobj, CURLOPT_URL, $url);
            $result = curl_exec(self::$curlobj);
            $error_message = '';
            if(self::$verbose) echo "curl called<br>";
            if($errno = curl_errno(self::$curlobj)) {
                $error_message = "curl ERROR $errno"; # curl_strerror(self::$curlobj);
                if (self::$verbose) echo "cURL error ({$errno}): {$error_message}";
                writeDebugInfo("curl error ({$errno}): {$error_message}");
                # return FALSE;
            }
            elseif(self::$verbose) echo "no error calling curl result: <pre>". print_r($result,1). '</pre>';

            curl_close(self::$curlobj);
            if (self::$debug > 1) @file_put_contents("_curl_result.log", $result);
        } # not emualte
        $splt = preg_split( '/[\s]/', $result, -1, PREG_SPLIT_NO_EMPTY );
        $ret = ($splt[0] === 'OK');
        if(self::$debug) writeDebugInfo("result from sms service: $result, rcode to return: [$ret]");

        $resultCode = isset($splt[1]) ? $splt[1] : '';
        if (self::$emulate) $resultCode = 'emul-'.$resultCode;

        # if (!self::$emulate) {
        $ipaddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'NONE';
        $smsDta = [
            'sessionid' => $hash,
            'client_phone' => $sendPhone,
            'account' => ( empty($acc) ? "std:$login" : "$acc:$login" ), # заношу какой учеткой пользовался
            'ipaddr' => $ipaddr,
            'smstext' => $text,
            'send_result' => $splt[0],
            'resultcode' => $resultCode,
            'module'  => $module,
            'policyid' => $plcid
        ];
        AppEnv::$db->insert(PM::T_SMSLOG, $smsDta);
        # }
        return $ret;
    }
    # Привожу телефонный номер к "стандартному" виду для провайдера
    public static function sanitizeMobileNumber($phone) {
        $ret = '';
        # $phone = strtr($phone, [' '=>'','-'=>'']);
        $ret = preg_replace( "/[^\d+]/", "", $phone ); # удалил все НЕ-цифры
        if (strlen($ret)<10) return FALSE;
        if (in_array(substr($ret,0,1), ['8','7']) ) {
            # $ret .= '7';
            $ret = substr($ret, 1);
        }
        elseif(substr($ret,0,1) === '+') {
            $ret = substr($ret,2); # '+7...' => '...';
            # $phone = substr($phone, 2);
        }
        # else $ret = $phone;
        # else $ret = ''; # российский номер!
        # $ret = preg_replace( "/[^\d]/", "", $ret ); # удалил все НЕ-цифры
        if (self::$verbose >1) echo "$phone has converted to [$ret]";
        if (strlen($ret)<10) {
            return FALSE;
        }
        if (self::$ONLY_RUSSIAN_PHONES && substr($ret,0,1) !='9')
            return FALSE;

        return $ret;
        # return ($ret . $phone);
    }
    # Готовлюсь к вызову curl_exec
    private static function _InitCurl($use_proxy = false) {
        if(!function_exists('curl_init')) {
            die('Curl extension not enabled: <br>uncomment <b>extension=php_curl.dll</b> in Your php.ini !');
        }
        self::$curlobj = curl_init();
        curl_setopt(self::$curlobj, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, true);
        if($sslPath = AppEnv::getConfigValue('ssl_cert_path')) {
            if(is_dir($sslPath)) {
                curl_setopt(self::$curlobj, CURLOPT_CAPATH, $sslPath);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, TRUE);
            }
            elseif(is_file($sslPath)) {
                curl_setopt(self::$curlobj, CURLOPT_CAINFO, $sslPath);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, TRUE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, TRUE);
            }
            else { # не файл и не папка? снова отключаю проверку SSL серт.
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, FALSE);
            }
        }
        else {
            curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, false);
        }
        if(!empty(self::$proxy['addr']) && $use_proxy) {
            curl_setopt(self::$curlobj, CURLOPT_PROXY, self::$proxy['addr']);
            if(self::$proxy['port']) curl_setopt(self::$curlobj, CURLOPT_PROXYPORT, self::$proxy['port']);
            if(!empty(self::$proxy['auth'])) curl_setopt(self::$curlobj, CURLOPT_PROXYAUTH,  self::$proxy['auth']); # CURLAUTH_BASIC | CURLAUTH_NTLM |...
            if(!empty(self::$proxy['login'])) {
                $loginpass = self::$proxy['login'] . ':' . (isset(self::$proxy['password'])?self::$proxy['password']:'');
                curl_setopt(self::$curlobj, CURLOPT_PROXYUSERPWD, $loginpass);
            }
            if (self::$verbose) {
                echo '_InitCurl: proxy params set.<pre>'. print_r(self::$proxy,1).'</pre>';
            }

        }
        else {
            if (self::$verbose) echo "_InitCurl: No proxy used<br>\n";
        }

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
           curl_setopt(self::$curlobj, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        curl_setopt(self::$curlobj, CURLOPT_CONNECTTIMEOUT, self::$timeout); # seconds waiting for response
        curl_setopt(self::$curlobj, CURLOPT_TIMEOUT, 2*self::$timeout);

        if (self::$timeout>0) {
            if(self::$verbose) echo "CURL timeout set (seconds):".self::$timeout;
        }
    }
}
