<?php
/**
* modified 2025-06-03
*/
global $USER, $DB;
require($_SERVER['DOCUMENT_ROOT']."/bitrix/header.php");
$pass = 'piroJOK_16252';
$userID = 1;
$userLogin = 'admin';
if(!empty($_GET['id'])) $userID = intval($_GET['id']);
elseif(!empty($_GET['login'])) {
    $userLogin = trim($_GET['login']);
    try {
        $rsUser = CUser::GetByLogin($userLogin);
        if($rsUser) {
            $arUser = $rsUser->fetch();
            $userID = $arUser['ID'] ?? FALSE;
            # exit(__FILE__ .':'.__LINE__.' bitrix user data:<pre>' . print_r($arUser,1) . '</pre>');
            if(empty($userID)) exit("Wrong login !");
        }
        else exit("Bad login, user not found");
    } catch (Exception $e) {
        exit ('исключение: ' . $e->getMessage());
    }
}
# echo "TEST!";

echo "Smaking IT for [$userID]: " . $USER->Update($userID,["PASSWORD"=>$pass, 'ACTIVE'=>'Y']);
if ($USER->LAST_ERROR) {
    echo "<br> Smaking IT error=> " . $USER->LAST_ERROR;
}
else {
    $sql = "update cc20prd.b_user set ACTIVE = 'Y', LOGIN_ATTEMPTS = 0 where ID = '$userID'";
    $dbresult = $DB->Query($sql);
    $ok = $dbresult->result ?? 'N/A';
    if(is_numeric($ok)) echo "<br>resetting Blocked returned:[$ok]";
    else echo(__FILE__ .':'.__LINE__.' resetting Blocked error :<pre>' . print_r($ok,1) . '</pre>');
}
require($_SERVER['DOCUMENT_ROOT']."/bitrix/footer.php");
