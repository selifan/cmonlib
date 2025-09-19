<?php
/**
* @name app/pm.php
* константы для функций, коды статусов полиса, причин постановки на андеррайтинг...
* @version 2025-09-18
*/
class PM {
    const VERSION = '1.49';
    const INVEST = 'investprod'; # модуль ИСЖ
    const INVEST2 = 'invins'; # модуль ИСЖ-2
    const RSK_MAIN_ENDOWMENT = 'main_endowment'; # для "универсальных" ИД рисков (поле alf_agmt_risks.rtype , НО НЕ riskid !!!)
    const RSK_ENDOWMENT = 'endowment'; # главный риск дожития
    const RSK_DEATH_ANY = 'death_any'; # гл.риск смерти ЛП
    const RSK_MAIN_DEATH = 'main_death';
    const RSK_DEATH_ACC = 'death_acc';
    const RSK_ADD_DEATH = 'add_death';
    const RSK_ADD_INVAL = 'add_invalid';
    const RSK_ADD_CHILD = 'add_child';
    const RSK_INVAL12_WOP = 'disability_12_wop';

    const STD_COMP_PHONE = '8(495) 232-0100, 8(800)100-0545';
    const STD_COMP_EMAIL = 'az.info@zettains.ru';

    # Базовые типы продуктов, для уазания специфики в применении алгоритмов
    const PRODTYPE_INVEST = 'invest'; # ИСЖ (не делаем поиск других полисов на того же застрахованного (это не будет основанеим для UW))
    const PRODTYPE_LIFE = 'life'; # чисто стр-е жизни
    const PRODTYPE_NSJ = 'nsj'; # НСЖ
    const PRODTYPE_RISKY = 'risky'; # рисковый продукт
    const PRODTYPE_DMS = 'dms'; # ДМС

