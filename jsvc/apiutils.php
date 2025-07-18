<?php
/**
* @name hsvc/apiutils.php
* модуль утилит для работы с API
* modified 2025-07-18 / created 2025-07-18
*/
namespace jsvc;
class apiUtils {
    static $skipEmptyParams = TRUE;
    /**
    * сохраняет в файл набор данных из калькулятора/редактора полиса для использования в тестах API
    */
    public static function saveCalcData() {
        $body = '$params = [';
        $module = '_noname_';
        $programid = '';
        $subtypeid = '';
        $no_benef = 1;
        foreach(\AppEnv::$_p as $key => $value) {
            if($key === 'plg' || $key === 'module') $module = $value;
            if($key === 'programid' || $key === 'program_id') $programid = $value;
            if($key === 'subtypeid' ) $subtypeid = $value;
            if($key === 'no_benef' ) $no_benef = $value;
            if(in_array($key, ['stmt_id','action','uw_confirmed','prolong','anketaid']) ) continue;

            if(substr($key,0,5) === 'benef' && $no_benef) continue; # ВП не сохраняем
            if($value=='' && self::$skipEmptyParams) continue;
            $body .= "\n  '$key' => '$value',";
        }
        $body .= "\n];\n\nreturn \$params;";
        $fname = __DIR__ . "/calcdata-$module" . ($programid!='' ? "-$programid" : '') . ($subtypeid!='' ? "-$subtypeid" : '') . ".php";
        $now = date('d.m.Y H:i:m');
        $result = file_put_contents($fname, "<?php\n/**\n* данные для тестов модуля $module, создано $now\n**/\n" . $body);
        return "$fname, size: $result";
    }
}

