<?php
/**
* @name class.paramdef.php
* @version 0.01.002
* modified 2023-12-18
*/
class ParamDef {
    protected $params = [];

    public function __construct($xmlName = '') {
        if ($xmlName) $this->load($xmlName);
    }
    /**
    * Load Parameters definitions from XML file or XML string
    *
    * @param mixed $xmlName
    * @param mixed $data
    */
    public function load($xmlName) {

        if(is_string($xmlName)) {
            if (is_file($xmlName))
                $xml = @simplexml_load_file($xmlName);
            elseif(substr($xmlName,0,5) === '<?xml')
                $xml = @simplexml_load_string($xmlName);
            # else die("$xmlName - unsupported string or no such file");
        }
        elseif(is_object($xmlName))
            $xml = $xmlName;

        if (!is_object($xml)) return FALSE;

        $this->params = [
          'fields' => [],
          'script' => '',
          'comment' => FALSE,
          'form' => FALSE,
        ];

        foreach($xml->children() as $key=>$fdef) {
            if ($key === 'field') {
                $fname = isset($fdef['name']) ? (string)$fdef['name'] : '';
                if(!$fname) continue;
                $ftype = isset($fdef['type']) ? strtolower((string)$fdef['type']) : 'text';
                $format = isset($fdef['format']) ? strtolower((string)$fdef['format']) : '';
                $width = isset($fdef['width']) ? strtolower((string)$fdef['width']) : FALSE;
                $className = isset($fdef['class']) ? (string)$fdef['class'] : '';
                $onchange = isset($fdef['onchange']) ? (string)$fdef['onchange'] : '';
                $class = isset($fdef['class']) ? (string)$fdef['class'] : '';
                $onclick = isset($fdef['onclick']) ? (string)$fdef['onclick'] : '';
                $label = isset($fdef['label']) ? (string)$fdef['label'] : $fname;
                $title = isset($fdef['title']) ? (string)$fdef['title'] : '';

                $parval = isset($fdef['value']) ? (string)$fdef['value'] : FALSE;
                $options = isset($fdef['options']) ? (string)$fdef['options'] : '';
                $required = isset($fdef['required']) ? (string)$fdef['required'] : '';
                $this->params['fields'][$fname] = [
                  'type' => $ftype,
                  'format' => $format,
                  'width' => $width,
                  'class' => $className,
                  'onclick' => $onclick,
                  'onchange' => $onchange,
                  'label' => $label,
                  'title' => $title,
                  'options' => $options,
                  'value' => $parval,
                  'required' => $required
                ];
            }
            elseif($key === 'comment') { # строка текста
                $this->params['comment'] = $fdef['value'];
            }
            elseif($key === 'script') { # Блок JS
                $this->params['script'] = (string) $fdef;
            }
            elseif($key === 'template') { # Блок HTML template
                $this->params['template'] = (string) $fdef;
            }
        }
        return $this->params;
    }
    public function getParams() {
        return $this->params;
    }

    # returnd just user field names as simple array
    public function getParamNames() {
        return array_keys($this->params['fields']);
    }
    public function getFields() {
        return $this->params['fields'];
    }

    # builds HTML code for all user parameters
    public function htmlForm($curvalues = FALSE) {
        $ret = '<table style="width:100%">';
        $subst = [];
        foreach($this->params['fields'] as $fname => $par) {
            if (strpos($fname, '[')) { # 'USD' => 1
                $keyVals = preg_split('/[\[\]]/',$fname,-1,PREG_SPLIT_NO_EMPTY);
                $curValue = isset($curvalues[$keyVals[0]][$keyVals[1]]) ? $curvalues[$keyVals[0]][$keyVals[1]] : 0;
            }
            else $curValue = isset($curvalues[$fname]) ? $curvalues[$fname] : '';

            $className = $par['class'];
            $onclick = $par['onclick'];
            $onchange = $par['onchange'];
            $title = $par['title'];
            $label = $par['label'];
            $attrs = ($className ? "class=\"$className\"" : '')
              . ($onclick ? " onclick=\"$onclick\"" : '')
              . ($onchange ? " onchange=\"$onchange\"" : '')
              . ($title ? " title=\"$title\"" : '')
              . ($par['required'] ? ' required ' : '')
              ;
            $input = $fname;

            switch($par['type']) {
                case 'text': case 'string':
                    $input = "<input type=\"text\" name=\"$fname\" $attrs "
                      .  " value='".self::_encodeValue($curValue)."' />";
                    $ret .= "<tr><td class=\"rt\">$label</td><td>$input</td></tr>";
                    break;

                case 'checkbox':
                    $chkval = ($parval === '') ? '1' : $parval;
                    $chk = $curValue ? 'checked="checked"' : '';
                    $input = "<input type=\"checkbox\" name=\"$fname\" value=\"$chkval\" $attrs $chk />";
                    $ret .= "<tr><td class=\"rt\">$label</td><td>$input<td></tr></tr>";
                    break;

                case 'select':
                    $arrOptions = $this->parseOptionList($par['options']);
                    $input = "<select name=\"$fname\" $attrs >";

                    if (is_array($arrOptions)) foreach($arrOptions as $optKey=>$optData) {
                        $optVal = $optData['value'];
                        $optLabel = $optData['label'];
                        $input .= "\n<option value='$optVal'>$optLabel</option>";
                    }
                    $input .= "\n</select>";
                    break;

                case 'radio':
                    $arrOptions = $this->parseOptionList($par['options']);
                    $input = '';
                    if (is_array($arrOptions)) foreach($arrOptions as $optKey=>$optData) {
                        $optVal = $optData['value'];
                        $optLabel = $optData['label'];
                        $input .= "<label><input type='radio' name='$fname' id='{$fname}_{$optVal}' value='$optVal'> $optLabel</label> &nbsp;";
                    }
                    break;
                # TODO: select, textarea, radio
            }

            $subst['%'.$fname.'%'] = $input;
            $subst['%'.$fname.'_label%'] = $label;


        }
        if (isset($fdef['value'])) {
            $ret .= "<tr><td $attrs colspan='2'>" . (string) $fdef['value'] . "</td></tr>";
        }
        $ret .= '</table>';
        if (!empty($this->params['template']))
            $ret = strtr($this->params['template'], $subst);

        if(!empty($script)) $ret .= "<script type=\"text/javascript\">$script</script>";

        return $ret;
    }

    public function parseOptionList($options) {
        if (is_callable($options)) {
            $data = call_user_func($options);
            $arr = [];
            foreach($data as $key=>$row) {
                if (is_array($row) && count($row)>1) $arr[] = [ 'value'=>$row[0], 'label'=>$row[1] ];
                else $arr[] = ['value'=>$key, 'label'=>(is_array($row)?$row[0]:$row) ];
            }
        }
        else { # options listed in jqGrid style string: "value1:label1;value2:label2 ..."
            $items = explode(';', $options);
            $arr = [];
            foreach($items as $element) {
                $pair = explode(':', $element);
                $arr[] = ['value' => $pair[0], 'label' => (isset($pair[1]) ?$pair[1]: $pair[0])];
            }
        }
        return $arr;
    }
    private static function _encodeValue($val) {
        $subst = ['"' => ' '];
        return strtr($val, $subst);
    }

}
