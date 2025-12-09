<?php
/**
* app/bindclient.php
* Ф-ционал поиска клиента среди ранее заеденных агентом полисов, привязки к клиенту, обновления данных клиента
* прямой ajax вызов : ./?p=bindlient&fclaction=Form{&other params... in get or send POST}
* @version 0.10.001
* modified 2025-12-09 / created 2025-02-26
*/
class BindClient {
    static $debug = 0;
    static $seekInPolicies = TRUE; # при поиске клиента лезть еще и в свои полисы и брать людей оттуда
    static $initOldDays = 370; # initClientList() - при начальном заполнении базы клиентов брать полисы не старее NNN дней

    public const ERR_NO_ACCESS = 1001; # у запросившего данные клиента нет к нему доступа
    public static $cliFields = ['fam','imia','otch','inn','snils','birth','sex','rez_country','birth_country','doctype','docser','docno',
      'docdate','docpodr','docissued','inopass','otherdocno','permit_type','migcard_ser','migcard_no','docfrom','doctill','married','phone','phone2','email',
      'adr_full','adr_zip','adr_countryid','adr_country','adr_region','adr_city','adr_street','adr_house','adr_corp','adr_build','adr_flat','sameaddr','adr_fias',
      'fadr_full','fadr_zip','fadr_countryid','fadr_country','fadr_region','fadr_city','fadr_street','fadr_house','fadr_corp','fadr_build','fadr_flat','fadr_fias'
    ];
    public static $dateFields = ['birth','docdate','docfrom','doctill'];

