<?php
/**
* @package ALFO
* @name orgunits.php - Набор функций для работы с данными об орг-юнитах (банки, агенты, eShop-учетки)
* @version 1.22.001
* modified 2025-10-08
*/
class OrgUnits {
    const VERSION = '1.16';
    const MT_NOTYPE = '0';
    const MT_BANK = '1';
    const MT_ALLIANZ = '2'; # компании группы Альянс
    const MT_AGENT = '100';
    const MT_ESHOP = '110';
    const MT_OTHER = '900';
    const PARNAME_TPCONFIRM = 'confirm_tp_date';
    const PARCAT_BANKS = '_banks_';
    const TXT_ALLNETS = 'Все партнеры/сети';

    static private $_cached = [];
    private static $meta_depts = FALSE;
    # Новые мета-Типы добавлять ЗДЕСЬ:
    static $metaTypes = [
      '0' =>'нет',
      '1' => 'Банки',
      '2' => 'Компания Zetta Страхование',
      '100' => 'Агенты',
      '110' => 'Клиенты eShop',
      '900' => 'прочие партнеры',
    ];

    /**
    * Получает "ближайшие" по иерархии непустые значения заданных атрибутов орг-юнита
    * (идет снизу вверх (по родительским юнитам) пока не заполнит все пустые значения атрибутов)
    * @param mixed $deptid ИД орг-юнита
    * @param mixed $attribs список имен полей, значения для которых надо получить
    */
    public static function getNestedUnitAttribs($deptid, $attribs = array()) {

        $ret = [];
        if (is_string($attribs)) $attribs = preg_split( '/[ ,;]/', $attribs, -1, PREG_SPLIT_NO_EMPTY );
        $fldList = array_merge(['dept_id','parentid','b_metadept'], $attribs);
        foreach($attribs as $attr) { $ret[$attr] = ''; }
        $attrCount = count($ret);
        $nest = 0;
        $curdept = $deptid;
        while(++$nest <=32) {
            # $deptrow = self::getUnitData($curdept, "dept_id,parentid,b_metadept,".implode(',',$attribs));
            $deptrow = self::getUnitData($curdept, $fldList);
            if (!isset($deptrow['parentid'])) return $ret;
            foreach($attribs as $attr) {
                if ($ret[$attr] == '' && !empty($deptrow[$attr])) {
                    $ret[$attr] = $deptrow[$attr];
                    $attrCount--;
                }
            }
            if ($attrCount<=0) {
                break; # все атрибуты заполнились, дальше вверх идти незачем
            }
            if (empty($deptrow['parentid']) || $deptrow['parentid'] == $curdept || $deptrow['b_metadept']>0)
                break; // доехали до самого верха
            $curdept = $deptrow['parentid'];
        }
        return $ret;
    }

    public static function getUnitData($deptid, $fields = '') {
        $ret = appEnv::$db->select(PM::T_DEPTS,
          [ 'where'  => ['dept_id'=>$deptid],
            'fields' => $fields,
            'singlerow' => 1
          ]);
        return $ret;
    }
    /**
    * Получаю Мета-орг.юнит для указаннного
    *
    * @param mixed $deptid
    * @return mixed
    */
    public static function getMetaDeptForDept($deptid) {
        # if (!$deptid) $deptid = appEnv::$auth->deptid;
        $stopit = 0;
        $cacheId = "metadept-$deptid";
        if (!isset(self::$_cached[$cacheId])) {
            while(++$stopit<=30) {
                $dta = appEnv::$db->select(['m'=>PM::T_DEPTS],
                [
                    'fields' => 'm.dept_id,m.parentid,m.dept_name,m.b_metadept',
                    'where'=> [ 'm.dept_id'=>$deptid ],
                    'associative' => 1,
                    'singlerow' => 1
                ]);
                # writeDebugInfo("dept($deptid): ", $dta);
                if (empty($dta['parentid']) || $dta['parentid'] == $dta['dept_id']) return 0; # искомый ОУ не под метаОУ.
                if ($dta['b_metadept'] >0) break;
                $deptid = $dta['parentid']; # ищем по иерархии выше
            }
            # writeDebugInfo("finally dta is ", $dta);
            self::$_cached[$cacheId] = $dta;
        }
        return self::$_cached[$cacheId];
    }

    # вернет "тип канала продаж" (Мета-Тип) для данного орг-юнита
    public static function getMetaType($dept=0) {
        if(!$dept) $dept = AppEnv::$auth->deptid;
        $meta = self::getMetaDeptForDept($dept);
        # writeDebugInfo("meta($dept): ", $meta);
        return (isset($meta['b_metadept']) ? $meta['b_metadept'] : 0);
    }

