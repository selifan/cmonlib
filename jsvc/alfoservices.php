<?php
/**
* @package ALFO
* @name jsvc/alfoservices.php
* ALFO JSON API
* @version 1.17.001 2025-06-02
* Обертки для вызова через API методов расчета, сохранения полиса, получения PDF форм из модулей ALFO
* Системные меж-серверные запросы - справочники, курсы валют и т.п.
*/
class AlfoServices {

    const TABLE_API_CLIENTS = 'alf_apiclients';
    const CLIENT_ID = 'client';
    const ERR_WRONG_FNCTION = '400';
    const ERR_WRONG_PARAMETERS = '500';
    const ERR_USRTOKEN_NOT_PASSED = '900';
    const ERR_USRTOKEN_WRONG = '1000';
    const ERR_USRTOKEN_EXPIRED = '1001';
    const ERR_USRTOKEN_NOTACTIVE = '1002';
    const ERR_WRONG_IP_ADDRESS = '1003';
    const ERR_WRONG_UID = '1004';
    const ERR_NO_CLIENT_ACCOUNT = '1005';
    const ERR_NOFUNCTION = '1006';
    const ERR_WRONG_MODULE = '1100';
    const CURRATES_LIMIT = 100; # лимит на одноразовое кол-во передаваемых курсов валют

    static $SYSTEM_TOKEN = 'sys_0327834687';
    static $SYSTEM_USERID= '1';
    static $FREE_USER_ID = -1000; # userID, назначаемый при вызове без токена
    static $freeAccessFunc = ['decodePolicyQrCode', ]; # ф-ции не требующие userToken

    static private $hideFuncs = ['init','setRawRequest','getRawRequest','prepareBaseParams'];
    protected $_rawRequest = '';
    static $encodeResponse = FALSE; // кодировать ли ответ с помощью utf8_encode()
    static $busCall = FALSE; # станет TRUE если запрос пришел от шины ( init() )
    static $autoDeleteTmpfiles = TRUE; # авто-удаление временных файлов (PDF ...)
    static $debug = 0; # включение отладочного вывода в файлы
    static $verbose = 0; # включение отладочного вывода на экран
    static $logCommands = 0; # 1|TRUE = все переданные вызовы с параметрами логировать в _debuginfo.log
    private $plgBackend = null; // будет занесена ссылка на бакенд нужного плагина
    static $module = '';
    # функции конвертации параметров перед вызовами ф-ций в опр.модулях
    static $callMap = [ #
       'trmig' => [
         'prepareParameters' => 'trmigMakeParams' # конвертация параметров для модуля мигрантов
       ],
    ];
    # маппинг имен функций - из "внешнего" (вызванного метода в API) во внутренний в нужном модуле
    static $funcMap = [
        'trmig' => [ # плагин Страхование мигрантов (ДМС)
          'getPdfBill' => 'printBill',
          'getPdfAgreement' => 'print_dog',
        ],
        'investprod' => [ # ИСЖ
          'createPolicy'   => 'apiCreatePolicy', # создание проекта договора
          'updatePolicy'   => 'apiUpdatePolicy', # изменение данных
          'setAgreementPayed' => 'apiSetPayed',   # отметка об оплате
          'cancelPolicy'   => 'apiSetCanceled',   # аннуляция
          'getPdfPolicy' => 'apiPrintPack', # получить PDF с полисом (пакет документов)
        ],
        '-all-' => [ # годится для всех страховых плагинов
          'setAgreementPayed' => 'setPayed',
          'getPdfPolicy' => 'print_pack',
          'getAvailablePrograms' => 'getAvailablePrograms',
          'getProgramSubtypes' => 'getProgramSubtypes',
          # 'getpdfStatement'   => 'print_stmt',
        ]

    ];
    # Маппинг полей API в поля для работы в модулях ALFO
    static $fieldMap = [
      'policyParams' => [
        'programId' => 'module',
        'equalInsured' => 'equalinsured',
        'startDate' => 'datefrom',
        'clientId' => 'extclientid',
      ],

      'person' => [
        'type' => 'type',
        'lastName' => 'fam',
        'firstName' => 'imia',
        'middleName' => 'otch',
        'birth' => 'birth',
        'sex' => 'sex',
        'rezCountry' => ['rez_country', 114], # из сервиса страну не ждем, всегда Россия (ИД = 114)
        'birthCountry' => 'birth_country' , # ['birth_country', 'Москва'], # место рожд- всегда Москва
        'inn' => 'inn',
        'ogrn' => 'ogrn',
        'docType' => 'doctype',
        'docSer' => 'docser',
        'docNo' => 'docno',
        'docPodr' => 'docpodr',
        'docDate' => 'docdate',
        'docIssued' => 'docissued',
        'inoPass' => 'inopass',
        'otherDocno' => 'otherdocno',
        'migсardSer' => 'migcard_ser',
        'migсardNo' => 'migcard_no',
        'docFrom' => 'docfrom',
        'docTill' => 'doctill',
        'married' => 'married',
        # 'phonePref' => 'phonepref',
        'phone' => 'phone',
        'email' => 'email',
        'sameAddress' => 'sameaddr',
      ],
      'address' => [
         'zipCode' => 'adr_zip',
         'country' => 'adr_country',
         'region' => 'adr_region',
         'area' => 'adr_raion',
         'city' => 'adr_city',
         'street' => 'adr_street',
         'house' => 'adr_house',
         'corp' => 'adr_corp',
         'build' => 'adr_build',
         'flat' => 'adr_flat',
      ]
    ];

    # маппинг полей, специфических для конкретных продуктов
    static $fieldMapProd = [
        'invonline' => [ # Инвест Онлайн
           'warranty' => ['warranty', '100'], # гарантия, значение по умолч, если не передали
           'baseactive' => ['baseactive', '1'], # Базовый актив, значение по умолч, если не передали
        ],

        'trmig' => [ # ДМС стр.мигрантов
          'inn' => 'inn',
          'policyid' => 'stmt_id', # обновление ранее созданного, если передан
          'vakcina' => ['vakcina', '0'],
          'old_insured' => ['old_insured','0'],
          'ph_type' => ['insurer_type', 2],
          'ph_ulname' => 'insrurname',
          'ph_inn' => 'insrurinn',
          'ph_ogrn' => 'insrogrn',
          'ph_kpp' => 'insrkpp',
          'ph_bankname' => 'ul_bankname',
          'ph_bankbik' => 'ul_bankbik',
          'ph_bankrs' => 'ul_bankrs',
          'ph_bankks' => 'ul_bankks',
          'ph_docser' => 'insrdocser',
          'ph_docno' => 'insrdocno',
          'ph_docdate' => 'insrdocdate',
          'ph_docissued' => 'insrdocissued',
          'ph_phone' => 'insrphone',
          'ph_phone2' => 'insrphone2',
          'ph_email' => 'insremail',
          'ph_addressreg'  => 'insradr_full',
          'ph_sameaddr' => 'insrsameaddr',
          'ph_addressfact' => 'insrfadr_full',
          'ph_headduty' => 'ul_head_duty',
          'ph_headname' => 'ul_head_name',
          'ph_headbase' => 'ul_osnovanie',
          'contact_name' => 'ul_contact_fio',
          'contact_birth' => 'ul_contact_birth',
          'contact_sex' => 'ul_contact_sex',
          'contact_address' => 'ul_contact_address',
          'contact_phone' => 'ul_contact_phone',
          'contact_email' => 'ul_contact_email',
          'paydocno' => 'platno', # при простанове оплаты- номер квитанции/плат.документа
          'payedsum' => 'pay_rur', # при простанове сумма премии в рублях
          'ikp_agent' => 'ikp_agent',
          'ikp_curator' => 'ikp_curator',
        ]
    ];

    public static $svcLastError = '';

    # Маппинг файлов, обрабатывающих запросы для отдельных плагинов
    static $moduleMappings = [
      'trmig' => 'jsvc_trmig',
      'imutual' => 'jsvc_imutual',
      'boxprod' => 'jsvc_boxprod',
      # 'madms' => 'jsvc_dms', # TODO: jsvc_dms.php - можно сделать один общий модуль-обработчик запросов на ВСЕ ДМС плагины
      # ...
    ];
    # запоминаю "сырой" блок данных из запроса (json)
    public function setRawRequest($strg) {
        $this->_rawRequest = $strg;
    }
    # получить "сырой" блок данных из запроса (json)
    public function getRawRequest() {
        return $this->_rawRequest;
    }

