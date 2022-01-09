<?php
/**
* CrudMan - проверки работы CRUD модуля (загрузка, конвертация описаний таблиц из tpl в XML etc.
* modified 2022-01-03
*/
class CrudMan {
    public static function form() {
        appEnv::setPagetitle('CrudMan-тестирование библиотеки waCRUDer');
        if(!SuperAdminMode()) {
            appEnv::echoError('err-no-rights');
            appEnv::finalize();
            exit;
        }
        $jsCode = <<< EOJS
crud = {
  toXml: function() {
    var pars = { "action": "toXml", "t": $("#tname").val() };
    SendServerRequest(this.backend, pars);
  },
  compare: function() {
    var pars = { "action": "tCompare", "t": $("#tname").val() };
    SendServerRequest(this.backend, pars);
  },

  theend: 0
};
EOJS;
        addJsCode($jsCode,'head');

        $html = <<< EOHTM
<br>

<div class="bounded w800">
<div class="darkhead ct">Тестирование ф-ционала CRUD модуля</div>
<table>
<tr><td>Имя таблицы</td><td><input type="text" name="tname" id="tname" class="ibox w160" /></td>
</table>
<div class="bounded area-buttons">
<span><input type="button" class="button" id="btn_toxml" value="TPL-&gt;XML" onclick="crud.toXml()" /></span>
<span><input type="button" class="button" id="btn_compare" value="compare TPL &amp; XML" onclick="crud.compare()" /></span>
</div>
EOHTM;
        appEnv::appendHtml($html);
        appEnv::finalize();
        exit;
    }

    public static function toXml() {
        $tname = empty(appEnv::$_p['t'])? '' : trim(appEnv::$_p['t']);
        if (!$tname) exit('No table name');
        include_once('waCRUDer.php');
        include_once('waCRUDer.read.tpl.php');
        include_once('waCRUDer.write.xml.php');

        WaCRUDer::setDefReader('tpl');
        WaCRUDer::setDefWriter('xml');
        $tabledef = new WaCRUDer($tname);
        # writeDebugInfo("tabledef: ", $tabledef);
        $destFolder = $tabledef->sourceFolder;
        if( !$destFolder ) {
            $errs = WaCRUDer::getErrorList();
            exit('1'. AjaxResponse::showError("Ошибки чтения шаблона ".$errs));
        }

        $tabledef->saveDefinition("$destFolder/tdef.$tname.xml");

        $ret = '1' . AjaxResponse::showMessage("tdef.$tname.xml записан в $destFolder");
        exit($ret);
    }

    public static function tCompare() {
        $tname = empty(appEnv::$_p['t'])? '' : trim(appEnv::$_p['t']);
        if (!$tname) exit('No table name');

        include_once('waCRUDer.php');
        # include_once('waCRUDer.read.tpl.php');
        # include_once('waCRUDer.read.xml.php');

        WaCRUDer::setDefReader('tpl');
        $tdefTpl = new WaCRUDer($tname);
        file_put_contents("tmp/$tname.tpl.log", print_r($tdefTpl,1));

        WaCRUDer::setDefReader('xml');
        $tdefXml = new WaCRUDer($tname);
        file_put_contents("tmp/$tname.xml.log", print_r($tdefXml,1));

        # TODO: compare objects!
        $ret = '1' . AjaxResponse::showMessage("tCompare $tname result: see files in tpl/");
        exit($ret);
    }
}

# если был (AJAX) вызов plcutilsaction, выполняю ф-цию с указанным именем
$action = empty(appEnv::$_p['action']) ? 'form' : trim(appEnv::$_p['action']);
if ($action) {
    if (method_exists('CrudMan', $action)) CrudMan::$action();
    else exit("CrudMan: undefined method call : $action");
}
