<?php
/**
* @name libs/openai.php
* реализация протокола OpenAI работы с LLM
* @version 0.10.001
* DeekSeek API docs по-русски: https://b.deepseek3.ru/api/
* Точки входа для некоторых LLM
* Deepseek: https://api.deepseek.com/v3
* OpenRouter: https://openrouter.ai/api/v1/chat/completions
* modified 2026-06-21 / created 2025-11-18
**/
# namespace Libs\aiengines;
namespace plugins\chatbot\aiengines;

class OpenAI {
    const METHOD_COMPLETIONS  = 'chat/completions';
    const METHOD_RESPONSES = 'responses';

    private $debug = 1;
    static $saveAllResponses = 1;
    static $maxProcessSeconds = 300; # timeout для curl (секунды)
    private $providerName = '';
    private $engineId = '';
    private $apiKey = '';
    private $baseUrl = '';
    private $Temperature = 0.7;
    private $inputFiles = FALSE; # ['image','doc']; # поддержка картинок, дркументов в отправке запроса

    private $maxTokens = 1024;
    private $model = '';
    private $context = '';
    private $config = [];
    private $errorMessage = '';
    private $uploadedFiles = [];
    private $uploadedDocs = []; # pdf, csv, xlsx для RAG и прочих векторых забав
    # private $uploadedSounds = []; # TODO: конда-нибудь попробуем поработать со звуком
    # private $uploadedVideos = []; # и может даже с видосами
    private $chatMode = 'responses'; # в какой движок делаем чат-запросы: completions | responses
    // FALSE|TRUE: отправлять ли в chat историю ответов (FALSE = слать только вопросы)
    // если число NN - это ограничитель: если история сообщений не более NN, отправлять с ответами, если больше - только вопросы
    private $sendResponseHist = 10;
    private $headers = [];
    static $textLimit = 700; # ограничитель текстовых значений в колонках models (description например)

    public function __construct($config=FALSE) {
        if(!empty($config)) $this->loadConfig($config);
    }
    public function makeHeaders() {
        if(!empty($this->config['OauthUrl'])) {
            $apiKey = $this->getApiKey();
            if(empty($apiKey)) {
                writeDebugInfo("no apiToken after OAuth request, ", $apiKey);
                exit("Oauth return - no apiToken !");
            }
        }
        else $apiKey = $this->apiKey;

        $this->headers = [];

        if(!empty($this->apiKey)) {
            $this->headers['Authorization'] = 'Bearer ' . $apiKey;
        }
        elseif(!empty($this->config['authorization'])) { # псевдо-авторизация с ИД сессии чата
            $authKey = $this->config['authorization']; #
            if(empty($_SESSION['chat_conversation_id'])) {
                # генерю свой ИД и толкаю его в auth header:
                $_SESSION['chat_conversation_id'] = 'chat-' . self::generateGUID();
            }
            # if(!empty($_SESSION['chat_conversation_id'])) {
                if($authKey === 'conversation_id')
                    $this->headers['Authorization'] = $authKey . ':' . $_SESSION['chat_conversation_id'];
                elseif($authKey === 'Conversation-Id: <id>')
                    $this->headers['Conversation-Id'] = $_SESSION['chat_conversation_id'];
            # }
        }
        $this->headers['Content-Type'] = 'application/json';
        if($this->debug) writeDebugInfo("http headers: ", $this->headers);
    }