    /**
     * Returns error string
     *
     * @return string
     */
    public function getError() {
        return self::$svcLastError;
    }
    /**
    * Перегоняет параметры из принятых от инициатора API вызова в родной формат для нужного модуля
    *
    * @param mixed $result (by ref) массив куда заносить
    * @param mixed $module ИД модуля/продукта
    * @param mixed $source массив принятых из API данных
    */
    public static function prepareBaseParams(&$result, $module, $source) {
        self::$module = $module;
        if( !isset(self::$fieldMapProd[$module]) ) return;
        foreach(self::$fieldMapProd[$module] as $web => $fld) {
            $outname = is_array($fld) ? $fld[0] : $fld;
            $default = (is_array($fld) && count($fld)>1) ? $fld[1] : NULL;
            if (isset($source[$web])) {
                $result[$outname] = $source[$web];
                # writeDebugInfo("$web -> $outname = ",$source[$web]);
            }
            else {
                if ($default!==NULL) {
                    $result[$outname] = $default;
                    # writeDebugInfo("$web -> $outname = default:[$default]");
                }
                # else writeDebugInfo("$web/$outname not passed, no value");
            }
        }
    }
    /**
    * преобраз-е параметров из асс.массива к виду, нужному в модуле trmig
    * @param mixed $orig исходные параметры
    * @return array - подготовленные для вызова в модуле
    */
    public static function trmigMakeParams($orig) {
        if (self::$debug) writeDebugInfo("orig params: ", $orig);
        $ret = [
            'datefrom' => (isset($orig['params']['datefrom']) ? to_date($orig['params']['datefrom']) : ''),
            'datetill' => (isset($orig['params']['datetill']) ? to_date($orig['params']['datetill']) : ''),
        ];
        self::prepareBaseParams($ret, 'trmig', $orig['params']);
        /*
        foreach(self::$fieldMapProd['trmig'] as $web => $fld) {
            $outname = is_array($fld) ? $fld[0] : $fld;
            $default = isset($fld[1]) ? $fld[1] : NULL;
            if (isset($orig['params'][$web]) && is_scalar($orig['params'][$web]) )
                $ret[$outname] = $orig['params'][$web];
            elseif($default!==NULL) $ret[$outname] = $default;
        }
        */
        if (isset($orig['params']['insuredlist']) && is_array($orig['params']['insuredlist'])) {
            foreach($orig['params']['insuredlist'] as $item) {
                $ret['insdbirth'][] = isset($item['birth'])? $item['birth'] : '';
                $ret['insdlastname'][] = isset($item['lastname'])? $item['lastname'] : '';
                $ret['insdfirstname'][] = isset($item['firstname'])? $item['firstname'] : '';
                $ret['insdmidname'][] = isset($item['midname'])? $item['midname'] : '';
                $ret['insdfullname_lat'][] = isset($item['fullname_lat'])? $item['fullname_lat'] : '';
                $ret['insdsex'][] = isset($item['sex'])? $item['sex'] : 'M';
                $ret['insdrez_country'][] = isset($item['rez_country'])? $item['rez_country'] : '';
                $ret['insdinn'][] = isset($item['inn'])? $item['inn'] : '';
                $ret['insddocser'][] = isset($item['docser'])? $item['docser'] : '';
                $ret['insddocno'][] = isset($item['docno'])? $item['docno'] : '';
                $ret['insddocdate'][] = isset($item['docdate'])? $item['docdate'] : '';
                $ret['insddocissued'][] = isset($item['docissued'])? $item['docissued'] : '';
                $ret['insdmigcard_ser'][] = isset($item['migcard_ser'])? $item['migcard_ser'] : '';
                $ret['insdmigcard_no'][] = isset($item['migcard_no'])? $item['migcard_no'] : '';
                $ret['insddocfrom'][] = isset($item['docfrom'])? $item['docfrom'] : '';
                $ret['insddoctill'][] = isset($item['doctill'])? $item['doctill'] : '';
                $ret['insdphone'][] = isset($item['phone'])? $item['phone'] : '';
                $ret['insdemail'][] = isset($item['email'])? $item['email'] : '';
                $ret['insdaddress'][] = isset($item['address'])? $item['address'] : '';
                $ret['insdwork_duty'][] = isset($item['work_duty'])? $item['work_duty'] : '';
                $ret['insdwork_company'][] = isset($item['work_company'])? $item['work_company'] : '';
            }
        }
        return $ret;
    }
    /**
    * Общая точка входа - исполнение всех ф-ций
    *
    * @param array $params
    */
    public function execute($params) {
        $execFunc = isset($params['execFunc']) ? $params['execFunc'] : '';
        $serialize = $params['serialize'] ?? $params['params']['serialize'] ?? FALSE;
        # writeDebugInfo("$execFunc, serialize=[$serialize]");
        if (is_file(__DIR__ . '/_debug.flag')) self::$debug = 1;
        else self::$debug = AppEnv::getConfigValue('z_debug_jsvc');

        # if (is_file(__DIR__ . '/_verbose.flag')) self::$verbose = 1;
        if (self::$logCommands) writeDebugInfo("execute params: ", $params);
        if (self::$debug>=2) {
            PlcUtils::$debug = 1;
            PolicyModel::$debug = 1;
            FileUtils::$debug = 1;
            error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);

            if (self::$debug) writeDebugInfo("KT1 - start execute/$execFunc, params ", $params);
        }
        if (self::$verbose) echo 'start execute/$execFunc, params; <pre>' . print_r($params,1). '</pre>';
        $token = $params['userToken'];
        $uid = isset($params['uid']) ? $params['uid'] : FALSE;
        $module = $params['module'] ?? $params['params']['module'] ?? '';

        if(self::$debug) writeDebugInfo("module from params : [$module], self:module: ", self::$module);
        if(empty($module)) $module = self::$module;
        $result = $this->init($token, $uid, $module, $execFunc);
        $executed = FALSE;

        if (empty($execFunc)) {
            $result = [
                'result' => 'ERROR',
                'errorCode' => self::ERR_NOFUNCTION,
                'message' => 'Не указана вызываемая функция/команда (execFunc)',
            ];
            $executed = TRUE;
        }

        if(!empty(self::$moduleMappings[$module])) {
            # $handlerFile = __DIR__ . '/' . self::$moduleMappings[$module];
            $handlerClass = "\\jsvc\\" . self::$moduleMappings[$module];
            $jsvcHandler = new $handlerClass;
            $result = $jsvcHandler->executeRequest($params);
            $executed = TRUE;
            # writeDebugInfo("result from called $module : ", $result);
            # return $result;
        }
        if (self::$verbose) echo "$module/initialization result <pre>" . print_r($result,1). '</pre>';

        if (is_array($result) && !empty($result['errorCode']))
            return $result; # ERROR detected

        if(!$executed) {
            # запрос из справочника стран
            if(strtolower($execFunc) === 'getcountrylist') {
                $data = PlcUtils::getCountryList();
                $result = ['result'=>'OK', 'data' => $data ];
                if($serialize) $result['data'] = self::serializeData($result['data'], $serialize);

                return $result;
            }
            elseif(!empty(self::$funcMap[$module][$execFunc])) # маппинг для конкр. модуля
                $execFunc = self::$funcMap[$module][$execFunc];
            elseif(!empty(self::$funcMap['-all-'][$execFunc])) # общий маппинг для всех модулей
                $execFunc = self::$funcMap['-all-'][$execFunc];

            if (self::$verbose) echo "$module/function to call : $execFunc<br>";

            $result = [
              'result' => 'OK',
              'message' => 'инициализация прошла' #, модуль класса: ', get_class($this->plgBackend),
            ];
            if (!empty($module) && !empty(self::$callMap[$module]['prepareParameters'])) {
                # перегоняю значения параметров из "внешего" формата во внутренний ALFO-шный
                $prepareFunc = self::$callMap[$module]['prepareParameters'];
                $funcParams = $this->$prepareFunc($params);
            }
            else $funcParams = is_array($params) ? $params : AppEnv::$_p;

            if(self::$debug) writeDebugInfo("prepared parameters: ", $funcParams);

            if (empty($module) && method_exists($this, $execFunc)) {
                # функция общего типа, не из плагинов, декларирована здесь же
                $result = $this->$execFunc($funcParams);
                # writeDebugInfo("called $execFunc with params: ", $funcParams);
            }
            elseif (method_exists($this->plgBackend, $execFunc)) {
                $funcParams['plg'] = $module;
                appEnv::$_p = $funcParams;
                ob_start();
                $result = $this->plgBackend->$execFunc($funcParams);
                $echoed = ob_get_flush();
                if ($echoed !=='') writeDebugInfo("calling service was echoed with: ", $echoed);
                if (self::$verbose) echo "KT50: $execFunc result <pre>" . print_r($result,1). '</pre>';;

                if (!empty($result['filepath'])) {
                    # пришла ссылка на сгенер. файл (PDF) 0 перегнать в BASE64
                    if (is_file($result['filepath'])) {
                        $result['filebody'] = base64_encode(file_get_contents($result['filepath']));
                        if (!self::$debug) @unlink($result['filepath']); # файл больше не нужен, сразу удалить
                        unset($result['filepath']);
                    }
                }
            }
            else {
                $result = ['result'=>'ERROR', 'errorCode' => self::ERR_WRONG_FNCTION, 'message'=>'Запрос неизвестного метода '.$execFunc, 'params'=>$funcParams];
                if (self::$verbose) echo "KT51: $execFunc - undefined func in module<br>";
            }
        }

