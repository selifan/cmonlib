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

    const PDVERSION = 2; #

    public function form() {
		appEnv::setPageTitle('MCP assistant, генератор файлов настройки MCP утилит для LLM');
        include_once('astedit.datatables.php');
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
        	$foldname = basename($fold);
            if(is_dir("$fold/printcfg")) {
                # если есть папка printcfg, считаю, что все XML-конфиги и PDF-шаблоны лежат в ней!
                $foldname .= '/printcfg';
                $fold .= '/printcfg';
            }
        	$xmls = glob("$fold/*.xml");
        	if (count($xmls)) { # count($xmls)
				$strlist = '1'; # $this->makeJsList($xmls);
				# WriteDebugInfo("$fold = $foldname: $strlist");
				# if ($strlist) {
					$selItems .= "\n<option>$fold</option>";
				# }
			}
		}
        $aiCfgList = \libs\AiBus::listOpenAiConfigs();

        $translator_options = ''; # '<option value="mumba_umba">Mumba Umba model</option>'; # Пока лажа
        if(is_array($aiCfgList) && count($aiCfgList)) foreach($aiCfgList as $oneCfg) {
            $translator_options .= "<option value=\"$oneCfg[0]\">$oneCfg[1]</option>";
        }

		$js = <<< EOJS
mcpassistant = {
   startGenerate: function() {
      var prms = $('#fm_mcpassistant').serialize();
      window.open('./?plg=mcpassistant&action=performGenerate&'+prms,'_blank');
   },
   chgCfgType: function() {
     var vForm = $("select#cfgtype").val();
     $("#btn_run").prop("disabled", (vForm==""));
   },
   chgTranslator: function() {
     var vTrans = $("select#translator_model").val();
     $("#make_english").prop("disabled", (vTrans==""));
   }
};
EOJS;
        addJsCode($js);
		$body = <<< EOHTM
<form id="fm_mcpassistant">
<br>
<div class="card w-600 mx-auto">
    <div class="card-body">
    <table class="table" stylr="width:100%">
    <tr>
     <td>Формат настроечного файла<br>
     <select name="cfgtype" class="form-select" id="cfgtype" onchange="mcpassistant.chgCfgType()">
      <option value="">--Не выбрано--</option>
      <option value="json_lmstudio" selected="selected">JSON for LM STUDUO</option>
      <option value="yaml_anythingllm">YAML for AnythingLLM</option>
     </select>
     </td>
    </tr>
    <tr>
       <td colspan="2"><label class='form-control bordered p-2'><input type="checkbox" name="make_english" id="make_english" value="1" disabled="disabled">
        description - на английском яз.(нужно подкл. к LLM-переводчику!)</label></td>
    </tr>
    <tr>
     <td>Выбрать настройку LLM для перевода<br>
     <select name="translator_model" class="form-select" id="translator_model" onchange="mcpassistant.chgTranslator()">
      <option value="">--Не выбрано--</option>
      $translator_options
     </select>
     </td>
    </tr>

    <!--
    <tr>
     <td colspan="2">
       <label class='bordered p-2'>Настроечная сетка (Ruler) <input type="checkbox" name="b_ruler" id="b_ruler" value="1" checked="checked"></label>
       &nbsp;
       <label class='bordered p-2'>Откл.штампы <input type="checkbox" name="b_nostamp" id="b_nostamp" value="1"></label>
       &nbsp;
       <label class='bordered p-2'>Закрасить блоки с высотой <input type="checkbox" name="b_fillblocks" id="b_fillblocks" value="1" checked="checked"></label>
     </td>
    </tr>
    <tr>
       <td colspan="2"><label class='bordered p-2'>EDO-ПЭП версия данных <input type="checkbox" name="b_edo" id="b_edo" value="1"></label></td>
    </tr>
    -->
  </table>
    </div>
    <div class="area-buttons card-footer">
        <input type="button" onclick="mcpassistant.startGenerate()" id="btn_run" class="btn btn-primary w200" value="Генерировать" __disabled="disabled" />
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
        $body = json_encode(AppEnv::$_p, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
        # TODO: загрузка опианий осн.таблиц, формирование description с их списком и описанием полей
        # PM::T_INDIVIDUAL, PM::T_POLICIES, PM::T_AGRISKS, PM::T_RISKS;
        AppEnv::sendBinaryFile('output.json', '', $body);
        exit;
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
