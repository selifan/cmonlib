<?PHP
/**
* @name class.currencyrates.php
* Load currency rates for RUR, RTS/MOEX cross-rates, FINAM BA rates, getting rates from db
* @Author Alexander Selifonov, <alex [at] selifan {dot} ru>
* @copyright Alexander Selifonov, <alex [at] selifan {dot} ru>
* @version 1.24.002 2025-08-29
* @Link: http://github.com/selifan
* @license http://www.opensource.org/licenses/bsd-license.php    BSD
* changelog: 1.10 added finam.ru stock rates load
* changelog: 1.11 new finam executing URI, from parameter added
**/
class CurrencyRates {
    const VERSION = '1.22.061';
    const ERR_INTERNET_CONNECTION = 1000;
    const ERR_READING_ERROR = 1001;
    const ERR_LOAD_URL_ERROR = 1002;
    const ERR_PROXY_CONNECT = 1003;
    const ERR_WRONG_CONTENT_DATA = 1010;
    const ERR_NOT_XML_RESPONSE = 1050;
    static $debug = 0;
    static $verbose = 0;
    public static $currencyHist = ''; # table name for saving ALL actions log
    static $proxy = array('addr'=>'','port'=>'','auth'=>'','login'=>'','password'=>'', 'timeout'=>30);
    static $useProxy = FALSE;
    static $timeout = 30; # таймаут для работы curl_*
    static $curlobj = null;
    static $ratetable = 'currates';
    static $ratelist = array('USD','EUR');
    static $lastRequestedDate = '';
    static $returnedDate = '';
    static $_errorcode = 0;
    static $_errormessage = '';
    static $_error_subcode = 0;
    static $LF = '<br>';
    static $curl_errormessage = '';
    static $testRates = []; # для имитации загрузки из Inet
    const URL_CBR = 'http://www.cbr.ru/scripts/XML_daily.asp'; # ?date_req=DD.MM.YYYY - for specific date

    const URL_RTS_FORTS = 'https://www.moex.com/export/derivatives/currency-rate.aspx?language=ru&currency=USD_RUB&moment_start=%date1%&moment_end=%date2%';
    const RTS_use_proxy = false;
    # https://www.moex.com/export/derivatives/currency-rate.aspx?language=ru&currency=USD_RUB&moment_start=2018-06-01&moment_end=2018-06-04
    #'http://www.rts.ru/export/xml/forts-usd-rate.aspx?moment_start=%date1%&moment_end=%date2%'; # dates: YYYY-MM-DD
    # 17.01.2014 : rbc.ru БОЛЬШЕ НЕ РАБОТАЕТ (с 19.10.2013) ! новый - moex.com