    # для формирования опций в SELECT - все мета-типы
    public static function getMetaTypeOptions() {
        $ret = [];
        foreach(self::$metaTypes as $id => $val) { $ret[] = [$id, $val]; }
        return $ret;
    }
    public static function decodeMetaType($meta) {
        if (isset(self::$metaTypes[$meta])) return self::$metaTypes[$meta];
        return "[$meta]";
    }

    public static function getHeadOrgUnit($deptid=0) {
        if (appEnv::isClientCall()) {
            $deptid = appEnv::$client_deptid;
            # WriteDebugInfo("getHeadOrgUnit: API client dept $deptid");
        }
        $ret = $initdept = ($deptid> 0) ? $deptid : appEnv::$auth->deptid;
        if (!$ret) return 0;
        $chkdept = appEnv::getCached("primaryDept-$deptid", $ret);
        if($chkdept !== NULL) {
            return $chkdept;
        }

        # if ( in_array($ret, $stopId) ) return $ret;
        $kkk = 0; # limit recursion
        while ($kkk++ < 32) {
            $pr = appEnv::$db->select(PM::T_DEPTS, array(
                'fields'=>'parentid,b_metadept'
                ,'where'=>"dept_id='$ret'"
                ,'singlerow'=>1
            ));
            $parent = isset($pr['parentid']) ? intval($pr['parentid']) : 0;
            if( $parent<=0 || $parent == intval($ret) ) {
                break;
            }
            if ($pr['b_metadept']>0) { $ret = $prevId; break; }
            $prevId = $ret;
            $ret = $parent;
        }
        appEnv::setCached("primaryDept-$deptid", $initdept, $ret);
        return $ret;
    }
    # {upd/2021-06-24} получить ИД родительского ОУ
    public static function getParentDeptId($deptid) {
        $pr = appEnv::$db->select(PM::T_DEPTS, array(
            'fields'=>'dept_id,parentid,b_metadept'
            ,'where'=> ["dept_id"=>$deptid]
            ,'singlerow'=>1
        ));

        $parent = isset($pr['parentid']) ? intval($pr['parentid']) : 0;
        if ($parent == $deptid || empty($parent)) return 0;
        if (!empty($pr['b_metadept'])) return FALSE;
        $pr2 = appEnv::$db->select(PM::T_DEPTS, array(
            'fields'=>'parentid,b_metadept'
            ,'where'=> ["dept_id"=>$parent]
            ,'singlerow'=>1
        ));

        if (!empty($pr2['b_metadept'])) return 0;
        return $parent;
    }
    # Для отчетов - получить спиcок ИД мета-ОУ агентских сетей
    public static function getMetaAgents($asArray = FALSE) {
        return self::getMetaOu(self::MT_AGENT, $asArray);
    }
    public static function getMetaAgentsString() {
        return self::getMetaOu(self::MT_AGENT, FALSE);
    }
    # Для отчетов - получить спиcок ИД мета-ОУ "банков"
    public static function getMetaBanks($asArray = FALSE) {
        return self::getMetaOu(self::MT_BANK, $asArray);
    }

    public static function getMetaBanksString() {
        return self::getMetaOu(self::MT_BANK, FALSE);
    }
    # Получение списка мета-юнитов указанного типа (в стандарте должен быть только один!)
    public static function getMetaOu($metaType, $asArray = FALSE) {
        $data = appEnv::$db->select(PM::T_DEPTS,['fields'=>'dept_id,dept_name', 'where'=>['b_metadept'=>$metaType],'orderby'=>'dept_name']);
        if ($asArray) {
            return $data;
        }
        $ret = '';
        if (is_array($data)) {
            foreach($data as $row) {
                $ret .= ($ret ? ',':'') . $row['dept_id'];
            }
        }
        return $ret;
    }

    /**
    * получаю Код "Мета-типа", к которому относится орг-юнит
    *
    * @param mixed $headedptId ИД ГОЛОВОГО (!) орг-юнита
    */
    public static function getOuMetaType($headedptId=0) {
        if (!$headedptId) $headedptId = self::getPrimaryDept();
        if (!isset(self::$_cached['metatypes'][$headedptId])) {
            $dta = appEnv::$db->select(['ou'=>PM::T_DEPTS, 'pr'=>PM::T_DEPTS], ['where'=>['ou.dept_id'=>$headedptId, "pr.dept_id=ou.parentid"], 'fields'=>'pr.b_metadept metatype', 'singlerow'=>1]);
            # return appEnv::$db->getLastQuery() . ', data: <pre>' . print_r($dta,1) . '</pre>';
            $result = (!empty($dta['metatype']) ? $dta['metatype'] : 0);
            if (!isset(self::$_cached['metatypes'])) self::$_cached['metatypes'] = [];
            self::$_cached['metatypes'][$headedptId] = $result;
        }
        return self::$_cached['metatypes'][$headedptId];
    }