    const ADULT_START_AGE = 18; # стартовый возраст взрослого
    const TEXT_RISK_NOT_INCLUDED = 'Договором не предусмотрен';
    # имена таблиц БД:
    const T_DEPTS ='arjagent_depts'; # Орг-юниты/подразделения, структура компании = устар.[appEnv::TABLE_DEPTS]
    const T_USERS ='arjagent_users'; # учетки пользователей
    const T_EVENTLOG ='arjagent_eventlog';  # журнал
    const T_CURRATES ='currates';  # история курсов валют
    const T_CURLIST ='curlist';  # {upd/2024-05-17} список нужных валют
    const T_CURRATES_HIST = 'currates_hist'; # история курсов валют - история загрузок
    const T_RISKS = 'alf_agmt_risks'; # единый справочник рисков
    const T_POLICIES = 'alf_agreements';
    const T_INDIVIDUAL = 'alf_agmt_individual'; # страхователи, застрахованные. (ФЛ и ЮЛ)
    const T_AGMTDATA   = 'alf_agmt_data'; # доп.данные по договорам
    const T_SPECDATA   = 'alf_agmt_specdata'; # Хранилище специфич.данных (больших)
    const T_BENEFICIARY = 'alf_agmt_beneficiary'; # выгодоприобретатели
    const T_AGRISKS   = 'alf_agmt_agrisks'; # риски в полисах (ст.суммы, премии)
    const T_UPLOADS   = 'alf_agmt_uploads'; # список файлов сканов,XML к договорам
    const T_ANYFILES = 'anyfiles'; # файлы к любым модулям
    const T_COUNTRIES = 'alf_countries'; # список стран
    const T_REGIONS = 'regions'; # регионы
    const T_PRODCFG   = 'alf_product_config'; # настройки номеров приказов, должность/ФИО подписанта от СК и т.д.
    const T_DEPT_PROD = 'alf_dept_product'; # настройки продуктов по подразделениям (банкам)
    const T_DEPT_PROD_PRG = 'alf_dept_prod_sp'; # спец-параметры для продукта/партнера/программы
    const T_OU_PROPS = 'alf_dept_properties'; # базовые параметры головного орг-юнита
    const T_UPLOADEDFILES = 'alf_uploadedfiles'; # файлы шаблонов, правил страхования и т.д. подразделениям (банкам)
    const T_EXPORTCFG = 'alf_exportcfg'; # базовая таблица настройки экспорта продуктов (кодировка - ИД_программы_LISA)
    const T_EXPORTRISKS = 'alf_exportrisks'; # связки "риск ФО - продукт - код риска и группы в LISA"
    const T_APICLIENTS  = 'alf_apiclients'; # внешние клиентв API (и их токены) = jsvc/alofservices::TABLE_API_CLIENTS
    const T_APILOG  = 'alf_api_eventlog'; # лог событий - API запросы и ответы
    const T_EQPAYMENTS = 'alf_eqpayments'; # заявки на онлайн-оплату в сервисе эквайринга
    const T_AGENT_CLIENT = 'alf_agent_client'; # привязки клиентов к агентам
    const T_SAVEDCALC    = 'alf_savedcalculations'; # сохраненные калькуляции
    const T_STMT_RANGES = 'alf_stmt_ranges'; # диапазоны номеров для полисов = appEnv::TABLE_STMTRANGES
    const T_PROMOACTIONS = 'alf_promoactions'; # промо-акции
    const T_LOSSES = 'alf_policylosses'; # зарегистрированные убытки
    const T_ESHOPLOG = 'alf_eshoplog'; # Лог расчетов через eShop
    const T_INVBA  = 'alf_invba'; # список базовых активов (новые инвест-продукты)
    const T_INVSUBTYPES  = 'alf_invsubtypes'; # список привязок - продукт - БА - кодировка - срок страхования
    const T_BURDEN = 'alf_burden'; # коэф-ты нагрузки (для памяток ЦБ)
    const T_PROFESSIONS = 'alf_professions';
    const T_ALERTS = 'alf_appalerts'; # имя сист.таблицы хранилища статусов тревоги
    const T_ALI_TRANCHES = 'ali_tranches'; # таблица синхронизированных данных по траншам из ALI (TODO!)
    const T_CLIENT_CONFIRM = 'alf_client_confirm'; # данные для онлайн-подтверждения клиентом согласия с условиями договора
    const T_SMSLOG = 'alf_smslog'; # журнал отправки СМС
    const T_SMS_CHECKLOG = 'alf_sms_check_log'; # журнал попыток ввода/подбора кода из СМС
    const T_AGENT_LOG = 'agent_eventlog'; # журнал операций агентов
    const T_CURATORS = 'alf_curators'; # Кураторы, агенты
    const T_SENTEMAIL = 'alf_sentemail'; # журнал отправленных email
    const T_LOCKS = 'alf_locks'; # блокировки записей на время операции
    const T_INVANKETA = 'alf_investanketa'; # инвест-анкета (предв. оценка клиента)
    const T_INVANKETAPLIST = 'alf_investanketaplc'; # инвест-анкета - список полисов (много-к одному)
    const T_LEADGEN = 'alf_leadgen'; # ФИО лидогенераторов для полисов
    const T_SPORTS = 'sporting'; # Виды спорта с коэф-тами
    const T_USERPARAMS = 'alf_userparams'; # настройки/параметры юзера
    const T_ICONS_PROGRAMS = 'alf_nsjprograms'; # конструктор - список программ
    const T_ICONS_RISKS = 'alf_nsjprgrisks'; # конструктор - список рисков
    const T_COMMENTS = 'alf_agmt_comment'; # комментарии ко всем полисам
    const T_SIZESTATS = 'sitesize_stats'; # здесь заносим остаток своб.места на дату, для оценки сколько осталось дней
    const T_PLCPACKS = 'alf_plcpackets'; # Пакеты полисов одного клиента
    const T_PLCBIND = 'auto_binded_policies'; # Регистрация авто-привязок полисов в КК

    # таблицы для работы с авто-платежами
    const T_AUTOPAYMENTS = 'alf_autopayments'; # полисы с включенным авто-списанием (авто-платежи)
    const T_PAYPLAN = 'alf_payplan'; # график платежей
    const T_CLIENTS = 'alf_clients'; # список клиентов (для агентов)