    /**
    * мини-форма с кнопкой для открытия диалога выбора клиента
    */
    public static function inlineForm($hideButtons = '') {
        $clientMode = AppEnv::getConfigValue('lifeag_clientmgr');
        $htmlAddText = '';
        if($clientMode == 10) {
            $htmlAddText .= " Пока Вы не выберете клиента, расчет будет недоступен!!";
            if(!empty($hideButtons)) {
                addJsCode("$('$hideButtons').hide();", 'ready');
                # прячу кнопки Рассчитаь пока не выберут клиента в кач-ве Застрахованного prop('visibility;','hidden')
                # writeDebugInfo("$hideButtons hided");
            }
        }

        $html = "<div class=\"card p-2\" id='btn_bindclient'><div class=\"col-md-12 col-12\"><input type=\"button\" "
          . "class=\"btn btn-primary\" value=\"Выбрать клиента\" onclick='policyModel.openBindClient()'/>"
         . "$htmlAddText</div></div>";
        return $html;
    }
    /**
    * вернёт HTML код формы ввода ФИО (и других данных) для поиска клиента среди своих(!)
    *
    */
    public static function form($mode = FALSE) {
        # writeDebugInfo(__CLASS__ . "/form trace: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
        $enabled = AppEnv::getConfigValue('lifeag_clientmgr');
        if(!empty(AppEnv::$_p['clientmode']))
            $mode = AppEnv::$_p['clientmode'];

        if(!$enabled) {
            AppEnv::appendHtml('<div class="card ct alarm p-4">Функционал выбора и добавления клиентов временно отключен!</div>');
            AppEnv::finalize();
        }
        $productListCode = 'Для Вас нет доступных программ!';
        if(empty($mode)) {
            $allLinks = [];
            foreach(AppEnv::$_plugins as $plgid => $plgObj) {

                if(method_exists($plgObj, 'stub')) {
                    $links = $plgObj->stub('list');
                    if(is_array($links) && count($links)) foreach($links as $link){

                        $title = $link['title'];
                        $href = $link['href'];
                        $allLinks[] = "<a type='button' class='btn btn-outline-primary' href=\"javascript:void(0)\" onclick=\"fcli.openLink('$href')\">$title</a>";
                    }
                }
            }
            if(count($allLinks)) $productListCode = implode(' ', $allLinks);
        }
        $html = <<< EOHTM
<div class='bordered p-3'><form id="fcli_seek"><input type="hidden" name="bindmode" value=$mode" />
<div class="card p-3 bordered" id="cli_stage1"> <legend>Введите данные для поиска клиента</legend>
  <div class="row">
    <div class="col-lg-4 col-md-6 col-12">Фамилия<br>
      <input type="text" name="cli_fam" id="cli_fam" class="form-control " maxlength="80" />
    </div>
    <div class="col-lg-4 col-md-6 col-12">Имя<br>
      <input type="text" name="cli_imia" id="cli_imia" class="form-control inFirstname " maxlength="40" />
    </div>
    <div class="col-lg-4 col-md-6 col-12">Отчество<br>
      <input type="text" name="cli_otch" id="cli_otch" class="form-control inMidname " maxlength="40" />
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8 col-md-6 col-12">
      Дата рождения<br>
      <input type="tel" name="birth_d" id="birth_d" class="form-control w40 d-inline ct" placeholder="ДД" /> .
      <input type="tel" name="birth_m" id="birth_m" class="form-control w40 d-inline ct" placeholder="ММ" /> .
      <input type="tel" name="birth_y" id="birth_y" class="form-control w60 d-inline ct" placeholder="ГГГГ"/>
    </div>
    <div class="col-lg-4 col-md-6 col-12">
      Пол<br><select name="cli_sex" class="form-select w120">
        <option value="">---</option>
        <option value="M">Мужской</option>
        <option value="F">Женский</option>
        </select>
    </div>
  </div>

  <div class="area-buttons card-footer" style="margin:1em 0" id="cli_buttons">
     <input type="button" class="btn btn-primary w200" value="Искать" onclick="fcli.startSeek()"/>
     <input type="button" id="btn_add_client" class="btn btn-primary w200" style="display:none" value="Завести нового клиента" onclick="fcli.createClient()"/>
  </div>

  <div class="row p-2 bordered" id="seek_results" style="max-height:300px; overflow:auto">
    &nbsp;
  </div>
</div>
</form></div>
EOHTM;

        if(empty($mode))  { # AJAX вызов с формы калькулятора или agredit - нужен только код формы поиска

            $html .= <<< EOHTM
<div id="cli_stage2" class="bordered p-3" style="display:none">
  <legend>Выберите продукт, для которого будете заводить договор</legend>
  <div class="d-flex flex-wrap justify-content-around justify-content-md-start gap-3">
  <div id="client_data" class="card bordered p-2 w100prc">... </div>
  $productListCode
  </div>
</div>

<script type="text/javascript">
fcli = {
  clientId: 0,
  fullName: '',
  startSeek: function() {
    fcli.clientId = 0;
    asJs.sendRequest("./?p=bindclient&fclaction=getMyClients", $("#fcli_seek").serialize(), true);
  },
  setClient: function(clid) {
    fcli.fullName = $("#found_"+clid).html();
    // alert(clid +' : '+ clid.substring(0,2)); return;
    if(clid.substring(0,2) === 'p_') { // нашел в полисе, надо перегнать в клиента
      var params = { 'personid':clid };
      $.post("./?p=bindclient&fclaction=fromPlcToclient", params, function(response) {
        console.log(response);
        var splt = response.split("|");
        if(splt[0] ==='1') {
          fcli.clientId = splt[1]; // пришел ИД нового клиента
          fcli.openProductSelect();
        }
        else {
          alert(response);
        }
      });
      return;
    }
    fcli.clientId = clid;
    fcli.openProductSelect();
  },
  createClient: function() {
    dlgConfirm({title:'Новый клиент',text:'Завести нового клиента с заданными ФИО/датой рождения?'}, fcli.performCreateClient, null);
  },
  performCreateClient: function() {
    fcli.fullName = $("#cli_fam").val() + ' '+$("#cli_imia").val() + ' '+$("#cli_otch").val();
    asJs.sendRequest("./?p=bindclient&fclaction=appendClient", $("#fcli_seek").serialize(), true);
  },
  openProductSelect: function() {
    $("#cli_stage1").hide();
    $("#client_data").html("Клиент : "+fcli.fullName);
    $("#cli_stage2").show();
  },
  openLink: function(strLink) { // открываем ссылку на ввод нового дог., добавив в ней ИД клиента
    var finalLink = strLink + "&clientid=" + fcli.clientId;
    // alert(finalLink);
    window.location.href = finalLink;
  }
}
</script>
EOHTM;
            UseJsModules('maskedinput');
            $jsReady = '$("#birth_d,#birth_m").mask("99"); $("#birth_y").mask("9999");';
            AddHeaderJsCode($jsReady, 'ready');
            AppEnv::setPageTitle('Выбор (ввод) клиента');
            AppEnv::appendHtml($html);
            AppEnv::finalize();
            exit;
        }
        $html .= <<< EOHTM
<script type="text/javascript">
fcli = {
  clientId: 0,
  fullName: '',
  startSeek: function() {
    fcli.clientId = 0;
    asJs.sendRequest("./?p=bindclient&fclaction=getMyClients", $("#fcli_seek").serialize(), true);
  },
  setClient: function(clid) {
    var oldUrl = window.location;
    window.location = oldUrl+"&clientid="+clid;
  },
  /*
  createClient: function() {
    dlgConfirm({title:'Новый клиент',text:'Завести нового клиента с заданными ФИО/датой рождения?'}, fcli.performCreateClient, null);
  },
  */
  createClient: function() {
    fcli.fullName = $("#cli_fam").val() + ' '+$("#cli_imia").val() + ' '+$("#cli_otch").val();
    asJs.sendRequest("./?p=bindclient&fclaction=appendClient", $("#fcli_seek").serialize(), true);
  },
}
</script>
EOHTM;
        exit($html);
    }