    /**
    * Получаю список наборов "пользовательских" параметров, которые можно назначить орг-юниту (партнеру)
    * для SELECT бокса выбора!
    */
    public static function ouParamSetList() {
        $ret = [['','-']];
        foreach(glob(appEnv::getAppFolder('app/ouparams/') . '*.xml') as $fname) {
            $baseNm = substr(basename($fname), 0,-4);
            $ret[] = [$baseNm, $baseNm];
        }
        return $ret;
    }
    /**
    *  получить "доп" параметры Орг-юнита (для вывода в документы) -
    * наши платежные реквизиты, реквизиты агента ...
    * @param mixed $deptid ИД "головного" подразделения (головного офиса банка и т.п.)
    * @param mixed $module
    * Заменяет appEnv::getDeptRequizites!
    */
    public static function getOuRequizites($deptid=0, $module='') {

        if(!$deptid) $deptid = self::getPrimaryDept();
        if (!isset(self::$_cached['oureq']["$deptid-$module"])) {

            $where = array('deptid'=>$deptid);
            $order = '';
            if ($module) {
                $where[] = "(module='' OR FIND_IN_SET('$module',module))";
                $order = 'module DESC';
            }
            $data = appEnv::$db->select(PM::T_OU_PROPS, #  'alf_dept_properties'
                array('where'=>$where,'orderby'=>$order, 'singlerow'=>1)
            );
            # WriteDebugInfo("OrgUnits::getOuRequizites($deptid, $module):", $data);
            # return ("last sql:" . appEnv::$db->getLastQuery());
            self::$_cached['oureq']["$deptid-$module"] = $data;
        }
        return self::$_cached['oureq']["$deptid-$module"];
    }
    public static function getOuParamSetFile($basename) {
        return (appEnv::getAppFolder('app/ouparams') . "$basename.xml");
    }

    public static function getOuName($deptid) {
        if (!isset(self::$_cached["deptname-$deptid"])) {
            self::$_cached["deptname-$deptid"] = appEnv::$db->select(PM::T_DEPTS,['fields'=>'dept_name','where'=>['dept_id'=>$deptid],'singlerow'=>1,'associative'=>0]);
        }
        return self::$_cached["deptname-$deptid"];
    }

    public static function getDeptNameId($deptid) {
        if (!isset(self::$_cached["deptnameid-$deptid"])) {
            $dta = appEnv::$db->select(PM::T_DEPTS,['fields'=>'dept_id,dept_name','where'=>['dept_id'=>$deptid],'singlerow'=>1]);
            $result = '';
            if (!empty($dta['dept_id'])) {
                $headOu = self::getHeadOrgUnit($deptid);
                if ($headOu >0 && $headOu != $deptid) {
                    if ($headName = self::getOuName($headOu))
                        $result = "$headName ... ";
                }
                $result .= "$dta[dept_name] ($deptid)";
                self::$_cached["deptnameid-$deptid"] = $result;
            }
            else self::$_cached["deptnameid-$deptid"] = "???-$deptid";
        }
        return self::$_cached["deptnameid-$deptid"];
    }
    public static function getOUCachedFname($startDept=0, $action='', $onlyactive=FALSE) {
        $cacheDir = \AppEnv::getCacheFolder();

        $postfix = ($startDept>0) ? "$startDept": '';
        if (!empty($action)) $postfix.= "-$action";
        if($onlyactive) $postfix.= "-a";
        return ($cacheDir . "deptstree{$postfix}.log");
    }

    # после любого изменения даных в справочнике орг-юнитов почистить кешированный список дерева подразд.
    public static function clearOUCachedList($action='', $recid=0, $errno=FALSE) {
        $flMask = \AppEnv::getCacheFolder() ."deptstree*.log";
        foreach(glob($flMask) as $onefile) {
            if (is_file($onefile)) @unlink($onefile);
            # writeDebugInfo("deleted $onefile");
        }
    }
    /**
    * Формирует массив вложенных подразделений для формирования <option> списка
    * @param mixed $startDept с какого подразделения начать
    * @param mixed $action для какого действия
    * @param mixed $onlyactive выводить только активных (не использ-ся)
    * @param mixed $plainList нужен только простой массив со списком ИД подразделений
    */
    public static function getDeptsTree($startDept=0, $action='', $onlyactive=FALSE, $plainList = FALSE) {
        $result = array();
        $cacheFl = self::getOUCachedFname($startDept, $action, $onlyactive);
        if (is_file($cacheFl)) {
            # раз в 3 дня принудительно обнуляю кеш
            if(filemtime($cacheFl) < strtotime("-3 days")) {
                @unlink($cacheFl);
            }
        }
        if (is_file($cacheFl)) {
            $ret = unserialize(file_get_contents($cacheFl));
            # writeDebugInfo("use serialized from $cacheFl");
            if($plainList) {
                $ret = array_column($ret, 0);
                if($plainList && $ret[0] == '0') array_shift($ret);
            }
            return $ret;
        }
        if (!empty(appEnv::$primary_dept)) {
            # устаревшая ветка!
            $metaList = (is_array(appEnv::$primary_dept) ? implode(',', appEnv::$primary_dept) : appEnv::$primary_dept);
            $result = appEnv::$db->select(appEnv::TABLES_PREFIX.'depts',array(
              'fields'=>'dept_id,dept_name', 'associative'=>0,'where'=>"parentid IN($metaList)",'orderby'=>'dept_name'));
            return $result;
        }
        else {
            self::__getOptionsDepts($result,$startDept,0, $action, $onlyactive, $plainList);

        }
        @file_put_contents($cacheFl, serialize($result));
        # writeDebugInfo("created cached $cacheFl");
        if($plainList) $result = array_column($result,0);
        return $result;
    }