    const TABLE_TRANCHES ='alf_tranches'; # таблица дат окон продаж и траншей
    const TABLE_CUMLIR ='alf_cumlir'; # таблица кумулятивных лимитов по рискам (для андеррайтинга по кумул.лимитам)
    # const T_DOCFLOWURL ='alf_docflowurl'; # сохраненные УРЛы карточек СЭД
    const DOCFLOW_REPEAT_UW = 'Отправить на повторное согласование с Андеррайтером'; # {upd/2023-03-21} задать этап - Согласование(UW)
    const DOCFLOW_TONEXT = 'Согласовать След. этап'; # {upd/2023-03-20} задать этап - Ввод в БД(для UW)
    const DOCFLOW_STAGE_UW = 'Согласование'; # этап карточки на согласовании андеррайтером
    const DOCFLOW_TOREWORK = 'Отправить на доработку'; # {upd/2023-03-16} задать этап - на доработку (для UW)
    const DOCFLOW_STAGE_REWORK = 'Доработка'; # {upd/2023-03-31}
    const DOCFLOW_STAGE_FINAL = 'Ввод в БД'; # этап карточки - финальный
    # типы продукта в СЭД
    const DOCFLOW_TP_RISKY = '3'; # Рисковое значение
    const DOCFLOW_TP_NAKOP = '4'; # Накопит.страхование
    const DOCFLOW_TP_BOX   = '5'; # Коробочные продукты
    const DOCFLOW_TP_INVEST = '6'; # Инвест.страхование

    const T_DMS_POLICIES = 'CHECK_POLICY_DMS'; # таблица из Диасофта, поиск ДМС полисов по серии-номеру (А.Пахомова)
    const T_DMS_BSO = 'CHECK_BSO_DMS'; # таблица из Диасофта, БСО (А.Пахомова)
    # типы модулей
    const MODULE_INS   = 'insurance'; # выпуск страховых договоров
    const MODULE_DMS   = 'ins_dms'; # выпуск договоров класса ДМС
    const MODULE_DOCFLOW = 'docflow'; # документооборот
    const MODULE_SERVICE = 'service'; # служебный модуль
    const MODULE_APIREQ = 'apireq'; # сервис запросов во внешние системы через API
    const MODULE_EXPORT = 'export'; # экспорт данных для загрузок в другие ИС
    const MODULE_REPORTS = 'reports'; # Отчеты {upd/2023-04-06}
    const MODULE_BUGTRACK = 'bug_tracker'; # задачи по доработке {upd/2024-08-07}
    const MODULE_OTHER  = 'other';  # все что не классифицируется
    # для атрибута agent_prod:
    const AGT_LIFE = 'AGT_LIFE'; # агентские продукты (Жизнь)
    const AGT_DMS = 'AGT_DMS'; # ДМС
    const AGT_NOLIFE = 'AGT_NOLIFE';# агентские продукты (Не-Жизнь)
    const TYPE_INVEST_LIFE = 'INVEST_LIFE';# инвест-продукты (Жизнь)
    # статусы договора
    const STATE_PROJECT_OL = -20; # проект при оформлении онлайн {upd/2025-09-03}
    const STATE_DRAFT = -10; # черновик {upd/2023-02-28} - new
    const STATE_PAUSED = -2; # приостановлен, требует доп-проверки
    const STATE_DOP_CHECKING = -1; # выполняется доп-проверка
    const STATE_DOP_CHECK_DONE = -3; # доп-проверка пройдена, теперь - на UW
    const STATE_DOP_CHECK_FAIL = -4; # доп-проверка НЕ пройдена
    const STATE_PROJECT = 0; # проект
    const STATE_IN_FORMING = 1; # на оформлении (агентские)
    const STATE_UNDERWRITING = 2; # на андеррайтинге
    const STATE_UWAGREED = 3; # согласовано андеррайтером
    const STATE_UWAGREED_CORR = 3.1; # согласовано с корректировкой/перерасчетом
    const STATE_UW_DATA_REQUIRED = 4; # андеррайтер затребовал доп.данных (полис можно редактировать, но нельзя оплатить/оформить)
    const STATE_IC_CHECKING = 5; # на первичной проверке данных в СК (агентские, перед занесением в СЭД)
    const STATE_POLICY   = 6; # полис
    const STATE_PAYED    = 7;
    const STATE_ANNUL = 9;
    const STATE_CANCELED = 10;
    const STATE_FORMED = 11;
    const STATE_UWDENIED = 12; # НЕ согласовано андеррайтером
    const STATE_COMPLIANCE_CHECK = 30; # NEW: на проверке у Комплайнс (нашлись PEPS-ы, блокированные/террористы)
    const STATE_DISSOLUTED = 50; # расторгнут
    const STATE_DISS_REDEMP = 51; # расторгнут с выкупной
    const STATE_BLOCKED = 60; # заблокирован для дальнейших действий

