<?php
/**
* ассистент для прямого вызова методов класса DNE
* @version 1.05.001 added 2023-10-14
* modified 2025-07-10
*/
include_once ('class.dne.php');

class DneAssist {
    static $b_inited = FALSE;
    public static function init() {
        if(!self::$b_inited) {
            DNExchange\DNE::setDefaultConfigPath(__DIR__  . '/dneconfig/');
            DNExchange\DNE::setXmlFinder('DneAssist::findXmlConfig');
            self::$b_inited = TRUE;
        }
    }
    public static function getJsCode($tabName) {
        $ret = DNExchange\DNE::getJsCode($tabName);
        return $ret;
    }
    public static function addMapping($tableid, $dneFilePath) {
        DNExchange\DNE::addMapping($tableid, $dneFilePath);
    }
    # HTML код для ячейки со ссылкой на вызов ф-ции экспорта записи
    public static function getExportRecordHtml($tableName='') {
        # Экспорт настройки в XML
        $exportTitle = AppEnv::getLocalized('dne_exportlink_title_'.$tableName,'Выгрузить в XML');
        # writeDebugInfo("getExportRecordHtml($tableName)");

        $retHtml = "<div class=\"ct\"><span role=\"button\" class=\"text-secondary\" onclick=\"dneLoader.exportNodeToXML({ID})\" title=\"$exportTitle\">"
         . "<i class=\"bi bi-floppy-fill font12\"></i></span></div>";
        return $retHtml;
    }
    # Генерация HTML кода кнопки "импорт из XML", для вывода на тулбар под гридом astedit
    public static function getButtonHtml($tableName='') {
        # writeDebugInfo("passed : ", $tableName);
        if(!is_string($tableName)) $tableName = '';
        $btLabel = AppEnv::getLocalized('dne_importbtn_label', 'Импорт из XML');
        $btTitle = AppEnv::getLocalized('dne_importbtn_title_'.$tableName, 'Импортировать данные из XML');
        $ret = "<input type=\"button\" id=\"{$tableName}_appendxml\" class=\"btn btn-primary me-1\" value=\"$btLabel\" onclick=\"dneLoader.importFromXml()\" title=\"$btTitle\"/>";
        return $ret;
    }

    # тест ф-ционала загрузки DNE-конфигурации
    public static function testFile($tableName) {
        $dneObj = new \DNExchange\DNE(AppEnv::$db);
        $loaded = $dneObj->loadConfig($tableName);
        if(!$loaded) $loaded = $dneObj->getErrorMessage();
        else $loaded = $dneObj->getChildTables();
        return $loaded;
    }
    /**
    * callback для поиска местонахождения dne файла настройки эскпорта-импорта таблицы
    * установить его через вызов \DNExchange\DNE::setXmlFinder()
    *
    * @param mixed $tablename имя таблицы
    */
    public static function findXmlConfig($tablename) {
        $parts = explode('_', $tablename);
        $baseName = "dne.$tablename.xml";
        if($parts[0] === 'alf') array_shift($parts);
        $module = $parts[0] ?? ''; # если это имя модуля)плагина) смотрю в его папке на соотв.файл
        if($module==='') return FALSE;
        $baseFolder = \AppEnv::getAppFolder("plugins/$module");
        if(!is_dir($baseFolder)) return FALSE;
        if(is_file("$baseFolder{$baseName}")) return "$baseFolder{$baseName}";
        if(is_file("{$baseFolder}tpl/$baseName")) return "{$baseFolder}tpl/$baseName";
        # файла не нашел:(
        return FALSE;
    }
}
