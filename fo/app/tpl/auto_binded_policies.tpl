# Регистрация авто-привязок полисов в КК в ночном задании {upd/2024-12-18}
ID|auto_binded_policies
DESCR|Регистрация авто-привязок полисов в КК
BRORDER|id desc
BRWIDTH|680
SEARCH|policyno,userid
SEARCHCOLS|1
CHARSET|@GetMainCharset
EDITFORMWIDTH|400
RIGHTS|1|0|0|0
# RIGHTS|NV|NE|ND|NA : NV - view right, NE-edit, ND-delete, NA-add
#FIELD| int_id |f_name |descr |sdesc |type |len |not_null| default[,new_val]| b_cond |b_formula | e_cond | e_type[,sub-params]
TOOLBAR|<span><input type="button" id="btn_bindreport" class="btn btn-primary" value="Получить отчет" onclick="bindedPlc.openReport()" title="Выполнить отчет в XLSX/HTML/XML"/></span>

FIELD|id||Nпп|Nпп|PKA||1||S|||
FIELD|contractid||ИД контракта(LISA)||BIGINT|20|1|0|1||1|TEXT,class="ibox w80"
FIELD|policyno||Номер полиса||VARCHAR|40|1|''|1||1|TEXT,class="ibox w160"
FIELD|userid||ИД клиента(BITRIX)|ИД клиента|BIGINT||1|0,''|1||1|TEXT,class="ibox w80"
FIELD|linkdate||Дата привязки||DATETIME||1|'0000-00-00'|1||1|TEXT
CUSTOMCOLUMN|<div class="ct"><span class="bi bi-info-circle font12 pnt" onclick="bindedPlc.showDetails({ID})" title="посмотреть подробности"/></div>|Inf
<script>
bindedPlc = {
  showDetails: function(id) {
    var params = {"miscinfoaction":"viewBindedPolicy", "id":id};
    asJs.sendRequest("./?p=miscinfo", params,true);
  },
  openReport: function() {
    window.open("?p=flexreps&name=bindPolicies", "_blank");
  }
};
</script>