# PM::T_DEPT_PROD Настройки параметров страховых продуктов по партнерам, upd:2026-02-06
ID|alf_dept_product
DESCR|Настройки параметров страховых продуктов по партнерам
DropUnknown|1
CHARSET|@GetMainCharset
search|deptid,module
# BRWIDTH|800px
ORDERBY|id
BRHEIGHT|500px
EDITFORMWIDTH|760
CONFIRMDEL|1
# реализуем динамическую форму редактирования спец-параметров для выбранного плагина (module)
# EDITSUBFORM|OuProdCfg::getHtmlLink
# BEFOREEDIT|OuProdCfg::clearSavedParams
# BEFOREUPDATE|OuProdCfg::restoreSpecParams
AUDITING|DataAudit
BLISTHEIGHT|200px
SEARCHCOLS|1
FIELD|id||ИД||PKA||||S||1|0||
FIELD|deptid||Орг.Юнит/партнер|Орг-Юнит|BIGINT|20|1|0|1|@OrgUnits::GetDeptName|1|SELECT,@OrgUnits::getAllPrimaryDepts,class="form-select d-inline w300"

FIELD|module||Модуль/семейство продуктов|модули|VARCHAR|30|1|''|1||1|SELECT,@Modules::getAgmtModules,onchange='deptProduct.chgModule(this)' class="form-select d-inline w300"
FIELD|prodcodes||Доступные продукты|продукты|VARCHAR|240|1|''|1|@BrShortText|1|BLISTLINK,@Modules::blistModuleProdcodes,deptProduct.openSubParam('{id}'),Доп.настройки
FIELD|visiblename||Название для партнера|П-Название|VARCHAR|80|1|''|1||1|TEXT,class="form-control w300" maxlength="80"
FIELD|ag_agreement||Номер агентского договора/ИКП|Аг.Дог|VARCHAR|40|1|''|0||1|TEXT,class="form-control text-center w200" maxlength="40"
FIELD|comission||Комиссия||DECIMAL|6,2|1|5|0||1|TEXT, style='width:60px; text-align:center'
FIELD|comthresh||Порог(д/комиссии-2)|К-порог|INT|1|1|0|0||0|TEXT, style='width:60px; text-align:center'
FIELD|comission2||Комиссия-2||DECIMAL|6,2|1|10.0|0||0|TEXT, style='width:60px; text-align:center'
FIELD|termcomission||КВ от срока и рассрочки|КВ-срок|VARCHAR|100|1|''|0||0|TEXT,style="width:100%" title="формат SP.срок=NN;RP.срок=NN;RP.срок2=NN..."
FIELD|origin_for||Является оригиналом для|оригинал|VARCHAR|200|1|''|0||0|BLIST,@OrgUnits::getAllPrimaryDepts

FIELD|anketa_output||Где печатать анкеты|где анкеты|CHAR|1|1|'P'|0||1|SELECT,Z=В заявление;P=В полис;0=не печатать,class="form-select w300"
FIELD|pdn_output||Где печатать согласия обр.ПДн|ПДн|CHAR|1|1|'Z'|0||1|SELECT,0=не печатать;Z=В заявление или в полис;P=В полис,class="form-select w300"
FIELD|b_clientanketa||Анкета клиента-ФЛ|анкета|VARCHAR|40|1|1|0||1|SELECT,@AppLists::AnketaTypes,class="form-select w300"
#  TODO: b_clientanketaul, anketa_benef - поддержка еще не реализована
FIELD|b_clientanketaul||Анкета клиента-ЮЛ|анкета|VARCHAR|100|1|1|0||1|SELECT,@AppLists::AnketaTypesUL,class="form-select w300"
FIELD|anketa_benef||Анкета выг-приобр|анкета-ВП|VARCHAR|100|1|1|0||1|SELECT,@AppLists::AnketaBenefTypes,class="form-select w300"
FIELD|opros_output||Где печатать опрос-лист(FATCA)|где опрос-лист|CHAR|1|1|'P'|0||1|SELECT,Z=В заявление;P=В полис;0=не печатать, class="form-select w-auto"
FIELD|opros_list||Шаблон опрос-листа|Опрос-лист|VARCHAR|100|1|1|0||1|SELECT,@AppLists::OpListTypes,class="form-select w300"
FIELD|zadd_print||Доп.печать документа(заявл)||VARCHAR|100|1|''|0||1|SELECT,@AppLists::AdditionalPrintouts,class="form-select w300"
FIELD|print_edo||Доп.печать для ЭДО(полис)||VARCHAR|100|1|''|0||1|SELECT,@AppLists::AdditionalPrintoutsEDO,class="form-select w300"
FIELD|add_print||Доп.печать документа(полис)||VARCHAR|100|1|''|0||1|SELECT,@AppLists::allPrintOuts,class="form-select w300"
# 2020-04-24 новое - онлайн подтверждение клиентом:
FIELD|online_confirm||Онлайн-подтверждение клиентом||INT|4|1|0|0||1|SELECT,0=нет;1=Стандарт И ЭДО процесс;10=Только ЭДО процесс,class="form-select w200"
FIELD|days_to_from||Нестанд.дни до начала д-вия|до начала|TINYINT|2|1|0|0||1|TEXT,class="form-control text-center w80 number"
FIELD|sed_chanel||СЭД:Канал продаж|СЭД-канал|VARCHAR|10|1|''|0||1|TEXT,class="form-control text-center w200" maxlength='10'
FIELD|ag_kp||СЭД: КП агента|КП аг.|VARCHAR|80|1|''|0||1|TEXT,class="form-control text-center w200" maxlength="80"

