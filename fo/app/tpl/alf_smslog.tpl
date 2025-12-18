# updated 2024-12-15
ID|alf_smslog
CHARSET|@GetMainCharset
DESCR|Журнал отправленных СМС
# AUDITING|DataAudit
DropUnknown|1
BRORDER|id desc
BRWIDTH|800
SEARCH|module,policyid,client_phone
SEARCHCOLS|1
EDITFORMWIDTH|600
FIELD|id||ИД||PKA||||S||1|0||
FIELD|sessionid||Сессия||VARCHAR|40|1|''|0||R|TEXT,class="ibox w300 ct"
FIELD|module||Модуль||VARCHAR|30|1|''|1||R|TEXT,class="ibox w100"|ix_module
FIELD|account||Аккаунт отправки||VARCHAR|30|1|''|1||R|TEXT,class="ibox w100"
FIELD|policyid||ID полиса-карточки|Карточка|INT|20|1||1||R|TEXT,class="ibox w200 ct"
FIELD|client_phone||Телефон клиента|Телефон|varchar|20|1|''|1||R|TEXT,class="ibox w200 ct"
FIELD|send_date||Дата подтверждения|Дата|TIMESTAMP||1||1|@to_char|R|TEXT,class="ibox w200 ct" maxlength='10'
FIELD|ipaddr||IP адрес клиента||VARCHAR|32|1|''|0||R|TEXT,class="ibox w200 ct"
FIELD|smstext||Текст||varchar|100|1||0||R|TEXT,class="ibox w100prc" maxlength='100'
FIELD|send_result||успех|Успех|VARCHAR|20|1|0|1||R|TEXT,class="ibox w100 ct" maxlength='30'
FIELD|resultcode||Код от сервиса|Ответ сервиса|VARCHAR|30|1|''|1||R|TEXT,class="ibox w200" maxlength='30'