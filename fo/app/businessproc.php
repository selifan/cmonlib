<?php
/**
* @package ALFO
* @name app/businessproc.php
* Класс BusinessProc для настройки разных бизнес-процессов (БП)
* (набор кнопок на форме просмотра договора, определение их доступности)
* @version 1.21.003
* modified 2025-10-17
*/
class BusinessProc {
    private static $debug = 0;
    const DAYS_FOR_PAY = 4; # сколько дней дается  на оплату полиса с момента даты "выпуска"
    const FMT_ALLBUTTONS = 'all_buttons';
    static $userButtons = [];
    static $emulAgent = FALSE; # включить для проверки режима "агента" на просмотре полиса
    private static $override_buttons = []; # кнопки ,которые "переназначили сверху"
    private static $bpTypes = [
      'std' => 'Cтандартный',
      'bank' => 'Банковский',
      'spec' => 'Специальный',
    ];
    private static $curType = 'std';
    private static $setReleaseOnPay = FALSE; # устанавливать ли дату выпуска при оплате (агенты)
    private static $_onceCalled = 0;
    private static $buttonSets = [
      'std' => ['recalc','edit','refreshdates','start_edo','start_not_edo','editagr','start_check','printstmt','set_meddeclar','set_speccond',
          'uploadstmt','uploadallscan','pay_state','set_payed','send_eqpay','printpack','print_a7','print_anketas',
          'setstate','uploaddocs','setstateformed','edo_client_letter','setstate_uwok','setstate_uwreq','setstate_uwdeny',
          'setstatecancel','setstate_annul','to_xml','checkfinmon','to_docflow','to_docflow_uw','dissolute'],

      'spec' => ['editagr','uploadallscan','set_payed','send_eqpay','setstate_uwdeny','to_docflow_uw'],
    ];
    # задание активного типа БП
    public static function setBptype($strType) {
        self::$curType = $strType;
    }
    public static function getMaxPayDate($releaseDate) {
        $releaseDate = to_date($releaseDate);
        $ret = date('Y-m-d', strtotime($releaseDate . ' +'. self::DAYS_FOR_PAY . ' days'));
        return $ret;
    }