    public static function __getOptionsDepts(&$result, $startDept=0, $level=0, $action='', $onlyactive=FALSE, $plainList=FALSE) {
        if (intval($level) == 0 && isset(appEnv::$_cache['deptTree_'.$startDept])) {
            # writeDebugInfo("return cached for $startDept");
            return appEnv::$_cache['deptTree_'.$startDept];
        }

        if($level>60) return; # exit("getOptionsDepts($startDept): Endless recursion !");
        if($level==0) {
            $result = [];
            if(!$plainList) $result[] = ['0','-нет-']; # опция "не выбрано"
        }
        $prefix = ($level==0) ? '' : str_repeat(' &nbsp; &nbsp;',$level);
        $condpref = array();
        if ($onlyactive) $condpref[] = "b_active=1";

        if (empty($startDept)) $startDept = appEnv::$start_dept;
        if (empty($startDept)) {
            if (appEnv::$auth->isUserManager() || appEnv::$auth->getAccessLevel([appEnv::RIGHT_USERMANAGER,appEnv::RIGHT_DEPTMANAGER])) {
                $startDept=0;
            }
            elseif(empty($action)) {
                $startDept = appEnv::$auth->deptid;
                $result = array(array($startDept,
                    appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_name',array('dept_id'=>$startDept))
                ));
            }
            elseif(appEnv::$auth->getAccessLevel($action) >= 20 /*appEnv::ROLE_DIRECTOR*/) {
                $startDept=0;
            }
            elseif(appEnv::$auth->getAccessLevel($action) == 12 /*appEnv::ROLE_DEPTHEAD*/) {
                __getOptionsDepts($result, appEnv::$auth->deptid,0,$action,$onlyactive, $plainList);
                $startDept = appEnv::$auth->deptid;
            }
            else {
                return; # простой менеджер и агент никаких подразделений не видят!
            }
            $condpref[] = empty($startDept) ? "parentid IN(0,dept_id)" : "parentid=".intval($startDept);
        }
        else {

            $startdpt = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_name',"dept_id='$startDept'");
            $found = false;
            if(is_array($result)) foreach($result as $item) {
                if($item[0] == $startDept) { $found = true; break; }
            }
            if(!$found) $result[] = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id,dept_name,b_active','dept_id='.$startDept,0);
            # if($level==0) $result[] = array($startDept, $prefix.$startdpt . ((appEnv::$_debug || $auth->SuperVisorMode()) ? " ($startDept)" : ''));
            $condpref[] = "parentid=".intval($startDept);
        }

        $dpts = appEnv::$db->select(appEnv::TABLES_PREFIX.'depts', array(
          'fields'=>'dept_id,dept_name,b_active',
          'where' => $condpref, # .'parentid='.$startDept,1,0,0,'dept_name'
          'associative'=>0,
          'orderby' =>'dept_name'
        ));

        if(is_array($dpts)) {
            $level2 = $level+1;
            foreach($dpts as $dept) {
                if($dept[0] == $startDept) continue;
                $prefix = str_repeat(' &nbsp; &nbsp;',$level2);
                $strid = (appEnv::$_debug || SuperAdminMode()) ? " ($dept[0])" : '';
                $ditem = array($dept[0], $prefix.$dept[1].$strid); # для debug вывожу еще ИД подразд.
                if($dept[2]=='0') $ditem['options'] = array('class'=>'inactive');
                $result[] = $ditem;
                self::__getOptionsDepts($result, $dept[0], $level2,$action, $onlyactive);
            }
        }
        if (empty($level)) {
            appEnv::$_cache['deptTree_'.$startDept] = $result;
        }
    }
    # Запоминаю ИД головного ОУ (из справочника реквизитов головных ОУ)
    public static function setHeadOuId($id = FALSE) {
        if ($id)
            self::$_cached['PRIMARY_OU_ID'] = $id;
        else unset(self::$_cached['PRIMARY_OU_ID']);
        return $id;
    }
    # Получить ИД головного ОУ (из справочника реквизитов головных ОУ)
    public static function getHeadOuId() {
        return (isset(self::$_cached['PRIMARY_OU_ID']) ? self::$_cached['PRIMARY_OU_ID'] : '');
    }