        # {upd/2024-01-11} - для отд.случаев может понадобиться "плоский" JSON, тогда result перевожу в строку
        $serialize = $params['serialize'] ?? $params['params']['serialize'] ?? FALSE;
        if($serialize) {
            if(is_array($result) && !empty($result['data']) && is_array($result['data'])) {
                $result['data'] = self::serializeData($result['data'], $serialize);
            }
            elseif(is_object($result) && !empty($result->data) && is_array($result->data)) {
                $result->data = self::serializeData($result->data, $serialize);
            }
        }

        if(self::$debug) writeDebugInfo("KT50 - execution $execFunc result: ", $result, " trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));

        return $result;
    }
    /**
    * превращаю массив в строку
    * @param mixed $arData ассоц.массив преобразуемых данных
    * @param mixed $method пока не используется. Для будущих задач (разные способы сериализации)
    */
    public static function serializeData($arData, $method = 1) {
        $arRet = [];
        $innerDelim = "\f"; # разделитель значений в одной строке данных
        $outerDelim = "\t"; # между строками
        $keyValDelim = "\x0B"; # разделитель key - value
        # $innerDelim = "{!}"; $outerDelim = "[/]"; # между строками
        foreach($arData as $rowid=>$row) {
            if($method == 1) {
                if(is_array($row)) $arRet[] = implode($innerDelim, $row);
                else $arRet[] = (string)$row;
            }
            elseif($method == 2) { # с именами полей
                if(is_array($row)) {
                    $itemRow = [];
                    foreach($row as $key => $value) {
                        $itemRow[] = $key . $keyValDelim . $value;
                    }
                    $arRet[] = implode($innerDelim, $itemRow);
                }
                else $arRet[] = (string)$row;

            }
        }
        return implode($outerDelim, $arRet);
    }
    /**
    * Инициализация:
    * Определяю учетку и ее права по переданному токену и Uid
    * получаю бакенд для модуля нужного продукта
    * @param string $userToken токен пользователя API
    * @param string $Uid ИД учетки в ALFO (необяз)
    * @param string $module плагин(страховой продукт) - необязательно
    * @param string $function имя вызываемой ф-ции/метода
    */
    private function init($userToken, $Uid = '', $module='', $function = '') {
        if (self::$debug) WriteDebugInfo("init($userToken, ($Uid), module='$module', func=$function)");
        include_once(__DIR__ . '/../svc/alfo_dataclasses.php');
        /*
        $busAddr = explode(',', appEnv::getConfigValue('bus_ip_address'));
        $pluginId = $module;
        if (count($busAddr)>0 && !empty($busAddr[0])) {
            self::$busCall = false;
            foreach($busAddr as $busIp) {
                if ($busIp === '*' || $_SERVER['REMOTE_ADDR'] === $busIp) {
                    self::$busCall = TRUE;
                    break;
                }
            }
        }
        */
        $usr = [ 'userid'=>$Uid ]; // По умолчанию считаю, что пришел запрос с сайта, где клиент сам оформляет себе полис
        if (TRUE) { # !self::$busCall
            # WriteDebugInfo("not a BUS call");
            # с пустым токеном можно вызывать только особые ф-ции без авторизации, их список self::$freeAccessFunc
            if (empty($userToken)) {
                if (self::$debug) WriteDebugInfo("API request w/o token from " . $_SERVER['REMOTE_ADDR']);
                if(!empty($function) && in_array($function, self::$freeAccessFunc)) {
                    # для вызовов некоторых ф-ций токен можно не передавать
                    $userid = self::$FREE_USER_ID;
                    appEnv::setApiCall($userid);
                    $userToken = self::$SYSTEM_TOKEN;
                }
                else return [ 'result' => 'ERROR',
                    'errorCode' => self::ERR_USRTOKEN_NOT_PASSED,
                    'message' => 'User Token not passed'
                ];
                # throw  new SoapFault(self::ERR_USRTOKEN_NOT_PASSED, "User Token not passed, your IP: ".$_SERVER['REMOTE_ADDR']);
            }
            if ($userToken === self::$SYSTEM_TOKEN) {
                # системный токен, все запросы включая служебные
                $usr = [ 'userid'=>self::$SYSTEM_USERID, 'active_from'=>0, 'active_till'=>0, 'system'=>1 ];
            }
            else {
                # передан userToken, определяю
                $usr = appEnv::$db->select(self::TABLE_API_CLIENTS, [
                  'where' => ['usertoken'=>$userToken],
                  'singlerow' => 1
                ]);
            }
            if (!isset($usr['userid'])) {
                sleep(2); // защита от подбора токена (брутфорс)
                return [ 'result' => 'ERROR',
                    'errorCode' => self::ERR_USRTOKEN_WRONG,
                    'message' => 'Wrong User Token'
                ];
                ## throw  new SoapFault(self::ERR_USRTOKEN_WRONG, "Wrong User Token");
            }
            if(intval($usr['active_till'])>0 && $usr['active_till']<date('Y-m-d'))
                return [ 'result' => 'ERROR',
                    'errorCode' => self::ERR_USRTOKEN_EXPIRED,
                    'message' => 'User Token expired'
                ];

            if(intval($usr['active_from'])>0 && $usr['active_from']>date('Y-m-d'))
                return [ 'result' => 'ERROR',
                    'errorCode' => self::ERR_USRTOKEN_NOTACTIVE,
                    'message' => 'User Token is not active yet'
                ];

            if (!empty($usr['ip_addresses'])) {
                $validIp = preg_split( '/[,; ]/', $usr['ip_addresses'], -1, PREG_SPLIT_NO_EMPTY );
                if (!in_array($_SERVER['REMOTE_ADDR'],$validIp)) {
                    return [ 'result' => 'ERROR',
                        'errorCode' => self::ERR_WRONG_IP_ADDRESS,
                        'message' => 'Your IP address is not allowed'
                    ];
                }
            }
        }
        //...
        # WriteDebugInfo("final user id: $usr[userid]");
        $userid = (empty($Uid) ? $usr['userid'] : $Uid);
        if (self::$debug) WriteDebugInfo("API: active userid : ",$userid);
        if ($userid === self::CLIENT_ID) {
            $userid = appEnv::getConfigValue('account_clientplc'); # appEnv::$client_userid;
            if (intval($userid)<=0) {
                if (self::$debug) WriteDebugInfo("Настройте ИД учетки для API-вызовов от клиента - account_clientplc!");
                return [ 'result' => 'ERROR',
                    'errorCode' => self::ERR_NO_CLIENT_ACCOUNT,
                    'message' => 'Нет настроек ID для стандартного клиента'
                ];
            }
            appEnv::setClientCall($userid);
            # WriteDebugInfo("userid for CLIENT:", $userid);
        }
        if (empty($usr['system'])) {
            $result = appEnv::$auth->getMyInfo($userid);
            if (self::$debug) WriteDebugInfo("auth->getmyinfo, found DeptId for $userid:", appEnv::$auth->deptid);
            # WriteDebugInfo("connected result: userid", appEnv::$auth->userid, "deptid:", appEnv::$auth->deptid, " rights:", appEnv::$auth->a_rights);
            if (empty(appEnv::$auth->deptid)) {
                return ['result'=>'ERROR', 'errorCode' => self::ERR_WRONG_UID, 'message'=>"Wrong User Id passed"];
            }
        }
        appEnv::setApiCall($userid);

        $bkend = FALSE;

        if (!empty($module)) {
            $module = trim($module);
            if (!isset(appEnv::$_plugins[$module])) {
                include_once(__DIR__ . '/mapper.php');
                $bkend = ProdMap::getEngine($module);
                if ($bkend) {
                    if (is_string($bkend) && isset(appEnv::$_plugins[$bkend])) {
                        $pluginId = $bkend;
                        $bkend = appEnv::$_plugins[$pluginId]->getBackend();
                    }
                    elseif (is_array($bkend) && !empty($bkend['result']) && $bkend['result'] ==='ERROR')
                        return $bkend;
                    elseif(is_object($bkend)) {
                        if (self::$debug) WriteDebugInfo("$module: подключен класс из insengines: ".get_class($bkend));
                    }
                }
                else {
                    return [
                       'result' => 'ERROR', 'errorCode' => self::ERR_WRONG_MODULE,
                       'message' => "Указан неизвестный продукт: $module"
                    ];
                }
            }
            else {
                $bkend = appEnv::getPluginBackend($module);
            }
        }

        if (is_object($bkend)) {
            $this->plgBackend = $bkend;
            appEnv::$_p['plg'] = $module; # TODO: если вызван из insengines, plg будет равен проджукту - Endowment... -
            if (self::$debug) WriteDebugInfo("$module backend found: ", get_class($this->plgBackend));
        }

        return $userid;
    }