    const URL_FINAM_EXPORT = 'https://export.finam.ru/'; # action addr, Web-форма: http://www.finam.ru/analysis/export/default.asp
    const URL_FINAM_REFERER = 'https://www.finam.ru/profile/moex-akcii/gazprom/export/';
    # c 22/10/2016 перестали грузиться курсы, 11.11.2016 - обнаружен новый адрес http://export.finam.ru/export9.out !!!
#    const URL_EXPORT_FINAM = 'http://www.finam.ru/analysis/profile041CA00007/default.asp';
# Full load string (with parameters, checked: 09.11.2016):
# http://export.finam.ru/output.txt?market=1&em=16842&code=GAZP&apply=0&df=8&mf=10&yf=2016&from=08.11.2016 ...
#  &dt=8&mt=10&yt=2016&to=08.11.2016&p=8&f=output&e=.txt&cn=GAZP&dtf=1&tmf=1 ...
#  &MSOR=1&mstime=on&mstimever=1&sep=1&sep2=1&datf=1&at=1
    const CURRATES_DEBUG = 0;
    static $curl_error_codes = [
        1 => 'CURLE_UNSUPPORTED_PROTOCOL',
        2 => 'CURLE_FAILED_INIT',
        3 => 'CURLE_URL_MALFORMAT',
        4 => 'CURLE_URL_MALFORMAT_USER',
        5 => 'CURLE_COULDNT_RESOLVE_PROXY',
        6 => 'CURLE_COULDNT_RESOLVE_HOST',
        7 => 'CURLE_COULDNT_CONNECT',
        8 => 'CURLE_FTP_WEIRD_SERVER_REPLY',
        9 => 'CURLE_REMOTE_ACCESS_DENIED',
        11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
        13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
        14 => 'CURLE_FTP_WEIRD_227_FORMAT',
        15 => 'CURLE_FTP_CANT_GET_HOST',
        17 => 'CURLE_FTP_COULDNT_SET_TYPE',
        18 => 'CURLE_PARTIAL_FILE',
        19 => 'CURLE_FTP_COULDNT_RETR_FILE',
        21 => 'CURLE_QUOTE_ERROR',
        22 => 'CURLE_HTTP_RETURNED_ERROR',
        23 => 'CURLE_WRITE_ERROR',
        25 => 'CURLE_UPLOAD_FAILED',
        26 => 'CURLE_READ_ERROR',
        27 => 'CURLE_OUT_OF_MEMORY',
        28 => 'CURLE_OPERATION_TIMEDOUT',
        30 => 'CURLE_FTP_PORT_FAILED',
        31 => 'CURLE_FTP_COULDNT_USE_REST',
        33 => 'CURLE_RANGE_ERROR',
        34 => 'CURLE_HTTP_POST_ERROR',
        35 => 'CURLE_SSL_CONNECT_ERROR',
        36 => 'CURLE_BAD_DOWNLOAD_RESUME',
        37 => 'CURLE_FILE_COULDNT_READ_FILE',
        38 => 'CURLE_LDAP_CANNOT_BIND',
        39 => 'CURLE_LDAP_SEARCH_FAILED',
        41 => 'CURLE_FUNCTION_NOT_FOUND',
        42 => 'CURLE_ABORTED_BY_CALLBACK',
        43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
        45 => 'CURLE_INTERFACE_FAILED',
        47 => 'CURLE_TOO_MANY_REDIRECTS',
        48 => 'CURLE_UNKNOWN_TELNET_OPTION',
        49 => 'CURLE_TELNET_OPTION_SYNTAX',
        51 => 'CURLE_PEER_FAILED_VERIFICATION',
        52 => 'CURLE_GOT_NOTHING',
        53 => 'CURLE_SSL_ENGINE_NOTFOUND',
        54 => 'CURLE_SSL_ENGINE_SETFAILED',
        55 => 'CURLE_SEND_ERROR',
        56 => 'CURLE_RECV_ERROR',
        58 => 'CURLE_SSL_CERTPROBLEM',
        59 => 'CURLE_SSL_CIPHER',
        60 => 'CURLE_SSL_CACERT',
        61 => 'CURLE_BAD_CONTENT_ENCODING',
        62 => 'CURLE_LDAP_INVALID_URL',
        63 => 'CURLE_FILESIZE_EXCEEDED',
        64 => 'CURLE_USE_SSL_FAILED',
        65 => 'CURLE_SEND_FAIL_REWIND',
        66 => 'CURLE_SSL_ENGINE_INITFAILED',
        67 => 'CURLE_LOGIN_DENIED',
        68 => 'CURLE_TFTP_NOTFOUND',
        69 => 'CURLE_TFTP_PERM',
        70 => 'CURLE_REMOTE_DISK_FULL',
        71 => 'CURLE_TFTP_ILLEGAL',
        72 => 'CURLE_TFTP_UNKNOWNID',
        73 => 'CURLE_REMOTE_FILE_EXISTS',
        74 => 'CURLE_TFTP_NOSUCHUSER',
        75 => 'CURLE_CONV_FAILED',
        76 => 'CURLE_CONV_REQD',
        77 => 'CURLE_SSL_CACERT_BADFILE',
        78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
        79 => 'CURLE_SSH',
        80 => 'CURLE_SSL_SHUTDOWN_FAILED',
        81 => 'CURLE_AGAIN',
        82 => 'CURLE_SSL_CRL_BADFILE',
        83 => 'CURLE_SSL_ISSUER_ERROR',
        84 => 'CURLE_FTP_PRET_FAILED',
        84 => 'CURLE_FTP_PRET_FAILED',
        85 => 'CURLE_RTSP_CSEQ_ERROR',
        86 => 'CURLE_RTSP_SESSION_ERROR',
        87 => 'CURLE_FTP_BAD_FILE_LIST',
        88 => 'CURLE_CHUNK_FAILED'
    ];
    # returns module version. @since 1.23.061
    public static function getVersion() {
        return self::VERSION;
    }
    public static function setDebug($deb = true) { self::$debug = $deb; }

    /**
    * Set working options, including proxy parameters
    *
    * @param mixed $options
    * @param mixed $proxy 'addr' - proxy address, 'port' - port number, 'auth' - auth type (0-no auth),'login','password' - auth params
    */
    public static function setOptions($options, $proxy=false) {
        if(isset($options['ratestable'])) self::$ratetable = trim($options['ratestable']);
        if(isset($options['ratelist']) && is_array($options['ratelist'])) {
            self::$ratelist = $options['ratelist'];
        }
        if(isset($options['debug'])) self::$debug = $options['debug'];
        if(is_array($proxy)) {
            if(!empty($proxy['addr'])) self::$proxy['addr'] = trim($proxy['addr']);
            if(!empty($proxy['port'])) self::$proxy['port'] = trim($proxy['port']);
            if(!empty($proxy['auth'])) self::$proxy['auth'] = trim($proxy['auth']);
            if(!empty($proxy['login'])) self::$proxy['login'] = trim($proxy['login']);
            if(!empty($proxy['password'])) self::$proxy['password'] = trim($proxy['password']);
            if(isset($proxy['timeout'])) self::$timeout = intval($proxy['timeout']);
            if (!empty(self::$proxy['addr']) && self::$verbose)
                echo "Will use proxy: ".self::$proxy['addr'] . " " .self::$proxy['port'] . "<br>\n";
        }
        self::$LF = (isset($_SERVER['REMOTE_ADDR'])) ? '<br>':"\r\n";
    }

    public static function GetRequestedDate() {
        return self::$lastRequestedDate;
    }
    public static function getErrorCode() { return self::$_errorcode; }

