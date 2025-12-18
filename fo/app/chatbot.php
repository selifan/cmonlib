<?php
/**
* страница чат-бот (юзает libs/aibus.php
* modified 2025-12-17
*/
class ChatBot {
    static $engine = 'stub';
    static $botName = 'Чат-бот'; # что будет видно в заголовках ответов
    static $userChatSession = '';
    const T_CHATBOT_HIST = 'chatbot_hist';
    const T_CHATBOT_CONTEXTS = 'chatbot_contexts';

    public static function init() {
        if(empty(self::$userChatSession) || empty($_SESSION['chat_user_session'])) {
            if(empty($_SESSION['chat_user_session']))
                $_SESSION['chat_user_session'] = 'chat-'.date('Y-m-d-H-i-s') . rand(100,999);
            self::$userChatSession = $_SESSION['chat_user_session'];
        }
        if(!empty($_SESSION['chat_engine'])) {
            self::setEngine($_SESSION['chat_engine']);
        }
    }
    public static function setEngine($strEngine) {
        if(self::$engine != $strEngine) {
            self::$engine = $strEngine;
            unset($_SESSION['chat_user_session']);
            self::init();
        }
    }
    # готовлю HTML код для диалога выбора контекста
    public static function initContextDialog() {
        $html = "<div class='p-2'>Выберите один из настроенных контекстов или создайте новый:<form id='fm_chat_setcontext' class='was-validated'>";
        # select from self::T_CHATBOT_CONTEXTS
        $myid = \AppEnv::getUserId();
        $newClass = '';
        $curCtx = \AppEnv::$db->select(self::T_CHATBOT_CONTEXTS, ['fields'=>'id,userid,context_name',
          'where' => "userid IN('__system__','$myid')", 'orderby'=>'context_name']);
        if(is_array($curCtx) && count($curCtx)) {
            $fmt = "<div class='bordered p-2 m-2'><label><input type='radio' name='contextid' value='%s' onclick='chatBot.chgContext(this)'/> %s</label></div>";
            $newClass = 'hideme'; # блок ввода нового контекста изначально скрыт, т.к. есть готовые
            foreach($curCtx as $item) {
                $html .= sprintf($fmt, $item['id'], $item['context_name']);
            }
            $html .= sprintf($fmt, '_new_', 'Создать новую настройку контекста');
        }
        $html .= "<div class='$newClass' id='new_context'>Новый контекст - название:<br><div class='row'><input type='text' name='ctx_name' class='form-control w300' required/></div>"
          . "Текст <br><div class='row'><textarea name='ctx_content' class='form-control w100prc' style='height:100px; overflow:auto; resize:none' required></textarea></div></div></form></div>";
        # writeDebugInfo("htmlChoose: ", $html);
        exit($html);
    }
    # форма в браузере, основа взята здесь: https://imranonline.net/building-an-ai-chatbot-with-php-and-deepseek-a-step-by-step-guide/
    public static function form() {
        self::init();
        $pagetitle = "Чат-бот";
        \AppEnv::setPageTitle($pagetitle);
        $botname = self::$botName;
        $engine = self::$engine;
        $btnContext = "<button class=\"btn btn-primary\" id=\"btn_context\" onclick=\"chatBot.selectContext()\">Задать контекст</button>";
        $curContextId = $_SESSION['chatbot_context'] ?? '';
        if($curContextId) {
            $strContext = "Контекст:" . self::getContextName($curContextId);
        }
        else $strContext = 'Контекст не задан';
        $html = <<< EOHTM
<h1>$pagetitle ($engine) $btnContext</h1>
<div id="cur_context" class="bordered msg_ok" style="position:fixed; z-index:50000; top:10px; right:20px; width:auto">$strContext</div>
<div id="chat">
    <div id="messages" class="bordered p-2" style="min-height:100px; _max-height:800px; overflow:auto"></div>
    <form id="chat-form">
        Введите вопрос<br>
        <textarea id="user-input"  required="required" class="form-control" style="height:100px;overflow:auto"></textarea>
        <br>
        <button type="submit" class="btn btn-primary w200" m-2>Отправить запрос</button>
        <button type="button" id="btn_reset_chat" class="btn btn-primary w200 m-2" onclick="chatBot.clearChatHistory()" disabled="disabled">(новый чат)</button>
    </form>
</div>

<script>

document.getElementById('chat-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    var userInput = $("#user-input").val();
    const messagesDiv = document.getElementById('messages');

    // Add user message to chat
    messagesDiv.innerHTML += '<p><strong>Вы:</strong><br>' + userInput + '</p>';

    // Send message to server
    var response = await fetch('./?p=chatbot&action=request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: userInput })
    });
    // console.log(response);
    var data = await response.json();
    console.log(data);
    messagesDiv.innerHTML += '<p><strong>$botname:</strong><br>'+ data.reply + '</p>';
    $(window).scrollTop(32000);
    $("#btn_context").addClass("hideme");
    // Clear input
    // document.getElementById('user-input').value = '';
    $("#user-input").val('');
    $("#btn_reset_chat").attr("disabled", false);
});
chatBot = {
  backend: "./?p=chatbot",
  clearChatHistory: function() {
    $("#messages").html("");
    $("#btn_context").removeClass("hideme");
    asJs.sendRequest(chatBot.backend,{action:"resetHistory"}, true);
  },
  selectContext: function() {
    $.post(chatBot.backend, {'action':'initContextDialog'}, function(response) {
      dlgConfirm(response,chatBot.performSetContext);
    });
  },
  chgContext: function(obj) {
    if(obj.value ==='_new_') $("#new_context").removeClass("hideme");
    else $("#new_context").addClass("hideme");
  },
  performSetContext: function() {
    var params = $("#fm_chat_setcontext").serialize();
    params += "&action=activateContext";
    console.log("TODO: set context ", params);
    asJs.sendRequest(chatBot.backend,params, true);
  },
}
</script>
EOHTM;
        \AppEnv::appendHtml($html);
        \AppEnv::finalize();
        exit;
    }

    public static function request() {
        self::init();
        # writeDebugInfo("my session: ", self::$userChatSession);
        $fromClient = file_get_contents('php://input');
        # writeDebugInfo("raw request: ", $fromClient);
        $input = @json_decode($fromClient, true);
        # writeDebugInfo("decoded: ", $input);
        $userMessage = $input['message'] ?? 'no text';

        $aiInstance = \libs\AiBus::init(self::$engine);
        if(!is_object($aiInstance)) exit("Error crearting wrapper object for ".self::$engine);

        $arHist = self::getChatChain();
        # TODO: сформировать цепочку контекста с предыдущими вопросами-ответами + текущий запрос
        $context = (empty($_SESSION['chatbot_context']) ? '' : self::getContext($_SESSION['chatbot_context']));
        $response = $aiInstance->request($userMessage, $arHist, $context); # will create echo and exits!
        # writeDebugInfo("response from LLM: ", $response);
        self::saveRequest($userMessage, $response);
        $jsonResponse = json_encode( ['reply'=>$response], JSON_UNESCAPED_UNICODE);
        # TODO: сохранить вопрос-ответ в истории
        exit($jsonResponse);
    }
    public static function resetHistory() {
        # TODO: сброс накопленного контекста, стартую новый чат
        unset($_SESSION['chat_user_session']);
        self::init();
        exit("1");
    }
    public static function getChatChain($userid=FALSE, $chatSessionId='') {
        if(!$userid) $userid = Appenv::getUserId();
        if(!$chatSessionId) $chatSessionId = self::$userChatSession;
        $data = \AppEnv::$db->select(self::T_CHATBOT_HIST, [
          'fields'=>'request,response',
          'where'=>['userid'=>$userid, 'chatsession_id'=>$chatSessionId],
          'orderby'=>'id'
        ]);
        return $data;
    }

    # сохраняю в истории чата выполненный запрос
    public static function saveRequest($request, $response) {
        $arData = [
          'userid' => AppEnv::getUserId(),
          'chatsession_id'=> self::$userChatSession,
          'request_time' =>'{now}',
          'request' => $request,
          'response' => $response,
        ];
        $result = \AppEnv::$db->insert(self::T_CHATBOT_HIST, $arData);
        # writeDebugInfo("add record result: ", $result, " sql-err:", \AppEnv::$db->sql_error(), " SQL:", \AppEnv::$db->getLastQuery());
        return $result;
    }
    # AJAX запрос на выбор/ввод нового контекста
    public static function activateContext() {
        $contextId = AppEnv::$_p['contextid'] ?? '';
        if($contextId === '_new_') {
            $ctName = \RusUtils::mb_trim( AppEnv::$_p['ctx_name'] ?? '');
            $ctxContent = \RusUtils::mb_trim( AppEnv::$_p['ctx_content'] ?? '');
            if(empty($ctName) ||empty($ctxContent)) exit('1' . \AjaxResponse::showError('Название и текст для контекста должны быть заполнены!'));
            $arData = ['userid' => AppEnv::getUserId(),
              'context_name' => $ctName,
              'content' => $ctxContent
            ];
            $newId = \AppEnv::$db->insert(self::T_CHATBOT_CONTEXTS, $arData);
            if($newId) {
                self::setActiveContext($newId,$ctName);
            }
            else exit('1' . AjaxResponse::showError('Ошибка при записи в БД'));
        }
        self::setActiveContext($contextId);
    }
    public static function setActiveContext($ctxId, $ctxName='') {
        if($ctxId > 0) {
            $_SESSION['chatbot_context'] = $ctxId;
            if(empty($ctxName)) $ctxName = self::getContextName($ctxId);
            exit('1'.AjaxResponse::setHtml("cur_context", "Контекст:$ctxName")); # .AjaxResponse::show("cur_context"));
        }
        else {
            unset($_SESSION['chatbot_context']);
            exit('1'.AjaxResponse::setHtml("cur_context", "Контекст не задан")); # .AjaxResponse::hide("cur_context"));
        }
    }
    # получить название контекста
    public static function getContextName($ctxId) {
        $ret = \AppEnv::$db->select(self::T_CHATBOT_CONTEXTS,
              ['fields'=>'context_name', 'where'=>['id'=>$ctxId],
               'singlerow'=>1, 'associative'=>0
              ]);
        return $ret;
    }

    # получить содержимое (текст) контекста
    public static function getContext($ctxId) {
        $ret = \AppEnv::$db->select(self::T_CHATBOT_CONTEXTS,
              ['fields'=>'content', 'where'=>['id'=>$ctxId],
               'singlerow'=>1, 'associative'=>0
              ]);
        return $ret;
    }
    # AJAX запрос на зачистку всей истории запросов
    public static function clearChatHistory() {
        if(SuperAdminMode()) {
            \AppEnv::$db->sql_query("truncate table ".self::T_CHATBOT_HIST);
            exit('1');
        }
        exit('1' . AjaxResponse::showError('Вам сюда нельзя!'));
    }
}

$action = AppEnv::$_p['action'] ?? 'form';
if(!empty($action)) {
    if(class_exists('ChatBot', $action)) ChatBot::$action();
    else exit("ERROR: No action $action in ChatBot");
}