<?php
/**
* @name libs/openai.php
* реализация протокола OpenAI работы с LLM
* @version 0.10.001
* DeekSeek API docs по-русски: https://b.deepseek3.ru/api/
* Точки входа для некоторых LLM
* Deepseek: https://api.deepseek.com/v3
* OpenRouter: https://openrouter.ai/api/v1/chat/completions
* modified 2025-11-21 / created 2025-11-18
**/
namespace Libs\aiengines;

class OpenAI {
    private $providerName = '';
    private $apiKey = '';
    private $baseUrl = '';
    private $Temperature = 0.7;
    private $maxTokens = 1024;
    private $model = '';
    private $context = '';
    private $confg = [];
    private $errorMessage = '';
    private $headers = [];
    private $debug = 1;

    public function __construct($config=FALSE) {
        if(!empty($config)) $this->loadConfig($config);
    }
    public function makeHeaders() {
        $this->headers = [];
        if(!empty($this->apiKey))
            $this->headers['Authorization'] = 'Bearer ' . $this->apiKey;

        $this->headers['Content-Type'] = 'application/json';
    }

    public function loadConfig($cfg='') {
        $arCfg = [];
        if($this->debug) writeDebugInfo("loadConfig($cfg)...");
        if(is_string($cfg)) {
            if(strpos($cfg, '.php')===FALSE) # передано только имя настройки
                $cfgFile = \AppEnv::getAppFolder('cfg/'). "openai-$cfg.php";
            else
                $cfgFile = $cfg;

            if($this->debug) writeDebugInfo("cfgFile: [$cfgFile]");

            if(!is_file($cfgFile)) {
                if($this->debug) writeDebugInfo("Exiting with No cfgFile");
                $this->errorMessage = "No cfgFile for $cfg";
                return FALSE;
            }

            $this->confg = include($cfgFile);
        }
        elseif(is_array($cfg))
            $this->confg = $cfg;
        if($this->debug>1) writeDebugInfo("confgi passed: ", $this->confg);
        if(isset($this->confg['name'])) $this->providerName = $this->confg['name'];
        if(isset($this->confg['apiKey'])) {
            $this->apiKey = $this->confg['apiKey'];
            if($this->debug) writeDebugInfo("set apiKey to ", $this->confg['apiKey']);
        }
        if(isset($this->confg['baseUrl'])) {
            $this->baseUrl = $this->confg['baseUrl'];
            if($this->debug) writeDebugInfo("set baseUrl to ", $this->confg['baseUrl']);
        }
        if(isset($this->confg['Temperature'])) $this->Temperature = $this->confg['Temperature'];

        if(isset($this->confg['Model'])) $this->model = $this->confg['Model'];
        if(isset($this->confg['maxTokens'])) $this->maxTokens = $this->confg['maxTokens'];
    }
    public function setContext($context = '') {
        $this->context = $context;
        return $this;
    }
    public function request($request, $arHist = [], $context = '') {

        if(empty($request)) return 'Empty request string!';
        if(!empty($context)) $this->context = $context;
        if($request === '@models') {
            $result = $this->modelList();
            writeDebugInfo("model list returned: ", $result);
            return "models:<pre>". print_r($result, 1) . '</pre>';
        }
        $this->makeHeaders();
        /*
        $headers = [
          'Authorization' => 'Bearer ' . $this->apiKey,
          'Content-Type' => 'application/json',
          # 'HTTP-Referer' => 'My Site URL',
          # "X-Title": "<YOUR_SITE_NAME>"
        ];
        */
        $messages = [];
        if(!empty($this->context)) # задаю стартовый контекст/роль ассистента
            $messages[] = [ 'role'=>'system', 'content' => $this->context ];

        # заношу историю вопросов-ответов для создания контекста к данному вопросу
        if(count($arHist)) foreach($arHist as $item) {
            if(!empty($item['request']))
                $messages[] = [ 'role'=>'user', 'content' => $item['request'] ];

            if(!empty($item['response']))
                $messages[] = [ 'role'=>'assistant', 'content' => $item['response'] ];
        }
        $messages[] = [ 'role'=>'user', 'content' => $request ];

        $postFields = [
            'messages' => $messages,
            'stream' => false,
            'temperature' => $this->Temperature,
            # 'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            # 'presence_penalty' => 0,
            # 'response_format' => ['type'=>'text'],
        ];

        if(!empty($this->model))
            $postFields['model'] = $this->model;

        try {
            $chatUrl = $this->baseUrl.'chat/completions';
            if($this->debug) {
                writeDebugInfo("calling $chatUrl with params, ", $postFields, " headers: ", $this->headers);
            }
            $response = \Curla::getFromUrl($chatUrl,$postFields,60,$this->headers, TRUE);
            $responseData = @json_decode($response, TRUE);
            if($this->debug) {
                writeDebugInfo("response from AI / JSON: ", $response, "\n as array: ", $responseData);
            }
            if(isset($responseData['error'])) {
                $this->errorMessage = $responseData['error']['message'];
                $result = '{ERROR}: ' . $this->errorMessage;
            }
            else
                $result = $responseData['choices'][0]['message']['content'] ?? '{no-answer}';
        }
        catch(Exception $e) { $result = $e->getMessage(); }
        return $result;
        /*
        $client = DeepSeekClient::build(self::$apiKey, self::$baseUrl, 30, 'guzzle');

        $response = $client
            ->withModel($this->model)
            # ->withStream()
            ->setTemperature($this->Temperature)
            ->setMaxTokens(8192)
            ->setResponseFormat('text') // or "json_object"  with careful .
            ->query($request)
            ->run();
        return $response;
        */
    }
    /**
    * вернет список моделей, доступных для использования
    */
    public function modelList() {
        $limit = 40;
        # Включать только эти колонки:
        $colNames = ['id','name', 'owned_by', 'description'];
        $this->makeHeaders();
        $fmt = 'md';
        if(!empty($this->confg['modelsUrl']))
            $url = $this->confg['modelsUrl'];

        else {
            $url = $this->baseUrl . 'models';
        }
        if($this->debug) writeDebugInfo("for models list, call url: $url");
        try {
            $response = \Curla::getFromUrl($url,FALSE,30,$this->headers,2);
            if($this->debug) writeDebugInfo("/models request response: ", $response);
            $responseData = @json_decode($response, true);
            if(!empty($responseData['error'])) $result = $responseData['error'];
            else $result = $responseData['data'] ?? $responseData;
        }
        catch(Exception $e) { $result = $e->getMessage(); }
        # writeDebugInfo("result: ", $result);
        $endWarning = '';
        if(is_array($result)) { # Превращаю массив - список моделей в MarkDown
            $goodCols = array_intersect($colNames, array_keys($result[0]));
            $cntCols = count($goodCols);

            if($fmt === 'md') {
                $txtOut = "## Список поддерживаемых моделей\r\n|" . implode(" | ", $goodCols) . "|\r\n";
                $txtOut .= "|" . str_repeat("---|", $cntCols) . "\r\n";
                foreach($result as $no => $item) {
                    $txtOut .= "|";
                    foreach($goodCols as $colid) { $txtOut .= $item[$colid] . "|"; }
                    $txtOut  .= "\r\n";
                    if($no>=($limit-1)) {
                        $endWarning = "показаны только первые $limit моделей";
                        break;
                    }
                }
                if($endWarning) $txtOut .= "\r\n _($endWarning}_";
                file_put_contents("tmp/_models.txt", $txtOut);
                return $txtOut;
            }
            else { # в виде HTML таблицы
                $txtOut = "<table><thead><tr><th> id </th><th> canonical_slug </th><th> name </th><th> description</th></tr></thead><tbody>";
                foreach($result as $nom => $item) {
                    $txtOut .= "<tr><td>$item[id]</td><td>$item[canonical_slug] </td><td> $item[name] </td><td> $item[description]</td></tr>\n";
                    if($nom>=$limit) break; # слишком много не вывожу!
                }
                $txtOut .= "</tbody></table>";
                file_put_contents("tmp/_models.txt", $txtOut);
            }
        }
        if($this->debug) writeDebugInfo("models final result: ", $result);
        return $result;
    }
    public static function getEngineInfo() {
        $ret = $this->providerName . ($this->model? ", model '. $this->model" : '');
        return $ret;
    }

}
