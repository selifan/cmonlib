<?php
/**
* @package ALFO
* @author Alexander Selifonov,
* генератор файлов настройки MCP утилит для LLM
* @name plugins/mcpassistant/backend.php - backend модуль
* @version 1.10.001
* last modified : 2026-04-26
*/
class mcpassistantBackend {

    const PDVERSION = 2;
    const CFG_FILENAME = 'cfg-mcpassistant.json';

    protected $tables = [
      'alf_agreements',
      'alf_agmt_individual',
      'alf_agmt_risks',
      'alf_agmt_agrisks',
    ];
    protected $cfg = [];
    public function form() {
		appEnv::setPageTitle('MCP assistant, генератор файлов настройки MCP утилит для LLM');
        /*
        $plugins = glob('plugins/*',GLOB_ONLYDIR);
        foreach($plugins as $plugfolder) {
            list($pl, $module) = explode('/',$plugfolder);
            # устаревшие продукты не включаю:
            if(AppEnv::isDeadModule($module)) continue;
            $folders[] = $plugfolder;
        }
        # $folders = array_merge($folders, glob('plugins/*',GLOB_ONLYDIR));
        # WriteDebugInfo('folders: ', $folders);
        $selItems = '';
        if (is_array($folders)) foreach($folders as $fold) {
		}
        */
        $aiCfgList = \libs\AiBus::listOpenAiConfigs();
        $cfg = $this->loadConfig();
        # writeDebugInfo("loaded cfg:, ", $cfg);
        $table_list = $cfg['table_list'] ?? '';
        $curCfg = $cfg['cfgtype'] ?? '';
        $cur_translate_comments = $cfg['translate_comments'] ?? '';

        $make_english = $cfg['make_english'] ?? 0;
        $chkEnglish =  ($make_english ? 'checked="checked"' : '');
        $translator_model = $cfg['translator_model'] ?? '';
        $cfgOptions = DrawSelectOptions(self::cfgTypeList(), $curCfg, TRUE);
        $show_llmtrans = ($make_english) ? '' :  'style="display:none"';
        $translator_options = ''; # '<option value="mumba_umba">Mumba Umba model</option>'; # Пока лажа
        if(is_array($aiCfgList) && count($aiCfgList)) foreach($aiCfgList as $oneCfg) {
            $translator_options = DrawSelectOptions($aiCfgList, $translator_model, TRUE);
        }
        UseJsModules('plugins/mcpassistant/mcpassistant.js');
        $initTableDefs = '';
        if(isset($cfg['table_desc']) && is_array($cfg['table_desc']))
          foreach ($cfg['table_desc'] as $no => $tDetails) {
            $trid = 'tt_'.$no;
            $tbname = $tDetails['tablename'];
            $tbdesc = $tDetails['table_desc'];
            $initTableDefs .= "<tr id='$trid'><td>Таблица: <input type='text' name='dt_tbname[]' class='form-control d-inline w200' value='$tbname'/>"
             . " <input type='button' onclick=\"$('#$trid').remove()\" class='btn btn-primary' value=\"Удалить\" />"
             . "<br>Описание/детали о таблице <textarea name='dt_tbdetails[]' class='form-control' style='height:60px;overflow:auto' >$tbdesc</textarea></td></tr>";
        }
		$body = <<< EOHTM
<form id="fm_mcpassistant">
<br>
<div class="card w-800 mx-auto">
    <div class="card-body">
    <table class="table" style="width:100%" id="t_frm_content">
    <tr>
     <td>Формат настроечного файла<br>
     <select name="cfgtype" class="form-select" id="cfgtype" onchange="mcpassistant.chgCfgType()">
      $cfgOptions
     </select>
     </td>
    </tr>
    <tr>
      <td>список таблиц, для которых выдать описания<br>
      <input type="text" name="table_list" id="table_list" class="form-control" value="$table_list"/>
      </td>
    </tr>
    <tr>
       <td colspan="2"><label class='form-control bordered p-2'><input type="checkbox" name="make_english" id="make_english" value="1" onchange="mcpassistant.chgTranslate(this)" $chkEnglish">
        description - на английском яз.(нужно подкл. к LLM-переводчику!)</label></td>
    </tr>
    <tr class="tr_lm_translate" $show_llmtrans >
     <td>Выбрать настройку LLM для перевода<br>
       <select name="translator_model" class="form-select" id="translator_model" onchange="mcpassistant.chgTranslator()">
        <option value="">--Не выбрано--</option>
        $translator_options
       </select>

       Дополнительные инструкции для переводчика описаний
       <textarea name="translate_comments" id="translate_comments" class="form-control" style="height:120px; overflow:auto">$cur_translate_comments</textarea>
     </td>
    </tr>
    <tr>
      <td><input type="button" onclick="mcpassistant.addTableDetBlock()" class="btn btn-primary" value="Добавить описание таблицы" />
      </td>
    </tr>
    $initTableDefs
    <!--
    <tr>
       <td colspan="2"><label class='bordered p-2'>EDO-ПЭП версия данных <input type="checkbox" name="b_edo" id="b_edo" value="1"></label></td>
    </tr>
    -->
  </table>
    </div>
    <div class="area-buttons card-footer">
        <input type="button" onclick="mcpassistant.startGenerate()" id="btn_run" class="btn btn-primary w200" value="Генерировать" />
        <span class="rt"><input type="button" onclick="mcpassistant.saveConfig()" id="btn_save" class="btn btn-primary w200" value="Сохранить настройки" /></span>
    </div>
</div>
</form>
EOHTM;

		appEnv::appendHtml($body);
		appEnv::finalize();
	}
	/**
	* Создает и бросает в поток клиенту JSON/YAML/... файл с наполненной настройкой MCP tool-зы
	*/
	public function performGenerate() {
        $this->cfg = $cfg = $this->saveConfig(1);
        if(!isset($this->cfg['make_english'])) $this->cfg['make_english'] = FALSE;
        $this->tables = preg_split('/[, ]/', $cfg['table_list'], -1, PREG_SPLIT_NO_EMPTY);
        # Если строчный список не заполнен, заливаю из указанного перечня "уточнений" к таблицам
        if(!count($this->tables)) {
            if(isset($cfg['table_desc']) && count($cfg['table_desc'])) {
                foreach($cfg['table_desc'] as $item) $this->tables[] = $item['tablename'];
            }
        }

        switch($this->cfg['cfgtype']) {
            case 'json_lmstudio':
                $this->generate_json_lmstudio();
                break;
            case 'yaml_anythingllm':
                $this->generate_yaml_anythingllm();
                break;
        }
        exit;
	}