    /**
    * AJAX запрос на поиск клиентов, подходящих под переданные данные
    *
    */
    public static function getMyClients() {
        $fam = AppEnv::$_p['cli_fam'] ?? '';
        $imia = AppEnv::$_p['cli_imia'] ?? '';
        $otch = AppEnv::$_p['cli_otch'] ?? '';
        $birth_d = AppEnv::$_p['birth_d'] ?? '';
        $birth_m = AppEnv::$_p['birth_m'] ?? '';
        $birth_y = AppEnv::$_p['birth_y'] ?? '';
        $sex = AppEnv::$_p['cli_sex'] ?? '';
        $bindmode = AppEnv::$_p['bindmode'] ?? '';
        if(empty($fam) && empty($imia) && empty($otch) && empty($birth_d) && empty($birth_m) && empty($birth_y))
            exit('1'. AjaxResponse::showError('Заведите данные для поиска!'));
        # TODO: расширить userid до поиска по дочкам-подразд, агентам)
        $myId = AppEnv::getUserId();
        $wcond = [ "userid='$myId'"];
        if(!empty($fam)) $wcond[] = "fam LIKE '$fam%'";
        if(!empty($imia)) $wcond[] = "imia LIKE '$imia%'";
        if(!empty($otch)) $wcond[] = "otch LIKE '$otch%'";
        if(!empty($sex)) $wcond[] = "sex='$sex'";

        # набираю гибкий поиск по элементам даты
        $dtFmt = $dtVal = [];
        if(!empty($birth_d)) { $dtFmt[] = '%d'; $dtVal[] = $birth_d; }
        if(!empty($birth_m)) { $dtFmt[] = '%m'; $dtVal[] = $birth_m; }
        if(!empty($birth_y)) { $dtFmt[] = '%Y'; $dtVal[] = $birth_y; }
        if(count($dtFmt)) {
            $strFormat = implode('.', $dtFmt);
            $strDate = implode('.', $dtVal);
            $wcond[] = "DATE_FORMAT(birth,'$strFormat')='$strDate'";
        }
        $cliData = AppEnv::$db->select(PM::T_CLIENTS, ['where'=>$wcond,
          'fields'=>'id,fam,imia,otch,birth',
          'orderby'=>'id desc'
        ]);
        # Только если не нашел в клиентах, ищу в полисах
        if(!count($cliData) && self::$seekInPolicies) { # добавляю людей из полисов агента
            $wcondPlc = $wcond;
            $wcondPlc[] = "agm.stmt_id=pers.stmt_id";
            $plcData = AppEnv::$db->select(['pers'=>PM::T_INDIVIDUAL,'agm'=>PM::T_POLICIES], ['where'=>$wcondPlc,
              'fields'=>"CONCAT('p_',pers.id) id,fam,imia,otch,birth",
              'orderby'=>'pers.stmt_id desc'
            ]);
            if(is_array($plcData) && count($plcData)) $cliData = array_merge($cliData,$plcData);
        }
        # writeDebugInfo("found in policies:", $plcData, "\n err:", AppEnv::$db->sql_error(), "\n SQL :", AppEnv::$db->getLastQuery());
        $listBody = '';
        if(is_array($cliData) && count($cliData)) {
            $listBody = "<table id='t_foundclients' class='zebra'>";
            foreach($cliData as $row) {
                $postfix = (substr($row['id'], 0,2) === 'p_') ? ' (Полис)' : '';
                $listBody .= "<tr><td id=\"found_{$row['id']}\">$row[fam] $row[imia] $row[otch], ".to_char($row['birth'])
                  ."</td><td><input button class='btn btn-primary' onclick=\"fcli.setClient('$row[id]')\" value='Выбрать'/> $postfix</td></tr>";
            }
            $listBody .= "</table>";
        }
        else $listBody = 'Ничего не найдено';
        $response = '1'.AjaxResponse::setHtml('seek_results', $listBody)
           . AjaxResponse::show("#btn_add_client");
        exit($response);
    }
    /**
    * Ищет среди полисов агента подходящего застрахованного
    *
    * @param mixed $fam Фамилия
    * @param mixed $imia Имя
    * @param mixed $otch Отчество
    * @param mixed $birth дата рождения
    * @param mixed $userid ИД агента, если ищем не для себя
    */
    public static function findClientCandidate($fam, $imia, $otch, $birth, $userid = 0) {
        if(!$userid) $userid = AppEnv::getUserid();
        $birth = to_date($birth);
        $arCandidate = AppEnv::$db->select(PM::T_INDIVIDUAL, [
          'where' => ['fam'=>$fam, 'imia'=>$imia, 'otch'=>$otch, 'birth'=>$birth,
             "stmt_id IN(select stmt_id from alf_agreements where userid='$userid')"],
          'orderby'=>'id DESC', 'singlerow'=>1
        ]);
        if(isset($arCandidate['id']))
            unset($arCandidate['id'],$arCandidate['stmt_id'],$arCandidate['ptype'],$arCandidate['relation'],
              $arCandidate['phonepref'],$arCandidate['phonepref2'],
              $arCandidate['ogrn'],$arCandidate['kpp'],$arCandidate['pepstate'],$arCandidate['rezident_rf']);

        return $arCandidate;
    }
    /**
    * агент выбрал ФИО со своего  полиса, а не из базы клиентов
    * перегоняю этого чела в таблицу Клиенты и возвращаю ИД записи
    */
    public static function fromPlcToclient() {
        $personid = AppEnv::$_p['personid'] ?? '';
        if(substr($personid,0,2)==='p_') $personid = substr($personid,2);
        $fields = array_merge(self::$cliFields, self::$dateFields);
        $cliData = AppEnv::$db->select(PM::T_INDIVIDUAL, ['fields'=>$fields, 'where'=>['id'=>$personid], 'singlerow'=>1]);
        if(count($cliData)) {
            $cliData['userid'] = AppEnv::getUserId();
            $cliData['deptid'] = AppEnv::getUserDept();
            $clientId = AppEnv::$db->insert(PM::T_CLIENTS, $cliData);
        }
        else $clientId = 0;
        if(!$clientId) $response = 'Ошибка копирования данных из полиса!';
        else $response = "1|$clientId";
        # writeDebugInfo("new client:[$clientId]");
        exit($response);
        # exit('fromPlcToclient: ' . $personid . ':'.count($cliData) );
    }

