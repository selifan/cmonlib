# chatbot_contexts (chatbot::T_CHATBOT_CONTEXTS) - настроенные контексты (spaces) для LLM чат-бота 2025-12-18
ID|chatbot_contexts
DESCR|Настроенные контексты
TYPE|MYISAM
CHARSET|UTF8
COLLATE|utf8_unicode_ci
SEARCH|userid,context_name
# AUDITING|DataAudit
DropUnknown|1
EDITFORMWIDTH|800
FIELD|id||ИД||PKA||||S||1|0||
FIELD|userid||Пользователь||VARCHAR|20|1|'__system__'|S||1|TEXT,class="form-control w100 d-inline"|ix_userid
FIELD|context_name||Название||VARCHAR|60|1|''|1||1|TEXT,class="form-control w300 d-inline" maxlength="60"
FIELD|public||Публичный|Пуб|TINYINT|1|1|1|1||1|CHECKBOX
FIELD|content||Текст контекста|Контекст|TEXT||1|''|1|@astedit::brShortText|1|TEXTAREA,class="form-control d-inline" style="width:100%;height:100px;overflow:auto"
EXTENSION|reports|chatbot_contexts|*