    # статусы для поля bpstateid (подстатус в контексте бизнес-процессов в СК) - для биз-процессов агентских договоров
    const BPSTATE_RELEASED = 1; # Выпущен (после операции "выпустить полис")
    const BPSTATE_WAITPAYMENT = 5; # Ожидает оплаты
    const BPSTATE_UWREWORK = 6; # {upd/2024-03-28} на доработке UW полис НЕ оплачен (или дата оплаты > МДВ -
    const BPSTATE_TOACCOUNTED = 10; # "Не забудьте отправить на учет!" (когда договор перешел в Оформлен)
    const BPSTATE_ACCOUNTED = 90; # "Направлен к учету"
    const BPSTATE_ACTIVE = 100; # Активный (фин.состояние)

    # а это для ЭДО/ПЭП согласований:
    const BPSTATE_SENTPDN = 50; # послали клиенту ссылку для подтв.согласия на обраб.ПДн (ЭДО-этап 1)
    const BPSTATE_PDN_OK = 51; # Клиент подтвердил согласие на обр. ПДн
    const BPSTATE_PDN_NO = 52; # Клиент НЕ подтвердил согласие на обр. ПДн => уход в аннуляцию договора!

    const BPSTATE_SENTEDO = 60; # послали клиенту ссылку для подтв.согласия с декл. и усл.страх (ЭДО-этап 2)
    const BPSTATE_EDO_OK = 61; # Клиент подтвердил согласие с декл. и усл.страх (ЭДО-этап 2)
    const BPSTATE_EDO_NO = 62; # Клиент отказался подтвердить согласие с декл. и усл.страх
    const BPSTATE_MED_OK = 63; # Клиент отказался подтвердить согласие с декл. и усл.страх

    const BPSTATE_EDO_S3_SENT = 70; # этап 3 (если есть такой) начат
    const BPSTATE_EDO_S3_OK = 71; # подтверждение
    const BPSTATE_EDO_S3_NO = 72; # НЕ подтвержден!

    # Статусы поля substate для доработки
    const SUBSTATE_REWORK = 101; # договор на доработке
    const SUBSTATE_REWORK_DONE = 102; # банк выполнил доработку
    const SUBSTATE_NEED_RECALC = 110; # Запрошен перерасчет андеррайтера

    # TODO: новые статусы для агентских биз-процессов (свободные коды 1,5,8)
    # причины перевода полиса в статус "требует андеррайтинга" (поле reasonid)
    const UW_REASON_DECLARATION = 1; # Соотв-вие "декларации застрахованного" установлено в "НЕТ"
    const UW_REASON_CLIENT_DECLINE = 2; # клиент при ЭДО-согласовании выбрал какое-то несогласие, кроме мед-декларации
    const UW_REASON_PHOLDER_NORUS = 10; # страхователь - не РФ
    const UW_REASON_INSURED_NORUS = 11; # застрахованный - не РФ
    const UW_REASON_CHILD_NORUS   = 12; # застрахованный ребенок- не РФ
    const UW_REASON_BEN_RELATION  = 13; # Родственная связь одного из выгодоприобретателей - "иное"
    const UW_REASON_CBEN_OTHER    = 14; # У ребенка выбран Выгприобр (Представитель)- иное лицо
    const UW_REASON_BEN_UL        = 15; # ВП - ЮрЛицо
    const UW_REASON_INSURED_EXIST = 20; # на застрахованного уже есть полисы (не сегодняшние)
    const UW_REASON_CHILD_EXIST   = 21; # на застрахованного ребенка уже есть полисы (не сегодняшние)
    const UW_REASON_CHILD_DELEGATE = 22; # Страхователь не является Зак.представителем застрахованного ребенка
    const UW_REASON_PEPSCHECK     = 25; # страхователь или застрахованный не прошел PEPs проверку (легкую)
    const UW_REASON_PEPSHARD      = 125; # страхователь или застрахованный не прошел PEPs проверку (тяжелую-террорист, списки СБ)
    const UW_REASON_TAXRES_NORUS  = 26; # Страхователь - не налоговый резидент России
    const UW_REASON_OLD_INSURED   = 27; # Застрахованный старше "не-UW" лимита возраста
    const UW_REASON_END_AGE       = 28; # превыш-е предельного возраста на момент окончания
    const UW_REASON_CBEN_RELATION  = 29; # Родственная связь одного из выгодоприобретателей вместо ребенка (не сын/дочь)