    /**
    * готовлю к вызовам curl_*
    *  $use_proxy - использовать ли прокси. Если false, не использовать даже если есть прокси-настройки!
    */
    private static function _InitCurl($use_proxy = false) {
        self:$useProxy = FALSE;
        if(!function_exists('curl_init')) {
            die('Curl extension not enabled: <br>uncomment <b>extension=php_curl.dll</b> in Your php.ini !');
        }
        self::$curlobj = curl_init();
        curl_setopt(self::$curlobj, CURLOPT_FOLLOWLOCATION, true);
        # echo "proxy:".self::$proxy['addr'] . "use proxy:[$use_proxy]<br>"; exit;
        if(!empty(self::$proxy['addr']) && $use_proxy) {
            self::$useProxy = TRUE;
            # curl_setopt(self::$curlobj, CURLOPT_SSL_FALSESTART, TRUE); # test
            # curl_setopt(self::$curlobj, CURLOPT_HTTPPROXYTUNNEL, TRUE); # не катит!
            curl_setopt(self::$curlobj, CURLOPT_PROXY, self::$proxy['addr']);
            if(self::$debug) writeDebugInfo("proxy addr: ", self::$proxy['addr']);
            if(!empty(self::$proxy['port'])) {
                curl_setopt(self::$curlobj, CURLOPT_PROXYPORT, self::$proxy['port']);
                if(self::$debug) writeDebugInfo("proxy port: ", self::$proxy['port']);
            }
            if(!empty(self::$proxy['auth'])) {
                # curl_setopt(self::$curlobj, CURLOPT_HTTP_VERSION , CURL_HTTP_VERSION_1_1);
                curl_setopt(self::$curlobj, CURLOPT_PROXYAUTH,  self::$proxy['auth']); # CURLAUTH_BASIC | CURLAUTH_NTLM
                if(self::$debug) writeDebugInfo("proxy auth: ", self::$proxy['auth'] . '/'.self::decodeProxyAuth(self::$proxy['auth']) );

                if(!empty(self::$proxy['login'])) {
                    $loginpass = self::$proxy['login'] . ':' . (isset(self::$proxy['password'])?self::$proxy['password']:'');
                    # if(self::$proxy['auth'] == CURLAUTH_NTLM) $loginpass = base64_encode($loginpass);
                    curl_setopt(self::$curlobj, CURLOPT_PROXYUSERPWD, $loginpass);
                    if(self::$debug) writeDebugInfo("proxy login:passw: ", $loginpass);
                }
            }
            if (self::$verbose) {
                echo '_InitCurl: proxy params set.<pre>'. print_r(self::$proxy,1).'</pre>';
            }

        }
        else {
            if (self::$verbose) echo "_InitCurl: No proxy used<br>\n";
        }
        curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(self::$curlobj, CURLOPT_SSL_VERIFYHOST, false);

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
           curl_setopt(self::$curlobj, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        curl_setopt(self::$curlobj, CURLOPT_CONNECTTIMEOUT, self::$timeout); # seconds waiting for response
        curl_setopt(self::$curlobj, CURLOPT_TIMEOUT, 2*self::$timeout);

        if (self::$timeout>0) {
            if(self::$verbose) echo "CURL timeout set (seconds):".self::$timeout.self::$LF;
        }
    }
    public static function getErrorMessage() {
        if (!empty(self::$_errormessage)) return self::$_errormessage;
        switch(self::$_errorcode) {
            case self::ERR_INTERNET_CONNECTION: return 'No internet connection to resource';
            case self::ERR_READING_ERROR: return 'Loading XML from CBR error';
            case self::ERR_PROXY_CONNECT: return 'Proxy Authentication Failed';
            case self::ERR_NOT_XML_RESPONSE: return 'Not XML string loaded';
            case self::ERR_LOAD_URL_ERROR: return 'Loading URL error ' . (self::$_error_subcode ? self::$_error_subcode:'');
        }
        if(!empty(self::$curl_errormessage)) return self::$curl_errormessage;
        return 'undefined error :'.self::$_errorcode;
    }
    /**
    *  Loads currency rates from Internet and stores to local MySQL table
    * @param mixed $date
    * {upd/2023-04-19} не делаю обновлений уже загруженного ранее курса,
    *   не делаю лишних записей для выходных, когда используется курс от предыд.дней
    * TODO: проверить работу!
    */
    public static function LoadRates($date='') {
      # global $as_dbengine;
      $cbdate = $date;

      $data = self::GetRatesFromInet($date, $cbdate);
      if(!is_array($data)) {
          if (self::$debug) WriteDebugInfo('GetRatesFromInet returned no data :', self::getErrorMessage());
          return false; # 'No rates loaded: ' . self::getErrorMessage();
      }
      if(self::$verbose) echo "rates returned for $cbdate".self::$LF;
      if(!$date && intval($cbdate)) $date = $cbdate;
      self::$lastRequestedDate = $date;
      self::$returnedDate = $cbdate;

      $realdt = to_date(self::$returnedDate);

      $logstr = '';

      if (self::$debug) writeDebugInfo("realdt: $realdt, data: ", $data);

      if(empty(self::$ratetable)) return $data;
      $ret = '';
      foreach($data as $curcode=>$value) { //<1>
        if($value>0)  { //<2>
          $logstr .= ", $curcode=$value";
          $curdt= $date ? to_date($date) : date('Y-m-d');
          if($realdt < $curdt) {
              # курс на запрошенную дату недоступен, прислала на прошлую. Если такой уже есть - не заносим
              $chkDate = $realdt;
          }
          else $chkDate = $curdt;
          $cnt = 0;
          $existRec = appEnv::$db->select(self::$ratetable, ['where'=> ['curcode'=>$curcode,'curdate'=>$chkDate], 'singlerow'=>1]);
          $recid = isset($existRec['recid']) ? $existRec['recid'] : FALSE;
          $qry = '';
          if($recid>0) {
              if(floatval($existRec['currate']) != $value) { # only if changed value, need update
                  $qry = 'UPDATE '.self::$ratetable . " SET currate=$value, updated=NOW() WHERE recid='$recid'";
                  $logstr .= "(updated)";
              }
              else $logstr .= "(skipped)";
          }
          else {
              $qry = 'INSERT INTO ' .self::$ratetable ." (curcode,curdate,currate,updated) VALUES('$curcode','$chkDate',$value,NOW())";
              $logstr .= "(added)";
          }
          if (self::$verbose) echo ("$curcode/$chkDate currates record seek for update:[$recid], query to update/add: ".$qry . self::$LF);
          if(!empty($qry) && !self::CURRATES_DEBUG) { //<3>
             $updResult = appEnv::$db->sql_query($qry);
             $err =  appEnv::$db->sql_error();
             # $ret .= "$qry = RESULT: [$updResult], err:$err<br>";

             if(self::$debug || !empty($err)) writeDebugInfo("$qry \n   result: [$updResult] ". $err);
             if($err) $ret .= "Ошибка при заведении записи по валюте :$err<br>SQL: $qry<br>";
             if(!$recid) $recid = $updResult;
             if(!empty(self::$currencyHist)) {
                 # save act to history
                 AppEnv::$db->insert(self::$currencyHist, ['curcode'=>$curcode, 'curdate'=>$chkDate, 'currate'=>$value, 'loaddate'=>'{now}']);
                 $err = AppEnv::$db->sql_error();
                 if($err) writeDebugInfo("save curr in history error: ", $err);
             }
             $cnt = appEnv::$db->affected_rows();
             if (self::$verbose) echo ("query : $qry ".self::$LF. "Affected rows: [$cnt], sql error (if any): ".appEnv::$db->sql_error().self::$LF);
             # echo "$curcode = $value - курс обновлен, ($cnt)<br>$err\n<br>";
          } //<3>
          # else echo "debug, SQL for rate update: $qry".self::$LF;
        } //<2>
      } //<1>

      if (is_callable("WebApp::logEvent")) WebApp::logEvent('RATES LOAD', "Currency rates from Internet load for $date");
      elseif (function_exists('WriteAuditEvent')) WriteAuditEvent('RATES LOAD', "Currency rates from Internet load for $date");
      $realdt = to_char($realdt);
      $dateDmy = to_char($date);
      $ret .= "Loading currency rates for [$dateDmy] (rates returned for [$realdt]) $logstr<br>";
      return $ret;
    }

    public static function GetRatesFromInet($dateval, &$realdate, $proxy_on = false) {
        if (self::$debug) WriteDebugInfo("GetRatesFromInet start, date=[$dateval]");
        if(isset(self::$testRates['date'])) {
            # test call
            $realdate = self::$testRates['date'];
            # echo "Fake GetRatesFromInet, using test values for $realdate !<br><pre>".print_r(self::$testRates,1).'</pre>';
            return [
              'USD'=> (self::$testRates['USD'] ?? 65),
              'EUR'=> (self::$testRates['EUR'] ?? 75),
            ];
        }

        if(intval($dateval)>1000) {
            if(function_exists('to_char')) $dateval = to_char($dateval); # from "YYYY-MM-DD" to "MM.DD.YYYY"
            else {
                $dspl = explode('-',$dateval);
                $dateval = $dspl[2] . '.' . $dspl[1] . '.' . $dspl[0];
            }
        }
        # {upd/2024-05-17} using configurable currency list
        if(method_exists('AppEnv', 'getCurrencyList')) {
            self::$ratelist = AppEnv::getCurrencyList();
        }

        $rateUrl = self::URL_CBR . (intval($dateval) ? "?date_req=$dateval" : '');
        if (self::$verbose) echo "URL for getting rates from cbr: $rateUrl, proxy:[$proxy_on]<br>\n"; # debug
        self::_InitCurl($proxy_on);
        curl_setopt(self::$curlobj, CURLOPT_URL, $rateUrl);

        if (self::$debug) WriteDebugInfo("calling URL: $rateUrl");

        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, 1);
        # curl_setopt($this->curlobj, CURLOPT_REFERER, 'http://referer_url/');
        $content = @curl_exec(self::$curlobj);
        if(self::$debug>1 && !empty($content)) @file_put_contents('tmp/rates.xml', $content); # debug 2023-11-20

        if(/* self::$useProxy && */ stripos($content, 'you have authenticated yourself')) {
            # response from proxy when proxy auth failed
            $ret = FALSE;
            self::$_errorcode = self::ERR_PROXY_CONNECT;
            return $ret;
        }

        $ret = [];

        if (self::$debug) WriteDebugInfo('CURL for CBR result:'. $content);
        if (self::$verbose) echo('response from CBR:'. self::$LF . $content . self::$LF);

        if ($err=curl_errno(self::$curlobj) || empty($content) ) {
            self::$_errorcode = self::ERR_LOAD_URL_ERROR;
            self::$_error_subcode = $err;
            $errDesc = self::$curl_errormessage = isset(self::$curl_error_codes[$err]) ? self::$curl_error_codes[$err] : '';
            if (self::$verbose) echo "CURL return error (or empty response), err code=$err / $errDesc<br>\n";
            if (self::$debug) WriteDebugInfo("CURL return error (or empty response), err code=$err / $errDesc");
            curl_close(self::$curlobj);
            return false;
        }

        # $content = @file_get_contents($rateUrl);

        if(self::$verbose) {
            echo "CBR request URL: ".$rateUrl .'<br>';
            if(isset($_SERVER['REMOTE_ADDR']))
                 echo('CBR response:<pre>' . htmlspecialchars( $content, ENT_COMPAT, 'WINDOWS-1251',false ) . '</pre>');
            else echo("CBR response:\r\n $content \r\n</pre>");
        }
        # file_put_contents('xml-valuta.xml', $content); # debug
        # $debugXML = './XML_daily.xml'; $content = file_get_contents(dirname(__FILE__) . $debugXML); # debug read instead CBR request

        $dtstart = stripos($content,'Date="');
        if($dtstart) $realdate = substr($content,$dtstart+6,10); # реальная дата, на которую ЦБ вернул список курсов
        self::$lastRequestedDate = $realdate;
        if(self::$debug>1) file_put_contents('rawresult-currates.xml',$content);
        if(self::$debug) writeDebugInfo("raw response from cbr: ", $content);
        $xml = @simplexml_load_string($content);
        if(is_object($xml)) {
            foreach($xml->children() as $child) {
                if(isset($child->CharCode)) {
                    $curcode = (string) $child->CharCode;
                    $Nominal = (string) $child->Nominal; # todo: save Nominal too ?
                    $value = str_replace(',', '.', (string) $child->Value);
                    if($Nominal > 1) $value = round($value / $Nominal, 6);
                    if(in_array($curcode, self::$ratelist)) $ret[$curcode] = $value;
                }
            }
            return $ret;
        }
        else {
            $errFile = '_cbr_bad_response.xml';
            if(is_dir('./tmp')) $errFile = "./tmp/" . $errFile;
            @file_put_contents($errFile, $content);
            self::$_errorcode = self::ERR_READING_ERROR;
            if(!isset($_SERVER['REMOTE_ADDR'])) echo ("Non-XML body returned (parse failed)\r\n");
            return false;
        }

    } // GetRatesFromInet()
    public static function getCurlMessage() {
        return self::$curl_errormessage;
    }
    # {upd/2023-12-15} - доработана с учетом новых алгоритмов сбора курсов валют (есть пропуски за выходные)
    # {upd/2025-08-29} - если сдуру запросили курс для рубля - всегда возвращаю 1.0
    public static function GetRates($date='', $curcode='', $nearestDate=true) {
        if($curcode === 'RUR' || $curcode==='RUB') return 1;
        $dt = intval($date) ? to_date($date) : date('Y-m-d');

        if($nearestDate)
            $conds = [ "curdate<='$dt'" ];
        else
            $conds = ['curdate'=>$dt ];

        if(!empty($curcode) ) {
            $conds["curcode"] = $curcode;
            $result = AppEnv::$db->select(self::$ratetable, ['fields'=>'currate', 'where'=>$conds, 'orderby'=>'curdate DESC','singlerow'=>1]);
            if(isset($result['currate'])) return floatval($result['currate']);
            return FALSE;
        }
        # валюта не указана, хотят все на дату
        # appEnv::$db->log(2);
        $curlist = AppEnv::$db->select(self::$ratetable, ['fields'=>'curcode', 'distinct'=>1,'associative'=>0]);
        if(!is_array($curlist) || !count($curlist)) return FALSE;
        $arRet = [];
        foreach($curlist as $currency) {
            $conds["curcode"] = $currency;
            $result = AppEnv::$db->select(self::$ratetable, ['fields'=>'currate', 'where'=>$conds, 'orderby'=>'curdate DESC','singlerow'=>1]);
            if(!empty($result['currate'])) $arRet[$currency] = floatval($result['currate']);
        }
        return $arRet;
    }
    public function createDbObjects() {

      $sql = "CREATE TABLE ".self::$ratetable . " ( `recid` bigint(20) NOT NULL auto_increment,"
       . "`curcode` char(3) NOT NULL default '', `curdate` date NOT NULL default '0000-00-00', "
       . "`currate` decimal(12,6) NOT NULL default 0.0, `updated` datetime NOT NULL default 0, PRIMARY KEY (`recid`) ) ENGINE=MyISAM";
      appEnv::$db->sql_query($sql);
    }