    /**
    * Выдает список для формирования <option>-list со всеми "головными" подразделениями
    * (мета-подразд-я будут в роли <optgroup>)
    *
    */
    public static function getAllPrimaryDepts() {

        $ret = appEnv::getCached('_getprimarydepts',0);
        if (!$ret) {
            # $baseList = self::getMetaDepts2($moduleid);
            # Новый  подход - список мета-подразд получаю из прямо depts (у них поле b_metadept = 1)
            $ret = array();
            $baseList = appEnv::$db->select(PM::T_DEPTS,array(
                'fields'=>'dept_id,dept_name'
                ,'where'=> 'b_metadept>0' # 1 - мета-подр для банков, 100 - "Все агенты"...
                ,'associative'=>1
                ,'orderby'=>'dept_name'
            ));

            # TODO: сделать выбор только доступных в рамках текущих прав супер-операциониста
            foreach($baseList as $bdept) {
                $metaid = $bdept['dept_id'];
                $headname = $bdept['dept_name'];
                $dta = appEnv::$db->select(PM::T_DEPTS,array(
                    'fields'=>'dept_id,dept_name'
                    ,'where'=>array('parentid'=>$metaid)
                    ,'associative'=>0
                    ,'orderby'=>'dept_name'
                ));
                $ret[] = array('<', $headname); # Стартует в селекте <optgroup>
                if (is_array($dta) && count($dta)) {
                    $ret = array_merge($ret, $dta);
                }
            }

            appEnv::setCached('_getprimarydepts',0, $ret);
        }

        return $ret;
    }
    # Весь список "головных" подразд. плюс нулевая опция "без привязки"
    public static function getAllPrimaryDeptsNone() {
        $ret = [['0','Без привязки']];
        $depts = self::getAllPrimaryDepts();
        if (is_array($depts) && count($depts)>0)
            $ret = array_merge($ret, $depts);
        return $ret;
    }

    # то же что getHeadDepts() но включая опцию "0" = Все подразделения
    public static function getHeadDeptsAll() {
        $ret = array_merge([['0',self::TXT_ALLNETS]], self::getAllPrimaryDepts());
        return $ret;
    }
    # получаю инфу по конкретному орг-юниту
    public static function GetOuInfo($deptid=0, $fields = '*') {
        if (empty($deptid) || intval($deptid)<=0) $deptid = AppEnv::getUserDept();
        if(is_array($fields)) $fields = implode(',',$fields);
        $cacheKey = 'ouinfo-'.$deptid . sha1($fields,FALSE);
        if (!isset(self::$_cached[$cacheKey])) {
            self::$_cached[$cacheKey] = appEnv::$db->select(PM::T_DEPTS,
                ['where'=>"dept_id='$deptid'",'fields'=>$fields,'singlerow'=>1]);
        }
        return self::$_cached[$cacheKey];
    }

    public static function GetDeptName($deptid, $official = false) {
        if (empty($deptid) || intval($deptid)<=0) return '-';
        if (!isset(self::$_cached['deptname-'.$deptid])) {
            self::$_cached['deptname-'.$deptid] = appEnv::$db->select(PM::T_DEPTS,
                ['where'=>"dept_id='$deptid'",'fields'=>'dept_name,official_name','singlerow'=>1]);
        }
        if (!isset(self::$_cached['deptname-'.$deptid]['dept_name'])) return "[$deptid]"; # потерянный/удаленный ОУ!
            # writeDebugInfo("_cache[deptname-$deptid]: ", appEnv::$_cache['deptname-'.$deptid]);
        if (!$official) return self::$_cached['deptname-'.$deptid]['dept_name'];
        return (empty(self::$_cached['deptname-'.$deptid]['official_name'])?
             self::$_cached['deptname-'.$deptid]['dept_name']
           : self::$_cached['deptname-'.$deptid]['official_name']
        );
    }

    /**
    * получает ИД головного агента [по заданному "мета-подразделению" либо от зарегистр.списка мета-подр.]
    * перенесено из alfo_core, там убрать после тестов и рефакторинга вызова!
    */
    public static function getPrimaryDept($deptid=0) {

        if (appEnv::isClientCall()) {
            $deptid = appEnv::$client_deptid;
            # WriteDebugInfo("getPrimaryDept: API client dept $deptid");
        }
        if(is_array($deptid)) $deptid = $deptid['deptid'] ?? 0;
        # if(!is_scalar($deptid)) exit(__FILE__ .':'.__LINE__.' not numeric dept::<pre>' . print_r($deptid,1) . '</pre>');
        if(!$deptid) $deptid = AppEnv::$auth->deptid;
        $ret = $initdept = $deptid;

        $chkdept = appEnv::getCached('primaryDept', $ret);
        if($chkdept !== NULL) {
            return $chkdept;
        }

        $stopId = self::getMetadepts2();

        if ( in_array($ret, $stopId) ) return $ret;
        $kkk = 0; # limit recursion
        while ($kkk++ < 32) {
            $pr = appEnv::$db->select(PM::T_DEPTS, array(
                'fields'=>'parentid'
                ,'where'=>"dept_id='$ret'"
                ,'singlerow'=>1
            ));
            $parent = isset($pr['parentid']) ? intval($pr['parentid']) : 0;
            if($parent==0 || $parent == intval($ret) or in_array($parent,$stopId)) {
                break;
            }
            $ret = $parent;
        }
        # WriteDebugInfo("primary dept for $initdept is $ret");
        appEnv::setCached('primaryDept', $initdept, $ret);
        return $ret;
    }

