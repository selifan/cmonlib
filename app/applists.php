<?php
/**
* @package ALFO Фронт-Офис
* @author Alexander Selifonov, <Aleksandr.Selifonov@zettains.ru>
* @name app/applists.php
* Выдача всяких списков для select-боксов и т.д.
* @version 1.26.001
* modified : 2025-09-18
*/
class AppLists {
    static $invest_types = [ 'INDEXX'=>'INDEXX', 'TREND'=>'TREND', 'COUPON'=>'COUPON','pif'=>'ДСЖ' ] ;
    static $employeeDiscount = 5; # стандартная скидка при страх-нии сотрудника (emp)
    # на замену AppEnv::getProductCategories
    public static function InvestTypeList() {
        return self::$invest_types;
    }

    # список кураторов для выбора у агента и т.п.
    public static function getCuratorsNone() {
        $ret = [ ['0','нет']];
        $dta = appEnv::$db->select(PM::T_CURATORS, ['fields'=>'id,fullname','where'=>"b_curator=1",'associative'=>0,'orderby'=>'fullname']);
        if (is_array($dta) && count($dta))
            $ret = array_merge($ret, $dta);
        return $ret;
    }
    # список коучей, для выбора в св-вах орг-юнита
    public static function getCouchesNone() {
        $ret = [ ['0','нет']];
        $dta = appEnv::$db->select(PM::T_CURATORS, ['fields'=>'id,fullname','where'=>"b_couche=1",'associative'=>0,'orderby'=>'fullname']);
        if (is_array($dta) && count($dta))
            $ret = array_merge($ret, $dta);
        return $ret;
    }

    // функции, дающие список для выбора настроек печати анкет в заявлении/полисе
    # анкета ФЛ(страхователя и застрахованного. Отбирает по маске anketa-fl-{any_name}.xml,
    public static function AnketaTypes() {
        $ret = array( ['0','Не печатать'] );
        if (is_file(ALFO_ROOT . 'templates/anketa/anketa-fl.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/anketa-fl-EDO.xml'))
                $ret[] = [ '1','Стандартная (с ЭДО)'];
            else $ret[] = ['1','Стандартная'];
        }