    /**
    * Получаем курс USD по версии РБК-Фортс
    *
    * @param mixed $datefrom - дата начала запрашиваемого диапазона
    * @param mixed $dateto - дата конца диапазона
    * #return ассоц.массив 'дата' => значение_курса ЛИБО 0/false
    */
    public static function getRateRTSForts($datefrom,$dateto='', $verbose = 0) {

        self::_InitCurl(self::RTS_use_proxy);

        if(intval($datefrom)<32) $datefrom = to_date($datefrom);
        $dateto = empty($dateto)? $datefrom : $dateto;
        if(intval($dateto)<32) $dateto = to_date($dateto);
        $url = str_replace(array('%date1%','%date2%'), array($datefrom,$dateto), self::URL_RTS_FORTS);
        if(self::$debug) {
            WriteDebugInfo('RTS rates URL:', $url);
        }
        if ($verbose) {
            echo "<pre>ULR for RTS: $url<br></pre>\r\n";
        }

        curl_setopt(self::$curlobj, CURLOPT_URL, $url);
        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, 1);
#        curl_setopt($this->curlobj, CURLOPT_REFERER, 'http://referer_url/');
        $content = curl_exec(self::$curlobj);
        if ($verbose) echo "curl return: <pre>" . htmlentities($content) . "</pre>";
        if ($err=curl_errno(self::$curlobj)) {
            self::$_errorcode = self::ERR_LOAD_URL_ERROR;
            self::$_error_subcode = $err;
            self::$_errormessage = self::decodeCurlError($err);
            if ($verbose) echo ("curl ERROR: " . $err . ' (' .self::$_errormessage . ')<br>');

            curl_close(self::$curlobj);
            return false;
        }
        if(self::$debug) WriteDebugInfo("curl response: " . $content);

