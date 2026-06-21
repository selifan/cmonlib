<?php
/**
* @name plugins/chatbot/backend.php чат-бот backend
* (юзает aibus.php)
* modified 2026-06-21
*/
class ChatBotBackend extends appPlugins {
    static $engine = 'stub'; # deepseek | stub | openrouter | lmstudio | gigachat | routerairu
    static $botName = 'Чат-бот'; # что будет видно в заголовках ответов
    static $userChatSession = '';
    static $knownActions = [ 'initContextDialog','setActiveContext', 'activateContext' ];
    static $parseMermaid = 1; # подключать ли парсер mermaid диаграмм в MarkDown контенте
    static $mermaid_theme = 'default'; # default | neutral | dark | forest | base
    static $debug = 0;
    static $supportFiles = FALSE;

    public static function init() {
        self::$engine = AppEnv::getConfigValue('chatbot_active_config', 'stub');
        if(empty(self::$userChatSession) || empty($_SESSION['chat_user_session'])) {
            if(empty($_SESSION['chat_user_session']))
                $_SESSION['chat_user_session'] = 'chat-'.date('Y-m-d-H-i-s') . rand(100,999);
            self::$userChatSession = $_SESSION['chat_user_session'];
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
        $access = AppEnv::userHasRight(chatbot::RIGHT_OPER);
        $pgPrefix = 'Выберите один из настроенных контекстов' . ($access>=2 ? ' или создайте новый' : '');

        $html = "<div class='p-2'>$pgPrefix:<form id='fm_chat_setcontext' class='was-validated'>";

        # select from chatbot::T_CHATBOT_CONTEXTS
        $myid = \AppEnv::getUserId();
        $newClass = '';
        $curCtx = \AppEnv::$db->select(chatbot::T_CHATBOT_CONTEXTS, ['fields'=>'id,userid,context_name',
          'where' => "userid IN('__system__','$myid') OR public=1", 'orderby'=>'context_name']);
        if(is_array($curCtx) && count($curCtx)) {
            $fmt = "<div class='bordered p-2 m-2'><label><input type='radio' name='contextid' value='%s' onclick='chatBot.chgContext(this)'/> %s</label></div>";
            foreach($curCtx as $item) {
                $html .= sprintf($fmt, $item['id'], $item['context_name']);
            }
        }

        if($access >= 2) { # доавбляю возможность ввести свой контекст
            $newClass = 'hideme'; # блок ввода нового контекста изначально скрыт, т.к. есть готовые
            $html .= sprintf($fmt, '_new_', 'Создать новую настройку контекста');

            $html .= "<div class='$newClass' id='new_context'><div class='row'><div class='col-md-6'>Новый контекст - название:<br>"
              . '<input type="text" name="ctx_name" class="form-control" required/></div>'
              . '<div class="col-md-6"><label><br><input type="checkbox" name="ctx_public" checked="checked" value="1" /> Публичный (доступ для всех)</label></div></div>'
              . "Текст <br><div class='row'><textarea name='ctx_content' class='form-control w100prc' style='height:100px; overflow:auto; resize:none' required></textarea></div></div></form></div>";
        }

        # writeDebugInfo("htmlChoose: ", $html);
        exit($html);
    }
    # форма в браузере, основа взята здесь: https://imranonline.net/building-an-ai-chatbot-with-php-and-deepseek-a-step-by-step-guide/
    public static function form() {
        self::init();
        $pagetitle = "Чат-бот";
        \AppEnv::setPageTitle($pagetitle);
        UseJsModules('js/markdown-it.min.js');
        // UseJsModules('js/purify.min.js');

        UseJsModules('js/mermaid.min.js');
        UseJsModules('js/markdown-helper.js'); # Динамичкский парсер markdown + mermaid для ответов от ИИ

        $aiInstance = \plugins\chatbot\AiBus::init();
        if(is_object($aiInstance)) {
            self::$engine = $aiInstance->getEngineName();
            if(method_exists($aiInstance, 'getStartMessage'))
                $startMessage = $aiInstance->getStartMessage() . '<hr>';
            else $startMessage = self::defaultStartMessage();
        }
        else $startMessage = AppEnv::getLocalized('chatbot::error_engine') . ":". \plugins\chatbot\AiBus::getErrorMessage();
        $dopInfo = '';
        if(is_object($aiInstance) && method_exists($aiInstance, 'supportInputFiles'))
            self::$supportFiles = $aiInstance->supportInputFiles();
        else self::$supportFiles = FALSE;

        if(self::$supportFiles) {
            $outFiles = $_SESSION['chat_outfiles'] ?? [];
            $dopInfo = self::generateDopInfo($outFiles);
            useJsModules('simpleajaxuploader');
        }
        $botname = self::$botName;
        $engine = $aiInstance->getEngineName();  self::$engine;
        $btnContext = "<button class=\"btn btn-primary\" id=\"btn_context\" onclick=\"chatBot.selectContext()\">Задать контекст</button>";
        $curContextId = $_SESSION['chatbot_context'] ?? '';
        if($curContextId) {
            $strContext = "Контекст:" . self::getContextName($curContextId);
        }
        else $strContext = 'Контекст не задан';
        $additionCode = self::getAdditionCode();
        $jsAfterResponse = (self::$supportFiles) ? 'chatBot.refreshDopInfo()' : '';
        $ajaxUploadCode = '';
        if(self::$supportFiles) {
            $ajaxUploadCode = <<< EOJS
chatbotUploader = new ss.SimpleUpload({
  dropzone: 'chat_input_area',
  url: "./?plg=chatbot",
  name: 'cbfile',
  data: { 'action':'uploadUserFile' },
  allowedExtensions: ['pdf','docx','csv','jpg','png'],
  maxSize: 12000, // kilobytes
  multiple: true,
  onComplete: chatBot.uploadFileDone
});
EOJS;
        }


        $html = <<< EOHTM
$additionCode
<h1>$pagetitle ($engine) $btnContext</h1>
<div id="cur_context" class="bordered chat_context msg_ok">$strContext</div>
<div id="chat">
    <div id="messages" class="bordered p-2" style="min-height:100px; _max-height:800px; overflow:auto">$startMessage</div>
    <form id="chat-form"><div id="chat_input_area">Введите вопрос<br>
        <textarea id="user-input"  required="required" class="form-control" style="height:100px;overflow:auto"></textarea>
       </div>
       <br>
       <div class="bordered rounded p-2">
       <button type="submit" id="btn_request" class="btn btn-primary w200" m-2>Отправить запрос</button>
       <button type="button" id="btn_reset_chat" class="btn btn-primary w200 m-2" onclick="chatBot.clearChatHistory()" disabled="disabled">(новый чат)</button>
       <span class="alright" id="dop_info">$dopInfo</span>
       </div>
    </form>
</div>
<script>
chatBot = {
  backend: "./?plg=chatbot",
  answers: 0,
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
    // console.log("TODO: set context ", params);
    asJs.sendRequest(chatBot.backend,params, true);
  },
  uploadFileDone: function(file, response) {
    console.log("file:", file);
    console.log("response:", response);
    var splt = response.split("|");
    if(splt[0] !== 'OK') {
      showMessage("Ошибка !", splt[1],"msg_error");
      return;
    }
    $("#dop_info").html(splt[1]);
    if(splt[2]) asJs.timedNotification(splt[2],3);
  },
  clearFiles: function() {
    asJs.sendRequest(chatBot.backend,{"action":"clearFiles"}, true);
  },
  refreshDopInfo: function() {
    asJs.sendRequest(chatBot.backend,{"action":"refreshDopInfo"}, true);
  }
};
document.getElementById('chat-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    var userInput = $("#user-input").val();
    $("#btn_request").attr("disabled",true).html("Машина думает...");
    const messagesDiv = document.getElementById('messages');

    // Add user message to chat
    messagesDiv.innerHTML += '<p><strong>Вы:</strong><div class="chat-user-request">' + userInput + '</div>';

    // Send message to server
    var response = await fetch('./?plg=chatbot&action=request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: userInput })
    });
    // console.log(response);
    var data = await response.json();
    // var data = await response.body;
    // console.log("data:", data);
    var divchat = $('#messages');
    divchat.append("<b>Чат-бот</b>:");
    MermaidMarkdown.addMessage(divchat, data.reply, 'from-bot').then(function(msg){
      // console.log('Message added', msg);
      chatBot.answers++;
      $jsAfterResponse
    });

    $(window).scrollTop(32000);
    $("#btn_context").addClass("hideme");
    // Clear input
    // document.getElementById('user-input').value = '';
    $("#user-input").val('');
    $("#btn_request").attr("disabled",false).html("Отправить запрос");

    $("#btn_reset_chat").attr("disabled", false);
});
$ajaxUploadCode
</script>
EOHTM;
        \AppEnv::appendHtml($html);
        \AppEnv::finalize();
        exit;
    }

    public static function request() {
        self::init();
        writeDebugInfo("my session: ", self::$userChatSession);
        $fromClient = file_get_contents('php://input');
        if(self::$debug) writeDebugInfo("raw request: ", $fromClient);
        $input = @json_decode($fromClient, true);
        $userMessage = $input['message'] ?? '';
        if(self::$debug) writeDebugInfo("user message[$userMessage]: full unput: ", $input);

        if(empty($userMessage)) $predefined = 'Передан пустой запрос!';
        else $predefined = self::checkPredefinedReplies($userMessage);

        if(!empty($predefined)) {
            $jsonResponse = json_encode( ['reply'=>$predefined], (JSON_UNESCAPED_UNICODE));
            # writeDebugInfo("predefined response with saved CR LF: ", $jsonResponse);
            exit($jsonResponse);
        }
        $aiInstance = \plugins\chatbot\AiBus::init();
        if(self::$debug) writeDebugInfo("created AI instance: ", $aiInstance);
        if(!is_object($aiInstance)) {
            $err = \plugins\chatbot\AiBus::getErrorMEssage();
            $jsonResponse = json_encode( ['reply'=>$err], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            exit($jsonResponse);
        }
        if(!is_object($aiInstance)) {
            if(self::$debug) writeDebugInfo("Ошибка создания AI объекта ", self::$engine);
            $err = "Error crearting wrapper object for ".self::$engine;
            $jsonResponse = json_encode( ['reply'=>$err], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            exit($jsonResponse);
        }

        $request = \RusUtils::mb_trim($userMessage);
        $parts = preg_split("/[ ,]+/", $request, -1, PREG_SPLIT_NO_EMPTY);

        $command = array_shift($parts);

        if($command === '@models') {
            if(method_exists($aiInstance, 'modelList')) {
                $result = $aiInstance->modelList($parts);
                if(is_array($result)) $result = 'Models: <pre>'.print_r($result,1).'</pre>';
                $jsonResponse = json_encode( ['reply'=>$result], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            else {
                $jsonResponse = json_encode( ['reply'=>"Получение списка моделей не поддерживается!"], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            exit($jsonResponse);
        }

        # $tmp =(__FILE__ . '/'.__LINE__." $command<pre>" . print_r($parts,1) . '</pre>');
        # exit (json_encode( ['reply'=>$tmp], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) );

        $arHist = self::getChatChain();
        #  формирую цепочку контекста = стартовый контекст + предыдущие вопросы-ответы + текущий запрос
        $context = (empty($_SESSION['chatbot_context']) ? '' : self::getContext($_SESSION['chatbot_context']));
        $response = $aiInstance->request($userMessage, $arHist, $context); # will create echo and exits!
        # writeDebugInfo("response from LLM: ", $response);
        self::saveRequest($userMessage, $response);
        $jsonResponse = json_encode( ['reply'=>$response], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        exit($jsonResponse);
    }

    public static function checkPredefinedReplies($request) {
        $ret = FALSE;
        if($request === '@lastresponse') {
            # повторяю прошлый ответ без запросов в AI агента
            $chatSession = $_SESSION['chat_user_session'] ?? '';
            if(!empty($chatSession)) $ret = 'Сессия не стартована';
            else {
                $ret = self::getLastResponse();
            }
        }
        elseif(substr($request,0,5) === '@test') {
            $testBody = 'chat-test';
            $postfix = substr($request,6);
            if($postfix) $testBody .= $postfix;
            $testResponse = __DIR__ .'/aiengines/testpages/' . "test{$postfix}.md";
            if(is_file($testResponse)) $ret = file_get_contents($testResponse);
            else $ret = "Тестовая страница $testResponse не найдена";
        }
        if(self::$debug) writeDebugInfo("checkPredefinedReplies returns: [$ret]");
        return $ret;
    }

    public static function getLastResponse() {
        $chatSession = $_SESSION['chat_user_session'] ?? '';
        if(empty($chatSession)) return 'Чат-сессия не стартована! сессия:<br>'.print_r($_SESSION);
        $arData = \AppEnv::$db->select(chatbot::T_CHATBOT_HIST,
          ['where'=>['chatsession_id'=>$chatSession], 'orderby'=>'id desc','singlerow'=>1]
        );
        return $arData['response'] ?? 'Ответов в сессии <b>$chatSession</b> еще не сохранено!';
    }
    public static function resetHistory() {
        # TODO: сброс накопленного контекста, стартую новый чат
        unset($_SESSION['chat_user_session']);
        unset($_SESSION['chat_conversation_id']); # ID сессии на стороне AI агента (stateful режим)
        self::init();
        exit("1");
    }
    public static function getChatChain($userid=FALSE, $chatSessionId='') {
        if(!$userid) $userid = Appenv::getUserId();
        if(!$chatSessionId) $chatSessionId = self::$userChatSession;
        $data = \AppEnv::$db->select(chatbot::T_CHATBOT_HIST, [
          'fields'=>'request,response',
          'where'=>['userid'=>$userid, 'chatsession_id'=>$chatSessionId],
          'orderby'=>'id'
        ]);
        return $data;
    }

    # сохраняю в истории чата выполненный запрос
    public static function saveRequest($request, $response) {
        if($response === '{no-answer}') return 0;
        if(mb_substr($response, 0,8,'UTF-8')==='{ERROR}:') return 0;
        $arData = [
          'userid' => AppEnv::getUserId(),
          'chatsession_id'=> self::$userChatSession,
          'request_time' =>'{now}',
          'request' => $request,
          'response' => $response,
        ];
        $result = \AppEnv::$db->insert(chatbot::T_CHATBOT_HIST, $arData);
        if($dqlErr=\AppEnv::$db->sql_error()) {
            $dttime = date('Ymd-His');
            @file_put_contents("tmp/_response-saveerr-$dttime.log", $response);
            writeDebugInfo("save request error : ", $result, " sql-err:", \AppEnv::$db->sql_error(), "\n SQL:", \AppEnv::$db->getLastQuery());
        }
        return $result;
    }
    # AJAX запрос на выбор/ввод нового контекста
    public static function activateContext() {
        writeDebugInfo("activateContext params: ", \AppEnv::$_p);
        $contextId = AppEnv::$_p['contextid'] ?? '';
        if($contextId === '_new_') {
            $ctName = \RusUtils::mb_trim( \AppEnv::$_p['ctx_name'] ?? '');
            $ctxContent = \RusUtils::mb_trim( \AppEnv::$_p['ctx_content'] ?? '');
            $ctxPublic = \AppEnv::$_p['ctx_public'] ?? 0;
            if(empty($ctName) ||empty($ctxContent)) exit('1' . \AjaxResponse::showError('Название и текст для контекста должны быть заполнены!'));
            $arData = ['userid' => AppEnv::getUserId(),
              'context_name' => $ctName,
              'content' => $ctxContent,
              'public' => $ctxPublic
            ];
            $newId = \AppEnv::$db->insert(chatbot::T_CHATBOT_CONTEXTS, $arData);
            if($newId) {
                self::setActiveContext($newId,$ctName);
            }
            else exit('1' . AjaxResponse::showError('Ошибка при записи в БД'));
        }
        self::setActiveContext($contextId);
    }
    public static function setActiveContext($ctxId, $ctxName='') {
        # writeDebugInfo("ctxId: [$ctxId]");
        if($ctxId > 0) {
            $_SESSION['chatbot_context'] = $ctxId;
            if(empty($ctxName)) $ctxName = self::getContextName($ctxId);
            exit('1'. AjaxResponse::setHtml("cur_context", "Контекст:$ctxName")); # .AjaxResponse::show("cur_context"));
        }
        else {
            unset($_SESSION['chatbot_context']);
            exit('1'. AjaxResponse::setHtml("cur_context", "Контекст не задан")); # .AjaxResponse::hide("cur_context"));
        }
    }
    # получить название контекста
    public static function getContextName($ctxId) {
        $ret = \AppEnv::$db->select(chatbot::T_CHATBOT_CONTEXTS,
              ['fields'=>'context_name', 'where'=>['id'=>$ctxId],
               'singlerow'=>1, 'associative'=>0
              ]);
        return $ret;
    }

    # получить содержимое (текст) контекста
    public static function getContext($ctxId) {
        $ret = \AppEnv::$db->select(chatbot::T_CHATBOT_CONTEXTS,
              ['fields'=>'content', 'where'=>['id'=>$ctxId],
               'singlerow'=>1, 'associative'=>0
              ]);
        return $ret;
    }
    # AJAX запрос на зачистку всей истории запросов
    public static function clearChatHistory() {
        if(SuperAdminMode()) {
            \AppEnv::$db->sql_query("truncate table ".chatbot::T_CHATBOT_HIST);
            exit('1');
        }
        exit('1' . AjaxResponse::showError('Вам сюда нельзя!'));
    }

    # из браузера клиента прилетел загруженный файл
    public static function uploadUserFile() {
        $file = $_FILES['cbfile'] ?? [];
        if(empty($file['tmp_name'])) {
            exit("ERR|ошибка - файл не загрузился!");
        }
        if(!empty($file['error'])) {
            exit("ERR|ошибка - " . $file['error']);
        }
        writeDebugInfo("загружен файл ", $file);
        $outFiles = $_SESSION['chat_outfiles'] ?? [];
        $flType = $file['type']; # TODO: для картинок делать авто-даунгрейд до лимитов ИИ-сервера
        $outFiles[] = [
            'name' => $file['name'],
            'type' => $file['type'],
            'filepath' => $file['tmp_name'],
        ];
        $cnt = count($outFiles);
        $_SESSION['chat_outfiles'] = $outFiles;
        exit("OK|" . self::generateDopInfo($outFiles) . "|Файл $file[name] будет отправлен со следующим запросом");
    }
    public static function clearFiles() {
        \plugins\chatbot\AiBus::clearOutputFiles();
        $endResponse = self::generateDopInfo();
        exit('1' .$response . \AjaxResponse::setHtml('dop_info', $endResponse));
    }
    public static function defaultStartMessage() {
        return '@models - получить список поддерживаемых моделей';
    }

    public static function generateDopInfo($arFiles=NULL) {

        $aiInstance = \plugins\chatbot\AiBus::init();
        if(is_object($aiInstance) && method_exists($aiInstance, 'supportInputFiles'))
            self::$supportFiles = $aiInstance->supportInputFiles();
        else self::$supportFiles = FALSE;
        if(self::$supportFiles) {
            if($arFiles ===  NULL) $arFiles = $_SESSION['chat_outfiles'] ?? FALSE;
            if(is_array($arFiles) && count($arFiles))
                $ret = "Для отправки загружен(ы) файл(ы): " .count($arFiles)
                . ' <input type="button" class="btn btn-primary" value="Удалить" onclick="chatBot.clearFiles()"/>';

            else $ret = 'Для отправки файла в бот перетащите его на окно ввода запроса!';
        }
        else $ret = '';
        return $ret;
    }
    public static function refreshDopInfo() {
        $aiInstance = \plugins\chatbot\AiBus::init(self::$engine);
        if(method_exists($aiInstance, 'supportInputFiles'))
            self::$supportFiles = $aiInstance->supportInputFiles();
        else self::$supportFiles = FALSE;
        if(self::$supportFiles) $dopInfo = self::generateDopInfo();
        else $dopInfo = '';
        exit('1' . \AjaxResponse::setHtml('dop_info', $dopInfo));
    }
    # код для страницы чат-бота, с подключением парсеров markdown, mermaid (Маша-GPT дала...)
    public static function getAdditionCode() {
        $theme = self::$mermaid_theme;
        $initMermaid = (self::$parseMermaid) ? "MermaidMarkdown.init({ mermaidConfig: { theme: '$theme' } });" : 'mermaid=false;';
        $code = <<< EOJS
<!-- script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs" /script -->
<style>
      /* минимальные стили */
      .mermaid-placeholder { background:#f8f8f8; padding:8px; border-radius:4px; }
      .chat_context { position:fixed; z-index:50000; top:10px; left:20px; width:auto }
      pre { padding:8px; border-radius:4px; overflow:auto; }
      div.from-bot { background-color: #fafafa; border: 1px solid #aaa; padding: 1em; border-radius: 6px; margin-bottom:6px;}
      div.chat-user-request { background-color: #f9f9f9; border: 1px solid #aaa; padding: 1em; border-radius: 6px; margin-bottom:6px;}
      td,th { border:1px solid #a0a0a0; padding: 0.2em 1em;}
      th { background-color: #eee; }
</style>
<script>
// Инициализация
$(document).ready(function() {
  MermaidMarkdown.init({ mermaidConfig: { theme: 'default' } });
});
</script>
EOJS;
        # file_put_contents('tmp/_mermaid-code.htm', $code); # to check correct $ char escapings
        return $code;
    }

    # методы из класса McpAssistant

    # вызов формы "конструктора" mcpAssistant
    public function mcpAssistant() {
        plugins\chatbot\mcpassistant::form();
    }
    # сохранение текукщей настройки mcp генерации
    public function McpSaveConfig() {
        plugins\chatbot\mcpassistant::saveConfig();
    }
    # выполнить генерацию файла для MCP
    public function McpGenerate() {
        plugins\chatbot\mcpassistant::performGenerate();
    }
}
$thisPage = AppEnv::$_p['p'] ?? '';
if($thisPage === 'chatbot') {
    $action = AppEnv::$_p['action'] ?? 'form';
    if(!empty($action)) {
        if(class_exists('ChatBot', $action)) ChatBot::$action();
        else exit("ERROR: No action $action in ChatBot");
    }
}