    /**
     * Getting generated binary file (PDF, XLSX...)
     * Returns FileResponse structure
     *
     * @pw_element string $params
     * @param string $params
     * @return FileResponse file name and body OR error message
     */
    public function getBinaryFile($params) {
        $ret = new FileResponse;
        $ret->result = '1';
        $ret->fileName='test.txt';
        $ret->body = base64_encode("Это простой тестовый файл\r\nСтрока от юзера:$params"); # base64_encode - вызовет сам phpwsdl!
        return $ret;
    }

    /**
    * @pw_set nillable=false $params is NOT null
    * @pw_element AuthByPhoneParams $params
    * @return stringArray
    */
    function AuthByPhone($params) {

        include_once(__DIR__ . '/clientV7/helper.php');

        $ret = new StdResponse();
        $phone = isset($params->MobilePhone) ? $params->MobilePhone : null;
        if (!$phone) {
            $ret->result = 'ERROR';
            $ret->message = 'Нет данных';
        } else {
            $userId = Helper::getIdByPhone($phone);
            if ($userId === false) {
                $ret->result = 'ERROR';
                $ret->message = 'Пользователь не найден';
            } else {
                $helper = new Helper($userId);
                $data = [];

                $this->init(null, $userId);
                $data['Uid'] = $userId;

                $role = $helper->getRole();
                if ($role === false) {
                    $ret->result = 'ERROR';
                    $ret->message = 'Ошибка доступа';
                } else {
                    $data['UserProfile'] = $helper->getUserProfile();
                    $data['Role'] = $helper->getRole();
                    $data['UserClients'] = $helper->getClients();

                    $ret->result = 'OK';
                    $ret->data = $data;
                }
            }
        }
        return $ret;
    }


    /**
    * @pw_set nillable=false $params is NOT null
    * @pw_element GetApplNumbParams $params
    * @return stringArray
    */
    function GetApplNumb($params) {
        $uid = isset($params->Uid) ? $params->Uid : '';
        $module = $params['module'] ?? 'none';
        $deptid = $params['deptid'] ?? 0;
        if(!$deptid && $uid > 0) $deptid = \AppEnv::$db->select(\PM::T_USERS, ['where'=>['usrid'=>$uid],
          'fields' => 'deptid', 'singlerow'=>1, 'associative'=>0]
        );
        $codir = $prodCode = isset($params->ProductType) ? $params->ProductType : '';
        if (isset(appEnv::$_plugins[$prodCode])) {
            // передан ИД плагина, беру первую известную кодировку, если есть
            $codes = PolicyModel::getProductsCodes($prodCode);
            if (is_array(PolicyModel::$plc_plugins[$prodCode]['subtypes']))
                $codir = PolicyModel::$plc_plugins[$prodCode]['subtypes'][0];
        }
        $nextNo = \NumberProvide::getNext($codir, $deptid, $module);
        $ret = new StdResponse();
        if ( $nextNo >0 ) {
            $data = ['Uid' => $uid, 'ProductType' => $prodCode, 'Application_number' => $nextNo];
            $ret->result = 'OK';
            $ret->data = $data;
        }
        else {
            $ret->result = 'ERROR';
            $ret->message = $this->getError();;
            #$ret['error'] = $this->getError();
            # $ret['message'] = appEnv::getSvcLastError();
        }
        return $ret;
    }

    /**
     * Получить PDF файл с полисом
     *
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $programId
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @param string $programId ID страхового продукта
     * @pw_element string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @param string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @return StdResponse result
     */
    public function getPolicyPdf($userToken, $Uid, $programId, $policyId){
        # $userToken = isset($params->userToken) ? $params->userToken : '';
        $connected = $this->init($userToken, $Uid, $programId);

        $mode = ''; # draft - должен автоматом понять как печатать!
        appEnv::$_p['id'] = $policyId;
        # chdir(ALFO_ROOT);
        $bkRet = $this->plgBackend->print_pack();

        $ret = new StdResponse('OK',
            'Файл получен в виде BASE64 кодированной строки'
        );
        if (isset($bkRet['result'])) $ret->result = $bkRet['result'];
        if (isset($bkRet['message'])) $ret->message = $bkRet['message'];
        if (isset($bkRet['filepath'])) {
            $ret->data['filename'] = basename($bkRet['filepath']);
            $ret->data['filebody'] = base64_encode(file_get_contents($bkRet['filepath']));
            if (self::$autoDeleteTmpfiles) @unlink($bkRet['filepath']);
        }
        return $ret;
    }