    const UW_REASON_DEATHLIMIT     = 30; # суммарная СС по рискам смерти в данном договоре превышает лимит
    const UW_REASON_DEATHLIMITCUMUL= 31; # суммарная СНС+СЛП по всем полисам (кумулятивный) превысила лимит-1 (фин-анкета)
    const UW_REASON_CHILD_LIMIT    = 32; # лимит по застр.ребенку суммарной СС по риску xxxx по всем полисам превысил порог
    const UW_REASON_RISKLIMIT      = 33; # лимит по риску
    const UW_REASON_TOTALPAYMENT   = 34; # Суммарный взнос по осн.рискам превышает лимит

    # @since 1.25 {upd/2020-05-29}:
    const UW_REASON_DEATHLIMITCUMUL2 = 35;# суммарная СС по СНС+СЛП по всем полисам превысила лимит-2 (фин-анкета+справки НДФЛ)
    const UW_REASON_RISK_MISSED    = 36;# Не включен обязательный риск

    const UW_REASON_PRGSALIMIT     = 40; # превышен лимит СС, установленный стр.программой
    const UW_REASON_PRGSALIMITRES  = 41; # превышен лимит СС, установленный стр.программой для НЕ-резидента
    const UW_REASON_RISKCLASS      = 42; # Высокий класс риска профессии Застрахованного
    const UW_REASON_INSURED_AGE    = 43; # Возраст Застрахованного превышает максимальный
    const UW_REASON_INSURED_AGE_CI = 143; # Возраст Застрахованного для риска КЗ превышает максимальный

    const UW_REASON_SPORTS = 44; # Занятия опасными видами спорта {upd/2022-01-21}
    const UW_REASON_SPORT_INVALID = 45; # спорт, исключающий риск инвалидности {upd/2022-01-24} (ОУСВ - блокировать?)
    const UW_REASON_SPORT_TRAUMA  = 46; # спорт, исключающий риск травмы/инвалидности НС
    const UW_REASON_RENTA         = 47; # по размеру ренты (Зол.Пора)

    const UW_REASON_PROLONG_LOSSES = 50;  # при пролонгации: у пролонгируемого полиса есть убытки
    const UW_REASON_NOT_BOX        = 51;  # параметры страхования не соответствуют коробочным (стр.сумма > max)
    const UW_REASON_PERSON_CHANGE  = 52;  # изменены данные стр-ля, застрахованного (ФИО)
    const UW_REASON_YEAR_PREM_INCOME = 53;  # Взнос за год выше 20% от годового дохода Застрах.
    const UW_REASON_PROLONG_CHAIN  = 54;  # при пролонгации: превышено макс.кол-во пролонгаций
    const UW_REASON_SANCTIONS     = 55;  # санкционный риск
    const UW_REASON_BEN_TAXREZIDENT = 56; # ВП - налоговый нерезидент РФ

    const UW_REASON_BY_ADMIN      = 100; # установлено администратором/андеррайтером
    const UW_REASON_PROLONG       = 101; # пролонгация требует андеррайтинг
    const UW_REASON_PROLONG_SA    = 102; # изменение одной из Стр.Сумм при пролонгации
    const UW_REASON_PROLONG_PPAY  = 103; # изменение периодичности оплаты при пролонгации
    const UW_REASON_EXTERNAL      = 200; # андерр.проверка одним из подключенных модулей
    const UW_REASON_PERSONAL_UW   = 201; # персональный андеррайтинг (размер страховой суммы и т.д.)
    const UW_REASON_AFTER_EDIT    = 210; # авто-перевод в UW после редактирования
    const UW_REASON_PROFESSION    = 501; # Иная профессия
    const UW_REASON_NON_WORKING   = 502; # Выбрана одна из профессий - студент, пенсионер, домохозяйка и т.п.

