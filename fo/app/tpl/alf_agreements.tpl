# полисы, все продукты кроме старых инвестов / upd.2023-12-15
ID|alf_agreements
DESCR|Список договоров
# SDESCR|
CHARSET|@GetMainCharset
DropUnknown|1
search|stateid,policyno,created/range
BEFOREDELETE|AsteditHelper::AgreemBeforeDelete
SEARCHCOLS|1
# BRORDER|updated DESC
BRORDER|stmt_id DESC
CONFIRMDEL|1
# AFTEREDIT|usersAfterEdit
# BuildUserFullName
# EDITFORMWIDTH|560
# CHILDTABLE|table_name|field_here|field_inchild_table||Сообщение о невозможности удаления
#AUDITING|DataAudit
#FIELD| int_id |f_name |descr |sdesc |type |len |not_null| default[,new_val]| b_cond |b_formula | e_cond | e_type[,sub-params]
FIELD|stmt_id||ИД||PKA||||S||1|0||
FIELD|deptid||Подразделение||VARCHAR|12|''|1|1|@OrgUnits::GetDeptName,,,,limited w200|A|SELECT,@kppselectDept|ix_deptid
FIELD|headdeptid||Головное подразделение||BIGINT|12|1|0|1|@OrgUnits::GetDeptName|1|SELECT,@OrgUnits::getAllPrimaryDepts|ix_headdeptid
FIELD|metatype||Канал||INT|3|1|0|0|@OrgUnits::decodeMetaType|1|TEXT|ix_meta
FIELD|userid||Сотрудник(кто создал запись)||VARCHAR|20|1|''|0||A|SELECT,@kppSelectUser|ix_userid
FIELD|authorid||Сотрудник||VARCHAR|20|1|''|0|@getUserFio|A|TEXT|
FIELD|extclientid||ИД клиента (API)||VARCHAR|30|1|''|0||0|TEXT
FIELD|module||ИД плагина||VARCHAR|30|1|''|0||1|TEXT,style='width:60px' maxlength='10'|ix_module
FIELD|programid||Программа||VARCHAR|30|1|''|1|@PolicyModel::decodeProgramName|1|TEXT,style='width:60px' maxlength='30'
FIELD|version||версия||INT|6|1|1|0||1|TEXT
# Добавить когда будешь переносить investprod в invins:
FIELD|subtypeid||Подтип,Баз.Актив||INT|6|1|0|0||1|TEXT
FIELD|prodcode||кодировка||VARCHAR|10|1|''|S||1|TEXT,style='width:60px' maxlength='10'
FIELD|policyno||Номер|Номер полиса|VARCHAR|30|1|''|S|@PolicyModel::showPolicyNo|A|TEXT,class='form-control w120 d-inline' maxlength='20'|ix_policyno

FIELD|insurer_type||Тип стр-ля||TINYINT|1|1|0|1||1|SELECT,1=ФЛ;2=ЮЛ
FIELD|equalinsured||Застрах=стр-ль||TINYINT|1|1|0|1||1|CHECKBOX
FIELD|no_benef||нет назначенных выгодоприобр.||TINYINT|1|1|0|1||1|CHECKBOX
# FIELD|send_korresp||куда слать корресп||INT|1|1|0|0||1|TEXT
# страхователь - ФИО/название ЮЛ
FIELD|insurer_fullname||ФИО или название ЮЛ страхователя|Страхователь|VARCHAR|80|1|'',|1||1|TEXT
# Застрахованный - ФИО
FIELD|insured_fullname||Застрахованный||VARCHAR|80|1|'','new'|1||1|TEXT
# Застр.ребенок - в alf_agmt_individual, ptype='child'
FIELD|term||срок||INT|4|1|0|0||1|TEXT
FIELD|termunit||строк страх-ед.измер.||CHAR|1|1|'Y'|0||1|SELECT,Y=год;M=мес;D=дней
FIELD|termmonth||доп срок мес||INT|2|1|0|0||1|TEXT
FIELD|policy_sa||Стр.сумма||DECIMAL|12,2|0|0|1|@moneyFormat|1|TEXT
FIELD|currency||валюта договора|Вал.|CHAR|3|1|'RUR'|1||1|SELECT,RUR=RUR;USD=USD;EUR=EUR
FIELD|policy_prem||Взнос||DECIMAL|12,2|1|0|1|@moneyFormat,,,,rt nowrap|1|TEXT
FIELD|policy_prem_rur||Взнос/руб|рубли|DECIMAL|12,2|1|0|1|@moneyFormat,right|1|TEXT
FIELD|currate||курс на дату оформл.|курс|DECIMAL|8,4|1|1|0||1|TEXT
FIELD|rassrochka||оплата||INT|4|1|0|0||1|SELECT,0=единовр;12=ежегодно;6=раз в полгода
FIELD|comission||комиссия,%||DECIMAL|6,2|1|0|1||1|TEXT
FIELD|med_declar||Соотв.декларации||CHAR|1|1|''|0||1|SELECT,Y=Y;N=N
FIELD|datepay||Дата оплаты||DATE||1|0|1||1|TEXT
FIELD|eqpayed||Онлайн-оплата||DECIMAL|14,2|1|0|1||1|TEXT
# eqpayed = сумма онлайн-оплаты (руб), если полис оплачен через сервис эквайринга

