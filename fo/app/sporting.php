<?php
/**
* @package ALFO
* @name app/sporting.php
* Работа с видами спорта (started 2021-01-14)
* @version 1.05.002 modified 2024-07-20
*/
class Sporting {
    static $riskNames = ['death_any','death_acc','invalid_any','invalid_acc','trauma','ci'];
    static $mark_uwTyps = TRUE; # К названиям спорта, требующих андеррайтинга, добавлять подсказку "(uw)"
    static $LIST_LIMIT = 0; # ограничение на длину списка видов спорта при выводе в полис
    private static $debug = 0;
    private static $initData = [
      'death_any_pc' => 0, 'death_any_pm' => 0, # exmPercent/exmPpm смерть ЛП - процент, промиль OK
      'ci_pc' => 0, 'ci_pm' => 0, # CIexm_percent/CIexm_ppm КЗ OK
      'invalid_any_pc' => 0, 'invalid_any_pm' => 0, # exdPercent/exdPpm инвал.ЛП/ОУСВ OK
      'invalid_acc_pc' => 0, 'invalid_acc_pm' => 0, # exAccTpd,OldExAccTPD инвал.НС
      'trauma_pc' => 0, 'trauma_pm' => 0, # exTrauma.../ травма НС
      'death_acc_pc'=>0, 'death_acc_pm'=>0, # exAccDb/exAccDb2 смерть НС
      'uw' => 0, # отправить на UW
      'block_risks' => [] # список рисков, запрещенных для страхования
    ];

    # вернет режим активности "спортивных надбавок"
    public static function isActive($prgData=0) {
        # return is_file(__DIR__ . '/sporting.flag'); # пока только на ПК разработчика
        # $ret = !appEnv::isProdEnv(); # пока только в тестовой среде
        $ret = appEnv::getConfigValue('use_sporting');
        if($ret && is_array($prgData) && isset($prgData['sport'])) {
            # есть свое разрешение на спорт в настройках программы
            $ret = !empty($prgData['sport']);
            # writeDebugInfo("sport from program is: [$ret]");
        }
        return $ret;
    }

    /**
    * получить HTML блок выбора видов спорта (для калькулятора
    * @param $type : 'ONLINE' или любое непустое для онлайн-калькулятора, иначе - для продукта в ALFO
    * @param $prefix : '' префикс в названии переменной
    * @param $forAdult : TRUE ели надо указать что Сопрт для ВЗРОСЛОГО Застрахованного
    */
    public static function htmlCalcBlock($type = FALSE, $prefix='', $forAdult=FALSE) {
        $list = appEnv::$db->select(PM::T_SPORTS, ['fields'=>'spkey,sportname,uw', 'orderby'=>'sportname', 'associative'=>1]);
        if (!isset($list[0]['spkey'])) return '';
        $ret = '';
        foreach ($list as $row) {
            $fname = 'sport' . $prefix . '_' . $row['spkey'];
            $spname = htmlentities($row['sportname'],ENT_COMPAT, MAINCHARSET);
            if (self::$mark_uwTyps && $row['uw']>0) $spname .= ' (uw)';
            $ret .= "<tr class='sport_item'><td><label><input type='checkbox' class='sport' name='$fname' id='$fname' value='1'>$spname</label></td></tr>\n";
        }
        # file_put_contents('_sports.log', $ret);
        $spTitle = $forAdult ? 'Занятия спортом Взрослого Застрахованного' : 'Занятия спортом';
        $ret = <<< EOHTM
<div><span role="button" id="btn_sports" class="btn btn-primary" onclick="sports.subformToggle()"><i class="bi bi-caret-down-fill"></i> $spTitle</span>

 <div class="block_sports bg-light card position-absolute w300 p-2 shadow-lg" id="block_sports">
   <input type="text" name="sport_filt" id="sport_filt" class="form-control d-inline w100" onkeyup="sports.doFilter()" autocomplete="off" onchange="sports.doFilter()"> Фильтр по названию
   <div class="card mt-2 overflow-auto h300"><table>$ret</table></div>
 </div>

</div>
EOHTM;
        return $ret;
    }
    /**
    *  блок javascript для калькулятора
    * @param $type : 'ONLINE' или любое непустое для онлайн-калькулятора, иначе - для продукта в ALFO
    */
    public static function jsCalcBlock($type = FALSE) {
        $ret = <<< EOJS
sports = {
  doFilter: function() {
    var fltValue = $("#sport_filt").val().toLowerCase();
    if(fltValue === "") $("tr.sport_item").show();
    else $("tr.sport_item").each(function() {
      var strVal = $("label", this).text().toLowerCase();
      if(strVal.indexOf(fltValue)>=0) $(this).show();
      else $(this).hide();
    });
  },
  subformToggle: function() {
    $("#block_sports").toggle();
    $("#btn_sports i").toggleClass("bi-caret-down-fill").toggleClass("bi-caret-up-fill");
  }
};
EOJS;
        return $ret;
    }

