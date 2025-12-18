# chatbot_hist (PM::CHATBOT_HIST) - сохраненные истории запросов к чатботу 2025-12-17
ID|chatbot_hist
DESCR|Сохраненные истории запросов к чатботу
TYPE|MYISAM
CHARSET|UTF8
COLLATE|utf8_unicode_ci
SEARCH|userid,chatsession_id
# AUDITING|DataAudit
DropUnknown|1
EDITFORMWIDTH|800
FIELD|id||ИД||PKA||||S||1|0||
FIELD|userid||Пользователь||VARCHAR|20|1|''|1||1|TEXT,class="form-control w200 d-inline"|ix_userid
FIELD|chatsession_id||ID чата||VARCHAR|30|1|''|1||1|TEXT,class="form-control w200 d-inline"|ix_sessionid
FIELD|request_time||Время отправки запроса|Дата|DATETIME||1||1||0|TEXT
FIELD|request||Текст запроса||TEXT||1|''|1||1|TEXTAREA,style="width:100%;height:100px;overflow:auto"
FIELD|response||Текст ответа||TEXT||1|''|1||1|TEXTAREA,style="width:100%;height:100px;overflow:auto"
TOOLBAR|<input type="button" id="bt_start_clean" class="btn btn-primary" value="Очистка" onclick="chatHist.startClean()"/>
<SCRIPT>
chatHist = {
  backend: "./?p=chatbot",
  startClean: function() {
    dlgConfirm("Выполнить полную очистку ?", chatHist.performClean, false);
  },
  performClean: function() {
    asJs.sendRequest(chatHist.backend, {"action":"clearChatHistory"},true, false, chatHist.reloadGrid);
  },
  reloadGrid: function() {
    asteditJs.tc['chatbot_hist'].dTable.page(1).draw();
  }

};
<SCRIPT>