FIELD|fld_married||Ввод семейного положения||VARCHAR|4|1|'0'|0||1|SELECT,0=стандарт;N=Не вводить;M=Обязательный ввод,class="form-select w200"
FIELD|fld_extclientid||Ввод партнёрского ИД клиента|КлтИД|VARCHAR|8|1|0|0||1|SELECT,0=нет;1=Необязательный;6CD=Обязательный 6 букв/цифр,class="form-select w200"
FIELD|fld_phonepref||Ввод телеф.кода страны|Тлф|TINYINT|1|1|0|0||1|CHECKBOX,title="Выбор кода страны вместо дефолтного +7"
FIELD|b_blockaddr||Блокировка полиса по адресам/странам|Блк-страны|TINYINT|1|1|0|0||1|CHECKBOX
FIELD|anketa_klienta||Вопросы к анкете клиента|анк|TINYINT|1|1|0|0||1|SELECT,0=По умолчанию;1=Стандартная;2=Тип 2 (РФБ),class="form-select w200"

FIELD|subtypecode||Шаблон для субтипа(экспорт в LISA)|Субтип|VARCHAR|40|1|''|0||1|TEXT,class="form-control w200" maxlength="40"

# FIELD|calc_paydate||Расчет даты оплаты|опл.|INT|2|1|0|0||1|SELECT,0=стандартно;3=плюс 3 раб.дня, style="width:200px"

# тип ввода FLEXFIELDSET (под-набор полей): следующий - callback рендера HTML кода блока ввода,
# следующий - имя поля, от которого зависит список, и при смене которого надо перерисовать html-блок ввода
FIELD|specparams||Специальные параметры|спц.|TEXT||1|''|0||1|FLEXFIELDSET,\ouprodcfg::renderSpecParams,module|
FIELD|b_active||Включен|Вкл|TINYINT|1|1|1|1||1|CHECKBOX
FIELD|testmode||Режим тестирования|Тст|TINYINT|1|1|0|1||1|CHECKBOX

<script>
deptProduct = {
   chgModule: function(obj) {
      $('#div_prodspecparams').floatWindowClose();
      $.post('./?p=ouprodcfg&dpaction=getBlistPrograms', {field:'prodcodes', module: $(obj).val()}, function(data) {
        $('#blist_prodcodes').html(data);
      });
   }
   ,openSpecParamForm: function() {
        var id = $('input[name=_astkeyvalue_]').val();
        var plg = $("select#module").val();
        console.log(id, plg);
        // var deptid = $('#deptid').val();
        $.post("./?p=ouprodcfg&dpaction=getsubform", {"dpmodule":plg,"id":id}, function(data){
            var spl = data.split("|");
            if (spl[0] !=='1' && spl[0] !=='OK')
                TimeAlert(spl[1],3,'msg_error');
            else {
                $("body").floatWindow({
                   html:'<div class="floatwnd ct w-600" id="div_prodspecparams">'
                    + '<div id="div_specpar_content" class="ou_prod_spec">' + spl[1] + '</div>'
                    + '<div class="bounded"><button class="btn btn-primary" id="btn_savespc" onclick="deptProduct.saveSpecpar()">Сохранить</button></div></div>'
                  ,id: 'div_prodspecparams'
                  ,position: { my: "center center", at: "center center", of: window.document }
                  ,title: 'Специальные параметры'
                  // ,closeOnEscape: true
                  //,init: function() {}
                });
            }
        });
   }
   ,saveSpecpar: function() {
     var spcPars = $('#div_specpar_content :input').serialize() + "&id="+ $('#_astkeyvalue_').val();
     $('#btn_savespc').prop('disabled',true);
     $.post("./?p=ouprodcfg&dpaction=saveData", spcPars, function(resp) {
        if (resp === '1')
           $('#div_prodspecparams').floatWindowClose();
        else {
           $('#btn_savespc').prop('disabled',false);
           TimeAlert(resp,5,'msg_error');
        }
     });
   }
   ,openSubParam: function(subid) {
       var sdeptid = $("#deptid").val();
       var smodule = $("#module").val();
       var params = { "partner":sdeptid, "module":smodule, "progid":subid };
       $.post("./?p=ouprodcfg&dpaction=openEditSubParams", params,function(data) {
           var splt = data.split("|");
           if(splt[0] != "1") {
              TimeAlert(data,5,'msg_error');
              return;
           }
           var sOpts = {"title":"Редактирование спец-параметров", "text":splt[1], "width": 600,'yes':'Сохранить','no':false};
           dlgConfirm(sOpts, deptProduct.saveSubParams);
       });
   }
   ,saveSubParams: function() {
       var params = $("#spar_module_dept").serialize();
       $.post("./?p=ouprodcfg&dpaction=saveSubParams", params,function(data) {
           if(data!=='1') TimeAlert(data,5,'msg_error');
       })
   }
};
</script>