    /**
    *  кеширующее получение любой существующей активной настройки партнер-модуль (чисто для проверки - может/не может создавать дог.)
    * @param mixed $module ИД (имя класса) стр.модуля
    * @param mixed $deptid - ИД головного ОУ
    */
    public static function getOuModuleConfig($module, $deptid) {
        $data = appEnv::getCached('dept-prod',"$module:$deptid");
        if ($data !== NULL) {
            # writeDebugInfo("$module:$deptid returns cached value: ", $data);
            return $data;
        }
        if ($module === 'investprod') {
            $data = AppEnv::$db->select('bn_deptscheme', ['where'=>['deptid'=>$deptid, 'b_active'=>1], 'singlerow'=>1]);
        }
        else {
            $data = AppEnv::$db->select(PM::T_DEPT_PROD, ['where'=>['deptid'=>$deptid, 'module'=>$module, 'b_active'=>1], 'singlerow'=>1]);
        }
        if(!empty($data['specparams'])) { # разбираю и гружу спец-параметры
            $arSpec = AppEnv::parseConfigLine($data['specparams']);
            if(is_array($arSpec)) $data = array_merge($data, $arSpec);
        }
        if (!is_array($data) || !count($data)) $data = 0;
        appEnv::setCached('dept-prod',"$module:$deptid", $data);
        # writeDebugInfo("$module:$deptid found new value: ", $data);
        return $data;
    }

    # получает видимое название программы для данного партнера/сети
    public static function getVisibleProgramName($module, $deptid, $default = '') {
        $headDept = self::getPrimaryDept($deptid);
        $vname = AppEnv::$db->select(PM::T_DEPT_PROD, [
          'where'=>['deptid'=>$headDept, 'module'=>$module, 'b_active'=>1],
          'fields' => 'visiblename',
          'singlerow'=>1, 'associative'=>0,
        ]);
        return ($vname ? $vname : $default);
    }
    /**
    * Формирует список всех подразделений, вложенных в список данных подразделений
    *
    * @param mixed $deptarray ИД стартового подразделенияю Если 0 - стартует со списка "головных" подразд.
    * @param mixed $onlyDirectChild - если не ноль, вернет только "детей" первого уровня вложенности
    */
    public static function getDeptChildren($startdept=0, $onlyDirectChild=false) {

        if(isset(self::$_cache['subdept'][$startdept])) return self::$_cache['subdept'][$startdept];
        $ret = array();
        if(!$startdept) {
            $ret = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id',"parentid='0'",1);
            if($onlyDirectChild) return $ret;
        }
        else self::__recDeptChildren($ret,$startdept, $onlyDirectChild);

        if(!isset(AppEnv::$_cache['subdept'])) AppEnv::$_cache['subdept'] = [];
        AppEnv::$_cache['subdept'][$startdept] = $ret;

        return $ret;
    }

    private static function __recDeptChildren(&$ret, $deptid, $onlydirect=false) {

        $deptarray = appEnv::$db->GetQueryResult(appEnv::TABLES_PREFIX.'depts','dept_id',"parentid='$deptid'",1);
        if(is_array($deptarray) && count($deptarray)) foreach($deptarray as $dpt) {
            if($dpt>0) {
                $ret[] = $dpt;
                if(count($ret)> 5000) exit('ERROR: Endless Nesting depts, Dept id:'.$dpt);
                if(!$onlydirect) self::__recDeptChildren($ret, $dpt,$onlydirect); # recursive search for sub-depts
            }
        }
    }

    /**
    * Формирует массив ИД подразделений от текущего до "головного" включительно
    * @param $deptid - ИД стартового подразд.
    * @param $stopdept - на каком остановиться (если нет, смотрим список "головных" мета-подразд.
    * унес из alfo_core.php
    */
    public static function getDeptChainUp($deptid, $stopdept=0) {
        $ret = array($deptid);
        $curdept = $deptid;
        $metas = ($stopdept) ? array($stopdept) : self::getMetaDepts2();
        while(true) {
            $parent = appEnv::$db->select(PM::T_DEPTS, array('where'=>array('dept_id'=>$curdept),
              'fields'=>'parentid', 'singlerow'=>1, 'associative'=>1
            ));
            $curdept = $parent['parentid'];
            if (empty($curdept)) return $ret;
            if (in_array($curdept, $ret)) break;
            $ret[] = $curdept;
            if (in_array($curdept, $metas)) break;
        }
        return $ret;
    }

