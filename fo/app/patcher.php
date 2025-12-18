<?php
/**
* @package ALFO
* @name app/patcher.php
* Обновление данных, структур таблиц в БД (запускать после наката из GIT)
* @author Alexander Selifonov
* modified 2025-12-17, A.Selifonov
**/
if (!class_exists('appEnv')) include_once(__DIR__ .'/alfo_core.php');

# ini_set('display_errors',1); ini_set('error_reporting',E_ALL);
# error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

class Patcher extends AppPatcherAlfo {

    public function patch1() {

        self::drawHeader(__FUNCTION__);
        self::echoText("Обновление таблиц подразделений, продуктов...<br>");
        self::initTable(PM::T_DEPTS);
        self::initTable(PM::T_DEPT_PROD);
        self::initTable('alf_planb_tariffs');
        # self::initTable('alf_apiclients', ['username'=>'Сайт', 'usertoken'=>'HF04726SFKFBNSJK2048CVMNRU758C6D']);
        self::footer();
    }

    public function patch2() {

        self::drawHeader(__FUNCTION__);
        self::echoText("- Обновление таблиц БА, кодировок(инвесты)...<br>");
        self::initTable('alf_invonline_tariffs', ['tarifname'=>'Основной тариф']);
        self::initTable('alf_invba', ['baname'=>'IT-технологии (корзина с контролем волатильности)']);
        self::initTable(PM::T_POLICIES); # добавилась дата транша !

        self::initTable(PM::T_UPLOADS); # добавилась exported !
        self::initTable('bn_policyscan'); # добавилась exported !
        self::initTable(PM::T_SAVEDCALC);
        self::initTable(PM::T_PROMOACTIONS); # промокоды
        self::initTable(PM::T_COUNTRIES); # страны
        self::initTable(PM::T_LOSSES); # alf_policylosses
        self::initTable('alf_appalerts'); # alf_appalerts
        self::initTable('alf_stamps'); # Штампы (подписанты)
        self::upgradeRoles('invonline');

        self::footer();
    }

    public function patch3() {

        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_EVENTLOG);
        self::initTable(PM::T_DEPTS); # подразделения
        self::initTable(PM::T_USERS); # пользователи, агенты
        self::initTable(PM::T_ESHOPLOG); # калькуляции из eShop
        if (class_exists('agtlife')) {
            self::initTable('alf_agtlifedept'); # настройки аг.калькуляторов на партнеров
            self::upgradeRoles('agtlife');
            self::__addProductConfig('agtlife');
        }
        self::updFreeClient();
        # новые роли - ред. подразделений и юзеров
        $rori = [
          'rights' => [
            'dept_editor' => ['checkbox','Редактор списка подразделений' ],
            appEnv::RIGHT_USERMANAGER => ['checkbox', 'Редактор списка пользователей'],
          ],
          'roles' => [
            'rdept_editor' => ['title'=>'Редактор списка подразделений', 'rights'=>['dept_editor'=>1] ],
          ],
        ];
        self::upgradeRoles('', $rori);