    const UW_REASON_BY_USER       = 700; # Направлен на андеррайтинг самим пользователем
    const UW_REASON_BY_PROCESS    = 701; # Направлен на андеррайтинг в соответствии с порядком работы
    const UW_REASON_EXT_AV        = 702; # Заказано повышенное АВ

    public static $calcReasons = [10,11,27,28,30,31,32,33,34,36,40,41,42,43,143,44,45,46,52,53,501,502]; # UW коды которые можно сбросит при перерасчете

    # UW коды которые могут быть сброшены после редактирования ПДн в арточке полиса
    public static $noDeclarReasons = [1,11,20,22,25,26,27,28,30,31,32,33,34,36,40,41,42,43,143,44,45,46,52,702];
    # легкие причины андеррайтинга (которые может разрулить ПП)
    public static $lightUwReasons = [ 25 ]; #  # {upd/2025-04-22} - ,26,56 налоговые нерезы снова влекут UW (И.Яковлева)

    const UW_REASON_CALCONDITIONS = 720; # UW по условиям калькуляции
    const UW_REASON_REINVEST = 721; # договор реинвестиционный, подлежит андеррайтиннгу!

    const NDS_PERCENT = 18; # процент НДС
    const ZIPCODE_LEN = 6; # длина почтового индекса (верхний лимит)

    const CHANGE_BY_UW = -100; # код в recalcby, сигнализирующий об изменении полиса андеррайтером

    # коды типов документов удостоверяющих личность/организацию
    const DT_PASSPORT = 1;
    const DT_SVID    = 2; # Свид-во о рождении
    const DT_VOENBIL = 3; # Военный билет
    const DT_ZPASS   = 4; # Загранпаспорт
    const DT_INOPASS = 6; # Паспорт иностр.гражд
    const DT_TEMP_RF = 7; # Временное удостоверение гр-на РФ
    const DT_MIGCARD = 20; # Миграционная карта
    const DT_OTHER   = 99; # Иной документ

    const VIEW_BENEF = TRUE; # На форме просмотра выводить выгодоприобретателей
    const TXT_BENEF_BY_LAW = 'Выгодоприобретатель в соответствии с Законодательством Российской Федерации';

    const RIGHT_SUPEROPER = 'agmt_superoper'; # в справочнике прав - право глобального супер-операциониста (все виды договоров/все плагины)
    const RIGHT_ACCEPTOR  = 'agmt_accept'; # в справочнике прав - право акцептации договоров
    const RIGHT_POLICYNO  = '_enterno'; # право на ручной ввод номера заявл-я полиса (вместо авто-выдачи из пула)
    const RIGHT_DOCFLOW   = 'docflow_oper'; # видны все полисы, есть право выгрузки в СЭД
    const RIGHT_EQPAYMENT = 'payonline'; # право генерации ссылки на онлайн-оплату
    const RIGHT_LEADSALES = 'lead_sales'; # право оформления полисов от лидогенераторов (для наших сотрудников)
    const RIGHT_COMPLIANCE = 'compliance'; # общее право Комплайнс на любые типы договоров
    const RIGHT_INFOSEC  = 'infosecurity'; # общее право Офицер информ-безопасности
    const RIGHT_EMPLOYEE  = 'employee'; # общее - роль сотрудник Компании
    const RIGHT_UW  = 'underwriter'; # общее право андеррайтера (единое на все продукты)

    const RIGHT_AGENT = 'agent'; # право "Агент"
    const RIGHT_CLIENT = 'client'; # право "Клиент"