    # переключаю режим авто-устанвоки даты выпуска в момент оплаты для агентского канала
    public static function setReleaseDateOnPay($mode) {
        self::$setReleaseOnPay = $mode;
    }
    # переназначить состояние кнопки по результатам своих операций
    public static function overrideButton($btn_id, $value) {
        self::$override_buttons[$btn_id] = $value;
    }
    # список для выбора типа БП (в select-боксе)
    public static function getBpTypes() {
        return self::$bpTypes;
    }
    # к списку станд.кнопок добавит специфические
    public static function appendButtonDefs($arDefs) {
        self::$userButtons = array_merge(self::$userButtons,$arDefs);
    }
    # вернет HTML код со всеми кнопками для текущего БП
    public static function getButtonsHtml($bptype=FALSE, $format = FALSE) {
        if(is_array($bptype)) $buttons = $bptype;
        else {
            $bp = ($bptype ? $bptype : self::$curType);
            if(!isset(self::$buttonSets[$bp])) return '';
            $buttons = self::$buttonSets[$bp];
        }
        $ret = ($format === self::FMT_ALLBUTTONS) ? [] : '';
        $allDefs = AllButtons::$buttons;

        if(count(self::$userButtons))
            $allDefs = array_merge($allDefs, self::$userButtons);

        foreach($buttons as $btid) {
            $valueid = isset($allDefs[$btid]['valueid']) ? $allDefs[$btid]['valueid'] : ('button_'.$btid);
            $titleid = isset($allDefs[$btid]['titleid']) ? $allDefs[$btid]['titleid'] : ('title_'.$btid);
            if(!empty($allDefs[$btid]['value'])) $value = $allDefs[$btid]['valueid'];
            else $value = \AppEnv::getLocalized($valueid);
            if(empty($value)) $value = $btid;
            if(!empty($allDefs[$btid]['title'])) $title = $allDefs[$btid]['title'];
            else $title = \AppEnv::getLocalized($titleid);
            $btclass = isset($allDefs[$btid]['class']) ? $allDefs[$btid]['class'] : 'btn btn-primary';
            $onclick = $allDefs[$btid]['onclick'] ?? '';
            if(!empty($allDefs[$btid]['html'])) $btnBody = $allDefs[$btid]['html'];
            else {
                $btnBody = "<input type=\"button\" class=\"$btclass\" value=\"$value\" id=\"bt_{$btid}\" onclick=\"$onclick\" "
                   . ($title ? " title=\"$title\"" : '') . ' />';
            }
            if($format === self::FMT_ALLBUTTONS) {
                $ret[$btid] = [ 'html' => $btnBody, 'display'=>0 ];
            }
            else
                $ret .= "<span id=\"btn_{$btid}\" style=\"display:none\">$btnBody></span>";
        }
        return $ret;
    }
    /**
    * формирует массив "видимости" кнопок
    * (перенос из policymodel.php:buttonsVisibility())
    * @param mixed $obj объект <module>Backend
    * @param mixed $plcdata массив данных о договоре (alf_agreements)
    */
    public static function viewButtonAttribs($obj, $plcdata, $docaccess=null) {
        if(!empty($_GET['debugbtn'])) self::$debug = intval($_GET['debugbtn']);
        /*
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $funName3 =  $trace[3]['function'] ?? '--';
        writeDebugInfo(__FUNCTION__, " call ", $funName3);
        */
        $RAZBORKI = 0; # временно вкл.отладоч вывод кошу под манагера (2)
        # writeDebugInfo("plcdata: ", $plcdata);
        if(isset($_GET['emulagent'])) self::$emulAgent = $_GET['emulagent']; # вкл.режим отладки агента
        $today = date('Y-m-d');
        if (method_exists($obj, 'beforeViewAgr')) {
            $obj->beforeViewAgr();
        }

        $repLev = $obj->getUserLevel('reports');
        $superAdmin = SuperAdminMode();

        if(!isset($plcdata['userid']) && isset($obj->_rawAgmtData['userid']))
            $plcdata =& $obj->_rawAgmtData;

        if(!isset($plcdata['stateid'])) {
            $err = "Ошибка получения данных о полисе";
            if(AppEnv::isApiCall()) return ['result'=>'ERROR','message'=>$err];
            AppEnv::echoError($err);
        }
        $draftNomer = $obj->isDraftPolicyno($plcdata['policyno']); # номер еще не выдали - блокировать выгрузку в XML и XLS

        #
        $uwBlocking = (method_exists($obj, 'uwIsBlocking') ? $obj->uwIsBlocking($plcdata) : FALSE);

        $authorId = $plcdata['createdby'] ?? $plcdata['userid'] ?? 0;
        $module = $plcdata['module'] ?? 'investprod';

        if($module === 'investprod') {
            $plfield = 'id';
            if (empty($plcdata['id'])) $plcid = $obj->_rawAgmtData['id'];
        }
        else {
            $plfield = 'stmt_id';
            if (empty($plcdata['stmt_id'])) $plcid = $obj->_rawAgmtData['stmt_id'];
        }
        $headOu = $plcdatat['headbankid'] ?? $obj->_rawAgmtData['headdeptid'] ?? 0;
        $userLevel = $obj->getUserLevel();
        if(empty($userLevel)) {
            $userLevel = AppEnv::$auth->getAccessLevel($obj->privid_editor);
        }

        $myAgmt = ($authorId==AppEnv::getUserId()) || ($userLevel>=PM::LEVEL_IC_ADMIN && $userLevel!=PM::LEVEL_UW);
        # делаю полис "своим" для всех кроме андера?

        if($RAZBORKI >= 2) { $myAgmt = 1; $userLevel = $RAZBORKI; }
        if($RAZBORKI) writeDebugInfo("myAgmt=[$myAgmt], authorId=$authorId, userid: ".AppEnv::getUserId());
        if(!isset($obj->_rawAgmtData['substate']) || !isset($obj->_rawAgmtData['datepay'])) {
            # writeDebugInfo("plcdata, ", $plcdata);
            # забираю все поля, а то в investprod приходит куцый набор
            $plcid = $plcdata[$plfield];
            # writeDebugInfo("получаю все даные по полису $module/$plcid");
            $obj->_rawAgmtData = $plcdata = PlcUtils::getPolicyData($module, $plcid);
            # writeDebugInfo("_rawAgmtData ", $obj->_rawAgmtData);
        }
        if (empty($obj->_deptCfg['id'])) {
            if($module === 'investprod') {
                $obj->_deptCfg = $obj->deptProdParams($plcdata['insuranceschemeid'],$plcdata['headbankid'],1);
            }
            else {
                $obj->deptProdParams($module,$obj->_rawAgmtData['headdeptid'], $obj->_rawAgmtData['prodcode'],
                  0, $obj->_rawAgmtData['subtypeid']);
            }
            # writeDebugInfo("deptCfg loaded from ", $plcdata);
        }

        if(!isset($obj->_deptCfg['online_confirm'])) writeDebugInfo("deptCfg not loaded! ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));

        $canUseEdo = $obj->canUseEdo();

        if(intval($canUseEdo)>=10) $edoEnabled = 10;
        else $edoEnabled = !empty($obj->_deptCfg['online_confirm']);

        # writeDebugInfo("edoEnabled=[$edoEnabled], _deptCfg[online_confirm]: ", $obj->_deptCfg); # $obj->_deptCfg['online_confirm']

        # {upd/2021-09-03} на паузе - если оказалось, что у полиса нет инв-анкеты или не заведены ИНН/СНИЛС
        $paused = $obj->paused = PlcStates::isPaused($obj, $obj->_rawAgmtData);
        # writeDebugInfo("paused: [$paused]");
        # (in_array($obj->_rawAgmtData['stateid'], [0, PM::STATE_POLICY]) && $obj->_rawAgmtData['substate']>0);

        # writeDebugInfo("user a_rights: ", AppEnv::$auth->a_rights);
        # Это мой полис или я не выше сотр.гл.офиса партнера

        $myPlcMgr = ($authorId == AppEnv::getUserId());
        # {upd/2023-11-23} даю всем кроме UW
        if($userLevel>=PM::LEVEL_MANAGER && $userLevel!= PM::LEVEL_UW) $myPlcMgr = $userLevel;

        if(self::$emulAgent >= 2)
            $userLevel = $myPlcMgr = 1; # проверка типа "под агентом"

        $pDays = AppEnv::getConfigValue('prolong_days_afterend');
        $prolongExpired = FALSE;
        if(isset($obj->agmtdata['datefrom']) && !empty($obj->agmtdata['previous_id'])) {
            $maxdate = ($pDays > 0) ? date('Y-m-d', strtotime(to_date($obj->agmtdata['datefrom']) . "+ $pDays days"))
              : to_date($obj->agmtdata['datefrom']);
            $prolongExpired = (!empty($obj->agmtdata['datefrom_max']) && $maxdate <= $today
              && $obj->agmtdata['stateid']<9 );
        }

        # writeDebugInfo("prolongExpired=[$prolongExpired], agmtdata: ", $obj->agmtdata);
        if(isset($obj->agmtdata['metatype']))
            $metatype = $obj->agmtdata['metatype'];
        else {
            $metatype = OrgUnits::getMetaType($headOu);
        }
        # если $metatype == OrgUnits::MT_BANK - свои особенности (кнопки, траектория Б-Процесса)
        /*
        if(!self::$_onceCalled) {
            self::$_onceCalled = 1;
            writeDebugInfo("meta: $metatype, _rawAgmtData: ", $obj->_rawAgmtData);
            writeDebugInfo("agmtdata: ", $obj->agmtdata);
        }
        */
        foreach ($obj->all_buttons as $key => &$btn) {
            $displ = 0;
            if (isset($btn['checkfunc'])) { # user button has own "get visibility" function
                $funcName = $btn['checkfunc'];
                if(method_exists($obj, $funcName)) {
                    $displ = $obj->$funcName($key, $obj->_rawAgmtData['stateid']);
                }
                elseif(is_callable($funcName)) {
                    $displ = call_user_func($funcName, $key, $obj->_rawAgmtData['stateid']);
                }
                else $displ = !empty($funcName);
            }
            $obj->enableBtn($key, $displ);
        }

        # {upd/2025-03-17} кнопка для ПП - проверка пройдена (вместо отправки на UW)
        $bUnpause = ( in_array($obj->agmtdata['stateid'], [PM::STATE_PAUSED, PM::STATE_DOP_CHECKING]) && $userLevel>=PM::LEVEL_IC_ADMIN );
        $obj->enableBtn('dopcheck_ok', $bUnpause);
        $obj->enableBtn('dopcheck_fail', $bUnpause);

        # {upd/2025-03-17} start_dopcheck
        $bStartUw = 0; # ($userLevel>=PM::LEVEL_IC_ADMIN && $obj->agmtdata['stateid']==PM::STATE_DOP_CHECK_DONE);
        # {upd/2025-03-20} на UW может послать только ПП после своей доп.проверки!
        $bStartDopCheck = $hardCase = 0; # ( in_array($obj->agmtdata['stateid'], [PM::STATE_PAUSED]) && $userLevel<=PM::LEVEL_MANAGER );
        $uwReasons = \UwUtils::getAllReasons($module, $obj->agmtdata['stmt_id'], TRUE);

        if($plcdata['stateid']<PM::STATE_UNDERWRITING && !empty($uwReasons)) {
            if(!empty($uwReasons['hard'])) {

                if($plcdata['stateid']==PM::STATE_PROJECT && !empty($uwReasons['light'])) $bStartDopCheck = 1; # сначала - доп-проверка ПП
                # elseif($plcdata['stateid']==PM::STATE_PAUSED) $bStartDopCheck = 1;
                elseif($plcdata['stateid']==PM::STATE_DOP_CHECK_DONE || empty($uwReasons['light'])) $bStartUw = 1;
                elseif(in_array($plcdata['stateid'], [PM::STATE_DOP_CHECK_FAIL,PM::STATE_PAUSED])) $bStartUw = 1; # проверка НЕ прошла либо только hard
                # только тяж.причины, или легкая проверка уже - кнопа UW
                if(self::$debug || $RAZBORKI) writeDebugInfo("hard UW case: bStartCheck=[$bStartDopCheck], bStartUw=[$bStartUw], uwReasons: ", $uwReasons);

            }
            elseif($plcdata['stateid']==PM::STATE_PROJECT && !empty($uwReasons['light'])) {
                $bStartDopCheck = 1; # полис с причиной на UW,но не жесткой - послать на доп-проверку
                $bStartUw = 0; # ($userLevel>=PM::LEVEL_IC_ADMIN);
                if(self::$debug) writeDebugInfo("only light check: bStartCheck=[$bStartDopCheck], bStartUw=[$bStartUw] uwReasons: ", $uwReasons);
            }
        }

        # writeDebugInfo("text hardCase = bStartUw = hardCase : $hardCase = $bStartUw = $bStartDopCheck");
        $obj->enableBtn('start_dopcheck', $bStartDopCheck);

        # кнопка выгрузки инвест-анкеты
        if(!empty($plcdata['anketaid'])) {
            $ankReady = InvestAnketa::isAnketaReady($plcdata['anketaid']);
            $b_invank = !empty($plcdata['anketaid']) && $ankReady;
            # writeDebugInfo("ankReady=[$ankReady] b_invank=[$b_invank]");
            $obj->enableBtn('invest_anketa', $b_invank);
        }
        if(is_scalar($plcdata) || !isset($plcdata[$plfield])) {
            if (isset($obj->_rawAgmtData[$plfield]))
                $plcdata = $obj->_rawAgmtData[$plfield];
            elseif(is_scalar($plcdata) && $plcdata > 0) {
                $plcdata = PlcUtils::loadPolicyData($module, $plcdata);
            }
            else {
                if ($superAdmin)
                    die("buttonsVisibility: Передан некорректный массив данных:<pre>".print_r($plcdata,1).'</pre>');
                else die('Неверные данные');
            }
        }
        $plcid = $plcdata[$plfield] ?? $plcdata['id'];
        # WriteDebugInfo("privid_editor: ", $obj->privid_editor, " level:", AppEnv::$auth->getAccessLevel($obj->privid_editor));
        # writeDebugInfo("policy data ", $obj->_rawAgmtData);
        if ($obj->_rawAgmtData['equalinsured']>0) $obj->insured = $obj->loadIndividual($plcid,'insr','');

        # анализирую, Застрахованный - резидент РФ или нет, от этого зависит возможность ЭДО!
        else $obj->insured = $obj->loadIndividual($plcid,'insd','');
        # writeDebugInfo("insured data ", $obj->insured);
        if (!isset($obj->insured['fam'])) $obj->insured = $obj->loadIndividual($plcid,'child');
        if (isset($obj->insured['rez_country']))
            $obj->rezident = PlcUtils::isRF($obj->insured['rez_country']);
        else $obj->rezident = 1;

        # writeDebugInfo("rezident: [$rezident], country : ", $obj->insured['rez_country']);
        $canaccept = (AppEnv::$auth->getAccessLevel(PM::RIGHT_ACCEPTOR) || AppEnv::$auth->getAccessLevel($obj->privid_editor)>=4);
        # WriteDebugInfo("canaccept: [$canaccept], this->enable_export=[$obj->enable_export]");
        $canexport = ($obj->enable_export && (AppEnv::$auth->getAccessLevel([PM::RIGHT_ACCEPTOR, AppEnv::RIGHT_DOCFLOW])));
        $authorId = $plcdata['$plcdata'] ?? $plcdata['createdby'] ?? 0;
        # если не передал ранее посчитанный ур.доступа, вычисляю здесь:
        if ($docaccess === null) $docaccess = $obj->checkDocumentRights($plcdata);
        # echo '$docaccess <pre>' . print_r($docaccess,1). '</pre>'; exit;
        # writeDebugInfo("docaccess: $docaccess");
        if ( ($docaccess <=0.1) && !$repLev) return; # простой зритель не может ничего, все кнопки невидимы !

        $super = ($userLevel >= 10);
        $specAdm = ($userLevel >= PM::LEVEL_IC_SPECADM); # Спец-сотрудник СК
        $admin = $obj->isAdmin();
        $SKofficer = $obj->isICOfficer();
        # writeDebugInfo("SKofficer=[$SKofficer], docaccess=[$docaccess] reports:[$repLev]");
        if(method_exists($obj, 'iAmUw')) $iAmUw = $obj->iAmUw();
        else
            $iAmUw = ($userLevel == PM::LEVEL_UW); # $obj->isUnderWriter();

        $saleSupport = (($userLevel>= PM::LEVEL_IC_ADMIN) && !$iAmUw); # строго поддержка продаж, но не андеррайтер

        # writeDebugInfo("super: [$super], userLevel: [$userLevel] iamUw:[$iAmUw]");

        $disab = ($super) ? 0 : max(AppEnv::getConfigValue($obj->module.'_disable_activity'),
            AppEnv::getConfigValue('alfo_disable_activity'));

        $editbtn_id = $obj->editagr_mode;

        $fixed = (!empty($plcdata['accepted']) || !empty($plcdata['docflowstate']));
        # writeDebugInfo("_rawAgmtData ", $obj->_rawAgmtData); # STOP HERE - неполные данные!
        $datepay = $plcdata['datepay'] ?? $obj->_rawAgmtData['datepay'];
        # echo '_rawAgmtData <pre>' . print_r($obj->_rawAgmtData,1). '</pre>'; exit;

        $isPayed = (in_array($plcdata['stateid'], [PM::STATE_PAYED, PM::STATE_FORMED]) || PlcUtils::isDateValue($datepay));

        $edoMode = in_array($obj->_rawAgmtData['bptype'], [PM::BPTYPE_EDO, PM::BPTYPE_STM]);
        $edoConfirmed = ($edoMode ? ($obj->_rawAgmtData['bpstateid']== PM::BPSTATE_EDO_OK) : FALSE);
        $expiredRel = \PlcUtils::isPolicyExpired($plcdata);
        # истекло время, когда полис можно оформить ?
        $policyExpired = (method_exists($obj, 'policyExpired') ? $obj->policyExpired(): $expiredRel);
        # истекло время д-вия калькуляции (надо заново)?
        $calcExpired = (method_exists($obj, 'policyCalcExpired') ? $obj->policyCalcExpired(): $expiredRel);

        # WriteDebugInfo("editagr_mode:",$obj->editagr_mode, "stateid:", $plcdata['stateid'] );
        # WriteDebugInfo("docaccess: ", $docaccess);
        # writeDebugInfo("buttons, plcdata: ", $plcdata);
        $uwHard = 0;
        if($module !== 'investprod') {
            $uwhard1 = $plcdata['uw_hard'] ?? 0;
            $uwhard2 = $plcdata['uw_hard2'] ?? 0;
            if(!isset($plcdata['uw_hard'])) {
                $obj->loadSpecData($plcid);
                $uwhard1 = $obj->srvcalc['uw_hard'] ?? $obj->calc['uw_hard'] ?? 0;
            }
            $uwHard = max($uwhard1, $uwhard2);
        }
        # {upd/2023-03-21} если "тяжесть" причины UW не 10, то можно еще выбрать "соотв-вие декларации" - но только в агентских!
        $bMedDeclar = (!$expiredRel && !$edoMode && $docaccess>=1.5 && $obj->enable_meddeclar && (!$iAmUw || $myPlcMgr)
          && in_array($plcdata['stateid'], [0,1,6,PM::STATE_DOP_CHECK_DONE,PM::STATE_DOP_CHECK_FAIL]) && empty($plcdata['med_declar']));
        # && empty($plcdata['med_declar'] && empty($uwReasons['hard']) # И.Яковлева сказала -давть агенту проставить соотв-е декларации даже при тяжелых причинах UW
        # writeDebugInfo("bMedDeclar=[$bMedDeclar], ", $plcdata['med_declar']);
        $obj->enableBtn('set_meddeclar', $bMedDeclar);


        # writeDebugInfo("bMedDeclar=[$bMedDeclar],iAmUw=[$iAmUw], myPlcMgr=[$myPlcMgr] uwHard=[$uwHard]");

        $bRelease = 0;

        # полис оплачен (и возможно, потом еще раз согласован с UW)
        $bPayed = (PlcUtils::isDateValue($plcdata['datepay']));
          ## && in_array($plcdata['stateid'], [PM::STATE_PAYED,PM::STATE_UWAGREED]));

        if($metatype == OrgUnits::MT_BANK) {
            $bRelease = ((in_array($plcdata['stateid'], [PM::STATE_IN_FORMING, PM::STATE_UWAGREED]))
              && in_array($plcdata['bpstateid'], [0, PM::BPSTATE_EDO_S3_OK, PM::BPSTATE_EDO_OK]));
            if($plcdata['stateid'] !=PM::STATE_UWAGREED && $plcdata['reasonid']>0)
                $bRelease = 0; # при "выпуске" полиса обнаружилось, что у него есть причины для UW
            # writeDebugInfo("BNK: release btn: [$bRelease]");
        }
        else { # у агентов теперь тоже есть "выпустить полис"

            $bRelease = ( !$expiredRel && $bPayed && in_array($plcdata['bpstateid'], [0, PM::BPSTATE_EDO_S3_OK, PM::BPSTATE_EDO_OK]));
            # writeDebugInfo("agent release = [$bRelease] (expiredRel=[$expiredRel])");
        }
        # writeDebugInfo("AGT: release btn: [$bRelease], bPayed: [$bPayed], myPlcMgr=[$myPlcMgr]");
        $bRelease = $docaccess>=1 && !$prolongExpired && !$calcExpired && !$calcExpired && $bRelease && ($myPlcMgr || $saleSupport);
        # writeDebugInfo("release now = [$bRelease] / calcExpired=[$calcExpired] myPlcMgr=[$myPlcMgr]");
        $obj->enableBtn('release_policy', $bRelease);
        # кнопа Выпустить полис - только для менеджера/операциониста. ПП и андерам не показывать

        $obj->enableBtn('setstate_uwok,setstate_uwreq,setstate_uwdeny', 0);
        # writeDebugInfo("setstate_uwok,setstate_uwreq,setstate_uwdeny to 0");

        if ( $iAmUw && $obj->enable_meddeclar==='SPEC'
            # && $plcdata['med_declar']==='N'
            && in_array($plcdata['stateid'], [PM::STATE_UNDERWRITING, PM::STATE_UWAGREED, PM::STATE_UWAGREED_CORR]))
            $obj->enableBtn('set_speccond', 1);

        if ( $specAdm) {
            # $obj->all_buttons['setstate']['display'] = 1;
            # $obj->all_buttons[$editbtn_id]['display'] = 1;
            $obj->enableBtn($editbtn_id, 1);
            # Иначе просьбы "исправить адрес" никогда не прекратятся
            if( !$iAmUw) $obj->enableBtn('setstate', 1);
        }
        else {

            if ( !in_array($plcdata['stateid'], [ PM::STATE_UNDERWRITING ]) ) {
                $editVal = ((!$fixed || in_array('FIXED', $obj->editable_states))
                  && in_array($plcdata['stateid'],$obj->editable_states)
                  && ($obj->agmt_editable) && ($disab<=1 || $disab == 1.1) && ($docaccess>=1.5));

                $obj->enableBtn($editbtn_id, $editVal);
            }
            if ($obj->enable_paymemt && !empty($obj->_rawAgmtData['datepay']) && $obj->_rawAgmtData['datepay'] > 0
             &&  !in_array('FIXED', $obj->editable_states)) {
                # $obj->all_buttons[$editbtn_id]['display'] = FALSE;
                $obj->enableBtn($editbtn_id, 0);
            }
            # WriteDebugInfo("KT-005, editable:", $obj->all_buttons[$editbtn_id]['display']);
        }
        $scans = $obj->getScanCount($plcid);
        $scansStmt = !empty($scans['stmt']) || !empty($scans['plc_zayav']);
        $scansAgmt = !empty($scans['agmt']) || !empty($scans['signed_policy']);
        # WriteDebugInfo("scans = ", $scans, "scansStmt = $scansStmt, scansAgmt = $scansAgmt");

        # writeDebugInfo("policyExpired=[$policyExpired]");
        # WriteDebugInfo('plcdata:', $plcdata);
        if ($obj->separate_anketas) {
            $obj->enableBtn('print_anketas', 1);
        }

        # writeDebugInfo("1: iAmUw=[$iAmUw] expiredRel=[$expiredRel] userLevel=$userLevel a_rights:", AppEnv::$auth->a_rights, ", operright priv_id: ", $obj->privid_editor);

        if($iAmUw && !$expiredRel) {
            if ( in_array($plcdata['stateid'], [PM::STATE_UNDERWRITING, PM::STATE_UW_DATA_REQUIRED])  ) { # кнопки для андеррайтера
                # $obj->all_buttons['setstate_uwok']['display'] = TRUE;  $obj->all_buttons['setstate_uwreq']['display'] = TRUE;
                $obj->enableBtn('setstate_uwok,setstate_uwdeny', 1); # setstate_uwreq - временно убрал (в ФТ не описан)
            }
            elseif ( $plcdata['stateid'] == PM::STATE_UW_DATA_REQUIRED ) {
                $obj->enableBtn('setstate_uwok,setstate_uwdeny', 1);
            }
        }
        else $obj->enableBtn('setstate_uwok,setstate_uwreq,setstate_uwdeny', 0);

        $agmt_state = $plcdata['stateid'];
        if($agmt_state == PM::STATE_UWAGREED) $reasonid = 0;
        else $reasonid = $plcdata['reasonid'];

        $payed = (intval($datepay)>0 || $plcdata['stateid']== PM::STATE_PAYED); # Отметка об оплате проставлена  (дата оплаты)
        if($metatype == OrgUnits::MT_BANK) {
            $canPay = ($docaccess>=1) && (($userLevel<=PM::LEVEL_CENTROFFICE || $myAgmt) && $obj->enable_paymemt && !$payed
              && in_array($agmt_state, [ PM::STATE_IN_FORMING, PM::STATE_UWAGREED, PM::STATE_UWAGREED_CORR ])
              && $plcdata['bpstateid'] == PM::BPSTATE_RELEASED
              ); # кнопки оплаты ПОСЛЕ того как нажали "выпустить полис"
              if(self::$debug) writeDebugInfo("Bank: canPay=[$canPay], docaccess=[$docaccess], myAgmt=$myAgmt"
               . " userLevel=[$userLevel], stateid=$agmt_state, bpstateid=$plcdata[bpstateid] payed=[$payed]");
        }
        else {
            $canPay = ($docaccess>=1) && !$bRelease && (!$iAmUw && $obj->enable_paymemt && !$payed
              && in_array($agmt_state, [ PM::STATE_IN_FORMING, PM::STATE_UWAGREED, PM::STATE_UWAGREED_CORR ]));

            if(self::$debug) writeDebugInfo("AGENTS: canPay=[$canPay], docaccess=[$docaccess], bRelease=[$bRelease]"
               . "userLevel=[$userLevel], stateid=$agmt_state, bpstateid=$plcdata[bpstateid] payed=[$payed]");
        }
        if($agmt_state == PM::STATE_IN_FORMING && !empty($plcdata['reasonid']))
            $canPay = 0; # кейс когда проставили reasonid ПОСЛЕ отправки на след.этап (включили UW позднее, например по повыш.АВ)

        if(self::$debug) writeDebugInfo("b_stmt_exist=[$obj->b_stmt_exist], b_stmt_print=[$obj->b_stmt_print] state=$agmt_state");
        $bPrintable = ($agmt_state >=0 || in_array($agmt_state,[PM::STATE_DOP_CHECKING,PM::STATE_DOP_CHECK_DONE]));

        $bPrintStmt = ($obj->b_stmt_exist || $obj->b_stmt_print) && $bPrintable; # PM::STATE_DRAFT - не печатать!
        $obj->enableBtn('printstmt', $bPrintStmt);

        # еще не оплачен?
        # writeDebugInfo("myAgmt=[$myAgmt], canPay=[$canPay], policyExpired=[$policyExpired], calcExpired=[$calcExpired]");
        # $RAZBORKI = 1;
        if($plcdata['stateid']<9 && !PlcUtils::isDateValue($plcdata['datepay'])) {
            # $acqPayWait: TRUE если есть ждущий оплаты онлайн ордер
            $payState = 0;
            if($obj->eq_payment_enabled && !empty($obj->online_payBy))
                $acqPayWait = Acquiring::hasWaitingOrder($module, $plcid);
            else $acqPayWait = FALSE;

            if( $acqPayWait) {
                # $obj->enableBtn('check_eqpay', !AppEnv::isProdEnv()); # пока только на тесте
                $obj->enableBtn('check_eqpay,revoke_eqpay', 1); # активно везде!
                # {upd/2024-02-21} Могли отправить на онлайн-оплату, а клиент оплатил на сайцте самю нАдо просто зарег.факт оплаты
                # $obj->enableBtn('set_payed',1);
            }
            else {
                $obj->enableBtn('check_eqpay,revoke_eqpay', 0);

                $canPay = 1;
                if($plcdata['stateid']<=PM::STATE_PROJECT) $canPay = 0; # сначала надо отправить на след.этап

                if($plcdata['metatype'] == OrgUnits::MT_BANK) { # {upd/2024-05-03} bug Fix разрешал кнопку Оплата у еще невыпущенного полиса(банк)
                    if(!in_array($plcdata['stateid'],[PM::STATE_IN_FORMING,PM::STATE_UWAGREED]) || $plcdata['bpstateid'] != PM::BPSTATE_RELEASED)
                        $canPay = 0; #  в банке - сначала выпуск полиса, потом - оплата!
                        if($RAZBORKI) writeDebugInfo("reset canPay!");
                }
                if($plcdata['stateid'] == PM::STATE_UNDERWRITING)
                    $canPay = 0;
                # $plcdata['metatype'] == OrgUnits::MT_BANK &&
                # writeDebugInfo("bRelease=[$bRelease] canPay=[$canPay] metatype: ".$plcdata['metatype']);

                # особый кейс - согласован UW, проосрочена МДВ
                if($plcdata['stateid'] == PM::STATE_UWAGREED && $expiredRel) {
                    $canPay = 0; # writeDebugInfo("STATE_UWAGREED and expiredRel!");
                    if($plcdata['metatype'] != OrgUnits::MT_BANK && empty($plcdata['bpstateid'])) {
                        # Не банк (т.е. агенты и проч) - включаю кнопку "стстус оплаты" - еслм еще не проставили НЕоплату (bpstateid=PM::BPSTATE_NOTPAYED
                        $payState = 1;
                        # writeDebugInfo("payState=1, plcdata: ", $plcdata);
                    }
                    # повторно отправили на согласование клиентом после UW-согласования

                }
                if($plcdata['stateid'] == PM::STATE_UWAGREED && in_array($plcdata['bpstateid'],[50,51,52,60,62,62,63,70,72])) {
                    $canPay = 0;
                    if($RAZBORKI) writeDebugInfo("setpayed=0, клиент не выполнил повт.согласование после UW-согласования");
                }

                if($RAZBORKI) writeDebugInfo("send_eqpay: canPay=[$canPay], policyExpired=[$policyExpired], expiredRel=[$expiredRel] stateid:$plcdata[stateid]"
                 . ", eq_payment_enabled:[$obj->eq_payment_enabled] bpstateid: $plcdata[bpstateid]");

                $obj->enableBtn('set_payed',$canPay); # {upd/2023-04-20} ввод оплаты разрешаю даже у просроченной МДВ (А.Загайнова

                $obj->enableBtn('pay_state',$payState); # {upd/2023-04-20} ввод оплаты разрешаю даже у просроченной МДВ (А.Загайнова

                if ($canPay && !$policyExpired && !$calcExpired ) { # кнопки рег.оплаты и онлайн-оплаты
                    # writeDebugInfo("set_payed to 1");
                    if (is_scalar($obj->eq_payment_enabled)) $eq_but = $obj->eq_payment_enabled;
                    elseif (is_array($obj->eq_payment_enabled)) $eq_but = AppEnv::$auth->getAccessLevel($obj->eq_payment_enabled);
                    #$eqAv = \Acquiring::isPaymentsAvailable($obj->online_payBy);
                    # if($plcdata['datefrom']<=$today) $eq_but = FALSE;
                    # writeDebugInfo("eq_but=[$eq_but], eqAv=[$eqAv]($obj->online_payBy)");
                    if($eq_but && \Acquiring::isPaymentsAvailable($obj->online_payBy)) {
                        $obj->enableBtn('send_eqpay', 1);
                        # writeDebugInfo("send_eqpay to 1");
                    }
                }
            }
        }
        else {
            $obj->enableBtn('set_payed,send_eqpay,check_eqpay,revoke_eqpay',0);
            # if(self::$debug) writeDebugInfo("set_payed,send_eqpay,check_eqpay to 0");
        }

        # writeDebugInfo("rawdata: ", $obj->_rawAgmtData);
        $metaType = $plcdata['metatype'] ?? OrgUnits::getMetaType($headOu);
        # writeDebugInfo("headOu=$headOu, metatype = [$metaType]");
        if($metaType == OrgUnits::MT_BANK) # В банке онлайн оплаты не бывает!
            $obj->enableBtn('send_eqpay,check_eqpay,revoke_eqpay',0);

        if ($obj->recalculable) {
            # WriteDebugInfo("recalculable: check iAmUw=[$iAmUw], agmt_state = [$agmt_state]");
            # {upd/2021-09-08} Даю простому оперу пересчитать калькуляцию
            $bRecalc = 0;
            if($iAmUw) {
                $bRecalc = (in_array($agmt_state, [0, PM::STATE_DRAFT, PM::STATE_POLICY, PM::STATE_UNDERWRITING, PM::STATE_UWAGREED, PM::STATE_PAYED])
                  && in_array($plcdata['bpstateid'], [0, PM::BPSTATE_PDN_OK, PM::BPSTATE_PDN_NO,PM::BPSTATE_EDO_OK,PM::BPSTATE_EDO_NO, PM::BPSTATE_UWREWORK])
                );
            }
            else {
                $bRecalc = ($docaccess>=1 && in_array($agmt_state, [PM::STATE_DRAFT, PM::STATE_POLICY, PM::STATE_PROJECT])
                  && empty($plcdata['bpstateid'])
                );

            }
            # writeDebugInfo($plcdata['policyno'] . "state=[$agmt_state], recalc: [$bRecalc]");
            $obj->enableBtn('recalc', $bRecalc);
        }
        else { # кнопка "Обновить данные" при истекшей мкакс.дате выпуска
            # writeDebugInfo("not recalculable!");
            $bRefreshDates = 0;
            if($expiredRel) $bRefreshDates = method_exists($obj, 'refreshDates');
            if(!$iAmUw && $plcdata['stateid'] == PM::STATE_UWAGREED && $plcdata['bpstateid']==PM::BPSTATE_UWREWORK)
                $bRefreshDates = 0; # агент не может "обновить даты", полис в статуск соглю сUW/на доработке UW
            $obj->enableBtn('refreshdates', $bRefreshDates);
        }
        # {upd/2022-12-22} - андерратер может войти в редактирование, когда полис на андеррайтинге
        if($iAmUw && !in_array(PM::STATE_UNDERWRITING, $obj->editable_states))
            $obj->editable_states[] = PM::STATE_UNDERWRITING;

        # {upd/2023-02-27} сотрудник стр.компании может править договор пока он не оформлен
        if($SKofficer && !in_array(PM::STATE_UNDERWRITING, $obj->editable_states))
            $obj->editable_states[] = PM::STATE_UNDERWRITING;

        # {upd/2024-10-09} кнопка Авто-платежи...
        $autoPay = \AutoPayments::getAutoPayState($module, $plcdata['stmt_id']);
        if($SKofficer && class_exists('autopayments') && \AppEnv::AutoPaymentActive() && $autoPay!==FALSE ) {
            $obj->enableBtn('autopayments', 1);
        }
        else $obj->enableBtn('autopayments', 0);

        # {updt/2025-09-25} при нахождении на доработке позволяю исправить данные, чтоб потом отправить на повторное соглас-е
        if( $userLevel>=PM::LEVEL_IC_ADMIN && $agmt_state==PM::STATE_FORMED
          && in_array($plcdata['substate'], [PM::SUBSTATE_REWORK, PM::SUBSTATE_COMPLIANCE]) ) {
            $bEdit = 1;
        }
        else
            $bEdit = ($docaccess>=1 && $obj->agmt_editable && in_array($agmt_state, $obj->editable_states) &&
              ( in_array($plcdata['bpstateid'], [0, PM::BPSTATE_EDO_NO]) ) || $userLevel>10 ); # PM::STATE_IN_FORMING

        if($bEdit ) { # && !$iAmUw
            if($obj->editagr_mode === 'stmt') {
                $obj->enableBtn('edit', 1); $obj->enableBtn('editagr', 0);
            }
            else {
                $obj->enableBtn('edit', 0); $obj->enableBtn('editagr', 1);
            }
        }
        else $obj->enableBtn('edit,editagr', 0);

        # writeDebugInfo("enable_export=[ $obj->enable_export], b_generate_xml=[$obj->b_generate_xml]");
        if ( $obj->enable_export && !$draftNomer && !$expiredRel
          && $userLevel>=PM::LEVEL_IC_ADMIN && $obj->b_generate_xml && !$iAmUw && $agmt_state != PM::STATE_DRAFT) {
            # $obj->all_buttons['to_xml']['display'] = true;
            $obj->enableBtn('to_xml', 1);
        }

        # {upd/2020-01-27}: кнопа выгрузки в СЭД доступна только у оформленных полисов
        $btnToUw = $btnTodocflow = 0;
        if ( class_exists('SEDExport') && !$obj->paused && empty($plcdata['docflowstate'])) {
            # WriteDebugInfo('rawdata:', $obj->_rawAgmtData);
            # TODO: если карточка уже создана, делать запрос на обновление (файлов,статуса... )?
            # $obj->_rawAgmtData['docflowstate']
            if ( $plcdata['stateid'] == PM::STATE_FORMED && $userLevel>9) {
                $btnTodocflow = !$prolongExpired;
            }
            if ($userLevel>=PM::LEVEL_IC_ADMIN && in_array($plcdata['stateid'], [PM::STATE_UNDERWRITING]) ) { # PM::STATE_FORMED - deleted!
                # $obj->all_buttons['to_docflow_uw']['display'] = $obj->enable_underwriting;
                $btnToUw = !$prolongExpired && !$iAmUw; # {upd/2022-11-16} андер сам отправить в СЭД не может? (по ФТ так!)
            }
        }
        # writeDebugInfo("userlev: $userLevel, iamUw:[$iAmUw], btntoUw: [$btnToUw] prolongExpired=[$prolongExpired], stateid:".$obj->_rawAgmtData['stateid']);

        $obj->enableBtn('to_docflow_uw', $btnToUw );
        $obj->enableBtn('to_docflow', $btnTodocflow);

        if (($canexport || $super) && isset(AppEnv::$_plugins['finmonitor']) && $agmt_state>=0) {
            # $obj->all_buttons['checkfinmon']['display'] = true;
            $obj->enableBtn('checkfinmon', 1);
        }

        $canCancel = TRUE;
        if(method_exists($obj, 'getStopCoolDate'))
            $coolDate = $obj->getStopCoolDate();
        else
            $coolDate = AddToDate($plcdata['datefrom'], 0,0, PM::DAYS_COOL);
        if(!in_array($agmt_state, [PM::STATE_DRAFT, PM::STATE_PROJECT, PM::STATE_IN_FORMING, PM::STATE_UWAGREED, PM::STATE_POLICY])) {
            $canCancel = ($today < $coolDate) || ($userLevel>=PM::LEVEL_IC_ADMIN);
        }
        # writeDebugInfo("canCancel=[$canCancel] = ($today < $coolDate)");
        $canDissolute = ($today >= $coolDate);

        if(in_array($agmt_state, [PM::STATE_ANNUL, PM::STATE_CANCELED, PM::STATE_DISSOLUTED, PM::STATE_BLOCKED]))
            $canCancel = FALSE;

        # $obj->all_buttons['printstmt']['display']  = ($obj->b_stmt_exist || $obj->b_stmt_print);
        $obj->enableBtn( 'get_finplan', !empty($obj->bFinPlan) );
        # $obj->all_buttons['uploadstmt']['display'] = ($obj->b_stmt_exist);
        $obj->enableBtn( 'uploadstmt', $obj->b_stmt_exist );

        if ( !$iAmUw ) { # $obj->all_buttons['setstatecancel']['display'] = ($docaccess >=1.5);
            $obj->enableBtn( 'setstatecancel', ($docaccess >=1 && $canCancel) );
        }

        # WriteDebugInfo('stateid:', $plcdata['stateid']);
        # WriteDebugInfo("this->uploadScanMode=", $obj->uploadScanMode);
        # {upf/2023-12-18} - повышенное АВ: extended_av
        $btExtAv = ((floor($userLevel) == PM::LEVEL_IC_ADMIN) || ($userLevel >= PM::LEVEL_SUPEROPER)) && $reasonid!=PM::UW_REASON_EXT_AV
          && in_array($plcdata['stateid'], [PM::STATE_PROJECT, PM::STATE_IN_FORMING, PM::STATE_UNDERWRITING])
          && empty($plcdata['docflowstate']) && !$expiredRel && $obj->enableExtAv;

        $obj->enableBtn('extended_av', $btExtAv);
        # отправить на UW - при наличии причин UW (операционист/агент) - либо если оплату полиса профукали, и надо снова отправлять на UW
        $bStartUw = $bStartUw && (in_array($agmt_state, [PM::STATE_PROJECT, PM::STATE_DOP_CHECK_DONE,PM::STATE_DOP_CHECK_FAIL, PM::STATE_PAUSED]))
          && (!empty($plcdata['med_declar']||$canUseEdo>=10) );
        if($RAZBORKI) writeDebugInfo("KT-003 now bStartUw=[$bStartUw] agmt_state=$agmt_state, med_declar:",$plcdata['med_declar']);
        if($agmt_state == PM::STATE_DOP_CHECK_FAIL && (!empty($plcdata['med_declar'])||$canUseEdo>=10)) $bStartUw = TRUE;
        /*
        $bStartUw = $bStartUw && !$paused && !$prolongExpired && !$calcExpired && !$bMedDeclar && !$policyExpired
          && (in_array($reasonid, PM::$noDeclarReasons) || $plcdata['med_declar']==='N')
          && (in_array($plcdata['stateid'],[0,PM::STATE_POLICY,PM::STATE_PAUSED, PM::STATE_DOP_CHECKING])
          && $docaccess >=1 && ($userLevel<=PM::LEVEL_CENTROFFICE || $myAgmt || $saleSupport) );
        */
        # TODO: если протухла макс.дата д-вия, снова делать доступной отправку на UW!
        # writeDebugInfo("bStartUw=[$bStartUw, bMedDeclar=[$bMedDeclar], paused=[$paused], reasonid=[$reasonid], ", ' policyCalcExpired:[',$obj->policyCalcExpired(), '] stateid=',$plcdata['stateid'], ' $docaccess:',$docaccess);
        # if($plcdata['stateid'] == PM::STATE_IN_FORMING) $bStartUw = 0; # этап пройден, UW уже не нужен (проверка пройдена)
        if(!empty($_GET['btndebug'])) # что за хрень с кнопками?
            exit("bMedDeclar=[$bMedDeclar], paused=[$paused] invest_anketa=[".$obj->invest_anketa
              . "], reasonid=[$reasonid],  prolongExpired=[$prolongExpired], "
              . "policyExpired=[$policyExpired], policyCalcExpired:[".$obj->policyCalcExpired()
              ."] stateid=$plcdata[stateid], <br>docaccess: [$docaccess]  myAgmt=[$myAgmt] saleSupport=[$saleSupport], bStartUw=[$bStartUw]");

        if($uwBlocking) $bStartUw = 0; # продукт не подразумевает класс.андеррайтинг (коробки - Амулет...)
        # else $bStartUw = ($bStartUw || $bUnpause); # если для ПП доступна кнопка "проверка пройдена", то им же открыть кнопку "На UW"
        $obj->enableBtn('start_uw', $bStartUw);
        # writeDebugInfo(__FUNCTION__, "/start_uw:[$startUw]", ' stateid: ', $plcdata['stateid']);

        $bPack = $bPrintable; # черновик - печатать нечего
        $obj->enableBtn( 'printpack', $bPack); # && (($scansStmt>0 || !$obj->b_stmt_exist))) );
        # writeDebugInfo("printpack = [$bPack]");
        if(!in_array($plcdata['stateid'],[PM::STATE_DISSOLUTED,PM::STATE_BLOCKED])) {
            # загрузка сканов - всегда кроме расторженных/блокированных дог.
            if ($obj->uploadScanMode > 0) {
                $obj->enableBtn( 'uploadallscan',1);
            }
            else {
                if ($super) # $obj->all_buttons['uploaddocs']['display'] = true;
                    $obj->enableBtn('uploaddocs',1);
                else $obj->enableBtn('uploaddocs',
                    (($payed || !empty($obj->enable_paymemt)) &&
                    ($scansStmt>0 || !$obj->b_stmt_exist) && ($obj->enable_agmtscans)));
            }
        }
        switch($agmt_state) { # статус договора
            case PM::STATE_PROJECT:
            case PM::STATE_IN_FORMING:
            case PM::STATE_POLICY:
            case PM::STATE_UWAGREED:
            case PM::STATE_UW_DATA_REQUIRED:
            case PM::STATE_PAYED:
            # Полис в начальном состоянии (Полис) / оплачен / согласован с андерр.
                # TODO: определиться, разрешать ли "оплату" полисов при блокировке редактир-я (либо добавить 3-ю степень блокировки)
                if ( $obj->paused || $disab > 1.1 ) {
                    $obj->enableBtn('set_payed,setstateformed',0);
                    # writeDebugInfo("disabled set_payed,setstateformed !  disab=$disab, paused=[$obj->paused]");
                }
                # $obj->enableBtn('printpack', $bPack);
                break;

            case PM::STATE_UNDERWRITING:
            case PM::STATE_UW_DATA_REQUIRED:

                # $obj->all_buttons['printstmt']['display'] = $obj->b_stmt_exist;
                $obj->enableBtn('printpack', ($obj->enable_printpackUw || $iAmUw));
                # $obj->all_buttons['printpack']['display'] = $obj->enable_printpackUw;
                break;

            case 9: case PM::STATE_CANCELED: # 9,10 отменен, аннулирован
                # WriteDebugInfo('ветка stateid=10');
                break;

            case PM::STATE_FORMED: # 11 = оформлен
                # WriteDebugInfo('ветка stateid=11');
                # $obj->all_buttons['printpack']['display'] = 1;
                $obj->enableBtn('printpack',1);
                if (!empty($obj->enable_print_a7))
                    # $obj->all_buttons['print_a7']['display'] = 1;
                    $obj->enableBtn('print_a7',1);

                if ($obj->uploadScanMode > 0)
                    # $obj->all_buttons['uploadallscan']['display'] = 1;
                    $obj->enableBtn('uploadallscan',1);
                else
                    # $obj->all_buttons['uploaddocs']['display'] = $obj->enable_agmtscans;
                    $obj->enableBtn('uploaddocs',$obj->enable_agmtscans);
                # WriteDebugInfo('enable_agmtscans = ['.$obj->enable_agmtscans . ']');
                if (constant('ENABLE_PACK_EXPORT')) {
                    if (($canaccept) && !$plcdata['export_pkt']) {
                        $obj->enableBtn('set_accepted',($plcdata['accepted']==0));
                        $obj->enableBtn('set_unaccepted',($plcdata['accepted']==1));
                        # $obj->all_buttons['set_accepted']['display'] = ($plcdata['accepted']==0);
                        # $obj->all_buttons['set_unaccepted']['display'] = ($plcdata['accepted']==1);
                    }
                }
                else {
                    # 05.2018 - режим пакетной выгрузки выключен, кнопка "акцептовать" используется для простановки статуса Принято СК
                    # 05.2019 - заблокировал, незачем ?(если вдркг понадобится, задать в alfo_core.php 10
                    if ( (constant('ENABLE_PACK_EXPORT') == 10) && $canaccept || AppEnv::docFlowAccess() ) {
                        # $obj->all_buttons['set_accepted']['display'] = ($plcdata['accepted']==0);
                        # $obj->all_buttons['set_unaccepted']['display'] = ($plcdata['accepted']==1);
                        $obj->enableBtn('set_accepted',($plcdata['accepted']==0));
                        $obj->enableBtn('set_unaccepted',($plcdata['accepted']==1));

                    }
                }
                if (AppEnv::$auth->getAccessLevel('payonline') == 1 && !$canaccept) # у агента онлайн-оплаты никакой "акцептовать" !
                    $obj->all_buttons['set_accepted']['display'] = $obj->all_buttons['set_unaccepted']['display'] = 0;
                    $obj->enableBtn('set_accepted,set_unaccepted',0);
                break;
        }
        # echo '$obj->enable_agmtscans <pre>' . print_r($obj->all_buttons['uploaddocs'],1). '</pre>'; exit;

        if (!$obj->enable_setformed) {
            $obj->enableBtn('setstateformed',0);
        }

        if (!$obj->b_stmt_exist && !$obj->b_stmt_print) {
            # $obj->all_buttons['printstmt']['display'] = 0; $obj->all_buttons['uploadstmt']['display'] = 0;
            $obj->enableBtn('printstmt,uploadstmt',0);

        }
        if ($obj->uploadScanMode > 0)
            $obj->enableBtn('uploadstmt',0);

        if (!$obj->enable_agmtscans || $docaccess < 1.5) { # Сканы договоров грузить НЕ давать!
            # $obj->all_buttons['uploaddocs']['display'] = 0; $obj->all_buttons['uploadstmt']['display'] = 0;
            # $obj->all_buttons['uploadallscan']['display'] = 0;
            $obj->enableBtn('uploaddocs,uploadstmt,uploadallscan',0);

        }
        # статус заявления Оформлен, НО полис аннулирован - давал загружать файлы
        if( in_array($plcdata['stateid'], [PM::STATE_ANNUL,PM::STATE_CANCELED]) ) {
            # $obj->all_buttons['uploaddocs']['display'] = 0; $obj->all_buttons['uploadstmt']['display'] = 0;
            # $obj->all_buttons['uploadallscan']['display'] = 0;
            $obj->enableBtn('uploaddocs,uploadstmt,uploadallscan',0);
        }

        if ( in_array($agmt_state, [PM::STATE_ANNUL,PM::STATE_CANCELED, PM::STATE_DISSOLUTED,PM::STATE_BLOCKED]))
            $obj->enableBtn('setstatecancel',0);
            # $obj->all_buttons['setstatecancel']['display'] = 0;

        # $obj->enableBtn('prolongate',0);

        if ( $disab<2 && $agmt_state == PM::STATE_FORMED && $userLevel>=PM::LEVEL_IC_ADMIN && !$iAmUw && $canDissolute) {
            # $obj->all_buttons['dissolute']['display'] = true;
            $obj->enableBtn('dissolute', 1);
        }
        if ( ($saleSupport) ) {
            $obj->enableBtn('setstate_annul', $canCancel); # андеррайтер кнопку Отказ не видит (ФТ)
            $obj->enableBtn('setstatecancel',0);
        }
        if ($agmt_state == PM::STATE_BLOCKED && !$super) {
            $obj->enableBtn('editagr,printstmt,uploadstmt,uploadallscan,set_payed,send_eqpay,set_meddeclar,printpack',0);
            $obj->enableBtn('uploaddocs,setstateformed,setstatecancel,setstate_annul,to_docflow,to_docflow_uw',0);
            # writeDebugInfo("set_meddeclar to 0");
        }

        if ($obj->peps_check_auto && !$super) { # при авто-проверке по спискам отдельная кнопка PEPs не нужна
            # $obj->all_buttons['checkfinmon']['display'] = false;
            $obj->enableBtn('checkfinmon',0);
        }
        if ($iAmUw || $admin) {
            $obj->enableBtn('printpack',1);
        }
        if($agmt_state == PM::STATE_FORMED) {
            # writeDebugInfo("Rework::getButtonAttribs call...");
            Rework::getButtonAttribs($obj->all_buttons,$module,$plcdata);
        }
        else { # все кнопы доработки прячем
            $obj->enableBtn('rework_set,rework_setdone,rework_letter,rework_clear',0);
        }
        if ($obj->enable_meddeclar && empty($plcdata['med_declar']) && !$edoConfirmed && $plcdata['stateid']!=PM::STATE_UWAGREED) {
            $obj->enableBtn('set_payed,send_eqpay,setstateformed',0);
            # writeDebugInfo("set oplata =0 of meddeclar");
        }

        $canStartEdo = FALSE;
        $insurerType = $plcdata['insurer_type'] ?? $obj->getInsurerType($plcdata);
        $isFL = ($insurerType == 1); # {upd/2021-03-22} ЭДО только если стр=ФЛ

        # writeDebugInfo("canUseEdo=$canUseEdo");
        if ( $edoEnabled && $docaccess>=1 && $canUseEdo) {
            # writeDebugInfo("edo starts, uwReasons: ", $uwReasons);
            if(intval($canUseEdo)>=10) {
                # режим ТОЛЬКО оформление по ЭДО, даже после UW
                # writeDebugInfo("only EDO: $canUseEdo");
                if($plcdata['stateid']==PM::STATE_UWAGREED) {
                    $canStartEdo = TRUE;
                }
                elseif($plcdata['stateid']==PM::STATE_DOP_CHECK_DONE) {
                    if(empty($uwReasons['hard'])) $canStartEdo = TRUE;
                }
                elseif(in_array($plcdata['stateid'], [PM::STATE_POLICY, PM::STATE_PROJECT]) && empty($uwReasons)) {
                    $canStartEdo = TRUE;
                }
                if(intval($plcdata['bpstateid'])> 0) $canStartEdo = FALSE; # ЭДО уже стартован

                # writeDebugInfo("canUseEdo=$canUseEdo, canStartEdo=$canStartEdo");
                $obj->enableBtn('start_edo', $canStartEdo);
                $obj->enableBtn('start_not_edo', FALSE); # не ЭДО процесс невозможен
                $obj->enableBtn('set_meddeclar', FALSE); # ЭДО - мед-декоарацию руками не проставляем?

            }
            else {
                if(!empty($uwReasons['hard'])) $canStartEdo = 0;
                elseif(!empty($uwReasons['light'])) {
                    $canStartEdo = ($plcdata['stateid']==PM::STATE_DOP_CHECK_DONE);
                }
                else $canStartEdo = 1;
                if(self::$debug) writeDebugInfo("init canStartEdo=[$canStartEdo], isFl=[$isFL], stateid: $plcdata[stateid], rezident:".$obj->rezident, " myPlcMgr=[$myPlcMgr], uwReasons: ", $uwReasons);

                if(in_array($plcdata['stateid'], [0,PM::STATE_UWAGREED, PM::STATE_POLICY, PM::STATE_PROJECT,PM::STATE_DOP_CHECK_DONE])
                  && $obj->rezident && $myPlcMgr && !$expiredRel) {
                    # if ($obj->b_stmt_exist && !in_array($plcdata['stmt_stateid'], [0,PM::STATE_UWAGREED, PM::STATE_POLICY, PM::STATE_FORMED]))
                    #     $canStartEdo = 0;
                    $canStartEdo = $canStartEdo & $isFL;
                    if(intval($plcdata['bpstateid'])>0) $canStartEdo = FALSE; # ЭДО уже стартован

                    $obj->enableBtn('start_edo', $canStartEdo);
                    $obj->enableBtn('start_not_edo', $canStartEdo);
                }
                else $obj->enableBtn('start_edo,start_not_edo', 0);
                # блокировать печать полиса, пока не выбрали вариант оформления ЭДО-стд, (админ/супер-юзер и андер - могут!)
                if ($plcdata['bptype']==='' && empty($plcdata['reasonid']) && $canStartEdo
                  && !$iAmUw && !$admin && $plcdata['stateid']<PM::STATE_PAYED) {
                    $obj->enableBtn('printpack',0);
                    # writeDebugInfo("printpack disabled");
                }
            }
        }

        if ($canStartEdo) {
            $obj->enableBtn('set_payed,send_eqpay,set_meddeclar', 0);
            if($RAZBORKI) writeDebugInfo("can start EDO, set_payed,send_eqpay,set_meddeclar to 0");
            # writeDebugInfo("final canStartEdo=[$canStartEdo], isFl=[$isFL], stateid: $plcdata[stateid], rezident:".$obj->rezident, " myPlcMgr=[$myPlcMgr]");
        }

        $bReset = FALSE;
        # сброс  договора в состояние Проект (агентом)
        # writeDebugInfo("agmtdata ", $obj->agmtdata);
        # признак просроченной возможности выпустить полис
        # $date_release = $obj->agmtdata['date_release'] ?? '';
        # $date_release_max = $obj->agmtdata['date_release_max'] ?? '';
        # $releaseExpired = (\PlcUtils::isDateValue($date_release_max) && date('Y-m-d')>$date_release_max);
        $released = in_array($plcdata['bpstateid'], [PM::BPSTATE_RELEASED, PM::BPSTATE_TOACCOUNTED,PM::BPSTATE_ACCOUNTED]);

        # полис ждет согласования клиентом ?
        $waitingEdoReaction = $plcdata['bptype']==='EDO' && in_array($plcdata['bpstateid'], [PM::BPSTATE_SENTPDN, PM::BPSTATE_PDN_OK, PM::BPSTATE_SENTEDO]);

        if($userLevel >= PM::LEVEL_IC_ADMIN && $userLevel!= PM::LEVEL_UW) {
            # {upd/2023-03-07} ПП, договор в процессе андеррайтинга, сброс чтобы агент исправил данные)
            if($agmt_state == PM::STATE_PAUSED && $plcdata['bpstateid'] == PM::BPSTATE_EDO_NO) {
                $bReset = 1;
                # writeDebugInfo("ADMIN,клиент не согласовал ЭДО - 2 фаза, разрешаю сброс");
            }

            else $bReset = in_array($agmt_state,[PM::STATE_UNDERWRITING, PM::STATE_IN_FORMING, PM::STATE_DOP_CHECKING]) || $waitingEdoReaction;
            # writeDebugInfo("KT-00U bReset=[$bReset], user level=[$userLevel] waitingEdoReaction=[$waitingEdoReaction]");
        }
        elseif($docaccess>=1 && $userLevel <= PM::LEVEL_CENTROFFICE) {
            if($agmt_state == PM::STATE_PAUSED && $plcdata['bpstateid'] == PM::BPSTATE_EDO_NO) {
                $bReset = 1;
                # writeDebugInfo("USER,клиент не согласовал ЭДО - 2 фаза, разрешаю сброс");
            }
            else $bReset = $myPlcMgr && (in_array($agmt_state,[PM::STATE_IN_FORMING, PM::STATE_UNDERWRITING, PM::STATE_PAUSED,PM::STATE_DOP_CHECKING])
              || ($agmt_state == PM::STATE_PROJECT && $plcdata['med_declar']!='' || $waitingEdoReaction));

            # writeDebugInfo("KT-001 myPlcMgr=[$myPlcMgr] bReset=[$bReset] agmt_state=$agmt_state, waitingEdoReaction=[$waitingEdoReaction]");
            if(PlcUtils::isWaitingEqPayment($obj->module, $plcid, $plcdata)) {
                $bReset = 0; # после отправки ссылки на оплату сброс в нач.сост. закрыть!
                # writeDebugInfo("закрыл сброс");
            }
            elseif(($agmt_state == PM::STATE_IN_FORMING && $plcdata['bpstateid'] == PM::BPSTATE_RELEASED) || $released) {
                $bReset = 0; # банки - полис "выпущен" - откат блокируется!
            }
            # {upd/2024-01-29} если даже есть карточка СЭД, но полис еще не выпущен
            elseif( $expiredRel===2 ) $bReset = 1; # просрочен выпуск, даем начать сначала (никогда не должен срабатывать, авто-сброс на открытии карточки)
            # else # TODO: раскомментировать когда определимся что делать при станд.откате полиса с карточкой в СЭД
            if( !empty($plcdata['docflowstate']) ) $bReset = 0; # ушел в СЭД - агент уже не может сбросить в начало!
        }
        $obj->enableBtn('resetplc', $bReset);

        if ($edoMode) {
            # {upd/2024-05-28} - поправка для кнопки "отправить оригинал клиенту" для банков
            if ($plcdata['metatype'] == OrgUnits::MT_BANK) { # в банке - сразу после того как выпустили (до оплаты)
                $bEdoLetter = (in_array($plcdata['stateid'], [PM::STATE_IN_FORMING,PM::STATE_PAYED, PM::STATE_FORMED])
                  && in_array($plcdata['bpstateid'], [PM::BPSTATE_RELEASED, PM::BPSTATE_ACTIVE]) );

            }
            else # у агентов - после оплаты
                $bEdoLetter = (in_array($plcdata['stateid'], [PM::STATE_PAYED,PM::STATE_FORMED])
                  && in_array($plcdata['bpstateid'], [PM::BPSTATE_RELEASED, PM::BPSTATE_ACTIVE]));

            if($plcdata['stateid'] ==PM::STATE_FORMED && in_array($plcdata['substate'], [PM::SUBSTATE_EDO2_OK]))
                $bEdoLetter = 1; # на доработке, клиент подтвердил изменения
            # writeDebugInfo("edo client letter: [$bEdoLetter]");
            $obj->enableBtn('edo_client_letter', $bEdoLetter);
        }
        if ($obj->paused) { # нужна или инв-анкета, или ИНН/СНИЛС
            $obj->enableBtn('set_payed,send_eqpay,setstateformed,start_edo,start_not_edo,set_meddeclar', 0);
            # writeDebugInfo("set_payed,send_eqpay,setstateformed,start_edo,start_not_edo,set_meddeclar off");
        }
        # writeDebugInfo("_deptCfg ", $obj->_deptCfg);
        if (!empty($obj->_deptCfg['online_confirm']) && !in_array($plcdata['bptype'], ['',0,PM::BPTYPE_STD])
          && $agmt_state!=PM::STATE_FORMED) {
            $obj->enableBtn([$editbtn_id,'recalc'], 0); # договор на ЭДО-согласовании не редактировать не пересчитывать
            # writeDebugInfo("EDO recalc to 0 bptype: ", $plcdata['bptype']);
        }

        # next_stage
        $b_nextStage = 0;
        if($docaccess>=1) {
            if( in_array($plcdata['stateid'], [PM::STATE_PROJECT, PM::STATE_POLICY, PM::STATE_DOP_CHECK_DONE]) &&
              $obj->isEdoPolicy($plcdata) &&  $plcdata['bpstateid'] == PM::BPSTATE_EDO_OK) {
                $b_nextStage = 1;
            }
            elseif(!$canStartEdo) {
                $b_nextStage = (in_array($plcdata['stateid'], [PM::STATE_PROJECT, PM::STATE_POLICY,PM::STATE_DOP_CHECK_DONE])
                  && $plcdata['med_declar']==='Y' && empty($plcdata['reasonid']) && $plcdata['bpstateid']==0);
            }
        }
        if($RAZBORKI)
          writeDebugInfo("canPay =[$canPay], canStartEdo=[$canStartEdo] next_stage: [$b_nextStage], docaccess=$docaccess, stateid=$plcdata[stateid], "
          . "med_declar=$plcdata[med_declar], reasonid=$plcdata[reasonid], myPlcMgr=[$myPlcMgr]\n"
          . "  meta_type=$plcdata[metatype] bpstateid=$plcdata[bpstateid]"
          );

        $obj->enableBtn('next_stage', ($b_nextStage && $myPlcMgr));
        # отправить на учет:
        $metaType = $plcdata['metatype'] ?? $obj->agmtdata['metatype'] ?? 'XXX';
        if($metaType == OrgUnits::MT_BANK) {
            $b_sendToReg = ($plcdata['stateid'] == PM::STATE_PAYED) && ($myPlcMgr || $saleSupport) && ($docaccess>=1);
            # writeDebugInfo("Bank, send_to_reg : [$b_sendToReg]");
        }
        else {
            $payed = ($plcdata['stateid'] == PM::STATE_PAYED || \PlcUtils::isDateValue($plcdata['datepay']));
            $b_sendToReg = (in_array($plcdata['stateid'], [PM::STATE_IN_FORMING, PM::STATE_PAYED, PM::STATE_UWAGREED])) && $payed
              && (in_array($plcdata['bpstateid'], [PM::BPSTATE_RELEASED, PM::STATE_IC_CHECKING]))
              && ($myPlcMgr || $saleSupport) && ($docaccess>=1);
            # $b_sendToReg = (($bPayed &&($plcdata['stateid']==PM::STATE_UWAGREED) || $plcdata['stateid'] == PM::STATE_PAYED) && $myPlcMgr);
            # сит. согласован ПОСЛЕ оплаты
            $meUserid = AppEnv::getUserId();
            # writeDebugInfo("b_sendToReg=[$b_sendToReg] stateid=$plcdata[stateid] bpstateid=$plcdata[bpstateid]");
            # TODO: проверить для агента - когда разрешать
        }
        if(self::$emulAgent)
          writeDebugInfo("meta:[$metaType], $plcdata[stmt_id]/$plcdata[policyno]: user:$meUserid, send_to_reg : [$b_sendToReg], myPlcMgr=[$myPlcMgr], states: $plcdata[stateid]/$plcdata[bpstateid]");

        $finalSendToReg = ($b_sendToReg && $docaccess>=1);
        $obj->enableBtn('send_to_reg', $finalSendToReg);
        # writeDebugInfo("send_to_reg=[$b_sendToReg], docAcess: [$docaccess], finalSendToReg=[$finalSendToReg]");

        $bToCompliance = ($plcdata['substate'] == PM::SUBSTATE_COMPLIANCE);
        $obj->enableBtn('to_compliance', $bToCompliance);

        $bComplianceOk = ($plcdata['substate'] == PM::SUBSTATE_COMPL_CHECKING);
        $obj->enableBtn('compliance_ok', $bComplianceOk);

        $bstartreEdo = ($edoMode && in_array($plcdata['substate'], [PM::SUBSTATE_COMPL_CHECK_OK, PM::SUBSTATE_AFTER_EDIT]));
        $obj->enableBtn('start_reedo', $bstartreEdo);
        # writeDebugInfo("bstartEdo2=[$bstartreEdo]");

        # если отправли в Комплаенс или на повторное согласование (EDO2), редактирование блокирую!
        if($plcdata['substate'] == PM::SUBSTATE_EDO2_STARTED) {
            $obj->enableBtn($editbtn_id, 0);
        }
        elseif(in_array($plcdata['substate'],
           [PM::SUBSTATE_REWORK, PM::SUBSTATE_AFTER_EDIT, PM::SUBSTATE_COMPLIANCE,PM::SUBSTATE_EDO2_FAIL]) && $userLevel>=PM::LEVEL_IC_ADMIN)
        {
            if(empty($plcdata['docflowstate']))
                $obj->enableBtn($editbtn_id, 1); # доработка, исправили, сработал комплаенс, клиент НЕ согласовал, надо снова исправлять!
            else
                $obj->enableBtn($editbtn_id, 0); # доработка, исправили, если полис уже в СЭД, больше не релактировать!
        }
        if($plcdata['substate'] > 0 && $editbtn_id != 'editagr') {
            $obj->enableBtn([$editbtn_id, 'edit'], 0); # если доработка, для редактора stmt блокируем редактирование
        }
        # при любом промежуточном статусе Доработок - "Проверен" недоступна
        if($bToCompliance || $bComplianceOk || $bstartreEdo)
            $obj->enableBtn('set_active', 0);
        # отправить на арх.хранение (проверен):
        $b_set_active = !$iAmUw && ($admin || ($userLevel >= PM::LEVEL_IC_ADMIN) ) && ($plcdata['stateid'] == PM::STATE_FORMED)
           && ($plcdata['bpstateid']==PM::BPSTATE_ACCOUNTED);
        # На доработке, на доработке после исправления - сработал комплайнс или UW причины
        if(in_array($plcdata['substate'], [ PM::SUBSTATE_REWORK,PM::SUBSTATE_NEED_RECALC,PM::SUBSTATE_COMPLIANCE ]))
            $b_set_active = FALSE;

        if($edoMode &&  in_array($plcdata['substate'], [ PM::SUBSTATE_COMPL_CHECK_OK, PM::SUBSTATE_EDO2_FAIL ]))
            $b_set_active = FALSE;
        # полис на доработке, после редактирования сработали чернын списки (комплайнс) - отправлять в СЭД нельзя?
        # if($plcdata['substate'] == PM::SUBSTATE_COMPLIANCE) $b_set_active = FALSE;

        $obj->enableBtn('set_active', $b_set_active);

        $bNewAcc = ($plcdata['stateid'] == PM::STATE_FORMED && empty($plcdata['substate']));
        $obj->enableBtn('newaccount', $bNewAcc);

        # {upd/2025-09-24} кнопа редактирования отдельных персон в полисе - пока в тестовом режиме
        # $obj->enableBtn('modify_pdata', AppEnv::isLocalEnv() );
        # writeDebugInfo("buttons: ", $obj->all_buttons);
        # просмотр других полисов на того же ЗВ
        $vOtherPlc = ($userLevel >= PM::LEVEL_IC_ADMIN);
        $obj->enableBtn('view_otherplc', $vOtherPlc);
        # обрабатываю переобъявленные доступности кнопок
        if(count(self::$override_buttons)) foreach(self::$override_buttons as $btnid=>$val) {
            $obj->enableBtn($btnid, $val);
            writeDebugInfo("override $btnid: [$val]");
        }
        if(method_exists($obj, 'buttonsAttrib')) {
            $obj->buttonsAttrib();
        }
        if($obj->bReinvest) \Reinvest::showHideButton($obj);
    }
    /**
    *  финальные обработки после оплаты полиса (
    * @param mixed $bkend бэкенд стр.модуля
    * @param mixed $plcdata массив с данными полиса
    * @param mixed $datepay дата оплаты
    * @param mixed $payOnline TRUE если оплата была через эквайринг
    */
    public static function afterPolicyPayed($bkend, $plcdata, $datepay, $payOnline=FALSE) {
        $shiftStartDate = empty($plcdata['previous_id']); # при пролонгации никакие даты не сдвигать!
        if((PolicyModel::$debug)) self::$debug = max(self::$debug, PolicyModel::$debug);
        if(self::$debug) writeDebugInfo(__FUNCTION__," start datepay = $datepay data: ", $plcdata);
        $plcid = $plcdata['stmt_id'] ?? $plcdata['id'];
        $headOu = $plcdata['headdeptid'] ?? $plcdata['headbankid'];
        $today = date('Y-m-d');
        $module = $bkend->module ?? $plcdata['module'] ?? 'investprod';

        if(isset($plcdata['metatype']))
            $metatype = $plcdata['metatype'];
        else
            $metatype = OrgUnits::getMetaType($headOu);

        # {upd/2025-08-19} - при легком онлайн-оформлении даты уже не двигаю!
        if(AppEnv::isLightProcess()) {
            $shiftStartDate = FALSE;
            $bkend->payed_to_formed = TRUE;
        }
        elseif($metatype == PM::META_BANKS) {
            # банк - дата выпуска уже не обновляется (была занесена по "выпустить полис"
            $shiftStartDate = FALSE; # {upd/2023-10-03} Загайнова сказала после выпуска полиса даты выпуска/начала не двигать!

            if(self::$debug)
                writeDebugInfo("банк - дата выпуска уже не обновляется, была занесена по Выпустить полис");
            # надо сбросить bpstateid
            # $arUpd = ['bpstateid' => 0];
            # PlcUtils::updatePolicy($module, $plcid, $arUpd);
        }
        else {
            # не банк (агентская сеть или что еще)
            # Меняю "дату выпуска" = дате оплаты
            # TODO: на новых агентских дог. при оплате ставится дата выпуска, и еще активан кнопа "выпустить полис", оно так правильно?
            if(empty($plcdata['previous_id']) || $plcdata['datefrom']>$today) {
                if(!self::$setReleaseOnPay) $relDate = FALSE;
                else $relDate = $datepay;
            }
            else { # пролонгация поиса после его даты начала - дату выпуска приводим к -1 дню от начала
                $relDate = AddToDate($plcdata['datefrom'], 0,0,-1);
                # writeDebugInfo("поздняя проолонгация - своя дата выпуска! $relDate");
            }
            if(!empty($relDate)) {
                $agmtDt = [ 'date_release' => $relDate ];
                AgmtData::saveData($module, $plcid, $agmtDt);
                if(self::$debug) writeDebugInfo("обновил дату выпуска на $relDate SQL: ", appEnv::$db->getLastQuery());
            }
        }
        if( $shiftStartDate ) {
            # {upd/2024-08-23} если дату оплаты ввелт задним числом, избегаю установки даты начала = "вчера"+1
            # ЗА ИСКЛЮЧЕНИЕМ "ЖЕСТКИХ" полисов типа СД,ЗП (приказ ЦБ) - метод isHardPayDate
            $hardPayDate = method_exists($bkend, 'isHardPayDate') ? $bkend->isHardPayDate($plcdata) : FALSE;
            $baseDate = $hardPayDate ? $datepay : max($datepay, date('Y-m-d'));
            if(self::$debug) writeDebugInfo("hardPayDate[ $plcdata[programid] ]=[$hardPayDate], basePayDate: $baseDate");
            if(method_exists($bkend, 'computeStartDate')) {
                $newStartDate = $bkend->computeStartDate($plcdata, $baseDate);
            }
            else {
                $daysToStart = method_exists($bkend, 'getDaysToStart') ? $bkend->getDaysToStart($plcdata) : 0;
                $newStartDate = ($daysToStart !=0) ? AddToDate($baseDate, 0,0, $daysToStart) : $baseDate; # max($datepay, date('Y-m-d'));
            }

            if(self::$debug) writeDebugInfo("TODO: сur datefrom: ".$bkend->_rawAgmtData['datefrom']." datepay=$datepay - сдвинуть даты в полисе на $shiftDays дней к $newStartDate");
            if($plcdata['datefrom'] != $newStartDate) {
                # двигаю даты действия в полисе и всех рисках на NN дней
                # PlcUtils::ShiftPolicyDates($bkend->_rawAgmtData,$shiftDays);
                if(self::$debug) writeDebugInfo("выполняю сдвиг дат д-вия к $newStartDate");
                self::shiftStartDate($plcdata,$newStartDate);
            }
        }
        $formed = 0;

        if (!empty($bkend->payed_to_formed) && method_exists($bkend, 'setPolicyFormed')) {
            # автоматом переводим в Оформленный (если еще не выдан постоянный Номер полиса - выдаем из пула!
            $formed = $bkend->setPolicyFormed($plcid, 'online');
            if (self::$debug) writeDebugInfo("setPolicyFormed result: ", $formed);
            if(AppEnv::isLightProcess()) {
                $saveDig = UniPep::createSignedPolicy($bkend->module, $plcid);
                if(self::$debug) writeDebugInfo("LightProcess: сразу генерю ЭЦП-полис: ", $saveDig);
                $created = $saveDig['result'] ?? FALSE;
                if(self::$debug) writeDebugInfo("LightProcess: plcdata: ", $bkend->_rawAgmtData);
                if(self::$debug) writeDebugInfo("LightProcess: pholder: ", $bkend->pholder);

                if($created === 'OK') {
                    # Отправляю клиенту е-полис
                    # $pdfName - файл уже удален! Надо брать из карточки полиса полный путь
                    $edoInCard = FileUtils::getFilesInPolicy($module, $plcid, "doctype='edo_policy'",TRUE);
                    # writeDebugInfo("EDO policy in card: ", $edoInCard);
                    $pdfName = $edoInCard[0]['fullpath'] ?? '';

                    $msgFile = AppEnv::getAppFolder('templates/letters/')
                      . 'client_final_letter.htm';

                    $msgbody = @file_get_contents($msgFile);
                    $imotch = ($bkend->pholder['imia'] ?? '') . ' ' . ($bkend->pholder['otch'] ?? '');
                    $policyNo = $bkend->_rawAgmtData['policyno'];
                    $arSubst = [
                        '{comp_name}' => AppEnv::getConfigValue('comp_title', 'ООО "Зетта страхование жизни"'),
                        '{comp_phones}' => AppEnv::getConfigValue('comp_phones', PM::STD_COMP_PHONE),
                        '{comp_email}' => AppEnv::getConfigValue('comp_email', PM::STD_COMP_EMAIL),
                        '{policyno}' => $policyNo,
                        '{client_name}' => $imotch,
                        '{dop_text}' => ''
                    ];

                    $letterData = [
                      'to' => ($bkend->pholder['email'] ?? ''),
                      'subj' => 'Ваш электронный полис страхования',
                      'message'=>strtr($msgbody, $arSubst),
                    ];

                    $files = [ ['fullpath'=>$pdfName, 'filename' => "policy-$policyNo.pdf"] ];

                    $sentCli = ClientUtils::sendLoggedEmail($letterData, $files, $module, $plcid);
                    $sentgOk = $sentCli ? 'Успех' : 'Неудача!';
                    AppEnv::logevent(($bkend->getLogPref() .'CLIENT LETTER'), "Отправка клиенту письма с полисом: $sentgOk",0,$plcid,FALSE,$module);
                }
            }
        }
        else {
            # У банков - ЭДО полис генерится после оплаты (т.к. выпуск был раньше, а агентов - наоборот, после выпуска!
            if($metatype == OrgUnits::MT_BANK && $bkend->isEdoPolicy($plcdata)) { # сразу заношу в ALFO ЭПЦ-подписанный полис
                $ePolicy = FileUtils::getFilesInPolicy($module,$plcid,"doctype='edo_policy'");
                $ePlcExist = (is_array($ePolicy) && count($ePolicy)>0);
                if($ePlcExist) {
                    if(self::$debug) writeDebugInfo("создание Е-полиса пропускаю, уже есть!");
                }
                else {
                    $saveDig = UniPep::createSignedPolicy($bkend->module, $plcid);
                    if(self::$debug) writeDebugInfo("createSignedPolicy: save DIG-signed policy after pay :", $saveDig);
                }
            }
        }
        return $formed;
    }

    public static function __buttonsVisibility($obj, $plcdata=0, $docaccess=FALSE) {
        if( in_array('recalc', self::$buttonSets[self::$curType])) {
            $bFlag = (in_array($plcdata['stateid'], [PM::STATE_PROJECT, PM::STATE_POLICY, PM::STATE_DRAFT]) && $obj->recalculable);
            $obj->enableBtn('recalc', $bFlag);
        }

    }
    /**
    * сдвигает даты действия в полисе - меняет даты С, ПО в полисе и всех рисках (калькуляция не трогается!)
    * @param mixed $data ID полиса ИЛИ массив с записью полиса (_rawAgmtData)
    * @param mixed $newDate либо новая дата начала, либо кол-во дней сдвига вперёд
    * @param mixed $test задать TRUE|1 чтобы просто посмотреть, какие даты будут сформированы (без занесения в БД)
    * @param mixed $logging регистрировать действие в журнале операций
    * В ИСЖ полисах дата окончания не двигается, т.к. привязана к дате транша!
    */
    public static function shiftStartDate($data, $newDate, $test = FALSE, $logging=FALSE) {
        $ret = '';
        if(is_numeric($data))
            $plcdata = AppEnv::$db->select(PM::T_POLICIES, ['where'=>['stmt_id'=>$data], 'singlerow'=>1]);
        elseif(is_array($data))
            $plcdata = $data;

        if(!isset($plcdata['datefrom'])) return FALSE;
        $curFrom = $plcdata['datefrom'];
        $curTill =  $plcdata['datetill'];
        $years = $plcdata['term'];
        $months = $plcdata['termmonth'];
        if($plcdata['termunit'] === 'M') {
            $years = floor($plcdata['term']/12);
            $months = $plcdata['term'] % 12; # остаток от деления на 12
        }
        $module = $plcdata['module'] ?? 'investprod';
        if(is_numeric($newDate)) # задано кол-во дней
            $days = intval($newDate);
        else # передали желаемую дату YYYY-MM-DD
            $days = DiffDays($curFrom, $newDate);

        if($days == 0) return "Сдвиг на 0 дней не требуется";
        $oldDateTill = $plcdata['datetill'];
        # writeDebugInfo("shift days from current $curFrom: $days");
        $plcid = $plcdata['stmt_id'] ?? $plcdata['id'];
        $risks = 0;

        if($module !== 'investprod') {
            #$arRedemp = appEnv::$db->select('bn_redemptionamount', ['where' => ['insurancepolicyid'=>$plcid], 'orderby'=>'id']);
            $risks = appEnv::$db->select(PM::T_AGRISKS, ['fields'=>'id,rtype,riskid,datefrom,datetill',
              'where' => ['stmt_id'=>$plcid], 'orderby'=>'id']);
        }
        # $ret = "days to Shift: $days, $data[stmt_id]/$module: $curFrom - $curTill<br>"; # debug
        $newFrom =  AddToDate($curFrom, 0,0, $days);

        # return "new date from: $newFrom";
        $trdate = $plcdata['tranchedate'] ?? '';
        if(!empty($trdate) && PlcUtils::isDateValue($trdate)) {
            # транш фиксирует дату окончания!
            $newTill = $curTill;
            # и нельзя дату начала двигать ЗА дату транша!
            if($newFrom >= $plcdata['tranchedate'])
                $newFrom = AddToDate($plcdata['tranchedate'],0,0,-1); # TODO: или разрешать дату начала = дате транша?
        }
        else {
            # $newTill = AddToDate($curTill, 0,0, $days);
            # {upd/2024-03-06} простой сдвиг даты оконч. на те же дни приводит к ошибке при datefrom около 29.02 високосного года
            $newTill = AddToDate($newFrom,$years, $months, -1);
        }
        # $ret .= "new dates $newFrom - $newTill<br>";
        # $ret .=  'risks  <pre>' . print_r($risks,1). '</pre>';

        # 1) сдвиг дат в самом полисе
        $upd = ['datefrom' => $newFrom];

        # TODO: для инвестов даты окончания другие, двигать нельзя (транш!)
        if(!in_array($module, ['invins','investprod'])) {
            $upd['datetill'] = $newTill;
        }
        # ИСЖ -  теперь есть полисы БЕЗ траншей (ДСЖ) - тм тоже надо двигать дату окончания!
        if($module === 'invins' && !PlcUtils::isDateValue($trdate))
            $upd['datetill'] = $newTill;
        $updated = FALSE;
        if($test) $ret .= "новые даты: $newFrom - $newTill<br>";
        else {
            $updated = PlcUtils::updatePolicy($module, $plcid, $upd);
            if(!$updated) exit('Ошибка записи данных договора в БД: '.AppEnv::$db->sql_error());
        }
        if(is_array($risks) && count($risks)) {
            foreach($risks as $oneRisk) {

                if(!in_array($module, ['invins','investprod'])) {
                    if($oneRisk['datetill'] == $oldDateTill)
                        $upd['datetill'] = $newTill;
                    else {
                        # сложный кейс (напр, инвалидность, истекающая ранее прочих рисков на N лет)
                        $rskYears = RusUtils::RoundedYears($oneRisk['datefrom'],$oneRisk['datetill']);
                        $upd['datetill'] = AddToDate($newFrom, $rskYears, $months,-1);
                    }
                }
                if($test) $ret .= "&nbsp; - риск $oneRisk[riskid] datetill $oneRisk[datetill] to $upd[datetill]<br>";
                else {
                    $rUpdated = AppEnv::$db->update(PM::T_AGRISKS, $upd, ['id'=>$oneRisk['id']]);
                    if(self::$debug || !$rUpdated) writeDebugInfo("risk updated=[$rUpdated], SQL: ",
                      AppEnv::$db->getLastQuery(), ' err: ', AppEnv::$db->sql_error());
                }
            }
        }
        if($logging && $updated) {
            $logPrf = AppEnv::getPluginBackend($module)->getLogPref();
            if(!$logPrf) $logPrf = strtoupper($module) . '.';
            $strDays = RusUtils::skloNumber($days, 'день');
            $logText = "Сдвиг дат действия полиса ($strDays, к дате ".to_char($newFrom).')' ;
            AppEnv::logEvent("{$logPrf}SHIFT DATES",$logText,0,$plcid);
        }
        if($updated)
            $ret .= "Смена дат произведена, ".to_char($newFrom) . ' - ' . to_char($newTill);
        elseif($test) $ret .= '(тестирование сдвига)';

        if(self::$debug) writeDebugInfo("shiftStartDate $module/$plcid: $ret");
        return $ret;
    }

    # дефолтный проверяльщик наличия необходимых сканов для указанного события $action
    public static function checkMandatoryFiles($obj, $action, $uwState=0, $return = FALSE) {
        if(self::$debug) writeDebugInfo("businessproc::checkMandatoryFiles($action) _rawAgmtData: ", $obj->_rawAgmtData);
        # exit("1" . AjaxResponse::showMessage("businessproc::checkMandatoryFiles ($action)"));

        $plcid = $obj->_rawAgmtData['stmt_id'] ?? $obj->_rawAgmtData['id'];
        if ($plcid<=0) exit('checkMandatoryFiles wrong call - BAD _rawAgmtData');
        $offlinePay = $obj->isOfflinePayed();
        $module = $obj->_rawAgmtData['module'] ?? '';

        # $programid = $obj->_rawAgmtData['programid'] ?? $obj->_rawAgmtData['insuranceschemeid']; # investprod - insuranceschemeid

        # exit('1' . ajaxResponse::showMessage("var $programid"));
        $hasChild = 0;
        if(method_exists($obj, 'policyHasChild'))
            $hasChild = $obj->policyHasChild();
        # writeDebugInfo("this->prgData ", $obj->prgData);
        $insrType = $obj->_rawAgmtData['insurer_type'] ?? ($obj->_rawAgmtData['insurerid']>0? 1:2);
        $metatype = $obj->_rawAgmtData['metatype'] ?? 0;
        $isFL = ($insrType == 1);
        # writeDebugInfo(__FUNCTION__, " insured is FL:[$isFL] ", $obj->_rawAgmtData);

        $isEdo = $obj->isEdoProcess();
        $prolong = !empty($obj->_rawAgmtData['previous_id']);
        $arMandatory = [];
        if( !$isEdo && in_array($action, [Events::SUBMIT_FORREG, Events::RELEASE_POLICY])
          && (!empty($obj->b_stmt_exist) || $obj->b_stmt_print) ) {
            $arMandatory['plc_zayav'] = 0;
        }
        # {upd/2027-02-27} при отправке на учет требовать скан согласия на обраб.ПДн (И.Яковлева)
        if( !$isEdo && ($action == Events::SUBMIT_FORREG) && !empty($obj->mandatorySoglPdn) ) {
            $arMandatory['sogl_pdn'] = 0;
        }

        # {upd/2025-04-01} а еще если есть нал.нерез (стр-тель или ВП) требовать скан анкеты нал.резидента,
        # {upd/2025-05-30} анкета 6886У только для ФЛ страхователя
        if(in_array($action, [Events::FIX_POLICY, Events::SUBMIT_FORREG]) && $metatype==OrgUnits::MT_BANK) {
            if(!empty($obj->anketa_6886) && !$isEdo && $obj->_rawAgmtData['insurer_type']==1)
                $arMandatory['anketa_6886'] = 0;
        }

        if($action == Events::SUBMIT_FORREG) {
            $needAnkTR = FALSE;
            $meta = $obj->loadSpecData($plcid);
            if(empty($obj->_rawBenefs[0]['fullname'])) $obj->_rawBenefs = \Persons::loadBeneficiaries($obj, $plcid, 'benef', FALSE,TRUE);
            # $obj->loadIndividual($plcid, 'insr');
            $pholderTr = $meta['spec_params']['tax_rezident'] ?? 0;
            if(!empty($pholderTr) && !PlcUtils::isRF($pholderTr)) $needAnkTR = TRUE;
            if(count($obj->_rawBenefs)) foreach($obj->_rawBenefs as $ben) {
                if(!PlcUtils::isRF($ben['tax_rezident'])) $needAnkTR = TRUE;
            }
            if($needAnkTR) $arMandatory['anketa_taxrez'] = 0;
            # exit('1' . AjaxResponse::showMessage("[$pholderTr]:отпр.на учет, spcparms: <pre>" . print_r($obj->_rawBenefs,1) . '</pre>'));
        }
        # exit('1' . AjaxResponse::showMessage('KT-033: <pre>' . print_r($arMandatory,1) . '</pre>')); # DEBUG PIT STOP
        # {upd/2024-04-23} Загайнова затребовала проверку наличия паспорта при выпуске(банки)/оплате(агенты)
        elseif( (in_array($action, [Events::RELEASE_POLICY]) && $metatype==OrgUnits::MT_BANK) # банки, выпуск полиса
            || ( in_array($action, [Events::PAYED, Events::SEND_ONLINE_PAY]) && $metatype!=OrgUnits::MT_BANK ) # агенты и прочие НЕ банки - оплата/онлайн опл.
        ) {
            if(empty($obj->_rawAgmtData['previous_id'])) { # при пролонгации - не надо!
                $arMandatory['passport_insr'] = 0;
                # exit('1' . AjaxResponse::showMessage('passport_insr'));
            }
        }

        $bDocPholder = 0;

        $equalInsured = $obj->_rawAgmtData['equalinsured'] ?? $obj->_rawAgmtData['insuredisinsurer'] ?? 0;

        $ankLimit = $obj->getAnketaCompLimit($obj->_rawAgmtData);

        $totalPay = $obj->getTotalPremium($obj->_rawAgmtData,TRUE);
        $bPayAnketas = ($totalPay >= $ankLimit);
        # writeDebugInfo("bPayAnketas=[$bPayAnketas], totalPay: ", $totalPay, " anketa limit: ", $ankLimit);

        if ($isEdo || in_array($action, [Events::FIX_POLICY, Events::SUBMIT_FORREG])) {
            # EDO оформление и в СЭД
            if($isFL) {
                # writeDebugInfo(__LINE__, " req. passport_insr");
                $arMandatory['passport_insr'] = 0;
                # if($equalInsured == 0)
                if(empty($obj->box_product) && $obj->_rawAgmtData['reasonid']>0 && $obj->_rawAgmtData['reasonid']!=PM::UW_REASON_BEN_UL
                  && $bPayAnketas) {
                    $arMandatory['anketa_insd'] = 0;
                    # writeDebugInfo("KT-001 added anketa_insd");
                }
            }
            if(!$isFL || !$equalInsured) {
                # страхователь ЮЛ/СТр<>Застр
                if($hasChild)
                    $arMandatory['passport_child'] = 0; #застрах реб.
                else {
                    $arMandatory['passport_insd'] = 0; #застрах взр.
                    if( empty($obj->box_product) && $obj->_rawAgmtData['reasonid']>0  && $obj->_rawAgmtData['reasonid']!=PM::UW_REASON_BEN_UL)
                      $arMandatory['anketa_insd'] = 0;
                }
            }
            if (($action === 'docflow' && empty($uwState)) ) {
                $arMandatory['signed_policy'] = 0;
                if($offlinePay)
                    $arMandatory['plc_paydoc'] = 0; # нужна платежка
            }

        }

        if(!$isEdo ) { # не ЭДО полис, не пролонгация

            if( in_array($action, [ 'uw', Events::TOUW, Events::SUBMIT_FORREG ]) ) {
                if(!empty($obj->b_stmt_exist) || $obj->b_stmt_print)
                    $arMandatory['plc_zayav'] = 0;

                if((empty($obj->box_product) || $obj->box_product === 'anketa') && ($obj->_rawAgmtData['reasonid']>0 || $obj->_rawAgmtData['med_declar']=='N')
                  && $obj->_rawAgmtData['reasonid']!=PM::UW_REASON_BEN_UL && $bPayAnketas && !$obj->getSpecTuning('no_medanketa')) {
                    $arMandatory['anketa_insd'] = 0; # мед-анкета застрахованного
                  }

                if ($isFL) {

                    $arMandatory['passport_insr'] = 0;# Страхователь ФЛ

                    # $arMandatory['anketa_insr'] = 0;
                    if($hasChild)
                        $arMandatory['passport_child'] = 0; #застрах реб.
                    elseif ($equalInsured==0) {
                        $arMandatory['passport_insd'] = 0; #застрах
                        if(!$prolong && ($obj->_rawAgmtData['reasonid']>0 || $obj->_rawAgmtData['med_declar']=='N')
                          && empty($obj->box_product) && $bPayAnketas)
                            $arMandatory['anketa_insd'] = 0;
                    }
                }
                else { # страхователь ЮЛ
                    if($hasChild)
                        $arMandatory['passport_child'] = 0; #застрах реб.
                    else {
                        $arMandatory['passport_insd'] = 0; #застрах взр.
                        /*
                        if(!$prolong && empty($obj->box_product) && $obj->_rawAgmtData['reasonid']>0
                          && $obj->_rawAgmtData['reasonid']!=PM::UW_REASON_BEN_UL && $bPayAnketas)
                            $arMandatory['anketa_insd'] = 0;
                        */

                        if(!$prolong) $arMandatory['anketa_insd'] = 0;
                    }
                }
                if (($action === 'docflow' && empty($uwState)) ) {
                    $arMandatory['signed_policy'] = 0;
                    if($offlinePay)
                        $arMandatory['plc_paydoc'] = 0; # нужна платежка
                }
            }
        }
        /*
        if($action == Events::RELEASE_POLICY) {
            if($offlinePay) $arMandatory['plc_paydoc'] = 0;
        }
        */
        if(in_array($action, [Events::SUBMIT_FORREG]) ) {
            $noBenefs = $obj->_rawAgmtData['no_benef'] ?? FALSE;
            # TODO: анкеты ВП требовать только при превышении общего взноса над (15000)?
            # if($ankLimit>0 && $totalPay>$ankLimit)
            if (!$noBenefs && !$isEdo) { # при ЭДО анкеты ВП уже есть в е-полисе
                $bens = $obj->loadBeneficiaries($plcid);
                if(is_array($bens) && count($bens)) {
                    if(!$isFL || $bPayAnketas)
                        $arMandatory['anketa_ben'] = 0; # {upd/2023-03-07} - анкету ВП, а не паспорт passport_ben|anketa_ben
                    # writeDebugInfo("KT-010 added anketa_ben");
                }
            }

            if($offlinePay)
                $arMandatory['plc_paydoc'] = 0; # нужна платежка

            if(!$isEdo)
                $arMandatory['signed_policy'] = 0; # нужен скан подписанного полиса!
        }

        /*
        # для андеррайтинга паспорта не нужны?
        if(in_array($action, ['uw', Events::TOUW]))
            $arMandatory['passport_insr'] = $arMandatory['passport_insd']
              = $arMandatory['passport_child'] = 1;
        */
        $files = FileUtils::getFilesInPolicy($obj->module, $plcid);

        # exit('1' . ajaxResponse::showMessage("$obj->module, $plcid, files: <pre>". print_r($files,1).'</pre>')); # PIT STOP

        $err = '';
        # exit('1' . ajaxResponse::showMessage("Mandaory files for event $action<pre>".print_r($arMandatory,1).'</pre>')); # DEBUG PIT STOP

        if(is_array($files)) foreach($files as $fl) {
            $dtype = $fl['doctype'] ?? $fl['typeid'];
            if ($dtype === 'agmt') { # загрузили по-старому, больше ничего не проверяю
                $arMandatory = [];
                break;
            }
            if (isset($arMandatory[$dtype])) $arMandatory[$dtype] = 1;
        }

        foreach($arMandatory as $dtype => $val) {
            if (!$val) $err .= '<li>' . PlcUtils::decodeScanType($dtype) . '</li>';
        }
        if ( $err ) {
            $msgErr = "К договору нужно приложить недоcтающие сканы документов:<ul>$err</ul>";
            if(!$return && isAjaxCall()) exit('1' . AjaxResponse::showError($msgErr));
            else return ['result'=>'ERROR', 'message'=>$msgErr];
        }
        # exit("checkMandatoryFiles($action/$programid), hasChild=[$hasChild] OK"); # debug PIT-STOP
        return 0;
    }
    # онлайн аннуляция/расторжение полиса
    public static function cancelPolicy($bkend, $policyid) {
        $plcData = \PlcUtils::loadPolicyData($bkend->module, $policyid);
        $stateid = $plcData['stateid'] ??  NULL;
        if($stateid === NULL) return ['result'=>'ERROR', 'message'=>'полис с указанным ИД не найден'];
        if(in_array($stateid, [PM::STATE_ANNUL, PM::STATE_CANCELED])) return ['result'=>'ERROR', 'message'=>'Полис уже аннулирован'];
        if(in_array($stateid, [PM::STATE_DISSOLUTED, PM::STATE_DISS_REDEMP])) return ['result'=>'ERROR', 'message'=>'Полис уже расторгнут'];
        if(in_array($stateid, [PM::STATE_BLOCKED])) return ['result'=>'ERROR', 'message'=>'Полис заблокирован (не действует)'];
        if($stateid == PM::STATE_FORMED) {
            $newState = PM::STATE_DISSOLUTED;
            $outText = 'Договор страхования расторгнут';
            $eventId = Events::DISSOLVE_ONLINE;
            $auUpd = [ 'stateid'=>PM::STATE_DISSOLUTED, 'diss_date'=>date('Y-m-d'), 'diss_reason'=>'DISS_ONLINE' ];
        }
        else {
            $newState = PM::STATE_ANNUL;
            $outText = 'Договор страхования аннулирован';
            $eventId = Events::CANCELLED_ONLINE;
            $auUpd = [ 'stateid'=>PM::STATE_ANNUL ];
        }
        $updResult = PlcUtils::updatePolicy($bkend->module,$policyid, $auUpd);
        if(!$updResult) {
            $errDetails = __FILE__ . ':'. __LINE__. ", SQL: ".AppEnv::$db->getLastQuery()
              . ", error: ".AppEnv::$db->slq_error();
            AppAlerts::raiseAlert('policy update', "Ошибка ввода в БД: ".$errDetails);
            return ['result'=>'ERROR', 'message'=>'Техническая ошибка при вводе в БД'];
        }
        AppAlerts::resetAlert('policy update');
        AppEnv::logEvent($bkend->getLogPref() . 'ANNULATE ONLINE', $outText,0,$policyid,0, $bkend->module);
        agtNotifier::send($bkend->module, $eventId, $plcData);
        $ret = ['result'=>'OK', 'message'=>$outText, 'data'=>['stateid'=>$newState]];
        # TODO: кому слать сообщение об аннуляции (ПП, оперблок)
        return $ret;
    }

    # AJAX запрос отправить в Комплаенс
    public static function sendToCompliance($bkObj, $arDta) {
        $cmpEmail = AppEnv::getConfigValue('comp_email_compliance');
        $plcid = $arDta['stmt_id'] ?? $bkObj->_rawAgmtData['stmt_id'] ?? 0;
        if(!$plcid) exit('1'.AjaxResponse::showError('Wrong Call'));
        if(empty($cmpEmail)) exit('1'.AjaxResponse::showError('Не настроен адрес отправки сообщений в Комплаенс!'));

        $sent = agtNotifier::send($bkObj->module,Events::SEND_TO_COMPLIANCE, $arDta);
        if($sent) {
            $arUpd = ['substate'=>PM::SUBSTATE_COMPL_CHECKING];
            PlcUtils::updatePolicy($bkObj->module,$arDta['stmt_id'], $arUpd);
            AppEnv::logEvent($bkObj->getLogPref() . 'COMPLIANCE START CHECK',"Договор отправлен на проверку в Комплаенс",0,$plcid,FALSE,$bkObj->module);
            $bkObj->refresh_view($plcid);
        }
        else exit('1' . AjaxResponse::showError('Ошибка при отправке письма !'));
    }

    # AJAX Комплаенс сказали, что всё ОК
    public static function setComplianceOk($bkObj, $arDta) {
        if($arDta['substate'] != PM::SUBSTATE_COMPL_CHECKING) {
            # $errTxt = 'Неверный статус договора сменился. Операция отменена';
            $response = AjaxResponse::showError('err_wrong_state_for_action') . $bkObj->refresh_view($plcid,TRUE);
            exit($response);
        }
        $arUpd = ['substate'=>PM::SUBSTATE_COMPL_CHECK_OK];
        $plcid = $arDta['stmt_id'];
        PlcUtils::updatePolicy($bkObj->module,$arDta['stmt_id'], $arUpd);
        if(is_callable('finmonitor::addToWhiteList') && AppEnv::getConfigValue('finmonitor_whitelist_ttl')) {
            $result = finmonitor::addToWhiteList($bkObj->module, $plcid);
        }
        AppEnv::logEvent($bkObj->getLogPref() . 'COMPLIANCE OK',"Комплаенс разрешили оформление полиса",0,$plcid,FALSE,$bkObj->module);
        AgtNotifier::send($bkObj->module,Events::COMPLIANCE_OK, $arDta);
        $bkObj->refresh_view($plcid);
    }
}