        curl_close(self::$curlobj);

        if(strlen($content)< 2) {
            self::$_errormessage = 'Returned content has zero length';
            return 0;
        }

        $ret = array();
        if(self::$debug) {
            echo '<pre>cross curs content:<br>'; echo htmlentities($content, ENT_COMPAT , 'windows-1251'); echo '</pre>';
            if (self::$debug>1) file_put_contents("rts-cross-$datefrom-$dateto.xml", $content);
        } # debug

        $xml = @simplexml_load_string($content);

        if ($verbose > 1) 'Parsed xml:<pre>'.print_r($xml,1) . '</pre>';

        if(isset($xml->rates)) foreach($xml->rates->children() as $key=>$item) {
            $dt = isset($item['moment']) ? (string)$item['moment'] : '';
            $ratevalue = isset($item['value']) ? (float)$item['value'] : 0;
            $onlydate = substr($dt,0,10);
            if(!empty($onlydate) && $ratevalue>0 && !isset($ret[$onlydate])) {
                $ret[$onlydate] = $ratevalue;
            }
        }
        else {
            self::$_errorcode = self::ERR_NOT_XML_RESPONSE;
            self::$_errormessage = 'Ответ от сервиса не в XML формате, либо формат изменен';
        }
        unset($xml);
#        echo 'ret: <pre>'.print_r($ret,1) . '</pre>';
/*
        # 08.2017 - поменяли формат с XML на HTML, пришлось парсить HTML :(
        # каждое значение курса на дату приходит 2 раза, на разное время: вечер, потом утро(2017-08-15 18:30:00, 2017-08-15 13:45:00)
        $cutPos = stripos($content, '<table style="border: solid 1px #666666; border-collapse:collapse;">');
        if ($cutPos <=0) {
            self::$_errorcode = self::ERR_WRONG_CONTENT_DATA;
            self::$_errormessage = "Returned HTML code does not contain rate data";
            return 0;
        }
        $content = substr($content, $cutPos);
        $DOM = new DOMDocument();
        $DOM->loadHTML($content);
        $aDataTableHeaderHTML = array();

        $Header = $DOM->getElementsByTagName('th');
        $Detail = $DOM->getElementsByTagName('td');

        foreach($Header as $NodeHeader)
        {
            $aDataTableHeaderHTML[] = trim($NodeHeader->textContent);
        }
        $i = 0;
        $j = 0;
        foreach($Detail as $sNodeDetail)
        {
            $parsedTable[$j][] = trim($sNodeDetail->textContent);
            $i = $i + 1;
            $j = $i % count($aDataTableHeaderHTML) == 0 ? $j + 1 : $j;
        }
        foreach($parsedTable as $rowrate) {
            if (!empty($rowrate[1]) && !empty($rowrate[2]) && floatval($rowrate[2])>0) {
                $rdate = substr($rowrate[1],0,10); # "YYYY-MM-DD"
                if (!isset($ret[$rdate])) $ret[$rdate] = floatval($rowrate[2]);
            }
        }
*/
        # нормальный порядок дат (в исходном файле - обратный):
        $ret = array_reverse($ret);

