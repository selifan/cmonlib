<?php
/**
* app/ajaxresponse
* Класс с ф-циями формирования блоков строк для отправки в AJAX клиенту
* @version 1.25.001
* @modified 2024-12-05
*/
class AjaxResponse {
    # у клиента покажет диалоговое "красное" окно с текстом ошибки
    public static function showError($errtext, $title='', $paramStrg = NULL, $dopPrm='') {
        if(empty($title)) $title = 'Ошибка!';
        else $title = AppEnv::getLocalized($title, $title);
        $outMsg = appEnv::getLocalized($errtext, $errtext);
        if($paramStrg !== NULL) {
            if(is_scalar($paramStrg)) # передан строчный параметр, надо вставить в строку с помощью sprintf
                $outMsg = sprintf($outMsg, $paramStrg);
            elseif(is_array($paramStrg)) # передан массив ['from' => 'to-string', делаю коныертацию по ключам
                $outMsg = strtr($outMsg, $paramStrg);
        }

        $dopStrg = ($dopPrm === '') ? '' : "\f$dopPrm";
        return "\tshowmessage\f{$outMsg}\f$title\fmsg_alarm".$dopStrg;
    }
    public static function exitError($errtext, $title='Ошибка!') {
        exit('1' . self::showError($errtext, $title));
    }
    # у клиента покажет диалоговое (нормальное) окно с текстом
    public static function showMessage($text, $title = FALSE, $class = FALSE, $paramStrg=NULL, $dopPrm='') {
        if($text === '') {
            writeDebugInfo("ERR: showMessage для передачи пустого текста, call stack: ", debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,3));
            return '';
        }
        if (empty($title)) $title = 'Соообщение от сервера';
        else $title = AppEnv::getLocalized($title, $title);
        $outMsg = appEnv::getLocalized($text, $text);

        if($paramStrg !== NULL) {
            if(is_scalar($paramStrg)) # передан строчный параметр, надо вставить в строку с помощью sprintf
                $outMsg = sprintf($outMsg, $paramStrg);
            elseif(is_array($paramStrg)) # передан массив ['from' => 'to-string', делаю коныертацию по ключам
                $outMsg = strtr($outMsg, $paramStrg);
        }

        if(empty($class)) $class = 'msg_ok';
        $dopStrg = ($dopPrm === '') ? '' : "\f$dopPrm";
        return "\tshowmessage\f{$outMsg}\f$title\f$class" . $dopStrg;
    }
    # заменить весь HTML код в элементе
    public static function setHtml($id, $htmlCode) {
        #if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
        #    $id = '#' . $id;
        return "\thtml\f$id\f$htmlCode";
    }
    # Добавить HTML код в конец кода в элементе
    public static function addHtml($id, $htmlCode) {
        return "\taddhtml\f$id\f$htmlCode";
    }
    public static function show($id) {
        if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
            $id = '#' . $id;
        return "\tshow\f$id";
    }
    public static function hide($id) {
        if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
            $id = '#' . $id;
        return "\thide\f$id";
    }
    # передаем установку значения одного поля ввода
    public static function setValue($id, $value='', $format='') {
        if(is_array($value) || is_object($value)) {
            writeDebugInfo("ajaxResponse ERR:[$id] value is array/object: ", $value, ' trace: ', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3));
            $value = implode(',', $value);
        }
        if ($format === 'money') $value = fmtMoney($value);
        elseif ($format === 'intmoney') $value = intMoney($value);
        return "\tset\f$id\f$value";
    }
    # пакуем весь массив вида fieldname => value в строку передачи
    public static function setAllValues($arValues) {
        $ret = '';
        foreach($arValues as $key => $val) {
            $ret .= self::setValue($key, $val);
        }
        return $ret;
    }

    public static function setProp($id, $propName='', $value='') {
        # "prop\f#FIELD_ID\fPROP_NAME\fPROP_VALUE"
        if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
            $id = '#' . $id;
        return "\tprop\f{$id}\f{$propName}\f{$value}";
    }
    public static function enable($id, $value=1) {
        if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
            $id = '#' . $id;
        return "\tenable\f$id\f$value";
    }
    public static function addClass($id, $class) {
        if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
            $id = '#' . $id;
        return "\taddclass\f$id\f$class";
    }
    public static function removeClass($id, $class) {
        if(substr($id,0,1) !== '#' && substr($id,0,1)!=='.')
            $id = '#' . $id;
        return "\tremoveclass\f$id\f$class";
    }
    public static function doEval($jsexpr) {
        return "\teval\f$jsexpr";
    }
    public static function execute($jsexpr) {
        return "\teval\f$jsexpr";
    }
    # перебросить браузер на указанный URL
    public static function gotoUrl($expr) {
        return "\tgotourl\f$expr";
    }
    # ssv: setting sessionStorage value
    public static function ssv($id, $value) {
        return "\tssv\f$id\f$value";
    }
    # lsv: setting localStorage value
    public static function lsv($id, $value) {
        return "\tlsv\f$id\f$value";
    }
    public static function confirm($htmlCode,$title='', $yesFunc='', $noFunc='') {
        if($title =='') $title = 'Подтверждение';
        $ret = "\tconfirm\f$title\f$htmlCode\f$yesFunc\f$noFunc";
        return $ret;
    }
    # вызов asJs.timedNotification(text [,time_sec])
    # @since 1.25 (2024-12-05)
    public static function timedNotify($strText, $time=0) {
        $locText = AppEnv::getLocalized($strText,$strText);
        return "\ttimednotify\f$locText" . (($time>0) ? "\f$time" : '');
    }
}