<?php
/**
* @package ALFO
* Набор доп.утилит для работы с полисами любого типа (policymodel / investprod)
* @version 1.98.001
* modified 2025-12-11
*/
# error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE); ini_set('display_errors', 1); ini_set('log_errors', 1);
if (!class_exists('PlcUtils')) {
class PlcUtils {
    const VERSION = '1.97';
    const PERMIT_TYPES = 1; # Разрешить выбор типа документа, разрешающего пребывание
    private static $payExpireAfter = 0; # блокировка оплаты после N дней с даты начала действия
    private static $seekOtherPolicies = TRUE;
    static $releasePayedDays = 7; # сколько дней посде даты МДВ разрешать выпустить оплаченный полис (агенты)
    static $uwProdType = ''; # устанавливать через setProductType() -
    # будет фиксировать тип полиса, чтобы менять алгоритм поиска полисов на застрахованного, решать по анкетам в полисе и тд
    static $dmsModules = ['trmig','madms', 'hhome','planb']; # модули чисто ДМС или не проверяемые в кумуляциях
    public static $cachedDeptCfg = FALSE; # сюда грузится последний deptCfg, для чтения из статических методов!
    static $seekUwInAlfo = TRUE; # после поиска в Лизе на того же застрах. ищем в ALFO (то, что еще не ушло в LISA)
    private static $errors = [];
    private static $plgBackend = NULL;
    const TABLE_COMMENTS = 'alf_agmt_comment'; # комментарии ко всем полисам
    const ID_RUSSIA = 114; # код России в справочнике стран
    const RUSSIA_DEFAULT = TRUE; # В блоках выбора страны (гражданство, налог.резидент) по умолчанию выставлять Россию
    const BILLS_CODE = '_BILLS_';
    const DAYS_AFTER_RELEASE = 30; # Макс. кол-во дней от даты выпуска до даты начала д-вия (2022-12-20)
    const MASK_SNILS = '999-999-999-99'; # маска для ввода кода СНИЛС
    const MASK_PHONE = '(999)999-9999'; # общая маска для ввода полного номера телефона
    const MASK_PHONEPREF = '999'; # общая маска для ввода префикса телефона
    const MASK_PHONENO   = '999-9999'; # общая маска для ввода только телефона
    const PHONE_RUSPREF = '+7'; # {upd/2023-05-10} добавляю перед выводимым номером телефона
    const IKP_PREFIX = 'Ж-'; # коды ИКП могут прилетать (с большого сайта) без этого префикса - добавлять при поиске по базе
    static private $policyDeptId = FALSE; # здесь будет запоминаться ИД поразделения в открытой карточке полиса
    static private $stateFailed = FALSE; # поднять флаг "глобального сбоя" при ошибке в одной из последовательных операций
    static private $failDetails = [];
    static $CHECK_BENEFS = TRUE; # флаг проверка выгодоприобретателей по спискам террористов/PEPS
    # {upd/2020-02-21} - Ю.Тучкова(Кузнецова) - сказала включить обратно. Перенес сюда из policymodel.php, будет общим с investprod
    const BILL_NUMBER_LEN = 7; # сколько цифр делать у "номерной" части в счете на оплату
    # const TABLE_EXT_POLICIES = 'alf_ext_policies'; # таблица/view с полисами из внешней LISA/DIASOFT(для поиска по андеррайтингу)
    # const TABLE_POLICIES_CC = 'usrInsurPolicyContractLisa'; # таблица с полисами в КК (база AGENTCAB20)
    static $userLevel = 0; # сюда занести уровень поль-ля в рамках текущего модуля (PlcBackend->getUserLevel())
    const REG_CITY_TYPE = 30; # такой addresstypelevelid в regions имеют "регионы" типа город (Москва, Питер)
    static $SEARCH_IN_EXT_POLICIES = 'CC'; # 'CC'; # Искать человека в списке полисов из "внешей" системы, "CC" - в базе Каб.Клиента
    static $holidays = ['01-01','01-02','01-03','01-04','01-05','01-06','01-07','01-08', '02-23',
          '03-08','05-01','05-09','06-12','11-04','12-31'];
    public static $uw_code = 0; # {upd/2023003-15} - сохраняю здесь "сработавший" код причины UW
    public static $uw_hardness = 0; # {upd/2023003-15} - "тяжесть" основания для UW
    const SEARCH_BY_PASSPORT = FALSE; # искать полисы на того же Застрах. по серии-номеру паспорта
    static $skipExpPlc = []; # полисы, найденные в ALFO и прошедшие проверку на лимиты, уже не учитывать, если найдутся в "EXT_POLICIES"
    static $foundOtherPolicy = ''; # номер другого полиса, на данного застрахованного
    static $debug = 0;
    static $saTexts = []; # спец.тексты для СС основных рисков (НСЖ детский Поколение)
    public static $excludedPolicies = []; # при поиске на того же застр. полисы из этого списка игнорировать! (см. AddExludedPolicy()
    static $SED_SEND_CONFIRMATIONS = 1; # TRUE|1 - отправлять в примечания СЭД инфу о подтверждении согласия страхователя
    static $uwExcludedModules = ['investprod','invins']; # спиcок модулей исключаемых из поиска полисов на того же застрах.
    static $uwCheckingMode = 1; # 1 - срабатывать при обнаружении любого полиса, 2 - кумул.суммы, 3 - для новых ИСЖ, не искать среди ИСЖ
    static $uwseekInvest = 0; # 0 - ищет все полисы, 1 искать только ИСЖ, -1 - не будет брать ИСЖ полисы из LISA,
    static $deathLimit1 = 0;
    static $deathLimit2 = 0;
    private static $investSaSlp = 0;
    private static $investSaSns = 0;
    static $actionWarnings = [];
    public static $prolongDebug = 0; # 1|TRUE - временно отключить проверку дат при пролонгации (отладка) в тестовой среде
    public static $clientAnketaLimit = 15000; # лимит (руб), при достижении которго требовать анкету клиента (страхователя)
    public static $nsAnketaLimit = 40000; # лимит (руб), условные НСЖ полисы
    static $_deathLimReached = 0; # станет 1 если общая СС по СЛП+СНС превысит лимит ins_limit_cumdeath1_rur,2 если ins_limit_cumdeath2_rur
    # 1 - посылаю полис на андеррайтинг даже если просто нашел действующий(не аннулированный) на сегодня,на того же застр.
    # 2 - проверить сумму рисков по смерти (какой - СЛП/СНС?), и на андеррайтинг - только при превышении лимита
    # {upd/2020-05-29} - добавляю проверку на 2 лимита - 6млн - включении фин-анкеты, 18млн = анкеты и справок 2/3НДФЛ (помимо ухода на UW)
    static $alarmedLimit = 0; # лимит, который вызвал перевод в андеррайтинг
    static $multiInvestAnketa = 1; # 1|TRUE - можно на одну инвест-анкету вешать несколько полисов (в пакетном вводе на одного клиента или в отдельных полисах)
    static $warn_message = '';
    static $_cache = [];
    static $_russiaCodes = [];
    static public $markedPages = [];
    static public $signatureData = []; # координаты, размеры блока ЭЦП, полученные из XML настройки
    static $lastPrintedPage= 0;
    private static $disableFilesCheck = FALSE; # можно отключить проверку наличия файлов перед выгрузкой в СЭД
    static $cached = [];
    static $allUwReasons = []; # накапливаю все коды причин постановки на UW (в идеале - сохранить в полисе весь список)
    static $requiredFields = []; # заполнит данными об обязательных для заведения полях
    public static $KIDRiskByRule = []; # собираю названия рисков группирую по номеру п-та правил, чтоб выводить одно на список

    # Из каких этапов можно выполнить финальный перевод карточки СЭД (андеррайтинг) на "Ввод в БД"
    static $SedGoodStages = ['Доработка'];
    private static $useAdultRiskNames = FALSE;

    private static $rassrochkaRP = [ # названия рассрочек в род.падеже
       '0' => 'единовременно',
       '1' => 'ежемесячно',
       '3' => 'ежеквартально',
       '6' => 'полугодово',
       '12' => 'ежегодно',
    ];

    static $printEdoMode = FALSE; # если TRUE, значит, печатаем полис, выпущенный по ЭДО-ПЭП

    static $showNotRussiaMode = FALSE; # в списке стран не показывать псевдо-страну НЕ РОССИЯ (она только для eShop-полисов)
    static $ouRegions = [
      [ '', '--' ],
      [ 'msk', 'Москва' ],
      [ 'reg', 'Регионы' ],
    ];
    static $income_sources = [
      'income_source_work' => 'от профессиональной деятельности',
      'income_source_social' => 'денежные выплаты социального обеспечения',
      'income_source_business' => 'доход от предпринимателькой деятельности',
      'income_source_finance' => 'поступления из финансовой системы',
      'income_source_realty' => 'доход от собственности',
      'income_source_other' => 'другие источники',
      'income_descr' => '',
    ];
    static $agmt_states = [ # список возможных состояний договора stateid
      '0' => 'Проект'
     ,'1' => 'На оформлении'
     ,'2' => 'На андеррайтинге'
     ,'3' => 'Согласовано андеррайтером'
     ,'3.1' => 'Согласовано с корректировкой'
     ,'4' => 'Требуется внесение/изменение данных' # STATE_UW_DATA_REQUIRED
     ,'5' => 'Проверка данных в СК'
     ,'6' => 'Полис'
     ,'7' => 'Оплачен'
     ,'9' => 'Аннулирован'
     ,'10' => 'Отменен'
     ,'11' => 'Оформлен'
     ,'30' => 'На проверке у Комплайнс'
     ,'33' => 'На проверке у ИБ'
     ,'63' => 'На проверке у Комплайнс и ИБ'
     ,'50' => 'Расторгнут'
     ,'60' => 'Блокирован'

    ]; # ID и названия возможных состояний договора (0-заявление ...)

    const DOCFORM_FIELDNAME = 'Форма документа'; # имя поля "Форма документа" в СЭД
    const DOCFORM_SES_ID = '3'; # Форма документа в СЭД - "Документ с ПЭП"
    const DOCFORM_PAPER = '1'; # Форма документа в СЭД - "Бумажный Документ"

    # какой риск искать в полисах при определении "кумулятивной" суммы по "осн".риску (смерти) А ЕЩЕ есть death_acc,death_acc_addcover,death_road,
    static $uw_deathSeekRisk = 'death_any'; # TODO: если будем искать по ребенку - свои коды рисков: child_death_any

    static $scanExtensions = ['pdf','jpg','jpeg','png','tiff','tif','zip','rar','7z','htm','html','txt','docx','xlsx'];
    # расширения файлов, при наличии которых разрешать выгруз в СЭД (иначе считаем, что сканов полиса, паспорта - ничего нет)

    static $uwcheck_currency = 'RUR';
    # финальный код валюты, в которой была выполнена проверка на лимиты (если обнаружены полисы в разных вал.

    const CHECKLOGTYPE = 'checklog'; # с таким типом в полис добавится "скан" с HTML логом проверки по спискам PEPs/террористы
    static $emulate = FALSE; # эмуляция = сохранения HTML как файл в тек.папке, вместо сохр. в полис

    static $FILENAME_PREFIX = 'Проверка ';
    static $checkLogPdf = 0; # сохранять журнал проверки в PDF (из HTML)
    static $INVEST_CHECK_TYPEID = '100';

    static $uw_coeffs = [
      'age-35' => [ 'age' => 35, 'life' => 25, 'ci' => 15, 'tpd' => 15, 'trauma' => 2 ],
      'age-40' => [ 'age' => 40, 'life' => 20, 'ci' => 12, 'tpd' => 12, 'trauma' => 2 ],
      'age-49' => [ 'age' => 49, 'life' => 15, 'ci' =>  9, 'tpd' =>  9, 'trauma' => 2 ],
      'age-55' => [ 'age' => 55, 'life' => 10, 'ci' =>  7, 'tpd' =>  7, 'trauma' => 2 ],
      'age-59' => [ 'age' => 59, 'life' => 10, 'ci' =>  0, 'tpd' =>  7, 'trauma' => 2 ],
      'age-64' => [ 'age' => 64, 'life' =>  5, 'ci' =>  0, 'tpd' =>  0, 'trauma' => 2 ],
      'age-69' => [ 'age' => 69, 'life' =>  3, 'ci' =>  0, 'tpd' =>  0, 'trauma' => 0 ],
      'age-999' =>[ 'age' =>999, 'life' =>  2, 'ci' =>  0, 'tpd' =>  0, 'trauma' => 0 ],
    ];

    static $uw_nonworking = [ // TODO:использовать эту таблицу в getSaLimits()
       'studbezrab' => [
           'death_total' => ['RUR'=> 3000000, 'USD' => 50000],
           'trauma' => ['RUR'=> 450000, 'USD' => 7500],
           'ci-formula' => 'death_any',
           'tpd-formula' => "(death_any+death_acc)/2",
           'trauma-formula' => "(death_any+death_acc)/2",
       ],
       'pension' => [
           'death_total' => ['RUR'=> 3000000, 'USD' => 50000],
           'trauma' => ['RUR'=> 450000, 'USD' => 7500],
           'ci' => 0,
           'tpd' => 0,
           'trauma-formula' => "(death_any+death_acc)/2",
       ],
       'housekeeper' => [
           'death_total' => ['RUR'=> 4500000, 'USD' => 75000],
           'trauma' => ['RUR'=> 450000, 'USD' => 7500],
           'ci-formula' => 'death_any',
           'tpd-formula' => "(death_any+death_acc)/2",
           'trauma-formula' => "(death_any+death_acc)/2",
       ],
    ];

    static $uw_max_sa = [ # верхние пределы с.сумм (для всех возрастов-доходов)
      'ci' => [ 'RUR' => 21000000, 'USD' => 350000 ],
      'trauma' => [ 'RUR' => 2250000, 'USD' => 37500 ],
    ];
    # Уровень страховых покрытий (Life + AD) не должен опускаться ниже лимитов для безработных граждан:
    static $uw_min_sa = [ 'RUR' => 3000000, 'USD' => 50000 ];

    // Список "близких" родственных связей, допустимых для выгодоприобр. (SELECT-box выбора)
    public static $close_relations = [
      'муж',
      'жена',
      'отец',
      'мать',
      'сын',
      'дочь',
      'внук',
      'внучка',
      'гражданский муж',
      'гражданская жена',
      'брат',
      'сестра',
      'зять',
      'невестка',
      'дядя',
      'тётя',
      'дедушка',
      'бабушка',
      'племянник',
      'племянница',
      'иное' # - на андеррайтинг!
    ];
    const RELATION_OTHER = 'иное';

    public static $draftstate_on = FALSE; # включать в TRUE при незаполненных обяз.полях на полисе
    public static $draft_reasons = [];
    // массив-маппинг для определения, какого типа данный риск - PlcUtils::getRiskType($riskid)
    static private $risk_mapping = [ # TODO: перенести в базовый список рисков как отд.поле выбора "принадлежности"
      'life' => ['death_any','death_acc','death_road','death_any_wop', 'child_death_any'], //  риски смерти (ЛП, НС)
      'ci' => ['first_ci', 'child_ci'],   // крит.заболев.
      'tpd' => ['disability_123_acc','invalid_12_any'],  // инвалидности
      'trauma' => ['trauma_acc'], // травмы, ЧПТ
    ];
    private static $_userButtons = []; // "Юзерские" Кнопки на форме просмотра полиса
    private static $_iamUw = FALSE;

    public static function getVersion() {
        return self::VERSION;
    }
    /**
    * Вернет результат как полный HTML код, доступный для просмотра в браузере/сохранения в файл
    * (чтобы приложить к полису)
    */
    public static function getFullHtml($html, $head = '') {
        if ($html == '') return '';
        if (!$head) $head = 'Проверка от ' . date('d.m.Y H-i');
        else $head = str_replace('%date%', date('d.m.Y H-i'), $head);

        $ret = <<< EOHTM
<!DOCTYPE html>
<html>
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <style>
    body { font-family:arial,helvetica;font-size:15px; margin:0px; padding:0px; }
    th { background-color: #d8d8ff; border:1px solid #88a; text-align:center;
      font-weight:bold; padding: 2px 4px; font:bold 11px verdana,tahoma,arial;
    }
    .attention { color: #e08; font-weight:bold;}

    table.zebra { border: 1px solid #ccf; border-spacing: 0; border-collapse: collapse; }
    table.zebra td { border: 1px solid #ccf; padding: 0 0.3em; padding: 2px; font-size: 14px;}
    table.zebra tr:nth-child(even) { background: #eeeeff }
    table.zebra tr:nth-child(odd) { background: #f8f8ff }
    table.zebra tr:hover td { background: #fefeff; }
  </style>

  </head>
  <body>
  <h4>$head</h4>
  $html
  </body>
</html>
EOHTM;
        return $ret;
    }
    /**
    * Сохраняет переданный HTML код как файл, прикладывая его к полису
    *
    * @param mixed $module ID модуля (investprod  либо что угодно другое)
    * @param mixed $plcid ИД полиса
    * @param mixed $html сохраняемый код
    */
    public static function saveResultHtmlToPolicy($module, $plcid, $html, $head='') {
        if (!$html) return;
        if (self::$checkLogPdf) {
            # конвертирую HTML в PDF
            $filename = self::$FILENAME_PREFIX . date('d-m-Y-H-i') . '.pdf';
            # $fullHtml = $html;
            $fullHtml = self::getFullHtml($html, $head);
            # file_put_contents('tmp/_check_src.htm',$fullHtml);
            include_once('tcpdf/tcpdf.php');
            $tcPdf = new TcPdf();
            $tcPdf->SetFont('arial', '', 14);
            // add a page
            $tcPdf->AddPage();
            $tcPdf->writeHTML($fullHtml);
            $fullPdf = $tcPdf->output($filename, 'S');
            unset($tcPdf);
            $params = [
                'id' => $plcid,
                'doctype' => self::CHECKLOGTYPE,
                'filename' => $filename,
                'filebody' => $fullPdf
            ];
            if (self::$emulate) {
                file_put_contents("policyCheck-$plcid.pdf", $fullPdf);
                return true;
            }
        }
        else {
            $fullHtml = self::getFullHtml($html, $head);
            $filename = self::$FILENAME_PREFIX . date('d-m-Y-H-i') . '.html';
            $params = [
                'id' => $plcid,
                'doctype' => self::CHECKLOGTYPE,
                'filename' => $filename,
                'filebody' => $fullHtml
            ];
            if (self::$emulate) {
                file_put_contents("policyCheck-$plcid.html", $fullHtml);
                return true;
            }
        }
        # TODO: сохранение (на место уже сделаного, если был)

        if (!isset(appEnv::$_plugins[$module])) return FALSE;

        if ($module === 'investprod') {
            $result = self::saveScanInvest($params); # TODO!
        }
        else {
            $bkend = appEnv::$_plugins[$module]->getBackend();
            $result = $bkend->addScan($params, true, TRUE); # true - не делать проверку файла на MD5
        }
        return $result;
    }
    # Для eShop-полисов, устанавливаю режим "выводить НЕ РОССИЯ" в выбор страны, при редактировании
    public static function showNotRussia($param = TRUE) {
        self::$showNotRussiaMode = $param;
    }
    # получить актуальный спсиок стран (для вызовов из API и т.п.)
    public static function getCountryList($onlyActive = TRUE) {
        $where = (($onlyActive) ? ["former=0"] : []);
        $ret = appEnv::$db->select(PM::T_COUNTRIES,
            ['fields'=>'countryno id,countryname name','where'=>$where, 'orderby'=>'ctype,countryname','associative'=>1]
        );
        return $ret;
    }
    #  $onlyActive > 0 - отберет только ныне существующие станы (СССР мимо кассы!)
    public static function getCountryOptions($initvalue='0', $onlyActive = FALSE) {
        $cntKey = 'opt_countries' .$initvalue.$onlyActive;
        if (empty(self::$_cache[$cntKey])) {
            $ret = [];
            if ($initvalue==0) $ret[] =  ['0','---Не выбрано!---',0];

            $where = (($onlyActive) ? ["former=0"] : []);
            if (!self::$showNotRussiaMode) $where[] = "countryno<=900";
            $cnts = appEnv::$db->select(PM::T_COUNTRIES,
                array('fields'=>'countryno,countryname,ctype','where'=>$where, 'orderby'=>'ctype,countryname','associative'=>0)
            );
            if (is_array($cnts)) $ret = array_merge($ret,$cnts);

            self::$_cache[$cntKey] = $ret;
        }
        $ret = '';
        $rusId = [];
        if (is_array(self::$_cache[$cntKey])) {
            foreach(self::$_cache[$cntKey] as $item) {
                if (!empty($initvalue))
                    $sel = ($item[1]===$initvalue || $item[0]==$initvalue) ? ' selected="selected"':'';
                elseif(self::RUSSIA_DEFAULT)
                    $sel = ($item[0] == self::ID_RUSSIA) ? ' selected="selected"':'';

                if ($item[2] == 1) $rusId[] = $item[0];
                $ret .= "<option value='$item[0]'$sel>$item[1]</option>";
            }
        }
        self::$_russiaCodes = $rusId;
        return $ret;
    }
    # получить ISO код по ИД страны  TODO: убрать из policymodel, переделав все вызовы сюда!
    public static function getCountryISO($id) {
        $dta = appEnv::$db->select(PM::T_COUNTRIES,array('fields'=>'isocode', 'where'=>array('id'=>$id),'singlerow'=>1));
        return (isset($dta['isocode']) ? $dta['isocode'] : $id);
    }

    # список регионов для select в данных адреса ("Регион")
    public static function getRegionsNone() {
        $rKey = 'cache_regions';
        if (!isset(self::$_cache[$rKey])) {
            $ret = [ ['', '--'] ];
            $where = [ 'countryid'=>'RUS' ];
            $regs= appEnv::$db->select(PM::T_REGIONS,
                array('fields'=>'id,regname','where'=>$where, 'orderby'=>'showorder,regname','associative'=>0)
            );
            if (is_array($regs)) $ret = array_merge($ret,$regs);

            self::$_cache[$rKey] = $ret;
        }
        return self::$_cache[$rKey];
    }
    # {upd/2023-05-03} - параметр $asName - если TRUE и найденный регион - город,
    #   вернуть короткое название города, иначе TRUE для города,FALSE у прочих
    public static function regionIsCity($regid, $asName = FALSE) {
        # writeDebugInfo("regionIsCity($regid, [$asName])");
        if (intval($regid)==0) return FALSE;
        $regdta= appEnv::$db->select(PM::T_REGIONS,
            ['fields'=>'sname,addresstypelevelid','where'=>['id'=>$regid],'singlerow'=>1]
        );
        # writeDebugInfo("regdta ", $regdta);
        if (!empty($regdta['addresstypelevelid']) && $regdta['addresstypelevelid'] == self::REG_CITY_TYPE)
            return (($asName) ? $regdta['sname'] : TRUE);
        else return FALSE;
    }
    public static function getRegionName($regid, $shortName = FALSE) {
        if (!is_numeric($regid)) return $regid;
        if (intval($regid)<=0) return '';
        $regdta= appEnv::$db->select(PM::T_REGIONS,
            ['fields'=>'code,regname,sname','where'=>['id'=>intval($regid)],'singlerow'=>1]
        );
        if (isset($regdta['sname'])) {
            if ($shortName === 'code') return $regdta['code'];
            return ($shortName ? $regdta['sname'] : $regdta['regname']);
        }
        else return '';
    }
    public static function findRegionNo($rname, $getId=FALSE) {
        $regdta= appEnv::$db->select(PM::T_REGIONS,
            [ 'fields'=>'id,code,regname,sname','where'=>"regname LIKE '%$rname%' OR sname LIKE '$rname%'",
              'singlerow'=>1, 'orderby'=>'sname'
            ]
        );
        if (!isset($regdta['id'])) return FALSE;
        $ret = ($getId) ? $regdta['id'] : $regdta['code'];
        # writeDebugInfo("findRegionNo($rname) => [$ret]");
        return $ret;
    }
    # своя ф-ция вместо вызова из investprod
    public static function saveScanInvest($params) {
        $ret = 0;
        $dta = appEnv::$db->select('bn_policyscan',
          [ 'where'=>[ 'insurancepolicyid'=>$params['id'],
            "typeid IN('100','checklog')"],
            'singlerow'=>1
          ]
        );
        $recid = (empty($dta['id']) ? 0 : $dta['id']);
        if ($recid && in_array($params['doctype'], ['100',self::CHECKLOGTYPE]) ) { # Обновляю существующий файл!
            if (!is_dir($dta['path'])) @mkdir($dta['path'],0777,TRUE);
            $destName = $dta['path'] . $dta['filename'];
            file_put_contents($destName, $params['filebody']);
            $updt = [ 'descr' => $params['filename'] ];
            appEnv::$db->update('bn_policyscan',$updt, [ 'id'=>$recid ]);
            # WriteDebugInfo("Обновили файл прошлой проверки ", $dta);
            $ret = 'U';
        }
        else {

            $path = InvestProdBackend::getUploadPath();

            $updt = [
              'insurancepolicyid' => $params['id'],
              'typeid' => self::$INVEST_CHECK_TYPEID,
              'filename' => '_tochange_file_name_',
              'descr' => $params['filename'],
              'path' => $path
            ];
            appEnv::$db->insert(InvestProd::TABLE_DOCSCANS,$updt);
            $scanid = appEnv::$db->insert_id();
            $flname = 'investprod_'.$scanid . '.html';
            $saved = file_put_contents($path . $flname, $params['filebody']);
            # WriteDebugInfo("новый скан с рез.проверки: $scanid / $saved, $flname");
            if ($scanid>0 && $saved) {
                appEnv::$db->update(InvestProd::TABLE_DOCSCANS, ['filename'=>$flname], ['id'=>$scanid]);
                $ret = 'A';
            }
        }
        return $ret;
    }
    public static function workFlowFilesCheckOff($param=TRUE) {
        self::$disableFilesCheck = $param;
    }
    /**
    * сгенерит в tmp папке эл.полис и добавит его в список файлов для СЭД
    *
    * @param mixed $bkend бакенд модуля
    * @param mixed $files ссылка на список уже существующих файлов
    */
    public static function appendEpolicyForDocflow($bkend, &$files) {
        if($bkend->isEdoPolicy()) return;
        $filename = 'ПФ-'.$bkend->_rawAgmtData['policyno'] . '.pdf';
        $tmpName = $bkend->print_pack($bkend->_rawAgmtData['stmt_id'],TRUE);
        $files[] = ['filename'=>$filename, 'fullpath' => $tmpName];
        if(self::$debug) writeDebugInfo("добавил в список загрузки в СЭД сгенеренный е-полис [$filename]=$tmpName");
    }
    /**
    * Выгружаем полис в карточку СЭД (с-му эл. документо-оборота)
    *
    * @param mixed $module ID плагина
    * @param mixed $bkend бакенд-объект плагина или FALSE
    * @param mixed $log_pref префикс в журнале для данного плагна
    * @param mixed $uwstate в каком статусе следует создать заявку (1 - отправить на UW, ...)
    * @param mixed $return TRUE если надо вернуть результат (внутренний вызов)
    * @param mixed $okStage TRUE выполнять ли финальные переводы статуса карточки СЭД
    */
    public static function policyToDocflow($module, $bkend=FALSE, $log_pref='', $uwstate = FALSE, $return=FALSE, $okStage=TRUE, $dopFiles=0) {
        # if (self::$debug || $uwstate>0 || !$okStage) writeDebugInfo("policyToDocflow($module, bkend, $log_pref, uwstate=[$uwstate], return=[$return], okstage=[$okStage]");
        # {upd/2021-12-06} При нажатой SHIFT - вместо выгрузки показываю набор полей, который улетит в СЭД
        $shift = !empty(appenv::$_p['shift']);
        if ($shift) {
            # exit("SED - SHIFT pressed!");
            SedExport::showParamsMode($shift);
        }
        $ret = '';
        /**
        if (!appEnv::isProdEnv()) {
            error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);
            ini_set('display_errors', 1);
            ini_set('log_errors', 1);
        }
        **/
        # {updt/2020-09-09} данные для СЭД из учетки имеют приоритет (если заполнены)
        $usrDta = appEnv::$db->select(PM::T_USERS, ['where' => ['userid'=>appEnv::$auth->userid], 'singlerow'=>1]);
        # echo "1\tshowmessage\f<pre>" . print_r($usrDta,1). '</pre>'; exit;
        if (!is_object($bkend)) $bkend = appEnv::getPluginBackend($module);
        self::$plgBackend = $bkend;

        $expType = (method_exists($bkend, 'isExportable') ? $bkend->isExportable() : 1 );
        if ($expType > 1) SedExport::setServerType($expType); # для "не-жизни" будет выбран сервер СЭД Альянс (не жизнь)

        # {upd/2021-11-12} временная блокировка СЭД через настройки (сервер СЭД на ремонте) (2021-12-23 - или по расписанию отключений)
        if ( !$shift && Sedexport::isServerDown($expType) ) {
            appEnv::setUserAppState('docflow_create', 'NO ACCESS');
            $errText = 'Сервер СЭД временно недоступен, выгрузка в СЭД невозможна!';
            if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                return ['result'=>'ERROR','message'=>$errText];
            else exit($errText);
        }

        $winlogin = $usrDta['winaccount'] ?? '';
        $winid = $uwWinId = $usrDta['winuserid'] ?? '';
        # беру "общие" логин и ИД для задания инициатора/куратора в СЭД (для модуля), если заданы
        if (empty($winlogin)) $winlogin = appEnv::getConfigValue('sed_'.$module. '_winlogin'); # sed_investprod_winlogin
        if (empty($winid))    $winid = appEnv::getConfigValue('sed_'.$module. '_userid'); # sed_investprod_userid

        if (!empty($bkend->_rawAgmtData['stmt_id'])) $id = $bkend->_rawAgmtData['stmt_id'];
        elseif (appEnv::isRunningJob()) {
            $id = appEnv::getPolicyId();
            if (self::$debug) WriteDebugInfo("policyToDocflow, appEnv::getPolicyId return: ", $id);
        }
        else {
            $id = (isset(appEnv::$_p['id']) ? trim(appEnv::$_p['id']) : 0);
            # WriteDebugInfo("id from _p ", $id);
        }

        if ($id<=0) {
            if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                return ['result'=>'ERROR','message'=>'Неизвестен ИД полиса'];
            else exit('Ошибка в параметрах');
        }

        if (!isset(appEnv::$_plugins['sedexport'])) exit('Плагин экспорта в СЭД не найден');
        if ($module === 'investprod') {
            $data = $bkend->getAgreementForExport($id);
            # appEnv::$_plugins['investprod']->getAgreementForExport($id);
            $data['insurer_fullname'] = RusUtils::mb_trim($data['insurer']['fam'] .' '. $data['insurer']['imia'].' '. $data['insurer']['otch']);
            if ($data['bptype'] === PM::BPTYPE_EDO) {# в комментарий добавляю тел и email страхователя
                self::addUserNotes($data, 'Тел: ' . $data['insurer']['phone']);
                if (!empty($data['insurer']['address']))
                    self::addUserNotes($data, "Адрес: ".$data['insurer']['address']);
            }
        }
        else {
            $data = $bkend->loadPolicy($id, 'export'); # in policymodel
            # writeDebugInfo("export data: ", $data);
            if ($data['bptype'] === PM::BPTYPE_EDO) { # в комментарий добавляю тел и email страхователя
                self::addUserNotes($data, 'Тел: ' . self::buildPhone($data['insurer']['phone']));
                $insraddr = !empty($data['insurer']['fulladdr']) ? $data['insurer']['fulladdr'] :
                  (!empty($data['insurer']['address']) ? $data['insurer']['address'] : '');
                if ($insraddr)
                    self::addUserNotes($data, "Адрес: ".$insraddr);
            }
        }
        if (in_array($data['bptype'], ['', PM::BPTYPE_STD]) ) {
            # {upd/2021-09-13} Не ЭДО, передаю Форма документа - "Бумажный"
            SedExport::addUserField(self::DOCFORM_FIELDNAME, self::DOCFORM_PAPER);
        }
        else {
             # ЭДО-договора, добавляю поле "Форма документа" - ЭДО (3)
            SedExport::addUserField(self::DOCFORM_FIELDNAME, self::DOCFORM_SES_ID);
        }

        $meta = $bkend->plcOuMetaType();
        # $agtProg = method_exists($bkend, 'isAgentProduct') ?  $bkend->isAgentProduct() : ''; # устарел
        # $agtProg = ($meta == OrgUnits::MT_AGENT); # {upd/2022-11-07} агентский продукт - если продан в агентской сети! TODO: сотрудники альянса, продажа с внеш.сайта - как?
        $agtProg = ($meta != OrgUnits::MT_BANK); # {upd/2022-11-08} вар.2 - агентская продажа, если договор создан НЕ в банке

        # {upd/2021-02-08}
        if ( $agtProg ) {
            # если продукт агентский-жизнь, то брать инициатора из параметров Аг.продуктов - sed_lifeag_*
            $winloginAgt = appEnv::getConfigValue('sed_lifeag_winlogin');
            if($winloginAgt) $winlogin = $winloginAgt;
            $winidAgt = appEnv::getConfigValue('sed_lifeag_userid');
            if($winidAgt) $winid = $winidAgt;
            # {upd/2022-11-07} Для агент-сети = автоматом беру ИД куратора юзера как в поле "Куратор" СЭД
            $plcAuthor = $data['userid'] ?? $data['createdby'] ?? 0;
            if($plcAuthor) {
                $curatorData = self::getUserCurator($plcAuthor);
                if(!empty($curatorData['win_id'])) $data['sed_curator'] = $curatorData['win_id'];
            }
        }
        # если для конкретного модуля логины не настроены, использую "общие":
        if (empty($winlogin)) $winlogin = appEnv::getConfigValue('sed_all_winlogin');
        if (empty($winid)) $winid = appEnv::getConfigValue('sed_all_userid');

        if (!empty($winlogin)) {
            SedExport::initCredentials($winlogin, $winid);
        }
        # writeDebugInfo("loaded SED login/id: [$winlogin], [$winid]");
        # writeDebugInfo("$module: data for SED: ", $data);
        # exit('1' . AjaxResponse::showMessage("SED: plc ,Meta=$meta,winlogin=$winlogin, winid=$winid, data: <pre>".print_r($data,1).'</pre>')); # debug stop!
        if(method_exists($bkend, 'SedOutData')) {
            # есть особый метод для получения доп-полей, выгружаемых в СЭД
            $bkend->SedOutData($data);
            # WriteDebugInfo("data after SedOutData:", $data); exit('debug stop');
        }
        if ($agtProg) { # добавляю ФИО и код агента OMNI: R-199865
            # writeDebugInfo("агентский полис ", $data, ' _rawAgmtData: ', $bkend->_rawAgmtData);
            $agtData = CmsUtils::getUserInfo($bkend->_rawAgmtData['userid']);
            $deptName = getDeptName($bkend->_rawAgmtData['deptid']);
            # writeDebugInfo("агент: ", $agtData);
            if (!empty($agtData['lastname'])) {
                self::addUserNotes($data, "Агент $agtData[lastname] $agtData[firstname] $agtData[secondname]");
                if (!empty($agtData['agentno']))
                    self::addUserNotes($data, "Код агента $agtData[agentno]");
                if ($deptName) {
                    $deptName = strtr($deptName, ['"'=>'', '<'=>' ', '>'=>' ']);
                    self::addUserNotes($data, "Подразделение $deptName");
                }
            }
            # {upd/2021-11-03} в комментарий добавляю слово Реинвестиция
            if(!empty($bkend->_rawAgmtData['reinvest']))
                    self::addUserNotes($data, "Реинвестиция");
            # exit ('1' . AjaxResponse::showMessage('userNotes <pre>' . print_r($data['userNotes'],1). '</pre>'));
        }
        # exit('PIT STOP');
        # {upd/2020-05-07} текст для комментария в $data['userNotes'] об онлайн-подтверждении согласия страхователем/застрахованным

        if (self::$SED_SEND_CONFIRMATIONS && class_exists('UniPep')) {

            $confrimSt = UniPep::IsConfirmed($module, $id, 'edo');
            if ($confrimSt) {
                self::addUserNotes($data, 'Онлайн-подтверждение Страхователем '.to_char($confrimSt,1) );
            }
        }

        if ($data['docflowstate'] >= 1) {
            if (self::$debug) WriteDebugInfo("policyToDocflow, exit - card exists");
            # TODO: вставить вызов ф-ции, обновляющей ранее созданную карточку (загрузка новых файлов и тп)?
            if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                return ['result'=>'ERROR','message'=>appEnv::getLocalized('docflow_err_card_already_exists')];

            $ret = '1' . AjaxResponse::showError('docflow_err_card_already_exists');
            exit($ret);
        }
        $plcState = !empty($data['stateid']) ? $data['stateid'] : 0;  # exit("stateid:" . $data['stateid']);
        # writeDebugInfo("data for SED: ", $data); exit('TODO');
        if (method_exists($bkend, 'checkFilesForDocflow')) {
            # в модуле есть своя проверка наличия необходимых файлов - запустить ее!
            $fls = isset($data['files']) ? $data['files'] : FALSE;
            $chkFiles = $bkend->checkFilesForDocflow($fls);
            if (!empty($chkFiles['error'])) {
                if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                    return ['result'=>'ERROR','message'=>$chkFiles['error']];

                $ret = '1' . AjaxResponse::showError($chkFiles['error']);
                exit($ret);
            }
        }
        else {
            # стандартная проверка - должен быть хотя бы один приложенный файл, иначе не пропустит
            if(self::$disableFilesCheck) $skipFilesCheck = TRUE;
            else $skipFilesCheck = method_exists($bkend,'isFreeClientPolicy') ? $bkend->isFreeClientPolicy() : FALSE;
            $scanFiles = 0;
            if (!$skipFilesCheck && isset($data['files']) && is_array($data['files'])) {
                if (self::$debug) WriteDebugInfo("files ", $data['files']);
                foreach($data['files'] as $no=>$fl) {
                    if (self::$debug) WriteDebugInfo("$no: file ", $fl);
                    $ext = mb_strtolower( appEnv::getFileExt($fl['filename']) );

                    if (in_array($ext, self::$scanExtensions)) $scanFiles++;
                }
            }
            if(self::$debug) {
                writeDebugInfo("skipFilesCheck=[$skipFilesCheck], files to send: ", $scanFiles);
                writeDebugInfo("data[files]: ", $data['files']);
                if(is_array($dopFiles)) writeDebugInfo("dop files: ", $dopFiles);
            }
            $prolongation = !empty($data['previous_id']);
            # writeDebugInfo("data: ", $data);
            if (!$skipFilesCheck && $scanFiles==0 && !$prolongation) {
                # if (!self::$emulate)
                $errText = appEnv::getLocalized('docflow_err_no_attached_files');
                if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                    return ['result'=>'ERROR','message'=>$errText];

                $ret = '1' . AjaxResponse::showError($errText);
                exit($ret);
            }
        }
        /*
        if (method_exists($bkend, 'addFilesToExport')) {
            # если в модуле есть метод генерации спец-файлов для выгрузки, вызываю его и заношу результат в массив файлов
            # Сделано для "Гарантия Плюс" - генерить файл с графиком платежей по ДМС (xlsx)
            $addFiles = $bkend->addFilesToExport($id);
            if(is_array($addFiles) && count($addFiles)) {
                if (self::$debug) WriteDebugInfo("export/$module::addFilesToExport() added files:", $addFiles);
                $data['files'] = array_merge($data['files'], $addFiles);
            }
        }
        */
        # доп-файлы, переданные в ф-цию
        if(is_array($dopFiles) && count($dopFiles))
            $data['files'] = array_merge($data['files'], $dopFiles);

        if (!empty($uwstate)) $SEDstate = 2; # пришло указание из параметров
        else $SEDstate = (isset(appEnv::$_p['sedstate']) ? trim(appEnv::$_p['sedstate']) : '1');
        if (empty($SEDstate)) $SEDstate = 1;
        # 1-OK, 2 - послать на андеррайтинг!

        /*
        # {upd/2024-06-05} А.Загайнова - хотят в СЭД видеть коммент для типового и UW-полиса (закомментил, т.к. есть поле "Условия дог."=Типовой/нетиповой")
        if($SEDstate == 1)
            self::addUserNotes($data, "Типовой Договор");
        else
            self::addUserNotes($data, "Нетиповой Договор");
        */
        if(self::$debug) {
            writeDebugInfo("SEDstate = [$SEDstate], _p: ", appEnv::$_p, ' data:', $data);
            writeDebugInfo("_p passed ", appEnv::$_p);
            # writeDebugInfo("debug_trace: ", debug_backtrace(0, 3));
        }
        # exit("passed SEDstate: $SEDstate"); # debug
        # $expType = (method_exists($bkend, 'isExportable') ? $bkend->isExportable() : 0 );
        # if ($expType > 1) SedExport::setServerType($expType); # для "не-жизни" будет выбран сервер СЭД Альянс (не жизнь)
        /*
        if($SEDstate == 1 && $bkend->b_epolicy_wf ) {
            self::appendEpolicyForDocflow($bkend, $data['files']);
        }
        */
        $data['sedstate'] = $SEDstate; # передаю куда послать карточку (1=типовой 2=нетиповой)
        # {upd/2023-12-18} передаём повышенное АВ, аквизиция = нестандарт и процент акв.=[comission]
        if($data['reasonid'] == PM::UW_REASON_EXT_AV) {
            $data['sed_akv'] = sedexport::AKV_NONSTANDARD;
            $data['sed_akvpercent'] = (string) floatval($data['comission']);
        }

        $docFlowBkend = appEnv::getPluginBackend('sedexport');

        if (!$okStage) {
            $docFlowBkend->setFinalStage(0); # отменяю установку последнего "решения" СЭД
            if (self::$debug) writeDebugInfo("SED: final stage canceled by okStage!");
        }

        if (empty($log_pref)) $log_pref = strtoupper($data['module']) . '.';
        # WriteDebugInfo("log_pref for SED:", $log_pref);
        $docFlowBkend->setLogPref($log_pref);

        # WriteDebugInfo("data for export:", $data);
        # file_put_contents('_data_for_xml.log', print_r($data,1));
        # echo 'data <pre>' . print_r($data,1). '</pre>';exit;
        if ($SEDstate <=1 || !empty($bkend->export_xml_inuw)) {
            # {upd/2021-01-29} - выгружаю XML только если не андеррайтинг (или разрешен для всех статусов)
            $xmlCfg = FALSE;
            $b_generate = isset($bkend->b_generate_xml) ? $bkend->b_generate_xml : TRUE;
            if (!$b_generate && !empty($data['prodcode'])) {
                $xmlCfg = self::XmlConfigExist($data['prodcode'], $bkend);
                if ($xmlCfg) $b_generate = TRUE;
            }
            # writeDebugInfo("xmlCfg: [$xmlCfg]");
            # file_put_contents('_xmlCfg.log', print_r($xmlCfg,1));
            if ( !empty(appEnv::$_plugins['plcexport']) && $b_generate ) {
                # генерю в память XML код файла выгрузки в LISA/Diasoft
                $exportBkend = appEnv::getPluginBackend('plcexport');
                # exit('TODO: generate XML!');
                $xmlContent = $exportBkend -> onePolicyPacket($module, $id, 'memory');
                # writeDebugInfo("created XML: ", $xmlContent);
                if (empty($xmlContent)) {
                    AjaxResponse::exitError('Не удалось создать XML с данными полиса, выгрузка прервана');
                }
                $data['files'][] = array(
                  'filename' => ($data['policyno'] . '.xml'),
                  'body' => $xmlContent,
                );
            }
        }
        else {
            if(self::$debug) writeDebugInfo("UW-mode: XML файл в СЭД не грузим!");
        }
        # exit('xml generated: <pre>'. $xmlContent . '</pre>');

        self::normalizeFileNames($data['files']);

        if (self::$debug) WriteDebugInfo("KT-25 state=[$SEDstate] files to SED in policy, : ", $data['files']);
        $prodType = ($module === 'investprod') ? '6' : '4'; #  6 - инвесты, 4 - накоп.стр. (все прочие)
        # С 14.11.2018 код продукта для СЭД задавать в свойстве backend-модуля плагина, sed_product_type
        if (isset($bkend->sed_product_type) && !empty($bkend->sed_product_type))
            $prodType = $bkend->sed_product_type; # переменная должна быть public!

        # WriteDebugInfo("createCard for $module, prodtype=$prodType");
        # exit('todo: to SED for '.$id);
        # {upd/2020-02-19} - запоминаю если карточка создана в статусе UW (2) в поле docflowstate и в журнале "(UW)"

        # Запрещаю одновременный запуск создания карточки двумя сотрудниками
        if(class_exists('PlcLocks') && !SedExport::$showParams) {
            $pLock = PlcLocks::lockRecord($module,$id, 'docflow');
            if ($pLock !== TRUE) {
                if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                    return ['result'=>'ERROR','message'=>$pLock];
                else exit($pLock);
            }
        }

        $result = $docFlowBkend->createCard($data, $data['files'], $prodType);

        if(class_exists('PlcLocks')) {
            PlcLocks::unlockRecord($module, $id);
        }
        $text = '';
        if (!$log_pref) $log_pref = strtoupper($module);
        if(substr($log_pref,-1)!=='.') $log_pref.= '.';
        $cardId = 0;
        if (!empty($result["CreatedItemId"])) {
            # Заношу ИД карточки в БД к полису
            $cardId = $result["CreatedItemId"];

            $logTxt = "Для договора $data[policyno] создана карточка СЭД $cardId";
            if ($SEDstate == 2) $logTxt .= "(UW)"; # в режиме на UW
            appEnv::logEvent($log_pref."SED.CREATE", $logTxt, FALSE, $id );
            # заношу лог загруженных в СЭД файлов
            if(isset($result['files']) && is_array($result['files'])) foreach($result['files'] as $no=>$item) {
                # writeDebugInfo("file item: ", $item);
                appEnv::logEvent($log_pref.'SED.FILE',"СЭД : ".print_r($item,1),0,$id);
            }

            $arUpd = [ 'docflowstate'=>$SEDstate,'export_pkt'=>$cardId, 'acceptedby'=>appEnv::$auth->userid ];
            $updresult = self::updatePolicy($module, $id, $arUpd);

            if (empty($updresult)) {
                $err = appEnv::$db->sql_error();
                appEnv::logEvent($log_pref."DOCFLOW PROBLEM", "$data[policyno] выгружен СЭД $cardId, но не занес номер в полис :$err", FALSE, $id );

                $errText = 'Ошибка при обновлении в полисе, карточка заведена!';
                if (self::$debug) WriteDebugInfo("policyToDocflow, KT-057-err: ".$errText);

                if (appEnv::isRunningJob() || appEnv::isApiCall() || $return)
                    return ['result'=>'ERROR','message'=>$errText];
                else exit($errText);
            }

            if($SEDstate == 2) {
                # {upd/2022-10-24} отправляю уведомление андеру - надо провести андеррайтимнг
                $sent = agtNotifier::send($module,Events::DOCFLOW,$id, $cardId);
                if(self::$debug) writeDebugInfo("отправка emaila для UW/$id/$cardId: result=[$sent]");
            }

            # WriteDebugInfo("update result: $updresult, sqll:", appEnv::$db->getLastQuery(), ' err:', appEnv::$db->sql_error());
            $text = "Карточка $cardId успешно создана";
            $errFiles = [];
            if (!empty($data['files'])) foreach($data['files'] as $no=>$onefile) {
                if (!empty($onefile['exported'])) {
                    if ( !empty($onefile['id']) ) {
                        if (self::$debug) writeDebugInfo("запоминаю факт выгрузки файла $onefile[filename]");
                        $scanTable = ($module ==='investprod') ? 'bn_policyscan' : PM::T_UPLOADS;
                        appEnv::$db->update($scanTable, ['exported'=>1],['id'=>$onefile['id']]);
                    }
                    # elseif(self::$debug)  writeDebugInfo("file exported but not fixed:", $onefile);
                }
                else $errFiles[] = $onefile['filename'];
            }
            if (count($errFiles)) { # isset($result['errors']['files'])
                $text .= "<br><div class='attention'>Внимание, не были загружеы файлы:</div>" . implode('<br>',$errFiles);
                # foreach($result['errors']['files'] as $item) { $text .= "<br> - <b>$item[0]</b>"; }
            }
            if ($plcState == 11 && $SEDstate == '1') {
                # полис оформлен, карточка создана - очищаю коментарий, если был
                $addcmd = self::setPolicyComment($module, $id,'',$log_pref, true);
            }
            if (!appEnv::isRunningJob() && !appEnv::isApiCall() && !$return) {
                $ret = '1' . AjaxResponse:: showMessage($text, 'Результат загрузки');
                if ($module === 'investprod') {
                    $ret .= $bkend->refresh_view($id, 0, FALSE);
                }
                else {
                    $ret .= $bkend->refresh_view($id, true);
                }
            }
            # self::checkPremiumLimitSED($data, $cardId, $module, $log_pref);
            $created = 1;
            appEnv::setUserAppState('docflow_create', 'OK');
            # $ret = "1\tshowmessage\f$text\fРезультат загрузки" . $bkend->refresh_view($id, 0, FALSE); // investprod
        }
        else {
            $text = 'Ошибка при создании карточки СЭД !';
            appEnv::setUserAppState('docflow_create', 'ERROR CREATE CARD');
            appEnv::logEvent($log_pref."DOCFLOW FAIL", "Ошибка при создании карточки СЭД для $data[policyno]", FALSE, $id );
            writeDebugInfo("SED createCard Failed, return: ", $result);

            if (isset($result['message'])) $text .= "<br>" .$result['message'];
            if (isset($result['errors'])) $text .= implode('<br>', $result['errors']);
            else $text .= '<br>Нет связи с сервером СЭД';
            $ret = '1' . AjaxResponse::showError($text, 'Результат загрузки');
            $created = 0;
        }

        if(self::$debug) writeDebugInfo("PlcUtils::uwstate = [$uwstate]");
        if ($return || appEnv::isRunningJob() || appEnv::isApiCall()) {

            if ($created) $ret = ['result'=>'OK', 'message'=>$text, 'cardId' => $cardId];
            else $ret = ['result'=>'ERROR', 'message'=>$text];
            if(self::$debug) writeDebugInfo("returning result: ", $ret, ' appEnv::isApiCall():', appEnv::isApiCall());
            return $ret;
        }
        if(self::$debug) writeDebugInfo("PlcUtils::policyToDocflow response: [$ret] / text: [$text]");
        exit($ret);
    }

    /**
    * {upd/2023-03-16} перевести на новый этап карточку СЭД
    * @param mixed $module модуль
    * @param mixed $bkend инстанс бэкенда модуля
    * @param mixed $stageNames список этапов - в массиве либо в строке через ";"
    */
    public static function DocflowSetStage($module, $bkend, $stageNames) {
        if(is_string($stageNames))
            $stageNames = explode(';', $stageNames);

        if(is_array($stageNames)) {
            $tmpStages = $stageNames;
            $stageName = array_pop($stageNames); # имя последнего этапа
            $stageNames = $tmpStages;
            unset($tmpStages);
        }

        $expType = (method_exists($bkend, 'isExportable') ? $bkend->isExportable() : 1 );
        if ($expType > 1) SedExport::setServerType($expType); # для "не-жизни" будет выбран сервер СЭД Альянс (не жизнь)

        $plcid = $bkend->_rawAgmtData['stmt_id'] ?? $bkend->_rawAgmtData['id'] ?? 0;
        if(!$plcid) return ['result'=>'ERROR', 'message'=>'Не обнаружен ИД договора'];

        $cardid = $bkend->_rawAgmtData['export_pkt'];
        $logpref = $bkend->getLogPref();
        # {upd/2021-11-12} временная блокировка СЭД через настройки (сервер СЭД на ремонте) (2021-12-23 - или по расписанию отключений)
        if (Sedexport::isServerDown($expType) ) {
            appEnv::logEvent($logpref."STAGE ERROR", "Ошибка установки этапа [$stageName],Сервер СЭД недоступен", 0, $plcid);
            appEnv::setUserAppState('docflow_create', 'NO ACCESS');
            $errText = 'Сервер СЭД временно недоступен, установка этапа не произведена';
            return ['result'=>'ERROR','message'=>$errText];
        }

        $winlogin = appEnv::getConfigValue('sed_'.$module. '_winlogin'); # sed_investprod_winlogin
        $winid = appEnv::getConfigValue('sed_'.$module. '_userid'); # sed_investprod_userid
        if (!empty($winlogin)) {
            SedExport::initCredentials($winlogin, $winid);
        }
        $docFlowBkend = appEnv::getPluginBackend('sedexport');
        $docFlowBkend->setLogPref($logpref);
        $result = $docFlowBkend->setCardStage($cardid, $stageNames);
        if(self::$debug) {
            writeDebugInfo("setCardStage(card=$cardid) stages: ", $stageNames);
            writeDebugInfo("setCardStage result: ", $result);
        }
        $mpStage = SEDExport::getStageName($stageName);
        if(!empty($result['success']) && strtoupper($result['success']) === 'OK') {

            if(in_array(PM::DOCFLOW_TOREWORK, $stageNames)) {
                # {upd/2024-06-04} # заношу ИД для поля "Согласующий андеррайтер"
                /***
                $usrInfo = CmsUtils::getUserInfo(AppEnv::getUserId());
                $uwWinId = $usrInfo['winuserid'] ?? '';
                if(!empty($uwWinId)) {
                    # обновить поля в карточке
                    $noFiles = [];
                    $updData = ['underwriter_id' => $uwWinId ];
                    $updUwResult = $docFlowBkend->updateCard($cardid, $updData, $bkend->_rawAgmtData, $noFiles);
                    if(self::$debug) {
                        writeDebugInfo("результат заненсения Согл.андеррайтера $uwWinId в карточку [$cardid]:", $updUwResult);
                    }
                    # exit('1' . AjaxResponse::showMessage('usrInfo: <pre>' . print_r($usrInfo,1) . '</pre>'));
                }
                **/
                # Комментарий от андеррайтера - если есть, добавится в Комментари карточки СЭД:
                if($uwComment = self::getPolicyComment($module, $plcid)) {
                    $cmtResult = $docFlowBkend->appendComment($cardid, $uwComment);
                }
            }

            # {upd/2024-06-21} финальный перевод на Ввод в БД карточки с UW -
            # надо СНОВА передать согласующего андеррайтера, иначе смена статуса затрет ИД системной учетки API
            elseif(in_array(PM::DOCFLOW_TONEXT, $stageNames) && !empty($bkend->_rawAgmtData['uw_acceptedby'])) {
                $usrInfo = CmsUtils::getUserInfo($bkend->_rawAgmtData['uw_acceptedby']);
                $uwWinId = $usrInfo['winuserid'] ?? '';
                # writeDebugInfo("фиксирую согл.андеррайтера ".$bkend->_rawAgmtData['uw_acceptedby']." / uwWinId=$uwWinId");
                if(!empty($uwWinId)) {
                    # обновить поля в карточке
                    # writeDebugInfo("DOCFLOW_TONEXT, надо бы повторно проставить ИД сог.андера, пока отключаил");

                    $noFiles = [];
                    $updData = ['underwriter_id' => $uwWinId ];
                    $updUwResult = $docFlowBkend->updateCard($cardid, $updData, $bkend->_rawAgmtData, $noFiles);
                    if(self::$debug) {
                        writeDebugInfo("результат повт.заненсения Согл.андеррайтера $uwWinId в карточку [$cardid]:", $updUwResult);
                    }

                }
            }
            $result['result'] = 'OK';
            appEnv::logEvent($logpref."SED.STAGE OK", "СЭД: карточка переведена на этап [$mpStage]", 0, $plcid);
        }
        else {
            $result['result'] = 'ERROR';
            $badStage = $result['stage'] ?? $mpStage;
            appEnv::logEvent($logpref."SED.STAGE ERROR", "СЭД: Ошибка при переводе карточки на этап [$badStage]!", 0, $plcid);
            writeDebugInfo("ошибка при переводе карточки СЭД [$cardid] на этап: $badStage");
        }
        return $result;
    }

    public static function DocflowGetStage($module, $bkend, $cardid) {

        $expType = (method_exists($bkend, 'isExportable') ? $bkend->isExportable() : 1 );
        if ($expType > 1) SedExport::setServerType($expType); # для "не-жизни" будет выбран сервер СЭД Альянс (не жизнь)

        # {upd/2021-11-12} временная блокировка СЭД через настройки (сервер СЭД на ремонте) (2021-12-23 - или по расписанию отключений)
        if (Sedexport::isServerDown($expType) ) {
            appEnv::setUserAppState('docflow_create', 'NO ACCESS');
            $errText = 'Сервер СЭД временно недоступен';
            return ['result'=>'ERROR','message'=>$errText];
        }

        $winlogin = appEnv::getConfigValue('sed_'.$module. '_winlogin'); # sed_investprod_winlogin
        $winid = appEnv::getConfigValue('sed_'.$module. '_userid'); # sed_investprod_userid
        if (!empty($winlogin)) {
            SedExport::initCredentials($winlogin, $winid);
        }
        $docFlowBkend = appEnv::getPluginBackend('sedexport');

        $result = $docFlowBkend->getCardStage($cardid);
        if(self::$debug) {
            writeDebugInfo("getCardStage(card=$cardid result:", $result);
        }
        return $result;
    }

    # добавляю теккст в поле комментария карточки СЭД
    public static function addDocFlowComment($cardId, $comment) {
        $expType = 1; # пока в СЭД Альянса обновление карточек не применяется, так что здесь всегда только СЭД АЖ
        $sedDisabled = ($expType > 1) ? appEnv::getConfigValue('sed_srv_disabled') : appEnv::getConfigValue('sed_srv2_disabled');
        if ($sedDisabled) {
            return ['result'=>'ERROR','message'=>'Сервер СЭД выключен!'];
        }
        $docFlowBkend = appEnv::getPluginBackend('sedexport');
        $result = $docFlowBkend->appendComment($cardId, $comment);
        return $result;
    }

    public static function updateDocFlowCard($cardId, $data, $plcdata, &$files, $log_pref='', $stage=FALSE) {

        # {upd/2021-11012} проверяется блокировка СЭД через настройки
        $expType = 1; # пока в СЭД Альянса обновление карточек не применяется, так что здесь всегда только СЭД АЖ
        $sedDisabled = ($expType > 1) ? appEnv::getConfigValue('sed_srv_disabled') : appEnv::getConfigValue('sed_srv2_disabled');
        if ($sedDisabled) {
            $errText = 'Сервер СЭД временно недоступен, обновление карточки СЭД невозможно!';
            appEnv::setUserAppState('docflow_update', 'NO ACCESS');
            if (appEnv::isRunningJob() || appEnv::isApiCall() )
                return ['result'=>'ERROR','message'=>$errText];
            else exit($errText);
        }

        $docFlowBkend = appEnv::getPluginBackend('sedexport');
        $errFiles = [];
        $module = isset($plcdata['module']) ? $plcdata['module'] : 'unknown';
        $bkend = AppEnv::getPluginBackend($module);

        if (empty($log_pref)) $log_pref = $bkend->getLogPref();

        if(substr($log_pref,-1)!=='.') $log_pref.= '.';

        # WriteDebugInfo("log_pref for SED:", $log_pref);
        # {upd/2021-02-25} добавляю блокировку на время изменения карточки СЭД
        $pLock = -1;
        $plcid = isset($plcdata['stmt_id']) ? $plcdata['stmt_id'] : (isset($plcdata['id']) ? $plcdata['id'] : 0);
        if(class_exists('PlcLocks') && !empty($plcid)) {

            $pLock = PlcLocks::lockRecord($module,$plcid, 'docflow_update');
            if ($pLock !== TRUE) {
                if ( appEnv::isRunningJob() || appEnv::isApiCall() )
                    return ['result'=>'ERROR','message'=>$pLock];
                else exit($pLock);
            }
        }

        $docFlowBkend->setLogPref($log_pref);
        # $result = $docFlowBkend->updateCard($cardId, $data, $plcdata, $files, $stage);

        # {upd/2021-11-22} - по результатам общения выключаем попытку перевода статуса/этапа (Фураева,Невзорова,Савкина)
        $result = $docFlowBkend->updateCard($cardId, $data, $plcdata, $files);

        if(class_exists('PlcLocks') && $plcid) {
            if ($pLock !== -1) PlcLocks::unlockRecord($module, $plcid);
        }
        # updateCard($cardid, $dta, $plcdata, &$files, $accept = FALSE)
        if (is_array($files)) {
             foreach($files as $no=>$onefile) {
                if (!empty($onefile['exported'])) {
                    if($module !=='investprod' && !empty($onefile['id'])) {
                        if (self::$debug) writeDebugInfo("запоминаю факт выгрузки файла ", $onefile);
                        appEnv::$db->update(PM::T_UPLOADS, ['exported'=>1],['id'=>$onefile['id']]);
                    }
                    # elseif(self::$debug)  writeDebugInfo("file exported but not fixed:", $onefile);
                }
                else $errFiles[] = $onefile['filename'];
            }
        }
        if (count($errFiles))
            appEnv::setUserAppState('docflow_update', 'ERROR FILES SAVE');
        else
            appEnv::setUserAppState('docflow_update', 'OK');

        if(!empty($result['files']) && is_array($result['files'])) foreach($result['files'] as $item) {
            AppEnv::logEvent($log_pref.'SED.FILE',"СЭД : ".print_r($item,1),0,$plcid);
        }
        return $result;
    }
    /**
    * пытается порезать номер телефона на префикс и номер отдельно
    *
    * @param mixed $strPhone
    */
    public static function splitPhoneNo($strPhone) {
        $defis = strpos($strPhone, '-');
        $prefix = '';
        $phone = $strPhone;
        if ($defis > 0) {
            $prefix = substr($strPhone, 0, $defis);
            $phone = substr($strPhone, $defis+1);
        }
        elseif (strlen($strPhone) > 8) {
            $prefix = substr($strPhone,0,3);
            $phone = substr($strPhone,3);
        }
        return [$prefix, $phone];
    }
    /**
    * Заявка С.Черноус: при создании карточки полиса в СЭД, если есть прывышение премией заданного лимита (руб),
    * посылать email уведомление на зарегистрированный адрес
    * @param mixed $dta массив с данными о полисе, как был передан в CreateCard()
    */
    public static function checkPremiumLimitSED($dta, $cardId, $module='', $log_pref='') {

        $premLimit = appEnv::getConfigValue('sed_premium_limit');
        $premEmail = appEnv::getConfigValue('sed_notify_email');
        if ($premLimit <=0 || empty($premEmail) || !isValidEmail($premEmail)) return;
        $prem = isset($dta['policy_prem']) ? floatval($dta['policy_prem']) : 0;
        $curr = isset($dta['currency']) ? floatval($dta['currency']) : 'RUR';
        $currate = isset($dta['currate']) ? floatval($dta['currate']) : 1;
        if ($curr != 'RUR' && $currate !=1) $prem = round($prem * $currate,2);
        if ( $prem <= $premLimit) return;
        $subj = 'СЭД - уведомление о полисе с превышением лимита '.fmtMoney($premLimit) . ' руб';

        $tplName = ALFO_ROOT . 'templates/SED_premium_limit.htm';
        if (is_file($tplName)) $body = file_get_contents($tplName);
        else {
          $body = "В СЭД была создана карточка договора %policyno% с премией (первым взносом), превысившим порог в размере %limit% руб.<br>"
           . "Ссылка для просмотра карточки : <a href=\"%sed_link%\">%sed_link%</a>";
        }

        $subst = [
           '%policyno%' => $dta['policyno'],
           '%limit%' => fmtMoney($premLimit),
           '%sed_link%' => SEDExport::getDocFlowUrlForCard($cardId, $module)
        ];
        $body = strtr($body, $subst);

        $resSend = appEnv::sendEmailMessage(array(
            'to' => $premEmail
            ,'subj' => $subj
            ,'message' => $body
          )
        );
        if ($resSend) {
            appEnv::logEvent("$log_pref.NOTIFY", "Отправлено уведомление о превышении взносом значения $premLimit руб.", FALSE, $dta['stmt_id']);
        }

    }
    /**
    * Получаем пааремтры настройки продут - партнер (головной орг-юнит)
    * кешируется в appEnv::$_cache[]
    * @param mixed $plgid модуль/плагин
    * @param mixed $headdept ИД "головного" орг-юнита
    * @param mixed $codirovka кодировка/код программы, если надо конкретно
    */

    public static function deptProdParams($plgid, $headdept=0, $codirovka='', $includeBlocked=FALSE, $subtypeId=0) {
        if (empty($headdept)) $headdept = OrgUnits::getPrimaryDept();
        if (empty($plgid) || empty($headdept)) {
            return FALSE;
        }
        if ($codirovka === 'SBEL') $codirovka = 'BEL';
        elseif ($codirovka === 'SBCL') $codirovka = 'BCL';
        # Спец.кодировки старом НСЖ, не присутствуют в явном виде в настройках !!!
        # writeDebugInfo("PlcUtils::deptProdParams($plgid, head=[$headdept], codir=[$codirovka], blocked=[$includeBlocked]");

        $cacheKey = "deptprod-$plgid-$headdept" . (($codirovka!='') ? ".$codirovka" : '') . ($includeBlocked ? '.B':'.NB');
        if (!isset(appEnv::$_cache['subdept'][$cacheKey])) {

            $where = array('module'=>$plgid, 'deptid'=>$headdept);
            if (!$includeBlocked)  $where['b_active'] = 1;

            ### if ($codirovka) $where[] = "(prodcodes='' OR FIND_IN_SET('$codirovka',prodcodes))";
            # веременно заглушим поиск по кодировке - Lifeag - сидят ИД программ, и ничего не находит!

            $dta = appEnv::$db->select(PM::T_DEPT_PROD,
              array('where'=>$where,'singlerow'=>1,'orderby'=>'prodcodes DESC'
            ));
            if(self::$debug) {
                writeDebugInfo("dept prod query result ", $dta);
                writeDebugInfo("dept query: ", appEnv::$db->getLastQuery());
                writeDebugInfo("dept query err: ", appEnv::$db->sql_error());
            }
            if (!empty($dta['specparams'])) {
                $spc = appEnv::parseConfigLine($dta['specparams']);
                if (is_array($spc)) $dta = array_merge($dta, $spc);
            }
            # {upd/2024-04-03} - беру настройку персонально для указанной модуля/партнера/программы
            $ouPrgParams = FALSE;
            if(!empty($codirovka) || !empty($subtypeId) ) {
                $ouPrgParams = \OuProdCfg::getCurrentSubParams($plgid,$headdept,$codirovka,$subtypeId);
                # writeDebugInfo("trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
                # writeDebugInfo("ouPrgParams($plgid,$headdept,$codirovka,$subtypeId) ",  $ouPrgParams);
                if(is_array($ouPrgParams) && count($ouPrgParams)) foreach($ouPrgParams as $key=>$val) {
                    # беру только непустые, чтобы не затереть нулями "общую" спец-настройку партнер-модуль
                    if(!empty($val)) $dta[$key] = $val;
                }
                # отрабатываю особые поля, универсальные для любых ouprodcfg:
                if(!empty($ouPrgParams['enable_edo'])) { # смена разрешения на ЭДО персонально для программы
                    if($ouPrgParams['enable_edo'] === 'Y') $dta['online_confirm'] = 1;
                    elseif($ouPrgParams['enable_edo'] === 'N') $dta['online_confirm'] = 0;
                }
            }
            # else writeDebugInfo("call ($plgid, $headdept, [$codirovka]) no codir, trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            if(self::$debug) writeDebugInfo("final dept-prod params for $cacheKey: ", $dta);
            appEnv::$_cache['subdept'][$cacheKey] = $dta;
        }
        # writeDebugInfo("deptCfg return: ", appEnv::$_cache['subdept'][$cacheKey]);
        self::$cachedDeptCfg =& appEnv::$_cache['subdept'][$cacheKey];
        return appEnv::$_cache['subdept'][$cacheKey];
    }

    /**
    * обеспечиваю уникальность имен файлов для СЭД
    * если найдет одинаковые (даже при разном регистре) имена файлов, переименует для получения уникальности в списке
    * а то эти господа партнеры заносят с одним именем разные файлы (popov.pdf, POPOV.pdf),
    * а СЭД потом сливает их в один (с историей версий)
    * @param $arr ссылка на массив имен, в каждой строке должен быть элемент ['filename']
    */
    public static function normalizeFileNames(&$arr) {
        $snames = [];
        foreach($arr as &$item) {
            if(!empty($item['doctype']) && method_exists(self::$plgBackend,'getExportDocumentName')) {
                $newName = self::$plgBackend->getExportDocumentName($item['doctype']);
                if(!empty($newName)) $item['filename'] = $newName . '.' . GetFileExtension($item['filename']);
            }
            $fname = $item['filename'];
            $uname = mb_strtoupper($fname);
            if (!in_array($uname, $snames)) {
                $snames[] = $uname;
                continue;
            }
            $no = 1;
            while(true && $no<=200) {
                $namespl = explode('.', $fname);
                $namespl[0] .= '_'.str_pad($no,3,'0',STR_PAD_LEFT);
                $testname = implode('.', $namespl);
                $tuname = mb_strtoupper($testname);
                if (!in_array($tuname, $snames)) break;
                $no++;
            }
            $item['filename'] = $testname;
            $snames[] = $tuname;
        }
        # writeDebugInfo("normalized files: ", $arr);
    }
    /**
    * регистрация расторжения (статус - PM::STATE_DISSOLUTED
    *
    */
    public static function dissolute($module = '', $log_pref='') {
        if (!$module)
            $module = isset(appEnv::$_p['plg']) ? appEnv::$_p['plg'] : '';
        if (!$module) exit('wrong call/no plg parameter');
        $bkend = appEnv::getPluginBackend($module);
        if(!$log_pref) $log_pref = $bkend->getLogPref();

        $canaccept = FALSE;
        if (method_exists($bkend, 'isICAdmin')) {
            $canaccept = $bkend->isICAdmin();
        }
        if (!$canaccept && method_exists($bkend, 'isAdmin')) {
            $canaccept = ($canaccept || $bkend->isAdmin());
        }

        # WriteDebugInfo("dissolute:module=$module, params:", appEnv::$_p);
        if (!$canaccept) {
            appEnv::echoError('err-no-rights');
            exit;
        }
        $id = isset(appEnv::$_p['id']) ? appEnv::$_p['id'] : 0;
        $diss_reason = isset(appEnv::$_p['diss_reason']) ? appEnv::$_p['diss_reason'] : 0;
        $diss_redemp = isset(appEnv::$_p['diss_redemp']) ? appEnv::$_p['diss_redemp'] : 0;
        $diss_date = (isset(appEnv::$_p['diss_date']) && intval(appEnv::$_p['diss_date']) )? trim(appEnv::$_p['diss_date']) : '0';
        $diss_zdate = (isset(appEnv::$_p['diss_zdate']) && intval(appEnv::$_p['diss_zdate']) )? trim(appEnv::$_p['diss_zdate']) : '0';
        # WriteDebugInfo("id=$id, diss_date=$diss_date");
        if ($id<=0 || !($diss_date) || !$diss_reason) {
            appEnv::echoError('err_wrong_call');
            exit;
        }
        if ($diss_reason == '1') $dis_zdate = $diss_date;
        elseif ($diss_reason == '2') {
            if(!intval($diss_zdate)) {
                appEnv::echoError('Не заведена дата заявления о расторжении');
                exit;
            }
            if (to_date($diss_zdate) > to_date($diss_date)) {
                appEnv::echoError('Дата заявления не может быть больше даты расторжения!');
                exit;
            }
        }

        $state_formed = PM::STATE_FORMED;

        $plcdta = self::getPolicyData($module, $id);
        # appEnv::$db->select($tbname, array('where'=>[$idfield=>$id],'singlerow'=>1,'associative'=>1));
        # WriteDebugInfo("found plc data:", $plcdta);
        if (empty($plcdta['policyno'])) {
            appEnv::echoError('err_policy_not_found');
            exit;
        }
        if ($plcdta['stateid'] != $state_formed) {
            appEnv::echoError('err_agmt_not_formed');
            exit;
        }
        $diss_dateymd = to_date($diss_date);
        $diss_zdateymd = to_date($diss_zdate);

        if ($diss_dateymd <= $plcdta['datefrom'] || $diss_dateymd >=$plcdta['datetill']) {
            appEnv::echoError('err_wrong_diss_date');
            exit;
        }

        $arUpd = [
           'stateid' => ($diss_redemp ? PM::STATE_DISS_REDEMP : PM::STATE_DISSOLUTED),
           'diss_date' => $diss_dateymd,
           'diss_zdate' => $diss_zdateymd,
           'diss_reason ' => $diss_reason,
           'substate' => 0, # сбросить статус доработки, если был
        ];

        $result = self::updatePolicy($module, $id, $arUpd); $lineNo = __LINE__;
        # WriteDebugInfo("update result:", $result, 'sql:', appEnv::$db->getLastquery() );
        # WriteDebugInfo("update error:", appEnv::$db->sql_error() );
        if ($result) {
            $diss_reason_txt = self::decodeDissolutionReason($diss_reason);
            $log_type = $log_pref . ((substr($log_pref, -1)==='.')?'':'.') . 'DISSOLUTE';
            appEnv::logEvent($log_type, "Расторжение $plcdta[policyno], дата $diss_date ($diss_reason_txt)",FALSE,$id);
        }
        else {
            AppEnv::logSqlError(__FILE__, __FUNCTION__, $lineNo);
            exit("Ошибка при занесении данных ". (SuperAdminMode() ? AppEnv::$db->sql_error() : ''));
        }

        return true;
    }
    public static function decodeDissolutionReason($code) {
        switch($code) {
            case 1: return 'Неоплата договора';
            case 2: return 'Заявление клиента';
            default: return "Неизвестный код [$code]";
        }
    }
    /**
    * Добавляет новый комментарий к полису
    *
    * @param mixed $module
    * @param mixed $id
    * @param mixed $comment
    * @param $returning - передать true если надо вернуться из ф-ции вместо отправки exit(refresh_cmd)
    */
    public static function setPolicyComment($module, $id, $comment='', $log_pref='', $returning = FALSE) {
        # exit('1' . AjaxResponse::showMessage("Data: $module, [$id], [$comment]"));
        $comment = RusUtils::mb_trim($comment);
        $id = intval($id);
        if (!$id) {
            exit('wrong call(empty id)');
        }

        if(empty($comment)) {
            $result = self::clearPolicyComment($module, $id);
            if($result)
                appEnv::logEvent($log_pref."CLEAR COMMENT", "Удален коментарий к полиcу",FALSE,$id,FALSE, $module);
        }
        else {

            $dta = appEnv::$db->select(PM::T_COMMENTS,
              array('where'=>['module'=>$module, 'policyid'=>$id],
                'orderby'=>'id DESC','singlerow'=>1
              )
            );
            $lastCmt = $dta['plccomment'] ?? FALSE;
            $result = 1;

            if ($comment !== $lastCmt) {
                $new = ['module' => $module, 'policyid'=>$id, 'plccomment'=>$comment,
                  'plcdate'=>'{now}', 'userid' => appEnv::$auth->userid
                ];
                if(!empty($dta['id']))
                    $result = appEnv::$db->update(PM::T_COMMENTS, $new, [ 'id'=>$dta['id'] ]);
                else
                    $result = appEnv::$db->insert(PM::T_COMMENTS, $new);

                if (!$result) exit('Ошибка при вводе в БД : ' . appEnv::$db->sql_error());
                if (!$log_pref) $log_pref = strtoupper($module);
                $log_type = $log_pref . ((substr($log_pref, -1)==='.')?'':'.') . 'COMMENT';
                appEnv::logEvent($log_type, "Введен коментарий к полиcу $comment",FALSE,$id,FALSE, $module);

            }
        }
        if ($returning) return ajaxResponse::setHtml('policycomment', $comment);
        exit("1" . ajaxResponse::setHtml('policycomment', $comment));

    }
    # получить примечание к полису
    public static function getPolicyComment($module, $id) {
        $ret = AppEnv::$db->select(PM::T_COMMENTS, ['where'=>['module'=>$module,
          'policyid'=>$id], 'fields'=>'plccomment','associative'=>0, 'singlerow'=>1]);
        return $ret;
    }
    # Очищаю от ранее введенного комментария
    public static function clearPolicyComment($module, $id) {
        $ret = AppEnv::$db->delete(PM::T_COMMENTS, ['module'=>$module,'policyid'=>$id]);
        return $ret;
    }
    public static function viewPolicyComment($module, $id) {
        # TODO: сделать
        if(is_numeric($id)) $plcid = $id;
        elseif(is_array($id)) $plcid = $id['stmt_id'];
        $dta = appEnv::$db->select(PM::T_COMMENTS,
          array('where'=>['module'=>$module, 'policyid'=>$plcid],
            'orderby'=>'id DESC','singlerow'=>1
          )
        );
        $ret = (isset($dta['plccomment']) ? $dta['plccomment'] : '');
        return $ret;
    }
    /**
    * Вернет HTML код кнопки ввода коментария к полису
    *
    * @param mixed $module модуль плагин
    * @param mixed $id ID полиса
    * @return mixed
    */
    public static function getCommentButton($module, $id) {
        $ret = '';
        if ($module === 'investprod')
            $access = appEnv::$auth->getAccessLevel([INVESTPROD_SUPEROPER,INVESTPROD_COADMIN]);
        else {
            $bkend = appEnv::$_plugins[$module]->getBackend();
            $access = $bkend->isInsCompOfficer();
        }
        if ($access) $ret = "<input type=\"button\" class=\"btn btn-primary\" onclick=\"plcUtils.setComment('$module',$id)\" value=\"Ввести\" title=\"Ввести новый комментарий к полису\"/>";
        return $ret;
    }
    /**
    * Вычислем "новый" возраст выхода на пенсию, с учетом поправок
    * нашего дорогого и любимого, блять, Правительства 06.2018
    *
    * @param mixed $birth дата рождения, YYYY-MM-DD или ДД.ММ.ГГГГ
    * @param mixed $sex пол (M | F)
    * @param mixed $stageStart дата начала непрерывного стажа (если есть 45/40 лет, уменьшаем п-возраст на 2 года
    */
    public static function getPensionAge($birth, $sex='M', $stageStart='') {

        $birthYMD = to_date($birth);

        switch(strtoupper($sex)) {
            case 'M': case 'М': case 'МУЖ': case 'МУЖСКОЙ':
                $sex = 'M'; break;
            default: $sex = 'F';
        }
        $startYear = ['M'=>1958, 'F'=>1963];
        $stageComp = ['M'=>45, 'F'=>40];
        $dobavka = ($sex === 'M') ? 5: 8;

        $initAge = ['M' => 60, 'F' => 55];
        $year = intval($birthYMD);

        $years = $year - $startYear[$sex];
        if ($years<=0) return $initAge[$sex];

        $adding = min($years,$dobavka);
        $ret = $initAge[$sex] + $adding;
        $datePens = AddToDate($birthYMD, $ret);
        $stag = 0;
        if (intval($stageStart)) {
            list($stag, $months) = diffDays2($stageStart,$datePens, true);
        }
        $sdebug ="years: $years, dobavka: $dobavka, to add: $adding, new age: $ret, stag to $datePens:$stag";
        if ($stag >= $stageComp[$sex]+2) {
            $sdebug .= "поправка за стаж (-2)";
            $ret -=2;
        }
        elseif($stag == $stageComp[$sex]+1) {
            $sdebug .= "поправка за стаж (-1)";
            $ret--;
        }
        $sdebug .= " финальный возраст: $ret";
        # return $sdebug;
        return $ret;
    }
    /**
    * сформирует строку с правильным склонением для числа лет/месяцев/дней: "5" => 5 лет, 21 => 21 год
    *
    * @param mixed $val числовое значение
    * @param mixed $factor склоняемое слово (год, месяц, день ...) default: "год"
    * @since 1.04
    */
    public static function skloNumber($val, $factor = '') {
        if ( !$factor ) $factor = 'год';
        switch($factor) {
            case 'год' : $sb = ['год','года','лет']; break;
            case 'месяц' : $sb = ['месяц','месяца','месяцев']; break;
            case 'день' : $sb = ['день','дня','дней']; break;
            default:
                if (substr($factor, -4) === 'одец') {
                    $fbase = substr($factor,0,-2);
                    $sb = [$factor, $fbase.'ца', $fbase.'цов'];
                }
                else {
                    $sb = [$factor, $factor.'а', $factor.'ов'];
                }
                break;
        }

        $strnum2 = intval(substr("$val", -2));
        $strnum1 = intval(substr("$val", -1));
        if ($strnum2>=5 && $strnum2<=20) return "$val $sb[2]";
        if ($strnum1 == 1) return "$val $sb[0]";
        if ($strnum1 == 0) return "$val $sb[2]";
        if ($strnum1 <= 4) return "$val $sb[1]";
        return "$val $sb[2]";
    }

    # для "помесячных" сроков страхования выведет N лет M месяцев
    public static function verboseTerm($period, $factor = 'Y') {
        if($factor === 'Y') {
            return self::skloNumber($period, 'год');
        }
        if ($factor === 'M') {
            $tYears = floor($period/12);
            $tMonths = ($period % 12);
            $ret = ($tYears > 0) ? self::skloNumber($tYears) : '';
            if ($tMonths > 0) $ret .= ($ret ? ' ':'') . self::skloNumber($tMonths, 'месяц');
            return $ret;
        }
        return ('undef. factor:'.$factor);
    }

    # получить коэ-фт выкупной суммы для нужного года, используя переданный список коэф-тов
    # "50,60,73,80,90"
    public static function getRedemptionCoeff($year, $coeffList) {
        if( $year<1 || empty($coeffList) ) return FALSE;
        $coeffs = is_array($coeffList) ? $coeffList : preg_split( '/[,; ]/', $coeffList, -1, PREG_SPLIT_NO_EMPTY );
        $off = min($year-1, count($coeffs)-1);
        return $coeffs[$off];
    }
    /**
    * Добавляю к указанной дате нужное число рабочих дней
    *
    * @param mixed $dateval исходная дата
    * @param mixed $days сколько раб.дней
    * @return дата + N раб.дней от исходной
    */
    public static function addWorkDays($dateval, $days) {
        if (!is_numeric($dateval))
            $dtRet = strtotime(to_date($dateval));
        else $dtRet = intval($dateval);
        # $holidays = ['01-01','01-02','01-03','01-04','01-05','01-06','01-07','01-08', '01-09','02-23',
        #   '03-08','05-01','05-09','06-12','11-04'];
        $nn= 0;
        while($days>0) {
            $dtRet += 86400; # + сутки (секунды = 24 * 3600)
            $ymd = date('Y-m-d', $dtRet);
            if (++$nn >= 1000) exit("addWorkDays: $nn - too many days!");
            if (!self::isHoliday($ymd)) $days--;
        }
        return date('Y-m-d', $dtRet);
    }
    /**
    * сформирует дату через N дней (или рабочих дней - "5wd") от текущей
    * @since 1.88 2024-12-18
    */

    public static function computeStartDate($days, $fromDate = FALSE, $fmt='') {
        $outFmt = ($fmt ? 'Y-m-d' : 'd.m.Y'); # по умолч. делаю в ДД.ММГГГГ, если надо ГГГГ-ММ-ДД, передай 1|TRUE
        if(!intval($fromDate)) { # от текущей даты
            if(is_numeric($days))
                $ret = date($outFmt, strtotime("+$days days"));
            elseif(substr($days,-1)==='w' || substr($days,-2)==='wd' ) { # 5wd, 5w - 5 рабочих дней
                $ret = PlcUtils::addWorkDays(date($outFmt), intval($days));

            }
            else $ret = date($outFmt);
        }
        else { # передали дату от которой строить
            # start sate passed
            $ymd = to_date($fromDate);
            if(is_numeric($days))
                $ret = date($outFmt, strtotime("$ymd +$days days"));
            elseif(substr($days,-1)==='w' || substr($days,-2)==='wd' ) { # 5wd, 5w - 5 рабочих дней
                $ret = PlcUtils::addWorkDays($fromDate, intval($days));

            }
            else $ret = $fromDate;
        }
        return $ret;
    }

    # {upd/2021-08-19} вернет TRUE или 6,7 (для субботы и воскр) если сегодня (или в переданную дату) выходной
    public static function isHoliday($date='') {
        if ($date) {
            $ymd = to_date($date);
            $curYm = substr($ymd,5,5);
            $wday = date('N',strtotime($ymd));
        }
        else {
            $curYm = date('m-d');
            $wday = date('N');
        }
        if ($wday>=6) return $wday;
        return (in_array($curYm, self::$holidays));
    }
    /**
    * Вычислит "стартовую" дату начала действия от текущей
    *
    * @param mixed $mode : число NN = + NN дней от текущей,
    *   'nextmonth' = первое число след.м-ца,
    *   '10wd' = плюс 10 рабочих дней
    * @param mixed $minDays - мин.число дней от тек.даты (3)
    * @return дата в ISO формате: "YYYY-MM-DD"
    * @since 1.21
    */
    public static function getDefaultDateFrom($mode = 10, $minDays = 3) {
        if(is_numeric($mode)) {
            $ret = date('Y-m-d', strtotime("+$mode days"));
        }
        elseif($mode === 'nextmonth') {
            $ret = date('Y-m-d', strtotime('first day of next month'));
        }
        elseif(stripos($mode, 'wd')) {
            $days = max(intval($mode), 1); # mudak protect
            $ret = self::addWorkDays(date('Y-m-d'), $days);
        }

        if ($minDays > 0)
            $ret = max($ret, date('Y-m-d', strtotime("+$minDays days")));
        return $ret;
    }

    /**
    * Перед поиском на "существующие полисы" того же застрахованного сначала можно задать список "плагинов",
    * полисы которых исключены из поиска (например, не искать среди полисов "Плана Б")
    * @param mixed $plglist  Строка, список ИД плагинов через запятую! "planb,insfza,investprod"
    */
    public static function setUwExcludedModules($plglist) {
        self::$uwExcludedModules = is_string($plglist) ? explode(',', $plglist) : $plglist;
    }

    # добавить полис, исключаемый из поиска (например, номер пролонгируемого)
    public static function AddExludedPolicy($policyno) {
        if (in_array($policyno, self::$excludedPolicies)) return;
        self::$excludedPolicies[] = $policyno;
    }
    public static function setInvestSaSlp($saVal) {
        self::$investSaSlp = floatval($saVal);
    }
    public static function setInvestSaSns($saVal) {
        self::$investSaSns = floatval($saVal);
    }
    # принудительно включаю/выключаю происк других полисов на того же застрахованного
    public static function seekOtherPolicyForInsured($bValue) {
        self::$seekOtherPolicies = $bValue;
    }
    /**
    * Единый проверяльщик договора на андеррайтинг (по кумуляции или наличию других полисов на того же застрахованного)
    * @param $persontype 'child' - проверять по ребенку, ''-по "обычному застрахованному
    * @param $module название модуля (investprod, plgkpp,...)
    * @param $plcid 0 или ИД полиса, если он уже в базе (чтоб при проверке исключить двойной счет)
    * @param $fam,$imia,$otch,$docser,$docno - фамилия, имя, отчество, серия и номер док-та
    * @param $mysa, $curr - проверяемая сумма СС и код валюты RUR|USD
    * @param $birthday - дата рождения (проверка по ФИО+дата рожд) TODO!
    * @param $policyno - исключая указанный номер полиса
    * @param $seekRisk - ID риска (смерти) который ищем для подсчета кумулятивной СС по риску
    * @param $limitId - ИД лимита стр.суммы (кумул.лимит)
    * @param $startDate - предполагаемая дата нач.д-вия (искать старые полисы, действуюшщие на эту дату)
    * @return 0 - ничего не найдено,
    *         number - код причины: (20=уже есть "действующие/не аннулированные" полисы с тем же застрахованным,)
    *         array() - массив СС по СЛП, с валютами: [USD]=>20000,[RUR]=>900000
    */
    public static function performUwChecks($persontype,$module,$plcid,$fam,$imia,
      $otch,$docser,$docno, $mysa=0,$curr='',$birthday='', $policyno='', $seekRisk = '', $limitId=0, $startDate = FALSE)
    {
        self::$alarmedLimit = 0;
        if(self::$debug) writeDebugInfo("performUwChecks('$persontype','$module',$plcid,'$fam' ...startdate='$startDate'), sa=$mysa, ",
           debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        if (empty($startDate) || !self::isDateValue($startDate)) $startDate = date('Y-m-d');
        else $startDate = to_date($startDate);

        list($subjAge) = DiffDays($birthday,$startDate,1);

        if($seekRisk === 'ALLDEATH' || self::$uwProdType === PM::PRODTYPE_INVEST) {
            # активирую кумулятивный поиск по рискам СНС и СЛП с лимитами из настроек - ins_limit_cumdeath1_rur,ins_limit_cumdeath2_rur
            $seekRisk = 'death_acc,death_any';
            self::$deathLimit1 = appEnv::getConfigValue('ins_limit_death_any',60000000); # лимит по СЛП
            self::$deathLimit2 = appEnv::getConfigValue('ins_limit_death_acc',12000000); # лимит по СНС
        }
        elseif (empty($seekRisk)) $seekRisk = self::$uw_deathSeekRisk;
        # $data = array('RUR'=>0, 'USD'=>0);
        if (self::$debug) {
            WriteDebugInfo("performUwChecks(type=$persontype,module=$module,id=$plcid,fam=$fam,imia=$imia,otch=$otch,docser=$docser,docno=$docno, ",
              "sa=$mysa,curr=$curr,birth=$birthday): /curr=[$curr], sa=$mysa, seekrisk=", print_r($seekRisk,1),
              ", limitID=$limitId)...");
            # WriteDebugInfo("limits:", PolicyModel::$sa_limits);
            WriteDebugInfo('self::$uwExcludedModules: ', self::$uwExcludedModules);
        }
        $curr = mb_strtoupper($curr);
        # сначала просто ищу любой полис в bn_policy или alf_agreements с тем же застрахованным и датой окончания большей today
        $usdKoeff = appEnv::getConfigValue('intrate_usd', 60); // для приведения всего в рубли

        $plclist = 0;
        $uwcode = 0;
        $cumulSa = 0;
        $invFind = [
          'lastname' => $fam,
          'firstname' => $imia,
          'middlename' => $otch,
        ];
        $seekInvest = TRUE;
        if(self::$seekOtherPolicies) {
        # {upd/2024-03-22} для ввода ИСЖ плоиса делаем полиск всех ИСЖ полисов и считаем кумул.суммы по СЛП, СНС
            $plcList = DataFind::findPolicyByInsured($fam,$imia,$otch,$birthday, $startDate, TRUE,0, $plcid);
            if(self::$debug) writeDebugInfo("полисы на того же ЗВ: ", $plcList);
            # нашел ВСЕ полисы (ИСЖ, не ИСЖ, теперь в зав.от того какой полис оформляется, смотри кумул.суммы и/или наличие других полисов
            if(!is_array($plcList) || count($plcList)==0) return; # полисов на того же ЗР нет
        }
        if($module === PM::INVEST2) {
            # ИСЖ - ищем ВСЕ полисы ИСЖ на застрахованного, считаем кумулятивно СС по СЛП и СНС, если превышения нет, пропускаем без UW
            $uwcode = self::checkInvestLimits($plcList);
            /*
            exit('1' . AjaxResponse::showMessage("investSaSlp: ".self::$investSaSlp."<br>investSaSns:".self::$investSaSns
              . '<br>ИСЖ полисы на ЗР: <pre>' . print_r($plcList,1) . '</pre>'));
            */
        }
        else {
            # ввод не ИСЖ полиса, наличие других полисов на ЗР (не ДМС) - увод на андеррайтинг
            $uwcode = 0;
            foreach($plcList as $plc) {
                if(isset($plc['module']) && in_array($plc['module'], self::$dmsModules)) # любой не ДМС полис игнорим
                    continue;
                else
                    $uwcode = PM::UW_REASON_INSURED_EXIST;
            }
            if($uwcode) self::setUwReasonHardness(10, $uwcode);
        }
        if(self::$debug) writeDebugInfo("uwcode по результата поиска полисов и СС: [$uwcode]");
        return $uwcode;
        # весь код ниже устарел?
        # не ИСЖ, любое нахождение ЛЮБОГО полиса - увод на UW
        if (in_array('investprod',self::$uwExcludedModules) ) $seekInvest = FALSE;

        if ($seekInvest) { # поиск в инвест-полисах нужен
            if (!empty($birthday) && intval($birthday))
                $invFind['birthdate'] = to_date($birthday);
            if (!empty($docser))
                $invFind[] = self::docSerVariants($docser, 'passportseries');
            if (!empty($docno))
                $invFind['passport'] = $docno;

            $invPeople = appEnv::$db->select('bn_individual', array('fields'=>'id', 'where'=>$invFind,'associative'=>0,'orderby'=>'id DESC'));
            if (self::$debug) WriteDebugInfo("invest seek person SQL:", appEnv::$db->getLastQuery());
            if (is_array($invPeople) && count($invPeople)>0) {

                if (self::$debug) WriteDebugInfo("found people in bn_individual for $fam,$imia,$otch,$docser,$docno");
                $strP = implode(',',$invPeople);
                $plclist = appEnv::$db->select('bn_policy p,bn_currency cr', [
                  'where' => "(p.insurerid IN($strP) OR p.insuredid IN($strP)) AND (p.stateid NOT IN(9,10,50,60)) AND p.datetill>'$startDate' AND p.currencyid=cr.id"
                    . (($module=='investprod' && $plcid>0) ? " AND (p.id<>$plcid)":''), # исключаю сам этот полис
                  'fields' =>'p.id,p.policyno,p.datefrom,p.datetill,p.payamount/100 payamount,cr.isocode'
                ]);
                if (self::$debug) {
                    WriteDebugInfo("last qry/inv): ". appEnv::$db->getLastQuery());
                    WriteDebugInfo("RESULT/inv plclist : ", $plclist);
                }
            }

            if (is_array($plclist) && count($plclist)>0) {
                if (self::$debug) WriteDebugInfo("invest plclist: ",$plclist);
                $invFound = FALSE;
                foreach($plclist as $p) {
                    # WriteDebugInfo('found investprod policy:', $p);
                    if (!in_array($p['policyno'], self::$excludedPolicies)) {
                        PolicyModel::addUwDetails($p['policyno'],$p['datefrom'],$p['datetill'], floatval($p['payamount']), $p['isocode']);
                        $invFound = TRUE;
                    }
                }
                if ($invFound) {
                    if(self::$debug) {
                        writeDebugInfo("Found, полисы на того же застрах:", $plclist);
                        writeDebugInfo("excluded:", self::$excludedPolicies);
                    }
                    if (self::$uwCheckingMode == 1)  {
                        $uwcode = PM::UW_REASON_INSURED_EXIST;
                        self::setUwReasonHardness(1, $uwcode);
                        # writeDebugInfo("set to UW_REASON_INSURED_EXIST");
                        self::$warn_message = 'UW: Found in Invest';
                        # if (self::$debug < 9) return $uwcode;
                    }
                    elseif(self::$uwCheckingMode == 2 && $mysa>0 && $curr !='') {
                        # TODO: делать проверку по всем полисам - сумму СС по искам смерти ?
                        # для инвестов можно не проверять, в самом investprod потом делается своя проверка по кумул. СЛП и СНС
                        $summa = $mysa * (($curr === 'USD') ? $usdKoeff : 1);
                        foreach($plclist as $i => $pl) {
                            $summa += $pl['payamount'] * (($pl['isocode'] === 'USD') ? $usdKoeff:1);
                        }
                        $slpLimit = appEnv::getConfigValue('ins_limit_death_any',60000000);
                        # WriteDebugInfo("$module/[$plcid]:кумулятивная сумма по инвест-полисам $summa , лимит: $slpLimit");
                        if ($summa > $slpLimit) {
                            $uwcode = PM::UW_REASON_DEATHLIMITCUMUL;
                            self::setUwReasonHardness(10, $uwcode);
                            # WriteDebugInfo("$module/[$plcid]:кумулятивная сумма по инвест-полисам превысила лимит: $summa > $slpLimit");
                            return $uwcode;
                        }
                        # exit("TODO: проверить сумму с учетом переданной $mysa $curr , cumul suma: $summa");
                    }
                }
            }
        }

        $plclist = [];

        $agmFind = [
          "stmt_id<>" . intval($plcid), # исключаю свой проверяемый полис
          'fam' => $fam,
          'imia' => $imia,
          'otch' => $otch,
        ];
        if (!empty($birthday) && intval($birthday))
           $agmFind['birth'] = to_date($birthday);

        /****
        # {upd/2023-12-06} - убираю поиск внутри ALFO, весь поиск - только в данных Лизы!
        if (self::SEARCH_BY_PASSPORT) {
            # {upd/2020-12-20} по паспорту больше не ищем (могли сменить)
            if(!empty($docser))
                $agmFind[] = self::docSerVariants($docser,'docser');
            if(!empty($docno))
                $agmFind['docno'] = $docno;
        }
        ***/
        # writeDebugInfo("поиск на человека: ", $agmFind);
        $agmPeople = appEnv::$db->select(PM::T_INDIVIDUAL, array('fields'=>'stmt_id', 'where'=>$agmFind,'associative'=>0));
        if (self::$debug) {
            WriteDebugInfo("alf_agreem seek person SQL:", appEnv::$db->getLastQuery());
        }

        if (is_array($agmPeople) && count($agmPeople)>0) {
            if(self::$debug) writeDebugInfo("проверка полисов по найденным для на людей ", $agmPeople, ' found by critery:', $agmFind);
            $strlist = implode(',',$agmPeople);
            $whereAgm = [
              "stmt_id IN($strlist)",
              "stateid IN(0,1,2,3,5,7,11,30)",
              "datetill>'$startDate'",
            ];
            if($module === PM::INVEST2) # ИСЖ ищу ФИО только среди ИСЖ-2 полисов
                $whereAgm[] = "module='invins'";
            else $whereAgm[] = "module NOT IN('invins','investprod')";

            # WriteDebugInfo("module: $module, where in alf_agreements:", $whereagm);

            $plclist = appEnv::$db->select(PM::T_POLICIES,[
              'where'=>$whereAgm,
              'fields'=>'stmt_id,module,policyno,stateid,datefrom,datetill,policy_sa,policy_prem payamount,currency'
            ]);

            if($module === PM::INVEST2) {
                # ищу действующие полисы в старом investprod

                $plclist2 = self::findOldInvestPolicies($fam,$imia,$otch,$birthday,$startDate);
                if(is_array($plclist2)) {
                    if(is_array($plclist)) $plclist = array_merge($plclist,$plclist2);
                    else $plclist = $plclist2;
                }
            }
            # exit('1' . AjaxResponse::showMessage('previous inv list: <pre>' . print_r($plclist,1) . '</pre>'));
            if (self::$debug) {
                WriteDebugInfo("last qry/agm): ". appEnv::$db->getLastQuery());
                WriteDebugInfo("found alf plclist:", $plclist);
            }
        }

        $foundPoliciesWithSameSubj = FALSE;

        if(in_array($module, ['invins'])) {
            self::checkInvestLimits($plclist);
        }
        elseif (is_array($plclist) && count($plclist)>0) {
            $riskCond = FALSE;
            if (is_array($seekRisk) && count($seekRisk) ) {
                $seekRisk = implode(',',$seekRisk);
                # если несколько рисков через"," - делаю правильный SQL: riskid IN('id1','id2','id3')"

                $riskCond = "riskid IN('" . str_replace(',', "','",$seekRisk) . "')";
            }
            foreach($plclist as $nn => $p) {

                if (in_array($p['policyno'], self::$excludedPolicies)) {
                    if(self::$debug) writeDebugInfo("пропускаю полис (исключен из отбора/пролонгация): ", $p['policyno']);
                    continue; # полис из списка исключаемых, пропуск!
                }
                $foundPoliciesWithSameSubj = $p['policyno'];
                PolicyModel::addUwDetails($p['policyno'],$p['datefrom'],$p['datetill'], floatval($p['payamount']) );
                if(!empty($riskCond)) {
                    $rskdta = appEnv::$db->select(PM::T_AGRISKS, [
                      'where' => [ 'stmt_id'=>$p['stmt_id'], $riskCond ],
                      'singlerow'=>1
                    ]);
                    if (self::$debug>1) WriteDebugInfo("seek by risk SQL: ", appEnv::$db->getLastquery(), ' error: ',  appEnv::$db->sql_error());
                    if (self::$debug>1) WriteDebugInfo("seek by risk plc data: ", $rskdta);
                    if (isset($rskdta['risksa']))
                        $cumulSa += $rskdta['risksa'] * ($p['currency']=='RUR' ? 1 : $usdKoeff);
                }
            }
            if(!empty($foundPoliciesWithSameSubj)) {

                if (self::$debug) WriteDebugInfo("found cumulative sa: ", $cumulSa, ', uwCheckingMode: ', self::$uwCheckingMode );
                if (self::$uwCheckingMode == 2 && $mysa>0 && $curr !='') {
                    # TODO: делать проверку по всем полисам - сумму СС по рискам смерти ?
                    # если все найденные полисы исключены, то нефига тут складывать лимиты

                    if ($limitId) $saLimit = self::getCumulRiskLimit($limitId);
                    else $saLimit = appEnv::getConfigValue('ins_limit_death_rur',10500000);

                    $summa = $mysa * (($curr === 'USD') ? $usdKoeff : 1);
                    if(self::$deathLimit2 > 0 && ($summa + $cumulSa) > self::$deathLimit2) {
                        $uwcode = PM::UW_REASON_DEATHLIMITCUMUL2;
                        self::setUwReasonHardness(10, $uwcode);
                    }
                    elseif(self::$deathLimit1 > 0 && ($summa + $cumulSa) > self::$deathLimit1) {
                        $uwcode = PM::UW_REASON_DEATHLIMITCUMUL;
                        self::setUwReasonHardness(10, $uwcode);
                    }
                    elseif (($summa + $cumulSa) > $saLimit) {
                        $uwcode = ($persontype === 'child') ? PM::UW_REASON_CHILD_LIMIT : PM::UW_REASON_DEATHLIMITCUMUL;
                        self::setUwReasonHardness(10, $uwcode);
                        self::$alarmedLimit = $saLimit;
                        if (self::$debug) WriteDebugInfo("Есть превышение кумул.лимита: $summa + $cumulSa > $saLimit");
                    }
                    elseif(self::$debug) WriteDebugInfo("сумма $mysa + $cumulSa не превзошла лимит ",$saLimit);
                    # проверка на лимиты
                }
                elseif (self::$uwCheckingMode == 1 || self::$uwCheckingMode==3) { # временно заглушил ==1, не готова проверка лимитов
                    if ($subjAge>=18) {
                        $uwcode = PM::UW_REASON_INSURED_EXIST;
                        if(self::$debug) writeDebugInfo("Регистрирую найденный полис на того же ЗВ ", $p);
                        self::setUwReasonHardness(1, $uwcode);
                        self::$warn_message = 'Найдены полисы на того же Застрахованного';
                        # writeDebugInfo("set to UW_REASON_INSURED_EXIST");
                    }
                    else { # нашлось для ребенка
                        $uwcode = PM::UW_REASON_CHILD_EXIST;
                        self::setUwReasonHardness(1,$uwcode);
                        self::$warn_message = 'Найдены полисы на того же Застрахованного ребенка';
                    }
                    if(self::$debug) writeDebugInfo("uwcode=$uwcode, в ALFO ".self::$warn_message);
                }
                # else exit("Застр. нашелся, но self::uwCheckingMode = ".self::$uwCheckingMode);

            }
        }
        # exit('TODO: поиск старых:'.$uwcode); # DEBUG STOP

        if (self::$debug>5) exit('TEMP stop! uwcode: '.$uwcode);
        if ($uwcode > 0 ) return $uwcode; # && (self::$uwCheckingMode== 1 || $mysa == 0)
        $extSeek = FALSE;
        if (self::$SEARCH_IN_EXT_POLICIES) {
            # if (self::$SEARCH_IN_EXT_POLICIES ) { # === 'CC'
                # {upd/2020-21-01} ищу по таблице контрактов в БД кабинета агента-клиента 2023-12-06 - self::$uwProdType
                $getRisks = (self::$uwProdType === PM::PRODTYPE_INVEST);
                $extSeek = DataFind::findPolicyByInsured($fam, $imia, $otch,$birthday,$startDate, $getRisks, self::$uwProdType); #

            # }
            if (self::$debug) {
                WriteDebugInfo("seek in LISA data result:", $extSeek);
            }
            $extFound = FALSE;
            if (is_array($extSeek) && count($extSeek)>0) {
                foreach($extSeek as $p) {
                    if (in_array($p['policyno'], self::$excludedPolicies)) {
                        if (self::$debug) writeDebugInfo("inLISA: $p[policyno] - найден, исключен из проверки");
                        continue;
                    }
                    $extFound = TRUE;
                }
            }

            if ($extFound) {
                if( self::$debug && AppEnv::isLocalEnv() ) {
                    writeDebugInfo("Есть полисы на того же застрах(LISA):", $extSeek);
                    writeDebugInfo("excluded: ", self::$excludedPolicies);
                }
                if ($subjAge>=18) {
                    $uwcode = PM::UW_REASON_INSURED_EXIST;
                    self::setUwReasonHardness(1, $uwcode);
                    self::$warn_message = 'Найдены полисы на того же Застрахованного';
                    if(self::$debug) writeDebugInfo("set to UW_REASON_INSURED_EXIST, exclude policies: ", self::$excludedPolicies, 'found same insured plc: ', $extSeek);
                }
                else { # нашлось для ребенка
                    $uwcode = PM::UW_REASON_CHILD_EXIST;
                    self::setUwReasonHardness(1, $uwcode);
                    self::$warn_message = 'Найдены полисы на того же Застрахованного ребенка';
                    if(self::$debug) writeDebugInfo("set to UW_REASON_CHILD_EXIST, exclude policies: ", self::$excludedPolicies, 'found same insured plc: ', $extSeek);
                }
                # $policyno,$datefrom, $datetill, $risksum=0, $currency='RUR')
                PolicyModel::addUwDetails($extSeek[0]['policyno'],$extSeek[0]['datefrom'],$extSeek[0]['datetill']);

                # self::$foundOtherPolicy = $extSeek[0]['policyno'];
                if (self::$debug) writeDebugInfo("returning $uwcode");
                if (self::$debug < 9) return $uwcode;
            }
        }
        # exit('checking tests '.print_r($invPeople,1) . 'agmt:'. print_r($plclist,1)); # debug aborting

        if (self::$debug> 9) exit('UW code found:'.$uwcode . ' '.self::$warn_message );

        return $uwcode; # если лимиты не превышены, все OK!
    }
    public static function checkInvestLimits($plcList) {
        self::$deathLimit1 = appEnv::getConfigValue('ins_limit_death_any',60000000); # лимит по СЛП
        self::$deathLimit2 = appEnv::getConfigValue('ins_limit_death_acc',12000000); # лимит по СНС
        $retcode = 0;
        # writeDebugInfo("sa-slp: ".self::$investSaSlp, "sa-SNS:",self::$investSaSns," deathLimit SLP: ",  self::$deathLimit1, 'limit SNS: ',self::$deathLimit2);
        # writeDebugInfo("checkInvestLimits, found policies: ", $plcList);
        if(!is_array($plcList) || count($plcList)==0) return;
        $cumulSlp = $cumulSns = 0;
        $usdRate = AppEnv::getConfigValue('intrate_usd',60);

        foreach($plcList as $invPlc) {
            if(empty($invPlc['invest'])) { # хотя бы один не ИСЖ полис - увожу н аUW!
                $retcode = PM::UW_REASON_INSURED_EXIST;
                if(self::$debug) writeDebugInfo("не ИСЖ полис на того же ЗВ, включаю UW: UW_REASON_INSURED_EXIST ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
                break;
            }

            foreach($invPlc['risks'] as $rsk) {
                $sa = $rsk['sa'];
                if($rsk['currency'] ==='USD') $sa *= $usdRate;
                if($rsk['riskename'] === 'death_any') $cumulSlp += $sa;
                elseif($rsk['riskename'] === 'death_acc') $cumulSns += $sa;
            }
            # writeDebugInfo("$invPlc[policyno]: currently slp=$cumulSlp, sns=$cumulSns, $invPlc[currency]");
        }
        if($retcode==0) {
            $fullSlp = $cumulSlp + self::$investSaSlp;
            $fullSns = $cumulSns + self::$investSaSns;
            if(self::$debug) writeDebugInfo("предыд.полисы: $cumulSlp, $cumulSns<br>Итого с новым полисом: $fullSlp, $fullSns");
            # exit('1' . AjaxResponse::showMessage("предыд.полисы: $cumulSlp, $cumulSns<br>Итого: $fullSlp, $fullSns"));
            if($fullSlp > self::$deathLimit1 || $fullSns > self::$deathLimit2) {
                $retcode = PM::UW_REASON_DEATHLIMITCUMUL;
                if(self::$debug) writeDebugInfo("превышение кумул.лимитов $fullSlp, $fullSns>".self::$deathLimit2);
            }
            elseif(self::$debug) writeDebugInfo("нет превышения");
        }

        if($retcode>0) self::setUwReasonHardness(10, $retcode);

        return $retcode;
    }
    public static function getAlarmedLimit() {
        return self::$alarmedLimit;
    }
    # задаю режим проверки по существ.полисам
    public static function setUwCheckMode($mode=1) {
        self::$uwCheckingMode = $mode;
    }
    #{upd/2021-02-12} - добавляю возможность исключать из поиска во внешних полисах все ИСЖ
    public static function UwCheckSeekInvest($mode=1) {
        self::$uwseekInvest = $mode;
    }
    /**
    * docSerVariants():
    * т.к. серию паспорта часто заносят как NNMM так и "NN MM" с пробелом, делаю docser IN('NNMM", 'NN MM')
    * чтобы при поиске по паспорту нашлись любые варианты
    */
    public static function docSerVariants($docser, $fieldname='docser') {
        $ds1 = str_replace(' ','', $docser);
        $ds2 = substr($ds1,0,2) . ' '. substr($ds1,2);
        return "$fieldname IN('$ds1','$ds2')";
    }
    /**
    * Для андеррайтерской проверки не превышения стр.сумм над лимитами,
    * зависящими от возраста, годового дохода, профессии застрахованного
    *
    * @param mixed $age возраст
    * @param mixed $profession профессия
    * @param mixed $yearincome годовой доход в руб.
    */
    public static function getSaLimits($age, $profession, $yearincome=0, $currency = 'RUR') {
        # WriteDebugInfo("getSaLimits($age, $profession, $yearincome, $currency)...");
        $koeffs = [];
        $limits = [];
        $usdKoeff = appEnv::getConfigValue('intrate_usd', 60); // для приведения годового дохода к валюте договора
        $eurKoeff = 70;
        if ($age <=0) die('getSaLimits: передан нулевой возраст');
        # WriteDebugInfo("getSaLimits($age, $profession, $yearincome, $currency)");
        foreach(self::$uw_coeffs as $no => $item) {
            if ($age <= $item['age']) {
                $koeffs = $item;
                break;
            }
        }
        # WriteDebugInfo("age $age, found item:", $koeffs);
        if ($yearincome > 0 && isset($koeffs['life'])) {

            if ($currency == 'USD') $yearincome /= $usdKoeff;
            elseif ($currency == 'EUR') $yearincome /= $eurKoeff;

            $limits = ['life' => $yearincome * $koeffs['life'],
                'ci'  => min( $yearincome * $koeffs['ci'], self::$uw_max_sa['ci'][$currency]),
                'tpd' => $yearincome * $koeffs['tpd'],
                'trauma' => min( $yearincome * $koeffs['trauma'], self::$uw_max_sa['trauma'][$currency]),
            ];
        }
        if (!empty($profession)) {
            if ($profession === 'студент' || mb_stripos($profession,  'безработн',0, MAINCHARSET)!==FALSE) {
                # WriteDebugInfo("setting студент/безработный values");
                $limits['death_total'] = (($currency=='RUR') ? 3000000 : 50000);
                $limits['trauma'] = (($currency=='RUR') ? 450000 : 7500);
                $limits['ci-formula'] = "death_total";
                $limits['tpd-formula'] = "death_total"; # было /2
                $limits['trauma-formula'] = "death_total";  # было /2
            }
            elseif ($profession === 'пенсионер' ) {
                # WriteDebugInfo("setting пенсионер values");
                $limits['death_total'] = (($currency=='RUR') ? 3000000 : 50000);
                $limits['ci'] = $limits['tpd'] = 0;
                $limits['trauma'] = (($currency=='RUR') ? 450000 : 7500);
                $limits['trauma-formula'] = "death_total";  # было /2
            }
            elseif ($profession === 'домохозяйка' ) {
                # WriteDebugInfo("setting домохозяйка values");
                $limits['death_total'] = (($currency=='RUR') ? 4500000 : 75000);
                $limits['trauma'] = (($currency=='RUR') ? 450000 : 7500);
                $limits['ci-formula'] = "death_total";
                $limits['tpd-formula'] = "death_total"; # было /2
                $limits['trauma-formula'] = "death_total"; # было /2
            }
        }
        # и добавим мин.лимиты на суммарную СС по рискам смерти
        if (FALSE) { # пока не будем...спросить у Дунаева - для Юникредита - надо или нет!
            $limits['min_life_ad'] = self::$uw_min_sa[$currency];
        }
        # WriteDebugInfo("returning UW limits:", $limits);
        return $limits;
    }

    # добавляю свой маппинг имен рисков
    public static function addRiskName($riskid, $longName, $shortName = 0) {
        if(!isset(self::$cached['rsk'])) self::$cached['rsk'] = [];
        self::$cached['rsk'][$riskid] = [
            'longname' => $longName,
            'shortname' => (($shortName) ? $shortName : $longName),
        ];
        # writeDebugInfo("manual add risk $riskid=$longName ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
    }
    public static function setAdultRiskNames($moda = TRUE) {
        self::$useAdultRiskNames = $moda;
    }
    # перенос из Policymodel - получение названия риска (по умолч.=полного)
    # ename = TRUE перпедать еще и riskename (для формирования имени поля ввода)
    public static function getRiskName($riskid, $what = 'longname', $ename = FALSE) {
        # риск ОУСВ - захардкодить!
        if($riskid === 'wop') {
            if($what === 'longname') return ('Инвалидность Застрахованного с установлением I, II группы '
              . 'инвалидности в результате несчастного случая или заболевания (с освобождением от уплаты страховых взносов по отдельным страховым рискам)');
            elseif($what==='riskename') return 'wop';
            return 'ОУСВ';
        }
        $cashedId = "$riskid-$what-$ename";
        if (isset(self::$cached['rsk'][$cashedId])) {
            # writeDebugInfo("$riskid: use cached risk name: ", self::$cached['rsk'][$riskid][$what]);
            return self::$cached['rsk'][$cashedId];
        }
        $where = is_numeric($riskid) ? "id='$riskid'" : "riskename='$riskid'";
        $dta = appEnv::$db->select(PM::T_RISKS,array('where'=>$where, 'singlerow'=>1));

        if (!isset($dta['exportname'])) return "[unknown risk : $where]";
        $ret = ($what ==='*') ? $dta : $dta[$what] ;
        # {upd/2025-03-06} если переключили на использоание "взрослых" названий - использую при непустом:
        if($what === 'longname' && self::$useAdultRiskNames && !empty($dta['adultname'])) {
            $ret = $dta['adultname'];
            # writeDebugInfo("$riskid: used adult risk name $ret");
        }

        if($ename) {
            $ret = [ $ret, $dta['riskename'] ];
        }
        if(!isset(self::$cached['rsk'])) self::$cached['rsk'] = [];
        self::$cached['rsk'][$cashedId] = $ret;
        return $ret;
    }

    /**
    * Вернет (если сможет) тип риска для поиска "возрастных" лимитов
    *
    * @param mixed $riskid
    */
    public static function getRiskType($riskid) {
        foreach(self::$risk_mapping as $rtype => $rlist) {
            if (in_array($riskid, $rlist)) return $rtype;
        }
        return '';
    }

    /**
    * получаю полное инфо о риске по кодировке и коду риска в Лизе
    * @param mixed $codirovka
    * @param mixed $lisId
    * @param mixed $simple - если TRUE|1 нужно вернуть только ALFO=шный RiksID
    * @since 1.63
    */
    public static function getRiskByLisaId($codirovka,$lisaId, $simple=FALSE) {
        $rskCfg = AppEnv::$db->select(PM::T_EXPORTCFG, ['where'=>"FIND_IN_SET('$codirovka',product)",'singlerow'=>1]);
        if(!isset($rskCfg['id'])) return FALSE;
        $cfgId = (empty($rskCfg['binded_to']) ? $rskCfg['id'] : $rskCfg['binded_to']);
        # return $cfgId;
        $data = AppEnv::$db->select(['r'=>PM::T_RISKS, 'e'=>PM::T_EXPORTRISKS], [
          'fields'=>'r.*',
          'where'=>['e.productid'=>$cfgId, 'e.exp_riskid'=>$lisaId, "r.id=e.riskid"],'singlerow'=>1]
        );
        if($simple && isset($data['riskename'])) return $data['riskename'];
        return $data;
    }
    public static function nonWorkingProfession($prof) {
        $dta = appEnv::$db->select(PM::T_PROFESSIONS, ['where'=>['profession' => $prof], 'singlerow'=>1]);
        if (isset($dta['incomeclass'])) return ($dta['incomeclass'] !=='1');
        else
            return (in_array($prof, InsObjects::$nonWorkingProf));
    }
    public static function getProfessionRiskClass($prof) {
        if ($prof === 'не выбрана (1 класс)') return 1;
        elseif ($prof === 'не выбрана (2 класс)') return 2;
        elseif ($prof === 'не выбрана (3 класс)') return 3;
        elseif ($prof === 'не выбрана (4 класс)') return 4;
        $dta = appEnv::$db->select(PM::T_PROFESSIONS, ['where'=>['profession' => $prof], 'singlerow'=>1]);
        if (isset($dta['riskclass'])) return $dta['riskclass'];
        else
            return 0;
    }
    /**
    * Добавляем свою кнопу для экрана просмотра полиса
    *
    * @param mixed $id Button ID
    * @param mixed $label Текст на кнопке
    * @param mixed $onclick событие в атрибуте onclick="..."
    * @param mixed $jsCode JS-код, добавится в тело формы чтоб было что позвать при нажатии кнопы
    * @param mixed $title всплыв.подсказка для кнопки
    * @param mixed $hidden TRUE чтобы сделать начальное состояние невидимым (display:none)
    */
    public static function addUserButton($id, $label, $onclick, $jsCode='', $title = FALSE, $hidden = FALSE) {
        $btnTtl = ($title ? "title=\"$title\"" : '');
        $hdStyle = (($hidden) ? "style='display:none'" : '');
        # writeDebugInfo("addUserButton($id,...)");
        self::$_userButtons[$id] = array(
            'html'=> "<span id='{$id}' $hdStyle><input type=\"button\" id=\"buttom_{$id}\" class=\"btn btn-primary\" onclick=\"$onclick\" value=\"$label\" $btnTtl/> </span>"
           # ,'display' => true
        );
        if ($jsCode) {
            HeaderHelper::addJsCode($jsCode,'tail');
        }
    }
    # прячу/вывожу пользовательскую кнопку на форме просмотра полиса
    public static function enableUserBtn($btnid, $param=1) {
        if(isset(self::$_userButtons[$btnid])) {
            self::$_userButtons[$btnid]['display'] = !empty($param);
            writeDebugInfo("set view for $btnid: [$param]");
        }
    }
    # вернет список ИД стран с признаком ctype=1 (Россия)
    public static function getRussiaCodes($withSng = FALSE) {
        $dta = appEnv::$db->select('alf_countries', ['fields'=>'id', 'where'=>'ctype=1', 'associative'=>0]);
        return $dta;
    }
    public static function getNextBillNo($codirovka, $prefix = '', $postfix = '', $nolen=0) {
        $baseNo = \NumberProvide::getNext($codirovka, appEnv::$auth->deptid, PM::RANGE_BILLS);
        $postf = '';
        if ($postfix) {
            $postf = strtr($postfix, ['[yyyy]'=>date('Y'), '[yy]'=>substr(date('Y'),-2)]);
        }
        if ($nolen <= 0) $nolen = self::BILL_NUMBER_LEN;
        return ($prefix . str_pad($baseNo, $nolen, '0', STR_PAD_LEFT) . $postf);
    }
    /**
    * вернет список возможных ИД формул (алгоритмов расчета) лимитов для выставления по риску
    *
    */
    public static function getLimitFormulas() {
        return [
            '' => '--нет--',
            'endowment' => '100% СС по риску Дожития',
            'death_any' => '100% СС по риску Смерти ЛП',
            'death_total' => 'Суммарная СС по рискам Смерти ЛП,НС,ДТП',
            'death_total/2' => '1/2 от суммарной СС по рискам Смерти ЛП,НС,ДТП',
            'total_premium' => 'Проверка суммарного взноса по полису'
            # добавлять здесь по вкусу!
        ];
    }
    /**
    * Вернет HTML код со всеми юзерскими кнопками
    *
    */
    public static function drawUserButtons($module='') {
        $ret = '';
        foreach(self::$_userButtons as $id => $btn) {
            $ret .= $btn['html'];
        }
        return $ret;
    }

    /**
    * Генерирует PDF с пакетом документов "полис"
    *
    * @param mixed $module ИД модуля
    * @param mixed $id  ИД полиса
    * @param mixed $mode 'draft' если нужен черновик (откл.штампы, включить слово "ЧЕРНОВИК" на всех листах)
    */
    public static function createPdfPolicy($module, $id, $mode='') {
        if (is_string($module)) {
            $backend = appEnv::getPluginBackend($module);
        }
        elseif (is_object($module)) $backend = $module;
        else die ($module . ' - Неизвестный модуль, формирование полиса невозможно');
        if (!is_object($backend)) die ("модуль backend не подключился");
        if (is_callable('appEnv::cleanTmpFolder'))
            appEnv::cleanTmpFolder();
        $flname = $backend->print_pack($id, true, $mode);
        return $flname;
    }
    /**
    * Привязывает (или отвязывает) зарегистрированного клиента к агенту
    *
    * @param mixed $clientId ИД клиента (FK arjagent_user.userid)
    * @param mixed $agentId ИД агента (FK arjagent_user.userid))
    * @param mixed $action TRUE (default) - привязать, FALSE - отвязать
    */
    public static function bindClientToAgent($clientId, $agentId, $action = true) {
        $where = [ 'clientid' => $clientId, 'agentid' =>$agentId ];
        $ret = true;
        $exist = appEnv::$db->select(PM::T_AGENT_CLIENT, [ 'where' => $where ]);
        if (!empty($exist[0]['id'])) {
            if ($action) return $exist[0]['id'];
            else {
                appEnv::$db->delete(PM::T_AGENT_CLIENT, $where);
                return true;
            }
        }
        # TODO: сначала посмотреть, а есть ли учетки с такими ИД?
        if ($action) {
            $ret = appEnv::$db->insert(PM::T_AGENT_CLIENT, $where);
        }
        return $ret;
    }
    /**
    * Получает ИД подразделения, к которому надо привязывать учетки "клиентов"
    *
    */
    public static function getDeptForClients() {

        $ret = '0';
        if ($freeCliId = appEnv::getConfigValue('account_clientplc')) {
            # цепляю нового клиента к тому "подразделению", где живет учетка "неавторизованного клиента из eShop"
            $clDta = appEnv::$db->select(PM::T_USERS, [
              'fields'=>'deptid',
              'where' => ['userid'=> $freeCliId],
              'singlerow' => 1
            ]);
            if (!empty($clDta['deptid'])) $ret = $clDta['deptid'];
        }
        return $ret;
    }
    /**
    * Ищет ИД указанной роли
    *
    * @param mixed $rolename
    */
    public static function getRoleId($rolename) {
        $dta = appEnv::$db->select(appEnv::TABLES_PREFIX.'acl_roles', [
          'where'=>['rolename' =>$rolename],
          'singlerow' =>1
        ]);
        return (isset($dta['roleid']) ? $dta['roleid'] : 0);
    }
    /**
    * Создает учетку клиента и привязывает ее к ИД агента - шефа (для вызовов из API с сайта eShop, digital agency
    *
    * @param mixed $params ассоц.массив с основными данными клиента: insrfam, linsrimia, insrotch, insrphone,...
    * @param int $agentid - ИД агента, к которому "привязать" клиента
    * @return array : 'result' => true (OK)| FALSE (some error), 'clientid' => ИД новой УЗ клиента, 'message'-текст ошибки
    */
    public static function registerClient($params, $agentid = null) {

        $pref = '';
        if (isset($params['insrphone'])) $pref = 'insr'; # по данным из полиса (страхователь)
        # $phonepref = (isset($params[$pref.'phonepref']) ? $params[$pref.'phonepref'] : '');
        $phone = (isset($params[$pref.'phone']) ? $params[$pref.'phone'] : '');
        $fullphone = $phone;
        if($fullphone == '') return ['result'=>FALSE, 'message' =>'Не передан номер телефона'];
        $fullphone = preg_replace( "/[^\-0-9]/", "", $fullphone ); # убрал все кроме цифр и "-"
        if(strlen($fullphone) < 10) return ['result'=>FALSE, 'message' =>'Короткий (некорректный) номер телефона'];

        $clientId = 0;

        # TODO: "нормализация" переданного телефона ?
        # проверяю, может такая УЗ уже есть - по номеру телефона
        $exist = appEnv::$db->select(PM::T_USERS, [
          'where'=>"(usrphone ='$fullphone' OR usrlogin='$fullphone')",
          'fields'=>'userid,fullname',
          'orderby' =>'userid',
          'singlerow' => 1
        ]);
        # return '<pre>'.print_r($exist,1).'</pre> SQL:' . appEnv::$db->getLastQuery();
        $bindText = '';
        $logStr = '';
        if (!empty($exist['userid'])) {
            $clientId = $exist['userid'];
            $ret = [ 'result'=>true,
              'message' =>'Учетка с указанным телефоном/логином уже есть, возвращен ее ID',
              'clientid' => $clientId
            ];
        }
        else {
            $dta = [
              'usrphone' => $fullphone,
              'usrlogin' => $fullphone,
              'usrname' => (isset($params[$pref.'fam']) ? $params[$pref.'fam'] : ''),
              'firstname' => (isset($params[$pref.'imia']) ? $params[$pref.'imia'] : ''),
              'secondname' => (isset($params[$pref.'otch']) ? $params[$pref.'otch'] : ''),
              'usremail' => (isset($params[$pref.'email']) ? $params[$pref.'email'] : ''),
              'created' => '{now}',
              'createdby' => (!empty(appEnv::$auth->userid)? appEnv::$auth->userid : 'SYSTEM'),
              'deptid' => self::getDeptForClients(),
              'usr_role' => self::getRoleId('role_agent')
            ];
            $dta['fullname '] = "$dta[usrname] $dta[firstname]" . ($dta['secondname']? " $dta[secondname]":'');

            if($dta['usrname'] == '') return ['result'=>FALSE, 'message' =>'Не передана/не заполнена Фамилия'];
            if($dta['firstname'] == '') return ['result'=>FALSE, 'message' =>'Не передано/не заполнено Имя'];
            # return $dta; # debug
            $clientId = appEnv::$db->insert(PM::T_USERS, $dta);
            if ($clientId) {
                $ret = [ 'result'=>true, 'clientid'=> $clientId ];
                $logStr = "Создана УЗ клиента [$clientId]";
            }
            else {
                $ret = [ 'result' => FALSE, 'message'=>'Ошибка при создании записи пользователя '.appEnv::$db->sql_error() ];
                WriteDebugInfo( "add user ERROR, query: ", appEnv::$db->getLastQuery() );
                WriteDebugInfo( "error: ", appEnv::$db->sql_error() );
                $logStr = 'Ошибка при регистрации УЗ клиента!';
            }
        }
        if ($clientId > 0 && $agentid>0) {
            $binded = self::bindClientToAgent($clientId, $agentid);
            if ($binded) $logStr .= " (УЗ клиента привязана к агенту [$agentid])";
            else $logStr .= " (Ошибка привязки агента $agentid к клиенту $clientId)";
            $ret['message'] = (empty($ret['message'])?'':$ret['message']) . $logStr;
        }
        if ($logStr) appEnv::logEvent('CLIENT.REGISTER', $logStr, FALSE, $clientId);
        return $ret;
    }
    /**
    * 2019-01-11 получить подходящую дату транша для типа полиса, кодировки (и даты нач.действия/продажи)
    *
    * @param mixed $prodtype тип полиса 'INDEXX'|'TREND'
    * @param mixed $codirovka нужная кодировка
    * @param mixed $datefrom дата продажи/начала действия
    */
    public static function getTrancheDate($prodtype, $codirovka='', $datefrom = '') {
        $where = [];
        if ($prodtype !='') $where['prodtype'] = $prodtype;
        if ($codirovka =='') $where[] = "codirovka=''";
        else $where[] = "(codirovka='' OR FIND_IN_SET('$codirovka', codirovka))";
        if (empty($datefrom) || intval($datefrom)==0) $datefrom = date('Y-m-d');
        else $datefrom = to_date($datefrom);

        $where[] = "'$datefrom' BETWEEN openday AND closeday";
        $dta = appEnv::$db->select(PM::TABLE_TRANCHES, [
          'where'=>$where,
          'orderby' =>'codirovka DESC', # сначала запись с указанными кодировками!
          'singlerow' => 1,
        ]);
        return (isset($dta['tranchedate'])? $dta['tranchedate'] : '');
    }

    /**
    * для форм просмотра договора, заявления
    * @param mixed $stateid  ИД статуса
    * @param mixed $accepted значение флага "акцептован) - используется если $fullform>0
    * @param mixed $fullform - 1 если нужен ПОЛНЫЙ текст, 0 - только собсно статус
    *
    public function decodeAgmtState($stateid, $accepted=0, $fullform=1) {
        # WriteDebugInfo("decodeAgmtState($stateid, $accepted, $fullform)");
        # WriteDebugInfo('decodeAgmtState, rawagmt:', $this->_rawAgmtData);
        $postfix = ( $fullform ? '_full':'');

        $statetext = appEnv::getLocalized('agmtstate_'.$stateid . $postfix);
        if ($statetext)
            $ret = $statetext;
        else
            $ret = (isset(self::$agmt_states[$stateid])) ? self::$agmt_states[$stateid] : "[$stateid]";
        if ($fullform) {

            if ($stateid == PM::STATE_DISSOLUTED && intval($this->_rawAgmtData['diss_date']))
                $ret .= " ".to_char($this->_rawAgmtData['diss_date']);

            if ( !empty($this->_rawAgmtData['datepay']) && intval($this->_rawAgmtData['datepay']) > 0)
                {
                $ret .= ', дата оплаты ' . to_char($this->_rawAgmtData['datepay']);

                if ($this->_rawAgmtData['eqpayed'] > 0 && $fullform) {
                    $autoPayHtml=  AppEnv::getConfigValue('alfo_auto_payments') ?
                      \AutoPayments::viewAutoPayForPolicy($this->_rawAgmtData['module'],$this->_rawAgmtData['stmt_id'])
                      : '';
                    if(!empty($autoPayHtml))
                        $ret .= " <span class='view_eqpayed'>(онлайн-оплата) $autoPayHtml</span>";
                }
                else

                if (!empty($this->_rawAgmtData['platno']) && $fullform)
                    $ret .= ", плат/квит. ".$this->_rawAgmtData['platno'];
            }
            if ( !empty($this->_rawAgmtData['state_fatca']) && $this->isAdmin() )
                $ret .= '&nbsp;<span class="attention bordered">' . FatcaUtils::getFatcaText() . '</span>';
        }
        if (!empty($this->_rawAgmtData['docflowstate']) && $fullform) {
            # уже выгрузили в СЭД, вывожу ссылку для открытия карточки в СЭД
            $cardid = $this->_rawAgmtData['export_pkt'];

            if ($cardid && (self::$userLevel >= PM::LEVEL_IC_ADMIN) ) {
                $docFlowUrl = SedExport::getDocFlowUrlForCard($cardid, $this->module);
                $ret .= " / <a href=\"$docFlowUrl\" target=\"_blank\">СЭД: $cardid</a>";
             }
             # else $ret .= " / в СЭД";
        }
        if ( $accepted ) $ret .= ' / ' . (constant('ENABLE_PACK_EXPORT') ? 'Акцептован' : '<b>Принято СК</b>');
        return $ret;
    }
    */
    # для CRUD-грида astedit/alf_agreements :
    public static function viewAgmtState($stateid, $fullrow=0) {

        global $ast_datarow;
        if (isset($ast_datarow) && is_array($ast_datarow)) $fullrow = $ast_datarow;
        $ret = (isset(self::$agmt_states[$stateid])) ? self::$agmt_states[$stateid] : "[$stateid]";
        # WriteDebugInfo("fyllrow: ", $fullrow);
        if ( !empty($fullrow['datepay']) && $fullrow['datepay'] > 0 && in_array($stateid, array(1,3,6))) {
            $ret .= " / оплачен";
            if(!empty($fullrow['platno'])) $ret .= "($fullrow[platno])";
        }
        # добавляю статус Биз-процесса!
        if (!empty($fullrow['bpstateid']))
            $ret .= ' ' .self::decodeBpState($fullrow['bpstateid'], 1);
        if (isset($fullrow['eqpayed']) && intval($fullrow['eqpayed'])>0)
            $ret .= ' <span class="eqpayed" title="Онлайн-оплата">О</span>';
        # if (!empty($ast_datarow['accepted'])) $ret .= ' / Акцептован';
        return $ret;
    }

    # Получить список заведенных БА для выбора в <select>("новые" инвест-полисы)
    public static function InvBaListNone() {
        # TODO - брать из alf_invba
        $ret = [ ['0', 'Не выбрано'] ];
        $dta = appEnv::$db->select(PM::T_INVBA, [
          'fields'=>'id,baname',
          'associative'=>0,
          'orderby'=>'baname'
        ]);
        if (is_array($dta)) $ret = array_merge($ret, $dta);
        return $ret;
    }
    /**
    * Получить список базовых активов для инв-продукта (модуля)
    *
    * @param mixed $module -ИД плагина
    */
    public static function getBaForProduct($module) {
        $dta = appEnv::$db->select([ 'ba'=>PM::T_INVBA, 'codes'=>PM::T_INVSUBTYPES ],
          [
            'fields'=>'ba.id,ba.baname',
            'where'=>"ba.id=codes.ba_id AND codes.productid='$module'",
            'distinct' => 1,
          ]
        );
        return $dta;
    }

    # Проверяем доступность СЭД и корректность пароля
    public static function testSED($cardid = FALSE, $debug = 0, $verbose=0) {

        if (!isset(appEnv::$_plugins['sedexport'])) die ('SEDEXPORT not installed');
        $bkend = appEnv::$_plugins['sedexport']->getBackend();
        if ($debug) {
            appEnv::setCached('SED', 'debug', $debug);
        }
        if ($verbose) {
            appEnv::setCached('SED', 'verbose', $verbose);
        }
        if (is_object($bkend)) {
            $result = $bkend->initTicketProcessor($debug, $verbose);
            if (!empty($result['message'])) return $result;
        }
        $ret = "initTicketProcessor OK";
        if (!$cardid)
            $cardid = (appEnv::isProdEnv() ? '186422' : '159570');
        if ( $data = $bkend->findCard($cardid) )
            $ret .= "<br>Данные по карточке $cardid:<pre>". print_r($data,1).'</pre>';
        return $ret;
        # exit( '<pre>' . print_r($bkend,1) . '</pre>');
    }
    /**
    * полусить значания указанных полей из карточки с номером
    *
    * @param mixed $cardid
    * @param mixed $fields
    */
    public static function getDocflowData($cardid, $fields = FALSE) {
        if (!isset(appEnv::$_plugins['sedexport'])) die ('SEDEXPORT not installed');
        if (intval($cardid) <=0) return ['result'=>'ERROR', 'message'=>'Не передан ИД карточки'];

        $bkend = appEnv::getPluginBackend('sedexport');
        if (is_object($bkend)) {
            $result = $bkend->initTicketProcessor();
            if (!empty($result['message'])) return $result;
        }

        $ret = $bkend->findCard($cardid, $fields);
        if(is_array($ret) && isset($ret['Item'])) {
            $ret['Item'] = json_decode(json_encode($ret['Item']), true);
            if (isset($ret['Item']['Fields']['ItemField'])) {
                $ret['fields'] = [];
                if(isset($ret['Item']['Fields']['ItemField'][0])) {
                    foreach($ret['Item']['Fields']['ItemField'] as $row) {
                        if (isset($row['FieldName']) && isset($row['FieldStringValue'])) {
                            $fldid = $row['FieldName'];
                            $ret['fields'][$fldid] = $row['FieldStringValue'];
                        }
                    }
                }
                elseif(isset($ret['Item']['Fields']['ItemField']['FieldName'])) {
                    $fldid = $ret['Item']['Fields']['ItemField']['FieldName'];
                    $ret['fields'][$fldid] = $ret['Item']['Fields']['ItemField']['FieldStringValue'];
                }

            }
            # $ret = (array) $ret;
        }
        return $ret;
    }
    /**
    * Выясняю, есть ли настройка выгрузки для полисов данной кодировки
    *
    * @param mixed $codirovka
    */
    public static function XmlConfigExist($codirovka, $backend=FALSE) {
        if (is_object($backend) && method_exists($backend,'getLisaModifiedCode')) {
            $newcode = $backend->getLisaModifiedCode();
            # writeDebugInfo("codirovka by getLisaModifiedCode: $newcode");
            if ($newcode) $codirovka = $newcode;
        }
        # writeDebugInfo("seek XML config for $codirovka ...");
        $baseRc = appEnv::$db->select(PM::T_EXPORTCFG, ['where'=>["FIND_IN_SET('$codirovka',product)"], 'singlerow'=>1]);
        $prodId = 0;
        if (isset($baseRc['id'])) $prodId = $baseRc['id'];
        if (!empty($baseRc['binded_to']))
            $prodId = $baseRc['binded_to'];
        if (!$prodId) return 0;
        # теперь проверяю, есть ли (и сколько) настроенных рисков alf_exportrisks
        $risks = appEnv::$db->select(PM::T_EXPORTRISKS, ['fields'=>'count(1) cnt', 'where'=>['productid' => $prodId], 'singlerow'=>1]);
        return (isset($risks['cnt']) ? $risks['cnt'] : 0);
    }
    /**
    * Проверка, может ли полис быть пролонгирован
    * (может, если нет другого полиса, пролонгации указанного)
    * @param mixed $module
    * @param mixed $policyid
    */
    public static function isProlongable($module, $policyid) {
        $ndata = appEnv::$db->select(PM::T_POLICIES, [
          'where'=>['module'=>$module, 'previous_id'=>$policyid],
          'fields' => 'stmt_id,stateid,policyno',
          'singlerow' => 1
        ]);
        if (isset($ndata['stateid'])) {
            if (!in_array($ndata['stateid'], [ PM::STATE_ANNUL,PM::STATE_CANCELED,PM::STATE_BLOCKED ]))
                $ret = ['result'=>'ERROR', 'error' =>"$policyid: Для данного полиса уже есть пролонгация: $ndata[policyno] ($ndata[stmt_id])"];
        }
        else $ret = TRUE;
        return $ret;
    }

    /**
    * поиск "схожего" (действующего или неотмененного) полиса на того же застрахованного/страхователя (ФИО, д.рожд [данные документа])
    * @param array $pars асоц.массив данных о человее lastname, firstname, middlename, birth, doctype, docser,docno...
    * @return FALSE или массив - (текст, ИД полиса, коэф-т релеантности (100 - найден абсолютно точно тот же чел, 80-совпадение ФИО+д.рожд)
    * @since 1.17
    * TODO: добавить режим обнаружения попытки сохранить полис как "новый бизнес", когда для данного ФЛ доступна пролонгация, либо
    *  она была "пропущена" (не сделана вовремя, в срок NN месяцев)
    */
    public static function findSimilarPolicy($pars) {
        if (self::$debug) WriteDebugInfo("findSimilarPolicy pars: ", $pars);
        $module = isset($pars['module']) ? $pars['module'] : FALSE;
        $ret = 0;
        $rate = 80;
        $catchProlong = isset($pars['prolongPeriod']) ? $pars['prolongPeriod'] : FALSE;
        # Обнаруживать полисы, которые могли быть пролонгированы, но не были в течение некот.срока ($catchProlong дней)
        # для вычисл-я попыток схитрожопить, не продляя полис (с потерей в условиях страхования), а оформляя "новый бизнес"

        $where = [];
        if(!empty($pars['myid'])) $where[] = "stmt_id<>" . $pars['myid'];
        if (isset($pars['lastname'])) $where['fam'] = $pars['lastname'];
        elseif(isset($pars['fam'])) $where['fam'] = $pars['fam'];

        if (isset($pars['firstname'])) $where['imia'] = $pars['firstname'];
        elseif(isset($pars['imia'])) $where['imia'] = $pars['imia'];

        if (isset($pars['middlename'])) $where['otch'] = $pars['middlename'];
        elseif(isset($pars['otch'])) $where['otch'] = $pars['otch'];

        if (isset($pars['birth'])) $where['birth'] = to_date($pars['birth']);
        elseif(isset($pars['birthdate'])) $where['birth'] = to_date($pars['birthdate']);

        $addPars = FALSE;
        if ( !empty($pars['docno']) && self::SEARCH_BY_PASSPORT) {
            $addPars = [
             'docno' => $pars['docno']
            ];
            if (!empty($pars['docser'])) $addPars['docser'] = $pars['docser'];
            if (!empty($pars['doctype'])) $addPars['doctype'] = $pars['doctype'];
        }
        if (count($where) > 2) {

            $ids1 = appEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>$where, 'fields'=>'ptype,stmt_id','distinct'=>1,'associative'=>1]);
            if (self::$debug) {
                writeDebugInfo("seek 1 result: ", $ids1);
                writeDebugInfo("seek 1 qry: ", appEnv::$db->getLastQuery());
            }
            if (!is_array($ids1)) return 0;
        }

        if ($addPars) { # ищем точное соответствие с документом
            $ids2 = appEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>array_merge($where, $addPars), 'fields'=>'ptype,stmt_id','distinct'=>1,'associative'=>1]);
            # запоминаю ptype, нужен толлько застрахованный (или страхователь только если он же застрахованный!)
            if (is_array($ids2) && count($ids2)) {
                $rate = 100;
                $ids1 = $ids2;
            }
        }
        if (self::$debug)  writeDebugInfo("persons after 2nd seek: ", $ids2);

        if(is_array($ids1)&& count($ids1)) {

            $plclist = implode(',', array_column($ids1, 'stmt_id'));


            if(!empty($pars['debugdate'])) $startCheck = to_date($pars['debugdate']);
            else $startCheck = (!empty($pars['datefrom']) ? to_date($pars['datefrom']) : date('Y-m-d'));
            # $startCheck = '2010-01-01'; # temp-debug

            $where = ["stmt_id IN($plclist)", "stateid NOT IN(9,10,50,60)", "datetill>='$startCheck'"];

            if ($module) $where[] = "module='$module'"; # указали одни модуль, ищем только в нем
            elseif(count(appEnv::$nonLifeModules)) # иначе - во всех модулях стр.Жизни
                $where[] = "module NOT IN('" . implode("','",appEnv::$nonLifeModules) . "')";
            $plcdta = appEnv::$db->select(PM::T_POLICIES, [
              'fields'=>'stmt_id,policyno,stateid,equalinsured',
              'where' => $where,
            ]);
            /*
            WriteDebugInfo("seek policy sql:", appEnv::$db->getLastQuery());
            WriteDebugInfo(" ERR:", appEnv::$db->sql_error());
            WriteDebugInfo("sql1 result:", $plcdta);
            */
            if (isset($plcdta[0]['stmt_id'])) {
                # нужно определить, что хотя бы в одном полисе нужный чел в роли Застрахованного
                foreach($plcdta as $plc) {
                    $stmtid = $plc['stmt_id'];
                    $sptype = ($plc['equalinsured'] ? 'insr' : '');
                    foreach($ids1 as $person) {
                        if ($person['stmt_id'] == $plc['stmt_id']) {
                            if (($plc['equalinsured']>0 || $person['ptype']!=='insr')) {
                                # среди найденных в том же модуле нашелся полис с совпавшим Застрахованным

                                return ['result'=>'ERROR',
                                  'message' => "На данного Застрахованного есть полис: ".$plc['policyno'],
                                  'data' => ['policyid' => $plc['stmt_id'], 'rate' => $rate]
                                ];

                           }
                        }
                    }
                }
            }

        }
        return $ret;
    }
    # Россия, не Россия (по переданному коду или названию страны)
    public static function isRF($id) {
        if (is_numeric($id)) {
            $dta = appEnv::$db->select(PM::T_COUNTRIES,array('fields'=>'countryname,ctype', 'where'=>"(id=$id OR countryno=$id)",'singlerow'=>1));
            $ret = (isset($dta['ctype']) && $dta['ctype']==1);
            # WriteDebugInfo("isRF($id) is russia: [$ret]");
            return $ret;
        }
        $strana = mb_strtolower($id, MAINCHARSET);
        return in_array($strana, ['rf','russia','russian federation','рф','россия','российская федерация']);
    }

    public static function printedDocumentInfo($dta, $prefix='', $full = FALSE) {
        $ret = '';
        if($prefix == 'zzzzzz') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3);
            exit('1' . AjaxResponse::showMessage("printedDocumentInfo[full=$full]: <pre>" . print_r($trace,1) . '</pre>'));
        }
        if(!isset($dta[$prefix.'docno']) && $prefix==='insd' && isset($dta['childdocno'])) {
            # исправляю ошибочно переданныq префикс при "гибком" застрахованном - врослом/ребёнке
            if(AppEnv::isLocalEnv())
                writeDebugInfo("prefix=$prefix, autofix to child, trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4));
            $prefix = 'child';
        }

        if(!isset($dta[$prefix.'docno'])) {
            if(AppEnv::isProdEnv()) return '';
            exit('1' . AjaxResponse::showMessage(__FILE__ .':'.__LINE__." no docno for [$prefix]:<pre>" . print_r($dta,1) .
                print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),1) .'</pre>'));
        }
        # if($prefix === 'insr') WriteDebugInfo('printedDocumentInfo:'.$prefix, ' data: ', $dta);
        if (!empty($dta[$prefix.'rezident_rf']) || empty($dta[$prefix.'rez_country']) || self::isRF($dta[$prefix.'rez_country'])) {

            $dtype = $dta[$prefix.'doctype'];

            $ret .= PolicyModel::decodeDocType($dtype) . ' ' . ($dta[$prefix.'docser'] ??'').' '.($dta[$prefix.'docno']??'');

            $vidanStr = ($dtype == PM::DT_SVID) ? 'выдано':'выдан';
            if ( !empty($dta[$prefix.'docdate']) && intval($dta[$prefix.'docdate']) || !empty($dta[$prefix.'docissued'])) {
                $ret .= ", $vidanStr";
                if (!empty($dta[$prefix.'docdate'])) $ret.= ' '.to_char($dta[$prefix.'docdate']);
                if (!empty($dta[$prefix.'docissued'])) $ret .= ' '.$dta[$prefix.'docissued'];
            }
            if ((empty($dtype) || $dtype==PM::DT_PASSPORT) && !empty($dta[$prefix.'docpodr']) && $full)
                $ret .= ', код подр.'. $dta[$prefix.'docpodr'];
        }
        else  { # ino
            $ret .= "Иностранный паспорт " . $dta[$prefix.'inopass'];
            if(self::isDateValue($dta[$prefix.'docdate']))
              $ret .= ", выдан " . to_char($dta[$prefix.'docdate']);
            if (!empty($dta[$prefix.'migcard_no']) && $full) {
                $migType = self::decodeVisaType($dta[$prefix.'permit_type']);
                $ret .= ", $migType ".$dta[$prefix.'migcard_ser'] . ' ' . $dta[$prefix.'migcard_no'];
                if (!empty($dta[$prefix.'docfrom']) && intval($dta[$prefix.'docfrom'])) $ret .= ", пребывание с ".to_char($dta[$prefix.'docfrom'])
                  . " по " . to_char($dta[$prefix.'doctill']);
            }
        }
        return $ret;
    }
    /**
    * Для "новых" инвест-полисов (Инвест XXL):
    * получаем параметры кодировки и БА
    * @param string $module ИД плагина/модуля
    * @param string $codirovka кодировка
    */
    public static function getInvSubtypeData($module, $codirovka) {
        $where = [ 'productid'=>$module, 'codirovka'=>$codirovka ];

        $subDta = appEnv::$db->select(PM::T_INVSUBTYPES, [
          'where'=> $where,
          'singlerow'=>1
        ]);
        $baid = (isset($subDta['ba_id'])? $subDta['ba_id'] : 1);
        $baData = appEnv::$db->select(PM::T_INVBA, [
          'where'=>['id'=>$baid],
          'singlerow' => 1
        ]);
        return array_merge($subDta, $baData);
    }
    /**
    * превращаю код страны в ее назание (если передано число!)
    * @param mixed $countryId ИД страны или номер по справочнику
    */
    public static function decodeCountry($countryId, $longName=FALSE) {
        if (is_numeric($countryId)) {
            $dta = appEnv::$db->select(PM::T_COUNTRIES,array('fields'=>'countryname,longname,ctype', 'where'=>"id='$countryId' OR countryno='$countryId'",'singlerow'=>1));
            $ret = (isset($dta['countryname']) ? $dta['countryname'] : $countryId);
            if($longName && !empty($dta['longname'])) $ret = $dta['longname'];
        }
        else $ret = $countryId;
        return $ret;
    }
    # Добавляю (создаю если еще нет) в элемент $data['userNotes'] строку|массив строк из $notes
    public static function addUserNotes(&$data, $notes) {
        if (!isset($data['userNotes']))
            $data['userNotes'] = [];
        elseif(is_string($data['userNotes']))
            $data['userNotes'] = [ $data['userNotes'] ];
        if (is_string($notes)) $data['userNotes'][] = $notes;
        elseif (is_array($notes)) $data['userNotes'] = array_merge($data['userNotes'], $notes);
    }
    /**
    * Беру из таблицы alf_product_config набор констант для печати номера/даты приказа, должности/ФИО/доверенности
    * подписанта от СК...
    * @param mixed $module ИД плагина
    * @param mixed $codirovka кодировка продукта
    */
    public static function getBaseProductCfg($module='',$codirovka='') {

        $where = array();
        $where = ["module" => $module];
        if ($codirovka) $where[] = "(prodcodes='' OR FIND_IN_SET('$codirovka',prodcodes))";
        $ret = appEnv::$db->select(PM::T_PRODCFG, ['where'=>$where,
          'singlerow'=>1, 'orderby' =>'prodcodes DESC'
        ]);

        if (!empty($ret['stampid']) && class_exists('Stamps')) {
            # $images = Stamps::getImagesForId($ret['stampid']);
            $images = Stamps::getSignerData($ret['stampid']);
            # теперь тут и данные ФИО, должность, доаеенность подписанта (2-19-02-22)
            if (is_array($images)) {
                $ret['fullstamp'] = $images['fullstamp'];
                $ret['faximile'] = $images['faximile'];
                # если поля данных о подписанте пустые или нету, беру из stamps
                if (empty($ret['signer_duty'])) $ret['signer_duty'] = $images['signer_duty'];
                if (empty($ret['signer_name'])) $ret['signer_name'] = $images['signer_name'];
                if (empty($ret['signer_dov_no'])) $ret['signer_dov_no'] = $images['signer_dov_no'];
                if (empty($ret['signer_dov_date']) || !intval($ret['signer_dov_date']))
                    $ret['signer_dov_date'] = $images['signer_dov_date'];
            }
        }

        if (isset($ret['id'])) {
            unset($ret['id']);
            $ret['prikaz_date'] = intval($ret['prikaz_date']) ? to_char($ret['prikaz_date']) : '';
            $ret['signer_dov_date'] = intval($ret['signer_dov_date']) ? to_char($ret['signer_dov_date']) : '';
        }
        if (isset($ret['signer_name'])) {
            $full_delim = ' '; # "\r\n"; # Для сокращения занимаемого места принуд.перевод после должности строки убрал
            if($ret['signer_dov_no'] == -1 || $ret['signer_dov_no'] ==='-') $dovertxt = '';
            else {
            $dovertxt = ((!empty($ret['signer_dov_no']) ?
                ('Действующий(ая) на основании Доверенности № '.$ret['signer_dov_no']. ' от '.$ret['signer_dov_date'])
                : 'Действующий(ая) на основании Устава'));
            }
            $ret['ic_signer_full'] = $ret['signer_duty'] . $full_delim . $ret['signer_name'];

            if(!empty($dovertxt)) $ret['ic_signer_full'] .= ', ' . $full_delim . $dovertxt;

            $ret['ic_signer_fio'] = MakeFio($ret['signer_name']); # поле "расшифровка подписи"
        }

        # взять path+filenames картинок штампов+afrcbvbkt в соотв-вии с настройкой продукта
        return $ret;
    }

    # astedit / ф-ция формирования ФИО как ссылки для вызова alfoCore.viewPerson()
    public static function evShowUserLink($usrid,$deptid=0) {
        global $ast_datarow; # запись arjagent_eventlog
        $fio = $usrid;
        if (empty($fio)) return '--';
        if (is_numeric($usrid)) {
            $fio = evShowUser($usrid);
            if (empty($deptid) && isset($ast_datarow['deptid'])) $deptid = $ast_datarow['deptid'];
            return "<a href=\"javascript:void()\" onclick=\"alfoCore.viewPerson(this,$usrid,$deptid)\">$fio</a>";
        }
        return $fio;
    }
    # AJAX - ответ с данными - ФИО и подразделение сотрудника для проссотра в журнале событий
    public static function viewPerson() {

        $usrid = appEnv::$_p['id'];
        $dptid = isset(appEnv::$_p['dept']) ? appEnv::$_p['dept'] : 0;
        $pInfo = appEnv::$db->select(PM::T_USERS, array('where'=>"userid='$usrid'", 'singlerow'=>1));
        # writeDebugInfo("pinfo ", $pInfo);
        if (isset($pInfo['userid'])) {
            $ret = ($pInfo['fullname']) ? $pInfo['fullname'] : "$pInfo[usrname] $pInfo[firstname] $pInfo[secondname]";
            $ret .= (($dptid) ? ("<br>&nbsp; ". OrgUnits::getChainedDeptName($dptid,0,1)) : '');
            if(!empty($pInfo['usremail'])) {
                $ret .= "<br>Email: ". $pInfo['usremail'];
                if(!empty($pInfo['agentcode']))
                    $ret .= ", Код агента: ". $pInfo['agentcode'];
            }

        }
        else $ret = appEnv::getLocalized('err_data_not_found', "Нет данных");
        $ret = "1\thtml\fdiv_viewperson_content\f$ret";
        exit($ret);
    }
    # $rp - родительный падеж (кого-чкго)
    public static function decodeCurrency($cur, $long=FALSE, $rp=FALSE) {
        # WriteDebugInfo("decodeCurrency($cur)...");
        switch(mb_strtoupper($cur)) {
            case 'RUR': case 'RUB':
                if ($rp) return 'рублей';
                return ($long ? 'Рубли РФ':'руб.');
            case 'EUR':
                return 'Евро';
            case 'USD':
                if ($rp) return 'долларов США';
                return ($long ? 'Доллары США':'долл.США');
            case 'CNY':
                if($rp) return 'юаней';
                $ret = ($long ? AppLists::getCurrencyName($cur, $long) : 'юани');
                if(empty($ret)) $ret = 'Юани КНР'; # если в БД не оказалось
                return $ret;
            default   :
                $ret = AppLists::getCurrencyName($cur, $long);
                if(!$ret) $ret = "[$cur]";
                return $ret;
        }
    }
    # делаю из RUR RUB, для передачи валюты в LISA
    public static function LisaCurrency($cur) {
        switch(mb_strtoupper($cur)) {
            case 'RUR': return 'RUB';
            case 'РУБ.': return 'RUB';
        }
        return $cur;

    }
    /**
    *  декодирую строку лимита возраста (pens, newpens, "ageMale,ageFemale"
    * @returns array: ['M' => age1, 'F' => age2 ]
    */
    public static function decodeAgeLimit($strg) {
        $ret = [ 'M'=>100, 'F' =>100 ];
        if ($strg === 'pens')
            $ret = [
              'M' => AppEnv::getConfigValue('ins_pensionage_m', Lim::PENS_MALE),
              'F' => AppEnv::getConfigValue('ins_pensionage_f', Lim::PENS_FEMALE),
            ];
        elseif ($strg === 'pens-1')
            $ret = [
              'M' => (AppEnv::getConfigValue('ins_pensionage_m', Lim::PENS_MALE)-1),
              'F' => (AppEnv::getConfigValue('ins_pensionage_f', Lim::PENS_FEMALE)-1),
            ];
        elseif($strg === 'newpens') { # новый возраст попоследней моде (2019)
            $ret = ['M'=> 65 , 'F' => 60];
        }
        else {
            $splt = preg_split( '/[,;\|]/', $strg, -1, PREG_SPLIT_NO_EMPTY );
            if (count($splt)>1)
                $ret = ['M'=>intval($splt[0]), 'F'=>intval($splt[1]) ];
            elseif($strg > 0)
                $ret = ['M'=>intval($strg), 'F'=>intval($strg) ];
        }
        return $ret;
    }

    /**
    * Функция для изменения фона колонок в списках полисов
    *
    * @param mixed $par - передаваемое значения поля, но смотрим - на всю строку - $ast_datarow
    */
    public static function clrBgAgmtState($par, $dt=FALSE) {
        global $ast_datarow;
        if (!$dt && isset($ast_datarow)) $dt = $ast_datarow;
        if ($dt['stateid'] >= PM::STATE_DISSOLUTED) return '#FFAFAA';
        if ($dt['stateid'] == PM::STATE_UNDERWRITING) return 'yellow';
        if ($dt['stateid'] == PM::STATE_ANNUL || $dt['stateid']== PM::STATE_CANCELED ) return '#FFD511'; # отмена
        if ($dt['stateid'] == PM::STATE_FORMED ) {
            if ($dt['export_pkt']!='') return '#afa'; # выгружен в пакет
            if ($dt['accepted'] > 0) return '#bbf'; # акцептован
            return '#ddf'; # 11=оформлен
        }
        return '';
    }
    # для инвертирования TRUE -> 0 и наоборот (печать в PDF)
    public static function invert($par) {
        if ($par) return 0;
        else return 1;
    }

    # расшифровка статуса догвора
    public static function decodePolicyState($stateid) {
        switch($stateid) {
            case 0: case PM::STATE_POLICY: return 'Проект полиса';
            case PM::STATE_DRAFT: return 'Черновик';
            case PM::STATE_IN_FORMING: return 'На оформлении';
            case PM::STATE_UNDERWRITING: return 'На андеррайтинге';
            case PM::STATE_UWAGREED: return 'Согласовано андеррайтером';
            case PM::STATE_UWAGREED_CORR: return 'Согласовано с перерасчетом';
            case PM::STATE_UWDENIED: return 'Не согласовано Андеррайтером';
            case PM::STATE_PAYED: return 'Оплачен';
            case PM::STATE_ANNUL: return 'Аннулирован';
            case PM::STATE_CANCELED: return 'Отменен (отказ от страхования)';
            case PM::STATE_FORMED: return 'Оформлен';
            case PM::STATE_DISSOLUTED: return 'Расторгнут';
            case PM::STATE_DISS_REDEMP: return 'Расторгнут с вык.суммой';
            case PM::STATE_BLOCKED: return 'Заблокирован';
        }
        return "[$stateid]";
    }
    /**
    * Получить содержимое файла htm шаблона с учетом текущего осн.языка
    * если файла для текущего языка нет, беру "универсальный " - без ".ru", если есть.
    * @param mixed $basename база имени файла, без расширения: "myfile" -> "templates.myfile.ru.htm"
    * @param mixed $plg имя плагина, если надо сначала поискать в папке плагина - plugins/mmm/html/
    * @return mixed
    */
    public static function getHtmlTemplate($basename, $plg='') {
        $lang = appEnv::DEFAULT_LANG;
        if ($plg !=='')
            $flnames = [
              ALFO_ROOT . "plugins/$plg/html/$basename.$lang.htm",
              ALFO_ROOT . "plugins/$plg/html/$basename.htm",
            ];
        else $flnames = [];

        $flnames = array_merge($flnames, [
            ALFO_ROOT . "templates/$basename.$lang.htm",
            ALFO_ROOT . "templates/$basename.htm",
        ]);

        foreach($flnames as $fl) {
            if (is_file($fl)) return file_get_contents($fl);
        }
        return '';
    }
    /**
    * рассчитываю дату окончания действия полиса, с учетом предельного возраста действия риска
    * @since 1.23
    * @param mixed $term - срок страхования (дает стандартную дату оконч если возраст не наступит)
    * @param mixed $startDate - дата начала д-вия полиса
    * @param mixed $age - начальный возраст застрахованного ИЛИ дата рождения YYYY-MM-DD (возраст сама посчитает)
    * @param mixed $maxAge - возраст, при котором риск перестает действовать
    * @return "YYYY-MM-DD" дата в ISO формате ИЛИ FALSE
    *   если возраст на дату начала УЖЕ достиг максимального
    */
    public static function getRiskDateTill($term, $startDate, $age, $maxAge, $riskid = '') {
        # WriteDebugInfo("getRiskDateTill($term, $startDate, $age, $maxAge, riskid='$riskid')...");
        $startAge = intval($age);
        if(!is_numeric($age)) { # передали дату dd.mm.yyyy или yyyy-mm-dd
            list($startAge, $days) = diffDays($age, $startDate,1);
        }
        if ($startAge >= $maxAge) return FALSE; # "startage $startAge greater than maxAge $maxAge";
        $lastYear = max(0, $maxAge - $startAge);
        $addYears = min($term, $lastYear);
        $startymd = to_date($startDate);
        $ret = addToDate($startymd, $addYears,0,-1);
        #return ("getRiskDateTill($term,$startDate,$age,$maxAge) return $ret, lastYear:$lastYear addYears:$addYears");
        return $ret;
    }

    public static function getListCumLR() {
        if (!isset(appEnv::$_cache['list_cumlr'])) {
            appEnv::$_cache['list_cumlr'] = array_merge( [['','нет']], appEnv::$db->select(PM::TABLE_CUMLIR, [
              'fields'=>"id,CONCAT(eng_id,' / ',limit_name) rname",'associative'=>0,'orderby'=>'id']
            ));
        }
        return appEnv::$_cache['list_cumlr'];
    }
    # получить лимит в рублях по его ИД из таблицы настроек кумул.лимитов
    public static function getCumulRiskLimit($rid) {
        $dta = appEnv::$db->select(PM::TABLE_CUMLIR, [
          'where'=>['id'=>$rid],
          'fields'=> 'limit_value' ,'associative'=>0,'singlerow'=>1]
        );
        return floatval($dta);
    }

    # превращаю поля ФЛ от alf_agrement в "переносимые" a la investprog
    public static function alfToInvest($src) {
        $ret = [
         'lastname' => $src['fam'],
         'firstname' => $src['imia'],
         'middlename' => $src['otch'],
         'sex' => $src['sex'],
         'inn' => $src['inn'],
         'passportseries' => $src['docser'],
         'passport' => $src['docno'],
         'passportissuedate' => (intval($src['docdate']) ? $src['docdate']:''),
         'passportissueplace' => $src['docissued'],
         'subdivisioncode' => $src['docpodr'],
         'birthdate' => to_char($src['birth']),
         'birthplace' => ($src['birth_country']),
         'resident' => PolicyModel::isRF($src['rez_country']), # для формы старых инвест-полисов
         'citizenship' => $src['rez_country'],
         'officialaddr_postcode' => $src['adr_zip'],
         # 'officialaddr_region' => ($module=='investprod' ? $src['adr_country'] :$src['adr_region']),
         'officialaddr_region' => $src['adr_region'],
         'officialaddr_district' => '',
         # 'officialaddr_city' => ($module=='investprod' ? $src['adr_region'] : $src['adr_country']),
         'officialaddr_city' => $src['adr_country'],
         'officialaddr_street' => $src['adr_street'],
         'officialaddr_houseno' => $src['adr_house'],
         'officialaddr_korpus' => $src['adr_corp'],
         'adr_build' => $src['adr_build'],
         'officialaddr_flat' => $src['adr_flat'],

         'address_officialaddr' => $src['sameaddr'],

         'addr_postcode' => $src['fadr_zip'],
         'addr_region' => $src['fadr_country'],
         'addr_district' => '',
         'addr_city' => $src['fadr_region'],
         'addr_street' => $src['fadr_street'],
         'addr_houseno' => $src['fadr_house'],
         'addr_korpus' => $src['fadr_corp'],
         'adr_build' => $src['fadr_build'],
         'addr_flat' => $src['fadr_flat'],

         'passporttypeid' => $src['doctype'],
         'docser' => $src['docser'],
         'docno' => $src['docno'],

         'inopass' => $src['inopass'],
         'otherdocno' => $src['otherdocno'],
         'docdate' => (intval($src['docdate']) ? ($src['docdate']) : ''),
         'docpodr' => $src['docpodr'],
         # 'phonepref' => $src['phonepref'],
         # 'phone' => ($src['phonepref'] .'-'. $src['phone']),
         'phone' => $src['phone'],
         # 'phonepref2' => $src['phonepref2'],
         'phone2' => $src['phone2'],
         'email' => $src['email'],
         # TODO: миг.карта, паспорт иностранца... в инвестах их пока нет

        ];
        if (!self::isRF($src['rez_country'])) {
            $ret['passporttypeid'] = '7';
            $splt = preg_split( '/[, \-]/', $src['inopass'], -1, PREG_SPLIT_NO_EMPTY );
            $ret['passportseries'] = count($splt)>1 ? $splt[0]: '';
            $ret['passport'] = count($splt)>1 ? $splt[1]: $splt[0];
        }
        return $ret;
    }
    /**
    * переводит данные по страхователю/застрахованному в формат для формы ввода соотв.модуля
    *
    * @param mixed $module
    * @param mixed $foundItem
    */
    public static function preparePerson($module, $foundItem, $prefix = '', $foundType='i') {
        if ($module === 'investprod') {
            $ret = [
              $prefix.'_individual_lastname' => $foundItem['lastname'],
              $prefix.'_individual_firstname' => $foundItem['firstname'],
              $prefix.'_individual_middlename' => $foundItem['middlename'],
              $prefix.'_individual_birthdate' => to_char($foundItem['birthdate']),
              $prefix.'_individual_birthplace' => $foundItem['birthplace'],
              $prefix.'_individual_resident' => $foundItem['resident'],
              $prefix.'_individual_citizenship' => $foundItem['citizenship'],
              $prefix.'_individual_officialaddr_postcode' => $foundItem['officialaddr_postcode'],
              $prefix.'_individual_officialaddr_region' => $foundItem['officialaddr_region'],
              $prefix.'_individual_officialaddr_district' => $foundItem['officialaddr_district'],
              $prefix.'_individual_officialaddr_city' => $foundItem['officialaddr_city'],
              $prefix.'_individual_officialaddr_street' => $foundItem['officialaddr_street'],
              $prefix.'_individual_officialaddr_houseno' => $foundItem['officialaddr_houseno'],
              $prefix.'_individual_officialaddr_korpus' => $foundItem['officialaddr_korpus'],
              $prefix.'_individual_officialaddr_flat' => $foundItem['officialaddr_flat'],

              $prefix.'_individual_address_officialaddr' => $foundItem['address_officialaddr'],

              $prefix.'_individual_addr_postcode' => $foundItem['addr_postcode'],
              $prefix.'_individual_addr_region' => $foundItem['addr_region'],
              $prefix.'_individual_addr_district' => $foundItem['addr_district'],
              $prefix.'_individual_addr_city' => $foundItem['addr_city'],
              $prefix.'_individual_addr_street' => $foundItem['addr_street'],
              $prefix.'_individual_addr_houseno' => $foundItem['addr_houseno'],
              $prefix.'_individual_addr_korpus' => $foundItem['addr_korpus'],
              $prefix.'_individual_addr_flat' => $foundItem['addr_flat'],

              $prefix.'_individual_passporttypeid' => $foundItem['passporttypeid'],

              $prefix.'_individual_passportseries' => $foundItem['passportseries'],
              $prefix.'_individual_passport' => $foundItem['passport'],
              $prefix.'_individual_passportissueplace' => $foundItem['passportissueplace'],
              $prefix.'_individual_subdivisioncode' => $foundItem['subdivisioncode'],
              $prefix.'_individual_passportissuedate' => (intval($foundItem['passportissuedate']) ? to_char($foundItem['passportissuedate']) : ''),

              $prefix.'_individual_phone' => $foundItem['phone'],
              $prefix.'_individual_email' => $foundItem['email'],
              # ...
            ];
        }
        else {
            # WriteDebugInfo('not investpod, found item:', $foundItem);
            /*
            $phonepref = '';
            if (!isset($foundItem['phonepref'])) {
                $splitPhone = self::splitPhoneNo($foundItem['phone']);
                list($phonepref, $foundItem['phone']) = $splitPhone;
            }
            */
            $ret = [ # имена полей для policymodel.agredit/stmt
              $prefix.'fam' => $foundItem['lastname'],
              $prefix.'imia' => $foundItem['firstname'],
              $prefix.'otch' => $foundItem['middlename'],
              $prefix.'birth' => to_char($foundItem['birthdate']),
              $prefix.'birth_country' => $foundItem['birthplace'],
              // 'resident' => $foundItem['resident'], ...
              $prefix.'rez_country' => $foundItem['citizenship'],
              $prefix.'adr_zip' => $foundItem['officialaddr_postcode'],
              $prefix.'adr_country' => $foundItem['officialaddr_region'],
              $prefix.'adr_region' => $foundItem['officialaddr_district'],
              # 'city' => $foundItem['officialaddr_city'],
              $prefix.'adr_street' => $foundItem['officialaddr_street'],
              $prefix.'adr_house' => $foundItem['officialaddr_houseno'],
              $prefix.'adr_corp' => $foundItem['officialaddr_korpus'],
              $prefix.'adr_flat' => $foundItem['officialaddr_flat'],
              # 'doctype' => $foundItem['doctype'],
              $prefix.'docser' => $foundItem['passportseries'],
              $prefix.'docno' => $foundItem['passport'],
              $prefix.'docdate' => to_char( $foundItem['passportissuedate'] ),
              $prefix.'docissued' => $foundItem['passportissueplace'],
              $prefix.'docpodr' => ( $foundItem['subdivisioncode'] ),
              # $prefix.'phonepref' => $phonepref,
              $prefix.'phone' => ($foundItem['phone'] ?? ''),
              # $prefix.'phonepref2' => (isset( $foundItem['phonepref2']) ? $foundItem['phonepref2'] : ''),
              $prefix.'phone2' => ($foundItem['phone2'] ?? ''),
              $prefix.'email' => ($foundItem['email'] ?? ''),
              $prefix.'sameaddr' => (isset( $foundItem['address_officialaddr']) ? $foundItem['address_officialaddr'] : '1'),
              $prefix.'adr_country' => ( $foundType === 'i' ? $foundItem['officialaddr_city'] : $foundItem['officialaddr_region'] ),
              $prefix.'adr_region' => ( $foundType === 'i' ? $foundItem['officialaddr_region'] : $foundItem['officialaddr_city'] ),
              $prefix.'adr_street' => $foundItem['addr_street'],
              # ...
            ];
            if (empty($foundItem['resident'])) { # не резидент РФ, данные паспорта считать ино-паспортом!
                $ret[$prefix.'inopass'] = $foundItem['passportseries'] . ' ' . $foundItem['passport'];
                $ret[$prefix.'docser'] = $ret[$prefix.'docno'] = '';
            }

            if (isset($foundItem['address_officialaddr'])&& $foundItem['address_officialaddr'] ==0) {
                # факт.адрес отдельно
                $factAddr = [
                  $prefix.'fadr_zip' => $foundItem['addr_postcode'],
                  $prefix.'fadr_country' => $foundItem['addr_region'],
                  $prefix.'fadr_region' => $foundItem['addr_district'],
                  # 'city' => $foundItem['addr_city'],
                  $prefix.'fadr_street' => $foundItem['addr_street'],
                  $prefix.'fadr_house' => $foundItem['addr_houseno'],
                  $prefix.'fadr_corp' => $foundItem['addr_korpus'],
                  $prefix.'fadr_flat' => $foundItem['addr_flat'],
                ];
                $ret = array_merge($ret,$factAddr);
            }
        }
        return $ret;
    }
    /**
    * Вернет HTML код для блока с флажком "пакетнго ввода" - отправить на андеррайтинг
    *
    */
    public static function htmlBlockPacketMode() {

        $label = appEnv::getLocalized('create_policy_in_uw');
        $btn = (!empty($_SESSION['policy_packet_mode']) ? " &nbsp; <input type='button' class='btn btn-primary' id='btn_resetpacket' onclick='plcUtils.resetPacket()' value='Закрыть текущий пакет'>" : '');
        $html = "<div class=\"bordered p-5\"><label><input type='checkbox' name='user_touw' id='user_touw' value='1' "
         . "onclick='plcUtils.clickPacketMode()'> $label</label>"
         . $btn
         . self::helpLink('policy-packetmode') . '</div>';
        return $html;
    }
    # AJAX - команда зкрыть текущий пакетный режим ввода (чтобы начать следующий)
    public static function closepacket() {
       if (!isset($_SESSION['policy_packet_mode'])) exit('Пакетный режим не был начат');
       unset($_SESSION['policy_packet_mode']);
       exit('1');
    }
    /**
    * вернет HTML код для ссылки, открывающей страницу помощи
    *
    * @param mixed $module
    * @param mixed $id
    */
    public static function helpLink($id, $module ='') {
        return "<a class=\"helplink\" href='javascript:void()' onclick=\"plcUtils.showHelp('$module','$id')\" title=\"Узнать подробнее\">?</a>";
    }

    # AJAX ответ со страницей помощи/инструкции (plcutils.js: showHelp(module, id)
    public static function showHelp() {
        $module = isset(appEnv::$_p['module']) ? appEnv::$_p['module'] : '';
        $pageid = isset(appEnv::$_p['pageid']) ? appEnv::$_p['pageid'] : '';
        $folder = ALFO_ROOT . ($module ? "$module/helpPages/" : "helpPages/");

        $files = [ $folder . "$pageid.htm", $folder . "$pageid.md" ];
        foreach($files as $item) {
            if (is_file($item)) {
                $body = file_get_contents($item);
                if (substr($item,-3) === '.md') {
                    $body = appEnv::parseMarkDown($body);
                }
                exit($body);
            }
        }
        exit("$pageid : указанная страница помощи не обнаружена!");
    }
    /**
    * AJAX - пришел запрос проверить EMAIL отправкой на него контрольного кода
    * @since 1.16
    *
    public static function checkEmail() {

        $id = (isset(appEnv::$_p['id'])) ? appEnv::$_p['id'] : 0;
        $module = (isset(appEnv::$_p['plg'])) ? appEnv::$_p['plg'] : '';
        if($module == 'investprod') { // своя таблица !
            $fields = 'email';
            $table = 'bn_individual';
            # ...
            $where = ['id' => $personalId];
        }
        else {
            $table = PM::T_INDIVIDUAL;
            $fields = 'email';
            $where = ['stmt_id'=>$id, 'ptype'=>'insr'];
        }
        $data = appEnv::$db->select($table, [
          'fields' => $fields,
          'where' => $where,
          'singlerow'=>1
        ]);
        if (!empty($data['email'])) {
            $class='msg_ok';
            $ccode = rand(100,999) . '-' . rand(100,999);
            $subst = [
              '{comp_name}' => AppEnv::getConfigValue('comp_title'),
              '{comp_email}' => AppEnv::getConfigValue('comp_email'),
              '{comp_site}' => AppEnv::getConfigValue('comp_site'),
              '{check_code}' => $ccode,
            ];
            $body = @file_get_contents(appEnv::getAppFolder('templates/letters/') . 'client-email-check.htm');
            $body = strtr($body, $subst);
            $pars = [
              'to' => $data['email'],
              'subj' => appEnv::getLocalized('subj_check_email'),
              'message' => $body,
            ];
            $sendResult = appEnv::sendEmailMessage($pars);
            if ($sendResult) {
                $text = "<div class='ct'>На адрес <b>$data[email]</b><br> был отправлен контрольный код :<br><br><span class='bordered' style=\"font-size:1.6em; padding:1px 8px\">$ccode</span></div>";
            }
            else {
                $text = "Не удалось отправить сообщение на адрес <b>$data[email]</b>";
                $class='msg_error';
            }
        }
        else {
            $text = 'Данные не найдены!';
            $class='msg_error';
        }
        $class='msg_ok';
        $topic = 'Проверка Email';
        $ret = "1\tshowmessage\f$text\f$topic\f$class";
        exit($ret);
    }
    */
    # перемножение элементов массива
    public static function arrayMultiply($arr, $default = 0) {
        if (!is_array($arr)) return $default;
        $ret = 1;
        foreach($arr as $k => $val) {
            $ret *= $val;
        }
        return $ret;
    }
    public static function getButtonPdfSend($callbackFn = '') {
        if ($callbackFn=='') $callbackFn = 'getPdfButtonState';
        $btnPrompt = appEnv::getLocalized('btn_send_pdf_to_email', 'Послать PDF на Email');
        $btnTtl  = appEnv::getLocalized('ttl_send_pdf_to_email', 'На адрес Страхователя будет отправлено письмо с файлом полиса');
        $ret = [
          'btn_pdf_toemail' => [
            'html'=> "<input type=\"button\" id=\"btn_pdf2email\" class=\"btn btn-primary\" value=\"$btnPrompt\" onclick=\"policyModel.sendPdfToClient()\" title=\"$btnTtl\"> ",
            'checkfunc' => $callbackFn ]
        ];
        return $ret;
    }
    /**
    * Общая ф-ция отправки клиенту письма с вложенным PDF полисом
    * (в интерфейсе просмотра нажали кнопку "послать PDF на Email")
    */
    public static function sendPdfToClient($id, $plg='') {
        if (empty($id)) $id = isset(appEnv::$_p['id']) ? appEnv::$_p['id'] : 0;
        if (empty($plg)) $plg = isset(appEnv::$_p['plg']) ? appEnv::$_p['plg'] : '';
        if ($id<=0 || empty($plg)) {
            return 'Wrong call';
        }
        $bkend = appEnv::getPluginBackend($plg);
        $logpref = $bkend->getLogPref();
        $data = $bkend->loadPolicy($id, 'print');
        $access = $bkend->checkDocumentRights($data);
        # writeDebugInfo("data: ", $data);

        if (!$access) {
            appEnv::echoError('err-no-rights');
            exit;
        }
        if (!isValidEmail($data['insremail'])) {
            return "1".AjaxResponse::showError("У Застрахованного не заполнен или неверный адрес Email");
        }
        $flname = self::createPdfPolicy($plg, $id);
        if (is_string($flname) && is_file($flname)) {
            $files[] = $flname;
            $msgPS = appEnv::getLocalized('ps_pdf_sent_to_email');
        }
        else {
            return "1" . AjaxResponse::showError("Ошибка при генерации PDF файла, отправка не выполнена.");
        }
        $clientTpl =  self::getHtmlTemplate('eqpayments/toclient-payed-email', $plg);

        $subst = [
          '%clientname%' => $data['insurer_fullname'],
          '%productname%' => $bkend->getProgramName($data['prodcode']),
          '%datefrom%' => to_char($data['datefrom']),
          '%datetill%' => to_char($data['datetill']),
          '%policyno%' => $data['policyno'],
          '%company_phone%' => self::getCompanyPhone($plg),
          '%company_email%' => self::getCompanyEmail($plg),
          # '%paysum%' => fmtMoney($data['policy_prem']),
        ];
        $msgbody = strtr($clientTpl, $subst);

        $resCli = appEnv::sendEmailMessage(array(
            'to' => $data['insremail']
            ,'subj' => "Ваш страховой Полис $data[policyno]"
            ,'message' => $msgbody
          ),
          $files
        );

        if (!in_array($data['stateid'], [PM::STATE_FORMED, PM::STATE_PAYED,PM::STATE_POLICY,PM::STATE_UWAGREED])) {
            $ret = '1' . AjaxResponse::showError('Текущий статус полиса не позволяет отправить клиенту PDF');
            return $ret;
        }

        if ($resCli) {
            $ret = '1' . AjaxResponse::showMessage('Письмо с вложенным PDF полисом отправлено клиенту<br>на адрес '.$data['insremail'], 'Отправка сообщения');
            appEnv::logEvent($logpref.'SEND PDF',"Клиенту отправлено письмо с полисом на $data[insremail]",0, $id);
        }
        else
            $ret = '1' . AjaxResponse::showError("Отправка сообщения на адрес $data[insremail] не выполнена, попробуйте позднее");
        # WriteDebugInfo("return AJAX string : ", $ret);
        return $ret;
    }
    /**
    *  получить курс валюты на дату
    * @param mixed $currency ISO код валюты
    * @param mixed $date на какую дату надо получить
    * @since 1.18
    */
    public static function getCurrRate($currency='USD', $date='') {
        include_once('class.currencyrates.php');
        $ret = CurrencyRates::GetRates($date, $currency);
        return $ret;
    }
    # беру курс для авто-пересчета при пролонгации - либо текущий, либо внутренний
    public static function getProlongRate($cur='USD') {
        $ret = Appenv::getConfigValue('intrate_usd', 60);
        # $ret = self::getCurrRate('USD');
        return $ret;
    }
    # сохраняю выбранного подписанта у полиса
    public static function updatePolicySigner() {
        Signers::updatePolicySigner();
    }

    /**
    * заносит в массив непустые значения источников дохода
    * (из параметров после ввода на форме)
    * @since 1.19
    */
    public static function getIncomeSources() {
        $ret = [];
        foreach(array_keys(self::$income_sources) as $id) {
            if (!empty(appEnv::$_p[$id])) $ret[$id] = appEnv::$_p[$id];
        }
        return $ret;
    }
    /**
    * Формирует список для выбора файла, с первой опцией "не выбрано"
    *
    * @param mixed $folderMask Папка и маска дял отбора
    * @param mixed $exclude какие файлы не исключить из списка (маска)
    * @since 1.20
    */
    public static function getFilesForSelect($folderMask, $exclude = FALSE) {
        $ret = [ ['','-- нет --'] ];
        foreach(glob($folderMask) as $fl) {
            $fname = basename($fl);
            if (!empty($exclude) && fnmatch($exclude,$fname)) continue;
            $ret[] = [$fname,$fname];
        }
        return $ret;
    }
    /**
    * Делает строку с перечислением всех "включенных" источников дохода
    * (для выгрузки в XLS файл)
    * @param mixed $src - ассоц. массив ID => value
    */
    public static function IncomeSourcesToString($src) {
        $ret = [];
        foreach(self::$income_sources as $id=>$text) {
            if (!empty($src[$id])) {
                if ($id==='income_descr') $ret[] = $src[$id];
                else $ret[] = $text;
            }
        }
        return (count($ret)>0 ? implode(';', $ret) : '');
    }

    # собираю список опций для SELECT поля binded_to в T_EXPORTCFG
    public static function getListExportCfg() {
        $ret = [ ['0','--'] ];
        $dta = appEnv::$db->select(PM::T_EXPORTCFG, ['fields'=>'id,product',
          'where' => "binded_to=0", 'orderby'=>'product','associative'=>0]);
        if (is_array($ret)) $ret = array_merge($ret, $dta);
        return $ret;
    }
    # универсальный получатель записи о полисе + из agmtdata и определяет metatype (1=БАНК...)
    public static function getPolicyData($module,$id) {
        $fields = "plc.*, agt.b_clrisky,agt.b_oprisky,agt.oprisky_descr,agt.date_recalc,agt.datefrom_max,agt.date_release,agt.date_release_max";
        if ($module === PM::INVEST) {
            $fields .= ",plc.createdby userid, plc.headbankid headdeptid, 0 metatype";
            $tbName = ['plc'=>'bn_policy'];
            $where= ['plc.id' => $id];
            $join = ['type'=>'LEFT', 'table' => (PM::T_AGMTDATA. ' agt'), 'condition'=>"agt.module='$module' AND agt.policyid=$id" ];
        }
        else {
            $fields .= ",plc.userid, plc.headdeptid, plc.metatype";
            $tbName = ['plc' => PM::T_POLICIES];
            $where= ['plc.stmt_id' => $id];
            $join = ['type'=>'LEFT', 'table' => (PM::T_AGMTDATA. ' agt'), 'condition'=>"agt.module='$module' AND agt.policyid=$id" ];
        }
        $ret = appEnv::$db->select($tbName,['fields' => $fields, 'where'=>$where,'join' => $join, 'singlerow'=>1]);
        # writeDebugInfo("getPolicy raw sql ", appEnv::$db->getLastQuery());
        if($module === PM::INVEST) {
            $ret['equalinsured'] = $ret['insuredisinsurer'] ?? 0;
            $ret['insurer_type'] = $ret['isjinsurer'] ? 2 : 1;
            $head = $ret['headbankid'] ?? 0;
            $ret['policy_prem'] = round($ret['payamount']/100,2);
            $ret['insurer_type'] = $ret['isjinsurer'] ? 2 : 1;
        }
        else {
            $head = $ret['headdeptid'] ?? 0;
        }
        if($head>0 && empty($ret['metatype'])) $ret['metatype'] = OrgUnits::getMetaType($head);
        # echo "sql :".appEnv::$db->getLastQuery().'<br>';
        # echo "sql err:".appEnv::$db->sql_error();
        return $ret;
    }

    # получить только доп-риски по полису (поля riskid,risksa,riskprem)
    public static function getPolicyRiders($module, $plcid) {
        $arRet = \AppEnv::$db->select(PM::T_AGRISKS, ['where'=>[ 'stmt_id'=>$plcid, 'rtype'=>PM::RSKTTYPE_ADDITIONAL ],
          'fields'=>'riskid,risksa,riskprem','orderby'=>'id']);
        return $arRet;
    }

    # простановка ответа на вопрос о соотв-ии Застрахованного декларации
    public static function setMedDeclar() {
        $id = (isset(appEnv::$_p['id'])) ? appEnv::$_p['id'] : 0;
        $module = (isset(appEnv::$_p['module'])) ? appEnv::$_p['module'] : '';
        $nouw = (isset(appEnv::$_p['nouw'])) ? appEnv::$_p['nouw'] : 0;
        if($module === 'lifeag') $nouw = 1; # Агентские - не перевожу в статус на UW, там свой процесс!
        $newval = (isset(appEnv::$_p['declarval'])) ? appEnv::$_p['declarval'] : '';
        $bkend = appEnv::getPluginBackend($module);
        $access = $bkend->checkDocumentRights($id);
        $pdta = self::getPolicyData($module,$id);
        # if(self::$debug)  writeDebugInfo("setMedDeclar, params: ", appEnv::$_p);
        # if(self::$debug)  writeDebugInfo("setMedDeclar, policyData: ", $plcdata);
        if ($access<1.5) {
            appEnv::echoError('err-no-rights');
            exit;
        }

        if ($pdta['stateid']>6 && !$bkend->isAdmin()) {
            appEnv::echoError('err_meddeclar_wrong_state');
            exit;
        }
        if (!empty($pdta['med_declar']) && !$bkend->isAdmin()) {
            appEnv::echoError('err_meddeclar_already_set');
            exit;
        }
        $updt = ['med_declar' => $newval ];
        $arAgmt = FALSE;
        $uw = FALSE;
        if ($newval === 'N' && in_array($pdta['stateid'],[0,1,3,6]) ) {
            if (intval($pdta['reasonid']) == 0)
                $updt['reasonid'] = PM::UW_REASON_DECLARATION;
                $arAgmt = ['uw_reason2' => PM::UW_REASON_DECLARATION, 'uw_hard2' => 10 ];

            if(!empty($bkend->declarToUw) && $nouw==0 ) {
                $updt['stateid'] = PM::STATE_UNDERWRITING;
                $uw = TRUE;
            }
            # else writeDebugInfo("stateid not channged: nouw = [$nouw], stateid=$plcdata[stateid]");
        }
        if(self::$debug) writeDebugInfo("setMedDeclar, new policy values: ", $updt);
        $result = self::updatePolicy($module, $id, $updt);

        if(self::$debug) writeDebugInfo("update policy result=[$result], SQL: ", appEnv::$db->getLastQuery() );

        if ($result) {
            $lpref = $bkend->getLogPref();
            $yesno = ($newval === 'Y') ? 'ДА':'НЕТ';
            $logStr = "Проставлена отметка соответствия Декларации: $yesno";
            appEnv::logEvent($lpref . 'DECLARATION', $logStr,0, $id);
            if($arAgmt) {
                AgmtData::saveData($module, $id, $arAgmt); # запоминаю новое основание для UW!
                # writeDebugInfo("запомнил новую тяжесть UW ", $arAgmt);
            }
            if ($uw)
                appEnv::logEvent($lpref . 'SET STATE', 'Договор переведен в статус На андеррайтинге (несоответствие декларации Застрах.)',0, $id);

            # $ret = '1' . $bkend->refresh_view($id, true);
            exit('1');
        }
        else exit('Ошибка при сохранении статуса соответствия декларации!');
        # exit( 'data <pre>' . print_r($pdta,1). '</pre>');
    }

    # AJAX - заносим текст "особые условия" в полис
    public static function doSetSpecCond() {

        $id = (isset(appEnv::$_p['id'])) ? appEnv::$_p['id'] : 0;
        $module = (isset(appEnv::$_p['module'])) ? appEnv::$_p['module'] : '';
        $spctext = (isset(appEnv::$_p['spctext'])) ? appEnv::$_p['spctext'] : '';
        $bkend = appEnv::getPluginBackend($module);
        $admin = $bkend->isAdmin();
        $isUw = $bkend->isUnderWriter();
        if (!$isUw && !$admin) {
            appEnv::echoError('err-no-rights');
            exit;
        }
        $result = appEnv::$db->update(PM::T_SPECDATA, ['spec_conditions'=>$spctext], ['stmt_id'=>$id]);
        if ($result) {
            $lpref = $bkend->getLogPref();
            $logStr = "Изменен текст особых условий";
            if($spctext == '') {
                $response = AjaxResponse::hide("#specinfo");
                $logStr = 'Текст особых условий удален';
            }
            else {
                $viewTxt = nl2br(htmlentities($spctext));
                $response = AjaxResponse::show("specinfo").AjaxResponse::setHtml("specinfodata",$viewTxt);
            }
            appEnv::logEvent($lpref . 'SET-SPECCOND', $logStr,0, $id);
            exit("1".$response);
        }
        else {
            $err = appEnv::$db->sql_error();
            if (appEnv::isProdEnv()) {
                exit('Ошибка при сохранении текста!');
            }
            else
                exit('Ошибка при сохранении текста!<br>' . $err);
        }
    }
    # Полное название валюты, для печатных форм
    public static  function getCurrencyFull($currency)
    {
        switch(strtoupper($currency)) {
            case 'RUR': case 'RUB':
                return 'Рубли РФ';
            case 'USD':
                return 'Доллары США';
            case 'EUR':
                return 'Евро';
        }
        return strtoupper($currency);
    }

    # вернет наименование БП-статуса договора (alf_agreements.bpstateid)
    public static function decodeBpState($stateid, $short = FALSE) {
        switch($stateid) {
            case PM::BPSTATE_WAITPAYMENT : return 'Ожидает оплаты';
            case PM::BPSTATE_TOACCOUNTED : return ($short ? 'ожидает проверки':'Ожидается отправка на проверку/к учету');
            case PM::BPSTATE_ACCOUNTED : return 'Направлен к учету';
            case PM::BPSTATE_ACTIVE : return 'Активный';
            case PM::BPSTATE_UWREWORK : return 'На доработке у андеррайтера';
            case PM::BPSTATE_SENTEDO: return '<span class="r_yellow">Э</span>'; # ЭДО-отправили запрос
            case PM::BPSTATE_SENTPDN: return '<span class="r_yellow">П</span>'; # ПДН-отправили запрос
            case PM::BPSTATE_PDN_NO : return '<span class="r_orange">П</span>'; # ПДН-отказали
            case PM::BPSTATE_PDN_OK : return '<span class="r_green">П</span>'; # ПДН-согласился
            case PM::BPSTATE_EDO_NO : return '<span class="r_orange">Э</span>'; # ЭДО-отказали
            case PM::BPSTATE_EDO_OK : return '<span class="r_green">Э</span>'; # ЭДО-согласился
        }
        return '';
    }

    # для выбора "региональной привязки" орг-юнита в справочнике орг-юнитов (подразделений)
    public static function OURegionList() {
        return self::$ouRegions;
    }
    public static function getUserCurator($userid=0) {
        if (!$userid) $userid = appEnv::$auth->userid;
        if ($userid<=0) return FALSE;
        $mgrid = appEnv::$db->select(PM::T_USERS, [
          'fields'=>'manager_id','where'=>['userid'=>$userid],'singlerow'=>1,'associative'=>0]
        );
        $ret = ($mgrid>0) ? self::getCuratorData($mgrid) : FALSE;
        return $ret;
    }
    # все данные о кураторе по его ИД
    public static function getCuratorData($curatorid) {
        $ret = appEnv::$db->select(PM::T_CURATORS, ['where'=>['id'=>$curatorid],'singlerow'=>1]);
        return $ret;
    }

    # Поиск агента по его ИКП (в справочнике кураторов-агентов, потом - в учетках?)
    # $everywhere = 1 - буду иксать в учетках, если не нашел в alf_curators
    public static function findAgent($ikp, $fullInfo = FALSE, $everywhere=FALSE) {

        $ikpFull = self::IKP_PREFIX . $ikp;
        $ret = appEnv::$db->select(PM::T_CURATORS, ['where'=>"ikp IN('$ikp','$ikpFull')", 'singlerow'=>1]);
        if ($ret) return ($fullInfo ? $ret : $ret['id']);
        if ($everywhere) {
            $ret = appEnv::$db->select(PM::T_USERS, ['where'=>"code_ikp IN('$ikp','$ikpFull')", 'singlerow'=>1]);
            if (isset($ret['userid'])) return ($fullInfo ? $ret : $ret['userid']);
        }
        return FALSE;
    }
    # Поиск куратора по его ИКП (в справочнике кураторов)
    public static function findCurator($ikp, $fullInfo = FALSE) {
        $ikpFull = self::IKP_PREFIX . $ikp;
        $ret = appEnv::$db->select(PM::T_CURATORS, ['where'=>"ikp IN('$ikp','$ikpFull') AND (b_curator=1)", 'singlerow'=>1]);
        if (isset($ret['id'])) return ($fullInfo ? $ret : $ret['id']);
        return FALSE;
    }
    /**
    * Вычищаю из массива профессий ненужные
    *
    * @param mixed $proflist полный список
    * @param mixed $parr что надо удалить (массив)
    * @param mixed $off номер елемента в строке, какую проверять
    */
    public static function deleteItems(&$proflist, $parr, $off=0) {

        foreach($parr as $dprof) {
            foreach($proflist as $no => $item) {
                if(isset($item[$off]) && $item[$off] == $dprof) {
                    array_splice($proflist, $no,1);
                    break;
                }
            }
        }
    }

    public static function getAgentCode($userid) {
        $ret = appEnv::$db->select(PM::T_USERS, ['where' => ['userid'=>$userid],
          'fields'=>'agentcode','singlerow'=>1,'associative'=>0
        ]);
        return $ret;
    }
    public static function getCoucheCuratorName($id) {
        $ret = appEnv::$db->select(PM::T_CURATORS, ['where' => ['id'=>$id],
          'fields'=>'fullname','singlerow'=>1,'associative'=>0
        ]);
        return $ret;
    }
    /**
    * AJAX запрос на отправку письма в компанию о создании учетки клиента, по данным из его полиса
    * @since 1.27 2020-06-30
    */
    public static function sendLetterNewAccount() {
        $module = (isset(appEnv::$_p['module']) ? appEnv::$_p['module'] : '');
        $id = (isset(appEnv::$_p['id']) ? appEnv::$_p['id'] : '');
        # writeDebugInfo("sendLetterNewAccount, $module/$id");
        $bkend = appEnv::getPluginBackend($module);
        if ($module !='investprod')
            $data = $bkend->loadPolicy($id, 'print');
        else $data = $bkend->getagreementForexport($id);

        $logPref = $bkend->getLogPref();
        ClientUtils::sendLetterNewClient($data,$logPref);
        exit('1');
    }

    # hook вызываемый при печати PDF (добавление страницы, имеющей этот хук в параметре pageevent)
    public static function setMarkPage($pageNo = 0) {
        self::$markedPages[] = $pageNo;
        # writeDebugInfo("setMarkPage($pageNo) executed");
    }
    # hook вызываемый после печати последей страницы PDF (запомнить ее номер)
    # из printformPdf придет массив вида ['lastPage' => 18]
    public static function setLastPdfPage($data = 0) {
        self::$lastPrintedPage = $data;
        # writeDebugInfo("setLastPdfPage() done, param:", $data);
    }
    # расшифровывает ИД типа скан-файла
    public static function decodeScanType($dtype, $scanTypes = FALSE) {
        $dspl = explode(':', $dtype);
        $dtype = $dspl[0];
        $postfix = $dspl[1] ?? '';
        if ($dtype === PM::$scanEdoPolicy) return 'Подписанный полис (ПЭП)';
        if ($dtype === PM::$scanCheckLog || $dtype == '100') return 'Лог проверок'; # 'checklog'
        if ($dtype === '0') return 'Сканы документов'; # investprod - все без разбору

        $scTypes = !empty($scanTypes) ? $scanTypes : PM::$scanTypes;
        if (isset($scTypes[$dtype])) return $scTypes[$dtype];
        return $dtype;
    }

    public static function decodeDocType($typeid) {
        switch($typeid) {
            case 0 : case 1:  return 'Паспорт РФ';
            case 2 : return 'Свидетельство о рождении';
            case 3 : return 'Военный билет';
            case 4 : return 'Загранпаспорт';
            case 6 : return 'Паспорт иностр.гражд.';
            case 11: return 'Свидетельство о регистрации';
            case 20: return 'Миграционная карта'; # типы документов иностранца, "разрешающих пребывание"
            case 21: return 'Виза';
            case 22: return 'Вид на жительство';
            case 23: return 'Разрешение на временное пребывание';
            case 99: return 'Иной документ';
        }
        return "NA[$typeid]";
    }

    public static function decodeVisaType($typeid) {
        switch($typeid) {
            case 20: case 21: case 22: case 23: return self::decodeDocType($typeid);
            default: return 'Миграционная карта';
        }
    }

    # формирует html код для выбора типа документа, разрешающего пребывание иностранца
    public static function allowDocTypesInput() {
        if(!self::PERMIT_TYPES) return 'Миграционная карта';
        $ret = '<select name="{pref}permit_type{no}" id="{pref}permit_type{no}" class="form-select form-select-sm d-inline w160" >';
        for($k=20; $k<=23; $k++) {
            $ret .= "<option value=\"$k\">".self::decodeDocType($k).'</option>';
        }
        $ret .= '</select>';
        return $ret;
    }
    /**
    * Вернет строку с названием документа в родительном падеже: "паспорта", "свид-ва о рождении"...
    * @param mixed $doctype
    */
    public static function decodeDocTypeRp($doctype) {
        switch( $doctype ) {
            case '1':  return 'паспорта';
            case '2':  return 'свид-ва о рождении';
            case '3':  return 'военного билета';
            case '4':  return 'занранпаспорта';
            case '6':  return 'иностранного паспорта';
            case '20': return 'миграционной карты(визы)';
            case '21': return 'визы';
            case '22': return 'вида на жительство';
            case '23': return 'разрешения на временное пребывание';
        }
        return 'иного документа';
    }

    # ajax: запрос на отправку письма клиенту с оригиналом полиса с ПЭП (ЭДО процесс)
    public static function sendEdoLetterToClient() {
        $module = isset(appEnv::$_p['module']) ? appEnv::$_p['module'] : '';
        $id = isset(appEnv::$_p['id']) ? appEnv::$_p['id'] : '';
        if (!$module || !$id) exit('wrong call/no module or id');
        UniPep::sendLetterToClient($module, $id);
    }

    public static function getAvailablePrograms($module, $deptid=0, $allCodes=FALSE) {
        # if($module === 'boxprod') self::$debug = 1;
        if(self::$debug) writeDebugInfo("allCodes: ", $allCodes);
        $hdept_id = OrgUnits::getPrimaryDept($deptid);

        $ret = [];
        $dta = \AppEnv::$db->select(PM::T_DEPT_PROD,
          array(
             'where'=>array('module'=>$module,'deptid'=>$hdept_id, "b_active>0")
            ,'associative'=>1
          )
        );
        if(self::$debug) {
            writeDebugInfo( "SQL for ".PM::T_DEPT_PROD. " : ", \AppEnv::$db->getLastQuery() );
            writeDebugInfo( "dta from DB ", $dta);
        }
        if (is_array($dta)) foreach ($dta as $item) {
            if (empty($item['prodcodes']) && !empty($allCodes)) {
                if(is_array($allCodes)) $codes = array_keys($allCodes);
                elseif(is_string($allCodes)) $codes = explode(',', $allCodes);
            }
            else {
                /*
                if(empty($item['prodcodes'])) {
                    if(self::$debug) writeDebugInfo("empty prodcodes, return *");
                    return '*';
                }
                */
                $codes = explode(',', $item['prodcodes']);
            }
            foreach($codes as $onecode) {
                if (!empty($onecode) && !in_array($onecode, $ret)) $ret[] = $onecode;
            }
        }
        return $ret;
    }
    # установка признака печати ЭДО-полиса
    public static function setPrintEdoMode($mode) {
        self::$printEdoMode = $mode;
    }
    # Это чо, печатается ЭДО-полис?
    public static function isPrintEdoMode() { return self::$printEdoMode; }

    # если есть -EDO пара для шаблона и включен режисм ЭДО-печати, возвращаю ЭДО-шное имя шаблона
    # {upd/2025-02-11} начал передавать папку, в которой нвдо искать ЭДО версию файла. Передавать с концевым слешем!
    public static function getTemplateEDO($srcXml, $debug=FALSE, $inFolder = '') {
        if ($debug) writeDebugInfo("getTemplateEDO($srcXml), EDO mode: [".self::$printEdoMode.']');
        $edoXml = substr($srcXml, 0, -4) . '-EDO.xml';
        if(self::$printEdoMode && is_file($inFolder.$edoXml)) $ret = $edoXml;
        else $ret = $srcXml;
        if ($debug) writeDebugInfo("src xml: $srcXml, edoXML: {$inFolder}{$edoXml}, self::printEdoMode=[".self::$printEdoMode."] final: $ret");
        return $ret;
    }
    # получить номер страницы из PDF, "маркированной" как держатель ЭЦП блока
    public static function getMarkedPage($no=0) {
        return (isset(self::$markedPages[$no]) ? self::$markedPages[$no] : 0);
    }
    public static function setSignatureData($params) {
        self::$signatureData = $params;
        if(self::$debug) writeDebugInfo("set digisign params ", self::$signatureData);
    }
    # Зазадю один конкретный параметр для ЭЦП
    public static function setSignatureDataParam($parname, $value) {
        self::$signatureData[$parname] = $value;
    }
    public static function getSignatureData() {
        return self::$signatureData;
    }
    public static function setIamUw($uwMode = TRUE) {
        self::$_iamUw = $uwMode;
    }

    # рассрочка в род.падеже (RP)
    public static function getRassrochkaRP($rassrochka, $ending='F') {
        $post = ($ending==='F') ? 'й' : 'го';
        if (isset(self::$rassrochkaRP[$rassrochka])) return (self::$rassrochkaRP[$rassrochka].$post);
        return 'неизвестно'.$post;
    }
    # рассрочка - в виде наречия
    public static function PayPeriodNarechie($rassrochka) {
        switch($rassrochka) {
            case 1: return 'ежемесячно'; break;
            case 3: return 'ежеквартально'; break;
            case 6: return 'раз в полугодие'; break;
            case 12: return 'ежегодно'; break;
            case 0:  return 'единовременно';
            default: return "[$rassrochka]";
        }
    }

    # расчет делается под андеррайтером?
    public static function isUwWorking() { return self::$_iamUw; }

    /**
    * рассчитает дату окончания д-вия риска инвалидности в зависимости от возраста застрахованного на дату начала
    * @since 1.33 (2020-09-02)
    * @param mixed $datefrom дата начала д-вия
    * @param mixed $sex пол застр. - M | F
    * @param mixed $datebirth дата рожд-я
    * @param mixed $datetill станд. дата окончания (если передать, вернет ее для "молодых" застрахованных
    * @param mixed $maxAge - если передан свой лимит возраста, счиать по нему, а не по пенсионному
    */
    public static function getInvalidEndDate($datebirth, $datefrom, $sex='M',  $datetill='', $maxAge = 0) {
        # writeDebugInfo("getInvalidEndDate($datebirth, $datefrom, $sex,  $datetill, $maxAge");
        list($years, $days) = DiffDays($datebirth, $datefrom, 1);
        if (!$maxAge) {
            if ($sex === 'child') $maxAge = 18;
            else $maxAge = ($sex ==='F') ? AppEnv::getConfigValue('ins_pensionage_f', 60)
              : AppEnv::getConfigValue('ins_pensionage_m', 65);
        }
        $maxPeriod = $maxAge - $years;
        # writeDebugInfo("datetill: $datetill, maxPeriod: $maxAge - $years = $maxPeriod");
        if ($maxPeriod <=0) $retDate = '';
        else $retDate = addToDate($datefrom,$maxPeriod,0,-1);
        if (intval($datetill) && $retDate > $datetill) $retDate = $datetill;
        if (!intval($retDate)) writeDebugInfo("getInvalidEndDate: zero date for $datebirth, $datefrom, $sex, $datetill, $maxAge)");

        return $retDate;
    }

    public static function buildPhone($no, $withPref = FALSE) { # $pref, - отказываемся от отд.префикса
        if(!empty($no) && $withPref) {
            # $ret = (empty($pref) ?$no : self::PHONE_RUSPREF."($pref) $no");
            $strPref = ($withPref === TRUE || $withPref===1) ? self::PHONE_RUSPREF : $withPref;
            if(stripos($no, $strPref)===FALSE)
                $ret = "$strPref $no";
            else $ret = $no;
        } else {
            $ret = $no;
            # $ret = (empty($pref) ?$no : "$pref-$no");
        }
        # writeDebugInfo("$pref, $no,[$withPref]returning $ret ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        return $ret;
    }
    public static function buildAllPhones($pref, $dta, $withPref = FALSE) {
        $ret = '';
        if(!empty($dta[$pref.'phone'])) $ret = self::buildPhone($dta[$pref.'phone'], $withPref); # $dta[$pref.'phonepref'],

        if(!empty($dta[$pref.'phone2'])) {
            $ret .= ($ret ? ", " : '') . self::buildPhone($dta[$pref.'phone2'], $withPref); # $dta[$pref.'phonepref2'],
        }
        return $ret;
    }
    # формирую строку полного документа (паспорт 1234 123456 выдан 01.01.2002 [место _выдачи_если full=1]
    # {upd/2021-12-21} - Добавил вывод кода подр.
    public static function buildFullDocument($data,$pref='', $full=FALSE, $plcData=FALSE) {
        # writeDebugInfo("data ", $data, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3));
        $phType = isset($plcData['insurer_type']) ? $plcData['insurer_type'] : 1;
        if ($pref === 'insr' && $phType == 2) {
            # ЮЛ
            $ret = "Свидетельство о регистрации ".$data[$pref.'docser'] .' '.$data[$pref.'docno'] . ', выдано '.to_char($data[$pref.'docdate']);
            if($full && !empty($data[$pref.'docissued'])) $ret .= ', место выдачи: '.$data[$pref.'docissued'];
            return $ret;
        }
        $rCountry = $data[$pref.'rez_country'] ?? '';
        if (!self::isRF($rCountry)) {
            # иностранец - всегда инопаспорт (+миг-карта если заполнена)
            $ret = 'Иностранный паспорт '.$data[$pref.'inopass'];
            if (intval($data[$pref.'docdate'])) $ret .= ', выдан '.to_char($data[$pref.'docdate']);
            if(!empty($data[$pref.'migcard_ser'])) {
                if(!empty($data[$pref.'permit_type']))
                    $permitName = self::decodeDocType($data[$pref.'permit_type']);
                else
                    $permitName = 'Миграционная карта';
                $ret.= ", $permitName ".$data[$pref.'migcard_ser']. ' '.$data[$pref.'migcard_no'];
                if (intval($data[$pref.'docfrom'])) $ret .= ", срок пребывания с ".to_char($data[$pref.'docfrom'])
                    . ' по '.to_char($data[$pref.'doctill']);
            }
            return $ret;
        }
        $ret = self::decodeDocType($data[$pref.'doctype']) . ' ';
        $doctype = $data[$pref.'doctype'];
        if (in_array($doctype, [0,1,2,3,4,11,20])) $ret .= $data[$pref.'docser'] . ' '.$data[$pref.'docno'];
        else $ret .= $data[$pref.'docno'];
        if (intval($data[$pref.'docdate']) || $data[$pref.'docissued']) {
            if($doctype == 20) $ret .= ', выдана ';
            else $ret .= (in_array($doctype, [2,11]) ? ', выдано ' : ', выдан ');

            if (!empty($data[$pref.'docdate']) && intval($data[$pref.'docdate'])) $ret .= to_char($data[$pref.'docdate']) . ' ';
            if ($full && in_array($doctype, [0,1,2,3,4,11])) $ret .= $data[$pref.'docissued'];
            if ($full>=2 && $doctype == PM::DT_PASSPORT && !empty($data[$pref.'docpodr'])) {
                $ret .= ", код подразделения ".$data[$pref.'docpodr'];
            }
        }
        return $ret;
    }

    # $full = 'f' - хочу с фактическим адресом, еслм он отличается от прописки,
    # full=1|TRUE - адрес прописки, а если свой фактич - добавить и его, 'f' - адрес проживания, 'r'-только адрес прописки!
    public static function buildPersonFull($dta, $pref='', $full = FALSE) {
        # echo 'data <pre>' . print_r($dta,1). '</pre>'; exit;
        $ret = '';
        if (isset($dta[$pref . 'fam'])) {
            $ret = $dta[$pref . 'fam'] . ' '.$dta[$pref . 'imia'] . (!empty($dta[$pref . 'otch']) ? ' '.$dta[$pref.'otch']:'');
        }
        elseif(isset($dta[$pref . 'fullname']))
            $ret = $dta[$pref . 'fullname'];

        if (!empty($dta[$pref . 'birth']) && intval($dta[$pref . 'birth'])) {
            $ret .= ', '. to_char($dta[$pref . 'birth']) ; # {upd/2022-06-30} убрал "рожд." - запрос Нисурминой
        }
        $ret .= "\n";

        if($full >= 2) $ret .= "\n"; # Для некоторых случаев нужна доп.пустая строка после ФИО-рожд и перед адресом

        if ($full==='f') { # хочy только адрес проживания
            if(empty($dta[$pref.'sameaddr']))
                $ret .= PolicyModel::buildFullAddress($dta,$pref,'f') . "\n";
            else
                $ret .= PolicyModel::buildFullAddress($dta,$pref,'') . "\n";
        }
        else {
            $ret .= PolicyModel::buildFullAddress($dta,$pref,'') . "\n"; # адрес прописки/регстр.
            if(!empty($full) && $full !== 'r' && empty($dta[$pref.'sameaddr']))
                $ret .= 'адрес проживания '.PolicyModel::buildFullAddress($dta,$pref,'f') . "\n";
        }
        $docInfo = self::printedDocumentInfo($dta,$pref, $full);
        $ret .= $docInfo;

        return $ret;
    }
    # AJAX-запрос от грида "история действий с договором", возвращаю json - записи
    public static function getAgrHistory() {

        global $auth;
        $id = isset(appEnv::$_p['id']) ? appEnv::$_p['id'] : 0;
        $prefix = isset(appEnv::$_p['prefix']) ? appEnv::$_p['prefix'] : 'NONE.';

        appEnv::avoidExternalHeaders();
        if ( $id<=0) {
            exit('1');
        }

        $dt = appEnv::$db->select(appEnv::$TABLES_PREFIX.'eventlog', array('where'=>"(itemid=$id) AND (evtype LIKE '{$prefix}%')",'orderby'=>'evid'));

        $rows = is_array($dt) ? count($dt) : 0;
        $response = array();
        $reccnt = $response['records'] = $rows;

        if(is_array($dt) && count($dt)) {
            ob_start();
            foreach($dt as $row) {
                $rid = $row['evid'];
                $userid = $row['userid'];
                $user = evShowUser($row['userid']); // GetEmployeeFIO()
                $dept = $row['deptid']; // GetEmployeeFIO()
                $userView = toUtf8($user);
                if (intval($userid))
                    $userView = "<a href='javascript:void(0)' onclick='alfoCore.viewPerson(this,$userid,$dept)'>$userView</a>";
                $response['rows'][] = array(
                   'id'   => $rid,
                   'cell' => array(
                     to_char($row['evdate'],1),
                     toUtf8($row['evtype']),
                     $userView,
                     toUtf8($row['evtext'])
                   )
                );
            }
        }
        exit (json_encode($response, 256)); # JSON_UNESCAPED_UNICODE
    }

    /**
    * присказка о конфиденциальности, для писем клиентам. Пихать в конец своего письма.
    * @param $withFiles - передать 1|TRUE если в письме пересылаются вложения (будет дополн текст!)
    * $since 1.35, 2020-10-16
    */
    public static function LetterBottomText($withFiles = FALSE) {
        if ($withFiles)
            return "\nКонфиденциальная информация. Настоящее сообщение и все приложения (вложения) к нему предназначены исключительно для использования указанными в нем получателями и включает конфиденциальную информацию.\n";
        else
            return "\nКонфиденциальная информация. Настоящее сообщение предназначено исключительно для использования указанными в нем получателями и включает конфиденциальную информацию.\n";
    }

    /**
    * Определяет, есть ли доступ к вводу новых полисов
    * @param mixed $module
    * @since 1.37 (2020-10-27}
    */
    public static function canAddPolicy($module) {
        $pdept = OrgUnits::getPrimaryDept();
        $btest = appEnv::$db->select(PM::T_USERS,['where'=>['userid'=>appEnv::$auth->userid], 'singlerow'=>1]);

        $where = ['deptid'=>$pdept, 'module'=>$module];
        if (empty($btest['is_test'])) $where['b_active'] = 1;

        $data = appEnv::$db->select(PM::T_DEPT_PROD,['where'=> $where]);
        if (!$data) return FALSE;
        return TRUE;
    }

    /**
    * Нажали кнопку оформить заказ на доставку полиса
    * ИЛИ вызывали из UniPep::FiPolicy с переданным module, id
    */
    public static function orderDelivery($module='', $plcid='') {
        $return = !empty($plcid);
        if (!$module) $module = (isset(appEnv::$_p['module']) ? appEnv::$_p['module'] : '');
        if (!$plcid) $plcid = (isset(appEnv::$_p['id']) ? appEnv::$_p['id'] : '');
        $text = "TODO: create order $module / $id";
        $bkend = appEnv::getPluginBackend($module);
        $plcdata = $bkend->loadPolicy($id,'print');
        # writeDebugInfo("orderDelivery/$module/policy : ", $plcdata);
        $dParams = [];
        if ($module === 'investprod') {
            # exit( '1'.ajaxResponse::showMessage('<pre>'.print_r($plcdata,1).'</pre>'));
            $fio = $plcdata['insr']['lastname'].' '.$plcdata['insr']['firstname'].' '.$plcdata['insr']['middlename'];
            $dParams = [
              'receiver_fullname' => $fio,
              'receiver_contact' => $fio,
              'receiver_zip' => $plcdata['insr']['addr_postcode'],
              'receiver_addr' => $plcdata['insr']['address'],
              'receiver_phone' => $plcdata['insr']['phone'],
              'receiver_email' => $plcdata['insr']['email'],
              'pack_description' => 'Пакет страховых документов',
            ];

        }
        else {
            $adPref = !empty($plcdata['insrsameaddr']) ? 'insradr' : 'insrfadr';
            $fullAddr = isset($plcdata['insrfullfaddr']) ? $plcdata['insrfullfaddr'] : $plcdata['insrfulladdr'];
            $dParams = [
              'receiver_fullname' => $plcdata['insurer_fullname'],
              'receiver_contact' => $plcdata['insurer_fullname'],
              'receiver_zip' => $plcdata[$adPref.'_zip'],
              'receiver_addr' => $fullAddr,
              'receiver_phone' => $plcdata['insrphone'], # self::buildPhone($plcdata['insrphonepref'],$plcdata['insrphone']),
              'receiver_email' => $plcdata['insremail'],
              'pack_description' => 'Пакет страховых документов',
            ];
        }
        if(self::$debug) writeDebugInfo("send params ", $dParams);

        $ordResult = Delivery::createPackage($dParams, $module, $id);
        if(self::$debug) writeDebugInfo('delivery result: ', $ordResult);

        if ($return) return $ordResult;

        if (!empty($ordResult['package_id'])) {
            $text = "Оформлен Заказ на доставку $ordResult[package_id]";
            $msgClass = 'msg_ok';
        }
        else {
            $text = 'Ошибка оформления заказа: ' . $ordResult['message'];
            $msgClass = 'msg_error';
        }
        $ret = '1' . AjaxResponse::showMessage($text, 'Результат загрузки',$msgClass);
        if (!empty($ordResult['package_id'])) {
            # надо убрать кнопку "оформить доставку"
            if ($module === 'investprod') {
                $ret .= $bkend->refresh_view($id, $plcdata, FALSE);
            }
            else {
                $ret .= $bkend->refresh_view($id, true);
            }
        }
        # writeDebugInfo("final ajax ret:", $ret);
        exit($ret);
    }

    public static function ModulesForRanges() {
        $ret = array_merge(
          [
            ['','-все модули-'],
            [PM::RANGE_BILLS,'-номера счетов-'],
            [PM::RANGE_XML,'-XML выгрузки-']
          ],
          PolicyModel::getAgmtModules()
        );
        return $ret;
    }
    # конверторы для вывода галок по полу человека (M|F)
    public static function isMale($par = '') {
        return (($par === 'M') ? 1:0);
    }
    public static function isFemale($par = '') {
        return (($par === 'F') ? 1:0);
    }
    public static function isEmpty($par = '') {
        return (empty($par) ? 1:0);
    }
    public static function isNotEmpty($par = '') {
        return (empty($par) ? 0:1);
    }

    # {upd/2020-12-30} Получить лимит взноса(руб), при достижении которго требовать анкету страхователя/клиента
    # {upd/2024-03-04} появился отдельный лимит премии для Р-контролей!, нужен вызов своей ф-ции при ее наличии в модуле
    /* # 2025-04-03 оба лимита (15000, 40000) теперь получаем в бэкендах - getAnketaCompLimit() эта ф-ция больше не используется!
    public static function getClientAnketaLimit($module='', $program='') {
        # exit("getClientAnketaLimit('$module', $program)");
        $ret = FALSE;
        if( !empty($module) ) {
            if(is_object($module)) $bkend = $module;
            else $bkend = AppEnv::getPluginBackend($module);
            if(method_exists($bkend, 'getAnketaCompLimit'))
                $ret = $bkend->getAnketaCompLimit($program);
            else
                $ret = AppEnv::getConfigValue($module.'_clientanketa_limit',self::$clientAnketaLimit);
        }

        if(empty($ret)) $ret = appEnv::getConfigValue('life_clientanketa_limit',self::$clientAnketaLimit);

        $retVal = (($ret > 0) ? $ret : self::$clientAnketaLimit);
        # writeDebugInfo("module=$module, getClientAnketaLimit return: $retVal");
        return $retVal;
    }
    */
    # {upd/2020-12-17} делаю набор опций для SELECT - выбор "родственной связи" (для Выг-приобр, Застрахованного)
    public static function optionsRelations() {
        $ret = '<option value="">--Выбрать!--</option>';
        foreach(self::$close_relations as $item) {
            $ret .= "<option>$item</option>";
        }
        return $ret;
    }

    # {upd/2021-03-01} получить режим активности" фичи "инвест-анкета" для моего орг-юнита
    public static function isInvestAnketaActive($module='', $forDept=0) {
        # $aktStart = appEnv::getConfigValue('invest_anketa_start');
        # $active = (intval($aktStart)>0 && to_date($aktStart) <= date('Y-m-d'));
        # if ($active) {
            $deptCfg = OrgUnits::getOuRequizites($forDept, $module);
            $active = !empty($deptCfg['invanketa']) ? $deptCfg['invanketa'] : FALSE;
        # }
        return $active;
    }

    # AJAX request, формирую HTML с найденными инв-анкетами для привязки к полису
    public static function getDataForBindAnketa() {
        InvestAnketa::getDataForBindAnketa(); # переправляю в investanketa.php (перенос ф-ционала)
        /*
        $module = !empty(appEnv::$_p['module'])? appEnv::$_p['module'] : '';
        $policyid = !empty(appEnv::$_p['policyid'])? intval(appEnv::$_p['policyid']) : 0;
        $fam = !empty(appEnv::$_p['lastname'])? appEnv::$_p['lastname'] : '';
        $imia = !empty(appEnv::$_p['firstname'])? appEnv::$_p['firstname'] : '';
        $otch = appEnv::$_p['middlename'] ?? '';
        $pser = !empty(appEnv::$_p['pser'])? trim(appEnv::$_p['pser']) : '';
        $pno = !empty(appEnv::$_p['pno'])? trim(appEnv::$_p['pno']) : '';
        $birth = !empty(appEnv::$_p['birth'])? to_date(appEnv::$_p['birth']) : '';
        if(empty($policyid) && (empty($fam) || empty($imia))) exit("EMPTY_REQUEST");
        # exit( print_r(appEnv::$_p,1) . '</pre>' );
        # writeDebugInfo("getDataForBindAnketa params ", AppEnv::$_p);
        # writeDebugInfo("getDataForBindAnketa:multi=[$multi]");
        if($policyid > 0 && !empty($module))
            $dta = InvestAnketa::findFreeAnketas('', '', '','','','',$module, $policyid);
        else
            $dta = InvestAnketa::findFreeAnketas($fam, $imia, $otch, $birth, $pser,$pno);


        if ($dta === 'ASSIGNED') exit('ASSIGNED'); # К полису уже прицепили анкету!
        if (!is_array($dta) || !count($dta)) exit("NODATA"); # passed: $module / $fam / $imia");
        $html = '<div class="floatwnd w-600" id="div_bindanketa" style="z-index:2000;"><table class="zebra"><tr><th>ФИО</th>'
           . '<th>Дата рожд.</th><th>Паспорт</th><th>Заведена</th><th>Выбор</th></tr>';
        foreach($dta as $row) {
            $html .= "<tr><td>$row[lastname] $row[firstname] $row[middlename]</td><td class='ct'>$row[birth]</td>"
              . "<td>$row[docser] $row[docno]</td><td class='ct'>$row[created]</td>"
              . "<td><a href='javascript:void(0)' onclick='plcUtils.applyInvestAnketa($row[id])'>Выбрать</a></td></tr>";
        }
        $html .= '</table></div>';
        exit($html);
        */
    }
    # Получаю стандартизованные данные Страхователя по модулю и ИД полиса
    public static function getPholderForPolicy($module, $policyid, $checkPolicy=FALSE) {
        if ($module === 'investprod') {
            $plc = appEnv::$db->select('bn_policy',[
                'fields'=>'id,policyno,insurerid,IF(isjinsurer,0,1) insurer_type,anketaid',
                'where'=>['id'=>$policyid], 'singlerow'=>1
            ]);
            # writeDebugInfo("plc SQL: ", appEnv::$db->getLastQuery()); writeDebugInfo("err: ", appEnv::$db->sql_error());

            if (empty($plc['id'])) exit('Не найдена запись о полисе');
            $insr = appEnv::$db->select('bn_individual',
              ['where'=>['id'=>$plc['insurerid']],
              'fields' => "lastname fam,firstname imia,middlename otch,birthdate birth,passportseries docser,passport docno,email, phone",
              'singlerow'=>1
            ]);

        }
        else {
            $plc = appEnv::$db->select(PM::T_POLICIES,[
                'fields'=>'stmt_id,policyno,insurer_type,anketaid',
                'where'=>['stmt_id'=>$policyid], 'singlerow'=>1
            ]);
            # writeDebugInfo("plc SQL: ", appEnv::$db->getLastQuery());
            # writeDebugInfo("err: ", appEnv::$db->sql_error());
            $insr = appenv::$db->select(PM::T_INDIVIDUAL,
              [ 'where' => ['stmt_id'=>$policyid, 'ptype'=>'insr'],
              # 'fields' => 'fam,imia,otch,birth,docser,docno,email,phone',
              'singlerow'=>1
            ]);
        }
        # writeDebugInfo("policy: ", $plc);
        if($checkPolicy && $plc['anketaid'] > 0) return 'ASSIGNED';
        return $insr;
    }
    # юзер выбрал ИД анкеты, заполняю все поля на форме ввода
    public static function applyInvestAnketa() {
        investAnketa::applyInvestAnketa();
    }

    # {upd/2021-07-29} - делаю блок полных данных о ЮЛ
    public static function buildUlFull($dta,$pref = 'insr', $addr = 0) {
        # writeDebugInfo("dta: ", $dta);
        $ret = $dta['insurer_fullname'];
        if ($addr) {
            $ret .= "\n" . PolicyModel::buildFullAddress($dta, $pref,'',1);
        }
        $ret .="\n" . self::BuildUlRekvizit($dta, $pref, 1);
        return $ret;
    }

    # User Defined Filter для гридов со списками договоров - отбор улетевших в СЭД (не закончено!)
    /*
    public static function SEDFilter($action='', $par2=0) {
        $plg = isset(appEnv::$_p['plg']) ? appEnv::$_p['plg'] : 'none';
        $curValue = isset($_SESSION['ddd']) ? $_SESSION['ddd'] : '';
        switch($action) {
            case 'form': # рисую элемент выбора значения фильтра
                return '<select name="filt_setstate"><option value="N">Нет</option><option value="Y">Да</option><select> Договор в СЭД';
            case 'setfilter':
                $fltValue = isset(appEnv::$_p['filt_setstate']) ? appEnv::$_p['filt_setstate'] : '';
                $seskey = appEnv::$_p['plg'].'.'.
                $_SESSION[$seskey] = $fltValue;
                break;
            case 'compute': # рисую элемент выбора значения фильтра
                writeDebugInfo("compute: ", appEnv::$_p);
                break;
            default:
                writeDebugInfo("passed action $action, params: ", appEnv::$_p);
                break;
        }
        return 0;
    } */
    public static function BuildUlRekvizit($dta, $pref='insr', $issued = FALSE) {
        $ret = [];
        if(!empty($dta[$pref.'docser'])) $ret[] = 'свид. о регистрации '.$dta[$pref.'docser'] . ' ' . $dta[$pref.'docno'];
        # Добавлять дату и где выдано?
        if($issued) $ret[] = ' выдано '.to_char($dta[$pref.'docdate']) . ' ' . $dta[$pref.'docissued'];

        if (isset($dta[$pref.'urinn']))
            $ret[] = 'ИНН '.$dta[$pref.'urinn'];
        elseif (isset($dta[$pref.'inn']))
            $ret[] = 'ИНН '.$dta[$pref.'inn'];

        if (!empty($dta[$pref.'ogrn'])) $ret[] = 'ОГРН '.$dta[$pref.'ogrn'];
        if (!empty($dta[$pref.'kpp'])) $ret[] = 'КПП '.$dta[$pref.'kpp'];
        return implode(', ', $ret);
    }
    # строку типа "1 000 000,34" превращает в нормальное float/number число
    public static function unFormatNumber($strg) {
        $result = str_replace(',', '.', str_replace(' ', '', $strg));
        return floatval($result);
    }
    # {upd/2021-09-03} формирует строку с телефоном в формате маски ввода "(NNN)NNN-NNNN"
    public static function getMaskedPhone($phone, $pref='') {
        $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
        if (!$pref) {
            $pref = substr($phoneDigits,0,3);
            $phoneDigits = substr($phoneDigits,3);
        }
        $d3 = substr($phoneDigits,0,3);
        $dLast = substr($phoneDigits, 3);
        return "($pref)$d3-$dLast";
    }

    # перевожу некоторые ALFO-шные "профессии" в специфические строки для LISA
    public static function profession2Lisa($prof) {
        if (in_array($prof, insObjects::$nonWorkingProf)) return $prof; # ['безработный','домохозяйка','пенсионер','студент']
        if ($prof === InsObjects::$otherProfession[1]) $class = 1; # иная профессия
        else $class = self::getProfessionRiskClass($prof);
        if ($class<=0) $class = 1;
        return "$class класс риска";
    }
    # делаю ссылку для формы просмотра полиса
    public static function getLinkViewAgr($module, $plcid) {
        $ret = appEnv::getConfigValue('comp_url');
        if (substr($ret,-1) !== '/') $ret .= '/';
        $ret .= "?plg={$module}&action=viewagr&id={$plcid}";
        return $ret;
    }

    # по полям ЮЛ подписанта формирую блок для печати
    public static function getUlSignerBlock(&$data) {

        if ($data['insurer_type'] == 1) return FALSE; # только для ЮЛ!

        $sblock = '';
        if (!empty($data['ul_signer_name'])) {
            $sblock = $data['ul_signer_name'];
            # {upd/2023-07-24} в подпись ФИО страхователя тожк буду выводить ФИО начальника ЮЛ (А.Загайнова)
            $data['ul_signer_fio'] = $data['pholder_fio'] = MakeFIO($data['ul_signer_name']);
            if (!empty($data['ul_signer_duty'])) $sblock = $data['ul_signer_duty'] . ' '. $sblock;
            $data['ul_head_name'] = $sblock; # Должность ФИО- для опрос-листа FATCA (oplist-ul.xml)
            $data['ul_osnovanie'] = '';
            if (!empty($data['ul_signer_dovno']) && $data['ul_signer_dovno']!=-1 && $data['ul_signer_dovno']!=='-') {
                $sblock .= ", действующий(ая) на основании доверенности";
                if (!empty($data['ul_signer_dovno'])) {
                    $sblock .= ' № '. $data['ul_signer_dovno'];
                    $data['ul_osnovanie'] .= "доверенности № " . $data['ul_signer_dovno'];
                }
                if (!empty($data['ul_signer_dovdate'])) {
                    $sblock .= " от $data[ul_signer_dovdate]";
                    $data['ul_osnovanie'] .= ' от ' . $data['ul_signer_dovdate'];
                }
            }
            else {
                $sblock .= ", действующий(ая) на основании Устава";
                $data['ul_osnovanie'] = 'Устава';
            }
        }
        if ($sblock) $data['ul_signer_block'] = $sblock;
        return $sblock;
    }
    /*
    # создаю запись с URL карточки в СЭД (на случай, если переключат на новый сервер, чтобы эту карточку можно было открыть!
    public static function saveDocFlowUrl($module, $policyid, $cardid, $sedType=FALSE) {

        $finalUrl = SedExport::getDocFlowUrlForCard($cardid, $module, $sedType);
        $urlData = [
          'module' => $module,
          'policyid' => $policyid,
          'cardid' => $cardid,
          'url' => $finalUrl,
          'userid' => appEnv::$auth->userid,
        ];
        $result = appEnv::$db->insert(PM::T_DOCFLOWURL, $urlData);
        return $result;
    }

    public static function getDocFlowUrl($module, $id, $cardid=0) {
        $urlData = appEnv::$db->select(PM::T_DOCFLOWURL, [['module'=>$module, 'policyid'=>$id],'singlerow'=>1]);
        if (!empty($urlData['url'])) return $urlData['url'];
        if ($cardid) return SedExport::getDocFlowUrlForCard($cardid, $module);
        return '';
    }
    */

    public static function getIsoCurrency($cur) {
        $cur2 = mb_strtolower($cur,MAINCHARSET);
        if($cur === 'RUB' || mb_substr($cur2,0,3,MAINCHARSET) === 'руб') return 'RUR';
        if(mb_substr($cur2,0,4,MAINCHARSET) === 'долл') return 'USD';
        if(mb_substr($cur2,0,4,MAINCHARSET) === 'евро') return 'EUR';
        return $cur;
    }
    /**
    * @param mixed $riskid ИД риска
    * @param mixed $riskText текст для колонки "стр.Суммы" на форме просмотра договора
    * @since 1.58 {upd/2022-07-07}
    */
    public static function setViewSaText($riskid, $riskText) {
        self::$saTexts[$riskid] = $riskText;
    }
    /**
    * проверяет можно ли увеличить дату начала -двия на nn дней при заданной дате начала и рожд-я Застрахованного
    * @param mixed $startOld текущая дата начала д-вия
    * @param mixed $birthday дата рождения, или массив дат рождений или строк таблицы ФЛ (список застрахованных)
    * @param mixed $days на сколько дней хотим сдвинуть
    * @returns TRUE | FALSE
    * @since 1.61
    */
    public static function checkNewStartDate($startOld, $birthday, $days) {
        if(!is_numeric($days)) # передали желаемую дату YYYY-MM-DD
            $days = DiffDays($startOld, $days);
        # if($days <=0) return FALSE;
        $start = to_date($startOld);
        if(is_string($birthday)) $bdays = [$birthday];
        elseif(is_array($birthday)) $bdays = $birthday;
        else return FALSE;
        $arChk =[];
        foreach($bdays as $bd) {
            if(is_string($bd)) $arChk[] = substr(to_date($bd), 5);
            elseif(isset($bd['birth'])) $arChk[] = substr(to_date($bd['birth']), 5);
        }
        $shifter = ($days > 0) ? "+1 days" : "-1 days";
        for($nday = 1; $nday<=abs($days); $nday++) {
            $start = date('Y-m-d', strtotime("$start $shifter"));
            foreach($arChk as $birthMmDd) {
                if(substr($start,5) === $birthMmDd) return FALSE;
            }
        }
        return TRUE;
    }
    # AJAX запрос на сброс полиса в нач.состояние (агент/менеджер) {upd/2023-03-07} - коммент от ПП для агента, письмо агенту
    public static function resetPlc() {
        $module = isset(AppEnv::$_p['module']) ? AppEnv::$_p['module'] : FALSE;
        $id = isset(AppEnv::$_p['id']) ? AppEnv::$_p['id'] : FALSE;
        $reset_cmt = AppEnv::$_p['reset_cmt'] ?? ''; # комментарий для агента, если сброс делает ПП
        $bkend = AppEnv::getPluginBackend($module);
        if($id<=0 || !is_object($bkend)) exit('Wrong call');
        $myLevel = $bkend->getUserLevel();
        $access = $bkend->checkDocumentRights($id);
        $dta = self::getPolicyData($module,$id);
        if($access<1.5) appEnv::echoError('err-no-rights');
        if(!in_array($dta['stateid'], [0,PM::STATE_PROJECT, PM::STATE_IN_FORMING, PM::STATE_UNDERWRITING,
          PM::STATE_PAUSED,PM::STATE_DOP_CHECKING]) )
            exit('1'.ajaxResponse::showError("Договор не может быть сброшен в начальное состояние!"));

        if($dta['docflowstate']>0 && $myLevel < PM::LEVEL_IC_ADMIN)
            exit('1'.ajaxResponse::showError("Договор уже ушел на андеррайтинг, вернуть в начальное состояние невозможно!"));

        # exit('debug: reset OK');
        $upd = ['stateid' => PM::STATE_PROJECT, 'med_declar'=>'', 'bptype'=>'', 'bpstateid'=>0,'sescode'=>'','dt_sessign'=>'0' ];
        if($module !== PM::INVEST) $upd['recalcby'] = 0;
        $result = self::updatePolicy($module, $id, $upd);
        if($result) {
            $uwdata = AgmtData::getData($module, $id);
            AgmtData::saveData($module, $id, ['dt_client_letter'=>'']); # сброс отметки об отправке письма клиенту
            if($bkend->autoCleanPep) UniPep::cleanRequests($module,$id); # зачистил от старых записей ЭДО процесса
            if(in_array($uwdata['uw_reason2'], [PM::UW_REASON_DECLARATION])
              || ($bkend->uwCheckEvent === 'nextstage' && in_array($dta['reasonid'],[PM::UW_REASON_INSURED_EXIST,PM::UW_REASON_CHILD_EXIST]))
            ) # сброс "тяжести UW", если была установлена через выбор соотв.мед-декларации=НЕТ или по обнаруж-ю полисов на Застрх при переходе на след.этап
                AgmtData::saveData($module, $id, ['uw_reason2'=>0, 'uw_hard2'=>0]);
                self::updatePolicy($module, $id, ['reasonid'=>0]); # сбрасываю код UW в самом договоре
            # удаляю неактуальные сканы заявления, анкет...
            FileUtils::deleteFilesInAgreement($module,$id,PM::$clearDocsReset);

            $ret = '1' . $bkend->refresh_view($id, TRUE) . AjaxResponse::execute("policyModel.refreshScans()");
            $pref = $bkend->getLogPref();
            $logTxt = "Сброс договора в начальное состояние" . (($myLevel>=PM::LEVEL_IC_ADMIN) ? ' (ПП)':'');
            AppEnv::logEvent($pref."RESET",$logTxt , 0, $id);
            Acquiring::blockAllCards($module, $id); # блокировка ордеров на онлайн оплату, если есть!
            # очистка от инфы по авто-платежам
            if(class_exists('AutoPayments')) AutoPayments::cleanPolicy($module, $id, TRUE);

            if($myLevel>=PM::LEVEL_IC_ADMIN) {
                $mailTpl = AppEnv::getAppFolder('templates/letters/') . 'agent_resetplc.htm';
                $subst = [
                  '{policyno}' => $dta['policyno'],
                  '{view_url}' => self::getLinkViewAgr($module, $id),
                  '{reset_cmt}' => Sanitizer::safeString($reset_cmt),
                  '{comp_name}' => AppEnv::getConfigValue('comp_title'),
                ];
                $msgbody = @file_get_contents($mailTpl);
                $msgbody = strtr($msgbody, $subst);
                # writeDebugInfo("msgbody: ", $msgbody);
                $msgOpts = [
                    'to' => CmsUtils::getUserEmail($dta['userid']),
                    'subj' => 'ALFO: договор нуждается в исправлении данных',
                    'message' => $msgbody
                ];
                $sent = AppEnv::sendEmailMessage($msgOpts);
                if($sent) AppEnv::logEvent($pref."NOTIFY AGENT",'Агенту отправлено уведомление о сбросе', 0, $id);
            }
        }
        else {
            writeDebugInfo("update sql error ", AppEnv::$db->sql_error(), ' SQL: ', AppEnv::$db->getLastQuery());
            $ret = '1' . AjaxResponse::showError('err-data-saving');
        }
        exit($ret);
        # exit('1' . ajaxResponse::showMessage("resetPlc $module/$id, access: $access<pre>". print_r($dta,1).'</pre>'));
    }

    /**
    * Отправка email уведомления о необх. аннулировать карточку СЭД (после полного сброса полиса)
    *
    * @param mixed $plcdata передан масив типа _rawAgmtData
    * @since 1.62
    */
    public static function BanSedCardNotification($plcdata) {
        $module = (isset($plcdata['module'])) ? $plcdata['module'] : 'invprod';
        $to = AppEnv::getConfigValue($module.'_feedback_email');
        if(!$to) AgtNotifier::getSupportEmail($plcdata);
        $sedNo = $plcdata['export_pkt'];
        $subj = 'ALFO: Аннулируйте карточку в СЭД!';
        $msgbody = "В связи со сбросом в начальное состояние договора $plcdata[policyno]\n нужно аннулировать в СЭД карточку с номером $sedNo";

        if($to) {
            $msg = [
              'to' => $to,
              'subj' => $subj,
              'message' => $msgbody,
            ];
            $result = AppEnv::sendEmailMessage($msg);
        }
        else
            $result = AppEnv::sendSystemNotification($subj,$msgbody);

        return (($result) ? $to : FALSE);
    }
    # для вьюхи на все полисы - формирует ссылку для открытия просмотра полиса
    public static function linkToPolicyView($data) {
        global $ast_datarow;
        # writeDebugInfo("data: ", $ast_datarow);
        $module = $ast_datarow['module'];
        $id = $ast_datarow['id'];
        $pno = $ast_datarow['policyno'];
        $ret = "<a href='javasdcript:void()' onclick=\"window.open('./?plg=$module&action=viewagr&id=$id')\">$pno</a>";
        return $ret;
    }
    # для селекта выбора "страховых" модулей
    public static function selModuleList() {
        $arRet = [];
        foreach(AppEnv::$_plugins as $id => $plgObj) {
            if(method_exists($plgObj,'moduleType')) $mType = $plgObj->moduleType();
            else $mType = '';
            if($mType === PM::MODULE_INS) {
                $title = (method_exists($plgObj, 'getModuleName')) ? $plgObj->getModuleName() : $id; # $plgObj->getProgramName()
                $arRet[] = [ $id, $title ];
            }
        }
        return $arRet;
    }
    # проверяет, не истекла ли возможность оплатить полис в зав. от его даты начала
    public static function PayExpired($dtStart) {
        $ymd = to_date($dtStart);
        if(self::$payExpireAfter > 0)
            $dtLimit = date('Y-m-d', strtotime("$ymd +".self::$payExpireAfter . ' days'));
        else $dtLimit = $ymd;
        # return $dtLimit;
        return ($dtLimit < date('Y-m-d'));
    }
    # при тестах - сищу лог событий по полису (кроме превой записи
    public static function clearLog($module, $id) {
        if(!SuperAdminMode()) return 'No rights!';
        $bkend = AppEnv::getPluginBackend($module);
        if(!is_object($bkend)) return "wrong module $module";
        if(method_exists($bkend, 'getLogPref'))
            $pref = $bkend->getLogPref();
        else return "wrong module $module, no getLogPref function!";

        $firstid = AppEnv::$db->select(PM::T_EVENTLOG, ['fields'=>'evid', 'where'=>"itemid=$id AND evtype LIKE '$pref%'", 'orderby'=>'evid','singlerow'=>1]);
        if(empty($firstid['evid'])) return FALSE;
        AppEnv::$db->delete(PM::T_EVENTLOG, "itemid=$id AND evtype LIKE '$pref%' AND evid>$firstid[evid]");
        $ret = AppEnv::$db->affected_rows();
        return "deleted rows: $ret";
    }
    # универс. загрузка основных данных полиса
    public static function loadPolicyData($module, $id, $shortInfo = FALSE) {
        if($module === 'investprod') {
            $ret = AppEnv::$db->select('bn_policy', ['where'=>['id'=>$id],'singlerow'=>1]);
            if(empty($ret['metatype']) && !empty($ret['headbankid']) && empty($ret['metatype']))
                $ret['metatype'] = OrgUnits::getMetaType($ret['headbankid']);
        }
        else {
            $fields = ($shortInfo ? 'stmt_id,metatype,userid,deptid,headdeptid,stateid' : '');
            $ret = AppEnv::$db->select(PM::T_POLICIES, ['fields'=>$fields,
              'where'=>['module'=>$module,'stmt_id'=>$id],'singlerow'=>1]);
            if(!empty($ret['headdeptid'])) $ret['metatype'] = OrgUnits::getMetaType($ret['headdeptid']);
        }
        return $ret;
    }

    # {upd/2023-11-14} грузим ВСЕ спец-данные полиса, парсим в масоц.массив (перенос из policymodel.php)
    public static function loadPolicySpecData($id) {
        $ret = [];
        $f = AppEnv::$db->select(PM::T_SPECDATA, array('where'=>array('stmt_id'=>$id),'singlerow'=>1));
        if (self::$debug>1) {
            WriteDebugInfo('loadPolicySpecData: select result:', $f);
        }
        if(isset($f['calc_params'])) {
            $ret = array(
               'calc_params' => WebApp::unserializeData($f['calc_params']),
               'spec_params' => WebApp::unserializeData($f['spec_params']),
               'ins_params' => WebApp::unserializeData($f['ins_params']),
               'fin_plan'   => [],
               'spec_conditions' => (empty($f['spec_conditions']) ? '' : $f['spec_conditions']),
            );
            if (!empty($f['fin_plan'])) {
                $ret['fin_plan'] = @WebApp::unserializeData($f['fin_plan']);
                # file_put_contents('_finplan-hhome.log', print_r($ret['fin_plan'],1));
            }
        }
        return $ret;
    }
    # грузим только параметры в калькуляторе
    public static function loadCalcParams($id) {
        $dta = AppEnv::$db->select(PM::T_SPECDATA, ['where'=>['stmt_id'=>$id],'fields'=>'ins_params', 'singlerow'=>1]);
        if(isset($dta['ins_params'])) $ret = AppEnv::unserializeData($dta['ins_params']);
        else $ret = FALSE;
        return $ret;
    }
    # грузим только спец-параметры (на форму редактирования)
    public static function loadSpecParams($id) {
        $dta = AppEnv::$db->select(PM::T_SPECDATA, ['where'=>['stmt_id'=>$id],'fields'=>'spec_params', 'singlerow'=>1]);
        if(isset($dta['spec_params'])) $ret = AppEnv::unserializeData($dta['spec_params']);
        else $ret = FALSE;
        return $ret;
    }
    /**
    *  заношу данные в договор из массива
    * @param mixed $module ИД плагина
    * @param mixed $policyid ИД договора
    * @param mixed $arData массив данных (updated не надо, сделает сам)
    */
    public static function updatePolicy($module, $policyid, $arData) {

        if(!isset($arData['updated']))  $arData['updated'] = '{now}';
        if(isset($arData['bpstateid']) || isset($arData['substate'])) $arData['bpstate_date'] = '{now}';
        if(isset($arData['stateid'])) $arData['statedate'] = '{now}';
        if(!empty($arData['sescode']) && !isset($arData['dt_sessign'])) $arData['dt_sessign'] = '{now}';

        if($module === 'investprod')
            $result = AppEnv::$db->update(investprod::TABLE_POLICIES, $arData, ['id' => $policyid]);
        else
            $result = AppEnv::$db->update(PM::T_POLICIES, $arData, ['stmt_id' => $policyid]);
        if(self::$debug) writeDebugInfo("updatePolicy SQL: ", AppEnv::$db->getLastQuery());
        return $result;
    }
    /**
    * Добавляет новую запись с полисом
    * @param mixed $arData массив данных по записи
    * @return ID созданной записи
    */
    public static function addPolicy($module, $arData) {
        if($module === 'investprod')
            $recid = AppEnv::$db->insert(investprod::TABLE_POLICIES ,$arData);
        else {
            if(empty($arData['metatype'])) {
                $headid = $arData['headdeptid'] ?? OrgUnits::getHeadOrgUnit();
                $arData['metatype'] = $metaType = OrgUnits::getMetaType($headid);
                if(self::$debug) writeDebugInfo("added metatype for $headid: ", $metaType);
            }
            $recid = AppEnv::$db->insert(PM::T_POLICIES ,$arData);
        }
        if(!$recid) {
            writeDebugInfo("Error adding policy record, sql: ", AppEnv::$db->getLastQuery(), ' err:', AppEnv::$db->sql_error() );
            AppAlerts::raiseAlert("add_polic_{$module}","Ошибка при попытке добавить запись о полисе, ".AppEnv::$db->sql_error() );
            throw new ErrorException("Ошибка прии заведении записи полиса в БД!");
        }
        else Appalerts::resetAlert("add_polic_{$module}", 'Ошибка создания полиса устранена');
        # $recid = AppEnv::$db->insert_id();
        return $recid;
    }
    # вернет дату ближайшего дня рождения по дате рождения
    # @since 1.66 2022-11-11
    # $shiftMonth - если нужно учесть сдвиг на 1(N) месяц до реальной даты рождения (Золотая пора с 1мес накопления)
    public static function nextBirthDay($dtBirth, $fromDate=FALSE, $shiftMonth=0) {
        # writeDebugInfo("nextBirthDay($dtBirth, $fromDate, $shiftMonth)");
        if(!$fromDate) $fromDate = date('Y-m-d');
        else $fromDate = to_date($fromDate);
        $birthYmd = to_date($dtBirth);
        if($shiftMonth > 0) $birthYmd = AddToDate($birthYmd,0, -$shiftMonth,0);
        $addYears = (int)$fromDate - intval($birthYmd);
        $ret = AddToDate($birthYmd, $addYears);
        if($ret < $fromDate)    $ret = AddToDate($birthYmd, $addYears+1);
        return $ret;
    }
    public static function getCompanyPhone($module) {
        $ret = appEnv::getConfigValue($module.'_feedback_phones');
        if(!$ret) $ret = AppEnv::getConfigValue('comp_phones');
        return $ret;
    }
    public static function getCompanyEmail($module) {
        $ret = appEnv::getConfigValue($module.'_feedback_email');
        if(!$ret) $ret = AppEnv::getConfigValue('comp_email');
        return $ret;
    }

    # вернет TRUE если клиенту отправлена ссылка на онлайн оплату и она еще не сгорела
    public static function isWaitingEqPayment($module, $plcid, $plcdata = FALSE) {
        # для разборок, почему может не придти массив с полисом
        if(!is_array($plcdata)) writeDebugInfo("isWaitingEqPayment($module, ) no plcdata: plcid=",$plcid,
         " trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));

        if(!isset($plcdata['stateid']) && is_numeric($plcid)) {
            $plcdata = self::loadPolicyData($module, $plcid);
        }
        $stateid = $plcdata['stateid'] ?? FALSE;
        if(is_numeric($stateid) && $stateid >= PM::STATE_PAYED) return FALSE;
        $data = Acquiring::findExistingCard($module, $plcid, TRUE);

        $now = date('Y-m-d H:i:s');
        if(isset($data[0])) $row = $data[0];
        else $row = $data;
        $is_payment = $row['is_payment'] ?? '';
        $timeto = $row['timeto'] ?? '';
        if( $is_payment == 1 ) return FALSE; # полис уже оплачен
        if(self::isDateValue($timeto)) {
            if(empty($row['is_payment']) && $now < $timeto) return TRUE; # не оплачена и еще действует
        }
        return FALSE;
    }

    # AJAX запрос "выпустить полис" (банковский/агентский канал)
    public static function releasePolicy($mdl='',$plcid='', $dstart='') {
        if (AppEnv::getConfigValue('z_debug_plc_release')) # вкл. отладку через настройки
            self::$debug = 1;
        $module = AppEnv::$_p['module'] ?? $mdl ?? '';
        $id = AppEnv::$_p['id'] ?? $plcid ?? 0;
        # {upd/20223-12-20} теперь в рисковых приходит введенная дата начала д-вия
        $userDtStart = $dtStart = appEnv::$_p['dt_start'] ?? $dstart ?? '';

        $dtMandatory = isset(appEnv::$_p['dt_start']);

        if(!isset(Appenv::$_plugins[$module]) || $id<=0) exit('Wrong Call');
        $bkend = AppEnv::getPluginBackend($module);
        $data = $bkend->_rawAgmtData = self::loadPolicyData($module, $id);
        $metatype = $data['metatype'] ?? '';
        if(self::$debug) writeDebugInfo("data: ", $data);
        $dopData = \AgmtData::getData($module, $id);
        if(is_array($dopData)) $data = array_merge($data, $dopData);
        $today = date('Y-m-d');

        # if($data['stateid'] == PM::STATE_PAYED && $data['bpstateid'] == 0 && $data['date_release_max']<$today)
        if(empty($dtStart) && self::isDateValue($data['datepay'])) {
            #агент, полис оплатили, и дата начала зафиксировалась по дате оплаты - от нее и прыгать!)
            $dtStart = $data['datefrom'];
            # writeDebugInfo("взята дата datefrom из оплаченного полиса $dtStart");
        }

        if(self::isDateValue($dtStart)) $dtStart = to_date($dtStart);

        # exit('1' . ajaxResponse::showMessage("$dtStart, pars: <pre>".print_r(appEnv::$_p,1).'</pre>'));

        $access = $bkend->checkDocumentRights($id);
        if($access < 1) AppEnv::echoError('err-no-rights');
        $userLevel = $bkend->getUserLevel();
        if(!isset($data['policyno'])) AppEnv::echoError('err_agreement_not_found');
        if(method_exists($bkend, 'beforeReleasePolicy')) {
            # {upd/2023-05-16} в модуле может быть своя проверка перед выпуском полиса, н-р, введенной даты начала (поч.возр)
            $bkend->beforeReleasePolicy($dtStart);
        }
        $isExpired = PlcUtils::isPolicyExpired($data);
        if($isExpired) exit('1' . AjaxResponse::showError('Максимальная дата выпуска полиса просрочена, выпуск невозможен!'));


        # exit('1' . AjaxResponse::showMessage("$isExpired=[$isExpired]Release, Data: <pre>" . print_r($data,1) . '</pre>'));

        $daysToStart = method_exists($bkend, 'getDaysToStart') ? $bkend->getDaysToStart($data) : 0;
        if(empty($dtStart)) $dtStart = PlcUtils::computeStartDate($daysToStart,'',1);

        # exit('1' . AjaxResponse::showMessage(" daysToStart=$daysToStart dtstart: $dtStart"));
        # exit('1' . AjaxResponse::showMessage("dtStart:$dtStart, daysToStart=[$daysToStart], agmtdata:<pre>" . print_r($data,1) . '</pre>'));

        if($dtStart) { # $dtStart
            if(!self::isDateValue($data['date_release_max'])) {
                if(!empty($data['previous_id'])) {
                    $days = \AppEnv::getConfigValue('prolong_days_afterend',0) - 1;
                    $data['date_release_max'] = date('Y-m-d', strtotime($data['datefrom'] . "+$days days"));
                }
                else {
                    if(superAdminMode()) $errMsg = 'Нулевая date_release_max: <pre>' . print_r($data,1) . '</pre>';
                    else $errMsg = 'Нужно обновить данные, чтобы сформировать макс.дату выпуска (сейчас она пустая)!';
                    exit('1' . \AjaxResponse::showMessage($errMsg));
                }
                # exit('1' . \AjaxResponse::showError("err_release_recalc_needed"));
            }
            # if(!self::isDateValue($data['date_release']))
            #     exit('1' . ajaxResponse::showError("err_policy_not_released")); # TODO: проверить логику: полис еще не должен быть выпущен, зачем это здесь?
            # exit('1' . AjaxResponse::showMessage("start: $dtStart, getdaystostart: $daysToStart dateRelease: $dateRelease"));
            $relDate = $data['date_release'];
            if(!PlcUtils::isDateValue($relDate)) {
                # if($metatype == \OrgUnits::MT_AGENT)
                $relDate = max($data['datepay'], $today); # при оплате
                # exit('1' . AjaxResponse::showMessage("fixed date_release from today=$today: <pre>" . print_r($relDate,1) . '</pre>'));
            }

            $maxDtStart = AddToDate($relDate, 0,0,self::DAYS_AFTER_RELEASE); # не более 30 дней после даты выпуска!
            # exit('1' . AjaxResponse::showMessage("maxDtStart=$maxDtStart")); # debug PITSTOP
            if(!$maxDtStart) $maxDtStart = $data['datefrom_max']; # ?? date('Y-m-d');
            $minStart = PlcUtils::computeStartDate($daysToStart, $relDate, 1);
            # exit('1' . AjaxResponse::showMessage("min start Date: $minStart maxDtStart=$maxDtStart"));
            /*
            if( $data['metatype'] == \OrgUnits::MT_AGENT) # агент может назначить дату начала от +1 дня, а не +NN дней
                $minStart = date('Y-m-d', strtotime($relDate . " +$daysToStart days"));
            */
            if($dtStart < $data['date_release']) {
                exit('1' . ajaxResponse::showError("err_startdate_before_release"));
            }

            if(!empty($daysToStart) && $dtStart < $minStart) {
                exit('1' . ajaxResponse::showError("err_startdate_mustbe_after_release",'',to_char($minStart)));
            }
            # exit('1' . AjaxResponse::showMessage('date_release: '.$data['date_release'] . " max date start $maxDtStart "));
            if($dtStart > $maxDtStart) {
                $errMsg = "$dtStart - $maxDtStart : " . sprintf(AppEnv::getLocalized('err_startdate_after_release'), self::DAYS_AFTER_RELEASE);
                exit('1' . ajaxResponse::showError($errMsg));
            }
            $maxDt = max($data['datefrom_max'], $data['date_release_max']);
            if($dtStart > $maxDt) {
                # if(superAdminMode()) exit('1' . AjaxResponse::showMessage('err_startdate_too_late: <pre>' . print_r($data,1) . '</pre>'));
                exit('1' . ajaxResponse::showError("err_startdate_too_late"));
            }
        }

        # exit('1' . ajaxResponse::showMessage("data <pre>".print_r($data,1).'</pre>'));
        # writeDebugInfo("data : ", $data);

        $plcUser = $data['userid'] ?? $data['createdby'];
        $myAgmt = ($plcUser == AppEnv::getUserId());
        # if( $userLevel > PM::LEVEL_CENTROFFICE && !$myAgmt) AppEnv::echoError('err_not_for_you');

        # {upd/2025-08-28} фикс: по ошибкеподписывались и банковские полисы (а у них - после выпкуск+оплата)!
        $isAgentPolicy = ($data['metatype'] == OrgUnits::MT_AGENT);

        if($data['metatype'] == OrgUnits::MT_BANK) {
            $canRelease = ( ($data['stateid'] == PM::STATE_IN_FORMING && in_array($data['bpstateid'],
              [0, PM::BPSTATE_EDO_OK, PM::BPSTATE_EDO_S3_OK])) || $data['stateid'] == PM::STATE_UWAGREED);
            if(self::$debug) writeDebugInfo("Банк: metatype=$data[metatype], canRelease=[$canRelease], bpstateid: $data[bpstateid], stateid=$data[stateid]");
        }
        else { # в агентском канале
            $canRelease = ( ($data['stateid'] == PM::STATE_PAYED || self::isDateValue($data['datepay']))
              && in_array($data['bpstateid'], [0, PM::BPSTATE_EDO_OK, PM::BPSTATE_EDO_S3_OK]));
            # writeDebugInfo("Агент: metatype=$data[metatype], canRelease=[$canRelease], bpstateid: $data[bpstateid], stateid=$data[stateid] ", $data);
        }
        # exit("1" . AjaxResponse::showMessage("goodstate: [$canRelease], metaType:$data[metatype]"));

        if(!$canRelease) {
            AppEnv::echoError('err_wrong_state_for_action');
            exit;
        }
        if(empty($data['date_release_max']) || !self::isDateValue($data['date_release_max'])) {
            exit('1' . AjaxResponse::showError('err_release_recalc_needed'));
        }

        $plcUpd = [' bpstateid' => PM::BPSTATE_RELEASED ];
        $agmtRel = $today;
        # writeDebugInfo("release, data: ", $data);
        if(self::isDateValue($data['datepay'])) {
            $today = min($today, $data['datepay']);
            # writeDebugInfo("$today - дата оплаты берется для сверки с макс.датой выпуска");
        }
        $now = date('Y-m-d');
        if($today > $data['date_release_max']) {
            # опоздали с выпуском, надо делать откат-перерасчет, но если это поздняя пролонгация, пропускаем, сдвигая даты выпуска назад
            if(empty($data['previous_id']) )
                exit('1' . AjaxResponse::showError('err_release_policy_expired'));
        }

        if(!empty($data['previous_id']) && $data['datefrom']<=$now) {
            # {upd/2023-04-10} - пролонгация, формальная даты выпуска не позднее 1 дня ДО начала
            $agmtRel = date('Y-m-d', strtotime($data['datefrom']. " -1 days"));
            # writeDebugInfo("поздняя пролонгация, дата выпуска: $agmtRel from datefrom:", $data['datefrom']);
        }

        if(method_exists($bkend, 'checkMandatoryFiles'))
            $bkend->checkMandatoryFiles(Events::RELEASE_POLICY);
        else {
            BusinessProc::checkMandatoryFiles($bkend,Events::RELEASE_POLICY);
        }

        $newDateFrom = FALSE;
        if($daysToStart==0) {
            $newDateFrom = $agmtRel;
        }
        elseif($daysToStart> 0) {
            $newDateFrom = date('Y-m-d', strtotime("$agmtRel + $daysToStart days"));
        }

        # exit('1' . ajaxResponse::showMessage("TODO releasePolicy $module $id $usrLevel")); # test call
        $updResult = self::updatePolicy($module, $id, $plcUpd);

        if($updResult) {
            # {upd/2023-04-10} - дату выпуска заношу только если еще не занесена
            if(!self::isDateValue($data['date_release'])) {
                $agdUpd = [ 'date_release' => $agmtRel ];
                # если еще не оплачен, меняю макс дату оплаты (банк-канал)
                /*
                if(!intval($data['datepay']) && method_exists($bkend, 'getMaxDatePay')) {
                    $maxDtPay = $bkend->getMaxDatePay($data,$agmtRel);
                    writeDebugInfo("макс. дата оплаты: $maxDtPay");
                    if(PlcUtils::isDateValue($maxDtPay))
                        $agdUpd['max_date_pay'] = $maxDtPay;
                }
                */
                $agResult = AgmtData::saveData($module, $id, $agdUpd);
            }
            # else writeDebugInfo("release date уже стоит, нек меняю! ", $data['date_release']);

            $pref = $bkend->getLogPref();
            # {upd/2025-11-18} заношу в лог введенную агентом дату начала
            $postText = ($userDtStart) ? (", дата начала: ".to_char($userDtStart)) : '';
            AppEnv::logEvent($pref."RELEASE POLICY", "Произведен выпуск полиса{$postText}", 0, $id);
            $notProlong = empty($data['previous_id']);

            if($notProlong) {
                if($dtStart) {
                    # делаю сдвиг дат начала и окончания
                    $shifted = BusinessProc::shiftStartDate($data, $dtStart);
                    if(self::$debug) writeDebugInfo("Agents:shift start date result: ", $shifted);
                }
                elseif($data['datefrom'] != $newDateFrom) { # в банковском канале, сдвиг даты начала при выпуске еще неоплаченного полиса
                    if(self::$debug) writeDebugInfo("буду делать shiftStartDate к $newDateFrom");
                    $shifted = BusinessProc::shiftStartDate($data, $newDateFrom);
                    if(self::$debug) writeDebugInfo("Bank:shift start date result: ", $shifted);
                }
            }
            else {
                if(self::$debug) writeDebugInfo("дату начала не двигаю, т.к. это ПРОЛОНГАЦИЯ");
            }

            # У агентов сначала оплата, потом выпуск, при выпуске генерю и сохраняю ЭЦП-полис
            # if($metatype != OrgUnits::MT_BANK && $bkend->isEdoPolicy($data)) { # у банков не генерился плоис!
            if($bkend->isEdoPolicy($data) && $isAgentPolicy) {
                $saveDig = UniPep::createSignedPolicy($module, $id);
                if(self::$debug) writeDebugInfo("save DIG-signed policy after release :", $saveDig);
            }

            # прошальное сообщение - сколько дней на оплату у клиента (до какой даты)
            if(self::isDateValue($data['datepay'])) { # полис уже был оплачен (агентский канал)
                $finalMsg = AppEnv::getLocalized('msg_policy_released');
            }
            else {
                if(method_exists($bkend, 'getDaysFromReleaseToPay'))
                    $maxDaysForPay = $bkend->getDaysFromReleaseToPay();
                else $maxDaysForPay = min(BusinessProc::DAYS_FOR_PAY, $daysToStart);
                if(empty($maxDaysForPay))
                    exit('1' . AjaxResponse::showError('Ошибка в настройках или алгоритмах - получена неверная настройка дней до начала д-вия'));
                $maxPayDate = date('Y-m-d', strtotime("$agmtRel +$maxDaysForPay days"));
                if($bkend->payBeforeDatefrom !== NULL) {
                    $maxDate2 = AddToDate($data['datefrom'],0,0, -$bkend->payBeforeDatefrom);
                    # writeDebugInfo("old maxPayDate: $maxPayDate, New max datefrom $data[datefrom] - payBeforeDatefrom($bkend->payBeforeDatefrom): ", $maxDate2);
                    $maxPayDate = min($maxPayDate,$maxDate2);
                    # writeDebugInfo("new max Pay Date: $maxPayDate");
                }
                if(self::$debug)
                    writeDebugInfo("daysToStart: [$daysToStart] Дни от даты выпуска до оплаты : $maxDaysForPay, max pay date from reldate[$agmtRel]: $maxPayDate");

                $maxPayDateDmy = to_char($maxPayDate);
                if($data['rassrochka'] > 0) $msgid = 'msg_policy_released_pay';
                else $msgid = 'msg_policy_released_payonce';
                $finalMsg = sprintf(AppEnv::getLocalized($msgid), $maxPayDateDmy);
            }

            $sendRule = AppEnv::getConfigValue('clent_send_rules');
            if($sendRule === Events::RELEASE_POLICY) {
                # {upd/2023-10-28} отправка клиенту письма с правилами (вложенным и/или ссылкой)
                $rulesUrl = $bkend->getProgramRulesUrl($data);
                $rulesId = $bkend->getProgramRulesFileId($data);
                if(self::$debug) writeDebugInfo("release-KID: File Id=$rulesId, Url = $rulesUrl");
                if( !empty($rulesId) || !empty($rulesUrl)) { # !empty($rulesUrl) ||
                    $sentRules = ClientUtils::sendInsRulesToClient($module,$data,$rulesUrl,$rulesId);
                    if(self::$debug) writeDebugInfo("результат отправки письма с правилами (id=$rulesId/url=$rulesUrl): [$sentRules]");
                }
                else {
                    if(self::$debug) writeDebugInfo("не настроены rulesId,rulesUrl, нечего отправлять клиенту в кач-ве файла правил!");
                }
            }
            $refreshCmd = $bkend->refresh_view($id, TRUE);
            $ret = '1' . $refreshCmd . AjaxResponse::showMessage($finalMsg);
        }
        else $ret ="1" . AjaxResponse::showError('err-data-saving');
        exit($ret);
    }

    # вернет Unix timestamp если строка содержит дату YYYY-MM-DD [HH:II:SS] или DD.MM.YYYY [HH:II:DD]
    # иначе вернет FALSE
    public static function isDateValue($val) {
        if(intval($val)>0) {
            if(intval($val)<32) $val = to_date($val,2);
            return strtotime($val);
            # if($dtVal>0) return TRUE;
        }
        return FALSE;
    }

    /**
    * {upd/2022-11-02} отработка AJAX команды "Проверен"
    * Отправить на учет: проверка наличия сканов, bpstate => PM::BPSTATE_ACTIVE (+карточка СЭД)
    */
    public static function setStateActive($module='', $plcid=0) {
        # self::$debug = 1;
        $notifyCliAnketa = FALSE;

        if (self::$debug) WriteDebugInfo('setActive params:', AppEnv::$_p);
        # exit(print_r(AppEnv::$_p,1));
        if(!$module) $module = Appenv::$_p['module'] ?? Appenv::$_p['plg'] ?? '';
        if(!$plcid) $plcid = AppEnv::$_p['id'] ?? 0;
        if($plcid<=0 || empty($module)) exit("Bad call(id, module)");

        $bkend = Appenv::getPluginBackend($module);

        $data = $bkend->_rawAgmtData = self::getPolicyData($module, $plcid);
        $metatype = $bkend->_rawAgmtData['metatype'];

        if(self::$debug) writeDebugInfo("raw policy data: ", $data);
        $myLevel = $bkend->getUserLevel();
        # {upd/2025-10-09} если полис на доработке, проверяем в каких статусах
        if(!empty($data['substate']) && $bkend->isEdoPolicy($data)) {
            if(in_array($data['substate'], [PM::SUBSTATE_EDITED, PM::SUBSTATE_COMPL_CHECK_OK])) {
                exit('1' . AjaxResponse::showError('Доработка: после изменений данных надо отправить Клиенту на согласование!'));
            }
            if(in_array($data['substate'], [PM::SUBSTATE_EDO2_STARTED])) {
                exit('1' . AjaxResponse::showError('Доработка: дождитесь результатов согласования изменений клиентом!'));
            }
            if(in_array($data['substate'], [PM::SUBSTATE_COMPL_CHECKING])) {
                exit('1' . AjaxResponse::showError('Доработка: После изменений ожидаем подтверждения от Комплаенс!'));
            }
            if(in_array($data['substate'], [PM::SUBSTATE_EDO2_FAIL])) {
                exit('1' . AjaxResponse::showError('Клиент не согласился с изменениями, повторите корректировки или расторгайте Договор!'));
            }
            /*
            if(in_array($data['substate'], [PM::SUBSTATE_COMPL_CHECK_OK])) {
                exit('1' . AjaxResponse::showError('Доработка: После изменений данных нужно согласовать их с Клиентом!'));
            }
            */
            if($data['substate'] != PM::SUBSTATE_EDO2_OK)
                exit('1' . AjaxResponse::showError('Сначала завершите режим доработки! ('.$data['substate'] . ')'));
        }

        if( !AppEnv::isLightProcess() ) {
            if( $myLevel < PM::LEVEL_IC_ADMIN) { appEnv::echoError('err-no-rights'); exit; }
            if($data['stateid']!= PM::STATE_FORMED || $data['bpstateid'] != PM::BPSTATE_ACCOUNTED) {
                if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'err_wrong_state_for_action'];
                AppEnv::echoError('err_wrong_state_for_action');
                exit;
            }
            if (method_exists($bkend, 'checkMandatoryFiles')) {
                # обнаружив нехватку документов, ф-ция сама отправит AJAX ответ о недостающих
                $result = $bkend->checkMandatoryFiles(Events::SUBMIT_FORREG);
            }
            else
                $result = BusinessProc::checkMandatoryFiles($bkend, Events::SUBMIT_FORREG);
        }
        $cardId = 'new';

        $warning = '';

        $newFiles = [];
        # $filesAgmt = FileUtils::getFilesInPolicy($module, $plcid, 'exported=0', TRUE);
        # $newFiles = array_merge($newFiles, $filesAgmt);
        # if(self::$debug) writeDebugInfo("Файлы из карточки ", $newFiles);

        if($bkend->b_epolicy_wf) { # выгружаю цифр.вариант ПФ (полиса)
            self::appendEpolicyForDocflow($bkend, $newFiles);
        }
        # {upd/2023-12-05} генерацию доп.файлов делаю только при финальной выгрузке в СЭД
        if (method_exists($bkend, 'addFilesToExport')) {
            # если в модуле есть метод генерации спец-файлов для выгрузки, вызываю его и заношу результат в массив файлов
            # Сделано для "Гарантия Плюс" - генерить файл с графиком платежей по ДМС (xlsx)
            $addFiles = $bkend->addFilesToExport($plcid);
            if(is_array($addFiles) && count($addFiles)) {
                $newFiles = array_merge($newFiles, $addFiles);
                if (self::$debug) WriteDebugInfo("setActive/$module::addFilesToExport($plcid) added files:", $addFiles);
            }
        }

        # {upd/2023-08-14} Если есть инвест-анкета, выгружаю файлом ее в СЭД
        if( !empty($data['anketaid']) && !AppEnv::isLightProcess() ) {
            if(self::$debug) writeDebugInfo("формирую файл с инвест-анкетой ", $data['anketaid']);
            $tmpAnketaName = \InvestAnketa::getFinalFile($data['anketaid']);
            if($tmpAnketaName) {
                $newFiles[] = [ 'filename'=> 'Инвест-анкета-клиента.pdf', 'fullpath' => $tmpAnketaName ];
            }
            if(self::$debug) writeDebugInfo("файл инв-анкеты: ", $tmpAnketaName);
        }

        # {upd/2023-11-24} - новая фишка - отгружать в СЭД последнюю страницу анкеты клиента (длz руч.заполнения сидами ПП/ОБ)
        if( !AppEnv::isLightProcess() ) { # $bkend->_rawAgmtData['insurer_type'] == 1
            # пока раздельная анкета клиента только у ФЛ
            $tmpAnketaName = self::createAnketaInternal($bkend);
            if($tmpAnketaName) {
                $newFiles[] = [ 'filename'=> 'Анкета-клиента-уровень-риска.pdf', 'fullpath' => $tmpAnketaName ];
                $notifyCliAnketa = TRUE; # не забыть послать уведомление в ПП (если это банки)
            }
        }
        # exit('1' . AjaxResponse::showMessage('agmtdata: <pre>' . print_r($bkend->_rawAgmtData,1) . '</pre>')); # debug PIT-STOP

        if ($data['docflowstate']>0 && $data['export_pkt']>0) {
            # карточка была создана на этапе классического UW, доливаю в нее файл (возможно, переключаю "решение")
            $cardId = $data['export_pkt'];
            $filesAgmt = FileUtils::getFilesInPolicy($module, $plcid, 'exported=0', TRUE);
            if(is_array($filesAgmt))
                $newFiles = array_merge($newFiles, $filesAgmt);
            # {upd/2023-04-24} - гружу в СЭД чистовую эл.версию полиса (если не было ЭДО с ЭЦП подписанием!
            $sedOper = 'updateDocFlowCard';
            if(self::$debug) writeDebugInfo("setcheckDone: карточка уже есть, обновляем статус+файлы");
            if (!$bkend->export_xml_inuw && $bkend->enable_export) {
                # {upd/2021-01-29} в СЭД на UW не был загружен XML для Лизы, и сейчас надо выгрузить финальный!
                if(self::$debug) writeDebugInfo("добавляю в выгрузку XML (ранее не выгруженный по UW)");

                $exportBkend = appEnv::getPluginBackend('plcexport');
                # exit('TODO: generate XML!');
                $xmlFilename = $exportBkend -> onePolicyPacket($module, $plcid, 'file');
                if ($xmlFilename && is_file($xmlFilename))
                    $newFiles[] = [ 'fullpath' => $xmlFilename, 'filename'=> basename($xmlFilename) ];

            }
            # else exit('1' . AjaxResponse::showMessage('Data no anketaid: : <pre>' . print_r($data,1) . '</pre>'));
            # $data = [ 'module' => 'lifeag' ]; # TODO: внести маршрут Ввод в БД ?
            # if (is_array($newFiles) && count($newFiles)) {
                if (self::$debug) writeDebugInfo(__FUNCTION__,":доливаю в UW карточку оставшиеся файлы + [Согласовать След. этап] ", $newFiles);
                # добавляю строку об онлайн оплате!
                if (floatval($data['eqpayed']) > 0) {
                    $updtComment = appEnv::getPluginBackend('sedexport')->appendComment($cardId, 'ОнЛайн оплата');
                    if (self::$debug) writeDebugInfo("ОнЛайн оплата - добавление комментария в карточке $cardId: ", $updtComment);
                    if (!isset($updtComment['result']) || $updtComment['result']!='OK')
                        appEnv::logevent($bkend->log_pref.'SED COMMENT FAIL', 'Не удалось занести в СЭД отметку об онлайн оплате', FALSE, $plcid);
                }

                if(!empty($data['uw_acceptedby'])) {
                    # пробую запомнить согласовавшего в своем поле СЭД
                    $uwinfo = CmsUtils::getUserInfo($data['uw_acceptedby']);
                    if(empty($uwinfo['winuserid'])) {
                        # если СЭД-ИД (winuserid) не заполнен, заношу ФИО в Примечания
                        $uwFullName = ($uwinfo['lastname'] ?? '') . ' '.($uwinfo['firstname'] ?? '') . ' '.($uwinfo['secondname'] ?? '');

                        if($uwFullName != '  ') {
                            # $sedFields['Согласующий андеррайтер'] = $sedUserWinId;
                            $sedResult2 = self::addDocFlowComment($cardId, "Согласовал: $uwFullName");
                            if(self::$debug) writeDebugInfo("запоминаю в СЭД согласующего, $uwFullName");
                        }
                    }
                }

                # {upd/2021-02-20} в UW карточке СЭД были не заполены даты начала-оконч, и поле "согласован клиентом" - теперь пора их занести!
                $sedFields = [
                  'Предполагаемая дата начала действия' => to_char($data['datefrom']),
                  'Дата окончания договора' => to_char($data['datetill']),
                  'Текст согласован клиентом' => 'True',
                ];
                $sedResult = self::updateDocFlowCard($cardId, $sedFields, $data, $newFiles);
                # $sedResult = self::updateDocFlowCard($cardId, $sedFields, $data, $newFiles, $bkend->log_pref, 'Согласовать След. этап');
                if (!empty($sedResult['message'])) $warning .= ($warning? '<br>':'') . $sedResult['message'];
                if (self::$debug || !empty($sedResult['message']) ) writeDebugInfo("PlcUtils::updateDocFlowCard Result : ", $sedResult);
                if(isset($sedResult['result']) && $sedResult['result'] === 'OK') {
                    foreach($newFiles as $id => $item) {
                        if(!empty($item['exported'])) $logTxt = sprintf('В карточку СЭД загружен файл %s',$item['filename']);
                        else $warning .= ($warning? '<br>':'') . sprintf('Ошибка загрузки файла %s в карточку СЭД', $item['filename']);
                    }
                }
                if( $data['docflowstate']>1 ) {
                    # карточка была создана под UW, надо перевести на Ввод в БД!
                    $curStage = self::DocflowGetStage($module, $bkend, $data['export_pkt']);
                    if(self::$debug) writeDebugInfo("тек.статус карточки СЭД перед Вводом в БД ", $curStage);
                    # {upd/2023-03-30} перевожу этап ТОЛЬКО если карточка водном из указанных этапов
                    if(in_array($curStage, self::$SedGoodStages)) {
                        $stageResult = self::DocflowSetStage($module, $bkend, [PM::DOCFLOW_REPEAT_UW, PM::DOCFLOW_TONEXT]);
                        if(self::$debug) writeDebugInfo("sed stage result: ", $stageResult);
                        if(!isset($stageResult['result']) || $stageResult['result'] != 'OK') {
                            $badStage = $result['stage'] ?? PM::DOCFLOW_TONEXT;
                            $warning .= ($warning? '<br>':'') . "Внимание! при переводе карточки СЭД в статус [Ввод в БД] произошла ошибка:<br>".$result['message'];
                        }
                    }
                    else {
                        if($curStage === PM::DOCFLOW_STAGE_FINAL)
                            $warning = ($warning? '<br>' : '') . "Перевод карточки СЭД на Ввод в БД не нужен, она уже в этом статусе!";
                        else {
                            $warning = ($warning? '<br>' : '') . "Перевод карточки СЭД на Ввод в БД невозможен, тек.этап: $curStage !";
                            AppEnv::logEvent($bkend->log_pref.'SED NO STAGE',"СЭД: Перевод на ввод в БД невозможен: $curStage",0,$plcid);
                        }
                    }
                }
            # }
            # else $sedResult = ['result' => 'OK']; # ничего доливать не надо, все уже там
        }
        else {
            if (self::$debug) WriteDebugInfo("Итоговый список файлов перед отправкой в СЭД ",$newFiles);

            # Карточки еще не было, создаю новую (без андеррайтинга)
            if(self::$debug) writeDebugInfo(__FUNCTION__, ":создаю новую карточку СЭД");
            $sedState = 0; # 0 - нормальный (без андеррайтинга) 1,2 - на UW!
            $sedOper = 'createDocFlowCard';
            $sedResult = self::policyToDocflow($module, $bkend, $bkend->log_pref, $sedState, TRUE, TRUE, $newFiles);
            $cardId = $sedResult['cardId'] ?? 0;
            if (self::$debug) writeDebugInfo("PlcUtils::policyToDocflow() result: ", $sedResult);
        }
        if (self::$debug) writeDebugInfo("sed result: ", $sedResult);
        $success = (!empty($sedResult['result']) && $sedResult['result'] === 'OK');

        # в карточку ничего не попало, отмменяем письмо в ОБ про ДСП лист анкеты клиента
        if(!$success) $notifyCliAnketa = FALSE;

        if (self::$debug) writeDebugInfo("sedOper: $sedOper, success: ", $success);
        if ($success || $sedOper=== 'updateDocFlowCard') {
            $upd  = [ 'bpstateid'=> PM::BPSTATE_ACTIVE, 'substate'=>0 ]; # финальное состояние полиса!
            $updResult = self::updatePolicy($module, $plcid, $upd);
            if(self::$debug) writeDebugInfo("update policy result=[$updResult], SQL: ", appenv::$db->getLastQuery() );
            if($updResult) {
                $logTxt = 'Договор переведен в статус Оформлен/Активный';
                appEnv::logEvent($bkend->getLogPref().'SET STATE', $logTxt, FALSE, $plcid);
            }
            else {
                $logTxt = 'Ошибка записи нового статуса в БД!';
                writeDebugInfo("BPSTATE_ACTIVE result:[$updResult]", $upd, ' query: ',AppEnv::$db->getLastquery(),
                 "\n  error:",  AppEnv::$db->sql_error());
            }
        }
        else {
            $logTxt = 'Ошибка при добавлении/обновлении карточки СЭД';
            if (!empty($sedResult['message'])) $logTxt = $sedResult['message'];
        }

        if ( $sedOper === 'updateDocFlowCard' ) {

            if ( !$success ) {
                # карточку в СЭД могли уже подвигать, и команда accept была отвергнута
                appEnv::logEvent($bkend->log_pref.'DOCFLOW WARNING', 'СЭД:Не удалось сменить статус [Согласовать След. этап]', FALSE, $plcid);
                $reason = isset($sedResult['message']) ? $sedResult['message'] : '';
                $logTxt .= '<br>СЭД:Не удалось задать статус [Согласовать След. этап]' . ($reason ? "<br>$reason" : '');
                if (TRUE) {
                    Cdebs::DebugSetOutput(AppEnv::getAppFolder('applogs/') . 'errors-'.date('Y-m-d').'.log');
                    writeDebugInfo("$module, policy[$plcid], Ошибка обновления карточки SED[Согласовать След. этап] fail(card $cardId)/sedOper=$sedOper:", $sedResult);
                    Cdebs::DebugSetOutput();
                }
            }
            # $errText = "Ошибка при выполнении операции в СЭД: " . print_r($sedResult,1);
            if (self::$debug) writeDebugInfo("SED (card $cardId)/$sedOper:", $sedResult);

            # TODO: раскомментировать строку ниже, если будет решено отправлять агенту уведомление об окончании проверки
            # $sent = $bkend->notifyAgmtChange($mailTxt,$plcid);
            # writeDebugInfo("notifyAgmtChange result: ", $sent);
        }
        if($warnActions = self::getActionWarnings(TRUE)) $warning .= ($warning ? '<br>':''). $warnActions;

        if(  $notifyCliAnketa && ($metatype == OrgUnits::MT_BANK) ) {
            # шлем уведомление  в ПП банковского канала
            agtNotifier::send($bkend->module, Events::CLIENT_ANKETA_DSP,$bkend->_rawAgmtData, $cardId);
        }

        if($warning) $logTxt .= '<br>' . $warning;

        if(AppEnv::isApiCall())
            return ['result'=>'OK', 'warning'=>$warning];

        exit('1' . AjaxResponse::showMessage($logTxt) . $bkend->refresh_view($plcid,TRUE));
    }

    # {upd/2022-10-20} AJAX-агент/операционист отправляет договор на андеррайтинг (проверка наличия сканов!)
    public static function startUw() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $module = AppEnv::$_p['plg'] ?? '';
        $shifted = isset(AppEnv::$_p['shift']) ? AppEnv::$_p['shift'] : 0; # альт.режим выполнения
        if(!$module || $plcid<=0) exit('1' . AjaxResponse::showError("Bad call startUw:<pre>" . print_r(appEnv::$_p,1).'</pre>'));

        $bkend = AppEnv::getPluginBackend($module);

        $data = self::getPolicyData($module, $plcid);
        # if(method_exists($this, 'beforeViewAgr')) $this->beforeViewAgr();
        if(!isset($data['stateid'])) exit("Policy not found");
        $usrLevel = $bkend->getUserLevel();
        $docaccess = $bkend->checkDocumentRights($data);
        if($docaccess<1.5) { AppEnv::echoError('err-no-rights'); exit; }
        $reasonid = 0;
        $calcExpired = method_exists($bkend,'policyCalcExpired') ? $bkend->policyCalcExpired() : FALSE;
        $plcExpired = method_exists($bkend,'policyExpired') ? $bkend->policyExpired() : FALSE;
        if($calcExpired) {
            $canUw = 2; # повторный андерр. из-за сгоревшей макс.даты выпуска
            $reasonid = PM::UW_REASON_BY_USER;
            # writeDebugInfo("KT-001 UW by expire");
        }
        elseif($plcExpired) {
            exit('1' . AjaxResponse::showError('Полис должен быть отредактирован/перерасчитан заново (истекло время действия расчета)'));
        }
        else {
            $allReasons = UwUtils::getAllReasons($module,$plcid,TRUE);

            $canUw = (in_array($data['stateid'],
              [0,PM::STATE_POLICY, PM::STATE_IN_FORMING, PM::STATE_DOP_CHECK_DONE,PM::STATE_DOP_CHECK_FAIL, PM::STATE_PAUSED])
              && !empty($allReasons['hard']) );
        }

        if(!$canUw) {
            exit('1'.AjaxResponse::showError('err_uw_impossible'));
        }

        if (method_exists($bkend, 'checkMandatoryFiles')) {
            # обнаружив нехватку документов, ф-ция сама отправит AJAX ответ о недостающих
            $bkend->checkMandatoryFiles('uw');
            # exit('1'.AjaxResponse::showMessage('this->checkMandatoryFiles OK'));
        }
        else {
            BusinessProc::checkMandatoryFiles($bkend, Events::TOUW);
            # exit('1'.AjaxResponse::showMessage('BusinessProc::checkMandatoryFiles OK: '. Events::TOUW));
        }

        $arUpd = [ 'stateid' => PM::STATE_UNDERWRITING ];
        if( $reasonid && empty($data['reasonid']) ) $arUpd['reasonid'] = $reasonid;

        $result = self::updatePolicy($module, $plcid, $arUpd); $lineNo = __LINE__;
        if($result) {
            # writeDebugInfo("this->agent_prod= ", $bkend->agent_prod);
            $statetxt = "Договор отправлен на андеррайтинг.";
            $bkend->loadPolicy($plcid, -1);
            AppEnv::logEvent($bkend->log_pref."SET STATE UW",$statetxt,0,$plcid);
            if(!empty($bkend->notify_agmt_change) && method_exists($bkend, 'notifyAgmtChange')) {
                $bkend->notifyAgmtChange($statetxt, $plcid);
            }
            elseif($bkend->moduleType() !== PM::AGT_NOLIFE) { # для всех кроме НЕ ЖИЗНИ - шлю уведомлялово
                agtNotifier::send($module, Events::TOUW, $data);
            }

            $bkend->refresh_view($plcid);
        }
        else {
            AppEnv::logSqlError(__FILE__, __FUNCTION__, $lineNo);
            exit("1".AjaxResponse::showError('Ошибка сохранения данных'));
        }

        # exit('TODO: startCheck(UW)');
    }
    # унив.вызов: получаю данные о Страхователе/застрахованном...
    public static function loadIndividual($module, $id, $ptype='insr', $pdata=0) {
        if($module === PM::INVEST) {
            if(!isset($pdata['isjinsurer']))
                $pdata = self::getPolicyData($module, $id);

            if($ptype === 'insr')
                $persid = $pdata['isjinsurer'] ?  $pdata['jinsurerid'] : $pdata['insurerid'];
            elseif($ptype === 'insd') {
                $persid = $pdata['insuredisinsurer'] ? $pdata['insurerid'] : $pdata['insuredid'];
            }
            $ret = AppEnv::$db->select(investprod::TABLE_INDIVID, ['where'=>['id'=>$persid],
              'singlerow' => 1
            ]);
            if(isset($ret['phone'])) {
                $ret['fullphone'] = $ret['phone'];
                $ret['fam'] = $ret['lastname'];
                $ret['imia'] = $ret['firstname'];
                $ret['otch'] = $ret['middlename'];
                $ret['fullphone'] = $ret['phone'];
                # остальные - по мере необх.
            }
        }
        else {
            $ret = AppEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>['stmt_id'=>$id, 'ptype'=>$ptype],
              'singlerow'=>1]);
            # делаю унив.имена полей
            if(isset($ret['phone'])) $ret['fullphone'] = $ret['phone'];
        }
        return $ret;
    }
    /**
    * AJAX запрос сформировать письмо, отправить клиенту для онлайн оплаты
    */
    public static function sendEqPay() {
        # self::$debug = 1;
        if($sCfg = AppEnv::getConfigValue('z_debug_acquiring'))
            self::$debug = $sCfg;

        $id = AppEnv::$_p['id'] ?? 0;
        # exit('1' . ajaxResponse::showMessage("sendEqPay $id ".$module));# debug stop
        $module = AppEnv::$_p['module'] ?? '';
        $today = date('Y-m-d');

        if($id<=0 || empty($module)) exit("Bad call(id, module)");
        $bkend = AppEnv::getPluginBackend($module);
        $pdata = $bkend->LoadPolicy($id);

        if( !empty($pdata['reasonid']) && $pdata['stateid']!=PM::STATE_UWAGREED) {
            # онлайн оплата невозможна
            $reason = InsObjects::getUwReasonDescription($pdata['reasonid'], $pdata);
            exit('1' . AjaxResponse::showError("Сначала должно быть согласование андеррайтером,<br>$reason"));
        }
        $isnum = is_numeric($pdata);

        # {upd/2024-07-18} пролонгация: если дата начала сегодня и раньше, отвергаю)
        if(!empty($pdata['previous_id'])) {
            if($pdata['datefrom'] <= $today)
                exit('1' . AjaxResponse::showError('err-prolonation_expired','',to_char($pdata['datefrom'])));
        }
        if(self::isPolicyExpired($pdata,$module) && $today > $bkend->agmtdata['date_release_max']) {
            $msgid = ($pdata['stateid'] == PM::STATE_IN_FORMING) ? 'err-policy-calculation-expired-informing' : 'err-policy-calculation-expired';
            exit('1'.AjaxResponse::showError($msgid) . $bkend->refresh_view($pdata, TRUE));
        }

        # exit('1' . AjaxResponse::showMessage(__LINE__ . "-$module-$id [$isnum] sendEqPay Data: <pre>" . print_r($pdata,1) . '</pre>'));
        # exit('1' . AjaxResponse::showError('OK!'));

        $insr = self::loadIndividual($module, $id,'insr', $pdata);

        if(self::$debug) {
            WriteDebugInfo('send to Acquiring, policy data:', $pdata);
            WriteDebugInfo('insurer: ', $insr);
        }
        $phone = $insr['fullphone'];
        $email = $insr['email'];
        $err = '';
        $refreshMe = FALSE;
        $maxDate = $pdata['datefrom'];
        # если есть макс.дата выпуска, разрешаем онлайн оплату до нее
        if(!empty($pdata['date_release_max']))
            $maxDate = max($maxDate, $pdata['date_release_max']);

        if ($pdata['stateid'] == PM::STATE_FORMED) {
            $err = 'Полис уже оформлен и не подлежит повторной оплате';
            $refreshMe = true;
        }
        elseif (!in_array($pdata['stateid'], [0,PM::STATE_IN_FORMING, PM::STATE_POLICY, PM::STATE_UWAGREED])) {
            $err = 'Текущий статус полиса не позволяет выполнить его оплату';
            $refreshMe = true;
        }
        elseif (/* $pdata['eqpayed']>0 || */ intval($pdata['datepay'])>0) {
            $err = 'Полис уже был оплачен';
            $refreshMe = true;
        }
        elseif (self::PayExpired($maxDate) && empty($pdata['previous_id']) ) {
            $err = 'Дата начала действия полиса просрочена. Оплата невозможна.<br>'
              . 'Если есть возможность, произведите перерасчет с новой датой начала действия';
        }
        elseif ($pdata['insurer_type'] >1) {
            $err = 'Страхователь - Юр-лицо. Онлайн оплата не предусмотрена!';
        }
        if (empty($email)) {
            $err .= ($err ? '<br>':'') . 'У страхователя не задан адрес Email (некуда выслать сообщение клиенту)';
        }
        if (empty($insr['fullphone'])) {
            $err .= ($err ? '<br>':'') .'У страхователя не задан номер моб.телефона';
        } # Пока на СМС не шлем, телефон необязателен?


        # для агентских продуктов "жизни" - всегда общая настройка эквайринга
        if (!empty($bkend->online_payBy)) $cfgKey = 'acquiring_' . $bkend->online_payBy;
        elseif ($bkend->agent_prod === PM::AGT_LIFE || $bkend->agent_prod === 'life')
            $cfgKey = 'acquiring_life';
        else $cfgKey = $module . '_paymode'; # для всех прочих - своя!


        $acqConfig = AppEnv::getConfigValue($cfgKey);
        if(self::$debug) writeDebugInfo("cfg-key: ", $cfgKey, ' acqConfig value: ', $acqConfig);

        if (empty($acqConfig) || $acqConfig === PM::MODE_NONE)
            $err .= ($err ? '<br>':'') . "Для данного продукта не настроены параметры онлайн-эквайринга $cfgKey";

        $metatype = $pdata['metatype'] ?? 0;
        if(!$metatype) $err .= ($err ? '<br>':'') . ' Неизвестный тип партнера/подразделения';

        if ($err) {
            if(self::$debug) writeDebugInfo("acquiring: found errors: ", $err);
            $ret = "1" . ajaxResponse::showError($err) . $bkend->refresh_view($id, TRUE);
            # if ($refreshMe) $ret .= "\teval\fpolicyModel.refreshAgmtView()"; # показать кнопки в соотв. с измен.статусами
            exit($ret);
        }
        # {upd/2024-04-23} перед отправкой ссылки - проверка наличия паспорта (агенты)
        if(method_exists($bkend, 'checkMandatoryFiles'))
            $bkend->checkMandatoryFiles(Events::SEND_ONLINE_PAY);
        else
            BusinessProc::checkMandatoryFiles($bkend,Events::SEND_ONLINE_PAY);

        if($metatype == OrgUnits::MT_BANK) {
            # {upd/2022-11-17} В банковском канале дата оплаты - до date_release + 4 дня!
            $maxDtPay = BusinessProc::getMaxPayDate($bkend->agmtdata['date_release']);
            if($maxDtPay < $today) exit('1' . AjaxResponse::showError('err_payment_after_release'));

            $maxDate2 = $dtexpire = date('Y-m-d', strtotime("+1 days"));
            $dtexpire = min($maxDtPay, $maxDate2);
        }
        else {
            $dtexpire = date('Y-m-d', strtotime("+1 days"));
        }

        # Если есть макс.дата выпуска (МДВ), ограничиваем ей время на оплату:
        if(self::isDateValue($pdata['date_release_max'])) {
            $dtexpire = min($dtexpire, $pdata['date_release_max']);
            # writeDebugInfo("поюзали МДВ: ", $pdata['date_release_max']);
        }

        if(method_exists($bkend, 'changeDateInLink'))
            $dtexpire = $bkend->changeDateInLink($pdata['datefrom']);

        $dtexpire .= ' 23:59:59'; # время до конца суток

                # $pdata['date_release_max'] = МДВ, макс.дата д-вия ссылки - в пределах МДВ!
        $paysum = $pdata['policy_prem'];
        # если не рубли, на лету переводим в руб.
        if ($pdata['currency'] !=='RUR') {
            $curr = $pdata['currency'];
            # $rates = GetRates();
            # isset($rates[$curr]) ? $rates[$curr] : 0;
            $curs = self::getCurrRate($pdata['currency']);
            if ($curs <=0) {
                exit('1' . AjaxResponse::showError("Ошибка при получении курса $curr на текущую дату!"));
            }
            $paysum = round($paysum * $curs, 2);
            $dtexpire = date('Y-m-d') . ' 23:59:59'; # валютные полисы - оплата до конца дня (завтра другой курс)
            # WriteDebugInfo("авто-перевод суммы в руб - ".$pdata['policy_prem'] . " - " . $paysum);
        }
        $checkCrd = Acquiring::findExistingCard($module, $id);
        if(self::$debug) writeDebugInfo("found pay-record ($module, $id)", $checkCrd);
        if (!empty($checkCrd)) {
            $ret = "1" . ajaxResponse::showError($checkCrd);
            exit($ret);
        }
        # exit('1' . AjaxResponse::showMessage("expire eq date: $dtexpire"));
        $emailUser = CmsUtils::getUserEmail($pdata['userid']);
        if(self::$debug) writeDebugInfo("email of user to send payment URL", $emailUser);

        $eqpar = [
          'module' => $module,
          'policyid' => $id,
          'policyno' => $pdata['policyno'],
          'phone' => $phone,
          'paysum' => $paysum,
          'currency' => 'RUR', # $pdata['currency'],
          'clientname' => "$insr[fam] $insr[imia] $insr[otch]",
          'clientemail' => $email,
          'emailseller' => $emailUser,
          'agentid' => AppEnv::$auth->userid,
          'acq_config' => $acqConfig,
          'order_expire' => $dtexpire,
        ];
        $card = Acquiring::createPayCard($eqpar);
        if(self::$debug) WriteDebugInfo('Acquiring::createPayCard result:', $card);
        if (!empty($card['hashsum'])) {
            $hash  = $card['hashsum'];
            $eq = Acquiring::sendPaymentLink($card, '', $bkend->send_policy_in_email);
            if(self::$debug) writeDebugInfo("payment link sent result: ", $eq);
            $response = [];
            if (!empty($eq['email'])) {
                $response[] = $eq['email'];
            }
            if (!empty($eq['sms'])) $response[] = $eq['sms'];
            $ret = '1' . AjaxResponse::showMessage(implode('<br>', $response)) . $bkend->refresh_view($id,TRUE);
        }
        else { // ошибка при создании карты
            $errTxt = 'Ошибка при создании карточки оплаты ' . (SuperAdminMode() ? AppEnv::$db->sql_error() : '');
            $ret = "1" . AjaxResponse::showError($errTxt);
        }

        exit($ret); # AJAX ответ о результате
    }
    # AJAX запрос проверки статуса ордера онлайн оплаты в банке (оплата могла произойти, но не зафиксироваться в ALFO)
    public static function checkEqPay() {
        # self::$debug = 1;
        $id = AppEnv::$_p['id'] ?? 0;
        $module = AppEnv::$_p['module'] ?? AppEnv::$_p['plg'] ?? 0;
        if(!$module || $id<=0) exit("Wrong call: module=[$module], id=[$id]");
        $bkend = AppEnv::getPluginBackend($module);
        $access = $bkend->checkDocumentRights($id);
        if(self::$debug) writeDebugInfo("checkEqPay: access = [$access]");
        if($access < 1.4) exit('1' . AjaxResponse::showError('err-no-rights'));
        $msgTail = '';
        $now = date('Y-m-d H:i:s');
        $plcData = $bkend->loadPolicy($id, -1);
        if(self::$debug) writeDebugInfo("plcData ", $plcData);
        if(!empty($plcData['stateid']) && (in_array($plcData['stateid'], [PM::STATE_PAYED, PM::STATE_FORMED]) || $plcData['stateid']>=9)) {
            # пока сидел на форме, полис успешно оплатили, или отмнили...
            exit('1' . $bkend->refresh_view($id, TRUE));
        }

        $card = Acquiring::getLastOrder($bkend->module, $id);
        if(self::$debug) writeDebugInfo("pay card: ", $card);
        # exit('1' . ajaxResponse::showMessage("TODO checkEqPay: id= $id, card:<pre>".print_r($card,1).'</pre>'));
        $syncResult = 0;
        if(!empty($card['error'])) exit('1'. AjaxResponse::showError($card['error']) . $bkend->refresh_view($id, TRUE));
        if(empty($card['bank_order_id'])) {
            $msg = AppEnv::getLocalized('eqpay_link_not_used', 'Клиент еще не открывал полученную ссылку');
            # после проверки, что эквайрингом еще не пользовались, показать кнопку регистр.оплаты офлайн
            $msgTail = AjaxResponse::show('btn_set_payed');
        }
        elseif($card['is_payment'] == 2) { # была зарегистр.ошибка оплаты
            $msg = AppEnv::getLocalized('eqpay_payment_fail', 'Произошла ошибка при оплате Договора');
            $msgTail = $bkend->refresh_view($id, TRUE);
        }
        elseif($card['is_payment'] >= 9) {
            $msg = AppEnv::getLocalized('eqpay_payment_fail2', 'Ошибка либо платеж просрочен');
            $msgTail = $bkend->refresh_view($id, TRUE);
        }
        elseif(empty($card['is_payment']) && $card['timeto'] < $now) { # протухла
            $msg = AppEnv::getLocalized('eqpay_payment_expired', 'Время действия ордера на оплату истекло');
            $msgTail = $bkend->refresh_view($id, TRUE);
        }
        else {
            $response = Acquiring::getOrderStatusExt($card, 'array');
            if(self::$debug) writeDebugInfo("getOrderStatusExt response as array: ", $response);
            $result = $response['result'] ?? 0;
            if(self::$debug) {
                writeDebugInfo("Acquiring::getOrderStatusExt() card: ", $card);
                writeDebugInfo("Acquiring::getOrderStatusExt() result: ", $result);
            }
            if($result == 1 || $result ==='OK') {
                $msg = AppEnv::getLocalized('eqpay_payment_done', 'Договор оплачен');
                $syncResult = Acquiring::syncronize($card, $result); # синхронизирую данные в ALFO (если не сработал переход после оплаты, полис остался подвисшимв неоплате)
                if($syncResult) $msgTail = $bkend->refresh_view($id, TRUE);
            }
            elseif(empty($result)) {
                if(!empty($result['message'])) {
                    $msg = $result['message'];
                    # [message] => Ошибка обращения к сервису
                }
                else $msg = AppEnv::getLocalized('eqpay_payment_not_done', 'Договор еще не оплачен');
            }
            elseif(is_string($result)) { # emul acquiring
                $msg = $result;
            }
            elseif(isset($result['result'])) {
                switch($result['result']) {
                    case 'FAIL':
                        $msg = AppEnv::getLocalized('eqpay_payment_fail', 'Ошибка при онлайн оплате Договора');
                        $syncResult = Acquiring::syncronize($card, $result);
                        if($syncResult) $msgTail = $bkend->refresh_view($id, TRUE);
                }
            }
        }
        if(self::$debug) writeDebugInfo("msg: $msg, msgTail=[$msgTail],  syncResult: ", $syncResult);
        # exit('1' . ajaxResponse::showMessage("TODO checkEqPay: id= $id, card:<pre>".print_r($card,1).'</pre>'));
        # exit('1' . ajaxResponse::showMessage("TODO checkEqPay: id= $id, result:<pre>".print_r($result,1).'</pre>'));
        exit('1' . ajaxResponse::showMessage($msg,'title-check-payment') . $msgTail);
    }

    # {upd/2024-03-21} AJAX запрос на отзыв онлайн-оплаты
    public static function revokeEqPay() {
        # self::$debug = 1;
        $id = AppEnv::$_p['id'] ?? 0;
        $confirmed = AppEnv::$_p['revoke_confirm'] ?? FALSE;
        $module = AppEnv::$_p['module'] ?? AppEnv::$_p['plg'] ?? 0;
        if(!$module || $id<=0) exit("Wrong call: module=[$module], id=[$id]");
        $bkend = AppEnv::getPluginBackend($module);
        $access = $bkend->checkDocumentRights($id);
        if(self::$debug) writeDebugInfo("checkEqPay: access = [$access]");
        if($access < 1.4) exit('1' . AjaxResponse::showError('err-no-rights'));
        $msgTail = '';
        $now = date('Y-m-d H:i:s');
        $plcData = $bkend->loadPolicy($id, -1);
        if(self::$debug) writeDebugInfo("plcData ", $plcData);
        if(!empty($plcData['stateid']) && (in_array($plcData['stateid'], [PM::STATE_PAYED, PM::STATE_FORMED]) || $plcData['stateid']>=9)) {
            exit('1' . AjaxResponse::showError('err-wrong-state'));
        }
        $acqPayWait = Acquiring::hasWaitingOrder($module, $id);
        # writeDebugInfo("acqPayWait=[$acqPayWait], full payment data: ", \Acquiring::$paymentData);
        if(empty($acqPayWait)) exit('1'.  AjaxResponse::showError('err_eqpay_no_requests'));
        if($acqPayWait === Acquiring::PSTATE_STARTED) {
            # клиент уже открыл ссылку, ордер в банке создан, мог начать его оплачивать
            $linkStarted = \Acquiring::$paymentData[0]['timefortimeout'] ?? \Acquiring::$paymentData[0]['created'] ?? '';
            if(!empty($linkStarted)) {
                # смотрю сколько времени прошло с момента создания ордера, если достаточнро много 0 разрешаю отзыв!
                $hours = \AppEnv::getConfigValue('acquiring_wait_hours');
                if(!$hours) $hours = 12;
                $timedOut = date('Y-m-d H:i:s', strtotime($linkStarted ." +$hours hours"));
                if(self::$debug) writeDebugInfo("revokeEqPay, start time : [$linkStarted], timedOut=$timedOut");
                if(date('Y-m-d H:i:s') < $timedOut && !$confirmed) # еще надо подождать
                    exit('1'.  AjaxResponse::doEval('plcUtils.confirmRevokeEqPay()'));
                # else exit('1' . AjaxResponse::showMessage('МОЖНО отозвать!<pre>' . print_r(Acquiring::$paymentData[0],1) . '</pre>')); # DEBUG STOP
            }
            else {
                if(!$confirmed) exit('1'.  AjaxResponse::doEval('plcUtils.confirmRevokeEqPay()'));
            }
        }
        $revoked = Acquiring::revokeRequest($module, $id);

        if(self::$debug) writeDebugInfo("revoke result: ", $revoked);

        if($revoked) {
            AppEnv::logEvent($bkend->getLogPref() . 'ONLINE PAY REVOKED', 'Отзыв заявки на онлайн оплату',0,$id,FALSE, $module);
            exit('1'.  AjaxResponse::showMessage('eqpay_revoke_done') . $bkend->refresh_view($id, TRUE));
        }
        else
            exit('1'.  AjaxResponse::showError('eqpay_revoke_fail') . $bkend->refresh_view($id, TRUE));
        # exit('1' . AjaxResponse::showMessage('В разработке data: <pre>' . print_r($plcData,1) . '</pre>')); # debug exit
    }
    # {upd/2023-03-15} установка тяжести причины UW
    # вернет TRUE, если произошла смена тек.значения (увеличена)
    public static function setUwReasonHardness($uwhard, $uwcode=0) {
        if(self::$debug > 1) writeDebugInfo("setUwReasonHardness($uwhard, $uwcode), stack: ",
          debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        # {upd/2025-03-17} автоматом выявляю "жесткие" коды причины, требующие обязательного андеррайтинга
        if(in_array($uwcode, PM::$noDeclarReasons)) $uwhard = max(20, $uwhard);
        $ret = (self::$uw_hardness < $uwhard);

        if($uwcode > 0 && !in_array($uwcode, self::$allUwReasons))
            self::$allUwReasons[] = $uwcode;

        if($ret && $uwcode>0) {
            self::$uw_code = $uwcode;
            self::$uw_hardness = $uwhard;
        }
        # writeDebugInfo("setUwReasonHardness($uwhard, $uwcode): [$ret]");
        # writeDebugInfo("all uwcodes list now: ", self::$allUwReasons);
        # writeDebugInfo("setUwReasonHardness($uwhard, $uwcode): [$ret], stack ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        return $ret;
    }
    public static function getAllUwReasons() {
        return self::$allUwReasons;
    }
    # @since 1.69 2023-04-17 готовлю поля вывода типа занятости и прочих "общих" данных на заявлении
    public static function printZayavData(&$dta) {
        if(isset($dta['work_type'])) {
            if($dta['work_type'] ==='F') $dta['work_type_1'] = 1;
            elseif($dta['work_type'] ==='P') $dta['work_type_2'] = 1;
            else $dta['work_type_3'] = 1;
        }
        if($dta['insurer_type']=='1') {
            if (isset($dta['tax_rezident'])) {
                if(\PlcUtils::isRF($dta['tax_rezident']))
                    $dta['tax_rezident_rf'] = 1;
                else {
                    $dta['tax_rezident_other'] = 1;
                    $dta['tax_rezident_country'] = \PlcUtils::decodeCountry($dta['tax_rezident']);
                }
            }
        }
        else unset($dta['tax_rezident_other'],$dta['tax_rezident_country']);

        if(!empty($dta['year_salary']) && intval($dta['year_salary']))
            $dta['year_salary'] = intmoney($dta['year_salary']); # разбиваю пробелами "1 000 000"
    }
    /**
    * получить тек.настройу для модуля "андеррайтинг при пролонгации:
    * если есть свой парам у модуля и включен, беру его, иначе - по общей настройке
    * @param mixed $module имя плагина (стр.модуля)
    * @since 1.69 2023-04-18
    */
    public static function forceUwOnProlong($module='') {
        $mode = ($module) ? AppEnv::getConfigValue($module.'_prolong_uw', 0) : 0;
        if($mode) return (($mode === 'Y') ? 1 : 0);
        return AppEnv::getConfigValue('prolong_only_uw');
    }
    # доп поля для печати на заявление
    public static function addKidRiskByRule($ruleNo, $riskName) {
        if(!isset(self::$KIDRiskByRule[$ruleNo])) self::$KIDRiskByRule[$ruleNo] = [];
        if(!in_array($riskName, self::$KIDRiskByRule[$ruleNo]))
            self::$KIDRiskByRule[$ruleNo][] = $riskName;
    }

    # соединяю все назания рисков в строку группирую по номерам пунктов правил стр-я
    # все риски группируются по указанному номеру пункта правил/условий
    # $word - передать при необх. "Условий", если надо поменять фразы
    public static function gatherKIDRiskByRule($word='Правил') {
        $pointNo = 0;
        $arRet = [];
        foreach(self::$KIDRiskByRule as $ruleNo=>$items) {
            $pointNo++;
            $ruleText = [];
            foreach($items as $riskName) {
                $ruleText[] = "«" . $riskName. "»";
            }
            if(count($ruleText) == 1)
                $arRet[] = "$pointNo. по страховому риску $ruleText[0]: п.$ruleNo $word";
            else
                $arRet[] = "$pointNo. по страховым рискам " . implode(', ', $ruleText) . ": п.$ruleNo $word";
        }
        $retStrg = implode(";\n", $arRet);
        return $retStrg;
    }
    # $prolag: 1 - ежегодной вместо ежегодно и т.д.
    public static function decodeRassrochka($intRassrochka, $prilag = FALSE) {
        switch($intRassrochka) {
            case 0:
                $ret = ($prilag===1) ? 'единовременной' : 'единовременно';
                break;
            case 12:
                $ret = ($prilag===1) ? 'ежегодной' : 'ежегодно';
                break;
            case 6:
                $ret = ($prilag===1) ? 'полугодовой' : 'раз в полугодие';
                break;
            case 3:
                $ret = ($prilag===1) ? 'ежеквартальной' : 'ежеквартально';
                break;
            case 1:
                $ret = ($prilag===1) ? 'ежемесячной' : 'ежемесячно';
                break;
            default: return "[$intRassrochka]";
        }
        return $ret;
    }
    # декодирую расрочку стиля life - калькулятора (наоборот)
    public static function decodeRassrochkaLife($intRassrochka, $caps=FALSE) {
        switch($intRassrochka) {
            case 1: $ret = 'ежегодно'; break;
            case 2: $ret = 'раз в полугодие'; break;
            case 4: $ret = 'ежеквартально'; break;
            case 12: $ret = 'ежемесячно'; break;
            case 0:
            default: $ret = 'единовременно';
        }
        if($caps) $ret = self::mb_ucfirst($ret);
        return $ret;
    }
    public static function mb_ucfirst($string, $encoding='UTF-8') {
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, null, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $then;
    }
    # Запрос на получение загруженной инвест-анкеты клиента
    public static function getInvestAnketa() {
        $module = AppEnv::$_p['module'] ?? '';
        $plcid = AppEnv::$_p['id'] ?? '';

        $plc = self::loadPolicyData($module, $plcid);
        $anketaid = $plc['anketaid'] ?? 0;
        $flName = InvestAnketa::getFinalFile($anketaid);
        # exit("Module: $module, id: $plcid, anketaid $anketaid : $flName");
        if(!is_file($flName)) AppEnv::echoError("err_error_getting_invanketa");
        $ext = GetFileExtension($flName);
        AppEnv::sendBinaryFile($flName, "invest-anketa-$anketaid.$ext");
        # TODO: получить анкету
    }
    # {updt/2023-09-14} - AJAX запрос на вывод подробных данных о персоне (страхователь, Застр, ВП...)
    public static function viewPersonData() {
        $module = AppEnv::$_p['module'] ?? '';
        $plcid = AppEnv::$_p['id'] ?? '';
        $what = AppEnv::$_p['what'] ?? '';
        $persNo = AppEnv::$_p['persno'] ?? '';
        $personStr = $what;
        switch($what) {
            case 'insr': $personStr = 'Страхователе'; break;
            case 'insd': $personStr = 'Застрахованном'; break;
            case 'child': $personStr = 'Застрахованном ребенке'; break;
            case 'benef': $personStr = 'Выгодоприобретателе'; break;
        }
        $arDta = [];
        if($what === 'insr' || $what === 'insd') {
            $flul = 1;
            if($what === 'insr') {
                $plcData = AppEnv::$db->select(PM::T_POLICIES,['where'=>['stmt_id'=>$plcid],
                  'fields'=>'insurer_type,equalinsured,no_benef','singlerow'=>1]);
                $flul = $plcData['insurer_type'] ?? 1;
            }
            $person = AppEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>['stmt_id'=>$plcid,'ptype'=>'insr'], 'singlerow'=>1]);
            if( $flul== 1) { # ФЛ
                $arDta[] = [ 'ФИО, дата рожд.',"$person[fam] $person[imia] $person[otch] ". to_char($person['birth']) ];
                $arDta[] = [ 'Данные СНИЛС,ИНН',"СНИЛС: $person[snils], ИНН: $person[inn]" ];
                if($person['sameaddr'])
                    $arDta[] = [ 'Адрес регистрации и проживания',Persons::buildFullAddress($person,'','',1) ];
                else {
                    $arDta[] = ['Адрес регистрации',Persons::buildFullAddress($person,'','',1)];
                    $arDta[] = ['Адрес проживания',Persons::buildFullAddress($person,'','f',1)];
                }
                    $arDta[] = [ 'Гражданство',PlcUtils::decodeCountry($person['rez_country']) ];
                    # $arDta[] = [ 'Нал.резидент',PlcUtils::decodeCountry($specdta['tax_rezident'] ];
            }
            else { # ЮЛ
                $arDta[] = ['Наименование ЮЛ',$person['fam']];
                $arDta[] = ['Данные ИНН,ОГРН...',"ИНН: $person[inn], ОГРН: $person[ogrn], КПП: $person[kpp]"  ];
                if($person['sameaddr'])
                    $arDta[] = [ 'Адрес регистрации и местонахожд.',Persons::buildFullAddress($person,'','',1) ];
                else {
                    $arDta[] = [ 'Адрес регистрации',Persons::buildFullAddress($person,'','',1) ];
                    $arDta[] = [ 'Адрес местонахождения',Persons::buildFullAddress($person,'','f',1) ];
                }
            }
        }
        $retHtml = '';
        foreach($arDta as $item) {
            $retHtml .= "<tr><td>$item[0]</td><td>$item[1]</td></tr>";
        }

        exit('1' . AjaxResponse::showMessage("<table class=\"zebra w100prc\">$retHtml</table>", 'Данные о '.$personStr));
        # exit('1' . AjaxResponse::showMessage('Data: <pre>' . print_r(AppEnv::$_p,1) . '</pre>','Данные о '.$personStr));
    }
    # Добавляю в список обяз.поля (из ParamDef)
    public static function addRequiredFields($arFields) {
        # writeDebugInfo("fields in ouset:", $arFields);
        if(!is_array(self::$requiredFields)) self::$requiredFields = [];
        foreach($arFields as $fname => $item) {
            if(!empty($item['required'])) {
                self::$requiredFields[$fname] = $item['label'];
            }
        }
    }

    # проверяю данные с формы ввода на заполненомсть всех "обязательных" спец-полей (из подключенной для партнера настройки со списком полей)
    public static function checkRequiredFields($params=FALSE) {
        if(!$params) $params = AppEnv::$_p;
        $ret = [];
        foreach(self::$requiredFields as $fname=>$label) {
            if(empty($params[$fname])) $ret[] = 'Не заполнено поле '.$label;
        }
        return $ret;
    }

    # Получаю строку данных о страхователе/Застрахованном
    public static function getIndividual($policyid, $perstype='', $persid=0) {
        if($persid > 0) {
            $arRet = \AppEnv::$db->select(investprod::TABLE_INDIVID, [
              'where'=>['id'=>$persid],
              'fields' => 'lastname fam,firstname imia,middlename otch,birthdate birth,passporttype doctype,passportseries docser,passport docno,phone,email,citizenship rez_country',
              'singlerow'=>1
            ]);
        }
        else {
            $arRet = \AppEnv::$db->select(PM::T_INDIVIDUAL, [
              'where'=>['stmt_id'=>$policyid, 'ptype'=>$perstype],
              # 'fields' => "fam,imia,otch,birth,doctype,docser,docno,inopass,phone,email,rez_country,inn,snils",
              'singlerow'=>1
            ]);
        }
        return $arRet;
    }
    public static function setDraftState() {
        self::$draftstate_on = TRUE;
    }
    public static function isDraftState() {
        return self::$draftstate_on;
    }

    # вернет 0 если по результатам проверки ниикаких незаполненных полей не осталось, иначе - кол-во сообщений о незаполненных
    public static function isDraftstateReasons() {
        return (count(self::$draft_reasons));
    }

    # подготовит текст сообщения на следующей странице пользователя, о причинах постановик в Черновик
    public static function publishDraftStateMessage() {
        if(count(self::$draft_reasons))
            AppEnv::addInstantMessage( "<b>Карточка договора остается в статусе Черновик, причины</b>:<br>"
              . implode('<br>',self::$draft_reasons) );
    }

    # {upd/2023-11-24} генерапция PDF с ДСП-листом от анкеты клиента
    public static function createAnketaInternal($bkend, $subj = 'insr') {
        $id = $bkend->_rawAgmtData['stmt_id'] ?? rand(1000,99999);
        $xmlName = AppEnv::getAppFolder('templates/anketa/')
          . (($bkend->_rawAgmtData['insurer_type'] == 1) ? 'anketa-fl-internal.xml': 'anketa-ul-internal.xml');

        if(!is_file($xmlName)) {
            self::addActionWarning('Для служебного листа анкеты клиента не найден файл настройки '.$xmlName);
            return FALSE;
        }
        $tmpName =  AppEnv::getAppFolder('tmp/'). "anketa-internal-$id.pdf";
        include_once('printformpdf.php');
        PM::$pdf = new PrintFormPdf([
           'configfile' =>  $xmlName,
           'outname'    => $tmpName,
           'tofile' => TRUE,
           'compression' => PolicyModel::COMPRESS_PDF
        ], $bkend);

        $pholder = self::loadIndividual($bkend->module,$id,$subj);

        $dta = ['anketa_fullname' => $bkend->_rawAgmtData['insurer_fullname'],
                'anketa_inn' => ($pholder['inn'] ?? '')
        ];
        if( AppEnv::isLightProcess() || $bkend->isEdoPolicy($id) ) {
            $dta['b_oprisky_no'] = 1; # проставляю галкe о безрисковости клиента
        }

        # {upd/2025-11-11} для печати должности, ФИО и сигнатуры сотрудника от СК в зав.от канала
        $metaType = $bkend->_rawAgmtData['metatype'] ?? '';
        AnketaPrint::loadSignerData($dta, $metaType);
        $dta['datesign'] = date('d.m.Y'); # Мустафаева Сабина - можно текущую дату

        PM::$pdf->AddData($dta);
        $echoed = ob_get_clean();

        $result = PM::$pdf->Render(true);
        if(!is_file($tmpName)) {
            self::addActionWarning("Не удалось сгенерировать файл сщ служебным листом анкеты клиента!");
            return FALSE;
        }
        return $tmpName;
    }
    public static function addActionWarning($strMessage) {
        self::$actionWarnings[] = $strMessage;
    }
    public static function getActionWarnings($asString = FALSE) {
        if(!count(self::$actionWarnings)) return '';
        return ($asString ? implode('<br>', self::$actionWarnings) : self::$actionWarnings);
    }
    public static function checkChildRelation($strRelation) {
        if(!in_array($strRelation, ['отец','мать','осын','дочь']))
            self::setUwReasonHardness(10, PM::UW_REASON_CBEN_RELATION);
    }

    # установка типа продукта перед поиском полисов на застрахованного
    public static function setProductType($strType) {
        self::$uwProdType = $strType;
    }
    # {upd/2023-12-13}
    public static function getPolicyRisks($plcid, $format = '') {
        if($format === 'LISA')
            $fields = 'rtype,riskid riskename,riskid,risksa sa,riskprem prem,currency';
        else
            $fields = 'stmt_id,rtype,riskid,risksa,riskprem,currency,datefrom,datetill';
        $arRet = AppEnv::$db->select(PM::T_AGRISKS,['fields'=>$fields,'where'=>['stmt_id'=>$plcid],'orderby'=>'id']);
        return $arRet;
    }
    # {upd/2023-12-18} AJAX request - задание повышенного АВ
    public static function setExtendedAv($obj) {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $av = AppEnv::$_p['av'] ?? 0;
        $av = floatval($av);
        $userLevel = AppEnv::$auth->getAccessLevel($obj->privid_editor);
        if($userLevel < PM::LEVEL_IC_ADMIN)
            exit('1' . AjaxResponse::showError('err-no-rights'));

        $data = self::loadPolicyData($obj->module, $plcid);
        if(!isset($data['stmt_id'])) exit('1' . AjaxResponse::showError('err_wrong_call'));
        if(!in_array($data['stateid'], [PM::STATE_PROJECT, PM::STATE_IN_FORMING, PM::STATE_UNDERWRITING]) || !empty($data['docflowstate']))
            exit('1' . AjaxResponse::showError('err-operation_impossible'));
        # $obj->_rawAgmtData['insurer_fullname']
        $arUpdt = ['comission'=>$av]; # , 'reasonid' => PM::UW_REASON_EXT_AV
        if(empty($data['reasonid'])) $arUpdt['reasonid'] = PM::UW_REASON_EXT_AV;
        $logPost = '';
        /* # сразу перевод в статуса "на UW" - позднее от идеи отказались!
        if($data['stateid'] != PM::STATE_UNDERWRITING) {
            $arUpdt['stateid'] = PM::STATE_UNDERWRITING;
            $logPost = '/Перевод на UW';
        }
        */
        $result = self::updatePolicy($obj->module, $plcid, $arUpdt);
        if(!$result) {
            writeDebugInfo("ext.AV fail, sql error: ", appEnv::$db->sql_error());
            writeDebugInfo("SQL: ", appEnv::$db->getLastQuery());
            throw new Exception("Ошибка при сохранении в БД ". appEnv::$db->getLastQuery());
        }

        AgmtData::saveData($obj->module, $plcid, ['extended_av'=>$av]);

        $refreshCmd = '1' . $obj->refresh_view($plcid, TRUE); # команды обновления интерфейса

        if($result) {
            $pref = $obj->getLogPref();
            AppEnv::logEvent($pref."EXT COMISSION", "Задание повышенного АВ{$logPost} - $av %", FALSE, $plcid );
            exit($refreshCmd);
        }
        else {
            exit($refreshCmd . ajaxResponse::showError("Ошибка при задании повышенного АВ!"));
        }
        # exit('1' . AjaxResponse::showMessage("TODO: setExtendedAv: $av, "));
    }

    /**
    * регистрация сбоя в какой-то операции цепочки
    * @since 1.80 {upd/2023-12-27}
    * @param mixed $origin ИД события/модуля, где произошло нехорошее
    */
    public static function setStateFailed($origin='general', $details = FALSE) {
        self::$stateFailed = $origin;
        if(!empty($details) && is_string($details))
            self::$failDetails[] = $details;
    }
    # получить код - признак "что-то случилось"
    public static function isStateFailed() {
        return self::$stateFailed;
    }
    public static function getFailDetails() {
        if(count(self::$failDetails))
            return implode('<br>', self::$failDetails);
        else return FALSE;
    }

    /**
    * {upd/2024-02-27} опеределяет, просрочена ли макс.дата выпуска (МДВ) и надо делать сброс карточки полиса в нач.состояние
    * @param mixed $plcdata массив иои ИД полиса
    * @param mixed $module нужен только если передан ИД полиса
    * @return FALSE - не просрочен; 1 - просрочен, но не подлежит сбросу; 2 - просрочен и нужен сброс в нач.состояние
    */
    public static function isPolicyExpired($plcdata, $module='') {
        if($module === 'investprod' || !isset($plcdata['stmt_id'])) return FALSE;
        if(is_numeric($plcdata) && !empty($module)) {
            $plcdata = self::getPolicyData($module, $plcdata);
            # writeDebugInfo("reload plcdata ", $plcdata);
        }
        if(!isset($plcdata['stmt_id'])) {
            writeDebugInfo("bad call isPolicyExpired  with ", $plcdata);
            throw new Exception("PlcUtils::isPolicyExpired(): Wrong parameters passed");
        }
        if(!$module) $module = $plcdata['module'];

        # {upd/2024-03-05} У модуля может быть свой алгоритм определения, протухла МДВ или нет
        $bkend = \AppEnv::getPluginBackend($module);
        if(method_exists($bkend,'isPolicyExpired')) {
            # return "call own $module::isPolicyExpired: " . $bkend->isPolicyExpired($plcdata);
            return $bkend->isPolicyExpired($plcdata);
        }

        $stateList = [PM::STATE_PROJECT,PM::STATE_DRAFT, PM::STATE_IN_FORMING, PM::STATE_UNDERWRITING, PM::STATE_UWAGREED];

        if(isset($plcdata['meta_type'])) {
            $metatype = $plcdata['meta_type'];
        }
        else {
            $agdata = AgmtData::getData($plcdata['module'], $plcdata['stmt_id']);
            $metatype = $agdata['meta_type'] ?? '';
        }
        if($metatype != OrgUnits::MT_BANK) # в агентских "оплачен" до "выпущен, поэтому "оплаченный" может быть просрочен
            $stateList[] = PM::STATE_PAYED;

        if(in_array($plcdata['stateid'], $stateList)
          && $plcdata['bpstateid']!=PM::BPSTATE_RELEASED) {

            if(!empty($plcdata['previous_id'])) {
                # у нас пролонгация, после даты начала есть еще N дней до сгорания момента выпуска/оплаты
                $days = \AppEnv::getConfigValue('prolong_days_afterend',7) - 1;
                $maxRelDate = date('Y-m-d', strtotime($plcdata['datetill']." +$days days"));
            }
            else {
                $maxRelDate = $plcdata['date_release_max'] ?? FALSE;
                if($maxRelDate === FALSE) {
                    $agmtData = AgmtData::getData($plcdata['module'],$plcdata['stmt_id']);
                    $maxRelDate = $agmtData['date_release_max'] ?? 0;
                }
            }
            if(!self::isDateValue($maxRelDate)) return FALSE;
            if ($maxRelDate >= date('Y-m-d')) return FALSE;
            $payed = ($plcdata['stateid'] == PM::STATE_PAYED || self::isDateValue($plcdata['datepay']));
            # writeDebugInfo("payed=[$payed], $plcdata[datepay] <= $maxRelDate");

            if($payed) {
                $maxDate = AddToDate($maxRelDate,0,0, self::$releasePayedDays);
                $now = date('Y-m-d'); # разрешаю выпустить оплаченный полис в течение 7 дней ($releasePayedDays) после МДВ
                # writeDebugInfo("оплачен ДО МДВ ($plcdata[datepay]<=$maxRelDate), но еще не выпущен и можно выпустить до $maxDate,  ");
                if($plcdata['datepay'] <= $maxRelDate && $now <= $maxDate) {
                    # writeDebugInfo("считаю просрочки нет $now <= $maxDate");
                    return FALSE;
                }
            }
            if(!empty($plcdata['previous_id'])) return 1; # У пролонгаций дату выпуска уже не подвигаешь!

            if($plcdata['stateid'] == PM::STATE_UNDERWRITING) {
                if(!empty($plcdata['docflowstate'])) {
                    return 1; # на андеррайтинге, но уже в СЭД, менять статус нельзя
                }
                else {
                    return 2; # еше не в СЭД, надо сбросить в Проект
                }
            }
            if($plcdata['stateid'] == PM::STATE_IN_FORMING) {
                # {upd/2024-03-19} полис на оформлении - в проект не сбрасываем
                return 1;
            }

            if(in_array($plcdata['stateid'], [PM::STATE_UWAGREED,PM::STATE_PAYED]))
                return 1; # согласован или оплачен, но изменению не подлежит!

            if( !empty($plcdata['bpstateid']) || !empty($plcdata['bptype']) || !empty($plcdata['med_declar'] || !empty($plcdata['sescode']))
              || !in_array($plcdata['stateid'], [PM::STATE_PROJECT,PM::STATE_DRAFT]) )
                return 2; # просрочен и нужен сброс

            return 1; # просрочен, но и так уже в начальном состоянии либо сбросу/перерасчету не подлежит

            # $bkend = \AppEnv::getPluginBackend($plcdata['module']);
            # $calcExpired = method_exists($bkend,'policyCalcExpired') ? $bkend->policyCalcExpired() : FALSE;
            # $plcExpired = method_exists($bkend,'policyExpired') ? $bkend->policyExpired() : FALSE;
        }
        return FALSE;
    }
    # {upd/2024-02-27} выполняет сброс полиса с просроченной датой выпуска в нач.состояние
    public static function resetExpiredPolicy($plcdata,$logging=FALSE) {
        if(!isset($plcdata['module'])) throw new Exception("PlcUtils::resetExpiredPolicy(): Wrong parameter passed");
        $upd = [];
        # статус "на UW" не сбрасываю!
        $clearState = TRUE;
        if( !in_array($plcdata['stateid'], [PM::STATE_PROJECT, PM::STATE_DRAFT]) ) {
            if( $plcdata['stateid'] == PM::STATE_UNDERWRITING ) {
                if(empty($plcdata['docflowstate']))
                    $upd['stateid'] = PM::STATE_PROJECT; # полис еще не в СЭД, можно сбросить с UW в проект и стереть сканы
                else $clearState = FALSE; # если уже в СЭД - статус не менять, файлы не стирать, они уже зафиксированы в СЭД!
            }
            if($plcdata['stateid']  == PM::STATE_UWAGREED) {
                $clearState = FALSE; # уже согласован - статус не менять, файлы не стирать
            }
            elseif(in_array($plcdata['stateid'], [PM::STATE_IN_FORMING, PM::STATE_PAYED])) {
                $clearState = FALSE; # оплачен или на оформлении - статус не менять, файлы не стирать
            }
            else $upd['stateid'] = PM::STATE_PROJECT;
        }
        # writeDebugInfo("clearState=$clearState");
        if(!$clearState) return 0; # откат не делается вообще

        # проставилось несоотв-вие по отказу клиента согласовать ЭДО, заказано повышенное АВ - сброс!
        if(in_array($plcdata['reasonid'], [PM::UW_REASON_DECLARATION, PM::UW_REASON_EXT_AV]))
            $upd['reasonid'] = 0;
        if(!empty($plcdata['med_declar'])) $upd['med_declar'] = '';
        if(!empty($plcdata['bptype']))     $upd['bptype'] = '';
        if(!empty($plcdata['bpstateid']))  $upd['bpstateid'] = 0;
        if(intval($plcdata['dt_sessign'])) $upd['dt_sessign'] = '0';
        if(!empty($plcdata['sescode'])) $upd['sescode'] = '';

        if(count($upd))
            $result = self::updatePolicy($plcdata['module'],$plcdata['stmt_id'], $upd);
        else $result = TRUE;
        if($result) {
            \FileUtils::deleteFilesInAgreement($plcdata['module'],$plcdata['stmt_id'],PM::$clearDocsReset);
            \UniPep::cleanRequests($plcdata['module'],$plcdata['stmt_id']);
            $blkAcq = \Acquiring::blockAllCards($plcdata['module'],$plcdata['stmt_id']);
            if($logging) {
                $pref = strtoupper($plcdata['module']);
                $logTxt = 'Авто-сброс в проект (просрочен выпуск)';
                if(!$blkAcq) {
                    $logTxt .= '/Ошибка сброса онлайн оплаты!';
                    if($logging === TRUE)
                        \AppEnv::addInstantMessage("Внимание! При сбросе полиса не удалось заблокировать ордер на онлайн-оплату, проверьте наличие платежа клиента!",'acquiring');
                    # else # сброс выполнен при массовой обработке джобом - копим ошибки!

                    self::$errors[] = "При сбросе не удалось заблокировать ордер на онлайн-оплату полиса $plcdata[policyno] ($plcdata[stmt_id]), проверьте, не успел ли клиент оплатить!";
                }
                \AppEnv::logEvent("$pref.RESET EXPIRED", $logTxt,0,$plcdata['stmt_id'],NULL,$plcdata['module']);
            }
        }
        return $result;
    }
    # собираю в массив "старые" ИСЖ на заданного зстрах., действующие на дату $startDate
    public static function findOldInvestPolicies($fam,$imia,$otch,$birthday,$startDate) {
        if(!intval($startDate)) $startDate = date('Y-m-d');
        else $startDate = to_date($startDate);
        $fioCond = ['lastname'=>$fam, 'firstname'=>$imia, 'middlename'=>$otch, 'birthdate'=>to_date($birthday)];
        $listbyFio = AppEnv::$db->select('bn_individual', ['where'=>$fioCond, 'fields'=>'id', 'associative'=>0]);
        $sql = AppEnv::$db->getLastQuery();
        $err = AppEnv::$db->sql_error();
        # exit('1' . AjaxResponse::showMessage('listbyFio: <pre>' . print_r($listbyFio,1) . '</pre>'.$sql.'<br>'.$err));
        if(!$listbyFio) return FALSE;

        $strlist = implode(',',$listbyFio);

        $whereAgm = [
          "insurerid IN($strlist) OR insuredid IN($strlist)",
          'stateid='.PM::STATE_FORMED,
          "datetill>'$startDate'",
        ];
        $arRet = appEnv::$db->select('bn_policy',[
          'where'=>$whereAgm,
          'fields'=>"id,'investprod' module,policyno,stateid,datefrom,datetill,ROUND(riskamount/100,2) policy_sa,ROUND(payamount/100,2) payamount,currency"
        ]);
        # $sql = AppEnv::$db->getLastQuery(); $err = AppEnv::$db->sql_error();
        # exit('1' . AjaxResponse::showMessage('arRet: <pre>' . print_r($arRet,1) . "</pre>$sql<br>$err"));
        return $arRet;
    }
    /**
    * {upd/2024-03-28} простанова бп-статуса полис НЕ оплачен
    * (проставлено с ответа агента на вопрос о статусе оплаты)
    *
    */
    public static function setNoPayment($pars = FALSE) {
        if(!$pars) $pars = AppEnv::$_p;
        $module = $pars['module'] ?? $pars['plg'] ?? '';
        $id = $pars['id'] ?? '';
        if(empty($module) || empty($id) || intval($id)<=0) exit('Wrong call');
        # exit('1' . AjaxResponse::showMessage("setNoPayment for $module/$id"));

        $bkend = AppEnv::getPluginBackend($module);
        $access = $bkend->checkDocumentRights($id);
        if($access <1)
            exit('1'. AjaxResponse::showError('err-no-rights-document'));

        $plcdata = self::loadPolicyData($module, $id);
        if($plcdata['bpstateid'] == PM::BPSTATE_UWREWORK || $plcdata['stateid'] != PM::STATE_UWAGREED)
            exit ('1' . AjaxResponse::showError("err_wrong_state_for_action"));

        $result = self::updatePolicy($module, $id, ['bpstateid' => PM::BPSTATE_UWREWORK]);

        if(self::$debug) writeDebugInfo("setNoPayment update SQL: ", AppEnv::$db->getLastQuery());
        if(!$result)
            exit('1'. AjaxResponse::showError('err-saving-error'));

        $plcdata['bpstateid'] = PM::BPSTATE_UWREWORK;
        agtNotifier::send($module,Events::EXPIRED_TO_UWREWORK,$plcdata);

        AppEnv::logEvent($bkend->getLogPref() . 'SET UW REWORK', "Неоплата/просроченная оплата, перевод в статус [На доработке UW]",0,$id,FALSE, $module);
        # writeDebugInfo("setNoPayment, plcdata ", $plcdata);
        exit('1' . $bkend->refresh_view($id, TRUE));
        # AjaxResponse::showMessage("access=[$access]. setNoPayment: <pre>" . print_r(AppEnv::$_p,1) . '</pre>'));
    }

    # {upd/2024-07-10} - единая ф-ция для получения лимита налогового вычета за год
    public static function getLimitNalVychet() {
        $ret = \AppEnv::getConfigValue('ins_return_ndfl', 120000);
        return intval($ret);
    }

    # AJAX запрос получить ответы клиента на отказной стадии согласования
    public static function getClientEdoAnswers() {
        $module = AppEnv::$_p['module'] ?? '';
        $id = AppEnv::$_p['id'] ?? '';
        $stage = AppEnv::$_p['stage'] ?? '';
        Unipep::decodeClientEdoAnswers($module,$id,$stage);
    }
    # AJAX запрос: вывести список всех причин для UW
    public static function viewAllUwReasons() {
        $module = AppEnv::$_p['module'] ?? '';
        $id = AppEnv::$_p['id'] ?? '';
        \UwUtils::viewAllUwReasons($module,$id);
    }
    # проверяет, создан ли полис API-учеткой (т.е. продажа со страницы на сайте, партнёрка)
    public static function policyFromSite($module, $policyid, $details=FALSE) {
        $plcData = self::loadPolicyData($module, $policyid);
        $userid = $plcData['userid'] ?? 0;
        if(!$userid) return NULL;
        $apiUser = \AppEnv::$db->select(\PM::T_APICLIENTS, ['fields'=>'id,username,usertoken,ip_addresses',
          'where'=>['userid'=>$userid], 'singlerow'=>1]
        );
        return (isset($apiUser['id']) ? ( $details ? $apiUser : $apiUser['id'] ) : FALSE);
    }
    # {upd/2025-09-24} готовлю диалог для выбора, чьи данные исправить
    public static function buildModifyPdataDlg($module, $plcid) {
        $bkObj = AppEnv::getPluginBackend($module);
        $ph = \Persons::loadIndividual($bkObj,$plcid,'insr','');
        # TODO: предварительно проверить статус полиса, и отказать если уже нельзя исправлять
        $retBody = "Выберите, чьи данные надо исправить<br><br>";
        $retBody .= "<input type='button' class='btn btn-primary w300' onclick=\"policyModel.startEditPdata('insr',$ph[id])\" value=\"Страхователь: $ph[fam] $ph[imia]\"><br><br>";
        # writeDebugInfo("pholder: ", $ph);
        $arPers = \Persons::loadIndividual($bkObj,$plcid,'insd','');
        # writeDebugInfo("arPers insd: ", $arPers);
        if(is_array($arPers) && isset($arPers[0])) foreach($arPers as $row) {
            $insdid = $row['id'];
            $fio = "Застрахованный: $row[fam] $row[imia]";
            $retBody .= "<input type='button' class='btn btn-primary w300' onclick=\"policyModel.startEditPdata('insd',$insdid)\" value=\"$fio\"><br><br>";
        }                                 #$obj, $policyid, $ptype, $pref=null, $ul=0, $offset=FALSE, $mode = 0
        $arPers = \Persons::loadIndividual($bkObj,$plcid,'child','',0, FALSE, 'export');
        # writeDebugInfo("arPers child: ", $arPers);
        if(is_array($arPers) && isset($arPers[0])) foreach($arPers as $row) {
            $insdid = $row['id'];
            $fio = "Застрах.ребенок: $row[fam] $row[imia]";
            $retBody .= "<input type='button' class='btn btn-primary w300' onclick=\"policyModel.startEditPdata('child',$insdid)\" value=\"$fio\"><br><br>";
        }
        $arPers = \Persons::loadBeneficiaries($bkObj,$plcid,'benef','', FALSE, TRUE);
        if(is_array($arPers) && isset($insdList[0])) foreach($arPers as $row) {
            $insdid = $row['id'];
            $fio = "Выг.Приобр: $row[fam] $row[imia]";
            $retBody .= "<input type='button' class='btn btn-primary w300' onclick=\"policyModel.startEditPdata('benef',$insdid)\" value=\"$fio\"><br><br>";
        }
        # child, cbenef !
        return AjaxResponse::showMessage($retBody, 'Выбор объекта для исправления данных');
    }

    # {upd/2025-10-10} вернет флаг наличия у текущей учетки сотрудника роли комплаенс
    public static function iAmCompliance() {
        return appEnv::$auth->userHasRights(PM::RIGHT_COMPLIANCE);
    }
    # {upd/2025-10-22} вернет флаг наличия у текущей учетки сотрудника роли офицера Инфо-Беза
    public static function iAmInfoSecurity() {
        return appEnv::$auth->userHasRights(PM::RIGHT_INFOSEC);
    }
    public static function setPolicyDept($deptid) {
        self::$policyDeptId = $deptid;
    }
    public static function getPolicyDept() {
        return self::$policyDeptId;
    }
} # class end


}
# если был (AJAX) вызов plcutilsaction, выполняю ф-цию с указанным именем
if (!empty(appEnv::$_p['plcutilsaction'])) {
    $func = appEnv::$_p['plcutilsaction'];
    if (method_exists('PlcUtils', $func)) PlcUtils::$func();
    else exit("PlcUtils: undefined call : $func");
}