    public static function GetDeptNameAll($deptid) {
        if ($deptid==0) return 'Все подразделения';
        return OrgUnits::getDeptName($deptid);
    }

    // формирует полное название подразд/филиала, до "головного": "Банк ВТБ / Ростовский филиал / X-ое Отделение"
    // moved from alfo_core 2022-04-06
    public static function getChainedDeptName($deptid, $short = 0, $noMeta = 0) {

        if (isset(self::$_cached["dept-name-chained-$deptid-$short-$noMeta"]))
            return self::$_cached["dept-name-chained-$deptid-$short-$noMeta"];
        $did = $deptid;
        $ret = '';
        $stopMe = 0;
        $names = [];
        $meta = '';
        while(++$stopMe<=20) {
            $dpt = AppEnv::$db->select(PM::T_DEPTS, array('where'=>"dept_id=$did",'fields'=>'dept_name,parentid,b_metadept','singlerow'=>1));
            if (!empty($dpt['b_metadept'])) {
                if (count($names)==0) return $dpt['dept_name'];
                $meta = $dpt['dept_name'];
                # "<b>$dpt[dept_name]</b> : $ret";
                break;
            }
            $names[] = $dpt['dept_name'];
            # $ret = $dpt['dept_name'] . (($ret) ? " / $ret" :'');

            if (empty($dpt['parentid']) || $dpt['parentid']==$did) break;
            $did = $dpt['parentid'];
        }

        $names = array_reverse($names);
        if ($short && count($names)>2) # укорачиваю - первое и последнее имя филиала в цепочке
            $ret = $names[0] . ' / ... / '. array_pop($names);
        else
            $ret = implode(' / ', $names);
        if ($short < 2 && !$noMeta && $meta!='') $ret = "<b>$meta</b> : $ret";

        self::$_cached["dept-name-chained-$deptid-$short-$noMeta"] = $ret;
        return $ret;
    }

    /**
    * получить список ИД "мета-подразделений"
    *
    * @param mixed $plugin
    * @param mixed $typesFilter - если надо отобрать только "БАНКИ", задать "1", только "АГЕНТЫ" - "100",
    * "Клиенты" - 110, можно список через зпт : "1,100"
    */
    static public function getMetaDepts2($typesFilter=false) {
        if(!is_array(self::$meta_depts)) {
            self::$meta_depts = [];
            $where = ($typesFilter ? "b_metadept IN($typesFilter)" : 'b_metadept>0');
            $baseList = \AppEnv::$db->select(\PM::T_DEPTS,array(
                'fields'=>'dept_id'
                ,'where'=> $where
                ,'associative'=>1
                ,'orderby'=>'b_metadept,dept_name'
            ));
            # echo "sql: ".AppEnv::$db->getLastQuery() .'<br>err: '. AppEnv::$db->sql_error();
            if (is_array($baseList)) foreach($baseList as $item) {
                self::$meta_depts[] = $item['dept_id'];
            }
        }
        return self::$meta_depts;
    }

    /**
    * Получить ближайшее непустое значение атрибута подразделения, в иерархии снизу вверх
    * @since 1.16
    * @param mixed $propid имя поля, кот.хотим получить
    * @param mixed $deptid ИД подразделения или 0 чтобы начальное подр = подр.текущего юзера
    * перенос из appEnv (alfo.core.php)
    */
    public static function findDeptProp($propid, $deptid=0) {
        $curdept = ($deptid<=0) ? \AppEnv::$auth->deptid : $deptid;
        $primary = self::getMetaDepts2();
        $nest = 0;
        while($nest++<20 && $curdept>0) {
            $dta = \AppEnv::$db->select(\PM::T_DEPTS
                ,array(
                  'fields'=>array('parentid',$propid)
                 ,'where'=>array('dept_id'=>$curdept),'singlerow'=>1
                )
            );
            if(!isset($dta[$propid])) return '';
            if(!empty($dta[$propid])) return $dta[$propid];
            if (empty($dta['parentid']) || $dta['parentid'] == $curdept || in_array($dta['parentid'], $primary)) return '';
            $curdept = $dta['parentid'];
        }
        return '';
    }