    /**
     * Получить список полисов
     * (список передаваемых полей - стандартный)
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @pw_element string $programId
     * @param string $programId ID страхового продукта
     * @pw_element string $clientId ИД клиента, на которого оформлены полисы
     * @param string $clientId ИД клиента, на которого оформлены полисы
     * @return PolicyListResponse result
     */
    public function getPolicyList($userToken, $Uid, $programId, $clientId){
        # $userToken = isset($params->userToken) ? $params->userToken : '';
        $connected = $this->init($userToken, $Uid);
        $ret = new PolicyListResponse;
        if (appEnv::isClientCall()) {
            $ret->result = 'ERROR';
            $ret->message = 'Вызов для неавторизованного клиента неприменим';
            return $ret;
        }

        $where = [ 'userid' => appEnv::$auth->userid, 'module' => trim($programId) ];
        $orderBy = 'stmt_id'; # TODO: позволить передавать пользователский порядок сортировки
        $plcFields = 'stmt_id policyid,policyno,insurer_fullname policyHolderName,insured_fullname insuredName,datefrom, datetill, policy_prem policyPremium,datepay datePay, stateid';
        if ($clientId) $where['extclientid'] = $clientId;
        $dta = appEnv::$db->select('alf_agreements', [
          'where' => $where,
          'fields' => $plcFields,
          'orderby' => $orderBy
        ]);

        if (is_array($dta)) {
            $ret->result = 'OK';
            foreach($dta as &$row) {
                $row['datefrom'] = to_char($row['datefrom']);
                $row['datetill'] = to_char($row['datetill']);
                $row['datePay'] = intval($row['datePay']) ? to_char($row['datePay']) : '';
            }
            $ret->data = $dta;
        }
        else {
            $ret->result = 'ERROR';
            $ret->message = 'Нет данных';
        }
        return $ret;
    }
    /**
    * Получить записи журнала действий
    *
    * @param string $userToken токен пользователя (auth)
    * @pw_element string $Uid user ID
    * @param mixed $PageNo номер страницы
    * @param mixed $rowCount кол-во записей на странице
    * @return StdResponse
    */
    public function getEventLog($params = FALSE) {
        include_once(__DIR__ . '/../svc/alfo_dataclasses.php');
        $userToken = $params['userToken'] ?? '';
        $Uid = $params['userid'] ?? '';
        # $connected = $this->init($userToken, $Uid, '', __FUNCTION__);
        if (self::$debug) writeDebugInfo(__FUNCTION__, " start, params: ", $params);
        $ret = new StdResponse('OK');
        $PageNo = $params['params']['page'] ?? $params['page'] ?? 0; # max(0,$PageNo);
        $rowCount = $params['params']['rows'] ?? $params['rows'] ?? 20; # min(100, max(10,$rowCount));
        $PageNo = max(0,$PageNo);
        $rowCount = min(100, max(1,$rowCount));

        $offset = $rowCount * $PageNo;
        $ret->data = AppEnv::$db->select(PM::T_EVENTLOG, ['fields'=>"DATE_FORMAT(evdate,'%d.%m.%Y %H:%i') evdate,evtype,ipaddr,userid,itemid,'' username,evtext",
          'offset'=>$offset, 'rows'=>$rowCount, 'orderby'=>'evid DESC'
        ]);
        # if (self::$debug) writeDebugInfo("event SQL ", AppEnv::$db->getLastQuery());
        # if (self::$debug>1) writeDebugInfo("event data ", $ret->data);
        if(is_array($ret->data)) foreach($ret->data as &$row) {
            if($row['userid'] > 0) $row['username'] = CmsUtils::getUserFullname($row['userid']);
        }
        return $ret;
    }
    /**
    * получить данные по полису, по его номеру
    * (используется в ALI для прямого получения данных о полисе)
    * @param mixed $params
    * @return StdResponse
    * @since 2024-12-18
    */
    public function getPolicyByNo($params = FALSE) {
        include_once(__DIR__ . '/../svc/alfo_dataclasses.php');
        $userToken = $params['userToken'] ?? '';
        $Uid = $params['userid'] ?? '';
        # $connected = $this->init($userToken, $Uid, '', __FUNCTION__);
        if (self::$debug) writeDebugInfo(__FUNCTION__, " start, params: ", $params);
        $ret = new StdResponse('OK');
        $pNo = $params['params']['policyno'] ?? $params['policyno'] ?? ''; # max(0,$PageNo);
        $bRisks = $params['params']['risks'] ?? $params['risks'] ?? 0; # включить риски
        # writeDebugInfo("policy=$pNo, risks=[$bRisks]");
        $ret->data = AppEnv::$db->select(PM::T_POLICIES, [
          'fields'=>"stmt_id,policyno,module,metatype,datefrom,datetill,module,programid,subtypeid,term,termunit,policy_prem,currency,datepay,tranchedate,stateid",
          'where'=> ['policyno'=>$pNo], 'singlerow'=>1,
        ]);
        # writeDebugInfo("seek by $pNo: ", $ret->data);
        $plcid = $ret->data['stmt_id'] ?? 0;
        if(!empty($ret->data['module'])) {
            if($ret->data['module'] == \PM::INVEST2) {
                # дополнительно передаю инфу о кодировке (коэф-ты РПФ,ГФ,РВД
                $codeDta = AppEnv::$db->select('invins_subtypes', ['where'=>['id'=>$ret->data['subtypeid']],'singlerow'=>1]);
                if(isset($codeDta['part_gf'])) {
                    $ret->data['part_gf'] = $codeDta['part_gf'];
                    $ret->data['part_rf'] = $codeDta['part_rf'];
                    $ret->data['rvd'] = $codeDta['rvd'];
                }
            }
            elseif($ret->data['module'] == 'imutual') {
                # перенесли ДСЖ в отдельный модуль - imutual, там сразу считается ПИФ часть и заносится в calc_params
                $spec = PlcUtils::loadPolicySpecData($plcid);
                if(is_array($spec['calc_params']))
                    $ret->data = array_merge($ret->data, $spec['calc_params']);
                # writeDebugInfo("imutual spec data: ", $spec);
            }
        }

        $plcid =  $ret->data['stmt_id'] ?? 0;
        if($plcid && $bRisks) {
            $risks = AppEnv::$db->select(PM::T_AGRISKS, ['where'=>['stmt_id'=>$plcid], 'fields'=>'rtype,riskid,risksa,riskprem,datefrom,datetill','orderby'=>'id']);
            if(is_array($risks) && count($risks))
            $ret->data['risks'] = $risks;

        }
        # if (self::$debug) writeDebugInfo("event SQL ", AppEnv::$db->getLastQuery());
        # if (self::$debug>1) writeDebugInfo("event data ", $ret->data);
        if(!is_array($ret->data) || !count($ret->data)) {
            $ret->result = 'ERROR';
            $ret->message = 'Данные по номеру не найдены';
        }
        return $ret;
    }
    /**
    * Получить активные сессии
    *
    * @param string $userToken токен пользователя (auth)
    * @pw_element string $Uid user ID
    * @param mixed $PageNo номер страницы
    * @param mixed $rowCount кол-во записей на странице
    * @return StdResponse
    */
    public function getActiveSessions($params = FALSE) {
        # $userToken = $params['userToken'] ?? '';
        # $Uid = $params['userid'] ?? '';
        # $connected = $this->init($userToken, $Uid, '',__FUNCTION__);
        include_once(__DIR__ . '/../svc/alfo_dataclasses.php');
        if (self::$debug) writeDebugInfo(__FUNCTION__, " start, params: ", $params);
        $ret = new \StdResponse('OK');
        $ret->data = \Libs\AppMonitor::activeSessions();
        return $ret;
    }
    /**
    * получение данных из таблицы траншей по указанной кодировке (для вызова из ALI)
    * Отбираются только "будущие" транши с еще не наступившей расч.датой (чтобы не затереть в ALI старые данные)
    * @param mixed $params
    * @return StdResponse
    */
    public function getTranches($params = FALSE) {
        include_once(__DIR__ . '/../svc/alfo_dataclasses.php');
        if (self::$debug) writeDebugInfo(__FUNCTION__, " start, params: ", $params);
        $ret = new \StdResponse('OK');
        $codirovka = $params['subtype'] ?? $params['params']['subtype'] ?? '';
        if (self::$debug) writeDebugInfo("getTranches for subtype=$codirovka");
        if(empty($codirovka)) {
            $ret->result = 'ERROR';
            $ret->message = 'Не указана кодировка';
        }
        else {
            $codeid = \AppEnv::$db->select('invins_subtypes',['fields'=>'id','where'=>['code'=>$codirovka],'singlerow'=>1,'associative'=>0]);
            $joinCond = [
              'type'=>'LEFT',
              'table' => 'invins_date_subtype dtr',
              'condition'=>"FIND_IN_SET('$codeid',codes) AND datestart=tr.tranchedate AND dtr.b_active=1"
            ];

            $ret->data = \AppEnv::$db->select(['tr'=>\PM::TABLE_TRANCHES], [
              'fields'=>'openday,closeday,tranchedate, dtr.koef_redemption,dtr.est_ku,dtr.gardohod,dtr.part_gf,dtr.part_rf,dtr.coupon_size',
              'join' => $joinCond,
              'where'=>["FIND_IN_SET('$codirovka', codirovka)", "tranchedate>=CURDATE()"], 'orderby'=>'tranchedate']);
            # writeDebugInfo("SQL ", \AppEnv::$db->getLastQuery() );
            # writeDebugInfo("err ", \AppEnv::$db->sql_error() );
        }
        return $ret;
    }
    /**
    * статистика за период (кол-во созданных договоров за период, с разбивкой по каналам)
    *
    * @param mixed $params
    */
    public static function periodStats($params = FALSE) {
        include_once(__DIR__ . '/../svc/alfo_dataclasses.php');
        if (self::$debug) writeDebugInfo(__FUNCTION__, " start, params: ", $params);
        $ret = new \StdResponse('OK');
        $ret->data = \Libs\AppMonitor::periodStats($params);
        return $ret;
    }
    /**
     * Зарегистрировать оплату по полису
     */
    public function setAgreementPayed($params){
        # $userToken = isset($params->userToken) ? $params->userToken : '';
        $programId = $params['program'] ?? $params['module'] ?? '';
        $connected = $this->init($userToken, $Uid, $programId);
        $ret = new \StdResponse;
        $module = trim($programId);
        $where = [ 'module' => $module, 'stmt_id'=>$policyId ]; // 'userid' => appEnv::$auth->userid,

        $dta = appEnv::$db->select('alf_agreements', [
          'where' => $where,
          'singlerow' => 1
        ]);
        $err = '';

        if ( !isset($dta['policyno']) ) $err = 'Неверный ИД полиса';
        elseif($dta['userid'] != appEnv::$auth->userid) $err = 'Вы не имеете полномочий менять указанный полис';
        elseif($dta['stateid'] == 9) $err = 'Полис Аннулирован';
        elseif($dta['stateid'] == 10) $err = 'Полис находится в статусе Отменен';
        elseif($dta['stateid'] == 11) $err = 'Полис уже находится в статусе Оформлен';
        elseif($dta['stateid'] == 50) $err = 'Полис расторгнут';
        elseif($dta['stateid'] == 60) $err = 'Полис заблокирован';
        elseif($dta['stateid'] == 7 || intval($dta['datepay'])>0) $err = 'Полис уже оплачен';
        elseif(!empty($paySum) && $paySum < $dta['policy_prem'])
            $err = 'Сумма оплаты меньше требуемой суммы взноса: '.fmtMoney($dta['policy_prem']). ' '.$dta['currency'];

        elseif(empty($docNumber))
            $err = 'Не указан номер платежного документа';

        if ($err) {
            $ret->result = 'ERROR';
            $ret->message = "Отметка об оплате $dta[policyno] не может быть проставлена: $err";
        }
        else {
            appEnv::$_p = [
              'plg' => $module,
              'id' => $policyId,
              'platno' => $docNumber,
              'datepay' => date('d.m.Y')
            ];
            # if ($paySum > 0) appenv::$_p['pay_rur'] = $paySum;
            if( $onlinePay ) {
                appEnv::$_p['eqpayed'] = $onlinePay;
            }
            # WriteDebugInfo('setPolicyPayed: IP=', $_SERVER['REMOTE_ADDR'], ' params:', appEnv::$_p);
            $payed = $this->plgBackend->setPayed();

            if (self::$debug) WriteDebugInfo("call policymodel::setPayed result:", $payed);
            $ret->result = $payed['result'];
            if ($payed['result'] == 'OK')
                $ret->message = isset($payed['message']) ? $payed['message']: "Отметка об оплате проставлена";
            else $ret->message = $payed['message'];
            if (isset($payed['data']))
                $ret->data = $payed['data'];
        }
        return $ret;
    }