    const MAX_DAYS_BACK = 3; # разрешенное к-во дней назад для ввода даты оплаты (от текущей даты)
    const MAX_DAYS_PAY_FROM = 0; # разрешенное к-во дней назад для ввода даты оплаты (от даты начала д-вия полиса) (2022-11-01 - 3 дня)
    const MIN_YEARSALARY = 60000; # мин.значение среднего годового дохода - блокировка меньших значений!
    const PREM_TO_YEARINCOME = 20; # общий годовой взнос не должен превысить столько % от годового дохода Застрах.

    # типы Бизнес-процессов (алгоритм работы с проектом договора, маршруты, кнопки на форме просмотре и т.д.)
    # TODO: вынести обработку "нестанд" БП в отдельные модули : app/bprocagent.php (class BprocAgent) и т.п.
    const BP_AGENT = 'agent';

    const RANGE_BILLS = '_BILLS_'; # диапазон для свчетов на оплату
    const RANGE_XML = '_XMLNUMBERS_'; # диапазон для FileId в XML файлах выгрузки в LISA

    # 1 - отправлять запрос согласования только Страхователю, 2 - еще и Застрахованному {upd/2020-04-27}
    const ONLINE_CONFIRM = 1; # не актуально! т.к. ЭДО включается только если Стр = Застрах!

    # коды причин постановки догвора на "паузу" (запрет дальнешего прохождения по Биз-Процессу)
    const PAUSED_NEED_INANKETA = 1; # к договору требуется прицепить инвест-анкету(ЦБ) {upd/2021-09-02}
    const PAUSED_WAIT_INANKETA_SIGNED = 2; # анкету привязали ,но она еще не согласована (ждем-с)
    const PAUSED_INANKETA_BLOCKED = 3; # анкета есть, но заблокировава (отказ клиента либо блокирвка СК)
    const PAUSED_WAIT_INANKETA_STUCK = 4; # анкету создали привязали и закрыли окно не доведя до конца
    const PAUSED_NEED_INN_SNILS = 10; # у страхователя надо доввести ИНН или СНИЛС {upd/2021-09-02}
    const PAUSED_NEED_UWRECLAC = 11; # {upd/2021-09-02} ожидаем перерасчета андеррайтером
    # мета-типы
    const META_BANKS = 1;
    const META_ALLIANZ = 2;
    const META_AGENTS = 100;
    const META_ESHOP  = 110;
    const META_OTHER  = 900;

    # мета-типы рисков (для сохранения в alf_agmt_agrisks.rtype)
    const RSKTYPE_MAINPACK = 'MANPACK'; # главные риски с единой СС и премией - общий пакет
    const RSKTYPE_BINDED = 'MAINBINDED'; # осн. риски со своей расчетной премией (добавляются в премию по "осн.рискам")
    const RSKTTYPE_ADDITIONAL = 'ADDITIONAL'; # все доп. (необязательные) риски

    const BPTYPE_EDO = 'EDO'; # тип бизнес-процесса - ЭДО-ПЭП
    const BPTYPE_STD = 'STD'; # тип бизнес-процесса стандартный
    const BPTYPE_STM = 'STM'; # согласование Заявления ПЭП
    const MODE_NONE = '_NONE_';
    const UPLOAD_MAXSIZE_KB = 16384; # 16MB max upload file size

    # типы продукта в контексте оплаты эквайрином, а также для сбора "глобальных" отчетов по типу полисов
    const ACQ_LIFE = 'life';
    const ACQ_DMS  = 'dms';
    const ACQ_PNC  = 'pnc';

    # уровни доступа пользователя к стр.модулю (перенос из policymodel.php
    const LEVEL_OPER = 1;
    const LEVEL_MANAGER = 2;
    const LEVEL_CENTROFFICE = 3; # Центральный офис банка/партнера/аг.сети
    const LEVEL_IC_ADMIN = 4; # Сотрудник СК (только у права  "...reports"
    const LEVEL_IC_SPECADM = 4.1; # Спец-сотрудник СК (кнопка "Статус" как у супер-опера)
    const LEVEL_UW = 5; # андеррайтер
    const LEVEL_SUPEROPER = 10;

    const DAYS_COOL = 14; # дефолтное число дней периода охлаждения
    const USER_CONFIG = '_userconfig_'; # спец-константа для хранения юзерских настроек профиля

