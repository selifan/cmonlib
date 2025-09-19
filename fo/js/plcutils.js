/**
* package ALFO, common functions module : plcutils.js
* version 1.40.2, 2025-06-18
*/
plcUtils = {
  backend : false,
  plcBkend : './?p=plcutils',
  curModule: '',
  noUw: false,
  plcid : false,
  phBenefNo: false,
  myLevel:1,
  curstate: false,
  edostate: false,
  plcVersion: 0,
  randValue: Math.floor(Math.random() * 100000),
  wndChild : false,
  prolongid: false,
  maxRelDate: false,
  riskyProg: false,
  childGrid: false,
  packetMode : false,
  uwEnabled: true,
  packetGetInsured: 0,
  dissolute: function(module, id) {
    plcUtils.backend = './?plg='+module;
    plcUtils.plcid = id;
    $("body").floatWindow({
       html:'<div class="floatwnd ct" id="div_dissolute" style="width:360px;"><table class="table w-a p-5 mx-a">'
        + '<tr id="tr_diss_zdate" style="display:none"><td>Дата заявления:</td><td><input type="text" id="in_diss_zdate" name="in_diss_zdate" class="datefield" /></td></tr>'
        + '<tr><td>Дата расторжения:</td><td><input type="text" id="in_diss_date" name="in_diss_date" class="datefield" /></td></tr>'
        + '<tr><td>Причина расторжения: </td><td><select id="in_diss_reason" name="in_diss_reason" class="w200">'
        + '<option value="1">Неоплата договора</option>'
        + '<option value="2">Заявление клиента</option>'
        + '</select></td></tr>'
        + '<tr><td><label for="in_diss_redemp">С выкупной суммой</label></td><td><input type="checkbox" id="in_diss_redemp" value="1"></td></tr>'
        + '</table>'
        + '<br><div class="area-buttons"><input type="button" class="btn btn-primary" id="btn_dodiss" onclick="plcUtils.performDissolute()" value="Выполнить расторжение" /></div>'
      ,id: 'div_dissolute'
      ,position: { my: "center center", at: "center center", of: window.document}
      ,title: ('Регистрация расторжения договора')
      ,closeOnEscape: true
      ,init: function() {
          $('#in_diss_date,#in_diss_zdate').datepicker().change(DateRepair);
          $("#in_diss_reason").on("change", plcUtils.chgReason);
      }
    });
  }
  ,viewAgmtHistory: function (pref, id) {
    $('#div_agrhistory').remove();
    plcUtils.logPref = pref;
    plcUtils.policyid = id;
    $("body").floatWindow({
       html:'<div class="floatwnd" id="div_agrhistory" style="width:700px;"><table id="grid_agrhistory"></table><div id="nav_agrhistory" /></div>'
      ,id: 'div_agrhistory'
      ,position: { my: "right-20 top+"+ifaceTop, at: "right top", of: window.document ,collision: 'fit'}
      ,title: ('История действий с договором')
      ,zIndex: 2001
      ,init: function() {
          $('#grid_agrhistory').jqGrid({
           datatype: 'json'
          ,mtype:'POST'
          ,url: this.backend
          ,postData: { action: 'getagrhistory', 'prefix': plcUtils.logPref,'id':id }
          ,hidegrid:false
          ,colModel:[
             {name:'evdate', label: 'Дата', width:100, sortable:false,align:'center',resizable:false, editable:false}
            ,{name:'evtype',label: 'Тип', width:100,sortable:false,align:'left',resizable:true,editable:false}
            ,{name:'userid',label: 'Кто делал', width:160,sortable:false,align:'left',resizable:true,editable:false}
            ,{name:'evtext',label: 'Подробно', width:340,sortable:false,align:'left',resizable:true,editable:false}
           ]
          ,height: 330
    //      ,width:
          ,caption: ''
          ,x: true
          ,autowidth: true
          ,pginput: false
          ,rowNum: -1 // rows on one page
          ,imgpath: gridimgpath
          ,pager: '#nav_agrhistory'
          ,viewrecords: true
          ,multiselect: false
        }).navGrid("#nav_agrhistory",{edit:false,add:false,del:false, search:false}).navButtonAdd(
         "#nav_agrhistory", { caption:'', title:'Экспорт журнала в XLS', buttonicon:'ui-icon-document', onClickButton: function() {
            window.open("./?p=exportlog&ajax=1&&pref="+plcUtils.logPref+"&id="+plcUtils.policyid, "_blank");
         }
         });
      }
    });
    plcUtils.childGrid = 'grid_agrhistory';
    return false;
  }

  ,chgReason: function() {
      if ($(this).val() == "2") $("#tr_diss_zdate").show();
      else $("#tr_diss_zdate").hide();
  }
  ,performDissolute : function() {
      //window.location = policyModel.backend + '&action=dissolute&id='+policyModel.stmtid;
      var params = {action:'dissolute',id: plcUtils.plcid, diss_date: $('#in_diss_date').val(),
        diss_zdate: $('#in_diss_zdate').val(), diss_reason: $('#in_diss_reason').val(),
        diss_redemp: ($('#in_diss_redemp').is(':checked')? 1:0)
      };
      if (params.diss_reason == '2' && params.diss_zdate == '') {
          showMessage('Ошибка', 'Заведите дату заявления!', 'msg_error');
          return;
      }

      if (params.diss_date == '') {
          showMessage('Ошибка', 'Заведите дату расторжения!', 'msg_error');
          return;
      }
      $('#div_dissolute').floatWindowClose();
      SendServerRequest(plcUtils.backend, params);
  },

  setComment: function(module, id) {
    plcUtils.backend = './?plg='+module;
    plcUtils.plcid = id;
    plcUtils.curCmt = $("#policycomment").html();
    plcUtils.curCmtBtn = $("#btn_comment").html();
    $("#policycomment").html("<input type='text' id='new_comment' class='form-control w300' />");
    $("#btn_comment").html("<input type='button' class='btn btn-primary' onclick='plcUtils.doSetComment()' value='OK'/>"
     + "&nbsp;<input type='button' class='btn btn-primary' value='Отменить' onclick='plcUtils.restoreCommentHtml()'/>");
  }
  ,restoreCommentHtml: function() {
    $("#policycomment").html(plcUtils.curCmt);
    $("#btn_comment").html(plcUtils.curCmtBtn);
  }
  ,doSetComment: function() {
    var params = {action:'set_policycomment', id: plcUtils.plcid, comment: $('#new_comment').val()};
    plcUtils.restoreCommentHtml();
    // console.log(params); alert('TODO '+ params.comment);
    SendServerRequest(plcUtils.backend, params, true);
  }
  ,clickPacketMode: function() {
      if ($('#user_touw').is(':checked')) {
        if(plcUtils.packetMode) {
          var opts = {
              width : 400
             ,title: 'Пакетное оформление'
             ,text: 'Загрузить данные из первого полиса в пакете ?'
             ,closeOnEscape: true
          };
          dlgConfirm(opts, plcUtils.getPacketPolicyData, null);
        }
      }
  }
  ,resetPacket: function() {
     $.post('./?p=plcutils', {plcutilsaction: 'closepacket'}, function(data) {
         plcUtils.packetMode = false;
         if (data == '1') { $('#btn_resetpacket').hide(); }
         else alert(data);
     });
  }
  ,getPacketPolicyData : function() {
      if (typeof(policyModel)!='undefined') plcUtils.wrkModule = policyModel.module;
      else plcUtils.wrkModule = 'investprod';
      var params = { plgtarget: plcUtils.wrkModule, plcutilsaction: 'getPacketPolicyData', getinsured: plcUtils.packetGetInsured};
      SendServerRequest('./?p=plcutils', params, true, true);
  }
  ,showHelp: function(module, pageId) {
    $.post("./?p=plcutils&plcutilsaction=showHelp", {'module': module, 'pageid': pageId}, function(response) {
      var winW = Math.floor( $(window).width()*0.8);
      var winH = Math.floor( $(window).height()*0.8);
      var dlgOpts = {width:winW, resizable:true, zIndex: 500, height:winH
        ,buttons: [{text: "OK",click: function() {$( this ).dialog( "close" ).remove();}}]
        ,open: function(event,ui) {
          $('.ui-dialog').css('z-index',9002);
          $('.ui-widget-overlay').css('z-index',9001);
        }
      };

      dlgOpts.title = 'Помощь';
      dlgOpts.dialogClass = 'floatwnd';
      $('<div id="dlg_helppage" style="z-index:9900">'+response+'</div>').dialog(dlgOpts);
    });
  },
  chgPolicySigner: function() {
      $('#btn_updtsigner').show();
      $('#span_signer_saved').hide();
  },
  updatePolicySigner: function() {
    // TODO: save new signer
    var params = {'signerid': $('select#signer').val(), 'id': policyModel.stmtid};
    // alert(params.signerid); return;
    $.post("./?p=plcutils&plcutilsaction=updatePolicySigner", params, function(response) {
        if(response == '1') {
            $('#span_signer_saved').show();
            $('#btn_updtsigner').hide();
        }
        else alert(response);
    });
  }
  ,dlgMedDeclar: function(inModule,noUW) {
      var subText = '(При ответе "Нет" для выпуска договора потребуется прохождение андеррайтинга)';
      plcUtils.curModule = inModule;
      plcUtils.noUw = !!noUW;
      if(!plcUtils.uwEnabled) subText = '(При ответе "Нет" дальнейшее оформление полиса недоступно, по программе не предусмотрен индивидуальный андеррайтинг)';
      var opts = {
         title: 'Декларация Застрахованного',
         text: 'Застрахованный(ые) соответствует Декларации ?' + (noUW?'':('<br><br>'+subText) ),
         closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.medDeclarYes, plcUtils.medDeclarNo);
  }
  ,medDeclarYes : function() {
      // alert(plcUtils.curModule + ' YES!');
      plcUtils.doMedDeclar('Y');
  }
  ,medDeclarNo : function() {
      // alert(plcUtils.curModule + ' NO!');
      plcUtils.doMedDeclar('N');
  }
  ,doMedDeclar: function(yesno) {
    var params = {'module': plcUtils.curModule, 'declarval': yesno, 'nouw': plcUtils.noUw};
    if (plcUtils.curModule === 'investprod') params['id'] = investprod.policyid;
    else params['id'] = policyModel.stmtid;
    $.post("./?p=plcutils&plcutilsaction=setMedDeclar", params, function(response) {
        if(response == '1') {
            if (plcUtils.curModule === 'investprod') {
                console.log('invest refresh_view');
                investprod.refresh_view();
            }
            else {
                policyModel.refreshAgmtView();
            }
        }
        else showMessage('Ошибка', response, 'msg_error');
    });
  }
  ,openSpecCond: function(module) {
      plcUtils.curModule = module;
      $('#txt_speccond').remove();
      var htCode = "Введите текст особых условий<br><textarea id='txt_speccond' style='width:100%;height:130px; resize:none'></textarea><br><br>Занести текст в договор?";
      var opts = {
          width : Math.floor($(window).width() * 0.8)
         ,title: 'Ввод текста Особых условий по Договору'
         ,text: htCode
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doSetSpecCond);
      // TODO - отправить на сервер
  }
  ,doSetSpecCond: function() {
      var params = { 'spctext': $('#txt_speccond').val(), 'plcutilsaction':'doSetSpecCond',
        'module' : plcUtils.curModule, 'id':policyModel.stmtid };
      //showMessage('Result', '<pre>'+txt+'</pre>');
      asJs.sendRequest("./?p=plcutils&plcutilsaction=doSetSpecCond", params, true);
  }
  ,letterNewAccount: function(module, stmtid) {
      plcUtils.curModule = module;
      plcUtils.plcid = stmtid;
      var opts = {
          width : 500
         ,title: 'Отправка письма о заведении учетной записи'
         ,text: 'На корпоративную почту будет отправлено письмо с заявлением о создании учетки клиента.<br>Продолжить?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doLetterNewAccount);
  }
  ,doLetterNewAccount: function() {
    var params = {'module': plcUtils.curModule, 'id': plcUtils.plcid, 'actionnouw': plcUtils.noUw};
    if (plcUtils.curModule === 'investprod') params['id'] = investprod.policyid;
    else params['id'] = policyModel.stmtid;
    $.post("./?p=plcutils&plcutilsaction=sendLetterNewAccount", params, function(response) {
        if(response == '1') {
            showMessage('Отправка','Письмо отправлено');
        }
        else handleResponseData(response);
    });
  }
  ,startEdo : function(inModule) {
      if(!inModule && typeof(policyModel.module)!="undefined")
        inModule = policyModel.module;
      plcUtils.curModule = inModule;
      var opts = {
          width : 500
         ,title: 'Согласование с ПЭП'
         ,text: 'Стартовать процесс согласования с ПЭП ?<br>(клиенту будет отправлен запрос на согласие)'
      };
      dlgConfirm(opts, plcUtils.doStartEdo, null);
  }
  ,doStartEdo: function() {
      if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
      else plcUtils.plcid = investprod.policyid;
      // alert(plcUtils.plcid+ " "+plcUtils.curModule); return;
      var params = { 'action': 'startEdo', 'id': plcUtils.plcid };
      SendServerRequest("./?plg="+plcUtils.curModule, params, true, true);
  }
  ,startEdo2 : function(inModule) {
      if(!inModule && typeof(policyModel.module)!="undefined")
        inModule = policyModel.module;
      plcUtils.curModule = inModule;
      var opts = {
          width : 500
         ,title: 'Согласование с ПЭП'
         ,text: 'Стартовать процесс повторного согласования с ПЭП ?<br>(клиенту будет отправлен запрос подтверждения)'
      };
      dlgConfirm(opts, plcUtils.doStartEdo2, null);
  }
  ,doStartEdo2: function() {
      if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
      else plcUtils.plcid = investprod.policyid;
      // alert(plcUtils.plcid+ " "+plcUtils.curModule); return;
      var params = { 'action': 'startEdo2', 'id': plcUtils.plcid };
      SendServerRequest("./?plg="+plcUtils.curModule, params, true, true);
  }
  ,startNotEdo : function(inModule) {
      if(!inModule && typeof(policyModel.module)!="undefined")
        inModule = policyModel.module;
      plcUtils.curModule = inModule;
      var opts = {
          width : 500
         ,title: 'Согласование Без ПЭП'
         ,text: 'Стартовать стандартный сроцесс (без ПЭП) ?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doStartNotEdo, null);

  }
  ,doStartNotEdo: function() {
      if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
      else plcUtils.plcid = investprod.policyid;
      var params = { 'action': 'startNotEdo', 'id':plcUtils.plcid };
      SendServerRequest("./?plg="+plcUtils.curModule, params, true, true);
  }
  ,sendEdoLetter: function(inModule) {
      if(!inModule && typeof(policyModel.module)!="undefined")
        inModule = policyModel.module;
      plcUtils.curModule = inModule;
      var opts = {
          width : 400
         ,title: 'Отправка клиенту письма с полисом'
         ,text: 'Отправить клиенту письмо с оригиналом полиса ?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doSendEdoLetter, null);

  }
  ,doSendEdoLetter: function(inModule) {
      var id = (typeof(policyModel) != 'undefined') ? policyModel.stmtid : investprod.policyid;
      var params = { 'plcutilsaction': 'sendEdoLetterToClient', 'id':id, 'module': plcUtils.curModule};
      SendServerRequest("./?p=plcutils", params, true, true);
  }
  ,orderDelivery: function() {
      var opts = {
          width : 500
         ,title: 'Оформление доставки'
         ,text: 'Заказать доставку полиса ?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.performOderDelivery, null);
  }
  ,performOderDelivery: function(inModule) {
      if(!inModule) {
        if(typeof(policyModel)!="undefined") inModule = policyModel.module;
        else inModule = 'investprod';
      }
      if (!plcUtils.plcid) {
        if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
        else plcUtils.plcid = investprod.policyid;
      }
      var params = { 'plcutilsaction': 'orderDelivery', 'id':plcUtils.plcid, 'module': inModule};
      SendServerRequest("./?p=plcutils", params, true, true);
  }
  ,bindInvestAnketa: function(inModule,fam,imia,otch,birthdate,passer,pasno) {
    if(!inModule)
      inModule = plcUtils.getModule();

    $.post("./?p=plcutils&plcutilsaction=getDataForBindAnketa",
      { module: inModule, lastname:fam, firstname:imia, middlename: otch, birth:birthdate, pser:passer, pno:pasno }, function(data) {
      if (data === 'NODATA') {
          TimeAlert('Анкет, подходящих под введенные данные не найдено',3,"msg_error");
          return;
      }
      if (data === 'EMPTY_REQUEST') {
          TimeAlert('Заведите Фамилию и Имя для поиска!',3,"msg_error");
          return;
      }

      $("body").floatWindow({
         html: data
         ,id: 'div_bindanketa'
         ,position: { my: "left top", at: "left top", of: "#btn_bindanketa"}
         ,title: ('Назначение инвест-анкеты')
         ,closeOnEscape: true
         // ,init: function() { $('#in_letterdate').datepicker(); }
      });
    });
  }
  ,attachInvestanketa: function() {
    // привязка с формы просмотра
    var inModule = plcUtils.getModule();
    $.post("./?p=plcutils&plcutilsaction=getDataForBindAnketa",
      {module: inModule, policyid: plcUtils.getPolicyId() }, function(data) {
      if (data === 'NODATA') {
          TimeAlert('Анкет, подходящих под данные Страхователя, не найдено',3,"msg_error");
          return;
      }
      if (data === 'EMPTY_REQUEST') {
          TimeAlert('Заведите Фамилию и Имя для поиска!',3,"msg_error");
          return;
      }
      if (data === 'ASSIGNED') {
          TimeAlert('Договор уже имеет инвест-анкету!',3,"msg_error");
          if('investprod'===plcUtils.getModule()) investprod.refresh_view();
          else policyModel.refreshAgmtView();
          return;
      }

      $("body").floatWindow({
         html: data
         ,id: 'div_bindanketa'
         ,position: { my: "left top", at: "left top", of: "#btn_bindanketa"}
         ,title: ('Назначение инвест-анкеты')
         ,closeOnEscape: true
         // ,init: function() { $('#in_letterdate').datepicker(); }
      });
    });

  }
  ,applyInvestAnketa: function(id) {
    var sloc = window.location.toString();
    var isViewAgr = (sloc.indexOf('action=viewagr') > 0);
    if (isViewAgr) {
      $.post("./?p=plcutils&plcutilsaction=applyInvestAnketa",
        { "module": plcUtils.getModule(),"anketaid":id, policyid: plcUtils.getPolicyId() }, function(data) {
          var spl = data.split("\t");
          if (spl[0] === '1') {
            $("#div_bindanketa").remove();
            if(plcUtils.curModule === 'investprod') investprod.refreshAgmtView(); // refresh_view()
            else policyModel.refreshAgmtView();
          }
        });

    }
    else {
      $.post("./?p=plcutils&plcutilsaction=applyInvestAnketa",
        {"module": plcUtils.getModule(),"anketaid":id}, function(data) {
          var spl = data.split("\t");
          if (spl[0] === '1') {
            $("#div_bindanketa").remove();
            $("#instype_2").prop('disabled',true);
            $("#blk_bind_anketa").html('<b>Инвест-анкета успешно назначена</b>');
            handleResponseData(data);
          } else TimeAlert(data, 4, 'msg_error');
      });
    }
  }
  // ф-ции для под-статуса "доработка"
  ,setReworkState: function() {
    plcUtils.reworkState = 'on';
    var opts = {
      width : 400
     ,title: 'Режим доработки'
     ,text: 'Перевести договор в режим доработки ?'
     ,closeOnEscape: true
    };
    dlgConfirm(opts, plcUtils.doSetReworkState, null);
  }
  ,doSetReworkState:function() {
      if(typeof(policyModel)!="undefined") inModule = policyModel.module;
      else inModule = 'investprod';
      if (!plcUtils.plcid) {
        if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
        else plcUtils.plcid = investprod.policyid;
      }
      var params = { 'action': 'setReworkState', 'id':plcUtils.plcid, 'module': inModule, 'state':plcUtils.reworkState};
      SendServerRequest("./?p=rework", params, true, true);
  }
  ,setReworkDone:function() {
    plcUtils.reworkState = 'done';
    var opts = {
      width : 400
     ,title: 'Уведомление о выполнении доработки'
     ,text: 'Доработка выполнена ?'
     ,closeOnEscape: true
    };
    dlgConfirm(opts, plcUtils.doSetReworkState, null);
  }

  ,clearReworkState:function() {
    var opts = {
      width : 400
     ,title: 'Выключение режима доработки'
     ,text: 'Выключить режим доработки ?'
     ,closeOnEscape: true
    };
    plcUtils.reworkState = 'off';
    dlgConfirm(opts, plcUtils.doSetReworkState, null);
  }
  ,getModule: function() {
    if (plcUtils.curModule == '') {
      if(typeof(policyModel)!="undefined") plcUtils.curModule = policyModel.module;
      else plcUtils.curModule = 'investprod';
    }
    return plcUtils.curModule;
  }
  ,getPolicyId: function() {
    if (!plcUtils.plcid) {
      if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
      else plcUtils.plcid = investprod.policyid;
    }
    return plcUtils.plcid;
  }
  /*
  ,doClearReworkState:function() {
    if(typeof(policyModel)!="undefined") inModule = policyModel.module;
    else inModule = 'investprod';
    if (!plcUtils.plcid) {
      if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
      else plcUtils.plcid = investprod.policyid;
    }
    var params = { 'action': 'setReworkState', 'id':plcUtils.plcid, 'module': inModule, 'state':'off'};
    SendServerRequest("./?p=rework", params, true, true);
  }
  */
  ,openReworkLetter : function() {
    if(typeof(policyModel)!="undefined") inModule = policyModel.module;
    else inModule = 'investprod';
    if (!plcUtils.plcid) {
      if (typeof(policyModel)!="undefined") plcUtils.plcid = policyModel.stmtid;
      else plcUtils.plcid = investprod.policyid;
    }
    var params = { 'action': 'openEmailLetterLink', 'id':plcUtils.plcid, 'module': inModule};
    $.post("./?p=rework", params, function(data) {
        var spl = data.split("|");
        if (spl[0] ==='1') window.open(encodeURI(spl[1]));
        else TimeAlert(data,3,'msg_error');
    });

    // var url = encodeURI("mailto:ddddd@aaa.ru&cc=ccopy@allianz100500.ru&subject=Доработка договора");
    // window.open(url);
  }
  ,unblockInvanketa: function() {
     var opts = {
       width : 400
      ,title: 'Разблокировка инвест-анкеты'
      ,text: 'Разблокировать анкету ?'
      ,closeOnEscape: true
     };
     dlgConfirm(opts, plcUtils.doUnblockInvanketa, null);
  }
  ,doUnblockInvanketa: function() {
    var module = plcUtils.getModule();
    var plcid = plcUtils.getPolicyId();

    $.post("./?p=investanketa", {'anketaaction': 'unblockAnketa','module':module, 'policyid':plcid}, function(data) {
        if (data === "1") policyModel.refreshAgmtView();
        else showMessage("Ошибка", data,"msg_error");
    });
  }
  ,createInvAnketa: function(anketaId) {
    var module = plcUtils.getModule();
    var plcid = plcUtils.getPolicyId();
    if(plcid == '') { // новый полис, проверка заполнения полей для анкеты
      var someEmpty = '';
      if($("#insrfam").val()=='') someEmpty += "<br>Фамилия";
      if($("#insrimia").val()=='') someEmpty += "<br>Имя";
      if($("#insrbirth").val()=='') someEmpty += "<br>Дата рождения";
      if(policyModel.isRussian['insr']) {
        if($("#insrdocser").val()=='') someEmpty += "<br>Паспорт-Серия";
        if($("#insrdocno").val()=='') someEmpty += "<br>Паспорт-Номер";
      }
      else {
        if($("#insrinopass").val()=='') someEmpty += "<br>Номер Иностр.паспорта";
      }
      if($("#insrphone").val()=='') someEmpty += "<br>телефон";
      if($("#insremail").val()=='') someEmpty += "<br>Email";
      if(someEmpty) {
        TimeAlert("Сначала заполните нужные поля:"+ someEmpty, 3, 'msg_error');
        return;
      }
    }

    var ankUrl = "./?p=addinvanketa";
    if (plcid) ankUrl += "&policyid="+plcid+"&module="+module;
    if(anketaId > 0) ankUrl += "&anketaid="+anketaId;
    if(!plcUtils.wndChild || plcUtils.wndChild.closed) {
        var ankUrl = "./?p=addinvanketa";
        if (plcid) ankUrl += "&policyid="+plcid+"&module="+module;
        if(anketaId > 0) ankUrl += "&anketaid="+anketaId;
        plcUtils.wndChild = window.open(ankUrl, "_invanketa"+plcUtils.randValue);
        plcUtils.wndChild.onload = plcUtils.passAnketaParams;
    }
    else plcUtils.passAnketaParams();

  }
  ,passAnketaParams: function() {
    if (plcUtils.wndChild.document) {
      var flVal, fvPhone;
      var module = plcUtils.getModule();
          if (module === 'investprod') {
          var flVal = $("input[name=f_individual_lastname]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_lastname").value=flVal;
          flVal = $("input[name=f_individual_firstname]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_firstname").value=flVal;
          flVal = $("input[name=f_individual_middlename]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_middlename").value=flVal;
          flVal = $("input[name=f_individual_birthdate]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_birth").value=flVal;
          flVal = $("input[name=f_individual_passportseries]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_docser").value=flVal;
          flVal = $("input[name=f_individual_passport]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_docno").value=flVal;
          flVal = $("input[name=f_individual_email]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_email").value=flVal;
          flVal = $("input[name=f_individual_phone]").val();
          if (flVal) {
            // TODO: maek (999)-999-9999 form
              plcUtils.wndChild.document.getElementById("ank_phone").value=flVal;
          }
          plcUtils.wndChild.investAnketa.checkData();
      }
      else {
          flVal = $("input[name=insrfam]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_lastname").value=flVal;
          flVal = $("input[name=insrimia]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_firstname").value=flVal;
          flVal = $("input[name=insrotch]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_middlename").value=flVal;
          flVal = $("input[name=insrbirth]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_birth").value=flVal;

          if(policyModel.isRussian['insr']) {

            flVal = $("input[name=insrdocno]").val();
            if (flVal) plcUtils.wndChild.document.getElementById("ank_docno").value=flVal;
            flVal = $("input[name=insrdocser]").val();
            if (flVal) plcUtils.wndChild.document.getElementById("ank_docser").value=flVal;
          }
          else {
            flVal = $("#insrinopass").val();
            if (flVal) plcUtils.wndChild.document.getElementById("ank_docno").value=flVal;
          }
          flVal = $("input[name=insremail]").val();
          if (flVal) plcUtils.wndChild.document.getElementById("ank_email").value=flVal;
          flVal = $("input[name=insrphone").val();
          if (flVal!=='') plcUtils.wndChild.document.getElementById("ank_phone").value= '('+flVal+')'+fvPhone;
          plcUtils.wndChild.investAnketa.checkData();
      }
    }
  }

  ,InvanketaLetter : function() {
     var opts = {
       width : 400
      ,title: 'Отправка письма'
      ,text: 'Отправить клиенту повторное письмо ?<br>Предыдущая отправленная ссылка станет недоступна!'
      ,closeOnEscape: true
     };
     dlgConfirm(opts, plcUtils.doInvanketaLetter, null);
  }
  ,doInvanketaLetter : function() {
    var module = plcUtils.getModule();
    var plcid = plcUtils.getPolicyId();
    $.post("./?p=investanketa", {'anketaaction': 'resendClientLetter','module':module, 'policyid':plcid}, function(data) {
        if (data === "1") showMessage('Письмо отправлено','Письмо со ссылкой успешно отправлено');
        else showMessage("Ошибка", data,"msg_error");
    });
  }
  ,resetPlc: function() {
     var opts = {
     title: 'Сброс карточки договора',
     text: 'Подтверждаете возврат в статус “Проект”?<br>(загруженные файлы будут удалены)',
     closeOnEscape: true
    };
    if(this.myLevel>=4) opts.text += "<br><br>Введите комментарий для агента/менеджера о требуемых корректировках:<br>"+
      '<input type="text" id="reset_cmt" class="form-control w100prc">';
    dlgConfirm(opts, plcUtils.doResetPlc, null);
  }
  ,doResetPlc: function() {
     var params = { module: plcUtils.getModule(), plcutilsaction: 'resetPlc', 'id': plcUtils.getPolicyId()};
     if(plcUtils.myLevel>=4) params['reset_cmt'] = $("#reset_cmt").val();
     asJs.sendRequest('./?p=plcutils', params, true);
  }
  ,refreshScanList: function() {
    if(typeof($("#grid_agrscans")[0]) !='undefined') $("#grid_agrscans").trigger("reloadGrid");
  }
  ,releasePolicy: function() {
     $("div.floatwnd").remove();
     var relDate = $("input#date_release").val();
     var opts = {
     title: 'Выпуск полиса',
     text: 'Внимание! Убедитесь, что все данные проверены.<br>Исправить их после выпуска оригинала Договора Вы уже не сможете!<br>'+
        'Дата выпуска Договора: '+(relDate ? relDate : jsToday)
        +( (plcUtils.riskyProg && !plcUtils.prolongid) ? '<br>Введите дату начала страхования: <input type="date" id="dt_start" class="ibox w100">': '' )
        +'<br>Подтверждаете выпуск полиса?', // Зафиксировать дату выпуска
     closeOnEscape: true
    };
    dlgConfirm(opts, plcUtils.doReleasePolicy, null);
  }
  ,doReleasePolicy: function() {
     var params = { plcutilsaction: 'releasePolicy', module: plcUtils.getModule(), 'id': plcUtils.getPolicyId()};
     if(plcUtils.riskyProg && !plcUtils.prolongid) {
         var dtstart = $("#dt_start").val();
         if(dtstart == '') {
             showMessage('Ошибка','Надо завести дату начала действия!','msg_error');
             return;
         }
         params['dt_start'] = dtstart;
     }
     asJs.sendRequest(plcUtils.plcBkend, params, true);
  }
  ,confirmEqPay: function() {
      var opts = {
          width : 480
         ,title: 'Отправка клиенту ссылки на онлайн-оплаты'
         ,text: 'Создать заявку на оплату и отправить ссылку клиенту ?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doSendEqPay, null);

  }
  ,doSendEqPay: function() {
     var params = { plcutilsaction: 'sendEqPay',module: plcUtils.getModule(), id: plcUtils.getPolicyId() };
     asJs.sendRequest(plcUtils.plcBkend, params);
  }
  ,revokeEqPay: function() {
      var opts = {
          width : 480
         ,title: 'Отзыв онлайн-оплаты'
         ,text: 'Отозвать ссылку на онлайн-оплату ?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doRevokeEqPay, null);
  }
  ,doRevokeEqPay: function() {
     var params = { plcutilsaction: 'revokeEqPay',module: plcUtils.getModule(), id: plcUtils.getPolicyId() };
     asJs.sendRequest(plcUtils.plcBkend, params);
  }
  ,confirmRevokeEqPay: function() { // переспросить если клиент уже создал ордер в банке и платит(платил)
      var opts = {
          width : 480
         ,title: 'Отзыв онлайн-оплаты'
         ,text: 'Клиент уже начать оплачивать полис. Уверены, что хотите отозвать оплату ?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.doConfirmRevokeEqPay, null);
  }

  ,doConfirmRevokeEqPay: function() {
     var params = { plcutilsaction: 'revokeEqPay',module: plcUtils.getModule(), id: plcUtils.getPolicyId(), 'revoke_confirm':1 };
     asJs.sendRequest(plcUtils.plcBkend, params);

  }
  ,checkEqPay: function() {
     var params = { plcutilsaction:'checkEqPay', module: plcUtils.getModule(), id: plcUtils.getPolicyId() };
     asJs.sendRequest(plcUtils.plcBkend, params, true);
  }

  ,setStateActive: function() {
      var opts = {
          width : 400
         ,title: 'Перевод договора в Действующий'
         ,text: 'Подтверждаете перевод в Действующий<br>и отправку на Архивное хранение?'
         ,closeOnEscape: true
      };
      dlgConfirm(opts, plcUtils.performSetStateActive, null);
  }
  ,performSetStateActive : function() {
     var params = { module: plcUtils.getModule(), plcutilsaction: 'setStateActive', 'id': plcUtils.getPolicyId()};
     asJs.sendRequest(plcUtils.plcBkend, params,true);
  }
  ,chgChildDelegate: function() {
    var selDeleg = $("#child_delegate").val();
    if(selDeleg == 'N') $("#tr_child_delegate").removeClass("hideme");
    else $("#tr_child_delegate").addClass("hideme");
  }
  ,getInvestAnketa: function() {
    var module = plcUtils.getModule();
    var plcid = plcUtils.getPolicyId();
    var ankUrl = "./?p=plcutils&ajax=1&plcutilsaction=getinvestAnketa&module="+module+"&id="+plcid;
    window.open(ankUrl,"_blank");
  }
  ,viewPersonData: function(what, persno) {
    var module = plcUtils.getModule();
    var plcid = plcUtils.getPolicyId();
    var viewUrl = "./?p=plcutils&ajax=1&plcutilsaction=viewPersonData";
    persno = persno || '0';
    asJs.sendRequest(viewUrl, {"module":module, "id":plcid, "what":what, "persno":persno},true);
  },
  changeDee: function(obj) {
    var curDee = $("input[name=deesposob_limited]:checked").val();
    // console.log("change deesposob : ", curDee);
    if(curDee==='Y') $("#tr_dee_reason").removeClass("hideme");
    else {
        $("#tr_dee_reason").addClass("hideme");
        $("input[name=deesposob_limited_reason]").val("");
    }
  },
  copyPholderToBenef: function(bno) {
    var eqInsured, varName, varLast, varSurname, fullName, varBirth, benDocType;
    var eqIns = $("#equalinsured");
    // var ins1 = $("#instype_1");
    // var ins2 = $("#instype_2");
    var insurerType = $('input[name=insurer_type]:checked').val();
    if(typeof(insurerType) == 'undefined') {
      insurerType = $('input[name=insurer_type]').val();
      // console.log("no chkbox, seek input:" ,insurerType);
    }
    if(typeof(insurerType) == 'undefined') insurerType = '1';

    var eqInsType = eqIns.prop('type');
    if(eqInsType === 'hidden') eqInsured = eqIns.val();
    else eqInsured = eqIns.prop("checked");

    if(eqInsured === true || eqInsured==1) {
      showMessage('Ошибка', 'Страхователь является Застрахованным лицом!','msg_error');
      return;
    }
    if(plcUtils.phBenefNo>0 && plcUtils.phBenefNo != bno) {
      showMessage('Ошибка', 'Вы уже назначили Страхователя в другом ВП!','msg_error');
      return;
    }
    /* if(ins2.prop('type') !=='undefined' && $("#instype_2").prop("checked")) { */
    if(insurerType == '2') {
      // alert("Страхователь - ЮЛ");
      if(bno!=1) {
        showMessage('Ошибка', 'Выгодоприобретатель ЮЛ допустим только первым в списке!','msg_error');
        return;
      }
      varName = $("#insrurname").val();
      if(varName == '') {
        showMessage('Ошибка', 'Сначала заполните все данные Страхователя ЮЛ!','msg_error');
        return;
      }
      $("#benefbtype1").prop("checked", true).trigger("change");
      $("#beneffullname"+bno).val(varName);
      $("#benefdoctype"+bno).val('99'); // прочие
      $("#benefdocser"+bno).val($("#insrdocser").val());
      $("#benefdocno"+bno).val($("#insrdocno").val());
      $("#benefinn"+bno).val($("#insrurinn").val());
      $("#benefkpp"+bno).val($("#insrkpp").val());
      $("#benefogrn"+bno).val($("#insrogrn").val());

      plcUtils.phBenefNo = bno;
    }
    else { // FL
      varLast = $("#insrfam").val();
      varName = $("#insrimia").val();
      varSurname = $("#insrotch").val();
      varBirth = $("#insrbirth").val();
      if(varLast == '' || varName == '' || varBirth=='') {
        showMessage('Ошибка', 'Сначала заполните данные Страхователя ФЛ!','msg_error');
        return;
      }
      if(bno==1) $("#benefbtype1").prop("checked", false).trigger("change");
      fullName = varLast + ' ' + varName + (varSurname ? (' '+varSurname) : '');
      $("#beneffullname"+bno).val(fullName);
      $("#benefbirth"+bno).val(varBirth);
      $("#beneftax_rezident"+bno).val($("#tax_rezident").val());
      $("#benefsex"+bno).val($("#insrsex").val());

      benDocType = policyModel.isRussian['insr'] ? '1' : '6';
      $("#benefdoctype"+bno).val(benDocType);
      $("#benefrez_country"+bno).val($("#insrrez_country").val());
      $("#benefbirth_country"+bno).val($("#insrbirth_country").val());

      plcUtils.phBenefNo = bno;
    }

    if(bno==1) $("#benefpercent"+bno).val("100");

    $("#benefdocser"+bno).val($("#insrdocser").val());
    $("#benefdocno"+bno).val($("#insrdocno").val());
    $("#benefdocpodr"+bno).val($("#insrdocpodr").val());
    $("#benefdocissued"+bno).val($("#insrdocissued").val());
    $("#benefdocdate"+bno).val($("#insrdocdate").val());
    $("#benefphone"+bno).val($("#insrphone").val());
    $("#benefphone2"+bno).val($("#insrphone2").val());
    //  console.log($("#insrphone").val());
    //  console.log($("#insrphone2").val());

    $("#benefadr_countryid"+bno).val($("#insradr_countryid").val()).trigger("change");
    $("#benefadr_zip"+bno).val($("#insradr_zip").val());
    $("#benefadr_country"+bno).val($("#insradr_country").val()).trigger("change");
    $("#benefadr_region"+bno).val($("#insradr_region").val());
    $("#benefadr_city"+bno).val($("#insradr_city").val());
    $("#benefadr_street"+bno).val($("#insradr_street").val());
    $("#benefadr_house"+bno).val($("#insradr_house").val());
    $("#benefadr_corp"+bno).val($("#insradr_corp").val());
    $("#benefadr_build"+bno).val($("#insradr_build").val());
    $("#benefadr_flat"+bno).val($("#insradr_flat").val());

    var adrSame = $("#insrsameaddr").prop("checked");
    var benSame = $("#benefsameaddr"+bno).prop("checked");
    if(adrSame != benSame) $("#benefsameaddr"+bno).trigger("click");

    if(!adrSame) {
      $("#beneffadr_countryid"+bno).val($("#insrfadr_countryid").val()).trigger("change");
      $("#beneffadr_zip"+bno).val($("#insrfadr_zip").val());
      $("#beneffadr_country"+bno).val($("#insrfadr_country").val()).trigger("change");
      $("#beneffadr_region"+bno).val($("#insrfadr_region").val());
      $("#beneffadr_city"+bno).val($("#insrfadr_city").val());
      $("#beneffadr_street"+bno).val($("#insrfadr_street").val());
      $("#beneffadr_house"+bno).val($("#insrfadr_house").val());
      $("#beneffadr_corp"+bno).val($("#insrfadr_corp").val());
      $("#beneffadr_build"+bno).val($("#insrfadr_build").val());
      $("#beneffadr_flat"+bno).val($("#insrfadr_flat").val());
    }

    $("input.bt_phbenef").each(function(idx) {
      if((idx+1)!=bno) $(this).hide();
    });
  }
  ,askPayState: function() {
    var opts = {
      width : 400,title: 'Статус оплаты договора',
      text: 'Клиент оплатил договор ?<br>При ответе <b>Да</b>, введите здесь дату оплаты: <input type="date" id="set_datepay" class="ibox w80">',
      closeOnEscape: true
    };
    dlgConfirm(opts, plcUtils.payStateYes, plcUtils.payStateNo);
  }
  ,payStateYes : function() {
      var dtpay = $("#set_datepay").val();
      // alert("TODO YES for [" + dtpay + ']');
      var params = {"action":'setpayed',"datepay": dtpay,
        "forexpired":1, id:policyModel.stmtid
      };
      asJs.sendRequest(policyModel.backend, params);

  }
  /**
  * AJAX команда от агента - Клиент ответ "не оплатил полис" с формы "статус оплаты" при просрочке МДВ
  */
  ,payStateNo : function() {
    var params = {"plcutilsaction": "setNoPayment", "module":plcUtils.getModule(), "id":plcUtils.getPolicyId()};
    asJs.sendRequest(plcUtils.plcBkend, params);
  },
  getClientEdoAnswers: function(stageName) {
    var params = {"plcutilsaction": "getClientEdoAnswers", "module":plcUtils.getModule(), "id":plcUtils.getPolicyId(), "stage":stageName};
    asJs.sendRequest(plcUtils.plcBkend, params);
  },
  viewAllUwReasons: function() {
    var params = {"plcutilsaction": "viewAllUwReasons", "module":plcUtils.getModule(), "id":plcUtils.getPolicyId()};
    asJs.sendRequest(plcUtils.plcBkend, params);
  }
};