    /**
     * Загрузить файл скана к полису (сканы паспорта, платежки и т.п.)
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @pw_element string $programId
     * @param string $programId ID страхового продукта
     * @pw_element string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @param string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @pw_element string $fileName имя файла
     * @param string $fileName имя файла
     * @pw_element base64Binary $fileBody Base64 encoded file body
     * @param base64Binary $fileBody содержимое файла в формате Base64 encoded
     * @return StdResponse result
     */
    public function addPolicyScan($userToken, $Uid, $programId, $policyId, $fileName, $fileBody){

        $this->init($userToken, $Uid, $programId);
        $module = trim($programId);
        # TODO: реализовать метод загрузки файла через $bkend->addScan
        $binBody = base64_decode($fileBody);
        # file_put_contents('ttt.pdf', $binBody);  WriteDebugInfo("KT-003, pdf saved from base64");
        $ret = new StdResponse;
        if (!$binBody) {
            $ret->result = 'ERROR';
            $ret->message = 'Неверное содкержимое файла (должна быть строка base64Binary)';
            return $ret;
        }
        $scparams = [
          'plg' => $module,
          'doctype' => 'agmt', # passport, stmt, agmt, anketa_ph, anketa_insd (анкета застрахованного),anketa_benef
          'id' => $policyId,
          'filename' => $fileName,
          'filebody' => $binBody,
        ];
        try {
            $bkResult = $this->plgBackend->addScan($scparams);
            # WriteDebugInfo("backend addScan return:", $bkResult);
            if (isset($bkResult['result'])) {
                $ret->result = $bkResult['result'];
            }
            if (isset($bkResult['message'])) {
                $ret->message = $bkResult['message'];
            }
            if (isset($bkResult['data'])) {
                $ret->data = $bkResult['data'];
            }
        }
        catch (Exception $e) {
            if (self::$debug) WriteDebugInfo('$bkend->addScan exception:', $e);
            $ret->result='FAULT';
            $ret->message = $e->getMessage() . ' line '.$e->getLine() . ' file '.$e->getFile();
        }

        return $ret;
    }

    /**
     * Получить список файлов сканов к полису
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @pw_element string $programId
     * @param string $programId ID страхового продукта
     * @pw_element string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @param string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @return StdResponse result
     */
    public function getPolicyScanList($userToken, $Uid, $programId, $policyId){
        $this->init($userToken, $Uid, $programId);
        $module = trim($programId);
        if (method_exists($this->plgBackend, 'checkDocumentRights')) {
            $access = $this->plgBackend->checkDocumentRights($policyId);
            if (!$access) return ['result'=>'ERROR', 'message' =>'У Вас не полномочий на указанный полис либо неверный номер полиса'];
        }

        $where = [ 'stmt_id'=>$policyId, "doctype<>'checklog'" ]; // 'userid' => appEnv::$auth->userid,

        $dta = appEnv::$db->select(PM::T_UPLOADS, [
          'where' => $where,
          'fields' =>'id,descr name,filesize',
          'orderby' => 'id'
        ]);
        $ret = new StdResponse();
        $ret->result = 'OK';
        $ret->data['files'] = $dta;
        return $ret;
    }

    /**
     * Удалит файл скана к полису
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @pw_element string $programId
     * @param string $programId ID страхового продукта
     * @pw_element string $policyId ID полиса
     * @param string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @pw_element string $fileId ID файла на удаление
     * @param string $fileId ID файла на удаление
     * @return StdResponse result
     */
    public function deletePolicyScan($userToken, $Uid, $programId, $policyId, $fileId){
        $this->init($userToken, $Uid, $programId);
        $module = trim($programId);

        $ret = new StdResponse();

        if (empty($policyId) || empty($fileId)) {
            $ret->result = 'ERROR';
            $err = [];
            if (!$policyId) $err[] = 'Пустой ИД полиса';
            if (!$fileId || intval($fileId)<=0) $err[] = 'Неверный или пустой ИД файла';
            $ret->message = implode(';', $err);
            return $err;
        }
        if (method_exists($this->plgBackend, 'checkDocumentRights')) {
            $access = $this->plgBackend->checkDocumentRights($policyId);
            if (!$access) return ['result'=>'ERROR', 'message' =>'У Вас нет полномочий на указанный полис либо неверный номер полиса'];
        }

        $pars = ['policyid' =>$policyId, 'id'=>$fileId, 'oper' => 'del' ];
        $result = $this->plgBackend->updtScan($pars);

        $ret->result = $result['result'];
        $ret->message = isset($result['message']) ? $result['message'] : '';
        return $ret;
    }
    /**
     * Перевести полис в статус "отменен"
     *
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @pw_element string $programId
     * @param string $programId ID страхового продукта
     * @pw_element string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @param string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @return StdResponse result
     */
    public function cancelPolicy($userToken, $Uid, $programId, $policyId){
        # $userToken = isset($params->userToken) ? $params->userToken : '';
        $connected = $this->init($userToken, $Uid, $programId);
        $ret = new StdResponse();
        $err = '';

        $module = trim($programId);
        $where = [ 'module' => $module, 'stmt_id'=>$policyId ]; // 'userid' => appEnv::$auth->userid,

        $dta = appEnv::$db->select('alf_agreements', [
          'where' => $where,
          'singlerow' => 1
        ]);

        if ( !isset($dta['policyno']) ) $err = 'Неверный ИД полиса';
        elseif($dta['userid'] != appEnv::$auth->userid) $err = 'Вы не имеете полномочий менять указанный полис';
        elseif($dta['stateid'] == 9) $err = 'Полис уже был Аннулирован';
        elseif($dta['stateid'] == 10) $err = 'Полис уже находится в статусе Отменен';
        elseif($dta['stateid'] == 50) $err = 'Полис расторгнут';
        elseif($dta['stateid'] == 60) $err = 'Полис заблокирован';

        if ($err) {
            $ret->result = 'ERROR';
            $ret->message = utf8_encode("Простановка статуса Отменен $dta[policyno] невозможна: $err");
        }
        else {
            appEnv::$_p = [
              'plg' => $module,
              'id' => $policyId,
              'state' => 'cancel', # PM::STATE_CANCELED,
            ];
            $payed = $this->plgBackend->setState();
            $ret->result = $payed['result'];
            if ($payed['result'] == 'OK')
                $ret->message = self::__strEncode("Полис $dta[policyno] переведен в статус Отменен");
            else $ret->message = self::__strEncode($payed['message']);
            # TODO: переводить ли полис в статус ОФОРМЛЕН, или для этого сделать отдельный API метод ?
        }
        return $ret;

    }

