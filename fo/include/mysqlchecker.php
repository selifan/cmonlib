<?php
/**
* @name mysqlchecker.php
* Checking amd repairing MySQL tables
* modified 2025-07-14 / created 2025-07-14
* mysqlcheck --repair --use-frm --all-databases
*/
class MysqlChecker {
    public static $VERSION = '0.1.001';
    static $shell_cmd = []; # commands that must be executed in the cmd line (OS shell)
    const ERR_TABLE_CRASHED = 144;

    public static function runCheck($dbNames = FALSE) {
        if(empty($dbNames)) {
            $databases = self::getAllDatabases();
        }
        elseif(is_string($dbNames)) $databases = explode(',',$dbNames);
        elseif(is_array($dbNames)) $databases = $dbNames;
        else throw new Exception ("wrong parameter databaseNames, array or string must be passed");
        $log = '';
        foreach($databases as $dbname) {
            $log .= self::checkOneDatabase($dbname);
        }
        return $log;
    }
    # get all database names excluding system ones
    public static function getAllDatabases() {
        $names = \AppEnv::$db->sql_query('SHOW DATABASES',TRUE,FALSE,TRUE);
        # return $names;
        $arRet = [];
        foreach ($names as $item) {
            if(in_array($item[0], ['mysql','performance_schema'])) continue;
            $arRet[] = $item[0];
        }
        return $arRet;
    }
    public static function checkOneDatabase($dbname) {
        CDbEngine::$FATAL_ERRORS = [];
        \AppEnv::$db->sql_query("USE $dbname",TRUE,FALSE,FALSE);
        $tables = \AppEnv::$db->sql_query('SHOW TABLES',TRUE,FALSE,TRUE);
        $ret = '';
        foreach($tables as $tabNo => $table) {
            $tbName = $table[0];
            $result = \AppEnv::$db->sql_query("OPTIMIZE TABLE $tbName",FALSE,FALSE,FALSE);
            $errNo = \AppEnv::$db->sql_errno();
            if(empty($err)) {
                $records = \AppEnv::$db->select($tbName, ['fields'=>'COUNT(1)', 'associative'=>0,'singlerow'=>1]);
                $errNo = \AppEnv::$db->sql_errno();
                if(empty($errNo)) $ret .= "$tbName optimized, no errors, records: $records<br>";
                else {
                    $errTxt = \AppEnv::$db->sql_error();
                    $ret .= "$tbName ater optimizing has ERROR $errNo / $errTxt<br>";
                    if($errNo == self::ERR_TABLE_CRASHED)
                        self::$shell_cmd[] = "myisamchk -r $dbname/$tbName"; # or -r -v
                }
            }
            else {
                $errTxt = \AppEnv::$db->sql_error();
                if($errNo == self::ERR_TABLE_CRASHED) {
                    self::$shell_cmd[] = "myisamchk -r $dbname/$tbName, must be repaired by myisamchk -r"; # or -r -v
                    $ret .= "$tbName has error $errNo - $errTxt<br>";
                }
                else {
                    \AppEnv::$db->sql_query("REPAIR $tbName USE_FRM",TRUE,FALSE,FALSE);
                    $errNo = \AppEnv::$db->sql_errno();
                    $errTxt = \AppEnv::$db->sql_error();
                    if(empty($errNo))
                        $ret .= "$tbName crashed index repaired by USE_FRM<br>";
                    else {
                        $ret .= "$tbName crashed index repair ERROR $errNo / $errTxt<br>";
                    }
                }
            }
            # if($tbName === 'boxprod_programs') break;
        }
        return $ret;
        # return $dbname . '<pre>' . print_r($tables,1) . '</pre>';
    }
    public static function dropTables($dbname, $tableMask, $debug=FALSE) {
        if(empty($tableMask)) {
            return FALSE;
        }
        if(is_string($tableMask)) $masks = explode(',',$tableMask);
        elseif(is_array($tableMask)) $masks = $tableMask;
        else throw new Exception ("wrong parameter tableMask, array or string must be passed");

        \AppEnv::$db->sql_query("USE $dbname",TRUE,FALSE,FALSE);
        $ret = [];
        foreach($masks as $oneMask) {
            $tables = \AppEnv::$db->sql_query("SHOW TABLES  LIKE '$oneMask'",TRUE,FALSE,TRUE);
            foreach($tables as $no => $tInfo) {
                $tableName = $tInfo[0];
                if($debug)
                    $ret[] = $tableName;
                else {
                    $result = \AppEnv::$db->sql_query("DROP TABLE $tableName",TRUE,FALSE,FALSE);
                    $ret[] = "$tableName dropped";
                }
                # if($no>2 && !$debug) break;
            }
        }
        return $ret;
    }
}

