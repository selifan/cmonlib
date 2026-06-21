/* js functions for McpAssistant block, 2026-06-19 */
mcpassistant = {
   backend: "./?plg=chatbot",
   startGenerate: function() {
      var prms = $('#fm_mcpassistant').serialize();
      window.open('./?plg=chatbot&action=McpGenerate&'+prms);
   },
   chgCfgType: function() {
     var vForm = $("select#cfgtype").val();
     $("#btn_run").prop("disabled", (vForm==""));
   },
   chgTranslate: function(obj) {
     if(obj.checked) $("tr.tr_lm_translate").show(200);
     else $("tr.tr_lm_translate").hide(200);
   },
   chgTranslator: function() {
     var vTrans = $("select#translator_model").val();
     // $("#make_english").prop("disabled", (vTrans==""));
   },
   saveConfig: function() {
      var prms = $('#fm_mcpassistant').serialize();
      prms += "&action=McpSaveConfig";
      asJs.sendRequest(this.backend, prms, true, false, null);
   },
   addTableDetBlock: function() {
     // asJs.timedNotification("TODO", 2);
     var trid = "tr_" + Math.floor(Math.random()*1000000);
     var htmlBlock = "<tr id='"+trid+"'><td>Таблица: <input type='text' name='dt_tbname[]' class='form-control d-inline w200'/>"
       + " <input type='button' onclick=\"$('#"+trid+"').remove()\" class='btn btn-primary' value=\"Удалить\" />"
       + "<br>Описание/детали о таблице <textarea name='dt_tbdetails[]' class='form-control' style='height:60px;overflow:auto' /></td></tr>";
     $("#t_frm_content").append(htmlBlock);
   }
};