    /**
     * Получить полные данные полиса (для редактирования, вывода формы просмотра и тд)
     * @pw_element string $userToken User Token string
     * @param string $userToken токен пользователя (auth)
     * @pw_element string $Uid user ID
     * @param string $Uid user ID
     * @pw_element string $programId
     * @param string $programId ID страхового продукта
     * @pw_element string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @param string $policyId ID полиса, выданный ранее при вызове savePolicy
     * @return GetPolicyDataResponse result
     */
    public function getPolicyData($userToken, $Uid, $programId, $policyId) {

        $ret = new GetPolicyDataResponse;

        $module = trim($programId);
        if (empty($module)) {
            $sdata = appEnv::$db->select(PM::T_POLICIES, [
              'fields'=>'stmt_id,module',
              'where'=>['stmt_id'=>$policyId],
              'singlerow'=>1
            ]);

            if (empty($sdata['module'])) {
                $ret->result = 'ERROR';
                $ret->message = 'Данные не найдены';
                return $ret;
            }
            $module = $sdata['module'];
        }
        $this->init($userToken, $Uid, $module);
        # WriteDebugInfo("backend class:", get_class($this->plgBackend));
        $data = $this->plgBackend->loadPolicy($policyId, 'edit');

        # WriteDebugInfo("$programId/$policyId: policy loaded data:", $data);
        if (!isset($data['module'])) {
            $ret->result = 'ERROR';
            $ret->message = 'Данные не найдены';
            return $ret;
        }
        if ($programId!='' && $data['module'] !== $programId) {
            $ret->result = 'ERROR';
            $ret->message = "Полис не относится к указанному виду/продукту ($programId)";
            return $ret;
        }
        $access = $this->plgBackend->checkDocumentRights();
        if ($access < 1) {
            $ret->result = 'ERROR';
            $ret->message = 'Доступ к полису запрещен';
            return $ret;
        }
        $ret->result = 'OK';
        $ret->policyStateId = $data['stateid'];
        $ret->policyNo = $data['policyno'];
        $ret->policyState = $this->plgBackend->decodeAgmtState($data['stateid'],'',0);
        $ret->params = $this->exportParameters( $data, $this->plgBackend->getSpecFields() );
        return $ret;
    }
    /**
     * sample, Getting data grid (2-dimensional array)
     *
     * @param string $params
     * @return CommonDataGrid file name and body OR error message
     */
    function getDataGrid($params) {
        $ret = new CommonDataGrid;
        $ret->data = array(
          ['name'=>'Gadet', 'lastname' => 'John', 'birthdate' => '1977-04-12'],
          ['name'=>'Simpleson', 'lastname' => 'Genry', 'birthdate' => '1979-11-07'],
          ['name'=>'Hardi', 'lastname' => 'Jona', 'birthdate' => '1968-05-17'],
        );
        return $ret;
    }
    /**
    * В зависимости от настрофки делать utf8_encode перед отправкой ответа
    *
    * @param mixed $strg
    */
    private static function __strEncode($strg) {
        if (self::$encodeResponse) return utf8_encode($strg);
        return $strg;
    }
    /**
    * Маппинг API->ALFO,перегоняю "стандартные" параметры из SOAP запроса в appEnv::$_p, чтоб отработали станд.ф-ции ALFO (сохранение полиса и т.д.)
    * @param mixed $params
    */
    private function importParameters($module, $policyId, $params, $onlycalc = FALSE) {

        if (self::$debug) WriteDebugInfo("called AlfoServices::importParameters($module, $policyId, params), ", $params);
        $_p = [ 'plg' => $module ];
        if ($policyId > 0) $_p['id'] = $policyId;

        foreach(self::$fieldMap['policyParams'] as $fldname=>$fdata) {
            if (isset($params->$fldname)) {
                $_p[$fdata] = $params->$fldname;
                if (self::$debug>1) WriteDebugInfo("{$fdata} = -> $fldname: ", $params->policyHolder->$fldname);
            }
            else {
                if (self::$debug>1) WriteDebugInfo("mainParams::$fldname not in received params");
            }

        }

        # формирую поля страхователя
        if (!$onlycalc) {
            $pref = 'insr';
            foreach(self::$fieldMap['person'] as $fldname => $fdata) {
                $justname = (is_array($fdata) ? $fdata[0] : $fdata);
                if (isset($params->policyHolder->$fldname)) { # если rezCountry все же передали - беру
                    $_p[$pref . $justname] = $params->policyHolder->$fldname;
                }
                elseif (is_array($fdata) && count($fdata)>1) {
                    # если параметр не передан, возможно для него есть default-значение в маппинге (rezCountry)
                    $_p[$pref . $fdata[0]] = $fdata[1];

                }
                else {
                    if (self::$debug) WriteDebugInfo("$fldname not in received params");
                }

            }
            # адрес страхователя
            foreach(self::$fieldMap['address'] as $fldname => $fdata) {
                if (isset($params->policyHolder->regAddress->$fldname)) {
                    $_p[$pref . $fdata] = $params->policyHolder->regAddress->$fldname;
                    if (self::$debug) WriteDebugInfo("policyholder address: {$pref}$fdata = ",
                      $params->policyHolder->regAddress->$fldname);
                }
            }
            if (empty($params->policyHolder->factAddress))
                $params->policyHolder->sameAddress = 1;
            else { # получаю факт.адрес
                foreach(self::$fieldMap['address'] as $fldname => $fdata) {
                    if (isset($params->policyHolder->factAddress->$fldname)) {
                        $_p[$pref .'f'. $fdata] = $params->policyHolder->factAddress->$fldname;
                        if (self::$debug) WriteDebugInfo("policyholder address: {$pref}$fdata = ",
                          $params->policyHolder->factAddress->$fldname);
                    }
                }
            }

            if (!$params->equalInsured && !empty($params->insured)) {
                # формирую поля Застрахованного
                $pref = 'insd';
                foreach(self::$fieldMap['person'] as $fldname => $fdata) {
                    if (is_array($fdata) && count($fdata)>1)
                        $_p[$pref . $fdata[0]] = $fdata[1];
                    else {
                        if (isset($params->insured->$fldname)) {
                            $_p[$pref . $fdata] = $params->insured->$fldname;
                            if (self::$debug) WriteDebugInfo("{$pref}{$fdata} = -> $fldname: ",
                              $params->insured->$fldname);
                        }
                        else {
                            if (self::$debug) WriteDebugInfo("$fldname not in received params");
                        }
                    }
                }
                # адрес
                foreach(self::$fieldMap['address'] as $fldname => $fdata) {
                    if (isset($params->insured->regAddress->$fldname)) {
                        $_p[$pref . $fdata] = $params->insured->regAddress->$fldname;
                        if (self::$debug) WriteDebugInfo("policyholder address: {$pref}$fdata = ", $params->insured->regAddress->$fldname);
                    }
                }
                if (empty($params->insured->factAddress))
                    $params->insured->sameAddress = 1;
                else { # получаю факт.адрес
                    foreach(self::$fieldMap['address'] as $fldname => $fdata) {
                        if (isset($params->insured->factAddress->$fldname)) {
                            $_p[$pref .'f'. $fdata] = $params->insured->factAddress->$fldname;
                            if (self::$debug) WriteDebugInfo("policyholder address: {$pref}$fdata = ", $params->insured->factAddress->$fldname);
                        }
                    }
                }

            }
        }

        if (is_array($params)) $adddata = $params;
        elseif (isset($params->otherParams)) $addata = $params->otherParams;
        elseif (isset($params->data)) $addata = $params->data;

        if (!empty($addata)) {
            if (is_array($addata))
                $_p = array_merge($_p, $addata);
            elseif (is_object($addata))
                $_p = array_merge($_p, get_object_vars($addata));
            /*
            foreach(get_object_vars($params->otherParams) as $key => $val) {
                $_p[$key] = $val;
            }
            */
            if (array_key_exists($module, self::$fieldMapProd)) {
                foreach(self::$fieldMapProd[$module] as $fid => $fdata) {
                    if (!isset($_p[$fid]) && is_array($fdata) && count($fdata)>1) {
                        $_p[$fid] = $fdata[1]; # получили дефолтное значение
                        if (self::$debug) WriteDebugInfo("$fid = $fdata[1] - взято дефолтное значение");
                    }
                }
            }
        }
        /*
        foreach ($_p as $key => &$val) {
            $val = utf8_decode($val);
        }
        */

        if (self::$debug) WriteDebugInfo("final _p data for ALFO/saveAgmt : ", $_p);
        return $_p;
    }

