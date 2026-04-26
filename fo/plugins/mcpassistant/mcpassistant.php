<?php
/**
* @package ALFO
* @author Alexander Selifonov,
* @name plugins/mcpassistant/mcpassistant.php
* 
* last modified : 2026-04-26 created 2026-04-26
* @version 0.01.01
**/
class mcpassistant extends appPlugins {

    const ME = 'mcpassistant';
    const TITLE_TEST   = 'MCP assistant';
    const RIGHT_EDIT = 'mcpassistant';

    const VERSION = '0.01';

    public function getVersion() {
        return self::VERSION;
    }
    public function getDescription() {
        return 'Служебный: генератор файлов настройки MCP утилит для LLM';
    }
    public function moduleType() { return PM::MODULE_SERVICE; }

    public function init() {
        $submenu = [];
        $this->_my_folder = appEnv::$FOLDER_PLUGINS . 'mcpassistant/';
        /*
        $pdfmenu = array(
            'mcpassistant_tests' => array('href'=>'./?plg='.self::ME.'&action=testform', 'title'=>self::TITLE_TEST)
        );
        appEnv::addSubmenuItem('mnu_utils', 'mnu_mcpassistant', 'PDF-tests', '', '', $pdfmenu);
        */
        appEnv::addSubmenuItem('mnu_utils', 'mnu_mcpassistant', 'MCP assistant','./?plg='.self::ME.'&action=form');
    }
}

if (appEnv::$auth->isSuperAdmin() || appEnv::$auth->getAccessLevel(mcpassistant::RIGHT_EDIT))
    appEnv::registerPlugin('mcpassistant');
