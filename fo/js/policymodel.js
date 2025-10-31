/**
* @package ALFO
* @name js/policymodel.js
* @version modified 2025-10-30
**/

plcStates = {
  'project': 'Проект',
  'stmt_formed': 'Заявление оформлено',
  'stmt_cancel': 'Заявление отменено',
  'cancel': 'Отказ от страхования',
  'annul': 'Аннулирован',
  'touw': 'На андеррайтинге',
  'uwagreed': 'Согласовано андеррайтером',
  'uwreqdata': 'Требуется довнесение данных',
  'uwdenied': 'НЕ Согласовано андеррайтером',
  'formed': 'Договор оформлен',
  'clearpayed': 'сбросить отметку об оплате',
};
if (typeof (superOper)=='undefined') superOper = false;
policyModel = {
  module : 'no'
  ,boxProduct: false
  ,rusCodes : []
  ,saElemId : false
  ,saBuffer : false
  ,RUSSIAID : '114'
  ,pageRandom: Math.floor(Math.random() * 999999)
  ,childWnd: false
  ,alwaisAdult: false
  ,stmtid:''
  ,plcEditable: false
  ,uploader: []
  ,newScanType : ''
  ,uloadMaxSize : 16384 // KB, 16384=16MB
  ,prolongid : false
  ,maxRelDate: false
  ,prolongCodes: false
  ,programid : ''
  ,loaded: 0
  ,isRussian: { "insr":true, "insd":true }
  ,riskyProg: false
  ,backend: './'
  ,confirmStmtFormedText: 'Подтверждаете установку статуса заявления "Оформлено" ?'
  ,newstate:''
  ,shiftKey : false
  ,altKey: false
  ,ctrlKey: false
  ,shifted : false
  ,guessPerson : false
  ,ifaceWidth: 300
  ,freezeInsured : true // insured params came from calc so can't be changed in edit
  ,handler : {} // plugin can add own handler
  ,relations: ['дочь','сын','отец','мать','внук','внучка','дедушка','бабушка','дядя','тётя','племянник','племянница','муж','жена','брат','сестра','иное']
  ,recalculator : false
  ,childToggle : null
  ,turnMode: ''
  ,bindClient: false
  ,copyFrom: false
  ,pay_data : { currency: 'RUR', pay_rur: 0 }
  ,init: function(module, plcid) {
     policyModel.module = module;
     policyModel.backend = './?plg='+module;
  //       console.log('module set to '+module);
     if(!!plcid) {
        policyModel.stmtid = plcid;
        policyModel.loadScanGrid(plcid);
        // $('#btn_fireupload').show();
        // alert(policyModel.module + ' = '+policyModel.backend + ' id ='+policyModel.stmtid);
     }
     else policyModel.loaded = true;

  }
  ,handleUploadResult: function(file, response) {
     $('#scanmenu_data').hide();
     var spt = response.split("\t");
     if (spt[0]!=='1') {
           showMessage('Ошибка при загрузке файла',
              'Загрузка файла не выполнена.<br>' + response
              ,'msg_error');
           return;
     }
     handleResponseData(response);
  }
  ,loadStmt: function(rid, prolong, calcid) {
     plcid = rid;
     policyModel.stmtid = rid;
     var params = { action:'loadstmt', id: rid};
     policyModel.loaded = false;
     if (prolong) params.prolong = '1';
     if (calcid) params.calcid = calcid;
     $.post(this.backend, params, function(data){
        handleResponseData(data, true);
        setTimeout(policyModel.setLoaded,200);
     });
  }
  ,setLoaded: function() {
     policyModel.loaded = 1;
     // console.log("set loaded=1, call recalc if exist..");
     if ($.isFunction(policyModel.recalculator)) policyModel.recalculator();
  }
  ,loadFromAnketa: function(ankid) {
     asJs.sendRequest(this.backend, {action:"loadFromAnketa", anketaid: ankid}, true);
  }
  ,openRecalc: function() {
     window.location = (policyModel.backend+"&action=agrcalc&id="+policyModel.stmtid);
  }
  ,openEdit: function() {
     window.location = (policyModel.backend+"&action=stmt&id="+policyModel.stmtid);
  }
  ,agredit: function() {
     window.location = (policyModel.backend+"&action=agredit&id="+policyModel.stmtid);
  }
  ,saveAgmt : function() {
  $('#btn_agrsave').prop('disabled',true);
  var disFld = [];
  $("input,select").each(function() {
        if($(this).is(":disabled")) {
        // console.log(this.id, " is disabled");
        disFld.push(this.id);
        $(this).removeAttr('disabled');
        }
    });
    var params = $('#fm_agr_'+this.module).serialize();
    // console.log("params to send:", params);
    for(var ii in disFld) {
      $("#"+disFld[ii]).attr("disabled", true);
      // console.log(disFld[ii], "return to disabled");
    }
    // alert(params); return;
    // console.log('saveAgmt'); return; // stop
    ShowSpinner(true,false,true, 'Подождите...');
    asJs.sendRequest(this.backend+'&action=saveagmt', params, true, false,policyModel.enableBtnSave);
  },
  enableBtnSave: function() {
     ShowSpinner(false);
     $('#btn_agrsave').prop('disabled',false);
  }
  ,copyPersonData: function(from,to) { // копирую ФИО и прочие данные физ-лица (из страхователя в застрахованного и т.д.)
     var flist = ['fam','imia','otch','birth','sex','inn','rezident_rf','rez_country','docser','docno','docdate','docpodr','docissued',
        'married','inopass','migcard_ser','migcard_no','docfrom','doctill'];
     for(fn in flist) {
           var cobj = $('#'+from+flist[fn]);
           var toobj = $('#'+to+flist[fn]);
           var ctype = cobj.prop('type');
           var cval = cobj.val();
           if(ctype === 'checkbox') {
              var chk = cobj.prop('checked');
              if(chk) { toobj.prop('checked', 'checked'); }
              else { toobj.prop('checked',false); }
              toobj.trigger('onclick');
           }
           else toobj.val( cval );
     }
  }
  ,chgFizUr: function() {
     var fu = $('input[name=insurer_type]:checked').val();
     var equalinsured = $('#equalinsured');
     if(fu=="2") {
        $('.block_insr_ur,#insrrow_3').show();
        $('.block_insr_fiz,#insrrow_inopass,#insrrow_migcard,#blk_bind_anketa,#send_info').hide();
        if(equalinsured.is(':checked')) {
           equalinsured.removeAttr('checked');
           this.chgEqualInsured();
        }
        equalinsured.prop('disabled',true);
        // if($('#insdfam').val() === '' && $('#insrfam').val() != '') { this.copyPersonData('insr','insd'); }

        $('#predstype1').prop({checked:false,disabled:true});
     }
     else {
        $('.block_insr_ur').hide();
        $('.block_insr_fiz,#blk_bind_anketa,#send_info').show();
        equalinsured.prop('disabled',false);
        $('#predstype1').prop({disabled:false});
     }
     if ($.isFunction(policyModel.recalculator)) policyModel.recalculator();
  }
  ,chgRezident: function(obj, prefix) {
     var vref = $('#'+prefix+'rezident_country');
     if(obj.checked) {
        vref.hide();
     }
     else vref.show();
     if ($.isFunction(policyModel.recalculator)) policyModel.recalculator();
  }
  ,chgRezCountry: function(prefix) {
     var countryId = parseInt($('#'+prefix+'rez_country').val());
     var isRus = (policyModel.rusCodes.indexOf(countryId)>=0);
     policyModel.isRussian[prefix] = isRus;
     if (isRus || countryId=='') {
        $('#'+prefix+'row_inopass,#'+prefix+'row_migcard').hide();
        $('#'+prefix+'row_3').show();
        $('#'+prefix+'docissued').prop('required', true);
     }
     else {
        $('#'+prefix+'row_3').hide();
        // $('#'+prefix+'rowissued').hide();
        $('#'+prefix+'docissued').prop('required', false);
        // $('input', '#'+prefix+'rowissued').removeClass('iboxm').addClass('ibox');
        $('#'+prefix+'row_inopass,#'+prefix+'row_migcard').show();
     }
  }
  ,chgSurname: function(obj) {
     if (!policyModel.loaded) return;
     var surval = $(obj).val();
     var sfname = obj.name;
     var pref = sfname.substr(0,sfname.length-4);
     var disab = $('#'+pref+'sex').prop('disabled');
     if(surval.length>2 && !disab) {
        var newsex = surval.substring(surval.length-2).toLowerCase()==='на'?'F':'M';
        $('#'+pref+'sex').val(newsex);
     }
  }
  ,chgEqualInsured: function() {
     var state = policyModel.getFieldValue('equalinsured');
     var prefSubj = (state ? 'insr' : 'insd');
     // console.log("chgEqualInsured, loaded: ", policyModel.loaded);
     // console.log('state / subj: ', state, prefSubj);
     if(state == 1 || state == true) { // включили "страх=застрах"
        $('#div_info_insured').hide(); //slideUp(100);
        if (policyModel.freezeInsured) {
              var dBirth = $('input[name=insdbirth]').val();
              // console.log('from insured:' +dBirth);
              $('#insrsex').val($('#insdsex').val()).prop('disabled',true);
              $('#insrbirth').val(dBirth).prop('readonly',true).datepicker( "option", "disabled", true );
        }
     }
     else { // выключили
        $('#div_info_insured').show();
        if (policyModel.freezeInsured) {
              //$('#insrfam,#insrimia,#insrotch').val('').prop('readonly',false);
              var cursex = $('#insrsex').val();
              $('#insrsex').val('').removeAttr('disabled');
              $('#insdsex').val(cursex).attr('disabled',true);

              var dBirth = $('input[name=insrbirth]').val();
              // console.log('from insr:' +dBirth);
              $('#insdbirth').val(dBirth).prop('disabled',true);

              $('#insrbirth').val('').prop('readonly',false).datepicker( "option", "disabled", false );
        }
     }
     if(policyModel.bindClient) {
        // $("#fm_agr_"+policyModel.module)[0].reset();
        if(policyModel.loaded) {
        var cleanBlock = (state ? 'insd' : 'insr');
        policyModel.clearBlock(cleanBlock);
        policyModel.loadClientData(prefSubj, policyModel.bindClient);
        }
        if ($.isFunction(policyModel.recalculator)) setTimeout(policyModel.recalculator, 300);
     }
     else {
        if ($.isFunction(policyModel.recalculator)) policyModel.recalculator();
     }
  }
  ,chgSameAddr: function(cobj,prefix, num) {
     // console.log(cobj);  console.log('prefix:', prefix);
     var fadrClass = prefix+'showfact' + ((!!num) ? num : '');
     if(cobj.checked) $('.'+fadrClass).slideUp(100);
     else $('.'+fadrClass).slideDown(100);
  }

  ,CopyAddress: function(pr, num) {
     var vnames = ['country','region','zip','city','street','house','corp','build','flat'];
     var vpfix = ((!!num) ? num : '');
     for(kn in vnames) {
        $('#'+pr+'fadr_'+vnames[kn]+vpfix).val( $('#'+pr+'adr_'+vnames[kn]+vpfix).val());
     }
  }
  ,copyAddressInsd: function(chid) {
     var vnames = ['countryid','country','region','zip','city','naspunkt','street','house','corp','build','flat'];
     var idPref = (chid) ? '#insd'+chid+'adr_' : '#insdadr_';
     for(kn in vnames) {
        $(idPref+vnames[kn]).val( $('#insradr_'+vnames[kn]).val()).trigger('change');
     }
  }
  ,copyAddressChild: function(chid) {
     var vnames = ['countryid','country','region','zip','city','naspunkt','street','house','corp','build','flat'];
     var idPref = (chid) ? '#child'+chid+'adr_' : '#childadr_';
     for(kn in vnames) {
        $(idPref+vnames[kn]).val( $('#insradr_'+vnames[kn]).val()).trigger('change');
     }
  }
  ,chgDocType: function(pref, sno) {
     var flname = pref + 'doctype' + sno;
     var dtType = $('#'+flname).val();
     // console.log(flname, dtType);
     if (dtType == '1' || dtType =='2') { // пасп РФ, св.рожд
        // $('#migcard_'+pref).hide();
        // $('#bl_docissued'+pref+sno).show();
        if (dtType == '1') $('#bl_docpodr'+pref+sno).show();
        else {
           $('#bl_docpodr'+pref+sno).hide();
           $('#'+pref+'docpodr'+sno).val('');
        }
        $('#'+pref+"docser"+sno).prop('required', true);
        $('#'+pref+"docdate"+sno).prop('required', true);
        // $('#bl_docdate'+pref+sno).show();
     }
     else if (dtType == '3' || dtType =='99' || dtType == '4') { // воен билет, иной док. загрпасс
        // $('#migcard_'+pref).hide();
        //$('#bl_docissued'+pref+sno).hide();
        $('#bl_docpodr'+pref+sno).hide();
        $('#'+pref+'docpodr'+sno).val('');

        $('#'+pref+"docser"+sno).prop('required', false);
        $('#'+pref+"docdate"+sno).prop('required', false);
     }
     /*
     else if (dtType == '6') { // ино паспорт
        $('#migcard_'+pref).hide();
        $('#bl_docissued'+pref+sno).hide();
        $('#bl_docpodr'+pref+sno).hide();
     }
     if (dtType == '20') { // мигр карта
        $('#migcard_'+pref).show();
        $('#bl_docissued'+pref+sno).hide();
        $('#bl_docpodr'+pref+sno).hide();
        // $('#bl_docdate'+pref+sno).hide();
     }
     */
  },
  chgChildDocType: function(chldo) {
  if (typeof(chldo) === 'undefined') chldo = '';
     var chlid = 'child'+chldo;
  var chlObj = $('#'+chlid+'doctype');
  var dtype = chlObj.val();
  if(dtype === '2') {
     $('#'+chlid+'_title_issued').text('Место государственной регистрации');
     $('#'+chlid+'docpodr').val('');
     $('#td_'+chlid+'docpodr').hide();
  }
  else {
     $('#'+chlid+'_title_issued').text('Кем выдан');
     $('#td_'+chlid+'docpodr').show();
  }
  }
  ,chgNoBenef: function() {
     if( $("#no_benef").is(":checked")) $("#div_beneficiaries").slideUp(100);
     else $("#div_beneficiaries").slideDown(100);
  }
  ,printXls: function(plgid) {
     var url = (!!plgid) ? './?plg='+plgid : this.backend;
     url +='&action=calctoxls';
     var calcid = $('input[name=calcid]').val();
     if (calcid) url +="&calcid="+calcid;
  //      alert('url='+url);
     window.open(url,'_blank');
  }
  ,confirmAddStmt: function(module) {
     if (module) policyModel.module=module;
     var opts = {
        width : 500
        ,title: 'Оформление заявления'
        ,text: 'С Клиентом согласован финансовый план ?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.performAddStmt, null);
  }
  ,performAddStmt: function() {
     var url = './?plg='+policyModel.module+'&action=stmt&id=calc';
     var calcid = $('input[name=calcid]').val();

     if (calcid) url +="&calcid="+calcid;
     window.location = url;
  }
  ,printPack: function() {
     var url = this.backend + '&clientprint=1&&action=print_pack&id='+this.stmtid;
     if (policyModel.ctrlKey) url+="&nostamp=3";
     else if (policyModel.altKey) url+="&nostamp=2";
     else if (policyModel.shiftKey) url+="&nostamp=1";
     setTimeout("policyModel.refreshScans()", 300);
     window.open(url,'_blank');
  }
  ,printA7: function() {
     var url = this.backend + '&ajax=1&action=print_a7&id='+this.stmtid;
     window.open(url,'_blank');
  }
  ,printAnketas: function() {
     var url = this.backend + '&ajax=1&&action=printAnketas&id='+this.stmtid;
     window.open(url,'_blank');
  }
  ,printMedicalAnketa: function() {
    var medicalforms = './?p=medicalforms';
    var url = medicalforms + "&ajax=1&action=printMedicalAnketa&id="+this.stmtid;
    window.open(url,'_blank');
  }
  ,refreshAgmtView: function() {
     ShowSpinner(false);
     asJs.sendRequest(policyModel.backend, {action:'refresh_view',id: policyModel.stmtid});
  }
  ,setState: function(newstate) {
     this.newstate = newstate;
     $('#statuscodes_data').hide();
     var txtstate = '';
     var dopHtml = '';
     if (plcStates[newstate]) txtstate = plcStates[newstate];
     else txtstate = newstate;
     policyModel.shifted = policyModel.shiftKey;
     if (newstate === 'uwreqdata')
     dopHtml = '<div class="lt">Введите комментарий для продавца<br><input type="text" id="uwcomment" class="ibox w400"></div>';
     else if(newstate === 'project' && policyModel.shifted)
     dopHtml = '<br>(полный сброс в начальное состояние!)';

     var askText = 'Вы собираетесь '+ ((newstate ==='clearpayed') ? txtstate : 'установить статус <b>'+txtstate+'</b>') + dopHtml;
     var dlgParam = {
        title: 'Изменение статуса договора'
     ,text:  askText + '<br>Подтверждаете операцию ?'
     ,dialogClass: 'floatwnd ct'
     ,closeOnEscape: true
     };
     dlgConfirm(dlgParam, policyModel.performSetState);
  }
  ,performSetState : function() {
  var params ={action:'setstate',id: policyModel.stmtid, state:policyModel.newstate},
  uwcomment = $('#uwcomment').val();
  if(uwcomment) params['uwcomment'] = uwcomment;
  if(policyModel.shiftKey || policyModel.shifted) params['shift'] = '1';
  if(policyModel.ctrlKey) params['ctrl'] = '1';
  asJs.sendRequest(policyModel.backend, params,1,0,policyModel.refreshAfterSetState);
  }
  ,refreshAfterSetState: function() {
  if (plcUtils.childGrid) $('#'+plcUtils.childGrid).trigger('reloadGrid');
  policyModel.refreshAgmtView();
  }
  ,performSetStateNoDecl : function() { // не соотв.декларациям - в андеррайтинг
     asJs.sendRequest(policyModel.backend, {action:'setstate',id: policyModel.stmtid, state:'touw', 'reason':'decl'},
        1,0,policyModel.refreshAgmtView);
  }
  ,onlineConfirm : function() {

     var opts = {
        width : 500
        ,title: 'Отправка клиенту запроса на подтверждение'
        ,text: 'Отправить на Email клиента запрос на подтверждение ?'
     };
     dlgConfirm(opts, policyModel.doOnlineConfirm, null);

  }
  ,doOnlineConfirm: function() {
     var params = { 'action': 'sendConfirmRequest', 'id':policyModel.stmtid };
     asJs.sendRequest(policyModel.backend,params, true, true);
  }
  ,saveStmt: function(noConfirm) {
     // console.log('saveStmt id:(' + this.stmtid + ')');
     var intid = parseInt(policyModel.stmtid);
     if (!isNaN(intid) && intid>0) $('input[name=stmt_id]').val(intid);
     if (!noConfirm && plcUtils.curstate == 4) {
        var dlgParam = {
           title: 'Сохранение изменений'
           ,text:  'Отправить полис на проверку андеррайтером?<br>(ответ Нет - продолжить ввод изменений)'
           ,dialogClass: 'floatwnd ct'
           ,closeOnEscape: true
        };
        dlgConfirm(dlgParam, policyModel.confirmedSaveStmt, policyModel.saveStmtKeepEdit);
        return;
     }
     if (noConfirm || superOper || intid>0 || policyModel.plcEditable) { this.confirmedSaveStmt(); return; }
     var saveopts = {
        title: 'Сохранение нового заявления',
        text: 'Важно!<br>Данные заявления проверены ?' + (policyModel.plcEditable ? '':'<br>После нажатия кнопки «ДА» корректировка данных будет невозможна !'),
        closeOnEscape: true
     };
     dlgConfirm(saveopts, policyModel.confirmedSaveStmt, null);
  }
  ,saveStmtKeepEdit : function() {// сохранение данных, не меняем тек.статус
  policyModel.confirmedSaveStmt(true);
  }
  ,confirmedSaveStmt: function(keepState) {
     // alert('confirmedSaveStmt');
     var disinputs = $('#fm_stmt').find(':disabled');
     disinputs.prop('disabled', false);
     var params = $('#fm_stmt').serialize();
     if(keepState) params += "&keepstate=1";
     disinputs.prop('disabled', true);
     var stid = $('#stmt_id').val() -.0;
     asJs.sendRequest('./?plg='+policyModel.module, params, true);
  }
  ,confirmStmtFormed: function() {
     var opts = {
        width : 500
        ,title : 'Оформление заявления'
        ,text  : policyModel.confirmStmtFormedText
        ,closeOnEscape: true
     };
     policyModel.newstate = 'stmt_formed';
     dlgConfirm(opts, policyModel.performSetState, policyModel.performSetStateNoDecl);
  }
  ,startStmtPayed: function() { // 7.3.3
    this.openStmtPayed();
  }
  ,openStmtPayed : function() {
    $("div.ui-dialog").remove();
    var htmlplat = '<div class="card floatwnd ct" id="div_setpayed" style="width:400px;">'
       + "<div class='p-3'>Дата оплаты: <input type='text' id='in_datepay' name='in_datepay' class='datefield d-inline form-control w100'><br>"

       + "<br>Сумма оплаты в рублях: <span id='in_oplata_rub'>...</span><br><div id='setpay_warning' class='attention'></div></div>"
       + '<div class="card-footer"><input type="button" class="btn btn-primary w100" id="btn_dosetpayed" onclick="policyModel.setStmtPayed()" value="OK" /></div>';

    $("body").floatWindow({
       html: htmlplat
       ,id: 'div_setpayed'
       ,position: { my: "center center", at: "center center", of: window.document}
       ,title: ('Простановка отметки об оплате')
       ,closeOnEscape: true
       ,init: function() {
          $('#in_datepay').datepicker().change(DateRepair).change(policyModel.refreshPayRur);
          asJs.sendRequest(policyModel.backend, {action:'setpayed_filldlg',id: policyModel.stmtid});
       }
    });

  }
  ,refreshPayRur: function(obj) {
     var params = {action: 'plc_newdatepay', id:policyModel.stmtid, dtpay: $('#in_datepay').val()};
     asJs.sendRequest(policyModel.backend, params);
  }
  ,confirmPayment: function() {
     var sparams = {action:'setpayed',datepay: $('#oplata_date').val(),
     id:policyModel.stmtid, pay_rur: policyModel.pay_rur
     };
     asJs.sendRequest(policyModel.backend, sparams);
  }
  ,confirmAgmtFormed: function() { // заменяемая ф-ция!
     var opts = {
        width : 400
        ,title: 'Оформление договора'
        ,text: 'Подтверждаете оформление договора ?'
        ,closeOnEscape: true
     };
     policyModel.newstate = 'formed';
     dlgConfirm(opts, policyModel.performSetState, null);
  }
  ,printZayav: function() {
     window.open(this.backend +'&action=print_stmt&id='+this.stmtid,'_blank');
  }
  ,calcOpenPrint: function(plgid) {
     var url = (!!plgid) ? './?plg='+plgid : this.backend;
     var wnd = window.open(url+"&action=calc1print", "_blank","height=750,width=900,location=0,menubar=0,resizable=1,scrollbars=1,status=1,toolbar=1,top=10,left=200");
     wnd.focus();
  }

  ,chgBenefPercent: function(obj) {
     NumberRepair(obj);
     var nom = parseInt(obj.value);
     if(nom >= 100 ) {
           obj.value="100";
     }
     else if (nom <= 0) obj.value="";
  }
  ,gridMessage: function(response,postdata){
     var txt   = response.responseText; // response text is returned from server.
     if(txt !=='1') TimeAlert(txt,3,'msg_error');
  //    var result = JSON.parse(json); // convert json object into javascript object.
  //    return [result.status,result.message,null];
     return [true]; // to auto-close delete confirm dialog
  }

  ,loadScanGrid : function(id) { // инициализация грида со списком сканов документов
  var hide = ($(window).width()<992) ? true:false;
  var stid = $('input#stateid').val();
  var canDelete = (stid<=7 || superOper );

  $("#grid_agrscans").jqGrid({
     datatype: 'json'
     ,mtype:'POST'
     ,url: this.backend
     ,postData: { action:'loadScans', 'id':id } // ,'doctype':'agmt'
     ,hidegrid:false
     ,colModel:[
        {name:'descr',label: 'Имя файла',sortable:false,align:'left',resizable:true,editable:false}
     ,{name:'doctype',label: 'Назначение', sortable:false,align:'left',resizable:true,editable:false}
     ,{name:'filesize',label: 'Размер', width:'50', sortable:false,align:'right',resizable:true,editable:false, hidden: hide}
     ,{name:'datecreated',label: 'Добавлено', width:'80', sortable:false,align:'center',resizable:true,editable:false, hidden: hide}
     ,{name:'viewscan',label: 'Просмотр',width:'100', sortable:false,align:'center',editable:false}
     ]
     ,height: 384
     ,caption: ''
     ,editurl: this.backend + '&action=updtScan'
     ,rownumbers: true
     ,width: null
     ,shrinkToFit : true
     ,autowidth: false
     ,pginput: false
     ,rowNum: -1 // rows on one page
     ,imgpath: gridimgpath
     ,pager: '#nav_agrscans'
     ,viewrecords: true
     ,multiselect:true
     ,loadComplete: function(data) {
        if (policyModel.loaded < 3) policyModel.loaded++;
        if (policyModel.loaded>=3) { policyModel.refreshAgmtView(); };
     }
  //      ,onSelectRow: vwoptRowsSelected // function(id) { vmo_curreq = id; } // function(id){ RefreshGridRoleRights(id);}
  }).navGrid("#nav_agrscans",{edit:false,add:false,del:canDelete, search:false}
     ,{},{},{afterSubmit: this.gridMessage});
  }
  ,openDoc: function(scanid) {
  window.open(this.backend + "&action=opendoc&id="+scanid, "_blank");
  }
  ,openStatusMenu: function() {
     if( $('#statuscodes_data').is(':visible') ) { $('#statuscodes_data').hide(); return; }
     var mnu = $('#statuscodes_data');
     mnu.show();
     var mHeight = mnu.height() + 25;
     $('#statuscodes_data').position({
           my: "left top",
           at: "left bottom-"+mHeight,
           of: $("#btn_setstatus")
     //   , collision: "fit"
     });
  }
  ,openScanMenu: function() {
     // $("div.ui-dialog").remove();
     if( $('#scanmenu_data').is(':visible') ) { $('#scanmenu_data').hide(); return; }
     var mnu = $('#scanmenu_data');
     mnu.show();
     var mHeight = mnu.height() + 25;
     console.log(mHeight);
     $('#scanmenu_data').position({
           my: "left top",
           at: "left bottom-"+mHeight,
           // of: $(this)
           of: $("#btn_uploadscan")
     //   , collision: "fit"
     });
  }
  ,openDockMenu: function(menuId, btn) {
    if( $(menuId).is(':visible') ) { $(menuId).hide(); return; }
    var mnu = $(menuId);
    mnu.show();
    var mHeight = mnu.height() + 25;
    $(menuId).position({
          my: "left top",
          at: "left bottom-"+mHeight,
          of: btn
    //   , collision: "fit"
    });
  }
  ,startUpload: function(scanType) {
     // console.log('start upload for '+scanType);
     $('#scanmenu_data').hide();
     // policyModel.uploader._opts.data['doctype'] = scanType;
     //console.log(policyModel.uploader._opts.data);
     //policyModel.uploader._createInput();
     // $('#btn_fireupload').click(); //trigger('click');
  }
  ,showDbg: function(plg,id) {
     var url = (plg ? './?plg='+plg : this.backend);
     var params = { action: 'getdebuginfo', id : (plg ? id : policyModel.stmtid) };
     var calcid = $('input[name=calcid]').val();
     if (calcid)params.calcid = calcid;
     $.post(url, params, function(data){
        showMessage('Debugging data',data, 'floatwnd');
     });
  }
  ,setUwConfirmed: function(moda) {
     $('input#uw_confirmed').val('1');
     this.confirmedSaveStmt(); // повторно отправляю на сохранение
  }
  ,setStmtPayed: function() {
     var params = { action:'setpayed', id: policyModel.stmtid, 'datepay': $('#in_datepay').val(),
     platno:  $('#in_platno').val() };
     console.log(params);
     if (params.datepay.length < 10) {
        TimeAlert('Введите дату оплаты !',2,'msg_error');
        return;
     }
     /*
     if (params.platno.length < 1) {
        TimeAlert('Введите номер платежки !',2,'msg_error');
        return;
     }
     */
     $('#div_setpayed').remove();
     // alert('id:'+params.id+ ' '+ params.datepay); return;
     asJs.sendRequest(policyModel.backend, params);
  }
  ,confirmAccept: function(actval) {
     policyModel.newstate = (actval ? 'set_accepted':'set_unaccepted');
     var opts = {
        width : 500
        ,title: 'Акцептация договора'
        ,text: 'Подтверждаете ' + (actval ? 'акцептацию договора' : 'отмену акцептации') + ' ?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.performSetState, null);
  }
  ,fmonCheck: function(pref) {
    var params = $('#fm_agr_'+this.module).serialize() + '&fmprefix='+pref;
    asJs.sendRequest(this.backend+'&action=fmon_check', params);
  }
  ,chgInsured2: function() {
     $('#b_insured2').is(':checked') ? $('#block_insured2').show() : $('#block_insured2').hide();
  }
  ,toggleBenDet : function (num) {
    var myobj = $("tr#ben_det"+num);
    if (myobj.is(":visible")) myobj.hide();
    else {
       $("tr.ben_detail").hide();
       myobj.show();
    }
  }
  ,chgBchild: function(obj) {
    var bname = obj.name, destId = '#block_child';
    var childChecked = 0;
    if (bname !== 'b_child') destId = '#block_child'+bname.replace('b_child','');
    if (obj.checked) $(destId).removeClass('hideme'); //.show();
    else $(destId).addClass('hideme'); // .hide();
    $("input[name^=b_child]").each(function() {if(this.checked) childChecked++;});
    console.log(childChecked);
    if(childChecked>0) $("#for_child").show(); else $("#for_child").hide();

    if ($.isFunction(policyModel.childToggle)) policyModel.childToggle(obj.checked);
  }
  ,nextStage: function() {
     var opts = {
        title: 'Следующий этап',
        text: 'Подтверждаете отправку на следующий этап?<br>(Договор станет недоступным для редактирования)',
        closeOnEscape: true
     };
     policyModel.shifted = policyModel.shiftKey;
     dlgConfirm(opts, policyModel.doNextStage);
  }
  ,doNextStage: function() {
  // var params = { 'action': 'NextStage', 'id':policyModel.stmtid };
  // asJs.sendRequest(policyModel.backend, params, true);
  policyModel.newstate = 'nextstage';
  policyModel.performSetState();
  }

  ,prolongate: function() {
     var opts = {
        width : 500
        ,title : 'Пролонгация договора'
        ,text  : 'Подтверждаете пролонгацию данного договора ?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, this.performProlongate);
  }
  ,performProlongate : function() {
     window.location = policyModel.backend + '&action=agredit&prolongid='+policyModel.stmtid;
     // asJs.sendRequest(this.backend, {action:'prolongate',id: policyModel.stmtid});
  }
  ,dissolute: function() {
  plcUtils.dissolute(policyModel.module, policyModel.stmtid);
  }
  ,showCalcParams: function() {
     $.post(policyModel.backend, {action:'showcalcparams', id:policyModel.stmtid}, function(data){
        showMessage('Параметры калькуляции полиса', data);
  });
  }
  ,copyWorkCompany: function() {
     $('#work_company').val($('#insrurname').val());
     var workaddr = $('#insradr_zip').val(), tTmp;
     if (tTmp=$('#insradr_countryid option:selected').text()) {
        if(tTmp !='Россия') workaddr += ", "+ tTmp;
     }
     if (tTmp=$('#insradr_country option:selected').text()) {
        if (tTmp !='--') workaddr += ", "+ tTmp;
     }
     if (tTmp=$('#insradr_region').val()) workaddr += ", "+ tTmp;
     if (tTmp=$('#insradr_city').val()) workaddr += ", "+ tTmp;
     if (tTmp=$('#insradr_street').val()) workaddr += ", "+ tTmp;
     if (tTmp=$('#insradr_house').val()) workaddr += ", дом "+ tTmp;
     if (tTmp=$('#insradr_corp').val()) workaddr += ", корп. "+ tTmp;
     if (tTmp=$('#insradr_build').val()) workaddr += ", стр. "+ tTmp;
     if (tTmp=$('#insradr_flat').val()) workaddr += ", офис "+ tTmp;
     $('#work_address').val(workaddr);
     $('#work_inn').val($('#insrurinn').val());
  },
  policyToXml: function() {
     var url = this.backend + '&action=policytoxml&id='+this.stmtid;
     window.open(url,"_blank");
     //, "height=160,width=600,location=0,resizable=1,scrollbars=1,status=0,toolbar=1,top=10,left=10");
  },
  policyToDocFlow: function(sedstate) {
     policyModel.sedState = sedstate;
     policyModel.shifted = policyModel.shiftKey;
     var opts = {
        width : 500
        ,title: 'Создание карточки договора в СЭД'
        ,text: 'Внимание ! Сейчас будет создана карточка договора в СЭД.<br>Все файлы приложены, данные проверены ?'
        ,closeOnEscape: true
     };

     dlgConfirm(opts, policyModel.runToDocFlow, null);
  },
  runToDocFlow : function() {
     ShowSpinner(true,false,true, 'Идет создание карточки в СЭД<br>Не уходите со страницы!');  // setTimeout(policyModel.refreshAgmtView, 2000);
     var params = {action:'policytodocflow',id: policyModel.stmtid, sedstate:policyModel.sedState };
     if (policyModel.ctrlKey) params['ctrl'] = '1';
     if (policyModel.altKey)  params['alt'] = '1';
     if (policyModel.shiftKey)  params['shift'] = '1';
     asJs.sendRequest(policyModel.backend, params, 1, 0, policyModel.refreshAgmtView);

  },
  refreshScans: function() {
  $("#grid_agrscans").trigger('reloadGrid');
  }
  ,checkFinmon: function() {
  ShowSpinner(true,false,true, 'Ожидайте, идет проверка по спискам<br>Не уходите со страницы!');
  $.post(policyModel.backend, {action:'checkfinmon',id: policyModel.stmtid }, function(data) {
     ShowSpinner(false);
     var spl = data.split("\t");
     showMessage('Результат проверки по спискам', spl[0],'msg_ok');
     if (spl.length>1 && spl[1] == 'reloadGrid')
        $("#grid_agrscans").trigger('reloadGrid');
  });
  },
  emailCheck: function(id) {
     var opts = {
        width : 500
        ,title: 'Проверка адреса Email'
        ,text: 'На указанный адрес будет послан контрольный код.<br>Выполнить проверку?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.doEmailCheck, null);
  },
  doEmailCheck: function() {
     // alert('TODO: '+policyModel.stmtid);
     var params = { id : policyModel.stmtid, plcutilsaction: "checkEmail"};
     asJs.sendRequest("./?p=plcutils", params, true, false);
     //
  },
  sendPdfToClient: function() {
     var opts = {
        width : 500
        ,title: 'Отправка полиса клиенту'
        ,text: 'Отправить клиенту Email с PDF версией полиса ?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.doSendPdfToClient, null);

  },

  doSendPdfToClient: function() {
     $('#btn_pdf2email').prop('disabled', true);
     asJs.sendRequest(policyModel.backend,
     { "action":'sendPdfToClient', id: policyModel.stmtid },
     true, false, policyModel.enableBtnSend);
  },
  enableBtnSend: function() {
     $('#btn_pdf2email').prop('disabled', false);
  }
  ,chgCountryId : function(obj) {
    var destid = $(obj).attr("id").replace('countryid','country');
    var curVal = $(obj).val();
    if (curVal != policyModel.RUSSIAID)
       $('#'+destid).val('').hide();
    else $('#'+destid).show();
  }
  ,chgRegion : function(obj) {
     var cityId = $(obj).attr("id").replace('country','city');
     $('#'+cityId).prop("required", (cityCodes.indexOf($(obj).val())>-1) ? false : true );
  }
  ,startUw: function() {
    var opts = {
       width : 500
       ,title: 'Отправка на андеррайтинг'
       ,text: 'Отправить полис на андеррайтинг ?<br>(Договор станет недоступным для редактирования)'
       ,closeOnEscape: true
    };
    dlgConfirm(opts, policyModel.performUw, null);
  }
  ,performUw: function() {
     asJs.sendRequest(policyModel.backend,
     { "action":"startUw", id: policyModel.stmtid },true, false);
  }
  ,showProlongInput: function() {
     $("#blk_prolong_input").toggle();
  }
  ,hideProlongInput: function() {
     $("#blk_prolong_input").hide();
  }
  ,seekProlongData: function(action) {
    var prpno = $("#prolong_policyno").val();
    var clientid = $("input[name=clientid]").val() || 0;
    // console.log(clientid);
    if (prpno == "") return;
    action = action || '';
    var splt = prpno.split("-");
    if (policyModel.prolongCodes !== false && policyModel.prolongCodes.indexOf(splt[0])===-1) {
       showMessage("Ошибка","Недопустимая кодировка полиса!", "msg_error");
       return;
    }
    var params = { "prolongaction": "findOriginalPolicy", "policyno":prpno, "module":this.module, 'action':action };
    if(parseInt(clientid)>0) params['clientid'] = clientid;
    asJs.sendRequest('./?p=prolongator', params, true, true);
  }
  ,loadPrevPolicy: function(policyno, module, action) {
    action = action || '';
    var params = {"prolongaction": "findOriginalPolicy", "policyno":policyno, "module":module, 'action':action};
    // console.log(params);
    // alert("loadPrevPolicy");
    asJs.sendRequest('./?p=prolongator', params, true, true);
  }
  ,bindAnketa: function() {
    var insFam = $('#insrfam').val();
    var insImia = $('#insrimia').val();
    var insOtch = $('#insrotch').val();
    var insBirth = $('input[name=insrbirth]').val();
    var inspSer = $('#insrdocser').val();
    var inspNo  = $('#insrdocno').val();
    var touw = $('#user_touw');
    var multiPolicy = 0;
    if (touw) multiPolicy = $('#user_touw').prop('checked') ? 1:0;
    plcUtils.bindInvestAnketa(policyModel.module,insFam,insImia,insOtch,insBirth,inspSer,inspNo);
  }
  ,chgBenType: function(pref,nom) {
    var isUL = $("#"+pref+"btype"+nom).is(":checked");
    // console.log("change Ben Type", pref, nom, isUL);
    if (isUL) {
       $(".fl"+pref+nom).css('visibility','hidden');
       $(".rfl"+pref+nom).hide();
       $(".rul"+pref+nom).show();
    }
    else {
       $(".fl"+pref+nom).css('visibility','visible');
       $(".rul"+pref+nom).hide();
       $(".rfl"+pref+nom).show();
    }
  }
  ,confirmSendToReg: function() {
     var opts = {
        width : 400
        ,title: 'Отправка договора на учет'
        ,text: 'Подтверждаете отправку договора на учет?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.performSendToReg, null);
  }
  ,performSendToReg : function() {
    var params ={ action:'submitForReg',id: policyModel.stmtid };
    if(policyModel.shiftKey) params['shift'] = '1';
    if(policyModel.ctrlKey) params['ctrl'] = '1';
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,setStateActive: function() {
     var opts = {
        width : 400
        ,title: 'Перевод договора в Действующий'
        ,text: 'Подтверждаете перевод в Действующий<br>и отправку на Архивное хранение?'
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.performSetStateActive, null);
  }
  ,performSetStateActive : function() {
    var params ={ action:'setStateActive',id: policyModel.stmtid };
    // if(policyModel.shifKey) params['shift'] = '1';
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,getFinPlan: function() {
    window.open(policyModel.backend + "&ajax=1&action=getfinplan&id="+policyModel.stmtid, '_blank');
  }
  ,viewOtherPolicies: function() {
    var params ={ action:'viewOtherPolicies',id: policyModel.stmtid };
    // if(policyModel.shifKey) params['shift'] = '1';
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,setExtendedAv: function() {
     var html = "Введите процент повышенного АВ:<br><input type='text' id='in_ext_av' class='ibox w100 ct' autocomplete='off'>";
     var opts = {
        width : 500
        ,title: 'Повышенное АВ'
        ,text: html
        ,closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.performExtAv, null);
  }
  ,performExtAv: function() {
     var avValue = parseFloat($("#in_ext_av").val());
     if(avValue<=0) {
        TimeAlert('Неверное значение !',1,'msg_error');
        return;
     }
     var params = { action:'setExtendedAv',id: policyModel.stmtid, "av":avValue };
     // if(policyModel.shifKey) params['shift'] = '1';
     asJs.sendRequest(policyModel.backend, params,true);
  }
  ,refreshDates: function() {
     var opts = {
        title: 'Обновление данных',
        text: 'Выполнить обновление данных (включая макс.дату выпуска) ?',
        closeOnEscape: true
     };
     dlgConfirm(opts, policyModel.doRefreshDates, null);
  }
  ,doRefreshDates: function() {
  var params ={ 'action':'refreshDates','id': policyModel.stmtid };
  asJs.sendRequest(policyModel.backend, params,true);
  }
  ,flexInsured: function() {
    var dBirth = $("#insdbirth").val();
    var dStart = $("#datefrom").val();
    var bEqual = $("#equalinsured").prop("checked");
    var age = asJs.yearsBetween(dBirth,dStart);
    if(bEqual || age>=18) { // adult
       $("#benef_block").show();
       $("#for_child").hide();
       $("input[name=insured_type]").val("adult");
       $("#insd_marriedstate").css('visibility','visible');
    }
    else {
       if(!policyModel.alwaisAdult) $("#benef_block").hide();
       $("#for_child").show();
       $("input[name=insured_type]").val("child"+(policyModel.alwaisAdult ? ',adult':''));
       $("#insd_marriedstate").css('visibility','hidden');
    }
    // console.log("birth changed ", dBirth, dStart, " age:", age, ' equal:',bEqual, ' type:', $("input[name=insured_type]").val());
  }
  ,viewPayPlan: function() {
     var params = { 'plg':policyModel.module, 'id':policyModel.stmtid, 'action':'viewPayPlan' };
     asJs.sendRequest(this.backend,params, true);
  }
  ,openAutoPayMenu: function() {
    // $("div.ui-dialog").remove();
    var mnu = $('#div_autopayments');
    if( $("#menu_autopayments").is(':visible') ) {
          mnu.hide();
          // console.log("hiding menu_autopayments");
          return;
    }
    mnu.show();
    var mHeight = mnu.height() + 25;
    mnu.position({
       my: "left top",
       at: "left bottom-"+mHeight,
       of: $("#btn_autopayments")
    });
  }
  ,addExtPayment: function() {
     $('#div_autopayments').hide();
     asJs.TimeAlert('addExtPayment пока не готово',1);
  }
  ,turnAutoPayments: function(operation) {
    policyModel.turnMode = operation;
    var sTitle, opts;
    if(operation === 'on')
      sTitle = 'Выполнить включение режима авто-оплаты ?';
    else sTitle = 'Выполнить отключение режима авто-оплаты?';
    opts = { 'text': sTitle, 'title':'Смена режима авто-оплаты' };
    asJs.confirm(opts, policyModel.execTurnAutoPayments);
    $('#div_autopayments').hide();
  }
  ,execTurnAutoPayments: function() {
    var params = { 'plg':policyModel.module, 'id':policyModel.stmtid, 'action':'TurnAutoPayments', 'value': policyModel.turnMode};
    asJs.sendRequest(this.backend,params, true);
  }
  ,startDopCheck: function() {
    opts = { 'text': 'Отправить договор в Поддержку Продаж на дополнительную проверку ?', 'title':'Отправка на проверку' };
    asJs.confirm(opts, policyModel.execStartDopCheck);
  }
  ,execStartDopCheck: function() {
    var params = { 'plg':policyModel.module, 'id':policyModel.stmtid, 'action':'startDopCheck' };
    asJs.sendRequest(this.backend,params, true);
  }
  ,dopCheckYes: function() {
    // opts = { 'text': 'Проверка пройдена успешно?', 'title':'Проверка' };
    opts = { 'text': 'Поставить отметку о пройденной проверке и продолжить оформление?', 'title':'Проверка' };
    asJs.confirm(opts, policyModel.execDopCheckYes); // policyModel.execDopCheckNo
  }
  ,execDopCheckYes: function() {
    var params = { 'plg':policyModel.module, 'id':policyModel.stmtid, 'action':'dopCheckSuccess' };
    asJs.sendRequest(this.backend,params, true);
  }
  ,dopCheckFail: function() {
    // opts = { 'text': 'Проверка пройдена успешно?', 'title':'Проверка' };
    opts = { 'text': 'Поставить отметку Проверка НЕ пройдена?', 'title':'Проверка' };
    asJs.confirm(opts, policyModel.execDopCheckFail); // policyModel.execDopCheckNo
  }

  ,execDopCheckFail: function() {
    var params = { 'plg':policyModel.module, 'id':policyModel.stmtid, 'action':'DopCheckFail' };
    asJs.sendRequest(this.backend,params, true);
  }
  ,loadClientData: function(prefix,clientid) {
    console.log("loadClientData prefix:", prefix, " clientid:",clientid);
    $.post(this.backend, {"action":"loadClientData", "clientid": clientid, "pref":prefix}, function(data){
       handleResponseData(data, true);
       setTimeout(policyModel.setLoaded,200);
    });
  }
  ,enableSaEdit : function(riskid, yesno) {
    if(yesno) { // включить
       $("#view_"+riskid).dblclick(policyModel.startRiskSaEdit).css('cursor', 'pointer').attr('title', 'Двойной клик для ввода новой СС');
       // console.log(riskid, " activated");
    }
    else { // выключить
       policyModel.resetEditSa();
       $("#view_"+riskid).unbind('dblclick').css('cursor', 'default').attr('title', '');
       // console.log(riskid, " DEactivated");
    }
  }
  ,startRiskSaEdit: function(evt, p2) {
    if(policyModel.saElemId) { // сбросить тек.ввод
       $("#sarisk_tmp").unbind("keyup");
       $("#"+policyModel.saElemId).html(policyModel.saBuffer);
    }
    var elemId = policyModel.saElemId = $(this).prop("id");
    policyModel.saBuffer = $("#"+elemId).html();
    var riskid = elemId.replace("view_",'');
    policyModel.saRiskId = riskid;
    var EditHtml = "СС:<input type='number' id='sarisk_tmp' class='ibox w100 ct'>";
    $("#"+elemId).html(EditHtml);
    $("#sarisk_tmp").on("keyup", policyModel.saTmpKeyup).focus();
    $("#sarisk_tmp").on("blur", policyModel.saTmpKeyup);
  }
  ,resetEditSa: function() {
    if(policyModel.saElemId) { // сбросить тек.ввод
       $("#sarisk_tmp").off();
       $("#"+policyModel.saElemId).html(policyModel.saBuffer);
       policyModel.saElemId = policyModel.saBuffer = false;
    }
  }
  ,saTmpKeyup: function(evt, p2) {
    if(evt.type == 'blur') {
       policyModel.resetEditSa();
       return;
    }
    if(evt.keyCode == 13 || evt.keyCode == 27 || evt.keyCode==9) { // Save data?
       var newSa = parseFloat($(this).val());
       policyModel.resetEditSa();
       // $("#sarisk_tmp").unbind("keyup");
       // $("#"+policyModel.saElemId).html(policyModel.saBuffer);
       if(evt.keyCode == 13 && newSa>0) {
       // console.log("to Save changes :"+newSa);
       var params = { 'plg':policyModel.module, 'id':policyModel.stmtid, 'action':'updateRiskSa', 'riskid':policyModel.saRiskId, 'value': newSa};
       asJs.sendRequest(policyModel.backend, params, true);
       }
    }
    // else console.log(evt);

    // console.log("this:" , $(this));
    return false;
  }
  ,bindToclient: function(clientId) {
    policyModel.bindClient = clientId;
    var equalInsured = policyModel.getFieldValue("equalinsured"); // hidden, select
    var clientPref = equalInsured ? 'insr' : 'insd';
    policyModel.loadClientData(clientPref, policyModel.bindClient);
    // console.log("Binded to client ", clientId, " now equal is:",equalInsured);
  }
  ,getFieldValue: function(fldname) {
    var fldObj = $("input[name="+fldname+"]");
    var eqType = fldObj.attr('type');
    if (eqType === "checkbox")
       state = fldObj.prop('checked'); //(obj) ? obj.checked : this.checked;
    else state= fldObj.val(); // hidden
    return state;
  }
  ,clearBlock: function(prefix) {
    // console.log("clearBlock for ", prefix);
    $(':input[name^='+prefix+']').each(function() {

       if(this.type=='text' || this.type=='tel') this.value='';
       else if(this.type=='select-one') {
          var firstVal = $("option:first", this).val();
          // console.log(this.name," first option:", firstVal);
          $(this).val(firstVal);
       }
       //else console.log(this.name, this.type);
    });
    // $.each($("input[name^=insr]")).function(console.log(this.name);
  }
  ,openBindClient: function() {
    $.post("./?p=bindclient&clientmode=calc&fclaction=form", {}, function(response) {
       asJs.confirm({
          width: 680,
          title:"Выбор клиента для расчета",
          text: response
       }, false, false);
    });
  }
  ,cleanupFias:function() {
    if(policyModel.loaded) {
      var flname = this.name;
      var fiasField = flname.split('_')[0]+'_fias';
      var lastCh = flname.slice(-1);
      if(lastCh>='0' && lastCh<='9') fiasField+=lastCh; // benefadr_field{N} fias field
      // console.log(policyModel.loaded, flname, fiasField, $("#"+fiasField).val());
      $("#"+fiasField).val(""); // зачистил устаревший ФИАС
      // console.log(fiasField, "зачищен");
    }
  }
  /*
  ,openModifyPdata: function() {
    var params ={ 'action':'openModifyPdata','id': policyModel.stmtid };
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,startEditPdata: function(ptype, recid) {
      $("div.ui-dialog").remove();
      console.log("startEditPdata:", ptype, recid);
  }
  */
  ,openSendToCompliance: function() {
    var opts = { 'text': 'Отправить на проверку в Комплаенс?', 'title':'Проверка данных по полису' };
    asJs.confirm(opts, policyModel.doSendToCompliance);
  }
  ,doSendToCompliance: function() {
    var params ={ 'action':'sendToCompliance','id': policyModel.stmtid };
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,openComplianceOk: function() {
    var opts = { 'text': 'Проставить о прохождении проверок Комплаенс ?<br>(полис будет пропущен на следующий этап)', 'title':'Проверка Комплаенс пройдена' };
    asJs.confirm(opts, policyModel.doComplianceOk);
  }
  ,doComplianceOk: function() {
    var params ={ 'action':'setComplianceOk','id': policyModel.stmtid };
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,openStartReedo: function() { // rework EDO
    var opts = { 'text': 'Отправить измененные данные на онлайн-согласование Клиенту?', 'title':'Проверка данных клиентом' };
    asJs.confirm(opts, policyModel.doStartReedo);
  }
  ,doStartReedo: function() {
    var params ={ 'action':'startReEDO','id': policyModel.stmtid };
    asJs.sendRequest(policyModel.backend, params,true);
  }
  ,nameKeyUp: function(ev) {
    var fldid = this.name;
    if(ev.ctrlKey && ev.keyCode==67) {// CTRL-C
      policyModel.copyFrom = fldid;
      asJs.timedNotification("Источник копирования установлен",1);
    }
    else if(ev.ctrlKey && ev.keyCode==86) {// CTRL-V
      if(policyModel.copyFrom) {
        policyModel.pastePerson(fldid);
      }
    }
  }
  ,pastePerson: function(fldTo) {
    var fromPref = policyModel.copyFrom.substring(0, policyModel.copyFrom.length - 3); // insr | insd | child
    var toPref = fldTo; // .substring(0, fldTo.length - 3);
    var fnames = ['rez_country','birth_country','doctype','docser','docno','docdate','docpodr','docissued','phone','phone2',
       'adr_full','adr_zip','adr_country','adr_city','adr_street','adr_house','adr_corp','adr_build','adr_flat','adr_fias',
       'fadr_full','fadr_zip','fadr_country','fadr_city','fadr_street','fadr_house','fadr_corp','fadr_build','fadr_flat','fadr_fias'];
    if(fldTo.substring(0,13) =='beneffullname') {
      toPref = 'benef';
      benNo = fldTo.substring(13,15);
      $("#"+toPref+'fullname'+benNo).val( $("#"+fromPref+'fam').val() + ' '+$("#"+fromPref+'imia').val() + ' '+$("#"+fromPref+'otch').val() );
      $("#"+toPref+'birth'+benNo).val( $("input[name="+fromPref+'birth]').val() );


      // console.log($("#"+fromPref+'birth').val());
      // $("#"+toPref+'rez_country'+benNo).val( $("#"+fromPref+'rez_country').val() );
      // $("#"+toPref+'birth_country'+benNo).val( $("#"+fromPref+'birth_country').val() );
      fnames.forEach(function(item) {
          $("#"+toPref+item+benNo).val( $("#"+fromPref+item).val());
      });
      var sameAddr = $("#"+fromPref+'sameaddr').is(':checked');
      $("#"+toPref+'sameaddr'+benNo).prop('checked', sameAddr).triggerHandler('click');
    }
    // else asJs.timedNotification("TODO: copy from "+fromPref+" to "+toPref);
  }
};

su = {
   found_agmt : ''
  ,found_user : ''
  ,moduleid : ''
  ,headdept_agmt: ''
  ,headdept_user: ''
  ,findAgreement: function() {
    var pno = $('#su_policyno').val();
    if (pno==='') return;
    $.post('./?p=policytools',{action:'find_agreement',policyno:pno},function(response){
       data = response.split("\t");
       if (data[0] ==='1') {
             su.moduleid = data[1];
             su.found_agmt = data[2];
             su.headdept_agmt = data[3];
             $('#div_suagreementinfo').html(data[4]);
       }
       else {
             su.found_agmt = su.moduleid = su.headdept_agmt = '';
             $('#div_suagreementinfo').html(response);
       }
       su.enableBindButton();
    });
   }
  ,findOperator: function() {
    var user = $('#su_newuser_login').val();
    if (user==='') return;
    $.post('./?p=policytools',{action:'find_operator',userno:user},function(response){
       data = response.split("\t");
       if (data[0] ==='1') {
             su.found_user = data[1];
             su.headdept_user = data[2];
             $('#div_sufounduser').html(data[3]);
       }
       else {
             su.found_user = su.headdept_user = '';
             $('#div_sufounduser').html(response);
       }
       su.enableBindButton();
    });
   }
   ,enableBindButton: function() {
      var disab = (this.headdept_agmt=='' || this.headdept_user=='' ); // || (this.headdept_agmt!==this.headdept_user)
      // alert('disab is '+ disab + "/"+su.headdept_agmt+"/"+su.headdept_user + ".");
      $('#btn_rebindagmt').prop('disabled', disab);
   }
   ,findAgmtInLog : function() {
      var pno = $('#plcseek_policyno').val();
      if (pno==='') return;
      $.post('./?p=policytools',{action:'seek_lost_policy',policyno: pno},function(response){
         $('#su_seek_policy_result').html(response);
      });
   }
}
_modalWaitText = 'Подождите!';
// $(document).ready(function() { activateAjaxSpinner(true); });

