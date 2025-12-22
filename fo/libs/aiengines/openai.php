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
    private $engineId = '';
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
    static $textLimit = 512; # ограпничитель текстовых значений в колонках models

    public function __construct($config=FALSE) {
        if(!empty($config)) $this->loadConfig($config);
    }
    public function makeHeaders() {
        if(!empty($this->confg['OauthUrl'])) {
            $apiKey = $this->getApiKey();
            if(empty($apiKey)) {
                writeDebugInfo("no apiToken after OAuth request, ", $apiKey);
                exit("Oauth return - no apiToken !");
            }
        }
        else $apiKey = $this->apiKey;

        $this->headers = [];

        if(!empty($this->apiKey))
            $this->headers['Authorization'] = 'Bearer ' . $apiKey;

        $this->headers['Content-Type'] = 'application/json';
    }

    public function loadConfig($cfg='') {
        $arCfg = [];
        if(is_string($cfg)) $this->engineId = $cfg;
        if($this->debug) writeDebugInfo("loadConfig($cfg)...");
        if(is_string($cfg)) {
            if(strpos($cfg, '.php')===FALSE) # передано только имя настройки
                $cfgFile = \AppEnv::getAppFolder('cfg/'). "openai-$cfg.php";
            else
                $cfgFile = $cfg;

            if($this->debug>1) writeDebugInfo("cfgFile: [$cfgFile]");

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
    # при наличии настройки OauthUrl перед любым запроосом надо авторизоваться по OAuth и получить ключ!
    public function getApiKey() {
        $headers = [
          'Content-Type' => 'application/x-www-form-urlencoded',
          'Accept' => 'application/json',
          'Authorization' => 'Basic '.$this->confg['apiKey'],
          'RqUID' => self::generateGUID(),
        ];

        $response = \Curla::getFromUrl($this->confg['OauthUrl'],FALSE,30,$headers);
        $arResponse = @json_decode($response, TRUE);
        writeDebugInfo("Oauth response: ", $response, " as array: ", $arResponse);
        $ret = $arResponse['access_token'] ?? FALSE;
        return $ret;
    }
    # Сьеру подавай GUID, мля.
    public static function generateGUID() {
        $ret = dechex(rand(hexdec('10000000'),hexdec('ffffffff')))
          . '-' . dechex(rand(hexdec('1000'),hexdec('ffff')))
          . '-' . dechex(rand(hexdec('1000'),hexdec('ffff')))
          . '-' . dechex(rand(hexdec('1000'),hexdec('ffff')))
          . '-' . dechex(rand(hexdec('100000000000'),hexdec('ffffffffffff')))
          ;     # 85995b73-a3bc-7bff-d2b2-7b367befe771
        return $ret;
    }
    public function request($request, $arHist = [], $context = '') {

        if(empty($request)) return 'Empty request string!';
        if(!empty($context)) $this->context = $context;
        /*
        $request = \RusUtils::mb_trim($request);
        $parts = preg_split("/[ \s/]/", $request);
        array_shift($parts);
        if($parts[0] === '@models') {
            $result = $this->modelList($parts);
            # writeDebugInfo("model list returned: ", $result);
            return "models:<pre>". print_r($result, 1) . '</pre>';
        }
        */
        $this->makeHeaders();

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
            $errCode = \Curla::getErrNo();
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
    * Формат Markdown !
    */
    public function modelList($params = FALSE) {

        $startPos = '';
        if($params) {
            $startPos = intval(is_array($params) ? array_shift($params) : $params);
        }
        # return "Запрошен список моделей с пропуском " . (string)($startPos);

        $limit = 40;
        # Включать только эти колонки:
        $colNames = ['id','name', 'owned_by', 'context_length','description'];
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
                    if($no < $startPos) continue;
                    $done++;
                    $txtOut .= "| ". ($no+1) . " |";

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

            if($this->debug) file_put_contents("tmp/_models-{$this->engineId}.txt", $txtOut);
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
