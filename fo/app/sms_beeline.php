<?php
/**
* @name app/sms_beeline.php
* Отправка СМС через BEELINE
* modified 2025-12-19
*/
class sms_beeline {

    static $debug = 1;
    static $curlobj = NULL;
    static $timeout = 5; # timeout seconds
    static $cfg = [];
    static $verbose = 0;
    static $emulate = 0;

    static $account = ''; # для переключения на другой комплект логина и пароля
    static $apiKey = FALSE;
    static $ONLY_RUSSIAN_PHONES = TRUE; # разрешаю только номера с 9-кой в начале (+7-9XX-XXX-XXXX)

    static $service_url = '';
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

        $cfgName = AppEnv::getAppFolder('cfg/') . '/cfg-sms-beeline.php';
        if (is_file($cfgName)) self::$cfg = include($cfgName);

        if (!empty(self::$cfg['service_url'])) self::$service_url = self::$cfg['service_url'];
        if (!empty(self::$cfg['apiKey'])) self::$apiKey = self::$cfg['apiKey'];
        if (isset(self::$cfg['emulate'])) self::$emulate = self::$cfg['emulate'];

        if(empty(self::$service_url)) return NULL;

        $sendPhone = self::sanitizeMobileNumber($phone);
        if (empty($sendPhone)) {
            if (self::$verbose) echo "sanitize number for $phone returned BAD [$sendPhone]<br>";
            if (self::$debug) writeDebugInfo("bad phone number after sanitizing, sms aborted: src:[$phone] sanitized:[$sendPhone], return FALSE");
            return FALSE;
        }

        if (self::$emulate) { # реальную посылку не делаю, эмуляция!
            $result = "OK\r\n\r\n". (string)(time() - strtotime('2020-08-01'));
            if(self::$debug || \AppEnv::isLocalEnv()) writeDebugInfo("emulate SMS send to $phone: [$text], result = $result");
        }
        else { # not emualte
            if (empty(self::$service_url)) {
                if (self::$debug) writeDebugInfo("Empty config value service_url, SMS not sent");
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
                    if(self::$debug) writeDebugInfo("metatype:[$meta], login base:[$acc]");
                }
            }
            $params = [];
            $login = self::$cfg['login'] ?? '';
            $password = self::$cfg['password'] ?? '';
            $apiKey = self::$cfg['apiKey'] ?? '';
            if(!empty($acc) && !empty(self::$cfg['login-'.$acc]) || !empty(self::$cfg['apiKey-'.$acc])) {
                $login = self::$cfg['login-'.$acc] ?? '';
                $password = self::$cfg['password-'.$acc] ?? '';
                $apiKey = self::$cfg['apiKey-'.$acc] ?? '';
                if(self::$debug) writeDebugInfo("Взят СМС apiKey/логин-пароль для [$acc]: apiKey=[$apiKey] / login:[$login] / password:[$password]");
            }

            $headers = [];

            if(!empty($apiKey)) {
                $params = [ 'apiKey' => $apiKey ]; # не надо, если передача в HTTP-хэдерах ?
                $headers['X-ApiKey'] = $apiKey;
            }
            else
                $params = ['user' => $login, 'pass' => $password];

            $params['action'] = 'post_sms';
            if(!empty(self::$cfg['sender_name'])) $params['sender'] = self::$cfg['sender_name'];
            $params['message'] = $text;
            $params['target'] = '+7'.$sendPhone;

             # '7'.$sendPhone; # по требованию сервиса - форма 7xxxXXXXXXX ?

            $url = self::$service_url;
            $result = \Curla::getFromUrl($url,$params,20,$headers,2);
            $errNo = \Curla::getErrNo();
            $errMsg = \Curla::getErrMessage();

            if (self::$debug > 1)
                @file_put_contents("applogs/_sms_beeline.log", "JSON result: $result, errNo[$errNo], errMsg:[$errMsg]\n", FILE_APPEND);
        } # not emualte
        $decoded = @json_decode($result, TRUE);

        if(self::$debug) writeDebugInfo("result from sms service: $result, decoded from JSON: ", $decoded);
        if(!empty($decoded['agt_id'])) {
            $ret = TRUE;
            $saveResult = 'OK';
        }
        else {
            $ret = FALSE;
            $saveResult = 'FAIL';
        }

        $resultCode = $decoded['agt_id'] ?? '';
        if (self::$emulate) $resultCode = 'emul-'.$resultCode;

        # if (!self::$emulate) {
        $ipaddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'NONE';
        $smsDta = [
            'sessionid' => $hash,
            'client_phone' => $sendPhone,
            'account' => ( empty($acc) ? "std:$login" : "$acc:$login" ), # заношу какой учеткой пользовался
            'ipaddr' => $ipaddr,
            'smstext' => $text,
            'send_result' => $saveResult,
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
}