    # вернет просто нули во всех коэф-тах
    public static function clearCoeffs() { return self::$initData; }

    /**
    * вернет массив итоговых поправок по всем выбранным видам спорта
    *
    */
    public static function getTotalModifiers($pars = FALSE, $prefix = '') {
        if (!self::isActive()) return self::$initData;
        if (!$pars) $pars = appEnv::$_p;
        if(self::$debug) writeDebugInfo("Sporting, params : ", $pars);
        $ret = self::$initData;

        $fldPref = 'sport'.$prefix.'_';
        $prlen = strlen($fldPref);
        foreach($pars as $pname => $val) {
            if (substr($pname,0,$prlen) === $fldPref) {
                $spid = substr($pname, $prlen); # вытащил ИД вида спорта
                $data = appEnv::$db->select(PM::T_SPORTS,['where'=>['spkey'=>$spid],'singlerow'=>1]);
                if(self::$debug) writeDebugInfo("data for $spid: ", $data);
                if (!isset($data['id'])) continue;
                if ($data['uw'] > 0) {
                    $ret['uw'] = 1;
                    continue;
                }
                foreach(self::$riskNames as $rname) {
                    if(!empty($data[$rname])) {
                        if($data[$rname] === 'D') {
                            if(!in_array($rname, $ret['block_risks']))
                                $ret['block_risks'][] = $rname;
                            # if ($rname === 'invalid_acc' && !in_array('trauma_acc', $ret['block_risks'])) {
                            #    $ret['block_risks'][] = 'trauma_acc';
                            # }

                            # $ret['uw'] = 1;
                            # continue 2;
                        }
                        $decVal = floatval($data[$rname]);
                        if ($decVal==0) continue;
                        if(substr($data[$rname], -1) == '%') {
                            $ret[$rname.'_pc'] += $decVal;
                            # if ($rname === 'invalid_acc') # Травма НС = инвал НС
                            #     $ret['trauma_pc'] += $decVal; # травма больше не в одном поле с инвал.НС!
                        }
                        else { # промилли
                            $ret[$rname.'_pm'] += $decVal;
                        }
                    }
                }
            }
        }
        if(self::$debug) writeDebugInfo("returning sport coeffs ", $ret);
        return $ret;
    }
    # Вернет данные по виду спорта по его ключу
    public static function getSportById($key) {
        $arDta = AppEnv::$db->select(PM::T_SPORTS, ['where' => ['spkey'=>$key], 'singlerow'=>1]);
        return $arDta;
    }

    # Сформирует строку с перечислением всех подключенных видов спорта, анализируя параметры калькуляции
    # Включаем названия до достижения лимита на длину строки! после чего закрываем фразой " и др."
    public static function decodeSportList($params, $asArray=FALSE) {
        if(!is_array($params)) return '';
        $ret = [];
        foreach($params as $key=>$val) {
            if(substr($key,0, 6)==='sport_' && $val>0) {
                $spid = substr($key,6);
                if($spid =='filt') continue;
                $sportDta = self::getSportById($spid);
                if(!empty($sportDta['sportname'])) {
                    # if(self::$LIST_LIMIT==0 || (mb_strlen($ret)+mb_strlen($sportDta['sportname'])+3) <=self::$LIST_LIMIT)
                        # $ret .= ($ret ? ', ':'') . $sportDta['sportname'];
                        $ret[] = $sportDta['sportname'];
                    # else {
                    #     $ret .= ' и др.';
                    #     break;
                    # }
                }
            }
        }
        return ($asArray ? $ret : implode(', ', $ret));
    }
    # вернет кол-во включенных видов спорта
    public static function getActiveCount($params) {
        $ret = 0;
        if(!is_array($params)) return 0;
        foreach($params as $key=>$val) {
            if(substr($key,0, 6)==='sport_' && $val>0) $ret++;
        }
        return $ret;
    }
    # после изменения sportname сразу звношу MD5 фрагиент в spkey
    public static function updateSpKey($params,$act) {
        $arRet = $params;
        if(in_array($act, ['doadd','doedit'])) {
            if(empty($params['sportname']))
                $arRet['spkey'] = '';
            else {
                $arRet['sportname'] = rusUtils::mb_trim($params['sportname']);
                $arRet['spkey'] = substr(md5($arRet['sportname']),0,10);
            }
            # writeDebugInfo("old spkey: $params[spkey], new: $arRet[spkey]");
        }
        return $arRet;
    }
}