    # внутренний вызов - поиск клиентов по переданным ФИО/д.р.
    public static function seekClients($fio='', $birth='', $userid = FALSE) {
        if(empty($fio) && !empty(AppEnv::$_p['client_fio'])) $fio = AppEnv::$_p['client_fio'];
        if(empty($birth) && !empty(AppEnv::$_p['client_birth'])) $birth = AppEnv::$_p['client_birth'];
        $nameParts = preg_split("/[ \s]/",$fio,-1,PREG_SPLIT_NO_EMPTY);
        if(empty($nameParts[0])) return '';
        $myId = ($userid > 0) ? $userid : AppEnv::getUserId();
        $wcond = [
           "pl.userid='$myId' AND pers.stmt_id=pl.stmt_id", # связка таблиц - брать только страхователей из полисов агента
           "pers.fam LIKE '$nameParts[0]%'"
        ];

        if(!empty($nameParts[1])) $wcond[] = [ "pers.imia LIKE '$nameParts[1]%'" ];
        if(!empty($nameParts[2])) $wcond[] = [ "pers.otch LIKE '$nameParts[2]%'" ];
        if(PlcUtils::isDateValue($birth)) {
            $birthYmd = to_date($birth);
            $wcond[] = "pers.birth='$birthYmd'";
        }
        $arData = AppEnv::$db->select(['pl'=>PM::T_POLICIES, 'pers'=>PM::T_INDIVIDUAL], [
          'fields'=> "pl.stmt_id, pers.*",
          'where' => $wcond,
          'orderby' => 'pl.stmt_id DESC'
        ]);
        return $arData;
    }