        return $ret;
    }

    /**
    * Проверяет наличие подключения с заданными параметрами прокси
    *
    * @param mixed $url адрес для проверки связи, по умолч. - лезет на google.com
    * @param mixed $returnContent если не ноль, вместо TRUE вернет HTML код данного URL, в случае успеха коннекта
    */
    public static function testInternetConnect($url = '', $returnContent=false) {
        self::_InitCurl();
        if(empty($url)) $url = 'http://www.google.com';
        curl_setopt(self::$curlobj, CURLOPT_URL, $url);
        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, 1);
        # curl_setopt($this->curlobj, CURLOPT_REFERER, 'http://referer_url/');
        $content = @curl_exec(self::$curlobj);
        if ($err=curl_errno(self::$curlobj)) {
            self::$_errorcode = self::ERR_INTERNET_CONNECTION;
            self::$_error_subcode = $err;
            curl_close(self::$curlobj);
            return false;
        }
        curl_close(self::$curlobj);
        return ($returnContent ? $content : TRUE);
    }

    /**
    * Получаем котировки фонда с сайта finam.ru
    * NOT IMPLEMENTED YET !
    * @param mixed $active  код актива
    * @param mixed $datefrom дата начала периода 'YYYY-MM-DD'
    * @param mixed $dateto конец периода 'YYYY-MM-DD'
    * @param mixed $keytime по какому времени брать величину ('18:45:00')
    * form elements:
    * market = 6
    * em   = 95
    * code = RTSI
    * дата-с: df (31), mf(0) yf (2013)
    * дата-по : dt, mt, yt
    * 'p'=>5 (time period, 1min=2, 5min='3', 10мин=4, 15мин=5 ... 1час=7)
    * 'f'='RTSI_130110_130110' имя вых.файла (на форме сочиняется автоматом по коду актива и датам 1-2
    * 'e' = расшир-е файла '.txt' | '.csv'
    * 'cn'=имя контракта = 'GAZP' (имени кода актива)
    * 'dtf'=формат даты : 2 = ГГММДД  3=ддммгг 4=дд/мм/гг "5"=мм/дд/гг
    * 'tmf'=формат времени : 1=ччммсс 2=ччмм 3=чч:мм:сс 4=чч:мм
    * выдать время MSOR=0 начала свечи, 1=окончания свечи
    * mstimever=1 (московское время всегда)
    * 'sep' - символ разделитель 4=табуляция
    * sep2 - разд.разрядов
    * 'datf' = '4'  4='TICKER, PER, DATE, TIME, CLOSE'
    * at=1 (добавить заголовок)
    */