        $files = glob(ALFO_ROOT . 'templates/anketa/anketa-fl-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
            if(strlen($bname) > 10) $ret[] = array(substr($bname,10), substr($bname,10));
        }
        return $ret;
    }
    public static function AnketaTypesUL() {
        $ret = array(['0','Не печатать']);
        if (is_file(ALFO_ROOT . 'templates/anketa/anketa-ul.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/anketa-ul-EDO.xml'))
                $ret[] = [ '1','Стандартная (с ЭДО)'];
            else $ret[] = ['1','Стандартная'];
        }

        $files = glob('templates/anketa/anketa-ul-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
           if(strlen($bname) > 10) $ret[] = array(substr($bname,10), substr($bname,10));
        }
        return $ret;
    }

    public static function AnketaBenefTypes() {
        $ret = array(['0','Не печатать']);
        if (is_file(ALFO_ROOT . 'templates/anketa/anketa-benef-fl.xml')) {
            if (is_file(ALFO_ROOT . 'templates/anketa/anketa-benef-fl-EDO.xml'))
                $ret[] = [ '1','Стандартная (с ЭДО)'];
            else $ret[] = ['1','Стандартная'];
        }

        $files = glob('templates/anketa/anketa-benef-fl*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if(in_array(basename($fname), ['anketa-benef-fl.xml'])) continue;
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
            if(strlen($bname) > 13) $ret[] = array(substr($bname,13), substr($bname,13));
        }
        return $ret;
    }
    # список шаблонов опросных листов (FATCA)
    public static function OpListTypes() {
        $ret = array(array('0','Не печатать'));
        $ankFolder = appEnv::getAppFolder('templates/anketa/');
        if (is_file($ankFolder . 'oplist-fl.xml')) {
            if (is_file($ankFolder . 'oplist-fl-EDO.xml'))
                $ret[] = [ '1','Стандартный oplist-fl (с ЭДО)'];
            else $ret[] = ['1','Стандартный oplist-fl'];
        }

        $files = glob($ankFolder . 'oplist-fl-*.xml');
        if(is_array($files)) foreach($files as $fname) {
            if (substr($fname, -8) === '-EDO.xml') continue;
            $bname = substr(basename($fname), 0,-4); # "anketa-flxxx" без расширения
            $subName = substr($bname,10);
            if(strlen($bname) > 10) {
                $edoName = substr($bname,0, -4) . '-EDO.xml';
                if (is_file($ankFolder . $edoName))
                    $ret[] = array($subName, $subName.' (с ЭДО)');
                else $ret[] = array($subName, $subName);
            }
        }
        return $ret;
    }

    // список для выбора "дополнительных" листов печати в полис (уведомление клиенту, спец-анкеты и т.д. кроме ЭДО!
    public static function AdditionalPrintouts() {
        $ret = self::allPrintOuts('EDO anketa');
        /*
        $ret = array(array('','нет'));
        $ankFolder = appEnv::getAppFolder('templates/anketa/');
        $files = glob($ankFolder . '*.xml');
        if(is_array($files)) foreach($files as $fname) {
            $bname = basename($fname);
            if (fnmatch ('anketa*.xml', $bname)) continue;
            if (fnmatch ('oplist*.xml', $bname)) continue;
            if (substr($bname, -8) === '-EDO.xml') continue;
            $edoName = substr($bname,0, -4) . '-EDO.xml';
            if (is_file($ankFolder . $edoName))
                $ret[] = array($bname, $bname.' (с ЭДО)');
            else $ret[] = array($bname, $bname);
        }
        */
        return $ret;
    }
    # все XML файлы  в папке templates/anketa/ либо за исключением указанных в фильтре
    public static function allPrintOuts($filter='') {
        $ret = array(array('','нет'));
        $ankFolder = appEnv::getAppFolder('templates/anketa/');
        $files = glob($ankFolder . '*.xml');
        if(is_array($files)) foreach($files as $fname) {
            $bname = basename($fname);
            if(strpos($filter,'anketa')!==FALSE && fnmatch ('anketa*.xml', $bname)) continue;
            if (fnmatch ('oplist*.xml', $bname)) continue;
            if(strpos($filter,'EDO')!==FALSE && substr($bname, -8) === '-EDO.xml') continue;
            $edoName = substr($bname,0, -4) . '-EDO.xml';
            if (is_file($ankFolder . $edoName))
                $ret[] = array($bname, $bname.' (с ЭДО)');
            else $ret[] = array($bname, $bname);
        }
        return $ret;
    }

    public static function AdditionalPrintoutsEDO() {
        $ret = self::getFilesForSelect(ALFO_ROOT . 'templates/anketa/*EDO*.xml');
        return $ret;
    }
    /**
    * Формирует список для выбора файла, с первой опцией "не выбрано"
    *
    * @param mixed $folderMask Папка и маска дял отбора
    * @param mixed $exclude какие файлы не исключить из списка (маска)
    * @since 1.20
    */
    public static function getFilesForSelect($folderMask, $exclude = FALSE, $withEDO = FALSE) {
        $ret = [ ['','нет'] ];
        foreach(glob($folderMask) as $fl) {
            $fname = basename($fl);
            if (!empty($exclude) && fnmatch($exclude,$fname)) continue;
            $ret[] = [$fname,$fname];
        }
        if ($withEDO) $ret = self::makeListEdoXml($ret);
        return $ret;
    }
    /**
    * преобразует массив имен xml файлов (сфорвированный для селекта) в вид "с ЭДО":
    * если у файла *.xml есть парный с окончанием "-EDO.xml", на его "выводимое" имя добавляю пометку (с ЭДО),
    * а сами -EDO файлы удаляю из списка выбора
    * @since 1.03 (2020-10-02)
    * @param mixed $srcArr исчходнй массив (строка = [имя, имя])
    */
    public static function makeListEdoXml($srcArr) {
        $ret = [];
        $tmpArr = $srcArr;
        foreach($srcArr as $item) {
            if (empty($item[0])) { # option "не выбрано"
                $ret[] = $item;
                continue;
            }
            if (substr($item[0],-8) === '-EDO.xml') continue;
            $edoName = substr($item[1],0,-4) . '-EDO.xml';
            if (self::in_multiArray($edoName, $srcArr)) {
                $ret[] = [$item[0], $item[0].' (с ЭДО)'];
            }
            else $ret[] = $item;
        }
        return $ret;
    }
    public static function in_multiArray($string, $arr) {
        foreach($arr as $item) {
            if (is_array($item)) {
                if (in_array($string,$item)) return TRUE;
            }
            elseif ($string == $item) return TRUE;
        }
        return FALSE;
    }
    /**
    * получаю массив строк для печати нескольких застрахованных (дети в Окно-барьере и т.п.)
    * @since 1.04
    * @param mixed $plcid ИД полиса
    * @param mixed $ptype тип записи (insd|child|...)
    * @param mixed $pref доп.префикс в ФИО субъекта)
    */
    public static function getPersonsForPrint($plcid, $ptype, $pref = FALSE) {
        $ret = appEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>['stmt_id'=>$plcid, 'ptype'=>$ptype],'orderby'=>'id']);
        # writeDebugInfo("getPersonsForPrint($plcid, $ptype,pref=[$pref])");
        $nomer = 0;
        if (is_array($ret) && count($ret)) foreach($ret as &$row) {
            $nomer++;
            $row['fullname'] = "$row[fam] $row[imia]" . (empty($row['otch']) ? '' : ' '.$row['otch']);
            if($pref) {
                switch($ptype) {
                    case 'child': $namePref = "Застрахованный ребенок № $nomer - "; break;
                    case 'insd': $namePref = "Застрахованный № $nomer - "; break;
                    case 'benef': default: $namePref = "Выгодприобретатель № $nomer - "; break;
                }
                $row['fullname'] = $namePref . $row['fullname'];
            }
            $row['fulladdr'] = PolicyModel::buildFullAddress($row);
            if (empty($row['sameaddr'])) $row['fullfaddr'] = PolicyModel::buildFullAddress($row,'','f');
            if (PlcUtils::isRf($row['rez_country'])) $row['rez_rf'] = 1;
            else {
                $row['rez_not_rf'] = 1;
                $row['rez_country_name'] = PlcUtils::decodeCountry($row['rez_country']);
            }
            $row['birth_country'] = PlcUtils::decodeCountry($row['birth_country']);
            if ($row['sex'] =='F') $row['sex_f'] = 1; else $row['sex_m'] = 1;
            $row['birth'] = to_char($row['birth']);

            if ($row['doctype'] == PM::DT_PASSPORT) {
                $row['paspser'] = $row['docser'];
                $row['paspno'] = $row['docno'];
                $row['paspissued'] = $row['docissued'];
                $row['paspdate'] = to_char($row['docdate']);
                $row['pasppodr'] = $row['docpodr'];
            }
            elseif ($row['doctype'] == PM::DT_SVID) { # свид-во о рожд.
                $row['svidser'] = $row['docser'];
                $row['svidno'] = $row['docno'];
                $row['svidissued'] = $row['docissued'];
                $row['sviddate'] = to_char($row['docdate']);
            }
            $row['fulldoc'] = PlcUtils::buildFullDocument($row,'',TRUE);
        }
        return $ret;
    }
    /**
    * добавляю в массив отдельные "гриды" для каждого застрахованного/субъекта
    * @since 1.07 (2023-04-13)
    * @param mixed $dta массив куда заносить данные
    * @param mixed $plcid ИД полиса
    * @param mixed $ptype тип записи (insd|child|...), он же будет базой ключа массива
    */
    public static function getPersonsDatagrids(&$dta, $plcid, $ptype='child') {
        $ret = appEnv::$db->select(PM::T_INDIVIDUAL, ['where'=>['stmt_id'=>$plcid, 'ptype'=>$ptype],'orderby'=>'id']);
        # writeDebugInfo("getPersonsForPrint($plcid, $ptype): ", $ret);
        $rowNo = 0;
        if (is_array($ret) && count($ret)) foreach($ret as &$row) {
            $rowNo++;
            $row['subj_yes'] = 1; # для вывода чекбокса "Присутствия" в блоке застрахованного
            $row['fullname'] = "$row[fam] $row[imia]" . (empty($row['otch']) ? '' : ' '.$row['otch']);
            $row['fulladdr'] = PolicyModel::buildFullAddress($row);
            if (empty($row['sameaddr'])) $row['fullfaddr'] = PolicyModel::buildFullAddress($row,'','f');
            if (PlcUtils::isRf($row['rez_country'])) $row['rez_rf'] = 1;
            else {
                $row['rez_not_rf'] = 1;
                $row['rez_country_name'] = PlcUtils::decodeCountry($row['rez_country']);
            }
            $row['birth_country'] = PlcUtils::decodeCountry($row['birth_country']);
            if ($row['sex'] =='F') $row['sex_f'] = 1; else $row['sex_m'] = 1;
            $row['birth'] = to_char($row['birth']);

            if ($row['doctype'] == PM::DT_PASSPORT) {
                $row['paspser'] = $row['docser'];
                $row['paspno'] = $row['docno'];
                $row['paspissued'] = $row['docissued'];
                $row['paspdate'] = to_char($row['docdate']);
                $row['pasppodr'] = $row['docpodr'];
            }
            elseif ($row['doctype'] == PM::DT_SVID) { # свид-во о рожд.
                $row['svidser'] = $row['docser'];
                $row['svidno'] = $row['docno'];
                $row['svidissued'] = $row['docissued'];
                $row['sviddate'] = to_char($row['docdate']);
            }
            $row['fulldoc'] = PlcUtils::buildFullDocument($row,'',TRUE);
            $dta[$ptype.$rowNo] = [$row];
        }
        return $rowNo;
    }
    # вернет Allianz либо Zetta в зав-мости от текущего названия компании
    public static function getShortCompName() {
        $cmp = mb_strtolower( AppEnv::getConfigValue('comp_title'), MAINCHARSET);
        if(strpos($cmp, 'zetta')!==FALSE || strpos($cmp, 'зетта')!==FALSE)
            return 'Zetta';
        return 'Allianz';
    }

    # текущее офиц.название стр.компании - для жизни (AZLIFE) или для СК Адльянс (AZ)
    # $companyId = "AZ" | "AZLIFE"
    public static function getOfficialCompanyName($companyId='AZLIFE') {
        $baseNm = self::getShortCompName();
        if($baseNm === 'Allianz') {
            if($companyId === 'AZLIFE') $ret = 'ООО СК «Альянс Жизнь»';
            else $ret = 'АО СК «Альянс»';
        }
        elseif( $baseNm === 'Zetta') {
            if($companyId === 'AZLIFE') $ret = 'ООО «Зетта Страхование жизни»';
            else $ret = 'АО «Зетта Страхование»';
        }
        else $ret = '???';
        return $ret;
    }
    /** список опций для формирования выбора компании у куратора, агента и т.д.
    * $justComp = TRUE - елси нужен строго только список из 2х компаний, без "не выбрано" и "Обе"
    */
    public static function chooseCompanyOptions($justComp=FALSE) {
        $base = self::getShortCompName();
        $ret = [];
        if(!$justComp) $ret[] = ['', '-Не выбрано-'];
        $ret[] = ['AZ', $base];
        $ret[] = ['AZLIFE', "$base Жизнь"];
        if(!$justComp) $ret[] = ['ALL', 'Обе компании'];
        return $ret;
    }
    # только сами компании
    public static function chooseCompanyOptionsJc() {
        return self::chooseCompanyOptions(1);
    }
    # развернутое название "активной" стр.компании, по ее идентификатору
    public static function decodeCompanyName($sName) {
        $base = self::getShortCompName();
        switch($sName) {
            case '': return 'Неизвестно';
            case 'AZ': return $base;
            case 'AZLIFE': return "$base Жизнь";
            case 'ALL': return 'Обе компании';
        }
        return $sName;
    }
    # вернет local_path/name.png картинки для писем, в соотв-вии с тек.настройкой компании
    public static function getLetterLogo() {
        $company = self::getShortCompName();
        $ret = AppEnv::getAppFolder('img/') . "letter-logo-$company.jpg";
        return $ret;
    }
    # Получаю, какая ветка гита сейчас активна - смотрю в файл .git/HEAD - строка "ref: refs/heads/master"
    public static function gitDevBranch($modes=0) {
        $gitFolder = ALFO_ROOT . '.git/';
        $refName = $gitFolder . 'HEAD';
        if(!is_file($refName)) return '';
        $sContent = file_get_contents($refName);
        $items = explode('/', $sContent);
        $ret = array_pop($items);

        if($modes > 1) {
            $commitId = @file_get_contents($gitFolder.'refs/heads/master');
            if(!empty($commitId)) $ret .= " / commit ID: $commitId";
        }
        if($modes > 0) {
            $commitTitle = @file_get_contents($gitFolder . 'COMMIT_EDITMSG');
            if(!empty($commitTitle)) $ret .= " ($commitTitle)";
        }
        return $ret;
    }
    public static function regionClasses() {
        return [
          [ '0', 'Не выбран' ],
          [ '20', 'Область' ],
          [ '24', 'Край' ],
          [ '25', 'Республика' ],
          [ '30', 'Город' ],
        ];
    }
    public static function getStateList() {
        return array(
           array(0, 'Проект')
          ,array(6, 'Полис')
          ,array(7, 'Оплачен')
          ,array(9, 'Аннулирован')
          ,array(10, 'Отменен')
          ,array(11, 'Оформлен')

        );
    }
    # {upd/2023-09-26} список кодов типа продукта в СЭД
    public static function sedProductTypes() {
        $ret = [
          [ '', '-- не выбрано --' ],
          [ PM::DOCFLOW_TP_INVEST, 'Инвестиционное страхование' ],
          [ PM::DOCFLOW_TP_NAKOP , 'Накопительное страхование' ],
          [ PM::DOCFLOW_TP_RISKY , 'Рисковое значение' ],
          [ PM::DOCFLOW_TP_BOX   , 'Коробочные продукты' ],
        ];
        return $ret;
    }
    # {upd/2023-09-26} разбор строки типа key=value;key2=value2;... либо @userFunctionName
    public static function parseString($strg) {
        if($strg[0] === '@') {
            $func = substr($strg,1);
            if(is_callable($func)) return call_user_func($func);
            else return '';
        }
        $pairs = explode(';', $strg);
        $ret = [];
        foreach($pairs as $item) {
            $subPair = explode('=',$item);
            if(count($subPair)<2) $ret[] = [$item, $item];
            else $ret[] = $subPair;
        }
        return $ret;
    }
    public static function getApiClients() {
        $arRet = \AppEnv::$db->select(\PM::T_APICLIENTS, ['fields'=>'id,username,usertoken,userid',
          'where'=>"(active_from=0 OR CURDATE()>=active_from) AND (active_till=0 OR CURDATE()<=active_till)",
          'orderby'=>'id']);
        return $arRet;
    }
    /**
    * Пришел AJAX запрос на динамическую смену списка кодировок в BLIST-поле
    * Формирую HTML код и отсылаю клиенту.
    */
    public static function gethtml_blist() {
        $flid = isset(AppEnv::$_p['field']) ? AppEnv::$_p['field'] : '';
        $ret = '';
        switch ($flid) {
            case 'prodcodes':
            case 'codelist':
                # $module = isset(AppEnv::$_p['module']) ? AppEnv::$_p['module'] : '';
                $ret = Modules::gethtml_blist();
                break;
        }
        exit($ret);
    }

    # набор ф-ций с ролями-правами (получение ИД, списков ИД)
    # получит ИД для права по его анг.идентификатору из справочника прав
    public static function getRightId($rightName) {
       $tableRights = AppEnv::TABLES_PREFIX . 'acl_rightdef';
       $arDta = AppEnv::$db->select($tableRights, ['where'=>['rightkey'=>$rightName], 'singlerow'=>1]);
       return ($arDta['rdefid'] ?? FALSE);
    }

    # получит ИД ролей, имеющих заданное право (на уровне $level)
    public static function getRolesWithRight($rightName, $level=1) {
        $rid = self::getRightId($rightName);
        if(!$rid) return [];
        $tRoleRights = AppEnv::TABLES_PREFIX . 'acl_rolerights';
        # получаю список ИД ролей, в которых есть право с заданным уровнем
        $roles = AppEnv::$db->select($tRoleRights, ['where'=>['rightid'=>$rid,'rightvalue'=>$level ],
          'fields'=>'roleid', 'distinct' => 1, 'associative'=>0 ]);
        # $roles[] = AppEnv::$db->getLastQuery();
        return $roles;
    }

    # получит список ИД пользователей, имеющих заданное право на уровне $level
    public static function getUsersWithRight($rightName, $level=1) {
        $arRoles = self::getRolesWithRight($rightName, $level);
        if(!is_array($arRoles) || !count($arRoles)) return [];
        $tUserRoles = AppEnv::TABLES_PREFIX . 'acl_userroles';
        $strList = implode(',',$arRoles);
        $arUsers = AppEnv::$db->select($tUserRoles, ['where'=>"roleid IN($strList)",
          'fields'=>'userid', 'distinct' => 1, 'associative'=>0 ]);
        return $arUsers;
    }
    # получить список кодов загружаемых валют
    public static function getCurrencyList() {
        $arRet = AppEnv::$db->select(PM::T_CURLIST, ['fields'=>'curcode','where'=>"b_active>0",'distinct'=>1,'associative'=>0]);
        # если справочник не заполнен, беру стандартный набор
        if(!is_array($arRet) || !count($arRet)) $arRet = ['EUR','USD'];
        return $arRet;
    }
    public static function getCurrencyName($curid, $long= FALSE) {
        $sRet = AppEnv::$db->select(PM::T_CURLIST, ['fields'=>'curname','where'=>"curcode='$curid'",'singlerow'=>1,'associative'=>0]);
        return $sRet;
    }
    # вернет список модулей, входящих в общие отчеты
    public static function getReportedModules($mode = FALSE, $asString = FALSE) {
        $arRet = [];
        foreach(AppEnv::$_plugins as $plgid => $obj) {
            if(!empty($obj->inReports) && (!$mode || $obj->inReports=== $mode))
                $arRet[] = $plgid;
        }
        return ($asString ? ("'".implode("','", $arRet) . "'") : $arRet);
    }
    /**
    * Беру из таблицы alf_product_config набор констант для печати номера/даты приказа, должности/ФИО/доверенности
    * подписанта от СК...
    * @param mixed $module ИД плагина
    * @param mixed $codirovka кодировка продукта
    * @since 1.16 : перенос из policymodel.php
    */
    public static function getBaseProductCfg($obj, $module='',$codirovka='') {
        # writeDebugInfo(__METHOD__ , " call");
        $where = array();
        $where = ["module" => $module];
        if ($codirovka) $where[] = "(prodcodes='' OR FIND_IN_SET('$codirovka',prodcodes))";
        $ret = AppEnv::$db->select(PM::T_PRODCFG, array('where'=>$where, 'singlerow'=>1,'orderby'=>'prodcodes DESC'));
        # writeDebugInfo("getBaseProductCfg sql: ", AppEnv::$db->getLastQuery(), ' result: ', $ret);
        # подписант, заданный в полисе, имеет приоритет:
        $skipSignerFields = FALSE;
        if (is_object($obj) && !empty($obj->_rawAgmtData['signer']))
            $stampid = $obj->_rawAgmtData['signer'];
        else {
            # {upd/2024-07-31} Для полисов с ЭДО-ПЭП согласованием может быть настроен свой подписант (факсимиле, должность, номер-дата доверенности)
            $isEdo = $obj->isEdoPolicy();
            if($isEdo && !empty($ret['stampid_edo'])) {
                $stampid = $ret['stampid_edo'];
                $skipSignerFields = TRUE;
            }
            else $stampid = $ret['stampid'] ?? 0;
        }

        if (!empty($stampid) && class_exists('Stamps')) {
            # $images = Stamps::getImagesForId($ret['stampid']);
            $images = Stamps::getSignerData($stampid);

            # теперь тут и данные ФИО, должность, доверенность подписанта (2-19-02-22)
            if (is_array($images)) {
                $ret['fullstamp'] = $images['fullstamp'];
                $ret['faximile'] = $images['faximile'];

                # если поля данных о подписанте пустые или нету, беру из stamps
                if (empty($ret['signer_duty']) || $skipSignerFields) $ret['signer_duty'] = $images['signer_duty'];
                if (empty($ret['signer_name']) || $skipSignerFields) $ret['signer_name'] = $images['signer_name'];
                if (empty($ret['signer_dov_no']) || $skipSignerFields) $ret['signer_dov_no'] = $images['signer_dov_no'];
                if (empty($ret['signer_dov_date']) || !intval($ret['signer_dov_date'])  || $skipSignerFields)
                    $ret['signer_dov_date'] = $images['signer_dov_date'];

                # {upd/2025-09-10} - подключаю сертификат ЭЦП, привязванный к "штампу" подписанта
                if (!empty($images['signer_digialias'])) $ret['signer_digialias'] = $images['signer_digialias'];
            }
        }

        if (isset($ret['id'])) {
            unset($ret['id']);
            $ret['prikaz_date'] = intval($ret['prikaz_date']) ? to_char($ret['prikaz_date']) : '';
            $ret['signer_dov_date'] = intval($ret['signer_dov_date']) ? to_char($ret['signer_dov_date']) : '';
        }
        if (isset($ret['signer_name'])) {
            $full_delim = ' '; # "\r\n"; # Для сокращения занимаемого места принуд.перевод после должности строки убрал
            $dovertxt = ((!empty($ret['signer_dov_no']) ?
                ('Действующий(ая) на основании Доверенности № '.$ret['signer_dov_no']. ' от '.$ret['signer_dov_date'])
                : 'Действующий(ая) на основании Устава'));

            $ret['ic_signer_full'] = $ret['signer_duty'] . $full_delim . $ret['signer_name'] .', ' . $full_delim
               . $dovertxt;
            $ret['ic_signer_fio'] = RusUtils::MakeFio($ret['signer_name']); # поле "расшифровка подписи"

        }
        # exit(__FILE__ .':'.__LINE__." stampid: $stampid:<pre>" . print_r($ret,1) . '</pre>');

        # взять path+filenames картинок штампов+afrcbvbkt в соотв-вии с настройкой продукта
        return $ret;
    }

    # AJAX-запрос с формы редактирования на проверку клиента по базе террористов
    # 2024-06-06 - перенос из policymodel.php::fmon_check
    public function finmonCheck() {
        $pref = isset(AppEnv::$_p['fmprefix']) ? AppEnv::$_p['fmprefix'] : 'insr';
        $fizur = ($pref === 'insr') ? (isset(AppEnv::$_p['insurer_type'])? AppEnv::$_p['insurer_type']:1) : 1;
        $filled = FALSE;
        $fil_what = 'Фамилию и имя';
        $fin = AppEnv::getPluginBackend('finmonitor');
        if (is_object($fin)) {
            if ($fizur == 1) {
                $params = array( # struct PartnerInfo
                    'Type'      => toUtf8('ФЛ')
                    ,'LastName'   => toUtf8(AppEnv::$_p[$pref.'fam'])
                    ,'FirstName'  => toUtf8(AppEnv::$_p[$pref.'imia'])
                );
                $filled = !empty(AppEnv::$_p[$pref.'fam']) && !empty(AppEnv::$_p[$pref.'imia']);
                if (!empty(AppEnv::$_p[$pref.'otch'])) $params['MiddleName'] = toUtf8(AppEnv::$_p[$pref.'otch']);
                if (!empty(AppEnv::$_p[$pref.'docser'])) $params['DocNumber'] = toUtf8(AppEnv::$_p[$pref.'docser']) .
                  ((!empty(AppEnv::$_p[$pref.'docno'])) ? (' '.toUtf8(AppEnv::$_p[$pref.'docno'])) : '');
                if (!empty(AppEnv::$_p[$pref.'birth'])) $params['BirthDate'] = to_date(AppEnv::$_p[$pref.'birth']);
            }
            else {
                $params = array( # struct PartnerInfo
                    'Type'      => toUtf8('ЮЛ')
                    ,'OrgName'  => toUtf8(AppEnv::$_p['insrurname'])
                    ,'INN'  => toUtf8(AppEnv::$_p['insrurinn'])
                );
                $filled = !empty(AppEnv::$_p[$pref.'insrurname']) && !empty(AppEnv::$_p[$pref.'insrurinn']);
                $fil_what = 'наименование и ИНН юр-лица';
            }
            if (!$filled) {
                AppEnv::echoError('Сначала введите '.$fil_what);
            }
            # WriteDebugInfo('params for finmonitor:', $params);  $result = "TODO...";
            $result = $fin -> request($params);
            exit("1" . AjaxResponse::showMessage($result, 'Результат проверки'));
            # AppEnv::echoError("1\tshowmessage\f$result\fРезультат проверки:");
        }
        exit("1\ttalert\fTerrorist Base engine not attached");
    }

    # список для отрисовки опций в SELECT выбора риска (перенос из iconst\types)
    public static function getRiskOptions() {
        if (!isset(\appEnv::$_cache['app_risklist'])) {
            \appEnv::$_cache['app_risklist'] = \appEnv::$db->select(\PM::T_RISKS,
              ['fields' => "id,CONCAT(riskename,' / ',shortname)",
              'associative'=>0,'orderby'=>'riskename'
            ]);
        }
        return \appEnv::$_cache['app_risklist'];
    }
    # то же, но делаю riskid/Название, чтобы удобнее сравнивать с единым списком рисков
    public static function getRiskIdShortName($id) {

        $ret = appEnv::$db->select(PM::T_RISKS, [
          'fields' => "CONCAT(riskename,' / ',shortname) id_name",
          'where'=>['id'=>$id],
          'singlerow'=>1 ]
        );
        return (isset($ret['id_name']) ? $ret['id_name'] : "[$id]");
    }
    # список вариантов канала продаж (для выгрузки в СЭД)
    public static function getSaleChannels() {
        $ret = [ ['0','Не задан']];
        if(class_exists('sedexport') && !empty(sedexport::$channels)) {
            foreach(sedexport::$channels as $key => $val) {
                $ret[] = [$key, $val];
            }
        }
        return $ret;
    }

    # {upd/2025-01-30} формирую список папок в указанной папке (плюс первый п-т - "не выбрано")
    public static function subFolderListNone($folder) {
        $ret = [['','не выбрано']];
        foreach(glob($folder."*",GLOB_ONLYDIR) as $subdir) {
            if($subdir ==='..') continue;
            $onedir = $label = basename($subdir);

            $descFile = $subdir . '/_description.txt'; # есть файл с описанием папки ?
            if(is_file($descFile)) # есть описание папки, добавляю в описание
                $label .= " / " . @file_get_contents($descFile);
            $ret[] = [$onedir, $label];
        }
        return $ret;
    }
    # {upd/2025-01-30} список подпапок с наборами для печати согласия на обр.ПДн
    public static function getPdnTemplates() {
        $folder = AppEnv::getAppFolder('templates/pdn/');
        $options = self::subFolderListNone($folder);
        return $options;
    }
    # {upd/2025-01-30} список подпапок с наборами для печати согласия на обр.ПДн
    public static function getMedAnketaConfigs() {
        $folder = AppEnv::getAppFolder('templates/med-anketas/');
        return self::subFolderListNone($folder);
    }

    /**
    * получает словарное представление скидки
    * $forFinPlan = TRUE если надо составить текст для вывода в фин-план
    */
    public static function verboseDiscount($sValue, $forFinPlan = FALSE) {
        $retBase = ($forFinPlan ? 'Расчет произведён с учетом скидки' : 'Скидка');
        if($sValue == 1) {
            $sValue = '50'; # старый тип 1 = "50% от АВ"
        }
        if(is_numeric($sValue)) {
            if($sValue == 0) return 'Стандартный расчет';
            return (($forFinPlan===2)? $retBase : "$retBase за счет $sValue % от АВ");
        }
        if($sValue === 'emp') return 'Расчет для сотрудника группы Зетта';
        return $sValue;
    }

    # формирую HTML блок с вариантами скидки зв счет АВ
    public static function getDiscountListHtml($strList, $fldname='b_sav', $onchange='') {
        if($strList == '1')
            return "<label> <input type=\"checkbox\" name=\"$fldname\" id=\"$fldname\" value=\"1\" onchange=\"wzc.agtRecalc()\"> Скидка за счет АВ</label>";

        $dList = preg_split("/[ ,;]/", $strList, -1, PREG_SPLIT_NO_EMPTY);
        $options = '';
        foreach($dList as $item) {
            $options .= "<option value=\"$item\">" . self::verboseDiscount($item) . '</option>';
        }
        $attOnChange = empty($onchange) ? '' : "onchange=\"$onchange\"";
        $discountsBlock = "<select name=\"$fldname\" id=\"$fldname\" $attOnChange class=\"form-select\">"
          . $options . '<select>';

        return $discountsBlock;
    }
    # преобразую значение скидки в реальный agent_ratio для экспорта
    public static function getAgentComissionValue($discount) {
        if($discount === 'emp') return (100 - self::$employeeDiscount);
        if($discount == 1) return 50;
        return (100 - floatval($discount));
    }
    # вернет список кодов "регионов" имеющих тип "город" (для корректной установки required у полей "Город, нас.пункт" после выбора региона в адресе)
    public static function getCityIdList($asArray=FALSE) {
        $cityList = \AppEnv::$db->select(\PM::T_REGIONS, ['fields'=>'id', 'where'=>"addresstypelevelid=30",'associative'=>0]);

        return ($asArray ? $cityList : implode(',',$cityList)) ;
    }
}

if(!empty(AppEnv::$_p['appaction'])) {
    $appAction = trim(AppEnv::$_p['appaction']);
    if(method_exists('AppLists',$appAction))
        return AppLists::$appAction();
    else return 'Bad appaction';
}