    /**
    * Заполняет список клиентов из базы полисов
    * (первичное заполнение)
    */
    public static function initClientList($userid = FALSE) {
        $myId = ($userid > 0) ? $userid : AppEnv::getUserId();
        $wcond = [
           "pl.userid='$myId' AND pers.stmt_id=pl.stmt_id", # связка таблиц - брать только страхователей из полисов агента
           'pl.stateid NOT IN(9,10)', # отмененные полисы в игнор!
        ];
        if(self::$initOldDays > 0)
            $wcond[] = "(TO_DAYS(pl.created)+".self::$initOldDays . ") > TO_DAYS(CURRENT_DATE)";

        $arData = AppEnv::$db->select(['pl'=>PM::T_POLICIES, 'pers'=>PM::T_INDIVIDUAL], [
          'fields'=> "pl.stmt_id, pl.created, pl.equalinsured, pers.*",
          'where' => $wcond,
          'orderby' => 'fam,imia,otch,birth' #  сортирую по ФИО клиента
        ]);
        # return AppEnv::$db->getLastQuery();

        if(!is_array($arData) || !count($arData)) return 0;
        $added = 0;
        $curFam = $curImia = $curOtch = $curBirth = '';
        $myDept = OrgUnits::getUserDept($myId);
        foreach($arData as $row) {
            if($row['equalinsured'] ==0 && $row['ptype']!=='insd') continue; # страхователь, есть отд.Застрахованный, пропуск!
            if($row['fam'] == $curFam && $row['imia'] == $curImia && $row['otch'] == $curOtch && $row['birth'] == $curBirth)
                continue; # повтор Застрахованного

            $curFam = $row['fam'];
            $curImia = $row['imia'];
            $curOtch = $row['otch'];
            $curBirth = $row['birth'];
            unset($row['id'], $row['stmt_id'], $row['created'], $row['equalinsured'], $row['ptype'], $row['relation'], $row['rezident_rf'],$row['inopass'],
              $row['phonepref'], $row['phonepref2'],$row['pepstate'],$row['ogrn'],$row['kpp'],$row['otherdocno'],$row['adr_full'],$row['fadr_full']);
            $row['deptid'] = $myDept;
            $row['userid'] = $myId;

            # защита от повторной вставки
            $existId = AppEnv::$db->select(PM::T_CLIENTS, [ 'fields'=>'id',
              'where'=>['fam'=>$curFam,'imia'=>$curImia,'otch'=>$curOtch,'birth'=>$curBirth],
              'singlerow'=>1, 'associative'=>0 ]);
            if($existId > 0) {
                # writeDebugInfo("clients: skipped record $existId");
                continue;
            }
            $adResult = AppEnv::$db->insert(PM::T_CLIENTS, $row);
            # writeDebugInfo("added: $adResult");
            if($adResult) $added++; else return AppEnv::$db->sql_error();
            # if($added>=5) break; # debug stop
        }
        return $added;
        # return $arData;
    }
    /**
    * AJAX запрос - создание новой записи о клиенте
    *
    */
    public static function appendClient() {

        $fam = AppEnv::$_p['cli_fam'] ?? '';
        $imia = AppEnv::$_p['cli_imia'] ?? '';
        $otch = AppEnv::$_p['cli_otch'] ?? '';
        $sex = AppEnv::$_p['cli_sex'] ?? 'M';
        $birth_d = AppEnv::$_p['birth_d'] ?? '';
        $birth_m = AppEnv::$_p['birth_m'] ?? '';
        $birth_y = AppEnv::$_p['birth_y'] ?? '';
        $bindmode = AppEnv::$_p['bindmode'] ?? '';
        if(empty($fam) || empty($imia) || empty($birth_d) || empty($birth_m) || empty($birth_y))
            exit('1'. AjaxResponse::showError('Заполните Фамилию, Имя и полностью дату рождения!'));
        if(!empty(Appenv::$_p['foruser'])) $userId = intval(Appenv::$_p['foruser']);
        else $userId = AppEnv::getUserId();
        if($userId<=0) exit('1' . AjaxResponse::showError('Неверный вызов - передан некорректный ИД сотрудника'));

        $deptid = OrgUnits::getUserDept($userId);
        $birthYmd = "$birth_y-$birth_m-$birth_d";
        if(!PlcUtils::isDateValue($birthYmd)) exit('1'.AjaxResponse::showError('введены некорректные значения для даты рождения: '.$birthYmd));

        $dta = [
          'userid' => $userId,
          'deptid' => $deptid,
          'fam' => $fam,
          'imia' => $imia,
          'otch' => $otch,
          'sex' => $sex,
          'birth' => $birthYmd,
        ];
        # Ищу среди полисов агента подходяего застрахованного, и если нашёл - сразу дополню запись!
        $arPerson = self::findClientCandidate($fam,$imia,$otch,$birthYmd);
        if(!empty($arPerson['fam'])) {
            $dta = array_merge($dta, $arPerson);
            # writeDebugInfo("client new f=data from alfo: ", $dta);
        }
        # заношу в "клиента" заполненную запись, если нашелся полис на этого же застрахованного
        $result = AppEnv::$db->insert(PM::T_CLIENTS, $dta);

        if($result>0) {
            if(empty($bindmode))
                $response = AjaxResponse::doEval("fcli.clientId='$result';fcli.openProductSelect()");
            else # мини-форма в калькуляторе или agredit
                $response = AjaxResponse::doEval("fcli.setClient('$result')");
        }
        else {
            $response = AjaxResponse::showError('Ошибка при записи в БД!'); #. AppEnv::$db->sql_error());
            writeDebugInfo("adding Client SQL error ", AppEnv::$db->sql_error(),  "\n  SQL:", AppEnv::$db->getLastQuery() );
        }

        exit('1' . $response);
    }

