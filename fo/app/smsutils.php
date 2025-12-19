<?php
/**
* @package ALFO - Фронт-Офис
* @author Alexander Selifonov,
* @name app/smsutils.php
* Класс для отправки SMS
* @version 0.15.003
* {upd/2024-11-05} добавлена поддержка отправки через разные учетки провайдера СМС-услуг
* тест с другими учетками: SmsUtils::test('9104311278', 'test SMS', 0, 'bnk') (or 'avr')
* modified : 2025-02-14
*/

class  SmsUtils {

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
    static $SMS_ENGINE = 'sms_zagruzka';

    public static function setAccount($accId) {
        if(!empty(self::$SMS_ENGINE)) {
            $className = self::$SMS_ENGINE;
            $result= $className::setAccount($accId);
        }
        else
            self::$account = $accId;
    }

    /**
    * Вернет время в секундах, прошедшее с последней отправки СМС от этой же сессии
    *
    */
    public static function getPassedTime($hash= '') {
        # select id, send_date, TIMEDIFF(NOW(), send_date) as timepass from  alf_smslog
        if (empty($hash)) $hash = session_id();
        $dta = appEnv::$db->select(PM::T_SMSLOG, [
          'where' => ['sessionid' => $hash],
          'fields' => "send_date, TIMEDIFF(NOW(), send_date) passed",
          'orderby' => 'send_date DESC',
          'singlerow' => 1,
        ]);
        if (self::$debug) writeDebugInfo("last SMS sent for hash $hash: ", $dta);
        if (isset($dta['passed'])) {
            $elems = explode(':',$dta['passed']);
            $seconds = intval($elems[0])*3600 + $elems[1]*60 + intval($elems[2]);
            return $seconds; # . ' / ' .$dta['passed'];
        }
        else return FALSE;
    }

    /**
    * отправка СМС на указанный номер
    * @param mixed $phone телефон
    * @param mixed $text сообщение
    * @param mixed $module ID модуля
    * @param mixed $plcid ID полиса/карточки документа
    */
    public static function sendSms($phone, $text, $hash='', $module='', $plcid=0) {

        $result = FALSE;
        $login = $password = '';
        if (!$hash) $hash = session_id();

        if (self::$debug) {
            writeDebugInfo("sendSms(phone=[$phone], text=[$text], hash=[$hash], module=[$module], plcid=[$plcid]) started");
            if (self::$debug>1) writeDebugInfo("sendSms trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        }
        if(!empty(self::$SMS_ENGINE)) {
            $className = self::$SMS_ENGINE;
            $result= $className::sendSms($phone, $text, $hash, $module, $plcid);
            return $result;
        }
        if ($phone === self::TEST_PHONE) return TRUE;
        $cfgName = __DIR__ . '/../cfg/cfg-smsutils.php';
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
        appEnv::$db->insert(PM::T_SMSLOG, $smsDta);
        # }
        return $ret;
    }
    # тольно на тестовых серверах - очистка лога от СМС для заданного полиса
    public static function clearLog($module, $plcid) {
        $ret = appEnv::$db->delete(PM::T_SMSLOG, ['module'=>$module, 'policyid'=>$plcid]);
        return $ret;
    }
    /**
    * тестовая отправка СМС, acount можно задать bnk или agt - для выбора 2-о1 или 3-ей учетки провайдера (2024-10-31)
    *
    * @param mixed $phone
    * @param mixed $text
    * @param mixed $verbose
    * @param mixed $account
    */
    public static function test($phone, $text = '', $verbose=0, $account=FALSE) {
        if (!$text) $text = 'test message';
        if ($phone) {

            if ($verbose)  {
                error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
                ini_set('display_errors', 1);
                ini_set('log_errors', 1);
                self::$verbose = self::$debug = 1;
            }
            if(!empty($account) && is_string($account))
                self::setAccount($account);

            $result = self::sendSms($phone, $text);
            if(!$result) {
                $ret = [ 'result'=>'FAIL', 'url' => $url ];
            }
        }
        else
            $result = 'Wrong phone no';

        return $result;
    }
    # Привожу телефонный номер к "стандартному" виду без лишних знаков
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
    # регистрирую событие подбора/ввода котнрольногокода из СМС
    public static function registerSmsAttempt($module, $docId, $result) {
        $arDta = [
          'module' => $module,
          'docid' => $docId,
          'useragent' => $_SERVER['HTTP_USER_AGENT'],
          'ipaddr' => $_SERVER['REMOTE_ADDR'],
          'evt_date' => '{now}',
          'result' => $result
        ];
        $result = AppEnv::$db->insert(PM::T_SMS_CHECKLOG, $arDta);
        if(!$result)
            AppEnv::logSqlError(__FILE__, __FUNCTION__, (__LINE__-1));
        return $result;
    }
}