FIELD|pepstate||Результат проверки (PEPs/паспорт)|PEPs|INT|4|1|0|1||1|TEXT
# pepstate: 0 - проверки не было, 100 - OK; 101-риск(PEPS) (бинарная комбинация типов обнаруж.)

FIELD|billno||Номер счета||VARCHAR|40|1|''|0||1|TEXT
FIELD|platno||Номер платежки||VARCHAR|40|1|''|0||1|TEXT
# при оплате через эквайринг здесь будет orderId от банка (можно запросить запись из их БД)
FIELD|datefrom||Дата-С||DATE||1|0|1||1|TEXT
FIELD|datetill||По||DATE||1|0|1||1|TEXT
FIELD|tranchedate||дата транша(расчетная)||DATE||1|0|0||1|TEXT
FIELD|tariffid||ID расч.тарифа|тариф|BIGINT|20|1|0|0||1|TEXT
FIELD|recalcby||Кто делал перерасчет|recalc|INT|6|1|0|1||1|TEXT
FIELD|stmt_stateid||Статус заявления|Ст.заявл.|INT|4|1|0|1|@appEnv::viewStmtState|1|
FIELD|stateid||Статус договора|статус|INT|2|1|0|1|@AgStates::viewAgmtState,,,,@AgStates::cellStateClass|1|SELECT,@applists::getStateList,class='form-select w120 d-inline'
FIELD|substate||под-статус||INT|4|1|0|0||1|SELECT,0=Станд.;101=На доработке;102=Доработка выполнена;110=UW-пересчет, class="form-select d-inline w-auto"
# bptype - тип бизнес-процесса, по кот.движется договор: 'EDO' = ЭДО/ПЭП, 'STD' - без ЭДО (обычный)
FIELD|bptype||Тип БизПроц|Тбп|VARCHAR|10|1|''|0||1|TEXT

# bpstateid - статус в контексте Бизнес-Процессов стр.компании(агентские договора)!
FIELD|bpstateid||Биз.Проц-Статус|БП|INT|3|1|0|0||1|TEXT
FIELD|bpstate_date||Дата смены БП-статуса|дата-БП|DATETIME||1|0|0||1|TEXT
FIELD|statedate||Дата установки статуса|Дата ст.|DATETIME||1|0|S||1|TEXT
FIELD|state_fatca||Статус FATCA|FATCA|INT|1|1|0|0||1|CHECKBOX
FIELD|reasonid||Код причины отказа/андеррайтинга||int|4|1|0|0||1|TEXT
FIELD|created||Дата оформления|оформл|DATE||1|0|S||1|TEXT
FIELD|updated||Обновление||TIMESTAMP||1||0||0|
FIELD|accepted||акцептован|акц|INT|1|1|0|0||1|TEXT
FIELD|acceptedby||Кем акцептован|кем|VARCHAR|20|1|''|0||1|TEXT
FIELD|uw_acceptedby||согл.UW||INT|10|1|0|0||1|
FIELD|reinvest||Реинвестиция||INT|1|1|0|0||1|
FIELD|vip||VIP-полис||INT|1|1|0|0||1|

# export_pkt - после создания карточки СЭД здесь будет ее cardId
FIELD|export_pkt||пакет выгрузки(СЭД)|выгрузка|VARCHAR|10|1|''|0||1|TEXT

FIELD|previous_id||ИД или номер предыд.полиса||VARCHAR|40|1|0|0||0|TEXT
FIELD|docflowstate||Выгружен в СЭД||INT|4|1|0|0||0|SELECT,0=Нет;1=Да;2=СЭД/UW, class="form-select d-inline w-auto"
FIELD|diss_reason||Код причины расторжения||VARCHAR|20|1|''|0||1|TEXT
FIELD|diss_date||Дата расторжения|дт.расторж|DATE||1|0|0|@to_char|1|TEXT
FIELD|diss_zdate||Дата заявления о расторжении|дт.заявл|DATE||1|0|0|@to_char|1|TEXT
FIELD|signer||Подписант по полису||INT|6|1|0|1||1|TEXT
FIELD|agent||Агент-продавец||INT|8|1|0|1||1|TEXT
FIELD|curator||Куратор||INT|8|1|0|1||1|TEXT
FIELD|coucheid||Коуч||INT|8|1|0|1||1|TEXT
FIELD|anketaid||ID инв анкеты||INT|10|1|0|0||1|TEXT
FIELD|letterno||Исх.Номер письма клиенту||VARCHAR|30|1|''|0||1|TEXT
FIELD|b_test||тестовый||TINYINT|1|1|0|0||1|CHECKBOX
FIELD|sescode||ПЭП-код|ПЭП|VARCHAR|40|1|''|0||1|TEXT|ix_sescode
FIELD|dt_sessign||Дата подписания ПЭП||DATETIME||1|0|0||1|TEXT
FIELD|seller||продавец||VARCHAR|60|1|''|0||1|TEXT
