<?php
/**
* @package ALFO
* @name libs/htmlelements.php
* Мелкие функции генерации кусков HTML кода
* @version 1.20.001
* modified 2025-11-06
*/
namespace Libs;
class HtmlElements {
    public static function parseOptionList($strList) {
        if(substr($strList,0,1)==='@') {
            $callback = substr($strList, 1);
            if(is_callable($callback)) return call_user_func($callback);
            else return [['-', "$callback() not defined!"]];
        }
        $pairs = explode(';', $strList);
        $arRet = [];
        foreach($pairs as $elem) {
            $subItems = explode('=',$elem);
            $arRet[] = [ $subItems[0], ($subItems[1] ?? $subItems[0]) ];
        }
        return $arRet;
    }
    /**
    * Формирует блок опций &lt;option&gt;...&lt;/option&gt; для элемента &lt;select&gt;, из переданного списка опций (массив или строка в виде "val1=Text1;...")
    *
    * @param array $options - data array (two-dimensional or associative), if first value in row is 'group', it starts &lt;optgroup&gt; element
    * @param mixed $initval - value that should be "selected" initially
    * @return mixed string or nothing
    */
    public static function selectOptions($options,$initval=FALSE) {
        if(is_string($options)) $options = self::parseOptionList($options);
        if(!is_array($options) || count($options)<1) return '';
        $ret = '';
        $ingroup = false;
        foreach($options as $key=>$val) {
        if(is_string($val)) {
            $vval = $key;
            $vtext = $val;
        }
        else {
            if(is_array($val) && count($val)>1) {
                $vval = array_shift($val);
                $vtext = array_shift($val);
            }
            else {
              $vtext = is_array($val) ? $val[0] : $val;
              $vval = (is_numeric($key) ? $vtext : $key);
            }
        }

        $vtext = htmlentities($vtext, (ENT_COMPAT | ENT_HTML401), MAINCHARSET);

        if($vval==='group' || $vval==='<group>' || $vval==='<' ) {
            if($ingroup) $ret .='</optgroup>';
            $htmltext="<optgroup label=\"$vtext\">";
            $ingroup = true;
        }
        else {
            $sel = ($initval!==false && "$initval"==="$vval")? ' selected="selected"':'';
            $htmltext = "<option value='$vval'{$sel}>$vtext</option>\n";
        }
        $ret .= $htmltext;
        }
        if($ingroup) $ret .='</optgroup>';

        return $ret;
    }
    /**
    * Формирует блок кадио-кнопок с заданным именем поля
    *
    * @param mixed $varname
    * @param mixed $options
    * @param mixed $initval
    * @return mixed
    */
    public static function radioOptions($varname, $options,$initval=FALSE) {
        if(is_string($options)) $options = self::parseOptionList($options);
        if(!is_array($options) || count($options)<1) return '';
        $ret = '';
        foreach($options as $key=>$val) {
            if(is_string($val)) {
                $vval = $key;
                $vtext = $val;
            }
            else {
                if(is_array($val) && count($val)>1) {
                    $vval = array_shift($val);
                    $vtext = array_shift($val);
                }
                else {
                  $vtext = is_array($val) ? $val[0] : $val;
                  $vval = (is_numeric($key) ? $vtext : $key);
                }
            }
            $checked = ($vval == $initval) ? 'checked="checked"' : '';
            $vtext = htmlentities($vtext, (ENT_COMPAT | ENT_HTML401), MAINCHARSET);
            $ret .= "<label><input type=\"radio\" name=\"$varname\" value=\"$vval\" $checked>$vtext</label> ";
        }
        return $ret;
    }
    /**
    * {upd/2025-11-06}
    * Формирует таблицу с набором чек-боксов с именами filed[]
    * @param mixed $fldName
    * @param mixed $optList
    */
    public static function drawMultiSelect(string $fldName, array $optList) {
        $strRet = '';
        foreach($optList as $rowid => $item) {
            if(is_array($item)) {
                if(count($item) == 1) {
                    $myId = $rowid;
                    $myLabel = array_shift($item);
                }
                else {
                    $myId = array_shift($item);;
                    $myLabel = array_shift($item);
                }
            }
            else {
                $myId = $rowid;
                $myLabel = (string)$item;
            }
            $strRet .= "<tr><td><label><input type=\"checkbox\" name=\"{$fldName}[]\" value=\"$myId\"> $myLabel</label></td></tr>\n";
        }
        return "<table class='zebra'>$strRet</table>";
    }
}