    public function generate_json_lmstudio($cfg) {
        include_once('astedit.datatables.php');
        $tableBlock = '';
        $fmt = 'object'; # TODO: предусмотреть "текстовое описание, как предложила mashaGPT 'text'
        $tplConfigName = __DIR__ . '/templates/mcp-lmstudio-tpl.json';
        $tplJson = @file_get_contents($tplConfigName);

        $baseCfg = json_decode($tplJson, TRUE);
        foreach($this->tables as $tbname) {
            $oneTable = $this->getTableDescription($tbname, $fmt);
            $baseCfg['description']['tables'][] = $oneTable;
        }

        $body = json_encode($arMCPconfig, (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        # TODO: загрузка описаний осн.таблиц, формирование description с их списком и описанием полей
        # PM::T_INDIVIDUAL, PM::T_POLICIES, PM::T_AGRISKS, PM::T_RISKS;
        AppEnv::sendBinaryFile('mcp-config.json', '', $body);
    }

    public  function generate_yaml_anythingllm($cfg) {
        exit("TODO: yaml for AnythingLLM is under construction!");
    }

    public function loadConfig() {
        $cfgName = AppEnv::getAppFolder('cfg/') . self::CFG_FILENAME;
        if(is_file($cfgName)) {
            $jsBody = file_get_contents($cfgName);
            $arRet = json_decode($jsBody, TRUE);
            # writeDebugInfo("$jsBody, as array: ", $arRet);
        }
        else {
            $arRet = [];
            # writeDebugInfo("no cfg $cfgName");
        }
        return $arRet;
    }

    public static function cfgTypeList() {
        return [
          [ '', '--Не выбрано--' ],
          [ 'json_lmstudio', 'JSON for LM STUDUO' ],
          [ 'yaml_anythingllm', 'YAML for AnythingLLM' ],
        ];
    }

    # AJAX - пришла команда сохранить настройки
    public function saveConfig($intCall=FALSE) {
        $cfgName = AppEnv::getAppFolder('cfg/') . self::CFG_FILENAME;
        writeDebugInfo("params ",AppEnv::$_p);
        $arSave = [
          'cfgtype' => (AppEnv::$_p['cfgtype'] ?? 'json_lmstudio'),
          'table_list' => (AppEnv::$_p['table_list'] ?? ''),
          'make_english' => (AppEnv::$_p['make_english'] ?? ''),
          'translator_model' => (AppEnv::$_p['translator_model'] ?? ''),
          'translate_comments' => (AppEnv::$_p['translate_comments'] ?? ''),
        ];
        $tables = AppEnv::$_p['dt_tbname'] ?? [];
        $tableTxt = AppEnv::$_p['dt_tbdetails'] ?? [];
        if(count($tables)) {
            $arSave['table_desc'] = [];
            foreach($tables as $no => $tabname) {
                if(!empty($tableTxt[$no]))
                    $arSave['table_desc'][] = ['tablename' => $tabname, 'table_desc'=> $tableTxt[$no] ];
            }
        }
        # TODO: список описаний по таблицам
        # writeDebugInfo("params, ", AppEnv::$_p);
        $jsonBody = json_encode( $arSave, (JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) );
        $saved = file_put_contents($cfgName, $jsonBody);
        if($intCall) return $arSave; # в генераторе буду использовать конфиг уже  с внутренними ключами
        if($saved) exit("1" . AjaxResponse::timedNotify("Конфиг сохранен",2));
        else exit('1' . \AjaxResponse::showError('Ошибка при сохранении настроек'));
    }

    public function findDetails($tableName) {
        if(!isset($this->cfg['table_desc']) || !count($this->cfg['table_desc'])) return '';
        foreach($this->cfg['table_desc'] as $row) {
            if($row['tablename'] == $tableName) return $row['table_desc'];
        }
        return '';
    }

    public function getTableDescription($tableName, $fmt) {
        $astObj = new CTableDefinition($tableName);
        if($fmt === 'object') {
            $arRet = [
              'name' => $tableName,
              'description' => $astObj->desc,
              'columns' =>[]
            ];
            if($details = $this->findDetails($tableName))   $arRet['description'] .= ". " . $details;
            foreach( $astObj->fields as $fldid => $flDef ) {
                $ftype = $flDef->type;
                if(in_array($ftype, ['VARCHAR','CHAR','DECIMAL']))
                    $ftype .= "($flDef->length)";
                if($astObj->_pkfield == $fldid)
                    $ftype .= ", Primary Key";
                # writeDebugInfo("text, $fldid: ", $flDef);
                if($this->cfg['make_english']) {
                    $descr = $this->tranclateString($flDef->desc);
                }
                else $descr = $flDef->desc;
                $arFld = ['name'=>$fldid, 'type'=>$ftype, 'description'=> $descr ];
                $arRet['columns'][] = $arFld;
            }
            return $arRet;
        }
        elseif($fmt === 'text') {
            $ret = "Table: $tableName\n";
            foreach( $astObj->fields as $fldid => $flDef ) {
                $ftype = $flDef->type;
                if(in_array($ftype, ['VARCHAR','CHAR','DECIMAL']))
                    $ftype .= "($flDef->length)";
                if($astObj->_pkfield == $fldid)
                    $ftype .= ", Primary Key";
                # writeDebugInfo("text, $fldid: ", $flDef);
                $strFld = "  - $fldid $ftype - $flDef->desc\n";
                $ret .= $strFld;
            }
        }
        return $ret;
    }
    public function tranclateString($strg) {
        # TODO: call local LLM configured as simple translator
        return $strg;
    }
    # AJAX запрос на список шаблонов в папке
    public static function getTemplateList() {
        $folder = appEnv::$_p['folder'];
        $ret = '1|';
        if($folder) {
            $fullFolder = appEnv::getAppFolder($folder);
            foreach(glob($fullFolder.'*.xml') as $fName) {
                $baseNm = substr(baseName($fName), 0,-4);
                $ret .= "<option>$baseNm</option>";
            }
        }
        exit($ret);
    }
}
