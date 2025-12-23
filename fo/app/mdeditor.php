<?php
/**
* страница онлайн-редактора MarkDown текста с рендером (MarkDown разметка, Mermaid диаграммы)
* modified 2025-12-21
*/
class MdEditor {
    static $mermaid_theme = 'default'; # default | neutral | dark | forest | base
    static $debug = 0;

    public static function init() {
    }
    # форма в браузере, основа взята здесь: https://imranonline.net/building-an-ai-chatbot-with-php-and-deepseek-a-step-by-step-guide/
    public static function form() {
        self::init();
        $pagetitle = "Редактор Markdown";
        \AppEnv::setPageTitle($pagetitle);
        UseJsModules('js/markdown-it.min.js');
        UseJsModules('js/mermaid.min.js');
        UseJsModules('js/markdown-helper.js'); # Динамический парсер markdown + mermaid

        $html = <<< EOHTM
<style>
      /* минимальные стили */
      .mermaid-placeholder { background:#f8f8f8; padding:8px; border-radius:4px; }
      pre { padding:8px; border-radius:4px; overflow:auto; }
      div.from-bot { background-color: #fafafa; border: 1px solid #aaa; padding: 1em; border-radius: 6px; margin-bottom:6px;}
      div.chat-user-request { background-color: #f9f9f9; border: 1px solid #aaa; padding: 1em; border-radius: 6px; margin-bottom:6px;}

      td,th { border:1px solid #a0a0a0; padding: 0.2em 1em;}
      th { background-color: #eee; }
</style>

<h1>$pagetitle </h1>

<a href="https://docs.mermaidchart.com/" target="_blank">Mermaid</a> &nbsp; / &nbsp;
<a href="https://habr.com/ru/articles/652867/" target="_blank">Mermaid диаграммы в Markdown</a> &nbsp; / &nbsp;
<a href="https://gist.github.com/mbaron/1d79fd3cc4de4070f6895264f01b19a1" target=_blank">Introduction to Mermaid Markdown</a>

<div id="mdeditor" class="row">
    <div id="edtarea" class="p-2 col-md-3 col-12" style="min-height:420px; min-width:300px">
      <textarea id="md_edited" class="form-control" style="height:360px; width:100%">Введите код здесь</textarea>
      <br><input type="button" class="btn btn-primary w200" value="Рендер!" onclick="mdEdit.render()" />
    </div>
    <div id="md_result" class="bordered p-2 m-2 col-md-8 col-lg-8 col-12" style="min-height:400px; overflow:auto">для отображения результата</div>
</div>
<script>
mdEdit = {
  backend: "./?p=mdeditor",
  done: 0,
  render: function() {
    var userInput = $("#md_edited").val();
    var divchat = $('#md_result');
    divchat.html("");
    if(userInput === "") return;

    MermaidMarkdown.addMessage(divchat, userInput, 'from-bot').then(function(msg){
      // console.log('marrdown parsed', msg);
      mdEdit.done++;
    });
  },
};
// Инициализация
$(document).ready(function() {
  MermaidMarkdown.init({ mermaidConfig: { theme: 'default' } });
});
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
        if(self::$debug) writeDebugInfo("raw request: ", $fromClient);
        $input = @json_decode($fromClient, true);
        $userMessage = $input['message'] ?? '';
        if(self::$debug) writeDebugInfo("user message[$userMessage]: full unput: ", $input);

        if(empty($userMessage)) $predefined = 'Передан пустой запрос!';
        else $predefined = self::checkPredefinedReplies($userMessage);

        if(!empty($predefined)) {
            $jsonResponse = json_encode( ['reply'=>$predefined], (JSON_UNESCAPED_UNICODE));
            # $jsonResponse = json_encode( ['reply'=>$predefined], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            # $jsonResponse = strtr($jsonResponse, ['\\r'=>'\\\\r', '\\n'=>'\\\\n']);
            # $jsonResponse = strtr($jsonResponse, ['\\r'=>"\r", "\\n"=>"\n"]);
            writeDebugInfo("predefined response with saved CR LF: ", $jsonResponse);
            exit($jsonResponse);
        }
        $aiInstance = \libs\AiBus::init(self::$engine);
        if(self::$debug) writeDebugInfo("created AI instance: ", $aiInstance);
        if(!is_object($aiInstance)) {
            $err = \libs\AiBus::getErrorMEssage();
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
            $result = $aiInstance->modelList($parts);
            if(is_array($result)) $result = 'Models: <pre>'.print_r($result,1).'</pre>';
            $jsonResponse = json_encode( ['reply'=>$result], (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
            $testResponse = AppEnv::getAppFolder('libs/aiengines/testpages/') . "test{$postfix}.md";
            if(is_file($testResponse)) $ret = file_get_contents($testResponse);
            else $ret = "Тестовая страница $testResponse не найдена";
        }
        if(self::$debug) writeDebugInfo("checkPredefinedReplies returns: [$ret]");
        return $ret;
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
        if($response === '{no-answer}') return 0;
        if(mb_substr($response, 0,8,'UTF-8')==='{ERROR}:') return 0;
        $arData = [
          'userid' => AppEnv::getUserId(),
          'chatsession_id'=> self::$userChatSession,
          'request_time' =>'{now}',
          'request' => $request,
          'response' => $response,
        ];
        $result = \AppEnv::$db->insert(self::T_CHATBOT_HIST, $arData);
        if($dqlErr=\AppEnv::$db->sql_error()) {
            $dttime = date('Ymd-His');
            @file_put_contents("tmp/_response-saveerr-$dttime.log", $response);
            writeDebugInfo("save request error : ", $result, " sql-err:", \AppEnv::$db->sql_error(), "\n SQL:", \AppEnv::$db->getLastQuery());
        }
        return $result;
    }
    # AJAX запрос на выбор/ввод нового контекста
    public static function activateContext() {

        $contextId = AppEnv::$_p['contextid'] ?? '';
        if($contextId === '_new_') {
            $ctName = \RusUtils::mb_trim( \AppEnv::$_p['ctx_name'] ?? '');
            $ctxContent = \RusUtils::mb_trim( \AppEnv::$_p['ctx_content'] ?? '');
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
        writeDebugInfo("ctxId: [$ctxId]");
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

$thisPage = AppEnv::$_p['p'] ?? '';
if($thisPage === 'mdeditor') {
    $action = AppEnv::$_p['action'] ?? 'form';
    if(!empty($action)) {
        if(class_exists('MdEditor', $action)) MdEditor::$action();
        else exit("ERROR: No action $action in MdEditor");
    }
}