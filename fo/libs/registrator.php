<?php
/**
* @package ALFO
* @name libs/registrator.php
* Регистрация в "агентском" журнале особых событий
* @version 0.7.001
* @created 2023-06-22 \Libs\Registrator::addEvent()
* modified 2025-11-20
*/
namespace Libs; # всё что в папке libs - с таким namespace !

class Registrator {
    const VERSION = '0.7.001';

    public static function getVersion() { return self::VERSION; }

    /**
    * Registrator::addEvent()
    * регистрирует событие в общем журнале
    * @param mixed $module
    * @param mixed $evtype
    * @param mixed $itemid
    * @param mixed $agentfio
    * @param mixed $insuredData готовая стррока идентификатора "застрахованного
    * (Пол:дата_рожд:профессия) либо массив, из которого его можно составить
    */
    public static function addEvent($module, $evtype, $itemid='', $agentfio='',$insuredData='', $prolong = NULL, $clientid = FALSE, $payment=FALSE) {
        if(is_array($insuredData)) {
            $insuredKey = self::calcInsuredKey($insuredData);
            if($prolong === NULL && isset($insuredData['previous_id']))
                $prolong = $insuredData['previous_id'];
            if($clientid===FALSE && isset($insuredData['clientid'])) $clientid = $insuredData['clientid'];
        }
        else $insuredKey = (string)$insuredData;

        $dta = [
          'evdate' => date('Y-m-d'),
          'module' => $module,
          'evtype' => $evtype,
          'userid' => \AppEnv::getUserId(),
          'deptid' => (\AppEnv::$auth->deptid ?? 0),
          'clientid' => $clientid,
          'itemid' => (string)$itemid,
          'insured_key' => $insuredKey,
        ];
        # myagent Если передан числовой ИД - значит, манагер считал от имени выбранного агента, его и заношу как Юзера
        if(is_numeric($agentfio)) $dta['userid'] = $agentfio;
        else $dt['agent_fio'] = $agentfio;

        if($payment!==FALSE) $dta['payment'] = ($payment>0) ? 'R' : 'S'; # оплата - рассрочка/единовр.

        # регистрирую калькуляцию (или сохранение) пролонгации
        if($prolong === NULL && is_array($insuredData)) {
            $prolong = $insuredData['previous_id'] ?? $insuredData['prolong'] ?? NULL;
        }
        if($prolong !== NULL)
            $dta['prolong'] = empty($prolong) ? 'N' : 'Y';

       $ret = \AppEnv::$db->insert(\PM::T_AGENT_LOG, $dta);
       return $ret;
    }
    public static function calcInsuredKey($arData) {
        $ret = [];
        $ret[] = $arData['sex'] ?? $arData['insured_gender'] ?? $arData['insured_sex'] ?? 'M';
        $ret[] = $arData['birthDate'] ?? $arData['insured_birth'] ?? 'nodata';
        $ret[] = $arData['profession'] ?? $arData['ins_profession'] ?? 'no';
        return implode('|', $ret);
    }
    public static function savePolicyId($policyid, $eventid) {
        $result = \AppEnv::$db->update(\PM::T_AGENT_LOG, ['policyid'=>$policyid], ['evid'=>$eventid]);
        return $result;
    }
    # TODO: ассистент по выдаче отчета для "воронки продаж"
    public static function report($params) {
    }
    # TODO: очистка от старых записей в журнале
    public static function rotateLog($days = 30) {
        $deadDate = date('Y-m-d', strtotime("-$days"));
        \AppEnv::$db->delete(\PM::T_AGENT_LOG, "evdate<'$deadDate'");
    }
}