/*  Мировые индексы, RTSI:
http://195.128.78.52/RTSI_130110_130110.txt?market=6&em=95&code=RTSI&df=10&mf=0&yf=2013&dt=10&mt=0
  &yt=2013&p=5&f=RTSI_130110_130110&e=.txt&cn=RTSI&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
<TICKER>    <PER>    <DATE>    <TIME>    <OPEN>    <HIGH>    <LOW>    <CLOSE>
Мировые рынки/RTSI: (РТС)
http://195.128.78.52/RTSI_130104_130105.txt?market=6&em=95&code=RTSI&df=4&mf=0&yf=2013&dt=5&mt=0&yt=2013&p=7&f=RTSI_130104_130105&e=.txt&cn=RTSI&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
http://195.128.78.52/RTSI_121210_121211.txt?market=6&em=95&code=RTSI&df=10&mf=11&yf=2012&dt=11&mt=11&yt=2012&p=7&f=RTSI_121210_121211&e=.txt&cn=RTSI&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
товары-Золото:
http://195.128.78.52/comex.GC_121201_121210.txt?market=24&em=18953&code=comex.GC&df=1&mf=11&yf=2012&dt=10&mt=11&yt=2012&p=4&f=comex.GC_121201_121210&e=.txt&cn=comex.GC&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
товары-Brent (нефть):
http://195.128.78.52/ICE.BRN_121201_121210.txt?market=24&em=19473&code=ICE.BRN&df=1&mf=11&yf=2012&dt=10&mt=11&yt=2012&p=4&f=ICE.BRN_121201_121210&e=.txt&cn=ICE.BRN&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
ММВБ-Top/Сбербанк:
http://195.128.78.52/SBER_121201_121210.txt?market=200&em=3&code=SBER&df=1&mf=11&yf=2012&dt=10&mt=11&yt=2012&p=4&f=SBER_121201_121210&e=.txt&cn=SBER&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
http://195.128.78.52/SBER_121201_121210.txt?market=200&em=3&code=SBER&df=1&mf=11&yf=2012&dt=10&mt=11&yt=2012&p=7&f=SBER_121201_121210&e=.txt&cn=SBER&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=2&at=1
10 мин - http://195.128.78.52/SBER_121201_121210.txt?market=200&em=3&code=SBER&df=1&mf=11&yf=2012&dt=10&mt=11&yt=2012&p=4&f=SBER_121201_121210&e=.txt&cn=SBER&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
1 мин -  http://195.128.78.52/SBER_121201_121210.txt?market=200&em=3&code=SBER&df=1&mf=11&yf=2012&dt=10&mt=11&yt=2012&p=2&f=SBER_121201_121210&e=.txt&cn=SBER&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1
RTSI    15    20130110    10:00    1573.5000000    1574.2700000    1571.2000000    1574.2700000
RTSI    15    20130110    10:15    1574.2900000    1575.1200000    1573.3800000    1573.4000000
...
RTSI    15    20130110    18:45    1572.0000000    1572.0000000    1572.0000000    1572.0000000
*/
    public static function getRatesFinam($market,$em, $active,$datefrom,$dateto='',$keytime='18:45') {
        # TODO: implement function !
        self::$_errorcode = 0;
        self::_InitCurl();
        if(intval($datefrom)<32) $datefrom = to_date($datefrom);
        $dateto = empty($dateto)? date('Y-m-d') : $dateto;
        if(intval($dateto)<32) $dateto = to_date($dateto);
        $url = self::URL_FINAM_EXPORT;
        $d1 = preg_split("/[\s,-\/\.\:]+/",$datefrom);
        $d2 = preg_split("/[\s,-\/\.\:]+/",$dateto);
        $tmint = 5; # интервал между временами строк
        $mytime = explode(':',$keytime);
        $mins = isset($mytime[1]) ? intval($mytime[1]) : 0;
        if($mins==0) $tmint = 7;
        elseif($mins % 15 == 0) $tmint = 5;
        elseif($mins % 10 == 0) $tmint = 4;
        elseif($mins % 5 == 0) $tmint = 3;
        else $tmint = 2;
        # WriteDebugInfo("getRatesFinam: $market/$em/$active/$datefrom/$dateto/$keytime, request interval code: $tmint");
        # $url .= "MYFILE.TXT?market={$market}&em={$em}&code={$active}&cn={$active}&df=$d1[2]&mf=$d1[1]&yf=$d1[0]&p=5&f=MYFILE&ext=.txt&datf=4&at=1&sep=4&mstimever=1&tmf=4" #  параметры запроса - в GET строку
        #          . "&dt=$d2[2]&mt=$d2[1]&yt=$d2[0]";
        $m1 = $d1[1]-1; # месяц 0-based!
        $m2 = $d2[1]-1;
         # debug
         # $url = 'http://195.128.78.52/RTSI_130110_130110.txt?market=6&em=95&code=RTSI&df=10&mf=0&yf=2013&dt=10&mt=0&yt=2013&p=5&f=RTSI_130110_130110&e=.txt&cn=RTSI&dtf=1&tmf=4&MSOR=0&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1';
        $fromDMY = (intval($datefrom)>1000) ? to_char($datefrom) : $datefrom;

        $url = self::URL_FINAM_EXPORT
          . "{$active}_load.txt?market={$market}&em={$em}&code={$active}&df=$d1[2]&mf=$m1"
          . "&yf=$d1[0]&from=$fromDMY&dt=$d2[2]&mt=$m2&yt=$d2[0]&p={$tmint}&f={$active}_load&e=.txt"
          . "&cn={$active}&dtf=1&tmf=4&MSOR=1&mstime=on&mstimever=1&sep=4&sep2=1&datf=4&at=1";

        if(self::CURRATES_DEBUG OR self::$debug) {
            echo "request Url:<br>$url<br>"; # debug
            WriteDebugInfo('finam rates request URL: ',$url);
        }
        curl_setopt(self::$curlobj, CURLOPT_URL, $url);
        curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt(self::$curlobj, CURLOPT_REFERER, self::URL_FINAM_REFERER);
        # WriteDebugInfo("request Finam url: $url");
        $content = curl_exec(self::$curlobj);
        # if($content) echo ('result OK,'. substr($content,0,30));
        if ($err=curl_errno(self::$curlobj)) {
            self::$_errorcode = self::ERR_LOAD_URL_ERROR;
            self::$_error_subcode = $err;
            self::$_errormessage = curl_error(self::$curlobj);
            curl_close(self::$curlobj);
            if(self::CURRATES_DEBUG>1 || (self::$debug)) echo 'Getting FINAM error:' . self::decodeCurlError() .' / '. self::$_errormessage . " \n<br>";
            return false;
        }

        if( self::$debug ) {
            file_put_contents('_finam_rates_'.$active."-$datefrom-$dateto.log",$content);
            if(isset($_SERVER['REMOTE_ADDR'])) echo ("response size: ".strlen($content).'<br>');
        }

        curl_close(self::$curlobj);
        $lines = preg_split("/[\\n\\r]+/", $content);
        $ret = array();
        if(is_array($lines)) foreach($lines as $ln) {
            $items = explode("\t", $ln);
            if(count($items)>3 && substr($items[3],0,strlen($keytime))==$keytime)
              $ret[] = array(
                 substr($items[2],0,4).'-'.substr($items[2],4,2).'-'.substr($items[2],6,2)
                ,floatval($items[4])
              );
        }
        if(self::$debug) WriteDebugInfo("return array (keytime:$keytime): ",$ret);
        return $ret;
    }

    public static function decodeCurlError($code=null) {
        if($code===null) $code = self::$_error_subcode;
        switch($code) {
            case 1: return 'CURLE_UNSUPPORTED_PROTOCOL';
            case 2: return 'CURLE_FAILED_INIT';
            case 3: return 'CURLE_URL_MALFORMAT';
            case 4: return 'CURLE_NOT_BUILT_IN';
            case 5: return 'CURLE_COULDNT_RESOLVE_PROXY';
            case 6: return 'CURLE_COULDNT_RESOLVE_HOST';
            case 7: return 'CURLE_COULDNT_CONNECT';
            case 8: return 'CURLE_FTP_WEIRD_SERVER_REPLY';
            case 9: return 'CURLE_REMOTE_ACCESS_DENIED';
            case 10: return 'CURLE_FTP_ACCEPT_FAILED';
            case 26: return '26/CURLE_READ_ERROR';
            case 28: return '28/CURLE_OPERATION_TIMEDOUT';
            case 34: return '34/CURLE_HTTP_POST_ERROR';
            case 35: return '35/CURLE_SSL_CONNECT_ERROR';
        }
        return ($code . ' : ' . curl_error(self::$curlobj));
    }
    public static function setTestRates($date, $rateUsd, $rateEur) {
        if(!\PlcUtils::isDateValue($date)) return 'Ошмбка вызова: Неверная дата';
        $yyymmdd = to_date($date);
        self::$testRates = ['date'=>$yyymmdd, 'USD'=> $rateUsd, 'EUR'=>$rateEur];
        # echo "setTestRates: now testRates:<pre>".print_r(self::$testRates,1).'</pre>';
        return TRUE;
    }
    public static function decodeProxyAuth($code) {
        switch($code) {
            case CURLAUTH_BASIC: return 'CURLAUTH_BASIC';
            case CURLAUTH_DIGEST: return 'CURLAUTH_DIGEST';
            case CURLAUTH_GSSNEGOTIATE: return 'CURLAUTH_GSSNEGOTIATE';
            case CURLAUTH_NTLM: return 'CURLAUTH_NTLM';
            case CURLAUTH_AWS_SIGV4: return 'CURLAUTH_AWS_SIGV4';
            case CURLAUTH_ANY: return 'CURLAUTH_ANY';
        }
        return $code;
    }

    /**
    * {upd/2024-02-02} загрузка БА с ресурса cbonds - https://cbonds.ru/api
    * гонкогская биржа для Эволюции НГ - https://cbonds.ru/etf/109/ : "last_price.numeric":15.66,...
    * @param mixed $url URL JSON сервиса
    * @param mixed $login логин доступа к API
    * @param mixed $password
    * @param mixed $arParams массив кодов для запроса
    * @param mixed $datefrom запрашивать начиная с даты
    * @param mixed $dateto и до даты вкл.
    */
    public static function getRatesCbonds($url, $login, $password, $arParams, $datefrom='', $dateto='') {
        $arRet = [];
        if(empty($datefrom)) $datefrom = date('Y-m-d', strtotime("-1 days"));
        if(empty($dateto)) $dateto = max($datefrom, date('Y-m-d', strtotime("-1 days")));

        $params = [
          'date'=>$datefrom,
          'code'=> $arParams[0], # TODO - допилить под описание API!
          'login' => $login,
          'password' => $password,
        ];
        if(class_exists('Curla')) {
            $headers = FALSE;
            $asJson = TRUE;
            $timeout = 10;
            $data = Curla::getFromUrl($url, $params, $timeout, $headers, $asJson);
        }
        else {
            # TODO: реализовать!
            self::_InitCurl();
            if(intval($datefrom)<32) $datefrom = to_date($datefrom);
            $dateto = empty($dateto)? date('Y-m-d') : $dateto;
            if(intval($dateto)<32) $dateto = to_date($dateto);
            curl_setopt(self::$curlobj, CURLOPT_URL, $url);
            curl_setopt(self::$curlobj, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt(self::$curlobj, CURLOPT_REFERER, self::URL_FINAM_REFERER);
            # WriteDebugInfo("request Finam url: $url");
            $content = curl_exec(self::$curlobj);
        }
        return $arRet;
    }
}