    /**
    * получит строку данных клиента по ИД
    * @param mixed $clientId ID клиента
    * @param mixed $prefix если указан insr|insd - формирую AJAX блок для отправки в браузер (заполнмть данные на форме)
    * @param mixed $userLevel уровень прав текущего поль-ля, для определения доступа к данным клиента
    */
    public static function getClientData($clientId, $pref='', $userLevel = 100, $shortInfo=FALSE) {
        # writeDebugInfo("getClientData($clientId, pref=[$pref])");
        $fields = ($shortInfo) ? 'id,deptid,userid,fam,imia,otch,birth,sex' : '';

        $arRet = AppEnv::$db->select(PM::T_CLIENTS, ['fields'=>$fields, 'where'=>['id'=>$clientId], 'singlerow'=>1]);
        if(!isset($arRet['fam'])) {
            writeDebugInfo("ERR CALL: Данные о клиенте не найдены (id=$clientId/pref=$pref) \n  trace:", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            if(!empty($pref)) return AjaxResponse::timedNotify("Данные о клиенте не найдены ($clientId)");
            return FALSE;
        }
        $access = FALSE;
        $myDept = AppEnv::$auth->deptid;
        if($arRet['userid'] == AppEnv::getUserId() || $userLevel >= PM::LEVEL_IC_ADMIN)
            $access = TRUE;
        elseif($userLevel >= PM::LEVEL_MANAGER) {
            $myDepts = OrgUnits::getDeptChildren($myDept);
            $access = (is_array($myDepts) && in_array($arRet['deptid'], $myDepts));
        }
        if(!$access) {
            if(!empty($pref)) return AjaxResponse::timedNotify('Нет прав на работу с клиентом!');
            else return self::ERR_NO_ACCESS;
        }
        if(!empty($pref)) { # надо сформировать AJAX блок для заполнения данных на форме
            $ret = '';
            foreach(self::$cliFields as $fld) {
                if(in_array($fld, self::$dateFields)) {
                    if(PlcUtils::isDateValue($arRet[$fld])) $arRet[$fld] = to_char($arRet[$fld]);
                    else $arRet[$fld] = '';
                }
                if(!empty($arRet[$fld]) || $arRet[$fld]==='0') {
                    $ret .= AjaxResponse::setValue($pref.$fld, $arRet[$fld]);
                }
            }
        }
        else {
            $ret = $arRet;
        }
        if(self::$debug) writeDebugInfo(__FUNCTION__ . " returns: ", $ret);
        return $ret;
    }
    /**
    * после ввода текущих данных страхователя/Застрахованного освежаю данные в записи клиента
    *
    * @param mixed $clientid
    * @param mixed $data
    * @param mixed $newsrv
    */
    public static function updateClientData($clientid, $data='', $newsrv=FALSE) {
        if(self::$debug) writeDebugInfo("updateClientData($clientid) data: ", $data);
        if(!is_array($data)) $data = AppEnv::$_p;
        $equalInsured = $data['equalinsured'] ?? $newsrv['equalinsured'] ?? 'Y';
        $pref = ($equalInsured ==='N' || $equalInsured ==1) ? 'insr' : 'insd';
        if(self::$debug) writeDebugInfo("person to get from - $pref");

        $upd = [];
        foreach(self::$cliFields as $fld) {
            $newVal = $data[$pref.$fld] ?? '';
            if(in_array($fld, self::$dateFields)) {
                if(PlcUtils::isDateValue($newVal)) $newVal = to_date($newVal);
                else $newVal = '0';
            }
            $upd[$fld] = $newVal;
        }
        if($pref === 'insd') {
            # у застрахованного может не быть поля СНИЛС,  - избегаю затирания ранее сохраненных у Страхователя
            if(!isset($data[$pref.'snils'])) unset($upd['snils']);
            if(!isset($data[$pref.'married'])) unset($upd['married']);
        }
        if(isset($newsrv['profession']))
            $upd['profession'] = $newsrv['profession'];
        elseif(isset($data['profession']))
            $upd['profession'] = $data['profession'];

        # TODO: идея - сохранять также и income_source_work и прочие источники доходов
        # work_type, work_company, work_address work_inn work_duty work_action
        # under_bankrot deesposob_limited deesposob_limited_reason
        $updResult = AppEnv::$db->update(PM::T_CLIENTS, $upd, ['id'=>$clientid]);
        if(self::$debug) writeDebugInfo("updateClientData($clientid) result: [$updResult] ", $upd);
        return $updResult;
    }

    # Обновляю ы клиенте только заданные в массиве поля
    public static function partialUpdate($oldClientId,$arUpd) {
        $ret = $updResult = AppEnv::$db->update(PM::T_CLIENTS, $arUpd, ['id'=>$oldClientId]);
        if($err = AppEnv::$db->sql_error()) writeDebugInfo("update client ERROR: ", $err, " from data:", $arUpd);
        return $ret;
    }
    # формирует AJAX ответ для заполнения "предварительных" полей в калькуляторе
    public static function loadClientParams() {
        $clientid = AppEnv::$_p['id'] ?? 0;
        if($clientid<=0) exit("Не найден ИД клиента $id");
        $mode = AppEnv::$_p['mode'] ?? 'calc';
        $module = AppEnv::$_p['module'] ?? '';
        $prolong = AppEnv::$_p['prolong'] ?? FALSE;
        if(!$module) exit("Wrong call");
        $bkend = \AppEnv::getPluginBackend($module);

        $myLev = $bkend->getUserLevel();
        $cliData = self::getClientData($clientid, '', $myLev);
        if(is_scalar($cliData) &&  $cliData === BindClient::ERR_NO_ACCESS) {
            $ret = AjaxResponse::timedNotify("Указанный клиент - не Ваш!");
        }
        elseif(!empty($cliData['id'])) {

            $fldBirthDate = 'birthDate'; # при других именах полей, добавь варианты в свой <module>Backend::calcFieldNames()
            $fldProfession = 'profession';
            $fldSex = 'sex';
            $fldEqual = 'equalinsured';
            $fldEqualYes = '1';
            if(method_exists($bkend, 'calcFieldNames')) {
                $fldNames = $bkend->calcFieldNames();
                # writeDebugInfo("fldNames from backend: ", $fldNames);
                if(!empty($fldNames['insured_birth'])) $fldBirthDate = $fldNames['insured_birth'];
                if(!empty($fldNames['profession'])) $fldProfession = $fldNames['profession'];
                if(!empty($fldNames['insured_sex'])) $fldSex = $fldNames['insured_sex'];
                if(isset($fldNames['equalinsured'])) $fldEqual = $fldNames['equalinsured'];
                if(isset($fldNames['equalinsured_yes'])) $fldEqualYes = $fldNames['equalinsured_yes'];
            }

            $ret = AjaxResponse::setValue('clientid', $clientid) . AjaxResponse::setValue($fldBirthDate, to_char($cliData['birth']));
            if(!empty($cliData['sex'])) $ret .= AjaxResponse::setValue($fldSex,$cliData['sex']);
            if(!empty($cliData['profession'])) $ret .= AjaxResponse::setValue($fldProfession,$cliData['profession']);
            $ret .= AjaxResponse::setHtml("client_info", "Расчет для клиента: $cliData[fam] $cliData[imia] $cliData[otch], дт.рожд. ".to_char($cliData['birth']) )
              . AjaxResponse::show("#client_info_outline");

            # если не пролонгация, сразу включаю галку "стрвахователь=Застрах"
            if(!$prolong && !empty($fldEqual))
                $ret .= AjaxResponse::setValue($fldEqual, $fldEqualYes); # Раз вводим дату рожд.клиента, по умолчанию он же и Застрахованный!
        }
        else $ret = AjaxResponse::timedNotify('Указанный клиент не найден!');
        if(self::$debug) writeDebugInfo("returns: ", $ret);

        exit('1' . $ret);
    }
    # проверка, не поменял ли юзер дату рождения клиента при вводе всех ПДн
    public static function checkClientUpdates($bkObj, $clientid, $birthFldName='', $return=FALSE, $bNewAgr=FALSE, $autoUpdate=FALSE) {
        $equalInsured = \AppEnv::$_p['equalinsured'] ?? 0;
        $newBirthFld = !empty($birthFldName) ? $birthFldName : ($equalInsured ? 'insrbirth' : 'insdbirth');
        $newSexFld = substr($newBirthFld,0,-5) . 'sex';
        $birthValue = to_date(AppEnv::$_p[$newBirthFld] ?? '');
        $newSex = AppEnv::$_p[$newSexFld] ?? '';

        if(!intval($birthValue)) return; # дата рожд. неизвестна (не было на форме ввода?)
        $cliData = self::getClientData($clientid,'',100,TRUE);
        if(!isset($cliData['birth'])) return;
        # writeDebugInfo("field: $newBirthFld: $birthValue clidata[birth]: ", $cliData['birth']);
        $cliPolicies = \BindClient::policiesAmountForClient($clientid);

        if( $birthValue !== $cliData['birth'] || ($newSex!='' && $newSex !=$cliData['sex']) ) {
            $blocking = $bNewAgr ? ($cliPolicies>0) : ($cliPolicies>1);
            $goodBirth = to_char($cliData['birth']);
            if($blocking) {
                # блокируем изменение даты/пола
                if($return) return FALSE;
                exit('1'
                  . AjaxResponse::setValue($newBirthFld, $goodBirth)
                  . (($newSex!='') ? AjaxResponse::setValue($newSexFld, $cliData['sex']) : '')
                  . AjaxResponse::showError('Нельзя менять дату рождения/пол Застрахованного!<br>(возвращены к исходному значению у связанного Клиента)')
                );
            }
            elseif($autoUpdate) { # обновляю дату рожд/пол в записи клиента
                $arUpd = ['birth'=>$birthValue];
                if($newSex) $auUpd['sex'] = $newSex;
                \BindClient::partialUpdate($clientid, $arUpd);
            }
            # ."<br>$birthValue-$cliData[birth]"
        }
        return TRUE;
        # exit('1' . AjaxResponse::showMessage("TODO: compare $birthValue"));
    }
    # {upd/2025-10-31} вернёт кол-во плоисов, оформленных для указанного клиента
    public static function policiesAmountForClient($clientid) {
        $rData = \AppEnv::$db->select(PM::T_AGMTDATA, ['fields'=>'count(1) cnt','where'=>['clientid'=>$clientid],'singlerow'=>1]);
        return ($rData['cnt'] ?? 0);
    }
}
# если был тестовый (AJAX) вызов fclaction, выполняю ф-цию с указанным именем,
# при простом вызове /?p=bindclient - откроет форму поиска
if(!empty(appEnv::$_p['p']) && appEnv::$_p['p'] === 'bindclient') {
    $flaction = appEnv::$_p['fclaction'] ?? 'form';
    if(empty($flaction)) $flaction = 'form';
    if ($flaction) {
        if (method_exists('BindClient', $flaction)) BindClient::$flaction();
        else exit("FindClient: undefined call : [$flaction]");
    }
}
