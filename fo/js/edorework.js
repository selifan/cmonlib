/*
* reedo.js,  version 0.10.001 2025-10-09 (c) Zetta Life
*/
EdoRework = { // EdoRework
   key: "",
   key2: "",
   wSec: 0,
   sesCode : '',
   backend: "./reedo.php",
   keyUpPhone: function(obj) {
     console.log('keyUpPhone');
     if (obj.value.length<10) $("#btn_sendsms").prop("disabled","disabled").addClass("disabled");
     else $("#btn_sendsms").removeAttr("disabled").removeClass("disabled");
   },
   sendSMS: function() {
      /*
      var phoneVal = $("#my_phone").val();
      if (phoneVal.length<10) return;
      $.post(EdoRework.backend, {action: "checkUserPhone", key: EdoRework.key, phone:phoneVal}, function(response) {
      */
      EdoRework.key = $("input#hashkey").val();
      // console.log(EdoRework.key);
      $.post(EdoRework.backend, {action: "checkUserPhone", key: EdoRework.key}, function(response) {
        // console.log('response ',response);
        var spl = response.split("|");
        if (spl[0] === '1') {
            $("#stage2").removeClass('hideme'); //show();
            $("#stage1").addClass('hideme'); // hide();
        }
        else if (spl[0] === 'wait') { // пришла команда подождать NN сек.
            EdoRework.setWaitSms(parseInt(spl[1]));
        }
        else TimeAlert(response,3, 'msg_error');
      });
   },
   keyUpSMS: function(obj) {
     if (obj.value.length<4) $("#btn_checksms").prop("disabled","disabled").addClass("disabled");
     else $("#btn_checksms").removeAttr("disabled").removeClass("disabled");
   },
   keyUpSMS2: function(obj) {
     if (obj.value.length<4) $("#btn_checksms").prop("disabled","disabled").addClass("disabled");
     else $("#btn_checksms2").removeAttr("disabled").removeClass("disabled");
   },

   sendSMS2: function() {
      /*
      var phoneVal = $("#my_phone").val();
      if (phoneVal.length<10) return;
      $.post(EdoRework.backend, {action: "checkUserPhone", key: EdoRework.key, phone:phoneVal}, function(response) {
      */
      EdoRework.key2 = $("input#hashkey_edo").val();
      // console.log(EdoRework.key2);
      $.post(EdoRework.backend, {action: "checkUserPhone", key: EdoRework.key2}, function(response) {
        var spl = response.split("|");
        if (spl[0] === '1') {
            $("#stage2").removeClass('hideme'); //show();
            $("#stage1").addClass('hideme'); // hide();
        }
        else if (spl[0] === 'wait') { // пришла команда подождать NN сек.
            EdoRework.setWaitSms(parseInt(spl[1]));
        }
        else TimeAlert(response,3, 'msg_error');
      });
   },

   checkSms: function() {
      if(EdoRework.key=='') return;
      var smsVal = $("#my_sms").val();
      if (smsVal.length<4) return;
      $.post(EdoRework.backend, {action: "checkSMS", key: EdoRework.key, sms: smsVal}, function(response) {
        console.log("checkSms response :", response);
        var sp = response.split("|");
        if (sp[0] === '1') { // OK, get PDF url etc.
          // $("#agmt_data").html(sp[1]);
          $("#stage_reedo").removeClass('hideme'); //show();
          $("#stage2").addClass('hideme'); // hide();
          if(typeof(sp[1]) !='undefined')  // приехал ПЭП код
            $("#ses_code").html(sp[1]);
        }
        else TimeAlert(response,3, 'msg_error');
      });
   },
   checkSms2: function() { // проверили СМС этапа 2
      EdoRework.key2 = $("#hashkey_edo").val();
      if (!EdoRework.key2) {
          alert('Не задан ключ');
          return;
      }
      var smsVal = $("#my_sms2").val();
      if (smsVal.length<4) return;
      $.post(EdoRework.backend, {action: "checkSMS", key: EdoRework.key2, sms: smsVal}, function(response) {
        // console.log("checkSMS2 response :", response);
        var sp = response.split("|");
        if (sp[0] === '1') { // OK, get PDF url etc.
          // $("#agmt_dataedo").html(sp[1]);
          $("#stage_reedo").removeClass('hideme'); //show();
          $("#stage_pdn,#stage2,#stage_smsedo").addClass('hideme'); // hide();
        }
        else TimeAlert(response,3, 'msg_error');
      });
   },
   getPdf: function() {
       var pdfUrl = this.backend + "?action=getPdfPolicy&key="+this.key2;
       window.open(pdfUrl, "_blank");
   },
   setConfirmed: function() { // reedo-yes
      console.log("setConfirmed key:", EdoRework.key2, " key:", EdoRework.key);
      if(EdoRework.key=='') return;
      $("#stage_reedo").addClass("hideme");
      $("#waiting").removeClass("hideme");
      $.post(EdoRework.backend, {action: "setconfirmededo", key: EdoRework.key}, function(response) {
        $("#waiting").addClass("hideme");
        if (response == '1') {
            $("#stage_reedo").addClass("hideme");
            $("#final_result").html("Спасибо! Подтверждение согласия с изменениями данных получено.<br>Скоро Вы должны получить новую версию электронного полиса.");
        }
        else TimeAlert(response, 4, 'msg_error');
      });
   },
   setNotConfirmed: function() { // EDO-NO!
      console.log("setNotConfirmed key:", EdoRework.key2, " key:", EdoRework.key);
      if(EdoRework.key=='') return;
      dlgConfirm("Подтверждаете свое несогласие ?", EdoRework.doSetNotConfirmed, false);
   },
   doSetNotConfirmed: function() {
      var params = {action: "setnotconfirmedEdo", key: EdoRework.key}
      $("input.chconf2").each(function(idx) {
        var vName = $(this).prop("id");
        var vValue = $(this).prop("checked");
        params[vName] = (vValue ? "Y" : "N")
      });
      // console.log(params); alert("see params"); return;
      $("#waiting").removeClass("hideme");
      $("#stage_reedo").addClass("hideme");
      $.post(EdoRework.backend, params, function(response) {
        $("#waiting").addClass("hideme");
        // console.log("doSetNotConfirmedPdn response ", response);
        if (response == '1') {
            $("#stage_reedo").addClass("hideme");
            $("#final_result").html("Данные НЕ подтверждены. Пожалуйста, свяжитесь со своим Агентом/персональным Менеджером или дождитесь звонка от него. Спасибо!");
        }
        else if (response == 'STOPPED') {
            $("#stage_reedo").addClass("hideme");
            $("#final_result").html("Оформление договора отменено.<br>Пожалуйста, свяжитесь со своим Агентом/персональным Менеджером.");
        }
        else TimeAlert(response, 4, 'msg_error');
      });
   },

   chgCheck1 : function() {
     var chDone = true;
     $(".chconf").each(function() {
         if (!checked) chDone = false;
     });
     if (!chDone) {
         $("#btn_confirm").prop("disabled",true).addClass("disabled");
         $("#btn_noconfirm").prop("disabled",false).removeClass("disabled");;
     }
     else {
         $("#btn_confirm").prop("disabled",false).removeClass("disabled");
         $("#btn_noconfirm").prop("disabled",true).addClass("disabled");
     }
   },
   chgCheck2 : function() { // 2й этап (деклар)
     var chDone = true;
     $(".chconf2").each(function() {
         var checked = $(this).prop("checked");
         if (!checked) {
             chDone = false;
             // console.log("not checked item:", this);
         }
     });
     if (!chDone) {
         $("#ifnot").show();
         $("#btn_confirm").prop("disabled",true).addClass("disabled");
         $("#btn_noconfirm").prop("disabled",false).removeClass("disabled");
     }
     else {
         $("#ifnot").hide();
         $("#btn_confirm").prop("disabled",false).removeClass("disabled");
         $("#btn_noconfirm").prop("disabled",true).addClass("disabled");
     }
   },
   setWaitSms: function(wsec) {
     EdoRework.wSec = wsec;
     EdoRework.showWaitMinSecs();
     $("div.waiting").removeClass("hideme");
     $("#btn_sendsms").prop('disabled',true).addClass("disabled");
     EdoRework.timerId = setInterval(EdoRework.decSecond, 1000);
     return true;
   },
   showWaitMinSecs: function() {
     var rMin = Math.floor(EdoRework.wSec/60);
     var rSec = EdoRework.wSec - (rMin * 60);
     var ret = "";
     if (rMin > 0) ret += rMin+" мин.";
     ret += " "+rSec+" сек.";
     $(".resttime").html(ret);
     return ret;
   },
   decSecond: function() {
       --EdoRework.wSec;
       if (EdoRework.wSec <=0) {
           clearTimeout(EdoRework.timerId);
           $("div.waiting").addClass("hideme");
           $("#btn_sendsms").removeAttr("disabled").removeClass("disabled");
           return;
       }
       EdoRework.showWaitMinSecs();
   }
};
