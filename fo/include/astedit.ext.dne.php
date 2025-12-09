<?php
/**
* @package webapp/astedit
* Astedit extension, for useing Data Node Exchange module (class.dne.php)
* modified 2025-12-06 / created 2025-12-06
* @version 0.1.001
* заменяю все подготовительные вызовы DNE на одно подключение модуля расширения
* TOOLBAR|@dneAssist::getButtonHtml
* LOADJSCODE|DneAssist::getJsCode|'boxProdPrg.reloadPage()'}
* CUSTOMCOLUMN|<div class="ct"><i class="bi bi-floppy-fill font12 pnt" onclick="dneLoader.exportNodeToXML({ID})" title="Экспорт в XML"/></div>|EX
*/
namespace astedit;
class Dne {
    public function __construct(\CTableDefinition $asteditObj, $reloadGridCode='')  {
        # require_once('class.dne.php'); # DNExchange\DNE
        $tableName = $asteditObj->id;
        # получаю имя настроечного dne xml: path/tablename.php -> path/dne.tablename.xml :
        $dneFileName = strtr($asteditObj->tplfile, ['.tpl'=>'.xml', $tableName => "dne.$tableName"]);
        # writeDebugInfo("add dne mapping for $tableName to $dneFileName");
        \Libs\DNE::addMapping($tableName, $dneFileName);
        $buttonHtml = self::getButtonHtml();
        $asteditObj->addToolbarElement($buttonHtml);
        # кнопа выгрузки как customColumn:
        $colTitle = 'Экспорт в XML';
        $theadLabel = "<div><i class='bi bi-floppy-fill font12'/></div>";
        $colHtm = "<div class=\"ct\"><i class=\"bi bi-floppy-fill font12 pnt\" onclick=\"dneLoader.exportNodeToXML({ID})\" title=\"$colTitle\"/></div>";
        $asteditObj->AddCustomColumn($colHtm, $theadLabel);

        $dneJs = \Libs\DNE::getJsCode([$tableName, $reloadGridCode]);
        $asteditObj->addJsCode($dneJs); # |'boxProdPrg.reloadPage()'
    }
    # Генерация HTML кода кнопки "импорт из XML", для вывода на тулбар под гридом astedit
    public static function getButtonHtml($tableName='') {
        # writeDebugInfo("passed : ", $tableName);
        if(!is_string($tableName)) $tableName = '';
        $titleImp = \AppEnv::getLocalized('dne_tritle_import', 'Импорт из XML');
        $btLabel = \AppEnv::getLocalized('dne_importbtn_label', $titleImp);
        $btTitle = \AppEnv::getLocalized('dne_importbtn_title_'.$tableName, 'Импортировать данные из XML');
        $ret = "<input type=\"button\" id=\"{$tableName}_appendxml\" class=\"btn btn-primary me-1\" "
          . "value=\"$btLabel\" onclick=\"dneLoader.importFromXml()\" title=\"$btTitle\"/>";
        return $ret;
    }

}
