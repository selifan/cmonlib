<?php
/**
* @name libs/pollutils.php
* Отрисовка HTML форм анкет/опросов по XML настройке списка вопросов
* @version 0.10.001
* modified 2024-07-19
*/
namespace Libs; # всё что в папке libs - с таким namespace !

class PollUtils {
    const VERSION = '0.1';
    public $questions = [];
    private $title = '';
    private $_publishFn = ''; # send form js callback function

    public function __construct($source='') {
        if(!empty($source)) $this->loadConfig($source);
    }
    public static function getVersion() {  return self::VERSION; }

    # загружает и парсит список вопросов/настроек из XML
    public function loadConfig($src) {
        $this->questions = [];
        if(is_file($src))
            $xml = @simplexml_load_file($src);
        elseif(substr($src, 0, 5) == '<?xml')
            $xml = @simplexml_load_string($src);
        else {
            $xml = FALSE;
            exit("No file $src");
        }

        if(!$xml) return "Passed parameter is not a file name or XML string";
        if(!isset($xml->questions)) return 'ERROR: Bad XML, no questions block';
        $this->title = isset($xml->title) ? (string)$xml->title : 'Poll Title';
        foreach($xml->questions->children() as $key => $item) {
            if($key !=='item') continue;
            $id = (string) $item['name'];
            if(isset($item['prompt'])) $prompt = (string)$item['prompt'];
            elseif(isset($item->prompt))$prompt = (string)$item->prompt;
            else $prompt = "$id no prompt!";
            $elem = [
              'no' => (string) $item['no'],
              'name' => (string) $item['name'],
              'type' => (isset($item['type']) ? (string)$item['type'] : 'string'),
              'explanation' => (isset($item['explanation']) ? (int)$item['explanation'] : FALSE),
              'required' => (isset($item['required']) ? (int)$item['required'] : FALSE),
              'prompt' => $prompt,
              'min' => (isset($item['min']) ? (string)$item['min'] : NULL),
              'max' => (isset($item['max']) ? (string)$item['max'] : NULL),
              'if' => (isset($item['if']) ? (string)$item['if'] : NULL),
              'cols' => (isset($item['cols']) ? (string)$item['cols'] : NULL),
              'coltypes' => (isset($item['coltypes']) ? (string)$item['coltypes'] : NULL),
            ];
            if($elem['if'])
                $elem['ifvalues'] = (isset($item['ifvalues']) ? (string)$item['ifvalues'] : 'Y');

            if(isset($item->options)) $elem['options'] = $this->parseOptions($item->options);
            elseif(isset($item['options'])) $elem['options'] = (string)$item['options'];
            $this->questions[$id] = $elem;
        }
        return TRUE;
    }
    # разиьраю XML блок <options/> с вариантами ответов
    public function parseOptions($element) {
        $no = 0;
        $arRet = [];
        foreach($element->children() as $key=>$optItem) {
            $no++;
            $arRet[] = [
              'value' => (string)($optItem['value'] ?? $no),
              'prompt' => (string)($optItem['prompt'] ?? "Вариант $no"),
              'description' => (string)($optItem['description'] ?? ''),
            ];
        }
        return $arRet;
    }
    /**
    * генерит HTML форму ввода ответов на вопросы анкеты/опроса
    * @param mixed $id - ID для окружающего div и тега <form id=... >
    * @param mixed $publishFn - javascript callback для вызова для выполнения финальной отправки на сервер
    * @param mixed $skipJs - если передать TRUE|1, JS код к форме не прицепится (надо сгенерить отдельным вызовом getJsCode() и вывести самому в нужном месте
    */
    public function renderForm($id = 'fm_poll', $publishFn = '', $skipJs = FALSE) {
        $divid = "div_".$id;
        $this->_publishFn = $publishFn;
        if(!$skipJs) {
            $js = $this->getJsCode();
            $body = "<script type=\"text/javascript\">\n$js\n</script>";
        }
        else $body = '';
        $body .= "<form id=\"$id\" class='was-validated'><div id=\"$divid\" class=\"p-3\"><table class=\"table table-bordered align-middle\">";
        $explanationBlocks = '';

        foreach($this->questions as $fldid => $item) {
            $required = $item['required'] ? 'required':'';
            $rowClass = '';
            if(!empty($item['if'])) $rowClass = 'class="hideme"';
            $lpad = '';
            $rowNo = '';
            if(strpos($item['no'], '.')) {
                $lpad = "style='padding-left:2em;'";
            }
            else $rowNo = $item['no'] . '. ';
            $explain = '';
            switch($item['type']) {
                case 'yesno':
                  $input = "<div class='d-md-flex form-check'><div class='d-flex align-items-center p-3 pe-4 '><input type=\"radio\" name=\"$fldid\" id=\"{$fldid}_no\" class='d-inline wh25 form-check-input' required value=\"N\" onchange='poller.chgYesNo(this)'><label for=\"{$fldid}_no\">&nbsp;НЕТ</label></div>"
                  . "<div class='d-flex align-items-center p-3'><input type=\"radio\" name=\"$fldid\" id=\"{$fldid}_yes\" class='d-inline wh25 form-check-input' required value=\"Y\" onchange='poller.chgYesNo(this)'><label for=\"{$fldid}_yes\">&nbsp;ДА</label></div></div>";
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad>{$rowNo}$item[prompt]</td><td class='nowrap'>$input</td><tr>";
                    if($item['explanation']) {
                        # $explanationBlocks .=
                        $explain = "<tr id=\"trexp_{$fldid}\" class=\"hideme\"><td colspan=\"2\">Пояснение к вопросу:<br>"
                        . "<textarea name=\"{$fldid}_exp\" id=\"{$fldid}_exp\" maxlength=\"$item[max]\" required class=\"form-control poll_explain\" rows='4' ></textarea></td></tr>\n";
                    }
                    break;
                case 'radio':
                    # {upd/2024-07-19} поле типа RADIO на несколько вариантов ответа
                    $input = '';
                    $nomer = 0;
                    if(!empty($item['options']) && is_array($item['options']))
                    foreach($item['options'] as $radioItem) {
                        $nomer++;
                        $rVal = $radioItem['value'];
                        $rPrompt = $radioItem['prompt'];
                        $rDescr = $radioItem['description']; # TODO: выводить поле для ввода доп-инфо при данном ответе
                        $input .= "<div class='d-flex p-3'><input type=\"radio\" class='d-inline form-check-input wh25' name=\"$fldid\" id=\"{$fldid}_{$rVal}\" required value=\"$rVal\" "
                         . "onchange='poller.chgYesNo(this)'>&nbsp;<label for=\"{$fldid}_{$rVal}\">$rPrompt</label></div>";
                    }
                    // $input = implode('<br>',$input);
                    $hiddenInput = "<input type=\"text\" hidden name=\"{$fldid}_question\" id=\"{$fldid}_question\" value=\"$item[prompt]\" />";

                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad>{$rowNo}$item[prompt]$hiddenInput</td><td class='nowrap'>$input</td><tr>";
                    break;
                case 'checkbox':
                    $input = '';
                    $nomer = 0;
                    if(!empty($item['options']) && is_array($item['options']))
                    foreach($item['options'] as $radioItem) {
                        $nomer++;
                        $rVal = $radioItem['value'];
                        $rPrompt = $radioItem['prompt'];
                        $input .= "<div class='d-flex p-3'><input type=\"checkbox\" class='d-inline form-check-input wh25' name=\"$rVal\" id=\"$rVal\" value=\"$rPrompt\">&nbsp;<label for=\"$rVal\">$rPrompt</label></div>";
                    }
                    $hiddenInput = "<input type=\"text\" hidden name=\"{$fldid}question\" id=\"{$fldid}question\" value=\"$item[prompt]\">";

                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad>{$rowNo}$hiddenInput</td><td class='nowrap'>$input</td><tr>";
                    break;
                case 'string':
                    $input = "<input type=\"text\" name=\"$fldid\" id=\"{$fldid}\" $required class=\"form-control w200\">";
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad colspan=\"2\">{$rowNo}$item[prompt]<br>$input</td><tr>";
                    break;
                case 'text':
                    $input = "<textarea name=\"$fldid\" id=\"{$fldid}\" class=\"form-control\" style=\"width:100%; height:3em;\"></textarea>";
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad colspan=\"2\">{$rowNo}$item[prompt]<br>$input</td><tr>";
                    break;
                case 'title':
                  $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad colspan=\"2\">{$rowNo}$item[prompt]</td></<tr>";
                  break;
                case 'table':
                    $cols = explode("|", $item['cols']);
                    $coltypes = explode("|", $item['coltypes']);
                    $tbody = "";
                    foreach ($cols as $index => $col) {
                      $tbody .= "<tr><td width='45%'>$col</td>";
                      $name = $fldid . $index . "0";
                      $types = [
                        'year' => "<input type=\"number\" min=\"1930\" max=\"2050\" name=\"$name\" class=\"form-control w100 \" />",
                        'date' => "<input type=\"date\" min=\"1950-01-01\" max=\"2050-01-01\" name=\"$name\" class=\"form-control w150 text-center datefield\">",
                        'text' => "<textarea name=\"$name\" class=\"form-control\" rows=\"1\"></textarea>",
                      ];
                      $tbody .= "<td>" . $types[$coltypes[$index]] . "</td></tr>";
                    }
                    $table = "<table class='table table-bordered {$fldid}table'>$tbody</table>";
                    $addbtn = " <button name=\"{$fldid}btn\" id=\"{$fldid}btn\" class=\"ms-auto btn btn-secondary\" type=\"button\" onclick=\"poller.addRow(this)\">+</button>";
                    $addbtn = $item['explanation'] ? '' : $addbtn;
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad colspan=\"2\">{$rowNo}$item[prompt]<br>$table $addbtn</td></tr>";
                    break;
                case 'int':
                    $input = "<input type=\"number\" min=\"$item[min]\" max=\"$item[max]\" name=\"$fldid\" id=\"{$fldid}\" $required class=\"form-control w100\">";
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad>{$rowNo}$item[prompt]</td><td class='nowrap'>$input</td><tr>";
                    break;
                case 'date':
                    $input = "<input type=\"text\" name=\"$fldid\" id=\"{$fldid}_in\" class=\"form-control w120 text-center datefield\">";
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad>{$rowNo}$item[prompt]</td><td class='nowrap'>$input</td><tr>";
                    break;
                default:
                    $htmlRow = "<tr id=\"row_{$fldid}\" $rowClass><td $lpad>{$rowNo}$item[prompt]</td><td class='nowrap'>$fldid: unsupported type $item[type]</td><tr>";
                    break;
            }
            $body .= $htmlRow . $explain;
        }
        $body .= $explanationBlocks;
        $body .= "</table></div><div class='card-footer'><input type=\"button\" id=\"btn_sendAnketa\" class=\"btn btn-primary\" value=\"Подписать и отправить\" onclick=\"poller.sendForm()\"/></div></form>";
        return $body;
    }
    public function getJsCode() {
        $flist = [];
        $depend = [];
        foreach($this->questions as $key => $item) {
            if(!empty($item['if'])) {
                $if = $item['if'];
                $ifvalues = $item['ifvalues'] ?? 'Y';
                $jsValues = explode(',', $ifvalues);
                $finalValues = "['" . implode("','",$jsValues) . "']";

                if(!isset($depend[$if])) $depend[$if] = [];
                $depend[$if][] = "['$key',$finalValues]";
            }
        }
        foreach($this->questions as $key => $item) {
            $strDepend = isset($depend[$key]) ? ('[' . implode(',',$depend[$key]) . ']') : 'false';
            $explVal = $item['explanation'] ? '1': '0';
            $required = $item['required'] ? '1': '0';
            $flist[] = "\"$key\": { type: '$item[type]', required: $required, explanation: $explVal, depend: $strDepend }";
        }
        $flistStr = implode(",\n  ", $flist);

        $publishFn = ($this->_publishFn) ? $this->_publishFn : 'poller.dummySendForm';

        $ret = <<< EOJS
poller = {
  fields: {
  $flistStr
  },
  chgYesNo: function(obj) {
    var vName = obj.name;
    var vValue = obj.value;
    $("tr#row_"+vName).removeClass("warnerr");

    // console.log("changed "+vName+ " value:",vValue);
    if(poller.fields[vName].explanation) {
        // console.log("show/hide explanation for trexp_"+vName);
        if(vValue == 'Y') $("#trexp_"+vName).removeClass('hideme');
        else if(vValue == 'N') {
          $("#trexp_"+vName).addClass('hideme');
        }
    }
    if(poller.fields[vName]['depend']) for(var idep=0;idep<poller.fields[vName]['depend'].length;idep++) { // subid in poller.fields[vName]['depend'])
      var subname = poller.fields[vName]['depend'][idep][0];
      var valList = poller.fields[vName]['depend'][idep][1];
      var showItem = false;
      if(valList.indexOf(vValue)>=0) showItem = true;
      if(showItem) $("#row_"+subname).removeClass("warnerr").removeClass('hideme');
      else $("#row_"+subname).addClass('hideme');
    }
  },
  checkForm: function() {
    var bErr = false;
    var yesRadio = ["yesno" , "radio"];
    console.log(poller.fields);
    for(fname in poller.fields) {
      var fType = poller.fields[fname]['type'];
      if(yesRadio.includes(fType)) {
        var fVal = $("input[name="+fname+"]:checked").val();
        if(!fVal) {
          bErr = true;
          $("tr#row_"+fname).addClass("warnerr");
        }

        if(poller.fields[fname]['depend']) {
          for(var subid=0; subid<poller.fields[fname]['depend'].length; subid++) {
            var subname = poller.fields[fname]['depend'][subid][0];
            var okList  = poller.fields[fname]['depend'][subid][1];
            if(okList.indexOf(fVal)>=0 && $("#"+subname).val() == '') {
              bErr = true;
              $("#row_"+subname).addClass("warnerr");
            }
            else {
              $("#row_"+subname).removeClass("warnerr");
            }
          }
          if(poller.fields[fname].explanation) {
            // console.log("explanation for ", fname, ' value: ',$("#"+fname+"_exp").val());
            if($("#"+fname+"_exp").val() == '') {
              bErr = true;
              $("#trexp_"+fname).addClass("warnerr");
            }
            else {
              $("#trexp_"+fname).removeClass("warnerr");
            }
          }
        }
        else {
          if(poller.fields[fname]['depend']) for(subid in poller.fields[fname]['depend']) {
            subname = poller.fields[fname]['depend'][subid];
            $("#row_"+subname).removeClass("warnerr");
          }
        }
        if(poller.fields[fname].explanation) {
          var ynVal = $("input[name='" + fname + "']:checked").val();
          if(ynVal == "Y" && $("#"+fname+"_exp").val() == ''){
            bErr = true;
            $("#trexp_"+fname).addClass("warnerr");
          }
          else {
            $("#trexp_"+fname).removeClass("warnerr");
          }
        }
        
      }
      else if (poller.fields[fname]['required']) {
        if($("input#"+fname).val() == '') {
          bErr = true;
          $("tr#row_"+fname).addClass("warnerr");
        }
        else {
          $("tr#row_"+fname).removeClass("warnerr");
        }
      }
    }
    return (!bErr);
  },
  sendForm: function() {
    var bChecked = this.checkForm();
    if(bChecked) dlgConfirm("Отправить заполненные данные ?", $publishFn, false);
    else {
      showMessage("Ошибки", "Не все ответы выбраны/заполнены!", "msg_error");
      var bt = $(".msg_error button.ui-button-text-only");
      bt.attr("onclick", "poller.addEv('sd')");
    }
  },
  dummySendForm: function() {
    TimeAlert('Внимание! Не передано имя callBack функции для отправки данных!', 4, 'msg_error');
  },
  addEv: function(l){
    var targetElement = $(".warnerr");
    var targetOffset = targetElement.offset().top - 40;
    $('html, body').animate({
      scrollTop: targetOffset
    }, 600);
  },
  addRow: function(obj) {
    let vName = obj.name;
    let classname = vName.replace("btn", "table");
    let tr = $("table." + vName.replace("btn", "table:last") + " tbody").html();
    let name = vName.replace("btn", "");
 
    let final = tr.split(/"(.*?)"/g).filter(v => v.startsWith(name))
    final.forEach((rep) => {
      let newName = rep.slice(-1);
      newName++;
      tr = tr.replace(rep, rep.slice(0, -1) + newName);
    })

    let td = "<table class='table table-bordered " + classname + "'>" + tr + "</table>";
    $("table." + vName.replace("btn", "table") + ":last").after(td);
  },
};
EOJS;
        // file_put_contents('_jsPoll.log', $ret);
        return $ret;
    }
}