    private function __saveEvent($operation, $arParams, $result) {
        $module = isset($arParams['progid']) ? $arParams['progid'] : (isset($arParams['plg']) ? $arParams['plg']:'');
        $sessionid = isset($arParams['sessionId']) ? $arParams['sessionId'] : '';
        # WriteDebugInfo("__saveEvent($operation)/$sessionid params:", $arParams);
        # WriteDebugInfo("__saveEvent($operation)/$sessionid return data:", $result);
        $dtBirth = isset($arParams['insured_birth']) ? to_date($arParams['insured_birth']) : '';
        if (!$dtBirth) $dtBirth = isset($arParams['insrbirth']) ? to_date($arParams['insrbirth']) : '';
        if (!empty($arParams['insdbirth'])) $dtBirth = to_date($arParams['insdbirth']);

        $dtStart = isset($arParams['datefrom']) ? to_date($arParams['datefrom']) : '';
        if (!intval($dtBirth) || !intval($dtStart)) return;
        $agedta = DiffDays($dtBirth, $dtStart, TRUE);
        $age = $agedta[0]; # возраст на начало д-вия
        if ($operation == 'calculate') {
            # фиксация (если надо) расчета
            $exist = appenv::$db->select(PM::T_ESHOPLOG, ['where'=>[
              'module'=>$module,
              'eshop_session'=>$sessionid,
              'birth_date' => $dtBirth,
              'policyid' => 0
             ], 'singlerow'=>1
            ]);
            $upd = [
              'module'=>$module,
              'eshop_session'=>$sessionid,
              'event_date' => '{now}',
              'birth_date' => $dtBirth,
              'start_date' => $dtStart,
              'insured_age' => $age,
            ];

            if (!isset($exist['id']))
                appEnv::$db->insert(PM::T_ESHOPLOG, $upd);
            else
                appEnv::$db->update(PM::T_ESHOPLOG, $upd, [ 'id'=>$exist['id'] ]);
            # WriteDebugInfo("eshop/$operation log operation:", appEnv::$db->getLastquery());
        }
        elseif ($operation == 'save') {
            # сохраняю проект полиса (возможно, обновляется ранее созданный)
            $policyId = isset($arParams['id']) ? intval($arParams['id']) : 0;
            $savedId =  isset($result->data['policyid']) ? intval($result->data['policyid']) : 0;
            $where = [
              'module'=>$module,
              # .($policyId ? " OR policyid=$policyId ":''),
            ];

            if ($policyId) $where[] = "policyid = $policyId";
            else $where[] = "(eshop_session='$sessionid' AND policyid=0) AND birth_date='$dtBirth'";

            # if (empty($policyId)) return; # повторно уже не сохраняю
            $exist = appenv::$db->select(PM::T_ESHOPLOG, [ 'where'=>$where, 'singlerow'=>1 ]);
            $upd = [
              'module'=>$module,
              'eshop_session'=>$sessionid,
              'event_date' => '{now}',
              'birth_date' => $dtBirth,
              'start_date' => $dtStart,
              'insured_age' => $age,
              'policyid '=> ($savedId>0 ? $savedId : $policyId),
            ];

            if (!isset($exist['id']))
                appEnv::$db->insert(PM::T_ESHOPLOG, $upd);
            else
                appEnv::$db->update(PM::T_ESHOPLOG, $upd, [ 'id'=>$exist['id'] ]);
            # WriteDebugInfo("eshop/$operation log operation:", appEnv::$db->getLastquery());
        }
    }

    # {upd/2021-12-02} поиск в CHECK_POLICY_DMS (запросы с сайта о статусе полиса ДМС)
    public static function findDmsPolicy($params) {
        Sanitizer::sanitizeArray($params);
        $pSer = $params['policySeries'] ?? $params['params']['policySeries'] ?? '';
        $pNo  = $params['policyNo'] ?? $params['params']['policyNo'] ?? '';
        $pSer = RusUtils::mb_trim($pSer);
        $pNo = RusUtils::mb_trim($pNo);

        if (empty($pSer) || empty($pNo)) {
            # writeDebugInfo("findDmsPolicy Empty request: ", $params); # {upd/2024-08-22}
            return ['result'=>'ERROR','message'=>'Не задана серия или номер полиса для поиска'];
        }
        $data = appEnv::$db->select(PM::T_DMS_POLICIES, [
          'where' => ['POL_SERIES'=>$pSer, 'POL_NUMBER' => $pNo], 'singlerow'=>1
        ]);
        /*
        if(!isset($data['POL_NUMBER'])) {
            writeDebugInfo("findDmsPolicy ($pSer, $pNo) result: ", $data);
            writeDebugInfo("SQL err:", appEnv::$db->sql_error());
            writeDebugInfo("SQL ", appEnv::$db->getLastquery);
        }
        */
        $result = [ 'result'=>'OK', 'data' => $data ];
        return $result;
    }

    # декодирование строки из QR-кода на полисе
    public static function decodePolicyQrCode($params) {
        $pars = $params['params'];
        Sanitizer::sanitizeArray($pars);
        $code = $pars['hexcode'] ?? '';
        $format = $pars['format'] ?? 'html';
        $decodeResult = QrCoder::decodeString($code);
        if ($decodeResult) {
            $parsed = QrCoder::parseResult($decodeResult, $format);
            $result = ['result' => 'OK', 'data'=> ['data' => $parsed]];
            # $result = ['result' => 'OK', 'data'=> ['data' => $decodeResult]];
        }
        else {
            $result = ['result' => 'ERROR', 'message'=> 'Неверная строка для расшифровки, QR-код неверный'];
        }
        return $result;
    }
    # запрос курсов валют (datasync)
    public static function getCurrencyRates($params, $verbose = FALSE) {

        if(self::$debug) writeDebugInfo("getCurrencyRates params: ", $params, "\t  trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        # exit(__FILE__ .':'.__LINE__.' getCurrencyRates params:<pre>' . print_r($params,1) . '</pre>');
        $lastdates = $params['lastdates'] ?? $params['params']['lastdates'] ?? FALSE;
        if(self::$debug) writeDebugInfo("getCurrencyRates KT-002 lastdates: ", $lastdates);

        if($verbose === FALSE) $verbose = $params['verbose'] ?? $params['params']['verbose'] ?? FALSE;

        if( !$lastdates || !is_array($lastdates) ) {
            $err = 'Не передан блок lastdates или там не список валют-дат';
            if(self::$debug) writeDebugInfo("no lastdates in passed params: ", $params);
            if($verbose) $err .= ', params: '.print_r($params,1);
            return ['result'=>'ERROR','message'=> $err];
        }

        if(self::$debug) writeDebugInfo("lastdates: ", $lastdates);

        foreach($lastdates as $curr => $ldate) {
            if(self::$debug) writeDebugInfo("iteration: currency=", $curr, ' date=', $ldate);
            $startdate = @to_date($ldate);
            $vals[$curr] = [];
            if(self::$debug) writeDebugInfo("ymd startdate: currency=[$startdate] vals now: ", $vals);
            $data = \AppEnv::$db->select('currates', [ 'fields'=>'curdate,currate',
               'where'=>['curcode'=>$curr, "curdate>'$startdate'"],
               'orderby'=>'curdate', 'rows'=>self::CURRATES_LIMIT ]
            );
            if(self::$debug>4) writeDebugInfo( "find rates SQL ", AppEnv::$db->getLastQuery(), "\n  data: ", $data, "\q error: ", AppEnv::$db->sql_error() );
            if(is_array($data)) foreach($data as $row) {
                $vals[$curr][] = [ $row['curdate'], floatval($row['currate']) ];
            }
            if(self::$debug>4) writeDebugInfo( "$curr: values now ", $vals[$curr]);
        }
        $result = [ 'result'=>'OK', 'data' => $vals ];
        if($verbose) $result['lastSql'] = \AppEnv::$db->getLastQuery();
        if(self::$debug) writeDebugInfo("returning data: ", $result);
        return $result;
    }

    # {upd/2021-12-09} поиск в CHECK_POLICY_DMS (запросы с сайта о статусе полиса ДМС)
    public static function findDmsBso($params) {

        $bsoNum = $params['bsoNum'] ?? $params['params']['bsoNum'] ?? '';
        # writeDebugInfo("KT-1 bsoNum=[$bsoNum]");
        $bsoNum = RusUtils::mb_trim( Sanitizer::safeString($bsoNum) );

        if (empty($bsoNum)) {
            # writeDebugInfo("findDmsBso params empty: ", $params, " \nbsoNum = [$bsoNum]");
            return ['result'=>'ERROR','message'=>'Не указан номер БСО'];
        }
        $data = appEnv::$db->select(PM::T_DMS_BSO, [
          'where' => ['bso_num'=>$bsoNum], 'singlerow'=>1
        ]);
        # writeDebugInfo("result: ", $data);
        $result = [ 'result'=>'OK', 'data' => $data ];
        return $result;
    }

    # Получить список методов
    public static function methodList() {

        $rdata = [];
        foreach(get_class_methods('AlfoServices') as $func) {
            if (substr($func,0,1)=='_' || in_array($func, self::$hideFuncs))
                continue;
                $rdata[] = $func;
        }
        $result = [ 'result'=>'OK', 'data' => $rdata ];
        return $result;

    }
}