        self::footer();
    }

    public function patch4() {

        self::drawHeader(__FUNCTION__);

        $brecs = [
          [ 'productid' => 'invonline','ba_id' => '1', 'codirovka' => 'XXL3R1R', 'term'=>3,'warranty'=>100 ],
          [ 'productid' => 'invonline','ba_id' => '1', 'codirovka' => 'XXL3R1W', 'term'=>3,'warranty'=>105 ],
          [ 'productid' => 'invonline','ba_id' => '1', 'codirovka' => 'XXL5R1R', 'term'=>5,'warranty'=>100 ],
          [ 'productid' => 'invonline','ba_id' => '1', 'codirovka' => 'XXL5R1W', 'term'=>5,'warranty'=>110 ],
        ];
        self::initTable(PM::T_INVSUBTYPES, $brecs);
        self::initTable('bn_insurancescheme'); # инвесты, новые поля "купонный доход", non_uwlimit, cbmemo
        self::initTable('bn_deptscheme'); # инвесты, новое add_print_noresident
        self::initTable('bn_policy'); # поля state_fatca, tax_country
        self::initTable('bn_policyeventrisk'); # удаляю ненужные поля
        self::initTable('bn_riskgroup'); # k_sa поменял тип, для купонного риска ДД
        self::initTable('bn_risk'); # rorder
        self::initTable(PM::T_POLICIES); # state_fatca
        self::initTable(PM::T_STMT_RANGES); # диапазоны номеров (module!)
        self::initTable(PM::T_BURDEN); # нагрузки, для памяток ЦБ
        # New nsj = refactored plg_kpp/
        if( isset(appEnv::$_plugins['nsj']) ) {
            $nsjPrg = [
               [ 'programid' => 'EndowmentLite','algoid'=>'EndowmentLite', 'codirovka' =>'BEA', 'programname'=>'Гарантия Будущего' ],
               [ 'programid' => 'Endowment','algoid'=>'Endowment', 'codirovka' =>'BEL', 'programname'=>'Гарантия Будущего+' ],
               [ 'programid' => 'TermFixLite','algoid'=>'TermFixLite', 'codirovka' =>'BCA', 'programname'=>'Путевка в жизнь' ],
               [ 'programid' => 'TermFix','algoid'=>'TermFix', 'codirovka' =>'BCL', 'programname'=>'Путевка в жизнь+' ],
            ];
            self::initTable(nsj::T_NSJPROGRAMS, $nsjPrg); # новый!
            self::initTable(nsj::T_PRGRISKS); # новый!

            self::upgradeRoles('nsj');
        }

        self::footer();
    }

    public function patch5() {
        self::drawHeader(__FUNCTION__);
        $cumlims = [
          [ 'eng_id' => 'death_adult', 'limit_name' => 'Риск смерти ЛП для Взрослого', 'limit_value' => 10500000 ],
          [ 'eng_id' => 'death_child', 'limit_name' => 'Риск смерти ЛП для Ребенка', 'limit_value' => 10500000 ],
        ];
        self::initTable(PM::TABLE_CUMLIR, $cumlims);

        # New nsj = refactored plg_kpp/
        if(class_exists('nsj')) {
            self::initTable(PM::T_ICONS_RISKS); # обновление структуры
            # меняю значения в кумуль-полях настроек рисков НСЖ-юник!
            $cumlr_updt = [
              [ 'where' => 'programid=7 AND riskid=3',
                'data' => ['risk_cumulate'=>1, 'cumul_limit'=>1, 'cumul_seekrisks'=>'death_any_wop' ]
              ],

              [ 'where' => 'programid=8 AND riskid=31',
                'data' => ['risk_cumulate'=>2, 'cumul_limit'=>2, 'cumul_seekrisks'=>'' ]
              ],

              [ 'where' => 'programid=8 AND riskid=36',
                'data' => ['risk_cumulate'=>1, 'cumul_limit'=>1, 'cumul_seekrisks'=>'death_any' ]
              ],
            ];
            self::__updateRecords(PM::T_ICONS_RISKS, $cumlr_updt);
        }
        self::initTable(PM::T_EVENTLOG); # инвесты, новые поля "купонный доход", non_uwlimit, cbmemo

        # заношу в настройки пенсионные возрасты
        self::setConfigVal('page_appconfig', 'ins_pensionage_m', 65);
        self::setConfigVal('page_appconfig', 'ins_pensionage_f', 60);
        self::echoText('Настройки пенсионных возрастов (65,60) обновлены<br>');

        self::footer();
    }
    public function patch6() {
        self::drawHeader(__FUNCTION__);
        #
        self::initTable(PM::T_EXPORTRISKS); # alf_exportrisks новое поле exp_individ
        self::initTable('alf_nsjprgrisks'); # новое поле age_action в описаниях рисков НСЖ юникред

        self::footer();
    }

    public function patch7() {
        self::drawHeader(__FUNCTION__);
        #
        self::initTable('bn_schemesubtype'); # ИСЖ - дробные сроки (3.5, 5.5 лет), strategy
        self::initTable('bn_policy'); # добавлены ед.срока (Y/M), subtypeid, iso_curr
        self::initTable('bn_redemptionamount'); # currency - новое поле вместо currencyid
        self::initTable('bn_deptscheme'); # новое поле opros_list_norez (настройка опр.листа для налоговых НЕрезидентов РФ
        # заполняю новые поля у старых записей investprod
        $updQueries = [
            'update bn_policy pn SET subtypeid = (SELECT id FROM bn_schemesubtype st
        WHERE st.currencyid=pn.currencyid AND st.insuranceschemeid=pn.insuranceschemeid) WHERE subtypeid=0',
            'update bn_policy pn SET currency = (SELECT isocode FROM bn_schemesubtype st
        WHERE st.currencyid=pn.currencyid AND st.insuranceschemeid=pn.insuranceschemeid)',
            'update bn_redemptionamount rd SET currency = (SELECT isocode FROM bn_currency cr
        WHERE cr.id=rd.currencyid) WHERE currencyid>0',
        ];
        self::runQueries($updQueries);
        self::footer();
    }

    public function patch8() {
        self::drawHeader(__FUNCTION__);
        #
        self::initTable('bn_policy'); # индекс на поле updated
        self::initTable('bn_schemesubtype'); # ИСЖ - список БА для поля в анкете ВСС
        self::footer();
    }
    public function patch9() {
        self::drawHeader(__FUNCTION__);
        #
        self::initTable('alf_tranches'); # индекс на поле updated
        self::footer();
    }
    public function patch10() {
        self::drawHeader(__FUNCTION__);
        # if(is_dir('plugins/hhome')) {
            self::upgradeRoles('hhome');
        # }
        self::initTable(PM::T_DEPT_PROD); # visiblename
        $pQueries = [
          "UPDATE arjagent_acl_rightdef SET rightoptions = '0:нет;1:операционист;2:Менеджер банка;3:Центральный офис банка;4:Сотрудник СК;5:Андеррайтер;10:Супероперационист' "
           . " WHERE rightkey = 'hhome_oper'"
        ];
        self::runQueries($pQueries);
        self::footer();
    }
    public function patch11() {
        self::drawHeader(__FUNCTION__);
        self::initTable('alf_stamps'); # module !
        self::initTable('bn_schemesubtype'); # b_active - отключаемые кодировки
        self::initTable(PM::T_POLICIES); # signer - поле для выбора подписанта (hhome)
        self::initTable('alf_nsjprograms'); # добавил поле risklist
        self::footer();
    }

    public function patch12() {
        self::drawHeader(__FUNCTION__);
        self::initTable('alf_nsjprograms'); # добавил поле risklist
        self::initTable(PM::T_EXPORTCFG); # новое поле binded_to

        if( isset(appEnv::$_plugins['ndc']) ) {
            # новый модуль ndc - НСЖ ДМС чекап
            $stdTarif = [
              ['tarifname'=>'Стандартный тариф']
            ];
            self::initTable(ndc::TABLE_TARIFFS, $stdTarif); # НДС-ДМС - тарифы
            self::upgradeRoles('ndc'); # сгенерить роли-права для модуля

            # добавляю новые риски в справочник, если таких еще нет:
            $ndcRisks = [
              [ 'condition' => ['riskename'=>'hospital_ci'],
                'data' => [ 'riskename'=>'hospital_ci', 'shortname'=>'Госпитализация КЗ','longname'=>'Госпитализация Застрахованного в результате КЗ','exportname'=>'ГСКЗ' ],
              ],
              [ 'condition' => ['riskename'=>'treatment_ci'],
                'data' => ['riskename'=>'treatment_ci','shortname'=>'Лечение КЗ','longname'=>'Лечение Застрахованного КЗ','exportname'=>'ЛКЗ'],
              ],

              [ 'condition' => ['riskename'=>'checkup'],
                'data' => ['riskename'=>'checkup','shortname'=>'ЧекАп','longname'=>'ЧекАп','exportname'=>'ЧК'],
              ],

            ];

            self::addNewRecords(PM::T_RISKS, $ndcRisks);

            # авто- добавл-е записи в справ-к нагрузок (справка ЦБ-мемо) для Global Life
            $burdenRecs = [
              [ 'condition' => ['module'=>'ndc'],
                'data' => ['module'=>'ndc','value_once'=>12,'value_reg'=>14], #TODO: выяснить верные цифры!
              ],
            ];
            self::addNewRecords(PM::T_BURDEN, $burdenRecs);

            # добавляю заглушки для настроек экспорта Global Life в LISA
            # TODO: надо бы еще заливку дочерних записей придумать - в alf_exportrisks
            $expLisaRecs = [
              [ 'condition' => ['product'=>'GBLL'],
                'data' => ['product'=>'GBLL','extprogram'=>320001], #TODO: тестовые ИД программ LISA, потом поправить!
              ],
              [ 'condition' => ['product'=>'GBLS'],
                'data' => ['product'=>'GBLS','extprogram'=>320002],
              ],
              [ 'condition' => ['product'=>'GBLP'],
                'data' => ['product'=>'GBLP','extprogram'=>320003],
              ],
              [ 'condition' => ['product'=>'GBLV'],
                'data' => ['product'=>'GBLV','extprogram'=>320004],
              ],
            ];
            self::addNewRecords(PM::T_EXPORTCFG, $expLisaRecs);
        }
        self::footer();
    }
    public function patch13() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_POLICIES); # добавил поле med_declar
        self::initTable(PM::T_SPECDATA); # добавил поле spec_conditions
        self::initTable('bn_schemesubtype'); # инвест-кодировки - поле coupon_size

        self::footer();
    }
    public function patch14() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_REGIONS);
        self::initTable(PM::T_INDIVIDUAL); # добавил adr_city, fadr_city,(f)addr_countryid
        self::initTable(PM::T_BENEFICIARY); # добавил adr_city, fadr_city, sex, (official)addr_countryid
        self::initTable('bn_individual'); # *countryid
        self::initTable('bn_beneficiary'); # *countryid
        $updQueries = [
          "update alf_agmt_individual SET adr_city=adr_region WHERE adr_city='' AND adr_region<>'' AND (adr_region REGEXP '^[0-9]+$' = 0)",
          "update alf_agmt_individual SET fadr_city=fadr_region WHERE fadr_city='' AND fadr_region<>'' AND (fadr_region REGEXP '^[0-9]+$' = 0)",

          "update alf_agmt_beneficiary SET adr_city=adr_region WHERE adr_city='' AND adr_region<>'' AND (adr_region REGEXP '^[0-9]+$' = 0)",
          "update alf_agmt_beneficiary SET fadr_city=fadr_region WHERE fadr_city='' AND fadr_region<>'' AND (fadr_region REGEXP '^[0-9]+$' = 0)",
        ];
        self::runQueries($updQueries);


        self::footer();
    }
    # patch date: 2020-03-17 - 2020-04-15
    public function patch15() {
        self::drawHeader(__FUNCTION__);
        self::initTable('bn_date_subtype'); # новая
        self::initTable(PM::T_POLICIES); # добавлено bpstateid (код статуса в бизнес-процессе)
        self::initTable(PM::T_INDIVIDUAL); # kpp, adr_full, fadr_full
        self::initTable(PM::T_BENEFICIARY); # adr_full, fadr_full

        self::initTable(PM::T_DEPT_PROD); # online_confirm во всех осн.модулях
        self::initTable('bn_deptscheme'); # online_confirm в инвестах

        if (class_exists('trmig')) { #  труд-мигранты
            $plg = 'trmig';
            self::upgradeRoles($plg);
            $stdTarif = [ ['tarifname'=>'Стандартный тариф'] ];
            self::initTable(trmig::TABLE_TARIFFS, $stdTarif); # тфрифы расчета
            self::initTable(trmig::TABLE_INSURED);

            $prodRecs = [ # запись в осн. настройках продуктов
              [ 'condition' => ['module'=>$plg],
                'data' => ['module'=>$plg,'prikaz_date'=>'2020-04-01','prikaz_no'=>'19'],
              ],
            ];
            self::addNewRecords(PM::T_PRODCFG, $prodRecs);

            $ranges = [
              [ 'condition' => ['module'=>$plg],  # диапазон номеров для полисов
                'data' => ['module'=>$plg,'startno'=>10000000,'endno'=>12000000, 'currentno'=>10000000],
              ],
              [ 'condition' => ['module'=>'_BILLS_'], # диапазон номеров счетов на оплату
                'data' => ['module'=>'_BILLS_','startno'=>10,'endno'=>9000000, 'currentno'=>10],
              ],
            ];
            self::addNewRecords(PM::T_STMT_RANGES, $ranges);
        }
        if (class_exists('lifeag')) {
            $plg = 'lifeag';
            self::upgradeRoles($plg);
            $prodRecs = [ # запись в осн. настройках продуктов
              [ 'condition' => ['module'=>$plg],
                'data' => ['module'=>$plg,'prikaz_date'=>date('Y-m-d'),'prikaz_no'=>'20'],
              ],
            ];
            self::addNewRecords(PM::T_PRODCFG, $prodRecs);

            $ranges = [
              [ 'condition' => ['module'=>$plg],  # диапазон номеров для полисов
                'data' => ['module'=>$plg,'startno'=>30000000,'endno'=>31000000, 'currentno'=>30000000],
              ],
            ];
            self::addNewRecords(PM::T_STMT_RANGES, $ranges);

            # записи в настройку выгрузки XML
            $recs = [
              [ 'condition' => ['product'=>'ACC'],  # настройа выгрузки Риск-контроль
                'data' => ['product'=>'ACC','extprogram'=>213,'binded_to'=>0],
              ],
            ];
            self::addNewRecords(PM::T_EXPORTCFG, $recs); # alf_exportcfg
        }
        self::footer();
    }

    # patch date: 2020-04-24 - 2020-0X-XX тема: онлайн-подтверждения
    public function patch16() {

        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_DEPT_PROD); # online_confirm во всех осн.модулях
        self::initTable('bn_deptscheme'); # online_confirm в инвестах
        self::initTable('bn_schemesubtype'); # настройки XML для формирования PDF полиса
        self::initTable(PM::T_CLIENT_CONFIRM); # данные для онлайн-подтверждения клиентом согласия с условиями договора
        self::initTable(PM::T_SMSLOG); # журнал отправки СМС
        self::initTable(PM::T_ALI_TRANCHES); # транши из ALI
        self::initTable(PM::T_DEPTS); # поле region
        self::initTable(PM::T_CURATORS); # новая - кураторы-агенты
        self::initTable(PM::T_POLICIES); # recalcby, agent, curator

        # lifeag: новый риск в списке! - инвал ЛП для заемщ.
        $recs = [
          [ 'condition' => ['riskename'=>'invalid_any_loaner'],
            'data' => [
               'riskgroup'=>'1','riskename'=>'invalid_any_loaner','shortname'=> 'Инвалидность Л/П для заемщиков',
               'longname'=>'Инвалидность I, II группы по любой причине для заемщиков','exportname'=>'ИНВЗАЕМ'
              ]
          ],
        ];
        self::addNewRecords(PM::T_RISKS, $recs);

        self::footer();
    }

    public function patch17() {

        self::drawHeader(__FUNCTION__);

        self::upgradeRoles('aclreport'); # роль для выдачи отчетов по ролям-польз-настройкам продуктов
        self::initTable(PM::T_DEPTS); # поле region, couche_id(2020-06), corpemail(2020-10)
        self::initTable(PM::T_CURATORS); # кураторы, b_couche

        self::initTable(PM::T_SMSLOG); # sessionid - length
        self::initTable(PM::T_CLIENT_CONFIRM); # sescode
        self::initTable(PM::T_POLICIES); # coucheid, letterno, bptype, sescode,dt_sessign
        self::initTable('bn_policy'); # bptype, bpstateid, sescode, dt_sessign
        self::initTable('bn_schemesubtype'); # min_premium, lisa_prg (2020-08-11)
        self::initTable('bn_deptscheme'); # origin_for
        self::initTable('bn_policyscan'); # doctype вместо typeid, descr
        self::initTable(PM::T_DEPT_PROD); # origin_for, print_edo, specparams(TEXT)
        self::initTable(PM::T_PRODCFG); # sed_chanel - общий на продукт
        self::initTable(PM::T_OU_PROPS); # 'alf_dept_properties': get_seller (выводить поле "Продавец" на всех продаваемых полисах)
        self::initTable(PM::T_USERS); # is_test
        self::initTable('alf_nsjprograms'); # b_stmt - вкл. заявление
        self::initTable(PM::T_SENTEMAIL); # alf_sentemail для хранения отправленных писем (ЭДО-процесс)
        self::initTable(PM::T_EXPORTRISKS); # exp_groupidnowop (ИД группы для случая "БЕЗ ОУСВ"

        $recs = [
          [ 'condition' => ['riskename'=>'death_any_addcover'],
            'data' => [
               'riskgroup'=>'1','riskename'=>'death_any_addcover','shortname'=> 'Смерть по любой причине (доп.покрытие)',
               'longname'=>'Смерть по любой причине (увеличение покрытия)','exportname'=>'СЛП_ДОП'
              ]
          ],
        ];
        self::addNewRecords(PM::T_RISKS, $recs);

        # переношу настройки из устаревшего alf_kpp_bnklimits в specparams
        self::initTable('alf_kpp_bnklimits');
        $tasks = PatcherTasks::plgKppToSpecPar();
        self:: echoText($tasks);

        /**
        # исправляю ошибки в именах полей
        $fixQry = [
            "alter table alf_agmt_individual CHANGE migсard_ser migcard_ser VARCHAR(20) DEFAULT ''",
            "alter table alf_agmt_individual CHANGE migсard_no migcard_no VARCHAR(20) DEFAULT ''"
        ];
        self::runQueries($fixQry);
        **/
        self::footer();
    }
    public function patch18() {

        self::drawHeader(__FUNCTION__);
        if (class_exists('oncob')) {
            $plg = 'oncob';
            self::upgradeRoles($plg);

            self::initTable('alf_oncob_tariffs');
            $recs = [
              [ 'condition' => ['tariffid>0'],
                'data' => [
                   'tarifname'=>'Стандартный тариф', 'date_start'=>date('Y-m-d')
                  ]
              ],
            ];
            self::addNewRecords('alf_oncob_tariffs', $recs);
        }

        $ranges = [
          [ 'condition' => ['module'=>PM::RANGE_XML], # диапазон номеров файла в XML для LISA
            'data' => ['module'=>PM::RANGE_XML,'startno'=>41194502,'endno'=>999999000, 'currentno'=>41194502],
          ],
        ];
        self::addNewRecords(PM::T_STMT_RANGES, $ranges);

        $text = PatcherTasks::addNsjWopRisks();
        self:: echoText($text);
        if (class_exists('appcaches')) {
            self::initTable(AppCaches::T_CACHE);
        }
        if (class_exists('delivery')) {
            self::initTable(delivery::T_DELIVERY);
        }
        if (class_exists('plsign')) {
            $fixQry = "update alf_plsign_cards SET stateid='FINISHED' where stateid='FINIDHED'"; # исправляю слово
            appEnv::$db->sql_query($fixQry);
            $affected = appEnv::$db->affected_rows();
            self::echoText("plsign-карточки: исправлено записей: $affected");
        }

        # Базовые настройки для Почетный Возраст

        self::initTable('alf_nsjprograms'); # добавил b_active
        self::initTable(PM::T_INDIVIDUAL); # добавил insured_relation (новый поч.возраст)
        self::footer();
    }

    # patch19 updated 2021-01-21
    public function patch19() {
        self::drawHeader(__FUNCTION__);

        self::initTable(PM::T_POLICIES); # previous_id VARCHAR, bpstate_date (2021-02-15) invanketaid(2021-0301)
        self::initTable('bn_policy'); # invanketaid(2021-0301)

        self::initTable('alf_oncob_tariffs'); # (2021-03-16)-новые тарифы Онко (не-коробка)
        $recs = [
          [ 'condition' => ['tariffid>0'],
            'data' => [
               'tarifname'=>'Стандартный тариф',
               'date_start'=>'2020-01-01'
            ]
          ],
        ];
        self::addNewRecords('alf_oncob_tariffs', $recs);

        # if (!empty(PM::T_LOCKS))
        self::initTable(PM::T_LOCKS); # {upd/2021`-02-18} таблица блокировок
        self::initTable(PM::T_INVANKETA); # {upd/2021-02-26}
        self::initTable(PM::T_OU_PROPS); # invanketa - включение инв анкеты для партнера
        /*
        $ttpl = 'Тяжелые травмы Застрахованного';
        $fixQry = "update alf_agmt_risks SET shortname='$ttpl',longname='$ttpl' where riskename='ttpl'"; # исправляю название риска
        appEnv::$db->sql_query($fixQry);
        $affected = appEnv::$db->affected_rows();
        self::echoText("Спр.рисков: исправлено записей: $affected");
        */

        if (class_exists('Pochvo')) {
            $plg = 'pochvo';
            self::initTable(pochvo::TABLE_TARIFFS);
            $recs = [
              [ 'condition' => ['deptid'=>0],
                'data' => ['deptid'=>0, 'tarifname'=>'Стандартный тариф'],
              ],
            ];
            self::addNewRecords(pochvo::TABLE_TARIFFS, $recs);

            $ranges = [
              [ 'condition' => ['module'=>$plg], # диапазон номеров полисов Поч.возраст
                'data' => ['module'=>$plg,'startno'=>21000000,'endno'=>22000000, 'currentno'=>21000000],
              ],
            ];
            self::addNewRecords(PM::T_STMT_RANGES, $ranges);

            $prodRecs = [ # запись в осн. настройках продуктов
              [ 'condition' => ['module'=>$plg],
                'data' => ['module'=>$plg,'stampid'=>5,'prikaz_no'=>222, 'prikaz_date'=>date('Y-m-d') ],
              ],
            ];
            self::addNewRecords(PM::T_PRODCFG, $prodRecs);

            # запись в настройке выгрузки - ABE
            $recs = [
              [ 'condition' => ['product' => 'ABE'],
                'data' => ['product'=>'ABE', 'extprogram'=>'100069' ],
              ],
            ];
            self::addNewRecords(PM::T_EXPORTCFG, $recs);

            self::upgradeRoles($plg);
        }

        # Базовые настройки для Онко-барьер
        if (class_exists('oncob')) { # alf_oncob_tariffs
            $plg = 'oncob';
            self::upgradeRoles($plg);
            self::initTable(oncob::T_TARIFFS);
            $recs = [
              [ 'condition' => ['deptid'=>0],
                'data' => ['deptid'=>0,'stampid'=>5, 'tarifname'=>'Стандартный тариф'],
              ],
            ];
            self::addNewRecords(oncob::T_TARIFFS, $recs);

            $ranges = [
              [ 'condition' => ['module'=>$plg], # диапазон номеров полисов Поч.возраст
                'data' => ['module' => $plg,'startno'=>2200001,'endno'=>23000000, 'currentno'=>2200001],
              ],
            ];
            self::addNewRecords(PM::T_STMT_RANGES, $ranges);

            $prodRecs = [ # запись в осн. настройках продуктов
              [ 'condition' => ['module' => $plg],
                'data' => ['module'=>'oncob', 'stampid'=>5, 'prikaz_no'=>222, 'prikaz_date'=>date('Y-m-d') ],
              ],
            ];
            self::addNewRecords(PM::T_PRODCFG, $prodRecs);

            # запись в настройке выгрузки Онко - OTA
            $recs = [
              [ 'condition' => ['product' => 'OTA'],
                'data' => ['product'=>'OTA', 'extprogram'=>'100083' ],
              ],
            ];
            self::addNewRecords(PM::T_EXPORTCFG, $recs);

        }
        else self::echoText('oncob - модуль еще не установлен');

        if (class_exists('plsign')) {
            self::upgradeRoles('plsign');
            self::initTable(plsign::T_CARDLIST);
        }
        if (class_exists('Acquiring')) { # alf_eqpayments
            self::initTable(Acquiring::TABLE_PAYMENTS);
        }
        self::footer();
    }

    public function patch20() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_INVANKETA); # {upd/2021-04-09...12}
        self::initTable(PM::T_INVANKETAPLIST); # {upd/2021-04-12}
        self::initTable(PM::T_DEPTS);
        self::initTable(PM::T_OU_PROPS); # {upd/2021-04-28} - paramset !
        self::initTable('bn_individual'); # {upd/2021-04-12} ИСЖ - появился выбор типа стр-ля ФЛ-ЮЛ
        self::initTable('bn_policy'); # {upd/2021-06-02} bpstate_date
        self::initTable('bn_schemesubtype'); # {upd/2021-04-30} ИСЖ - появился with_default
        if (appEnv::getPlugin('dops')) {
            self::initTable('alf_dops_cards'); # {upd/2021-05-31} модуль доп-соглашений, таблица карточек ДОПсов
        }
        /*
        $fixQry = "update bn_individual SET set ptype=2 WHERE ptype=1 AND nalogcode<>'' AND firstname=''"; # исправляю тип у ЮЛ
        appEnv::$db->sql_query($fixQry);
        $affected = appEnv::$db->affected_rows();
        self::echoText("ИСЖ: исправлено страхователей-ЮЛ: $affected");
        */
        $fixQry = "update arjagent_acl_rightdef SET rightoptions='0:нет;0.5:Калькулятор;1:операционист;2:Менеджер банка;3:Центральный офис банка;4:Сотрудник СК;10:5:Андеррайтер;Супероперационист' where rightkey='nsj_oper'"; # добавлю уровни "калькулятор" НСЖ,Андер!
        appEnv::$db->sql_query($fixQry);
        $affected = appEnv::$db->affected_rows();
        $err = appEnv::$db->sql_error();
        if ($err) self::echoText("arjagent_acl_rightdef: модиф.списка прав (НСЖ): ERR: $err");
        else self::echoText("arjagent_acl_rightdef: модиф.списка прав (НСЖ): $affected");

        # подправляю короткое название риска ОУСВ инвалидности
        self::updateTbl(PM::T_RISKS, ['shortname'=>'Инвалидность Застрахованного ЛП - ОУСВ'], ['riskename'=>'disability_12_wop']);
        /*
        appEnv::$db->update(PM::T_RISKS, ['shortname'=>'Инвалидность Застрахованного ЛП - ОУСВ'], ['riskename'=>'disability_12_wop']);
        $affected = appEnv::$db->affected_rows();
        $err = appEnv::$db->sql_error();
        if ($err) self::echoText("риск ОУСВ инв.: ERR: $err");
        else self::echoText("риск ОУСВ инв.: обновлено $affected");
        */
        self::updateTbl(PM::T_RISKS, ['longname'=>'Травма Застрахованного взрослого в результате несчастного случая'], ['riskename'=>'trauma_acc']);
        /*
        $affected = appEnv::$db->affected_rows();
        $err = appEnv::$db->sql_error();
        if ($err) self::echoText("trauma_acc: ERR: $err");
        else self::echoText("trauma_acc: обновлено $affected");
        */
        self::updateTbl(PM::T_RISKS, ['longname'=>'Смерть Застрахованного по любой причине (увеличение покрытия)'], ['riskename'=>'death_any_addcover']);

        $plg = 'nsj';
        self::upgradeRoles($plg); # Кальк, Андеррайтер (2021-05-27)
        # if (appEnv::$_plugins[$plg]->getVersion() >= 2.0) {
            self::initTable('alf_nsjprograms');
        # }
        # новый риск - Смерть на трансопрте (типа ДТП)
        $recs = [
          [ 'condition' => ['riskename'=>'death_on_transport'],
            'data' => [
               'riskgroup'=>'1','riskename'=>'death_on_transport','shortname'=> 'Смерть на транспорте',
               'longname'=>'Смерть Застрахованного на транспорте','exportname'=>'СТРАНСП'
              ]
          ],
        ];
        self::addNewRecords(PM::T_RISKS, $recs);

    }

    public function patch21() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_CURATORS); # {upd/2021-06-24} раздельные фамилия, имя, отчество
        self::initTable('bn_ou_couche'); # {upd/2021-06-24} ИСЖ-привязка коуча к паре оргюнит-кодировка
        self::initTable('bn_policy');    # {upd/2021-06-24} под-статус substate ("доработка")
        self::initTable('bn_individual');  # {upd/2021-08-18} СНИЛС
        self::initTable('bn_deptscheme');  # {upd/2021-08-24} b_printagent стал строкой (можно ввести своего агента реал.для продукта)
        self::initTable(PM::T_INDIVIDUAL); # {upd/2021-08-18} СНИЛС
        self::initTable(PM::T_POLICIES); # {upd/2021-06-24} под-статус substate ("доработка") 2021-07-15 - statedate
        self::initTable(PM::T_OU_PROPS); # {upd/2021-07-02} alf_dept_properties.formname
        self::initTable(PM::T_BURDEN); # {upd/2021-07-02} alf_burden.currency нагрузка с разбивкой по валютам (2в1 !)
        self::initTable('alf_nsjprograms'); # {upd/2021-06-30} поле sp_tvs_comment, 2021-07-09: ins_rules
        self::initTable('alf_plsign_cards'); # {upd/2021-08-27} ДМС ПЭП: поле cardtype
        # заливаю URL правил страхования для 2в1
        self::updateTbl('alf_nsjprograms',
          ['ins_rules'=>'https://www.allianz.ru/content/dam/onemarketing/cee/azru/documents/правила-страхования/страхование-жизни/20210706/Правила-страхования-НСЖ-с-гарантией.pdf'],
          ['programid'=>'2in1']);

        if(FALSE && class_exists('CfgAnketa')) {
            self::initTable(CfgAnketa::T_ANKETALST);
            self::initTable(CfgAnketa::T_ANKETAFLD);
        }
        self::footer();
    }

    public function patch22() {
        self::drawHeader(__FUNCTION__);
        self::initTable('bn_policy'); # добавил индексы, reinvest
        self::initTable('bn_individual'); # passportissueplace - место выдачи расширил, новое agentid
        self::initTable('bn_beneficiary'); # passportissueplace
        self::initTable('bn_schemesubtype'); # 2021-11-25 добавил rulename
        self::initTable('bn_deptscheme'); # 2021-11-25 добавил maxsabase
        self::initTable(PM::T_POLICIES); # поле reinvest
        self::initTable(PM::T_INDIVIDUAL); # поле docissued расширено
        self::initTable(PM::T_BENEFICIARY); # поле docissued расширено
        self::initTable('CHECK_POLICY_DMS'); # таблица приема полисов из Диасофт (Анна Пахомова) - 2021-11-30
        self::initTable('CHECK_BSO_DMS'); # таблица приема БСО из Диасофт (Анна Пахомова) - 2021-12-08
        self::initTable(PM::T_CURATORS); # поле b_leadcouche
        self::initTable(PM::T_LEADGEN); # новая - лидогенераторы
        self::initTable(PM::T_EVENTLOG); # idx evtype
        $vSql = <<< EOSQL
SELECT p.module, p.stmt_id id, p.policyno, p.created,p.datefrom,p.datetill, p.policy_prem, p.currency,
  p.insurer_fullname, p.stateid, p.updated
  FROM alf_agreements p
UNION
SELECT 'investprod' module, p.id, p.policyno, p.created,p.datefrom, p.datetill, p.payamount/100 policy_prem, p.currency,
  CONCAT(ind.lastname,' ',ind.firstname,' ',ind.middlename ) insurer_fullname, p.stateid, p.updated
  FROM bn_policy p, bn_individual ind WHERE ind.id=p.insurerid
EOSQL;
        self::makeView('v_all_policies', $vSql);  # {upd/2021-10-12}

        $vSql = <<< EOSQL
SELECT p.stmt_id id, p.module, p.policyno, p.created,p.datefrom,p.datetill, p.policy_prem, p.currency, '0' insurerid,
  ind.fam insrfam,
  ind.imia insrimia,
  ind.otch insrotch,
  IF(ind.rez_country=114, 1,0) resident_rf,
  ind.docser insrdocser,
  ind.docno insrdocno,
  ind.docdate insrdocdate,
  ind.docissued insrdocissued,
  ind.docpodr insrdocpodr,
  ind.birth insrbirth,
  ind.birth_country insrbirthplace,
  cnt.countryname insraddr_country,
  rg.regname insraddr_region,
  ind.adr_city insraddr_city,
  ind.adr_street insraddr_street,
  ind.adr_house insraddr_house,
  ind.adr_corp insraddr_corp,
  ind.adr_flat insraddr_flat,
  ind.adr_zip insraddr_zip

  FROM alf_agreements p, alf_agmt_individual ind, alf_countries cnt, regions rg
  WHERE p.module='nsj' AND p.stateid=11 AND ind.stmt_id=p.stmt_id AND ind.ptype='insr'
  AND cnt.id=ind.adr_countryid AND rg.id=ind.adr_country

UNION

SELECT p.id, 'investprod' module, p.policyno, p.created,p.datefrom, p.datetill, p.payamount/100 policy_prem, p.currency, p.insurerid,
  ind.lastname insrfam,
  ind.firstname insrimia,
  ind.middlename insrotch,
  IF((ind.resident OR ind.citizenship='114'), 1,0) resident_rf,
  ind.passportseries insrdocser,
  ind.passport insrdocno,
  ind.passportissuedate insrdocdate,
  ind.passportissueplace insrdocissued,
  ind.subdivisioncode insrdocpodr,
  ind.birthdate insrbirth,
  ind.birthplace insrbirthplace,
  cnt.countryname insraddr_country,
  rg.regname insraddr_region,
  ind.addr_city insraddr_city,
  ind.addr_street insraddr_street,
  ind.addr_houseno insraddr_house,
  ind.addr_korpus insraddr_corp,
  ind.addr_flat insraddr_flat,
  ind.addr_postcode insraddr_zip

  FROM bn_policy p, bn_individual ind, alf_countries cnt, regions rg
  WHERE p.stateid=11 AND ind.id=p.insurerid
  AND cnt.id=ind.addr_countryid AND rg.id=ind.addr_region
EOSQL;
        self::makeView('v_policies_01', $vSql);  # {upd/2021-10-12}

        # {upd/2021-12-08} добавляю роль "продающего сотрудника" (Альянс Эксклюзив)
        $arRole = [
           'rights' => [
             PM::RIGHT_LEADSALES => [ 'checkbox','Оформление договоров от лидогенераторов' ],
          ]
          ,'roles' => [
             'sales_worker' => [
               'title'  =>'Продающий сотрудник'
              ,'rights' => [ PM::RIGHT_LEADSALES => 1 ]
             ],
          ]
        ];

        aclTools::setPrefix(appEnv::TABLES_PREFIX);
        $rolesLog = aclTools::upgradeRoles('', $arRole, TRUE);
        self::echoText($rolesLog);
        self::footer();
    }

    # 2021-12-23- 2022-06-01, patch 23
    public function patch23() {
        self::drawHeader(__FUNCTION__);
        self::initTable('bn_confirm'); # даты последних подтв. точки продаж plugins/investprod
        self::initTable(investprod::TABLE_POLICIES); # 2022-11-09 ... 2023-01-18 новые поля как в alf_agreements
        self::initTable(investprod::TABLE_PRODUCTS); # 2023-01-24 убрал expterm
        self::initTable('alf_acqemulator');  # 2021-12-23 таблица "эмуляционных" ордеров эквайринга, app/acquiring.emulator.php
        self::initTable('alf_sed_downtime'); # 2021-12-23 график остановок СЭД, plugins/sedexport
        self::initTable('bn_beneficiary'); # 2022-02-18 новые поля для ВП ЮЛ ИСЖ
        self::initTable(PM::T_POLICIES); # 2022-02-28 новое поле vip
        self::initTable(PM::T_SENTEMAIL); # 2022-04-29 новое поле subj
        self::initTable(LifeAg::T_HISTINCOME); # 2022-06-01...25 справочник ист.доходности
        self::initTable(PM::T_INDIVIDUAL); # 2022-07-18 permit_type
        self::initTable(PM::T_AGMTDATA); # 2022-07-21 поля b_clrisky, b_oprisky 2022-10-26:datefrom_max,indexes,date_release,date_release_max
        self::initTable(PM::T_SENTEMAIL); # alf_sentemail index
        $fixQry = "update ".PM::T_AGMTDATA . " SET date_release_max=datefrom_max WHERE date_release_max=0 AND datefrom_max>0"; # 2022-12-22
        appEnv::$db->sql_query($fixQry);

        self::initTable(plsign::T_CARDLIST); # alf_plsign_cards - 2022-08-10 валютные ДМС ПЭП, ограничение на время оплаты
        self::initTable('alf_acqemulator'); # 2022-08-11 order_expire - ограничение на время оплаты
        self::initTable(PM::T_ICONS_PROGRAMS); # 2022-09-30...2023-03-16 Настройка программ (iconst)
        self::initTable(PM::T_CLIENT_CONFIRM); # 2022-12-09 persontype расширил

        # self::initTable(PM::T_SPECDATA);

        self::initTable(PM::T_SPORTS); # 2022-01-14 Виды спорта с коэф-тами

        $fixQry = "update ".PM::T_SPORTS . " SET spkey=SUBSTR(MD5(sportname),1,10)"; # 2021-01-19
        appEnv::$db->sql_query($fixQry);
        $affected = appEnv::$db->affected_rows();
        self::echoText("sporting: обновление ключей: $affected");
        self::upgradeRoles('irisky'); # 2022-08-17 Рисковые продукты - генерю роли-права
        self::upgradeRoles('investprod'); # 2023-01-12 ИСЖ - дополнения, добавлен андеррайтер
        self::upgradeRoles('lifeag'); #
        self::upgradeRoles('pochvo'); #
        # self::upgradeRoles('plgkpp');
        # self::upgradeRoles('pochvo');
        self::initTable(PM::T_BENEFICIARY); # 2023-02-13 tax_rezident

        self::initTable(PM::T_SMSLOG); # 2023-01-16 - module, policyid
        self::initTable(PM::T_SMS_CHECKLOG); # 2023-01-31
        self::initTable(PM::T_OU_PROPS); # 2023-02-16 новые поля для платежных док.

        if(class_exists('agentvr')) { # 2023-01-17 - агентские акты вз-расчетов
            self::initTable(agentvr::T_CARDLIST);
            self::initTable(agentvr::T_AGENTS);
            self::upgradeRoles('agentvr');
        }

        if(is_file('app/tpl/currates_hist.tpl'))
            self::initTable(PM::T_CURRATES_HIST);

        self::footer();
    }
    # 2023-05-11
    public function patch24() {
        self::drawHeader(__FUNCTION__);
        self::initTable(plsign::T_CARDLIST);
        self::initTable(PM::T_POLICIES);
        self::initTable(PM::T_ICONS_PROGRAMS);
        self::initTable(PM::T_ICONS_RISKS); # 2023-05-11 - formula_action
        self::initTable(PM::T_COUNTRIES); # {upd/2023-05-03} санкции
        self::initTable(PM::T_REGIONS); # {upd/2023-05-03} санкции
        self::initTable(oncob::T_TARIFFS);
        self::initTable(PM::T_ANYFILES); # унив.список файлов
        self::initTable(PM::T_AGENT_LOG); # лог опер.агентов
        # {upd/2023-05-15} Дейнекина - замена кракого названия риска
        self::updateTbl(PM::T_RISKS, ['shortname'=>'Инвалидность НС'],  ['riskename'=>'invalid_disorder_acc']); # PM::T_RISKS = 'alf_agmt_risks'
        # {upd/2023-06-21} добавляю краткое назв. смерти ЛП с ОВ (ЛП ОУСВ)
        self::updateTbl('alf_agmt_risks', ['shortname'=>'Смерть Застрахованного по любой причине с ОВ'],  ['riskename'=>'death_any_delay']);

        /*
        # обновленные названия рисков в справочнике
        self::updateTbl(PM::T_RISKS, ['longname'=>'Инвалидность Застрахованного с установлением I, II, III группы инвалидности в результате несчастного случая'],
           ['riskename'=>'invalid_disorder_acc']
        );
        self::updateTbl(PM::T_RISKS, ['longname'=>'Травма Застрахованного в результате несчастного случая'],
           ['riskename'=>'trauma_acc']
        );
        */
        self::footer();
    }

    # 2023-07-25
    public function patch25() {

        self::drawHeader(__FUNCTION__);
        self::runQueries(["drop table if exists lifeag_finplan"]); # lifeag::T_FINPLAN - передумал создавать
        self::initTable(PM::T_ICONS_PROGRAMS);
        self::initTable(PM::T_ICONS_RISKS);
        self::initTable(PM::T_AGENT_LOG); # лог опер.агентов
        self::initTable(PM::T_OU_PROPS); # необяз.ввод телефона партнером, ввод названия самим менеджером
        self::initTable(ndc::TABLE_TARIFFS); # alf_ndc_tariffs - GL - правки тарифа
        self::initTable(PM::T_POLICIES); # 2023-10-23 new subtypeid для нового ИСЖ
        self::initTable(investprod::TABLE_POLICIES); # 2023-10-07 new metatype
        $textMeta = PatcherTasks::updateMetaType();
        self:: echoText($textMeta);
        $textMeta = PatcherTasks::updateMetaType(PM::INVEST);
        self:: echoText($textMeta);
        #
        self::updateTbl(PM::T_RISKS,
          # новые значения:
          ['shortname'=>'Смерть в результате ДТП','longname'=>'Смерть Застрахованного в результате ДТП'],
          # критерий отбора where:
          ['riskename'=>'death_on_transport']
        ); # PM::T_RISKS = 'alf_agmt_risks'
        self::updateTbl(PM::T_RISKS,
          # новые значения:
          ['shortname'=>'Травма от НС','longname'=>'Травма Застрахованного в результате несчастного случая'],
          # критерий отбора where:
          ['riskename'=>'trauma_acc']
        ); # PM::T_RISKS = 'alf_agmt_risks'
        # хотят НСЖ для банков развести с агентами

        self::footer();
    }

    public function patch26() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_POLICIES); # 2023-10-23:new subtypeid (для invins), 2023-11-30:policyno увеличен,2023-12-14:packid
        self::initTable(PM::T_ICONS_PROGRAMS); # 2023-11-02 новые поля под МК USD(откл.UW, откл.RUR)
        self::initTable(PM::T_UPLOADEDFILES); # 2023-11-14 filename увеличил
        self::initTable(PM::T_COUNTRIES); # 2023-12-08 добавил longname (для Диасофт)
        self::upgradeRoles('ndc'); # новая роль спец-сотрудника СК
        self::upgradeRoles('nsj'); # новая роль спец-сотрудника СК
        # self::upgradeRoles('plgkpp');

        if(class_exists('madms')) {
            $defaultTar = ['tarifname' => 'Основной тариф'];
            self::initTable(madms::T_TARIFFS, $defaultTar); # 2022-11-23 ДМС тариф с авто-добавлением 1 записи
            self::initTable(madms::T_DMS_ANKETA); # 2022-12-28 ДМС анкета заболеваний Застрахованного
            self::upgradeRoles('madms');
        }

        if(class_exists('invins')) {
            self::upgradeRoles('invins');
            self::initTable('invins_date_subtype');
            self::initTable('invins_deptscheme');
            self::initTable('invins_group');
            self::initTable('invins_programs');
            self::initTable('invins_riskgroup');
            self::initTable('invins_subtypes');

            # добавляю новый риск в справочник:
            $cuponRsk = [
              'riskpid'=>'88888',
              'riskename'=>'endowment_coupon',
              'shortname'=>'Дожитие до установленных дат',
              'longname'=>'Дожитие Застрахованного до установленных дат',
              'exportname'=>'ДД',
            ];
            self::updateTbl(PM::T_RISKS, $cuponRsk, ['riskename'=>'endowment_coupon']);
        }

        # склеиваю префикс с телефоном (больше раздельно не вводим, маска ввода - на весь телефон!
        $fixQry = "update alf_agmt_individual SET phone=CONCAT('(',phonepref,')',phone), phonepref='' WHERE phonepref<>''";
        appEnv::$db->sql_query($fixQry);
        $affected1 = appEnv::$db->affected_rows();
        self::echoText("Обновление телефонов-1, исправлено записей: $affected1");

        $fixQry = "update alf_agmt_beneficiary SET phone=CONCAT('(',phonepref,')',phone), phonepref='' WHERE phonepref<>''";
        appEnv::$db->sql_query($fixQry);
        $affected1 = appEnv::$db->affected_rows();
        self::echoText("Обновление телефонов-2, исправлено записей: $affected1");

        # $textIconst = PatcherTasks::upgradeIconstProdCfg(); # уже неактуально, везде проставилось
        $textLog  = PatcherTasks::upgradeNDCProdCfg();
        self:: echoText($textLog);
        self::footer();
    }

    public function patch27() {
        self::drawHeader(__FUNCTION__);

        self::initTable('invins_programs');
        self::initTable(PM::T_INDIVIDUAL); # 2024-02-12: pepstate
        self::initTable(PM::T_BENEFICIARY); # 2024-02-12: pepstate
        self::initTable(PM::T_CLIENT_CONFIRM);
        self::initTable(PM::T_DEPT_PROD_PRG);

        self::initTable(PM::T_ICONS_PROGRAMS); # 2024-03-05 поле stability, 2024-04-18 - discounts
        self::initTable(PM::T_ICONS_RISKS); # 2024-04-18 - minterm мин.срок страх-я для риска

        self::initTable(PM::T_STMT_RANGES); # 2024-03-07 to INODB

        self::initTable(PM::T_CURLIST); # 2024-05-17 список валют под загрузку
        # сразу добавлю базовые валюты
        self::updateTbl(PM::T_CURLIST, ['curcode'=>'EUR', 'curname'=>'Евро'], ['curcode'=>'EUR']);
        self::updateTbl(PM::T_CURLIST, ['curcode'=>'USD', 'curname'=>'Доллар США'], ['curcode'=>'USD']);

        # self::initTable(PM::T_PLCPACKS);

        # исправляю несовместимость collate между alf_agreements и alf_agmt_data (для alfojobs):
        $qryArr = [
          'alter table alf_agreements CHARACTER SET utf8 COLLATE utf8_unicode_ci',
          "alter table alf_agreements modify `module` varchar(30) NOT NULL DEFAULT '' collate utf8_unicode_ci"
        ];
        self::runQueries($qryArr);

        $invalRsk = [ # {upd/2024-04-18} новый риск инвалидности ЛП+НС3
          'riskpid'=>'89000',
          'riskename'=>'invalid_any12ns3',
          'shortname'=>'Инвалидность ЛП 1,2гр. НС 3гр.',
          'longname'=>'Инвалидность Застрахованного с установлением I,II,III группы инвалидности '
            . 'в результате несчастного случая или I,II группы инвалидности в результате заболевания',
          'exportname'=>'И12 ЛП+И3 НС',
        ];
        self::updateTbl(PM::T_RISKS, $invalRsk, ['riskename'=>'invalid_any12ns3']);

        if(class_exists('bugi')) {
            self::initTable(bugi::T_TASKS); # список задач(тикетов)
            self::initTable(bugi::T_TASKHIST); # история
            self::initTable(bugi::T_FILES); # список файлов
            self::initTable(bugi::T_EXECUTIVES); # список осн исполнителей
            self::upgradeRoles('bugi');
        }
        if(class_exists('boomer')) {
            self::initTable(boomer::T_TARIFFS); # тарифы
            self::upgradeRoles('boomer');
            $tarData = ['tarifname'=>'Основной тариф'];
            self::updateTbl(boomer::T_TARIFFS, $tarData, ['tariffid'=>'1']);
        }
        if(class_exists('vstexp')) {
            self::initTable(vstexp::T_TARIFFS); # тарифы
            $tarData = ['tarifname'=>'Основной тариф','deptid'=>'0'];
            self::updateTbl(vstexp::T_TARIFFS, $tarData, ['deptid'=>'0'], TRUE);

            $tarUKB = [ # тариф для ЮКБ
              'deptid' => '4899',
              'tarifname'=>'Тариф для Юникредит',
              'codirovka' => 'PRV',
              'min_prem'=>'400000',
              'sa_endowment'=>'111',
              'sa_death_any1'=>'105.5',
              'sa_death_any2'=>'111',
              # 'k_redemp'=>'90;100',
            ];

            $arProd = ['module'=>'vstexp', 'prikaz_no'=>'99','prikaz_date'=>date('Y-m-d'),'stampid'=>5];
            self::updateTbl(PM::T_PRODCFG, $arProd, ['module'=>'vstexp'],TRUE);
            self::upgradeRoles('vstexp');
        }
        # pesoch - модуль "песочница"
        if( class_exists('pesoch') ) {
            self::initTable(pesoch::T_TARIFFS); # тарифы
            $arTarif = ['tarifname'=>'Основной тариф'];
            self::updateTbl(pesoch::T_TARIFFS, $arTarif, ['deptid'=>'0'], TRUE);
            # сразу добавлю настройку продукта со штампом-подписантом
            $arProd = ['module'=>'pesoch', 'prikaz_no'=>'99','prikaz_date'=>date('Y-m-d'),'stampid'=>5];
            self::updateTbl(PM::T_PRODCFG, $arProd, ['module'=>'pesoch'], TRUE);

            self::upgradeRoles('pesoch');
        }

        self::footer();
    }

    # 2024-07-15
    public function patch28() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_CURLIST); # 2024-06-04 CHARSET!
        # сразу добавлю базовые валюты
        self::updateTbl(PM::T_CURLIST, ['curcode'=>'EUR', 'curname'=>'Евро'], ['curcode'=>'EUR']);
        self::updateTbl(PM::T_CURLIST, ['curcode'=>'USD', 'curname'=>'Доллар США'], ['curcode'=>'USD']);
        self::updateTbl(PM::T_CURLIST, ['curcode'=>'CNY', 'curname'=>'Юани'], ['curcode'=>'CNY']);
        self::initTable(PM::T_OU_PROPS); # 2024-05-20 поле b_edo
        self::initTable(PM::T_DEPT_PROD); # 2024-06-14 поле anketa_output default = 'P'
        self::initTable(PM::T_EXPORTCFG); # 2024-06-20 поле комментария adminnotes
        self::initTable(PM::T_PLCBIND); # 2024-06-27 Регистрация авто-привязок полисов в КК
        self::initTable(PM::T_EQPAYMENTS); # 2024-08-08 autopay
        self::initTable(PM::T_ICONS_PROGRAMS); # обновление структуры
        self::initTable('alf_acqemulator');  # 2024-10-09 таблица "эмуляционных" ордеров эквайринга, app/acquiring.emulator.php

        self::initTable(PM::T_ICONS_RISKS); # 2024-09-11 - spectype
        # Нужен новый риск - СЛП с рег.выплатами при смерти
        $recRisk = [
          [ 'condition' => ['riskename'=>'death_any_rent'],
            'data' => [
               'riskpid' => '10123',
               'riskename'=>'death_any_rent',
               'riskgroup'=>'1','riskename'=>'death_any_rent',
               'shortname'=> 'Смерть ЛП с периодическими выплатами ЗВ',
               'longname'=>'Смерть Застрахованного взрослого по любой причине с периодическими выплатами в течение установленного срока',
               'exportname'=>'СЛП (период.выплаты)'
              ]
          ],
        ];
        self::addNewRecords(PM::T_RISKS, $recRisk);

        # boomer тарифы - создаю новый для банков (БКС)
        self::initTable(boomer::T_TARIFFS); # 2024-08-27 тарифы - coeff_i1

        $boomerTrf = [ 'tarifname'=>'Тариф Банки 2024-Q3', 'codirovka'=>'BMGB' ];
        self::updateTbl(boomer::T_TARIFFS, $boomerTrf, ['tarifname'=>'Тариф Банки 2024-Q3'], TRUE);
        # новый банк-тариф с 3-го квартала 2024

        self::footer();
    }

    public function patch29() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_SMSLOG); # 2024-10-25 новое поле account
        # self::initTable('tstdeptlist');
        # self::initTable('tstemployee');
        # self::initTable('tstvacations');
        self::initTable(PM::T_PRODCFG); # 2024-07-09 rules поля для правил стр.
        self::initTable(PM::T_AUTOPAYMENTS); # 2024-06-27 таблицы для работы авто-платежей
        self::initTable(PM::T_PAYPLAN);
        self::initTable(PM::T_AGENT_LOG); # 2024-11-21 - новые поля для обновленного отчета по калькуляциям (Вигдорчик)
        self::initTable(PM::T_DEPT_PROD); # 2024-12-06 - days_to_from
        self::initTable(PM::T_OU_PROPS); # alf_dept_properties

        self::updateTbl(PM::T_RISKS, ['longname'=>'Смерть Застрахованного по любой причине (дополнительное покрытие)'], ['riskename'=>'death_any_addcover']);
        self::updateTbl(PM::T_RISKS, ['longname'=>'Смерть Застрахованного в результате несчастного случая'], ['riskename'=>'death_acc_addcover']);
        self::updateTbl(PM::T_RISKS, ['longname'=>'Инвалидность застрахованного в результате несчастного случая'], ['riskename'=>'invalid_disorder_acc']);
        self::updateTbl(PM::T_RISKS, ['longname'=>'Травма Застрахованного ребенка в результате несчастного случая'], ['riskename'=>'child_trauma_acc']);

        self::footer();
    }

    public function patch30() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_CLIENTS); # 2025-02-26 alf_clients
        self::initTable(PM::T_POLICIES); # 2025-03-20 alf_agreements.authorid
        self::initTable(PM::T_INDIVIDUAL); # 2025-04-16 birth_countryid
        self::initTable(PM::T_AGMTDATA); # 2025-02-26 client_id, 2025-04-04 - dt_client_letter
        self::initTable(PM::T_AGENT_LOG); # 2025-02-27:client_id 2025-06-19:payment
        self::initTable(PM::T_RISKS); # 2025-03-06 новое поле adultname
        self::initTable(PM::T_AGMTDATA); # 2025-03-20 поле all_uwcodes для хранения ВСЕХ причин UW
        self::initTable(PM::T_DEPT_PROD); # 2025-04-29 subtypecode
        self::initTable(PM::T_BENEFICIARY); # 2025-07-28: pepstate

        self::initTable(PM::T_USERPARAMS); # 2022-12-28

        # заполняю новое поле adultname
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Дожитие Застрахованного лица до окончания срока страхования'], ['riskename'=>'endowment']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Смерть Застрахованного лица по любой причине'], ['riskename'=>'death_any']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Смерть Застрахованного взрослого по любой причине (дополнительное покрытие)'], ['riskename'=>'death_any_addcover']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Смерть Застрахованного взрослого в результате несчастного случая'], ['riskename'=>'death_acc']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Инвалидность Застрахованного взрослого с установлением I, II группы инвалидности в результате несчастного случая или заболевания'], ['riskename'=>'invalid_12_any']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Инвалидность Застрахованного взрослого с установлением I, II, III группы инвалидности в результате несчастного случая'], ['riskename'=>'disability_123_acc']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Хирургические операции, проведенные Застрахованному взрослому в результате несчастного случая'], ['riskename'=>'surgery']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Травма Застрахованного взрослого в результате несчастного случая'], ['riskename'=>'trauma_acc']);
        self::updateTbl(PM::T_RISKS, ['adultname'=>'Смерть Застрахованного взрослого в результате ДТП'], ['riskename'=>'death_on_transport']);
        # self::updateTbl(PM::T_RISKS, ['adultname'=>'xxx'], ['riskename'=>'xx']);
        self::footer();
    }

    public function patch31() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_INDIVIDUAL); # 2025-09-04 коды ФИАС в адресах
        self::initTable(PM::T_BENEFICIARY); # 2025-09-04 коды ФИАС
        self::initTable('alf_stamps'); # 2025-09-08...17 signer_digialias,signer_email
        self::initTable(PM::T_APICLIENTS); # 2025-09-19 b_logging
        self::initTable(PM::T_APILOG); # 2025-09-18 мониторинг-лог API вызовов
        self::initTable(PM::T_SESARHIV); # 2025-10-09 архив ПЭП кодов
        self::initTable(PM::T_CLIENTS); # full adr
        self::upgradeRoles('_global_'); # 2025-10-10 - список "глобальных" ролей (комплаенс, ИБ)
        if(class_exists('finmonitor'))
            self::initTable(finmonitor::T_WHITELIST);

        self::initTable(PM::T_CHATBOT_HIST); # 2025-12-17 таблицы для чат-бота
        self::initTable(PM::T_CHATBOT_CONTEXTS); # 2025-12-17

        self::footer();
    }

    /**
    public function patchNNN() {
        self::drawHeader(__FUNCTION__);
        self::initTable(PM::T_ZZZ);
        self::footer();
    }
    **/
}

if ( !SuperAdminMode() ) {
    appEnv::echoError('err-no-rights');
    return;
}
$patcher = new Patcher();
$action = (empty(appEnv::$_p['action']) ? 'form' : appEnv::$_p['action']);
if (method_exists($patcher, $action)) $patcher->$action();
else appEnv::echoError("ERROR: команда $action в данном патчере не поддерживается ");
