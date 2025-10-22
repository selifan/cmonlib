<?php
# app_menu.php - встраиваемый код для генерации основного меню приложения (used in alfo_core.php:buildMainMenu())
# modified 2025-10-22 / created 2023-08-16
if(self::$auth->IsUserLogged()) {
    $invanketa = InvestAnketa::isInvestAnketaActive();
    if ($invanketa && self::$workmode < 100) { # ссылка для просмотра инвест-анкет
        AppEnv::addSubmenuItem('mnu_browse', 'mnu_invanketas', AppEnv::getLocalized('mnu_invanketas'),'./?p=invanketas');
    }
    if(AppEnv::isGlobalViewRights())
        AppEnv::addSubmenuItem('mnu_browse', 'mnu_allagrs', AppEnv::getLocalized('mnu_allagr'),'./?p=allagr');

    $subm_in = array();

    $mymeta = OrgUnits::getMyMetaType();
    if($mymeta == OrgUnits::MT_AGENT && AppEnv::getConfigValue('lifeag_clientmgr')) {
        AppEnv::addSubmenuItem('inputdata', 'mnu_start_clientseek', AppEnv::getLocalized('mnu-start_clientseek'),'./?p=bindclient');
    }

    if ($invanketa && self::$workmode==0) { #(==2)  ссылка для ввода инвест-анкеты БЕЗ ввода договора
        AppEnv::addSubmenuItem('inputdata', 'mnu_newinvanketa', AppEnv::getLocalized('mnu_newinvanketa'),'./?p=addinvanketa');
    }

    if(count($subm_in)>0) self::$_mainmenu['inputdata']['submenu'] = $subm_in;

    $submenu = array();
    if(AppEnv::$config_enabled) $submenu['config'] = array('href'=>'./?p=config', 'title'=>AppEnv::getLocalized('title-appconfig'));
    if($admlev || $suadmin)
    {
        $submenu['log'] =  array('href'=>'./?p=eventlog', 'title'=>AppEnv::getLocalized('page.browse.eventlog'));
    }
    if ($deptadmin)
        $submenu['depts'] = array('href'=>'./?p=depts', 'title'=>AppEnv::getLocalized('mnu-deptlist'));

    if ($usradmin) {
        $submenu['users'] = array('href'=>'./?p=users', 'title'=>AppEnv::getLocalized('mnu-userlist'));
    }
    if ($suadmin || $mgrfiles || $suoper)
        $submenu['manage_files'] = array('title'=>AppEnv::getLocalized('title-managefiles'), 'href'=>'./?p=managefiles');

    $submnu_sysrep = [];

    if ($admlev || $suadmin || $suoper) { # служебные отчеты видны супер-оперу (НСЖ)
        $submnu_sysrep['smslog'] = ['href'=>'./?p=flexreps&name=smslog', 'title'=>AppEnv::getLocalized('mnu-sms_log') ];
        $submnu_sysrep['report_finmonitor'] = ['href'=>'./?p=flexreps&name=finmonitoring', 'title'=>AppEnv::getLocalized('mnu_report_finmionitor') ];
    }
    if ($suadmin) {

        $submenu['datamgr'] = array('href'=>'./?p=adminpanel', 'title'=>AppEnv::getLocalized('mnu-datamgr'));
        $submenu['acledit'] = array('href'=>'./?p=acleditor', 'title'=>AppEnv::getLocalized('title-acldesigner'));
        if (self::isStandalone())
            $submenu['psw_policies'] = array('href'=>'./?p=editref&t=arjagent_pswpolicies', 'title'=>AppEnv::getLocalized('title-psw_policies'));
        # $submenu['dailyjobs'] = array('title'=>AppEnv::getLocalized('title-dailyjobs'), 'href'=>'dailyjobs.php');

        $submenui = array(
           'ref_countries'  => array('href'=>'./?p=editref&t=alf_countries', 'title'=>AppEnv::getLocalized('mnu-list-countries'))
          ,'ref_regions'    => array('href'=>'./?p=editref&t=regions', 'title'=>AppEnv::getLocalized('mnu-list-regions'))
          ,'ref_curlist'    => array('href'=>'./?p=editref&t=curlist', 'title'=>AppEnv::getLocalized('mnu-list-curlist'))
          ,'ref_prod_config'  => array('href'=>'./?p=editref&t=alf_product_config', 'title'=>AppEnv::getLocalized('mnu-list-product_config'))
          ,'ref_dept_rekv'    => array('href'=>'./?p=dept_props', 'title'=>AppEnv::getLocalized('mnu-dept_properties'))

          ,'alf_dept_product' => array('href'=>'./?p=editref&t=alf_dept_product', 'title'=>AppEnv::getLocalized('mnu-list-dept_product'))
          ,'stmt_ranges' => array('href'=>'./?p=stmt_ranges', 'title'=>AppEnv::getLocalized('mnu-stmt_ranges'))
          ,'globalrisks' => array('href'=>'./?p=editref&t=alf_agmt_risks', 'title'=>AppEnv::getLocalized('mnu-list_global_risks'))
          ,'cumul_limits' => array('href'=>'./?p=editref&t='.PM::TABLE_CUMLIR, 'title'=>AppEnv::getLocalized('mnu-list_cumlir'))
          ,'promoactions' => array('href'=>'./?p=editref&t=alf_promoactions', 'title'=>AppEnv::getLocalized('mnu-list_promoactions'))
          # ,'bnk_alf_svcusers' => array('title'=>AppEnv::getLocalized('title_alf_svcusers'), 'href'=> './?p=editref&t=alf_svcusers')
          ,'bnk_alf_tranches' => array('title'=>AppEnv::getLocalized('title_alf_tranches'), 'href'=> './?p=editref&t='.PM::TABLE_TRANCHES)
          ,'list_professions' => array('title'=>AppEnv::getLocalized('title_professions_list'), 'href'=> './?p=editref&t='.PM::T_PROFESSIONS)
          ,'list_curators' => array('title'=>AppEnv::getLocalized('title_curators'), 'href'=> './?p=editref&t='.PM::T_CURATORS)
          ,'list_sports' => array('title'=>AppEnv::getLocalized('title_sports'), 'href'=> './?p=editref&t='.PM::T_SPORTS)
        );
        # if ($admlev || $suadmin ) {
           $submenui['alf_exportcfg'] = array('href'=>'./?p=editref&t=alf_exportcfg', 'title'=>AppEnv::getLocalized('mnu-list_alf_exportcfg'));
           if (method_exists('AppEnv', 'prependEditRef'))
              AppEnv::prependEditRef('alf_exportcfg','AppEnv::addAjaxUploader');
        # }
        $submenu['mnu_ins_block'] = array('title'=>'Страхование...', 'submenu' => $submenui);
        $submenu['mnu_all_tariffs'] = array('title'=>'Настройка продуктов/тарифов...', 'submenu' => '');

        # WriteDebugInfo("added mnu_all_tariffs");
        # self::$_mainmenu['mnu_reports']['sysreps'] = ['title'=> 'Системные', 'submenu' => $submnu_sysrep];
    }

    if($bankReports)
        $submnu_sysrep['invAnketas_bank'] = ['href'=>'./?p=flexreps&name=investAnketas', 'title'=>AppEnv::getLocalized('mnu_invanketa_bank') ];
    if($agtReports)
        $submnu_sysrep['invAnketas_agents'] = ['href'=>'./?p=flexreps&name=investAnketasAgent', 'title'=>AppEnv::getLocalized('mnu_invanketa_agents') ];


    if($bankReports) {
        $submnu_sysrep['reworkPolicies'] = ['href'=>'./?p=flexreps&name=reworkPolicies', 'title'=>AppEnv::getLocalized('mnu_report_reworkpolicies') ];
        $submnu_sysrep['reworkPolicies02'] = ['href'=>'./?p=flexreps&name=reworkPolicies02', 'title'=>AppEnv::getLocalized('mnu_report_reworkpolicies02') ];
        $submnu_sysrep['reworkLetters'] = ['href'=>'./?p=flexreps&name=reworkLetters', 'title'=>AppEnv::getLocalized('mnu_report_reworkletters') ];
    }

    if($agtReports) {
        $submnu_sysrep['agtActivity'] = ['href'=>'./?p=flexreps&name=agentCalculations', 'title'=>AppEnv::getLocalized('mnu_report_agentCalculations') ];
        $submnu_sysrep['agtActivity2'] = ['href'=>'./?p=flexreps&name=agentCalculations2', 'title'=>'Отчет по калькуляциям-клиенты (2)' ];
    }
    if($agtRepLevel>1) {
        $submnu_sysrep['welcomeCalls'] = ['href'=>'./?p=flexreps&name=welcomeCallsPolicies', 'title'=>AppEnv::getLocalized('mnu_report_welcomeCallsPolicies') ];
        if( $agtRepLevel>=PM::LEVEL_IC_ADMIN ) # отчет по всем полисам агентского канала
            $submnu_sysrep['agentReports'] = ['href'=>'./?p=flexreps&name=agentPolicies', 'title'=>AppEnv::getLocalized('mnu_report_agentPolicies') ];
    }

    if($maxReportLevel >= PM::LEVEL_MANAGER) {
        $submnu_sysrep['policy_events'] = ['href'=>'./?p=flexreps&name=policyEventLog', 'title'=>AppEnv::getLocalized('mnu_report_policyEventLog') ];
    }

    if (count($submnu_sysrep))
        AppEnv::addSubmenuItem('mnu_reports', 'sys_reports', AppEnv::getLocalized('service_reports'),'',   false, $submnu_sysrep);

    if(count($submenu)) self::$_mainmenu['mnu_admin']['submenu'] = $submenu;

    $submenu2 = [];
    $submenu2['myrights'] = array('title'=>AppEnv::getLocalized('mnu-view-my-rights'), 'href'=>'./?p=myrights');
    $submenu2['myprofile'] = array('title'=>AppEnv::getLocalized('mnu-view-my-profile'), 'href'=>'./?p=myprofile');

    if ($admlev || $usradmin || $suoper || $suadmin)
        $submenu2['seek_uwinfo'] = array('title'=>AppEnv::getLocalized('mnu_seek_uwinfo'), 'href'=>'./?p=seekuwinfo');
        # AppEnv::addSubmenuItem('mnu_utils','seek_uwinfo','mnu_seek_uwinfo','./?p=seekuwinfo');

    # if (self::$auth->getAccessLevel('admin')) $submenu2['org-structure'] = array('title'=>AppEnv::getLocalized('mnu-view-dept_tree'), 'href'=>'./?p=viewDeptTree');

    self::$_mainmenu['mnu_utils']['submenu'] = $submenu2;

    if(self::$auth->SuperVisorMode()) { # only for SUPERVISOR
        $supermnu = array('pinfo' => ['title'=>'PHP Info', 'href'=>'./?p=pinfo']
                         ,'_patcher_' => ['title'=>'Patcher', 'href'=>'./?p=patcher']
                         ,'tests' => ['title'=>'Test/evals page', 'href'=>'./?p=evals']
                         ,'codemake' => ['title'=>'Code Maker', 'href'=>'./?p=codemake']
        );
        if (function_exists('opcache_get_status')) {
            $supermnu['opcache_stat'] = array('title'=>'Статус OPcache', 'href'=>'./?p=opcachestat');
        }
        if (webApp::isDeveloperEnv() && self::isStandalone())
            $supermnu['wapluginator'] = array('title'=>'Plugin generator', 'href'=>'./?p=waplug');
        if (webApp::isDeveloperEnv() )
            $supermnu['api_test'] = array('title'=>'API tester', 'href'=>'./jsvc/test.php');

        AppEnv::addSubmenuItem('mnu_utils', 'mnu_supervisor', 'Super-Admin...', '','',$supermnu);
    }

    $hlphref = (constant('IN_BITRIX') ? './?p=helpme' : './?p=help');
    self::$_mainmenu['mnu_docs']['submenu'] = [];

    /*
    self::$_mainmenu['mnu_docs']['submenu'] = array(
         'help_main'  => array('href'=>$hlphref, 'title'=>AppEnv::getLocalized('title-help'))
        ,'help_files' => array('href'=>'./?p=showfiles','title'=>AppEnv::getLocalized('mnu-showfiles'))
    );
    */
    if (!constant('IN_BITRIX')) { # под Bitrix справку временно прикрываю!
       self::$_mainmenu['mnu_docs']['submenu']['help_main'] = array('href'=>$hlphref, 'title'=>AppEnv::getLocalized('title-help'));

    }
    self::$_mainmenu['mnu_docs']['submenu']['help_files'] = array('href'=>'./?p=showfiles','title'=>AppEnv::getLocalized('mnu-showfiles'));
}

