<?php
/**
* @package ALFO - Фронт-Офис
* @author Alexander Selifonov,
* @name app/policymodel.php Базовый класс для работы с данными по договору/полису
* (создание, просмотр, редактир-е,печать, валидация, сохранение, загрузка данных)
* @version 1.132.004 / EDO-unipep / investanketa / unified_pdn
* modified : 2025-12-11
*/
if (!defined('PLC_SAVE_CHECKLOG'))
    define('PLC_SAVE_CHECKLOG', FALSE); # сохранять ли результат проверки по спискам в полис, как html файл
# настройка PLC_SAVE_CHECKLOG в файле cfg/_appcfg.php имеет приоритет

class PolicyModel {
    const MODULEVERSION = '1.125';
    const VERSION = '3'; # 3 - тип ввода род.связи выгодоприоб - 3+: выбор из списка
    const HTM_HIDE = 'style="display:none"';
    const BACKEND = 'backend.php';
    const COMPRESS_PDF = TRUE; # {upd/2023-04-24} Включение режима компрессии генерируемых PDF (заявление, полис)
    static $logErrors = FALSE;
    const ZIPCODE6 = FALSE; # накладывать маску 6 цифр на почтовые коды ?
    const PENSIA_MALE   = 65; # пенсионный возраст - муж
    const PENSIA_FEMALE = 60; # пенсионный возраст - жен
    public static $AGE_CHILD = 18; # возраст окончания статуса "ребенок"
    public static $AGE_CHILD_PROLONG = 22; # {upd/2021-12-10} пороговый возраст "ребенка" при пролонгации (Онко)
    public static $PH2BENEF= TRUE; # {upd/2024-02-15} включить кнопку копирования Страхователя в Выгодоприобр.

    const IMG_NOSTAMP = 'only-faximile.png'; # {09.12.2015} имя файла подписи без штампа компании
    const NOT_RUSSIA = '999'; # спец-код для "страны" НЕ РОССИЯ (Air Agency, присылают вместо кода страны у нерезидентов)
    # const IMG_NOSTAMP = 'signed-stepanova.png'; # имя файла подписи без штампа компании (для подмен при печати)

    public static $debug = 0; # 1.1 - не уходить с формы ввода полиса (многократные занесения без пересчета в кальк.)
    public $debugAnk = 0; # отладочный вывод в выборе XML настроек для анкет
    public $_deptCfg = []; # настройки партнера в данном модуле
    public $_deptReq = []; # Общие реквизиты настройки головного ОУ
    protected $iAmAdmin = FALSE; # удалить, больше не нужна
    static $super_rights = array('bank:superoper','agmt_superoper');
    static $copyPastePersons = TRUE; # вкл.поддержку CTRL-C / CTRL-V из страхователя в блок ВП
    # static $pmcached = [];
    static $earlyInvestAnketa = FALSE; # выводить кнопки ввода инвест-анкеты на раннем этапе - ввод нового договора
    static $anketa_signer_image = ''; # Сюда подгрузится путь к картинке факсимиле подписанта на анкетах клиентв/ВП
    public static $_instance = [];

    protected $printdata = NULL;
    protected $scanTypes = FALSE; # можно задать свой список принимаемых к загрузке типов сканов (паспорт, платеж.док-т...)
    public $scanRestrict = FALSE; # если надо, можно добавить огр.типа файла для отдельных типов сканов)
    // переменные, влияющие на интерфейсы редакторов, вьюверов полиса / заяления
    public $plcversion = '3'; # номер версии для создаваемого полиса
    public $b_stmt_exist = FALSE; // TRUE - есть заявление (ДО договора) и его редактирование
    public $b_stmt_print = FALSE; // TRUE - независимо b_stmt_exist выводить кнопку печати заявления
    public $editagr_mode = 'edit'; // edit = править "заявл-е" входя в режим редактирования - что вызвать stmt или agredit
    public $agredit_is_calc = FALSE; # Редактор данных - он же и калькулятор (обычно - коробочные продукты) - вызывать SavePolicyRisks

    public $b_benefs = FALSE; // TRUE - у продукта есть выгодоприобретатели (ВП)
    public $benef_order = TRUE; // TRUE - разрешать выбор очередности ВП
    public $benef_ul = 1; // TRUE - разрешен ВП ЮЛ (только один, первый!)
    public $max_benefs = 2; # макс. число выг-приобр.
    # $benef_relation: 0 = ввод текста с названием род.связи, 1 = выбор из пред-объявленных("иное"-уход на андеррайтинг)
    public $ignore_uw_reasons = [];
    public $benef_relation = 1;
    public $benef_phones = 0; // Ввод телефонов для выгодоприобр: 0- нет, 1-ввод, но необязат., 2-обязат(не пускать пустой тел-1)
    public $benef_email = 0; // Ввод адрес электронной почты(email) для выгодоприобр: 0- нет, 1-ввод, но необязат., 2-обязат(не пускать пустой, не валидный email)
    public $benef_attorney_doc = 0; // Ввод документа, подтверждающего полномочия представителя для выгодоприобр: 0- нет, 1-ввод, но необязат., 2-обязат(не пускать пустое поле)
    public $benef_passport = 1; // Ввод серии-номера паспорта ВП, 1-ввод, но необязат., 2-обязат(не пускать пустой пасп)
    public $benef_address = 0; // @since 1.52/2020-02-21, 1 - ввод адреса, 2 = Обяз.ввод адреса у ВП
    public $clientBankInfo = FALSE; // вводить данные для выплат клиенту
    public $b_childBenef = TRUE; // TRUE: выводить блок выбора ВП для застр.ребенка если он есть в полисе, 2: с полем cbenefdocconfirm (2024-08-16)
    public $max_childbenefs = 1;
    public $paused = FALSE; # договор приостановлен на текущем статусе по к-либо причинам
    public $b_calculated = FALSE;  // TRUE - отдельный расчет ДО сохранения, в редакторе блокировать смену дт.рожд-я и пола у застрах.
    public $b_sendcorresp = FALSE; // TRUE - agredit/stmt: есть поле "высылать корреспонденцию" (блок "страхователь")
    public $strong_migcard = FALSE; // TRUE - строгая проверка мигр.карты (дата оконч. пребывания должна быть неистекшей)
    public $b_block_working = FALSE; // 1,2,3 - agredit/stmt: выдавать блок полей "место работы, должность..." 3 - с должн.обяз.
    public $b_generate_xml = 'LISA'; // TRUE - для модуля должна быть генерация XML (LISA)
    public $b_epolicy_wf = FALSE; // {upd/2023-04-24} TRUE - при финальной операции над догвором выгружать в СЭД чистовую версию полиса
    public $child_vp = ''; // {upd/2021-02-03} 'insd' - если при выгрузке надо в детских рисках назначить выгодополучателем ЗАстрахованного, а не страхователя
    public $logAgentEvents = FALSE; // TRUE для продуктов, где надо регистрировать для отчета по агентам событие "новый проект полиса"
    public $export_xml_inuw = 0; # 1|TRUE=отправлять в СЭД XML файл даже если полис на андеррайтинге (0 - отправлять только когда статус "Оформлен")
    # protected $b_check_finmon  = FALSE; // TRUE - включить в интерфейс кнопку проверки на террориста
    public $WAIT_DAYS = 180; # станд.период ожидания, дней
    protected $js_modules = '';
    protected $errorMessage = '';
    protected $FOLDER_SCANS = 'agmt_files/'; # в эту папку складываются сканы документов
    public $home_folder = ''; // plugin files subfolder
    protected $_drawGrid = 0; # ruler - пристрелочная сетка на PDF формах
    protected $_debugPdf = 0;
    protected $_debugExp = 0;
    protected $_debugAdd = 0; # при установке в TRUE добавление полиса эмулируется (отладка процесса создания)
    public $beforeSaveAgr = FALSE; # hook вызываемый перед сохранением полиса (например, перерасчет стоимости, формирование рисков и премий)
    public $b_clientRisky = FALSE; # {upd/2022-07-21} - ввод признаков "рисковости" клиента
    public $riskyProg = FALSE; # {upd/2022-12-20} - рисковый продукт (свои правила простановки/проверки дат выпуска/оплаты)
    protected $init_state = PM::STATE_PROJECT;
    // настроечные переменные, влияющие на форму ввода параметров полиса, их проверки, видимость кнопок "бизнес-процесса"
    public $invest_mode = 0; # Признак инвест-полиса. Если инвест-полис, дату окончания берем по траншу! (1 = INDEXX, 2 = TREND + поиск по кодировке!)
    protected $insuredList = []; # в полисах со списком застрахованных - сюда грузить список (1 запись - один Застрах)
    public $reportDept = FALSE;
    public $reportChannel = FALSE; # канал, по коорму будет собираться отчет (в ПП канала)
    # {updt/2020-03-24} - вводим упрощенный ввод адресов (для сайта/DADATA)
    public $simpleAddr = FALSE; # TRUE - весь адрес одной строкой (приходит из API, заполненный в КЛАДР/DADATA - ТОЛЬКО для nonlife!

    public $notify_agmt_change = 1; # 1 - отправлять email админам в СК при каждом создании/редактировании договора
    public $notify_addfile = 0; # 1 - отправлять уведомление о загруженном файле

    public $stmt_noconfirm = 0; # 1 - сохранять новое заявление/проект договора без запроса "все проверено?"
    public $editable_states = [ PM::STATE_DRAFT, PM::STATE_PROJECT, PM::STATE_POLICY ];
    protected $uw_after_edit = FALSE; # если было редактирование, после него дать полису статус на андеррайтинге
    public $uwBlocking = FALSE; # TRUE: если есть причины для hard UW (террорист, списки СБ...) полис не сохранять!
    protected $auto_uw_state = FALSE; # в новом полисе, если есть осн.для UW, сразу ставить статус PM:UW_UNDERWRITING
    public $ul_enabled = 1; // 1=Страхователь может быть Юр-Лицом, FALSE - только ФЛ  2 - "Расширенный ЮЛ", 3 - с подписантом
    public $ul_mandatory_document = TRUE; # Для ЮЛ обязательны серяи и номер свидетельства
    protected $nonlife = FALSE;
    public $lifeIns = 'life'; # life - беру лимит 15000, 'ns' - 40000
    protected $termunit = 'Y'; # единица измерения страховых периодов (Y-год, M=мес)
    protected $bp_type = ''; # тип Бизнес-Процесса (TODO: поддержка БП "agent")
    protected $agent_prod = 0; # TRUE - Модуль относится к агентским продуктам
    protected $lossBlockingProlong = FALSE; # TRUE: наличие убытков в прошлом полисе запрещает его пролонгацию
    public $insurer_enabled = TRUE; // Есть ли ввод страхователя (FALSE - нет), 'UL' - только Юр-Лицо!(2020-03-24)
    public $email_mandatory = 1; // 1 - обязательный ввод Email страхователя, 2 - и застрахованного
    public $box_product = FALSE;    # TRUE=Коробочный продукт
    protected $email_checker = FALSE; // кнопка для проверки Email на форме просмотра полиса
    public $income_sources = FALSE; // вывод блока полей "укажите источники дохода"
    protected $in_anketa_client = FALSE; // вывод блока полей "Дополнительные вопросы о Страхователе, банкрот"
    protected $bindToclient = FALSE; # коробка (нет отдельного калькулятора), у агентов есть привязка к выбору "клиента"

    public $enable_paymemt = TRUE; // Вывод кнопки "Оплата" на просмотре полиса. FALSE - кнопку "Оплата" не выводить
    public $enable_statepaid = FALSE; // показывать кнопку "Оплата". При оплате переводить статус в "оплачен" (7)?
    public $online_payBy = FALSE; # задать life | dms | pnc для выбора соотв.настройки эквайринга при онлайн оплате
    public $separate_anketas = FALSE; # кнопка для печати анкет отдельно от осн.документа
    public $eq_payment_enabled = FALSE; // Возможность отправки клиенту ссылки для оплаты картой
    protected $promo_enabled = FALSE; // Возможность промо-акций (промо-коды)
    protected $loss_enabled = FALSE; // По полису могут регистрироваться убытки
    protected $defer_policyno = FALSE; // отложенная генерация номера полиса (в момент отправки далее)
    public $afterFormed = FALSE; # при TRUE вызывать afterPolicyFormed независимо от $event
    // true - доступна любому авторизованному операционисту, массив "список ИД прав" - только при наличии роли из списка
    public $send_policy_in_email = FALSE; // в письме со ссылкой на оплату - Отправлять приаттаченный черновик полиса PDF
    protected $api_ikpMandatory = 0; // 1:при создании полиса из API не пускать если не передан ИКП агента, 2 - агента и куратора

    protected $deferredFields = []; # поля, чьи значения отправлять на форму в последнюю очередь
    public $multi_insured = FALSE; // 0|FALSE - страхователь-Застрахованному, 1 если страхуется один застрахованный, 2-если возможно двое застрах.,100-список
    protected $multiInsuredReady = FALSE; // блокирует установку $multi_insured
    public $block_nonresidents = 2; # блокировать полис для не-резидентов РФ: 1-блокировать выпуск, 2 - создать, но в статусе "андеррайтинг"
    public $block_tax_rezident = 0; # @since 2018-08-28 выводить ли блок выбора "налоговое резидентство" (в составе block_working)
    public $agredit_notify_payment = FALSE; # на форме agredit - вывод полей "уведомлений СМС"
    public $insured_phones = 2; # Вывод полей ввода телефонов, email у застрахованного(1-доступны но не обяз, 2 - обяз.ввод телеф.!)
    public $insured_child  = 0;     // Установить 1 если может быть застрахованный ребенок
    public $insured_flex  = FALSE;  // ЗАстрахованный может быть как взрослым, так и ребенком (зав.от введенной даты рожд.
    public $insured_adult  = 1;     // Установить 0 когда нет взрослого Застрах. (getAgmtValues)
    public $insured_relation = 0;   // Поле "Отношение Застрахованного NN к страхователю {upd/2020-12-17}
    protected $uw_declaration = FALSE;  // Есть ли поле подтверждения соответствия декларации застрахованного
    public $declarToUw = FALSE; # при задании мед-декларации =N(Не соотв.) сразу переводить дог. в статус UW
    public $married_status = 1;  // выводить ли поле Женат/Холост в параметрах физ-лица (0.5 - выводить но не запрещать пустой)
    public $insrinn = 0; # обязательный ИНН у страхователя ФЛ
    public $snils = 1; # СНИЛС у страхователя: 0 - нет, 1 - есть, необяз., 2 - есть и обязателен к вводу!
    public $insdsnils = 0; # СНИЛС у Застрахованного: 0 - нет, 1 - есть, необяз., 2 - есть и обязателен к вводу!
    # {upd/2021-02-19} - условный вывод анкеты ФЛ(страхователя):
    protected $print_anketa = 1;  // 1: выводить анкету ФЛ всегда, 'LIMIT': только если годовой взнос не превышает лимит (15000р.)
    public $invest_anketa = FALSE;  // 1: по продукту активен запрос инвест-анкеты клиента
    public $anketa_6886 = FALSE;  // 1: нужна новая анкета ЦБ 6886-У (вывод на ЭДО странице еще одной ссылки просмотра
    protected $specialTunings = []; # любые доп.настройки, включаемые при опр.условиях
    protected $draft_packet = FALSE;  // пока полис не будет в статусе оформлен, выдавать пакет без печатей и с диагональным "ЧЕРНОВИК"
    public $draft_state = FALSE;  // разрешение на статус "черновик" (пока не все обяз.данные заведены)
    protected $hide_statement  = FALSE; // Установить TRUE если заявление не оформляется (не имеет смысла)
    public $birth_place = 1; // в редакторе страхователя/застрах: 0= ничего, 1: выводить SELECT страны рождения, 2 - строка ввода "место рождения"
    public $enable_setformed = TRUE; // доступна кнопка "оформить договор" (FALSE- недоступна)
    public $enable_touw = FALSE; // новый  полис - доступна галка "отправить на андеррайтинг (FALSE- недоступна)
    public $enable_meddeclar = FALSE; // бизнес-процесс с установкой ДА-НЕТ по декларации Застрахованного
    public $bFinPlan = FALSE; # наличие фин-плана (для вывода кнопки "Фин-план" на форме просмотра
    protected $finplan_fmt = ''; // нестанд.формат сохранения данных в поле alf_agmt_specdata.fin_plan : 'json'
    protected $ask_for_uw = 1; # спрашивать (1) или нет (0) "Договор будет отправлен на андеррайтинг. Хотите продолжить?"
    protected $draftWord = FALSE; // нестанд.слово на полисе в статусе черновик
    public $bReinvest = FALSE; // в продукте можно указывать "реинвестиция"

    public $peps_check_auto = FALSE; # делать PEPs авто-проверку страхователя/застрахованного сразу после ввода данных
    #   1 - просто делать проверку и сохранять код в alf_agreements.pepstate,
    #   2 - если не ОК, еще и переводить полис в UW, а если OK и полис был в статусе UW/причина PEPs, возвращать статус "полис"
    #   3 - еще и возвращать обратно полис из андеррайтинга, если исправили список Застрахованных и они больше не PEPS/террористы
    # точный код "плохого" статуса, в который перевести полис - в переменной $pepsfail_state:
    public $autoCleanPep = TRUE; # TRUE: вызов UniPep::cleanRequests() после изменения данных в полисе (после agredit - saveAgmt()
    public $pepsfail_state = 0; #PM::STATE_UNDERWRITING; // если не 0, сразу выставлять stateid при срабатывании PEPs/террористы, иначе - reasonid

    // код причины перевода на UW вновь заведенного/сохраненного договора,
    // может быть именем callback функции, которая вернет код причины, если надо
    protected $new_agmt_uwcode = 0;

    protected $peps_detailed = []; # подробный ассоц.массив результатов проверки по спискам

    protected $sign_pdf_states = []; // список ИД статусов, при которых подписывать PDF с пакетом документов (полисом) ЭЦП

    // $uwcheck_on_save: выполнять ли станд.проверку андерр (наличие полисов у застрахованного)
    // FALSE|0 - не выполнять, true|1 - выполнять если только что завели новый дог., 2+ - проверять после каждого редактирования
    public $uwcheck_on_save = 0;

    public $enable_export  = FALSE; // доступность выгрузки в Lisa (кнопки "акцептовать" на договоре) и в СЭД
    protected $redefineExpCode= FALSE; // при получении конфигурации для выгрузки, использовать кодировку из номера полиса, не из prodcode
    public $exportType = 0; // нестанд.алгоритм генерации файла экспорта
    protected $comission_mode = 0; // 1 - искать в справочнике комиссию для головного подразд, 0 - комиссия не используется!
    public $agmt_editable = FALSE; // Установить TRUE если договор можно повторно отредактировать (кнопка "Редактировать")
    public $enable_agmtscans = 1; // 0: сканы по полисам не загружаем, 1 - можно, 2: ОБЯЗАТЕЛЬНЫ для оформления
    public $enable_agmtscansUw = FALSE; // разрешать ли опреационисту загрузку сканов документов в полис на андеррайтинге
    public $enable_printpackUw = TRUE; // разрешать операционисту печать полиса в статусе "на андеррайтинге"
    public $payed_to_formed = 0; // 1: при проставлении оплаты сразу переводить полис в ОФОРМЛЕН

    public $uploadScanMode = 1; // 0: старый способ загрузки сканов, 1 - "новый" (popup-меню для выбора типа документа/05.2018)
    public $mandatoryDocs = []; # список обязательных типов сканов для перевода в Оформленный/движения по процессу
    public $mandatorySoglPdn = TRUE; # обязательность скана "согласия на обраб.ПДн"
    public $uw_editable = FALSE; // Разрешено ли редактировать полис в статусе "на андеррайтинге"
    public $select_signer = FALSE; // Разрешить выбор подписанта для полиса на форме просмотра viewagr
    public $enable_underwriting = TRUE; // Установить FALSE если в модуле нет андеррайтинга
    public $benef_separate_riskprc = FALSE; // раздельный ввод % выплаты за риски смерти по выгодоприобретателю
    public $input_seller = TRUE; // если у орг-.нита активен режим get_seller, и input_seller=true, будет поле ввода "Продавец" (заказ от ВТБ 2020-09)

    protected $declar_uwcode = '1'; // код причины текста в случае отказа от соответствия застрахованного пунктам в заявлении

    public $view_risks_mode = 0; // способ показа премий по рискам (включать если, напр, сумма премий не сходится с общим взносом
    protected $view_showPremium = TRUE; // на форме просмотра - показывать колонку с премиями по рискам
    protected $cb_memo = FALSE; // формировать ли памятку клиента ВСС либо ЦБ в данном продукте

    protected $enable_prolongate = FALSE;   // показывать кнопку "Пролонгация" на форме просмотра
    public $prolongate_before_end = 30; // за сколько дней до оконч.полиса можно оформить пролонгацию
    public $prolongate_after_end  = 30;  // сколько дней после оконч.полиса можно пролонгировать
    public $payBeforeDatefrom = NULL; # за сколько дней до даты начала д-вия надо оплатить полис ( отрицат.значения - можно после даты начала)
    protected $prolongate_paydays = 14;  // {upd/2021-01-27} сколько дней дается на оплату с момента начала д-вия пролонгированного полиса

    protected $report_to_lisa = FALSE; # список отчетов - выводить пункт "отчет для экспорта в LISA"
    protected $ben_unassignedrisks = true; # разрешать ли "нераспределенные по выгодоприобретателям" риски смерти

    public $insr_doctypes = ''; # можно задать список допустимых видов документа для страхователя-ФЛ ('pass,svro'...)
    public $insd_doctypes = ''; # список допустимых видов документа для застрахованного-ФЛ ('pass,svro'...)
    public $child_doctypes = ''; # список допустимых видов документа для застрахованного-ребенка
    public $benef_doctypes = 'pass,zpass,svro,inopass,other'; # список допустимых видов документа для ВП

    protected $policyno_mode = 1; // 1 - номер полиса генерится по кодировке и след.номеру из пула, 2-вводится руками(ID,...), 0-не использ.
    protected $policyno_title = '№ полиса'; # подпись к тому, что вводится в "номер полиса"
    protected $b_dissolute = TRUE; # true - полисы можно расторгать, FALSE - нет
    public $b_seekPerson = 0; # TRUE - вывод кнопки и ф-ционал поиска стр-ля/застрахованного по ФИО+рожд / паспорту при заведении нового полиса
    public $edoType = ''; # Тип ЭДО/ПЭП процесса (для нового uniedo класса)
    protected $edoformed_fix = TRUE; # {upd/2021-0225} TRUE=При переводе ЭДО полиса в Оформлен автоматом создавать подписанный ЭЦП и в СЭД? (для агентских - не нужно!)
    protected $nerezEdo = 0; # TRUE - разрешать для НЕ-резидентов оформляться по ЭДО (по умолч FALSE - нет!)

    public $sed_product_type = ''; # тип продукта для выгрузки в СЭД (поле в карточке - 'Тип продукта')
    public $product_type = ''; # общий тип продукта ('invest' - не делать поиск полисов на того же застрахованного'
    public $sed_final_state = 1; # в карточке СЭД выполнять финальные последние установки "решения" (accept)

    public $enable_print_a7 = FALSE; // TRUE если надо печатать квит.А7
    public $enableExtAv = TRUE; # разрешен ввод Повышенного АВ
    public $mtpost = 'nsj'; # xxx-постфикс для переменных в сессии : srv_xxx, calcdata_xxx, finplan_xxx (после расчета в калькуляторе)
    public $warning_text = '';
    public $agmt_setstate = 0;
    public $uw_reason = '';
    public $uw_reasonid = 0;
    public $specCond = '';
    # списки ИД прав, дающих возможности оператора, андеррайтера, супер-админа, и т.д.
    public $privid_editor  = ''; # в каждом плагине - свои ИД прав
    public $privid_view    = '';
    public $privid_super   = 'superoper';
    protected $privid_reports = 'reports';
    protected $privid_uwrights = ''; # Право (или список прав) андеррайтера
    protected $_userLevel = NULL; # уровень доступа при ввод/измкенении договоров
    protected $reportLevel = NULL;   # уровень доступа в отчетах и просмотр договоров
    protected $sedMinLevel = PM::LEVEL_IC_ADMIN;   # мин.уровень доступа для видимости ссылки в СЭД
    protected $_viewLevel = NULL; # Право просмотра (отчеты)
    protected $saleSupport = NULL; # поддержка продаж (Сотр.СК) и выше
    private $_compliance = NULL; # Право Комплайнс
    private $_metaType = 0; # мета-тип орг-юнита, в котором выпущен договор

    public $recalculable = FALSE; # есть ли поддержка перерасчета полиса
    public $_rawAgmtData = FALSE; // полная строка данных по договору из alf_acgreements

    public $_rawBenefs = array(); // полный массив данных выгодоприобретателей
    public $_rawcBenefs = array(); // ВП для застр.ребенка
    public $_rawPolicyRisks = array(); // полный массив рисков (raw)
    protected $title_scans_agr = 'title_scans_agreement'; # ID заголовка к странице сканов к договору
    public $tarif = 0;
    const EXPORT_PLAINRISKS = 'plainrisks'; // экспорт рисков - подробный
    const EXPORT_RISK_SHORTNAMES  = 'RiskShortNames'; // экспорт рисков - с их именами типа "Дожитие застрахованного"
    const EXPORT_RISK_EXPORTNAMES = 'ExportName'; // экспорт рисков - с их общими именами вместо ID (типа "Д")
    # protected $privs_superoper = array('super_oper'); # список ИД прав, имеющих права супер-оператора
    # protected $privs_icadmins = array('ic_admin'); # права "админа стр.компании"

    static $main_risk_list = array('endowment','death_acc','death_any','death_acc_delay','death_any_delay'); # ИД основных рисков
    static $sa_limits = array( # лимиты без-андеррайтингового оформления договоров
        'death'    => array('RUR'=>10500000, 'USD'=>  175000) # по риску смерти СЛП для осн.застрахованного (было 6 млн)
       ,'child'    => array('RUR'=> 1200000, 'USD'=>   20000) # по риску инвалидности застрах.ребенка (было 600 000)
    );
    static $int_rate = array('RUR'=>1, 'USD'=>60, 'EUR'=>60); # внутр.курс пересчета, для нужд андеррайтинга "мульти-валютных ситуаций"

    static $personfields = array('fam','imia','otch','birth','inn','sex','rez_country','birth_countryid','birth_country','doctype','docser'
         ,'docno','docdate','docpodr','docissued','inopass','migcard_ser','permit_type','migcard_no','docfrom','doctill','married',
         'phone','phone2','email'
         ,'adr_zip','adr_countryid','adr_country','adr_region','adr_city','adr_street','adr_house','adr_corp','adr_build','adr_flat','sameaddr'
         ,'fadr_zip','fadr_countryid','fadr_country','fadr_region','fadr_city','fadr_street','fadr_house','fadr_corp','fadr_build','fadr_flat'
    );

    public $availableCodes = []; # кодировки продуктов, доступные юзеру (в соотв. с настройками головного подразделения)
    # protected $privs_uw = array('underwriter'); # список ИД прав андеррайтера

    protected $viewWidth = 800;
    public $log_pref = ''; # префикс для типа события в eventlog - всегда задавай свой в __construct()
    public $policyno_len = 10; # сколько цифр должно быть у числовой части в номере полиса - [BBB]-##########

    protected $baseRisks = []; # список ИД "базовых" рисков в продукте
    protected $mainRisks = []; # осн. риски с общей стр.суммой (на просмотре полиса будет одна СС на все)
    protected $dopRisks = [];
    protected $mandatoryRisks = []; # при выгрузке добавить сюда риски, обязательные в XML, которые могли не сформироваться в списке рисков полиса (оусв...)
    public $xmlUsrParams = []; # польз.параметры в XML файле настройки печати

    // $nl_risks: Non-Life risks - риски ДМС и прочие "НЕ ЖИЗНЬ" (СС, премии формируются своими ф-циями) (в основной гр.рисков?)
    protected $nl_risks = array();
    protected $nl_risks_join = FALSE; # на форме просмотра полиса все СС по рискам не-жизни показать одним общим значением

    protected $benefRisks = []; # складываем риски, которые относятся к взрослым ВП
    protected $cbenefRisks = []; # складываем детские риски, которые относятся к взрослым ВП
    public $agmtdata = []; # сюда технические грузятся данные по договору (as is)
    public $reportTitle = 'Отчет по договорам';

    public $spec_fields = []; # список имен полей, специфических для конкретного продукта/плагина
    private $_cache = array();
    protected $_restricted = 0;
    private $_headDept = 0;
    private $_repOuFields = [];
    public $rezident = 0;
    public $right_handpno = '{prefix}_enterpno'; # ИД права ручного ввода номера полиса
    public $calcid = '';

    public function getHomeFolder() {
        return $this->home_folder;
    }

    # получить список "особых" полей для данного продукта
    public function getSpecFields() {
        return $this->spec_fields;
    }

    static $report_types = array(
        'rep_export'    => array('title'=> 'Выгрузка своих полисов в Excel за выбранный период времени' ) # операционист и выше
       ,'rep_exportall' => array('title'=> 'Выгрузка всех полисов в Excel за выбранный период времени' ) # ЦО банка
        # ,'rep_salesall'  => array('title'=> 'Отчет по продажам за выбранный период времени' ) # у менеджера - по всем прикреп.подразд
       ,'ropt'          => array('title'=> 'Выгрузка реестров по доп. опциям всего банка за выбранный период времени' )
    );

    # кнопки и их доступность/видимость на форме просмотра договора
    public $all_buttons = [];

    static $individTypes = [
      'insr'  => 'страхователя'
     ,'insd'  => 'застрахованного'
     ,'insd2' => 'застрахованного №2'
     ,'child' => 'застрахованного ребенка'
     ,'child1' => 'застрахованного ребенка 1'
     ,'child2' => 'застрахованного ребенка 2'
     ,'child3' => 'застрахованного ребенка 3'
     ,'benef' => 'Выгодоприобретатель'
     ,'cbenef'=> 'Законный представитель Застрахованного ребенка'
    ];
    static $uw_details = [];
    public $calc = [];
    public $srvcalc = array();
    public $spec_params = array();
    public $finplan = array();
    public $ins_params = array();
    public $pholder = array(); # загруженый страхователь
    public $insured = []; # застрахованный
    public $insured2 = []; # застрахованный - 2+
    public $child = array(); # застрахованный, деточка
    public $pepsState = 0; # здесь будет результат проверок по спискам заблокироанных/террористов
    public $maxPepsState = 1; # PEPS - разрешаем, все что выше - блокируем

    static $list_datefields = ['datefrom','datetill','created','updated','insrbirth','insdbirth','insddocdate','insrdocdate','insrdocfrom','insrdoctill',
    'insddocfrom','insddoctill','childdocfrom','childdoctill'];

    static protected $subheaders = [ # строки для вывода подзаголовков (в списке рисков)
        'mainrisks' => 'Страховые риски',
        'addrisks' => 'Дополнительная страховая защита',
        'nl_risks' => 'Риски (в части ДМС/)',
    ];

    static $stmt_states = [ # список возможных состояний заявл-я - поле stmt_stateid
      '0' => 'Проект'
     ,'2' => 'На андеррайтинге'            # STATE_UNDERWRITING
     ,'3' => 'Согласовано с андеррайтером' # STATE_UWAGREED
     ,'4' => 'Требуется внесение/изменение данных' # STATE_UW_DATA_REQUIRED
     ,'9' => 'Аннулировано'
     ,'10'=> 'Отменено'
     ,'11'=> 'Оформлено'
    ];
    static $agmt_states = [ # список возможных состояний договора stateid
      '-10' => 'Черновик'
     ,'0' => 'Проект'
     ,'1' => 'На оформлении'
     ,'2' => 'На андеррайтинге'
     ,'3' => 'Согласовано андеррайтером'
     ,'3.1' => 'Согласовано с корректировкой'
     ,'4' => 'Требуется внесение/изменение данных' # STATE_UW_DATA_REQUIRED
     ,'5' => 'Проверка данных в СК'
     ,'6' => 'Полис'
     ,'7' => 'Оплачен'
     ,'9' => 'Аннулирован'
     ,'10' => 'Отказ' # отменен/Не вступил в силу
     ,'11' => 'Оформлен'
     ,'12' => 'Не согласовано андеррайтером'
     ,'30' => 'На проверке у Комплайнс'
     ,'33' => 'На проверке у ИБ'
     ,'63' => 'На проверке у Комплайнс и ИБ'
     ,'50' => 'Расторгнут'
     ,'51' => 'Расторгнут с выкупной'
     ,'60' => 'Блокирован'

    ]; # ID и названия возможных состояний договора (0-заявление ...)

    protected $_p = array();
    public $module = ''; # plugin/module id
    protected $plgid = '';
    public $person_sysfields = array('stmt_id','ptype'); # не заносить поля в массив при чтении данных 'id',
    protected $person_datefields = array('insrbirth','insrdocdate','insdbirth','insddocdate','birth','docdate','childbirth','childdocdate');
    public $_err = array();
    private $_error = '';
    protected static $_programs = array();
    protected $my_programs = []; # локальный список программ-кодировок
    static $uw_checkers = array(); # список зарегистрированных функций для проверки на андеррайтинг (поиск застрахованного по ФИО+пасп - в старых и сегодняшн)

    # {upd/2023-12-06} новый подход - проверки на наличие других полисов в момент выпуска ЛИБО при сохранении (старый)
    public $uwCheckEvent = 'save'; # save - поиск плоисов на того же ЗВ в момент сохранения данных, nextstage - в момент отправки на след.этап

    # Список описаний плагинов, работающих с "полисами" : key=имя плагина, item = array('title'=>{название}, 'rights'=>{ID_superoper})
    # Каждый зарегистрированный полисный плагин должен иметь ф-ции uwchecker, supertools
    # public static $plc_plugins = array(); перенесен в modules.php со всеми ф-циями!

    # застрахованный, 2-й застр. (ребенок)? вернет FALSE, 'insd' или 'child' - в какой записи alf_individual данные на ребенка
    public function policyHasChild($calc=FALSE) {
        $dta = isset($calc['equalinsured']) ? $calc : $this->_rawAgmtData;
        $insured = Persons::getPersonData($dta['stmt_id'],'insd');
        $ret = FALSE;
        if( !isset($insured['id']) && isset($dta['stmt_id']) )
            $insured = Persons::getPersonData($dta['stmt_id'],'child');
        if(!empty($insured['birth'])) {
            $insdAge = \RusUtils::yearsBetween($insured['birth'], $dta['datefrom']);
            if($insdAge < PM::ADULT_START_AGE || $insured['ptype'] === 'child') $ret = $insured['ptype']; # осн.застрах-ребенок
        }

        return $ret;
    }

    public function moduleType() { return PM::MODULE_INS; }

    # возвращает режим "выгружаемости" полиса в СЭД/внеш.систему
    public function isExportable() { return $this->enable_export; }

    # получить статус - является ли плагин модулем инвестиционного страхования (надо ли подключать инвест-анкету)
    public function isInvest() { return $this->invest_mode; }
    public function hasInvestanketa() { return $this->invest_anketa; }

    public final static function moduleVersion() { return self::MODULEVERSION; }

    public function setViewButtons() {
        $this->all_buttons = include(__DIR__ . '/stdbuttons.php');
        if(method_exists($this, 'addViewButtons')) {
            $this->addViewButtons();
        }
    }
    /**
    * если на форме просмотра полиса нужны свои заголовки для групп рисков и т.д. - менять через setViewSubHeader!
    * @param mixed $headerid ИД заголовка
    * @param mixed $strg новое значение
    */
    public function setViewSubHeader($headerid, $strg) {
        self::$subheaders[$headerid] = $strg;
    }
    public function setSpecTuning($tuningKey, $value=1) {
        $this->specialTunings[$tuningKey] = $value;
    }
    public function getSpecTuning($tuningKey) {
        return ($this->specialTunings[$tuningKey]  ?? FALSE);
    }

    public function setLogPref($strg) {
        $this->log_pref = $strg;
        if(substr($strg,-1) !=='.') $this->log_pref .= '.';
        return $this;
    }
    public function getLogPref() {
        return $this->log_pref;
    }
    public function isLossBlockingProlong() {
        return $this->lossBlockingProlong;
    }
    public final function getRawPolicyData($id = 0) {
        if (!isset($this->_rawAgmtData['stmt_id']) && $id>0)
            $this->loadPolicy($id, -1);
        return $this->_rawAgmtData;
    }
    public final function getPolicyData($id = 0) {
        if (!isset($this->agmtdata['stmt_id']) && $id>0)
            $this->loadPolicy($id, -1);
        return $this->agmtdata;
    }
    public final function getRawPolicyRisks() { return $this->_rawPolicyRisks; }
    /**
    * Общая часть ф-ции получения данных в виде "для печати пакета документов"
    * (полис, правила страхования, декларация, анкеты...)
    * @param mixed $dta
    */
    public function prepareForPrintPacket(&$dta, $b_rekvizits=FALSE) {
        $zpref = 'insd';
        $module = empty($dta['module']) ? $this->module : $dta['module'];
        $codirovka = empty($dta['prodcode']) ? '' : $dta['prodcode'];
        if (!$codirovka) list($codirovka, $nomer) = explode('-', $dta['policyno']);
        if (self::$debug) WriteDebugInfo("prepareForPrintPacket KT-002, MODULE: $module, CODIR: $codirovka" );

        $baseDta = $this->getBaseProductCfg($module, $codirovka);
        # exit(__FILE__ .':'.__LINE__." baseDta($module,$codirovka) :<pre>" . print_r($baseDta,1) . '</pre>');
        # {upd/2025-09-10} если в настройки штампа задан свой ЭЦП алиас, буду использовать его!
        if(!empty($baseDta['signer_digialias'])) {
            DigiSign::setCertificateAlias($baseDta['signer_digialias']);
            # AppEnv::setUsrAppState('digisign_use_alias', $baseDta['signer_digialias']);
        }
        $this->deptProdParams($module,$this->_rawAgmtData['headdeptid'], $codirovka, $this->_rawAgmtData['b_test'], $this->_rawAgmtData['programid']);
        # writeDebugInfo("deptCfg: ", $this->_deptCfg);
        # занес данные в $this->_deptCfg

        # writeDebugInfo("getBaseProductCfg($module, $codirovka): ", $baseDta);
        if (!is_array($baseDta)) {
            $err = "Для продукта нет настроек реквизитов для печати<br>Пожалуйста, обратитесь к администратору системы";
            if(AppEnv::isApiCall()) {
                return array( 'result' => 'ERROR','message' => $err );
            }
            die($err);
        }
        if (is_array($baseDta)) $dta = array_merge($dta,$baseDta);

        # {upd/2023-04-28} дицензия банка РФ:
        $dta['lic_no'] = AppEnv::getConfigValue('comp_license_no', '3828');
        $dta['lic_date'] = AppEnv::getConfigValue('comp_license_date', '19.07.2022');

        $dta['company_email'] = AppEnv::getConfigValue('comp_email');
        $dta['product_email'] = AppEnv::getConfigValue($this->module . '_feedback_email');

        if (self::$debug) WriteDebugInfo("prepareForPrintPacket KT-110");
        $dta['title_policyholder'] = ($dta['insurer_type']==1) ? 'Страхователь:' : 'От имени Страхователя:';
        $dta['title_policyholder_address'] = ($dta['insurer_type']==1) ?
           "Адрес регистрации по месту жительства:\n\nПаспорт или заменяющий его документ:"
           : "Юридический адрес:\nРеквизиты:";

        $dta['title_policyholder'] = ($dta['insurer_type']==1) ? 'Страхователь:' : 'От имени Страхователя:';
        # exit("[$withBlocked] deptCdfg <pre>" . print_r($this->_deptCfg,1). '</pre>');

        # WriteDebugInfo("deptProdParams:,", $this->_deptCfg);

        # добавляю "видимое для партнера" Главное название программы
        # writeDebugInfo("_deptCfg : ", $this->_deptCfg);
        if (!empty($this->_deptCfg['visiblename']))
            $dta['visible_programname'] = $this->_deptCfg['visiblename'];
        elseif(method_exists($this, 'getProgramName')) $dta['visible_programname'] = $this->getProgramName($dta['programid'], $dta);
        if(empty($dta['visible_programname']) && method_exists($this, 'ProgramTitle'))
            $dta['visible_programname'] = $this->ProgramTitle($dta);

        if (!empty($dta['visible_programname'])) # цепляю кавычки
            $dta['visible_programname'] = '«' . $dta['visible_programname'] . '»';

        if ($b_rekvizits) {
            $rekv = OrgUnits::getOuRequizites($dta['headdeptid'], $this->module);
            if (!isset($rekv['our_rekvizit'])) {
                # WriteDebugInfo('error: head dept: ', $dta['headdeptid'], ', module:'.$this->module
                #  .  ', not found plat.rekv in alf_dept_properties:', $rekv);
                $errRekv = AppEnv::getLocalized('err_no_bank_rekvizit');
                AppEnv::addWarning($errRekv);

                if (AppEnv::isApiCall()) {
                    # return FALSE; # PDF полис будет сформирован, но без реквизитов "подразделения", с предупреждением в message

                    # PDF не формирую, отдаю в API признак ошибки и текст с причиной
                    return array( 'result' => 'ERROR','message' => $errRekv );

                }
                else { AppEnv::echoError($errRekv); exit; }
            }
            unset($rekv['deptid']); # {fix/2023-04-13} затиирал deptid в полисе!
            if(empty($rekv['our_rekvizit'])) {
                $rekv['our_rekvizit'] = AppEnv::getConfigValue('fo_base_payreq');
                # writeDebugInfo("our_rekvizit - беру стандартные из глоб.параметров! ", $rekv['our_rekvizit']);
            }
            $dta = array_merge($dta, $rekv);
        }

        # {upd/2025-10-10} если на заполнены плат.реквизиты в настйрое партнера, беру стандартные из глоб.параметров

        $dta['notresident'] = $dta['tax_notresident'] = '';

        if (!empty($dta['tax_rezident']) && !PlcUtils::isRf($dta['tax_rezident']))
            $dta['tax_notresident'] = 1;

        if (!$this->printdata) { //<1>

            # если договор явл. пролонгацией, ищем исходный, для полей previous_*
            if (!empty($dta['previous_id'])) {
                $prevdta = DataFind::getPolicyData($dta['previous_id'],FALSE, $this->module);
                if (isset($prevdta['policyno'])) {
                    $dta['previous_policyno'] = $prevdta['policyno'];
                    $dta['previous_datefrom'] = isset($prevdta['datefrom']) ? to_char($prevdta['datefrom']) : '';
                    $dta['previous_datetill'] = isset($prevdta['datetill']) ? to_char($prevdta['datetill']) : '';
                    $dta['previous_policyinfo'] = $prevdta['policyno'] . (!empty($prevdta['created'])?  (' от ' . to_char($prevdta['created']).'г.') : '');
                }
            }

            if ($this->isRF($dta['insrrez_country'])) {
                unset($dta['insrinopass'],$dta['insrmigcard_ser'],$dta['insrmigcard_no'],
                  $dta['insrdocfrom'],$dta['insrdoctill']
                );
            }
            else {
                # на место серии-номер рос-паспорта подставляю инопасопрт, чтоб меньше гемора на XML настройке
                $dta['insrdocno'] = $dta['insrinopass'];
                $dta['insrdocser'] = '';
                $dta['notresident'] .= 'P';
            }
            if (isset($dta['insdrez_country'])) {
                if($this->isRF($dta['insdrez_country'])) {
                    unset($dta['insdinopass'],$dta['insdmigcard_ser'],$dta['insdmigcard_no'],
                      $dta['insddocfrom'],$dta['insddoctill']
                    );
                }
                else {
                    $dta[$zpref.'docno'] = $dta[$zpref.'inopass'];
                    $dta[$zpref.'docser'] = '';
                    $dta['notresident'] .= 'Z';
                }
            }
            if(isset($dta[$zpref.'married'])) {
                $dta[$zpref.'married']==1 ? ($dta[$zpref.'married_yes']=1) : ($dta[$zpref.'married_no']=1);
            }
            if (!$this->_rawAgmtData['equalinsured'] && !empty($dta[$zpref.'rez_country']) && $this->isRF($dta[$zpref.'rez_country']))
            {
                unset($dta[$zpref.'inopass'],$dta[$zpref.'migcard_ser'],$dta[$zpref.'migcard_no'],
                  $dta[$zpref.'docfrom'],$dta[$zpref.'doctill']
                );
            }

            $dta['checkbox_yes'] = 1; # поле "всегда включ"
            if (isset($dta['insrrez_country']) && is_numeric($dta['insrrez_country'])) {
                if ($dta['insrrez_country'] === self::NOT_RUSSIA) $dta['insrrez_country'] = ''; # 999-не выводим ничего
                else $dta['insrrez_country'] = PlcUtils::decodeCountry($dta['insrrez_country']);
            }
            if (isset($dta[$zpref.'rez_country']) && is_numeric($dta[$zpref.'rez_country'])) {
                if (!$this->isRf($dta[$zpref.'rez_country'])) $dta['notresident'] .= 'Z';
                if ($dta[$zpref.'rez_country'] === self::NOT_RUSSIA) $dta[$zpref.'rez_country'] = ''; # 999-не выводим ничего
                else $dta[$zpref.'rez_country'] = PlcUtils::decodeCountry($dta[$zpref.'rez_country']);
            }
            if (isset($dta['tranchedate'])) $dta['tranchedate'] = to_char($dta['tranchedate']);

            # Делаю ФИО страхователя, застрах
            if($dta['insurer_type'] == 1) {
                $dta['pholder_fio'] = $dta['pholder_fio_fl'] = $dta['ul_signer_fio'] = RusUtils::MakeFio($dta['insurer_fullname']);
                $dta['insurer_lastname'] = $dta['insrfam'];
                $dta['insurer_name'] = $dta['insrimia'];
                $dta['insurer_patrname'] = $dta['insrotch'];
                # insurer_name, _lastname, _patrname, адрес - для анкеты согласия ПД и аналогичных
                if ($dta['equalinsured']) $dta['insured_fio'] = $dta['pholder_fio'];
                $dta['insrmarried']==1 ? ($dta['insrmarried_yes']=1) : ($dta['insrmarried_no']=1);
            }
            if (empty($dta['insured_fio'])) $dta['insured_fio'] = RusUtils::MakeFio($dta['insured_fullname']);
            if (!empty($dta[$zpref.'birth'])) {
                $iAge = DiffDays($dta[$zpref.'birth'], date('Y-m-d'),1);
                if($iAge[0]<18) $dta['insured_fio'] = '';
                # ребенок не будет подписантом
            }
        } // <1>

        $plcdate =  $dta['date_sign'] = to_char($this->getDateSign($dta));
        $dta['policyno_date'] = "№ $dta[policyno] от $plcdate г.";

        $dta['title_insurer_phones_email'] = 'Телефон/Email'; # заголовок под блок тел+email
        $dta['insr_phones_email'] = plcUtils::buildAllPhones('insr', $dta, TRUE);

        if (!empty($dta['insremail'])) $dta['insr_phones_email'] .= ' / '.$dta['insremail'];
        # exit("insr_phones_email: " .  $dta['insr_phones_email']);
        if (!empty($dta['currency'])) # полное название валюты
            $dta['currency_name'] = PlcUtils::getCurrencyFull($dta['currency']);

        if (!empty($this->specCond)) $this->addSpecConditionsText($dta, $this->specCond); # было policy_speccond

        if (!empty($dta['b_test'])) $dta['demofld'] = 'ТЕСТОВЫЙ';
        if (self::$debug>1) WriteDebugInfo("policymodel: prepareForPrintPacket, final data:", $dta);
        if (!empty($this->_rawAgmtData['dt_sessign']) && intval($this->_rawAgmtData['dt_sessign'])>0) {
            $dta['sessign_date'] = to_char($this->_rawAgmtData['dt_sessign']);
            $dta['sessign_time'] = substr($this->_rawAgmtData['dt_sessign'], 11,5);
        }
        $dta['insurername'] = $dta['insurer_fullname'];
        $dta['oferta_date'] = $dta['datesign'] = $dta['created'];
        $dta['fullagentname'] = GetDeptName($dta['headdeptid']);
        $dta['raw_policyno'] = $dta['policyno'];
        $dta['currency_rus'] = PlcUtils::decodeCurrency($dta['currency_raw'],1);
        $dta['title_insurer_phones_email'] = "Телефон/Email";
        # {upd/2023-05-15} чищу поля ФЛ страхователя, если у нас ЮЛ
        if($dta['insurer_type'] == 2) {
            unset($dta['insremail'], $dta['insrphone'],
              $dta['insrphone2'], $dta['insrfulldoc'], $dta['insrfulladdr']);
        }
        PlcUtils::getUlSignerBlock($dta); # {upd/2022-02-03} блок подписанта от ЮЛ страхователя
        # exit(__FILE__ .':'.__LINE__.' data after getUlSignerBlock:<pre>' . print_r($dta,1) . '</pre>');
        return TRUE;
    }

    public function getAnketaCompLimit($dta=0) {
        if($this->lifeIns === 'ns') $ret = AppEnv::getConfigValue('ns_clientanketa_limit', PlcUtils::$nsAnketaLimit);
        else /*if($this->lifeIns === 'life')*/ $ret = AppEnv::getConfigValue('life_clientanketa_limit', PlcUtils::$clientAnketaLimit);
        return $ret;
    }
    /**
    * Добавляем все анкеты
    *
    * @param mixed $moda : Z  -идет печать заявления, P - печать полиса. '*' - выдать анкеты страх-ля(+застрах) независимо от настройки депт-программа
    * @param mixed $dta
    * @param mixed $skipCB - признак отключения памятки ЦБ(есть КИД): TRUE|1 = памятку ЦБ не печатаем (в банковских это не так!)
    */
    public function addAllAnketas($moda, &$dta, $skipCB=FALSE) {
        # writeDebugInfo("addAllAnketas($moda, skipCB=[$skipCB]): _deptCfg = ", $this->_deptCfg, "  \t trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        # echo "addAllAnketas $moda <pre>" . print_r($this->_deptCfg,1). '</pre>'; exit;
        # anketaOptions - должна вернуть массив флагов "необходимости вывода" анкеты: 'anketa_insr'=>1 (для страхователя)
        if (method_exists($this, 'anketaOptions')) $ankOpts = $this->anketaOptions();
        else $ankOpts = FALSE;
        $doAnketa = $this->print_anketa;

        $metaType = $dta['metatype'] ?? $this->_rawAgmtData['metatype'] ?? '';
        AnketaPrint::loadSignerData($dta, $metaType);

        $premTotal = $dta['premtotal'] ?? $this->getTotalPremium($dta,TRUE);
        if(floatval($premTotal) <= floatval($dta['policy_prem']) && $dta['rassrochka']>0) {
            # неверный общий взнос, принудительно пересчитываю!
            $premTotal = $this->getTotalPremium($dta,TRUE);
        }
        if($premTotal<=$dta['policy_prem'] && $dta['currency'] ==='USD') {
            $premTotal = $this->getTotalPremium($dta,TRUE);
        }
        # {upd/2024-06-17} надо сравнить суммарный взнос с 15000/40000 - если не достиг, анкета не нужна (А.Загайнова)

        $anketaLimit = $this->getAnketaCompLimit($dta['programid']);

        $compAnketas = ( ($anketaLimit > 0 && floatval($premTotal) >= floatval($anketaLimit)) || $dta['insurer_type']==2 );
        # {upd/2025-04-02} Для ЮЛ страхователя анкеты всегда, независимо от лимитов

        if ($doAnketa === 'LIMIT') {
            # {upd/2021-02-19} - вывожу анкету только при превышении суммарным взносом лимита PlcUtils::$clientAnketaLimit (15000р.)
            $doAnketa = $compAnketas; # $clientAnketaLimit
        }
        # if(SuperAdminMode()) exit(__FILE__ .':'.__LINE__."moda=$moda, doAnketa=[$doAnketa], compAnketas=[$compAnketas] data:<pre>" . print_r($dta,1) . '</pre>');

        # exit(__FILE__ .':'.__LINE__."limit=$anketaLimit, doAnketa=[$doAnketa]".' data:<pre>' . print_r($dta,1) . '</pre>');
        # if(SuperAdminMode()) exit (__LINE__." premTotal=[$premTotal] anketaLimit=[$anketaLimit] compAnketas = [$compAnketas] doAnketa=[$doAnketa]");

        $anketa_output = $this->_deptCfg['anketa_output'] ?? '';
        $b_clientanketa = $this->_deptCfg['b_clientanketa'] ?? 0;
        $anketa_insr = $this->_deptCfg['anketa_insr'] ?? 0;
        $b_clientanketaul = $this->_deptCfg['b_clientanketaul'] ?? 0;

        $nonDeathRisks = $this->existNonDeathRisks(); # есть риски не-смерти

        # exit(__FILE__ .':'.__LINE__."/tot.Prem($totalPrem) >= limit($ankLimit) output=[$anketa_output]/$moda, doAnketa=[$doAnketa]");
        # exit(__FILE__ .':'.__LINE__." doAnketa=[$doAnketa] anketa_output=$anketa_output, data:<pre>" . print_r($this->_rawAgmtData,1) . '</pre>');
        if ($doAnketa && (($moda === '*' || $anketa_output===$moda) || ($anketa_output==='Z' && $moda==='P' && !$this->hasStatement($dta))) ) { #
            # {updt/2025-02-04} - если выод анкет назначен в заяву, но заяв в пролукте нет, печатаю в полис!
            if ($this->debugAnk) WriteDebugInfo("$moda: надо выводить анкеты ФЛ,ЮЛ страх-застрах.");
            if($this->_rawAgmtData['insurer_type']==1 && !empty($b_clientanketa)) {
                # надо выводить анкету страхователя-ФЛ
                $b_ank = (isset($ankOpts['anketa_insr']) ? $ankOpts['anketa_insr'] : TRUE);
                if ($b_ank) {
                    if ($this->debugAnk) WriteDebugInfo("надо выводить анкету страхователя-ФЛ, this->pholder: ", $this->pholder);
                    $xmlName = $this->prepareAnketaData($dta, $this->pholder, 'anketa_insr');
                    if ($this->debugAnk) WriteDebugInfo("имя XML ФЛ анкеты: '$xmlName'");

                    if ($xmlName) {
                        # TODO:EDO нужны ли -EDO версии шаблонов анкет? если да, раскомментировать строку ниже.
                        $xmlName = PlcUtils::getTemplateEDO($xmlName);
                        $edo = PlcUtils::isPrintEdoMode();
                        # exit("anketa for insurer(edo=[$edo]: " .$xmlName);
                        PM::$pdf->AppendPageDefFromXml($xmlName, 'anketa_insr');
                    }
                }
            }
            elseif($this->_rawAgmtData['insurer_type']==2 && !empty($b_clientanketaul)) {
                # надо выводить анкету страхователя-ЮЛ
                # echo "ankOpts <pre>" . print_r($ankOpts,1) . '</pre>'; exit;
                $b_ank = (isset($ankOpts['anketa_insr']) ? $ankOpts['anketa_insr'] : TRUE);
                # exit ("UL: b_ank = [$b_ank] <pre>". print_r($ankOpts,1).'</pre>');
                if ($b_ank) {
                    if ($this->debugAnk) WriteDebugInfo("anketa_insr: надо выводить анкету страхователя->ЮЛ, this->pholder: ", $this->pholder);
                    $xmlName = $this->prepareAnketaDataUL($dta, $this->pholder, 'anketa_insr');
                    $xmlName = PlcUtils::getTemplateEDO($xmlName);
                    if ($this->debugAnk) WriteDebugInfo("имя XML ЮЛ  анкеты: '$xmlName'");
                    if ($xmlName) PM::$pdf->AppendPageDefFromXml($xmlName, 'anketa_insr');
                }
            }

            # writeDebugInfo("ankOpts ", $ankOpts);
            # {upd/2021-02019} - вообщн отключаю вывод анкеты Компланс (anketa-fl) для Застрахованного (А.Шатунова)
            # {upd/2021-06-08 - Шатунова !"не помнит, чтобы такое говорила", по треб.Ю.Кузнецовой вкл.обратно!
            if (!$this->nonlife && ($this->_rawAgmtData['insurer_type']>1 || !$this->_rawAgmtData['equalinsured']) && $this->insured_adult) {
                # Застрахованный - не совпал со страх-лем
                # WriteDebugInfo("Застрахованный! this->insured:",$this->insured[0]);
                $b_ank = TRUE; # TODO: = $doAnketa - если решат отключить анкету Застрах-го при премии < 15000

                if($nonDeathRisks) $b_ank = FALSE;
                # writeDebugInfo("nonDeathRisks=[$nonDeathRisks], bAnk=[$b_ank] если есть риски НЕ-смерти, для Застрахованного есть анкета ВП -> анкету ФЛ не надо");

                if (isset($ankOpts['anketa_insd']) && $ankOpts['anketa_insd'] ==0) $b_ank = FALSE;
                if ($b_ank) {
                    if ($this->debugAnk) WriteDebugInfo("надо выводить анкету Застрахованного ФЛ, this->insured: ", $this->insured[0]);
                    if (!empty($this->insured[0])) {
                        $xmlName = $this->prepareAnketaData($dta, $this->insured[0], 'anketa_insd');
                        $xmlName = PlcUtils::getTemplateEDO($xmlName);
                        # WriteDebugInfo("insured FL anketa : ", $xmlName);
                        PM::$pdf->AppendPageDefFromXml($xmlName, 'anketa_insd');
                    }
                }
            }
        }
        # benefs:

        # echo('_rawBenefs <pre>' . print_r($this->_rawBenefs,1). '</pre>');
        # exit('_rawcBenefs <pre>' . print_r($this->_rawcBenefs,1). '</pre>');

        if ( $doAnketa && $anketa_output===$moda ) { # $compAnketas

            $deathRisks = $this->existDeathRisks();
            # writeDebugInfo("equalinsured: " . ($dta['equalinsured'] ?? 'N/A'));
            # writeDebugInfo("nonDeathRisks: ", $nonDeathRisks);

            if(empty($dta['equalinsured']) && $nonDeathRisks) {
                # надо напечатать анкету ВП для Застрахованного не равного страхователю, т.к. он - ВП по несмертям (инвалидности, КЗ...)
                # exit(__FILE__ .':'.__LINE__.' add Benef anketa for insured:<pre>' . print_r($dta,1) . '</pre>');

                if(isset($dta['main_insured']) && $dta['main_insured'] == 'child' && empty($dta['b_insured2'])) {
                    # страхователь и один застрах - ребенок, анкета ВП не нужна, т.к. ВП
                    # $deleg = $dta['child_delegate'] ?? '?';
                    # exit(__FILE__ .':'.__LINE__."$deleg ,  data:<pre>" . print_r($this->_rawcBenefs,1) . '</pre>');
                    # if($deleg === 'N') # ЗПЗР -
                    #    $xmlName = AnketaPrint::prepareBenefs($dta, $this->_rawcBenefs);                }
                    if(!empty($dta['insd_fam']) || !empty($dta['insd_fullname'])) {
                        # exit(__LINE__ . ":KT-001 - Вывод анкеты ВП на ЗВ");
                        $xmlName = AnketaPrint::prepareBenefs($dta, 'insd');
                    }
                    # else exit("NO NEED VP anketa for Adult Insured");
                }
                else {
                    # {upd/2024-11-18} для отдельного взрослого ЗАстрах-ного вывожу его в анкету ВП (не "Мой капитал")
                    if(!empty($dta['insd_fam']) || !empty($dta['insd_fullname'])) {
                        $xmlName = AnketaPrint::prepareBenefs($dta, 'insd');
                        # exit(__LINE__ . ":KT-002 - Вывод анкеты ВП на ЗВ");
                    }
                    # else exit("NO NEED VP anketa for Adult Insured");
                }
            }

            $allBenefs = $this->_rawBenefs;

            if(!empty($this->_rawcBenefs['fullname']))
                $allBenefs[] = $this->_rawcBenefs;
            # {upd/2023-06-15} Теперь анкеты ВП будут и на представителя ЗР
            # if(SuperAdminMode()) exit(__FILE__ .':'.__LINE__.' $allBenefs:<pre>' . print_r($allBenefs,1) . '</pre>');
            if($doAnketa && count($allBenefs)>0) {
                $xmlName = AnketaPrint::prepareBenefs($dta, $allBenefs);
                /*
                if ($xmlName && is_file($xmlName)) {
                    $xmlName = PlcUtils::getTemplateEDO($xmlName);
                    PM::$pdf->AppendPageDefFromXml($xmlName);
                }
                */
            }
        }

        # {upd/2020-02-03} - памятку ЦБ вывожу ПЕРЕД опросным FATCA (только в полис!)
        # writeDebugInfo("cb_memo: [{$this->cb_memo}], skipCB=[$skipCB], moda=[$moda]");
        if ( !$skipCB && $moda==='P' && $this->cb_memo) {
            $xmlCbMemo = CbMemo::prepareMemoData($this->module, $dta,'', $this->finplan);
            if($xmlCbMemo) {
                $xmlCbMemo = PlcUtils::getTemplateEDO($xmlCbMemo);
                PM::$pdf->AppendPageDefFromXml($xmlCbMemo);
            }
        }
        # exit(__FILE__ .':'.__LINE__." moda=$moda data:<pre>" . print_r($this->_deptCfg,1) . '</pre>');
        if (!empty($this->_deptCfg['opros_output']) && $this->_deptCfg['opros_output']===$moda) { # опросный лист (FATCA)
            # {upd/2022-03-04} - для отд.продуктов опр.лист может отключаться - это выдаст orposListNeeded() (А.Абрамова)
            $insurType = $this->getInsuranceType();
            if(method_exists($this, 'oprosListNeeded')) {
                $doOprosList = $this->oprosListNeeded();
            }
            else {
                $doOprosList = in_array($insurType, [PM::PRODTYPE_NSJ, PM::PRODTYPE_INVEST]); # по заявке R-247046
            }

            # exit("insurType=[$insurType], oprosListNeeded= [$doOprosList]"); # смотрю как сформировался флаг вывода опрос-листа FATCA

            if ($doOprosList) {
                $pref = ($this->_rawAgmtData['insurer_type']==1) ? 'fl' : 'ul';
                $pref2 = ($this->_deptCfg['opros_list'] =='1') ? '': '-'. ($this->_deptCfg['opros_list']);
                $oplistXml = AppEnv::getAppFolder("templates/anketa/") . "oplist-$pref{$pref2}.xml";
                if ($this->debugAnk) WriteDebugInfo("Полис, Опросный лист, ищу файл $oplistXml");
                if (is_file($oplistXml)) {
                    $this->prepareAnketaData($dta, $this->pholder, 'fatca');
                    $oplistXml = PlcUtils::getTemplateEDO($oplistXml);
                    PM::$pdf->AppendPageDefFromXml($oplistXml, 'fatca');
                }
                elseif($this->debugAnk) WriteDebugInfo("$oplistXml - файл XML не найден");
                # А теперь для застрахованного, который всегда - ФЛ (если застр. != страхователь)
                if ($this->_rawAgmtData['equalinsured']==0 && !empty($this->insured['fam'])) {

                    $pref2 = ($this->_deptCfg['opros_list'] =='1') ? '': '-'. ($this->_deptCfg['opros_list']);
                    $oplistXml = 'templates/anketa/' . "oplist-insured{$pref2}.xml";
                    if ($this->debugAnk) WriteDebugInfo("Опросный лист для застрах, ищу файл $oplistXml");
                    if (is_file($oplistXml)) {
                        $oplistXml = PlcUtils::getTemplateEDO($oplistXml);
                        PM::$pdf->AppendPageDefFromXml($oplistXml);
                    }
                }
            }
        }
        # else exit("FATCA - turned OFF in cfg!"); # debug PITSTOP

        # анкета налогового резидента, добавляется если страхователь - не нлг-резидент РФ
        # writeDebugInfo("block_tax_rezident=[$this->block_tax_rezident], dta= ", $dta);

        $taxNotRez = $dta['tax_notresident'] ?? -1;
        if($taxNotRez === -1 && isset($dta['tax_rezident']))
            $taxNotRez = !PlcUtils::isRF($dta['tax_rezident']);


        # if(SuperAdminMode()) exit(__FILE__ .':'.__LINE__." taxNotRez=[$taxNotRez] for tax-rezident: data:<pre>" . print_r($dta,1) . '</pre>');
        if ($this->block_tax_rezident && $anketa_output === $moda) {
            $benNotRez = Persons::BenefNotRusExists($this, $dta); # один ВП-налоговый нерезидент
            if(($dta['insurer_type']==1 && $taxNotRez) || $benNotRez) {
                $this->addAnketaNotRezident($dta, $benNotRez);
            }
        }
    }

    /**
    *  добавляю одну анкету нал.резидента для переданных данных человека, налогового НЕрезидента
    * @param mixed $dta куда доливаем подмассив данных для листа анкеты
    * @param mixed $subj - ИД подмассива (уникальный, иначе затрёт предыдущего!)
    * @param mixed $benefdata ассоц.массив данных одного ФЛ (fam, imia, otch...)
    */
    public function addAnketaNotRezident(&$dta, $benNotRez=FALSE) {
        $taxXml = AppEnv::getAppFolder('templates/anketa/') . 'anketa-taxresident.xml';
        # $tax_rezident = $dta['tax_rezident'] ?? $this->calc['tax_rezident'] ?? $this->ins_params['tax_rezident'] ?? '???';
        # exit("tax_rezident=$tax_rezident");
        # $phNotRez = (empty($tax_rezident) || !plcUtils::isRF($tax_rezident)) ? $this->pholder : FALSE;
        # FALSE ИЛИ массив данных страхователея - налогового нерезидента

        # FALSE ИЛИ массив данных [0..n] ВП - налогового нерезидента
        if (is_file($taxXml)) {
            # $this->prepareAnketaData($dta, $this->pholder, '');
            # добавляю поля ВП-налогового нерезидента для анкеты нал/рез.
            # writeDebugInfo("добавляю поля налогового нерезидента");

            $taxNotRez = $dta['tax_notresident'] ?? -1;
            if($taxNotRez === -1 && isset($dta['tax_rezident']))
                $taxNotRez = !PlcUtils::isRF($dta['tax_rezident']);

            $pholderInfo = $taxNotRez ? $this->pholder : FALSE; # если Стра-ль - нал.резидент, в анкете Н.рез его не выводим
            AnketaPrint::AddTaxRezidentData($dta,$pholderInfo, $benNotRez);
            $taxXml = PlcUtils::getTemplateEDO($taxXml);
            PM::$pdf->AppendPageDefFromXml($taxXml);
            # echo 'to print tax_rezident!:<pre>'.print_r($dta,1) . '</pre>'; exit;
        }
    }

    # должна проверить все параметры и если что, вернуть код причины постановки "на андеррайтинг"
    # public function checkUnderwritingConditions($data) { return FALSE; }
    # 2018-07-16 перенес полную порверку из plg_kpp в общий модуль ! (Грузенкина - не произошла проверка на полисах Гар.Классик)
    public function checkUnderwritingConditions($data) {
    }

    /**
    * получение пакета данных для печати заявления
    */
    public function prepareForPrintStmt(&$dta) {
        $dta['checkbox_yes'] = 1; # поле "всегда включ"
        $codirovka = empty($dta['prodcode']) ? '' : $dta['prodcode'];
        if (!$codirovka) list($codirovka, $nomer) = explode('-', $dta['policyno']);

        $baseDta = $this->getBaseProductCfg($this->module, $codirovka);
        # writeDebugInfo("getBaseProductCfg($module, $codirovka): ", $baseDta);
        if (!is_array($baseDta)) {
            $err = "Для продукта нет настроек реквизитов для печати<br>Пожалуйста, обратитесь к администратору системы";
            if(AppEnv::isApiCall()) {
                return array( 'result' => 'ERROR','message' => $err );
            }
            die($err);
        }

        $dateSign = to_char($dta['created']);

        if( !empty($dta['date_release']) && PlcUtils::isDateValue($dta['date_release']) )
            $dateSign = to_char($dta['date_release']);

        # echo "<pre>dateSign = $dateSign </pre>"; exit; # PITSTOP
        $dta['datesign'] = $dta['issuedate'] = $dateSign;

        if (!empty($this->_rawAgmtData['created'])) {
            # $datesign = min($this->_rawAgmtData['created'], $this->_rawAgmtData['datefrom']);
            $datesign = $this->getDateSign($this->agmtdata);

            $dta['date_sign_verbose'] = AppEnv::dateVerbose($datesign,1);
            $dta['date_sign'] = to_char($datesign); # {upd/2022-06-29}
        }
        $dta = array_merge($dta, $baseDta);
        $dta['company_email'] = AppEnv::getConfigValue('comp_email');
        $dta['product_email'] = AppEnv::getConfigValue($this->module . '_feedback_email');

        if (!empty($this->_deptCfg['visiblename']))
            $dta['visible_programname'] = $this->_deptCfg['visiblename'];
        elseif(method_exists($this, 'getProgramName')) $dta['visible_programname'] = $this->getProgramName($dta['programid'], $dta);
        if(empty($dta['visible_programname']) && method_exists($this, 'ProgramTitle'))
            $dta['visible_programname'] = $this->ProgramTitle($dta);
        # $ouRekv = OrgUnits::getOuRequizites($dta['headdeptid'], $this->module);

        $meta = $this->loadSpecData($dta['stmt_id']); # $meta['spec_params']['predstype']
        if(!empty($meta['spec_params'])) $dta = array_merge($dta, $meta['spec_params']);
        # if(SuperAdminMode()) exit(__FILE__ .':'.__LINE__.' dta:<pre>' . print_r($dta,1) . '</pre>');

        if (!empty($dta['fullstamp'])) {
            PM::$pdf->setFieldAttribs('stamp', array('src'=>$dta['fullstamp']));
            # WriteDebugInfo("full stamp image from prod cfg:", $dta['fullstamp']);
        }
        if($dta['insurer_type'] == 1) {
            $dta['pholder_fio'] = RusUtils::MakeFio($dta['insurer_fullname']);
            $dta['insr_allphones'] = PlcUtils::buildAllPhones('insr', $dta); # полная строка со всеми телефонами
            # {upd/2021-09-07} добавляю СНИЛС с заголовком, для форм где нет готового поля!
            if (!empty($dta['insrsnils'])) $dta['insr_fullsnils'] = 'СНИЛС: ' . $dta['insrsnils'];
        }

        if(!empty($dta['insured_fullname']))
           $dta['insured_fio'] = RusUtils::MakeFio($dta['insured_fullname']);

        $datesign = to_char($this->getDateSign($dta));
        $dta['policyno_date'] = '№ ' . $dta['policyno'] . " от $datesign г.";
        # {updt/2021-10-29} fix: не было формирования № предыд.полиса!
        if (!empty($dta['previous_id'])) {

            if (is_numeric($dta['previous_id'])) {
                $prevdta = DataFind::getPolicyData($dta['previous_id'], FALSE, ($this->module ?? $dta['module']));
                if (isset($prevdta['policyno'])) {
                    $dta['previous_policyno'] = $prevdta['policyno'];
                }
            }
            else $dta['previous_policyno'] = $dta['previous_id'];
        }
        PlcUtils::getUlSignerBlock($dta); # {upd/2022-02-08} 'ul_signer_block' - блок подписанта от ЮЛ страхователя
        $dta['insurername'] = $dta['insurer_fullname'];
        $dta['insrphones'] = PlcUtils::buildAllPhones('insr', $dta, TRUE);
        # {upd/2023-05-29} - если еще не проставили соотв-вие декларации, вывожу на заяву ЧЕРНОВИК (А.Загайнова)
        # {ups/2023-09-15} - всем кроме банк.канала!
        $metaTp = $dta['metatype'] ?? '';
        # {upd/2023-10-30} предзаполняю атрибут мед-декларации - да-нет
        # if(in_array($this->_rawAgmtData['reasonid'], PM::$noDeclarReasons))
        if(in_array($dta['reasonid'], PM::$noDeclarReasons))
            $dta['med_declar_no'] = 1;
        elseif(!empty($this->_rawAgmtData['med_declar'])) {
            if($this->_rawAgmtData['med_declar'] === 'Y') $dta['med_declar_yes'] = 1;
            elseif($this->_rawAgmtData['med_declar'] === 'N') $dta['med_declar_no'] = 1;
        }
        $allReasons = $dta['calc_uwreasons'] ?? $dta['all_uwreasons'] ?? $dta['reasonid'];
        if(is_string($allReasons)) $allReasons = explode(',', $allReasons);
        if(!in_array($dta['reasonid'], $allReasons)) $allReasons[] = $dta['reasonid'];
        # Если поле соотв.мед-декларации не заполнено, включаю вывод ЧЕРНОВИК
        # {upd/2025-12-04} если хоть один из кодов UW в списке $noDeclarReasons - слово ЧЕРНОВИК не вывожу
        # (на UW надо подавать заявл-е подписанное клиентом, т.е. не черновик!)
        $intercept = array_intersect($allReasons, array_merge(PM::$noDeclarReasons, PM::$calcReasons));
        $dta['blocking_reasons'] = $intercept;
        if($dta['stateid']<PM::STATE_UNDERWRITING && count($intercept)==0
          && empty($dta['med_declar'])  && ($metaTp != \OrgUnits::MT_BANK)) {
            $dta['demofld'] = 'ЧЕРНОВИК';
        }

        if(!empty($dta['insdfam']))
            $dta['insdphones'] = PlcUtils::buildAllPhones('insd', $dta, TRUE);
        if(!empty($dta['childfam']))
            $dta['childphones'] = PlcUtils::buildAllPhones('child', $dta, TRUE);

        # exit(__FILE__ .':'.__LINE__.' prepareForPrintStmt data:<pre>' . print_r($dta,1) . '<br>all_resons: '.print_r($allReasons,1). '</pre>');
    }

    public static final function getInstance($classname) {
        $clid = strtolower($classname);
        return (isset(self::$_instance[$clid]) ? self::$_instance[$clid] : FALSE);
    }
    # Должен вернуть список ИД полисов, готовых для выгрузки (accepted=1, export_pkt='')
    # abstract public function getAgreementListForExport($pktid=0);

    public function __construct($param = FALSE) {
        $this->setViewButtons();
        if(empty($this->log_pref)) $this->log_pref = strtoupper($this->module) . '.';
        if ($param) {
            if (is_string($param)) $this->module = trim($param);
        }
        $this->right_handpno = $this->module . PM::RIGHT_POLICYNO;
        if($this->b_childBenef)
            $this->addSpecFields( ['child_delegate','cbenefrelate']);

        if($this->b_childBenef == 2)
            $this->addSpecFields( ['cbenefdocconfirm']);

        if($this->clientBankInfo) { # {upd/2025-04-08} поля блока данных о банке клиента
            $this->addSpecFields( ['client_bankname','client_bankbic','client_corracc','client_persacc','client_accowner']);
        }

        if ($this->ul_enabled == 2) { # поля доп.данных для "Большого ЮЛ"
            $this->addSpecFields( [
              'ul_bankname','ul_bankbik','ul_bankrs','ul_bankks','ul_head_duty','ul_head_name','ul_osnovanie',# 'ul_regdate', 'ul_regplace',
              'ul_contact_fio','ul_contact_birth','ul_contact_sex','ul_contact_address','ul_contact_phone','ul_contact_email',
            ] );
            # writeDebugInfo("added UL dop info spec");
        }
        elseif ($this->ul_enabled == 3) { # поля доп.данных для подписанта ЮЛ
            $this->addSpecFields( [
              'ul_signer_name','ul_signer_duty','ul_signer_dovno','ul_signer_dovdate'
            ] );
        }
        if ($this->income_sources) {
            # Ис точники доходов страхователя-ФЛ
            $this->addSpecFields(['income_source_work','income_source_social','income_source_business','income_source_finance',
              'income_source_realty','income_source_other','income_descr'
            ]);
            # {upd/2024-06-18} и источники для страхователя-ЮЛ
            $this->addSpecFields(['ul_income_source_commerce','ul_income_source_loan','ul_income_source_realty',
              'ul_income_source_other','ul_income_descr'
            ]);
        }
        if($this->ul_enabled == 3) # 3 = ЮЛ с подписантом от компании {upd/2024-06-11} - bugFix, было ПРИСВАИВАНИЕ ul_enabled = 3 !!!
            $this->addSpecFields(['ul_signer_name','ul_signer_duty','ul_signer_dovno','ul_signer_dovdate']);
        if($this->in_anketa_client) # вводятся данные для анкеты клиента
            $this->addSpecFields(['under_bankrot','deesposob_limited','deesposob_limited_reason']);
        # {upd/2024-05-03}
        if($this->insured_flex)
            $this->addSpecFields(['child_delegate','cbenefrelate','cbenefdocconfirm']);
    }

    public function addSpecFields($arr) {
        if(is_string($arr)) $arr = explode(',', $arr);
        foreach($arr as $elem) {
            if (!in_array($elem, $this->spec_fields))
                $this->spec_fields[] = $elem;
        }
    }

    public function updateUI() {
        AppEnv::addSubmenuItem('mnu_utils','seek_uwinfo','mnu_seek_uwinfo','./?p=seekuwinfo');
        # AppEnv::addSubmenuItem('mnu_utils','plc_tools','mnu_plc_tools','./?p=policytools');
        #       addSubmenuItem($menuid, $itemid, $title, $href='', $onclick='', $submenu=FALSE)
    }
    # Задает риски, которые выводить в выпадающий список выбора риска по выгодоприобретателю, [и по застрах.ребенку]
    public function setBenefRisks($risks, $childrisks=0) {
        $this->benefRisks = $risks;
        $this->cbenefRisks = is_array($childrisks) ? $childrisks : array();
    }
    public static function decodeDocType($typeid) {
        switch($typeid) {
            case 0 : case 1:  return 'Паспорт РФ';
            case 2 : return 'Свидетельство о рождении';
            case 3 : return 'Военный билет';
            case 4 : return 'Загранпаспорт';
            case 6 : return 'Паспорт иностр.гражд.';
            case 20: return 'Миграционная карта';
            case 11: return 'Свидетельство о регистрации';
            case 99: return 'Иной документ';
        }
        return "NA[$typeid]";
    }
    public function getErrorMessage() {
        if (!empty($this->_error)) return $this->_error;
        if(is_array($this->_err)) return implode('<br>', $this->_err);
    }
    public static function init() {
        # гружу из настроек внутр.курс USD, значения лимитов
        if (($rate = AppEnv::getConfigValue('intrate_usd'))) self::$int_rate['USD'] = $rate;
        if (($val = AppEnv::getConfigValue('ins_limit_death_rur'))) self::$sa_limits['death']['RUR'] = $val;
        if (($val = AppEnv::getConfigValue('ins_limit_death_usd'))) self::$sa_limits['death']['USD'] = $val;

        # {upd/2019-07-16} - добавляем глоб.лимит СС по риску смерти ЗР
        if (($val = AppEnv::getConfigValue('ins_limit_child_death_rur'))) self::$sa_limits['child_death']['RUR'] = $val;
        if (($val = AppEnv::getConfigValue('ins_limit_child_death_usd'))) self::$sa_limits['child_death']['USD'] = $val;

        if (($val = AppEnv::getConfigValue('ins_limit_child_rur'))) self::$sa_limits['child']['RUR'] = $val;
        if (($val = AppEnv::getConfigValue('ins_limit_child_usd'))) self::$sa_limits['child']['USD'] = $val;
        # WriteDebugInfo('actual NS limits:',self::$sa_limits);
    }
    # @since 2022-10-20 получаю уровень "редакторских"|отчетных (reports)прав юзера в модуле
    public function getUserLevel($operation='oper') {
        if($operation === 'oper') {
            if($this->_userLevel === NULL || $this->_userLevel===FALSE) {
                if(empty($this->privid_editor)) {
                    # writeDebugInfo("Исправляю пустой ID права редактора в {$this->module} !");
                    $this->privid_editor = $this->module . '_oper';
                }
                $this->_userLevel = AppEnv::$auth->getAccessLevel($this->privid_editor);
                if(self::$debug>3) writeDebugInfo("find priv for {$this->privid_editor} = [$this->_userLevel]");
                # if(self::$debug>1) writeDebugInfo("trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
            }
            # else writeDebugInfo("current _userLevel: [$this->_userLevel], type: ", gettype($this->_userLevel));
            return $this->_userLevel;
        }
        else { # $operation = 'reports'
            if(empty($this->privid_reports)) {
                # writeDebugInfo("Исправляю пустой ID права редактора в {$this->module} !");
                $this->privid_reports = $this->module . '_reports';
            }
            $ret = AppEnv::$auth->getAccessLevel($this->privid_reports);
            return $ret;
        }
    }
    public function isCompliance() {
        if($this->_compliance === NULL)
            $this->_compliance = AppEnv::$auth->getAccessLevel(PM::RIGHT_COMPLIANCE);

        return $this->_compliance;
    }
    public function isAdmin() {
        if ($this->getUserLevel()>=5) return TRUE; # начиная с андеррайтера - админ!
        return (AppEnv::$auth->getAccessLevel([$this->privid_super, PM::RIGHT_SUPEROPER, PM::RIGHT_SUPERADMIN]));
    }
    public function isICOfficer($exact=FALSE) { # сотрудник стр.компании и выше
        if($exact) return ($this->getUserLevel()==PM::LEVEL_IC_ADMIN);
        if ($this->getUserLevel()>=PM::LEVEL_IC_ADMIN) return TRUE;
        return (AppEnv::$auth->getAccessLevel([$this->privid_super, PM::RIGHT_SUPEROPER, PM::RIGHT_SUPERADMIN]));
    }

    # Сотрудник стр.компании?
    public function isICAdmin($exact=FALSE) {
        if($exact) return ($this->getUserLevel()==4);
        return ($this->getUserLevel()>=4);
    }
    # андеррайтер? {upd/2021-01-20} - дал супер-админу UW полномочия
    public function isUnderWriter($exact = FALSE) {
        if ($exact) return (PM::LEVEL_UW == $this->getUserLevel());
        if(SuperAdminMode()) return TRUE;
        return (PM::LEVEL_UW == $this->getUserLevel());
    }

    # для CRUD-грида astedit/alf_agreements :
    public function viewAgmtState($stateid, $fullrow=0) {

        global $ast_datarow;
        # {upd/202-10-20} простой юзер (агент,менеджер) не видит "отклонено андеррайтером" - просто "на UW"
        if($this->getUserLevel() <= PM::LEVEL_CENTROFFICE) {
            self::$agmt_states['12'] = self::$agmt_states['2'];
            self::$agmt_states['9'] = self::$agmt_states['10'];
        }

        if (isset($ast_datarow) && is_array($ast_datarow)) $fullrow = $ast_datarow;
        $ret = (isset(self::$agmt_states[$stateid])) ? self::$agmt_states[$stateid] : "[$stateid]";
        # WriteDebugInfo("fyllrow: ", $fullrow);
        if ( !empty($fullrow['datepay']) && $fullrow['datepay'] > 0 && in_array($stateid, array(1,3,5,6))) {
            $ret .= " / оплачен";
            if(!empty($fullrow['platno'])) $ret .= "($fullrow[platno])";
        }
        # добавляю статус Биз-процесса!
        if ( !empty($fullrow['bpstateid']) ) {
        if ($stateid == PM::STATE_FORMED && !empty($fullrow['bpstateid']))
            $ret .= '/' .PlcUtils::decodeBpState($fullrow['bpstateid'], 1);
        }
        if (isset($fullrow['eqpayed']) && intval($fullrow['eqpayed'])>0)
            $ret .= ' <span class="eqpayed" title="Онлайн-оплата">О</span>';
        # if (!empty($ast_datarow['accepted'])) $ret .= ' / Акцептован';
        return $ret;
    }

    /**
    * для форм просмотра договора, заявления
    * @param mixed $stateid  ИД статуса
    * @param mixed $accepted значение флага "акцептован) - используется если $fullform>0
    * @param mixed $fullform - 1 если нужен ПОЛНЫЙ текст, 0 - только собсно статус
    */
    public function decodeAgmtState($stateid, $accepted=0, $fullform=1) {
        # WriteDebugInfo("decodeAgmtState($stateid, $accepted, $fullform)");
        # WriteDebugInfo('decodeAgmtState, rawagmt:', $this->_rawAgmtData);
        $postfix = ( $fullform ? '_full':'');
        $statetext = '';
        $showid = $stateid;
        $userLevel = $this->getUserLevel();

        if($userLevel <=3) {
            if($stateid == PM::STATE_UWDENIED) $showid = PM::STATE_UNDERWRITING;
            if($stateid == PM::STATE_ANNUL) $showid = PM::STATE_CANCELED;
        }
        $statetext = AppEnv::getLocalized('agmtstate_'.$showid . $postfix);

        if ($statetext)
            $ret = $statetext;
        else
            $ret = (isset(self::$agmt_states[$showid])) ? self::$agmt_states[$showid] : "[$stateid]";
        if($stateid == PM::STATE_IN_FORMING && $this->agmtdata['bpstateid'] == PM::BPSTATE_RELEASED) {
            $ret = 'Полис выпущен';
            if($fullform && !empty($this->agmtdata['date_release']))
                $ret .= ', дата выпуска: '. to_char($this->agmtdata['date_release']);
        }
        elseif(in_array($stateid, [PM::STATE_PROJECT,PM::STATE_FORMED]) && !empty($this->agmtdata['bpstateid']) && $fullform) {
            $ret .= ' / ' . Agstates::viewBpState($this->agmtdata['bpstateid'], !$fullform, $this);
        }
        elseif($stateid == PM::STATE_UWAGREED && !empty($this->agmtdata['bpstateid']) && $fullform) {
            $ret .= ' / ' . Agstates::viewBpState($this->agmtdata['bpstateid'], !$fullform, $this);
        }
        if ($fullform) {
            # writeDebugInfo("agmtdata ", $this->agmtdata);
            if ($stateid == PM::STATE_DISSOLUTED && intval($this->agmtdata['diss_date']))
                $ret .= " ".to_char($this->agmtdata['diss_date']);

            if(in_array($this->agmtdata['stateid'], [PM::STATE_PAYED, PM::STATE_IN_FORMING, PM::STATE_UWAGREED])
              && $this->agmtdata['bpstateid'] == PM::BPSTATE_RELEASED) {
                if($this->agmtdata['metatype'] == OrgUnits::MT_BANK && $this->agmtdata['stateid'] == PM::STATE_PAYED)
                    $ret = 'Оплачен'; # В Банковском секторе для оплаченного и выпущенного писать "Оплачен"
                else $ret = "Выпущен"; # а в Агентском - "Выпущен" (ну тут Загайнова загнула)

                if(PlcUtils::isDateValue($this->agmtdata['date_release']))
                    $ret .= ', дата выпуска '. to_char($this->agmtdata['date_release']);
            }
            elseif ($this->_rawAgmtData['stateid'] == PM::STATE_FORMED && PlcUtils::isDateValue(($this->agmtdata['date_release'] ?? '')) ){
                $ret .= ', дата выпуска '. to_char($this->agmtdata['date_release']);
                # writeDebugInfo("KT-004");
            }
            elseif(!empty($this->_rawAgmtData['datepay']) && intval($this->_rawAgmtData['datepay']) > 0
              && $this->_rawAgmtData['stateid']<PM::STATE_ANNUL) {
                $ret = 'Оплачен';
                # writeDebugInfo("KT-005");
            }

            if (!empty($this->_rawAgmtData['datepay']) && intval($this->_rawAgmtData['datepay']) > 0 ) {
                $ret .= ', дата оплаты ' . to_char($this->_rawAgmtData['datepay']);

                if ($this->_rawAgmtData['eqpayed'] > 0 ) {
                    if(AppEnv::getConfigValue('alfo_auto_payments'))
                        $autoPayHtml= \AutoPayments::viewAutoPayForPolicy($this->_rawAgmtData['module'],$this->_rawAgmtData['stmt_id']);
                    else $autoPayHtml = '';
                    if(!empty($autoPayHtml)) $ret .= " <span class='view_eqpayed'>(онлайн-оплата) $autoPayHtml</span>";
                }
                else

                if ( !empty($this->_rawAgmtData['platno']) )
                    $ret .= ", плат/квит. ".$this->_rawAgmtData['platno'];
            }
            if ( !empty($this->_rawAgmtData['state_fatca']) && $this->isAdmin() )
                $ret .= '&nbsp;<span class="attention bordered">' . FatcaUtils::getFatcaText() . '</span>';
            if (!empty($this->_rawAgmtData['substate']))
                $ret .= Rework::getReworkStateHtml($this->_rawAgmtData['substate']);

            # доп.статусы финального продвижения (как в агентских)
            /*
            if($userLevel>=PM::LEVEL_IC_ADMIN && $this->_rawAgmtData['stateid'] == PM::STATE_FORMED && !empty($this->_rawAgmtData['bpstateid'])) {
                if($this->_rawAgmtData['bpstateid'] == PM::BPSTATE_ACCOUNTED) $ret .= ' / Отправлен на учет';
                elseif($this->_rawAgmtData['bpstateid'] == PM::BPSTATE_ACTIVE) $ret .= ' / Действующий';
            }
            */
            if($this->_rawAgmtData['stateid'] < PM::STATE_PAYED) {
                $waitAcqPay = Acquiring::hasWaitingOrder($this->_rawAgmtData['module'], $this->_rawAgmtData['stmt_id']);
                if(!empty($waitAcqPay)) {
                    $text = 'Клиенту отправлена ссылка для онлайн-оплаты';
                    if($waitAcqPay=== 'EXPIRED') $text .= ' (просрочена!)';
                    $ret .= "<div class=\"enlight\">$text</div>";
                }
            }
            if (!empty($this->_rawAgmtData['docflowstate']) ) {
                # уже выгрузили в СЭД, вывожу ссылку для открытия карточки в СЭД
                $cardid = $this->_rawAgmtData['export_pkt'];

                # {upd/2022-10-21} показываю сылку в СЭД для любого нашего сотрудника (level=4+) (TODO: надо еще убедиться, что он в лок.сети Alz)
                if ($cardid && isset(AppEnv::$_plugins['sedexport']) && ($userLevel >= $this->sedMinLevel) ) {
                    SedExport::setServerType($this->enable_export); # тип сервера, 1 - СЭД АЖ, 2 - СЭД Альянс
                    $docFlowUrl = SedExport::getDocFlowUrlForCard($cardid, $this->module,$this->enable_export);
                    $ret .= " / <a href=\"$docFlowUrl\" target=\"_blank\">СЭД: $cardid</a>";
                }
                # else $ret .= " / в СЭД"; // без ссылки - только показ
            }
        }

        # writeDebugInfo("agmtdata ", $this->agmtdata);
        # writeDebugInfo("KT-001, ", $this->_rawAgmtData);

        if($fullform && PlcUtils::isPolicyExpired($this->agmtdata)) {

            if(empty($this->agmtdata['previous_id'])) {

                if($this->agmtdata['stateid'] == PM::STATE_PAYED) # просрочка МДВ в оплаченном полисе
                    $expiredTxt = AppEnv::getLocalized('err_release_expired_payed');
                else {
                    if($this->agmtdata['stateid'] == PM::STATE_UWAGREED && $this->agmtdata['bpstateid'] == PM::BPSTATE_UWREWORK)
                        $expiredTxt = AppEnv::getLocalized('warn_release_expired_rework_uw');
                    else {
                        $expiredTxt = AppEnv::getLocalized('warn_release_expired');
                        if($this->recalculable)
                            $expiredTxt .= ' (кнопка "Пересчитать")';
                        else
                            $expiredTxt .= ' (кнопка "Обновить данные")';
                    }
                }
            }
            else {
                $expiredTxt = AppEnv::getLocalized('err_release_expired_prolong');
            }
                # }
                # $ret .= "<br><b>$expiredTxt</b>";
                $ret .= '<div class="card text-danger fw-bolder p-2">' . $expiredTxt . '</div>';
            # }
        }

        # writeDebugInfo("state: ", $ret);
        return $ret;
    }

    public static function viewStmtState($stateid) {
        $strstate = (string)$stateid;
        $ret = '';
        if(isset(self::$stmt_states[$strstate])) $ret = self::$stmt_states[$strstate];
        else $ret = "[$stateid]";
        return $ret;
    }
    public function decodeStmtState($stateid, $fullform=TRUE) {
        $strstate = (string)$stateid;
        $ret = '';
        if(isset(self::$stmt_states[$strstate])) $ret = self::$stmt_states[$strstate];
        else $ret = "[$stateid]";
        if ($stateid == PM::STATE_UNDERWRITING) {
            $reasonid = (isset($this->_rawAgmtData['reasonid'])? $this->_rawAgmtData['reasonid'] : FALSE);
            if ($reasonid!==FALSE && ($reasonid!=25 || $this->isAdmin()) && $fullform) {
                $reasonSrtr = InsObjects::getUwReasonDescription($reasonid);
                $ret = "<span title='$reasonSrtr'>$ret</span>";
            }
        }
        return $ret;
    }

    /**
    * Просмотр списка полисов данного продукта
    *
    */
    public function agrlist() {

        $plg = $this->module;
        if (!$plg) $plg = AppEnv::currentPlugin();

        # include_once('astedit.php');
        if(WebApp::$useDataTables) include_once('astedit.datatables.php');
        else include_once('astedit.php');

        $tbl = new CTableDefinition(PM::T_POLICIES);
        $tbl->setBaseUri("./?plg=$plg&action=agrlist");

        # {upd/2024-03-13} фильтры по номеру полиса в одном продукте не будут влиять на просмотр другого
        $tbl->setFilterPrefix($plg);
        $agmt_filter = $this->agmtDeptFilter();
        if (self::$debug) WriteDebugInfo($this->module . '/policy view filter: ',$agmt_filter);
        if (($agmt_filter) && $agmt_filter!=='1') $tbl->addBrowseFilter($agmt_filter);

        $super = SuperAdminMode(); # супер-админ может удалять полисы

        if (method_exists($this, 'agrListFields')) $fldlist = $this->agrListFields();
        else {
            $fldlist = 'policyno';
            /*
            if ($super || AppEnv::$auth->getAccessLevel($this->privid_super)) {
              if (WebApp::$IFACE_WIDTH ==0 || WebApp::$IFACE_WIDTH > 800) $fldlist .= ',deptid';
            }
            */
            if (WebApp::$IFACE_WIDTH >0 && WebApp::$IFACE_WIDTH < 800) # short field list
              $fldlist .= ',datefrom,datetill,term,policy_prem,currency,created';
            else
              $fldlist .= ',insurer_fullname,datefrom,datetill,term,policy_prem,currency,created';

            # $fldlist   .= ($this->b_stmt_exist ? ',stmt_stateid' : '') . ',stateid';
            $fldlist   .= ',stateid,statedate';
        }

        $tbl->SetView($fldlist);
        $tbl->addBrowseFilter("module='$plg'");
        # {upd/2023-11-01} юзер может отключить вывод аннулировыанных
        $hideCanceled = UserParams::getSpecParamValue(0,PM::USER_CONFIG,'show_canceled');
        if($hideCanceled === 'hide') $tbl->addBrowseFilter("stateid NOT IN(9,10)"); # PM::STATE_ANNUL,STATE_CANCELED
        /**
        if(InsObjects::isBankProduct($this->module)) {
            # $tbl->AddSearchFields('substate,@PlcUtils::SEDFilter');
            $tbl->AddSearchFields('docflowstate,substate');
        }
        **/
        # удалять полисы можно только в тест-средах!
        $canDelete = ($super && !AppEnv::isProdEnv());

        if(isAjaxCall()) {
            $tbl->MainProcess(1,0,$canDelete,0);
            exit;
        }
        if (method_exists(AppEnv::$_plugins[$this->module], 'getVisibleProductName')) {
            $visibleName = $plg::getVisibleProductName();
            $pageTitle = AppEnv::getLocalized('title_agrlist') . ' '. $visibleName;
        }
        else $pageTitle = AppEnv::getLocalized($plg.':agrlist');

        AppEnv::drawPageHeader($pageTitle); # AppEnv::getLocalized

        $tbl->MainProcess(1,0,$canDelete,0);

        AppEnv::drawPageBottom();
        if (AppEnv::isStandalone()) exit;
    }
    public function agrlist_dt() {

        $plg = $this->module;
        if (!$plg) $plg = AppEnv::currentPlugin();

        if(WebApp::$useDataTables) include_once('astedit.datatables.php');
        else include_once('astedit.php');

        $tbl = new CTableDefinition(PM::T_POLICIES);
        $tbl->setBaseUri("./?plg=$plg&action=agrlist_dt");

        # {upd/2024-03-13} фильтры по номеру полиса в одном продукте не будут влиять на просмотр другого
        $tbl->setFilterPrefix($plg);

        $super = SuperAdminMode(); # супер-админ может удалять полисы

        if (method_exists($this, 'agrListFields')) $fldlist = $this->agrListFields();
        else {
            $fldlist = 'policyno';
            /*
            if ($super || AppEnv::$auth->getAccessLevel($this->privid_super)) {
              if (WebApp::$IFACE_WIDTH ==0 || WebApp::$IFACE_WIDTH > 800) $fldlist .= ',deptid';
            }
            */
            if (WebApp::$IFACE_WIDTH >0 && WebApp::$IFACE_WIDTH < 800) # short field list
              $fldlist .= ',datefrom,datetill,term,policy_prem,currency,created';
            else
              $fldlist .= ',insurer_fullname,datefrom,datetill,term,policy_prem,currency,created';

            # $fldlist   .= ($this->b_stmt_exist ? ',stmt_stateid' : '') . ',stateid';
            $fldlist   .= ',stateid,statedate';
        }

        $tbl->SetView($fldlist);
        $tbl->addBrowseFilter("module='$plg'");
        # {upd/2023-11-01} юзер может отключить вывод аннулировыанных
        $hideCanceled = UserParams::getSpecParamValue(0,PM::USER_CONFIG,'show_canceled');
        if($hideCanceled === 'hide') $tbl->addBrowseFilter("stateid NOT IN(9,10)"); # PM::STATE_ANNUL,STATE_CANCELED
        /**
        if(InsObjects::isBankProduct($this->module)) {
            # $tbl->AddSearchFields('substate,@PlcUtils::SEDFilter');
            $tbl->AddSearchFields('docflowstate,substate');
        }
        **/
        # удалять полисы можно только в тест-средах!
        $canDelete = ($super && !AppEnv::isProdEnv());

        if(isAjaxCall()) {
            $tbl->MainProcess(1,0,$canDelete,0);
            exit;
        }
        if (method_exists(AppEnv::$_plugins[$this->module], 'getVisibleProductName')) {
            $visibleName = $plg::getVisibleProductName();
            $pageTitle = AppEnv::getLocalized('title_agrlist') . ' '. $visibleName;
        }
        else $pageTitle = AppEnv::getLocalized($plg.':agrlist');

        AppEnv::drawPageHeader($pageTitle); # AppEnv::getLocalized
        $agmt_filter = $this->agmtDeptFilter();
        if (self::$debug) WriteDebugInfo($this->module . '/policy view filter: ',$agmt_filter);
        if (($agmt_filter) && $agmt_filter!=='1') $tbl->addBrowseFilter($agmt_filter);

        $tbl->MainProcess(1,0,$canDelete,0);

        AppEnv::drawPageBottom();
        if (AppEnv::isStandalone()) exit;
    }


    public function updatestmt() {
        if ( self::$debug ) {
            WriteDebugInfo('updatestmt: params: ', $this->_p); # exit('TODO: stop saving');
        }
        $stmt_id = $recid = empty($this->_p['stmt_id']) ? 0 : intval($this->_p['stmt_id']);

        $ret = '1';
        if (method_exists($this,'save_stmt_data')) {
            $recid = $this->save_stmt_data($policyno);
            if ($stmt_id>0) { # {upd/2021-09-20} синхронизирую изменение ФИО/рожд/пасп/тлф/emial в инв-анкету
                $ankRes = investAnketa::synchronize($this->module, $stmt_id);
                # writeDebugInfo("synchronize invAnketa: [$ankRes]");
            }
        }
        else $this->saveagmt();
        # WriteDebugInfo('Выделен policyno: ',$policyno);
        if (!$recid) {
            $errtext = $this->getErrorMessage();
            $ret = "1\tshowmessage\f{$errtext}\fОшибка!\fmsg_error"; # showmessage | title | text
            AppEnv::echoError($ret);
        }
        /*
        if($stmt_id) { # update existing record
            $ret .= "\tgotourl\f./?plg={$this->module}&action=agrlist"; # после редактирования возвращаюсь в список полисов
            # $ret .= "\tshowmessage\fOpana, success !<br>id=$stmt_id , pno=$policyno";
            # TODO: возможны варианты, напр., если у договора уже были сканы, предв.удалить их ?
        }
        else { # add new stmt!
            if($recid) {
                $ret .= "\tgotourl\f./?plg={$this->module}&action=viewagr&id=$recid";
            }
        }
        */
        if (self::$debug==1.1) $ret .= "\tshowmessage\fДанные сохранил, <br>ID договора=$recid\fРежим отладки";
        else $ret .= "\tgotourl\f./?plg={$this->module}&action=viewagr&id=$recid";
        if ($this->notify_agmt_change == 1 && method_exists($this, 'notifyAgmtChange') && empty($this->_p['keepstate'])) {
            if (!$stmt_id) {
                $details = 'Заведен новый договор';
                # else $details = 'Произведено редактирование договора';
                $this->notifyAgmtChange($details, $recid);
            }
        }
        # немедленно перейти на страницу просмотра договра с кнопками печати, upload...
        if (self::$debug>1) WriteDebugInfo('updatestmt exit string:',$ret);
        exit($ret);
    }
    /**
    * Сохраняет перс.данные страхователя, застрахованного (застр.ребенка), либо удаляет соотв.запись если $data = FALSE
    *
    * @param mixed $policyid ИД полиса
    * @param mixed $ptype  тип лица ( insr | insd | child )
    * @param mixed $data assoc.array - данные в массиве
    * @param mixed $pref префикс в имени полей. Если не указан, юзать ptype
    * @param $ul = 0 (ФЛ) | 1 (ЮЛ)
    * @param $append = FALSE (по умолчанию - обновление существующей записи) | TRUE - всегда добавлять (для заливки списка застрахованных)
    * @return полное ФИО ФЛ / наименование ЮЛ, для заполнения поля insurer_fullname/insured_fullname
    */
    public function saveIndividual($policyid, $ptype, $data, $pref=null, $ul=0, $append=FALSE, $offset = 0) {
        $ret = \Persons::saveIndividual($policyid, $ptype, $data, $pref, $ul, $append, $offset);
        return $ret;
        /**
        # Ищу, не сохраняли ли раньше эту персону для данного полиса
        if (self::$debug) WriteDebugInfo("saveIndividual/$ptype/$pref data:", $data);
        if ($append) $find = 0;
        else {
            $findopts = array('fields'=>'id','where'=>"stmt_id=$policyid AND ptype='$ptype'",'singlerow'=>1,'orderby'=>'id');
            if ($offset > 0) # ищем ВТОРУЮ/NN-ю запись с застрахованным
                 $findopts['offset'] = $offset;
            $find = AppEnv::$db->select(PM::T_INDIVIDUAL, $findopts);
        }

        if ($pref===null) $pref = $ptype;
        $sameadr = empty($data[$pref.'sameaddr']) ? 0:1;
        $snils = (isset($data[$pref.'snils']) ? $data[$pref.'snils'] : '');
        if($snils!='') $snils = Sanitizer::safeString($snils,'snils'); # защита от "___-___-___-__"
        $person = array(
            'ptype' => $ptype
           ,'fam'   => (isset($data[$pref.'fam']) ? rusUtils::capitalizeName($data[$pref.'fam']) : '')
           ,'imia'  => (isset($data[$pref.'imia']) ? rusUtils::capitalizeName($data[$pref.'imia']) : '')
           ,'otch'  => (isset($data[$pref.'otch']) ? rusUtils::capitalizeName($data[$pref.'otch']) : '')
           ,'inn'   => (isset($data[$pref.'inn']) ? $data[$pref.'inn'] : '')
           ,'snils'   => $snils
           ,'ogrn'  => ''
           ,'rezident_rf' => (isset($data[$pref.'rezident_rf']) ? $data[$pref.'rezident_rf'] : FALSE)
           ,'rez_country' => (isset($data[$pref.'rez_country']) ? $data[$pref.'rez_country'] : '')
           ,'birth_countryid' => (isset($data[$pref.'birth_countryid']) ? $data[$pref.'birth_countryid'] : '')
           ,'birth_country' => (isset($data[$pref.'birth_country']) ? $data[$pref.'birth_country'] : '')
           ,'doctype' => (isset($data[$pref.'doctype']) ? $data[$pref.'doctype'] : ($ul?10:1)) # паспорт=1, свид.рег ЮЛ=10
           ,'docser' => (isset($data[$pref.'docser']) ? RusUtils::mb_trim($data[$pref.'docser']) : '')
           ,'docno' => (isset($data[$pref.'docno']) ? RusUtils::mb_trim($data[$pref.'docno']) : '')
           ,'docdate' => (isset($data[$pref.'docdate']) ? to_date($data[$pref.'docdate']) : '0')

           ,'inopass' => (isset($data[$pref.'inopass']) ? RusUtils::mb_trim($data[$pref.'inopass']) : '')
           ,'permit_type' => (isset($data[$pref.'permit_type']) ? $data[$pref.'permit_type'] : 0)
           ,'migcard_ser' => (isset($data[$pref.'migcard_ser']) ? RusUtils::mb_trim($data[$pref.'migcard_ser']) : '')
           ,'migcard_no' => (isset($data[$pref.'inopass']) ? RusUtils::mb_trim($data[$pref.'migcard_no']) : '')

           ,'docfrom' => (!empty($data[$pref.'docfrom']) ? to_date($data[$pref.'docfrom']) : '0')
           ,'doctill' => (!empty($data[$pref.'doctill']) ? to_date($data[$pref.'doctill']) : '0')
           ,'docissued' => (isset($data[$pref.'docissued']) ? RusUtils::mb_trim($data[$pref.'docissued']) : '')
           ,'docpodr' => (isset($data[$pref.'docpodr']) ? rusUtils::makeCodePodr($data[$pref.'docpodr']) : '')

           # ,'phonepref' => (isset($data[$pref.'phonepref']) ? $data[$pref.'phonepref'] : '')
           ,'phone' => (isset($data[$pref.'phone']) ? $data[$pref.'phone'] : '')
           # ,'phonepref2' => (isset($data[$pref.'phonepref2']) ? $data[$pref.'phonepref2'] : '')
           ,'phone2' => (isset($data[$pref.'phone2']) ? $data[$pref.'phone2'] : '')
           ,'email' => (isset($data[$pref.'email']) ? $data[$pref.'email'] : '')
           ,'sameaddr' => $sameadr
           ,'relation' => (isset($data[$pref.'relation']) ? $data[$pref.'relation'] : '')
        );

        if ($this->simpleAddr) {
            $person['adr_full'] = $data[$pref.'adr_full'] ?? $data[$pref.'fulladdr'] ?? '';
            $person['fadr_full'] = $data[$pref.'fadr_full'] ?? $data[$pref.'fullfaddr'] ?? '';
        }
        else {
          $addrData = [
           'adr_full' => (isset($data[$pref.'adr_full']) ? $data[$pref.'adr_full'] : '')
           ,'adr_zip' => (isset($data[$pref.'adr_zip']) ? RusUtils::mb_trim($data[$pref.'adr_zip']) : '')
           ,'adr_countryid' => (isset($data[$pref.'adr_countryid']) ? $data[$pref.'adr_countryid'] : PlcUtils::ID_RUSSIA)
           ,'adr_country' => (isset($data[$pref.'adr_country']) ? $data[$pref.'adr_country'] : '')
           ,'adr_region' => (isset($data[$pref.'adr_region']) ? $data[$pref.'adr_region'] : '')
           ,'adr_city' => (isset($data[$pref.'adr_city']) ? $data[$pref.'adr_city'] : '')
           ,'adr_street' => (isset($data[$pref.'adr_street']) ? $data[$pref.'adr_street'] : '')
           ,'adr_house' => (isset($data[$pref.'adr_house']) ? $data[$pref.'adr_house'] : '')
           ,'adr_corp' => (isset($data[$pref.'adr_corp']) ? $data[$pref.'adr_corp'] : '')
           ,'adr_build' => (isset($data[$pref.'adr_build']) ? $data[$pref.'adr_build'] : '')
           ,'adr_flat' => (isset($data[$pref.'adr_flat']) ? $data[$pref.'adr_flat'] : '')
           ,'fadr_full' => (isset($data[$pref.'fadr_full']) ? $data[$pref.'fadr_full'] : '')
           ,'fadr_countryid' => ((!$sameadr && isset($data[$pref.'fadr_countryid'])) ? $data[$pref.'fadr_countryid'] : '')
           ,'fadr_zip' => ((!$sameadr && isset($data[$pref.'fadr_zip'])) ? $data[$pref.'fadr_zip'] : '')
           ,'fadr_country' => ((!$sameadr && isset($data[$pref.'fadr_country'])) ? $data[$pref.'fadr_country'] : '')
           ,'fadr_region' => ((!$sameadr && isset($data[$pref.'fadr_region'])) ? $data[$pref.'fadr_region'] : '')
           ,'fadr_city' => (isset($data[$pref.'fadr_city']) ? $data[$pref.'fadr_city'] : '')
           ,'fadr_street' => ((!$sameadr && isset($data[$pref.'fadr_street'])) ? $data[$pref.'fadr_street'] : '')
           ,'fadr_house' => ((!$sameadr && isset($data[$pref.'fadr_house'])) ? $data[$pref.'fadr_house'] : '')
           ,'fadr_corp' => ((!$sameadr && isset($data[$pref.'fadr_corp'])) ? $data[$pref.'fadr_corp'] : '')
           ,'fadr_build' => ((!$sameadr && isset($data[$pref.'fadr_build'])) ? $data[$pref.'fadr_build'] : '')
           ,'fadr_flat' => ((!$sameadr && isset($data[$pref.'fadr_flat'])) ? $data[$pref.'fadr_flat'] : '')
          ];
          $person = array_merge($person, $addrData);
        }
        if ($ul) {
            $ret = $person['fam'] = (isset($data[$pref.'urname']) ? $data[$pref.'urname'] : '');
            $person['ogrn'] = (isset($data[$pref.'ogrn']) ? $data[$pref.'ogrn'] : '');
            $person['inn'] = (isset($data[$pref.'urinn']) ? $data[$pref.'urinn'] : '');
            $person['kpp'] = (isset($data[$pref.'kpp']) ? $data[$pref.'kpp'] : '');
            # $person['ulurl'] = (isset($data[$pref.'ulurl']) ? $data[$pref.'ulurl'] : '');
            $person['snils'] = '';
            # exit( '1' . AjaxResponse::showMessage('<pre>' . print_r($person,1). '</pre>'));
        }
        else {
            $person['birth'] = (isset($data[$pref.'birth']) ? to_date($data[$pref.'birth']) : '0');
            # WriteDebugInfo('person birth: ', $person['birth']);
            # $person['ulurl'] = '';
            $person['sex']   = (isset($data[$pref.'sex']) ? $data[$pref.'sex'] : 'M');
            $person['married'] = (isset($data[$pref.'married']) ? $data[$pref.'married'] : '');
            $ret = trim( $person['fam'].' '.$person['imia'].' '.$person['otch'] );
            $b_rus = self::isRF($data[$pref.'rez_country']);
            if ($b_rus) {
                $person['inopass'] = $person['migcard_ser'] = $person['migcard_no'] = '';
                $person['docfrom'] = $person['doctill'] = '0';
            }
            else {
                $person['docser'] = $person['docno'] = $person['docpodr'] = '';
            }
        }
        # exit('TODO UL');
        if(self::$debug>1) WriteDebugInfo($pref, ' individual data to save:', $person);
        if ($find) {
            $recid = $find['id'];
            $ok = AppEnv::$db->update(PM::T_INDIVIDUAL, $person, array('id'=>$recid));
        }
        else {
            $person['stmt_id'] = $policyid;
            if ($this->_debugAdd) $ok = 1;
            else $ok = AppEnv::$db->insert(PM::T_INDIVIDUAL, $person);
        }
        if (self::$debug>1) WriteDebugInfo("[$ok]=result indiv save SQL:", AppEnv::$db->getLastQuery());
        if ($err = AppEnv::$db->sql_error()) WriteDebugInfo("SQL error: ", $err);
        return $ret;
        **/
    }

    # отдельный застрахованный больше не нужен, удаляю запись
    public function dropIndividual($stmtid, $ptype) {
        $ret = AppEnv::$db->delete(PM::T_INDIVIDUAL, array('stmt_id'=>$stmtid, 'ptype'=>$ptype));
        return $ret;
    }
    # Сохраняю всех застрах.детей : child1, child2, child3...
    public function saveMultiChilds($stmtid, $ptype='child', $maxItems=4) {
        AppEnv::$db->delete(PM::T_INDIVIDUAL, array('stmt_id'=>$stmtid, "ptype LIKE '$ptype%'"));
        $childNo = 0;
        for($kk=1; $kk<=$maxItems; $kk++) {
            if (!empty($this->_p['b_'.$ptype.$kk])) {
                # $childNo++;
                $dataPref  = $ptype.$kk;
                $savePref = $ptype.(++$childNo);
                $this->saveIndividual($stmtid,$ptype, $this->_p, $dataPref,0,TRUE);
                #                     $stmtid,$ptype, $data,     $pref=null, $ul=0, $append
            }
        }
        return $childNo;
    }
    /**
    * Загружаем договор в массив $this->agmtdata и возвращаем как результат
    *
    * @param mixed $id
    * @param mixed $mode 0-для формы просмотра, 'edit'-для редактирования, 'export'-для выгрузки
    */
    public function loadPolicy($id, $mode=0, $skipCalc=FALSE) {
        # writeDebugInfo("loadPolicy($id, $mode)");

        $prolong = FALSE; # !empty(AppEnv::$_p['prolong']); # устаревшая пролонгация - не пользуемся!
        if($id<=0) return FALSE;
        $this->agmtdata = [];
        $dta = AppEnv::$db->select(PM::T_POLICIES,array('where'=>array('stmt_id'=>$id),'singlerow'=>1));
        if (!isset($dta['stmt_id'])) return FALSE;

        if ($prolong) {
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            if (AppEnv::$auth->isSuperAdmin()) # супер-админ - пролонгация ТОЧНО от даты окончания исходного
                $dta['datefrom'] = addToDate($dta['datetill'],0,0,1);
            else
                $dta['datefrom'] = max($tomorrow, addToDate($dta['datetill'],0,0,1));

            $term = $dta['term'];
            $dta['datetill'] = addToDate($dta['datefrom'],$term,0,-1); # не нужно, чисто для порядка
            $dta['policyno'] = ''; # зачистка ручного ввода N полиса
        }

        $this->_rawAgmtData = $this->agmtdata = $dta;

        $this->_metaType = $dta['metatype']; # = OrgUnits::getMetaType($dta['headdeptid']);
        $dopData = AgmtData::getData($this->module, $id);

        if(is_array($dopData)) $this->agmtdata = $dta = array_merge($dta, $dopData);

        if ($mode === -1) {
            # $this->agmtdata = $dta;
            return $dta; # нужны только "основные данные", больше ничего не гружу
        }
        $dta['currency_raw'] = $dta['currency'];
        if ($this->max_benefs>0 && empty($dta['no_benef'])) {
            $this->_rawBenefs = AppEnv::$db->select(PM::T_BENEFICIARY,array(
                'where'=>array('stmt_id'=>$id,'bentype'=>'benef'),'orderby'=>'id'
              )
            );
        }

        $this->_rawcBenefs = AppEnv::$db->select(PM::T_BENEFICIARY,array(
            'where'=>array('stmt_id'=>$id,'bentype'=>'cbenef'),'orderby'=>'id','singlerow'=>1
          )
        );
        # if (self::$debug) WriteDebugInfo('loadPolicy/001: data:', $dta);
        if ($mode === 0) {
            $this->agmtdata = array_merge($this->agmtdata, $dta);
            return $dta; # нужны только осн.данные
        }

        $meta = $this->loadSpecData($id); # $meta['spec_params']['predstype']
        # echo 'meta data <pre>' . print_r($meta,1). '</pre>';
        # WriteDebugInfo('SPC DATA:', $meta);
        # $this->calc = isset($meta['calc_params']) ? $meta['calc_params'] : array(); # b_insured2=1 - есть 2-ой застрах.
        # writeDebugInfo("loaded calc:", $this->calc);
        # $this->srvcalc = isset($meta['ins_params']) ? $meta['ins_params'] : array();
        # $this->finplan = isset($meta['fin_plan']) ? $meta['fin_plan'] : array();

        if (method_exists($this, 'modifyParams'))
            $this->modifyParams();

        if (!$skipCalc) {
            unset($this->calc['action'], $this->calc['stmt_id']);
            # чтобу не портило лишними данными (перешибает "стандартное поле action)
            # writeDebugInfo("dta before merge ", $dta);
            # exit(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($dta,1) .print_r($this->calc,1) . '</pre>');
            if(is_array($this->calc))
                $dta = array_merge($dta, $this->calc);
            # writeDebugInfo("dta after merge ", $dta);
        }
        #if (isset($meta['spec_params']) && is_array($meta['spec_params']))
        #    $dta = array_merge($dta,$meta['spec_params']);
        // if (isset($meta['']
        $insd2_offset = 0;
        $b_child = $this->policyHasChild();
        $chDelegate = $meta['spec_params']['child_delegate'] ?? $meta['spec_params']['predstype'] ?? '';
        # exit("b_child=$b_child chDelegate=$chDelegate");

        if ($mode===0 || $mode === 'view') { # для просмотра (view)
            # if (method_exists($this, 'beforeViewAgr')) $this->beforeViewAgr();
            $prefZ = method_exists($this, 'getInsuredPrefix') ? $this->getInsuredPrefix() : 'insd';
            $insp = empty($dta['equalinsured']) ? $prefZ:'insr';

            $dta['insr'] = $this->loadIndividual($id, 'insr','', ($dta['insurer_type']>=2),0,$mode);
            if (!$this->nonlife) {
                if ($dta['equalinsured']==0 || ($this->multi_insured>1 && !empty($this->calc['b_insured2'])) ) {
                    $dta['insd'] = $this->loadIndividual($id, $prefZ, '',0,0,$mode);
                    # гружу весь список застрахованных
                    # writeDebugInfo("TODO: застрахованные/$prefZ ", $dta['insd']);
                }
            }

            if ($this->insured_child && $this->policyHasChild())
                $dta['child'] = $this->loadIndividual($id, 'child','',0,FALSE, $mode);

            $dta['stmt_statusname'] = $this->decodeStmtState($dta['stmt_stateid']);
            $dta['statusname'] = $this->decodeAgmtState($dta['stateid']);

            # $dta['policy_prem'] = fmtMoney($dta['policy_prem']);

            if (isset($dta['m1'])) $dta['oplata'] = self::verboseOplata($dta['m1']);
            else
                $dta['oplata'] = self::verboseOplata($dta['rassrochka']);

            if (method_exists($this, 'viewOplataDetails')) {
                if ($oplSuffix = $this->viewOplataDetails())
                    $dta['oplata'] .= ', ' . $oplSuffix;
            }
            if (method_exists($this, 'decodeSubProgram')) {
                $dta['programname'] = $this->decodeSubProgram($this->calc);
            }
            elseif(method_exists(AppEnv::$_plugins[$this->module], 'getFullProgramName')) {
                $dta['programname'] = AppEnv::$_plugins[$this->module]->getFullProgramName($dta['programid'], $dta);
            }
            else $dta['programname'] = $this->getProgramName($dta['programid'], $dta);


            if ( PM::VIEW_BENEF && empty($dta['no_benef'])) {
                $dta['benef'] = $this->loadBeneficiaries($id,'benef',0,1); # осн.выгодоприобр.
            }
            if (!empty( $chDelegate) &&  $chDelegate == 'N') $dta['cbenef'] = $this->loadBeneficiaries($id,'cbenef');
        }
        elseif ($mode === 'edit' || $mode === 'print' || $mode==='export') { # собрались редактировать данные в договоре | печать | выгрузка
            # writeDebugInfo("$mode, meta: ", $meta);
            $ul = ($dta['insurer_type']==2);
            if (!empty($meta['spec_params'])) {
                unset($meta['spec_params']['insrphone'], $meta['spec_params']['action']);
                $dta = array_merge($dta, $meta['spec_params']);
            }
            # writeDebugInfo("KT-003 $mode, data: ", $dta);
            $this->finplan = isset($meta['fin_plan']) ? $meta['fin_plan'] : array();
            $inprefix = ($mode==='export') ? '':'insr';
            $person = $this->loadIndividual($id,'insr',$inprefix,$ul,FALSE, $mode);

            $insd2_offset = 0;
            if (self::$debug)  WriteDebugInfo("loadPolicy/$mode, insurer loaded:", $person);
            if (is_array($person)) {
                if ($mode === 'export') $dta['insurer'] = $person;
                else {
                    $dta = array_merge($dta, $person);
                    if ($ul) {
                        $dta['insurer_fullname'] = $person['insrurname'];
                    }
                }
            }
            else {
                if($this->agmtdata['stateid']>=0) # для предв.расчета это ОК
                    writeDebugInfo("ERROR: $this->module, id: $id, не найден страхователь!");
            }
            if( $this->multi_insured <100 ) {
                if (empty($dta['equalinsured']) ) {
                    $inprefix = ($mode==='export') ? '' : null;
                    $insd2_offset = 1;
                    $person = $this->loadIndividual($id,'insd', $inprefix,0,0, $mode);
                    # exit('insd person <pre>' . print_r($person,1). '</pre>');
                    if ( self::$debug )  WriteDebugInfo('loadPolicy, insured loaded:', $person);
                    if (is_array($person)) {
                        if ($mode === 'export') $dta['insured'] = $person[0];
                        else $dta = array_merge($dta, $person[0]);
                    }
                }
                else {
                    if ($mode === 'print') {
                        foreach(self::$personfields as $fln) {
                          if (isset($dta['insr'.$fln])) $dta['insd'.$fln] = $dta['insr'.$fln];
                        }
                    }
                }
            }
            # WriteDebugInfo('load, meta: ',$meta);
            if (!$this->nonlife && $this->multi_insured == 2 && !empty($this->calc['b_insured2'])) {
                # есть 2-ой застрахованный!
                $offset = (empty($this->_rawAgmtData['equalinsured'])? 1:0);
                $person = $this->loadIndividual($id,'insd', 'insd2',0,$offset,$mode);

                # echo "loaded insd2/offset=$offset:<pre>", print_r($person,1).'</pre>END!!!<hr>';
                if (isset($person[0]) && is_array($person[0])) $person = $person[0];
                if ( self::$debug )  WriteDebugInfo('loadPolicy, insured-2 loaded:', $person);
                if (is_array($person)) {
                    if ($mode === 'export') $dta['insured2'] = $person;
                    else $dta = array_merge($dta, $person);
                }
            }
            elseif ($this->multi_insured >=100 && method_exists($this, 'getInsuredList')) { # список застрахованных
                $this->insuredList = $this->getInsuredList($id, $mode);
                # writeDebugInfo("multi_insured, loadIndividual...", $this->insuredList);
            }

            # Застрахованный ребенок
            $inprefix = ($mode==='export') ? '' : null;
            if (!$this->nonlife && $b_child ) {
                $person = $this->loadIndividual($id,'child', $inprefix,0,0,$mode);
                if(isset($person['child1fam'])) {
                    # если есть несколько детей, из child1 автоматом делаю щапись "без-номера":
                    $arDopChild = [];
                    foreach($person as $chkey => $chValue) {
                        if(substr($chkey,0,6) === 'child1') $arDopChild['child'.substr($chkey,6)] = $chValue;
                    }
                    $person = array_merge($arDopChild, $person);
                    # exit(__FILE__ .':'.__LINE__.' $arDopChild:<pre>' . print_r($arDopChild,1) . '</pre>');
                }
                # exit(__FILE__ .':'.__LINE__.' child person:<pre>' . print_r($person,1) . '</pre>');
                if (self::$debug)  WriteDebugInfo('child loaded:', $person);
                if (is_array($person)) {
                    if ($mode === 'export') $dta['insuredchild'] = $person;
                    else $dta = array_merge($dta, $person);
                }
            }
            # WriteDebugInfo('on LoadPolicy:no_benef?', $dta);
            if (empty($dta['no_benef'])) {

                $benefs = $this->loadBeneficiaries($id,'benef', ($mode==='edit')); # осн.выгодоприобр.
                # exit(__FILE__ .':'.__LINE__.' $benefs:<pre>' . print_r($benefs,1) . '</pre>');
                # writeDebugInfo("benef/$mode ", $benefs);

                if ($mode === 'print' && is_array($benefs)) for ($kk=1; $kk<=$this->max_benefs; $kk++) {
                    # вместо ИД риска беру из справочника его название
                    # WriteDebugInfo("loaded benef $kk: ", $ben);
                    $bkey = 'benefrisk'.$kk;
                    if (!empty($benefs[$bkey]))  $benefs[$bkey] = $this->getRiskName($benefs[$bkey]);
                    # writeDebugInfo("benefs[$bkey]: ", $benefs[$bkey]);
                }
                # if (self::$debug) WriteDebugInfo('benef loaded:', $benefs);
                if (is_array($benefs)) {
                    if ($mode === 'export') {
                        $dta['beneficiaries'] = $benefs;
                    }
                    else $dta = array_merge($dta, $benefs);
                }
            }

            if($this->max_childbenefs) { # old: $b_child && $chDelegate == 'N' ||
                # $benefs = $this->loadBeneficiaries($id,'cbenef',true); # выгодоприобр. по застр.ребенку
                $cbenefs = Persons::loadBeneficiaries($this, $id,'cbenef', ($mode==='edit'), FALSE);

                # exit(__FILE__ .':'.__LINE__.' cbenefs<pre>' . print_r($benefs,1) . '</pre>');
                if (self::$debug) WriteDebugInfo('cbenefs loaded:', $cbenefs);
                if (is_array($cbenefs) && count($cbenefs)) {
                    if ($mode === 'export') $dta['child_beneficiaries'] = $cbenefs;
                    else $dta = array_merge($dta, $cbenefs);
                }
            }

        }

        if ($mode !== 'export') foreach(self::$list_datefields as $fld) { # преобразую к читабельному dd.mm.yyyy виду
            if (!empty($dta[$fld])) $dta[$fld] = to_char($dta[$fld]);
        }
        if(is_array($dta) && count($dta)>0) {
            # self::$_prodid = $prodid = self::decodeProduct($dta['prodtype']);
            if(isset($meta['spec_params'])) $dta = array_merge($dta,$meta['spec_params']);
            if (!$skipCalc && isset($meta['ins_params']))
                $dta = array_merge($dta,$meta['ins_params']);
            # WriteDebugInfo('ins_params:', $meta['ins_params']);
            if ($mode === 'print') {
               if(isset($meta['fin_plan'])) $dta['fin_plan'] = $meta['fin_plan'];
            }
        }

        if ( is_array($this->calc) && !$this->b_stmt_exist && !$skipCalc) {
            unset($this->calc['stmt_id']); # чтобы затирал реальный ИД договора!
            $dta = array_merge($dta, $this->calc); # Проверить в НСЖ!!! не сломал ли чего
            # writeDebugInfo("dta before merge 2 ", $dta);

        }

        $dta['equalinsured'] = $this->_rawAgmtData['equalinsured']; # из calc может приходить пустышка!!!
        if ($mode === 'export') {
            # готовлю список приложенных файлов - если понадобится для выгрузки во внешнюю систему (СЭД)
            $files = [];
            # {updt/2020-04-03 - добавил в отбор критерий - только невыгруженные файлы (exported=0)
            $finfo = AppEnv::$db->select(PM::T_UPLOADS, array('where'=>['stmt_id'=>$id, 'exported'=>0 ], 'orderby'=>'id'));

            if (is_array($finfo) && count($finfo)>0) foreach($finfo as $fl) {
                $fullFpath = $fl['path'] . $fl['filename'];
                if (!is_file($fullFpath)) continue;
                $files[] = [ 'id'=> $fl['id'], 'filename'=>$fl['descr'], 'fullpath'=>$fullFpath,
                  'filesize'=>$fl['filesize'], 'doctype'=>$fl['doctype']
                ];
            }

            $dta['files'] = $files;
        }
        if ($mode === 'view') {
            $dta['datetill'] = to_char($dta['datetill']);
        }
        unset($dta['anketaid']); # чтобы dta не обнулял anketaid
        if(!empty($this->_rawAgmtData['previous_id']))
            $dta['previous_id'] = $this->_rawAgmtData['previous_id'];

        unset($dta['currency']);

        $this->agmtdata = array_merge($this->agmtdata, $dta);
        # WriteDebugInfo("final return of loadPolicy($id, $mode): ", $dta);
        # WriteDebugInfo("agmtdata: ", $this->agmtdata);
        # exit(__FILE__ .':'.__LINE__.' agmtdata:<pre>' . print_r($this->agmtdata,1) . '</pre>');
        return $this->agmtdata;
    }
    # вернет мета-тип орг-юнита, в котором оформлен договор (Банки, агентская сеть... - OrgUnits)
    public function plcOuMetaType() { return $this->_metaType; }

    # меняю названия полей, чтобы привести к виду "adr_region" - ID города/области
    private function shiftAddrFields(&$arr) {
        if (isset($arr['adr_country'])) {
            $arr['adr_district'] = $arr['adr_region'];
            $arr['adr_region'] = $arr['adr_country'];
            unset($arr['adr_country']);
        }
        if (isset($arr['fadr_country'])) {
            $arr['fadr_district'] = $arr['fadr_region'];
            $arr['fadr_region'] = $arr['fadr_country'];
            unset($arr['fadr_country']);
        }

    }
    /**
    * загружаю в массив $arDest данные о ФЛ для вывода "стандартной" анкеты ФЛ
    * @param mixed $arDest ссылка на массив
    * @param mixed $pers ИД записи в T_INDIVIDUAL ЛИБО готовый массив с данными об ФЛ ЛИБО "префикс" в осн.массиве (insr,insd)
    * @param mixed $prefix  префикс-ключ под-массива (_pageNN), NN = номер страницы в PDF
    * # fixed: 23.11.2016
    */
    public function prepareAnketaData(&$arDest, $pers, $prefix='', $anketaType='') {
        # WriteDebugInfo('pers:', $pers);
        $bAnketa = (isset($this->_deptCfg['b_clientanketa']) ? $this->_deptCfg['b_clientanketa'] :'1');
        if ($bAnketa == '0') return '';
        AnketaPrint::$rawPolicyData =& $this->_rawAgmtData;
        AnketaPrint::$policySpecData = array_merge($this->spec_params, $this->calc);
        # echo 'spec_params <pre>' . print_r($this->spec_params,1). '<br>calc:<br>' .print_r($this->calc,1). '</pre>'; exit;
        # include_once(__DIR__ . '/anketaPrint.php');
        return AnketaPrint::prepareData( $arDest, $pers, $prefix, $bAnketa);
    }
    public function prepareAnketaDataUL(&$arDest, $pers, $prefix) {
        $bAnketa = (isset($this->_deptCfg['b_clientanketaul']) ? $this->_deptCfg['b_clientanketaul'] :'1');
        if ($bAnketa == '0') return '';
        # include_once(__DIR__ . '/anketaPrint.php');
        # AnketaPrint::$rawPolicyData =& $this->_rawAgmtData;
        AnketaPrint::$policySpecData = array_merge($this->_rawAgmtData, $this->spec_params, $this->calc);

        return AnketaPrint::prepareDataUL( $arDest, $pers, $prefix, $bAnketa);
    }

    /**
    *  заношу для печати данные по выгодоприобретателям, в станд.анкету {redmine/2874}
    * @param mixed $arDest - массив куда заносим
    * @param mixed $policyid ИД полиса
    * @param mixed $bentype префикс, тип выг-приобр (benef|benef2|cbenef...)
    */
    public function prepareAnketaBenefs(&$arDest, $policyid, $bentype) {

        $bens = $dta = AppEnv::$db->select(PM::T_BENEFICIARY,
           array('where' => array('stmt_id'=>$policyid,'bentype'=>$bentype)
                ,'orderby'=>'id'
           )
        );
        if (is_array($bens) && count($bens)>0) foreach($bens as $no=>$ben) {
            $bno = $no+1;
            $benstrk = "$bno. " . $ben['fullname'];
            # {redmine:2874}
            # ФИО, ГРАЖДАНСТВО, ДАТА И МЕСТО РОЖДЕНИЯ, РЕКВИЗИТЫ ДОКУМЕНТА УДОСТОВЕРЯЮЩЕГО ЛИЧНОСТЬ, АДРЕС РЕГИСТРАЦИИ/АДРЕС ПРЕБЫВАНИЯ
            if ($ben['rez_country']) $benstrk .= ', '. PlcUtils::decodeCountry($ben['rez_country']);
            $benstrk .=  (intval($ben['birth'])? (", ".to_char($ben['birth'])) : '');
            if (!empty($ben['birth_country'])) $benstrk .= ' '.PlcUtils::decodeCountry($ben['birth_country']);
            if (!empty($ben['docser']) || !empty($ben['docno'])) {
              $dtype = (!empty($ben['doctype'])? $ben['doctype'] : 0);
              $benstrk .= ', '.self::decodeDocType($dtype) . ' ' . trim($ben['docser'] .' '. $ben['docno']);
              $issued = '';
              if (!empty($ben['docissued'])) $issued .= $ben['docissued'];
              if (intval($ben['docdate'])) $issued .= ' '.to_char($ben['docdate']);
              if ($issued !='') $benstrk .= ", выдан " . $issued;
            }
            $addr = '';
            if (!empty($ben['adr_zip']) || !empty($ben['adr_country']) || !empty($ben['adr_street'])) {
              $addr = self::buildFullAddress($ben,'');
              if (empty($ben['sameaddr']))
              $addr .= ' / ' .self::buildFullAddress($ben,'f');
            }
            if ($addr) $benstrk .= ', ' . $addr;

            $arDest['anketa_benef'.$bno] = $benstrk;
        }
    }
    # $nlRisks - Non life риски
    public function setActiveRisks($mainrsk, $doprsk = FALSE, $nlRisks = FALSE) {
        $this->mainRisks = $mainrsk;
        if(is_array($doprsk)) $this->dopRisks = $doprsk;
        if(is_array($nlRisks)) $this->nl_risks = $nlRisks;
    }

    public static function decodeCurrency($cur, $long=FALSE) {

        switch(mb_strtoupper($cur)) {
            case 'RUR': case 'RUB': return ($long ? 'Рубли':'руб.');
            case 'EUR': return 'Евро';
            case 'USD': return ($long ? 'Доллары США':'долл.США');
            default   : return "[$cur]";
        }
    }
    /**
    * Форма просмотра договора, добавления сканов, кнопок продвижения полиса (андеррайтинг, отмена и т.д.)
    *
    */
    public function viewagr() {
        $id = isset($this->_p['id']) ? $this->_p['id'] : 0;
        # HeaderHelper::useJsModules('jqgrid,dmuploader,'.WebApp::FOLDER_APPJS.'policymodel.js');
        HeaderHelper::useJsModules(['simpleajaxuploader','jqgrid', WebApp::FOLDER_APPJS.'policymodel.js',
            WebApp::FOLDER_APPJS.'plcutils.js']
        );
        if ($this->js_modules) HeaderHelper::useJsModules( $this->js_modules);

        $data = $this->loadPolicy($id,'view');
        if(!isset($data['stateid'])) {
            AppEnv::echoError('Неверный ИД полиса');
        }
        $today = date('Y-m-d');
        # writeDebugInfo("view. rawAgmtData: ", $this->_rawAgmtData);
        # writeDebugInfo("view. agmtdata: ", $this->agmtdata);

        # {upd/2024-02-28} - проверка истекшей даты выпуска, авто-сброс при необходимости
        $expired = \PlcUtils::isPolicyExpired($this->agmtdata);
        # writeDebugInfo("expired=[$expired], data ",$this->agmtdata);
        if($expired == 2) {
            # дата выпуска просрочена и нужен сброс, выполняю авто-сброс в начальный проект/черновик
            $reset = \PlcUtils::resetExpiredPolicy($this->agmtdata, TRUE); # ,TRUE - запись о сбросе в журнал
            if(self::$debug) writeDebugInfo("Авто-сброс выполнен, result=[$reset]");
            if($reset) $data = $this->loadPolicy($id,'view'); # загрузить заново после изменения полей
        }
        $prodid = $this->_rawAgmtData['programid'];
        $headDept = $this->_rawAgmtData['headdeptid'];
        # $this->deptProdParams($this->module, $headDept, $this->_rawAgmtData['prodcode'], 0, $this->_rawAgmtData['subtypeid']);
        $this->deptProdParams($this->module, $headDept, $prodid, 0, $this->_rawAgmtData['subtypeid']);
        # ($prodid = '', $headdept = 0, $codirovka='', $includeNonActive=FALSE, $progid = FALSE)
        # writeDebugInfo("view: data ", $data);
        # AppEnv::appendHtml('data <pre>' . print_r($data,1). '</pre>'); AppEnv::finalize(); exit;
        $title = '';
        if (method_exists($this, 'viewPolicyTitle')) $title = $this->viewPolicyTitle($data);
        elseif(method_exists($this->module, 'getVisibleProductName')) {
            $title = 'Договор ' . AppEnv::$_plugins[$this->module]->getVisibleProductName($headDept);
        }
        if (empty($title)) $title = AppEnv::getLocalized($this->module . ':stmt_title');

        # {upd/2025-01-23} - подключение ф-ционала "Избранное"
        if(AppEnv::isFavActive()) {
            $title .= Favority::getIconHtmlPolicy($this->module, $id);
            addJsCode(Favority::getJsCode());
        }

        # [feature-121] если полис ЭДО, отключаем работу с заяалениями: (2024-02 - за хером?
        if ($data['bptype'] === PM::BPTYPE_EDO)
            $this->b_stmt_exist = FALSE;

        # WriteDebugInfo("viewagr, data:", $data);  # WriteDebugInfo("viewagr, this->agmtdata", $this->agmtdata);
        # Защита от ввода ИД "не того" договора прямо в строке URL
        if (isset($data['module']) && $data['module'] !== $this->module) {
            AppEnv::setPageTitle('Ошибка !');
            AppEnv::echoError(AppEnv::getLocalized('err_wrong_agreement_type')
               . ' '. AppEnv::getLocalized($this->module.':main_title')
            );
        }
        $access = $this->checkDocumentRights($id);

        if ($access <=0) AppEnv::echoError('err-no-rights-document');
        if (AppEnv::getAppState() >=100) {
            AppEnv::finalize();
            return;
        }
        $ICadmin = $this->isICAdmin();
        $iamUw = ($this->getUserLevel() == PM::LEVEL_UW);

        $superop = ($ICadmin || $this->isAdmin()) ? 'true':'false';

        $disabView = AppEnv::getConfigValue($this->module . '_disable_activity');
        if($disabView>=10 && !$ICadmin) {
            AppEnv::echoError('err-module_fully_blocked');
            AppEnv::finalize();
            exit;
        }

        # {updt/2020-09-17} вызов метода пред-настроек перед выводом формы полиса, если есть в классе бэкенда
        if (method_exists($this, 'beforeViewAgr')) {
            $this->beforeViewAgr();
        }
        # writeDebugInfo("ICADMIN: [$ICadmin]");
        $myLev = intval($this->getUserLevel()); # viewagr - будет знать уровень прав юзера
        $jscode = "plcUtils.myLevel=$myLev; superOper = $superop; policyModel.init('".$this->module."','$id');";

        # {upd/2024-03-20} при выпуске не давать вводить дату начала, если у полиса просрочена МДВ
        $canSetFrom = TRUE;
        $drelMax = $this->agmtdata['date_release_max'] ?? $data['date_release_max'] ?? '';
        if(PlcUtils::isDateValue($drelMax) && $drelMax < $today)
            $canSetFrom = FALSE;

        if($this->riskyProg && $canSetFrom) # при выпуске будет спрашивать желаемую дату начала
            $jscode .= "plcUtils.riskyProg = true;";

        if(!empty($data['previous_id'])) {
            $jscode .= "plcUtils.prolongid = '$data[previous_id]';";
            $maxRelease = $data['date_release_max'] ?? ''; # в старых полисах этих полей нет!
            # запоминаю макс.дату выпуска для поздней пролонгации:
            if($maxRelease <= date('Y-m-d') && intval($maxRelease))
                $jscode.= "plcUtils.maxRelDate = '".to_char($data['date_release_max']). "';";
        }

        if ( $superop === 'true' || $this->isICAdmin() ) $jscode .= "\$('#menu_statuslist').menu();";
        if ($ICadmin) {
        /* || AppEnv::$auth->getAccessLevel($this->privs_uw)*/
            $jscode .= <<< EOJS

$(document).on('mouseup', function(e){
  policyModel.shiftKey = e.shiftKey;
  policyModel.altKey = e.altKey;
  policyModel.ctrlKey = e.ctrlKey;
  return false;
});

EOJS;
        }
        $maxSize = PM::UPLOAD_MAXSIZE_KB; # ограничение размера файла, КВ
        if ($this->uploadScanMode > 0) {
            # новый способ - раздельные загрузки скана для каждого типа файла
            $jscode .= ' $("#menu_scantypes").menu();';

            $scTypes = !empty($this->scanTypes) ? $this->scanTypes : array_keys(PM::$scanTypes);

            foreach($scTypes as $sctype) {
                # TODO: раскрасить текст, если документ такого типа уже загружен к полису
                # if ( $sctype === 'stmt' ) continue; # для заявления своя кнопа загрузки
                $jscode .= <<< EOJS
  policyModel.uploader['$sctype'] = new ss.SimpleUpload({
     button: 'bt_scan_$sctype',
     url: policyModel.backend, name: 'attachfile',
     data: { 'action':'addscan', 'doctype':'$sctype', 'policyid': policyModel.stmtid },
     maxSize: $maxSize, onComplete: policyModel.handleUploadResult
  });

EOJS;
            }
        }
        else { # старый способ - одна кнопка для загрузки сканов "заявление", одна "полис" (все прочие файлы)
            if ($this->b_stmt_exist && $this->uploadScanMode==0) {
                $jscode .= <<< EOJS
   policyModel.uploader['stmt'] = new ss.SimpleUpload({
      button: 'btn_uploadstmt',
      url: policyModel.backend, name: 'attachfile',
      data: { 'action':'addscan', 'doctype':'stmt', 'policyid': policyModel.stmtid },
      maxSize: $maxSize, onComplete: policyModel.handleUploadResult
   });
EOJS;
            }
            $jscode .= <<< EOJS

   policyModel.uploader['agmt'] = new ss.SimpleUpload({
      button: 'btn_uploadagmt',
      url: policyModel.backend, name: 'attachfile',
      data: { 'action':'addscan', 'doctype':'agmt', 'policyid': policyModel.stmtid },
      maxSize: policyModel.uloadMaxSize, onComplete: policyModel.handleUploadResult
   });
EOJS;

        }
        if( \AppEnv::AutoPaymentActive() && \AutoPayments::getAutoPayState($this->module, $id)!==FALSE ) {
            # формирую кнопку с popup-меню авто-платежей
            \AutoPayments::prepareButtonMenu();
        }
        if (!$this->nonlife && $this->_rawAgmtData['stateid'] == PM::STATE_FORMED && $ICadmin && !$iamUw) {
            $btnAccount = [
                'newaccount' => [
                    'html'=> '<input type="button" id="btn_newaccount" class="btn btn-primary" '
                      . 'onclick="plcUtils.letterNewAccount(policyModel.module, policyModel.stmtid)" '
                      . 'value="Заявл.об учетке" title="В службу поддержки будет отправлено письмо об учетке для Клиента" />'
                   ,'display' => false
                ]
            ];

            $this->all_buttons = array_merge($this->all_buttons, $btnAccount);
            /*
            PlcUtils::addUserButton('btn_newaccount', 'Заявл.об учетке',
              'plcUtils.letterNewAccount(policyModel.module, policyModel.stmtid)', '');
            */
        }
        if($this->_rawAgmtData['metatype'] == OrgUnits::MT_BANK && class_exists('Rework')) { # InsObjects::isBankProduct($this->module)
            $this->all_buttons = array_merge($this->all_buttons, Rework::addButtons($this->module,$this->_rawAgmtData));
            # writeDebugInfo("rework btn must be added");
        }


        $jscode .= <<< EOJS
        $(window).bind('resize', function() {
        var grid = $("#grid_agrscans")
        let newWidth = grid.closest(".ui-jqgrid").parent().width() - 4 ;
        grid.setGridWidth(newWidth);
        }).trigger('resize');
EOJS;

        $jscode .= <<< EOJS
$(document).mouseup(function (e) {
    var mx = e.pageX, my = e.pageY;
    // var dObj = $('#statuscodes_data');
    $('.pm_popmenu').each(function(idx,dObj) {
      // console.log(idx, dObj);
      var jqObj = $(dObj);
      if(jqObj.is(':visible') ) {
        var mypos = jqObj.offset();
        if(mx < mypos.left || mx > mypos.left+jqObj.width()) jqObj.hide();
        if(my < mypos.top || my > mypos.top+jqObj.height()) jqObj.hide();
      }
    });
});
EOJS;

        if ($this->_rawAgmtData['bptype'] === PM::BPTYPE_EDO)
            $jscode .= "plcUtils.edostate = 1;"; # Js будет знать, что полис в ЭДО маршруте

        $plcVers = $this->_rawAgmtData['version'];
        $jscode .= "plcUtils.plcVersion = $plcVers;";

        if (method_exists($this, 'viewagrJsCodeReady')) # настроечный код, специф. для плагина, если есть
            $jscode .= $this->viewagrJsCodeReady($data);

        if (method_exists($this, 'preViewAgr')) # предв.настройки и другой код,специфический для продукта
            $this->preViewAgr($data);

        HeaderHelper::addJsCode($jscode,'ready');
        $this->buttonsVisibility($data, $access);

        $super = AppEnv::$auth->getAccessLevel([$this->privid_super, PM::RIGHT_SUPEROPER]) || (AppEnv::$auth->getAccessLevel($this->privid_editor)>=8);

        # задаю видимость кнопок в соотв-вии с правами юзера:
        # WriteDebugInfo('this->all_buttons:', $this->all_buttons);
        $btnhtml = '';

        foreach ($this->all_buttons as $btnid=>$btn) {
            # WriteDebugInfo("btn $btnid:", $btn);
            if(!isset($btn['display'])) {
                continue;
                # writeDebugInfo("no display in button item $btnid: ", $btn);
            }
            else
                $dspl = $btn['display'] ? '': "style='display:none'";
            if (isset($btn['html']))
                $btnhtml .= "<span id='btn_{$btnid}' $dspl>$btn[html]</span>";
            elseif(self::$debug>1 ) WriteDebugInfo("$btnid: btn w/o html:", $btn);
        }
        /*
        if ($this->isICAdmin() && $this->_rawAgmtData['stateid'] == PM::STATE_FORMED && $this->_rawAgmtData['docflowstate'] > 0
          && $this->_rawAgmtData['bptype'] === PM::BPTYPE_EDO) {
            if (class_exists('delivery')) delivery::addButtons($this->module, $id);
        }
        */
        AppEnv::setPageTitle("Карточка договора ".$this->_rawAgmtData['policyno']);
        # Юзерские кнопки (для конкретного модуля)на форме:
        $btnhtml .= PlcUtils::drawUserButtons($this->module);

        # DEBUG - для супервизора, кнопка вызова полных технич. данных о полисе
        if (AppEnv::$auth->isSuperAdmin())
            $btnhtml .= '<button class="btn btn-primary" onclick="policyModel.showDbg()">[D]</button> &nbsp;';

        # {upd/2022-11-24 - в банковском канале кнопку не показываю (А.Загайнова)
        if ( $data['metatype'] !=OrgUnits::MT_BANK && method_exists($this, 'showCalcParams')) { # кнопка показа параметров калькуляции
            if (!empty($this->calc) && is_array($this->calc) && count($this->calc)>0)
                $btnhtml .= '<input type="button" class="btn btn-primary" title="%title_show_calc_params%" onclick="policyModel.showCalcParams()" value="%label_calc_params%" /> ';
        }
        AppEnv::localizeStrings($btnhtml);
        $data['title'] = $title;
        $hist = $this->log_pref; # TODO: check if user has ADMIN privilege on this module!
        $this->viewPolicy($id,$data,$btnhtml,$hist);
    }

    /**
    * Форма ввода подробных данных к заявлению, договору (полису)
    * если id пустое, параметры продукта берутся из S_SESSION, иначе из таблицы metadata(id)
    */
    public function stmt() {

        # include_once('as_propsheet.php');
        $calcid = isset(AppEnv::$_p['calcid']) ? AppEnv::$_p['calcid'] : FALSE;
        $prodid = isset($this->_p['prodid']) ? $this->_p['prodid'] : '';
        $id = isset($this->_p['id']) ? $this->_p['id'] : '';

        # exit("TODO: $id");
        $newAgr = FALSE;
        $prolong = '0';
        if ($id === 'calc' || $id=='' ) {
            $headDept = OrgUnits::getPrimaryDept();
            $newAgr = TRUE;
            $authorId = AppEnv::$auth->userid;
            $arr = FALSE;
            if ($calcid) {
                if (isset($_SESSION[$calcid]['calcdata_'.$this->mtpost]))
                    $arr = $this->calc = $_SESSION[$calcid]['calcdata_'.$this->mtpost];
                elseif (isset($_SESSION['calcdata_'.$this->mtpost][$calcid]))
                    $arr = $this->calc = $_SESSION['calcdata_'.$this->mtpost][$calcid];

                if (isset($_SESSION[$calcid]['srv_'.$this->mtpost]))
                    $this->srvcalc =  $_SESSION[$calcid]['srv_'.$this->mtpost];
                elseif (isset($_SESSION['srv_'.$this->mtpost][$calcid]))
                    $this->srvcalc =  $_SESSION['srv_'.$this->mtpost][$calcid];

                if(!$arr) $arr = $this->srvcalc;

                # WriteDebugInfo("calcdata_{$this->mtpost}, calc from session:", $this->calc);
                # WriteDebugInfo("srvcalc from session:", $this->srvcalc);
                # WriteDebugInfo("arr :", $arr);
            }
            else $arr = isset($_SESSION['calcdata_'.$this->mtpost]) ? $_SESSION['calcdata_'.$this->mtpost] : FALSE;

            if (!$arr) {
                $errstk = str_replace('{url}','./?plg='.$this->module . '&action=agrcalc', AppEnv::getLocalized('err_stmt_without_calc'));
                if (SuperAdminMode())
                    $errstk .=  "<br>mt_post=$this->mtpost, session:<pre>".print_r($_SESSION,1) . '</pre>';
                AppEnv::echoError($errstk);
            }
            # $this->calc = $arr;
            # WriteDebugInfo("calc in session:", $arr);
        }
        else {
            $this->loadPolicy($id,'edit');
            $rd = $this->loadSpecData($id);
            $access = $this->checkDocumentRights($id, 'edit');
            if (!$access) {
                AppEnv::echoError('err-no-rights-document');
                return;
            }
            if ($access < 2) {
                AppEnv::echoError('err_policy_not_editable');
                return;
            }
            $authorId = $this->_rawAgmtData['userid'];
            $this->calc = isset($rd['calc_params']) ? $rd['calc_params'] : array();
            $this->srvcalc = isset($rd['ins_params']) ? $rd['ins_params'] : array();
            $benrisks = $childrisks = array();
            $headDept = $this->_rawAgmtData['headdeptid'];
        }
        $deptReq = $this->_deptReq = OrgUnits::getOuRequizites($headDept, $this->module);
        $metaType = OrgUnits::getMetaType($headDept);
        $getSeller = (!empty($deptReq['get_seller']) && $this->input_seller);

        $ouparamSet = isset($deptReq['ouparamset']) ? $deptReq['ouparamset'] : '';

        $iamUw = $this->isUnderWriter();

        if (method_exists($this, 'getBenefRisks'))
            $this->getBenefRisks($benrisks,$childrisks);
        # WriteDebugInfo("stmt, calc:", $this->calc);
        # WriteDebugInfo("stmt, srvcalc:", $this->srvcalc);
        if(isset($this->calc['program'])) $prog = $this->calc['program'];
        elseif(isset($this->calc['progid'])) $prog = $this->calc['progid'];
        elseif(isset($this->srvcalc['progid'])) $prog = $this->srvcalc['progid'];
        else {
            AppEnv::echoError('Ошибка при загрузке данных по договору (нет ИД программы)!');
            return;
        }
        # AppEnv::appendHtml('calc <pre>' . print_r($this->calc,1). '</pre>'); AppEnv::finalize(); return;
        # writeDebugInfo("stmt, calc: ", $this->calc);
        if (!$this->multiInsuredReady) {
            if (!empty($this->calc['equalinsured'])) {
                if ($this->calc['equalinsured'] == 1 || $this->calc['equalinsured'] === 'Y')
                    $this->multi_insured = 0; # стр = Застрах.
                elseif($this->calc['equalinsured'] === 'N') # в калькуляторе lifeag такие значения Стр=Застр = Y/N
                    $this->multi_insured = -1; # строго - Страхователь и Застр - Разные люца!
            }
        }
        # exit("policymodel/stmt, multi_insured: [{$this->multi_insured}], insured_adult:[$this->insured_adult] insured_child=[$this->insured_child]");

        $superop = $this->isAdmin() ? 'true':'false';

        useJsModules(WebApp::FOLDER_APPJS.'policymodel.js');
        $initCity = "cityCodes = [" . AppLists::getCityIdList() . '];';
        AddHeaderJsCode($initCity);
        useJsModules(WebApp::FOLDER_APPJS.'plcutils.js');
        useJsModules('maskedinput');

        $jscode = "superOper = $superop; policyModel.init('".$this->module."'); \$(\"input.docpodr\").mask(\"999-999\");";

        if ($this->enable_touw && !empty($_SESSION['policy_packet_mode'])) {
            $jscode .= 'plcUtils.packetMode = true;';
        }

        if ($this->agmt_editable) {
            if (is_callable($this->agmt_editable)) $b_edit = call_user_func($this->agmt_editable,$this->_rawAgmtData);
            elseif (method_exists($this,$this->agmt_editable)) {
                $callbck = $this->agmt_editable;
                $b_edit = $this->$callbck();
            }
            else $b_edit = TRUE;
            if ($b_edit) $jscode .= "policyModel.plcEditable = true;\n";
            if ($this->married_status >= 1) $jscode .= "\n$(\"#insrmarried,#insdmarried\").prop('required', true);\n";

            if(DadataUtl::isActive()) {
                UseJsModules('js/jquery.suggestions.min.js');
                UseJsModules('js/addrHelper.js'); // Dadata - поиск адреса
                UseJsModules('js/bankHelper.js'); // Dadata-поиск названия к/счета БИК банка
                UseJsModules('js/fmsUnitHelper.js'); // Dadata - место выдачи паспорта по коду подразд
                UseCssModules('css/suggestions.css');
                HeaderHelper::addJsCode(DadataUtl::getJsCode());
            }
            $jscode .= '$("input.docpodr").mask("999-999");';
            if(self::ZIPCODE6) $jscode .= '$("input.zipcode").mask("999999");'; # почтовые индексы 6 цифр

        }

        if (!$iamUw && $this->uw_after_edit && $id>0) {
            $curstate = isset($this->_rawAgmtData['stateid'])?$this->_rawAgmtData['stateid'] : 0;
            $jscode .= "plcUtils.curstate = $curstate; ";
        }
        $jscode .= "$('input.number').on('change',NumberRepair);\n$('input.relate').autocomplete({source: policyModel.relations, minLength:2});";
        if (!empty($this->calc['equalinsured'])) {
            # перекл. disabled в полях дт.рожд, пол страхователя, т.к. он т застрахованный
            $jscode.= "policyModel.chgEqualInsured();";
        }

        $jscode .= "\n policyModel.loadStmt('$id',0,'$calcid');"; # загрузка данных по переданному ID/calc-из сессии(калькулятор)
        $rusId = PlcUtils::getRussiaCodes();
        # WriteDebugInfo("rusID:", $rusId);
        if (count($rusId)) $jscode .= "policyModel.rusCodes = [" . implode(',', $rusId) . "];\n";

        # @since 1.45 - свой js код для ред-я в stmt()
        if (method_exists($this, 'stmtJsCodeReady')) $jscode .= $this->stmtJsCodeReady($id);
        # {upd/2025-10-30 - ф-ционал CTRL-C / CTRL-V для копирования Страхователя/Застрах, в Выг-Приобр.
        if(self::$copyPastePersons) $jscode .= '$("input.cp_name").on("keyup", policyModel.nameKeyUp);';

        if ($getSeller) {
           # цепляю к полю авто-заполнение (autocomplete)
           $sellerJs = InputSeller::getJsAutoComlete($authorId);
           if ($sellerJs) {
               $jscode .= $sellerJs;
           }
        }

        HeaderHelper::addJsCode($jscode, 'ready');
        Persons::setEditAttribs($this->_deptReq);

        $isChild = FALSE;
        $pagetitle = AppEnv::getLocalized($this->module . ':stmt_title');
        if (!$pagetitle) $pagetitle = AppEnv::getLocalized('title_life_agreement');

        AppEnv::setPageTitle($pagetitle);
        $frmwidth = 800;
        if (WebApp::$IFACE_WIDTH > 0) $frmwidth = min($frmwidth, WebApp::$IFACE_WIDTH);
        # WriteDebugInfo('stmt, $frmwidth:', $frmwidth,  'WebApp::$IFACE_WIDTH:' , WebApp::$IFACE_WIDTH);
        # {upd/08.07.2014} Кажется, бизнес прикрывают...:(
        $this->checkGlobalBlocking($id);
        $subtitle = ($id>0 || $this->nonlife) ? 'Договор, ' : 'Заявление, ';
        $prgName = $this->getProgramName($prog);
        # $randval = 'c' . date('YmdHi') . rand(100000,9900000); # чтобы калькуляции из разных окон браузераа не конфликтовали, разносить их в сессии
        $html = <<< EOHTM
<form id='fm_stmt' class='was-validated'><input type='hidden' name='action' value='updatestmt'><input type='hidden' name='stmt_id' id='stmt_id' >
    <input type='hidden' name='uw_confirmed' id='uw_confirmed' value='0' />
    <input type="hidden" name="calcid" id="calcid" value="$calcid" />
    <input type="hidden" name="anketaid" id="anketaid" value="" />
    <div class="card"><div class="text-center card-header"><b class="fs-4">$subtitle программа $prgName</b></div>
EOHTM;

        if(method_exists($this, 'beforeStmt')) {
            $this->beforeStmt();
        }
        if ($this->nonlife) $chk_insrIsInsd = '';
        elseif ($this->multi_insured === 0) {
            $chk_insrIsInsd = '<input type="hidden" name="equalinsured" id="equalinsured" value="1"/> (Страхователь и Застрахованный - одно лицо)';
        }
        elseif ($this->multi_insured === 'child') { # Застрахованный - всегда ребенок
            $chk_insrIsInsd = '<input type="hidden" name="equalinsured" id="equalinsured" value="0"/>';
        }
        elseif ($this->multi_insured >= 1) {
            $chk_insrIsInsd = '<input type="checkbox" name="equalinsured" id="equalinsured" value="1" onclick="policyModel.chgEqualInsured()"/><label for="equalinsured"> Страхователь и Застрахованный - одно лицо</label>';
        }
        elseif ($this->multi_insured == -1) { # Стр и Застр - разные лица
            $chk_insrIsInsd = '<input type="hidden" name="equalinsured" id="equalinsured" value="0"/>';
        }

        if ( intval($id)==0 && $this->enable_touw ) {
            $addtoUw = "<div class=\"bounded\"><label><input type='checkbox' name='user_touw' id='user_touw' value='1' onclick='plcUtils.clickPacketMode()'> %create_policy_in_uw%</label>" . PlcUtils::helpLink('policy-packetmode') . "</div>";
            AppEnv::localizeStrings($addtoUw);
            $html .= $addtoUw;
        }
        $investAnketaMode = $payOnce = FALSE;
        if ( $this->invest_anketa && $newAgr && ($ouInv=PlcUtils::isInvestAnketaActive($this->module)) ) {
            # writeDebugInfo("stmt, calc: ", $this->calc);
            if (isset($this->calc['m1']))
                $payOnce = ($this->calc['m1'] === 'once');
            if (isset($this->calc['pppperiodicity']))
                $payOnce = ($this->calc['pppperiodicity'] <= 0);

            # else echo 'data <pre>' . print_r($this->calc,1). '</pre>';# TODO: прочие варианты поля рассрочки из калькулятора!!!
            # exit("ouInv=$ouInv, payOnce=[$payOnce]");
            $investAnketaMode = $payOnce;
            # if(!empty(AppEnv::$_p['anketaid'])) $anketaid = AppEnv::$_p['anketaid'];
        }

        $sumPay = $this->getTotalPremium(0,1);

        /**
        # writeDebugInfo("sum Pay: $sumPay, once: [$payOnce]");
        if (!$payOnce && $sumPay >= PlcUtils::getClientAnketaLimit()) {
            $this->insrinn = 1;
            if ($this->snils) $this->snils = 2;
        }
        else {
            $this->insrinn = 0;
            $this->snils = min(1, $this->snils); # снижаю до "СНИЛС не требуется"
        }
        **/
        # {upd/2021-04-12} добавляю кнопку для связывания с инвест-анкетой
        if ($investAnketaMode && self::$earlyInvestAnketa) {
            $html .= @file_get_contents(AppEnv::getAppFolder('templates') . 'block_bind_invanketa.htm');
            if(AppEnv::getConfigValue('invest_anketa_reuse')) {
                $html = str_replace('%btn_bind_anketa_free%','%btn_bind_anketa%',$html);
            }
            # '<div class="bounded" id="blk_bind_anketa"><input type="button" id="btn_bindanketa" class="btn btn-primary" value="%btn_bind_anketa%" onclick="policyModel.bindAnketa()" title="%title_bind_anketa%"></div>';
        }

        if ($this->insurer_enabled) {
            $html .= "<div class='border-top'><legend>" . AppEnv::getLocalized('label_insurer') . $chk_insrIsInsd .'</div>'
                  . HtmlBlocks::pageInsurer($this) . '</fieldset>';
        }
        # {upd/2022-07-21} - блок ввода признаков риска у клиента
        if($this->b_clientRisky) {
            $html .= file_get_contents(AppEnv::getAppFolder('templates/') . 'block_client_risks.htm');
        }

        if ($this->income_sources) {
            $html_sources = @file_get_contents(ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'income_source.htm');
            $html .= $html_sources;
        }
        # доп.поля ввода для анкеты клиента
        if($this->in_anketa_client) {
            $html_sources = @file_get_contents(AppEnv::getAppFolder('templates/') . 'anketa_client.htm');
            $html .= $html_sources;
        }

        $separatedInsured = FALSE;
        if (!$this->nonlife) {

            if($this->multi_insured !== 'child') {
                if ($this->multi_insured >= 1 || $this->multi_insured == -1) {
                    $html .= HtmlBlocks::pageInsured($this);
                    $separatedInsured = TRUE;
                }
                if ($this->multi_insured == 2) { # форма на 2х застрахованных, по условиям продукта
                    $html .= HtmlBlocks::pageInsured($this, 2);
                }
            }
            $isChild = ($this->multi_insured === 'child' || $this->policyHasChild($this->calc, $this->srvcalc) || !empty($this->insured_child));
            # writeDebugInfo("isChild: [$isChild], this->multi_insured=[$this->multi_insured] this->insured_child=[$this->insured_child]");
            if ($isChild) {
                $html .= "<hr><legend>" . AppEnv::getLocalized('label_child') . '</legend>'
                    . HtmlBlocks::pageInsuredChild($this);
                if (method_exists($this, 'chooseChildBenef'))
                    $html .= $this->chooseChildBenef($this->calc, $this->srvcalc);
                else $html .= $this->childBenefFormHtml($separatedInsured);
            }
        }

        if ($ouparamSet != '') {
            # {upd/2021-05-04} добавляю на форму ввода поля под "спец-параметры" для партнера
            $xmlOuSet = OrgUnits::getOuParamSetFile($ouparamSet);
            $ouDef = new \ParamDef($xmlOuSet);
            $paramSubForm = $ouDef->htmlForm();
            if (!empty($paramSubForm)) {
                $html .= $paramSubForm;

                if($metaType == OrgUnits::MT_BANK) {
                    # в банках - авто-заполнение доп-поля "название компании
                    $bankName = OrgUnits::GetDeptName($headDept);
                    $autoPartnerJs = "$(\"input[name=partner_lname]\").val('$bankName').prop('readonly',true);";
                    addJsCode($autoPartnerJs, 'ready');
                }
            }
        }
        if(method_exists($this, 'AdultBenefNeeded')) {
            # в модуле может быть своя логика добавления ВП для взрослого
            $adultBenef = $this->AdultBenefNeeded();
        }
        else
            $adultBenef = ($this->b_benefs && $this->multi_insured !== 'child');
        if ($adultBenef) {
            # Если осн.застрахованный - ребенок, блок ВП не нужен!?
            $benTitle1 = HtmlBlocks::$benTitles[0]; # {upd/2023-06-02} здесь возможна подстава других заголовков (Подарок реб)
            $benTitle2 = HtmlBlocks::$benTitles[1];
            $html .= '<div id="benef_block" class="border-top"><div class="darkhead px-3">' . $benTitle1 # AppEnv::getLocalized('label_benefs_long')
                  . " &nbsp; <input type=\"checkbox\" name=\"no_benef\" id=\"no_benef\" value=\"1\" onclick=\"policyModel.chgNoBenef()\" /><label for=\"no_benef\" class=\"ms-2\">$benTitle2</label></div>"
                  . HtmlBlocks::pageBenefs($this) . '</div>';
        }
        else $html .= "<input type='hidden' name='no_benef' id='no_benef' value='1'>";

        $restHtml = $this->home_folder . 'html/stmt_restdata.htm';
        if (is_file($restHtml)) {
            $html .=  @file_get_contents($restHtml);
        }
        if ($getSeller && is_file(ALFO_ROOT.'templates/block_seller.htm')) {
            $html .=  @file_get_contents(ALFO_ROOT.'templates/block_seller.htm');
           # цепляю к полю авто-заполнение (autocomplete)
           /**
           $sellerJs = InputSeller::getJsAutoComlete($authorId);
           if ($sellerJs) {
               HeaderHelper::addJsCode($sellerJs,'ready');
               //useJsModules('autocomplete');
           }
           **/
        }
        $subst = [

            '{visible_child}' => ($isChild ? '': self::HTM_HIDE)
            ,'<!-- married_status -->' => ($this->married_status ? '': self::HTM_HIDE)

        ];
        AppEnv ::localizeStrings($html);
        AppEnv::appendHtml( strtr($html, $subst));

        $noConfirm = ($this->stmt_noconfirm ? '1':'0');
        // AppEnv::appendHtml( '</div>');
        AppEnv::appendHtml( "<div class='area-buttons card-footer'><input type=\"button\" class=\"btn btn-primary w200\" onclick=\"policyModel.saveStmt($noConfirm)\" value=\"".AppEnv::getLocalized('btn_save'). '"/></div>');
        AppEnv::appendHtml( '</div></form>');

        AppEnv::finalize();
        # if(AppEnv::isStandalone()) exit;
    }

    /**
    * Расширяемая ф-ция вывода формы редактирования договора
    * 'prolongid=NNN' вместо id=NNN - пролонгация договора
    */
    public function agredit($apiParams = FALSE) {
        if(is_array($apiParams)) AppEnv::$_p = $apiParams; # пришел запрос со страницы лендинга на отрисовку формы ввода
        $id = AppEnv::$_p['id'] ??  0;
        $calcid = AppEnv::$_p['calcid'] ?? '';
        $includeBlocked = (!empty($id) || AppEnv::isTestAccount());
        $b_restrict = AppEnv::getConfigValue($this->module . '_disable_activity',0);
        if(in_array($b_restrict, [1,2]) && empty($id)) {
            AppEnv::echoError('err-new-agmt-blocked');
            return;
        }

        # writeDebugInfo("$this->module/$id/includeBlocked = [$includeBlocked]");
        $prodid = AppEnv::$_p['prodid'] ?? AppEnv::$_p['programid'] ?? '';
        $this->deptProdParams($this->module, 0, $prodid, $includeBlocked);
        # WriteDebugInfo("module: [" .$this->module . "], deptCfg:", $this->_deptCfg);

        if (empty($id) && empty($this->_deptCfg)) {
            if($apiParams || AppEnv::isApiCall()) return AppEnv::getLocalized('err-nodept-prod-cfg');
            AppEnv::echoError('err-nodept-prod-cfg');
            if(AppEnv::isStandalone()) exit;
            return;
        }
        $findid = $id;
        $prolong = $anketaid = 0;
        if (!empty(AppEnv::$_p['prolongid'])) { # вызов пролонгаци от указанного полиса
            $findid = AppEnv::$_p['prolongid'];
            $prolong = 1;
        }
        $headDept = 0;
        $this->checkGlobalBlocking($findid);

        $module = $this->module;

        HeaderHelper::useJsModules('js/policymodel.js,js/plcutils.js,maskedinput');
        # HeaderHelper::useJsModules(WebApp::FOLDER_APPJS.'plcutils.js');
        if ($this->js_modules) HeaderHelper::useJsModules( $this->js_modules);

        $initCity = "cityCodes = [" . AppLists::getCityIdList() . '];';
        AddHeaderJsCode($initCity);

        $subst = [
             '<!-- married_status -->' => ($this->married_status ? '': self::HTM_HIDE)
            ,'<!--pcurrency -->' => ''
        ];
        /*
        if (is_file($this->home_folder . $this->module . '.js'))
            HeaderHelper::useJsModules($this->home_folder . $this->module . '.js');
        */
        if (AppEnv::$auth->getAccessLevel($this->privid_editor)<=0) {
            AppEnv::echoError('err-no-rights');
            return;
        }
        $headDept = OrgUnits::getPrimaryDept();
        $metaType = OrgUnits::getMetaType($headDept);
        $codirovka = '';
        $authorId = AppEnv::$auth->userid;
        $pageTitle = '';
        if ( $findid ) {
            $this->loadPolicy($findid,'edit');

            # фиксирую подразд-е, в котором создан полис, для дальнейших зависящих вызовов
            if(!empty($this->_rawAgmtData['deptid'])) {
                PlcUtils::setPolicyDept($this->_rawAgmtData['deptid']);
            }
            if (!empty($this->_rawAgmtData['policyno'])) {
                $splCodes = explode('-', $this->_rawAgmtData['headdeptid']);
                $codirovka = $splCodes[0];
            }
            if (!empty($this->_rawAgmtData['headdeptid']))
                $this->deptProdParams($this->module, $this->_rawAgmtData['headdeptid'],$this->_rawAgmtData['prodcode'],1,$this->_rawAgmtData['programid']);

            if ($prolong) {
                if (!isset($this->_rawAgmtData['stateid'])) {
                    AppEnv::echoError('err_agmt_not_found');
                    exit;
                }

                $canProlong = PlcUtils::isProlongable($this->module, $findid);
                # проверяю, вдруг уже есть пролонгация для данного полиса
                if (!$canProlong || isset($canProlong['error'])) {
                    $err = isset($canProlong['error']) ? $canProlong['error'] : 'Полис не может быть пролонгирован!';
                    AppEnv::echoError($err);
                    exit;
                }

                $pageTitle .= ' ' . $this->_rawAgmtData['policyno'];

                $this->loadProlongLimits();

                # модифицирую поля с учетом что это пролонгация (пересчет даты начала...)
                # Проверки валидности операции пролонгации

                $days = diffDays(date('Y-m-d'), $this->_rawAgmtData['datetill']);
                if ($this->_rawAgmtData['stateid'] != PM::STATE_FORMED)
                    AppEnv::echoError('err_prolongate_impossible');
                if ($days > 0 && $days > $this->prolongate_before_end)
                    AppEnv::echoError('err_prolongate_too_early');
                if ($days < 0 && abs($days) > $this->prolongate_after_end)
                    AppEnv::echoError('err_prolongate_too_late');
            }

            $headDept = $this->_rawAgmtData['headdeptid'];
            $authorId = $this->_rawAgmtData['userid'];
            # Защита от ввода ИД "не того" договора прямо в строке URL
            if (isset($this->agmtdata['module']) && $this->agmtdata['module'] !== $this->module) {
                AppEnv::setPageTitle('Ошибка !');
                AppEnv::echoError(AppEnv::getLocalized('err_wrong_agreement_type')
                   . ' '. AppEnv::getLocalized($this->module.':main_title')
                );
            }
            $rd = $this->loadSpecData($findid);
            $access = $this->checkDocumentRights($findid, 'edit');
            if (!$access) {
                AppEnv::echoError('err-no-rights-document');
                return;
            }
            $myLevel = $this->getUserLevel();

            if($myLevel < PM::LEVEL_IC_ADMIN && !in_array($this->_rawAgmtData['stateid'], $this->editable_states)) {
                writeDebugInfo("ban editing, ", $this->editable_states);
                AppEnv::echoError('err-wrong-document-state');
                return;
            }
            $this->calc = isset($rd['calc_params']) ? $rd['calc_params'] : array();
            $benrisks = $childrisks = array();
        }

        if ($this->promo_enabled) {
            # include_once(AppEnv::FOLDER_APP . 'promocodes.php');
            PromoCodes::init($headDept, $this->module);
            PromoCodes::setPolicyMode(($id==0), $prolong);
            # WriteDebugInfo("promocods: dept=$headDept, module=$this->module, id=$id, prolong=[$prolong]");
        }

        $this->_deptReq = OrgUnits::getOuRequizites($headDept, $this->module);
        $programid = AppEnv::$_p['programid'] ?? AppEnv::$_p['prodid']  ?? 0; # $this->_rawAgmtData['programid'] ??
        $pageTitle = '';
        $baseTitleId1 = $module . ($id ? ':agredit_title':':agrnew'); # ($id) ? 'module:agredit' : 'module:agrnew';
        $baseTitleId2 = ($id ? 'title_agr' : 'title_new_agr');
        $baseTitle = AppEnv::getLocalized($baseTitleId1,'');
        if(!$baseTitle) $baseTitle = AppEnv::getLocalized($baseTitleId2);
        # writeDebugInfo("baseTitle $baseTitle");
        $visibleName = '';
        if (method_exists(AppEnv::$_plugins[$this->module], 'getFullProgramName')) {
            $visibleName = AppEnv::$_plugins[$this->module]->getFullProgramName($programid, $this->_rawAgmtData);
            $pageTitle = $baseTitle . ' '.$visibleName;
        }
        elseif (method_exists(AppEnv::$_plugins[$this->module], 'getVisibleProductName')) {
            $mod = $this->module;
            $visibleName = AppEnv::$_plugins[$this->module]->getVisibleProductName($headDept, $programid);
            $pageTitle = $baseTitle;
            if(mb_stripos($pageTitle, $visibleName)===FALSE) $pageTitle .= ' '. $visibleName;
            # writeDebugInfo("pageTitle $pageTitle");
        }
        else {
            $pageTitle = $baseTitle;
        }

        if(!$visibleName) {
            $subName = AppEnv::$_plugins[$this->module]->getProgramName($programid, $this->_rawAgmtData);
            # if(!empty($subName) ) writeDebugInfo("subName=$subName, pageTitle=$pageTitle");
            if(!empty($subName) && mb_stripos($pageTitle, $subName)===FALSE) $pageTitle = "$baseTitle $subName";
        }
        if ($prolong) {
            $title_id = 'agredit_prolongation';
            $pageTitle = AppEnv::getLocalized($title_id); # добавить номер дог.
        }
        /*
        if(method_exists($this, 'titleForAgredit')) {
            $pageTitle = $this->titleForAgredit($pageTitle);
            if(self::$debug) writeDebugInfo("pageTitle from titleForAgredit: $pageTitle");
        }
        */
        $ouparamSet = isset($this->_deptReq['ouparamset']) ? $this->_deptReq['ouparamset'] : '';
        $getSeller = (!empty($this->_deptReq['get_seller']) && $this->input_seller);

        $super = AppEnv::$auth->getAccessLevel($this->privid_super); # $this->privid_super
        $fixed = (!empty($this->_rawAgmtData['accepted']) || !empty($this->_rawAgmtData['docflowstate']));
        $superop = $super ? 'true':'false';
        if(isset($this->_rawAgmtData['stateid'])) {
            $canEdit = TRUE;
            $curStateid = $this->_rawAgmtData['stateid'];
            # ПП, может исправлятьполис в п/статусе "на доработке"
            if($this->_userLevel>=PM::LEVEL_IC_ADMIN && $curStateid == PM::STATE_FORMED
              && in_array($this->_rawAgmtData['substate'],
                [PM::SUBSTATE_AFTER_EDIT, PM::SUBSTATE_REWORK, PM::SUBSTATE_COMPLIANCE, PM::SUBSTATE_EDO2_FAIL]))
                $canEdit = TRUE;
            elseif ((!$super && $curStateid >= 9) && !$prolong && !in_array($curStateid, $this->editable_states))
                $canEdit = FALSE;

            if(!$canEdit) {
                AppEnv::setPageTitle('title_error');
                AppEnv::$msgSubst['{state}'] = self::decodeAgmtState($this->_rawAgmtData['stateid'],'',FALSE);
                writeDebugInfo("ban editing, ", $this->editable_states);
                AppEnv::echoError('err-wrong-document-state');
                return;
            }
        }
        $this->tarif = array();
        if (method_exists($this, 'getActiveTariff')) $this->tarif = $this->getActiveTariff($headDept);
        if (method_exists($this, 'agreditJsCode')) {
            $jsAgrEdit = $this->agreditJsCode($findid, $prolong);
            if(!empty($jsAgrEdit)) HeaderHelper::addJsCode($jsAgrEdit);
        }
        if ($this->promo_enabled) {
            if ( $jsPromo = PromoCodes::getJsCode() )
                HeaderHelper::addJsCode($jsPromo);
        }

        # {upd/2022-09-22} js коды для вызовов dadata на адресах
        if(DadataUtl::isActive()) {
            UseJsModules('js/jquery.suggestions.min.js');
            UseJsModules('js/addrHelper.js');
            UseJsModules('js/bankHelper.js'); // Dadata-поиск названия к/счета БИК банка
            UseJsModules('js/fmsUnitHelper.js'); // Dadata - место выдачи паспорта по коду подразд
            UseCssModules('css/suggestions.css');
            HeaderHelper::addJsCode(DadataUtl::getJsCode());
        }
        # Макса дял кода подразделения:
        HeaderHelper::addJsCode('$("input.docpodr").mask("999-999");', 'ready');
        # очистка ФИАС (полученного в DADATA) при любом ручном изменении полей адреса:
        HeaderHelper::addJsCode('$("input.clfias,select.clfias").on("change",policyModel.cleanupFias);', 'ready');

        $freezeInsured = $this->b_calculated ? 'true' : 'false';
        $rusId = PlcUtils::getRussiaCodes();
        if (is_array($rusId) && count($rusId)) $rusId = "[" . implode(',', $rusId) . "]";
        else $rusId = '[]';

        $freeClient = $this->isFreeClientPolicy();

        # при ред-нии eShop полиса в ALFO в списке стран будет "НЕ РОССИЯ"
        if ($freeClient) PlcUtils::showNotRussia();

        $jscodeReady = <<< EOJS
superOper = $superop;
policyModel.init('$module');
policyModel.rusCodes = $rusId;
policyModel.freezeInsured = $freezeInsured;
policyModel.confirmedSaveStmt = policyModel.saveAgmt;
EOJS;
        if (is_array($this->enable_prolongate) && count($this->enable_prolongate)>1) {
            $prList = [];
            foreach($this->enable_prolongate as $code) $prList[] = "'$code'";
            $jscodeReady .= 'policyModel.prolongCodes = [' . implode(',',$prList) . "];\n";
        }
        if(!empty($calcid)) {
            $keysrv = 'srv_'.$this->module;
            if(!empty($_SESSION[$calcid][$keysrv])) {
                # writeDebugInfo("srvcalc in session ",  $_SESSION[$calcid][$keysrv]);
                $prolongid = $_SESSION[$calcid][$keysrv]['previous_id'] ?? '';
                # Подгрузить то, что выбрано на калькуляторе ПЕРЕД данной формой ввода данных
                # AddHeaderJsCode("policyModel.loadStmt(0,'$prolongid','$calcid'); // load data from calc\n", 'ready');
            }
        }
        if ($id>0 && !empty($this->_rawAgmtData['previous_id'])) {
            # вспомню, что полис - пролонгация, и блокировать изменение важных параметров
            $prevPlc = $this->_rawAgmtData['previous_id'];
            $jscodeReady .= "$('input#prolong').val('$prevPlc');";
        }
        if ($this->insurer_enabled === 'UL') { # Страхователь - ТОЛЬКО ЮЛ - включаю ЮЛ, блокирую выбор ФЛ
            $jscodeReady .="\n$('#instype_2').prop('checked',true); $('#instype_1').prop('disabled',true); $('#lab_fl').hide(); policyModel.chgFizUr();";
        }

        if(method_exists($this, 'beforeStmt')) $this->beforeStmt();
        # writeDebugInfo("KT-02 this->multi_insured=[$this->multi_insured]");
        if (method_exists($this, 'agreditJsCodeReady')) $jscodeReady .= $this->agreditJsCodeReady($id);
        if ($this->married_status >= 1) $jscodeReady .= "\n$(\"#insrmarried,#insdmarried\").prop('required',true);\n";

        if ($findid || $calcid) $jscodeReady .= "policyModel.loadStmt('$findid',$prolong,'$calcid'); \$('input[name=stmt_id]').val($id);\n"; # загрузка данных по переданному ID
        elseif($anketaid) {
            $jscodeReady .= "policyModel.loadFromAnketa('$anketaid');";
        }

        if ( $this->insurer_enabled && $this->email_mandatory && !$freeClient ) { # email страхователя - покрасить как обязательный!
            $jscodeReady.= '$("#insremail").prop("required", true);';
        }
        HeaderHelper::addJsCode($jscodeReady, 'ready');
        Persons::setEditAttribs($this->_deptReq);

        $htmlStart = '';

        $frmwidth = 800;
        if (WebApp::$IFACE_WIDTH > 0) $frmwidth = min($frmwidth, WebApp::$IFACE_WIDTH);

        $html = '';
        AppEnv::setPageTitle($pageTitle);

        if (AppEnv::inBitrix()) AppEnv::appendHtml('<h3>'.$pageTitle.'</h3>');

        $this->checkGlobalBlocking($id);
        $disab = AppEnv::getConfigValue($this->module.'_disable_activity');
        # if (!$id && $disab) AppEnv::echoError('err_new_policy_disabled');
        # if($disab>1) AppEnv::echoError('err_all_disabled');

        if (!is_array($this->tarif)) AppEnv::echoError('err_no_active_tariff');

        if(method_exists($this,'beforeAgredit'))
            $this->beforeAgrEdit();

        if (isset($this->tarif['currency'])) $subst['<!--pcurrency -->'] = self::decodeCurrency($this->tarif['currency']);
        $stmt_id = ($id>0 ? $id : '');
        $htmlClientId = '';
        # writeDebugInfo("bindToclient:[$this->bindToclient], stmt_id=$stmt_id metaType=$metaType stmt_id=[$stmt_id]");
        if($apiParams) $bindCliActive = 0;
        else $bindCliActive = AppEnv::getConfigValue('lifeag_clientmgr',0);
        if($this->bindToclient && $metaType==OrgUnits::MT_AGENT && $bindCliActive>0) {

            if($stmt_id>0) { # при редактировании ранее введенного, при переключении Застрах=Страх будет перепрыгивать блок данных
                $agmtDta = AgmtData::getData($this->module, $stmt_id);
                $clientid = $agmtDta['clientid'] ?? 0;
                # writeDebugInfo("policy: loaded clientid: $clientid");
                if($clientid>0) AddHeaderJsCode("policyModel.bindClient = '$clientid';", 'ready');
            }
            else {
                $clientid = !empty(AppEnv::$_p['clientid']) ? intval(AppEnv::$_p['clientid']) : 0;
                if(empty($clientid)) {
                    # appEnv::echoError('err-client_must_be_set');
                    # вывел кнопку, открывающую диалог полиса-ввода клиента (с блокировкой кнопки Сохранить, пока не выбрали клиента)
                    $htmlStart = BindClient::inlineForm('.card-footer');
                    if($bindCliActive >= 10) {
                        appEnv::appendHtml($html.$htmlStart);
                        appEnv::finalize();
                        exit;
                    }
                    #else $htmlStart = BindClient::inlineForm('.card-footer');

                }
                if($clientid) AddHeaderJsCode("setTimeout(\"policyModel.bindToclient($clientid)\",300);", 'ready');
            }
            if($clientid) $htmlClientId = "<input type='hidden' name='clientid' id='clientid' value='$clientid' />";
        }

        $html .= $htmlStart . "<div class='card'><form id='fm_agr_$module' class='was-validated'><input type='hidden' name='stmt_id' id='stmt_id' value=\"$stmt_id\"/>"
            . "<input type='hidden' name='uw_confirmed' id='uw_confirmed' value='0' />" # для простановки "согласия на андеррайтинг"
            . "<input type='hidden' name='prolong' id='prolong' value='$prolong' />"
            . "<input type='hidden' name='anketaid' id='anketaid' value='$anketaid' />"
            . $htmlClientId
        ;
        if(!$stmt_id) {
            # Пока до конца не реализовано!
            # Новый дог, менеджер - добавляю код выблра агента "от имени которого..."
            $myagents = $this->getMyAgents();
            if($myagents) $html .= HtmlBlocks::chooseAgentHtml($myagents);
        }

        $html .=   "<div class='p-2'>";

        $chk_insrIsInsd = '';

        if (!$this->nonlife) {
            if (empty($this->multi_insured)) {
                $chk_insrIsInsd = ' &nbsp; <input type="hidden" name="equalinsured" id="equalinsured" value="1"/> / Застрахованный';
            }
            elseif ($this->multi_insured === 'child') { # Застрахованный - всегда ребенок
                $chk_insrIsInsd = ' &nbsp; <input type="hidden" name="equalinsured" id="equalinsured" value="0"/>';
            }
            elseif ($this->multi_insured >= 1 && $this->multi_insured<100) {
                $chk_insrIsInsd = ' &nbsp; <label><input type="checkbox" name="equalinsured" id="equalinsured" '
                    . 'value="1" onclick="policyModel.chgEqualInsured()"/> Страхователь и Застрахованный - одно лицо</label>';
            }
        }
        if(!$apiParams) {
            if (!$id && $this->invest_anketa && PlcUtils::isInvestAnketaActive($this->module) && self::$earlyInvestAnketa) {
                # активна инвест-анкета для программы и партнера, включаю кнопку выбора анкеты
                $html .= @file_get_contents(AppEnv::getAppFolder('templates') . 'block_bind_invanketa.htm');
                # меняю ИД подсказки если разрешено повт.использрвание инв анкет:
                if(AppEnv::getConfigValue('invest_anketa_reuse')) {
                    $html = str_replace('%btn_bind_anketa_free%','%btn_bind_anketa%',$html);
                }
                # $html .= '<div class="bounded" id="blk_bind_anketa"><input type="button" id="btn_bindanketa" class="btn btn-primary" '
                #  . 'value="%btn_bind_anketa%" onclick="policyModel.bindAnketa()" title="%title_bind_anketa%"></div>';
            }
        }

        if (!$prolong && $this->enable_prolongate && empty($id) && empty($anketaid)) {
            # Если новый договор, разрешена пролонгация, добавляю блок с кнопкой "Пролонгация"
            $b_restrict = max(AppEnv::getConfigValue('alfo_disable_activity',0),
              AppEnv::getConfigValue($this->module . '_disable_activity',0));

            $prolongateOnly = ($b_restrict == 1.1);
            $html .= Prolongator::buttonBlock('','',$prolongateOnly);
        }

        if ($this->insurer_enabled) {

            $html .= "<div><legend>"
                . AppEnv::getLocalized('label_insurer') . "$chk_insrIsInsd</legend>";

            $html .= HtmlBlocks::pageInsurer($this) . '</div>';
            # {upd/2022-07-21} - блок ввода признаков риска у клиента
            if(!$apiParams) {
                if($this->b_clientRisky) {
                    $html .= file_get_contents(AppEnv::getAppFolder('templates/') . 'block_client_risks.htm');
                }
                if ($this->income_sources === 'agredit') {
                    $html_sources = @file_get_contents(ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'income_source.htm');
                    $html .= $html_sources;
                }
                if ($this->income_sources === 'agredit_radio') {
                    $html_sources = @file_get_contents(ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'income_source_radio.htm');
                    $html .= $html_sources;
                }

                # доп.поля ввода для анкеты клиента
                if($this->in_anketa_client) {
                    $html_sources = @file_get_contents(AppEnv::getAppFolder('templates/') . 'anketa_client.htm');
                    $html .= $html_sources;
                }
            }

        }
        if ($this->multi_insured == -1 || $this->multi_insured == 1 || $this->multi_insured == 2) {
            $html .= HtmlBlocks::pageInsured($this);
        }

        if ($this->multi_insured == 2) { # форма на 2х застрахованных, по условиям продукта
            $html .= HtmlBlocks::pageInsured($this, 2);
        }

        elseif ($this->multi_insured >=3) { # TODO: форма на список застрахованных (типа ВЗР)
            if (is_file($this->home_folder . '/insuredlist.php')) {
                include_once($this->home_folder . '/insuredlist.php');
            }
            if (class_exists('InsuredList')) {
                $html .= '<hr><legend>Список Застрахованных</legend>'
                  . InsuredList::getHtmlInput($this->multi_insured);
            }
        }
        if ($this->insured_child) {
            if (is_callable($this->insured_child)) {
                $html .= call_user_func($this->insured_child);
            }
            else {
                $childParams = explode(',', $this->insured_child);
                $chOption = $hideAttr = '';
                $maxChild = 1;
                foreach($childParams as $item) {
                    if (is_numeric($item)) $maxChild = max($maxChild, intval($item));
                    # можно задать макс. число Застрахованных детей: например, "option,3"
                }
                # writeDebugInfo("$maxChild= $maxChild");
                if ($maxChild > 1) {
                    # вывод N форм для детей (все с чек-боксом, если есть "option")
                    $html .= HtmlBlocks::drawMultiChildForm($this, $childParams, $maxChild);
                }
                else {
                    $htmlChild = HtmlBlocks::pageInsuredChild($this);
                    if (in_array('option', $childParams)) {
                        $chOption = '<input type="checkbox" name="b_child" id="b_child" value="1" onclick="policyModel.chgBchild(this)">';
                        $hideAttr = 'class="hideme"';
                    }
                    $html .= "<hr><legend>$chOption" . AppEnv::getLocalized('label_child') . '</legend>'
                          . "<div id='block_child' $hideAttr>$htmlChild</div>";
                }
            }
        }

        if (!$apiParams && $getSeller && is_file(ALFO_ROOT.'templates/block_seller.htm')) {
            $html .=  @file_get_contents(ALFO_ROOT.'templates/block_seller.htm');
            # цепляю к полю авто-заполнение (autocomplete)
            $sellerJs = InputSeller::getJsAutoComlete($authorId);
            if ($sellerJs) {
                addJsCode($sellerJs,'ready');
                //useJsModules('autocomplete');
            }
        }

        if (!$apiParams && $ouparamSet != '') {
            # {upd/2021-05-04} добавляю на форму ввода поля под "спец-параметры" для партнера

            $xmlOuSet = OrgUnits::getOuParamSetFile($ouparamSet);
            $ouDef = new ParamDef($xmlOuSet);
            $paramSubForm = $ouDef->htmlForm();
            if ($paramSubForm) {
                $html .= $paramSubForm;

                if($metaType == OrgUnits::MT_BANK) {
                    # в банках - авто-заполнение доп-поля "название компании
                    $bankName = OrgUnits::GetDeptName($headDept);
                    $autoPartnerJs = "$(\"input[name=partner_lname]\").val('$bankName').prop('readonly',true);";
                    addJsCode($autoPartnerJs, 'ready');
                }

            }
        }
        # Блок редактируемых параметров страхования, для коробочных - зона выбора "вариантов коробки"
        $insParams = '';
        if (method_exists($this,'agreditMainParams')) $insParams = $this->agreditMainParams($this->tarif);
        elseif (is_file($this->home_folder . 'html/agredit-params.htm')) {
            $insParams = file_get_contents($this->home_folder . 'html/agredit-params.htm');
            if(method_exists($this, 'editDataParams')) {
                $substRest = $this->editDataParams();
                # В шаблоне есть блоки, требующие подстановки данных (опции в SELECT и тп)
                if(is_array($substRest)) $insParams = strtr($insParams, $substRest);
                # file_put_contents('tmp/editblock.htm', $insParams);
            }
        }
        if ($this->promo_enabled) {
            PromoCodes::getHtmlcode($insParams);
        }
        if ( $insParams) $html .= $insParams;

        # зона выгодоприобретателей...
        # {upd/2023-11-29} новый кейс - автоматом показывать блоки под ребёнка, если у "взрослого" ввели дату рпожд, и оказалось это дитя
        if($this->insured_adult === '1AC' || $this->insured_flex  || !empty($this->insured_child)) {
            $bCBenInsured = !empty($this->insured_child);
            $childBlock = HtmlBlocks::childBenefFormHtml($this, $bCBenInsured, TRUE, TRUE);
            $html .= $childBlock;
            # writeDebugInfo("added child block ", $childBlock);
        }
        # agredit: Блок ввода Выгодоприобретателей
        if (!$apiParams && $this->b_benefs) {
            $benTitle1 = HtmlBlocks::$benTitles[0]; # {upd/2023-06-02} здесь возможна подтсава других заголовков (Подарок реб)
            $benTitle2 = HtmlBlocks::$benTitles[1];
            $html .= '<div id="benef_block" class="p-2 border-top"><div class="darkhead">' . $benTitle1 # AppEnv::getLocalized('label_benefs_long')
                  . " &nbsp; <input type=\"checkbox\" name=\"no_benef\" id=\"no_benef\" value=\"1\" onclick=\"policyModel.chgNoBenef()\" /><label for=\"no_benef\" class=\"ms-2\">$benTitle2</label></div>"
                  . HtmlBlocks::pageBenefs($this) . '</div>';
        }
        else $html .= "<input type='hidden' name='no_benef' id='no_benef' value='1'>";

        $jscodeReady = '';
        # {upd/2025-10-30 - пробую ф-ционал CTRL-C / CTRL-V для копирования ЗАстрах, в ВП или ещё куда
        if(self::$copyPastePersons) {
            $jscodeReady .= '$("input.cp_name").on("keyup", policyModel.nameKeyUp);';
        }

        if($this->insured_flex) {
            $html .= "<input type='hidden' name='insured_type' id='insured_type' value='adult'/>";
            $jscodeReady .= '$("#equalinsured,#insdbirth,#datefrom").on("change", policyModel.flexInsured);';
            # добавляю обработку события для динамич.смены блоков ввода выг.приобретателя (для взрослого или ребенка)
        }

        if($jscodeReady) AddHeaderJsCode($jscodeReady, 'ready');

        $restHtml = $this->home_folder . 'html/stmt_restdata.htm';
        if (is_file($restHtml)) {
            $htmlRestString = @file_get_contents($restHtml);
            $html .= $htmlRestString;
        }

        if ($this->b_benefs && self::$debug) {
            WriteDebugInfo('raw_benefs:', $this->_rawBenefs);
        }
        $html = strtr($html, $subst);

        AppEnv::LocalizeStrings($html);

        $frmwidth -= 20;


        if(is_array($apiParams)) {
            # Вызов со страницы лендинга, возвращаю готовый HTML код
            # TODO: добавить туда куски кода, попавшие через addHeaderJsCode()
            # TODO: вместо JSON вызовов calculate нужны API calls! - своя версия policymodel.js
            return $html;
        }
        $code = <<< EOHTM
        </div>
        <div class="area-buttons card-footer">
            <input type="button" class="btn btn-primary w200" value="Сохранить" id="btn_agrsave" onclick="policyModel.saveAgmt()" />
        </div>
        </div>
        </form>
    </div>

EOHTM;

        $html .= $code;
        AppEnv::appendHtml($html);
        AppEnv::finalize();
        if(AppEnv::isStandalone()) exit;
    }

    # подгрузка на форму ввода полиса данных из инвест-анкеты
    public function loadFromAnketa() {
        $anketaid = AppEnv::$_p['anketaid'];
        $data = Investanketa::loadData($anketaid);
        $ret = '1';
        if (!empty($data['id'])) {
            $ret .= AjaxResponse::setValue('anketaid', $data['id'])
                . AjaxResponse::setValue('insrfam', $data['lastname'])
                . AjaxResponse::setValue('insrimia', $data['firstname'])
                . AjaxResponse::setValue('insrotch', $data['middlename'])
            ;
            if (!empty($data['email'])) $ret.= AjaxResponse::setValue('insremail', $data['email']);
            if (!empty($data['phone'])) {
                list($pref, $phone) = RusUtils::SplitPhone($data['phone']);
                # if ($pref) $ret.= AjaxResponse::setValue('insrphonepref', $pref);
                if ($phone) $ret.= AjaxResponse::setValue('insrphone', $phone);
            }

        }
        exit($ret);
    }

    /**
    * {updt/2022-10-26} - фиксация макс.даты выпуска, начала д-вия и прочие конечные фиксации
    * @param mixed $recid ID договора (stmt_id)
    * @param mixed $initDate (дата "начала д-вия" от которой рассчитать макс.дату выпуска
    * @param mixed $daysBack сколько дней от даты "выпуска" до даты начала д-вия
    * (новой датой "выпуска" становится дата последнего перерасчета)
    */
    public function finalSavings($recid, $initDate = FALSE, $daysBack=FALSE, $toBirth=0, $uwCodes = TRUE) {
        # writeDebugInfo("finalSavings($recid, initDate:[$initDate], daysback:[$daysBack],toBirth=$toBirth) from ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        # writeDebugInfo("finalSavings($recid)");
        if(method_exists($this, 'beforeFinalSavings')) {
            $progid = $this->_rawAgmtData['programid'] ?? 0;
            $this->beforeFinalSavings($recid, $progid);
        }
        if($daysBack === FALSE) {
            if(!isset($this->agmtdata['stmt_id'])) $this->loadPolicy($recid);
            if(!$daysBack && method_exists($this, 'getDaysToStart')) $daysBack = $this->getDaysToStart($this->agmtdata);
        }

        AgmtData::updateMaxRelDate($this->module, $recid, $initDate, $daysBack, $toBirth);

        # сохраняю все коды отправки на UW
        if($uwCodes) \UwUtils::saveAllUwCodes($this->module,$recid);
    }
    /**
    * Унифицированное сохранение договора после редактирования : agredit -> saveagmt
    *
    */
    public function saveAgmt($return=FALSE) {
        # self::$debug = 3;
        if(self::$debug>2) WriteDebugInfo("saveAgmt() [$return]", AppEnv::$_p);
        # exit('1' . AjaxResponse::showMessage("Data[$return]: <pre>" . print_r(AppEnv::$_p,1) . '</pre>'));
        $agentid = $curatorid = 0;
        $enablePast = FALSE; # станет TRUE если редактируется пролонгированный полис с "вчерашней" датой начала
        $super = AppEnv::$auth->getAccessLevel(PM::RIGHT_SUPEROPER) or (10<=AppEnv::$auth->getAccessLevel($this->privid_editor));
        $this->autoSpecFields();
        $uwcode = 0;

        $bRefresh = ($return === 'refreshdates');
        /*
        if(AppEnv::$auth->SuperVisorMode()) {
            error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
            ini_set('display_errors', 1);
        }
        */
        Sanitizer::sanitizeArray(AppEnv::$_p);
        if (AppEnv::isApiCall()) {
            $userid = AppEnv::getUserId();
            $deptid = AppEnv::$auth->deptid;
            if (self::$debug>1) WriteDebugInfo("saveAgmt() - вызов из API user: $userid/dept:$deptid, params: ", AppEnv::$_p);
            $this->_p = AppEnv::$_p;
        }

        $this->agmt_setstate = $this->init_state;
        # WriteDebugInfo("saveagmt params:", AppEnv::$_p); // exit('saveagmt debug pitstop');
        # AppEnv::$db->log(2);
        $module = $this->module;
        # if ($module === 'planb') self::$debug = 2;
        $b_restrict = max(AppEnv::getConfigValue($module . '_disable_activity',0),AppEnv::getConfigValue('alfo_disable_activity',0)) ;

        if ($super) $right = $super;
        else {
            $rid = $module . '_oper'; # eval($module . '::RIGHT_OPER'); # eval не работает!! :(
            # WriteDebugInfo("rights to check before save stmt:",[$rid, $this->privid_editor, $this->privid_super]);
            $right = AppEnv::$auth->getAccessLevel($this->privid_editor);

            if(!$right) {
                $right = AppEnv::$auth->a_rights[$this->privid_editor] ?? 0;
                # writeDebugInfo("right by seek in a_rights $this->privid_editor: [$right]");
            }
        }

        if (self::$debug>1) {
            WriteDebugInfo("all user rights:", AppEnv::$auth->a_rights);
            WriteDebugInfo("right for this module($this->privid_editor):[$right]");
        }

        $recid = empty($this->_p['stmt_id']) ? 0 : intval($this->_p['stmt_id']);
        $priceEditable = TRUE;

        $prolong = (empty($recid) && $this->enable_prolongate && (!empty($this->_p['prolong'])));
        $b_newAgr = empty($recid);
        if(self::$debug) writeDebugInfo("recid: $recid, b_newAgr=[$b_newAgr]");
        $err = FALSE;
        if($b_restrict>0) {
            # writeDebugInfo("prolong check: ", $this->_p);
            if ($b_restrict==2) $err = 'Ввод новых договоров и редактирование временно недоступно';
            elseif(empty($this->_p['id']) && empty($recid) && !$prolong && $b_restrict==1.1)
                $err = 'Временно разрешена только пролонгация !';
            elseif($b_restrict == 1 && $b_newAgr)
                $err = 'Ввод новых договоров временно недоступен';
        }

        if (!$err && !$right) {
            $err = AppEnv::getLocalized('err-no-rights');
        }

        $today = date('Y-m-d');
        if(!empty($this->_p['datefrom']))
            $dateFrom = to_date($this->_p['datefrom']);
        else $dateFrom = FALSE;

        if(!$err && $prolong && $b_newAgr) {
            # {upd/2022-08-10} проверяю непревышение дней до-после окончания д-вия
            $this->loadProlongLimits();
            # $prevPolicy = DataFind::findPolicyByPolicyNo($this->_p['prolong']);
            DataFind::init(FALSE, $this->module);
            $prevPolicy = DataFind::findPolicyByPolicyNo($this->_p['prolong'],FALSE,$this->module, 'P');
            # exit("1" . AjaxResponse::showMessage("<pre>prevPolicy: " . print_r($prevPolicy,1) . '</pre>'));

            if($prevPolicy['datetill'] > $today && $this->prolongate_before_end > 0) {
                $minDate = AddToDate($prevPolicy['datetill'],0,0,- $this->prolongate_before_end);
                if($today < $minDate)
                    $err = "До окончания действия продляемого полиса более {$this->prolongate_before_end} дней, пролонгация пока невозможна!";
            }
            if($prevPolicy['datetill'] < $today && $this->prolongate_after_end > 0) {
                $maxDate = AddToDate($prevPolicy['datetill'],0,0,$this->prolongate_after_end);
                if($today > $maxDate)
                    $err = "С момента окончания действия продляемого полиса прошло более {$this->prolongate_after_end} дней, пролонгация невозможна!";
            }
            if(!empty($prevPolicy['history']) && is_array($prevPolicy['history'])) {
                $maxProlong = AppEnv::getConfigValue('ins_limit_prolong_count',0);
                $pAction = AppEnv::getConfigValue('ins_limit_prolong_action', 'B');
                if($maxProlong>1 && count($prevPolicy['history']) >= $maxProlong) {
                     if($pAction === 'B')
                        $err = 'Договор не подлежит пролонгации, превышено допустимое число пролонгаций';
                     else {
                         $uwHard = substr($pAction, 1);
                         PlcUtils::setUwReasonHardness($uwHard, PM::UW_REASON_PROLONG_CHAIN);
                     }
                }
            }
        }

        if($err) {
            if(self::$debug) writeDebugInfo("returnig with error ", $err);
            if (AppEnv::isApiCall()) {
                return [
                  'result' =>'ERROR',
                  'message' => $err,
                  'userid' => AppEnv::$auth->userid,
                  'deptid' => AppEnv::$auth->deptid,
                  'data' => ['userid' => AppEnv::$auth->userid]
                ];
            }
            if(isAjaxCall()) exit('1' . AjaxResponse::showError($err));
            exit($err);
        }
        # exit('PIT-STOP-001');

        if ($b_newAgr && $this->_debugAdd) self::$debug = 1; # эмуляция с логированием

        $fizur = isset($this->_p['insurer_type']) ? $this->_p['insurer_type'] : 1;

        $headdept = 0;
        # Ищу "потеряшку" - прошлое незаконченное оформление тем же клиентом той же программы
        if(empty($recid) && AppEnv::isLightProcess()) {
            $recid = OnlineProcess::findMyAgreement($this->module);
            if(self::$debug) writeDebugInfo("OnlineProcess::findMyAgreement called, result: [$recid]");
        }

        if ($recid > 0) {
            $this->loadPolicy($recid,'edit');
            $headdept = $this->_rawAgmtData['headdeptid'];

            if($this->_rawAgmtData['stateid'] == PM::STATE_DRAFT) {
                PlcUtils::setDraftState(1);
                if(self::$debug) writeDebugInfo("старый полис черновик, вкл.режим проверок - draft");
            }

            if (!empty($this->_rawAgmtData['previous_id'])) $enablePast = TRUE;
            else $enablePast = in_array($this->_rawAgmtData['substate'],
              [PM::SUBSTATE_AFTER_EDIT, PM::SUBSTATE_REWORK, PM::SUBSTATE_EDITED, PM::SUBSTATE_COMPLIANCE]);
            # полис оформлен, на доработке - можно исправлять!
            $access = $this->checkDocumentRights($recid, 'edit');
            # exit("access: $access");
            # WriteDebugInfo("updating policy $recid, access: [$access]");
            if ($access < 1.5) {
                if (AppEnv::isApiCall()) {
                    if(self::$debug) writeDebugInfo("Returning with error/Low access [$access], userid:", AppEnv::getUserId());
                    return [
                      'result'=>'ERROR',
                      'message' => AppEnv::getLocalized('err-no-rights-document')
                    ];
                }
                AppEnv::echoError('err-no-rights-document');
            }
            # exit('PIT-SIOP-004');
            #  проверить статус у документа, отразить попытки обновления "оформленных/отмененных"
            $canEdit = $priceEditable = TRUE;
            $curStateid = $this->_rawAgmtData['stateid'];
            # ПП, может исправлять полис в п/статусе "на доработке"
            if($this->_userLevel>=PM::LEVEL_IC_ADMIN && $curStateid == PM::STATE_FORMED
              && in_array($this->_rawAgmtData['substate'],
              [PM::SUBSTATE_AFTER_EDIT, PM::SUBSTATE_REWORK, PM::SUBSTATE_COMPLIANCE,PM::SUBSTATE_EDO2_FAIL]))
              {
                $canEdit = TRUE;
                $priceEditable = FALSE; # нельзя менять условия страхования - policy_prem не должно меняться!
              }
            elseif ((!$super && $curStateid >= 9) && !$prolong && !in_array($curStateid, $this->editable_states))
                $canEdit = FALSE;

            if (!$canEdit) {
                $errMsg = AppEnv::getLocalized('err-wrong-document-state');
                # writeDebugInfo("Cant change");
                AppEnv::$msgSubst['{state}'] = self::decodeAgmtState($this->agmtdata['stateid'],'',FALSE);
                $errMsg = strtr($errMsg, AppEnv::$msgSubst);
                if(self::$debug) writeDebugInfo("error ", $errMsg);
                if (AppEnv::isApiCall()) {

                    return [
                      'result'=>'ERROR',
                      'message' => $errMsg
                    ];
                }

                AppEnv::echoError($errMsg);
            }
            if($this->_rawAgmtData['stateid'] == PM::STATE_DRAFT) {
                PlcUtils::setDraftState(1);
            }
        }
        else {
            $headdept = OrgUnits::getPrimaryDept();
            if($this->draft_state) # включаю режим сохранения в статусе Черновик, если не всё заполнили
                PlcUtils::setDraftState(1);

            # {upd/2020-05-29} могли передать ИКП агента и куратора - ищем их ИД по справочникам
            if (!empty($this->_p['ikp_agent'])) {
                $agentid = PlcUtils::findAgent($this->_p['ikp_agent']);
                if (!$agentid) $this->_err[] = 'Указан неверный код ИКП агента';
            }
            elseif (AppEnv::isApiCall() && $this->api_ikpMandatory>0) {
                $this->_err[] = 'Не передан код ИКП агента (ikp_agent)';
            }
            if (!empty($this->_p['ikp_curator'])) {
                $curatorid = PlcUtils::findCurator($this->_p['ikp_curator']);
                if (!$curatorid) $this->_err[] = 'Указан неверный код ИКП куратора';
            }
            elseif (AppEnv::isApiCall() && $this->api_ikpMandatory>1) {
                $this->_err[] = 'Не передан код ИКП куратора (ikp_curator)';
            }
            if(self::$debug) writeDebugInfo("new agmt curatorid: [$curatorid], err: ", $this->_err);
            if (count($this->_err)) {
                $message = implode(';',$this->_err);
                if(self::$debug) writeDebugInfo("error ", $message);

                if (AppEnv::isApiCall() || $return) return ['result'=>'ERROR', 'message'=>$message];
                exit('1' . ajaxResponse::showError($message));
            }
            # WriteDebugInfo("_p:", $this->_p);
            # $findOthers = (!$this->nonlife && $this->uwcheck_on_save);
            # проверяю все кроме Инвест-продуктов, клиент может купить несколько ИСЖ полисов
            /* т.к. у нас будет поиск кумуляций, "аналогичнвые полисы" здесь искать уже не надо
            if (!$this->nonlife && $this->uwcheck_on_save && ($this->product_type !== PM::PRODTYPE_INVEST)) {
                $ppref = (empty($this->_p['equalinsured']) || $fizur !=1) ? 'insd' : 'insr';
                if (!$prolong && $this->multi_insured < 3) {
                    # Ищу нет ли полисов на того же застрахованного
                    $findPars = [
                      'module' => $module,
                      'lastname' => $this->_p[$ppref.'fam'],
                      'firstname' => $this->_p[$ppref.'imia'],
                      'middlename' => $this->_p[$ppref.'otch'],
                      'birth' => $this->_p[$ppref.'birth'],
                      'doctype' => (!empty($this->_p[$ppref.'doctype']) ? $this->_p[$ppref.'doctype'] : ''),
                      'docser' => (!empty($this->_p[$ppref.'docser']) ? $this->_p[$ppref.'docser'] : ''),
                      'docno' => (!empty($this->_p[$ppref.'docno']) ? $this->_p[$ppref.'docno'] : ''),
                      'datefrom' => ($this->_p['datefrom'] ?? '')
                    ];
                    $found = PlcUtils::findSimilarPolicy($findPars);

                    # return ['result'=>'ERROR', 'message'=>'test', 'data'=>$found]; # debug stop
                    # WriteDebugInfo("findSimilarPolicy data:", $findPars); WriteDebugInfo("found result for new policy:", $found);

                    if (isset($found['result']) && $found['result']==='ERROR') {
                        if (AppEnv::isApiCall()) return $found;
                        AjaxResponse::exitError($found['message']);
                        #AppEnv::echoError($found['message']); exit;
                    }
                }
            }
            */
        }
        if(self::$debug>1) writeDebugInfo("KT-200 enablePast=[$enablePast]");
        $this->tarif = 0;

        $term = 1;
        $paytype = 'E'; # понадобится для расчета КВ

        $this->_deptReq = OrgUnits::getOuRequizites($headdept);
        $this->deptProdParams($this->module,$headdept);
        if(self::$debug) writeDebugInfo("this->_deptCfg: ", $this->_deptCfg);
        $ouparamSet = isset($this->_deptReq['ouparamset']) ? $this->_deptReq['ouparamset'] : '';

        if ( method_exists($this, 'getActiveTariff')) {
            $this->tarif = $this->getActiveTariff($headdept, $recid);
            if (!is_array($this->tarif)) AppEnv::echoError('err_no_active_tariff');
            if (!empty($this->tarif['term'])) $term = $this->tarif['term'];
        }
        if (self::$debug) WriteDebugInfo("KT-201, tarif:", $this->tarif);

        # смена настроек по условиям конкретного модуля
        if (method_exists($this, 'beforeSaveAgmt')) $this->beforeSaveAgmt();

        if ($this->insurer_enabled && !$bRefresh) {
            Persons::validateIndividual($this, $this->_p, 'insr', ($fizur>1));
            # exit('1' . AjaxResponse::showMessage('Persons::validateIndividual(insr) done: <pre>' . print_r($this->_err,1) . '</pre>'));

            if($this->b_clientRisky) AgmtData::validate(AppEnv::$_p, $this->_err, $this);
        }

        # exit('1' . AjaxResponse::showMessage(__FILE__ .':'.__LINE__.' KT-002 return:' . $return));

        $insrType = isset($this->_p['insurer_type']) ? $this->_p['insurer_type'] : 1;

        $equalinsured = ($this->multi_insured >= 100) ? 0 : ((empty($this->_p['equalinsured']) || $insrType==2) ? 0:1);
        # exit(__LINE__ . "-KT-001 equalinsured =['$equalinsured] insrType=[$insrType]");
        $cleanCbenef = FALSE; # надо будет зачистить от старого детского представителя
        $insuredType = $this->_p['insured_type'] ?? 'adult'; # если д.рожд.застрахованного дала ребенка (insured_flex=TRUE)
        # {upd/2024-06-13} теперь insured_type может быть "child,adult" - оба застрахованных по рез-татам выбора параметров/калькуляции
        if(!$this->nonlife && !$bRefresh) { # для полисов "не-жизни" или если чисто "обновить даты" этот блок пропускается

            if ( $insrType == 1 && $this->multi_insured>0 && $this->multi_insured<100) { # ФЛ
                $equalinsured = !empty($this->_p['equalinsured']) ? 1 : 0;
            }

            $dtBirthInsured = '';
            if ( !$this->insurer_enabled ) $equalinsured = 0;
            else $dtBirthInsured = isset($this->_p['insrbirth']) ? $this->_p['insrbirth'] : '';
            $childDelegate = $this->_p['child_delegate'] ?? '';

            # writeDebugInfo("child_delegate: [$childDelegate], insuredType=[$insuredType]");
            if (!$equalinsured) {
                $dtBirthInsured = isset($this->_p['insdbirth'])? $this->_p['insdbirth'] : '';
                Persons::validateIndividual($this, $this->_p, 'insd'); # проверяю застрахованного

                # TODO: $this->multi_insured > 1 - проверка списка ?
                # {updt/2023-11-29} - если ребенок, заведены данные на его представителя?
                if($this->insured_adult === '1AC' || strpos($insuredType,'child')!==FALSE || !empty($this->insured_child)) {
                    $this->addSpecFields('child_delegate');
                    if($childDelegate === 'N') {
                        Persons::validateBeneficiaries($this,'cbenef', $this->_p);
                        if(!in_array(PM::UW_REASON_CBEN_OTHER, $this->ignore_uw_reasons))
                            PlcUtils::setUwReasonHardness(10, PM::UW_REASON_CBEN_OTHER);
                    }
                    else {
                        $this->addSpecFields('cbenefrelate');
                        $cleanCbenef = TRUE;
                        if($childDelegate === 'Y' && $recid>0) {
                            Persons::cleanBeneficiaries($this, $recid, 'cbenef');
                            if(self::$debug) writeDebugInfo("1AC/child: очистил от cbenef");
                        }
                    }
                }
            }
            if ($this->multi_insured == 2 && !empty($this->_p['b_insured2'])) {
                Persons::validateIndividual($this, $this->_p, 'insd2'); # проверяю застрахованного-2
                # TODO:  может быть список застрах. - проверить всех!
            }
        }
        # exit('1' . AjaxResponse::showMessage('spec_fields: <pre>' . print_r($this->spec_fields,1) . '</pre>'));
        if( !$bRefresh ) {
            if ($this->multi_insured >=100 && method_exists($this, 'validateInsureds') ) {
                # список застрахованных: в backend-классе должен быть свой метод проверки
                $this->validateInsureds();
            }
            # exit( 'KT99: $equalinsured ' . print_r($equalinsured,1). " insrType = [$insrType]<br>");

            $childCfg = explode(',', $this->insured_child);
            if(!empty($childCfg[0])) {
                if ($childCfg[0] === 'option' && !empty($childCfg[1])) {
                    $maxChild = intval($childCfg[1]);
                    for($iChild = 1; $iChild<=$maxChild; $iChild++) {
                        $cpref = 'child' . $iChild;
                        if (!empty($this->_p['b_'.$cpref])) {
                            Persons::validateIndividual($this, $this->_p, $cpref); # проверяю застрахованного ребенка NN
                        }
                    }
                }
            }
        }
        $checkdata = array();
        if(!$bRefresh) {
            # {upd/2023-02-10} - захотели принудительного андеррайтинга при пролонгации
            if($prolong && !$recid) {
                if(PlcUtils::forceUwOnProlong($this->module)) {
                    $uwcode = $this->uw_reasonid = PM::UW_REASON_PROLONG;
                    PlcUtils::setUwReasonHardness(1,$uwcode);
                }
            }

            if (intval($recid) == 0) {
                if ($this->enable_touw) {
                    if(!empty($this->_p['user_touw'])) {
                        $uwcode = PM::UW_REASON_BY_USER;
                        PlcUtils::setUwReasonHardness(1,$uwcode);
                        $this->_p['uw_confirmed'] = 1; # незачем переспрашивать, агент сам включил
                    }
                    else # новый полис без подтверждения "в пакете" прекращает режим пакетного ввода
                        unset($_SESSION['policy_packet_mode']);
                }
            }
            $checkdata = FALSE;
            # из калькуляции могло придти включение режима андеррайтинга, сделан перерасчет:
            if (method_exists($this, 'agrCheckCalculate')) {
                if (self::$debug) WriteDebugInfo("saveAgmt KT-0041, call agrCheckCalculate");
                $checkdata = $this->agrCheckCalculate();
                if (!empty($checkdata['term'])) $term = $checkdata['term'];
                if ( $this->uw_reasonid > 0 ) {
                    $uwcode = $this->uw_reasonid;
                    # WriteDebugInfo("uwcode задан:",$uwcode);
                }
                # в процессе проверки могли установить код постановки на андеррайтинг!
            }
            # exit('1' . AjaxResponse::showMessage('after agrCheckCalculate: <pre>'.print_r($this->_err,1).'</pre>'));

            # if (self::$debug) WriteDebugInfo("saveAgmt KT-0042, before validateIncomeSources");

            if ($this->income_sources)  {
                \Persons::validateIncomeSources($this);
            }
            $this->validateSpecData();
        }
        # if(!count($this->_err)) exit('1' . AjaxResponse::showMessage("PITSTOP: <pre>" . print_r(AppEnv::$_p,1) . '</pre>'));

        if (self::$debug) WriteDebugInfo("saveAgmt KT-0043, after validateSpecData");

        $hookData = FALSE;
        if (!$bRefresh && !empty($this->beforeSaveAgr) && method_exists($this,$this->beforeSaveAgr)) {
            $callFnc = $this->beforeSaveAgr;
            $hookData = $this->$callFnc(1, 'fullcheck');
            if (self::$debug) WriteDebugInfo("calling  $callFnc result: ", $hookData);
        }
        $calculatedData = $hookData;
        if(isset($hookData['data']) && is_array($hookData['data']))
            $calculatedData = $hookData['data']; # при расчета через API основные результаты заносятся в [data]
        if(!empty($calculatedData['datefrom']) && \PlcUtils::isDateValue($calculatedData['datefrom']))
            $dateFrom = to_date($calculatedData['datefrom']);
        elseif(isset($this->_p['datefrom']) && \PlcUtils::isDateValue($this->_p['datefrom'])) {
            # проверка даты начала ?!
            $today = date('Y-m-d');
            $dateFrom = to_date($this->_p['datefrom']);

            if ($dateFrom < $today && !$prolong && !$super && !$enablePast) {
                $this->_err[] = "Некорректная дата начала действия полиса";
            }
        }
        if($this->policyHasChild()) {
            # exit('1' . AjaxResponse::showMessage('With child: <pre>' . print_r(AppEnv::$_p,1) . '</pre>'));
            $instype = $this->_p['insurer_type'] ?? '1';
            if(!empty($this->_p['child_delegate'])) {
                if($this->_p['child_delegate'] === 'Y' && $instype ==2) # ЮЛ
                    $this->_err[] = "Страхователь ЮЛ не может быть выбран Представителем Застрахованного ребенка!";
                elseif($this->_p['child_delegate'] === 'Z' && !empty($this->_p['equalinsured'])) # Зстрах.взрослого нет
                    $this->_err[] = "Нет взрослого Застрахованного, он не может быть выбран Представителем Застрахованного ребенка!";
            }
        }
        # проверки выгодоприобретателей
        if (!$bRefresh && !$this->nonlife && $this->b_benefs && empty($this->_p['no_benef']) && strpos($insuredType,'adult')!==FALSE) {
            Persons::validateBeneficiaries($this, 'benef',$this->_p);
            # $this->validateBeneficiaries('benef',$this->_p);
        }
        # exit('1' . AjaxResponse::showMessage(__LINE__ . ':Persons::validateIndividual(insr) done err: <pre>' . print_r($this->_err,1) . '</pre>'));

        # {upd/2020-11-13} - вызов хука перерасчета, если есть

        if (self::$debug) WriteDebugInfo("saveAgmt KT-0050 (after validateSpecData,validateBeneficiaries");

        $handlepno = '';
        if ($this->policyno_mode == 2 && !AppEnv::isApiCall()) { # ручной ввод
            $handlepno = isset($this->_p['policyno']) ? trim($this->_p['policyno']) : '';
            if (empty($handlepno)) $this->_err[] = 'Не заполнено поле ' . $this->policyno_title;
            else { # проверка уникальности введенного (TODO: некорректная - нет серии !)
                $where = array('module'=>$this->module, 'policyno' => $handlepno);
                if ($recid>0) $where[] = "(stmt_id <> $recid)";
                $found = AppEnv::$db->select(PM::T_POLICIES, array('where'=>$where,'fields'=>'COUNT(1) cnt','singlerow'=>1));
                # WriteDebugInfo('found non-unique:', $found);
                if (!empty($found['cnt'])) $this->_err[] = $this->policyno_title . ' : Введенное значение уже есть в базе! Введите уникальное';
            }
        }
        if (in_array('seller', $this->spec_fields) && isset($this->_p['seller']) && empty($this->_p['seller'])) {
            # writeDebugInfo("spec fields ", $this->spec_fields);
            $this->_err[] = 'Не введен Продавец';
        }

        if ( $ouparamSet != '' && empty($return) ) {
            # {upd/2021-05-04} "спец-параметры" партнера
            $xmlOuSet = OrgUnits::getOuParamSetFile($ouparamSet);
            $ouDef = new ParamDef($xmlOuSet);
            # echo 'data <pre>' . print_r($ouDef->getParams(),1). '</pre>'; exit;
            $paramNames = $ouDef->getParamNames();
            if ($paramNames) {
                $this->spec_fields = array_merge($this->spec_fields, $paramNames);
                # запоминаю, какие поля - обязательные
                PlcUtils::addRequiredFields($ouDef->getFields());
                $errs = PlcUtils::checkRequiredFields();
                if(count($errs)) {
                    $this->_err = array_merge($this->_err,$errs);
                    # $errAll = implode('<br>',$errs);
                    # exit('1' . AjaxResponse::showError($errAll));
                }
            }
            # writeDebugInfo("all spec fields: ", $this->spec_fields);
            # writeDebugInfo("_p:", $this->_p);
        }

        if(count($this->_err)) {
            if (self::$debug) WriteDebugInfo("saveAgmt KT-0060 errors:", $this->_err);
            $errstring = implode(';<br>',$this->_err);
            if (AppEnv::isApiCall() || $return) {
                return array(
                  'result'=>'ERROR',
                  'message' => $errstring
                );
            }
            exit('1' . ajaxResponse::showError($errstring, 'Ошибки в параметрах'));
        }
        if(self::$debug || PlcUtils::$debug) writeDebugInfo("this->uwCheckEvent: ", $this->uwCheckEvent, ' uw_hardness:'.PlcUtils::$uw_hardness);
        # в модуле возможны свои проверки для перевода на UW
        if(method_exists($this, 'uwChecking')) $this->uwChecking();

        if ( empty($return) && ($this->uwCheckEvent === 'save' && (PlcUtils::$uw_hardness<10 || $this->box_product))) {
            // если код андеррайтинга установился на этапе agrCheckCalculate, то больше никаких проверок не делаю
            if ( !$this->nonlife && ( $b_newAgr || $this->uwcheck_on_save>1 ) && $this->uwcheck_on_save ) {
                $datefrom = $this->_p['datefrom'] ?? '';
                # writeDebugInfo("Kt-002, datefrom: [$datefrom]");

                $pref = ($this->insurer_enabled && empty(AppEnv::$_p['equalinsured']) ) ?
                    'insd' : 'insr';
                if ($this->multi_insured>=100) $pref = 'insr';
                $saRisk = 0; # TODO: получить СС по риску СЛП (для инвестов - взносу)
                PlcUtils::performUwChecks('insd',$this->module, $recid,
                  rusUtils::capitalize($this->_p[$pref.'fam']),
                  rusUtils::capitalize($this->_p[$pref.'imia']),
                  rusUtils::capitalize($this->_p[$pref.'otch']),
                  $this->_p[$pref.'docser'],
                  $this->_p[$pref.'docno'],
                  $saRisk,
                  '',
                  (!empty($this->_p[$pref.'birth'])?$this->_p[$pref.'birth']:''),
                  $recid,0,0,$datefrom
                );
                if(self::$debug || PlcUtils::$debug)
                    writeDebugInfo("performUwChecks() done uw_reason: ", PlcUtils::$uw_code, ' uwHardness:' , PlcUtils::$uw_hardness);

                if ($this->multi_insured == 2 && !empty($this->_p['b_insured2'])) { # есть 2-ой застрахованный - проверим и его
                    $pref = 'insd2';
                    PlcUtils::performUwChecks('insd',$this->module, $recid,
                      rusUtils::capitalize($this->_p[$pref.'fam']),
                      rusUtils::capitalize($this->_p[$pref.'imia']),
                      rusUtils::capitalize($this->_p[$pref.'otch']),
                      $this->_p[$pref.'docser'],
                      $this->_p[$pref.'docno'],
                      0,
                      '',
                      $this->_p[$pref.'birth'],
                      $recid,0,0,$datefrom
                    );
                }

            }
            elseif(PlcUtils::$debug) writeDebugInfo("пропуск проверок performUwChecks");
        }
        else {
            if(self::$debug) writeDebugInfo("не было проверок");
        }

        if ( !$bRefresh && !empty($this->new_agmt_uwcode) && !AppEnv::isLightProcess() ) {
            if (is_numeric($this->new_agmt_uwcode)) {
                $uwcode = $this->new_agmt_uwcode;
                PlcUtils::setUwReasonHardness(1,$this->new_agmt_uwcode);
            }
            elseif(is_callable($this->new_agmt_uwcode))
                $uwcode = call_user_func($this->new_agmt_uwcode, $this->_p);

            $this->_p['uw_confirmed'] = TRUE; # не надо лишних запросов подтверждения!
        }

        if(!$uwcode) $uwcode = PlcUtils::$uw_code;
        if(self::$debug) writeDebugInfo("uwcode after checks: $uwcode");
        # вопрос задаю только если UW-код причины - не "стандартный при заведении нового договора" (new_agmt_uwcode)
        $old_uwcode = $old_uwhard = 0;
        if(isset($this->calc['uwcode']) || isset($this->srvcalc['uwcode']) || isset($this->ins_params['uwcode']) ) {
            $old_uwcode = $this->calc['uwcode'] ?? $this->srvcalc['uwcode'] ?? $this->ins_params['uwcode'] ?? 0;
            $old_uwhard = $this->calc['uw_hard'] ?? $this->srvcalc['uw_hard'] ?? $this->ins_params['uw_hard'] ?? 0;
            # writeDebugInfo("old_uwcode=[$old_uwcode] from calc/ins_params");
            # TODO: в коробочных задавать uw_hard при калькуляции
        }
        elseif (isset($this->_rawAgmtData['reasonid'])) {
            $old_uwcode = $this->_rawAgmtData['reasonid'];
            # writeDebugInfo("from rawAgmtData, [$old_uwcode]");
        }
        $allReasons = PlcUtils::$allUwReasons;
        # exit('1' . AjaxResponse::showMessage("uwcode=$uwcode, all uwReasons: <pre>" . print_r($allReasons,1) . '</pre>'));
        # writeDebugInfo("current uwcode: $old_uwcode ", $this->_rawAgmtData);
        # writeDebugInfo("uwcode: $uwcode, old_uwcode: $old_uwcode, ask_for_uw: [$this->ask_for_uw]");
        if(PlcUtils::$uw_hardness > $old_uwhard) $uwcode = PlcUtils::$uw_code;

        if ($uwcode) {
            $this->uw_reason = InsObjects::getUwReasonDescription($uwcode);
            $this->uw_reasonid = $uwcode;
        }
        $ret = '';

        if ($uwcode>0 && $old_uwcode==0 && $priceEditable) {
            $msg_reason  = $this->warning_text = $this->uw_reason; # InsObjects::getUwReasonDescription($uwcode);
            if(PlcUtils::$debug) writeDebugInfo("начинаем спрашивать про UW ", $msg_reason);
            # if (!$msg_reason && !empty($this->uw_reason)) $msg_reason = $this->uw_reason;
            # writeDebugInfo("this->_p: ", $this->_p);
            if ($this->box_product !== 2 && empty($this->_p['uw_confirmed'])
              && $this->ask_for_uw) { # юзер еще не подтвердил согласие сохранить полис с андеррайтингом, формирую запрос

                if(self::$debug) writeDebugInfo("confirm needed ", $msg_reason);
                if (AppEnv::isApiCall()) {
                    # при вызове из API возвращаем CONFIRM запрос на сохранение в статусе андеррайтинг
                    return array(
                      'result' => 'CONFIRM',
                      'message' => "Данный договор будет требовать андеррайтинга. Причина: $msg_reason"
                    );
                }

                $msgtext = "Данный договор будет требовать андеррайтинга. Причина: <br><br>" .$msg_reason
                   . "<br><br>Желаете все равно сохранить его ? (при ответе <b>нет</b> будет продолжен ввод)";
                if($return) {
                    $ret = ['result'=>'WARNING','message'=> "Данный договор будет требовать андеррайтинга. Причина:<br>" .$msg_reason];
                }
                else {
                    $ret = "1\tconfirm\fВнимание!\f$msgtext\fpolicyModel.setUwConfirmed()";
                    exit ($ret);
                }

            }

            # формирую транспарант с сообщением об андеррайтинге, для показа юзеру

            if (!$this->isAdmin() && $recid <=0 ) {
                # $this->setUwNotification($this->warning_text, $uwcode);
            }
        }
        if (self::$debug || PlcUtils::$debug)
            WriteDebugInfo("KT-232, uwcode: ", $uwcode. ', uw_reason:', $this->uw_reason. ' uw_reasonid:',$this->uw_reasonid);

        # exit('uwcheck result: '.$uwcode); # debug pit dtop

        # Если есть причины для андеррайтинга, то либо пошлет запрос подтверждения, либо полис сохранит полис,
        # но юзер увидит праздничный транспарант с уведомлением о статусе "андеррайтинг"

        # {upd/202-10-21} - немедленный превод статуса в UW отменяю, показываю кнопу "отправить на андеррайтинг"
        ### if (!empty($this->uw_reason)) $this->setUnderWritingState();
        # exit("1" . ajaxresponse::showMessage('Save: TEST STOP-001'));
        $subtypeid = '';
        if (isset($this->_p['programid'])) $product = $this->_p['programid'];
        elseif (!empty($this->_p['subprogram'])) $product = $this->_p['subprogram'];
        else $product = $this->module;
        if(!empty($this->_p['subtypeid'])) {
            # subtypeid выбран на форме (ИСЖ-2)
            $subtypeid = $this->_p['subtypeid'];
        }
        $prodCode = '';
        if (method_exists($this, 'getSubType')) {
            $prodCode = $this->getSubType($product, $this->_p, $this->uw_reasonid, $subtypeid);
            if(self::$debug) writeDebugInfo("prodcode by bkend->getSubType(): [$prodCode]");
        }
        if(empty($prodCode))
            $prodCode = $this->getProgramCode($product, $this->_p);

        if (self::$debug) WriteDebugInfo("KT-235, prodCode = $prodCode");

        $codirovka = $prodCode;
        $agent_id = '';
        # if (self::$debug === 1.1) WriteDebugInfo("final product ID : $product, codirovka=$codirovka");

        if($bRefresh) $dt = [];
        else $dt = [
           'insurer_type' => $insrType,
           'equalinsured' => $equalinsured,
           'prodcode' => $prodCode,
        ];

        # раскомментировать когда/если будет перевод ИСЖ на policymodel (invins)
        # if($subtypeid) $dt['subtypeid'] = $subtypeid; # новое поле - под ИСЖ-2 вместо bn_policy.subtypeid

        if (!$bRefresh && isset($this->_p['seller'])) $dt['seller'] = RusUtils::mb_trim($this->_p['seller']);
        # exit('1' . AjaxResponse::showMessage(__LINE__ .':pre-Data: <pre>' . print_r($dt,1) . '</pre>'));
        # exit('1' . AjaxResponse::showMessage(__LINE__  . ' codirovka: <pre>' . print_r($codirovka,1) . '</pre>'));

        if (!$bRefresh&& is_array($checkdata) && count($checkdata))
            $dt = array_merge($dt, $checkdata);
        # exit('1'.AjaxResponse::showMessage('<pre>'.print_r($dt,1).'</pre>')); # debug pitstop
        if(self::$debug>1) writeDebugInfo("codirovka: $codirovka, calculatedData: ", $calculatedData);
        if (is_array($calculatedData)) {
            # $dateFrom = FALSE;
            # exit('1' . AjaxResponse::showMessage(__LINE__ . ' hookData: <pre>' . print_r($hookData,1) . '</pre>'));
            if (!empty($calculatedData['datefrom'])) $dateFrom = $dt['datefrom'] = to_date($calculatedData['datefrom']);
            if (!empty($calculatedData['programid'])) $dt['programid'] = intval($calculatedData['programid']);
            if (!empty($calculatedData['policy_prem'])) $dt['policy_prem'] = floatval($calculatedData['policy_prem']);
            if (!empty($calculatedData['currency'])) $dt['currency'] = $calculatedData['currency'];
            if (!empty($calculatedData['term'])) {
                $term = $dt['term'] = floatval($calculatedData['term']);
                if(!empty($dateFrom)) {
                    $dt['datefrom'] = to_date($dateFrom);
                    $dt['datetill'] = AddToDate($dateFrom, $term, 0, -1);
                    if(self::$debug) writeDebugInfo("KT-003 redefine datefrom to $dt[datefrom], datetill: $dt[datetill] calcData: ", $calculatedData);
                }
                if (!empty($dt['datefrom']) && $term>0) $dt['datetill'] = addToDate($dt['datefrom'],$term,0,-1);
                if (!empty($dt['currency']) && $dt['currency']==='RUR' && !empty($dt['policy_prem']))
                    $dt['policy_prem_rur'] = $dt['policy_prem'];
                if(!empty($calculatedData['termunit'])) {
                    $dt['termunit'] = $calculatedData['termunit'];
                }
                if(!empty($calculatedData['datetill'])) $dt['datetill'] = $calculatedData['datetill'];
                else {
                    if(!empty($dateFrom) && !empty($term) && !empty($calculatedData['termunit'])) {
                        if(!empty($dt['termunit']) && $dt['termunit'] ==='M') # term-период в месяцах
                            $dt['datetill'] = AddToDate($dateFrom,0,$term,-1);
                        else
                            $dt['datetill'] = AddToDate($dateFrom,$term,0,-1); # период в годах
                    }
                }
            }
            if(!empty($calculatedData['prodcode']) && $calculatedData['prodcode']) {
                $dt['prodcode'] = $codirovka = $prodCode = $calculatedData['prodcode']; # кодировка - своя!
            }
            if(!empty($calculatedData['subtypeid'])) $dt['subtypeid'] = $calculatedData['subtypeid'];

            # echo 'data <pre>' . print_r($hookData,1). '</pre>';
            if(!empty($calculatedData['uwcode'])) $uwcode = $this->uw_reasonid = $calculatedData['uwcode']; # услать на андеррахтунг!
            if(isset($calculatedData['vip'])) $dt['vip'] = $calculatedData['vip']; # {upd/2022-02-28} полис VIP
        }
        if(self::$debug>1) writeDebugInfo("data to set after calc: ", $dt);
        # writeDebugInfo("now uwcode: $uwcode");
        if ($return !== 'refreshdates' && method_exists($this, 'subcodeModifier')) {
            $newCode = $this->subcodeModifier($prodCode, $this->_p);
            if ($newCode) $dt['prodcode'] = $codirovka = $prodCode = $newCode;
            if (self::$debug) WriteDebugInfo("subcodeModifier returned, result: $codirovka from $prodCode");
        }
        $logPostfix = '';
        # exit("TODO: $uwcode - $codirovka");
        # exit ('1'. ajaxResponse::showMessage("prodCode: $prodCode/$codirovka, <pre>".print_r($this->_p,1).'</pre>'));
        $clientid = ($this->bindToclient) ? (AppEnv::$_p['clientid'] ?? 0) : 0;
        if(!$bRefresh) {
            if ($b_newAgr) {
                $dt['version'] = $this->plcversion;
                $dt['headdeptid'] = $headdept;

                $myMeta = \OrgUnits::getMetaType(\AppEnv::$auth->deptid);
                $dt['metatype'] = $myMeta;
                if ($this->_p['insurer_type'] == 2 || $this->uw_reasonid>0)
                    $dt['bptype'] = PM::BPTYPE_STD; # ЮЛ, никакого ПЭП

                # exit('1' . AjaxResponse::showMessage('Data after checks: <pre>' . print_r($dt,1) . '</pre>'));
            }
            else {
                # writeDebugInfo(__FUNCTION__, " agmtdata: ", $this->agmtdata);
                $clientid = $this->agmtdata['clientid'] ?? 0;
            }
            if ($handlepno) $dt['policyno'] = $handlepno;
            # WriteDebugInfo("dt for savin plc:", $dt);
        }

        # блокирую изменение даты рождения (пола?) клиента при вводе ПДн

        if($this->bindToclient && $clientid>0) {
            \BindClient::checkClientUpdates($this, $clientid, '',FALSE, $b_newAgr);
        }

        if (!empty($dt['term'])) $term = $dt['term'];
        else $term = $this->tarif['term'];
        if (empty($dt['headdeptid'])) $dt['headdeptid'] = $headdept;

        $altcode = '';
        if(!$bRefresh) {
            if ($this->comission_mode){
                # TODO: комиссия проставляется в зав-сти от премии, а значит, при оплате или установке "Оформлен"
                if (!$b_newAgr && $recid > 0) {
                    $plcSplit = explode('-', $this->_rawAgmtData['policyno']);
                    $altcode = $plcSplit[0];
                    # $codirovka = 'DDD';
                }
                if (isset($dt['rassrochka'])) $paytype = $dt['rassrochka'];
                elseif(isset($this->_rawAgmtData['rassrochka'])) $paytype = $this->_rawAgmtData['rassrochka'];
                elseif(isset($this->_p['rassrochka'])) $paytype = $this->_p['rassrochka'];
                else $paytype = 'E';

                $dt['comission'] = self::getComissionPercent($this->module,[$codirovka, $altcode],$dt['headdeptid'],0,$paytype,$term);

                if (self::$debug) WriteDebugInfo("KT-238/$this->module, comission: ", $dt['comission']);
                /*** # все про комиссии убираю - не нужны!
                if ($dt['comission'] === FALSE) {
                    if (AppEnv::isApiCall()) return array(
                      'result' =>'ERROR',
                      'message' => AppEnv::getLocalized('err_comission_not_set') . " (подразд-е: $dt[headdeptid])"
                    );

                    AppEnv::echoError('err_comission_not_set');
                }
                **/
            }
            else $dt['comission'] = 0;

            if(!empty($this->_p['no_benef']) || strpos($insuredType,'adult')===FALSE) {
                $dt['no_benef'] = 1;
                # if ($recid>0) $this->cleanPolicyBenefs($recid);
                # writeDebugInfo("TODO: no benefs, delete previos if exist");
            }
            else $dt['no_benef'] = 0;
            # exit("codirovka: $codirovka, prodCode: $prodCode");
        }
        # exit ( '1'.ajaxResponse::showMessage('data <pre>' . print_r($dt,1). '</pre>') ); # debug stop
        $once = FALSE;
        # writeDebugInfo("_p: ", $this->_p);
        if(isset($this->_p['rassrochka']) && !isset($dt['rassrochka'])) {
            $dt['rassrochka'] = $this->_p['rassrochka'];
            $once = (intval($this->_p['rassrochka']<=0));
        }
        # TODO: другие возможные варианты, включая $this->calc?

        $log_change_state = FALSE;
        # доп.поля перед сохранением договора в БД
        if(method_exists($this, 'applyAgmtData'))
            $this->applyAgmtData($dt);

        # {upd/2025-08-19} - При облегч.онлайн оформлении номер сразу не выделяю:
        # if(AppEnv::isLightProcess()) $this->defer_policyno = TRUE;
        # !!! экономия номеров приводит к нехорошему кейсу - оплата с номером DRAFT, потом бухг.не найдет полис

        if( $recid>0 ) {
            # сброс под-статуса "на доработке UW"
            if($bRefresh) {
                /*
                if(!empty($this->_p['datefrom'])) $dt['datefrom'] = to_date($this->_p['datefrom']);
                if(!empty($this->_p['datetill'])) $dt['datetill'] = to_date($this->_p['datetill']);
                */
                if( $this->_rawAgmtData['bpstateid']==PM::BPSTATE_UWREWORK) {
                if(self::$debug) writeDebugInfo("refreshdates, reset BPSTATE_UWREWORK to 0");
                    $dt['bpstateid'] = 0;
                }
            }
            else {
                $investAnketaMode = ($this->invest_anketa && PlcUtils::isInvestAnketaActive($this->module,$this->_rawAgmtData['headdeptid']));
                $log_change_state = FALSE;
                if ($uwcode>0) {
                    ### $this->agmt_setstate = PM::STATE_UNDERWRITING; # {upd.2022-10-21} в UW статус не перевожу!
                    ### $log_change_state = $uwcode;
                    $dt['reasonid'] = $uwcode;
                }
                elseif(empty($old_uwhard) && empty($old_uwcode) && empty($uwcode) && in_array($this->_rawAgmtData['stateid'], [0,1,6])) {
                    # тестовый фича - вернем проект дог. в норм.статус без причин андеррайтинга
                    # writeDebugInfo("проверить - возврат в БЕЗ-андеррайтинговый статус");
                    $dt['reasonid'] = 0;
                }

                $draftReason = FALSE;
                if($this->draft_state) {
                    $draftReason = PlcUtils::isDraftstateReasons();
                    if($this->_rawAgmtData['stateid'] == PM::STATE_DRAFT && !$draftReason) {
                        $this->agmt_setstate = PM::STATE_PROJECT;
                        $logPostfix = ' (Статус Проект)';
                    }
                }
                # если по исправленным данным надо включать андеррайтинг ИЛИ закрыть статус черновика:
                if (!$draftReason && $this->agmt_setstate != $this->_rawAgmtData['stateid']) {
                    if (in_array($this->_rawAgmtData['stateid'], [-10, 0,1,6])) {
                        $dt['stateid'] = $this->agmt_setstate;
                    }
                }
                # exit('1' . AjaxResponse::showMessage(__LINE__ . ' Data: <pre>' . print_r($dt,1) . '</pre>'));
                # {upd/2019-01-11}: если после изменения данных у полиса должна быть другая кодировка - поменять policyno!!! (invonline - поменяли срок 3 - 5 лет...)

                $plcNo = explode('-', $this->_rawAgmtData['policyno']);

                if ($codirovka !== $plcNo[0]){
                    # WriteDebugInfo($this->_rawAgmtData['policyno'], " надо сменить кодировку $plcNo[0] -> $codirovka ");
                    $plcNo[0] = $codirovka;
                    if($this->defer_policyno ) {
                        if($this->_rawAgmtData['stateid']<PM::STATE_UNDERWRITING) {
                            # полис с отложенным присвоением номера, снова поменяли кодировку - значит надо вернуться на DRAFT номер
                            if( !$this->isDraftPolicyno($this->_rawAgmtData['policyno']))
                                $plcNo[1] = self::getDraftPolicyNo(); # 'DRAFT' . date('ymdhi');
                        }
                        else { # исправили полис на андеррайтинге, надо сразу выдать новый номер!
                            # if( $this->isDraftPolicyno($this->_rawAgmtData['policyno']))
                            $plcNo[1] = NumberProvide::getNext($codirovka,$this->_rawAgmtData['headdeptid'],$this->module, $this->policyno_len);
                        }
                    }

                    $dt['policyno'] = implode('-', $plcNo);
                    $logTail = " (смена кодировки: $dt[policyno])";
                    # если надо запросить новый номер полиса - делать здесь!
                }
                # else exit("Debug: номер полиса не меняем: ".$this->_rawAgmtData['policyno']);
                # {upd/2023-05-16} если коробка типа 2 - отменяю андеррайтинг ваще! (А.Загайнова, А.Дунаев)
                if($this->box_product === 2) {
                    if(self::$debug || PlcUtils::$debug) writeDebugInfo("коробка-2, сброс UW-признака uwcode:", PlcUtils::$uw_code);
                    $uwcode = $dt['reasonid'] = 0;
                    PlcUtils::$uw_code = PlcUtils::$uw_hardness = 0;
                }
                if(empty(PlcUtils::$uw_code) && $this->_rawAgmtData['stateid']==PM::STATE_PROJECT) {
                    if(PlcUtils::$debug) writeDebugInfo("сброс reasonid, plcUtils::uw_code = ");
                    $dt['reasonid'] = 0; # сброс признака UW после редактирования
                }
            }
            if(self::$debug) writeDebugInfo("KT-099, priceEditable=[$priceEditable] dt to update policy: ", $dt);
            # if($bRefresh) exit('1' . AjaxResponse::showMessage('Data to update: <pre>' . print_r($dt,1)));

            if(!$priceEditable) { # отредактировали полис на доработке, премии, equalinsured не должны были поменяться!

                $reworkErrs = [];
                if(isset($dt['term']) && $dt['term'] != $this->_rawAgmtData['term']) $reworkErrs[] = 'Срок страхования менять нельзя!';
                if(isset($dt['insurer_type']) && $dt['insurer_type'] != $this->_rawAgmtData['insurer_type']) $reworkErrs[] = 'Условия страхования менять нельзя (Страхователь ФЛ/ЮЛ)!';
                if(isset($dt['policy_prem']) && $dt['policy_prem'] != $this->_rawAgmtData['policy_prem']) $reworkErrs[] = 'Условия страхования менять нельзя!';
                if(isset($dt['equalinsured']) && $dt['equalinsured'] != $this->_rawAgmtData['equalinsured']) $reworkErrs[] = 'Условия страхования менять нельзя (Страхователь=Застрахованный!';
                if(isset($dt['rassrochka']) && $dt['rassrochka'] != $this->_rawAgmtData['rassrochka']) $reworkErrs[] = 'Условия страхования менять нельзя (вариант оплаты)!';
                if(!empty($dt['datefrom']) && to_date($dt['datefrom']) !== $this->_rawAgmtData['datefrom']) $reworkErrs[] = 'Дату начала менять нельзя!';
                # $reworkErrs[] = 'uw_code:'.$this->uw_reasonid;
                if(count($reworkErrs)) {
                    $errTxt = 'Недопустимые изменения:<br>' . implode('<br>', $reworkErrs);
                    if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=> $errTxt];
                    exit('1' . AjaxResponse::showError($errTxt,'Полис на доработке!'));
                }
                $dt['substate'] = PM::SUBSTATE_EDITED; # фиксирую, что были корректировки
                if(self::$debug>1) writeDebugInfo("KT-005 rework, before deleteFilesInAgreement");
                FileUtils::deleteFilesInAgreement($this->module, $recid,[PM::$scanEdoPolicy,PM::$scanPolicy]);
            }
            $result = PlcUtils::updatePolicy($this->module, $recid, $dt);
            if ($result) {

                $logTxt = ($bRefresh) ? 'Обновление дат,премий' : 'Редактирование данных в договоре';
                if (!empty($logTail)) $logTxt .= $logTail;
                $logAction = ($return === 'refresh') ? 'REFRESH' :'UPDT AGMT';
                AppEnv::logEvent($this->log_pref.$logAction, $logTxt, FALSE, $recid);
                if ($log_change_state) {
                    AppEnv::logEvent($this->log_pref.'CHANGE_UWSTATE', 'Перевод договора в статус На андеррайтинге: '.InsObjects::getUwReasonDescription($log_change_state),
                    FALSE, $recid);
                }
            }
            $agmtData = AgmtData::getData($this->module, $recid);

            $clientid = $agmtData['clientid'] ?? 0;
            # WriteDebugInfo($recid, " clientid from policy:$clientid ", "in _p: ",(AppEnv::$_p['clientid']??'no'), "from agmtdata: $clientid, договор обновился");
        }
        else { # new record
            # $clientid = AppEnv::$_p['clientid'] ?? 0;
            $investAnketaMode = ($this->invest_anketa && PlcUtils::isInvestAnketaActive($this->module) && ($this->invest_anketa>1 || $insrType==1));
            # writeDebugInfo("investAnketaMode=[$investAnketaMode], invest_anketa=[$this->invest_anketa], fizur=$fizur");
            # при вызове из API, могли передать свой ИД клиента, на которого "вешаем" этот договор
            if(self::$debug > 9) exit('1' . AjaxResponse::showMessage('DEBUG stop','debug'));

            if(AppEnv::isApiCall()) {
                if (!empty($this->_p['extclientid']))
                    $dt['extclientid'] = trim($this->_p['extclientid']);
                # else return ['result'=>'error', 'message'=>'не указан ИД клиента (clientId)'];
                # раскоментировать если передача ID клиента из API обязательна
            }
            $pno = '';
            if ( $this->policyno_mode == 2 && !empty($this->_p['policyno']) ) { # ручной ввод
                $pno = intval($this->_p['policyno']);
                if (!$pno) AppEnv::echoError('err_empty_manual_policyno');
            }
            else  { // ($this->policyno_mode == 1)
                if($this->defer_policyno) # номер не выдавать, будет получен позже
                    $pno = self::getDraftPolicyNo(); #
                else $pno = NumberProvide::getNext($codirovka, AppEnv::$auth->deptid, $module);

                if (!$pno) {
                    $errTxt = AppEnv::getLocalized('err_no_available_policyno') . " (кодировка $codirovka)";
                    if (AppEnv::isApiCall()) {
                        return array(
                          'result' => 'ERROR',
                          'message' => $errTxt
                        );
                    }
                    AjaxResponse::exitError($errTxt);
                    return;
                }
            }

            $dt['policyno'] = $nextpno = $codirovka . '-' . (is_numeric($pno)? str_pad($pno,$this->policyno_len,'0',STR_PAD_LEFT): $pno);
            $dt['module'] = $this->module;
            $myMeta = OrgUnits::getMetaType(AppEnv::$auth->deptid);

            if (AppEnv::isClientCall()) {
                $dt['userid'] = AppEnv::getConfigValue('account_clientplc');
                $dt['deptid'] = AppEnv::$auth->deptid;
            }
            else {
                $dt['userid'] = AppEnv::$auth->userid;
                $dt['deptid'] = AppEnv::$auth->deptid;
            }
            $dt['headdeptid'] = $headdept;

            # myagent полис завел менеджер от имени агента
            $agent_id = AppEnv::$_p['myagent_id'] ?? '';

            if($agent_id > 0) {
                $arAgt = AppEnv::$db->select(PM::T_USERS, ['fields'=>'firstname,usrname,deptid', 'where'=>['userid'=>$agent_id], 'singlerow'=>1]);
                if(isset($arAgt['deptid'])) {
                    $dt['deptid'] = $arAgt['deptid'];
                    $dt['userid'] = $agent_id; # теперь полис как бы заведен этим агентом
                    $dt['authorid'] = AppEnv::getUserId(); # манагер, который реально завёл полис
                    $logPostfix .= " (агент $arAgt[firstname] $arAgt[usrname])";
                }
            }

            if(!empty($curatorid)) $dt['curator'] = $curatorid;

            elseif($this->agent_prod === PM::AGT_LIFE || $myMeta == OrgUnits::MT_AGENT) { # {upd/2021-02-05} для агентских продуктов/продаж:
                # проставляю ИД куратора, активного для агента на момент создания договора
                $dt['curator'] = \Libs\AgentUtils::getUserCurator($agent_id); # ID куратора агента
            }
            if($myMeta == OrgUnits::MT_BANK) {
                # {upd/2023-09-25} для банк-канала - фиксирукю ИД коуча
                $dt['coucheid'] = \Libs\AgentUtils::getDeptCouch(AppEnv::$auth->deptid);
            }

            $dt['agent'] = (empty($agentid)) ? $agent_id : $agent;
            if(!empty(AppEnv::$_p['anketaid']) && $once) {
                if ($once) { # учитываю выбранную анкету только если была единовр.оплата!
                    $dt['anketaid'] = AppEnv::$_p['anketaid'];
                    InvestAnketa::rejectIfUsed(AppEnv::$_p['anketaid']);
                    # если к анкете кто-то уже приделал полис, второй блокируется!
                }
            }
            /* # {upd/2021-09-10} отменяю запрет, Теперь полис может становиться на паузу, и анкету можно заводить с формы просмотра
            else { # анкета не прицеплена, надо проверить режим
                if ($investAnketaMode && $once)
                    exit('1'.AjaxResponse::showError('err-invanketa_not_chosen'));
            }
            */
            # TODO: coucheid - когда будет справочник коучей с привязкой к подразд/продукту!

            if ($prolong) {
                $dt['previous_id'] = $this->_p['prolong'];
            }
            if (!empty($this->_deptCfg['testmode']) || AppEnv::isTestAccount())
                $dt['b_test'] = 1; # у полиса тестовый статус

            if (!empty($this->tarif['currency']))
                $dt['currency'] = $this->tarif['currency'];
            if (isset($this->tarif['tariffid']))
                $dt['tariffid'] = $this->tarif['tariffid'];
            $dt['currate'] = 1;
            if ($dt['currency']!=='RUR') {
                $dt['currate'] = PlcUtils::getCurrRate($dt['currency']);
                if (self::$debug) WriteDebugInfo("today rate for $dt[currency]:", $dt['currate']);
            }
            $dt['stmt_stateid'] = PM::STATE_FORMED;
            # {upd/2023-05-16} если крорбка типа 2 - отменяю андеррайтинг ваще! (А.Загайнова, А.Дунаев)
            if($this->box_product === 2) {
                if(self::$debug) writeDebugInfo("коробка-2, сброс всех UW-признаков :", PlcUtils::$uw_code);
                $uwcode = $this->uw_reasonid = 0;
                PlcUtils::$uw_code = PlcUtils::$uw_hardness = 0;
            }

            if ($uwcode>0 || $this->uw_reasonid > 0) {
                # $this->agmt_setstate = PM::STATE_UNDERWRITING;
                $dt['reasonid'] = max($uwcode, $this->uw_reasonid);
            }
            # writeDebugInfo("stateid in new policy: ", $this->agmt_setstate);
            /* if(AppEnv::isLightProcess())
                $dt['stateid'] = PM::STATE_PROJECT_OL; # проект при онлайн-оформлении
            else
            */
            $dt['stateid'] = $this->agmt_setstate; # PM::STATE_POLICY;

            # exit("stateid : $dt[stateid], reason: $this->uw_reasonid");
            $dt['created'] = '{now}'; # date('Y-m-d H:i:s');
            if ($this->_debugAdd) {
                $recid = rand(500000,590000);
                writeDebugInfo("policy data to add: ", $dt);
            }
            else {
                # exit('1'. ajaxResponse::showMessage('dept<pre>' . print_r($this->_deptCfg,1). '</pre>')); # debug pit stop
                # сразу выставляю тип "не ЭДО оформление" если Стр <> Застрахованный или есть UW
                if(!empty($this->_deptCfg['online_confirm'])) {
                    if( !empty($dt['reasonid'])) { # empty($dt['equalinsured']) ||
                        $dt['bptype'] = PM::BPTYPE_STD;
                        $logPostfix = '(оформление без ЭДО)';
                    }
                }

                # exit('1'. ajaxResponse::showMessage('data to insert<pre>' . print_r($dt,1). '</pre>')); # debug pit stop
                # $result = AppEnv::$db->insert(PM::T_POLICIES ,$dt);
                # $recid = AppEnv::$db->insert_id();
                if(PlcUtils::isDraftstateReasons() && !AppEnv::isLightProcess()) {
                    $dt['stateid'] = PM::STATE_DRAFT;
                    $logPostfix = ' (Черновик)';
                }
                $agtEventId = FALSE;
                if( $this->bindToclient && $myMeta == OrgUnits::MT_AGENT && !AppEnv::isLightProcess() && !AppEnv::isApiCall() ) { # $this->logAgentEvents
                    # регистрируем первичное событие агента - создание проекта договора
                    $prolonged = $this->_p['prolong'] ?? NULL;
                    $payment = $dt['rassrochka'] ?? 0;
                    $programid = $dt['programid'] ?? 0;
                    $agtEventId = \Libs\Registrator::addEvent($this->module,'new_agmt',$programid,$agent_id,$this->_p,$prolonged,$clientid,$payment);
                    # writeDebugInfo("new agmt create, event in agent log: ", $agtEventId);
                }
                if(self::$debug) writeDebugInfo("policy to add: ", $dt);
                $recid = PlcUtils::addPolicy($this->module, $dt);
                if ($recid>0 && !empty($dt['anketaid'])) {
                    $invFix = Investanketa::fixPolicy($dt['anketaid'], $recid, $this->module);
                    # writeDebugInfo("Investanketa::fixPolicy result: ",$invFix);
                }
                if($recid && $agtEventId) { # связываю запись о "калькуляции" с записью карточки договора
                    \Libs\Registrator::savePolicyId($recid, $agtEventId);
                    # writeDebugInfo("event $agtEventId linked to plc $recid");
                }
                if($recid && $clientid) {
                    $savecli = AgmtData::saveData($this->module, $recid, ['clientid'=>$clientid]);
                    # writeDebugInfo("storing clientid=$clientid in policy data: [$savecli]");
                }
                # else writeDebugInfo("clientid not saved in polict, [$clientid]");
            }
            $eshop = '';
            if ($recid) {
                /*
                if (!is_numeric($pno)) {
                    # сразу добавляю в номер eShop-полиса его ИД BBBB-PROJ => BBBB-PROJ123456
                    $nextpno = $prodCode . '-' . $pno . $recid;
                    $eshop = 'eShop: ';
                    AppEnv::$db->update(PM::T_POLICIES,['policyno'=>$nextpno], ['stmt_id'=>$recid]);
                }
                */
                $msgstring = "{$eshop}Добавлен договор [$nextpno]";
                if ($prolong)
                    $msgstring .= "/Пролонгация дог. ".$this->_p['prolong']; # . ':'.$this->_rawAgmtData['policyno'];
                if ($dt['stateid'] == PM::STATE_UNDERWRITING)
                    $msgstring .= ' (андеррайтинг)';

                if ( $this->enable_touw && !empty($this->_p['user_touw']) && !AppEnv::isApiCall() ) {
                    if (!isset($_SESSION['policy_packet_mode'])) {
                        $_SESSION['policy_packet_mode'] = $this->module . ':' . $recid;
                        # writeDebugInfo("старт пакета: $this->module / $recid)");
                        # Запомнил ИД первого полиса в пакете, чтоб потом загружать  из него данные ФИО на новые полисы пакета
                    }
                }
                if (!$this->_debugAdd)
                    AppEnv::logEvent($this->log_pref.'ADD AGMT', ($msgstring.$logPostfix), FALSE, $recid);
            }
        }

        if ($recid) {
            $insd2_offset = 0;

            if( $this->bindToclient && !AppEnv::isApiCall() ) {
                if(!empty($clientid)) {
                    \BindClient::updateClientData($clientid,AppEnv::$_p);
                    # writeDebugInfo("updated client data for: ", $clientid);
                }
                # else writeDebugInfo("client not updated / empty clientid!");
            }

            if(!$bRefresh) { # NOT refreshdates!
                AgmtData::saveData($this->module, $recid, ['uw_reason2'=>PlcUtils::$uw_code, 'uw_hard2'=>PlcUtils::$uw_hardness]);

                $insrfull = $insdfull = $this->saveIndividual($recid,'insr',$this->_p,'insr', ($insrType>1));
                if ($this->multi_insured >=100) { # много застрахованных - вызвать свой метод сохранения (должен быть в backend!)
                    $savedInsd = 0;
                    if (method_exists($this, 'saveInsuredList')) {
                        $savedInsd = $this->saveInsuredList($recid);
                    }
                    elseif(!AppEnv::isProdEnv()) {
                        $err = $this->module . ': В backend.php не реализован метод saveInsuredList(), Застрахованные не сохранены!';
                        AppEnv::addInstantMessage($err,'policy_save');
                    }
                    $insdfull = 'Список застрахованных' . ($savedInsd>0 ? "($savedInsd)" : '');
                }
                elseif ($this->multi_insured>0) {
                    if( $equalinsured) {
                        $this->dropIndividual($recid, 'insd'); # удаляю запись о застрахованном, если была до этого
                        $insdfull = $insrfull;
                    }
                    else {
                        $insdfull = $this->saveIndividual($recid, 'insd', $this->_p); # отдельный застрахованный, сохраняю!
                        $insd2_offset++;
                    }
                }
                $dtperson = array(
                    'insurer_fullname' => $insrfull
                   ,'insured_fullname'=> $insdfull
                );

                if ($this->multi_insured == 2 && !empty($this->_p['b_insured2'])) { # есть 2-ой застрахованный
                    $insured2 = $this->saveIndividual($recid, 'insd', $this->_p,'insd2',0,0,$insd2_offset);
                }

                if (($this->insured_child) ) {
                    if ($childCfg[0] === 'option' && !empty($childCfg[1])) {
                        # сохраняю список застрахованных деток
                        if (self::$debug) writeDebugInfo("сохраняю список застрахованных деток,1-$childCfg[1]");
                        $maxChild = intval($childCfg[1]);
                        $saved = $this->saveMultiChilds($recid, 'child', $maxChild);
                        # writeDebugInfo("saved chiuld result: ", $saved);
                    }
                    else {
                        if($this->policyHasChild())
                            $childname = $this->saveIndividual($recid, 'child', $this->_p);
                    }
                }

                # Заношу в осн.таблицу ФИО/наим-я страхователя + застрахованного
                AppEnv::$db->update(PM::T_POLICIES, $dtperson, array('stmt_id'=>$recid));
                $spec = $newcalc = $newsrv = $newplan = [];

                if ($this->income_sources) $spec = PlcUtils::getIncomeSources();

                if (count($this->spec_fields)) $this->addUserSpecParams($spec);

                # {upd/2022-07-22} - рисковость клиента
                # if($this->b_clientRisky) AgmtData::saveData($this->module, $recid, AppEnv::$_p);
                if (method_exists($this, 'makeSpecificData')) {
                    $this->makeSpecificData($spec, $newcalc,$newsrv, $newplan);
                }
                if (self::$debug) writeDebugInfo("spec to save: ", $spec);
                if (count($spec) || count($newcalc) || count($newsrv) || count($newplan))
                    $this->saveSpecData($recid, $spec, $newcalc, $newsrv, $newplan);

            } # NOT refreshdates!

            $fromAgrEdit = stripos(($_SERVER['HTTP_REFERER'] ?? ''), 'action=agredit');
            if((!$bRefresh && $this->agredit_is_calc && $fromAgrEdit>0) || AppEnv::isApiCall() ) {
                # Только если пришел со страницы agredit, и она содержит расчетные данные (agredit_is_calc)
                if(self::$debug) WriteDebugInfo("calling savePolicyRisks($recid,", $dt, $checkdata);
                if(empty($dt['datefrom'])) {
                    $dt['datefrom'] = $dateFrom;
                    writeDebugInfo("why no datefrom: restored : [$dateFrom]");
                }
                $result = $this->savePolicyRisks($recid, $dt, $checkdata);
            }

            if(!$bRefresh) {
                # writeDebugInfo("insuredType: ", $insuredType);
                if ($this->max_benefs && empty($this->_p['no_benef']) && strpos($insuredType,'adult')!==FALSE)
                    $this->saveBeneficiaries($recid,'benef', $this->_p, $this->max_benefs);
                else $this->cleanPolicyBenefs($recid,'benef');

                if ( !$cleanCbenef && strpos($insuredType,'child')!==FALSE
                  || (!empty($this->insured_child) && $childDelegate === 'N' && !empty($this->_p['cbeneffullname'])))
                { # $insuredType==='child'
                    $this->saveBeneficiaries($recid,'cbenef',$this->_p, $this->max_childbenefs);
                    if(self::$debug) writeDebugInfo("saved cbenef ", $this->_p['cbeneffullname']);
                }
                else {
                    if(self::$debug) writeDebugInfo("another clear Child Benefs $recid, cbenef");
                    $this->cleanPolicyBenefs($recid,'cbenef');
                }

                # {updt/2022-10-26} - определение и сохранение макс.даты начала д-вия (новый биз-проц)
                # $initDate = max(date('Y-m-d'), $dateFrom);
            }
            # writeDebugInfo("text");
            $this->finalSavings($recid); # , $initDate
            # exit('1' . AjaxResponse::showMessage('Stop, Dt: <pre>' . print_r($dt,1) . '</pre>'));
            # Если включена авто-проверка PEPs при сохранении, выполняю!
            # Вопрос: что делать, если PEPs требует перевода в андеррайтинг, а полис и так уже на андерр-е по другой причине ?
            # Пока - ничего не делаю, оставляю код причины UW прежним
            $pepsComment = '';
            # writeDebugInfo("peps_check_auto ", $this->peps_check_auto);
            if (!$bRefresh && $this->peps_check_auto  && !in_array($this->_rawAgmtData['stateid'], [-10,9,10,50,60])) {
                $this->pepsState = $this->CheckFinmon($recid);
                # writeDebugInfo("pepstate: ", $this->pepsState, " this->peps_check_auto: ", $this->peps_check_auto);
                # $commonDta = [ 'pepstate' => ($this->pepsState+100) ];
                if(self::$debug>1) writeDebugInfo("CheckFinmon done, peState: [$this->pepsState], peps_check_auto=[$this->peps_check_auto]");

                if ( ($this->peps_check_auto != 3) && ($this->pepsState > $this->maxPepsState) ) {
                    $stateText = '';
                    $pepsComment = "\nВнимание, страхователь и/или Застрахованный(е) не прошел проверку Комплайнс (списки, паспорт), код проверки: $this->pepsState";
                    if ($this->peps_check_auto == 2 && $priceEditable) { # перевод статуса полиса!
                        if (empty($uwcode)) { # текущий uwcode - OK
                            $pepstate = $this->pepsState+100;
                            $hard = UwUtils::hasHardPepsReasons($pepstate);
                            $pepreason = $hard ? PM::UW_REASON_PEPSHARD : PM::UW_REASON_PEPSCHECK;
                            $uwdt = [ 'reasonid' => $pepreason, 'pepstate'=>$pepstate ];
                            if($this->pepsfail_state!=0) {
                                $uwdt['stateid'] = $this->pepsfail_state;
                                $stateText = self::decodeAgmtState($this->pepsfail_state,0,0);
                                if(self::$debug) writeDebugInfo("set UW state by PEPS check: ",$this->pepsfail_state);
                            }
                            # $result = AppEnv::$db->update(PM::T_POLICIES ,$uwdt, ['stmt_id'=>$recid]);
                            $result = PlcUtils::updatePolicy($this->module, $recid, $uwdt);
                            if($stateText) {
                                AppEnv::logEvent($this->log_pref.'CHANGE_UWSTATE',
                                    "Перевод договора в статус " . $stateText . ", PEPs-проверки:[$this->pepsState]",
                                    FALSE, $recid);
                            }
                        }
                        else {
                            if(self::$debug>1) writeDebugInfo("finmon result ignored");
                        }
                    }
                    elseif(!$priceEditable) {
                        # полис оформлен, "на доработке", после изменений попал под комплайнс!
                        $uwdt = ['substate' => PM::SUBSTATE_COMPLIANCE];
                        $result = PlcUtils::updatePolicy($this->module, $recid, $uwdt);
                        AppEnv::logEvent($this->log_pref."COMPLIANCE TRIGGERED", 'После редактирования данных не пройдена проверка Комплаенс',0,$recid,0,$this->module);
                    }
                }
                else {
                    $DoNothing = 0;
                    # если после редактирования ФИО стало не PEPS-овым (нормальным)
                    if ($this->peps_check_auto==3 && $this->_rawAgmtData['stateid'] == PM::STATE_UNDERWRITING &&
                      in_array($this->_rawAgmtData['reasonid'], [PM::UW_REASON_PEPSHARD,PM::UW_REASON_PEPSCHECK])) {
                        $uwdt = ['stateid'=> $this->init_state, 'reasonid' => 0];
                        # $result = AppEnv::$db->update(PM::T_POLICIES ,$uwdt, ['stmt_id'=>$recid]);
                        $result = PlcUtils::updatePolicy($this->module, $recid, $uwdt);
                        AppEnv::logEvent($this->log_pref.'CHANGE_UWSTATE',
                            "Договор возвращен в исходный статус (после исправления прошел проверку Комплаенс)",
                            FALSE, $recid);
                    }
                    # после 2-го исправления (на доработке) все стало ОК, возвращаю в норм-статус, исправили ФИО на хороший, не террорист:)
                    if(!$priceEditable && $this->_rawAgmtData['substate'] == PM::SUBSTATE_COMPLIANCE) {
                        $uwdt = ['substate' => PM::SUBSTATE_REWORK];
                        $result = PlcUtils::updatePolicy($this->module, $recid, $uwdt);
                    }
                }
            }
            if ($b_newAgr && $this->notify_agmt_change && method_exists($this, 'notifyAgmtChange')) { # про изменение больше не уведомляю
                if ($b_newAgr) $details = 'Заведен новый договор';
                else $details = 'Произведено редактирование договора';
                $details .= $pepsComment;
                $this->notifyAgmtChange($details, $recid);
            }
            else {
                if (!$bRefresh && $this->agent_prod !== PM::AGT_NOLIFE && $this->_rawAgmtData['stateid'] == PM::STATE_UNDERWRITING) {
                    if(self::$debug) writeDebugInfo("sending admin notify about UW policy");
                    agtNotifier::send($this->module, Events::TOUW, $recid);
                }
            }
            if (!$bRefresh && $this->eq_payment_enabled || !empty($this->online_payBy) || !empty($this->_p['eqpayed']) ) {
                # include_once(__DIR__ . '/eqpayments.php');
                # все "ждущие" клика ссылки на оплату делаю "сгоревшими", чтоб клиент не смог по ним оплатить полис с устаревшими данными
                $eqpResult = Acquiring::blockAllCards($this->module, $recid);
            }

            #  exit ("saved, recid:$recid"); # debug
            # ЭДО: если раньше отправляли запросы клиенту, и он даже их согласовал, отменить! чтоб можно было послать заново
            if (!$b_newAgr && !$bRefresh && $this->autoCleanPep) UniPep::cleanRequests($this->module, $recid);

            if (AppEnv::isApiCall()) {

                $pepsResult = 'Стандарт';
                if ($this->pepsState == 1) $pepsResult = 'Умеренный';
                elseif ($this->pepsState >= 10) $pepsResult = 'Высокий, оформление невозможно';

                $apiresult = array(
                  'result' => 'OK',
                  'message' => 'Сохранение успешно',
                  'data' => [
                     'policyid' => $recid,
                     'policyno' => $this->_rawAgmtData['policyno'],
                     'stateid' => $this->_rawAgmtData['stateid'],
                     'check_result' => $pepsResult, // $this->pepsState,
                  ]
                );
                foreach($this->peps_detailed as $key => $val) {
                    $apiresult['data'][$key] = ($val ? '1' : '0');
                }
                if (self::$debug) writeDebugInfo("returning result for API call: ", $apiresult);
                return $apiresult;
            }
            if ($this->_debugAdd && $b_newAgr) exit('1'. AjaxResponse::showMessage('Эмуляция сохранения новых!'));
            if (!$b_newAgr && empty($return)) {
                $ankRes = investAnketa::synchronize($this->module, $recid);
                # writeDebugInfo("saveagmt: update invAnketa: [$ankRes]");
            }

            # предупреждение о причинах статуса черновика
            if(PlcUtils::isDraftstateReasons() && empty($return)) {
                PlcUtils::publishDraftStateMessage();
            }
            if($return) {
                return TRUE; # команда saveAgmt выполнена из refreshDates()
            }

            exit ("1\tgotourl\f./?plg=$module&action=viewagr&id=$recid");
        }
        WriteDebugInfo('Ошибка при сохранении договора, модуль '.$this->module, ' apiresult: ', $apiresult);
        if (AppEnv::isApiCall() || !empty($return)) {
           return array(
             'result' => 'ERROR',
             'message' => 'Ошибка при сохранении договора: ' . print_r($apiresult,1)
           );
        }
        AppEnv::echoError("Ошибка при сохранении данных"); # sa: $sa, age: $years, offset: $ageOff, premium: $premium"); # debug
    }

    /**
    * Печать заявления в PDF
    *
    */
    public function print_stmt($plcid=0, $tofile = FALSE) {
        if ($plcid > 0)
            $stmtid = $plcid;
        else {
            if(!count($this->_p)) $this->_p = AppEnv::$_p;
            $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        }
        if (empty($this->module) && isset(AppEnv::$_p['plg']))
             $this->module = AppEnv::$_p['plg'];
        if (empty($plcid)) { # если передан $plcid, вызов из другого модуля, проверки прав не делаем
            if(AppEnv::isClientCall()) $access = 1.5; # {upd/2022-11-14} вызов клиентом со страницы ЭДО согласования!
            else $access = $this->checkDocumentRights($stmtid);
            if ($access<1.1) AppEnv::echoError('err-no-rights-document');
        }
        AppEnv::avoidExternalHeaders();
        # WriteDebugInfo('xml def: ',  $this->home_folder . '/pdf-stmt'.$prodid.'.xml');

        $dta = $this->loadPolicy($stmtid,'print');
        if (!$tofile && ($edoFile = UniPep::getFixedPolicy($this->module, $this->_rawAgmtData,PM::$scanEdoZayav))) {
            # {upd/2024-08-05} Сохранена "фиксированная" версия заявы (ЭДО) - вывожу ее, больше файл не генерю!
            AppEnv::sendBinaryFile($edoFile['fullpath'], $edoFile['filename']);
            exit;
        }

        include_once('printformpdf.php');
        ob_start();

        # exit(__FILE__ .':'.__LINE__.' data after LoadPolicy:<pre>' . print_r($dta,1) . '</pre>');
        # $baseDta = $this->getBaseProductCfg($module, $codirovka);
        # TODO: переделать под настройку на анкеты из alf_dept_prog
        $this->deptProdParams($this->module, $this->_rawAgmtData['headdeptid']);

        $this->_deptReq = OrgUnits::getOuRequizites($this->_rawAgmtData['headdeptid'], $this->module);
        $formName = $this->_deptReq['formname']; # свой ИД "банка" для печ-форм

        $risks = $this->loadPolicyRisks($stmtid,'print');
        $dta['risks'] = $risks;
        # if (method_exists($this, 'prepareForPrintStmt'))
        if (self::$debug) WriteDebugInfo('print_stmt loaded/prepared data:', $dta);
        $prodid = $dta['prodcode']; # BCL,...
        $program = $dta['programid']; # TermFix, ...
        $pno = $dta['policyno'];

        $xmlFolder = $this->home_folder;
        if(is_dir($xmlFolder . 'printcfg'))
            $xmlFolder .= 'printcfg/';

        $cfgname = '';

        if (method_exists($this, 'getZayavConfigName')) {
            $cfgname = $this->getZayavConfigName($prodid,$pno, $program);
        }
        else {
            $cfgNames = [];
            if (!empty($this->_rawAgmtData['previous_id'])) {
                # Сначала буду искать настройки "-prolong"
                if ($formName !='') {
                    $cfgNames[] = "zayav-$prodid-$formName-prolong.xml";
                    $cfgNames[] = "zayav-$prodid-$program-prolong.xml";
                }
                $cfgNames[] = "zayav-$prodid-prolong.xml";
                $cfgNames[] = "zayav-$prodid-prolong.xml";
            }
            if ($formName !='') {
                $cfgNames[] = "zayav-$prodid-$formName.xml";
                $cfgNames[] = "zayav-$prodid-$program.xml";
            }
            $cfgNames[] = "zayav-$prodid.xml";
            $cfgNames[] = "zayav-$prodid.xml";

            foreach($cfgNames as $cname) {
                # echo $xmlFolder . $cname . " : [". is_file($xmlFolder . $cname) . ']<br>';
                if (is_file($xmlFolder . $cname)) {
                    $cfgname = $xmlFolder.$cname;
                    break; # файл XML из списка возможных найден, юзаем его!
                }
            }
        }
        $edo = !in_array($this->_rawAgmtData['bptype'], ['', PM::BPTYPE_STD]);
        if ($edo) {
            PlcUtils::setPrintEdoMode(1);
        }
        if (strpos($cfgname, '/')===FALSE) $cfgname = AppEnv::getAppFolder($xmlFolder) . $cfgname;
        $cfgname = PlcUtils::getTemplateEDO($cfgname);
        # exit(__LINE__ . "/edo=[$edo], Stmt print config: [$cfgname] exist:[" . is_file($cfgname).']');

        # {upd/2023-06-07} - заношу блок userparams из XML в переменную бакенда (если понадобится при работе prepareForPrint...
        if(is_file($cfgname)) {
            $this->getUserParamsXml($cfgname);
        }
        $outputName = ($tofile) ? (ALFO_ROOT . "tmp/zayavlenie-$pno.pdf") : "zayavlenie-$pno.pdf";
        $outputName = translit($outputName); # чтоб без русских букв в имени файла
        $options = array(
           'configfile' => $cfgname
          ,'outname'    => $outputName
          ,'compression' => self::COMPRESS_PDF
        );
        if ($tofile) $options['tofile'] = true; # в файл на диске

        PM::$pdf = new PrintFormPdf($options,$this);
        /*
          'configfile' => $cfgname
          ,'outname'    => "zayavlenie-$pno.pdf"
          ,'compression' => self::COMPRESS_PDF
        ], $this);
        */
        if(self::$debug>4) {
            echo "<pre>$stmtid:<br>"; print_r($dta); echo '</pre>';
            echo "TO BE PRINTED HERE, ID=$stmtid, productid=$prodid ($dta[prodtype]) <br><hr>";
            exit;
        }

        $persbnkId = AppEnv::getConfigValue($this->mtpost.'_persdata_pdf', '');
        # сделать в *def.xml описание переменной под список ИД банков, у которых надо добавлять страницу о согласии на ОПД

        $idlist = $persbnkId!=='' ? explode(',', $persbnkId) : array(0);
        $primaryBank = $this->_rawAgmtData['headdeptid']; // OrgUnits::getPrimaryDept();

        if($idlist[0] >0 && in_array($primaryBank, $idlist)) {
            # Добавляю PDF страницу для заполнения согласия на обработку персональных данных
            $srcfile = $this->home_folder . 'pers-obrabotka.pdf';
            if (!is_file($srcfile)) $srcfile = 'templates/anketa/pers-obrabotka.pdf';
            if (is_file($srcfile)) {
                $pgdef = array('src'=> $srcfile);
                PM::$pdf->addPageDef($pgdef);
            }
        }
        $dta['city_name'] = AppEnv::getDeptCity($this->_rawAgmtData['deptid']);

        $this->prepareForPrintStmt($dta);
        unescapeDbData($dta);
        # WriteDebugInfo('_rawAgmtData: ', $this->_rawAgmtData); WriteDebugInfo('dta: ', $dta);

        $this->addAllAnketas('Z', $dta); # анкеты ФЛ, ЮЛ, застрахованного,с выгодоприобр, опросный лист
        $this->printdata =& $dta;
        # {upd/2025-02-04} теперь согласие на ПДН - единообразный вывод из настроенного комплекта шаблонов
        $this->printUnifiedPdn('Z', $dta); # если печать согласий ПДн настроена в заяву, вывести !

        if (!empty($this->_deptCfg['zadd_print'])) { # доп-страницы в заявлении для банка
            $addXmlCfg = $this->home_folder . $this->_deptCfg['zadd_print'];
            if (!is_file($addXmlCfg)) {
                $addXmlCfg = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'anketa/' . $this->_deptCfg['zadd_print'];
            }
            if (is_file($addXmlCfg)) {
                $addXmlCfg = PlcUtils::getTemplateEDO($addXmlCfg);
                if ($this->debugAnk) WriteDebugInfo("В заявлении - доп-печать из xml: ", $addXmlCfg);
                PM::$pdf->AppendPageDefFromXml($addXmlCfg);
            }
        }

        /**
        # на боевом - временно прикрыл, пока не выверят справочник
        $dta['dept_name'] = AppEnv::getOfficialDeptName($this->_rawAgmtData['deptid'], true);
        $dta['primarydept_name'] = AppEnv::getOfficialDeptName($this->_rawAgmtData['headdeptid'], true);

        if ($dta['primarydept_name'] === $dta['dept_name']) $dta['dept_name'] = ''; # не печатаю одно и то же
        */
        if (!empty($this->_rawAgmtData['b_test']))
            $dta['demofld'] = 'ТЕСТИРОВАНИЕ';

        # echo 'stmt final data <pre>' . print_r($dta,1). '</pre>'; exit;
        # echo 'pdf Obj <pre>' . print_r(PM::$pdf,1). '</pre>'; exit;
        PM::$pdf->AddData($dta);

        $echoed = ob_get_clean();
        # if($this->_drawGrid) PM::$pdf->DrawMeasuringGrid();
        $result = PM::$pdf->Render(true, $this->_debugPdf);
        $pdfErr = PM::$pdf->GetErrorMessage();
        if ($pdfErr) exit("Ошибка при генерации PDF: " . $pdfErr);
        if($echoed !='') {
            WriteDebugInfo("[print_stmt - Error]: parasite echo while creating PDF [$echoed]");
        }
        if ($tofile) return $outputName;
        # writeDebugInfo("Render result: ", $result, ' PDF err:', $pdfErr);
        exit;
    }

    # вытаскивает из XML параметры в userparams и добавляет в $this->xmlUsrParams
    public function getUserParamsXml($xmlFile) {
        if(!is_file($xmlFile)) return;
        $xmlObj = @simplexml_load_file($xmlFile);
        if(is_object($xmlObj) && isset($xmlObj->userparameters)) {
            if(self::$debug) writeDebugInfo("гружу userparameters из $xmlFile ...");
            $params = [];
            foreach($xmlObj->userparameters->children() as $key=>$element) {
                $pname = (string) $element['name'];
                $pval = (string) $element['value'];
                $params[$pname] = $pval;
            }
            if(!is_array($this->xmlUsrParams)) $this->xmlUsrParams = [];
            $this->xmlUsrParams = array_merge($this->xmlUsrParams, $params);
            # exit("prepareForPrintPAck: params in KID XML:<pre>".print_r($params,1).'</pre>');
        }
        unset($xmlObj);
    }
    # Добавлен $progid - в наследниках может использоваться (invins)
    public function deptProdParams($plgid = '', $headdept=0, $codirovka='', $includeBlocked=FALSE, $progid = FALSE) {
        if (empty($plgid)) $plgid= $this->module;
        if (empty($headdept)) $headdept = OrgUnits::getPrimaryDept();
        $this->_deptCfg = PlcUtils::deptProdParams($plgid, $headdept, $codirovka,$includeBlocked, $progid);
        # writeDebugInfo("deptCfg($plgid, $headdept, $codirovka,[$includeBlocked],[$progid]): ", $this->_deptCfg);
        # writeDebugInfo("trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        return $this->_deptCfg;
    }

    /**
    * Печать пакета документов (полис, правила, фин-план  и т.д.)
    * @param $id ИД полиса, (если вызывается из другого модуля)
    * @param $tofile - true если надо сформировать в файл в tmp папке (для дальнейшей работы, отправки по Email...)
    * @param $mode - 'draft' - отключить вывод штампа, добавить слово ЧЕРНОВИК
    *
    */
    public function print_pack($id=0, $tofile=FALSE, $mode='', $getData=FALSE, $forOnline=FALSE) {
        # writeDebugInfo("mode=$mode, stack: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4));
        # exit ("begin: draft_packet " .$this->draft_packet . '<br>');
        $curDir = getcwd();
        chdir(ALFO_ROOT);
        if(AppEnv::isApiCall()) {
            $this->_p = AppEnv::$_p;
            if (!empty($this->_p['stmt_id'])) $id = $this->_p['stmt_id'];
        }
        if (self::$debug) WriteDebugInfo("print_pdf Start. p: ", $this->_p);

        $xmlFolder = $this->home_folder;
        if (stripos($xmlFolder,ALFO_ROOT) === FALSE)
            $xmlFolder = ALFO_ROOT . $this->home_folder;

        AppEnv::$tmpdata =& $this->_rawAgmtData;
        if(is_dir($xmlFolder . 'printcfg')) # TODO - при вызове из eqpayments не приставляет printcfg!
            $xmlFolder .= 'printcfg/';

        # writeDebugInfo("final XML folder for $this->module: ", $xmlFolder);
        # exit("printpack: xmlFolder = $xmlFolder");
        $dta = FALSE;

        # {updt/2025-05-15} BUGFIX: печать при выпуске полиса - не выводил анкеты/памятки ЦБ!
        $printFromCalc = (!empty($this->printdata) && is_array($this->printdata) && empty($this->_rawAgmtData['stmt_id']));

        if ( AppEnv::isApiCall() && empty($tofile) )
            $tofile = true; # при вызове из API генерю в файл на диске в tmp/, верну его fullpath

        if ($printFromCalc) {
            # печать черновика по данным из калькулятора
            $dta = $this->printdata;
            # echo 'data <pre>' . print_r($dta,1). '</pre>'; exit;
        }
        elseif ($id > 0) {
            # вызов из внешних процедур, права не проверяем!
            $stmtid = $id;
        }
        else {
            # обычный вход сотрудника
            $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        }
        if( $printFromCalc || $getData || AppEnv::isClientCall() || empty($stmtid) ) {
            $access = 1.5; # {upd/2022-11-14} вызов клиентом со страницы ЭДО согласования!
        }
        else {
            if($forOnline) $access = 2; # запрос со страницы клиента (не авторизованного)
            else $access = $this->checkDocumentRights($stmtid);
        }

        if ( $access<1.5 && !AppEnv::isIntCall()) {
            if (self::$debug) WriteDebugInfo("exiting, no rights for print, client not identifed/$stmtid, access = [$access]");
            if (AppEnv::isApiCall()) {
                return array(
                  'result' =>'ERROR',
                  'message' => AppEnv::getLocalized('err-no-rights-document')
                );
            }

            AppEnv::echoError('err-no-rights-document');
            exit;
        }


        $nostamp = isset($this->_p['nostamp']) ? $this->_p['nostamp'] : 0;
        if ($printFromCalc) {
            $headdept = OrgUnits::getPrimaryDept();
            # $plg = AppEnv::$_p['plg'];
            $plgid = $this->module;
            $dta['policyno_date'] = 'Черновик (калькуляция)';
        }
        else {
            $dta = $this->loadPolicy($stmtid,'print');
            if(!$getData) {
                if (($edoFile = UniPep::getFixedPolicy($this->module, $this->_rawAgmtData,PM::$scanEdoPolicy))) {
                    # Сохранена "фиксированная" версия полиса (ЭДО) - вывожу ее, больше файл не генерю!
                    if(self::$debug) writeDebugInfo("Использется фиксированная версия е-полиса (ЭДО)");
                    if(AppEnv::isApiCall()) {
                        return [ 'result'=>'OK', 'filepath'=>$edoFile['fullpath'], 'filename'=>"epolicy-$id.pdf", 'filesize'=>filesize($edoFile['fullpath']) ];
                    }
                    elseif($tofile) return $edoFile['fullpath'];

                    AppEnv::sendBinaryFile($edoFile['fullpath'], $edoFile['filename']);
                    exit;
                }
            }
            # {upd/2022/10-31} если полис еще не оплачен и просрочена макс.дата выпуска, печать блокируется
            if($this->policyCalcExpired()) {
                if(SuperAdminMode())
                    $dta['demofld'] = $this->draftWord = 'ПРОСРОЧЕН!';
                else {
                    exit(AppEnv::getLocalized('err-policy-calculation-expired'));
                }
            }
            # echo '_rawAgmtData <pre>' . print_r($this->_rawAgmtData,1). '</pre>'; exit;
            # WriteDebugInfo("this->_raw", $this->_rawAgmtData);
            $headdept = $dta['headdeptid'];
            $plgid = empty($this->module) ? $dta['module'] : $this->module;
            # {upd/2022-10-31} - нове поле откуда беру "дату выпуска" (дата последнего перерасчета)

        }

        $dta['anketaid'] = $this->_rawAgmtData['anketaid']; # loadPolicy: почему-то занулилась!
        # echo '1: Before InvestAnketa $dta<pre>' . print_r($dta,1). '</pre>';
        # echo '2: _rawAgmtData<pre>' . print_r($this->_rawAgmtData,1). '</pre>'; exit;

        if (empty($headdept)) {
            $err = 'Ошибка вызова - не удалось получить ИД головного орг-юнита';
            writeDebugInfo($err, ' params: ',AppEnv::$_p); # appEnv::logErr();
            exit($err);
            # AppEnv::echoError($err); exit;
        }

        # $this->_deptCfg = \PlcUtils::deptProdParams($this->module,$headdept,($this->_rawAgmtData['prodcode']??''));

        # {upd/2023-03-16} - новый документ в хвосте - КИД
        $kidTemplate = '';
        if(method_exists($this, 'getKIDtemplate'))
            $kidTemplate = $this->getKIDtemplate();
        else {
            $kidTemplate = AppEnv::getAppFolder('plugins/' . $this->module . '/printcfg/') . 'kid-' . $this->agmtdata['prodcode'] . '.xml';
            if(!is_file($kidTemplate)) { # последняя попытка - найти "общую" настройку КИД без указаниЯ кодировки:
                $kidTemplate = AppEnv::getAppFolder('plugins/' . $this->module . '/printcfg/') . 'kid.xml';
            }
        }
        # {upd/2023-06-07} - заношу блок userparams из XML в переменную бакенда (если понадобится при работе prepareForPrint...
        if(!empty($kidTemplate) && is_file($kidTemplate)) {
            $this->getUserParamsXml($kidTemplate);
        }

        # $skipCB = is_file($kidTemplate); # если будет КИД, памятку ЦБ не выводить!?
        $skipCB = FALSE; # СЖ, ПР в агентских - УИД + пам.ЦБ

        $withBlocked = !empty($this->_rawAgmtData['b_test']);
        $this->deptProdParams($this->module,$headdept,'',$withBlocked, $this->_rawAgmtData['programid']);
        $this->_deptReq = OrgUnits::getOuRequizites($headdept, $this->module);
        if(!isset($this->_deptReq['formname'])) {
            writeDebugInfo("getOuRequizites - не нашел реквизиты для {$this->module}/headdept=[$headdept]/");
            # if(appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'Не настройки партнера на данный продукт!'];
        }
        $formname = $this->_deptReq['formname'] ?? '';

        # {upd/2022-02-02} запомнил, при определении всяких файлов шаблонов буду проверять на наличие альт.файлов под данное имя для "банка"
        if ($formname) OrgUnits::setHeadOuId($formname);

        if (isset($this->_rawAgmtData['bptype'])) {
            if ($this->isEdoPolicy()) {
                # {upd/2024-12-28} ЭЦП наложить только если полис в продвинутых статусах иначе только SES (ПЭП-блок)!
                # $printEdoMode = (in_array($this->_rawAgmtData['bptype'], [PM::STATE_FORMED,PM::STATE_PAYED, PM::STATE_UWAGREED])) ? 1 : 'SES';
                PlcUtils::setPrintEdoMode(TRUE); # будем печатать из EDO-шаблонов
            }
            else {
                # Если по программе разрешен ЭДО процесс, но юзер еще не запустил ни ЭДО, ни без-ЭДО, печатать ЧЕРНОВИК!
                if(empty($this->_rawAgmtData['bptype']) && !empty($this->_deptCfg['online_confirm']) &&
                  !in_array($this->_rawAgmtData['stateid'], [PM::STATE_FORMED, PM::STATE_PAYED])) {
                    $this->draft_packet = 1;
                }
            }
        }
        $metaType = $this->_rawAgmtData['metatype'] ?? $this->agmtdata['metatype'] ?? 0;
        # exit($mode . '=mode, metaType:<pre>'. print_r($this->agmtdata,1).'</pre>');
        if (empty($mode)) {
            if (method_exists($this, 'draftPacketMode')) {
                $mode = $this->draftPacketMode();
                # echo "mode=[$mode] from draftPacketMode() call!<br>";
            }
            else {
                if($metaType == OrgUnits::MT_BANK) {
                    if ($this->draft_packet && !in_array($this->_rawAgmtData['stateid'], [ PM::STATE_FORMED, PM::STATE_PAYED ])) {
                        $mode = 'draft';
                        if(in_array($this->_rawAgmtData['stateid'], [PM::STATE_IN_FORMING, PM::STATE_UWAGREED])
                          && $this->_rawAgmtData['bpstateid'] == PM::BPSTATE_RELEASED) {
                            $mode = ''; # Банковский канал, полис "выпущен" - начинаю печатать чистовик!
                        }
                    }
                }
                else { # агентский и др. НЕ банковский
                    if ($this->draft_packet && !in_array($this->_rawAgmtData['stateid'], [ PM::STATE_FORMED ])) {
                        $mode = 'draft';
                        if($this->_rawAgmtData['stateid'] == PM::STATE_PAYED && $this->_rawAgmtData['bpstateid'] == PM::BPSTATE_RELEASED) {
                            $mode = ''; # полис "выпущен" - начинаю печатать чистовик!
                        }
                    }

                }
            }
        }
        # exit ("mode = [$mode]<br> stateid:". $this->_rawAgmtData['stateid'] . " draft_packet: [$this->draft_packet]");

        $prodid = $plgid;
        $program = '';
        if (self::$debug) WriteDebugInfo('print_stmt loaded/prepared data:', $dta);
        if (!empty($dta['prodcode'])) {
            $prodid = mb_strtolower($dta['prodcode']); # BCL -> bcl ...
        }
        if (!empty($dta['programid'])) {
            $program = $dta['programid']; # TermFix, ...
        }

        if (!empty($dta['policyno']))
            $pno = str_replace(array('/','\\','*','?',':'), '-', $dta['policyno']);
        else $pno = $plgid;
        $module = $this->module;

        AppEnv::avoidExternalHeaders();
        $cfgname = '';
        if (method_exists($this, 'getPackConfigName')) {
            $cfgname = $this->getPackConfigName($prodid, $dta, $program);
            # exit("returned XML pack cfg:".$cfgname . ' <pre>'.print_r($dta,1).'</pre>');
        }
        else {
            $names = [];
            if (!empty($dta['previous_id'])) {    # Если это пролонгация, ищу соотв.файл настроек "***-prolong.xml"
                # {upd/2021-04-07} - Н.Нисурмина - подстройка персональной ПФ для партнера (по его ИД орг-юнита)
                if ($formname) {
                    $names[] = "cfg-docpack-$prodid-$formname-prolong.xml";
                    $names[] = "cfg-docpack-$plgid-$formname-prolong.xml";
                }
                if ($headdept) {
                    $names[] = "cfg-docpack-$prodid-$headdept-prolong.xml";
                    $names[] = "cfg-docpack-$plgid-$headdept-prolong.xml";
                }

                $names[] = "cfg-docpack-$prodid-prolong.xml";
                $names[] = "cfg-docpack-$plgid-prolong.xml";
            }

            if ($formname) { # шаблоны с именем формы, указанным в реквизитах головного ОУ
                $names[] = "cfg-docpack-$prodid-$formname.xml";
                $names[] = "cfg-docpack-$plgid-$formname.xml";
            }

            if ($headdept) {
                $names[] = "cfg-docpack-$prodid-$headdept.xml";
                $names[] = "cfg-docpack-$plgid-$headdept.xml";
            }

            $names[] = "cfg-docpack-$prodid.xml";
            $names[] = "cfg-docpack-$plgid.xml";

            foreach($names as $fn) {
                if (is_file($xmlFolder . $fn)) {
                    $cfgname = $xmlFolder . $fn;
                    break;
                }
            }
            /*
            if (!empty($dta['previous_id'])) {    # Если это пролонгация, ищу соотв.файл настроек "***-prolong.xml"
                $cfgprol = $xmlFolder . "cfg-docpack-$prodid-prolong.xml";
                $cfgprol2= $xmlFolder . "cfg-docpack-$dta[module]-prolong.xml";
                if (is_file($cfgprol)) $cfgname = $cfgprol;
                if (is_file($cfgprol2)) $cfgname2 = $cfgprol;
            }
            */
        }
        # if (!is_file($cfgname) && is_file($cfgname2)) $cfgname = $cfgname2;
        if (self::$debug) WriteDebugInfo("print_pack KT-020, print config: ", $cfgname);

        $cfgname = PlcUtils::getTemplateEDO($cfgname); # если ЭДО режим, ищет -EDO.xml версию настройки печати
        # exit(__LINE__ . "/xml pack name:$cfgname, headdept:$headdept<br>");

        # если супер-опер ввел вручную дату "С" задним числом, корректирую дату "подписания"

        # {upd/2023-10-25} беру из agmtdata макс.дату оплаты и дату выпуска, если выпущен
        $this->agmtdata = AgmtData::getData($this->module,$this->_rawAgmtData['stmt_id'],0);
        if(isset($this->agmtdata['max_datepay'])) {
            if(intval($this->agmtdata['max_datepay']))
                $dta['max_datepay'] = to_char($this->agmtdata['max_datepay']);
            if(intval($this->agmtdata['date_release']))
                $dta['date_release'] = to_char($this->agmtdata['date_release']);
        }

        if (!empty($this->_rawAgmtData['created'])) {
            $datesign = min($this->_rawAgmtData['created'], $this->_rawAgmtData['datefrom']);
            $dta['date_sign_verbose'] = AppEnv::dateVerbose($datesign, 1);
        }

        if ($mode === 'draft') {
            $dta['demofld'] = (!empty($this->draftWord) ? $this->draftWord : 'ЧЕРНОВИК');
            # exit("mode : $mode, data <pre>" . print_r($dta,1). '</pre>');
        }

        $prepared = $this->prepareForPrintPacket($dta, TRUE);
        $this->finalizeSpecCondText($dta); # добавит текст про реинвестицию, или "Нет" если ос.условий нет
        $this->printdata =& $dta;
        if($getData) return $dta;

        if (empty($cfgname) || !is_file ($cfgname)) {
            $err = "$plgid: Для данного продукта нет настроек печати ($cfgname)";
            AppEnv::setFailMessage($err);
            appAlerts::raiseAlert("PRINT-PDF.$plgid", "$plgid - $err (произошло при генерации PDF для $pno)");
            if (AppEnv::isIntCall()) return array(
                'result' => 'ERROR',
                'message' => $err
            );
            AppEnv::echoError($err);
            exit;
        }
        else appAlerts::resetAlert("PRINT-PDF.$plgid","Проблема с настройкой печати для $plgid устранена");

        include_once('printformpdf.php');
        # if(!PrintFormPdf::$DEBPRINT)
        ob_start();

        # writeDebugInfo("(after EDO apply) policy XML: ", $cfgname);
        # exit("mode: [$mode], cfgname: $cfgname");
        $plcPostfix = ($mode ==='draft')? '-draft' : '';
        $outputName = ($tofile) ? ALFO_ROOT . "tmp/policy-$pno{$plcPostfix}.pdf" : "policy-$pno{$plcPostfix}.pdf";
        $outputName = translit($outputName); # чтоб без русских букв в имени файла
        $options = array(
           'configfile' => $cfgname
          ,'outname'    => $outputName
          ,'hook_end' => 'PlcUtils::setLastPdfPage'
          ,'compression' => self::COMPRESS_PDF
        );
        if ($tofile) $options['tofile'] = true; # в файл на диске

        # {upd/2025-05-20} если полис выпущен|оформлен, но Е-полиса нет в базе (удалили) - создаю ЗАНОВО!
        $resaveEdo = FALSE;
        if($this->_rawAgmtData['metatype'] == OrgUnits::MT_AGENT) $eList = [PM::STATE_FORMED];
        else $eList = [PM::STATE_FORMED];
        if( $stmtid && $this->_rawAgmtData['bptype']==='EDO' && !empty(AppEnv::$_p['clientprint'])) {
            if($this->_rawAgmtData['metatype'] == OrgUnits::MT_AGENT)
                $forResave = (in_array($this->_rawAgmtData['stateid'], [PM::STATE_FORMED]) || ( $this->_rawAgmtData['stateid']==PM::STATE_PAYED
                  && $this->_rawAgmtData['bpstateid']==PM::BPSTATE_RELEASED) );

            else $forResave = (in_array($this->_rawAgmtData['stateid'], [PM::STATE_FORMED, PM::STATE_PAYED]) || ( $this->_rawAgmtData['stateid']==PM::STATE_IN_FORMING
                  && $this->_rawAgmtData['bpstateid']==PM::BPSTATE_RELEASED) );

            if($forResave) {
                $edoPlc = \FileUtils::getFilesInPolicy($this->module,$stmtid,["doctype='edo_policy'"]);
                if(!is_array($edoPlc) || !count($edoPlc)) $resaveEdo = TRUE;
            }
        }

        if($resaveEdo) {
            $options['tofile'] = TRUE;
            $options['outname'] = $outputName = AppEnv::getAppFolder('tmp/') . "tmp_epolicy-$stmtid.pdf";
            \UniPep::$setDigiSign = TRUE;
        }
        if(self::$debug) writeDebugInfo("resaveEdo=[$resaveEdo], options: ", $options, "\n get params: ", AppEnv::$_p);
        # exit(__FILE__ .':'.__LINE__." _rawAgmtData($resaveEdo):<pre>" . print_r($options,1) . '</pre>');
        PM::$pdf = new PrintFormPdf($options, $this);

        if (method_exists($this, 'beforePrintPack')) $this->beforePrintPack($dta);

        # Метод вставит страницы, который должны быть сразу после ПФ полиса
        $this->pagesAfterPolicy($dta);
        # {upd/08.08.2016} добавление листа согласия на обработку ПД, если есть xml-описание ДЛЯ ДАННОГО БАНКА

        $xmlSopd = $xmlFolder . "cfg-soglasie-opd-$headdept.xml";
        if (is_file($xmlSopd)) {
            PM::$pdf->AppendPageDefFromXml($xmlSopd);
        }

        # echo 'data /K33<pre>' . print_r($dta,1). '</pre>'; exit;


        if (isset($prepared['result']) && $prepared['result'] === 'ERROR') {
            if (AppEnv::isApiCall()) return $prepared;
        }
        # writeDebugInfo("printFromCalc=[$printFromCalc]");
        if (!$printFromCalc) { # при печати из калькуляции, анкеты и прочее фуфло не нужны!
            # все анкеты, какие выводить на полис
            $this->addAllAnketas('P', $dta, $skipCB);

            if ($this->_rawAgmtData['bptype'] === PM::BPTYPE_EDO && !empty($this->_deptCfg['print_edo'])) {
                $addXmlCfg = ALFO_ROOT . 'templates/anketa/' . $this->_deptCfg['print_edo'];
                if (is_file($addXmlCfg)) {
                    PM::$pdf->AppendPageDefFromXml($addXmlCfg);
                }
            }
            if (!empty($this->_deptCfg['add_print'])) {

                $addXmlCfg = $xmlFolder . $this->_deptCfg['add_print'];
                if (!is_file($addXmlCfg)) {
                    $addXmlCfg = ALFO_ROOT . 'templates/anketa/' . $this->_deptCfg['add_print'];
                }
                if (is_file($addXmlCfg)) {
                    $addXmlCfg = PlcUtils::getTemplateEDO($addXmlCfg);
                    # WriteDebugInfo("use add_print xml: ", $addXmlCfg);
                    PM::$pdf->AppendPageDefFromXml($addXmlCfg);
                }
            }

            # Памятка клиента (приказ ЦБ-11.2018)
            # {upd/2019-09-09} - передаю фин-план, чтобы посчитать правильный итоговый взнос!
            # writeDebugInfo('FIN-plan:', $this->finplan);
        }
        # else writeDebugInfo("addAllAnketas() skipped! rawData: ", AppEnv::$tmpdata);
        # echo 'dta/K0 <pre>' . print_r($dta,1). '</pre>';
        # вместо штампа с подписью - печатаю только подпись

        if ($nostamp == '1') {
            $stampsrc = empty($dta['faximile']) ? self::IMG_NOSTAMP : $dta['faximile'];
            PM::$pdf->setFieldAttribs('stamp', array('src'=>$stampsrc));
        }
        elseif ($nostamp == '2') { # вообще без печати/подписи
            PM::$pdf->setFieldAttribs('stamp', array('src'=>'--no-file--.png'));
        }
        elseif (!empty($dta['fullstamp'])) {
            PM::$pdf->setFieldAttribs('stamp', array('src'=>$dta['fullstamp']));
            # WriteDebugInfo("full stamp image from prod cfg:", $dta['fullstamp']);
        }
        # WriteDebugInfo("nostamp = [$nostamp] only-faximile image:", $stampsrc);
        elseif (!empty($this->_rawAgmtData['b_test']))
            $dta['demofld'] = 'ТЕСТИРОВАНИЕ';
        elseif ($mode === 'draft') {
            if (!empty($this->draftWord)) $dta['demofld'] = $this->draftWord;
            else $dta['demofld'] = 'ЧЕРНОВИК';
            PM::$pdf->setFieldAttribs('stamp', array('src'=>'')); // ни печати, ни факсимиле
        }
        elseif (isset(DEMOWords::$list[$this->_rawAgmtData['stateid']])) {
            $dta['demofld'] = DEMOWords::$list[$this->_rawAgmtData['stateid']];
        }
        # exit('demofld is '.$dta['demofld']);

        # принудительный вывод вод-знака для аннулированных, расторгнутых, заблокированных
        if (in_array($this->_rawAgmtData['stateid'], [9,10,50,60])) {
            $dta['demofld'] = DEMOWords::$list[$this->_rawAgmtData['stateid']];
            PM::$pdf->setFieldAttribs('stamp', array('src'=>'')); // ни печати, ни факсимиле
        }

        $dta['created_verbose'] = RusUtils::dateVerbose($this->_rawAgmtData['created']);

        # метод добавления "финальных" листов в формируемый PDF
        if (method_exists($this, 'finalPacketPages')) {
            if(self::$debug) writeDebugInfo("calling finalPacketPages...");
            $this->finalPacketPages($dta);
        }
        # {upd/2021-03-02} - инвест-анкета клиента в пакете с полисом
        # {upd/2023-08-14} - больше в полис не выводить! Подгружать в СЭД отдельным файлом при операции "Проверен" (PlcUtils::setStateActibe())
        # {upd/2024-03-29} - снова сказали "выводить" (Нисурмина + Яковлева(Дейнекина)
        /** **/
        if (!empty($dta['anketaid'])) {
            $padeAnketa = Investanketa::getPrintPage($dta, $dta['anketaid']);
            # exit(__FILE__ .':'.__LINE__.' invest anketa for create PDF: <pre>' . print_r($padeAnketa,1). '</pre>');
            if (is_array($padeAnketa))
                PM::$pdf->addPageDef($padeAnketa);
            elseif(is_string($padeAnketa)) # ЭДО-вернули имя XML файла шаблона инв-анкеты
                PM::$pdf->AppendPageDefFromXml($padeAnketa);
        }
        /** **/
        # {upd/2025-01-30} - подключение печати единой формы согласия на обработку ПДн
        $this->printUnifiedPdn('P', $dta); #
        # {upd/2025-07-03} анкету 6886-У выводить только в банковских полисах (а агентских - не надо, или наоборот - тоьлко в аг. НЕ выводить?)
        if($metaType == OrgUnits::MT_BANK && $this->anketa_6886 && $dta['insurer_type']==1) { # анкета 6886 только у ФЛ страхователя!
            # Добавляю на автомате вывод анкеты 6886-У
            $anketa6886Xml = PlcUtils::getTemplateEDO(AppEnv::getAppFolder('templates/anketa/').'anketa-6886U.xml');
            PM::$pdf->AppendPageDefFromXml($anketa6886Xml);
        }

        if(is_file($kidTemplate)) {
            # все поля к КИД должны быть приготовлены в $dta!
            PM::$pdf->AppendPageDefFromXml($kidTemplate);
            if(method_exists($this, 'prepareKidData'))
                $this->prepareKidData($dta);
        }

        # Если в папке настроек есть файл policy-ending.xml - добавляем в конец!
        $endingXml = $xmlFolder . 'policy-ending.xml';
        if (is_file($endingXml)) PM::$pdf->AppendPageDefFromXml($endingXml);

        # echo 'print dta <pre>' . print_r($dta,1). '</pre>'; exit;
        if ($this->multi_insured >=100 && method_exists($this, 'getInsuredList')) {
            # список Застрах - печатаю отдельный полис для каждого из списка
            $insureds = $this->getInsuredList($id, 'print');
            # writeDebugInfo("full_premium ", $this->_rawAgmtData['policy_prem']);
            if (is_array($insureds) && count($insureds)>0) {
                # премия в расчете на одного Застрахованного (N полисов в пачке)
                $dta['one_insured_prem'] = fmtMoney($this->_rawAgmtData['policy_prem'] / count($insureds));
            }
            if (is_array($insureds)) foreach($insureds as $oneIns) {
                $insdDta = array_merge($dta, $oneIns);
                # exit('one insured Full data <pre>' . print_r($insdDta,1). '</pre>');
                PM::$pdf->AddData($insdDta);
            }
        }
        else {
            PM::$pdf->AddData($dta);
        }
        # exit( 'data <pre>' . print_r($dta,1). '</pre>');

        # echo 'print data<pre>' . print_r($dta,1). '</pre>'; exit;
        $echoed = ob_get_clean();

        # if($this->_drawGrid) PM::$pdf->DrawMeasuringGrid();

        if($echoed !='') {
            WriteDebugInfo("[printPacket - Error]: parasite echo:[$echoed]");
        }
        # audit event, смена статуса полиса на "оформлен" ?
        # if (!$auth->supervisorMode()) AppEnv::logEvent($this->log_pref,'AGMT PRINT', 'Печать пакета документов', '', $stmtid);
        # WriteDebugInfo('stateid ',$dta['stateid']);

        if (PlcUtils::isPrintEdoMode()) {
            # если режим печати ЭДО, полис в статусе "оформлен" обязательно подписывается!
            $this->sign_pdf_states[] = PM::STATE_FORMED;
            if (AppEnv::isLocalEnv()) # DEBUG: для ЭДО полиса включаю ЭЦП подписание при любом статусе (в локальной среде разраба!)
                $this->sign_pdf_states[] = $dta['stateid'];
            /*
            if($dta['metatype'] == OrgUnits::MT_BANK) {
                writeDebugInfo("bank policy: add digisign? : ", $dta);
                writeDebugInfo("trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
            }
            */
        }

        $addDigiSign = ( (!empty(UniPep::useDigiSign() || in_array($dta['stateid'], $this->sign_pdf_states)))
          && DigiSign::isServiceActive($this->module) && $dta['substate']!=PM::SUBSTATE_EDO2_STARTED );
        # SUBSTATE_EDO2_STARTED - если полис отдаем кдиенту на страницу ЭДО подтверждения изменений в данных, ЭЦП не ставим!
        # writeDebugInfo("addDigiSign=[$addDigiSign]");

        if ( $addDigiSign ) { # !$this->printdata &&
            if (self::$debug) WriteDebugInfo("буду подписывать файл ЭЦП (DigiSign)");
            if(PlcUtils::isPrintEdoMode()) {
                $usrParams = PM::$pdf->getUserParameters();
                PlcUtils::setSignatureData($usrParams);
            }
            # digisign сможет получить aliasKey для данного модуля, если он задана настройках:
            AppEnv::setUsrAppState('sign_module', $this->module);
            PM::$pdf->addSignFunction('digisign::signPdfBody');
            # Если для плагина есть настройка своего алиаса сертификата, активирую ее (уже есть в самом digiSign)
        }

        if (self::$debug) WriteDebugInfo("KT-800, gonna generate PDF...");
        PM::$pdf->Render(true, $this->_debugPdf);
        if (self::$debug) WriteDebugInfo("print_pdf KT-999, render executed");

        if (AppEnv::isApiCall()) {
            if (self::$debug) WriteDebugInfo("internal or API call, returned");

            $ret = ['result'=>'OK', 'filename'=> basename($outputName),
               'filesize'=> filesize($outputName),
               'filepath'=> $outputName
            ];
            AppEnv::logEvent($this->log_pref . "API:GET PDF", "API выгрузка PDF полиса, размер(Б): ".filesize($outputName),0,$stmtid);
            if (count(AppEnv::$warnings))
                $ret['message'] = implode('; ',AppEnv::$warnings);

            chdir($curDir);
            return $ret;
        }
        if($resaveEdo) {
            # 1) сохранить файл в базу как е-полис, и вывести его в браузер клиенту
            $fparams = [
              'module' => $this->module,
              'id' => $stmtid,
              'doctype' => PM::$scanEdoPolicy,
              'fullpath' => $outputName,
            ];
            $policyno = $this->_rawAgmtData['policyno'];
            $finalFileName = FileUtils::addScan($fparams,TRUE, $this->_rawAgmtData);
            if(self::$debug) writeDebugInfo("addScan returns (resaveEdo): ", $finalFileName);
            if(is_string($finalFileName) && is_file($finalFileName))
                AppEnv::sendBinaryFile($finalFileName, "epolicy-regenerate-$policyno.pdf");
        }
        if ($tofile) return $outputName;
        exit;
    }
    /**
    * Печать формы A7
    *
    */
    public function print_a7() {

        if(!count($this->_p)) $this->_p = AppEnv::$_p;
        $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;

        $dta = $this->loadPolicy($stmtid,'print');
        $access = $this->checkDocumentRights($stmtid);
        if ($access<2) AppEnv::echoError('err-no-rights-document');

        AppEnv::avoidExternalHeaders();

        if (self::$debug) WriteDebugInfo('print_a7 loaded/prepared data:', $dta);
        $prodid = $this->_rawAgmtData['programid'];
        $pno = $this->agmtdata['policyno'];

        include_once('printformpdf.php');
        # include_once('sumpropis.php');

        $xmlFolder = $this->home_folder;
        if(is_dir($xmlFolder . 'printcfg')) $xmlFolder .= 'printcfg/';

        ob_start();


        $cfgname = (method_exists($this, 'getA7ConfigName')) ? $this->getA7ConfigName($prodid) : ('cfg-form-a7.xml');
        if (!is_file ($xmlFolder . $cfgname)) AppEnv::echoError('Для данного продукта печать формы A7 еще не настроена : нет файла '.$this->home_folder . $cfgname);
        PM::$pdf = new PrintFormPdf(array(
           'configfile' => ( $xmlFolder . $cfgname )
          ,'outname'    => "form-a7-$pno.pdf"
          # ,'compression' => self::COMPRESS_PDF
        ), $this);
        $this->prepareForPrintA7($dta);

        PM::$pdf->AddData($dta);

        $echoed = ob_get_clean();

        if($this->_drawGrid) PM::$pdf->DrawMeasuringGrid();

        if($echoed !='') {
            WriteDebugInfo("[printPacket - Error]: parasite echo:[$echoed]");
        }
        # audit event, смена статуса полиса на "оформлен" ?
        # if (!$auth->supervisorMode()) AppEnv::logEvent($this->log_pref,'AGMT PRINT', 'Печать пакета документов', '', $stmtid);
        PM::$pdf->Render(true, $this->_debugPdf);
        exit;

    }
    public function prepareForPrintA7(&$dta) {
        // to re-define in plugin!
        $dta['insurant_name'] = $dta['insurer_fullname'];

    }
    # {upd/2023-11-14} - перенс ф-ционал loadPolicySpecData в модуль app/plcutils.php:loadPolicySpecData()
    public static function loadPolicySpecData($id, $finplanFmt = '') {
        $ret = PlcUtils::loadPolicySpecData($id);
        /*
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
        if ((self::$debug>1)) {
            WriteDebugInfo("loadPolicySpecData($id) returns:",$ret);
        }
        */
        return $ret;
    }
    public function loadSpecData($id) {

        $ret = self::loadPolicySpecData($id, $this->finplan_fmt); # static call
        if(isset($ret['calc_params'])) {
            $this->calc = $ret['calc_params'];
            $this->spec_params = $ret['spec_params']; # $this->spec_fields = $ret['spec_params'];
            $this->srvcalc = $ret['ins_params'];
            $this->ins_params =& $this->srvcalc;
            $this->finplan = $ret['fin_plan'];
            if (!empty($ret['spec_conditions']))
                $this->specCond = $ret['spec_conditions'];
        }
        # writeDebugInfo("loadSpecData($id) done, this->ins_params = ", $this->ins_params);
        return $ret;
    }
    /**
    * Отдельная печать анкеты (Страхователя, ЗАстрахованного)
    *
    */
    public function printAnketas() {

        if(!count($this->_p)) $this->_p = AppEnv::$_p;
        $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;

        $dta = $this->loadPolicy($stmtid,'print');
        if (!isset($this->_deptCfg['id']))
            $this->deptProdParams($this->module,$dta['headdeptid'], '', $this->_rawAgmtData['b_test'], $this->_rawAgmtData['programid']);

        $access = $this->checkDocumentRights($stmtid);
        if ($access<2) AppEnv::echoError('err-no-rights-document');

        AppEnv::avoidExternalHeaders();

        if (self::$debug) WriteDebugInfo('printAnketas data:', $dta);
        $prodid = $this->_rawAgmtData['programid'];
        $pno = $dta['policyno'];

        include_once('printformpdf.php');

        $cfgname = ALFO_ROOT . 'templates/anketa/empty.xml';

        ob_start();

        PM::$pdf = new PrintFormPdf(array(
           'configfile' => $cfgname
          ,'outname'    => "anketas-$pno.pdf"
        ), $this);
        $this->addAllAnketas('*', $dta);
        $echoed = ob_get_clean();

        if($echoed !='') {
            WriteDebugInfo("[printPacket - Error]: parasite echo:[$echoed]");
        }
        PM::$pdf->addData($dta);
        PM::$pdf->Render(true, $this->_debugPdf);
        exit;
    }

    /**
    * Проверяю наличие прав на чтение/редактирование полиса у юзера
    * Возвращает 1 - можно просматривать, 2+ - можно редактировать
    * {upd/2023-02-21} - добавил проверку глобального права роли Комплайнс, 2024-12-05 - сотр.ИБ - RIGHT_INFOSEC
    */
    public function checkDocumentRights($docid=0) {

        $ret = 0;
        if (is_scalar($docid)) {
            if (!$docid) {
                if(isset($this->_rawAgmtData['stmt_id'])) $docid = $this->_rawAgmtData['stmt_id'];
            }
            if (!$docid) {
                return 0;
            }
        }
        if(self::$debug) writeDebugInfo("agmt ot check rights: ", $this->_rawAgmtData);
        if($this->getUserLevel()>=10 || SuperAdminMode() || AppEnv::$auth->getAccessLevel(PM::RIGHT_SUPEROPER))
            return PM::LEVEL_SUPEROPER;

        $editlev = FALSE;
        if( AppEnv::isGlobalViewRights() ) {
            $editlev = max(0.5,  AppEnv::$auth->getAccessLevel($this->privid_editor));
            $viewLev = max(6, AppEnv::$auth->getAccessLevel($this->privid_editor));
            # writeDebugInfo("Comp OR EinfoSec, editlev=$editlev, viewLev=$viewLev");
        }
        if(!$editlev) {
            $viewLev = $this->getUserLevel('reports');
            $editlev = $this->getUserLevel();
        }
        if(self::$debug) writeDebugInfo("user edit level({$this->privid_editor}): $editlev");

        if ( $editlev<=0 ) {
            if($viewLev>=5) return 0.1;
        }
        # $ret = $editlev; # 0.1 ... 0.99 - no editing rights

        # WriteDebugInfo("checkDocumentRights ret: $ret");
        # if($auth->getAccessLevel($this->privs_icadmins)) return 1;
        if (is_array($docid)) $rec = $docid;
        else  $rec = $this->_rawAgmtData;
        if (!$rec) $rec = AppEnv::$db->select(PM::T_POLICIES, array(
            'fields'=>'stmt_id,deptid,userid,metatype,headdeptid,datepay,stmt_stateid,stateid'
           ,'where'=>"(stmt_id='$docid')"
           ,'associative'=>1, 'singlerow'=>1)
        );
        # WriteDebugInfo('rec:', $rec);
        if (!isset($rec['stmt_id'])) return 0;

        if( AppEnv::isGlobalViewRights() ) $ret = $editlev;
        else $ret = 0;

        $primaryDept = OrgUnits::getPrimaryDept();

        if ($editlev >= PM::LEVEL_UW ) $ret = 2; # ВСЕ полисы доступны для чтения и изменения
        elseif( floor($editlev) == PM::LEVEL_IC_ADMIN ) { # ПП = "сотрудник СК"
            $myMeta = OrgUnits::getMetaType($primaryDept);
            if($rec['metatype'] == $myMeta)
                $ret = 2;
            else $ret = FALSE;
        }
        elseif ($editlev == PM::LEVEL_CENTROFFICE) { # "Сотрудник центр.(головного)офиса банка" - все права на просмотр+редактир.
            $ret = max($ret, (($rec['headdeptid'] == $primaryDept) ? 2 : 0));
        }
        elseif ($editlev == PM::LEVEL_MANAGER) {
            $myDepts = AppEnv::$auth->getDeptsTree(); # array со списком своих подразд.
            # WriteDebugInfo('my dept:', AppEnv::$auth->deptid, " my dept tree::", implode(',',$myDepts));
            if (in_array($rec['deptid'], $myDepts)) {
                if ($editlev >= PM::LEVEL_MANAGER) $ret = max($ret,2);
            }
        }
        # exit("access ret now: $ret");
        # WriteDebugInfo('my head:',$primaryDept, 'policy headbank:', $rec['headdeptid'] );

        # Запись заведена мной, и пока я в том же головном банке, всегда имею к ней доступ :
        if ($rec['userid'] == AppEnv::$auth->userid && $rec['headdeptid'] == $primaryDept) $ret = max($ret,2);

        # $plcstate = $rec['stmt_stateid'];
        $plcstate = $rec['stateid'];
        if (!in_array($plcstate, $this->editable_states)) $ret = min(1.5, $ret); # только просмотр
        if($editlev >=PM::LEVEL_UW && $plcstate == PM::STATE_UNDERWRITING) $ret = 2; # Андеррайтер может править полис в UW-статусе

        return $ret;
    }

    /**
    * Формирует фильтр для отбора полисов (изначально только по подразделениям)
    * ограничения на просмотр договоров взависимости от прав
    * @param $asArray - по умолчанию возвращает фильтр как готовую строку для WHERE, а при $asArray>0 - массивом кодов подраздел.
    * ЕЩЕ: при обычном (не в массив) возврате формируется фильтр, включающий полисы в том же "головном" подраздедении что и юзер,
    * если у них автор - этот юзер (после его перехода в другое отделение он продолжает видеть полисы, созданные им)
    * @param $tprefix - при необходимости в строковом результате добаляю префикс к именам поля из таблицы договоров
    * (она должна иметь в самом запросе алиас: $asArray="p." если алиас 'p' (select ... from {T_POLICIES} p  ...)
    */
    public function agmtDeptFilter($asArray=FALSE, $tprefix='') {

        $res = 'deptid<0';
        $deptlist = array();
        $repRights = [ $this->privid_editor,$this->privid_reports ];
        $acclev = AppEnv::$auth->getAccessLevel($repRights);

        $super = AppEnv::$auth->getAccessLevel([PM::RIGHT_SUPERADMIN]);
        if( AppEnv::isGlobalViewRights() ) {
            $acclev = max($acclev, 6);
        }
        # у "выгрузчика договоров" право смотреть ВСЕ, как у супер-операциониста

        if ($super) $acclev = PM::LEVEL_SUPEROPER;
        # WriteDebugInfo("super = $super, acclev = $acclev");
        if ($acclev<1) return $res;
        $deptlist = FALSE;
        $maindept = OrgUnits::getPrimaryDept();
        # if ($_SERVER['REMOTE_ADDR'] === '10.77.12.62') WriteDebugInfo("agrlist access: ",$acclev);
        # writeDebugInfo("module: [$this->module],acclev: $acclev, maindept: [$maindept]");
        switch(floor($acclev)) {

            case 0.5: case PM::LEVEL_OPER:
                $res = "(userid='" . AppEnv::getUserId() . "')";
                if ( !$asArray ) { # {redmine:355} операционист после перехода в другое подразд-е того же банка должен видеть свои старые дог.
                    if($maindept>0)
                       $res = "({$tprefix}headdeptid='$maindept' AND {$tprefix}userid='". AppEnv::$auth->userid ."')";
                }
                break;

            case PM::LEVEL_MANAGER:

                $deptlist = AppEnv::$auth->getDeptsTree(0, true);
                $res = "(deptid in ($deptlist))";
                if ( !$asArray ) { # {redmine:355} операционист после перехода в другое подразд-е того же банка должен видеть свои старые дог.
                    if($maindept>0)
                       $res = "(($res) OR ({$tprefix}headdeptid='$maindept' AND {$tprefix}userid='". AppEnv::$auth->userid . "'))";
                }
                break;

            case PM::LEVEL_CENTROFFICE:
                $res = "{$tprefix}headdeptid='$maindept'";
                break;
            # {upd/2023-10-06} - режу: отделяю ПП банков от ПП агентов
            case PM::LEVEL_IC_ADMIN:
                $myMeta = OrgUnits::getMetaType();
                # writeDebugInfo("$this->privid_reports, view level:$acclev, mt metaType: [$myMeta], may main dept: $maindept");
                if($myMeta == PM::META_AGENTS) { # в агентках сотрудник СК видит только свою аг.сеть
                    $res = "headdeptid='$maindept'";
                }
                elseif($myMeta == OrgUnits::MT_BANK) { # Сотрудник СК банков - видит ВСЕ банки во всех "банк-сетях"
                    $res = "metatype=".OrgUnits::MT_BANK;
                }
                else $res = '(1)'; # В банках сотрудник СК видит ВСЕ банки
                # if($myMeta>0) $res = "metatype=$myMeta";
                # $myHDlist = OrgUnits::getMyChannelHD();
                # if(count($myHDlist)) $res = "headdeptid IN(".implode(',',$myHDlist) . ')';
                # else $res = '(1)';
                # writeDebugInfo("$this->module, LEVEL_IC_ADMIN: my meta:$myMeta,  agrlist filter is ",$res);
                break;
        }
        if ($acclev >=PM::LEVEL_UW) $res = '(1)'; # все что не ниже чем UW- все полисы доступны
        else {
            $this->reportDept = $maindept;
        }

        return ($asArray && is_array($deptlist) ? $deptlist : $res);
    }
    /**
    * Получить кол-во файлов сканов к полису
    *
    * @param mixed $plcid
    * @param mixed $doctype = "" - тип скана. Если не передать, вернет ассоц.массив ['type' => кол_во,...]
    */
    public function getScanCount($plcid, $doctype='') {
        if ($doctype) {
            $ret = AppEnv::$db->select(PM::T_UPLOADS, array('fields'=> array('cnt'=>'count(1)'),
            'where'=>array('stmt_id'=>$plcid,'doctype'=>$doctype), 'singlerow'=>1));
            return $ret['cnt'];
        }
        $ret = [];
        $dta = AppEnv::$db->select(PM::T_UPLOADS, array('fields'=> "doctype, count(1) cnt",
            'where'=>array('stmt_id'=>$plcid),'groupby'=>'doctype') );
        if (is_array($dta)) foreach($dta as $row) {
            $ret[$row['doctype']] = $row['cnt'];
        }
        return $ret;
    }
    /**
    * Формирует значения 'display' в массиве self::$all_buttons в соотв-вии с текущим статусом полиса и правами юзера
    *
    * @param mixed $plcdata - ID полиса либо ассоц.массив с данными
    */
    public function buttonsVisibility($plcdata, $docaccess=null) {
        # настройка видимости станд.кнопок выполняется в app/businessproc.php
        BusinessProc::viewButtonAttribs($this, $plcdata, $docaccess);
    }

    # добавляем к станд.кнопкам свою
    public function addUserButton($id, $label, $jscode, $title='') {
        $labelStr = AppEnv::getLocalized($label, $label);
        $bTitle = '';
        if($title) {
            $titleStr = AppEnv::getLocalized($title, $title);
            $bTitle = ($titleStr ? "title=\"$titleStr\"" : '');
        }
        $html = "<input type=\"button\" id=\"btn_{$id}\" value=\"$labelStr\" class=\"btn btn-primary\" onclick=\"$jscode\" $bTitle/>";
        $this->all_buttons[$id] = [ 'html' => $html,'display'=>1];
    }

    public function enableBtn($idlist, $value=TRUE, $debug = 0) {
        $btlist = is_string($idlist) ? explode(',',$idlist) : $idlist;
        /* ## для разборок почему кнопка не видна ##
        if(in_array('editagr', $btlist)) {
            writeDebugInfo($idlist, " state: [$value]");
            writeDebugInfo("trace ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        }
        */
        foreach($btlist as $btid) {
            if (isset($this->all_buttons[$btid])) {
                $this->all_buttons[$btid]['display'] = $value;
                if ($debug) writeDebugInfo("btn $btid set to [$value] ", ($debug>1 ? debug_backtrace(0, 3): ''));
            }
            elseif(empty($value)) { # почему-то HTML кнопки не подключен, но ее надо спрятать (доработка...)
                $this->all_buttons[$btid] = ['display' => 0];
            }
        }
    }

    /**
    * Ищет полис, являющийся пролонгацией заданного
    * Если находит, возвращает ассоц.массив "основных" данных (из alf_agreements), иначе FALSE
    * @param mixed $id
    * @return FALSE or assoc.array
    */
    public function getProlongedAgreement($id) {
        $prolonged = AppEnv::$db->select(PM::T_POLICIES, array(
          'where'=>array('previous_id'=>$id),
          'singlerow'=>1
        )); # если уже есть пролонгация, кнопку "пролонгации" не выводить!
        return (is_array($prolonged)? $prolonged : FALSE);
    }
    # По АРЖ-коду программы возвращаю кодировку : TermFix => 'BCL' ...
    public function getProgramCode($program, $params=0) {
        # WriteDebugInfo("getProgramCode($program) ..."); WriteDebugInfo('self::$_programs:', self::$_programs);
        $ret =(isset(self::$_programs[$program]) ? self::$_programs[$program][0] : FALSE);
        if (!$ret && isset($this->my_programs[$program])) {
            $ret = $this->my_programs[$program][0];
        }
        if($ret === FALSE && empty($params['stmt_id'])) {
            $err = 'Не удалось получить кодировку для продукта '.$program;
            # writeDebugInfo("кодировку не нашел / $program: ", self::$_programs);
            if (AppEnv::isApiCall()) return array( 'result' => 'ERROR','message' => $err);
            exit($err);
        }
        return $ret;
    }
    # Пополняет список программ
    public static function addPrograms($prgArr, $module='') {
        if ($module) self::$_programs[$module] = $prgArr;
        else self::$_programs = array_merge(self::$_programs, $prgArr);
    }
    # если номсер полиса - черновой (типа ZZZ-DRAFT0000001) вернёт его кодировку, иначе FALSE
    public function isDraftPolicyno($policyno = FALSE) {
        if(!$policyno) $policyno = $this->_rawAgmtData['policyno'] ?? '';
        if(empty($policyno)) return FALSE;
        @list($codirovka, $nomer) = explode('-', $policyno);
        return ((substr($nomer,0,5)==='DRAFT') ? $codirovka : FALSE);
    }
    # По АРЖ-коду программы возвращаю наименование: TermFix => 'Путевка в жизнь Плюс' ...
    public function getProgramName($program) {
        $ret = '';
        $module = '';
        if (!empty(AppEnv::$_plugins[$this->module])) {
            $module = $this->module;
            if (method_exists(AppEnv::$_plugins[$this->module],'getProgramName')) {
                $ret = AppEnv::$_plugins[$this->module]->getProgramName($program);
            }
        }
        if (!$ret) {
            if ($module) $ret = (isset(self::$_programs[$module][$program]) ? self::$_programs[$module][$program][1] : $program);
            else $ret = (isset(self::$_programs[$program]) ? self::$_programs[$program][1] : $program);
        }
        return $ret;
    }

    # получаю название программы по кодировке/коду подпрограммы

    public static function decodeProgramName($prodcode, $module = '') {
        # WriteDebugInfo("decodeProgramName($prodcode) start");
        # WriteDebugInfo("all prod_codes: ", self::$_programs);
        global $ast_datarow;
        if (!$module && isset($ast_datarow['module'])) $module = $ast_datarow['module'];
        elseif(!empty(AppEnv::$_p['plg'])) $module = AppEnv::$_p['plg'];
        if (!empty($module)) {
            if(method_exists(AppEnv::$_plugins[$module], 'getProgramName'))
            return AppEnv::$_plugins[$module]->getProgramName($prodcode, $ast_datarow);
            if (isset(self::$_programs[$module][$prodcode][1])) return self::$_programs[$module][$prodcode][1];
            return $prodcode;
        }
        if (isset(self::$_programs[$prodcode])) return self::$_programs[$prodcode][1];

        foreach (self::$_programs as $name=>$item) {
            # WriteDebugInfo("item[$name] : ", $item);
            if ($name === $prodcode || $item[0] === $prodcode) {
                return $item[1];
            }
        }
        return "[$prodcode]";
    }

    # @since 1.7.20
    public static function getShortRiskName($riskid) {
        $ret = self::getRiskName($riskid,'exportname');
        if(empty($ret)) switch($riskid) {
            case 'death_acc_delay': $ret = 'СНС/ОВ'; break;
            case 'death_acc': $ret = 'СНС'; break;
            case 'death_any': $ret =  'СЛП'; break;
            default: $ret = $riskid;
        }
        return $ret;
    }

    /**
    * Добавляю HTML код для выбора выг-приобр. пос смерти Застр.ребенка
    *
    */
    public function childBenefFormHtml($insured_exist = FALSE, $pholderFL=TRUE) {
        return HtmlBlocks::childBenefFormHtml($this,$insured_exist, $pholderFL);
    }

    # Сохраняю набор рисков по полису (ст.суммы, премии)
    # Заглушка ! Переписать для каждого модуля - своя реализация
    public function savePolicyRisks($id, $params, $calculation) {
        writeDebugInfo("default (empty) savePolicyRisks!");
        return 0;
    }

    # загружаю данные по рискам полиса $id - переопределить в своем плагине!
    public function loadPolicyRisks($id, $mode=0) {
        # writeDebugInfo("loadPolicyRisks($id, $mode)");
        if($mode !== 'raw' && isset($this->_rawAgmtData['prodcode'])) {
            if (method_exists($this, 'getLisaModifiedCode'))
                $expSubcode = $this->getLisaModifiedCode($this->_rawAgmtData['prodcode']);
            else
                $expSubcode = $codirovka = $this->_rawAgmtData['prodcode']; # substr($this->_rawAgmtData['policyno'], 0,3);

            if (empty($expSubcode)) {
                list($codirovka) = explode('-', $this->_rawAgmtData['policyno']);
                $expSubcode = $codirovka;
            }
            # writeDebugInfo("codirovka: $codirovka");
            if ($this->redefineExpCode) {
                list($expSubcode) = explode('-', ($this->_rawAgmtData['policyno'] ?? ''));
            }
        }
        if ($id<=0) $id = $this->_rawAgmtData['stmt_id'] ?? 0;
        $dta = AppEnv::$db->select(PM::T_AGRISKS, array('where'=>array('stmt_id'=>$id),'orderby'=>'id'));

        if(!is_array($dta)) $dta = [];
        if (method_exists($this, 'addHiddenRisks')) # если есть "невидимые" риски, добавить для "отчетности"
            $this->addHiddenRisks($dta, $mode);
        $this->_rawPolicyRisks = $dta;
        if($mode === 'raw') return $dta;

        # if ($mode ==='export') file_put_contents('all_risks.log', print_r($dta,1));
        if (count($dta)<1) return array();

        $ret = array();

        if ($mode === 0 || $mode === 'view' || $mode === 'print') { # только id risk (ключ),
            foreach($dta as $rsk) {
                $rtype = $rsk['rtype']; # TODO: исправить для случая 2х типов - MANPACK / ADDITIONAL
                if (in_array($rtype, ['MANPACK','ADDITIONAL','RISK_DMS']))
                    $ret[] = [
                    $rsk['riskid'], $rsk['risksa'], $rsk['riskprem'],
                    'rtype' => $rtype,'riskid'=>$rsk['riskid'], 'risksa'=>$rsk['risksa'],
                    'riskprem'=>$rsk['riskprem'], 'datefrom' => $rsk['datefrom'],
                    'datetill' => $rsk['datetill'],
                ];
                else $ret[$rtype] = [
                    $rsk['riskid'], $rsk['risksa'], $rsk['riskprem'],
                    'riskid'=>$rsk['riskid'], 'risksa'=>$rsk['risksa'], 'riskprem'=>$rsk['riskprem'],
                    'datefrom' => $rsk['datefrom'],'datetill' => $rsk['datetill'],
                ];
            }
        }
        /*
        elseif ($mode == 'print') {
            foreach($dta as $rsk) {
                $rtype = $rsk['rtype'];
                $ret[$rtype] = $rsk;
            }
        }
        */
        elseif ($mode == 'export') {
            $ret = array();
            # WriteDebugInfo("mode: $mode"); # , risks in policy:", $dta);
            foreach($dta as &$rsk) {
                # WriteDebugInfo("one risk to add:", $rsk);
                $dopdta = AppEnv::$db->select(PM::T_RISKS, array(
                    'where'=>array('riskename'=>$rsk['riskid']),
                    'singlerow'=>1
                ));

                $rtype = $rsk['rtype'];
                $rskid = $rsk['riskid']; # 'death_acc_addcover' ... - risk english id !
                $rexpname = $rsk['exportname'] = (isset($dopdta['exportname']) ? $dopdta['exportname']:'');
                $rshortname = $rsk['shortname'] = (isset($dopdta['shortname']) ? $dopdta['shortname']:'');
                # WriteDebugInfo("Collecting risk for export for rtype=$rtype / riskid=$rskid, one rsk:", $rsk);
                # $rsk[risksa | riskprem] - стр.сумма и премия

                # может быть несколько "выходных" рисков LISA для одного в ФО, в этом случае всем проставляем одинаковую стр.сумму и премию
                # writeDebugInfo("export: $expSubcode, getting risk $rskid");
                $rmeta = $this->getExportRiskConfig($expSubcode, $rskid);
                # echo "getExportRiskConfig: rmeta for $rskid:<pre>".print_r($rmeta,1).'</pre>';

                # WriteDebugInfo('export risks/rmeta: ', $rmeta);
                if (!is_array($rmeta) || count($rmeta)<1) {
                    # WriteDebugInfo("OOPS: no export cfg for risk $codirovka, $rskid: ", $rmeta);
                    continue;
                }
                $persontype = (stripos($rtype, 'child')!==FALSE ? 'child' : '');

                foreach($rmeta as $no=>$rmt) {
                    if (empty($rmt['riskgroup']) || $rmt['riskgroup'] < 0) {
                        # writeDebugInfo("$rskid - риск НЕ ЖИЗНИ, пропускается!: ", $rmt);
                        continue; # риск НЕ ЖИЗНИ, в LISA не выгружается!
                    }
                    $srtype = $rtype . ( ($no>0)? "-$no":'' );
                    # if (!isset($ret[$srtype])) $ret[$srtype] = []; $ret[$srtype] = ...
                    $ret[] = $debarr = array(
                    'rtype' => $rtype,
                    'riskid'  => $rmt['riskpid'], # для экспорта - внешний ИД риска
                    'alforiskid' => $rsk['riskid'],
                    # 'riskename' => $rskid,
                    'riskgroupid' => $rmt['riskgroup'],
                    'forindividual' => $rmt['forindividual'],
                    'otherperson' => $rmt['otherperson'], # на кого вешать риск если forindividual=3 (other)
                    'payamount' => $rsk['riskprem'],
                    'riskamount' => $rsk['risksa'],
                    'persontype' => $persontype,
                    'exportname' => $rmt['exportname'],
                    'shortname' => $rmt['shortname'],
                    'longname' => $rmt['longname'],
                    'datefrom' => $rsk['datefrom'],
                    'datetill' => $rsk['datetill'],
                    );
                    # WriteDebugInfo("adding risk for export:", $debarr);
                }

            }
            $this->_rawPolicyRisks = $dta; // добавил exportname, shortname
        }
         # elseif($mode=='...') - other $mode values here...
        # WriteDebugInfo("ready risks for $id:", $ret);
        # exit('data <pre>' . print_r($ret,1). '</pre>');
        return $ret;

    }

    # {upd/2020-10-07} передаю из присланных данных значения "спец-полей"
    public function addUserSpecParams(&$spec) {
        # writeDebugInfo("addUserSpecParams, spec_fields: ", $this->spec_fields);
        foreach($this->spec_fields as $fldid) {
            $fldVal = AppEnv::$_p[$fldid] ?? 'NONE';
            if (isset(AppEnv::$_p[$fldid])) {
                $spec[$fldid] = Sanitizer::safeString(AppEnv::$_p[$fldid]);
            }
        }
        if(in_array('sport', $this->spec_fields)) { # собираю ВСЕ sport_xxxxxx
            foreach(AppEnv::$_p as $key=>$val) {
                if(substr($key,0,6)==='sport_')
                    $spec[$key] = $val;
            }
        }
    }

    # Сохраняю данные из полей калькулятора, осн.расчет, фин-план как сериализованные строки
    public function saveSpecData($id, $specific, $calc=0,$srv=0, $plan=0, $mode='json') {
        if ($id<=0) return;
        $dt = array();
        # writeDebugInfo("saveSpecData, specific:", $specific);
        $specValues = is_array($specific) ? $specific : [];
        # {upd/2024-08-20} - добавляю поля, чьи ИД есть в списке $this->spec_fields
        if(count($this->spec_fields)) foreach($this->spec_fields as $key) {
            if(!isset($specValues[$key]) && isset(AppEnv::$_p[$key]))
                $specValues[$key] = AppEnv::$_p[$key];
        }
        # writeDebugInfo("saveSpecData, specValues: " , $specValues);
        if (is_array($specValues) && count($specValues)) {
            Sanitizer::sanitizeArray($specValues);
            $dt['spec_params'] = WebApp::serializeData($specValues, $mode,TRUE);
        }
        if(is_array($calc) && count($calc)>0) {
            # чтобы снова не попали в клиента при редактировании/выгрузке:
            unset($calc['action'], $calc['calcid'], $calc['plg'], $calc['calcDate'], $calc['stmt_id']);
            $dt['calc_params'] = WebApp::serializeData($calc,$mode,TRUE);
            # {upd/2023-03-12} сохраняю в json (легче и больше данных)
        }
        if(is_array($srv)  && count($srv)>0 )  {
            Sanitizer::sanitizeArray($srv);
            $dt['ins_params']  = WebApp::serializeData($srv,$mode,TRUE);
        }
        if(is_array($plan) && count($plan)>0) {
            Sanitizer::sanitizeArray($plan);
            if ($this->finplan_fmt === 'json') # сложный фин-план, соханяю в json строке
                $dt['fin_plan'] = WebApp::serializeData($plan,'json',TRUE);
            else
                $dt['fin_plan'] = WebApp::serializeData($plan);
        }

        if (self::$debug>2) {
            WriteDebugInfo('saveSpecData, calc_params:', $calc);
            WriteDebugInfo('specific:', $specific);
            WriteDebugInfo('srv_params:', $srv);
            WriteDebugInfo('finplan:', $plan);
        }
        $ret = 1;
        if (!$this->_debugAdd) {
            $f = AppEnv::$db->select(PM::T_SPECDATA, array('fields'=>'stmt_id','where'=>array('stmt_id'=>$id)));
            if(!empty($f)) {
                $ret = AppEnv::$db->update(PM::T_SPECDATA,$dt, array('stmt_id'=>$id));
            }
            else {
                $dt['stmt_id'] = $id;
                $ret = AppEnv::$db->insert(PM::T_SPECDATA,$dt);
            }
            $err = AppEnv::$db->sql_error();
        }
        else $err = '';

        if ($err) {
            WriteDebugInfo("ERROR saving specdata:", $err);
            WriteDebugInfo("ERROR saving SQL:", AppEnv::$db->getLastQuery());
            die ('Ошибка при сохранении данных полиса (specdata)');
        }
        return $ret;
    }

    public function setPluginId($strg) { self::$plgid = trim($strg); }

    /**
    * Рисую "стандартную" страницу просмотра договора
    *
    * @param mixed $policyid
    * @param mixed $data
    * @param mixed $buttons
    * @param mixed $showhist
    */
    public function viewPolicy($policyid, $data=array(), $buttons ='', $showhist=FALSE) {

        include_once('as_propsheet.php');
        $tech = FALSE; # технич.просмотр - риски с их ИД!
        if ($this->loss_enabled) useJsModules('js/lossmanager.js');
        $today = date('Y-m-d');
        # WriteDebugInfo('viewPolicy/data:', $data);
        # WriteDebugInfo('viewPolicy/_rawAgmtdata:', $this->_rawAgmtData);
        if (!$policyid && isset($data['stmt_id'])) $policyid = $data['stmt_id'];
        if (defined('IF_LIMIT_WIDTH') && IF_LIMIT_WIDTH>0) $this->viewWidth = IF_LIMIT_WIDTH;

        elseif (WebApp::$IFACE_WIDTH > 0) $this->viewWidth = min($this->viewWidth, WebApp::$IFACE_WIDTH);

        $parsedRef = parse_url($_SERVER['HTTP_REFERER'] ?? ''); # https://localhost/alfo/?p=allagr при входе с глобального грида allagr
        $prms = [];
        parse_str(($parsedRef['query'] ?? ''), $prms);

        if(($prms['p'] ?? '')=== 'allagr') # пришли из грида ВСЕХ полисов, возвратная ссылка на него же
            $top = @file_get_contents(AppEnv::getAppFolder(AppEnv::FOLDER_TEMPLATES) . 'policymodel.top.all.htm');
        else
            $top = @file_get_contents(AppEnv::getAppFolder(AppEnv::FOLDER_TEMPLATES) . 'policymodel.top.htm');

        AppEnv::localizeStrings($top);
        $bottom = @file_get_contents(AppEnv::getAppFolder(AppEnv::FOLDER_TEMPLATES) . 'policymodel.bottom.htm');

        $modulePath = AppEnv::getAppFolder('plugins/'.$this->module.'/templates/') . 'policymodel.view.htm';
        $html = is_file($modulePath) ? @file_get_contents($modulePath) :
            @file_get_contents(AppEnv::getAppFolder(AppEnv::FOLDER_TEMPLATES) . 'policymodel.view.htm');

        $this->loadSpecData($policyid);
        $this->getAgmtValues(__FUNCTION__);
        # writeDebugInfo("agmtdata ", $this->agmtdata);
        $dtRelease = $this->agmtdata['date_release'] ?? '';
        $releaseDate = PlcUtils::isDateValue($dtRelease) ? to_char($dtRelease) : '';
        $msubst = array(
           '{iface_width}'   => $this->viewWidth-100
           ,'{iface_width2}' => $this->viewWidth-130
           ,'{module}'       => $this->module
           ,'{prolong_info}'   => $this->getHtmlProlongInfo($data)
           ,'{policy_comment}'  => ''
           ,'{btn_comment}'  => ''
           ,'<!-- signer_block -->' => ''
           ,'<!-- special_info -->' => ''
           ,'<!-- state_details -->' => ''
           ,'<!-- date_sign -->' => to_char($this->getDateSign($data))
           ,'%show_state_details%' => 'style="display:none"'
           ,'{date_release}' => $releaseDate
        );
        if ($this->isICOfficer() && $this->select_signer) {
            if (!in_array($data['stateid'], [PM::STATE_ANNUL, PM::STATE_BLOCKED, PM::STATE_CANCELED,PM::STATE_DISSOLUTED]))
                $signer_block = Signers::selectSignerHtml($policyid, $this->module);
            else
                $signer_block = Signers::viewSignerHtml($policyid);
            $msubst['<!-- signer_block -->'] = '<tr><td>Подписант от СК</td><td>' . $signer_block . '</td></tr>';
        }
        if ($data['stateid'] == 11 && ($data['docflowstate'] >0 || $data['accepted']>0))
             $cmtbut = '';
        else $cmtbut = PlcUtils::getCommentButton($this->module, $policyid);

        # в бэкенде можно добавить во вьюху строки о "платежах":
        if(method_exists($this, 'viewagrAddInfo'))
            $this->viewagrAddInfo($msubst);

        $msubst['{policy_comment}'] = PlcUtils::viewPolicyComment($this->module, $policyid);
        $msubst['{btn_comment}'] = $cmtbut;
        # WriteDebugInfo('date from ',$data['datefrom']);
        $fromymd = to_date($data['datefrom']);
        if ( $this->loss_enabled && $fromymd < date('Y-m-d') && in_array($data['stateid'],[9,10,11,50]) ) {
            $curLoss = LossManager::getLossTotal($this->module, $policyid, $data['currency']);
            $lossStr = ( $curLoss > 0) ? (fmtMoney($curLoss) . ' '.$data['currency']) : '--';

            $lossEdit = "<span class='rt'  style='float:right' id='plcview_losses'>"
              . "<input type=\"button\" class=\"btn btn-primary\" title='Подробные сведения об убытках' onclick='lossManager.openEditor($policyid)' value='Подробно'></span>";

            $msubst['<!-- loss_info -->'] = "<tr><td>Убытки по полису</td><td><span id='viewloss_total'>$lossStr</span>$lossEdit</tr>";
        }
        # if (method_exists($this, 'getStateDetails')) {
            $msubst['<!-- state_details -->'] = $this->getStateDetails();
        # }
        if (!empty($this->_rawAgmtData['recalcby'])) {
            $msgRecalc = $this->messageAboutRecalc();
            $msubst['<!-- state_details -->'] .= ' <span class="imbold">'.$msgRecalc.'</span>';
        }
        $subStt = '';
        if(PlcUtils::isPolicyExpired($this->agmtdata)) {
            $subStt = ''; # '<div class="attention bordered">' . $expiredTxt . '</div>';
            # if(!intval($this->agmtdata['date_release']) && intval($this->agmtdata['date_release_max']) && $this->agmtdata['date_release_max']<$today)
            /*
            $expiredTxt = AppEnv::getLocalized('warn_release_expired');
            if($this->recalculable)
                $expiredTxt .= ' (кнопка "Пересчитать")';
            else
                $expiredTxt .= ' (кнопка "Обновить данные")';

            $subStt = '<div class="attention bordered">' . $expiredTxt . '</div>';
            */
        }
        elseif (in_array($this->_rawAgmtData['stateid'], [PM::STATE_PROJECT,PM::STATE_DRAFT, PM::STATE_PAUSED, PM::STATE_DOP_CHECKING, PM::STATE_UWAGREED])
          && in_array($this->_rawAgmtData['bpstateid'],
            [PM::BPSTATE_SENTEDO, PM::BPSTATE_EDO_OK, PM::BPSTATE_EDO_NO, PM::BPSTATE_SENTPDN, PM::BPSTATE_PDN_OK, PM::BPSTATE_PDN_NO])) {
            $subStt = UniPep::getConfirmRequestHtml($this->module, $data['stmt_id'], $this->_rawAgmtData);

        }

        # writeDebugInfo("agmtdata: ", $this->agmtdata);
        if($subStt) $msubst['<!-- state_details -->'] .= ' '.$subStt;

        if ($msubst['<!-- state_details -->']) $msubst['%show_state_details%'] = ''; # не прятать строку!
        # writeDebugInfo("_deptCfg ", $this->_deptCfg);
        $specCnd = '';
        if ($this->specCond) {
            $specCnd = mb_substr($this->specCond,0,64) . '...';
            $specCnd = nl2br(htmlentities($specCnd,(ENT_QUOTES | ENT_SUBSTITUTE), MAINCHARSET));
            $spcStyle = '';
        }
        else $spcStyle = ' style="display:none"';
        $msubst['<!-- special_info -->'] .= "<tr id=\"specinfo\" $spcStyle><td><abbr title='Будут выводиться в полис'>Особые условия<abbr></td>"
          . '<td id="specinfodata">' . $specCnd . '</td></tr>';
        # WriteDebugInfo('btn_comment', $msubst['{btn_comment}'] );
        # writeDebugInfo("agmtdata ", $this->agmtdata);
        # {upd/2022-10-28} <!-- date_release_max --> - для макс.даты начала д-вия - datefrom_max
        # writeDebugInfo("agmtdata ", $this->agmtdata);

        if(!empty($this->agmtdata['date_release_max']) && PlcUtils::isDateValue($this->agmtdata['date_release_max'])) {
            # writeDebugInfo("Kt-00222, data ", $this->agmtdata);
            if( $this->agmtdata['stateid'] < PM::STATE_ANNUL
               && intval($this->agmtdata['bpstateid'])==0 && !PlcUtils::isDateValue($this->agmtdata['date_release']) ) {
                $msubst['<!-- date_sign -->'] .= ', Макс.дата выпуска полиса: <b>'.to_char($this->agmtdata['date_release_max']) . '</b>';
            }
            # else writeDebugInfo("Kt-00223 - not max release");
        }

        $html = strtr($html, $msubst);

        # $html .= "<input type='button' id='btn_fireupload' kkstyle='display:none' value='upl'>";
        # кнопка, к которй привяжется simpleupload если надо грузить с выбором типа

        # $listhref = "<a href=\"./?plg=" . self::$plgid . "&action=agrlist>".AppEnv::getLocalized('return_to_agrlist').'</a>';
        $top = strtr($top, $msubst);

        foreach($data as $k => $val) {
            if (is_scalar($val)) {
                if ($k == 'policy_prem') $val = fmtMoney($val);
                elseif(in_array($k, ['datefrom','datetill'])) $val = to_char($val);
                elseif($k === 'policyno' && !empty($data['b_test'])) $val .= ' / <span class="attention">ТЕСТОВЫЙ</span>';
                elseif($k === 'currency') {
                    $val = \PlcUtils::decodeCurrency($val,FALSE, TRUE);
                }
                $html = str_replace("{{$k}}",$val, $html);
            }
        }

        $jsSheet = CPropertySheet::commonJsBlock();
        HeaderHelper::addJsCode($jsSheet);

        $id = $data['stmt_id'];
        if($showhist) {
            # writeDebugInfo("data: ", $data);
            $repoLevel = $this->getUserLevel('reports');
            $histhref = '';
            # историю действий показываю Админу и сотр.СК или сотр. с ролью отчетов сотр.СК+
            if ($this->isICAdmin() || PlcUtils::iAmCompliance() ||  $repoLevel>=PM::LEVEL_IC_ADMIN) {
                $histhref = "<a href='javascript:void(0)' onclick=\"plcUtils.viewAgmtHistory('$showhist','$id')\" >" .
                AppEnv::getLocalized('view_action_history') . '</a>';
            }
            $top = str_replace("<!-- href_agrhistory -->",$histhref, $top);
        }
        $top = str_replace("{title}",$data['title'], $top);

        $persons = AppEnv::$db->select(PM::T_INDIVIDUAL, ['fields'=>'count(1) cnt', 'where'=>['stmt_id'=>$id],
          'singlerow'=>1, 'associative'=>0]);

        if($this->agmtdata['stateid'] == PM::STATE_DRAFT && $persons==0) {
            $persHtml = (method_exists($this, 'viewDraftInfo') ? $this->viewDraftInfo() : "Здесь доп-данные предв.расчета");
        }
        else {
            # Закладки (tabs) для отображения страхователя, [застрахованного если отличен, [ребенка если есть]]
            $persheet = new CPropertySheet(array('width'=>$this->viewWidth-90,'height'=>'107','tabsPosition'=>CPropertySheet::TABS_LEFT));
            # WriteDebugInfo('all data:', $this->agmtdata);
            $offset = 0;
            $childCfg = explode(',', $this->insured_child);
            # writeDebugInfo("nonlife[$this->nonlife] ", $this->agmtdata);
            if (($this->insurer_enabled) && (!empty($this->agmtdata['equalinsured'])
               || !empty($this->_rawAgmtData['equalinsured']) || $this->nonlife) ) {
                $tabTitle = ($this->nonlife || !$this->insured_adult) ? 'Страхователь' : 'Страхователь/Застрахованный';
                $persheet->addPage($tabTitle, $this->_viewPerson('insr'));
            }
            else {
                if ($this->insurer_enabled) {
                    $persheet->addPage('Страхователь', $this->_viewPerson('insr'));
                }
                if ($this->insured_adult && isset($this->agmtdata['insd']) && $this->multi_insured !='child') {
                    $persheet->addPage('Застрахованный', $this->_viewPerson('insd', $offset++));
                    # writeDebugInfo("stmt: insured_adult=[$this->insured_adult], multi_insured=[$this->multi_insured], added Inasured Block");
                }
            }
            if ($this->multi_insured==2 && !empty( $this->agmtdata['b_insured2'])) {

                $persheet->addPage('Застрахованный № 2', $this->_viewPerson('insd',$offset++));
            }
            elseif($this->multi_insured>=100 && method_exists($this, 'viewInsuredList')) {
                $persheet->addPage('Список Застрахованных', $this->viewInsuredList($offset++));
            }

            if($childCfg[0] === 'option') { # список застрахованных детей
                if ($this->policyHasChild())
                    $persheet->addPage('Застрахованные дети', $this->viewInsuredArray('child',$offset++));
            }
            elseif ( isset($this->agmtdata['child'])) {
                $persheet->addPage('Застрахованный ребенок', $this->_viewPerson('child',$offset++));
            }

            if(!empty($this->_rawcBenefs['id'])) $persheet->addPage('Представитель Застрах.Ребенка', HtmlBlocks::viewOneBenefData($this->_rawcBenefs));

            if (!empty($this->agmtdata['benef']) ) {
                $persheet->addPage('Выгодоприобретатели', $this->_viewBenefs('benef'));
            }
            $persHtml = $persheet->draw(0,true);

        }

        $html = str_replace('<!-- policy-persons -->', $persHtml, $html);
        $bottom = str_replace("<!-- block_buttons -->",$buttons, $bottom);

        $riskhtml = '';
        $mainHtml = $addHtml = '';
        if (method_exists($this, 'viewAgmtBlockRisks')) {
            # У плагина свой метод визуализации рисков
            $riskhtml .= $this->viewAgmtBlockRisks($policyid);
        }
        else {
            $titleRskMain = self::$subheaders['mainrisks'];
            $titleRskPrem = ($this->view_showPremium) ? '<th nowrap>Премия (взнос)</th>' : '';
            $riskhtml .= "<tr> <th>$titleRskMain</th><th nowrap>Страховая сумма</th>$titleRskPrem</tr>";

            if(count($this->mainRisks)>0 || $this->view_risks_mode === 'BOX') {
                # отображение по фактическому списку "основных" и доп. рисков
                # стандартный заголовок для рисков
                $this->loadPolicyRisks($policyid,'edit');
                # writeDebugInfo("_rawPolicyRisks: ", $this->_rawPolicyRisks);

                $mainRs = $addRs = [];

                foreach ($this->_rawPolicyRisks as $no => $risk) {
                    # WriteDebugInfo("$no: one FROM _rawPolicyRisks: ", $risk);
                    if (in_array($risk['rtype'],$this->mainRisks) || in_array($risk['riskid'],$this->mainRisks)
                      || $this->view_risks_mode >= 2) {
                          # при включенном view_risks_mode =2+ ВСЕ риски сую в одну "главную" группу
                        $mainRs[ $risk['riskid'] ] = array(
                          'risktitle'=>$this->getRiskName($risk['riskid'], 'shortname')
                          ,'risksa' => $risk['risksa']
                          ,'riskprem' => $risk['riskprem']
                        );
                    }
                    elseif(!in_array($risk['riskid'],$this->nl_risks)) {
                        $addRs[ $risk['riskid'] ] = [
                          'risktitle'=>$this->getRiskName($risk['riskid'], 'shortname'),
                          'risksa' => $risk['risksa'],
                          'riskprem' => $risk['riskprem'],
                          'count' => (method_exists($this, 'riskSubjectsCount') ? $this->riskSubjectsCount($risk['riskid']) : 1),
                        ];
                        if (floatval($risk['riskprem']) == 0) { # особый риск без премии
                            if(method_exists($this, 'getRiskComment'))
                            $addRs[$risk['riskid']]['comment'] = $this->getRiskComment($risk['riskid'],0);
                        }
                    }
                }
                # writeDebugInfo("mainRs ", $mainRs); writeDebugInfo("addRs ", $addRs);
                $rowspan = count($mainRs);
                $ii = 0;
                foreach($mainRs as $riskid => $rdata) {
                    # writeDebugInfo("view risk $riskid");
                    $tdprem = '';
                    if ($ii == 0) { # вывожу одну общую премию на все риски гл.группы
                        switch($this->view_risks_mode) {
                            case 1: // позже при необходимости можно расширить способы показа премий
                                # WriteDebugInfo($riskid, ' Вывожу общий взнос как премию по осн.рискам ',$this->_rawAgmtData['policy_prem']);
                                $rprem = fmtMoney($this->_rawAgmtData['policy_prem']);
                                break;
                            case 0: case 'BOX':
                                # WriteDebugInfo($riskid,' Вывожу станд. премию по риску ', $rdata['riskprem']);
                                $rprem = ($rdata['riskprem']>0) ? fmtMoney($rdata['riskprem']) : '';
                                break;
                        }
                        if ($this->view_showPremium)
                            $tdprem = "<td rowspan='$rowspan' class='viewmoney' id=\"view_{$riskid}\" >" . $rprem .'</td>';
                    }
                    else $rprem = '';

                    $saText = FALSE;
                    # {upd/2020-02.03} - у СС может быть особый текст, выводимый вместо СС, типа "возврат уплаченных взносов"
                    if (method_exists($this, 'getRiskSaText') ) {
                        $saText = $this->getRiskSaText($riskid);
                    }
                    if ($saText === FALSE) {

                        $saText = ($rdata['risksa']>0) ? fmtMoney($rdata['risksa']) : '--';
                        # $saText = method_exists($this, 'getRiskSaText') ? $this->getRiskSaText($riskid) : '--';
                    }
                    if ($riskid === 'disability_12_wop' || $riskid === 'wop')
                        $saText = 'Согласно Договору';
                    $rtitle = $rdata['risktitle'] . ($tech ? "/$riskid" : '');
                    $mainHtml .= "<tr><td>$rtitle</td><td class='viewmoney' id=\"view_{$riskid}\" >$saText</td>$tdprem</tr>";
                    $ii++;
                }
                # exit(__FILE__ .':'.__LINE__.' addRs:<pre>' . print_r($addRs,1) . '</pre>');
                foreach($addRs as $riskid => $rdata) {
                    $radd = ($tech ? "/$riskid" : '');
                    if (!empty($rdata['comment'])) { # особый риск без премии
                        $addHtml .= "<tr><td>$rdata[risktitle]{$radd}</td><td colspan='2'>$rdata[comment]</td></tr>";
                    }
                    else {
                        $mulriplier = (!empty($rdata['count']) && $rdata['count']>1) ? "$rdata[count] x " : '';
                        $addHtml .= "<tr><td>$rdata[risktitle]{$radd}</td><td class='viewmoney doprisk' id=\"view_{$riskid}\" >" . fmtMoney($rdata['risksa'])
                          . '</td>'
                           .($this->view_showPremium ? '<td class="viewmoney">' . $mulriplier . fmtMoney($rdata['riskprem']) . '</td></tr>' : '');
                    }
                }
                $riskhtml .= $mainHtml;
                    # rsk items: risksa, riskprem,currency, datefrom,datetill
            }
            elseif (is_array($this->baseRisks) && count($this->baseRisks)) {
                # есть заполненный справочник ИД базовых рисков, строю HTML по ним
                $plcrisks = $this->loadPolicyRisks($policyid,'view');
                # WriteDebugInfo('policy risks:', $plcrisks);
                if (is_array($plcrisks)) foreach($plcrisks as $pid=> $rsk) {
                    $riskid = isset($rsk[0]) ? $rsk[0] : $rsk['riskid'];
                    $rskPrem = ($this->view_showPremium) ? '<td class="viewmoney">' . (empty($rsk['riskprem'])?'':moneyFormat($rsk['riskprem'])).'</td>'
                       : '';
                    $radd = ($tech ? "/$riskid" : '');
                    # {upd/2024-09-13} если специфичесчкий риск, тип СЛП с выплтой ренты
                    $riskSaText = '';
                    if(method_exists($this, 'viewRiskSa'))
                        $riskSaText = $this->viewRiskSa($$rsk);
                    if(empty($riskSaText)) $riskSaText = (empty($rsk['risksa']) || $rsk['risksa']==0) ? '' : \RusUtils::moneyView($rsk['risksa']);

                    $riskhtml .= '<tr><td>' . $this->getRiskName($riskid) . $radd . '</td>'
                        . '<td class="viewmoney">' . $riskSaText . '</td>'
                        . $rskPrem .'</tr>';
                }
            }

            # Блок рисков "Не-Жизни" (non life) - типа ДМС
            if(count($this->nl_risks)) {

                $premBlock = '';
                $rskNames = [];
                $saBlock = '';
                $riskhtml .= '<tr><th>' . self::$subheaders['nl_risks'] . '</th><th></th>' .
                    ($this->view_showPremium ? '<th></th>' : '');

                foreach( $this->nl_risks as $nlriskid) {
                    $riskSa = '';
                    if (method_exists($this, 'getNonLifeRiskSa')) {
                        $nldata = $this->getNonLifeRiskSa($nlriskid);
                    }
                    else {
                        $nldata = ['',0];
                        foreach($this->_rawPolicyRisks as $id=>$rsk) {
                            if ($rsk['riskid'] === $nlriskid) {
                                $nldata = [ $rsk['risksa'],$rsk['currency'],$rsk['riskprem'] ];
                                break;
                            }
                        }
                    }
                    $rsktitle = $this->getRiskName($nlriskid);
                    if ($nldata[0] === '*') {
                        $rskSa = 'Согласно условиям';
                        $rskPrem = '';
                    }
                    elseif (count($nldata)>=2) {
                        $rskSa = moneyFormat($nldata[0]);
                        $rskPrem = ( $nldata[2]>0) ? moneyFormat($nldata[2]) : '--';
                        if ($this->_rawAgmtData['currency'] !=$nldata[1])
                            $riskSa .=  ' ' . $nldata[1]; // XXX.XX RUR
                    }
                    if ($this->nl_risks_join) {
                        if ($saBlock =='') $saBlock = $rskSa;
                        if ($premBlock =='') $premBlock = $rskPrem;
                        $rskNames[] = $rsktitle;
                    }
                    else {
                        $riskhtml .= "<tr><td>$rsktitle</td>"
                            . "<td class=\"viewmoney\">$rskSa</td>"
                            . ($this->view_showPremium ? "<td class=\"viewmoney\">$rskPrem</td>" : '')
                            . '</tr>';

                    }
                }
                if ($this->nl_risks_join) {
                    $riskhtml .= "<tr><td>" . implode('<br>',$rskNames) . '</td>'
                      . "<td class=\"viewmoney\">$saBlock</td>"
                      . ($this->view_showPremium ? "<td class=\"viewmoney\">$premBlock</td>" : '')
                      . '</tr>';
                }
            }
        }

        if ($addHtml) {
            if(!empty(self::$subheaders['addrisks']))
                $riskhtml .= '<tr><th>' . self::$subheaders['addrisks'] . '</th><th>&nbsp;</th>';

            $riskhtml .= ($rskPrem = ($this->view_showPremium) ?'<th>&nbsp;</th>':'') . '</tr>'. $addHtml;
        }
        unset($addHtml);
        $html = str_replace("<!-- agreement_risks -->",$riskhtml, $html);
        unset($riskhtml);
        if (method_exists($this, 'viewPolicyFooter')) {
            # модуль может иметь код для вывода доп.HTML кода в просмотре полиса, например - показ ссылок на загрузку файлов правил
            $vfooter = $this->viewPolicyFooter();
            if (is_string($vfooter) && !empty($vfooter))
                $html .= $vfooter;
        }

        $superadmin = AppEnv::$auth->isSuperAdmin();

        appEnv::appendHtml($top);
        $isAdmin = $this->isAdmin();
        $specAdmin = ($this->_userLevel >= PM::LEVEL_IC_SPECADM);

        if ( $specAdmin ) {

            $html_uwr = ($this->enable_underwriting || $superadmin ) ?
                 "<li><a href=\"javascript://void(0)\" onclick=\"policyModel.setState('project');\">Проект</a></li>"
               . "<li><a href=\"javascript://void(0)\" onclick=\"policyModel.setState('touw');\">Андеррайтинг</a></li>"
               . "<li><a href=\"javascript://void(0)\" onclick=\"policyModel.setState('uwagreed');\">согласован с анд.</a></li>"
               : '';

            $btn_menu = <<< EOHTM
<div id="statuscodes_data" class="div_outline pm_popmenu" style="display:none; position:absolute; left:200px; top:300px; z-index:80"><ul id="menu_statuslist" class="lt">
 <li><a href="javascript://void(0)" onclick="policyModel.setState('cancel');">Отмена</a></li>
 <li><a href="javascript://void(0)" onclick="policyModel.setState('annul');">Аннулирован</a></li>
$html_uwr
 <li><a href="javascript://void(0)" onclick="policyModel.setState('clearpayed');">Снять отметку оплаты</a></li>
 <li><a href="javascript://void(0)" onclick="policyModel.setState('formed');">Дог-Оформлен</a></li>
</ul>
</div>
EOHTM;
            AppEnv::appendHtml($btn_menu);
        }

        # для нового режима (раздельной) загрузки сканов: код меню с вариантами типов скана
        if ($this->uploadScanMode > 0) {
            $scanMnu = '<div id="scanmenu_data" class="div_outline pm_popmenu" style="display:none; position:absolute; left:200px; top:300px; z-index:80">'
               . '<ul id="menu_scantypes" class="lt">';
            $scTypes = !empty($this->scanTypes) ? $this->scanTypes : array_keys(PM::$scanTypes);
            foreach($scTypes as $sctype) { #  => $item
                $scanDesc = isset(PM::$scanTypes[$sctype]) ? PM::$scanTypes[$sctype] : $sctype;
                # TODO: раскрасить текст, если документ такого типа уже загружен к полису, блокировать недоступные типы ?
                if ( $sctype === 'stmt' ) continue;
                $scanMnu .= "\n<li><a><input type='button' class='buttonscan' id='bt_scan_$sctype' value=\"$scanDesc\" onclick=\"policyModel.startUpload('$sctype')\"/></a></li>";
            }
            $scanMnu .= "\n</ul></div>";
            AppEnv::appendHtml($scanMnu);
        }
        # Формирую блок просмотра с закладками (tabs)
        $sheet = new CPropertySheet(array('width'=>$this->viewWidth-70,'height'=>'300','tabsPosition'=>CPropertySheet::TABS_LEFT));
        $sheet->addPage('Данные о договоре', $html);
        if ($this->enable_agmtscans) {
            $satitle = AppEnv::getLocalized('title_scans');
            $sheet->addPage($satitle, '<table id="grid_agrscans"></table><div id="nav_agrscans"></div>');
        }

        # $htmlSheet = $sheet->Draw(0, TRUE);
        AppEnv::appendHtml($sheet->Draw(0, TRUE));

        AppEnv::appendHtml($bottom);
        AppEnv::finalize(); # drawPageBottom();
        if(AppEnv::isStandalone()) exit;
    }

    # гибкая дата "выпуска/оформления договора - если зафиксировали дату выпуска, беру ее.
    public function getDateSign($data) {
        if(!empty($data['date_release']) && intval($data['date_release']))
            $ret = $data['date_release'];
        elseif(!empty($data['date_recalc']) && intval($data['date_recalc']))
            $ret = $data['date_recalc'];
        else $ret = substr($data['created'], 0,10);
        return $ret;
    }
    # формирует блок view-данных о страхователе/застрахованном/застрахованном ребенке
    private function _viewPerson($type, $offset = FALSE) {
        $tpl = '';
        # writeDebugInfo("_viewPerson($type, [$offset])");
        # writeDebugInfo("this->agmtdata ", $this->agmtdata);
        $ftype = 1;
        # WriteDebugInfo("_viewPerson($type) :", $this->agmtdata[$type]);
        if ( ($type === 'child') || ($type === 'insd') || ($type === 'insr' && $this->_rawAgmtData['insurer_type']==1)) {
            if ($type === 'insd' && method_exists($this, 'viewAgmtBlockInsured')) { # есть собственный вывод застрахованных
                $ret = $this->viewAgmtBlockInsured($offset);
                return $ret;
            }
            $tplfile = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'view-fl.htm';
        }
        else {
            $tplfile = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'view-ul.htm';
            $ftype = 2;
            # writeDebugInfo("agmtdata ", $this->agmtdata);
        }
        $person = $this->agmtdata[$type];
        # WriteDebugInfo('person:', $person, " tpl: $tplfile exist:", is_file($tplfile));

        if ($offset !== FALSE && isset($person[$offset])) $person = $person[$offset];
        if ($ftype == 1 && !isset($person['fam'])) return ''; # мульти-child или другие ситуации - свой вывод данных!
        $body = @file_get_contents($tplfile);
        if ($this->simpleAddr) {
            $address = ($person['sameaddr']) ? $person['adr_full'] : $person['fadr_full'];
        }
        else {
            $address = ($person['sameaddr']) ? $this->buildFullAddress($person,'') :
                                           $this->buildFullAddress($person,'','f');
        }
        $docdata = ''; # self::decodeDocType($person['doctype']) .' '. $person['docser'].' № '. $person['docno']

        if ($this->isRF($person['rez_country'])) {
                $docdata = PlcUtils::buildFullDocument($person, '',1);
        }
        elseif (!empty($person['inopass'])) {
            $docdata = 'Иностр.паспорт '.$person['inopass'];
            if (!empty($person['migcard_no'])) {
                $docdata.= ", мигр.карта $person[migcard_ser] $person[migcard_no]";
                if (intval($person['docfrom'])) $docdata .= " c ".to_char($person['docfrom']);
                if (intval($person['doctill'])) $docdata .= " по ".to_char($person['doctill']);
            }
        }
        else {
            $docdata = 'Паспорт';
        }

        $subst = array(
           '{fullname}' => ''
          ,'{birth}' => ''
          ,'{fulladdress}' => $address
          ,'{document-data}' => $docdata
          ,'{contacts}' => ''
          ,'{view_contacts}' => (($type==='insr' || ($type==='insd' && $this->insured_phones)) ? '' : self::HTM_HIDE)
        );
        $arCont = [];
        if ( !empty($person['phone'])) $arCont[] = 'тел. ' . PlcUtils::buildAllPhones('', $person, TRUE);

        if ( !empty($person['email']) ) {
            $emailCont = 'email: '. $person['email'];
            if ($this->email_checker) # добавить кнопку быстрой проверки эл.почты
                 $emailCont .= " <a href=\"javascript:void()\" onclick=\"policyModel.emailCheck()\">%btn_check_email%</a>";

            $arCont[] = $emailCont;
        }
        if(count($arCont)) $subst['{contacts}'] = implode(', ', $arCont);

        if ($ftype == 1) {
            # WriteDebugInfo("ФЛ/$type: ", $person);
            $subst['{fullname}'] = $person['fam']. ' ' . $person['imia'] . ' '.$person['otch'];
            $subst['{birth}'] = isset($person['birth'])? $person['birth']: '';
            if(!empty($person['snils'])) $subst['{birth}'] .= " СНИЛС: ".$person['snils'];
            if(!empty($person['inn'])) $subst['{birth}'] .= " ИНН: ".$person['inn'];
        }
        else {
            $subst['{fullname}'] = $person['urname'];
            if(!empty($this->agmtdata['ul_signer_name'])) {
                $subst['{fullname}'] .= ", Руководитель: ".$this->agmtdata['ul_signer_name'];
                if(!empty($this->agmtdata['ul_signer_duty']))
                    $subst['{fullname}'] .= ", ". $this->agmtdata['ul_signer_duty'];
                if(!empty($this->agmtdata['ul_signer_dovno'])) {
                    $subst['{fullname}'] .= ", доверенность № ". $this->agmtdata['ul_signer_dovno'];
                    if(!empty($this->agmtdata['ul_signer_dovdate']))
                        $subst['{fullname}'] .= " от ". $this->agmtdata['ul_signer_dovdate'];

                }

            }
            $subst['{requizites}'] = 'ИНН '. $person['urinn'] . " ОГРН $person[ogrn]"
              . (!empty($person['kpp']) ? ", КПП $person[kpp]":'');
            $subst['{document-data}'] = $person['docser'].' № '. $person['docno'];
        }
        # if(AppEnv::isLocalEnv()) $subst['{fullname}'] = "<a href=\"javascript:void()\" onclick=\"plcUtils.viewPersonData('insr')\" title=\"Подрбности...\">".$subst['{fullname}'].'</a>';
        $body = strtr($body, $subst);
        AppEnv::localizeStrings($body);

        /*
        $dta['insdbirth'] = $insured[$insp.'birth'];
        $dta['insddoctype'] = $insured[$insp.'doctype'];
        $dta['insddocumentno'] = $insured[$insp.'docser'] . ' '. $insured[$insp.'docno'];
        $dta['insddocissued'] = $insured[$insp.'docissued'];
        $dta['insddocdate'] = $insured[$insp.'docdate'];
        $dta['phone'] = '(' . $insured[$insp.'phonepref'] .')-'.$insured[$insp.'phone'];
        if (!empty($insured[$insp.'phonepref2']) || !empty($insured[$insp.'phone2']))
            $dta['phone'] .= ', (' . $insured[$insp.'phonepref2'] .')-'.$insured[$insp.'phone2'];
        $dta['insd_fulladdress'] = self::buildFullAddress($insured, $insp);
        */

        return $body;
    }

    public function decodePepsState($state) {
        if ($state==0) return 'OK';
        if ($state>=10) return 'Блокирующий';
        return 'PEPS';
    }
    private function _viewBenefs($bentype = 'benef') {
        $body = "<table class='zebra w100prc'>"; # table p-5
        # WriteDebugInfo('_viewBenefs, _raw:', $this->_rawAgmtData);
        if($bentype === 'benef') $arBen =& $this->agmtdata['benef'];
        elseif($bentype === 'cbenef') $arBen = [ $this->_rawcBenefs ];
        else return '';
        if (isset($this->agmtdata['benef'])) {
            $body .= '<tr><th>ФИО</th><th>Дата рождения</th><th>% выплаты</th><th>Отношения</th><th>Очередь</th></tr>';

            foreach($arBen as $benef) {

                if ($this->_rawAgmtData['version']<2 || !$this->benef_separate_riskprc) {
                    $prc = $benef['percent'];
                }
                else { # формируем строку со всеми процентами по рискам у выгодоприобретателя
                    $prc = $this->renderBenefPercent($benef);
                    if(!$prc) $prc = $benef['percent'];
                }
                $porder = empty($benef['payorder']) ? '1' : $benef['payorder'];
                $body .= "<tr><td>$benef[fullname]</td><td class='ct'>" . to_char($benef['birth'])
                   . "</td><td class='ct'>$prc</td><td class='ct'>$benef[relate]</td><td class='ct'>$porder</td></tr>";
            }
        }
        /*
        if (!empty($this->agmtdata['cbenef'])) {
            writeDebugInfo("cbnef: ", $this->agmtdata['cbenef']);
            $bn = $this->agmtdata['cbenef'][0] ?? $this->agmtdata['cbenef'];
            $fullname = $bn['fullname'] ?? $bn['cbeneffullname1'] ?? '';
            $bbirth = $bn['birth'] ?? $bn['cbenefbirth1'] ?? '';
            $body .= '<tr><th colspan=>Выгодоприобретатель по ребенку</th><th>Дата рождения</tr>';
            $body .= "<tr><td>$fullname</td><td class='ct'>" . to_char($bbirth) . '</td></tr>';
        }
        */
        $body .= '</table>';
        return $body;
    }
    /**
    * формирует строку с процентами по всем рискам для выгодоприобретателя (ВП):
    *  СЛП:50%, СНС:100% ...
    * @param mixed $benef - ассоц.массив данных об одном ВП
    * (массив кодов рисков должен быть подготовлен - $this->benefRisks
    */
    public function renderBenefPercent($benef) {
        if ($this->_rawAgmtData['version']<2 || !$this->benef_separate_riskprc) $prc = $benef['percent'];
        else { # формируем строку со всеми процентами по рискам у выгодоприобретателя
            $prc = '';
            foreach($this->benefRisks as $no=>$rid) {
                $rname = self::getShortRiskName($rid);
                $fldname = 'percent' . (($no>0) ? ($no+1):'');
                if (!empty($benef[$fldname])) $prc .= ($prc ? ', ':'') . ("$rname:".$benef[$fldname]);
            }
        }
        return $prc;
    }

    public static function getRiskName($riskid, $what = 'longname') {
        return PlcUtils::getRiskName($riskid, $what);
    }

    # Загружаю данные о человеке/ЮЛ, с авто-преобразованием дат в DD.MM.YYYY Для редактир-я/печати
    public function loadIndividual($policyid, $ptype, $pref=null, $ul=0, $offset=FALSE, $mode = 0) {
        # WriteDebugInfo("loadIndividual($policyid,type:[$ptype],pref:'$pref',ul=$ul,offset:[$offset], mode:'$mode')\n   trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4));
        # $ret = Persons::loadIndividual($this, $policyid, $ptype, $pref=null, $ul, $offset, $mode);
        # return $ret;

        $multirow = ( $ptype==='insd' && $pref!=='insd2' );
        $cfg = [''];
        if ($ptype === 'child') {
            $cfg = explode(',', $this->insured_child);
            if ($cfg[0] === 'option') $multirow = TRUE;
            if(self::$debug) writeDebugInfo("child is multirow:[$multirow], $this->insured_child");
        }
        if ($pref === null) $pref = $ptype;
        # AppEnv::$db->log(2);
        $d = AppEnv::$db->select(PM::T_INDIVIDUAL, array(
           'where' => array('stmt_id'=>$policyid, 'ptype'=>$ptype)
          ,'singlerow' =>(($multirow) ? 0:1) # Для ВЗР возможен список (несколько застрахованных)
          ,'orderby'   => 'id'
          ,'rows'=>($multirow ? 100: 1)
          ,'offset'=>(int)$offset
        ));
        # if ($offset) echo "sql:".AppEnv::$db->getLastQuery() . '<br> err : '.AppEnv::$db->sql_error() . ' data: '.print_r($d,1) . '<hr>';

        # writeDebugInfo("get indiv ptype:$ptype, pref=$pref, offset: $offset trace: ", debug_backtrace(NULL,3));
        if (!is_array($d) || count($d)<1) return FALSE;

        if ($ptype === 'insr') $this->pholder = $d; # чистые поля без префиксов в именах
        elseif ($ptype === 'insd') {
            if ($pref === 'insd2') $this->insured2 = $d;
            else $this->insured = $d;
        }
        elseif ($ptype === 'child') $this->child = $d;

        $ret = array();
        # writeDebugInfo("KT-01 data : ", $d);
        if ($mode === 'export' && $multirow) return $d;
        if (count($d)>0 && ($multirow || isset($d[0]) && is_array($d[0]))) {
            foreach($d as $no => $drow) {
                $item = [];
                if ($cfg[0] === 'option') $pref = $ptype . ($no+1); # child12, child2...
                foreach($drow as $key => $val) {
                    if (in_array($key, $this->person_sysfields)) continue;
                    if (in_array($key, $this->person_datefields)) $val = (intval($val)>0 ? to_char($val) : '');
                    $item[$pref.$key] = $val;
                }
                if ( empty($drow['docfrom']) || intval($drow['docfrom']) ==0) $drow['docfrom'] = '';
                if ( empty($drow['doctill']) || intval($drow['doctill']) ==0) $drow['doctill'] = '';
                # if($ptype === 'child') writeDebugInfo("ready child item: ", $item);

                if (in_array($mode, ['edit','print','view']) && $cfg[0] === 'option') {
                    $ret = array_merge($ret, $item);
                }
                else {
                    $ret[] = $item;
                }
            }
        }
        elseif (isset($d['id'])) {
            foreach($d as $key=>$val) {
                if (in_array($key, $this->person_sysfields)) continue;
                if (in_array($key, $this->person_datefields)) $val = (intval($val)>0 ? to_char($val) : '');
                $ret[$pref.$key] = $val;
            }
        }
        if ($ul) {
            $ret[$pref.'urname'] = $ret[$pref.'fam'];
            $ret[$pref.'urinn'] = $ret[$pref.'inn'];
            unset($ret[$pref.'fam'],$ret[$pref.'imia'],$ret[$pref.'otch'],$ret[$pref.'inn'],$ret[$pref.'birth']);
        }
        if (in_array($mode, ['print','export'])) {
            if (isset($ret[$pref.'rez_country'])) {
                if (PlcUtils::isRf($ret[$pref.'rez_country']) && !empty($ret[$pref.'doctype'])) {
                    $ret[$pref.'docname'] = PlcUtils::decodeDocType($ret[$pref.'doctype']);
                    $ret[$pref.'fulldoc'] = PlcUtils::buildFullDocument($ret, $pref, 2, $this->_rawAgmtData);
                }
                else {
                    $ret[$pref.'docname'] = 'иностранный паспорт';
                    $ret[$pref.'fulldoc'] = PlcUtils::buildFullDocument($ret, $pref, 2); # PlcUtils::printedDocumentInfo
                }
            }

            ## if ($pref == 'insd') echo ("insd: multirow=[$multirow], doctype: ".$ret[$pref.'doctype'] . '<br>d<pre>'. print_r($d,1).'</pre>');
            if (!$multirow) {
                if (!empty($ret[$pref.'adr_zip'])) {
                    $ret[$pref.'fulladdr'] = $this->buildFullAddress($d,'','',1);
                    if(empty($d['sameaddr']))
                        $ret[$pref.'fullfaddr'] = $this->buildFullAddress($d,'','f',1);
                }
                elseif(isset($ret[0]) && is_array($ret[0])) {
                    foreach($ret as &$row) {
                        # застрахованный(м.б.несколко ) - массив!
                        $row[$pref.'fulladdr'] = $this->buildFullAddress($row,$pref,'',1);
                        if(!empty($row[$pref.'sameaddr']))
                            $row[$pref.'fullfaddr'] = $this->buildFullAddress($row,$pref,'f',1);

                        $row[$pref.'fulldoc'] = PlcUtils::buildFullDocument($row,$pref, 2,$this->_rawAgmtData);
                        $row[$pref.'_fullname'] = $row[$pref.'fam'] . ' ' . $row[$pref.'imia']
                          .(!empty($row[$pref.'otch']) ? (' '.$row[$pref.'otch']) : '');
                    }
                }

            }
            else { # multirow
                # if($ptype==='child')  echo ("child multirow<pre>" . print_r($d,1).'</pre>');
                if($ptype ==='child' && isset($d[0]) && is_array($d[0])) {
                    foreach($d as $no => $childRow) {
                        $chid = $no+1;
                        $ret["child{$chid}fullname"] =  $childRow['fam'] . ' ' . $childRow['imia']
                            .(!empty($childRow['otch']) ? (' '.$childRow['otch']) : '');;

                        $ret["child{$chid}fulladdr"] = $this->buildFullAddress($childRow,'','',1);
                        if(empty($childRow['sameaddr'])) $ret['child'.$chid.'fullfaddr'] = $this->buildFullAddress($childRow,'','f',1);
                        else $ret["child{$chid}fullfaddr"] = $ret["child{$chid}fulladdr"];
                        $ret["child{$chid}fulldoc"] = PlcUtils::buildFullDocument($childRow,'', 2);
                        # echo "created child{$chid}*: ". $ret["child{$chid}fullname"] . " / " . $ret["child{$chid}fulladdr"]. " / " . $row["child{$chid}fulldoc"] . "<br>'";
                    }
                    # echo( __FILE__ .':'.__LINE__.' todo: parse multiple childs:<pre>' . print_r($d,1) . '</pre>');
                }
                elseif(in_array($pref, ['insd','multi-child']) && is_array($ret[0]) ) {
                    # exit($pref. '/data: <pre>' . print_r($ret,1) . '</pre>');
                    foreach($ret as $nomer => &$row) {
                        # echo(__FILE__ .':'.__LINE__." [$nomer] / $pref, row : :<pre>" . print_r($row,1) . '</pre>');
                        # застрахованный(м.б.несколко ) - массив!
                        $row[$pref.'fulladdr'] = $this->buildFullAddress($row,$pref,'',1);
                        if(empty($row[$pref.'sameaddr']))
                            $row[$pref.'fullfaddr'] = $this->buildFullAddress($row,$pref,'f',1);

                        $row[$pref.'_fullname'] = $row[$pref.'fam'] . ' ' . $row[$pref.'imia']
                          .(!empty($row[$pref.'otch']) ? (' '.$row[$pref.'otch']) : '');
                        $row[$pref.'fulldoc'] = PlcUtils::buildFullDocument($row,$pref, 2, $this->_rawAgmtData);
                        if ($mode === 'print') {
                            # $row[$pref.'rez_country'] = PlcUtils::decodeCountry($row[$pref.'rez_country']);
                            $row[$pref.'birth_country'] = PlcUtils::decodeCountry($row[$pref.'birth_country']);
                        }
                        break; # остальных игнорирую, главный застрах - один!
                    }
                }
            }
        }
        # else writeDebugInfo("multirow $pref data: ", $ret);

        # if ($pref === 'insd2') writeDebugInfo("insd2: ", $ret);
        # writeDebugInfo("individual: ", $ret);
        return $ret;

    }

    # вернет TRUE, если договор можно оформить по ЭДО/ПЭП. Может быть переопределена!
    public function canUseEdo() {
        # writeDebugInfo("canUseEdo, _rawAgmtData: ", $this->_rawAgmtData);
        # TODO: есть ситуация, когда отдельный застрахованный - это фактически ребенок,
        # и тогда, возможно, надо разрешать ЭДО (GL)
        if(self::$debug) writeDebugInfo("canUseEdo KT-000 this->edoType=[$this->edoType]");
        if(empty($this->edoType)) return FALSE;
        $head = $this->_rawAgmtData['headdeptid'];
        # writeDebugInfo("[$head], text ",$this->_deptCfg);
        if(!isset($this->_deptCfg['deptid']) || $this->_deptCfg['deptid']!= $head) {
            $this->_deptCfg = PlcUtils::deptProdParams($this->module,$head);
        }
        if(!empty($this->_deptCfg['online_confirm']) && $this->_deptCfg['online_confirm'] >=10)
            return $this->_deptCfg['online_confirm'];

        if(!empty($this->_rawAgmtData['equalinsured'])) $canBeEdo = TRUE;
        else {
            # {updt/2025-02-04} если Основной застрахованный - ребенок, но его представитель = страхователь ФЛ, то ЭДО возможно
            $insured = Persons::getPersonData($this->_rawAgmtData['stmt_id'],'insd');
            if(!isset($insured['id']))
                $insured = Persons::getPersonData($this->_rawAgmtData['stmt_id'],'child');
            $insdAge = \RusUtils::yearsBetween($insured['birth'], ($this->_rawAgmtData['datefrom'] ?? ''));

            if($insdAge < PM::ADULT_START_AGE || $insured['ptype'] === 'child') {
                $specDta = $this->loadSpecData($this->_rawAgmtData['stmt_id']);
                $delegate = $specDta['spec_params']['child_delegate'] ?? 'N';
                if(self::$debug>1) writeDebugInfo("delegate: [$delegate], specdta: ", $specDta);
                $canBeEdo = ($delegate === 'Y'); # представитель - Страхователь
                unset($specDta);
            }
            else $canBeEdo = FALSE;
        }

        $ret = ( $this->_rawAgmtData['bptype']==='' && $canBeEdo
          && in_array($this->_rawAgmtData['bpstateid'],[0,PM::BPSTATE_EDO_NO])
          && empty($this->_rawAgmtData['reasonid'])
          && $this->_rawAgmtData['med_declar']!=='N'
        );
        if(self::$debug) writeDebugInfo("canUseEdo returns: [$ret], _rawAgmtData: ", $this->_rawAgmtData);

        return $ret;
    }
    /**
    * put your comment there...
    *  очищаю от выгодопр.
    * @param mixed $id
    * @param mixed $beneftype - если передать тип, очистятся только эти типы ВП (cbenef - представитель ребенка)
    */
    public function cleanPolicyBenefs($id, $beneftype = '') {
        $where = ['stmt_id'=>$id];
        if(!empty($beneftype)) $where['bentype'] = trim($beneftype);
        AppEnv::$db->delete(PM::T_BENEFICIARY,$where);
    }

    /**
    * Загружает выгодоприобретателей,
    *
    * @param mixed $id
    * @param mixed $ptype - '' или 'benef' - основные выгодоприобретатели, 'cbenef' - по застрахованному ребенку
    */
    public function loadBeneficiaries($id, $ptype = 'benef', $foredit = FALSE, $multiLine = FALSE) {
        return Persons::loadBeneficiaries($this, $id, $ptype, $foredit, $multiLine);
    }
    # saveBeneficiaries: перенесено в persons.php
    public function saveBeneficiaries($id, $pref, $data, $max_benef=4) {
        $ret = Persons::saveBeneficiaries($this, $id, $pref, $data, $max_benef);
        return $ret;
    }
    /**
    * Добавляяю "пользовательскую" кнопку для формы просмотра полиса
    *
    * @param array$btnData - ассоц.массив как в $this->all_buttons, плюс одно поле - 'checkfunc'=> {польз.ф-ция проверки доступности кнопки}
    * Эта ф-ция будет вызываться для определения, должна ли быть видна данная кнопа в настоящий момент
    * Описания кнопок без заданной ф-ции проверки игнорируются!
    **/
    public function addViewButton($btnData) {
        if (!is_array($btnData)) return;
        foreach($btnData as $key => $btn) {
            if (!isset($btn['checkfunc']) || empty($btn['html'])) continue;
            if (isset($this->all_buttons[$key])) continue;
            $this->all_buttons[$key] = $btn; // [ 'html' => $btn['html'], 'checkfunc'=>$btn['checkfunc'] ];
        }
    }
    /**
    * берет из базы риски по списку и делает опции для селекта (из "коротких наименований" рисков- shortname)
    *
    * @param mixed $risklist
    */
    public function buildRiskoptions($risklist) {
        $ret = '';
        foreach($risklist as $rename) {
            $data = AppEnv::$db->select(PM::T_RISKS, array('where'=>array('riskename'=>$rename),'singlerow'=>1));
            $label = isset($data['shortname'])? $data['shortname'] : $rename;
            $ret .= "<option value='$rename'>$label</option>";
        }
        return $ret;
    }

    public static function decodeOplata($opl) { # устарела - не использовать! Юзать encodeRassrochka()
        switch($opl) {
            case 'once' : return '0';
            case 'yearly': return '12';
            case 'half-yearly' : return '6';
            case 'monthly': case '1': return '1';
            default: return 0;
        }
    }

    public static function verboseOplata($opl) {
        # writeDebugInfo("verboseOplata called from ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        switch($opl) {
            case 'once' : case '0' : case 'E': return 'Единовременно';
            case 'yearly': case '12': return 'Ежегодно';
            case 'half-yearly' : case '6': return 'Раз в полугодие';
            case 'monthly' : case '1': return 'Ежемесячно';
            case '3': case 'Q': case 'quarterly': return 'Ежеквартально';
            default: return "[$opl]";
        }
    }

    public static function encodeRassrochka($rassrochka) {
        # WriteDebugInfo("encodeRassrochka($rassrochka)...");
        switch(strtolower($rassrochka)) {
            case 'once': case 'Единовременно': case 'e': case '0': return '0';
            case 'yearly': case 'ежегодно': case 'e': case '12':  return '12';
            case 'half-yearly': case 'раз в полгода': case 'раз в полугодие':
                case '6':  return '6';
            case 'monthly': case 'ежемесячно': case '1':  return '1';
            case 'q': case 'quarterly' : case 'ежеквартально': case '3':  return '3';
            default:
                return $rassrochka;
        }
    }
    # строит строку с полным адресом, если передать fact='f', то сделает по данным фfктич адреса (если он отличается от прописки)
    public static function buildFullAddress($data, $pref='', $fact='', $withZip=true) {
        if (!empty($data['sameaddr'])) $fact = '';
        $isRF = (empty($data[$pref.$fact.'adr_countryid']) || plcUtils::isRF($data[$pref.$fact.'adr_countryid']));
        $opts = array(
           'country' => ( !empty($data[$pref.$fact.'adr_countryid']) ? PlcUtils::decodeCountry($data[$pref.$fact.'adr_countryid']) : FALSE)
           ,'postcode' => ($withZip && !empty($data[$pref.$fact.'adr_zip']) ?  $data[$pref.$fact.'adr_zip'] : FALSE)
           ,'region' => ($isRF && !empty($data[$pref.$fact.'adr_country']) ? $data[$pref.$fact.'adr_country'] : FALSE)
           ,'district'   => (!empty($data[$pref.$fact.'adr_region']) ? $data[$pref.$fact.'adr_region'] : FALSE)
           ,'city'   => (!empty($data[$pref.$fact.'adr_city']) ? $data[$pref.$fact.'adr_city'] : FALSE)
           ,'street' => (!empty($data[$pref.$fact.'adr_street']) ? $data[$pref.$fact.'adr_street'] : FALSE)
           ,'house'  => (!empty($data[$pref.$fact.'adr_house']) ? $data[$pref.$fact.'adr_house'] : FALSE)
           ,'corp'   => (!empty($data[$pref.$fact.'adr_corp']) ? $data[$pref.$fact.'adr_corp'] : FALSE)
           ,'build'  => (!empty($data[$pref.$fact.'adr_build']) ? $data[$pref.$fact.'adr_build'] : FALSE)
           ,'flat'   => (!empty($data[$pref.$fact.'adr_flat']) ? $data[$pref.$fact.'adr_flat'] : FALSE)
        );

        # echo "$pref: <pre>" . print_r($data,1) . '</pre>'; echo '<pre>' . print_r($opts,1) . '</pre>'; exit;
        return \Persons::BuilPostAddress($opts);
    }
    public static function buildFullPassport($data, $pref = 'insr') {
        # UTF!
        $b_rus = self::isRF($data[$pref.'rez_country']);
        if ($b_rus) {
            $dtype = isset($data[$pref.'doctype']) ? $data[$pref.'doctype'] : 1;
            $strDoctype = (isset($data[$pref.'doctype'])) ? self::decodeDocType($data[$pref.'doctype']) : 'Паспорт';
            $vidan = 'выдан';
            if (isset($data[$pref.'doctype'])) {
                if( in_array($data[$pref.'doctype'], array(2,10,11)) )
                    $vidan = 'выдано';
                elseif ( in_array($data[$pref.'doctype'], array(20)) )
                    $vidan = 'выдана';
            }
            $ret = $strDoctype . ' ' . $data[$pref.'docser'] . ' № ' . $data[$pref.'docno'];
            if ($dtype == 1 || $dtype == 2) { # выдан - дата и место только у пасопрта и св-во о рожд
                $ret .= ", $vidan ". $data[$pref.'docissued'] . ' ' . $data[$pref.'docdate'];
                if (!empty($data[$pref.'docpodr'])) $ret .= ", код подр. " . RusUtils::makeCodePodr($data[$pref.'docpodr']);
            }
            elseif ($dtype == 3 || $dtype==4 && intval($data[$pref.'docdate'])) {
                $ret .= ", выдан ". $data[$pref.'docdate'];
            }

        }
        else {
            $ret = 'Иностранный паспорт '.$data[$pref.'inopass'];
        }
        return $ret;
    }

    /**
    * поменяли дату оплаты в диалоге "простановка отметки об оплате" -
    * надо сменить сумму платежа в рублях, по курсу на дату
    * Если дата из будущего, блокирую кнопку ОК (#btn_dosetpayed)
    * @since 24.04.2018 - добавлено поле номер платежки - проверяю !
    */
    public function plc_newdatepay() {
        # WriteDebugInfo("plc_newdatepay params: ", $this->_p);
        $plcid = isset($this->_p['id']) ? $this->_p['id'] : 0;

        $dta = $this->loadPolicy($plcid,-1);
        $dtcreated = to_date($dta['created']);
        $dtpay = isset($this->_p['dtpay']) ? $this->_p['dtpay'] : date('d.m.Y');
        $dtpay = to_date($dtpay);
        $ret = '1';
        $today = date('Y-m-d');
        if($dtpay > $today) {
            $ret .= "\thtml\fin_oplata_rub\fневерная дата!\tenable\f#btn_dosetpayed\f0";
        }
        elseif(!empty($dta['previous_id'])) { # пролонгация - двтв оплаты может быть ДО сегодня
            # TODO: надо чего-то проверять?
            # writeDebugInfo("OK-prolong");
        }
        elseif ( $dtpay < $dtcreated ) {
            $ret .= "\thtml\fin_oplata_rub\fневерная дата!\tenable\f#btn_dosetpayed\f0";
        }
        else {
            if ($dta['currency'] !== 'RUR' && $dta['currency'] !== 'RUB') {
                $rate = \PlcUtils::getCurrRate($dta['currency'], $dtpay);
                if ($rate <= 0) $rate = AppEnv::getConfigValue('intrate_usd', 60);
                $oplrub = fmtMoney($dta['policy_prem'] * $rate);
                $ret .= "\thtml\fin_oplata_rub\f" . $oplrub
                    . "\tenable\f#btn_dosetpayed\f1";

            }
            else { # рубли
                $ret .= "\thtml\fin_oplata_rub\f" . fmtMoney($dta['policy_prem'])
                    . "\tenable\f#btn_dosetpayed\f1";
            }
        }
        exit($ret);
    }
    /**
    *  простановка отметки об оплате
    * + номер платежки (platno) (2023-больше не вводим!)
    */

    public function setPayed() {
        if (AppEnv::isApiCall()) {
            # self::$debug = max(self::$debug, 2);
            $this->_p = AppEnv::$_p;
            if (self::$debug) {
                WriteDebugInfo("API call setPayed, params:", AppEnv::$_p);
                writeDebugInfo("connected user id:", AppEnv::getUserId(), " dept:", AppEnv::$auth->deptid);
            }
        }
        # exit('1' . AjaxResponse::showMessage('Data: <pre>' . print_r(AppEnv::$_p,1) . '</pre>'));
        $forexpired = $this->_p['forexpired'] ?? FALSE;

        $plcid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        if(!$plcid && !empty($this->_p['stmt_id'])) $plcid = $this->_p['stmt_id'];
        if ($plcid <=0) {
            $errtxt = 'Не передан ИД договора';
            if (AppEnv::isApiCall()) {
                return ['result' => 'ERROR',
                  'message' => $errtxt,
                  # № 'params' => $this->_p, # для отладки
                ];
            }
            AppEnv::echoError($errtxt);
            exit;
        }

        # WriteDebugInfo("setPayed params:", $this->_p);
        $today = date('Y-m-d');
        $datepay = !empty($this->_p['datepay']) ? to_date($this->_p['datepay']) : '';

        # {upd/2023-03-07} вводить номер платежки больше не хотят (А.Загайнова)
        $platno =  !empty($this->_p['platno']) ? RusUtils::mb_trim($this->_p['platno']) : '';

        $shiftStartDate = FALSE; # признак - надо сдвинуть даты начала-окончания по полису м всем рискам
        /*
        if (empty($platno)) { // platno сможет быть HEX строкой (не дес.числом!)
            $errtxt = 'Не передан номер платежного документа';
            if (AppEnv::isApiCall()) return ['result' => 'ERROR','message' => $errtxt];
            AppEnv::echoError($errtxt);
            exit;
        }
        */

        if (!$plcid OR !PlcUtils::isDateValue($datepay)) {
            $errtxt = 'Не указана либо некорректная дата оплаты либо ИД полиса';
            if (AppEnv::isApiCall()) {
                return ['result' => 'ERROR',
                  'message' => $errtxt
                ];
            }
            AppEnv::echoError($errtxt);
            exit;
        }
        $dta = $this->loadPolicy($plcid,-1);

        $errId = FALSE;
        if(in_array($dta['stateid'],[PM::STATE_ANNUL,PM::STATE_CANCELED]))
            $errId = 'err_policy_annulated';
        elseif($dta['stateid'] >= 50)
            $errId = 'err_policy_dissolved';
        elseif(PlcUtils::isDateValue($dta['datepay']) && in_array($dta['stateid'],[PM::STATE_PAYED,PM::STATE_FORMED]))
            $errId = 'err_policy_already_payed';

        if($errId) {
            $errText = AppEnv::getLocalized($errId);
            if (AppEnv::isApiCall()) return ['result'=>'ERROR','message'=> $errText];

            AppEnv::echoError($errText);
            return;
        }

        $err = FALSE;
        # if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'details'=>$dta]; # online pay debug stop

        if(!empty($dta['reasonid']) && $dta['stateid']!=PM::STATE_UWAGREED) {
            $msgErr = InsObjects::getUwReasonDescription($dta['reasonid'], $dta);
            $errText = "Сначала должно быть согласование андеррайтером,<br>$msgErr";
            if (AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$errText];
            exit('1' . AjaxResponse::showError($errText));
        }
        $admin = $this->isAdmin();
        if ($admin) $ok = TRUE;
        else {
            $ok = $this->checkDocumentRights($dta);
        }

        if (!$ok) {
            if (AppEnv::isApiCall()) return ['result'=>'ERROR',
              'message'=> AppEnv::getLocalized('err-no-rights-document')
            ];
            AppEnv::echoError('err-no-rights-document');
            return;
        }

        if (!AppEnv::isLightProcess() && !$this->isDeclarationSet() && !$admin) {
            $errText = AppEnv::getLocalized('err_declaration_not_set');
            if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$errText];
            AppEnv::echoError('err_declaration_not_set');
            return;
        }
        if(method_exists($this, 'beforeViewAgr'))
            $this->beforeViewAgr();
        # exit("riskyProg: " .($this->riskyProg ? 'YES':'NO'));

        # дата оплаты раньше даты проследней калькуляции недопустима:
        if(!empty($dta['date_recalc']) && intval($dta['date_recalc']) ) $created = $dta['date_recalc'];
        else $created = substr($dta['created'],0,10);
        # {upd/01.09.2016} разрешаю оплачивать задним числом до 30 дней, заявка от Е.Кузнецовой {upd/2022-10-30} - 3 дня назад!(А.Загайнова)
        # WriteDebugInfo("maxpaydate :$maxpaydate дата оплаты: $datepay");
        $err_msgid = FALSE;
        if($datepay > $today) {
            $err_msgid = 'err_setpayed_wrong_date';
            # writeDebugInfo("ERR-01");
        }
        elseif(!empty($dta['previous_id'])) {
            # при оплате пролонгации - даем завести оплату с датой ДО факт.даты начала д-вия
            if($datepay >= $dta['datefrom'])
                $err_msgid = 'err_setpayed_prolong_expired';
        }
        elseif ( $datepay < $created ) {
            # WriteDebugInfo("err-001 dtpay: $datepay CREATED:$created, dta[Сreated]: ",$dta['created'], ' today:',$today);
            # exit('1' . ajaxResponse::showMessage("KT-001 pay=$datepay today=$today created = $created"));
            $err_msgid = 'err_setpayed_wrong_date';
        }
        else {
            $daysAfterRelease = 0;
            $dtRelease = intval($dta['date_release']) ? $dta['date_release'] : $today;
            # exit('1' . AjaxResponse::showMessage('date_release: <pre>' . print_r($dta['date_release'],1) . "<br>dtRelease=$dtRelease</pre>"));
            if(method_exists($this, 'getDaysFromReleaseToPay')) {
                $daysAfterRelease = $this->getDaysFromReleaseToPay();
                $maxPayDate = addToDate($dtRelease,0,0,$daysAfterRelease);
            }
            else $maxPayDate = BusinessProc::getMaxPayDate($dtRelease);
            # exit('1' . AjaxResponse::showMessage("created: $created, pay date: $datepay, daysAfterRelease=$daysAfterRelease,  MaxPay date: $maxPayDate"));
            if($dta['metatype'] == OrgUnits::MT_BANK) {
                if(intval($dta['date_release'])>0) {
                    if($datepay < $dta['date_release'])
                        exit('1' . ajaxResponse::showError("err_payment_before_release"));

                    # writeDebugInfo("оплата до maxDatePay: $maxDatePay");
                    # добавляю на оплату 4 дня после даты выпуска
                    if(($datepay > $maxPayDate))
                        exit('1' . ajaxResponse::showError("err_payment_after_release"));

                }
                # exit('1' . ajaxResponse::showMessage("DEBUG: Тут проверка даты оплаты для банка $datepay VS max pay:$maxPayDate"));
            }
            elseif(!empty($dta['date_release_max']) && intval($dta['date_release_max'])) {
                $maxdaysBack = $admin ? 20 : PM::MAX_DAYS_BACK;
                $minPaydate = date('Y-m-d', strtotime("-$maxdaysBack days"));
                # exit('1' . ajaxResponse::showMessage("date_release_max: $maxdaysBack, min pay date: $minPaydate"));
                if(intval($dta['date_recalc'])) $minPaydate = max($minPaydate,$dta['date_recalc']);
                $shiftStartDate = TRUE; # после простановки оплаты двигаю дату нач.д-вия к дате оплаты, если она ниже
                # {upd/2022-12-20} - для рисковых смотрим текущую дату, а не дату оплаты(ФТ-Загайнова)

                $checkedDate = isset($this->_p['datepay']) ? to_date($datepay) : $today; # $this->riskyProg - не попало для Р-К (всегда ставил "сегодня")
                # writeDebugInfo("riskyProg: [".$this->riskyProg."] $checkedDate <= ".$dta['date_release_max'] . ' ???');
                # exit('1' . AjaxResponse::showMessage("date pay: $checkedDate, MDV: ".$dta['date_release_max']));
                if( $checkedDate > $dta['date_release_max'] && empty($dta['previous_id']) ) {
                    if($forexpired) PlcUtils::setNoPayment($this->_p);
                    else exit('1' . AjaxResponse::showMessage('not forexpired <pre>' . print_r($dta,1) . '</pre>'));
                    # {upd/2022-11-01} оплата позже макс.даты выпуска, при пролонгации с опозданием - допустимо!
                    if($dta['stateid'] == PM::STATE_IN_FORMING)
                        $err_msgid = 'err-policy-calculation-expired-informing';
                    else
                        $err_msgid = 'err-policy-calculation-expired';
                    # exit('1' . ajaxResponse::showError(AppEnv::getLocalized('err-policy-calculation-expired')));
                }
                elseif($datepay<$minPaydate && empty($dta['previous_id'])) {
                    # {upd/2023-05-31} - при пролонгации не проверяю (и.Дейнекина)
                    $err_msgid = 'err_setpayed_date_too_early';
                }
            }
            else {
                # {upd/2021-09-28} - Дату оплаты теперь разрешаю до 30 дней после даты начала, а не -90 от текущей (А.Шатунова)
                # exit('1' . ajaxResponse::showMessage("Без date_release_max: <pre>".print_r($dta,1).'</pre>'));
                $daysFrom = $admin ? 200 : PM::MAX_DAYS_PAY_FROM;
                $maxPayDate = ($daysFrom > 0) ? date('Y-m-d', strtotime($dta['datefrom'] . " +$daysFrom days")) : $dta['datefrom'];
                if($daysFrom > 0  && ( $datepay > $maxPayDate && (empty($dta['previous_id']) || empty(PlcUtils::$prolongDebug)) )) {
                # {upd/2021-03-30} разрешаю ставить оплату до N дней после даты начала д-вия (админ - 100 дней)
                    # writeDebugInfo("$datepay > $dta[datefrom]");
                    $err_msgid = 'err_setpayed_too_late';
                    # AppEnv::echoError('err_setpayed_too_late');
                }
                elseif (in_array($dta['stateid'], [2,9,10,11,PM::STATE_BLOCKED, PM::STATE_DISSOLUTED])) {
                    $err_msgid = 'err-wrong-document-state';
                    AppEnv::$msgSubst['{state}'] = self::decodeAgmtState($dta['stateid'],'',FALSE);
                    # AppEnv::echoError('err-wrong-document-state');
                }
            }
            # exit('1' . ajaxResponse::showMessage("err msg: $err_msgid"));
            /*
            elseif (($this->b_stmt_exist) && in_array($dta['stmt_stateid'], array(2,9,10))) {
                $err_msgid = 'err-wrong-document-state';
                AppEnv::$msgSubst['{state}'] = self::decodeAgmtState($dta['stmt_stateid'],'',FALSE);
                #AppEnv::echoError('err-wrong-document-state');
            }
            */
        }
        if ($err_msgid) {
            $errMsg = AppEnv::getLocalized($err_msgid);
            if ($err_msgid === 'err_setpayed_date_too_early')
                $errMsg = sprintf($errMsg, RusUtils::skloNumber(PM::MAX_DAYS_BACK, 'день') );

            elseif ($err_msgid === 'err_setpayed_too_late')
                $errMsg = sprintf($errMsg, RusUtils::skloNumber(PM::MAX_DAYS_PAY_FROM, 'день'));
                # " более чем на " . RusUtils::skloNumber(PM::MAX_DAYS_PAY_FROM, 'день');

            if (AppEnv::isApiCall()) {
                return ['result' => 'ERROR',
                  'message' => $errMsg
                ];
            }
            if(isAjaxCall()) exit('1'.AjaxResponse::showError($errMsg));
            AppEnv::echoError($errMsg);
            exit;
        }
        # {upd/2024-04-23} - проверка наличия паспорта при простановке оплаты (только агенты)
        if ( empty($this->_p['eqpayed']) && !AppEnv::isLightProcess() ) {
            $this->checkMandatoryFiles(Events::PAYED);
        }

        $pay_rur = isset($this->_p['pay_rur']) ? floatval($this->_p['pay_rur']) : $dta['policy_prem'];

        $upd = [ 'datepay'=>$datepay, 'policy_prem_rur' => $pay_rur ]; # , 'platno' => $platno
        if ($this->enable_statepaid) {
            $upd['stateid'] = PM::STATE_PAYED;
        }
        if ($dta['currency'] !== 'RUR') {
            include_once('class.currencyrates.php');
            $upd['currate'] = $rate = CurrencyRates::GetRates($datepay,$dta['currency']);
            $pay_rur = $upd['policy_prem_rur'] = round($dta['policy_prem'] * $rate, 2);
            # WriteDebugInfo('new cur rate:', $rate, " payment: $pay_rur");
        }
        $bPayOnline = FALSE;
        if (!empty($this->_p['eqpayed'])) {
            # API: пришло известие об онлайн-оплате!
            $bPayOnline = TRUE;
            if ($this->_p['eqpayed'] == 1) $upd['eqpayed'] = floatval($pay_rur);
            else $upd['eqpayed'] = max($this->_p['eqpayed'], $pay_rur);
            # в этом случае статус сразу делать Оформлен - через настройку $this->payed_to_formed
            # $upd['stateid'] = PM::STATE_PAYED;
        }
        ## if (SuperAdminMode()) exit('1' . AjaxResponse::showMessage("<pre>".print_r($this->_rawAgmtData,1).'</pre>'));

        # если ЭДО еще не включили, то после оплаты уже и нельзя:
        if (empty($this->_rawAgmtData['bptype'])) {
            if(appEnv::isLightProcess() && empty($this->_rawAgmtData['sescode'])) {# при облегченном онлайн процессе автоматом включаю ЭДО, заношу ПЭП-код
                $upd['bptype'] = PM::BPTYPE_EDO;
                $upd['bpstateid'] = PM::BPSTATE_EDO_OK;
                if(!empty($this->_p['sescode']))
                    $upd['sescode'] = $this->_p['sescode'];
                else {
                    # $upd['sescode'] = UniPep::createHashCode($plcid, $this->module);
                    # Могу и генерить? а пока отправляю ошибку
                    if(appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'Не передан ПЭП код (sescode)'];
                }
                $upd['dt_sessign'] = '{now}';
            }
            else $upd['bptype'] = PM::BPTYPE_STD;
        }

        if(!AppEnv::isLightProcess() && empty($upd['eqpayed']) && Acquiring::hasWaitingOrder($this->module, $plcid)) {
            $declined = Acquiring::blockAllCards($this->module, $plcid, Acquiring::PAYED_EXTERN);
            # writeDebugInfo("decline orders result ", $declined);
            if(!$declined && $this->_userLevel < PM::LEVEL_IC_ADMIN)
                exit('1' . AjaxResponse::showError('Клиент начал онлайн оплату, ввод оплаты офлайн невозможен'));
        }
        # онлайн-продажи: проект полиса мог быть с черновым номером полиса
        $newPno = $this->_rawAgmtData['policyno'];

        $logtxt = 'Проставлена отметка об оплате, оплата: ' . to_char($datepay);
        if ($bPayOnline && !AppEnv::isLightProcess())  $logtxt .= " (Онлайн оплата)";

        if($this->isDraftPolicyno($newPno)) {
            # return ['result'=>'ERROR' , 'upd'=>$upd];
            $draft = $this->isDraftPolicyno($this->_rawAgmtData['policyno']);
            # writeDebugInfo("выдать номер полиса _rawAgmtData: ", $this->_rawAgmtData);
            $newPnoDta = $this->assignPolicyNo($plcid);
            $newPno = '';

            if(self::$debug) writeDebugInfo("assignPolicyNo result: ", $newPnoDta);
            if(is_scalar($newPnoDta) && !empty($newPnoDta)) $newPno = $newPnoDta;
            elseif( is_array($newPnoDta) && !empty($newPnoDta['data']['policyno'])) $newPno = $newPnoDta['data']['policyno'];

            if(empty($newPno)) {
                $errText = 'Невозможно получить номер полиса из пула номеров';
                if(appEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$errText];
                exit('1' . AjaxResponse::showError("Оплата не проставлена, $errText"));
            }
            $logtxt .= ", Выдан номер $newPno";
            $this->_rawAgmtData['policyno'] = $newPno;
        }

        # {upd/2025-08-20} При упрощенном онлайн процессе - автоматом проставляю отметку о соотв.декларации
        if(empty($this->_rawAgmtData['med_declar']) && AppEnv::isLightProcess())
            $upd['med_declar'] = 'Y';

        if(self::$debug) writeDebugInfo("policy update array: ", $upd);

        $ok = PlcUtils::updatePolicy($this->module, $plcid, $upd);
        if(!$ok) {
            $errText = 'Ошибка при обновлении данных в БД';
            if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$errText];
            exit('1' . AjaxResponse::showError($errText));
        }

        # если клиенту отправлена ссылка, но он оплатил где-то еще, после рег.платежа сбросить все ордера на онлайн-оплату

        AppEnv::logEvent($this->log_pref.'SET PAYED', $logtxt, '', $plcid);

        # все операции после оплаты (простой и эквайрингом) унес сюда:
        # writeDebugInfo("call afterPolicyPayed...");
        $formed = $this->afterPolicyPayed($datepay, $bPayOnline);
        if(self::$debug) writeDebugInfo("afterPolicyPayed result:", $formed);

        if (AppEnv::isApiCall()) {
            $onlinePay = empty($this->_p['eqpayed']) ? '' : 'Онлайн-';
            $apimsg = "Отметка об {$onlinePay}оплате проставлена";
            $ret = ['result'=>'OK', 'message' => $apimsg, 'data'=>['stateid' => $upd['stateid'] ]];
            if($newPno) $ret['data']['policyno'] = $newPno;
            if ($formed) {
                $ret['message'] .= "; новый статус полиса - Оформлен";
                $ret['data']['stateid'] = PM::STATE_FORMED;
            }
            if(AppEnv::isLightProcess()) {
                # выполняю все финальные операции - генерация е-полиса, пимьмо клиенту (?) отправка в СЭД, статус - Активный(?)
                # PlcUtils::FinalLightProcess($plcid);
                if(self::$debug) writeDebugInfo("LightProcess: финальные операции над оплаченным онлайн полисом...");

                # $sedCard = \PlcUtils::setStateActive($this->module, $plcid); # сразу в СЭД
                # if(self::$debug) writeDebugInfo("online/setStateActive result: ", $sedCard);

                $ret['data']['send_forreg'] = $submResult = $this->submitForReg($plcid); # отправка на учет
                if(self::$debug) writeDebugInfo("online/submitForReg result: ", $submResult);
                # TODO: отправка письма в ПП ?
            }
            return  $ret;
        }

        $ret = '1' . $this->refresh_view($plcid, true);

        $details = "Проставлена отметка об оплате";
        $notify = ($this->notify_agmt_change==1 || stripos($this->notify_agmt_change,'PAYED')!==FALSE );

        $notifyStatus = 'setpayed';
        if ($formed) {
            $details .= '. Полис переведен в статус Оформлен';
            $notifyStatus .= ',formed';
            $notify = $notify || ($this->notify_agmt_change==1 || stripos($this->notify_agmt_change,'FORMED')!==FALSE );
        }
        if ($notify && method_exists($this, 'notifyAgmtChange')) {
            $sent = $this->notifyAgmtChange($details, $plcid, $notifyStatus);
            if(self::$debug) writeDebugInfo("notifyAgmtChange result: [", $sent, '] details: ', $details);
        }
        else {
            $sent = agtNotifier::send($this->module, Events::PAYED,$this->_rawAgmtData);
            if(self::$debug) writeDebugInfo("agtNotifier::send result for ".Events::PAYED.": [", $sent, ']');
        }
        exit($ret);
    }
    # станд.д-вия по окончании регистрации оплаты - перенаправл. в BusinessProc::afterPolicyPayed
    public function afterPolicyPayed($datepay, $payOnline=FALSE) {
        $ret = BusinessProc::afterPolicyPayed($this, $this->_rawAgmtData, $datepay, $payOnline);
        return $ret;
    }
    # Если у полиса пропущена макс.дата выпуска, он "блокируется" для агента/менеджера (печать и проч.)
    public function policyCalcExpired() {
        $checkDate = ($this->agmtdata['date_release_max'] ?? '');
        if(!empty($this->agmtdata['previous_id'])) {
            # {updt/2023-03-14} - если пролонгация, не блокировать оплату после даты начала, в пределах дней из конфиг.
            $daysOff = AppEnv::getConfigValue('prolong_days_afterend');
            if($daysOff>0) $checkDate = date('Y-m-d', strtotime(to_date($this->agmtdata['datefrom']) . " +$daysOff days"));
        }

        # writeDebugInfo("policyCalcExpired, checkDate=[$checkDate], agmtdata: ", $this->agmtdata);
        $expired = (PlcUtils::isDateValue($checkDate) && $checkDate<date('Y-m-d')
          && in_array($this->agmtdata['stateid'], [6,PM::STATE_PROJECT, PM::STATE_IN_FORMING, PM::STATE_UNDERWRITING, PM::STATE_UWAGREED])
          && intval($this->agmtdata['datepay'])==0 && intval($this->agmtdata['bpstateid']) == 0 # bpstateid=1 - уже выпущен, не считать его просроч.
          # && $this->getUserLevel() < PM::LEVEL_IC_ADMIN
        );
        if(self::$debug) writeDebugInfo("policy project is expired: [$expired]");
        return $expired; # печатать полис нельзя - профукали дату выпуска
        # exit ("plcdata: <pre>" . print_r($this->agmtdata,1).'</pre>');
    }
    # Присваиваем вместо временного номера полиса "окончательный" из пула номеров
    public function assignPolicyNo($plcid = 0) {
        $logThisEvent = FALSE;
        if (!$plcid && !empty($this->_rawAgmtData['stmt_id']))
            $plcid = $this->_rawAgmtData['stmt_id'];
        if (!isset($this->_rawAgmtData['policyno']))
            $this->loadPolicy($plcid);
        $plcSplit = explode('-', $this->_rawAgmtData['policyno']);

        if (isset($plcSplit[1]) && is_numeric($plcSplit[1])) return TRUE;

        $pno = NumberProvide::getNext($plcSplit[0], $this->_rawAgmtData['headdeptid'], $this->module);
        # WriteDebugInfo("state to FORMED, next policy no:", $pno);
        if ($pno > 0) {
            $newPolicyNo = $plcSplit[0] . '-' . str_pad($pno, $this->policyno_len,'0',STR_PAD_LEFT);
            $upd = ['policyno'=>$newPolicyNo];
            PlcUtils::updatePolicy($this->module, $plcid, $upd);
            if($logThisEvent) AppEnv::logEvent($this->log_pref.'SET POLICYNO',"Полису присвоен номер $newPolicyNo",'',$plcid);
        }
        else {
            $newPolicyNo = FALSE;
            if (!$this->isAdmin()) {
                $module = $this->module;
                $url = PlcUtils::getLinkViewAgr($module, $plcid);
                $errTxt = "В системе ALFO не был получен номер для полиса $plcid,\n"
                 . "Проверьте наличие пула номеров и после этого при необходимости выполните ручной перевод полиса в статус ОФОРМЛЕН по ссылке ниже :\n"
                 . "<a href='{$url}'>Полис</a>";
                # AppAlerts::raiseAlert("POOL ERROR:$plcSplit[0]", $errTxt);
                AppEnv::sendSystemNotification('ALFO:не выдан номер полиса!', $errTxt);
            }

        }
        if (AppEnv::isApiCall()) {
            if ($newPolicyNo === TRUE) $newPolicyNo = $this->_rawAgmtData['policyno'];
            $ret = [
              'result' => ($newPolicyNo ? 'OK':'ERROR'),
              'message' => ($newPolicyNo ? '': $this->errorMessage),
              'data' => ['policyno' => $newPolicyNo]
            ];
            return $ret;
        }
        return $newPolicyNo;
    }
    /**
    * Пеервод в статус Оформлен
    *
    * @param mixed $plcid ИД полиса
    * @param mixed $event : 'online' - если полис оформлен автоматом после онлайн-платы
    * и надо всех уведомить или послать юзеру письмо с вложеным PDF...
    */
    public function setPolicyFormed($plcid = 0, $event = FALSE, $auto=FALSE) {
        if(self::$debug)  WriteDebugInfo("setPolicyFormed($plcid, event=[$event], auto=[$auto]) edoformed_fix=[$this->edoformed_fix]");
        # {upd/2025-08-19} - при упрощенном онлайн оформлении проверки файлов не делаю

        if(AppEnv::isLightProcess()) $auto = TRUE;
        if ($plcid>0 && empty($this->_rawAgmtData['stmt_id'])) {
            $this->loadPolicy($plcid, -1);
        }
        if (!$auto && !$this->isDeclarationSet() && !$this->isAdmin()) {
            $this->errorMessage = AppEnv::getLocalized('err_declaration_not_set');
            return FALSE;
        }
        if (!$auto) {
            if( $this->_rawAgmtData['bptype'] === PM::BPTYPE_EDO && $this->edoformed_fix) {
                # будет сохранение в СЭД, значит, надо проверить наличие всех сканов!
                $filesOk = $this->checkMandatoryFiles('fixpolicy');
            }
            else {
                if (!$this->isAdmin()) {
                    $filesOk = $this->checkMandatoryFiles('setformed');
                }
            }
        }
        $upd = ['stateid' => PM::STATE_FORMED, 'stmt_stateid'=>PM::STATE_FORMED, 'statedate'=>'{now}'];
        # {upd/2020-06-17} заносим ИД коуча из справ-ка орг-юнитов:
        if (isset($this->_rawAgmtData['coucheid']) && ($coucheid = AppEnv::findDeptProp('couche_id', $this->_rawAgmtData['deptid']))) {
            $upd['coucheid'] = $coucheid;
            # writeDebugInfo("coucheid $coucheid");
        }

        $policyno = $this->_rawAgmtData['policyno'];
        if (intval($this->_rawAgmtData['datepay'])==0 )
            $upd['datepay'] = '{today}';

        $upd['reasonid'] = 0;
        # AppEnv::$db->update(PM::T_POLICIES, $upd,['stmt_id'=>$plcid]);
        $pResult = PlcUtils::updatePolicy($this->module, $plcid, $upd);
        $evtype = 'SET STATE';
        $logtxt = 'Установлен статус договора: ' . PlcUtils::decodePolicyState(PM::STATE_FORMED);
        AppEnv::logEvent($this->log_pref.$evtype, $logtxt, '', $plcid);

        # Если полис с ПЭП-согласованием, фиксируем подписанный PDF полиса в базе!
        if($this->_rawAgmtData['bptype'] === PM::BPTYPE_EDO && ($this->edoformed_fix || AppEnv::isLightProcess()) ) {
            $fixResult = EdoPep::FixPolicy($this->module, $plcid);
            if(self::$debug) writeDebugInfo("FixPolicy done: ", $fixResult);
            # TODO: передать команду на обновление списка сканов, если не isApiCall() ?
        }

        $sent = 0;
        if(!AppEnv::isLightProcess()) { # письма только при стандартной оплате. При легком процессе - свои д-вия!
            if (!$this->isEdoPolicy()) {
                $sent = InsObjects::sendNotifyAboutFormedPolicy($this->module, $plcid, $policyno);
                if(self::$debug) writeDebugInfo("sendNotifyAboutFormedPolicy result:", $sent);
            }

            if (!$sent) {
                if (method_exists($this, 'notifyAgmtChange')) {
                    $sent = $this->notifyAgmtChange($logtxt, $plcid);
                    if(self::$debug) writeDebugInfo("notifyAgmtChange($logtxt) result:", $sent);
                }
                elseif($this->agent_prod !== PM::AGT_NOLIFE) {
                    if (self::$debug) writeDebugInfo("agent_prod branch, send FORMED notify");
                    $sent = agtNotifier::send($this->module, Events::FORMED, $plcid);
                    if(self::$debug) writeDebugInfo("agtNotifier::send(".Events::FORMED.") result:", $sent);
                }
            }
        }
        # {upd/2021-08-18} автоматом отправлять письмо об учетке клиента, OMNI R-212614
        if (in_array($this->module, ClientUtils::$AUTOSEND_MODULES)) {
            $data = $this->loadPolicy($plcid, 'print');
            $sentCliMail = ClientUtils::sendLetterNewClient($data, $this->getLogPref(), 1);
            if(self::$debug) writeDebugInfo("ClientUtils::sendLetterNewClient for client acc: [$sentCliMail]");
        }

        # writeDebugInfo("KT-001 event: [$event]", $event);
        if (($event || $this->afterFormed) && method_exists($this, 'afterPolicyFormed')) {# зову callback, если есть в backEnd модуле
            $fresult = $this->afterPolicyFormed($plcid, $event);
            if(self::$debug) writeDebugInfo("this->afterPolicyFormed result: ", $fresult);
        }

        return TRUE;
    }
    # установка всех прочих статусов
    public function setState() {

        if (AppEnv::isApiCall()) {
            $this->_p = AppEnv::$_p;
        }
        # exit($this->_p['state']);
        $statetxt = $this->_p['state'] ?? '';
        if (self::$debug) WriteDebugInfo('setState params:', $this->_p);
        # exit('1' . AjaxResponse::showMessage('Data: <pre>' . print_r($this->_p,1) . '</pre>'));

        # эмуляция ошибки 500 при AJAX запросе:
        /*
        if(SuperAdminMode() && !AppEnv::isProdEnv() && $statetxt==='uwagreed') {
            header("X-Error-Message: testing ERROR 500 reaction in AJAX call", TRUE, 500);
            exit(" Additional Error Info in SetState, just testing ERROR 500 case in AJAX");
        }
        */
        $plcid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        $shifted = isset($this->_p['shift']) ? $this->_p['shift'] : 0; # альт.режим выполнения
        $resetPlc = 0;
        $uwcomment = isset($this->_p['uwcomment']) ? Sanitizer::safeString($this->_p['uwcomment']) : '';
        $msgOk = $msgErr = $warning = '';

        if (!$plcid OR $statetxt==='') AppEnv::echoError('err_wrong_call');
        if(!empty($this->_rawAgmtData['stmt_id']) && $this->_rawAgmtData['stmt_id'] == $plcid)
            $data =& $this->_rawAgmtData;
        else $data = $this->loadPolicy($plcid, -1);

        if(method_exists($this, 'beforeViewAgr'))
            $this->beforeViewAgr();

        $b_admin = $this->isAdmin();
        # $userLevel= $this->getUserLevel();
        $userLevel = AppEnv::$auth->getAccessLevel($this->privid_editor);

        if (in_array($data['stateid'], [PM::STATE_DISSOLUTED,PM::STATE_BLOCKED, PM::STATE_ANNUL, PM::STATE_CANCELED]) && !$b_admin) {
            AppEnv::echoError('err-blocked_or_dissoluted');
            exit;
        }
        # exit('1'.AjaxResponse::showMessage("shift=[$shifted]<pre>".print_r($this->_p,1).'</pre>')); # debug pitstop

        if (!isset($data['stmt_id'])) {
            if (AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'неверный ID полиса'];
            apppEnv::echoError('err_wrong_policy_id');
            exit;
        }
        $ok = $this->checkDocumentRights($plcid);
        if (in_array($statetxt, array('set_accepted','set_unaccepted'))) {
            $ok = (AppEnv::$auth->getAccessLevel(PM::RIGHT_ACCEPTOR) || $this->isICAdmin());
        }
        elseif ($b_admin) $ok = true; # AppEnv::$auth->getAccessLevel($this->privid_super)
        else {
            if ($data['stateid'] > PM::STATE_PAYED && !in_array($statetxt, ['cancel','annul'])) {
                exit('1' . AjaxResponse::showError('err_policy_not_editable'));
                # AppEnv::echoError('err_policy_not_editable');
            }
            # Можно только "отмену", остальные смены статуса простому операционисту недоступны
        }
        if (!$ok) {
            if (AppEnv::isApiCall()) return ['result'=>'ERROR',
              'message'=> AppEnv::getLocalized('err-no-rights-document')
            ];
            AppEnv::echoError('err-no-rights-document');
            exit;
        }

        # все статусы кроме расторж, отмен, аннуляции сбросят дату расторжения
        $upd = ['diss_date'=>'0', 'diss_zdate'=>'0','statedate'=>'{now}'];
        $logStrings = []; # допюстроки в журнал действий
        $evtype = 'SET STATE';
        $statecode= FALSE;
        $need_update = true;
        $deny_reason = '';
        $done = FALSE;
        $subLog = '';
        $updAgmtDt = []; # обновить что-то в alf_agmt_data
        # exit('1' . ajaxResponse::showMessage("set state [$statetxt]"));
        switch($statetxt) {
            case 'project':
                if($userLevel < PM::LEVEL_IC_SPECADM) {
                    AppEnv::echoError('err-no-rights');
                    exit;
                }
                $upd['stateid'] = PM::STATE_PROJECT;
                $upd['diss_reason'] = '';
                $upd['diss_date'] = '0';
                $upd['diss_zdate'] = '0';
                $upd['bpstateid'] = 0;
                $upd['bpstate_date'] = $upd['dt_sessign'] = '000-00-00';
                $upd['stmt_stateid'] = PM::STATE_FORMED;
                $upd['bptype'] = '';
                $upd['recalcby'] = 0;
                $upd['sescode'] = '';
                $upd['comission'] = 0; # сброс запрошенного АВ
                $updAgmtDt['extended_av'] = 0;
                $updAgmtDt['dt_client_letter'] = ''; # сброс отметки о посланном письмк с оригиналом!

                Acquiring::blockAllCards($this->module, $plcid);
                if($shifted) { # нажал SHIFT - полный сброс карточки договора
                    if(floatval($data['eqpayed'])==0) { # если и была оплата, не в эквайринге, можно сбросить.
                        $upd['datepay'] = 0; $upd['platno'] = '';
                    }
                    if(!AppEnv::isProdEnv()) { # на тестовых затираю даже "онлайн" платеж
                        $upd['datepay'] = $upd['eqpayed'] = 0; $upd['platno'] = '';
                    }
                    if(in_array($data['reasonid'], [PM::UW_REASON_DECLARATION,100,700,701,702]))
                        $upd['reasonid'] = 0; # сброс некоторых оснований для UW

                    $upd['pepstate'] = $upd['reinvest'] = $upd['uw_acceptedby'] =
                      $upd['substate'] = $upd['docflowstate'] = $upd['anketaid'] = '0';
                    $upd['bptype'] = '';
                    $upd['med_declar'] = $upd['billno'] = $upd['export_pkt'] = '';
                    # exit('1' . AjaxResponse::showMessage("reset, current data:<pre>".print_r($this->_rawAgmtData,1).'</pre>'));
                    $subLog = '(полный сброс)';
                    $resetPlc = 1;
                }
                break;

            case 'nextstage': # на след.этап
                # self::$debug = 1;
                if($this->_rawAgmtData['med_declar']!=='Y' || $this->_rawAgmtData['reasonid']>0) {
                    exit('1'.AjaxResponse::showError('Перевод на следующий этап на невозможен (требуется андеррайтинг либо еще не проставлено соотв-вие мед.декларации)'));
                }
                $this->checkMandatoryFiles(Events::NEXTSTAGE); # {upd/2025-04-30} теперь на этом переходе тоже могут требоваться сканы (анкета ЦБ 6886)

                # exit('1' . AjaxResponse::showMessage('nextstage: <pre>' . print_r($this->_p,1) . '</pre>'));
                if(!$this->nonlife && $this->uwCheckEvent === 'nextstage' && empty($this->_rawAgmtData['reasonid'])) {
                    # здесь делаю проверку на наличие других полисов у того же застр. и тд
                    $pref = ($this->insurer_enabled && empty($this->_rawAgmtData['equalinsured']) ) ?
                        'insd' : 'insr';
                    if ($this->multi_insured>=100) $pref = 'insr';
                    $saRisk = 0; # TODO: получить СС по риску СЛП (для инвестов - взносу)
                    $person = $this->loadIndividual($plcid,$pref,'');
                    if(self::$debug>3) exit('1' . AjaxResponse::showMessage('person for uwcheck: <pre>' . print_r($person,1) . '</pre>'));
                    # writeDebugInfo("person to check vs other plc: ", $person);
                    PlcUtils::$debug = self::$debug;
                    PlcUtils::performUwChecks('insd',$this->module, $plcid,
                      $person['fam'], $person['imia'],$person['otch'],'','',$saRisk,'',$person['birth']
                    );

                    if(self::$debug) # exit('1' . AjaxResponse::showMessage('performUwChecks done uw_reason: : '.PlcUtils::$uw_code. ' hardness:'.PlcUtils::$uw_hardness));
                        writeDebugInfo("performUwChecks done uw_reason: ", PlcUtils::$uw_code, ' hardness:' , PlcUtils::$uw_hardness);
                    /*
                    if (!PlcUtils::$uw_code && $this->multi_insured == 2 ) { # есть 2-ой застрахованный - проверим и его
                        $pref = 'insd2';
                        PlcUtils::performUwChecks('insd',$this->module, $recid,
                          rusUtils::capitalize($this->_p[$pref.'fam']),
                          rusUtils::capitalize($this->_p[$pref.'imia']),
                          rusUtils::capitalize($this->_p[$pref.'otch']),
                          $this->_p[$pref.'docser'],
                          $this->_p[$pref.'docno'],
                          0,
                          '',
                          $this->_p[$pref.'birth']
                        );
                    }
                    */
                }
                if(!empty($this->_p['shift'])) {# вывод инфы по полисам на застрахованного
                    # exit('1' . AjaxResponse::showMessage('cumul data: <pre>' . print_r($this->_p,1) . '</pre>'));
                    DataFind::checkCumLimitions($this, $plcid, TRUE);
                }
                # {upd/2023-12-06} - Новая проверка наличия полисов на застрахованных - в момент отправки на след.этап
                elseif(!PlcUtils::$uw_code && $this->uwCheckEvent === 'nextstage' && method_exists('DataFind', 'checkCumLimitions')) {
                    $result = DataFind::checkCumLimitions($this, $plcid);
                }
                if(PlcUtils::$uw_code) {
                    $upd['reasonid'] = PlcUtils::$uw_code;
                }
                # else { # проверки на других Застр. не нашли криминала
                    $upd['stmt_stateid'] = PM::STATE_FORMED;
                    $upd['stateid'] = PM::STATE_IN_FORMING;
                # }
                if($curCodir = $this->isDraftPolicyno()) {
                    $this->getRealPolicyNo($curCodir, $upd);
                    $logStrings[] = [ 'SET POLICYNO', "Полису присвоен номер ".$upd['policyno'] ];
                }
                break;
            case 'stmt_formed':
                $upd['stmt_stateid'] = $statecode = PM::STATE_FORMED;
                $upd['stateid'] = PM::STATE_POLICY; # Договор сразу - в статус "полис"
                break;
            case 'stmt_cancel':
            case 'cancel':
                $upd['stateid'] = $statecode = $upd['stmt_stateid'] = PM::STATE_CANCELED;
                $upd['diss_date'] = '{today}';
                break;

            case events::TOUW: # 'touw'
                $statecode = PM::STATE_UNDERWRITING;
                $upd['stateid'] = PM::STATE_UNDERWRITING;
                if (!empty($this->_p['reason'])) {
                    $upd['reasonid'] = $this->uw_reasonid = (($this->_p['reason']==='decl') ? $this->declar_uwcode : PM::UW_REASON_BY_ADMIN);
                    # exit('UW reason ID: '.$upd['reasonid']);
                }
                elseif(empty($this->_rawAgmtData['reasonid']))
                    $upd['reasonid'] = PM::UW_REASON_BY_ADMIN;
                break;

            case events::UWAGREED: # 'uwagreed'
                # exit("UWAGREED: userLevel=$userLevel");
                if($userLevel < PM::LEVEL_UW) AppEnv::echoError('err-no-rights');
                $upd['stmt_stateid'] = PM::STATE_FORMED; # {upd/11.11.2015}
                $upd['stateid'] = $statecode = PM::STATE_UWAGREED;
                $upd['bpstateid'] = 0; # если был ЭДО, где клиент не согласовал соротв.декларации, тут его сбрасываю!
                $upd['uw_acceptedby'] = AppEnv::getUserId(); # {upd/2020-08-14} Кашуба/Дунаев запросили выводить кем согласовано

                # $curStage = PlcUtils::DocflowGetStage($this->module, $this, $this->_rawAgmtData['export_pkt']);
                # exit('1' . AjaxResponse::showMessage("Тек.этап карточки СЭД: $curStage")); # PIT STOP

                break;

            case 'uwdenied':
                if($userLevel < PM::LEVEL_UW) AppEnv::echoError('err-no-rights');
                $upd['stateid'] = $statecode = PM::STATE_UWDENIED; # {upd/2022-09-13}
                $upd['uw_acceptedby'] = AppEnv::getUserId();
                break;

            case 'uwreqdata':  # {upd/2020-02-18}
                if($userLevel < PM::LEVEL_UW) AppEnv::echoError('err-no-rights');
                $upd['stmt_stateid'] = PM::STATE_FORMED;
                $upd['stateid'] = $statecode = PM::STATE_UW_DATA_REQUIRED;
                $upd['uw_acceptedby'] = AppEnv::getUserId();
                break;

            case Events::ANNULATED :
                if($userLevel < PM::LEVEL_IC_ADMIN) AppEnv::echoError('err-no-rights');
                $upd['stateid'] = $statecode = PM::STATE_ANNUL;
                $upd['diss_date'] = '{today}';
                break;
            case 'formed':
                if (method_exists($this, 'beforeSetFormed')) {
                    $err = $this->beforeSetFormed();
                    if (!empty($err)) {
                        if (AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>$err];
                        exit($err);
                    }
                }
                $formed = $this->setPolicyFormed($plcid);
                if ($formed) $done = TRUE;
                else {
                    $errText = $this->errorMessage;
                    if (AppEnv::isApiCall()) return ['result'=>'ERROR',
                      'message' => $this->errorMessage
                    ];
                    exit('1' . AjaxResponse::showError($this->errorMessage));
                }
                $statecode = PM::STATE_FORMED;
                // сразу проставляю и дату оплаты, если она не была проставлена раньше
                break;

            case 'set_accepted':
                if($userLevel < PM::LEVEL_IC_ADMIN) AppEnv::echoError('err-no-rights');
                $upd['accepted'] = 1;
                $upd['acceptedby'] = AppEnv::$auth->userid;
                $evtype = 'ACCEPT';
                break;
            case 'set_unaccepted':
                if($userLevel < PM::LEVEL_IC_ADMIN) AppEnv::echoError('err-no-rights');
                $upd['accepted'] = 0;
                $upd['acceptedby'] = AppEnv::$auth->userid;
                $evtype = 'UNACCEPT';
                break;
            case 'clearpayed':
                if($userLevel < PM::LEVEL_SUPEROPER) AppEnv::echoError('err-no-rights');
                if (intval($data['datepay']) == 0) {
                    $need_update = FALSE;
                    $deny_reason = 'У договора отметка об оплате еще не проставлена ';
                }
                elseif($data['stateid'] == PM::STATE_ANNUL || $data['stateid'] == PM::STATE_CANCELED) { # отмена, аннулирован
                    $need_update = FALSE;
                    $deny_reason = 'Договор в статусе ' .self::decodeAgmtState($data['stateid'], 0, 0)
                      . '.<br>Снятие отметки об оплате невозможно !';
                }
                else {
                    $upd['datepay'] = '0';
                    $upd['platno'] = '';
                    if (in_array($data['stateid'], [PM::STATE_PAYED, PM::STATE_FORMED])) {
                        # если уже в статусе оформлен, сбросить его - куда ? (на андеррайтинг?)
                        $upd['stateid'] = PM::STATE_PROJECT;
                    }

                    $evtype = "CLEAR_PAYED";
                }

                break;
        }
        # $ret = '<pre>'.print_r($this->_p).'</pre>';  exit($ret); // debug

        if ($need_update && empty($deny_reason)) {
            # if(self::$debug) writeDebugInfo("KT-001, upd: ", $upd, ' done: ', $done);
            if (!$done) {
                # $ok = AppEnv::$db->update(PM::T_POLICIES, $upd, array('stmt_id'=>$plcid)); $lineNo = __LINE__;
                $ok = PlcUtils::updatePolicy($this->module, $plcid, $upd); $lineNo = __LINE__;
                if(!$ok) {
                    AppEnv::logSqlError(__FILE__, __FUNCTION__, $lineNo);
                    exit('1' . AjaxResponse::showError('Ошибка при записи в БД!'));
                }
                if(count($updAgmtDt)) AgmtData::saveData($this->module, $plcid, $updAgmtDt);

                if($statetxt === 'project') # после сброса extended_av и прочих, перевывести основной reasonid
                    \UwUtils::updatePolicyReasonId($this->module, $plcid);

                if($resetPlc) {
                    EdoPep::cleanHistory($this->module, $plcid);
                    AgmtData::clearData($this->module, $plcid, $this->recalculable);

                    # удаляю неактуальные сканы заявления, анкет...
                    FileUtils::deleteFilesInAgreement($this->module,$plcid,PM::$clearDocsReset);

                    if(!AppEnv::isProdEnv()) {
                        Acquiring::deleteAllCards($this->module, $plcid);
                        if(AppEnv::isLocalEnv()) SmsUtils::clearLog($this->module, $plcid);
                        PlcUtils::clearLog($this->module, $plcid);
                    }

                    # очистка от инфы по авто-платежам
                    if(class_exists('AutoPayments')) AutoPayments::cleanPolicy($this->module, $plcid, TRUE);

                    if(!empty($this->_rawAgmtData['export_pkt'])) {
                        # при сбросе в нач.состояние сбросить флажки "выгружен" у всех файлов
                        AppEnv::$db->update(PM::T_UPLOADS, ['exported'=>0], ['stmt_id'=> $plcid]);
                        # отправляю письмо о необходимости аннулировать ранее созданную карточку в СЭД
                        $sentBan = PlcUtils::BanSedCardNotification($this->_rawAgmtData);
                        $instantMsg = 'Необходимо аннулировать в СЭД карточку '.$this->_rawAgmtData['export_pkt'];
                        if($sentBan) $instantMsg .= "<br>На адрес $sentBan отправлено напоминание об этом.";
                        $msgOk.= $instantMsg;
                    }
                }
                if (in_array($statetxt,array('touw','stmt_formed'))) {
                    $logtxt = 'Установлен статус : ' . self::decodeAgmtState($statecode,0,0);
                    if ($statecode == PM::STATE_UNDERWRITING)
                        $logtxt .= " / ".((isset($this->_p['reason']) && ($this->_p['reason']==='decl'))?'декларация':'админ');
                }
                elseif ( $statetxt === 'set_accepted') {
                    $logtxt = 'Произведена акцептация договора';
                    PlcUtils::setPolicyComment($this->module, $plcid, '', $this->log_pref, true);
                }
                elseif ( $statetxt === 'set_unaccepted') {
                    $logtxt = 'Отмена акцептации договора';
                }
                elseif( $statetxt == 'clearpayed') {
                    $logtxt = 'Снятие отметки об оплате';
                }
                else $logtxt = 'Установлен статус договора: ' . $this->decodeAgmtState($upd['stateid'],0,FALSE) . $subLog;
                if(PlcUtils::$uw_code) { # фиксирую код причины и ее "тяжесть"
                    AgmtData::saveUwValues($this->module, $plcid);
                    if(self::$debug) writeDebugInfo("зафиксировал в agmtdata UW-коды");
                }

                AppEnv::logEvent($this->log_pref.$evtype, $logtxt, '', $plcid);
                /* # {upd/2021-12-20} нет больше такой ф-ции - annulateDocflowCard()!
                if( $statetxt === 'annul') {
                    if (!empty($this->_rawAgmtData['docflowstate']) && $this->_rawAgmtData['export_pkt']>0) {
                        $annulDF = PlcUtils::annulateDocflowCard($this->_rawAgmtData['export_pkt'], 'Аннулирован в ALFO');
                        if ($annulDF) AppEnv::logEvent($this->log_pref.'DOCFLOW ANNUL', 'Карточка СЭД аннулирована', '', $plcid);
                        else AppEnv::logEvent($this->log_pref.'DOCFLOW ANNUL ERR', 'Не удалось аннулировать карточку СЭД', '', $plcid);
                    }
                }
                */
                if(count($logStrings)) foreach($logStrings as $oneLog) {
                    AppEnv::logEvent($this->log_pref.$oneLog[0], $oneLog[1], '', $plcid, 0, $this->module);
                }

                if (!isset($upd['reasonid'])) $upd['reasonid'] = 0;
                if ($statetxt == 'uwreqdata' && !empty($uwcomment)) {
                    $cmtResult = PlcUtils::setPolicyComment($this->module, $plcid, $uwcomment, $this->log_pref, TRUE);
                }
                if(!empty($upd['stateid']) && $upd['stateid'] == PM::STATE_UWAGREED && !empty($this->_rawAgmtData['export_pkt'])) {
                    # {upd/2022-11-02} при простановке "согласовано uw" двигаю дату X и макс.дату выпуска
                    $now = date('Y-m-d');

                    $this->finalSavings($plcid, $now);
                    # {upd/2023-03-16} - каточку в СЭД перевожу на этап "Доработка"
                    if($this->_rawAgmtData['docflowstate']>=2 && !empty($this->_rawAgmtData['export_pkt'])) {
                        # TODO: проверить тек.статус карточки перед переводом в "Доработку" ! Доаустимые: "Согласование"
                        $doSed = TRUE;
                        $curStage = PlcUtils::DocflowGetStage($this->module, $this, $this->_rawAgmtData['export_pkt']);

                        if($curStage === PM::DOCFLOW_STAGE_REWORK) {
                            $warning = 'Карточка СЭД уже на доработке, смена этапа не требуется';
                            $doSed = FALSE;
                        }
                        elseif($curStage !== PM::DOCFLOW_STAGE_UW) {
                            $warning = "Карточка СЭД в статусе $curStage, перевод на Доработку невозможен!";
                            AppEnv::logEvent($this->log_pref.'SED NO STAGE',"СЭД: Перевод карточки на Доработку невозможен,$curStage",0,$plcid);
                            $doSed = FALSE;
                        }
                        # exit('1' . AjaxResponse::showMessage("Тек.этап карточки СЭД: $curStage")); # PIT STOP
                        if($doSed) {
                            $stageResult = PlcUtils::DocflowSetStage($this->module, $this, [PM::DOCFLOW_TOREWORK]);
                            # $stageResult = PlcUtils::DocflowSetStage($this->module, $this, [PM::DOCFLOW_TONEXT,PM::DOCFLOW_TOREWORK]);
                            if(self::$debug) writeDebugInfo("sed stage result: ", $stageResult);
                            if(!isset($stageResult['result']) || strtoupper($stageResult['result']) != 'OK') {
                                $badStage = $result['stage'] ?? PM::DOCFLOW_TOREWORK;
                                $warning = "Внимание! при переводе карточки СЭД в статус [$badStage] произошла ошибка:<br>".$result['message'];
                            }
                        }
                    }
                    # else writeDebugInfo("no need to set stage Отправить на доработку _rawAgmtData: ", $this->_rawAgmtData);
                }
                if (method_exists($this, 'afterStateChange')) {
                    # {updt/2019-12-11} - добавлено для HappyHome - свой отправщик уведомления о статусе
                    # writeDebugInfo("gonna afterStateChange($plcid,[$statecode])");
                    if ($statecode !== FALSE) {
                        if (self::$debug) writeDebugInfo("calling afterStateChange...", $statecode);
                        $this->afterStateChange($plcid,$statecode, $logtxt);
                    }
                }
                else {
                    if (self::$debug) writeDebugInfo("seek notifyAgmtChange");
                    if($this->notify_agmt_change && method_exists($this, 'notifyAgmtChange') && !empty($upd['stateid'])) {
                        $this->notifyAgmtChange($logtxt, $plcid, $statetxt, $upd['stateid']);
                    }
                    elseif($this->agent_prod !== PM::AGT_NOLIFE) { # для всех кроме НЕ-Жизни -  стандартная отправка уведомл.Email
                        if (self::$debug) writeDebugInfo("agent_prod branch");
                        agtNotifier::send($this->module, $statetxt, $data);
                    }
                    else writeDebugInfo("нечем посылать сообщ-е, agent_prod = [$this->agent_prod]");
                }
                if( in_array($statecode, [PM::STATE_CANCELED, PM::STATE_ANNUL]) && AppEnv::isFavActive() ) {
                    if(UserParams::getSpecParamValue(0,PM::USER_CONFIG,'fav_auto_clear'))
                        Favority::dropViewPolicyPage($this->module, $plcid);
                }
            }
        }
        else {
            if (AppEnv::isApiCall()) return ['result'=>'ERROR',
              'message' => $deny_reason
            ];
            $msgErr = $deny_reason;
        }
        # $ret = "1\tgotourl\f./?plg={$this->module}&action=agrlist"; exit($ret);

        if (AppEnv::isApiCall() && !$msgErr)
            return ['result'=>'OK', 'message'=>"Полис $data[policyno] успешно отменен"];
        $ret = '1';
        $finalMsg = $msgOk;
        if($warning) $finalMsg .= ($msgOk ? '<br>':'') . $warning;
        if($msgErr) $ret .= AjaxResponse::showError($msgErr);
        elseif($finalMsg) $ret .= AjaxResponse::showMessage($finalMsg);
        $ret .= $this->refresh_view($plcid, TRUE);
        exit($ret);
    }

    public function getRealPolicyNo($codir, &$arUpd) {
        # получаю новый номер вместо временного
        $newNo = NumberProvide::getNext($codir,$this->_rawAgmtData['headdeptid'],$this->module,$this->policyno_len);
        if(!$newNo) exit('1' . AjaxResponse::showError("Не удалось получить очередной номер для полиса кодировки $codir, обратитесь к Администартору"));
        $arUpd['policyno'] = ($codir . '-' . $newNo); # str_pad($newNo,$this->policyno_len,'0',STR_PAD_LEFT);
    }

    public static function findAgreement($par) {
        if (!is_array($par)) return FALSE;
        $where = array();
        foreach ($par as $fldid=>$fldval) {
            if ($fldid === 'insured_fullname') $where[] = "($fldid LIKE '$fldval%')";
            else $where[$fldid] = $fldval;
        }
        $plc = AppEnv::$db->select(PM::T_POLICIES, array(
           'fields' => "stmt_id id,module,policyno,headdeptid,deptid,userid,insured_fullname insured"
              . ",currency,policy_sa sa,policy_prem premium,datefrom,datetill,stateid"
          ,'where'  => $where
          ,'singlerow' => 1)
        );

        return $plc;
    }

    /**
    * Вернет TRUE если заводится новый или редактируется полис от имени "свободного клиента" eShop
    * {upd/2019-04.18} - в настройке account_clientplc можно завести через ,; несколько ИД "eShop-ских" учеток
    * (появляются новые партнеры с продажей со своих сайтов)
    */
    public function isFreeClientPolicy() {
        if (AppEnv::isClientCall()) {
            # writeDebugInfo("isclientCall, isFreeClientPolicy returns TRUE ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            return TRUE;
        }
        if (!empty($this->_rawAgmtData['userid'])) $userid = $this->_rawAgmtData['userid'];
        elseif (!empty(AppEnv::$auth->userid)) $userid = AppEnv::$auth->userid;
        if ($userid<=0) return FALSE;
        $cliList = preg_split( '/[\s,;]/',  AppEnv::getConfigValue('account_clientplc'), -1, PREG_SPLIT_NO_EMPTY );
        if(in_array($userid, $cliList)) return TRUE;
        $usrdept = AppEnv::$db->select(PM::T_USERS, [
          'where'=>['userid' => $userid],
          'fields' => 'deptid',
          'singlerow'=>1,
          'associative' => FALSE
        ]);
        if (!$usrdept) return FALSE;
        $metaOu = OrgUnits::getMetaType($usrdept);
        if(self::$debug > 3) writeDebugInfo("isFreeClientPolicy: metaOu=[$metaOu]m trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        return ($metaOu == OrgUnits::MT_ESHOP);
    }
    # вынесено в persons.php
    public function validateIncomeSources() {
        Persons::validateIncomeSources($this);
        /*
        # если "клиентский" вызов из eShop, или редактировался полис от "свободного клиента" eShop, заполнение необязательно:
        if ($this->isFreeClientPolicy()) return;
        $chkAny = 0;
        if (!empty($this->_p['income_source_work'])) $chkAny = 1;
        if (!empty($this->_p['income_source_social'])) $chkAny = 1;
        if (!empty($this->_p['income_source_business'])) $chkAny = 1;
        if (!empty($this->_p['income_source_finance'])) $chkAny = 1;
        if (!empty($this->_p['income_source_realty'])) $chkAny = 1;
        if (!empty($this->_p['income_source_other'])) {
            $chkAny = 1;
            if (empty($this->_p['income_descr'])) $this->addCheckError('Не введен источник дохода (другие источники)', TRUE);
        }
        if (!$chkAny) $this->addCheckError('Не выбран ни один из источников дохода', TRUE);
        */
    }
    /**
    * Активирую "уведомление об андеррайтинге"
    * (договор сохраняется, но у юзера выскочит уведомление, что он в статусе "андеррайтинг"
    * с поясняющим текстом
    * @param mixed $reason_text - текст причины постановки на андеррайтинг
    * @param mixed $uwcode - код причины. Если передан, пытаюсь найти специфический файл шаблон с тестом сообщения!
    */
    public function setUwNotification($reason_text='', $uwcode='') {
        if (empty($reason_text) && empty($uwcode)) return;
        # writeDebugInfo("setUwNotification('$reason_text', $uwcode)...");
        if (!$reason_text) $reason_text = $this->warning_text;
        if (!$reason_text) $reason_text = InsObjects::getUwReasonDescription($uwcode);
        $instanttxt = "Оформление договора не может быть продолжено, причина:<br><br>" . $reason_text;
        # Файл с текстом об андеррайтинге ищу сначала в папке плагина, потом - беру "общий" из templates/

        if($this->agent_prod === PM::AGT_LIFE)
            $subst = [
              '{email}' => AppEnv::getConfigValue('lifeag_feedback_email', PM::$infoEmail),
              '{phone}' => AppEnv::getConfigValue('lifeag_feedback_phones', PM::$compPhones),
            ];
        else {
            $subst = [
              '{email}' => AppEnv::getConfigValue($this->module.'_feedback_email', PM::$infoEmail),
              '{phone}' => AppEnv::getConfigValue($this->module.'_feedback_phones', PM::$compPhones),
            ];
        }

        $subst['{comp_name}'] = \AppEnv::getConfigValue('comp_title');

        if (empty($subst['{phone}'])) $subst['{phone}'] = PM::$compPhones;
        $subst['{comp_name}'] = AppEnv::getConfigValue('comp_title'); # для ребрендинга!
        # WriteDebugInfo("found email and phones: ",$subst);
        $uwTplFiles = [];
        if ($uwcode!='') {
            $uwTplFiles[] = $this->home_folder . "html/uw_message_{$uwcode}.htm";
            if ($this->agent_prod===PM::AGT_LIFE)
                $uwTplFiles[] = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . "uw_message_agprod_{$uwcode}.htm";
            else
                $uwTplFiles[] = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . "uw_message_{$uwcode}.htm";
        }
        $uwTplFiles[] = $this->home_folder . "html/uw_message.htm";
        if ($this->agent_prod === PM::AGT_LIFE) {
            $uwTplFiles[] = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . "uw_message_agprod.htm";
        }
        else {
            $uwTplFiles[] = ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . "uw_message.htm";
        }
        $uwcontacts = '';
        foreach($uwTplFiles as $tplName) {
            if (is_file($tplName)) { $uwcontacts = file_get_contents($tplName); break; }
        }
        if ($uwcontacts == '') {
            $uwcontacts = "<br><br>Вам необходимо связаться с {comp_name} по E-mail адресу: {email} или по тел. {phones}";
        }
        $instanttxt .= strtr($uwcontacts, $subst);
        AppEnv::addInstantMessage($instanttxt, $this->module);

    }
    # ajax запрос от jQgrid на загрузку сканов к договору
    # 05.2018 - переделал под единый грид для ВСЕХ файлов скана, с выводом "типа скана" (назначение)
    public function loadScans() {
        $plcid = $this->_p['id'];
        $doctype = isset($this->_p['doctype']) ? $this->_p['doctype'] : '';
        $where = array('stmt_id'=>$plcid);
        if (!empty($doctype)) $where ['doctype'] = $doctype;
        else {
            # если нет прав СЭД/не супер-админ/фин-монитор, файл лога PEPs проверок не показывать:
            $viewPeps = $this->isAdmin() || (AppEnv::$auth->getAccessLevel([PM::RIGHT_ACCEPTOR, PM::RIGHT_DOCFLOW, 'finmonitor']) ||
               AppEnv::$auth->getAccessLevel($this->privid_editor)>=4);
            if (!$viewPeps) $where[] = "doctype<>'checklog'";
        }
        # if ($doctype == 'agmt') $where = "stmt_id=$plcid AND doctype<>'stmt'";
        $dt = AppEnv::$db->select(PM::T_UPLOADS, array('where'=>$where,'orderby'=>'id'));

        $rows = is_array($dt) ? count($dt) : 0;
        $response = array();
        $reccnt = $response['records'] = $rows;
        if(is_array($dt) && count($dt)) {
            ob_start();
            foreach($dt as $row) {
                $rid = $row['id'];
                $url = "<span role='button' class='btn btn-link' onclick=\"policyModel.openDoc($rid)\">Просмотреть</span>";
                $response['rows'][] = array(
                   'id'   => $row['id'],
                   'cell' => ($doctype === 'stmt' ? [toUtf8($row['descr']), number_format($row['filesize'],0,0,' '), to_char($row['datecreated'],1), toUtf8($url)] :
                        [toUtf8($row['descr']), $this->decodeScanType($row['doctype']),number_format($row['filesize'],0,0,' '), to_char($row['datecreated'],1), toUtf8($url)])
                );
            }
        }

        if(($console=ob_get_clean())) WriteDebugInfo('loadpolicyscans, parasite echo/errors:',$console);
        exit (json_encode($response));

    }
    public function decodeScanType($dtype) {
        # $scTypes = !empty($this->scanTypes) ? $this->scanTypes : PM::$scanTypes;
        return PlcUtils::decodeScanType($dtype);
    }

    # переобъявляемая ф-ция для получения списка обяз.сканов в полисе
    public function getMandatoryDoctypes($param = FALSE) {}
    /**
    * ajax request - Обновить видимость кнопок на просмотре заявл-я/договора, а также текущие статусы заявл-я и договора
    * @param $id - ID договора
    * @param $return_me - TRUE чтоб не делать exit() а вернуть строку ответа в вызвавший код
    */
    public function refresh_view($id=0, $return_me = FALSE) {
        # WriteDebugInfo("refresh_view(($id), ($return_me)) _p: ", $this->_p, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        $data = FALSE;
        if (is_numeric($id) && intval($id)>0) {
            # принудительно перезагружаю данные договора (после изменения!)
            $plcid = $id;
            $data = $this->loadPolicy($plcid, -1);
        }
        elseif (is_array($id)) {
            if(isset($id['stmt_id'])) {
                $data = $id; # передали готовый массив полиса
                $plcid = $id['stmt_id'];
            }
        }
        elseif(isset($this->_p['id'])) {
            $plcid = $this->_p['id'];
            $data = $this->loadPolicy($plcid, -1);
        }
        elseif(!empty($this->agmtdata['stmt_id'])) {
            # повторно загрузить, данные могли обновиться!
            $plcid = $this->agmtdata['stmt_id'];
            $data = $this->loadPolicy($plcid, -1);
        }

        if (empty($data['stmt_id'])) {
            writeDebugInfo("wrong call refresh_view ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,4));
            die ('wrong call refresh_view');
        }
        $docaccess = $this->checkDocumentRights($plcid);

        $this->buttonsVisibility($data,$docaccess);

        # $this->deptProdParams($this->module, $data['headdeptid'], $data['prodcode']);
        # writeDebugInfo("refresh_view _deptCfg: ", $this->_deptCfg);
        # writeDebugInfo("buttons states: ", $this->all_buttons);
        $ret = '';
        # writeDebugInfo("data: ", $data);

        $fromTill = 'C '. to_char($data['datefrom']) . ' по ' . to_char($data['datetill']);
        $ret .= AjaxResponse::setHtml('td_datefrom',$fromTill);
        $dateSign = to_char($this->getDateSign($data));

        if( !empty($data['date_release_max']) && PlcUtils::isDateValue($data['date_release_max'])
          && ($this->agmtdata['stateid'] < PM::STATE_ANNUL) && intval($this->agmtdata['bpstateid'])==0 ) {
            $dateSign .= ', Макс.дата выпуска полиса: <b>'.to_char($data['date_release_max']) . '</b>';
        }

        $ret .= AjaxResponse::setHtml('td_datesign', $dateSign);

        $ret .= AjaxResponse::setHtml('view_policyno', $data['policyno']);
        foreach ($this->all_buttons as $btnid=>$btn) {
            # $btnhtml .= "<span id='btn_{$btnid}' $dspl>$btn[html]</span>";
            if(!empty($btn['display'])) $ret .= AjaxResponse::show('btn_'.$btnid);
            else $ret .= AjaxResponse::hide('btn_'.$btnid);
            # $ret .= "\t$oper\f#btn_".$btnid;
        }

        if ($this->b_stmt_exist) {
            $stmt_state = $data['stmt_stateid'];
            $ret .= "\tattr\f#td_stmt_stateid\fclass\fplc_state{$stmt_state}"
                 . "\thtml\ftd_stmt_stateid\f" . $this->decodeStmtState($stmt_state);
        }

        $stateid = $data['stateid'];

        $strState = $this->decodeAgmtState($stateid,0,1);
        # WriteDebugInfo("decodeAgmtState($stateid), $data[accepted]:", $strState);
        $ret .= "\tattr\f#td_stateid\fclass\fplc_state{$stateid}"
             . "\thtml\ftd_stateid\f" . $strState;

        $comment = PlcUtils::viewPolicyComment($this->module, $plcid);
        $ret .= "\thtml\fpolicycomment\f$comment";

        $stDetails = $this->getStateDetails();
        $agmtdata = AgmtData::getData($this->module,$data['stmt_id']);
        if(PlcUtils::isDateValue($agmtdata['date_release'] ?? '')) {# обновляю дату выпуска
            # writeDebugInfo("обновляю дату выпуска ", $agmtdata['date_release']);
            $ret .= AjaxResponse::setValue('date_release', to_char($agmtdata['date_release']));
        }

        if (in_array($this->_rawAgmtData['stateid'], [PM::STATE_PROJECT,PM::STATE_DRAFT, PM::STATE_PAUSED, PM::STATE_DOP_CHECKING])
          && in_array($this->_rawAgmtData['bpstateid'],
            [PM::BPSTATE_SENTEDO, PM::BPSTATE_EDO_OK, PM::BPSTATE_EDO_NO, PM::BPSTATE_SENTPDN, PM::BPSTATE_PDN_OK, PM::BPSTATE_PDN_NO])) {

            $stDetails .= UniPep::getConfirmRequestHtml($this->module, $data['stmt_id'], $data);
        }
        if (!empty($this->_rawAgmtData['recalcby']) && $this->_rawAgmtData['recalcby'] == PM::CHANGE_BY_UW)
            $stDetails .= '<div style="font-weight:bold">'.$this->messageAboutRecalc().'</div>';

        if ($stDetails == '')
            $ret.= AjaxResponse::hide('#tr_state_details');
        else
            $ret .= AjaxResponse::setHtml('td_statedetails',$stDetails) . AjaxResponse::show('#tr_state_details');

        if ($stateid == 11 && ($data['docflowstate'] >0 || $data['accepted']>0))
            $cmtbut = '';
        else $cmtbut = PlcUtils::getCommentButton($this->module, $id);
        # WriteDebugInfo("data:", $data);  WriteDebugInfo("cmt_but:", $cmtbut);
        $ret .= "\thtml\fbtn_comment\f$cmtbut";
        # если есть сообщения для немедл.показа, послать js-команду их запроса:
        if (AppEnv::instantMessagesExist()) $ret .= "\teval\fshowInstantMessage()";
        if ( $data['stateid']>=11 && !$this->isAdmin()) $ret .= "\teval\f\$('td[id^=del_grid_]').hide()"; # запрет удаления сканов (скрываю кнопку удаления)
        if( AppEnv::isFavActive() &&  UserParams::getSpecParamValue(0,PM::USER_CONFIG,'fav_auto_clear') ) {
            # перерисовываю иконку активности Избранное
            $favState = Favority::getViewState($this->module, $id);
            if($favState) $ret .= AjaxResponse::addClass("#pagefav", 'bi-heart-fill').AjaxResponse::removeClass("#pagefav", 'bi-heart');
            else $ret .= AjaxResponse::removeClass("#pagefav", 'bi-heart-fill').AjaxResponse::addClass("#pagefav", 'bi-heart');
        }
        if ( (self::$debug >1) ) WriteDebugInfo('refresh_view response:', $ret);
        # writeDebugInfo("final refresh cmd: ", $ret);
        if ($return_me) return $ret;
        exit("1" . $ret);
    }
    # строка о наличии факта пеперасчета
    public function messageAboutRecalc() {
        if($this->_rawAgmtData['recalcby'] == PM::CHANGE_BY_UW) return "Был изменен андеррайтером";
        # return 'По договору был сделан перерасчет';
        return '';
    }
    public function getStateDetails() {
        $ret = '';
        if (!empty($this->_rawAgmtData['bptype']) && $this->_rawAgmtData['bptype'] === PM::BPTYPE_EDO)
            $ret .= '[ЭДО] ';
        $this->paused = PlcStates::isPaused($this, $this->_rawAgmtData);
        $plcid = $this->_rawAgmtData['stmt_id'];
        # writeDebugInfo("paused: [$this->paused]");
        if ($this->paused)
            $ret .= PlcStates::getPauseDescription($this->paused, $plcid, $this->isAdmin());
        if (!empty($this->_rawAgmtData['reinvest']))
            $ret .= Reinvest::getStateHtml($this->_rawAgmtData['reinvest']);
        # скопировано из lifeag:
        if ($this->_rawAgmtData['stateid']< PM::STATE_UNDERWRITING && $this->_rawAgmtData['stateid']!=PM::STATE_IN_FORMING) {
            $uwReasons = \UwUtils::getAllReasons($this->module,$plcid, TRUE); # light=>N1, hard=>N2
            $uwComment = '';
            if ( !empty($uwReasons) ) { # $this->_rawAgmtData['reasonid']>0 || $this->_rawAgmtData['med_declar'] ==='N' || $this->agmtdata['extended_av']>0
                $uwDet = '';

                if($uwReasons['light']>0 && $this->_rawAgmtData['stateid']!=PM::STATE_DOP_CHECK_DONE)
                    $uwDet = 'доп.проверки';
                if( !empty($uwReasons['hard']) ) {
                    $uwDet .= ($uwDet ? ' / ':'') . 'андеррайтинга';
                    if(method_exists($this, 'uwIsBlocking')) {
                        $uwBlock = $this->uwIsBlocking($this->_rawAgmtData);
                        if($uwBlock) $uwComment = ' По программе не предусмотрен индивидуальный анедррайтинг';
                    }
                }
                $uwRet = 'Договор требует '.$uwDet;
                if($uwDet)
                    $ret .= "<a href='javascript:void(0)' onclick='plcUtils.viewAllUwReasons()'>$uwRet</a>$uwComment";
            }
            # для наших сотрудников вывожу про PEPs
            /*
            if($this->_rawAgmtData['pepstate']==101 && $this->_userLevel>=PM::LEVEL_IC_ADMIN)
                $ret .= ($ret ? ', ':'') . '(в списках PEPs)';
            */
            # if ($this->_rawAgmtData['pepstate']>100)
            #    $ret.= '<div>Страхователь или Застрахованный не прошли проверку Комплаенс</div>';
            /****
            if(!empty($this->_rawAgmtData['reasonid'])) {
                if($this->_rawAgmtData['reasonid'] == PM::UW_REASON_EXT_AV) {
                    $reason = InsObjects::getUwReasonDescription($this->_rawAgmtData['reasonid'], $this->_rawAgmtData);
                    $ret .= "<div>$reason</div>";
                }
                elseif (!empty($this->calc['warnings']))
                    $ret .= '<div>' . implode('<br>',$this->calc['warnings']) . '</div>';
                else {
                    $reason = InsObjects::getUwReasonDescription($this->_rawAgmtData['reasonid'], $this->_rawAgmtData);
                    $ret .= "<div>$reason</div>";
                }
            }
            if ($this->_rawAgmtData['med_declar'] ==='N' && !in_array($this->_rawAgmtData['reasonid'],[PM::UW_REASON_DECLARATION,PM::UW_REASON_EXT_AV]))
                $ret .= '<div>Застрахованный не соответствует мед.декларации</div>';
            ***/
        }
        elseif(in_array($this->_rawAgmtData['stateid'], [PM::STATE_UNDERWRITING, PM::STATE_UWAGREED, PM::STATE_COMPLIANCE_CHECK])) {
            # {upd/2023-12-19}
            # для наших сотрудников вывожу про PEPs
            $ret .= "<a href='javascript:void(0)' onclick='plcUtils.viewAllUwReasons()'>См.основания для проверки/андеррайтинга</a>";
            /*
            if($this->_userLevel>=PM::LEVEL_IC_ADMIN) {
               if($this->_rawAgmtData['pepstate']==101) $ret .= ($ret ? ', ':'') . '(в списках PEPs)';
               elseif($this->_rawAgmtData['pepstate']>101) $ret .= ($ret ? ', ':'') . '(не пройдены проверки Комплайнс)';
            }
            if($this->_rawAgmtData['reasonid'] == PM::UW_REASON_EXT_AV)
                $ret .= '<div>' . AppEnv::getLocalized('comment_extended_av') . '</div>';
            */
        }
        /*
        if($this->_rawAgmtData['stateid']==PM::STATE_FORMED && $this->_rawAgmtData['substate'] == PM::SUBSTATE_COMPLIANCE)
            $ret .= "<div class='alarm'>Доработка: После изменения данных не пройдена проверка Комплайнс!</div>";
        */
        # writeDebugInfo("statedetails ", $ret);
        return $ret;
    }
    # Пришел запрос на чтение (загрузку в браузер клиенту) файла скана к договору
    public function openDoc($params=0) {
        if(!is_array($params)) $params = $this->_p;
        FileUtils::openFile($params);
    }

    # Изменение - (пока только удаление) скана док-та
    public function updtScan($params = 0) {
        # if ($this->debug) WriteDebugInfo('updtagrscan params:', $this->_p);
        #TODO: проверять разрешение оператору на работу с полисом !!!
        $pars = is_array($params) ? $params : $this->_p;
        return FileUtils::updateFile($pars);
    }

    # проверяю наличие аналогичного файла по его размеру и MD5 сумме
    public function fileAlreadyInPolicy($policyid, $fsize, $md5sum) {
       $dta = AppEnv::$db->select(PM::T_UPLOADS, array('where'=>"stmt_id=$policyid"));
       if(is_array($dta) && count($dta)>0) {
           foreach($dta as $finfo) {
               $fname = ALFO_ROOT . $finfo['path'] . $finfo['filename'];
               if(is_file($fname) && filesize($fname)==$fsize && md5_file($fname) === $md5sum) return TRUE;
           }
       }
       return FALSE;
    }


    /**
    *  пришел файл со сканом - надо занести в систему!
    * @param mixed $params, по умолчанию - вызов из AJAX, все данные в AppEnv::$_p, $_FILES
    * но если надо залить файл из другого модуля - передан массив:
    * 'doctype' => doc_type, 'filename' => "file.ext', 'filebody' OR 'fullpath' - для передачи содержимого
    */
    public function addScan($params=0, $skipCheck = FALSE, $sysAction = FALSE) {
        # перенесен в fileutils.php
        if(self::$debug) writeDebugInfo("addScan params: ", $params);
        $ret = FileUtils::addScan($params, $skipCheck, $this->_rawAgmtData, $sysAction);
        return $ret;
    }

    # AJAX-запрос с формы редактирования на проверку клиента по базе террористов
    # {upd/2024-06-06 - перенос в applists.php::finmonCheck()
    public function fmon_check() {
        AppLists::finmonCheck();
    }

    # AJAX-запрос от грида "история действий с договором", возвращаю json - записи
    public function getAgrHistory() {

        if (empty(AppEnv::$_p['prefix'])) AppEnv::$_p['prefix'] = $this->log_pref;
        PlcUtils::getAgrHistory();
    }

    public function getDebugInfo($module='', $id=0) {
        # WriteDebugInfo('getdebuginfo:', AppEnv::$_p);
        $id = isset($this->_p['id']) ? $this->_p['id'] : 0;
        $calcid = isset($this->_p['calcid']) ? $this->_p['calcid'] : 0;
        $postfix = $this->mtpost;
        if (!AppEnv::$auth->IsSuperAdmin()) exit ('You are denied');
        if ($id === 'calc') {
            $dta = 0;
            if ($calcid)
                $alldta = array(
                    'calc_params'=> $_SESSION[$calcid]['calcdata_'.$postfix]
                   ,'spec_params'=> $_SESSION[$calcid]['srv_'.$postfix]
                   ,'fin_plan'   => $_SESSION[$calcid]['finplan_'.$postfix]
                );
            else
                $alldta = array(
                    'calc_params'=> $_SESSION['calcdata_'.$postfix]
                   ,'spec_params'=> $_SESSION['srv_'.$postfix]
                   ,'fin_plan'   => $_SESSION['finplan_'.$postfix]
                );
        }
        else {
            $dta = $this->loadPolicy($id);
            $alldta = $this->loadSpecData($id);
        }
        $ret = "<div style='overflow:auto; height:600px'>main Policy data:<pre>" . print_r($dta,1) . '</pre>';
        if (isset($alldta['spec_params'])) $ret .= 'spec data:<pre>' . print_r($alldta['spec_params'],1) . '</pre>';
        if (isset($alldta['calc_params'])) $ret .= 'calc params:<pre>' . print_r($alldta['calc_params'],1) . '</pre>';
        if (isset($alldta['ins_params'])) $ret .= 'ins/srv params:<pre>' . print_r($alldta['ins_params'],1) . '</pre>';
        # file_put_contents('_fin_plan.log', print_r($alldta['fin_plan'],1));
        if ( isset($alldta['fin_plan']) && is_array($alldta['fin_plan'])) {
            if(isset($alldta['fin_plan'][1]) ) {
                $ret .= '<table class="zebra"><tr>';
                # $k1 = array_keys($alldta['fin_plan']);
                foreach(array_keys($alldta['fin_plan'][1]) as $fid) {
                    $ret .= "<th>$fid</th>";
                }
                // $ret .= '<tr><td>' . $alldta['fin_plan']. '</td></tr>';
                foreach( $alldta['fin_plan'] as $no=>$item) {

                    if(is_array($item)){
                        $ret .= '<tr><td>' . implode('</td><td>',$item). '</td></tr>';
                    } else{
                        $ret .= '<tr><td>' . $item . '</td></tr>';
                    }

                }
                $ret .= '</table>';
            }
            elseif(is_array($alldta['fin_plan']) && count($alldta['fin_plan'])) {
                $ret .= "Фин-план/детали расчета:<pre>". print_r($alldta['fin_plan'],1). '</pre>';
            }
        }
        $ret .= '</div>';
        exit (($ret));
    }
    /**
    * Беру из таблицы alf_product_config набор констант для печати номера/даты приказа, должности/ФИО/доверенности
    * подписанта от СК...
    * @param mixed $module ИД плагина
    * @param mixed $codirovka кодировка продукта
    * @since 1.46 : беру ID подписанта/штампов из полиса, если задан
    */
    public function getBaseProductCfg($module='',$codirovka='') {
        $ret = \AppLists::getBaseProductCfg($this, $module,$codirovka);
        return $ret;
    }

    /**
    * получаем из справочника alf_dept_product список кодировок доступных программ для "головного" подразделения
    *
    * @param mixed $deptid
    */
    public function getAvailablePrograms($deptid=0) {
        if(!is_scalar($deptid)) $deptid = 0;
        $hdept_id = OrgUnits::getPrimaryDept($deptid);
        $ret = array();
        $dta = AppEnv::$db->select(PM::T_DEPT_PROD,
          array(
             'where'=>array('module'=>$this->module,'deptid'=>$hdept_id,'b_active'=>1)
            ,'associative'=>1
          )
        );

        if (AppEnv::isTestingMode()) {
            $sql = AppEnv::$db->getLastQuery();
            # echo "SQL result/$sql/(primary dept is $hdept_id):<pre>". print_r($dta,1).'</pre>';
            WriteDebugInfo("SQL: ", $sql);
        }

        if (is_array($dta)) foreach ($dta as $item) {
            if (empty($item['prodcodes'])) {
                foreach(self::$_programs as $prgname=>$arr) {
                    $ret[] = $arr[0]; # кодировка из массива "всех имеющихсмя кодировок"
                }
                return $ret;
            }
            else
                $codes = explode(',', $item['prodcodes']);

            foreach($codes as $onecode) {
                if (!empty($onecode) && !in_array($onecode, $ret)) $ret[] = $onecode;
            }
        }
        return $ret;
    }

    /**
    * Получить настроенный процент комиссии для продукта(плагина)/кодировки (подразделения)
    *
    * @param mixed $module
    * @param mixed $codirovka
    * @param mixed $deptid
    * @since 1.9:
    * @param mixed $factor - значение доп.фактора (если не 0, сравнивается с comthresh,
    * и если $factor>=comthresh, берется значение comission2 (комиссия при достижении порогового значения
    * @param $paytype - вид оплаты ('E' или 'SR' - единоврем, остальное ('R','RP')- в рассрочку
    * @param $term - срок страхования, лет
    */
    public static function getComissionPercent($module, $codirovka='', $deptid=0, $factor=0, $paytype='E',$term=0) {

        if (self::$debug>2) WriteDebugInfo("getComissionPercent($module, ".print_r($codirovka,1).", $deptid, $factor, $paytype,$term)...");
        $chcode = (is_array($codirovka)? implode('-',$codirovka) : $codirovka);
        $cacheId = "$module/$chcode/$deptid/$factor/$paytype/$term";
        $ret = AppEnv::getCached('comprc', $cacheId);
        if ($ret !== null) return $ret;
        $hdept_id = OrgUnits::getPrimaryDept($deptid);
        $where = array( 'deptid'=>$hdept_id, 'module'=>$module );
        # для тестовой учетки доступны все, включая заблокированные)
        if(!$deptid && !AppEnv::isTestAccount())
            $where['b_active'] = 1;

        $dta = AppEnv::$db->select(PM::T_DEPT_PROD,
            array('where'=>$where,'associative'=>1, 'singlerow'=>1,'orderby'=>'id')
        );
        if (self::$debug>2) {
            WriteDebugInfo("getComission SQL:", AppEnv::$db->getLastquery());
            WriteDebugInfo("getComission SQL result : ", $dta);
            if ($sqlerr=AppEnv::$db->sql_error()) WriteDebugInfo("SQL error:", $sqlerr);
        }
        # if ($deptid == 3097) AppEnv::appendHtml("dept product dta:<pre>" . print_r($dta,1). '</pre>');
        $ret = isset($dta['comission']) ? $dta['comission'] : FALSE;
        if (!empty($factor) && !empty($dta['comthresh'])
            && $factor >= $dta['comthresh'] && !empty($dta['comission2']))
            $ret = $dta['comission2'];

        if (!empty($dta['termcomission'])) {
            # есть строка задания комиссии от лет рассрочки, вида
            if ($paytype==='E') {
                $pattern = "/\bSP.[0-9]{1,}=[.0-9]{1,}\b/i";
            }
            else {
                $pattern = "/\bRP.[0-9]{1,}=[.0-9]{1,}\b/i";
            }

            $result = preg_match_all($pattern,$dta['termcomission'],$matches);
            if (!empty($matches[0]) && count($matches[0])) foreach($matches[0] as $item) {
                $splt = explode('=', $item);
                if (count($splt)<1) continue;
                $maxterm = intval(substr($splt[0],3)); # "RP.30" => 30

                if ($term <= $maxterm) {
                    $ret = floatval($splt[1]);
                    # AppEnv::appendHtml ("comission for term $term (in limit $maxterm): $ret");
                    break;
                }
            }
        }
        AppEnv::setCached('comprc', $cacheId, $ret);
        # WriteDebugInfo("comission for $module/$hdept_id/[$codirovka]/paytype=$paytype, term=$term = ", $ret);
        return $ret;
    }

    /**
    * Форма выбора отчета...
    *
    */
    public function reports() {
        $rtitle = $this->module.':title_reports';
        $cfg = PlcUtils::deptProdParams($this->module);
        # exit('data: <pre>' . print_r(AppEnv::$_plugins[$this->module],1) . '</pre>');
        if (!empty($cfg['visiblename'])) {
            $rtitle = AppEnv::getLocalized('title_reports') . " " . $cfg['visiblename'];
        }
        elseif(method_exists(AppEnv::$_plugins[$this->module], 'ProgramTitle'))
            $rtitle = AppEnv::getLocalized('title_reports') . " " . AppEnv::$_plugins[$this->module]->$this->ProgramTitle();
        elseif(method_exists(AppEnv::$_plugins[$this->module], 'getVisibleProductName'))
            $rtitle = AppEnv::getLocalized('title_reports') . " " . AppEnv::$_plugins[$this->module]->getVisibleProductName();
        AppEnv::setPageTitle($rtitle);
        $body = file_get_contents(ALFO_ROOT . AppEnv::FOLDER_TEMPLATES . 'reportlist.htm');
        $options = '';

        if (SuperAdminMode()) $acclev = 10;
        else
            $acclev = AppEnv::$auth->getAccessLevel($this->privid_reports);

        if ($acclev < 1) AppEnv::echoError('err-no-rights');

        if ($acclev == PM::LEVEL_IC_ADMIN) {
            $options .= "<option value='rep_exportall'>".self::$report_types['rep_exportall']['title'].'</option>';
        }
        else {
            if ($acclev >= PM::LEVEL_OPER) $options .= "<option value='rep_export'>".self::$report_types['rep_export']['title'].'</option>';
            if ($acclev >= PM::LEVEL_MANAGER) $options .= "<option value='rep_exportall'>".self::$report_types['rep_exportall']['title'].'</option>';
            # if ($acclev >= PM::LEVEL_MANAGER) $options .= "<option value='rep_salesall'>".self::$report_types['rep_salesall']['title'].'</option>';
            # Отчет по продажам - больше не показываю !
        }

        # Появляются плагины с поддержко спец-форматов выгрузки - для импорта в Лизу !
        if ($acclev >= PM::LEVEL_SUPEROPER && $this->report_to_lisa) {
            $options .= "<option value='rep_tolisa'>Отчет для загрузки в LISA</option>";
        }
        if (constant('IN_BITRIX')) AppEnv::appendHtml("<h4>$rtitle</h4>");
        $body = str_replace(array('<!-- report_options -->','%module%'), array($options, $this->module), $body);
        AppEnv::appendHtml($body);

        AppEnv::finalize();
    }
    public function getStdReportConfigName() {
        # 1) {upd/2024-01-36} ищу настройку осн.отчета в папке плагина
        $xmlConfig = AppEnv::getAppFolder('plugins/'.$this->module) . 'policyReport.xml';
        if(is_file($xmlConfig)) return $xmlConfig;

        # 2) ищу файл с именем policyReport.module.xml в осн.папке настроек отчетов
        $xmlConfig = AppEnv::getAppFolder('cfg-reports') . 'policyReport.' . $this->module . '.xml';
        if (is_file($xmlConfig)) return $xmlConfig;

        # ничего не нашел, беру стандартный конфиг отчета
        $xmlConfig = AppEnv::getAppFolder('cfg-reports') . 'policyReport.xml';
        return $xmlConfig;
    }
    /**
    * новый отчетник - с помощью flexreport - замена для reports + stdreport
    *
    */
    public function freports() {
        include_once('flexreport.php');
        $xmlConfig = $this->getStdReportConfigName();
        $cfg = PlcUtils::deptProdParams($this->module);
        $reporter = new \SelifanLab\FlexReport($xmlConfig, $this);
        $reporter->setConfigFolder(AppEnv::getAppFolder('cfg-reports'));

        $cfg = AppEnv::getLocalized(PlcUtils::deptProdParams($this->module));
        if (!empty($cfg['visiblename'])) {
            $rtitle = AppEnv::getLocalized('title_reports') . " " . $cfg['visiblename'];
        }
        else {
            $rtitle = $this->reportGetTitle();
            # $rtitle = AppEnv::getLocalized($this->module.':title_reports');
        }

        AppEnv::setPageTitle($rtitle);
        if (constant('IN_BITRIX')) AppEnv::appendHtml("<h4>$rtitle</h4>");
        else AppEnv::appendHtml('<br>');
        $body = $reporter->fullForm("./?plg={$this->module}&action=runpolicyreport");
        AppEnv::appendHtml($body);
        AppEnv::finalize();
    }
    public function runPolicyReport() {
        # echo "params : <pre>".print_r(AppEnv::$_p,1).'</pre>'; exit;
        # reporttype : rep_export | rep_exportall
        include_once('flexreport.php');
        # $xmlConfig = AppEnv::getAppFolder('cfg-reports') . 'policyReport.xml';
        $xmlConfig = $this->getStdReportConfigName();
        $reporter = new \SelifanLab\FlexReport($xmlConfig, $this);
        $this->reportDept = 0;
        if (SuperAdminMode()) $acclev = 10;
        else
            $acclev = AppEnv::$auth->getAccessLevel($this->privid_reports);
        if ($acclev < 1) exit('Error');
        if ($acclev <=3 || AppEnv::$_p['reporttype'] === 'rep_export') {
            $this->reportDept = OrgUnits::getPrimaryDept();
        }
        if($acclev == 4) $this->reportChannel = OrgUnits::getMetaType();

        $this->reportLevel = $acclev;
        if ($this->reportDept >0) {
            $deptReq = OrgUnits::getOuRequizites($this->reportDept);
            if (!empty($deptReq['ouparamset'])) {
                $xmlOuSet = OrgUnits::getOuParamSetFile($deptReq['ouparamset']);
                $ouDef = new ParamDef($xmlOuSet);
                $this->_repOuFields = $ouDef->getParams();
                if (isset($this->_repOuFields['fields']) && is_array($this->_repOuFields['fields'])) foreach($this->_repOuFields['fields'] as $fname=>$fdef) {
                    $fopt = [
                      'title' => $fdef['label'],
                      'width' => (isset($fdef['width']) ? $fdef['width'] : 14),
                      'format' => $fdef['format']
                    ];
                    $reporter->addField($fname, $fopt);
                }
            }
        }
        $result = $reporter->execute(AppEnv::$_p);
        if ($result) { # отчет в html формате
            AppEnv::appendHtml($result);
            AppEnv::finalize();
        }
        exit;
    }

    # callback, вызывается из flexreports для станд.отчета по полисам - получение SQL запроса
    public function getPolicyReportQuery($pars = []) {
        $userFilter = $filters = '';
        if (SuperAdminMode()) $acclev = 10;
        else
            $acclev = AppEnv::$auth->getAccessLevel($this->privid_reports);

        $where = ["(module='$this->module')"];

        # {upd/2023-10-09} отбор только полисов своего канала (ПП агенты, ПП банки)
        if($this->reportChannel > 0) {
            $userFilter = "(metatype=".$this->reportChannel . ')';
        }
        elseif ($this->reportDept > 0) {
            $userFilter = $this->agmtDeptFilter();
        }

        if ($pars['reporttype'] === 'rep_export') # только свои
            $userFilter = "(userid='".AppEnv::$auth->userid . "')";
        if ($userFilter) $where [] = $userFilter;
        if (!empty($pars['datefrom'])) $where[] = "(created >= '" . to_date($pars['datefrom']) . "')";
        if (!empty($pars['datetill'])) $where[] = "(created <= '" . to_date($pars['datetill']) . "')";
        $where[] = "(b_test=0)"; # no test polisies in report

        $filters = implode(' AND ', $where);
        $ret = "SELECT metatype,stmt_id,userid,policyno,created,insurer_fullname,insured_fullname,rassrochka,datefrom, datetill, prodcode, '' `benefs`, currency, term, policy_prem "
          . ",policy_sa,policy_prem_rur, 'risks' risks, stateid,bpstateid,bpstate_date,'tabnomer' tabnomer, deptid,headdeptid,datepay,diss_date,accepted,coucheid,seller " # , dt.date_release
          . " FROM alf_agreements p"
          # ." LEFT JOIN alf_agmt_data dt ON (dt.policyid=p.stmt_id AND dt.module=p.module)" # если захотят дат выпуска
          . " WHERE $filters"
          . " ORDER BY stmt_id";
        # echo "getPolicyReportQuery: $ret<br><pre> <br>".print_r($pars,1).'</pre>'; exit;
        return $ret;
    }

    # callback для формирования заголовка в отчет по полисам/договорам
    public function reportGetTitle() {
        $plg =& AppEnv::$_plugins[$this->module];
        if(method_exists($plg, 'getVisibleProductName')) $pgName = $plg->getVisibleProductName();
        elseif(method_exists($plg, 'ProgramTitle')) $pgName = $plg->ProgramTitle();
        elseif(method_exists($plg, 'getModuleName')) $pgName = $plg->getModuleName();
        else $pgName = $this->module;
        # writeDebugInfo("reportGetTitle, params ", AppEnv::$_p);
        if(!empty(AppEnv::$_p['datefrom']) && PlcUtils::isDateValue(AppEnv::$_p['datefrom']))
            $pgName .= ' c '.AppEnv::$_p['datefrom'];

        if(!empty(AppEnv::$_p['datetill']) && PlcUtils::isDateValue(AppEnv::$_p['datetill']))
            $pgName .= ' до '.AppEnv::$_p['datetill'];

        return ("Отчет по договорам $pgName");
    }
    # callback для flexreport - получаем недостающие данные полиса
    public function preparePolicyReportRow($row) {
        include ( __DIR__ . '/policymodel.preparepolicyreportrow.php');
        return $row;
    }

    public function getPolicyReportTypes() {
        if (SuperAdminMode()) $acclev = 10;
        else
            $acclev = AppEnv::$auth->getAccessLevel($this->privid_reports);

        if ($acclev < 1) return [];

        $ret = [ ['rep_export', self::$report_types['rep_export']['title']] ];
        if ($acclev >= PM::LEVEL_MANAGER) $ret[] = [ 'rep_exportall', self::$report_types['rep_exportall']['title']];
        if ($acclev >= PM::LEVEL_SUPEROPER && $this->report_to_lisa) {
            $ret[] = ['rep_tolisa', 'Отчет для загрузки в LISA'];
        }
        return $ret;
    }
    # {upd/2021-02-08}
    public function isAgentProduct() { return $this->agent_prod; }
    /**
    * Стандартный метод формирования массива для экспорта договора в XML для ЛИЗы
    *
    */
    public function getAgreementForExport($plcid) {
        $debugMe = FALSE;
        $plcExp = $this->loadPolicy($plcid, 'export');
        # exit(__FILE__ .':'.__LINE__.' init $plcExp:<pre>' . print_r($plcExp,1) . '</pre>');

        if (self::$debug>=4) {
            file_put_contents("_insured.log", print_r($this->insured,1));
            file_put_contents("_insured2.log", print_r($this->insured2,1));
        }
        $plc = $this->_rawAgmtData;
        # $specFld = ['child_delegate','year_salary','tax_rezident'];
        # exit(__FILE__ .':'.__LINE__.' spec_fields:<pre>' . print_r($this->spec_fields,1) . '</pre>');
        foreach($this->spec_fields as $spc) { # заношу спец-поля, для формир.доп.параемтров выгрузки
            if(isset($plcExp[$spc])) $plc[$spc] = $plcExp[$spc];
        }
        if(isset($plcExp['year_salary'])) $plc['year_salary'] = $plcExp['year_salary'];
        if(isset($plcExp['tax_rezident'])) $plc['tax_rezident'] = $plcExp['tax_rezident'];
        # {upd/2021-01-18} включен ли режим выгрузки по номерам агентских АД? если да и у юзера есть АД, передаем его для XML
        # агентский продукт, пытаюсь использовать номер агента (АД) из учетки в качестве agentno для генерации <Broker BrokerId="NN"/>
        $lisaExpMode = AppEnv::getconfigValue('lifeag_lisaexport');
        if (!empty($this->agent_prod) && $lisaExpMode == '1') {
            $agentNo = CmsUtils::getUserAgentNo($plc['userid']);
            if (!empty($agentNo)) $plc['agentno'] = $agentNo;
        }
        # $plc['paymentfrequency'] = 'E'; # единоразовый платеж
        # E - единоврем ,Y- раз в год, «H» - раз в полгода, «Q» - раз в квартал, «M» - ежемесячно
        # echo 'calc:<pre>'.print_r($this->calc,1) . '</pre>';
        # echo 'spec_params:<pre>'.print_r($this->spec_data,1) . '</pre>';
        $fldOplata = '';
        if (isset($this->_rawAgmtData['rassrochka'])) $fldOplata = $this->_rawAgmtData['rassrochka'];
        elseif (isset($this->spec_params['rassrochka'])) $fldOplata = $this->spec_params['rassrochka'];
        elseif (isset($this->calc['m1'])) $fldOplata = $this->calc['m1'];
        # TODO: прочие варианты упаковки рассрочки
        /*
        switch ($fldOplata) {
            case '12': case 'yearly': $plc['paymentfrequency'] = 'Y'; break;
            case '6': case 'half-yearly': $plc['paymentfrequency'] = 'H'; break;
            case '3': case 'quarterly': $plc['paymentfrequency'] = 'Q'; break;
            case '1': case 'monthly': $plc['paymentfrequency'] = 'M'; break;
        }
        */
        $plc['paymentfrequency'] = PlcExport::makePeriodicity($fldOplata);

        # echo "plc['paymentfrequency']: $plc[paymentfrequency]";  exit;
        # WriteDebugInfo("policy specfields: ", $this->spec_fields);
        # writeDebugInfo("plc array: ", $plc);

        $plc['issuedate'] = $plc['created']; // $plc['datepay']; {upd/2020-02-11} Овсянникова Ирина
        $agmtData = AgmtData::getData($this->module,$plcid);
        # $plc['currency'] = $plc['currency_raw'];
        $persCount = 0; # счетчик ФЛ в списке
        $childRowno = 0; # ID ФЛ "застрахованного ребенка"
        $secondInsuredRowno = 0; # ID ФЛ "2-го застрахованного"
        $plc['insuranceschemeid'] = 0;
        $plc['subtype_code'] = ''; # Подтип программы страхования/ - для XML поля InsuranceSchemeSubtypeCode

        # {upd/2020-02-07} есть особый subtypecode для LISA ? - а выдать его!
        if(method_exists($this, 'getExportSubTypeCode'))
            $plc['subtype_code'] = $this->getExportSubTypeCode($plc);

        # LISA: код может быть не занесен в LISA.INSURANCESCHEMESUBTYPE, и тогда его передача в XML приведет к отказу импорта!
        # $plc['subtype_code'] = $plc['programid'];
        # WriteDebugInfo("this: getLisaInsurancescheme exist: [".method_exists($this, 'getLisaInsurancescheme') . ']');
        $lisaCode = $prodcode = $this->_rawAgmtData['prodcode'];

        # $lisaCode - по этой кодировке ищу настройку выгрузки рисков
        if(method_exists($this, 'getLisaModifiedCode')) {
            # {upd/2021-07-05} - В программе 2в1 будут 2 раздельные настройки в зависимости от осн.Застрахованного!
            $specCodir = $this->getLisaModifiedCode($lisaCode);
            if (!empty($specCodir)) $lisaCode = $specCodir;
        }
        if(!empty($this->b_generate_xml)) {
            if (method_exists($this, 'getLisaInsurancescheme')) {
                $plc['insuranceschemeid'] = $this->getLisaInsurancescheme($plcid);
            }
            else $plc['insuranceschemeid'] = self::getExportInsuranceScheme($this->_rawAgmtData, $lisaCode);

            if (!$plc['insuranceschemeid']) {
                $errMsg = "Для продукта/кодировки $lisaCode не найдена настройка выгрузки в {$this->b_generate_xml}<br>Генерация XML файла выгрузки невозможна !";
                if(AppEnv::isAjax())
                    exit('1' . AjaxResponse::showError($errMsg));
                if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=> $errMsg];
                AppEnv::echoError($errMsg);
                exit;
            }
        }

        $risks = $this->loadPolicyRisks($plcid, 'export');

        if (self::$debug) writeDebugInfo("risks for export: ", $risks);
        # echo 'risks for export <pre>' . print_r($risks,1). '</pre>';
        # при наличии метода modifyRisksBeforeExport подвергаю риски корректировке перед экспортом
        if (method_exists($this, 'modifyRisksBeforeExport')) {
            $this->modifyRisksBeforeExport($risks);
            # echo 'modified risks for export <pre>' . print_r($risks,1). '</pre>';
        }
        # @file_put_contents("_pm_risks.log", print_r($risks,1)); # debug
        # WriteDebugInfo("risks for export:", $risks);

        $plc['benefrisks'] = $this->benefRisks = $this->getBenefAddRisks($prodcode);
        # WriteDebugInfo("$prodcode: benef risks: ", $this->benefRisks); // [0] => death_any_delay [1] => death_acc_addcover
        # exit ( 'benef risks:<pre>'.print_r($this->benefRisks,1) . '</pre>');
        # TODO: генерить матрицу раздачи процентов по выгодоприобретателям - benef.percent, percent2,percent3
        $plc['benef'] = $this->_rawBenefs;

        if (!empty($this->child_vp)) $plc['child_vp'] = $this->child_vp; # особые режимы выг-получателя по детям

        if (is_array($plc['benef']) && count($plc['benef'])) foreach($plc['benef'] as &$item) {
            $item['adr_district'] = $item['adr_region'];
            $item['adr_region'] = $item['adr_country']; // adr_country - код региона из справ-ка
            $item['fadr_district'] = $item['fadr_region'];
            $item['fadr_region'] = $item['fadr_country']; // adr_country - код региона из справ-ка
            unset($item['adr_country'],$item['fadr_country']);
            # writeDebugInfo("final benef row: ", $item);
        }
        $plc['cbenef'] = $this->_rawcBenefs;

        if ($this->_debugExp) {
            # echo ("export groups: <pre>" . print_r($expgroups,1). '</pre>');
            echo 'risks in policy:<pre>'.print_r($risks,1) . '</pre>';
            echo 'raw risks in policy:<pre>'.print_r($this->_rawPolicyRisks,1) . '</pre>';
        }

        if(!empty($this->b_generate_xml)) {
            # Делаю массив рисков под формат ЛИЗы
            $lisaRisks = array();
            foreach($risks as $rskid => &$rsk) { // [0] => endowment,[1] => SA, [2] => premium
                $rsktype = isset($rsk['riskid'])? $rsk['riskid'] : $rskid;
                # $rskname = isset($rsk['riskename'])? $rsk['riskename'] : ''; # was rtype
                $groupid = isset($rsk['riskgroupid'])? $rsk['riskgroupid'] : '1';
                if (!isset($rsk['riskamount']) && !isset( $rsk[1])) {
                    WriteDebugInfo("странные данные о риске-нет riskammount,1: [$rskid]=", $rsk);
                    continue;
                }
                $rsa = (isset($rsk['riskamount']) ? $rsk['riskamount'] : $rsk[1]) * 100;
                $rprem = (isset($rsk['payamount']) ? $rsk['payamount'] : $rsk[2]) * 100;
                  if (!isset($lisaRisks[$groupid])) {
                    # WriteDebugInfo("adding lisa riks: ", $groupid. ' / ', $rprem, ' / ');
                    $lisaRisks[$groupid] = array(
                      'riskgroupid' => $groupid,
                      'payamount' => $rprem,
                      'items' => []
                    );
                }
                # writeDebugInfo("KT-22 rsk: ", $rsk);
                $forindividual = isset($rsk['forindividual'])? $rsk['forindividual'] : '';
                $expname = isset($rsk['exportname'])? $rsk['exportname'] : '';
                $shortname = isset($rsk['shortname'])? $rsk['shortname'] : '';
                if($rsa>0 || TRUE) $lisaRisks[$groupid]['items'][$rsktype] = array(
                    'riskamount' => $rsa,
                    'rtype' => $rsk['rtype'],
                    'alforiskid' => $rsk['alforiskid'],
                    'payamount' => $rprem,
                    'forindividual' => $forindividual,
                    'otherperson' => $rsk['otherperson'],
                    'exportname' => $expname,
                    'shortname' => $shortname,
                    # 'riskid' => $rsk['riskid'],
                    'datefrom' => (isset($rsk['datefrom'])? $rsk['datefrom'] : ''),
                    'datetill' => (isset($rsk['datetill'])? $rsk['datetill'] : ''),
                );
                $lisaRisks[$groupid]['payamount'] = max($lisaRisks[$groupid]['payamount'], $rprem);
            }

            /*
            $lisagroup = $rinfo['riskgroup'];
            if (!isset($plc['risks'][$lisagroup]))
                $plc['risks'][$lisagroup] = array('riskgroupid'=>$lisagroup, 'payamount'=>0, 'items'=>array());
            $plc['risks'][$lisagroup]['payamount'] = max($plc['risks'][$lisagroup]['payamount'], $rsk['payamount']);
            $plc['risks'][$lisagroup]['items'][$riskid] = $rsk;
            */
            if (count($lisaRisks)) {
                # {upd/2021-01-13} удаляю риски для 2-ых застрахованных, если их в полисе нет!
                # file_put_contents("_loaded_risks-$plc[policyno].log", print_r($lisaRisks,1) );
                $plc['risks'] = []; #  = $lisaRisks;
                # writeDebugInfo("plc: ", $plc);
                # echo ("Lisa risks:<pre>" . print_r($lisaRisks,1).'</pre>');
                $sumprem = 0;
                foreach($lisaRisks as $grpid=>$r1) {
                    $subject = '';
                    $alforiskid = '';
                    foreach($r1['items'] as $rsk) {
                        $subject = $rsk['forindividual']==3 ? $rsk['otherperson'] : '';
                        $alforiskid = $rsk['alforiskid'];
                    }
                    if (self::$debug) writeDebugInfo("grp $grpid: forindividual=$rsk[forindividual], subject=[$subject]");
                    if ($subject === 'insd-2' && empty($this->insured2['fam'])) {
                        # writeDebugInfo("insd-2 skipped (no insured 2!)", $rsk);
                        continue;
                    }
                    # делать так же для списка детей застрахованных (онко-барьер):
                    if (stripos($alforiskid,'child')!==FALSE && $subject === 'set') {
                        if(self::$debug) writeDebugInfo("Child risk:", $rsk);
                    }
                    elseif (substr($subject,0,6) === 'child-') {
                        $childNo = intval(substr($subject,6));
                        /*
                        if($childNo == 1 &&  !empty($this->child['fam'])) {
                            $useChild = 1;
                            writeDebugInfo("use child obj (for child-1)");
                        }
                        else
                        */
                        if (empty($this->agmtdata['insuredchild'][$childNo-1]['fam'])) {
                            # writeDebugInfo("child ", $this->child);
                            # writeDebugInfo("$subject skipped (no child $childNo)", $rsk);
                            continue;
                        }
                        elseif (self::$debug) writeDebugInfo("$grpid: child risk group for existing child-$childNo");
                    }
                    $plc['risks'][$grpid] = $r1;
                    $sumprem += $r1['payamount'];
                }
            }
            else {
                writeDebugInfo("Ошибка при загрузке рисков для LISA, _p", AppEnv::$_p, " \ntrace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
                writeDebugInfo("risks: ", $risks);
                if(AppEnv::isApiCall()) return ['result'=>'ERROR', 'message'=>'Ошибка при подготовке к выгрузке в СЭД (XML файл для ИС)'];
                exit('1' . AjaxResponse::showError('Ошибка при загрузке рисков для LISA'));
            }
        }

        # echo 'risks array:<pre>'.print_r($plc['risks'],1) . '</pre>'; exit;
        $dateSign = to_date($plc['created']);

        # if (intval($plc['datepay'])) $plc['datesign'] = $plc['datepay'];
        # {upd/2023-04-10} - новое определение даты "выпуска" для Лизы

        if( !empty($agmtData['date_release']) && PlcUtils::isDateValue($agmtData['date_release']) )
            $dateSign = $agmtData['date_release'];

        # echo "<pre>dateSign = $dateSign </pre>"; exit; # PITSTOP
        $plc['datesign'] = $plc['issuedate'] = $dateSign;

        $insurer = $this->pholder;

        if ($plc['insurer_type'] == 1) {
            $this->standardizePerson($insurer);
            $persCount++;
        }
        /*
        else $this->standardizeUL($insurer);
        */
        # сдвиг полей адреса под имена в модуле выгрузки
        $this->shiftAddrFields($insurer);
        # writeDebugInfo("insurer ", $insurer);
        $plc['insurer'] = $insurer;
        if (!$plc['equalinsured'] && is_array($this->insured) && count($this->insured)>0) {
            $insured = $this->insured;
            if (isset($insured['0']['adr_country'])) foreach($insured as &$oneInsured) {
                $this->shiftAddrFields($oneInsured);
            }
            else
                $this->shiftAddrFields($insured);
            $this->standardizePerson($insured);
            # writeDebugInfo("insured ", $insured);
            $plc['insured'] = $insured; # $this->insured; ???
            $persCount++;
        }
        if (is_array($this->insured2) && count($this->insured2)>0) {
            $insured2 = $this->insured2;
            if (isset($insured2['0']['adr_country'])) foreach($insured2 as &$oneInsured) {
                $persCount++;
                $this->shiftAddrFields($oneInsured);
            }
            else {
                $persCount++;
                $this->shiftAddrFields($insured2);
            }
            $this->standardizePerson($insured2);
            # writeDebugInfo("insured2 ", $insured2);
            $plc['insured2'] = $insured2;
        }
        # есть застрах.ребенок - добавляю в список застрахованных
        if (!empty($this->agmtdata['insuredchild'])) {
            # echo 'data <pre>' . print_r($this->agmtdata['insuredchild'],1). '</pre>'; exit;
            if ($this->isMultiChild()) {
                $plc['child'] = [];
                if(is_array($this->agmtdata['insuredchild'][0]))
                  foreach($this->agmtdata['insuredchild'] as $childRow) {
                    $this->shiftAddrFields($childRow);
                    $plc['child'][] = $childRow;
                    $persCount++;
                }
            }
            else {
                $plc['child'] = $this->agmtdata['insuredchild'];
                $this->shiftAddrFields($plc['child']);
                $persCount++;
            }

            # WriteDebugInfo("child exist, risk list:", $plc['risks']);
            # WriteDebugInfo("todo: add rowid in child risk, lisaRisks:", $lisaRisks);
            foreach($lisaRisks as $lid => &$grp) {
                foreach ($grp['items'] as $no=>$ritem) {
                    if (!empty($ritem['persontype'])) {
                        if ($ritem['persontype'] === 'child') $ritem['personid'] = $persCount;
                    }
                }
            }

            /*
            if (isset($plc['insured'][0]))
                $plc['insured'][] = $this->agmtdata['insuredchild'];
            elseif(isset($plc['insured']['fam'])) {
                $insured0 = $plc['insured'];
                $plc['insured'] = array($insured0, $this->agmtdata['insuredchild']);
            }
            if ($this->_debugExp) {
                foreach($plc['insured'] as $no=>$item) {
                    echo "Insured item $no:<pre>".print_r($item,1) . '</pre>';
                }
                exit;
            }
            */
        }
        if ($debugMe) {
            AppEnv::appendHtml( '_rawAgmtData: <pre>' . print_r($this->_rawAgmtData,1) .'</pre>'); # debug
            AppEnv::appendHtml( 'Страхователь:<pre>' . print_r($this->pholder,1) .'</pre>'); # debug
            AppEnv::appendHtml( 'Застрахованный:<pre>' . print_r($this->insured,1) .'</pre>'); # debug
        }
        if ($debugMe) {
            AppEnv::appendHtml( 'Risks:<pre>' . print_r($risks,1) .'</pre>'); # debug
            AppEnv::finalize();
        }
        # @since 1.49: если есть метод getRedemptionsExport - вызываю для получения массива выкупных сумм!
        if(method_exists($this, 'getRedemptionsExport')) {
            $redemp = $this->getRedemptionsExport($plcid);
            if (is_array($redemp) && count($redemp))
                $plc['redemption'] = $redemp;
        }
        # exit(__FILE__ .':'.__LINE__.' Export final data:<pre>' . print_r($plc,1) . '</pre>');
        return $plc;
    }
    # Вернет NN - мкас.число застрахованных детей, если активен мульти-чайлд
    public function isMultiChild() {
        $splt = explode(',', $this->insured_child);
        if ($splt[0] === 'option' && !empty($splt[1])) return $splt[1];
        return FALSE;
    }
    /**
    * Общий способ получить все ИД рисков смерти в полисе (чтобы в XML формировать блок привязок рисков к выгодоприобр.)
    * {upd/2023-05-30} - добавляю еще и риски на ребёнка - по ним тоже надо будет выводить ВП
    * @param mixed $prodcode
    */
    public function getBenefAddRisks($prodcode = '') {
        $ret = [];
        $risks = [];
        if (!empty($this->_rawPolicyRisks)) {
            $risks = $this->_rawPolicyRisks;
        }
        elseif(!empty($this->_rawAgmtData['stmt_id'])) {
            $risks = $this->loadPolicyRisks($this->_rawAgmtData['stmt_id'], -1);
        }
        if (is_array($risks) && count($risks)>0) foreach($risks as $no => $rsk) {
            if (stripos($rsk['riskid'],'death')!==FALSE || stripos($rsk['riskid'],'child')!==FALSE)
                $ret[] = $rsk['riskid'];
        }

        return $ret;
    }
    /**
    * формирование ссылки на открытие просмотра договора, для грида списка полисов
    *
    * @param mixed $id
    */
    public static function showPolicyNo($param, $arRow=FALSE) {
        # writeDebugInfo("showPolicyNo param: ", $param, " arRow: ", $arRow, " trace:", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        global $ast_datarow;
        if(!is_array($ast_datarow) && is_array($arRow)) $ast_datarow =& $arRow;
        $module = $ast_datarow['module'] ?? ''; // $this->module;
        $ret = "<a href='./?plg=$module&action=viewagr&id=".($ast_datarow['stmt_id']??'xx')."'>$param</a>";
        return $ret;
    }
    public function isNonLife() { return $this->nonlife; }

    # формирую отчет (стандартный из класса app/stdreport.php)
    public function runreport() {
        # ini_set('display_errors',1); ini_set('error_reporting',E_ALL);
        # include_once(AppEnv::FOLDER_APP . 'stdreport.php');
        # задаю параметры доступа, названия программ
        StdReport::$repLevel = AppEnv::$auth->getAccessLevel($this->privid_reports);
        StdReport::$repTitle = $this->reportTitle;
        StdREport::run($this);
    }

    # По коду страны (либо названию, если не число) выясняю, Россия или нет
    public static function isRF($id) {
        if (is_numeric($id)) {
            $dta = AppEnv::$db->select(PM::T_COUNTRIES,array('fields'=>'countryname,ctype', 'where'=>"(id=$id OR countryno=$id)",'singlerow'=>1));
            return (isset($dta['ctype']) && $dta['ctype']==1);
        }
        $strana = mb_strtolower((string)$id);
        return in_array($strana, ['рф','россия','российская федерация']);
    }

    # AJAX - пришел запрос "стартовать ЭДО процесс"
    public function startEdo() {
        # exit('1' . AjaxResponse::timedNotify('Проверка исчезающих сообщений! '.date('H:i:s'),6));
        $id = AppEnv::$_p['id'] ?? 0;
        $typeId= AppEnv::$_p['typeid'] ?? FALSE;
        $procId= AppEnv::$_p['processid'] ?? FALSE;
        if ($id<=0) exit('wrong call');
        $this->loadPolicy($id, -1);
        $this->defineEdoType($id);
        if($this->isDraftPolicyno()) {
            $plcNo = explode('-', $this->_rawAgmtData['policyno']);
            # Перед отправкой на ЭДО согл-е надо присвоить постоянный номер полиса

            $plcNo[1] = NumberProvide::getNext($plcNo[0],$this->_rawAgmtData['headdeptid'],$this->module, $this->policyno_len);
            if(!is_numeric($plcNo[1]))
                exit('1' . AjaxResponse::showError('В системе нет доступного очередного номера полиса!<br>Свяжитесь с администраторами'));
            $newPno = implode('-', $plcNo);
            $dt = ['policyno' => $newPno ];
            $this->_rawAgmtData['policyno'] = $newPno;
            $result = PlcUtils::updatePolicy($this->module,$id,$dt);
            if(!$result) exit('1' . AjaxResponse::showError('Ошибка при обновлении номера полиса!'));

        }
        UniPep::startEdo($this->module, $id, $typeId, $procId);
    }
    # определяю тип ЭДО процесса в зависимости от факторов (например, канала продаж)
    public function defineEdoType($policyid=0) {
        return $this->edoType;
    }
    public function startEdo2() {
        $id = isset(AppEnv::$_p['id']) ? AppEnv::$_p['id'] : 0;
        if ($id<=0) exit('wrong call');
        UniPep::startEdo2($this->module, $id);
    }
    # AJAX - пришел запрос "стартовать Без ПЭП"
    public function startNotEdo() {
        $id = isset(AppEnv::$_p['id']) ? AppEnv::$_p['id'] : 0;
        if ($id<=0) exit('wrong call');
        UniPep::startStdProc($this->module);
    }
    # получить ISO код по ИД страны
    public static function getCountryISO($id) {
        $dta = AppEnv::$db->select(PM::T_COUNTRIES,array('fields'=>'isocode', 'where'=>array('id'=>$id),'singlerow'=>1));
        return (isset($dta['isocode']) ? $dta['isocode'] : $id);
    }
    public static function decodeCountry($id) {
        if(is_numeric($id)) $where = "id=$id OR countryno=$id";
        else $where = ['countryname' => $id];
        $dta = AppEnv::$db->select(PM::T_COUNTRIES,array('fields'=>'countryname,ctype', 'where'=>$where,'singlerow'=>1));
        return (isset($dta['countryname']) ? $dta['countryname'] : $id);
    }
    public static function decodeMarried($val, $sex = '') {
        $myes = ['M' => 'женат', 'F' => 'замужем', 'Муж.'=>'женат', 'Жен.'=>'замужем'];
        $mno = ['M' => 'холост', 'F' => 'не замужем', 'Муж.'=>'холост', 'Жен.'=>'не замужем'];
        if ($val === '') return '';
        if ($val === '0' || $val ===0 ) return (isset($mno[$sex]) ? $mno[$sex] : 'Холост/не замужем');
        else return (isset($myes[$sex]) ? $myes[$sex] : 'Женат/замужем');
    }
    # список статусов для отбора в фильтре
    public static function AgmtStatesList() {
        return array(
            array('0','Проект')
           ,array('2','Андеррайтинг')
           ,array('6','Полис')
           ,array('9','Аннулирован')
           ,array('10','Отменен')
           ,array('11','Оформлен')

        );
    }

    /**
    * Добавляю плагин, работающий с полисами/договорами
    *
    * @param mixed $moduleid ID плагина
    * @param mixed $title  кракое название модуля (тип полисов)
    * @param mixed $rights  ID права супер-операциониста
    * @param mixed $subtypes  список кодировок для полисов, выпускаемых в данном модуле ИЛИ имя ф-ции, которая его выдаст
    * @param mixed $metadept  ИД мета-подразделения из настроек (для поиска "головных" подразд.)
    */
    public static function addPlcPlugin($moduleid, $title, $rights, $subtypes=array(), $invest=FALSE) {
        Modules::addPlcPlugin($moduleid, $title, $rights, $subtypes, $invest);
        # self::$plc_plugins[$moduleid] = array('title'=>$title, 'rights'=>$rights, 'subtypes'=>$subtypes, 'invest'=>$invest);
    }
    /**
    * Вернет список только инвест-модулей (для формироания опций SELECT-боксов)
    *
    * @param mixed $p1
    * @param mixed $selectNone - надо ли включать опцию "не выбрано ничего"
    * @since 1.28
    */
    public static function getInvestModules($p1=FALSE,$selectNone = 1) {
        return Modules::getInvestModules($p1,$selectNone);
        /*
        $ret = (($selectNone) ? [ [ '', '---'] ] : []); # опция "не выбрано ничего" для select box
        foreach(self::$plc_plugins as $id => $arr) {
            if ($arr['invest']) $ret[] = [ $id, $arr['title'] ];
        }
        return $ret;
        */
    }
    # определяет к какому плагину относится кодировка
    public static function getModuleForSubtype($subtype) {
        return Modules::getModuleForSubtype($subtype);
        /*
        foreach (self::$plc_plugins as $moduleid => $data) {
            if (is_string($data['subtypes'])) {
                $codes = (is_callable($data['subtypes']) ? call_user_func($data['subtypes']) : explode(',',$data['subtypes']));
            }
            else $codes = $data['subtypes'];
            if ( is_array($codes) && in_array($subtype, $codes) || in_array($subtype, array_keys($codes)) ) return $moduleid;
        }
        return '';
        */
    }
    /**
    * Вернет массив - список зарегистрированных "страховых" модулей, для формирования select-бокса выбора
    * (перенесено из alfo_core (AppEnv::)
    */
    public static function getAgmtModules($mymodule='') {
        return Modules::getAgmtModules($mymodule);
        /*
        $ret = array(); # array(array('','--не выбран--'));
        # WriteDebugInfo('current module:', self::$_plg_name);
        foreach(self::$plc_plugins as $id=>$item) {
            # $item['title'], $item['rights'] = 'xxx_superopert', $item['subtypes'] = 'SSS,CCC,...'
            if (!empty($mymodule) && ($mymodule!=='') && $mymodule !==$id) continue;
            $ret[] = array($id, $item['title']);
        }
        return $ret;
        */
    }
    public static function getModulesAll() {
        return Modules::getModulesAll();
        /*
        $ret = array_merge([['','-все модули-']], self::getAgmtModules());
        return $ret;
        */
    }
    public function getModule() {
        return $this->module;
    }
    public static function getModulesAllBills() {
        return Modules::getModulesAllBills();
        /*
        $ret = array_merge([['','-все модули-']], self::getAgmtModules(), [[PlcUtils::BILLS_CODE,'-Для номеров счетов-']]);
        return $ret;
        */
    }
    /**
    * Для astedit-редактора таблицы alf_dept_product - BLIST-поля prodcodes
    *
    * @param mixed $module
    * (перенесено из alfo_code (AppEnv::)
    */
    public static function blistModuleProdcodes($module='') {
        return Modules::blistModuleProdcodes($module);
        /*
        global $ast_datarow;
        if (isset($ast_datarow['module'])) $module = $ast_datarow['module'];
        if (isset(self::$plc_plugins[$module]['subtypes'])) {
            $ret = [];
            foreach(self::$plc_plugins[$module]['subtypes'] as $cod=>$item) {
                if (is_numeric($cod) && !is_array($item)) $ret[] = $item;
                else $ret[] = isset($item[1]) ? [ $cod, $item[1] ] : $cod;
            }
            return $ret;
        }
        return [];
        */
    }

    public static function getProductsCodes($module='') {
        return Modules::getProductsCodes($module);
    }

    public static function addUwDetails($policyno,$datefrom, $datetill, $risksum=0, $currency='--') {
        self::$uw_details[] = array($policyno,$datefrom, $datetill, $risksum, $currency);
    }
    /**
    * Вычисляет "правильную" дату окончания действия по возрасту застрахованного и его типу:
    *
    * @param mixed $persontype - тип застрахованного : 'm'-муж, 'f'-жен, 'c'|'child'-ребенок
    * @param mixed $personbirth - дата рождения застрахованного, 'YYYY-MM-DD'
    * @param mixed $dtstart - дата начала д-вия полиса, 'YYYY-MM-DD'
    * @param mixed $dttill - дата окончания д-вия полиса (начальная), 'YYYY-MM-DD'
    */
    public static function getDateTillByAge($persontype,$personbirth,$dtstart='',$dttill='') {

        $ptype = mb_strtolower(mb_substr($persontype,0,1));
        switch($ptype) {
            case 'f': case 'ж': case 'w':
                # $maxage = self::PENSIA_FEMALE;
                $maxage = AppEnv::getConfigValue('ins_pensionage_f',60); # новый пенс-возраст - из настроек!

                break; # из кальк-ра передается женщина = 'w' !!!
            case 'c':
                $maxage = self::$AGE_CHILD; break;
            default :
                # $maxage = self::PENSIA_MALE;
                $maxage = AppEnv::getConfigValue('ins_pensionage_m',65);
                break;
        }
        $yrarr = DiffDays($personbirth, $dttill,true);
        $years = $yrarr[0]; # лет на момент оконч.д-вия полиса
        # echo "лет на момент оконч.д-вия полиса: $years<br>";
        $delta = $maxage-$years;
        if (AppEnv::isTestingMode()) {
            echo "<br>getDateTillByAge: max age=$maxage, person birth is $personbirth, calculated years at ending policy: $years, delta: $delta<br>";
        }
        $ret = ($years < $maxage) ? $dttill : AddToDate($dttill, $delta,0,0);
        # $yrarr = DiffDays($personbirth, $ret,true); echo "<br>Лет на посчитанную дату:".print_r($yrarr,1).'<br>';
        # WriteDebugInfo("counted TillByAge($persontype,$personbirth,$dtstart) = $ret");
        return $ret;
    }

    # открыли диалог о простанове оплаты - надо вернуть тек.дату и сумму оплаты в руб.
    public function setpayed_filldlg() {
        # writeDebugInfo("setpayed_filldlg ", AppEnv::$_p);
        $plcid = $this->_p['id'];
        $ret = "1"; # \tset\fin_datepay\f" . date('d.m.Y');
        $dta = self::loadPolicy($plcid, -1);
        if (isset($dta['policy_prem'])) {
            $premrur = $dta['policy_prem'];
            if ($dta['currency'] !== 'RUR') {
                include_once('class.currencyrates.php');
                $rate = CurrencyRates::GetRates('',$dta['currency']);
                $premrur = round( $premrur * $rate,2);
            }
            # $today = date('d.m.Y');
            $rubli = fmtMoney($premrur);
            $ret .= AjaxResponse::setHtml('in_oplata_rub', $rubli);
            if($this->eq_payment_enabled && Acquiring::hasWaitingOrder($this->module, $plcid)) {
                $warn = AppEnv::getLocalized('warn_acquiring_sent');
                $ret .= AjaxResponse::setHtml('setpay_warning', $warn);
            }

            # $ret .= "\tset\fin_datepay\f$today\thtml\fin_oplata_rub\f$rubli";
        }
        exit($ret);
    }
    // AJAX: запрос на просмотр данных о пользователеи, из окна лога действий с договором
    public function event_viewperson() {
        Libs\ViewInfo::event_viewperson();
    }
    // policytools/seek_lost_policy - ищу потерянный полис (возможно со сменившимся номером)
    public static function seekLostPolicy() {
        return Libs\ViewInfo::seekLostPolicy();
    }

    # default function for reading template (usually for ajax response)
    public function readtemplate($params=0) {

        $id = isset(WebApp::$_p['id']) ? WebApp::$_p['id'] : 'noid';
        if (!empty(WebApp::$_p['template']) && WebApp::$_p['template']!=='false') $tpl = WebApp::$_p['template'];
        else $tpl = $id;
        $fnames = [ # буду искать по очереди все варианты файла шаблона, в конце - из общей папки templates
            $this->home_folder . "$tpl.htm",
            $this->home_folder . "html/$tpl.htm",
            ALFO_ROOT . "templates/$tpl.htm"
        ];
        $fname = '';
        foreach($fnames as $oneName) {
            if (is_file($oneName)) { $fname = $oneName; break; }
        }
        $fbody = ($fname) ? file_get_contents($fname) : "<div id='$tpl' class='float_wnd' style='width:200px'>$tpl: no html template found</div>";
        WebApp::localizeStrings($fbody);

        exit ($fbody);
    }

    /**
    * регистрировать ИД учетки и факт попытки передачи подставных данных, взлома расчета
    * @param mixed $module ИД плагина
    * @param mixed $hacktype тип "хака"
    */
    public static function registerHackAttempt($module, $hacktype='') {
        # TODO: регистрировать ИД учетки и факт попытки передачи подставных данных, взлома расчета
    }

    # Создание записей с описанием прав и ролей для плагина
    # вызов: ./?plg={plugin}&action=makeroles
    public function makeRoles($noFinalize = FALSE) {

        $mid = get_class($this);
        # echo "$mid - class<br>";
        # $mid = AppEnv::currentPlugin();
        # include_once(AppEnv::FOLDER_APP . 'acl.tools.php');
        aclTools::setPrefix(AppEnv::TABLES_PREFIX);
        $arr = $this->listRolesRights();
        aclTools::upgradeRoles($mid, $arr, $noFinalize);
        # if ($noFinalize) AppEnv::finalize();
        # if(AppEnv::isStandalone()) exit;
    }


    public function checkGlobalBlocking($agmtid=0, $super = NULL) {
        if ($super === NULL) $super = AppEnv::$auth->getAccessLevel($this->privid_super);
        if ($super) return 0;
        $global = AppEnv::getConfigValue('alfo_disable_activity',0);
        $disab = max(AppEnv::getConfigValue($this->module.'_disable_activity',0), $global);
        # $disab = AppEnv::getConfigValue('kpp_disable_activity');
        $compName = AppEnv::getConfigValue('comp_title');
        if (!$agmtid && $disab>1.1) AppEnv::echoError('К сожалению, продажа временно приостановлена.<br>По всем вопросам обращайтесь в '.$compName);
        # elseif ($disab>1 && $disab !=1.1) AppEnv::echoError('К сожалению, доступ к сервису оформления договоров временно заблокирован.<br>По всем вопросам обращайтесь в '.$compName);
    }
    /**
    * @since 1.2.017
    * @param mixed $param
    * @return Муж|Жен
    */
    public static function decodeSex($param) {
        if ($param === 'F' || $param === 'Ж') return 'Жен.';
        return 'Муж.';
    }
    /**
    * Получает ИД стр.схемы для ЛИЗы по справочнику alf_exportcfg
    * по серии из номера полиса
    * @param mixed $data
    */
    public final static function getExportInsuranceScheme($data, $lisaCode = '') {
        if(self::$debug && $lisaCode) writeDebugInfo("getExportInsuranceScheme, for lisaCode: ", $lisaCode);
        # writeDebugInfo("getExportInsuranceScheme, lisaCode: $lisaCode");
        if (!empty($lisaCode)) $code = $lisaCode;
        elseif (is_scalar($data)) {
            $code = $data;
        }
        if (!$code) {
            $spl = [ '' ];
            if (!empty($data['policyno'])) {
                list($code) = explode('-', $data['policyno']);
            }
            elseif (!empty($data['prodcode'])) $code = $data['prodcode'];
        }
        $prodid  = $code;
        if (!empty($data['module'])) {
            $bkEnd = AppEnv::getPluginBackend($data['module']);
            if (!empty($bkEnd->redefineExpCode) && !empty($spl[0]) ) $code = $spl[0];
        }
        if (!empty($data['programid'])) $prodid = $data['programid'];
        $exp = AppEnv::$db->select(PM::T_EXPORTCFG, array('where'=>"FIND_IN_SET('$code', product)", 'singlerow'=>1)); # new!
        # exit (__FILE__ .':'.__LINE__.' data:<pre>' . print_r($exp,1) . '</pre>' . AppEnv::$db->getLastQuery());
        # writeDebugInfo("exp : ", $exp, ' SQL: ' , AppEnv::$db->getLastQuery());
        $ret = (isset($exp['extprogram']) ? $exp['extprogram'] : '');
        # writeDebugInfo("return programid : [$ret]");
        return $ret;

    }

    /**
    * Привожу ключи физ-лица к "стандарту" (для единого модуля выгрузки)
    * @param mixed $p ассоц.массив из alf_agmt_individual
    */
    public function standardizePerson(&$p) {
        # для офиц.адреса (прописка/регистрация)
        #        WriteDebugInfo('standardizePerson : ', $p);
        /*        $pairs = array('fam'=>'lastname', 'imia'=>'firstname','otch'=>'middlename','birth'=>'birthdate','docser'=>'passportseries'
                    ,'docno'=>'passport','docdate'=>'passportissuedate', 'docissued'=>'passportissueplace'
                    ,'adr_zip'=>'officialaddr_postcode','adr_country'=>'officialaddr_region' # ,'adr_country'=>'officialaddr_district'
                    ,'adr_region'=> 'officialaddr_city','adr_street'=>'officialaddr_street','adr_house'=>'officialaddr_houseno'
                    ,'adr_corp'=>'officialaddr_korpus','adr_flat'=>'officialaddr_flat'
                );

        */        # для факт.адреса:
        # $p['rez_country'] = PolicyModel::decodeCountry($p['rez_country']);
        if (!empty($p['fam']))
          $p['birth_place'] = (!empty($p['birth_country']) ? self::decodeCountry($p['birth_country']) : '');
    }
    /**
    * Готовлю станд.названия элементов ЮЛ для выгрузки
    *
    * @param mixed $p
    */
    public function standardizeUL(&$p) {
        return;
        /*
        $pairs = array('inn'=>'nalogcode','name'=>'lastname', 'docser'=>'passportseries'
            ,'docno'=>'passport','docdate'=>'passportissuedate', 'docissued'=>'passportissueplace'
            ,'adr_zip'=>'officialaddr_postcode','adr_country'=>'officialaddr_region' # ,'officialaddr_district'=>'xxx'
            ,'adr_city'=> 'officialaddr_city','adr_street'=>'officialaddr_street','adr_house'=>'officialaddr_houseno'
            ,'adr_corp'=>'officialaddr_korpus','adr_flat'=>'officialaddr_flat'
        );
        foreach($pairs as $k=>$v) {
            $p[$k] = $p[$v];
            unset($p[$v]);
        }
        */
    }
    /**
    * Добавляю текст причины постановки на андеррайтинг
    * (договор сохраняется, но у юзера выскочит уведомление, что он в статусе "андеррайтинг"
    * с поясняющим текстом
    * @param mixed $reason_text
    */
    public function addUnderwritingReason($text_reason) {

        if($this->auto_uw_state) $this->agmt_setstate = PM::STATE_UNDERWRITING; # {upd/2022-12-26} auto_uw_state
        if ($this->uw_reason=='' || (FALSE === mb_strpos($this->uw_reason,$text_reason)))
            $this->uw_reason .= ( $this->uw_reason ? '<br>':'') . $text_reason;

    }
    /**
    * Устанавливаю статус "Договор на андеррайтинге". Если подтверждения еще не пришло, порслыаю ajax запрос,
    * если уже подтверждение было - договор будет сохранен в статусе "на андеррайтинге"
    * $this->_p['uw_confirmed'] - поле проверки подтверждения от клиента
    */
    public function setUnderWritingState() {

        if(self::$debug) WriteDebugInfo("setUnderWritingState: ", $this->uw_reason, ', reasonid:', $this->uw_reasonid, ' details: ', self::$uw_details);
        if (empty($this->uw_reason)) return;
        if (empty($this->_p['uw_confirmed']) && $this->ask_for_uw) {
            # юзер еще не подтвердил согласие сохранить полис с андеррайтингом, посылаю AJAX запрос
            $warn_details = (count(self::$uw_details)>0) ? implode('<br>',self::$uw_details) : $this->uw_reason;
            $msgtext = "Данный договор будет требовать андеррайтинга. Причина(ы): <br><br>"
               . $warn_details
               . "<br><br>Желаете все равно сохранить его ? (при ответе <b>нет</b> будет продолжен ввод)";

            $ret = "1\tconfirm\fВнимание!\f$msgtext\fpolicyModel.setUwConfirmed()";
            exit (encodeResponseData($ret));
        }
        # $this->agmt_setstate = PM::STATE_UNDERWRITING;

        $this->setUwNotification($this->uw_reason);
    }
    /**
    * Общая ф-ция для получения данных о любом полисе по его номеру
    *
    * @param mixed $stmtno
    * @since 1.4
    */
    public static function findPolicyInfo($stmtno) {
        $data = AppEnv::$db->select(PM::T_POLICIES, array(
          'where'=>array('policyno'=>$stmtno)
          ,'associative'=>1
          ,'singlerow'=>1
        ));
        if (!isset($data['policyno'])) return FALSE;
        return $data;
    }

    /**
    * дает строку с коротким названием риска для выгодоприобретателя по строке из benef.risk
    * 'R1'-основной, 'R2'-дополн., 'R12' - оба
    *
    * @param mixed $risks
    * @param mixed $pversion
    * @since 1.7.20
    */
    public function decodeBenefRisks($riskstr, $pversion='1') {

        if ($pversion == '1' && empty($riskstr)) $riskstr = 'R12';
        switch($riskstr) {
            case 'R1': return 'Основной';
            case 'R2': return 'Дополн.';
            case 'R12': return 'Осн. + Дополн.';
            default : return $riskstr;
        }

    }

    /**
    * Получаю параметры риска для выгрузки в LISA
    *
    * @param mixed $productid ИД или кодировка продукта в ФО
    * @param mixed $riskid id или riskename риска в едином справочнике рисков ФО
    * @param mixed $multirow вывести все записи (может быть несколько "выходных" рисков LISA для одного "головного" в ФО
    * @since 1.8
    */
    public function getExportRiskConfig($productid, $riskid, $multirow=true) {
        if(self::$debug) {
            writeDebugInfo("getExportRiskConfig($productid, $riskid,multirow=[$multirow] ...");
            # $trc = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            # if ($productid==='GAR') writeDebugInfo("debug trace: ", $trc);
        }
        # writeDebugInfo("raw data: ", $this->_rawAgmtData);
        # writeDebugInfo("raw calc: ", $this->calc);
        # writeDebugInfo("raw src: ", $this->srvcalc);
        $wop = FALSE;
        if (isset($this->calc['wop'])) $wop = $this->calc['wop'];
        elseif (isset($this->calc['b_wop'])) $wop = $this->calc['b_wop'];
        elseif (isset($this->srvcalc['wop'])) $wop = $this->srvcalc['wop'];
        elseif (isset($this->srvcalc['b_wop'])) $wop = $this->srvcalc['b_wop'];
        $wop = ($wop === 'y' || $wop === 'Y' || $wop == 1); # TRUE либо FALSE
        # определил есть ли в полисе риск ОУСВ
        $prod = AppEnv::$db->select(PM::T_EXPORTCFG, array('where'=>"FIND_IN_SET('$productid',product)", 'singlerow'=>1));
        # writeDebugInfo("$productid: prod for export: ", $prod);
        if(!$prod || !count($prod)) writeDebugInfo('stack:', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        /*
        if (!empty($prod['binded_to'])) {
            $prod = AppEnv::$db->select(PM::T_EXPORTCFG, array('where'=>['id'=>$prod['binded_to']], 'singlerow'=>1));
            echo "binded export for prog=$productid found: <pre>" . print_r($prod,1). '</pre>';
        }
        */
        if ( !is_array($prod) || !isset($prod['id']) ) {
            echo " getExportRiskConfig($productid, $riskid,[$multirow]) is EMPTY<br>"; exit;
            return FALSE;
        }
        # echo " getExportRiskConfig($productid, $riskid,[$multirow]): <pre>".print_r($prod,1).'</pre>';
        if (!isset($prod['id'])) exit('no id in prod:<pre>'.print_r($prod,1)/'</pre>');
        $prid = $oldprid = $prod['id'];
        if (!empty($prod['binded_to']) && $prod['binded_to']>0) {
            $prid = $prod['binded_to'];
            # echo "redefine prid from $oldprid to $prid<br>";
            # переназначение, берется настройка рисков от другой программы
        }
        $rsk = AppEnv::$db->select(PM::T_RISKS, array('where'=>"'$riskid' IN(id,riskename)", 'singlerow'=>1,'orderby'=>'id'));
        if (!isset($rsk['id']))
            return FALSE;

        $risknum = $rsk['id'];
        $exp = AppEnv::$db->select(PM::T_EXPORTRISKS,
           array( 'where'=>array('productid'=>$prid, 'riskid'=>$risknum) )
        );
        /*
        if($riskid === 'death_any_wop') {
            WriteDebugInfo("$riskid SQL: ", AppEnv::$db->getLastquery());
            WriteDebugInfo("$riskid exp: ", $exp, );
        }
        */
        # WriteDebugInfo("productid, riskid, multirow: $productid=$prid, $riskid, $multirow");
        if (!is_array($exp) || count($exp)<1) {
            if (self::$logErrors) WriteDebugInfo("Не найдена настройка в T_EXPORTRISKS для productid=$prid, riskid=$risknum, rsk:, ", $rsk);
            # echo "Не найдена настройка в T_EXPORTRISKS для productid=$prid, riskid=$risknum, rsk: <pre>" . print_r($rsk,1). '</pre>';
            return FALSE; # (($multirow) ? array($rsk) : $rsk);
        }

        $ret = array();
        foreach($exp as $onexp) {
            $orsk = $rsk;
            # if($riskid === 'death_any_wop') writeDebugInfo("death_any_wop onexp item: ", $onexp);
            if (!empty($onexp['exp_riskid'])) $orsk['riskpid'] = $onexp['exp_riskid'];
            if (!empty($onexp['exp_groupid'])) {
                $grpid = $onexp['exp_groupid'];
                # если ОУСВ выключен и задана группа для НЕ-ОУСВ, использую:
                if (!$wop && !empty($onexp['exp_groupidnowop'])) $grpid = $onexp['exp_groupidnowop'];
                if ($grpid < 0 && method_exists($this, 'getExportRiskGroup')) {
                    # {upd/2020-02-14} отриц.значение - признак "хочу получить код программно (вызови getExportRiskGroup()"
                    # {?upd/2020-02} либо не выгружать вообще (<0 означает риск НЕ ЖИЗНЬ, в Лизу не посылать)!
                    $grpid =  $this->getExportRiskGroup($grpid, $onexp['exp_riskid']);
                }
                $orsk['riskgroup'] = $grpid;
            }
            else {
                # echo "skipping: <pre>".print_r($onexp).'</pre>';
                continue; # нет настройки риска или нулевая группа - пропускаем
            }

            $orsk['forindividual'] = (!empty($onexp['exp_individ']) ? $onexp['exp_individ'] : '1');
            $orsk['otherperson'] = (!empty($onexp['otherperson']) ? $onexp['otherperson'] : '0');
            # {upd/2019-09-13} - добавляю тип субъекта forindividual для тега в риске <ForIndividualTypeID>
            if ($multirow) $ret[] = $orsk;
            else {
                return    $orsk;
            }
        }
        return $ret;
    }
    /**
    * Получить код подразд-я по юзер-токену
    * @since 1.10
    * @param mixed $tokenstr "user token"
    */
    public static function getDeptByToken($tokenstr='') {
        $dta = AppEnv::$db->select(PM::T_APICLIENTS, array('where'=>array('usertoken'=>$tokenstr), 'singlerow'=>1));
        return $dta;
    }

    /**
    * Выполнение пролонгации (делаем копию всех параметров, страхователя, застрахованного и полис в статусе "Проект"(6)
    * @since 1.11
    */
    public function prolongate() {

        $id = isset(AppEnv::$_p['id']) ? AppEnv::$_p['id'] : 0;
        // TODO: implement
        if ($id<=0) AppEnv::echoError('err_wrong_call');
        $plcdta = AppEnv::$db->select(PM::T_POLICIES, array('where'=>"stmt_id=$id",'singlerow'=>1,'associative'=>1));
        if (empty($plcdta['stmt_id'])) AppEnv::echoError('err_policy_not_found');

        unset($plcdta['stmt_id'], $plcdta['accepted'], $plcdta['export_pkt'], $plcdta['datepay']);

        $plcdta['userid'] = AppEnv::$auth->userid; # TODO: для супервизора возможны накладки по коду подразд.
        $plcdta['datefrom'] = addToDate($plcdta['datetill'],0,0,1); # на след.день после оконч-я

        $term = $plcdta['term'];
        switch($plcdta['termunit']) {
            case 'y': case 'Y':
                $plcdta['datetill'] = AddToDate($plcdta['datefrom'], $term,0,0);
                break;
            case 'm': case 'M': default:
                $plcdta['datetill'] = AddToDate($plcdta['datefrom'], 0, $term,0);
                break;
        }
        $plcdta['previous_id'] = $id;
        $plcdta['stmt_stateid'] = PM::STATE_FORMED;
        $plcdta['stateid'] = PM::STATE_POLICY;
        $plcdta['created'] = date('Y-m-d H:i:s');

        $ret = '<pre>'.print_r($plcdta,1).'</pre>';
        exit($ret);
    }
    /**
    * вернет true если есть права сотрудника стр.компании/супер-операциониста или
    *  права простановки статуса Принято СК/акцептовано
    */
    public function isInsCompOfficer() {
        $ret = AppEnv::$auth->getAccessLevel([PM::RIGHT_SUPEROPER, $this->privid_super]);
        if (!$ret) $ret = (AppEnv::$auth->getAccessLevel($this->privid_editor)>=PM::LEVEL_IC_ADMIN);
        return $ret;
    }
    /**
    * регистрация расторжения (статус - PM::STATE_DISSOLUTED
    *
    */
    public function dissolute() {
        $canaccept = (AppEnv::$auth->getAccessLevel(PM::RIGHT_ACCEPTOR) || AppEnv::$auth->getAccessLevel($this->privid_editor)>=4);
        if (!$canaccept) {
            AppEnv::echoError('err-no-rights');
            exit;
        }

        $result = PlcUtils::dissolute($this->module, $this->log_pref);

        $this->refresh_view();
    }

    /**
    * Формирует HTML код для показа инфо о полисе, пролонгацией которого являетсмя данный (если есть)
    * и ссылки на "следующий" в цепочке (если у него тоже есть пролонгация)
    * @param mixed $data ассоц.массив данных полиса
    * @return string
    */
    public function getHtmlProlongInfo($data) {
        $id = $data['stmt_id'];
        $prev = $data['previous_id'];
        $today = date('Y-m-d');

        # {upd/2023-03-21} - вывожу предупр. об истекшем сроке на пролонгацию
        $pDays = AppEnv::getConfigValue('prolong_days_afterend');
        $maxdate = ($pDays > 0) ? date('Y-m-d', strtotime(to_date($data['datefrom']) . "+ $pDays days")) : to_date($data['datefrom']);
        $prolongExpired = (!empty($data['previous_id']) && $maxdate <= $today
          && $this->agmtdata['stateid']<9);

        $ret = '';
        if (is_numeric($prev) && $prev>0) {
            $prevdta = AppEnv::$db->select(PM::T_POLICIES, array('fields'=>'policyno','where'=>"stmt_id=$prev",'singlerow'=>1));

            $pno = isset($prevdta['policyno']) ? $prevdta['policyno'] : $prev;
            $ret .= " [ %short_prolong_agmt% <a href=\"./?plg=" . $this->module . "&action=viewagr&id=$prev\">$pno</a> ]";
        }
        elseif(!empty($prev)) { # номер анешнего полиса
            $ret .= " [ %short_prolong_agmt% $prev ]";
        }
        $next = AppEnv::$db->select(PM::T_POLICIES, array(
          'fields'=>'stmt_id,policyno','where'=>"previous_id=$id",'singlerow'=>1)
        );
        if (!empty($next['policyno']))
            $ret .= "[ %short_prolong_exist%: <a href=\"./?plg=" . $this->module . "&action=viewagr&id=$next[stmt_id]\">$next[policyno]</a> ]";
        AppEnv::localizeStrings($ret);
        if($prolongExpired && $data['stateid']<=PM::STATE_ANNUL)
            $ret .= '<div class="alert alert-danger fw-bolder m-0">' . AppEnv::getLocalized('alarm_prolong_expired') . '</div>';
        return $ret;
    }

    /**
    * Проверка наличия полиса с данным номером
    *
    * @param mixed $policyno номер полиса BBB-NNNNNNNN
    * @param mixed $myid ИД полиса, который не нужно проверять (сам данный полис)
    * @return FALSE если полис не найден, array с данными если найден
    */
    public function isPolicyExist($policyno, $myid = 0) {
        $where = array("policyno='$policyno'");
        if ($myid>0) $where[] = "stmt_id<>$myid";
        # WriteDebugInfo("isPolicyExist for $policyno, $myid");
        $dta = AppEnv::$db->select(PM::T_POLICIES, array('where'=>$where, 'fields'=>'stmt_id,deptid,headdeptid','singlerow'=>1));
        # WriteDebugInfo("dta:", $dta); WriteDebugInfo("SQL:", AppEnv::$db->getLastQuery());

        return ((isset($dta['stmt_id'])) ? $dta : FALSE);
    }

    /**
    * Выгрузка одного полиса в XML, как при экспорте пакета
    * @since 1.14 2018-02-20
    * AppEnv::$_p: id: ИД полиса, plg: ИД плагина: plg=plgkpp & id=201
    */
    public function policyToXml() {
        if (!empty(AppEnv::$_plugins['plcexport'])) {
            AppEnv::getPluginBackend('plcexport') -> onePolicyPacket(AppEnv::$_p['plg'], AppEnv::$_p['id']);
        }
        else die('Модуль экспорта в XML не подключен!');
    }

    /**
    * Сохраняет в сессии, в подмассиве указанного ключа переданные данные
    * @since 1.20
    *
    * @param mixed $baseid строка с базовым ключом : 'calcdata_garcl' и т.д.
    * @param mixed $datakey
    * @param mixed $data собсно массив , который хотим запомнить
    * @param mixed $uniqueid уникальный ИД для под-массива
    *
    */
    public function saveCalcToSession($baseid, $data, $uniqueid='') {
        if (empty($uniqueid) && !empty(AppEnv::$_p['calcid']))
            $uniqueid = AppEnv::$_p['calcid'];
        if (empty($uniqueid)) die('Не передан уник.код расчета calcid');
        if (!isset($_SESSION[$baseid])) $_SESSION[$baseid] = [];
        $_SESSION[$baseid][$uniqueid] = $data;
        # file_put_contents("_{$baseid}.log", print_r($data,1));
    }
    /**
    * Выгружаем полис в карточку СЭД (с-му эл. документо-оборота)
    *
    */
    public function policyToDocflow() {
        # writeDebugInfo("policyToDocflow ", $this->_p);
        # exit('1' . AjaxResponse::showMessage('policyToDocflow Data: <pre>' . print_r(AppEnv::$_p,1) . '</pre>'));
        if (isset($this->_p['id']) && !isset($this->_rawAgmtData['stmt_id']))
            $this->LoadPolicy($this->_p['id'], -1);
        if (method_exists($this, 'beforeViewAgr')) $this->beforeViewAgr();
        # для полисов, оформленных "свободным клиентом" в eShop наличие файлов скана необязательно.
        $toUwstate = (!empty($this->_p['sedstate']) && $this->_p['sedstate'] == 2); # отправить в СЭД на андеррайтинг?
        $checkFiles = 1;
        if(!empty($this->_p['ctrl']) && SuperAdminMode()) {
            $checkFiles = 0;
            PlcUtils::workFlowFilesCheckOff(TRUE);
        }

        if ($checkFiles && $this->uploadScanMode == 1) {
            $this->checkMandatoryFiles('docflow', $toUwstate);
        }
        # else exit('No uploadScanMode'); # debug STOP
        PlcUtils::policyToDocflow($this->module, $this, $this->log_pref, $toUwstate, FALSE, $this->sed_final_state);
    }

    # Валидирую специфические данные
    public function validateSpecData() {
        $checkChild = FALSE;
        $args = [];
        if(func_num_args()) {
            $args = func_get_args();
            if($args[0] === 'check_child') $checkChild = TRUE;
        }
        # writeDebugInfo(__METHOD__, " args: ", $args);
        if($this->b_block_working && $this->_p['work_type'] !=='0') {
            $ch = array();
            if(empty($this->_p['work_company'])) $ch[] = 'наим-е Работодателя';
            if(empty($this->_p['work_duty'])) $ch[] = 'Должность';
            if(in_array($this->b_block_working, [3,'strict','strictinn']) && empty($this->_p['work_action']))
                $ch[] = 'Характер выполняемой работы '; # Должностные обязанности
            if(empty($this->_p['year_salary'])) $ch[] = 'средний годовой доход';

            if(count($ch)) $this->addCheckError('Страхователь: не указаны : '.implode(', ',$ch),TRUE);
            if(!empty($this->_p['year_salary']) && floatval($this->_p['year_salary']) < PM::MIN_YEARSALARY)
                $this->addCheckError('Введен средний <b>годовой</b> доход ниже '. PM::MIN_YEARSALARY . ' руб.', TRUE);
        }
        if ($this->block_tax_rezident) {
            if (empty($this->_p['tax_rezident']))
                $this->addCheckError('err_empty_tax_rezident', TRUE);
        }
        if($checkChild) {
            if(empty($this->_p['cbenefrelate']))
                $this->addCheckError('err_empty_cbenef_relation', TRUE);
            # else PlcUtils::checkChildRelation($this->_p['cbenefrelate']); # добавит UW статус если не сын/дочь/папа/мама
            if($this->b_childBenef === 2 && empty($this->_p['cbenefrelate']))
                $this->addCheckError('err_empty_cbenefdocconfirm', TRUE);
        }
        if($this->in_anketa_client) {
            if( ($this->_p['deesposob_limited']??'') =='Y' && empty($this->_p['deesposob_limited_reason']))
                $this->addCheckError('Не введено Обоснование ограничения дееспособности ',TRUE);
        }
        if($this->clientBankInfo) {
            $chBank = [];
            if(empty($this->_p['client_bankname'])) $chBank[] = 'Название Банка';
            if(empty($this->_p['client_bankbic'])) $chBank[] = 'БИК Банка';
            if(empty($this->_p['client_corracc'])) $chBank[] = '№ корр.счета';
            elseif(!RusUtils::checkBankAccount($this->_p['client_corracc']))
                $chBank[] = 'Некорректный № корр-счета';
            if(empty($this->_p['client_persacc'])) $chBank[] = '№ счёта клиента';
            elseif(!RusUtils::checkBankAccount($this->_p['client_persacc']))
                $chBank[] = 'Некорректный № счёта клиента';

            if(empty($this->_p['client_accowner'])) $chBank[] = 'ФИО владельца счёта';
            if(count($chBank)) $this->addCheckError('Данные банка клиента, не указаны: не указаны : '.implode(', ',$chBank),TRUE);
        }
    }
    # авто-добавление спец-полей при включенных параметрах модуля
    public function autoSpecFields() {
        if ($this->b_block_working) $this->addSpecFields(['work_type','work_company',
          'work_inn', 'work_address','work_duty','year_salary']
        );
        if ($this->agredit_notify_payment) $this->addSpecFields(['notify_payment']);
    }
    /**
    * Онлайн-проверка страхователя, застрахованного, всех выг-приобр по спискам фин-мониторинга
    * @param $policyid ID полиса, для "внутреннего" вызова из ALFO (например, при сохранени полиса), а не AJAX вызову от кнопки PEPs
    * @param $saveLog : TRUE - сразу занесу HTML с результатом файлом в договор (независимо от PLC_SAVE_CHECKLOG)
    */
    public function CheckFinmon($policyid = 0, $saveLog = FALSE) {
        # writeDebugInfo("start CheckFinmon, $policyid");
        $innerCall = ($policyid > 0); # вызвали из других методов, не по кнопке PEPs

        $id = ($policyid>0) ? $policyid : (isset(AppEnv::$_p['id']) ? trim(AppEnv::$_p['id']) : 0);
        if (!$id && !empty($this->_rawAgmtData['stmt_id'])) $id = $this->_rawAgmtData['stmt_id'];

        if ($id<=0) exit('Ошибка в параметрах');
        $fmBackEnd = AppEnv::getPluginBackend('finmonitor'); # $_plugins['finmonitor']->getBackend();
        if (!$fmBackEnd) {
            if (AppEnv::isApiCall())
                return ['result'=>'ERROR', 'message' => 'не найден плагин finmonitor'];
            exit('Плагин "Финмониторинг" не найден!');
        }

        $dta = $this->loadPolicy($id, 'view');
        # writeDebugInfo("view dta for chewckFinmon ", $dta);
        $policyno = $dta['policyno'];

        $fmBackEnd = AppEnv::getPluginBackend('finmonitor'); # $_plugins['finmonitor']->getBackend();
        if ($fmBackEnd && $this->module === 'ZZZ-trmig') { # временно мигрантов не проверяю...
            self::$debug = 1;
            # FinMonitorBackend::setEmulate(1); writeDebugInfo("set finmonitor to emaulate mode");
        }
        $htmlResult = '<div style="overflow:auto; __max-height:200px"><table class="table table-bordered table-light"><tr><th>Тип</th><th>Результат</th></tr>';

        $cnt = 1;
        # WriteDebugInfo('insurer: ', $dta['insr']);
        if ($dta['insurer_type'] != 1) {

            # writeDebugInfo("CheckFinmon/UL dta: ", $dta);
            $orgName = 'none'; $orgInn = '';
            if(!empty($dta['insrurname'])) $orgName = $dta['insrurname'];
            elseif(!empty($dta['insr']['urname'])) $orgName = $dta['insr']['urname'];

            if(!empty($dta['insrurinn'])) $orgInn = $dta['insrurinn'];
            elseif(!empty($dta['insr']['urinn'])) $orgInn = $dta['insr']['urinn'];

            $req = [ 'subjtype ' => 'ul',
              'orgname' => $orgName,
              'inn' => $orgInn
            ];
            $insrname = 'ЮЛ: ' . $orgName;
        }
        else {
            $req = [
                'lastname' => $dta['insr']['fam'],
                'firstname' => $dta['insr']['imia'],
                'patrname' => $dta['insr']['otch'],
                'birthdate' => $dta['insr']['birth'],
            ];
            # writeDebugInfo("insr: ", $dta['insr']);
            if($dta['insr']['doctype'] == 1) {
                $req['docser'] = trim($dta['insr']['docser']);
                $req['docno'] = trim($dta['insr']['docno']);
            }
            $insrname = trim($dta['insr']['fam'].' '.$dta['insr']['imia'].' '.$dta['insr']['otch']);
        }
        $persid = $dta['insr']['id'] ?? 0;

        $ptitle = ($dta['equalinsured']) ? 'Страхователь/застрах.' : 'Страхователь';
        if (empty($dta['equalinsured']))
            $req['ignorePEP'] = 1; # Страхователь - на PEPs не проверять

        if (self::$debug) {
            WriteDebugInfo("Finmonitor request params:", $req);
        }
        $result = ( $fmBackEnd->isError()) ? '---' : $fmBackEnd->request($req, $persid);
        $uwcheckCode = 0;

        # writeDebugInfo("persid=[$persid], person data: ", $dta['insr'], ' result :', $result);

        if (self::$debug ) {
            WriteDebugInfo("Finmonitor request result:", $result);
        }

        $htmlResult .= "<tr><td>$ptitle</td><td>$result</td></tr>";

        if (empty($dta['equalinsured']) && !empty($dta['insd'][0])) {
            $cnt++;
            # WriteDebugInfo('insured data: ', $dta['insd']);
            foreach($dta['insd'] as $no => $item ) {
                $req = [
                    'lastname' => $item['fam'],
                    'firstname' => $item['imia'],
                    'patrname' => $item['otch'],
                    'birthdate' => $item['birth'],
                    # 'document' => trim($item['docser']. ' '. $item['docno']),
                ];
                if($item['doctype'] == 1) {
                    $req['docser'] = trim($item['docser']);
                    $req['docno'] = trim($item['docno']);
                }

                $persid = $dta['insd']['id'] ?? 0;

                $insdname = trim($item['fam'].' '.$item['imia'].' '.$item['otch']);
                if (self::$debug) writeDebugInfo("finmonitor params: ", $req);
                $result = ($fmBackEnd->isError()) ? '---' : $fmBackEnd->request($req, $persid);
                if (self::$debug) writeDebugInfo("finmonitor result: ", $result);
                $htmlResult .= '<tr><td>Застрахованный</td><td>'.$result.'</td></tr>';
            }
        }
        if (isset($dta['child'])) {
            # exit('1' . AjaxResponse::showMessage('child:<pre>'.print_r($dta['child'],1).'</pre>'));
            $cnt++;
            if(substr($this->insured_child,0,7) === 'option,') {# список детей!
                $maxChild = intval(substr($this->insured_child,7));
                for($nChild = 1; $nChild<=$maxChild; $nChild++) {
                    if (!empty(AppEnv::$_p['b_child'.$nChild])) {
                        if(self::$debug) WriteDebugInfo("check child[$nChild]...");
                        $req = [
                            'lastname' => RusUtils::mb_trim($dta['child']["child{$nChild}fam"]),
                            'firstname' => RusUtils::mb_trim($dta['child']["child{$nChild}imia"]),
                            'patrname' => RusUtils::mb_trim($dta['child']["child{$nChild}otch"]),
                            'birthdate' => $dta['child']["child{$nChild}birth"],
                            # 'document' => ($dta['child']["child{$nChild}docser"]. ' '. $dta['child']["child{$nChild}docno"]),
                        ];
                        if($dta['child']["child{$nChild}doctype"] == 1) {
                            $req['docser'] = trim( $dta['child']["child{$nChild}docser"] );
                            $req['docno'] = trim( $dta['child']["child{$nChild}docno"] );
                        }

                        # $insdname = trim($childData['fam'].' '.$childData['imia'].' '.$childData['otch']);
                        $persid = $dta['child']["child{$nChild}id"] ?? 0;
                        $result = ($fmBackEnd->isError()) ? '---' : $fmBackEnd->request($req, $persid);
                        $htmlResult .= "<tr><td>Застрахованный ребенок $nChild </td><td>$result</td></tr>";
                    }
                }
            }
            else {
                if(self::$debug) WriteDebugInfo('check single child: ', $dta['child']);
                $req = [
                    'lastname' => RusUtils::mb_trim($dta['child']['fam']),
                    'firstname' => RusUtils::mb_trim($dta['child']['imia']),
                    'patrname' => RusUtils::mb_trim($dta['child']['otch']),
                    'birthdate' => RusUtils::mb_trim($dta['child']['birth']),
                    # 'document' => trim($dta['child']['docser']. ' '. $dta['child']['docno']),
                ];
                if($dta['child']['doctype'] == 1) {
                    $req['docser'] = trim( $dta['child']['docser'] );
                    $req['docno'] = trim( $dta['child']['docno'] );
                }

                $insdname = trim($dta['child']['fam'].' '.$dta['child']['imia'].' '.$dta['child']['otch']);
                $persid = $dta['child']['id'] ?? 0;
                $result = ($fmBackEnd->isError()) ? '---' : $fmBackEnd->request($req, $persid);
                $htmlResult .= '<tr><td>Застрахованный ребенок</td><td>'.$result.'</td></tr>';
            }
        }
        if ($this->multi_insured >=100 && method_exists($this, 'finmonInsureds')) {
            $chkInsured = $this->finmonInsureds($id, $fmBackEnd);
            if ($chkInsured) $htmlResult .= $chkInsured; # добавим в общий лог проверки
            $uwcheckCode = $fmBackEnd->getCheckSummary();
        }
        if (isset($dta['benef']) && is_array($dta['benef']) && plcUtils::$CHECK_BENEFS) {
            # WriteDebugInfo('benef: ', $dta['benef']);
            foreach($dta['benef'] as $no => $item) {
                $cnt++;
                $nm = preg_split("/[ ]/", $item['fullname'],-1, PREG_SPLIT_NO_EMPTY );
                $req = [
                    'lastname' => $nm[0],
                    'firstname' => (isset($nm[1]) ? $nm[1] : ''),
                    'patrname' => (isset($nm[2]) ? $nm[2] : ''),
                    'birthdate' => $item['birth'],
                    # 'document' => trim($item['docser']. ' '. $item['docno']),
                ];
                if($item['doctype'] == 1) {
                    $req['docser'] = trim( $item['docser'] );
                    $req['docno'] = trim( $item['docno'] );
                }

                $persid = $item['id'] ?? 0;
                if($persid > 0) $persid = "B:$persid"; # чтобы писать не в individual, а в benef
                $result = ($fmBackEnd->isError()) ? '---' : $fmBackEnd->request($req, $persid);
                $htmlResult .= '<tr><td>Выгодоприобр. '.($no+1).'</td>'
                  . "<td>$result</td></tr>\n";
            }

        }
        if($fmBackEnd->getErrorMessage()) {
            AppEnv::addInstantMessage("Проверка Комплайнс не была выполнена (ошибка обращения к сервису)!", 'finmonotr_svc');
        }
        else {
            $htmlResult .= '</table></div>';
            $this->peps_detailed = $fmBackEnd->getStates(); # полный массив результатов проверок по типам

            $resume = $fmBackEnd->getCheckSummaryHtml(); # "Подлежит блокировке",...

            $htmlResult .= "\n<br>$resume";

            if ( constant('PLC_SAVE_CHECKLOG') || $saveLog) {
                $result = PlcUtils::saveResultHtmlToPolicy($this->module, $id, $htmlResult, "Проверка полиса $policyno от %date%");
                if ($result) $htmlResult .= "\treloadGrid"; # обновит список сканов с учтокм нового "лога"
            }
            $uwcheckCode = $fmBackEnd->getCheckSummary(); # 0 - OK, 1=STATE_TO_UW, 10=STATE_BLOCKING,

            $pepsUpd = ['pepstate'=>($uwcheckCode+100)];

            # writeDebugInfo("agmtdata ", $this->agmtdata );

            if($uwcheckCode > 0 && empty($this->agmtdata['reasonid'])) {
                PlcUtils::$uw_code = $pepsUpd['reasonid'] = PM::UW_REASON_PEPSCHECK;
                if($this->agmtdata['stateid']==PM::STATE_FORMED && $this->agmtdata['substate'] == PM::SUBSTATE_AFTER_EDIT) {
                    $pepsUpd['substate'] = PM::SUBSTATE_COMPLIANCE; # надо отправить в Комплаенс
                    if(self::$debug) writeDebugInfo("доработка, снова отправляю в Комплаенс");
                }
                elseif(in_array($this->agmtdata['stateid'], [PM::STATE_PROJECT, PM::STATE_IN_FORMING]))
                    PlcUtils::$uw_code = $pepsUpd['reasonid'] = PM::UW_REASON_PEPSCHECK;
            }

            if(self::$debug) writeDebugInfo("to update after PEPs check: ", $pepsUpd); # AppEnv::$db->log(10);
            # $res = AppEnv::$db->update(PM::T_POLICIES, $pepsUpd, ['stmt_id'=>$id]);
            $res = PlcUtils::updatePolicy($this->module, $id, $pepsUpd);
            # writeDebugInfo("update peps: $res, sql:", AppEnv::$db->getLastQuery(), ' err:',  AppEnv::$db->sql_error());
        }
        # сохранил код "рисковости" по результатам PEPs-проверок

        if ($innerCall) return $uwcheckCode; # вызов из другого модуля, возвращаем итоговый статус проверки (код рисковости)
        else exit($htmlResult);
    }

    public function set_policycomment() {

        $id = isset(AppEnv::$_p['id']) ? AppEnv::$_p['id'] : 0;
        $cmt = isset(AppEnv::$_p['comment']) ? AppEnv::$_p['comment'] : '';
        $result = PlcUtils::setPolicyComment($this->module, $id, $cmt, $this->log_pref, TRUE);
        if (method_exists($this, 'afterCommentSent')) {
            $this->afterCommentSent($id, $cmt);
        }

        exit("1$result");
    }

    public static function getUploadFolder($basePath='', $rootFolder = '') {
        if(empty($basePath)) $basePath = self::$FOLDER_SCANS;
        if(empty($rootFolder)) {
            $rootFolder = ALFO_ROOT;
            $cfgFilePath = AppEnv::getConfigValue('upload_root_folder','');
            if(!empty($cfgFilePath))
                $rootFolder = $cfgFilePath;
            # writeDebugInfo("cfgFilePath: [$cfgFilePath], rootFolder=$rootFolder");
        }
        # writeDebugInfo("final rootFolder=$rootFolder");

        if (substr($basePath, -1) != '/') $basePath .= '/';
        $ret = $rootFolder . $basePath . date('Y');
        if (!is_dir($ret)) {
            $cresult = @mkdir($ret, 0777, true);
            if(self::$debug) WriteDebugInfo("created=[$cresult] folder:", $rootFolder . $ret);
            if (!is_dir($ret)) return $basePath;
        }
        # writeDebugInfo("getUploadFolder returns $ret/");
        return ($ret . '/');
    }
    /**
    * Тестовый вызов - расчет полного АВ по полису (памятка ЦБ)
    *
    */
    public function calc_av() {
        $id = isset($this->_p['id']) ? $this->_p['id'] : '';
        $dta = $this->loadPolicy($id);
        # WriteDebugInfo('calc_av, dta: ', $dta);
        $av = CbMemo::calcAvValues($dta);
        exit("calc_av call, module:$this->module, id=$id, AV: ".implode('; ', $av));
    }

    # AJAX запрос для запонения грида - список убытков по полису
    public function getLossesList() {
        LossManager::getLossesList($this->module, AppEnv::$_p['id']);
    }

    public function updateLoss() {
        if (!$this->isAdmin()) {
            AppEnv::echoError('err-no-rights');
            return;
        }
        LossManager::updateLoss('update');
    }

    public function addLoss() {
        if (!$this->isAdmin()) {
            AppEnv::echoError('err-no-rights');
            return;
        }
        LossManager::updateLoss('add');
    }

    public function deleteLoss() {
        if (!$this->isAdmin()) {
            AppEnv::echoError('err-no-rights');
            return;
        }
        LossManager::deleteLoss();
    }

    public function sendPdfToClient() {
        $ret = PlcUtils::sendPdfToClient(AppEnv::$_p['id'], AppEnv::$_p['plg']);
        exit($ret);
    }

    /**
    * вернет TRUE если в полисе проставлено соотв-е декларации Застрахованного (или в модуле она не активирована)
    * или подтверждено согласие Клиента со всеми ЭДО положениями
    */
    public function isDeclarationSet() {
        if(!$this->enable_meddeclar || $this->nonlife) return TRUE;
        $edoConfirmed = ($this->_rawAgmtData['bpstateid']== PM::BPSTATE_EDO_OK);
        # writeDebugInfo("isDeclarationSet: this->_rawAgmtData ", $this->_rawAgmtData);
        # {upd/2022-11-01} если статус "соглас. с UW" в отметку декларации можно не смотреть
        $normMed = ( !empty($this->_rawAgmtData['med_declar']) || in_array($this->_rawAgmtData['stateid'],[PM::STATE_UWAGREED, PM::STATE_UWAGREED_CORR]) );
        $ret = ($normMed || $edoConfirmed);
        # writeDebugInfo("ret = [$ret], agmt: ", $this->_rawAgmtData);
        return $ret;
    }
    # вернет соответствие договора мед-декларациям (Y,N или '' если еще не определено)
    # В основном для заявления
    public function underMedDeclar($dta = FALSE) {
        if(!$dta) $dta =& $this->_rawAgmtData;
        if(!empty($dta['med_declar'])) return $this->_rawAgmtData['med_declar'];
        if(!empty($dta['reasonid'])) return 'N';
        return '';
    }
    public function getProp($propName) {
        if (isset($this->$propName)) return $this->$propName;
        return NULL;
    }
    # рисует HTML с описанием полиса, для отправки на страницу согласования клиентом и т.п.
    public function getAgreementDescription($plcid) {
        $pdata = $this->loadPolicy($plcid, 'print');
        # writeDebugInfo("getAgreementDescription data ", $pdata);
        $html = '';
        return $html;
    }
    # на лету добавляю статус, при котором будет ЭЦП подписание PDF полиса после генерации
    public function addDigSignState($stateid) {
        if(is_array($stateid))
            $this->sign_pdf_states = array_merge($this->sign_pdf_states, $stateid);
        else
            $this->sign_pdf_states[] = $stateid;
    }

    # Позвали со страницы ЭДО-подтверждений вывод PDF с офертой.
    public function printOferta($id=0) {
        if (!$id) $id = (isset($this->_p['id']) ? $this->_p['id'] : FALSE);
        if (!$id) exit('printOferta wrong call');
        $dta = $this->loadPolicy($id, 'print');
        $this->prepareForPrintPacket($dta);
        $dta['insurername'] = $dta['insurer_fullname'];
        # echo 'data <pre>' . print_r($dta,1). '</pre>'; exit;
        UniPep::printPdfOferta($dta);
        exit;
    }
    # заглушка - должна вернуть TRUE или URL если для данного полис есть документ "инвестиционная декларация"
    public function hasInvestDeclaration($data=0) {
        return FALSE;
    }
    # заглушка - должна вернуть TRUE если для данного полиса есть форма заявления Застрахованного/Страхователя (для ЭДО)
    public function hasStatementInsured($data=0) {
        return FALSE;
    }
    public function viewInsuredArray($perstype,$pageId) {
        $ret = '<div class="lightbg" style="height:100px;overflow:auto"><table class="zebra" style="width:100%"><tr><th>№</th><th>ФИО</th><th>Дата рожд.</th><th>Документ</th></tr>';
        $dta = AppEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>['stmt_id'=>$this->_rawAgmtData['stmt_id'],'ptype'=>$perstype],
          'fields'=>"CONCAT(fam,' ',imia,' ',otch) fio,DATE_FORMAT(birth,'%d.%m.%Y') birth,rez_country,doctype,docser,docno,docdate,inopass,otherdocno",
          'orderby'=>'id'
        ]);
        $no=0;
        if (is_array($dta)) foreach($dta as $row) {
            $no++;
            $document = Persons::viewPersonDocument($row);
            $ret .= "<tr><td class='ct'>$no</td><td>$row[fio]</td><td class='ct'>$row[birth]</td><td>$document</td></tr>";
        }
        $ret .= '</table></div>';
        return $ret;
    }
    # {upd/2021-01-19} вернет признак авто-перевода в оформленный при оплате
    public function payedToFormed() { return $this->payed_to_formed; }

    public function getEdoType($policyid=0) {
        $this->defineEdoType($policyid);
        return $this->edoType;
    }
    public function getAgmtValues() { } // заглушка для выставления всех параметров договора (insured_child, insured_adult...)

    # {upd/2022-07-22} перед AJAX - отправкой данных в agredit/stmt получаю все прочие данные
    public function getOtherData($id, &$dta) {
        if($this->b_clientRisky) {
            AgmtData::appendData($this->module, $id, $dta);
        }
    }
    # {upd/2022-08-10} -  беру дни на пролонгацию из глоб.настроек
    public function loadProlongLimits() {
        if($prVal = AppEnv::getConfigValue('prolong_days_advance'))
            $this->prolongate_before_end = $prVal;
        if($prVal = AppEnv::getConfigValue('prolong_days_afterend'))
            $this->prolongate_after_end = $prVal;
    }

    # {upd/2022-10-20} AJAX-агент/операционист отправляет договор на андеррайтинг (проверка наличия сканов!)
    public function startUw() {
        # exit('1' . AjaxResponse::showMessage('Data: <pre>' . print_r(appEnv::$_p,1) . '</pre>'));
        $plcid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        $logStrings = [];
        $shifted = isset($this->_p['shift']) ? $this->_p['shift'] : 0; # альт.режим выполнения
        $data = $this->loadPolicy($plcid, -1);
        # if(method_exists($this, 'beforeViewAgr')) $this->beforeViewAgr();
        if(!isset($data['stateid'])) exit("wrong call");
        $usrLevel = $this->getUserLevel();
        $docaccess = $this->checkDocumentRights($data);
        if($docaccess<1.5) { AppEnv::echoError('err-no-rights'); exit; }
        $reasonid = 0;
        if($this->policyCalcExpired()) {
            $canUw = 2; # повторный андерр. из-за сгоревшей маакс.даты выпуска
            $reasonid = PM::UW_REASON_BY_USER;
            # writeDebugInfo("KT-001 UW by expire");
        }
        else {
            $allReasons = UwUtils::getAllReasons($this->module,$plcid,TRUE);
            # exit('1' . AjaxResponse::showMessage('Data: <pre>' . print_r($allReasons,1) . '</pre>'));
            if($data['stateid'] == PM::STATE_DOP_CHECK_FAIL)
                 $canUw = TRUE;
            else $canUw = (in_array($data['stateid'],
              [0,PM::STATE_PROJECT, PM::STATE_IN_FORMING, PM::STATE_DOP_CHECK_DONE, PM::STATE_PAUSED])
              && !empty($allReasons['hard']));
            # writeDebugInfo("canUW: [$canUw] ", $data);
        }
        if(!$canUw) {
            exit('1'.AjaxResponse::showError('err_uw_impossible'));
        }

        $this->checkMandatoryFiles(Events::TOUW);

        $upd = [ 'stateid' => PM::STATE_UNDERWRITING, 'updated'=>'{now}' ];
        if( $reasonid && empty($data['reasonid']) ) $upd['reasonid'] = $reasonid;
        if($curCodir = $this->isDraftPolicyno()) {
            # получить боевой номер для полиса и заменяю
            $this->getRealPolicyNo($curCodir, $upd);
            $logStrings[] = [ 'SET POLICYNO', "Полису присвоен номер ".$upd['policyno'] ];
        }
        # $result = AppEnv::$db->update(PM::T_POLICIES,$upd,['stmt_id'=>$plcid]);
        $result = PlcUtils::updatePolicy($this->module, $plcid, $upd);
        if($result) {
            # writeDebugInfo("this->agent_prod= ", $this->agent_prod);
            if(count($logStrings)) foreach($logStrings as $item) {
                AppEnv::logEvent($this->log_pref.$item[0],$item[1],0,$plcid,0);
            }
            $statetxt = "Договор отправлен на андеррайтинг.";
            $this->loadPolicy($plcid, -1);
            AppEnv::logEvent($this->log_pref."SET STATE UW",$statetxt,0,$plcid);
            if($this->notify_agmt_change && method_exists($this, 'notifyAgmtChange')) {
                $this->notifyAgmtChange($statetxt, $plcid);
            }
            elseif($this->agent_prod !== PM::AGT_NOLIFE) { # для всех кроме НЕ ЖИЗНИ - шлю уведомлялово
                agtNotifier::send($this->module, Events::TOUW, $data);
            }

            $this->refresh_view($plcid);
        }
        else exit("1".AjaxResponse::showError('Ошибка сохранения данных'));

        # exit('TODO: startCheck(UW)');
    }
    /**
    * {upd/2022-11-02} отработка AJAX или API команды "отправить на учет"
    * проверка наличия сканов, перевод в Оформлен stateid=PM::STATE_FORMED, bpstateid=PM::BPSTATE_ACCOUNTED
    */
    public function submitForReg($paramid = FALSE) {
        if (AppEnv::isApiCall()) {
            $this->_p = AppEnv::$_p;
        }
        if (self::$debug) WriteDebugInfo('submitForReg params:', $this->_p);
        if($paramid>0) # внутренний вызов, например при онлайн-простановке оплаты (LightProcess)
            $plcid = $paramid;
        else {
            $plcid = isset($this->_p['id']) ? $this->_p['id'] : 0;
            $shifted = isset($this->_p['shift']) ? $this->_p['shift'] : 0; # альт.режим выполнения
        }
        $data = $this->loadPolicy($plcid, -1);
        $agmtData = AgmtData::getData($this->module, $plcid);

        $access = $this->checkDocumentRights();

        if($access < 1) {
            $errTxt = AppEnv::getLocalized('err-no-rights');
            if($paramid || AppEnv::isApiCall()) return ['result'=>'ERROR','message'=>$errTxt];
            appEnv::echoError($errTxt);
            exit;
        }

        $goodStates = [PM::STATE_IN_FORMING, PM::STATE_PAYED, PM::STATE_UWAGREED];
        if( AppEnv::isLightProcess() || AppEnv::isApiCall() )
            $goodStates[] = PM::STATE_FORMED; # при легком онлайн оформлении разрешен переход из "оформленного"

        if(!in_array($data['stateid'], $goodStates) || !intval($data['datepay'])) {
            # writeDebugInfo("submitForEeg: bad stateid: ", $data['stateid'], ' datepay: ', $data['datepay'], ' $goodStates: ', $goodStates);
            $errMsg = AppEnv::getLocalized('err_wrong_state_for_action');
            if(AppEnv::isApiCall() || $paramid) return ['result'=>'ERROR', 'message'=>$errMsg];
            AppEnv::echoError($errMsg);
            exit;
        }
        if($this->isEdoPolicy($data) && empty($agmtData['dt_client_letter'])) { # сначала надо послать
            if(!AppEnv::isApiCall() && !$paramid)
                exit('1'.AjaxResponse::showError('err-client-leter-not-sent'));
        }
        # exit('1' . AjaxResponse::showMessage('KT1 : '.$agmtData['dt_client_letter']));
        if(!AppEnv::isLightProcess()) {
            $chkResult = $this->checkMandatoryFiles(Events::SUBMIT_FORREG);
        }
        $upd = ['stateid' => PM::STATE_FORMED, 'bpstateid' => PM::BPSTATE_ACCOUNTED ];
        # $result = AppEnv::$db->update(PM::T_POLICIES, $upd, ['stmt_id' => $plcid]);
        $result = PlcUtils::updatePolicy($this->module, $plcid, $upd);
        if($result) {
            AppEnv::logEvent($this->getLogPref().'SUBMIT FOR REG', "Договор отправлен на учет",0, $plcid);
            # {upd/2025-01-24} - авто-удаление из Избранного, если у юзера включено
            if( !AppEnv::isApiCall() && AppEnv::isFavActive() ) {
                if(Favority::getViewState($this->module, $plcid) && UserParams::getSpecParamValue(0,PM::USER_CONFIG,'fav_auto_clear')) {
                    Favority::dropViewPolicyPage($this->module, $plcid);
                }
            }
            $sent = agtNotifier::send($this->module,Events::SUBMIT_FORREG, $data);# email уведомления - в ПП/ОБ
            if(AppEnv::isApiCall() || $paramid) return ['result'=>'OK', 'message'=>'Договор отправлен на учет'];
            $this->refresh_view($plcid,0);
        }
        else {
            if(AppEnv::isApiCall() || $paramid) return ['result'=>'ERROR', 'message'=>'err-data-saving'];
            exit('1' . AjaxResponse::showError("err-data-saving"));
        }
    }

    /**
    * {upd/2022-11-02} отработка AJAX или API команды "Проверен"
    * (финальная операция, полис уходит в СЭД если еще не там)
    */
    public function setStateActive() {
        if (AppEnv::isApiCall()) {
            $this->_p = AppEnv::$_p;
        }

        if (self::$debug) WriteDebugInfo('setActive params:', $this->_p);
        # exit(print_r($this->_p,1));
        $plcid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        $data = $this->loadPolicy($plcid, -1);

        $myLevel = $this->getUserLevel();
        if($myLevel < PM::LEVEL_IC_ADMIN) { appEnv::echoError('err-no-rights'); exit; }
        if($data['stateid']!= PM::STATE_FORMED || $data['bpstateid'] != PM::BPSTATE_ACCOUNTED) {
            AppEnv::echoError('err_wrong_state_for_action');
            exit;
        }

        $this->checkMandatoryFiles(Events::SUBMIT_FORREG);

        $cardId = 'new';
        $warning = '';
        if ($this->_rawAgmtData['docflowstate']>0 && $this->_rawAgmtData['export_pkt']>0) {
            # карточка была создана на этапе классического UW, доливаю в нее файл (возможно, переключаю "решение")
            $cardId = $this->_rawAgmtData['export_pkt'];
            $newFiles = FileUtils::getFilesInPolicy($this->module, $plcid, 'exported=0', TRUE);
            $sedOper = 'updateDocFlowCard';
            if(self::$debug) writeDebugInfo("setcheckDone: карточка уже есть, обновляем статус+файлы");
            if (!$this->export_xml_inuw && $this->enable_export) {
                # {upd/2021-01-29} в СЭД на UW не был загружен XML для Лизы, и сейчас надо выгрузить финальный!
                if(self::$debug) writeDebugInfo("добавляю в выгрузку XML (ранее не выгруженный по UW)");

                $exportBkend = appEnv::getPluginBackend('plcexport');
                # exit('TODO: generate XML!');
                $xmlFilename = $exportBkend -> onePolicyPacket($this->module, $plcid, 'file');
                if ($xmlFilename && is_file($xmlFilename))
                    $newFiles[] = [ 'fullpath' => $xmlFilename, 'filename'=> basename($xmlFilename) ];

            }

            # $data = [ 'module' => 'lifeag' ]; # TODO: внести маршрут Ввод в БД ?
            # if (is_array($newFiles) && count($newFiles)) {
                if (self::$debug) writeDebugInfo(__FUNCTION__,":доливаю в UW карточку оставшиеся файлы + [Согласовать След. этап] ", $newFiles);
                # добавляю строку об онлайн оплате!
                if (floatval($this->_rawAgmtData['eqpayed']) > 0) {
                    $updtComment = appEnv::getPluginBackend('sedexport')->appendComment($cardId, 'ОнЛайн оплата');
                    if (self::$debug) writeDebugInfo("ОнЛайн оплата - добавление комментария в карточке $cardId: ", $updtComment);
                    if (!isset($updtComment['result']) || $updtComment['result']!='OK')
                        appEnv::logevent($this->log_pref.'SED COMMENT FAIL', 'Не удалось занести в СЭД отметку об онлайн оплате', FALSE, $id);
                }
                # {upd/2021-02-20} в UW карточке СЭД были не заполены даты начала-оконч, и поле "согласован клиентом" - теперь пора их занести!
                $sedFields = [
                  'Предполагаемая дата начала действия' => to_char($this->_rawAgmtData['datefrom']),
                  'Дата окончания договора' => to_char($this->_rawAgmtData['datetill']),
                  'Текст согласован клиентом' => 'True',
                ];

                $sedResult = PlcUtils::updateDocFlowCard($cardId, $sedFields, $this->_rawAgmtData, $newFiles, $this->log_pref);
                # $sedResult = PlcUtils::updateDocFlowCard($cardId, $sedFields, $this->_rawAgmtData, $newFiles, $this->log_pref, 'Согласовать След. этап');
                if (!empty($sedResult['message'])) $warning = $sedResult['message'];
                if (self::$debug || !empty($sedResult['message']) ) writeDebugInfo("PlcUtils::updateDocFlowCard Result : ", $sedResult);
                if(isset($sedResult['result']) && $sedResult['result'] === 'OK') {
                    foreach($newFiles as $id => $item) {
                        if(!empty($item['exported'])) $logTxt = sprintf('В карточку СЭД загружен файл %s',$item['filename']);
                        else $logTxt = sprintf('Ошибка загрузки файла %s в карточку СЭД', $item['filename']);
                    }
                }
            # }
            # else $sedResult = ['result' => 'OK']; # ничего доливать не надо, все уже там
        }
        else {
            # Карточки еще не было, создаю новую (без андеррайтинга)
            if(self::$debug) writeDebugInfo(__FUNCTION__, ":создаю новую карточку СЭД");
            $sedState = 0; # 0 - нормальный (без андеррайтинга) 1,2 - на UW!
            $sedOper = 'createDocFlowCard';
            $sedResult = PlcUtils::policyToDocflow($this->module, $this, $this->log_pref, $sedState, TRUE);
            if (self::$debug) writeDebugInfo("PlcUtils::policyToDocflow() result: ", $sedResult);

        }
        if (self::$debug) writeDebugInfo("sed result: ", $sedResult);
        $success = (!empty($sedResult['result']) && $sedResult['result'] === 'OK');
        if (self::$debug) writeDebugInfo("sedOper: $sedOper, success: ", $success);
        if ($success || $sedOper=== 'updateDocFlowCard') {
            $upd  = [ 'bpstateid'=> PM::BPSTATE_ACTIVE, 'bpstate_date'=>'{now}','updated'=>'{now}']; # финальное состояние полиса!
            # $updResult = appEnv::$db->update(PM::T_POLICIES, $upd, ['stmt_id'=>$plcid]);
            $updResult = PlcUtils::updatePolicy($this->module, $plcid, $upd);
            if($updResult) {
                $logTxt = 'Договор переведен в статус Оформлен/Активный';
                appEnv::logEvent($this->log_pref.'SET STATE', $logTxt, FALSE, $plcid);
            }
            else {
                $logTxt = 'Ошибка записи нового статуса в БД!';
                writeDebugInfo("BPSTATE_ACTIVE result:[$updResult]", $upd, ' query: ',AppEnv::$db->getLastquery(),
                 "\n  error:",  AppEnv::$db->sql_error());
            }
        }
        else {
            $logTxt = 'Ошибка при добавлении/обновлении карточки СЭД';
            if (!empty($sedResult['message'])) $logTxt .= ': '.$sedResult['message'];
        }

        if ( $sedOper === 'updateDocFlowCard' ) {

            if ( !$success ) {
                # карточку в СЭД могли уже подвигать, и команда accept была отвергнута
                appEnv::logEvent($this->log_pref.'DOCFLOW WARNING', 'СЭД:Не удалось сменить статус [Согласовать След. этап]', FALSE, $plcid);
                $reason = isset($sedResult['message']) ? $sedResult['message'] : '';
                $logTxt .= '<br>СЭД:Не удалось задать статус [Согласовать След. этап]' . ($reason ? "<br>$reason" : '');
                if (TRUE) {
                    Cdebs::DebugSetOutput(AppEnv::getAppFolder('applogs/') . 'errors-'.date('Y-m-d').'.log');
                    writeDebugInfo("$this->module, policy[$plcid], Ошибка обновления карточки SED[Согласовать След. этап] fail(card $cardId)/sedOper=$sedOper:", $sedResult);
                    Cdebs::DebugSetOutput();
                }
            }
            # $errText = "Ошибка при выполнении операции в СЭД: " . print_r($sedResult,1);
            if (self::$debug) writeDebugInfo("SED (card $cardId)/$sedOper:", $sedResult);

            # TODO: раскомментировать строку ниже, если будет решено отправлять агенту уведомление об окончании проверки
            # $sent = $this->notifyAgmtChange($mailTxt,$plcid);
            # writeDebugInfo("notifyAgmtChange result: ", $sent);
        }

        exit('1' . ajaxREsponse::showMessage($logTxt) . $this->refresh_view($plcid,TRUE));
    }
    # определяю что полис оплачен и НЕ через эквайринг (зарегистрировали платежку с номером)
    public function isOfflinePayed() {
        $ret = ($this->_rawAgmtData['eqpayed']==0 && intval($this->_rawAgmtData['datepay'])>0);
        return $ret;
    }
    # определяю согласовывается ли полис по ЭДО
    public function isEdoProcess($data = 0) {
        return $this->isEdoPolicy($data);
    }
    # вернет TRUE если договор с ЭДО
    public function isEdoPolicy($data = FALSE) {
        if (!$data) $data =& $this->_rawAgmtData;
        if(is_scalar($data) && is_numeric($data)) {
            if(!isset($this->_rawAgmtData['stmt_id']) || $this->_rawAgmtData['stmt_id']!=$data)
                $this->loadPolicy($data, -1); # -1: нужна только запись карточки полиса
                $data =& $this->_rawAgmtData;
        }
        # writeDebugInfo("policymodel data is ", $data);
        $bptype = $data['bptype'] ?? '';
        return (!in_array($bptype, ['', PM::BPTYPE_STD]));
    }
    # определяет дату окончания периода охлаждения у договора
    public function getStopCoolDate($dta=0) {
        if(!is_array($dta)) $dta =& $this->_rawAgmtData;
        $days = PM::DAYS_COOL; # дефолтное число дней периода охлаждения
        $ret = addtoDate(($dta['datefrom']),0,0, $days);
        return $ret;
    }

    public function showNRND() {
        exit('1'. AjaxResponse::showError("TODO - реализовать в классе"));
    }
    # либо добавляю текст в список ошибок, либо поднимаю флажок "состояния Черновик" (если $toDraft = TRUE)
    public function addCheckError($text, $toDraft = TRUE) {
        $finalText = \AppEnv::getLocalized($text,''); # {upd/2024-08-16} - можно передавать ИД локализованой строки
        if(empty($finalText)) $finalText = $text;
        if($this->draft_state && $toDraft && PlcUtils::$draftstate_on) {
            PlcUtils::$draft_reasons[] = $finalText;
        }
        else $this->_err[] = $finalText;
    }
    # генерит черновой номер для полиса (временный вмсто выдаваемого из пула)
    public static function getDraftPolicyNo() {
        return 'DRAFT' . date('ymdhi');
    }

    # Переобъявить эти ф-ции где свой принцип получения файла Правил страхования
    # URL файла правил на офиц.сайте
    public function getProgramRulesUrl($data=0) {
        $key = $this->module . 'url_kid';
        $ret = AppEnv::getConfigValue($key, '');
        return $ret;
    }
    # ИД файла правил во внутреннем справочнике док-файлов ALFO
    public function getProgramRulesFileId($data=0) {
        $key = $this->module . '_url_fileid';
        $ret = AppEnv::getConfigValue($key, '');
        return $ret;
    }

    # сгенерить наилучшую ссылку для загрузки правил страхования по программе (сначала URL на сайте, потом - из ALFO)
    public function getRulesUrlForClient($data=0) {
        $ruleUrl = $this->getProgramRulesUrl($data);
        if(!empty($ruleUrl)) return $ruleUrl;
        $ruleId = self::getProgramRulesFileId($data);
        if($ruleId>0) {
            $ret = \ClientUtils::createDocFileLink($ruleId);
            return $ret;
        }
        return FALSE;
    }
    # формирует ссылку для загрузки чистого файла Согласие на ПДн, по умолчанию - файл на взрослого ("1")
    public function getUrlPdnSoglasie($data=0) {
        $headdept = $data['headdeptid'] ?? 0;
        $this->_deptReq = OrgUnits::getOuRequizites($headdept, $this->module);
        # writeDebugInfo("$this->module, $headdept - _deptReq: ", $this->_deptReq);
        # writeDebugInfo("data: ", $data);
        if(!empty($this->_deptReq['unified_pdn'])) {
            # будет генерация согласия из подключенрного "единого" шаблона согласий на обр.ПДн
            $urlcode = "unified_pdn|$this->module|$data[stmt_id]";
        }
        else $urlcode = 'soglasie_pdn|1';
        return ClientUtils::createDocFileLink($urlcode);
    }

    /**
    * {upd/2025-02-05} генерация динамического PDF согласия на обраб.ПДн (показ на форме согласования клиентом)
    * с нужными листами Страхователь, Застрах, Предст.ребенка по общей нстройке ПДн для партнера
    * @param mixed $plcid ИД полиса ИЛИ массив данных при печати для онлайн-продаж с сайта ДО создания карточки полиса
    */
    public function generateUnifiedPdn($plcid) {
        # exit("TODO: generate unified PDN for $this->module / policy=$plcid");
        # self::$debug = 1;
        if(is_array($plcid)) $plcData = $plcid;
        elseif(is_numeric($plcid))  $plcData = $this->print_pack($plcid,FALSE,'',TRUE); # только получить все данные
        else throw new Exception("generateUnifiedPdn - надр передавать ИД плоиса ЛИБО массив данных полиса");
        # writeDebugInfo("generateUnifiedPdn data: ", $plcData, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
        # return ['data'=>$plcData];

        $headdept = $plcData['headdeptid'] ?? OrgUnits::getHeadOrgUnit();
        # $this->_deptCfg = AppEnv::deptProdParams($this->module, $headdept);
        $this->_deptReq = OrgUnits::getOuRequizites($headdept, $this->module);
        $baseName = ($this->isEdoPolicy($plcData) || AppEnv::isLightProcess()) ? '/pdn-EDO.xml' : '/pdn.xml';

        # $baseName = '/pdn.xml';
        $cfgPdn = AppEnv::getAppFolder('templates/pdn/') . $this->_deptReq['unified_pdn'] . $baseName;
        # writeDebugInfo("solg PDN xml: [$cfgPdn]");
        $this->printUnifiedPdn('S', $plcData);

        # exit(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($plcData,1) . '</pre>');
        # exit(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($cfgPdn,1) . '</pre>');
        include_once('printformpdf.php');
        $options = array(
           'configfile' => $cfgPdn
          ,'outname'    => 'Soglasie-Na-Obrabotku-PDn.pdf'
          ,'compression' => self::COMPRESS_PDF
        );
        $tmpName  = '';
        if(AppEnv::isApiCall()) {
            $tmpFolder = AppEnv::getAppFolder('tmp/');
            $rand = str_pad(rand(1000, 99999999), 8,'0',STR_PAD_LEFT);
            $options['tofile'] = 1;
            $options['outname'] = $tmpName = $tmpFolder . "PDn-$rand.pdf";
            \FileUtils::cleanFolder($tmpFolder,'',0.5);
        }
        # writeDebugInfo("printPdf options: ", $options);

        PM::$pdf = new PrintFormPdf($options,$this);
        # {updt/2025-03-04} на предв.согласии просят не выводить ФИО в зоне подписи - И.Яковлева
        unset($plcData['pholder_fio'], $plcData['insured_fio'], $plcData['insd2_fio'], $plcData['cbenef_fio'], $plcData['sescode']);
        PM::$pdf->AddData($plcData);
        # if(appenv::isApiCall()) writeDebugInfo("data for PDN print: ", $plcData);
        $result = PM::$pdf->Render(true);
        $pdfErr = PM::$pdf->GetErrorMessage();

        if(AppEnv::isApiCall()) {
            if(is_file($tmpName)) {
                $ret = [
                  'result'=>'OK',
                  'filename'=>'soglasie-PDN.pdf',
                  'filesize'=> filesize($tmpName),
                  'filebody'=> base64_encode(file_get_contents($tmpName))
                ];
                \AppAlerts::resetAlert('pdf_pdn', "Ошибка при генерации PDF устранена");
            }
            else {
                $ret = ['result'=>'ERROR', 'message' =>'Не удалось создать PDF'];
                \AppAlerts::raiseAlert('pdf_pdn', "Ошибка при генерации PDF: $pdfErr");
            }

            return $ret;
        }
        exit;
    }
    # переобъявить в своем модуле при нужде печсатать что-то срзу после страниц полиса
    public function pagesAfterPolicy(&$dta) {
    }
    public function viewOtherPolicies() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        #$pno = \AppEnv::$db->select(PM::T_POLICIES,['where'=>['stmt_id'=>$plcid],'fields'=>'policyno','singlerow'=>1]);
        # writeDebugInfo("pno ", $pno);
        #DataFind::checkCumLimitions($this, ($pno['policyno']?? $plcid), TRUE);
        DataFind::checkCumLimitions($this, $plcid, TRUE);
    }
    # стандартный расчет ожидаемого общего взноса за весь срок страхования
    public function getTotalPremium($data=0, $inRub=FALSE) {
        $term = $rassrochka = $prem = 0;
        $currency = 'RUB';

        if (!isset($this->_rawAgmtData['policy_prem'])) {
            if (!empty($data['stmt_id']))
                $this->loadPolicy($data['stmt_id'],0);
        }
        if (isset($this->_rawAgmtData['term'])) {
            $term = max(1, intval($this->_rawAgmtData['term']));
            $currency = $this->_rawAgmtData['currency'];
            $rassrochka = $this->_rawAgmtData['rassrochka'];
            $prem = $this->_rawAgmtData['policy_prem'];
        }
        if($prem > 0) {
            # TODO: переписать с учетом возможных досрочных окончаний рисков инвал, КЗ....
            $ret = $prem;

            if($rassrochka > 0) {
                $ret = 0;
                $kYear = floor(12 / $rassrochka);
                # $ret = $prem * $term * $kYear;
                $mainRskCounted = FALSE;

                foreach($this->_rawPolicyRisks as $rsk) {
                    $years = RusUtils::RoundedYears($rsk['datefrom'], $rsk['datetill']);
                    if($rsk['rtype'] === PM::RSKTYPE_MAINPACK) {
                        if($mainRskCounted) continue; # осн.риск уже учел
                        $ret += $rsk['riskprem'] * $term * $kYear;
                        # writeDebugInfo("added fore main risk $rsk[riskprem] * $term * $kYear");
                        $mainRskCounted = TRUE;
                    }
                    else {
                        $ret += $rsk['riskprem'] * $years * $kYear;
                        # writeDebugInfo("added for $rsk[riskid] $rsk[riskprem] * $years * $kYear");
                    }
                }
            }

            if(!in_array($currency, ['RUR','RUB']) && $inRub)
                $ret *= AppEnv::getConfigValue('intrate_usd',60);
            return $ret;
        }
        return 0;
    }

    # {upd/2023-12-18} AJAX request - задание повышенного АВ
    public function setExtendedAv() {
        PlcUtils::setExtendedAv($this); # перенаправляю в plcutils.php
    }
    # для генерации сссылки на загрузку Заявления на страхование
    public function hasStatement($plcdata, $mode=FALSE) {
        return FALSE;
    }
    # refreshData(): AJAX запрос на обновление данных в плоисе с просроченным выпуском
    # надо реализовать свой в backend модулях нужных плагинов (где нет отдельного калькулятора!
    public function __refreshData() {
        exit('1' . AjaxResponse::showError('Функционал для данного продукта ещё не реализован'));
    }

    # вернет TRUE если в полисе есть риски НЕ-смерти (ВП - сам Застрахованный, не ребенок!)
    public function existNonDeathRisks() {
        if(is_array($this->_rawPolicyRisks))
        foreach($this->_rawPolicyRisks as $rsk) {
            if(stripos($rsk['riskid'], 'death')===FALSE && stripos($rsk['riskid'], 'child')===FALSE)
                return TRUE;
        }
        return FALSE;
    }
    # вернет TRUE если в полисе есть риски смерти
    public function existDeathRisks() {
        if(is_array($this->_rawPolicyRisks))
        foreach($this->_rawPolicyRisks as $rsk) {
            if(stripos($rsk['riskid'], 'death')!==FALSE )
                return TRUE;
        }
        return FALSE;
    }
    # {upd/2024-03-12} получает "категорию страхования у программы - invest|nsj|risky|dms (ИСЖ, НСЖ, рисковое, ДМС)
    public function getInsuranceType($plcdata=0) {
        if(!empty($this->product_type)) return $this->product_type;
        if( !empty(PlcUtils::$uwProdType) ) return PlcUtils::$uwProdType;
        # if(!$plcdata) $plcdata = $this->_rawAgmtData;
        if(in_array($this->module, ['invins','investprod'])) return PM::PRODTYPE_INVEST;
        if(in_array($this->module, ['nsj','plgkpp'])) return PM::PRODTYPE_NSJ;
        if(in_array($this->module, ['trmig','madms'])) return PM::PRODTYPE_DMS;
        if(in_array($this->module, ['irisky'])) return PM::PRODTYPE_RISKY;
        return '';
    }
    # получает разрешение онлайн-ввода инвест-анкеты в контексте орг-юнита
    public function isInvAnketaOnlineEnabled($deptid=0) {
        $globalVal = Appenv::getConfigValue('invest_anketa_online', FALSE);
        if(!$globalVal) return FALSE;

        if(!$deptid) {
            if(!empty($this->_rawAgmtData['headdeptid'])) $deptid = $this->_rawAgmtData['headdeptid'];
            else $deptid = OrgUnits::getPrimaryDept();
        }
        $partnerDta = OrgUnits::getOuRequizites($deptid, $this->module);

        return ($partnerDta['b_invanketa_online'] ?? 1);
    }

    /**
    * Стандартный вызов проверки наличия необх.файлов для указанного события.
    * При необходимости создай свой метод в бэкенде
    * @param $eventId строчный ID события
    */
    public function checkMandatoryFiles($eventId) {
        BusinessProc::checkMandatoryFiles($this,$eventId);
    }
    /**
    * {upd/2024-08-08} отработка AJAX запроса - "Покажи график платежей"
    * перенаправляю в AutoPayments
    * @param mixed $pars
    */
    public function viewPayPlan($pars = FALSE) {
        # 1) сначала проверка прав на просмотр !
        $id = \AppEnv::$_p['id'] ?? 0;
        if(!$id) exit('Wrong Call');
        $access = $this->checkDocumentRights($id);
        if(!$access) exit("No rights!");
        \AutoPayments::viewPayPlan($pars);
    }

    /**
    *  получить размер одного взноса в каждом году
    * @param mixed $policyid - ID полиса
    * @since 1.116 (2024-10-03)
    */
    public function getPremiumsInYear($policyid) {
        $plcdata = $this->loadPolicy($policyid,0);
        $term = $plcdata['term'];
        $prem = $plcdata['policy_prem'];

        if($plcdata['rassrochka']<=0) return [$prem];

        $ret = [$prem];
        if($plcdata['termunit'] ==='M') $term = floor($term/12);

        # return $plcdata;

        $riskRecs = $this->loadPolicyRisks($policyid,'raw');
        # return $riskRecs;
        for($year=1; $year<=($term-1); $year++) {
            $sumPrem = 0;
            $mainAdded = $wopAdded = FALSE;
            $startYearDate = AddToDate($plcdata['datefrom'],$year,0,0);
            foreach($riskRecs as $oneRec) {
                if($startYearDate <= $oneRec['datetill']) {
                    if($oneRec['rtype'] === \PM::RSKTYPE_MAINPACK) {
                        if(!$mainAdded) {
                            $sumPrem += floatval($oneRec['riskprem']);
                            $mainAdded = TRUE;
                        }
                    }
                    else {
                        if(stripos($oneRec['riskid'], '_wop')!==FALSE) {
                            # ОУСВ доп-риски: также брать сумму только от первого в комплекте (инвал ОУСВ + смерть ЛП ОУСВ)
                            if(!$wopAdded) {
                                $wopAdded = TRUE;
                                $sumPrem += floatval($oneRec['riskprem']);
                            }
                        }
                        else
                            $sumPrem += floatval($oneRec['riskprem']);
                    }
                }
            }
            $ret[] = $sumPrem;
        }
        return $ret;
    }
    # AJAX запрос выполнения переключения режима авто-оплаты - on|off
    public function TurnAutoPayments() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $data = $this->loadPolicy($plcid, -1);

        $access = $this->checkDocumentRights();
        if($access < 1) { appEnv::echoError('err-no-rights'); exit; }
        $active = AutoPayments::getAutoPayState($this->module, $plcid);
        if($active === FALSE) exit('1' . AjaxResponse::showError('Операция недопустима!'));
        $newMode = AppEnv::$_p['value'] ?? 'off';
        if($active == AutoPayments::STATE_ACTIVE && $newMode == 'on') exit('1' . AjaxResponse::showError('Режим авто-оплаты уже активен'));
        if($active == AutoPayments::STATE_NOT_ACTIVE && $newMode == 'off') exit('1' . AjaxResponse::showError('Режим авто-оплаты уже неактивен'));
        $result = AutoPayments::setAutoPayState($this->module, $plcid, $newMode);
        $cliMsg = ($newMode === 'on') ? 'Режим авто-оплаты включен' : 'Режим авто-оплаты отключен';
        if($result) AppEnv::logEvent($this->log_pref.'AUTOPAY '.strtoupper($newMode),$cliMsg,0,FALSE, $this->module);
        if($result) exit('1' . AjaxResponse::showMessage($cliMsg));
        else exit('1' . AjaxResponse::showMessage('Операция не произведена'));

    }

    # {upd/2025-01-31} Вернет TRUE если страхователь - ФЛ
    public function pholderIsFL($data=0) {
        $dta = (is_array($data)&& count($data)) ? $data : $this->printdata;
        if(AppEnv::isApiCall() && !isset($dta['insurer_type']))
            $dta['insurer_type'] = 1; # Для вызовов API если не передан insurer_type, считаю что ФЛ
        $ret = (($dta['insurer_type'] ?? '')==1);
        # writeDebugInfo("pholderIsFL returns: [$ret], data: ", $dta);
        return $ret;
    }
    # {upd/2025-01-30} вернет TRUE при наличии Взрослого Зстрахованного (не равного страхователю)
    public function separateInsured($data=0) {
        # self::$debug = 1;
        $dta = (is_array($data)&& count($data)) ? $data : $this->printdata;
        if(self::$debug) writeDebugInfo("separateInsured, dta: ", $dta);
        if(!isset($dta['stmt_id'])) {
            if(self::$debug) writeDebugInfo("returning FALSE");
            return FALSE;
        }
        if (!empty($dta['equalinsured'])) {
            if(self::$debug) writeDebugInfo("returning FALSE");
            return FALSE;
        }

        $ret = TRUE;
        $insured = Persons::getPersonData($dta['stmt_id'],'insd');
        if(!isset($insured['id']))
            $insured = Persons::getPersonData($dta['stmt_id'],'child');
        if(!empty($insured['birth'])) {
            $insdAge = \RusUtils::yearsBetween($insured['birth'], $dta['datefrom']);
            if($insdAge < PM::ADULT_START_AGE || $insured['ptype'] === 'child') $ret = FALSE; # осн.застрах-ребенок
        }
        else {
            $ret = TRUE; # взрослый
        }
        if(self::$debug) writeDebugInfo("separateInsured returns: [$ret]");
        return $ret;
    }

    /**
    * {upd/2025-02-04} - подключение печати единой формы согласия на обработку ПДн (в заявл-е, в полис - в зав. от переданного режима $moda
    * @param mixed $moda # что в данный момент печатается: 'S': standalone (только приготовить данные), P-полис, Z-заявление
    * @param mixed $dta массив данных для печати
    * @return mixed
    */
    public function printUnifiedPdn($moda, &$dta) {
        $pdn_output = $this->_deptCfg['pdn_output'] ?? 'P';
        # exit(__FILE__ .':'.__LINE__.' printUnifiedPdn _deptCfg:<pre>' . print_r($this->_deptCfg,1) . "</pre> moda: $moda, pdn_output=$pdn_output");
        if(empty($this->_deptReq['unified_pdn']) ) return; # печать согласий ПДн выключена

        if ($moda==='S' || $pdn_output===$moda || ($pdn_output==='Z' && $moda==='P' && !$this->hasStatement($dta)) ) {
            # подключена печать единой формы согласия ПДн
            $basePdn = 'pdn.xml'; # переделал на единый файл, вся логика подключения отдельных листов - внутри него
            $pdnSet = $this->_deptReq['unified_pdn'];

            $dta['pdn_insrphone_email'] = ($dta['insremail'] ?? '') . ' / ' . ($dta['insrphones'] ?? $dta['insr_allphones'] ??
              plcUtils::buildAllPhones('insr', $dta, TRUE)); # на листе pholder-adv
            # exit(__FILE__ .':'.__LINE__.' PDN, data:<pre>' . print_r($dta,1) . '</pre>');
            if($childType = $this->policyHasChild()) {
                # готовлю печатные поля по Представителю ребёнка
                $deleg = $dta['child_delegate'] ?? '';

                if(is_numeric($childType)) $childType = 'child';
                # данные ребенка могут сидеть в записи child, а могут в insd (осн.Застрахованный, например в Азбуке Жизни)
                $dta['pdn_child_fullname'] = $dta[$childType .'fullname'] ?? $dta[$childType .'_fullname'] ?? $dta['child_fullname'] ?? $dta['childfullname'] ?? '';
                $dta['pdn_childfulladdr'] = $dta[$childType .'fulladdr'] ?? $dta['childfulladdr'] ?? '';
                $dta['pdn_childfulldoc'] = $dta[$childType .'fulldoc'] ?? $dta['childfulldoc'] ?? '';
                if($deleg === 'Y') { # страхователь
                    if(empty($dta['child_delegate_name'])) $dta['child_delegate_name'] = $dta['insurer_fullname'] ?? '';
                    if(empty($dta['child_delegate_fulldoc'])) $dta['child_delegate_fulldoc'] = $dta['insrfulldoc'] ?? '';
                    if(empty($dta['child_delegate_fulladdr'])) $dta['child_delegate_fulladdr'] = $dta['insrfulladdr'] ?? '';
                    if(empty($dta['cbenef_fio'])) $dta['cbenef_fio'] = $dta['pholder_fio'] ?? ''; # ФИО предст=ФИО страховатtля
                }
                elseif($deleg === 'Z') { # Застрахованный Взрослый
                    if(empty($dta['child_delegate_name'])) $dta['child_delegate_name'] = $dta['insured_fullname'] ?? '';
                    if(empty($dta['child_delegate_fulldoc'])) $dta['child_delegate_fulldoc'] = $dta['insdfulladdr'] ?? '';
                    if(empty($dta['child_delegate_fulladdr'])) $dta['child_delegate_fulladdr'] = $dta['insdfulldoc'] ?? '';
                    if(empty($dta['cbenef_fio'])) $dta['cbenef_fio'] = $dta['insured_fio'] ?? ''; # ФИО предст=ФИО ЗВ
                }
                else  { # отдельно описанный представитель ЗР
                    if(empty($dta['child_delegate_name'])) $dta['child_delegate_name'] = $dta['cbeneffullname'] ?? '';
                    if(empty($dta['child_delegate_fulldoc'])) $dta['child_delegate_fulldoc'] = PlcUtils::buildFullDocument($dta,'cbenef',2); # с кодом подразд.
                    if(empty($dta['child_delegate_fulladdr'])) $dta['child_delegate_fulladdr'] = $this->buildFullAddress($dta, 'cbenef','', TRUE);
                }
                if(empty($dta['child_delegate_name'])) {
                    exit(__FILE__ .':'.__LINE__.' empty child_delegate_name:<pre>' . print_r($dta,1) . '</pre>');
                }
                if(empty($dta['cbenef_fio']) && !empty($dta['child_delegate_name']))
                    $dta['cbenef_fio'] = RusUtils::MakeFIO($dta['child_delegate_name']);
            }
            if(SuperAdminMode() && empty($dta['pdn_child_fullname']) && is_file('tmp/_debug.txt')) exit(__FILE__ .':'.__LINE__
              .' no pdn_child_fullname, PDN data:<pre>' . print_r($dta,1) . "</pre>childPref: [$childType]");

            if($moda!=='S') {
                $pdnXmlFile = AppEnv::getAppFolder('templates/pdn/') . $pdnSet . '/' . $basePdn;
                # exit ("PDN base : $pdnXmlFile");
                if(is_file($pdnXmlFile)) {
                    AppAlerts::resetAlert("NO-UNIFIED-PDN-$pdnSet");
                    $pdnXmlFile = PlcUtils::getTemplateEDO($pdnXmlFile); # добавит -EDO к имени если печатается ЭДО договор
                    # exit(__FILE__ .':'.__LINE__.' data:<pre>' . print_r($dta,1) . '</pre>');
                    PM::$pdf->AppendPageDefFromXml($pdnXmlFile);
                }
                else AppAlerts::raiseAlert("NO-UNIFIED-PDN-$pdnSet",
                  "Настройка единого согласия ПДн $pdnSet : нет файла $pdnXmlFile");
            }
            # exit("PDN added");
        }
        /*
        else exit(__FILE__ .':'.__LINE__.' NOI PDN :<pre>' . print_r($this->_deptReq,1)
          . "deptCfg:" . print_r($this->_deptCfg,1). "</pre> moda: $moda, pdn_output=$pdn_output");
        */
    }
    /**
    * {upd/2025-02-07} Вернет TRUE или 'insd2' если есть 2-ой взрослый Застрахованный
    * callback при печати единых согласий ПДн
    * @param mixed $dta массив сформированных для печати данных
    */
    public function Insured2Exists($dta=0) {
        if(is_array($this->printdata) && count($this->printdata)>0) $dta = $this->printdata;
        $ret = empty($dta['insd2fam']) ? FALSE : 'insd2';
        # exit(__FILE__ .':'.__LINE__." Insured2Exists returns: [$ret], data:<pre>" . print_r($dta,1) . '</pre>');
        # writeDebugInfo(__FUNCTION__ . " returns: [$ret], data: ", $dta);
        return $ret;
    }
    public function Child2Exists($dta=0) {
        if(is_array($this->printdata) && count($this->printdata)>0) $dta = $this->printdata;
        $ret = empty($dta['child2fam']) ? FALSE : 'child2';
        # writeDebugInfo(__FUNCTION__ . " returns: [$ret], data: ", $dta);
        return $ret;
    }
    public function Child3Exists($dta=0) {
        if(is_array($this->printdata) && count($this->printdata)>0) $dta = $this->printdata;
        $ret = empty($dta['child3fam']) ? FALSE : 'child3';
        # writeDebugInfo(__FUNCTION__ . " returns: [$ret], data: ", $dta);
        return $ret;
    }
    # {upd/2025-02-12} стандартная выдача Мед.деклараций Застрахованного(ых)
    # для вызова со страницы ЭДО согласования, когда пришел запрос на загрузку мед.декларации
    public function printMedDeclar($plcid) {
        # writeDebugInfo("printMedDeclar($plcid) _p: ", AppEnv::$_p);
        $plcdata = $this->loadPolicy(intval($plcid), 'print');
        # exit(__FILE__ .':'.__LINE__.' $plcdata:<pre>' . print_r($plcdata,1) . '</pre>');
        if(method_exists($this, 'getMedDeclarBaseName')) {
            $outFile = $this->getMedDeclarBaseName();
            # writeDebugInfo("med declar base name from getMedDeclarBaseName(): [$outFile]");
        }
        else {
            $outFile = 'EDO-declar-insured.pdf'; # согласие только для азрослого
            if(!empty($plcdata['equalinsured'])) $adult = TRUE;
            else $adult = FALSE;
            $child = FALSE;
            if(!empty($plcdata['insdbirth'])  && PlcUtils::isDateValue($plcdata['insdbirth'])) {
                # есть отдельный застрахованный (даже если сам страхователь тоже застрахованный)
                $years = \RusUtils::yearsBetween($plcdata['insdbirth'], $plcdata['datefrom']);
                if($years < PM::ADULT_START_AGE) $child = TRUE;
                else $adult = TRUE;
            }
            if($child) {
                if($adult) # есть оба - ЗВ и ЗР
                    $outFile = 'EDO-declar-insured-adult-child.pdf';
                else
                    $outFile = 'EDO-declar-insured-child.pdf';
            }
        }
        if(empty($outFile)) exit("Файл мед-декларации не настроен!");
        $fullName = AppEnv::getAppFolder("plugins/$this->module/printcfg/") . $outFile;
        # ИДЕЯ: если в папке плагина файла нет, можно поискать "стандартный в templates/declarations/ ?
        # exit("main:$main, declarFile: $fullName, is exist: [".is_file($fullName). ']<br>');
        if(is_file($fullName)) AppEnv::sendBinaryFile($fullName);
        else exit("Извините, файл мед.декларации не найден: $outFile");
        # exit(__FILE__ .':'.__LINE__."plcid=$plcid plcData:<pre>" . print_r($plcdata,1) . '</pre>');
    }
    # добавляет текст в поле special_conditions
    public function addSpecConditionsText(&$dta, $sText, $newLine = TRUE) {
        $curText = $dta['special_conditions'] ?? '';
        if($curText === 'Нет') $curText = '';
        if(!empty($curText)) $curText .= ($newLine ) ? "\n":' ';
        $dta['special_conditions'] = $curText . $sText;
    }

    # втсавит текст в НАЧАЛО печатного поля special_conditions
    public function prependSpecConditionsText(&$dta, $sText, $newLine = TRUE) {
        $curText = $dta['special_conditions'] ?? '';
        if($curText === 'Нет') $curText = '';
        if($curText === '') $dta['special_conditions'] = $sText;
        else
            $dta['special_conditions'] = $sText . ($newLine ? "\n":' ') . $curText;

    }
    # Если текстов для особ.условий не нашлось, надо вывести "Нет"
    public function finalizeSpecCondText(&$dta) {
        if(!empty($dta['reinvest'])) \Reinvest::addSpecConditionText($this, $dta);
        if(!empty($this->_deptCfg['spc_conditions'])) return;
        if(empty($dta['special_conditions'])) $dta['special_conditions'] = 'Нет';
        else {
            # if(mb_substr($dta['special_conditions'],-1) !=="\n")
            # $dta['special_conditions'] .= "\n "; # для режима JUSTIFY
        }
    }
    /**
    * стандартная перед выполнением viewagr
    *
    */
    public function beforeViewAgr() {
        if($this->bReinvest) \Reinvest::addCode($this);
    }

    # {upd/2025-03-17} отправка в ПП на доп-проверку
    public function startDopCheck() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $data = $this->loadPolicy($plcid, -1);
        if($this->_userLevel === NULL) $this->_userLevel = AppEnv::$auth->getAccessLevel($this->privid_editor);
        if($this->_userLevel < PM::LEVEL_OPER) exit('No access, ['.$this->_userLevel . ']');
        $arUpdt = [
          'stateid' => PM::STATE_DOP_CHECKING
        ];
        $updResult = PlcUtils::updatePolicy($this->module, $plcid, $arUpdt);
        if($updResult) {
            agtNotifier::send($this->module,events::DOP_CHECK_START,$data);
            AppEnv::logEvent($this->log_pref.'ON_CHECKING', "Отправка на доп.проверку в ПП",0,$plcid,FALSE,$this->module);
        }
        exit('1' . $this->refresh_view($plcid,FALSE));
    }
    # {upd/2025-03-20} после проверок ПП разрешает дальнейшее оформление полиса (проверка пройдена)
    public function dopCheckSuccess() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $data = $this->loadPolicy($plcid, -1);
        if($this->_userLevel === NULL) $this->_userLevel = AppEnv::$auth->getAccessLevel($this->privid_editor);
        if($this->_userLevel < PM::LEVEL_IC_ADMIN) exit('No access :['.$this->_userLevel . ']');

        $lightUwReason = \UwUtils::isLightUwReason($this->module, $plcid);
        # $newStateId = $lightUwReason ? PM::STATE_PROJECT : PM::STATE_DOP_CHECK_DONE;
        # exit('1' . AjaxResponse::showMessage("$plcid, lightUwReason=[$lightUwReason] newStateId=[$newStateId]: <pre>" . print_r(0,1) . '</pre>'));

        $updt = [
          'stateid' => PM::STATE_DOP_CHECK_DONE,
          # 'bpstateid' => 0,
          # 'reasonid' => 0,
          'bptype'=>'',
          # 'med_declar' => '', # чтобы вернуть в самое исходное состояние
        ];
        if($lightUwReason) $updt['reasonid'] = 0; # Сброс нужды на андеррайтинг
        $dopLog = ($lightUwReason) ? 'можно продолжить оформление':'необходим андеррайтинг';
        $updResult = PlcUtils::updatePolicy($this->module, $plcid, $updt);
        if($updResult) {
            agtNotifier::send($this->module,events::DOP_CHECK_DONE,$data);
            AppEnv::logEvent($this->log_pref.'CHECKING_OK', "Доп.проверка пройдена, $dopLog",0,$plcid,FALSE,$this->module);
            exit('1' . $this->refresh_view($plcid,FALSE));
        }
        else exit('1' . AjaxResponse::showError('err-saving-error'));
    }
    # {upd/2025-04-01} после проверок ПП прерывает дальнейшее оформление полиса для андеррайтинга или исправления
    public function dopCheckFail() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $data = $this->loadPolicy($plcid, -1);
        if($this->_userLevel === NULL) $this->_userLevel = AppEnv::$auth->getAccessLevel($this->privid_editor);
        if($this->_userLevel < PM::LEVEL_IC_ADMIN) exit('No access :['.$this->_userLevel . ']');
        $updt = [
          'stateid' => PM::STATE_DOP_CHECK_FAIL,
        ];

        $updResult = PlcUtils::updatePolicy($this->module, $plcid, $updt);
        if($updResult) {
            agtNotifier::send($this->module,events::DOP_CHECK_FAIL,$data);
            AppEnv::logEvent($this->log_pref.'CHECKING_OK', "Доп.проверка НЕ пройдена",0,$plcid,FALSE,$this->module);
            exit('1' . $this->refresh_view($plcid,FALSE));
        }
        else exit('1' . AjaxResponse::showError('err-saving-error'));
    }
    /**
    * AJAX запрос - загрузка данных по клиенту на форму ввода ПДн (сразу после калькулятора)
    *
    */
    public function loadClientData() {
        $clientid = AppEnv::$_p['clientid'] ?? 0;
        $prefix = AppEnv::$_p['prefix'] ?? AppEnv::$_p['pref'] ?? 'insr';
        if(!$clientid) exit('loadClientData no client id in params');
        $result = \BindClient::getClientData( $clientid,$prefix, $this->getUserLevel() );
        # writeDebugInfo("loadClientData(pref=$prefix, cli=$clientid result: ", $result);
        exit('1' . $result);
    }
    # {updt/2025-05-22} AJAX запрос на изменение СС по риску (прилетело от андеррвайтера)
    public function updateRiskSa() {
        $id = AppEnv::$_p['id'] ?? 0;
        $riskid = AppEnv::$_p['riskid'] ?? '';
        $savalue = floatval(AppEnv::$_p['value'] ?? 0);
        $mylevel = $this->getUserLevel('oper');
        if($mylevel < PM::LEVEL_UW) exit('1'. AjaxResponse::showError('err-no-rights'));
        $this->_rawAgmtData = PlcUtils::loadPolicyData($this->module, $id);
        if(!in_array($this->_rawAgmtData['stateid'], [PM::STATE_UNDERWRITING, PM::STATE_PROJECT, PM::STATE_IN_FORMING]))
            exit('1'. AjaxResponse::showError('err-wrong-document-state'));

        # TODO: занести СС в риск
        $riskRec = AppEnv::$db->select(PM::T_AGRISKS, ['where'=>['stmt_id'=>$id, 'riskid'=>$riskid], 'singlerow'=>1]);
        if(!isset($riskRec['risksa']))
            exit('1'. AjaxResponse::showError('err_risk_not_in_policy'));
        if($savalue <= 0)
            exit('1'. AjaxResponse::showError('err_bad_sa_value'));
        if(floatval($riskRec['risksa']) == $savalue)
            exit('1'. AjaxResponse::showError('err_sa_not_changed'));
        $updt = AppEnv::$db->update(PM::T_AGRISKS, ['risksa'=>$savalue], ['id'=>$riskRec['id'] ]);
        if($updt) {
            $fmtVal = \RusUtils::moneyView($savalue);
            AppEnv::logEvent($this->log_pref.'CHANGE_RISK_SA',"Изменение Стр.Суммы по риску $riskid",false,$id,false,$this->module);
            exit('1' . AjaxResponse::setHtml('view_'.$riskid, $fmtVal));
        }
        else exit('1' . AjaxResponse::showError('err-saving-error'));
        # exit('1' . AjaxResponse::showMessage('Risk: <pre>' . print_r($riskRec,1) . '</pre>'));
        # exit('1' . AjaxResponse::showMessage("TODO: updateRiskSa $id $riskid $savalue level:$mylevel"));
    }
    public function getPolicyHeadDept($params = FALSE) {
        if(!$params) $params =& AppEnv::$_p;
        $plcid = $params['stmt_id'] ?? $params['id'] ?? 0;
        if(!empty($params['headdeptid'])) $deptHead =$params['headdeptid'];
        elseif(!empty($plcid))
            $deptHead = AppEnv::$db->select(PM::T_POLICIES, ['fields'=>'headdeptid',
              'where'=>['module'=>$this->module, 'stmt_id'=>intval($plcid)],
              'singlerow'=>1,
              'associative'=>0
            ]);
        else $deptHead = OrgUnits::getPrimaryDept(); # getHeadOuId();
        # writeDebugInfo("returning head-dept: ", $deptHead, $params);
        return $deptHead;
    }
    # аннуляция полиса (для вызовов из онлайн-оформления клиентом)
    public function cancelPolicy($policyid=0) {
        if(!$policyid) $policyid = AppEnv::$_p['id'] ?? 0;
        $ret = BusinessProc::cancelPolicy($this, $policyid);
        return $ret;
    }

    # {upd/2025-09-24} AJAX запрос - открыть диалоговое окно выбора - кого будем исправлять НЕ ИСПОЛЬЗУЕТСЯ!
    public function openModifyPdata() {
        $plcid = AppEnv::$_p['id'] ?? 0;
        $response = PlcUtils::buildModifyPdataDlg($this->module, $plcid);
        exit('1' . $response);
    }
    # отправляем письмо о проверке обнаруженных комплаенс-рисков
    public function sendToCompliance() {
        $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        $dta = $this->loadPolicy($stmtid,'print');
        $access = $this->checkDocumentRights($stmtid);
        if ($access<1) { AppEnv::echoError('err-no-rights-document'); return; }

        return BusinessProc::sendToCompliance($this, $dta);
    }
    # {upd/2029-09-30} Комплаенс сказали - ВСЕ ОК!
    public function setComplianceOk() {
        $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        $dta = $this->loadPolicy($stmtid,'print');
        $access = $this->checkDocumentRights($stmtid);
        if ($access<1) { AppEnv::echoError('err-no-rights-document'); return; }
        return BusinessProc::setComplianceOk($this, $dta);
    }
    # {upd/2029-09-30} Доработка, стартуем согласование клиентом наших изменений в полисе
    public function startReEDO() {
        $stmtid = isset($this->_p['id']) ? $this->_p['id'] : 0;
        EdoRework::startEdo($this->module, $stmtid);
    }

    # {upd/2025-11-10} картинка с подписью подписанта для анкет
    public static function getAnketaSignerImage() {
        if(!empty(self::$anketa_signer_image)) $ret = self::$anketa_signer_image;
        else $ret = 'signers/Mustafaeva.png';
        return $ret;
    }
    # {upd/2025-11-20} Если юзер - в роли менеджера в аг.сети, вернет все учетки агентов в подразд-ии и дочках
    public function getMyAgents($onlyActive=TRUE) {

        if(!$mgrMode=AppEnv::getConfigValue('lifeag_mgrmode',FALSE)) return FALSE;
        $myLevel = $this->getUserLevel();
        # writeDebugInfo("mgrMode=$mgrMode, my level: ", $myLevel);
        if(AppEnv::getMyMetaType() != OrgUnits::MT_AGENT) return FALSE;
        if($myLevel != PM::LEVEL_MANAGER) return 0;
        $operRole = ($mgrMode == 1) ? '' : '*';
        $arRet = \Libs\AgentUtils::amIManagerWithAgents($this->module,TRUE,$operRole);
        return $arRet;
    }
} # PolicyModel end