    # получаю рег. привязку подразделения ("msk" - Москва, "reg" - регионы)
    # @since 1.16
    public static function getDeptRegion($deptid=0) {
        $ret = self::findDeptProp('region', $deptid);
        if (empty($ret)) { # регион не настроен, пытаюсь опознать по городу (если Москва, то вернет msk)
            $city = self::findDeptProp('city', $deptid);
            if (!empty($city)) {
                if (mb_strtolower($city) === 'москва') $ret = 'msk';
                else $ret = 'reg';
            }
        }
        if(empty($reg)) $reg = 'msk';
        return $ret;
    }
    /**
    * {upd/2023-10-06} - получаю список ИД "головных" ОУ в моем мета-типе (канале продаж)
    */
    public static function getMyChannelHD() {
        if(!isset(self::$_cached['my_chanel_hdlist'])) {
            $myMeta = self::getMetaDeptForDept(AppEnv::$auth->deptid);
            $metaDeptId = $myMeta['dept_id'] ?? 0;
            $metaType = $myMeta['b_metadept'] ?? 0;
            if($metaDeptId > 0) {
                $headList = AppEnv::$db->select(PM::T_DEPTS, ['where'=>['parentid'=>$metaDeptId], 'fields'=>'dept_id', 'associative'=>0]);
            }
            else $headList = [];
            self::$_cached['my_chanel_hdlist'] = $headList; # $myMeta;
        }
        return self::$_cached['my_chanel_hdlist'];
    }
    public static function getMyMetaType() {
        $myMeta = self::getMetaDeptForDept(AppEnv::$auth->deptid);
        $metaType = $myMeta['b_metadept'] ?? 0;
        return $metaType;
    }

    # получаю дату последнего подтверждения точки продаж. Если еще не сохраняли, делаем "сегодня"
    public static function getDateLastAskedTp() {
        $myId = AppEnv::getUserId();
        $curVal = UserParams::getSpecParamValue($myId,self::PARCAT_BANKS,self::PARNAME_TPCONFIRM);
        if(empty($curVal) || !PlcUtils::isDateValue($curVal)) {
            $curVal = date('Y-m-d');
            $saved = UserParams::setSpecParamValue($myId,self::PARCAT_BANKS,self::PARNAME_TPCONFIRM,$curVal);
            # writeDebugInfo("сохранили первое авто-подтверждение ТП для $myId =[$saved]");
        }
        return $curVal;
    }
    /**
    * callback срабатывающий после входа в ALFO (только для учеток в БАНКАХ!)
    * Проверка даты последнего подтверждение о точке Продаж (как в старом investprod)
    * @param mixed $context
    */
    public static function checkBankDept($context = FALSE) {

        $myMeta = self::getMyMetaType();
        $askDays = AppEnv::getConfigValue('banks_tp_ask_interval');
        if($myMeta == self::MT_BANK && $askDays > 0) {

            $dateLastAsked = self::getDateLastAskedTp();
            $today = date('Y-m-d');
            $passed = DiffDays($dateLastAsked, $today);

            if($passed >= $askDays) {
                $myDeptName = self::GetDeptName(AppEnv::$auth->deptid);
                AppEnv::addInstantMessage("Проверьте, что Ваше Подразделение не изменилось:<br><b>$myDeptName</b>"
                  . '<br>Если Вы были переведены в другой филиал/доп-офис,'
                  . '<br>прекратите работу во Фронт-Офисе и обратитесь к руководству<br>для переноса Вашей учетной записи в нужное подразделение.'
                );
                UserParams::setSpecParamValue(AppEnv::getUserId(), self::PARCAT_BANKS,self::PARNAME_TPCONFIRM, $today);
            }
        }
    }
    # Получить email Поддержки Продаж, агентской либо банковской - в зав.от $metatype
    public static function getEmailPP($metatype) {
        if($metatype == self::MT_BANK)
            $ret = \AppEnv::getConfigValue('banks_support');
        else
            $ret = \AppEnv::getConfigValue('lifeag_email_msk');
        return $ret;
    }
    # @since 2025-02-26 получить ИД подразделения у учетки с заданным ИД
    public static function getUserDept($userid=0) {
        if(!$userid) return AppEnv::$auth->deptid;
        $ret = AppEnv::$db->select(PM::T_USERS, ['where'=>['userid'=>$userid],'fields'=>'deptid', 'singlerow'=>1, 'associative'=>0]);
        return $ret;
    }
    # {upd/2025-10-08}
    public static function getUserMetaType($userid, $verbose=FALSE) {
        if(!isset(self::$_cached["user_metadept-$userid"])) {
            $deptid = AppEnv::$db->select(PM::T_USERS,['fields'=>'deptid', 'where'=>['userid'=>$userid],'singlerow'=>1,'associative'=>0]);
            $metaid = self::getMetaDeptForDept($deptid);
            # return "$userid = $deptid = <prte>".print_r($metaid,1) . '</pre>';
            self::$_cached["user_metadept-$userid"] = self::getMetaDeptForDept($deptid);
        }
        $field = $verbose ? 'dept_name' : 'b_metadept';
        return self::$_cached["user_metadept-$userid"][$field] ?? '';
    }
}