if(class_exists('CfgAnketa') && $suadmin) {
  AppEnv::addSubmenuItem('mnu_admin/mnu_constuct', 'cfg_anketas', AppEnv::getLocalized('mnu_cfg_anketas'),'./?p=editref&t='.CfgAnketa::T_ANKETALST);
}
self::runPlugins('init');
if ($suadmin || WebApp::$workmode<100) {
  self::runPlugins('modify_mainmenu');
  # {upd/15.04.2015} Заполняю пункты меню инфографики
  if (AppEnv::isStandalone()) # под битрикcом инфографику вырубаю!
  foreach(self::$_plugins as $plgid=>$plgObj) {
    if(method_exists($plgObj,'infographics')) {
        $lst = $plgObj->infographics();
        if (is_array($lst) && count($lst)>0) {
            $sub_info = array();
            foreach ($lst as $lstitem){ # array: [action_id, title]
                $mnuid = $lstitem[0];
                $mnutext = empty($lstitem[1]) ? "$plgid:infogr-$mnuid" : $lstitem[1];
                $mnutitle = AppEnv::getLocalized($mnutext,$mnutext);
                $sub_info[$mnuid] = array('href'=>"./?plg={$plgid}&action=infographics&f=$mnuid",'title'=>$mnutitle);
            }
            AppEnv::addSubmenuItem('mnu_infographics', "info-$plgid",(AppEnv::getLocalized($plgid.':title',$plgid).'...'), '','',$sub_info);
        }
    }
  }
}
