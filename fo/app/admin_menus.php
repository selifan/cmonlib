<?php
/**
* Дополнительные пункты в меню у супер-админа
* modified 2025-12-19
*/
if (SuperAdminMode() ) {

    $api_menu = [
      'api_clients' => array('title'=>appEnv::getLocalized('mnu-api-clients'), 'href'=>'./?p=editref&t='.PM::T_APICLIENTS),
      'api_event_log' => array('title'=>appEnv::getLocalized('mnu-api-log'), 'href'=>'./?p=editref&t='.PM::T_APILOG )
    ];
    appEnv::addSubmenuItem('mnu_utils', "mnu_apiconfigs",appEnv::getLocalized('mnu-api-headttitle'), '','',$api_menu);
    appEnv::addSubmenuItem('mnu_utils', "mnu_clientemails",appEnv::getLocalized('mnu-client-emails'), './?p=editref&t=alf_sentemail');
    appEnv::addSubmenuItem('mnu_utils', "mnu_sms_checklog",appEnv::getLocalized('mnu-sms_checklog'), './?p=editref&t='. PM::T_SMS_CHECKLOG);
    appEnv::addSubmenuItem('mnu_utils', "mnu_sms_log",appEnv::getLocalized('mnu-sms_log'), './?p=editref&t='. PM::T_SMSLOG);
    appEnv::addSubmenuItem('mnu_utils', "mnu_binded_plc",appEnv::getLocalized('mnu-binded_policies'), './?p=editref&t='. PM::T_PLCBIND);
    # $mnu_av['av_list_av'] = array('title'=>appEnv::getLocalized('mnu-av-listav'), 'href'=>'./?p=editref&t=alf_av');
    # appEnv::addSubmenuItem('mnu_admin/mnu_ins_block', 'av_list_av', appEnv::getLocalized('mnu-av-listav'), './?p=editref&t=alf_av');
    appEnv::addSubmenuItem('mnu_admin/mnu_ins_block', 'av_list_burden', appEnv::getLocalized('mnu-av-burden'), './?p=editref&t=alf_burden');

    # Справочники инвест онлайн - invonline
    if( !in_array('invonline', PM::$deadProducts) ) {
        appEnv::addSubmenuItem('mnu_admin/mnu_ins_block', 'list_inv_ba', appEnv::getLocalized('mnu-inv_ba'), './?p=editref&t=alf_invba');
        appEnv::addSubmenuItem('mnu_admin/mnu_ins_block', 'list_inv_subtypes', appEnv::getLocalized('mnu-inv_subtypes'), './?p=editref&t=alf_invsubtypes');
    }
    # {upd/2022-08-16} Конструктор стр.программ (перенос из НСЖ)
    $submnu = array(
      # 'nsj_dept_prod' => array('title'=>appEnv::getLocalized('nsj:title_ref_dept_prod'), 'href'=>'./?plg='.self::ME.'&action=refdeptprod'),
      # sj_bnk_limits'=> array('title'=>appEnv::getLocalized('nsj:title_ref_bnklimits'), 'href'=>'./?plg='.self::ME.'&action=refbnklimits'),
      'nsj_programlist'=> array('title'=>appEnv::getLocalized('title_ref_programlist'), 'href'=>'./?p=editref&t='.PM::T_ICONS_PROGRAMS),
      'nsj_programrisks'=> array('title'=>appEnv::getLocalized('title_ref_programrisks'), 'href'=>'./?p=editref&t='.PM::T_ICONS_RISKS),
    );
    if(is_dir('./iconst')) {
        appEnv::addSubmenuItem('mnu_admin/mnu_constuct', 'nsj_programlist', appEnv::getLocalized('mnu_prg_constructor'),'./?p=editref&t='.PM::T_ICONS_PROGRAMS);
        appEnv::addSubmenuItem('mnu_admin/mnu_constuct', 'nsj_programrisks', appEnv::getLocalized('title_ref_programrisks'),'./?p=editref&t='.PM::T_ICONS_RISKS);
        # appEnv::addSubmenuItem('mnu_admin', 'mnu_prgconst', appEnv::getLocalized('mnu_prg_constructor'),FALSE,FALSE,$submnu);
    }
    if(AppEnv::isLocalEnv()) {
        # {upd/2025-12-19} hello, 3I ATLAS! заношу в ALFO чат-бот
        $chatMenu = [
          'chatbot_open' => array('title'=>'Войти в чат-бот', 'href'=>'./?p=chatbot'),
          'chatbot_context' => array('title'=>'Контексты', 'href'=>'./?p=editref&t='.PM::T_CHATBOT_CONTEXTS ),
          'chatbot_hist' => array('title'=>'История запросов', 'href'=>'./?p=editref&t='.PM::T_CHATBOT_HIST ),
        ];
        appEnv::addSubmenuItem('mnu_utils', "mnu_chatbot",'Чат-бот', '','',$chatMenu);
    }
    elseif($chtLevel = AppEnv::userHasRight(PM::RIGHT_CHATBOTUSER)) {
        if($chtLevel == 1)
            appEnv::addSubmenuItem('mnu_utils', "chatbot_open",'Чат-бот', './?p=chatbot');

    }
    /* # свой блок на стартовой странице:
    $superCodes = [];
    $superCodes[] = "<a href=\"./p=editref&t=". PM::T_SMS_CHECKLOG . appEnv::getLocalized('mnu-sms_attempts') . '</a>';
    AppEnv::drawUserBlock($superCodes, 'Я Супер!');
    */
}