    public function loadConfig($cfg='') {
        $arCfg = [];
        if(is_string($cfg)) $this->engineId = $cfg;
        if($this->debug) writeDebugInfo("loadConfig($cfg)...");
        if(is_string($cfg)) {
            if(strpos($cfg, '.json')===FALSE) # передано только имя настройки
                $cfgFile = __DIR__ . "/configs/openai-$cfg.json";
            else
                $cfgFile = $cfg;

            if($this->debug>1) writeDebugInfo("cfgFile: [$cfgFile]");

            if(!is_file($cfgFile)) {
                if($this->debug) writeDebugInfo("Exiting with No cfgFile");
                $this->errorMessage = "No cfgFile for $cfg";
                return FALSE;
            }
            $cfgBody = @file_get_contents($cfgFile);
            # writeDebugInfo("KT54 cfgbody: $cfgBody");
            $this->config = json_decode($cfgBody, TRUE);
            writeDebugInfo("KT55 config from $cfgFile:  ", $this->config);
        }
        elseif(is_array($cfg))
            $this->config = $cfg;
        if($this->debug>1) writeDebugInfo("confgi passed: ", $this->config);
        if(isset($this->config['name'])) $this->providerName = $this->config['name'];
        if(isset($this->config['apiKey'])) {
            $this->apiKey = $this->config['apiKey'];
            if(substr($this->apiKey,0,1) === '@')
                $this->apiKey = $this->loadApiKey($this->apiKey);

            if($this->debug) writeDebugInfo("set apiKey to ", $this->apiKey);
        }
        if(isset($this->config['baseUrl'])) {
            $this->baseUrl = $this->config['baseUrl'];
            if($this->debug) writeDebugInfo("set baseUrl to ", $this->config['baseUrl']);
        }
        if(isset($this->config['Temperature'])) $this->Temperature = $this->config['Temperature'];
        if(!empty($this->config['chatMode'])) $this->chatMode = $this->config['chatMode']; # chat/completions | responses
        # есть поддержка обработки файлов в движке/модели?
        if(!empty($this->config['inputFiles'])) {
            $this->inputFiles = is_string($this->config['inputFiles']) ?
              explode(',', $this->config['inputFiles'])
              : $this->config['inputFiles'];
        }

        if(isset($this->config['Model'])) $this->model = $this->config['Model'];
        if(isset($this->config['maxTokens'])) $this->maxTokens = $this->config['maxTokens'];
    }
    public function getStartMessage() {
        return '<b>@models</b> - получить список поддерживаемых моделей; <b>@models free</b> - показать только бесплатные модели';
    }
    # получаю apiKey из файла коллекции всех API ключей по его ИД
    public function loadApiKey($apiKey) {
        $apiKeysFile = realpath( __DIR__ . '/../configs/apiKeys.json');
        $ret = $apiKey;
        $keyId = (substr($apiKey,0,1) === '@') ? substr($apiKey,1) : $apiKey;

        if(is_file($apiKeysFile)) {
            $jsonData = file_get_contents($apiKeysFile);
            $arKeys = json_decode($jsonData,TRUE);
            writeDebugInfo("all keys:, ", $arKeys);
            $ret = $arKeys[$keyId] ?? 'not-found';
        }
        else writeDebugInfo("ERRKEY: no file, $apiKeysFile");
        writeDebugInfo("key for $keyId: $ret");
        return $ret;
    }
    public function setContext($context = '') {
        $this->context = $context;
        return $this;
    }
    public function supportInputFiles() {
        return $this->inputFiles;
    }
    public function getEngineName() {
        writeDebugInfo("config, ", $this->config);
        return $this->config['description'] ?? $this->config['name'];
    }
    # при наличии настройки OauthUrl перед любым запроосом надо авторизоваться по OAuth и получить ключ!
    public function getApiKey() {
        $headers = [
          'Content-Type' => 'application/x-www-form-urlencoded',
          'Accept' => 'application/json',
          'Authorization' => 'Basic '.$this->config['apiKey'],
          'RqUID' => self::generateGUID(),
        ];

        $response = \Curla::getFromUrl($this->config['OauthUrl'],FALSE,20,$headers);
        $arResponse = @json_decode($response, TRUE);
        # writeDebugInfo("Oauth response: ", $response, " as array: ", $arResponse);
        $ret = $arResponse['access_token'] ?? FALSE;
        return $ret;
    }
    # простой GUID, есть ещё appLists::generateGUID($dopString)
    public static function generateGUID() {
        $ret = bin2hex(random_bytes(4))
          . '-' . bin2hex(random_bytes(2))
          . '-' . bin2hex(random_bytes(2))
          . '-' . bin2hex(random_bytes(2))
          . '-' . bin2hex(random_bytes(6))
          ;     # 85995b73-a3bc-7bff-d2b2-7b367befe771
        return $ret;
    }