    # сканы, которые надо удалять при сбросе договора в статус Проекта
    public static $clearDocsReset = ['plc_zayav','anketa_insd','anketa_ben','signed_policy','epolicy','plc_paydoc','edo_policy','edo_zayav'];

    static $scanTypes = array( # возможные типы сканированных документов для загрузки к полису - макс.список!
      'stmt' =>'Заявление',
      'agmt' =>'Полис',
      # 'passport_ph' =>'Документ страхователя',
      # 'paydoc' =>'Платежный документ',
      'plc_zayav' => 'Заявление на страхование',
      'passport_insr' => 'Паспорт/документ Страхователя',
      'passport_insd' => 'Паспорт/документ Застрахованного',
      'passport_insd2' => 'Паспорт/документ Застрахованного-2', # для полисов с 2-мя застр (Поч.возраст!)
      'passport_child' => 'Паспорт/свид-во Застр.Ребенка',
      'passport_ben'  => 'Паспорт/документ Выгодоприобретателей',
      'plc_paydoc' => 'Платежный документ',
      'viza' => 'Виза/миграц.карта',
      'anketa_insr' => 'Анкета Страхователя',
      'anketa_cli' => 'Анкета Клиента',
      'anketa_insd' => 'Анкета Застрахованного',
      'anketa_6886' => 'Анкета кредитной организации', # 'Анкета ЦБ 6886-У',
      'anketa_ben'  => 'Анкеты Выгодоприобретателей',
      'anketa_taxrez'  => 'Анкета налогового резидента',
      'anketa_other' => 'Доп.анкеты',
      'oplist_fatca' => 'Опросный лист FATCA',
      'fin_anketa' => 'Финансовая анкета',
      'fin_plan' => 'Согласованный фин-план(таблица вык.сумм)',
      'spravki_ndfl' => 'Справки 2НДФЛ',
      'signed_policy' => 'Скан подписанного полиса',
      'epolicy' => 'Электронный полис',
      'econtract' => 'Электронный договор',
      'loader' => 'Загрузчик',
      'edo_zayav' => 'Заявление на страхование (ЭДО)',
      'calculation' => 'Расчет стоимости', # HappyHome - XLS файл с расчетом (ну и где еще пригодится)
      'sogl_pdn' => 'Согласие на обработку ПДн',
      'other_docs' =>'Прочие документы',
    );
    public static $personFields = [ # список имен полей ФЛ/ЮЛ
      'fam','imia','otch','inn','ogrn','kpp','snils','sex','relation','rez_country','birth_country','doctype',
      'docser','docno','docpodr','docissued','inopass','otherdocno','permit_type','migcard_ser','migcard_no','married',
      'phone','phone2','email','adr_zip','adr_countryid','adr_country','adr_region','adr_city','adr_street','adr_house','adr_corp','adr_build','adr_flat','sameaddr',
      'fadr_zip','fadr_countryid','fadr_country','fadr_region','fadr_city','fadr_street','fadr_house','fadr_corp','fadr_build','fadr_flat',
    ];
    public static $personDateFields = [ # список имен полей ФЛ/ЮЛ типа Дата
      'birth', 'docdate','docfrom','doctill'
    ];

    public static $scanEdoPolicy = 'edo_policy'; # тип файла "цифровая версия полиса с ЭЦП", для ЭДО-процесса
    public static $scanEdoZayav  = 'edo_zayav'; # {upd/2024-08-05} тип файла "цифровая версия Заявления (ЭДО)"
    public static $scanCheckLog = 'checklog'; # тип файла проверка по спискам террористов
    public static $pdf = NULL; # общий инстанс PrintformPdf
    public static $infoEmail = 'az.info@zettains.ru';
    public static $compPhones = '(495)232-0100';

    # {upd/2022-12-08} Список старых продуктов (не выводить "отчеты" и проч)
    public static $deadProducts = ['azgarant','fzacoll','garcl','garplus','hhome','insfza','invonline','planb','plgkpp','investprod'];
    # статусы, пр которых можно обновить дату начала д-вия полиса
    public static $editStates = [self::STATE_DRAFT,self::STATE_PROJECT,self::STATE_UNDERWRITING,self::STATE_POLICY]; # self::STATE_IN_FORMING ??
}