    # формирую основные параметры запроса в чат-движок
    public function preparePostFields($request, $arHist = [], $context = '') {

        # $this->uploadedFiles брать из $_SESSION (список подгруженных файлов)
        if(isset($_SESSION['chat_outfiles']) && is_array($_SESSION['chat_outfiles']))
            $this->uploadedFiles = $_SESSION['chat_outfiles'];

        $postFields = [];
        $effortResoning = FALSE;
        $messages = [];
        if(!$context) $context = $this->context;

        if($this->chatMode === self::METHOD_COMPLETIONS) {
            # готовлю параметры под chat/completions
            if(!empty($this->model))
                $postFields['model'] = $this->model;

            if(!empty($context)) # задаю стартовый контекст/роль ассистента
                $messages[] = [ 'role'=>'system', 'content' => $context ];

            $this->addPreviousMessages($messages, $arHist);
            # заношу историю вопросов-ответов для создания контекста к данному вопросу
            # $messages[] = [ 'role'=>'user', 'content' => $request ];

            $postFields = [
                # 'messages' => $messages,
                'stream' => false,
                'temperature' => $this->Temperature,
                'max_tokens' => $this->maxTokens,
                # 'presence_penalty' => 0,
                # 'response_format' => ['type'=>'text'],
            ];
        }
        elseif($this->chatMode === self::METHOD_RESPONSES) {
            # writeDebugInfo("METHOD_RESPONSES... ");
            if(!empty($this->model))
                $postFields['model'] = $this->model;

            if(!empty($context)) # instructions: "Talk like a pirate.",
                $postFields['instructions'] = $context;

            if($effortResoning)
                $postFields['reasoning'] = [ 'effort'=> $effortResoning ]; // "low"

            if(!empty($this->maxTokens))
                $postFields['max_output_tokens'] = $this->maxTokens;

            if(!empty($this->config['stateful'])) {
                $convid_field = (string) ($this->config['user_convid'] ?? '');
                if(!empty($convid_field) && !empty($_SESSION['chat_conversation_id'])) {
                    $postFields[$convid_field] = $_SESSION['chat_conversation_id'];
                    # writeDebugInfo("передаю ИД сесии в $convid_field: ", $_SESSION['chat_conversation_id']);
                }
            }
            else { #  stateless, # надо отправлять прошлые ответы как в completions
                $this->addPreviousMessages($messages, $arHist);
                # writeDebugInfo("METHOD_RESPONSES, заношу прошлые сообщ-я confg: ", $this->config);
            }
        }

        $content = [];
        if(is_array($this->uploadedFiles) && count($this->uploadedFiles)) {
            $content [] = ['type'=>'text', 'text'=> $request];
            # кодирую загруженные картинки в тело запроса
            foreach($this->uploadedFiles as $oneFile) {
                $imgPath = $oneFile['filepath'] ?? $oneFile['path'] ?? '';
                $fileType = $oneFile['type'] ?? '';
                if(!is_file($imgPath) || empty($fileType)) continue;
                $partsFT = explode('/',$fileType);
                switch($fileType) {
                    case 'image/png':
                    case 'image/jpg':
                        $ftype = 'image_url';
                        break;
                    case 'application/pdf': # pdf, xls, csv?
                    case 'application/xls':
                    case 'application/csv':
                    default:
                        $ftype = 'file_url'; # Проверить!
                        break;
                }
                $content[] = ['type' => $ftype,
                  $ftype => [
                    'url' => [ "data:$fileType,".base64_encode(file_get_contents($imgPath)) ]
                  ]
                ];
            }
            $messages[] = [ 'role'=>'user', 'content' => $content ];
        }
        else {
            $messages[] = [ 'role'=>'user', 'content' => $request ]; # если ни картинок/файлов, никакой мульти-модальности
        }


        $postFields['input'] = $messages;

        return $postFields;
    }
    public function addPreviousMessages(&$messages, $arHist) {
        if(count($arHist) && count($arHist)) foreach($arHist as $item) {
            if(!empty($item['request']))
                $messages[] = [ 'role'=>'user', 'content' => $item['request'] ];

            if(!empty($item['response'])) {
                if($this->sendResponseHist === TRUE || count($arHist)<=(int)$this->sendResponseHist)
                    $messages[] = [ 'role'=>'assistant', 'content' => $item['response'] ];
            }
        }
    }
    public function request($request, $arHist = [], $context = '') {

        if(empty($request)) return 'Empty request string!';
        if(!empty($context)) $this->context = $context;
        $this->makeHeaders();
        $postFields = $this->preparePostFields($request, $arHist, $context);

        try {
            $chatUrl = $this->buildChatUrl();
            if($this->debug) {
                writeDebugInfo("calling $chatUrl headers: ", $this->headers);
            }
            $response = \Curla::getFromUrl($chatUrl,$postFields,self::$maxProcessSeconds,$this->headers, TRUE);
            $errCode = \Curla::getErrNo();
            $responseData = @json_decode($response, TRUE);
            if($this->debug || self::$saveAllResponses) {
                writeDebugInfo("response from AI / ", $response, "\n as array: ", $responseData);
            }
            if(isset($responseData['error']) || $errCode) {
                $this->errorMessage = $responseData['error']['message'] ?? "curl:$errCode";
                $result = '{ERROR}: ' . $this->errorMessage;
            }
            else {
                if($this->chatMode == self::METHOD_COMPLETIONS)
                    $result = $responseData['choices'][0]['message']['content'] ?? ('{no-answer},output: <pre>'.print_r($responseData['output'],1).'</pre>');
                else {
                    $outType = $responseData['output'][0]['content'][0]['type'] ?? 'undefined';
                    $result = $responseData['output'][0]['content'][0]['text'] ?? ('{no-answer}, output: <pre>'.print_r($responseData['output'],1).'</pre>');
                }
                if(!empty($this->config['stateful'])) {
                    $server_convid = $this->config['server_convid'] ?? 'id';
                    if(!empty($responseData[$server_convid])) {
                        $_SESSION['chat_conversation_id'] = $responseData[$server_convid];
                        # writeDebugInfo("saved conv session id($server_convid): ", $responseData[$server_convid]);
                    }
                }
                if(isset($_SESSION['chat_outfiles']) && is_array($_SESSION['chat_outfiles'])) {
                    $this->uploadedFiles = $_SESSION['chat_outfiles'];
                }


            }
        }
        catch(Exception $e) { $result = $e->getMessage(); }
        return $result;
    }
    public function buildChatUrl() {
        $ret = $this->baseUrl . $this->chatMode;
        return $ret;
    }
    /**
    * вернет список моделей, доступных для использования
    * Формат Markdown !
    */
    public function modelList($params = FALSE) {

        $startPos = $onlyFree = $filter = FALSE;
        $limit = 40;
        if(is_array($params)) {
            while(count($params)) {
                $itemParam = array_shift($params);
                if(is_numeric($itemParam)) $startPos = intval($itemParam);
                elseif(strtolower($itemParam) === 'free') $onlyFree = TRUE;
                else $filter = $itemParam;
            }
        }
        else {
            if(is_numeric($params)) $startPos = intval($params);
            elseif($params === 'free') $onlyFree = TRUE;
            else $filetr = $params;
        }
        # return "Запрошен список моделей с пропуском " . (string)($startPos);

        # Включать только эти колонки:
        $colNames = ['id','name', 'owned_by', 'context_length','description'];
        $this->makeHeaders();
        $fmt = 'md';
        if(!empty($this->config['modelsUrl']))
            $url = $this->config['modelsUrl'];

        else {
            $url = $this->baseUrl . 'models';
        }
        if($this->debug) writeDebugInfo("for models list, call url: $url");
        try {
            $response = \Curla::getFromUrl($url,FALSE,30,$this->headers,2);
            if($this->debug) writeDebugInfo("/models request response: ", $response);
            if(is_array($response)) $responseData = $response;
            else $responseData = @json_decode($response, true);
            if(!empty($responseData['error'])) $result = $responseData['errorMessage'] ?? $responseData['error'];
            elseif(!empty($responseData['status']) && $responseData['status']!='200') {
                $result = "Service connect error: " . ($responseData['message'] ?? $responseData['status']);
            }
            else $result = $responseData['data'] ?? $responseData;
            if($this->debug>1) writeDebugInfo("array with models: ",$result);
        }
        catch(Exception $e) {
            $result = $e->getMessage();
            return "ERROR: $result";
        }
        # writeDebugInfo("result: ", $result);
        $endWarning = '';
        if(is_array($result) && count($result)) { # Превращаю массив - список моделей в MarkDown
            if(count($result) <= $startPos)
                $txtOut = "Список пуст или запрошенный пропуск $startPos превышает число элементов в списке";
            else {
                $goodCols = array_intersect($colNames, array_keys($result[0]));
                $cntCols = count($goodCols);

                $txtOut = "## Список поддерживаемых моделей\r\n|No|" . implode(" | ", $goodCols) . "|\r\n";
                $txtOut .= "|" . str_repeat("---|", $cntCols+1) . "\r\n";
                $count = count($result);
                $done = 0;
                foreach($result as $no => $item) {
                    if($onlyFree) {
                        if($onlyFree && stripos($item['id'], 'free')===FALSE) continue;
                    }
                    if( !empty($filter) && stripos($item['id'], $filter)===FALSE ) continue;

                    if($no < $startPos) continue;
                    $done++;
                    $txtOut .= "| ". ($no+1) . " |";
                    # $txtOut .= "| $done |";

                    foreach($goodCols as $colid) {
                        $value = strtr($item[$colid], ["\r"=>'',"\n"=>' ', '|'=>' ']);
                        if(mb_strlen($value, 'UTF-8')> self::$textLimit)
                            $value = mb_substr($value, 0, self::$textLimit,'UTF-8') . ' ...';
                        $txtOut .= $value . "|";
                    }
                    $txtOut  .= "\r\n";
                    if($done>=($limit)) {
                        $starting = $startPos ? " начиная с $startPos-го" : '';
                        $endWarning = "показаны только $limit из $count моделей{$starting}, чтобы посмотреть продолжение, укаэите сколько пропустить: **@models 40** ";
                        break;
                    }
                }
                if($endWarning) $txtOut .= "\r\n _($endWarning}_";
            }

            if($this->debug) {
                $debugFileName = appEnv::getAppFolder('tmp/') . substr(basename($this->engineId), 0,-5) . '.txt';
                file_put_contents($debugFileName, $txtOut, FILE_APPEND);
            }
            return $txtOut;
        }
        # else $result = 'Возвращен не масив моделей: ';
        if($this->debug>1) writeDebugInfo("models final result: ", $result);
        return $result;
    }
    public static function getEngineInfo() {
        $ret = $this->providerName . ($this->model? ", model '. $this->model" : '');
        return $ret;
    }

}
